<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// ✅ Ensure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../views/login.php");
    exit("Please login first!");
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

$course_id = $_GET["course_id"] ?? null;
if (!$course_id) {
    header("Location: course_list.php");
    exit("Invalid course ID!");
}

// ✅ Check if course exists and get details
$stmt = $pdo->prepare("SELECT c.*, u.name as teacher_name 
                       FROM courses c
                       JOIN users u ON c.teacher_id = u.id
                       WHERE c.id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: course_list.php");
    exit("Course does not exist!");
}

// ✅ Check if user has permission to view (admin, teacher, or student enrolled in the course)
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
    exit("You do not have permission to view this course's member list!");
}

// ✅ Get course member list
$stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.role, uc.enrolled_at 
                       FROM user_courses uc
                       JOIN users u ON uc.user_id = u.id 
                       WHERE uc.course_id = ?
                       ORDER BY u.role, u.name");
$stmt->execute([$course_id]);
$members = $stmt->fetchAll();

// Calculate statistics
$totalMembers = count($members);
$studentCount = 0;
$teacherCount = 0;

foreach ($members as $member) {
    if ($member['role'] == 'student') {
        $studentCount++;
    } elseif ($member['role'] == 'teacher') {
        $teacherCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Members - <?= htmlspecialchars($course['course_name']) ?> - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .member-card {
            transition: all 0.3s ease;
        }
        .member-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .teacher-row {
            background-color: rgba(13, 110, 253, 0.05);
        }
        .avatar {
            width: 40px;
            height: 40px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #495057;
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
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["name"] ?? "User") ?> 
                        <span class="badge bg-light text-primary ms-1"><?= ucfirst($role) ?></span>
                    </span>
                    <a href="../logout.php" class="btn btn-outline-light btn-sm">
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
                <li class="breadcrumb-item active" aria-current="page">Course Members</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="mb-3">
                    <i class="bi bi-people-fill me-2"></i>
                    <?= htmlspecialchars($course['course_name']) ?> Course Members
                </h2>
                <p class="text-muted">
                    <span class="badge bg-primary me-2"><?= $course['course_code'] ?></span>
                    <i class="bi bi-person-badge me-1"></i>Course Instructor: <?= htmlspecialchars($course['teacher_name']) ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="view_course.php?id=<?= $course_id ?>" class="btn btn-outline-primary">
                    <i class="bi bi-info-circle me-1"></i>Course Details
                </a>
                <a href="course_list.php" class="btn btn-secondary ms-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to Course List
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total Members</h6>
                                <h2 class="mb-0"><?= $totalMembers ?></h2>
                            </div>
                            <i class="bi bi-people-fill fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Students</h6>
                                <h2 class="mb-0"><?= $studentCount ?></h2>
                            </div>
                            <i class="bi bi-mortarboard-fill fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Teachers</h6>
                                <h2 class="mb-0"><?= $teacherCount ?></h2>
                            </div>
                            <i class="bi bi-person-badge-fill fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (empty($members)) : ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                No members have joined this course yet.
            </div>
        <?php else : ?>
            <!-- Table View -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Member List</h5>
                    <?php if ($role === "admin" || ($role === "teacher" && $user_id == $course['teacher_id'])): ?>
                        <a href="add_member.php?course_id=<?= $course_id ?>" class="btn btn-sm btn-success">
                            <i class="bi bi-plus-circle me-1"></i>Add Member
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="width: 50px;"></th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Join Date</th>
                                    <?php if ($role === "admin" || ($role === "teacher" && $user_id == $course['teacher_id'])): ?>
                                        <th scope="col" style="width: 100px;">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($members as $member) : 
                                    $isTeacher = $member['role'] === 'teacher';
                                    $rowClass = $isTeacher ? 'teacher-row' : '';
                                    $roleBadge = $isTeacher ? 
                                        '<span class="badge bg-info">Teacher</span>' : 
                                        '<span class="badge bg-success">Student</span>';
                                    $initial = mb_substr($member['name'], 0, 1, 'UTF-8');
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="text-center">
                                            <div class="avatar" style="background-color: <?= $isTeacher ? '#cfe2ff' : '#d1e7dd' ?>;">
                                                <?= $initial ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($member['name']) ?>
                                            <?php if ($member['id'] == $course['teacher_id']): ?>
                                                <span class="badge bg-primary">Lead Instructor</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($member['email']) ?></td>
                                        <td><?= $roleBadge ?></td>
                                        <td>
                                            <?php if (isset($member['enrolled_at'])): ?>
                                                <?= date('Y-m-d', strtotime($member['enrolled_at'])) ?>
                                            <?php else: ?>
                                                --
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($role === "admin" || ($role === "teacher" && $user_id == $course['teacher_id'])): ?>
                                            <td>
                                                <?php if ($member['id'] != $course['teacher_id']): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" data-bs-target="#removeMemberModal<?= $member['id'] ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Remove Member Confirmation Modal -->
                                                    <div class="modal fade" id="removeMemberModal<?= $member['id'] ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Confirm Member Removal</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to remove the following member from the course?</p>
                                                                    <div class="alert alert-warning">
                                                                        <strong><?= htmlspecialchars($member['name']) ?></strong>
                                                                        (<?= htmlspecialchars($member['email']) ?>)
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <a href="remove_member.php?course_id=<?= $course_id ?>&user_id=<?= $member['id'] ?>" 
                                                                       class="btn btn-danger">
                                                                        Confirm Removal
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Card View -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Members Card View</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($members as $member) : 
                            $isTeacher = $member['role'] === 'teacher';
                            $cardClass = $isTeacher ? 'border-primary' : '';
                            $headerClass = $isTeacher ? 'bg-primary text-white' : 'bg-light';
                            $initial = mb_substr($member['name'], 0, 1, 'UTF-8');
                        ?>
                            <div class="col-md-4 col-lg-3">
                                <div class="card member-card h-100 <?= $cardClass ?>">
                                    <div class="card-header <?= $headerClass ?> d-flex align-items-center">
                                        <div class="avatar me-2" style="background-color: <?= $isTeacher ? '#cfe2ff' : '#d1e7dd' ?>; color: <?= $isTeacher ? '#0d6efd' : '#198754' ?>">
                                            <?= $initial ?>
                                        </div>
                                        <div>
                                            <?= htmlspecialchars($member['name']) ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <p class="card-text mb-1">
                                            <i class="bi bi-envelope me-1"></i>
                                            <?= htmlspecialchars($member['email']) ?>
                                        </p>
                                        <p class="card-text mb-1">
                                            <i class="bi bi-person-badge me-1"></i>
                                            <?= $isTeacher ? 'Teacher' : 'Student' ?>
                                            <?php if ($member['id'] == $course['teacher_id']): ?>
                                                <span class="badge bg-primary">Lead Instructor</span>
                                            <?php endif; ?>
                                        </p>
                                        <?php if (isset($member['enrolled_at'])): ?>
                                            <p class="card-text text-muted small">
                                                <i class="bi bi-calendar-check me-1"></i>
                                                Joined on <?= date('Y-m-d', strtotime($member['enrolled_at'])) ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (($role === "admin" || ($role === "teacher" && $user_id == $course['teacher_id'])) && $member['id'] != $course['teacher_id']): ?>
                                        <div class="card-footer bg-white border-top-0 text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" data-bs-target="#removeMemberCardModal<?= $member['id'] ?>">
                                                <i class="bi bi-person-dash me-1"></i>Remove
                                            </button>
                                            
                                            <!-- Card View Remove Member Confirmation Modal -->
                                            <div class="modal fade" id="removeMemberCardModal<?= $member['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Confirm Member Removal</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to remove the following member from the course?</p>
                                                            <div class="alert alert-warning">
                                                                <strong><?= htmlspecialchars($member['name']) ?></strong>
                                                                (<?= htmlspecialchars($member['email']) ?>)
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <a href="remove_member.php?course_id=<?= $course_id ?>&user_id=<?= $member['id'] ?>" 
                                                               class="btn btn-danger">
                                                                Confirm Removal
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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