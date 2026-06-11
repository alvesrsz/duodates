<?php
session_start();
include '../conexao.php';

// ===================================================================
// LÓGICA DE COMPATIBILIDADE
// ===================================================================

function fetchPreferences_Sistema1($user_id, $conn) {
    $sql = "SELECT pref_vibe, pref_food_ranking, pref_atividade, pref_comfort 
            FROM usuarios 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) { return null; }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return empty($data['pref_vibe']) ? null : $data;
}

function fetchPreferences_Sistema2($user_id, $id_conexao, $conn) {
    $sql = "SELECT id_questao_fk, GROUP_CONCAT(resposta_selecionada) as tags_string
            FROM respostas_usuario
            WHERE id_usuario_fk = ? AND id_conexao_fk = ?
            GROUP BY id_questao_fk";
    $stmt = $conn->prepare($sql);
     if (!$stmt) { return null; }
    $stmt->bind_param("ii", $user_id, $id_conexao);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while($row = $result->fetch_assoc()) {
        $data[$row['id_questao_fk']] = $row['tags_string'];
    }
    $stmt->close();
    return empty($data) ? null : $data;
}

function calcularCompatibilidade($user_id_A, $user_id_B, $id_conexao, $conn) {
    $prefsA_S1 = fetchPreferences_Sistema1($user_id_A, $conn);
    $prefsB_S1 = fetchPreferences_Sistema1($user_id_B, $conn);
    $prefsA_S2 = fetchPreferences_Sistema2($user_id_A, $id_conexao, $conn);
    $prefsB_S2 = fetchPreferences_Sistema2($user_id_B, $id_conexao, $conn);

    if ($prefsA_S1 === null || $prefsB_S1 === null || $prefsA_S2 === null || $prefsB_S2 === null) {
        return -1;
    }

    $score = 0;
    $total_secoes = 11;
    $max_score_per_section = 100 / $total_secoes;

    $calculateJaccard = function($prefA, $prefB, $is_comfort_section = false) use ($max_score_per_section) {
        if (empty($prefA) && empty($prefB)) {
            if ($is_comfort_section && $prefA === null && $prefB === null) {
                return $max_score_per_section;
            }
            return 0; 
        } 
        if (!empty($prefA) && !empty($prefB)) {
            $tagsA = array_filter(array_map('trim', explode(',', $prefA)));
            $tagsB = array_filter(array_map('trim', explode(',', $prefB)));
            $common = array_intersect($tagsA, $tagsB);
            $total = array_unique(array_merge($tagsA, $tagsB));
            if (count($total) > 0) {
                return (count($common) / count($total)) * $max_score_per_section;
            }
        }
        return 0; 
    };

    $score += $calculateJaccard($prefsA_S1['pref_vibe'], $prefsB_S1['pref_vibe']);
    $score += $calculateJaccard($prefsA_S1['pref_atividade'], $prefsB_S1['pref_atividade']);
    $score += $calculateJaccard($prefsA_S1['pref_comfort'], $prefsB_S1['pref_comfort'], true); 

    $foodA = json_decode($prefsA_S1['pref_food_ranking'], true) ?: [];
    $foodB = json_decode($prefsB_S1['pref_food_ranking'], true) ?: [];
    $commonFoodTags = array_intersect_key($foodA, $foodB);
    if (count($commonFoodTags) > 0) {
        $totalSimilarity = 0; $maxRankDiff = 7; 
        foreach (array_keys($commonFoodTags) as $tag) {
            $diff = abs($foodA[$tag] - $foodB[$tag]);
            $similarity = ($maxRankDiff - $diff) / $maxRankDiff; 
            $totalSimilarity += $similarity;
        }
        $score += ($totalSimilarity / count($commonFoodTags)) * $max_score_per_section; 
    }

    $questoes_S2 = [1, 2, 3, 5, 6, 7, 8]; 
    foreach ($questoes_S2 as $q_id) {
        $tagsA = $prefsA_S2[$q_id] ?? ''; 
        $tagsB = $prefsB_S2[$q_id] ?? '';
        $score += $calculateJaccard($tagsA, $tagsB);
    }
    
    return (int)round($score);
}

