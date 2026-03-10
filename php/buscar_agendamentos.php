<?php
session_start();
include '../conexao.php';

// Proteção: Apenas usuários logados podem buscar seus agendamentos
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION['user_id'];
$eventos = [];

// Busca os agendamentos do usuário logado
$sql = "SELECT titulo_evento, data_agendada FROM agendamentos WHERE id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Formata os dados para o padrão que o FullCalendar espera
    $eventos[] = [
        'title' => $row['titulo_evento'],
        'start' => $row['data_agendada'] // Formato 'YYYY-MM-DD HH:MM:SS'
    ];
}

$stmt->close();
$conn->close();

// Define o cabeçalho como JSON e envia os eventos
header('Content-Type: application/json');
echo json_encode($eventos);
?>