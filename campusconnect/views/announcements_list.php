<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Verify user login status
if (!isset($_SESSION["user_id"])) {
    die("Please login first!");
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];
$user_name = $_SESSION["user_name"] ?? "User";

// Get filter parameters
$course_filter = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get sorting parameters
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'ASC' : 'DESC';

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = [];
$params = [];

// Limit query scope based on role
if ($role === "student") {
    // Students can only see announcements from courses they're enrolled in
    $where_conditions[] = "announcements.course_id IN (SELECT course_id FROM enrollments WHERE student_id = ?)";
    $params[] = $user_id;
} elseif ($role === "teacher") {
    // Teachers can only see announcements from their courses
    $where_conditions[] = "courses.teacher_id = ?";
    $params[] = $user_id;
}

// Apply course filter
if (!empty($course_filter)) {
    $where_conditions[] = "announcements.course_id = ?";
    $params[] = $course_filter;
}

// Apply search filter
if (!empty($search_term)) {
    $where_conditions[] = "(announcements.title LIKE ? OR announcements.content LIKE ?)";
    $params[] = "%{$search_term}%";
    $params[] = "%{$search_term}%";
}

// Assemble WHERE clause
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get total number of announcements
$count_sql = "SELECT COUNT(*) FROM announcements 
              JOIN courses ON announcements.course_id = courses.id 
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_announcements = $count_stmt->fetchColumn();

// Calculate total pages
$total_pages = ceil($total_announcements / $per_page);
$page = max(1, min($page, $total_pages));

// Get announcement data
// Change the query around line 77-83 to use teacher_id instead of publisher_id
$sql = "SELECT announcements.id, courses.course_name, courses.course_code, announcements.title, 
               announcements.content, announcements.created_at, 
               announcements.priority,
               users.name as publisher_name
        FROM announcements 
        JOIN courses ON announcements.course_id = courses.id
        JOIN users ON announcements.teacher_id = users.id
        $where_clause
        ORDER BY announcements.$sort_by $sort_order
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

// Get all courses for filtering
try {
    if ($role === "admin") {
        // Administrators can see all courses
        $course_stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses ORDER BY course_name");
        $course_stmt->execute();
    } elseif ($role === "teacher") {
        // Teachers can only see courses they teach
        $course_stmt = $pdo->prepare("SELECT id, course_name, course_code FROM courses WHERE teacher_id = ? ORDER BY course_name");
        $course_stmt->execute([$user_id]);
    } else {
        // Students can only see courses they're enrolled in
        $course_stmt = $pdo->prepare("SELECT c.id, c.course_name, c.course_code 
                                    FROM courses c 
                                    JOIN enrollments e ON c.id = e.course_id 
                                    WHERE e.student_id = ? 
                                    ORDER BY c.course_name");
        $course_stmt->execute([$user_id]);
    }
    $courses = $course_stmt->fetchAll();
} catch (PDOException $e) {
    $courses = [];
}

// Date time formatting function
function formatDateTime($dateTime) {
    $timestamp = strtotime($dateTime);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        return floor($diff / 60) . " minutes ago";
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . " hours ago";
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . " days ago";
    } else {
        return date("Y-m-d", $timestamp);
    }
}

