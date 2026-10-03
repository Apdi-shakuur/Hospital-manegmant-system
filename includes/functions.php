<?php
/**
 * Global Helper & Security Functions
 * Hospital Management System (HMS)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escape HTML output to prevent XSS attacks
 */
function escape(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize string input
 */
function sanitize(mixed $data): string {
    if (is_array($data)) {
        return '';
    }
    return trim((string)$data);
}

/**
 * Generate CSRF Token for forms and session
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input field
 */
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . escape($token) . '">';
}

/**
 * Verify submitted CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Standardized JSON API Response Helper
 */
function json_response(bool $success, string $message = '', array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Record action into activity_logs database table
 */
function log_activity(PDO $db, ?int $userId, string $action, string $description): bool {
    try {
        $ip = get_client_ip();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (:user_id, :action, :description, :ip)");
        return $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => $action,
            ':description' => $description,
            ':ip'          => $ip
        ]);
    } catch (PDOException $e) {
        error_log("Failed to insert activity log: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification to a target user
 */
function add_notification(PDO $db, int $userId, string $title, string $message, string $type = 'Info', ?string $link = null): bool {
    try {
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (:user_id, :title, :message, :type, :link)");
        return $stmt->execute([
            ':user_id' => $userId,
            ':title'   => $title,
            ':message' => $message,
            ':type'    => $type,
            ':link'    => $link
        ]);
    } catch (PDOException $e) {
        error_log("Failed to insert notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get client IP address
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Format currency string
 */
function format_currency(float $amount, string $symbol = '$'): string {
    return $symbol . number_format($amount, 2);
}

/**
 * Format Date string
 */
function format_date(?string $dateStr, string $format = 'M d, Y'): string {
    if (empty($dateStr)) return 'N/A';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/**
 * Format DateTime string
 */
function format_datetime(?string $dateStr, string $format = 'M d, Y - h:i A'): string {
    if (empty($dateStr)) return 'N/A';
    $timestamp = strtotime($dateStr);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/**
 * Calculate BMI from Weight (kg) and Height (cm)
 */
function calculate_bmi(float $weightKg, float $heightCm): float {
    if ($heightCm <= 0) return 0.0;
    $heightMeters = $heightCm / 100.0;
    return round($weightKg / ($heightMeters * $heightMeters), 1);
}

/**
 * Generate formatted unique code (e.g., PAT-2026-8812)
 */
function generate_code(string $prefix): string {
    return strtoupper($prefix) . '-' . date('Y') . '-' . sprintf('%04d', rand(1, 9999));
}
