<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upload Course Resource - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
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
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        #file-input {
            display: none;
        }
        .resource-type-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .progress {
            height: 0.5rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <?php
    session_set_cookie_params(0, '/');
    require_once "../config/db.php";
    session_start();
    
    // Check if the user is logged in and is a teacher
    if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
        header("Location: ../login.php");
        exit;
    }
    
    // Get the teacher's course list
    $stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses WHERE teacher_id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
    $courses = $stmt->fetchAll();
    
    // Check if there are any courses
    $has_courses = count($courses) > 0;
    ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="course_resources.php">Course Resource Management</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Upload Resource</li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="card border-0 p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-2 fw-normal">
                                <i class="bi bi-cloud-upload-fill text-primary me-2"></i>Upload Course Resource
                            </h1>
                            <p class="text-muted">Share learning materials with your students</p>
                        </div>
                        <div class="d-flex">
                            <a href="course_resources.php" class="btn btn-outline-primary me-2">
                                <i class="bi bi-folder2-open me-1"></i>Resource Management
                            </a>
                            <a href="../dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-house-door me-1"></i>Dashboard
                            </a>
                        </div>
                    </div>

                    <?php if (!$has_courses): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        You currently do not have any courses. Please create a course before uploading resources.
                        <a href="create_course.php" class="alert-link">Create a New Course</a>
                    </div>
                    <?php else: ?>

                    <ul class="nav nav-pills mb-4" id="resourceTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="file-tab" data-bs-toggle="pill" data-bs-target="#file" type="button" role="tab" aria-selected="true">
                                <i class="bi bi-file-earmark me-1"></i>File
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="link-tab" data-bs-toggle="pill" data-bs-target="#link" type="button" role="tab" aria-selected="false">
                                <i class="bi bi-link me-1"></i>Link
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="resourceTabContent">
                        <!-- File Upload Form -->
                        <div class="tab-pane fade show active" id="file" role="tabpanel" aria-labelledby="file-tab">
                            <form action="../modules/resource_upload.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="file-form">
                                <input type="hidden" name="resource_type" value="file">
                                
                                <div class="mb-3">
                                    <label for="course_id" class="form-label">
                                        <i class="bi bi-book me-1"></i>Select Course
                                    </label>
                                    <select name="course_id" id="course_id" class="form-select" required>
                                        <option value="">-- Select a Course --</option>
                                        <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>">
                                            <?= htmlspecialchars($course['course_name']) ?> 
                                            (<?= htmlspecialchars($course['course_code']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a course</div>
                                </div>

                                <div class="mb-3">
                                    <label for="resource_name" class="form-label">
                                        <i class="bi bi-tag me-1"></i>Resource Name
                                    </label>
                                    <input type="text" name="resource_name" id="resource_name" class="form-control" 
                                           placeholder="Provide a descriptive name for the resource" required>
                                    <div class="invalid-feedback">Please provide a resource name</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="resource_description" class="form-label">
                                        <i class="bi bi-card-text me-1"></i>Resource Description (Optional)
                                    </label>
                                    <textarea name="resource_description" id="resource_description" class="form-control" 
                                              rows="3" placeholder="Add a brief description about this resource"></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label d-block">
                                        <i class="bi bi-file-earmark-arrow-up me-1"></i>Upload File
                                    </label>
                                    <div class="upload-area" id="upload-area">
                                        <div id="resource-type-icon" class="resource-type-icon text-primary">
                                            <i class="bi bi-file-earmark"></i>
                                        </div>
                                        <p class="mb-1">Click to upload or drag and drop a file here</p>
                                        <p class="text-muted small mb-0">
                                            Supported formats: Documents, Spreadsheets, Slides, PDFs, and Multimedia Files (Max: 50MB)
                                        </p>
                                        <div id="file-preview" class="mt-3"></div>
                                        <div class="progress d-none" id="upload-progress">
                                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" 
                                                 role="progressbar" style="width: 0%"></div>
                                        </div>
                                    </div>
                                    <input type="file" name="file" id="file-input" required>
                                    <div class="invalid-feedback">Please select a file to upload</div>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" name="is_visible" id="is_visible" checked>
                                    <label class="form-check-label" for="is_visible">
                                        Publish to Students Immediately
                                    </label>
                                    <div class="form-text">If unchecked, the resource will be saved but not visible to students</div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary" id="submit-btn">
                                        <i class="bi bi-cloud-upload me-2"></i>Upload Resource
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Link Resource Form -->
                        <div class="tab-pane fade" id="link" role="tabpanel" aria-labelledby="link-tab">
                            <form action="../modules/resource_upload.php" method="POST" class="needs-validation" novalidate id="link-form">
                                <input type="hidden" name="resource_type" value="link">
                                
                                <div class="mb-3">
                                    <label for="link_course_id" class="form-label">
                                        <i class="bi bi-book me-1"></i>Select Course
                                    </label>
                                    <select name="course_id" id="link_course_id" class="form-select" required>
                                        <option value="">-- Select a Course --</option>
                                        <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>">
                                            <?= htmlspecialchars($course['course_name']) ?> 
                                            (<?= htmlspecialchars($course['course_code']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a course</div>
                                </div>

                                <div class="mb-3">
                                    <label for="link_resource_name" class="form-label">
                                        <i class="bi bi-tag me-1"></i>Resource Name
                                    </label>
                                    <input type="text" name="resource_name" id="link_resource_name" class="form-control" 
                                           placeholder="Provide a descriptive name for the link resource" required>
                                    <div class="invalid-feedback">Please provide a resource name</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="resource_url" class="form-label">
                                        <i class="bi bi-link-45deg me-1"></i>Resource URL
                                    </label>
                                    <input type="url" name="resource_url" id="resource_url" class="form-control" 
                                           placeholder="https://example.com/resource" required>
                                    <div class="invalid-feedback">Please enter a valid URL (starting with http:// or https://)</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="link_resource_description" class="form-label">
                                        <i class="bi bi-card-text me-1"></i>Resource Description (Optional)
                                    </label>
                                    <textarea name="resource_description" id="link_resource_description" class="form-control" 
                                              rows="3" placeholder="Add a brief description about this link resource"></textarea>
                                </div>
                                
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" name="is_visible" id="link_is_visible" checked>
                                    <label class="form-check-label" for="link_is_visible">
                                        Publish to Students Immediately
                                    </label>
                                    <div class="form-text">If unchecked, the resource will be saved but not visible to students</div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-link-45deg me-2"></i>Add Link Resource
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form Validation
        (() => {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    } else if (form.id === 'file-form') {
                        // Show submitting state
                        const submitBtn = document.querySelector('#submit-btn');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Uploading...';
                        
                        // Show progress bar
                        document.getElementById('upload-progress').classList.remove('d-none');
                        simulateProgress();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // File Upload Area Interaction
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        const filePreview = document.getElementById('file-preview');
        const resourceTypeIcon = document.getElementById('resource-type-icon');

        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // Drag and Drop Functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#0d6efd';
            uploadArea.style.backgroundColor = 'rgba(13, 110, 253, 0.05)';
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = '';
            uploadArea.style.backgroundColor = '';
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '';
            uploadArea.style.backgroundColor = '';
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateFilePreview(e.dataTransfer.files[0]);
            }
        });

        // Show File Preview After Selection
        fileInput.addEventListener('change', (e) => {
            if (fileInput.files.length) {
                updateFilePreview(fileInput.files[0]);
            }
        });

        function updateFilePreview(file) {
            // Update Resource Name
            const resourceNameInput = document.getElementById('resource_name');
            if (!resourceNameInput.value) {
                resourceNameInput.value = file.name.split('.')[0];
            }
            
            // Set File Icon
            const extension = file.name.split('.').pop().toLowerCase();
            let iconClass = 'bi-file-earmark';
            
            // Set Different Icons Based on File Type
            if (['doc', 'docx'].includes(extension)) {
                iconClass = 'bi-file-earmark-word';
            } else if (['xls', 'xlsx'].includes(extension)) {
                iconClass = 'bi-file-earmark-excel';
            } else if (['ppt', 'pptx'].includes(extension)) {
                iconClass = 'bi-file-earmark-slides';
            } else if (extension === 'pdf') {
                iconClass = 'bi-file-earmark-pdf';
            } else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                iconClass = 'bi-file-earmark-image';
            } else if (['mp4', 'mov', 'avi'].includes(extension)) {
                iconClass = 'bi-file-earmark-play';
            } else if (['mp3', 'wav'].includes(extension)) {
                iconClass = 'bi-file-earmark-music';
            } else if (extension === 'zip') {
                iconClass = 'bi-file-earmark-zip';
            }
            
            resourceTypeIcon.innerHTML = `<i class="bi ${iconClass}"></i>`;
            
            // Display File Information
            const fileSize = formatFileSize(file.size);
            filePreview.innerHTML = `
                <div class="alert alert-primary alert-dismissible fade show" role="alert">
                    <i class="bi ${iconClass} me-2"></i>
                    <strong>${file.name}</strong> (${fileSize})
                    <button type="button" class="btn-close" onclick="clearFile(event)"></button>
                </div>
            `;
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function clearFile(e) {
            e.stopPropagation();
            fileInput.value = '';
            filePreview.innerHTML = '';
            resourceTypeIcon.innerHTML = '<i class="bi bi-file-earmark"></i>';
        }

        // Simulate Upload Progress
        function simulateProgress() {
            const progressBar = document.querySelector('.progress-bar');
            let width = 0;
            
            const interval = setInterval(() => {
                if (width >= 90) {
                    clearInterval(interval);
                } else {
                    width += Math.random() * 10;
                    progressBar.style.width = width + '%';
                }
            }, 300);
        }
    </script>
</body>
</html>