// Truncate text function
function truncateText($text, $length = 100) {
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . '...';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --primary-dark: #0a58ca;
            --primary-light: #e7f0ff;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        
        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 15px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 1rem 2rem rgba(0, 0, 0, 0.15);
        }
        
        .announcement-card .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .announcement-card .card-body {
            padding: 1.5rem;
        }
        
        .announcement-card .card-footer {
            padding: 1rem 1.5rem;
            background-color: white;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        
        .announcement-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .announcement-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .announcement-course {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            background-color: var(--primary-light);
            color: var(--primary-dark);
            font-weight: 500;
        }
        
        .announcement-priority {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            text-transform: uppercase;
        }
        
        .priority-normal {
            background-color: #e9ecef;
            color: #495057;
        }
        
        .priority-important {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .priority-urgent {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .announcement-content {
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .filter-card {
            margin-bottom: 2rem;
            background-color: white;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-left: 2.5rem;
            border-radius: 20px;
        }
        
        .search-box .bi-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination {
            --bs-pagination-border-radius: 20px;
        }
        
        .btn-group-sm > .btn, .btn-sm {
            border-radius: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .btn-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            z-index: 1000;
        }
        
        .read-more {
            color: var(--primary-color);
            cursor: pointer;
            font-weight: 500;
        }
        
        .read-more:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn-group {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .search-box {
                width: 100%;
            }
            
            .filter-card .card-body {
                padding: 1rem;
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
                        <a class="nav-link" href="course_list.php">
                            <i class="bi bi-book me-1"></i> Courses
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-megaphone me-1"></i> Announcements
                        </a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($user_name) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-1"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../modules/logout.php"><i class="bi bi-box-arrow-right me-1"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="page-container">
        <!-- Page title -->
        <div class="page-header">
            <h2><i class="bi bi-megaphone-fill me-2"></i> Announcements</h2>
            <div class="d-flex align-items-center gap-3">
                <?php if ($role === "teacher" || $role === "admin"): ?>
                    <a href="add_announcement.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i> Post Announcement
                    </a>
                <?php endif; ?>
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search announcements..." value="<?= htmlspecialchars($search_term) ?>">
                </div>
            </div>
        </div>

       <!-- Filter card -->
       <div class="card filter-card">
            <div class="card-body">
                <form id="filterForm" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="course_id" class="form-label">
                            <i class="bi bi-filter me-1"></i> Filter by Course
                        </label>
                        <select name="course_id" id="course_id" class="form-select" onchange="this.form.submit()">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course_name']) ?> 
                                    <?= !empty($course['course_code']) ? "(".htmlspecialchars($course['course_code']).")" : "" ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="sort" class="form-label">
                            <i class="bi bi-sort-down me-1"></i> Sort By
                        </label>
                        <select name="sort" id="sort" class="form-select" onchange="this.form.submit()">
                            <option value="created_at" <?= $sort_by == 'created_at' ? 'selected' : '' ?>>Publication Date</option>
                            <option value="title" <?= $sort_by == 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="course_id" <?= $sort_by == 'course_id' ? 'selected' : '' ?>>Course Name</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="order" class="form-label">Sort Order</label>
                        <select name="order" id="order" class="form-select" onchange="this.form.submit()">
                            <option value="desc" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="asc" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex justify-content-end">
                        <button type="button" id="resetFilter" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset Filters
                        </button>
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">
                        <input type="hidden" name="page" value="1">
                    </div>
                </form>
            </div>
        </div>

        <!-- Announcements display area -->
        <?php if (empty($announcements)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bi bi-clipboard-x"></i>
                </div>
                <h4>No Announcements</h4>
                <p class="text-muted">There are no announcements matching your criteria</p>
                <?php if (!empty($search_term) || !empty($course_filter)): ?>
                    <button type="button" id="clearFilters" class="btn btn-outline-primary mt-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                    </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="announcement-list">
                <?php foreach ($announcements as $announcement): ?>
                    <?php 
                        // Set priority styles
                        $priority_class = 'priority-normal';
                        $priority_text = 'Normal';
                        
                        if ($announcement['priority'] === 'important') {
                            $priority_class = 'priority-important';
                            $priority_text = 'Important';
                        } elseif ($announcement['priority'] === 'urgent') {
                            $priority_class = 'priority-urgent';
                            $priority_text = 'Urgent';
                        }
                    ?>
                    <div class="card announcement-card" id="announcement-<?= $announcement['id'] ?>">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <h5 class="announcement-title">
                                    <?php if ($announcement['priority'] === 'important' || $announcement['priority'] === 'urgent'): ?>
                                        <i class="bi bi-exclamation-circle-fill me-1 text-warning"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($announcement['title']) ?>
                                </h5>
                                <span class="announcement-priority <?= $priority_class ?>"><?= $priority_text ?></span>
                            </div>
                            <div class="announcement-meta">
                                <span><i class="bi bi-mortarboard me-1"></i> 
                                    <span class="announcement-course"><?= htmlspecialchars($announcement['course_name']) ?></span>
                                </span>
                                <span><i class="bi bi-person me-1"></i> <?= htmlspecialchars($announcement['publisher_name']) ?></span>
                                <span><i class="bi bi-clock me-1"></i> <?= formatDateTime($announcement['created_at']) ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="announcement-content" id="content-<?= $announcement['id'] ?>">
                                <?php
                                    $full_content = nl2br(htmlspecialchars($announcement['content']));
                                    $truncated = false;
                                    
                                    if (mb_strlen($announcement['content']) > 300) {
                                        echo '<div class="short-content">' . nl2br(htmlspecialchars(truncateText($announcement['content'], 300))) . '</div>';
                                        echo '<div class="full-content" style="display: none;">' . $full_content . '</div>';
                                        $truncated = true;
                                    } else {
                                        echo $full_content;
                                    }
                                ?>
                                <?php if ($truncated): ?>
                                    <div class="mt-2">
                                        <span class="read-more" data-id="<?= $announcement['id'] ?>">
                                            <i class="bi bi-chevron-down me-1"></i> Show More
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="button" class="btn btn-sm btn-outline-primary me-2 share-btn" 
                                            data-id="<?= $announcement['id'] ?>" data-title="<?= htmlspecialchars($announcement['title']) ?>">
                                        <i class="bi bi-share me-1"></i> Share
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary me-2 bookmark-btn" 
                                            data-id="<?= $announcement['id'] ?>">
                                        <i class="bi bi-bookmark me-1"></i> Bookmark
                                    </button>
                                </div>
                                
                                <?php if ($_SESSION["role"] === "teacher" || $_SESSION["role"] === "admin"): ?>
                                    <div class="btn-group">
                                        <a href="edit_announcement.php?id=<?= $announcement['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil me-1"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-btn" 
                                                data-id="<?= $announcement['id'] ?>" data-title="<?= htmlspecialchars($announcement['title']) ?>">
                                            <i class="bi bi-trash me-1"></i> Delete
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <nav aria-label="Announcement pagination">
                        <ul class="pagination">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page-1 ?>&sort=<?= $sort_by ?>&order=<?= $sort_order == 'DESC' ? 'desc' : 'asc' ?>&course_id=<?= $course_filter ?>&search=<?= urlencode($search_term) ?>" aria-label="Previous">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            // Display page navigation
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&sort=' . $sort_by . '&order=' . ($sort_order == 'DESC' ? 'desc' : 'asc') . '&course_id=' . $course_filter . '&search=' . urlencode($search_term) . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&sort=' . $sort_by . '&order=' . ($sort_order == 'DESC' ? 'desc' : 'asc') . '&course_id=' . $course_filter . '&search=' . urlencode($search_term) . '">' . $i . '</a></li>';
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&sort=' . $sort_by . '&order=' . ($sort_order == 'DESC' ? 'desc' : 'asc') . '&course_id=' . $course_filter . '&search=' . urlencode($search_term) . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page+1 ?>&sort=<?= $sort_by ?>&order=<?= $sort_order == 'DESC' ? 'desc' : 'asc' ?>&course_id=<?= $course_filter ?>&search=<?= urlencode($search_term) ?>" aria-label="Next">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Teacher/Admin creation button -->
        <?php if ($role === "teacher" || $role === "admin"): ?>
            <a href="add_announcement.php" class="btn btn-primary btn-float">
                <i class="bi bi-plus-lg"></i>
            </a>
        <?php endif; ?>
    </div>

    <!-- Delete confirmation dialog -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the announcement "<span id="deleteAnnouncementTitle"></span>"?</p>
                    <p class="text-danger">This action cannot be undone. Once deleted, the data cannot be recovered.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Confirm Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript libraries -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            // Expand/collapse announcement content
            $('.read-more').click(function() {
                const id = $(this).data('id');
                const contentDiv = $(`#content-${id}`);
                const shortContent = contentDiv.find('.short-content');
                const fullContent = contentDiv.find('.full-content');
                
                if (shortContent.is(':visible')) {
                    shortContent.hide();
                    fullContent.show();
                    $(this).html('<i class="bi bi-chevron-up me-1"></i> Show Less');
                } else {
                    fullContent.hide();
                    shortContent.show();
                    $(this).html('<i class="bi bi-chevron-down me-1"></i> Show More');
                }
            });
            
            // Search functionality
            let searchTimeout;
            $('#searchInput').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    const searchTerm = $('#searchInput').val().trim();
                    $('#filterForm input[name="search"]').val(searchTerm);
                    $('#filterForm input[name="page"]').val(1);
                    $('#filterForm').submit();
                }, 500);
            });
            
            // Reset filter button
            $('#resetFilter').click(function() {
                window.location.href = window.location.pathname;
            });
            
            // Clear filters button
            $('#clearFilters').click(function() {
                window.location.href = window.location.pathname;
            });
            
            // Delete confirmation popup
            $('.delete-btn').click(function() {
                const id = $(this).data('id');
                const title = $(this).data('title');
                
                $('#deleteAnnouncementTitle').text(title);
                $('#confirmDelete').attr('href', '../modules/announcement_delete.php?id=' + id);
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
            
            // Bookmark functionality
            $('.bookmark-btn').click(function() {
                const $btn = $(this);
                const id = $btn.data('id');
                
                // Toggle icon
                if ($btn.find('i').hasClass('bi-bookmark')) {
                    $btn.find('i').removeClass('bi-bookmark').addClass('bi-bookmark-fill');
                    $btn.addClass('active');
                    
                    // Add AJAX request to server to add bookmark
                    showToast('Added to bookmarks', 'success');
                } else {
                    $btn.find('i').removeClass('bi-bookmark-fill').addClass('bi-bookmark');
                    $btn.removeClass('active');
                    
                    // Add AJAX request to server to remove bookmark
                    showToast('Removed from bookmarks', 'info');
                }
            });
            
            // Share functionality
            $('.share-btn').click(function() {
                const id = $(this).data('id');
                const title = $(this).data('title');
                
                // Build share link
                const shareUrl = `${window.location.origin}${window.location.pathname}?id=${id}`;
                
                // Try to use Web Share API
                if (navigator.share) {
                    navigator.share({
                        title: title,
                        text: `View announcement: ${title}`,
                        url: shareUrl,
                    })
                    .then(() => showToast('Shared successfully', 'success'))
                    .catch((error) => console.log('Share failed:', error));
                } else {
                    // Copy link to clipboard
                    navigator.clipboard.writeText(shareUrl).then(
                        function() {
                            showToast('Link copied to clipboard', 'success');
                        },
                        function() {
                            // If copy fails, show link for manual copy
                            prompt('Copy this link to share:', shareUrl);
                        }
                    );
                }
            });
            
            // Show toast message
            function showToast(message, type = 'info') {
                // Remove previous toast
                $('.toast').remove();
                
                // Set different colors for different types
                const bgClass = type === 'success' ? 'bg-success' : 
                                type === 'warning' ? 'bg-warning' :
                                type === 'danger' ? 'bg-danger' : 'bg-info';
                
                // Create toast HTML
                const toastHtml = `
                    <div class="toast align-items-center ${bgClass} text-white position-fixed bottom-0 end-0 m-3" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                ${message}
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `;
                
                // Add to page and show
                $('body').append(toastHtml);
                const toastElement = document.querySelector('.toast');
                const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
                toast.show();
            }
            
            // Check URL parameters, if specific ID is present, auto-scroll to that announcement
            const urlParams = new URLSearchParams(window.location.search);
            const highlightId = urlParams.get('id');
            
            if (highlightId) {
                const targetAnnouncement = document.getElementById('announcement-' + highlightId);
                if (targetAnnouncement) {
                    targetAnnouncement.classList.add('border', 'border-primary');
                    targetAnnouncement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Add highlight animation
                    setTimeout(() => {
                        targetAnnouncement.style.transition = 'background-color 2s';
                        targetAnnouncement.style.backgroundColor = '#e7f0ff';
                        setTimeout(() => {
                            targetAnnouncement.style.backgroundColor = '';
                        }, 2000);
                    }, 500);
                }
            }
        });
    </script>
</body>
</html>