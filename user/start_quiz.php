<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// User protection
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? "User";

// Fetch categories
$categories = mysqli_query($conn, "SELECT * FROM categories");
?>
<!DOCTYPE html>
<html>

<head>
    <title>Start Quiz</title>
    <script>
        function toggleMode() {
            const questionBox = document.getElementById('questionBox');
            const timeBox = document.getElementById('timeBox');

            const mode = document.querySelector('input[name="exam_mode"]:checked');

            if (!mode) return;

            if (mode.value === 'question') {
                questionBox.style.display = 'block';
                timeBox.style.display = 'none';
            } else if (mode.value === 'time') {
                questionBox.style.display = 'none';
                timeBox.style.display = 'block';
            }
        }
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="start_quiz.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

</head>


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
            <div class="row g-4 ">
                <!-- TOPBAR -->
                <div class="topbar">
                    <h5 class="mb-0">
                        Start New Quiz 🚀, <?= htmlspecialchars($user_name) ?>
                    </h5>

                    
                </div>


                <div class="quiz-wrapper">

                    <div class="quiz-card">

                        <div class="quiz-icon">
                            <i class="bi bi-book"></i>
                        </div>

                        <h2 class="quiz-title">Start New Quiz 🚀</h2>
                        <p class="quiz-subtitle">Choose your preferences and begin</p>

                        <form method="GET" action="quiz.php">

                            <!-- CATEGORY -->
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                                        <option value="<?= $cat['id'] ?>">
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- DIFFICULTY -->
                            <div class="mb-3">
                                <label class="form-label">Difficulty</label>
                                <select name="difficulty_mode" class="form-select" required>
                                    <option value="">Select Difficulty</option>
                                    <option value="easy">Easy</option>
                                    <option value="medium">Medium</option>
                                    <option value="hard">Hard</option>
                                </select>
                            </div>

                            <!-- EXAM MODE -->
                            <!-- EXAM MODE -->
                            <div class="exam-section">

                                <label class="section-label">Exam Mode</label>

                                <!-- QUESTION MODE CARD -->
                                <div class="mode-card active" id="questionModeCard" onclick="selectMode('question')">
                                    <span>#</span>
                                    <p>Based on Number of Questions</p>
                                </div>

                                <!-- QUESTION SLIDER -->
                                <div class="slider-box" id="questionBox">
                                    <div class="d-flex justify-content-between">
                                        <label>Number of Questions</label>
                                        <strong><span id="questionValue">10</span></strong>
                                    </div>

                                    <input type="range" name="limit" min="5" max="50" value="10"
                                        oninput="questionValue.innerText=this.value">
                                </div>

                                <!-- TIME MODE CARD -->
                                <div class="mode-card" id="timeModeCard" onclick="selectMode('time')">
                                    <i class="bi bi-clock"></i>
                                    <p>Based on Time Limit</p>
                                </div>

                                <!-- TIME SLIDER -->
                                <div class="slider-box" id="timeBox" style="display:none;">
                                    <div class="d-flex justify-content-between">
                                        <label>Time Limit (minutes)</label>
                                        <strong><span id="timeValue">5</span></strong>
                                    </div>

                                    <input type="range" name="time_limit" min="1" max="60" value="5"
                                        oninput="timeValue.innerText=this.value">
                                </div>

                                <!-- HIDDEN INPUT -->
                                <input type="hidden" name="exam_mode" id="exam_mode" value="question">

                            </div>



                            <button class="btn-start" type="submit">
                                <i class="bi bi-play"></i> Start Quiz
                            </button>

                        </form>

                    </div>
                </div>



            </div>

        </div>

    </div>
</div>










</body>
<script>
function selectMode(mode){

    const qCard = document.getElementById("questionModeCard");
    const tCard = document.getElementById("timeModeCard");
    const qBox  = document.getElementById("questionBox");
    const tBox  = document.getElementById("timeBox");

    if(mode === "question"){
        qCard.classList.add("active");
        tCard.classList.remove("active");

        qBox.style.display = "block";
        tBox.style.display = "none";

        document.getElementById("exam_mode").value = "question";
    }

    if(mode === "time"){
        tCard.classList.add("active");
        qCard.classList.remove("active");

        tBox.style.display = "block";
        qBox.style.display = "none";

        document.getElementById("exam_mode").value = "time";
    }
}
</script>




<script>
    document.querySelector(".collapse-btn").onclick = function() {
        document.querySelector(".sidebar").classList.toggle("collapsed");
    };
</script>

</html>