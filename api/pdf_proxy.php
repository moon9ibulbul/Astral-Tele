<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../s3.php';
require_once __DIR__ . '/../auth.php';

$chapterId = $_GET['id'] ?? null;
if (!$chapterId) {
    http_response_code(400);
    echo "ID required";
    exit;
}

$db = getDbConnection();
$stmt = $db->prepare("SELECT pdf_url, password, pdf_password, price FROM chapters WHERE id = ?");
$stmt->execute([$chapterId]);
$chapter = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$chapter || empty($chapter['pdf_password'])) {
    http_response_code(404);
    echo "Encrypted chapter not found";
    exit;
}

// Verify authorization
$user = getAuthenticatedUser();
$authorized = false;

$needsUnlock = false;
if (!empty($chapter['password']) || $chapter['price'] > 0) {
    $needsUnlock = true;
}

if ($user && $user['role'] === 'admin') {
    $authorized = true;
} else if (!$needsUnlock) {
    $authorized = true;
} else if ($user) {
    $check = $db->prepare("SELECT 1 FROM unlocked_chapters WHERE user_id = ? AND chapter_id = ?");
    $check->execute([$user['id'], $chapterId]);
    if ($check->fetch()) {
        $authorized = true;
    }
}

if (!$authorized) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

// Fetch encrypted file from S3
function extractS3KeyProxy($url) {
    global $config;
    $path = parse_url($url, PHP_URL_PATH);
    $bucketPrefix = '/' . $config['s3']['bucket'] . '/';
    if (strpos($path, $bucketPrefix) === 0) {
        return substr($path, strlen($bucketPrefix));
    }
    if (strpos($path, '/pdfs/') !== false) {
        return substr($path, strpos($path, '/pdfs/') + 1);
    }
    return $url;
}

$key = extractS3KeyProxy($chapter['pdf_url']);
$presignedUrl = getPresignedUrl($key);

$encryptedContent = @file_get_contents($presignedUrl);
if ($encryptedContent === false) {
    http_response_code(500);
    echo "Failed to fetch file";
    exit;
}

// Decrypt
$password = $chapter['pdf_password'];
$aesKey = hash('sha256', $password, true);
$ivLength = openssl_cipher_iv_length('aes-256-cbc');
$iv = substr($encryptedContent, 0, $ivLength);
$ciphertext = substr($encryptedContent, $ivLength);

$decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $aesKey, OPENSSL_RAW_DATA, $iv);

if ($decrypted === false) {
    http_response_code(500);
    echo "Decryption failed";
    exit;
}

header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($decrypted));
header('Cache-Control: private, max-age=3600');
echo $decrypted;
