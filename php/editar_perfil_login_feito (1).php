<?php
session_start();
include '../conexao.php';

// Proteção 1: Se o usuário não estiver logado, vai para o login.
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login-feito.php');
    exit();
}

$id_usuario_logado = $_SESSION['user_id'];

// --- LÓGICA DE PROCESSAMENTO (SÓ EXECUTA QUANDO O FORMULÁRIO É ENVIADO) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $novo_nome = trim($_POST['nome']);
    $caminho_foto = null;

    if (empty($novo_nome)) {
        $_SESSION['error_message'] = "O nome de usuário não pode ficar em branco.";
        header('Location: ../php/editar_perfil_login_feito.php');
        exit();
    }

    // Lógica para o Upload da Foto
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
            $_SESSION['error_message'] = "Erro de servidor: O diretório de uploads não existe ou não tem permissão de escrita.";
            header('Location: ../php/editar_perfil_login_feito.php');
            exit();
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['foto_perfil']['type'], $allowed_types)) {
            $file_extension = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
            $nome_unico = 'user_' . $id_usuario_logado . '_' . time() . '.' . $file_extension;
            $caminho_foto = $upload_dir . $nome_unico;

            if (!move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $caminho_foto)) {
                $_SESSION['error_message'] = "Erro ao salvar a nova foto.";
                header('Location: ../php/editar_perfil_login_feito.php');
                exit();
            }
        } else {
            $_SESSION['error_message'] = "Formato de arquivo não permitido. Use JPG, PNG ou GIF.";
            header('Location: ../php/editar_perfil_login_feito.php');
            exit();
        }
    }

    // Atualização no Banco de Dados
    $sql_parts = [];
    $params = [];
    $types = "";

    $sql_parts[] = "nome = ?";
    $params[] = $novo_nome;
    $types .= "s";

    if ($caminho_foto !== null) {
        $sql_parts[] = "foto_perfil = ?";
        $params[] = $caminho_foto;
        $types .= "s";
    }

    if (count($sql_parts) > 0) {
        $params[] = $id_usuario_logado;
        $types .= "i";
        $sql = "UPDATE usuarios SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $_SESSION['usuario'] = $novo_nome; // Atualiza o nome na sessão
                $_SESSION['success_message'] = "Perfil atualizado com sucesso!";
            } else {
                $_SESSION['error_message'] = "Erro ao atualizar o perfil: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Erro ao preparar a query: " . $conn->error;
        }
    } else {
         $_SESSION['success_message'] = "Nenhuma alteração foi feita.";
    }

    $conn->close();
    // Redireciona de volta para a mesma página para mostrar a mensagem
    header('Location: ../php/editar_perfil_login_feito.php');
    exit();
}
// --- FIM DA LÓGICA DE PROCESSAMENTO ---


// --- LÓGICA DE EXIBIÇÃO DA PÁGINA (EXECUTA EM ACESSOS NORMAIS) ---
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);

$sql_user = "SELECT nome, email, foto_perfil FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $id_usuario_logado);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();
$conn->close();

