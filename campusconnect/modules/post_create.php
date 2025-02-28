<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

if (!isset($_SESSION["role"]) || ($_SESSION["role"] != "teacher" && $_SESSION["role"] != "admin")) {
    header("Location: ../views/forum.php?error=permission_denied");
    exit;
}


if (!isset($_SESSION["user_id"])) {
    die('<div class="container mt-5"><div class="alert alert-danger">请先登录！</div></div>');
}

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $course_id = $_POST["course_id"];
    $title = trim($_POST["title"]);
    $content = trim($_POST["content"]);
    $user_id = $_SESSION["user_id"];

    $stmt = $pdo->prepare("INSERT INTO discussion_posts (course_id, user_id, title, content) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$course_id, $user_id, $title, $content])) {
        $success_message = "帖子发布成功！";
    } else {
        $error_message = "帖子发布失败，请重试！";
    }
}

// 获取课程列表（如果需要）
$courses = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM courses ORDER BY name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "获取课程列表失败: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发布讨论帖</title>
    <!-- 引入Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">发布新讨论帖</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?>
                                <a href="../views/discussion.php" class="alert-link">返回讨论区</a>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="mb-3">
                                <label for="course_id" class="form-label">选择课程</label>
                                <select class="form-select" id="course_id" name="course_id" required>
                                    <option value="" selected disabled>-- 请选择课程 --</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>">
                                            <?php echo htmlspecialchars($course['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">标题</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="content" class="form-label">内容</label>
                                <textarea class="form-control" id="content" name="content" rows="6" required></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="../views/discussion.php" class="btn btn-secondary me-md-2">取消</a>
                                <button type="submit" class="btn btn-primary">发布帖子</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 引入Bootstrap JS和Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
</body>
</html>