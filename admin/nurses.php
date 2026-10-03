<?php
/**
 * Nurse Directory & Staffing Management
 * Hospital Management System (HMS)
 */

$pageTitle = 'Nurse Directory';
$activeNav = 'nurses';
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
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name'] ?? '');
        $gender    = $_POST['gender'] ?? 'Female';
        $email     = sanitize($_POST['email'] ?? '');
        $phone     = sanitize($_POST['phone'] ?? '');
        $deptId    = (int)($_POST['department_id'] ?? 0);

        if (empty($firstName) || empty($lastName) || empty($email) || $deptId <= 0) {
            $message = 'First Name, Last Name, Email, and Department are required.';
            $messageType = 'danger';
        } else {
            try {
                $nurseNum = generate_code('NRS');

                // Check user account
                $userStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                $userStmt->execute([':email' => $email]);
                $user = $userStmt->fetch();
                $userId = $user['id'] ?? null;

                if (!$userId) {
                    $username = strtolower('nurse.' . $firstName);
                    $hash = password_hash('password123', PASSWORD_BCRYPT);
                    $uStmt = $db->prepare("INSERT INTO users (role_id, username, email, password_hash, status) VALUES (3, :username, :email, :hash, 'Active')");
                    $uStmt->execute([':username' => $username, ':email' => $email, ':hash' => $hash]);
                    $userId = (int)$db->lastInsertId();
                }

                $stmt = $db->prepare("
                    INSERT INTO nurses (user_id, department_id, nurse_number, first_name, last_name, gender, phone, email, status) 
                    VALUES (:uid, :dept_id, :nurse_num, :fn, :ln, :gender, :phone, :email, 'Active')
                ");
                $stmt->execute([
                    ':uid'       => $userId,
                    ':dept_id'   => $deptId,
                    ':nurse_num' => $nurseNum,
                    ':fn'        => $firstName,
                    ':ln'        => $lastName,
                    ':gender'    => $gender,
                    ':phone'     => $phone,
                    ':email'     => $email
                ]);
                log_activity($db, current_user()['id'], 'Nurse Registered', "Registered nurse Nurse {$firstName} {$lastName}.");
                $message = "Nurse Nurse {$firstName} {$lastName} registered successfully.";
            } catch (Exception $e) {
                $message = 'Error registering nurse (email must be unique).';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['nurse_id'] ?? 0);
        $currStatus = $_POST['status'] ?? 'Active';
        $newStatus  = $currStatus === 'Active' ? 'Inactive' : 'Active';

        $stmt = $db->prepare("UPDATE nurses SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $id]);
        $message = "Nurse status changed to {$newStatus}.";
    }
}

$nurses = $db->query("
    SELECT n.*, d.name AS department_name 
    FROM nurses n 
    JOIN departments d ON n.department_id = d.id 
    ORDER BY n.first_name ASC
")->fetchAll();

$departments = $db->query("SELECT * FROM departments WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Nurse Directory</h1>
                <div class="page-subtitle">Manage nursing staff profiles and department assignments</div>
            </div>
            <button class="btn btn-primary" onclick="openModal('add-nurse-modal')">+ Add Nurse</button>
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
                            <th>Nurse Code</th>
                            <th>Nurse Name</th>
                            <th>Gender</th>
                            <th>Department</th>
                            <th>Contact Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nurses as $n): ?>
                            <tr>
                                <td><strong style="color:var(--primary-600);"><?= escape($n['nurse_number']) ?></strong></td>
                                <td><strong>Nurse <?= escape($n['first_name'] . ' ' . $n['last_name']) ?></strong></td>
                                <td><?= escape($n['gender']) ?></td>
                                <td><span class="badge badge-info"><?= escape($n['department_name']) ?></span></td>
                                <td><?= escape($n['phone']) ?></td>
                                <td><?= escape($n['email']) ?></td>
                                <td><span class="badge badge-<?= strtolower($n['status']) ?>"><?= escape($n['status']) ?></span></td>
                                <td>
                                    <form method="POST" action="nurses.php" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="nurse_id" value="<?= $n['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $n['status'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $n['status'] === 'Active' ? 'btn-danger' : 'btn-success' ?>">
                                            <?= $n['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
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

<!-- Add Nurse Modal -->
<div id="add-nurse-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Register Nurse</div>
            <button class="modal-close" onclick="closeModal('add-nurse-modal')">&times;</button>
        </div>
        <form method="POST" action="nurses.php">
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
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-nurse-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Nurse Record</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
