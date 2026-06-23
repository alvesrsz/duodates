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
    <link rel="stylesheet" href="../css/empresa.css">
</head>
<body>
    <div class="sidebar">
        <h2>DuoDates<span>Painel do Parceiro</span></h2>
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