<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// **🔐 Restrict access for non-logged in users**
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php"); // Redirect to login page
    exit;
}

// **🔐 Restrict access for non-admin users**
if ($_SESSION["role"] !== "admin") {
    die("Access denied! Please log in with an administrator account.");
}

$admin_name = $_SESSION["user_name"] ?? "Administrator";

// Get faculty list for association
try {
    $stmt = $pdo->prepare("SELECT id, faculty_name FROM faculties ORDER BY faculty_name");
    $stmt->execute();
    $faculties = $stmt->fetchAll();
} catch (PDOException $e) {
    $faculties = [];
}

// Handle message notifications
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear messages from session
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - CampusConnect</title>
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
            max-width: 1100px;
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
            border-color: var(--admin-primary);
            box-shadow: 0 0 0 0.25rem rgba(102, 16, 242, 0.25);
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
        
        .btn-admin {
            background-color: var(--admin-primary);
            border-color: var(--admin-primary);
            color: white;
        }
        
        .btn-admin:hover {
            background-color: var(--admin-dark);
            border-color: var(--admin-dark);
            color: white;
        }
        
        .btn-outline-admin {
            color: var(--admin-primary);
            border-color: var(--admin-primary);
        }
        
        .btn-outline-admin:hover {
            background-color: var(--admin-primary);
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
        
        /* Password strength indicator */
        .password-strength {
            height: 6px;
            border-radius: 3px;
            margin-top: 8px;
            transition: all 0.3s ease;
        }
        
        .password-strength-weak {
            background-color: var(--danger-color);
            width: 33%;
        }
        
        .password-strength-medium {
            background-color: var(--warning-color);
            width: 66%;
        }
        
        .password-strength-strong {
            background-color: var(--success-color);
            width: 100%;
        }
        
        /* Role cards */
        .role-card {
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 1.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .role-card.active {
            border-color: var(--admin-primary);
            background-color: var(--admin-light);
        }
        
        .role-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--admin-primary);
        }
        
        .role-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .role-description {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        /* Preview card */
        .user-preview {
            background-color: var(--admin-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--admin-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            margin-right: 1rem;
        }
        
        /* Navbar styles */
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .navbar-admin {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-dark));
        }
        
        /* Animation effects */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .role-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation bar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-admin">
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
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-people me-1"></i> User Management
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="#"><i class="bi bi-person-plus me-1"></i> Add User</a></li>
                            <li><a class="dropdown-item" href="user_list.php"><i class="bi bi-list-ul me-1"></i> User List</a></li>
                            <li><a class="dropdown-item" href="user_roles.php"><i class="bi bi-shield-lock me-1"></i> Role Management</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-book me-1"></i> Course Management
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($admin_name) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../views/profile.php"><i class="bi bi-person me-1"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../modules/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="admin-container">
        <!-- Page title -->
        <div class="page-header">
            <h2><i class="bi bi-person-plus me-2"></i> Add User</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="user_list.php">User Management</a></li>
                    <li class="breadcrumb-item active">Add User</li>
                </ol>
            </nav>
        </div>

        <!-- Message notifications -->
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

        <!-- Main card -->
        <div class="card mb-4 fade-in">
            <div class="card-header">
                <h5><i class="bi bi-person-add me-2"></i> User Information</h5>
                <p class="mb-0 mt-2 small">Add a new user to the system</p>
            </div>
            
            <div class="card-body">
                <form id="userForm" action="../modules/add_user.php" method="POST">
                    
                    <!-- Role selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select User Role</label>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="role-card" data-role="admin">
                                    <div class="role-icon"><i class="bi bi-shield-lock"></i></div>
                                    <div class="role-title">Administrator</div>
                                    <div class="role-description">Has complete control over the system, can manage all users and courses</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="role-card" data-role="teacher">
                                    <div class="role-icon"><i class="bi bi-mortarboard"></i></div>
                                    <div class="role-title">Teacher</div>
                                    <div class="role-description">Can create and manage courses, publish assignments and teaching resources</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="role-card active" data-role="student">
                                    <div class="role-icon"><i class="bi bi-person-badge"></i></div>
                                    <div class="role-title">Student</div>
                                    <div class="role-description">Can join courses, view and submit assignments, participate in discussions</div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="role" id="role" value="student">
                    </div>
                    <div class="row">
                        <!-- Basic Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Basic Information</h5>
                            
                            <!-- Name -->
                            <div class="mb-3 form-group required">
                                <label for="name" class="form-label">
                                    <i class="bi bi-person me-1"></i> Name
                                </label>
                                <input type="text" name="name" id="name" class="form-control" 
                                       required maxlength="50" placeholder="Enter user's name">
                                <div class="form-text">Enter the user's real name</div>
                            </div>

                            <!-- Email -->
                            <div class="mb-3 form-group required">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope me-1"></i> Email
                                </label>
                                <input type="email" name="email" id="email" class="form-control" 
                                       required placeholder="example@school.edu.cn">
                                <div class="form-text">Email will be used as the login account, ensure it is unique</div>
                            </div>

                            <!-- Password -->
                            <div class="mb-3 form-group required">
                                <label for="password" class="form-label">
                                    <i class="bi bi-key me-1"></i> Password
                                </label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control" 
                                           required minlength="8" placeholder="Set a secure password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="password-strength"></div>
                                <div class="form-text">Password must be at least 8 characters and include letters, numbers, and special characters</div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3 form-group required">
                                <label for="confirm_password" class="form-label">
                                    <i class="bi bi-check-circle me-1"></i> Confirm Password
                                </label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                                       required placeholder="Re-enter password">
                                <div id="password-match" class="form-text">Passwords must match</div>
                            </div>
                        </div>
                        
                        <!-- Additional Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Additional Information</h5>
                            
                            <!-- Student/Employee ID -->
                            <div class="mb-3">
                                <label for="id_number" class="form-label">
                                    <i class="bi bi-credit-card me-1"></i> <span id="id-label">Student ID</span>
                                </label>
                                <input type="text" name="id_number" id="id_number" class="form-control" 
                                       placeholder="Enter student ID">
                                <div class="form-text" id="id-hint">Student ID for identification</div>
                            </div>

                            <!-- Faculty/Department -->
                            <div class="mb-3">
                                <label for="faculty_id" class="form-label">
                                    <i class="bi bi-building me-1"></i> Faculty
                                </label>
                                <select name="faculty_id" id="faculty_id" class="form-select">
                                    <option value="">-- Select Faculty --</option>
                                    <?php foreach ($faculties as $faculty): ?>
                                        <option value="<?= $faculty['id'] ?>">
                                            <?= htmlspecialchars($faculty['faculty_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the user's faculty or department</div>
                            </div>

                            <!-- Phone Number -->
                            <div class="mb-3">
                                <label for="phone" class="form-label">
                                    <i class="bi bi-telephone me-1"></i> Phone Number
                                </label>
                                <input type="tel" name="phone" id="phone" class="form-control" 
                                    placeholder="Enter Malaysian phone number (e.g. 012-345 6789)">
                                <div class="form-text">Used for contacting and notifying the user</div>
                            </div>

                            <!-- Account Status -->
                            <div class="mb-3">
                                <label class="form-label d-block">
                                    <i class="bi bi-toggle-on me-1"></i> Account Status
                                </label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="status_active" value="active" checked>
                                    <label class="form-check-label" for="status_active">Active</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="status" id="status_inactive" value="inactive">
                                    <label class="form-check-label" for="status_inactive">Pending Activation</label>
                                </div>
                                <div class="form-text">Set the initial status of the user account</div>
                            </div>

                            <!-- User Preview -->
                            <div class="user-preview">
                                <h6 class="mb-3"><i class="bi bi-eye me-1"></i> User Preview</h6>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="user-avatar" id="preview-avatar">S</div>
                                    <div>
                                        <div class="fw-bold mb-1" id="preview-name">User Name</div>
                                        <div class="small text-muted" id="preview-email">user@example.com</div>
                                        <div class="mt-1">
                                            <span class="badge bg-primary" id="preview-role">Student</span>
                                            <span class="badge bg-light text-dark" id="preview-faculty">No Faculty Selected</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Bottom Buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="user_list.php" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i> Return to User List
                            </a>
                        </div>
                        <div>
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                            </button>
                            <button type="submit" id="submitBtn" class="btn btn-admin">
                                <i class="bi bi-person-plus me-1"></i> Create User
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tips Card -->
        <div class="card bg-light fade-in">
            <div class="card-body">
                <h5><i class="bi bi-lightbulb me-2 text-warning"></i> User Management Tips</h5>
                <ul class="mb-0">
                    <li><strong>Secure passwords:</strong> Use strong passwords and change them regularly to ensure system security</li>
                    <li><strong>User roles:</strong> Assign appropriate roles based on actual needs, avoid excessive permissions</li>
                    <li><strong>Bulk import:</strong> Use Excel bulk import function when adding multiple users</li>
                    <li><strong>Account activation:</strong> Users set to "Pending Activation" status need to complete email verification before logging in</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Role card selection
            $('.role-card').click(function() {
                $('.role-card').removeClass('active');
                $(this).addClass('active');
                
                const role = $(this).data('role');
                $('#role').val(role);
                
                // Update UI
                updateRoleSpecificElements(role);
                updatePreview();
            });
            
            function updateRoleSpecificElements(role) {
                if (role === 'student') {
                    $('#id-label').text('Student ID');
                    $('#id_number').attr('placeholder', 'Enter student ID');
                    $('#id-hint').text('Student ID for identification');
                } else if (role === 'teacher') {
                    $('#id-label').text('Employee ID');
                    $('#id_number').attr('placeholder', 'Enter teacher employee ID');
                    $('#id-hint').text('Teacher employee ID for identification');
                } else {
                    $('#id-label').text('Staff ID');
                    $('#id_number').attr('placeholder', 'Enter staff ID');
                    $('#id-hint').text('Administrator ID for identification');
                }
                
                // Update preview role
                const roleText = role === 'admin' ? 'Administrator' : (role === 'teacher' ? 'Teacher' : 'Student');
                $('#preview-role').text(roleText);
                
                // Update role badge color
                const roleClass = role === 'admin' ? 'bg-danger' : (role === 'teacher' ? 'bg-success' : 'bg-primary');
                $('#preview-role').removeClass('bg-primary bg-success bg-danger').addClass(roleClass);
            }
            
            // Toggle password visibility
            $('.toggle-password').click(function() {
                const passwordInput = $('#password');
                const icon = $(this).find('i');
                
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('bi-eye').addClass('bi-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('bi-eye-slash').addClass('bi-eye');
                }
            });
            
            // Password strength check
            $('#password').on('input', function() {
                const password = $(this).val();
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 1;
                
                // Complexity check
                if (/[A-Z]/.test(password)) strength += 1;
                if (/[a-z]/.test(password)) strength += 1;
                if (/[0-9]/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                const $strengthBar = $('.password-strength');
                
                // Update strength bar
                if (password.length === 0) {
                    $strengthBar.css('width', '0').removeClass('password-strength-weak password-strength-medium password-strength-strong');
                } else if (strength <= 2) {
                    $strengthBar.removeClass('password-strength-medium password-strength-strong').addClass('password-strength-weak');
                } else if (strength <= 4) {
                    $strengthBar.removeClass('password-strength-weak password-strength-strong').addClass('password-strength-medium');
                } else {
                    $strengthBar.removeClass('password-strength-weak password-strength-medium').addClass('password-strength-strong');
                }
                
                // Check password match
                checkPasswordMatch();
            });
            
            // Confirm password match check
            $('#confirm_password').on('input', function() {
                checkPasswordMatch();
            });
            
            function checkPasswordMatch() {
                const password = $('#password').val();
                const confirmPassword = $('#confirm_password').val();
                const $matchIndicator = $('#password-match');
                
                if (!confirmPassword) {
                    $matchIndicator.removeClass('text-success text-danger').addClass('text-muted').text('Passwords must match');
                } else if (password === confirmPassword) {
                    $matchIndicator.removeClass('text-muted text-danger').addClass('text-success').text('Passwords match ✓');
                } else {
                    $matchIndicator.removeClass('text-muted text-success').addClass('text-danger').text('Passwords do not match ✗');
                }
            }
            
            // Update preview when name and email change
            $('#name, #email, #faculty_id').on('input change', function() {
                updatePreview();
            });
            
            // Update user preview
            function updatePreview() {
                const name = $('#name').val() || 'User Name';
                const email = $('#email').val() || 'user@example.com';
                const faculty = $('#faculty_id option:selected').text();
                const facultyText = faculty && faculty !== '-- Select Faculty --' ? faculty : 'No Faculty Selected';
                
                $('#preview-name').text(name);
                $('#preview-email').text(email);
                $('#preview-faculty').text(facultyText);
                
                // Update avatar initial
                const initial = name.charAt(0).toUpperCase() || 'U';
                $('#preview-avatar').text(initial);
            }
            
            // Form validation
            $('#userForm').on('submit', function(e) {
                // Name validation
                const name = $('#name').val().trim();
                if (!name) {
                    e.preventDefault();
                    alert('Please enter user name!');
                    $('#name').focus();
                    return false;
                }
                
                // Email validation
                const email = $('#email').val().trim();
                if (!email) {
                    e.preventDefault();
                    alert('Please enter email address!');
                    $('#email').focus();
                    return false;
                }
                
                // Password validation
                const password = $('#password').val();
                const confirmPassword = $('#confirm_password').val();
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters!');
                    $('#password').focus();
                    return false;
                }
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    $('#confirm_password').focus();
                    return false;
                }
                
                // Phone validation
                // Malaysian phone validation
                const phone = $('#phone').val().trim();
                // Remove spaces and dashes for validation
                const cleanPhone = phone.replace(/[\s-]/g, '');
                if (phone && !/^(01[0-9]|03|04|05|06|07|08|09|082|083|084|085|086|087|088|089|09)\d{7,8}$/.test(cleanPhone)) {
                    e.preventDefault();
                    alert('Please enter a valid Malaysian phone number!');
                    $('#phone').focus();
                    return false;
                }
                
                // Prevent multiple submissions
                $('#submitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Creating...');
                
                return true;
            });
            
            // Reset button handling
            $('button[type="reset"]').click(function() {
                setTimeout(function() {
                    // Reset role selection
                    $('.role-card').removeClass('active');
                    $('.role-card[data-role="student"]').addClass('active');
                    $('#role').val('student');
                    
                    // Reset preview
                    updateRoleSpecificElements('student');
                    updatePreview();
                    
                    // Reset password strength
                    $('.password-strength').css('width', '0').removeClass('password-strength-weak password-strength-medium password-strength-strong');
                    
                    // Reset password match hint
                    $('#password-match').removeClass('text-success text-danger').addClass('text-muted').text('Passwords must match');
                }, 100);
            });
            
            // Initialize interface
            updatePreview();
            
            // Auto-close success message
            setTimeout(function() {
                $('.alert-success').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>