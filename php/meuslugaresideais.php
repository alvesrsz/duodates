<?php
session_start();
// Defina UM placeholder padrão.
$defaultPlaceholderUrl = "../images/perfil.png"; 
include '../conexao.php'; 

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$userName = "Usuário";
$userEmail = "";
$profilePhotoUrl = $defaultPlaceholderUrl; 

// --- 1. BUSCAR DADOS BÁSICOS DO USUÁRIO ---
$sql_user = "SELECT nome, email, foto_perfil FROM usuarios WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
if ($stmt_user) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    if ($user_data = $result_user->fetch_assoc()) {
        $userName = $user_data['nome'];
        $userEmail = $user_data['email'];
        // Verifica se a foto existe
        if (!empty($user_data['foto_perfil'])) {
             // Lógica simples para foto de perfil: se não começar com ../ e não for http, adiciona
             $foto = $user_data['foto_perfil'];
             if (strpos($foto, 'http') === false && strpos($foto, '../') === false) {
                 $foto = '../' . $foto;
             }
             if (file_exists($foto) || strpos($foto, 'http') !== false) {
                 $profilePhotoUrl = $foto;
             }
        }
    }
    $stmt_user->close();
}

// --- 2. BUSCAR PREFERÊNCIAS DO USUÁRIO ---
$userPreferencesList = [];
$totalUserPreferences = 0; 

$sql_prefs = "SELECT pref_vibe, pref_food_ranking, pref_atividade, pref_orcamento, pref_comfort FROM usuarios WHERE id = ?";
$stmt_prefs = $conn->prepare($sql_prefs);
if ($stmt_prefs) {
    $stmt_prefs->bind_param("i", $user_id);
    $stmt_prefs->execute();
    $result_prefs = $stmt_prefs->get_result();
    if ($pref_data = $result_prefs->fetch_assoc()) {
        if (!empty($pref_data['pref_vibe'])) $userPreferencesList = array_merge($userPreferencesList, array_map('trim', explode(',', $pref_data['pref_vibe'])));
        if (!empty($pref_data['pref_food_ranking'])) {
             $foodPrefs = json_decode($pref_data['pref_food_ranking'], true);
             if (is_array($foodPrefs)) $userPreferencesList = array_merge($userPreferencesList, array_keys($foodPrefs));
        }
        if (!empty($pref_data['pref_atividade'])) $userPreferencesList = array_merge($userPreferencesList, array_map('trim', explode(',', $pref_data['pref_atividade'])));
        if (!empty($pref_data['pref_orcamento'])) $userPreferencesList = array_merge($userPreferencesList, array_map('trim', explode(',', $pref_data['pref_orcamento'])));
        if (!empty($pref_data['pref_comfort'])) $userPreferencesList = array_merge($userPreferencesList, array_map('trim', explode(',', $pref_data['pref_comfort'])));

        $userPreferencesList = array_filter(array_unique($userPreferencesList));
        $totalUserPreferences = count($userPreferencesList);
    }
    $stmt_prefs->close();
}

// --- 3. BUSCAR LOCAIS E CALCULAR COMPATIBILIDADE ---
$placesWithScores = [];
$idealPlaces = []; 

if ($totalUserPreferences > 0) { 
    $sql_places = "SELECT l.id_local, l.titulo, l.imagem_url, l.slug, GROUP_CONCAT(DISTINCT t.nome_tag SEPARATOR '|') AS tag_names
                   FROM locais l
                   LEFT JOIN local_tags lt ON l.id_local = lt.id_local_fk
                   LEFT JOIN tags t ON lt.id_tag_fk = t.id_tag
                   GROUP BY l.id_local, l.titulo, l.imagem_url, l.slug";
    $result_places = $conn->query($sql_places);

    if ($result_places && $result_places->num_rows > 0) {
        while ($place_row = $result_places->fetch_assoc()) {
            $placeTags = !empty($place_row['tag_names']) ? array_filter(array_unique(array_map('trim', explode('|', $place_row['tag_names'])))) : [];
            $totalPlaceTags = count($placeTags);
            $matchCount = ($totalPlaceTags > 0 && $totalUserPreferences > 0) ? count(array_intersect($userPreferencesList, $placeTags)) : 0;

            $scoreUserPrefs = ($totalUserPreferences > 0) ? ($matchCount / $totalUserPreferences) * 100 : 0;
            $scorePlaceTags = ($totalPlaceTags > 0) ? ($matchCount / $totalPlaceTags) * 100 : 0; 
            $score = round(($scoreUserPrefs + $scorePlaceTags) / 2); 

            if ($score > 0) {
                $placesWithScores[$place_row['id_local']] = [
                    'id' => $place_row['id_local'], 'titulo' => $place_row['titulo'],
                    'imagem_url' => $place_row['imagem_url'], 'slug' => $place_row['slug'], 'score' => $score
                ];
            }
        }
        $result_places->close();
        uasort($placesWithScores, function($a, $b) { return $b['score'] <=> $a['score']; });
        $idealPlaces = $placesWithScores;
    }
}

