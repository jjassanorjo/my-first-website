<?php
$page = max(0, intval($_GET['page'])); // Ensure positive integer
$limit = 5;
$offset = $page * $limit;
$sql = "SELECT * FROM students LIMIT $offset, $limit";
?>