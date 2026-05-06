<?php
session_start();
include('../conexao.php');

<<<<<<< HEAD
// ── SEGURANÇA ──────────────────────────────────────────────
=======
// 1. VERIFICAÇÃO DE SEGURANÇA
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
if (!isset($_SESSION['usuario']) || ($_SESSION['tipo_conta'] !== 'empresarial' && $_SESSION['tipo_conta'] !== 'admin')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['id'];

<<<<<<< HEAD
// ── VARIÁVEIS INICIAIS ─────────────────────────────────────
$id_local              = null;
$nome_local            = "Empresa";
$status_local          = "nenhum"; // 'ativo' | 'pendente' | 'nenhum'
$total_favoritos       = 0;
$total_visualizacoes   = 0;
$total_agendamentos    = 0;
$total_confirmados     = 0;
$avaliacao_media       = 0;
$agendamentos_recentes = [];
$agendamentos_semana   = [];

if ($conn) {

    // ── PASSO 1: Local ATIVO vinculado ao usuário ──────────
    $stmt = $conn->prepare("SELECT id_local_associado FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!empty($row['id_local_associado'])) {
        $id_local     = $row['id_local_associado'];
        $status_local = 'ativo';
    }

    // ── PASSO 2: Se não ativo, verifica PENDENTE ───────────
    if ($status_local === 'nenhum') {
        $stmt = $conn->prepare("SELECT titulo FROM locais_pendentes WHERE id_usuario = ? LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $row_pen = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row_pen) {
            $nome_local   = $row_pen['titulo'];
            $status_local = 'pendente';
        }
    }

    // ── PASSO 3: Estatísticas (somente se ATIVO) ───────────
    if ($status_local === 'ativo' && $id_local) {

        // Nome e Visualizações
        $stmt = $conn->prepare("SELECT titulo, visualizacoes_total FROM locais WHERE id_local = ?");
        $stmt->bind_param("i", $id_local);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($r) {
            $nome_local          = $r['titulo'];
            $total_visualizacoes = (int)$r['visualizacoes_total'];
        }

        // Favoritos
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM favoritos WHERE place_id = ?");
        $stmt->bind_param("s", $id_local);
        $stmt->execute();
        $total_favoritos = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        // Agendamentos pendentes
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM agendamentos WHERE id_local = ? AND status = 'pendente'");
        $stmt->bind_param("i", $id_local);
        $stmt->execute();
        $total_agendamentos = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        // Agendamentos confirmados
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM agendamentos WHERE id_local = ? AND status = 'confirmado'");
        $stmt->bind_param("i", $id_local);
        $stmt->execute();
        $total_confirmados = (int)$stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        // Avaliação média (tabela avaliacoes, se existir)
        $stmt = $conn->prepare("SELECT AVG(nota) AS media FROM avaliacoes WHERE id_local = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id_local);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $avaliacao_media = $r && $r['media'] ? round((float)$r['media'], 1) : 0;
            $stmt->close();
        }

        // Agendamentos dos últimos 7 dias (para o gráfico)
        $stmt = $conn->prepare("
            SELECT DATE(data_agendamento) AS dia, COUNT(*) AS total
            FROM agendamentos
            WHERE id_local = ?
              AND data_agendamento >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY dia
            ORDER BY dia ASC
        ");
        $stmt->bind_param("i", $id_local);
        $stmt->execute();
        $res = $stmt->get_result();
        $map = [];
        while ($r = $res->fetch_assoc()) {
            $map[$r['dia']] = (int)$r['total'];
        }
        $stmt->close();

        // Preenche os 7 dias (inclusive zeros)
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $agendamentos_semana[] = [
                'dia'   => date('d/m', strtotime($d)),
                'total' => $map[$d] ?? 0,
            ];
        }

        // Últimos 5 agendamentos
        $stmt = $conn->prepare("
            SELECT a.id, u.nome AS cliente, a.data_agendamento, a.status
            FROM agendamentos a
            JOIN usuarios u ON u.id = a.id_usuario
            WHERE a.id_local = ?
            ORDER BY a.data_agendamento DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $id_local);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $agendamentos_recentes[] = $r;
        }
        $stmt->close();
    }
}

// ── HELPERS ────────────────────────────────────────────────
$max_semana = max(array_column($agendamentos_semana, 'total') ?: [1]);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard – <?= htmlspecialchars($nome_local) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Lato:wght@300;400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ════════════════════════════════════════
   RESET & VARIABLES
