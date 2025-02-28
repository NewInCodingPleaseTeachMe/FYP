<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// **Ensure user is logged in and is a teacher or administrator**
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["teacher", "admin"])) {
    die("Access denied!");
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"] ?? "User";

// Handle filtering and search
$course_filter = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Sorting parameters
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'due_date';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Build query conditions
$where_conditions = [];
$params = [];

// Limit query scope based on role
if ($role === "teacher") {
    $where_conditions[] = "courses.teacher_id = ?";
    $params[] = $user_id;
}

// Apply course filter
if (!empty($course_filter)) {
    $where_conditions[] = "assignments.course_id = ?";
    $params[] = $course_filter;
}

// Apply status filter
if (!empty($status_filter)) {
    $now = date('Y-m-d H:i:s');
    
    if ($status_filter === 'upcoming') {
        $where_conditions[] = "assignments.due_date > ?";
        $params[] = $now;
    } elseif ($status_filter === 'past') {
        $where_conditions[] = "assignments.due_date < ?";
        $params[] = $now;
    } elseif ($status_filter === 'today') {
        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');
        $where_conditions[] = "assignments.due_date BETWEEN ? AND ?";
        $params[] = $today_start;
        $params[] = $today_end;
    }
}

// Apply search filter
if (!empty($search_term)) {
    $where_conditions[] = "(assignments.title LIKE ? OR assignments.description LIKE ? OR courses.course_name LIKE ?)";
    $params[] = "%{$search_term}%";
    $params[] = "%{$search_term}%";
    $params[] = "%{$search_term}%";
}

// Assemble WHERE clause
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Query teacher's courses (for filtering)
if ($role === "teacher") {
    $course_stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name");
    $course_stmt->execute([$user_id]);
} else {
    $course_stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
    $course_stmt->execute();
}
$courses = $course_stmt->fetchAll();

// Query assignments
$sql = "SELECT assignments.id, assignments.title, assignments.description, assignments.due_date, 
               assignments.created_at, assignments.max_points, assignments.course_id, assignments.attachment,
               courses.course_name, courses.course_code,
               (SELECT COUNT(*) FROM submissions WHERE submissions.assignment_id = assignments.id) as submission_count
        FROM assignments 
        JOIN courses ON assignments.course_id = courses.id 
        $where_clause
        ORDER BY $sort_by $sort_order";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

// Helper function: Format date
function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}

// Helper function: Calculate assignment status
function getAssignmentStatus($dueDate) {
    $now = time();
    $due = strtotime($dueDate);
    
    if ($due < $now) {
        return ['status' => 'past', 'label' => 'Expired', 'class' => 'danger'];
    } elseif ($due - $now < 86400) { // Less than 24 hours
        return ['status' => 'urgent', 'label' => 'Due Soon', 'class' => 'warning'];
    } else {
        return ['status' => 'active', 'label' => 'Active', 'class' => 'success'];
    }
}

