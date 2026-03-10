<?php
session_start();
include('../conexao.php');

// 1. VERIFICAÇÃO DE SEGURANÇA
if (!isset($_SESSION['usuario']) || ($_SESSION['tipo_conta'] !== 'empresarial' && $_SESSION['tipo_conta'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];

// Variáveis Iniciais
$id_local = null;
$nome_local = "Empresa";
$status_local = "nenhum"; // Estados possíveis: 'ativo', 'pendente', 'nenhum'
$total_favoritos = 0;
$total_visualizacoes = 0;
$total_agendamentos = 0;

if ($conn) {
    // ---------------------------------------------------------
    // PASSO 1: Verificar se já existe um local ATIVO vinculado
    // ---------------------------------------------------------
    $sql_user = "SELECT id_local_associado FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($sql_user);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['id_local_associado'])) {
            $id_local = $row['id_local_associado'];
            $status_local = 'ativo';
        }
    }
    $stmt->close();

    // ---------------------------------------------------------
    // PASSO 2: Se não achou ativo, procura nos PENDENTES
    // (Isso resolve o problema de aparecer que não tem local)
    // ---------------------------------------------------------
    if ($status_local === 'nenhum') {
        // Busca na tabela locais_pendentes pelo ID do usuário
        // Nota: Assumindo que a coluna na tabela locais_pendentes é 'id_usuario'
        $sql_pendente = "SELECT titulo FROM locais_pendentes WHERE id_usuario = ? LIMIT 1";
        
        if ($stmt_pen = $conn->prepare($sql_pendente)) {
            $stmt_pen->bind_param("i", $user_id);
            $stmt_pen->execute();
            $res_pen = $stmt_pen->get_result();
            
            if ($row_pen = $res_pen->fetch_assoc()) {
                $nome_local = $row_pen['titulo'];
                $status_local = 'pendente';
            }
            $stmt_pen->close();
        }
    }

    // ---------------------------------------------------------
    // PASSO 3: BUSCAR ESTATÍSTICAS (Apenas se estiver ATIVO)
    // ---------------------------------------------------------
    if ($status_local === 'ativo' && $id_local) {
        // Nome e Visualizações
        $sql_dados = "SELECT titulo, visualizacoes_total FROM locais WHERE id_local = ?";
        $stmt = $conn->prepare($sql_dados);
        $stmt->bind_param("i", $id_local);
        $stmt->execute();
        $res_dados = $stmt->get_result();
        if ($r = $res_dados->fetch_assoc()) {
            $nome_local = $r['titulo'];
            $total_visualizacoes = $r['visualizacoes_total'];
        }
        $stmt->close();

        // Favoritos
        $sql_fav = "SELECT COUNT(*) as total FROM favoritos WHERE place_id = ?";
        $stmt = $conn->prepare($sql_fav);
        $stmt->bind_param("s", $id_local); // place_id geralmente é string/varchar
        $stmt->execute();
        $total_favoritos = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        // Agendamentos Pendentes
        $sql_agend = "SELECT COUNT(*) as total FROM agendamentos WHERE id_local = ? AND status = 'pendente'";
        $stmt = $conn->prepare($sql_agend);
        $stmt->bind_param("i", $id_local);
        $stmt->execute();
        $total_agendamentos = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo htmlspecialchars($nome_local); ?></title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Estilos extras para os avisos */
        .status-box {
            padding: 30px;
            border-radius: 8px;
            text-align: center;
            margin-top: 20px;
            background: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        }
        .status-box i { font-size: 50px; margin-bottom: 20px; display: block; }
        
        .box-warning { border-left: 5px solid #ffc107; }
        .box-warning i { color: #ffc107; }
        
        .box-danger { border-left: 5px solid #dc3545; }
        .box-danger i { color: #dc3545; }

        .sidebar-badge {
            background: #444; 
            color: #fff; 
            padding: 2px 8px; 
            border-radius: 10px; 
            font-size: 10px; 
            margin-left: 5px;
            text-transform: uppercase;
        }
        .badge-active { background: #28a745; }
        .badge-pending { background: #ffc107; color: #333; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>
                    <?php echo htmlspecialchars($nome_local); ?>
                </h3>
                <div style="margin-top:5px;">
                    <?php if($status_local == 'ativo'): ?>
                        <span class="sidebar-badge badge-active">Ativo</span>
                    <?php elseif($status_local == 'pendente'): ?>
                        <span class="sidebar-badge badge-pending">Em Análise</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <?php if ($status_local === 'ativo'): ?>
                        <li><a href="#" class="active"><i class="fas fa-chart-line"></i> <span>Visão Geral</span></a></li>
                        <li><a href="gerenciar_agendamentos.php"><i class="fas fa-calendar-check"></i> <span>Agendamentos</span></a></li>
                        <li><a href="editar_meu_local.php"><i class="fas fa-edit"></i> <span>Editar Local</span></a></li>
                    <?php else: ?>
                        <li><a href="#" class="active" style="cursor: default;"><i class="fas fa-lock"></i> <span>Aguardando Aprovação</span></a></li>
                    <?php endif; ?>
                    
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            
            <?php if ($status_local === 'pendente'): ?>
                <header class="main-header">
                    <h1>Cadastro Recebido</h1>
                </header>
                <div class="status-box box-warning">
                    <i class="fas fa-clock"></i>
                    <h2>Seu local está em análise!</h2>
                    <p>Olá! Recebemos o cadastro do <strong><?php echo htmlspecialchars($nome_local); ?></strong>.</p>
                    <p>Nossa equipe administrativa irá revisar as informações. Assim que for aprovado, este painel será liberado automaticamente.</p>
                </div>

            <?php elseif ($status_local === 'nenhum'): ?>
                <header class="main-header">
                    <h1>Atenção</h1>
                </header>
                <div class="status-box box-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <h2>Nenhum Local Vinculado</h2>
                    <p>Não encontramos nenhum estabelecimento (nem ativo, nem pendente) nesta conta.</p>
                    <a href="adicionar-lugar.php" class="btn-login" style="display:inline-block; margin-top:15px; background:#e94d65; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;">Cadastrar Agora</a>
                </div>

            <?php else: ?>
                <header class="main-header">
                    <h1>Relatório de Engajamento</h1>
                </header>

                <div class="cards-container">
                    <div class="card report-card">
                        <h4>Total de Favoritos</h4>
                        <p class="favorite-count"><?php echo $total_favoritos; ?></p>
                        <p class="card-description"><i class="fas fa-heart" style="color:red;"></i> Usuários amaram seu local.</p>
                    </div>

                    <div class="card report-card">
                        <h4>Visualizações</h4>
                        <p class="favorite-count"><?php echo $total_visualizacoes; ?></p>
                        <p class="card-description"><i class="fas fa-eye" style="color:blue;"></i> Acessos à sua página.</p>
                    </div>

                    <div class="card report-card" style="border-left: 5px solid <?php echo ($total_agendamentos > 0 ? '#ff9800' : '#4caf50'); ?>;">
                        <h4>Agendamentos Pendentes</h4>
                        <p class="favorite-count"><?php echo $total_agendamentos; ?></p>
                        <p class="card-description">
                            <a href="gerenciar_agendamentos.php" style="text-decoration:underline;">Gerenciar solicitações</a>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

        </main>
    </div>
</body>
</html>