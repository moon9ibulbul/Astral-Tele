<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../s3.php';
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

// Allow reading active reviews without auth
if ($method === 'GET') {
    $comicId = $_GET['comic_id'] ?? null;
    
    if (!$comicId) {
        http_response_code(400);
        echo json_encode(['error' => 'comic_id required']);
        exit;
    }

    $isAdmin = false;
    $user = getAuthenticatedUser();
    if ($user && $user['role'] === 'admin' && isset($_GET['all']) && $_GET['all'] == '1') {
        $isAdmin = true;
    }

    if ($isAdmin) {
        $stmt = $db->prepare("
            SELECT r.*, u.username, u.photo_url 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.comic_id = ?
            ORDER BY r.created_at DESC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT r.*, u.username, u.photo_url 
            FROM reviews r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.comic_id = ? AND r.status = 'active'
            ORDER BY r.created_at DESC
        ");
    }
    $stmt->execute([$comicId]);
    $reviews = $stmt->fetchAll();

    // Group into threads
    $threads = [];
    $replies = [];
    foreach ($reviews as $review) {
        if ($review['parent_id']) {
            $replies[$review['parent_id']][] = $review;
        } else {
            $threads[] = $review;
        }
    }

    foreach ($threads as &$thread) {
        $thread['replies'] = $replies[$thread['id']] ?? [];
    }

    echo json_encode($threads);
    exit;
}

$user = getAuthenticatedUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

if ($method === 'POST') {
    // Action could be 'add', 'like', 'dislike'
    $action = $_POST['action'] ?? 'add';

    if ($action === 'add' || $action === 'edit') {
        if (isset($user['is_muted']) && $user['is_muted']) {
            http_response_code(403);
            echo json_encode(['error' => 'You are muted and cannot post or edit comments.']);
            exit;
        }
    }
    
    if ($action === 'add') {
        $comicId = $_POST['comic_id'] ?? null;
        $content = $_POST['content'] ?? '';
        $rating = $_POST['rating'] ?? null;
        $parentId = $_POST['parent_id'] ?? null;

        if (!$comicId || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'comic_id and content required']);
            exit;
        }

        if (!$parentId) {
            $checkStmt = $db->prepare("SELECT id FROM reviews WHERE comic_id = ? AND user_id = ? AND parent_id IS NULL");
            $checkStmt->execute([$comicId, $user['id']]);
            if ($checkStmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'You have already reviewed this comic. Please edit your existing review instead.']);
                exit;
            }
        }

        $imageUrl = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['image']['tmp_name'];
            $fileName = 'reviews/' . time() . '_' . $_FILES['image']['name'];
            $imageUrl = uploadToS3($tmpName, $fileName);
        }

        $stmt = $db->prepare("INSERT INTO reviews (comic_id, user_id, parent_id, rating, content, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$comicId, $user['id'], $parentId, $rating, $content, $imageUrl]);
        
        // Update comic average rating if it's a top-level review with rating
        if (!$parentId && $rating) {
            $avgStmt = $db->prepare("SELECT AVG(rating) FROM reviews WHERE comic_id = ? AND parent_id IS NULL AND rating IS NOT NULL AND status = 'active'");
            $avgStmt->execute([$comicId]);
            $avgRating = $avgStmt->fetchColumn();
            
            $updateComic = $db->prepare("UPDATE comics SET average_rating = ? WHERE id = ?");
            $updateComic->execute([$avgRating, $comicId]);
        }
        
        echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
        exit;
    }
    
    if ($action === 'like' || $action === 'dislike') {
        $reviewId = $_POST['review_id'] ?? null;
        if (!$reviewId) {
            http_response_code(400);
            echo json_encode(['error' => 'review_id required']);
            exit;
        }
        
        // Simple implementation: delete any existing like/dislike, insert new one, update counts
        $db->prepare("DELETE FROM review_likes WHERE review_id = ? AND user_id = ?")->execute([$reviewId, $user['id']]);
        
        $db->prepare("INSERT INTO review_likes (review_id, user_id, type) VALUES (?, ?, ?)")->execute([$reviewId, $user['id'], $action]);
        
        // Update counts
        $db->prepare("
            UPDATE reviews SET 
            likes = (SELECT COUNT(*) FROM review_likes WHERE review_id = ? AND type = 'like'),
            dislikes = (SELECT COUNT(*) FROM review_likes WHERE review_id = ? AND type = 'dislike')
            WHERE id = ?
        ")->execute([$reviewId, $reviewId, $reviewId]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'edit') {
        $reviewId = $_POST['review_id'] ?? null;
        $content = $_POST['content'] ?? '';
        $rating = $_POST['rating'] ?? null;

        if (!$reviewId || !$content) {
            http_response_code(400);
            echo json_encode(['error' => 'review_id and content required']);
            exit;
        }

        // Verify ownership
        $stmt = $db->prepare("SELECT id, comic_id, parent_id FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->execute([$reviewId, $user['id']]);
        $review = $stmt->fetch();

        if (!$review) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorized to edit this review']);
            exit;
        }

        if ($rating) {
            $update = $db->prepare("UPDATE reviews SET content = ?, rating = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$content, $rating, $reviewId]);
        } else {
            $update = $db->prepare("UPDATE reviews SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $update->execute([$content, $reviewId]);
        }

        // Update average rating if it's a top-level review
        if (!$review['parent_id']) {
            $avgStmt = $db->prepare("SELECT AVG(rating) FROM reviews WHERE comic_id = ? AND parent_id IS NULL AND rating IS NOT NULL AND status = 'active'");
            $avgStmt->execute([$review['comic_id']]);
            $avgRating = $avgStmt->fetchColumn() ?? 0;

            $updateComic = $db->prepare("UPDATE comics SET average_rating = ? WHERE id = ?");
            $updateComic->execute([$avgRating, $review['comic_id']]);
        }

        echo json_encode(['success' => true]);
        exit;
    }
}

// Admin only actions
if ($user['role'] === 'admin' && $method === 'PUT') {
    parse_str(file_get_contents("php://input"), $_PUT);
    $reviewId = $_GET['id'] ?? null;
    $status = $_PUT['status'] ?? null; // 'hidden', 'spam', 'active'
    
    if ($reviewId && $status) {
        $stmt = $db->prepare("UPDATE reviews SET status = ? WHERE id = ?");
        $stmt->execute([$status, $reviewId]);
        echo json_encode(['success' => true]);
        exit;
    }
}

if ($user['role'] === 'admin' && $method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    if ($id) {
        $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed or bad request']);