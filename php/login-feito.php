<?php
// ── Sessão antes de qualquer output ───────────────────────────────────────────
session_start();

// ── Configurações do Banco ────────────────────────────────────────────────────
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "db_duodate";

// ── Variáveis globais ─────────────────────────────────────────────────────────
$places         = [];
$couples        = [];
$usuario_logado = ['nome' => 'Usuário', 'email' => '', 'foto' => ''];

// ── Helper: verifica se coluna existe em tabela ───────────────────────────────
function colunaExiste(mysqli $conn, string $tabela, string $coluna, string $banco): bool {
    $res = $conn->query("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = '{$banco}'
          AND TABLE_NAME   = '{$tabela}'
          AND COLUMN_NAME  = '{$coluna}'
    ");
    if (!$res) return false;
    $row = $res->fetch_assoc();
    return (int)($row['total'] ?? 0) > 0;
}

// ── Helper: calcula score de compatibilidade entre duas strings CSV ───────────
function calcularSubScore(?string $strA, ?string $strB): int {
    if (empty($strA) || empty($strB)) return 50;
    $arrA = array_map('trim', explode(',', $strA));
    $arrB = array_map('trim', explode(',', $strB));
    $intersecao = array_intersect($arrA, $arrB);
    $uniao = array_unique(array_merge($arrA, $arrB));
    if (count($uniao) === 0) return 50;
    return (int) round((count($intersecao) / count($uniao)) * 100);
}

try {
    // Sem MYSQLI_REPORT_STRICT para evitar exceção em queries defensivas
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");

    /* ══════════════════════════════════════════════════════════════════════════
     * 0. DADOS DO USUÁRIO LOGADO
     * ══════════════════════════════════════════════════════════════════════════ */
    $usuario_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0);

    // Detecta colunas disponíveis na tabela usuarios (Verificando foto_perfil)
    $tem_foto_user  = colunaExiste($conn, 'usuarios', 'foto_perfil',  $dbname);
    $tem_email_user = colunaExiste($conn, 'usuarios', 'email', $dbname);

    $select_user = "SELECT nome" 
                 . ($tem_email_user ? ", email" : "")
                 . ($tem_foto_user  ? ", foto_perfil AS foto"  : "")
                 . " FROM usuarios WHERE id = ? LIMIT 1";

    if ($usuario_id > 0) {
        $stmt_user = $conn->prepare($select_user);
        if ($stmt_user) {
            $stmt_user->bind_param('i', $usuario_id);
            $stmt_user->execute();
            $res_user = $stmt_user->get_result();
            if ($row_user = $res_user->fetch_assoc()) {
                $usuario_logado = [
                    'nome'  => $row_user['nome']  ?? 'Usuário',
                    'email' => $row_user['email'] ?? '',
                    'foto'  => $row_user['foto']  ?? '',
                ];
            }
            $stmt_user->close();
        }
    }

    /* ══════════════════════════════════════════════════════════════════════════
     * 1. LUGARES IDEAIS
     * ══════════════════════════════════════════════════════════════════════════ */
    $sql_lugares = "
        SELECT
            l.titulo       AS name,
            c.nome         AS cat,
            c.icone_fa     AS icon,
            FLOOR(60 + (RAND() * 38)) AS compat,
            l.descricao    AS `desc`
        FROM locais l
        LEFT JOIN categorias c ON l.id_categoria = c.id_categoria
        LIMIT 15
    ";
    $result_lugares = $conn->query($sql_lugares);
    if ($result_lugares && $result_lugares->num_rows > 0) {
        while ($row = $result_lugares->fetch_assoc()) {
            $places[] = $row;
        }
    }

    /* ══════════════════════════════════════════════════════════════════════════
     * 2. MATCHES / MELHORES AFINIDADES
     * ══════════════════════════════════════════════════════════════════════════ */
    $tem_foto_conn = colunaExiste($conn, 'usuarios', 'foto_perfil', $dbname);

    $foto_select = $tem_foto_conn
        ? ", COALESCE(u1.foto_perfil,'') AS foto_a, COALESCE(u2.foto_perfil,'') AS foto_b"
        : "";

    // QUERY ATUALIZADA: Filtra pelo usuário logado e ordena pela maior porcentagem (DESC)
    $sql_conexoes = "
        SELECT
            c.compatibilidade_score AS pct_geral,
            u1.nome AS nome_a, u1.pref_vibe AS vibe_a,
            u1.pref_atividade AS ativ_a, u1.pref_ambiente AS amb_a,
            u2.nome AS nome_b, u2.pref_vibe AS vibe_b,
            u2.pref_atividade AS ativ_b, u2.pref_ambiente AS amb_b
            {$foto_select}
        FROM conexoes c
        JOIN usuarios u1 ON c.id_solicitante = u1.id
        JOIN usuarios u2 ON c.id_solicitado  = u2.id
        WHERE c.status = 'aceito'
          AND (c.id_solicitante = ? OR c.id_solicitado = ?)
        ORDER BY c.compatibilidade_score DESC
        LIMIT 5
    ";

    if ($usuario_id > 0) {
        $stmt_conexoes = $conn->prepare($sql_conexoes);
        if ($stmt_conexoes) {
            $stmt_conexoes->bind_param("ii", $usuario_id, $usuario_id);
            $stmt_conexoes->execute();
            $result_conexoes = $stmt_conexoes->get_result();

            if ($result_conexoes && $result_conexoes->num_rows > 0) {
                while ($row = $result_conexoes->fetch_assoc()) {
                    $pnomeA    = explode(' ', $row['nome_a'])[0];
                    $pnomeB    = explode(' ', $row['nome_b'])[0];
                    $pct_geral = $row['pct_geral'] ? (int)$row['pct_geral'] : 50;

                    $score_estilo     = calcularSubScore($row['vibe_a'] ?? '', $row['vibe_b'] ?? '');
                    $score_hobbies    = calcularSubScore($row['ativ_a'] ?? '', $row['ativ_b'] ?? '');
                    $score_ritmo      = calcularSubScore($row['amb_a']  ?? '', $row['amb_b']  ?? '');
                    $score_comunicacao = (int) round(($score_estilo + $score_hobbies + $score_ritmo) / 3);

                    $couples[] = [
                        'initials' => strtoupper(substr($pnomeA, 0, 1) . substr($pnomeB, 0, 1)),
                        'name'     => $pnomeA . ' & ' . $pnomeB,
                        'pct'      => $pct_geral,
                        'foto_a'   => $row['foto_a'] ?? '',
                        'foto_b'   => $row['foto_b'] ?? '',
                        'dims'     => [
                            'estilo'      => $score_estilo,
                            'hobbies'     => $score_hobbies,
                            'ritmo'       => $score_ritmo,
                            'comunicacao' => $score_comunicacao,
                        ],
                    ];
                }
                
                // Garantia extra do PHP para ordenar do maior para o menor
                usort($couples, function($a, $b) {
                    return $b['pct'] <=> $a['pct'];
                });
            }
            $stmt_conexoes->close();
        }
    }

    $conn->close();

} catch (Exception $e) {
    echo "<div style='background:#fde8e8;color:#7a0000;padding:12px 20px;font-family:sans-serif;font-size:13px;border-bottom:1px solid #f5c0c0'>"
       . "<strong>Aviso técnico:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Fallbacks
if (empty($places)) {
    $places = [[
        'name'   => 'Nenhum local cadastrado',
        'cat'    => 'todos',
        'icon'   => 'fa-solid fa-map-pin',
        'compat' => 0,
        'desc'   => 'Cadastre locais no banco de dados',
    ]];
}
if (empty($couples)) {
    $couples = [[
        'initials' => '--',
        'name'     => 'Sem conexões',
        'pct'      => 0,
        'foto_a'   => '',
        'foto_b'   => '',
        'dims'     => ['estilo' => 0, 'hobbies' => 0, 'ritmo' => 0, 'comunicacao' => 0],
    ]];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Duo Dates</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#770820;
  --bg-card:#F7F0E8;
  --bg-white:#F7F0E8;
  --wine:#770820;
  --wine-mid:#770820;
  --wine-pale:#F5E6E8;
  --wine-border:#770820;
  --text-dark:#2A1A1E;
  --text-mid:#5A3A40;
  --text-light:#770820;
  --border:#D0BAA8;
}
html,body{height:100%;font-family:'Montserrat',sans-serif;font-weight:500;background:var(--bg);color:var(--text-dark);overflow:hidden}
.shell{display:grid;grid-template-rows:58px 1fr;height:100vh}

/* TOPBAR */
.topbar{background:var(--bg-white);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 1.5rem}
.logo{display:flex;align-items:center;gap:10px;text-decoration:none}
.logo-text{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:700;color:var(--wine);}
.tc{display:flex;gap:2px}
.tbb{width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text-mid);font-size:19px;cursor:pointer;transition:background .15s,color .15s}
.tbb:hover{background:var(--wine-pale);color:var(--wine)}
.tr{display:flex;gap:3px}
.notif{position:relative}
.notif::after{content:'';position:absolute;top:6px;right:6px;width:6px;height:6px;background:var(--wine);border-radius:50%;border:1.5px solid var(--bg-white)}

