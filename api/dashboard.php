<?php
/**
 * Dashboard Real-Time Metrics & Charts API
 * Hospital Management System (HMS)
 */

require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(false, 'Unauthorized access.', [], 401);
}

try {
    $db = Database::getInstance();

    // 1. KPI Counts
    $patientsCount    = (int)$db->query("SELECT COUNT(*) FROM patients WHERE status = 'Active'")->fetchColumn();
    $doctorsCount     = (int)$db->query("SELECT COUNT(*) FROM doctors WHERE status = 'Active'")->fetchColumn();
    $nursesCount      = (int)$db->query("SELECT COUNT(*) FROM nurses WHERE status = 'Active'")->fetchColumn();
    
    $todayAppts       = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURRENT_DATE()")->fetchColumn();
    $pendingAppts     = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status IN ('Scheduled', 'Confirmed')")->fetchColumn();
    $completedAppts   = (int)$db->query("SELECT COUNT(*) FROM appointments WHERE status = 'Completed'")->fetchColumn();
    
    $availMedicines   = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE status = 'Active' AND quantity > 0")->fetchColumn();
    $lowStockMeds     = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE quantity <= reorder_level AND status = 'Active'")->fetchColumn();
    
    $pendingLabTests  = (int)$db->query("SELECT COUNT(*) FROM lab_requests WHERE status IN ('Requested', 'Sample Collected', 'Processing')")->fetchColumn();
    
    $todayRevenue     = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = CURRENT_DATE()")->fetchColumn();
    $totalRevenue     = (float)$db->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();

    // 2. Department Breakdown
    $deptStats = $db->query("
        SELECT d.name, COUNT(doc.id) as doctor_count 
        FROM departments d 
        LEFT JOIN doctors doc ON d.id = doc.department_id AND doc.status = 'Active'
        GROUP BY d.id
        LIMIT 6
    ")->fetchAll();

    // 3. Appointment Status Breakdown
    $apptStatusStats = $db->query("
        SELECT status, COUNT(*) as count 
        FROM appointments 
        GROUP BY status
    ")->fetchAll();

    // 4. Recent Activity Log
    $recentActivity = $db->query("
        SELECT a.*, u.username 
        FROM activity_logs a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.created_at DESC 
        LIMIT 5
    ")->fetchAll();

    json_response(true, 'Metrics fetched successfully.', [
        'kpi' => [
            'total_patients'     => $patientsCount,
            'total_doctors'      => $doctorsCount,
            'total_nurses'       => $nursesCount,
            'today_appointments' => $todayAppts,
            'pending_appts'      => $pendingAppts,
            'completed_appts'    => $completedAppts,
            'available_medicines'=> $availMedicines,
            'low_stock_medicines'=> $lowStockMeds,
            'pending_lab_tests'  => $pendingLabTests,
            'today_revenue'      => $todayRevenue,
            'total_revenue'      => $totalRevenue,
        ],
        'department_stats'  => $deptStats,
        'appointment_stats' => $apptStatusStats,
        'recent_activity'   => $recentActivity
    ]);

} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    json_response(false, 'Database error while fetching statistics.', [], 500);
}
