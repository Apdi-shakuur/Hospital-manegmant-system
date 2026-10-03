<?php
/**
 * Medical Records & Vital Signs Management
 * Hospital Management System (HMS)
 */

$pageTitle = 'Medical Records & Consultations';
$activeNav = 'medical-records';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Doctor', 'Nurse', 'Patient']);

$db = Database::getInstance();
$user = current_user();
$message = '';
$messageType = 'success';

// Handle Add Consultation Record & Vitals
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'create_record') {
        $patientId  = (int)($_POST['patient_id'] ?? 0);
        $complaint  = sanitize($_POST['chief_complaint'] ?? '');
        $symptoms   = sanitize($_POST['symptoms'] ?? '');
        $diagnosis  = sanitize($_POST['diagnosis'] ?? '');
        $treatment  = sanitize($_POST['treatment_plan'] ?? '');
        $notes      = sanitize($_POST['notes'] ?? '');
        $followUp   = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;

        // Vitals
        $temp       = (float)($_POST['temperature'] ?? 37.0);
        $bp         = sanitize($_POST['blood_pressure'] ?? '120/80');
        $hr         = (int)($_POST['heart_rate'] ?? 72);
        $rr         = (int)($_POST['respiratory_rate'] ?? 16);
        $o2         = (int)($_POST['oxygen_saturation'] ?? 98);
        $weight     = (float)($_POST['weight_kg'] ?? 70.0);
        $height     = (float)($_POST['height_cm'] ?? 170.0);
        $bmi        = calculate_bmi($weight, $height);

        // Fetch Doctor ID for current user
        $docStmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $docStmt->execute([':uid' => $user['id']]);
        $docId = (int)$docStmt->fetchColumn();
        if ($docId <= 0) {
            $docId = 1; // Default fallback to Dr. Smith
        }

        if ($patientId <= 0 || empty($complaint) || empty($diagnosis)) {
            $message = 'Patient, Chief Complaint, and Diagnosis are required.';
            $messageType = 'danger';
        } else {
            try {
                $db->beginTransaction();

                $recNum = generate_code('MR');

                $mrStmt = $db->prepare("
                    INSERT INTO medical_records (record_number, patient_id, doctor_id, chief_complaint, symptoms, diagnosis, treatment_plan, notes, follow_up_date) 
                    VALUES (:num, :pid, :did, :complaint, :symptoms, :diag, :treat, :notes, :fdate)
                ");
                $mrStmt->execute([
                    ':num'       => $recNum,
                    ':pid'       => $patientId,
                    ':did'       => $docId,
                    ':complaint' => $complaint,
                    ':symptoms'  => $symptoms,
                    ':diag'      => $diagnosis,
                    ':treat'     => $treatment,
                    ':notes'     => $notes,
                    ':fdate'     => $followUp
                ]);
                $recordId = (int)$db->lastInsertId();

                // Save Vitals
                $vStmt = $db->prepare("
                    INSERT INTO vital_signs (patient_id, medical_record_id, temperature, blood_pressure, heart_rate, respiratory_rate, oxygen_saturation, weight_kg, height_cm, bmi, recorded_by) 
                    VALUES (:pid, :mrid, :temp, :bp, :hr, :rr, :o2, :w, :h, :bmi, :rec_by)
                ");
                $vStmt->execute([
                    ':pid'    => $patientId,
                    ':mrid'   => $recordId,
                    ':temp'   => $temp,
                    ':bp'     => $bp,
                    ':hr'     => $hr,
                    ':rr'     => $rr,
                    ':o2'     => $o2,
                    ':w'      => $weight,
                    ':h'      => $height,
                    ':bmi'    => $bmi,
                    ':rec_by' => $user['id']
                ]);

                $db->commit();
                log_activity($db, $user['id'], 'Medical Record Added', "Created consultation record {$recNum} for Patient ID {$patientId}.");
                $message = "Medical Consultation record {$recNum} created successfully (BMI: {$bmi}).";
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Record Add Error: " . $e->getMessage());
                $message = 'Error creating medical record.';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch Medical Records
$sql = "
    SELECT mr.*, 
           p.first_name AS pat_fn, p.last_name AS pat_ln, p.patient_number,
           d.first_name AS doc_fn, d.last_name AS doc_ln
    FROM medical_records mr 
    JOIN patients p ON mr.patient_id = p.id 
    JOIN doctors d ON mr.doctor_id = d.id 
    WHERE 1=1
";
$params = [];

if ($user['role_name'] === 'Doctor') {
    $sql .= " AND d.user_id = :uid";
    $params[':uid'] = $user['id'];
} elseif ($user['role_name'] === 'Patient') {
    $sql .= " AND p.user_id = :uid";
    $params[':uid'] = $user['id'];
}

$sql .= " ORDER BY mr.visit_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$patients = $db->query("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Medical Records & Consultations</h1>
                <div class="page-subtitle">Record clinical diagnoses, vital signs, complaints, and treatment plans</div>
            </div>
            <?php if (has_role(['Admin', 'Doctor'])): ?>
                <button class="btn btn-primary" onclick="openModal('add-record-modal')">+ Create Medical Record</button>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="badge badge-<?= $messageType ?>" style="display:block; padding:0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom:1.5rem;">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Record #</th>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Visit Date</th>
                            <th>Chief Complaint</th>
                            <th>Diagnosis</th>
                            <th>Follow-up</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--slate-400); padding:2rem;">No medical records found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td><strong style="color:var(--primary-600);"><?= escape($r['record_number']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($r['pat_fn'] . ' ' . $r['pat_ln']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($r['patient_number']) ?></div>
                                    </td>
                                    <td>Dr. <?= escape($r['doc_fn'] . ' ' . $r['doc_ln']) ?></td>
                                    <td><?= format_datetime($r['visit_date']) ?></td>
                                    <td><span style="font-size:0.85rem; color:var(--slate-700);"><?= escape($r['chief_complaint']) ?></span></td>
                                    <td><strong style="color:var(--slate-900);"><?= escape($r['diagnosis']) ?></strong></td>
                                    <td><?= format_date($r['follow_up_date']) ?></td>
                                    <td>
                                        <a href="../patients/profile.php?id=<?= $r['patient_id'] ?>" class="btn btn-sm btn-secondary">View Profile</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<!-- Create Medical Record Modal -->
<div id="add-record-modal" class="modal-overlay">
    <div class="modal-container" style="max-width: 750px;">
        <div class="modal-header">
            <div class="modal-title">New Consultation & Vital Signs Entry</div>
            <button class="modal-close" onclick="closeModal('add-record-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_record">
            <div class="modal-body">
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

                <h4 style="margin-top:1.25rem; margin-bottom:0.75rem; color:var(--primary-700); font-size:1rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.35rem;">
                    1. Patient Vital Signs Intake
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Temp (°C)</label>
                        <input type="number" step="0.1" name="temperature" class="form-control" value="37.0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Blood Pressure</label>
                        <input type="text" name="blood_pressure" class="form-control" value="120/80" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Heart Rate (bpm)</label>
                        <input type="number" name="heart_rate" class="form-control" value="72" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">O2 Saturation (%)</label>
                        <input type="number" name="oxygen_saturation" class="form-control" value="98" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" step="0.1" name="weight_kg" class="form-control" value="70.0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" step="0.1" name="height_cm" class="form-control" value="170.0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Respiratory Rate</label>
                        <input type="number" name="respiratory_rate" class="form-control" value="16" required>
                    </div>
                </div>

                <h4 style="margin-top:1.25rem; margin-bottom:0.75rem; color:var(--primary-700); font-size:1rem; border-bottom:1px solid var(--slate-200); padding-bottom:0.35rem;">
                    2. Clinical Diagnosis & Plan
                </h4>
                <div class="form-group">
                    <label class="form-label">Chief Complaint</label>
                    <input type="text" name="chief_complaint" class="form-control" placeholder="Patient's primary complaint..." required>
                </div>

                <div class="form-group">
                    <label class="form-label">Observed Symptoms</label>
                    <textarea name="symptoms" class="form-control" rows="2" placeholder="Clinical symptoms noted..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Diagnosis</label>
                    <input type="text" name="diagnosis" class="form-control" placeholder="Confirmed medical diagnosis..." required>
                </div>

                <div class="form-group">
                    <label class="form-label">Treatment Plan</label>
                    <textarea name="treatment_plan" class="form-control" rows="2" placeholder="Recommended treatment & care plan..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Follow-up Date (Optional)</label>
                        <input type="date" name="follow_up_date" class="form-control" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Consultation Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Confidential clinical notes...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-record-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Consultation Record</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
