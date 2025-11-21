<?php  
$conn = mysqli_connect("localhost", "root", "", "class_db");
$email = $_GET['email'];  // ← FIXED: Changed $_POST to $_GET
$sql = "SELECT * FROM students WHERE email='$email'";  
$res = mysqli_query($conn, $sql);  
?>