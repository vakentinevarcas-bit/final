<?php
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

$place_name = isset($input['place_name']) ? trim($input['place_name']) : '';
$rating = isset($input['rating']) ? (int)$input['rating'] : 0;
$comment = isset($input['comment']) ? trim($input['comment']) : '';
$user_name = isset($input['user_name']) ? trim($input['user_name']) : 'Alex Johnson';

if (empty($place_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tourist spot name is required']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Rating must be an integer between 1 and 5']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO `feedback` (`place_name`, `user_name`, `rating`, `comment`, `created_at`) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$place_name, $user_name, $rating, $comment]);
    
    echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
