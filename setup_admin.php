<?php
require_once "php/db.php";

// Create system_settings table
$conn->query("
    CREATE TABLE IF NOT EXISTS system_settings (
        setting_key   VARCHAR(100) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL
    )
");

// Insert default institution_type (skip if already exists)
$conn->query("
    INSERT IGNORE INTO system_settings (setting_key, setting_value)
    VALUES ('institution_type', 'school')
");

// Create admin user with password Admin1234 (skip if username already exists)
$hashed = password_hash('Admin1234', PASSWORD_DEFAULT);
$stmt = $conn->prepare("
    INSERT IGNORE INTO users (first_name, last_name, username, password, role, school, class_name, subject)
    VALUES ('מנהל', 'מערכת', 'admin', ?, 'admin', '', '', '')
");
$stmt->bind_param("s", $hashed);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
  <meta charset="UTF-8"/>
  <title>Admin Setup</title>
  <style>
    body { font-family: sans-serif; text-align: center; padding: 40px; direction: rtl; }
    .box { max-width: 480px; margin: 0 auto; background: #f6f6f6; border-radius: 16px; padding: 32px; }
    .warn { color: #d9534f; font-weight: 700; margin-top: 20px; }
  </style>
</head>
<body>
<div class="box">
  <h2>✅ הגדרת מנהל הושלמה</h2>
  <p>טבלת ההגדרות נוצרה (או כבר קיימת).</p>
  <?php if ($affected > 0): ?>
    <p>משתמש מנהל נוסף בהצלחה.</p>
  <?php else: ?>
    <p>משתמש admin כבר קיים — לא שונה כלום.</p>
  <?php endif; ?>
  <p><strong>שם משתמש:</strong> admin</p>
  <p><strong>סיסמה:</strong> Admin1234</p>
  <p class="warn">⚠️ חשוב: מחק את הקובץ setup_admin.php מהשרת לאחר הכניסה הראשונה!</p>
  <a href="login.html">→ לדף הכניסה</a>
</div>
</body>
</html>
