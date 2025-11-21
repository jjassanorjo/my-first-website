<?php  
$conn = mysqli_connect("localhost","root","", "class_db");
$fname = $_POST['fname'];
$sql = "SELECT * FROM students WHERE first_name = '$fname'"; // Added quotes
$res = mysqli_query($conn, $sql);  
?>