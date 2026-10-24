<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

$isAdmin = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare(
            'SELECT id, title, description, location, event_date, event_time
             FROM events
             WHERE event_date >= ?
             ORDER BY event_date ASC, event_time ASC, id ASC'
        );
        $stmt->execute([$today]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($events as &$event) {
            $event['id'] = (int)$event['id'];
        }
        unset($event);
        echo json_encode(['success' => true, 'events' => $events], JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (PDOException $error) {
        error_log('Event API database error: ' . $error->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Upcoming events could not be loaded.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

if (!$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Administrator sign-in is required.']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($_SESSION['admin_events_csrf']) || !hash_equals($_SESSION['admin_events_csrf'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$action = isset($input['action']) ? trim((string)$input['action']) : '';

try {
    if ($action === 'create') {
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $location = trim((string)($input['location'] ?? ''));
        $eventDate = trim((string)($input['event_date'] ?? ''));
        $eventTime = trim((string)($input['event_time'] ?? ''));

        $parsedDate = DateTime::createFromFormat('!Y-m-d', $eventDate);
        if ($title === '' || mb_strlen($title) > 160) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Enter an event name up to 160 characters.']);
            exit;
        }
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $eventDate || $eventDate < $today) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Choose a valid date today or later.']);
            exit;
        }
        if (mb_strlen($location) > 160) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Location must be 160 characters or fewer.']);
            exit;
        }
        if ($eventTime !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $eventTime)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Enter a valid event time.']);
            exit;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO events (title, description, location, event_date, event_time)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $title,
            $description !== '' ? $description : null,
            $location !== '' ? $location : null,
            $eventDate,
            $eventTime !== '' ? $eventTime : null,
        ]);
        echo json_encode(['success' => true, 'message' => 'Event added.']);
        exit;
    }

    if ($action === 'delete') {
        $eventId = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$eventId || $eventId < 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Choose a valid event.']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'That event no longer exists.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Event removed.']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown event action.']);
} catch (PDOException $error) {
    error_log('Event API database error: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The event could not be saved. Please try again.']);
}
