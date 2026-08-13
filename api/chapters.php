<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../s3.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

function extractS3Key($url) {
    global $config;
    $path = parse_url($url, PHP_URL_PATH);
    $bucketPrefix = '/' . $config['s3']['bucket'] . '/';
    if (strpos($path, $bucketPrefix) === 0) {
        return substr($path, strlen($bucketPrefix));
    }
    // Try without bucket prefix if endpoint already includes it or style differs
    if (strpos($path, '/pdfs/') !== false) {
        return substr($path, strpos($path, '/pdfs/') + 1);
    }
    return $url; // Fallback
}

if ($method === 'GET') {
    $comicId = $_GET['comic_id'] ?? null;
    $chapterId = $_GET['id'] ?? null;
    $user = getAuthenticatedUser();
    
    if ($chapterId) {
        $stmt = $db->prepare("SELECT * FROM chapters WHERE id = ?");
        $stmt->execute([$chapterId]);
        $chapter = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($chapter) {
            $chapter['locked'] = false;
            $chapter['has_password'] = !empty($chapter['password']);
            unset($chapter['password']); // Never expose password hash/plaintext

            $needsUnlock = false;
            if ($chapter['has_password'] || $chapter['price'] > 0) {
                if (!$user || $user['role'] !== 'admin') {
                    $needsUnlock = true;
                    if ($user) {
                        $check = $db->prepare("SELECT 1 FROM unlocked_chapters WHERE user_id = ? AND chapter_id = ?");
                        $check->execute([$user['id'], $chapterId]);
                        if ($check->fetch()) {
                            $needsUnlock = false;
                        }
                    }
                }
            }

            if ($needsUnlock) {
                $chapter['locked'] = true;
                $chapter['pdf_url'] = null; // Do not expose PDF url if locked
            } else {
                // Generate presigned URL
                $key = extractS3Key($chapter['pdf_url']);
                $chapter['pdf_url'] = getPresignedUrl($key);
            }
            echo json_encode($chapter);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Chapter not found']);
        }
        exit;
    }
    
    if ($comicId) {
        $stmt = $db->prepare("SELECT id, comic_id, chapter_number, title, is_adult, price, password IS NOT NULL AND password != '' as has_password, created_at FROM chapters WHERE comic_id = ? ORDER BY chapter_number DESC");
        $stmt->execute([$comicId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
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
    $isAdult = isset($_POST['is_adult']) && $_POST['is_adult'] === '1' ? 1 : 0;
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    $price = isset($_POST['price']) ? (int)$_POST['price'] : 0;

    if (!$comicId || !$chapterNumber) {
        http_response_code(400);
        echo json_encode(['error' => 'comic_id and chapter_number required']);
        exit;
    }

    $pdfUrl = null;
    // Check if reuploading an existing chapter
    $stmt = $db->prepare("SELECT id, pdf_url FROM chapters WHERE comic_id = ? AND chapter_number = ?");
    $stmt->execute([$comicId, $chapterNumber]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['pdf']['tmp_name'];
        $fileName = 'pdfs/' . time() . '_' . $_FILES['pdf']['name'];
        $pdfUrl = uploadToS3($tmpName, $fileName);
        if (!$pdfUrl) {
            http_response_code(400);
            echo json_encode(['error' => 'Failed to upload PDF']);
            exit;
        }
    } else if ($existing) {
        $pdfUrl = $existing['pdf_url'];
    }

    if (!$pdfUrl) {
        http_response_code(400);
        echo json_encode(['error' => 'PDF file required']);
        exit;
    }

    if ($existing) {
        $stmt = $db->prepare("UPDATE chapters SET pdf_url = ?, title = ?, is_adult = ?, password = ?, price = ? WHERE id = ?");
        $stmt->execute([$pdfUrl, $title, $isAdult, $password, $price, $existing['id']]);
        echo json_encode(['success' => true, 'id' => $existing['id'], 'action' => 'reupload']);
    } else {
        $stmt = $db->prepare("INSERT INTO chapters (comic_id, chapter_number, title, pdf_url, is_adult, password, price) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$comicId, $chapterNumber, $title, $pdfUrl, $isAdult, $password, $price]);
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