════════════════════════════════════════ */
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
  --crimson:      #9B1C3A;
  --crimson-dark: #7a1530;
  --rose:         #e8305a;
  --pink-soft:    #fde8ed;
  --sidebar-bg:   #1e2139;
  --body-bg:      #f4f5fb;
  --card-bg:      #ffffff;
  --text-main:    #1e1e2e;
  --text-muted:   #8888aa;
  --border:       #eaeaf2;
  --green:        #22c97a;
  --orange:       #f59e0b;
  --blue:         #3b82f6;
  --shadow:       0 2px 16px rgba(30,33,57,.08);
}

body {
  font-family: 'Lato', sans-serif;
  background: var(--body-bg);
  color: var(--text-main);
  display: flex;
  min-height: 100vh;
}

/* ════════════════════════════════════════
   SIDEBAR
════════════════════════════════════════ */
.sidebar {
  width: 220px;
  min-height: 100vh;
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0;
  z-index: 200;
}

.sidebar-brand {
  padding: 26px 22px 20px;
  border-bottom: 1px solid rgba(255,255,255,.07);
}

.sidebar-brand .brand-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 800;
  font-size: 1.15rem;
  color: #fff;
  line-height: 1.2;
}

.sidebar-brand .brand-sub {
  font-size: 0.68rem;
  color: var(--rose);
  text-transform: uppercase;
  letter-spacing: 1.8px;
  font-weight: 600;
  margin-top: 3px;
  display: block;
}

