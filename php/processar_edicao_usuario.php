<?php
session_start();
include '../conexao.php';

// Apenas admins logados podem processar esta ação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    // Se não for admin, encerra o script.
    die("Acesso não autorizado.");
}

// Verifica se os dados foram enviados via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e limpa os dados do formulário
    $id = $_POST['id'];
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $tipo_conta = $_POST['tipo_conta'];

    // Validação básica
    if (empty($id) || empty($nome) || empty($email) || empty($tipo_conta)) {
        $_SESSION['error_message'] = "Todos os campos são obrigatórios.";
        header('Location: ../php/editar_usuario.php?id=' . $id);
        exit();
    }

    // Prepara a consulta SQL para ATUALIZAR (UPDATE) o usuário
    $sql = "UPDATE usuarios SET nome = ?, email = ?, tipo_conta = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    // Associa os parâmetros (s = string, i = integer)
    $stmt->bind_param("sssi", $nome, $email, $tipo_conta, $id);

    // Executa e verifica se deu certo
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
    } else {
        $_SESSION['error_message'] = "Erro ao atualizar o usuário: " . $conn->error;
    }

    $stmt->close();
    $conn->close();

    // Redireciona de volta para a lista de usuários
    header('Location: ../php/gerenciar_usuarios.php');
    exit();

} else {
    // Se alguém tentar acessar o arquivo diretamente, redireciona
    header('Location: ../php/gerenciar_usuarios.php');
    exit();
}
?>