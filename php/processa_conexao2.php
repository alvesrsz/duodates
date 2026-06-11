<?php
session_start();
include '../conexao.php'; // 1. Inclui a conexão

// 2. Segurança: O usuário está logado?
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

// 3. Segurança: O ID do usuário a ser solicitado foi enviado pela URL?
if (!isset($_GET['id'])) {
    // Define uma mensagem de erro na sessão
    $_SESSION['error_message'] = "Erro: ID de usuário não fornecido.";
    // Volta para a página de busca
    header('Location: ../php/criar_conexao.php'); 
    exit();
}

// 4. Pega os IDs
$id_solicitante = (int)$_SESSION['user_id'];
$id_solicitado = (int)$_GET['id'];

// 5. Validação: Não pode se auto-solicitar
if ($id_solicitante === $id_solicitado) {
    $_SESSION['error_message'] = "Você não pode enviar um pedido de conexão para si mesmo.";
    header('Location: ../php/criar_conexao.php');
    exit();
}

// 6. Validação: Verifica se já existe uma conexão ou pedido pendente (em qualquer direção)
$sql_check = "SELECT id_conexao FROM conexoes 
              WHERE (id_solicitante = ? AND id_solicitado = ?) 
              OR (id_solicitante = ? AND id_solicitado = ?)";

$stmt_check = $conn->prepare($sql_check);
// Liga os 4 parâmetros: (A -> B) OR (B -> A)
$stmt_check->bind_param("iiii", $id_solicitante, $id_solicitado, $id_solicitado, $id_solicitante);
$stmt_check->execute();
$result_check = $stmt_check->get_result();

if ($result_check->num_rows > 0) {
    // Já existe uma conexão ou pedido
    $stmt_check->close();
    $_SESSION['error_message'] = "Você já possui uma conexão ou um pedido pendente com este usuário.";
    header('Location: ../php/criar_conexao.php');
    exit();
}
$stmt_check->close();

// 7. Se todas as validações passaram, insere o novo pedido
$sql_insert = "INSERT INTO conexoes (id_solicitante, id_solicitado, status) VALUES (?, ?, 'pendente')";
$stmt_insert = $conn->prepare($sql_insert);
$stmt_insert->bind_param("ii", $id_solicitante, $id_solicitado);

if ($stmt_insert->execute()) {
    // Sucesso! Define uma mensagem de sucesso
    $_SESSION['success_message'] = "Pedido de conexão enviado com sucesso!";
} else {
    // Erro
    $_SESSION['error_message'] = "Erro ao enviar o pedido. Tente novamente.";
}

$stmt_insert->close();
$conn->close();

// 8. Redireciona de volta para a página principal de "Meus Dates"
// A página meus_dates.php já está preparada para mostrar essas mensagens de sessão.
header('Location: ../php/meus_dates.php');
exit();
?>