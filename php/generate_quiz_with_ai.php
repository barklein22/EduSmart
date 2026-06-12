<?php
session_start();
require_once __DIR__ . "/../openai_service.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    die("גישה לא מורשית");
}

$topic = trim($_POST["topic"] ?? "");
$grade = trim($_POST["grade"] ?? "");
$num_questions = intval($_POST["num_questions"] ?? 0);
$difficulty = trim($_POST["difficulty"] ?? "");
$target_class = trim($_POST["target_class"] ?? "");

if ($topic === "" || $grade === "" || $num_questions <= 0 || $difficulty === "" || $target_class === "") {
    die("נא למלא את כל השדות");
}

$institution_type = 'school';
try {
    require_once __DIR__ . "/db.php";
    $st = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'institution_type'");
    if ($st && $st->execute()) {
        $row = $st->get_result()->fetch_assoc();
        if (!empty($row['setting_value'])) {
            $institution_type = $row['setting_value'];
        }
        $st->close();
    }
} catch (Throwable $e) {
    $institution_type = 'school';
}

$generated_questions = generateQuestionsWithAI(
    $topic,
    $grade,
    $difficulty,
    $num_questions,
    $institution_type
);

if (!$generated_questions || !is_array($generated_questions)) {
    echo '<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EduSmart - שגיאה</title>
  <link rel="stylesheet" href="../css/style.css"/>
  <style>
    .err-wrap{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:80vh;text-align:center;padding:32px 20px;}
    .err-icon{width:64px;height:64px;border-radius:50%;background:#ffe5e5;color:#d9534f;font-size:2rem;font-weight:700;display:flex;align-items:center;justify-content:center;margin-bottom:20px;}
    .err-title{font-size:1.3rem;font-weight:700;color:#222;margin-bottom:10px;}
    .err-desc{font-size:0.95rem;color:#666;max-width:300px;line-height:1.6;margin-bottom:24px;}
  </style>
</head>
<body>
<main class="page-wrapper">
  <div class="err-wrap">
    <div class="err-icon">✕</div>
    <p class="err-title">יצירת השאלות נכשלה</p>
    <p class="err-desc">ה-AI לא הצליח לייצר שאלות הפעם. אפשר לנסות שוב.</p>
    <button class="gold-btn" type="button" onclick="location.href=\'../create-quiz.html\'">חזרה ליצירת מבדק</button>
  </div>
</main>
</body>
</html>';
    exit;
}

$_SESSION["pending_quiz"] = [
    "teacher_id" => $_SESSION["user_id"],
    "topic" => $topic,
    "grade" => $grade,
    "difficulty" => $difficulty,
    "num_questions" => $num_questions,
    "target_class" => $target_class,
    "questions" => $generated_questions
];

header("Location: ../quiz-preview.php");
exit;
?>