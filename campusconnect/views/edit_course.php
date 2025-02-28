<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit("Please login first!");
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// Get course ID
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    header("Location: course_list.php");
    exit("Invalid course ID!");
}

$course_id = $_GET["id"];

// Query course details (including teacher information)
$stmt = $pdo->prepare("SELECT c.*, u.name as teacher_name 
                       FROM courses c
                       JOIN users u ON c.teacher_id = u.id
                       WHERE c.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: course_list.php");
    exit("Course does not exist!");
}

// Only administrators or course teachers can edit
if ($role !== "admin" && $user_id != $course["teacher_id"]) {
    header("Location: view_course.php?id=$course_id");
    exit("You do not have permission to edit this course!");
}

// Get all available teachers (only admins can change teachers)
$teachers = [];
if ($role === "admin") {
    $teachers_stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name");
    $teachers = $teachers_stmt->fetchAll();
}

$notification = "";
$notificationType = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $course_name = trim($_POST["course_name"]);
        $course_code = trim($_POST["course_code"]);
        $description = trim($_POST["description"]);
        $credit_hours = (float)$_POST["credit_hours"]; // Changed from credits to credit_hours
        $status = $_POST["status"];
        
        // Only admins can change teachers
        $teacher_id = $course["teacher_id"];
        if ($role === "admin" && isset($_POST["teacher_id"])) {
            $teacher_id = $_POST["teacher_id"];
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update course information
        $stmt = $pdo->prepare("UPDATE courses SET 
                              course_name = ?, 
                              course_code = ?,
                              description = ?, 
                              credit_hours = ?,
                              status = ?,
                              teacher_id = ?,
                              updated_at = NOW()
                              WHERE id = ?");
                              
        $stmt->execute([
            $course_name, 
            $course_code,
            $description, 
            $credit_hours, // Changed from credits to credit_hours
            $status,
            $teacher_id,
            $course_id
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        // Record activity log
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at) 
                                  VALUES (?, 'update', 'course', ?, ?, NOW())");
        $log_details = json_encode(['course_name' => $course_name]);
        $log_stmt->execute([$user_id, $course_id, $log_details]);
        
        $notification = "Course updated successfully!";
        $notificationType = "success";
        
        // Update local variables to reflect changes
        $course["course_name"] = $course_name;
        $course["course_code"] = $course_code;
        $course["description"] = $description;
        $course["credit_hours"] = $credit_hours; // Changed from credits to credit_hours
        $course["status"] = $status;
        $course["teacher_id"] = $teacher_id;
        
        // If teacher changed, get new teacher name
        if ($teacher_id != $course["teacher_id"]) {
            $teacher_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
            $teacher_stmt->execute([$teacher_id]);
            $teacher_data = $teacher_stmt->fetch();
            if ($teacher_data) {
                $course["teacher_name"] = $teacher_data["name"];
            }
        }
        
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        $notification = "Update failed: " . $e->getMessage();
        $notificationType = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - <?= htmlspecialchars($course['course_name']) ?> - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .form-section {
            border-left: 4px solid #0d6efd;
            padding-left: 1rem;
            margin-bottom: 2rem;
        }
        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            border: none;
        }
        .btn-floating {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1030;
        }
        .character-counter {
            font-size: 0.8rem;
            color: #6c757d;
            text-align: right;
        }
    </style>
</head>
<body class="bg-light">
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
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-journal-richtext me-1"></i>Course List
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_course.php?id=<?= $course_id ?>">
                            <i class="bi bi-book me-1"></i>Course Details
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["name"] ?? "User") ?> 
                        <span class="badge bg-light text-primary ms-1"><?= ucfirst($role) ?></span>
                    </span>
                    <a href="../modules/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Breadcrumb navigation -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="course_list.php">Course List</a></li>
                <li class="breadcrumb-item"><a href="view_course.php?id=<?= $course_id ?>">Course Details</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Course</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-pencil-square me-2"></i>
                    Edit Course
                </h2>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="view_course.php?id=<?= $course_id ?>" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i>Return to Course Details
                </a>
            </div>
        </div>
        
        <?php if (!empty($notification)): ?>
            <div class="alert alert-<?= $notificationType ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $notificationType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
                <?= $notification ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-white">
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2"><?= htmlspecialchars($course['course_code']) ?></span>
                    <h5 class="mb-0"><?= htmlspecialchars($course['course_name']) ?></h5>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="editCourseForm">
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Basic Information -->
                            <div class="form-section">
                                <h5 class="mb-3">Basic Information</h5>
                                
                                <div class="mb-3">
                                    <label for="course_name" class="form-label">Course Name <span class="text-danger">*</span></label>
                                    <input type="text" id="course_name" name="course_name" class="form-control" 
                                           value="<?= htmlspecialchars($course['course_name']) ?>" 
                                           maxlength="100" required>
                                    <div class="character-counter">
                                        <span id="course_name_counter">0</span>/100
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="course_code" class="form-label">Course Code <span class="text-danger">*</span></label>
                                    <input type="text" id="course_code" name="course_code" class="form-control" 
                                           value="<?= htmlspecialchars($course['course_code']) ?>" 
                                           required>
                                    <div class="form-text">Please enter a unique course code (e.g., CS101, MATH201, etc.)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Course Description <span class="text-danger">*</span></label>
                                    <textarea id="description" name="description" class="form-control" 
                                              rows="5" maxlength="1000" required><?= htmlspecialchars($course['description']) ?></textarea>
                                    <div class="character-counter">
                                        <span id="description_counter">0</span>/1000
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Detailed Settings -->
                            <div class="form-section">
                                <h5 class="mb-3">Detailed Settings</h5>
                                
                                <div class="mb-3">
                                    <label for="credit_hours" class="form-label">Credit Hours</label>
                                    <select id="credit_hours" name="credit_hours" class="form-select">
                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                            <option value="<?= $i ?>" <?= $course['credit_hours'] == $i ? 'selected' : '' ?>>
                                                <?= $i ?> Credits
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Course Status</label>
                                    <select id="status" name="status" class="form-select">
                                        <option value="active" <?= $course['status'] == 'active' ? 'selected' : '' ?>>
                                            Active - Students can enroll and access
                                        </option>
                                        <option value="inactive" <?= $course['status'] == 'inactive' ? 'selected' : '' ?>>
                                            Inactive - Temporarily hidden, enrollment closed
                                        </option>
                                        <option value="archived" <?= $course['status'] == 'archived' ? 'selected' : '' ?>>
                                            Archived - Course completed, for reference only
                                        </option>
                                    </select>
                                </div>
                                
                                <?php if ($role === "admin"): ?>
                                <div class="mb-3">
                                    <label for="teacher_id" class="form-label">Course Instructor</label>
                                    <select id="teacher_id" name="teacher_id" class="form-select">
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?= $teacher['id'] ?>" <?= $course['teacher_id'] == $teacher['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($teacher['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <!-- Course Information Card -->
                            <div class="card mb-4">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Current Course Information</h6>
                                </div>
                                <div class="card-body">
                                    <p><strong>Created On:</strong><br>
                                       <?= date('Y-m-d H:i', strtotime($course['created_at'])) ?>
                                    </p>
                                    <p><strong>Last Updated:</strong><br>
                                       <?= date('Y-m-d H:i', strtotime($course['updated_at'] ?? $course['created_at'])) ?>
                                    </p>
                                    <p><strong>Instructor:</strong><br>
                                       <?= htmlspecialchars($course['teacher_name']) ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong>Student Count:</strong><br>
                                        <?php
                                        $students_stmt = $pdo->prepare("SELECT COUNT(*) FROM user_courses WHERE course_id = ?");
                                        $students_stmt->execute([$course_id]);
                                        echo $students_stmt->fetchColumn() . " students";
                                        ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Tips -->
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="bi bi-info-circle-fill me-2"></i>Edit Course Tips
                                    </h6>
                                    <ul class="card-text mb-0 ps-3">
                                        <li>Course code should remain unique</li>
                                        <li>Changing course status controls student enrollment</li>
                                        <li>Complete course descriptions help students understand the content</li>
                                        <li>All changes take effect immediately</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="course_list.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <?php if ($role === "admin"): ?>
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Danger Zone
                </h5>
            </div>
            <div class="card-body">
                <p>The following actions may permanently delete data or seriously impact the course. Please proceed with caution.</p>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash me-1"></i>Delete Course
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirm Deletion
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the following course?</p>
                        <div class="alert alert-warning">
                            <strong><?= htmlspecialchars($course['course_code']) ?>:</strong> 
                            <?= htmlspecialchars($course['course_name']) ?>
                        </div>
                        <p class="text-danger mb-0">
                            <strong>Warning:</strong> This action cannot be undone! Deleting the course will also delete all related:
                        </p>
                        <ul class="text-danger">
                            <li>Student enrollment records</li>
                            <li>Course resources</li>
                            <li>Assignments and submissions</li>
                            <li>Discussion forum posts and comments</li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <a href="delete_course.php?id=<?= $course_id ?>&confirm=true" class="btn btn-danger">
                            Confirm Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Floating Save Button -->
    <div class="btn-floating d-md-none">
        <button type="button" class="btn btn-primary btn-lg rounded-circle shadow" onclick="document.getElementById('editCourseForm').submit();">
            <i class="bi bi-save"></i>
        </button>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Character Counter Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const courseNameInput = document.getElementById('course_name');
            const courseNameCounter = document.getElementById('course_name_counter');
            const descriptionTextarea = document.getElementById('description');
            const descriptionCounter = document.getElementById('description_counter');
            
            // Initial count
            courseNameCounter.textContent = courseNameInput.value.length;
            descriptionCounter.textContent = descriptionTextarea.value.length;
            
            // Real-time counter update
            courseNameInput.addEventListener('input', function() {
                courseNameCounter.textContent = this.value.length;
            });
            
            descriptionTextarea.addEventListener('input', function() {
                descriptionCounter.textContent = this.value.length;
            });
        });
    </script>
</body>
</html>