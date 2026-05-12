<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../includes/db.php";

/* ---------------------------
   USER PROTECTION
----------------------------*/
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}

/* ---------------------------
   READ VALUES SENT FROM start_quiz.php
----------------------------*/
$category_id     = $_GET['category_id'] ?? null;
$difficulty_mode = $_GET['difficulty_mode'] ?? null;
$exam_mode       = $_GET['exam_mode'] ?? null;
$limit           = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
$time_limit      = isset($_GET['time_limit']) ? (int)$_GET['time_limit'] : 0;

/* ---------------------------
   BACKEND VALIDATION
----------------------------*/
if (!$category_id || !$difficulty_mode || !$exam_mode) {
    die("Invalid quiz request");
}

if ($exam_mode === 'question' && ($limit < 1 || $limit > 200)) {
    die("Invalid number of questions");
}

if ($exam_mode === 'time' && $time_limit < 1) {
    die("Invalid time limit");
}

/* ---------------------------
   DIFFICULTY MODE MAPPING
----------------------------*/
switch ($difficulty_mode) {
    case 'easy':
        $difficulties = ['easy'];
        break;
    case 'medium':
        $difficulties = ['medium'];
        break;
    case 'hard':
        $difficulties = ['hard'];
        break;
    case 'easy_medium':
        $difficulties = ['easy', 'medium'];
        break;
    case 'medium_hard':
        $difficulties = ['medium', 'hard'];
        break;
    case 'mixed':
        $difficulties = ['easy', 'medium', 'hard'];
        break;
    default:
        die("Invalid difficulty mode");
}

/* ---------------------------
   FETCH QUESTIONS
----------------------------*/
$placeholders = implode(',', array_fill(0, count($difficulties), '?'));

$sql = "
    SELECT * FROM questions
    WHERE category_id = ?
    AND difficulty IN ($placeholders)
    ORDER BY RAND()
";

if ($exam_mode === 'question') {
    $sql .= " LIMIT ?";
}

$stmt = mysqli_prepare($conn, $sql);

if ($exam_mode === 'question') {
    $types  = "i" . str_repeat("s", count($difficulties)) . "i";
    $params = array_merge([$category_id], $difficulties, [$limit]);
} else {
    $types  = "i" . str_repeat("s", count($difficulties));
    $params = array_merge([$category_id], $difficulties);
}

mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$questions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $questions[] = $row;
}

if (!$questions) {
    die("No questions available");
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Quiz</title>

    <script>
        /* ========= PER QUESTION TIMER ========= */

        let timeTaken = {};
        let questionStartTime = Date.now();
        let currentQuestionId = null;

        /* Called when user selects an option */
        function selectAnswer(qid) {
            saveTime();
            currentQuestionId = qid;
            questionStartTime = Date.now();
        }

        /* Save time spent on current question */
        function saveTime() {
            if (currentQuestionId !== null) {
                let sec = Math.floor((Date.now() - questionStartTime) / 1000);
                timeTaken[currentQuestionId] =
                    (timeTaken[currentQuestionId] || 0) + sec;
            }
        }

        /* Attach times before submit */
        function attachTimes() {
            saveTime(); // save last question

            let input = document.createElement("input");
            input.type = "hidden";
            input.name = "time_taken";
            input.value = JSON.stringify(timeTaken);

            document.getElementById("quizForm").appendChild(input);
        }

        /* ========= COUNTDOWN TIMER ========= */
        <?php if ($exam_mode === 'time'): ?>
            let remainingSeconds = <?= $time_limit ?> * 60;

            function startTimer() {
                const timerEl = document.getElementById("timer");

                setInterval(() => {
                    let min = Math.floor(remainingSeconds / 60);
                    let sec = remainingSeconds % 60;

                    timerEl.innerText =
                        String(min).padStart(2, '0') + ":" +
                        String(sec).padStart(2, '0');

                    if (remainingSeconds <= 0) {
                        alert("Time is up! Submitting quiz.");
                        attachTimes(); // 👈 ADD THIS
                        document.getElementById("quizForm").submit();
                    }


                    remainingSeconds--;
                }, 1000);
            }

            window.onload = startTimer;
        <?php endif; ?>
    </script>


    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="quiz.css?v=2" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body>

    <div class="quiz-wrapper">

        <!-- HEADER -->
        <div class="quiz-header">
            <h2>Quiz Started 🚀</h2>

            <?php if ($exam_mode === 'time'): ?>
                <div class="timer-box">
                    ⏳ <span id="timer">00:00</span>
                </div>
            <?php endif; ?>
        </div>

        <form method="POST" action="submit_quiz.php" id="quizForm" onsubmit="attachTimes()">

            <?php foreach ($questions as $index => $q): ?>
                <div class="question-card">

                    <div class="question-title">
                        Q<?= $index + 1 ?>.
                        <?= htmlspecialchars($q['question']) ?>
                        <span class="text-muted">(<?= $q['difficulty'] ?>)</span>
                    </div>

                    <?php
                    $opts = mysqli_query(
                        $conn,
                        "SELECT * FROM options WHERE question_id = " . (int)$q['id']
                    );
                    while ($opt = mysqli_fetch_assoc($opts)):
                    ?>

                        <label class="option">
                            <input
                                type="radio"
                                name="answers[<?= $q['id'] ?>]"
                                value="<?= $opt['id'] ?>"
                                onclick="selectAnswer(<?= $q['id'] ?>)"

                                required>
                            <?= htmlspecialchars($opt['option_text']) ?>
                        </label>

                    <?php endwhile; ?>

                </div>
            <?php endforeach; ?>

            <!-- HIDDEN VALUES -->
            <input type="hidden" name="difficulty_mode" value="<?= htmlspecialchars($difficulty_mode) ?>">
            <input type="hidden" name="exam_mode" value="<?= htmlspecialchars($exam_mode) ?>">
            <input type="hidden" name="time_limit" value="<?= (int)$time_limit ?>">

            <button type="submit" class="submit-btn">
                Submit Quiz
            </button>

        </form>

    </div>

</body>

</html>