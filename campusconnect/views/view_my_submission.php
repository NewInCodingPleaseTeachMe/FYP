<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure the user is logged in and is a student
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: ../login.php");
    exit("Access denied!");
}

$user_id = $_SESSION["user_id"];

// Check if submission_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: my_submissions.php");
    exit("Missing required parameter!");
}

$submission_id = $_GET['id'];

// Get submission information, ensure students can only view their own submissions
$stmt = $pdo->prepare("SELECT s.*, a.title as assignment_title, a.description as assignment_description, 
                      a.due_date, c.course_name 
                      FROM submissions s 
                      JOIN assignments a ON s.assignment_id = a.id 
                      JOIN courses c ON a.course_id = c.id 
                      WHERE s.id = ? AND s.student_id = ?");
$stmt->execute([$submission_id, $user_id]);
$submission = $stmt->fetch();

// If submission not found or doesn't belong to the current student, redirect back to the list page
if (!$submission) {
    header("Location: my_submissions.php");
    exit("Submission record not found or no permission to view!");
}

// Get other submission versions from this student
$versions_stmt = $pdo->prepare("SELECT id, submitted_at, grade, score 
                               FROM submissions 
                               WHERE assignment_id = ? AND student_id = ? 
                               ORDER BY submitted_at DESC");
$versions_stmt->execute([$submission['assignment_id'], $user_id]);
$versions = $versions_stmt->fetchAll();

// Format date display
function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission - CampusConnect</title>
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
        .submission-file {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        .grade-badge {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 1.5rem;
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
        .version-item {
            transition: all 0.2s;
        }
        .version-item:hover {
            background-color: #f8f9fa;
        }
        .current-version {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation bar -->
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
                        <a class="nav-link active" href="my_submissions.php">
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
        <!-- Breadcrumb navigation -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="my_submissions.php">My Assignments</a></li>
                <li class="breadcrumb-item active" aria-current="page">View Submission</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-file-earmark-text me-2"></i>
                    View Submission
                </h2>
                <p class="text-muted">Submission details and grading information</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="my_submissions.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>Return to Assignment List
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Left side: Submission information -->
            <div class="col-lg-8 mb-4">
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Assignment Information</h5>
                    </div>
                    <div class="card-body">
                        <h4><?= htmlspecialchars($submission['assignment_title']) ?></h4>
                        <p class="mb-3">
                            <span class="badge bg-primary me-2"><?= htmlspecialchars($submission['course_name']) ?></span>
                            <span class="text-muted">
                                <i class="bi bi-calendar me-1"></i>Due Date: <?= formatDate($submission['due_date']) ?>
                            </span>
                        </p>
                        
                        <?php if (!empty($submission['assignment_description'])): ?>
                            <div class="mb-4">
                                <h6>Assignment Description:</h6>
                                <div class="p-3 bg-light rounded">
                                    <?= nl2br(htmlspecialchars($submission['assignment_description'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="submission-file mb-4">
                            <h6><i class="bi bi-file-earmark me-1"></i>Submitted File</h6>
                            <div class="d-flex align-items-center mt-3">
                                <i class="bi bi-file-earmark-text fs-2 me-3 text-primary"></i>
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($submission['file_name']) ?></h6>
                                    <p class="text-muted mb-0">
                                        Submitted on: <?= formatDate($submission['submitted_at']) ?>
                                    </p>
                                </div>
                                <a href="../uploads/<?= htmlspecialchars($submission['file_path']) ?>" 
                                   class="btn btn-outline-primary ms-auto" download>
                                    <i class="bi bi-download me-1"></i>Download
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($submission['grade'])): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-award me-2"></i>Grading Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center mb-4">
                                <div class="col-auto">
                                    <div class="grade-badge grade-<?= $submission['grade'] ?>">
                                        <?= $submission['grade'] ?>
                                    </div>
                                </div>
                                <div class="col">
                                    <h4 class="mb-0">Grade: <?= $submission['grade'] ?></h4>
                                    <?php if (!empty($submission['score'])): ?>
                                        <h5 class="mb-0"><?= $submission['score'] ?>/100</h5>
                                    <?php endif; ?>
                                    <p class="text-muted mb-0">
                                        Graded on: <?= formatDate($submission['graded_at']) ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if (!empty($submission['feedback'])): ?>
                                <div>
                                    <h6>Instructor Feedback:</h6>
                                    <div class="p-3 bg-light rounded">
                                        <?= nl2br(htmlspecialchars($submission['feedback'])) ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>The instructor has not provided feedback.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                This submission has not been graded yet. Please wait for the instructor to grade it.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right side: Submission history -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Submission History</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (count($versions) > 0): ?>
                                <?php foreach ($versions as $version): ?>
                                    <a href="view_my_submission.php?id=<?= $version['id'] ?>" 
                                       class="list-group-item list-group-item-action version-item <?= $version['id'] == $submission_id ? 'current-version' : '' ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="d-block"><?= formatDate($version['submitted_at']) ?></span>
                                                <?php if (!empty($version['grade'])): ?>
                                                    <span class="badge bg-success">Graded: <?= $version['grade'] ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Not Graded</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($version['id'] == $submission_id): ?>
                                                <span class="badge bg-primary">Current View</span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="list-group-item text-muted">
                                    No submission history
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <?php 
                        $now = new DateTime();
                        $due_date = new DateTime($submission['due_date']);
                        if ($now < $due_date):
                        ?>
                            <a href="submit_assignment.php?id=<?= $submission['assignment_id'] ?>" class="btn btn-primary w-100">
                                <i class="bi bi-arrow-up-circle me-1"></i>Resubmit
                            </a>
                        <?php else: ?>
                            <div class="alert alert-secondary mb-0">
                                <i class="bi bi-info-circle me-2"></i>
                                Past due date, cannot resubmit
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>