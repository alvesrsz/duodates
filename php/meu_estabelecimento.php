<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (file_exists('conexao.php')) { include 'conexao.php'; } 
elseif (file_exists('../conexao.php')) { include '../conexao.php'; }

if (!isset($conn) && isset($conexao)) { $conn = $conexao; }

if (!isset($_SESSION['user_id'])) { die("Erro: Usuário não logado."); }
$empresa_id = $_SESSION['user_id'];

// Busca dados cadastrais da empresa
$nome_empresa = "Meu Estabelecimento";
$email_empresa = "";
$cnpj_empresa = "Não cadastrado";
$status_aprovacao = "Pendente";

$query_user = "SELECT nome, email, cnpj, status_aprovacao, id_local_associado FROM usuarios WHERE id = '$empresa_id' LIMIT 1";
$resultado_user = $conn->query($query_user);

if ($resultado_user && $resultado_user->num_rows > 0) {
    $user_data = $resultado_user->fetch_assoc();
    $nome_empresa = !empty($user_data['nome']) ? $user_data['nome'] : $nome_empresa;
    $email_empresa = $user_data['email'];
    $cnpj_empresa = !empty($user_data['cnpj']) ? $user_data['cnpj'] : $cnpj_empresa;
    $status_aprovacao = !empty($user_data['status_aprovacao']) ? $user_data['status_aprovacao'] : $status_aprovacao;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DuoDates - Meu Estabelecimento</title>
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
        .panel { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 700px; }
        .panel h3 { font-size: 18px; color: #8C1C30; margin-bottom: 20px; border-bottom: 2px solid #f4f7f6; padding-bottom: 10px; }
        .info-group { margin-bottom: 15px; }
        .info-group label { font-size: 13px; color: #888; display: block; margin-bottom: 5px; font-weight: 600; }
        .info-group p { font-size: 16px; color: #333; background: #F9FAFB; padding: 10px; border-radius: 6px; border: 1px solid #E5E7EB; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; display: inline-block; }
        .badge.aprovado { background: #d1fae5; color: #065f46; }
        .badge.pendente { background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>DuoDates<br><span style="font-size:14px; font-weight:normal;">Painel do Parceiro</span></h2>
        <a href="dashboard_empresa.php"><i class="fas fa-chart-line"></i> Visão Geral</a>
        <a href="meu_estabelecimento.php" class="active"><i class="fas fa-store"></i> Meu Estabelecimento</a>
        <a href="agendamentos.php"><i class="fas fa-calendar-check"></i> Agendamentos</a>
        <a href="avaliacoes.php"><i class="fas fa-star"></i> Avaliações</a>
    </div>
    <div class="main-content">
        <div class="header">
            <h1>Perfil do Estabelecimento</h1>
        </div>
        <div class="panel">
            <h3>Informações Cadastrais</h3>
            <div class="info-group">
                <label>Nome Comercial / Fantasia</label>
                <p><?php echo htmlspecialchars($nome_empresa); ?></p>
            </div>
            <div class="info-group">
                <label>E-mail de Contato</label>
                <p><?php echo htmlspecialchars($email_empresa); ?></p>
            </div>
            <div class="info-group">
                <label>CNPJ</label>
                <p><?php echo htmlspecialchars($cnpj_empresa); ?></p>
            </div>
            <div class="info-group">
                <label>Status da Conta</label>
                <span class="badge <?php echo ($status_aprovacao == 'aprovado' || $status_aprovacao == 'Aprovado') ? 'aprovado' : 'pendente'; ?>">
                    <?php echo htmlspecialchars($status_aprovacao); ?>
                </span>
            </div>
        </div>
    </div>
</body>
</html>