<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure the user is a teacher
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: ../login.php?error=unauthorized");
    exit;
}

// Process the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    if (!isset($_POST["submission_id"]) || !isset($_POST["feedback"])) {
        header("Location: ../views/assignments_list.php?error=missing_data");
        exit;
    }
    
    $submission_id = filter_var($_POST["submission_id"], FILTER_SANITIZE_NUMBER_INT);
    $feedback = trim($_POST["feedback"]);
    $notify_student = isset($_POST["notify_student"]) ? 1 : 0;
    
    // Check if submission exists and belongs to a course taught by this teacher
    try {
        $stmt = $pdo->prepare("SELECT s.id, s.assignment_id, a.course_id, c.teacher_id 
                              FROM submissions s 
                              JOIN assignments a ON s.assignment_id = a.id 
                              JOIN courses c ON a.course_id = c.id 
                              WHERE s.id = ? AND c.teacher_id = ?");
        $stmt->execute([$submission_id, $_SESSION["user_id"]]);
        $submission = $stmt->fetch();
        
        if (!$submission) {
            header("Location: ../views/assignments_list.php?error=not_found");
            exit;
        }
        
        // Begin transaction for atomicity
        $pdo->beginTransaction();
        
        // Update feedback
        $stmt = $pdo->prepare("UPDATE submissions SET feedback = ? WHERE id = ?");
        if (!$stmt->execute([$feedback, $submission_id])) {
            throw new Exception("Failed to update feedback");
        }
        
        // Record in activity log if the table exists
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
            if ($checkTable->rowCount() > 0) {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                                      VALUES (?, 'update', 'submission', ?, ?)");
                $details = json_encode(['type' => 'feedback', 'notify' => $notify_student]);
                $stmt->execute([$_SESSION["user_id"], $submission_id, $details]);
                
                // Send notification if requested
                if ($notify_student) {
                    // Get student email
                    $stmt = $pdo->prepare("SELECT u.email, u.name, a.title 
                                         FROM submissions s 
                                         JOIN users u ON s.student_id = u.id 
                                         JOIN assignments a ON s.assignment_id = a.id 
                                         WHERE s.id = ?");
                    $stmt->execute([$submission_id]);
                    $student = $stmt->fetch();
                    
                    if ($student) {
                        // In a production environment, you would send an actual email here
                        // For now, we'll just log it
                        $logStmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                                                 VALUES (?, 'notify', 'student', ?, ?)");
                        $notifyDetails = json_encode([
                            'email' => $student['email'],
                            'subject' => 'Feedback on ' . $student['title'],
                            'message' => 'Your submission has received feedback.'
                        ]);
                        $logStmt->execute([$_SESSION["user_id"], $submission_id, $notifyDetails]);
                    }
                }
            }
        } catch (PDOException $e) {
            // Just log the error, don't fail the entire operation because of logging issues
            error_log("Error logging activity: " . $e->getMessage());
        }
        
        // Commit the transaction
        $pdo->commit();
        
        // Redirect back to the submissions list with success message
        header("Location: ../views/view_submissions.php?assignment_id=" . $submission['assignment_id'] . "&success=feedback_saved");
    } catch (Exception $e) {
        // Rollback any changes if there was an error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Redirect with error message
        header("Location: ../views/view_submissions.php?assignment_id=" . 
               (isset($submission['assignment_id']) ? $submission['assignment_id'] : 0) . 
               "&error=save_failed&message=" . urlencode($e->getMessage()));
    }
} else {
    // If not a POST request, redirect to the home page
    header("Location: ../views/dashboard.php");
}
exit;
?>