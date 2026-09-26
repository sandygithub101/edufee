<?php
/**
 * Utility Functions & Business Logic Engine
 * Student & Semester-Wise Fee Management System
 */

declare(strict_types=1);

// Application secret key for token signing and authentication
if (!defined('APP_AUTH_SECRET')) {
    define('APP_AUTH_SECRET', getenv('APP_AUTH_SECRET') ?: 'edufee_master_secret_key_auth_v1_2026_secured');
}

/**
 * Detects if the current request is an AJAX / JSON request.
 */
function is_ajax_request(): bool
{
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
        || (isset($_GET['ajax']) && $_GET['ajax'] == '1')
        || (isset($_POST['ajax']) && $_POST['ajax'] == '1');
}

/**
 * Generates a signed tamper-proof token for cookie-free / iframe-resilient auth.
 */
function generate_auth_token(int $adminId, string $username): string
{
    $expires = time() + (86400 * 7); // 7 days validity
    $payload = "$adminId|$username|$expires";
    $signature = hash_hmac('sha256', $payload, APP_AUTH_SECRET);
    return base64_encode("$payload|$signature");
}

/**
 * Validates a signed auth token and returns admin data from database.
 */
function verify_auth_token(?string $token): ?array
{
    if (empty($token)) {
        return null;
    }

    $decoded = base64_decode($token, true);
    if ($decoded === false) {
        return null;
    }

    $parts = explode('|', $decoded);
    if (count($parts) !== 4) {
        return null;
    }

    [$adminId, $username, $expires, $signature] = $parts;

    // Check expiration
    if ((int)$expires < time()) {
        return null;
    }

    // Verify HMAC signature
    $expected = hash_hmac('sha256', "$adminId|$username|$expires", APP_AUTH_SECRET);
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    // Verify against DB that admin still exists and is Active
    try {
        if (function_exists('getDBConnection')) {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, username, name, email, status FROM admins WHERE id = ? AND username = ?");
            $stmt->execute([(int)$adminId, $username]);
            $admin = $stmt->fetch();
            if ($admin && $admin['status'] === 'Active') {
                return $admin;
            }
        }
    } catch (Throwable $e) {
        // Fallback for default admin
        if ((int)$adminId === 1 && $username === 'admin') {
            return [
                'id' => 1,
                'username' => 'admin',
                'name' => 'System Administrator',
                'email' => 'admin@feemanagement.edu',
                'status' => 'Active'
            ];
        }
    }

    return null;
}

/**
 * Detects if the connection is HTTPS or behind an SSL-terminating proxy (AI Studio / Cloud Run).
 */
function is_https_request(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        || (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && (int)$_SERVER['HTTP_X_FORWARDED_PORT'] === 443)
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
}

/**
 * Sends partitioned cookie headers for iframe cross-site persistence.
 */
function set_auth_cookies(string $authToken): void
{
    if (headers_sent()) {
        return;
    }

    $expireTime = time() + (86400 * 7);
    $expireGmt = gmdate('D, d M Y H:i:s T', $expireTime);
    $isHttps = is_https_request();
    $cookieFlags = $isHttps ? "; SameSite=None; Secure; Partitioned; HttpOnly" : "; SameSite=Lax; HttpOnly";

    // Set auth cookie
    header("Set-Cookie: edufee_auth_token=" . urlencode($authToken) . "; Expires=$expireGmt; Max-Age=604800; Path=/$cookieFlags", false);

    // Reinforce session cookie if active
    if (session_id()) {
        header("Set-Cookie: " . session_name() . "=" . session_id() . "; Expires=$expireGmt; Max-Age=604800; Path=/$cookieFlags", false);
    }
}

/**
 * Configures session cookies for cross-origin iframe compatibility and starts the session.
 */
