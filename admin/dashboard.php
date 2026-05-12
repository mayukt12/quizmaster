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

/* CURRENT PAGE FOR ACTIVE MENU */
$current_page = basename($_SERVER['PHP_SELF']);

/* STATS */
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) t FROM users"))['t'];
$total_attempts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) t FROM quiz_attempts"))['t'];
$active_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) t FROM quiz_attempts"))['t'];
$total_questions = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) t FROM questions"))['t'];

/* RECENT ATTEMPTS */
$recent = mysqli_query($conn, "
SELECT u.name, c.category_name, qa.score, qa.accuracy, qa.attempted_at
FROM quiz_attempts qa
JOIN users u ON qa.user_id=u.id
JOIN categories c ON qa.category_id=c.id
ORDER BY qa.attempted_at DESC
LIMIT 10
");

/* CHART DATA */
$chart = mysqli_query($conn, "
SELECT DATE(attempted_at) d, COUNT(*) c
FROM quiz_attempts
GROUP BY d
ORDER BY d
");

$dates = [];
$counts = [];
while ($r = mysqli_fetch_assoc($chart)) {
    $dates[] = $r['d'];
    $counts[] = $r['c'];
}

$accuracyData = mysqli_fetch_assoc(mysqli_query($conn, "
SELECT 
SUM(score) as total_correct,
SUM(total_questions - score) as total_wrong
FROM quiz_attempts
"));

$total_correct = $accuracyData['total_correct'] ?? 0;
$total_wrong = $accuracyData['total_wrong'] ?? 0;
?>

<!DOCTYPE html>
<html>

<head>
    <title>Admin Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="stylesheet" href="../assets/admin_css/admin_dashboard.css?v=2">
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

            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="view_attempts.php" class="<?= $current_page == 'view_attempts.php' ? 'active' : '' ?>">Attempts</a>
            <a href="charts.php" class="<?= $current_page == 'charts.php' ? 'active' : '' ?>">Analytics</a>
            <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">Users</a>
            <a href="add_category.php" class="<?= $current_page == 'add_category.php' ? 'active' : '' ?>">Categories</a>
            <a href="add_question.php" class="<?= $current_page == 'add_question.php' ? 'active' : '' ?>">Questions</a>

        </div>

        <!-- MAIN -->
        <div class="main">

            <!-- TOPBAR -->
            <div class="topbar">
                <h5>Admin Dashboard</h5>
                <div>
                    <?= htmlspecialchars($admin_name) ?>
                    <a href="../auth/logout.php" class="btn btn-danger btn-sm ms-3">Logout</a>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="row mb-4">

                <div class="col-md-3">
                    <div class="stat-card bg-blue text-center">
                        <h6>Total Users</h6>
                        <h3><?= $total_users ?></h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-purple text-center">
                        <h6>Total Attempts</h6>
                        <h3><?= $total_attempts ?></h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-green text-center">
                        <h6>Active Users</h6>
                        <h3><?= $active_users ?></h3>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card bg-orange text-center">
                        <h6>Total Questions</h6>
                        <h3><?= $total_questions ?></h3>
                    </div>
                </div>

            </div>

            <!-- CHARTS -->
            <div class="row mb-4">

                <div class="col-md-6">
                    <div class="card p-3 shadow">
                        <h6>Attempts Per Day</h6>
                        <canvas id="attemptChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-3 shadow">
                        <h6>Accuracy Distribution</h6>
                        <canvas id="accuracyChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- RECENT -->
            <h5>Recent Quiz Attempts</h5>

            <div class="card p-3 shadow">
                <table class="table table-bordered">
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Score</th>
                        <th>Accuracy</th>
                    </tr>

                    <?php while ($r = mysqli_fetch_assoc($recent)): ?>
                        <tr>
                            <td><?= $r['attempted_at'] ?></td>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars($r['category_name']) ?></td>
                            <td><?= $r['score'] ?></td>
                            <td><?= $r['accuracy'] ?>%</td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>

        </div> <!-- MAIN END -->
    </div> <!-- WRAPPER END -->

    <script>
        new Chart(attemptChart, {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [{
                    label: 'Attempts',
                    data: <?= json_encode($counts) ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.2)',
                    fill: true,
                    tension: 0.4
                }]
            }
        });

        new Chart(accuracyChart, {
            type: 'pie',
            data: {
                labels: ['Correct', 'Wrong'],
                datasets: [{
                    data: [
                        <?= $total_correct ?>,
                        <?= $total_wrong ?>
                    ],
                    backgroundColor: ['#198754', '#dc3545']
                }]
            }
        });
    </script>

</body>

</html>