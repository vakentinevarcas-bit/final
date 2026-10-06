<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../Cadiz/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$full_name = isset($input['full_name']) ? trim($input['full_name']) : '';
$username = isset($input['username']) ? trim(strtolower($input['username'])) : '';
$password = isset($input['password']) ? $input['password'] : '';

if (empty($full_name) || empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `admin_users` WHERE `username` = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO `admin_users` (`username`, `password`, `full_name`) VALUES (?, ?, ?)");
    $stmt->execute([$username, $hashedPassword, $full_name]);

    echo json_encode([
        'success' => true,
        'message' => 'Admin registered successfully. You can now login.'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

