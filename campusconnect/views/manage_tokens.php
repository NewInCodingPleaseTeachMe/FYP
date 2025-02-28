<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Access restriction: only administrators can manage tokens
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit("Access denied!");
}

$notification = "";
$notificationType = "";

// Generate Token
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["generate_token"])) {
    try {
        $quantity = isset($_POST["token_quantity"]) ? intval($_POST["token_quantity"]) : 1;
        $quantity = max(1, min($quantity, 50)); // Limit quantity between 1-50
        
        $generated_tokens = [];
        
        for ($i = 0; $i < $quantity; $i++) {
            $new_token = bin2hex(random_bytes(8)); // Generate random Token (16 characters)
            $stmt = $pdo->prepare("INSERT INTO tokens (token, is_used, created_by, created_at) VALUES (?, 0, ?, NOW())");
            $stmt->execute([$new_token, $_SESSION["user_id"]]);
            $generated_tokens[] = $new_token;
        }
        
        $notification = "Successfully generated " . count($generated_tokens) . " new tokens!";
        $notificationType = "success";
        
        // Record activity log
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, entity_type, details, created_at) 
                                  VALUES (?, 'create', 'token', ?, NOW())");
        $log_details = json_encode(['count' => count($generated_tokens)]);
        $log_stmt->execute([$_SESSION["user_id"], $log_details]);
        
    } catch (Exception $e) {
        $notification = "Error generating tokens: " . $e->getMessage();
        $notificationType = "danger";
    }
}

// Copy to clipboard functionality is implemented in frontend JavaScript