// --- 4. BUSCAR MINI AGENDA ---
$proximos_agendamentos = [];
$sql_agenda = "SELECT ag.titulo_evento, ag.data_agendada, l.titulo as nome_local 
               FROM agendamentos ag 
               JOIN locais l ON ag.id_local = l.id_local 
               WHERE ag.id_usuario = ? AND ag.data_agendada >= NOW() 
               ORDER BY ag.data_agendada ASC LIMIT 3";
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

// --- 5. NOTIFICAÇÕES ---
$notificacoes = [];
$sql_notif = "SELECT ag.titulo_evento, ag.data_agendada, l.titulo as nome_local FROM agendamentos ag JOIN locais l ON ag.id_local = l.id_local WHERE ag.id_usuario = ? AND ag.data_agendada >= NOW() LIMIT 3";
$stmt_notif = $conn->prepare($sql_notif);
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$res_notif = $stmt_notif->get_result();
while ($row = $res_notif->fetch_assoc()) {
    $notificacoes[] = ['tipo' => 'lembrete', 'titulo' => $row['titulo_evento'], 'data' => $row['data_agendada']];
}
$stmt_notif->close();
$total_notificacoes = count($notificacoes);

function formatarDataEmPortugues() {
    $meses = ['JANEIRO','FEVEREIRO','MARÇO','ABRIL','MAIO','JUNHO',
              'JULHO','AGOSTO','SETEMBRO','OUTUBRO','NOVEMBRO','DEZEMBRO'];
    return $meses[(int)date('n') - 1] . ' ' . date('Y');
}
$currentMonthYear = formatarDataEmPortugues();

// Mockup para "O que combina comigo"
$matches = [ ["nome" => "Bruno Mars", "encontro" => "Show Arena BRB", "score" => 90], ["nome" => "Festa Junina", "encontro" => "Parque da Cidade", "score" => 75] ];

