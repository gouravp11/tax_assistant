<?php
// Database connection details
$hostName = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "tax_assistant";

// Establish a connection to the database
$conn = mysqli_connect($hostName, $dbUser, $dbPassword, $dbName);

// Check if the connection was successful
if (!$conn) {
    die("Something went wrong;");
}
?>