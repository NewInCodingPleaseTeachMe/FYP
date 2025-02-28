<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// 确保用户已登录
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];
$assignment_id = $_GET["id"] ?? null;

// 检查作业ID是否有效
if (!$assignment_id) {
    header("Location: course_list.php?error=invalid_assignment");
    exit;
}

// 查询作业信息
$stmt = $pdo->prepare("SELECT a.*, c.course_name, c.course_code, c.id as course_id
                       FROM assignments a
                       JOIN courses c ON a.course_id = c.id
                       WHERE a.id = ?");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    header("Location: course_list.php?error=assignment_not_found");
    exit;
}

// 检查用户是否有权限查看该作业
// 如果是教师，确保是该课程的教师
// 如果是学生，确保已加入该课程
$has_permission = false;

if ($role === "teacher") {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$assignment['course_id'], $user_id]);
    $has_permission = $stmt->fetch() ? true : false;
} elseif ($role === "student") {
    $stmt = $pdo->prepare("SELECT * FROM user_courses WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $assignment['course_id']]);
    $has_permission = $stmt->fetch() ? true : false;
} elseif ($role === "admin") {
    $has_permission = true;
}

if (!$has_permission) {
    header("Location: course_list.php?error=no_permission");
    exit;
}

// 如果是学生，检查是否已提交作业
$submission = null;
if ($role === "student") {
    $stmt = $pdo->prepare("SELECT * FROM submissions 
                          WHERE student_id = ? AND assignment_id = ? 
                          ORDER BY submitted_at DESC LIMIT 1");
    $stmt->execute([$user_id, $assignment_id]);
    $submission = $stmt->fetch();
}

// 获取截止日期信息
$due_date = new DateTime($assignment['due_date']);
$now = new DateTime();
$is_overdue = $now > $due_date;

// 获取附件信息(如果有)
$has_attachment = !empty($assignment['attachment']);
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($assignment['title']) ?> - Assignment Details | CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            border-radius: 0.8rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            background-color: rgba(13, 110, 253, 0.03);
            border-bottom: 1px solid rgba(13, 110, 253, 0.125);
            font-weight: 600;
        }
        .assignment-header {
            background-color: #ffffff;
            border-radius: 0.8rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-left: 5px solid #0d6efd;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .course-badge {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            padding: 0.35rem 0.65rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .due-date {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 0.35rem 0.65rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .attachment-box {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
        .submission-status {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .description-card {
            background-color: rgba(13, 110, 253, 0.03);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Breadcrumb navigation -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="course_detail.php?id=<?= $assignment['course_id'] ?>">
                    <?= htmlspecialchars($assignment['course_name']) ?>
                </a></li>
                <li class="breadcrumb-item"><a href="student_assignments.php?course_id=<?= $assignment['course_id'] ?>">
                    Assignment List
                </a></li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?= htmlspecialchars($assignment['title']) ?>
                </li>
            </ol>
        </nav>

 <!-- Assignment Header -->
 <div class="assignment-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="course-badge mb-2 d-inline-block">
                        <?= htmlspecialchars($assignment['course_code']) ?> - <?= htmlspecialchars($assignment['course_name']) ?>
                    </span>
                    <h1 class="mb-2"><?= htmlspecialchars($assignment['title']) ?></h1>
                    <div class="due-date">
                        <i class="bi bi-calendar-event me-1"></i>
                        Due Date: <?= $due_date->format('F j, Y H:i') ?>
                        <?php if ($is_overdue): ?>
                            <span class="ms-2 badge bg-danger">Closed</span>
                        <?php else: 
                            $interval = $now->diff($due_date);
                            if ($interval->days == 0) {
                                echo '<span class="ms-2 badge bg-warning text-dark">Due Today</span>';
                            } elseif ($interval->days == 1) {
                                echo '<span class="ms-2 badge bg-warning text-dark">Due Tomorrow</span>';
                            } else {
                                echo '<span class="ms-2 badge bg-info">' . $interval->days . ' days left</span>';
                            }
                        ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="student_assignments.php?course_id=<?= $assignment['course_id'] ?>" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Assignments
                    </a>
                </div>
            </div>

            <!-- Assignment Description -->
            <?php if (!empty($assignment['description'])): ?>
            <div class="description-card">
                <h5><i class="bi bi-info-circle me-2"></i>Assignment Instructions</h5>
                <div class="assignment-description mt-3">
                    <?= nl2br(htmlspecialchars($assignment['description'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Attachments (if any) -->
            <?php if ($has_attachment): ?>
            <div class="attachment-box mt-3">
                <h5><i class="bi bi-paperclip me-2"></i>Instructor Attachments</h5>
                <div class="d-flex align-items-center mt-2">
                    <i class="bi bi-file-earmark-text fs-2 me-3 text-primary"></i>
                    <div>
                        <h6 class="mb-1"><?= basename($assignment['attachment']) ?></h6>
                    </div>
                    <a href="<?= htmlspecialchars($assignment['attachment']) ?>" 
                       class="btn btn-outline-primary ms-auto" download>
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="row">
            <!-- Left side: Submission area (for students only) -->
            <?php if ($role === "student"): ?>
            <div class="col-lg-8 mb-4">
                <!-- Submission status -->
                <?php if ($submission): ?>
                    <div class="submission-status bg-success bg-opacity-10">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-check-circle-fill text-success fs-3 me-3"></i>
                            <div>
                                <h5 class="text-success mb-1">Submitted</h5>
                                <p class="mb-0">Submission time: <?= date('F j, Y H:i', strtotime($submission['submitted_at'])) ?></p>
                                <?php if ($submission['submitted_at'] > $assignment['due_date']): ?>
                                    <div class="text-danger mt-1">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        <small>Late submission</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ms-auto">
                                <a href="../uploads/<?= htmlspecialchars($submission['file_path']) ?>" 
                                   class="btn btn-outline-primary" download>
                                    <i class="bi bi-download me-1"></i>Download
                                </a>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6>Submitted file:</h6>
                            <div class="d-flex align-items-center">
                                <i class="bi bi-file-earmark-text text-primary me-2"></i>
                                <span><?= htmlspecialchars($submission['file_name']) ?></span>
                            </div>
                        </div>
                        <?php if (!empty($submission['grade'])): ?>
                            <div class="mt-3 pt-3 border-top">
                                <h6>Grading information:</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <strong>Grade:</strong> <?= $submission['grade'] ?>
                                            <?php if (!empty($submission['score'])): ?>
                                                (<?= $submission['score'] ?> / <?= $assignment['max_points'] ?? 100 ?> points)
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-2">
                                            <strong>Graded on:</strong> <?= date('F j, Y', strtotime($submission['graded_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($submission['feedback'])): ?>
                                    <div class="mt-2">
                                        <strong>Instructor feedback:</strong>
                                        <div class="p-2 bg-light rounded mt-1">
                                            <?= nl2br(htmlspecialchars($submission['feedback'])) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_overdue): ?>
                        <div class="card mt-3">
                            <div class="card-header">
                                <i class="bi bi-arrow-repeat me-2"></i>Resubmit
                            </div>
                            <div class="card-body">
                                <p class="card-text">If you want to update your submission, you can resubmit a new version.</p>
                                <a href="submit_assignment.php?id=<?= $assignment_id ?>" class="btn btn-primary">
                                    <i class="bi bi-upload me-1"></i>Resubmit
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if (!$is_overdue): ?>
                        <div class="submission-status bg-warning bg-opacity-10">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-exclamation-circle-fill text-warning fs-3 me-3"></i>
                                <div>
                                    <h5 class="text-warning mb-1">Not Submitted</h5>
                                    <p class="mb-0">You have not yet submitted this assignment. Please complete your submission before the due date.</p>
                                </div>
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header">
                                <i class="bi bi-upload me-2"></i>Submit Assignment
                            </div>
                            <div class="card-body">
                                <form action="../modules/submission_upload.php" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                                    <div class="mb-3">
                                        <label for="submission_file" class="form-label">Select file to submit</label>
                                        <input class="form-control" type="file" id="submission_file" name="submission_file" required>
                                        <div class="form-text">Supported file formats: DOC, DOCX, PDF, ZIP, RAR (max 10MB).</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="submission_comment" class="form-label">Submission comment (optional)</label>
                                        <textarea class="form-control" id="submission_comment" name="comment" rows="3" placeholder="Anything you want to tell your instructor?"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-upload me-1"></i>Submit Assignment
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="submission-status bg-danger bg-opacity-10">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-x-circle-fill text-danger fs-3 me-3"></i>
                                <div>
                                    <h5 class="text-danger mb-1">Not Submitted (Overdue)</h5>
                                    <p class="mb-0">This assignment is past the due date and you did not submit on time. Please contact your instructor about possible late submissions.</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Right side: Assignment information -->
            <div class="col-lg-<?= ($role === "student") ? '4' : '12' ?>">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle-fill me-2"></i>Assignment Information
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-<?= ($role === "student") ? '12' : '4' ?> mb-3">
                                <h6>Basic Information</h6>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>Course:</span>
                                        <span class="text-primary"><?= htmlspecialchars($assignment['course_name']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>Due Date:</span>
                                        <span class="text-danger"><?= $due_date->format('F j, Y H:i') ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>Maximum Points:</span>
                                        <span><?= $assignment['max_points'] ?? 100 ?> points</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>Created On:</span>
                                        <span><?= date('F j, Y', strtotime($assignment['created_at'])) ?></span>
                                    </li>
                                </ul>
                            </div>
                            
                            <?php if ($role === "teacher" || $role === "admin"): ?>
                            <div class="col-lg-4 mb-3">
                                <h6>Submission Statistics</h6>
                                <?php
                                // Get submission statistics
                                $stats_stmt = $pdo->prepare("
                                    SELECT
                                        (SELECT COUNT(*) FROM user_courses WHERE course_id = ?) AS total_students,
                                        (SELECT COUNT(DISTINCT student_id) FROM submissions WHERE assignment_id = ?) AS submitted_students,
                                        (SELECT COUNT(*) FROM submissions WHERE assignment_id = ? AND grade IS NOT NULL) AS graded_submissions
                                ");
                                $stats_stmt->execute([$assignment['course_id'], $assignment_id, $assignment_id]);
                                $stats = $stats_stmt->fetch();
                                
                                $submission_rate = $stats['total_students'] > 0 
                                    ? round(($stats['submitted_students'] / $stats['total_students']) * 100) 
                                    : 0;
                                ?>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>Total Students:</span>
                                        <span class="text-primary"><?= $stats['total_students'] ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>Students Submitted:</span>
                                        <span class="text-success"><?= $stats['submitted_students'] ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>Graded Submissions:</span>
                                        <span><?= $stats['graded_submissions'] ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between px-0">
                                        <span>Submission Rate:</span>
                                        <span><?= $submission_rate ?>%</span>
                                    </li>
                                </ul>
                            </div>
                            
                            <div class="col-lg-4 mb-3">
                                <h6>Actions</h6>
                                <div class="d-grid gap-2">
                                    <a href="view_submissions.php?assignment_id=<?= $assignment_id ?>" class="btn btn-primary">
                                        <i class="bi bi-list-check me-1"></i>View All Submissions
                                    </a>
                                    <a href="edit_assignment.php?id=<?= $assignment_id ?>" class="btn btn-outline-primary">
                                        <i class="bi bi-pencil me-1"></i>Edit Assignment
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                        <i class="bi bi-trash me-1"></i>Delete Assignment
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($role === "teacher" || $role === "admin"): ?>
                <!-- Grade distribution (visible to teachers) -->
                <div class="card mt-4">
                    <div class="card-header">
                        <i class="bi bi-bar-chart-fill me-2"></i>Grade Distribution
                    </div>
                    <div class="card-body">
                        <?php
                        // Get grade distribution
                        $grades_stmt = $pdo->prepare("
                            SELECT grade, COUNT(*) as count
                            FROM submissions
                            WHERE assignment_id = ? AND grade IS NOT NULL
                            GROUP BY grade
                            ORDER BY CASE 
                                WHEN grade = 'A' THEN 1
                                WHEN grade = 'B' THEN 2
                                WHEN grade = 'C' THEN 3
                                WHEN grade = 'D' THEN 4
                                WHEN grade = 'F' THEN 5
                                ELSE 6
                            END
                        ");
                        $grades_stmt->execute([$assignment_id]);
                        $grades = $grades_stmt->fetchAll();
                        
                        if (count($grades) > 0):
                            $total_graded = array_sum(array_column($grades, 'count'));
                        ?>
                            <div class="row">
                                <?php foreach ($grades as $grade): ?>
                                    <?php 
                                    $percentage = round(($grade['count'] / $total_graded) * 100);
                                    $color = 'primary';
                                    switch ($grade['grade']) {
                                        case 'A': $color = 'success'; break;
                                        case 'B': $color = 'primary'; break;
                                        case 'C': $color = 'secondary'; break;
                                        case 'D': $color = 'warning'; break;
                                        case 'F': $color = 'danger'; break;
                                    }
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-2 fw-bold"><?= $grade['grade'] ?>:</div>
                                            <div class="progress flex-grow-1" style="height: 10px;">
                                                <div class="progress-bar bg-<?= $color ?>" role="progressbar" 
                                                     style="width: <?= $percentage ?>%;" 
                                                     aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <div class="ms-2"><?= $grade['count'] ?> (<?= $percentage ?>%)</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-bar-chart text-muted" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0 text-muted">No grading data available yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($role === "teacher" || $role === "admin"): ?>
    <!-- Delete assignment confirmation dialog -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Assignment Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> Deleting this assignment will permanently remove all student submissions and grading data. This action cannot be undone!
                    </div>
                    <p>Are you sure you want to delete the assignment <strong><?= htmlspecialchars($assignment['title']) ?></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../modules/delete_assignment.php?id=<?= $assignment_id ?>" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Confirm Deletion
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>