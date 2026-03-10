<?php
session_start();
// Defina UM placeholder padrão. Use 'placeholder_local.png' se tiver uma imagem genérica para locais.
$defaultPlaceholderUrl = "../images/perfil.png"; // <-- VERIFIQUE SE ESTE ARQUIVO EXISTE OU MUDE O NOME
include '../conexao.php'; // Inclui a conexão com o banco

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$userName = "Usuário";
$userEmail = "";
$profilePhotoUrl = $defaultPlaceholderUrl; // Usa o placeholder como padrão

// --- BUSCAR DADOS BÁSICOS DO USUÁRIO ---
$sql_user = "SELECT nome, email, foto_perfil FROM usuarios WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
if ($stmt_user) { // Verifica se a preparação foi bem-sucedida
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($user_data = $result_user->fetch_assoc()) {
        $userName = $user_data['nome'];
        $userEmail = $user_data['email'];
        // Verifica se a foto existe antes de usar
        if (!empty($user_data['foto_perfil']) && file_exists($user_data['foto_perfil'])) {
            $profilePhotoUrl = $user_data['foto_perfil'];
        }
    }
    $stmt_user->close();
} else {
    // Tratar erro na preparação da query, se necessário
    error_log("Erro ao preparar query de usuário: " . $conn->error);
}


// --- BUSCAR PREFERÊNCIAS DO USUÁRIO ---
$userPreferencesList = [];
$totalUserPreferences = 0; // Contará quantos itens de preferência o usuário tem

$sql_prefs = "SELECT pref_vibe, pref_food_ranking, pref_atividade, pref_orcamento, pref_comfort FROM usuarios WHERE id = ?";
$stmt_prefs = $conn->prepare($sql_prefs);
if ($stmt_prefs) {
    $stmt_prefs->bind_param("i", $user_id);
    $stmt_prefs->execute();
    $result_prefs = $stmt_prefs->get_result();
    if ($pref_data = $result_prefs->fetch_assoc()) {

        // 1. Vibe (CORRIGIDO: Usando explode para separar as tags)
        if (!empty($pref_data['pref_vibe'])) {
            $userPreferencesList = array_merge($userPreferencesList, array_map('trim', explode(',', $pref_data['pref_vibe'])));
        }

        // 2. Comida (Pergunta 2 - JSON Ranking)
        if (!empty($pref_data['pref_food_ranking'])) {
             $foodPrefs = json_decode($pref_data['pref_food_ranking'], true);
             if (is_array($foodPrefs)) {
                 $userPreferencesList = array_merge($userPreferencesList, array_keys($foodPrefs));
             }
        }

        // 3. Atividade (Pergunta 3 - Checkbox, separado por vírgula)
        if (!empty($pref_data['pref_atividade'])) {
            $userPreferencesList = array_merge($userPreferencesList, array_map('trim', explode(',', $pref_data['pref_atividade'])));
        }

        // 4. Orçamento (Pergunta 4 - Checkbox, separado por vírgula)
        if (!empty($pref_data['pref_orcamento'])) {
            $userPreferencesList = array_merge($userPreferencesList, array_map('trim', explode(',', $pref_data['pref_orcamento'])));
        }

        // 5. Conforto (Pergunta 5 - Checkbox, separado por vírgula)
        if (!empty($pref_data['pref_comfort'])) {
            $userPreferencesList = array_merge($userPreferencesList, array_map('trim', explode(',', $pref_data['pref_comfort'])));
        }

        $userPreferencesList = array_filter(array_unique($userPreferencesList));
        $totalUserPreferences = count($userPreferencesList);
    }
    $stmt_prefs->close();
} else {
     error_log("Erro ao preparar query de preferências: " . $conn->error);
}

// --- BUSCAR LOCAIS E CALCULAR COMPATIBILIDADE ---
$placesWithScores = [];
$idealPlaces = []; // Inicializa como array vazio

