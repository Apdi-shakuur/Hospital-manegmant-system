<?php
/**
 * Patient Management Directory & Registration
 * Hospital Management System (HMS)
 */

$pageTitle = 'Patient Management';
$activeNav = 'patients';
$baseUrl   = '../';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_role(['Admin', 'Receptionist', 'Doctor', 'Nurse']);

$db = Database::getInstance();
$message = '';
$messageType = 'success';

// Handle Add / Edit Patient
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
        $dob          = $_POST['date_of_birth'] ?? '';
        $phone        = sanitize($_POST['phone'] ?? '');
        $email        = sanitize($_POST['email'] ?? '');
        $address      = sanitize($_POST['address'] ?? '');
        $emergency    = sanitize($_POST['emergency_contact'] ?? '');
        $bloodGroup   = $_POST['blood_group'] ?? 'Unknown';
        $allergies    = sanitize($_POST['allergies'] ?? '');

        if (empty($firstName) || empty($lastName) || empty($dob) || empty($phone)) {
            $message = 'First Name, Last Name, Date of Birth, and Phone are required.';
            $messageType = 'danger';
        } else {
            try {
                $patientNum = generate_code('PAT');

                // Create user account if email provided
                $userId = null;
                if (!empty($email)) {
                    $uStmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
                    $uStmt->execute([':email' => $email]);
                    $user = $uStmt->fetch();
                    $userId = $user['id'] ?? null;

                    if (!$userId) {
                        $username = strtolower('patient.' . str_replace(' ', '', $firstName));
                        $hash = password_hash('password123', PASSWORD_BCRYPT);
                        $insU = $db->prepare("INSERT INTO users (role_id, username, email, password_hash, status) VALUES (7, :username, :email, :hash, 'Active')");
                        $insU->execute([':username' => $username, ':email' => $email, ':hash' => $hash]);
                        $userId = (int)$db->lastInsertId();
                    }
                }

                $stmt = $db->prepare("
                    INSERT INTO patients (user_id, patient_number, first_name, last_name, gender, date_of_birth, phone, email, address, emergency_contact, blood_group, allergies, status) 
                    VALUES (:uid, :pnum, :fn, :ln, :gender, :dob, :phone, :email, :addr, :emerg, :bg, :allergies, 'Active')
                ");
                $stmt->execute([
                    ':uid'       => $userId,
                    ':pnum'      => $patientNum,
                    ':fn'        => $firstName,
                    ':ln'        => $lastName,
                    ':gender'    => $gender,
                    ':dob'       => $dob,
                    ':phone'     => $phone,
                    ':email'     => $email,
                    ':addr'      => $address,
                    ':emerg'     => $emergency,
                    ':bg'        => $bloodGroup,
                    ':allergies' => $allergies
                ]);
                log_activity($db, current_user()['id'], 'Patient Registered', "Registered patient {$firstName} {$lastName} ({$patientNum}).");
                $message = "Patient {$firstName} {$lastName} ({$patientNum}) registered successfully.";
            } catch (Exception $e) {
                error_log("Patient Reg Error: " . $e->getMessage());
                $message = 'Error registering patient.';
                $messageType = 'danger';
            }
        }
    }
}

// Search and Filter logic
$searchQuery = sanitize($_GET['search'] ?? '');
$bloodFilter = $_GET['blood_group'] ?? '';

$sql = "SELECT * FROM patients WHERE 1=1";
$params = [];

if (!empty($searchQuery)) {
    $sql .= " AND (patient_number LIKE :q OR first_name LIKE :q OR last_name LIKE :q OR phone LIKE :q OR email LIKE :q)";
    $params[':q'] = "%{$searchQuery}%";
}

if (!empty($bloodFilter)) {
    $sql .= " AND blood_group = :bg";
    $params[':bg'] = $bloodFilter;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="content-body">
        <div class="page-header">
            <div>
                <h1 class="page-title">Patient Management Directory</h1>
                <div class="page-subtitle">Register patients, search profiles, view medical history and billing</div>
            </div>
            <button class="btn btn-primary" onclick="openModal('add-patient-modal')">+ Register New Patient</button>
        </div>

        <?php if ($message): ?>
            <div class="badge badge-<?= $messageType ?>" style="display:block; padding:0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom:1.5rem;">
                <?= escape($message) ?>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Card -->
        <div class="card">
            <form method="GET" action="index.php" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
                <div class="search-box" style="flex:1; min-width:260px;">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" name="search" class="form-control" placeholder="Search patient name, ID, phone..." value="<?= escape($searchQuery) ?>">
                </div>

                <div style="width:160px;">
                    <select name="blood_group" class="form-select" onchange="this.form.submit()">
                        <option value="">All Blood Groups</option>
                        <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg): ?>
                            <option value="<?= $bg ?>" <?= $bloodFilter === $bg ? 'selected' : '' ?>><?= $bg ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-secondary">Filter</button>
                <?php if ($searchQuery || $bloodFilter): ?>
                    <a href="index.php" class="btn btn-secondary" style="color:var(--rose-600);">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Patients List Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient ID</th>
                            <th>Full Name</th>
                            <th>Gender / DOB</th>
                            <th>Blood Group</th>
                            <th>Phone & Email</th>
                            <th>Allergies</th>
                            <th>Registered</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; color:var(--slate-400); padding:2rem;">No patients found matching criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($patients as $p): ?>
                                <tr>
                                    <td><strong style="color:var(--primary-600);"><?= escape($p['patient_number']) ?></strong></td>
                                    <td><strong><?= escape($p['first_name'] . ' ' . $p['last_name']) ?></strong></td>
                                    <td>
                                        <div><?= escape($p['gender']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= format_date($p['date_of_birth']) ?></div>
                                    </td>
                                    <td><span class="badge badge-info"><?= escape($p['blood_group']) ?></span></td>
                                    <td>
                                        <div><?= escape($p['phone']) ?></div>
                                        <div style="font-size:0.75rem; color:var(--slate-400);"><?= escape($p['email'] ?: 'N/A') ?></div>
                                    </td>
                                    <td><span style="font-size:0.8rem; color:var(--rose-600); font-weight:600;"><?= escape($p['allergies'] ?: 'None') ?></span></td>
                                    <td><?= format_date($p['created_at']) ?></td>
                                    <td>
                                        <a href="profile.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">View Profile</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<!-- Register Patient Modal -->
<div id="add-patient-modal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">Register New Patient</div>
            <button class="modal-close" onclick="closeModal('add-patient-modal')">&times;</button>
        </div>
        <form method="POST" action="index.php">
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
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+1 555-XXXX" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address (Optional)</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Blood Group</label>
                        <select name="blood_group" class="form-select">
                            <option value="Unknown">Unknown</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Emergency Contact Info</label>
                        <input type="text" name="emergency_contact" class="form-control" placeholder="Spouse / Parent (+1 555-XXXX)">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Home Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Street address, city, state..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Known Allergies</label>
                    <input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin, Latex, Nuts">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-patient-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Complete Registration</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
