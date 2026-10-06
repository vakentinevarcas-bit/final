CREATE DATABASE IF NOT EXISTS `cadiz_go` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cadiz_go`;

DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `page_views`;
DROP TABLE IF EXISTS `tracker_locations`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `landmarks`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `location` VARCHAR(100) DEFAULT 'Cadiz City',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `feedback` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `place_name` VARCHAR(255) NOT NULL,
    `user_name` VARCHAR(100) NOT NULL DEFAULT 'Alex Johnson',
    `rating` INT NOT NULL,
    `comment` TEXT,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `sender` ENUM('user','admin') NOT NULL DEFAULT 'user',
    `message` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `security_code` VARCHAR(255) NOT NULL DEFAULT '0000',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `landmarks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `lat` DECIMAL(10,8) NOT NULL,
    `lng` DECIMAL(11,8) NOT NULL,
    `photo` LONGTEXT,
    `category` VARCHAR(50) NOT NULL DEFAULT 'historical',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tracker_locations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL DEFAULT 0,
    `lat` DOUBLE NOT NULL,
    `lng` DOUBLE NOT NULL,
    `accuracy` DOUBLE NULL,
    `device_info` VARCHAR(255) DEFAULT NULL,
    `device_type` VARCHAR(50) DEFAULT NULL,
    `device_model` VARCHAR(100) DEFAULT NULL,
    `os_version` VARCHAR(100) DEFAULT NULL,
    `browser_name` VARCHAR(100) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `battery_level` FLOAT DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT 'online',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    INDEX (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `page_views` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL DEFAULT 0,
    `page_name` VARCHAR(255) NOT NULL DEFAULT '',
    `content_type` VARCHAR(50) DEFAULT NULL,
    `content_data` LONGTEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (`user_id`),
    INDEX (`page_name`),
    INDEX (`visited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admin_users` (`username`, `password`, `full_name`, `security_code`) VALUES
('admin@gmail.com', '$2y$10$rQ481NJ48Wp2V2zQPm4W1u8yJGer3UYYulSbz602c/ALLDJtoVJkG', 'Administrator', '$2y$10$Z6U.tpeRF.hEhRr5WC.RHehLL/ny/7CpTsjcxo68XYYatkYnk0gGO');

INSERT INTO `users` (`username`, `password`, `full_name`, `location`) VALUES
('alex', '$2y$10$jxsrWKxSxjVoRGJyaAVS9eW7jnKU5NYJxkSakONT3jmpzDnW5POtO', 'Alex Johnson', 'Cadiz City');

INSERT INTO `landmarks` (`name`, `description`, `lat`, `lng`, `photo`, `category`) VALUES
('Cadiz City Plaza', 'Historic center of the city with gardens and walking paths.', 10.95040000, 123.30810000, 'https://images.unsplash.com/photo-1519098901906-b1683be0294a?auto=format&fit=crop&w=500&q=80', 'historical'),
('Patag Beach', 'White sand beach with tranquil waters, ideal for relaxation and sunset viewing.', 10.97000000, 123.33000000, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=500&q=80', 'beaches'),
('Lakawon Island Resort', 'A famous white sandbar resort with crystal clear waters and floating bars.', 11.03867000, 123.20274000, 'https://images.unsplash.com/photo-1590523278191-995c9d150b9d?auto=format&fit=crop&w=500&q=80', 'beaches'),
('Aluyan Beach Resort', 'A peaceful sunset cove and beach resort with pristine shoreline.', 10.92250000, 123.29650000, 'https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=500&q=80', 'beaches'),
('City Mall Cadiz', 'Modern department store offering shopping, dynamic dining options and movies.', 10.95150000, 123.30900000, 'https://images.unsplash.com/photo-1567449303078-07ccbda7bb50?auto=format&fit=crop&w=500&q=80', 'shopping'),
('SM Hypermarket', 'Your one-stop grocery and retail center for all household needs.', 10.95200000, 123.30850000, 'https://images.unsplash.com/photo-1534452203293-494d7ddbf7e0?auto=format&fit=crop&w=500&q=80', 'shopping'),
('San Diego Cathedral', 'Historic old cathedral in the city center with magnificent architecture.', 10.94900000, 123.30700000, 'https://images.unsplash.com/photo-1585829365295-f3f7b5f1c5b7?auto=format&fit=crop&w=500&q=80', 'historical'),
('Seafood House', 'Local cuisine featuring fresh catch-of-the-day seafood and local recipes.', 10.95300000, 123.31200000, 'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=500&q=80', 'food'),
('Cadiz Food Park', 'A vibrant evening food park and night market offering local street foods.', 10.94950000, 123.30900000, 'https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=500&q=80', 'food'),
('Cadiz Public Market', 'Busy local market featuring fresh local produce and sea harvests.', 10.94800000, 123.31000000, 'https://images.unsplash.com/photo-1584346133934-a3afd2a33c4c?auto=format&fit=crop&w=500&q=80', 'shopping'),
('Cadiz City Hall', 'The administrative and governing seat of local government.', 10.95130000, 123.30290000, NULL, 'historical'),
('Hilantagaan Island View', 'Scenic elevated view of the nearby Hilantagaan Island.', 10.95500000, 123.31000000, NULL, 'beaches');

INSERT INTO `feedback` (`place_name`, `user_name`, `rating`, `comment`, `created_at`) VALUES
('Cadiz City Plaza', 'John Doe', 5, 'Beautiful gardens and historical monument, loved it!', NOW() - INTERVAL 10 DAY),
('Cadiz City Plaza', 'Sarah K.', 4, 'Very clean and relaxing plaza right in the center.', NOW() - INTERVAL 8 DAY),
('Patag Beach', 'Mike R.', 4, 'Fine white sands and fantastic sunset view. Must visit!', NOW() - INTERVAL 5 DAY),
('Lakawon Island Resort', 'Jessica W.', 5, 'Absolutely spectacular sandbar and the floating bar is awesome!', NOW() - INTERVAL 3 DAY),
('Aluyan Beach Resort', 'Alex P.', 4, 'Quiet, peaceful cove with awesome sunset vistas.', NOW() - INTERVAL 2 DAY),
('City Mall Cadiz', 'Emily S.', 4, 'Convenient place to eat and shop inside Cadiz.', NOW() - INTERVAL 1 DAY);
