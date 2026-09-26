<?php
/**
 * Edit Student Profile
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$page_title = 'Edit Student Details';

$student_id = (int)($_GET['id'] ?? 0);
if ($student_id <= 0) {
    header('Location: students.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['flash_error'] = 'Student record not found.';
    header('Location: students.php');
    exit;
}

$errors = [];
$courses = $pdo->query("SELECT id, name, code FROM courses ORDER BY name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please retry.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $father_name = trim($_POST['father_name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $address = trim($_POST['address'] ?? '');
        $course_id = (int)($_POST['course_id'] ?? 0);
        $admission_date = trim($_POST['admission_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Active');

        if (empty($name)) $errors[] = 'Name is required.';
        if (empty($father_name)) $errors[] = "Father's name is required.";
        if (empty($mobile) || !preg_match('/^[0-9]{10,15}$/', $mobile)) $errors[] = 'Valid mobile number is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email address is required.';
        if ($course_id <= 0) $errors[] = 'Valid course selection is required.';

        if (empty($errors)) {
            try {
                $upd = $pdo->prepare("
                    UPDATE students 
                    SET name = ?, father_name = ?, mobile = ?, email = ?, gender = ?, 
                        address = ?, course_id = ?, admission_date = ?, status = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    $name, $father_name, $mobile, $email, $gender,
                    $address, $course_id, $admission_date, $status, $student_id
                ]);

                $_SESSION['flash_success'] = "Student '$name' updated successfully!";
                header('Location: students.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Update failed: ' . $e->getMessage();
            }
        }
    }
} else {
    $name = $student['name'];
    $father_name = $student['father_name'];
    $mobile = $student['mobile'];
    $email = $student['email'];
    $gender = $student['gender'];
    $address = $student['address'];
    $course_id = (int)$student['course_id'];
    $admission_date = $student['admission_date'];
    $status = $student['status'];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Edit Student: <?= sanitize($student['name']) ?></h3>
        <p class="text-muted small mb-0">Enrollment No: <span class="mono fw-semibold text-primary"><?= sanitize($student['enrollment_no']) ?></span></p>
    </div>
    <a href="students.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
        <i class="bi bi-arrow-left"></i> Back to Directory
    </a>
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

<div class="card card-custom p-4">
    <form method="POST" action="student_edit.php?id=<?= $student_id ?>">
        <input type="hidden" name="csrf_token" value="<?= sanitize(generate_csrf_token()) ?>">

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Enrollment Number (Read Only)</label>
                <input type="text" class="form-control mono bg-light" value="<?= sanitize($student['enrollment_no']) ?>" readonly>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Course <span class="text-danger">*</span></label>
                <select name="course_id" class="form-select" required>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= ($course_id === (int)$c['id']) ? 'selected' : '' ?>>
                            <?= sanitize($c['code']) ?> - <?= sanitize($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Admission Date <span class="text-danger">*</span></label>
                <input type="date" name="admission_date" class="form-control" value="<?= sanitize($admission_date) ?>" required>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Student Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= sanitize($name) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Father's Name <span class="text-danger">*</span></label>
                <input type="text" name="father_name" class="form-control" value="<?= sanitize($father_name) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Gender <span class="text-danger">*</span></label>
                <select name="gender" class="form-select">
                    <option value="Male" <?= ($gender === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($gender === 'Female') ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= ($gender === 'Other') ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Mobile Phone <span class="text-danger">*</span></label>
                <input type="tel" name="mobile" class="form-control" value="<?= sanitize($mobile) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" value="<?= sanitize($email) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Status</label>
                <select name="status" class="form-select">
                    <option value="Active" <?= ($status === 'Active') ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= ($status === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                    <option value="Passed Out" <?= ($status === 'Passed Out') ? 'selected' : '' ?>>Passed Out</option>
                    <option value="Suspended" <?= ($status === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold text-secondary small">Address <span class="text-danger">*</span></label>
                <textarea name="address" rows="3" class="form-control" required><?= sanitize($address) ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="students.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-check2 me-1"></i> Update Student</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
