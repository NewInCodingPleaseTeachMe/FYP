<?php
session_set_cookie_params(0, '/');
// Ensure session_start is at the top of the file
session_start();
require_once "../config/db.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    // Fetch user information
    $stmt = $pdo->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC); // Use FETCH_ASSOC to avoid duplicate keys

    if ($user && password_verify($password, $user["password"])) {
        // **Ensure session variables are stored correctly**
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["name"]    = $user["name"] ?? "Unknown User"; // Consistently use "name"
        $_SESSION["role"]    = $user["role"];

        // **Test if session variables are stored successfully**
        error_log("SESSION name: " . $_SESSION["name"]);

        // Redirect to the dashboard
        header("Location: /campusconnect/dashboard.php");
        exit;
    } else {
        // Login failed, redirect to login page with an error
        header("Location: /campusconnect/views/login.php?error=1");
        exit;
    }
}
?>
