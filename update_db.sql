CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS `comic_categories` (
    `comic_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    PRIMARY KEY (`comic_id`, `category_id`),
    FOREIGN KEY (`comic_id`) REFERENCES `comics`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
);

INSERT IGNORE INTO `categories` (`name`) VALUES ('Action'), ('Romance'), ('Fantasy'), ('Comedy');
ALTER TABLE `chapters`
ADD COLUMN `is_adult` BOOLEAN DEFAULT FALSE,
ADD COLUMN `password` VARCHAR(255) NULL,
ADD COLUMN `price` INT DEFAULT 0;

CREATE TABLE IF NOT EXISTS `unlocked_chapters` (
    `user_id` BIGINT NOT NULL,
    `chapter_id` INT NOT NULL,
    `unlocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `chapter_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`id`) ON DELETE CASCADE
);
