<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Students only
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    die("Access denied!");
}

$user_id = $_SESSION["user_id"];
$notification = "";
$notificationType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_id = $_POST["course_id"];

    // Check if student is already enrolled in the course
    $stmt = $pdo->prepare("SELECT * FROM user_courses WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    $already_enrolled = $stmt->fetch();

    if ($already_enrolled) {
        $notification = "You are already enrolled in this course!";
        $notificationType = "danger";
    } else {
        // If not enrolled, add to course
        $stmt = $pdo->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (?, ?)");
        if ($stmt->execute([$user_id, $course_id])) {
            $notification = "Course enrollment successful!";
            $notificationType = "success";
        } else {
            $notification = "Enrollment failed, please try again!";
            $notificationType = "danger";
        }
    }
}

// Get all courses
$courses = $pdo->query("SELECT * FROM courses")->fetchAll();

// Get list of course IDs the student is already enrolled in
$stmt = $pdo->prepare("SELECT course_id FROM user_courses WHERE user_id = ?");
$stmt->execute([$user_id]);
$enrolledCourses = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Course - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-journal-plus me-1"></i>Join Course
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../modules/logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">
                            <i class="bi bi-journal-plus me-2"></i>Join Course
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($notification)): ?>
                            <div class="alert alert-<?php echo $notificationType; ?> alert-dismissible fade show" role="alert">
                                <i class="bi bi-<?php echo $notificationType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>-fill me-2"></i>
                                <?php echo $notification; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="course_select" class="form-label fw-bold">Select Course</label>
                                <select name="course_id" id="course_select" class="form-select form-select-lg" required>
                                    <option value="" selected disabled>-- Please select a course to join --</option>
                                    <?php foreach ($courses as $course): 
                                        $isEnrolled = in_array($course['id'], $enrolledCourses);
                                    ?>
                                        <option value="<?php echo $course['id']; ?>" <?php echo $isEnrolled ? 'disabled' : ''; ?>>
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                            <?php echo $isEnrolled ? '(Enrolled)' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a course</div>
                                <div class="form-text text-muted mt-2">
                                    <i class="bi bi-info-circle me-1"></i>Courses you are already enrolled in are disabled and cannot be selected again
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-plus-circle me-2"></i>Join Course
                                </button>
                                <a href="../dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Return to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($enrolledCourses)): ?>
                <div class="card mt-4 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="bi bi-journal-check me-2"></i>Enrolled Courses
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php 
                            $stmt = $pdo->prepare("
                                SELECT c.* FROM courses c 
                                JOIN user_courses uc ON c.id = uc.course_id 
                                WHERE uc.user_id = ?
                            ");
                            $stmt->execute([$user_id]);
                            $myCourses = $stmt->fetchAll();
                            
                            foreach ($myCourses as $course):
                            ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($course['course_name']); ?></h6>
                                        <?php if(isset($course['course_code'])): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($course['course_code']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <a href="view_course.php?id=<?php echo $course['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-right"></i> View
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>