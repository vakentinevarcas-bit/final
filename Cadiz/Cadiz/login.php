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
$deviceInfo = isset($input['device_info']) ? trim($input['device_info']) : null;
$loginLat = isset($input['lat']) ? (float)$input['lat'] : null;
$loginLng = isset($input['lng']) ? (float)$input['lng'] : null;
$loginAccuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `username` = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['location'] = $user['location'];

        // Save initial GPS location to tracker_locations if coordinates provided
        if ($loginLat !== null && $loginLng !== null) {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $trackerStmt = $pdo->prepare(
                "INSERT INTO tracker_locations (user_id, lat, lng, accuracy, device_info, ip_address) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $trackerStmt->execute([
                (int)$user['id'],
                $loginLat,
                $loginLng,
                $loginAccuracy,
                $deviceInfo ? substr($deviceInfo, 0, 200) : null,
                $ipAddress
            ]);

            // Also update the user's location field with approximate location
            $locStr = number_format($loginLat, 4) . ', ' . number_format($loginLng, 4);
            $updateLocStmt = $pdo->prepare("UPDATE users SET location = ? WHERE id = ?");
            $updateLocStmt->execute([$locStr, (int)$user['id']]);
            $_SESSION['location'] = $locStr;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'username' => $user['username'],
                'full_name' => $user['full_name'],
                'location' => $_SESSION['location']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
