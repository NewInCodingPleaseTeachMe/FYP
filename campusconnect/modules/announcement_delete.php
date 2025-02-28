<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// Allow both teachers AND admins to delete announcements
if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] !== "teacher" && $_SESSION["role"] !== "admin")) {
    die("Access denied! Please login as a teacher or admin first.");
}

if (isset($_GET["id"])) {
    $id = $_GET["id"];
    
    // 安全检查：确保教师只能删除自己的公告，而管理员可以删除任何公告
    if ($_SESSION["role"] === "teacher") {
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND teacher_id = ?");
        $result = $stmt->execute([$id, $_SESSION["user_id"]]);
    } else {
        // 管理员可以删除任何公告
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
        $result = $stmt->execute([$id]);
    }
    
    if ($stmt->rowCount() > 0) {
        // 重定向到公告列表页面，显示成功消息
        header("Location: ../views/announcements_list.php?deleted=1");
        exit;
    } else {
        // 重定向到公告列表页面，显示错误消息
        header("Location: ../views/announcements_list.php?error=delete");
        exit;
    }
} else {
    // 没有提供ID
    header("Location: ../views/announcements_list.php?error=noid");
    exit;
}
?>