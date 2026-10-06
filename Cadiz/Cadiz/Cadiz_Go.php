<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: Index.php');
    exit;
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$fullName = htmlspecialchars($_SESSION['full_name'] ?? 'Alex Johnson', ENT_QUOTES, 'UTF-8');
$username = htmlspecialchars($_SESSION['username'] ?? 'alex', ENT_QUOTES, 'UTF-8');
$firstName = htmlspecialchars(explode(' ', trim($_SESSION['full_name'] ?? 'Traveler'))[0], ENT_QUOTES, 'UTF-8');
$initial  = htmlspecialchars(mb_strtoupper(mb_substr(trim($_SESSION['full_name'] ?? 'A'), 0, 1)), ENT_QUOTES, 'UTF-8');
$location = htmlspecialchars($_SESSION['location'] ?? 'Cadiz City', ENT_QUOTES, 'UTF-8');

$lastDeviceLat  = null;
$lastDeviceLng  = null;
$lastDeviceTime = null;
$lastDeviceInfo = null;

// ── Device detection from User-Agent ──
function detectDeviceType($ua) {
    $ua = $ua ?? '';
    if (stripos($ua, 'android') !== false) return 'Android';
    if (stripos($ua, 'iphone') !== false || stripos($ua, 'ipad') !== false) return 'iOS';
    if (stripos($ua, 'windows phone') !== false) return 'Windows Phone';
    if (stripos($ua, 'windows') !== false) return 'Windows';
    if (stripos($ua, 'macintosh') !== false || stripos($ua, 'mac os') !== false) return 'macOS';
    if (stripos($ua, 'linux') !== false) return 'Linux';
    if (stripos($ua, 'cros') !== false) return 'Chrome OS';
    return 'Unknown';
}

function detectDeviceModel($ua) {
    $ua = $ua ?? '';
    // Samsung
    if (preg_match('/SM-[A-Z0-9]+/', $ua, $m)) return $m[0];
    if (preg_match('/GT-[A-Z0-9]+/', $ua, $m)) return $m[0];
    // iPhone
    if (preg_match('/iPhone(\d+,\d+)?/', $ua, $m)) return $m[0];
    // iPad
    if (preg_match('/iPad(\d+,\d+)?/', $ua, $m)) return $m[0];
    // Pixel
    if (preg_match('/Pixel \d/', $ua, $m)) return $m[0];
    // OnePlus
    if (preg_match('/OnePlus\d*/', $ua, $m)) return $m[0];
    // Xiaomi
    if (preg_match('/Mi\d+/', $ua, $m)) return $m[0];
    // Redmi
    if (preg_match('/Redmi \w+/', $ua, $m)) return $m[0];
    // Generic model pattern
    if (preg_match('/\b([A-Z][a-z]+[_ ]?[A-Za-z0-9\-]+)\b.*?Mobile/', $ua, $m)) return $m[1];
    return 'Unknown Model';
}

function detectOSVersion($ua) {
    $ua = $ua ?? '';
    if (preg_match('/Android (\d+[\.\d]*)/', $ua, $m)) return 'Android ' . $m[1];
    if (preg_match('/iPhone OS (\d+[_\d]*)/', $ua, $m)) return 'iOS ' . str_replace('_', '.', $m[1]);
    if (preg_match('/Windows NT (\d+[\.\d]*)/', $ua, $m)) {
        $ver = ['6.0'=>'Vista','6.1'=>'7','6.2'=>'8','6.3'=>'8.1','10.0'=>'10/11'];
        return 'Windows ' . ($ver[$m[1]] ?? $m[1]);
    }
    if (preg_match('/Mac OS X (\d+[_\d]*)/', $ua, $m)) return 'macOS ' . str_replace('_', '.', $m[1]);
    if (preg_match('/CrOS (\S+)/', $ua, $m)) return 'Chrome OS ' . $m[1];
    if (preg_match('/Linux (\S+)/', $ua, $m)) return 'Linux ' . $m[1];
    return 'Unknown';
}

