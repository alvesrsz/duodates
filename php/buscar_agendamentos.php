<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

// Configura o charset para evitar que acentos quebrem o JSON do calendário
$conn->set_charset("utf8mb4");

$user_id = $_SESSION['user_id'];
$eventos = [];

// Recupera tanto os lembretes quanto os agendamentos com locais vinculados
$sql = "SELECT ag.titulo_evento, ag.data_agendada, l.titulo as nome_local 
        FROM agendamentos ag 
        LEFT JOIN locais l ON ag.id_local = l.id_local 
        WHERE ag.id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $titulo_completo = $row['titulo_evento'];
    if (!empty($row['nome_local'])) {
        $titulo_completo .= ' - ' . $row['nome_local'];
    }
    
    $eventos[] = [
        'title' => $titulo_completo,
        'start' => $row['data_agendada']
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json; charset=utf-8');
echo json_encode($eventos, JSON_UNESCAPED_UNICODE);
?>