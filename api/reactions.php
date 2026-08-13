<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

if ($method === 'GET') {
    $chapterId = $_GET['chapter_id'] ?? null;
    if (!$chapterId) {
        http_response_code(400);
        echo json_encode(['error' => 'chapter_id required']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT reaction_type, COUNT(*) as count
        FROM chapter_reactions
        WHERE chapter_id = ?
        GROUP BY reaction_type
    ");
    $stmt->execute([$chapterId]);
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [
        'Happy' => 0,
        'Sad' => 0,
        'Laugh' => 0,
        'Angry' => 0,
        'Fire' => 0,
    ];
    foreach ($counts as $row) {
        if (isset($result[$row['reaction_type']])) {
            $result[$row['reaction_type']] = (int)$row['count'];
        }
    }

    // Get current user's reaction if authenticated
    $userReaction = null;
    $user = getAuthenticatedUser();
    if ($user) {
        $stmtUser = $db->prepare("SELECT reaction_type FROM chapter_reactions WHERE chapter_id = ? AND user_id = ?");
        $stmtUser->execute([$chapterId, $user['id']]);
        $userReactionRow = $stmtUser->fetch();
        if ($userReactionRow) {
            $userReaction = $userReactionRow['reaction_type'];
        }
    }

    echo json_encode(['data' => $result, 'user_reaction' => $userReaction]);
    exit;
}

if ($method === 'POST') {
    $user = getAuthenticatedUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $chapterId = $_POST['chapter_id'] ?? null;
    $reactionType = $_POST['reaction_type'] ?? null;

    if (!$chapterId || !$reactionType) {
        http_response_code(400);
        echo json_encode(['error' => 'chapter_id and reaction_type required']);
        exit;
    }

    $validReactions = ['Happy', 'Sad', 'Laugh', 'Angry', 'Fire'];
    if (!in_array($reactionType, $validReactions)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid reaction type']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO chapter_reactions (chapter_id, user_id, reaction_type)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type)
    ");
    $stmt->execute([$chapterId, $user['id'], $reactionType]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
