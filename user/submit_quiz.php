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
   READ POST DATA
----------------------------*/
/* ---------------------------
   READ POST DATA
----------------------------*/
$user_id         = $_SESSION['user_id'];
$answers         = $_POST['answers'] ?? [];
$difficulty_mode = $_POST['difficulty_mode'] ?? '';

$time_taken = [];
if (isset($_POST['time_taken'])) {
    $time_taken = json_decode($_POST['time_taken'], true);
}


if (empty($answers)) {
    die("No answers submitted");
}

/* ---------------------------
   CALCULATE SCORE
----------------------------*/
$total_questions = count($answers);
$correct_answers = 0;

foreach ($answers as $question_id => $option_id) {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT is_correct FROM options WHERE id = ? AND question_id = ?"
    );
    mysqli_stmt_bind_param($stmt, "ii", $option_id, $question_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);

    if ($row && $row['is_correct'] == 1) {
        $correct_answers++;
    }
}

$score    = $correct_answers;
$accuracy = round(($correct_answers / $total_questions) * 100, 2);

/* ---------------------------
   TIME CALCULATIONS
----------------------------*/
$total_time = array_sum($time_taken);
$avg_time   = round($total_time / $total_questions, 2);

/* ---------------------------
   GET CATEGORY ID
----------------------------*/
$first_question_id = array_key_first($answers);

$q = mysqli_query(
    $conn,
    "SELECT category_id FROM questions WHERE id = " . (int)$first_question_id
);
$q = mysqli_fetch_assoc($q);
$category_id = $q['category_id'];

/* ---------------------------
   INSERT INTO quiz_attempts
----------------------------*/
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO quiz_attempts
     (user_id, category_id, difficulty, total_questions,
      correct_answers, score, accuracy)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);

mysqli_stmt_bind_param(
    $stmt,
    "iisiiid",
    $user_id,
    $category_id,
    $difficulty_mode,
    $total_questions,
    $correct_answers,
    $score,
    $accuracy
);

mysqli_stmt_execute($stmt);
$attempt_id = mysqli_insert_id($conn);

