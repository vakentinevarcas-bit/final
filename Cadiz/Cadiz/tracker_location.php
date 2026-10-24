<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

function jsonOut($data) {
    echo json_encode($data);
    exit;
}

function err($msg, $code = 400) {
    http_response_code($code);
    jsonOut(['success' => false, 'message' => $msg]);
}

// ── Ensure tracker_locations table exists with full schema ──
function ensureTrackerTable($pdo) {
    $needsCreate = false;

    try {
        $pdo->query("SELECT 1 FROM `tracker_locations` LIMIT 1");
        $needsCreate = false;
    } catch (PDOException $e) {
        $needsCreate = true;
        // MySQL 1932 = InnoDB table missing in engine; 1146 = table doesn't exist
        // Force discard stale data-dictionary entry before recreating
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 0"); } catch (PDOException $ex) {}
        try { $pdo->exec("DROP TABLE IF EXISTS `tracker_locations`"); } catch (PDOException $ex) {}
        try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (PDOException $ex) {}
    }

    if ($needsCreate) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `tracker_locations` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `user_id`      INT NOT NULL DEFAULT 0,
            `lat`          DOUBLE NOT NULL,
            `lng`          DOUBLE NOT NULL,
            `accuracy`     DOUBLE NULL,
            `device_info`  VARCHAR(255) DEFAULT NULL,
            `device_type`  VARCHAR(50)  DEFAULT NULL,
            `device_model` VARCHAR(100) DEFAULT NULL,
            `os_version`   VARCHAR(100) DEFAULT NULL,
            `browser_name` VARCHAR(100) DEFAULT NULL,
            `ip_address`   VARCHAR(45)  DEFAULT NULL,
            `battery_level` FLOAT       DEFAULT NULL,
            `status`       VARCHAR(50)  DEFAULT 'online',
            `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (`user_id`),
            INDEX (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // ── Safely add any missing columns to existing tables ──
    $alterCols = [
        'device_info'  => "ALTER TABLE `tracker_locations` ADD COLUMN `device_info`  VARCHAR(255) DEFAULT NULL",
        'device_type'  => "ALTER TABLE `tracker_locations` ADD COLUMN `device_type`  VARCHAR(50)  DEFAULT NULL",
        'device_model' => "ALTER TABLE `tracker_locations` ADD COLUMN `device_model` VARCHAR(100) DEFAULT NULL",
        'os_version'   => "ALTER TABLE `tracker_locations` ADD COLUMN `os_version`   VARCHAR(100) DEFAULT NULL",
        'browser_name' => "ALTER TABLE `tracker_locations` ADD COLUMN `browser_name` VARCHAR(100) DEFAULT NULL",
        'ip_address'   => "ALTER TABLE `tracker_locations` ADD COLUMN `ip_address`   VARCHAR(45)  DEFAULT NULL",
        'battery_level'=> "ALTER TABLE `tracker_locations` ADD COLUMN `battery_level` FLOAT       DEFAULT NULL",
        'status'       => "ALTER TABLE `tracker_locations` ADD COLUMN `status`        VARCHAR(50)  DEFAULT 'online'",
    ];

    foreach ($alterCols as $col => $sql) {
        try {
            $pdo->query("SELECT `$col` FROM `tracker_locations` LIMIT 1");
        } catch (PDOException $e) {
            try { $pdo->exec($sql); } catch (PDOException $ex) {}
        }
    }
}

try {
    ensureTrackerTable($pdo);
} catch (PDOException $e) {
    // Last-resort: wipe everything and recreate from scratch
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP TABLE IF EXISTS `tracker_locations`");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        ensureTrackerTable($pdo);
    } catch (PDOException $ex) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB table error: ' . $ex->getMessage()]);
        exit;
    }
}

if ($action === 'sharing' || $action === 'update') {
    err('Location sharing has been removed.', 403);
}

// ── GET LAST KNOWN LOCATION (before device died / offline) ──
if ($action === 'get_last_known_location' || $action === 'get_device_location') {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        err('Sign in to view your saved location.', 401);
    }

    $stmt = $pdo->prepare("SELECT * FROM tracker_locations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $loc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($loc) {
        jsonOut([
            'success' => true,
            'location' => [
                'id'            => (int)$loc['id'],
                'user_id'       => (int)$loc['user_id'],
                'lat'           => (float)$loc['lat'],
                'lng'           => (float)$loc['lng'],
                'accuracy'      => $loc['accuracy'] ? (float)$loc['accuracy'] : null,
                'battery_level' => isset($loc['battery_level']) && $loc['battery_level'] !== null ? (float)$loc['battery_level'] : null,
                'status'        => $loc['status'] ?? 'online',
                'device_info'   => $loc['device_info'] ?? null,
                'created_at'    => $loc['created_at']
            ]
        ]);
    } else {
        jsonOut(['success' => false, 'message' => 'No recorded location found']);
    }
}

// Location records can only be accessed by the owning account.
if ($action === 'list_users') {
    err('Location data is private to each signed-in account.', 403);
}

if ($action === 'get_history') {
    err('Location history is private to each signed-in account.', 403);
}

err('Unknown action');
?>
