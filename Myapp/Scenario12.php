<?php
$id = intval($_GET['id']); // Cast to integer
$sql = "SELECT * FROM students WHERE student_id = $id"; // Removed quotes
?>