<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: login.html");
    exit;
}

if (!isset($_SESSION["quiz_result"])) {
    header("Location: student-home.php");
    exit;
}

$wrong_answers = $_SESSION["quiz_result"]["wrong_answers"] ?? [];

$quiz_topic   = $_SESSION["quiz_result"]["topic"] ?? "";
$search_query = urlencode($quiz_topic . " הסבר בעברית");
$youtube_link = !empty($quiz_topic)
    ? "https://www.youtube.com/results?search_query=" . $search_query
    : "https://www.youtube.com/results?search_query=%D7%9C%D7%A9%D7%95%D7%9F+%D7%A2%D7%91%D7%A8%D7%99%D7%AA+%D7%94%D7%A1%D7%91%D7%A8";
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduSmart - הסברים</title>
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
        <button class="back-btn" type="button" onclick="goTo('quiz-result.php')">‹</button>
    </section>

    <div class="logo-wrap">
        <img src="images/logo.png" alt="EduSmart Logo" class="logo-img">
    </div>

    <h1 class="page-title">הסברים לתשובות השגויות</h1>

    <div class="resource-section">
        <p class="resource-title">רוצה להבין את הנושא טוב יותר?</p>
        <p class="resource-subtitle">מצאנו עבורך מקור הסבר שיכול לעזור לך לחזק את החומר.</p>
        <a class="resource-btn" href="<?php echo htmlspecialchars($youtube_link); ?>" target="_blank" rel="noopener">
            לצפייה בהסבר
        </a>
    </div>

    <?php if (empty($wrong_answers)): ?>
        <div class="result-card">
            <p>כל הכבוד! אין תשובות שגויות.</p>
        </div>
    <?php else: ?>
        <?php foreach ($wrong_answers as $index => $item): ?>
            <div class="feedback-card">
                <p class="question-text">שאלה <?php echo $index + 1; ?>:</p>
                <p><?php echo htmlspecialchars($item["question"]); ?></p>
                <p>בחרת: <?php echo htmlspecialchars($item["selected"]); ?> ❌</p>
                <p>תשובה נכונה: <?php echo htmlspecialchars($item["correct"]); ?> ✅</p>
                <div class="explanation">
                    הסבר: <?php echo htmlspecialchars($item["explanation"]); ?>
                </div>
                <div class="learning-tips">
                    <p class="learning-tip">💡 טיפ ללמידה: מומלץ לחזור על הכלל או הנושא שמופיע בשאלה.</p>
                    <p class="learning-tip">✏️ שווה לנסות לפתור שאלה דומה נוספת כדי לחזק את ההבנה והזיכרון של החומר.</p>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="quiz-actions">
        <button class="gold-btn" type="button" onclick="goTo('student-home.php')">
            חזרה לדף הבית
        </button>
    </div>
</main>

<footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
<script src="js/main.js"></script>
</body>
</html>