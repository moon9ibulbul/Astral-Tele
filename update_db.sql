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
