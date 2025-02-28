<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Verify if user is logged in
if (!isset($_SESSION["user_id"])) {
    die('<div class="alert alert-danger">Please login before posting!</div>');
}

$user_id = $_SESSION["user_id"];
$user_name = $_SESSION["user_name"] ?? "User";
$role = $_SESSION["role"] ?? "student";

// 检查权限：只允许老师和管理员创建帖子
if ($role != "teacher" && $role != "admin") {
    header("Location: ../views/forum.php?error=permission_denied");
    exit;
}

// Get specific course if ID provided in URL
$specific_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;

// Get all courses
try {
    // Adjust query based on user role
    if ($role == "admin") {
        // Administrators can see all courses
        $stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses ORDER BY course_name ASC");
        $stmt->execute();
    } elseif ($role == "teacher") {
        // Teachers can only see courses they teach
        $stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name ASC");
        $stmt->execute([$user_id]);
    } else {
        // Students can only see courses they are enrolled in
        $stmt = $pdo->prepare("SELECT c.id, c.course_name, c.course_code 
                              FROM courses c 
                              JOIN enrollments e ON c.id = e.course_id 
                              WHERE e.student_id = ? 
                              ORDER BY c.course_name ASC");
        $stmt->execute([$user_id]);
    }
    
    $courses = $stmt->fetchAll();
    $hasCourses = !empty($courses);
} catch (PDOException $e) {
    $courses = [];
    $hasCourses = false;
}

// Check if specific course exists in user's available courses
$courseFound = false;
if ($specific_course_id && $hasCourses) {
    foreach ($courses as $course) {
        if ($course['id'] == $specific_course_id) {
            $courseFound = true;
            $selectedCourse = $course;
            break;
        }
    }
}

// Get all categories
try {
    $stmt = $pdo->prepare("SELECT id, category_name FROM forum_categories ORDER BY category_name ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Post - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- TinyMCE Editor -->
    <script src="https://cdn.tiny.cloud/1/x2r18e54wtbge9t83bqb22o4zyk63uh7lcfo9p53pld3scjj/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-dark: #0a58ca;
            --primary-light: #e7f0ff;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --forum-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--forum-bg);
            color: #333;
        }
        
        .forum-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem 15px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #0d6efd, #0a58ca);
            color: white;
            border-bottom: none;
            padding: 1.25rem 1.5rem;
        }
        
        .card-header h5 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .form-group.required .form-label:after {
            content: " *";
            color: red;
        }
        
        /* Tag styles */
        .post-tag {
            display: inline-block;
            padding: 0.4rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
            border: 1px solid #dee2e6;
            color: #6c757d;
            background-color: white;
        }
        
        .post-tag.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .post-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Preview area */
        .preview-section {
            background-color: var(--primary-light);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            display: none;
        }
        
        .preview-post {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.05);
        }
        
        .preview-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .preview-body {
            padding: 1.5rem;
        }
        
        .preview-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-dark);
        }
        
        .tox-tinymce {
            border-radius: 8px !important;
            border-color: #dee2e6 !important;
        }
        
        /* Animation */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Avatar */
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        /* Navigation bar */
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
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
                            <i class="bi bi-book me-1"></i> Courses
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-chat-dots me-1"></i> Forum
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../views/forum.php"><i class="bi bi-list-ul me-1"></i> Forum Home</a></li>
                            <li><a class="dropdown-item active" href="#"><i class="bi bi-plus-circle me-1"></i> Create New Post</a></li>
                            <li><a class="dropdown-item" href="../views/my_posts.php"><i class="bi bi-person me-1"></i> My Posts</a></li>
                        </ul>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($user_name) ?>
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

    <div class="forum-container">
        <!-- Page title -->
        <div class="page-header">
            <h2><i class="bi bi-pencil-square me-2"></i> Create New Post</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="../views/forum.php">Forum</a></li>
                    <?php if ($courseFound): ?>
                    <li class="breadcrumb-item"><a href="../views/discussion.php?course_id=<?= $specific_course_id ?>"><?= htmlspecialchars($selectedCourse['course_name']) ?></a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active">Create Post</li>
                </ol>
            </nav>
        </div>

        <?php if (!$hasCourses): ?>
            <!-- No courses alert -->
            <div class="alert alert-warning">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>
                        <h5 class="alert-heading">You don't have any courses to post in</h5>
                        <p class="mb-0">Please enroll in or create a course before posting.</p>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="../views/course_list.php" class="btn btn-primary">
                        <i class="bi bi-book me-1"></i> Browse Courses
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Main card -->
            <div class="card mb-4 fade-in">
                <div class="card-header">
                    <h5><i class="bi bi-pencil me-2"></i> Write Post</h5>
                    <p class="mb-0 mt-2 small">Share your questions, insights, or ideas</p>
                </div>
                
                <div class="card-body">
                    <form id="postForm" action="../modules/post_create.php" method="POST">
                        <div class="row">
                            <!-- Left column -->
                            <div class="col-md-8">
                                <!-- Post title -->
                                <div class="mb-4 form-group required">
                                    <label for="title" class="form-label">
                                        <i class="bi bi-card-heading me-1"></i> Title
                                    </label>
                                    <input type="text" name="title" id="title" class="form-control" 
                                           required maxlength="150" placeholder="Enter an engaging title...">
                                    <div class="d-flex justify-content-between">
                                        <div class="form-text">A good title will attract more discussion</div>
                                        <div class="form-text"><span id="titleCounter">0</span>/150</div>
                                    </div>
                                </div>

                                <!-- Post content -->
                                <div class="mb-4 form-group required">
                                    <label for="content" class="form-label">
                                        <i class="bi bi-file-earmark-text me-1"></i> Content
                                    </label>
                                    <textarea name="content" id="content" class="form-control"></textarea>
                                    <div class="form-text">Describe your question or viewpoint in detail. You can add formatted text, images, and links</div>
                                </div>
                            </div>

                            <!-- Right column -->
                            <div class="col-md-4">
                                <!-- Course selection -->
                                <div class="mb-4 form-group required">
                                    <label for="course_id" class="form-label">
                                        <i class="bi bi-book me-1"></i> Related Course
                                    </label>
                                    <select name="course_id" id="course_id" class="form-select" required>
                                        <option value="" disabled <?= !$courseFound ? 'selected' : '' ?>>-- Select Course --</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?= $course['id'] ?>" <?= $courseFound && $course['id'] == $specific_course_id ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($course['course_name']) ?> 
                                                <?= !empty($course['course_code']) ? "(".htmlspecialchars($course['course_code']).")" : "" ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Select the course this post belongs to</div>
                                </div>

                             <!-- Category selection -->
                             <div class="mb-4">
                                    <label for="category_id" class="form-label">
                                        <i class="bi bi-folder me-1"></i> Post Category
                                    </label>
                                    <select name="category_id" id="category_id" class="form-select">
                                        <option value="">-- Select Category (Optional) --</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['id'] ?>">
                                                <?= htmlspecialchars($category['category_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Choose a category that best describes your post content</div>
                                </div>

                                <!-- Tag selection -->
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="bi bi-tags me-1"></i> Tags
                                    </label>
                                    <div>
                                        <span class="post-tag" data-value="Help Request">Help Request</span>
                                        <span class="post-tag" data-value="Study Materials">Study Materials</span>
                                        <span class="post-tag" data-value="Course Discussion">Course Discussion</span>
                                        <span class="post-tag" data-value="Assignment">Assignment</span>
                                        <span class="post-tag" data-value="Exam">Exam</span>
                                        <span class="post-tag" data-value="Experience Sharing">Experience Sharing</span>
                                    </div>
                                    <input type="hidden" name="tags" id="post_tags" value="">
                                    <div class="form-text">Selecting appropriate tags makes your post easier to find</div>
                                </div>

                                <!-- Posting options -->
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title"><i class="bi bi-gear me-1"></i> Posting Options</h6>
                                        
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="is_anonymous" name="is_anonymous" value="1">
                                            <label class="form-check-label" for="is_anonymous">
                                                Post Anonymously
                                            </label>
                                        </div>
                                        <div class="form-text mb-3">Anonymous posts will not display your username</div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="allow_comments" name="allow_comments" value="1" checked>
                                            <label class="form-check-label" for="allow_comments">
                                                Allow Comments
                                            </label>
                                        </div>
                                        <div class="form-text">Unchecking will prevent other users from commenting</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview area -->
                        <div class="preview-section" id="previewSection">
                            <div class="preview-title"><i class="bi bi-eye me-2"></i> Post Preview</div>
                            <div class="preview-post">
                                <div class="preview-header">
                                    <h4 id="previewTitle">Post Title</h4>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar me-2" id="previewAvatar">
                                                <?= strtoupper(substr($user_name, 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div class="fw-medium" id="previewAuthor"><?= htmlspecialchars($user_name) ?></div>
                                                <div class="small text-muted">Just posted · <span id="previewCourse">Selected course</span></div>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge bg-primary me-1" id="previewCategory">Category</span>
                                            <div id="previewTags" class="d-inline"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="preview-body">
                                    <div id="previewContent">
                                        Post content will be displayed here...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form bottom buttons -->
                        <div class="d-flex justify-content-between mt-4">
                            <div>
                                <?php if ($courseFound): ?>
                                <a href="../views/discussion.php?course_id=<?= $specific_course_id ?>" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-arrow-left me-1"></i> Return to Discussion
                                </a>
                                <?php else: ?>
                                <a href="../views/forum.php" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-arrow-left me-1"></i> Return to Forum
                                </a>
                                <?php endif; ?>
                            </div>
                            <div>
                                <button type="button" id="previewBtn" class="btn btn-outline-primary me-2">
                                    <i class="bi bi-eye me-1"></i> Preview
                                </button>
                                <button type="submit" id="submitBtn" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i> Post
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tips card -->
            <div class="card bg-light fade-in">
                <div class="card-body">
                    <h5><i class="bi bi-lightbulb me-2 text-warning"></i> Posting Tips</h5>
                    <ul class="mb-0">
                        <li><strong>Clear title:</strong> Use a concise and clear title that expresses your question or topic</li>
                        <li><strong>Detailed description:</strong> Provide sufficient background information and details to make it easier for others to understand and respond</li>
                        <li><strong>Appropriate tags:</strong> Use accurate tags to help other students find your post more quickly</li>
                        <li><strong>Friendly communication:</strong> Maintain respect and courtesy to promote a positive discussion atmosphere</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            <?php if ($hasCourses): ?>
                // Initialize rich text editor
                tinymce.init({
                    selector: '#content',
                    height: 400,
                    menubar: false,
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                        'insertdatetime', 'media', 'table', 'help', 'wordcount'
                    ],
                    toolbar: 'undo redo | blocks | ' +
                        'bold italic backcolor | alignleft aligncenter ' +
                        'alignright alignjustify | bullist numlist outdent indent | ' +
                        'removeformat | help',
                    content_style: 'body { font-family:Poppins,Arial,sans-serif; font-size:16px }'
                });

                // Title character counter
                $('#title').on('input', function() {
                    let count = $(this).val().length;
                    $('#titleCounter').text(count);
                    
                    // Alert when approaching character limit
                    if (count > 130) {
                        $('#titleCounter').addClass('text-danger');
                    } else {
                        $('#titleCounter').removeClass('text-danger');
                    }
                });
                
                // Tag selection
                $('.post-tag').click(function() {
                    $(this).toggleClass('active');
                    updateTags();
                });
                
                // Update hidden field with tag values
                function updateTags() {
                    const tags = [];
                    $('.post-tag.active').each(function() {
                        tags.push($(this).data('value'));
                    });
                    $('#post_tags').val(tags.join(','));
                    
                    // Update preview tags
                    const tagsHtml = tags.map(tag => 
                        `<span class="badge bg-secondary me-1">${tag}</span>`
                    ).join('');
                    $('#previewTags').html(tagsHtml);
                }
                
                // Preview functionality
                function updatePreview() {
                    const title = $('#title').val() || "Post Title";
                    const content = tinymce.get('content').getContent() || "Post content will be displayed here...";
                    
                    // Update title and content
                    $('#previewTitle').text(title);
                    $('#previewContent').html(content);
                    
                    // Update course name
                    const courseSelect = document.getElementById('course_id');
                    const selectedCourse = courseSelect.options[courseSelect.selectedIndex];
                    $('#previewCourse').text(selectedCourse && selectedCourse.value ? selectedCourse.text : 'Selected course');
                    
                    // Update category
                    const categorySelect = document.getElementById('category_id');
                    const selectedCategory = categorySelect.options[categorySelect.selectedIndex];
                    if (selectedCategory && selectedCategory.value) {
                        $('#previewCategory').text(selectedCategory.text).show();
                    } else {
                        $('#previewCategory').hide();
                    }
                    
                    // Update author (anonymous handling)
                    if ($('#is_anonymous').is(':checked')) {
                        $('#previewAuthor').text('Anonymous User');
                        $('#previewAvatar').text('?');
                    } else {
                        $('#previewAuthor').text('<?= htmlspecialchars($user_name) ?>');
                        $('#previewAvatar').text('<?= strtoupper(substr($user_name, 0, 1)) ?>');
                    }
                    
                    // Update tags
                    updateTags();
                    
                    // Show preview area
                    $('#previewSection').show();
                }
                
                // Preview button click
                $('#previewBtn').click(function() {
                    updatePreview();
                    // Scroll to preview area
                    $('html, body').animate({
                        scrollTop: $("#previewSection").offset().top - 100
                    }, 500);
                });
                
                // Anonymous option change
                $('#is_anonymous').change(function() {
                    updatePreview();
                });
                
                // Form validation
                $('#postForm').on('submit', function(e) {
                    // Title validation
                    const title = $('#title').val().trim();
                    if (!title) {
                        e.preventDefault();
                        alert('Please enter a post title!');
                        $('#title').focus();
                        return false;
                    }
                    
                    // Content validation
                    const content = tinymce.get('content').getContent().trim();
                    if (!content) {
                        e.preventDefault();
                        alert('Please enter post content!');
                        tinymce.get('content').focus();
                        return false;
                    }
                    
                    // Course validation
                    const courseId = $('#course_id').val();
                    if (!courseId) {
                        e.preventDefault();
                        alert('Please select a course for this post!');
                        $('#course_id').focus();
                        return false;
                    }
                    
                    // Prevent multiple submissions
                    $('#submitBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Posting...');
                    
                    return true;
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>