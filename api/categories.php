<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

if ($method === 'GET') {
    $stmt = $db->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
    echo json_encode(['data' => $categories]);
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
    $name = trim($_POST['name'] ?? '');
    if (!$name) {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
    try {
        $stmt->execute([$name]);
        echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'name' => $name]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Category already exists or error occurred']);
    }
    exit;
}

if ($method === 'PUT') {
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = $_GET['id'] ?? null;
    $name = trim($_PUT['name'] ?? '');

    if (!$id || !$name) {
        http_response_code(400);
        echo json_encode(['error' => 'ID and Name required']);
        exit;
    }

    $stmt = $db->prepare("UPDATE categories SET name = ? WHERE id = ?");
    try {
        $stmt->execute([$name, $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(400);
        echo json_encode(['error' => 'Error updating category']);
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

    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);