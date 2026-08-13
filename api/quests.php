<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/quests_helper.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();
$user = getAuthenticatedUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit();
}

if ($method === 'GET') {
    // Admin checking all quests
    if ($user['role'] === 'admin' && isset($_GET['admin']) && $_GET['admin'] == '1') {
        $stmt = $db->query("SELECT * FROM quests ORDER BY id ASC");
        echo json_encode(['quests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    // User checking their quests and pts
    $stmtUser = $db->prepare("SELECT pts FROM users WHERE id = ?");
    $stmtUser->execute([$user['id']]);
    $pts = $stmtUser->fetchColumn() ?: 0;

    // Fetch active quests and completion status
    $stmtQuests = $db->prepare("
        SELECT q.id, q.type, q.title, q.reward_pts, q.period,
        (
            SELECT 1 FROM user_quests uq
            WHERE uq.quest_id = q.id AND uq.user_id = ? AND
            (
                (q.period = 'daily' AND DATE(uq.completed_at) = CURRENT_DATE()) OR
                (q.period = 'weekly' AND YEARWEEK(uq.completed_at, 1) = YEARWEEK(CURRENT_DATE(), 1))
            )
        ) as completed
        FROM quests q
        WHERE q.is_active = TRUE
        ORDER BY q.id ASC
    ");
    $stmtQuests->execute([$user['id']]);
    $quests = $stmtQuests->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['pts' => $pts, 'quests' => $quests]);
    exit();
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $success = recordQuestProgress($user['id'], 'login');
        echo json_encode(['success' => $success]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit();
}

if ($method === 'PUT' && $user['role'] === 'admin') {
    parse_str(file_get_contents("php://input"), $_PUT);

    $quests = json_decode($_PUT['quests'] ?? '[]', true);

    // Validate: Admin can select exactly 5 quests? Prompt says "Admin bisa memilih 5 Quest dari beberapa quest"
    // Let's just update based on the data sent from the client
    $activeCount = 0;
    foreach ($quests as $q) {
        if (!empty($q['is_active']) || filter_var($q['is_active'], FILTER_VALIDATE_BOOLEAN)) $activeCount++;
    }

    if ($activeCount > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Max 5 quests can be active']);
        exit();
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE quests SET reward_pts = ?, period = ?, is_active = ? WHERE id = ?");
        foreach ($quests as $q) {
            $isActive = (!empty($q['is_active']) || filter_var($q['is_active'], FILTER_VALIDATE_BOOLEAN)) ? 1 : 0;
            $stmt->execute([
                (int)$q['reward_pts'],
                $q['period'],
                $isActive,
                (int)$q['id']
            ]);
        }
        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
