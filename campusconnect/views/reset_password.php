<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// If user is already logged in, redirect to dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: ../dashboard.php");
    exit;
}

$error_message = "";
$success_message = "";
$email_sent = false;
$token_valid = false;
$reset_successful = false;

// Step 1: User submits email to request password reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["request_reset"])) {
    $email = trim($_POST["email"]);

    if (empty($email)) {
        $error_message = "Please enter your email address";
    } else {
        // Check if email exists in the database
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Store token in the database
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, is_used) VALUES (?, ?, ?, 0)");
            $stmt->execute([$user["id"], $token, $expires]);

            // In a real application, you would send an email with the reset link
            // For demo purposes, we'll just display the link
            $reset_link = "reset_password.php?token=" . $token;
            
            // Log activity for security
            $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, created_at) 
                                      VALUES (?, 'password_reset_request', 'system', NOW())");
            $log_stmt->execute([$user["id"]]);

            $success_message = "Please check your email for instructions on how to reset your password.";
            $email_sent = true;
        } else {
            // Don't reveal that the email doesn't exist for security reasons
            $success_message = "If this email is registered in our system, you will receive password reset instructions.";
            $email_sent = true;
        }
    }
}

// Step 2: User clicks on reset link from email and enters new password
if (isset($_GET["token"])) {
    $token = $_GET["token"];
    
    // Verify the token
    $stmt = $pdo->prepare("
        SELECT pr.*, u.email, u.name 
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? AND pr.is_used = 0 AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset_data = $stmt->fetch();

    if ($reset_data) {
        $token_valid = true;
    } else {
        $error_message = "This password reset link is invalid or has expired.";
    }
}

// Step 3: User submits new password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["reset_password"]) && isset($_POST["token"])) {
    $token = trim($_POST["token"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    
    $validation_errors = [];
    
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
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $validation_errors[] = "The passwords you entered do not match.";
    }

    // If there are validation errors, display them
    if (!empty($validation_errors)) {
        $error_message = implode("<br>", $validation_errors);
        $token_valid = true; // Keep form open
    } else {
        // Verify the token again
        $stmt = $pdo->prepare("
            SELECT pr.*, u.id as user_id 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? AND pr.is_used = 0 AND pr.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch();

        if ($reset_data) {
            // Update user's password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($update_stmt->execute([$password_hash, $reset_data["user_id"]])) {
                // Mark token as used
                $stmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                // Log activity
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, created_at) 
                                          VALUES (?, 'password_reset_complete', 'system', NOW())");
                $log_stmt->execute([$reset_data["user_id"]]);
                
                $success_message = "Your password has been successfully reset. You can now login with your new password.";
                $reset_successful = true;
            } else {
                $error_message = "Failed to update password. Please try again later.";
                $token_valid = true; // Keep form open
            }
        } else {
            $error_message = "This password reset link is invalid or has expired.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-container {
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
        .btn-reset {
            padding: 0.75rem;
            font-weight: bold;
            background: linear-gradient(45deg, #0d6efd, #0dcaf0);
            border: none;
        }
        .btn-reset:hover {
            background: linear-gradient(45deg, #0b5ed7, #0bacbe);
        }
        .footer-text {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .alert-shake {
            animation: shake 0.5s linear 1;
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
    <div class="container reset-container">
        <div class="card">
            <div class="card-header text-center">
                <div class="logo">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <h2 class="fw-bold mb-0">CampusConnect</h2>
                <p class="mb-0">Password Recovery</p>
            </div>
            <div class="card-body p-4 p-md-5">
                <?php if ($reset_successful): ?>
                    <!-- Step 3: Success message after password reset -->
                    <div class="text-center mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Password Reset Successful</h4>
                    </div>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message); ?>
                    </div>
                    <div class="d-grid mt-4">
                        <a href="login.php" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Login Now
                        </a>
                    </div>
                
                <?php elseif ($token_valid): ?>
                    <!-- Step 2: Reset form with token -->
                    <h4 class="mb-4">Create New Password</h4>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-shake">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token); ?>">
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="bi bi-lock-fill"></i> New Password
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
                        
                        <div class="form-floating mb-4">
                            <i class="bi bi-lock-fill input-icon"></i>
                            <input type="password" name="confirm_password" class="form-control" id="confirm_password" placeholder="Confirm Password" required>
                            <label for="confirm_password">Confirm Password</label>
                            <div class="validation-feedback" id="confirm_feedback"></div>
                        </div>
                        
                        <div class="d-grid mb-4">
                            <button type="submit" name="reset_password" class="btn btn-primary btn-reset" id="submitBtn">
                                <i class="bi bi-check-circle-fill me-2"></i>Reset Password
                            </button>
                        </div>
                    </form>
                
                <?php elseif ($email_sent): ?>
                    <!-- Step 1: Success after email submission -->
                    <div class="text-center mb-4">
                        <i class="bi bi-envelope-check-fill text-primary" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">Check Your Email</h4>
                    </div>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success_message); ?>
                    </div>
                    <div class="mt-4">
                        <p class="text-muted text-center">
                            Didn't receive an email? Check your spam folder or request again.
                        </p>
                        <div class="d-grid gap-2">
                            <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Reset Password
                            </a>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Return to Login
                            </a>
                        </div>
                    </div>
                    
                    <!-- For demo purposes, show the reset link -->
                    <?php if (isset($reset_link) && !empty($reset_link)): ?>
                        <div class="mt-4 p-3 bg-light border rounded">
                            <p class="mb-1 text-muted">Demo reset link (would be emailed in production):</p>
                            <a href="<?= htmlspecialchars($reset_link); ?>" class="word-break"><?= htmlspecialchars($reset_link); ?></a>
                        </div>
                    <?php endif; ?>
                
                <?php else: ?>
                    <!-- Step 1: Request password reset form -->
                    <h4 class="mb-4">Reset Your Password</h4>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger alert-shake">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted mb-4">
                        Enter your email address below and we'll send you instructions to reset your password.
                    </p>

                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                        <div class="form-floating mb-4">
                            <i class="bi bi-envelope input-icon"></i>
                            <input type="email" name="email" class="form-control" id="email" placeholder="name@example.com" required>
                            <label for="email">Email Address</label>
                        </div>
                        
                        <div class="d-grid mb-4">
                            <button type="submit" name="request_reset" class="btn btn-primary btn-reset">
                                <i class="bi bi-send-fill me-2"></i>Send Reset Link
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <p class="mb-0">Remembered your password? <a href="login.php" class="text-decoration-none fw-bold">Login</a></p>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <p class="footer-text mb-0">
                <a href="../index.php" class="text-decoration-none">
                    <i class="bi bi-house-door me-1"></i>Return to Homepage
                </a> | 
                <a href="login.php" class="text-decoration-none">Login</a> 
            </p>
            <p class="footer-text mt-2">© 2025 CampusConnect. All Rights Reserved.</p>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Focus on email input on page load if not in token mode
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$token_valid && !$email_sent && !$reset_successful): ?>
                document.getElementById('email').focus();
            <?php endif; ?>
            
            <?php if ($token_valid): ?>
                // Password visibility toggle
                const togglePassword = document.querySelector('#togglePassword');
                const password = document.querySelector('#password');
                
                togglePassword.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    this.querySelector('i').classList.toggle('bi-eye');
                    this.querySelector('i').classList.toggle('bi-eye-slash');
                });
                
                // Password strength checker
                const passwordInput = document.getElementById('password');
                const confirmInput = document.getElementById('confirm_password');
                const passwordStrength = document.getElementById('passwordStrength');
                const lengthCheck = document.getElementById('length_check');
                const uppercaseCheck = document.getElementById('uppercase_check');
                const lowercaseCheck = document.getElementById('lowercase_check');
                const numberCheck = document.getElementById('number_check');
                const specialCheck = document.getElementById('special_check');
                const confirmFeedback = document.getElementById('confirm_feedback');
                const submitBtn = document.getElementById('submitBtn');
                
                passwordInput.addEventListener('input', function() {
                    const value = this.value;
                    const hasLength = value.length >= 8;
                    const hasUppercase = /[A-Z]/.test(value);
                    const hasLowercase = /[a-z]/.test(value);
                    const hasNumber = /[0-9]/.test(value);
                    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(value);
                    
                    // Update check status
                    updateCheckStatus(lengthCheck, hasLength);
                    updateCheckStatus(uppercaseCheck, hasUppercase);
                    updateCheckStatus(lowercaseCheck, hasLowercase);
                    updateCheckStatus(numberCheck, hasNumber);
                    updateCheckStatus(specialCheck, hasSpecial);
                    
                    // Calculate strength
                    let strength = 0;
                    if (hasLength) strength += 20;
                    if (hasUppercase) strength += 20;
                    if (hasLowercase) strength += 20;
                    if (hasNumber) strength += 20;
                    if (hasSpecial) strength += 20;
                    
                    // Update strength indicator
                    passwordStrength.style.width = strength + '%';
                    
                    // Set color
                    if (strength < 40) {
                        passwordStrength.style.backgroundColor = '#dc3545'; // Red
                    } else if (strength < 60) {
                        passwordStrength.style.backgroundColor = '#ffc107'; // Yellow
                    } else if (strength < 80) {
                        passwordStrength.style.backgroundColor = '#0dcaf0'; // Light blue
                    } else {
                        passwordStrength.style.backgroundColor = '#198754'; // Green
                    }
                    
                    // Check if passwords match
                    checkPasswordMatch();
                    
                    // Require minimum security
                    validateForm();
                });
                
                confirmInput.addEventListener('input', checkPasswordMatch);
                
                function checkPasswordMatch() {
                    const password = passwordInput.value;
                    const confirmPassword = confirmInput.value;
                    
                    confirmFeedback.innerHTML = '';
                    
                    if (confirmPassword.length === 0) {
                        return;
                    }
                    
                    if (password === confirmPassword) {
                        confirmFeedback.innerHTML = '<div class="valid-feedback-item"><i class="bi bi-check-circle"></i> Passwords match</div>';
                    } else {
                        confirmFeedback.innerHTML = '<div class="invalid-feedback-item"><i class="bi bi-x-circle"></i> Passwords do not match</div>';
                    }
                    
                    validateForm();
                }
                
                function validateForm() {
                    const password = passwordInput.value;
                    const confirmPassword = confirmInput.value;
                    
                    const hasLength = password.length >= 8;
                    const hasUppercase = /[A-Z]/.test(password);
                    const hasLowercase = /[a-z]/.test(password);
                    const hasNumber = /[0-9]/.test(password);
                    const passwordsMatch = password === confirmPassword && confirmPassword !== '';
                    
                    if (hasLength && hasUppercase && hasLowercase && hasNumber && passwordsMatch) {
                        submitBtn.disabled = false;
                    } else {
                        submitBtn.disabled = true;
                    }
                }
                
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
            <?php endif; ?>
        });
    </script>
</body>
</html>