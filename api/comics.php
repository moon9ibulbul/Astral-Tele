<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../s3.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

// Simple router
if ($method === 'GET') {
    // Check if fetching single comic by ID
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM comics WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $comic = $stmt->fetch();
        if ($comic) {
            echo json_encode(['data' => [$comic]]); // Wrapping in array to match structure or just return obj
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
        }
        exit;
    }

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';

    $where = [];
    $params = [];

    if ($search) {
        $where[] = "title LIKE ?";
        $params[] = "%$search%";
    }
    if ($category) {
        $where[] = "category = ?";
        $params[] = $category;
    }

    $whereSql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";

    $countStmt = $db->prepare("SELECT COUNT(*) FROM comics $whereSql");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    $sql = "SELECT * FROM comics $whereSql ORDER BY id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $comics = $stmt->fetchAll();
    
    // Fetch latest 2 chapters for each comic
    foreach ($comics as &$comic) {
        $chStmt = $db->prepare("SELECT id, chapter_number, title FROM chapters WHERE comic_id = ? ORDER BY chapter_number DESC LIMIT 2");
        $chStmt->execute([$comic['id']]);
        $comic['latest_chapters'] = $chStmt->fetchAll();
    }

    echo json_encode([
        'data' => $comics,
        'page' => $page,
        'totalPages' => ceil($total / $limit)
    ]);
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
    // Add comic
    $title = $_POST['title'] ?? '';
    $altTitle = $_POST['alternative_title'] ?? '';
    $author = $_POST['author'] ?? '';
    $artist = $_POST['artist'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $synopsis = $_POST['synopsis'] ?? '';
    $category = $_POST['category'] ?? '';

    $thumbnailUrl = null;
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['thumbnail']['tmp_name'];
        $fileName = 'thumbnails/' . time() . '_' . $_FILES['thumbnail']['name'];
        $thumbnailUrl = uploadToS3($tmpName, $fileName);
    }

    $stmt = $db->prepare("INSERT INTO comics (title, alternative_title, author, artist, publisher, synopsis, category, thumbnail_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $altTitle, $author, $artist, $publisher, $synopsis, $category, $thumbnailUrl]);
    
    echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        exit;
    }
    
    // Update basic text fields for simplicity (multipart/form-data with PUT is tricky in raw PHP)
    $title = $_PUT['title'] ?? '';
    $category = $_PUT['category'] ?? '';
    $stmt = $db->prepare("UPDATE comics SET title = ?, category = ? WHERE id = ?");
    $stmt->execute([$title, $category, $id]);
    
    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM comics WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);