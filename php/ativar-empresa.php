<?php
include('../conexao.php');
$mensagem = '';

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    // Procura o token em uma conta EMPRESARIAL que ainda não foi ativada
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE activation_token = ? AND is_email_confirmed = 0 AND tipo_conta = 'empresarial'");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        // Token válido, vamos ativar a conta!
        $stmt_update = $conn->prepare("UPDATE usuarios SET is_email_confirmed = 1, activation_token = NULL WHERE activation_token = ?");
        $stmt_update->bind_param("s", $token);
        $stmt_update->execute();

        // Mensagem de sucesso para a empresa
        $mensagem = "Seu e-mail foi verificado com sucesso! Seu cadastro está agora em análise por nossa equipe. Avisaremos por e-mail assim que for aprovado.";
    } else {
        // Token não encontrado, inválido ou já utilizado
        $mensagem = "Link de ativação inválido ou já utilizado.";
    }
} else {
    $mensagem = "Token de ativação não fornecido.";
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Ativação de Conta - DuoDates</title>
    <link rel="stylesheet" href="../css/login.css"> </head>
<body>
    <main class="login-main">
        <div class="login-container">
            <h1>Ativação de Conta Empresarial</h1>
            <p class="success-message" style="display:block; text-align: center;"><?php echo $mensagem; ?></p>
            <a href="../php/login.php" class="btn-login-submit" style="text-decoration: none; margin-top: 20px;">Ir para o Login</a>
        </div>
    </main>
</body>
</html>