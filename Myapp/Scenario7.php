<?php
$conn = mysqli_connect("localhost","root","", "class_db");
$id = $_POST['id'];
$email = $_POST['email'];
$sql = "UPDATE students SET email='$email' WHERE student_id=$id"; // Added quotes and fixed column
$res = mysqli_query($conn, $sql);

if($res) {
    echo "Updated!";
} else {
    echo "Error: " . mysqli_error($conn); // Added error handling
}
?>