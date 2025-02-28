<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php"; // Connect to database

// ✅ If the user is already logged in, redirect to Dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: ../dashboard.php");
    exit;
}

$error_message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = trim($_POST["token"]);
    $student_id = trim($_POST["student_id"]);
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    
    $validation_errors = [];
    
    // Validate student ID format (10-digit number, first 4 digits must be the enrollment year)
    if (!preg_match('/^[0-9]{10}$/', $student_id) || substr($student_id, 0, 4) < 1980 || substr($student_id, 0, 4) > date('Y')) {
        $validation_errors[] = "Student ID must be a 10-digit number, and the first 4 digits must be a valid enrollment year.";
    }
    
    // Validate name format (allows Chinese, English, spaces, and hyphens)
    if (!preg_match('/^[\p{Han}a-zA-Z\s\-]+$/u', $name)) {
        $validation_errors[] = "Name can only contain Chinese or English characters, spaces, and hyphens.";
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Please enter a valid email address.";
    }
    
    // Validate password complexity
    if (strlen($password) < 8) {
        $validation_errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $validation_errors[] = "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $validation_errors[] = "Password must contain at least one lowercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $validation_errors[] = "Password must contain at least one number.";
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $validation_errors[] = "It is recommended that the password includes at least one special character.";
    }
    
    // If there are validation errors, display them
    if (!empty($validation_errors)) {
        $error_message = implode("<br>", $validation_errors);
    } else {
        // ✅ First, check if the Token is valid
        $stmt = $pdo->prepare("SELECT * FROM tokens WHERE token = ? AND is_used = 0 LIMIT 1");
        $stmt->execute([$token]);
        $token_data = $stmt->fetch();

        if (!$token_data) {
            $error_message = "❌ Invalid or already used registration Token!";
        } else {
            // Token is valid, allow registration
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // 1. Check if the email is already registered
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $check_stmt->execute([$email]);
            $exists = $check_stmt->fetchColumn();

            if ($exists > 0) {
                // Email is already taken
                $error_message = "❌ This email is already in use. Please use a different email.";
            } else {
                // 2. If not, proceed with inserting the user data
                $stmt = $pdo->prepare("
                    INSERT INTO users (student_id, name, email, password, role) 
                    VALUES (?, ?, ?, ?, 'student')
                ");
                if ($stmt->execute([$student_id, $name, $email, $password_hash])) {
                    // ✅ Mark Token as used
                    $stmt = $pdo->prepare("UPDATE tokens SET is_used = 1 WHERE token = ?");
                    $stmt->execute([$token]);

                    // ✅ Redirect after successful registration
                    header("Location: login.php?message=registered");
                    exit;
                } else {
                    $error_message = "❌ Registration failed. Please try again later!";
                }
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>用户注册 - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            padding: 0.5rem 2rem;
        }
        .password-strength {
            height: 5px;
            transition: all 0.3s ease;
            margin-top: 5px;
        }
        .validation-feedback {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .valid-feedback-item {
            color: #198754;
        }
        .invalid-feedback-item {
            color: #dc3545;
        }
    </style>
</head>
<body class="bg-light">
    <!-- 在表单前面添加这段代码，用于显示成功和错误消息 -->
<?php if(isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i> <?= $_SESSION['success_message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if(isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $_SESSION['error_message'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- 还可以在register.php中添加这段，用于保留之前输入的表单数据 -->
<?php
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);
?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card border-0 p-4">
                    <div class="text-center mb-4">
                        <h1 class="h3 mb-3 fw-normal">📋 CampusConnect</h1>
                        <p class="text-muted">Create Your Account</p>
                    </div>

                    <!-- 显示错误消息 -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="token" class="form-label">
                                <i class="bi bi-key-fill"></i> Registration Token
                            </label>
                            <input type="text" name="token" class="form-control" id="token" required>
                            <div class="invalid-feedback">Please enter a valid registration Token.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="student_id" class="form-label">
                                <i class="bi bi-person-badge"></i> 学号
                            </label>
                            <input type="text" name="student_id" class="form-control" id="student_id" 
                                   pattern="[0-9]{10}" title="请输入10位数字的学号" required maxlength="10">
                            <div class="validation-feedback" id="student_id_feedback">
                                <div class="form-text">Must be a 10-digit number, first 4 digits should be the enrollment year.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                <i class="bi bi-person-fill"></i> Name
                            </label>
                            <input type="text" name="name" class="form-control" id="name" required>
                            <div class="validation-feedback" id="name_feedback">
                                <div class="form-text">Only Chinese/English characters, spaces, and hyphens allowed.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                <i class="bi bi-envelope-fill"></i> Email
                            </label>
                            <input type="email" name="email" class="form-control" id="email" required>
                            <div class="validation-feedback" id="email_feedback">
                                <div class="form-text">Please enter a valid email address.</div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock-fill"></i> Password
                            </label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control" id="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength w-100 bg-light" id="passwordStrength"></div>
                            <div class="validation-feedback mt-2" id="password_feedback">
                                <div class="password-check" id="length_check"><i class="bi bi-x-circle"></i> At least 8 characters</div>
                                <div class="password-check" id="uppercase_check"><i class="bi bi-x-circle"></i> Contains an uppercase letter</div>
                                <div class="password-check" id="lowercase_check"><i class="bi bi-x-circle"></i> Contains a lowercase letter</div>
                                <div class="password-check" id="number_check"><i class="bi bi-x-circle"></i> Contains a number</div>
                                <div class="password-check" id="special_check"><i class="bi bi-x-circle"></i> Recommended to include a special character</div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="bi bi-check-circle-fill me-2"></i> Register Account
                            </button>
                            <a href="../index.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house-fill me-2"></i> Return to Homepage
                            </a>
                        </div>


                        <div class="mt-4 text-center">
                            <p class="mb-0 text-muted">Already have an account? <a href="login.php" class="text-decoration-none">Login</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 表单验证
        (() => {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // 密码显示/隐藏功能
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });

        // 学号验证
        const studentIdInput = document.getElementById('student_id');
        const studentIdFeedback = document.getElementById('student_id_feedback');
        
        studentIdInput.addEventListener('input', function() {
            const value = this.value;
            const isValid = /^[0-9]{10}$/.test(value);
            const year = value.substring(0, 4);
            const currentYear = new Date().getFullYear();
            const isValidYear = year >= 1980 && year <= currentYear;
            
            studentIdFeedback.innerHTML = '';
            
            if (value.length !== 10) {
            studentIdFeedback.innerHTML += '<div class="invalid-feedback-item">Student ID must be a 10-digit number</div>';
            } else if (!isValid) {
                studentIdFeedback.innerHTML += '<div class="invalid-feedback-item">Student ID can only contain numbers</div>';
            } else if (!isValidYear) {
                studentIdFeedback.innerHTML += '<div class="invalid-feedback-item">The first 4 digits must be a valid enrollment year</div>';
            } else {
                studentIdFeedback.innerHTML += '<div class="valid-feedback-item"><i class="bi bi-check-circle"></i> Student ID format is correct</div>';
            }

        });

        // 姓名验证
        const nameInput = document.getElementById('name');
        const nameFeedback = document.getElementById('name_feedback');
        
        nameInput.addEventListener('input', function() {
            const value = this.value;
            const isValid = /^[\u4e00-\u9fa5a-zA-Z\s\-]+$/.test(value);
            
            nameFeedback.innerHTML = '';
            
            if (value.length === 0) {
                nameFeedback.innerHTML += '<div class="form-text">Only Chinese and English characters, spaces, and hyphens are allowed. Special characters are not permitted.</div>';
            } else if (!isValid) {
                nameFeedback.innerHTML += '<div class="invalid-feedback-item">Name can only contain Chinese or English characters, spaces, and hyphens.</div>';
            } else {
                nameFeedback.innerHTML += '<div class="valid-feedback-item"><i class="bi bi-check-circle"></i> Name format is correct</div>';
            }

        });

        // 邮箱验证
        const emailInput = document.getElementById('email');
        const emailFeedback = document.getElementById('email_feedback');
        
        emailInput.addEventListener('input', function() {
            const value = this.value;
            const isValid = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(value);
            
            emailFeedback.innerHTML = '';
            
            if (value.length === 0) {
            emailFeedback.innerHTML += '<div class="form-text">Please enter a valid email address.</div>';
            } else if (!isValid) {
                emailFeedback.innerHTML += '<div class="invalid-feedback-item">Please enter a valid email format.</div>';
            } else {
                emailFeedback.innerHTML += '<div class="valid-feedback-item"><i class="bi bi-check-circle"></i> Email format is correct.</div>';
            }

        });

        // 密码强度检查
        const passwordInput = document.getElementById('password');
        const passwordStrength = document.getElementById('passwordStrength');
        const lengthCheck = document.getElementById('length_check');
        const uppercaseCheck = document.getElementById('uppercase_check');
        const lowercaseCheck = document.getElementById('lowercase_check');
        const numberCheck = document.getElementById('number_check');
        const specialCheck = document.getElementById('special_check');
        const submitBtn = document.getElementById('submitBtn');
        
        passwordInput.addEventListener('input', function() {
            const value = this.value;
            const hasLength = value.length >= 8;
            const hasUppercase = /[A-Z]/.test(value);
            const hasLowercase = /[a-z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(value);
            
            // 更新各项检查状态
            updateCheckStatus(lengthCheck, hasLength);
            updateCheckStatus(uppercaseCheck, hasUppercase);
            updateCheckStatus(lowercaseCheck, hasLowercase);
            updateCheckStatus(numberCheck, hasNumber);
            updateCheckStatus(specialCheck, hasSpecial);
            
            // 计算密码强度
            let strength = 0;
            if (hasLength) strength += 20;
            if (hasUppercase) strength += 20;
            if (hasLowercase) strength += 20;
            if (hasNumber) strength += 20;
            if (hasSpecial) strength += 20;
            
            // 更新强度指示条
            passwordStrength.style.width = strength + '%';
            
            // 设置颜色
            if (strength < 40) {
                passwordStrength.style.backgroundColor = '#dc3545'; // 红色
            } else if (strength < 60) {
                passwordStrength.style.backgroundColor = '#ffc107'; // 黄色
            } else if (strength < 80) {
                passwordStrength.style.backgroundColor = '#0dcaf0'; // 浅蓝色
            } else {
                passwordStrength.style.backgroundColor = '#198754'; // 绿色
            }
            
            // 强制要求满足所有必要条件才能提交
            if (hasLength && hasUppercase && hasLowercase && hasNumber) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        });
        
        function updateCheckStatus(element, isValid) {
            if (isValid) {
                element.classList.remove('invalid-feedback-item');
                element.classList.add('valid-feedback-item');
                element.querySelector('i').classList.remove('bi-x-circle');
                element.querySelector('i').classList.add('bi-check-circle');
            } else {
                element.classList.remove('valid-feedback-item');
                element.classList.add('invalid-feedback-item');
                element.querySelector('i').classList.remove('bi-check-circle');
                element.querySelector('i').classList.add('bi-x-circle');
            }
        }
    </script>
</body>
</html>