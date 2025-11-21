<?php
$conn = mysqli_connect("localhost","root","", "class_db");
$first = $_POST['fname'];
$last = $_POST['lname'];

if(!empty($first) && !empty($last)) { // Added validation
    $sql = "INSERT INTO students (first_name,last_name) VALUES ('$first', '$last')";
    mysqli_query($conn, $sql);
    echo "Inserted!";
} else {
    echo "Please fill all fields!";
}
?>