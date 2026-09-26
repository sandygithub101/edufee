<?php
/**
 * Reports Module: Student-Wise Fee Report & Pending-Fee Report
 * Comprehensive reporting, filtering, and print/export readiness
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$page_title = 'Fee Reports & Audits';

$activeTab = $_GET['tab'] ?? 'student_wise';
$courseFilter = (int)($_GET['course_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$courses = $pdo->query("SELECT id, name, code FROM courses ORDER BY name ASC")->fetchAll();

// 1. Student-Wise Fee Ledger Data
$sql = "
    SELECT s.id AS student_id, s.name, s.enrollment_no, s.mobile, s.status AS student_status,
           c.id AS course_id, c.code AS course_code, c.name AS course_name,
           (SELECT COALESCE(SUM(cs.fee), 0) FROM course_semesters cs WHERE cs.course_id = s.course_id) AS total_fee,
           (SELECT COALESCE(SUM(fp.amount), 0) FROM fee_payments fp WHERE fp.student_id = s.id) AS total_paid,
           (SELECT MAX(fp.payment_date) FROM fee_payments fp WHERE fp.student_id = s.id) AS last_payment_date
    FROM students s
    JOIN courses c ON s.course_id = c.id
    WHERE 1=1
";
$params = [];

if ($courseFilter > 0) {
    $sql .= " AND s.course_id = ?";
    $params[] = $courseFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND s.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY c.name ASC, s.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allStudentLedgers = $stmt->fetchAll();

// Process calculations
$reportStudents = [];
$pendingStudents = [];

$sumTotalFee = 0.0;
$sumTotalPaid = 0.0;
$sumTotalPending = 0.0;

foreach ($allStudentLedgers as $row) {
    $totalFee = (float)$row['total_fee'];
    $totalPaid = (float)$row['total_paid'];
    $balance = max(0.0, $totalFee - $totalPaid);

    if ($totalPaid <= 0) {
        $feeStatus = 'Pending';
    } elseif ($totalPaid < $totalFee) {
        $feeStatus = 'Partial';
    } else {
        $feeStatus = 'Paid';
        $balance = 0.0;
    }

    $row['calculated_fee'] = $totalFee;
    $row['calculated_paid'] = $totalPaid;
    $row['calculated_balance'] = $balance;
    $row['fee_status'] = $feeStatus;

    $sumTotalFee += $totalFee;
    $sumTotalPaid += $totalPaid;
    $sumTotalPending += $balance;

    $reportStudents[] = $row;

    if ($balance > 0) {
        $pendingStudents[] = $row;
    }
}

// Pending report sorted by highest balance
usort($pendingStudents, fn($a, $b) => $b['calculated_balance'] <=> $a['calculated_balance']);

// Export CSV handler
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    $filename = ($activeTab === 'pending') ? 'pending_fees_report_' . date('Ymd_His') . '.csv' : 'student_fee_report_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Enrollment No', 'Student Name', 'Course Code', 'Course Name', 'Mobile', 'Total Fee', 'Paid Amount', 'Pending Balance', 'Fee Status', 'Last Payment Date']);
    $dataToExport = ($activeTab === 'pending') ? $pendingStudents : $reportStudents;
    foreach ($dataToExport as $s) {
        fputcsv($output, [
            $s['enrollment_no'],
            $s['name'],
            $s['course_code'],
            $s['course_name'],
            $s['mobile'],
            $s['calculated_fee'],
            $s['calculated_paid'],
            $s['calculated_balance'],
            $s['fee_status'],
            $s['last_payment_date'] ?? 'None'
        ]);
    }
    fclose($output);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 no-print">
    <div>
        <h3 class="fw-bold mb-1">Fee Management Reports</h3>
        <p class="text-muted small mb-0">Detailed student-wise ledger breakdowns and pending fee audit tracking</p>
    </div>
    <div class="d-flex gap-2">
        <a href="reports.php?action=export_csv&tab=<?= urlencode($activeTab) ?>&course_id=<?= $courseFilter ?>&status=<?= urlencode($statusFilter) ?>" class="btn btn-outline-success d-inline-flex align-items-center gap-2 bg-white">
            <i class="bi bi-file-earmark-excel"></i> Export CSV
        </a>
        <button type="button" class="btn btn-outline-primary d-inline-flex align-items-center gap-2 bg-white" onclick="window.print()">
            <i class="bi bi-printer"></i> Print Report
        </button>
    </div>
</div>

<!-- Tabs & Filters -->
<div class="card card-custom mb-4 no-print">
    <div class="card-body p-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <ul class="nav nav-pills" id="reportTabs">
                <li class="nav-item">
                    <a class="nav-link <?= ($activeTab === 'student_wise') ? 'active' : '' ?>" href="reports.php?tab=student_wise&course_id=<?= $courseFilter ?>&status=<?= urlencode($statusFilter) ?>">
                        <i class="bi bi-person-lines-fill me-1"></i> Student-Wise Fee Report
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($activeTab === 'pending') ? 'active' : '' ?>" href="reports.php?tab=pending&course_id=<?= $courseFilter ?>&status=<?= urlencode($statusFilter) ?>">
                        <i class="bi bi-exclamation-triangle-fill me-1 text-danger"></i> Pending Fee Report
                        <span class="badge bg-danger ms-1"><?= count($pendingStudents) ?></span>
                    </a>
                </li>
            </ul>

            <form method="GET" action="reports.php" class="d-flex gap-2">
                <input type="hidden" name="tab" value="<?= sanitize($activeTab) ?>">
                <select name="course_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All Courses</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($courseFilter === (int)$c['id']) ? 'selected' : '' ?>>
                            <?= sanitize($c['code']) ?> - <?= sanitize($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($courseFilter > 0): ?>
                    <a href="reports.php?tab=<?= sanitize($activeTab) ?>" class="btn btn-sm btn-outline-secondary" title="Clear Filter">
                        <i class="bi bi-x-lg"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card card-custom p-3 border-primary">
            <span class="text-muted small fw-semibold text-uppercase">Total Curriculum Fees</span>
            <h4 class="fw-bold my-1 text-primary"><?= format_currency($sumTotalFee) ?></h4>
            <span class="text-secondary small">Across <?= count($reportStudents) ?> Students</span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card card-custom p-3 border-success">
            <span class="text-muted small fw-semibold text-uppercase">Total Fees Collected</span>
            <h4 class="fw-bold my-1 text-success"><?= format_currency($sumTotalPaid) ?></h4>
            <span class="text-success small fw-medium">Realized to Date</span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card card-custom p-3 border-danger">
            <span class="text-muted small fw-semibold text-uppercase">Total Pending Dues</span>
            <h4 class="fw-bold my-1 text-danger"><?= format_currency($sumTotalPending) ?></h4>
            <span class="text-danger small fw-medium"><?= count($pendingStudents) ?> Students with Pending Dues</span>
        </div>
    </div>
</div>

<?php if ($activeTab === 'student_wise'): ?>
    <!-- Student-Wise Fee Report Table -->
    <div class="card card-custom">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold"><i class="bi bi-file-earmark-spreadsheet me-2 text-primary"></i>Student-Wise Fee Ledger Summary</h6>
            <span class="text-muted small">Generated on <?= date('d M Y, h:i A') ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>Enrollment No</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Total Fee (₹)</th>
                        <th>Total Paid (₹)</th>
                        <th>Balance Due (₹)</th>
                        <th>Status</th>
                        <th class="text-end no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportStudents)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No student records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportStudents as $st): ?>
                            <tr>
                                <td><span class="mono fw-semibold text-primary"><?= sanitize($st['enrollment_no']) ?></span></td>
                                <td class="fw-bold text-dark"><?= sanitize($st['name']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= sanitize($st['course_code']) ?></span></td>
                                <td class="fw-semibold"><?= format_currency($st['calculated_fee']) ?></td>
                                <td class="text-success fw-bold"><?= format_currency($st['calculated_paid']) ?></td>
                                <td class="<?= $st['calculated_balance'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= format_currency($st['calculated_balance']) ?>
                                </td>
                                <td><?= get_status_badge($st['fee_status']) ?></td>
                                <td class="text-end no-print">
                                    <a href="student_view.php?id=<?= (int)$st['student_id'] ?>" class="btn btn-sm btn-outline-info" title="View Full Ledger">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($st['calculated_balance'] > 0): ?>
                                        <a href="payments.php?action=new&student_id=<?= (int)$st['student_id'] ?>" class="btn btn-sm btn-success" title="Collect Fee">
                                            <i class="bi bi-currency-rupee"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">Grand Totals:</td>
                        <td class="text-primary"><?= format_currency($sumTotalFee) ?></td>
                        <td class="text-success"><?= format_currency($sumTotalPaid) ?></td>
                        <td class="text-danger"><?= format_currency($sumTotalPending) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- Pending Fee Report Table -->
    <div class="card card-custom">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Outstanding Pending Fees Audit</h6>
            <span class="text-muted small"><?= count($pendingStudents) ?> Students with Pending Fees</span>
        </div>
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th>Enrollment No</th>
                        <th>Student Name</th>
                        <th>Contact Mobile</th>
                        <th>Course</th>
                        <th>Total Fee (₹)</th>
                        <th>Paid So Far (₹)</th>
                        <th>Pending Balance (₹)</th>
                        <th>Last Payment</th>
                        <th class="text-end no-print">Collect Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pendingStudents)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-success fw-bold"><i class="bi bi-check-circle-fill me-2 fs-5"></i>All student fees are fully cleared! No pending dues.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pendingStudents as $pst): ?>
                            <tr>
                                <td><span class="mono fw-semibold text-primary"><?= sanitize($pst['enrollment_no']) ?></span></td>
                                <td class="fw-bold text-dark"><?= sanitize($pst['name']) ?></td>
                                <td><i class="bi bi-telephone text-muted me-1"></i><?= sanitize($pst['mobile']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= sanitize($pst['course_code']) ?></span></td>
                                <td><?= format_currency($pst['calculated_fee']) ?></td>
                                <td class="text-success"><?= format_currency($pst['calculated_paid']) ?></td>
                                <td class="text-danger fw-bold fs-6"><?= format_currency($pst['calculated_balance']) ?></td>
                                <td class="small text-muted"><?= sanitize($pst['last_payment_date'] ?: 'No Payments Yet') ?></td>
                                <td class="text-end no-print">
                                    <a href="payments.php?action=new&student_id=<?= (int)$pst['student_id'] ?>" class="btn btn-sm btn-danger shadow-sm">
                                        <i class="bi bi-currency-rupee me-1"></i> Collect Fee
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="6" class="text-end">Total Outstanding Dues:</td>
                        <td class="text-danger fs-6"><?= format_currency($sumTotalPending) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
