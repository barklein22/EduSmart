<?php
session_start();

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "teacher") {
    header("Location: login.html");
    exit;
}

require_once "php/db.php";

$teacher_id = $_SESSION["user_id"];
$selected_quiz_id = intval($_GET["quiz_id"] ?? 0);

/* שליפת כל המבדקים של המורה */
$stmt = $conn->prepare("
    SELECT id, topic, grade
    FROM quizzes
    WHERE teacher_id = ?
    ORDER BY id DESC
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$quizzes_result = $stmt->get_result();

$teacher_quizzes = [];
while ($row = $quizzes_result->fetch_assoc()) {
    $teacher_quizzes[] = $row;
}
$stmt->close();

/* אם לא נבחר מבדק - נבחר את האחרון */
if ($selected_quiz_id === 0 && !empty($teacher_quizzes)) {
    $selected_quiz_id = $teacher_quizzes[0]["id"];
}

/* בדיקה שהמבדק שייך למורה */
$selected_quiz = null;
foreach ($teacher_quizzes as $quiz) {
    if ((int)$quiz["id"] === $selected_quiz_id) {
        $selected_quiz = $quiz;
        break;
    }
}

/* ברירת מחדל */
$completed_students = 0;
$avg_success = 0;
$hard_questions = null;
$students_need_help = null;
$top_students = null;

if ($selected_quiz) {
    /* כמה תלמידים ביצעו את המבדק */
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS completed_students
        FROM quiz_assignments
        WHERE quiz_id = ? AND status = 'completed'
    ");
    $stmt->bind_param("i", $selected_quiz_id);
    $stmt->execute();
    $completed_students_result = $stmt->get_result()->fetch_assoc();
    $completed_students = $completed_students_result["completed_students"] ?? 0;
    $stmt->close();

    /* ממוצע הצלחה למבדק */
    $stmt = $conn->prepare("
        SELECT AVG(
            CASE
                WHEN qr.total_questions > 0 THEN (qr.correct_count / qr.total_questions) * 100
                ELSE 0
            END
        ) AS avg_success
        FROM quiz_results qr
        JOIN quiz_assignments qa ON qr.assignment_id = qa.id
        WHERE qa.quiz_id = ?
    ");
    $stmt->bind_param("i", $selected_quiz_id);
    $stmt->execute();
    $avg_success_result = $stmt->get_result()->fetch_assoc();
    $avg_success = round($avg_success_result["avg_success"] ?? 0);
    $stmt->close();

    /* שאלות שטעו בהן הכי הרבה במבדק */
    $stmt = $conn->prepare("
        SELECT 
            q.question_text,
            COUNT(sa.id) AS wrong_count
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        JOIN quiz_assignments qa ON sa.assignment_id = qa.id
        WHERE qa.quiz_id = ? AND sa.is_correct = 0
        GROUP BY q.id, q.question_text
        ORDER BY wrong_count DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $selected_quiz_id);
    $stmt->execute();
    $hard_questions = $stmt->get_result();
    $stmt->close();

    /* תלמידים שצריכים חיזוק */
    $stmt = $conn->prepare("
        SELECT 
            u.first_name,
            u.last_name,
            qr.wrong_count
        FROM quiz_results qr
        JOIN quiz_assignments qa ON qr.assignment_id = qa.id
        JOIN users u ON qa.student_id = u.id
        WHERE qa.quiz_id = ?
        ORDER BY qr.wrong_count DESC, u.first_name ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $selected_quiz_id);
    $stmt->execute();
    $students_need_help = $stmt->get_result();
    $stmt->close();

    /* תלמידים מצטיינים */
    $stmt = $conn->prepare("
        SELECT 
            u.first_name,
            u.last_name,
            qr.correct_count
        FROM quiz_results qr
        JOIN quiz_assignments qa ON qr.assignment_id = qa.id
        JOIN users u ON qa.student_id = u.id
        WHERE qa.quiz_id = ?
        ORDER BY qr.correct_count DESC, u.first_name ASC
        LIMIT 5
    ");
    $stmt->bind_param("i", $selected_quiz_id);
    $stmt->execute();
    $top_students = $stmt->get_result();
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>EduSmart - דוחות</title>
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

    <h1 class="page-title">דוחות</h1>

    <section class="section-block">
        <div class="section-header">
            <h2>בחירת מבדק</h2>
        </div>

        <?php if (empty($teacher_quizzes)): ?>
            <div class="report-card">
                <p class="report-text">עדיין לא יצרת מבדקים.</p>
            </div>
        <?php else: ?>
            <form method="GET" class="form-card">
                <div class="form-group">
                    <label for="quiz_id">בחרי מבדק</label>
                    <select name="quiz_id" id="quiz_id" onchange="this.form.submit()">
                        <?php foreach ($teacher_quizzes as $quiz): ?>
                            <option value="<?php echo $quiz["id"]; ?>" <?php echo ($quiz["id"] == $selected_quiz_id) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($quiz["topic"] . " | כיתה " . $quiz["grade"]); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($selected_quiz): ?>
        <section class="stats-row">
            <div class="stat-card">
                <span class="stat-number"><?php echo $completed_students; ?></span>
                <span class="stat-label">תלמידים ביצעו</span>
            </div>

            <div class="stat-card">
                <span class="stat-number"><?php echo $avg_success; ?>%</span>
                <span class="stat-label">ממוצע הצלחה</span>
            </div>
        </section>

        <section class="section-block">
            <div class="section-header">
                <h2>שאלות שבהן טעו הכי הרבה</h2>
            </div>

            <?php if ($hard_questions && $hard_questions->num_rows > 0): ?>
                <?php while ($row = $hard_questions->fetch_assoc()): ?>
                    <div class="report-card">
                        <h3 class="report-title"><?php echo htmlspecialchars($row["question_text"]); ?></h3>
                        <p class="report-text"><?php echo $row["wrong_count"]; ?> תלמידים טעו בשאלה זו</p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="report-card">
                    <p class="report-text">אין עדיין נתונים להצגה.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="section-block">
            <div class="section-header">
                <h2>תלמידים שזקוקים לחיזוק</h2>
            </div>

            <?php if ($students_need_help && $students_need_help->num_rows > 0): ?>
                <?php while ($row = $students_need_help->fetch_assoc()): ?>
                    <div class="report-card">
                        <h3 class="report-title">
                            <?php echo htmlspecialchars($row["first_name"] . " " . $row["last_name"]); ?>
                        </h3>
                        <p class="report-text"><?php echo $row["wrong_count"]; ?> תשובות שגויות</p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="report-card">
                    <p class="report-text">אין עדיין נתונים להצגה.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="section-block">
            <div class="section-header">
                <h2>תלמידים מצטיינים</h2>
            </div>

            <?php if ($top_students && $top_students->num_rows > 0): ?>
                <?php while ($row = $top_students->fetch_assoc()): ?>
                    <div class="report-card">
                        <h3 class="report-title">
                            <?php echo htmlspecialchars($row["first_name"] . " " . $row["last_name"]); ?>
                        </h3>
                        <p class="report-text"><?php echo $row["correct_count"]; ?> תשובות נכונות</p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="report-card">
                    <p class="report-text">אין עדיין נתונים להצגה.</p>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <nav class="bottom-nav">
        <button class="nav-item" type="button" onclick="goTo('teacher-home.php')">בית</button>
        <button class="nav-item" type="button" onclick="goTo('create-quiz.html')">יצירה</button>
        <button class="nav-item active" type="button">דוחות</button>
    </nav>

</main>

<footer class="site-footer"><p>© כל הזכויות שמורות לצוות EduSmart</p></footer>
<script src="js/main.js"></script>
</body>
</html>