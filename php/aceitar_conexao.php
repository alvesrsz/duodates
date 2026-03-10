<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id_conexao'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$id_conexao = (int)$_GET['id_conexao'];

$sql = "UPDATE conexoes SET status = 'aceito' 
        WHERE id_conexao = ? AND id_solicitado = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id_conexao, $user_id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    
    $_SESSION['success_message'] = "Conexão aceita! Responda ao questionário para ver sua compatibilidade.";
    
    // *** CORREÇÃO IMPORTANTE ***
    // Redireciona para o questionário DESTA conexão
    header('Location: ../php/compatibilidade.php?id_conexao=' . $id_conexao);
    exit();
    
} else {
    $_SESSION['error_message'] = "Erro ao aceitar a conexão.";
    header('Location: ../php/meus_dates.php');
    exit();
}
?>