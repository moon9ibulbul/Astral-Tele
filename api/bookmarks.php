<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$user = getAuthenticatedUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDbConnection();

if ($method === 'GET') {
    $comicId = $_GET['comic_id'] ?? null;
    if (!$comicId) {
        http_response_code(400);
        echo json_encode(['error' => 'comic_id required']);
        exit;
    }

    $stmt = $db->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND comic_id = ?");
    $stmt->execute([$user['id'], $comicId]);
    $bookmarked = $stmt->fetch() !== false;

    echo json_encode(['bookmarked' => $bookmarked]);
    exit;
}

if ($method === 'POST') {
    $comicId = $_POST['comic_id'] ?? null;
    $action = $_POST['action'] ?? null; // 'toggle', 'add', 'remove'

    if (!$comicId || !$action) {
        http_response_code(400);
        echo json_encode(['error' => 'comic_id and action required']);
        exit;
    }

    if ($action === 'toggle') {
        $stmt = $db->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND comic_id = ?");
        $stmt->execute([$user['id'], $comicId]);
        if ($stmt->fetch()) {
            $del = $db->prepare("DELETE FROM bookmarks WHERE user_id = ? AND comic_id = ?");
            $del->execute([$user['id'], $comicId]);
            echo json_encode(['success' => true, 'bookmarked' => false]);
        } else {
            $add = $db->prepare("INSERT INTO bookmarks (user_id, comic_id) VALUES (?, ?)");
            $add->execute([$user['id'], $comicId]);
            echo json_encode(['success' => true, 'bookmarked' => true]);
        }
    }
}
