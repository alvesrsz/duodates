<?php
session_start();
include '../conexao.php';

// Proteção
if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: ../php/login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$partner_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

if ($partner_id === false || $partner_id === $user_id) {
    header('Location: ../php/meus_dates.php');
    exit();
}

// --- BUSCA DADOS USUÁRIOS ---
$sql_users = "SELECT id, nome, email, foto_perfil FROM usuarios WHERE id IN (?, ?)";
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

// CORREÇÃO FOTO DE PERFIL
$profilePhotoUrl = !empty($user_data['foto_perfil']) ? htmlspecialchars($user_data['foto_perfil']) : '../images/perfil.png';
if (strpos($profilePhotoUrl, 'http') === false && strpos($profilePhotoUrl, '../') === false) $profilePhotoUrl = '../' . $profilePhotoUrl;


// --- BUSCAR "LUGARES FAVORITADOS" POR AMBOS ---
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
    <title>Lugares Favoritados em Comum - Duo Dates</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/shell.css">
    <link rel="stylesheet" href="../css/meus_dates.css">
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
          <img src="<?php echo $profilePhotoUrl; ?>" alt="Foto" onerror="this.onerror=null;this.src='../images/iconeperfil.png';">
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
                <div class="section-header">
                    <h2>LUGARES FAVORITADOS POR AMBOS</h2>
                </div>
                
                <p class="page-subtitle">
                    Estes são os lugares que você e <strong><?php echo htmlspecialchars($partner_data['nome']); ?></strong> marcaram com <i class="fas fa-heart" style="color: var(--primary-color);"></i>!
                </p>

                <div class="common-places-list">
                    <?php if (empty($lugares_favoritos)): ?>
                        <div class="empty-state">
                            <i class="fas fa-heart-crack"></i>
                            <p>Vocês ainda não têm lugares favoritados em comum.</p>
                            <span>Explore a plataforma e favorite os locais que mais gosta!</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lugares_favoritos as $local): ?>
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
</body>
</html>