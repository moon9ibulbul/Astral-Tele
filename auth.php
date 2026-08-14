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
    global $config;
    $db = getDbConnection();
    
    $stmt = $db->prepare("SELECT id, role, is_banned, is_muted FROM users WHERE id = ?");
    $stmt->execute([$userData['id']]);
    $existingUser = $stmt->fetch();
    
    $firstName = $userData['first_name'] ?? '';
    $lastName = $userData['last_name'] ?? '';
    $username = $userData['username'] ?? '';
    $photoUrl = $userData['photo_url'] ?? '';

    $isAdmin = false;
    if (isset($config['app']['admin_id']) && (string)$userData['id'] === (string)$config['app']['admin_id']) {
        $isAdmin = true;
    }

    if ($existingUser) {
        $updateStmt = $db->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, photo_url = ? WHERE id = ?");
        $updateStmt->execute([$firstName, $lastName, $username, $photoUrl, $userData['id']]);

        $role = $existingUser['role'];
        if ($isAdmin && $existingUser['role'] !== 'admin') {
            $roleUpdateStmt = $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $roleUpdateStmt->execute([$userData['id']]);
            $role = 'admin';
        }

        return [
            'id' => $userData['id'],
            'role' => $role,
            'is_banned' => (int)$existingUser['is_banned'],
            'is_muted' => (int)$existingUser['is_muted']
        ];
    } else {
        $role = $isAdmin ? 'admin' : 'user';
        $insertStmt = $db->prepare("INSERT INTO users (id, first_name, last_name, username, photo_url, role) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$userData['id'], $firstName, $lastName, $username, $photoUrl, $role]);
        return [
            'id' => $userData['id'],
            'role' => $role,
            'is_banned' => 0,
            'is_muted' => 0
        ];
    }
}

function getAuthenticatedUser() {
    // For local testing purposes where we can't easily fake Telegram initData
    if (php_sapi_name() === 'cli-server') {
        return ['id' => 1, 'role' => 'admin', 'is_banned' => 0, 'is_muted' => 0];
    }

    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $initData = $matches[1];
            $telegramUser = validateTelegramInitData($initData);
            if ($telegramUser) {
                $user = syncUser($telegramUser);
                if ($user && isset($user['is_banned']) && $user['is_banned']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Your account has been banned.']);
                    exit;
                }
                return $user;
            }
        }
    }
    return null;
}