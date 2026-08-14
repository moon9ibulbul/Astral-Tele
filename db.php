<?php
require_once __DIR__ . '/config.php';

function getDbConnection() {
    if (defined('TEST_MODE') && TEST_MODE) {
        static $sqliteDb = null;
        if ($sqliteDb === null) {
            $sqliteDb = new PDO('sqlite::memory:');
            $sqliteDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sqliteDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $sqliteDb->exec("CREATE TABLE IF NOT EXISTS users (
                id BIGINT PRIMARY KEY,
                username VARCHAR(255) NULL,
                first_name VARCHAR(255) NULL,
                last_name VARCHAR(255) NULL,
                photo_url TEXT NULL,
                role TEXT DEFAULT 'user',
                is_banned INT DEFAULT 0,
                is_muted INT DEFAULT 0
            )");
        }
        return $sqliteDb;
    }

    global $config;
    
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};charset={$config['db']['charset']}";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $config['db']['user'], $config['db']['pass'], $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}