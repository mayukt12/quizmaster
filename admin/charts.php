<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$admin_name = $_SESSION['name'] ?? 'Admin';
/* ================= EXPORT CSV ================= */
if (isset($_GET['export']) && $_GET['export'] == 1) {

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=analytics_export.csv');

    $output = fopen("php://output", "w");

    // Column Headers
    fputcsv($output, [
        'User ID',
        'Category',
        'Total Questions',
        'Correct Answers',
        'Accuracy (%)',
        'Attempted At'
    ]);

    $exportQuery = mysqli_query($conn, "
        SELECT 
            qa.user_id,
            c.category_name,
            qa.total_questions,
            qa.correct_answers,
            qa.accuracy,
            qa.attempted_at
        FROM quiz_attempts qa
        JOIN categories c ON qa.category_id = c.id
        ORDER BY qa.attempted_at DESC
    ");

    while ($row = mysqli_fetch_assoc($exportQuery)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit; // CRITICAL
}

/* ================= DATE FILTER ================= */
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';

$dateCondition = "";
if ($from && $to) {
    $dateCondition = "WHERE attempted_at BETWEEN '$from' AND '$to 23:59:59'";
}

/* ================= KPI ================= */
$totalUsers = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) c FROM users WHERE is_deleted=0"
))['c'];

$totalAttempts = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) c FROM quiz_attempts $dateCondition"
))['c'];

$totalQuestions = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) c FROM questions"
))['c'];

$overallAccuracy = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT ROUND(AVG(accuracy),2) acc FROM quiz_attempts $dateCondition"
))['acc'] ?? 0;




/* ================= ATTEMPTS PER DAY ================= */
$q1 = mysqli_query($conn, "
SELECT DATE(attempted_at) d, COUNT(*) total
FROM quiz_attempts
$dateCondition
GROUP BY d ORDER BY d
");

$days = [];
$attemptData = [];
while ($r = mysqli_fetch_assoc($q1)) {
    $days[] = date("d M", strtotime($r['d']));
    $attemptData[] = $r['total'];
}

/* ================= ACTIVE USERS ================= */
$q2 = mysqli_query($conn, "
SELECT DATE(attempted_at) d, COUNT(DISTINCT user_id) total
FROM quiz_attempts
$dateCondition
GROUP BY d ORDER BY d
");

$activeUsers = [];
while ($r = mysqli_fetch_assoc($q2)) {
    $activeUsers[] = $r['total'];
}

/* ================= CATEGORY ACCURACY ================= */
$q3 = mysqli_query($conn, "
SELECT c.category_name, ROUND(AVG(qa.accuracy),2) avg_acc
FROM quiz_attempts qa
JOIN categories c ON qa.category_id=c.id
$dateCondition
GROUP BY c.id
");

$catLabels = [];
$catAccuracy = [];
while ($r = mysqli_fetch_assoc($q3)) {
    $catLabels[] = $r['category_name'];
    $catAccuracy[] = $r['avg_acc'];
}

/* ================= CORRECT VS WRONG ================= */
$q4 = mysqli_query($conn, "
SELECT SUM(correct_answers) correct,
SUM(total_questions - correct_answers) wrong
FROM quiz_attempts
$dateCondition
");

$row = mysqli_fetch_assoc($q4);
$correct = $row['correct'] ?? 0;
$wrong = $row['wrong'] ?? 0;
?>

<!DOCTYPE html>
<html>

<head>
    <title>Admin Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/admin_css/admin_dashboard.css?v=2">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body>

    <div class="wrapper">

        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <i class="bi bi-book"></i>
                </div>
                <div class="logo-text">QuizMaster</div>
            </div>
            <a href="dashboard.php">Dashboard</a>
            <a href="view_attempts.php">Attempts</a>
            <a class="active" href="charts.php">Analytics</a>
            <a href="users.php">Users</a>
            <a href="add_category.php">Categories</a>
            <a href="add_question.php">Questions</a>
        </div>

        <div class="main">

            <!-- TOPBAR -->
            <div class="topbar">
                <h5>Analytics Dashboard</h5>
                <div>
                    <?= htmlspecialchars($admin_name) ?>
                    <a href="../auth/logout.php" class="btn btn-danger btn-sm ms-3">Logout</a>
                </div>
            </div>

            <!-- FILTER -->
            <div class="card p-3 mb-4 shadow">
                <form class="row g-3 align-items-end">

                    <div class="col-md-3">
                        <label>From</label>
                        <input type="date" name="from" value="<?= $from ?>" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label>To</label>
                        <input type="date" name="to" value="<?= $to ?>" class="form-control">
                    </div>

                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary">Apply</button>
                    </div>

                    <div class="col-md-2 d-grid">
                        <a href="charts.php" class="btn btn-secondary">Reset</a>
                    </div>

                    <div class="col-md-2 d-grid">
                        <a href="charts.php?export=1" class="btn btn-success">Export CSV</a>
                    </div>

                </form>
            </div>

            <!-- KPI ROW -->
            <div class="row g-4 mb-4">

                <div class="col-md-3">
                    <div class="stat-card bg-blue">
                        <h6>Total Users</h6>
                        <h3><?= $totalUsers ?></h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-purple">
                        <h6>Total Attempts</h6>
                        <h3><?= $totalAttempts ?></h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-green">
                        <h6>Overall Accuracy</h6>
                        <h3><?= $overallAccuracy ?>%</h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-orange">
                        <h6>Total Questions</h6>
                        <h3><?= $totalQuestions ?></h3>
                    </div>
                </div>

            </div>

            <!-- CHART ROW 1 -->
            <div class="row g-4 mb-4">
                

                <div class="col-md-6">
                    <div class="card p-4 shadow">
                        <h6>Attempts Per Day</h6>
                        <canvas id="attemptChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-4 shadow">
                        <h6>Active Users Per Day</h6>
                        <canvas id="activeChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- CHART ROW 2 -->
            <div class="row g-4 mb-4">

                <div class="col-md-6">
                    <div class="card p-4 shadow">
                        <h6>Accuracy by Category</h6>
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-4 shadow">
                        <h6>Correct vs Wrong</h6>
                        <canvas id="pieChart"></canvas>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script>
        new Chart(document.getElementById('attemptChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($days) ?>,
                datasets: [{
                    label: 'Attempts',
                    data: <?= json_encode($attemptData) ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78,115,223,0.2)',
                    fill: true,
                    tension: 0.4
                }]
            }
        });

        const usersData = <?= json_encode($activeUsers) ?>;
        const maxValue = Math.max(...usersData);
        const suggestedMax = maxValue + 2; // 👈 add 2 extra space

        new Chart(document.getElementById('activeChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($days) ?>,
                datasets: [{
                    label: 'Active Users',
                    data: usersData,
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28,200,138,0.2)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: suggestedMax, // 👈 force extra space
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });



        new Chart(document.getElementById('categoryChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    label: 'Average Accuracy %',
                    data: <?= json_encode($catAccuracy) ?>,
                    backgroundColor: '#36b9cc'
                }]
            }
        });

        new Chart(document.getElementById('pieChart'), {
            type: 'pie',
            data: {
                labels: ['Correct', 'Wrong'],
                datasets: [{
                    data: [<?= $correct ?>, <?= $wrong ?>],
                    backgroundColor: ['#1cc88a', '#e74a3b']
                }]
            }
        });
    </script>

</body>

</html>