<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// 验证用户权限 - 允许教师和管理员访问
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["teacher", "admin"])) {
    die('<div class="alert alert-danger">访问被拒绝！请先以教师或管理员身份登录。</div>');
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"] ?? "用户";

// 获取课程列表 (管理员可以看到所有课程，教师只能看到自己的课程)
if ($role === "admin") {
    $stmt = $pdo->prepare("SELECT c.id, c.course_name, c.course_code, u.name as teacher_name 
                          FROM courses c 
                          JOIN users u ON c.teacher_id = u.id 
                          ORDER BY c.course_name ASC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name ASC");
    $stmt->execute([$user_id]);
}
$courses = $stmt->fetchAll();
$hasCourses = !empty($courses);

// 获取当前日期和默认截止日期（一周后）
$today = date('Y-m-d\TH:i');
$defaultDueDate = date('Y-m-d\TH:i', strtotime('+1 week'));
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布新作业 - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Flatpickr (日期时间选择器) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-dark: #0a58ca;
            --primary-light: #e7f0ff;
            --warning-color: #ffc107;
            --success-color: #198754;
            --danger-color: #dc3545;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .main-container {
            max-width: 1000px;
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
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
        }
        
        .card-header h5 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .form-group.required .form-label:after {
            content: " *";
            color: red;
        }
        
        .file-upload-wrapper {
            position: relative;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background-color: #f8f9fa;
        }
        
        .file-upload-wrapper:hover {
            border-color: var(--primary-color);
            background-color: var(--primary-light);
        }
        
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-upload-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .file-info {
            display: none;
            padding: 0.75rem;
            background-color: var(--primary-light);
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .badge-course-count {
            background-color: var(--primary-light);
            color: var(--primary-color);
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }
        
        .alert-no-courses {
            border-left: 4px solid var(--warning-color);
        }
        
        .points-badge {
            background-color: var(--primary-light);
            color: var(--primary-color);
            font-weight: 500;
            border-radius: 20px;
            padding: 0.5rem 1rem;
        }
        
        .option-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: white;
        }
        
        .preview-section {
            background-color: var(--primary-light);
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .preview-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-dark);
        }
        
        /* 动画效果 */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* 响应式调整 */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .admin-badge {
            background-color: #dc3545;
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
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
                            <i class="bi bi-speedometer2 me-1"></i> 仪表板
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-book me-1"></i> 课程
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-clipboard-check me-1"></i> 作业管理
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="#"><i class="bi bi-plus-circle me-1"></i> 发布新作业</a></li>
                            <li><a class="dropdown-item" href="assignments_list.php"><i class="bi bi-list-check me-1"></i> 作业列表</a></li>
                            <li><a class="dropdown-item" href="grade_assignments.php"><i class="bi bi-check2-circle me-1"></i> 批改作业</a></li>
                            <?php if ($role === "admin"): ?>
                            <li><a class="dropdown-item" href="grade_overview.php"><i class="bi bi-bar-chart me-1"></i> 成绩概览</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php if ($role === "admin"): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-gear me-1"></i> 管理
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="manage_users.php"><i class="bi bi-people me-1"></i> 用户管理</a></li>
                            <li><a class="dropdown-item" href="manage_courses.php"><i class="bi bi-book-half me-1"></i> 课程管理</a></li>
                            <li><a class="dropdown-item" href="system_settings.php"><i class="bi bi-sliders me-1"></i> 系统设置</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($user_name) ?>
                            <?php if ($role === "admin"): ?>
                            <span class="admin-badge">管理员</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-1"></i> 个人资料</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../modules/logout.php"><i class="bi bi-box-arrow-right me-1"></i> 退出登录</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <div class="main-container">
        <!-- 页面标题 -->
        <div class="page-header">
            <h2><i class="bi bi-clipboard-plus me-2"></i> 发布新作业</h2>
            <?php if ($hasCourses): ?>
                <span class="badge-course-count">
                    <i class="bi bi-book-fill me-1"></i> 
                    <?php if ($role === "admin"): ?>
                        系统中共有 <?= count($courses) ?> 门课程
                    <?php else: ?>
                        您有 <?= count($courses) ?> 门课程
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- 主卡片 -->
        <div class="card mb-4 fade-in">
            <div class="card-header">
                <h5><i class="bi bi-pencil-square me-2"></i> 作业详情</h5>
                <p class="mb-0 mt-2 small">
                    <?php if ($role === "admin"): ?>
                        为任何课程创建新的学习任务和作业
                    <?php else: ?>
                        为您的学生创建新的学习任务和作业
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="card-body">
                <?php if (!$hasCourses): ?>
                    <!-- 无课程提醒 -->
                    <div class="alert alert-warning alert-no-courses">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                            <div>
                                <?php if ($role === "admin"): ?>
                                    <h5 class="alert-heading">系统中尚无课程</h5>
                                    <p class="mb-0">需要先创建课程后才能发布作业。请先使用课程管理功能创建课程。</p>
                                <?php else: ?>
                                    <h5 class="alert-heading">您没有任何教授的课程</h5>
                                    <p class="mb-0">需要先创建或分配课程后才能发布作业。请联系管理员安排课程。</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- 作业表单 -->
                    <form id="assignmentForm" action="../modules/assignment_create.php" method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <!-- 左侧栏 -->
                            <div class="col-md-7">
                                <!-- 课程选择 -->
                                <div class="mb-4 form-group required">
                                    <label for="course_id" class="form-label">
                                        <i class="bi bi-book-fill me-1"></i> 选择课程
                                    </label>
                                    <select name="course_id" id="course_id" class="form-select" required>
                                        <option value="" selected disabled>-- 请选择一门课程 --</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?= $course['id'] ?>">
                                                <?= htmlspecialchars($course['course_name']) ?> 
                                                <?= !empty($course['course_code']) ? "(".htmlspecialchars($course['course_code']).")" : "" ?>
                                                <?php if ($role === "admin" && isset($course['teacher_name'])): ?>
                                                    - 教师: <?= htmlspecialchars($course['teacher_name']) ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">选择要发布作业的目标课程</div>
                                </div>

                                <!-- 作业标题 -->
                                <div class="mb-4 form-group required">
                                    <label for="title" class="form-label">
                                        <i class="bi bi-card-heading me-1"></i> 作业标题
                                    </label>
                                    <input type="text" name="title" id="title" class="form-control" 
                                           required maxlength="100" placeholder="例如：第三章习题">
                                    <div class="form-text">简明的标题有助于学生快速理解作业内容</div>
                                </div>

                                <!-- 作业描述 -->
                                <div class="mb-4 form-group required">
                                    <label for="description" class="form-label">
                                        <i class="bi bi-file-text me-1"></i> 作业描述
                                    </label>
                                    <textarea name="description" id="description" class="form-control" 
                                              required rows="6" placeholder="详细说明作业要求、完成方式以及评分标准..."></textarea>
                                    <div class="form-text">详细的描述可以减少学生的疑问并提高作业质量</div>
                                </div>
                            </div>

                            <!-- 右侧栏 -->
                            <div class="col-md-5">
                                <!-- 截止日期 -->
                                <div class="mb-4 form-group required">
                                    <label for="due_date" class="form-label">
                                        <i class="bi bi-calendar-event me-1"></i> 截止日期
                                    </label>
                                    <input type="datetime-local" name="due_date" id="due_date" class="form-control" 
                                           required min="<?= $today ?>" value="<?= $defaultDueDate ?>">
                                    <div class="form-text">设置合理的截止日期，建议给学生3-7天的完成时间</div>
                                </div>

                                <!-- 分数设置 -->
                                <div class="mb-4">
                                    <label for="points" class="form-label">
                                        <i class="bi bi-star me-1"></i> 作业分数
                                    </label>
                                    <div class="input-group">
                                        <input type="number" name="points" id="points" class="form-control" 
                                               value="100" min="0" max="1000">
                                        <span class="input-group-text">分</span>
                                    </div>
                                    <div class="form-text">设置此作业的总分，默认为100分</div>
                                </div>

                                <!-- 附件上传 -->
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="bi bi-paperclip me-1"></i> 附件（可选）
                                    </label>
                                    <div class="file-upload-wrapper">
                                        <div class="file-upload-icon">
                                            <i class="bi bi-cloud-arrow-up"></i>
                                        </div>
                                        <div class="file-upload-text">
                                            <p class="mb-1">拖放文件到此处或点击上传</p>
                                            <span class="text-muted small">支持PDF、Word、PPT等格式（最大20MB）</span>
                                        </div>
                                        <input type="file" name="attachment" id="attachment" class="form-control">
                                    </div>
                                    <div id="fileInfo" class="file-info"></div>
                                </div>

                                <!-- 附加选项 -->
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="mb-3"><i class="bi bi-sliders me-1"></i> 附加选项</h6>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="allow_late" name="allow_late" value="1">
                                            <label class="form-check-label" for="allow_late">
                                                允许逾期提交（自动扣分）
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="notify_students" name="notify_students" value="1" checked>
                                            <label class="form-check-label" for="notify_students">
                                                发布后通知所有学生
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="group_assignment" name="group_assignment" value="1">
                                            <label class="form-check-label" for="group_assignment">
                                                小组作业（允许小组提交）
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($role === "admin"): ?>
                                <!-- 管理员特权选项 -->
                                <div class="card bg-danger bg-opacity-10 mb-4">
                                    <div class="card-body">
                                        <h6 class="mb-3 text-danger"><i class="bi bi-shield-lock me-1"></i> 管理员选项</h6>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1">
                                            <label class="form-check-label" for="featured">
                                                设为重要作业（首页显示）
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="override_settings" name="override_settings" value="1">
                                            <label class="form-check-label" for="override_settings">
                                                覆盖课程默认设置
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 预览区域 -->
                        <div class="preview-section">
                            <div class="preview-title"><i class="bi bi-eye me-2"></i> 作业预览</div>
                            <div id="previewContent">
                                <div class="text-muted text-center py-3">
                                    完成表单后将在此处显示预览
                                </div>
                            </div>
                        </div>

                        <!-- 表单底部按钮 -->
                        <div class="d-flex justify-content-between mt-4">
                            <div>
                                <a href="../dashboard.php" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-arrow-left me-1"></i> 返回仪表板
                                </a>
                            </div>
                            <div>
                                <button type="button" id="previewBtn" class="btn btn-outline-primary me-2">
                                    <i class="bi bi-eye me-1"></i> 预览
                                </button>
                                <button type="submit" id="submitBtn" class="btn btn-primary">
                                    <i class="bi bi-check2-circle me-1"></i> 发布作业
                                </button>
                            </div>
                        </div>
                        
                        <!-- 隐藏字段，记录当前用户角色 -->
                        <input type="hidden" name="publish_role" value="<?= $role ?>">
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- 提示卡片 -->
        <div class="card bg-light fade-in">
            <div class="card-body">
                <h5><i class="bi bi-lightbulb me-2 text-warning"></i> 作业发布提示</h5>
                <ul class="mb-0">
                    <li><strong>清晰指引：</strong> 详细描述作业要求、格式和提交方式</li>
                    <li><strong>合理期限：</strong> 根据作业难度设置适当的截止日期</li>
                    <li><strong>提供资源：</strong> 包含相关学习材料或参考资料</li>
                    <li><strong>评分标准：</strong> 在描述中明确说明评分标准和权重</li>
                </ul>
            </div>
        </div>
    </div>
   <!-- JavaScript libraries -->
   <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/zh.js"></script>

    <script>
        $(document).ready(function() {
            <?php if ($hasCourses): ?>
                // 初始化日期选择器
                flatpickr("#due_date", {
                    enableTime: true,
                    dateFormat: "Y-m-d H:i",
                    minDate: "today",
                    locale: "zh",
                    time_24hr: true,
                    defaultHour: 23,
                    defaultMinute: 59
                });

                // 文件上传处理
                $("#attachment").change(function() {
                    const file = this.files[0];
                    if (file) {
                        // 获取文件大小
                        const fileSize = (file.size / 1024 / 1024).toFixed(2);
                        // 获取文件扩展名
                        const fileName = file.name;
                        const fileExt = fileName.split('.').pop().toLowerCase();
                        
                        // 显示文件信息
                        let icon = 'bi-file-earmark';
                        let colorClass = 'text-primary';
                        
                        // 根据文件类型设置不同的图标
                        if (['pdf'].includes(fileExt)) {
                            icon = 'bi-file-earmark-pdf';
                            colorClass = 'text-danger';
                        } else if (['doc', 'docx'].includes(fileExt)) {
                            icon = 'bi-file-earmark-word';
                            colorClass = 'text-primary';
                        } else if (['xls', 'xlsx'].includes(fileExt)) {
                            icon = 'bi-file-earmark-excel';
                            colorClass = 'text-success';
                        } else if (['ppt', 'pptx'].includes(fileExt)) {
                            icon = 'bi-file-earmark-ppt';
                            colorClass = 'text-warning';
                        } else if (['zip', 'rar'].includes(fileExt)) {
                            icon = 'bi-file-earmark-zip';
                            colorClass = 'text-secondary';
                        }
                        
                        const fileHtml = `
                            <div class="d-flex align-items-center">
                                <i class="bi ${icon} fs-2 me-3 ${colorClass}"></i>
                                <div>
                                    <div class="fw-medium text-truncate" style="max-width: 250px;">${fileName}</div>
                                    <div class="small text-muted">
                                        <span class="me-2">${fileExt.toUpperCase()}</span>
                                        <span>${fileSize} MB</span>
                                    </div>
                                </div>
                                <button type="button" id="removeFile" class="btn btn-sm btn-outline-danger ms-auto">
                                    <i class="bi bi-x"></i>
                                </button>
                            </div>
                        `;
                        
                        $("#fileInfo").html(fileHtml).show();
                    } else {
                        $("#fileInfo").html('').hide();
                    }
                });
                
                // 移除文件按钮
                $(document).on('click', '#removeFile', function() {
                    $("#attachment").val('');
                    $("#fileInfo").html('').hide();
                });
                
                // 预览功能
                function updatePreview() {
                    const title = $("#title").val() || "(未设标题)";
                    const description = $("#description").val() || "(未设描述)";
                    const dueDate = $("#due_date").val();
                    const points = $("#points").val();
                    const courseId = $("#course_id").val();
                    
                    let courseName = "未选择课程";
                    if (courseId) {
                        const courseSelect = document.getElementById('course_id');
                        const courseOption = courseSelect.options[courseSelect.selectedIndex];
                        courseName = courseOption.text;
                    }
                    
                    // 格式化日期显示
                    let formattedDate = "未设置";
                    if (dueDate) {
                        const dateObj = new Date(dueDate);
                        formattedDate = dateObj.toLocaleString('zh-CN', {
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    }
                    
                    // 文件信息
                    let attachmentInfo = "";
                    const file = document.getElementById('attachment').files[0];
                    if (file) {
                        attachmentInfo = `
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <i class="bi bi-paperclip me-1"></i> 附件
                                </div>
                                <div class="card-body py-2">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-file-earmark me-2 text-primary"></i>
                                        <span>${file.name}</span>
                                        <span class="ms-2 badge bg-secondary rounded-pill">${(file.size / 1024 / 1024).toFixed(2)} MB</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                    
                    // 组装预览HTML
                    const previewHtml = `
                        <div class="card mb-3">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">${title}</h5>
                                    <div class="small">${courseName}</div>
                                </div>
                                <span class="badge bg-light text-primary">${points} 分</span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <div><i class="bi bi-calendar-event me-1"></i> 发布日期: ${new Date().toLocaleDateString('zh-CN')}</div>
                                    <div class="text-danger"><i class="bi bi-alarm me-1"></i> 截止日期: ${formattedDate}</div>
                                </div>
                                <h6 class="mb-2"><i class="bi bi-card-text me-1"></i> 作业描述:</h6>
                                <div class="p-3 bg-light rounded">${description.replace(/\n/g, '<br>')}</div>
                            </div>
                        </div>
                        ${attachmentInfo}
                        <div class="d-flex justify-content-between align-items-center small text-muted">
                            <div><i class="bi bi-person-circle me-1"></i> 发布者: <?= htmlspecialchars($user_name) ?></div>
                            <div>
                                <i class="bi bi-check-circle me-1"></i> 允许逾期提交: ${$("#allow_late").is(":checked") ? "是" : "否"}
                                <span class="ms-2"><i class="bi bi-people me-1"></i> 小组作业: ${$("#group_assignment").is(":checked") ? "是" : "否"}</span>
                            </div>
                        </div>
                    `;
                    
                    $("#previewContent").html(previewHtml);
                }
                
                // 预览按钮点击
                $("#previewBtn").click(function() {
                    updatePreview();
                    // 滚动到预览区域
                    $('html, body').animate({
                        scrollTop: $(".preview-section").offset().top - 100
                    }, 500);
                });
                
                // 表单变化时更新预览
                $("#title, #description, #due_date, #points, #course_id, #allow_late, #group_assignment").on('change input', function() {
                    updatePreview();
                });
                
                // 表单验证
                $("#assignmentForm").on('submit', function(e) {
                    // 基本验证
                    const courseId = $('#course_id').val();
                    if (!courseId) {
                        e.preventDefault();
                        alert('请选择一个课程！');
                        $('#course_id').focus();
                        return false;
                    }

                    // 标题验证
                    const title = $('#title').val().trim();
                    if (!title) {
                        e.preventDefault();
                        alert('请输入作业标题！');
                        $('#title').focus();
                        return false;
                    }

                    // 描述验证
                    const description = $('#description').val().trim();
                    if (!description) {
                        e.preventDefault();
                        alert('请输入作业描述！');
                        $('#description').focus();
                        return false;
                    }
                    
                    // 截止日期验证
                    const dueDate = $('#due_date').val();
                    if (!dueDate) {
                        e.preventDefault();
                        alert('请设置截止日期！');
                        $('#due_date').focus();
                        return false;
                    }
                    
                    // 文件大小验证
                    const file = document.getElementById('attachment').files[0];
                    if (file && file.size > 20 * 1024 * 1024) { // 20MB
                        e.preventDefault();
                        alert('附件大小不能超过20MB！');
                        return false;
                    }
                    
                    // 防止重复提交
                    $("#submitBtn").prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>提交中...');
                    
                    return true;
                });
                
                // 初始化时加载一次预览
                setTimeout(updatePreview, 1000);
            <?php endif; ?>
        });
    </script>
</body>
</html>