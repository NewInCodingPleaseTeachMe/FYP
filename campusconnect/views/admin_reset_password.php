<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// **🔐 Restrict access to unauthenticated users**
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// **🔐 Restrict access to non-admin users**
if ($_SESSION["role"] !== "admin") {
    header("Location: ../dashboard.php?error=unauthorized");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_users.php?error=invalid_id");
    exit;
}

$user_id = intval($_GET['id']);

// Fetch user details
$stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If user not found, redirect back to list
if (!$user) {
    header("Location: manage_users.php?error=user_not_found");
    exit;
}

$success_message = "";
$error_message = "";

// Process password reset form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password
    if (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        try {
            // Check if users table has updated_at column
            $table_info_stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'updated_at'");
            $table_info_stmt->execute();
            $has_updated_at = $table_info_stmt->rowCount() > 0;
            
            // Update the password in the database - with or without updated_at
            if ($has_updated_at) {
                $update_stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            }
            
            $result = $update_stmt->execute([$hashed_password, $user_id]);
            
            if ($result) {
                // Log the password reset
                $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $log_stmt->execute([$_SESSION['user_id'], 'password_reset', 'user', $user_id]);
                
                $success_message = "Password has been reset successfully.";
            } else {
                $error_message = "Failed to reset password. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Function to generate a random secure password
function generateRandomPassword($length = 12) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()-_=+';
    $password = '';
    $max = strlen($characters) - 1;
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, $max)];
    }
    
    return $password;
}

