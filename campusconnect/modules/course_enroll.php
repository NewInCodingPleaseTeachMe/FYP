<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Only students can access this page
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    include_once "../views/header.php";
    showErrorAndExit("Access Denied", "Only students can enroll in courses.", "../views/login.php");
}

$user_id = $_SESSION["user_id"];

// Check if `course_id` is provided
if (!isset($_POST["course_id"]) || empty($_POST["course_id"])) {
    include_once "../views/header.php";
    showErrorAndExit("Invalid Request", "Course ID cannot be empty.", "../views/course_list.php");
}

$course_id = $_POST["course_id"];

// Get course details to display in the response
$stmt = $pdo->prepare("SELECT course_name FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
$course_name = $course ? htmlspecialchars($course['course_name']) : 'Unknown Course';

// **Check if the student has already enrolled in the course**
$stmt = $pdo->prepare("SELECT * FROM user_courses WHERE user_id = ? AND course_id = ?");
$stmt->execute([$user_id, $course_id]);
if ($stmt->fetch()) {
    include_once "../views/header.php";
    showEnrollmentStatus("Already Enrolled", "You have already enrolled in \"$course_name\".", "warning", "../views/course_details.php?id=$course_id");
}

// **Enroll the student in the course**
$stmt = $pdo->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (?, ?)");
if ($stmt->execute([$user_id, $course_id])) {
    include_once "../views/header.php";
    showEnrollmentStatus("Enrollment Successful", "You have successfully enrolled in \"$course_name\".", "success", "../views/course_details.php?id=$course_id");
} else {
    include_once "../views/header.php";
    showErrorAndExit("Enrollment Failed", "There was an error processing your enrollment. Please try again later.", "../views/course_list.php");
}

/**
 * Display error message and exit script
 * 
 * @param string $title Error title
 * @param string $message Error message
 * @param string $redirect Redirect URL
 */
function showErrorAndExit($title, $message, $redirect) {
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-danger shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $title ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <img src="../assets/images/error.svg" alt="Error" class="img-fluid" style="max-width: 120px;">
                        </div>
                        <p class="text-center"><?= $message ?></p>
                        <div class="text-center mt-4">
                            <a href="<?= $redirect ?>" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left me-2"></i>Go Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    include_once "../views/footer.php";
    exit;
}

/**
 * Display enrollment status message
 * 
 * @param string $title Status title
 * @param string $message Status message
 * @param string $type Message type (success|warning|danger)
 * @param string $courseLink Link to the course
 */
function showEnrollmentStatus($title, $message, $type, $courseLink) {
    $icon = ($type === 'success') ? 'check-circle-fill' : 'info-circle-fill';
    $bgColor = ($type === 'success') ? 'bg-success' : 'bg-warning';
    $textColor = ($type === 'success') ? 'text-white' : 'text-dark';
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-<?= $type ?> shadow-sm">
                    <div class="card-header <?= $bgColor ?> <?= $textColor ?>">
                        <h5 class="mb-0"><i class="bi bi-<?= $icon ?> me-2"></i><?= $title ?></h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-4">
                            <?php if ($type === 'success'): ?>
                                <div class="success-animation">
                                    <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                        <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none"/>
                                        <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                                    </svg>
                                </div>
                            <?php else: ?>
                                <i class="bi bi-info-circle-fill" style="font-size: 4rem; color: #ffc107;"></i>
                            <?php endif; ?>
                        </div>
                        <p class="mb-4"><?= $message ?></p>
                        <div class="d-grid gap-2 d-sm-flex justify-content-center">
                            <a href="<?= $courseLink ?>" class="btn btn-primary">
                                <i class="bi bi-book me-2"></i>Go to Course
                            </a>
                            <a href="../views/course_list.php" class="btn btn-outline-secondary">
                                <i class="bi bi-grid-3x3-gap me-2"></i>Course Catalog
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        /* Success animation styles */
        .success-animation {
            margin: 20px auto;
            width: 80px;
            height: 80px;
        }
        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: #4bb71b;
            stroke-miterlimit: 10;
            box-shadow: inset 0px 0px 0px #4bb71b;
            animation: fill .4s ease-in-out .4s forwards, scale .3s ease-in-out .9s both;
        }
        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 2;
            stroke-miterlimit: 10;
            stroke: #4bb71b;
            fill: none;
            animation: stroke .6s cubic-bezier(0.650, 0.000, 0.450, 1.000) forwards;
        }
        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke .3s cubic-bezier(0.650, 0.000, 0.450, 1.000) .8s forwards;
        }
        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }
        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }
        @keyframes fill {
            100% { box-shadow: inset 0px 0px 0px 30px #4bb71b; }
        }
    </style>
    <?php
    include_once "../views/footer.php";
    exit;
}
?>