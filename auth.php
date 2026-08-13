<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function validateTelegramInitData($initData) {
    global $config;
    $botToken = $config['telegram']['bot_token'];

    parse_str($initData, $data);
    if (!isset($data['hash'])) {
        return false;
    }
    
    $hash = $data['hash'];
    unset($data['hash']);
    
    $dataCheckString = [];
    foreach ($data as $k => $v) {
        $dataCheckString[] = $k . '=' . $v;
    }
    sort($dataCheckString);
    $dataCheckString = implode("\n", $dataCheckString);
    
    $secretKey = hash_hmac('sha256', $botToken, "WebAppData", true);
    $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));
    
    if (strcmp($hash, $calculatedHash) === 0) {
        return json_decode($data['user'], true);
    }
    return false;
}

function syncUser($userData) {
    $db = getDbConnection();
    
    $stmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$userData['id']]);
    $existingUser = $stmt->fetch();
    
    $firstName = $userData['first_name'] ?? '';
    $lastName = $userData['last_name'] ?? '';
    $username = $userData['username'] ?? '';
    $photoUrl = $userData['photo_url'] ?? '';

    if ($existingUser) {
        $updateStmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, photo_url = ? WHERE id = ?");
        $updateStmt->execute([$firstName, $lastName, $username, $photoUrl, $userData['id']]);
        return ['id' => $userData['id'], 'role' => $existingUser['role']];
    } else {
        $insertStmt = $db->prepare("INSERT INTO users (id, first_name, last_name, username, photo_url) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->execute([$userData['id'], $firstName, $lastName, $username, $photoUrl]);
        return ['id' => $userData['id'], 'role' => 'user'];
    }
}

function getAuthenticatedUser() {
    // For local testing purposes where we can't easily fake Telegram initData
    if (php_sapi_name() === 'cli-server') {
        return ['id' => 1, 'role' => 'admin'];
    }

    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $initData = $matches[1];
            $telegramUser = validateTelegramInitData($initData);
            if ($telegramUser) {
                return syncUser($telegramUser);
            }
        }
    }
    return null;
}