<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../includes/db.php";

/* ---------------------------
   AUTH CHECK (USER OR ADMIN)
----------------------------*/
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$logged_user_id = $_SESSION['user_id'];
$role           = $_SESSION['role'];
$attempt_id     = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

if ($attempt_id <= 0) {
    die("Invalid attempt");
}

/* ---------------------------
   FETCH ATTEMPT OWNER
----------------------------*/
$stmt = mysqli_prepare(
    $conn,
    "SELECT user_id FROM quiz_attempts WHERE id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $attempt_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$attempt = mysqli_fetch_assoc($res);

if (!$attempt) {
    die("Attempt not found");
}

/* ---------------------------
   ACCESS CONTROL
----------------------------*/
if ($role === 'user' && $attempt['user_id'] != $logged_user_id) {
    die("Unauthorized access");
}

/* ---------------------------
   FETCH QUESTION DETAILS
----------------------------*/
$sql = "
SELECT
    q.question,
    q.difficulty,
    o.option_text AS selected_option,
    o.is_correct,
    ad.time_taken,
    (
        SELECT option_text
        FROM options
        WHERE question_id = q.id AND is_correct = 1
        LIMIT 1
    ) AS correct_option
FROM attempt_details ad
JOIN questions q ON ad.question_id = q.id
JOIN options o ON ad.selected_option_id = o.id
WHERE ad.attempt_id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $attempt_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);


/* ============================
   OVERALL SUMMARY
============================ */

$summarySql = "
SELECT 
COUNT(*) AS total_q,
SUM(o.is_correct) AS correct,
SUM(CASE WHEN o.is_correct=0 THEN 1 ELSE 0 END) AS incorrect
FROM attempt_details ad
JOIN options o ON ad.selected_option_id=o.id
WHERE ad.attempt_id = $attempt_id
";

$summaryRes = mysqli_query($conn, $summarySql);
$sum = mysqli_fetch_assoc($summaryRes);

$totalQ = $sum['total_q'] ?? 0;
$correctQ = $sum['correct'] ?? 0;
$incorrectQ = $sum['incorrect'] ?? 0;
$accuracy = $totalQ ? round(($correctQ / $totalQ) * 100) : 0;

?>

<!DOCTYPE html>
<html>

<head>
    <title>Quiz Review</title>
    <link href="review.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>
<div class="wrapper">
    <div class="sidebar" id="sidebar">

            <!-- LOGO -->
            <div class="logo">
                <div class="logo-icon">
                    <i class="bi bi-book"></i>
                </div>
                <div class="logo-text">QuizMaster</div>
            </div>

            <!-- MENU -->
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            ?>

            <div class="menu">

                <a class="side-link <?= $currentPage == "dashboard.php" ? 'active' : '' ?>" href="dashboard.php">
                    <i class="bi bi-grid"></i>
                    <span class="link-text">Dashboard</span>
                </a>

                <a class="side-link <?= $currentPage == "start_quiz.php" ? 'active' : '' ?>" href="start_quiz.php">
                    <i class="bi bi-play"></i>
                    <span class="link-text">Start New Quiz</span>
                </a>

                <a class="side-link <?= $currentPage == "charts.php" ? 'active' : '' ?>" href="charts.php">
                    <i class="bi bi-bar-chart"></i>
                    <span class="link-text">Charts</span>
                </a>

                <a class="side-link <?= $currentPage == "history.php" ? 'active' : '' ?>" href="history.php">
                    <i class="bi bi-clock-history"></i>
                    <span class="link-text">History</span>
                </a>

            </div>


            <!-- COLLAPSE -->
            <div class="collapse-btn" id="toggleBtn">
                <i class="bi bi-chevron-left"></i>
                <span class="link-text">Collapse</span>
            </div>

        </div>
    <div class="page-wrapper">
        

        <div class="summary-wrapper">

            <div class="summary-header">
                <div>
                    <h2>Quiz Review 📝</h2>
                    <p>Review your answers and learn from mistakes</p>
                </div>

                <div class="score-pill">
                    Score: <?= $correctQ ?>/<?= $totalQ ?>
                </div>
            </div>

            <div class="summary-grid">

                <div class="summary-card blue">
                    <span>Total Questions</span>
                    <h3><?= $totalQ ?></h3>
                </div>

                <div class="summary-card green">
                    <span>Correct</span>
                    <h3><?= $correctQ ?></h3>
                </div>

                <div class="summary-card red">
                    <span>Incorrect</span>
                    <h3><?= $incorrectQ ?></h3>
                </div>

                <div class="summary-card purple">
                    <span>Accuracy</span>
                    <h3><?= $accuracy ?>%</h3>
                </div>

            </div>

        </div>

        <?php $i = 1; ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>

            <?php
            $seconds = (int)$row['time_taken'];
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            $s = $seconds % 60;
            $formattedTime = "{$h}h {$m}m {$s}s";
            ?>

            <div class="review-card">

                <div class="d-flex justify-content-between align-items-start">

                    <div>
                        <h5 class="mb-2">
                            Q<?= $i++ ?>.
                            <?= htmlspecialchars($row['question']) ?>
                        </h5>

                        <span class="difficulty-badge">
                            <?= htmlspecialchars(ucfirst($row['difficulty'])) ?>
                        </span>
                    </div>

                    <div class="text-end time-text">
                        ⏱ <?= $formattedTime ?>
                    </div>

                </div>

                <div class="mt-3">

                    <div class="answer-box <?= $row['is_correct'] ? 'correct' : 'wrong' ?>">
                        Your Answer:
                        <?= htmlspecialchars($row['selected_option']) ?>
                    </div>

                    <?php if (!$row['is_correct']): ?>
                        <div class="correct-answer">
                            Correct Answer:
                            <strong><?= htmlspecialchars($row['correct_option']) ?></strong>
                        </div>
                    <?php endif; ?>

                </div>

            </div>

        <?php endwhile; ?>

        <?php if ($role === 'admin'): ?>
            <a href="../admin/view_attempts.php" class="back-btn">
                ⬅ Back to Attempts
            </a>
        <?php else: ?>
            <a href="history.php" class="back-btn">
                ⬅ Back to History
            </a>
        <?php endif; ?>

    </div>
</div>

</body>
<script>
    document.querySelector(".collapse-btn").onclick = function() {
        document.querySelector(".sidebar").classList.toggle("collapsed");
    };
</script>

</html>