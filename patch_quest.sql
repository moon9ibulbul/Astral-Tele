ALTER TABLE `users` ADD COLUMN `pts` INT DEFAULT 0;

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
