<?php
/**
 * Session Authentication Guard
 * Protects admin pages from unauthorized access
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';

init_app_session();

// Check if authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || empty($_SESSION['admin_id'])) {
    // If AJAX request, return 401 JSON
    if (is_ajax_request()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized or session expired']);
        exit;
    }

    // Check if client storage has token before redirecting to login.php
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Authenticating...</title>
        <script>
            (function() {
                var token = null;
                try {
                    token = localStorage.getItem('edufee_auth_token') || sessionStorage.getItem('edufee_auth_token');
                } catch(e) {}
                if (token && token.length > 10) {
                    var sep = window.location.search ? '&' : '?';
                    window.location.replace(window.location.pathname + window.location.search + sep + 'auth_token=' + encodeURIComponent(token) + window.location.hash);
                } else {
                    window.location.replace('login.php');
                }
            })();
        </script>
        <noscript>
            <meta http-equiv="refresh" content="0; url=login.php">
        </noscript>
    </head>
    <body style="background:#0f172a;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">
        <div style="text-align:center;">
            <div style="display:inline-block;width:32px;height:32px;border:3px solid rgba(255,255,255,0.3);border-radius:50%;border-top-color:#3b82f6;animation:spin 1s ease-in-out infinite;"></div>
            <p style="margin-top:12px;font-size:14px;color:#94a3b8;">Verifying session...</p>
        </div>
        <style>@keyframes spin { to { transform: rotate(360deg); } }</style>
    </body>
    </html>
    <?php
    exit;
}
