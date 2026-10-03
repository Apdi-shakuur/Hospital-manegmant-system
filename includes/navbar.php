<?php
/**
 * Top Navbar Header Component
 * Hospital Management System (HMS)
 */
require_once __DIR__ . '/auth.php';

$user = current_user();
$role = $user['role_name'] ?? '';
$baseUrl = $baseUrl ?? '../';

// Fetch unread notifications for the user
$notifications = [];
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([':uid' => $user['id']]);
    $notifications = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Navbar Notification Error: " . $e->getMessage());
}

$unreadCount = count(array_filter($notifications, fn($n) => $n['is_read'] == 0));
?>
<header class="navbar">
    <style>
        .navbar {
            height: var(--navbar-height);
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            position: sticky;
            top: 0;
            z-index: 90;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--slate-600);
            cursor: pointer;
            padding: 0.25rem;
        }
        .mobile-toggle svg {
            width: 24px;
            height: 24px;
        }
        @media (max-width: 768px) {
            .mobile-toggle { display: block; }
            .navbar { padding: 0 1.25rem; }
        }
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .notif-wrapper {
            position: relative;
        }
        .notif-btn {
            background: var(--slate-100);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--slate-600);
            cursor: pointer;
            position: relative;
            transition: var(--transition);
        }
        .notif-btn:hover {
            background: var(--slate-200);
            color: var(--slate-900);
        }
        .notif-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: var(--rose-600);
            color: white;
            font-size: 0.65rem;
            font-weight: 800;
            width: 18px;
            height: 18px;
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }
        .notif-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 340px;
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-xl);
            display: none;
            z-index: 200;
        }
        .notif-dropdown.active {
            display: block;
        }
        .notif-header {
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .notif-list {
            max-height: 300px;
            overflow-y: auto;
            list-style: none;
        }
        .notif-item {
            padding: 0.875rem 1.25rem;
            border-bottom: 1px solid var(--slate-100);
            transition: var(--transition);
        }
        .notif-item:hover {
            background: var(--slate-50);
        }
        .notif-item-title {
            font-weight: 600;
            font-size: 0.825rem;
            color: var(--slate-900);
        }
        .notif-item-desc {
            font-size: 0.775rem;
            color: var(--slate-500);
            margin-top: 0.2rem;
        }
        .navbar-user-btn {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            text-decoration: none;
            color: var(--slate-800);
            font-weight: 600;
            font-size: 0.875rem;
        }
        .logout-link {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            color: var(--rose-600);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.4rem 0.75rem;
            border-radius: var(--radius-sm);
            background: #ffe4e6;
            transition: var(--transition);
        }
        .logout-link:hover {
            background: var(--rose-600);
            color: white;
        }
    </style>

    <div class="navbar-left">
        <button id="sidebar-toggle" class="mobile-toggle" aria-label="Toggle Sidebar Navigation">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="search-box">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" class="form-control" placeholder="Search patients, doctors, records...">
        </div>
    </div>

    <div class="navbar-right">
        <!-- Notification Dropdown -->
        <div class="notif-wrapper">
            <button id="notif-dropdown-btn" class="notif-btn" aria-label="Notifications">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </button>
            <div id="notif-dropdown-menu" class="notif-dropdown">
                <div class="notif-header">
                    <span>Notifications</span>
                    <span class="badge badge-info"><?= count($notifications) ?> Total</span>
                </div>
                <ul class="notif-list">
                    <?php if (empty($notifications)): ?>
                        <li class="notif-item" style="text-align: center; color: var(--slate-400);">No notifications</li>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <li class="notif-item">
                                <div class="notif-item-title"><?= escape($n['title']) ?></div>
                                <div class="notif-item-desc"><?= escape($n['message']) ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <a href="<?= $baseUrl ?>auth/logout.php" class="logout-link">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Logout
        </a>
    </div>
</header>
