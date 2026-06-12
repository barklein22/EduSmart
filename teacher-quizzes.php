<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.html");
    exit;
}

require_once "php/db.php";

$teacher_id = $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT id, topic, grade, difficulty, num_questions, target_class
    FROM quizzes
    WHERE teacher_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduSmart - המבדקים שלי</title>
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
<main class="page-wrapper dashboard-page">

    <section class="simple-header">
        <button class="back-btn" type="button" onclick="goTo('teacher-home.php')">‹</button>
    </section>

    <div class="logo-wrap">
        <img src="images/logo.png" alt="EduSmart Logo" class="logo-img">
    </div>

    <h1 class="page-title">המבדקים שיצרתי</h1>

    <section class="section-block">
        <?php if ($result->num_rows === 0): ?>
            <div class="report-card">
                <p class="report-text">עדיין לא יצרת מבדקים.</p>
            </div>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="report-card">
                    <h3 class="report-title"><?php echo htmlspecialchars($row["topic"]); ?></h3>
                    <p class="report-text">כיתה: <?php echo htmlspecialchars($row["grade"]); ?></p>
                    <p class="report-text">רמת קושי: <?php echo htmlspecialchars($row["difficulty"]); ?></p>
                    <p class="report-text">מספר שאלות: <?php echo htmlspecialchars($row["num_questions"]); ?></p>
                    <p class="report-text">נשלח לכיתה: <?php echo htmlspecialchars($row["target_class"]); ?></p>

                    <div style="margin-top:12px;">
                        <button class="gold-btn action-btn" type="button"
                            onclick="goTo('reports.php?quiz_id=<?php echo $row["id"]; ?>')">
                            לצפייה בדוח
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </section>

</main>

<footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
<script src="js/main.js"></script>
</body>
</html>