/* BODY GRID */
.body{display:grid;grid-template-columns:215px 1fr 260px;overflow:hidden;height:100%}

/* SIDENAV */
.sidenav{background:var(--bg-white);border-right:1px solid var(--border);padding:1.25rem 1rem;display:flex;flex-direction:column;overflow-y:auto}
.pblock{text-align:center;margin-bottom:1.25rem;padding-bottom:1.125rem;border-bottom:1px solid var(--border)}
.av{width:68px;height:68px;border-radius:50%;background:var(--wine-pale);border:2.5px solid var(--wine-border);margin:0 auto 9px;display:flex;align-items:center;justify-content:center;font-size:24px;color:var(--wine);font-weight:600;overflow:hidden;position:relative}
.av img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.pname{font-size:14px;font-weight:700;color:var(--text-dark)}
.pemail{font-size:10px;color:var(--text-light);margin-top:2px;font-weight:500}
.nlabel{font-size:9px;font-weight:700;color:var(--text-light);letter-spacing:.09em;text-transform:uppercase;padding:0 8px;margin:8px 0 3px}
.ni{display:flex;align-items:center;gap:8px;padding:8px 9px;border-radius:8px;font-size:13px;font-weight:600;color:var(--text-mid);cursor:pointer;transition:background .15s,color .15s;margin-bottom:1px}
.ni i{font-size:16px}
.ni:hover,.ni.active{background:var(--wine-pale);color:var(--wine)}
.ni.active{font-weight:700}
.ndiv{height:1px;background:var(--border);margin:8px 0}

