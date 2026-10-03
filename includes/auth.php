<?php
/**
 * Authentication & Role-Based Access Control (RBAC)
 * Hospital Management System (HMS)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Ensure secure session configuration
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        session_start();
    }
}

start_secure_session();

/**
 * Check if a user is currently logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get currently authenticated user data
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'email'     => $_SESSION['email'],
        'role_id'   => $_SESSION['role_id'],
        'role_name' => $_SESSION['role_name'],
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'],
    ];
}

/**
 * Log in a user and set up session
 */
function login_user(array $user, string $roleName, ?string $fullName = null): void {
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role_id']   = $user['role_id'];
    $_SESSION['role_name'] = $roleName;
    $_SESSION['full_name'] = $fullName ?? $user['username'];
    $_SESSION['last_activity'] = time();

    // Update last_login timestamp in database
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);
        
        log_activity($db, $user['id'], 'User Login', "User '{$user['username']}' logged in successfully.");
    } catch (Exception $e) {
        error_log("Failed to update last login: " . $e->getMessage());
    }
}

/**
 * Log out current user
 */
function logout_user(): void {
    if (is_logged_in()) {
        try {
            $db = Database::getInstance();
            log_activity($db, $_SESSION['user_id'], 'User Logout', "User '{$_SESSION['username']}' logged out.");
        } catch (Exception $e) {
            error_log("Logout log error: " . $e->getMessage());
        }
    }

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Guard page: Require user to be authenticated
 */
function require_login(string $redirectUrl = '../auth/login.php'): void {
    if (!is_logged_in()) {
        header("Location: " . $redirectUrl);
        exit;
    }

    // Session timeout check (30 minutes of inactivity)
    $timeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        logout_user();
        header("Location: " . $redirectUrl . "?expired=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Guard page: Require user to have specific role(s)
 * @param string|array $allowedRoles Single role name or array of role names
 */
function require_role(string|array $allowedRoles, string $redirectUrl = '../auth/login.php'): void {
    require_login($redirectUrl);

    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    $userRole = $_SESSION['role_name'] ?? '';

    // Admin has access to all pages
    if ($userRole === 'Admin') {
        return;
    }

    if (!in_array($userRole, $roles, true)) {
        http_response_code(403);
        die("
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>403 Access Denied - HMS</title>
            <style>
                body { font-family: system-ui, -apple-system, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
                .card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); text-align: center; max-width: 450px; }
                h1 { color: #e11d48; margin-top: 0; font-size: 2rem; }
                p { color: #64748b; font-size: 1rem; line-height: 1.5; }
                a { display: inline-block; margin-top: 1.5rem; background: #0284c7; color: white; padding: 0.75rem 1.5rem; text-decoration: none; border-radius: 6px; font-weight: 600; }
                a:hover { background: #0369a1; }
            </style>
        </head>
        <body>
            <div class='card'>
                <h1>403 Access Denied</h1>
                <p>You do not have permission to view this page. Your role (<strong>" . escape($userRole) . "</strong>) is restricted from accessing this module.</p>
                <a href='" . get_user_dashboard_url() . "'>Return to Dashboard</a>
            </div>
        </body>
        </html>
        ");
    }
}

/**
 * Check if current user has a specific role
 */
function has_role(string|array $roles): bool {
    if (!is_logged_in()) return false;
    $roleList = is_array($roles) ? $roles : [$roles];
    $userRole = $_SESSION['role_name'] ?? '';
    return $userRole === 'Admin' || in_array($userRole, $roleList, true);
}

/**
 * Get relative URL for current user role dashboard
 */
function get_user_dashboard_url(): string {
    if (!is_logged_in()) {
        return 'auth/login.php';
    }

    $role = $_SESSION['role_name'] ?? '';
    return match ($role) {
        'Admin'        => 'admin/dashboard.php',
        'Doctor'       => 'admin/dashboard.php?view=doctor',
        'Nurse'        => 'admin/dashboard.php?view=nurse',
        'Receptionist' => 'admin/dashboard.php?view=receptionist',
        'Pharmacist'   => 'pharmacy/index.php',
        'Laboratory Staff' => 'laboratory/index.php',
        'Patient'      => 'patients/profile.php',
        default        => 'index.php',
    };
}
