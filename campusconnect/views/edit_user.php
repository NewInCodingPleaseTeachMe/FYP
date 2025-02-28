<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Only allow administrators access
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit("Access denied!");
}

// Get user ID and validate
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: user_list.php");
    exit("Invalid user ID!");
}

$user_id = $_GET["id"];

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If user doesn't exist, return to user list
if (!$user) {
    header("Location: user_list.php");
    exit("User does not exist!");
}

// Get user courses
$courses_stmt = $pdo->prepare("SELECT c.id, c.course_name, c.course_code 
                               FROM courses c 
                               JOIN user_courses uc ON c.id = uc.course_id 
                               WHERE uc.user_id = ?");
$courses_stmt->execute([$user_id]);
$user_courses = $courses_stmt->fetchAll();

// Get user activity logs
$logs_stmt = $pdo->prepare("SELECT action, entity_type, created_at 
                            FROM activity_logs 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC 
                            LIMIT 5");
$logs_stmt->execute([$user_id]);
$user_logs = $logs_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - <?= htmlspecialchars($user['name']) ?> - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
        }
        .form-section {
            border-left: 4px solid #0d6efd;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }
        .role-badge {
            padding: 10px;
            border-radius: 50px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .role-badge.active {
            transform: scale(1.05);
        }
        .admin-role {
            background-color: #f8d7da;
            color: #dc3545;
        }
        .teacher-role {
            background-color: #cce5ff;
            color: #0d6efd;
        }
        .student-role {
            background-color: #d1e7dd;
            color: #198754;
        }
        .avatar-placeholder {
            width: 100px;
            height: 100px;
            font-size: 2.5rem;
            background-color: #f8f9fa;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-mortarboard-fill me-2"></i>CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="user_list.php">
                            <i class="bi bi-people me-1"></i>User Management
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["name"] ?? "Administrator") ?> 
                        <span class="badge bg-light text-primary ms-1">Admin</span>
                    </span>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="user_list.php">User Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit User</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-person-gear me-2"></i>
                    Edit User Information
                </h2>
                <p class="text-muted">Modify user details, roles, and permissions</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="user_list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Return to User List
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- User Information Form -->
                <div class="card mb-4">
                    <div class="card-body p-4">
                        <form action="../modules/edit_user.php" method="POST" id="editUserForm">
                            <input type="hidden" name="id" value="<?= $user_id ?>">
                            
                            <!-- Basic Information -->
                            <div class="form-section">
                                <h5 class="mb-3">Basic Information</h5>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" class="form-control" 
                                           value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="email" class="form-control" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" name="phone" id="phone" class="form-control" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                            </div>
                            
                            <!-- Role Settings -->
                            <div class="form-section">
                                <h5 class="mb-3">Role Settings</h5>
                                
                                <!-- Visual Role Selection -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="role-badge admin-role <?= $user['role'] == 'admin' ? 'active' : '' ?>" 
                                             data-role="admin">
                                             <i class="bi bi-shield-lock me-2"></i>Administrator
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="role-badge teacher-role <?= $user['role'] == 'teacher' ? 'active' : '' ?>" 
                                             data-role="teacher">
                                             <i class="bi bi-person-badge me-2"></i>Teacher
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="role-badge student-role <?= $user['role'] == 'student' ? 'active' : '' ?>" 
                                             data-role="student">
                                             <i class="bi bi-mortarboard me-2"></i>Student
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actual Role Selection Field (Hidden) -->
                                <input type="hidden" name="role" id="role" value="<?= $user['role'] ?>">
                                
                                <div class="alert alert-info" id="roleDescription">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    <span id="roleInfoText">
                                        <?php if ($user['role'] == 'admin'): ?>
                                            Administrators have full access to the system and can manage users, courses, and system settings.
                                        <?php elseif ($user['role'] == 'teacher'): ?>
                                            Teachers can create and manage courses, add learning resources, publish assignments, and grade submissions.
                                        <?php else: ?>
                                            Students can enroll in courses, participate in discussions, submit assignments, and view course resources.
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Account Settings -->
                            <div class="form-section">
                                <h5 class="mb-3">Account Settings</h5>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Account Status</label>
                                    <select name="status" id="status" class="form-select">
                                        <option value="active" <?= ($user['status'] ?? 'active') == 'active' ? 'selected' : '' ?>>
                                            Active - User can log in and use the system normally
                                        </option>
                                        <option value="inactive" <?= ($user['status'] ?? '') == 'inactive' ? 'selected' : '' ?>>
                                            Inactive - User temporarily cannot log in to the system
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="reset_password" id="resetPassword">
                                    <label class="form-check-label" for="resetPassword">
                                        Reset User Password
                                    </label>
                                    <div class="form-text">Checking this will send a password reset email to the user</div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="user_list.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Danger Zone -->
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Danger Zone
                        </h5>
                    </div>
                    <div class="card-body">
                        <p>The following actions may permanently delete data or seriously impact the user. Please proceed with caution.</p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                <i class="bi bi-trash me-1"></i>Delete User
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete the following user?</p>
                                <div class="alert alert-warning">
                                    <strong><?= htmlspecialchars($user['name']) ?></strong> 
                                    (<?= htmlspecialchars($user['email']) ?>)
                                </div>
                                <p class="text-danger mb-0">
                                    <strong>Warning:</strong> This action cannot be undone! Deleting the user will also delete all related:
                                </p>
                                <ul class="text-danger">
                                    <li>Course enrollment records</li>
                                    <li>Assignment submissions</li>
                                    <li>Discussion forum posts and comments</li>
                                    <li>Activity logs</li>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <a href="delete_user.php?id=<?= $user_id ?>&confirm=true" class="btn btn-danger">
                                    Confirm Delete
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- User Information Card -->
                <div class="card mb-4">
                    <div class="card-body text-center pt-4">
                        <div class="avatar-placeholder">
                            <?= mb_substr($user['name'], 0, 1, 'UTF-8') ?>
                        </div>
                        <h5><?= htmlspecialchars($user['name']) ?></h5>
                        <p class="text-muted mb-2"><?= htmlspecialchars($user['email']) ?></p>
                        
                        <?php 
                        $roleBadgeClass = '';
                        $roleText = '';
                        
                        switch($user['role']) {
                            case 'admin':
                                $roleBadgeClass = 'bg-danger';
                                $roleText = 'Administrator';
                                break;
                            case 'teacher':
                                $roleBadgeClass = 'bg-primary';
                                $roleText = 'Teacher';
                                break;
                            default:
                                $roleBadgeClass = 'bg-success';
                                $roleText = 'Student';
                        }
                        ?>
                        
                        <span class="badge <?= $roleBadgeClass ?>"><?= $roleText ?></span>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">User ID:</span>
                            <span class="fw-bold"><?= $user_id ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Registration Date:</span>
                            <span><?= date('Y-m-d', strtotime($user['created_at'] ?? date('Y-m-d'))) ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Last Login:</span>
                            <span><?= !empty($user['last_login']) ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never logged in' ?></span>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Account Status:</span>
                            <span class="badge <?= ($user['status'] ?? 'active') == 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                <?= ($user['status'] ?? 'active') == 'active' ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- User Courses -->
                <?php if (!empty($user_courses)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-journal-check me-2"></i>Enrolled Courses</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($user_courses as $course): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($course['course_name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($course['course_code']) ?></small>
                                    </div>
                                    <a href="view_course.php?id=<?= $course['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recent Activity -->
                <?php if (!empty($user_logs)): ?>
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($user_logs as $log): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">
                                            <?php
                                            $action_text = '';
                                            switch($log['action']) {
                                                case 'create': $action_text = 'Created'; break;
                                                case 'update': $action_text = 'Updated'; break;
                                                case 'delete': $action_text = 'Deleted'; break;
                                                case 'login': $action_text = 'Logged in'; break;
                                                default: $action_text = $log['action']; 
                                            }
                                            
                                            $entity_text = '';
                                            switch($log['entity_type']) {
                                                case 'course': $entity_text = ' course'; break;
                                                case 'assignment': $entity_text = ' assignment'; break;
                                                case 'submission': $entity_text = ' submission'; break;
                                                case 'post': $entity_text = ' post'; break;
                                                case 'system': $entity_text = ' to system'; break;
                                                default: $entity_text = ' ' . $log['entity_type'];
                                            }
                                            
                                            echo $action_text . $entity_text;
                                            ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= date('Y-m-d H:i', strtotime($log['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Role selection
            const roleBadges = document.querySelectorAll('.role-badge');
            const roleInput = document.getElementById('role');
            const roleInfoText = document.getElementById('roleInfoText');
            
            roleBadges.forEach(badge => {
                badge.addEventListener('click', function() {
                    // Remove all active classes
                    roleBadges.forEach(b => b.classList.remove('active'));
                    
                    // Add active class
                    this.classList.add('active');
                    
                    // Update hidden input
                    const role = this.dataset.role;
                    roleInput.value = role;
                    
                    // Update role description
                    switch(role) {
                        case 'admin':
                            roleInfoText.textContent = 'Administrators have full access to the system and can manage users, courses, and system settings.';
                            break;
                        case 'teacher':
                            roleInfoText.textContent = 'Teachers can create and manage courses, add learning resources, publish assignments, and grade submissions.';
                            break;
                        case 'student':
                            roleInfoText.textContent = 'Students can enroll in courses, participate in discussions, submit assignments, and view course resources.';
                            break;
                    }
                });
            });
        });
    </script>
</body>
</html>