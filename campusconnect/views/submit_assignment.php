<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submit Assignment - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .form-control:focus, .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 2rem 1rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #198754;
            background-color: rgba(25, 135, 84, 0.05);
        }
        #file-input {
            display: none;
        }
        #file-name {
            margin-top: 1rem;
            font-weight: 500;
        }
        .progress {
            display: none;
            height: 0.5rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <?php
    require_once "../config/db.php";
    session_set_cookie_params(0, '/');
    session_start();
    
    // Check if the user is logged in
    if (!isset($_SESSION["user_id"])) {
        header("Location: ../login.php");
        exit;
    }
    ?>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div class="card border-0 p-4">
                    <div class="text-center mb-4">
                        <h1 class="h3 mb-3 fw-normal">
                            <i class="bi bi-cloud-upload-fill text-success me-2"></i>Submit Assignment
                        </h1>
                        <p class="text-muted">Please select the assignment and upload your file</p>
                    </div>

                    <form action="../modules/submission_upload.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="submission-form">
                        <div class="mb-4">
                            <label for="assignment_id" class="form-label">
                                <i class="bi bi-file-earmark-text me-1"></i> Select Assignment
                            </label>
                            <select name="assignment_id" id="assignment_id" class="form-select" required>
                                <option value="">Select an assignment</option>
                                <?php
                                try {
                                    // Debug current time
                                    $current_date = date('Y-m-d H:i:s');
                                    echo "<!-- Debug: Current time $current_date -->";
                                    
                                    // Modify query to check if the 'visible' column exists
                                    $stmt = $pdo->prepare("
                                        SHOW COLUMNS FROM assignments LIKE 'visible'
                                    ");
                                    $stmt->execute();
                                    $visible_exists = $stmt->rowCount() > 0;
                                    
                                    // Build query based on whether 'visible' column exists
                                    if ($visible_exists) {
                                        $stmt = $pdo->prepare("
                                            SELECT id, title, due_date 
                                            FROM assignments 
                                            WHERE visible = 1 AND due_date > ?
                                            ORDER BY due_date ASC
                                        ");
                                        $stmt->execute([$current_date]);
                                    } else {
                                        // If 'visible' column does not exist, filter only by due date
                                        $stmt = $pdo->prepare("
                                            SELECT id, title, due_date 
                                            FROM assignments 
                                            WHERE due_date > ?
                                            ORDER BY due_date ASC
                                        ");
                                        $stmt->execute([$current_date]);
                                    }
                                    
                                    if ($stmt->rowCount() > 0) {
                                        while ($assignment = $stmt->fetch()) {
                                            // Calculate due date
                                            $due_date = new DateTime($assignment['due_date']);
                                            $now = new DateTime();
                                            $interval = $now->diff($due_date);
                                            $days_left = $interval->days;
                                            $hours_left = $interval->h;
                                            
                                            // Set option with due date information
                                            $due_info = "";
                                            if ($days_left > 0) {
                                                $due_info = " ({$days_left} days left)";
                                            } else {
                                                $due_info = " ({$hours_left} hours left)";
                                            }
                                            
                                            echo "<option value='{$assignment['id']}'>{$assignment['title']}{$due_info}</option>";
                                        }
                                    } else {
                                        echo "<option value=''>No assignments available for submission</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Failed to load assignments: " . $e->getMessage() . "</option>";
                                }
                                ?>
                            </select>
                            <div class="invalid-feedback">Please select an assignment</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="bi bi-paperclip me-1"></i> Upload File
                            </label>
                            <div class="upload-area" id="upload-area">
                                <i class="bi bi-file-earmark-arrow-up fs-1 text-success"></i>
                                <p class="mt-2 mb-0">Click to upload or drag and drop a file here</p>
                                <p class="text-muted small">Supported formats: PDF, DOC, DOCX, ZIP (Max: 10MB)</p>
                                <div id="file-name"></div>
                                <div class="progress mt-3">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                            <input type="file" name="file" id="file-input" required>
                            <div class="invalid-feedback">Please upload your assignment file</div>
                        </div>

                        <div class="mb-3">
                            <label for="comment" class="form-label">
                                <i class="bi bi-chat-left-text me-1"></i> Comment (Optional)
                            </label>
                            <textarea name="comment" id="comment" class="form-control" rows="3" placeholder="Add any notes or instructions about this submission"></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success" id="submit-btn">
                                <i class="bi bi-check-circle-fill me-2"></i>Submit Assignment
                            </button>
                            <a href="../dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house-door-fill me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form validation
        (() => {
            'use strict'
            const form = document.querySelector('#submission-form');
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    // Show submitting state
                    const submitBtn = document.querySelector('#submit-btn');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
                    
                    // Show progress bar animation
                    document.querySelector('.progress').style.display = 'block';
                    simulateProgress();
                }
                form.classList.add('was-validated');
            }, false);
        })();

        // File upload area interaction
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        const fileName = document.getElementById('file-name');

        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('border-success');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('border-success');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-success');
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFileName(e.dataTransfer.files[0].name);
            }
        });

        // Show file name after selection
        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length) {
                updateFileName(fileInput.files[0].name);
            }
        });

        function updateFileName(name) {
            fileName.innerHTML = `
                <div class="alert alert-success mt-2">
                    <i class="bi bi-file-earmark-check me-2"></i>
                    <strong>${name}</strong>
                    <button type="button" class="btn-close float-end" onclick="clearFile(event)"></button>
                </div>
            `;
        }

        function clearFile(e) {
            e.stopPropagation();
            fileInput.value = '';
            fileName.innerHTML = '';
        }

        // Simulate upload progress
        function simulateProgress() {
            const progressBar = document.querySelector('.progress-bar');
            let width = 0;
            
            const interval = setInterval(() => {
                if (width >= 90) {
                    clearInterval(interval);
                } else {
                    width += Math.random() * 10;
                    progressBar.style.width = width + '%';
                    progressBar.setAttribute('aria-valuenow', width);
                }
            }, 300);
        }
    </script>
</body>
</html>