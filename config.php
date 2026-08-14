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
    ],
    'app' => [
        'admin_id' => 'YOUR_TELEGRAM_ADMIN_ID'
    ]
];