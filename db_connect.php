<?php
// db_connect.php

$dbHost = 'localhost'; // Or your database host
$dbUser = 'root';      // Your database username
$dbPass = '';          // Your database password
$dbName = 'foodnow'; // <<-- UPDATED DATABASE NAME

$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Optional: Set character set
mysqli_set_charset($conn, "utf8mb4");

?>