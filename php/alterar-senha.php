<?php
session_start();

// Se o usuário não estiver logado, não pode alterar a senha. Redireciona para o login.
if (!isset($_SESSION['usuario'])) {
    header('Location: ../php/login.php');
    exit();
}

include('../conexao.php'); // Inclui a conexão com o banco

$mensagem_sucesso = '';
$mensagem_erro = '';

// Verifica se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_nova_senha = $_POST['confirmar_nova_senha'];
    $email_usuario = $_SESSION['email']; // Pega o email do usuário logado na sessão

    // Validações básicas
    if (empty($senha_atual) || empty($nova_senha) || empty($confirmar_nova_senha)) {
        $mensagem_erro = "Todos os campos são obrigatórios.";
    } elseif ($nova_senha !== $confirmar_nova_senha) {
        $mensagem_erro = "A nova senha e a confirmação não correspondem.";
    } elseif (strlen($nova_senha) < 6) {
        $mensagem_erro = "A nova senha deve ter no mínimo 6 caracteres.";
    } else {
        // Busca a senha atual hashada no banco de dados
        $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email_usuario);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        $stmt->close();

        // Verifica se a senha atual fornecida está correta
        if ($usuario && password_verify($senha_atual, $usuario['senha'])) {
            // A senha atual está correta, então podemos atualizar para a nova.
            
            // Cria um novo hash para a nova senha
            $hash_nova_senha = password_hash($nova_senha, PASSWORD_DEFAULT);

            // Atualiza a senha no banco de dados
            $update_stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
            $update_stmt->bind_param("ss", $hash_nova_senha, $email_usuario);
            
            if ($update_stmt->execute()) {
                $mensagem_sucesso = "Senha alterada com sucesso!";
            } else {
                $mensagem_erro = "Ocorreu um erro ao atualizar a senha. Tente novamente.";
            }
            $update_stmt->close();
        } else {
            // A senha atual fornecida está incorreta
            $mensagem_erro = "A senha atual está incorreta.";
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Alterar Senha - DuoDates</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/login.css" />
</head>
<body>
  <header class="login-header">
    <div class="logo-container">
      <span class="logo-text">DuoDates</span>
    </div>
  </header>

  <main class="login-main">
    <div class="login-container">
      <form class="login-form" method="POST" action="../php/alterar-senha.php">
        <h1>Alterar sua Senha</h1>

        <?php if (!empty($mensagem_sucesso)): ?>
            <p class="success-message"><?php echo $mensagem_sucesso; ?></p>
        <?php endif; ?>
        <?php if (!empty($mensagem_erro)): ?>
            <p class="error-message"><?php echo $mensagem_erro; ?></p>
        <?php endif; ?>

        <div class="input-group">
          <label for="senha_atual">Senha Atual</label>
          <input type="password" id="senha_atual" name="senha_atual" required />
        </div>
        <div class="input-group">
          <label for="nova_senha">Nova Senha</label>
          <input type="password" id="nova_senha" name="nova_senha" required />
        </div>
        <div class="input-group">
          <label for="confirmar_nova_senha">Confirme a Nova Senha</label>
          <input type="password" id="confirmar_nova_senha" name="confirmar_nova_senha" required />
        </div>
        <button type="submit" class="btn-login-submit">ALTERAR SENHA</button>
        <div class="login-links">
          <a href="../php/perfil.php" class="signup-link">Voltar para o Perfil</a>
        </div>
      </form>
    </div>
  </main>

  <footer class="login-footer">
    <p>&copy; <?php echo date("Y"); ?> DuoDates. Todos os direitos reservados.</p>
  </footer>
</body>
</html>