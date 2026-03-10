<?php
// login.php
session_start();
include('../conexao.php');

$error_message = '';
$success_message_html = ''; // Nova variável para a mensagem de sucesso em HTML

// Bloco de mensagem para quando o usuário finaliza o questionário
if (isset($_GET['status']) && $_GET['status'] === 'questionario_finalizado') {
    $success_message_html = "
        <div class='success-box' style='text-align: center; border-left-color: #28a745;'>
            <i class='fas fa-check-circle' style='font-size: 24px; color: #28a745; margin-bottom: 10px;'></i>
            <h4>Cadastro Finalizado com Sucesso!</h4>
            <p>Enviamos um link de ativação para o seu e-mail. Por favor, clique nele para confirmar sua conta antes de fazer o login.</p>
        </div>
    ";
}

// Bloco de mensagem para quando uma empresa se cadastra
if (isset($_GET['status']) && $_GET['status'] === 'cadastro_concluido') {
    $success_message_html = "
        <div class='success-box'>
            <i class='fas fa-hourglass-start'></i>
            <h4>Solicitação Enviada com Sucesso!</h4>
            <p>Sua conta empresarial foi criada e agora aguarda a aprovação da nossa equipe. Você receberá uma notificação por e-mail assim que o processo for concluído.</p>
        </div>
    ";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        $error_message = "Por favor, preencha todos os campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Formato de e-mail inválido.";
    } else {
        if ($conn) {
            $stmt = $conn->prepare("SELECT id, nome, email, senha, tipo_conta, is_email_confirmed, status_aprovacao FROM usuarios WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $usuario_db = $result->fetch_assoc();
                    if (password_verify($senha, $usuario_db['senha'])) {
                        
                        // Verifica se a conta é empresarial e se está pendente
                        if ($usuario_db['tipo_conta'] === 'empresarial' && $usuario_db['status_aprovacao'] === 'pendente') {
                             $error_message = "Sua conta está em análise. Você será notificado por e-mail quando for aprovada.";
                        } elseif ($usuario_db['is_email_confirmed'] == 1) {
                            $_SESSION['user_id'] = $usuario_db['id'];
                            $_SESSION['usuario'] = $usuario_db['nome'];
                            $_SESSION['email'] = $usuario_db['email'];
                            $_SESSION['tipo_conta'] = $usuario_db['tipo_conta'];

                            if ($_SESSION['tipo_conta'] === 'empresarial') {
                                header("Location: ../php/dashboard_empresa.php");
                            } else {
                                header("Location: ../index.php");
                            }
                            exit();
                        } else {
                            $error_message = "Sua conta ainda não foi ativada. Por favor, verifique seu e-mail.";
                        }
                    } else {
                        $error_message = "E-mail ou senha incorretos.";
                    }
                } else {
                    $error_message = "E-mail ou senha incorretos.";
                }
                $stmt->close();
            } else {
                $error_message = "Erro no sistema. Tente novamente mais tarde.";
            }
            $conn->close();
        } else {
            $error_message = "Erro de conexão. Tente novamente mais tarde.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - DuoDates</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../css/login.css" />
</head>
<body>
  <header class="login-header">
    <div class="logo-container">
     
             <a href="../index.php" class="logo-link">
            <span class="logo">Duo Dates</span>
            <img src="../images/logoduodates.png" alt="Coração" class="logo-image">
          </a>
    </div>
  </header>

  <main class="login-main">
    <div class="login-container">
      <form class="login-form" method="POST" action="../php/login.php">
        <h1>Acesse sua Conta</h1>
        <?php
        if (!empty($error_message)) {
            echo "<p class='error-message'>{$error_message}</p>";
        }
        // MOSTRA O BLOCO DE SUCESSO
        if (!empty($success_message_html)) {
            echo $success_message_html;
        }
        ?>
        <div class="input-group">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
        </div>
        <div class="input-group">
          <label for="senha">Senha</label>
          <input type="password" id="senha" name="senha" placeholder="Sua senha" required />
        </div>
        <button type="submit" class="btn-login-submit">ENTRAR</button>
        <div class="login-links">
          <a href="../php/esqueci-senha.php" class="forgot-password">Esqueceu a senha?</a>
          
          <?php if (empty($success_message_html)): ?>
            <p class="signup-link">Não tem uma conta? <a href="../php/telacadastro.php">Criar conta</a></p>
            <p class="signup-link" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--input-border-color);">É uma empresa? <a href="../php/cadastro-empresa.php">Cadastre-se</a></p>
          <?php endif; ?>

        </div>
      </form>
    </div>
  </main>

  <footer class="login-footer">
    <p>&copy; <?php echo date("Y"); ?> DuoDates. Todos os direitos reservados.</p>
  </footer>
</body>
</html>