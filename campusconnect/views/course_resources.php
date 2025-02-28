<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Ensure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit("Please login first!");
}

$user_id = $_SESSION["user_id"];
$role = $_SESSION["role"];

// Get filter parameters
$course_filter = isset($_GET['course']) ? $_GET['course'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query - Modified JOIN to LEFT JOIN to ensure resources display even without matching users
$query = "SELECT cr.id, cr.course_id, c.course_name, 
          COALESCE(cr.resource_name, cr.file_name) AS file_name, 
          cr.file_type, cr.file_size, 
          COALESCE(cr.resource_description, cr.description) AS description, 
          cr.uploaded_at, cr.file_path, cr.resource_url, cr.is_link,
          COALESCE(u.name, 'System') AS uploader_name,
          cr.uploaded_by
          FROM course_resources cr
          JOIN courses c ON cr.course_id = c.id
          LEFT JOIN users u ON cr.uploaded_by = u.id
          WHERE 1=1";

$params = [];

if (!empty($course_filter)) {
    $query .= " AND cr.course_id = ?";
    $params[] = $course_filter;
}

if (!empty($type_filter)) {
    $query .= " AND cr.file_type = ?";
    $params[] = $type_filter;
}

if (!empty($search)) {
    $query .= " AND (COALESCE(cr.resource_name, cr.file_name) LIKE ? OR c.course_name LIKE ? OR COALESCE(cr.resource_description, cr.description) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY cr.uploaded_at DESC";

// Try to execute the query
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $resources = $stmt->fetchAll();
    
    // Debug information - uncomment if needed
    // echo "<pre>Query: " . $query . "</pre>";
    // echo "<pre>Parameters: " . print_r($params, true) . "</pre>";
    // echo "<pre>Result count: " . count($resources) . "</pre>";
} catch (PDOException $e) {
    // Display error during debugging - comment out in production
    // echo "<div class='alert alert-danger'>Database error: " . $e->getMessage() . "</div>";
    $resources = [];
}

// Get all courses for filtering
$courses_stmt = $pdo->query("SELECT id, course_name FROM courses ORDER BY course_name");
$courses = $courses_stmt->fetchAll();

// Get all different file types for filtering
$types_stmt = $pdo->query("SELECT DISTINCT file_type FROM course_resources ORDER BY file_type");
$file_types = $types_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get corresponding icon and color based on file type
function getFileTypeInfo($type) {
    $type = strtolower($type);
    
    $icons = [
        'pdf' => ['icon' => 'bi-file-earmark-pdf', 'color' => 'danger'],
        'doc' => ['icon' => 'bi-file-earmark-word', 'color' => 'primary'],
        'docx' => ['icon' => 'bi-file-earmark-word', 'color' => 'primary'],
        'xls' => ['icon' => 'bi-file-earmark-excel', 'color' => 'success'],
        'xlsx' => ['icon' => 'bi-file-earmark-excel', 'color' => 'success'],
        'ppt' => ['icon' => 'bi-file-earmark-ppt', 'color' => 'warning'],
        'pptx' => ['icon' => 'bi-file-earmark-ppt', 'color' => 'warning'],
        'txt' => ['icon' => 'bi-file-earmark-text', 'color' => 'secondary'],
        'zip' => ['icon' => 'bi-file-earmark-zip', 'color' => 'info'],
        'rar' => ['icon' => 'bi-file-earmark-zip', 'color' => 'info'],
        'jpg' => ['icon' => 'bi-file-earmark-image', 'color' => 'primary'],
        'jpeg' => ['icon' => 'bi-file-earmark-image', 'color' => 'primary'],
        'png' => ['icon' => 'bi-file-earmark-image', 'color' => 'primary'],
        'mp4' => ['icon' => 'bi-file-earmark-play', 'color' => 'danger'],
        'mp3' => ['icon' => 'bi-file-earmark-music', 'color' => 'warning'],
        'link' => ['icon' => 'bi-link', 'color' => 'info'],
    ];
    
    return isset($icons[$type]) ? $icons[$type] : ['icon' => 'bi-file-earmark', 'color' => 'secondary'];
}

// Format file size
function formatFileSize($bytes) {
    if (empty($bytes) || !is_numeric($bytes)) {
        return 'Unknown';
    }
    
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Resources - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .resource-card {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .file-icon {
            font-size: 2rem;
        }
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        .dropdown-menu {
            max-height: 300px;
            overflow-y: auto;
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
                        <a class="nav-link active" href="#">
                            <i class="bi bi-folder2-open me-1"></i>Course Resources
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-folder2-open me-2"></i>Course Resources</h2>
            <div>
            <?php if ($role === "teacher" || $role === "admin"): ?>
                <a href="upload_resource.php" class="btn btn-success">
                    <i class="bi bi-cloud-upload me-1"></i>Upload New Resource
                </a>
                <?php endif; ?>
                <a href="../dashboard.php" class="btn btn-secondary ms-2">
                    <i class="bi bi-house-door me-1"></i>Return to Dashboard
                </a>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select name="course" class="form-select">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">File Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <?php foreach ($file_types as $type): ?>
                                <option value="<?= $type ?>" <?= $type_filter == $type ? 'selected' : '' ?>>
                                    <?= strtoupper($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search filename or description..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="d-grid w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-filter me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($resources)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                No course resources found matching your criteria.
            </div>
        <?php else: ?>
            <!-- Table View -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-table me-2"></i>Resource List</h5>
                    <span class="badge bg-primary"><?= count($resources) ?> resources</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="width: 60px;"></th>
                                    <th scope="col">Filename</th>
                                    <th scope="col">Course</th>
                                    <th scope="col">Size</th>
                                    <th scope="col">Uploader</th>
                                    <th scope="col">Upload Time</th>
                                    <th scope="col" style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resources as $resource): 
                                    $file_info = getFileTypeInfo($resource['file_type']);
                                ?>
                                <tr>
                                    <td class="text-center">
                                        <i class="bi <?= $file_info['icon'] ?> text-<?= $file_info['color'] ?> file-icon"></i>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($resource['file_name']) ?></div>
                                        <?php if (!empty($resource['description'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($resource['description']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($resource['course_name']) ?></td>
                                    <td>
                                        <?php if (!empty($resource['file_size'])): ?>
                                            <?= formatFileSize($resource['file_size']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($resource['uploader_name']) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($resource['uploaded_at'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($resource['is_link'] == 1 && !empty($resource['resource_url'])): ?>
                                                <a href="<?= htmlspecialchars($resource['resource_url']) ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i>Visit Link
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= htmlspecialchars($resource['file_path']) ?>" download class="btn btn-sm btn-success">
                                                    <i class="bi bi-download me-1"></i>Download
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($role === "admin" || ($role === "teacher" && $_SESSION["user_id"] == $resource['uploaded_by'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        data-bs-toggle="modal" data-bs-target="#deleteResourceModal<?= $resource['id'] ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                
                                                <!-- Delete Resource Confirmation Modal -->
                                                <div class="modal fade" id="deleteResourceModal<?= $resource['id'] ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete the following resource?</p>
                                                                <div class="alert alert-warning">
                                                                    <strong><?= htmlspecialchars($resource['file_name']) ?></strong>
                                                                </div>
                                                                <p class="text-danger mb-0">
                                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                                                    This action cannot be undone. The file will be permanently deleted.
                                                                </p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <a href="delete_resource.php?id=<?= $resource['id'] ?>" class="btn btn-danger">
                                                                    Confirm Delete
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
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
                    <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Card View</h5>
                </div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
                        <?php foreach ($resources as $resource): 
                            $file_info = getFileTypeInfo($resource['file_type']);
                        ?>
                        <div class="col">
                            <div class="card h-100 resource-card">
                                <div class="card-header bg-light text-center py-3">
                                    <i class="bi <?= $file_info['icon'] ?> text-<?= $file_info['color'] ?>" style="font-size: 3rem;"></i>
                                    <div class="mt-2">
                                        <span class="badge bg-<?= $file_info['color'] ?>"><?= strtoupper($resource['file_type']) ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title text-truncate" title="<?= htmlspecialchars($resource['file_name']) ?>">
                                        <?= htmlspecialchars($resource['file_name']) ?>
                                    </h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <i class="bi bi-journal-richtext me-1"></i>
                                            <?= htmlspecialchars($resource['course_name']) ?>
                                        </small>
                                    </p>
                                    <?php if (!empty($resource['description'])): ?>
                                        <p class="card-text small">
                                            <?= htmlspecialchars($resource['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="small text-muted">
                                            <?php if (!empty($resource['file_size'])): ?>
                                                <i class="bi bi-hdd me-1"></i><?= formatFileSize($resource['file_size']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted">
                                            <i class="bi bi-calendar me-1"></i>
                                            <?= date('Y-m-d', strtotime($resource['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <div class="d-grid">
                                        <?php if ($resource['is_link'] == 1 && !empty($resource['resource_url'])): ?>
                                            <a href="<?= htmlspecialchars($resource['resource_url']) ?>" target="_blank" class="btn btn-primary">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>Visit Link
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= htmlspecialchars($resource['file_path']) ?>" download class="btn btn-primary">
                                                <i class="bi bi-download me-1"></i>Download File
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($role === "admin" || ($role === "teacher" && $_SESSION["user_id"] == $resource['uploaded_by'])): ?>
                                        <div class="d-grid mt-2">
                                            <button type="button" class="btn btn-outline-danger btn-sm" 
                                                    data-bs-toggle="modal" data-bs-target="#deleteCardResourceModal<?= $resource['id'] ?>">
                                                <i class="bi bi-trash me-1"></i>Delete Resource
                                            </button>
                                        </div>
                                        
                                        <!-- Card View Delete Resource Confirmation Modal -->
                                        <div class="modal fade" id="deleteCardResourceModal<?= $resource['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Deletion</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Are you sure you want to delete the following resource?</p>
                                                        <div class="alert alert-warning">
                                                            <strong><?= htmlspecialchars($resource['file_name']) ?></strong>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <a href="delete_resource.php?id=<?= $resource['id'] ?>" class="btn btn-danger">
                                                            Confirm Delete
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
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