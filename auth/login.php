<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../includes/db.php";

$error = '';


// total students
$students_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users WHERE role='user'");
$students = mysqli_fetch_assoc($students_q)['total'];

// total questions
$questions_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM questions");
$questions = mysqli_fetch_assoc($questions_q)['total'];

//categories
$cat_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM categories");
$categories = mysqli_fetch_assoc($cat_q)['total'];



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $login_role = $_POST['login_role'] ?? 'user';


    if (empty($email) || empty($pass)) {
        $error = "All fields are required";
    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, name, password, role 
FROM users 
WHERE email = ? AND role = ?
"
        );
        mysqli_stmt_bind_param($stmt, "ss", $email, $login_role);

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {

            if (password_verify($pass, $user['password'])) {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['role']    = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../user/dashboard.php");
                }
                exit;
            }
        }

        $error = "Invalid email or password";
    }
}

if (isset($_GET['registered'])): ?>
    <div class="alert alert-success text-center">
        Account created successfully. Please login.
    </div>
<?php endif;

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login | Smart Exam</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="login.css?v=3">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">



</head>

<body>

    <div class="split">

        <div class="split1">
            <div class="one-main">

                <div class="brandd">
                    <div class="logo-box">
                        <i class="bi bi-book"></i>
                    </div>

                    <div class="brand-text">
                        <h1>QuizMaster</h1>
                        <p>Learn. Practice. Excel.</p>
                    </div>

                </div>
                <div class="hero-content">

                    <h2 class="first-h2">Master Your </h2>
                    <h2 class="second-h2">Knowledge</h2>

                    <p class="hero-sub">
                        Join thousands of students improving their skills with our
                        comprehensive MCQ platform .
                    </p>

                    <div class="feature-box">
                        <i class="bi bi-stars"></i>
                        <span>Thousands of practice questions</span>
                    </div>

                    <div class="feature-box">
                        <i class="bi bi-trophy"></i>
                        <span>Track your progress in real-time</span>
                    </div>

                    <div class="feature-box">
                        <i class="bi bi-lightning-charge"></i>
                        <span>Compete with students worldwide</span>
                    </div>

                    <div class="stats">

                        <div>
                            <h3><?= number_format($students) ?>+</h3>
                            <p>Students</p>
                        </div>

                        <div>
                            <h3><?= number_format($questions) ?>+</h3>
                            <p>Questions</p>
                        </div>

                        <div>
                            <h3><?= $categories ?>+</h3>
                            <p>Categories</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="split2">
            <div class="login-wrapper">

                <div class="card login-card">
                    <div class="card-body">

                        <div class="brand">Welcome Back!</div>
                        <div class="subtitle">Please enter your credentials to continue</div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger text-center">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" novalidate>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Login As</label>

                                <div class="role-toggle">

                                    <input type="radio" name="login_role" id="userRole" value="user" checked>
                                    <input type="radio" name="login_role" id="adminRole" value="admin">

                                    <span class="toggle-bg"></span>

                                    <label for="userRole" class="toggle-option left">User</label>
                                    <label for="adminRole" class="toggle-option right">Admin</label>
                                </div>
                            </div>
                            <div class="mb-2">Email Address :</div>
                            <div class="mb-4 input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" name="email" class="form-control"
                                    placeholder="Email address" required>
                            </div>

                            <div class="mb-2">Password :</div>
                            <div class="mb-3 input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>

                                <input type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    placeholder="Password"
                                    required>

                                <span class="input-group-text"
                                    onclick="togglePassword()"
                                    style="cursor:pointer;">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </span>
                            </div>


                            <button type="submit" class="btn btn-primary w-100 btn-login">
                                Login
                            </button>


                        </form>
                        <div class="divider">Or continue with</div>



                        <div class="text-center mt-3 register-line">
                            Don’t have an account?
                            <a href="register.php" class="register"> Sign up for free</a>
                        </div>

                    </div>
                </div>

            </div>

            <div class="trust-box">
                <span class="star">✨</span>
                <span>Trusted by <?= number_format($students) ?>+ students worldwide</span>
            </div>

        </div>

    </div>

    <!-- SCRIPT MOVED HERE -->
    <script>
        function togglePassword() {
            const p = document.getElementById("password");
            const icon = document.getElementById("eyeIcon");

            if (p.type === "password") {
                p.type = "text";
                icon.classList.remove("bi-eye");
                icon.classList.add("bi-eye-slash");
            } else {
                p.type = "password";
                icon.classList.remove("bi-eye-slash");
                icon.classList.add("bi-eye");
            }
        }
    </script>
    <script>
        function uiOnly() {
            alert("UI only - Not functional");
        }
    </script>
</body>

</html>