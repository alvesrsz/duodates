<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([]);
    exit();
}

$user_id = $_SESSION['user_id'];
$eventos = [];

$sql = "SELECT titulo_evento, data_agendada, STATUS FROM agendamentos WHERE id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $eventos[] = [
        'title' => $row['titulo_evento'] . ' (' . ucfirst($row['STATUS']) . ')',
        'start' => $row['data_agendada']
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($eventos);
?>