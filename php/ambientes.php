<?php
session_start();
include('../conexao.php');

// LÓGICA PHP
$is_logged_in = isset($_SESSION['user_id']);
$favoritos_usuario = [];

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $sql_favoritos = "SELECT place_id FROM favoritos WHERE user_id = ?";
    $stmt_favoritos = $conn->prepare($sql_favoritos);
    if ($stmt_favoritos) {
        $stmt_favoritos->bind_param("i", $user_id);
        $stmt_favoritos->execute();
        $resultado_favoritos = $stmt_favoritos->get_result();
        while ($row = $resultado_favoritos->fetch_assoc()) {
            $favoritos_usuario[] = $row['place_id'];
        }
        $stmt_favoritos->close();
    }
}

// 1. NOME DA CATEGORIA ESPECÍFICA DESTA PÁGINA
$nome_categoria = 'Ambiente Mais Íntimo';

$sql_locais = "SELECT l.* FROM locais AS l JOIN categorias AS c ON l.id_categoria = c.id_categoria WHERE c.nome = ?";
$stmt_locais = $conn->prepare($sql_locais);
$stmt_locais->bind_param("s", $nome_categoria);
$stmt_locais->execute();
$resultado_locais = $stmt_locais->get_result();
$locais = $resultado_locais->fetch_all(MYSQLI_ASSOC);
$stmt_locais->close();
$conn->close();

