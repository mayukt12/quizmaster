<?php
// ALWAYS show errors while developing
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Correct include (do NOT change this)
require_once __DIR__ . "/../includes/db.php";

// total students
$students_q = mysqli_query($conn,"SELECT COUNT(*) AS total FROM users WHERE role='user'");
$students = mysqli_fetch_assoc($students_q)['total'];

// total questions
$questions_q = mysqli_query($conn,"SELECT COUNT(*) AS total FROM questions");
$questions = mysqli_fetch_assoc($questions_q)['total'];

//categories
$cat_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM categories");
$categories = mysqli_fetch_assoc($cat_q)['total'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $cpass = $_POST['confirm_password'];

    // 1. Password match check
    if ($pass !== $cpass) {
        $error = "Passwords do not match";
    }
    // 2. Minimum length check
    elseif (strlen($pass) < 8) {
        $error = "Password must be at least 8 characters long";
    }
    // 3. Any special character check
    elseif (!preg_match('/[^a-zA-Z0-9]/', $pass)) {
        $error = "Password must contain at least one special character";
    }
    else {
        // 4. Email exists check
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check, "s", $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = "Email already registered";
        } else {
            // 5. Hash password
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            // 6. Insert user
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "sss", $name, $email, $hash);
            mysqli_stmt_execute($stmt);

            $success = "Registration successful";
            header("Location: login.php?registered=1");
exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login | Smart Exam</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="register.css" rel="stylesheet">
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

                        <div class="brand">Create Your Account</div>
                        <div class="subtitle">Please enter your credentials to continue</div>

                        <?php if (!empty($error)): ?>
    <div class="alert alert-danger text-center">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success text-center">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>


                        <form method="POST">

                            <div class="mb-4 input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" name="name" class="form-control"
                                    placeholder="Name" required>
                            </div>


                            <div class="mb-4 input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" name="email" class="form-control"
                                    placeholder="Email address" required>
                            </div>
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
                            
                            <div class="mb-3 input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock"></i>
                                </span>

                                <input type="password"
                                    name="confirm_password"
                                    id="confirm_password"
                                    class="form-control"
                                    placeholder="confirm Password"
                                    required>

                                <span class="input-group-text"
                                    onclick="togglePassword()"
                                    style="cursor:pointer;">
                                    <i class="bi bi-eye" id="eyeIcon2"></i>
                                </span>
                            </div>
                            

                            <button type="submit" class="btn btn-primary w-100 btn-register">
                                Register
                            </button>


                        </form>
                        <div class="divider">Or continue with</div>

                        

                        <div class="text-center mt-3 signin-line">
                            already have account?
                            <a href="login.php" class="sign-in"> Sign In </a>
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
    const p1 = document.getElementById("password");
    const p2 = document.getElementById("confirm_password");
    const icon1 = document.getElementById("eyeIcon");
    const icon2 = document.getElementById("eyeIcon2");

    [p1, p2].forEach(p => {
        if (p.type === "password") {
            p.type = "text";
        } else {
            p.type = "password";
        }
    });

    [icon1, icon2].forEach(icon => {
        icon.classList.toggle("bi-eye");
        icon.classList.toggle("bi-eye-slash");
    });
}
</script>
    <script>
        function uiOnly() {
            alert("UI only - Not functional");
        }
    </script>


</body>

</html>
