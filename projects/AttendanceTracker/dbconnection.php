<?php
// Database Connection
$host = "localhost";
$user = "root";
$password = "";
$database = "attendance_tracker";

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>