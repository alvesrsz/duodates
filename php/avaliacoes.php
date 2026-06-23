<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (file_exists('conexao.php')) { include 'conexao.php'; } 
elseif (file_exists('../conexao.php')) { include '../conexao.php'; }

if (!isset($conn) && isset($conexao)) { $conn = $conexao; }
if (!isset($_SESSION['user_id'])) { die("Erro: Usuário não logado."); }
$empresa_id = $_SESSION['user_id'];

$avaliacao_media = 4.8;
$id_local = 0;

// Busca a nota média dinâmica se houver coluna correspondente
$query_user = "SELECT id_local_associado FROM usuarios WHERE id = '$empresa_id' LIMIT 1";
$res_user = $conn->query($query_user);
if ($res_user && $res_user->num_rows > 0) {
    $id_local = (int)$res_user->fetch_assoc()['id_local_associado'];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DuoDates - Avaliações dos Clientes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/empresa.css">
</head>
<body>
    <div class="sidebar">
        <h2>DuoDates<span>Painel do Parceiro</span></h2>
        <a href="dashboard_empresa.php"><i class="fas fa-chart-line"></i> Visão Geral</a>
        <a href="meu_estabelecimento.php"><i class="fas fa-store"></i> Meu Estabelecimento</a>
        <a href="agendamentos.php"><i class="fas fa-calendar-check"></i> Agendamentos</a>
        <a href="avaliacoes.php" class="active"><i class="fas fa-star"></i> Avaliações</a>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Avaliações do Estabelecimento</h1>
        </div>
        
        <div class="rating-box">
            <i class="fas fa-star"></i>
            <div>
                <div class="value"><?php echo number_format($avaliacao_media, 1); ?> / 5.0</div>
                <div style="font-size:13px; opacity:0.9;">Sua reputação na plataforma</div>
            </div>
        </div>

        <h2 class="feedbacks-title">Feedbacks Recentes</h2>

        <div class="review-card">
            <div class="review-header">
                <span class="client-name">Beatriz Souza</span>
                <span class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></span>
            </div>
            <p class="comment">"Ambiente maravilhoso, perfeito para um encontro de casal. Fomos muito bem atendidos e a reserva funcionou perfeitamente!"</p>
        </div>

        <div class="review-card">
            <div class="review-header">
                <span class="client-name">Marcos Oliveira</span>
                <span class="stars"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="far fa-star"></i></span>
            </div>
            <p class="comment">"Ótima experiência. O local cumpre exatamente o que promete no perfil do app. Recomendo."</p>
        </div>
    </div>
</body>
</html>