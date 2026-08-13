<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../s3.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = getDbConnection();
$period = $_GET['period'] ?? 'all'; // today, week, all
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;

$whereClause = "";
if ($period === 'today') {
    $whereClause = "WHERE rh.read_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
} else if ($period === 'week') {
    $whereClause = "WHERE rh.read_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
}

$sql = "
    SELECT c.id, c.title, c.thumbnail_url, COUNT(rh.user_id) as views_count
    FROM reading_history rh
    JOIN chapters ch ON rh.chapter_id = ch.id
    JOIN comics c ON ch.comic_id = c.id
    $whereClause
    GROUP BY c.id
    ORDER BY views_count DESC
    LIMIT $limit
";

$stmt = $db->prepare($sql);
$stmt->execute();
$popular = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['data' => $popular]);
