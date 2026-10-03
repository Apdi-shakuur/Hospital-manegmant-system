<?php
/**
 * Department Management
 * Hospital Management System (HMS)
 */

$pageTitle = 'Department Management';
$activeNav = 'departments';
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
        $name        = sanitize($_POST['name'] ?? '');
        $code        = strtoupper(sanitize($_POST['code'] ?? ''));
        $description = sanitize($_POST['description'] ?? '');

        if (empty($name) || empty($code)) {
            $message = 'Department name and code are required.';
            $messageType = 'danger';
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO departments (name, code, description, status) VALUES (:name, :code, :desc, 'Active')");
                $stmt->execute([':name' => $name, ':code' => $code, ':desc' => $description]);
                log_activity($db, current_user()['id'], 'Department Added', "Added department '{$name}' ({$code}).");
                $message = "Department '{$name}' created successfully.";
            } catch (Exception $e) {
                $message = 'Error creating department (code or name must be unique).';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['dept_id'] ?? 0);
        $currStatus = $_POST['status'] ?? 'Active';
        $newStatus  = $currStatus === 'Active' ? 'Inactive' : 'Active';
        
        $stmt = $db->prepare("UPDATE departments SET status = :status WHERE id = :id");
        $stmt->execute([':status' => $newStatus, ':id' => $id]);
        $message = "Department status changed to {$newStatus}.";
    }
}

// Fetch departments with active doctor counts
$departments = $db->query("
    SELECT d.*, COUNT(doc.id) AS doctor_count 
    FROM departments d 
    LEFT JOIN doctors doc ON d.id = doc.department_id AND doc.status = 'Active' 
    GROUP BY d.id 
    ORDER BY d.name ASC
")->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Department Management</h1>
                <div class="page-subtitle">Configure hospital clinical and operational departments</div>
            </div>
            <button class="btn btn-primary" onclick="openModal('add-dept-modal')">+ Add Department</button>
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
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Description</th>
                            <th>Assigned Doctors</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                            <tr>
                                <td><strong style="color:var(--primary-600);"><?= escape($d['code']) ?></strong></td>
                                <td><strong><?= escape($d['name']) ?></strong></td>
                                <td><?= escape($d['description'] ?: 'N/A') ?></td>
                                <td><span class="badge badge-info"><?= $d['doctor_count'] ?> Doctors</span></td>
                                <td><span class="badge badge-<?= strtolower($d['status']) ?>"><?= escape($d['status']) ?></span></td>
                                <td>
                                    <form method="POST" action="departments.php" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="dept_id" value="<?= $d['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $d['status'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $d['status'] === 'Active' ? 'btn-danger' : 'btn-success' ?>">
                                            <?= $d['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>
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

<!-- Add Department Modal -->
<div id="add-dept-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Add New Department</div>
            <button class="modal-close" onclick="closeModal('add-dept-modal')">&times;</button>
        </div>
        <form method="POST" action="departments.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Department Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Neurology" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Department Code (Short Identifier)</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. NEURO" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief summary of specialized clinical services provided..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-dept-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Department</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
