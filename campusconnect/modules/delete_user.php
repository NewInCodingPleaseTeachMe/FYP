<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Prepare variables for response message
$message = "";
$alertClass = "";
$isSuccess = false;

// Check user permissions
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    die("
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Access Denied</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css'>
    </head>
    <body class='bg-light'>
        <div class='container mt-5'>
            <div class='row justify-content-center'>
                <div class='col-md-6'>
                    <div class='card shadow-sm border-0'>
                        <div class='card-body p-4 text-center'>
                            <i class='bi bi-shield-lock text-danger' style='font-size: 3rem;'></i>
                            <h2 class='mt-3'>Access Denied</h2>
                            <p class='text-muted'>Please log in with an admin account.</p>
                            <a href='../index.php' class='btn btn-primary mt-3'>Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    ");
}

if (isset($_GET["id"])) {
    $user_id = $_GET["id"];

    // Ensure that an admin cannot delete themselves
    if ($user_id == $_SESSION["user_id"]) {
        $message = "You cannot delete yourself!";
        $alertClass = "alert-danger";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $message = "User has been deleted successfully!";
            $alertClass = "alert-success";
            $isSuccess = true;
        } else {
            $message = "Deletion failed, please try again!";
            $alertClass = "alert-danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="bi bi-person-x me-2"></i>Delete User</h4>
                    </div>
                    <div class="card-body p-4 text-center">
                        <?php if (!empty($message)): ?>
                            <div class="mb-4">
                                <i class="bi <?php echo $isSuccess ? 'bi-check-circle text-success' : 'bi-exclamation-triangle text-danger'; ?>" style="font-size: 3rem;"></i>
                                <h4 class="mt-3"><?php echo $message; ?></h4>
                            </div>
                            
                            <div class="d-grid gap-2 col-md-8 mx-auto">
                                <a href="../views/user_list.php" class="btn btn-primary">
                                    <i class="bi bi-list-ul me-2"></i>Return to User List
                                </a>
                                
                                <?php if (!$isSuccess): ?>
                                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Go Back
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                No user ID was specified for deletion.
                            </div>
                            <a href="../views/user_list.php" class="btn btn-primary">
                                <i class="bi bi-list-ul me-2"></i>Return to User List
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>