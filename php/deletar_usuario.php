<?php
session_start();
include '../conexao.php';
header('Content-Type: application/json');

// --- VERIFICAÇÃO DE SEGURANÇA COMPLETA ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    http_response_code(403); // Proibido
    echo json_encode(['status' => 'error', 'message' => 'Acesso não autorizado.']);
    exit();
}
// ... resto do código para deletar

// Recebe o ID do usuário a ser deletado
$data = json_decode(file_get_contents('php://input'), true);
$id_para_deletar = $data['user_id'] ?? null;

// Validação
if (!$id_para_deletar) {
    http_response_code(400); // Requisição inválida
    echo json_encode(['status' => 'error', 'message' => 'ID do usuário não fornecido.']);
    exit();
}

// --- IMPEDE QUE O ADMIN DELETE A PRÓPRIA CONTA ---
if ($id_para_deletar == $_SESSION['user_id']) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Você não pode deletar sua própria conta.']);
    exit();
}

// --- EXECUTA A EXCLUSÃO ---
try {
    $sql = "DELETE FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_para_deletar);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Usuário deletado com sucesso.']);
    } else {
        throw new Exception('Não foi possível deletar o usuário.');
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500); // Erro de servidor
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>