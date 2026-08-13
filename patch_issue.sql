ALTER TABLE `comics`
ADD COLUMN `views` INT DEFAULT 0,
ADD COLUMN `year` INT NULL,
ADD COLUMN `status` ENUM('Ongoing', 'Completed', 'On Hold', 'Hiatus', 'Dropped') DEFAULT 'Ongoing';

CREATE TABLE IF NOT EXISTS `chapter_reactions` (
    `chapter_id` INT NOT NULL,
    `user_id` BIGINT NOT NULL,
    `reaction_type` ENUM('Happy', 'Sad', 'Laugh', 'Angry', 'Fire') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`chapter_id`, `user_id`),
    FOREIGN KEY (`chapter_id`) REFERENCES `chapters`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
