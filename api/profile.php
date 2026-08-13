<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../s3.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
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

$db = getDbConnection();

if ($method === 'GET') {
    $stmt = $db->prepare("SELECT id, role, username, photo_url, first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    echo json_encode($stmt->fetch());
    exit;
}

if ($method === 'POST') {
    $username = $_POST['username'] ?? '';

    // Check username uniqueness if updating
    if ($username !== '') {
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $checkStmt->execute([$username, $user['id']]);
        if ($checkStmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Username already exists']);
            exit;
        }
    }

    $photoUrl = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['photo']['tmp_name'];

        // Resize logic with PHP GD
        $info = getimagesize($tmpName);
        if ($info === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid image file']);
            exit;
        }

        $width = $info[0];
        $height = $info[1];
        $mime = $info['mime'];

        switch ($mime) {
            case 'image/jpeg': $image = imagecreatefromjpeg($tmpName); break;
            case 'image/png': $image = imagecreatefrompng($tmpName); break;
            case 'image/gif': $image = imagecreatefromgif($tmpName); break;
            case 'image/webp': $image = imagecreatefromwebp($tmpName); break;
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Unsupported image type']);
                exit;
        }

        $newWidth = 500;
        $newHeight = 500;

        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // preserve transparency
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Crop/resize (cover behavior)
        $ratio = max($newWidth / $width, $newHeight / $height);
        $srcH = $newHeight / $ratio;
        $srcW = $newWidth / $ratio;
        $srcX = ($width - $srcW) / 2;
        $srcY = ($height - $srcH) / 2;

        imagecopyresampled($resizedImage, $image, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $srcW, $srcH);

        $resizedTmp = tempnam(sys_get_temp_dir(), 'resized_');
        imagejpeg($resizedImage, $resizedTmp, 90);

        imagedestroy($image);
        imagedestroy($resizedImage);

        $fileName = 'profiles/' . time() . '_' . $user['id'] . '.jpg';
        $photoUrl = uploadToS3($resizedTmp, $fileName);
        unlink($resizedTmp);

        if (!$photoUrl) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to upload image']);
            exit;
        }
    }

    if ($photoUrl) {
        if ($username !== '') {
            $stmt = $db->prepare("UPDATE users SET username = ?, photo_url = ? WHERE id = ?");
            $stmt->execute([$username, $photoUrl, $user['id']]);
        } else {
            $stmt = $db->prepare("UPDATE users SET photo_url = ? WHERE id = ?");
            $stmt->execute([$photoUrl, $user['id']]);
        }
    } else {
        if ($username !== '') {
            $stmt = $db->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $user['id']]);
        }
    }

    echo json_encode(['success' => true, 'photo_url' => $photoUrl, 'username' => $username]);
}
