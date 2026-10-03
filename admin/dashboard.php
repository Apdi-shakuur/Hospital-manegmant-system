<?php
/**
 * Master Admin & Hospital Staff Dashboard View
 * Hospital Management System (HMS)
 */

$pageTitle = 'Dashboard Overview';
$activeNav = 'dashboard';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Welcome back, <?= escape($user['full_name'] ?? $user['username']) ?>!</h1>
                <div class="page-subtitle">Hospital Operations & Performance Overview (<?= date('F j, Y') ?>)</div>
            </div>
            <div style="display:flex; gap:0.5rem;">
                <a href="../patients/index.php?action=add" class="btn btn-primary">+ New Patient</a>
                <a href="../appointments/index.php?action=book" class="btn btn-secondary">+ Book Appointment</a>
            </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-total-patients">--</div>
                    <div class="stat-label">Total Patients</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon teal">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-total-doctors">--</div>
                    <div class="stat-label">Total Doctors</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon emerald">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-total-nurses">--</div>
                    <div class="stat-label">Total Nurses</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon primary">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-today-appts">--</div>
                    <div class="stat-label">Today's Appts</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-pending-appts">--</div>
                    <div class="stat-label">Pending Appts</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon emerald">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-completed-appts">--</div>
                    <div class="stat-label">Completed Appts</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon teal">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-avail-meds">--</div>
                    <div class="stat-label">Available Meds</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon rose">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-low-stock-meds">--</div>
                    <div class="stat-label">Low Stock Meds</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon amber">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-pending-labs">--</div>
                    <div class="stat-label">Pending Lab Tests</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon emerald">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value" id="kpi-today-revenue">--</div>
                    <div class="stat-label">Today's Revenue</div>
                </div>
            </div>
        </div>

        <!-- Dashboard Layout Grid -->
        <div class="dashboard-grid">
            <!-- Left Column: Department Staffing Chart & Quick Actions -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Department Doctors Statistics</div>
                    </div>
                    <div id="dept-chart-container" class="chart-container">
                        <div style="color:var(--slate-400); text-align:center; width:100%;">Loading chart...</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Quick System Actions</div>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="../patients/index.php" class="quick-action-btn">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            Patients
                        </a>
                        <a href="../appointments/index.php" class="quick-action-btn">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Appointments
                        </a>
                        <a href="../pharmacy/index.php" class="quick-action-btn">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            Pharmacy
                        </a>
                        <a href="../laboratory/index.php" class="quick-action-btn">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                            Laboratory
                        </a>
                        <a href="../billing/index.php" class="quick-action-btn">
                            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            Billing
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right Column: Recent Activity Logs -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Activity Log</div>
                    </div>
                    <ul id="activity-feed-container" class="activity-list">
                        <li style="color:var(--slate-400); text-align:center;">Loading activity...</li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Dashboard Specific Script -->
    <script src="../assets/js/dashboard.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
