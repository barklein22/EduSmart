<?php
/**
 * EduSmart — LTI 1.1 Launch Endpoint
 *
 * Moodle POSTs here when a student or teacher opens EduSmart as an External Tool.
 * This file validates the OAuth 1.0 signature, looks up the user by username,
 * starts a normal EduSmart session, and redirects to the appropriate home page.
 *
 * Nothing else in the project is changed by this file.
 */

require_once __DIR__ . '/php/lti_config.php';
require_once __DIR__ . '/php/db.php';

session_start();

// ── DEMO MODE ────────────────────────────────────────────────────────────────
// Only active when LTI_DEMO_MODE_ENABLED is true (set in php/lti_config.php).
// Trigger via browser: GET /lti_launch.php?demo_lti=1&role=teacher
//                  or: GET /lti_launch.php?demo_lti=1&role=student
// This path skips OAuth entirely and uses placeholder values.
// NEVER point Moodle at a server where LTI_DEMO_MODE_ENABLED is true.
if (LTI_DEMO_MODE_ENABLED
    && $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['demo_lti'])
) {
    $demo_role = ($_GET['role'] ?? 'student') === 'teacher' ? 'teacher' : 'student';

    $_SESSION['user_id']           = null; // no real DB user in demo
    $_SESSION['lti_login']         = true;
    $_SESSION['role']              = $demo_role;
    $_SESSION['first_name']        = 'Demo';
    $_SESSION['last_name']         = $demo_role === 'teacher' ? 'Teacher' : 'Student';
    $_SESSION['email']             = 'demo@example.com';
    $_SESSION['course_name']       = 'Demo Course';
    $_SESSION['lti_context_title'] = 'Demo LTI Context';

    if ($demo_role === 'teacher') {
        header('Location: teacher-home.php');
    } else {
        header('Location: student-home.php');
    }
    exit;
}
// ── END DEMO MODE ─────────────────────────────────────────────────────────────


// ── PRODUCTION MODE ───────────────────────────────────────────────────────────

// Only accept POST from Moodle.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    lti_error('Invalid request method. This endpoint only accepts POST requests from Moodle.');
}

$params = $_POST;

// 1. Validate required LTI fields.
$required_fields = [
    'lti_version',
    'lti_message_type',
    'oauth_consumer_key',
    'user_id',
    'roles',
];
foreach ($required_fields as $field) {
    if (empty($params[$field])) {
        lti_error('Missing required LTI parameter: ' . htmlspecialchars($field));
    }
}

if ($params['lti_version'] !== 'LTI-1p0') {
    lti_error('Unsupported LTI version: ' . htmlspecialchars($params['lti_version'])
        . '. This endpoint supports LTI 1.1 (LTI-1p0) only.');
}

if ($params['lti_message_type'] !== 'basic-lti-launch-request') {
    lti_error('Unsupported message type: ' . htmlspecialchars($params['lti_message_type']));
}

if ($params['oauth_consumer_key'] !== LTI_CONSUMER_KEY) {
    lti_error('Unknown consumer key. Check the consumer key configured in Moodle.');
}

// 2. Verify OAuth 1.0 HMAC-SHA1 signature.
//
// TODO (production hardening): also validate that oauth_timestamp is within ±300 seconds
//      of the current server time, and store oauth_nonce values in a short-lived cache
//      (e.g. a DB table with a TTL) to prevent replay attacks.
if (!verify_oauth_signature($params, LTI_SHARED_SECRET)) {
    lti_error('OAuth signature verification failed. '
        . 'Check that the shared secret in Moodle matches the one in php/lti_config.php.');
}

// 3. Map Moodle role string to an EduSmart role.
$moodle_role = $params['roles'] ?? '';
$edulti_role  = map_lti_role($moodle_role);
if ($edulti_role === null) {
    lti_error('Unrecognized Moodle role: ' . htmlspecialchars($moodle_role)
        . '. Expected a role containing Instructor, Teacher, Administrator, Learner, or Student.');
}

