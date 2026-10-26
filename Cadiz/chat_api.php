<?php
session_start();
header('Content-Type: application/json');
require_once 'db.php';

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

function jsonOut($data) { echo json_encode($data); exit; }
function err($msg, $code = 400) { http_response_code($code); jsonOut(['success' => false, 'message' => $msg]); }

if ($action === 'send') {
    if (!isset($_SESSION['user_id'])) err('Not logged in', 401);

    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $msg = isset($body['message']) ? trim($body['message']) : '';
    if ($msg === '') err('Empty message');

    $userId = (int)$_SESSION['user_id'];
    $userName = $_SESSION['full_name'];

    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, user_name, sender, message) VALUES (?,?,?,?)");
    $stmt->execute([$userId, $userName, 'user', $msg]);
    $msgId = (int)$pdo->lastInsertId();

    $lastAdminCheck = $pdo->prepare(
        "SELECT sender, created_at FROM chat_messages 
         WHERE user_id = ? AND sender = 'admin' 
         ORDER BY id DESC LIMIT 1"
    );
    $lastAdminCheck->execute([$userId]);
    $lastAdmin = $lastAdminCheck->fetch();

    $lastUserCheck = $pdo->prepare(
        "SELECT created_at FROM chat_messages 
         WHERE user_id = ? AND sender = 'user' AND id < ?
         ORDER BY id DESC LIMIT 1"
    );
    $lastUserCheck->execute([$userId, $msgId]);
    $lastUser = $lastUserCheck->fetch();

    $shouldAutoReply = true;
    if ($lastAdmin && $lastUser) {
        $adminTime = strtotime($lastAdmin['created_at']);
        $userTime = strtotime($lastUser['created_at']);
        if ($adminTime > $userTime) {
            $shouldAutoReply = false;
        }
    }

    if ($shouldAutoReply) {
        $botReply = getBotReply($msg);
        $stmt2 = $pdo->prepare("INSERT INTO chat_messages (user_id, user_name, sender, message) VALUES (?,?,?,?)");
        $stmt2->execute([$userId, $userName, 'admin', $botReply]);
        $botReplyId = (int)$pdo->lastInsertId();
    } else {
        $botReply = null;
        $botReplyId = null;
    }

    jsonOut(['success' => true, 'id' => $msgId, 'bot_reply' => $botReply, 'bot_reply_id' => $botReplyId]);
}

if ($action === 'poll') {
    if (!isset($_SESSION['user_id'])) err('Not logged in', 401);

    $userId = (int)$_SESSION['user_id'];
    $afterId = isset($_GET['after']) ? (int)$_GET['after'] : 0;
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;

    $stmt = $pdo->prepare(
        "SELECT id, user_name, sender, message, created_at FROM chat_messages
         WHERE user_id = ? AND id > ?
         ORDER BY id ASC LIMIT ?"
    );
    $stmt->execute([$userId, $afterId, $limit]);
    jsonOut(['success' => true, 'messages' => $stmt->fetchAll()]);
}

if ($action === 'full_history') {
    if (!isset($_SESSION['user_id'])) err('Not logged in', 401);

    $userId = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare(
        "SELECT id, user_name, sender, message, created_at FROM chat_messages
         WHERE user_id = ?
         ORDER BY id ASC LIMIT 200"
    );
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll();

    $latestId = 0;
    if (!empty($messages)) {
        $last = end($messages);
        $latestId = (int)$last['id'];
    }

    jsonOut(['success' => true, 'messages' => $messages, 'latest_id' => $latestId]);
}

if ($action === 'users') {
    $rows = $pdo->query(
        "SELECT
            cm.user_id,
            cm.user_name,
            MAX(cm.created_at)  AS last_msg,
            SUM(cm.sender='user') AS msg_count,
            (SELECT message FROM chat_messages c2 WHERE c2.user_id = cm.user_id ORDER BY c2.id DESC LIMIT 1) AS last_msg_text
         FROM chat_messages cm
         GROUP BY cm.user_id, cm.user_name
         ORDER BY last_msg DESC"
    )->fetchAll();

    jsonOut(['success' => true, 'users' => $rows]);
}

if ($action === 'history') {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    $afterId = isset($_GET['after']) ? (int)$_GET['after'] : 0;
    if (!$userId) err('user_id required');

    $stmt = $pdo->prepare(
        "SELECT id, user_name, sender, message, created_at FROM chat_messages
         WHERE user_id = ? AND id > ?
         ORDER BY id ASC LIMIT 100"
    );
    $stmt->execute([$userId, $afterId]);
    jsonOut(['success' => true, 'messages' => $stmt->fetchAll()]);
}