$conn->close(); 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Lugares Ideais - Duo Dates</title>
    <link rel="stylesheet" href="../css/login-feito.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    <style>
        /* ESTILOS ESPECÍFICOS DESTA PÁGINA (Para manter os cards grandes) */
        .horizontal-scroll-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
            gap: 20px;
            width: 100%;
            padding-bottom: 20px;
        }
        .place-card {
            width: 100%; height: 240px; 
            border: 1px solid #ddd; border-radius: 12px;
            overflow: hidden; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            background: #fff; display: flex; flex-direction: column;
            transition: transform 0.3s ease;
        }
        .place-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1); }
        .card-image-placeholder {
            width: 100%; height: 160px; object-fit: cover;
            border-radius: 12px 12px 0 0; background-color: #eee;
        }
        .place-card p {
            margin-top: 15px; font-size: 0.95rem; color: var(--primary-color); font-weight: 700; text-align: center;
        }
        .empty-state-message { grid-column: 1 / -1; text-align: center; padding: 40px; color: #777; }
        .empty-state-message a { color: var(--primary-color); text-decoration: underline; }
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
            <a href="../php/adicionar-lugar.php" title="Adicionar Local"><i class="fas fa-plus"></i></a> 
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
                                 <a href="#" class="notification-item">
                                     <i class="fas fa-calendar-check notification-icon reminder"></i>
                                     <div><strong>Lembrete:</strong> <?php echo htmlspecialchars($notif['titulo']); ?></div>
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
                    <li><a href="../php/login-feito.php" class="nav-item"><i class="fas fa-home"></i> <span>Meu Perfil</span></a></li>
                    <li><a href="../php/mudar_essencia.php" class="nav-item"><i class="fas fa-clipboard-list"></i><span> Minha Essência</span></a></li>
                     <li><a href="../php/meus_dates.php" class="nav-item"><i class="fas fa-user-friends"></i> <span>Meus Dates</span></a></li>
                    <li><a href="../php/editar_perfil_login_feito.php" class="nav-item"><i class="fas fa-user-edit"></i><span> Editar Perfil</span></a></li>
                    <li><a href="../php/logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span> Sair</span></a></li>
                </ul>
            </nav>
            
            <div class="sidebar-chart">
                <div class="chart-title">Minha Essência</div>
                <div class="chart-area">
                    <?php
                    if (!empty($idealPlaces)) {
                        $chartPlaces = array_slice($idealPlaces, 0, 4);
                        foreach ($chartPlaces as $place) {
                            $score = $place['score'];
                            $title = htmlspecialchars($place['titulo']) . ' - ' . $score . '%'; 
                            $height = ($score > 0) ? max(5, $score) : 5;
                            echo '<div class="chart-bar" style="height: ' . $height . '%;" title="' . $title . '"></div>';
                        }
                        for($i=count($chartPlaces); $i<4; $i++) { echo '<div class="chart-bar" style="height: 5%;"></div>'; }
                    } else {
                        echo '<div class="chart-bar" style="height: 5%;"></div><div class="chart-bar" style="height: 5%;"></div><div class="chart-bar" style="height: 5%;"></div><div class="chart-bar" style="height: 5%;"></div>';
                    }
                    ?>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <section class="section-container ideal-places">
                <div class="section-header">
                    <h2>MEUS LUGARES IDEAIS</h2>
                </div>
                <div class="horizontal-scroll-cards">
                    <?php if (!empty($idealPlaces)): ?>
                        <?php foreach ($idealPlaces as $place): ?>
                            <?php 
                                $imgDisplay = $place['imagem_url'];
                                // Se não for link externo e não tiver ../, adiciona
                                if (strpos($imgDisplay, 'http') === false && strpos($imgDisplay, '../') === false) {
                                    $imgDisplay = ltrim($imgDisplay, '/'); // Remove barra inicial se tiver
                                    $imgDisplay = '../' . $imgDisplay;
                                }
                            ?>
                            <a href="../php/local_detalhe.php?slug=<?php echo htmlspecialchars($place['slug']); ?>" class="place-card">
                                <img src="<?php echo htmlspecialchars($imgDisplay); ?>"
                                     alt="<?php echo htmlspecialchars($place['titulo']); ?>"
                                     class="card-image-placeholder"
                                     onerror="this.onerror=null; this.src='<?php echo $defaultPlaceholderUrl; ?>';">
                                <p><?php echo $place['score']; ?>% de compatibilidade</p>
                            </a>
                        <?php endforeach; ?>
                    <?php elseif ($totalUserPreferences > 0): ?>
                        <p class="empty-state-message">Não encontramos lugares com alta compatibilidade no momento.</p>
                    <?php else: ?>
                        <p class="empty-state-message">Responda ao questionário <a href="../php/mudar_essencia.php">'Minha Essência'</a>!</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <aside class="matches-sidebar">
            <div class="matches-container">
                <div class="matches-header">
                    <h4>COMBINA COMIGO</h4>
                </div>
                <div class="match-list">
                    <?php foreach ($matches as $match): ?>
                        <div class="match-item">
                            <div class="match-info">
                                <h5><?php echo htmlspecialchars($match['nome']); ?></h5>
                                <p><?php echo htmlspecialchars($match['encontro']); ?></p>
                            </div>
                            <div class="match-score"><?php echo $match['score']; ?>%</div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="events-section" style="margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                    <div class="matches-header"> <h4><i class="fas fa-ticket-alt"></i> Eventos em Brasília</h4></div>
                    <?php @include '../php/buscar_ticketmaster.php'; ?>
                    <div class="events-list"> 
                        <?php if (isset($eventosFormatados['error'])): ?>
                            <div class="event-item error-message"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($eventosFormatados['error']); ?></div>
                        <?php elseif (isset($eventosFormatados['info'])): ?>
                             <div class="event-item info-message"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($eventosFormatados['info']); ?></div>
                        <?php elseif (!empty($eventosFormatados)): ?>
                            <?php foreach ($eventosFormatados as $evt): ?>
                                <a href="<?php echo htmlspecialchars($evt['url']); ?>" target="_blank" class="event-item">
                                    <div class="event-details">
                                        <span class="event-name"><?php echo htmlspecialchars($evt['nome']); ?></span>
                                        <span class="event-info"><i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($evt['dataHora']); ?> | <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['local']); ?></span>
                                    </div>
                                    <div class="event-link-icon"><i class="fas fa-external-link-alt"></i></div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                             <div class="event-item info-message"><i class="fas fa-info-circle"></i> Não foi possível carregar os eventos.</div>
                        <?php endif; ?>
                    </div>
                </div> 
                 
                 <div class="agenda-section" style="margin-top: 20px; border-top: 1px solid var(--border-color); padding-top: 15px;">
                    <div class="matches-header"> <h4><i class="far fa-calendar-check"></i> AGENDA <?php echo $currentMonthYear; ?></h4></div>
                    <div class="agenda-list">
                        <?php if (empty($proximos_agendamentos)): ?>
                            <div class="agenda-empty"><i class="fas fa-calendar-times"></i><p>Nenhum evento agendado.</p></div>
                        <?php else: ?>
                            <?php foreach ($proximos_agendamentos as $evt): ?>
                                <div class="agenda-item">
                                    <div class="agenda-date">
                                        <span class="day"><?php echo date('d', strtotime($evt['data_agendada'])); ?></span>
                                        <?php $meses_abrev = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez']; ?>
                                        <span class="month"><?php echo $meses_abrev[(int)date('n', strtotime($evt['data_agendada'])) - 1]; ?></span>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bellIconContainer = document.querySelector('.notification-icon-container');
            const notificationPanel = document.getElementById('notification-panel');
            if (bellIconContainer && notificationPanel) {
                bellIconContainer.addEventListener('click', function(event) { event.stopPropagation(); notificationPanel.classList.toggle('show'); });
                document.addEventListener('click', function(event) { if (notificationPanel.classList.contains('show') && !notificationPanel.contains(event.target)) { notificationPanel.classList.remove('show'); } });
            }
        });
    </script>
</body>
</html>