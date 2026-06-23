<?php
// ARQUIVO: reservaconexao.php
// OBJETIVO: Conexão segura com o Banco de Dados do Infinity Free
// DADOS PREENCHIDOS AUTOMATICAMENTE COM BASE NA SUA IMAGEM

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "db_duodate";

// Cria a conexão
$conn = new mysqli($servername, $username, $password, $dbname);

// Verifica se houve erro
if ($conn->connect_error) {
    die("Falha na conexão com o banco de reservas: " . $conn->connect_error);
}

// Define o padrão de caracteres para aceitar acentos (ç, ã, é)
$conn->set_charset("utf8");
?>
