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
// Garante que apenas um dos usuários envolvidos na conexão possa excluí-la.
$sql = "DELETE FROM conexoes WHERE id_conexao = ? AND (id_solicitante = ? OR id_solicitado = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $id_conexao, $id_usuario_logado, $id_usuario_logado);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    $_SESSION['success_message'] = "Conexão desvinculada com sucesso.";
} else {
    // Pode falhar se a conexão não existir ou o usuário não tiver permissão
    $_SESSION['error_message'] = "Não foi possível desvincular a conexão ou você não tem permissão.";
}

$stmt->close();
$conn->close();

header('Location: ../php/meus_dates.php');
exit();
?>