.sidebar-badge {
  display: inline-block;
  margin-top: 8px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: .5px;
  text-transform: uppercase;
}
.badge-ativo    { background: #22c97a22; color: var(--green); border: 1px solid #22c97a55; }
.badge-pendente { background: #f59e0b22; color: var(--orange); border: 1px solid #f59e0b55; }
.badge-nenhum   { background: #e8305a22; color: var(--rose);   border: 1px solid #e8305a55; }

.sidebar nav { flex: 1; padding: 14px 0; }

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 22px;
  color: rgba(255,255,255,.55);
  font-size: 0.85rem;
  font-weight: 500;
  text-decoration: none;
  transition: all .18s;
  border-left: 3px solid transparent;
  cursor: pointer;
}
.nav-item:hover { background: rgba(255,255,255,.06); color: #fff; }
.nav-item.active { background: rgba(155,28,58,.28); color: #fff; border-left-color: var(--rose); }
.nav-item i { width: 18px; text-align: center; opacity: .75; }
.nav-item.active i { opacity: 1; }

.sidebar-footer {
  padding: 14px 22px;
  border-top: 1px solid rgba(255,255,255,.07);
}
.sidebar-footer .nav-item { padding: 10px 0; border-left: none; }

/* ════════════════════════════════════════
   MAIN
════════════════════════════════════════ */
.main {
  margin-left: 220px;
  flex: 1;
  padding: 32px 36px;
}

/* ── Topbar ── */
.topbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 28px;
  animation: fadeDown .5s ease both;
}
@keyframes fadeDown {
  from { opacity:0; transform:translateY(-10px); }
  to   { opacity:1; transform:translateY(0); }
}

.topbar h1 {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.65rem;
  font-weight: 800;
  color: var(--text-main);
}
.topbar h1 span { color: var(--crimson); }

.topbar-right { text-align: right; }
.topbar-right .welcome { font-size: 0.78rem; color: var(--text-muted); }
.topbar-right .company { font-family:'Plus Jakarta Sans',sans-serif; font-weight:700; color: var(--crimson); font-size:.95rem; }

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: .72rem;
  font-weight: 700;
  margin-top: 5px;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.pill-ativo    { background:#e8f9f0; color: var(--green); }
.pill-pendente { background:#fff8e8; color: var(--orange); }
.pill-nenhum   { background:#fde8ed; color: var(--rose); }
.status-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: currentColor;
}
.pill-ativo .status-dot { animation: pulse 2s infinite; }
@keyframes pulse {
  0%,100% { opacity:1; } 50% { opacity:.3; }
}

/* ════════════════════════════════════════
   STATUS BOXES (pendente / nenhum)
════════════════════════════════════════ */
.status-box {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 48px 36px;
  text-align: center;
  box-shadow: var(--shadow);
  max-width: 560px;
  margin: 40px auto;
  animation: fadeUp .5s ease both;
}
.status-box .box-icon {
  width: 72px; height: 72px;
  border-radius: 50%;
  display: flex; align-items:center; justify-content:center;
  font-size: 2rem;
  margin: 0 auto 20px;
}
.box-warning .box-icon { background:#fff8e8; color: var(--orange); }
.box-danger  .box-icon { background:#fde8ed; color: var(--rose); }

.status-box h2 {
  font-family:'Plus Jakarta Sans',sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  margin-bottom: 10px;
}
.status-box p { color: var(--text-muted); font-size:.9rem; line-height:1.6; }
.status-box p + p { margin-top: 6px; }

.btn-primary {
  display: inline-block;
  margin-top: 22px;
  padding: 12px 28px;
  background: linear-gradient(135deg, var(--rose), var(--crimson));
  color: #fff;
  text-decoration: none;
  border-radius: 10px;
  font-weight: 700;
  font-size: .9rem;
  transition: opacity .2s, transform .15s;
}
.btn-primary:hover { opacity:.9; transform:translateY(-1px); }

/* ════════════════════════════════════════
   STAT CARDS
════════════════════════════════════════ */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 18px;
  margin-bottom: 24px;
}

.stat-card {
  border-radius: 16px;
  padding: 22px 24px;
  color: #fff;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 112px;
  box-shadow: 0 4px 20px rgba(0,0,0,.12);
  animation: fadeUp .5s ease both;
}
@keyframes fadeUp {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:translateY(0); }
}
.stat-card:nth-child(1) { background: linear-gradient(135deg,#e8305a,#9B1C3A); animation-delay:.05s; }
.stat-card:nth-child(2) { background: linear-gradient(135deg,#3b82f6,#2563eb); animation-delay:.10s; }
.stat-card:nth-child(3) { background: linear-gradient(135deg,#22c97a,#16a35a); animation-delay:.15s; }
.stat-card:nth-child(4) { background: linear-gradient(135deg,#f59e0b,#d97706); animation-delay:.20s; }

.stat-card .bg-icon {
  position: absolute;
  right: 18px; top: 50%;
  transform: translateY(-50%);
  font-size: 3.8rem;
  opacity: .13;
  pointer-events: none;
}
.stat-card .stat-label {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  opacity: .9;
}
.stat-card .stat-value {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1;
}
.stat-card .stat-hint {
  font-size: .7rem;
  opacity: .8;
  margin-top: 2px;
}

/* ════════════════════════════════════════
   PANELS
════════════════════════════════════════ */
.panel {
  background: var(--card-bg);
  border-radius: 16px;
  padding: 24px 26px;
  box-shadow: var(--shadow);
  border: 1px solid var(--border);
  animation: fadeUp .5s ease both;
  animation-delay: .25s;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.panel-title {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: .95rem;
  font-weight: 800;
  color: var(--text-main);
}
.panel-title span { color: var(--text-muted); font-weight:500; font-size:.8rem; margin-left:6px; }

.panel-link {
  font-size: .75rem;
  color: var(--crimson);
  font-weight: 700;
  text-decoration: none;
}
.panel-link:hover { text-decoration: underline; }

/* ── Mid & Bottom grids ── */
.mid-grid {
  display: grid;
  grid-template-columns: 1.6fr 1fr;
  gap: 20px;
  margin-bottom: 22px;
}
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

/* ════════════════════════════════════════
   CHART (barras)
════════════════════════════════════════ */
.chart-wrap {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  height: 160px;
  padding: 0 4px;
}

.bar-col {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  height: 100%;
  justify-content: flex-end;
}

.bar-fill {
  width: 100%;
  border-radius: 7px 7px 0 0;
  background: linear-gradient(180deg, var(--rose), var(--crimson));
  min-height: 4px;
  transition: height .6s cubic-bezier(.4,0,.2,1);
  position: relative;
}
.bar-fill:hover::after {
  content: attr(data-val);
  position: absolute;
  top: -24px; left: 50%;
  transform: translateX(-50%);
  background: var(--crimson);
  color: #fff;
  padding: 2px 7px;
  border-radius: 4px;
  font-size: .68rem;
  font-weight: 700;
  white-space: nowrap;
}

.bar-label {
  font-size: .65rem;
  color: var(--text-muted);
  white-space: nowrap;
}

/* ════════════════════════════════════════
   TABELA DE AGENDAMENTOS
════════════════════════════════════════ */
.agenda-table {
  width: 100%;
  border-collapse: collapse;
}
.agenda-table th {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .4px;
  color: var(--text-muted);
  padding: 0 10px 12px;
  text-align: left;
  border-bottom: 1px solid var(--border);
}
.agenda-table td {
  padding: 12px 10px;
  font-size: .85rem;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.agenda-table tr:last-child td { border-bottom: none; }
.agenda-table tr:hover td { background: #fafafe; }

.badge-status {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: .7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.bs-pendente   { background:#fff8e8; color: var(--orange); }
.bs-confirmado { background:#e8f9f0; color: var(--green); }
.bs-cancelado  { background:#fde8ed; color: var(--rose); }

.empty-state {
  text-align: center;
  padding: 28px;
  color: var(--text-muted);
  font-size: .85rem;
}
.empty-state i { font-size: 2rem; display:block; margin-bottom:8px; opacity:.4; }

/* ════════════════════════════════════════
   MÉTRICAS RÁPIDAS (painel lateral)
════════════════════════════════════════ */
.quick-metrics { display: flex; flex-direction: column; gap: 14px; }

.qm-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-radius: 12px;
  background: var(--body-bg);
  transition: background .18s;
}
.qm-item:hover { background: #ededf8; }

.qm-left { display: flex; align-items: center; gap: 12px; }
.qm-icon {
  width: 38px; height: 38px;
  border-radius: 10px;
  display: flex; align-items:center; justify-content:center;
  font-size: 1rem;
}
.qm-label { font-size: .8rem; font-weight: 600; color: var(--text-main); }
.qm-sub   { font-size: .7rem; color: var(--text-muted); margin-top:1px; }
.qm-value {
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--text-main);
}

/* ── Avaliação estrelas ── */
.stars { color: #f59e0b; font-size: .85rem; letter-spacing:1px; }

/* ════════════════════════════════════════
   DICAS PAINEL
════════════════════════════════════════ */
.tip-list { display: flex; flex-direction: column; gap: 12px; }
.tip-item {
  display: flex;
  gap: 12px;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid var(--border);
  transition: border-color .18s, box-shadow .18s;
}
.tip-item:hover { border-color: var(--rose); box-shadow: 0 2px 12px rgba(232,48,90,.08); }
.tip-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  background: var(--pink-soft);
  color: var(--crimson);
  display:flex; align-items:center; justify-content:center;
  font-size:.9rem; flex-shrink:0;
}
.tip-text strong { font-size:.82rem; font-weight:700; color:var(--text-main); display:block; }
.tip-text span   { font-size:.75rem; color:var(--text-muted); }
</style>
</head>
<body>

<!-- ════════ SIDEBAR ════════ -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-title">DuoDates</div>
    <span class="brand-sub">Empresa</span>
    <br>
    <?php if ($status_local === 'ativo'): ?>
      <span class="sidebar-badge badge-ativo"><i class="fas fa-circle" style="font-size:.5rem;"></i> Ativo</span>
    <?php elseif ($status_local === 'pendente'): ?>
      <span class="sidebar-badge badge-pendente"><i class="fas fa-clock" style="font-size:.55rem;"></i> Em análise</span>
    <?php else: ?>
      <span class="sidebar-badge badge-nenhum"><i class="fas fa-times" style="font-size:.55rem;"></i> Sem local</span>
    <?php endif; ?>
  </div>

  <nav>
    <?php if ($status_local === 'ativo'): ?>
      <a class="nav-item active" href="#"><i class="fas fa-chart-line"></i> Visão Geral</a>
      <a class="nav-item" href="gerenciar_agendamentos.php"><i class="fas fa-calendar-check"></i> Agendamentos
        <?php if ($total_agendamentos > 0): ?>
          <span style="margin-left:auto;background:var(--rose);color:#fff;font-size:.62rem;font-weight:700;padding:2px 7px;border-radius:20px;"><?= $total_agendamentos ?></span>
        <?php endif; ?>
      </a>
      <a class="nav-item" href="editar_meu_local.php"><i class="fas fa-edit"></i> Editar Local</a>
      <a class="nav-item" href="../index.php"><i class="fas fa-globe"></i> Ver Site</a>
    <?php else: ?>
      <a class="nav-item active" href="#" style="cursor:default;"><i class="fas fa-lock"></i> Aguardando Aprovação</a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a class="nav-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
  </div>
</aside>

<!-- ════════ MAIN ════════ -->
<main class="main">

  <!-- ── Topbar ── -->
  <div class="topbar">
    <h1>
      <?php if ($status_local === 'ativo'): ?>
        Visão Geral <span>do Negócio</span>
      <?php elseif ($status_local === 'pendente'): ?>
        Cadastro <span>Recebido</span>
      <?php else: ?>
        Atenção
      <?php endif; ?>
    </h1>
    <div class="topbar-right">
      <div class="welcome">Bem-vindo,</div>
      <div class="company"><?= htmlspecialchars($nome_local) ?></div>
      <?php if ($status_local === 'ativo'): ?>
        <div class="status-pill pill-ativo"><span class="status-dot"></span> Online</div>
      <?php elseif ($status_local === 'pendente'): ?>
        <div class="status-pill pill-pendente"><span class="status-dot"></span> Em análise</div>
      <?php else: ?>
        <div class="status-pill pill-nenhum"><span class="status-dot"></span> Sem local</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ════════ ESTADO: PENDENTE ════════ -->
  <?php if ($status_local === 'pendente'): ?>
  <div class="status-box box-warning">
    <div class="box-icon"><i class="fas fa-clock"></i></div>
    <h2>Seu local está em análise!</h2>
    <p>Recebemos o cadastro de <strong><?= htmlspecialchars($nome_local) ?></strong>.</p>
    <p>Nossa equipe irá revisar as informações em breve.<br>Assim que aprovado, este painel será liberado automaticamente.</p>
  </div>

  <!-- ════════ ESTADO: NENHUM ════════ -->
  <?php elseif ($status_local === 'nenhum'): ?>
  <div class="status-box box-danger">
    <div class="box-icon"><i class="fas fa-exclamation-circle"></i></div>
    <h2>Nenhum Local Vinculado</h2>
    <p>Não encontramos nenhum estabelecimento (nem ativo, nem pendente) nesta conta.</p>
    <a href="adicionar-lugar.php" class="btn-primary"><i class="fas fa-plus"></i> Cadastrar Agora</a>
  </div>

  <!-- ════════ ESTADO: ATIVO ════════ -->
  <?php else: ?>

  <!-- ── CARDS ── -->
  <div class="stat-grid">
    <div class="stat-card">
      <i class="fas fa-heart bg-icon"></i>
      <div class="stat-label">Favoritos</div>
      <div class="stat-value"><?= $total_favoritos ?></div>
      <div class="stat-hint">usuários salvaram seu local</div>
    </div>
    <div class="stat-card">
      <i class="fas fa-eye bg-icon"></i>
      <div class="stat-label">Visualizações</div>
      <div class="stat-value"><?= $total_visualizacoes ?></div>
      <div class="stat-hint">acessos à sua página</div>
    </div>
    <div class="stat-card">
      <i class="fas fa-calendar-check bg-icon"></i>
      <div class="stat-label">Agendamentos Confirmados</div>
      <div class="stat-value"><?= $total_confirmados ?></div>
      <div class="stat-hint">total aceitos</div>
    </div>
    <div class="stat-card">
      <i class="fas fa-hourglass-half bg-icon"></i>
      <div class="stat-label">Pendentes</div>
      <div class="stat-value"><?= $total_agendamentos ?></div>
      <div class="stat-hint"><a href="gerenciar_agendamentos.php" style="color:rgba(255,255,255,.8);text-decoration:underline;">Gerenciar →</a></div>
    </div>
  </div>

  <!-- ── MID ROW ── -->
  <div class="mid-grid">

    <!-- Gráfico de agendamentos (7 dias) -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">Agendamentos <span>últimos 7 dias</span></div>
        <a class="panel-link" href="gerenciar_agendamentos.php">Ver todos →</a>
      </div>
      <?php if (!empty($agendamentos_semana)): ?>
        <div class="chart-wrap">
          <?php foreach ($agendamentos_semana as $item):
            $pct = $max_semana > 0 ? round(($item['total'] / $max_semana) * 140) : 4;
            $h   = max($pct, 4);
          ?>
          <div class="bar-col">
            <div class="bar-fill" style="height:<?= $h ?>px" data-val="<?= $item['total'] ?>"></div>
            <div class="bar-label"><?= $item['dia'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-chart-bar"></i>
          Ainda sem agendamentos registrados.
        </div>
      <?php endif; ?>
    </div>

    <!-- Métricas rápidas -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">Métricas Rápidas</div>
      </div>
      <div class="quick-metrics">
        <div class="qm-item">
          <div class="qm-left">
            <div class="qm-icon" style="background:#fde8ed;color:var(--rose);"><i class="fas fa-heart"></i></div>
            <div>
              <div class="qm-label">Favoritos</div>
              <div class="qm-sub">usuários que salvaram</div>
            </div>
          </div>
          <div class="qm-value"><?= $total_favoritos ?></div>
        </div>
        <div class="qm-item">
          <div class="qm-left">
            <div class="qm-icon" style="background:#e8f0ff;color:var(--blue);"><i class="fas fa-eye"></i></div>
            <div>
              <div class="qm-label">Visualizações</div>
              <div class="qm-sub">total de acessos</div>
            </div>
          </div>
          <div class="qm-value"><?= $total_visualizacoes ?></div>
        </div>
        <div class="qm-item">
          <div class="qm-left">
            <div class="qm-icon" style="background:#fff8e8;color:var(--orange);"><i class="fas fa-star"></i></div>
            <div>
              <div class="qm-label">Avaliação Média</div>
              <div class="qm-sub">
                <?php
                  $full  = floor($avaliacao_media);
                  $half  = ($avaliacao_media - $full) >= 0.5 ? 1 : 0;
                  $empty = 5 - $full - $half;
                  echo str_repeat('★', $full) . ($half ? '½' : '') . str_repeat('☆', $empty);
                ?>
              </div>
            </div>
          </div>
          <div class="qm-value"><?= $avaliacao_media > 0 ? $avaliacao_media : '—' ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── BOTTOM ROW ── -->
  <div class="bottom-grid">

    <!-- Últimos agendamentos -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">Últimos Agendamentos</div>
        <a class="panel-link" href="gerenciar_agendamentos.php">Ver todos →</a>
      </div>
      <?php if (!empty($agendamentos_recentes)): ?>
      <table class="agenda-table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Data</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($agendamentos_recentes as $ag):
            $bs = match($ag['status']) {
              'confirmado' => 'bs-confirmado',
              'cancelado'  => 'bs-cancelado',
              default      => 'bs-pendente',
            };
          ?>
          <tr>
            <td><?= htmlspecialchars($ag['cliente']) ?></td>
            <td><?= date('d/m/Y', strtotime($ag['data_agendamento'])) ?></td>
            <td><span class="badge-status <?= $bs ?>"><?= ucfirst($ag['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-calendar-times"></i>
          Nenhum agendamento encontrado.
        </div>
      <?php endif; ?>
    </div>

    <!-- Dicas para melhorar -->
    <div class="panel">
      <div class="panel-header">
        <div class="panel-title">Dicas para você</div>
      </div>
      <div class="tip-list">
        <div class="tip-item">
          <div class="tip-icon"><i class="fas fa-images"></i></div>
          <div class="tip-text">
            <strong>Adicione mais fotos</strong>
            <span>Locais com 5+ fotos recebem 3x mais favoritos.</span>
          </div>
        </div>
        <div class="tip-item">
          <div class="tip-icon"><i class="fas fa-tags"></i></div>
          <div class="tip-text">
            <strong>Complete as tags</strong>
            <span>Tags ajudam o sistema de compatibilidade a recomendar seu local.</span>
          </div>
        </div>
        <div class="tip-item">
          <div class="tip-icon"><i class="fas fa-calendar-alt"></i></div>
          <div class="tip-text">
            <strong>Mantenha horários atualizados</strong>
            <span>Evite agendamentos fora do horário real de funcionamento.</span>
          </div>
        </div>
        <div class="tip-item">
          <div class="tip-icon"><i class="fas fa-reply"></i></div>
          <div class="tip-text">
            <strong>Responda agendamentos rápido</strong>
            <span>Respostas em menos de 1h aumentam sua visibilidade no app.</span>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /bottom-grid -->
  <?php endif; ?>

</main>
=======
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
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
</body>
</html>