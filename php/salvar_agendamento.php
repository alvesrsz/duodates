<?php
session_start();
include '../conexao.php';

header('Content-Type: application/json');

// 1. Verifica login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Você precisa estar logado.']);
    exit();
}

// 2. Recebe dados JSON
$data = json_decode(file_get_contents('php://input'), true);

// 3. Validação
if (
    !isset($data['id_local']) || empty($data['id_local']) ||
    !isset($data['titulo_evento']) || empty($data['titulo_evento']) ||
    !isset($data['data_agendada']) || empty($data['data_agendada'])
) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Preencha todos os campos obrigatórios.']);
    exit();
}

try {
    $id_usuario = $_SESSION['user_id'];
    $id_local = $data['id_local'];
    $titulo_evento = $data['titulo_evento'];
    $data_agendada = $data['data_agendada'];
    $notas = $data['notas'] ?? null;

    // 🔥 INSERT COM STATUS
    $sql = "INSERT INTO agendamentos 
            (id_usuario, id_local, data_agendada, titulo_evento, notas, STATUS) 
            VALUES (?, ?, ?, ?, ?, 'pendente')";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception('Erro ao preparar: ' . $conn->error);
    }

    $stmt->bind_param("iisss", $id_usuario, $id_local, $data_agendada, $titulo_evento, $notas);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Date agendado com sucesso!']);
    } else {
        throw new Exception('Erro ao salvar.');
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>