<?php
/**
 * Password Reset Request Interface
 * Hospital Management System (HMS)
 */

require_once __DIR__ . '/../includes/auth.php';

$message = '';
$messageType = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'danger';
    } else {
        $message = 'If an account exists for ' . escape($email) . ', password reset instructions have been dispatched. For demo accounts, contact system administrator.';
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ModernCare HMS</title>
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
        .card {
            max-width: 440px;
            width: 100%;
            background: white;
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
        }
        .title { font-size: 1.5rem; font-weight: 800; color: var(--slate-900); }
        .subtitle { font-size: 0.9rem; color: var(--slate-500); margin-top: 0.25rem; margin-bottom: 1.5rem; }
    </style>
</head>
<body>

<div class="card">
    <h1 class="title">Reset Password</h1>
    <p class="subtitle">Enter your registered email address to receive password recovery instructions.</p>

    <?php if ($message): ?>
        <div class="badge badge-<?= $messageType ?>" style="display:block; padding:0.875rem 1rem; border-radius: var(--radius-sm); margin-bottom:1.5rem; font-size:0.875rem; text-transform:none;">
            <?= escape($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="forgot-password.php">
        <div class="form-group">
            <label class="form-label" for="email">Registered Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="name@hospital.com" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">
            Send Password Reset Link
        </button>
    </form>

    <div style="text-align: center; margin-top: 1.5rem;">
        <a href="login.php" style="color: var(--primary-600); text-decoration: none; font-weight: 600; font-size: 0.875rem;">
            &larr; Back to Login
        </a>
    </div>
</div>

</body>
</html>
