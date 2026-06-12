<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.html");
    exit;
}

require_once "php/db.php";

$teacher_id = $_SESSION["user_id"];
$full_name = $_SESSION["first_name"] . " " . $_SESSION["last_name"];

/* מספר מבדקים שנוצרו */
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_quizzes
    FROM quizzes
    WHERE teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$total_quizzes_result = $stmt->get_result()->fetch_assoc();
$total_quizzes = $total_quizzes_result["total_quizzes"] ?? 0;
$stmt->close();

/* ממוצע הצלחה כללי */
$stmt = $conn->prepare("
    SELECT AVG(
        CASE
            WHEN qr.total_questions > 0 THEN (qr.correct_count / qr.total_questions) * 100
            ELSE 0
        END
    ) AS avg_success
    FROM quiz_results qr
    JOIN quiz_assignments qa ON qr.assignment_id = qa.id
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE q.teacher_id = ?
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$avg_result = $stmt->get_result()->fetch_assoc();
$avg_success = round($avg_result["avg_success"] ?? 0);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EduSmart - אזור מורה</title>
    <link rel="stylesheet" href="css/style.css" />
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
    <section class="top-bar">
        <a class="logout-btn" href="php/logout.php">התנתקות</a>
    </section>

    <div class="logo-wrap small-logo-space">
        <img src="images/logo.png" alt="EduSmart Logo" class="logo-img" />
    </div>

    <section class="welcome-box">
        <h1 class="dashboard-title">שלום <?php echo htmlspecialchars($full_name); ?></h1>
        <p class="dashboard-subtitle">מכאן אפשר ליצור מבדקים, לשלוח לתלמידים ולצפות בדוחות.</p>
    </section>

    <section class="stats-row">
        <div class="stat-card" style="cursor:pointer;" onclick="goTo('success-reports.php')">
            <span class="stat-number"><?php echo $avg_success; ?>%</span>
            <span class="stat-label">ממוצע הצלחה</span>
        </div>

        <div class="stat-card" style="cursor:pointer;" onclick="goTo('teacher-quizzes.php')">
            <span class="stat-number"><?php echo $total_quizzes; ?></span>
            <span class="stat-label">מבדקים נוצרו</span>
        </div>
    </section>

    <section class="section-block">
        <div class="section-header">
            <h2>פעולות מהירות</h2>
        </div>

        <div class="quick-actions">
            <button class="gold-btn action-btn" type="button" onclick="goTo('create-quiz.html')">יצירת מבדק</button>
            <button class="secondary-btn action-btn" type="button" onclick="goTo('reports.php')">צפייה בדוחות</button>
        </div>
    </section>

    <nav class="bottom-nav">
        <button class="nav-item active" type="button">בית</button>
        <button class="nav-item" type="button" onclick="goTo('create-quiz.html')">יצירה</button>
        <button class="nav-item" type="button" onclick="goTo('reports.php')">דוחות</button>
    </nav>
</main>

<footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
<script src="js/main.js"></script>
</body>
</html>