if ($action === 'admin_reply') {
    $body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $userId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
    $msg = isset($body['message']) ? trim($body['message']) : '';

    if (!$userId || $msg === '') err('user_id and message required');

    $nameRow = $pdo->prepare("SELECT user_name FROM chat_messages WHERE user_id=? LIMIT 1");
    $nameRow->execute([$userId]);
    $row = $nameRow->fetch();
    $userName = $row ? $row['user_name'] : 'User';

    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, user_name, sender, message) VALUES (?,?,?,?)");
    $stmt->execute([$userId, $userName, 'admin', $msg]);

    jsonOut(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
}

if ($action === 'global_poll') {
    $afterId = isset($_GET['after']) ? (int)$_GET['after'] : 0;

    $latestIdRow = $pdo->query("SELECT MAX(id) as max_id FROM chat_messages")->fetch();
    $latestId = $latestIdRow ? (int)$latestIdRow['max_id'] : 0;

    $stmt = $pdo->prepare(
        "SELECT id, user_id, user_name, sender, message, created_at FROM chat_messages
         WHERE id > ? AND sender = 'user'
         ORDER BY id ASC LIMIT 20"
    );
    $stmt->execute([$afterId]);
    $newMessages = $stmt->fetchAll();

    jsonOut([
        'success' => true,
        'latest_id' => $latestId,
        'new_messages' => $newMessages
    ]);
}

err('Unknown action');

