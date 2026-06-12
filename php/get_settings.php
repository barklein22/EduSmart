<?php
require_once "db.php";
header("Content-Type: application/json");

$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'institution_type'");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$institution_type = $row["setting_value"] ?? "school";
$stmt->close();

echo json_encode(["institution_type" => $institution_type]);