// Helper function: Get file icon
function getFileIcon($filename) {
    if (empty($filename)) return '';
    
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    switch (strtolower($extension)) {
        case 'pdf':
            return 'bi-file-earmark-pdf';
        case 'doc':
        case 'docx':
            return 'bi-file-earmark-word';
        case 'xls':
        case 'xlsx':
            return 'bi-file-earmark-excel';
        case 'ppt':
        case 'pptx':
            return 'bi-file-earmark-ppt';
        case 'zip':
        case 'rar':
            return 'bi-file-earmark-zip';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'bi-file-earmark-image';
        default:
            return 'bi-file-earmark';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Management - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-dark: #0a58ca;
            --primary-light: #e7f0ff;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 15px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .assignment-card .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .assignment-card .card-body {
            padding: 1.5rem;
        }
        
        .assignment-card .card-footer {
            padding: 1rem 1.5rem;
            background-color: white;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .assignment-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .assignment-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .assignment-course {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            background-color: var(--primary-light);
            color: var(--primary-dark);
            font-weight: 500;
        }
        
        .assignment-points {
            background-color: #e9ecef;
            color: #495057;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .assignment-status {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            text-transform: uppercase;
        }
        
        .attachment-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-top: 1rem;
            display: flex;
            align-items: center;
        }
        
        .attachment-box .attachment-icon {
            font-size: 1.5rem;
            margin-right: 0.75rem;
            color: var(--primary-color);
        }
        
        .attachment-info {
            flex-grow: 1;
        }
        
        .attachment-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .filter-card {
            margin-bottom: 2rem;
            background-color: white;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-left: 2.5rem;
            border-radius: 20px;
        }
        
        .search-box .bi-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .stats-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            flex: 1;
            min-width: 200px;
            background-color: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stats-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stats-number {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .progress-slim {
            height: 8px;
            margin-top: 0.5rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .btn-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            z-index: 1000;
        }
        
        .description-truncate {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .badge-count {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.75rem;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn-group {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .search-box {
                width: 100%;
            }
            
            .filter-card .card-body {
                padding: 1rem;
            }
            
            .stats-cards {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-mortarboard-fill me-2"></i> CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-book me-1"></i> Courses
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-clipboard-check me-1"></i> Assignment Management
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="#"><i class="bi bi-list-check me-1"></i> Assignment List</a></li>
                            <li><a class="dropdown-item" href="add_assignment.php"><i class="bi bi-plus-circle me-1"></i> Add Assignment</a></li>
                            <li><a class="dropdown-item" href="grade_overview.php"><i class="bi bi-bar-chart me-1"></i> Grading Overview</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($user_name) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-1"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../modules/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="page-container">
        <!-- Page title -->
        <div class="page-header">
            <h2><i class="bi bi-clipboard-check me-2"></i> Assignment Management</h2>
            <div class="d-flex align-items-center gap-3">
                <a href="add_assignment.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i> Add Assignment
                </a>
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search assignments..." value="<?= htmlspecialchars($search_term) ?>">
                </div>
            </div>
        </div>

        <!-- Display deletion success message -->
        <?php if (isset($_GET["message"]) && $_GET["message"] === "deleted") : ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Assignment successfully deleted!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Assignment statistics cards -->
        <?php
            $total_assignments = count($assignments);
            $past_due = 0;
            $upcoming = 0;
            $today_due = 0;
            $total_submissions = 0;
            
            foreach ($assignments as $assignment) {
                $status = getAssignmentStatus($assignment['due_date']);
                if ($status['status'] === 'past') {
                    $past_due++;
                } elseif ($status['status'] === 'urgent') {
                    $today_due++;
                } else {
                    $upcoming++;
                }
                $total_submissions += $assignment['submission_count'];
            }
            
            // Calculate percentages
            $past_percent = $total_assignments > 0 ? round(($past_due / $total_assignments) * 100) : 0;
            $today_percent = $total_assignments > 0 ? round(($today_due / $total_assignments) * 100) : 0;
            $upcoming_percent = $total_assignments > 0 ? round(($upcoming / $total_assignments) * 100) : 0;
        ?>
        <div class="stats-cards">
            <div class="stats-card">
                <div class="stats-icon text-primary">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <div class="stats-number"><?= $total_assignments ?></div>
                <div class="stats-label">Total Assignments</div>
            </div>
            <div class="stats-card">
                <div class="stats-icon text-success">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="stats-number"><?= $upcoming ?></div>
                <div class="stats-label">Active Assignments</div>
                <div class="progress progress-slim">
                    <div class="progress-bar bg-success" style="width: <?= $upcoming_percent ?>%"></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon text-warning">
                    <i class="bi bi-calendar-week"></i>
                </div>
                <div class="stats-number"><?= $today_due ?></div>
                <div class="stats-label">Due Soon</div>
                <div class="progress progress-slim">
                    <div class="progress-bar bg-warning" style="width: <?= $today_percent ?>%"></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon text-danger">
                    <i class="bi bi-calendar-x"></i>
                </div>
                <div class="stats-number"><?= $past_due ?></div>
                <div class="stats-label">Expired</div>
                <div class="progress progress-slim">
                    <div class="progress-bar bg-danger" style="width: <?= $past_percent ?>%"></div>
                </div>
            </div>
            <div class="stats-card">
                <div class="stats-icon text-info">
                    <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="stats-number"><?= $total_submissions ?></div>
                <div class="stats-label">Submissions Received</div>
            </div>
        </div>

        <!-- Filter card -->
        <div class="card filter-card">
            <div class="card-body">
                <form id="filterForm" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="course_id" class="form-label">
                            <i class="bi bi-filter me-1"></i> Filter by Course
                        </label>
                        <select name="course_id" id="course_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course_name']) ?> 
                                    <?= !empty($course['course_code']) ? "(".htmlspecialchars($course['course_code']).")" : "" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">
                            <i class="bi bi-funnel me-1"></i> Filter by Status
                        </label>
                        <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Statuses</option>
                            <option value="upcoming" <?= $status_filter == 'upcoming' ? 'selected' : '' ?>>Active</option>
                            <option value="today" <?= $status_filter == 'today' ? 'selected' : '' ?>>Due Today</option>
                            <option value="past" <?= $status_filter == 'past' ? 'selected' : '' ?>>Expired</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="sort" class="form-label">
                            <i class="bi bi-sort-down me-1"></i> Sort By
                        </label>
                        <select name="sort" id="sort" class="form-select" onchange="this.form.submit()">
                            <option value="due_date" <?= $sort_by == 'due_date' ? 'selected' : '' ?>>Due Date</option>
                            <option value="created_at" <?= $sort_by == 'created_at' ? 'selected' : '' ?>>Creation Date</option>
                            <option value="title" <?= $sort_by == 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="course_id" <?= $sort_by == 'course_id' ? 'selected' : '' ?>>Course</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex justify-content-end">
                        <button type="button" id="resetFilter" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                        </button>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">
                        <input type="hidden" name="order" value="<?= $sort_order == 'DESC' ? 'desc' : 'asc' ?>">
                    </div>
                </form>
            </div>
        </div>

        <!-- Assignment display area -->
        <?php if (empty($assignments)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-clipboard-x"></i>
                </div>
                <h4>No Assignments</h4>
                <p class="text-muted">There are no assignments matching your criteria</p>
                <?php if (!empty($search_term) || !empty($course_filter) || !empty($status_filter)): ?>
                    <button type="button" id="clearFilters" class="btn btn-outline-primary mt-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                    </button>
                <?php else: ?>
                    <a href="add_assignment.php" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-circle me-1"></i> Add First Assignment
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="assignment-list">
                <?php foreach ($assignments as $assignment): ?>
                    <?php 
                        // Calculate assignment status
                        $status = getAssignmentStatus($assignment['due_date']);
                    ?>
                    <div class="card assignment-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="assignment-title">
                                    <?= htmlspecialchars($assignment['title']) ?>
                                </h5>
                                <span class="assignment-status bg-<?= $status['class'] ?>-subtle text-<?= $status['class'] ?>">
                                    <?= $status['label'] ?>
                                </span>
                            </div>
                            <div class="assignment-meta">
                                <span><i class="bi bi-mortarboard me-1"></i> 
                                    <span class="assignment-course"><?= htmlspecialchars($assignment['course_name']) ?></span>
                                </span>
                                <span><i class="bi bi-calendar me-1"></i> Created: <?= formatDate($assignment['created_at']) ?></span>
                                <span class="assignment-points">
                                    <i class="bi bi-star me-1"></i> <?= $assignment['max_points'] ?? 100 ?> points
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <?php if (!empty($assignment['description'])): ?>
                                        <p class="description-truncate"><?= nl2br(htmlspecialchars($assignment['description'])) ?></p>
                                    <?php else: ?>
                                        <p class="text-muted">No detailed description</p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($assignment['attachment'])): ?>
                                        <div class="attachment-box">
                                            <div class="attachment-icon">
                                                <i class="bi <?= getFileIcon($assignment['attachment']) ?>"></i>
                                            </div>
                                            <div class="attachment-info">
                                                <div class="attachment-name"><?= basename($assignment['attachment']) ?></div>
                                                <div class="text-muted small">Click the button on the right to download attachment</div>
                                            </div>
                                            <a href="<?= htmlspecialchars($assignment['attachment']) ?>" class="btn btn-sm btn-outline-primary" download>
                                                <i class="bi bi-download me-1"></i> Download
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> Assignment Information</h6>
                                            <div class="mb-2">
                                                <strong class="text-danger"><i class="bi bi-calendar2-x me-1"></i> Due Date:</strong><br>
                                                <?= formatDate($assignment['due_date']) ?>
                                            </div>
                                            <div class="mb-2">
                                                <strong><i class="bi bi-file-text me-1"></i> Submitted:</strong><br>
                                                <span class="badge bg-primary"><?= $assignment['submission_count'] ?></span> student submissions
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="view_submissions.php?assignment_id=<?= $assignment['id'] ?>" class="btn btn-primary position-relative">
                                        <i class="bi bi-files me-1"></i> View Submissions
                                        <?php if ($assignment['submission_count'] > 0): ?>
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger badge-count">
                                                <?= $assignment['submission_count'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </a>
                                </div>
                                <div>
                                    <a href="edit_assignment.php?id=<?= $assignment['id'] ?>" class="btn btn-outline-primary me-2">
                                        <i class="bi bi-pencil me-1"></i> Edit
                                    </a>
                                    <button type="button" class="btn btn-outline-danger delete-btn" 
                                            data-id="<?= $assignment['id'] ?>" 
                                            data-title="<?= htmlspecialchars($assignment['title']) ?>">
                                        <i class="bi bi-trash me-1"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Floating add button -->
        <a href="add_assignment.php" class="btn btn-success btn-float">
            <i class="bi bi-plus-lg"></i>
        </a>
    </div>

    <!-- Delete confirmation dialog -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the assignment "<span id="deleteAssignmentTitle"></span>"?</p>
                    <p class="text-danger">This action cannot be undone, and all student submissions will also be deleted.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Confirm Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Search functionality
            let searchTimeout;
            $('#searchInput').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    const searchTerm = $('#searchInput').val().trim();
                    $('#filterForm input[name="search"]').val(searchTerm);
                    $('#filterForm').submit();
                }, 500);
            });
            
            // Reset filter button
            $('#resetFilter').click(function() {
                window.location.href = window.location.pathname;
            });
            
            // Clear filters button
            $('#clearFilters').click(function() {
                window.location.href = window.location.pathname;
            });
            
            // Delete confirmation popup
            $('.delete-btn').click(function() {
                const id = $(this).data('id');
                const title = $(this).data('title');
                
                $('#deleteAssignmentTitle').text(title);
                $('#confirmDelete').attr('href', '../modules/delete_assignment.php?id=' + id + '&redirect=1');
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
            
            // Toggle sort direction
            $('#toggleSort').click(function() {
                const currentOrder = '<?= $sort_order ?>';
                const newOrder = currentOrder === 'DESC' ? 'asc' : 'desc';
                $('input[name="order"]').val(newOrder);
                $('#filterForm').submit();
            });
            
            // Automatically close success message after 5 seconds
            setTimeout(function() {
                $('.alert-success').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>