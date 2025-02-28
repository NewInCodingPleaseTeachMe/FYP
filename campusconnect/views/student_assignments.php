<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure the user is logged in and is a student
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../login.php");
    exit("Unauthorized access!");
}

$user_id = $_SESSION["user_id"];
$course_id = $_GET["course_id"] ?? null;

// Check if the course ID is valid
if (!$course_id) {
    header("Location: course_list.php?error=invalid_course");
    exit;
}

// Query course information
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: course_list.php?error=course_not_found");
    exit;
}

// Check if the student is enrolled in the course
$stmt = $pdo->prepare("SELECT * FROM user_courses WHERE user_id = ? AND course_id = ?");
$stmt->execute([$user_id, $course_id]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    header("Location: course_list.php?error=not_enrolled");
    exit;
}

// Query all assignments for the course
$stmt = $pdo->prepare("SELECT * FROM assignments WHERE course_id = ? ORDER BY due_date DESC");
$stmt->execute([$course_id]);
$assignments = $stmt->fetchAll();

// Get all submission records for the student
$stmt = $pdo->prepare("SELECT assignment_id, MAX(submitted_at) as latest_submission, grade, score
                      FROM submissions 
                      WHERE student_id = ? 
                      GROUP BY assignment_id");
$stmt->execute([$user_id]);
$submissions_result = $stmt->fetchAll();

// Organize submission records into an array keyed by assignment ID
$submissions = [];
foreach ($submissions_result as $submission) {
    $submissions[$submission['assignment_id']] = [
        'latest_submission' => $submission['latest_submission'],
        'grade' => $submission['grade'],
        'score' => $submission['score']
    ];
}

// Calculate statistics
$total_assignments = count($assignments);
$completed_assignments = count($submissions);
$graded_assignments = 0;
$total_score = 0;
$graded_count = 0;

foreach ($submissions as $assignment_id => $submission) {
    if (!empty($submission['grade'])) {
        $graded_assignments++;
        if (isset($submission['score']) && $submission['score'] !== null) {
            $total_score += $submission['score'];
            $graded_count++;
        }
    }
}

$average_score = $graded_count > 0 ? round($total_score / $graded_count, 1) : 0;
$completion_rate = $total_assignments > 0 ? round(($completed_assignments / $total_assignments) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Assignments - <?= htmlspecialchars($course['course_name']) ?> | CampusConnect</title>
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
            margin-bottom: 1.5rem;
        }
        .assignment-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .progress-thin {
            height: 8px;
        }
        .deadline-badge {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 0.35rem 0.65rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .stats-card {
            text-align: center;
            padding: 1.5rem;
            background-color: white;
            border-radius: 0.8rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
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
                        <a class="nav-link" href="my_submissions.php">
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
                    <a href="../modules/logout.php" class="btn btn-outline-light btn-sm">
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
                <li class="breadcrumb-item"><a href="course_list.php">Course List</a></li>
                <li class="breadcrumb-item"><a href="view_course.php?id=<?= $course_id ?>">
                    <?= htmlspecialchars($course['course_name']) ?>
                </a></li>
                <li class="breadcrumb-item active" aria-current="page">Course Assignments</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-clipboard-check me-2"></i>
                    <?= htmlspecialchars($course['course_name']) ?> - Course Assignments
                </h2>
                <p class="text-muted">View all assignments and submission status for this course</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="view_course.php?id=<?= $course_id ?>" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Course
                </a>
            </div>
        </div>

        <!-- Assignment Progress Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3 mb-md-0">
                <div class="stats-card">
                    <div class="stats-number"><?= $total_assignments ?></div>
                    <div class="stats-label">Total Assignments</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3 mb-md-0">
                <div class="stats-card">
                    <div class="stats-number"><?= $completed_assignments ?></div>
                    <div class="stats-label">Submitted</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card">
                    <div class="stats-number"><?= $graded_assignments ?></div>
                    <div class="stats-label">Graded</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stats-card">
                    <div class="stats-number"><?= $average_score ?></div>
                    <div class="stats-label">Average Score</div>
                </div>
            </div>
        </div>

        <!-- Completion Progress Bar -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Assignment Completion Progress</h5>
                <div class="d-flex justify-content-between mb-1">
                    <span>Completed <?= $completed_assignments ?> / <?= $total_assignments ?> Assignments</span>
                    <span><?= $completion_rate ?>%</span>
                </div>
                <div class="progress progress-thin">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $completion_rate ?>%" 
                         aria-valuenow="<?= $completion_rate ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
            </div>
        </div>

        <?php if (empty($assignments)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                No assignments available for this course.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($assignments as $assignment): 
                    // Calculate deadline information
                    $now = new DateTime();
                    $due_date = new DateTime($assignment['due_date']);
                    $is_overdue = $now > $due_date;
                    
                    // Calculate time remaining
                    $interval = $now->diff($due_date);
                    $days_left = $interval->days;
                    $hours_left = $interval->h;
                    
                    // Check submission status
                    $submitted = isset($submissions[$assignment['id']]);
                    $graded = $submitted && !empty($submissions[$assignment['id']]['grade']);
                ?>
                    <div class="col-md-6 mb-4">
                        <div class="card assignment-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title"><?= htmlspecialchars($assignment['title']) ?></h5>
                                    <?php if ($graded): ?>
                                        <span class="badge bg-success">Graded</span>
                                    <?php elseif ($submitted): ?>
                                        <span class="badge bg-warning text-dark">Submitted</span>
                                    <?php elseif ($is_overdue): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Not Submitted</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($assignment['description'])): ?>
                                    <p class="text-muted mb-3">
                                        <?= nl2br(htmlspecialchars(mb_substr($assignment['description'], 0, 100) . (mb_strlen($assignment['description']) > 100 ? '...' : ''))) ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="d-flex align-items-center mb-3">
                                    <span class="deadline-badge me-2">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?= $due_date->format('Y-m-d H:i') ?>
                                    </span>
                                    
                                    <?php if (!$is_overdue): ?>
                                        <span class="badge bg-info">
                                            <?php
                                            if ($days_left == 0 && $hours_left < 1) {
                                                echo "Due Soon";
                                            } elseif ($days_left == 0) {
                                                echo "{$hours_left} hours left";
                                            } elseif ($days_left == 1) {
                                                echo "Due Tomorrow";
                                            } else {
                                                echo "{$days_left} days left";
                                            }
                                            ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($graded && isset($submissions[$assignment['id']]['score'])): ?>
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-2 fw-bold">Grade:</div>
                                            <div class="d-inline-block px-3 py-1 bg-success text-white rounded-pill">
                                                <?= $submissions[$assignment['id']]['grade'] ?>
                                            </div>
                                            <div class="ms-3">
                                                <?= $submissions[$assignment['id']]['score'] ?> / <?= $assignment['max_points'] ?? 100 ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-end mt-3">
                                    <a href="view_assignment.php?id=<?= $assignment['id'] ?>" class="btn btn-primary">
                                        <i class="bi bi-eye me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>