<?php
// HABILITA EXIBIÇÃO DE ERROS
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'conexao.php';

// 1. VERIFICA SE O USUÁRIO ESTÁ LOGADO E SE O MÉTODO É POST
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$id_usuario_logado = $_SESSION['user_id'];

// 2. PEGA A LISTA DE PREFERÊNCIAS ENVIADA PELO FORMULÁRIO
// O '?? []' garante que, se o usuário desmarcar tudo, teremos um array vazio em vez de um erro.
$novas_preferencias_ids = $_POST['preferencias'] ?? [];

// Inicia uma transação para garantir que ambas as operações (apagar e inserir) funcionem juntas
$conn->begin_transaction();

try {
    // 3. APAGA TODAS AS PREFERÊNCIAS ANTIGAS DO USUÁRIO
    $sql_delete = "DELETE FROM usuario_preferencias WHERE id_usuario = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $id_usuario_logado);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 4. INSERE AS NOVAS PREFERÊNCIAS, SE HOUVER ALGUMA
    if (!empty($novas_preferencias_ids)) {
        // Prepara a query de inserção uma única vez para ser mais eficiente
        $sql_insert = "INSERT INTO usuario_preferencias (id_usuario, id_preferencia) VALUES (?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        // Loop para inserir cada nova preferência
        foreach ($novas_preferencias_ids as $id_preferencia) {
            $stmt_insert->bind_param("ii", $id_usuario_logado, $id_preferencia);
            $stmt_insert->execute();
        }
        $stmt_insert->close();
    }

    // Se tudo deu certo, confirma as alterações no banco
    $conn->commit();
    $_SESSION['success_message'] = "Preferências salvas com sucesso!";

} catch (Exception $e) {
    // Se algo deu errado, desfaz todas as alterações
    $conn->rollback();
    $_SESSION['error_message'] = "Ocorreu um erro ao salvar suas preferências: " . $e->getMessage();
}

// 5. REDIRECIONA DE VOLTA PARA A PÁGINA DE PERFIL
$conn->close();
header('Location: perfil.php');
exit();
?>