/* MAIN */
.main{background:var(--bg);padding:1.25rem;display:flex;flex-direction:column;gap:1.125rem;overflow-y:auto}
.slabel{font-size:9px;font-weight:700;color:var(--text-light);letter-spacing:.1em;text-transform:uppercase;margin-bottom:.45rem}
.card{background:var(--bg-white);border-radius:13px;border:1px solid var(--border);padding:1.125rem}
.ch{display:flex;align-items:center;justify-content:space-between;margin-bottom:.875rem}
.ctitle{font-size:15px;font-weight:700;color:var(--text-dark)}
.badge{background:var(--wine-pale);color:var(--wine);font-size:10px;padding:3px 9px;border-radius:20px;font-weight:700;border:1px solid var(--wine-border)}

/* COMPAT CARDS */
.cgrid{display:grid;grid-template-columns:repeat(5,1fr);gap:8px}
.ccard{background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:10px;cursor:pointer;transition:border-color .15s,background .15s;position:relative}
.ccard:hover{border-color:var(--wine-border);background:var(--wine-pale)}
.ccard.selected{border-color:var(--wine);background:var(--wine-pale)}
.ccav{width:100%;height:62px;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-bottom:7px;overflow:hidden}
.ccav img{width:100%;height:100%;object-fit:cover;border-radius:8px;display:block}
.cpct{position:absolute;top:8px;right:8px;background:var(--wine);color:#fff;font-size:11px;font-weight:700;letter-spacing:.02em;padding:1px 6px;border-radius:20px}
.cname{font-size:11px;font-weight:700;color:var(--text-dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cbar{height:3px;background:var(--border);border-radius:2px;margin-top:5px;overflow:hidden}
.cfill{height:100%;background:var(--wine);border-radius:2px}

/* DETAIL */
.dh{display:flex;align-items:center;gap:13px;margin-bottom:.875rem}
.pavs{display:flex}
.pav{width:40px;height:40px;border-radius:50%;border:2px solid var(--bg-white);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;color:#fff;overflow:hidden}
.pav img{width:100%;height:100%;object-fit:cover;border-radius:50%;display:block}
.pav+.pav{margin-left:-11px}
.sbig{font-size:34px;font-weight:800;letter-spacing:.01em;color:var(--wine)}
.ssub{font-size:11px;font-weight:600;color:var(--text-light);margin-top:1px}
.dgrid{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.dim{background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:9px}
.dlbl{font-size:10px;font-weight:600;color:var(--text-light);display:flex;align-items:center;gap:4px;margin-bottom:4px}
.dlbl i{font-size:12px;color:var(--wine)}
.dval{font-size:15px;font-weight:700;letter-spacing:.01em;color:var(--text-dark)}
.dbar{height:3px;background:var(--border);border-radius:2px;margin-top:5px;overflow:hidden}
.dbarfill{height:100%;background:var(--wine);border-radius:2px}

/* PLACES */
.pfilters{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:.75rem}
.chip{display:flex;align-items:center;gap:4px;background:var(--bg-white);border:1px solid var(--border);border-radius:20px;padding:5px 10px;font-size:10px;font-weight:600;color:var(--text-mid);cursor:pointer;transition:all .15s}
.chip i{font-size:12px}
.chip:hover,.chip.active{background:var(--wine-pale);border-color:var(--wine-border);color:var(--wine)}
.pgrid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
.pplace{background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:10px;display:flex;gap:9px;align-items:flex-start;cursor:pointer;transition:border-color .15s,background .15s}
.pplace:hover{border-color:var(--wine-border);background:var(--wine-pale)}
.picon{width:40px;height:40px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:19px;color:var(--wine);background:var(--wine-pale);border:1px solid var(--wine-border)}
.pinfo{flex:1;min-width:0}
.pname2{font-size:11px;font-weight:700;color:var(--text-dark);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pdesc{font-size:9px;font-weight:500;color:var(--text-light);margin-bottom:5px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden}
.pcompat{display:flex;align-items:center;gap:5px}
.ppct{font-size:13px;font-weight:700;letter-spacing:.01em;color:var(--wine);min-width:28px}
.pbar{flex:1;height:3px;background:var(--border);border-radius:2px;overflow:hidden}
.pfill{height:100%;background:var(--wine);border-radius:2px}

/* RIGHT PANEL */
.rp{background:var(--bg-white);border-left:1px solid var(--border);padding:1.125rem;overflow-y:auto}

/* =========================================================
   AJUSTES PARA A SEÇÃO DE EVENTOS E CALENDÁRIO 
   ========================================================= */
.events-section { margin-bottom: 1.625rem; }
.matches-header h4 { font-size: 14px; font-weight: 700; color: var(--wine); letter-spacing: .06em; display: flex; align-items: center; gap: 7px; margin-bottom: 0.8rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); margin-top: 0; }

/* Lista de Eventos com scroll para ficar como na imagem */
.events-list { max-height: 350px; overflow-y: auto; padding-right: 5px; }
.events-list::-webkit-scrollbar { width: 4px; }
.events-list::-webkit-scrollbar-thumb { background: #D0BAA8; border-radius: 4px; }

/* Item Individual de Evento */
.event-item { display: flex; justify-content: space-between; align-items: center; text-decoration: none; padding: 12px 0; border-bottom: 1px solid #E5D5C5; transition: opacity 0.2s; }
.event-item:last-child { border-bottom: none; }
.event-item:hover { opacity: 0.7; }
.event-details { flex: 1; }
.event-name { font-size: 13px; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; display: block; }
.event-info { font-size: 11px; font-weight: 500; color: #7A6A6E; display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.event-link-icon { color: var(--text-mid); font-size: 14px; margin-left: 10px; }

/* Botão "Ver calendário" */
.lbtn { display:inline-flex; align-items:center; justify-content:center; gap:6px; margin-top:1rem; font-size:13px; font-weight:700; color:var(--wine); cursor:pointer; text-decoration:none; padding: 10px 15px; border-radius: 8px; background: var(--wine-pale); transition: background 0.2s; width: 100%; }
.lbtn:hover { background: #e8d0d4; }
.lbtn i { font-size: 16px; }
</style>
</head>
<body>
<div class="shell">

  <header class="topbar">
    <div class="logo">
      <a href="../index.php" class="logo" style="text-decoration:none;">
        <img src="../images/logoduodates.png" alt="Duo Dates" style="height:28px;width:auto;">
        <span class="logo-text">Duo Dates</span>
      </a>
    </div>
    <div class="tc">
      <div class="tbb" title="Lugares" onclick="window.location.href='meuslugaresideais.php'"><i class="ti ti-map-pin"></i></div>
      <div class="tbb" title="Agenda" onclick="window.location.href='meu_calendario.php'"><i class="ti ti-calendar"></i></div>
      <div class="tbb" title="Matches" onclick="window.location.href='meus_dates.php'"><i class="ti ti-users"></i></div>
      <div class="tbb" title="Novo" onclick="window.location.href='criar_conexao.php'"><i class="ti ti-plus"></i></div>
    </div>
    <div class="tr">
      <div class="tbb notif" title="Favoritos" onclick="window.location.href='favoritos.php'"><i class="ti ti-heart"></i></div>
      <div class="tbb notif"><i class="ti ti-bell"></i></div>
    </div>
  </header>

  <div class="body">

    <nav class="sidenav">
      <div class="pblock">
        <div class="av" style="background:#E0E0E0; border-color:var(--wine-border);">
          <?php if (!empty($usuario_logado['foto'])): ?>
            <img src="<?= htmlspecialchars($usuario_logado['foto']) ?>"
                 alt="Foto de <?= htmlspecialchars($usuario_logado['nome']) ?>"
                 onerror="this.src='../images/iconeperfil.png'">
          <?php else: ?>
            <img src="../images/iconeperfil.png" alt="Ícone de Usuário Padrão" style="width:100%;height:100%;object-fit:cover;">
          <?php endif; ?>
        </div>
        <div class="pname"><?= htmlspecialchars($usuario_logado['nome']) ?></div>
        <div class="pemail"><?= htmlspecialchars($usuario_logado['email']) ?></div>
      </div>
      <div class="nlabel">Menu</div>
      <div class="ni active" onclick="window.location.href='login-feito.php'"><i class="ti ti-layout-dashboard"></i> Meu perfil</div>
      <div class="ni" onclick="window.location.href='mudar_essencia.php'"><i class="ti ti-sparkles"></i> Minha essência</div>
      <div class="ni" onclick="window.location.href='meus_dates.php'"><i class="ti ti-heart-handshake"></i> Meus dates</div>
      <div class="ni" onclick="window.location.href='todoslocais.php'"><i class="ti ti-map"></i> Locais</div>
      <div class="ni" onclick="window.location.href='editar_perfil_login_feito.php'"><i class="ti ti-user-edit"></i> Editar perfil</div>
      <div class="ndiv"></div>
      <div class="ni" onclick="window.location.href='logout.php'"><i class="ti ti-logout"></i> Sair</div>
    </nav>

    <main class="main">

      <div>
        <p class="slabel">Compatibilidade — casais em destaque</p>
        <div class="card">
          <div class="ch">
            <span class="ctitle">Minhas compatibilidades</span>
            <span class="badge">Atualizado hoje</span>
          </div>
          <div class="cgrid" id="cg"></div>
        </div>
      </div>

      <div class="card" id="dp">
        <div class="dh">
          <div class="pavs">
            <div class="pav" style="background:#E0E0E0" id="a1">
              <?php if (!empty($usuario_logado['foto'])): ?>
                <img src="<?= htmlspecialchars($usuario_logado['foto']) ?>"
                     alt="User"
                     onerror="this.src='../images/iconeperfil.png'">
              <?php else: ?>
                <img src="../images/iconeperfil.png" alt="Sem foto" style="width:100%;height:100%;object-fit:cover;">
              <?php endif; ?>
            </div>
            <div class="pav" style="background:#E0E0E0" id="a2">
                <img src="../images/iconeperfil.png" alt="?" style="width:100%;height:100%;object-fit:cover;">
            </div>
          </div>
          <div>
            <div class="sbig" id="bs">—</div>
            <div class="ssub" id="ss">Selecione um casal para ver os detalhes</div>
          </div>
        </div>
        <div class="dgrid" id="dg"></div>
      </div>

      <div>
        <p class="slabel">Lugares ideais — compatibilidade com seu perfil</p>
        <div class="card">
          <div class="ch">
            <span class="ctitle">Lugares ideais</span>
            <span class="badge">Brasília · DF</span>
          </div>
          
          <div class="pfilters" id="pf">
            <div class="chip active" data-cat="todos"><i class="ti ti-star"></i> Todos</div>
            <div class="chip" data-cat="Restaurantes e Cafés"><i class="fa-solid fa-utensils"></i> Gastronomia</div>
            <div class="chip" data-cat="Aventuras e Natureza"><i class="fas fa-person-hiking"></i> Natureza</div>
            <div class="chip" data-cat="Experiências Culturais"><i class="fa-solid fa-masks-theater"></i> Cultura</div>
            <div class="chip" data-cat="Encontros com Música"><i class="fa-solid fa-music"></i> Shows</div>
            <div class="chip" data-cat="Atividades Diferentes"><i class="fa-solid fa-puzzle-piece"></i> Diferentes</div>
          </div>
          
          <div class="pgrid" id="pg"></div>
        </div>
      </div>

    </main>

    <aside class="rp">
      <div class="events-section">
        <div class="matches-header">
          <h4><i class="fas fa-ticket-alt"></i> Eventos em Brasília</h4>
        </div>
        <?php @include '../php/buscar_ticketmaster.php'; ?>
        <div class="events-list"> 
            <?php if (isset($eventosFormatados['error'])): ?>
                <div class="event-item error-message" style="color:#5A3A40; text-align:center;"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($eventosFormatados['error']); ?></div>
            <?php elseif (isset($eventosFormatados['info'])): ?>
                 <div class="event-item info-message" style="color:#5A3A40; text-align:center;"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($eventosFormatados['info']); ?></div>
            <?php elseif (!empty($eventosFormatados)): ?>
                <?php foreach ($eventosFormatados as $evt): ?>
                    <a href="<?php echo htmlspecialchars($evt['url']); ?>" target="_blank" class="event-item">
                        <div class="event-details">
                            <span class="event-name"><?php echo htmlspecialchars($evt['nome']); ?></span>
                            <span class="event-info"><i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($evt['dataHora']); ?> &nbsp;|&nbsp; <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['local']); ?></span>
                        </div>
                        <div class="event-link-icon"><i class="fas fa-external-link-alt"></i></div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                 <div class="event-item info-message" style="color:#5A3A40; text-align:center;"><i class="fas fa-info-circle"></i> Não foi possível carregar os eventos.</div>
            <?php endif; ?>
        </div>
      </div> 
      <a href="meu_calendario.php" class="lbtn">
         <i class="far fa-calendar-alt"></i> Ver calendário completo
      </a>
    </aside>

  </div>
</div>

<script>
const couples = <?php echo json_encode($couples); ?>;
const places = <?php echo json_encode($places); ?>;

const dIcons={estilo:'ti-shirt',hobbies:'ti-activity',ritmo:'ti-clock',comunicacao:'ti-message-circle'};
const dLabels={estilo:'Estilo de vida',hobbies:'Hobbies',ritmo:'Ritmo do date',comunicacao:'Comunicação'};

const defaultImg = '../images/iconeperfil.png';

const pg = document.getElementById('pg');
function renderPlaces(cat){
  pg.innerHTML='';
  const list = cat === 'todos' ? places : places.filter(p => p.cat === cat);
  
  if(list.length === 0){
      pg.innerHTML = '<p style="grid-column: 1 / -1; text-align:center; padding: 20px; font-size:12px; color:var(--text-light)">Nenhum local encontrado nessa categoria.</p>';
      return;
  }

  list.forEach(p => {
    const el = document.createElement('div');
    el.className = 'pplace';
    el.innerHTML = `
      <div class="picon"><i class="${p.icon}"></i></div>
      <div class="pinfo">
        <div class="pname2">${p.name}</div>
        <div class="pdesc" title="${p.desc}">${p.desc}</div>
        <div class="pcompat">
          <span class="ppct">${p.compat}%</span>
          <div class="pbar">
            <div class="pfill" style="width:${p.compat}%"></div>
          </div>
        </div>
      </div>`;
    pg.appendChild(el);
  });
}
renderPlaces('todos');

document.getElementById('pf').addEventListener('click', e => {
  const chip = e.target.closest('.chip');
  if(!chip) return;
  document.querySelectorAll('#pf .chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  renderPlaces(chip.dataset.cat);
});

const cg = document.getElementById('cg');
const bs = document.getElementById('bs');
const ss = document.getElementById('ss');
const dg = document.getElementById('dg');
const a2 = document.getElementById('a2');

couples.forEach((c,i)=>{
  const shades = ['#770820','#8C1228','#6B1020','#9E1832','#5C0A16'];
  const el = document.createElement('div');
  el.className = 'ccard';

  // Montagem do avatar do casal com ícone de perfil cinza como fallback
  let srcA = c.foto_a ? c.foto_a : defaultImg;
  let srcB = c.foto_b ? c.foto_b : defaultImg;

  let avatarHtml = `<div style="display:flex;width:100%;height:100%;border-radius:8px;overflow:hidden;background:#E0E0E0;">
      <img src="${srcA}" alt="" style="flex:1;object-fit:cover;display:block" onerror="this.src='${defaultImg}'">
      <img src="${srcB}" alt="" style="flex:1;object-fit:cover;display:block;border-left:2px solid #fff" onerror="this.src='${defaultImg}'">
  </div>`;

  el.innerHTML = `
    <div class="ccav" style="background:#E0E0E0">
      ${avatarHtml}
    </div>
    <span class="cpct">${c.pct}%</span>
    <div class="cname">${c.name}</div>
    <div class="cbar"><div class="cfill" style="width:${c.pct*2}%"></div></div>
  `;
  el.addEventListener('click', () => sel(i, el, shades[i]));
  cg.appendChild(el);
});

function sel(i, el, shade){
  document.querySelectorAll('.ccard').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  const c = couples[i];

  // Avatar do parceiro no painel de detalhe
  let srcB = c.foto_b ? c.foto_b : defaultImg;
  
  a2.innerHTML = `<img src="${srcB}" alt="Perfil" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;background:#E0E0E0;" onerror="this.src='${defaultImg}'">`;
  a2.style.background = '#E0E0E0';
  
  bs.textContent = c.pct + '%';
  ss.textContent = c.name + ' — compatibilidade geral';
  dg.innerHTML = '';
  Object.keys(c.dims).forEach(k => {
    const v = c.dims[k];
    const d = document.createElement('div');
    d.className = 'dim';
    d.innerHTML = `
      <div class="dlbl"><i class="ti ${dIcons[k]}"></i>${dLabels[k]}</div>
      <div class="dval">${v}%</div>
      <div class="dbar"><div class="dbarfill" style="width:${v}%"></div></div>
    `;
    dg.appendChild(d);
  });
}

document.querySelectorAll('.ni').forEach(n => {
  n.addEventListener('click', () => {
    document.querySelectorAll('.ni').forEach(x => x.classList.remove('active'));
    n.classList.add('active');
  });
});
</script>
</body>
</html>