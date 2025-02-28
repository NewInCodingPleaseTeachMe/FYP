<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is logged in and is a teacher
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: ../login.php");
    exit("Access denied!");
}

// Validate if submission ID exists
if (!isset($_GET["submission_id"]) || empty($_GET["submission_id"])) {
    header("Location: submissions_list.php");
    exit("Invalid submission ID!");
}

$submission_id = $_GET["submission_id"];
$teacher_id = $_SESSION["user_id"];

// Get submission details - 这里修改 a.total_points 为 a.max_points
$stmt = $pdo->prepare("SELECT s.*, u.name as student_name, u.email as student_email, 
                        a.title as assignment_title, a.description as assignment_description,
                        a.due_date, a.max_points, c.course_name, c.id as course_id
                        FROM submissions s
                        JOIN users u ON s.student_id = u.id
                        JOIN assignments a ON s.assignment_id = a.id
                        JOIN courses c ON a.course_id = c.id
                        WHERE s.id = ?");
$stmt->execute([$submission_id]);
$submission = $stmt->fetch();

// Check if submission exists
if (!$submission) {
    header("Location: assignments_list.php");
    exit("Submission does not exist!");
}

// Verify if current teacher has permission to grade (is the teacher for this course)
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
$stmt->execute([$submission['course_id'], $teacher_id]);
if (!$stmt->fetch() && $_SESSION["role"] !== "admin") {
    header("Location: submissions_list.php");
    exit("You do not have permission to grade this submission!");
}

// Calculate if submission is late
$due_date = new DateTime($submission['due_date']);
$submit_date = new DateTime($submission['submitted_at']); // 使用 submitted_at 而不是 created_at
$is_late = $submit_date > $due_date;
$days_late = 0;

if ($is_late) {
    $interval = $submit_date->diff($due_date);
    $days_late = $interval->days;
}

// Check if there are predefined grading rubrics - 添加错误处理
$rubrics = [];
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'grading_rubrics'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM grading_rubrics WHERE assignment_id = ? ORDER BY min_score DESC");
        $stmt->execute([$submission['assignment_id']]);
        $rubrics = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // 表不存在，$rubrics 保持为空数组
}

