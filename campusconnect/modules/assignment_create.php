<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// 验证用户权限 - 允许教师和管理员访问
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["teacher", "admin"])) {
    $_SESSION['error_message'] = "访问被拒绝！请先以教师或管理员身份登录。";
    header("Location: ../views/assignments_list.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"] ?? "用户";

// 处理表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 验证必填字段
    if (empty($_POST['course_id']) || empty($_POST['title']) || empty($_POST['description']) || empty($_POST['due_date'])) {
        $_SESSION['error_message'] = "请填写所有必填字段！";
        header("Location: ../views/add_assignment.php");
        exit;
    }

    // 获取并处理表单数据
    $course_id = intval($_POST['course_id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $max_points = isset($_POST['points']) ? intval($_POST['points']) : 100;
    
    // 如果是管理员且不是课程的负责教师，验证课程是否存在
    if ($role === "admin") {
        $course_stmt = $pdo->prepare("SELECT teacher_id FROM courses WHERE id = ?");
        $course_stmt->execute([$course_id]);
        $course = $course_stmt->fetch();
        
        if (!$course) {
            $_SESSION['error_message'] = "选择的课程不存在！";
            header("Location: ../views/add_assignment.php");
            exit;
        }
    } else {
        // 验证教师只能为自己的课程添加作业
        $course_stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND teacher_id = ?");
        $course_stmt->execute([$course_id, $user_id]);
        $course = $course_stmt->fetch();
        
        if (!$course) {
            $_SESSION['error_message'] = "您只能为自己教授的课程添加作业！";
            header("Location: ../views/add_assignment.php");
            exit;
        }
    }
    
    // 处理格式化截止日期
    // 如果日期格式为 Y-m-dTH:i (HTML datetime-local 输入格式)
    if (strpos($due_date, 'T') !== false) {
        $due_date = str_replace('T', ' ', $due_date);
    }
    
    // 处理文件上传
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['size'] > 0) {
        $upload_dir = "../uploads/assignments/";
        
        // 确保上传目录存在
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = $_FILES['attachment']['name'];
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_size = $_FILES['attachment']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // 验证文件大小（20MB限制）
        if ($file_size > 20 * 1024 * 1024) {
            $_SESSION['error_message'] = "文件大小超过限制！最大允许20MB。";
            header("Location: ../views/add_assignment.php");
            exit;
        }
        
        // 生成唯一文件名
        $unique_file_name = uniqid() . '_' . $file_name;
        $upload_path = $upload_dir . $unique_file_name;
        
        // 上传文件
        if (move_uploaded_file($file_tmp, $upload_path)) {
            $attachment = $upload_path;
        } else {
            $_SESSION['error_message'] = "文件上传失败，请重试！";
            header("Location: ../views/add_assignment.php");
            exit;
        }
    }
    
    // 准备插入数据
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 插入作业数据
        $stmt = $pdo->prepare("
            INSERT INTO assignments (course_id, title, description, due_date, max_points, attachment, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $course_id,
            $title,
            $description,
            $due_date,
            $max_points,
            $attachment
        ]);
        
        $assignment_id = $pdo->lastInsertId();
        
        // 记录活动日志
        $log_stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
            VALUES (?, 'create', 'assignment', ?, ?)
        ");
        
        $details = json_encode([
            'title' => $title,
            'course_id' => $course_id,
            'role' => $role
        ]);
        
        $log_stmt->execute([$user_id, $assignment_id, $details]);
        
        // 如果勾选了通知学生选项，记录通知请求
        if (isset($_POST['notify_students']) && $_POST['notify_students'] == "1") {
            // 这里可以实现发送通知给学生的逻辑
            // 或者记录到通知表中，由后台任务处理
            
            // 举例：记录通知事件
            $notify_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                VALUES (?, 'notify_request', 'assignment', ?, ?)
            ");
            
            $notify_details = json_encode([
                'type' => 'new_assignment',
                'title' => $title
            ]);
            
            $notify_stmt->execute([$user_id, $assignment_id, $notify_details]);
        }
        
        // 处理管理员特殊选项
        if ($role === "admin") {
            if (isset($_POST['featured']) && $_POST['featured'] == "1") {
                // 实现设为重要作业的逻辑
                // 例如，更新作业状态或添加到特殊表中
                $featured_stmt = $pdo->prepare("
                    UPDATE assignments SET featured = 1 WHERE id = ?
                ");
                $featured_stmt->execute([$assignment_id]);
            }
        }
        
        // 提交事务
        $pdo->commit();
        
        // 设置成功消息并重定向
        $_SESSION['success_message'] = "作业已成功发布！";
        header("Location: ../views/assignments_list.php");
        exit;
        
    } catch (PDOException $e) {
        // 回滚事务
        $pdo->rollBack();
        
        // 记录错误
        error_log("作业创建失败: " . $e->getMessage());
        
        // 设置错误消息并重定向
        $_SESSION['error_message'] = "作业发布失败：" . $e->getMessage();
        header("Location: ../views/add_assignment.php");
        exit;
    }
} else {
    // 如果不是POST请求，重定向到添加页面
    header("Location: ../views/add_assignment.php");
    exit;
}
?>