<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$role = $_SESSION["role"];
$user_id = $_SESSION["user_id"];

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Prepare query
$query = "SELECT courses.*, users.name AS teacher_name 
          FROM courses 
          JOIN users ON courses.teacher_id = users.id";

// If search criteria exists, add WHERE clause
if (!empty($search)) {
    $query .= " WHERE courses.course_name LIKE :search 
               OR courses.course_code LIKE :search 
               OR users.name LIKE :search";
    $search = "%$search%";
}

// Add sorting
$query .= " ORDER BY courses.course_code ASC";

$stmt = $pdo->prepare($query);

// Bind search parameters
if (!empty($search)) {
    $stmt->bindParam(':search', $search, PDO::PARAM_STR);
}

$stmt->execute();
$courses = $stmt->fetchAll();

// Get current user's enrolled courses
$enrolled_courses = [];
if ($role === "student") {
    $stmt = $pdo->prepare("SELECT course_id FROM user_courses WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $enrolled_courses = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course List - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .course-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .badge-teacher {
            background-color: #e3f2fd;
            color: #0d6efd;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Navigation bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-mortarboard-fill me-2"></i>CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-journal-richtext me-1"></i>Course List
                        </a>
                    </li>
                    <?php if ($role === "student") : ?>
                    <li class="nav-item">
                        <a class="nav-link" href="course_enroll.php">
                            <i class="bi bi-journal-plus me-1"></i>Enroll
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["name"] ?? "User") ?>
                        <span class="badge bg-light text-primary ms-1"><?= ucfirst($role) ?></span>
                    </span>
                    <a href="/campusconnect/modules/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-journal-richtext me-2"></i>Course List</h2>
            <div>
                <?php if ($role === "admin" || $role === "teacher") : ?>
                    <a href="add_course.php" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i>Add Course
                    </a>
                <?php endif; ?>
                <a href="../dashboard.php" class="btn btn-secondary ms-2">
                    <i class="bi bi-house-door me-1"></i>Return to Dashboard
                </a>
            </div>
        </div>

        <!-- Search bar -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search by course code, course name, or teacher..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($courses)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?= empty($search) ? 'No courses are currently available.' : 'No matching courses found.' ?>
            </div>
        <?php else: ?>
            <!-- Course list - Table view -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Course List</h5>
                    <span class="badge bg-primary"><?= count($courses) ?> Courses</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3">Course Code</th>
                                    <th scope="col">Course Name</th>
                                    <th scope="col">Instructor</th>
                                    <th scope="col" style="width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td class="ps-3 fw-bold"><?= htmlspecialchars($course["course_code"]) ?></td>
                                    <td><?= htmlspecialchars($course["course_name"]) ?></td>
                                    <td>
                                        <span class="badge badge-teacher">
                                            <i class="bi bi-person-badge me-1"></i>
                                            <?= htmlspecialchars($course["teacher_name"] ?? "Not Assigned") ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="view_course.php?id=<?= $course["id"] ?>" class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        
                                        <?php if ($role === "student" && !in_array($course["id"], $enrolled_courses)): ?>
                                            <a href="enroll_single.php?id=<?= $course["id"] ?>" class="btn btn-outline-success btn-sm">
                                                <i class="bi bi-plus-circle me-1"></i>Enroll
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($role === "admin" || ($role === "teacher" && $_SESSION["user_id"] == $course["teacher_id"])): ?>
                                            <a href="edit_course.php?id=<?= $course["id"] ?>" class="btn btn-outline-warning btn-sm">
                                                <i class="bi bi-pencil me-1"></i>Edit
                                            </a>
                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#deleteModal<?= $course["id"] ?>">
                                                <i class="bi bi-trash me-1"></i>Delete
                                            </button>
                                            
                                            <!-- Delete confirmation modal -->
                                            <div class="modal fade" id="deleteModal<?= $course["id"] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirm Deletion</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <p>Are you sure you want to delete the following course?</p>
                                                            <div class="alert alert-warning">
                                                                <strong><?= htmlspecialchars($course["course_code"]) ?></strong>: 
                                                                <?= htmlspecialchars($course["course_name"]) ?>
                                                            </div>
                                                            <p class="text-danger mb-0">
                                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                                This action cannot be undone, and all related student enrollment records will also be deleted.
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <a href="delete_course.php?id=<?= $course["id"] ?>" class="btn btn-danger">
                                                                Confirm Delete
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Course list - Card view -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Course Card View</h5>
                </div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php foreach ($courses as $course): ?>
                        <div class="col">
                            <div class="card h-100 course-card">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><?= htmlspecialchars($course["course_code"]) ?></span>
                                    <?php if ($role === "student"): ?>
                                        <?php if (in_array($course["id"], $enrolled_courses)): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-circle me-1"></i>Enrolled
                                            </span>
                                        <?php else: ?>
                                            <a href="enroll_single.php?id=<?= $course["id"] ?>" class="badge bg-primary text-decoration-none">
                                                <i class="bi bi-plus-circle me-1"></i>Enroll
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($course["course_name"]) ?></h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="bi bi-person-badge me-1"></i>Instructor: <?= htmlspecialchars($course["teacher_name"] ?? "Not Assigned") ?>
                                        </small>
                                    </p>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <div class="d-flex justify-content-between">
                                        <a href="view_course.php?id=<?= $course["id"] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i>View Details
                                        </a>
                                        <?php if ($role === "admin" || ($role === "teacher" && $_SESSION["user_id"] == $course["teacher_id"])): ?>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit_course.php?id=<?= $course["id"] ?>" class="btn btn-outline-warning">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteCardModal<?= $course["id"] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            
                                            <!-- Card view delete confirmation modal -->
                                            <div class="modal fade" id="deleteCardModal<?= $course["id"] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirm Deletion</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete the following course?</p>
                                                            <div class="alert alert-warning">
                                                                <strong><?= htmlspecialchars($course["course_code"]) ?></strong>: 
                                                                <?= htmlspecialchars($course["course_name"]) ?>
                                                            </div>
                                                            <p class="text-danger mb-0">
                                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                                This action cannot be undone.
                                                            </p>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <a href="delete_course.php?id=<?= $course["id"] ?>" class="btn btn-danger">
                                                                Confirm Delete
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>