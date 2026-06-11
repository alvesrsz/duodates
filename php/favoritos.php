<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('../conexao.php');

// Garantir charset e capturar a sessão corretamente
$conn->set_charset("utf8mb4");
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0);

if ($user_id == 0) {
    header('Location: ../php/login.php');
    exit();
}

// CONSULTA
// Adicionado OR f.place_id = CAST(a.id_local AS CHAR) para pegar os favoritos antigos que salvaram por ID
$sql = "SELECT
            a.titulo,
            a.descricao,
            a.imagem_url,
            a.slug,
            a.link_botao
        FROM
            favoritos f
        JOIN
            locais a ON f.place_id = a.slug OR f.place_id = CAST(a.id_local AS CHAR)
        WHERE
            f.user_id = ?";

$stmt = $conn->prepare($sql);
if ($stmt === false) { die("Erro na preparação da consulta: " . $conn->error); }
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Favoritos</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/favoritos.css">
</head>
<body>

<header>
    <a href="login-feito.php" class="back-button" aria-label="Voltar">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
    </a>
    <h1>Meus Favoritos</h1>
    <button id="delete-all-btn" class="delete-all-btn" data-tooltip="Apagar todos os lugares">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
    </button>
</header>

<div class="container">
    <div id="favoritos-container">
        <?php if ($result->num_rows > 0): ?>
            <div class="favoritos-grid">
                <?php while($row = $result->fetch_assoc()): 
                    // Corrige o link da imagem caso seja URL externa (http) ou interna
                    $img = htmlspecialchars($row["imagem_url"]);
                    if (strpos($img, 'http') !== 0 && strpos($img, '../') !== 0) {
                        $img = '../' . $img; 
                    }
                ?>
                    <div class="favorito-item-wrapper" data-id="<?php echo htmlspecialchars($row['slug']); ?>">
                        <a href="<?php echo htmlspecialchars($row['link_botao']); ?>" class="favorito-card-link" target="_blank" rel="noopener noreferrer">
                            <div class="favorito-item">
                                <div class="info">
                                    <h3><?php echo htmlspecialchars($row["titulo"]); ?></h3>
                                    <p><?php echo htmlspecialchars($row["descricao"]); ?></p>
                                </div>
                                <img src="<?php echo $img; ?>" alt="Imagem de <?php echo htmlspecialchars($row["titulo"]); ?>">
                            </div>
                        </a>
                        <button class="unfavorite-btn" aria-label="Remover favorito">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-favorites-container">
                <p class='no-favorites-message'>Você ainda não favoritou nenhum local.</p>
                <a href="todoslocais.php" class="explore-button">Explorar Locais</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const favoritosContainer = document.getElementById('favoritos-container');
    
    favoritosContainer.addEventListener('click', function(e) {
        const unfavoriteBtn = e.target.closest('.unfavorite-btn');
        if (!unfavoriteBtn) return;
        
        const wrapper = unfavoriteBtn.closest('.favorito-item-wrapper');
        const placeId = wrapper.dataset.id;
        
        if (confirm('Tem certeza que deseja remover este local dos favoritos?')) {
            fetch('remover_favorito.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ place_id: placeId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    wrapper.style.transition = 'opacity 0.5s ease';
                    wrapper.style.opacity = '0';
                    setTimeout(() => {
                        wrapper.remove();
                        if (document.querySelectorAll('.favorito-item-wrapper').length === 0) {
                            favoritosContainer.innerHTML = `
                                <div class="no-favorites-container">
                                    <p class='no-favorites-message'>Você ainda não favoritou nenhum local.</p>
                                    <a href="todoslocais.php" class="explore-button">Explorar Locais</a>
                                </div>`;
                        }
                    }, 500);
                } else {
                    alert('Erro ao remover favorito: ' + data.message);
                }
            })
            .catch(error => console.error('Erro:', error));
        }
    });

    const deleteAllBtn = document.getElementById('delete-all-btn');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', () => {
            if (document.querySelectorAll('.favorito-item-wrapper').length === 0) {
                alert('Não há favoritos para remover.');
                return;
            }
            if (confirm('ATENÇÃO: Isso irá apagar TODOS os seus locais favoritos. Deseja continuar?')) {
                fetch('remover_favorito.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_all' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        favoritosContainer.innerHTML = `
                            <div class="no-favorites-container">
                                <p class='no-favorites-message'>Você ainda não favoritou nenhum local.</p>
                                <a href="todoslocais.php" class="explore-button">Explorar Locais</a>
                            </div>`;
                    } else {
                        alert('Erro ao apagar todos os favoritos: ' + data.message);
                    }
                })
                .catch(error => console.error('Erro:', error));
            }
        });
    }
});
</script>
</body>
</html>