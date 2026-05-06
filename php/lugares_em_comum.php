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
                <img src="<?php echo $profilePhotoUrl; ?>" alt="Foto de Perfil" class="profile-photo" onerror="this.onerror=null; this.src='../images/perfil.png';">
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
            </section>

        </main>
    </div>
</body>
</html>