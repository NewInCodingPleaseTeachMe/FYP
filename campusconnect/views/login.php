<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php"; // Connect to database

// If user is already logged in, redirect to dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: ../dashboard.php");
    exit;
}

$error_message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        $error_message = "Please enter your email and password";
    } else {
        // Modified query to include user status
        $stmt = $pdo->prepare("SELECT id, name, role, password, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password"])) {
            // Check user status
            if ($user["status"] === "active") {
                // Login successful, create session
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["name"] = $user["name"];
                $_SESSION["role"] = $user["role"];

                // Record login activity
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, created_at) 
                                          VALUES (?, 'login', 'system', 0, NOW())");
                $log_stmt->execute([$user["id"]]);

                // Update last login time
                $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $update_stmt->execute([$user["id"]]);

                // Redirect to dashboard
                header("Location: ../dashboard.php");
                exit;
            } elseif ($user["status"] === "pending") {
                $error_message = "Your account is pending administrator approval. You will receive an email notification once approved.";
            } else {
                $error_message = "Your account has been disabled. Please contact an administrator for more information.";
            }
        } else {
            $error_message = "Login failed, incorrect email or password!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 420px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(45deg, #6610f2, #0d6efd);
            color: white;
            border-radius: 1rem 1rem 0 0 !important;
            text-align: center;
            padding: 2rem 1rem;
        }
        .logo {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .form-floating > label {
            padding-left: 3rem;
        }
        .form-floating > .form-control {
            padding-left: 3rem;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 1.1rem;
            z-index: 2;
            color: #6c757d;
        }
        .btn-login {
            padding: 0.75rem;
            font-weight: bold;
            background: linear-gradient(45deg, #0d6efd, #0dcaf0);
            border: none;
        }
        .btn-login:hover {
            background: linear-gradient(45deg, #0b5ed7, #0bacbe);
        }
        .footer-text {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .alert-shake {
            animation: shake 0.5s linear 1;
        }
        @keyframes shake {
            0% { transform: translateX(0); }
            20% { transform: translateX(-10px); }
            40% { transform: translateX(10px); }
            60% { transform: translateX(-10px); }
            80% { transform: translateX(10px); }
            100% { transform: translateX(0); }
        }
    </style>
</head>
<body>
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
    <div class="container login-container">
        <div class="card">
            <div class="card-header text-center">
                <div class="logo">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h2 class="fw-bold mb-0">CampusConnect</h2>
                <p class="mb-0">Education Management System</p>
            </div>
            <div class="card-body p-4 p-md-5">
                <h4 class="mb-4">User Login</h4>

                <?php if (!empty($error_message)) : ?>
                    <div class="alert alert-danger alert-shake">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?= htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                    <div class="form-floating mb-3">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control" id="email" placeholder="name@example.com" required>
                        <label for="email">Email Address</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                        <label for="password">Password</label>
                    </div>
                    
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="remember-me">
                        <label class="form-check-label" for="remember-me">
                            Remember Me
                        </label>
                        <a href="reset_password.php" class="float-end text-decoration-none">Forgot Password?</a>
                    </div>
                    
                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">Don't have an account?</p>
                        <div class="d-flex justify-content-center mt-2">
                            <a href="register.php" class="btn btn-outline-primary me-2">
                                <i class="bi bi-mortarboard me-1"></i> Student Registration
                            </a>
                            <a href="teacher_register.php" class="btn btn-outline-success">
                                <i class="bi bi-person-workspace me-1"></i> Teacher Registration
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <p class="footer-text mb-0">
                <a href="../index.php" class="text-decoration-none">
                    <i class="bi bi-house-door me-1"></i>Return to Homepage
                </a> | 
                <a href="about.php" class="text-decoration-none">About Us</a> 
                
            </p>
            <p class="footer-text mt-2">© 2025 CampusConnect. All Rights Reserved.</p>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Focus on email input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('email').focus();
        });
    </script>
</body>
</html>