<?php
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . "/../includes/db.php";

/* =============================
   ADMIN PROTECTION
============================= */
if(!isset($_SESSION['user_id']) || $_SESSION['role']!=='admin'){
    header("Location: ../auth/login.php");
    exit;
}

$admin_name = $_SESSION['name'] ?? 'Admin';

/* =============================
   ADD CATEGORY
============================= */
if(isset($_POST['add_category'])){
    $name = ucwords(strtolower(trim($_POST['name'])));

    if($name!=""){
        $chk = mysqli_prepare($conn,
            "SELECT id FROM categories WHERE LOWER(category_name)=LOWER(?)"
        );
        mysqli_stmt_bind_param($chk,"s",$name);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);

        if(mysqli_stmt_num_rows($chk)>0){
            $error="Category already exists!";
        }else{
            $ins=mysqli_prepare($conn,
                "INSERT INTO categories(category_name) VALUES(?)"
            );
            mysqli_stmt_bind_param($ins,"s",$name);
            mysqli_stmt_execute($ins);
            $success="Category added successfully!";
        }
    }
}

/* =============================
   UPDATE CATEGORY
============================= */
if(isset($_POST['update_category'])){
    $id=(int)$_POST['id'];
    $name=ucwords(strtolower(trim($_POST['name'])));

    mysqli_query($conn,"
        UPDATE categories SET category_name='$name'
        WHERE id=$id
    ");

    header("Location: add_category.php");
    exit;
}

/* =============================
   DELETE CATEGORY
============================= */
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];

    $chk=mysqli_fetch_assoc(mysqli_query($conn,"
        SELECT COUNT(*) c FROM questions WHERE category_id=$id
    "))['c'];

    if($chk==0){
        mysqli_query($conn,"DELETE FROM categories WHERE id=$id");
        header("Location:add_category.php");
        exit;
    }
}

/* =============================
   SEARCH
============================= */
$search=$_GET['search'] ?? "";

/* =============================
   FETCH CATEGORIES
============================= */
$result=mysqli_query($conn,"
SELECT c.id,c.category_name,
COUNT(q.id) total_q
FROM categories c
LEFT JOIN questions q ON c.id=q.category_id
WHERE c.category_name LIKE '%$search%'
GROUP BY c.id
ORDER BY c.category_name
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Category Management</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin_css/admin_dashboard.css?v=2">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
.table thead{background:#1e1e2f;color:#fff;}
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
<a href="view_attempts.php">Attempts</a>
<a href="charts.php">Analytics</a>
<a href="users.php">Users</a>
<a class="active" href="add_category.php">Categories</a>
<a href="add_question.php">Questions</a>
</div>

<!-- MAIN -->
<div class="main">

<!-- TOPBAR -->
<div class="topbar">
<h5>Category Management</h5>
<div>
<?= htmlspecialchars($admin_name) ?>
<a href="../auth/logout.php" class="btn btn-danger btn-sm ms-3">Logout</a>
</div>
</div>

<!-- ALERTS -->
<?php if(!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<?php if(!empty($success)): ?>
<div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<!-- ADD CATEGORY -->
<div class="card p-3 mb-4 shadow">
<h6>Add New Category</h6>

<form method="POST" class="row g-3">
<div class="col-md-8">
<input type="text" name="name"
class="form-control"
placeholder="Enter category name" required>
</div>

<div class="col-md-4">
<button name="add_category"
class="btn btn-primary w-100">
Add Category
</button>
</div>
</form>
</div>

<!-- SEARCH -->
<div class="card p-3 mb-3 shadow">
<form class="row g-3">

<div class="col-md-8">
<input type="text" name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search category...">
</div>

<div class="col-md-4">
<button class="btn btn-secondary w-100">
Search
</button>
</div>

</form>
</div>

<!-- CATEGORY TABLE -->
<div class="card p-3 shadow">

<table class="table table-hover align-middle">

<thead>
<tr>
<th>#</th>
<th>Category Name</th>
<th>Total Questions</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php $i=1; while($row=mysqli_fetch_assoc($result)): ?>

<tr>

<td><?= $i++ ?></td>

<td>

<form method="POST" class="d-flex gap-2">
<input type="hidden" name="id" value="<?= $row['id'] ?>">
<input type="text" name="name"
value="<?= htmlspecialchars($row['category_name']) ?>"
class="form-control form-control-sm">
<button name="update_category"
class="btn btn-sm btn-outline-primary">
Save
</button>
</form>

</td>

<td>
<span class="badge bg-info">
<?= $row['total_q'] ?>
</span>
</td>

<td>

<?php if($row['total_q']==0): ?>
<a onclick="return confirm('Delete this category?')"
href="add_category.php?delete=<?= $row['id'] ?>"
class="btn btn-sm btn-outline-danger">
Delete
</a>
<?php else: ?>
<span class="text-muted">In Use</span>
<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>
</table>

</div>

</div>
</div>

</body>
</html>



