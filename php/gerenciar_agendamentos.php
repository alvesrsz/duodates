<?php
session_start();
include('../conexao.php');

// 1. VERIFICAÇÃO DE SEGURANÇA
// Se não estiver logado, manda pro login
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];
$id_local = null;

// 2. DESCOBRE QUAL O LOCAL DESTA EMPRESA
$stmt = $conn->prepare("SELECT id_local_associado FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $id_local = $row['id_local_associado'];
}
$stmt->close();

// Se a empresa não tiver local, para aqui.
if (!$id_local) {
    die("Erro: Sua conta empresarial ainda não está vinculada a nenhum local.");
}

// 3. PROCESSAR AÇÃO (QUANDO CLICA EM ACEITAR OU RECUSAR)
if (isset($_GET['acao']) && isset($_GET['id_agend'])) {
    $novo_status = ($_GET['acao'] == 'aceitar') ? 'aprovado' : 'recusado';
    $id_agend = $_GET['id_agend'];
    
    // Atualiza apenas se o agendamento pertencer ao local da empresa logada
    $stmt_up = $conn->prepare("UPDATE agendamentos SET STATUS = ? WHERE id_agendamento = ? AND id_local = ?");
    $stmt_up->bind_param("sii", $novo_status, $id_agend, $id_local);
    $stmt_up->execute();
    $stmt_up->close();
    
    // Recarrega a página para limpar a URL e atualizar a lista
    header("Location: gerenciar_agendamentos.php"); 
    exit();
}

// 4. BUSCAR A LISTA DE AGENDAMENTOS DO BANCO
// Trazemos dados da tabela 'agendamentos' e o nome/email da tabela 'usuarios'
$sql_lista = "SELECT a.*, u.nome as nome_cliente, u.email 
              FROM agendamentos a 
              JOIN usuarios u ON a.id_usuario = u.id 
              WHERE a.id_local = ? 
              ORDER BY a.data_agendada DESC";

$stmt_lista = $conn->prepare($sql_lista);
$stmt_lista->bind_param("i", $id_local);
$stmt_lista->execute();
$result_lista = $stmt_lista->get_result();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Agendamentos - DuoDates</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* CSS Específico para esta tabela */
        .table-container { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
            overflow-x: auto;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        th, td { 
            padding: 15px; 
            text-align: left; 
            border-bottom: 1px solid #eee; 
        }
        th { 
            background-color: #f8f9fa; 
            color: #555; 
            font-weight: 600;
        }
        tr:hover { background-color: #fcfcfc; }
        
        /* Cores dos Status */
        .status-pendente { color: #ff9800; font-weight: bold; background: #fff3e0; padding: 5px 10px; border-radius: 20px; font-size: 0.85em; }
        .status-aprovado { color: #28a745; font-weight: bold; background: #d4edda; padding: 5px 10px; border-radius: 20px; font-size: 0.85em; }
        .status-recusado { color: #dc3545; font-weight: bold; background: #f8d7da; padding: 5px 10px; border-radius: 20px; font-size: 0.85em; }

        /* Botões de Ação */
        .btn-action { 
            display: inline-flex; 
            align-items: center; 
            justify-content: center;
            width: 35px; 
            height: 35px; 
            border-radius: 50%; 
            text-decoration: none; 
            color: white; 
            margin-right: 5px; 
            transition: transform 0.2s;
        }
        .btn-action:hover { transform: scale(1.1); }
        .btn-accept { background-color: #28a745; }
        .btn-reject { background-color: #dc3545; }
        
        .btn-back { 
            display: inline-block; 
            margin-bottom: 20px; 
            text-decoration: none; 
            color: #666; 
            font-weight: 500;
        }
        .btn-back:hover { color: #000; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Painel Empresa</h3>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li>
                        <a href="dashboard_empresa.php">
                            <i class="fas fa-chart-line"></i>
                            <span>Visão Geral</span>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="active"> <i class="fas fa-calendar-check"></i>
                            <span>Agendamentos</span>
                        </a>
                    </li>
                    <li>
                        <a href="editar_meu_local.php">
                            <i class="fas fa-edit"></i>
                            <span>Editar Local</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>Gestão de Agendamentos</h1>
            </header>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Data do Date</th>
                            <th>Cliente</th>
                            <th>Evento/Motivo</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result_lista->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <i class="far fa-clock"></i> 
                                <?php echo date('d/m/Y \à\s H:i', strtotime($row['data_agendada'])); ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['nome_cliente']); ?></strong><br>
                                <span style="font-size: 0.9em; color: #888;"><?php echo htmlspecialchars($row['email']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['titulo_evento']); ?></td>
                            <td>
                                <span class="status-<?php echo strtolower($row['STATUS']); ?>">
                                    <?php echo ucfirst($row['STATUS']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if(strtolower($row['STATUS']) == 'pendente'): ?>
                                    <a href="?acao=aceitar&id_agend=<?php echo $row['id_agendamento']; ?>" 
                                       class="btn-action btn-accept" 
                                       title="Aceitar Reserva"
                                       onclick="return confirm('Tem certeza que deseja ACEITAR este agendamento?');">
                                       <i class="fas fa-check"></i>
                                    </a>
                                    
                                    <a href="?acao=recusar&id_agend=<?php echo $row['id_agendamento']; ?>" 
                                       class="btn-action btn-reject" 
                                       title="Recusar Reserva"
                                       onclick="return confirm('Tem certeza que deseja RECUSAR este agendamento?');">
                                       <i class="fas fa-times"></i>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#aaa; font-size:0.9em;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php if($result_lista->num_rows == 0): ?>
                    <div style="text-align:center; padding: 40px; color: #777;">
                        <i class="far fa-calendar-times" style="font-size: 3em; margin-bottom: 10px; display:block;"></i>
                        Nenhum agendamento encontrado para o seu local.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>