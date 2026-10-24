<?php
require_once __DIR__ . '/../Cadiz/session.php';
require_once __DIR__ . '/../Cadiz/db.php';

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$adminName = isset($_SESSION['admin_full_name'])
    ? $_SESSION['admin_full_name']
    : (isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Administrator');

$usersStmt = $pdo->query("SELECT id, username, full_name, created_at FROM users ORDER BY created_at DESC");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
$escape = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadiz Go Admin | Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin_dashboard.css">
    <style>
        .users-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .users-summary {
            color: #5b677b;
            font-size: 14px;
        }
        .users-search {
            width: min(100%, 320px);
            padding: 11px 14px;
            border: 1px solid #e0e7ef;
            border-radius: 10px;
            background: #fff;
            font: inherit;
            outline: none;
        }
        .users-search:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, .12);
        }
        .users-table-wrap {
            overflow-x: auto;
        }
        .user-name {
            font-weight: 600;
        }
        .users-empty {
            padding: 28px 16px;
            color: #8a95a8;
            text-align: center;
        }
        @media (max-width: 600px) {
            .users-toolbar {
                align-items: stretch;
                flex-direction: column;
            }
            .users-search {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>
    <aside class="sidebar" id="sidebar">
        <button class="logo sidebar-toggle" id="sidebarToggle" type="button" aria-label="Expand navigation" aria-expanded="false">
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
        <nav class="nav">
            <a href="admin_dashboard.php#page-dashboard" class="nav-item">
                <i class="fas fa-th-large" aria-hidden="true"></i><span class="nav-item-label">Dashboard</span>
            </a>
            <a href="users.php" class="nav-item active" aria-current="page">
                <i class="fas fa-users" aria-hidden="true"></i><span class="nav-item-label">Users</span>
            </a>
            <a href="admin_dashboard.php#page-map" class="nav-item">
                <i class="fas fa-map-pin" aria-hidden="true"></i><span class="nav-item-label">Map</span>
            </a>
            <a href="admin_dashboard.php#page-chat" class="nav-item">
                <i class="fas fa-comments" aria-hidden="true"></i><span class="nav-item-label">Chat</span>
                <span id="chatUnreadBadge" style="display:none;background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:1px 7px;border-radius:30px;margin-left:auto;min-width:20px;text-align:center;transition:transform 0.15s ease;">0</span>
            </a>
            <a href="admin_dashboard.php#page-feedback" class="nav-item">
                <i class="fas fa-comment-dots" aria-hidden="true"></i><span class="nav-item-label">Feedback</span>
            </a>
        </nav>
        <div class="nav-footer">
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt" aria-hidden="true"></i><span class="nav-item-label">Log Out</span></a>
        </div>
    </aside>

    <main class="main" id="main">
        <header class="topbar">
            <div class="topbar-left">
                <h1>Users</h1>
            </div>
            <div class="topbar-right">
                <span class="date"><i class="far fa-calendar-alt" style="margin-right:6px;"></i> <?= date('F j, Y') ?></span>
                <div class="avatar" title="<?= $escape($adminName) ?>"><?= $escape(strtoupper(substr($adminName, 0, 1))) ?></div>
                <span style="font-weight:600;color:#0a0a23;margin-left:4px;font-size:15px;"><?= $escape($adminName) ?></span>
            </div>
        </header>

        <section>
            <div class="table-card">
                <div class="users-toolbar">
                    <div>
                        <h3>Registered Users</h3>
                        <p class="users-summary"><span id="visibleUserCount"><?= count($users) ?></span> users</p>
                    </div>
                    <input class="users-search" id="userSearch" type="search" placeholder="Search users..." aria-label="Search users">
                </div>
                <div class="users-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Joined</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="user-name"><?= $escape($user['full_name']) ?></td>
                                    <td><?= $escape($user['username']) ?></td>
                                    <td><?= $escape(date('M j, Y', strtotime($user['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="noUsersRow" <?= $users ? 'hidden' : '' ?>>
                                <td colspan="3" class="users-empty">No registered users found.</td>
                            </tr>
                            <tr id="noSearchResultsRow" hidden>
                                <td colspan="3" class="users-empty">No users match your search.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <script>
        (function () {
            var search = document.getElementById('userSearch');
            var tableBody = document.getElementById('usersTableBody');
            var count = document.getElementById('visibleUserCount');
            var rows = Array.prototype.slice.call(tableBody.querySelectorAll('tr')).filter(function (row) {
                return row.id !== 'noUsersRow' && row.id !== 'noSearchResultsRow';
            });
            var noUsersRow = document.getElementById('noUsersRow');
            var noResultsRow = document.getElementById('noSearchResultsRow');

            search.addEventListener('input', function () {
                var query = search.value.trim().toLowerCase();
                var visible = 0;
                rows.forEach(function (row) {
                    var matches = row.textContent.toLowerCase().includes(query);
                    row.hidden = !matches;
                    if (matches) visible++;
                });
                count.textContent = visible;
                noUsersRow.hidden = rows.length !== 0 || query !== '';
                noResultsRow.hidden = rows.length === 0 || visible !== 0;
            });

            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('overlay');
            var sidebarToggle = document.getElementById('sidebarToggle');
            var main = document.getElementById('main');
            function closeSidebar() {
                sidebar.classList.remove('open', 'expanded');
                main.classList.remove('sidebar-expanded');
                sidebarToggle.setAttribute('aria-expanded', 'false');
                sidebarToggle.setAttribute('aria-label', 'Expand navigation');
                overlay.classList.remove('active');
            }
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    var expanded = sidebar.classList.toggle('expanded');
                    main.classList.toggle('sidebar-expanded', expanded);
                    overlay.classList.toggle('active', expanded && window.innerWidth <= 768);
                    sidebarToggle.setAttribute('aria-expanded', String(expanded));
                    sidebarToggle.setAttribute('aria-label', expanded ? 'Collapse navigation' : 'Expand navigation');
                });
            }
            overlay.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && sidebar.classList.contains('expanded')) closeSidebar();
            });
        })();
    </script>
</body>
</html>
