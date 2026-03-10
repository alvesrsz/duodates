<?php
session_start();
include '../conexao.php';

// Verificação de segurança (só admin pode acessar)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Query para buscar todos os agendamentos, juntando com as tabelas de usuários e locais
// para obter os nomes em vez de apenas os IDs.
$sql_agendamentos = "SELECT 
                        ag.data_agendada,
                        ag.titulo_evento,
                        u.nome as nome_usuario,
                        l.titulo as nome_local,
                        ag.criado_em
                     FROM agendamentos ag
                     JOIN usuarios u ON ag.id_usuario = u.id
                     JOIN locais l ON ag.id_local = l.id_local
                     ORDER BY ag.data_agendada DESC";

$resultado_agendamentos = $conn->query($sql_agendamentos);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Agendamentos - DuoDates Admin</title>
    <link rel="stylesheet" href="../CSS/admin.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DuoDates Admin</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="../php/admin.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../php/gerenciar_usuarios.php" class="nav-item"><i class="fas fa-users"></i> Gerenciar Usuários</a>
                <a href="../php/gerenciar_locais.php" class="nav-item"><i class="fas fa-map-marker-alt"></i> Gerenciar Locais</a>
                <a href="../php/ver_agendamentos.php" class="nav-item active"><i class="fas fa-calendar-alt"></i> Ver Agendamentos</a>
                <a href="../index.php" class="nav-item"><i class="fas fa-globe"></i> Ver Site</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h3>Todos os Agendamentos de Dates</h3>
            </header>

            <div class="panel full-width">
                <h4>Agendamentos Registrados no Sistema</h4>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data do Evento</th>
                            <th>Título do Evento</th>
                            <th>Usuário</th>
                            <th>Local Agendado</th>
                            <th>Data do Agendamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado_agendamentos && $resultado_agendamentos->num_rows > 0): ?>
                            <?php while($agendamento = $resultado_agendamentos->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($agendamento['data_agendada'])); ?></td>
                                    <td><?php echo htmlspecialchars($agendamento['titulo_evento']); ?></td>
                                    <td><?php echo htmlspecialchars($agendamento['nome_usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($agendamento['nome_local']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($agendamento['criado_em'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">Nenhum agendamento encontrado no sistema.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>