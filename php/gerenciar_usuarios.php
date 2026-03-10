<?php
session_start();
include 'conexao.php';

// --- VERIFICAÇÃO DE SEGURANÇA COMPLETA ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// O restante do seu código para buscar os usuários continua o mesmo...
$sql_usuarios = "SELECT id, nome, email, tipo_conta, criado_em FROM usuarios ORDER BY nome ASC";
$resultado_usuarios = $conn->query($sql_usuarios);
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - DuoDates Admin</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DuoDates Admin</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="admin.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="gerenciar_usuarios.php" class="nav-item active"><i class="fas fa-users"></i> Gerenciar Usuários</a>
                <a href="#" class="nav-item"><i class="fas fa-map-marker-alt"></i> Gerenciar Locais</a>
                <a href="#" class="nav-item"><i class="fas fa-calendar-alt"></i> Ver Agendamentos</a>
                <a href="index.php" class="nav-item"><i class="fas fa-globe"></i> Ver Site</a>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h3>Gerenciar Usuários</h3>
                <div class="header-actions">
                    <a href="perfil.php">Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</a>
                </div>
            </header>

            <?php if(isset($_SESSION['success_message'])): ?>
                <div class="message success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error_message'])): ?>
                <div class="message error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
            <?php endif; ?>

            <div class="content-grid">
                <div class="panel large">
                    <h4>Todos os Usuários</h4>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Tipo de Conta</th>
                                <th>Data de Cadastro</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultado_usuarios->num_rows > 0): ?>
                                <?php while($usuario = $resultado_usuarios->fetch_assoc()): ?>
                                <tr id="user-row-<?php echo $usuario['id']; ?>">
                                    <td><?php echo $usuario['id']; ?></td>
                                    <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($usuario['tipo_conta'])); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($usuario['criado_em'])); ?></td>
                                    <td class="actions-cell">
                                        <a href="editar_usuario.php?id=<?php echo $usuario['id']; ?>" class="action-btn edit">Editar</a>
                                        <button class="action-btn delete" data-id="<?php echo $usuario['id']; ?>">Deletar</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6">Nenhum usuário encontrado.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('.action-btn.delete');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.id;
                
                if (confirm('Tem certeza que deseja deletar este usuário? Esta ação não pode ser desfeita.')) {
                    fetch('deletar_usuario.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: userId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            document.getElementById('user-row-' + userId).remove();
                            alert(data.message);
                        } else {
                            alert('Erro: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Ocorreu um erro de comunicação.');
                    });
                }
            });
        });
    });
    </script>
</body>
</html>