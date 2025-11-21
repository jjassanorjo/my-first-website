<?php
$age = $_POST['age'];
$sql = "SELECT * FROM students WHERE age = $age"; // Fixed variable name
?>