<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is logged in and is a student
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../login.php");
    exit("Access denied!");
}

$user_id = $_SESSION["user_id"];

// Get all assignments
$stmt = $pdo->prepare("SELECT a.id as assignment_id, a.title, a.description, a.due_date, 
                     c.course_name, c.id as course_id
                   FROM assignments a
                   JOIN courses c ON a.course_id = c.id
                   JOIN user_courses uc ON c.id = uc.course_id AND uc.user_id = ?
                   GROUP BY a.id");
$stmt->execute([$user_id]);
$assignments = $stmt->fetchAll();

// Get all submissions
$submissions_stmt = $pdo->prepare("SELECT * FROM submissions WHERE student_id = ?");
$submissions_stmt->execute([$user_id]);
$all_submissions = $submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize submission data by assignment ID
$submission_by_assignment = [];
foreach ($all_submissions as $sub) {
    $assignment_id = $sub['assignment_id'];
    if (!isset($submission_by_assignment[$assignment_id])) {
        $submission_by_assignment[$assignment_id] = [];
    }
    $submission_by_assignment[$assignment_id][] = $sub;
}

// Merge data
$submissions = [];
foreach ($assignments as $assignment) {
    $assignment_id = $assignment['assignment_id'];
    $assignment_submissions = isset($submission_by_assignment[$assignment_id]) ? $submission_by_assignment[$assignment_id] : [];
    
    // Add assignment info when there's no submission
    if (empty($assignment_submissions)) {
        $submissions[] = $assignment;
    } else {
        // Find graded submissions and the latest submission
        $graded_submission = null;
        $latest_submission = null;
        $latest_time = 0;
        
        foreach ($assignment_submissions as $sub) {
            if (!empty($sub['grade'])) {
                if ($graded_submission === null || strtotime($sub['submitted_at']) > strtotime($graded_submission['submitted_at'])) {
                    $graded_submission = $sub;
                }
            }
            
            $sub_time = strtotime($sub['submitted_at']);
            if ($sub_time > $latest_time) {
                $latest_time = $sub_time;
                $latest_submission = $sub;
            }
        }
        
        // Prioritize displaying graded submissions
        $submission_to_use = $graded_submission ?? $latest_submission;
        
        // Merge assignment info and submission info
        $merged = array_merge($assignment, [
            'submission_id' => $submission_to_use['id'],
            'file_name' => $submission_to_use['file_name'],
            'submitted_at' => $submission_to_use['submitted_at'],
            'grade' => $submission_to_use['grade'] ?? null,
            'feedback' => $submission_to_use['feedback'] ?? null,
            'score' => $submission_to_use['score'] ?? null,
            'graded_at' => $submission_to_use['graded_at'] ?? null
        ]);
        $submissions[] = $merged;
    }
}

// Get statistics
$total_assignments = count($submissions);
$graded_assignments = 0;
$pending_assignments = 0;
$average_score = 0;
$total_score = 0;
$score_count = 0;

foreach ($submissions as $submission) {
    if (!empty($submission['submission_id'])) {
        if (!empty($submission['grade'])) {
            $graded_assignments++;
            if (!empty($submission['score'])) {
                $total_score += $submission['score'];
                $score_count++;
            }
        } else {
            $pending_assignments++;
        }
    }
}

if ($score_count > 0) {
    $average_score = round($total_score / $score_count, 1);
}

// Get grade distribution
$grade_distribution = [
    'A' => 0,
    'B' => 0,
    'C' => 0,
    'D' => 0,
    'F' => 0
];

foreach ($submissions as $submission) {
    if (!empty($submission['grade']) && isset($grade_distribution[$submission['grade']])) {
        $grade_distribution[$submission['grade']]++;
    }
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$course_filter = isset($_GET['course']) ? $_GET['course'] : '';

// Prepare course list for filtering
$courses_stmt = $pdo->prepare("SELECT DISTINCT c.id, c.course_name 
                              FROM courses c
                              JOIN user_courses uc ON c.id = uc.course_id
                              WHERE uc.user_id = ?");
$courses_stmt->execute([$user_id]);
$courses = $courses_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assignments - CampusConnect</title>
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
        .stat-card {
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .grade-badge {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            margin-right: 10px;
        }
        .grade-A {
            background-color: #198754;
        }
        .grade-B {
            background-color: #0d6efd;
        }
        .grade-C {
            background-color: #6c757d;
        }
        .grade-D {
            background-color: #fd7e14;
        }
        .grade-F {
            background-color: #dc3545;
        }
        .submission-card {
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }
        .submission-card:hover {
            transform: translateX(5px);
        }
        .graded-submission {
            border-left-color: #198754;
        }
        .pending-submission {
            border-left-color: #fd7e14;
        }
        .overdue-submission {
            border-left-color: #dc3545;
        }
        .no-submission {
            border-left-color: #6c757d;
        }
        .progress-thin {
            height: 6px;
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
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-journal-richtext me-1"></i>My Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-file-earmark-text me-1"></i>My Assignments
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["name"] ?? "Student") ?> 
                        <span class="badge bg-light text-primary ms-1">Student</span>
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
                <li class="breadcrumb-item active" aria-current="page">My Assignments</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-file-earmark-text me-2"></i>
                    My Assignments
                </h2>
                <p class="text-muted">View and manage all your submitted assignments</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="../dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>Return to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card bg-primary text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Total Submissions</h6>
                                <h2 class="mb-0"><?= $total_assignments ?></h2>
                            </div>
                            <i class="bi bi-file-earmark-text fs-1 text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Graded</h6>
                                <h2 class="mb-0"><?= $graded_assignments ?></h2>
                            </div>
                            <i class="bi bi-check-circle fs-1 text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card bg-warning text-dark h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-dark-50">Pending</h6>
                                <h2 class="mb-0"><?= $pending_assignments ?></h2>
                            </div>
                            <i class="bi bi-hourglass-split fs-1 text-dark-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stat-card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-white-50">Average Score</h6>
                                <h2 class="mb-0"><?= $average_score ?>/100</h2>
                            </div>
                            <i class="bi bi-graph-up fs-1 text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grade Distribution -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>Grade Distribution</h5>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row mt-4">
                            <?php
                            $grades = ['A', 'B', 'C', 'D', 'F'];
                            $colors = ['success', 'primary', 'secondary', 'warning', 'danger'];
                            
                            for ($i = 0; $i < count($grades); $i++): 
                                $grade = $grades[$i];
                                $color = $colors[$i];
                                $percentage = ($total_assignments > 0) ? round(($grade_distribution[$grade] / $total_assignments) * 100) : 0;
                            ?>
                            <div class="col">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="grade-badge grade-<?= $grade ?>"><?= $grade ?></div>
                                    <div class="flex-grow-1">
                                        <div class="progress progress-thin">
                                            <div class="progress-bar bg-<?= $color ?>" role="progressbar" style="width: <?= $percentage ?>%" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted">
                                    <span><?= $grade_distribution[$grade] ?> assignments</span>
                                    <span><?= $percentage ?>%</span>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <select name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <select name="filter" class="form-select">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Assignments</option>
                            <option value="graded" <?= $filter === 'graded' ? 'selected' : '' ?>>Graded</option>
                            <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="unsubmitted" <?= $filter === 'unsubmitted' ? 'selected' : '' ?>>Unsubmitted</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($submissions)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                You haven't enrolled in any courses or submitted any assignments yet.
            </div>
        <?php else: ?>
            <!-- Assignment List -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Assignment List</h5>
                    <div>
                        <span class="badge bg-primary"><?= count($submissions) ?> assignments</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php 
                        $filtered_submissions = [];
                        
                        // Apply filters
                        foreach ($submissions as $submission) {
                            $show_submission = true;
                            
                            // Course filter
                            if (!empty($course_filter) && $submission['course_id'] != $course_filter) {
                                $show_submission = false;
                            }
                            
                            // Status filter
                            if ($filter === 'graded' && empty($submission['grade'])) {
                                $show_submission = false;
                            } elseif ($filter === 'pending' && (empty($submission['submission_id']) || !empty($submission['grade']))) {
                                $show_submission = false;
                            } elseif ($filter === 'unsubmitted' && !empty($submission['submission_id'])) {
                                $show_submission = false;
                            }
                            
                            if ($show_submission) {
                                $filtered_submissions[] = $submission;
                            }
                        }
                        
                        if (empty($filtered_submissions)): 
                        ?>
                            <div class="p-4 text-center">
                                <div class="text-muted mb-3">
                                    <i class="bi bi-search" style="font-size: 3rem;"></i>
                                </div>
                                <h5>No matching assignments found</h5>
                                <p class="mb-0">Try adjusting your filter criteria</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($filtered_submissions as $submission): 
                                // Determine submission status and card style
                                $card_class = 'no-submission';
                                $status_badge = '<span class="badge bg-secondary">Not Submitted</span>';
                                
                                if (!empty($submission['submission_id'])) {
                                    if (!empty($submission['grade'])) {
                                        $card_class = 'graded-submission';
                                        $status_badge = '<span class="badge bg-success">Graded</span>';
                                    } else {
                                        $card_class = 'pending-submission';
                                        $status_badge = '<span class="badge bg-warning text-dark">Pending</span>';
                                    }
                                    
                                    // Check if submission was late
                                    $due_date = new DateTime($submission['due_date']);
                                    $submit_date = new DateTime($submission['submitted_at']);
                                    
                                    if ($submit_date > $due_date) {
                                        $card_class = 'overdue-submission';
                                        $status_badge = '<span class="badge bg-danger">Late Submission</span>';
                                    }
                                }
                            ?>
                                <div class="list-group-item submission-card <?= $card_class ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1"><?= htmlspecialchars($submission['title']) ?></h5>
                                            <p class="mb-1 text-muted">
                                                <i class="bi bi-book me-1"></i><?= htmlspecialchars($submission['course_name']) ?>
                                                <span class="mx-2">•</span>
                                                <i class="bi bi-calendar me-1"></i>Due date: <?= date('Y-m-d', strtotime($submission['due_date'])) ?>
                                            </p>
                                            
                                            <?php if (!empty($submission['submission_id'])): ?>
                                                <p class="mb-1 small">
                                                    <i class="bi bi-file-earmark me-1"></i>
                                                    File: <?= htmlspecialchars($submission['file_name']) ?>
                                                    <span class="mx-2">•</span>
                                                    <i class="bi bi-clock me-1"></i>
                                                    Submitted on: <?= date('Y-m-d H:i', strtotime($submission['submitted_at'])) ?>
                                                </p>
                                                
                                                <?php if (!empty($submission['grade'])): ?>
                                                    <div class="mt-3">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <div class="grade-badge grade-<?= $submission['grade'] ?>"><?= $submission['grade'] ?></div>
                                                            <div>
                                                                <div class="fw-bold">Grade: <?= $submission['grade'] ?> 
                                                                    <?php if (!empty($submission['score'])): ?>
                                                                        (<?= $submission['score'] ?>/100)
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="small text-muted">Graded on: <?= date('Y-m-d', strtotime($submission['graded_at'])) ?></div>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if (!empty($submission['feedback'])): ?>
                                                            <div class="card bg-light mt-2">
                                                                <div class="card-body py-2 px-3">
                                                                    <div class="small fw-bold mb-1">Instructor Feedback:</div>
                                                                    <div class="small"><?= nl2br(htmlspecialchars($submission['feedback'])) ?></div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="text-muted small mt-2">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    You haven't submitted this assignment yet. Due date is <?= date('Y-m-d', strtotime($submission['due_date'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ms-3 text-end">
                                            <?= $status_badge ?>
                                            <div class="mt-2">
                                                <?php if (empty($submission['submission_id']) && strtotime($submission['due_date']) > time()): ?>
                                                    <a href="submit_assignment.php?id=<?= $submission['assignment_id'] ?>" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-upload me-1"></i>Submit
                                                    </a>
                                                <?php elseif (!empty($submission['submission_id'])): ?>
                                                    <a href="view_my_submission.php?id=<?= $submission['submission_id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-eye me-1"></i>View
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>