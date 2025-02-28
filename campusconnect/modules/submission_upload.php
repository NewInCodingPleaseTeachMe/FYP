<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Students only access
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    die("<div class='alert alert-danger'>❌ Access denied!</div>");
}

$assignment_id = $_POST["assignment_id"] ?? null;
$student_id = $_SESSION["user_id"];
$comment = $_POST["comment"] ?? "";

// ✅ Ensure `assignment_id` exists
if (!$assignment_id) {
    die("<div class='alert alert-danger'>❌ Error: Please select an assignment!</div>");
}

// ✅ Ensure file upload was successful
if (!isset($_FILES["submission_file"]) || $_FILES["submission_file"]["error"] !== 0) {
    die("<div class='alert alert-danger'>❌ Upload failed, please try again!</div>");
}

// ✅ Process file upload
$file_name = $_FILES["submission_file"]["name"];
$file_tmp = $_FILES["submission_file"]["tmp_name"];
$file_size = $_FILES["submission_file"]["size"];
$upload_dir = "../uploads/";

// Check file size (10MB limit)
$max_size = 10 * 1024 * 1024; // 10MB
if ($file_size > $max_size) {
    die("<div class='alert alert-danger'>❌ File too large, maximum allowed is 10MB!</div>");
}

// Check file type
$allowed_extensions = ['doc', 'docx', 'pdf', 'zip', 'rar'];
$file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if (!in_array($file_extension, $allowed_extensions)) {
    die("<div class='alert alert-danger'>❌ Unsupported file format!</div>");
}

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ✅ Generate unique filename to prevent overwriting 
$new_file_name = uniqid() . "_" . $file_name;
$file_path = $upload_dir . $new_file_name;

// Move uploaded file
if (!move_uploaded_file($file_tmp, $file_path)) {
    die("<div class='alert alert-danger'>❌ File upload failed, please try again!</div>");
}

// ✅ Check if submission already exists
$stmt = $pdo->prepare("SELECT id, file_path FROM submissions WHERE assignment_id = ? AND student_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$assignment_id, $student_id]);
$existing_submission = $stmt->fetch();

if ($existing_submission) {
    // ✅ If submission exists, delete old file and update record
    $old_file_path = $existing_submission['file_path'];
    if (file_exists($old_file_path)) {
        unlink($old_file_path); // Delete old file
    }

    // Update file path in database
    $stmt = $pdo->prepare("UPDATE submissions SET file_name = ?, file_path = ?, comment = ?, submitted_at = NOW(), grade = NULL, score = NULL, feedback = NULL, graded_at = NULL WHERE id = ?");
    $stmt->execute([$file_name, $file_path, $comment, $existing_submission['id']]);

    echo "<div class='alert alert-success'>✅ Assignment has been updated!</div>";
} else {
    // ✅ If no submission exists, create new record
    $stmt = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, file_name, file_path, comment, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$assignment_id, $student_id, $file_name, $file_path, $comment]);

    echo "<div class='alert alert-success'>✅ Assignment submitted successfully!</div>";
}

// ✅ Redirect to assignment details page after successful submission
header("Location: ../views/view_assignment.php?id=".$assignment_id);
exit;
?>