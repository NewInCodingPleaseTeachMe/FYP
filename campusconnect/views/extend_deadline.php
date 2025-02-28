<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is logged in and is an administrator
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    die("Access denied! Admin privileges required.");
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "Admin";

// Check if required parameters are provided
if ($_SERVER["REQUEST_METHOD"] !== "POST" || 
    !isset($_POST["assignment_id"]) || 
    !isset($_POST["new_deadline"])) {
    die("Invalid request! Missing required parameters.");
}

$assignment_id = intval($_POST["assignment_id"]);
$new_deadline = $_POST["new_deadline"];
$notify_students = isset($_POST["notify_students"]) && $_POST["notify_students"] == "1";
$reason = $_POST["reason"] ?? "";

// Validate the assignment exists
$stmt = $pdo->prepare("
    SELECT a.*, c.course_name, c.course_code 
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ?
");
$stmt->execute([$assignment_id]);
$assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assignment) {
    die("Error: Assignment not found!");
}

// Convert the datetime-local format to MySQL datetime format
$formatted_deadline = date('Y-m-d H:i:s', strtotime($new_deadline));

// Update the assignment deadline
$update_stmt = $pdo->prepare("
    UPDATE assignments 
    SET due_date = ?
    WHERE id = ?
");

if ($update_stmt->execute([$formatted_deadline, $assignment_id])) {
    // Log the activity
    $log_stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'extend_deadline', 'assignment', ?, ?)
    ");
    $log_stmt->execute([
        $user_id,
        $assignment_id,
        json_encode([
            'previous_deadline' => $assignment['due_date'],
            'new_deadline' => $formatted_deadline,
            'reason' => $reason
        ])
    ]);
    
    // Notify students if requested (future implementation)
    if ($notify_students) {
        // This would be implemented with a notification system
        // For now, we'll just log that notifications were requested
        $log_stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details)
            VALUES (?, 'notification_request', 'assignment', ?, ?)
        ");
        $log_stmt->execute([
            $user_id,
            $assignment_id,
            json_encode([
                'type' => 'deadline_extended',
                'reason' => $reason
            ])
        ]);
    }
    
    // Redirect back to the edit page with success message
    $_SESSION['deadline_extended'] = true;
    header("Location: edit_assignment.php?id=$assignment_id&message=deadline_extended");
    exit;
} else {
    // Handle error
    die("Failed to extend deadline. Please try again.");
}
?>