<?php
session_set_cookie_params(0, '/');
// 🚀 Ensure session starts
session_start();
require_once __DIR__ . "/config/db.php";

// ✅ Check if the user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: views/login.php");
    exit;
}

// ✅ Handle possible missing session keys
$user_id   = $_SESSION["user_id"];
$user_name = $_SESSION["name"] ?? "User"; // Use default value if `user_name` is empty
$role      = $_SESSION["role"] ?? "guest";    // Use default value if `role` is empty

// Get pending teacher count if admin
$pending_teachers_count = 0;
if ($role === "admin") {
    try {
        $pending_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'pending'");
        $pending_teachers_count = $pending_stmt->fetchColumn();
    } catch (PDOException $e) {
        // Silently handle error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CampusConnect</title>
    <!-- Use the latest Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-dark: #0a58ca;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --card-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
            --transition: all 0.3s ease;
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Poppins', sans-serif;
            padding-top: 20px;
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }

        .dashboard-container {
            max-width: 1200px;
            margin: auto;
            padding: 20px;
        }

        .header-card {
            background: linear-gradient(135deg, #0d6efd, #4c6ef5, #6610f2);
            border: none;
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .header-card .card-body {
            padding: 2.5rem;
            color: white;
            text-align: center;
        }

        .header-card h2 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .header-card p {
            opacity: 0.9;
            font-weight: 300;
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        .feature-card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            height: 100%;
            overflow: hidden;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, .2);
        }

        .feature-card .card-body {
            padding: 2rem;
            text-align: center;
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            font-size: 1.8rem;
            color: white;
        }

        .bg-primary-gradient {
            background: linear-gradient(135deg, #0d6efd, #4c6ef5);
        }

        .bg-success-gradient {
            background: linear-gradient(135deg, #198754, #20c997);
        }

        .bg-warning-gradient {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
        }
        
        .bg-danger-gradient {
            background: linear-gradient(135deg, #dc3545, #fd3a5c);
        }
        
        .bg-purple-gradient {
            background: linear-gradient(135deg, #6f42c1, #9461fb);
        }

        .feature-card h4 {
            font-weight: 600;
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #212529;
        }

        .feature-card p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .btn-feature {
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            border-radius: 0.5rem;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            transition: var(--transition);
        }

        .footer-actions {
            margin-top: 2.5rem;
        }

        .footer-actions .btn {
            padding: 0.8rem 1.5rem;
            font-weight: 500;
            border-radius: 0.5rem;
            margin-right: 1rem;
            display: inline-flex;
            align-items: center;
        }

        .footer-actions .btn i {
            margin-right: 0.5rem;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header-card .card-body {
                padding: 2rem 1rem;
            }
            
            .feature-card .card-body {
                padding: 1.5rem;
            }
            
            .feature-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }
        }

        /* Add a simple animation effect */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animated {
            animation: fadeIn 0.6s ease forwards;
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }
        .delay-3 { animation-delay: 0.3s; }
        .delay-4 { animation-delay: 0.4s; }
        .delay-5 { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($user_name); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="#"><i class="fas fa-user-cog me-2"></i>Profile Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="modules/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="dashboard-container" style="margin-top: 70px;">
        <!-- Welcome Card -->
        <div class="card header-card animated">
            <div class="card-body">
                <h2>👋 Welcome back, <?= htmlspecialchars($user_name); ?>!</h2>
                <p>Your current role is <span class="badge bg-light text-primary"><?= ucfirst($role) ?></span></p>
            </div>
        </div>

        <!-- Feature Cards Row -->
        <div class="row g-4">
            <?php if ($role === "admin") : ?>
                <div class="col-md-3 animated delay-1">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-primary-gradient">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4>Manage Users</h4>
                            <p>Add, edit, and manage user accounts and permissions in the system.</p>
                            <a href="views/user_list.php" class="btn btn-primary btn-feature w-100">
                                <i class="fas fa-arrow-right me-2"></i>Start Managing
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 animated delay-2">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-success-gradient">
                                <i class="fas fa-book"></i>
                            </div>
                            <h4>Manage Courses</h4>
                            <p>Create, edit, and organize course content and schedules for the school.</p>
                            <a href="views/course_list.php" class="btn btn-success btn-feature w-100">
                                <i class="fas fa-arrow-right me-2"></i>Start Managing
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 animated delay-3">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-warning-gradient">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h4>Manage Assignments</h4>
                            <p>Create, assign, and grade assignments and assessments for classes.</p>
                            <a href="views/assignments_list.php" class="btn btn-warning btn-feature w-100 text-dark">
                                <i class="fas fa-arrow-right me-2"></i>Start Managing
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 animated delay-4">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-danger-gradient">
                                <i class="fas fa-key"></i>
                            </div>
                            <h4>Manage Tokens</h4>
                            <p>Generate and manage access tokens required for user registration.</p>
                            <a href="views/manage_tokens.php" class="btn btn-danger btn-feature w-100">
                                <i class="fas fa-arrow-right me-2"></i>Manage Tokens
                            </a>
                        </div>
                    </div>
                </div>
                <!-- New: Teacher Approval Card -->
                <div class="col-md-3 animated delay-5">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-purple-gradient position-relative">
                                <i class="fas fa-user-check"></i>
                                <?php if ($pending_teachers_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    <?= $pending_teachers_count ?>
                                    <span class="visually-hidden">pending teachers</span>
                                </span>
                                <?php endif; ?>
                            </div>
                            <h4>Teacher Approval</h4>
                            <p>Review and approve teacher registration requests.</p>
                            <a href="views/teacher_approval.php" class="btn btn-purple btn-feature w-100" style="background-color: #6f42c1; color: white;">
                                <?php if ($pending_teachers_count > 0): ?>
                                <i class="fas fa-exclamation-circle me-2"></i>Review Requests
                                <?php else: ?>
                                <i class="fas fa-arrow-right me-2"></i>Review Requests
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === "teacher") : ?>
                <div class="col-md-4 animated delay-1">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-primary-gradient">
                                <i class="fas fa-book"></i>
                            </div>
                            <h4>My Courses</h4>
                            <p>View and manage all courses you are currently teaching.</p>
                            <a href="views/course_list.php" class="btn btn-primary btn-feature w-100">
                                <i class="fas fa-arrow-right me-2"></i>View Courses
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 animated delay-2">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-success-gradient">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h4>Assignment Management</h4>
                            <p>Create, assign, and grade assignments and tasks submitted by students.</p>
                            <a href="views/assignments_list.php" class="btn btn-success btn-feature w-100">
                                <i class="fas fa-arrow-right me-2"></i>View Assignments
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 animated delay-3">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-warning-gradient">
                                <i class="fas fa-folder"></i>
                            </div>
                            <h4>Course Resources</h4>
                            <p>Upload and manage course materials, handouts, and learning resources.</p>
                            <a href="views/upload_resource.php" class="btn btn-warning btn-feature w-100 text-dark">
                                <i class="fas fa-arrow-right me-2"></i>Upload Resources
                            </a>
                        </div>
                    </div>
                </div>
            <?php elseif ($role === "student") : ?>
                <div class="col-md-4 animated delay-1">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-primary-gradient">
                                <i class="fas fa-book-reader"></i>
                            </div>
                            <h4>My Courses</h4>
                            <p>View all courses you are currently enrolled in and their content.</p>
                            <a href="views/course_list.php" class="btn btn-primary btn-feature w-100">
                                <i class="fas fa-arrow-right me-2"></i>View Courses
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 animated delay-2">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-success-gradient">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h4>Enroll in New Courses</h4>
                            <p>Browse the available course catalog and enroll in new learning courses.</p>
                            <a href="views/course_enroll.php" class="btn btn-success btn-feature w-100">
                                <i class="fas fa-arrow-right me-2"></i>Browse Courses
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 animated delay-3">
                    <div class="card feature-card">
                        <div class="card-body">
                            <div class="feature-icon bg-warning-gradient">
                                <i class="fas fa-upload"></i>
                            </div>
                            <h4>My Assignments</h4>
                            <p>View and submit your course assignments and tasks.</p>
                            <a href="views/submit_assignment.php" class="btn btn-warning btn-feature w-100 text-dark">
                                <i class="fas fa-arrow-right me-2"></i>Submit Assignments
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Additional Feature Area -->
        <div class="row g-4 mt-4">
            <!-- Statistics Card -->
            <div class="col-md-4 animated delay-1">
                <div class="card border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                            <i class="fas fa-calendar-alt text-primary fs-4"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-semibold">Today's Date</h6>
                            <p class="mb-0 text-muted"><?= date("F j, Y") ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Notifications -->
            <div class="col-md-8 animated delay-2">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title fw-semibold mb-3"><i class="fas fa-bell me-2 text-warning"></i>Notifications</h5>
                        <?php if ($role === "admin" && $pending_teachers_count > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            You have <strong><?= $pending_teachers_count ?></strong> pending teacher registration request(s) to review.
                            <a href="views/teacher_approval.php" class="alert-link">Review now</a>
                        </div>
                        <?php else: ?>
                        <p class="text-muted">Welcome to the CampusConnect system. Important notifications will be displayed here.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 🚀 Footer Actions -->
        <div class="footer-actions">
            <a href="index.php" class="btn btn-outline-primary"><i class="fas fa-home"></i> Return to Home</a>
            <a href="modules/logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>  

    <!-- Bootstrap JS and Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>