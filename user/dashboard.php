<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? "User";
//total question
$stmt = mysqli_prepare($conn, "
    SELECT SUM(total_questions) AS total_questions
    FROM quiz_attempts
    WHERE user_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$total_questions = mysqli_fetch_assoc($res)['total_questions'] ?? 0;
//total quizzes
$stmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS total_quizzes
    FROM quiz_attempts
    WHERE user_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$total_quizzes = mysqli_fetch_assoc($res)['total_quizzes'] ?? 0;
//accuracy
$stmt = mysqli_prepare($conn, "
SELECT ROUND(AVG(accuracy),0)
FROM quiz_attempts
WHERE user_id = ?
AND accuracy IS NOT NULL
");

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$avg_accuracy = mysqli_fetch_row($res)[0] ?? 0;


//weekly progress
$stmt = mysqli_prepare($conn, "
    SELECT SUM(correct_answers) AS weekly_correct
    FROM quiz_attempts
    WHERE user_id = ?
    AND attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$weekly_progress = mysqli_fetch_assoc($res)['weekly_correct'] ?? 0;
//current Streak
$stmt = mysqli_prepare($conn, "
    SELECT DATE(attempted_at) AS attempt_date
    FROM quiz_attempts
    WHERE user_id = ?
    GROUP BY DATE(attempted_at)
    ORDER BY attempt_date DESC
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$streak = 0;
$prev_date = null;

while ($row = mysqli_fetch_assoc($res)) {
    $current_date = $row['attempt_date'];

    if ($prev_date === null) {
        $streak++;
    } elseif (strtotime($prev_date) - strtotime($current_date) == 86400) {
        $streak++;
    } else {
        break;
    }

    $prev_date = $current_date;
}

$current_streak = $streak;


$stmt = mysqli_prepare($conn, "
SELECT 
    c.id,
    c.category_name,
    COUNT(qa.id) AS attempts,
    COALESCE(ROUND(AVG(qa.accuracy),2),0) AS avg_accuracy,
    COALESCE(ROUND(AVG(qa.score),2),0) AS avg_score
FROM categories c
LEFT JOIN quiz_attempts qa 
    ON qa.category_id = c.id 
    AND qa.user_id = ?
GROUP BY c.id
ORDER BY c.category_name
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);


$weekData = [];

/* Create last 7 days (rolling) */
for ($i = 6; $i >= 0; $i--) {
    $day = date("Y-m-d", strtotime("-$i days"));
    $label = date("D", strtotime($day)); // Mon Tue Wed
    $weekData[$label] = 0;
}

/* Fetch last 7 days average accuracy */
$sql = "
SELECT 
    DATE(attempted_at) as d,
    ROUND(AVG(accuracy)) as avg_score
FROM quiz_attempts
WHERE user_id = $user_id
AND attempted_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
GROUP BY DATE(attempted_at)
";

$res = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($res)) {
    $label = date("D", strtotime($row['d']));
    $weekData[$label] = (int)$row['avg_score'];
}




$stmt = mysqli_prepare($conn, "
SELECT 
    c.category_name,
    qa.score,
    qa.total_questions,
    qa.accuracy,
    qa.attempted_at
FROM quiz_attempts qa
JOIN categories c ON qa.category_id = c.id
WHERE qa.user_id = ?
AND DATE(qa.attempted_at) = CURDATE()
ORDER BY qa.attempted_at DESC
LIMIT 5
");


mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$recentResult = mysqli_stmt_get_result($stmt);

$stmt = mysqli_prepare($conn, "
SELECT 
    c.id,
    c.category_name,

    COALESCE(SUM(qa.total_questions),0)  AS attempted_questions,
    COALESCE(SUM(qa.correct_answers),0)  AS correct_answers

FROM categories c
LEFT JOIN quiz_attempts qa
    ON qa.category_id = c.id
    AND qa.user_id = ?

GROUP BY c.id
ORDER BY c.category_name
");

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$categoryResult = mysqli_stmt_get_result($stmt);








?>
<!DOCTYPE html>
<html>

<head>
    <title>Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body>

    <div class="wrapper">

        <!-- SIDEBAR -->
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


        <div class="main-content" id="mainContent">

            <!-- TOP BAR -->
            <div class="container-fluid mb-4">
                <div class="row g-4">
                    <!-- TOPBAR -->
                    <div class="topbar">
                        <h5 class="mb-0">
                            Hello, <?= htmlspecialchars($user_name) ?> 👋
                        </h5>

                        <div class="topbar-right">
                            <a href="../auth/logout.php" class="btn btn-outline-danger btn-sm">
                                Logout
                            </a>
                        </div>
                    </div>

                    <!-- TOTAL QUESTIONS -->
                    <div class="col-md-3">
                        <div class="stat-card bg-blue">
                            <div class="stat-content">
                                <p class="stat-title">Total Questions</p>
                                <h2 class="stat-value">
                                    <?= number_format($total_questions ?? 0) ?>
                                </h2>

                            </div>
                            <div class="icon-box">
                                <i class="bi bi-book"></i>
                            </div>
                        </div>
                    </div>

                    <!-- ACCURACY RATE -->
                    <div class="col-md-3">
                        <div class="stat-card bg-purple">
                            <div class="stat-content">
                                <p class="stat-title">Accuracy Rate</p>

                                <h2 class="stat-value">
                                    <?= round($avg_accuracy ?? 0) ?>%
                                </h2>


                            </div>

                            <div class="icon-box">
                                <i class="bi bi-bullseye"></i>
                            </div>
                        </div>
                    </div>


                    <!-- WEEKLY PROGRESS -->
                    <div class="col-md-3">
                        <div class="stat-card bg-green">
                            <div class="stat-content">
                                <p class="stat-title">Weekly Progress</p>
                                <h2 class="stat-value">
                                    <?= number_format($weekly_progress ?? 0) ?>
                                </h2>
                                <span class="stat-sub">Correct answers (7 days)</span>
                            </div>
                            <div class="icon-box">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>

                    <!-- CURRENT STREAK -->
                    <div class="col-md-3">
                        <div class="stat-card bg-orange">
                            <div class="stat-content">
                                <p class="stat-title">Current Streak</p>
                                <h2 class="stat-value">
                                    <?= (int)($current_streak ?? 0) ?> days
                                </h2>
                                <span class="stat-sub">
                                    <?= ($current_streak ?? 0) > 0 ? 'Keep it up!' : 'Start today!' ?>
                                </span>
                            </div>
                            <div class="icon-box">
                                <i class="bi bi-fire"></i>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="row g-4 mt-3">

                    <!-- PERFORMANCE OVERVIEW -->
                    <div class="col-md-8">
                        <div class="card p-4 h-100">
                            <div class="d-flex justify-content-between mb-3">
                                <h5>Performance Overview (7 days)</h5>

                            </div>

                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>

                    <!-- RECENT ACTIVITY -->
                    <div class="col-md-4">
                        <div class="card p-4 h-100">
                            <h5 class="mb-3">Recent Activity</h5>

                            <?php while ($row = mysqli_fetch_assoc($recentResult)): ?>

                                <div class="activity-item">
                                    <div class="left">
                                        <span class="status <?= $row['accuracy'] >= 50 ? 'success' : 'danger' ?>">
                                            <?= $row['accuracy'] >= 50 ? '✔' : '✖' ?>
                                        </span>

                                        <div>
                                            <strong><?= htmlspecialchars($row['category_name']) ?></strong>
                                            <div class="small text-muted">
                                                Score: <?= $row['score'] ?>/<?= $row['total_questions'] ?> •
                                                <?= date("g:i A", strtotime($row['attempted_at'])) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <span class="badge <?= $row['accuracy'] >= 50 ? 'bg-success' : 'bg-danger' ?>">
                                        <?= round($row['accuracy']) ?>%
                                    </span>
                                </div>

                            <?php endwhile; ?>
                        </div>
                    </div>

                </div>
                <!-- categories-->
                <div class="row g-4 categories-performance">

                    <?php while ($cat = mysqli_fetch_assoc($categoryResult)):

                        $attempted = (int)$cat['attempted_questions'];
                        $correct   = (int)$cat['correct_answers'];

                        // percentage = correct / attempted
                        $progressPercent = 0;
                        if ($attempted > 0) {
                            $progressPercent = round(($correct / $attempted) * 100);
                        }

                        $icons = [
                            "gk" => "🌍",
                            "history" => "🏛️",
                            "maths" => "🔢",
                            "science" => "🔬",
                            "social science" => "📚"
                        ];

                        $key = strtolower(trim($cat['category_name']));
                    ?>

                        <div class="col-md-3">
                            <div class="category-card p-4">

                                <div class="icon-box mb-3">
                                    <?= $icons[$key] ?? "📘"; ?>
                                </div>

                                <h5><?= htmlspecialchars($cat['category_name']); ?></h5>

                                <p>Attempted: <strong><?= $attempted ?></strong></p>
                                <p>Correct: <strong><?= $correct ?></strong></p>

                                <!-- PERFORMANCE BAR -->
                                <div class="progress" style="height:8px;">
                                    <div class="progress-bar bg-dark"
                                        role="progressbar"
                                        style="width: <?= $progressPercent ?>%;">
                                    </div>
                                </div>

                            </div>
                        </div>

                    <?php endwhile; ?>

                </div>





            </div>

            <!-- END STATS -->


        </div>
        <!-- END CONTAINER -->

    </div>


</body>
<script>
    document.querySelector(".collapse-btn").onclick = function() {
        document.querySelector(".sidebar").classList.toggle("collapsed");
    };
</script>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const chartData = {
    labels: <?= json_encode(array_keys($weekData)) ?>,
    datasets: [{
        label: "Score",
        data: <?= json_encode(array_values($weekData)) ?>,
        borderColor: "#3b82f6",
        backgroundColor: "rgba(59,130,246,0.15)",
        tension: 0.4,
        fill: true,
        pointRadius: 5
    }]
};

new Chart(document.getElementById("performanceChart"), {
    type: "line",
    data: chartData,
    options: {
        plugins: {
            legend: { display:false }
        },
        scales: {
            y: {
                beginAtZero:true,
                max:100
            }
        }
    }
});
</script>

<?php if (mysqli_num_rows($recentResult) == 0): ?>
    <p class="text-muted">No activity today.</p>
<?php endif; ?>


</html>