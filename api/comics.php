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
            $catStmt = $db->prepare("SELECT c.id, c.name FROM categories c JOIN comic_categories cc ON c.id = cc.category_id WHERE cc.comic_id = ?");
            $catStmt->execute([$comic['id']]);
            $comic['categories'] = $catStmt->fetchAll();
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
    $join = "";

    if ($search) {
        $where[] = "c.title LIKE ?";
        $params[] = "%$search%";
    }
    if ($category) {
        $join = "JOIN comic_categories cc ON c.id = cc.comic_id JOIN categories cat ON cc.category_id = cat.id";
        $where[] = "cat.name = ?";
        $params[] = $category;
    }

    $whereSql = count($where) > 0 ? "WHERE " . implode(' AND ', $where) : "";

    $countStmt = $db->prepare("SELECT COUNT(DISTINCT c.id) FROM comics c $join $whereSql");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    $sql = "SELECT DISTINCT c.* FROM comics c $join $whereSql ORDER BY c.id DESC LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $comics = $stmt->fetchAll();
    
    // Fetch latest 2 chapters and categories for each comic
    foreach ($comics as &$comic) {
        $chStmt = $db->prepare("SELECT id, chapter_number, title FROM chapters WHERE comic_id = ? ORDER BY chapter_number DESC LIMIT 2");
        $chStmt->execute([$comic['id']]);
        $comic['latest_chapters'] = $chStmt->fetchAll();

        $catStmt = $db->prepare("SELECT cat.id, cat.name FROM categories cat JOIN comic_categories cc ON cat.id = cc.category_id WHERE cc.comic_id = ?");
        $catStmt->execute([$comic['id']]);
        $comic['categories'] = $catStmt->fetchAll();
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
    $action = $_POST['action'] ?? 'add';
    $id = $_POST['id'] ?? ($_GET['id'] ?? null);

    if ($action === 'edit' || (isset($_GET['id']) && $_POST)) {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required for edit']);
            exit;
        }

        $title = $_POST['title'] ?? '';
        $altTitle = $_POST['alternative_title'] ?? '';
        $author = $_POST['author'] ?? '';
        $artist = $_POST['artist'] ?? '';
        $publisher = $_POST['publisher'] ?? '';
        $synopsis = $_POST['synopsis'] ?? '';
        $categories = isset($_POST['categories']) ? explode(',', $_POST['categories']) : [];

        $stmt = $db->prepare("SELECT thumbnail_url FROM comics WHERE id = ?");
        $stmt->execute([$id]);
        $comic = $stmt->fetch();
        $thumbnailUrl = $comic['thumbnail_url'] ?? null;

        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['thumbnail']['tmp_name'];
            $fileName = 'thumbnails/' . time() . '_' . $_FILES['thumbnail']['name'];
            $thumbnailUrl = uploadToS3($tmpName, $fileName);
        }

        $stmt = $db->prepare("UPDATE comics SET title = ?, alternative_title = ?, author = ?, artist = ?, publisher = ?, synopsis = ?, thumbnail_url = ? WHERE id = ?");
        $stmt->execute([$title, $altTitle, $author, $artist, $publisher, $synopsis, $thumbnailUrl, $id]);

        $delStmt = $db->prepare("DELETE FROM comic_categories WHERE comic_id = ?");
        $delStmt->execute([$id]);

        if (!empty($categories)) {
            $catStmt = $db->prepare("INSERT INTO comic_categories (comic_id, category_id) VALUES (?, ?)");
            foreach ($categories as $catId) {
                $catId = (int)$catId;
                if ($catId > 0) {
                    $catStmt->execute([$id, $catId]);
                }
            }
        }

        echo json_encode(['success' => true]);
        exit;
    } else {
        // Add comic
        $title = $_POST['title'] ?? '';
        $altTitle = $_POST['alternative_title'] ?? '';
        $author = $_POST['author'] ?? '';
        $artist = $_POST['artist'] ?? '';
        $publisher = $_POST['publisher'] ?? '';
        $synopsis = $_POST['synopsis'] ?? '';
        $categories = isset($_POST['categories']) ? explode(',', $_POST['categories']) : [];

        $thumbnailUrl = null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['thumbnail']['tmp_name'];
            $fileName = 'thumbnails/' . time() . '_' . $_FILES['thumbnail']['name'];
            $thumbnailUrl = uploadToS3($tmpName, $fileName);
        }

        $stmt = $db->prepare("INSERT INTO comics (title, alternative_title, author, artist, publisher, synopsis, thumbnail_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $altTitle, $author, $artist, $publisher, $synopsis, $thumbnailUrl]);
        $comicId = $db->lastInsertId();

        if (!empty($categories)) {
            $catStmt = $db->prepare("INSERT INTO comic_categories (comic_id, category_id) VALUES (?, ?)");
            foreach ($categories as $catId) {
                $catId = (int)$catId;
                if ($catId > 0) {
                    $catStmt->execute([$comicId, $catId]);
                }
            }
        }

        echo json_encode(['success' => true, 'id' => $comicId]);
        exit;
    }
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