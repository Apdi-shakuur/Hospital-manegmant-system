<?php
/**
 * Main Application Router Entry Point
 * Hospital Management System (HMS)
 */

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . get_user_dashboard_url());
} else {
    header('Location: auth/login.php');
}
exit;
