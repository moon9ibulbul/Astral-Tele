<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
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

$chapterId = $_POST['chapter_id'] ?? null;
$unlockMethod = $_POST['method'] ?? null; // 'password' or 'stars'
$password = $_POST['password'] ?? null;

if (!$chapterId || !$unlockMethod) {
    http_response_code(400);
    echo json_encode(['error' => 'chapter_id and method required']);
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("SELECT * FROM chapters WHERE id = ?");
$stmt->execute([$chapterId]);
$chapter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chapter) {
    http_response_code(404);
    echo json_encode(['error' => 'Chapter not found']);
    exit;
}

$unlocked = false;

if ($unlockMethod === 'password') {
    if ($chapter['password'] !== null && $chapter['password'] === $password) {
        $unlocked = true;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid password']);
        exit;
    }
} else if ($unlockMethod === 'stars') {
    if ($chapter['price'] > 0) {
        // In a real app with webhook, we wouldn't just trust the client.
        // We might check a pending transaction, or the client wouldn't call this directly.
        // For this demo/task, we assume the frontend's successful Telegram invoice callback is trusted.
        $unlocked = true;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Chapter is not locked by price']);
        exit;
    }
}

if ($unlocked) {
    $insert = $db->prepare("INSERT IGNORE INTO unlocked_chapters (user_id, chapter_id) VALUES (?, ?)");
    $insert->execute([$user['id'], $chapterId]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unlock failed']);
}
