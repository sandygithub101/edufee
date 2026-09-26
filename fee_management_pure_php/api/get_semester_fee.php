<?php
/**
 * AJAX API: Get Detailed Fee Calculation for Student & Semester
 * Returns Total Fee, Paid Amount, Remaining Balance, and Status
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();

$studentId = (int)($_GET['student_id'] ?? 0);
$semesterId = (int)($_GET['semester_id'] ?? 0);

if ($studentId <= 0 || $semesterId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Both student_id and semester_id are required']);
    exit;
}

$stat = get_student_semester_fee_status($pdo, $studentId, $semesterId);

if (!$stat['found']) {
    echo json_encode(['success' => false, 'error' => 'Semester fee setup not found']);
    exit;
}

echo json_encode([
    'success'           => true,
    'semester_id'       => $stat['semester_id'],
    'semester_no'       => $stat['semester_no'],
    'course_name'       => $stat['course_name'],
    'course_code'       => $stat['course_code'],
    'fee'               => $stat['fee'],
    'paid'              => $stat['paid'],
    'remaining'         => $stat['remaining'],
    'status'            => $stat['status'],
    'payments_count'    => $stat['payments_count'],
    'formatted_fee'     => format_currency($stat['fee']),
    'formatted_paid'    => format_currency($stat['paid']),
    'formatted_remaining'=> format_currency($stat['remaining'])
]);
