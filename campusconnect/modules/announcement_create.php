<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// 修改：允许教师和管理员发布公告
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] !== "teacher" && $_SESSION["role"] !== "admin")) {
    die("Access denied! Please login as a teacher or admin first.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_id = $_POST["course_id"];
    $title = trim($_POST["title"]);
    $content = trim($_POST["content"]);
    
    // 如果是管理员且指定了教师ID，则使用该ID，否则使用当前用户ID
    $teacher_id = isset($_POST["teacher_id"]) ? $_POST["teacher_id"] : $_SESSION["user_id"];
    
    // 获取额外的字段
    $priority = isset($_POST["priority"]) ? $_POST["priority"] : "normal";
    $pin_announcement = isset($_POST["pin_announcement"]) ? 1 : 0;
    
    // 检查发布者是否与教师不同（管理员以教师身份发布）
    $publisher_id = null;
    if ($_SESSION["role"] === "admin" && $teacher_id != $_SESSION["user_id"]) {
        $publisher_id = $_SESSION["user_id"];
    }
    
    // 插入数据库并包含扩展字段
    $stmt = $pdo->prepare("INSERT INTO announcements (course_id, teacher_id, title, content, priority, publisher_id) 
                         VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$course_id, $teacher_id, $title, $content, $priority, $publisher_id])) {
        // 如果请求了电子邮件通知
        if (isset($_POST["send_email"]) && $_POST["send_email"] == 1) {
            // 在这里添加发送电子邮件的代码
            // 这只是电子邮件功能的占位符
        }
        
        // 重定向到公告列表
        header("Location: ../views/announcements_list.php?success=1");
        exit;
    } else {
        // 重定向并显示错误
        header("Location: ../views/add_announcement.php?error=1");
        exit;
    }
}
?>