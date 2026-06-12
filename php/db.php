<?php
$host = "localhost";
$dbname = "isbarkl2_edusmart_db";
$username = "isbarkl2_admin";
$password = "texkryur123";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>