<?php
/**
 * User Accounts & Role Management
 * Hospital Management System (HMS)
 */

$pageTitle = 'User Management';
$activeNav = 'users';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role('Admin');

$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Handle Add User / Edit Status / Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $message = 'CSRF validation failed.';
        $messageType = 'danger';
    } elseif ($action === 'create') {
        $username = sanitize($_POST['username'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $roleId   = (int)($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? 'password123';

        if (empty($username) || empty($email) || $roleId <= 0) {
            $message = 'All fields are required.';
            $messageType = 'danger';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $db->prepare("INSERT INTO users (role_id, username, email, password_hash, status) VALUES (:role_id, :username, :email, :hash, 'Active')");
                $stmt->execute([
                    ':role_id' => $roleId,
                    ':username' => $username,
                    ':email' => $email,
                    ':hash' => $hash
                ]);
                log_activity($db, current_user()['id'], 'User Created', "Created new user '{$username}'.");
                $message = "User account '{$username}' created successfully.";
            } catch (Exception $e) {
                $message = 'Error creating user (username or email may already exist).';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'toggle_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newStatus = $_POST['status'] === 'Active' ? 'Inactive' : 'Active';
        
        $stmt = $db->prepare("UPDATE users SET status = :status WHERE id = :id AND username != 'admin'");
        $stmt->execute([':status' => $newStatus, ':id' => $userId]);
        $message = "User status updated to {$newStatus}.";
    }
}

// Fetch all users with their roles
$users = $db->query("
    SELECT u.*, r.name AS role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    ORDER BY u.created_at DESC
")->fetchAll();

$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">User Accounts Management</h1>
                <div class="page-subtitle">Create accounts, assign role permissions, and manage access statuses</div>
            </div>
            <button class="btn btn-primary" onclick="openModal('add-user-modal')">+ Create New User</button>
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
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= $u['id'] ?></td>
                                <td><strong><?= escape($u['username']) ?></strong></td>
                                <td><?= escape($u['email']) ?></td>
                                <td><span class="badge badge-info"><?= escape($u['role_name']) ?></span></td>
                                <td><span class="badge badge-<?= strtolower($u['status']) ?>"><?= escape($u['status']) ?></span></td>
                                <td><?= format_datetime($u['last_login']) ?></td>
                                <td>
                                    <?php if ($u['username'] !== 'admin'): ?>
                                        <form method="POST" action="users.php" style="display:inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $u['status'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $u['status'] === 'Active' ? 'btn-danger' : 'btn-success' ?>">
                                                <?= $u['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:0.8rem; color:var(--slate-400);">System Owner</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<!-- Create User Modal -->
<div id="add-user-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Create User Account</div>
            <button class="modal-close" onclick="closeModal('add-user-modal')">&times;</button>
        </div>
        <form method="POST" action="users.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="e.g. dr.brown" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="e.g. brown@hospital.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= escape($r['name']) ?> - <?= escape($r['description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Password</label>
                    <input type="password" name="password" class="form-control" value="password123" required>
                    <small style="color:var(--slate-400);">Default: password123</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-user-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save User</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
