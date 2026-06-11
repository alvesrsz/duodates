<?php
session_start();
include '../conexao.php'; // Inclui a conexão com o banco

// Define que a resposta será em JSON
header('Content-Type: application/json');

// 1. Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Usuário não está logado.'
    ]);
    exit();
}
$user_id = $_SESSION['user_id'];

// 2. Obter os dados enviados pelo JavaScript
// O 'fetch' envia um JSON, então precisamos ler o 'input'
$data = json_decode(file_get_contents('php://input'), true);

// 3. Validar os dados recebidos
if (!$data || !isset($data['place_id']) || !isset($data['is_favorited'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Dados incompletos.'
    ]);
    exit();
}

$place_id = $data['place_id']; // Este é o 'slug' do local
$is_favorited = $data['is_favorited']; // Isso será true (para salvar) ou false (para remover)

// 4. Executar a ação no Banco de Dados
try {
    
    if ($is_favorited) {
        // Se is_favorited for TRUE, o usuário clicou para ADICIONAR
        // (Verifica antes se já não existe, para evitar duplicatas)
        $sql = "INSERT INTO favoritos (user_id, place_id) 
                SELECT ?, ? 
                WHERE NOT EXISTS (
                    SELECT 1 FROM favoritos WHERE user_id = ? AND place_id = ?
                )";
        $stmt = $conn->prepare($sql);
        // "is" = integer (user_id), string (place_id)
        $stmt->bind_param("isis", $user_id, $place_id, $user_id, $place_id);

    } else {
        // Se is_favorited for FALSE, o usuário clicou para REMOVER
        $sql = "DELETE FROM favoritos WHERE user_id = ? AND place_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $place_id);
    }

    // 5. Executar e enviar resposta
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao executar a query: ' . $stmt->error
        ]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    // Captura qualquer erro (ex: falha na conexão, tabela não existe)
    error_log($e->getMessage()); // Salva o erro no log do servidor
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro interno do servidor.'
    ]);
}

$conn->close();
?>