function init_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = is_https_request();

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.use_trans_sid', '0');

    if ($isHttps) {
        ini_set('session.cookie_samesite', 'None');
        ini_set('session.cookie_secure', '1');
        session_set_cookie_params([
            'lifetime' => 86400 * 7,
            'path'     => '/; SameSite=None; Secure; Partitioned',
            'domain'   => '',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ]);
    } else {
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', '0');
        session_set_cookie_params([
            'lifetime' => 86400 * 7,
            'path'     => '/',
            'domain'   => '',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    @session_start();

    // Reinforce session cookie on HTTP response
    if (session_id() && !headers_sent()) {
        $expireGmt = gmdate('D, d M Y H:i:s T', time() + (86400 * 7));
        $cookieFlags = $isHttps ? "; SameSite=None; Secure; Partitioned; HttpOnly" : "; SameSite=Lax; HttpOnly";
        header("Set-Cookie: " . session_name() . "=" . session_id() . "; Expires=$expireGmt; Max-Age=604800; Path=/$cookieFlags", false);
    }

    // Check if session is already authenticated
    if (!empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_id'])) {
        return;
    }

    // Check for auth_token in query, POST, headers, or custom cookie
    $token = $_GET['auth_token'] 
          ?? $_POST['auth_token'] 
          ?? $_COOKIE['edufee_auth_token'] 
          ?? $_SERVER['HTTP_X_AUTH_TOKEN'] 
          ?? null;

    if (empty($token) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = $m[1];
        }
    }

    if (!empty($token)) {
        $admin = verify_auth_token($token);
        if ($admin) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = (int)$admin['id'];
            $_SESSION['admin_user']      = $admin['username'];
            $_SESSION['admin_name']      = $admin['name'];
            $_SESSION['admin_email']     = $admin['email'];
            $_SESSION['auth_token']      = $token;
        }
    }
}

// Automatically initialize session on include
init_app_session();

/**
 * Escapes HTML output to prevent Cross-Site Scripting (XSS).
 */
function sanitize(?string $data): string
{
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Formats numeric currency with Indian Rupee symbol (₹).
 */
function format_currency(float|int|string $amount): string
{
    $num = (float)$amount;
    return '₹' . number_format($num, 2);
}

/**
 * Returns HTML badge element corresponding to fee status.
 */
function get_status_badge(string $status): string
{
    $status = ucfirst(strtolower(trim($status)));
    return match ($status) {
        'Paid'    => '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Paid</span>',
        'Partial' => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-pie-chart-fill me-1"></i>Partial</span>',
        'Pending' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-clock-fill me-1"></i>Pending</span>',
        'Active'  => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Active</span>',
        'Inactive'=> '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">Inactive</span>',
        default   => '<span class="badge bg-light text-dark border px-2 py-1">' . sanitize($status) . '</span>'
    };
}

/**
 * Generates and stores a CSRF protection token.
 * Returns session token or signed token fallback.
 */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the incoming CSRF token.
 * Accepts active session CSRF token, or HMAC-signed fallback token.
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($token)) {
        return false;
    }
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }
    // Also accept valid HMAC stateless token for current or previous 24h
    $statelessToday = hash_hmac('sha256', date('Y-m-d') . APP_AUTH_SECRET, APP_AUTH_SECRET);
    $statelessYesterday = hash_hmac('sha256', date('Y-m-d', strtotime('-1 day')) . APP_AUTH_SECRET, APP_AUTH_SECRET);
    if (hash_equals($statelessToday, $token) || hash_equals($statelessYesterday, $token)) {
        return true;
    }
    return false;
}

/**
 * Calculates semester fee, paid amount, remaining fee, and status for a student.
 * Mandatory logic:
 * - Support multiple payments for the same semester.
 * - Calculate Paid, Remaining and Status automatically.
 * - Status: Pending / Partial / Paid.
 * - Reject payment greater than remaining fee.
 */
