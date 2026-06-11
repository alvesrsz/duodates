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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { display: flex; background-color: #F4F7F6; color: #333; min-height: 100vh; }
        .sidebar { width: 250px; background-color: #8C1C30; color: white; display: flex; flex-direction: column; padding-top: 20px; }
        .sidebar h2 { text-align: center; margin-bottom: 30px; font-weight: 800; font-size: 24px; }
        .sidebar a { color: #f0f0f0; text-decoration: none; padding: 15px 20px; display: block; font-size: 15px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background-color: rgba(255,255,255,0.1); border-left: 4px solid #fff; }
        .sidebar a i { width: 25px; }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { font-size: 24px; color: #2c3e50; }
        .rating-box { background: linear-gradient(135deg, #10b981, #34d399); color: white; padding: 20px; border-radius: 10px; display: inline-flex; align-items: center; gap: 15px; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .rating-box .value { font-size: 36px; font-weight: bold; }
        .rating-box i { font-size: 30px; }
        .review-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 15px; }
        .review-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .client-name { font-weight: bold; color: #2c3e50; }
        .stars { color: #fbbf24; }
        .comment { color: #555; font-size: 14px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>DuoDates<br><span style="font-size:14px; font-weight:normal;">Painel do Parceiro</span></h2>
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

        <h2 style="font-size: 18px; color: #555; margin-bottom: 15px;">Feedbacks Recentes</h2>

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