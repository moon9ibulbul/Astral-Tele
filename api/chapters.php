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
            $hasPdfPassword = !empty($chapter['pdf_password']);
            unset($chapter['password']); // Never expose password hash/plaintext
            unset($chapter['pdf_password']); // Never expose

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
                if ($hasPdfPassword) {
                    $chapter['pdf_url'] = '/api/pdf_proxy.php?id=' . $chapterId;
                } else {
                    // Generate presigned URL
                    $key = extractS3Key($chapter['pdf_url']);
                    $chapter['pdf_url'] = getPresignedUrl($key);
                }
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
        echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // For admin or general chapter listing
    if (!$comicId && !$chapterId) {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $search = isset($_GET['search']) ? $_GET['search'] : '';

        $whereSql = "";
        $params = [];
        if ($search) {
            $whereSql = "WHERE c.title LIKE ?";
            $params[] = "%$search%";
        }

        $countStmt = $db->prepare("SELECT COUNT(*) FROM chapters ch JOIN comics c ON ch.comic_id = c.id $whereSql");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        $sql = "
            SELECT ch.id, ch.comic_id, ch.chapter_number, ch.title, ch.pdf_url, ch.created_at, c.title as comic_title
            FROM chapters ch
            JOIN comics c ON ch.comic_id = c.id
            $whereSql
            ORDER BY c.title ASC, ch.chapter_number DESC
            LIMIT $limit OFFSET $offset
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'page' => $page,
            'totalPages' => ceil($total / $limit)
        ]);
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
    $pdfPassword = !empty($_POST['pdf_password']) ? $_POST['pdf_password'] : null;
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

        if (!empty($pdfPassword)) {
            // Encrypt PDF if pdf_password is set
            $pdfContent = file_get_contents($tmpName);
            $key = hash('sha256', $pdfPassword, true);
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
            $encryptedContent = openssl_encrypt($pdfContent, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            file_put_contents($tmpName, $iv . $encryptedContent);
        }

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
        // Only update pdf_password if it's explicitly provided, otherwise keep existing
        $pdfPasswordToUpdate = $pdfPassword;
        if (empty($pdfPassword)) {
            $existingDataStmt = $db->prepare("SELECT pdf_password FROM chapters WHERE id = ?");
            $existingDataStmt->execute([$existing['id']]);
            $existingData = $existingDataStmt->fetch();
            $pdfPasswordToUpdate = $existingData['pdf_password'];
        }

        $stmt = $db->prepare("UPDATE chapters SET pdf_url = ?, title = ?, is_adult = ?, password = ?, pdf_password = ?, price = ? WHERE id = ?");
        $stmt->execute([$pdfUrl, $title, $isAdult, $password, $pdfPasswordToUpdate, $price, $existing['id']]);
        echo json_encode(['success' => true, 'id' => $existing['id'], 'action' => 'reupload']);
    } else {
        $stmt = $db->prepare("INSERT INTO chapters (comic_id, chapter_number, title, pdf_url, is_adult, password, pdf_password, price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$comicId, $chapterNumber, $title, $pdfUrl, $isAdult, $password, $pdfPassword, $price]);
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
