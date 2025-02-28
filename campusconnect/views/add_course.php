<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Only allow teachers and administrators to access
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "teacher")) {
    die("Access denied!");
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];
// Note: If your session stores 'name' instead of 'user_name', please maintain consistency
$user_name = $_SESSION["name"] ?? "User";

// Get list of faculties
try {
    $stmt = $pdo->prepare("SELECT id, faculty_name FROM faculties ORDER BY faculty_name ASC");
    $stmt->execute();
    $faculties = $stmt->fetchAll();
} catch (PDOException $e) {
    $faculties = [];
}

// If administrator, get list of all teachers
$teachers = [];
if ($role === "admin") {
    try {
        // Note: If the users table stores teacher names in the 'name' field, change to SELECT id, name, email FROM users ...
        $stmt = $pdo->prepare("SELECT id, name as user_name, email FROM users WHERE role = 'teacher' ORDER BY name ASC");
        $stmt->execute();
        $teachers = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Handle error
    }
}

// Handle form submission
$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_code = trim($_POST["course_code"]);
    $course_name = trim($_POST["course_name"]);
    $description = trim($_POST["description"]);
    
    // Determine teacher ID source - if admin can specify teacher, otherwise use current user ID
    $teacher_id = ($role === "admin" && !empty($_POST["teacher_id"])) ? $_POST["teacher_id"] : $user_id;
    
    // Get additional fields
    $faculty_id = !empty($_POST["faculty_id"]) ? $_POST["faculty_id"] : null;
    $semester = !empty($_POST["semester"]) ? $_POST["semester"] : null;
    $credit_hours = !empty($_POST["credit_hours"]) ? $_POST["credit_hours"] : null;
    $max_students = !empty($_POST["max_students"]) ? $_POST["max_students"] : null;
    $start_date = !empty($_POST["start_date"]) ? $_POST["start_date"] : null;
    $end_date = !empty($_POST["end_date"]) ? $_POST["end_date"] : null;
    
    // 1. Check if same course code already exists (course_code must be unique)
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_code = ?");
    $check_stmt->execute([$course_code]);
    $exists = $check_stmt->fetchColumn();
    
    if ($exists > 0) {
        // If duplicate course code exists, give error message
        $error_message = "Database error: This course code is already in use, please choose a different course code!";
    } else {
        // 2. If it doesn't exist, proceed with insert operation
        try {
            $stmt = $pdo->prepare("INSERT INTO courses 
                (course_code, course_name, description, teacher_id, faculty_id, semester, credit_hours, max_students, start_date, end_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([
                $course_code, $course_name, $description, $teacher_id, $faculty_id, 
                $semester, $credit_hours, $max_students, $start_date, $end_date
            ])) {
                $success_message = "Course '{$course_name}' created successfully!";
            } else {
                $error_message = "Course creation failed, please try again!";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Course - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Date Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        
        .course-container {
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
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
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
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
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
        
        /* Tag styles */
        .course-tag {
            display: inline-block;
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid var(--primary-color);
            color: var(--primary-color);
            background-color: white;
        }
        
        .course-tag.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .course-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Preview area */
        .preview-section {
            background-color: var(--primary-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .preview-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
        }
        
        .preview-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 1.5rem;
            border-bottom: none;
        }
        
        .preview-body {
            padding: 1.5rem;
        }
        
        .preview-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-dark);
        }
        
        /* Animation */
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
        }
        
        /* Navbar styles */
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
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
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-book me-1"></i> Course Management
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="#"><i class="bi bi-plus-circle me-1"></i> Add Course</a></li>
                            <li><a class="dropdown-item" href="course_list.php"><i class="bi bi-list-ul me-1"></i> Course List</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($user_name) ?>
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

    <div class="course-container">
        <!-- Page title -->
        <div class="page-header">
            <h2><i class="bi bi-book-fill me-2"></i> Add New Course</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="course_list.php">Course List</a></li>
                    <li class="breadcrumb-item active">Add Course</li>
                </ol>
            </nav>
        </div>

        <!-- Message alerts -->
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
                <h5><i class="bi bi-plus-circle me-2"></i> Course Information</h5>
                <p class="mb-0 mt-2 small">Fill in complete course information to create a new course</p>
            </div>
            
            <div class="card-body">
                <form id="courseForm" method="POST" action="">
                    <div class="row">
                        <!-- Left column - Basic Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Basic Information</h5>
                            
                            <!-- Course code -->
                            <div class="mb-3 form-group required">
                                <label for="course_code" class="form-label">
                                    <i class="bi bi-hash me-1"></i> Course Code
                                </label>
                                <input type="text" name="course_code" id="course_code" class="form-control" 
                                       required maxlength="20" placeholder="Example: CS101">
                                <div class="form-text">Enter a unique course code, such as CS101, MATH202, etc.</div>
                            </div>

                            <!-- Course name -->
                            <div class="mb-3 form-group required">
                                <label for="course_name" class="form-label">
                                    <i class="bi bi-card-heading me-1"></i> Course Name
                                </label>
                                <input type="text" name="course_name" id="course_name" class="form-control" 
                                       required maxlength="100" placeholder="Example: Introduction to Computer Programming">
                                <div class="form-text">Enter a descriptive course name</div>
                            </div>

                            <!-- Course description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    <i class="bi bi-file-text me-1"></i> Course Description
                                </label>
                                <textarea name="description" id="description" class="form-control" 
                                          rows="5" placeholder="Detailed description of course content, objectives, and learning outcomes..."></textarea>
                                <div class="form-text">Provide a detailed explanation of the course, including course objectives, content overview, etc.</div>
                            </div>

                            <!-- Faculty selection -->
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
                                <div class="form-text">Select the faculty or department the course belongs to</div>
                            </div>
                        </div>

                       <!-- Right column - Detailed information -->
                       <div class="col-md-6">
                            <h5 class="mb-3">Detailed Settings</h5>
                            
                            <!-- Select teacher (visible only to administrators) -->
                            <?php if ($role === "admin"): ?>
                                <div class="mb-3">
                                    <label for="teacher_id" class="form-label">
                                        <i class="bi bi-person-badge me-1"></i> Instructor
                                    </label>
                                    <select name="teacher_id" id="teacher_id" class="form-select">
                                        <option value="">-- Select Teacher --</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?= $teacher['id'] ?>">
                                                <?= htmlspecialchars($teacher['user_name']) ?> 
                                                (<?= htmlspecialchars($teacher['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select the teacher responsible for this course</div>
                                </div>
                            <?php endif; ?>

                            <!-- Semester -->
                            <div class="mb-3">
                                <label for="semester" class="form-label">
                                    <i class="bi bi-calendar3 me-1"></i> Semester
                                </label>
                                <select name="semester" id="semester" class="form-select">
                                    <option value="">-- Select Semester --</option>
                                    <option value="2024-Spring">2024 - Spring Semester</option>
                                    <option value="2024-Summer">2024 - Summer Semester</option>
                                    <option value="2024-Fall">2024 - Fall Semester</option>
                                    <option value="2025-Spring">2025 - Spring Semester</option>
                                </select>
                                <div class="form-text">Select the semester for this course</div>
                            </div>

                            <!-- Credit hours -->
                            <div class="mb-3">
                                <label for="credit_hours" class="form-label">
                                    <i class="bi bi-award me-1"></i> Credit Hours
                                </label>
                                <input type="number" name="credit_hours" id="credit_hours" class="form-control" 
                                       min="0" max="10" step="0.5" value="3">
                                <div class="form-text">Set the credit hours for this course</div>
                            </div>

                            <!-- Maximum students -->
                            <div class="mb-3">
                                <label for="max_students" class="form-label">
                                    <i class="bi bi-people me-1"></i> Maximum Students
                                </label>
                                <input type="number" name="max_students" id="max_students" class="form-control" 
                                       min="1" max="500" value="50">
                                <div class="form-text">Set the maximum number of students that can enroll in this course</div>
                            </div>

                            <!-- Course dates -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="start_date" class="form-label">
                                        <i class="bi bi-calendar-plus me-1"></i> Start Date
                                    </label>
                                    <input type="date" name="start_date" id="start_date" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="end_date" class="form-label">
                                        <i class="bi bi-calendar-minus me-1"></i> End Date
                                    </label>
                                    <input type="date" name="end_date" id="end_date" class="form-control">
                                </div>
                                <div class="form-text mt-0">Set the start and end dates for this course</div>
                            </div>

                            <!-- Course type tags -->
                            <div class="mb-3">
                                <label class="form-label"><i class="bi bi-tags me-1"></i> Course Type</label>
                                <div>
                                    <span class="course-tag" data-value="Required">Required</span>
                                    <span class="course-tag" data-value="Elective">Elective</span>
                                    <span class="course-tag" data-value="Core">Core Professional</span>
                                    <span class="course-tag" data-value="General">General Education</span>
                                    <span class="course-tag" data-value="Lab">Laboratory</span>
                                    <span class="course-tag" data-value="Seminar">Seminar</span>
                                </div>
                                <input type="hidden" name="course_type" id="course_type" value="">
                                <div class="form-text">Select the type of course</div>
                            </div>
                        </div>
                    </div>

                    <!-- Preview area -->
                    <div class="preview-section">
                        <div class="preview-title"><i class="bi bi-eye me-2"></i> Course Preview</div>
                        <div class="preview-card">
                            <div class="preview-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0 preview-course-name">Course name will be displayed here</h5>
                                    <span class="badge bg-light text-primary preview-course-code">Course Code</span>
                                </div>
                            </div>
                            <div class="preview-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <p class="preview-description text-muted">Course description will be displayed here...</p>
                                        <div class="d-flex flex-wrap mt-3">
                                            <div class="me-4 mb-3">
                                                <strong><i class="bi bi-person-badge me-1"></i> Instructor:</strong>
                                                <span class="preview-teacher"><?= htmlspecialchars($user_name) ?></span>
                                            </div>
                                            <div class="me-4 mb-3">
                                                <strong><i class="bi bi-calendar3 me-1"></i> Semester:</strong>
                                                <span class="preview-semester">Not set</span>
                                            </div>
                                            <div class="me-4 mb-3">
                                                <strong><i class="bi bi-award me-1"></i> Credits:</strong>
                                                <span class="preview-credits">3</span>
                                            </div>
                                            <div class="mb-3">
                                                <strong><i class="bi bi-people me-1"></i> Enrollment Cap:</strong>
                                                <span class="preview-max-students">50</span> students
                                            </div>
                                        </div>
                                        <div id="previewTags" class="mt-2"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light h-100">
                                            <div class="card-body">
                                                <h6 class="card-title"><i class="bi bi-info-circle me-1"></i> Course Information</h6>
                                                <div class="small">
                                                    <div class="mb-1">
                                                        <strong>Start Date:</strong> <span class="preview-start-date">Not set</span>
                                                    </div>
                                                    <div class="mb-1">
                                                        <strong>End Date:</strong> <span class="preview-end-date">Not set</span>
                                                    </div>
                                                    <div class="mt-2">
                                                        <strong>Faculty:</strong> <span class="preview-faculty">Not set</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form bottom buttons -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="course_list.php" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left me-1"></i> Return to Course List
                            </a>
                        </div>
                        <div>
                            <button type="button" id="previewBtn" class="btn btn-outline-primary me-2">
                                <i class="bi bi-eye me-1"></i> Preview
                            </button>
                            <button type="submit" id="submitBtn" class="btn btn-primary">
                                <i class="bi bi-check2-circle me-1"></i> Create Course
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tips card -->
        <div class="card bg-light fade-in">
            <div class="card-body">
                <h5><i class="bi bi-lightbulb me-2 text-warning"></i> Course Creation Tips</h5>
                <ul class="mb-0">
                    <li><strong>Clear course code:</strong> Use easy-to-understand and memorable course codes, preferably with a subject prefix and numbers</li>
                    <li><strong>Detailed description:</strong> Provide a comprehensive course description, including learning objectives, teaching methods, and assessment methods</li>
                    <li><strong>Credit hours setting:</strong> Set appropriate credit hours based on course difficulty and contact hours</li>
                    <li><strong>Date planning:</strong> Ensure course start and end dates align with the school's academic calendar</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- JavaScript libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/en.js"></script>

    <script>
        $(document).ready(function() {
            // Tag selection
            $('.course-tag').click(function() {
                $(this).toggleClass('active');
                updateTags();
            });
            
            // Update hidden field with tag values
            function updateTags() {
                const tags = [];
                $('.course-tag.active').each(function() {
                    tags.push($(this).data('value'));
                });
                $('#course_type').val(tags.join(','));
                
                // Update preview
                const tagsHtml = tags.map(tag => 
                    `<span class="badge bg-primary me-1 mb-1">${tag}</span>`
                ).join('');
                $('#previewTags').html(tagsHtml);
            }
            
            // Real-time preview update
            function updatePreview() {
                // Basic information
                $('.preview-course-name').text($('#course_name').val() || 'Course name will be displayed here');
                $('.preview-course-code').text($('#course_code').val() || 'Course Code');
                $('.preview-description').text($('#description').val() || 'Course description will be displayed here...');
                
                // Detailed information
                $('.preview-credits').text($('#credit_hours').val() || '3');
                $('.preview-max-students').text($('#max_students').val() || '50');
                
                // Dates
                $('.preview-start-date').text($('#start_date').val() || 'Not set');
                $('.preview-end-date').text($('#end_date').val() || 'Not set');
                
                // Semester
                const semesterSelect = document.getElementById('semester');
                const selectedSemester = semesterSelect.options[semesterSelect.selectedIndex];
                $('.preview-semester').text(selectedSemester && selectedSemester.value ? selectedSemester.text : 'Not set');
                
                // Faculty
                const facultySelect = document.getElementById('faculty_id');
                const selectedFaculty = facultySelect.options[facultySelect.selectedIndex];
                $('.preview-faculty').text(selectedFaculty && selectedFaculty.value ? selectedFaculty.text : 'Not set');
                
                // If admin, update teacher
                <?php if ($role === "admin"): ?>
                const teacherSelect = document.getElementById('teacher_id');
                const selectedTeacher = teacherSelect.options[teacherSelect.selectedIndex];
                $('.preview-teacher').text(selectedTeacher && selectedTeacher.value ? selectedTeacher.text.split('(')[0].trim() : '<?= htmlspecialchars($user_name) ?>');
                <?php endif; ?>
                
                // Update tags
                updateTags();
            }
            
            // Update preview on form changes
            $('#course_name, #course_code, #description, #credit_hours, #max_students, #start_date, #end_date, #semester, #faculty_id<?php if ($role === "admin"): ?>, #teacher_id<?php endif; ?>').on('change input', function() {
                updatePreview();
            });
            
            // Preview button click
            $('#previewBtn').click(function() {
                updatePreview();
                // Scroll to preview area
                $('html, body').animate({
                    scrollTop: $('.preview-section').offset().top - 100
                }, 500);
            });
            
            // Form validation
            $('#courseForm').on('submit', function(e) {
                // Course code validation
                const courseCode = $('#course_code').val().trim();
                if (!courseCode) {
                    e.preventDefault();
                    alert('Please enter a course code!');
                    $('#course_code').focus();
                    return false;
                }
                
                // Course name validation
                const courseName = $('#course_name').val().trim();
                if (!courseName) {
                    e.preventDefault();
                    alert('Please enter a course name!');
                    $('#course_name').focus();
                    return false;
                }
                
                // Date logic validation
                const startDate = $('#start_date').val();
                const endDate = $('#end_date').val();
                
                if (startDate && endDate && new Date(startDate) > new Date(endDate)) {
                    e.preventDefault();
                    alert('End date cannot be earlier than start date!');
                    $('#end_date').focus();
                    return false;
                }
                
                // Prevent multiple submissions
                $('#submitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');
                
                return true;
            });
            
            // Load preview once on form initialization
            setTimeout(updatePreview, 500);
            
            // Auto-close success message
            setTimeout(function() {
                $('.alert-success').fadeOut('slow');
            }, 5000);
        });
    </script>
</body>
</html>