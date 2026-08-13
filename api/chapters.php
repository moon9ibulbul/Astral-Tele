<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../s3.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

if ($method === 'GET') {
    $comicId = $_GET['comic_id'] ?? null;
    $chapterId = $_GET['id'] ?? null;
    
    if ($chapterId) {
        $stmt = $db->prepare("SELECT * FROM chapters WHERE id = ?");
        $stmt->execute([$chapterId]);
        echo json_encode($stmt->fetch());
        exit;
    }
    
    if ($comicId) {
        $stmt = $db->prepare("SELECT * FROM chapters WHERE comic_id = ? ORDER BY chapter_number DESC");
        $stmt->execute([$comicId]);
        echo json_encode($stmt->fetchAll());
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['error' => 'comic_id or id required']);
    exit;
}

// Below methods require Admin auth
$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($method === 'POST') {
    $comicId = $_POST['comic_id'] ?? null;
    $chapterNumber = $_POST['chapter_number'] ?? null;
    $title = $_POST['title'] ?? '';

    if (!$comicId || !$chapterNumber) {
        http_response_code(400);
        echo json_encode(['error' => 'comic_id and chapter_number required']);
        exit;
    }

    $pdfUrl = null;
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['pdf']['tmp_name'];
        $fileName = 'pdfs/' . time() . '_' . $_FILES['pdf']['name'];
        $pdfUrl = uploadToS3($tmpName, $fileName);
    }

    if (!$pdfUrl) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to upload PDF']);
        exit;
    }

    // Check if reuploading an existing chapter
    $stmt = $db->prepare("SELECT id FROM chapters WHERE comic_id = ? AND chapter_number = ?");
    $stmt->execute([$comicId, $chapterNumber]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("UPDATE chapters SET pdf_url = ?, title = ? WHERE id = ?");
        $stmt->execute([$pdfUrl, $title, $existing['id']]);
        echo json_encode(['success' => true, 'id' => $existing['id'], 'action' => 'reupload']);
    } else {
        $stmt = $db->prepare("INSERT INTO chapters (comic_id, chapter_number, title, pdf_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$comicId, $chapterNumber, $title, $pdfUrl]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'action' => 'upload']);
    }
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM chapters WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);