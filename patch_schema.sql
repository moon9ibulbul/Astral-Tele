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
