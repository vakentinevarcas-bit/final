<?php


session_start();
header('Content-Type: application/json');

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$username = isset($input['username']) ? trim(strtolower($input['username'])) : '';
$password = isset($input['password']) ? $input['password'] : '';
$full_name = isset($input['full_name']) ? trim($input['full_name']) : '';
$location = isset($input['location']) ? trim($input['location']) : 'Cadiz City';
$regLat = isset($input['lat']) ? (float)$input['lat'] : null;
$regLng = isset($input['lng']) ? (float)$input['lng'] : null;
$regAccuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
$deviceInfo = isset($input['device_info']) ? trim($input['device_info']) : null;

if (empty($username) || empty($password) || empty($full_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Full name, username, and password are required']);
    exit;
}

if (strlen($username) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters long']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
    exit;
}

try {
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
    $checkStmt->execute([$username]);
    if ($checkStmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Username is already taken']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $insertStmt = $pdo->prepare("INSERT INTO `users` (`username`, `password`, `full_name`, `location`, `created_at`) VALUES (?, ?, ?, ?, NOW())");
    $insertStmt->execute([$username, $hashedPassword, $full_name, $location]);

    $newUserId = $pdo->lastInsertId();

    $_SESSION['user_id'] = $newUserId;
    $_SESSION['username'] = $username;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['location'] = $location;

    if ($regLat !== null && $regLng !== null) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $trackerStmt = $pdo->prepare(
            "INSERT INTO tracker_locations (user_id, lat, lng, accuracy, device_info, ip_address) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $trackerStmt->execute([
            (int)$newUserId,
            $regLat,
            $regLng,
            $regAccuracy,
            $deviceInfo ? substr($deviceInfo, 0, 200) : null,
            $ipAddress
        ]);

        $locStr = number_format($regLat, 4) . ', ' . number_format($regLng, 4);
        $updateLocStmt = $pdo->prepare("UPDATE users SET location = ? WHERE id = ?");
        $updateLocStmt->execute([$locStr, (int)$newUserId]);
        $_SESSION['location'] = $locStr;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Registration and login successful',
        'user' => [
            'username' => $username,
            'full_name' => $full_name,
            'location' => $_SESSION['location']
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
