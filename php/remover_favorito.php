<?php
// remover_favorito.php

session_start();
include('../conexao.php');

// Responde em formato JSON
header('Content-Type: application/json');

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Pega os dados enviados pelo JavaScript
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['status' => 'error', 'message' => 'Nenhum dado recebido.']);
    exit();
}

// Lógica para remover TODOS os favoritos
if (isset($data['action']) && $data['action'] === 'delete_all') {
    $sql = "DELETE FROM favoritos WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Falha ao remover todos os favoritos.']);
    }

// Lógica para remover um ÚNICO favorito
} elseif (isset($data['place_id'])) {
    $place_id = $data['place_id'];
    $sql = "DELETE FROM favoritos WHERE user_id = ? AND place_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $place_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'removed_id' => $place_id]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Falha ao remover o favorito.']);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Ação inválida.']);
}

$stmt->close();
$conn->close();
?>