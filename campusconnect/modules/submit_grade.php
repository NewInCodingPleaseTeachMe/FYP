<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

// Initialize variables
$success = false;
$error_message = "";
$assignment_id = null;
$submission = null;
$student_name = "";
$assignment_title = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST["submission_id"]) || !isset($_POST["grade"])) {
        $error_message = "缺少必要参数";
    } else {
        $submission_id = $_POST["submission_id"];
        $grade = filter_var($_POST["grade"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $feedback = trim($_POST["feedback"] ?? "");

        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Query assignment_id and student information for correct return
            $stmt = $pdo->prepare("SELECT s.assignment_id, a.title as assignment_title, u.name as student_name 
                                  FROM submissions s
                                  JOIN assignments a ON s.assignment_id = a.id
                                  JOIN users u ON s.student_id = u.id
                                  WHERE s.id = ?");
            $stmt->execute([$submission_id]);
            $submission = $stmt->fetch();

            if (!$submission) {
                throw new Exception("提交记录不存在！");
            }

            $assignment_id = $submission["assignment_id"];
            $student_name = $submission["student_name"];
            $assignment_title = $submission["assignment_title"];

            // Update grade
            $stmt = $pdo->prepare("UPDATE submissions SET grade = ?, feedback = ?, graded_at = NOW() WHERE id = ?");
            if (!$stmt->execute([$grade, $feedback, $submission_id])) {
                throw new Exception("数据库更新失败");
            }
            
            // Log the activity if activity_logs table exists
            try {
                $checkTable = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
                if ($checkTable->rowCount() > 0) {
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                                          VALUES (?, 'grade', 'submission', ?, ?)");
                    $details = json_encode(['grade' => $grade, 'feedback' => substr($feedback, 0, 50) . (strlen($feedback) > 50 ? '...' : '')]);
                    $stmt->execute([$_SESSION["user_id"], $submission_id, $details]);
                }
            } catch (PDOException $e) {
                // Just continue if activity logs table doesn't exist
            }
            
            // Commit transaction
            $pdo->commit();
            $success = true;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $success ? "Grading Successful" : "Grading Failed" ?> | CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .result-card {
            width: 100%;
            max-width: 500px;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            animation: fadeIn 0.6s ease-in-out;
        }
        .result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
        }
        .success-icon {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        .error-icon {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .info-box {
            background-color: rgba(13, 110, 253, 0.05);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .info-label {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-weight: 600;
        }
        .back-link {
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .brand {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="result-card bg-white">
            <div class="card-body p-4 p-lg-5">
                <?php if ($success): ?>
                    <div class="result-icon success-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h5 class="card-title">评分成功！</h5>
                    
                    <div class="info-box">
                        <div class="row mb-2">
                            <div class="col-4">
                                <p class="info-label">学生</p>
                                <p class="info-value"><?= htmlspecialchars($student_name) ?></p>
                            </div>
                            <div class="col-4">
                                <p class="info-label">作业</p>
                                <p class="info-value"><?= htmlspecialchars($assignment_title) ?></p>
                            </div>
                            <div class="col-4">
                                <p class="info-label">成绩</p>
                                <p class="info-value"><?= htmlspecialchars($grade) ?> 分</p>
                            </div>
                        </div>
                        <p class="info-label">评分时间</p>
                        <p class="info-value"><?= date('Y-m-d H:i:s') ?></p>
                    </div>
                    
                    <div class="text-center">
                        <a href="../views/view_submissions.php?assignment_id=<?= $assignment_id ?>" class="btn btn-primary btn-lg">
                            <i class="bi bi-arrow-left me-2"></i>返回提交列表
                        </a>
                    </div>
                <?php else: ?>
                    <div class="result-icon error-icon">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <h5 class="card-title">评分失败</h5>
                    
                    <div class="alert alert-danger mb-4">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <?= htmlspecialchars($error_message ?: "评分失败，请稍后重试。") ?>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button onclick="history.back()" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-2"></i>返回上一页
                        </button>
                        <a href="../views/assignments_list.php" class="btn btn-primary">
                            <i class="bi bi-list-check me-2"></i>查看所有作业
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="brand text-center">
        <p>© 2025 CampusConnect - The Power to Connect Campuses</p>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>