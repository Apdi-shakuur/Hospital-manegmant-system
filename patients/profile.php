<?php
/**
 * 360-Degree Comprehensive Patient Profile View
 * Hospital Management System (HMS)
 */

$pageTitle = 'Patient Profile';
$activeNav = 'patients';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Receptionist', 'Doctor', 'Nurse', 'Patient']);

$db = Database::getInstance();
$user = current_user();

$patientId = (int)($_GET['id'] ?? 0);

// If role is Patient, restrict to viewing their own linked record
if ($user['role_name'] === 'Patient') {
    $pStmt = $db->prepare("SELECT id FROM patients WHERE user_id = :uid LIMIT 1");
    $pStmt->execute([':uid' => $user['id']]);
    $patientId = (int)$pStmt->fetchColumn();
}

if ($patientId <= 0) {
    die("<div class='content-body'><div class='card'><h2>Invalid or Unspecified Patient Record.</h2></div></div>");
}

// Fetch Patient Details
$pStmt = $db->prepare("SELECT * FROM patients WHERE id = :id");
$pStmt->execute([':id' => $patientId]);
$patient = $pStmt->fetch();

if (!$patient) {
    die("<div class='content-body'><div class='card'><h2>Patient Record Not Found.</h2></div></div>");
}

// Calculate age
$dob = new DateTime($patient['date_of_birth']);
$now = new DateTime();
$age = $now->diff($dob)->y;

