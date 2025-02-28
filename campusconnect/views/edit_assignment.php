<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is logged in and is either a teacher or administrator
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["teacher", "admin"])) {
    die('<div class="alert alert-danger">Access Denied! Please log in as a teacher or admin first.</div>');
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"] ?? "User";

// Check if assignment ID is provided
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    die('<div class="alert alert-danger">Error: No assignment ID provided!</div>');
}

$assignment_id = intval($_GET["id"]);

// Fetch the assignment details
$stmt = $pdo->prepare("
    SELECT a.*, c.course_name, c.course_code, c.teacher_id 
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ?
");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if assignment exists
if (!$assignment) {
    die('<div class="alert alert-danger">Error: Assignment not found!</div>');
}

// For teachers, verify they own the course
if ($role === "teacher" && $assignment["teacher_id"] != $user_id) {
    die('<div class="alert alert-danger">You do not have permission to edit this assignment!</div>');
}

// Get all courses for dropdown (admin sees all, teacher sees only their courses)
if ($role === "admin") {
    $course_stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
    $course_stmt->execute();
} else {
    $course_stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name");
    $course_stmt->execute([$user_id]);
}
$courses = $course_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Validate input
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $course_id = intval($_POST["course_id"] ?? 0);
    $due_date = $_POST["due_date"] ?? "";
    $max_points = intval($_POST["max_points"] ?? 100);
    
    // Basic validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Assignment title is required";
    }
    
    if (empty($due_date)) {
        $errors[] = "Due date is required";
    }
    
    if ($course_id <= 0) {
        $errors[] = "Please select a valid course";
    }
    
    // If no errors, process file upload if provided
    if (empty($errors)) {
        $attachment = $assignment["attachment"]; // Keep existing attachment by default
        
        // Check if a new file was uploaded
        if (isset($_FILES["attachment"]) && $_FILES["attachment"]["size"] > 0) {
            $target_dir = "../uploads/assignments/";
            
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $temp_name = $_FILES["attachment"]["tmp_name"];
            $file_name = basename($_FILES["attachment"]["name"]);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Generate a unique filename
            $unique_name = uniqid() . "_" . $file_name;
            $target_file = $target_dir . $unique_name;
            
            // Move uploaded file
            if (move_uploaded_file($temp_name, $target_file)) {
                $attachment = $target_file;
            } else {
                $errors[] = "Sorry, there was an error uploading your file.";
            }
        }
        
        // Update assignment in database
        if (empty($errors)) {
            $update_stmt = $pdo->prepare("
                UPDATE assignments 
                SET title = ?, description = ?, course_id = ?, due_date = ?, max_points = ?, attachment = ?
                WHERE id = ?
            ");
            
            $result = $update_stmt->execute([
                $title,
                $description,
                $course_id,
                $due_date,
                $max_points,
                $attachment,
                $assignment_id
            ]);
            
            if ($result) {
                // Log the activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details)
                    VALUES (?, 'update', 'assignment', ?, ?)
                ");
                $log_stmt->execute([
                    $user_id,
                    $assignment_id,
                    json_encode([
                        'title' => $title,
                        'course_id' => $course_id
                    ])
                ]);
                
                // Redirect to the assignments list with success message
                header("Location: assignments_list.php?message=updated");
                exit;
            } else {
                $errors[] = "Failed to update assignment. Please try again.";
            }
        }
    }
}