/* ---------------------------
   INSERT INTO attempt_details (WITH TIME)
----------------------------*/
foreach ($answers as $question_id => $option_id) {

    $check = mysqli_prepare(
        $conn,
        "SELECT is_correct FROM options WHERE id = ?"
    );
    mysqli_stmt_bind_param($check, "i", $option_id);
    mysqli_stmt_execute($check);
    $res = mysqli_stmt_get_result($check);
    $row = mysqli_fetch_assoc($res);

    $is_correct = ($row && $row['is_correct'] == 1) ? 1 : 0;
    $time = $time_taken[$question_id] ?? 0;

    $detail = mysqli_prepare(
        $conn,
        "INSERT INTO attempt_details
         (attempt_id, question_id, selected_option_id, is_correct, time_taken)
         VALUES (?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $detail,
        "iiiii",
        $attempt_id,
        $question_id,
        $option_id,
        $is_correct,
        $time
    );

    mysqli_stmt_execute($detail);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Quiz Result</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <link href="submit_quiz.css?v=2" rel="stylesheet">
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
            <script>
                document.querySelector(".collapse-btn").onclick = function() {
                    document.querySelector(".sidebar").classList.toggle("collapsed");
                };
            </script>


        </div>















<div class="container1">

        <div class="container" id="pdfArea">
            <h4 class="text-center mt-2">
                <?= htmlspecialchars($_SESSION['name']) ?>' Result
            </h4>


            <!-- HEADER -->
            <div class="result-header">
                <div class="result-icon">
                    <i class="bi bi-bullseye"></i>
                </div>

                <h1>Quiz Result</h1>
                <p>Don't Give Up! Practice Makes Perfect 💪</p>
            </div>

            <!-- MAIN ROW -->
            <div class="row g-4">

                <!-- SCORE -->
                <div class="col-md-4">
                    <div class="card-box text-center position-relative">

                        <div class="score-wrapper">

                            <canvas id="scoreChart"></canvas>

                            <div class="center-score">
                                <h2><?= round($accuracy) ?>%</h2>
                                <span>Accuracy</span>
                            </div>

                        </div>


                        <p class="mt-3 badge bg-danger">
                            <?= $score ?>/<?= $total_questions ?>
                        </p>

                    </div>
                </div>

                <!-- BREAKDOWN -->
                <div class="col-md-8">
                    <div class="card-box">

                        <h5 class="mb-3">Performance Breakdown</h5>

                        <div class="row g-3">

                            <div class="col-md-6">
                                <div class="stat-mini blue d-flex align-items-center gap-3">
                                    <div class="stat-icon icon-blue">
                                        <i class="bi bi-clipboard-data"></i>
                                    </div>
                                    <div>
                                        <small>Total Questions</small>
                                        <h3><?= $total_questions ?></h3>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-6">
                                <div class="stat-mini green d-flex align-items-center gap-3">
                                    <div class="stat-icon icon-green">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div>
                                        <small>Correct Answers</small>
                                        <h3><?= $correct_answers ?></h3>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-6">
                                <div class="stat-mini red d-flex align-items-center gap-3">
                                    <div class="stat-icon icon-red">
                                        <i class="bi bi-x-circle"></i>
                                    </div>
                                    <div>
                                        <small>Incorrect Answers</small>
                                        <h3><?= $total_questions - $correct_answers ?></h3>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-6">
                                <div class="stat-mini purple d-flex align-items-center gap-3">
                                    <div class="stat-icon icon-purple">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                    <div>
                                        <small>Total Time</small>
                                        <h3><?= $total_time ?>s</h3>
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-12">
                                <div class="stat-mini yellow d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="stat-icon icon-yellow">
                                            <i class="bi bi-lightning-charge"></i>
                                        </div>
                                        <small>Average Time per Question</small>
                                    </div>
                                    <strong><?= $avg_time ?>s</strong>
                                </div>
                            </div>


                        </div>

                    </div>
                </div>

            </div>

            <!-- DISTRIBUTION -->
            <div class="row mt-5">
                <div class="col-md-12">
                    <div class="card-box text-center">

                        <h5 class="mb-4">Answer Distribution</h5>

                        <canvas id="distChart" style="max-width:300px;margin:auto;"></canvas>

                    </div>
                </div>
            </div>

            <!-- PERFORMANCE RATING -->
            <div class="row mt-5">
                <div class="col-md-12">
                    <div class="card-box">

                        <div class="d-flex justify-content-between">
                            <strong>Performance Rating</strong>
                            <strong><?= round($accuracy) ?>%</strong>
                        </div>

                        <div class="rating-bar mt-2">
                            <div class="rating-fill" style="width:<?= round($accuracy) ?>%"></div>
                        </div>

                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Beginner</span>
                            <span>Intermediate</span>
                            <span>Expert</span>
                        </div>

                    </div>
                </div>
            </div>
        </div>


            <!-- BUTTONS -->
            <div class="row mt-5">
                <div class="col-md-4">
                    <a href="start_quiz.php" class="btn action-btn btn-blue w-100">
                        <i class="bi bi-arrow-repeat"></i> Attempt Another Quiz
                    </a>
                </div>

                <div class="col-md-4">
                    <a href="dashboard.php" class="btn action-btn btn-outline w-100">
                        <i class="bi bi-house"></i> Go to Dashboard
                    </a>
                </div>

                <div class="col-md-4">
                    <button class="btn action-btn btn-outline w-100" onclick="downloadPDF()">
                        <i class="bi bi-download"></i> Download Result
                    </button>

                    <script>
                        async function downloadPDF() {

                            const {
                                jsPDF
                            } = window.jspdf;

                            const element = document.getElementById("pdfArea");

                            const canvas = await html2canvas(element, {
                                scale: 2
                            });

                            const imgData = canvas.toDataURL("image/png");

                            const pdf = new jsPDF("p", "mm", "a4");

                            const pageWidth = pdf.internal.pageSize.getWidth();
                            const pageHeight = (canvas.height * pageWidth) / canvas.width;

                            pdf.addImage(imgData, "PNG", 0, 0, pageWidth, pageHeight);

                            pdf.save("Quiz_Result.pdf");
                        }
                    </script>



                </div>
            </div>

            <!-- TIPS -->
            <div class="row mt-5 mb-5">
                <div class="col-md-12">
                    <div class="tip-box">

                        <h5>Tips to Improve</h5>

                        <ul class="mt-3">
                            <li>Review the topics you missed</li>
                            <li>Practice more questions in this category</li>
                            <li>Read questions carefully</li>
                            <li>Retry after studying</li>
                        </ul>

                    </div>
                </div>
            </div>

        </div>

        <!-- CHARTS -->
        <script>
            new Chart(document.getElementById("scoreChart"), {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [<?= round($accuracy) ?>, <?= 100 - round($accuracy) ?>],
                        backgroundColor: ['#4f46e5', '#e5e7eb'],
                        borderWidth: 0
                    }]
                },
                options: {
                    cutout: '75%',
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });

            new Chart(document.getElementById("distChart"), {
                type: 'doughnut',
                data: {
                    labels: ['Correct', 'Incorrect'],
                    datasets: [{
                        data: [<?= $correct_answers ?>, <?= $total_questions - $correct_answers ?>],
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


    </div>

</body>

<script>
    document.getElementById("toggleBtn").onclick = function() {
        document.getElementById("sidebar").classList.toggle("collapsed");
    };
</script>


</html>