// 4. Look up the user in the EduSmart users table by username.
//    Moodle sends the login username in ext_user_username.
$moodle_username = trim($params['ext_user_username'] ?? '');
if ($moodle_username === '') {
    lti_error('Moodle did not send a username (ext_user_username is empty). '
        . 'Check the External Tool privacy settings in Moodle — "Send user\'s name" must be set to Always.');
}

$stmt = $conn->prepare('SELECT id, first_name, last_name, role FROM users WHERE username = ?');
$stmt->bind_param('s', $moodle_username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    lti_not_connected_page($moodle_username);
}

// 5. Start the normal EduSmart session for this user.
//    We use the DB values (not Moodle values) as the source of truth for role and name,
//    so that the rest of the application behaves exactly as after a normal login.
$_SESSION['user_id']           = (int) $user['id'];
$_SESSION['first_name']        = $user['first_name'];
$_SESSION['last_name']         = $user['last_name'];
$_SESSION['role']              = $user['role'];
$_SESSION['lti_login']         = true; // marks this session as LTI-originated
$_SESSION['email']             = $params['lis_person_contact_email_primary'] ?? '';
$_SESSION['course_name']       = $params['context_title'] ?? '';
$_SESSION['lti_context_title'] = $params['context_title'] ?? '';

// 6. Redirect to the correct home page based on the DB role.
if ($user['role'] === 'teacher') {
    header('Location: teacher-home.php');
    exit;
} elseif ($user['role'] === 'student') {
    header('Location: student-home.php');
    exit;
} else {
    lti_error('User role "' . htmlspecialchars($user['role']) . '" is not supported for LTI access.');
}


// ── HELPER FUNCTIONS ──────────────────────────────────────────────────────────

/**
 * Verify an OAuth 1.0 HMAC-SHA1 signature for an LTI 1.1 POST launch.
 *
 * Steps follow the OAuth 1.0 spec (RFC 5849) exactly:
 *   1. Remove oauth_signature from the parameter set.
 *   2. Percent-encode (RFC 3986) every key and value.
 *   3. Sort lexicographically and join as key=value pairs with &.
 *   4. Build the base string: METHOD & base_url & normalized_params (all encoded).
 *   5. Build the signing key: secret & (empty token secret).
 *   6. HMAC-SHA1 the base string with the signing key; base64-encode the result.
 *   7. Compare timing-safely with the provided signature.
 */
function verify_oauth_signature(array $params, string $secret): bool
{
    if (empty($params['oauth_signature'])) {
        return false;
    }

    $provided_sig = $params['oauth_signature'];
    unset($params['oauth_signature']);

    // Build normalized parameter string.
    $encoded_pairs = [];
    foreach ($params as $key => $value) {
        $encoded_pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
    }
    sort($encoded_pairs); // lexicographic sort on the encoded strings
    $normalized_params = implode('&', $encoded_pairs);

    // Build the base URL — scheme + host + path, no query string, no default port.
    $base_url = build_base_url();

    // Signature base string.
    $base_string = rawurlencode('POST')
        . '&' . rawurlencode($base_url)
        . '&' . rawurlencode($normalized_params);

    // Signing key: consumer_secret & token_secret (token secret is empty for LTI 1.1).
    $signing_key = rawurlencode($secret) . '&';

    $expected_sig = base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));

    return hash_equals($expected_sig, $provided_sig);
}

/**
 * Build the base URL for the OAuth signature base string.
 * Must be: scheme://host/path  — no port for 80/443, no query string.
 */
function build_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = strtolower($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME']);
    $port   = (int) ($_SERVER['SERVER_PORT'] ?? 80);

    // Strip default ports that are implicit in the scheme.
    if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
        // host already contains the correct value; port is implicit
    } else {
        // Non-standard port — include it if not already in HTTP_HOST.
        if (strpos($host, ':') === false) {
            $host .= ':' . $port;
        }
    }

    // Path only (no query string).
    $path = strtok($_SERVER['REQUEST_URI'], '?');

    return $scheme . '://' . $host . $path;
}

/**
 * Map the comma-separated Moodle roles string to an EduSmart role.
 * Returns 'teacher', 'student', or null if the role cannot be identified.
 * Teacher keywords are checked first because some Moodle configs send both.
 */
