<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: login.html");
    exit;
}

require_once "php/db.php";

$full_name = $_SESSION["first_name"] . " " . $_SESSION["last_name"];
$student_id = $_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT qa.quiz_id, qa.status, q.topic, q.grade, q.num_questions
    FROM quiz_assignments qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.student_id = ?
    ORDER BY qa.quiz_id DESC
");

$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EduSmart - אזור תלמיד</title>
    <link rel="stylesheet" href="css/style.css" />
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
<main class="page-wrapper dashboard-page">

    <section class="top-bar">
        <a class="logout-btn" href="php/logout.php">התנתקות</a>
    </section>

    <div class="logo-wrap small-logo-space">
        <img src="images/logo.png" alt="EduSmart Logo" class="logo-img" />
    </div>

    <section class="welcome-box">
        <h1 class="dashboard-title">שלום <?php echo htmlspecialchars($full_name); ?></h1>
        <p class="dashboard-subtitle">כאן אפשר לראות את המבדקים שנשלחו אליך ולפתור אותם.</p>
    </section>

    <section class="section-block">
        <div class="section-header">
            <h2>המבדקים שלי</h2>
        </div>

        <?php if ($result->num_rows === 0): ?>
            <p>אין לך עדיין מבדקים.</p>
        <?php else: ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                $status_text = "";
                $status_class = "";

                if ($row["status"] === "not_started") {
                    $status_text = "חדש";
                    $status_class = "status-new";
                } elseif ($row["status"] === "completed") {
                    $status_text = "בוצע";
                    $status_class = "status-done";
                } else {
                    $status_text = $row["status"];
                }
                ?>

                <div class="quiz-card">
                    <div class="quiz-card-top">
                        <span class="subject-badge">לשון</span>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo htmlspecialchars($status_text); ?>
                        </span>
                    </div>

                    <h3 class="quiz-card-title">
                        <?php echo htmlspecialchars($row["topic"]); ?>
                    </h3>

                    <p class="quiz-card-text">
                        כיתה <?php echo htmlspecialchars($row["grade"]); ?> |
                        <?php echo htmlspecialchars($row["num_questions"]); ?> שאלות
                    </p>

                    <?php if ($row["status"] === "not_started"): ?>
                        <button class="gold-btn action-btn"
                            type="button"
                            onclick="goTo('take-quiz.php?quiz_id=<?php echo $row["quiz_id"]; ?>')">
                            התחל מבדק
                        </button>
                    <?php else: ?>
                        <button class="secondary-btn action-btn" type="button" disabled>
                            בוצע
                        </button>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </section>

    <nav class="bottom-nav">
        <button class="nav-item active" type="button">בית</button>
        <button class="nav-item" type="button" onclick="goTo('quiz-result.php')">תוצאות</button>
    </nav>

</main>

<footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
<script src="js/main.js"></script>
</body>
</html>