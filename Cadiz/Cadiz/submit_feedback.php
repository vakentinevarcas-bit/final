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

$message  = isset($input['message'])   ? trim($input['message'])   : '';
$rating   = isset($input['rating'])    ? (int)$input['rating']     : 0;
$category = isset($input['category'])  ? trim($input['category'])  : 'General Feedback';

$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : (isset($input['user_name']) ? trim($input['user_name']) : 'Anonymous');

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Feedback message is required']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a star rating (1–5)']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO `feedback` (`place_name`, `user_name`, `rating`, `comment`, `created_at`) VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$category, $user_name, $rating, $message]);

    echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully!']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
