<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// **🔐 Restrict access to unauthenticated users**
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// **🔐 Restrict access to non-admin users**
if ($_SESSION["role"] !== "admin") {
    header("Location: ../dashboard.php?error=unauthorized");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php?error=invalid_id");
    exit;
}

$user_id = intval($_GET['id']);

// Fetch user details
$stmt = $pdo->prepare("
    SELECT u.*, 
        (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) as activity_count,
        (SELECT MAX(created_at) FROM activity_logs WHERE user_id = u.id) as last_activity
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If user not found, redirect back to list
if (!$user) {
    header("Location:user_list.php.php?error=user_not_found");
    exit;
}

// Fetch recent activity logs
$log_stmt = $pdo->prepare("
    SELECT action, entity_type, created_at 
    FROM activity_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$log_stmt->execute([$user_id]);
$activity_logs = $log_stmt->fetchAll();

// Fetch enrolled courses (for students)
$courses = [];
if ($user['role'] === 'student') {
    $course_stmt = $pdo->prepare("
        SELECT c.id, c.course_name, c.course_code, c.description, e.enrollment_date
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.student_id = ?
        ORDER BY e.enrollment_date DESC
    ");
    $course_stmt->execute([$user_id]);
    $courses = $course_stmt->fetchAll();
}

// Fetch teaching courses (for teachers)
$teaching_courses = [];
if ($user['role'] === 'teacher') {
    $teaching_stmt = $pdo->prepare("
        SELECT id, course_name, course_code, description, created_at
        FROM courses
        WHERE teacher_id = ?
        ORDER BY created_at DESC
    ");
    $teaching_stmt->execute([$user_id]);
    $teaching_courses = $teaching_stmt->fetchAll();
}

// Helper function to generate role badge
function getRoleBadge($role) {
    $roleClasses = [
        'admin' => 'bg-danger',
        'teacher' => 'bg-success',
        'student' => 'bg-primary'
    ];
    
    $roleIcons = [
        'admin' => 'shield-lock-fill',
        'teacher' => 'person-workspace',
        'student' => 'mortarboard-fill'
    ];
    
    $class = $roleClasses[$role] ?? 'bg-secondary';
    $icon = $roleIcons[$role] ?? 'person-fill';
    
    return "<span class='badge $class'><i class='bi bi-$icon me-1'></i>" . ucfirst($role) . "</span>";
}

// Format date helper function
function formatDate($date) {
    if (empty($date)) return "N/A";
    return date('M d, Y h:i A', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Profile: <?= htmlspecialchars($user['name']) ?> - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            border-radius: 0.8rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
        }
        .profile-header {
            background: linear-gradient(45deg, #6610f2, #0d6efd);
            border-radius: 0.8rem 0.8rem 0 0;
            padding: 2rem;
            color: white;
        }
        .avatar-container {
            width: 120px;
            height: 120px;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 5px solid rgba(255, 255, 255, 0.3);
            overflow: hidden;
        }
        .avatar-placeholder {
            font-size: 3rem;
            color: #0d6efd;
        }
        .info-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(13, 110, 253, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0d6efd;
            margin-right: 1rem;
        }
        .activity-item {
            border-left: 2px solid #dee2e6;
            padding-left: 20px;
            position: relative;
            padding-bottom: 1.5rem;
        }
        .activity-item:last-child {
            padding-bottom: 0;
        }
        .activity-item:before {
            content: "";
            position: absolute;
            left: -8px;
            top: 0;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #0d6efd;
        }
        .activity-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .course-card {
            transition: all 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .action-button {
            transition: all 0.2s;
        }
        .action-button:hover {
            transform: translateY(-3px);
        }
        .nav-tabs .nav-link {
            border: none;
            color: #495057;
            padding: 1rem 1.5rem;
        }
        .nav-tabs .nav-link.active {
            border-bottom: 3px solid #0d6efd;
            color: #0d6efd;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Top Navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-person-badge-fill text-primary me-2"></i>User Profile
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="user_list.php">User Management</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($user['name']) ?></li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-primary action-button">
                    <i class="bi bi-pencil-fill me-2"></i>Edit User
                </a>
                <a href="user_list.php" class="btn btn-outline-secondary ms-2 action-button">
                    <i class="bi bi-arrow-left me-2"></i>Back to User List
                </a>
            </div>
        </div>

        <!-- Profile Card -->
        <div class="card mb-4">
            <div class="profile-header">
                <div class="row align-items-center">
                    <div class="col-md-auto mb-3 mb-md-0">
                        <div class="avatar-container">
                            <?php if (!empty($user['avatar'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="User avatar" class="img-fluid">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md">
                        <h3 class="mb-1"><?= htmlspecialchars($user['name']) ?></h3>
                        <p class="mb-2">
                            <?= getRoleBadge($user['role']) ?>
                            <?php if ($user['role'] === 'student' && !empty($user['student_id'])): ?>
                                <span class="badge bg-light text-dark ms-2">Student ID: <?= htmlspecialchars($user['student_id']) ?></span>
                            <?php endif; ?>
                        </p>
                        <p class="mb-0"><i class="bi bi-envelope-fill me-2"></i><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <ul class="nav nav-tabs mb-4" id="userTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="overview-tab" data-bs-toggle="tab" href="#overview" role="tab" aria-controls="overview" aria-selected="true">
                            <i class="bi bi-info-circle me-2"></i>Overview
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="activity-tab" data-bs-toggle="tab" href="#activity" role="tab" aria-controls="activity" aria-selected="false">
                            <i class="bi bi-clock-history me-2"></i>Activity
                        </a>
                    </li>
                    <?php if ($user['role'] === 'student'): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="courses-tab" data-bs-toggle="tab" href="#courses" role="tab" aria-controls="courses" aria-selected="false">
                            <i class="bi bi-book me-2"></i>Enrolled Courses
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($user['role'] === 'teacher'): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="teaching-tab" data-bs-toggle="tab" href="#teaching" role="tab" aria-controls="teaching" aria-selected="false">
                            <i class="bi bi-easel me-2"></i>Teaching Courses
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="tab-content" id="userTabsContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-4"><i class="bi bi-person-lines-fill me-2"></i>Personal Information</h5>
                                
                                <div class="mb-3 d-flex align-items-center">
                                    <div class="info-icon">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Full Name</h6>
                                        <p class="mb-0"><?= htmlspecialchars($user['name']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3 d-flex align-items-center">
                                    <div class="info-icon">
                                        <i class="bi bi-envelope-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Email Address</h6>
                                        <p class="mb-0"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>
                                </div>
                                
                                <?php if ($user['role'] === 'student' && !empty($user['student_id'])): ?>
                                <div class="mb-3 d-flex align-items-center">
                                    <div class="info-icon">
                                        <i class="bi bi-card-text"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Student ID</h6>
                                        <p class="mb-0"><?= htmlspecialchars($user['student_id']) ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3 d-flex align-items-center">
                                    <div class="info-icon">
                                        <i class="bi bi-shield-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Role</h6>
                                        <p class="mb-0"><?= ucfirst($user['role']) ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="mb-4"><i class="bi bi-gear-fill me-2"></i>Account Information</h5>
                                
                                <div class="mb-3 d-flex align-items-center">
                                    <div class="info-icon">
                                        <i class="bi bi-calendar-date"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Account Created</h6>
                                        <p class="mb-0"><?= formatDate($user['created_at']) ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3 d-flex align-items-center">
                                    <div class="info-icon">
                                        <i class="bi bi-clock-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Last Login</h6>
                                        <p class="mb-0"><?= !empty($user['last_login']) ? formatDate($user['last_login']) : "Never" ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3 d-flex align-items-center">
                                    <div class="info-icon">
                                        <i class="bi bi-activity"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Last Activity</h6>
                                        <p class="mb-0"><?= !empty($user['last_activity']) ? formatDate($user['last_activity']) : "None" ?></p>
                                    </div>
                                </div>
                                
                                <div class="mb-3 d-flex align-items-center">
                                    <div class="info-icon">
                                        <i class="bi bi-graph-up"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-1">Total Activity Count</h6>
                                        <p class="mb-0"><?= $user['activity_count'] ?> actions recorded</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Activity Tab -->
                    <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                        <h5 class="mb-4"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                        
                        <?php if (count($activity_logs) > 0): ?>
                            <div class="activity-timeline mb-4">
                                <?php foreach ($activity_logs as $log): ?>
                                    <div class="activity-item">
                                        <h6 class="mb-1">
                                            <?php
                                            $actionIcon = "";
                                            $actionText = "";
                                            
                                            switch ($log['action']) {
                                                case 'login':
                                                    $actionIcon = "box-arrow-in-right";
                                                    $actionText = "Logged into the system";
                                                    break;
                                                case 'logout':
                                                    $actionIcon = "box-arrow-right";
                                                    $actionText = "Logged out of the system";
                                                    break;
                                                case 'password_reset_request':
                                                    $actionIcon = "key";
                                                    $actionText = "Requested password reset";
                                                    break;
                                                case 'password_reset_complete':
                                                    $actionIcon = "check-circle";
                                                    $actionText = "Completed password reset";
                                                    break;
                                                default:
                                                    $actionIcon = "arrow-right-circle";
                                                    $actionText = ucfirst(str_replace('_', ' ', $log['action'])) . " " . 
                                                                 (!empty($log['entity_type']) ? "(" . ucfirst(str_replace('_', ' ', $log['entity_type'])) . ")" : "");
                                            }
                                            ?>
                                            <i class="bi bi-<?= $actionIcon ?> me-2"></i>
                                            <?= $actionText ?>
                                        </h6>
                                        <p class="activity-time mb-0"><?= formatDate($log['created_at']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="text-center">
                                <a href="#" class="btn btn-outline-primary">
                                    <i class="bi bi-list-ul me-2"></i>View All Activity
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                No activity recorded for this user yet.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Enrolled Courses Tab (Students only) -->
                    <?php if ($user['role'] === 'student'): ?>
                    <div class="tab-pane fade" id="courses" role="tabpanel" aria-labelledby="courses-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-book me-2"></i>Enrolled Courses</h5>
                            <a href="#" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle me-2"></i>Enroll in New Course
                            </a>
                        </div>
                        
                        <?php if (count($courses) > 0): ?>
                            <div class="row">
                                <?php foreach ($courses as $course): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100 course-card">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($course['course_name']) ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted">Code: <?= htmlspecialchars($course['course_code']) ?></h6>
                                                <p class="card-text">
                                                    <?= !empty($course['description']) ? htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : '') : 'No description available.' ?>
                                                </p>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar2-check me-1"></i>
                                                        Enrolled on: <?= formatDate($course['enrollment_date']) ?>
                                                    </small>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <a href="../course/view.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i>View Course
                                                </a>
                                                <a href="#" class="btn btn-sm btn-outline-danger float-end">
                                                    <i class="bi bi-trash me-1"></i>Unenroll
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                This student is not enrolled in any courses yet.
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Teaching Courses Tab (Teachers only) -->
                    <?php if ($user['role'] === 'teacher'): ?>
                    <div class="tab-pane fade" id="teaching" role="tabpanel" aria-labelledby="teaching-tab">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0"><i class="bi bi-easel me-2"></i>Teaching Courses</h5>
                            <a href="../course/create.php?teacher_id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle me-2"></i>Assign New Course
                            </a>
                        </div>
                        
                        <?php if (count($teaching_courses) > 0): ?>
                            <div class="row">
                                <?php foreach ($teaching_courses as $course): ?>
                                    <div class="col-md-6 mb-4">
                                        <div class="card h-100 course-card">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($course['course_name']) ?></h5>
                                                <h6 class="card-subtitle mb-2 text-muted">Code: <?= htmlspecialchars($course['course_code']) ?></h6>
                                                <p class="card-text">
                                                    <?= !empty($course['description']) ? htmlspecialchars(substr($course['description'], 0, 100)) . (strlen($course['description']) > 100 ? '...' : '') : 'No description available.' ?>
                                                </p>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar2-check me-1"></i>
                                                        Created on: <?= formatDate($course['created_at']) ?>
                                                    </small>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <a href="../course/view.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i>View Course
                                                </a>
                                                <a href="../course/edit.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-secondary ms-1">
                                                    <i class="bi bi-pencil me-1"></i>Edit
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                This teacher is not assigned to any courses yet.
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card-footer text-center p-3">
                <div class="btn-group" role="group">
                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-outline-primary">
                        <i class="bi bi-pencil-fill me-2"></i>Edit User
                    </a>
                    <?php if ($_SESSION['user_id'] != $user['id']): ?>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash-fill me-2"></i>Delete User
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-danger" disabled>
                            <i class="bi bi-trash-fill me-2"></i>Cannot Delete Current User
                        </button>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user <strong><?= htmlspecialchars($user['name']) ?></strong>? This action cannot be undone.</p>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Deleting a user will also remove all related data, including courses, assignments, and submissions.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../modules/delete_user.php?id=<?= $user['id'] ?>" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Confirm Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>