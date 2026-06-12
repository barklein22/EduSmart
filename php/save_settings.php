<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.html");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../admin-dashboard.php");
    exit;
}

$institution_type = trim($_POST["institution_type"] ?? "");
if ($institution_type !== "school" && $institution_type !== "university") {
    header("Location: ../admin-dashboard.php?error=" . urlencode("ערך לא תקין"));
    exit;
}

$stmt = $conn->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'institution_type'");
$stmt->bind_param("s", $institution_type);
$stmt->execute();
$stmt->close();

header("Location: ../admin-dashboard.php?saved=1");
exit;
