<?php
/**
 * Student Comprehensive View & Semester Fee Ledger
 * Shows student profile, semester fees, paid amount, remaining fee, status, and payment history
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$student_id = (int)($_GET['id'] ?? 0);

if ($student_id <= 0) {
    header('Location: students.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, c.name AS course_name, c.code AS course_code, c.duration AS course_duration, c.total_semesters
    FROM students s 
    JOIN courses c ON s.course_id = c.id 
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['flash_error'] = 'Student record not found.';
    header('Location: students.php');
    exit;
}

$page_title = 'Student Profile: ' . $student['name'];

// Fetch all semesters configured for this course
$semStmt = $pdo->prepare("SELECT * FROM course_semesters WHERE course_id = ? ORDER BY semester_no ASC");
$semStmt->execute([$student['course_id']]);
$semesters = $semStmt->fetchAll();

// Calculate totals
$totalCourseFee = 0.0;
$totalCoursePaid = 0.0;
$semesterRows = [];

foreach ($semesters as $sem) {
    $stat = get_student_semester_fee_status($pdo, $student_id, (int)$sem['id']);
    $totalCourseFee += (float)$sem['fee'];
    $totalCoursePaid += $stat['paid'];
    $semesterRows[] = [
        'semester_id' => $sem['id'],
        'semester_no' => $sem['semester_no'],
        'fee'         => (float)$sem['fee'],
        'paid'        => $stat['paid'],
        'remaining'   => $stat['remaining'],
        'status'      => $stat['status'],
        'payments'    => $stat['payments_count']
    ];
}
$totalCourseBalance = max(0.0, $totalCourseFee - $totalCoursePaid);

// Fetch all payments made by this student
$payStmt = $pdo->prepare("
    SELECT fp.*, cs.semester_no 
    FROM fee_payments fp 
    JOIN course_semesters cs ON fp.semester_id = cs.id 
    WHERE fp.student_id = ? 
    ORDER BY fp.id DESC
");
$payStmt->execute([$student_id]);
$paymentHistory = $payStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1"><?= sanitize($student['name']) ?></h3>
        <p class="text-muted small mb-0">
            <span class="mono fw-semibold text-primary"><?= sanitize($student['enrollment_no']) ?></span> &bull; 
            <?= sanitize($student['course_name']) ?> (<?= sanitize($student['course_code']) ?>)
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="payments.php?action=new&student_id=<?= $student_id ?>" class="btn btn-success d-inline-flex align-items-center gap-2">
            <i class="bi bi-currency-rupee"></i> Collect Payment
        </a>
        <a href="student_edit.php?id=<?= $student_id ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i> Edit Profile
        </a>
        <a href="students.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Directory
        </a>
    </div>
</div>

<!-- Profile Info Card -->
<div class="row g-4 mb-4">
    <div class="col-12 col-lg-4">
        <div class="card card-custom h-100 p-4">
            <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 54px; height: 54px; font-size: 1.5rem; font-weight: 700;">
                    <?= strtoupper(substr($student['name'], 0, 1)) ?>
                </div>
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><?= sanitize($student['name']) ?></h5>
                    <div class="small text-muted mono"><?= sanitize($student['enrollment_no']) ?></div>
                    <div class="mt-1"><?= get_status_badge($student['status']) ?></div>
                </div>
            </div>

            <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small">
                <li><strong class="text-secondary">Father's Name:</strong> <?= sanitize($student['father_name']) ?></li>
                <li><strong class="text-secondary">Gender:</strong> <?= sanitize($student['gender']) ?></li>
                <li><strong class="text-secondary">Course:</strong> <?= sanitize($student['course_name']) ?> (<?= sanitize($student['course_code']) ?>)</li>
                <li><strong class="text-secondary">Duration:</strong> <?= sanitize($student['course_duration']) ?> (<?= (int)$student['total_semesters'] ?> Semesters)</li>
                <li><strong class="text-secondary">Mobile:</strong> <?= sanitize($student['mobile']) ?></li>
                <li><strong class="text-secondary">Email:</strong> <?= sanitize($student['email']) ?></li>
                <li><strong class="text-secondary">Admission:</strong> <?= sanitize($student['admission_date']) ?></li>
                <li><strong class="text-secondary">Address:</strong> <?= sanitize($student['address']) ?></li>
            </ul>
        </div>
    </div>

    <!-- Overall Fee Financial Summary -->
    <div class="col-12 col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-4">
                <div class="card card-custom p-3 text-center border-primary">
                    <span class="text-muted small fw-semibold text-uppercase">Total Course Fee</span>
                    <h4 class="fw-bold my-1 text-primary"><?= format_currency($totalCourseFee) ?></h4>
                    <span class="small text-muted"><?= count($semesters) ?> Semesters Configured</span>
                </div>
            </div>
            <div class="col-4">
                <div class="card card-custom p-3 text-center border-success">
                    <span class="text-muted small fw-semibold text-uppercase">Total Fee Paid</span>
                    <h4 class="fw-bold my-1 text-success"><?= format_currency($totalCoursePaid) ?></h4>
                    <span class="small text-muted"><?= count($paymentHistory) ?> Payments</span>
                </div>
            </div>
            <div class="col-4">
                <div class="card card-custom p-3 text-center border-danger">
                    <span class="text-muted small fw-semibold text-uppercase">Total Remaining Due</span>
                    <h4 class="fw-bold my-1 text-danger"><?= format_currency($totalCourseBalance) ?></h4>
                    <span class="small <?= $totalCourseBalance > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $totalCourseBalance > 0 ? 'Pending Balance' : 'Fully Cleared' ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Semester Fee Breakdown Table -->
        <div class="card card-custom">
            <div class="card-header bg-white border-bottom py-3">
                <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2 text-primary"></i>Semester-Wise Fee Status Ledger</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Semester</th>
                            <th>Total Fee</th>
                            <th>Paid Amount</th>
                            <th>Remaining Fee</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($semesterRows)): ?>
                            <tr><td colspan="6" class="text-center py-3 text-muted">No semester fees configured for this course yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($semesterRows as $sr): ?>
                                <tr>
                                    <td class="fw-semibold text-dark">Semester <?= (int)$sr['semester_no'] ?></td>
                                    <td><?= format_currency($sr['fee']) ?></td>
                                    <td class="text-success fw-bold"><?= format_currency($sr['paid']) ?></td>
                                    <td class="text-danger fw-bold"><?= format_currency($sr['remaining']) ?></td>
                                    <td><?= get_status_badge($sr['status']) ?></td>
                                    <td class="text-end">
                                        <?php if ($sr['remaining'] > 0): ?>
                                            <a href="payments.php?action=new&student_id=<?= $student_id ?>&semester_id=<?= (int)$sr['semester_id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-plus-circle me-1"></i> Pay
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Payment History for this Student -->
<div class="card card-custom">
    <div class="card-header bg-white border-bottom py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Receipt Transactions History</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Semester</th>
                    <th>Amount Paid</th>
                    <th>Mode</th>
                    <th>Transaction / Cheque No</th>
                    <th>Date</th>
                    <th>Remarks</th>
                    <th class="text-end">Print Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($paymentHistory)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No payments recorded for this student yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($paymentHistory as $ph): ?>
                        <tr>
                            <td><span class="mono fw-semibold text-primary"><?= sanitize($ph['receipt_no']) ?></span></td>
                            <td>Semester <?= (int)$ph['semester_no'] ?></td>
                            <td class="fw-bold text-success"><?= format_currency($ph['amount']) ?></td>
                            <td><span class="badge bg-light text-dark border"><?= sanitize($ph['payment_mode']) ?></span></td>
                            <td class="mono small text-muted"><?= sanitize($ph['transaction_no'] ?: '—') ?></td>
                            <td class="small text-muted"><?= sanitize($ph['payment_date']) ?></td>
                            <td class="small text-muted"><?= sanitize($ph['remarks'] ?: '—') ?></td>
                            <td class="text-end">
                                <a href="receipt.php?id=<?= (int)$ph['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="bi bi-printer me-1"></i> Receipt
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
