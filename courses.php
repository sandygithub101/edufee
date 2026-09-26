<?php
/**
 * Course Management Module
 * CRUD: Add, Edit, Delete, Duration, Total Semesters
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$page_title = 'Courses Management';
$errors = [];

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid.';
    } else {
        $action = trim($_POST['action'] ?? '');

        if ($action === 'create' || $action === 'update') {
            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $duration = trim($_POST['duration'] ?? '');
            $total_semesters = (int)($_POST['total_semesters'] ?? 6);
            $status = trim($_POST['status'] ?? 'Active');
            $course_id = (int)($_POST['course_id'] ?? 0);

            if (empty($name)) $errors[] = 'Course name is required.';
            if (empty($code)) $errors[] = 'Course code is required.';
            if (empty($duration)) $errors[] = 'Course duration is required.';
            if ($total_semesters < 1 || $total_semesters > 12) $errors[] = 'Semesters must be between 1 and 12.';

            if (empty($errors)) {
                try {
                    if ($action === 'create') {
                        // Check code duplicate
                        $chk = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE code = ?");
                        $chk->execute([$code]);
                        if ($chk->fetchColumn() > 0) {
                            $errors[] = "Course code '$code' already exists.";
                        } else {
                            $ins = $pdo->prepare("INSERT INTO courses (name, code, duration, total_semesters, status) VALUES (?, ?, ?, ?, ?)");
                            $ins->execute([$name, $code, $duration, $total_semesters, $status]);
                            $newId = (int)$pdo->lastInsertId();

                            // Automatically seed course_semesters placeholders so admin can set fees
                            $semIns = $pdo->prepare("INSERT INTO course_semesters (course_id, semester_no, fee) VALUES (?, ?, 0.00)");
                            for ($s = 1; $s <= $total_semesters; $s++) {
                                $semIns->execute([$newId, $s]);
                            }

                            $_SESSION['flash_success'] = "Course '$name' ($code) created with $total_semesters semesters!";
                            header('Location: courses.php');
                            exit;
                        }
                    } elseif ($action === 'update' && $course_id > 0) {
                        $upd = $pdo->prepare("UPDATE courses SET name = ?, code = ?, duration = ?, total_semesters = ?, status = ? WHERE id = ?");
                        $upd->execute([$name, $code, $duration, $total_semesters, $status, $course_id]);

                        // Ensure semester records exist up to total_semesters
                        for ($s = 1; $s <= $total_semesters; $s++) {
                            $chkSem = $pdo->prepare("SELECT COUNT(*) FROM course_semesters WHERE course_id = ? AND semester_no = ?");
                            $chkSem->execute([$course_id, $s]);
                            if ($chkSem->fetchColumn() == 0) {
                                $pdo->prepare("INSERT INTO course_semesters (course_id, semester_no, fee) VALUES (?, ?, 0.00)")
                                    ->execute([$course_id, $s]);
                            }
                        }

                        $_SESSION['flash_success'] = "Course '$name' updated successfully.";
                        header('Location: courses.php');
                        exit;
                    }
                } catch (Exception $e) {
                    $errors[] = 'Operation failed: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
            $delId = (int)($_POST['course_id'] ?? 0);
            try {
                // Check if students exist for this course
                $stuCount = (int)$pdo->prepare("SELECT COUNT(*) FROM students WHERE course_id = ?")->execute([$delId]) ? $pdo->query("SELECT COUNT(*) FROM students WHERE course_id = $delId")->fetchColumn() : 0;
                if ($stuCount > 0) {
                    $errors[] = "Cannot delete course: $stuCount student(s) are actively enrolled in it.";
                } else {
                    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$delId]);
                    $_SESSION['flash_success'] = "Course deleted successfully.";
                    header('Location: courses.php');
                    exit;
                }
            } catch (Exception $e) {
                $errors[] = 'Delete failed: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all courses with enrolled students count and semester fee count
$courses = $pdo->query("
    SELECT c.*, 
           (SELECT COUNT(*) FROM students s WHERE s.course_id = c.id) AS student_count,
           (SELECT COALESCE(SUM(cs.fee), 0) FROM course_semesters cs WHERE cs.course_id = c.id) AS total_curriculum_fee
    FROM courses c
    ORDER BY c.id ASC
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Courses &amp; Academic Programs</h3>
        <p class="text-muted small mb-0">Configure academic degrees, durations, semester structures, and fees</p>
    </div>
    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="prepareAddCourse()">
        <i class="bi bi-plus-circle"></i> Add New Course
    </button>
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

<!-- Courses Table -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Course Name</th>
                    <th>Duration</th>
                    <th>Total Semesters</th>
                    <th>Total Course Fee</th>
                    <th>Enrolled Students</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($courses)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No courses configured yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td><span class="mono fw-bold text-primary"><?= sanitize($c['code']) ?></span></td>
                            <td class="fw-bold text-dark"><?= sanitize($c['name']) ?></td>
                            <td><?= sanitize($c['duration']) ?></td>
                            <td><?= (int)$c['total_semesters'] ?> Semesters</td>
                            <td class="fw-semibold text-dark"><?= format_currency($c['total_curriculum_fee']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-people me-1"></i><?= (int)$c['student_count'] ?> Students
                                </span>
                            </td>
                            <td><?= get_status_badge($c['status']) ?></td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="semester_fees.php?course_id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Setup Semester Fees">
                                        <i class="bi bi-currency-rupee me-1"></i> Fees
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit Course" onclick='prepareEditCourse(<?= json_encode($c) ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="courses.php" style="display:inline;" onsubmit="return confirm('Delete course <?= sanitize($c['name']) ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= sanitize(generate_csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="course_id" value="<?= (int)$c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Course" <?= ($c['student_count'] > 0) ? 'disabled title="Cannot delete course with enrolled students"' : '' ?>>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Course Modal -->
<div class="modal fade" id="courseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="courses.php" class="modal-content">
            <input type="hidden" name="csrf_token" value="<?= sanitize(generate_csrf_token()) ?>">
            <input type="hidden" name="action" id="modalAction" value="create">
            <input type="hidden" name="course_id" id="modalCourseId" value="0">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalTitle">Add New Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary small">Course Code <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="courseCode" class="form-control mono text-uppercase" placeholder="e.g. BCA, MCA, B.Tech" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary small">Course Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="courseName" class="form-control" placeholder="e.g. Bachelor of Computer Applications" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold text-secondary small">Duration <span class="text-danger">*</span></label>
                        <input type="text" name="duration" id="courseDuration" class="form-control" placeholder="e.g. 3 Years" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold text-secondary small">Total Semesters <span class="text-danger">*</span></label>
                        <input type="number" name="total_semesters" id="courseSemesters" class="form-control" min="1" max="12" value="6" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-secondary small">Status</label>
                    <select name="status" id="courseStatus" class="form-select">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4 fw-semibold" id="modalSubmitBtn">Save Course</button>
            </div>
        </form>
    </div>
</div>

<script>
function prepareAddCourse() {
    document.getElementById('modalTitle').textContent = 'Add New Course';
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalCourseId').value = '0';
    document.getElementById('courseCode').value = '';
    document.getElementById('courseName').value = '';
    document.getElementById('courseDuration').value = '3 Years';
    document.getElementById('courseSemesters').value = '6';
    document.getElementById('courseStatus').value = 'Active';
    document.getElementById('modalSubmitBtn').textContent = 'Save Course';
}

function prepareEditCourse(course) {
    document.getElementById('modalTitle').textContent = 'Edit Course: ' + course.code;
    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalCourseId').value = course.id;
    document.getElementById('courseCode').value = course.code;
    document.getElementById('courseName').value = course.name;
    document.getElementById('courseDuration').value = course.duration;
    document.getElementById('courseSemesters').value = course.total_semesters;
    document.getElementById('courseStatus').value = course.status;
    document.getElementById('modalSubmitBtn').textContent = 'Update Course';
    new bootstrap.Modal(document.getElementById('courseModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
