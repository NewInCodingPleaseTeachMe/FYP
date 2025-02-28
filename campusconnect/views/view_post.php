<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$post_id = $_GET["post_id"] ?? null;
if (!$post_id) {
    header("Location: discussion.php?error=invalid_post");
    exit;
}

// Get post information, including `course_id`
$stmt = $pdo->prepare("SELECT discussion_posts.*, users.name, users.avatar, users.role, courses.course_name, courses.id AS course_id
                       FROM discussion_posts 
                       JOIN users ON discussion_posts.user_id = users.id
                       JOIN courses ON discussion_posts.course_id = courses.id
                       WHERE discussion_posts.id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch();

if (!$post) {
    header("Location: discussion.php?error=post_not_found");
    exit;
}

$course_id = $post["course_id"]; // Get `course_id`, used for returning to discussion area

// Get all comments for the post
$stmt = $pdo->prepare("SELECT discussion_comments.*, users.name, users.avatar, users.role
                       FROM discussion_comments 
                       JOIN users ON discussion_comments.user_id = users.id 
                       WHERE discussion_comments.post_id = ?
                       ORDER BY discussion_comments.created_at ASC");
$stmt->execute([$post_id]);
$comments = $stmt->fetchAll();

// Organize comment structure, distinguish between main comments and replies
$comment_tree = [];
foreach ($comments as $comment) {
    $parent_id = $comment["parent_id"] ?? 0; // Handle NULL to become 0, ensure consistent data format

    if ($parent_id == 0) {
        $comment_tree[$comment["id"]] = ["comment" => $comment, "replies" => []];
    } else {
        if (!isset($comment_tree[$parent_id])) {
            $comment_tree[$parent_id] = ["comment" => null, "replies" => []]; // Initialize first
        }
        $comment_tree[$parent_id]["replies"][] = $comment;
    }
}

// Get current user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION["user_id"]]);
$current_user = $stmt->fetch();

// Update post view count
$stmt = $pdo->prepare("UPDATE discussion_posts SET views = views + 1 WHERE id = ?");
$stmt->execute([$post_id]);

// Get related topics
$stmt = $pdo->prepare("SELECT * FROM discussion_posts 
                      WHERE course_id = ? AND id != ? 
                      ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$course_id, $post_id]);
$related_posts = $stmt->fetchAll();

// Function to generate avatar colors
function getAvatarColor($role) {
    switch ($role) {
        case 'teacher':
            return 'bg-success';
        case 'admin':
            return 'bg-danger';
        default:
            return 'bg-primary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - Discussion Details | CampusConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --bs-primary-rgb: 13, 110, 253;
            --bs-secondary-rgb: 108, 117, 125;
        }
        body {
            background-color: #f8f9fa;
            color: #212529;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
        }
        .post-card {
            border: none;
            border-radius: 0.8rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            overflow: hidden;
        }
        .post-header {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            border-bottom: 1px solid rgba(var(--bs-primary-rgb), 0.1);
        }
        .comment-card {
            border: none;
            border-radius: 0.8rem;
            margin-bottom: 1.5rem;
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .reply-card {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            border-radius: 0.6rem;
            margin-left: 3rem;
            margin-top: 1rem;
            box-shadow: 0 0.125rem 0.15rem rgba(0, 0, 0, 0.05);
        }
        .reply-form {
            display: none;
            margin-top: 1rem;
            background-color: rgba(var(--bs-primary-rgb), 0.03);
            padding: 1rem;
            border-radius: 0.6rem;
        }
        .comment-actions {
            font-size: 0.875rem;
        }
        .course-badge {
            background-color: #6610f2;
            font-weight: 500;
            padding: 0.35rem 0.65rem;
            border-radius: 0.5rem;
        }
        .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .avatar-sm {
            width: 32px;
            height: 32px;
            font-size: 0.8rem;
        }
        .role-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            border-radius: 0.3rem;
            margin-left: 0.5rem;
        }
        .stats-item {
            display: flex;
            align-items: center;
            margin-right: 1rem;
            color: #6c757d;
            font-size: 0.875rem;
        }
        .post-content {
            font-size: 1.05rem;
            line-height: 1.6;
            white-space: pre-line;
        }
        .related-post-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: #212529;
        }
        .related-post-item {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
        }
        .related-post-item:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.03);
        }
        .related-post-item:last-child {
            border-bottom: none;
        }
        .btn-floating {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .comment-form-container {
            position: sticky;
            bottom: 1rem;
            z-index: 10;
        }
        .highlights {
            background-color: #fff;
            border-radius: 0.6rem;
            padding: 0.3rem 0.6rem;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            font-weight: 600;
            margin-right: 0.5rem;
        }
        .back-to-top {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            display: none;
            z-index: 99;
        }
        @media (max-width: 767.98px) {
            .reply-card {
                margin-left: 1rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../dashboard.php">
                <i class="bi bi-mortarboard-fill me-2"></i>CampusConnect
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-house-door-fill me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../views/course_list.php">
                            <i class="bi bi-book me-1"></i>Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="discussion.php">
                            <i class="bi bi-chat-dots-fill me-1"></i>Discussion
                        </a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="text-white me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($current_user['name'] ?? 'User') ?>
                    </div>
                    <a href="../modules/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row">
            <div class="col-lg-8">
                <!-- Breadcrumb navigation -->
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="forum.php">Discussion</a></li>
                        <li class="breadcrumb-item"><a href="discussion.php?course_id=<?= $course_id ?>"><?= htmlspecialchars($post['course_name']) ?></a></li>
                        <li class="breadcrumb-item active">Post Details</li>
                    </ol>
                </nav>

                <!-- Post content -->
                <div class="card post-card mb-4">
                    <div class="card-header post-header py-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="course-badge text-white">
                                <i class="bi bi-book me-1"></i><?= htmlspecialchars($post['course_name']) ?>
                            </span>
                            <div class="d-flex">
                                <div class="stats-item me-3">
                                    <i class="bi bi-eye me-1"></i> <?= $post['views'] ?? 0 ?> Views
                                </div>
                                <div class="stats-item">
                                    <i class="bi bi-chat-left me-1"></i> <?= count($comments) ?> Comments
                                </div>
                            </div>
                        </div>
                        <h4 class="card-title mb-0">
                            <?= htmlspecialchars($post['title']) ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar <?= getAvatarColor($post['role']) ?> me-2">
                                <?php if (!empty($post['avatar'])): ?>
                                    <img src="<?= htmlspecialchars($post['avatar']) ?>" alt="Avatar" class="w-100 h-100 rounded-circle">
                                <?php else: ?>
                                    <?= mb_substr(htmlspecialchars($post['name']), 0, 1) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="d-flex align-items-center">
                                    <span class="fw-bold"><?= htmlspecialchars($post['name']) ?></span>
                                    <?php if ($post['role']): ?>
                                        <span class="role-badge bg-<?= $post['role'] === 'teacher' ? 'success' : ($post['role'] === 'admin' ? 'danger' : 'primary') ?>">
                                            <?= $post['role'] === 'teacher' ? 'Teacher' : ($post['role'] === 'admin' ? 'Admin' : 'Student') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i><?= date('Y-m-d H:i', strtotime($post['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                        <div class="post-content mb-3">
                            <?= $post['content'] ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center border-top pt-3">
                            <div>
                                <button class="btn btn-sm btn-outline-primary me-2" id="likeBtn">
                                    <i class="bi bi-hand-thumbs-up me-1"></i>Like
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" id="scrollToComment">
                                    <i class="bi bi-chat-dots me-1"></i>Comment
                                </button>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" id="shareBtn">
                                    <i class="bi bi-share me-1"></i>Share
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments list -->
                <h5 class="mb-3" id="commentSection">
                    <i class="bi bi-chat-square-text me-2"></i>Comments 
                    <span class="badge bg-secondary"><?= count($comments) ?></span>
                </h5>

                <?php if (empty($comment_tree)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle-fill me-2"></i>No comments yet, be the first to comment!
                    </div>
                <?php else: ?>
                    <?php foreach ($comment_tree as $comment_id => $comment_data): ?>
                        <?php if (!empty($comment_data["comment"])): ?>
                            <!-- Main comment -->
                            <div class="comment-card">
                                <div class="card-body">
                                    <div class="d-flex">
                                        <div class="avatar <?= getAvatarColor($comment_data['comment']['role']) ?> me-2">
                                            <?php if (!empty($comment_data['comment']['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($comment_data['comment']['avatar']) ?>" alt="Avatar" class="w-100 h-100 rounded-circle">
                                            <?php else: ?>
                                                <?= mb_substr(htmlspecialchars($comment_data['comment']['name']), 0, 1) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center">
                                                    <span class="fw-bold"><?= htmlspecialchars($comment_data['comment']['name']) ?></span>
                                                    <?php if ($comment_data['comment']['role']): ?>
                                                        <span class="role-badge bg-<?= $comment_data['comment']['role'] === 'teacher' ? 'success' : ($comment_data['comment']['role'] === 'admin' ? 'danger' : 'primary') ?>">
                                                            <?= $comment_data['comment']['role'] === 'teacher' ? 'Teacher' : ($comment_data['comment']['role'] === 'admin' ? 'Admin' : 'Student') ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i><?= date('Y-m-d H:i', strtotime($comment_data['comment']['created_at'])) ?>
                                                </small>
                                            </div>
                                            <div class="mb-2">
                                                <?= nl2br(htmlspecialchars($comment_data['comment']['content'])) ?>
                                            </div>
                                            <div class="comment-actions">
                                                <button class="btn btn-sm btn-link text-decoration-none p-0 reply-toggle" data-target="reply-form-<?= $comment_id ?>">
                                                    <i class="bi bi-reply me-1"></i>Reply
                                                </button>
                                                
                                                <?php if ($_SESSION["user_id"] == $comment_data['comment']['user_id'] || $_SESSION["role"] == "admin"): ?>
                                                    <a href="../modules/comment_delete.php?id=<?= $comment_id ?>&post_id=<?= $post_id ?>" class="btn btn-sm btn-link text-decoration-none p-0 ms-3 text-danger" onclick="return confirm('Are you sure you want to delete this comment?')">
                                                        <i class="bi bi-trash me-1"></i>Delete
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Reply Form -->
                                            <div id="reply-form-<?= $comment_id ?>" class="reply-form">
                                                <form action="../modules/comment_create.php" method="POST">
                                                    <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                                    <input type="hidden" name="parent_id" value="<?= $comment_id ?>">
                                                    <div class="input-group">
                                                        <input type="text" name="content" class="form-control" placeholder="Reply to <?= htmlspecialchars($comment_data['comment']['name']) ?>..." required>
                                                        <button type="submit" class="btn btn-primary">Send</button>
                                                    </div>
                                                    <small class="text-muted mt-1 d-block">Press Enter to send reply, ESC to cancel</small>
                                                </form>
                                            </div>

                                            <!-- Reply List -->
                                            <?php if (!empty($comment_data['replies'])): ?>
                                                <div class="replies">
                                                    <?php foreach ($comment_data['replies'] as $reply): ?>
                                                        <div class="reply-card">
                                                            <div class="card-body py-2 px-3">
                                                                <div class="d-flex">
                                                                    <div class="avatar avatar-sm <?= getAvatarColor($reply['role']) ?> me-2">
                                                                        <?php if (!empty($reply['avatar'])): ?>
                                                                            <img src="<?= htmlspecialchars($reply['avatar']) ?>" alt="Avatar" class="w-100 h-100 rounded-circle">
                                                                        <?php else: ?>
                                                                            <?= mb_substr(htmlspecialchars($reply['name']), 0, 1) ?>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                                            <div class="d-flex align-items-center">
                                                                                <span class="fw-medium"><?= htmlspecialchars($reply['name']) ?></span>
                                                                                <?php if ($reply['role']): ?>
                                                                                    <span class="role-badge bg-<?= $reply['role'] === 'teacher' ? 'success' : ($reply['role'] === 'admin' ? 'danger' : 'primary') ?>">
                                                                                        <?= $reply['role'] === 'teacher' ? 'Teacher' : ($reply['role'] === 'admin' ? 'Admin' : 'Student') ?>
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            <small class="text-muted">
                                                                                <?= date('m-d H:i', strtotime($reply['created_at'])) ?>
                                                                            </small>
                                                                        </div>
                                                                        <div>
                                                                            <?= nl2br(htmlspecialchars($reply['content'])) ?>
                                                                        </div>
                                                                        
                                                                        <?php if ($_SESSION["user_id"] == $reply['user_id'] || $_SESSION["role"] == "admin"): ?>
                                                                            <div class="comment-actions mt-1">
                                                                                <a href="../modules/comment_delete.php?id=<?= $reply['id'] ?>&post_id=<?= $post_id ?>" class="btn btn-sm btn-link text-decoration-none p-0 text-danger" onclick="return confirm('Are you sure you want to delete this reply?')">
                                                                                    <i class="bi bi-trash me-1"></i>Delete
                                                                                </a>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Post Comment -->
                <div class="comment-form-container" id="commentForm">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="../modules/comment_create.php" method="POST">
                                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                                <input type="hidden" name="parent_id" value="0">
                                <div class="d-flex align-items-center">
                                    <div class="avatar <?= getAvatarColor($current_user['role']) ?> me-2" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                        <?php if (!empty($current_user['avatar'])): ?>
                                            <img src="<?= htmlspecialchars($current_user['avatar']) ?>" alt="Avatar" class="w-100 h-100 rounded-circle">
                                        <?php else: ?>
                                            <?= mb_substr(htmlspecialchars($current_user['name']), 0, 1) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="input-group">
                                        <input type="text" name="content" class="form-control" placeholder="Share your thoughts..." required>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send-fill me-1"></i>Send
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="position-sticky" style="top: 1rem;">
                    <!-- Course Information -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-book me-2"></i><?= htmlspecialchars($post['course_name']) ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-around mb-3">
                                <div class="text-center">
                                    <div class="highlights">
                                        <i class="bi bi-people-fill me-1 text-primary"></i>
                                        <span>34</span>
                                    </div>
                                    <div class="mt-1 small">Students</div>
                                </div>
                                <div class="text-center">
                                    <div class="highlights">
                                        <i class="bi bi-chat-dots-fill me-1 text-primary"></i>
                                        <span>126</span>
                                    </div>
                                    <div class="mt-1 small">Discussions</div>
                                </div>
                                <div class="text-center">
                                    <div class="highlights">
                                        <i class="bi bi-file-earmark-text-fill me-1 text-primary"></i>
                                        <span>8</span>
                                    </div>
                                    <div class="mt-1 small">Resources</div>
                                </div>
                            </div>
                            <div class="d-grid">
                                <a href="view_course.php?id=<?= $course_id ?>" class="btn btn-outline-primary">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Enter Course
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Related Topics -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi bi-chat-square-text me-2"></i>Related Topics
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($related_posts)): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($related_posts as $related_post): ?>
                                        <a href="view_post.php?post_id=<?= $related_post['id'] ?>" class="list-group-item list-group-item-action related-post-item">
                                            <div class="related-post-title mb-1"><?= htmlspecialchars($related_post['title']) ?></div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><?= date('m-d', strtotime($related_post['created_at'])) ?></small>
                                                <small class="text-muted">
                                                    <i class="bi bi-chat-dots me-1"></i><?= $related_post['comment_count'] ?? 0 ?>
                                                </small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-3 text-center text-muted">
                                    <i class="bi bi-emoji-neutral mb-2 fs-4"></i>
                                    <p class="mb-0">No related topics yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Back Buttons -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="discussion.php?course_id=<?= $course_id ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Discussion Board
                                </a>
                                <a href="../dashboard.php" class="btn btn-outline-primary">
                                    <i class="bi bi-house-door me-2"></i>Back to Homepage
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <button class="btn btn-primary btn-floating shadow-sm" id="backToTop">
        <i class="bi bi-arrow-up"></i>
    </button>

    <footer class="bg-white py-3 mt-5 border-top">
        <div class="container text-center text-muted">
            <small>© 2025 CampusConnect - The Power of Connecting Campus</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Reply toggle display
            document.querySelectorAll('.reply-toggle').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const formElement = document.getElementById(targetId);
                    
                    // Close all other reply forms
                    document.querySelectorAll('.reply-form').forEach(form => {
                        if (form.id !== targetId) {
                            form.style.display = 'none';
                        }
                    });
                    
                    // Toggle current reply form
                    if (formElement.style.display === 'block') {
                        formElement.style.display = 'none';
                    } else {
                        formElement.style.display = 'block';
                        formElement.querySelector('input[type="text"]').focus();
                    }
                });
            });
            
            // Reply form key listeners
            document.querySelectorAll('.reply-form input[type="text"]').forEach(input => {
                input.addEventListener('keydown', function(e) {
                    // ESC key closes reply form
                    if (e.key === 'Escape') {
                        this.closest('.reply-form').style.display = 'none';
                    }
                });
            });
            
            // Scroll to comment section
            document.getElementById('scrollToComment').addEventListener('click', function() {
                document.getElementById('commentSection').scrollIntoView({ behavior: 'smooth' });
                setTimeout(() => {
                    document.querySelector('#commentForm input[type="text"]').focus();
                }, 500);
            });

            // Like functionality
            document.getElementById('likeBtn').addEventListener('click', function() {
                this.classList.toggle('btn-outline-primary');
                this.classList.toggle('btn-primary');
                const icon = this.querySelector('i');
                if (icon.classList.contains('bi-hand-thumbs-up')) {
                    icon.classList.replace('bi-hand-thumbs-up', 'bi-hand-thumbs-up-fill');
                    this.innerHTML = this.innerHTML.replace('Like', 'Liked');
                } else {
                    icon.classList.replace('bi-hand-thumbs-up-fill', 'bi-hand-thumbs-up');
                    this.innerHTML = this.innerHTML.replace('Liked', 'Like');
                }
            });
            
            // Share functionality
            document.getElementById('shareBtn').addEventListener('click', function() {
                if (navigator.share) {
                    navigator.share({
                        title: '<?= htmlspecialchars($post['title']) ?>',
                        text: 'Check out this discussion on CampusConnect: <?= htmlspecialchars($post['title']) ?>',
                        url: window.location.href
                    }).catch(err => {
                        console.log('Share failed:', err);
                    });
                } else {
                    alert('Link copied successfully, please share manually');
                    navigator.clipboard.writeText(window.location.href);
                }
            });
            
            // Back to top button
            const backToTopBtn = document.getElementById('backToTop');
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopBtn.style.display = 'flex';
                } else {
                    backToTopBtn.style.display = 'none';
                }
            });
            
            backToTopBtn.addEventListener('click', function() {
                window.scrollTo({top: 0, behavior: 'smooth'});
            });
        });