function detectBrowserName($ua) {
    $ua = $ua ?? '';
    if (stripos($ua, 'edg/') !== false || stripos($ua, 'edge/') !== false) return 'Edge';
    if (stripos($ua, 'opr/') !== false || stripos($ua, 'opera/') !== false) return 'Opera';
    if (stripos($ua, 'chrome/') !== false && stripos($ua, 'samsung') !== false) return 'Samsung Browser';
    if (stripos($ua, 'chrome/') !== false) return 'Chrome';
    if (stripos($ua, 'firefox/') !== false) return 'Firefox';
    if (stripos($ua, 'safari/') !== false && stripos($ua, 'chrome') === false) return 'Safari';
    if (stripos($ua, 'trident/') !== false) return 'Internet Explorer';
    return 'Unknown';
}

$deviceUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
$detectedDeviceType  = detectDeviceType($deviceUA);
$detectedDeviceModel = detectDeviceModel($deviceUA);
$detectedOSVersion   = detectOSVersion($deviceUA);
$detectedBrowserName = detectBrowserName($deviceUA);

$deviceName      = $fullName . "'s Device";
$deviceOS        = $detectedOSVersion !== 'Unknown' ? $detectedOSVersion : $detectedDeviceType;

if ($detectedDeviceType === 'Android') {
    $deviceName      = ($detectedDeviceModel !== 'Unknown Model') ? $detectedDeviceModel : ($fullName . "'s Android");
} elseif ($detectedDeviceType === 'iOS') {
    $deviceName      = ($detectedDeviceModel !== 'Unknown Model') ? $detectedDeviceModel : ($fullName . "'s iPhone");
} elseif ($detectedDeviceType === 'Windows') {
    $deviceName      = $fullName . "'s Windows PC";
} elseif ($detectedDeviceType === 'macOS') {
    $deviceName      = $fullName . "'s Mac";
} elseif ($detectedDeviceType === 'Linux') {
    $deviceName      = $fullName . "'s Linux PC";
} elseif ($detectedDeviceType === 'Chrome OS') {
    $deviceName      = $fullName . "'s Chromebook";
}

