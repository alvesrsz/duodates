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
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Perfil - Duo Dates</title>
    <link rel="stylesheet" href="../css/login-feito.css">
    <link rel="stylesheet" href="../css/editar_perfil.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <header class="main-header">
        <div class="header-left">
            <a href="../php/login-feito.php" class="logo-link">
                <span class="logo">Duo Dates</span>
                <img src="../images/logoduodates.png" alt="Logo Duo Dates" class="logo-image">
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
                <i class="far fa-bell"></i>
             </div>
        </div>
    </header>

    <div class="profile-dashboard edit-profile-layout">
        <aside class="profile-sidebar">
            <div class="user-info">
                <img src="<?php echo $profilePhotoUrl; ?>" alt="Foto de Perfil" class="profile-photo" onerror="this.onerror=null; this.src='../images/perfil.png';">
                <h3><?php echo htmlspecialchars($user_data['nome']); ?></h3>
                <p><?php echo htmlspecialchars($user_data['email']); ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                          <a href="../php/login-feito.php"><i class="fas fa-home"></i> <span> Meu Perfil</span></a>
                    </li>
                    <li class="nav-item">
                       <a href="../php/mudar_essencia.php"> <i class="fas fa-clipboard-list"></i><span> Minha Essência</span></a>
                     </li>
                    <li>
                          <li class="nav-item">
                          <a href="../php/meus_dates.php"> <i class="fas fa-user-friends"></i><span> Meus Dates</span></a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="../php/editar_perfil_login_feito.php"><i class="fas fa-user-edit"></i><span> Editar Perfil</span></a>
                    </li>
                    <li class="nav-item">
                       <a href="../php/logout.php" ><i class="fas fa-sign-out-alt"></i><span> Sair</span></a>
                    </li>
                </ul>
            </nav>

             <div class="sidebar-chart">
                <div class="chart-title">Meus Lugares Ideais</div>
                <div class="chart-area">
                    <div class="chart-bar" style="height: 70%;"></div>
                    <div class="chart-bar" style="height: 50%;"></div>
                    <div class="chart-bar" style="height: 80%;"></div>
                    <div class="chart-bar" style="height: 40%;"></div>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <section class="section-container">
                <div class="section-header">
                    <h2>EDITAR INFORMAÇÕES DO PERFIL</h2>
                </div>
                
                <?php if ($success_message): ?>
                    <div class="message success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="message error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?></div>
                <?php endif; ?>

                <form action="../php/editar_perfil_login_feito.php" method="POST" enctype="multipart/form-data" class="edit-profile-form">
                    <div class="edit-grid">
                        <div class="photo-upload-section">
                            <label>Foto de Perfil</label>
                            <img src="<?php echo $profilePhotoUrl; ?>" alt="Preview da Foto" id="image-preview" class="profile-photo-preview" onerror="this.onerror=null; this.src='../images/perfil.png';">
                            <label for="foto_perfil" class="upload-label">
                                <i class="fas fa-camera"></i> Trocar Foto
                            </label>
                            <input type="file" id="foto_perfil" name="foto_perfil" accept="image/png, image/jpeg, image/gif" style="display: none;">
                        </div>
                        
                        <div class="info-section">
                            <div class="form-group">
                                <label for="nome">Nome de Usuário</label>
                                <input type="text" id="nome" name="nome" class="form-input" value="<?php echo htmlspecialchars($user_data['nome']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user_data['email']); ?>" disabled>
                                <small>O e-mail não pode ser alterado.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="../php/login-feito.php" class="btn-cancel">Cancelar</a>
                        <button type="submit" class="btn-salvar"><i class="fas fa-save"></i> Salvar Alterações</button>
                    </div>
                </form>
            </section>
        </main>
    </div>

    <script>
    document.getElementById('foto_perfil').addEventListener('change', function(event) {
        const preview = document.getElementById('image-preview');
        const file = event.target.files[0];
        if (file) {
            preview.src = URL.createObjectURL(file);
        }
    });
    </script>
</body>
</html>