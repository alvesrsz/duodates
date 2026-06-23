
<?php
session_start();

// Incluindo o seu arquivo de conexão existente
require_once '../conexao.php';

$usuario_logado = ['nome' => 'Usuário', 'email' => '', 'foto' => ''];
$locais = [];

try {
    $usuario_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0);

    // Dados do usuário
    if ($usuario_id > 0) {
        $stmt_user = $conn->prepare("SELECT nome, email, foto_perfil AS foto FROM usuarios WHERE id = ? LIMIT 1");
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

    // Buscar todos os locais com suas categorias
    $sql_lugares = "
        SELECT 
            l.id_local, l.titulo, l.descricao, l.imagem_url, l.local_info, l.horario_info, l.link_botao, l.texto_botao, 
            c.nome AS categoria_nome, c.icone_fa
        FROM locais l
        LEFT JOIN categorias c ON l.id_categoria = c.id_categoria
        ORDER BY l.titulo ASC
    ";
    
    $result_lugares = $conn->query($sql_lugares);
    if ($result_lugares && $result_lugares->num_rows > 0) {
        while ($row = $result_lugares->fetch_assoc()) {
            $locais[] = $row;
        }
    }

} catch (Exception $e) {
    $erro = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Todos os Locais - Duo Dates</title>
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

/* BODY GRID */
.body{display:grid;grid-template-columns:215px 1fr;overflow:hidden;height:100%}

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

/* MAIN CONTENT */
.main{background:var(--bg);padding:1.25rem;display:flex;flex-direction:column;gap:1.125rem;overflow-y:auto}
.header-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; background: var(--bg-white); padding: 15px 20px; border-radius: 10px; border: 1px solid var(--border); }
.header-section h1 { font-size: 1.2rem; color: var(--wine); }
.header-section span { font-size: 0.85rem; color: var(--text-mid); font-weight: 600; }

/* SEARCH BAR */
.search-container { margin-bottom: 15px; }
.search-input { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-white); color: var(--text-dark); font-family: inherit; font-size: 14px; outline: none; }
.search-input:focus { border-color: var(--wine); box-shadow: 0 0 0 2px var(--wine-pale); }

