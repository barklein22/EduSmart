<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.html");
    exit;
}

if (!isset($_SESSION["pending_quiz"])) {
    header("Location: create-quiz.html");
    exit;
}

$quiz = $_SESSION["pending_quiz"];
$questions = $quiz["questions"];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>EduSmart - תצוגה מקדימה</title>
  <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<nav class="site-navbar">
  <div class="navbar-inner">
    <a class="navbar-logout" href="php/logout.php">התנתקות</a>
    <div class="navbar-links">
      <button class="nav-link" type="button" onclick="goTo('teacher-home.php')">דף הבית</button>
      <button class="nav-link" type="button" onclick="goTo('teacher-quizzes.php')">המבדקים שלי</button>
      <button class="nav-link" type="button" onclick="goTo('create-quiz.html')">צור מבדק</button>
      <button class="nav-link" type="button" onclick="goTo('reports.php')">דוחות</button>
    </div>
    <img src="images/logo.png" alt="EduSmart" class="navbar-logo-img">
  </div>
</nav>
  <main class="page-wrapper preview-page">
    <section class="simple-header">
      <button class="back-btn" type="button" onclick="goTo('create-quiz.html')">‹</button>
    </section>

    <div class="logo-wrap">
      <img src="images/logo.png" alt="EduSmart Logo" class="logo-img">
    </div>

    <h1 class="page-title">תצוגה מקדימה</h1>

    <div class="preview-summary">
      <p><strong>נושא:</strong> <?php echo htmlspecialchars($quiz["topic"]); ?></p>
      <p><strong>כיתה:</strong> <?php echo htmlspecialchars($quiz["grade"]); ?></p>
      <p><strong>מספר שאלות:</strong> <?php echo htmlspecialchars($quiz["num_questions"]); ?></p>
      <p><strong>רמת קושי:</strong> <?php echo htmlspecialchars($quiz["difficulty"]); ?></p>
      <p><strong>כיתת יעד:</strong> <?php echo htmlspecialchars($quiz["target_class"]); ?></p>
    </div>

    <?php foreach ($questions as $index => $q): ?>
      <div class="question-card">
        <p class="question-number">שאלה <?php echo $index + 1; ?></p>
        <p class="question-text"><?php echo htmlspecialchars($q["question_text"]); ?></p>

        <div class="preview-options">
          <div class="preview-option <?php echo $q["correct_option"] === 1 ? 'correct-preview' : ''; ?>">
            <?php echo htmlspecialchars($q["option_1"]); ?>
          </div>
          <div class="preview-option <?php echo $q["correct_option"] === 2 ? 'correct-preview' : ''; ?>">
            <?php echo htmlspecialchars($q["option_2"]); ?>
          </div>
          <div class="preview-option <?php echo $q["correct_option"] === 3 ? 'correct-preview' : ''; ?>">
            <?php echo htmlspecialchars($q["option_3"]); ?>
          </div>
          <div class="preview-option <?php echo $q["correct_option"] === 4 ? 'correct-preview' : ''; ?>">
            <?php echo htmlspecialchars($q["option_4"]); ?>
          </div>
        </div>

        <div class="explanation">
          <?php echo htmlspecialchars($q["explanation"]); ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="preview-actions">
      <button class="secondary-btn" type="button" onclick="goTo('create-quiz.html')">חזרה לעריכה</button>

      <form action="php/save_quiz.php" method="POST">
        <button type="submit" class="gold-btn">אישור ושמירה</button>
      </form>
    </div>
  </main>

  <footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
  <script src="js/main.js"></script>
</body>
</html>