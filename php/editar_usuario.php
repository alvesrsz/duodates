<?php
session_start();
include '../conexao.php';

// --- VERIFICAÇÃO DE SEGURANÇA COMPLETA ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Pega o ID do usuário da URL (?id=...)
$id_usuario_para_editar = $_GET['id'] ?? null;

if (!$id_usuario_para_editar) {
    // Se não houver ID, redireciona de volta para a lista
    header('Location: ../php/gerenciar_usuarios.php');
    exit();
}

// Busca os dados atuais do usuário no banco
$sql = "SELECT id, nome, email, tipo_conta FROM usuarios WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario_para_editar);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

// Se o usuário não for encontrado, redireciona de volta
if (!$usuario) {
    header('Location: ../php/gerenciar_usuarios.php');
    exit();
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - DuoDates Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DuoDates Admin</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="../php/admin.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../php/gerenciar_usuarios.php" class="nav-item active"><i class="fas fa-users"></i> Gerenciar Usuários</a>
                <a href="#" class="nav-item"><i class="fas fa-map-marker-alt"></i> Gerenciar Locais</a>
                <a href="#" class="nav-item"><i class="fas fa-calendar-alt"></i> Ver Agendamentos</a>
                <a href="../index.php" class="nav-item"><i class="fas fa-globe"></i> Ver Site</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h3>Editando Usuário: <?php echo htmlspecialchars($usuario['nome']); ?></h3>
            </header>

            <div class="content-grid">
                <div class="panel large">
                    <form action="../php/processar_edicao_usuario.php" method="POST" class="edit-form">
                        <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">

                        <div class="form-group">
                            <label for="nome">Nome:</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="tipo_conta">Tipo de Conta:</label>
                            <select id="tipo_conta" name="tipo_conta">
                                <option value="usuario" <?php if ($usuario['tipo_conta'] == 'usuario') echo 'selected'; ?>>Usuário</option>
                                <option value="empresarial" <?php if ($usuario['tipo_conta'] == 'empresarial') echo 'selected'; ?>>Empresarial</option>
                                <option value="admin" <?php if ($usuario['tipo_conta'] == 'admin') echo 'selected'; ?>>Admin</option>

                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="action-btn save">Salvar Alterações</button>
                            <a href="../php/gerenciar_usuarios.php" class="action-btn cancel">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>