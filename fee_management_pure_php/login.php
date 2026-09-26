<?php
/**
 * Admin Login Page
 * Student & Semester-Wise Fee Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($submittedToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Both username and password are required.';
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT id, username, password, name, email, status FROM admins WHERE username = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    if ($admin['status'] !== 'Active') {
                        $error = 'Your admin account has been deactivated. Please contact support.';
                    } else {
                        // Secure session regeneration
                        session_regenerate_id(true);
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_id'] = (int)$admin['id'];
                        $_SESSION['admin_user'] = $admin['username'];
                        $_SESSION['admin_name'] = $admin['name'];
                        $_SESSION['admin_email'] = $admin['email'];

                        header('Location: index.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid username or password. Check credentials.';
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - EduFee Management</title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }
    </style>
</head>
<body>

<div class="login-card p-4 p-sm-5">
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-4 p-3 shadow-sm mb-3" style="width: 60px; height: 60px;">
            <i class="bi bi-mortarboard-fill fs-2"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">EduFee Portal</h4>
        <p class="text-muted small mb-0">Student &amp; Semester-Wise Fee Management</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?= sanitize($error) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= sanitize(generate_csrf_token()) ?>">

        <div class="mb-3">
            <label for="username" class="form-label fw-semibold text-secondary small">Username</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" value="<?= sanitize($username ?: 'admin') ?>" required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label fw-semibold text-secondary small">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" value="admin123" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
            <i class="bi bi-box-arrow-in-right"></i> Sign In to Dashboard
        </button>
    </form>

    <div class="mt-4 pt-3 border-top text-center">
        <div class="badge bg-light text-secondary border px-3 py-2 text-start w-100 font-monospace" style="font-size: 0.75rem;">
            <div><strong>Default Admin Credentials:</strong></div>
            <div>Username: <code>admin</code></div>
            <div>Password: <code>admin123</code></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
