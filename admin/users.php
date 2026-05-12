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

/* ---------------- SEARCH ---------------- */
$search = $_GET['search'] ?? '';

/* ---------------- PAGINATION ---------------- */
$limit = 8;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page-1)*$limit;

/* ---------------- EXPORT CSV ---------------- */
if(isset($_GET['export'])){
    header("Content-Type:text/csv");
    header("Content-Disposition:attachment; filename=users.csv");
    $out=fopen("php://output","w");
    fputcsv($out,["Name","Email","Role","Status"]);

    $ex=mysqli_query($conn,"SELECT name,email,role,status FROM users WHERE is_deleted=0");
    while($r=mysqli_fetch_assoc($ex)){
        fputcsv($out,$r);
    }
    fclose($out);
    exit;
}

/* ---------------- BLOCK / UNBLOCK ---------------- */
if(isset($_GET['toggle'])){
    $id=(int)$_GET['toggle'];

    // prevent self block
    if($id != $_SESSION['user_id']){
        mysqli_query($conn,"
            UPDATE users
            SET status = IF(status='active','blocked','active')
            WHERE id=$id AND role!='admin'
        ");
    }
    header("Location: users.php");
    exit;
}

/* ---------------- CHANGE ROLE ---------------- */
if(isset($_POST['change_role'])){
    $uid=(int)$_POST['user_id'];
    $role=$_POST['role'];

    if($uid != $_SESSION['user_id']){
        mysqli_query($conn,"UPDATE users SET role='$role' WHERE id=$uid");
    }
    header("Location: users.php");
    exit;
}

/* ---------------- SOFT DELETE ---------------- */
if(isset($_GET['delete'])){
    $id=(int)$_GET['delete'];

    // prevent deleting admin or yourself
    if($id != $_SESSION['user_id']){
        mysqli_query($conn,"
            UPDATE users SET is_deleted=1
            WHERE id=$id AND role!='admin'
        ");
    }
    header("Location: users.php");
    exit;
}

/* ---------------- COUNT ---------------- */
$countQ=mysqli_query($conn,"
SELECT COUNT(*) total FROM users
WHERE is_deleted=0
AND (name LIKE '%$search%' OR email LIKE '%$search%')
");
$total=mysqli_fetch_assoc($countQ)['total'];
$totalPages=ceil($total/$limit);

/* ---------------- USERS (ADMINS FIRST) ---------------- */
$sql="
SELECT
u.id,u.name,u.email,u.role,u.status,
COUNT(qa.id) attempts,
ROUND(AVG(qa.accuracy),2) avg_acc,
MAX(qa.attempted_at) last_attempt
FROM users u
LEFT JOIN quiz_attempts qa ON u.id=qa.user_id
WHERE u.is_deleted=0
AND (u.name LIKE '%$search%' OR u.email LIKE '%$search%')
GROUP BY u.id
ORDER BY 
    CASE WHEN u.role='admin' THEN 0 ELSE 1 END,
    u.name
LIMIT $offset,$limit
";

$result=mysqli_query($conn,$sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin - Users</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin_css/admin_dashboard.css?v=2">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<style>
.table thead{background:#1e1e2f;color:#fff;}
.badge-admin{background:#4e73df;}
.badge-user{background:#1cc88a;}
.badge-active{background:#198754;}
.badge-blocked{background:#dc3545;}

.admin-row{
    background:linear-gradient(90deg,#fff3cd,#ffe69c);
    font-weight:600;
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
<a href="view_attempts.php">Attempts</a>
<a href="charts.php">Analytics</a>
<a class="active" href="users.php">Users</a>
<a href="add_category.php">Categories</a>
<a href="add_question.php">Questions</a>
</div>

<!-- MAIN -->
<div class="main">

<!-- TOPBAR -->
<div class="topbar">
<h5>User Management</h5>
<div>
<?= htmlspecialchars($admin_name) ?>
<a href="../auth/logout.php" class="btn btn-danger btn-sm ms-3">Logout</a>
</div>
</div>

<!-- SEARCH + EXPORT -->
<div class="card p-3 mb-3 shadow">
<form class="row g-3">

<div class="col-md-4">
<input type="text" name="search"
value="<?= htmlspecialchars($search) ?>"
class="form-control"
placeholder="Search by name or email">
</div>

<div class="col-md-2">
<button class="btn btn-primary w-100">Search</button>
</div>

<div class="col-md-2">
<a href="users.php?export=1" class="btn btn-success w-100">
Export CSV
</a>
</div>

</form>
</div>

<!-- USERS TABLE -->
<div class="card p-3 shadow">

<?php if(mysqli_num_rows($result)==0): ?>
<p class="text-center text-muted py-5">No users found.</p>
<?php else: ?>

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>
<tr>
<th>Name</th>
<th>Email</th>
<th>Role</th>
<th>Status</th>
<th>Attempts</th>
<th>Avg Accuracy</th>
<th>Last Attempt</th>
<th>Actions</th>
</tr>
</thead>

<tbody>

<?php while($u=mysqli_fetch_assoc($result)): ?>

<tr class="<?= $u['role']=='admin' ? 'admin-row' : '' ?>">

<td><?= htmlspecialchars($u['name']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>

<td>
<span class="badge <?= $u['role']=='admin'?'badge-admin':'badge-user' ?>">
<?= ucfirst($u['role']) ?>
</span>
</td>

<td>
<span class="badge <?= $u['status']=='active'?'badge-active':'badge-blocked' ?>">
<?= ucfirst($u['status']) ?>
</span>
</td>

<td><?= $u['attempts'] ?></td>

<td><?= $u['avg_acc']!==null ? $u['avg_acc'].'%' : '-' ?></td>

<td>
<?= $u['last_attempt']
? date("d M Y h:i A",strtotime($u['last_attempt']))
: '-' ?>
</td>

<td>

<?php if($u['attempts']>0): ?>
<a href="view_attempts.php?user_id=<?= $u['id'] ?>"
class="btn btn-sm btn-outline-primary">
Attempts
</a>
<?php endif; ?>

<?php if($u['role']!='admin' && $u['id']!=$_SESSION['user_id']): ?>

<a href="users.php?toggle=<?= $u['id'] ?>"
class="btn btn-sm btn-outline-warning">
<?= $u['status']=='active'?'Block':'Unblock' ?>
</a>

<a onclick="return confirm('Delete this user?')"
href="users.php?delete=<?= $u['id'] ?>"
class="btn btn-sm btn-outline-danger">
Delete
</a>

<?php endif; ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>
</table>

</div>

<?php endif; ?>

</div>

<!-- PAGINATION -->
<?php if($totalPages>1): ?>
<div class="mt-3 d-flex justify-content-center">
<nav>
<ul class="pagination">
<?php for($i=1;$i<=$totalPages;$i++): ?>
<li class="page-item <?= $page==$i?'active':'' ?>">
<a class="page-link"
href="users.php?page=<?= $i ?>&search=<?= $search ?>">
<?= $i ?>
</a>
</li>
<?php endfor; ?>
</ul>
</nav>
</div>
<?php endif; ?>

</div>
</div>

</body>
</html>
