<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = getAuthenticatedUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$tab = $_GET['tab'] ?? 'history';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$db = getDbConnection();

if ($tab === 'history') {
    // Count total for pagination
    $countSql = "SELECT COUNT(DISTINCT ch.comic_id) FROM reading_history rh JOIN chapters ch ON rh.chapter_id = ch.id WHERE rh.user_id = ?";
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute([$user['id']]);
    $total = $stmtCount->fetchColumn();

    // Get latest read chapter for each comic the user has in history
    $sql = "
        SELECT c.id as comic_id, c.title as comic_title, c.thumbnail_url,
               ch.id as chapter_id, ch.chapter_number, ch.title as chapter_title, rh.read_at
        FROM reading_history rh
        JOIN chapters ch ON rh.chapter_id = ch.id
        JOIN comics c ON ch.comic_id = c.id
        WHERE rh.user_id = ?
        AND rh.read_at = (
            SELECT MAX(read_at)
            FROM reading_history rh2
            JOIN chapters ch2 ON rh2.chapter_id = ch2.id
            WHERE rh2.user_id = rh.user_id AND ch2.comic_id = c.id
        )
        ORDER BY rh.read_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user['id']]);
    echo json_encode(['data' => $stmt->fetchAll(), 'page' => $page, 'totalPages' => ceil($total / $limit)]);
} elseif ($tab === 'bookmarks') {
    // Count total
    $countSql = "SELECT COUNT(*) FROM bookmarks WHERE user_id = ?";
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute([$user['id']]);
    $total = $stmtCount->fetchColumn();

    // Get bookmarked comics sorted by latest chapter updates
    $sql = "
        SELECT c.id as comic_id, c.title as comic_title, c.thumbnail_url
        FROM bookmarks b
        JOIN comics c ON b.comic_id = c.id
        WHERE b.user_id = ?
        ORDER BY (
            SELECT MAX(created_at) FROM chapters WHERE comic_id = c.id
        ) DESC, b.created_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user['id']]);
    echo json_encode(['data' => $stmt->fetchAll(), 'page' => $page, 'totalPages' => ceil($total / $limit)]);
} elseif ($tab === 'purchased') {
    // Count total
    $countSql = "SELECT COUNT(*) FROM unlocked_chapters uc JOIN chapters ch ON uc.chapter_id = ch.id WHERE uc.user_id = ? AND ch.price > 0";
    $stmtCount = $db->prepare($countSql);
    $stmtCount->execute([$user['id']]);
    $total = $stmtCount->fetchColumn();

    // Get chapters bought with stars (unlocked_chapters where price > 0)
    $sql = "
        SELECT c.id as comic_id, c.title as comic_title, c.thumbnail_url,
               ch.id as chapter_id, ch.chapter_number, ch.title as chapter_title, uc.unlocked_at
        FROM unlocked_chapters uc
        JOIN chapters ch ON uc.chapter_id = ch.id
        JOIN comics c ON ch.comic_id = c.id
        WHERE uc.user_id = ? AND ch.price > 0
        ORDER BY uc.unlocked_at DESC
        LIMIT $limit OFFSET $offset
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$user['id']]);
    echo json_encode(['data' => $stmt->fetchAll(), 'page' => $page, 'totalPages' => ceil($total / $limit)]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tab']);
}
