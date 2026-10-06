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

// ── GET LAST KNOWN LOCATION (before device died / offline) ──
if ($action === 'get_last_known_location' || $action === 'get_device_location') {
    $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);
    if (!$targetUserId) err('User ID missing');

    $stmt = $pdo->prepare("SELECT * FROM tracker_locations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$targetUserId]);
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

// ── LIST USERS (for admin) ── returns each user's latest location ──
if ($action === 'list_users') {
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        err('Unauthorized: Admin access required', 403);
    }

    $stmt = $pdo->query(
        "SELECT t1.*, u.full_name, u.username
         FROM tracker_locations t1
         INNER JOIN (
             SELECT user_id, MAX(id) AS max_id
             FROM tracker_locations
             GROUP BY user_id
         ) t2 ON t1.id = t2.max_id
         LEFT JOIN users u ON u.id = t1.user_id
         ORDER BY t1.created_at DESC"
    );
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($locations as &$loc) {
        $loc['id'] = (int)$loc['id'];
        $loc['user_id'] = (int)$loc['user_id'];
        $loc['lat'] = (float)$loc['lat'];
        $loc['lng'] = (float)$loc['lng'];
        $loc['accuracy'] = $loc['accuracy'] ? (float)$loc['accuracy'] : null;
        $loc['battery_level'] = isset($loc['battery_level']) && $loc['battery_level'] !== null ? (float)$loc['battery_level'] : null;
        $loc['status'] = $loc['status'] ?? 'online';
    }

    jsonOut(['success' => true, 'users' => $locations]);
}

// ── GET HISTORY for a specific user ──
if ($action === 'get_history') {
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        err('Unauthorized: Admin access required', 403);
    }

    $targetUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 100;

    if (!$targetUserId) err('user_id is required');

    $stmt = $pdo->prepare(
        "SELECT tl.*, u.full_name, u.username
         FROM tracker_locations tl
         LEFT JOIN users u ON u.id = tl.user_id
         WHERE tl.user_id = ?
         ORDER BY tl.created_at DESC
         LIMIT ?"
    );
    $stmt->execute([$targetUserId, $limit]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($history as &$h) {
        $h['id'] = (int)$h['id'];
        $h['user_id'] = (int)$h['user_id'];
        $h['lat'] = (float)$h['lat'];
        $h['lng'] = (float)$h['lng'];
        $h['accuracy'] = $h['accuracy'] ? (float)$h['accuracy'] : null;
        $h['battery_level'] = isset($h['battery_level']) && $h['battery_level'] !== null ? (float)$h['battery_level'] : null;
        $h['status'] = $h['status'] ?? 'online';
    }

    jsonOut(['success' => true, 'history' => $history]);
}

// ── UPDATE location (for logged-in users) ──
if ($action === 'update') {
    if (!isset($_SESSION['user_id'])) {
        $anonUserId = 0;
    } else {
        $anonUserId = (int)$_SESSION['user_id'];
    }

    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = $_POST;

    $lat = isset($body['lat']) ? (float)$body['lat'] : null;
    $lng = isset($body['lng']) ? (float)$body['lng'] : null;
    $accuracy = isset($body['accuracy']) ? (float)$body['accuracy'] : null;
    $deviceInfo = isset($body['device_info']) ? trim($body['device_info']) : null;
    $batteryLevel = isset($body['battery_level']) && $body['battery_level'] !== null ? (float)$body['battery_level'] : null;
    $status = isset($body['status']) ? trim($body['status']) : 'online';

    if ($lat === null || $lng === null) err('lat and lng required');
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) err('Invalid coordinates');

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $pdo->prepare(
        "INSERT INTO tracker_locations (user_id, lat, lng, accuracy, device_info, ip_address, battery_level, status) VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([$anonUserId, $lat, $lng, $accuracy, $deviceInfo, $ipAddress, $batteryLevel, $status]);

    if ($anonUserId > 0) {
        $locStr = number_format($lat, 4) . ', ' . number_format($lng, 4);
        $updateUserStmt = $pdo->prepare("UPDATE users SET location = ? WHERE id = ?");
        $updateUserStmt->execute([$locStr, $anonUserId]);
        $_SESSION['location'] = $locStr;
    }

    jsonOut(['success' => true]);
}

err('Unknown action');
?>

