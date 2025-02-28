<?php
session_set_cookie_params(0, '/');
session_start(); // ✅ Ensure session starts in the first line
require_once "../config/db.php"; // ✅ Connect to database

// **Modified: Ensure user is logged in and is a teacher OR admin**
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] !== "teacher" && $_SESSION["role"] !== "admin")) {
    die("Access denied! Please login first.");
}

$user_id = $_SESSION["user_id"]; // ✅ Read current user's ID

// 修复：检查 user_name 是否存在，如果不存在则尝试从 name 获取或从数据库获取
if (!isset($_SESSION["user_name"])) {
    if (isset($_SESSION["name"])) {
        $_SESSION["user_name"] = $_SESSION["name"];
    } else {
        // 如果 session 中没有用户名，则从数据库获取
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION["user_name"] = $user["name"];
        } else {
            $_SESSION["user_name"] = "Unknown User";
        }
    }
}
$user_name = $_SESSION["user_name"]; // 现在安全地读取用户名
$user_role = $_SESSION["role"]; // Get user's role

// ✅ **Query courses - for admin show all courses, for teacher show only their courses**
if ($user_role === "admin") {
    $stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name ASC");
    $stmt->execute([$user_id]);
}

// ✅ **Get query results**
$courses = $stmt->fetchAll();
$hasCourses = !empty($courses); // ✅ Check if there are courses