if ($totalUserPreferences > 0) { // Só calcula se o usuário tiver respondido algo
    $sql_places = "SELECT
                        l.id_local, l.titulo, l.imagem_url, l.slug,
                        GROUP_CONCAT(DISTINCT t.nome_tag SEPARATOR '|') AS tag_names
                    FROM locais l
                    LEFT JOIN local_tags lt ON l.id_local = lt.id_local_fk
                    LEFT JOIN tags t ON lt.id_tag_fk = t.id_tag
                    GROUP BY l.id_local, l.titulo, l.imagem_url, l.slug";

    $result_places = $conn->query($sql_places);

    if ($result_places && $result_places->num_rows > 0) {
        while ($place_row = $result_places->fetch_assoc()) {
            $placeTags = [];
            if (!empty($place_row['tag_names'])) {
                $placeTags = array_map('trim', explode('|', $place_row['tag_names']));
                $placeTags = array_filter(array_unique($placeTags));
            }
            $totalPlaceTags = count($placeTags);

            $matchCount = 0;
            if ($totalPlaceTags > 0 && $totalUserPreferences > 0) {
                 $matches = array_intersect($userPreferencesList, $placeTags);
                 $matchCount = count($matches);
            }

            // Usando a fórmula: (% das prefs do user atendidas + % das tags do local que deram match) / 2
            $scoreUserPrefs = ($totalUserPreferences > 0) ? ($matchCount / $totalUserPreferences) * 100 : 0;
            $scorePlaceTags = ($totalPlaceTags > 0) ? ($matchCount / $totalPlaceTags) * 100 : 0; 
            $score = round(($scoreUserPrefs + $scorePlaceTags) / 2); // Média

            // Considera apenas locais com alguma compatibilidade
            if ($score > 0) {
                $placesWithScores[$place_row['id_local']] = [
                    'id' => $place_row['id_local'],
                    'titulo' => $place_row['titulo'],
                    'imagem_url' => $place_row['imagem_url'],
                    'slug' => $place_row['slug'],
                    'score' => $score
                ];
            }
        }
        $result_places->close();

        // --- ORDENAR LOCAIS POR PONTUAÇÃO ---
        uasort($placesWithScores, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // --- SELECIONAR OS TOP N LOCAIS ---
        $numberOfPlacesToShow = 8;
        $idealPlaces = array_slice($placesWithScores, 0, $numberOfPlacesToShow, true);
    }
}


// --- BUSCAR NOTIFICAÇÕES ---
$notificacoes = [];
// 1. Lembretes de Agendamentos
$sql_lembretes = "SELECT ag.titulo_evento, ag.data_agendada, l.titulo as nome_local
                  FROM agendamentos ag
                  JOIN locais l ON ag.id_local = l.id_local
                  WHERE ag.id_usuario = ? AND ag.data_agendada >= NOW()
                  ORDER BY ag.data_agendada ASC LIMIT 5";
$stmt_lembretes = $conn->prepare($sql_lembretes);
if ($stmt_lembretes) {
    $stmt_lembretes->bind_param("i", $user_id);
    $stmt_lembretes->execute();
    $result_lembretes = $stmt_lembretes->get_result();
    while ($row = $result_lembretes->fetch_assoc()) {
        $notificacoes[] = [
            'tipo' => 'lembrete',
            'titulo' => $row['titulo_evento'],
            'local' => $row['nome_local'],
            'data' => $row['data_agendada'],
            'link' => '../php/meu_calendario.php' 
        ];
    }
    $stmt_lembretes->close();
}

// 2. Pedidos de Conexão
$sql_conexoes = "SELECT c.id_conexao, u.nome
                 FROM conexoes c
                 JOIN usuarios u ON c.id_solicitante = u.id
                 WHERE c.id_solicitado = ? AND c.status = 'pendente'
                 ORDER BY c.data_criacao DESC LIMIT 5";
$stmt_conexoes = $conn->prepare($sql_conexoes);
if ($stmt_conexoes) {
    $stmt_conexoes->bind_param("i", $user_id);
    $stmt_conexoes->execute();
    $result_conexoes = $stmt_conexoes->get_result();
    while ($row = $result_conexoes->fetch_assoc()) {
        $notificacoes[] = [
            'tipo' => 'conexao',
            'nome_solicitante' => $row['nome'],
            'id_conexao' => $row['id_conexao'],
            'link' => '../php/meus_dates.php' 
        ];
    }
    $stmt_conexoes->close();
}

// 3. LÓGICA DE NOTIFICAÇÃO DE LUGARES BASEADA EM COMPATIBILIDADE
if (!empty($placesWithScores)) {
    $placesForNotification = array_diff_key($placesWithScores, $idealPlaces);

    if (!empty($placesForNotification)) {
        $top2Notify = array_slice($placesForNotification, 0, 2, true);

        foreach ($top2Notify as $place) {
            $notificacoes[] = [
                'tipo' => 'novo_lugar',
                'titulo_lugar' => $place['titulo'],
                'link' => '../php/local_detalhe.php?slug=' . urlencode($place['slug'])
            ];
        }
    }
}


$total_notificacoes = count($notificacoes);

// --- OUTROS DADOS PARA A PÁGINA ---

// --- BUSCAR TODAS AS CATEGORIAS PARA EXPLORAR ---
$sql_all_categorias = "SELECT nome, imagem_url, link_pagina, icone_fa FROM categorias ORDER BY nome ASC";
$result_all_categorias = $conn->query($sql_all_categorias);
$all_categorias = [];
if ($result_all_categorias) {
    while ($row = $result_all_categorias->fetch_assoc()) {
        $all_categorias[] = $row;
    }
} else {
    // Deixa um erro no log, mas não quebra a página
    error_log("Erro ao buscar 'Categorias para Explorar': " . $conn->error);
}
function formatarDataEmPortugues() { setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese'); return strtoupper(strftime('%B %Y')); }
$currentMonthYear = formatarDataEmPortugues();
$matches = [ ["nome" => "Bruno Mars", "encontro" => "Show Arena BRB", "score" => 90], ["nome" => "Festa Junina", "encontro" => "Parque da Cidade", "score" => 75] ];

// ===================================================================
// INÍCIO DA ALTERAÇÃO: BUSCAR AGENDAMENTOS PARA MINI-AGENDA
// ===================================================================
$proximos_agendamentos = [];
$sql_agenda = "SELECT ag.titulo_evento, ag.data_agendada, l.titulo as nome_local 
               FROM agendamentos ag 
               JOIN locais l ON ag.id_local = l.id_local 
               WHERE ag.id_usuario = ? AND ag.data_agendada >= NOW() 
               ORDER BY ag.data_agendada ASC 
               LIMIT 3"; // Busca os próximos 3 eventos
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
// ===================================================================
// FIM DA ALTERAÇÃO
// ===================================================================


?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil Duo Dates</title>
    <link rel="stylesheet" href="../css/login-feito.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* Adicione este estilo para a imagem dentro do card */
        .card-image-placeholder {
            width: 150px; /* Ou a largura definida no seu CSS */
            height: 120px; /* Ou a altura definida no seu CSS */
            object-fit: cover; /* Faz a imagem cobrir a área sem distorcer */
            border-radius: 10px; /* Mantém o arredondamento */
            background-color: #ddd; /* Cor de fundo enquanto carrega ou se falhar */
        }
        .empty-state-message { /* Estilo para mensagens de "nenhum resultado" */
            text-align: center;
            padding: 20px;
            color: #3d3d3d;
            width: 100%; /* Ocupa toda a largura do container */
        }
         .empty-state-message a {
            color: var(--primary-color);
            font-weight: bold;
            text-decoration: underline;
        }
        /* Classe para esconder cards na busca de categoria */
        .explore-card.hidden {
            display: none;
        }
    </style>
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
            <a href="../php/adicionar_local.php" title="Adicionar Local"><i class="fas fa-plus"></i></a> </div>
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
                 <img src="<?php echo htmlspecialchars($profilePhotoUrl); ?>" alt="Foto de Perfil" class="profile-photo" onerror="this.onerror=null; this.src='<?php echo $defaultPlaceholderUrl; ?>';">
                <h3><?php echo htmlspecialchars($userName); ?></h3>
                <p><?php echo htmlspecialchars($userEmail); ?></p>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="../php/login-feito.php" class="nav-item active"><i class="fas fa-home"></i> <span>Meu Perfil</span></a></li>
                    <li><a href="../php/mudar_essencia.php" class="nav-item"><i class="fas fa-clipboard-list"></i><span> Minha Essência</span></a></li>
                    <li><a href="../php/meus_dates.php" class="nav-item"><i class="fas fa-user-friends"></i> <span>Meus Dates</span></a></li>
                    <li><a href="../php/editar_perfil_login_feito.php" class="nav-item"><i class="fas fa-user-edit"></i><span> Editar Perfil</span></a></li>
                    <li><a href="../php/logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span> Sair</span></a></li>
                </ul>
            </nav>
            
            <div class="sidebar-chart">
                <div class="chart-title"></div>
                <div class="chart-area">
                    <?php
                    if (!empty($idealPlaces)) {
                        // Pega os 4 primeiros LOCAIS (que já contêm título e score)
                        $chartPlaces = array_slice($idealPlaces, 0, 4);

                        // Itera pelos 4 locais (ou menos, se houver menos)
                        foreach ($chartPlaces as $place) {
                            $score = $place['score'];
                            // Cria o texto para o hover (passar o mouse)
                            $title = htmlspecialchars($place['titulo']) . ' - ' . $score . '% de compatibilidade'; 
                            // Garante altura mínima de 1% para barras > 0
                            $height = ($score > 0) ? max(1, $score) : 0;
                            // Imprime a barra com a altura e o título dinâmicos
                            echo '<div class="chart-bar" style="height: ' . $height . '%;" title="' . $title . '"></div>';
                        }
                        
                        // Preenche as barras restantes (até 4) com 0%
                        $barrasRestantes = 4 - count($chartPlaces);
                        for ($i = 0; $i < $barrasRestantes; $i++) {
                            echo '<div class="chart-bar" style="height: 0%;" title="Nenhuma recomendação"></div>';
                        }

                    } else {
                        // Se não houver locais (ex: questionário não respondido), mostra 4 barras vazias
                        echo '<div class="chart-bar" style="height: 0%;"></div>';
                        echo '<div class="chart-bar" style="height: 0%;"></div>';
                        echo '<div class="chart-bar" style="height: 0%;"></div>';
                        echo '<div class="chart-bar" style="height: 0%;"></div>';
                    }
                    ?>
                </div>
            </div>
            </aside>

        <main class="main-content">
            <section class="section-container ideal-places">
                <div class="section-header">
                    <h2>MEUS LUGARES IDEAIS</h2>
                    <a href="../php/meuslugaresideais.php" id="show-more-btn">ver mais</a>
                </div>
                <div class="horizontal-scroll-cards">
                    <?php if (!empty($idealPlaces)): ?>
                        <?php foreach ($idealPlaces as $place): ?>
                            <a href="../php/local_detalhe.php?slug=<?php echo htmlspecialchars($place['slug']); ?>" class="place-card">
                               <?php 
    $placeImg = $place['imagem_url'];
    // Mesma lógica: se não tiver '../' e não for site externo, adiciona '../'
    if (strpos($placeImg, 'http') === false && strpos($placeImg, '../') === false) {
        $placeImg = '../' . $placeImg;
    }
?>
<img src="<?php echo htmlspecialchars($placeImg); ?>"
     alt="<?php echo htmlspecialchars($place['titulo']); ?>"
     class="card-image-placeholder"
     onerror="this.onerror=null; this.src='<?php echo $defaultPlaceholderUrl; ?>';">
                                <p><?php echo $place['score']; ?>% de compatibilidade</p>
                            </a>
                        <?php endforeach; ?>
                    <?php elseif ($totalUserPreferences > 0): // Se respondeu questionário mas não achou nada ?>
                        <p class="empty-state-message">Não encontramos lugares com alta compatibilidade no momento. Que tal <a href="#explore">explorar as categorias</a> abaixo?</p>
                    <?php else: // Se não respondeu o questionário ?>
                        <p class="empty-state-message">Para ver sugestões personalizadas, responda ao questionário <a href="mudar_essencia.php">'Minha Essência'</a>!</p>
                    <?php endif; ?>
                </div>
            </section>
            
            <section class="section-container explore-categories" id="explore"> 
                <div class="section-header">
                    <h2>CATEGORIAS PARA EXPLORAR</h2>
                </div>
                <div class="search-bar">
                    <input type="text" id="category-search-input" placeholder="Buscar por estilo, culinária, atividade...">
                    <i class="fas fa-search"></i>
                </div>
                
                <div class="explore-cards-grid">
                    
                    <?php if (count($all_categorias) > 0): ?>
                        <?php foreach ($all_categorias as $categoria): ?>
                            
                           <?php 
    // LÓGICA DE CORREÇÃO DE CAMINHO
    $catImg = $categoria['imagem_url'];
    // Se não for link externo e não tiver '../', a gente corrige
    if (strpos($catImg, 'http') === false && strpos($catImg, '../') === false) {
        // Se no banco salvou como "images/foto.jpg", adiciona "../"
        // Se salvou só "foto.jpg", adiciona "../"
        $catImg = '../' . $catImg;
    }
?>
<a href="<?php echo htmlspecialchars($categoria['link_pagina']); ?>" 
   class="explore-card" 
   data-tags="<?php echo htmlspecialchars($categoria['nome']); ?>" 
   style="background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('<?php echo htmlspecialchars($catImg); ?>'); background-size: cover; background-position: center;">
                                
                                <div class="card-overlay">
                                    <div class="card-content">
                                        <h3><?php echo strtoupper(htmlspecialchars($categoria['nome'])); ?></h3>
                                        <i class="<?php echo htmlspecialchars($categoria['icone_fa']); ?>"></i>
                                    </div>
                                </div>
                            </a>
                            
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-message">
                            Nenhuma categoria principal foi encontrada no banco de dados.
                        </div>
                    <?php endif; ?>
                
                    <div id="no-results-message" class="empty-state-message" style="display: none;">
                        Nenhuma categoria encontrada com esses termos.
                    </div>
                    
                </div>
                </section>
            </main>
        
        <aside class="matches-sidebar">
            <div class="matches-container">
                <div class="matches-header">
                    
                
                    <div class="matches-header"> <h4><i class="fas fa-ticket-alt"></i> Próximos Eventos em Brasília</h4>
                    </div>

                    <?php 
                    // Tenta incluir o arquivo que busca eventos
                    @include '../php/buscar_ticketmaster.php'; 
                    ?>

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
                            // Definir locale para abreviação do mês em PT-BR
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
            // Script existente do painel de notificação
            const bellIconContainer = document.querySelector('.notification-icon-container');
            const notificationPanel = document.getElementById('notification-panel');
            if (bellIconContainer && notificationPanel) {
                bellIconContainer.addEventListener('click', function(event) { event.stopPropagation(); notificationPanel.classList.toggle('show'); });
                document.addEventListener('click', function(event) { if (notificationPanel.classList.contains('show') && !notificationPanel.contains(event.target)) { notificationPanel.classList.remove('show'); } });
                notificationPanel.addEventListener('click', function(event) { event.stopPropagation(); });
            }

            // Script existente do Toast de notificação
            const toastNotification = document.getElementById('toast-notification');
            if (toastNotification) {
                setTimeout(() => {
                    toastNotification.classList.add('show');
                }, 500); // Mostra após 0.5s

                const autoCloseToast = setTimeout(() => {
                    toastNotification.classList.remove('show');
                }, 5500); // Esconde após 5.5s (5s visível)

                const closeBtn = document.getElementById('toast-close-btn');
                closeBtn.addEventListener('click', () => {
                    clearTimeout(autoCloseToast); // Cancela o fechamento automático
                    toastNotification.classList.remove('show');
                });
            }

            // Script da barra de pesquisa de categorias
            const searchInput = document.getElementById('category-search-input');
            const categoryCards = document.querySelectorAll('.explore-cards-grid .explore-card');
            const noResultsMessage = document.getElementById('no-results-message');

            if (searchInput && categoryCards.length > 0 && noResultsMessage) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = searchInput.value.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Remove acentos
                    let visibleCount = 0;

                    categoryCards.forEach(card => {
                        const cardText = card.textContent.toLowerCase().trim().normalize("NFD").replace(/[\u0300-\u036f]/g, ""); // Pega todo texto do card
                        const tags = card.dataset.tags ? card.dataset.tags.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "") : ""; // Pega tags

                        // Verifica se o termo está no texto OU nas tags
                        if (cardText.includes(searchTerm) || tags.includes(searchTerm)) {
                            card.classList.remove('hidden');
                            visibleCount++;
                        } else {
                            card.classList.add('hidden');
                        }
                    });

                    // Mostra ou esconde a mensagem de "nenhum resultado"
                    noResultsMessage.style.display = (visibleCount === 0) ? 'block' : 'none';
                });
            }
        });
    </script>
    <?php
    // --- Bloco de Fechamento da Conexão ---
    if (isset($conn) && $conn) {
        $conn->close();
    }
    ?>
</body>
</html>