$profilePhotoUrl = !empty($user_data['foto_perfil']) ? htmlspecialchars($user_data['foto_perfil']) : '../uploads/user_default.png';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Editar Perfil — Duo Dates</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand:          #5C1222;
      --brand-dark:     #3D0B14;
      --brand-light:    #E8D0C4;
      --brand-mid:      #C9A898;
      --brand-border:   #C08898;

      --bg-page:        #F2EBE1;
      --bg-surface:     #F7F2EC;
      --bg-card:        #F7F2EC;
      --bg-muted:       #EDE3D8;
      --bg-input:       #FDF8F3;
      --bg-topbar:      #3D0B14;

      --text-primary:   #2A0A10;
      --text-secondary: #7A5A52;
      --text-tertiary:  #A8887A;
      --text-on-brand:  #F7EDE6;

      --border:         #DDD0C0;
      --border-focus:   #5C1222;

      --radius-sm: 7px;
      --radius-md: 10px;
      --radius-lg: 13px;

      --font: 'DM Sans', sans-serif;
    }

    html, body { height: 100%; font-family: var(--font); font-size: 14px; color: var(--text-primary); background: var(--bg-page); }
    a { text-decoration: none; color: inherit; }
    button { font-family: var(--font); cursor: pointer; }
    input, textarea, select { font-family: var(--font); }

    /* TOPBAR */
    .topbar {
      position: sticky; top: 0; z-index: 100;
      background: var(--bg-topbar);
      border-bottom: 1px solid #5C1222;
      height: 52px; display: flex; align-items: center;
      padding: 0 28px; justify-content: space-between;
    }
    .logo { display: flex; align-items: center; gap: 9px; font-size: 16px; font-weight: 500; color: #F2EBE1; }
    .logo svg { width: 24px; height: 24px; }
    .topbar-nav { display: flex; align-items: center; gap: 22px; }
    .topbar-nav a { color: #E8C8B8; display: flex; align-items: center; transition: color .15s; }
    .topbar-nav a:hover { color: #fff; }
    .topbar-nav svg { width: 20px; height: 20px; }

    /* LAYOUT */
    .layout { display: flex; min-height: calc(100vh - 52px); }

    /* SIDEBAR */
    .sidebar {
      width: 230px; min-width: 230px;
      background: var(--bg-surface);
      border-right: 1px solid var(--border);
      padding: 28px 14px 24px;
      display: flex; flex-direction: column; gap: 22px;
      position: sticky; top: 52px;
      height: calc(100vh - 52px); overflow-y: auto;
    }
    .profile-card { display: flex; flex-direction: column; align-items: center; gap: 10px; text-align: center; }
    .avatar-wrap { position: relative; }
    .avatar {
      width: 76px; height: 76px; border-radius: 50%;
      background: var(--brand-mid);
      display: flex; align-items: center; justify-content: center;
      font-size: 24px; font-weight: 500; color: var(--brand-dark);
      border: 2px solid #E8C8B8; overflow: hidden;
    }
    .avatar img { width: 100%; height: 100%; object-fit: cover; }
    .online-dot { position: absolute; bottom: 4px; right: 4px; width: 12px; height: 12px; background: #5A7A3A; border-radius: 50%; border: 2px solid var(--bg-surface); }
    .profile-name { font-size: 14px; font-weight: 500; color: var(--text-primary); }
    .profile-email { font-size: 11px; color: var(--text-tertiary); word-break: break-all; }
    .couple-badge { background: var(--brand-light); color: var(--brand); font-size: 11px; font-weight: 500; padding: 3px 12px; border-radius: 20px; }
    .nav-divider { height: 1px; background: var(--border); }
    .nav-menu { display: flex; flex-direction: column; gap: 2px; }
    .nav-item {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 12px; border-radius: var(--radius-md);
      font-size: 13px; color: var(--text-secondary);
      transition: background .15s, color .15s;
    }
    .nav-item:hover { background: var(--bg-muted); color: var(--text-primary); }
    .nav-item.active { background: var(--brand); color: var(--text-on-brand); font-weight: 500; }
    .nav-item.danger { color: #8A2020; }
    .nav-item.danger:hover { background: #F5E8E8; }
    .nav-item svg { width: 16px; height: 16px; flex-shrink: 0; }

    /* MAIN */
    .main { flex: 1; padding: 32px 36px; max-width: 820px; display: flex; flex-direction: column; gap: 20px; }
    .page-title { font-size: 20px; font-weight: 500; color: var(--text-primary); }
    .page-sub { font-size: 13px; color: var(--text-secondary); margin-top: 3px; }

    /* TABS */
    .tab-bar { display: flex; border-bottom: 1px solid var(--border); }
    .tab-btn { font-size: 13px; padding: 10px 20px; background: none; border: none; border-bottom: 2px solid transparent; color: var(--text-secondary); cursor: pointer; margin-bottom: -1px; transition: color .15s; }
    .tab-btn:hover { color: var(--text-primary); }
    .tab-btn.active { color: var(--brand); border-bottom-color: var(--brand); font-weight: 500; }

    /* PANES */
    .pane { display: none; flex-direction: column; gap: 16px; }
    .pane.active { display: flex; }

    /* CARDS */
    .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 22px 26px; }
    .card-label { font-size: 10px; font-weight: 500; letter-spacing: .07em; text-transform: uppercase; color: var(--text-tertiary); margin-bottom: 16px; }

    /* FOTO */
    .photo-row { display: flex; align-items: flex-end; gap: 22px; }
    .photo-preview { width: 90px; height: 90px; border-radius: 50%; background: var(--brand-mid); display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: 500; color: var(--brand-dark); border: 2px solid #E8C8B8; overflow: hidden; flex-shrink: 0; }
    .photo-preview img { width: 100%; height: 100%; object-fit: cover; }
    .photo-actions { display: flex; flex-direction: column; gap: 8px; }

    /* FORM */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label { font-size: 12px; color: var(--text-secondary); }
    .form-input { font-size: 13px; padding: 9px 13px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-input); color: var(--text-primary); outline: none; width: 100%; transition: border-color .15s, box-shadow .15s; }
    .form-input:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(92,18,34,.1); }
    .form-input:disabled { background: var(--bg-muted); color: var(--text-tertiary); cursor: not-allowed; }
    .form-hint { font-size: 11px; color: var(--text-tertiary); }
    .form-textarea { font-size: 13px; padding: 9px 13px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--bg-input); color: var(--text-primary); outline: none; width: 100%; resize: vertical; min-height: 80px; font-family: var(--font); transition: border-color .15s, box-shadow .15s; }
    .form-textarea:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(92,18,34,.1); }

    /* TAGS */
    .tag-group { display: flex; flex-wrap: wrap; gap: 7px; }
    .tag { font-size: 12px; padding: 5px 14px; border-radius: 20px; border: 1px solid var(--border); background: var(--bg-muted); color: var(--text-secondary); cursor: pointer; user-select: none; transition: all .15s; }
    .tag:hover { border-color: var(--brand-border); color: var(--brand); }
    .tag.selected { background: var(--brand); color: var(--text-on-brand); border-color: var(--brand); font-weight: 500; }

    /* ESTATÍSTICAS */
    .stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
    .stat-item { background: var(--bg-muted); border-radius: var(--radius-md); padding: 16px 18px; }
    .stat-val { font-size: 26px; font-weight: 500; color: var(--brand); }
    .stat-label { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
    .bar-list { display: flex; flex-direction: column; gap: 12px; }
    .bar-row { display: flex; align-items: center; gap: 12px; }
    .bar-name { font-size: 12px; color: var(--text-secondary); min-width: 140px; }
    .bar-track { flex: 1; background: var(--brand-light); border-radius: 4px; height: 8px; overflow: hidden; }
    .bar-fill { height: 100%; border-radius: 4px; background: var(--brand); }
    .bar-fill.light { background: var(--brand-mid); }
    .bar-pct { font-size: 12px; color: var(--text-tertiary); min-width: 32px; text-align: right; }

    /* CASAL */
    .couple-row { display: flex; align-items: center; gap: 14px; }
    .couple-avatar { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 500; border: 2px solid var(--bg-surface); }
    .couple-avatar.me { background: var(--brand-mid); color: var(--brand-dark); }
    .couple-avatar.partner { background: #C4CCDC; color: #2A3A5A; }
    .couple-info { display: flex; flex-direction: column; }
    .couple-name { font-size: 14px; font-weight: 500; color: var(--text-primary); }
    .couple-since { font-size: 12px; color: var(--text-secondary); }
    .couple-status { margin-left: auto; background: var(--brand-light); color: var(--brand); font-size: 11px; font-weight: 500; padding: 4px 12px; border-radius: 20px; }

    /* BOTÕES */
    .action-row { display: flex; justify-content: flex-end; gap: 10px; padding-top: 6px; }
    .btn { font-size: 13px; font-weight: 500; padding: 8px 20px; border-radius: var(--radius-md); transition: all .15s; cursor: pointer; }
    .btn-outline { background: transparent; color: var(--text-primary); border: 1px solid var(--border); }
    .btn-outline:hover { background: var(--bg-muted); }
    .btn-primary { background: var(--brand); color: var(--text-on-brand); border: none; }
    .btn-primary:hover { background: var(--brand-dark); }
    .btn-ghost { background: none; border: none; font-size: 12px; color: var(--text-tertiary); padding: 4px 0; }
    .btn-ghost:hover { color: #8A2020; }
    .btn-danger { background: transparent; color: #8A2020; border: 1px solid #C08080; }
    .btn-danger:hover { background: #F5E8E8; }

    @media (max-width: 768px) {
      .sidebar { display: none; }
      .main { padding: 20px 16px; }
      .form-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<header class="topbar">
  <div class="logo">
    <svg viewBox="0 0 24 24" fill="none">
      <path d="M12 21C12 21 3 13.5 3 8a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 5.5-9 13-9 13z" fill="#E8C8B8"/>
      <path d="M12 21C12 21 10 15 10 11a4 4 0 0 1 4-4 4 4 0 0 1 4 4c0 4-2 10-6 10z" fill="#C9A898" opacity="0.8"/>
    </svg>
    Duo Dates
  </div>
  <nav class="topbar-nav">
    <a href="#" title="Lugares"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg></a>
    <a href="#" title="Agenda"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></a>
    <a href="#" title="Casal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M19 8v6m3-3h-6"/></svg></a>
    <a href="#" title="Favoritos"><svg viewBox="0 0 24 24" fill="#E8C8B8" stroke="#E8C8B8" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg></a>
  </nav>
</header>

<div class="layout">
  <aside class="sidebar">
    <div class="profile-card">
      <div class="avatar-wrap">
        <div class="avatar" id="sidebar-avatar">
          <?php if (!empty($usuario['foto'])): ?>
            <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="Foto" />
          <?php else: echo $iniciais; endif; ?>
        </div>
        <div class="online-dot"></div>
      </div>
      <div>
        <div class="profile-name"><?= htmlspecialchars($usuario['username']) ?></div>
        <div class="profile-email"><?= htmlspecialchars($usuario['email']) ?></div>
      </div>
      <div class="couple-badge">Casal desde Jan 2024</div>
    </div>

    <nav class="nav-menu">
     
      <a href="login-feito.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="5"/><path d="M3 21v-1a7 7 0 0 1 14 0v1"/></svg>
        Meu Perfil
      </a>
      <a href="mudar_essencia.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 18.35l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        Minha Essência
      </a>
      <a href="meus_dates.php" class="nav-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
        Meus Dates
      </a>
      <a href="editar_perfil.php" class="nav-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Editar Perfil
      </a>
      <div class="nav-divider"></div>
      <a href="logout.php" class="nav-item danger">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sair
      </a>
    </nav>
  </aside>

  <main class="main">
    <div>
      <h1 class="page-title">Editar perfil</h1>
      <p class="page-sub">Atualize suas informações pessoais e preferências</p>
    </div>

    <div class="tab-bar">
      <button class="tab-btn active" onclick="showTab('info',this)">Informações</button>
      <button class="tab-btn" onclick="showTab('pref',this)">Preferências</button>
      <button class="tab-btn" onclick="showTab('casal',this)">Casal</button>
      <button class="tab-btn" onclick="showTab('stats',this)">Estatísticas</button>
    </div>

    <!-- PANE: INFORMAÇÕES -->
    <div id="pane-info" class="pane active">
      <div class="card">
        <div class="card-label">Foto de perfil</div>
        <div class="photo-row">
          <div class="photo-preview" id="photo-preview">
            <?php if (!empty($usuario['foto'])): ?>
              <img src="<?= htmlspecialchars($usuario['foto']) ?>" alt="Foto" />
            <?php else: echo $iniciais; endif; ?>
          </div>
          <div class="photo-actions">
            <label for="foto-input" class="btn btn-outline" style="display:inline-block;text-align:center;">Trocar foto</label>
            <input type="file" id="foto-input" accept="image/*" style="display:none;" onchange="previewFoto(this)" />
            <button class="btn-ghost" onclick="removerFoto()">Remover foto</button>
          </div>
        </div>
      </div>

      <form class="card" method="POST" action="salvar_perfil.php" enctype="multipart/form-data">
        <input type="hidden" name="acao" value="info" />
        <div class="card-label">Dados pessoais</div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label" for="username">Nome de usuário</label>
            <input class="form-input" type="text" id="username" name="username" value="<?= htmlspecialchars($usuario['username']) ?>" required />
          </div>
          <div class="form-group">
            <label class="form-label" for="nome">Nome completo</label>
            <input class="form-input" type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" placeholder="Como prefere ser chamado" />
          </div>
          <div class="form-group full">
            <label class="form-label" for="email">E-mail</label>
            <input class="form-input" type="email" id="email" value="<?= htmlspecialchars($usuario['email']) ?>" disabled />
            <span class="form-hint">O e-mail não pode ser alterado</span>
          </div>
          <div class="form-group full">
            <label class="form-label" for="bio">Mini bio</label>
            <textarea class="form-textarea" id="bio" name="bio" placeholder="Conte um pouco sobre você e o que gosta de fazer nos dates..."><?= htmlspecialchars($usuario['bio']) ?></textarea>
          </div>
        </div>
        <div class="action-row">
          <a href="perfil.php" class="btn btn-outline">Cancelar</a>
          <button type="submit" class="btn btn-primary">Salvar alterações</button>
        </div>
      </form>
    </div>

    <!-- PANE: PREFERÊNCIAS -->
    <div id="pane-pref" class="pane">
      <div class="card">
        <div class="card-label">Estilo de date preferido</div>
        <div class="tag-group">
          <?php
          $opcoes_estilo = ['Ambiente íntimo','Aventura ao ar livre','Experiências culturais','Atividades criativas','Relaxamento e bem-estar','Música ao vivo','Gastronomia','Esportes juntos'];
          foreach ($opcoes_estilo as $op):
            $sel = in_array($op, $prefs_estilo) ? ' selected' : '';
          ?>
          <span class="tag<?= $sel ?>" data-group="estilo" onclick="this.classList.toggle('selected')"><?= htmlspecialchars($op) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <form class="card" method="POST" action="salvar_perfil.php">
        <input type="hidden" name="acao" value="preferencias" />
        <input type="hidden" name="prefs_estilo" id="prefs_estilo_input" />
        <input type="hidden" name="prefs_culinaria" id="prefs_culinaria_input" />
        <div class="card-label">Culinária favorita</div>
        <div class="tag-group">
          <?php
          $opcoes_culinaria = ['Italiana','Japonesa','Brasileira','Árabe','Frutos do mar','Vegana','Fast casual'];
          foreach ($opcoes_culinaria as $op):
            $sel = in_array($op, $prefs_culinaria) ? ' selected' : '';
          ?>
          <span class="tag<?= $sel ?>" data-group="culinaria" onclick="this.classList.toggle('selected')"><?= htmlspecialchars($op) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-outline">Cancelar</button>
          <button type="submit" class="btn btn-primary" onclick="prepararPrefs()">Salvar preferências</button>
        </div>
      </form>
    </div>

    <!-- PANE: CASAL -->
    <div id="pane-casal" class="pane">
      <div class="card">
        <div class="card-label">Seu casal</div>
        <div class="couple-row">
          <div class="couple-avatar me"><?= $iniciais ?></div>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="#5C1222"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          <div class="couple-avatar partner">PA</div>
          <div class="couple-info">
            <div class="couple-name"><?= htmlspecialchars($usuario['username']) ?> + <?= htmlspecialchars($usuario['parceiro_nome']) ?></div>
            <div class="couple-since">Conectados desde Janeiro 2024</div>
          </div>
          <div class="couple-status">Ativo</div>
        </div>
      </div>

      <form class="card" method="POST" action="salvar_perfil.php">
        <input type="hidden" name="acao" value="casal" />
        <div class="card-label">Informações do casal</div>
        <div class="form-grid" style="grid-template-columns:1fr 1fr;">
          <div class="form-group">
            <label class="form-label" for="casal_desde">Início do relacionamento</label>
            <input class="form-input" type="date" id="casal_desde" name="casal_desde" value="<?= htmlspecialchars($usuario['casal_desde']) ?>" />
          </div>
          <div class="form-group">
            <label class="form-label" for="apelido_casal">Apelido do casal</label>
            <input class="form-input" type="text" id="apelido_casal" name="apelido_casal" value="<?= htmlspecialchars($usuario['apelido_casal']) ?>" placeholder="Ex: JH & Mari" />
          </div>
        </div>
        <div class="action-row">
          <button type="button" class="btn btn-danger">Desconectar casal</button>
          <button type="button" class="btn btn-outline">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>

    <!-- PANE: ESTATÍSTICAS -->
    <div id="pane-stats" class="pane">
      <div class="card">
        <div class="card-label">Sua jornada de dates</div>
        <div class="stats-row">
          <div class="stat-item"><div class="stat-val"><?= (int)$usuario['dates_realizados'] ?></div><div class="stat-label">Dates realizados</div></div>
          <div class="stat-item"><div class="stat-val"><?= (int)$usuario['lugares_favoritos'] ?></div><div class="stat-label">Lugares favoritos</div></div>
          <div class="stat-item"><div class="stat-val"><?= (int)$usuario['meses_ativos'] ?></div><div class="stat-label">Meses ativos</div></div>
        </div>
      </div>
      <div class="card">
        <div class="card-label">Categorias mais visitadas</div>
        <div class="bar-list">
          <?php foreach ($categorias as $cat): ?>
          <div class="bar-row">
            <span class="bar-name"><?= htmlspecialchars($cat['nome']) ?></span>
            <div class="bar-track"><div class="bar-fill<?= $cat['light'] ? ' light' : '' ?>" style="width:<?= (int)$cat['pct'] ?>%"></div></div>
            <span class="bar-pct"><?= (int)$cat['pct'] ?>%</span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </main>
</div>

<script>
function showTab(name, btn) {
  document.querySelectorAll('.pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('pane-' + name).classList.add('active');
  if (btn) btn.classList.add('active');
}

function prepararPrefs() {
  document.getElementById('prefs_estilo_input').value = JSON.stringify(
    [...document.querySelectorAll('[data-group="estilo"].selected')].map(t => t.textContent.trim())
  );
  document.getElementById('prefs_culinaria_input').value = JSON.stringify(
    [...document.querySelectorAll('[data-group="culinaria"].selected')].map(t => t.textContent.trim())
  );
}

function previewFoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = '<img src="' + e.target.result + '" alt="Preview" style="width:100%;height:100%;object-fit:cover;" />';
      document.getElementById('photo-preview').innerHTML = img;
      const sb = document.getElementById('sidebar-avatar');
      if (sb) sb.innerHTML = img;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function removerFoto() {
  const ini = '<?= $iniciais ?>';
  document.getElementById('photo-preview').innerHTML = ini;
  document.getElementById('foto-input').value = '';
  const sb = document.getElementById('sidebar-avatar');
  if (sb) sb.innerHTML = ini;
}

const tabParam = new URLSearchParams(window.location.search).get('tab');
if (tabParam && ['info','pref','casal','stats'].includes(tabParam)) {
  const idx = ['info','pref','casal','stats'].indexOf(tabParam);
  showTab(tabParam, document.querySelectorAll('.tab-btn')[idx]);
}
</script>
</body>
</html>
