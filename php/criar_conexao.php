<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$search_result = null;
$search_message = '';

// Lógica de busca de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email_busca'])) {
    $email_busca = trim($_POST['email_busca']);

    if (empty($email_busca) || !filter_var($email_busca, FILTER_VALIDATE_EMAIL)) {
        $search_message = "<p class='message error'>Por favor, insira um e-mail válido.</p>";
    } else {
        // Busca o usuário, garantindo que não seja o próprio usuário logado
        $sql_search = "SELECT id, nome, foto_perfil FROM usuarios WHERE email = ? AND id != ?";
        $stmt_search = $conn->prepare($sql_search);
        $stmt_search->bind_param("si", $email_busca, $user_id);
        $stmt_search->execute();
        $result = $stmt_search->get_result();

        if ($result->num_rows > 0) {
            $search_result = $result->fetch_assoc();
        } else {
            $search_message = "<p class='message error'>Nenhum usuário encontrado com este e-mail.</p>";
        }
        $stmt_search->close();
    }
}

// Busca dados do usuário logado para a sidebar
$sql_user = "SELECT nome, email, foto_perfil FROM usuarios WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$profilePhotoUrl = !empty($user_data['foto_perfil']) ? htmlspecialchars($user_data['foto_perfil']) : 'uploads/user_default.png';
$stmt_user->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Conexão - Duo Dates</title>
    <link rel="stylesheet" href="../css/login-feito.css">
    <link rel="stylesheet" href="../css/meus_dates.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>

    <header class="main-header">
        <div class="header-logo">
            <a href="../index.php" class="logo-link">
                <img src="../images/logoduodates.png" alt="Logo Duo Dates" class="logo-image">
                <span>Duo Dates</span>
            </a>
        </div>
        <div class="header-pages">
            <i class="fas fa-map-marker-alt"></i>
            <i class="far fa-calendar-alt"></i>
            <i class="fas fa-user-friends"></i>
            <i class="fas fa-plus"></i>
        </div>
        <div class="header-icons">
             <a href="../php/favoritos.php"><i class="far fa-heart"></i></a>
            <i class="far fa-bell"></i>
        </div>
    </header>

    <div class="profile-dashboard" style="grid-template-columns: 280px 1fr;">
        <aside class="profile-sidebar">
            <div class="user-info">
                <img src="<?php echo $profilePhotoUrl; ?>" alt="Foto de Perfil" class="profile-photo">
                <h3><?php echo htmlspecialchars($user_data['nome']); ?></h3>
                <p><?php echo htmlspecialchars($user_data['email']); ?></p>
            </div>
            <nav class="sidebar-nav">
                 <ul>
                    <li class="nav-item active"><a href="../php/meus_dates.php"><i class="fas fa-user-friends"></i> <span>Meus Dates</span></a></li>
                    <li class="nav-item"><a href="../php/login-feito.php"><i class="fas fa-home"></i> <span>Meu Perfil</span></a></li>
                    <li class="nav-item"><a href="../php/editar_perfil.php"><i class="fas fa-user-edit"></i> <span>Editar Perfil</span></a></li>
                    <li class="nav-item"><a href="../php/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <section class="section-container">
                <div class="section-header">
                    <h2>CRIAR NOVA COMPATIBILIDADE</h2>
                </div>

                <p style="margin-bottom: 20px; color: #666;">Digite o e-mail do usuário com quem você deseja se conectar. Se o usuário for encontrado, você poderá enviar um pedido de conexão.</p>

                <form method="POST" action="../php/criar_conexao.php" class="search-form">
                    <div class="search-bar" style="max-width: 500px; margin-bottom: 25px; border-radius: 50px; border: 1px solid var(--border-color); padding: 5px 5px 5px 15px;">
                        <input type="email" name="email_busca" placeholder="Digite o e-mail do usuário..." required style="border: none; outline: none; flex-grow: 1; background: transparent;">
                        <button type="submit" style="background: var(--primary-color); color: white; border: none; border-radius: 50%; width: 35px; height: 35px; cursor: pointer;"><i class="fas fa-search"></i></button>
                    </div>
                </form>

                <div class="search-results">
                    <?php echo $search_message; ?>
                    <?php if ($search_result): ?>
                        <h4>Usuário Encontrado:</h4>
                        <div class="compatibilidade-card">
                            <img src="<?php echo !empty($search_result['foto_perfil']) ? htmlspecialchars($search_result['foto_perfil']) : '../uploads/user_default.png'; ?>" alt="Foto de <?php echo htmlspecialchars($search_result['nome']); ?>" class="compatibilidade-photo">
                            <div class="compatibilidade-info">
                                <h3><?php echo htmlspecialchars($search_result['nome']); ?></h3>
                            </div>
                            <div class="compatibilidade-actions">
                                <a href="../php/processa_conexao2.php?id=<?php echo $search_result['id']; ?>" class="btn-primary">
                                    <i class="fas fa-paper-plane"></i> Enviar Pedido
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </section>
        </main>
    </div>
</body>
</html>