<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit("Please login first!");
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// Get course ID
$course_id = $_GET["course_id"] ?? null;
if (!$course_id) {
    header("Location: course_list.php");
    exit("Invalid course ID!");
}

// Get course information
$stmt = $pdo->prepare("SELECT c.*, u.name AS teacher_name 
                       FROM courses c
                       JOIN users u ON c.teacher_id = u.id
                       WHERE c.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: course_list.php");
    exit("Course does not exist!");
}

// Check if current user has permission to access this course's discussion forum
$hasAccess = false;
if ($role === "admin" || $role === "teacher") {
    $hasAccess = true;
} else {
    // Check if student is enrolled in this course
    $stmt = $pdo->prepare("SELECT * FROM user_courses WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    if ($stmt->fetch()) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    header("Location: course_list.php");
    exit("You do not have permission to access this course's discussion forum!");
}

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Build query
$query = "SELECT dp.id, dp.title, dp.content, dp.created_at, dp.updated_at, 
          u.id as user_id, u.name as author_name, u.role as author_role,
          (SELECT COUNT(*) FROM discussion_comments WHERE post_id = dp.id) as comment_count
          FROM discussion_posts dp
          JOIN users u ON dp.user_id = u.id
          WHERE dp.course_id = ?";

$params = [$course_id];

if (!empty($search)) {
    $query .= " AND (dp.title LIKE ? OR dp.content LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filter === 'my_posts') {
    $query .= " AND dp.user_id = ?";
    $params[] = $user_id;
} elseif ($filter === 'teacher_posts') {
    $query .= " AND u.role = 'teacher'";
} elseif ($filter === 'unanswered') {
    $query .= " AND (SELECT COUNT(*) FROM discussion_comments WHERE post_id = dp.id) = 0";
}

$query .= " ORDER BY dp.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Calculate statistics
$total_posts = count($posts);
$my_posts_count = 0;
$teacher_posts_count = 0;
$unanswered_count = 0;
$recent_posts_count = 0;

foreach ($posts as $post) {
    if ($post['user_id'] == $user_id) {
        $my_posts_count++;
    }
    if ($post['author_role'] == 'teacher') {
        $teacher_posts_count++;
    }
    if ($post['comment_count'] == 0) {
        $unanswered_count++;
    }
    if (strtotime($post['created_at']) > strtotime('-7 days')) {
        $recent_posts_count++;
    }
}

// Helper function: Calculate time difference
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' year(s) ago';
    } elseif ($diff->m > 0) {
        return $diff->m . ' month(s) ago';
    } elseif ($diff->d > 0) {
        return $diff->d . ' day(s) ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour(s) ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute(s) ago';
    } else {
        return 'just now';
    }
}

// Truncate content preview
function truncateContent($content, $length = 100) {
    if (mb_strlen($content, 'UTF-8') > $length) {
        $content = mb_substr($content, 0, $length, 'UTF-8') . '...';
    }
    return $content;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Discussion Forum - <?= htmlspecialchars($course['course_name']) ?> - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .discussion-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid transparent;
        }
        .discussion-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .discussion-card.teacher-post {
            border-left-color: #0d6efd;
        }
        .discussion-card.my-post {
            border-left-color: #198754;
        }
        .discussion-card.unanswered {
            border-left-color: #ffc107;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        .teacher-avatar {
            background-color: #0d6efd;
        }
        .student-avatar {
            background-color: #198754;
        }
        .admin-avatar {
            background-color: #dc3545;
        }
        .badge-post-count {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(30%, -30%);
        }
    </style>
