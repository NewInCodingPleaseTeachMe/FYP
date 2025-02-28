<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// 只允许教师访问此页面
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.php");
    exit;
}

$teacher_name = $_SESSION["name"] ?? "教师";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Guide - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .guide-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
            border: none;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #0d6efd;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        .step-icon {
            font-size: 2.5rem;
            color: #0d6efd;
        }
        .accordion-button:not(.collapsed) {
            background-color: #e7f1ff;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
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
                            <i class="bi bi-book me-1"></i> My Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="teacher_guide.php">
                            <i class="bi bi-info-circle me-1"></i> Teacher Guide
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($teacher_name) ?>
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

    <div class="guide-container">
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold">Welcome to CampusConnect Teacher Platform</h1>
            <p class="lead">This quick guide will help you understand how to use our system to create and manage courses</p>
        </div>
        <!-- Guide Cards -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <i class="bi bi-book-half step-icon"></i>
                        </div>
                        <h4>Create Courses</h4>
                        <p class="text-muted">Create your courses and add syllabus, learning objectives, and basic information</p>
                        <a href="course_add.php" class="btn btn-primary mt-2">Create New Course</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <i class="bi bi-file-earmark-text step-icon"></i>
                        </div>
                        <h4>Manage Course Resources</h4>
                        <p class="text-muted">Upload lecture notes, reading materials, and other learning resources</p>
                        <a href="course_list.php" class="btn btn-primary mt-2">Manage My Courses</a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center p-4">
                        <div class="mb-4">
                            <i class="bi bi-pencil-square step-icon"></i>
                        </div>
                        <h4>Assignments & Grading</h4>
                        <p class="text-muted">Create assignments, quizzes, and grade student submissions</p>
                        <a href="assignments_list.php" class="btn btn-primary mt-2">Manage Assignments</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step Guide -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Getting Started Guide</h3>
                <p class="text-muted mb-0">Follow these steps to get started with the system</p>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <span class="step-number">1</span>
                            <h5 class="mb-0">Complete Your Profile</h5>
                        </div>
                        <p>Update your profile information including contact details, expertise areas, and bio for students to better know you.</p>
                        <a href="profile.php" class="btn btn-outline-primary">Update Profile</a>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <span class="step-number">2</span>
                            <h5 class="mb-0">Create Your First Course</h5>
                        </div>
                        <p>Create a new course by filling in basic information such as course name, code, credits, and description.</p>
                        <a href="course_add.php" class="btn btn-outline-primary">Create New Course</a>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <span class="step-number">3</span>
                            <h5 class="mb-0">Upload Course Resources</h5>
                        </div>
                        <p>Add lecture notes, reference materials, video links, and other learning resources to help students learn better.</p>
                        <a href="course_list.php" class="btn btn-outline-primary">Manage Course Resources</a>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <span class="step-number">4</span>
                            <h5 class="mb-0">Create Assignments & Activities</h5>
                        </div>
                        <p>Create assignments, quizzes, and other activities, set due dates and grading criteria to assess student progress.</p>
                        <a href="assignment_create.php" class="btn btn-outline-primary">Create New Assignment</a>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <span class="step-number">5</span>
                            <h5 class="mb-0">Interact with Students</h5>
                        </div>
                        <p>Use announcements and discussion boards to interact with students, answer questions, and provide additional guidance and support.</p>
                        <a href="course_list.php" class="btn btn-outline-primary">View Courses</a>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-3">
                            <span class="step-number">6</span>
                            <h5 class="mb-0">Grade & Provide Feedback</h5>
                        </div>
                        <p>Grade student submissions, provide detailed feedback to help students improve and grow.</p>
                        <a href="submissions_list.php" class="btn btn-outline-primary">View Submissions</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="card">
            <div class="card-header">
                <h3>Frequently Asked Questions</h3>
                <p class="text-muted mb-0">Common questions about using the system as a teacher</p>
            </div>
            <div class="card-body">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faqOne">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                How do I allow students to join my course?
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="faqOne" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>After creating a course, students can join through these methods:</p>
                                <ol>
                                    <li>Students can find your course in the course list and request to join</li>
                                    <li>You can generate an invitation code through the system to share directly with students</li>
                                    <li>Administrators can directly add students to your course</li>
                                </ol>
                                <p>You can view and manage enrolled students in the "Student Management" page of your course.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faqTwo">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                How do I modify the due date for a published assignment?
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="faqTwo" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>To modify the due date for a published assignment:</p>
                                <ol>
                                    <li>Go to the respective course</li>
                                    <li>Find the assignment in the "Assignments" tab</li>
                                    <li>Click the "Edit" button</li>
                                    <li>Update the due date in the form</li>
                                    <li>Click "Save Changes" to apply the new deadline</li>
                                </ol>
                                <p>Students will automatically be notified of the deadline change.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faqThree">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                How do I provide feedback on student submissions?
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="faqThree" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>To provide feedback on student submissions:</p>
                                <ol>
                                    <li>Navigate to the assignment in your course</li>
                                    <li>Click on "View Submissions" to see all student submissions</li>
                                    <li>Select the submission you want to grade</li>
                                    <li>Enter a score and provide written feedback in the designated form</li>
                                    <li>Click "Submit Feedback" to save your assessment</li>
                                </ol>
                                <p>Students will receive a notification when their work has been graded.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faqFour">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                What file formats can I upload as course resources?
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="faqFour" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <p>You can upload various file formats as course resources, including:</p>
                                <ul>
                                    <li>Documents: PDF, DOC/DOCX, PPT/PPTX, XLS/XLSX, TXT</li>
                                    <li>Images: JPG, PNG, GIF</li>
                                    <li>Media: MP3, MP4 (up to 50MB)</li>
                                    <li>Archives: ZIP (for bundling multiple files)</li>
                                </ul>
                                <p>You can also add links to external resources like YouTube videos or websites.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>