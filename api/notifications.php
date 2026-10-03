<?php
/**
 * In-App Notifications Management API
 * Hospital Management System (HMS)
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(false, 'Unauthorized.', [], 401);
}

$user = current_user();
$db = Database::getInstance();

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([':uid' => $user['id']]);
    $items = $stmt->fetchAll();
    json_response(true, 'Notifications fetched.', ['notifications' => $items]);
} elseif ($action === 'mark_read') {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
    $stmt->execute([':uid' => $user['id']]);
    json_response(true, 'All notifications marked as read.');
}