function getBotReply($msg) {
    $m = strtolower($msg);

    // -- Greetings --
    if (preg_match('/\b(hello|hi|hey|good morning|good afternoon|good evening|howdy|greetings|kamusta|maayong)\b/', $m))
        return 'Hello! Welcome to CADIZGO Assistant! I can help you find beaches, historical spots, food, shopping, and more in Cadiz City. What would you like to explore?';

    if (preg_match('/\b(thank|thanks|salamat|thank you)\b/', $m))
        return 'You\'re welcome! Enjoy your time in Cadiz City. Feel free to ask me anything about local attractions!';

    if (preg_match('/\b(how are you|how are you doing|kamusta|okay ka)\b/', $m))
        return 'I\'m doing great, thanks! Ready to help you explore Cadiz City. What are you looking for?';

    // -- About Cadiz City --
    if (preg_match('/\b(cadiz city|cadiz|cadizgo)\b/', $m) && !preg_match('/\b(plaza|mall|market|beach|food|historical|shop)\b/', $m))
        return 'Cadiz City is a coastal city in Negros Occidental, Philippines. Known as the "City of Whales" for the whale sharks at Lakawon Island! It has beautiful beaches, historical landmarks, shopping centers, and great local food. Use the categories on the Home screen or explore the Map tab!';

    // -- Beaches & Nature --
    if (preg_match('/\b(beach|beaches|sea|shore|swim|sand|island|sunset|nature)\b/', $m))
        return 'Cadiz has amazing beaches! Popular ones include: Aluyan Beach (peaceful sunset cove, 3.8 km from Plaza), Patag Beach (white sand, 2.4 km from Plaza), and Lakawon Island (famous sandbar resort, 12 km). Tap "Animated Route" on the Map to get directions to any beach!';

    if (preg_match('/\b(aluyan|aluyan beach)\b/', $m))
        return 'Aluyan Beach Resort - A quiet, peaceful cove known for stunning sunset views. Located about 3.8 km from the Plaza. Perfect for relaxation and swimming. Switch to the Map tab and tap "Animated Route" to get there!';

    if (preg_match('/\b(patag|patag beach)\b/', $m))
        return 'Patag Beach - A beautiful beach with fine white sand and tranquil waters. Located about 2.4 km from the Plaza. Excellent for sunset viewing! Switch to Map and use "Animated Route" for directions.';

    if (preg_match('/\b(lakawon|lacawon|whale shark|sandbar)\b/', $m))
        return 'Lakawon Island Resort - The crown jewel of Cadiz! A famous white sandbar with crystal clear waters, floating bar, and whale shark sightings. Located about 12 km from the Plaza. Best visited as a day trip! Switch to Map to see its location.';

    // -- Historical Places --
    if (preg_match('/\b(historical|history|landmark|lumang|heritage|culture)\b/', $m))
        return 'Historical spots in Cadiz: Cadiz City Plaza (historic heart with gardens), San Diego Cathedral (old cathedral with beautiful architecture), and Cadiz City Hall (seat of local government). Visit these on the Map tab and tap "Animated Route" to navigate!';

    if (preg_match('/\b(san diego|cadiz cathedral|cathedral|church)\b/', $m))
        return 'San Diego Cathedral - A historic cathedral located near the Plaza (about 0.3 km). Beautiful architecture and a peaceful place to visit. Open to the public daily.';

    if (preg_match('/\b(plaza|city plaza|cadiz plaza)\b/', $m))
        return 'Cadiz City Plaza - The historic heart of Cadiz City with beautiful gardens and walking paths. A great starting point for exploring - it\'s near several shopping spots and landmarks!';

    // -- Food & Dining --
    if (preg_match('/\b(food|eat|restaurant|dining|lunch|dinner|breakfast|cuisine|cafe|coffee)\b/', $m))
        return 'Food spots in Cadiz: Seafood House (fresh local seafood, 0.6 km from Plaza), Cadiz Food Park (vibrant night market with street food, 0.2 km), and City Mall (food court with various dining options). Use the Map tab to find these and get animated routes!';

    if (preg_match('/\b(seafood|seafood house|fresh fish)\b/', $m))
        return 'Seafood House - Local cuisine featuring fresh catch-of-the-day seafood and authentic Negrense recipes. Located about 0.6 km from the Plaza. A must-try when visiting Cadiz!';

    if (preg_match('/\b(food park|cadiz food park|night market|street food)\b/', $m))
        return 'Cadiz Food Park - A vibrant evening food park and night market offering delicious local street foods. Located just 0.2 km from the Plaza. Perfect for dinner and trying local flavors!';

    // -- Shopping --
    if (preg_match('/\b(mall|shop|shopping|buy|grocery|store|department)\b/', $m))
        return 'Shopping in Cadiz: City Mall (clothes, snacks, dining, and department store), SM Hypermarket (one-stop groceries and daily needs), and Public Market (fresh local produce and sea harvests). Tap "Animated Route" on the Map for directions to any!';

    if (preg_match('/\b(city mall)\b/', $m) && !preg_match('/\bsm\b/', $m))
        return 'City Mall Cadiz - Modern department store offering shopping, dining options, and more. Located about 0.5 km from the Plaza. Great for clothes, snacks, and entertainment!';

    if (preg_match('/\b(sm|hypermarket|sm hypermarket)\b/', $m))
        return 'SM Hypermarket - Your one-stop grocery and retail center for all household needs. Located about 0.4 km from the Plaza. Convenient for daily necessities!';

    if (preg_match('/\b(public market|market|palengke|fresh produce)\b/', $m))
        return 'Cadiz Public Market - A busy local market featuring fresh local produce, seafood, and local goods. Located about 0.4 km from the Plaza. Great for experiencing local life!';

    // -- Navigation & Routes --
    if (preg_match('/\b(route|direction|navigate|how to get|way|map|guide)\b/', $m))
        return 'Getting around Cadiz: 1. Go to the Map tab (bottom nav). 2. Find your destination on the map. 3. Tap the marker and click "Animated Route". 4. Watch the animated path guide you! You can zoom, drag, and pan the map even while the route is active.';

    // -- Weather & Best Time --
    if (preg_match('/\b(weather|climate|best time|season|when to visit)\b/', $m))
        return 'The best time to visit Cadiz City is during the dry season (November to May). The weather is sunny and perfect for beach trips to Lakawon or Aluyan. Weekends are lively at the Food Park and Plaza!';

    // -- Events & Activities --
    if (preg_match('/\b(event|festival|fiesta|celebration|activity|things to do)\b/', $m))
        return 'Events & Activities in Cadiz: Enjoy the sunset at Aluyan Beach or Patag Beach, Visit Lakawon Island for the famous sandbar, Night food trip at Cadiz Food Park, Stroll at Cadiz City Plaza, Shopping at City Mall and SM Hypermarket, Visit San Diego Cathedral. Check the Home screen categories for more!';

    // -- Admin/Live Chat --
    if (preg_match('/\b(admin|agent|human|real person|talk to|staff)\b/', $m))
        return 'Our admin team monitors this chat. They\'ll reply to you soon if they\'re online! Meanwhile, ask me about beaches, food, or directions.';

    // -- App Features --
    if (preg_match('/\b(feature|app|how to use|help|ano ba|tutorial|guide)\b/', $m))
        return 'CADIZGO App Guide: Home (browse categories and popular attractions), Map (full interactive map with animated routes), Profile (your account and settings), Search (find places by name), Chatbot (ask me anything), Feedback (rate and review places). Tap the blue pulsing circle on the map to see your location!';

    // -- Default catch-all --
    return 'I\'m not sure about that. But I can help you with: Beaches (Aluyan, Patag, Lakawon), Historical spots (Plaza, Cathedral, City Hall), Food (Seafood House, Food Park), Shopping (City Mall, SM Hypermarket, Public Market), and Directions & Routes (animated map routing). Just type your question above!';
}

