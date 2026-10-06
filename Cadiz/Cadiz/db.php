<?php
$host = 'localhost';
$username = 'root';
$password = '';
$dbname = 'cadiz_go';

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Helper to safely ensure a table exists and is queryable (auto-heals MySQL error 1932 / 1813 corruption)
    function cadizEnsureTable(PDO $pdo, string $dbname, string $tableName, string $createSQL) {
        // Step 1: Verify if table is functional and readable
        try {
            $pdo->query("SELECT 1 FROM `$tableName` LIMIT 1");
            return; // Table is healthy!
        } catch (PDOException $e) {
            // Table missing or corrupted in InnoDB data dictionary (Error 1932 / 1813)
        }

        // Step 2: Purge dictionary definition
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$tableName`");
        } catch (PDOException $e) {}

        // Step 3: Purge orphan .ibd and .frm files from MySQL data folder
        try {
            $datadir = $pdo->query("SELECT @@datadir")->fetchColumn();
            if ($datadir) {
                $ibdPath = rtrim($datadir, '/\\') . DIRECTORY_SEPARATOR . $dbname . DIRECTORY_SEPARATOR . $tableName . '.ibd';
                $frmPath = rtrim($datadir, '/\\') . DIRECTORY_SEPARATOR . $dbname . DIRECTORY_SEPARATOR . $tableName . '.frm';
                if (file_exists($ibdPath)) {
                    @unlink($ibdPath);
                }
                if (file_exists($frmPath)) {
                    @unlink($frmPath);
                }
            }
        } catch (Throwable $t) {}

        // Step 4: Try creating table fresh
        try {
            $pdo->exec($createSQL);
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE `$tableName` DISCARD TABLESPACE");
                $pdo->exec("DROP TABLE IF EXISTS `$tableName`");
                $pdo->exec($createSQL);
            } catch (PDOException $e2) {}
        }

        // Step 5: Final verification - ensure table can be queried
        try {
            $pdo->query("SELECT 1 FROM `$tableName` LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$tableName`");
                $cleanCreate = str_replace("CREATE TABLE IF NOT EXISTS", "CREATE TABLE", $createSQL);
                $pdo->exec($cleanCreate);
            } catch (PDOException $e2) {
                error_log("Table $tableName creation final exception: " . $e2->getMessage());
            }
        }
    }

    $createFeedbackSQL = "CREATE TABLE IF NOT EXISTS `feedback` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `place_name` VARCHAR(255) NOT NULL,
        `user_name` VARCHAR(100) NOT NULL DEFAULT 'Alex Johnson',
        `rating` INT NOT NULL,
        `comment` TEXT,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    cadizEnsureTable($pdo, $dbname, 'feedback', $createFeedbackSQL);

    $createUsersSQL = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(50) UNIQUE NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `full_name` VARCHAR(100) NOT NULL,
        `location` VARCHAR(100) DEFAULT 'Cadiz City',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    cadizEnsureTable($pdo, $dbname, 'users', $createUsersSQL);

    $createChatSQL = "CREATE TABLE IF NOT EXISTS `chat_messages` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT NOT NULL,
        `user_name`  VARCHAR(100) NOT NULL,
        `sender`     ENUM('user','admin') NOT NULL DEFAULT 'user',
        `message`    TEXT NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    cadizEnsureTable($pdo, $dbname, 'chat_messages', $createChatSQL);

    $createAdminSQL = "CREATE TABLE IF NOT EXISTS `admin_users` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `username`      VARCHAR(50) UNIQUE NOT NULL,
        `password`      VARCHAR(255) NOT NULL,
        `full_name`     VARCHAR(100) NOT NULL,
        `security_code` VARCHAR(255) NOT NULL DEFAULT '0000',
        `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    cadizEnsureTable($pdo, $dbname, 'admin_users', $createAdminSQL);

    $createLandmarksSQL = "CREATE TABLE IF NOT EXISTS `landmarks` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `name`        VARCHAR(255) NOT NULL,
        `description` TEXT,
        `lat`         DECIMAL(10, 8) NOT NULL,
        `lng`         DECIMAL(11, 8) NOT NULL,
        `photo`       LONGTEXT,
        `category`    VARCHAR(50) NOT NULL DEFAULT 'historical',
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    // Check if landmarks category column exists
    try {
        $tableInfo = $pdo->query("SHOW COLUMNS FROM `landmarks` LIKE 'category'")->fetch();
        if (!$tableInfo) {
            $pdo->exec("DROP TABLE IF EXISTS `landmarks`");
        }
    } catch (PDOException $e) {
    }
    cadizEnsureTable($pdo, $dbname, 'landmarks', $createLandmarksSQL);

    // Seed admin_users if empty
    try {
        $checkAdminEmpty = $pdo->query("SELECT COUNT(*) FROM `admin_users`")->fetchColumn();
        if ($checkAdminEmpty == 0) {
            $adminHashed = password_hash('admin123', PASSWORD_DEFAULT);
            $defaultCodeHash = password_hash('0000', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO `admin_users` (`username`, `password`, `full_name`, `security_code`) VALUES
                ('admin@gmail.com', '$adminHashed', 'Administrator', '$defaultCodeHash')");
        }
    } catch (PDOException $e) {}

    // Seed users if empty
    try {
        $checkUsersEmpty = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        if ($checkUsersEmpty == 0) {
            $hashedPassword = password_hash('alex123', PASSWORD_DEFAULT);
            $seedUsersSQL = "INSERT INTO `users` (`username`, `password`, `full_name`, `location`) VALUES
                ('alex', '$hashedPassword', 'Alex Johnson', 'Cadiz City')";
            $pdo->exec($seedUsersSQL);
        }
    } catch (PDOException $e) {}

    // Seed landmarks if empty
    try {
        $checkLandmarksEmpty = $pdo->query("SELECT COUNT(*) FROM `landmarks`")->fetchColumn();
        if ($checkLandmarksEmpty == 0) {
            $stmt = $pdo->prepare("INSERT INTO `landmarks` (`name`, `description`, `lat`, `lng`, `photo`, `category`) VALUES (?, ?, ?, ?, ?, ?)");
            
            $seeds = [
                ['Cadiz City Plaza', 'Historic center of the city with gardens and walking paths.', 10.9504, 123.3081, 'https://images.unsplash.com/photo-1519098901906-b1683be0294a?auto=format&fit=crop&w=500&q=80', 'historical'],
                ['Patag Beach', 'White sand beach with tranquil waters, ideal for relaxation and sunset viewing.', 10.9700, 123.3300, 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=500&q=80', 'beaches'],
                ['Lakawon Island Resort', 'A famous white sandbar resort with crystal clear waters and floating bars.', 11.03867, 123.20274, 'https://images.unsplash.com/photo-1590523278191-995c9d150b9d?auto=format&fit=crop&w=500&q=80', 'beaches'],
                ['Aluyan Beach Resort', 'A peaceful sunset cove and beach resort with pristine shoreline.', 10.9225, 123.2965, 'https://images.unsplash.com/photo-1519046904884-53103b34b206?auto=format&fit=crop&w=500&q=80', 'beaches'],
                ['City Mall Cadiz', 'Modern department store offering shopping, dynamic dining options and movies.', 10.9515, 123.3090, 'https://images.unsplash.com/photo-1567449303078-07ccbda7bb50?auto=format&fit=crop&w=500&q=80', 'shopping'],
                ['SM Hypermarket', 'Your one-stop grocery and retail center for all household needs.', 10.9520, 123.3085, 'https://images.unsplash.com/photo-1534452203293-494d7ddbf7e0?auto=format&fit=crop&w=500&q=80', 'shopping'],
                ['San Diego Cathedral', 'Historic old cathedral in the city center with magnificent architecture.', 10.9490, 123.3070, 'https://images.unsplash.com/photo-1585829365295-f3f7b5f1c5b7?auto=format&fit=crop&w=500&q=80', 'historical'],
                ['Seafood House', 'Local cuisine featuring fresh catch-of-the-day seafood and local recipes.', 10.9530, 123.3120, 'https://images.unsplash.com/photo-1559339352-11d035aa65de?auto=format&fit=crop&w=500&q=80', 'food'],
                ['Cadiz Food Park', 'A vibrant evening food park and night market offering local street foods.', 10.9495, 123.3090, 'https://images.unsplash.com/photo-1552566626-52f8b828add9?auto=format&fit=crop&w=500&q=80', 'food'],
                ['Cadiz Public Market', 'Busy local market featuring fresh local produce and sea harvests.', 10.9480, 123.3100, 'https://images.unsplash.com/photo-1584346133934-a3afd2a33c4c?auto=format&fit=crop&w=500&q=80', 'shopping'],
                ['Cadiz City Hall', 'The administrative and governing seat of local government.', 10.9513, 123.3029, null, 'historical'],
                ['Hilantagaan Island View', 'Scenic elevated view of the nearby Hilantagaan Island.', 10.9550, 123.3100, null, 'beaches']
            ];

            foreach ($seeds as $s) {
                $stmt->execute($s);
            }
        }
    } catch (PDOException $e) {}

// ── Ensure tracker_locations has device_type and device_model columns ──
    try {
        $pdo->query("SELECT `device_type` FROM `tracker_locations` LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE `tracker_locations` ADD COLUMN `device_type` VARCHAR(50) DEFAULT NULL AFTER `device_info`");
            $pdo->exec("ALTER TABLE `tracker_locations` ADD COLUMN `device_model` VARCHAR(100) DEFAULT NULL AFTER `device_type`");
            $pdo->exec("ALTER TABLE `tracker_locations` ADD COLUMN `os_version` VARCHAR(50) DEFAULT NULL AFTER `device_model`");
            $pdo->exec("ALTER TABLE `tracker_locations` ADD COLUMN `browser_name` VARCHAR(50) DEFAULT NULL AFTER `os_version`");
        } catch (PDOException $alterErr) {}
    }

    // ── page_views table (tracks visits and content snapshots) ──
    $createPageViewsSQL = "CREATE TABLE IF NOT EXISTS `page_views` (
        `id`           INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`      INT NOT NULL DEFAULT 0,
        `page_name`    VARCHAR(255) NOT NULL DEFAULT '',
        `content_type` VARCHAR(50) DEFAULT NULL,
        `content_data` LONGTEXT DEFAULT NULL,
        `ip_address`   VARCHAR(45) DEFAULT NULL,
        `user_agent`   TEXT DEFAULT NULL,
        `visited_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (`user_id`),
        INDEX (`page_name`),
        INDEX (`visited_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    cadizEnsureTable($pdo, $dbname, 'page_views', $createPageViewsSQL);

    // Seed feedback if empty
    try {
        $checkFeedbackEmpty = $pdo->query("SELECT COUNT(*) FROM `feedback`")->fetchColumn();
        if ($checkFeedbackEmpty == 0) {
            $pdo->exec("INSERT INTO `feedback` (`place_name`, `user_name`, `rating`, `comment`, `created_at`) VALUES
                ('Cadiz City Plaza', 'John Doe', 5, 'Beautiful gardens and historical monument, loved it!', NOW() - INTERVAL 10 DAY),
                ('Cadiz City Plaza', 'Sarah K.', 4, 'Very clean and relaxing plaza right in the center.', NOW() - INTERVAL 8 DAY),
                ('Patag Beach', 'Mike R.', 4, 'Fine white sands and fantastic sunset view. Must visit!', NOW() - INTERVAL 5 DAY),
                ('Lakawon Island Resort', 'Jessica W.', 5, 'Absolutely spectacular sandbar and the floating bar is awesome!', NOW() - INTERVAL 3 DAY),
                ('Aluyan Beach Resort', 'Alex P.', 4, 'Quiet, peaceful cove with awesome sunset vistas.', NOW() - INTERVAL 2 DAY),
                ('City Mall Cadiz', 'Emily S.', 4, 'Convenient place to eat and shop inside Cadiz.', NOW() - INTERVAL 1 DAY)");
        }
    } catch (PDOException $e) {}

} catch (PDOException $e) {
    die("Database connection/initialization failed: " . $e->getMessage());
}
?>
