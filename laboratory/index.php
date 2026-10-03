<?php
/**
 * Laboratory Test Queue & Results Entry
 * Hospital Management System (HMS)
 */

$pageTitle = 'Laboratory Management';
$activeNav = 'laboratory';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Doctor', 'Laboratory Staff', 'Patient']);

$db = Database::getInstance();
$user = current_user();
$message = '';
$messageType = 'success';

// Handle Create Lab Request & Enter Results
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'create_request') {
        $patientId  = (int)($_POST['patient_id'] ?? 0);
        $testId     = (int)($_POST['lab_test_id'] ?? 0);
        $priority   = $_POST['priority'] ?? 'Routine';
        $notes      = sanitize($_POST['notes'] ?? '');

        // Fetch Doctor ID
        $docStmt = $db->prepare("SELECT id FROM doctors WHERE user_id = :uid LIMIT 1");
        $docStmt->execute([':uid' => $user['id']]);
        $docId = (int)$docStmt->fetchColumn();
        if ($docId <= 0) $docId = 1;

        if ($patientId <= 0 || $testId <= 0) {
            $message = 'Patient and Test selection are required.';
            $messageType = 'danger';
        } else {
            try {
                $reqNum = generate_code('LAB-REQ');
                $stmt = $db->prepare("
                    INSERT INTO lab_requests (request_number, patient_id, doctor_id, priority, notes, status) 
                    VALUES (:num, :pid, :did, :prio, :notes, 'Requested')
                ");
                $stmt->execute([
                    ':num'   => $reqNum,
                    ':pid'   => $patientId,
                    ':did'   => $docId,
                    ':prio'  => $priority,
                    ':notes' => $notes
                ]);
                $reqId = (int)$db->lastInsertId();

                // Insert pending result placeholder
                $testStmt = $db->prepare("SELECT normal_range FROM lab_tests WHERE id = :tid");
                $testStmt->execute([':tid' => $testId]);
                $range = $testStmt->fetchColumn();

                $resStmt = $db->prepare("INSERT INTO lab_results (lab_request_id, lab_test_id, result_value, normal_range, status) VALUES (:rid, :tid, 'Pending Analysis', :range, 'Normal')");
                $resStmt->execute([':rid' => $reqId, ':tid' => $testId, ':range' => $range]);

                log_activity($db, $user['id'], 'Lab Test Requested', "Created lab request {$reqNum} for Patient ID {$patientId}.");
                $message = "Lab Test Request {$reqNum} submitted successfully.";
            } catch (Exception $e) {
                error_log("Lab Req Error: " . $e->getMessage());
                $message = 'Error creating lab test request.';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'submit_results') {
        $reqId     = (int)($_POST['request_id'] ?? 0);
        $resValue  = sanitize($_POST['result_value'] ?? '');
        $statusVal = $_POST['result_status'] ?? 'Normal';
        $techNotes = sanitize($_POST['notes'] ?? '');

        if ($reqId <= 0 || empty($resValue)) {
            $message = 'Test result value is required.';
            $messageType = 'danger';
        } else {
            try {
                $db->beginTransaction();

                $upRes = $db->prepare("
                    UPDATE lab_results 
                    SET result_value = :val, status = :st, notes = :notes, technician_id = :tid, result_date = NOW() 
                    WHERE lab_request_id = :rid
                ");
                $upRes->execute([
                    ':val'   => $resValue,
                    ':st'    => $statusVal,
                    ':notes' => $techNotes,
                    ':tid'   => $user['id'],
                    ':rid'   => $reqId
                ]);

                $upReq = $db->prepare("UPDATE lab_requests SET status = 'Completed' WHERE id = :rid");
                $upReq->execute([':rid' => $reqId]);

                $db->commit();
                log_activity($db, $user['id'], 'Lab Result Entered', "Processed lab request ID {$reqId} with status '{$statusVal}'.");
                $message = "Laboratory test results recorded successfully.";
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Lab Result Error: " . $e->getMessage());
                $message = 'Error submitting lab test results.';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'update_status') {
        $reqId     = (int)($_POST['request_id'] ?? 0);
        $newStatus = $_POST['status'] ?? 'Requested';

        $stmt = $db->prepare("UPDATE lab_requests SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $reqId]);
        $message = "Lab request status updated to {$newStatus}.";
    }
}

// Fetch Lab Requests
$sql = "
    SELECT lr.*, 
           p.first_name AS pat_fn, p.last_name AS pat_ln, p.patient_number,
           d.first_name AS doc_fn, d.last_name AS doc_ln,
           lt.test_name, lt.category AS test_cat, lt.price,
           lres.result_value, lres.normal_range, lres.status AS res_status
    FROM lab_requests lr 
    JOIN patients p ON lr.patient_id = p.id 
    JOIN doctors d ON lr.doctor_id = d.id 
    LEFT JOIN lab_results lres ON lr.id = lres.lab_request_id 
    LEFT JOIN lab_tests lt ON lres.lab_test_id = lt.id 
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

$sql .= " ORDER BY lr.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$labRequests = $stmt->fetchAll();

$patients = $db->query("SELECT id, patient_number, first_name, last_name FROM patients WHERE status = 'Active' ORDER BY first_name ASC")->fetchAll();
$labTests = $db->query("SELECT id, test_code, test_name, category, price, normal_range FROM lab_tests WHERE status = 'Active' ORDER BY test_name ASC")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Laboratory Management</h1>
                <div class="page-subtitle">Request lab tests, collect samples, enter analytical results, and view history</div>
            </div>
            <?php if (has_role(['Admin', 'Doctor'])): ?>
                <button class="btn btn-primary" onclick="openModal('add-req-modal')">+ Order Lab Test</button>
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
                            <th>Request #</th>
                            <th>Patient</th>
                            <th>Test Order</th>
                            <th>Ordering Doctor</th>
                            <th>Priority</th>
                            <th>Request Status</th>
                            <th>Result Output</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($labRequests)): ?>
                            <tr><td colspan="8" style="text-align:center; color:var(--slate-400); padding:2rem;">No laboratory test requests found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($labRequests as $lr): ?>
                                <tr>
                                    <td><strong style="color:var(--primary-600);"><?= escape($lr['request_number']) ?></strong></td>
                                    <td>
                                        <strong><?= escape($lr['pat_fn'] . ' ' . $lr['pat_ln']) ?></strong>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($lr['patient_number']) ?></div>
                                    </td>
                                    <td>
                                        <strong><?= escape($lr['test_name'] ?: 'Lab Screening') ?></strong>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($lr['test_cat'] ?: 'General') ?></div>
                                    </td>
                                    <td>Dr. <?= escape($lr['doc_fn'] . ' ' . $lr['doc_ln']) ?></td>
                                    <td><span class="badge badge-info"><?= escape($lr['priority']) ?></span></td>
                                    <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $lr['status'])) ?>"><?= escape($lr['status']) ?></span></td>
                                    <td>
                                        <?php if ($lr['status'] === 'Completed'): ?>
                                            <div style="font-size:0.85rem; font-weight:600; color:var(--slate-900);"><?= escape($lr['result_value']) ?></div>
                                            <span class="badge badge-<?= strtolower($lr['res_status']) ?>"><?= escape($lr['res_status']) ?></span>
                                        <?php else: ?>
                                            <span style="font-size:0.8rem; color:var(--slate-400);">Analysis Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (has_role(['Admin', 'Laboratory Staff'])): ?>
                                            <?php if ($lr['status'] !== 'Completed'): ?>
                                                <button class="btn btn-sm btn-success" onclick="openResultModal(<?= $lr['id'] ?>, '<?= escape($lr['request_number']) ?>', '<?= escape($lr['test_name']) ?>')">Enter Results</button>
                                                
                                                <form method="POST" action="index.php" style="display:inline-block; margin-top:0.2rem;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="request_id" value="<?= $lr['id'] ?>">
                                                    <select name="status" class="form-select" onchange="this.form.submit()" style="padding:0.2rem; font-size:0.75rem; width:auto;">
                                                        <option value="Requested" <?= $lr['status'] === 'Requested' ? 'selected' : '' ?>>Requested</option>
                                                        <option value="Sample Collected" <?= $lr['status'] === 'Sample Collected' ? 'selected' : '' ?>>Sample Collected</option>
                                                        <option value="Processing" <?= $lr['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                                                    </select>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge badge-completed">✓ Done</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="../patients/profile.php?id=<?= $lr['patient_id'] ?>" class="btn btn-sm btn-secondary">Profile</a>
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

<!-- Order Lab Test Modal -->
<div id="add-req-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Create Laboratory Request</div>
            <button class="modal-close" onclick="closeModal('add-req-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_request">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Patient</label>
                    <select name="patient_id" class="form-select" required>
                        <option value="">-- Choose Patient --</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= escape($p['patient_number']) ?> - <?= escape($p['first_name'] . ' ' . $p['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Test Catalog Item</label>
                    <select name="lab_test_id" class="form-select" required>
                        <option value="">-- Choose Laboratory Test --</option>
                        <?php foreach ($labTests as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= escape($t['test_name']) ?> (<?= escape($t['category']) ?> - $<?= number_format($t['price'], 2) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Priority Level</label>
                    <select name="priority" class="form-select">
                        <option value="Routine">Routine</option>
                        <option value="Urgent">Urgent</option>
                        <option value="Emergency">Emergency</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Clinical Notes for Lab Tech</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Specific indications or sample collection instructions..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-req-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Order</button>
            </div>
        </form>
    </div>
</div>

<!-- Enter Test Results Modal -->
<div id="result-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Enter Results: <span id="res-test-title"></span></div>
            <button class="modal-close" onclick="closeModal('result-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="submit_results">
            <input type="hidden" id="res-req-id" name="request_id" value="">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Result Value & Measurements</label>
                    <textarea name="result_value" class="form-control" rows="3" placeholder="Enter numerical values, observations, or lab findings..." required></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Result Assessment Status</label>
                    <select name="result_status" class="form-select" required>
                        <option value="Normal">Normal (Within Reference Range)</option>
                        <option value="Abnormal">Abnormal (Outside Normal Limits)</option>
                        <option value="Critical">Critical (High Clinical Flag)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Technician Comments</label>
                    <input type="text" name="notes" class="form-control" placeholder="Comments on sample quality or method...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('result-modal')">Cancel</button>
                <button type="submit" class="btn btn-success">Save & Publish Results</button>
            </div>
        </form>
    </div>
</div>

<script>
function openResultModal(reqId, reqNum, testName) {
    document.getElementById('res-req-id').value = reqId;
    document.getElementById('res-test-title').textContent = reqNum + ' - ' + testName;
    openModal('result-modal');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
