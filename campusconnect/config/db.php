<?php
$host = "localhost";
$dbname = "campusconnect";
$username = "root";  // 默认XAMPP的MySQL用户
$password = "";      // XAMPP默认没有密码

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}
?>
