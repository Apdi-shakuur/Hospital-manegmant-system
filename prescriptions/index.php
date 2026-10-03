<?php
/**
 * Doctor Prescription Writer & History
 * Hospital Management System (HMS)
 */

$pageTitle = 'Prescription System';
$activeNav = 'prescriptions';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Doctor', 'Pharmacist', 'Patient']);

$db = Database::getInstance();
$user = current_user();
$message = '';
$messageType = 'success';

// Handle Add Prescription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'create_rx') {
        $patientId  = (int)($_POST['patient_id'] ?? 0);
        $medicineId = (int)($_POST['medicine_id'] ?? 0);
        $dosage     = sanitize($_POST['dosage'] ?? '');
        $freq       = sanitize($_POST['frequency'] ?? '');
        $duration   = sanitize($_POST['duration'] ?? '');
        $qty        = (int)($_POST['quantity'] ?? 1);
        $instructions = sanitize($_POST['instructions'] ?? '');
        $notes      = sanitize($_POST['notes'] ?? '');

        // Fetch Doctor ID
        $docStmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $docStmt->execute([':uid' => $user['id']]);
        $docId = (int)$docStmt->fetchColumn();
        if ($docId <= 0) $docId = 1;

        if ($patientId <= 0 || $medicineId <= 0 || empty($dosage) || $qty <= 0) {
            $message = 'Patient, Medicine, Dosage, and Quantity are required.';
            $messageType = 'danger';
        } else {
            try {
                $db->beginTransaction();

                $rxNum = generate_code('RX');

                $rxStmt = $db->prepare("
                    INSERT INTO prescriptions (prescription_number, patient_id, doctor_id, status, notes) 
                    VALUES (:num, :pid, :did, 'Pending', :notes)
                ");
                $rxStmt->execute([
                    ':num'   => $rxNum,
                    ':pid'   => $patientId,
                    ':did'   => $docId,
                    ':notes' => $notes
                ]);
                $rxId = (int)$db->lastInsertId();

                $itemStmt = $db->prepare("
                    INSERT INTO prescription_items (prescription_id, medicine_id, dosage, frequency, duration, quantity, instructions) 
                    VALUES (:rxid, :medid, :dosage, :freq, :duration, :qty, :inst)
                ");
                $itemStmt->execute([
                    ':rxid'     => $rxId,
                    ':medid'    => $medicineId,
                    ':dosage'   => $dosage,
                    ':freq'     => $freq,
                    ':duration' => $duration,
                    ':qty'      => $qty,
                    ':inst'     => $instructions
                ]);

                $db->commit();
                log_activity($db, $user['id'], 'Prescription Created', "Created prescription {$rxNum} for Patient ID {$patientId}.");
                $message = "Prescription {$rxNum} created successfully and queued for pharmacy dispensing.";
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Rx Error: " . $e->getMessage());
                $message = 'Error creating prescription.';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch Prescriptions List
$sql = "
    SELECT pr.*, 
           p.first_name AS pat_fn, p.last_name AS pat_ln, p.patient_number,
           d.first_name AS doc_fn, d.last_name AS doc_ln
    FROM prescriptions pr 
    JOIN patients p ON pr.patient_id = p.id 
    JOIN doctors d ON pr.doctor_id = d.id 
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

$sql .= " ORDER BY pr.prescription_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

$patients = $db->query("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();
$medicines = $db->query("SELECT id, name, generic_name, quantity, unit_price FROM medicines WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Prescription Management</h1>
                <div class="page-subtitle">Write patient prescriptions, set dosages, and send to pharmacy</div>
            </div>
            <?php if (has_role(['Admin', 'Doctor'])): ?>
                <button class="btn btn-primary" onclick="openModal('add-rx-modal')">+ Create Prescription</button>
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
                            <th>Rx Code</th>
                            <th>Patient Name</th>
                            <th>Prescribing Doctor</th>
                            <th>Date Issued</th>
                            <th>Prescribed Items</th>
                            <th>Dispensing Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prescriptions)): ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--slate-400); padding:2rem;">No prescriptions recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($prescriptions as $rx): ?>
                                <?php
                                $itemsStmt = $db->prepare("
                                    SELECT pi.*, m.name AS med_name 
                                    FROM prescription_items pi 
                                    JOIN medicines m ON pi.medicine_id = m.id 
                                    WHERE pi.prescription_id = :rxid
                                ");
                                $itemsStmt->execute([':rxid' => $rx['id']]);
                                $items = $itemsStmt->fetchAll();
                                ?>
                                <tr>
                                    <td><strong style="color:var(--primary-600);"><?= escape($rx['prescription_number']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($rx['pat_fn'] . ' ' . $rx['pat_ln']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($rx['patient_number']) ?></div>
                                    </td>
                                    <td>Dr. <?= escape($rx['doc_fn'] . ' ' . $rx['doc_ln']) ?></td>
                                    <td><?= format_datetime($rx['prescription_date']) ?></td>
                                    <td>
                                        <?php foreach ($items as $it): ?>
                                            <div><strong><?= escape($it['med_name']) ?></strong> (<?= escape($it['dosage']) ?>, <?= escape($it['frequency']) ?>, Qty: <?= $it['quantity'] ?>)</div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td><span class="badge badge-<?= strtolower($rx['status']) ?>"><?= escape($rx['status']) ?></span></td>
                                    <td>
                                        <?php if (has_role(['Admin', 'Pharmacist']) && $rx['status'] === 'Pending'): ?>
                                            <a href="../pharmacy/dispensing.php?rx_id=<?= $rx['id'] ?>" class="btn btn-sm btn-success">Dispense Medicine</a>
                                        <?php else: ?>
                                            <a href="../patients/profile.php?id=<?= $rx['patient_id'] ?>" class="btn btn-sm btn-secondary">View Profile</a>
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

<!-- Create Prescription Modal -->
<div id="add-rx-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Write New Prescription</div>
            <button class="modal-close" onclick="closeModal('add-rx-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_rx">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Patient</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">-- Select Patient --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (($_GET['patient_id'] ?? 0) == $p['id']) ? 'selected' : '' ?>>
                                <?= escape($p['patient_number']) ?> - <?= escape($p['first_name'] . ' ' . $p['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Medicine Item</label>
                    <select name="medicine_id" class="form-select" required>
                        <option value="">-- Choose Stocked Drug --</option>
                        <?php foreach ($medicines as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= escape($m['name']) ?> (Available Stock: <?= $m['quantity'] ?> units)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Dosage</label>
                        <input type="text" name="dosage" class="form-control" placeholder="e.g. 500mg" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Frequency</label>
                        <input type="text" name="frequency" class="form-control" placeholder="e.g. 3 times daily" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Duration</label>
                        <input type="text" name="duration" class="form-control" placeholder="e.g. 5 days" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Prescribed Quantity</label>
                        <input type="number" name="quantity" class="form-control" value="10" min="1" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Dispensing & Usage Instructions</label>
                    <input type="text" name="instructions" class="form-control" placeholder="e.g. Take after meals with glass of water">
                </div>

                <div class="form-group">
                    <label class="form-label">Prescription Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Optional notes for pharmacist...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-rx-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Send Rx</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