$suggested_password = generateRandomPassword();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Reset Password for <?= htmlspecialchars($user['name']) ?> - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            border-radius: 0.8rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
        }
        .form-control {
            border-radius: 0.5rem;
        }
        .card-header {
            background: linear-gradient(45deg, #6610f2, #0d6efd);
            color: white;
            border-radius: 0.8rem 0.8rem 0 0 !important;
        }
        .password-strength {
            height: 5px;
            transition: all 0.3s;
        }
        .pwd-very-weak { width: 20%; background-color: #dc3545; }
        .pwd-weak { width: 40%; background-color: #ffc107; }
        .pwd-medium { width: 60%; background-color: #fd7e14; }
        .pwd-strong { width: 80%; background-color: #20c997; }
        .pwd-very-strong { width: 100%; background-color: #198754; }
        .password-feedback {
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        .btn-toggle-password {
            border: none;
            background: none;
            color: #6c757d;
        }
        .btn-toggle-password:focus {
            box-shadow: none;
        }
        .password-requirements {
            font-size: 0.85rem;
        }
        .req-met {
            color: #198754;
        }
        .req-unmet {
            color: #6c757d;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Top Navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-key-fill text-primary me-2"></i>Admin Reset Password
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="manage_users.php">User Management</a></li>
                        <li class="breadcrumb-item"><a href="user_profile.php?id=<?= $user['id'] ?>"><?= htmlspecialchars($user['name']) ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Reset Password</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="user_profile.php?id=<?= $user['id'] ?>" class="btn btn-outline-secondary action-button">
                    <i class="bi bi-arrow-left me-2"></i>Back to User Profile
                </a>
            </div>
        </div>

        <!-- Password Reset Card -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header p-4">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="bi bi-shield-lock-fill fs-1"></i>
                            </div>
                            <div>
                                <h4 class="mb-1">Reset Password for User</h4>
                                <p class="mb-0">You are resetting password for <strong><?= htmlspecialchars($user['name']) ?></strong> (<?= htmlspecialchars($user['email']) ?>) - <?= ucfirst($user['role']) ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <form action="" method="post" id="passwordResetForm">
                            <div class="mb-4">
                                <div class="alert alert-info">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <i class="bi bi-info-circle-fill fs-4"></i>
                                        </div>
                                        <div>
                                            <h5 class="alert-heading">Important Information</h5>
                                            <p>Resetting a password will:</p>
                                            <ul>
                                                <li>Immediately invalidate the user's current password</li>
                                                <li>Force the user to use the new password for their next login</li>
                                                <li>Be logged in the system activity logs</li>
                                            </ul>
                                            <p class="mb-0">Make sure to communicate the new password to the user securely.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label for="new_password" class="form-label fw-bold">New Password</label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="generatePassword">
                                        <i class="bi bi-magic me-2"></i>Generate Random Password
                                    </button>
                                </div>
                                <div class="input-group mb-2">
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <button class="btn btn-outline-secondary btn-toggle-password" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar password-strength" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="password-feedback text-muted"></div>
                                
                                <div class="password-requirements mt-3">
                                    <p class="mb-2 fw-bold">Password requirements:</p>
                                    <ul class="list-unstyled mb-0">
                                        <li id="req-length" class="req-unmet"><i class="bi bi-circle me-2"></i>At least 8 characters long</li>
                                        <li id="req-uppercase" class="req-unmet"><i class="bi bi-circle me-2"></i>Contains uppercase letters</li>
                                        <li id="req-lowercase" class="req-unmet"><i class="bi bi-circle me-2"></i>Contains lowercase letters</li>
                                        <li id="req-number" class="req-unmet"><i class="bi bi-circle me-2"></i>Contains numbers</li>
                                        <li id="req-special" class="req-unmet"><i class="bi bi-circle me-2"></i>Contains special characters</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label fw-bold">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary btn-toggle-password" type="button" id="toggleConfirmPassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div id="password-match-feedback" class="form-text"></div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="reset_password" class="btn btn-primary btn-lg">
                                    <i class="bi bi-check-circle-fill me-2"></i>Reset Password
                                </button>
                                <a href="user_profile.php?id=<?= $user['id'] ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrength = document.querySelector('.password-strength');
        const passwordFeedback = document.querySelector('.password-feedback');
        const passwordMatchFeedback = document.getElementById('password-match-feedback');
        const togglePasswordBtn = document.getElementById('togglePassword');
        const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
        const generatePasswordBtn = document.getElementById('generatePassword');
        const resetForm = document.getElementById('passwordResetForm');
        
        // Password requirement elements
        const reqLength = document.getElementById('req-length');
        const reqUppercase = document.getElementById('req-uppercase');
        const reqLowercase = document.getElementById('req-lowercase');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        
        // Toggle password visibility
        togglePasswordBtn.addEventListener('click', function() {
            if (newPasswordInput.type === 'password') {
                newPasswordInput.type = 'text';
                togglePasswordBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                newPasswordInput.type = 'password';
                togglePasswordBtn.innerHTML = '<i class="bi bi-eye"></i>';
            }
        });
        
        toggleConfirmPasswordBtn.addEventListener('click', function() {
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                toggleConfirmPasswordBtn.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                confirmPasswordInput.type = 'password';
                toggleConfirmPasswordBtn.innerHTML = '<i class="bi bi-eye"></i>';
            }
        });
        
        // Generate random password
        generatePasswordBtn.addEventListener('click', function() {
            newPasswordInput.value = "<?= $suggested_password ?>";
            confirmPasswordInput.value = "<?= $suggested_password ?>";
            checkPasswordStrength();
            checkPasswordMatch();
        });
        
        // Check password strength
        function checkPasswordStrength() {
            const password = newPasswordInput.value;
            let strength = 0;
            let feedback = '';
            
            // Update requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[^A-Za-z0-9]/.test(password);
            
            // Update requirement indicators
            updateRequirement(reqLength, hasLength);
            updateRequirement(reqUppercase, hasUppercase);
            updateRequirement(reqLowercase, hasLowercase);
            updateRequirement(reqNumber, hasNumber);
            updateRequirement(reqSpecial, hasSpecial);
            
            // Calculate strength
            if (password.length > 0) {
                // Length contributes up to 40% of strength
                strength += Math.min(40, (password.length / 12) * 40);
                
                // Character types contribute up to 60% of strength
                if (hasUppercase) strength += 15;
                if (hasLowercase) strength += 15;
                if (hasNumber) strength += 15;
                if (hasSpecial) strength += 15;
            }
            
            // Update UI
            passwordStrength.style.width = strength + '%';
            passwordStrength.className = 'progress-bar password-strength';
            
            if (password.length === 0) {
                passwordStrength.classList.add('pwd-very-weak');
                feedback = 'No password entered';
            } else if (strength < 30) {
                passwordStrength.classList.add('pwd-very-weak');
                feedback = 'Very weak: Password is too simple';
            } else if (strength < 50) {
                passwordStrength.classList.add('pwd-weak');
                feedback = 'Weak: Add more character types';
            } else if (strength < 70) {
                passwordStrength.classList.add('pwd-medium');
                feedback = 'Medium: Getting better, but could be stronger';
            } else if (strength < 90) {
                passwordStrength.classList.add('pwd-strong');
                feedback = 'Strong: Good password!';
            } else {
                passwordStrength.classList.add('pwd-very-strong');
                feedback = 'Very strong: Excellent password!';
            }
            
            passwordFeedback.textContent = feedback;
        }
        
        // Update requirement indicators
        function updateRequirement(element, isMet) {
            if (isMet) {
                element.classList.remove('req-unmet');
                element.classList.add('req-met');
                element.querySelector('i').classList.remove('bi-circle');
                element.querySelector('i').classList.add('bi-check-circle-fill');
            } else {
                element.classList.remove('req-met');
                element.classList.add('req-unmet');
                element.querySelector('i').classList.remove('bi-check-circle-fill');
                element.querySelector('i').classList.add('bi-circle');
            }
        }
        
        // Check if passwords match
        function checkPasswordMatch() {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (confirmPassword.length === 0) {
                passwordMatchFeedback.textContent = '';
                passwordMatchFeedback.className = 'form-text';
            } else if (password === confirmPassword) {
                passwordMatchFeedback.textContent = 'Passwords match';
                passwordMatchFeedback.className = 'form-text text-success';
            } else {
                passwordMatchFeedback.textContent = 'Passwords do not match';
                passwordMatchFeedback.className = 'form-text text-danger';
            }
        }
        
        // Add event listeners
        newPasswordInput.addEventListener('input', checkPasswordStrength);
        newPasswordInput.addEventListener('input', checkPasswordMatch);
        confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        
        // Form submission validation
        resetForm.addEventListener('submit', function(e) {
            const password = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long.');
            } else if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match.');
            }
        });
        
        // Initial check on page load
        checkPasswordStrength();
    });
    </script>
</body>
</html>