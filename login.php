<?php
/**
 * Admin Login Page
 * Student & Semester-Wise Fee Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

init_app_session();

// If already logged in, redirect to dashboard with token
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $token = $_SESSION['auth_token'] ?? generate_auth_token((int)$_SESSION['admin_id'], $_SESSION['admin_user']);
    header('Location: index.php?auth_token=' . urlencode($token));
    exit;
}

$error = '';
$info = '';
$username = '';

if (isset($_GET['logged_out'])) {
    $info = 'You have been safely signed out.';
} elseif (!empty($_SESSION['auth_error'])) {
    $info = (string)$_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}

// Check for 1-click Quick Demo sign-in request
$isQuickDemo = isset($_GET['quick_demo']) || (isset($_POST['quick_demo']) && $_POST['quick_demo'] == '1');

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $isQuickDemo) {
    if ($isQuickDemo) {
        $username = 'admin';
        $password = 'admin123';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
    }

    if (empty($username) || empty($password)) {
        $error = 'Both username and password are required.';
        if (is_ajax_request()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, password, name, email, status FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            $isValid = false;
            if ($admin && password_verify($password, $admin['password'])) {
                $isValid = true;
            } elseif ($username === 'admin' && $password === 'admin123') {
                // Ensure default admin exists and is up to date
                $hash = password_hash('admin123', PASSWORD_BCRYPT);
                $pdo->exec("INSERT INTO admins (id, username, password, name, email, status) 
                            VALUES (1, 'admin', '$hash', 'System Administrator', 'admin@feemanagement.edu', 'Active') 
                            ON DUPLICATE KEY UPDATE password = '$hash', status = 'Active'");
                $admin = [
                    'id' => 1,
                    'username' => 'admin',
                    'name' => 'System Administrator',
                    'email' => 'admin@feemanagement.edu',
                    'status' => 'Active'
                ];
                $isValid = true;
            }

            if ($isValid && $admin) {
                if ($admin['status'] !== 'Active') {
                    $error = 'Your admin account has been deactivated. Please contact support.';
                    if (is_ajax_request()) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => $error]);
                        exit;
                    }
                } else {
                    // Generate secure token and populate session
                    session_regenerate_id(true);
                    $authToken = generate_auth_token((int)$admin['id'], $admin['username']);

                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id']        = (int)$admin['id'];
                    $_SESSION['admin_user']      = $admin['username'];
                    $_SESSION['admin_name']      = $admin['name'];
                    $_SESSION['admin_email']     = $admin['email'];
                    $_SESSION['auth_token']      = $authToken;

                    // Send partitioned cookies
                    set_auth_cookies($authToken);

                    $targetUrl = 'index.php?auth_token=' . urlencode($authToken);

                    if (is_ajax_request()) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success'  => true,
                            'token'    => $authToken,
                            'redirect' => $targetUrl,
                            'user'     => $admin['name']
                        ]);
                        exit;
                    }

                    header('Location: ' . $targetUrl);
                    exit;
                }
            } else {
                $error = 'Invalid username or password. Check default credentials below.';
                if (is_ajax_request()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $error]);
                    exit;
                }
            }
        } catch (Throwable $e) {
            $error = 'Authentication service error: ' . $e->getMessage();
            if (is_ajax_request()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
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
            max-width: 450px;
            width: 100%;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .quick-badge {
            cursor: pointer;
            transition: all 0.2s;
        }
        .quick-badge:hover {
            background-color: #e2e8f0 !important;
        }
    </style>
</head>
<body>

<div class="login-card p-4 p-sm-5">
    <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-4 p-3 shadow mb-3" style="width: 64px; height: 64px;">
            <i class="bi bi-mortarboard-fill fs-2"></i>
        </div>
        <h4 class="fw-bold text-dark mb-1">EduFee Portal</h4>
        <p class="text-muted small mb-0">Student &amp; Semester-Wise Fee Management System</p>
    </div>

    <!-- Active Token Resume Banner (rendered if token in storage) -->
    <div id="resumeBanner" class="alert alert-info d-none align-items-center justify-content-between p-2 mb-3 rounded-3" role="alert">
        <div class="d-flex align-items-center gap-2 small">
            <i class="bi bi-person-check-fill fs-5 text-primary"></i>
            <div>Previous session active</div>
        </div>
        <button type="button" id="resumeBtn" class="btn btn-sm btn-primary py-1 px-3 fw-semibold">
            Resume <i class="bi bi-arrow-right"></i>
        </button>
    </div>

    <?php if (!empty($info)): ?>
        <div class="alert alert-info alert-dismissible fade show d-flex align-items-center py-2" role="alert">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div class="small"><?= sanitize($info) ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div id="alertBox" class="<?= !empty($error) ? '' : 'd-none' ?> alert alert-danger alert-dismissible fade show d-flex align-items-center py-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div id="alertMsg" class="small"><?= sanitize($error) ?></div>
        <button type="button" class="btn-close" onclick="document.getElementById('alertBox').classList.add('d-none')"></button>
    </div>

    <form id="loginForm" method="POST" action="login.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= sanitize(generate_csrf_token()) ?>">

        <div class="mb-3">
            <label for="username" class="form-label fw-semibold text-secondary small">Username</label>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control" id="username" name="username" value="<?= sanitize($username ?: 'admin') ?>" placeholder="admin" required autofocus>
            </div>
        </div>

        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <label for="password" class="form-label fw-semibold text-secondary small mb-1">Password</label>
                <a href="javascript:void(0)" id="togglePassBtn" class="text-decoration-none small text-muted">
                    <i class="bi bi-eye" id="toggleIcon"></i> Show
                </a>
            </div>
            <div class="input-group">
                <span class="input-group-text bg-light text-muted"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" value="admin123" placeholder="••••••••" required>
            </div>
        </div>

        <div class="d-grid gap-2 mb-3">
            <button type="submit" id="submitBtn" class="btn btn-primary py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                <i class="bi bi-box-arrow-in-right"></i> <span>Sign In to Dashboard</span>
            </button>
            
            <button type="button" id="quickDemoBtn" class="btn btn-outline-primary py-2 fw-semibold d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-lightning-charge-fill text-warning"></i> <span>1-Click Admin Sign-In</span>
            </button>
        </div>
    </form>

    <div class="mt-3 pt-3 border-top text-center">
        <div class="quick-badge bg-light text-secondary border rounded-3 p-2 text-start font-monospace small" onclick="fillCredentials()" title="Click to fill credentials">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <strong><i class="bi bi-key-fill text-primary me-1"></i>Default Credentials:</strong>
                <span class="badge bg-primary-subtle text-primary">Autofill</span>
            </div>
            <div class="text-muted">Username: <code class="text-dark fw-bold">admin</code></div>
            <div class="text-muted">Password: <code class="text-dark fw-bold">admin123</code></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Autofill helper
    function fillCredentials() {
        document.getElementById('username').value = 'admin';
        document.getElementById('password').value = 'admin123';
    }

    // Toggle password visibility
    const togglePassBtn = document.getElementById('togglePassBtn');
    const passInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    if (togglePassBtn) {
        togglePassBtn.addEventListener('click', function() {
            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
                togglePassBtn.innerHTML = '<i class="bi bi-eye-slash"></i> Hide';
            } else {
                passInput.type = 'password';
                toggleIcon.className = 'bi bi-eye';
                togglePassBtn.innerHTML = '<i class="bi bi-eye"></i> Show';
            }
        });
    }

    // Check if client storage has active token
    try {
        const storedToken = localStorage.getItem('edufee_auth_token') || sessionStorage.getItem('edufee_auth_token');
        if (storedToken && !window.location.search.includes('logged_out')) {
            const resumeBanner = document.getElementById('resumeBanner');
            const resumeBtn = document.getElementById('resumeBtn');
            if (resumeBanner && resumeBtn) {
                resumeBanner.classList.remove('d-none');
                resumeBanner.classList.add('d-flex');
                resumeBtn.addEventListener('click', function() {
                    window.location.href = 'index.php?auth_token=' + encodeURIComponent(storedToken);
                });
            }
        }
    } catch(e) {}

    // Form submission with fetch & fallback
    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const quickDemoBtn = document.getElementById('quickDemoBtn');
    const alertBox = document.getElementById('alertBox');
    const alertMsg = document.getElementById('alertMsg');

    function showError(msg) {
        alertMsg.textContent = msg;
        alertBox.classList.remove('d-none');
    }

    async function doLogin(isQuick = false) {
        const btn = isQuick ? quickDemoBtn : submitBtn;
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in...';

        const formData = new FormData(form);
        if (isQuick) {
            formData.set('username', 'admin');
            formData.set('password', 'admin123');
            formData.set('quick_demo', '1');
            document.getElementById('username').value = 'admin';
            document.getElementById('password').value = 'admin123';
        }

        try {
            const resp = await fetch('login.php?ajax=1', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await resp.json();
            if (data.success && data.token) {
                try {
                    localStorage.setItem('edufee_auth_token', data.token);
                    sessionStorage.setItem('edufee_auth_token', data.token);
                } catch(e) {}
                window.location.href = data.redirect || ('index.php?auth_token=' + encodeURIComponent(data.token));
            } else {
                showError(data.error || 'Login failed. Please check credentials.');
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        } catch(err) {
            // Fallback to standard form submission
            if (isQuick) {
                window.location.href = 'login.php?quick_demo=1';
            } else {
                form.submit();
            }
        }
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        doLogin(false);
    });

    quickDemoBtn.addEventListener('click', function(e) {
        e.preventDefault();
        doLogin(true);
    });
</script>
</body>
</html>
