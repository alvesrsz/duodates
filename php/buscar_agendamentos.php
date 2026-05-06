<?php
session_start();
include '../conexao.php';

<<<<<<< HEAD
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
=======
// Proteção: Apenas usuários logados podem buscar seus agendamentos
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Proibido
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION['user_id'];
$eventos = [];

<<<<<<< HEAD
$sql = "SELECT titulo_evento, data_agendada, STATUS FROM agendamentos WHERE id_usuario = ?";
=======
// Busca os agendamentos do usuário logado
$sql = "SELECT titulo_evento, data_agendada FROM agendamentos WHERE id_usuario = ?";
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
<<<<<<< HEAD
    $eventos[] = [
        'title' => $row['titulo_evento'] . ' (' . ucfirst($row['STATUS']) . ')',
        'start' => $row['data_agendada']
=======
    // Formata os dados para o padrão que o FullCalendar espera
    $eventos[] = [
        'title' => $row['titulo_evento'],
        'start' => $row['data_agendada'] // Formato 'YYYY-MM-DD HH:MM:SS'
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
    ];
}

$stmt->close();
$conn->close();

<<<<<<< HEAD
=======
// Define o cabeçalho como JSON e envia os eventos
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
header('Content-Type: application/json');
echo json_encode($eventos);
?>