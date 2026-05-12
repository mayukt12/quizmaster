 <?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "exam_system";
$port = 3306; 

$conn = mysqli_connect($host, $user, $pass,$db, $port);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>