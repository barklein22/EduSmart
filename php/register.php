<?php
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}

function reg_error($role, $msg) {
    if ($role === 'teacher') $page = '../register-teacher.html';
    elseif ($role === 'student') $page = '../register-student.html';
    else $page = '../choose-role.html';
    header("Location: $page?error=" . urlencode($msg));
    exit;
}

$first_name = trim($_POST["first_name"] ?? "");
$last_name = trim($_POST["last_name"] ?? "");
$username = trim($_POST["username"] ?? "");
$password = trim($_POST["password"] ?? "");
$role = trim($_POST["role"] ?? "");
$school = trim($_POST["school"] ?? "");
$class_name = trim($_POST["class_name"] ?? "");
$subject = trim($_POST["subject"] ?? "");

if (
    $first_name === "" ||
    $last_name === "" ||
    $username === "" ||
    $password === "" ||
    $role === "" ||
    $school === ""
) {
    reg_error($role, "נא למלא את כל שדות החובה");
}

if ($role !== "student" && $role !== "teacher") {
    reg_error($role, "סוג משתמש לא תקין");
}

if ($role === "student" && $class_name === "") {
    reg_error($role, "נא לבחור כיתה");
}

if ($role === "teacher" && $subject === "") {
    reg_error($role, "נא לבחור מקצוע");
}

$check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$check_stmt->bind_param("s", $username);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    reg_error($role, "שם המשתמש כבר קיים במערכת");
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users (first_name, last_name, username, password, role, school, class_name, subject)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssss",
    $first_name,
    $last_name,
    $username,
    $hashed_password,
    $role,
    $school,
    $class_name,
    $subject
);

if ($stmt->execute()) {
    $new_student_id = $stmt->insert_id;

    if ($role === "student") {
        $assign_stmt = $conn->prepare("
            INSERT INTO quiz_assignments (quiz_id, student_id, status)
            SELECT q.id, ?, 'not_started'
            FROM quizzes q
            WHERE q.target_class = ?
            AND NOT EXISTS (
                SELECT 1 FROM quiz_assignments qa
                WHERE qa.quiz_id = q.id AND qa.student_id = ?
            )
        ");
        $assign_stmt->bind_param("isi", $new_student_id, $class_name, $new_student_id);
        $assign_stmt->execute();
        $assign_stmt->close();
    }

    header("Location: ../login.html");
    exit;
} else {
    reg_error($role, "שגיאה בהרשמה, אנא נסה שנית");
}

$stmt->close();
$check_stmt->close();
$conn->close();
?>