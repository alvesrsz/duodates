<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "db_duodate";

$conn = new mysqli($servername, $username, $password, $database);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}
?>
