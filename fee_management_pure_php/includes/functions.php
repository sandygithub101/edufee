<?php
/**
 * Utility Functions & Business Logic Engine
 * Student & Semester-Wise Fee Management System
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
 */
function verify_csrf_token(?string $token): bool
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
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
