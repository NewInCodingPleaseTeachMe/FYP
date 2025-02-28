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

// Handle search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Build the query
$query = "SELECT id, name, email, role, student_id, created_at FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR student_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

// Sorting
$query .= " ORDER BY id DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Count users by role
$role_counts = [];
$role_stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $role_stmt->fetch()) {
    $role_counts[$row['role']] = $row['count'];
}
$total_users = array_sum($role_counts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management - CampusConnect</title>
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
        .user-role {
            font-size: 0.875rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            display: inline-block;
        }
        .role-admin {
            background-color: #dc3545;
            color: white;
        }
        .role-teacher {
            background-color: #198754;
            color: white;
        }
        .role-student {
            background-color: #0d6efd;
            color: white;
        }
        .table th {
            font-weight: 600;
            color: #495057;
        }
        .search-container {
            position: relative;
        }
        .search-container .bi-search {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .search-container input {
            padding-left: 2.5rem;
        }
        .stats-card {
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .delete-btn {
            transition: all 0.2s;
        }
        .delete-btn:hover {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Top Navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-people-fill text-primary me-2"></i>User Management
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">User Management</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="bi bi-person-plus-fill me-2"></i>Add User
                </a>
                <a href="../dashboard.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-house-door-fill me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-white mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Total Users</h6>
                                <h3 class="card-title mb-0"><?= $total_users ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-people-fill text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-white mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Students</h6>
                                <h3 class="card-title mb-0"><?= $role_counts['student'] ?? 0 ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-mortarboard-fill text-primary fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-white mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Teachers</h6>
                                <h3 class="card-title mb-0"><?= $role_counts['teacher'] ?? 0 ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-person-workspace text-success fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-white mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Admins</h6>
                                <h3 class="card-title mb-0"><?= $role_counts['admin'] ?? 0 ?></h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                <i class="bi bi-shield-lock-fill text-danger fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-6">
                        <div class="search-container">
                            <i class="bi bi-search"></i>
                            <input type="text" name="search" class="form-control" placeholder="Search by name, email, or student ID..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="teacher" <?= $role_filter === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                            <option value="student" <?= $role_filter === 'student' ? 'selected' : '' ?>>Student</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- User List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" width="5%">#</th>
                                <th scope="col" width="20%">User Info</th>
                                <th scope="col" width="20%">Contact</th>
                                <th scope="col" width="15%">Role</th>
                                <th scope="col" width="15%">Created At</th>
                                <th scope="col" width="25%">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'success' : 'primary') ?> bg-opacity-10 p-2 rounded-circle me-3">
                                                    <i class="bi bi-person-fill text-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'teacher' ? 'success' : 'primary') ?>"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?= htmlspecialchars($user['name']) ?></h6>
                                                    <?php if ($user['student_id']): ?>
                                                        <small class="text-muted">Student ID: <?= htmlspecialchars($user['student_id']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <i class="bi bi-envelope me-1 text-muted"></i>
                                                <?= htmlspecialchars($user['email']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="user-role role-<?= $user['role'] ?>">
                                                <?php
                                                $role_icon = $user['role'] === 'admin' ? 'shield-lock-fill' : ($user['role'] === 'teacher' ? 'person-workspace' : 'mortarboard-fill');
                                                $role_text = $user['role'] === 'admin' ? 'Admin' : ($user['role'] === 'teacher' ? 'Teacher' : 'Student');
                                                ?>
                                                <i class="bi bi-<?= $role_icon ?> me-1"></i>
                                                <?= $role_text ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['created_at']): ?>
                                                <i class="bi bi-calendar me-1 text-muted"></i>
                                                <?= date('Y-m-d', strtotime($user['created_at'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit User">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($_SESSION['user_id'] != $user['id']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger delete-btn" 
                                                            data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                            data-user-id="<?= $user['id'] ?>" 
                                                            data-user-name="<?= htmlspecialchars($user['name']) ?>"
                                                            title="Delete User">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Cannot delete current user">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="py-5">
                                            <i class="bi bi-search fs-1 text-muted"></i>
                                            <p class="mt-3 mb-0">No users found matching your criteria</p>
                                            <?php if (!empty($search) || !empty($role_filter)): ?>
                                                <a href="manage_users.php" class="btn btn-outline-primary mt-3">
                                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Reset Filters
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (count($users) > 0): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" tabindex="-1">Previous</a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#">Next</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user <strong id="userName"></strong>? This action cannot be undone.</p>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        Deleting a user will also remove all related data, including courses, assignments, and submissions.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Confirm Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Set up delete modal parameters
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const userName = button.getAttribute('data-user-name');
                
                document.getElementById('userName').textContent = userName;
                document.getElementById('confirmDelete').href = '../modules/delete_user.php?id=' + userId;
            });
        }

        // Table row hover effect
        document.querySelectorAll('tbody tr').forEach(tr => {
            tr.addEventListener('mouseover', () => {
                tr.classList.add('bg-light');
            });
            tr.addEventListener('mouseleave', () => {
                tr.classList.remove('bg-light');
            });
        });
    </script>
</body>
</html>