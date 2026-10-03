<?php
/**
 * User Login Page
 * Hospital Management System (HMS)
 */

require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ../' . get_user_dashboard_url());
    exit;
}

$errorMessage = '';
$successMessage = '';

if (isset($_GET['logout'])) {
    $successMessage = 'You have been logged out successfully.';
} elseif (isset($_GET['expired'])) {
    $errorMessage = 'Your session has expired. Please log in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $username  = sanitize($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';

    if (!verify_csrf_token($csrfToken)) {
        $errorMessage = 'Invalid request payload (CSRF failure). Please try again.';
    } elseif (empty($username) || empty($password)) {
        $errorMessage = 'Please provide both username and password.';
    } else {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT u.*, r.name AS role_name 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.username = :username OR u.email = :email 
                LIMIT 1
            ");
            $stmt->execute([
                ':username' => $username,
                ':email'    => $username
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'Active') {
                    $errorMessage = 'Your account status is currently ' . escape($user['status']) . '. Please contact System Admin.';
                } else {
                    // Fetch full name depending on role
                    $fullName = $user['username'];
                    if ($user['role_name'] === 'Doctor') {
                        $dStmt = $db->prepare("SELECT CONCAT('Dr. ', first_name, ' ', last_name) AS full_name FROM doctors WHERE user_id = :uid");
                        $dStmt->execute([':uid' => $user['id']]);
                        $d = $dStmt->fetch();
                        if ($d) $fullName = $d['full_name'];
                    } elseif ($user['role_name'] === 'Nurse') {
                        $nStmt = $db->prepare("SELECT CONCAT('Nurse ', first_name, ' ', last_name) AS full_name FROM nurses WHERE user_id = :uid");
                        $nStmt->execute([':uid' => $user['id']]);
                        $n = $nStmt->fetch();
                        if ($n) $fullName = $n['full_name'];
                    } elseif ($user['role_name'] === 'Patient') {
                        $pStmt = $db->prepare("SELECT CONCAT(first_name, ' ', last_name) AS full_name FROM patients WHERE user_id = :uid");
                        $pStmt->execute([':uid' => $user['id']]);
                        $p = $pStmt->fetch();
                        if ($p) $fullName = $p['full_name'];
                    }

                    login_user($user, $user['role_name'], $fullName);
                    header('Location: ../' . get_user_dashboard_url());
                    exit;
                }
            } else {
                $errorMessage = 'Invalid username or password credentials.';
            }
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            $errorMessage = 'System error occurred while verifying credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Staff & Patient Login - HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #0c4a6e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-lg);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 480px;
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, var(--primary-600), var(--teal-600));
            padding: 2.25rem 2rem;
            color: #ffffff;
            text-align: center;
        }
        .login-header-icon {
            width: 54px;
            height: 54px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            backdrop-filter: blur(4px);
        }
        .login-title {
            font-size: 1.5rem;
            color: #ffffff;
            font-weight: 800;
        }
        .login-subtitle {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.85);
            margin-top: 0.25rem;
        }
        .login-body {
            padding: 2rem;
        }
        .alert {
            padding: 0.875rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
        }
        .alert-danger { background: #ffe4e6; color: #be123c; border-left: 4px solid var(--rose-600); }
        .alert-success { background: #dcfce7; color: #15803d; border-left: 4px solid var(--emerald-600); }

        .demo-roles {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--slate-200);
        }
        .demo-roles-title {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--slate-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.75rem;
        }
        .demo-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
        }
        .demo-pill {
            background: var(--slate-100);
            border: 1px solid var(--slate-300);
            border-radius: var(--radius-full);
            padding: 0.3rem 0.65rem;
            font-size: 0.725rem;
            font-weight: 600;
            color: var(--slate-700);
            cursor: pointer;
            transition: var(--transition);
        }
        .demo-pill:hover {
            background: var(--primary-600);
            color: white;
            border-color: var(--primary-600);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="login-header-icon">+</div>
        <div class="login-title">ModernCare HMS</div>
        <div class="login-subtitle">Hospital Staff & Patient Authentication</div>
    </div>

    <div class="login-body">
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= escape($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= escape($successMessage) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="username">Username or Email Address</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="e.g. admin or dr.smith" required autofocus>
            </div>

            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.375rem;">
                    <label class="form-label" for="password" style="margin-bottom:0;">Password</label>
                    <a href="forgot-password.php" style="font-size:0.8rem; color:var(--primary-600); text-decoration:none; font-weight:600;">Forgot Password?</a>
                </div>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 1rem; margin-top: 0.5rem;">
                Sign In to Portal
            </button>
        </form>

        <div class="demo-roles">
            <div class="demo-roles-title">Demo Accounts Quick Autofill (Password: password123)</div>
            <div class="demo-pills">
                <button type="button" class="demo-pill" onclick="autofill('admin')">Admin</button>
                <button type="button" class="demo-pill" onclick="autofill('dr.smith')">Doctor</button>
                <button type="button" class="demo-pill" onclick="autofill('nurse.mary')">Nurse</button>
                <button type="button" class="demo-pill" onclick="autofill('receptionist')">Receptionist</button>
                <button type="button" class="demo-pill" onclick="autofill('pharmacist')">Pharmacist</button>
                <button type="button" class="demo-pill" onclick="autofill('labtech')">Lab Staff</button>
                <button type="button" class="demo-pill" onclick="autofill('patient.david')">Patient</button>
            </div>
        </div>
    </div>
</div>

<script>
function autofill(username) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = 'password123';
}
</script>

</body>
</html>