// Get Token statistics
$stats_stmt = $pdo->query("SELECT 
                             COUNT(*) as total_tokens,
                             SUM(CASE WHEN is_used = 1 THEN 1 ELSE 0 END) as used_tokens,
                             SUM(CASE WHEN is_used = 0 THEN 1 ELSE 0 END) as unused_tokens,
                             MAX(created_at) as latest_token_date
                           FROM tokens");
$stats = $stats_stmt->fetch();

// Get all Tokens, with filtering support
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT t.*, u.name as created_by_name, u2.name as used_by_name 
          FROM tokens t
          LEFT JOIN users u ON t.created_by = u.id
          LEFT JOIN users u2 ON t.used_by = u2.id
          WHERE 1=1";

$params = [];

if ($filter === 'used') {
    $query .= " AND t.is_used = 1";
} elseif ($filter === 'unused') {
    $query .= " AND t.is_used = 0";
}

if (!empty($search)) {
    $query .= " AND (t.token LIKE ? OR u.name LIKE ? OR u2.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY t.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tokens = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Token Management - CampusConnect</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
        }
        .token-text {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .token-badge {
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
            font-size: 0.9rem;
            padding: 0.35rem 0.65rem;
        }
        .counter-card {
            transition: all 0.3s;
        }
        .counter-card:hover {
            transform: translateY(-5px);
        }
        .counter-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .copy-tooltip {
            position: relative;
            display: inline-block;
        }
        .copy-tooltip .tooltip-text {
            visibility: hidden;
            width: 80px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -40px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .copy-tooltip .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #555 transparent transparent transparent;
        }
        .btn-floating {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1030;
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
                        <a class="nav-link" href="user_list.php">
                            <i class="bi bi-people me-1"></i>User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="#">
                            <i class="bi bi-key-fill me-1"></i>Token Management
                        </a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= htmlspecialchars($_SESSION["name"] ?? "Administrator") ?> 
                        <span class="badge bg-light text-primary ms-1">Admin</span>
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
                <li class="breadcrumb-item active" aria-current="page">Token Management</li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2>
                    <i class="bi bi-key-fill me-2"></i>
                    Registration Token Management
                </h2>
                <p class="text-muted">Manage access tokens required for user registration</p>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#generateTokenModal">
                    <i class="bi bi-plus-circle me-1"></i>Generate New Token
                </button>
            </div>
        </div>

        <?php if (!empty($notification)): ?>
            <div class="alert alert-<?= $notificationType ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $notificationType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill me-2"></i>
                <?= $notification ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card counter-card h-100 bg-primary text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Tokens</h5>
                        <p class="counter-value mb-0"><?= $stats['total_tokens'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card counter-card h-100 bg-success text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Unused</h5>
                        <p class="counter-value mb-0"><?= $stats['unused_tokens'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card counter-card h-100 bg-info text-white">
                    <div class="card-body text-center">
                        <h5 class="card-title">Used</h5>
                        <p class="counter-value mb-0"><?= $stats['used_tokens'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Search tokens or users..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select name="filter" class="form-select">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Tokens</option>
                            <option value="used" <?= $filter === 'used' ? 'selected' : '' ?>>Used</option>
                            <option value="unused" <?= $filter === 'unused' ? 'selected' : '' ?>>Unused</option>
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

        <!-- Display All Tokens -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list me-2"></i>Token List</h5>
                <div>
                    <?php if (count($tokens) > 0): ?>
                        <span class="badge bg-primary"><?= count($tokens) ?> Tokens</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($tokens)): ?>
                    <div class="p-4 text-center">
                        <div class="text-muted mb-3">
                            <i class="bi bi-search" style="font-size: 3rem;"></i>
                        </div>
                        <h5>No Tokens Found</h5>
                        <p class="mb-0">Try adjusting your search criteria or generate new tokens</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" style="width: 60px;">ID</th>
                                    <th scope="col">Token</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Created By</th>
                                    <th scope="col">Created On</th>
                                    <th scope="col">Used By</th>
                                    <th scope="col">Used On</th>
                                    <th scope="col" style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tokens as $token): ?>
                                    <tr>
                                        <td>#<?= $token["id"] ?></td>
                                        <td class="token-text">
                                            <?php if (!$token["is_used"]): ?>
                                                <span class="copy-tooltip" onclick="copyToClipboard('<?= htmlspecialchars($token["token"]) ?>', this)">
                                                    <?= htmlspecialchars($token["token"]) ?>
                                                    <span class="tooltip-text" id="tooltip-<?= $token["id"] ?>">Copy</span>
                                                </span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($token["token"]) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($token["is_used"]): ?>
                                                <span class="badge bg-success">Used</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Unused</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($token["created_by_name"] ?? "System") ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($token["created_at"])) ?></td>
                                        <td><?= $token["is_used"] ? htmlspecialchars($token["used_by_name"] ?? "Unknown") : "-" ?></td>
                                        <td><?= $token["used_at"] ? date('Y-m-d H:i', strtotime($token["used_at"])) : "-" ?></td>
                                        <td>
                                            <?php if (!$token["is_used"]): ?>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" onclick="copyToClipboard('<?= htmlspecialchars($token["token"]) ?>', this)">
                                                        <i class="bi bi-clipboard"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteTokenModal" data-token-id="<?= $token["id"] ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bottom Button -->
        <div class="mt-4 text-end">
            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>Return to Dashboard
            </a>
        </div>
    </div>

    <!-- Generate Token Modal -->
    <div class="modal fade" id="generateTokenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-plus-circle me-2"></i>Generate New Token
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                    <div class="modal-body">
                        <p>Generate new registration tokens. Each token can only be used once.</p>
                        <div class="mb-3">
                            <label for="token_quantity" class="form-label">Quantity</label>
                            <input type="number" name="token_quantity" id="token_quantity" class="form-control" min="1" max="50" value="1">
                            <div class="form-text">You can generate up to 50 tokens at once</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="generate_token" class="btn btn-success">
                            <i class="bi bi-check-circle me-1"></i>Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Token Confirmation Modal -->
    <div class="modal fade" id="deleteTokenModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this token? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="deleteTokenLink" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Confirm Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Generate Button (visible on mobile devices) -->
    <div class="btn-floating d-md-none">
        <button type="button" class="btn btn-success btn-lg rounded-circle shadow" data-bs-toggle="modal" data-bs-target="#generateTokenModal">
            <i class="bi bi-plus-lg"></i>
        </button>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Script -->
    <script>
        // Copy to clipboard functionality
        function copyToClipboard(text, element) {
            navigator.clipboard.writeText(text).then(function() {
                // Show tooltip
                const tooltipElement = element.querySelector('.tooltip-text') || 
                                      document.getElementById('tooltip-' + element.closest('tr').cells[0].textContent.substring(1));
                
                if (tooltipElement) {
                    tooltipElement.style.visibility = 'visible';
                    tooltipElement.style.opacity = '1';
                    tooltipElement.textContent = 'Copied!';
                    
                    setTimeout(function() {
                        tooltipElement.style.visibility = 'hidden';
                        tooltipElement.style.opacity = '0';
                        setTimeout(function() {
                            tooltipElement.textContent = 'Copy';
                        }, 300);
                    }, 2000);
                }
            }).catch(function(err) {
                console.error('Unable to copy: ', err);
            });
        }
        
        // Set delete token link
        document.addEventListener('DOMContentLoaded', function() {
            const deleteTokenModal = document.getElementById('deleteTokenModal');
            if (deleteTokenModal) {
                deleteTokenModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const tokenId = button.getAttribute('data-token-id');
                    const deleteLink = document.getElementById('deleteTokenLink');
                    deleteLink.href = '../modules/delete_token.php?id=' + tokenId;
                });
            }
        });
    </script>
</body>
</html>