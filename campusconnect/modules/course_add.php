<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Only teachers can add courses
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != "teacher") {
    die("Access denied!");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_code = trim($_POST["course_code"]);
    $course_name = trim($_POST["course_name"]);
    $description = trim($_POST["description"]);
    $teacher_id = $_SESSION["user_id"];

    // Check if the course code already exists
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE course_code = ?");
    $stmt->execute([$course_code]);
    if ($stmt->fetch()) {
        die("Course code already exists!");
    }

    // Insert new course
    $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, description, teacher_id) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$course_code, $course_name, $description, $teacher_id])) {
        echo "Course added successfully! <a href='../views/course_list.php'>View courses</a>";
    } else {
        echo "Failed to add course, please try again!";
    }
}
?>
