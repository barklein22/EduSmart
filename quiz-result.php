<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "student") {
    header("Location: login.html");
    exit;
}

if (!isset($_SESSION["quiz_result"])) {
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduSmart - תוצאות</title>
    <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<main class="page-wrapper" style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:80vh;text-align:center;">
    <img src="images/logo.png" alt="EduSmart Logo" class="logo-img" style="margin-bottom:24px;">
    <p style="font-size:1.2rem;margin-bottom:28px;">איזה כיף, אין שום מבדק באופק</p>
    <button class="gold-btn" type="button" onclick="goTo('student-home.php')">חזרה לדף הבית</button>
</main>
<script src="js/main.js"></script>
</body>
</html>
<?php
    exit;
}

$result = $_SESSION["quiz_result"];
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduSmart - תוצאות</title>
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

    <h1 class="page-title">תוצאות המבדק</h1>

    <div class="result-card">
        <p class="result-line">ענית נכון על <strong><?php echo $result["correct"]; ?></strong> שאלות</p>
        <p class="result-line">ענית לא נכון על <strong><?php echo $result["wrong"]; ?></strong> שאלות</p>
        <p class="result-line">סה״כ שאלות: <strong><?php echo $result["total"]; ?></strong></p>
    </div>

    <div class="quiz-actions">
        <?php if ($result["wrong"] > 0): ?>
            <button class="gold-btn" type="button" onclick="goTo('question-feedback.php')">
                לצפייה בהסברים
            </button>
        <?php endif; ?>

        <button class="secondary-btn" type="button" onclick="goTo('student-home.php')">
            חזרה לדף הבית
        </button>
    </div>
</main>

<footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
<script src="js/main.js"></script>
</body>
</html>