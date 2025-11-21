<?php
$newEmail = $_POST['email'];
$id = $_POST['id']; // Added ID
$sql = "UPDATE students SET email='$newEmail' WHERE student_id=$id"; // Added WHERE
mysqli_query($conn,$sql);
?>