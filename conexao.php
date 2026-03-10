<?php
$servername = "sql200.infinityfree.com";
$username = "if0_38704863";
$password = "duodates2025";
$database = "if0_38704863_db_duodate";

$conn = new mysqli($servername, $username, $password, $database);

$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
} else {

}
?>
