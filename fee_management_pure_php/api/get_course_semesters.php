<?php
/**
 * AJAX API: Get Semesters for a Student or Course
 * Returns JSON array of semesters
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Auth check for API
if (empty($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();

$studentId = (int)($_GET['student_id'] ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);

if ($studentId > 0) {
    $stmt = $pdo->prepare("SELECT course_id FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $courseId = (int)$stmt->fetchColumn();
}

if ($courseId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Valid student or course required']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, course_id, semester_no, fee 
    FROM course_semesters 
    WHERE course_id = ? 
    ORDER BY semester_no ASC
");
$stmt->execute([$courseId]);
$semesters = $stmt->fetchAll();

echo json_encode([
    'success'   => true,
    'course_id' => $courseId,
    'semesters' => $semesters
]);