</head>
<body>
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
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-journal-richtext me-1"></i>Course List
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_course.php?id=<?= $course_id ?>">
                            <i class="bi bi-book me-1"></i>Course Details
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["name"] ?? "User") ?> 
                        <span class="badge bg-light text-primary ms-1"><?= ucfirst($role) ?></span>
                    </span>
                    <a href="../modules/logout.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Breadcrumb Navigation -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="course_list.php">Course List</a></li>
                <li class="breadcrumb-item"><a href="view_course.php?id=<?= $course_id ?>">Course Details</a></li>
                <li class="breadcrumb-item active" aria-current="page">Discussion Forum</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-chat-square-text-fill me-2"></i>
                    <?= htmlspecialchars($course['course_name']) ?> Discussion Forum
                </h2>
                <p class="text-muted">
                    <span class="badge bg-primary me-2"><?= $course['course_code'] ?></span>
                    <i class="bi bi-person-badge me-1"></i>Course Teacher: <?= htmlspecialchars($course['teacher_name']) ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
            <?php if ($role == "teacher" || $role == "admin"): ?>
                <a href="add_post.php?course_id=<?= $course_id ?>" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i>Create New Post
                </a>
            <?php endif; ?>
                <a href="view_course.php?id=<?= $course_id ?>" class="btn btn-secondary ms-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to Course Details
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3 mb-md-0">
                <div class="card bg-primary text-white h-100">
                    <div class="card-body text-center position-relative">
                        <i class="bi bi-chat-square-text-fill fs-1 mb-2"></i>
                        <h5 class="mb-0">Total Posts</h5>
                        <h3 class="mb-0"><?= $total_posts ?></h3>
                    </div>
                </div>
            </div>
            
            <?php if ($role == "teacher" || $role == "admin"): ?>
            <!-- 只对教师和管理员显示"My Posts" -->
            <div class="col-md-3 col-6 mb-3 mb-md-0">
                <div class="card bg-success text-white h-100">
                    <div class="card-body text-center position-relative">
                        <i class="bi bi-person-circle fs-1 mb-2"></i>
                        <h5 class="mb-0">My Posts</h5>
                        <h3 class="mb-0"><?= $my_posts_count ?></h3>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- 对学生显示"Recent Posts" -->
            <div class="col-md-3 col-6 mb-3 mb-md-0">
                <div class="card bg-success text-white h-100">
                    <div class="card-body text-center position-relative">
                        <i class="bi bi-clock-history fs-1 mb-2"></i>
                        <h5 class="mb-0">Recent Posts</h5>
                        <h3 class="mb-0"><?= $recent_posts_count ?></h3>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="col-md-3 col-6">
                <div class="card bg-info text-white h-100">
                    <div class="card-body text-center position-relative">
                        <i class="bi bi-mortarboard-fill fs-1 mb-2"></i>
                        <h5 class="mb-0">Teacher Posts</h5>
                        <h3 class="mb-0"><?= $teacher_posts_count ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card bg-warning text-dark h-100">
                    <div class="card-body text-center position-relative">
                        <i class="bi bi-question-circle-fill fs-1 mb-2"></i>
                        <h5 class="mb-0">Unanswered</h5>
                        <h3 class="mb-0"><?= $unanswered_count ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="course_id" value="<?= $course_id ?>">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search post title or content..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="filter" class="form-select">
                            <option value="" <?= $filter === '' ? 'selected' : '' ?>>All Posts</option>
                            <?php if ($role == "teacher" || $role == "admin"): ?>
                            <option value="my_posts" <?= $filter === 'my_posts' ? 'selected' : '' ?>>My Posts</option>
                            <?php endif; ?>
                            <option value="teacher_posts" <?= $filter === 'teacher_posts' ? 'selected' : '' ?>>Teacher Posts</option>
                            <option value="unanswered" <?= $filter === 'unanswered' ? 'selected' : '' ?>>Unanswered Posts</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter me-1"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($posts)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php if (empty($search) && empty($filter)): ?>
                    <?php if ($role == "teacher" || $role == "admin"): ?>
                        No posts in the discussion forum yet. Create a new post!
                    <?php else: ?>
                        No posts in the discussion forum yet. Teachers will post course materials and announcements here.
                    <?php endif; ?>
                <?php else: ?>
                    No posts found matching your criteria.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Post List -->
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list me-2"></i>Discussion Posts List</h5>
                    <span class="badge bg-primary"><?= count($posts) ?> posts</span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($posts as $post): 
                            $isTeacherPost = $post['author_role'] === 'teacher';
                            $isMyPost = $post['user_id'] == $user_id;
                            $isUnanswered = $post['comment_count'] == 0;
                            
                            $cardClass = '';
                            if ($isTeacherPost) $cardClass .= ' teacher-post';
                            if ($isMyPost) $cardClass .= ' my-post';
                            if ($isUnanswered) $cardClass .= ' unanswered';
                            
                            $avatarClass = '';
                            if ($post['author_role'] === 'teacher') {
                                $avatarClass = 'teacher-avatar';
                            } elseif ($post['author_role'] === 'admin') {
                                $avatarClass = 'admin-avatar';
                            } else {
                                $avatarClass = 'student-avatar';
                            }
                            
                            $initial = mb_substr($post['author_name'], 0, 1, 'UTF-8');
                        ?>
                        <div class="col-12 mb-3">
                            <div class="card discussion-card<?= $cardClass ?>">
                                <div class="card-body">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <div class="avatar <?= $avatarClass ?>">
                                                <?= $initial ?>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="mb-1">
                                                <a href="view_post.php?post_id=<?= $post['id'] ?>" class="text-decoration-none">
                                                    <?= htmlspecialchars($post['title']) ?>
                                                </a>
                                                <?php if ($isTeacherPost): ?>
                                                    <span class="badge bg-primary ms-2">Teacher</span>
                                                <?php endif; ?>
                                                <?php if ($isMyPost && ($role == "teacher" || $role == "admin")): ?>
                                                    <span class="badge bg-success ms-2">Mine</span>
                                                <?php endif; ?>
                                                <?php if ($isUnanswered): ?>
                                                    <span class="badge bg-warning text-dark ms-2">Unanswered</span>
                                                <?php endif; ?>
                                            </h5>
                                            <p class="text-muted mb-1 small">
                                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($post['author_name']) ?>
                                                <i class="bi bi-clock ms-3 me-1"></i><?= timeAgo($post['created_at']) ?>
                                                <i class="bi bi-chat-dots ms-3 me-1"></i><?= $post['comment_count'] ?> replies
                                            </p>
                                            <?php if (!empty($post['content'])): ?>
                                                <p class="card-text mb-0"><?= truncateContent(htmlspecialchars($post['content'])) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ms-auto text-end">
                                            <a href="view_post.php?post_id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye me-1"></i>View
                                            </a>
                                            <?php if ($isMyPost || $role === 'admin' || $role === 'teacher'): ?>
                                                <a href="edit_post.php?post_id=<?= $post['id'] ?>" class="btn btn-sm btn-outline-secondary ms-1">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>