<?php
require_once __DIR__ . '/../Cadiz/session.php';
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

$username = isset($input['username']) ? trim(strtolower($input['username'])) : '';
$password = isset($input['password']) ? $input['password'] : '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM `admin_users` WHERE `username` = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_full_name'] = $admin['full_name'];
        $_SESSION['is_admin'] = true;

        echo json_encode([
            'success' => true,
            'message' => 'Admin authentication successful',
            'admin' => [
                'username' => $admin['username'],
                'full_name' => $admin['full_name']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid admin credentials']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