// Function to format file size
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 B";
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Calculate file size
$file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($submission['file_path'], '/');
$file_size = file_exists($file_path) ? filesize($file_path) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Assignment - <?= htmlspecialchars($submission["student_name"]) ?> - CampusConnect</title>
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
        .file-preview {
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            background-color: #f8f9fa;
        }
        .submission-timeline {
            position: relative;
            padding-left: 30px;
        }
        .submission-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #dee2e6;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #0d6efd;
            border: 2px solid #fff;
        }
        .rubric-item {
            cursor: pointer;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .rubric-item:hover {
            background-color: #f8f9fa;
            border-left-color: #0d6efd;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        .character-counter {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
        }
        .grade-score {
            font-size: 2rem;
            font-weight: bold;
            color: #0d6efd;
            text-align: center;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard-fill me-2"></i>CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assignments_list.php">
                            <i class="bi bi-journal-check me-1"></i>Assignments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>">
                            <i class="bi bi-inbox me-1"></i>Submissions
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["name"] ?? "Teacher") ?> 
                        <span class="badge bg-light text-primary ms-1">Teacher</span>
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
                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="assignments_list.php">Assignments</a></li>
                <li class="breadcrumb-item"><a href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>">Assignment Submissions</a></li>
                <li class="breadcrumb-item active" aria-current="page">Grade Submission</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-check-square me-2"></i>
                    Grade Assignment
                </h2>
                <p class="text-muted">Grade and provide feedback for student submissions</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Return to Submission List
                </a>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Student Submission Information -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex align-items-center">
                            <div class="avatar me-3">
                                <?= mb_substr($submission['student_name'], 0, 1, 'UTF-8') ?>
                            </div>
                            <div>
                                <h5 class="mb-0"><?= htmlspecialchars($submission['student_name']) ?></h5>
                                <p class="text-muted mb-0 small"><?= htmlspecialchars($submission['student_email']) ?></p>
                            </div>
                            <?php if ($is_late): ?>
                                <span class="ms-auto badge bg-warning text-dark">
                                    <i class="bi bi-clock-history me-1"></i>Late by <?= $days_late ?> day(s)
                                </span>
                            <?php else: ?>
                                <span class="ms-auto badge bg-success">
                                    <i class="bi bi-clock-check me-1"></i>On time
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h5 class="card-title">
                                <i class="bi bi-book me-2"></i>
                                <?= htmlspecialchars($submission['assignment_title']) ?>
                            </h5>
                            <p class="card-text">
                                <span class="badge bg-primary me-2"><?= htmlspecialchars($submission['course_name']) ?></span>
                                <span class="text-muted">Due date: <?= date('Y-m-d H:i', strtotime($submission['due_date'])) ?></span>
                            </p>
                        </div>

                        <!-- Submitted File Information -->
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="bi bi-file-earmark me-2"></i>Submitted File</h6>
                            <div class="file-preview">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <?php
                                        $file_ext = pathinfo($submission['file_name'], PATHINFO_EXTENSION);
                                        $icon_class = 'bi-file-earmark';
                                        
                                        switch(strtolower($file_ext)) {
                                            case 'pdf': $icon_class = 'bi-file-earmark-pdf'; break;
                                            case 'doc':
                                            case 'docx': $icon_class = 'bi-file-earmark-word'; break;
                                            case 'xls':
                                            case 'xlsx': $icon_class = 'bi-file-earmark-excel'; break;
                                            case 'ppt':
                                            case 'pptx': $icon_class = 'bi-file-earmark-ppt'; break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png': $icon_class = 'bi-file-earmark-image'; break;
                                            case 'zip':
                                            case 'rar': $icon_class = 'bi-file-earmark-zip'; break;
                                            case 'txt': $icon_class = 'bi-file-earmark-text'; break;
                                        }
                                        ?>
                                        <i class="bi <?= $icon_class ?> me-2 fs-4"></i>
                                        <span class="fw-bold"><?= htmlspecialchars($submission['file_name']) ?></span>
                                    </div>
                                    <a href="<?= htmlspecialchars($submission['file_path']) ?>" class="btn btn-primary btn-sm" download>
                                        <i class="bi bi-download me-1"></i>Download File
                                    </a>
                                </div>
                                <div class="mt-3">
                                    <p class="text-muted mb-0 small">
                                        <i class="bi bi-clock me-1"></i>Submission time: <?= date('Y-m-d H:i:s', strtotime($submission['submitted_at'])) ?>
                                        <br>
                                        <i class="bi bi-hdd me-1"></i>File size: <?= formatFileSize($file_size) ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Student Comments -->
                        <?php if (!empty($submission['comment'])): ?>
                        <div class="mb-4">
                            <h6 class="mb-2"><i class="bi bi-chat-left-text me-2"></i>Student Comments</h6>
                            <div class="p-3 bg-light rounded">
                                <?= nl2br(htmlspecialchars($submission['comment'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Grading Form -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-award me-2"></i>Grading</h5>
                    </div>
                    <div class="card-body">
                        <form action="../modules/submit_grade.php" method="POST" id="gradeForm">
                            <input type="hidden" name="submission_id" value="<?= $submission_id ?>">
                            
                            <div class="mb-3">
                                <label for="grade" class="form-label fw-bold">Score (out of <?= $submission["max_points"] ?? 100 ?>)</label>
                                <div class="input-group mb-3">
                                    <input type="number" name="grade" id="grade" class="form-control" min="0" max="<?= $submission["max_points"] ?? 100 ?>" value="<?= $submission['grade'] ?? '' ?>" required>
                                    <span class="input-group-text">/ <?= $submission["max_points"] ?? 100 ?></span>
                                </div>
                                
                                <div class="progress mb-2">
                                    <div class="progress-bar" id="gradeProgressBar" role="progressbar" style="width: <?= ($submission['grade'] ?? 0) / ($submission["max_points"] ?? 100) * 100 ?>%" aria-valuenow="<?= $submission['grade'] ?? 0 ?>" aria-valuemin="0" aria-valuemax="<?= $submission["max_points"] ?? 100 ?>"></div>
                                </div>
                                
                                <div class="grade-score mb-3" id="gradeScoreDisplay">
                                    <?= $submission['grade'] ?? '0' ?> / <?= $submission["max_points"] ?? 100 ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="feedback" class="form-label fw-bold">Feedback</label>
                                <textarea name="feedback" id="feedback" class="form-control" rows="5" maxlength="1000"><?= htmlspecialchars($submission['feedback'] ?? '') ?></textarea>
                                <div class="character-counter mt-1">
                                    <span id="feedback_counter">0</span>/1000
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Submit Grade
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Grading Rubrics -->
                <?php if (!empty($rubrics)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-list-check me-2"></i>Grading Rubrics</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($rubrics as $rubric): ?>
                            <div class="list-group-item rubric-item" data-grade="<?= htmlspecialchars($rubric['min_score']) ?>" data-feedback="<?= htmlspecialchars($rubric['description']) ?>">
                                <div class="d-flex align-items-center">
                                    <div class="badge bg-primary me-3">
                                        <?= htmlspecialchars($rubric['min_score']) ?>+
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($rubric['title']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($rubric['description']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Submission Timeline -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Submission Timeline</h5>
                    </div>
                    <div class="card-body">
                        <div class="submission-timeline">
                            <div class="timeline-item">
                                <h6 class="mb-0">Assignment Due Date</h6>
                                <p class="text-muted small mb-0">
                                    <?= date('Y-m-d H:i', strtotime($submission['due_date'])) ?>
                                </p>
                            </div>
                            <div class="timeline-item">
                                <h6 class="mb-0">Student Submission</h6>
                                <p class="text-muted small mb-0">
                                    <?= date('Y-m-d H:i', strtotime($submission['submitted_at'])) ?>
                                </p>
                                <?php if ($is_late): ?>
                                    <span class="badge bg-warning text-dark">Late by <?= $days_late ?> day(s)</span>
                                <?php else: ?>
                                    <span class="badge bg-success">On time</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($submission['graded_at'])): ?>
                            <div class="timeline-item">
                                <h6 class="mb-0">Graded</h6>
                                <p class="text-muted small mb-0">
                                    <?= date('Y-m-d H:i', strtotime($submission['graded_at'])) ?>
                                </p>
                                <span class="badge bg-primary">
                                    <?= $submission['grade'] ?> points
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Feedback Templates -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-chat-square-text me-2"></i>Quick Feedback Templates</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">Click on the templates below to quickly add them to your feedback</p>
                        <div class="list-group">
                            <button type="button" class="list-group-item list-group-item-action feedback-template">
                                Excellent work! Your assignment demonstrates a deep understanding of the topic and clear expression.
                            </button>
                            <button type="button" class="list-group-item list-group-item-action feedback-template">
                                Good attempt, but some concepts need further clarification. I recommend reviewing chapters 3 and 4.
                            </button>
                            <button type="button" class="list-group-item list-group-item-action feedback-template">
                                Your argument is well-structured, but lacks sufficient examples to support your points.
                            </button>
                            <button type="button" class="list-group-item list-group-item-action feedback-template">
                                There are some grammar and spelling errors in your assignment. I recommend proofreading carefully before submitting.
                            </button>
                            <button type="button" class="list-group-item list-group-item-action feedback-template">
                                The assignment did not meet the basic requirements. Please refer to the course syllabus and redo this assignment.
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Character counter
            const feedbackTextarea = document.getElementById('feedback');
            const feedbackCounter = document.getElementById('feedback_counter');
            
            // Initial count
            feedbackCounter.textContent = feedbackTextarea.value.length;
            
            // Real-time counter update
            feedbackTextarea.addEventListener('input', function() {
                feedbackCounter.textContent = this.value.length;
            });
            
            // Grade display update
            const gradeInput = document.getElementById('grade');
            const gradeProgressBar = document.getElementById('gradeProgressBar');
            const gradeScoreDisplay = document.getElementById('gradeScoreDisplay');
            const maxPoints = <?= $submission["max_points"] ?? 100 ?>;
            
            gradeInput.addEventListener('input', function() {
                let value = parseInt(this.value) || 0;
                
                // Ensure the value is within range
                if (value > maxPoints) value = maxPoints;
                if (value < 0) value = 0;
                
                // Update display elements
                const percentage = (value / maxPoints) * 100;
                gradeProgressBar.style.width = percentage + '%';
                gradeScoreDisplay.textContent = value + ' / ' + maxPoints;
                
                // Update color of progress bar based on score
                if (percentage >= 80) {
                    gradeProgressBar.className = 'progress-bar bg-success';
                } else if (percentage >= 60) {
                    gradeProgressBar.className = 'progress-bar bg-warning';
                } else {
                    gradeProgressBar.className = 'progress-bar bg-danger';
                }
            });
            
            // Rubric item click
            const rubricItems = document.querySelectorAll('.rubric-item');
            
            rubricItems.forEach(item => {
                item.addEventListener('click', function() {
                    const grade = this.dataset.grade;
                    const feedback = this.dataset.feedback;
                    
                    // Set grade
                    gradeInput.value = grade;
                    
                    // Trigger the input event to update visuals
                    const event = new Event('input', {
                        bubbles: true,
                        cancelable: true,
                    });
                    gradeInput.dispatchEvent(event);
                    
                    // Add feedback
                    feedbackTextarea.value = feedback;
                    feedbackCounter.textContent = feedback.length;
                });
            });
            
            // Quick feedback templates
            const feedbackTemplates = document.querySelectorAll('.feedback-template');
            
            feedbackTemplates.forEach(template => {
                template.addEventListener('click', function() {
                    const templateText = this.textContent.trim();
                    
                    if (feedbackTextarea.value) {
                        feedbackTextarea.value += '\n\n' + templateText;
                    } else {
                        feedbackTextarea.value = templateText;
                    }
                    
                    feedbackCounter.textContent = feedbackTextarea.value.length;
                });
            });
        });
    </script>
</body>
</html>