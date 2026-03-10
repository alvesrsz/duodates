<?php
include '../php/reservaconexao.php'; // Caminho da conexão

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Recebe o ID DO LOCAL (vindo da tabela locais)
    $lugar_id = $_POST['lugar_id'];
    $data = $_POST['data_date'];
    $hora = $_POST['hora_date'];
    $notas = $_POST['notas'];

    // Insere na tabela de RESERVAS
    // Atenção: A tabela 'reservas' precisa existir no seu banco!
    $stmt = $conn->prepare("INSERT INTO reservas (lugar_id, data_reserva, hora_reserva, notas) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $lugar_id, $data, $hora, $notas);

    if ($stmt->execute()) {
        echo "
        <!DOCTYPE html>
        <html lang='pt-br'>
        <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <link href='https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600&display=swap' rel='stylesheet'>
        </head>
        <body style='background-color: #F9F4EF; font-family: Montserrat, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0;'>
            <div style='background: white; padding: 50px; border-radius: 15px; box-shadow: 0 10px 30px rgba(125, 29, 44, 0.1); text-align: center; max-width: 400px;'>
                <div style='font-size: 50px; margin-bottom: 20px;'>✨</div>
                <h1 style='color: #7D1D2C; font-family: Playfair Display, serif; margin: 0 0 10px 0;'>Reserva Confirmada!</h1>
                <p style='color: #5A5A5A; margin-bottom: 30px;'>Seu date foi agendado com sucesso.</p>
                <a href='nova_reserva.php' style='background-color: #7D1D2C; color: white; text-decoration: none; padding: 15px 30px; border-radius: 30px; font-weight: bold; text-transform: uppercase; font-size: 14px;'>Agendar Outro</a>
            </div>
        </body>
        </html>";
    } else {
        echo "Erro ao salvar: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    header("Location: nova_reserva.php");
    exit();
}
?>