// Get notification priority options
$priorities = [
    ["value" => "normal", "label" => "Normal", "class" => "text-dark"],
    ["value" => "important", "label" => "Important", "class" => "text-warning"],
    ["value" => "urgent", "label" => "Urgent", "class" => "text-danger"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Announcement - CampusConnect</title>
    <!-- Using Bootstrap 5 CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Import Summernote rich text editor -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-light: #e7f0ff;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .announcement-form-container {
            max-width: 900px;
            margin: 2rem auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            padding: 1.5rem;
            border-bottom: none;
        }
        .card-header h4 {
            margin-bottom: 0;
            font-weight: 600;
        }
        .card-body {
            padding: 2rem;
        }
        .card-footer {
            background-color: white;
            border-top: 1px solid #eee;
            padding: 1.5rem 2rem;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #0a58ca;
            border-color: #0a58ca;
        }
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        .form-hint {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .course-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-light);
            color: var(--primary-color);
            border-radius: 50%;
            margin-right: 0.75rem;
        }
        .custom-option {
            display: flex;
            align-items: center;
        }
        .preview-box {
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            min-height: 100px;
            background-color: white;
        }
        .character-counter {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: right;
            margin-top: 0.5rem;
        }
        .alert-no-courses {
            border-left: 4px solid #ffc107;
        }
        .note-editor {
            border-radius: 0.5rem !important;
        }
        .page-title-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .badge-course-count {
            background-color: var(--primary-light);
            color: var(--primary-color);
            font-size: 0.85rem;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
        }
        .form-group.required .form-label:after {
            content: " *";
            color: red;
        }
        .admin-badge {
            background-color: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.7rem;
            margin-left: 0.5rem;
        }
        /* Animation effects */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Navigation bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-mortarboard-fill me-2"></i> CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../views/course_list.php">
                            <i class="bi bi-book me-1"></i> 
                            <?= $user_role === "admin" ? "All Courses" : "My Courses" ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-megaphone me-1"></i> Announcements
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="#"><i class="bi bi-plus-circle me-1"></i> Create New Announcement</a></li>
                            <li><a class="dropdown-item" href="../views/announcements_list.php"><i class="bi bi-list-ul me-1"></i> Announcement List</a></li>
                        </ul>
                    </li>
                    <?php if ($user_role === "admin"): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="../admin/admin_panel.php">
                            <i class="bi bi-gear-fill me-1"></i> Admin Panel
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> 
                            <?= htmlspecialchars($user_name) ?>
                            <?php if ($user_role === "admin"): ?>
                                <span class="admin-badge">ADMIN</span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../views/profile.php"><i class="bi bi-person me-1"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../modules/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container announcement-form-container fade-in">
        <!-- Page title -->
        <div class="page-title-wrapper">
            <h2><i class="bi bi-megaphone-fill me-2"></i> Create New Announcement</h2>
            <?php if ($hasCourses): ?>
                <span class="badge-course-count">
                    <i class="bi bi-book-fill me-1"></i> 
                    <?= $user_role === "admin" ? "Total" : "You have" ?> 
                    <?= count($courses) ?> courses
                </span>
            <?php endif; ?>
        </div>

        <!-- Main card -->
        <div class="card mb-4">
            <div class="card-header text-white">
                <h4>
                    <i class="bi bi-send-fill me-2"></i> Create Announcement
                    <?php if ($user_role === "admin"): ?>
                        <span class="admin-badge">ADMIN MODE</span>
                    <?php endif; ?>
                </h4>
                <p class="mb-0 mt-2 small">Publish important information, course updates or reminders for your students</p>
            </div>
            
            <div class="card-body">
                <?php if (!$hasCourses): ?>
                    <!-- No courses alert -->
                    <div class="alert alert-warning alert-no-courses">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                            <div>
                                <h5 class="alert-heading">No courses available</h5>
                                <?php if ($user_role === "admin"): ?>
                                    <p class="mb-0">No courses are available in the system. Please create courses first before publishing announcements.</p>
                                <?php else: ?>
                                    <p class="mb-0">You can publish announcements after courses are created or assigned to you. Please contact the administrator to arrange courses.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Announcement form -->
                    <form id="announcementForm" action="../modules/announcement_create.php" method="POST">
                        <!-- Teacher selection for admin user -->
                        <?php if ($user_role === "admin"): ?>
                            <div class="row mb-4">
                                <div class="col-md-6 form-group required">
                                    <label for="teacher_id" class="form-label">
                                        <i class="bi bi-person-badge-fill me-1"></i> Post as
                                    </label>
                                    <select name="teacher_id" id="teacher_id" class="form-select" required>
                                        <option value="<?= $user_id ?>" selected>
                                            <?= htmlspecialchars($user_name) ?> (Administrator)
                                        </option>
                                        <!-- Option to add teacher selection here if needed -->
                                    </select>
                                    <small class="form-hint">As an administrator, you're posting this announcement</small>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Course selection -->
                        <div class="row mb-4">
                            <div class="col-md-6 form-group required">
                                <label for="course_id" class="form-label">
                                    <i class="bi bi-book-fill me-1"></i> Select Course
                                </label>
                                <select name="course_id" id="course_id" class="form-select" required>
                                    <option value="" selected disabled>-- Please select a course --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>">
                                            <?= htmlspecialchars($course['course_name']) ?> 
                                            (<?= htmlspecialchars($course['course_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-hint">Select the target course for this announcement</small>
                            </div>
                            
                            <!-- Announcement priority -->
                            <div class="col-md-6 form-group">
                                <label for="priority" class="form-label">
                                    <i class="bi bi-flag-fill me-1"></i> Priority
                                </label>
                                <select name="priority" id="priority" class="form-select">
                                    <?php foreach ($priorities as $priority): ?>
                                        <option value="<?= $priority['value'] ?>" 
                                                class="<?= $priority['class'] ?>">
                                            <?= $priority['label'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-hint">Set the importance level of this announcement</small>
                            </div>
                        </div>

                        <!-- Announcement title -->
                        <div class="mb-4 form-group required">
                            <label for="title" class="form-label">
                                <i class="bi bi-card-heading me-1"></i> Announcement Title
                            </label>
                            <input type="text" name="title" id="title" class="form-control" 
                                   required maxlength="100" placeholder="Enter an attention-grabbing title">
                            <div class="d-flex justify-content-between">
                                <small class="form-hint">Clear and concise titles are more likely to catch students' attention</small>
                                <small class="character-counter"><span id="titleCounter">0</span>/100</small>
                            </div>
                        </div>

                        <!-- Announcement content -->
                        <div class="mb-4 form-group required">
                            <label for="content" class="form-label">
                                <i class="bi bi-file-text-fill me-1"></i> Announcement Content
                            </label>
                            <textarea name="content" id="content" class="form-control summernote" 
                                      required placeholder="Please describe the announcement content in detail..." rows="7"></textarea>
                            <small class="form-hint">You can add formatted text, lists, and simple tables</small>
                        </div>

                        <!-- Additional options -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="pin_announcement" name="pin_announcement" value="1">
                                    <label class="form-check-label" for="pin_announcement">
                                        <i class="bi bi-pin-angle-fill me-1"></i> Pin Announcement
                                    </label>
                                </div>
                                <small class="form-hint d-block mt-1">Pinned announcements will be displayed at the top of the course page</small>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="send_email" name="send_email" value="1">
                                    <label class="form-check-label" for="send_email">
                                        <i class="bi bi-envelope-fill me-1"></i> Also Send Email Notification
                                    </label>
                                </div>
                                <small class="form-hint d-block mt-1">Notify students via email to check this announcement</small>
                            </div>
                        </div>

                        <!-- Divider -->
                        <hr class="my-4">

                        <!-- Preview area -->
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-eye-fill me-1"></i> Announcement Preview
                            </label>
                            <div id="previewBox" class="preview-box">
                                <div class="text-muted text-center">
                                    <i class="bi bi-eye me-1"></i> Preview will be displayed here after you complete the form
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Card footer -->
            <div class="card-footer d-flex justify-content-between">
                <a href="../dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Return to Dashboard
                </a>
                
                <?php if ($hasCourses): ?>
                    <div>
                        <button type="button" id="previewBtn" class="btn btn-outline-primary me-2">
                            <i class="bi bi-eye me-1"></i> Preview
                        </button>
                        <button type="submit" form="announcementForm" class="btn btn-primary">
                            <i class="bi bi-send-fill me-1"></i> Publish Announcement
                        </button>
                    </div>
                <?php else: ?>
                    <?php if ($user_role === "admin"): ?>
                        <a href="../admin/courses_manage.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Create New Course
                        </a>
                    <?php else: ?>
                        <a href="../views/course_list.php" class="btn btn-primary">
                            <i class="bi bi-book me-1"></i> View My Courses
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Help card -->
        <div class="card bg-light">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-lightbulb-fill text-warning me-2"></i> Announcement Tips</h5>
                <ul class="mb-0">
                    <li>Use clear and concise titles to ensure students immediately understand the topic</li>
                    <li>Set "Important" or "Urgent" priority for critical information</li>
                    <li>Keep content detailed but not too long; highlight key information in bold or list format</li>
                    <li>Check "Also Send Email Notification" if students need to see the announcement immediately</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- JavaScript libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs5.min.js"></script>

    <script>
        $(document).ready(function() {
            <?php if ($hasCourses): ?>
                // Initialize rich text editor
                $('.summernote').summernote({
                    placeholder: 'Please enter announcement content...',
                    tabsize: 2,
                    height: 200,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'underline', 'clear']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['table', ['table']],
                        ['insert', ['link']],
                        ['view', ['fullscreen', 'help']]
                    ],
                    callbacks: {
                        onChange: function() {
                            updatePreview();
                        }
                    }
                });

                // Title character counter
                $('#title').on('input', function() {
                    let count = $(this).val().length;
                    $('#titleCounter').text(count);
                    
                    // Warning when approaching character limit
                    if (count > 80) {
                        $('#titleCounter').addClass('text-danger');
                    } else {
                        $('#titleCounter').removeClass('text-danger');
                    }
                    
                    updatePreview();
                });
                
                // Update preview
                function updatePreview() {
                    const title = $('#title').val();
                    const content = $('.summernote').summernote('code');
                    const courseId = $('#course_id').val();
                    const prioritySelect = document.getElementById('priority');
                    const priorityOption = prioritySelect.options[prioritySelect.selectedIndex];
                    const priorityClass = priorityOption ? priorityOption.className : '';
                    const priorityText = priorityOption ? priorityOption.text : '';
                    
                    if (title || content) {
                        let courseName = "Selected Course";
                        if (courseId) {
                            const courseSelect = document.getElementById('course_id');
                            const courseOption = courseSelect.options[courseSelect.selectedIndex];
                            courseName = courseOption.text;
                        }
                        
                        let priorityBadge = '';
                        if (priorityText && priorityText !== 'Normal') {
                            const badgeClass = priorityClass.replace('text-', 'bg-') || 'bg-secondary';
                            priorityBadge = `<span class="badge ${badgeClass} me-2">${priorityText}</span>`;
                        }
                        
                        const previewHtml = `
                            <div class="mb-1 small text-muted">Published to: ${courseName}</div>
                            <h4 class="mb-3">${priorityBadge}${title || '(No title)'}</h4>
                            <div class="preview-content">
                                ${content || '<p class="text-muted">(No content)</p>'}
                            </div>
                            <div class="mt-3 small text-muted">
                                <i class="bi bi-person-circle me-1"></i> Published by: ${<?= json_encode(htmlspecialchars($user_name)) ?>}
                                <?php if ($user_role === "admin"): ?>
                                <span class="badge admin-badge">ADMIN</span>
                                <?php endif; ?>
                                <span class="ms-3"><i class="bi bi-clock me-1"></i> Published time: ${new Date().toLocaleString()}</span>
                            </div>
                        `;
                        
                        $('#previewBox').html(previewHtml);
                    } else {
                        $('#previewBox').html('<div class="text-muted text-center"><i class="bi bi-eye me-1"></i> Preview will be displayed here after you complete the form</div>');
                    }
                }
                
                // Preview button
                $('#previewBtn').click(function() {
                    updatePreview();
                    $('html, body').animate({
                        scrollTop: $("#previewBox").offset().top - 100
                    }, 500);
                });

                // Form validation
                $('#announcementForm').on('submit', function(e) {
                    // Basic validation
                    const courseId = $('#course_id').val();
                    if (!courseId) {
                        e.preventDefault();
                        alert('Please select a course!');
                        $('#course_id').focus();
                        return false;
                    }

                    // Title validation
                    const title = $('#title').val().trim();
                    if (!title) {
                        e.preventDefault();
                        alert('Please enter an announcement title!');
                        $('#title').focus();
                        return false;
                    }

                    // Content validation
                    const content = $('.summernote').summernote('code').trim();
                    if (!content || content === '<p><br></p>') {
                        e.preventDefault();
                        alert('Please enter announcement content!');
                        $('.summernote').summernote('focus');
                        return false;
                    }
                    
                    return true;
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>