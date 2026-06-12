<?php
session_start();
require_once "db.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request");
}

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    die("גישה לא מורשית");
}

$student_id = $_SESSION["user_id"];
$quiz_id = intval($_POST["quiz_id"] ?? 0);
$answers = $_POST["answers"] ?? [];

if ($quiz_id <= 0 || empty($answers)) {
    die("נתונים חסרים");
}

try {
    $conn->begin_transaction();

    // שליפת assignment_id של התלמיד למבדק הזה
    $stmt = $conn->prepare("
        SELECT id
        FROM quiz_assignments
        WHERE student_id = ? AND quiz_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $student_id, $quiz_id);
    $stmt->execute();
    $assignment_result = $stmt->get_result();

    if ($assignment_result->num_rows === 0) {
        $conn->rollback();
        header("Location: ../student-home.php");
        exit;
    }

    $assignment = $assignment_result->fetch_assoc();
    $assignment_id = $assignment["id"];
    $stmt->close();

    // מניעת הגשה כפולה
    $stmt = $conn->prepare("SELECT id FROM quiz_results WHERE assignment_id = ? LIMIT 1");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $conn->rollback();
        header("Location: ../student-home.php");
        exit;
    }
    $stmt->close();

    // שליפת שאלות המבדק
    $stmt = $conn->prepare("
        SELECT id, question_text, correct_option, explanation,
               option_1, option_2, option_3, option_4
        FROM questions
        WHERE quiz_id = ?
        ORDER BY id ASC
    ");
    $stmt->bind_param("i", $quiz_id);
    $stmt->execute();
    $questions_result = $stmt->get_result();

    $correct_count = 0;
    $wrong_count = 0;
    $total_questions = 0;
    $wrong_answers = [];

    while ($q = $questions_result->fetch_assoc()) {
        $question_id = $q["id"];
        $correct_option = (int)$q["correct_option"];
        $selected_option = isset($answers[$question_id]) ? (int)$answers[$question_id] : 0;

        $is_correct = ($selected_option === $correct_option) ? 1 : 0;
        $total_questions++;

        // שמירת תשובת תלמיד
        $stmt_insert = $conn->prepare("
            INSERT INTO student_answers (assignment_id, question_id, selected_option, is_correct)
            VALUES (?, ?, ?, ?)
        ");
        $stmt_insert->bind_param("iiii", $assignment_id, $question_id, $selected_option, $is_correct);
        $stmt_insert->execute();
        $stmt_insert->close();

        if ($is_correct) {
            $correct_count++;
        } else {
            $wrong_count++;

            $options = [
                1 => $q["option_1"],
                2 => $q["option_2"],
                3 => $q["option_3"],
                4 => $q["option_4"]
            ];

            $wrong_answers[] = [
                "question" => $q["question_text"],
                "selected" => $options[$selected_option] ?? "",
                "correct" => $options[$correct_option] ?? "",
                "explanation" => $q["explanation"]
            ];
        }
    }
    $stmt->close();

    // שמירת סיכום תוצאה
    $stmt = $conn->prepare("
        INSERT INTO quiz_results (assignment_id, correct_count, wrong_count, total_questions)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiii", $assignment_id, $correct_count, $wrong_count, $total_questions);
    $stmt->execute();
    $stmt->close();

    // עדכון סטטוס המבדק
    $stmt = $conn->prepare("
        UPDATE quiz_assignments
        SET status = 'completed'
        WHERE id = ?
    ");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // שליפת נושא המבדק לדף המשוב
    $stmt_topic = $conn->prepare("
        SELECT q.topic FROM quiz_assignments qa
        JOIN quizzes q ON qa.quiz_id = q.id
        WHERE qa.id = ?
    ");
    $stmt_topic->bind_param("i", $assignment_id);
    $stmt_topic->execute();
    $topic_row = $stmt_topic->get_result()->fetch_assoc();
    $quiz_topic = $topic_row["topic"] ?? "";
    $stmt_topic->close();

    $_SESSION["quiz_result"] = [
        "correct" => $correct_count,
        "wrong" => $wrong_count,
        "total" => $total_questions,
        "wrong_answers" => $wrong_answers,
        "topic" => $quiz_topic
    ];

    header("Location: ../quiz-result.php");
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
    <p class="err-title">שגיאה בשמירת התשובות</p>
    <p class="err-desc">אירעה שגיאה בעת שמירת המבדק. אפשר לנסות שוב.</p>
    <button class="gold-btn" type="button" onclick="history.back()">חזרה למבדק</button>
  </div>
</main>
</body>
</html>';
    exit;
}
?>