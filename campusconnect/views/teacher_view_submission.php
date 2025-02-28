<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// 确保用户是老师
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

$user_id = $_SESSION["user_id"];
$teacher_id = $user_id;

// 检查是否提供了submission_id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: assignments_list.php?error=missing_id");
    exit;
}

$submission_id = $_GET['id'];

// 获取提交信息，确保老师只能查看其教授课程的提交
$stmt = $pdo->prepare("SELECT s.*, 
                          a.title as assignment_title, 
                          a.description as assignment_description, 
                          a.due_date, 
                          a.max_points,
                          c.course_name, 
                          c.course_code,
                          u.name as student_name,
                          u.student_id as student_number,
                          u.email as student_email
                       FROM submissions s 
                       JOIN assignments a ON s.assignment_id = a.id 
                       JOIN courses c ON a.course_id = c.id 
                       JOIN users u ON s.student_id = u.id
                       WHERE s.id = ? AND c.teacher_id = ?");
$stmt->execute([$submission_id, $teacher_id]);
$submission = $stmt->fetch();

// 如果提交不存在或不属于该老师的课程，重定向回列表页面
if (!$submission) {
    header("Location: assignments_list.php?error=not_found");
    exit;
}

// 获取学生的其他提交版本
$versions_stmt = $pdo->prepare("SELECT s.id, s.submitted_at, s.grade, s.score, s.file_name
                               FROM submissions s 
                               WHERE s.assignment_id = ? AND s.student_id = ? 
                               ORDER BY s.submitted_at DESC");
$versions_stmt->execute([$submission['assignment_id'], $submission['student_id']]);
$versions = $versions_stmt->fetchAll();

// 获取该作业的所有学生提交统计
$stats_stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total_submissions,
                            AVG(score) as avg_score,
                            MAX(score) as max_score,
                            MIN(score) as min_score
                            FROM submissions 
                            WHERE assignment_id = ? AND grade IS NOT NULL");
$stats_stmt->execute([$submission['assignment_id']]);
$stats = $stats_stmt->fetch();

// 格式化日期显示
function formatDate($date) {
    return date('Y-m-d H:i', strtotime($date));
}

// 获取文件图标
function getFileIcon($filename) {
    if (empty($filename)) return 'bi-file-earmark';
    
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    switch (strtolower($extension)) {
        case 'pdf':
            return 'bi-file-earmark-pdf text-danger';
        case 'doc':
        case 'docx':
            return 'bi-file-earmark-word text-primary';
        case 'xls':
        case 'xlsx':
            return 'bi-file-earmark-excel text-success';
        case 'ppt':
        case 'pptx':
            return 'bi-file-earmark-ppt text-warning';
        case 'zip':
        case 'rar':
            return 'bi-file-earmark-zip text-secondary';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'gif':
            return 'bi-file-earmark-image text-info';
        default:
            return 'bi-file-earmark text-muted';
    }
}

// 检查提交是否逾期
function isLateSubmission($submissionDate, $dueDate) {
    return strtotime($submissionDate) > strtotime($dueDate);
}

// 获取老师姓名
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Student Submission - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
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
        .student-info {
            background-color: rgba(13, 110, 253, 0.05);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .student-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #6c757d;
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
        .stats-card {
            background-color: #fff;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .progress-slim {
            height: 6px;
            margin-top: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .file-icon {
            font-size: 2rem;
        }
        .late-badge {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .score-input {
            width: 80px;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 no-print">
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
                            <i class="bi bi-house-door me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assignments_list.php">
                            <i class="bi bi-journal-check me-1"></i>Assignments
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>">
                            <i class="bi bi-people-fill me-1"></i>Submissions
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-workspace me-1"></i>
                        <?= htmlspecialchars($teacher['name'] ?? "Teacher") ?>
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
        <nav aria-label="breadcrumb" class="mb-4 no-print">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Home</a></li>
                <li class="breadcrumb-item"><a href="assignments_list.php">Assignments</a></li>
                <li class="breadcrumb-item"><a href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>">View Submissions</a></li>
                <li class="breadcrumb-item active" aria-current="page">Student Submission</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-file-earmark-check me-2"></i>
                    View Student Submission
                </h2>
                <p class="text-muted">Detailed submission information and grading options</p>
            </div>
            <div class="col-md-4 text-md-end no-print">
                <a href="view_submissions.php?assignment_id=<?= $submission['assignment_id'] ?>" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Submissions
                </a>
                <button onclick="window.print()" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Left side: Submission information -->
            <div class="col-lg-8">
                <!-- Student Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="student-info">
                            <div class="d-flex align-items-center">
                                <div class="student-avatar me-3">
                                    <?= mb_substr(htmlspecialchars($submission['student_name']), 0, 1) ?>
                                </div>
                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($submission['student_name']) ?></h5>
                                    <div class="text-muted mb-1">
                                        <i class="bi bi-person-badge me-1"></i> 
                                        Student ID: <?= htmlspecialchars($submission['student_number']) ?>
                                    </div>
                                    <div class="text-muted">
                                        <i class="bi bi-envelope me-1"></i> 
                                        <?= htmlspecialchars($submission['student_email']) ?>
                                    </div>
                                </div>
                                <div class="ms-auto no-print">
                                    <a href="mailto:<?= htmlspecialchars($submission['student_email']) ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-envelope me-1"></i> Send Email
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="card-title mb-3">
                            <i class="bi bi-clipboard-check me-2 text-primary"></i>
                            Assignment: <?= htmlspecialchars($submission['assignment_title']) ?>
                        </h5>
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <span class="badge bg-primary">
                                <i class="bi bi-book me-1"></i>
                                <?= htmlspecialchars($submission['course_name']) ?>
                                <?= !empty($submission['course_code']) ? "(".htmlspecialchars($submission['course_code']).")" : "" ?>
                            </span>
                            <span class="badge bg-secondary">
                                <i class="bi bi-calendar-event me-1"></i>
                                Due: <?= formatDate($submission['due_date']) ?>
                            </span>
                            <span class="badge bg-info">
                                <i class="bi bi-trophy me-1"></i>
                                Max Points: <?= $submission['max_points'] ?? 100 ?>
                            </span>
                        </div>
                        
                        <?php if (!empty($submission['assignment_description'])): ?>
                            <div class="mb-4">
                                <h6>Assignment Description:</h6>
                                <div class="p-3 bg-light rounded">
                                    <?= nl2br(htmlspecialchars($submission['assignment_description'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ... 剩余页面内容 ... -->
                
                <!-- Submission Details -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                            Submission Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- 提交详情部分 -->
                    </div>
                </div>
                
                <!-- Grading Form -->
                <div class="card mb-4 no-print">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi bi-pencil-square me-2 text-primary"></i>
                            Grade Submission
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- 评分表单部分 -->
                    </div>
                </div>
            </div>

            <!-- Right side: Submission history and statistics -->
            <div class="col-lg-4">
                <!-- 右侧统计和历史记录部分 -->
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>