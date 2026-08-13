CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT PRIMARY KEY,
    `username` VARCHAR(255) NULL,
    `first_name` VARCHAR(255) NULL,
    `last_name` VARCHAR(255) NULL,
    `photo_url` TEXT NULL,
    `role` ENUM('user', 'admin') DEFAULT 'user',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `comics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `alternative_title` VARCHAR(255) NULL,
    `author` VARCHAR(255) NULL,
    `artist` VARCHAR(255) NULL,
    `publisher` VARCHAR(255) NULL,
    `synopsis` TEXT NULL,
    `thumbnail_url` TEXT NULL,
    `category` VARCHAR(100) NULL,
    `average_rating` DECIMAL(3, 2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `chapters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `comic_id` INT NOT NULL,
    `chapter_number` DECIMAL(6, 2) NOT NULL,
    `title` VARCHAR(255) NULL,
    `pdf_url` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`comic_id`) REFERENCES `comics`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `comic_id` INT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `parent_id` INT NULL,
    `rating` INT NULL,
    `content` TEXT NOT NULL,
    `image_url` TEXT NULL,
    `status` ENUM('active', 'hidden', 'spam') DEFAULT 'active',
    `likes` INT DEFAULT 0,
    `dislikes` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`comic_id`) REFERENCES `comics`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `review_likes` (
    `review_id` INT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `type` ENUM('like', 'dislike') NOT NULL,
    PRIMARY KEY (`review_id`, `user_id`),
    FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);