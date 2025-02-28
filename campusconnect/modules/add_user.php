<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Check user permissions
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    die("
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Access Denied</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-6'>
                    <div class='card shadow-sm border-0'>
                        <div class='card-body p-4 text-center'>
                            <i class='bi bi-shield-lock text-danger' style='font-size: 3rem;'></i>
                            <h2 class='mt-3'>Access Denied</h2>
                            <p class='text-muted'>Please log in with an admin account.</p>
                            <a href='../index.php' class='btn btn-primary mt-3'>Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    ");
}

// 获取管理员姓名用于导航栏显示
$admin_name = $_SESSION["user_name"] ?? "Administrator";

// Initialize variables
$status = "error";
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process form data
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_BCRYPT);
    $role = $_POST["role"];
    $id_number = isset($_POST["id_number"]) ? trim($_POST["id_number"]) : "";

    try {
        // First, check if email already exists
        $check_email = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $check_email->execute([$email]);
        if ($check_email->fetchColumn() > 0) {
            $message = "A user with this email already exists. Please use a different email address.";
        } 
        // Then check if student_id already exists
        else if (!empty($id_number)) {
            $check_id = $pdo->prepare("SELECT COUNT(*) FROM users WHERE student_id = ?");
            $check_id->execute([$id_number]);
            if ($check_id->fetchColumn() > 0) {
                $message = "A user with this ID number already exists. Please use a different ID.";
            } else {
                // If no duplicates found, proceed with insertion
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, student_id) VALUES (?, ?, ?, ?, ?)");
                
                if ($stmt->execute([$name, $email, $password, $role, $id_number])) {
                    $status = "success";
                    $message = "User {$name} has been added successfully!";
                } else {
                    $message = "Failed to add user, please check your input and try again.";
                }
            }
        } else {
            // If no ID number provided but email check passed
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $password, $role])) {
                $status = "success";
                $message = "User {$name} has been added successfully!";
            } else {
                $message = "Failed to add user, please check your input and try again.";
            }
        }
    } catch (PDOException $e) {
        // For any other database errors, log the specific error message
        $message = "Database error occurred: " . $e->getMessage();
        
        // Log detailed error information for debugging
        error_log("User creation error: " . $e->getMessage() . " - Code: " . $e->getCode());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($status == "success") ? "User Added Successfully" : "Error Adding User"; ?> - CampusConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --admin-primary: #6610f2;
            --admin-dark: #520dc2;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .result-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 15px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .result-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
        }
        
        .result-title {
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .result-message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
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
        
        .navbar-admin {
            background: linear-gradient(135deg, var(--admin-primary), var(--admin-dark));
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        /* Animations */
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-20px);}
            60% {transform: translateY(-10px);}
        }
        
        @keyframes shake {
            0%, 100% {transform: translateX(0);}
            10%, 30%, 50%, 70%, 90% {transform: translateX(-5px);}
            20%, 40%, 60%, 80% {transform: translateX(5px);}
        }
        
        .bounce {
            animation: bounce 1s;
        }
        
        .shake {
            animation: shake 0.5s;
        }
        
        /* Timer bar for auto-redirect */
        .timer-bar {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 2rem;
        }
        
        .timer-progress {
            height: 100%;
            width: 100%;
            background-color: <?php echo ($status == "success") ? "var(--success-color)" : "var(--danger-color)"; ?>;
            border-radius: 2px;
            transition: width 5s linear;
        }
        
        /* Additional action buttons */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        
        .action-buttons .btn {
            min-width: 180px;
        }
        
        @media (max-width: 576px) {
            .action-buttons .btn {
                width: 100%;
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
                            <li><a class="dropdown-item" href="../views/add_user.php"><i class="bi bi-person-plus me-1"></i> Add User</a></li>
                            <li><a class="dropdown-item" href="../views/user_list.php"><i class="bi bi-list-ul me-1"></i> User List</a></li>
                            <li><a class="dropdown-item" href="../views/user_roles.php"><i class="bi bi-shield-lock me-1"></i> Role Management</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../views/course_list.php">
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

    <div class="result-container py-5">
        <div class="card">
            <div class="card-body p-5 text-center">
                <?php if ($status == "success"): ?>
                    <i class="bi bi-check-circle-fill result-icon text-success bounce"></i>
                    <h2 class="result-title">Success!</h2>
                <?php else: ?>
                    <i class="bi bi-exclamation-circle-fill result-icon text-danger shake"></i>
                    <h2 class="result-title">Error!</h2>
                <?php endif; ?>
                
                <p class="result-message"><?php echo htmlspecialchars($message); ?></p>
                
                <?php if ($status == "success"): ?>
                <div class="action-buttons">
                    <a href="../views/add_user.php" class="btn btn-admin">
                        <i class="bi bi-person-plus me-1"></i> Add Another User
                    </a>
                    <a href="../views/user_list.php" class="btn btn-outline-primary">
                        <i class="bi bi-list-ul me-1"></i> Go to User List
                    </a>
                </div>
                <?php else: ?>
                <div class="action-buttons">
                    <a href="javascript:history.back()" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Go Back
                    </a>
                    <a href="../views/add_user.php" class="btn btn-admin">
                        <i class="bi bi-arrow-repeat me-1"></i> Try Again
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="d-flex justify-content-center">
                    <a href="../dashboard.php" class="btn btn-light mt-2">
                        <i class="bi bi-house me-1"></i> Return to Dashboard
                    </a>
                </div>
                
                <div class="timer-bar">
                    <div class="timer-progress" id="timer-progress"></div>
                </div>
                <p class="small text-muted mt-2">You will be redirected to 
                   <?php echo ($status == "success") ? "the user list" : "the add user page"; ?> 
                   in <span id="countdown">5</span> seconds...</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect countdown
        document.addEventListener('DOMContentLoaded', function() {
            let timeLeft = 5;
            const countdownElement = document.getElementById('countdown');
            const timerProgress = document.getElementById('timer-progress');
            const redirectUrl = '<?php echo ($status == "success") ? "../views/user_list.php" : "../views/add_user.php"; ?>';
            
            // Start the timer bar animation
            setTimeout(() => {
                timerProgress.style.width = '0';
            }, 100);
            
            const countdownTimer = setInterval(function() {
                timeLeft--;
                countdownElement.textContent = timeLeft;
                
                if (timeLeft <= 0) {
                    clearInterval(countdownTimer);
                    window.location.href = redirectUrl;
                }
            }, 1000);
        });
    </script>
</body>
</html>