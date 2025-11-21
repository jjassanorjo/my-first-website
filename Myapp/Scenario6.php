<?php
$conn = mysqli_connect("localhost","root","","class_db");
$id = intval($_GET['id']); // Sanitize input
$sql = "DELETE FROM students WHERE student_id = $id"; // Fixed column name
mysqli_query($conn, $sql);
?>