function get_student_semester_fee_status(PDO $pdo, int $student_id, int $semester_id): array
{
    // Fetch semester fee
    $stmt = $pdo->prepare("SELECT cs.id, cs.semester_no, cs.fee, c.name AS course_name, c.code AS course_code 
                           FROM course_semesters cs 
                           JOIN courses c ON cs.course_id = c.id 
                           WHERE cs.id = ?");
    $stmt->execute([$semester_id]);
    $sem = $stmt->fetch();

    if (!$sem) {
        return [
            'found'         => false,
            'fee'           => 0.0,
            'paid'          => 0.0,
            'remaining'     => 0.0,
            'status'        => 'Pending',
            'semester_no'   => 0,
            'payments_count'=> 0
        ];
    }

    $fee = (float)$sem['fee'];

    // Calculate total paid across all payments for this student and semester
    $payStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_paid, COUNT(id) AS payments_count 
                             FROM fee_payments 
                             WHERE student_id = ? AND semester_id = ?");
    $payStmt->execute([$student_id, $semester_id]);
    $payData = $payStmt->fetch();

    $paid = (float)($payData['total_paid'] ?? 0);
    $remaining = round(max(0.0, $fee - $paid), 2);
    $paymentsCount = (int)($payData['payments_count'] ?? 0);

    if ($paid <= 0) {
        $status = 'Pending';
    } elseif ($paid < $fee) {
        $status = 'Partial';
    } else {
        $status = 'Paid';
        $remaining = 0.0;
    }

    return [
        'found'          => true,
        'semester_id'    => $sem['id'],
        'semester_no'    => (int)$sem['semester_no'],
        'course_name'    => $sem['course_name'],
        'course_code'    => $sem['course_code'],
        'fee'            => $fee,
        'paid'           => $paid,
        'remaining'      => $remaining,
        'status'         => $status,
        'payments_count' => $paymentsCount
    ];
}

/**
 * Converts a numeric amount to Indian Rupee Words for official receipts.
 */
function number_to_words_indian(float|int $amount): string
{
    $number = floor($amount);
    $decimal = round(($amount - $number) * 100);

    $words = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen', 20 => 'Twenty',
        30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety'
    ];

    if ($number == 0) {
        return 'Zero Rupees Only';
    }

    $crores = floor($number / 10000000);
    $number %= 10000000;
    $lakhs = floor($number / 100000);
    $number %= 100000;
    $thousands = floor($number / 1000);
    $number %= 1000;
    $hundreds = floor($number / 100);
    $remainder = $number % 100;

    $convertPart = function($num) use ($words) {
        if ($num < 20) {
            return $words[$num] ?? '';
        }
        $ten = floor($num / 10) * 10;
        $unit = $num % 10;
        return trim(($words[$ten] ?? '') . ' ' . ($words[$unit] ?? ''));
    };

    $result = '';
    if ($crores > 0) {
        $result .= $convertPart($crores) . ' Crore ';
    }
    if ($lakhs > 0) {
        $result .= $convertPart($lakhs) . ' Lakh ';
    }
    if ($thousands > 0) {
        $result .= $convertPart($thousands) . ' Thousand ';
    }
    if ($hundreds > 0) {
        $result .= $convertPart($hundreds) . ' Hundred ';
    }
    if ($remainder > 0) {
        $result .= $convertPart($remainder) . ' ';
    }

    $result = trim($result) . ' Rupees';
    if ($decimal > 0) {
        $result .= ' and ' . $convertPart((int)$decimal) . ' Paise';
    }
    return $result . ' Only';
}

/**
 * Generates unique formatted receipt numbers (e.g. REC-2024-0006).
 */
function generate_receipt_no(PDO $pdo): string
{
    $year = date('Y');
    try {
        $stmt = $pdo->query("SELECT MAX(id) AS max_id FROM fee_payments");
        $row = $stmt->fetch();
        $nextId = (int)($row['max_id'] ?? 0) + 1;
    } catch (Exception $e) {
        $nextId = rand(100, 999);
    }
    return sprintf('REC-%s-%04d', $year, $nextId);
}