/* GRID DE LOCAIS */
.locais-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; padding-bottom: 20px; }
.local-card { background: var(--bg-white); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; }
.local-card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-color: var(--wine); }
.local-img { width: 100%; height: 160px; object-fit: cover; background: #e0e0e0; }
.local-body { padding: 15px; flex: 1; display: flex; flex-direction: column; }
.local-cat { font-size: 10px; font-weight: 700; color: var(--wine); text-transform: uppercase; letter-spacing: .05em; margin-bottom: 5px; display: flex; align-items: center; gap: 5px; }
.local-title { font-size: 15px; font-weight: 700; color: var(--text-dark); margin-bottom: 8px; line-height: 1.2; }
.local-desc { font-size: 11px; font-weight: 500; color: var(--text-mid); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; margin-bottom: 12px; }
.local-meta { margin-top: auto; display: flex; flex-direction: column; gap: 5px; border-top: 1px solid var(--border); padding-top: 10px; }
.meta-item { display: flex; align-items: flex-start; gap: 6px; font-size: 10px; color: var(--text-mid); }
.meta-item i { color: var(--wine); font-size: 12px; margin-top: 1px; }
.local-action { margin-top: 12px; display: block; text-align: center; background: var(--wine-pale); color: var(--wine); font-weight: 700; font-size: 12px; padding: 10px; border-radius: 6px; text-decoration: none; transition: background 0.2s; border: 1px solid var(--wine-border); }
.local-action:hover { background: var(--wine); color: #fff; }

.no-results { grid-column: 1 / -1; background: var(--bg-white); padding: 30px; text-align: center; border-radius: 10px; color: var(--text-mid); font-weight: 600; border: 1px solid var(--border); }

/* Responsivo */
@media (max-width: 768px) {
    .body { grid-template-columns: 1fr; }
    .sidenav { display: none; }
}
</style>
</head>
<body>
<div class="shell">

  <header class="topbar">
    <div class="logo">
      <a href="login-feito.php" class="logo" style="text-decoration:none;">
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
      <div class="tbb" title="Favoritos" onclick="window.location.href='favoritos.php'"><i class="ti ti-heart"></i></div>
    </div>
  </header>

  <div class="body">

    <nav class="sidenav">
      <div class="pblock">
        <div class="av" style="background:#E0E0E0; border-color:var(--wine-border);">
          <?php if (!empty($usuario_logado['foto'])): ?>
            <img src="<?= htmlspecialchars($usuario_logado['foto']) ?>" onerror="this.src='../images/iconeperfil.png'">
          <?php else: ?>
            <img src="../images/iconeperfil.png" style="width:100%;height:100%;object-fit:cover;">
          <?php endif; ?>
        </div>
        <div class="pname"><?= htmlspecialchars($usuario_logado['nome']) ?></div>
        <div class="pemail"><?= htmlspecialchars($usuario_logado['email']) ?></div>
      </div>
      <div class="nlabel">Menu</div>
      <div class="ni" onclick="window.location.href='login-feito.php'"><i class="ti ti-layout-dashboard"></i> Meu perfil</div>
      <div class="ni" onclick="window.location.href='mudar_essencia.php'"><i class="ti ti-sparkles"></i> Minha essência</div>
      <div class="ni" onclick="window.location.href='meus_dates.php'"><i class="ti ti-heart-handshake"></i> Meus dates</div>
      <div class="ni active" onclick="window.location.href='locais.php'"><i class="ti ti-map"></i> Locais</div>
      <div class="ni" onclick="window.location.href='editar_perfil_login_feito.php'"><i class="ti ti-user-edit"></i> Editar perfil</div>
      <div class="ndiv"></div>
      <div class="ni" onclick="window.location.href='logout.php'"><i class="ti ti-logout"></i> Sair</div>
    </nav>

    <main class="main">

      <?php if (isset($erro)): ?>
          <div style="background:#f8d7da; color:#842029; padding:15px; border-radius:8px; margin-bottom:15px;">
              Erro ao carregar locais: <?= htmlspecialchars($erro) ?>
          </div>
      <?php endif; ?>

      <div class="header-section">
          <h1><i class="ti ti-map"></i> Explorar Locais</h1>
          <span><?= count($locais) ?> locais encontrados</span>
      </div>

      <div class="search-container">
          <input type="text" id="searchInput" class="search-input" placeholder="Buscar local por nome, descrição ou categoria..." onkeyup="filtrarLocais()">
      </div>

      <div class="locais-grid" id="locaisGrid">
          <?php if (empty($locais)): ?>
              <div class="no-results">Nenhum local cadastrado no momento.</div>
          <?php else: ?>
              <?php foreach ($locais as $local): 
                  $img = !empty($local['imagem_url']) ? htmlspecialchars($local['imagem_url']) : '../images/placeholder-local.jpg';
                  if (strpos($img, 'http') !== 0 && strpos($img, '../') !== 0) {
                      $img = '../' . $img; 
                  }
              ?>
                  <div class="local-card" data-titulo="<?= strtolower(htmlspecialchars($local['titulo'])) ?>" data-cat="<?= strtolower(htmlspecialchars($local['categoria_nome'] ?? '')) ?>" data-desc="<?= strtolower(htmlspecialchars($local['descricao'])) ?>">
                      <img src="<?= $img ?>" alt="<?= htmlspecialchars($local['titulo']) ?>" class="local-img" onerror="this.src='../images/placeholder-local.jpg'">
                      <div class="local-body">
                          <div class="local-cat">
                              <i class="<?= htmlspecialchars($local['icone_fa'] ?? 'ti ti-map-pin') ?>"></i> 
                              <?= htmlspecialchars($local['categoria_nome'] ?? 'Sem categoria') ?>
                          </div>
                          <h3 class="local-title"><?= htmlspecialchars($local['titulo']) ?></h3>
                          <div class="local-desc" title="<?= htmlspecialchars($local['descricao']) ?>">
                              <?= htmlspecialchars($local['descricao']) ?>
                          </div>
                          
                          <div class="local-meta">
                              <?php if(!empty($local['local_info'])): ?>
                                  <div class="meta-item"><i class="ti ti-map-pin"></i> <span><?= htmlspecialchars($local['local_info']) ?></span></div>
                              <?php endif; ?>
                              <?php if(!empty($local['horario_info'])): ?>
                                  <div class="meta-item"><i class="ti ti-clock"></i> <span><?= htmlspecialchars($local['horario_info']) ?></span></div>
                              <?php endif; ?>
                          </div>

                          <a href="<?= !empty($local['link_botao']) ? htmlspecialchars($local['link_botao']) : '#' ?>" target="_blank" class="local-action">
                              <?= !empty($local['texto_botao']) ? htmlspecialchars($local['texto_botao']) : 'Ver Mais' ?>
                          </a>
                      </div>
                  </div>
              <?php endforeach; ?>
          <?php endif; ?>
      </div>

    </main>

  </div>
</div>

<script>
function filtrarLocais() {
    let input = document.getElementById('searchInput').value.toLowerCase();
    let cards = document.getElementsByClassName('local-card');
    let hasVisible = false;

    for (let i = 0; i < cards.length; i++) {
        let titulo = cards[i].getAttribute('data-titulo');
        let cat = cards[i].getAttribute('data-cat');
        let desc = cards[i].getAttribute('data-desc');

        if (titulo.includes(input) || cat.includes(input) || desc.includes(input)) {
            cards[i].style.display = "flex";
            hasVisible = true;
        } else {
            cards[i].style.display = "none";
        }
    }

    let grid = document.getElementById('locaisGrid');
    let noResultMsg = document.getElementById('msgNoResult');
    
    if (!hasVisible && input !== "") {
        if (!noResultMsg) {
            noResultMsg = document.createElement('div');
            noResultMsg.id = 'msgNoResult';
            noResultMsg.className = 'no-results';
            noResultMsg.innerText = 'Nenhum local encontrado para a sua busca.';
            grid.appendChild(noResultMsg);
        }
    } else if (noResultMsg) {
        noResultMsg.remove();
    }
}
</script>
</body>
</html>

```