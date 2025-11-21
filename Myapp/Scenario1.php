<?php
$conn = mysqli_connect("localhost", "root", "", "class_db");
$id = $_GET['id']; // Changed from $_POST to $_GET //grahhhh
$sql = "SELECT * FROM students WHERE student_id = $id"; // Fixed column name
$res = mysqli_query($conn, $sql);
$r = mysqli_fetch_assoc($res);
echo $r['first_name'];
?>