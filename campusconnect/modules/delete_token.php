<?php
session_set_cookie_params(0, '/');
session_start();
require_once "../config/db.php";

// **🔐 Restrict access**
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    die("Access denied!");
}

// Get Token ID
if (!isset($_GET["id"])) {
    die("Missing Token ID!");
}

$token_id = $_GET["id"];

// **Only delete unused tokens**
$stmt = $pdo->prepare("DELETE FROM tokens WHERE id = ? AND is_used = 0");
$stmt->execute([$token_id]);

// ✅ Redirect back to the token management page after deletion
header("Location: ../views/manage_tokens.php?message=deleted");
exit;
