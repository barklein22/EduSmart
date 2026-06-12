<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.html");
    exit;
}

require_once "php/db.php";

$teacher_id = $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT 
        q.id,
        q.topic,
        q.grade,
        COUNT(DISTINCT qa.student_id) AS assigned_students,
        COUNT(DISTINCT CASE WHEN qa.status = 'completed' THEN qa.student_id END) AS completed_students,
        ROUND(AVG(
            CASE
                WHEN qr.total_questions > 0 THEN (qr.correct_count / qr.total_questions) * 100
                ELSE NULL
            END
        )) AS avg_success
    FROM quizzes q
    LEFT JOIN quiz_assignments qa ON q.id = qa.quiz_id
    LEFT JOIN quiz_results qr ON qa.id = qr.assignment_id
    WHERE q.teacher_id = ?
    GROUP BY q.id, q.topic, q.grade
    ORDER BY q.id DESC
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
    <title>EduSmart - ממוצעי הצלחה</title>
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

    <h1 class="page-title">ממוצעי הצלחה לפי מבדק</h1>

    <section class="section-block">
        <?php if ($result->num_rows === 0): ?>
            <div class="report-card">
                <p class="report-text">אין עדיין מבדקים להצגה.</p>
            </div>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="report-card">
                    <h3 class="report-title"><?php echo htmlspecialchars($row["topic"]); ?></h3>
                    <p class="report-text">כיתה: <?php echo htmlspecialchars($row["grade"]); ?></p>
                    <p class="report-text">ממוצע הצלחה: <?php echo (int)($row["avg_success"] ?? 0); ?>%</p>
                    <p class="report-text">תלמידים שקיבלו: <?php echo (int)$row["assigned_students"]; ?></p>
                    <p class="report-text">תלמידים שביצעו: <?php echo (int)$row["completed_students"]; ?></p>

                    <div style="margin-top:12px;">
                        <button class="gold-btn action-btn" type="button"
                            onclick="goTo('reports.php?quiz_id=<?php echo $row["id"]; ?>')">
                            לדוח המלא
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