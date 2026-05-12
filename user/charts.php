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

/* ---------------------------
   SUBJECT FILTER
----------------------------*/
$category_id = $_GET['category_id'] ?? 'all';

/* Fetch categories */
$categories_q = mysqli_query($conn, "
    SELECT id, category_name 
    FROM categories
");

/* ---------------------------
   BUILD WHERE CLAUSE
----------------------------*/
$whereClause = "WHERE qa.user_id = $user_id";

if ($category_id !== 'all') {
    $whereClause .= " AND qa.category_id = " . (int)$category_id;
}

/* ---------------------------
   ACCURACY + SCORE DATA
----------------------------*/
$q1 = mysqli_query($conn, "
    SELECT 
        qa.attempted_at,
        qa.accuracy,
        qa.score,
        c.category_name
    FROM quiz_attempts qa
    JOIN categories c ON qa.category_id = c.id
    $whereClause
    ORDER BY qa.attempted_at
");

/* ---------------------------
   CORRECT vs INCORRECT
----------------------------*/
$q2 = mysqli_query($conn, "
    SELECT 
        SUM(correct_answers) AS correct,
        SUM(total_questions - correct_answers) AS incorrect
    FROM quiz_attempts qa
    $whereClause
");

/* ---------------------------
   PREPARE ARRAYS
----------------------------*/
$labels   = [];
$accuracy = [];
$scores   = [];

while ($row = mysqli_fetch_assoc($q1)) {
    $labels[]   = date("d M Y, h:i A", strtotime($row['attempted_at']));
    $accuracy[] = $row['accuracy'];
    $scores[]   = $row['score'];
}
$ci = mysqli_fetch_assoc($q2);
$correct   = $ci['correct'] ?? 0;
$incorrect = $ci['incorrect'] ?? 0;


/* ===========================
   SUMMARY STATS (ADD HERE)
=========================== */

/* ===========================
   SUMMARY STATS
=========================== */

$summaryWhere = "WHERE qa.user_id=$user_id";

if ($category_id !== 'all') {
    $summaryWhere .= " AND qa.category_id=" . (int)$category_id;
}

/* Average Accuracy */
$avgQ = mysqli_query($conn, "
    SELECT ROUND(AVG(accuracy),1) AS avg_accuracy
    FROM quiz_attempts qa
    $summaryWhere
");
$avg_accuracy = mysqli_fetch_assoc($avgQ)['avg_accuracy'] ?? 0;


/* Total Time Spent */
$timeQ = mysqli_query($conn, "
    SELECT SUM(ad.time_taken) AS total_time
    FROM attempt_details ad
    JOIN quiz_attempts qa ON ad.attempt_id = qa.id
    $summaryWhere
");

$totalSeconds = mysqli_fetch_assoc($timeQ)['total_time'] ?? 0;

$hours   = floor($totalSeconds / 3600);
$minutes = floor(($totalSeconds % 3600) / 60);
$seconds = $totalSeconds % 60;

$total_time_formatted = "{$hours}h {$minutes}m {$seconds}s";



/* Best Subject (GLOBAL – stays same) */
$bestQ = mysqli_query($conn, "
    SELECT c.category_name, ROUND(AVG(qa.accuracy),1) AS acc
    FROM quiz_attempts qa
    JOIN categories c ON qa.category_id=c.id
    WHERE qa.user_id=$user_id
    GROUP BY c.id
    ORDER BY acc DESC
    LIMIT 1
");

$best = mysqli_fetch_assoc($bestQ);



/* Subject Performance */
$subQ = mysqli_query($conn, "
SELECT c.category_name, ROUND(AVG(qa.accuracy),1) AS acc
FROM quiz_attempts qa
JOIN categories c ON qa.category_id=c.id
WHERE qa.user_id=$user_id
GROUP BY c.id
");

$subjects = [];
$subjectAcc = [];

while ($s = mysqli_fetch_assoc($subQ)) {
    $subjects[] = $s['category_name'];
    $subjectAcc[] = $s['acc'];
}

?>


<!DOCTYPE html>
<html>

<head>
    <title>My Performance Charts</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="charts.css" rel="stylesheet">

</head>

<body>

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
            <script>
    document.querySelector(".collapse-btn").onclick = function() {
        document.querySelector(".sidebar").classList.toggle("collapsed");
    };
</script>

        </div>


        <div class="main-content" id="mainContent">
        <!-- HEADER -->
        <div class="page-header">
            <h1 class="page-title">
                Performance Charts
                <span class="title-icon">📊</span>
            </h1>
            <p class="page-subtitle">
                Visualize your learning progress and performance metrics
            </p>
        </div>

        <div class="row g-4 mb-4 align-items-stretch">
            <div class="col-md-4 d-flex">
                <div class="card-box w-100">

                    <h6>Average Accuracy</h6>
                    <h2 class="text-primary"><?= $avg_accuracy ?>%</h2>
                </div>
            </div>

            <div class="col-md-4 d-flex">
                <div class="card-box w-100">

                    <h6>Total Time Spent</h6>
                    <h2 class="text-purple"><?= $total_time_formatted ?>
                    </h2>
                </div>
            </div>

            <div class="col-md-4 d-flex">
                <div class="card-box w-100">

                    <h6>Best Subject</h6>
                    <h2 class="text-success"><?= $best['category_name'] ?? 'N/A' ?></h2>
                    <small><?= $best['acc'] ?? 0 ?>% accuracy</small>
                </div>
            </div>

        </div>





        <!-- FILTER -->
        <div class="filter-box text-center mb-4">
            <form method="GET">
                <select name="category_id" class="form-select w-auto d-inline" onchange="this.form.submit()">
                    <option value="all">All Subjects</option>
                    <?php while ($cat = mysqli_fetch_assoc($categories_q)): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= ($category_id == $cat['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['category_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
        </div>

        <?php if (empty($labels)): ?>

            <div class="card-box text-center">
                <h5>No Data Available</h5>
                <p class="text-muted">Attempt a quiz to see analytics</p>
            </div>

        <?php else: ?>

            <div class="row g-4 mb-4 align-items-stretch">


                <!-- ACCURACY -->
                <div class="col-md-6">
                    <div class="card-box">
                        <h5 class="chart-title">Accuracy Over Time</h5>
                        <canvas id="accuracyChart"></canvas>
                    </div>
                </div>

                <!-- SUBJECT PERFORMANCE -->
                <div class="col-md-6">
                    <div class="card-box">
                        <h5 class="chart-title">Subject Performance</h5>
                        <canvas id="subjectChart"></canvas>
                    </div>
                </div>



                <!-- CORRECT VS INCORRECT -->
                <div class="col-md-12">
                    <div class="card-box text-center">
                        <h5 class="chart-title">Correct vs Incorrect</h5>
                        <canvas id="correctChart" style="max-width:350px;margin:auto;"></canvas>
                    </div>
                </div>

            </div>

            <script>
                /* Accuracy */
                new Chart(document.getElementById('accuracyChart'), {
                    type: 'line',
                    data: {
                        labels: <?= json_encode(array_fill(0, count($accuracy), "")) ?>,
                        datasets: [{
                            data: <?= json_encode($accuracy) ?>,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79,70,229,.15)',
                            tension: .4,
                            fill: true
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            x: {
                                display: false
                            },
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });



                new Chart(document.getElementById('subjectChart'), {
                    type: 'bar',
                    data: {
                        labels: <?= json_encode($subjects) ?>,
                        datasets: [{
                            data: <?= json_encode($subjectAcc) ?>,
                            backgroundColor: '#3b82f6',
                            borderRadius: 10
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });



                /* Correct vs Incorrect */
                new Chart(document.getElementById('correctChart'), {

                    type: 'doughnut',
                    data: {
                        labels: ['Correct', 'Incorrect'],
                        datasets: [{
                            data: [<?= $correct ?>, <?= $incorrect ?>],
                            backgroundColor: ['#22c55e', '#ef4444']
                        }]
                    },
                    options: {
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            </script>

        <?php endif; ?>

    </div>

</body>

</html>