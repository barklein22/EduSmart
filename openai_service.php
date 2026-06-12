<?php
$_openai_key = getenv("OPENAI_API_KEY");

if (!$_openai_key) {
    $envFile = __DIR__ . "/.env";
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), "#") === 0) continue;
            if (strpos($line, "=") !== false) {
                [$name, $value] = explode("=", $line, 2);
                if (trim($name) === "OPENAI_API_KEY") {
                    $_openai_key = trim($value);
                    break;
                }
            }
        }
    }
}

if (!$_openai_key) {
    die("Error: OPENAI_API_KEY is not configured. Add it to /public_html/.env");
}

define("OPENAI_API_KEY", $_openai_key);
unset($_openai_key);

function generateQuestionsWithAI($topic, $grade, $difficulty, $num_questions, $institution_type = 'school') {
    $json_format = <<<'JSON'
{
  "questions": [
    {
      "question_text": "...",
      "option_1": "...",
      "option_2": "...",
      "option_3": "...",
      "option_4": "...",
      "correct_option": 2,
      "explanation": "..."
    }
  ]
}
JSON;

    $rules = <<<'RULES'
כללים:
- כל הטקסטים בעברית
- correct_option הוא מספר שלם בין 1 ל-4 בלבד
- explanation מסביר בקצרה מדוע התשובה נכונה
RULES;

    if ($institution_type === 'university') {
        $intro = "אתה מרצה באקדמיה בישראל. צור {$num_questions} שאלות אמריקאיות (4 אפשרויות תשובה) בנושא \"{$topic}\" עבור {$grade}, ברמת קושי {$difficulty}.\nהשאלות מיועדות לסטודנטים באוניברסיטה או מכללה. השתמש בשפה אקדמית ברמה גבוהה, דרוש הבנה מעמיקה וחשיבה אנליטית.";
    } else {
        $intro = "אתה מורה לעברית בישראל. צור {$num_questions} שאלות אמריקאיות (4 אפשרויות תשובה) בנושא \"{$topic}\" עבור כיתה {$grade}, ברמת קושי {$difficulty}.\nהשאלות מיועדות לתלמידי תיכון (כיתות י׳–י״ב). השתמש בשפה פשוטה וברורה המתאימה לגיל.";
    }

    $prompt = $intro . "\n\nהחזר JSON בפורמט הבא בלבד, ללא טקסט נוסף:\n" . $json_format . "\n\n" . $rules;

    $payload = json_encode([
        "model"           => "gpt-4o-mini",
        "messages"        => [
            ["role" => "user", "content" => $prompt]
        ],
        "response_format" => ["type" => "json_object"],
        "temperature"     => 0.7
    ]);

    $context = stream_context_create([
        "http" => [
            "method"        => "POST",
            "header"        => implode("\r\n", [
                "Content-Type: application/json",
                "Authorization: Bearer " . OPENAI_API_KEY,
                "Content-Length: " . strlen($payload)
            ]),
            "content"       => $payload,
            "ignore_errors" => true,
            "timeout"       => 60
        ]
    ]);

    $response = file_get_contents("https://api.openai.com/v1/chat/completions", false, $context);

    if ($response === false) {
        error_log("[OPENAI_DEBUG] file_get_contents returned false");
        return null;
    }

    $data = json_decode($response, true);

    if (!isset($data["choices"][0]["message"]["content"])) {
        error_log("[OPENAI_DEBUG] unexpected response: " . substr($response, 0, 500));
        return null;
    }

    $content = json_decode($data["choices"][0]["message"]["content"], true);

    if (!isset($content["questions"]) || !is_array($content["questions"])) {
        return null;
    }

    $questions = [];
    foreach ($content["questions"] as $q) {
        if (!isset(
            $q["question_text"],
            $q["option_1"], $q["option_2"], $q["option_3"], $q["option_4"],
            $q["correct_option"],
            $q["explanation"]
        )) {
            continue;
        }

        $correct = (int)$q["correct_option"];
        if ($correct < 1 || $correct > 4) {
            continue;
        }

        $questions[] = [
            "question_text"  => (string)$q["question_text"],
            "option_1"       => (string)$q["option_1"],
            "option_2"       => (string)$q["option_2"],
            "option_3"       => (string)$q["option_3"],
            "option_4"       => (string)$q["option_4"],
            "correct_option" => $correct,
            "explanation"    => (string)$q["explanation"]
        ];
    }

    return empty($questions) ? null : $questions;
}