function checkUserCompletedS1($user_id, $conn) {
    $sql = "SELECT pref_vibe FROM usuarios WHERE id = ? AND pref_vibe IS NOT NULL LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function checkUserCompletedS2($user_id, $id_conexao, $conn) {
    $sql = "SELECT id_resposta FROM respostas_usuario WHERE id_usuario_fk = ? AND id_conexao_fk = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $id_conexao);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// ===================================================================
// FIM DAS FUNÇÕES PHP
// ===================================================================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$defaultPlaceholderUrl = "../images/perfil.png"; 

// --- BUSCAR DADOS DO USUÁRIO LOGADO ---
$sql_user = "SELECT nome, email, foto_perfil FROM usuarios WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$userName = $user_data['nome'] ?? "Usuário";
$userEmail = $user_data['email'] ?? "";
$profilePhotoUrl = !empty($user_data['foto_perfil']) && file_exists($user_data['foto_perfil']) ? htmlspecialchars($user_data['foto_perfil']) : $defaultPlaceholderUrl;
$stmt_user->close();


// --- LÓGICA DE NOTIFICAÇÕES ---
$notificacoes = []; 
$sql_lembretes = "SELECT ag.titulo_evento, ag.data_agendada, l.titulo as nome_local 
                  FROM agendamentos ag 
                  JOIN locais l ON ag.id_local = l.id_local 
                  WHERE ag.id_usuario = ? AND ag.data_agendada >= NOW() 
                  ORDER BY ag.data_agendada ASC LIMIT 5";
$stmt_lembretes = $conn->prepare($sql_lembretes);
$stmt_lembretes->bind_param("i", $user_id);
$stmt_lembretes->execute();
$result_lembretes = $stmt_lembretes->get_result();
while ($row = $result_lembretes->fetch_assoc()) {
    $notificacoes[] = ['tipo' => 'lembrete', 'titulo' => $row['titulo_evento'], 'local' => $row['nome_local'], 'data' => $row['data_agendada'], 'link' => '../php/meu_calendario.php'];
}
$stmt_lembretes->close();
$sql_conexoes_notif = "SELECT c.id_conexao, u.nome 
                 FROM conexoes c 
                 JOIN usuarios u ON c.id_solicitante = u.id 
                 WHERE c.id_solicitado = ? AND c.status = 'pendente' 
                 ORDER BY c.data_criacao DESC LIMIT 5";
$stmt_conexoes_notif = $conn->prepare($sql_conexoes_notif);
$stmt_conexoes_notif->bind_param("i", $user_id);
$stmt_conexoes_notif->execute();
$result_conexoes_notif = $stmt_conexoes_notif->get_result();
while ($row = $result_conexoes_notif->fetch_assoc()) {
    $notificacoes[] = ['tipo' => 'conexao', 'nome_solicitante' => $row['nome'], 'id_conexao' => $row['id_conexao'], 'link' => '../php/meus_dates.php'];
}
$stmt_conexoes_notif->close();
$sql_novos_lugares = "SELECT titulo, slug FROM locais ORDER BY id_local DESC LIMIT 2";
$result_novos_lugares = $conn->query($sql_novos_lugares);
if($result_novos_lugares && $result_novos_lugares->num_rows > 0){
     while ($row = $result_novos_lugares->fetch_assoc()) {
         $notificacoes[] = ['tipo' => 'novo_lugar', 'titulo_lugar' => $row['titulo'], 'link' => '../php/local_detalhe.php?slug=' . urlencode($row['slug'])];
     }
}
$total_notificacoes = count($notificacoes);

// --- DADOS PARA SIDEBAR DIREITA (AGENDA) ---
function formatarDataEmPortugues() { setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese'); return strtoupper(strftime('%B %Y')); }
$currentMonthYear = formatarDataEmPortugues();

$proximos_agendamentos = [];
$sql_agenda = "SELECT ag.titulo_evento, ag.data_agendada, l.titulo as nome_local 
               FROM agendamentos ag 
               JOIN locais l ON ag.id_local = l.id_local 
               WHERE ag.id_usuario = ? AND ag.data_agendada >= NOW() 
               ORDER BY ag.data_agendada ASC 
               LIMIT 3";
$stmt_agenda = $conn->prepare($sql_agenda);
if ($stmt_agenda) {
    $stmt_agenda->bind_param("i", $user_id);
    $stmt_agenda->execute();
    $result_agenda = $stmt_agenda->get_result();
    while ($row = $result_agenda->fetch_assoc()) {
        $proximos_agendamentos[] = $row;
    }
    $stmt_agenda->close();
}

// --- BUSCAR PEDIDOS DE CONEXÃO PENDENTES ---
$pedidos_pendentes = [];
$sql_pendentes = "SELECT c.id_conexao, u.nome, u.foto_perfil 
                  FROM conexoes c 
                  JOIN usuarios u ON c.id_solicitante = u.id 
                  WHERE c.id_solicitado = ? AND c.status = 'pendente'";
$stmt_pendentes = $conn->prepare($sql_pendentes);
$stmt_pendentes->bind_param("i", $user_id);
$stmt_pendentes->execute();
$result_pendentes = $stmt_pendentes->get_result();
while($row = $result_pendentes->fetch_assoc()){
    $pedidos_pendentes[] = $row;
}
$stmt_pendentes->close();

// --- BUSCAR CONVITES ENVIADOS PENDENTES ---
$convites_enviados_pendentes = [];
$sql_enviados = "SELECT c.id_conexao, u.nome, u.foto_perfil 
                 FROM conexoes c 
                 JOIN usuarios u ON c.id_solicitado = u.id 
                 WHERE c.id_solicitante = ? AND c.status = 'pendente'";
$stmt_enviados = $conn->prepare($sql_enviados);
$stmt_enviados->bind_param("i", $user_id);
$stmt_enviados->execute();
$result_enviados = $stmt_enviados->get_result();
while($row = $result_enviados->fetch_assoc()){
    $convites_enviados_pendentes[] = $row;
}
$stmt_enviados->close();

// --- BUSCAR CONEXÕES JÁ ACEITAS ---
$compatibilidades = [];
$sql_aceitos = "SELECT 
                    c.id_conexao, 
                    c.compatibilidade_score,
                    CASE WHEN c.id_solicitante = ? THEN u_solicitado.id ELSE u_solicitante.id END as id_parceiro,
                    CASE WHEN c.id_solicitante = ? THEN u_solicitado.nome ELSE u_solicitante.nome END as nome_parceiro,
                    CASE WHEN c.id_solicitante = ? THEN u_solicitado.foto_perfil ELSE u_solicitante.foto_perfil END as foto_parceiro
                FROM conexoes c
                JOIN usuarios u_solicitante ON c.id_solicitante = u_solicitante.id
                JOIN usuarios u_solicitado ON c.id_solicitado = u_solicitado.id
                WHERE (c.id_solicitante = ? OR c.id_solicitado = ?) AND c.status = 'aceito'";
$stmt_aceitos = $conn->prepare($sql_aceitos);
$stmt_aceitos->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt_aceitos->execute();
$result_aceitos = $stmt_aceitos->get_result();

while($row = $result_aceitos->fetch_assoc()){
    $id_conexao = $row['id_conexao'];
    $id_parceiro = $row['id_parceiro'];
    $score_salvo = $row['compatibilidade_score'];

    if ($score_salvo === NULL) {
        $score = calcularCompatibilidade($user_id, $id_parceiro, $id_conexao, $conn);
        if ($score != -1) {
            $sql_update_score = "UPDATE conexoes SET compatibilidade_score = ? WHERE id_conexao = ?";
            $stmt_update = $conn->prepare($sql_update_score);
            if($stmt_update) {
                $stmt_update->bind_param("ii", $score, $id_conexao);
                $stmt_update->execute();
                $stmt_update->close();
            }
        }
        $row['compatibilidade'] = $score;
    } else {
        $row['compatibilidade'] = (int)$score_salvo;
    }
    
    if ($row['compatibilidade'] == -1) {
        $currentUserCompletedS1 = @checkUserCompletedS1($user_id, $conn);
        $currentUserCompletedS2 = @checkUserCompletedS2($user_id, $id_conexao, $conn);
        $partnerUserCompletedS1 = @checkUserCompletedS1($id_parceiro, $conn);
        $partnerUserCompletedS2 = @checkUserCompletedS2($id_parceiro, $id_conexao, $conn);
        
        if (!$currentUserCompletedS1 || !$currentUserCompletedS2) {
            $row['na_reason'] = 'Voce_precisa_responder';
            $row['missing_quiz_link'] = !$currentUserCompletedS1 ? '../php/mudar_essencia.php' : '../php/compatibilidade.php';
        } else if (!$partnerUserCompletedS1 || !$partnerUserCompletedS2) {
            $row['na_reason'] = 'Parceiro_precisa_responder';
        } else {
            $row['na_reason'] = 'Erro_geral'; 
        }
    }
    $compatibilidades[] = $row;
}
$stmt_aceitos->close();

$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Dates - Duo Dates</title>
    <link rel="stylesheet" href="../css/login-feito.css">
    <link rel="stylesheet" href="../css/meus_dates.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <header class="main-header">
       <div class="header-left">
          <a href="../index.php" class="logo-link">
            <span class="logo">Duo Dates</span>
            <img src="../images/logoduodates.png" alt="Coração" class="logo-image">
          </a>
        </div>
        <div class="header-pages">
            <a href="../php/meuslugaresideais.php" title="Meus Lugares Ideais"><i class="fas fa-map-marker-alt"></i></a>
            <a href="../php/meu_calendario.php" title="Meu Calendário"><i class="far fa-calendar-alt"></i></a>
            <a href="../php/meus_dates.php" title="Meus Dates"><i class="fas fa-user-friends"></i></a>
            <a href="../php/adicionar_local.php" title="Adicionar Local"><i class="fas fa-plus"></i></a>
        </div>
        <div class="header-icons">
            <a href="../php/favoritos.php" title="Favoritos"><i class="far fa-heart"></i></a>
            <div class="notification-icon-container">
                 <i class="far fa-bell <?php echo ($total_notificacoes > 0) ? 'has-notifications' : ''; ?>" id="notification-bell-icon"></i>

                 <div class="notification-panel" id="notification-panel">
                     <div class="notification-header">Notificações</div>
                     <div class="notification-list">
                         <?php if (empty($notificacoes)): ?>
                             <div class="notification-item empty">Nenhuma notificação nova.</div>
                         <?php else: ?>
                             <?php foreach ($notificacoes as $notif): ?>
                                 <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="notification-item">
                                     <?php if ($notif['tipo'] === 'lembrete'): ?>
                                         <i class="fas fa-calendar-check notification-icon reminder"></i>
                                         <div><strong>Lembrete:</strong> <?php echo htmlspecialchars($notif['titulo']); ?> em <?php echo htmlspecialchars($notif['local']); ?><small><?php echo date('d/m H:i', strtotime($notif['data'])); ?></small></div>
                                     <?php elseif ($notif['tipo'] === 'conexao'): ?>
                                         <i class="fas fa-user-plus notification-icon connection"></i>
                                         <div><strong>Conexão:</strong> <?php echo htmlspecialchars($notif['nome_solicitante']); ?> quer se conectar.</div>
                                     <?php elseif ($notif['tipo'] === 'novo_lugar'): ?>
                                          <i class="fas fa-map-marker-alt notification-icon new-place"></i>
                                          <div><strong>Sugestão:</strong> Confira <?php echo htmlspecialchars($notif['titulo_lugar']); ?>!</div>
                                     <?php endif; ?>
                                 </a>
                             <?php endforeach; ?>
                         <?php endif; ?>
                     </div>
                 </div>
            </div>
            </div>
    </header>

    <div class="profile-dashboard">
        
        <aside class="profile-sidebar">
             <div class="user-info">
                 <img src="<?php echo $profilePhotoUrl; ?>" alt="Foto de Perfil" class="profile-photo" onerror="this.onerror=null; this.src='<?php echo $defaultPlaceholderUrl; ?>';">
                 <h3><?php echo htmlspecialchars($userName); ?></h3>
                 <p><?php echo htmlspecialchars($userEmail); ?></p>
             </div>
            <nav class="sidebar-nav">
                 <ul>
                    <li><a href="../php/login-feito.php" class="nav-item"><i class="fas fa-home"></i> <span>Meu Perfil</span></a></li>
                    <li><a href="../php/mudar_essencia.php" class="nav-item"><i class="fas fa-clipboard-list"></i><span> Minha Essência</span></a></li>
                    <li><a href="../php/meus_dates.php" class="nav-item active"><i class="fas fa-user-friends"></i> <span>Meus Dates</span></a></li>
                    <li><a href="../php/editar_perfil_login_feito.php" class="nav-item"><i class="fas fa-user-edit"></i><span> Editar Perfil</span></a></li>
                    <li><a href="../php/logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span> Sair</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <section class="section-container">
                
                <?php if ($success_message): ?><p class="message success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></p><?php endif; ?>
                <?php if ($error_message): ?><p class="message error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?></p><?php endif; ?>

                <?php if (!empty($pedidos_pendentes)): ?>
                    <div class="pending-requests-section">
                        <h4><i class="fas fa-hourglass-half"></i> Pedidos de Conexão Pendentes</h4>
                        <div class="compatibilidade-list">
                            <?php foreach ($pedidos_pendentes as $pedido): ?>
                                <div class="compatibilidade-card pending">
                                    <img src="<?php echo !empty($pedido['foto_perfil']) ? htmlspecialchars($pedido['foto_perfil']) : htmlspecialchars($defaultPlaceholderUrl); ?>" alt="Foto de <?php echo htmlspecialchars($pedido['nome']); ?>" class="compatibilidade-photo">
                                    <div class="compatibilidade-info">
                                        <h3><?php echo htmlspecialchars($pedido['nome']); ?></h3><p class="pending-text">enviou um pedido de conexão.</p>
                                    </div>
                                    <div class="compatibilidade-actions">
                                        <a href="../php/aceitar_conexao.php?id_conexao=<?php echo $pedido['id_conexao']; ?>" class="btn-accept"><i class="fas fa-check"></i> Aceitar</a>
                                        <a href="../php/recusar_conexao.php?id_conexao=<?php echo $pedido['id_conexao']; ?>" class="btn-decline"><i class="fas fa-times"></i> Recusar</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="actions-header"><a href="../php/criar_conexao.php" class="btn-primary"><i class="fas fa-plus-circle"></i> Criar Nova Compatibilidade</a></div>

                <?php if (!empty($convites_enviados_pendentes)): ?>
                    <div class="pending-requests-section sent-requests-section">
                        <h4><i class="fas fa-paper-plane"></i> Convites Enviados Aguardando Resposta</h4>
                        <div class="compatibilidade-list">
                            <?php foreach ($convites_enviados_pendentes as $convite): ?>
                                <div class="compatibilidade-card pending">
                                    <img src="<?php echo !empty($convite['foto_perfil']) ? htmlspecialchars($convite['foto_perfil']) : htmlspecialchars($defaultPlaceholderUrl); ?>" alt="Foto de <?php echo htmlspecialchars($convite['nome']); ?>" class="compatibilidade-photo">
                                    <div class="compatibilidade-info">
                                        <h3><?php echo htmlspecialchars($convite['nome']); ?></h3>
                                        <p class="pending-text">Aguardando aceitação...</p>
                                    </div>
                                    <div class="compatibilidade-actions">
                                        <a href="../php/desvincular_conexao.php?id_conexao=<?php echo $convite['id_conexao']; ?>" 
                                           class="btn-decline" 
                                           onclick="return confirm('Tem certeza que deseja cancelar o convite enviado para <?php echo htmlspecialchars(addslashes($convite['nome'])); ?>?');">
                                           <i class="fas fa-times"></i> Cancelar
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="section-header"><h2>MINHAS COMPATIBILIDADES</h2></div>
                <div class="compatibilidade-list">
                    <?php if (empty($compatibilidades) && empty($convites_enviados_pendentes) && empty($pedidos_pendentes)): ?>
                        <div class="empty-state"><i class="fas fa-users-slash"></i><p>Você ainda não tem nenhuma compatibilidade aceita.</p><span>Conecte-se com outros usuários ou aceite um pedido pendente!</span></div>
                    <?php else: ?>
                        <?php foreach ($compatibilidades as $comp): ?>
                            <div class="compatibilidade-card">
                                <img src="<?php echo !empty($comp['foto_parceiro']) ? htmlspecialchars($comp['foto_parceiro']) : htmlspecialchars($defaultPlaceholderUrl); ?>" alt="Foto de <?php echo htmlspecialchars($comp['nome_parceiro']); ?>" class="compatibilidade-photo">
                                <div class="compatibilidade-info">
                                    <h3><?php echo htmlspecialchars($comp['nome_parceiro']); ?></h3>
                                    
                                    <?php if ($comp['compatibilidade'] == -1): ?>
                                        <div class="compatibilidade-score not-calculated">
                                            <i class="fas fa-question-circle"></i> N/A
                                        </div>
                                        </div> <div class="compatibilidade-actions">
                                            <?php 
                                            if (isset($comp['na_reason']) && $comp['na_reason'] == 'Voce_precisa_responder') {
                                                $link_questionario = $comp['missing_quiz_link'];
                                                $texto_botao = 'Responder (Essência)';
                                                if ($link_questionario == '../php/compatibilidade.php') {
                                                    $link_questionario .= '?id_conexao=' . $comp['id_conexao'];
                                                    $texto_botao = 'Responder (Compatibilidade)';
                                                }
                                                echo '<a href="' . $link_questionario . '" class="btn-primary" style="font-size: 0.8rem; padding: 8px 12px; background-color: #f0ad4e; border-color: #f0ad4e;"> <i class="fas fa-edit"></i> ' . $texto_botao . '</a>';
                                            } else if (isset($comp['na_reason']) && $comp['na_reason'] == 'Parceiro_precisa_responder') {
                                                echo '<span class="action-link" style="background-color: #f7f7f7; color: #777; cursor: not-allowed;"><i class="fas fa-hourglass-half"></i> Aguardando ' . htmlspecialchars($comp['nome_parceiro']) . '</span>';
                                            } else {
                                                 echo '<span class="action-link" style="background-color: #f7f7f7; color: #777; cursor: not-allowed;"><i class="fas fa-exclamation-triangle"></i> Erro</span>';
                                            }
                                            ?>
                                            <a href="../php/desvincular_conexao.php?id_conexao=<?php echo $comp['id_conexao']; ?>" 
                                               class="btn-unlink" 
                                               onclick="return confirm('Tem certeza que deseja desvincular de <?php echo htmlspecialchars(addslashes($comp['nome_parceiro'])); ?>?');">
                                               <i class="fas fa-unlink"></i> Desvincular
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="compatibilidade-score">
                                            <i class="fas fa-heart"></i> <?php echo $comp['compatibilidade']; ?>%
                                        </div>
                                        </div> <div class="compatibilidade-actions">
                                            <a href="../php/perfil_em_comum.php?id=<?php echo $comp['id_parceiro']; ?>&id_conexao=<?php echo $comp['id_conexao']; ?>" class="action-link"><i class="fas fa-user-friends"></i> Perfil Comum</a>
                                            <a href="../php/lugares_em_comum.php?id=<?php echo $comp['id_parceiro']; ?>" class="action-link"><i class="fas fa-map-marked-alt"></i> Favoritos</a>
                                            <a href="../php/desvincular_conexao.php?id_conexao=<?php echo $comp['id_conexao']; ?>" 
                                               class="btn-unlink" 
                                               onclick="return confirm('Tem certeza que deseja desvincular de <?php echo htmlspecialchars(addslashes($comp['nome_parceiro'])); ?>?');">
                                               <i class="fas fa-unlink"></i> Desvincular
                                            </a>
                                        </div>
                                    <?php endif; ?>
                            </div> 
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside class="matches-sidebar">
            <div class="matches-container">
                <div class="matches-header"> 
                    <h4><i class="fas fa-ticket-alt"></i> Próximos Eventos em Brasília</h4>
                </div>

                <?php @include '../php/buscar_ticketmaster.php'; ?>

<div class="events-list"> 
    <?php if (isset($eventosFormatados['error'])): ?>
        <div class="event-item error-message">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($eventosFormatados['error']); ?>
        </div>
    <?php elseif (isset($eventosFormatados['info'])): ?>
            <div class="event-item info-message">
            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($eventosFormatados['info']); ?>
        </div>
    <?php elseif (!empty($eventosFormatados)): ?>
        <?php foreach ($eventosFormatados as $evt): ?>
            <a href="<?php echo htmlspecialchars($evt['url']); ?>" target="_blank" class="event-item">
                <div class="event-details">
                    <span class="event-name"><?php echo htmlspecialchars($evt['nome']); ?></span>
                    <span class="event-info">
                        <i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($evt['dataHora']); ?>
                            | <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['local']); ?>
                    </span>
                </div>
                <div class="event-link-icon">
                    <i class="fas fa-external-link-alt"></i>
                </div>
            </a>
        <?php endforeach; ?>
    <?php else: ?>
            <div class="event-item info-message">
            <i class="fas fa-info-circle"></i> Não foi possível carregar os eventos no momento.
        </div>
    <?php endif; ?>
</div>
                 
                 <div class="agenda-section" style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                    <div class="matches-header"> 
                         <h4><i class="far fa-calendar-check"></i> AGENDA <?php echo $currentMonthYear; ?></h4>
                    </div>
                    <div class="agenda-list">
                        <?php if (empty($proximos_agendamentos)): ?>
                            <div class="agenda-empty">
                                <i class="fas fa-calendar-times"></i>
                                <p>Nenhum evento agendado.</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese'); 
                            ?>
                            <?php foreach ($proximos_agendamentos as $evt): ?>
                                <div class="agenda-item">
                                    <div class="agenda-date">
                                        <span class="day"><?php echo date('d', strtotime($evt['data_agendada'])); ?></span>
                                        <span class="month"><?php echo strftime('%b', strtotime($evt['data_agendada'])); ?></span>
                                    </div>
                                    <div class="agenda-details">
                                        <span class="title"><?php echo htmlspecialchars($evt['titulo_evento']); ?></span>
                                        <span class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['nome_local']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <a href="../php/meu_calendario.php" class="agenda-full-link">Ver calendário completo</a>
                    </div>
                </div> 
            </div>
        </aside>
    </div>
    
    <?php if ($total_notificacoes > 0): ?>
        <div id="toast-notification" class="toast-notification">
            <i class="fas fa-bell toast-icon"></i>
            <span class="toast-message">Você tem <?php echo $total_notificacoes; ?> notificaç<?php echo ($total_notificacoes > 1) ? 'ões' : 'ão'; ?> não lida<?php echo ($total_notificacoes > 1) ? 's' : ''; ?>!</span>
            <button class="toast-close-btn" id="toast-close-btn">&times;</button>
        </div>
    <?php endif; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bellIconContainer = document.querySelector('.notification-icon-container');
            const notificationPanel = document.getElementById('notification-panel');
            if (bellIconContainer && notificationPanel) {
                bellIconContainer.addEventListener('click', function(event) { 
                    event.stopPropagation(); 
                    notificationPanel.classList.toggle('show'); 
                });
                document.addEventListener('click', function(event) { 
                    if (notificationPanel.classList.contains('show') && !notificationPanel.contains(event.target)) { 
                        notificationPanel.classList.remove('show'); 
                    } 
                });
                notificationPanel.addEventListener('click', function(event) { 
                    event.stopPropagation(); 
                });
            }

            const toastNotification = document.getElementById('toast-notification');
            if (toastNotification) {
                setTimeout(() => {
                    toastNotification.classList.add('show');
                }, 500); 

                const autoCloseToast = setTimeout(() => {
                    toastNotification.classList.remove('show');
                }, 5500); 

                const closeBtn = document.getElementById('toast-close-btn');
                closeBtn.addEventListener('click', () => {
                    clearTimeout(autoCloseToast); 
                    toastNotification.classList.remove('show');
                });
            }
        });
    </script>
</body>
</html>