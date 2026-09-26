<?php
/**
 * Student Management Module
 * List, Search, Filter, Status Management, Actions
 */

declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth_check.php';

$pdo = getDBConnection();
$page_title = 'Students Directory';

// Filters & Search
$search = trim($_GET['search'] ?? '');
$courseFilter = (int)($_GET['course_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT s.*, c.name AS course_name, c.code AS course_code 
        FROM students s 
        JOIN courses c ON s.course_id = c.id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (s.name LIKE ? OR s.enrollment_no LIKE ? OR s.email LIKE ? OR s.mobile LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($courseFilter > 0) {
    $sql .= " AND s.course_id = ?";
    $params[] = $courseFilter;
}

if (!empty($statusFilter)) {
    $sql .= " AND s.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Fetch Courses for filter dropdown
$courses = $pdo->query("SELECT id, name, code FROM courses ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h3 class="fw-bold mb-1">Student Management</h3>
        <p class="text-muted small mb-0">Total <?= count($students) ?> enrolled student records found</p>
    </div>
    <div>
        <a href="student_add.php" class="btn btn-primary d-inline-flex align-items-center gap-2 shadow-sm">
            <i class="bi bi-person-plus-fill"></i> Add New Student
        </a>
    </div>
</div>

<!-- Search & Filters Bar -->
<div class="card card-custom mb-4 p-3">
    <form method="GET" action="students.php" class="row g-2 align-items-center">
        <div class="col-12 col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by Name, Enrollment, Mobile, Email..." value="<?= sanitize($search) ?>">
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <select name="course_id" class="form-select">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= ($courseFilter === (int)$c['id']) ? 'selected' : '' ?>>
                        <?= sanitize($c['code']) ?> - <?= sanitize($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-md-3">
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="Active" <?= ($statusFilter === 'Active') ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= ($statusFilter === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
                <option value="Passed Out" <?= ($statusFilter === 'Passed Out') ? 'selected' : '' ?>>Passed Out</option>
                <option value="Suspended" <?= ($statusFilter === 'Suspended') ? 'selected' : '' ?>>Suspended</option>
            </select>
        </div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
            <?php if (!empty($search) || $courseFilter > 0 || !empty($statusFilter)): ?>
                <a href="students.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Students Data Table -->
<div class="card card-custom">
    <div class="table-responsive">
        <table class="table table-custom table-hover mb-0">
            <thead>
                <tr>
                    <th>Enrollment No</th>
                    <th>Student Info</th>
                    <th>Contact Details</th>
                    <th>Course</th>
                    <th>Admission Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-people display-6 d-block text-secondary mb-2"></i>
                            No student records matching your query criteria.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $stu): ?>
                        <tr>
                            <td>
                                <span class="mono fw-bold text-primary"><?= sanitize($stu['enrollment_no']) ?></span>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= sanitize($stu['name']) ?></div>
                                <div class="small text-muted">S/D of: <?= sanitize($stu['father_name']) ?> (<?= sanitize($stu['gender']) ?>)</div>
                            </td>
                            <td>
                                <div class="small"><i class="bi bi-telephone me-1 text-muted"></i><?= sanitize($stu['mobile']) ?></div>
                                <div class="small text-muted"><i class="bi bi-envelope me-1 text-muted"></i><?= sanitize($stu['email']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold">
                                    <?= sanitize($stu['course_code']) ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= sanitize($stu['admission_date']) ?></td>
                            <td><?= get_status_badge($stu['status']) ?></td>
                            <td class="text-end">
                                <div class="btn-group">
                                    <a href="student_view.php?id=<?= (int)$stu['id'] ?>" class="btn btn-sm btn-outline-info" title="View Fee Breakdown">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="payments.php?action=new&student_id=<?= (int)$stu['id'] ?>" class="btn btn-sm btn-outline-success" title="Pay Fee">
                                        <i class="bi bi-currency-rupee"></i>
                                    </a>
                                    <a href="student_edit.php?id=<?= (int)$stu['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit Student">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="student_delete.php?id=<?= (int)$stu['id'] ?>" class="btn btn-sm btn-outline-danger" title="Delete Student" onclick="return confirm('Are you sure you want to delete <?= sanitize($stu['name']) ?>?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
