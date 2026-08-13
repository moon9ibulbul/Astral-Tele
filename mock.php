<?php
require 'db.php';
$db = getDbConnection();

// Create users table if not exists just in case (schema should have done it)
$db->exec("CREATE TABLE IF NOT EXISTS `users` (`id` BIGINT PRIMARY KEY, `role` VARCHAR(255) DEFAULT 'user')");
$db->exec("INSERT IGNORE INTO users (id, role) VALUES (1, 'admin')");

// Create comic
$db->exec("INSERT INTO comics (id, title) VALUES (1, 'Test Comic') ON DUPLICATE KEY UPDATE title='Test Comic'");

// Create chapters
$db->exec("INSERT INTO chapters (id, comic_id, chapter_number, title, pdf_url, is_adult, password, price) VALUES (1, 1, 1.0, 'Adult Chapter', 'dummy.pdf', 1, NULL, 0) ON DUPLICATE KEY UPDATE title='Adult Chapter'");
$db->exec("INSERT INTO chapters (id, comic_id, chapter_number, title, pdf_url, is_adult, password, price) VALUES (2, 1, 2.0, 'Locked Password', 'dummy.pdf', 0, '123', 0) ON DUPLICATE KEY UPDATE title='Locked Password'");
$db->exec("INSERT INTO chapters (id, comic_id, chapter_number, title, pdf_url, is_adult, password, price) VALUES (3, 1, 3.0, 'Locked Price', 'dummy.pdf', 0, NULL, 50) ON DUPLICATE KEY UPDATE title='Locked Price'");

echo "Mock data inserted.\n";
