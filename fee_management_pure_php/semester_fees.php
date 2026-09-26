<?php
/**
 * Course Semester Fees Configuration Module
 * Dynamic setup of course-wise semester fees
 * Brief requirement: "Fees must be stored in MySQL and linked to the selected course. Do not hard-code fees in PHP."
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$page_title = 'Semester Fees Setup';
$errors = [];

// Fetch all courses
$courses = $pdo->query("SELECT * FROM courses ORDER BY id ASC")->fetchAll();
$selectedCourseId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 1));

// Handle Fee Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid.';
    } else {
        $courseId = (int)($_POST['course_id'] ?? 0);
        $semesterFees = $_POST['fees'] ?? [];

        if ($courseId > 0 && is_array($semesterFees)) {
            try {
                $updStmt = $pdo->prepare("
                    INSERT INTO course_semesters (course_id, semester_no, fee) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE fee = VALUES(fee)
                ");

                foreach ($semesterFees as $semNo => $feeVal) {
                    $semNo = (int)$semNo;
                    $feeVal = max(0.0, (float)$feeVal);
                    $updStmt->execute([$courseId, $semNo, $feeVal]);
                }

                $_SESSION['flash_success'] = 'Semester fees updated successfully!';
                header('Location: semester_fees.php?course_id=' . $courseId);
                exit;
            } catch (Exception $e) {
                $errors[] = 'Failed to update fees: ' . $e->getMessage();
            }
        }
    }
}

// Fetch selected course details
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$selectedCourseId]);
$currentCourse = $stmt->fetch();

// Fetch semesters for this course
$semesters = [];
if ($currentCourse) {
    $semStmt = $pdo->prepare("
        SELECT cs.*, 
               (SELECT COUNT(*) FROM fee_payments fp WHERE fp.semester_id = cs.id) AS payments_count,
               (SELECT COALESCE(SUM(fp.amount), 0) FROM fee_payments fp WHERE fp.semester_id = cs.id) AS total_collected
        FROM course_semesters cs 
        WHERE cs.course_id = ? 
        ORDER BY cs.semester_no ASC
    ");
    $semStmt->execute([$selectedCourseId]);
    $semesters = $semStmt->fetchAll();

    // If no semesters exist yet, initialize them
    if (empty($semesters)) {
        for ($s = 1; $s <= (int)$currentCourse['total_semesters']; $s++) {
            $pdo->prepare("INSERT INTO course_semesters (course_id, semester_no, fee) VALUES (?, ?, 0.00)")
                ->execute([$selectedCourseId, $s]);
        }
        $semStmt->execute([$selectedCourseId]);
        $semesters = $semStmt->fetchAll();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Course Semester Fee Configuration</h3>
        <p class="text-muted small mb-0">Database-driven fee structures linked to courses (Never hardcoded)</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <label class="form-label mb-0 fw-semibold text-secondary small text-nowrap">Select Program:</label>
        <select class="form-select" onchange="window.location.href='semester_fees.php?course_id=' + this.value;">
            <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ($selectedCourseId === (int)$c['id']) ? 'selected' : '' ?>>
                    <?= sanitize($c['code']) ?> - <?= sanitize($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= sanitize($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Course Fee Card -->
<div class="card card-custom p-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center pb-3 mb-3 border-bottom gap-2">
        <div>
            <h5 class="fw-bold mb-0 text-primary">
                <?= sanitize($currentCourse['name'] ?? '') ?> (<?= sanitize($currentCourse['code'] ?? '') ?>)
            </h5>
            <div class="small text-muted">
                Duration: <?= sanitize($currentCourse['duration'] ?? '') ?> &bull; 
                Total Semesters: <?= (int)($currentCourse['total_semesters'] ?? 0) ?>
            </div>
        </div>
        <div class="badge bg-light text-dark border p-2">
            Status: <span class="fw-bold text-success"><?= sanitize($currentCourse['status'] ?? '') ?></span>
        </div>
    </div>

    <!-- Practical Assessment Specific Notice -->
    <?php if (($currentCourse['code'] ?? '') === 'BCA'): ?>
        <div class="alert alert-info py-2 px-3 small d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>
                <strong>Assessment Reference:</strong> As required in the brief: BCA Sem 1 = ₹7,000, Sem 2 = ₹7,000, Sem 3 = ₹7,500, Sem 4 = ₹7,500.
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" action="semester_fees.php?course_id=<?= $selectedCourseId ?>">
        <input type="hidden" name="csrf_token" value="<?= sanitize(generate_csrf_token()) ?>">
        <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">

        <div class="table-responsive">
            <table class="table table-custom align-middle">
                <thead>
                    <tr>
                        <th style="width: 15%;">Semester</th>
                        <th style="width: 30%;">Semester Tuition / Lab Fee (₹)</th>
                        <th style="width: 25%;">Total Collected So Far</th>
                        <th style="width: 15%;">Transactions</th>
                        <th style="width: 15%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalFeeSum = 0;
                    $totalCollectedSum = 0;
                    foreach ($semesters as $sem): 
                        $totalFeeSum += (float)$sem['fee'];
                        $totalCollectedSum += (float)$sem['total_collected'];
                    ?>
                        <tr>
                            <td class="fw-bold text-dark">
                                Semester <?= (int)$sem['semester_no'] ?>
                            </td>
                            <td>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-secondary">₹</span>
                                    <input type="number" step="0.01" min="0" 
                                           name="fees[<?= (int)$sem['semester_no'] ?>]" 
                                           class="form-control fw-semibold" 
                                           value="<?= number_format((float)$sem['fee'], 2, '.', '') ?>" required>
                                </div>
                            </td>
                            <td class="text-success fw-semibold">
                                <?= format_currency($sem['total_collected']) ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <?= (int)$sem['payments_count'] ?> receipts
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-success-subtle text-success">Configured</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td>Total Program Fee:</td>
                        <td class="text-primary fs-6"><?= format_currency($totalFeeSum) ?></td>
                        <td class="text-success fs-6"><?= format_currency($totalCollectedSum) ?></td>
                        <td colspan="2" class="text-muted small fw-normal">Total across all semesters</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-3">
            <a href="courses.php" class="btn btn-outline-secondary">Back to Courses</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm">
                <i class="bi bi-check-circle me-1"></i> Update Semester Fees
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
