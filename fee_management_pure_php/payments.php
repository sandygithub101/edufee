<?php
/**
 * Fee Payment Processing & Transaction Management Module
 * Supports multiple payments for the same semester, automatic status calculation,
 * strictly rejects payment > remaining fee, stores payment mode, txn no, remarks.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$page_title = 'Fee Payments & Collections';
$errors = [];

// Pre-fill parameters if arriving from Student Profile or Action button
$prefillStudentId = (int)($_GET['student_id'] ?? 0);
$prefillSemesterId = (int)($_GET['semester_id'] ?? 0);
$showNewModal = isset($_GET['action']) && $_GET['action'] === 'new';

// Handle Add Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh the page.';
    } else {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $payment_mode = trim($_POST['payment_mode'] ?? 'Cash');
        $transaction_no = trim($_POST['transaction_no'] ?? '');
        $remarks = trim($_POST['remarks'] ?? '');

        // Validation 1: Student
        if ($student_id <= 0) {
            $errors[] = 'Please select a valid enrolled student.';
        }

        // Validation 2: Semester
        if ($semester_id <= 0) {
            $errors[] = 'Please select a valid semester.';
        }

        // Validation 3: Amount must be > 0
        if ($amount <= 0) {
            $errors[] = 'Payment amount must be greater than zero.';
        }

        // Validation 4: Strict Fee Logic - REJECT payment greater than remaining fee!
        if (empty($errors)) {
            $statusInfo = get_student_semester_fee_status($pdo, $student_id, $semester_id);
            if (!$statusInfo['found']) {
                $errors[] = 'Could not find fee configuration for this semester.';
            } else {
                $remainingFee = (float)$statusInfo['remaining'];
                if ($remainingFee <= 0) {
                    $errors[] = "This semester fee is already fully paid (Fee: " . format_currency($statusInfo['fee']) . "). No additional payment is due.";
                } elseif (round($amount, 2) > round($remainingFee, 2)) {
                    $errors[] = sprintf(
                        "Payment Rejected! Amount (%s) exceeds remaining fee (%s). Maximum payable balance is %s.",
                        format_currency($amount),
                        format_currency($remainingFee),
                        format_currency($remainingFee)
                    );
                }
            }
        }

        // Process Transaction
        if (empty($errors)) {
            try {
                $receipt_no = generate_receipt_no($pdo);
                $createdBy = $_SESSION['admin_id'] ?? null;

                $ins = $pdo->prepare("
                    INSERT INTO fee_payments 
                    (receipt_no, student_id, semester_id, amount, payment_date, payment_mode, transaction_no, remarks, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([
                    $receipt_no,
                    $student_id,
                    $semester_id,
                    $amount,
                    $payment_date,
                    $payment_mode,
                    empty($transaction_no) ? null : $transaction_no,
                    empty($remarks) ? null : $remarks,
                    $createdBy
                ]);

                $paymentId = (int)$pdo->lastInsertId();
                $_SESSION['flash_success'] = "Payment of " . format_currency($amount) . " received successfully! Receipt: $receipt_no";
                
                // Immediately redirect to official printable receipt
                header('Location: receipt.php?id=' . $paymentId);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Payment processing failed: ' . $e->getMessage();
            }
        }
    }
}

// Search and Filter Transactions
$search = trim($_GET['search'] ?? '');
$modeFilter = trim($_GET['payment_mode'] ?? '');

$sql = "
    SELECT fp.*, s.name AS student_name, s.enrollment_no, s.mobile, c.code AS course_code, c.name AS course_name, cs.semester_no, cs.fee AS semester_fee
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN course_semesters cs ON fp.semester_id = cs.id
    JOIN courses c ON cs.course_id = c.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (fp.receipt_no LIKE ? OR s.name LIKE ? OR s.enrollment_no LIKE ? OR fp.transaction_no LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}

if (!empty($modeFilter)) {
    $sql .= " AND fp.payment_mode = ?";
    $params[] = $modeFilter;
}

$sql .= " ORDER BY fp.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Fetch Active Students for Dropdown
$studentsList = $pdo->query("
    SELECT s.id, s.name, s.enrollment_no, s.course_id, c.code AS course_code 
    FROM students s 
    JOIN courses c ON s.course_id = c.id 
    WHERE s.status = 'Active' 
    ORDER BY s.name ASC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Fee Payment Transactions</h3>
        <p class="text-muted small mb-0">Record student payments, track installments, and issue official receipts</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
            <i class="bi bi-plus-circle-fill"></i> Collect New Payment
        </button>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h6 class="alert-heading fw-bold mb-1"><i class="bi bi-x-circle-fill me-1"></i> Payment Error</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= sanitize($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Search and Filter Bar -->
<div class="card card-custom p-3 mb-4">
    <form method="GET" action="payments.php" class="row g-2 align-items-center">
        <div class="col-12 col-md-6">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by Receipt No, Student Name, Enrollment, Txn No..." value="<?= sanitize($search) ?>">
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <select name="payment_mode" class="form-select">
                <option value="">All Payment Modes</option>
                <option value="Cash" <?= ($modeFilter === 'Cash') ? 'selected' : '' ?>>Cash</option>
                <option value="UPI" <?= ($modeFilter === 'UPI') ? 'selected' : '' ?>>UPI</option>
                <option value="Net Banking" <?= ($modeFilter === 'Net Banking') ? 'selected' : '' ?>>Net Banking</option>
                <option value="Cheque" <?= ($modeFilter === 'Cheque') ? 'selected' : '' ?>>Cheque</option>
                <option value="Debit/Credit Card" <?= ($modeFilter === 'Debit/Credit Card') ? 'selected' : '' ?>>Debit/Credit Card</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
            <?php if (!empty($search) || !empty($modeFilter)): ?>
                <a href="payments.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Transactions Table -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Student Details</th>
                    <th>Course &amp; Sem</th>
                    <th>Paid Amount</th>
                    <th>Payment Mode</th>
                    <th>Txn / Ref No</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt display-6 d-block text-secondary mb-2"></i>
                            No fee payments found matching the selected criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><span class="mono fw-bold text-primary"><?= sanitize($p['receipt_no']) ?></span></td>
                            <td>
                                <div class="fw-bold text-dark"><?= sanitize($p['student_name']) ?></div>
                                <div class="small text-muted mono"><?= sanitize($p['enrollment_no']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= sanitize($p['course_code']) ?> &bull; Sem <?= (int)$p['semester_no'] ?>
                                </span>
                            </td>
                            <td class="fw-bold text-success fs-6"><?= format_currency($p['amount']) ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="bi bi-credit-card me-1"></i><?= sanitize($p['payment_mode']) ?>
                                </span>
                            </td>
                            <td><span class="mono small text-muted"><?= sanitize($p['transaction_no'] ?: '—') ?></span></td>
                            <td class="small text-muted"><?= sanitize($p['payment_date']) ?></td>
                            <td class="text-end">
                                <a href="receipt.php?id=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Print Official Receipt">
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

<!-- Add Payment Modal with Live AJAX Dynamic Calculations & Strict Validations -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="payments.php" id="paymentForm" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= sanitize(generate_csrf_token()) ?>">
            <input type="hidden" name="action" value="record_payment">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet-fill text-primary me-2"></i>Collect Student Fee Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Live AJAX Dynamic Fee Status Card -->
                <div class="card bg-light border p-3 mb-4" id="feeCalculationBox" style="display: none;">
                    <div class="row text-center g-2">
                        <div class="col-4">
                            <span class="small text-muted text-uppercase fw-semibold d-block">Semester Fee</span>
                            <span class="fw-bold text-dark fs-5" id="dispTotalFee">₹0.00</span>
                        </div>
                        <div class="col-4">
                            <span class="small text-muted text-uppercase fw-semibold d-block">Already Paid</span>
                            <span class="fw-bold text-success fs-5" id="dispPaidFee">₹0.00</span>
                        </div>
                        <div class="col-4">
                            <span class="small text-muted text-uppercase fw-semibold d-block">Remaining Balance</span>
                            <span class="fw-bold text-danger fs-5" id="dispRemainingFee">₹0.00</span>
                        </div>
                    </div>
                    <div class="text-center mt-2" id="dispStatusBadge"></div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Select Enrolled Student <span class="text-danger">*</span></label>
                        <select name="student_id" id="modalStudentSelect" class="form-select" required onchange="handleStudentChange(this.value)">
                            <option value="">Choose Student...</option>
                            <?php foreach ($studentsList as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" data-course="<?= (int)$s['course_id'] ?>" <?= ($prefillStudentId === (int)$s['id']) ? 'selected' : '' ?>>
                                    <?= sanitize($s['name']) ?> (<?= sanitize($s['enrollment_no']) ?> - <?= sanitize($s['course_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Select Semester <span class="text-danger">*</span></label>
                        <select name="semester_id" id="modalSemesterSelect" class="form-select" required onchange="handleSemesterChange(this.value)">
                            <option value="">Select Student First...</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Payment Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light">₹</span>
                            <input type="number" step="0.01" min="1" name="amount" id="modalAmountInput" class="form-control fw-bold text-success fs-6" placeholder="0.00" required oninput="validatePaymentAmount()">
                        </div>
                        <div class="text-danger small mt-1 fw-semibold" id="amountWarning" style="display: none;">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Cannot exceed remaining balance!
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Payment Mode <span class="text-danger">*</span></label>
                        <select name="payment_mode" id="modalPaymentMode" class="form-select" required>
                            <option value="Cash">Cash</option>
                            <option value="UPI">UPI (GPay / PhonePe / Paytm)</option>
                            <option value="Net Banking">Net Banking (IMPS / NEFT)</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Debit/Credit Card">Debit / Credit Card</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Payment Date <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold text-secondary small">Transaction / Cheque / UTR No</label>
                        <input type="text" name="transaction_no" class="form-control mono" placeholder="Optional for Cash">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold text-secondary small">Remarks / Notes</label>
                        <input type="text" name="remarks" class="form-control" placeholder="e.g. Installment 1 / Cleared counter payment">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="btnSubmitPayment" class="btn btn-success px-4 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Confirm &amp; Print Receipt
                </button>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript for Dynamic Calculations & Validation -->
<script>
let currentRemainingFee = 0;

function handleStudentChange(studentId) {
    const semSelect = document.getElementById('modalSemesterSelect');
    const calcBox = document.getElementById('feeCalculationBox');
    calcBox.style.display = 'none';

    if (!studentId) {
        semSelect.innerHTML = '<option value="">Select Student First...</option>';
        return;
    }

    semSelect.innerHTML = '<option value="">Loading semesters...</option>';

    fetch('api/get_course_semesters.php?student_id=' + encodeURIComponent(studentId))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.semesters.length > 0) {
                let html = '<option value="">Choose Semester...</option>';
                data.semesters.forEach(s => {
                    const isPrefill = (<?= $prefillSemesterId ?> === parseInt(s.id));
                    html += `<option value="${s.id}" ${isPrefill ? 'selected' : ''}>Semester ${s.semester_no} (Fee: ₹${parseFloat(s.fee).toLocaleString()})</option>`;
                });
                semSelect.innerHTML = html;

                if (<?= $prefillSemesterId ?> > 0) {
                    handleSemesterChange(<?= $prefillSemesterId ?>);
                }
            } else {
                semSelect.innerHTML = '<option value="">No semesters configured</option>';
            }
        })
        .catch(err => {
            console.error('Error fetching semesters:', err);
            semSelect.innerHTML = '<option value="">Error loading semesters</option>';
        });
}

function handleSemesterChange(semesterId) {
    const studentId = document.getElementById('modalStudentSelect').value;
    const calcBox = document.getElementById('feeCalculationBox');

    if (!studentId || !semesterId) {
        calcBox.style.display = 'none';
        return;
    }

    fetch(`api/get_semester_fee.php?student_id=${encodeURIComponent(studentId)}&semester_id=${encodeURIComponent(semesterId)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                calcBox.style.display = 'block';
                document.getElementById('dispTotalFee').textContent = data.formatted_fee;
                document.getElementById('dispPaidFee').textContent = data.formatted_paid;
                document.getElementById('dispRemainingFee').textContent = data.formatted_remaining;

                currentRemainingFee = parseFloat(data.remaining);

                // Status Badge
                let badgeClass = 'bg-danger text-white';
                if (data.status === 'Paid') badgeClass = 'bg-success text-white';
                else if (data.status === 'Partial') badgeClass = 'bg-warning text-dark';
                
                document.getElementById('dispStatusBadge').innerHTML = `
                    <span class="badge ${badgeClass} px-3 py-1">Current Status: ${data.status}</span>
                `;

                // Auto populate amount with remaining fee if positive
                const amountInput = document.getElementById('modalAmountInput');
                if (currentRemainingFee > 0) {
                    amountInput.value = currentRemainingFee;
                    amountInput.max = currentRemainingFee;
                } else {
                    amountInput.value = '';
                }
                validatePaymentAmount();
            }
        })
        .catch(err => console.error('Error fetching fee info:', err));
}

function validatePaymentAmount() {
    const amountInput = document.getElementById('modalAmountInput');
    const warning = document.getElementById('amountWarning');
    const submitBtn = document.getElementById('btnSubmitPayment');
    const val = parseFloat(amountInput.value) || 0;

    if (currentRemainingFee <= 0) {
        warning.textContent = 'This semester fee is already fully paid!';
        warning.style.display = 'block';
        submitBtn.disabled = true;
        return;
    }

    if (val > currentRemainingFee) {
        warning.textContent = `Amount cannot exceed remaining balance (₹${currentRemainingFee.toLocaleString()})!`;
        warning.style.display = 'block';
        submitBtn.disabled = true;
    } else if (val <= 0) {
        warning.textContent = 'Amount must be greater than zero.';
        warning.style.display = 'block';
        submitBtn.disabled = true;
    } else {
        warning.style.display = 'none';
        submitBtn.disabled = false;
    }
}

// Auto open modal if action=new requested
<?php if ($showNewModal || $prefillStudentId > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
    <?php if ($prefillStudentId > 0): ?>
        handleStudentChange(<?= $prefillStudentId ?>);
    <?php endif; ?>
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
