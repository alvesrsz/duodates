<?php
session_start();
include('../conexao.php');

$favoritos_usuario = [];
if (isset($_SESSION['user_id'])) {
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

// 1. ALTERAÇÃO PRINCIPAL AQUI: Define o nome da categoria para buscar no banco
$nome_categoria = 'Pet-Friendly'; 
$sql_locais = "SELECT l.* FROM locais AS l JOIN categorias AS c ON l.id_categoria = c.id_categoria WHERE c.nome = ?";
$stmt_locais = $conn->prepare($sql_locais);
$stmt_locais->bind_param("s", $nome_categoria);
$stmt_locais->execute();
$resultado_locais = $stmt_locais->get_result();
$locais = $resultado_locais->fetch_all(MYSQLI_ASSOC);
$stmt_locais->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DuoDates - Pet-Friendly</title> 
    <link rel="stylesheet" href="../css/lugares.css">
    <style>
        /* O seu CSS do modal continua o mesmo aqui */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center; }
        .modal-container { display: none; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); z-index: 1001; width: 90%; max-width: 500px; position: relative; flex-direction: column; }
        .modal-container.show, .modal-overlay.show { display: flex; }
        .modal-close-btn { position: absolute; top: 15px; right: 15px; font-size: 24px; font-weight: bold; cursor: pointer; border: none; background: none; }
        .modal-container h3 { margin-top: 0; }
        #agendamento-form label { display: block; margin-top: 15px; font-weight: bold; }
        #agendamento-form input, #agendamento-form textarea { width: 100%; padding: 8px; margin-top: 5px; border-radius: 4px; border: 1px solid #ccc; box-sizing: border-box; }
        #agendamento-form button { width: 100%; padding: 10px; margin-top: 20px; border: none; border-radius: 4px; background-color: #E94D73; color: white; font-size: 16px; cursor: pointer; }
        .card-actions { display: flex; gap: 10px; margin-top: 15px; align-items: center; }
    </style>
</head>
<body>
    <header>
        <a href="../index.php" class="back-button-link"><i class="arrow"></i></a>
        <h1>DuoDates</h1>
    </header>
    <main class="container">
        <section class="page-title">
            <h2>Pet-Friendly</h2>
            <p>Sugestões de lugares incríveis para vocês e seus pets aproveitarem juntos em Brasília.</p>
        </section>
        <section class="suggestions-grid">
            <?php foreach ($locais as $local): ?>
                <div class="card">
                    <img src="<?php echo htmlspecialchars($local['imagem_url']); ?>" alt="<?php echo htmlspecialchars($local['titulo']); ?>">
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
                            <a href="<?php echo htmlspecialchars($local['link_botao']); ?>" target="_blank" rel="noopener noreferrer" class="card-button"><?php echo htmlspecialchars($local['texto_botao']); ?></a>
                            <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="card-button agendar-btn" data-id-local="<?php echo $local['id_local']; ?>" data-titulo-local="<?php echo htmlspecialchars($local['titulo']); ?>">Agendar Date</button>
                                <?php $is_favorited_class = in_array($local['slug'], $favoritos_usuario) ? 'favorited' : ''; ?>
                                <button class="favorite-btn <?php echo $is_favorited_class; ?>" data-id="<?php echo htmlspecialchars($local['slug']); ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
    
    <div class="modal-overlay" id="agendamento-modal-overlay"></div>
    <div class="modal-container" id="agendamento-modal">
        <button class="modal-close-btn" id="modal-close-btn">&times;</button>
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

    <footer><p>&copy; <?php echo date("Y"); ?> DuoDates. Todos os direitos reservados.</p></footer>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // ... (todo o seu script de favoritar e agendar permanece igual)
        // ... (eu omiti para encurtar a resposta, mas você deve manter o seu)
        const favoriteButtons = document.querySelectorAll('.favorite-btn');
        favoriteButtons.forEach(button => { /* ... (código de favoritar) ... */ });

        const modal = document.getElementById('agendamento-modal');
        const overlay = document.getElementById('agendamento-modal-overlay');
        const closeBtn = document.getElementById('modal-close-btn');
        const form = document.getElementById('agendamento-form');
        const modalTitle = document.getElementById('modal-title');
        const modalIdLocalInput = document.getElementById('modal-id-local');

        document.querySelectorAll('.agendar-btn').forEach(button => {
            button.addEventListener('click', function() {
                modalIdLocalInput.value = this.dataset.idLocal;
                modalTitle.textContent = `Agendar Date em: ${this.dataset.tituloLocal}`;
                modal.classList.add('show');
                overlay.classList.add('show');
            });
        });

        const closeModal = () => {
            modal.classList.remove('show');
            overlay.classList.remove('show');
        };

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', closeModal);

        form.addEventListener('submit', function(e) {
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
                    closeModal();
                    form.reset();
                } else {
                    alert('Erro ao agendar: ' + result.message);
                }
            });
        });
    });
    </script>
</body>
</html>