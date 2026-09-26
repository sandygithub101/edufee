<?php
/**
 * Add New Student
 * Server-side validation, duplicate check, prepared statements
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$page_title = 'Add New Student';

$errors = [];
$courses = $pdo->query("SELECT id, name, code FROM courses WHERE status = 'Active' ORDER BY name ASC")->fetchAll();

// Default values
$enrollment_no = 'ENR-' . date('Y') . '-' . str_pad((string)rand(10, 999), 3, '0', STR_PAD_LEFT);
$name = '';
$father_name = '';
$mobile = '';
$email = '';
$gender = 'Male';
$address = '';
$course_id = 0;
$admission_date = date('Y-m-d');
$status = 'Active';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired or invalid. Please retry.';
    } else {
        $enrollment_no = trim($_POST['enrollment_no'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $father_name = trim($_POST['father_name'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $gender = trim($_POST['gender'] ?? 'Male');
        $address = trim($_POST['address'] ?? '');
        $course_id = (int)($_POST['course_id'] ?? 0);
        $admission_date = trim($_POST['admission_date'] ?? date('Y-m-d'));
        $status = trim($_POST['status'] ?? 'Active');

        // Server-Side Validations
        if (empty($enrollment_no)) {
            $errors[] = 'Enrollment number is mandatory.';
        } else {
            // Check uniqueness
            $chk = $pdo->prepare("SELECT COUNT(*) FROM students WHERE enrollment_no = ?");
            $chk->execute([$enrollment_no]);
            if ($chk->fetchColumn() > 0) {
                $errors[] = "Enrollment number '$enrollment_no' is already assigned to another student.";
            }
        }

        if (empty($name)) {
            $errors[] = 'Student name is required.';
        }
        if (empty($father_name)) {
            $errors[] = "Father's name is required.";
        }
        if (empty($mobile) || !preg_match('/^[0-9]{10,15}$/', $mobile)) {
            $errors[] = 'Valid 10-15 digit mobile number is required.';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if ($course_id <= 0) {
            $errors[] = 'Please select a valid course.';
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO students (enrollment_no, name, father_name, mobile, email, gender, address, course_id, admission_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $enrollment_no, $name, $father_name, $mobile, $email,
                    $gender, $address, $course_id, $admission_date, $status
                ]);

                $_SESSION['flash_success'] = "Student '$name' ($enrollment_no) registered successfully!";
                header('Location: students.php');
                exit;
            } catch (Exception $e) {
                $errors[] = 'Database insertion failed: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">Add New Student</h3>
        <p class="text-muted small mb-0">Fill in all mandatory profile and admission parameters</p>
    </div>
    <a href="students.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-1">
        <i class="bi bi-arrow-left"></i> Back to Directory
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <h6 class="alert-heading fw-bold mb-2"><i class="bi bi-exclamation-octagon-fill me-1"></i> Form Validation Errors:</h6>
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= sanitize($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card card-custom p-4">
    <form method="POST" action="student_add.php">
        <input type="hidden" name="csrf_token" value="<?= sanitize(generate_csrf_token()) ?>">

        <h6 class="text-primary fw-bold text-uppercase mb-3" style="font-size: 0.8rem; letter-spacing: 0.05em;">
            1. Academic &amp; Enrollment Identification
        </h6>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Enrollment Number <span class="text-danger">*</span></label>
                <input type="text" name="enrollment_no" class="form-control mono" value="<?= sanitize($enrollment_no) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Enrolling Course <span class="text-danger">*</span></label>
                <select name="course_id" class="form-select" required>
                    <option value="">Select Course...</option>
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
        </div>

        <h6 class="text-primary fw-bold text-uppercase mb-3" style="font-size: 0.8rem; letter-spacing: 0.05em;">
            2. Personal &amp; Family Information
        </h6>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Full Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" placeholder="e.g. Rahul Sharma" value="<?= sanitize($name) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Father's Name <span class="text-danger">*</span></label>
                <input type="text" name="father_name" class="form-control" placeholder="e.g. Rajendra Sharma" value="<?= sanitize($father_name) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Gender <span class="text-danger">*</span></label>
                <select name="gender" class="form-select">
                    <option value="Male" <?= ($gender === 'Male') ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= ($gender === 'Female') ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= ($gender === 'Other') ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
        </div>

        <h6 class="text-primary fw-bold text-uppercase mb-3" style="font-size: 0.8rem; letter-spacing: 0.05em;">
            3. Contact &amp; Residential Details
        </h6>
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Mobile Phone <span class="text-danger">*</span></label>
                <input type="tel" name="mobile" class="form-control" placeholder="10-digit number" value="<?= sanitize($mobile) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Email Address <span class="text-danger">*</span></label>
                <input type="email" name="email" class="form-control" placeholder="student@example.com" value="<?= sanitize($email) ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label fw-semibold text-secondary small">Student Status</label>
                <select name="status" class="form-select">
                    <option value="Active" <?= ($status === 'Active') ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= ($status === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold text-secondary small">Residential Address <span class="text-danger">*</span></label>
                <textarea name="address" rows="3" class="form-control" placeholder="Enter complete address, city, state and PIN code..." required><?= sanitize($address) ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="students.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary px-4 fw-semibold"><i class="bi bi-save me-1"></i> Register Student</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