// Fetch Appointments History
$appts = $db->prepare("
    SELECT a.*, d.first_name AS doc_fn, d.last_name AS doc_ln, dep.name AS dept_name 
    FROM appointments a 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN departments dep ON a.department_id = dep.id 
    WHERE a.patient_id = :pid 
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$appts->execute([':pid' => $patientId]);
$appointments = $appts->fetchAll();

// Fetch Latest Vital Signs
$vitalsStmt = $db->prepare("SELECT * FROM vital_signs WHERE patient_id = :pid ORDER BY recorded_at DESC LIMIT 5");
$vitalsStmt->execute([':pid' => $patientId]);
$vitals = $vitalsStmt->fetchAll();

// Fetch Medical Records / Consultations
$recordsStmt = $db->prepare("
    SELECT mr.*, d.first_name AS doc_fn, d.last_name AS doc_ln 
    FROM medical_records mr 
    JOIN doctors d ON mr.doctor_id = d.id 
    WHERE mr.patient_id = :pid 
    ORDER BY mr.visit_date DESC
");
$recordsStmt->execute([':pid' => $patientId]);
$medicalRecords = $recordsStmt->fetchAll();

// Fetch Prescriptions
$rxStmt = $db->prepare("
    SELECT pr.*, d.first_name AS doc_fn, d.last_name AS doc_ln 
    FROM prescriptions pr 
    JOIN doctors d ON pr.doctor_id = d.id 
    WHERE pr.patient_id = :pid 
    ORDER BY pr.prescription_date DESC
");
$rxStmt->execute([':pid' => $patientId]);
$prescriptions = $rxStmt->fetchAll();

// Fetch Lab Requests & Results
$labStmt = $db->prepare("
    SELECT lr.*, d.first_name AS doc_fn, d.last_name AS doc_ln 
    FROM lab_requests lr 
    JOIN doctors d ON lr.doctor_id = d.id 
    WHERE lr.patient_id = :pid 
    ORDER BY lr.request_date DESC
");
$labStmt->execute([':pid' => $patientId]);
$labRequests = $labStmt->fetchAll();

// Fetch Invoices & Billing
$invStmt = $db->prepare("SELECT * FROM invoices WHERE patient_id = :pid ORDER BY invoice_date DESC");
$invStmt->execute([':pid' => $patientId]);
$invoices = $invStmt->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <!-- Patient Profile Header Card -->
        <div class="card" style="background: linear-gradient(135deg, #ffffff 0%, var(--primary-50) 100%); border-left: 5px solid var(--primary-600);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.5rem;">
                <div>
                    <div style="display:flex; align-items:center; gap:0.75rem;">
                        <h1 class="page-title" style="margin:0;"><?= escape($patient['first_name'] . ' ' . $patient['last_name']) ?></h1>
                        <span class="badge badge-info" style="font-size:0.85rem;"><?= escape($patient['patient_number']) ?></span>
                    </div>
                    <div style="color:var(--slate-600); font-size:0.9rem; margin-top:0.35rem;">
                        <strong><?= escape($patient['gender']) ?></strong>, <?= $age ?> Years Old (DOB: <?= format_date($patient['date_of_birth']) ?>)
                    </div>
                </div>

                <div style="display:flex; gap:1rem;">
                    <?php if (has_role(['Admin', 'Doctor', 'Nurse'])): ?>
                        <a href="../medical-records/index.php?patient_id=<?= $patient['id'] ?>" class="btn btn-primary">+ New Consultation</a>
                    <?php endif; ?>
                    <?php if (has_role(['Admin', 'Receptionist'])): ?>
                        <a href="../appointments/index.php?patient_id=<?= $patient['id'] ?>" class="btn btn-secondary">+ Book Appointment</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Essential Info Strip -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid var(--slate-200);">
                <div>
                    <div style="font-size:0.75rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Blood Group</div>
                    <div style="font-weight:800; font-size:1.1rem; color:var(--rose-600);"><?= escape($patient['blood_group']) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Contact Phone</div>
                    <div style="font-weight:600; color:var(--slate-800);"><?= escape($patient['phone']) ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Email Address</div>
                    <div style="font-weight:600; color:var(--slate-800);"><?= escape($patient['email'] ?: 'N/A') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Known Allergies</div>
                    <div style="font-weight:700; color:var(--rose-600);"><?= escape($patient['allergies'] ?: 'None') ?></div>
                </div>
                <div>
                    <div style="font-size:0.75rem; color:var(--slate-500); text-transform:uppercase; font-weight:700;">Emergency Contact</div>
                    <div style="font-weight:600; color:var(--slate-800);"><?= escape($patient['emergency_contact'] ?: 'N/A') ?></div>
                </div>
            </div>
        </div>

        <!-- Vitals & Recent Consultations Section -->
        <div class="dashboard-grid">
            <!-- Left: Consultations & Medical History -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Consultation & Diagnoses History</div>
                    </div>
                    <?php if (empty($medicalRecords)): ?>
                        <div style="color:var(--slate-400); text-align:center; padding:1.5rem;">No medical records recorded yet.</div>
                    <?php else: ?>
                        <?php foreach ($medicalRecords as $mr): ?>
                            <div style="border-bottom: 1px solid var(--slate-100); padding-bottom: 1rem; margin-bottom: 1rem;">
                                <div style="display:flex; justify-content:space-between; font-weight:700;">
                                    <span style="color:var(--primary-600);"><?= escape($mr['record_number']) ?></span>
                                    <span style="color:var(--slate-400); font-size:0.8rem;"><?= format_datetime($mr['visit_date']) ?></span>
                                </div>
                                <div style="font-weight:600; margin-top:0.35rem;">Attending: Dr. <?= escape($mr['doc_fn'] . ' ' . $mr['doc_ln']) ?></div>
                                <div style="margin-top:0.4rem; font-size:0.9rem;"><strong>Diagnosis:</strong> <?= escape($mr['diagnosis']) ?></div>
                                <div style="margin-top:0.2rem; font-size:0.85rem; color:var(--slate-600);"><strong>Chief Complaint:</strong> <?= escape($mr['chief_complaint']) ?></div>
                                <div style="margin-top:0.2rem; font-size:0.85rem; color:var(--slate-600);"><strong>Treatment Plan:</strong> <?= escape($mr['treatment_plan']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Prescriptions History</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rx #</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($prescriptions)): ?>
                                    <tr><td colspan="4" style="text-align:center; color:var(--slate-400);">No prescriptions issued.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($prescriptions as $rx): ?>
                                        <tr>
                                            <td><strong><?= escape($rx['prescription_number']) ?></strong></td>
                                            <td>Dr. <?= escape($rx['doc_fn'] . ' ' . $rx['doc_ln']) ?></td>
                                            <td><?= format_date($rx['prescription_date']) ?></td>
                                            <td><span class="badge badge-<?= strtolower($rx['status']) ?>"><?= escape($rx['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right: Vital Signs & Lab Results & Invoices -->
            <div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Latest Vital Signs</div>
                    </div>
                    <?php if (empty($vitals)): ?>
                        <div style="color:var(--slate-400); text-align:center; padding:1rem;">No vital signs recorded.</div>
                    <?php else: ?>
                        <?php $v = $vitals[0]; ?>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.75rem;">
                            <div style="background:var(--slate-50); padding:0.75rem; border-radius:var(--radius-sm);">
                                <div style="font-size:0.7rem; color:var(--slate-400); font-weight:700;">TEMP</div>
                                <div style="font-weight:800; font-size:1.1rem; color:var(--slate-800);"><?= $v['temperature'] ?> °C</div>
                            </div>
                            <div style="background:var(--slate-50); padding:0.75rem; border-radius:var(--radius-sm);">
                                <div style="font-size:0.7rem; color:var(--slate-400); font-weight:700;">BLOOD PRESSURE</div>
                                <div style="font-weight:800; font-size:1.1rem; color:var(--slate-800);"><?= escape($v['blood_pressure']) ?></div>
                            </div>
                            <div style="background:var(--slate-50); padding:0.75rem; border-radius:var(--radius-sm);">
                                <div style="font-size:0.7rem; color:var(--slate-400); font-weight:700;">HEART RATE</div>
                                <div style="font-weight:800; font-size:1.1rem; color:var(--slate-800);"><?= $v['heart_rate'] ?> bpm</div>
                            </div>
                            <div style="background:var(--slate-50); padding:0.75rem; border-radius:var(--radius-sm);">
                                <div style="font-size:0.7rem; color:var(--slate-400); font-weight:700;">O2 SATURATION</div>
                                <div style="font-weight:800; font-size:1.1rem; color:var(--emerald-600);"><?= $v['oxygen_saturation'] ?>%</div>
                            </div>
                            <div style="background:var(--slate-50); padding:0.75rem; border-radius:var(--radius-sm);">
                                <div style="font-size:0.7rem; color:var(--slate-400); font-weight:700;">WEIGHT / HEIGHT</div>
                                <div style="font-weight:700; font-size:0.95rem;"><?= $v['weight_kg'] ?> kg / <?= $v['height_cm'] ?> cm</div>
                            </div>
                            <div style="background:var(--slate-50); padding:0.75rem; border-radius:var(--radius-sm);">
                                <div style="font-size:0.7rem; color:var(--slate-400); font-weight:700;">CALCULATED BMI</div>
                                <div style="font-weight:800; font-size:1.1rem; color:var(--primary-600);"><?= $v['bmi'] ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Laboratory Orders</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Req #</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($labRequests)): ?>
                                    <tr><td colspan="3" style="text-align:center; color:var(--slate-400);">No lab tests requested.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($labRequests as $lr): ?>
                                        <tr>
                                            <td><strong><?= escape($lr['request_number']) ?></strong></td>
                                            <td><span class="badge badge-info"><?= escape($lr['priority']) ?></span></td>
                                            <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $lr['status'])) ?>"><?= escape($lr['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Invoices & Payment History</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Total</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($invoices)): ?>
                                    <tr><td colspan="4" style="text-align:center; color:var(--slate-400);">No invoices recorded.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($invoices as $inv): ?>
                                        <tr>
                                            <td><a href="../billing/view.php?id=<?= $inv['id'] ?>" style="color:var(--primary-600); font-weight:700; text-decoration:none;"><?= escape($inv['invoice_number']) ?></a></td>
                                            <td><?= format_currency($inv['total_amount']) ?></td>
                                            <td style="color:<?= $inv['balance'] > 0 ? 'var(--rose-600)' : 'var(--emerald-600)' ?>; font-weight:700;"><?= format_currency($inv['balance']) ?></td>
                                            <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $inv['status'])) ?>"><?= escape($inv['status']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
