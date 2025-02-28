<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// ✅ Ensure the user is logged in
if (!isset($_SESSION["user_id"])) {
    die("Please log in first!");
}

$user_id = $_SESSION["user_id"];
$post_id = $_POST["post_id"] ?? null;
$parent_id = $_POST["parent_id"] ?? 0; // ✅ Default to 0, indicating a top-level comment
$content = trim($_POST["content"] ?? "");

// ✅ Validate data
if (!$post_id || empty($content)) {
    die("Comment content cannot be empty!");
}

// ✅ Check if `post_id` exists
$stmt = $pdo->prepare("SELECT id FROM discussion_posts WHERE id = ?");
$stmt->execute([$post_id]);
$post_exists = $stmt->fetch();

if (!$post_exists) {
    die("Invalid post ID!");
}

// ✅ Ensure `parent_id` is valid
if ($parent_id > 0) {
    $stmt = $pdo->prepare("SELECT id FROM discussion_comments WHERE id = ?");
    $stmt->execute([$parent_id]);
    $parent_exists = $stmt->fetch();

    if (!$parent_exists) {
        die("Invalid parent comment ID!");
    }
}

// ✅ Insert the comment
$stmt = $pdo->prepare("INSERT INTO discussion_comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
$stmt->execute([$post_id, $user_id, $content, $parent_id]);

// ✅ Redirect back to the post page
header("Location: ../views/view_post.php?post_id=" . $post_id);
exit;
