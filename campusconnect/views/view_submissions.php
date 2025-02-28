<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure the user is a teacher
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

// Check if assignment_id is provided
if (!isset($_GET["assignment_id"]) || empty($_GET["assignment_id"])) {
    header("Location: assignments_list.php?error=missing_id");
    exit;
}

$assignment_id = $_GET["assignment_id"];
$teacher_id = $_SESSION["user_id"];

// Fetch assignment information
$stmt = $pdo->prepare("SELECT a.*, c.course_name, c.course_code, c.id AS course_id 
                      FROM assignments a 
                      JOIN courses c ON a.course_id = c.id 
                      WHERE a.id = ? AND c.teacher_id = ?");
$stmt->execute([$assignment_id, $teacher_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    header("Location: assignments_list.php?error=not_found");
    exit;
}

// Fetch teacher's name
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$teacher = $stmt->fetch();

// Fetch total submissions and graded count
$stmt = $pdo->prepare("SELECT 
                      COUNT(*) as total_submissions,
                      SUM(CASE WHEN grade IS NOT NULL THEN 1 ELSE 0 END) as graded_count,
                      AVG(grade) as average_grade,
                      MAX(grade) as max_grade,
                      MIN(CASE WHEN grade IS NOT NULL THEN grade ELSE NULL END) as min_grade
                      FROM submissions 
                      WHERE assignment_id = ?");
$stmt->execute([$assignment_id]);
$stats = $stmt->fetch();

// Fetch total number of students in the course
$stmt = $pdo->prepare("SELECT COUNT(*) as total_students 
                      FROM user_courses
                      WHERE course_id = ?");
$stmt->execute([$assignment['course_id']]);
$course_stats = $stmt->fetch();

// Calculate submission rate
$submission_rate = 0;
if ($course_stats['total_students'] > 0) {
    $submission_rate = ($stats['total_submissions'] / $course_stats['total_students']) * 100;
}

// Format deadline
$deadline = new DateTime($assignment["due_date"]);
$now = new DateTime();
$interval = $now->diff($deadline);
$is_overdue = $now > $deadline;
$deadline_status = "";

if ($is_overdue) {
    // Deadline has passed
    $deadline_status = "Deadline Passed";
} else {
    // Deadline not passed, calculate remaining time
    if ($interval->days > 0) {
        $deadline_status = $interval->days . " days remaining";
    } else if ($interval->h > 0) {
        $deadline_status = $interval->h . " hours remaining";
    } else {
        $deadline_status = $interval->i . " minutes remaining";
    }
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 B";
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Helper function to get full file path
function getFullFilePath($relativePath) {
    // If the path starts with / or C:, it's already an absolute path
    if (preg_match('/^(\/|[A-Za-z]:)/', $relativePath)) {
        return $relativePath;
    }
    
    // Otherwise, convert relative path to absolute path
    // Assuming $relativePath is relative to the website root directory
    return $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($relativePath, '/');
}

// Fetch student submissions
$stmt = $pdo->prepare("SELECT s.id, s.submitted_at, s.file_name, s.file_path, s.grade, s.feedback,
                      u.name, u.id as student_id, u.student_number, u.email
                      FROM submissions s 
                      JOIN users u ON s.student_id = u.id 
                      WHERE s.assignment_id = ?
                      ORDER BY s.submitted_at DESC");
$stmt->execute([$assignment_id]);
$submissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submissions - <?= htmlspecialchars($assignment["title"]); ?> | CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
    :root {
        --bs-primary-rgb: 13, 110, 253;
        --bs-success-rgb: 25, 135, 84;
        --bs-info-rgb: 13, 202, 240;
        --bs-warning-rgb: 255, 193, 7;
        --bs-danger-rgb: 220, 53, 69;
    }
    body {
        background-color: #f8f9fa;
        color: #212529;
    }
    .navbar-brand {
        font-weight: 700;
    }
    .card {
        border: none;
        border-radius: 0.8rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 1.5rem;
    }
    .card-header {
        background-color: #fff;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }
    .stats-card {
        transition: all 0.3s;
        border-radius: 0.5rem;
    }
    .stats-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }
    .stats-icon {
        width: 48px;
        height: 48px;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-right: 1rem;
    }
    .stats-value {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0;
        line-height: 1.2;
    }
    .stats-label {
        color: #6c757d;
        font-size: 0.875rem;
        margin-bottom: 0;
    }
    .progress {
        height: 8px;
        border-radius: 1rem;
    }
    .grade-badge {
        width: 60px;
        font-weight: 600;
    }
    .table-responsive {
        border-radius: 0.5rem;
        overflow: hidden;
    }
    .table th {
        font-weight: 600;
        color: #495057;
    }
    .deadline-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 1rem;
    }
    .search-box {
        position: relative;
    }
    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
    }
    .search-box input {
        padding-left: 2.5rem;
        border-radius: 2rem;
    }
    .file-thumbnail {
        width: 36px;
        height: 36px;
        border-radius: 0.3rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        padding: 1rem;
    }
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_length {
        padding: 1rem 1rem 0.5rem 1rem;
    }
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
    }
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
    }
    .form-floating > label {
        padding: 0.5rem 0.75rem;
    }
    .form-floating > .form-control {
        padding: 0.5rem 0.75rem;
    }
    .assignmentMainInfo {
        background-color: rgba(var(--bs-primary-rgb), 0.05);
        border-radius: 0.5rem;
        padding: 1rem;
    }
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 0.5rem;
        border: none;
        transition: all 0.2s;
    }
    .action-btn:hover {
        transform: translateY(-2px);
    }
    .student-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #e9ecef;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        color: #6c757d;
        margin-right: 0.5rem;
    }
    .submission-time {
        display: flex;
        align-items: center;
    }
    .submission-time .indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 0.5rem;
    }
    .course-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 0.5rem;
        background-color: #e9ecef;
        color: #495057;
    }
    @media print {
        .no-print {
            display: none !important;
        }
        .card {
            box-shadow: none !important;
            border: 1px solid #dee2e6 !important;
        }
        body {
            background-color: #fff !important;
        }
    }
