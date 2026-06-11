<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (file_exists('conexao.php')) { include 'conexao.php'; } 
elseif (file_exists('../conexao.php')) { include '../conexao.php'; }

if (!isset($conn) && isset($conexao)) { $conn = $conexao; }
if (!isset($_SESSION['user_id'])) { die("Erro: Usuário não logado."); }
$empresa_id = $_SESSION['user_id'];

$id_local = 0;
$lista_agendamentos = [];

// 1. Obtém o id_local_associado
$query_user = "SELECT id_local_associado FROM usuarios WHERE id = '$empresa_id' LIMIT 1";
$res_user = $conn->query($query_user);
if ($res_user && $res_user->num_rows > 0) {
    $id_local = (int)$res_user->fetch_assoc()['id_local_associado'];
}

// 2. Carrega todos os agendamentos reais do local
if ($id_local > 0) {
    $query_lista = "SELECT a.data_agendada, a.STATUS, a.titulo_evento, u.nome as cliente, u.email as cliente_email 
                    FROM agendamentos a 
                    INNER JOIN usuarios u ON a.id_usuario = u.id 
                    WHERE a.id_local = '$id_local' 
                    ORDER BY a.data_agendada DESC";
                    
    $resultado_lista = $conn->query($query_lista);
    if ($resultado_lista && $resultado_lista->num_rows > 0) {
        while ($row = $resultado_lista->fetch_assoc()) {
            $timestamp = strtotime($row['data_agendada']);
            $lista_agendamentos[] = [
                'cliente' => !empty($row['cliente']) ? $row['cliente'] : 'Cliente Oculto',
                'email'   => $row['cliente_email'],
                'evento'  => !empty($row['titulo_evento']) ? $row['titulo_evento'] : 'Date',
                'data'    => date('d/m/Y', $timestamp),
                'hora'    => date('H:i', $timestamp),
                'status'  => strtolower($row['STATUS'])
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DuoDates - Todos os Agendamentos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: #F4F7F6; color: #333; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #8C1C30; color: white; display: flex; flex-direction: column; padding-top: 20px; }
        .sidebar h2 { text-align: center; margin-bottom: 30px; font-weight: 800; font-size: 24px; }
        .sidebar a { color: #f0f0f0; text-decoration: none; padding: 15px 20px; display: block; font-size: 15px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: rgba(255,255,255,0.1); border-left: 4px solid #fff; }
        .sidebar a i { width: 25px; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 24px; color: #2c3e50; }
        .panel { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .panel h3 { font-size: 16px; color: #555; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        th { color: #888; font-weight: 600; }
        .status { padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: capitalize; display: inline-block; }
        .status.confirmado { background: #d1fae5; color: #065f46; }
        .status.pendente { background: #fef3c7; color: #92400e; }
        .status.cancelado { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>DuoDates<br><span style="font-size:14px; font-weight:normal;">Painel do Parceiro</span></h2>
        <a href="dashboard_empresa.php"><i class="fas fa-chart-line"></i> Visão Geral</a>
        <a href="meu_estabelecimento.php"><i class="fas fa-store"></i> Meu Estabelecimento</a>
        <a href="agendamentos.php" class="active"><i class="fas fa-calendar-check"></i> Agendamentos</a>
        <a href="avaliacoes.php"><i class="fas fa-star"></i> Avaliações</a>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Gerenciamento de Agendamentos</h1>
        </div>
        <div class="panel">
            <h3>Histórico de Reservas</h3>
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Data / Hora</th>
                        <th>Evento / Atividade</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($lista_agendamentos)): ?>
                        <tr><td colspan="4" style="text-align:center; color:#888;">Nenhum agendamento encontrado para o seu estabelecimento.</td></tr>
                    <?php else: ?>
                        <?php foreach($lista_agendamentos as $agendamento): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($agendamento['cliente']); ?></strong><br><small style="color:#888;"><?php echo htmlspecialchars($agendamento['email']); ?></small></td>
                            <td><?php echo $agendamento['data']; ?> às <?php echo $agendamento['hora']; ?></td>
                            <td><?php echo htmlspecialchars($agendamento['evento']); ?></td>
                            <td><span class="status <?php echo $agendamento['status']; ?>"><?php echo $agendamento['status']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>