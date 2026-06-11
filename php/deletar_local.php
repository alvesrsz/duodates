<?php
session_start();
include '../conexao.php';
header('Content-Type: application/json');

// --- VERIFICAÇÃO DE SEGURANÇA COMPLETA ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado.']);
    exit();
}
// ... resto do código para deletar
// OBS: No futuro, reativar a verificação de admin: || $_SESSION['tipo_conta'] !== 'admin'

// Recebe o ID do local a ser deletado
$data = json_decode(file_get_contents('php://input'), true);
$id_para_deletar = $data['local_id'] ?? null;

if (!$id_para_deletar) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['status' => 'error', 'message' => 'ID do local não fornecido.']);
    exit();
}

// --- EXECUTA A EXCLUSÃO USANDO TRANSAÇÃO PARA SEGURANÇA ---
$conn->begin_transaction();

try {
    // 1. Deleta os favoritos associados a este local
    $sql_favoritos = "DELETE FROM favoritos WHERE place_id IN (SELECT slug FROM locais WHERE id_local = ?)";
    $stmt_favoritos = $conn->prepare($sql_favoritos);
    $stmt_favoritos->bind_param("i", $id_para_deletar);
    $stmt_favoritos->execute();
    $stmt_favoritos->close();

    // 2. Deleta os agendamentos associados a este local
    $sql_agendamentos = "DELETE FROM agendamentos WHERE id_local = ?";
    $stmt_agendamentos = $conn->prepare($sql_agendamentos);
    $stmt_agendamentos->bind_param("i", $id_para_deletar);
    $stmt_agendamentos->execute();
    $stmt_agendamentos->close();

    // 3. Finalmente, deleta o local
    $sql_locais = "DELETE FROM locais WHERE id_local = ?";
    $stmt_locais = $conn->prepare($sql_locais);
    $stmt_locais->bind_param("i", $id_para_deletar);
    $stmt_locais->execute();
    
    // Se a execução chegou até aqui, todas as exclusões foram bem-sucedidas
    if ($stmt_locais->affected_rows > 0) {
        $conn->commit(); // Efetiva as alterações no banco
        echo json_encode(['status' => 'success', 'message' => 'Local e todos os seus dados associados foram deletados.']);
    } else {
        throw new Exception('Local não encontrado ou já deletado.');
    }
    $stmt_locais->close();

} catch (Exception $e) {
    $conn->rollback(); // Desfaz todas as alterações em caso de erro
    http_response_code(500); // Erro de servidor
    echo json_encode(['status' => 'error', 'message' => 'Erro ao deletar o local: ' . $e->getMessage()]);
}

$conn->close();
?>