<?php
/**
 * System Reports & Financial Analytics Module
 * Hospital Management System (HMS)
 */

$pageTitle = 'System Reports';
$activeNav = 'reports';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role('Admin');

$db = Database::getInstance();

$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');

// 1. Patient Reports
$totalPatients = (int)$db->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$pStmt = $db->prepare("SELECT COUNT(*) FROM patients WHERE DATE(created_at) BETWEEN :s AND :e");
$pStmt->execute([':s' => $startDate, ':e' => $endDate]);
$newPatients = (int)$pStmt->fetchColumn();

// 2. Appointment Reports
$apptStmt = $db->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
        SUM(CASE WHEN status = 'No Show' THEN 1 ELSE 0 END) AS noshow
    FROM appointments 
    WHERE appointment_date BETWEEN :s AND :e
");
$apptStmt->execute([':s' => $startDate, ':e' => $endDate]);
$apptReport = $apptStmt->fetch();

// 3. Financial Reports
$revStmt = $db->prepare("
    SELECT 
        COALESCE(SUM(amount), 0) AS total_collected
    FROM payments 
    WHERE DATE(payment_date) BETWEEN :s AND :e
");
$revStmt->execute([':s' => $startDate, ':e' => $endDate]);
$revenueCollected = (float)$revStmt->fetchColumn();

$outstandingBalance = (float)$db->query("SELECT COALESCE(SUM(balance), 0) FROM invoices WHERE status IN ('Unpaid', 'Partially Paid')")->fetchColumn();

// 4. Pharmacy Inventory Valuation & Low Stock
$pharmacyValuation = (float)$db->query("SELECT COALESCE(SUM(quantity * unit_price), 0) FROM medicines WHERE status = 'Active'")->fetchColumn();
$lowStockCount = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE quantity <= reorder_level AND status = 'Active'")->fetchColumn();
$expiredCount  = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE expiry_date <= CURRENT_DATE() AND status = 'Active'")->fetchColumn();

// 5. Laboratory Reports
$labPending   = (int)$db->query("SELECT COUNT(*) FROM lab_requests WHERE status != 'Completed'")->fetchColumn();
$labDoneStmt  = $db->prepare("SELECT COUNT(*) FROM lab_requests WHERE status = 'Completed' AND DATE(created_at) BETWEEN :s AND :e");
$labDoneStmt->execute([':s' => $startDate, ':e' => $endDate]);
$labCompleted = (int)$labDoneStmt->fetchColumn();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Executive Hospital Reports</h1>
                <div class="page-subtitle">Comprehensive financial, patient, appointment, pharmacy, and lab analytics</div>
            </div>
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Executive Report</button>
        </div>

        <!-- Date Range Filter Form -->
        <div class="card">
            <form method="GET" action="index.php" style="display:flex; gap:1.25rem; flex-wrap:wrap; align-items:flex-end;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= escape($startDate) ?>">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= escape($endDate) ?>">
                </div>
                <button type="submit" class="btn btn-secondary">Apply Date Range</button>
            </form>
        </div>

        <!-- Printable Report Layout Container -->
        <div class="printable-area">
            <div style="margin-bottom:1.5rem; background:white; padding:1.25rem; border-radius:var(--radius-md); border:1px solid var(--border-color);">
                <h2 style="margin:0; font-size:1.25rem; color:var(--slate-900);">Hospital Performance Summary Statement</h2>
                <div style="color:var(--slate-500); font-size:0.875rem;">Report Period: <strong><?= format_date($startDate) ?></strong> to <strong><?= format_date($endDate) ?></strong></div>
            </div>

            <!-- Financial & Revenue Report Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">1. Financial & Revenue Report</div>
                </div>
                <div class="stats-grid" style="margin-bottom:0;">
                    <div class="stat-card">
                        <div class="stat-icon emerald">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= format_currency($revenueCollected) ?></div>
                            <div class="stat-label">Collections in Selected Period</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon rose">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= format_currency($outstandingBalance) ?></div>
                            <div class="stat-label">Total Outstanding Patient Balances</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Patient Demographics & Registration Report Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">2. Patient Registration Report</div>
                </div>
                <div class="stats-grid" style="margin-bottom:0;">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $totalPatients ?></div>
                            <div class="stat-label">Total System Patients</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon teal">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= $newPatients ?></div>
                            <div class="stat-label">New Registrations in Period</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Appointments & Pharmacy & Lab Grid -->
            <div class="dashboard-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">3. Appointments Summary</div>
                    </div>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:0.75rem;">
                        <li style="display:flex; justify-content:space-between; padding:0.5rem; background:var(--slate-50); border-radius:var(--radius-sm);">
                            <span>Total Scheduled:</span> <strong><?= $apptReport['total'] ?? 0 ?></strong>
                        </li>
                        <li style="display:flex; justify-content:space-between; padding:0.5rem; background:var(--slate-50); border-radius:var(--radius-sm);">
                            <span>Completed Consultations:</span> <strong style="color:var(--emerald-600);"><?= $apptReport['completed'] ?? 0 ?></strong>
                        </li>
                        <li style="display:flex; justify-content:space-between; padding:0.5rem; background:var(--slate-50); border-radius:var(--radius-sm);">
                            <span>Cancelled / No Shows:</span> <strong style="color:var(--rose-600);"><?= ($apptReport['cancelled'] ?? 0) + ($apptReport['noshow'] ?? 0) ?></strong>
                        </li>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">4. Pharmacy & Lab Summary</div>
                    </div>
                    <ul style="list-style:none; display:flex; flex-direction:column; gap:0.75rem;">
                        <li style="display:flex; justify-content:space-between; padding:0.5rem; background:var(--slate-50); border-radius:var(--radius-sm);">
                            <span>Total Inventory Value:</span> <strong><?= format_currency($pharmacyValuation) ?></strong>
                        </li>
                        <li style="display:flex; justify-content:space-between; padding:0.5rem; background:var(--slate-50); border-radius:var(--radius-sm);">
                            <span>Low Stock Medicines:</span> <strong style="color:var(--amber-500);"><?= $lowStockCount ?> Drugs</strong>
                        </li>
                        <li style="display:flex; justify-content:space-between; padding:0.5rem; background:var(--slate-50); border-radius:var(--radius-sm);">
                            <span>Expired Drugs:</span> <strong style="color:var(--rose-600);"><?= $expiredCount ?> Drugs</strong>
                        </li>
                        <li style="display:flex; justify-content:space-between; padding:0.5rem; background:var(--slate-50); border-radius:var(--radius-sm);">
                            <span>Pending Lab Requests:</span> <strong><?= $labPending ?> Orders</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
