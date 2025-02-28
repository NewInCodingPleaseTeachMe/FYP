<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    die("Access denied!");
}

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST["id"];
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $role = $_POST["role"];

    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
    if ($stmt->execute([$name, $email, $role, $user_id])) {
        $success_message = "User information updated successfully!";
    } else {
        $error_message = "Update failed, please try again!";
    }
}

// Fetch user data if ID is provided
$user = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update User - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .update-container {
            max-width: 800px;
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
            padding: 1.5rem;
        }
        .input-group-text {
            background-color: #f8f9fa;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-primary {
            background: linear-gradient(45deg, #0d6efd, #0dcaf0);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(45deg, #0b5ed7, #0bacbe);
        }
        .role-icon {
            font-size: 1.2rem;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="container update-container py-4">
        <!-- Navigation -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="../views/user_list.php">User Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">Update User</li>
            </ol>
        </nav>

        <div class="card mb-4">
            <div class="card-header text-center">
                <h3 class="mb-0">
                    <i class="bi bi-person-gear me-2"></i>Update User
                </h3>
                <p class="mb-0">Make changes to user information</p>
            </div>
            <div class="card-body p-4">
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    <div class="mt-2">
                        <a href="../views/user_list.php" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-arrow-left me-2"></i>Return to User List
                        </a>
                        <?php if (isset($user_id)): ?>
                        <a href="../views/view_user.php?id=<?= $user_id ?>" class="btn btn-sm btn-outline-primary ms-2">
                            <i class="bi bi-eye me-2"></i>View User Profile
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if ($user): ?>
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="role" class="form-label">User Role</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-shield-fill"></i></span>
                            <select class="form-select" id="role" name="role" required>
                                <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>
                                    <i class="bi bi-mortarboard-fill"></i> Student
                                </option>
                                <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>
                                    <i class="bi bi-person-workspace"></i> Teacher
                                </option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>
                                    <i class="bi bi-shield-lock-fill"></i> Administrator
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Role Information:</strong>
                        <ul class="mb-0 mt-2">
                            <li><i class="bi bi-mortarboard-fill role-icon text-primary"></i>Student: Can enroll in courses and submit assignments</li>
                            <li><i class="bi bi-person-workspace role-icon text-success"></i>Teacher: Can create and manage courses</li>
                            <li><i class="bi bi-shield-lock-fill role-icon text-danger"></i>Admin: Has full system access</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="../views/user_list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-2"></i>Update User
                        </button>
                    </div>
                </form>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    No user ID provided or user not found.
                    <div class="mt-3">
                        <a href="../views/user_list.php" class="btn btn-outline-primary">
                            <i class="bi bi-arrow-left me-2"></i>Back to User List
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="text-center text-muted small">
            <p>© 2025 CampusConnect. All Rights Reserved.</p>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add icons to role options
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            if (roleSelect) {
                // Visual enhancement for role selection
                roleSelect.addEventListener('change', function() {
                    const role = this.value;
                    this.className = 'form-select';
                    if (role === 'admin') {
                        this.classList.add('border-danger');
                    } else if (role === 'teacher') {
                        this.classList.add('border-success');
                    } else {
                        this.classList.add('border-primary');
                    }
                });
                
                // Trigger change event to apply initial styling
                roleSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>