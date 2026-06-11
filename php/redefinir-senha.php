<?php
// redefinir-senha.php
session_start();
include('../conexao.php');

$token = $_GET['token'] ?? '';
$mensagem_erro = '';
$mensagem_sucesso = '';
$token_valido = false;

if (empty($token)) {
    $mensagem_erro = "Token inválido ou ausente.";
} else {
    // Verifica se o token existe e não expirou
    $stmt = $conn->prepare("SELECT email FROM usuarios WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $token_valido = true;
        $usuario = $result->fetch_assoc();
        $email_usuario = $usuario['email'];
    } else {
        $mensagem_erro = "Token inválido, expirado ou já utilizado. Por favor, solicite um novo link.";
    }
    $stmt->close();
}

if ($token_valido && $_SERVER["REQUEST_METHOD"] == "POST") {
    $nova_senha = $_POST['nova_senha'];
    $confirmar_nova_senha = $_POST['confirmar_nova_senha'];

    if ($nova_senha !== $confirmar_nova_senha) {
        $mensagem_erro = "As senhas não correspondem.";
    } elseif (strlen($nova_senha) < 6) {
        $mensagem_erro = "A nova senha deve ter no mínimo 6 caracteres.";
    } else {
        // Hash da nova senha
        $hash_nova_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
        
        // Atualiza a senha e anula o token para não ser reutilizado
        $update_stmt = $conn->prepare("UPDATE usuarios SET senha = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE email = ?");
        $update_stmt->bind_param("ss", $hash_nova_senha, $email_usuario);
        
        if ($update_stmt->execute()) {
            $mensagem_sucesso = "Senha alterada com sucesso! Você já pode fazer o login.";
            $token_valido = false; // Esconde o formulário após o sucesso
        } else {
            $mensagem_erro = "Ocorreu um erro ao atualizar a senha.";
        }
        $update_stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Redefinir Senha - DuoDates</title>
    <link rel="stylesheet" href="../css/login.css" />
</head>
<body>
    <main class="login-main">
        <div class="login-container">
            <h1>Crie sua Nova Senha</h1>

            <?php if (!empty($mensagem_erro)): ?>
                <p class="error-message"><?php echo $mensagem_erro; ?></p>
            <?php endif; ?>
            <?php if (!empty($mensagem_sucesso)): ?>
                <p class="success-message"><?php echo $mensagem_sucesso; ?></p>
                <a href="../php/login.php" class="btn-login-submit" style="text-decoration:none; text-align:center; display:block; margin-top:1rem;">IR PARA LOGIN</a>
            <?php endif; ?>

            <?php if ($token_valido): ?>
            <form method="POST" action="../php/redefinir-senha.php?token=<?php echo htmlspecialchars($token); ?>">
                <div class="input-group">
                    <label for="nova_senha">Nova Senha</label>
                    <input type="password" id="nova_senha" name="nova_senha" required />
                </div>
                <div class="input-group">
                    <label for="confirmar_nova_senha">Confirme a Nova Senha</label>
                    <input type="password" id="confirmar_nova_senha" name="confirmar_nova_senha" required />
                </div>
                <button type="submit" class="btn-login-submit">ALTERAR SENHA</button>
            </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>