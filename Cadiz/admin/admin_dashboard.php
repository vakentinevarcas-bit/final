<?php
require_once __DIR__ . '/../Cadiz/session.php';
require_once __DIR__ . '/../Cadiz/db.php';

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = isset($_SESSION['admin_full_name']) ? $_SESSION['admin_full_name'] : (isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Administrator');

$avgRatingStmt = $pdo->query("SELECT AVG(rating) as avg_r FROM feedback");
$avgRatingRow = $avgRatingStmt->fetch();
$avgRating = isset($avgRatingRow['avg_r']) ? (float)$avgRatingRow['avg_r'] : 4.8;

$feedbackCountStmt = $pdo->query("SELECT COUNT(*) as count_f FROM feedback");
$totalFeedbackCount = (int)$feedbackCountStmt->fetchColumn();

$recentFeedbackStmt = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC LIMIT 4");
$recentFeedbacks = $recentFeedbackStmt->fetchAll();

$allFeedbackStmt = $pdo->query("SELECT * FROM feedback ORDER BY created_at DESC");
$allFeedbacks = $allFeedbackStmt->fetchAll();

function renderStars($rating) {
    $html = '<span class="stars">';

    $r = is_numeric($rating) ? (int)$rating : 0;

    if ($r < 0) $r = 0;
    if ($r > 5) $r = 5;

    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $r) {
            $html .= '<i class="fas fa-star star-filled" style="color: #ffc107; margin-right: 2px;"></i>';
        } else {
            $html .= '<i class="far fa-star star-empty" style="color: #ccc; margin-right: 2px;"></i>';
        }
    }

    $html .= '</span>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../css/admin_dashboard.css">
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <i class="fas fa-cube"></i>
            <span>LOGO</span>
        </div>

        <nav class="nav">
            <a href="#" class="nav-item active" data-page="dashboard">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
        <a href="#" class="nav-item" data-page="map">
                <i class="fas fa-map-pin"></i> Map
            </a>
            <a href="#" class="nav-item" data-page="chat">
                <i class="fas fa-comments"></i> Chat
                <span id="chatUnreadBadge" style="display:none;background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 7px;border-radius:30px;margin-left:auto;min-width:20px;text-align:center;transition:transform 0.15s ease;">0</span>
            </a>
            <a href="#" class="nav-item" data-page="feedback">
                <i class="fas fa-comment-dots"></i> Feed back
            </a>
        </nav>

        <div class="nav-footer">
            <a href="logout.php" class="nav-item" id="adminLogoutBtn">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </a>
        </div>
    </aside>

    <main class="main" id="main">

        <div class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 id="pageTitle">Dashboard</h1>
            </div>
            <div class="topbar-right">
                <span class="date"><i class="far fa-calendar-alt" style="margin-right:6px;"></i> July 5, 2026</span>
                <div class="avatar" title="<?= htmlspecialchars($adminName) ?>"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
                <span style="font-weight:600; color:#0a0a23; margin-left:4px; font-size:15px;"><?= htmlspecialchars($adminName) ?></span>
            </div>
        </div>

        <!-- ========== DASHBOARD ========== -->
        <section class="page-section active" id="page-dashboard">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label"><i class="fas fa-users"></i> Users</div>
                    <div class="value">12,584</div>
                    <span class="change"><i class="fas fa-arrow-up"></i> 12.5%</span>
                </div>
                <div class="stat-card">
                    <div class="label"><i class="fas fa-shopping-cart"></i> Sales</div>
                    <div class="value">$48,290</div>
                    <span class="change"><i class="fas fa-arrow-up"></i> 8.2%</span>
                </div>
                <div class="stat-card">
                    <div class="label"><i class="fas fa-eye"></i> Visits</div>
                    <div class="value">87,430</div>
                    <span class="change negative"><i class="fas fa-arrow-down"></i> 2.1%</span>
                </div>
                <div class="stat-card">
                    <div class="label"><i class="fas fa-star"></i> Rating</div>
                    <div class="value"><?= number_format($avgRating, 1) ?></div>
                    <span class="change"><i class="fas fa-arrow-up"></i> 0.6%</span>
                </div>
            </div>

            <div class="charts-row">
                <div class="chart-card">
                    <h3>Weekly Activity <span>Last 7 days</span></h3>
                    <div class="chart-placeholder" id="barChart"></div>
                </div>
                <div class="chart-card">
                    <h3>Traffic Sources</h3>
                    <div class="doughnut-placeholder">
                        <div class="doughnut-svg"></div>
                        <div class="doughnut-legend">
                            <div class="item"><span class="dot"></span> Direct &nbsp; 60%</div>
                            <div class="item"><span class="dot gray"></span> Referral &nbsp; 40%</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <h3>Recent Feedback</h3>
                <table>
                    <thead>
                        <tr><th>User</th><th>Place</th><th>Feedback</th><th>Rating</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentFeedbacks)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #8a95a8; padding: 20px;">No feedback submitted yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentFeedbacks as $fb): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($fb['user_name']) ?></strong></td>
                                    <td><span style="font-size: 13px; font-weight: 600; color: #4f46e5; background: #eef0ff; padding: 4px 10px; border-radius: 12px; white-space: nowrap;"><?= htmlspecialchars($fb['place_name']) ?></span></td>
                                    <td><?= htmlspecialchars($fb['comment']) ?></td>
                                    <td><?= renderStars($fb['rating']) ?></td>
                                    <td><?= date('M j, Y', strtotime($fb['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ========== WEBSITE ========== -->
        <section class="page-section" id="page-website">
            <div class="page-placeholder">
                <div class="icon-big"><i class="fas fa-globe"></i></div>
                <h2>Website Management</h2>
                <p>This is the Website page. Here you could manage your site content, SEO settings, and traffic analytics.</p>
                <button class="action-btn secondary" id="stayBtn">
                    <i class="fas fa-hand-peace"></i> Stay (does nothing)
                </button>
                <p style="margin-top: 14px; font-size: 14px; color: #5b677b;">
                    This button is just for show – it doesn't reload or navigate.
                </p>
            </div>
        </section>

        <!-- ========== MAP ========== -->
        <section class="page-section" id="page-map">
            <div class="app">
                <div class="app-header">
                    <h1><i class="fas fa-map"></i> Landmark Manager</h1>
                    <div class="landmark-count">
                        <i class="fas fa-location-dot"></i>
                        <span id="landmarkCount">0</span> landmarks
                    </div>
                </div>

                <div class="map-wrapper">
                    <div id="map"></div>
                    <div class="coords-display" id="coordsDisplay">
                        <i class="fas fa-crosshairs"></i>
                        <span id="coordLat">10.9489</span><span class="coord-sep">,</span>
                        <span id="coordLng">123.3037</span>
                        <span style="opacity:0.4;margin-left:6px;font-weight:300;">&bull; Cadiz City</span>
                    </div>
                </div>

                <div class="toolbar">
                    <button class="btn btn-update" id="btnUpdate" disabled>
                        <i class="fas fa-save"></i> Update
                    </button>
                    <button class="btn btn-edit" id="btnEdit" disabled>
                        <i class="fas fa-pen"></i> Edit
                    </button>
                    <button class="btn btn-delete" id="btnDelete" disabled>
                        <i class="fas fa-trash"></i> Delete
                    </button>
                    <button class="btn btn-add" id="btnAdd" type="button">
                        <i class="fas fa-plus"></i> <span id="btnAddLabel">Add Landmark</span>
                    </button>
                    <span class="spacer"></span>
                    <span class="status-badge" id="statusBadge">
                        <span class="dot dot-idle"></span>
                        <span id="statusText">Ready</span>
                    </span>
                </div>
            </div>

            <div class="modal-overlay" id="modalOverlay">
                <div class="modal" id="modalContent">
                    <h2 id="modalTitle"><i class="fas fa-location-dot"></i> Landmark</h2>
                    <p class="subtitle" id="modalSubtitle">Fill in the details.</p>
                    <div class="form-group">
                        <label for="landmarkName">Name</label>
                        <input type="text" id="landmarkName" placeholder="e.g. Central Park" maxlength="50" />
                    </div>
                    <div class="form-group">
                        <label for="landmarkDesc">Description</label>
                        <textarea id="landmarkDesc" placeholder="A short note…" maxlength="140"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="landmarkCategory">Category</label>
                        <select id="landmarkCategory" style="width: 100%; border: 1px solid #d1d9e6; border-radius: 8px; padding: 8px 12px; font-size: 14px; outline: none; background: #fff;">
                            <option value="historical">Historical</option>
                            <option value="food">Food & Drink</option>
                            <option value="beaches">Beaches</option>
                            <option value="shopping">Shopping</option>
                            <option value="hotel">Hotel</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Photo</label>
                        <div class="photo-upload-area" id="photoUploadArea">
                            <div class="upload-icon"><i class="fas fa-camera"></i></div>
                            <div class="upload-text">Click to upload a photo</div>
                            <input type="file" id="photoInput" accept="image/*" />
                        </div>
                        <div class="photo-preview" id="photoPreview">
                            <img id="previewImage" src="" alt="Preview" />
                            <button class="remove-photo" id="removePhotoBtn" type="button">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button class="btn btn-secondary" id="modalCancel">Cancel</button>
                        <button class="btn btn-primary" id="modalSave">
                            <i class="fas fa-check"></i> Save
                        </button>
                    </div>
                </div>
            </div>

            <div class="toast-container" id="toastContainer"></div>
        </section>

        <!-- ========== CHAT ========== -->
        <section class="page-section" id="page-chat">
            <div class="chat-app">
                <!-- User List -->
                <div class="chat-user-list">
                    <div class="list-header">
                        <span><i class="fas fa-comments" style="margin-right:8px;"></i> Live Chats</span>
                        <span id="chatUserCount" style="font-size:12px;background:#4f46e5;color:#fff;padding:2px 8px;border-radius:20px;">0</span>
                    </div>
                    <div style="padding:10px 12px;">
                        <input id="chatUserSearch" type="text" placeholder="Search user…" style="width:100%;border:1px solid #e0e7ef;border-radius:8px;padding:6px 10px;font-size:13px;outline:none;">
                    </div>
                    <div style="overflow-y:auto;flex:1;" id="userListContainer">
                        <div style="text-align:center;padding:30px;color:#8a95a8;font-size:13px;">
                            <i class="fas fa-spinner fa-spin" style="font-size:20px;"></i><br>Loading users…
                        </div>
                    </div>
                </div>

                <!-- Chat Window -->
                <div class="chat-window" id="chatWindow">
                    <!-- Chat Header -->
                    <div class="chat-header" id="chatHeader">
                        <div class="avatar" id="chatAvatar" style="background:#4f46e5;">?</div>
                        <div class="user-details">
                            <span class="name" id="chatName">Select a user</span>
                            <span class="email" id="chatEmail">to see their messages</span>
                            <span class="status-text" id="chatStatus" style="color:#22c55e;">
                                <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:4px;"></span>
                                Live
                            </span>
                        </div>
                        <div class="user-actions">
                            <button title="Refresh" id="refreshChatBtn"><i class="fas fa-sync-alt"></i></button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="chat-messages" id="chatMessages">
                        <div class="chat-empty" id="chatEmpty">
                            <i class="fas fa-comment-dots"></i>
                            <h3>Select a user</h3>
                            <p>Pick a user from the left panel to view their live chat.</p>
                        </div>
                    </div>

                    <!-- Admin Reply Input -->
                    <div class="chat-input" id="chatInputArea">
                        <input type="text" id="adminChatInput" placeholder="Type a reply to user…" disabled />
                        <button id="adminSendBtn" disabled><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </section>

        <script>
        // ── Admin Live Chat JS ─────────────────────────────────────────────
        (function () {
            var adminName      = <?= json_encode($adminName) ?>;
            var activeUserId   = null;
            var activeUserName = '';
            var lastMsgId      = 0;
            var pollTimer      = null;
            var allUsers       = [];

            var userListEl    = document.getElementById('userListContainer');
            var chatMsgEl     = document.getElementById('chatMessages');
            var chatEmptyEl   = document.getElementById('chatEmpty');
            var chatNameEl    = document.getElementById('chatName');
            var chatEmailEl   = document.getElementById('chatEmail');
            var chatAvatarEl  = document.getElementById('chatAvatar');
            var adminInput    = document.getElementById('adminChatInput');
            var adminSendBtn  = document.getElementById('adminSendBtn');
            var userCountEl   = document.getElementById('chatUserCount');
            var searchEl      = document.getElementById('chatUserSearch');
            var refreshBtn    = document.getElementById('refreshChatBtn');

            // ── Helpers ──────────────────────────────────────────────────────
            function esc(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }
            function fmtTime(ts) {
                if (!ts) return '';
                var d = new Date(ts.replace(' ','T'));
                return d.toLocaleTimeString([],{hour:'2-digit',minute:'2-digit'}) +
                       ' · ' + d.toLocaleDateString([],{month:'short',day:'numeric'});
            }
            function avatarLetter(name) {
                return name ? name.charAt(0).toUpperCase() : '?';
            }
            var colors = ['#4f46e5','#7c3aed','#0891b2','#059669','#d97706','#dc2626'];
            function avatarColor(uid) {
                return colors[uid % colors.length];
            }

        // ── Load user list ───────────────────────────────────────────────
            function loadUsers() {
                fetch('../Cadiz/chat_api.php?action=users')
                .then(function(r){return r.json();})
                .then(function(data){
                    if (!data.success) return;
                    allUsers = data.users || [];
                    userCountEl.textContent = allUsers.length;
                    renderUserList(allUsers);
                });
            }

            function renderUserList(users) {
                if (!users.length) {
                    userListEl.innerHTML = '<div style="text-align:center;padding:30px;color:#8a95a8;font-size:13px;"><i class="fas fa-inbox" style="font-size:24px;margin-bottom:8px;display:block;"></i>No users yet</div>';
                    return;
                }
                var html = '';
                users.forEach(function(u){
                    var isActive = u.user_id == activeUserId;
                    html += '<div class="user-item' + (isActive ? ' active-user' : '') + '" data-uid="' + u.user_id + '" data-name="' + esc(u.user_name) + '" style="display:flex;align-items:center;gap:10px;padding:12px 14px;cursor:pointer;border-bottom:1px solid #f0f2f7;transition:background 0.15s;' + (isActive ? 'background:#f0eeff;' : '') + '">' +
                        '<div style="width:40px;height:40px;border-radius:50%;background:' + avatarColor(parseInt(u.user_id)) + ';color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;flex-shrink:0;">' + avatarLetter(u.user_name) + '</div>' +
                        '<div style="flex:1;min-width:0;">' +
                            '<div style="font-weight:600;font-size:14px;color:#1e2636;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(u.user_name) + '</div>' +
                            '<div style="font-size:12px;color:#8a95a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (u.last_msg_text ? esc(u.last_msg_text) : (u.msg_count + ' messages')) + '</div>' +
                        '</div>' +
                        '<div style="font-size:11px;color:#b0b8c9;white-space:nowrap;">' + (u.last_msg ? fmtTime(u.last_msg).split(' · ')[1] : '') + '</div>' +
                    '</div>';
                });
                userListEl.innerHTML = html;

                userListEl.querySelectorAll('.user-item').forEach(function(el){
                    el.addEventListener('click', function(){
                        selectUser(parseInt(el.dataset.uid), el.dataset.name);
                    });
                    el.addEventListener('mouseover', function(){ if(!el.classList.contains('active-user')) el.style.background='#f8f9fe'; });
                    el.addEventListener('mouseout',  function(){ if(!el.classList.contains('active-user')) el.style.background=''; });
                });
            }

            // ── Select a user to chat ────────────────────────────────────────
            function selectUser(uid, name) {
                activeUserId   = uid;
                activeUserName = name;
                lastMsgId      = 0;

                chatNameEl.textContent   = name;
                chatEmailEl.textContent  = 'User ID: ' + uid;
                chatAvatarEl.textContent = avatarLetter(name);
                chatAvatarEl.style.background = avatarColor(uid);

                adminInput.disabled    = false;
                adminSendBtn.disabled  = false;
                adminInput.placeholder = 'Reply to ' + name + '…';

                // Clear messages area
                chatMsgEl.innerHTML = '';
                chatEmptyEl && chatMsgEl.appendChild(chatEmptyEl);
                chatEmptyEl.style.display = 'none';

                // Load full history then start polling
                loadHistory(true);
                renderUserList(allUsers); // refresh to show active
                startPoll();
            }

            // ── Load message history ─────────────────────────────────────────
            function loadHistory(full) {
                if (!activeUserId) return;
                var after = full ? 0 : lastMsgId;
                fetch('../Cadiz/chat_api.php?action=history&user_id=' + activeUserId + '&after=' + after)
                .then(function(r){return r.json();})
                .then(function(data){
                    if (!data.success || !data.messages.length) return;
                    if (full) {
                        // Remove spinner/empty
                        chatMsgEl.querySelectorAll('.admin-chat-msg').forEach(function(m){m.remove();});
                    }
                    data.messages.forEach(function(m){
                        if (m.id > lastMsgId) {
                            appendAdminMsg(m);
                            lastMsgId = m.id;
                        }
                    });
                });
            }

            function appendAdminMsg(m) {
                var isUser = m.sender === 'user';
                var div = document.createElement('div');
                div.className = 'admin-chat-msg';
                div.style.cssText = 'display:flex;align-items:flex-end;gap:8px;margin-bottom:12px;' + (isUser ? '' : 'flex-direction:row-reverse;');
                div.innerHTML =
                    '<div style="width:32px;height:32px;border-radius:50%;background:' + (isUser ? avatarColor(activeUserId) : '#4f46e5') + ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;" title="' + esc(isUser ? activeUserName : adminName) + '">' +
                        (isUser ? avatarLetter(activeUserName) : avatarLetter(adminName)) +
                    '</div>' +
                    '<div style="max-width:70%;">' +
                        '<div style="font-size:11px; font-weight:600; color:#5b677b; margin-bottom:4px; margin-left:4px; margin-right:4px; text-align:' + (isUser ? 'left' : 'right') + ';">' + esc(isUser ? activeUserName : adminName) + '</div>' +
                        '<div style="background:' + (isUser ? '#f0f2f7' : '#4f46e5') + ';color:' + (isUser ? '#1e2636' : '#fff') + ';padding:10px 14px;border-radius:' + (isUser ? '16px 16px 16px 4px' : '16px 16px 4px 16px') + ';font-size:14px;line-height:1.45;">' + esc(m.message) + '</div>' +
                        '<div style="font-size:11px;color:#b0b8c9;margin-top:4px;text-align:' + (isUser ? 'left' : 'right') + ';">' + fmtTime(m.created_at) + '</div>' +
                    '</div>';
                chatMsgEl.appendChild(div);
                chatMsgEl.scrollTop = chatMsgEl.scrollHeight;
            }

            // ── Polling ──────────────────────────────────────────────────────
            function startPoll() {
                if (pollTimer) clearInterval(pollTimer);
                pollTimer = setInterval(function(){
                    loadHistory(false);
                    loadUsers(); // refresh user list counts
                }, 3000);
            }

            // ── Admin sends reply ────────────────────────────────────────────
            function adminReply() {
                var msg = adminInput.value.trim();
                if (!msg || !activeUserId) return;
                adminInput.value = '';
                adminSendBtn.disabled = true;

                fetch('../Cadiz/chat_api.php?action=admin_reply', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({user_id: activeUserId, message: msg})
                })
                .then(function(r){return r.json();})
                .then(function(data){
                    if (data.success) {
                        appendAdminMsg({sender:'admin', message: msg, created_at: new Date().toISOString().replace('T',' ').slice(0,19), id: data.id});
                        lastMsgId = data.id;
                    }
                })
                .finally(function(){ adminSendBtn.disabled = false; });
            }

            adminSendBtn.addEventListener('click', adminReply);
            adminInput.addEventListener('keypress', function(e){ if(e.key==='Enter') adminReply(); });
            refreshBtn.addEventListener('click', function(){ loadUsers(); if(activeUserId) loadHistory(true); });

            // ── Search filter ─────────────────────────────────────────────────
            searchEl.addEventListener('input', function(){
                var q = searchEl.value.toLowerCase();
                var filtered = allUsers.filter(function(u){ return u.user_name.toLowerCase().includes(q); });
                renderUserList(filtered);
            });

            // ── Init ──────────────────────────────────────────────────────────
            loadUsers();
            setInterval(loadUsers, 5000); // refresh user list every 5s
        })();

        // ── Global New Message Poller & Notifications ──────────────────────
        (function() {
            var lastGlobalId = 0;
            var unreadCount = 0;
            var badgeEl = document.getElementById('chatUnreadBadge');
            var toastContainer = document.getElementById('adminGlobalToast');

            var chatNavItem = document.querySelector('.nav-item[data-page="chat"]');

            function esc(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'<').replace(/>/g,'>');
            }

            function clearBadge() {
                unreadCount = 0;
                if (badgeEl) {
                    badgeEl.style.display = 'none';
                    badgeEl.textContent = '0';
                }
            }

            function showGlobalToast(userName, message) {
                if (!toastContainer) return;
                var toast = document.createElement('div');
                toast.style.cssText = 'background:#0b1e33;color:#f0f5fc;padding:14px 20px;border-radius:14px;font-size:0.92rem;font-weight:500;box-shadow:0 12px 40px rgba(0,0,0,0.20);display:flex;align-items:center;gap:12px;animation:fadeIn 0.35s ease;border-left:4px solid #4f46e5;cursor:pointer;';
                toast.innerHTML = '<div style="width:36px;height:36px;border-radius:50%;background:#4f46e5;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex-shrink:0;">' + (userName ? userName.charAt(0).toUpperCase() : '?') + '</div>' +
                    '<div style="flex:1;min-width:0;">' +
                        '<div style="font-weight:700;font-size:13px;color:#fff;">' + esc(userName) + ' sent a message</div>' +
                        '<div style="font-size:12px;color:#b0b8c9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + esc(message.substring(0, 80)) + '</div>' +
                    '</div>' +
                    '<button onclick="event.stopPropagation();this.parentElement.remove()" style="background:none;border:none;color:#8a95a8;cursor:pointer;font-size:16px;padding:4px;">&times;</button>';
                toastContainer.appendChild(toast);

                toast.addEventListener('click', function(e) {
                    if (e.target.tagName === 'BUTTON') return;
                    if (chatNavItem) chatNavItem.click();
                    clearBadge();
                    toastContainer.innerHTML = '';
                });

                setTimeout(function() {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(30px)';
                    toast.style.transition = 'all 0.3s ease';
                    setTimeout(function() { if (toast.parentElement) toast.remove(); }, 350);
                }, 5000);
            }

            function pollGlobal() {
                fetch('../Cadiz/chat_api.php?action=global_poll&after=' + lastGlobalId)
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data.success) return;
                    if (data.latest_id > lastGlobalId) {
                        lastGlobalId = data.latest_id;
                    }
                    if (data.new_messages && data.new_messages.length > 0) {
                        data.new_messages.forEach(function(m) {
                            if (m.id > lastGlobalId) {
                                lastGlobalId = m.id;
                            }
                            var chatPage = document.getElementById('page-chat');
                            var isOnChatPage = chatPage && chatPage.classList.contains('active');
                            if (!isOnChatPage) {
                                unreadCount++;
                                if (badgeEl) {
                                    badgeEl.style.display = 'inline';
                                    badgeEl.textContent = unreadCount;
                                    badgeEl.style.transform = 'scale(1.3)';
                                    setTimeout(function() { badgeEl.style.transform = 'scale(1)'; }, 200);
                                }
                                showGlobalToast(m.user_name, m.message);
                            }
                        });
                    }
                })
                .catch(function(){});
            }

            setInterval(pollGlobal, 4000);
            setTimeout(pollGlobal, 1000);

            if (chatNavItem) {
                chatNavItem.addEventListener('click', function() {
                    clearBadge();
                    if (toastContainer) toastContainer.innerHTML = '';
                });
            }
        })();
        </script>

        <!-- ========== GLOBAL TOAST CONTAINER ========== -->
        <div id="adminGlobalToast" style="position:fixed;bottom:30px;right:30px;z-index:9000;display:flex;flex-direction:column;gap:10px;max-width:400px;width:100%;pointer-events:auto;"></div>

        <!-- ========== FEEDBACK ========== -->
        <section class="page-section" id="page-feedback">
            <div class="page-placeholder" style="text-align:left; padding: 32px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <h2 style="font-size:24px; font-weight:600; color:#0a0a23; margin:0;"><i class="fas fa-comment-dots" style="color:#4f46e5; margin-right:10px;"></i>Feedback Center</h2>
                        <p style="color:#5b677b; margin-top:4px;">All user feedback with ratings and dates.</p>
                    </div>
                    <div style="display:flex; gap:12px;">
                        <div style="background:#f8f9fe; border-radius:12px; padding:12px 20px;">
                            <div style="font-weight:700; font-size:20px;"><?= $totalFeedbackCount ?></div>
                            <div style="font-size:13px; color:#5b677b;">Total Reviews</div>
                        </div>
                        <div style="background:#f8f9fe; border-radius:12px; padding:12px 20px;">
                            <div style="font-weight:700; font-size:20px;"><?= number_format($avgRating, 1) ?></div>
                            <div style="font-size:13px; color:#5b677b;">Avg Rating</div>
                        </div>
                    </div>
                </div>

                <!-- Feedback Table (no Status column) -->
                <div style="background:#ffffff; border-radius:16px; border:1px solid #eef1f8; overflow-x:auto; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                    <table style="width:100%; border-collapse:collapse; font-size:14px;">
                        <thead style="background:#f8f9fe; border-radius:10px;">
                            <tr>
                                <th style="padding:12px 16px; text-align:left; font-weight:600; color:#3d4a5c; font-size:13px;">User</th>
                                <th style="padding:12px 16px; text-align:left; font-weight:600; color:#3d4a5c; font-size:13px;">Place</th>
                                <th style="padding:12px 16px; text-align:left; font-weight:600; color:#3d4a5c; font-size:13px;">Feedback</th>
                                <th style="padding:12px 16px; text-align:left; font-weight:600; color:#3d4a5c; font-size:13px;">Rating</th>
                                <th style="padding:12px 16px; text-align:left; font-weight:600; color:#3d4a5c; font-size:13px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allFeedbacks)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #8a95a8; padding: 20px;">No feedback submitted yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allFeedbacks as $fb): ?>
                                    <tr>
                                        <td style="padding:12px 16px;"><strong><?= htmlspecialchars($fb['user_name']) ?></strong></td>
                                        <td style="padding:12px 16px;"><span style="font-size: 13px; font-weight: 600; color: #4f46e5; background: #eef0ff; padding: 4px 10px; border-radius: 12px; white-space: nowrap;"><?= htmlspecialchars($fb['place_name']) ?></span></td>
                                        <td style="padding:12px 16px;"><?= htmlspecialchars($fb['comment']) ?></td>
                                        <td style="padding:12px 16px;"><?= renderStars($fb['rating']) ?></td>
                                        <td style="padding:12px 16px;"><?= date('M j, Y', strtotime($fb['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>

    <script src="../js/admin_dashboard.js?v=<?= filemtime(__DIR__ . '/../js/admin_dashboard.js') ?>">
        
    </script>

</body>
</html>
