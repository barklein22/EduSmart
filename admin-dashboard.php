<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.html");
    exit;
}

require_once "php/db.php";

$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'institution_type'");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$current_type = $row["setting_value"] ?? "school";
$stmt->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduSmart - ממשק מנהל</title>
    <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<nav class="site-navbar">
  <div class="navbar-inner">
    <a class="navbar-logout" href="php/logout.php">התנתקות</a>
    <div class="navbar-links">
      <button class="nav-link" type="button" onclick="goTo('admin-dashboard.php')">ממשק מנהל</button>
    </div>
    <img src="images/logo.png" alt="EduSmart" class="navbar-logo-img">
  </div>
</nav>
<main class="page-wrapper">
    <div class="logo-wrap">
        <img src="images/logo.png" alt="EduSmart Logo" class="logo-img">
    </div>

    <h1 class="page-title">ממשק מנהל</h1>

    <?php if (isset($_GET["saved"])): ?>
        <div class="admin-success">ההגדרות נשמרו בהצלחה ✅</div>
    <?php endif; ?>
    <?php if (isset($_GET["error"])): ?>
        <div class="admin-error"><?php echo htmlspecialchars($_GET["error"]); ?></div>
    <?php endif; ?>

    <div class="admin-card">
        <h2 class="admin-section-title">סוג מוסד לימודי</h2>
        <p class="admin-section-desc">
            הגדרה זו משפיעה על אפשרויות הכיתה ביצירת מבדק ובהרשמת תלמידים.
        </p>

        <form action="php/save_settings.php" method="POST">
            <div class="admin-radio-group">
                <label class="admin-radio-label">
                    <input type="radio" name="institution_type" value="school"
                        <?php echo $current_type === "school" ? "checked" : ""; ?>>
                    בית ספר (כיתות ז–יב)
                </label>
                <label class="admin-radio-label">
                    <input type="radio" name="institution_type" value="university"
                        <?php echo $current_type === "university" ? "checked" : ""; ?>>
                    אוניברסיטה / מכללה (קורסים / קבוצות לימוד)
                </label>
            </div>
            <button type="submit" class="gold-btn small-btn" style="margin-top: 20px;">שמירת הגדרות</button>
        </form>
    </div>
</main>

<footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
<script src="js/main.js"></script>
</body>
</html>
