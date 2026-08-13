<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getAuthenticatedUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$db = getDbConnection();

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'count') {
        // Count unread replies to the user's reviews
        $stmt = $db->prepare("
            SELECT COUNT(*)
            FROM reviews r
            JOIN reviews p ON r.parent_id = p.id
            WHERE p.user_id = ? AND r.user_id != ? AND r.is_read = FALSE AND r.status = 'active'
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $count = $stmt->fetchColumn();
        echo json_encode(['count' => $count]);
        exit;
    }

    if ($action === 'list') {
        // List all replies to the user's reviews
        $stmt = $db->prepare("
            SELECT r.id, r.content, r.created_at, r.is_read, r.comic_id, u.username
            FROM reviews r
            JOIN reviews p ON r.parent_id = p.id
            JOIN users u ON r.user_id = u.id
            WHERE p.user_id = ? AND r.user_id != ? AND r.status = 'active'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $notifications = $stmt->fetchAll();
        echo json_encode(['data' => $notifications]);
        exit;
    }
}

if ($method === 'POST') {
    $action = $_POST['action'] ?? null;

    if ($action === 'read') {
        $notificationId = $_POST['id'] ?? null;

        if ($notificationId) {
            // Check if the reply's parent belongs to the user
            $checkStmt = $db->prepare("
                SELECT r.id FROM reviews r
                JOIN reviews p ON r.parent_id = p.id
                WHERE r.id = ? AND p.user_id = ?
            ");
            $checkStmt->execute([$notificationId, $user['id']]);

            if ($checkStmt->fetchColumn()) {
                $stmt = $db->prepare("UPDATE reviews SET is_read = TRUE WHERE id = ?");
                $stmt->execute([$notificationId]);
                echo json_encode(['success' => true]);
                exit;
            }
        }
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
