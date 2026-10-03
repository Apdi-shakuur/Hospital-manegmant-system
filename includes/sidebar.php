<?php
/**
 * Role-Aware Navigation Sidebar
 * Hospital Management System (HMS)
 */
require_once __DIR__ . '/auth.php';

$user = current_user();
$role = $user['role_name'] ?? '';
$activeNav = $activeNav ?? 'dashboard';
$baseUrl = $baseUrl ?? '../';
?>
<aside id="sidebar" class="sidebar">
    <style>
        .sidebar {
            width: var(--sidebar-width);
            background: var(--slate-900);
            color: #ffffff;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: var(--transition);
        }
        .sidebar-brand {
            padding: 1.5rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-bottom: 1px solid var(--slate-800);
        }
        .brand-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, var(--primary-500), var(--teal-500));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }
        .brand-text {
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.02em;
        }
        .brand-subtitle {
            font-size: 0.7rem;
            color: var(--slate-400);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .sidebar-menu {
            flex: 1;
            padding: 1.25rem 0.75rem;
            overflow-y: auto;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .menu-label {
            padding: 0.75rem 0.75rem 0.35rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--slate-400);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .menu-item a {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.7rem 0.875rem;
            color: var(--slate-300);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }
        .menu-item a:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #ffffff;
        }
        .menu-item.active a {
            background: var(--primary-600);
            color: #ffffff;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }
        .menu-item svg {
            width: 20px;
            height: 20px;
            stroke-width: 2;
        }
        .sidebar-user {
            padding: 1rem 1.25rem;
            background: rgba(15, 23, 42, 0.6);
            border-top: 1px solid var(--slate-800);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-full);
            background: var(--slate-700);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: var(--primary-500);
        }
        .user-info-text {
            overflow: hidden;
        }
        .user-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #ffffff;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }
        .user-role-badge {
            font-size: 0.7rem;
            color: var(--slate-400);
        }
    </style>

    <div class="sidebar-brand">
        <div class="brand-icon">+</div>
        <div>
            <div class="brand-text">ModernCare</div>
            <div class="brand-subtitle">Hospital System</div>
        </div>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-label">Main Menu</li>
        
        <li class="menu-item <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
            <a href="<?= $baseUrl . get_user_dashboard_url() ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
        </li>

        <?php if (has_role(['Admin', 'Receptionist', 'Doctor', 'Nurse'])): ?>
        <li class="menu-item <?= $activeNav === 'patients' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>patients/index.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Patients
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role(['Admin', 'Doctor', 'Receptionist', 'Nurse', 'Patient'])): ?>
        <li class="menu-item <?= $activeNav === 'appointments' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>appointments/index.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Appointments
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role(['Admin', 'Doctor', 'Nurse', 'Patient'])): ?>
        <li class="menu-item <?= $activeNav === 'medical-records' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>medical-records/index.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Medical Records
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role(['Admin', 'Doctor', 'Pharmacist', 'Patient'])): ?>
        <li class="menu-item <?= $activeNav === 'prescriptions' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>prescriptions/index.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                Prescriptions
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role(['Admin', 'Pharmacist'])): ?>
        <li class="menu-item <?= $activeNav === 'pharmacy' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>pharmacy/index.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Pharmacy Stock
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role(['Admin', 'Doctor', 'Laboratory Staff', 'Patient'])): ?>
        <li class="menu-item <?= $activeNav === 'laboratory' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>laboratory/index.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                Laboratory
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role(['Admin', 'Receptionist', 'Patient'])): ?>
        <li class="menu-item <?= $activeNav === 'billing' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>billing/index.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Billing & Invoices
            </a>
        </li>
        <?php endif; ?>

        <?php if (has_role('Admin')): ?>
        <li class="menu-label">Administration</li>
        <li class="menu-item <?= $activeNav === 'users' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>admin/users.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                User Accounts
            </a>
        </li>
        <li class="menu-item <?= $activeNav === 'doctors' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>admin/doctors.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Doctors Directory
            </a>
        </li>
        <li class="menu-item <?= $activeNav === 'departments' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>admin/departments.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                Departments
            </a>
        </li>
        <li class="menu-item <?= $activeNav === 'reports' ? 'active' : '' ?>">
            <a href="<?= $baseUrl ?>reports/index.php">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                System Reports
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?></div>
        <div class="user-info-text">
            <div class="user-name"><?= escape($user['full_name'] ?? $user['username']) ?></div>
            <div class="user-role-badge"><?= escape($role) ?></div>
        </div>
    </div>
</aside>
