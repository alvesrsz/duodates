<?php
session_start();
include '../conexao.php';

// Define o cabeçalho da resposta como JSON para comunicação com o JavaScript
header('Content-Type: application/json');

// --- VERIFICAÇÕES DE SEGURANÇA E VALIDAÇÃO ---

// 1. Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Código de "Não Autorizado"
    echo json_encode(['status' => 'error', 'message' => 'Você precisa estar logado para agendar um date.']);
    exit();
}

// 2. Recebe e decodifica os dados JSON enviados pelo formulário
$data = json_decode(file_get_contents('php://input'), true);

// 3. Valida os dados recebidos para garantir que não estão vazios
if (
    !isset($data['id_local']) || empty($data['id_local']) ||
    !isset($data['titulo_evento']) || empty($data['titulo_evento']) ||
    !isset($data['data_agendada']) || empty($data['data_agendada'])
) {
    http_response_code(400); // Código de "Requisição Inválida"
    echo json_encode(['status' => 'error', 'message' => 'Todos os campos obrigatórios devem ser preenchidos.']);
    exit();
}

// --- LÓGICA DE INSERÇÃO NO BANCO DE DADOS ---
try {
    $id_usuario = $_SESSION['user_id'];
    $id_local = $data['id_local'];
    $titulo_evento = $data['titulo_evento'];
    $data_agendada = $data['data_agendada'];
    $notas = $data['notas'] ?? null; // Usa null se as notas estiverem vazias

    // Prepara a consulta SQL para inserir o novo agendamento
    $sql = "INSERT INTO agendamentos (id_usuario, id_local, data_agendada, titulo_evento, notas) VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Erro ao preparar a consulta: ' . $conn->error);
    }
    
    // Associa os parâmetros com os tipos de dados corretos (i = integer, s = string)
    $stmt->bind_param("iisss", $id_usuario, $id_local, $data_agendada, $titulo_evento, $notas);
    
    // Executa a consulta
    if ($stmt->execute()) {
        // Se a execução for bem-sucedida, envia uma resposta de sucesso
        echo json_encode(['status' => 'success', 'message' => 'Date agendado com sucesso!']);
    } else {
        // Se falhar, lança uma exceção
        throw new Exception('Erro ao salvar o agendamento no banco de dados.');
    }

    $stmt->close();

} catch (Exception $e) {
    // Se ocorrer qualquer erro no processo, envia uma resposta de erro genérica
    http_response_code(500); // Erro Interno do Servidor
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>