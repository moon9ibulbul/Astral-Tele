CREATE TABLE IF NOT EXISTS `users` (
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
);

CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE
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
    `average_rating` DECIMAL(3, 2) DEFAULT 0.00,
    `views` INT DEFAULT 0,
    `year` INT NULL,
    `status` ENUM('Ongoing', 'Completed', 'On Hold', 'Hiatus', 'Dropped') DEFAULT 'Ongoing',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `comic_categories` (
    `comic_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    PRIMARY KEY (`comic_id`, `category_id`),
    FOREIGN KEY (`comic_id`) REFERENCES `comics`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `chapters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `comic_id` INT NOT NULL,
    `chapter_number` DECIMAL(6, 2) NOT NULL,
    `title` VARCHAR(255) NULL,
    `pdf_url` TEXT NOT NULL,
    `is_adult` BOOLEAN DEFAULT FALSE,
    `password` VARCHAR(255) NULL,
    `pdf_password` VARCHAR(255) NULL,
    `price` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`comic_id`) REFERENCES `comics`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `unlocked_chapters` (
    `user_id` BIGINT NOT NULL,
    `chapter_id` INT NOT NULL,
    `unlocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `chapter_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `bookmarks` (
    `user_id` BIGINT NOT NULL,
    `comic_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `comic_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`comic_id`) REFERENCES `comics`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `reading_history` (
    `user_id` BIGINT NOT NULL,
    `chapter_id` INT NOT NULL,
    `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `chapter_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `chapter_reactions` (
    `chapter_id` INT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `reaction_type` ENUM('Happy', 'Sad', 'Laugh', 'Angry', 'Fire') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`chapter_id`, `user_id`),
    FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
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
    `is_read` BOOLEAN DEFAULT FALSE,
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

CREATE TABLE IF NOT EXISTS `quests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(100) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `reward_pts` INT DEFAULT 0,
    `period` ENUM('daily', 'weekly') DEFAULT 'daily',
    `is_active` BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS `user_quests` (
    `user_id` BIGINT NOT NULL,
    `quest_id` INT NOT NULL,
    `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `quest_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE CASCADE
);

INSERT IGNORE INTO `quests` (`id`, `type`, `title`, `reward_pts`, `period`, `is_active`) VALUES
(1, 'login', 'Daily Login', 10, 'daily', 1),
(2, 'read', 'Read 1 Chapter', 20, 'daily', 1),
(3, 'review', 'Post 1 Review', 30, 'daily', 1),
(4, 'react', 'React to Chapter', 15, 'daily', 1),
(5, 'unlock', 'Unlock Paid Chapter', 50, 'weekly', 1);
