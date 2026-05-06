<?php
include '../conexao.php';

$id = $_POST['id'];
$titulo = $_POST['titulo'];
$data = $_POST['data'];

$sql = "UPDATE agendamentos SET titulo_evento=?, data_agendada=? WHERE id_agendamento=?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $titulo, $data, $id);
$stmt->execute();

echo "ok";