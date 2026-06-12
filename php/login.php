<?php
session_start();
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}

$username = trim($_POST["username"] ?? "");
$password = trim($_POST["password"] ?? "");
$role = trim($_POST["role"] ?? "");

if ($username === "" || $password === "" || $role === "") {
    header("Location: ../login.html?error=" . urlencode("נא למלא את כל השדות"));
    exit;
}

$stmt = $conn->prepare("
    SELECT id, first_name, last_name, username, password, role
    FROM users
    WHERE username = ? AND role = ?
");
$stmt->bind_param("ss", $username, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header("Location: ../login.html?error=" . urlencode("שם המשתמש או הסיסמה שגויים"));
    exit;
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user["password"])) {
    header("Location: ../login.html?error=" . urlencode("שם המשתמש או הסיסמה שגויים"));
    exit;
}

$_SESSION["user_id"] = $user["id"];
$_SESSION["first_name"] = $user["first_name"];
$_SESSION["last_name"] = $user["last_name"];
$_SESSION["username"] = $user["username"];
$_SESSION["role"] = $user["role"];

if ($user["role"] === "student") {
    header("Location: ../student-home.php");
} elseif ($user["role"] === "admin") {
    header("Location: ../admin-dashboard.php");
} else {
    header("Location: ../teacher-home.php");
}
exit;
?>