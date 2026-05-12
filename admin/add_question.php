<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . "/../includes/db.php";





/* ======================
   ADMIN PROTECTION
====================== */
if(!isset($_SESSION['user_id']) || $_SESSION['role']!=='admin'){
    header("Location: ../auth/login.php");
    exit;
}

$admin_name = $_SESSION['name'] ?? 'Admin';

/* ======================
   FETCH CATEGORIES
====================== */
$cats=mysqli_query($conn,"SELECT id,category_name FROM categories");

/* ======================
   ADD QUESTION
====================== */
if(isset($_POST['add_question'])){

$cat=$_POST['category_id'];
$q=$_POST['question'];
$diff=$_POST['difficulty'];
$correct=$_POST['correct'];
$options=$_POST['options'];




mysqli_query($conn,"
INSERT INTO questions(category_id,question,difficulty)
VALUES('$cat','$q','$diff')
");

$qid=mysqli_insert_id($conn);

foreach($options as $i=>$opt){
$iscorrect=($i==$correct)?1:0;
mysqli_query($conn,"
INSERT INTO options(question_id,option_text,is_correct)
VALUES('$qid','$opt','$iscorrect')
");
}

$success="Question Added";
}

/* ======================
   DELETE QUESTION
====================== */
if(isset($_GET['delete'])){
$id=(int)$_GET['delete'];
mysqli_query($conn,"DELETE FROM options WHERE question_id=$id");
mysqli_query($conn,"DELETE FROM questions WHERE id=$id");
header("Location:add_question.php");
exit;
}

/* ======================
   CSV IMPORT
====================== */
if(isset($_POST['import_csv'])){

$f=fopen($_FILES['csv']['tmp_name'],"r");
$first=true;

while(($row=fgetcsv($f))!==false){

    if($first){ $first=false; continue; }
    if(count($row)<8) continue;

    $cat=$row[0];
    $ques=$row[1];
    $diff=$row[2];
    $o1=$row[3];
    $o2=$row[4];
    $o3=$row[5];
    $o4=$row[6];
    $correct = max(0, min(3, ((int)$row[7]) - 1));

    $res = mysqli_query($conn,"
SELECT id FROM categories WHERE category_name='$cat'
");

$row = mysqli_fetch_assoc($res);
if(!$row) continue; // skip row

$c = $row['id'];

    mysqli_query($conn,"
    INSERT INTO questions(category_id,question,difficulty)
    VALUES('$c','$ques','$diff')
    ");

    $qid=mysqli_insert_id($conn);
    $ops=[$o1,$o2,$o3,$o4];

    foreach($ops as $i=>$op){
        $corr=($i==$correct)?1:0;
        mysqli_query($conn,"
        INSERT INTO options(question_id,option_text,is_correct)
        VALUES('$qid','$op','$corr')
        ");
    }
}

$success="CSV Imported";
}





/* ======================
   SEARCH + PAGINATION
====================== */
$search=$_GET['search']??'';
$limit=8;
$page=$_GET['page']??1;
$start=($page-1)*$limit;

$count=mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) c FROM questions
WHERE question LIKE '%$search%'
"))['c'];

$pages=ceil($count/$limit);

$questions=mysqli_query($conn,"
SELECT q.*,c.category_name
FROM questions q
JOIN categories c ON q.category_id=c.id
WHERE q.question LIKE '%$search%'
ORDER BY q.id DESC
LIMIT $start,$limit
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Question Management</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin_css/admin_dashboard.css?v=2">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

</head>

<body>

<div class="wrapper">

<div class="sidebar">
<div class="logo">
                <div class="logo-icon">
                    <i class="bi bi-book"></i>
                </div>
                <div class="logo-text">QuizMaster</div>
            </div>
<a href="dashboard.php">Dashboard</a>
<a href="view_attempts.php">Attempts</a>
<a href="charts.php">Analytics</a>
<a href="users.php">Users</a>
<a href="add_category.php">Categories</a>
<a class="active" href="add_question.php">Questions</a>
</div>

<div class="main">

<div class="topbar">
<h5>Question Management</h5>
<div>
<?= $admin_name ?>
<a href="../auth/logout.php" class="btn btn-danger btn-sm ms-3">Logout</a>
</div>
</div>

<?php if(!empty($success)): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<!-- ADD QUESTION -->
<div class="card p-4 mb-4">

<form method="POST" enctype="multipart/form-data">

<select name="category_id" class="form-select mb-2" required>
<option value="">Select Category</option>
<?php while($c=mysqli_fetch_assoc($cats)): ?>
<option value="<?= $c['id'] ?>"><?= $c['category_name'] ?></option>
<?php endwhile; ?>
</select>

<textarea name="question" class="form-control mb-2" placeholder="Enter Question" required></textarea>



<select name="difficulty" class="form-select mb-2">
<option value="easy">Easy</option>
<option value="medium">Medium</option>
<option value="hard">Hard</option>
</select>

<?php for($i=0;$i<4;$i++): ?>
<div class="d-flex mb-2">
<input type="radio" name="correct" value="<?= $i ?>" required>
<input type="text" name="options[]" class="form-control ms-2" placeholder="Option <?= $i+1 ?>" required>
</div>
<?php endfor; ?>

<button name="add_question" class="btn btn-primary w-100">Save Question</button>

</form>

</div>

<!-- IMPORTS -->
<!-- IMPORT SECTION -->
<div class="mt-4 mb-3">
    <div class="card p-4 shadow">

<h6>Import Questions (CSV)</h6>
<p class="text-muted small mb-2">
Format:
<code>category,question,difficulty,option1,option2,option3,option4,correct_option_index</code>
</p>

<p class="text-muted small">
Example:<br>
Science,What is 2+2?,easy,1,2,3,4,4
</p>

<form method="post" enctype="multipart/form-data">
<input type="file" name="csv" accept=".csv" required>
<button name="import_csv" class="btn btn-success w-100 mt-3">
Import CSV
</button>
</form>


</div>
</div>




<!-- SEARCH -->
<form method="GET" class="question-search-box mb-3">
    <input type="text"
           name="search"
           value="<?= htmlspecialchars($search) ?>"
           placeholder="🔍 Search question..."
           class="form-control">
</form>

<!-- TABLE -->
<div class="card p-4">

<table class="table table-hover">
<thead class="table-dark">
<tr>
<th>ID</th>
<th>Question</th>
<th>Category</th>
<th>Difficulty</th>
<th>Action</th>
</tr>
</thead>

<tbody>
<?php while($q=mysqli_fetch_assoc($questions)): ?>
<tr>
<td><?= $q['id'] ?></td>
<td><?= htmlspecialchars($q['question']) ?></td>
<td><?= htmlspecialchars($q['category_name']) ?></td>
<td><?= htmlspecialchars(ucfirst($q['difficulty'])) ?></td>
<td>
<a href="?delete=<?= $q['id'] ?>" class="btn btn-sm btn-danger"
onclick="return confirm('Delete?')">Delete</a>
</td>
</tr>
<?php endwhile; ?>
</tbody>

</table>

</div>

<!-- PAGINATION -->
<nav class="mt-3">
<ul class="pagination justify-content-center">
<?php for($i=1;$i<=$pages;$i++): ?>
<li class="page-item <?= $i==$page?'active':'' ?>">
<a class="page-link" 
href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
<?= $i ?>
</a>
</li>
<?php endfor; ?>
</ul>
</nav>

</div>
</div>


</body>
</html>


