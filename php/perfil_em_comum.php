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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/shell.css">
    <link rel="stylesheet" href="../css/meus_dates.css">
    <style>
        /* ── AI HERO SECTION ── */
        .ai-hero-section {
            position: relative;
            background: linear-gradient(135deg, #5d0819 0%, #8c0f2a 55%, #b52040 100%);
            border-radius: 20px;
            padding: 36px 32px 28px;
            margin: 28px 0 36px;
            overflow: hidden;
            color: #fff;
        }
        .ai-orb {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            pointer-events: none;
        }
        .ai-orb-1 { width: 220px; height: 220px; top: -70px; right: -50px; }
        .ai-orb-2 { width: 130px; height: 130px; bottom: -40px; left: 10px; }
        .ai-orb-3 { width: 80px;  height: 80px;  top: 20px;  left: 38%; background: rgba(255,255,255,0.04); }

        .ai-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            margin-bottom: 14px;
        }
        .ai-hero-title {
            font-size: 1.65rem;
            font-weight: 800;
            margin: 0 0 8px;
            line-height: 1.2;
        }
        .ai-hero-subtitle {
            font-size: 0.9rem;
            opacity: 0.82;
            line-height: 1.65;
            max-width: 560px;
            margin: 0 0 26px;
        }

        /* ── BOTÃO ── */
        .btn-ai-recomendar {
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            color: #8c0f2a;
            border: none;
            padding: 13px 30px;
            border-radius: 50px;
            font-size: 0.97rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 20px rgba(0,0,0,0.25);
        }
        .btn-ai-recomendar:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.35); }
        .btn-ai-recomendar:disabled { opacity: 0.65; cursor: not-allowed; }
        .btn-shimmer {
            position: absolute;
            top: 0; left: -100%;
            width: 55%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.55), transparent);
            animation: shimmer 2.8s infinite;
        }
        @keyframes shimmer { 0% { left: -100%; } 100% { left: 200%; } }

        /* ── LOADING ── */
        .ai-loading {
            display: none;
            align-items: center;
            gap: 14px;
            margin-top: 22px;
            color: rgba(255,255,255,0.92);
            font-weight: 600;
            font-size: 0.93rem;
        }
        .ai-loading.visivel { display: flex; }
        .ai-dots { display: flex; gap: 5px; }
        .ai-dots span {
            width: 9px; height: 9px;
            background: #fff;
            border-radius: 50%;
            animation: bounce 1.1s infinite ease-in-out;
        }
        .ai-dots span:nth-child(2) { animation-delay: 0.18s; }
        .ai-dots span:nth-child(3) { animation-delay: 0.36s; }
        @keyframes bounce { 0%,60%,100% { transform: translateY(0); opacity: 0.6; } 30% { transform: translateY(-9px); opacity: 1; } }

        /* ── ERRO ── */
        .ai-erro {
            display: none;
            background: rgba(255,255,255,0.13);
            border-left: 4px solid rgba(255,200,200,0.8);
            padding: 12px 16px;
            border-radius: 8px;
            color: #ffe0e0;
            font-size: 0.88rem;
            margin-top: 16px;
        }
        .ai-erro.visivel { display: block; }

        /* ── CARDS ── */
        .ai-cards-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 14px;
            margin-top: 26px;
        }
        .ai-card {
            background: rgba(255,255,255,0.11);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 22px 20px;
            color: #fff;
            opacity: 0;
            transform: translateY(18px);
            transition: opacity 0.5s ease, transform 0.5s ease, background 0.2s, box-shadow 0.2s;
        }
        .ai-card.visivel { opacity: 1; transform: translateY(0); }
        .ai-card:hover { background: rgba(255,255,255,0.18); box-shadow: 0 10px 36px rgba(0,0,0,0.25); transform: translateY(-5px); }
        .ai-card:nth-child(1) { transition-delay: 0s; }
        .ai-card:nth-child(2) { transition-delay: 0.15s; }
        .ai-card:nth-child(3) { transition-delay: 0.3s; }

        .ai-card-accent {
            height: 3px;
            border-radius: 3px;
            margin-bottom: 16px;
        }
        #card-analise  .ai-card-accent { background: #fbbf24; }
        #card-sugestao .ai-card-accent { background: #34d399; }
        #card-evento   .ai-card-accent { background: #60a5fa; }

        .ai-card-icon-wrap {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: rgba(255,255,255,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 12px;
        }
        .ai-card-titulo {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            opacity: 0.65;
            margin-bottom: 8px;
        }
        .ai-card-texto {
            font-size: 0.9rem;
            line-height: 1.7;
            opacity: 0.95;
        }
    </style>
</head>
<body>
<div class="shell">

  <header class="topbar">
    <a href="../index.php" class="logo">
      <img src="../images/logoduodates.png" alt="Duo Dates" style="height:28px;width:auto;">
      <span class="logo-text">Duo Dates</span>
    </a>
    <div class="tc">
      <div class="tbb" title="Lugares" onclick="window.location.href='meuslugaresideais.php'"><i class="ti ti-map-pin"></i></div>
      <div class="tbb" title="Agenda" onclick="window.location.href='meu_calendario.php'"><i class="ti ti-calendar"></i></div>
      <div class="tbb" title="Meus Dates" onclick="window.location.href='meus_dates.php'"><i class="ti ti-users"></i></div>
      <div class="tbb" title="Adicionar Local" onclick="window.location.href='adicionar-lugar.php'"><i class="ti ti-plus"></i></div>
    </div>
    <div class="tr">
      <div class="tbb" title="Favoritos" onclick="window.location.href='favoritos.php'"><i class="ti ti-heart"></i></div>
      <div class="tbb" title="Notificações"><i class="ti ti-bell"></i></div>
    </div>
  </header>

  <div class="body">

    <nav class="sidenav">
      <div class="pblock">
        <div class="av">
          <img src="<?php echo $userPhotoUrl; ?>" alt="Foto" onerror="this.onerror=null;this.src='../images/iconeperfil.png';">
        </div>
        <div class="pname"><?php echo htmlspecialchars($user_data['nome']); ?></div>
        <div class="pemail"><?php echo htmlspecialchars($user_data['email']); ?></div>
      </div>
      <div class="nlabel">Menu</div>
      <div class="ni" onclick="window.location.href='login-feito.php'"><i class="ti ti-layout-dashboard"></i> Meu perfil</div>
      <div class="ni" onclick="window.location.href='mudar_essencia.php'"><i class="ti ti-sparkles"></i> Minha essência</div>
      <div class="ni active" onclick="window.location.href='meus_dates.php'"><i class="ti ti-heart-handshake"></i> Meus dates</div>
      <div class="ni" onclick="window.location.href='todoslocais.php'"><i class="ti ti-map"></i> Locais</div>
      <div class="ni" onclick="window.location.href='editar_perfil_login_feito.php'"><i class="ti ti-user-edit"></i> Editar perfil</div>
      <div class="ndiv"></div>
      <div class="ni" onclick="window.location.href='logout.php'"><i class="ti ti-logout"></i> Sair</div>
    </nav>

    <main class="main">
      <div class="card">
                
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

                <!-- === SEÇÃO DE RECOMENDAÇÃO POR IA === -->
                <div class="ai-hero-section">
                    <div class="ai-orb ai-orb-1"></div>
                    <div class="ai-orb ai-orb-2"></div>
                    <div class="ai-orb ai-orb-3"></div>

                    <div class="ai-badge"><i class="fas fa-robot"></i> Inteligência Artificial</div>
                    <h2 class="ai-hero-title">Recomendação Personalizada para vocês</h2>
                    <p class="ai-hero-subtitle">Nossa IA analisa os perfis de vocês dois, os interesses em comum e eventos disponíveis em Brasília para criar a sugestão perfeita de date.</p>

                    <button class="btn-ai-recomendar" id="btn-recomendar">
                        <span class="btn-shimmer"></span>
                        <i class="fas fa-wand-magic-sparkles"></i>
                        <span>Gerar Recomendação com IA</span>
                    </button>

                    <div class="ai-loading" id="ai-loading">
                        <div class="ai-dots"><span></span><span></span><span></span></div>
                        Analisando perfis e buscando eventos...
                    </div>

                    <div class="ai-erro" id="ai-erro"></div>

                    <div class="ai-cards-wrapper">
                        <div class="ai-card" id="card-analise">
                            <div class="ai-card-accent"></div>
                            <div class="ai-card-icon-wrap">💞</div>
                            <div class="ai-card-titulo">Análise do Casal</div>
                            <div class="ai-card-texto" id="texto-analise"></div>
                        </div>
                        <div class="ai-card" id="card-sugestao">
                            <div class="ai-card-accent"></div>
                            <div class="ai-card-icon-wrap">🗓️</div>
                            <div class="ai-card-titulo">Sugestão de Date</div>
                            <div class="ai-card-texto" id="texto-sugestao"></div>
                        </div>
                        <div class="ai-card" id="card-evento">
                            <div class="ai-card-accent"></div>
                            <div class="ai-card-icon-wrap">🎟️</div>
                            <div class="ai-card-titulo">Evento em Destaque</div>
                            <div class="ai-card-texto" id="texto-evento"></div>
                        </div>
                    </div>
                </div>
                <!-- === FIM SEÇÃO IA === -->

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

      </div><!-- .card -->
    </main>

    <aside class="rp">
      <div class="rp-section">
        <div class="rp-title"><i class="fas fa-ticket-alt"></i> Próximos Eventos</div>
        <?php @include '../php/buscar_ticketmaster.php'; ?>
        <div class="events-list">
          <?php if (!empty($eventosFormatados) && !isset($eventosFormatados['error']) && !isset($eventosFormatados['info'])): ?>
            <?php foreach ($eventosFormatados as $evt): ?>
              <a href="<?php echo htmlspecialchars($evt['url']); ?>" target="_blank" class="event-item">
                <div class="event-details">
                  <span class="event-name"><?php echo htmlspecialchars($evt['nome']); ?></span>
                  <span class="event-info"><i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($evt['dataHora']); ?></span>
                </div>
                <div class="event-link-icon"><i class="fas fa-external-link-alt"></i></div>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="event-item" style="color:var(--text-mid);font-size:.82rem">Não foi possível carregar os eventos.</div>
          <?php endif; ?>
        </div>
      </div>
      <a href="../php/meu_calendario.php" class="lbtn"><i class="ti ti-calendar"></i> Ver calendário completo</a>
    </aside>

  </div>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('btn-recomendar');
            const loading = document.getElementById('ai-loading');
            const erroDiv = document.getElementById('ai-erro');
            const cards = document.querySelectorAll('.ai-card');

            btn.addEventListener('click', function () {
                btn.disabled = true;
                loading.classList.add('visivel');
                erroDiv.classList.remove('visivel');
                cards.forEach(c => c.classList.remove('visivel'));

                const formData = new FormData();
                formData.append('partner_id', '<?php echo $partner_id; ?>');
                formData.append('id_conexao', '<?php echo $id_conexao; ?>');

                fetch('../php/recomendar_dates.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    loading.classList.remove('visivel');

                    if (data.erro) {
                        erroDiv.textContent = '⚠️ ' + data.erro;
                        erroDiv.classList.add('visivel');
                        btn.disabled = false;
                        return;
                    }

                    document.getElementById('texto-analise').textContent = data.analise;
                    document.getElementById('texto-sugestao').textContent = data.sugestao;
                    document.getElementById('texto-evento').textContent = data.evento;

                    document.getElementById('card-analise').classList.add('visivel');
                    document.getElementById('card-sugestao').classList.add('visivel');
                    document.getElementById('card-evento').classList.add('visivel');

                    const label = btn.querySelector('span:last-child');
                    if (label) label.textContent = 'Gerar Nova Recomendação';
                    btn.disabled = false;
                })
                .catch(() => {
                    loading.classList.remove('visivel');
                    erroDiv.textContent = '⚠️ Erro de conexão. Verifique a internet e tente novamente.';
                    erroDiv.classList.add('visivel');
                    btn.disabled = false;
                });
            });
        });
    </script>
</body>
</html>