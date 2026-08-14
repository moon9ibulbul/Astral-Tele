<?php

$config = [
    'db' => [
        'host' => '127.0.0.1',
        'dbname' => 'astral_tele',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    's3' => [
        'endpoint' => 'https://s3.example.com',
        'region' => 'us-east-1',
        'key' => 'YOUR_S3_KEY',
        'secret' => 'YOUR_S3_SECRET',
        'bucket' => 'astral-tele-bucket'
    ],
    'telegram' => [
        'bot_token' => 'YOUR_TELEGRAM_BOT_TOKEN'
    ]
];

if (php_sapi_name() !== 'cli') {
    // Disable default HTML error display to avoid corrupting JSON output
    ini_set('display_errors', 0);
    ini_set('html_errors', 0);

    // Register global exception handler to return clean JSON
    set_exception_handler(function ($exception) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine()
        ]);
        exit;
    });

    // Register fatal error shutdown handler to return clean JSON
    register_shutdown_function(function () {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'error' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
            exit;
        }
    });
}