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
    // Return all read chapters for a given comic
    $comicId = $_GET['comic_id'] ?? null;
    if ($comicId) {
        $stmt = $db->prepare("
            SELECT rh.chapter_id
            FROM reading_history rh
            JOIN chapters c ON rh.chapter_id = c.id
            WHERE rh.user_id = ? AND c.comic_id = ?
        ");
        $stmt->execute([$user['id'], $comicId]);
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'comic_id required']);
    }
    exit;
}

if ($method === 'POST') {
    $chapterId = $_POST['chapter_id'] ?? null;

    if (!$chapterId) {
        http_response_code(400);
        echo json_encode(['error' => 'chapter_id required']);
        exit;
    }

    $stmt = $db->prepare("
        INSERT INTO reading_history (user_id, chapter_id) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user['id'], $chapterId]);

    echo json_encode(['success' => true]);
}
