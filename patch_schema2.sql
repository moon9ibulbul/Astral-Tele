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

-- Note: In production we'd want this, but for now we'll do it if possible without dropping data.
ALTER TABLE `users` ADD UNIQUE (`username`);
