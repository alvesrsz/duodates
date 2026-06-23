<?php
session_start();
include '../conexao.php'; // Inclui a conexão com o banco

// 1. Obter o Slug da URL e Validar
if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    echo "Erro: Local não especificado.";
    exit();
}
$slug = trim($_GET['slug']);

// 2. Buscar Detalhes do Local no Banco de Dados
$local = null;
$tags_local = [];

$sql_local = "SELECT l.id_local, l.titulo, l.descricao, l.imagem_url, l.local_info, l.horario_info, l.link_botao, l.texto_botao, c.nome as nome_categoria
              FROM locais l
              JOIN categorias c ON l.id_categoria = c.id_categoria
              WHERE l.slug = ?";
$stmt_local = $conn->prepare($sql_local);

if ($stmt_local) {
    $stmt_local->bind_param("s", $slug);
    $stmt_local->execute();
    $result_local = $stmt_local->get_result();
    $local = $result_local->fetch_assoc();
    $stmt_local->close();

    if ($local) {
        $id_local = $local['id_local'];
        $sql_tags = "SELECT t.nome_tag FROM tags t JOIN local_tags lt ON t.id_tag = lt.id_tag_fk WHERE lt.id_local_fk = ?";
        $stmt_tags = $conn->prepare($sql_tags);
        if ($stmt_tags) {
            $stmt_tags->bind_param("i", $id_local);
            $stmt_tags->execute();
            $result_tags = $stmt_tags->get_result();
            while ($row_tag = $result_tags->fetch_assoc()) {
                $tags_local[] = $row_tag['nome_tag'];
            }
            $stmt_tags->close();
        } else {
             error_log("Erro ao preparar query de tags: " . $conn->error);
        }
    }

} else {
    error_log("Erro ao preparar query do local: " . $conn->error);
    echo "Erro ao buscar informações do local.";
    exit();
}

// 3. Verificar se o Local Foi Encontrado
if (!$local) {
    echo "Local não encontrado.";
    $conn->close();
    exit();
}

// 4. Verificar Login e Favorito
$is_logged_in = isset($_SESSION['user_id']); 
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;
$isFavorito = false; 

if ($is_logged_in && $local) {
    $sql_fav = "SELECT id FROM favoritos WHERE user_id = ? AND place_id = ?";
    $stmt_fav = $conn->prepare($sql_fav);
    $stmt_fav->bind_param("is", $user_id, $local['slug']); 
    $stmt_fav->execute();
    $result_fav = $stmt_fav->get_result();
    if ($result_fav->num_rows > 0) {
        $isFavorito = true; 
    }
    $stmt_fav->close();
}

