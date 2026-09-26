<?php
/**
 * Admin Dashboard
 * Modules: Total Students, Courses, Collected Fees, Pending Fees, Recent Payments
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$page_title = 'Dashboard';

// 1. Total Students
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();

// 2. Active Courses
$totalCourses = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'Active'")->fetchColumn();

// 3. Total Collected Fees
$totalCollected = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fee_payments")->fetchColumn();

// 4. Total Pending Fees calculation:
// For all active students, calculate their course's total semester fees minus all payments made
$stmt = $pdo->query("
    SELECT s.id AS student_id, s.course_id,
           (SELECT COALESCE(SUM(cs.fee), 0) FROM course_semesters cs WHERE cs.course_id = s.course_id) AS total_fee,
           (SELECT COALESCE(SUM(fp.amount), 0) FROM fee_payments fp WHERE fp.student_id = s.id) AS total_paid
    FROM students s
    WHERE s.status = 'Active'
");
$studentLedgers = $stmt->fetchAll();

$totalPending = 0.0;
$studentsWithPending = [];

foreach ($studentLedgers as $row) {
    $due = max(0.0, (float)$row['total_fee'] - (float)$row['total_paid']);
    $totalPending += $due;
}

// 5. Recent 5 Fee Payments with student and semester info
$recentPaymentsStmt = $pdo->query("
    SELECT fp.*, s.name AS student_name, s.enrollment_no, c.code AS course_code, cs.semester_no 
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN course_semesters cs ON fp.semester_id = cs.id
    JOIN courses c ON cs.course_id = c.id
    ORDER BY fp.id DESC
    LIMIT 6
");
$recentPayments = $recentPaymentsStmt->fetchAll();

// 6. Top Students with Pending Dues
$pendingStudentsStmt = $pdo->query("
    SELECT s.id, s.enrollment_no, s.name, s.mobile, c.code AS course_code,
           (SELECT COALESCE(SUM(cs.fee), 0) FROM course_semesters cs WHERE cs.course_id = s.course_id) AS total_course_fee,
           (SELECT COALESCE(SUM(fp.amount), 0) FROM fee_payments fp WHERE fp.student_id = s.id) AS total_paid
    FROM students s
    JOIN courses c ON s.course_id = c.id
    WHERE s.status = 'Active'
    ORDER BY (total_course_fee - total_paid) DESC
    LIMIT 5
");
$topPending = [];
while ($st = $pendingStudentsStmt->fetch()) {
    $bal = max(0.0, (float)$st['total_course_fee'] - (float)$st['total_paid']);
    if ($bal > 0) {
        $st['balance'] = $bal;
        $topPending[] = $st;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Administrative Overview</h3>
        <p class="text-muted small mb-0">Student &amp; Semester-Wise Fee Collection Metrics</p>
    </div>
    <div class="d-flex gap-2">
        <a href="payments.php?action=new" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-plus-circle"></i> Collect Fee
        </a>
        <a href="student_add.php" class="btn btn-outline-primary d-inline-flex align-items-center gap-2 bg-white">
            <i class="bi bi-person-plus"></i> Add Student
        </a>
    </div>
</div>

<!-- 4 Key Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-custom h-100 p-3" style="border-left: 4px solid #2563eb;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Students</span>
                    <h3 class="fw-bold my-1 text-dark"><?= number_format($totalStudents) ?></h3>
                    <span class="text-success small fw-medium"><i class="bi bi-check-circle me-1"></i>Active Enrolled</span>
                </div>
                <div class="bg-primary-subtle text-primary p-3 rounded-3">
                    <i class="bi bi-people fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-custom h-100 p-3" style="border-left: 4px solid #0891b2;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Active Courses</span>
                    <h3 class="fw-bold my-1 text-dark"><?= number_format($totalCourses) ?></h3>
                    <span class="text-secondary small fw-medium"><i class="bi bi-journal-bookmark me-1"></i>Curriculum Programs</span>
                </div>
                <div class="bg-info-subtle text-info p-3 rounded-3">
                    <i class="bi bi-book fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-custom h-100 p-3" style="border-left: 4px solid #16a34a;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Total Collected</span>
                    <h3 class="fw-bold my-1 text-success"><?= format_currency($totalCollected) ?></h3>
                    <span class="text-success small fw-medium"><i class="bi bi-arrow-up-right me-1"></i>Realized Revenue</span>
                </div>
                <div class="bg-success-subtle text-success p-3 rounded-3">
                    <i class="bi bi-currency-rupee fs-4"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="card card-custom h-100 p-3" style="border-left: 4px solid #dc2626;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-semibold text-uppercase">Pending Balance</span>
                    <h3 class="fw-bold my-1 text-danger"><?= format_currency($totalPending) ?></h3>
                    <span class="text-danger small fw-medium"><i class="bi bi-exclamation-circle me-1"></i>Outstanding Dues</span>
                </div>
                <div class="bg-danger-subtle text-danger p-3 rounded-3">
                    <i class="bi bi-hourglass-split fs-4"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Recent Fee Payments -->
    <div class="col-12 col-xl-8">
        <div class="card card-custom h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Fee Payments</h6>
                <a href="payments.php" class="btn btn-sm btn-link text-decoration-none">View All Payments &rarr;</a>
            </div>
            <div class="table-responsive">
                <table class="table table-custom table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Student</th>
                            <th>Course / Sem</th>
                            <th>Amount</th>
                            <th>Mode</th>
                            <th>Date</th>
                            <th class="text-end">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No fee payments recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $pay): ?>
                                <tr>
                                    <td><span class="mono fw-semibold text-primary"><?= sanitize($pay['receipt_no']) ?></span></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= sanitize($pay['student_name']) ?></div>
                                        <div class="small text-muted mono"><?= sanitize($pay['enrollment_no']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= sanitize($pay['course_code']) ?> Sem <?= (int)$pay['semester_no'] ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-success"><?= format_currency($pay['amount']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-secondary small">
                                            <?= sanitize($pay['payment_mode']) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted"><?= sanitize($pay['payment_date']) ?></td>
                                    <td class="text-end">
                                        <a href="receipt.php?id=<?= (int)$pay['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Print Receipt">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Outstanding Dues Quick View -->
    <div class="col-12 col-xl-4">
        <div class="card card-custom h-100">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Highest Outstanding</h6>
                <a href="reports.php?tab=pending" class="btn btn-sm btn-link text-decoration-none">Full Report &rarr;</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if (empty($topPending)): ?>
                        <li class="list-group-item text-center py-4 text-muted">All students have cleared their fees!</li>
                    <?php else: ?>
                        <?php foreach ($topPending as $pStudent): ?>
                            <li class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark"><?= sanitize($pStudent['name']) ?></div>
                                    <div class="small text-muted mono"><?= sanitize($pStudent['enrollment_no']) ?> &bull; <?= sanitize($pStudent['course_code']) ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-danger"><?= format_currency($pStudent['balance']) ?></div>
                                    <a href="payments.php?action=new&student_id=<?= (int)$pStudent['id'] ?>" class="btn btn-xs btn-outline-danger py-0 px-2 small mt-1" style="font-size: 0.75rem;">
                                        Collect
                                    </a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
