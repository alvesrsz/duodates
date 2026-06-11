<?php
// HABILITA EXIBIÇÃO DE ERROS
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../conexao.php';

// Redireciona para a página de login se o usuário não estiver logado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

$id_usuario_logado = $_SESSION['user_id'];

// --- Busca os dados completos do usuário (incluindo a foto) ---
$sql_usuario = "SELECT nome, email, foto_perfil FROM usuarios WHERE id = ?";
$stmt_usuario = $conn->prepare($sql_usuario);
$stmt_usuario->bind_param("i", $id_usuario_logado);
$stmt_usuario->execute();
$resultado_usuario = $stmt_usuario->get_result();
$usuario = $resultado_usuario->fetch_assoc();

// Se a consulta não retornar nenhum usuário, destrói a sessão e redireciona para o login.
if (!$usuario) {
    session_destroy();
    header('Location: ../php/login.php?erro=usuario_nao_encontrado');
    exit();
}

// Define a foto de perfil
$foto_perfil = !empty($usuario['foto_perfil']) && file_exists($usuario['foto_perfil']) ? $usuario['foto_perfil'] : 'https://cdn-icons-png.flaticon.com/512/12225/12225881.png';

// --- Lógica para buscar as preferências ---
$sql_todas = "SELECT id_preferencia, nome FROM preferencias ORDER BY nome";
$resultado_todas = $conn->query($sql_todas);
$todas_preferencias = $resultado_todas->fetch_all(MYSQLI_ASSOC);

$sql_minhas = "SELECT id_preferencia FROM usuario_preferencias WHERE id_usuario = ?";
$stmt_minhas = $conn->prepare($sql_minhas);
$stmt_minhas->bind_param("i", $id_usuario_logado);
$stmt_minhas->execute();
$resultado_minhas = $stmt_minhas->get_result();
$minhas_preferencias_rows = $resultado_minhas->fetch_all(MYSQLI_ASSOC);
$minhas_preferencias_ids = array_column($minhas_preferencias_rows, 'id_preferencia');

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - DuoDates</title>
    <link rel="stylesheet" href="../css/perfil.css">
    <link rel="stylesheet" href="../css/lugares.css">
    <style>
        /* Estilo para o botão Cancelar */
        .action-btn-secondary {
            background-color: #6c757d; /* Um cinza para diferenciar */
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1em;
            text-align: center;
        }
        .action-btn-secondary:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <header>
        <a href="../index.php" class="back-button-link">
            <i class="arrow"></i>
        </a>
        <h1>DuoDates</h1>
    </header>

    <main class="container">
        <div class="profile-card">

            <?php if(isset($_SESSION['success_message'])): ?>
                <p class="success-message"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></p>
            <?php endif; ?>
            <?php if(isset($_SESSION['error_message'])): ?>
                <p class="error-message"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></p>
            <?php endif; ?>

            <div id="view-mode">
                <div class="profile-header">
                    <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de Perfil" class="profile-avatar">
                    <h2 class="profile-name"><?php echo htmlspecialchars($usuario['nome']); ?></h2>
                    <p class="profile-email"><?php echo htmlspecialchars($usuario['email']); ?></p>
                </div>
<div class="profile-actions">
    <button type="button" id="edit-btn" class="action-btn">Editar Perfil</button>
    <a href="../php/agenda.php" class="action-btn">Minha Agenda</a> 
    <a href="../php/alterar-senha.php" class="action-btn">Alterar Senha</a>
    <a href="../php/logout.php" class="action-btn-logout">Sair</a>
</div>
            </div>

            <div id="edit-mode" style="display: none;">
                <form action="../php/editar_perfil.php" method="POST" enctype="multipart/form-data">
                    <div class="profile-header editable">
                        <div class="profile-pic-container">
    <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de Perfil" id="profilePicPreview">
    <input type="file" name="foto_perfil" id="fileInput" accept="image/jpeg, image/png, image/gif" style="display: none;">
    <button type="button" id="changePicBtn" class="change-pic-btn">Alterar Foto</button>
</div>
                        
                        <div class="profile-info">
                            <label for="nome_input" class="input-label">Nome de Usuário</label>
                            <input type="text" name="nome" id="nome_input" class="profile-name-input" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                            <p class="profile-email"><?php echo htmlspecialchars($usuario['email']); ?></p>
                        </div>
                    </div>
                    <div class="profile-actions">
                        <button type="submit" class="action-btn">Salvar Alterações</button>
                        <button type="button" id="cancel-btn" class="action-btn-secondary">Cancelar</button>
                    </div>
                </form>
            </div>

        </div>

        <div class="profile-section">
            <h3 class="section-title">Meus Estilos de Date Favoritos</h3>
            <form action="../php/salvar_preferencias.php" method="POST">
                <div class="preferences-grid">
                    <?php foreach ($todas_preferencias as $pref): ?>
                        <label class="preference-item">
                            <input 
                                type="checkbox" 
                                name="preferencias[]" 
                                value="<?php echo $pref['id_preferencia']; ?>"
                                <?php if (in_array($pref['id_preferencia'], $minhas_preferencias_ids)) echo 'checked'; ?>
                            >
                            <?php echo htmlspecialchars($pref['nome']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="save-btn">Salvar Preferências</button>
            </form>
        </div>
    </main>

    <footer class="profile-footer">
        <p>&copy; <?php echo date("Y"); ?> DuoDates. Todos os direitos reservados.</p>
    </footer>

    <script>
    // --- Lógica para alternar entre os modos de visualização e edição ---
    const viewMode = document.getElementById('view-mode');
    const editMode = document.getElementById('edit-mode');
    const editBtn = document.getElementById('edit-btn');
    const cancelBtn = document.getElementById('cancel-btn');

    editBtn.addEventListener('click', () => {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    });

    cancelBtn.addEventListener('click', () => {
        editMode.style.display = 'none';
        viewMode.style.display = 'block';
        // Opcional: recarregar a página para resetar a foto caso o usuário tenha escolhido uma nova e cancelado
        location.reload(); 
    });

    // --- NOVA Lógica para o botão de alterar foto e preview ---
    const changePicBtn = document.getElementById('changePicBtn');
    const fileInput = document.getElementById('fileInput');
    const profilePicPreview = document.getElementById('profilePicPreview');

    // 1. Quando o botão "Alterar Foto" for clicado, acione o input de arquivo.
    changePicBtn.addEventListener('click', function() {
        fileInput.click();
    });

    // 2. Quando um arquivo for escolhido, atualize a pré-visualização.
    fileInput.addEventListener('change', function(event) {
        if (event.target.files && event.target.files[0]) {
            const reader = new FileReader();

            reader.onload = function(e) {
                profilePicPreview.src = e.target.result;
            }

            reader.readAsDataURL(event.target.files[0]);
        }
    });
</script>
</body>
</html>