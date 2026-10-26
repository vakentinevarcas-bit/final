<?php
require_once __DIR__ . '/../Cadiz/session.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../Cadiz/db.php';

$stats = $pdo->query('SELECT COUNT(*) AS total, AVG(rating) AS average FROM feedback')->fetch();
$feedback = $pdo->query(
    'SELECT id, user_name, place_name, comment, rating, created_at FROM feedback ORDER BY created_at DESC, id DESC'
)->fetchAll();

foreach ($feedback as &$entry) {
    $entry['id'] = (int)$entry['id'];
    $entry['rating'] = (int)$entry['rating'];
    $entry['date_label'] = date('M j, Y', strtotime($entry['created_at']));
}
unset($entry);

echo json_encode([
    'success' => true,
    'total' => (int)$stats['total'],
    'average' => $stats['average'] !== null ? (float)$stats['average'] : 0,
    'feedback' => $feedback,
], JSON_INVALID_UTF8_SUBSTITUTE);
