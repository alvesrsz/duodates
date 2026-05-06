<?php
// Arquivo: reprovar_empresa.php
session_start();
include '../conexao.php';

// VERIFICAÇÃO DE SEGURANÇA: Garante que apenas o admin possa executar esta ação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Verifica se um ID foi passado pela URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_empresa_reprovar = $_GET['id'];

    // Inicia uma transação
    $conn->begin_transaction();

    try {
        // 1. DELETA o local pendente associado
        $stmt_delete_local = $conn->prepare("DELETE FROM locais_pendentes WHERE id_usuario = ?");
        $stmt_delete_local->bind_param("i", $id_empresa_reprovar);
        $stmt_delete_local->execute();
        $stmt_delete_local->close();

        // 2. DELETA o usuário/empresa pendente
        // Alternativa: em vez de deletar, você poderia atualizar o status para 'reprovado'
        // $stmt_update_user = $conn->prepare("UPDATE usuarios SET status_aprovacao = 'reprovado' WHERE id = ?");
        $stmt_delete_user = $conn->prepare("DELETE FROM usuarios WHERE id = ? AND status_aprovacao = 'pendente'");
        $stmt_delete_user->bind_param("i", $id_empresa_reprovar);
        $stmt_delete_user->execute();
        $stmt_delete_user->close();
        
        // Se tudo deu certo, confirma a transação
        $conn->commit();

    } catch (Exception $e) {
        // Se algo deu errado, desfaz tudo
        $conn->rollback();
        // header('Location: ../php/admin.php?msg=erro_reprovacao');
        // exit();
        die("Erro ao reprovar a empresa: " . $e->getMessage());
    }

    $conn->close();

    // Redireciona de volta para o painel do admin
    header('Location: ../php/admin.php?msg=reprovacao_sucesso');
    exit();

} else {
    // Se nenhum ID foi fornecido, apenas redireciona
    header('Location: ../php/admin.php');
    exit();
}
?>