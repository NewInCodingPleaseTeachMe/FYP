<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is a teacher
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: ../login.php");
    exit;
}

// Get form data
$resource_type = isset($_POST["resource_type"]) ? $_POST["resource_type"] : "";
$course_id = isset($_POST["course_id"]) ? $_POST["course_id"] : "";
$resource_name = isset($_POST["resource_name"]) ? $_POST["resource_name"] : "";
$resource_description = isset($_POST["resource_description"]) ? $_POST["resource_description"] : "";
$is_visible = isset($_POST["is_visible"]) ? 1 : 0;
$teacher_id = $_SESSION["user_id"];
$success = false;
$error_message = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate course ID
    if (empty($course_id)) {
        $error_message = "Please select a course!";
    } else {
        // Process based on resource type
        if ($resource_type == "file") {
            // Handle file upload
            if (isset($_FILES["file"]) && $_FILES["file"]["error"] == 0) {
                $file_name = $_FILES["file"]["name"];
                $file_tmp = $_FILES["file"]["tmp_name"];
                $file_size = $_FILES["file"]["size"];
                $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
                $upload_dir = "../uploads/";
                
                // Ensure directory exists
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_file_name = uniqid() . "_" . $file_name;
                $file_path = $upload_dir . $new_file_name;
                
                // Allowed file types
                $allowed_types = ["pdf", "doc", "docx", "ppt", "pptx", "xls", "xlsx", "jpg", "jpeg", "png", "gif", "mp3", "mp4", "zip"];
                
                // Check file type
                if (!in_array(strtolower($file_type), $allowed_types)) {
                    $error_message = "File type not allowed!";
                }
                // Check file size (max 50MB)
                else if ($file_size > 50 * 1024 * 1024) {
                    $error_message = "File size cannot exceed 50MB!";
                }
                // Move uploaded file
                else if (move_uploaded_file($file_tmp, $file_path)) {
                    // Store in database
                    try {
                        $stmt = $pdo->prepare("INSERT INTO course_resources (course_id, teacher_id, resource_name, resource_description, file_name, file_path, file_type, is_visible, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $stmt->execute([$course_id, $teacher_id, $resource_name, $resource_description, $file_name, $file_path, $file_type, $is_visible]);
                        $success = true;
                    } catch (PDOException $e) {
                        $error_message = "Database error: " . $e->getMessage();
                    }
                } else {
                    $error_message = "File upload failed!";
                }
            } else {
                $error_message = "Please select a file!";
            }
        } else if ($resource_type == "link") {
            // Handle link resource
            $resource_url = isset($_POST["resource_url"]) ? $_POST["resource_url"] : "";
            
            if (empty($resource_url)) {
                $error_message = "Please enter a resource URL!";
            } else if (!filter_var($resource_url, FILTER_VALIDATE_URL)) {
                $error_message = "Please enter a valid URL!";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO course_resources (course_id, teacher_id, resource_name, resource_description, resource_url, is_link, is_visible, created_at) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
                    $stmt->execute([$course_id, $teacher_id, $resource_name, $resource_description, $resource_url, $is_visible]);
                    $success = true;
                } catch (PDOException $e) {
                    $error_message = "Database error: " . $e->getMessage();
                }
            }
        } else {
            $error_message = "Invalid resource type!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resource Upload Result - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .result-card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            max-width: 600px;
            width: 100%;
        }
        .icon-container {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .icon-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }
        .success-circle {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        .error-circle {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        .btn-action {
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="result-card card border-0 p-4">
            <div class="card-body text-center">
                <?php if ($success): ?>
                    <div class="icon-container">
                        <div class="icon-circle success-circle">
                            <i class="bi bi-check-lg"></i>
                        </div>
                    </div>
                    <h2 class="card-title mb-3">Upload Successful!</h2>
                    <p class="card-text text-muted mb-4">
                        Your <?= $resource_type == "file" ? "file" : "link" ?> resource "<?= htmlspecialchars($resource_name) ?>" has been successfully uploaded.
                        <?= $is_visible ? "Students can now see this resource." : "This resource has been saved but is currently hidden from students. You can publish it later." ?>
                    </p>
                    <div class="d-grid gap-3 d-md-flex justify-content-center">
                        <a href="../views/course_resources.php" class="btn btn-primary btn-action">
                            <i class="bi bi-folder2-open me-2"></i>View Resource Management
                        </a>
                        <a href="../views/upload_resource.php" class="btn btn-outline-primary btn-action">
                            <i class="bi bi-cloud-upload me-2"></i>Continue Uploading
                        </a>
                    </div>
                <?php else: ?>
                    <div class="icon-container">
                        <div class="icon-circle error-circle">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <h2 class="card-title mb-3">Upload Failed</h2>
                    <div class="alert alert-danger" role="alert">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                    <div class="d-grid gap-3 d-md-flex justify-content-center">
                        <button class="btn btn-primary btn-action" onclick="history.back()">
                            <i class="bi bi-arrow-left me-2"></i>Go Back to Edit
                        </button>
                        <a href="../dashboard.php" class="btn btn-outline-secondary btn-action">
                            <i class="bi bi-house-door me-2"></i>Return to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-transparent border-0 text-center text-muted mt-3">
                <small>CampusConnect © <?= date("Y") ?> - Course Resource Management System</small>
            </div>
        </div>
    </div>

    <!-- Add fade-out animation for success message -->
    <?php if ($success): ?>
    <script>
        // Automatically redirect to resource management page after 3 seconds
        setTimeout(function() {
            window.location.href = '../views/course_resources.php';
        }, 3000);
    </script>
    <?php endif; ?>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>