// Format the due date for the datetime-local input
$formatted_due_date = str_replace(' ', 'T', date('Y-m-d H:i', strtotime($assignment["due_date"])));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment - CampusConnect</title>
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
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .main-container {
            max-width: 1000px;
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
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
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
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .form-group.required .form-label:after {
            content: " *";
            color: red;
        }
        
        .file-upload-wrapper {
            position: relative;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background-color: #f8f9fa;
        }
        
        .file-upload-wrapper:hover {
            border-color: var(--primary-color);
            background-color: var(--primary-light);
        }
        
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-upload-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .file-info {
            padding: 0.75rem;
            background-color: var(--primary-light);
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .current-file {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .admin-badge {
            background-color: #dc3545;
            color: white;
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
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
                    <li class="nav-item">
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-book me-1"></i> Courses
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-clipboard-check me-1"></i> Assignment Management
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="assignments_list.php"><i class="bi bi-list-check me-1"></i> Assignment List</a></li>
                            <li><a class="dropdown-item" href="add_assignment.php"><i class="bi bi-plus-circle me-1"></i> Add Assignment</a></li>
                            <?php if ($role === "admin"): ?>
                            <li><a class="dropdown-item" href="grade_overview.php"><i class="bi bi-bar-chart me-1"></i> Grading Overview</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($user_name) ?>
                            <?php if ($role === "admin"): ?>
                            <span class="admin-badge">Admin</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-1"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../modules/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-container">
        <!-- Page title -->
        <div class="page-header">
            <h2><i class="bi bi-pencil-square me-2"></i> Edit Assignment</h2>
            <div>
                <a href="assignments_list.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Assignments
                </a>
            </div>
        </div>

        <!-- Display any errors -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> Please fix the following errors:
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- Edit assignment form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="bi bi-pencil-square me-2"></i> Update Assignment Details</h5>
                <p class="mb-0 mt-2 small">Modify assignment information and save changes</p>
            </div>
            
            <div class="card-body">
                <form id="editAssignmentForm" action="" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <!-- Left column -->
                        <div class="col-md-7">
                            <!-- Course selection -->
                            <div class="mb-4 form-group required">
                                <label for="course_id" class="form-label">
                                    <i class="bi bi-book-fill me-1"></i> Course
                                </label>
                                <select name="course_id" id="course_id" class="form-select" required>
                                    <option value="" disabled>-- Select Course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>" <?= ($course['id'] == $assignment['course_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['course_name']) ?> 
                                            <?= !empty($course['course_code']) ? "(".htmlspecialchars($course['course_code']).")" : "" ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Change the course this assignment belongs to</div>
                            </div>

                            <!-- Assignment title -->
                            <div class="mb-4 form-group required">
                                <label for="title" class="form-label">
                                    <i class="bi bi-card-heading me-1"></i> Assignment Title
                                </label>
                                <input type="text" name="title" id="title" class="form-control" 
                                       required maxlength="100" value="<?= htmlspecialchars($assignment['title']) ?>">
                                <div class="form-text">A clear title helps students understand the assignment</div>
                            </div>

                            <!-- Assignment description -->
                            <div class="mb-4 form-group required">
                                <label for="description" class="form-label">
                                    <i class="bi bi-file-text me-1"></i> Assignment Description
                                </label>
                                <textarea name="description" id="description" class="form-control" 
                                          required rows="6"><?= htmlspecialchars($assignment['description']) ?></textarea>
                                <div class="form-text">Provide detailed instructions for the assignment</div>
                            </div>
                        </div>

                        <!-- Right column -->
                        <div class="col-md-5">
                            <!-- Due date -->
                            <div class="mb-4 form-group required">
                                <label for="due_date" class="form-label">
                                    <i class="bi bi-calendar-event me-1"></i> Due Date
                                </label>
                                <input type="datetime-local" name="due_date" id="due_date" class="form-control" 
                                       required value="<?= $formatted_due_date ?>">
                                <div class="form-text">Updated deadline for assignment submission</div>
                            </div>

                            <!-- Points setting -->
                            <div class="mb-4">
                                <label for="max_points" class="form-label">
                                    <i class="bi bi-star me-1"></i> Maximum Points
                                </label>
                                <div class="input-group">
                                    <input type="number" name="max_points" id="max_points" class="form-control" 
                                           value="<?= $assignment['max_points'] ?>" min="0" max="1000">
                                    <span class="input-group-text">points</span>
                                </div>
                                <div class="form-text">Total points possible for this assignment</div>
                            </div>

                            <!-- Current attachment info -->
                            <?php if (!empty($assignment['attachment'])): ?>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-file-earmark me-1"></i> Current Attachment
                                    </label>
                                    <div class="current-file">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-file-earmark-fill fs-4 me-3 text-primary"></i>
                                            <div class="flex-grow-1">
                                                <div class="fw-medium"><?= basename($assignment['attachment']) ?></div>
                                                <div class="small text-muted">Leave empty to keep this file</div>
                                            </div>
                                            <a href="<?= htmlspecialchars($assignment['attachment']) ?>" class="btn btn-sm btn-outline-primary" download>
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Attachment upload -->
                            <div class="mb-4">
                                <label class="form-label">
                                    <i class="bi bi-paperclip me-1"></i> Upload New Attachment (Optional)
                                </label>
                                <div class="file-upload-wrapper">
                                    <div class="file-upload-icon">
                                        <i class="bi bi-cloud-arrow-up"></i>
                                    </div>
                                    <div class="file-upload-text">
                                        <p class="mb-1">Drag and drop files here or click to upload</p>
                                        <span class="text-muted small">Supports PDF, Word, PPT, etc. (Max 20MB)</span>
                                    </div>
                                    <input type="file" name="attachment" id="attachment" class="form-control">
                                </div>
                                <div id="fileInfo" class="file-info" style="display: none;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submission details section -->
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h6><i class="bi bi-info-circle me-2"></i> Submission Information</h6>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Created:</strong> <?= date('Y-m-d H:i', strtotime($assignment['created_at'])) ?></p>
                                    <p class="mb-0"><strong>Assignment ID:</strong> <?= $assignment_id ?></p>
                                </div>
                                <div class="col-md-6">
                                    <?php
                                    // Get submission count
                                    $sub_stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE assignment_id = ?");
                                    $sub_stmt->execute([$assignment_id]);
                                    $submission_count = $sub_stmt->fetchColumn();
                                    ?>
                                    <p class="mb-1"><strong>Submissions Received:</strong> <?= $submission_count ?></p>
                                    <p class="mb-0">
                                        <a href="view_submissions.php?assignment_id=<?= $assignment_id ?>" class="text-primary">
                                            <i class="bi bi-eye me-1"></i> View Submissions
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form bottom buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="assignments_list.php" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-x-circle me-1"></i> Cancel
                            </a>
                        </div>
                        <div>
                            <button type="button" id="deleteBtn" class="btn btn-outline-danger me-2">
                                <i class="bi bi-trash me-1"></i> Delete Assignment
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Save Changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Admin actions card (only for admins) -->
        <?php if ($role === "admin"): ?>
        <div class="card bg-light">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-shield-lock me-2 text-danger"></i> Admin Actions</h5>
                <p>As an administrator, you can edit any assignment in the system regardless of which teacher created it.</p>
                <div class="d-flex mt-3">
                    <button type="button" id="notifyStudentsBtn" class="btn btn-outline-primary me-2">
                        <i class="bi bi-bell me-1"></i> Notify Students of Changes
                    </button>
                    <button type="button" id="extendDeadlineBtn" class="btn btn-outline-success">
                        <i class="bi bi-calendar-plus me-1"></i> Extend Deadline for All
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Delete confirmation modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-1 me-3"></i>
                        <div>
                            <p class="mb-1">Are you sure you want to delete this assignment?</p>
                            <p class="text-danger mb-0"><strong>This action cannot be undone!</strong></p>
                        </div>
                    </div>
                    <p>Assignment: <strong><?= htmlspecialchars($assignment['title']) ?></strong></p>
                    <p>Course: <strong><?= htmlspecialchars($assignment['course_name']) ?></strong></p>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle-fill me-2"></i> All student submissions for this assignment will also be permanently deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../modules/delete_assignment.php?id=<?= $assignment_id ?>" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Delete Assignment
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Deadline extension modal (admin only) -->
    <?php if ($role === "admin"): ?>
    <div class="modal fade" id="extendDeadlineModal" tabindex="-1" aria-labelledby="extendDeadlineModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="extendDeadlineModalLabel">Extend Assignment Deadline</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Set a new deadline for all students for this assignment:</p>
                    <div class="mb-3">
                        <label for="extended_deadline" class="form-label">New Deadline</label>
                        <input type="datetime-local" class="form-control" id="extended_deadline" value="<?= $formatted_due_date ?>">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="notify_extension" checked>
                        <label class="form-check-label" for="notify_extension">
                            Notify all students about the extended deadline
                        </label>
                    </div>
                    <div class="form-group">
                        <label for="extension_reason" class="form-label">Reason for Extension (Optional)</label>
                        <textarea class="form-control" id="extension_reason" rows="3" placeholder="Explain why the deadline is being extended..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmExtension">Save Extension</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- JavaScript libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // File upload handling
            $("#attachment").change(function() {
                const file = this.files[0];
                if (file) {
                    // Get file size
                    const fileSize = (file.size / 1024 / 1024).toFixed(2);
                    // Get file extension
                    const fileName = file.name;
                    const fileExt = fileName.split('.').pop().toLowerCase();
                    
                    // Display file information
                    let icon = 'bi-file-earmark';
                    let colorClass = 'text-primary';
                    
                    // Set different icons based on file type
                    if (['pdf'].includes(fileExt)) {
                        icon = 'bi-file-earmark-pdf';
                        colorClass = 'text-danger';
                    } else if (['doc', 'docx'].includes(fileExt)) {
                        icon = 'bi-file-earmark-word';
                        colorClass = 'text-primary';
                    } else if (['xls', 'xlsx'].includes(fileExt)) {
                        icon = 'bi-file-earmark-excel';
                        colorClass = 'text-success';
                    } else if (['ppt', 'pptx'].includes(fileExt)) {
                        icon = 'bi-file-earmark-ppt';
                        colorClass = 'text-warning';
                    } else if (['zip', 'rar'].includes(fileExt)) {
                        icon = 'bi-file-earmark-zip';
                        colorClass = 'text-secondary';
                    }
                    
                    const fileHtml = `
                        <div class="d-flex align-items-center">
                            <i class="bi ${icon} fs-2 me-3 ${colorClass}"></i>
                            <div>
                                <div class="fw-medium text-truncate" style="max-width: 250px;">${fileName}</div>
                                <div class="small text-muted">
                                    <span class="me-2">${fileExt.toUpperCase()}</span>
                                    <span>${fileSize} MB</span>
                                </div>
                            </div>
                            <button type="button" id="removeFile" class="btn btn-sm btn-outline-danger ms-auto">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    `;
                    
                    $("#fileInfo").html(fileHtml).show();
                } else {
                    $("#fileInfo").html('').hide();
                }
            });
            
            // Remove file button
            $(document).on('click', '#removeFile', function() {
                $("#attachment").val('');
                $("#fileInfo").html('').hide();
            });
            
            // Delete button
            $("#deleteBtn").click(function() {
                // Show the delete confirmation modal
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
            
            <?php if ($role === "admin"): ?>
            // Admin-only features
            $("#extendDeadlineBtn").click(function() {
                // Show the deadline extension modal
                const extensionModal = new bootstrap.Modal(document.getElementById('extendDeadlineModal'));
                extensionModal.show();
            });
            
            // Notify students button
            $("#notifyStudentsBtn").click(function() {
                // You can implement this feature by making an AJAX call to a notification endpoint
                if (confirm("Are you sure you want to notify all enrolled students about changes to this assignment?")) {
                    alert("Notification feature will be implemented soon!");
                    // TODO: Implement notification feature
                }
            });
            
            // Confirm deadline extension
            $("#confirmExtension").click(function() {
                const newDeadline = $("#extended_deadline").val();
                const notify = $("#notify_extension").is(":checked");
                const reason = $("#extension_reason").val();
                
                if (!newDeadline) {
                    alert("Please set a new deadline!");
                    return;
                }
                
                // Create a form and submit it
                const form = $("<form>")
                    .attr("method", "post")
                    .attr("action", "extend_deadline.php");
                
                $("<input>")
                    .attr("type", "hidden")
                    .attr("name", "assignment_id")
                    .attr("value", <?= $assignment_id ?>)
                    .appendTo(form);
                
                $("<input>")
                    .attr("type", "hidden")
                    .attr("name", "new_deadline")
                    .attr("value", newDeadline)
                    .appendTo(form);
                
                $("<input>")
                    .attr("type", "hidden")
                    .attr("name", "notify_students")
                    .attr("value", notify ? "1" : "0")
                    .appendTo(form);
                
                $("<input>")
                    .attr("type", "hidden")
                    .attr("name", "reason")
                    .attr("value", reason)
                    .appendTo(form);
                
                form.appendTo("body").submit();
            });
            <?php endif; ?>
            
            // Form validation
            $("#editAssignmentForm").on('submit', function(e) {
                let isValid = true;
                
                // Validate title
                const title = $("#title").val().trim();
                if (!title) {
                    alert("Please enter an assignment title!");
                    $("#title").focus();
                    isValid = false;
                }
                
                // Validate description
                const description = $("#description").val().trim();
                if (!description) {
                    alert("Please enter an assignment description!");
                    $("#description").focus();
                    isValid = false;
                }
                
                // Validate course selection
                const courseId = $("#course_id").val();
                if (!courseId) {
                    alert("Please select a course!");
                    $("#course_id").focus();
                    isValid = false;
                }
                
                // Validate due date
                const dueDate = $("#due_date").val();
                if (!dueDate) {
                    alert("Please set a due date!");
                    $("#due_date").focus();
                    isValid = false;
                }
                
                // Validate file size if provided
                const file = document.getElementById('attachment').files[0];
                if (file && file.size > 20 * 1024 * 1024) { // 20MB
                    alert("File size exceeds 20MB limit!");
                    isValid = false;
                }
                
                return isValid;
            });
        });
    </script>
</body>
</html>