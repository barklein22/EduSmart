<?php
session_start();
require_once "db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    die("גישה לא מורשית");
}

if (!isset($_SESSION["pending_quiz"])) {
    die("אין מבדק לשמירה");
}

$quiz = $_SESSION["pending_quiz"];
$questions = $quiz["questions"];

$teacher_id = $_SESSION["user_id"];
$topic = $quiz["topic"];
$grade = $quiz["grade"];
$difficulty = $quiz["difficulty"];
$num_questions = $quiz["num_questions"];
$target_class = $quiz["target_class"];

try {
    $conn->begin_transaction();

    // 1. שמירת המבדק
    $stmt = $conn->prepare("
        INSERT INTO quizzes (teacher_id, topic, grade, difficulty, num_questions, target_class)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssis", $teacher_id, $topic, $grade, $difficulty, $num_questions, $target_class);
    $stmt->execute();
    $quiz_id = $stmt->insert_id;
    $stmt->close();

    // 2. שמירת השאלות
    $stmt = $conn->prepare("
        INSERT INTO questions (quiz_id, question_text, option_1, option_2, option_3, option_4, correct_option, explanation)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($questions as $q) {
        $stmt->bind_param(
            "isssssis",
            $quiz_id,
            $q["question_text"],
            $q["option_1"],
            $q["option_2"],
            $q["option_3"],
            $q["option_4"],
            $q["correct_option"],
            $q["explanation"]
        );
        $stmt->execute();
    }
    $stmt->close();

    // 3. שליפת כל התלמידים מהכיתה שנבחרה
    $stmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE role = 'student' AND class_name = ?
    ");
    $stmt->bind_param("s", $target_class);
    $stmt->execute();
    $result = $stmt->get_result();

    $student_ids = [];
    while ($row = $result->fetch_assoc()) {
        $student_ids[] = $row["id"];
    }
    $stmt->close();

    if (empty($student_ids)) {
        $conn->rollback();
        echo '<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EduSmart - שגיאה</title>
  <link rel="stylesheet" href="../css/style.css"/>
  <style>
    .error-page-wrap {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 80vh;
      text-align: center;
      padding: 32px 20px;
    }
    .error-icon {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: #ffe5e5;
      color: #d9534f;
      font-size: 2.4rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 24px;
    }
    .error-title {
      font-size: 1.4rem;
      font-weight: 700;
      color: #222;
      margin-bottom: 12px;
    }
    .error-desc {
      font-size: 1rem;
      color: #555;
      max-width: 320px;
      line-height: 1.6;
      margin-bottom: 28px;
    }
  </style>
</head>
<body>
<main class="page-wrapper">
  <div class="error-page-wrap">
    <div class="error-icon">✕</div>
    <p class="error-title">אין תלמידים רשומים לכיתה זו</p>
    <p class="error-desc">לא ניתן לשמור את המבדק מכיוון שאין תלמידים רשומים בכיתה שנבחרה.</p>
    <button class="gold-btn" type="button" onclick="location.href=\'../create-quiz.html\'">חזרה ליצירת מבדק</button>
  </div>
</main>
</body>
</html>';
        exit;
    }

    // 4. שיוך המבדק לכל תלמיד
    if (!empty($student_ids)) {
        $stmt = $conn->prepare("
            INSERT INTO quiz_assignments (quiz_id, student_id, status)
            VALUES (?, ?, ?)
        ");

        $status = "not_started";

        foreach ($student_ids as $student_id) {
            $stmt->bind_param("iis", $quiz_id, $student_id, $status);
            $stmt->execute();
        }

        $stmt->close();
    }

    $conn->commit();

    unset($_SESSION["pending_quiz"]);

    header("Location: ../teacher-home.php");
    exit;

} catch (Exception $e) {
    $conn->rollback();
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
    <p class="err-title">שגיאה בשמירת המבדק</p>
    <p class="err-desc">אירעה שגיאה בעת שמירת המבדק. אפשר לנסות שוב.</p>
    <button class="gold-btn" type="button" onclick="location.href=\'../create-quiz.html\'">חזרה ליצירת מבדק</button>
  </div>
</main>
</body>
</html>';
    exit;
}
?>