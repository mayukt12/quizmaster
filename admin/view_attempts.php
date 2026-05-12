<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . "/../includes/db.php";

if(!isset($_SESSION['user_id']) || $_SESSION['role']!=='admin'){
    header("Location: ../auth/login.php");
    exit;
}

$admin_name = $_SESSION['name'] ?? 'Admin';

/* FILTER */
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$sort = $_GET['sort'] ?? 'latest';

switch($sort){
    case 'oldest': $orderBy="qa.attempted_at ASC"; break;
    case 'accuracy': $orderBy="qa.accuracy DESC"; break;
    case 'score': $orderBy="qa.score DESC"; break;
    default: $orderBy="qa.attempted_at DESC";
}

$where="";
$params=[];
$types="";

if($user_id>0){
    $where="WHERE qa.user_id=?";
    $params[]=$user_id;
    $types.="i";
}

/* DATA */
$sql="
SELECT
 qa.id attempt_id,
 u.name user_name,
 u.email,
 c.category_name,
 qa.difficulty,
 qa.total_questions,
 qa.correct_answers,
 qa.score,
 qa.accuracy,
 qa.attempted_at,
 IFNULL(SUM(ad.time_taken),0) total_time
FROM quiz_attempts qa
JOIN users u ON qa.user_id=u.id
JOIN categories c ON qa.category_id=c.id
LEFT JOIN attempt_details ad ON qa.id=ad.attempt_id
$where
GROUP BY qa.id
ORDER BY $orderBy
";

$stmt=mysqli_prepare($conn,$sql);
if($user_id>0){
    mysqli_stmt_bind_param($stmt,$types,...$params);
}
mysqli_stmt_execute($stmt);
$result=mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin - Attempts</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin_css/admin_dashboard.css?v=2">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>

/* Difficulty badges */
.badge-easy{background:#198754;}
.badge-medium{background:#0dcaf0;color:#000;}
.badge-hard{background:#dc3545;}

/* Table header */
.table thead{
    background:#1e1e2f;
    color:#fff;
}

/* Nice spacing */
.table td, .table th{
    vertical-align:middle;
}

</style>

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
<a class="active" href="view_attempts.php">Attempts</a>
<a href="charts.php">Analytics</a>
<a href="users.php">Users</a>
<a href="add_category.php">Categories</a>
<a href="add_question.php">Questions</a>
</div>

<!-- MAIN -->
<div class="main">

<!-- TOPBAR -->
<div class="topbar">
<h5>Quiz Attempts</h5>
<div>
<?= htmlspecialchars($admin_name) ?>
<a href="../auth/logout.php" class="btn btn-danger btn-sm ms-3">Logout</a>
</div>
</div>

<!-- FILTER -->
<div class="card p-3 mb-3 shadow filter-card">
<form method="GET" class="row g-3 align-items-end">

<?php if($user_id>0): ?>
<input type="hidden" name="user_id" value="<?= $user_id ?>">
<?php endif; ?>

<div class="col-md-3">
<label class="fw-bold">View By</label>
<select name="sort" class="form-select" onchange="this.form.submit()">
<option value="latest" <?= $sort=='latest'?'selected':'' ?>>Latest Attempts</option>
<option value="oldest" <?= $sort=='oldest'?'selected':'' ?>>Oldest Attempts</option>
<option value="accuracy" <?= $sort=='accuracy'?'selected':'' ?>>Highest Accuracy</option>
<option value="score" <?= $sort=='score'?'selected':'' ?>>Highest Score</option>
</select>
</div>

</form>
</div>

<!-- TABLE -->
<div class="card p-3 shadow">

<?php if(mysqli_num_rows($result)==0): ?>
<p class="text-center text-muted py-5">No attempts found.</p>
<?php else: ?>

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>
<tr>
<th>Date</th>
<th>User</th>
<th>Email</th>
<th>Category</th>
<th>Difficulty</th>
<th>Total Q</th>
<th>Correct</th>
<th>Score</th>
<th>Accuracy</th>
<th>Total Time</th>
<th>Avg/Q</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($result)):

$avg=$row['total_questions']>0
? round($row['total_time']/$row['total_questions'],2)
: 0;

$cls = match($row['difficulty']){
 'easy'=>'badge-easy',
 'medium'=>'badge-medium',
 'hard'=>'badge-hard',
 default=>'bg-secondary'
};
?>

<tr>
<td><?= $row['attempted_at'] ?></td>
<td><?= htmlspecialchars($row['user_name']) ?></td>
<td><?= htmlspecialchars($row['email']) ?></td>
<td><?= htmlspecialchars($row['category_name']) ?></td>

<td>
<span class="badge <?= $cls ?>">
<?= ucfirst($row['difficulty']) ?>
</span>
</td>

<td><?= $row['total_questions'] ?></td>
<td><?= $row['correct_answers'] ?></td>
<td><?= $row['score'] ?></td>

<td class="fw-bold"><?= $row['accuracy'] ?>%</td>

<td><?= $row['total_time'] ?>s</td>
<td><?= $avg ?>s</td>

<td>
<a href="../user/review.php?attempt_id=<?= $row['attempt_id'] ?>"
class="btn btn-outline-primary btn-sm">
View
</a>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<?php endif; ?>

</div>

</div>
</div>

</body>
</html>


