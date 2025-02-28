<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// **Ensure user is logged in**
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$course_id = $_GET["id"] ?? null;
$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// **Check if course ID is valid**
if (!$course_id) {
    header("Location: course_list.php?error=invalid_course");
    exit;
}

// **Query course information**
$stmt = $pdo->prepare("SELECT courses.*, users.name AS teacher_name, users.email AS teacher_email
                       FROM courses 
                       JOIN users ON courses.teacher_id = users.id 
                       WHERE courses.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: course_list.php?error=course_not_found");
    exit;
}

// **Check if current user has enrolled in the course**
$stmt = $pdo->prepare("SELECT * FROM user_courses WHERE user_id = ? AND course_id = ?");
$stmt->execute([$user_id, $course_id]);
$enrolled = $stmt->fetch();

// **Get course announcements**
$stmt = $pdo->prepare("SELECT a.*, u.name AS author_name 
                      FROM announcements a
                      JOIN users u ON a.teacher_id = u.id
                      WHERE a.course_id = ? 
                      ORDER BY a.created_at DESC 
                      LIMIT 5");
$stmt->execute([$course_id]);
$announcements = $stmt->fetchAll();


// **Get recent assignments**
$stmt = $pdo->prepare("SELECT * FROM assignments 
                      WHERE course_id = ? AND due_date > NOW() 
                      ORDER BY due_date ASC LIMIT 3");
$stmt->execute([$course_id]);
$upcoming_assignments = $stmt->fetchAll();

// **Get course member count**
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM user_courses WHERE course_id = ?");
$stmt->execute([$course_id]);
$member_count = $stmt->fetch()['count'];

// **Get course resource count**
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM course_resources WHERE course_id = ?");
$stmt->execute([$course_id]);
$resource_count = $stmt->fetch()['count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($course["course_name"]) ?> - Course Details | CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --bs-primary-rgb: 13, 110, 253;
            --bs-secondary-rgb: 108, 117, 125;
        }
        body {
            background-color: #f8f9fa;
        }
        .course-header {
            background-color: #ffffff;
            border-radius: 0.8rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-left: 5px solid #0d6efd;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .course-code {
            background-color: rgba(var(--bs-primary-rgb), 0.1);
            color: #0d6efd;
            padding: 0.35rem 0.65rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
        }
        .teacher-badge {
            background-color: rgba(var(--bs-secondary-rgb), 0.1);
            color: #6c757d;
            padding: 0.35rem 0.65rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
        }
        .card {
            border: none;
            border-radius: 0.8rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            height: 100%;
        }
        .card-header {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            border-bottom: 1px solid rgba(var(--bs-primary-rgb), 0.125);
            padding: 1rem 1.25rem;
            font-weight: 600;
        }
        .announcement-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .announcement-item:last-child {
            border-bottom: none;
        }
        .announcement-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .assignment-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .assignment-item:last-child {
            border-bottom: none;
        }
        .assignment-icon {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(var(--bs-primary-rgb), 0.1);
            color: #0d6efd;
            border-radius: 0.5rem;
            margin-right: 1rem;
        }
        .assignment-due {
            font-size: 0.8rem;
            color: #dc3545;
            font-weight: 500;
        }
        .action-card {
            text-align: center;
            transition: all 0.2s ease;
        }
        .action-card:hover {
            transform: translateY(-5px);
        }
        .action-icon {
            width: 3.5rem;
            height: 3.5rem;
            background-color: rgba(var(--bs-primary-rgb), 0.1);
            color: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            font-size: 1.5rem;
        }
        .stats-item {
            text-align: center;
            padding: 1rem;
            background-color: #ffffff;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .stats-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: #0d6efd;
        }
        .badge-card {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background-color: #dc3545;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .description-card {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
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
                <li class="breadcrumb-item"><a href="course_list.php">Course List</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($course["course_name"]) ?></li>
            </ol>
        </nav>

        <!-- Course title area -->
        <div class="course-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <span class="course-code mb-2 d-inline-block">
                        <?= htmlspecialchars($course["course_code"]) ?>
                    </span>
                    <h1 class="mb-2"><?= htmlspecialchars($course["course_name"]) ?></h1>
                    <div class="teacher-badge">
                        <i class="bi bi-person-workspace me-1"></i>
                        <?= htmlspecialchars($course["teacher_name"]) ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <?php if ($role === "student" && !$enrolled) : ?>
                        <form action="../modules/course_enroll.php" method="POST">
                            <input type="hidden" name="course_id" value="<?= $course_id ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Join Course
                            </button>
                        </form>
                    <?php elseif ($role === "student" && $enrolled) : ?>
                        <div class="alert alert-success mb-0 d-inline-block py-2">
                            <i class="bi bi-check-circle-fill me-1"></i> Already Enrolled
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Course description -->
            <?php if (!empty($course["description"])): ?>
            <div class="description-card mt-3">
                <h5><i class="bi bi-info-circle me-2"></i>Course Introduction</h5>
                <p class="mb-0"><?= nl2br(htmlspecialchars($course["description"])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Course statistics -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3 mb-md-0">
                <div class="stats-item">
                    <div class="stats-value"><?= $member_count ?></div>
                    <div class="stats-label">Students</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3 mb-md-0">
                <div class="stats-item">
                    <div class="stats-value"><?= count($upcoming_assignments) ?></div>
                    <div class="stats-label">Pending Assignments</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-item">
                    <div class="stats-value"><?= $resource_count ?></div>
                    <div class="stats-label">Course Resources</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-item">
                    <div class="stats-value"><?= count($announcements) ?></div>
                    <div class="stats-label">Latest Announcements</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left side: Action area -->
            <div class="col-md-4 mb-4 mb-md-0">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <i class="bi bi-gear-fill me-2"></i> Course Actions
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="discussion.php?course_id=<?= $course_id ?>" class="text-decoration-none text-dark">
                                    <div class="action-card">
                                        <div class="action-icon">
                                            <i class="bi bi-chat-dots"></i>
                                        </div>
                                        <h6>Discussion</h6>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <?php if ($role === "student"): ?>
                                    <a href="student_assignments.php?course_id=<?= $course_id ?>" class="text-decoration-none text-dark">
                                <?php else: ?>
                                    <a href="assignments_list.php?course_id=<?= $course_id ?>" class="text-decoration-none text-dark">
                                <?php endif; ?>
                                    <div class="action-card position-relative">
                                        <?php if (count($upcoming_assignments) > 0): ?>
                                            <div class="badge-card"><?= count($upcoming_assignments) ?></div>
                                        <?php endif; ?>
                                        <div class="action-icon">
                                            <i class="bi bi-clipboard-check"></i>
                                        </div>
                                        <h6>Assignments</h6>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="course_resources.php?course_id=<?= $course_id ?>" class="text-decoration-none text-dark">
                                    <div class="action-card">
                                        <div class="action-icon">
                                            <i class="bi bi-folder2-open"></i>
                                        </div>
                                        <h6>Course Resources</h6>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="course_members.php?course_id=<?= $course_id ?>" class="text-decoration-none text-dark">
                                    <div class="action-card">
                                        <div class="action-icon">
                                            <i class="bi bi-people"></i>
                                        </div>
                                        <h6>Course Members</h6>
                                    </div>
                                </a>
                            </div>
                            
                            <?php if ($role === "teacher" && $user_id == $course["teacher_id"]): ?>
                            <div class="col-6">
                                <a href="add_announcement.php?course_id=<?= $course_id ?>" class="text-decoration-none text-dark">
                                    <div class="action-card">
                                        <div class="action-icon" style="background-color: rgba(25, 135, 84, 0.1); color: #198754;">
                                            <i class="bi bi-megaphone"></i>
                                        </div>
                                        <h6>Post Announcement</h6>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="add_assignment.php?course_id=<?= $course_id ?>" class="text-decoration-none text-dark">
                                    <div class="action-card">
                                        <div class="action-icon" style="background-color: rgba(25, 135, 84, 0.1); color: #198754;">
                                            <i class="bi bi-file-earmark-plus"></i>
                                        </div>
                                        <h6>Add Assignment</h6>
                                    </div>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($role === "admin" || $user_id == $course["teacher_id"]): ?>
                            <div class="col-6">
                                <a href="edit_course.php?id=<?= $course_id ?>" class="text-decoration-none text-dark">
                                    <div class="action-card">
                                        <div class="action-icon" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="bi bi-pencil-square"></i>
                                        </div>
                                        <h6>Edit Course</h6>
                                    </div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="javascript:void(0)" class="text-decoration-none text-dark" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <div class="action-card">
                                        <div class="action-icon" style="background-color: rgba(220, 53, 69, 0.1); color: #dc3545;">
                                            <i class="bi bi-trash"></i>
                                        </div>
                                        <h6>Delete Course</h6>
                                    </div>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right side: Information area -->
            <div class="col-md-8">
                <div class="row">
                    <!-- Announcement area -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div><i class="bi bi-megaphone-fill me-2"></i> Course Announcements</div>
                                <a href="announcements_list.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body p-0">
                                <?php if (count($announcements) > 0): ?>
                                    <?php foreach ($announcements as $announcement): ?>
                                        <div class="announcement-item">
                                            <h5 class="mb-1"><?= htmlspecialchars($announcement['title']) ?></h5>
                                            <p class="mb-1"><?= nl2br(htmlspecialchars(mb_substr($announcement['content'], 0, 100) . (mb_strlen($announcement['content']) > 100 ? '...' : ''))) ?></p>
                                            <div class="announcement-meta">
                                                <span class="me-3"><i class="bi bi-person me-1"></i><?= htmlspecialchars($announcement['author_name']) ?></span>
                                                <span><i class="bi bi-clock me-1"></i><?= date('Y-m-d H:i', strtotime($announcement['created_at'])) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <img src="../assets/images/no-announcements.svg" alt="No announcements" style="width: 120px; opacity: 0.6;" class="mb-3">
                                        <p class="mb-0 text-muted">No course announcements yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Upcoming assignments -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div><i class="bi bi-calendar-check-fill me-2"></i> Upcoming Assignments</div>
                                <?php if ($role === "student"): ?>
                                    <a href="student_assignments.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-outline-primary">View All</a>
                                <?php else: ?>
                                    <a href="assignments_list.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-outline-primary">View All</a>
                                <?php endif; ?>
                            </div>
                            <div class="card-body p-0">
                                <?php if (count($upcoming_assignments) > 0): ?>
                                    <?php foreach ($upcoming_assignments as $assignment): ?>
                                        <div class="assignment-item">
                                            <div class="assignment-icon">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h5 class="mb-1"><?= htmlspecialchars($assignment['title']) ?></h5>
                                                <div class="assignment-due">
                                                    <i class="bi bi-alarm me-1"></i>
                                                    <?php
                                                    $due_date = new DateTime($assignment['due_date']);
                                                    $now = new DateTime();
                                                    $interval = $now->diff($due_date);
                                                    
                                                    if ($interval->days == 0) {
                                                        echo "Due today";
                                                    } elseif ($interval->days == 1) {
                                                        echo "Due tomorrow";
                                                    } else {
                                                        echo $interval->days . " days remaining";
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <a href="view_assignment.php?id=<?= $assignment['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <img src="../assets/images/no-assignments.svg" alt="No assignments" style="width: 120px; opacity: 0.6;" class="mb-3">
                                        <p class="mb-0 text-muted">No upcoming assignments</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete course confirmation modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Course Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Warning:</strong> Deleting this course will permanently remove all related assignments, resources, discussions, and student records. This action cannot be undone!
                    </div>
                    <p>Are you sure you want to delete the course <strong><?= htmlspecialchars($course["course_name"]) ?></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../modules/delete_course.php?id=<?= $course_id ?>" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Confirm Deletion
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>