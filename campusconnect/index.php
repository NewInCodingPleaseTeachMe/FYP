<?php
session_set_cookie_params(0, '/');
session_start();

// **If the user is already logged in, redirect to the Dashboard**
if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to CampusConnect</title>
    <!-- Using Bootstrap 5 CDN (You can also use your local files) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Optional: Add Font Awesome icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .welcome-card {
            max-width: 500px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .logo {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .btn {
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 500;
        }
        .btn-register {
            position: relative;
        }
        .dropdown-menu {
            width: 100%;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .dropdown-item {
            padding: 10px 15px;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="welcome-card bg-white p-5 text-center">
            <div class="logo">🎓</div>
            <h1 class="mb-4">Welcome to CampusConnect</h1>
            <p class="lead text-muted mb-4">Connect with your campus, share resources, and enhance your learning experience.</p>
            
            <div class="d-grid gap-3 col-lg-8 mx-auto">
                <a href="views/login.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
                
                <div class="dropdown">
                    <button class="btn btn-outline-success btn-lg dropdown-toggle w-100" type="button" id="registerDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-plus me-2"></i>Register
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="registerDropdown">
                        <li>
                            <a class="dropdown-item" href="views/register.php">
                                <i class="fas fa-user-graduate me-2"></i>Student Registration
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="views/teacher_register.php">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Teacher Registration
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-5 text-muted small">
                <p>© 2025 CampusConnect. All rights reserved.</p>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JavaScript Bundle with Popper (Required for dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>