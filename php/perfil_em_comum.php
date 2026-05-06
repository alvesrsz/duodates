<?php
session_start();
include '../conexao.php';

// ===================================================================
// LÓGICA DE COMPATIBILIDADE - VERSÃO OPÇÃO 2 (11 SEÇÕES)
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

// --- Proteção e Busca de Dados ---
if (!isset($_SESSION['user_id']) || !isset($_GET['id']) || !isset($_GET['id_conexao'])) {
    header('Location: ../php/login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$partner_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
$id_conexao = filter_var($_GET['id_conexao'], FILTER_VALIDATE_INT); 

if ($partner_id === false || $id_conexao === false || $partner_id === $user_id) {
    header('Location: ../php/meus_dates.php');
    exit();
}

$sql_users = "SELECT id, nome, email, foto_perfil, 
                     pref_vibe, pref_food_ranking, pref_atividade, pref_comfort 
              FROM usuarios 
              WHERE id IN (?, ?)";
$stmt_users = $conn->prepare($sql_users);
$stmt_users->bind_param("ii", $user_id, $partner_id);
$stmt_users->execute();
$result_users = $stmt_users->get_result();

$user_data = [];
$partner_data = [];
while ($row = $result_users->fetch_assoc()) {
    if ($row['id'] == $user_id) {
        $user_data = $row;
    } else {
        $partner_data = $row;
    }
}
$stmt_users->close();

if (empty($partner_data)) {
    header('Location: ../php/meus_dates.php');
    exit();
}

// CORREÇÃO IMAGEM DE PERFIL
$userPhotoUrl = !empty($user_data['foto_perfil']) ? htmlspecialchars($user_data['foto_perfil']) : '../images/perfil.png';
if (strpos($userPhotoUrl, 'http') === false && strpos($userPhotoUrl, '../') === false) $userPhotoUrl = '../' . $userPhotoUrl;

$partnerPhotoUrl = !empty($partner_data['foto_perfil']) ? htmlspecialchars($partner_data['foto_perfil']) : '../images/perfil.png';
if (strpos($partnerPhotoUrl, 'http') === false && strpos($partnerPhotoUrl, '../') === false) $partnerPhotoUrl = '../' . $partnerPhotoUrl;


// 2. CALCULAR A COMPATIBILIDADE
$compatibilidade_score = calcularCompatibilidade($user_id, $partner_id, $id_conexao, $conn);

// ===================================================================
// LÓGICA 1: ENCONTRAR INTERESSES EM COMUM
// ===================================================================
$interesses_compartilhados = [];

$extractTags = function($pref_string) {
    return array_filter(array_map('trim', explode(',', $pref_string ?? '')));
};

$common_vibe = array_intersect($extractTags($user_data['pref_vibe']), $extractTags($partner_data['pref_vibe']));

$extractFoodTags = function($food_json) {
    $ranking = json_decode($food_json ?? '[]', true);
    if (empty($ranking) || !is_array($ranking)) return [];
    return array_keys($ranking); 
};
$common_food = array_intersect($extractFoodTags($user_data['pref_food_ranking']), $extractFoodTags($partner_data['pref_food_ranking']));

$common_activity = array_intersect($extractTags($user_data['pref_atividade']), $extractTags($partner_data['pref_atividade']));
$common_comfort = array_intersect($extractTags($user_data['pref_comfort']), $extractTags($partner_data['pref_comfort']));

$interesses_S1 = array_unique(array_merge($common_vibe, $common_food, $common_activity, $common_comfort));

$prefsA_S2 = fetchPreferences_Sistema2($user_id, $id_conexao, $conn); 
$prefsB_S2 = fetchPreferences_Sistema2($partner_id, $id_conexao, $conn);

$questoes_S2 = [1, 2, 3, 5, 6, 7, 8];
$common_tags_S2_IDs = []; 

if ($prefsA_S2 !== null && $prefsB_S2 !== null) {
    foreach ($questoes_S2 as $q_id) {
        $tagsA = $extractTags($prefsA_S2[$q_id] ?? '');
        $tagsB = $extractTags($prefsB_S2[$q_id] ?? '');
        $common_tags_S2_IDs = array_merge($common_tags_S2_IDs, array_intersect($tagsA, $tagsB));
    }
}

$common_tags_S2_nomes = [];
if (!empty($common_tags_S2_IDs)) {
    $common_tags_S2_unique = array_unique($common_tags_S2_IDs);
    $common_tags_S2_unique = array_filter($common_tags_S2_unique, 'is_numeric'); 
    
    if (!empty($common_tags_S2_unique)) {
        $placeholders = implode(',', array_fill(0, count($common_tags_S2_unique), '?'));
        $types = str_repeat('i', count($common_tags_S2_unique));
        
        $sql_tags = "SELECT nome_tag FROM tags WHERE id_tag IN ($placeholders)";
        $stmt_tags = $conn->prepare($sql_tags);
        $stmt_tags->bind_param($types, ...$common_tags_S2_unique);
        $stmt_tags->execute();
        $result_tags = $stmt_tags->get_result();
        while ($row_tag = $result_tags->fetch_assoc()) {
            $common_tags_S2_nomes[] = $row_tag['nome_tag'];
        }
        $stmt_tags->close();
    }
}

$interesses_compartilhados = array_unique(array_merge($interesses_S1, $common_tags_S2_nomes));

// ===================================================================
// LÓGICA 2: BUSCAR LUGARES QUE COMBINAM
// ===================================================================
$lugares_ideais = [];

if (!empty($interesses_compartilhados)) {
    $interesses_compartilhados = array_filter($interesses_compartilhados); 
    
    if (!empty($interesses_compartilhados)) {
        $placeholders = implode(',', array_fill(0, count($interesses_compartilhados), '?'));
        $types = str_repeat('s', count($interesses_compartilhados));

        $sql_ideal = "
            SELECT l.id_local, l.titulo, l.descricao, l.imagem_url, l.link_botao, l.texto_botao, l.slug, 
                   COUNT(t.id_tag) as match_count
            FROM locais l
            JOIN local_tags lt ON l.id_local = lt.id_local_fk
            JOIN tags t ON lt.id_tag_fk = t.id_tag
            WHERE t.nome_tag IN ($placeholders)
            GROUP BY l.id_local, l.titulo, l.descricao, l.imagem_url, l.link_botao, l.texto_botao, l.slug
            ORDER BY match_count DESC
            LIMIT 6
        ";

        $stmt_ideal = $conn->prepare($sql_ideal);
        if($stmt_ideal) {
            $stmt_ideal->bind_param($types, ...$interesses_compartilhados);
            $stmt_ideal->execute();
            $result_ideal = $stmt_ideal->get_result();
            while ($row = $result_ideal->fetch_assoc()) {
                $lugares_ideais[] = $row;
            }
            $stmt_ideal->close();
        }
    }
}

// ===================================================================
// LÓGICA 3: BUSCAR "LUGARES FAVORITADOS" POR AMBOS
// ===================================================================
$lugares_favoritos = [];
$sql_common = "SELECT f.place_id, l.titulo, l.descricao, l.imagem_url, l.link_botao, l.texto_botao
               FROM favoritos f
               JOIN locais l ON f.place_id = l.slug
               WHERE f.user_id IN (?, ?)
               GROUP BY f.place_id, l.titulo, l.descricao, l.imagem_url, l.link_botao, l.texto_botao
               HAVING COUNT(DISTINCT f.user_id) = 2";

$stmt_common = $conn->prepare($sql_common);
$stmt_common->bind_param("ii", $user_id, $partner_id);
$stmt_common->execute();
$result_common = $stmt_common->get_result();

while ($row = $result_common->fetch_assoc()) {
    $lugares_favoritos[] = $row;
}
$stmt_common->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?php echo htmlspecialchars($user_data['nome']); ?> e <?php echo htmlspecialchars($partner_data['nome']); ?></title>
    <link rel="stylesheet" href="../css/login-feito.css">
    <link rel="stylesheet" href="../css/meus_dates.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
             <a href="../php/favoritos.php"><i class="far fa-heart"></i></a>
            <div class="notification-icon-container">
                 <i class="far fa-bell" id="notification-bell-icon"></i>
            </div>
        </div>
    </header>

    <div class="profile-dashboard" style="grid-template-columns: 280px 1fr;">
        
        <aside class="profile-sidebar">
            <div class="user-info">
                <img src="<?php echo $userPhotoUrl; ?>" alt="Foto de Perfil" class="profile-photo" onerror="this.onerror=null; this.src='../images/perfil.png';">
                <h3><?php echo htmlspecialchars($user_data['nome']); ?></h3>
                <p><?php echo htmlspecialchars($user_data['email']); ?></p>
            </div>
            <nav class="sidebar-nav">
                 <ul>
                    <li class="nav-item"><a href="../php/login-feito.php"><i class="fas fa-home"></i> <span>Meu Perfil</span></a></li>
                    <li class="nav-item"><a href="../php/mudar_essencia.php"> <i class="fas fa-clipboard-list"></i><span> Minha Essência</span></a></li>
                    <li class="nav-item active"><a href="../php/meus_dates.php"><i class="fas fa-user-friends"></i> <span>Meus Dates</span></a></li>
                    <li class="nav-item"><a href="../php/editar_perfil.php"><i class="fas fa-user-edit"></i> <span>Editar Perfil</span></a></li>
                    <li class="nav-item"><a href="../php/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <section class="section-container">
                
                <div class="profile-header-common">
                    <div class="user-display">
                        <img src="<?php echo $userPhotoUrl; ?>" alt="Foto de <?php echo htmlspecialchars($user_data['nome']); ?>" class="profile-photo-common" onerror="this.onerror=null; this.src='../images/perfil.png';">
                        <span><?php echo htmlspecialchars($user_data['nome']); ?></span>
                    </div>
                    
                    <div class="compatibility-score-display">
                        <?php if ($compatibilidade_score == -1): ?>
                            <i class="fas fa-question-circle dynamic-heart-na" title="Um dos usuários (ou ambos) não respondeu aos dois questionários de essência."></i>
                            <span class="score-label">N/A</span>
                            <span class="score-sublabel">(Responda aos questionários)</span>
                        <?php else: ?>
                            <i class="fas fa-heart dynamic-heart" 
                               style="--fill-percent: <?php echo $compatibilidade_score; ?>%;" 
                               title="Compatibilidade de <?php echo $compatibilidade_score; ?>%">
                            </i>
                            <span class="score-label"><?php echo $compatibilidade_score; ?>% Compatíveis</span>
                            <span class="score-sublabel">(Baseado em 11 seções)</span>
                        <?php endif; ?>
                    </div>
                    <div class="user-display">
                        <img src="<?php echo $partnerPhotoUrl; ?>" alt="Foto de <?php echo htmlspecialchars($partner_data['nome']); ?>" class="profile-photo-common" onerror="this.onerror=null; this.src='../images/perfil.png';">
                        <span><?php echo htmlspecialchars($partner_data['nome']); ?></span>
                    </div>
                </div>

                <div class="shared-interests-container">
                    <h3 class="common-subtitle">O que vocês têm em comum na essência</h3>
                    <?php if (empty($interesses_compartilhados)): ?>
                        <div class="empty-state" style="padding: 20px 0; border: none;">
                            <i class="fas fa-comment-slash"></i>
                            <p>Vocês não têm interesses em comum registrados nos questionários.</p>
                        </div>
                    <?php else: ?>
                        <div class="tag-list-common">
                            <?php foreach ($interesses_compartilhados as $tag): ?>
                                <span class="tag-item-common"><i class="fas fa-check"></i> <?php echo htmlspecialchars($tag); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="section-header" style="margin-top: 40px;">
                    <h2>Lugares Ideais para vocês (Baseado na Essência)</h2>
                </div>
                <div class="common-places-list">
                    <?php if (empty($lugares_ideais)): ?>
                        <div class="empty-state">
                            <i class="fas fa-map-signs"></i>
                            <p>Não encontramos locais que combinam com suas essências em comum.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lugares_ideais as $local): ?>
                            <div class="common-place-card">
                                <?php 
                                    // CORREÇÃO IMAGEM
                                    $imgDisplay = $local['imagem_url'];
                                    if (strpos($imgDisplay, 'http') === false && strpos($imgDisplay, '../') === false) {
                                        $imgDisplay = '../' . ltrim($imgDisplay, '/');
                                    }
                                ?>
                                <img src="<?php echo htmlspecialchars($imgDisplay); ?>" alt="Imagem de <?php echo htmlspecialchars($local['titulo']); ?>">
                                <div class="common-place-info">
                                    <h3><?php echo htmlspecialchars($local['titulo']); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($local['descricao'], 0, 120)) . '...'; ?></p>
                                    <a href="<?php echo htmlspecialchars($local['link_botao']); ?>" target="_blank" class="btn-primary" style="padding: 8px 15px; font-size: 0.9rem;">
                                        <i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars($local['texto_botao']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="section-header" style="margin-top: 40px;">
                    <h2>Lugares Favoritados por Ambos</h2>
                </div>
                <div class="common-places-list">
                    <?php if (empty($lugares_favoritos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-heart-crack"></i>
                            <p>Vocês ainda não têm lugares favoritados em comum.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lugares_favoritos as $local): ?>
                            <div class="common-place-card">
                                 <?php 
                                    // CORREÇÃO IMAGEM
                                    $imgDisplayFav = $local['imagem_url'];
                                    if (strpos($imgDisplayFav, 'http') === false && strpos($imgDisplayFav, '../') === false) {
                                        $imgDisplayFav = '../' . ltrim($imgDisplayFav, '/');
                                    }
                                ?>
                                <img src="<?php echo htmlspecialchars($imgDisplayFav); ?>" alt="Imagem de <?php echo htmlspecialchars($local['titulo']); ?>">
                                <div class="common-place-info">
                                    <h3><?php echo htmlspecialchars($local['titulo']); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($local['descricao'], 0, 120)) . '...'; ?></p>
                                    <a href="<?php echo htmlspecialchars($local['link_botao']); ?>" target="_blank" class="btn-primary" style="padding: 8px 15px; font-size: 0.9rem;">
                                        <i class="fas fa-external-link-alt"></i> <?php echo htmlspecialchars($local['texto_botao']); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </section>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bellIconContainer = document.querySelector('.notification-icon-container');
            if (bellIconContainer) {
                // Lógica de notificação (opcional se não tiver o painel, mas mantém sem erro)
            }
        });
    </script>
</body>
</html>