<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: login.html");
    exit;
}

require_once "php/db.php";

$student_id = $_SESSION["user_id"];
$quiz_id = intval($_GET["quiz_id"] ?? 0);

if ($quiz_id <= 0) {
    header("Location: student-home.php");
    exit;
}

// בדיקה שהמבדק באמת שויך לתלמיד
$stmt = $conn->prepare("
    SELECT q.id, q.topic, qa.status
    FROM quiz_assignments qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.quiz_id = ? AND qa.student_id = ?
");
$stmt->bind_param("ii", $quiz_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: student-home.php");
    exit;
}

$quiz = $result->fetch_assoc();
$stmt->close();

if ($quiz["status"] === "completed") {
    header("Location: student-home.php");
    exit;
}

// שליפת שאלות
$stmt = $conn->prepare("
    SELECT id, question_text, option_1, option_2, option_3, option_4
    FROM questions
    WHERE quiz_id = ?
    ORDER BY id ASC
");
$stmt->bind_param("i", $quiz_id);
$stmt->execute();
$questions_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduSmart - מבדק</title>
    <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<nav class="site-navbar">
  <div class="navbar-inner">
    <a class="navbar-logout" href="php/logout.php">התנתקות</a>
    <div class="navbar-links">
      <button class="nav-link" type="button" onclick="goTo('student-home.php')">דף הבית</button>
    </div>
    <img src="images/logo.png" alt="EduSmart" class="navbar-logo-img">
  </div>
</nav>
<main class="page-wrapper">
    <section class="simple-header">
        <button class="back-btn" type="button" onclick="goTo('student-home.php')">‹</button>
    </section>

    <div class="logo-wrap">
        <img src="images/logo.png" alt="EduSmart Logo" class="logo-img">
    </div>

    <h1 class="page-title"><?php echo htmlspecialchars($quiz["topic"]); ?></h1>

    <form action="php/submit_quiz.php" method="POST">
        <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">

        <?php $question_number = 1; ?>
        <?php while ($q = $questions_result->fetch_assoc()): ?>
            <div class="question-card">
                <p class="question-number">שאלה <?php echo $question_number; ?></p>
                <p class="question-text"><?php echo htmlspecialchars($q["question_text"]); ?></p>

                <div class="answers">
                    <label class="answer-btn">
                        <input type="radio" name="answers[<?php echo $q["id"]; ?>]" value="1" required>
                        <?php echo htmlspecialchars($q["option_1"]); ?>
                    </label>

                    <label class="answer-btn">
                        <input type="radio" name="answers[<?php echo $q["id"]; ?>]" value="2">
                        <?php echo htmlspecialchars($q["option_2"]); ?>
                    </label>

                    <label class="answer-btn">
                        <input type="radio" name="answers[<?php echo $q["id"]; ?>]" value="3">
                        <?php echo htmlspecialchars($q["option_3"]); ?>
                    </label>

                    <label class="answer-btn">
                        <input type="radio" name="answers[<?php echo $q["id"]; ?>]" value="4">
                        <?php echo htmlspecialchars($q["option_4"]); ?>
                    </label>
                </div>
            </div>
            <?php $question_number++; ?>
        <?php endwhile; ?>

        <div class="quiz-actions">
            <button class="gold-btn" type="submit">סיום מבדק</button>
        </div>
    </form>
</main>

<footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
<script src="js/main.js"></script>
</body>
</html>