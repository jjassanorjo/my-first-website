<?php  
$conn = mysqli_connect("localhost","root","", "class_db");
$age = $_GET['age'];

// FIXED: Using prepared statement to prevent SQL injection
$sql = "SELECT * FROM students WHERE age = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $age);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
?>