</style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-mortarboard-fill me-2"></i>CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-house-door-fill me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="assignments_list.php">
                            <i class="bi bi-journal-check me-1"></i>Assignments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="students_list.php">
                            <i class="bi bi-people-fill me-1"></i>Students
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3">
                        <i class="bi bi-person-workspace me-1"></i><?= htmlspecialchars($teacher["name"] ?? "Teacher") ?>
                    </span>
                    <a href="../modules/logout.php" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-4 no-print">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                <li class="breadcrumb-item"><a href="course_assignments.php?course_id=<?= $assignment['course_id'] ?>"><?= htmlspecialchars($assignment["course_name"]); ?></a></li>
                <li class="breadcrumb-item"><a href="assignments_list.php">Assignments</a></li>
                <li class="breadcrumb-item active">View Submissions</li>
            </ol>
        </nav>

        <!-- Assignment Information Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="bi bi-clipboard-check text-primary me-2"></i><?= htmlspecialchars($assignment["title"]); ?></h4>
                <span class="course-badge">
                    <i class="bi bi-book me-1"></i><?= htmlspecialchars($assignment["course_code"] ?? ""); ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Assignment Details -->
                        <div class="assignmentMainInfo mb-3">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-calendar-event text-primary me-2"></i>
                                        <div>
                                            <small class="d-block text-muted">Deadline</small>
                                            <span class="fw-medium"><?= date('Y-m-d H:i', strtotime($assignment["due_date"] ?? "now")); ?></span>
                                            <?php if ($is_overdue): ?>
                                                <span class="deadline-badge bg-danger text-white ms-2">Deadline Passed</span>
                                            <?php else: ?>
                                                <span class="deadline-badge bg-success text-white ms-2"><?= $deadline_status ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-trophy text-primary me-2"></i>
                                        <div>
                                            <small class="d-block text-muted">Total Points</small>
                                            <span class="fw-medium"><?= $assignment["total_points"] ?? 100 ?> points</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Assignment Description -->
                        <h5><i class="bi bi-info-circle me-2"></i>Assignment Description</h5>
                        <div class="p-3 bg-light rounded mb-3">
                            <?= nl2br(htmlspecialchars($assignment["description"] ?? "No description")); ?>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mb-3 no-print">
                            <a href="assignments_list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Back to Assignments
                            </a>
                            <a href="edit_assignment.php?id=<?= $assignment_id ?>" class="btn btn-outline-primary ms-2">
                                <i class="bi bi-pencil me-1"></i>Edit Assignment
                            </a>
                            <a href="assignment_report.php?id=<?= $assignment_id ?>" class="btn btn-outline-success ms-2">
                                <i class="bi bi-file-earmark-bar-graph me-1"></i>View Report
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <!-- Statistics Card -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Submission Statistics</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="stats-card p-3 bg-light h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="stats-icon bg-primary bg-opacity-10 text-primary">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </div>
                                                <div>
                                                    <div class="stats-value text-primary"><?= $stats['total_submissions'] ?? 0 ?></div>
                                                    <p class="stats-label">Total Submissions</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stats-card p-3 bg-light h-100">
                                            <div class="d-flex align-items-center">
                                                <div class="stats-icon bg-success bg-opacity-10 text-success">
                                                    <i class="bi bi-check-circle"></i>
                                                </div>
                                                <div>
                                                    <div class="stats-value text-success"><?= $stats['graded_count'] ?? 0 ?></div>
                                                    <p class="stats-label">Graded</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <!-- Grading Progress Bar -->
                                        <?php
                                        $percent = ($stats['total_submissions'] > 0) ? 
                                            round(($stats['graded_count'] / $stats['total_submissions']) * 100) : 0;
                                        ?>
                                        <div class="stats-card p-3 bg-light">
                                            <p class="stats-label mb-1">Grading Progress</p>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percent ?>%" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <small class="text-muted"><?= $percent ?>% Completed</small>
                                                <small class="text-muted"><?= $stats['graded_count'] ?>/<?= $stats['total_submissions'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($stats['graded_count'] > 0): ?>
                                    <div class="col-12">
                                        <div class="stats-card p-3 bg-light">
                                            <p class="stats-label mb-2">Grade Statistics</p>
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Average Grade</span>
                                                <span class="fw-bold"><?= round($stats['average_grade'], 1) ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between mb-1">
                                                <span>Highest Grade</span>
                                                <span class="fw-bold"><?= $stats['max_grade'] ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>Lowest Grade</span>
                                                <span class="fw-bold"><?= $stats['min_grade'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="col-12">
                                        <div class="stats-card p-3 bg-light">
                                            <p class="stats-label mb-1">Submission Rate</p>
                                            <div class="progress">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: <?= round($submission_rate) ?>%" aria-valuenow="<?= round($submission_rate) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <small class="text-muted"><?= round($submission_rate) ?>% of students submitted</small>
                                                <small class="text-muted"><?= $stats['total_submissions'] ?>/<?= $course_stats['total_students'] ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      <!-- Student Submissions List -->
      <?php if (empty($submissions)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>No student submissions yet
            </div>
        <?php else: ?>
            <!-- Submission Filter and Search -->
            <div class="card no-print">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-lg-5 col-md-6">
                            <div class="search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" id="searchInput" class="form-control" placeholder="Search students...">
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <select id="filterGrade" class="form-select">
                                <option value="all">All Statuses</option>
                                <option value="graded">Graded</option>
                                <option value="ungraded">Ungraded</option>
                                <option value="late">Late Submissions</option>
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-12 text-md-end">
                            <button id="btnExport" class="btn btn-success">
                                <i class="bi bi-file-earmark-excel me-1"></i>Export Grades
                            </button>
                            <button id="btnBatchGrade" class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#batchGradeModal">
                                <i class="bi bi-check2-all me-1"></i>Batch Grading
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submissions Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-check text-primary me-2"></i>Student Submissions List</h5>
                    <div class="no-print">
                        <button class="btn btn-sm btn-outline-secondary" id="printBtn">
                            <i class="bi bi-printer me-1"></i>Print
                        </button>
                        <button class="btn btn-sm btn-outline-primary ms-2" id="refreshBtn">
                            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="submissionsTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" width="5%">#</th>
                                    <th scope="col" width="20%">Student</th>
                                    <th scope="col" width="15%">Submission Time</th>
                                    <th scope="col" width="20%">File</th>
                                    <th scope="col" width="10%">Grade</th>
                                    <th scope="col" width="15%">Feedback</th>
                                    <th scope="col" width="15%" class="no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $counter = 1; foreach ($submissions as $submission): ?>
                                    <tr data-student-name="<?= htmlspecialchars($submission['name']) ?>" 
                                        data-student-id="<?= $submission['student_id'] ?>"
                                        data-grade-status="<?= ($submission['grade'] !== null) ? 'graded' : 'ungraded' ?>"
                                        data-late-status="<?= (isset($assignment["due_date"]) && strtotime($submission['submitted_at']) > strtotime($assignment["due_date"])) ? 'late' : 'ontime' ?>">
                                        <td><?= $counter++ ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="student-avatar">
                                                    <?= mb_substr(htmlspecialchars($submission['name']), 0, 1) ?>
                                                </div>
                                                <div>
                                                    <div class="fw-medium"><?= htmlspecialchars($submission['name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($submission['student_number'] ?? 'No ID') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="submission-time">
                                                <?php 
                                                // Check if submission is late
                                                $is_late = false;
                                                if (isset($assignment["due_date"]) && strtotime($submission['submitted_at']) > strtotime($assignment["due_date"])) {
                                                    $is_late = true;
                                                    echo '<div class="indicator bg-warning"></div>';
                                                } else {
                                                    echo '<div class="indicator bg-success"></div>';
                                                }
                                                ?>
                                                <div>
                                                    <?= date('Y-m-d H:i', strtotime($submission['submitted_at'])) ?>
                                                    <?php if ($is_late): ?>
                                                        <small class="d-block text-warning">Late Submission</small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $extension = pathinfo($submission['file_name'], PATHINFO_EXTENSION);
                                                $bg_color = 'bg-secondary';
                                                $icon = 'bi-file-earmark';

                                                switch(strtolower($extension)) {
                                                    case 'pdf': 
                                                        $bg_color = 'bg-danger'; 
                                                        $icon = 'bi-file-earmark-pdf'; 
                                                        break;
                                                    case 'doc': 
                                                    case 'docx': 
                                                        $bg_color = 'bg-primary'; 
                                                        $icon = 'bi-file-earmark-word'; 
                                                        break;
                                                    case 'xls': 
                                                    case 'xlsx': 
                                                        $bg_color = 'bg-success'; 
                                                        $icon = 'bi-file-earmark-excel'; 
                                                        break;
                                                    case 'ppt': 
                                                    case 'pptx': 
                                                        $bg_color = 'bg-warning'; 
                                                        $icon = 'bi-file-earmark-slides'; 
                                                        break;
                                                    case 'zip': 
                                                    case 'rar': 
                                                        $bg_color = 'bg-secondary'; 
                                                        $icon = 'bi-file-earmark-zip'; 
                                                        break;
                                                    case 'jpg': 
                                                    case 'jpeg': 
                                                    case 'png': 
                                                    case 'gif': 
                                                        $bg_color = 'bg-info'; 
                                                        $icon = 'bi-file-earmark-image'; 
                                                        break;
                                                }
                                                ?>
                                                <div class="file-thumbnail <?= $bg_color ?> bg-opacity-10 text-<?= str_replace('bg-', '', $bg_color) ?> me-2">
                                                    <i class="bi <?= $icon ?>"></i>
                                                </div>
                                                <div>
                                                    <div class="text-truncate" style="max-width: 150px;">
                                                        <?= htmlspecialchars($submission['file_name']) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php
                                                        // Dynamically calculate file size
                                                        $fullPath = getFullFilePath($submission['file_path']);
                                                        echo file_exists($fullPath) ? formatFileSize(filesize($fullPath)) : '0 B';
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                            <a href="<?= htmlspecialchars($submission['file_path']) ?>" download class="btn btn-sm btn-outline-primary mt-1 no-print">
                                                <i class="bi bi-download me-1"></i>Download
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($submission['grade'] !== null): ?>
                                                <span class="badge bg-success grade-badge"><?= $submission['grade'] ?> points</span>
                                                <div class="progress mt-1" style="height: 4px;">
                                                    <?php 
                                                    $gradePercent = ($submission['grade'] / ($assignment["total_points"] ?? 100)) * 100;
                                                    $gradeClass = $gradePercent >= 80 ? 'bg-success' : ($gradePercent >= 60 ? 'bg-warning' : 'bg-danger');
                                                    ?>
                                                    <div class="progress-bar <?= $gradeClass ?>" role="progressbar" style="width: <?= $gradePercent ?>%"></div>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary grade-badge">Not Graded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($submission['feedback'])): ?>
                                                <div class="text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($submission['feedback']) ?>">
                                                    <?= htmlspecialchars(mb_substr($submission['feedback'], 0, 25)) ?><?= (mb_strlen($submission['feedback']) > 25) ? '...' : '' ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">No Feedback</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="no-print">
                                            <div class="d-flex">
                                                <a href="grade_submission.php?submission_id=<?= $submission['id'] ?>" class="btn btn-sm btn-primary me-1" title="Grade">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-info me-1" data-bs-toggle="modal" 
                                                        data-bs-target="#feedbackModal" 
                                                        data-submission-id="<?= $submission['id'] ?>"
                                                        data-student-name="<?= htmlspecialchars($submission['name']) ?>"
                                                        data-feedback="<?= htmlspecialchars($submission['feedback'] ?? '') ?>"
                                                        title="Feedback">
                                                    <i class="bi bi-chat-dots"></i>
                                                </button>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $submission['id'] ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="bi bi-three-dots"></i>
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $submission['id'] ?>">
                                                        <li><a class="dropdown-item" href="<?= htmlspecialchars($submission['file_path']) ?>" download>
                                                            <i class="bi bi-download me-2"></i>Download File
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="teacher_view_submission.php?id=<?= $submission['id'] ?>">
                                                            <i class="bi bi-eye me-2"></i>View Details
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="mailto:<?= htmlspecialchars($submission['email']) ?>">
                                                            <i class="bi bi-envelope me-2"></i>Send Email
                                                        </a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="../modules/delete_submission.php?id=<?= $submission['id'] ?>" onclick="return confirm('Are you sure you want to delete this submission?')">
                                                            <i class="bi bi-trash me-2"></i>Delete Submission
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Total <?= count($submissions) ?> submissions</small>
                        <div class="no-print">
                            <button class="btn btn-sm btn-outline-success" id="quickGradeBtn" data-bs-toggle="modal" data-bs-target="#batchGradeModal">
                                <i class="bi bi-lightning-charge me-1"></i>Quick Grade
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <!-- Feedback Modal -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-chat-square-text me-2"></i>
                        Feedback for <span id="studentNameLabel"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="feedbackForm" action="../modules/save_feedback.php" method="post">
                        <input type="hidden" id="submissionId" name="submission_id">
                        <div class="mb-3">
                            <label for="feedback" class="form-label">Feedback</label>
                            <textarea class="form-control" id="feedback" name="feedback" rows="5" placeholder="Enter feedback for the student's assignment..."></textarea>
                            <div class="form-text">Feedback will be shown to the student to help them understand the grading and improvement areas.</div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="notifyStudent" name="notify_student" checked>
                                <label class="form-check-label" for="notifyStudent">
                                    Notify Student
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="feedbackForm" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i>Save Feedback
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Batch Grading Modal -->
    <div class="modal fade" id="batchGradeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check2-all me-2"></i>Batch Grading
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>Batch grading allows you to quickly grade multiple student assignments, improving efficiency.
                    </div>
                    
                    <form id="batchGradeForm" action="../modules/batch_grade.php" method="post">
                        <input type="hidden" name="assignment_id" value="<?= $assignment_id ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th width="30%">Student</th>
                                        <th width="20%">Submission Time</th>
                                        <th width="15%">Status</th>
                                        <th width="30%">Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($submissions as $submission): ?>
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input submission-check" type="checkbox" 
                                                       name="submissions[]" value="<?= $submission['id'] ?>"
                                                       <?= $submission['grade'] !== null ? 'checked' : '' ?>>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="student-avatar me-2">
                                                    <?= mb_substr(htmlspecialchars($submission['name']), 0, 1) ?>
                                                </div>
                                                <?= htmlspecialchars($submission['name']) ?>
                                            </div>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($submission['submitted_at'])) ?></td>
                                        <td>
                                            <?php if ($submission['grade'] !== null): ?>
                                                <span class="badge bg-success">Graded: <?= $submission['grade'] ?> points</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Not Graded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control grade-input" 
                                                       name="grades[<?= $submission['id'] ?>]" 
                                                       min="0" max="<?= $assignment["total_points"] ?? 100 ?>" 
                                                       placeholder="Grade" 
                                                       value="<?= $submission['grade'] ?? '' ?>">
                                                <span class="input-group-text">/ <?= $assignment["total_points"] ?? 100 ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mb-3 mt-3">
                            <label for="batchFeedback" class="form-label">Batch Feedback (Optional)</label>
                            <textarea class="form-control" id="batchFeedback" name="batch_feedback" rows="3" placeholder="Enter general feedback for all selected students..."></textarea>
                            <div class="form-text">This feedback will be applied to all selected submissions. You can also add personalized feedback for each student later.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="batchGradeForm" class="btn btn-primary">
                        <i class="bi bi-check2-all me-1"></i>Save All Grades
                    </button>
                </div>
            </div>
        </div>
    </div>
    <footer class="bg-light py-3 mt-5 no-print">
        <div class="container text-center text-muted">
            <small>© 2025 CampusConnect - The Power to Connect Campuses</small>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <a href="#" class="btn btn-primary btn-lg position-fixed bottom-0 end-0 m-4 no-print" id="backToTop" style="display: none; border-radius: 50%; width: 50px; height: 50px;">
        <i class="bi bi-arrow-up"></i>
    </a>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <script>
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    
        // Initialize DataTables
        $(document).ready(function() {
            const table = $('#submissionsTable').DataTable({
                responsive: true,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No data available",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    }
                },
                dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                     "<'row'<'col-sm-12'tr>>" +
                     "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                order: [[2, 'desc']] // Sort by submission time
            });
            
            // Custom filter
            $.fn.dataTable.ext.search.push(
                function(settings, data, dataIndex) {
                    const filterValue = $('#filterGrade').val();
                    if (filterValue === 'all') return true;
                    
                    const row = table.row(dataIndex).node();
                    if (filterValue === 'graded' && $(row).attr('data-grade-status') === 'graded') return true;
                    if (filterValue === 'ungraded' && $(row).attr('data-grade-status') === 'ungraded') return true;
                    if (filterValue === 'late' && $(row).attr('data-late-status') === 'late') return true;
                    
                    return false;
                }
            );
            
            // Filter event
            $('#filterGrade').on('change', function() {
                table.draw();
            });
            
            // Refresh button
            $('#refreshBtn').on('click', function() {
                location.reload();
            });
        });
        
        // Feedback modal
        const feedbackModal = document.getElementById('feedbackModal');
        if (feedbackModal) {
            feedbackModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const submissionId = button.getAttribute('data-submission-id');
                const studentName = button.getAttribute('data-student-name');
                const feedback = button.getAttribute('data-feedback');
                
                document.getElementById('submissionId').value = submissionId;
                document.getElementById('studentNameLabel').textContent = studentName;
                document.getElementById('feedback').value = feedback;
            });
        }
        
        // Batch grading modal
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.submission-check');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
        
        // Grade input field limits
        document.querySelectorAll('.grade-input').forEach(input => {
            input.addEventListener('input', function() {
                const max = parseInt(this.max);
                if (this.value > max) {
                    this.value = max;
                }
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });
        
        // Export grades
        document.getElementById('btnExport').addEventListener('click', function() {
            // Prepare export data
            const rows = document.querySelectorAll('#submissionsTable tbody tr');
            const data = [
                ['Student ID', 'Name', 'Submission Time', 'Grade', 'Feedback']
            ];
            
            rows.forEach(row => {
                const studentId = row.getAttribute('data-student-id');
                const studentName = row.getAttribute('data-student-name');
                const cells = row.querySelectorAll('td');
                
                // Get submission time
                const submissionTime = cells[2].textContent.trim();
                
                // Get grade
                let grade = 'Not Graded';
                if (cells[4].querySelector('.badge.bg-success')) {
                    grade = cells[4].querySelector('.badge.bg-success').textContent.replace(' points', '');
                }
                
                // Get feedback
                let feedback = 'No Feedback';
                if (cells[5].textContent.trim() !== 'No Feedback') {
                    feedback = cells[5].textContent.trim();
                }
                
                data.push([studentId, studentName, submissionTime, grade, feedback]);
            });
            
            // Create worksheet
            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Grades");
            
            // Set column widths
            const colWidths = [{ wch: 10 }, { wch: 10 }, { wch: 20 }, { wch: 8 }, { wch: 30 }];
            ws['!cols'] = colWidths;
            
            // Export file
            const fileName = `${document.title.split(' - ')[0]}_Grades_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(wb, fileName);
        });
        
        // Print functionality
        document.getElementById('printBtn').addEventListener('click', function() {
            window.print();
        });
        
        // Back to top button
        window.onscroll = function() {scrollFunction()};
        
        function scrollFunction() {
            if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                document.getElementById("backToTop").style.display = "flex";
            } else {
                document.getElementById("backToTop").style.display = "none";
            }
        }
        
        document.getElementById('backToTop').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    </script>
</body>
</html>