<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// **Ensure the user is logged in and is either a teacher or an admin**
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["role"], ["teacher", "admin"])) {
    die("Access denied!");
}

// **Check if an assignment ID is provided**
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    die("Error: Missing assignment ID!");
}

$assignment_id = $_GET["id"];
$role = $_SESSION["role"];
$user_id = $_SESSION["user_id"];

// **Fetch assignment details to ensure teachers can only delete their own assignments**
$stmt = $pdo->prepare("SELECT assignments.id, assignments.title, courses.course_name, courses.teacher_id 
                       FROM assignments 
                       JOIN courses ON assignments.course_id = courses.id 
                       WHERE assignments.id = ?");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    die("Error: Assignment does not exist!");
}

// **Teachers can only delete assignments from their own courses**
if ($role === "teacher" && $assignment["teacher_id"] !== $user_id) {
    die("You do not have permission to delete this assignment!");
}

// **Execute assignment deletion**
$stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ?");
if ($stmt->execute([$assignment_id])) {
    // ✅ **Store in session and redirect to delete_success.php**
    $_SESSION["deleted_assignment"] = [
        "title" => $assignment["title"],
        "course_name" => $assignment["course_name"],
        "deleted_at" => date("Y-m-d H:i:s"),
        "deleted_by" => $_SESSION["user_name"],
        "role" => $_SESSION["role"]
    ];

    header("Location: ../views/delete_success.php");
    exit;
} else {
    die("Deletion failed, please try again!");
}
?>
