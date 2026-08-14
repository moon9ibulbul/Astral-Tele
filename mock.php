<?php
require_once __DIR__ . '/db.php';
$db = getDbConnection();

// Check if tables are fully initialized (e.g. check if comics table exists)
try {
    $stmt = $db->query("SELECT 1 FROM `comics` LIMIT 1");
} catch (\PDOException $e) {
    // Comics table doesn't exist, let's initialize the database schema from schema.sql
    echo "Comics table not found. Initializing database schema from schema.sql...\n";
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        die("Error: schema.sql file not found in " . __DIR__ . "\n");
    }

    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        die("Error: Could not read schema.sql\n");
    }

    try {
        $db->exec($sql);
        echo "Database schema successfully initialized.\n";
    } catch (\PDOException $schemaException) {
        die("Error executing schema.sql: " . $schemaException->getMessage() . "\n");
    }
}

// Create users table if not exists just in case (schema should have done it)
$db->exec("CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT PRIMARY KEY,
    `username` VARCHAR(255) NULL UNIQUE,
    `first_name` VARCHAR(255) NULL,
    `last_name` VARCHAR(255) NULL,
    `photo_url` TEXT NULL,
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `is_banned` TINYINT(1) DEFAULT 0,
    `is_muted` TINYINT(1) DEFAULT 0,
    `pts` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

$db->exec("INSERT IGNORE INTO users (id, role, username, first_name) VALUES (1, 'admin', 'admin', 'Admin')");

// Create categories
$categories = ['Action', 'Adventure', 'Fantasy', 'Comedy', 'Romance'];
$catStmt = $db->prepare("INSERT INTO categories (id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
foreach ($categories as $index => $catName) {
    $catStmt->execute([$index + 1, $catName]);
}

// Create comic
$db->exec("INSERT INTO comics (id, title, thumbnail_url, synopsis, status) VALUES (1, 'Test Comic', 'https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?w=300', 'This is a sample test comic synopsis.', 'Ongoing') ON DUPLICATE KEY UPDATE title='Test Comic', thumbnail_url='https://images.unsplash.com/photo-1607604276583-eef5d076aa5f?w=300', synopsis='This is a sample test comic synopsis.', status='Ongoing'");

// Link comic with categories
$db->exec("INSERT IGNORE INTO comic_categories (comic_id, category_id) VALUES (1, 1)");
$db->exec("INSERT IGNORE INTO comic_categories (comic_id, category_id) VALUES (1, 3)");

// Create chapters
$db->exec("INSERT INTO chapters (id, comic_id, chapter_number, title, pdf_url, is_adult, password, price) VALUES (1, 1, 1.0, 'Adult Chapter', 'dummy.pdf', 1, NULL, 0) ON DUPLICATE KEY UPDATE title='Adult Chapter'");
$db->exec("INSERT INTO chapters (id, comic_id, chapter_number, title, pdf_url, is_adult, password, price) VALUES (2, 1, 2.0, 'Locked Password', 'dummy.pdf', 0, '123', 0) ON DUPLICATE KEY UPDATE title='Locked Password'");
$db->exec("INSERT INTO chapters (id, comic_id, chapter_number, title, pdf_url, is_adult, password, price) VALUES (3, 1, 3.0, 'Locked Price', 'dummy.pdf', 0, NULL, 50) ON DUPLICATE KEY UPDATE title='Locked Price'");

echo "Mock data inserted.\n";