$defaultPlaceholderUrl = "../images/placeholder_local.png"; 
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DuoDates - <?php echo htmlspecialchars($nome_categoria); ?></title>
    
    <link rel="stylesheet" href="../css/login-feito.css">
    <link rel="stylesheet" href="../css/lugares.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <header class="main-header">
        <div class="header-left">
          <a href="../index.php" class="logo-link">
            <span class="logo">Duo Dates</span>
            <img src="../images/logoduodates.png" alt="Coração" class="logo-image">
          </a>
        </div>
         <?php if ($is_logged_in): ?>
             <div class="header-pages">
                 <a href="../php/meuslugaresideais.php" title="Meus Lugares Ideais"><i class="fas fa-map-marker-alt"></i></a>
                 <a href="../php/meu_calendario.php" title="Meu Calendário"><i class="far fa-calendar-alt"></i></a>
                 <a href="../php/meus_dates.php" title="Meus Dates"><i class="fas fa-user-friends"></i></a>
                 <a href="../php/adicionar_local.php" title="Adicionar Local"><i class="fas fa-plus"></i></a>
             </div>
             <div class="header-icons">
                 <a href="../php/favoritos.php" title="Favoritos"><i class="far fa-heart"></i></a>
                 <div class="notification-icon-container"><i class="far fa-bell"></i></div>
             </div>
         <?php else: ?>
             <div class="header-pages">
                 <a href="../php/login.php" style="font-size: 1rem; font-weight: bold;">LOGIN</a>
                 <a href="../php/telacadastro.php" style="font-size: 1rem; font-weight: bold;">CADASTRO</a>
             </div>
         <?php endif; ?>
    </header>

    <main class="container">
        <section class="page-title">
            <h2><?php echo htmlspecialchars($nome_categoria); ?></h2>
            <p>Ideias para criar momentos especiais e fortalecer a conexão a dois.</p>
        </section>
        
        <section class="suggestions-grid">
            <?php if (count($locais) > 0): ?>
                <?php foreach ($locais as $local): ?>
                    <div class="card">
                        <button class="favorite-btn <?php echo in_array($local['slug'], $favoritos_usuario) ? 'favorited' : ''; ?>" 
                                data-id="<?php echo htmlspecialchars($local['slug']); ?>">
                            <i class="<?php echo in_array($local['slug'], $favoritos_usuario) ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                        
                        <?php 
                            $imgDisplay = $local['imagem_url'];
                            // Se não for link externo e não tiver ../, adiciona o prefixo
                            if (strpos($imgDisplay, 'http') === false && strpos($imgDisplay, '../') === false) {
                                $imgDisplay = '../' . ltrim($imgDisplay, '/');
                            }
                        ?>
                        <img src="<?php echo htmlspecialchars($imgDisplay); ?>" 
                             alt="<?php echo htmlspecialchars($local['titulo']); ?>"
                             onerror="this.onerror=null; this.src='<?php echo $defaultPlaceholderUrl; ?>';">
                        <div class="card-content">
                            <h3><?php echo htmlspecialchars($local['titulo']); ?></h3>
                            <p>
                                <?php echo nl2br(htmlspecialchars($local['descricao'])); ?>
                                <br><br>
                                <strong>Local:</strong> <?php echo htmlspecialchars($local['local_info']); ?>
                                <br>
                                <strong>Horário:</strong> <?php echo htmlspecialchars($local['horario_info']); ?>
                            </p>
                            <div class="card-actions">
                                <?php if (!empty($local['link_botao'])): ?>
                                    <a href="<?php echo htmlspecialchars($local['link_botao']); ?>" target="_blank" rel="noopener noreferrer" class="card-button"><?php echo htmlspecialchars($local['texto_botao']); ?></a>
                                <?php endif; ?>
                                <button class="card-button agendar-btn" data-id-local="<?php echo $local['id_local']; ?>" data-titulo-local="<?php echo htmlspecialchars($local['titulo']); ?>">Agendar Date</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
    
    <div class="modal-overlay" id="agendamento-modal-overlay">
        <div class="modal-container" id="agendamento-modal">
            <button class="modal-close-btn" id="agendamento-modal-close-btn">&times;</button>
            <h3 id="modal-title">Agendar Date</h3>
            <form id="agendamento-form">
                <input type="hidden" name="id_local" id="modal-id-local">
                <label for="modal-titulo-evento">Título do Evento:</label>
                <input type="text" name="titulo_evento" id="modal-titulo-evento" required>
                <label for="modal-data-agendada">Data e Hora:</label>
                <input type="datetime-local" name="data_agendada" id="modal-data-agendada" required>
                <label for="modal-notas">Notas (opcional):</label>
                <textarea name="notas" id="modal-notas" rows="3"></textarea>
                <button type="submit">Salvar Agendamento</button>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="login-modal-overlay">
        <div class="modal-container" id="login-modal">
            <button class="modal-close-btn" id="login-modal-close-btn">&times;</button>
            <h3>Faça Login Para Continuar</h3>
            <p>Você precisa estar conectado para favoritar locais e agendar dates.</p>
            <div class="login-modal-actions">
                <a href="../php/login.php" class="login-modal-btn login">Fazer Login</a>
                <a href="../php/telacadastro.php" class="login-modal-btn register">Criar Conta</a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const isUserLoggedIn = <?php echo json_encode($is_logged_in); ?>;

        // Modal Login
        const loginOverlay = document.getElementById('login-modal-overlay');
        const loginCloseBtn = document.getElementById('login-modal-close-btn');
        const openLoginModal = () => loginOverlay.classList.add('show');
        const closeLoginModal = () => loginOverlay.classList.remove('show');

        if(loginOverlay) {
            loginCloseBtn.addEventListener('click', closeLoginModal);
            loginOverlay.addEventListener('click', (e) => { if (e.target === loginOverlay) closeLoginModal(); });
        }
        
        // Favoritar
        document.querySelectorAll('.favorite-btn').forEach(button => {
            button.addEventListener('click', () => {
                if (!isUserLoggedIn) { openLoginModal(); return; }
                
                const placeId = button.dataset.id; 
                const isFavorited = button.classList.toggle('favorited');
                const icon = button.querySelector('i');
                
                if (isFavorited) { icon.classList.remove('far'); icon.classList.add('fas'); } 
                else { icon.classList.remove('fas'); icon.classList.add('far'); }

                fetch('../php/salvar_favorito.php', { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({place_id: placeId, is_favorited: isFavorited})
                }).then(response => response.json()).then(data => { 
                    if (data.status === 'error') { 
                        alert(data.message); button.classList.toggle('favorited'); 
                    }
                }).catch(() => { alert('Erro de comunicação.'); button.classList.toggle('favorited'); });
            });
        });

        // Agendamento
        const agendamentoOverlay = document.getElementById('agendamento-modal-overlay');
        const agendamentoModal = document.getElementById('agendamento-modal');
        const agendamentoCloseBtn = document.getElementById('agendamento-modal-close-btn');
        const agendamentoForm = document.getElementById('agendamento-form');
        
        if (agendamentoModal) {
            const modalTitle = document.getElementById('modal-title');
            const modalIdLocalInput = document.getElementById('modal-id-local');

            document.querySelectorAll('.agendar-btn').forEach(button => {
                button.addEventListener('click', function() {
                    if (!isUserLoggedIn) { openLoginModal(); return; }
                    const idLocal = this.dataset.idLocal;
                    const tituloLocal = this.dataset.tituloLocal;
                    modalIdLocalInput.value = idLocal;
                    modalTitle.textContent = `Agendar Date em: ${tituloLocal}`;
                    agendamentoOverlay.classList.add('show');
                });
            });

            const closeAgendamentoModal = () => { agendamentoOverlay.classList.remove('show'); };
            agendamentoCloseBtn.addEventListener('click', closeAgendamentoModal);
            agendamentoOverlay.addEventListener('click', (e) => { if (e.target === agendamentoOverlay) closeAgendamentoModal(); });

            agendamentoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());
                fetch('../php/salvar_agendamento.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                }).then(response => response.json()).then(result => {
                    if(result.status === 'success') {
                        alert('Date agendado com sucesso!');
                        closeAgendamentoModal();
                        agendamentoForm.reset();
                    } else { alert('Erro: ' + result.message); }
                }).catch(error => { alert('Erro de comunicação.'); });
            });
        }
    });
    </script>
</body>
</html>