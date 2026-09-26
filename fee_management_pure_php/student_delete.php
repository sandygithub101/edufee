<?php
/**
 * Student Deletion Handler
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$student_id = (int)($_GET['id'] ?? 0);

if ($student_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT name, enrollment_no FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $stu = $stmt->fetch();

        if ($stu) {
            $del = $pdo->prepare("DELETE FROM students WHERE id = ?");
            $del->execute([$student_id]);
            $_SESSION['flash_success'] = "Student '{$stu['name']}' ({$stu['enrollment_no']}) deleted successfully.";
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = 'Could not delete student: ' . $e->getMessage();
    }
}

header('Location: students.php');
exit;
