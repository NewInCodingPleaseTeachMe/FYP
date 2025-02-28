<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// If the user is already logged in, redirect to dashboard
if (isset($_SESSION["user_id"])) {
    header("Location: ../dashboard.php");
    exit;
}

$error_message = "";
$success_message = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $employee_id = trim($_POST["employee_id"]);
    $faculty_id = isset($_POST["faculty_id"]) ? $_POST["faculty_id"] : null;
    $phone = isset($_POST["phone"]) ? trim($_POST["phone"]) : null;
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    
    $validation_errors = [];
    
    // Validate employee ID format (can customize based on your requirements)
    if (!preg_match('/^[A-Za-z0-9]{5,15}$/', $employee_id)) {
        $validation_errors[] = "Employee ID must be 5-15 alphanumeric characters.";
    }
    
    // Validate name format (allows Chinese, English, spaces, and hyphens)
    if (!preg_match('/^[\p{Han}a-zA-Z\s\-]+$/u', $name)) {
        $validation_errors[] = "Name can only contain Chinese or English characters, spaces, and hyphens.";
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Please enter a valid email address.";
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        $validation_errors[] = "Passwords do not match.";
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
    
    // If there are validation errors, display them
    if (!empty($validation_errors)) {
        $error_message = implode("<br>", $validation_errors);
    } else {
        // Check if the email is already registered
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $check_stmt->execute([$email]);
        $exists = $check_stmt->fetchColumn();

        if ($exists > 0) {
            // Email is already taken
            $error_message = "This email is already in use. Please use a different email.";
        } else {
            // Proceed with inserting the user data
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert user with "pending" status
            $stmt = $pdo->prepare("
                INSERT INTO users (student_id, name, email, password, role, status, phone) 
                VALUES (?, ?, ?, ?, 'teacher', 'pending', ?)
            ");
            
            if ($stmt->execute([$employee_id, $name, $email, $password_hash, $phone])) {
                $new_user_id = $pdo->lastInsertId();
                
                // Associate with faculty if provided
                if (!empty($faculty_id)) {
                    $faculty_stmt = $pdo->prepare("UPDATE users SET faculty_id = ? WHERE id = ?");
                    $faculty_stmt->execute([$faculty_id, $new_user_id]);
                }
                
                // Record in activity log
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                    VALUES (?, 'register', 'user', ?, 'Teacher registration pending approval')
                ");
                $log_stmt->execute([$new_user_id, $new_user_id]);
                
                // Simulate sending email notification to admin
                // In production, you would use mail() or a proper email library
                $admin_message = "A new teacher has registered and is pending approval:\n";
                $admin_message .= "Name: $name\n";
                $admin_message .= "Email: $email\n";
                $admin_message .= "Employee ID: $employee_id\n\n";
                $admin_message .= "Please login to the admin panel to review this registration.";
                
                // Save to a file instead of sending
                file_put_contents("../logs/admin_notifications.txt", date('Y-m-d H:i:s') . " - " . $admin_message . "\n\n", FILE_APPEND);
                
                // Set success message and clear form
                $success_message = "Your registration has been submitted successfully! An administrator will review your application. You will receive an email notification once your account has been approved.";
            } else {
                $error_message = "Registration failed. Please try again later!";
            }
        }
    }
}

// Get list of faculties for dropdown
try {
    $stmt = $pdo->query("SELECT id, faculty_name FROM faculties ORDER BY faculty_name");
    $faculties = $stmt->fetchAll();
} catch (PDOException $e) {
    $faculties = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Teacher Registration - CampusConnect</title>
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
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
        .btn-success {
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
        .progress-steps {
            margin: 2rem 0;
        }
        .progress-step {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }
        .progress-step.active {
            background-color: #198754;
            color: white;
        }
        .progress-line {
            flex: 1;
            height: 3px;
            background-color: #e9ecef;
            margin: 0 -5px;
        }
        .progress-line.active {
            background-color: #198754;
        }
        .step-label {
            position: absolute;
            top: 40px;
            width: 120px;
            text-align: center;
            left: -45px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="card border-0 p-4">
                    <div class="text-center mb-4">
                        <h1 class="h3 mb-3 fw-normal">
                            <i class="bi bi-mortarboard-fill text-success me-2"></i>CampusConnect
                        </h1>
                        <h2 class="h4 mb-3">Teacher Registration</h2>
                        <p class="text-muted">Register as a teacher to create and manage courses</p>
                    </div>

                    <!-- Progress steps -->
                    <div class="progress-steps d-flex align-items-center justify-content-center">
                        <div class="progress-step active">
                            1
                            <div class="step-label">Registration</div>
                        </div>
                        <div class="progress-line"></div>
                        <div class="progress-step">
                            2
                            <div class="step-label">Admin Review</div>
                        </div>
                        <div class="progress-line"></div>
                        <div class="progress-step">
                            3
                            <div class="step-label">Account Activation</div>
                        </div>
                    </div>

                    <!-- Display error message -->
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Display success message -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= $success_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        
                        <div class="text-center my-4">
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                            </a>
                            <a href="../index.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-house-fill me-2"></i>Return to Homepage
                            </a>
                        </div>
                    <?php else: ?>
                        <form action="" method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">
                                        <i class="bi bi-person-fill"></i> Full Name
                                    </label>
                                    <input type="text" name="name" class="form-control" id="name" required>
                                    <div class="validation-feedback" id="name_feedback">
                                        <div class="form-text">Only Chinese and English characters, spaces, and hyphens are allowed.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="employee_id" class="form-label">
                                        <i class="bi bi-person-badge"></i> Employee ID
                                    </label>
                                    <input type="text" name="employee_id" class="form-control" id="employee_id" required>
                                    <div class="validation-feedback" id="employee_id_feedback">
                                        <div class="form-text">Enter your official employee or teacher ID number</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        <i class="bi bi-envelope-fill"></i> Email
                                    </label>
                                    <input type="email" name="email" class="form-control" id="email" required>
                                    <div class="validation-feedback" id="email_feedback">
                                        <div class="form-text">Please enter a valid email address.</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">
                                        <i class="bi bi-telephone-fill"></i> Phone Number
                                    </label>
                                    <input type="tel" name="phone" class="form-control" id="phone">
                                    <div class="form-text">Optional contact number</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="faculty_id" class="form-label">
                                    <i class="bi bi-building"></i> Faculty/Department
                                </label>
                                <select name="faculty_id" id="faculty_id" class="form-select">
                                    <option value="">-- Select Faculty --</option>
                                    <?php foreach ($faculties as $faculty): ?>
                                        <option value="<?= $faculty['id'] ?>">
                                            <?= htmlspecialchars($faculty['faculty_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select your department or faculty</div>
                            </div>
                            
                            <div class="mb-3">
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
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">
                                    <i class="bi bi-lock-fill"></i> Confirm Password
                                </label>
                                <input type="password" name="confirm_password" class="form-control" id="confirm_password" required>
                                <div class="validation-feedback" id="confirm_password_feedback">
                                    <div class="form-text">Passwords must match</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="termsCheck" required>
                                    <label class="form-check-label" for="termsCheck">
                                        I agree to the <a href="terms_of_service.php">Terms of Service</a> and <a href="privacy_policy.php">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success" id="submitBtn">
                                    <i class="bi bi-check-circle-fill me-2"></i> Submit Registration
                                </button>
                                <a href="../index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-house-fill me-2"></i> Return to Homepage
                                </a>
                            </div>

                            <div class="mt-4 text-center">
                                <p class="mb-0 text-muted">Already have an account? <a href="login.php" class="text-decoration-none">Login</a></p>
                                <p class="mt-2 text-muted">Are you a student? <a href="register.php" class="text-decoration-none">Register here</a></p>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
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

        // Password toggle visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });

        // Employee ID validation
        const employeeIdInput = document.getElementById('employee_id');
        const employeeIdFeedback = document.getElementById('employee_id_feedback');
        
        employeeIdInput.addEventListener('input', function() {
            const value = this.value;
            const isValid = /^[A-Za-z0-9]{5,15}$/.test(value);
            
            employeeIdFeedback.innerHTML = '';
            
            if (value.length === 0) {
                employeeIdFeedback.innerHTML += '<div class="form-text">Enter your official employee or teacher ID number</div>';
            } else if (value.length < 5 || value.length > 15) {
                employeeIdFeedback.innerHTML += '<div class="invalid-feedback-item">Employee ID must be 5-15 characters</div>';
            } else if (!isValid) {
                employeeIdFeedback.innerHTML += '<div class="invalid-feedback-item">Employee ID can only contain letters and numbers</div>';
            } else {
                employeeIdFeedback.innerHTML += '<div class="valid-feedback-item"><i class="bi bi-check-circle"></i> Employee ID format is correct</div>';
            }
        });

        // Name validation
        const nameInput = document.getElementById('name');
        const nameFeedback = document.getElementById('name_feedback');
        
        nameInput.addEventListener('input', function() {
            const value = this.value;
            const isValid = /^[\u4e00-\u9fa5a-zA-Z\s\-]+$/.test(value);
            
            nameFeedback.innerHTML = '';
            
            if (value.length === 0) {
                nameFeedback.innerHTML += '<div class="form-text">Only Chinese and English characters, spaces, and hyphens are allowed.</div>';
            } else if (!isValid) {
                nameFeedback.innerHTML += '<div class="invalid-feedback-item">Name can only contain Chinese or English characters, spaces, and hyphens.</div>';
            } else {
                nameFeedback.innerHTML += '<div class="valid-feedback-item"><i class="bi bi-check-circle"></i> Name format is correct</div>';
            }
        });

        // Email validation
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

        // Password strength check
        const passwordInput = document.getElementById('password');
        const passwordStrength = document.getElementById('passwordStrength');
        const lengthCheck = document.getElementById('length_check');
        const uppercaseCheck = document.getElementById('uppercase_check');
        const lowercaseCheck = document.getElementById('lowercase_check');
        const numberCheck = document.getElementById('number_check');
        const confirmInput = document.getElementById('confirm_password');
        const confirmFeedback = document.getElementById('confirm_password_feedback');
        const submitBtn = document.getElementById('submitBtn');
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmInput.value;
            
            confirmFeedback.innerHTML = '';
            
            if (confirmPassword.length === 0) {
                confirmFeedback.innerHTML += '<div class="form-text">Passwords must match</div>';
            } else if (password !== confirmPassword) {
                confirmFeedback.innerHTML += '<div class="invalid-feedback-item"><i class="bi bi-x-circle"></i> Passwords do not match</div>';
            } else {
                confirmFeedback.innerHTML += '<div class="valid-feedback-item"><i class="bi bi-check-circle"></i> Passwords match</div>';
            }
        }
        
        passwordInput.addEventListener('input', function() {
            const value = this.value;
            const hasLength = value.length >= 8;
            const hasUppercase = /[A-Z]/.test(value);
            const hasLowercase = /[a-z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            
            // Update check status
            updateCheckStatus(lengthCheck, hasLength);
            updateCheckStatus(uppercaseCheck, hasUppercase);
            updateCheckStatus(lowercaseCheck, hasLowercase);
            updateCheckStatus(numberCheck, hasNumber);
            
            // Calculate password strength
            let strength = 0;
            if (hasLength) strength += 25;
            if (hasUppercase) strength += 25;
            if (hasLowercase) strength += 25;
            if (hasNumber) strength += 25;
            
            // Update strength indicator
            passwordStrength.style.width = strength + '%';
            
            // Set color
            if (strength < 50) {
                passwordStrength.style.backgroundColor = '#dc3545'; // Red
            } else if (strength < 75) {
                passwordStrength.style.backgroundColor = '#ffc107'; // Yellow
            } else {
                passwordStrength.style.backgroundColor = '#198754'; // Green
            }
            
            // Check confirm password match
            if (confirmInput.value.length > 0) {
                checkPasswordMatch();
            }
        });
        
        confirmInput.addEventListener('input', checkPasswordMatch);
        
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