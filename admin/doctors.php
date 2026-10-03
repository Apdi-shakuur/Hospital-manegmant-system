<?php
/**
 * Doctor Directory & Profile Management
 * Hospital Management System (HMS)
 */

$pageTitle = 'Doctor Directory';
$activeNav = 'doctors';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role('Admin');

$db = Database::getInstance();
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'create') {
        $firstName    = sanitize($_POST['first_name'] ?? '');
        $lastName     = sanitize($_POST['last_name'] ?? '');
        $gender       = $_POST['gender'] ?? 'Male';
        $email        = sanitize($_POST['email'] ?? '');
        $phone        = sanitize($_POST['phone'] ?? '');
        $deptId       = (int)($_POST['department_id'] ?? 0);
        $spec         = sanitize($_POST['specialization'] ?? '');
        $license      = sanitize($_POST['license_number'] ?? '');
        $exp          = (int)($_POST['experience_years'] ?? 0);
        $schedule     = sanitize($_POST['availability_schedule'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($email) || empty($license) || $deptId <= 0) {
            $message = 'First Name, Last Name, Email, License Number and Department are required.';
            $messageType = 'danger';
        } else {
            try {
                // Auto generate Doctor Code (e.g. DOC-1005)
                $docNum = generate_code('DOC');

                // Check if user account needs to be auto-created
                $userStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $userStmt->execute([':email' => $email]);
                $user = $userStmt->fetch();
                $userId = $user['id'] ?? null;

                if (!$userId) {
                    $username = strtolower($firstName . '.' . $lastName);
                    $hash = password_hash('password123', PASSWORD_BCRYPT);
                    $uStmt = $db->prepare("INSERT INTO users (role_id, username, email, password_hash, status) VALUES (2, :username, :email, :hash, 'Active')");
                    $uStmt->execute([':username' => $username, ':email' => $email, ':hash' => $hash]);
                    $userId = (int)$db->lastInsertId();
                }

                $stmt = $db->prepare("
                    INSERT INTO doctors (user_id, department_id, doctor_number, first_name, last_name, gender, phone, email, specialization, license_number, experience_years, availability_schedule, status) 
                    VALUES (:uid, :dept_id, :doc_num, :fn, :ln, :gender, :phone, :email, :spec, :license, :exp, :sched, 'Active')
                ");
                $stmt->execute([
                    ':uid'     => $userId,
                    ':dept_id' => $deptId,
                    ':doc_num' => $docNum,
                    ':fn'      => $firstName,
                    ':ln'      => $lastName,
                    ':gender'  => $gender,
                    ':phone'   => $phone,
                    ':email'   => $email,
                    ':spec'    => $spec,
                    ':license' => $license,
                    ':exp'     => $exp,
                    ':sched'   => $schedule
                ]);
                log_activity($db, current_user()['id'], 'Doctor Created', "Created doctor profile Dr. {$firstName} {$lastName}.");
                $message = "Doctor profile Dr. {$firstName} {$lastName} registered successfully.";
            } catch (Exception $e) {
                error_log("Doctor Save Error: " . $e->getMessage());
                $message = 'Error creating doctor record (license number or email must be unique).';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['doctor_id'] ?? 0);
        $currStatus = $_POST['status'] ?? 'Active';
        $newStatus  = $currStatus === 'Active' ? 'Inactive' : 'Active';

        $stmt = $db->prepare("UPDATE doctors SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $id]);
        $message = "Doctor status changed to {$newStatus}.";
    }
}

// Fetch doctors
$doctors = $db->query("
    SELECT doc.*, d.name AS department_name 
    FROM doctors doc 
    JOIN departments d ON doc.department_id = d.id 
    ORDER BY doc.first_name ASC
")->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Doctor Directory</h1>
                <div class="page-subtitle">Manage medical specialists, departments, licenses, and availability schedules</div>
            </div>
            <button class="btn btn-primary" onclick="openModal('add-doctor-modal')">+ Add New Doctor</button>
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
                            <th>Doc ID</th>
                            <th>Doctor Name</th>
                            <th>Department</th>
                            <th>Specialization</th>
                            <th>License</th>
                            <th>Contact</th>
                            <th>Availability</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($doctors as $doc): ?>
                            <tr>
                                <td><strong style="color:var(--primary-600);"><?= escape($doc['doctor_number']) ?></strong></td>
                                <td>
                                    <strong>Dr. <?= escape($doc['first_name'] . ' ' . $doc['last_name']) ?></strong>
                                    <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($doc['gender']) ?> | <?= $doc['experience_years'] ?> Yrs Exp</div>
                                </td>
                                <td><span class="badge badge-info"><?= escape($doc['department_name']) ?></span></td>
                                <td><?= escape($doc['specialization']) ?></td>
                                <td><code><?= escape($doc['license_number']) ?></code></td>
                                <td>
                                    <div><?= escape($doc['phone']) ?></div>
                                    <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($doc['email']) ?></div>
                                </td>
                                <td><span style="font-size:0.8rem; color:var(--slate-600);"><?= escape($doc['availability_schedule']) ?></span></td>
                                <td><span class="badge badge-<?= strtolower($doc['status']) ?>"><?= escape($doc['status']) ?></span></td>
                                <td>
                                    <form method="POST" action="doctors.php" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="doctor_id" value="<?= $doc['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $doc['status'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $doc['status'] === 'Active' ? 'btn-danger' : 'btn-success' ?>">
                                            <?= $doc['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<!-- Add Doctor Modal -->
<div id="add-doctor-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Register New Doctor</div>
            <button class="modal-close" onclick="closeModal('add-doctor-modal')">&times;</button>
        </div>
        <form method="POST" action="doctors.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department_id" class="form-select" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= $dept['id'] ?>"><?= escape($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" class="form-control" placeholder="e.g. Pediatric Surgery" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Medical License Number</label>
                        <input type="text" name="license_number" class="form-control" placeholder="e.g. LIC-SURG-9901" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Experience (Years)</label>
                        <input type="number" name="experience_years" class="form-control" value="5" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Availability Schedule</label>
                        <input type="text" name="availability_schedule" class="form-control" value="Mon - Fri (08:00 AM - 04:00 PM)">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-doctor-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Doctor Profile</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
