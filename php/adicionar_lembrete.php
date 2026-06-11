<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['titulo']) || !isset($_POST['data'])) {
    http_response_code(400);
    exit();
}

$user_id = $_SESSION['user_id'];
$titulo = trim($_POST['titulo']);
$data = str_replace('T', ' ', $_POST['data']) . ':00';
$status = 'aprovado';

$sql = "INSERT INTO agendamentos (id_usuario, titulo_evento, data_agendada, STATUS, id_local) VALUES (?, ?, ?, ?, NULL)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $user_id, $titulo, $data, $status);

if ($stmt->execute()) {
    http_response_code(200);
} else {
    http_response_code(500);
}

$stmt->close();
$conn->close();
?>