<?php
/**
 * Appointment Scheduling & Workflow Management
 * Hospital Management System (HMS)
 */

$pageTitle = 'Appointments Management';
$activeNav = 'appointments';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Doctor', 'Receptionist', 'Nurse', 'Patient']);

$db = Database::getInstance();
$user = current_user();
$message = '';
$messageType = 'success';

// Handle Booking & Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'book') {
        $patientId = (int)($_POST['patient_id'] ?? 0);
        $doctorId  = (int)($_POST['doctor_id'] ?? 0);
        $apptDate  = $_POST['appointment_date'] ?? '';
        $apptTime  = $_POST['appointment_time'] ?? '';
        $reason    = sanitize($_POST['reason'] ?? '');
        $notes     = sanitize($_POST['notes'] ?? '');

        // If logged-in user is Patient, enforce their patient_id
        if ($user['role_name'] === 'Patient') {
            $pStmt = $db->prepare("SELECT id FROM patients WHERE user_id = :uid LIMIT 1");
            $pStmt->execute([':uid' => $user['id']]);
            $patientId = (int)$pStmt->fetchColumn();
        }

        if ($patientId <= 0 || $doctorId <= 0 || empty($apptDate) || empty($apptTime) || empty($reason)) {
            $message = 'Patient, Doctor, Date, Time, and Reason are required.';
            $messageType = 'danger';
        } else {
            // Check for Doctor Double-Booking Conflict!
            $checkStmt = $db->prepare("
                SELECT id FROM appointments 
                WHERE doctor_id = :doc_id 
                AND appointment_date = :adate 
                AND appointment_time = :atime 
                AND status NOT IN ('Cancelled', 'No Show') 
                LIMIT 1
            ");
            $checkStmt->execute([
                ':doc_id' => $doctorId,
                ':adate'  => $apptDate,
                ':atime'  => $apptTime
            ]);

            if ($checkStmt->fetch()) {
                $message = 'Doctor is already booked for an appointment at this exact date and time. Please select another time slot.';
                $messageType = 'danger';
            } else {
                try {
                    // Fetch doctor's department_id
                    $dStmt = $db->prepare("SELECT department_id FROM doctors WHERE id = :did");
                    $dStmt->execute([':did' => $doctorId]);
                    $deptId = (int)$dStmt->fetchColumn();

                    $apptNum = generate_code('APT');

                    $insStmt = $db->prepare("
                        INSERT INTO appointments (appointment_number, patient_id, doctor_id, department_id, appointment_date, appointment_time, reason, status, notes, created_by) 
                        VALUES (:num, :pid, :did, :dept_id, :adate, :atime, :reason, 'Scheduled', :notes, :created_by)
                    ");
                    $insStmt->execute([
                        ':num'        => $apptNum,
                        ':pid'        => $patientId,
                        ':did'        => $doctorId,
                        ':dept_id'    => $deptId,
                        ':adate'      => $apptDate,
                        ':atime'      => $apptTime,
                        ':reason'     => $reason,
                        ':notes'      => $notes,
                        ':created_by' => $user['id']
                    ]);

                    log_activity($db, $user['id'], 'Appointment Booked', "Booked appointment {$apptNum} for Patient ID {$patientId} with Doctor ID {$doctorId}.");
                    $message = "Appointment {$apptNum} successfully scheduled.";
                } catch (Exception $e) {
                    error_log("Booking Error: " . $e->getMessage());
                    $message = 'Error scheduling appointment.';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($action === 'update_status') {
        $apptId    = (int)($_POST['appointment_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'Scheduled';

        $stmt = $db->prepare("UPDATE appointments SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $apptId]);
        $message = "Appointment status updated to {$newStatus}.";
    }
}

// Fetch Appointments List
$statusFilter = $_GET['status'] ?? '';
$dateFilter   = $_GET['date'] ?? '';

$sql = "
    SELECT a.*, 
           p.first_name AS pat_fn, p.last_name AS pat_ln, p.patient_number, p.phone AS pat_phone,
           d.first_name AS doc_fn, d.last_name AS doc_ln,
           dep.name AS department_name 
    FROM appointments a 
    JOIN patients p ON a.patient_id = p.id 
    JOIN doctors d ON a.doctor_id = d.id 
    JOIN departments dep ON a.department_id = dep.id 
    WHERE 1=1
";
$params = [];

// Role-specific scopes
if ($user['role_name'] === 'Doctor') {
    $sql .= " AND d.user_id = :uid";
    $params[':uid'] = $user['id'];
} elseif ($user['role_name'] === 'Patient') {
    $sql .= " AND p.user_id = :uid";
    $params[':uid'] = $user['id'];
}

if (!empty($statusFilter)) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $statusFilter;
}

if (!empty($dateFilter)) {
    $sql .= " AND a.appointment_date = :adate";
    $params[':adate'] = $dateFilter;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Supporting Lookups
$patients = $db->query("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();
$doctors  = $db->query("SELECT id, doctor_number, first_name, last_name, specialization FROM doctors WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Appointment Management</h1>
                <div class="page-subtitle">Schedule patient consultations, view doctor agendas, and track statuses</div>
            </div>
            <button class="btn btn-primary" onclick="openModal('book-modal')">+ Book New Appointment</button>
        </div>

        <?php if ($message): ?>
            <div class="badge badge-<?= $messageType ?>" style="display:block; padding:0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom:1.5rem;">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <!-- Filters Form -->
        <div class="card">
            <form method="GET" action="index.php" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
                <div style="width:180px;">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="Scheduled" <?= $statusFilter === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="Confirmed" <?= $statusFilter === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="Completed" <?= $statusFilter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="No Show" <?= $statusFilter === 'No Show' ? 'selected' : '' ?>>No Show</option>
                    </select>
                </div>

                <div style="width:180px;">
                    <input type="date" name="date" class="form-control" value="<?= escape($dateFilter) ?>" onchange="this.form.submit()">
                </div>

                <?php if ($statusFilter || $dateFilter): ?>
                    <a href="index.php" class="btn btn-secondary" style="color:var(--rose-600);">Reset Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Appointments Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Appt #</th>
                            <th>Patient</th>
                            <th>Doctor & Dept</th>
                            <th>Date & Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($appointments)): ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--slate-400); padding:2rem;">No appointments found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($appointments as $apt): ?>
                                <tr>
                                    <td><strong style="color:var(--primary-600);"><?= escape($apt['appointment_number']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($apt['pat_fn'] . ' ' . $apt['pat_ln']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($apt['patient_number']) ?> | <?= escape($apt['pat_phone']) ?></div>
                                    </td>
                                    <td>
                                        <div>Dr. <?= escape($apt['doc_fn'] . ' ' . $apt['doc_ln']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($apt['department_name']) ?></div>
                                    </td>
                                    <td>
                                        <strong><?= format_date($apt['appointment_date']) ?></strong>
                                        <div style="font-size:0.8rem; color:var(--slate-600);"><?= date('h:i A', strtotime($apt['appointment_time'])) ?></div>
                                    </td>
                                    <td><span style="font-size:0.85rem; color:var(--slate-700);"><?= escape($apt['reason']) ?></span></td>
                                    <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $apt['status'])) ?>"><?= escape($apt['status']) ?></span></td>
                                    <td>
                                        <?php if (has_role(['Admin', 'Receptionist', 'Doctor'])): ?>
                                            <form method="POST" action="index.php" style="display:inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                <select name="status" class="form-select" onchange="this.form.submit()" style="padding:0.2rem 0.5rem; font-size:0.75rem; width:auto; display:inline-block;">
                                                    <option value="Scheduled" <?= $apt['status'] === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                                    <option value="Confirmed" <?= $apt['status'] === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                    <option value="Completed" <?= $apt['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                    <option value="Cancelled" <?= $apt['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    <option value="No Show" <?= $apt['status'] === 'No Show' ? 'selected' : '' ?>>No Show</option>
                                                </select>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<!-- Book Appointment Modal -->
<div id="book-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Book Patient Appointment</div>
            <button class="modal-close" onclick="closeModal('book-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="book">
            <div class="modal-body">
                <?php if ($user['role_name'] !== 'Patient'): ?>
                    <div class="form-group">
                        <label class="form-label">Select Patient</label>
                        <select name="patient_id" class="form-select" required>
                            <option value="">-- Choose Patient --</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= (($_GET['patient_id'] ?? 0) == $p['id']) ? 'selected' : '' ?>>
                                    <?= escape($p['patient_number']) ?> - <?= escape($p['first_name'] . ' ' . $p['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Select Doctor & Specialization</label>
                    <select name="doctor_id" class="form-select" required>
                        <option value="">-- Choose Doctor --</option>
                        <?php foreach ($doctors as $d): ?>
                            <option value="<?= $d['id'] ?>">Dr. <?= escape($d['first_name'] . ' ' . $d['last_name']) ?> (<?= escape($d['specialization']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Appointment Date</label>
                        <input type="date" name="appointment_date" class="form-control" value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time Slot</label>
                        <select name="appointment_time" class="form-select" required>
                            <option value="09:00:00">09:00 AM</option>
                            <option value="10:00:00">10:00 AM</option>
                            <option value="11:00:00">11:00 AM</option>
                            <option value="11:30:00">11:30 AM</option>
                            <option value="14:00:00">02:00 PM</option>
                            <option value="15:00:00">03:00 PM</option>
                            <option value="16:00:00">04:00 PM</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason for Visit</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Fever, chest pain, annual checkup..." required>
                </div>

                <div class="form-group">
                    <label class="form-label">Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Special requests or symptoms..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('book-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm Appointment</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
