<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is logged in
if (!isset($_SESSION["user_id"])) {
    die("Access denied!");
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

if (!isset($_GET["id"])) {
    die("Invalid request!");
}

$course_id = $_GET["id"];

// Get course information
$stmt = $pdo->prepare("SELECT teacher_id FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    die("Course does not exist!");
}

// Only **administrators** or **course teachers** can delete courses
if ($role !== "admin" && $course["teacher_id"] != $user_id) {
    die("You do not have permission to delete this course!");
}

// Delete the course
$stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
if ($stmt->execute([$course_id])) {
    echo "Course deleted successfully! <a href='course_list.php'>Return to course list</a>";
} else {
    echo "Deletion failed, please try again!";
}
?>