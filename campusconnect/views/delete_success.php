<?php
session_set_cookie_params(0, '/');
session_start();

// Ensure user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["deleted_assignment"])) {
    header("Location: ../login.php");
    exit("Access denied!");
}

// Get deleted assignment information
$assignment = $_SESSION["deleted_assignment"];

// Clear session data to avoid showing the same data on refresh
unset($_SESSION["deleted_assignment"]);

// Set automatic redirect
$redirect_after = 5; // Redirect after 5 seconds
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Successful - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .success-icon {
            font-size: 4rem;
            color: #dc3545;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .countdown {
            font-weight: bold;
            color: #dc3545;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 1rem;
        }
        
        .card-header {
            border-radius: 1rem 1rem 0 0 !important;
        }
        
        .info-row {
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-mortarboard-fill me-2"></i>CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="assignments_list.php">
                            <i class="bi bi-journal-check me-1"></i>Assignments List
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="../logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header bg-danger text-white text-center py-4">
                        <i class="bi bi-trash-fill success-icon d-block mb-3"></i>
                        <h2 class="mb-0">Assignment Successfully Deleted</h2>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-warning mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            This assignment and related submissions have been permanently deleted and cannot be recovered.
                        </div>
                        
                        <div class="info-row">
                            <div class="row">
                                <div class="col-md-4 text-muted">
                                    <i class="bi bi-bookmark-fill me-2"></i>Assignment Title
                                </div>
                                <div class="col-md-8 fw-bold">
                                    <?= htmlspecialchars($assignment["title"]) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="row">
                                <div class="col-md-4 text-muted">
                                    <i class="bi bi-book-fill me-2"></i>Course
                                </div>
                                <div class="col-md-8">
                                    <?= htmlspecialchars($assignment["course_name"]) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="row">
                                <div class="col-md-4 text-muted">
                                    <i class="bi bi-calendar-x-fill me-2"></i>Deletion Time
                                </div>
                                <div class="col-md-8">
                                    <?= $assignment["deleted_at"] ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-row">
                            <div class="row">
                                <div class="col-md-4 text-muted">
                                    <i class="bi bi-person-fill me-2"></i>Deleted By
                                </div>
                                <div class="col-md-8">
                                    <?= htmlspecialchars($assignment["deleted_by"]) ?>
                                    <span class="badge bg-primary ms-2"><?= ucfirst($assignment["role"]) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                Page will automatically redirect to assignments list in <span id="countdown" class="countdown"><?= $redirect_after ?></span> seconds
                            </p>
                            <div class="progress mb-4" style="height: 5px;">
                                <div id="progress-bar" class="progress-bar bg-danger" role="progressbar" style="width: 0%"></div>
                            </div>
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <a href="assignments_list.php" class="btn btn-primary">
                                    <i class="bi bi-list-check me-2"></i>Return to Assignments List
                                </a>
                                <a href="../dashboard.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-speedometer2 me-2"></i>Return to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Countdown and auto-redirect script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let count = <?= $redirect_after ?>;
            const countdownDisplay = document.getElementById('countdown');
            const progressBar = document.getElementById('progress-bar');
            
            const interval = setInterval(function() {
                count--;
                countdownDisplay.textContent = count;
                
                // Update progress bar
                const percentage = (<?= $redirect_after ?> - count) / <?= $redirect_after ?> * 100;
                progressBar.style.width = percentage + '%';
                
                if (count <= 0) {
                    clearInterval(interval);
                    window.location.href = 'assignments_list.php';
                }
            }, 1000);
        });
    </script>
</body>
</html>