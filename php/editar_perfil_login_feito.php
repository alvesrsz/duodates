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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/shell.css">
    <link rel="stylesheet" href="../css/editar_perfil.css">
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
      <div class="ni" onclick="window.location.href='meus_dates.php'"><i class="ti ti-heart-handshake"></i> Meus dates</div>
      <div class="ni" onclick="window.location.href='todoslocais.php'"><i class="ti ti-map"></i> Locais</div>
      <div class="ni active" onclick="window.location.href='editar_perfil_login_feito.php'"><i class="ti ti-user-edit"></i> Editar perfil</div>
      <div class="ndiv"></div>
      <div class="ni" onclick="window.location.href='logout.php'"><i class="ti ti-logout"></i> Sair</div>
    </nav>

    <main class="main">
      <div class="card">
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
      </div><!-- .card -->
    </main>

    <aside class="rp">
      <div class="rp-section">
        <div class="rp-title"><i class="fas fa-ticket-alt"></i> Próximos Eventos em Brasília</div>
        <?php @include '../php/buscar_ticketmaster.php'; ?>
        <div class="events-list">
          <?php if (isset($eventosFormatados['error'])): ?>
            <div class="event-item" style="color:var(--text-mid);font-size:.82rem"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($eventosFormatados['error']); ?></div>
          <?php elseif (isset($eventosFormatados['info'])): ?>
            <div class="event-item" style="color:var(--text-mid);font-size:.82rem"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($eventosFormatados['info']); ?></div>
          <?php elseif (!empty($eventosFormatados)): ?>
            <?php foreach ($eventosFormatados as $evt): ?>
              <a href="<?php echo htmlspecialchars($evt['url']); ?>" target="_blank" class="event-item">
                <div class="event-details">
                  <span class="event-name"><?php echo htmlspecialchars($evt['nome']); ?></span>
                  <span class="event-info"><i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($evt['dataHora']); ?> | <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($evt['local']); ?></span>
                </div>
                <div class="event-link-icon"><i class="fas fa-external-link-alt"></i></div>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="event-item" style="color:var(--text-mid);font-size:.82rem"><i class="fas fa-info-circle"></i> Não foi possível carregar os eventos.</div>
          <?php endif; ?>
        </div>
      </div>
      <a href="../php/meu_calendario.php" class="lbtn"><i class="ti ti-calendar"></i> Ver calendário completo</a>
    </aside>

  </div>
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