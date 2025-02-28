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
    // Validate input
    if (!isset($_POST["assignment_id"]) || !isset($_POST["submissions"]) || !isset($_POST["grades"])) {
        header("Location: ../views/assignments_list.php?error=missing_data");
        exit;
    }
    
    $assignment_id = filter_var($_POST["assignment_id"], FILTER_SANITIZE_NUMBER_INT);
    $submissions = $_POST["submissions"]; // Array of submission IDs to grade
    $grades = $_POST["grades"]; // Array of grades keyed by submission ID
    $batch_feedback = isset($_POST["batch_feedback"]) ? trim($_POST["batch_feedback"]) : "";
    
    // Verify that the assignment belongs to a course taught by this teacher
    $stmt = $pdo->prepare("SELECT a.id, a.course_id, c.teacher_id 
                          FROM assignments a 
                          JOIN courses c ON a.course_id = c.id 
                          WHERE a.id = ? AND c.teacher_id = ?");
    $stmt->execute([$assignment_id, $_SESSION["user_id"]]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        header("Location: ../views/assignments_list.php?error=not_found");
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        $success_count = 0;
        
        // Update each submission
        foreach ($submissions as $submission_id) {
            // Clean the submission_id
            $submission_id = filter_var($submission_id, FILTER_SANITIZE_NUMBER_INT);
            
            // Check if there's a grade for this submission
            if (isset($grades[$submission_id]) && $grades[$submission_id] !== '') {
                $grade = filter_var($grades[$submission_id], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
                // Update the grade and feedback
                $stmt = $pdo->prepare("UPDATE submissions 
                                       SET grade = ?, 
                                           feedback = CASE 
                                                        WHEN feedback IS NULL OR feedback = '' THEN ? 
                                                        WHEN ? != '' THEN CONCAT(feedback, '\n\n', ?)
                                                        ELSE feedback 
                                                     END,
                                           graded_at = NOW() 
                                       WHERE id = ? AND assignment_id = ?");
                $stmt->execute([$grade, $batch_feedback, $batch_feedback, $batch_feedback, $submission_id, $assignment_id]);
                
                if ($stmt->rowCount() > 0) {
                    $success_count++;
                    
                    // Log the activity
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details) 
                                          VALUES (?, 'grade', 'submission', ?, ?)");
                    $details = json_encode(['grade' => $grade, 'batch' => true]);
                    $stmt->execute([$_SESSION["user_id"], $submission_id, $details]);
                }
            }
        }
        
        // Commit the transaction
        $pdo->commit();
        
        // Redirect with success message
        header("Location: ../views/view_submissions.php?assignment_id=" . $assignment_id . "&success=batch_graded&count=" . $success_count);
    } catch (Exception $e) {
        // Rollback the transaction on error
        $pdo->rollBack();
        
        // Redirect with error message
        header("Location: ../views/view_submissions.php?assignment_id=" . $assignment_id . "&error=batch_failed&message=" . urlencode($e->getMessage()));
    }
} else {
    // If not a POST request, redirect to the home page
    header("Location: ../views/dashboard.php");
}
exit;
?>