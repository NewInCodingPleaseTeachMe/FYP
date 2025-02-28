<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// 验证请求是否为POST
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    $_SESSION['error_message'] = "Invalid request method!";
    header("Location: ../views/register.php");
    exit;
}

// 获取表单数据
$token = trim($_POST["token"] ?? "");
$student_id = trim($_POST["student_id"] ?? "");
$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

// 验证所有必填字段
if (empty($token) || empty($student_id) || empty($name) || empty($email) || empty($password)) {
    $_SESSION['error_message'] = "All fields are required!";
    $_SESSION['form_data'] = [
        'student_id' => $student_id,
        'name' => $name,
        'email' => $email
    ];
    header("Location: ../views/register.php");
    exit;
}

// 验证token
$stmt = $pdo->prepare("SELECT * FROM tokens WHERE token = ? AND is_used = 0 LIMIT 1");
$stmt->execute([$token]);
$token_data = $stmt->fetch();

if (!$token_data) {
    $_SESSION['error_message'] = "Invalid or already used registration token!";
    $_SESSION['form_data'] = [
        'student_id' => $student_id,
        'name' => $name,
        'email' => $email
    ];
    header("Location: ../views/register.php");
    exit;
}

// 验证学号格式
if (!preg_match('/^[0-9]{10}$/', $student_id) || 
    substr($student_id, 0, 4) < 1980 || 
    substr($student_id, 0, 4) > date('Y')) {
    $_SESSION['error_message'] = "Student ID must be a 10-digit number, and the first 4 digits must be a valid enrollment year.";
    $_SESSION['form_data'] = [
        'student_id' => $student_id,
        'name' => $name,
        'email' => $email
    ];
    header("Location: ../views/register.php");
    exit;
}

// 验证姓名格式
if (!preg_match('/^[\p{Han}a-zA-Z\s\-]+$/u', $name)) {
    $_SESSION['error_message'] = "Name can only contain Chinese or English characters, spaces, and hyphens.";
    $_SESSION['form_data'] = [
        'student_id' => $student_id,
        'name' => $name,
        'email' => $email
    ];
    header("Location: ../views/register.php");
    exit;
}

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = "Please enter a valid email address.";
    $_SESSION['form_data'] = [
        'student_id' => $student_id,
        'name' => $name,
        'email' => $email
    ];
    header("Location: ../views/register.php");
    exit;
}

// 验证密码复杂度
$password_errors = [];
if (strlen($password) < 8) {
    $password_errors[] = "Password must be at least 8 characters long.";
}
if (!preg_match('/[A-Z]/', $password)) {
    $password_errors[] = "Password must contain at least one uppercase letter.";
}
if (!preg_match('/[a-z]/', $password)) {
    $password_errors[] = "Password must contain at least one lowercase letter.";
}
if (!preg_match('/[0-9]/', $password)) {
    $password_errors[] = "Password must contain at least one number.";
}

if (!empty($password_errors)) {
    $_SESSION['error_message'] = implode("<br>", $password_errors);
    $_SESSION['form_data'] = [
        'student_id' => $student_id,
        'name' => $name,
        'email' => $email
    ];
    header("Location: ../views/register.php");
    exit;
}

try {
    // 检查邮箱或学号是否已被注册
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR student_id = ?");
    $stmt->execute([$email, $student_id]);
    
    if ($user = $stmt->fetch()) {
        $_SESSION['error_message'] = "This email or student ID is already registered!";
        $_SESSION['form_data'] = [
            'student_id' => $student_id,
            'name' => $name,
            'email' => $email
        ];
        header("Location: ../views/register.php");
        exit;
    }
    
    // 开始事务
    $pdo->beginTransaction();
    
    // 安全地存储密码
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // 插入新用户
    $stmt = $pdo->prepare("INSERT INTO users (student_id, name, email, password, role, created_at) VALUES (?, ?, ?, ?, 'student', NOW())");
    $stmt->execute([$student_id, $name, $email, $password_hash]);
    
    // 标记token为已使用
    $update_token = $pdo->prepare("UPDATE tokens SET is_used = 1, used_by = LAST_INSERT_ID(), used_at = NOW() WHERE token = ?");
    $update_token->execute([$token]);
    
    // 记录活动日志
    $user_id = $pdo->lastInsertId();
    $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) VALUES (?, 'register', 'system', 0, ?)");
    $log_stmt->execute([$user_id, json_encode(['student_id' => $student_id, 'email' => $email])]);
    
    // 提交事务
    $pdo->commit();
    
    // 设置成功消息并重定向
    $_SESSION['success_message'] = "Registration successful! You can now log in with your credentials.";
    header("Location: ../views/login.php");
    exit;
    
} catch (PDOException $e) {
    // 事务回滚
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // 记录错误
    error_log("Registration error: " . $e->getMessage());
    
    // 设置错误消息并重定向
    $_SESSION['error_message'] = "Registration failed: " . $e->getMessage();
    $_SESSION['form_data'] = [
        'student_id' => $student_id,
        'name' => $name,
        'email' => $email
    ];
    header("Location: ../views/register.php");
    exit;
}
?>