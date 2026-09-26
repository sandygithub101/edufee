<?php
/**
 * Admin Logout Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

init_app_session();

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
    header("Set-Cookie: " . session_name() . "=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; Path=/; SameSite=None; Secure; Partitioned; HttpOnly", false);
    header("Set-Cookie: edufee_auth_token=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; Path=/; SameSite=None; Secure; Partitioned; HttpOnly", false);
}

session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Signing out...</title>
    <script>
        try {
            localStorage.removeItem('edufee_auth_token');
            sessionStorage.removeItem('edufee_auth_token');
        } catch(e) {}
        window.location.replace('login.php?logged_out=1');
    </script>
</head>
<body style="background:#0f172a;color:#fff;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;">
    <div style="text-align:center;">
        <p>Signing out...</p>
        <p><a href="login.php?logged_out=1" style="color:#60a5fa;">Click here to return to login</a></p>
    </div>
</body>
</html>
