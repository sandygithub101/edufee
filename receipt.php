<?php
/**
 * Official Print-Friendly Student Fee Receipt
 * Displays student info, semester fees, payment mode, transaction number,
 * remaining balance, amount in words, and authorized signature.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$payment_id = (int)($_GET['id'] ?? 0);

if ($payment_id <= 0) {
    header('Location: payments.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT fp.*, 
           s.name AS student_name, s.enrollment_no, s.father_name, s.mobile, s.email, s.address,
           c.name AS course_name, c.code AS course_code, 
           cs.semester_no, cs.fee AS semester_fee,
           a.name AS admin_name
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN course_semesters cs ON fp.semester_id = cs.id
    JOIN courses c ON cs.course_id = c.id
    LEFT JOIN admins a ON fp.created_by = a.id
    WHERE fp.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['flash_error'] = 'Receipt transaction not found.';
    header('Location: payments.php');
    exit;
}

$page_title = 'Receipt - ' . $payment['receipt_no'];

// Calculate previously paid and remaining balance at time of this receipt
$historyStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) 
    FROM fee_payments 
    WHERE student_id = ? AND semester_id = ? AND id <= ?
");
$historyStmt->execute([$payment['student_id'], $payment['semester_id'], $payment_id]);
$totalPaidUpToThis = (float)$historyStmt->fetchColumn();

$semFee = (float)$payment['semester_fee'];
$prevPaid = max(0.0, $totalPaidUpToThis - (float)$payment['amount']);
$remainingBalance = max(0.0, $semFee - $totalPaidUpToThis);

$amountInWords = number_to_words_indian((float)$payment['amount']);

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h3 class="fw-bold mb-1">Fee Payment Receipt</h3>
        <p class="text-muted small mb-0">Official receipt for records and student acknowledgment</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Print Receipt
        </button>
        <a href="payments.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> All Payments
        </a>
    </div>
</div>

<!-- Printable Receipt Container -->
<div class="card card-custom p-4 p-md-5 mx-auto receipt-container" style="max-width: 820px; background: #ffffff;">
    <!-- College Header -->
    <div class="border-bottom pb-4 mb-4 text-center">
        <div class="d-flex justify-content-center align-items-center gap-3 mb-2">
            <div class="bg-primary text-white rounded-3 p-2 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                <i class="bi bi-mortarboard-fill fs-2"></i>
            </div>
            <div class="text-start">
                <h3 class="fw-bold text-dark mb-0 tracking-tight">INSTITUTE OF ADVANCED STUDIES</h3>
                <div class="text-muted small">Affiliated with State Technical University &bull; Approved by AICTE</div>
            </div>
        </div>
        <div class="badge bg-primary text-white px-3 py-1 fs-6 text-uppercase letter-spacing mt-2">
            Student Fee Payment Receipt
        </div>
    </div>

    <!-- Receipt Meta -->
    <div class="row g-3 mb-4 pb-3 border-bottom small">
        <div class="col-6">
            <div><span class="text-muted">Receipt Number:</span> <strong class="mono text-primary fs-6"><?= sanitize($payment['receipt_no']) ?></strong></div>
            <div><span class="text-muted">Transaction Date:</span> <strong><?= sanitize($payment['payment_date']) ?></strong></div>
            <div><span class="text-muted">Issued By:</span> <strong><?= sanitize($payment['admin_name'] ?: 'Accounts Department') ?></strong></div>
        </div>
        <div class="col-6 text-end">
            <div><span class="text-muted">Academic Program:</span> <strong><?= sanitize($payment['course_name']) ?> (<?= sanitize($payment['course_code']) ?>)</strong></div>
            <div><span class="text-muted">Semester:</span> <strong class="fs-6 text-dark">Semester <?= (int)$payment['semester_no'] ?></strong></div>
            <div><span class="text-muted">Payment Mode:</span> <strong class="badge bg-light text-dark border"><?= sanitize($payment['payment_mode']) ?></strong></div>
        </div>
    </div>

    <!-- Student Details -->
    <div class="card bg-light border-0 p-3 mb-4">
        <h6 class="fw-bold text-secondary text-uppercase mb-2" style="font-size: 0.75rem; letter-spacing: 0.05em;">Student Particulars</h6>
        <div class="row g-2 small">
            <div class="col-sm-6">
                <span class="text-muted">Student Name:</span> <strong class="text-dark fs-6"><?= sanitize($payment['student_name']) ?></strong>
            </div>
            <div class="col-sm-6">
                <span class="text-muted">Enrollment No:</span> <strong class="mono text-primary fs-6"><?= sanitize($payment['enrollment_no']) ?></strong>
            </div>
            <div class="col-sm-6">
                <span class="text-muted">Father's Name:</span> <strong class="text-dark"><?= sanitize($payment['father_name']) ?></strong>
            </div>
            <div class="col-sm-6">
                <span class="text-muted">Contact Mobile:</span> <strong class="text-dark"><?= sanitize($payment['mobile']) ?></strong>
            </div>
        </div>
    </div>

    <!-- Fee Financial Breakdown Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fee Description</th>
                    <th class="text-end">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="fw-semibold text-dark">Tuition &amp; Semester Fee (Sem <?= (int)$payment['semester_no'] ?>)</div>
                        <div class="text-muted small"><?= sanitize($payment['course_name']) ?></div>
                    </td>
                    <td class="text-end fw-semibold"><?= format_currency($semFee) ?></td>
                </tr>
                <tr>
                    <td class="text-muted">Previously Paid Amount</td>
                    <td class="text-end text-muted"><?= format_currency($prevPaid) ?></td>
                </tr>
                <tr class="table-success bg-opacity-25">
                    <td>
                        <div class="fw-bold text-success fs-6"><i class="bi bi-check-circle-fill me-1"></i> Current Amount Received</div>
                        <?php if (!empty($payment['transaction_no'])): ?>
                            <div class="small text-muted mono">Txn / Ref No: <?= sanitize($payment['transaction_no']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end fw-bold text-success fs-5"><?= format_currency($payment['amount']) ?></td>
                </tr>
                <tr class="<?= $remainingBalance > 0 ? 'table-warning' : 'table-light' ?>">
                    <td>
                        <span class="fw-bold <?= $remainingBalance > 0 ? 'text-danger' : 'text-success' ?>">Remaining Balance for Semester <?= (int)$payment['semester_no'] ?></span>
                    </td>
                    <td class="text-end fw-bold <?= $remainingBalance > 0 ? 'text-danger fs-6' : 'text-success' ?>">
                        <?= $remainingBalance > 0 ? format_currency($remainingBalance) : '₹0.00 (Nil - Paid in Full)' ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Amount in Words -->
    <div class="p-3 bg-light rounded-2 border mb-4">
        <span class="small text-muted text-uppercase fw-semibold d-block">Amount in Words:</span>
        <strong class="text-dark fs-6"><?= sanitize($amountInWords) ?></strong>
    </div>

    <?php if (!empty($payment['remarks'])): ?>
        <div class="mb-4 small text-muted">
            <strong>Remarks / Notes:</strong> <?= sanitize($payment['remarks']) ?>
        </div>
    <?php endif; ?>

    <!-- Signature & Stamp Section -->
    <div class="row pt-5 mt-4 text-center">
        <div class="col-6">
            <div class="border-top pt-2 mx-auto" style="width: 200px;">
                <span class="small fw-semibold text-secondary">Student / Depositor Signature</span>
            </div>
        </div>
        <div class="col-6">
            <div class="border-top pt-2 mx-auto" style="width: 200px;">
                <span class="small fw-semibold text-secondary">Authorized Cashier / Stamp</span>
                <div class="text-muted" style="font-size: 0.7rem;">EduFee Accounts Dept</div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5 pt-3 border-top text-muted" style="font-size: 0.75rem;">
        * This is an official computer-generated fee acknowledgment receipt. Keep for future academic clearance and examinations.
    </div>
</div>

<style>
@media print {
    body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .receipt-container {
        box-shadow: none !important;
        border: none !important;
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