function map_lti_role(string $roles): ?string
{
    $lower = strtolower($roles);

    $teacher_keywords = ['instructor', 'teacher', 'administrator'];
    foreach ($teacher_keywords as $kw) {
        if (strpos($lower, $kw) !== false) {
            return 'teacher';
        }
    }

    $student_keywords = ['learner', 'student'];
    foreach ($student_keywords as $kw) {
        if (strpos($lower, $kw) !== false) {
            return 'student';
        }
    }

    return null;
}

/**
 * Render a friendly Hebrew page when the Moodle username has no matching EduSmart account.
 * Uses the existing EduSmart CSS classes so the page looks like a natural part of the site.
 */
function lti_not_connected_page(string $username): void
{
    $safe_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EduSmart - חיבור חשבון</title>
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<main class="page-wrapper welcome-page">

    <section class="simple-header"></section>

    <section class="center-section">

        <div class="logo-wrap logo-home">
            <img src="images/logo.png" alt="EduSmart Logo" class="logo-img large" />
        </div>

        <h1 class="page-title">החשבון לא מחובר</h1>

        <p style="color:var(--primary);font-size:15px;margin:0 0 6px;text-align:center;">
            שם המשתמש שהתקבל מ-MAMA:
        </p>
        <p style="font-weight:700;font-size:17px;color:var(--text-main);text-align:center;margin:0 0 22px;word-break:break-all;">
            <?php echo $safe_username; ?>
        </p>

        <div style="background:#fff;border-radius:14px;padding:20px 22px;margin-bottom:22px;box-shadow:0 2px 12px rgba(0,0,0,.08);text-align:center;">
            <p style="margin:0 0 4px;color:var(--primary);font-size:16px;line-height:1.7;">
                המשתמש שלך עדיין לא מחובר ל-EduSmart.
                כדי להתחבר דרך MAMA, עליך להירשם תחילה לאתר EduSmart
                עם אותו שם משתמש שבו אתה מתחבר ל-MAMA.
            </p>
        </div>

        <div style="background:#fffbee;border:2px solid var(--gold);border-radius:14px;padding:18px 22px;margin-bottom:28px;text-align:center;">
            <p style="margin:0;color:var(--text-main);font-size:15px;line-height:1.7;">
                <strong>חשוב:</strong>
                בשדה שם המשתמש בהרשמה ל-EduSmart יש להזין בדיוק את אותו שם משתמש
                שבו אתה מתחבר ל-MAMA.
                אין צורך להשתמש באותה סיסמה של MAMA —
                ניתן לבחור סיסמה חדשה ונפרדת ל-EduSmart.
            </p>
        </div>

        <div class="auth-buttons">
            <button class="gold-btn" type="button" onclick="location.href='register-student.html'">הרשמה כתלמיד</button>
            <button class="gold-btn" type="button" onclick="location.href='register-teacher.html'">הרשמה כמורה</button>
        </div>

    </section>
</main>
</body>
</html>
    <?php
    exit;
}

/**
 * Render a clear error page and stop execution.
 * $message may contain safe HTML (e.g. <strong>) but must NOT contain
 * any user-supplied content unless already escaped with htmlspecialchars().
 */
function lti_error(string $message): void
{
    http_response_code(400);
    ?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EduSmart — LTI Error</title>
    <link rel="stylesheet" href="css/style.css" />
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .lti-error-box {
            background: #fff;
            border: 2px solid #e74c3c;
            border-radius: 12px;
            padding: 40px 50px;
            max-width: 560px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,.1);
        }
        .lti-error-box h2 { color: #e74c3c; margin-bottom: 16px; }
        .lti-error-box p  { color: #333; line-height: 1.6; }
        .lti-error-box a  { color: #3498db; }
    </style>
</head>
<body>
    <div class="lti-error-box">
        <h2>LTI Launch Error</h2>
        <p><?php echo $message; ?></p>
        <p style="margin-top:24px;font-size:.9em;color:#888;">
            If this problem persists, contact your EduSmart administrator.
        </p>
    </div>
</body>
</html>
    <?php
    exit;
}
