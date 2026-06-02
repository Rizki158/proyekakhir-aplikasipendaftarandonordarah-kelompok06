<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "pmi_ayodonor";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>