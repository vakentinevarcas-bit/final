<?php
require_once __DIR__ . '/session.php';
header('Content-Type: application/json');
require_once 'db.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

function jsonOut($data) { echo json_encode($data); exit; }
function err($msg, $code = 400) { http_response_code($code); jsonOut(['success' => false, 'message' => $msg]); }
function validateLandmarkPhoto($photo) {
    if ($photo === null) return null;
    if (!is_string($photo)) err('Photo data must be a string');
    if (strlen($photo) > 512 * 1024) {
        err('Photo is too large. Please choose a smaller image.', 413);
    }
    return $photo;
}

// GET / READ operations are public
if ($action === 'list') {
    try {
        $stmt = $pdo->query(
            "SELECT 
                l.id, 
                l.name, 
                l.description, 
                l.lat, 
                l.lng, 
                l.photo, 
                l.category,
                COALESCE(ROUND(AVG(f.rating), 1), 4.5) AS rating,
                COUNT(f.id) AS reviews
             FROM landmarks l
             LEFT JOIN feedback f ON f.place_name = l.name
             GROUP BY l.id
             ORDER BY l.id ASC"
        );
        $landmarks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert types correctly
        foreach ($landmarks as &$lm) {
            $lm['id'] = (int)$lm['id'];
            $lm['lat'] = (float)$lm['lat'];
            $lm['lng'] = (float)$lm['lng'];
            $lm['rating'] = (float)$lm['rating'];
            $lm['reviews'] = (int)$lm['reviews'];
        }
        jsonOut(['success' => true, 'landmarks' => $landmarks]);
    } catch (PDOException $e) {
        err('Database error: ' . $e->getMessage(), 500);
    }
}

// WRITE operations require admin session
if ($action === 'add' || $action === 'update' || $action === 'delete') {
    if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
        err('Unauthorized: Admin access required', 403);
    }
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 600 * 1024) {
        err('Request is too large. Please choose a smaller landmark photo.', 413);
    }
}

if ($action === 'add') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = $_POST;
    $name = isset($body['name']) ? trim($body['name']) : '';
    $description = isset($body['description']) ? trim($body['description']) : '';
    $lat = isset($body['lat']) ? (float)$body['lat'] : 0.0;
    $lng = isset($body['lng']) ? (float)$body['lng'] : 0.0;
    $photo = validateLandmarkPhoto(isset($body['photo']) ? $body['photo'] : null);
    $category = isset($body['category']) ? trim($body['category']) : 'historical';

    if ($name === '') err('Name is required');
    if (!$lat || !$lng) err('Latitude and Longitude are required');

    try {
        $stmt = $pdo->prepare("INSERT INTO landmarks (name, description, lat, lng, photo, category) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $lat, $lng, $photo, $category]);
        jsonOut(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    } catch (PDOException $e) {
        err('Database error: ' . $e->getMessage(), 500);
    }
}

if ($action === 'update') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = $_POST;
    $id = isset($body['id']) ? (int)$body['id'] : 0;
    $name = isset($body['name']) ? trim($body['name']) : '';
    $description = isset($body['description']) ? trim($body['description']) : '';
    $lat = isset($body['lat']) ? (float)$body['lat'] : 0.0;
    $lng = isset($body['lng']) ? (float)$body['lng'] : 0.0;
    $hasPhoto = array_key_exists('photo', $body);
    $photo = $hasPhoto ? validateLandmarkPhoto($body['photo']) : null;
    $category = isset($body['category']) ? trim($body['category']) : 'historical';

    if (!$id) err('ID is required');
    if ($name === '') err('Name is required');
    if (!$lat || !$lng) err('Latitude and Longitude are required');

    try {
        if ($hasPhoto) {
            $stmt = $pdo->prepare("UPDATE landmarks SET name = ?, description = ?, lat = ?, lng = ?, photo = ?, category = ? WHERE id = ?");
            $stmt->execute([$name, $description, $lat, $lng, $photo, $category, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE landmarks SET name = ?, description = ?, lat = ?, lng = ?, category = ? WHERE id = ?");
            $stmt->execute([$name, $description, $lat, $lng, $category, $id]);
        }
        jsonOut(['success' => true]);
    } catch (PDOException $e) {
        err('Database error: ' . $e->getMessage(), 500);
    }
}

if ($action === 'delete') {
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $id = isset($body['id']) ? (int)$body['id'] : 0;

    if (!$id) err('ID is required');

    try {
        $stmt = $pdo->prepare("DELETE FROM landmarks WHERE id = ?");
        $stmt->execute([$id]);
        jsonOut(['success' => true]);
    } catch (PDOException $e) {
        err('Database error: ' . $e->getMessage(), 500);
    }
}

err('Unknown action');
?>
