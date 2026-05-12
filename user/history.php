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

$user_id = $_SESSION['user_id'];

$category_id = $_GET['category_id'] ?? 'all';
$status      = $_GET['status'] ?? 'all';

/* ============================
   SUMMARY STATS
============================ */

/* Total Quizzes */
$qTotal = mysqli_query($conn, "
    SELECT COUNT(*) AS total_quizzes 
    FROM quiz_attempts 
    WHERE user_id=$user_id
");
$total_quizzes = mysqli_fetch_assoc($qTotal)['total_quizzes'] ?? 0;

/* Passed */
$qPassed = mysqli_query($conn, "
    SELECT COUNT(*) AS passed 
    FROM quiz_attempts 
    WHERE user_id=$user_id AND accuracy>=50
");
$passed = mysqli_fetch_assoc($qPassed)['passed'] ?? 0;

/* Failed */
$qFailed = mysqli_query($conn, "
    SELECT COUNT(*) AS failed 
    FROM quiz_attempts 
    WHERE user_id=$user_id AND accuracy<50
");
$failed = mysqli_fetch_assoc($qFailed)['failed'] ?? 0;

/* Average Percentage */
$qAvg = mysqli_query($conn, "
    SELECT ROUND((SUM(correct_answers)/SUM(total_questions))*100,1) AS avg_percentage
    FROM quiz_attempts
    WHERE user_id=$user_id
");
$avg_score = mysqli_fetch_assoc($qAvg)['avg_percentage'] ?? 0;


/* ============================
   FILTERED ATTEMPTS
============================ */

$where = "WHERE qa.user_id=?";
$params = [$user_id];
$types = "i";

if ($category_id !== "all") {
    $where .= " AND qa.category_id=?";
    $params[] = $category_id;
    $types .= "i";
}

if ($status === "passed") {
    $where .= " AND qa.accuracy>=40";
} elseif ($status === "failed") {
    $where .= " AND qa.accuracy<40";
}

$sql = "
SELECT 
    qa.id AS attempt_id,
    c.category_name,
    qa.difficulty,
    qa.total_questions,
    qa.correct_answers,
    qa.score,
    qa.accuracy,
    qa.attempted_at,
    IFNULL(SUM(ad.time_taken),0) AS total_time
FROM quiz_attempts qa
JOIN categories c ON qa.category_id=c.id
LEFT JOIN attempt_details ad ON qa.id=ad.attempt_id
$where
GROUP BY qa.id
ORDER BY qa.attempted_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);


/* ============================
   CSV EXPORT
============================ */

if (isset($_GET['export'])) {

    header("Content-Type:text/csv");
    header("Content-Disposition:attachment; filename=quiz_history.csv");

    $out = fopen("php://output", "w");

    fputcsv($out, [
        "Date",
        "Category",
        "Difficulty",
        "Total Questions",
        "Correct",
        "Score",
        "Accuracy",
        "Time Taken"
    ]);

    $exportSql = "
        SELECT 
            qa.attempted_at,
            c.category_name,
            qa.difficulty,
            qa.total_questions,
            qa.correct_answers,
            qa.score,
            qa.accuracy,
            IFNULL(SUM(ad.time_taken),0) AS total_time
        FROM quiz_attempts qa
        JOIN categories c ON qa.category_id=c.id
        LEFT JOIN attempt_details ad ON qa.id=ad.attempt_id
        WHERE qa.user_id=$user_id
        GROUP BY qa.id
        ORDER BY qa.attempted_at DESC
    ";

    $res = mysqli_query($conn, $exportSql);

    while ($r = mysqli_fetch_assoc($res)) {
        fputcsv($out, [
            $r['attempted_at'],
            $r['category_name'],
            $r['difficulty'],
            $r['total_questions'],
            $r['correct_answers'],
            $r['score'],
            $r['accuracy'] . "%",
            formatTime($r['total_time'])
        ]);
    }

    fclose($out);
    exit;
}


/* ============================
   TIME FORMATTER
============================ */

function formatTime($seconds)
{
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return "{$h}h {$m}m {$s}s";
}

?>


<!DOCTYPE html>
<html>

<head>
    <title>My Quiz History</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="history.css?v=2" rel="stylesheet">

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










        <div class=" py-5  main-content" id="mainContent">

            <!-- HEADER -->
            <h2>Quiz History 📜</h2>
            <p class="text-muted">Review your past quiz attempts and track your progress</p>

            <!-- STATS -->
            <div class="row g-4 mb-4">

                <div class="col-md-3">
                    <div class="stat-card stat-blue">
                        <h6>Total Quizzes</h6>
                        <h2><?= $total_quizzes ?></h2>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card stat-green">
                        <h6>Passed</h6>
                        <h2><?= $passed ?></h2>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card stat-red">
                        <h6>Failed</h6>
                        <h2><?= $failed ?></h2>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card stat-purple">
                        <h6>Avg Score</h6>
                        <h2><?= $avg_score ?>%</h2>
                    </div>
                </div>

            </div>
            <div class="filter-bar d-flex justify-content-end gap-3 mb-4">

                <!-- Category Filter -->
                <form method="GET" class="filter-wrap">

                    <select name="category_id" onchange="this.form.submit()">
                        <option value="all">All Categories</option>
                        <?php
                        $catQ = mysqli_query($conn, "SELECT id,category_name FROM categories");
                        while ($cat = mysqli_fetch_assoc($catQ)):
                        ?>
                            <option value="<?= $cat['id'] ?>"
                                <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                                <?= $cat['category_name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>

                    <select name="status" onchange="this.form.submit()">
                        <option value="all">All Status</option>
                        <option value="passed" <?= $status == 'passed' ? 'selected' : '' ?>>Passed</option>
                        <option value="failed" <?= $status == 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                    <button type="submit" name="export" value="1">
                        ⬇ Export
                    </button>


                </form>


            </div>


            <!-- ATTEMPTS -->

            <?php if (mysqli_num_rows($result) == 0): ?>

                <p>No quiz attempts yet.</p>

            <?php else: ?>

                <?php while ($row = mysqli_fetch_assoc($result)):
                    $percent = round($row['accuracy']);
                    $passedClass = $percent >= 40 ? 'bg-success' : 'bg-danger';
                    $total_time = formatTime($row['total_time']);
                ?>


                    <div class="history-card mb-4">

                        <div class="d-flex justify-content-between align-items-center">

                            <div class="d-flex align-items-center gap-3">

                                <div class="status-icon <?= $percent >= 40 ? 'success' : 'danger' ?>">
                                    <i class="bi <?= $percent >= 40 ? 'bi-check' : 'bi-x' ?>"></i>
                                </div>

                                <div>
                                    <h5 class="mb-1"><?= htmlspecialchars($row['category_name']) ?></h5>

                                    <div class="text-muted small d-flex gap-3 flex-wrap">

                                        <span class="badge-soft">
                                            <?= ucfirst($row['difficulty']) ?>
                                        </span>

                                        <span>
                                            <i class="bi bi-calendar"></i>
                                            <?= date("d M Y", strtotime($row['attempted_at'])) ?>
                                        </span>

                                        <span>
                                            <i class="bi bi-stopwatch"></i>
                                            <?= $total_time ?>
                                        </span>

                                    </div>

                                </div>

                            </div>

                            <a href="review.php?attempt_id=<?= $row['attempt_id'] ?>" class="review-btn">
                                <i class="bi bi-eye"></i> Review
                            </a>

                        </div>

                        <!-- SCORE BAR -->
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small">
                                <span>Score</span>
                                <span>
                                    <?= $row['score'] ?>/<?= $row['total_questions'] ?>
                                    <strong class="<?= $percent >= 50 ? 'text-success' : 'text-danger' ?>">
                                        <?= $percent ?>%
                                    </strong>
                                </span>
                            </div>

                            <div class="progress mt-1">
                                <div class="progress-bar <?= $passedClass ?>"
                                    style="width:<?= $percent ?>%">
                                </div>
                            </div>
                        </div>

                    </div>

                <?php endwhile; ?>

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