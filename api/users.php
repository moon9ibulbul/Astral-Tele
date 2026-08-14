<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

// Require Admin Authentication
$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($method === 'GET') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $limit = 10; // Max 10 users per page as requested
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? $_GET['search'] : '';

    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = "(username LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereSql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";

    // Get total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM users $whereSql");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    // Fetch users
    $sql = "
        SELECT id, username, first_name, last_name, photo_url, role, is_banned, is_muted, created_at
        FROM users
        $whereSql
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'data' => $users,
        'page' => $page,
        'totalPages' => max(1, ceil($total / $limit))
    ]);
    exit;
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = $_POST['user_id'] ?? null;

    if (!$userId || !$action) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id and action are required']);
        exit;
    }

    // Prevent admin from banning or muting themselves
    if ((string)$userId === (string)$user['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'You cannot ban or mute yourself.']);
        exit;
    }

    if ($action === 'ban') {
        $stmt = $db->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
        $stmt->execute([$userId]);
    } else if ($action === 'unban') {
        $stmt = $db->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
        $stmt->execute([$userId]);
    } else if ($action === 'mute') {
        $stmt = $db->prepare("UPDATE users SET is_muted = 1 WHERE id = ?");
        $stmt->execute([$userId]);
    } else if ($action === 'unmute') {
        $stmt = $db->prepare("UPDATE users SET is_muted = 0 WHERE id = ?");
        $stmt->execute([$userId]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
