<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// **🔐 限制未登录用户访问**
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// **🔐 限制非管理员用户访问**
if ($_SESSION["role"] !== "admin") {
    header("Location: ../dashboard.php?error=unauthorized");
    exit;
}

$admin_name = $_SESSION["name"] ?? "管理员";
$success_message = "";
$error_message = "";

// 处理批准或拒绝操作
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['user_id']) && isset($_POST['action'])) {
        $user_id = $_POST['user_id'];
        $action = $_POST['action'];
        $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
        
        if ($action === 'approve') {
            // 更新用户状态为活跃
            $update_stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'teacher' AND status = 'pending'");
            $result = $update_stmt->execute([$user_id]);
            
            if ($result) {
                // 记录活动日志
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                                          VALUES (?, 'approve', 'user', ?, 'Teacher registration approved')");
                $log_stmt->execute([$_SESSION["user_id"], $user_id]);
                
                // 也插入到教师验证表（如果存在）
                try {
                    $verify_stmt = $pdo->prepare("INSERT INTO teacher_verifications 
                                                (user_id, status, admin_notes, reviewed_by, reviewed_at) 
                                                VALUES (?, 'approved', ?, ?, NOW())");
                    $verify_stmt->execute([$user_id, $admin_notes, $_SESSION["user_id"]]);
                } catch (PDOException $e) {
                    // 表可能不存在，继续执行
                }
                
                // 获取教师邮箱以便通知
                $email_stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                $email_stmt->execute([$user_id]);
                $teacher = $email_stmt->fetch();
                
                if ($teacher) {
                    // 模拟发送批准邮件
                    $email_subject = "您的教师账号已获批准";
                    $email_message = "尊敬的 {$teacher['name']},\n\n";
                    $email_message .= "您在CampusConnect系统中的教师注册请求已被批准！\n\n";
                    $email_message .= "您现在可以登录您的账户并开始创建课程。\n\n";
                    $email_message .= "谢谢！\n";
                    $email_message .= "- CampusConnect管理团队";
                    
                    // 保存到文件而不是实际发送
                    file_put_contents("../logs/teacher_notifications.txt", 
                                      date('Y-m-d H:i:s') . " - To: {$teacher['email']}\nSubject: $email_subject\n$email_message\n\n", 
                                      FILE_APPEND);
                }
                
                $success_message = "教师账户已成功批准！";
            } else {
                $error_message = "批准操作失败，请重试。";
            }
        } elseif ($action === 'reject') {
            // 更新用户状态为不活跃
            $update_stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'teacher' AND status = 'pending'");
            $result = $update_stmt->execute([$user_id]);
            
            if ($result) {
                // 记录活动日志
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                                          VALUES (?, 'reject', 'user', ?, 'Teacher registration rejected')");
                $log_stmt->execute([$_SESSION["user_id"], $user_id]);
                
                // 也插入到教师验证表（如果存在）
                try {
                    $verify_stmt = $pdo->prepare("INSERT INTO teacher_verifications 
                                                (user_id, status, admin_notes, reviewed_by, reviewed_at) 
                                                VALUES (?, 'rejected', ?, ?, NOW())");
                    $verify_stmt->execute([$user_id, $admin_notes, $_SESSION["user_id"]]);
                } catch (PDOException $e) {
                    // 表可能不存在，继续执行
                }
                
                // 获取教师邮箱以便通知
                $email_stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
                $email_stmt->execute([$user_id]);
                $teacher = $email_stmt->fetch();
                
                if ($teacher) {
                    // 模拟发送拒绝邮件
                    $email_subject = "关于您的教师账号申请";
                    $email_message = "尊敬的 {$teacher['name']},\n\n";
                    $email_message .= "感谢您申请CampusConnect系统的教师账号。\n\n";
                    $email_message .= "很遗憾，您的申请目前未被批准。";
                    
                    if (!empty($admin_notes)) {
                        $email_message .= "\n\n原因：" . $admin_notes;
                    }
                    
                    $email_message .= "\n\n如有疑问，请联系管理员获取更多信息。\n\n";
                    $email_message .= "谢谢！\n";
                    $email_message .= "- CampusConnect管理团队";
                    
                    // 保存到文件而不是实际发送
                    file_put_contents("../logs/teacher_notifications.txt", 
                                      date('Y-m-d H:i:s') . " - To: {$teacher['email']}\nSubject: $email_subject\n$email_message\n\n", 
                                      FILE_APPEND);
                }
                
                $success_message = "教师申请已被拒绝。";
            } else {
                $error_message = "拒绝操作失败，请重试。";
            }
        }
    }
}

// 获取所有待审批的教师账户
$stmt = $pdo->prepare("
    SELECT u.*, DATE_FORMAT(u.created_at, '%Y-%m-%d %H:%i:%s') as formatted_date 
    FROM users u 
    WHERE u.role = 'teacher' AND u.status = 'pending' 
    ORDER BY u.created_at DESC
");
$stmt->execute();
$pending_teachers = $stmt->fetchAll();

// 获取最近已处理的教师申请
$recent_stmt = $pdo->prepare("
    SELECT u.*, 
           DATE_FORMAT(u.created_at, '%Y-%m-%d %H:%i:%s') as formatted_date,
           (SELECT name FROM users WHERE id = tv.reviewed_by) as admin_name,
           tv.status as verification_status,
           tv.admin_notes,
           DATE_FORMAT(tv.reviewed_at, '%Y-%m-%d %H:%i:%s') as review_date
    FROM users u 
    LEFT JOIN teacher_verifications tv ON u.id = tv.user_id
    WHERE u.role = 'teacher' 
    AND (u.status != 'pending' OR tv.status IN ('approved', 'rejected'))
    ORDER BY tv.reviewed_at DESC, u.created_at DESC
    LIMIT 10
");

try {
    $recent_stmt->execute();
    $recent_applications = $recent_stmt->fetchAll();
} catch (PDOException $e) {
    // 表可能不存在，使用备用查询
    $recent_stmt = $pdo->prepare("
        SELECT u.*, DATE_FORMAT(u.created_at, '%Y-%m-%d %H:%i:%s') as formatted_date
        FROM users u 
        WHERE u.role = 'teacher' AND u.status != 'pending'
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
    $recent_stmt->execute();
    $recent_applications = $recent_stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>教师申请审批 - CampusConnect</title>
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
            --admin-primary: #6610f2;
            --admin-dark: #520dc2;
            --admin-light: #e5ddfc;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 15px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-dark));
            color: white;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
        }
        
        .card-header h5 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .user-item {
            transition: all 0.3s ease;
        }
        
        .user-item:hover {
            background-color: var(--primary-light);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
        }
        
        .empty-state {
            padding: 3rem 2rem;
            text-align: center;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <!-- 导航栏 -->
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
                            <i class="bi bi-speedometer2 me-1"></i> 控制面板
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-people me-1"></i> 用户管理
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="add_user.php"><i class="bi bi-person-plus me-1"></i> 添加用户</a></li>
                            <li><a class="dropdown-item" href="user_list.php"><i class="bi bi-list-ul me-1"></i> 用户列表</a></li>
                            <li><a class="dropdown-item active" href="teacher_approval.php"><i class="bi bi-check-circle me-1"></i> 教师审批</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-book me-1"></i> 课程管理
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($admin_name) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../views/profile.php"><i class="bi bi-person me-1"></i> 个人资料</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../modules/logout.php"><i class="bi bi-box-arrow-right me-1"></i> 登出</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <!-- 页面标题 -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-check-circle-fill text-success me-2"></i> 教师申请审批
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php">控制面板</a></li>
                        <li class="breadcrumb-item"><a href="user_list.php">用户管理</a></li>
                        <li class="breadcrumb-item active">教师审批</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="user_list.php" class="btn btn-outline-primary">
                    <i class="bi bi-people-fill me-1"></i> 用户列表
                </a>
            </div>
        </div>

        <!-- 消息通知 -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- 待审批教师申请 -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title">
                            <i class="bi bi-hourglass-split me-2"></i> 待审批教师申请
                        </h5>
                        <span class="badge bg-warning"><?= count($pending_teachers) ?> 待处理</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($pending_teachers) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($pending_teachers as $teacher): ?>
                                    <div class="list-group-item user-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-4">
                                                <h6 class="mb-0"><?= htmlspecialchars($teacher['name']) ?></h6>
                                                <small class="text-muted">
                                                    <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($teacher['email']) ?>
                                                </small>
                                            </div>
                                            <div class="col-md-3">
                                                <small class="text-muted">
                                                    <i class="bi bi-person-badge me-1"></i> 工号: <?= htmlspecialchars($teacher['student_id']) ?>
                                                </small>
                                                <br>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar me-1"></i> <?= $teacher['formatted_date'] ?>
                                                </small>
                                            </div>
                                            <div class="col-md-2">
                                                <span class="badge bg-warning status-badge">待审核</span>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <button type="button" class="btn btn-sm btn-outline-success me-1" 
                                                        data-bs-toggle="modal" data-bs-target="#approveModal" 
                                                        data-user-id="<?= $teacher['id'] ?>"
                                                        data-user-name="<?= htmlspecialchars($teacher['name']) ?>">
                                                    <i class="bi bi-check-lg"></i> 批准
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" data-bs-target="#rejectModal" 
                                                        data-user-id="<?= $teacher['id'] ?>"
                                                        data-user-name="<?= htmlspecialchars($teacher['name']) ?>">
                                                    <i class="bi bi-x-lg"></i> 拒绝
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-check2-all"></i>
                                <h4>没有待处理的教师申请</h4>
                                <p class="text-muted">当有教师提交注册申请时，它们将显示在这里。</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 申请统计 -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-graph-up me-2"></i> 申请统计
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h1 class="display-4 fw-bold text-center mb-3"><?= count($pending_teachers) ?></h1>
                            <p class="text-center text-muted mb-0">待处理的教师申请</p>
                        </div>
                        
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                今日新申请
                                <span class="badge bg-primary rounded-pill">
                                    <?php
                                    $today_count = 0;
                                    $today = date('Y-m-d');
                                    foreach ($pending_teachers as $teacher) {
                                        if (substr($teacher['created_at'], 0, 10) === $today) {
                                            $today_count++;
                                        }
                                    }
                                    echo $today_count;
                                    ?>
                                </span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                本周已处理
                                <span class="badge bg-success rounded-pill">
                                    <?php
                                    $week_ago = date('Y-m-d', strtotime('-7 days'));
                                    $processed_count = 0;
                                    foreach ($recent_applications as $app) {
                                        if (isset($app['review_date']) && $app['review_date'] >= $week_ago) {
                                            $processed_count++;
                                        }
                                    }
                                    echo $processed_count;
                                    ?>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 最近处理的申请 -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="bi bi-clock-history me-2"></i> 最近处理的申请
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($recent_applications) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>教师信息</th>
                                            <th>申请日期</th>
                                            <th>状态</th>
                                            <th>处理人</th>
                                            <th>处理时间</th>
                                            <th>备注</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_applications as $app): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($app['name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($app['email']) ?></small>
                                                    </div>
                                                </td>
                                                <td><?= $app['formatted_date'] ?></td>
                                                <td>
                                                    <?php 
                                                    $status_text = '';
                                                    $status_class = '';
                                                    
                                                    if (isset($app['verification_status'])) {
                                                        if ($app['verification_status'] === 'approved') {
                                                            $status_text = '已批准';
                                                            $status_class = 'success';
                                                        } elseif ($app['verification_status'] === 'rejected') {
                                                            $status_text = '已拒绝';
                                                            $status_class = 'danger';
                                                        }
                                                    } else {
                                                        if ($app['status'] === 'active') {
                                                            $status_text = '已批准';
                                                            $status_class = 'success';
                                                        } elseif ($app['status'] === 'inactive') {
                                                            $status_text = '已拒绝';
                                                            $status_class = 'danger';
                                                        }
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?= $status_class ?>"><?= $status_text ?></span>
                                                </td>
                                                <td><?= isset($app['admin_name']) ? htmlspecialchars($app['admin_name']) : '系统管理员' ?></td>
                                                <td><?= isset($app['review_date']) ? $app['review_date'] : '未记录' ?></td>
                                                <td>
                                                    <?php if (!empty($app['admin_notes'])): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                data-bs-toggle="tooltip" data-bs-placement="left" 
                                                                title="<?= htmlspecialchars($app['admin_notes']) ?>">
                                                            <i class="bi bi-info-circle"></i> 查看备注
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="text-muted">无备注</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="bi bi-clock"></i>
                                <h4>没有最近处理的申请</h4>
                                <p class="text-muted">当你处理教师申请后，它们将显示在这里。</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 批准模态框 -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="approveModalLabel">
                        <i class="bi bi-check-circle me-2"></i> 批准教师申请
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="approve_user_id">
                        <input type="hidden" name="action" value="approve">
                        
                        <p>您确定要批准 <strong id="approve_user_name"></strong> 的教师申请吗？</p>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            批准后，将向该教师发送通知邮件，他们将能够登录系统并创建课程。
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_notes_approve" class="form-label">管理员备注（可选）</label>
                            <textarea name="admin_notes" id="admin_notes_approve" class="form-control" rows="3" placeholder="添加备注"></textarea>
                            <div class="form-text">这些备注将仅对管理员可见</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i> 确认批准
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 拒绝模态框 -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="rejectModalLabel">
                        <i class="bi bi-x-circle me-2"></i> 拒绝教师申请
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="reject_user_id">
                        <input type="hidden" name="action" value="reject">
                        
                        <p>您确定要拒绝 <strong id="reject_user_name"></strong> 的教师申请吗？</p>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            拒绝后，该用户将无法登录系统，且将收到一封拒绝通知邮件。
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_notes_reject" class="form-label">拒绝原因（可选）</label>
                            <textarea name="admin_notes" id="admin_notes_reject" class="form-control" rows="3" placeholder="请说明拒绝原因"></textarea>
                            <div class="form-text">此信息将会发送给申请者</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-lg me-1"></i> 确认拒绝
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Handle approve modal
        const approveModal = document.getElementById('approveModal');
        if (approveModal) {
            approveModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const userName = button.getAttribute('data-user-name');
                
                document.getElementById('approve_user_id').value = userId;
                document.getElementById('approve_user_name').textContent = userName;
            });
        }
        
        // Handle reject modal
        const rejectModal = document.getElementById('rejectModal');
        if (rejectModal) {
            rejectModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const userName = button.getAttribute('data-user-name');
                
                document.getElementById('reject_user_id').value = userId;
                document.getElementById('reject_user_name').textContent = userName;
            });
        }
        
        // Auto-dismiss success messages
        window.setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>