// ── Ensure tracker_locations table exists with full schema ──
// Handles MySQL 1932 (InnoDB tablespace missing) + 1146 (table not found)
function ensureTrackerTableMain($pdo) {
    $needsCreate = false;
    try {
        $pdo->query("SELECT 1 FROM `tracker_locations` LIMIT 1");
    } catch (PDOException $e) {
        $needsCreate = true;
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

    // Add any missing columns to existing tables
    $cols = [
        'device_info'  => "ALTER TABLE `tracker_locations` ADD COLUMN `device_info`  VARCHAR(255) DEFAULT NULL",
        'device_type'  => "ALTER TABLE `tracker_locations` ADD COLUMN `device_type`  VARCHAR(50)  DEFAULT NULL",
        'device_model' => "ALTER TABLE `tracker_locations` ADD COLUMN `device_model` VARCHAR(100) DEFAULT NULL",
        'os_version'   => "ALTER TABLE `tracker_locations` ADD COLUMN `os_version`   VARCHAR(100) DEFAULT NULL",
        'browser_name' => "ALTER TABLE `tracker_locations` ADD COLUMN `browser_name` VARCHAR(100) DEFAULT NULL",
        'ip_address'   => "ALTER TABLE `tracker_locations` ADD COLUMN `ip_address`   VARCHAR(45)  DEFAULT NULL",
        'battery_level'=> "ALTER TABLE `tracker_locations` ADD COLUMN `battery_level` FLOAT       DEFAULT NULL",
        'status'       => "ALTER TABLE `tracker_locations` ADD COLUMN `status`        VARCHAR(50)  DEFAULT 'online'",
    ];
    foreach ($cols as $col => $sql) {
        try {
            $pdo->query("SELECT `$col` FROM `tracker_locations` LIMIT 1");
        } catch (PDOException $e) {
            try { $pdo->exec($sql); } catch (PDOException $ex) {}
        }
    }
}

try {
    ensureTrackerTableMain($pdo);
} catch (PDOException $e) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP TABLE IF EXISTS `tracker_locations`");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        ensureTrackerTableMain($pdo);
    } catch (PDOException $ex) { /* continue; page queries will return empty */ }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_device_location') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("SELECT lat, lng, accuracy, device_info, created_at FROM tracker_locations WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$userId]);
        $loc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($loc) {
            echo json_encode([
                'success' => true,
                'lat'      => (float)$loc['lat'],
                'lng'      => (float)$loc['lng'],
                'accuracy' => $loc['accuracy'] ? (float)$loc['accuracy'] : null,
                'device'   => $loc['device_info'],
                'time'     => $loc['created_at']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No location recorded yet']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

$lastDeviceLat     = null;
$lastDeviceLng     = null;
$lastDeviceTime    = null;
$lastDeviceInfo    = null;
$lastBatteryLevel  = null;
$lastDeviceStatus  = null;

// Always try to fetch the last known location for page display
try {
    $stmt = $pdo->prepare("SELECT lat, lng, accuracy, device_info, battery_level, status, created_at FROM tracker_locations WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $lastLoc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($lastLoc) {
        $lastDeviceLat     = (float)$lastLoc['lat'];
        $lastDeviceLng     = (float)$lastLoc['lng'];
        $lastDeviceTime    = $lastLoc['created_at'];
        $lastDeviceInfo    = $lastLoc['device_info'];
        $lastBatteryLevel  = isset($lastLoc['battery_level']) && $lastLoc['battery_level'] !== null ? (float)$lastLoc['battery_level'] : null;
        $lastDeviceStatus  = $lastLoc['status'] ?? 'online';
    }
} catch (PDOException $e) { /* silently ignore */ }

// ─────────────────────────────────────────────────────────────
// POST HANDLERS
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = isset($input['action']) ? trim($input['action']) : '';

    // ── Track device location ──
    if ($action === 'track_device_location') {
        $lat          = isset($input['lat']) ? (float)$input['lat'] : null;
        $lng          = isset($input['lng']) ? (float)$input['lng'] : null;
        $accuracy     = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
        $deviceInfo   = isset($input['device_info']) ? trim($input['device_info']) : ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        $batteryLevel = isset($input['battery_level']) && $input['battery_level'] !== null ? (float)$input['battery_level'] : null;
        $status       = isset($input['status']) ? trim($input['status']) : 'online';
        $ipAddress    = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($lat === null || $lng === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'lat and lng are required']);
            exit;
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO `tracker_locations` (`user_id`, `lat`, `lng`, `accuracy`, `device_info`, `device_type`, `device_model`, `os_version`, `browser_name`, `ip_address`, `battery_level`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $lat, $lng, $accuracy, $deviceInfo, $detectedDeviceType, $detectedDeviceModel, $detectedOSVersion, $detectedBrowserName, $ipAddress, $batteryLevel, $status]);

            // Also update the user's location field
            $locStr = number_format($lat, 4) . ', ' . number_format($lng, 4);
            $updateStmt = $pdo->prepare("UPDATE users SET location = ? WHERE id = ?");
            $updateStmt->execute([$locStr, $userId]);
            $_SESSION['location'] = $locStr;

            echo json_encode(['success' => true, 'message' => 'Device location saved']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Insert a rating / feedback ──
    if ($action === 'submit_rating' || $action === 'submit_feedback') {
        $place_name = isset($input['place_name']) ? trim($input['place_name']) : ($action === 'submit_feedback' ? 'General Feedback' : '');
        $rating     = isset($input['rating']) ? (int)$input['rating'] : 0;
        $comment    = isset($input['comment']) ? trim($input['comment']) : (isset($input['message']) ? trim($input['message']) : '');
        $user_name  = isset($input['user_name']) ? trim($input['user_name']) : $fullName;

        if (empty($place_name) && $action === 'submit_rating') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Place name is required']);
            exit;
        }
        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Rating must be 1–5']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO `feedback` (`place_name`, `user_name`, `rating`, `comment`, `created_at`) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$place_name, $user_name, $rating, $comment]);
            echo json_encode(['success' => true, 'message' => ucfirst(str_replace('submit_', '', $action)) . ' submitted successfully!']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Save page content / HTML snapshot into DB ──
    if ($action === 'save_page_content') {
        $content_type = isset($input['content_type']) ? trim($input['content_type']) : 'html_snapshot';
        $content_data = isset($input['content_data']) ? $input['content_data'] : '';
        $page_url     = isset($input['page_url']) ? trim($input['page_url']) : 'Cadiz/Cadiz_Go.php';

        try {
            $stmt = $pdo->prepare("INSERT INTO `page_views` (`user_id`, `page_name`, `content_type`, `content_data`, `ip_address`, `user_agent`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $page_url,
                $content_type,
                is_string($content_data) ? $content_data : json_encode($content_data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// ─────────────────────────────────────────────────────────────
// TRACK PAGE VIEW — Insert a visit record
// ─────────────────────────────────────────────────────────────
try {
    $pdo->query("SELECT `content_type` FROM `page_views` LIMIT 1");
} catch (PDOException $e) {
    try {
        $pdo->exec("ALTER TABLE `page_views` ADD COLUMN `content_type` VARCHAR(50) DEFAULT NULL AFTER `page_name`");
        $pdo->exec("ALTER TABLE `page_views` ADD COLUMN `content_data` LONGTEXT DEFAULT NULL AFTER `content_type`");
    } catch (PDOException $alterErr) {}
}

$ipAddress  = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? null;
$stmt = $pdo->prepare("INSERT INTO `page_views` (`user_id`, `page_name`, `ip_address`, `user_agent`) VALUES (?, 'Cadiz_Go.php', ?, ?)");
$stmt->execute([$userId, $ipAddress, $userAgent]);
$pageViewId = (int)$pdo->lastInsertId();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>CADIZGO | Cadiz City Guide</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
    <link rel="stylesheet" href="../css/Cadiz_Go.css?v=<?php echo filemtime(__DIR__ . '/../css/Cadiz_Go.css'); ?>">  
    <script>

    window.isLoggedIn     = true;
    window.userId         = <?php echo $userId; ?>;
    window.userFullName   = <?php echo json_encode($fullName); ?>;
    window.userUsername   = <?php echo json_encode($username); ?>;
    window.userLocation   = <?php echo json_encode($location); ?>;
    window.__cadizDeviceDetails = {
        name: <?php echo json_encode($deviceName); ?>,
        os: <?php echo json_encode($deviceOS); ?>,
        type: <?php echo json_encode($detectedDeviceType); ?>,
        model: <?php echo json_encode($detectedDeviceModel); ?>,
        browser: <?php echo json_encode($detectedBrowserName); ?>
    };
    window.pageViewId        = <?php echo $pageViewId; ?>;
    window.lastDeviceLat     = <?php echo $lastDeviceLat !== null ? $lastDeviceLat : 'null'; ?>;
    window.lastDeviceLng     = <?php echo $lastDeviceLng !== null ? $lastDeviceLng : 'null'; ?>;
    window.lastDeviceTime    = <?php echo $lastDeviceTime ? json_encode($lastDeviceTime) : 'null'; ?>;
    window.lastDeviceInfo    = <?php echo $lastDeviceInfo ? json_encode($lastDeviceInfo) : 'null'; ?>;
    window.lastBatteryLevel = <?php echo $lastBatteryLevel !== null ? $lastBatteryLevel : 'null'; ?>;
    window.lastDeviceStatus  = <?php echo $lastDeviceStatus ? json_encode($lastDeviceStatus) : 'null'; ?>;
    </script>
</head>
<body>
<div class="app-container">
    <!-- HOME SCREEN -->
    <div id="home-screen" class="screen active">
        <header><div class="header-content d-flex justify-content-between align-items-center"><div class="logo d-flex align-items-center gap-2"><button type="button" class="menu-toggle" aria-label="Open menu" aria-controls="navDrawer" aria-expanded="false"><span></span><span></span><span></span></button><div class="logo-mark">CG</div><div><div class="logo-text">CADIZGO</div><div class="logo-subtitle">Cadiz City guide</div></div></div><button class="header-action-btn" id="notification-btn">Alerts</button></div></header>
        <div class="container p-3">
            <div class="greeting"><h1>Welcome, <?php echo $firstName; ?></h1><p>Search for a place or open the map to plan your route.</p></div>
            <div class="search-container p-3 mb-3"><div class="search-box"><input type="text" id="homeSearch" placeholder="Search places" aria-label="Search places"><div class="voice-search" id="voiceMock" role="button" tabindex="0">Voice</div></div></div>
            <div class="ai-assistant p-3 mb-3"><div class="ai-header d-flex align-items-center gap-3 mb-2"><div class="ai-avatar">AI</div><div><h3 class="h6 mb-0 text-primary">CADIZGO Assistant</h3><p class="small text-muted">Routes, ratings and your device location</p></div></div><div class="ai-message"><p class="small mb-0">Ask the assistant how to get a route, rate a place or find your device, or send a message to the CADIZGO team.</p></div><div class="ai-actions mt-2"><button class="ai-action-btn" id="planDayBtn">Ask the assistant</button><button class="ai-action-btn" id="findFoodBtn">Open map</button></div></div>
            <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="text-primary m-0">Explore by Category</h5><a href="#" id="seeAllMap" class="small text-primary">Open map</a></div>
            <div class="row no-gutters categories g-2 mb-3">
                <div class="col-6 pr-1 mb-2"><div class="category-card" role="button" tabindex="0" data-category="historical"><div class="category-icon"></div><h6>Historical</h6><small>Landmarks and heritage</small></div></div>
                <div class="col-6 pl-1 mb-2"><div class="category-card" role="button" tabindex="0" data-category="food"><div class="category-icon"></div><h6>Food & Drink</h6><small>Restaurants and cafes</small></div></div>
                <div class="col-6 pr-1 mb-2"><div class="category-card" role="button" tabindex="0" data-category="beaches"><div class="category-icon"></div><h6>Beaches</h6><small>Coast and islands</small></div></div>
                <div class="col-6 pl-1 mb-2"><div class="category-card" role="button" tabindex="0" data-category="shopping"><div class="category-icon"></div><h6>Shopping</h6><small>Markets and malls</small></div></div>
                 <div class="col-6 pl-1 mb-2"><div class="category-card" role="button" tabindex="0" data-category="hotel"><div class="category-icon"></div><h6>Hotel</h6><small>Hotels</small></div></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2"><h5 class="text-primary m-0">Popular Attractions</h5></div>
            <div id="popularAttractionsContainer" class="attractions"><div class="empty-state"><strong>No attractions yet</strong>Featured places will appear here once they are added.</div></div>
        </div>
    </div>

    <!-- MAP SCREEN -->
    <div id="map-screen" class="screen hidden"><header><div class="header-content d-flex justify-content-between"><div class="logo d-flex align-items-center gap-2"><button type="button" class="menu-toggle" aria-label="Open menu" aria-controls="navDrawer" aria-expanded="false"><span></span><span></span><span></span></button><div class="logo-mark">CG</div><div><div class="logo-text">Map</div><div class="logo-subtitle">Live position and routes</div></div></div></div></header><div class="map-container"><div id="map"></div><div class="map-search-box"><input type="text" id="map-search-input" placeholder="Search places" aria-label="Search places"></div>
        <div id="gpsStatusBar"><span id="gpsStatusText">Acquiring location…</span></div>
        <div id="coordDisplay"><span id="coordText">Waiting for GPS…</span></div>
        <div class="map-controls">
            <button class="map-control-btn" id="zoom-in" title="Zoom in" aria-label="Zoom in">+</button>
            <button class="map-control-btn" id="zoom-out" title="Zoom out" aria-label="Zoom out">&minus;</button>
            <button class="map-control-btn" id="locate-me" title="Centre on my live position">Locate</button>
            <button class="map-control-btn locate-dead-btn" id="locate-dead-device" title="Show last known device location">Last seen</button>
            <button class="map-control-btn" id="map-layers" title="Map help">Help</button>
            <button class="map-control-btn" id="clear-route-btn" style="background:#e74c3c;color:white;display:none;" title="Clear route">Clear route</button>
        </div></div></div>

    <!-- PROFILE SCREEN -->
    <div id="profile-screen" class="screen hidden"><div class="profile-header text-center py-4" ><button type="button" class="menu-toggle floating" aria-label="Open menu" aria-controls="navDrawer" aria-expanded="false"><span></span><span></span><span></span></button><div class="profile-avatar mx-auto rounded-circle" style="width:90px;height:90px;background:rgba(255,255,255,0.2);font-size:2rem;display:flex;align-items:center;justify-content:center"><?php echo $initial; ?></div><h5 class="mt-2" id="profileName"><?php echo $fullName; ?></h5><p class="small"><span id="profileLocation"><?php echo $location; ?></span></p></div><div class="profile-stats d-flex justify-content-around bg-white p-3 mx-3 rounded shadow-sm" style="margin-top:-1rem"><div class="text-center"><div class="stat-value fw-bold text-primary">0</div><small>Places</small></div><div class="text-center"><div class="stat-value fw-bold text-primary">0</div><small>Photos</small></div><div class="text-center"><div class="stat-value fw-bold text-primary">0</div><small>Reviews</small></div></div><div class="container p-3"><div class="profile-section p-3 mb-3"><h6 class="text-primary">Account</h6><div class="profile-menu-item d-flex py-2 border-bottom"><div class="menu-icon me-3"></div><div><p class="mb-0 fw-semibold">Edit Profile</p><small class="text-muted">Update info</small></div></div></div><div class="profile-section p-3"><a href="logout.php" class="btn btn-danger w-100 rounded-pill">Log out</a></div></div></div>

    <!-- CATEGORY SCREEN -->
    <div id="category-screen" class="screen hidden"><header><div class="header-content d-flex align-items-center"><button type="button" class="menu-toggle mr-2" aria-label="Open menu" aria-controls="navDrawer" aria-expanded="false"><span></span><span></span><span></span></button><button class="header-action-btn me-2 mr-2" id="back-from-category">Back</button><div><div class="logo-text" id="category-title">Category</div><div class="logo-subtitle" id="category-subtitle">Explore</div></div></div></header><div class="category-detail-header py-4 text-center text-white" ><h2 id="detail-title">Beaches</h2><p id="detail-description">Discover coastal gems</p></div><div id="category-places" class="container p-3" aria-live="polite"></div></div>

    <div class="nav-scrim" id="navScrim"></div>
    <nav class="nav-drawer" id="navDrawer" aria-label="Main menu" aria-hidden="true">
        <div class="drawer-brand"><div class="logo-mark">CG</div><div><div class="drawer-user"><?php echo $fullName; ?></div><div class="drawer-sub">Cadiz City guide</div></div></div>
        <div class="drawer-links">
            <button type="button" class="drawer-item active" data-screen="home">Home</button>
            <button type="button" class="drawer-item" data-screen="map">Map</button>
            <button type="button" class="drawer-item" data-screen="profile">Profile</button>
        </div>
        <a href="logout.php" class="drawer-logout">Log out</a>
    </nav>

    <div id="toastMsg" class="custom-toast">Ready</div>

    <!-- MODALS -->
    <div class="image-modal" id="imageModal"><div class="image-card"><div class="alert-image" id="modalImage"></div><h5 id="modalTitle"></h5><div class="stars mb-2" id="modalStars"></div><p id="modalDesc" class="small"></p><p id="modalDistance" class="small fw-bold text-primary mb-2"></p><div class="d-flex gap-2 mt-3"><button id="modalRouteBtn" class="btn-custom flex-grow-1">Show route</button><button id="modalRateBtn" class="btn-custom flex-grow-1" style="background:#eef4fe; color:#1a5fb4">Rate</button></div><button id="closeImageModal" class="btn btn-light btn-sm rounded-pill position-absolute" style="top:10px;right:10px">Close</button></div></div>
    <div class="rating-modal" id="ratingModal"><div class="rating-card"><h5 class="text-primary">Rate your experience</h5><div class="fw-bold mb-2" id="ratingPlaceLabel">Place</div><div class="rating-scale mb-1" id="ratingStarsContainer" role="radiogroup" aria-label="Rating"><button type="button" class="rating-option" data-val="1" role="radio" aria-label="1 star" aria-checked="false">★</button><button type="button" class="rating-option" data-val="2" role="radio" aria-label="2 stars" aria-checked="false">★</button><button type="button" class="rating-option" data-val="3" role="radio" aria-label="3 stars" aria-checked="false">★</button><button type="button" class="rating-option" data-val="4" role="radio" aria-label="4 stars" aria-checked="false">★</button><button type="button" class="rating-option" data-val="5" role="radio" aria-label="5 stars" aria-checked="false">★</button></div><div class="rating-hint mb-3">Tap a star to rate</div><textarea id="ratingComment" rows="2" class="form-control mb-3" placeholder="Optional review..."></textarea><div class="d-flex gap-2"><button id="ratingCancel" class="btn btn-light rounded-pill flex-grow-1">Cancel</button><button id="ratingSubmit" class="btn btn-primary rounded-pill flex-grow-1">Submit rating</button></div></div></div>

    <!-- DEAD / LOST DEVICE MODAL -->
    <div class="image-modal" id="deadDeviceModal">
        <div class="image-card text-center p-4" style="max-width:340px; border-radius:24px;">
            
            <h5 class="fw-bold text-danger mb-1" id="deadDeviceModalTitle">Last known device location</h5>
            <p class="small text-muted mb-3" id="deadDeviceSubtitle">Last GPS position recorded before the device went offline</p>

            <div class="card border-0 bg-light p-3 mb-3 text-start rounded-3" style="box-shadow: inset 0 0 5px rgba(0,0,0,0.05);">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-muted">Device</span>
                    <span class="fw-bold small" id="deadDeviceName"><?php echo htmlspecialchars($deviceName); ?></span>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-muted">Battery level</span>
                    <span class="badge bg-danger text-white py-1 px-2" id="deadDeviceBattery">Unknown</span>
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="small text-muted">Last seen</span>
                    <span class="small fw-semibold" id="deadDeviceTime">Unknown</span>
                </div>
                <div class="border-top pt-2 mt-1">
                    <span class="small text-muted d-block mb-1">Last address</span>
                    <div class="small fw-bold text-dark text-truncate" id="deadDeviceAddress">Cadiz City</div>
                    <div class="small text-muted" id="deadDeviceCoords">10.9504, 123.3081</div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button id="focusDeadDeviceBtn" class="btn btn-primary btn-sm flex-grow-1 rounded-pill">View on map</button>
                <button id="closeDeadDeviceModal" class="btn btn-light btn-sm flex-grow-1 rounded-pill">Close</button>
            </div>
        </div>
    </div>

    <!-- CHATBOT -->
    <div class="floating-chatbot" id="draggableChatbot"><div class="chatbot-window" id="chatbotWindow" role="dialog" aria-label="CADIZGO Assistant"><div class="chatbot-header"><div class="chatbot-title"><span class="chatbot-name">CADIZGO Assistant</span><span class="chatbot-status">Instant help, plus replies from our team</span></div><button id="chatbotClose" class="chatbot-close" type="button">Close</button></div><div class="chatbot-messages" id="chatbotMessages" aria-live="polite"><div class="chat-message bot"><div class="message-bubble">Welcome to the CADIZGO Assistant. Choose a topic below for instant help, or type a message and the CADIZGO team will reply here.</div></div></div><div class="chatbot-suggestions" id="chatbotSuggestions"><button type="button" class="chat-chip" data-faq="route">Get a route</button><button type="button" class="chat-chip" data-faq="rate">Rate a place</button><button type="button" class="chat-chip" data-faq="locate">My location</button><button type="button" class="chat-chip" data-faq="device">Find my device</button><button type="button" class="chat-chip" data-faq="team">Message the team</button></div><div class="chatbot-input-area"><input class="chatbot-input" id="chatbotInput" maxlength="500" placeholder="Type a message" aria-label="Message" autocomplete="off"><button class="chatbot-send" id="chatbotSend">Send</button></div></div><button class="chatbot-icon" id="chatbotIcon" aria-label="Open chat">Chat<span class="chatbot-unread-badge" id="chatbotUnreadBadge"></span></button></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>    
<script src="../js/Cadiz_go.js?v=<?php echo filemtime(__DIR__ . '/../js/Cadiz_go.js'); ?>"></script>
</body>
</html>