$conn->close();
$defaultPlaceholderUrl = "../images/placeholder_local.png"; // Verifique se esta imagem existe

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($local['titulo']); ?> - DuoDates</title>
    
    <link rel="stylesheet" href="../css/lugares.css"> 
    <link rel="stylesheet" href="../css/style_detalhe.css"> 
    
    <link rel="stylesheet" href="../css/login-feito.css">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <a href="javascript:history.back()" class="btn-voltar" title="Voltar" style="position: absolute; top: 120px; left: 20px; z-index: 100; background: white; padding: 10px; border-radius: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.2); color: var(--primary-color);">
        <i class="fas fa-arrow-left"></i>
    </a>

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
                 <a href="../php/adicionar-lugar.php" title="Adicionar Local"><i class="fas fa-plus"></i></a>
             </div>
             <div class="header-icons">
                 <a href="../php/favoritos.php" title="Favoritos"><i class="far fa-heart"></i></a>
                 <div class="notification-icon-container">
                    <i class="far fa-bell"></i>
                 </div>
             </div>
         <?php else: ?>
             <div class="header-pages">
                 <a href="../php/login.php" style="font-size: 1rem; font-weight: bold;">LOGIN</a>
                 <a href="../php/telacadastro.php" style="font-size: 1rem; font-weight: bold;">CADASTRO</a>
             </div>
         <?php endif; ?>
    </header>


    <div class="detalhe-container">
        <div class="imagem-destaque">
            <?php 
                // --- CORREÇÃO DE CAMINHO DA IMAGEM ---
                $imgDisplay = $local['imagem_url'];
                // Se não for link externo (http) e não começar com ../, adiciona ../
                if (strpos($imgDisplay, 'http') === false && strpos($imgDisplay, '../') === false) {
                    $imgDisplay = ltrim($imgDisplay, '/');
                    $imgDisplay = '../' . $imgDisplay;
                }
            ?>
            <img src="<?php echo htmlspecialchars($imgDisplay); ?>"
                 alt="<?php echo htmlspecialchars($local['titulo']); ?>"
                 onerror="this.onerror=null; this.src='<?php echo $defaultPlaceholderUrl; ?>';">
                 
            <?php if ($is_logged_in): ?>
                <button class="btn-favorito favorite-btn <?php echo $isFavorito ? 'favorited' : ''; ?>" 
                        data-id="<?php echo htmlspecialchars($local['slug']); ?>">
                    
                    <?php if ($isFavorito): ?>
                        <i class="fas fa-heart"></i> Favoritado
                    <?php else: ?>
                        <i class="far fa-heart"></i> Favoritar
                    <?php endif; ?>
                </button>
            <?php endif; ?>
            </div>

        <div class="conteudo-local">
            <h1><?php echo htmlspecialchars($local['titulo']); ?></h1>
            <span class="categoria-local"><?php echo htmlspecialchars($local['nome_categoria']); ?></span>

            <p class="descricao-local"><?php echo nl2br(htmlspecialchars($local['descricao'])); ?></p>

            <div class="info-local">
                <div>
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($local['local_info']); ?></span>
                </div>
                <?php if (!empty($local['horario_info'])): ?>
                <div>
                    <i class="far fa-clock"></i>
                    <span><?php echo htmlspecialchars($local['horario_info']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($tags_local)): ?>
                <div class="tags-local">
                    <h4>Tags:</h4>
                    <?php foreach ($tags_local as $tag): ?>
                        <span class="tag-item"><?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="acoes-local">
                <?php if (!empty($local['link_botao']) && !empty($local['texto_botao'])): ?>
                    <a href="<?php echo htmlspecialchars($local['link_botao']); ?>" target="_blank" class="btn btn-principal">
                        <?php echo htmlspecialchars($local['texto_botao']); ?> <i class="fas fa-external-link-alt"></i>
                    </a>
                <?php endif; ?>

                <button class="btn btn-secundario agendar-btn" 
                        data-id-local="<?php echo $local['id_local']; ?>" 
                        data-titulo-local="<?php echo htmlspecialchars($local['titulo']); ?>">
                    <i class="far fa-calendar-plus"></i> Agendar Date
                </button>
            </div>

        </div>
    </div>

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

    <?php // include '../php/footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const isUserLoggedIn = <?php echo json_encode($is_logged_in); ?>;

        // --- LÓGICA DO MODAL DE LOGIN ---
        const loginOverlay = document.getElementById('login-modal-overlay');
        const loginModal = document.getElementById('login-modal');
        const loginCloseBtn = document.getElementById('login-modal-close-btn');

        const openLoginModal = () => {
            if (loginOverlay) loginOverlay.classList.add('show');
        }
        const closeLoginModal = () => {
            if (loginOverlay) loginOverlay.classList.remove('show');
        }

        if(loginOverlay && loginCloseBtn) { 
            loginCloseBtn.addEventListener('click', closeLoginModal);
            loginOverlay.addEventListener('click', (e) => {
                if (e.target === loginOverlay) closeLoginModal();
            });
        }
        
        // --- LÓGICA DE FAVORITAR ---
        const favButton = document.querySelector('.favorite-btn');
        if (favButton) {
            favButton.addEventListener('click', () => {
                if (!isUserLoggedIn) {
                    openLoginModal();
                    return;
                }
                
                const placeId = favButton.dataset.id; 
                const isFavorited = favButton.classList.toggle('favorited');
                
                const icon = favButton.querySelector('i');
                if (isFavorited) {
                    icon.classList.remove('far');
                    icon.classList.add('fas'); 
                    favButton.innerHTML = '<i class="fas fa-heart"></i> Favoritado';
                } else {
                    icon.classList.remove('fas');
                    icon.classList.add('far');
                    favButton.innerHTML = '<i class="far fa-heart"></i> Favoritar';
                }

                fetch('../php/salvar_favorito.php', { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({place_id: placeId, is_favorited: isFavorited})
                })
                .then(response => response.json())
                .then(data => { 
                    if (data.status === 'error') { 
                        alert(data.message);
                        favButton.classList.toggle('favorited');
                         if (isFavorited) {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            favButton.innerHTML = '<i class="far fa-heart"></i> Favoritar';
                         } else {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            favButton.innerHTML = '<i class="fas fa-heart"></i> Favoritado';
                         }
                    }
                })
                .catch((error) => {
                    console.error('Erro no fetch de favorito:', error);
                    alert('Erro de comunicação. Tente novamente.');
                    favButton.classList.toggle('favorited');
                });
            });
        }

        // --- LÓGICA DO MODAL DE AGENDAMENTO ---
        const agendamentoOverlay = document.getElementById('agendamento-modal-overlay');
        const agendamentoModal = document.getElementById('agendamento-modal');
        const agendamentoCloseBtn = document.getElementById('agendamento-modal-close-btn');
        const agendamentoForm = document.getElementById('agendamento-form');
        
        if (agendamentoModal && agendamentoCloseBtn && agendamentoForm) { 
            const modalTitle = document.getElementById('modal-title');
            const modalIdLocalInput = document.getElementById('modal-id-local');
            const agendarBtn = document.querySelector('.agendar-btn'); 
            
            if (agendarBtn) {
                agendarBtn.addEventListener('click', function() {
                    if (!isUserLoggedIn) {
                        openLoginModal();
                        return;
                    }
                    
                    const idLocal = this.dataset.idLocal;
                    const tituloLocal = this.dataset.tituloLocal;
                    
                    modalIdLocalInput.value = idLocal;
                    modalTitle.textContent = `Agendar Date em: ${tituloLocal}`;
                    agendamentoOverlay.classList.add('show');
                });
            }

            const closeAgendamentoModal = () => {
                agendamentoOverlay.classList.remove('show');
            };

            agendamentoCloseBtn.addEventListener('click', closeAgendamentoModal);
            agendamentoOverlay.addEventListener('click', (e) => {
                if (e.target === agendamentoOverlay) {
                    closeAgendamentoModal();
                }
            });

            agendamentoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());

                fetch('../php/salvar_agendamento.php', { 
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(result => {
                    if(result.status === 'success') {
                        alert('Date agendado com sucesso!');
                        closeAgendamentoModal();
                        agendamentoForm.reset();
                    } else {
                        alert('Erro ao agendar: ' + (result.message || 'Erro desconhecido.')); 
                    }
                })
                .catch(error => {
                    console.error('Erro no fetch de agendamento:', error);
                    alert('Ocorreu um erro de comunicação ao tentar agendar.');
                });
            });
        }
    });
    </script>

</body>
</html>