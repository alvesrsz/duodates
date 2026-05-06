<?php
session_start();
include '../conexao.php';

// Proteção da página
if (!isset($_SESSION['user_id']) || !isset($_GET['id_conexao'])) {
    header('Location: ../php/login.php');
    exit();
}

$id_conexao = filter_var($_GET['id_conexao'], FILTER_VALIDATE_INT);
$id_usuario_logado = $_SESSION['user_id'];

if ($id_conexao === false) {
    $_SESSION['error_message'] = "ID de conexão inválido.";
    header('Location: ../php/meus_dates.php');
    exit();
}

// Query de deleção com segurança:
// Garante que apenas o usuário que recebeu o convite pode recusá-lo.
$sql = "DELETE FROM conexoes WHERE id_conexao = ? AND id_solicitado = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id_conexao, $id_usuario_logado);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success_message'] = "Solicitação recusada.";
} else {
    $_SESSION['error_message'] = "Não foi possível recusar a conexão ou você não tem permissão.";
}

$stmt->close();
$conn->close();

header('Location: ../php/meus_dates.php');
exit();
?>