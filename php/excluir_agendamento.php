<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$sql = "DELETE FROM agendamentos 
        WHERE id_agendamento = ? AND id_usuario = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();

header("Location: meu_calendario.php");
exit();
?>