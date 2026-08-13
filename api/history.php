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

    // Also increment the comic views
    $stmtComic = $db->prepare("
        UPDATE comics c
        JOIN chapters ch ON ch.comic_id = c.id
        SET c.views = c.views + 1
        WHERE ch.id = ?
    ");
    $stmtComic->execute([$chapterId]);

    // Keep only the 100 most recent reading histories for the user
    // We do this by finding the read_at timestamp of the 100th record
    // and deleting anything older.
    $stmtCount = $db->prepare("SELECT COUNT(*) FROM reading_history WHERE user_id = ?");
    $stmtCount->execute([$user['id']]);
    $historyCount = $stmtCount->fetchColumn();

    if ($historyCount > 100) {
        $stmtOffset = $db->prepare("
            SELECT read_at
            FROM reading_history
            WHERE user_id = ?
            ORDER BY read_at DESC
            LIMIT 1 OFFSET 99
        ");
        $stmtOffset->execute([$user['id']]);
        $thresholdDate = $stmtOffset->fetchColumn();

        if ($thresholdDate) {
            $stmtDel = $db->prepare("DELETE FROM reading_history WHERE user_id = ? AND read_at < ?");
            $stmtDel->execute([$user['id'], $thresholdDate]);
        }
    }

    echo json_encode(['success' => true]);
}
