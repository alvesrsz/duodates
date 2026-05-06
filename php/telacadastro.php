<?php
// ../php/telacadastro.php
session_start();
include('../conexao.php');

// Inclui e usa as classes do PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../src/Exception.php';
require '../src/PHPMailer.php';
require '../src/SMTP.php';

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $data_nascimento = $_POST['data_nascimento'];
    $cidade_bairro = trim($_POST['cidade_bairro']);

    // Validações
    if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha) || empty($data_nascimento) || empty($cidade_bairro)) {
        $error_message = "Todos os campos são obrigatórios.";
    } elseif ($senha !== $confirmar_senha) {
        $error_message = "As senhas não coincidem.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Formato de e-mail inválido.";
    } elseif (strlen($senha) < 6) {
        $error_message = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        if ($conn) {
            $stmt_check = $conn->prepare("SELECT email FROM usuarios WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "Este e-mail já está cadastrado.";
            } else {
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                $token_ativacao = bin2hex(random_bytes(32)); // Gera um token de ativação seguro

                // Insere o usuário com status 'não confirmado' (is_email_confirmed = 0 por padrão do BD)
                $stmt_insert = $conn->prepare("INSERT INTO usuarios (nome, email, senha, data_nascimento, cidade_bairro, activation_token) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("ssssss", $nome, $email, $senha_hash, $data_nascimento, $cidade_bairro, $token_ativacao);

                if ($stmt_insert->execute()) {
                    // --- Início do Envio de E-mail de Ativação ---
                    $mail = new PHPMailer(true);
                    try {
                        // Configurações do Servidor SMTP
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'duodatess@gmail.com';     // <-- 1. COLOQUE SEU E-MAIL AQUI
                        $mail->Password   = 'dgpc gynr ekza orxe';        // <-- 2. COLOQUE SUA SENHA DE APP AQUI
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        $mail->setFrom('duodatess@gmail.com', 'DuoDates'); // <-- COLOQUE SEU E-MAIL AQUI
                        $mail->addAddress($email, $nome);

                        $mail->isHTML(true);
                        $mail->CharSet = 'UTF-8';
                        $mail->Subject = '✨ Ative sua Conta no DuoDates!';
                        
                        $link_ativacao = "http://duodate.ct.ws/php/ativar-conta.php?token=" . $token_ativacao; // <-- MUDE SEU DOMÍNIO AQUI

                        // ===== INÍCIO DA ALTERAÇÃO DO TEMPLATE DE E-MAIL =====
// ===== COLE ESTE BLOCO PARA SUBSTITUIR O ANTIGO $mail->Body =====
// ========================================================
// ========================================================
                      // ========================================================
                        // INÍCIO DO NOVO TEMPLATE DE E-MAIL (Bege + Logo + Fontes)
                        // ========================================================
                      // ========================================================
                        // INÍCIO DO NOVO TEMPLATE DE E-MAIL (Título Centralizado)
                        // ========================================================
                        $mail->Body = "
                        <!DOCTYPE html>
                        <html lang='pt-BR'>
                        <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <title>Ative sua Conta no DuoDates</title>
                        <style>
                            /* Estilos para fontes */
                            @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:wght@700&display=swap');
                        </style>
                        </head>
                        <body style='margin: 0; padding: 0; font-family: \"Montserrat\", Arial, sans-serif;'>

                            <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                <tr>
                                    <td style='padding: 20px 0; background-color: #FAF5EF;'>
                                        
                                        <table align='center' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0;'>
                                            
                                            <tr>
                                                <td style='padding: 30px 40px; background-color: #7c2d3b;'>
                                                    
                                                    <table border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        <tr>
                                                            <td align='left' style='padding-right: 15px; vertical-align: middle; width: 40px;'>
                                                                <img src='http://duodate.ct.ws/logoduodates.png' alt='Logo' width='40' style='display: block; border: 0;'>
                                                            </td>
                                                            
                                                            <td align='center' style='vertical-align: middle;'>
                                                                <h1 style='font-family: \"Playfair Display\", serif; font-size: 36px; color: #ffffff; margin: 0; font-weight: 700;'>
                                                                    DuoDates
                                                                </h1>
                                                            </td>
                                                        </tr>
                                                    </table>

                                                </td>
                                            </tr>

                                            <tr>
                                                <td style='padding: 40px 40px 30px 40px; text-align: center;'>
                                                    
                                                    <h2 style='font-family: \"Playfair Display\", serif; font-size: 26px; color: #333333; margin: 0 0 20px 0; font-weight: 700;'>
                                                        Falta só um passo!
                                                    </h2>

                                                    <p style='font-family: \"Montserrat\", Arial, sans-serif; font-size: 18px; color: #333333; margin: 0 0 25px 0;'>
                                                        Olá, {$nome}!
                                                    </p>
                                                    
                                                    <p style='font-family: \"Montserrat\", Arial, sans-serif; font-size: 16px; color: #555555; line-height: 1.6; margin: 0 0 35px 0;'>
                                                        Seja bem-vindo(a)! Para começar a explorar momentos inesquecíveis, por favor, confirme seu e-mail clicando no botão abaixo:
                                                    </p>

                                                    <table border='0' cellspacing='0' cellpadding='0' align='center' style='margin: 0 auto;'>
                                                        <tr>
                                                            <td align='center' style='border-radius: 50px; background-color: #7c2d3b;'>
                                                                <a href='{$link_ativacao}' target='_blank' style='font-family: \"Montserrat\", Arial, sans-serif; font-size: 16px; font-weight: bold; color: #ffffff; text-decoration: none; padding: 16px 40px; border-radius: 50px; display: inline-block;'>
                                                                    ATIVAR MINHA CONTA
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>

                                            <tr>
                                                <td style='padding: 0 40px 40px 40px; text-align: center;'>
                                                    <p style='font-family: \"Montserrat\", Arial, sans-serif; font-size: 12px; color: #888888; margin: 30px 0 0 0;'>
                                                        Se o botão não funcionar, copie e cole este link no seu navegador:
                                                        <br>
                                                        <a href='{$link_ativacao}' target='_blank' style='color: #7c2d3b; text-decoration: underline; word-break: break-all;'>
                                                            {$link_ativacao}
                                                        </a>
                                                    </p>
                                                </td>
                                            </tr>

                                        </table>
                                        <table align='center' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; margin: 0 auto;'>
                                            <tr>
                                                <td style='padding: 30px 40px; text-align: center;'>
                                                    <p style='font-family: \"Montserrat\", Arial, sans-serif; font-size: 14px; color: #888888; font-style: italic; margin: 0;'>
                                                        “💖 DuoDates – Conectando pessoas a momentos inesquecíveis.”
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                    </td>
                                </tr>
                            </table>

                        </body>
                        </html>
                        ";
                        // ========================================================
                        // FIM DO NOVO TEMPLATE DE E-MAIL
                        // ========================================================

                         
                        // ===== FIM DA ALTERAÇÃO DO TEMPLATE DE E-MAIL =====

                        $mail->send();
                    } catch (Exception $e) {
                        // Opcional: Logar o erro se o e-mail não puder ser enviado
                        // error_log("Mailer Error: " . $mail->ErrorInfo);
                    }
                    // --- Fim do Envio de E-mail ---
                    
// Pega o ID do usuário que acabou de ser inserido no banco
$novo_user_id = $stmt_insert->insert_id;

// Redireciona o usuário para a nova página de boas-vindas
// Passamos o ID e o Nome (o nome é pego da variável $nome lá do início do script)
header("Location: ../php/bemvinda.php?user_id=" . $novo_user_id . "&nome=" . urlencode($nome));
exit(); // É importante usar exit() após um redirecionamento para parar a execução do script

                } else {
                    $error_message = "Erro ao cadastrar. Tente novamente.";
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
            $conn->close();
        } else {
            $error_message = "Não foi possível conectar ao banco de dados.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Criar Conta - DuoDates</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/cadastro.css" />
</head>
<body>
  <header class="cadastro-header">
    <div class="logo-container">
       <a href="../index.php" class="logo-text-link">
            <span class="logo">Duo Dates</span>
            <img src="../images/logoduodates.png" alt="Coração" class="logo-image">
     </a>
    </div>
  </header>

  <main class="cadastro-main">
    <div class="cadastro-container">
      <form class="cadastro-form" method="POST" action="../php/telacadastro.php">
        <h1>Crie sua Conta</h1>

        <?php if (!empty($error_message)): ?>
          <p class="error-message"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
          <p class="success-message"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <?php if (empty($success_message)): // Só mostra o formulário se não houve sucesso ?>
        <div class="input-group">
          <label for="nome">Nome completo</label>
          <input type="text" id="nome" name="nome" placeholder="Seu nome completo" required value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" />
        </div>

        <div class="input-group">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
        </div>

        <div class="input-group">
          <label for="senha">Senha</label>
          <input type="password" id="senha" name="senha" placeholder="Crie uma senha (mín. 6 caracteres)" required />
        </div>

        <div class="input-group">
          <label for="confirmar_senha">Confirme sua Senha</label>
          <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a senha" required />
        </div>

        <div class="input-group">
          <label for="data_nascimento">Data de Nascimento</label>
          <input type="date" id="data_nascimento" name="data_nascimento" required value="<?php echo isset($_POST['data_nascimento']) ? htmlspecialchars($_POST['data_nascimento']) : ''; ?>" />
        </div>

        <div class="input-group">
          <label for="cidade_bairro">Cidade/Bairro</label>
          <input type="text" id="cidade_bairro" name="cidade_bairro" placeholder="Sua cidade e bairro" required value="<?php echo isset($_POST['cidade_bairro']) ? htmlspecialchars($_POST['cidade_bairro']) : ''; ?>" />
        </div>

        <button type="submit" class="btn-cadastro-submit">CRIAR CONTA</button>

        <div class="login-link-container">
          <p>Já tem uma conta? <a href="../php/login.php">Faça login</a></p>
          <p class="login-link-containe" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--input-border-color);">É uma empresa? <a href="../php/cadastro-empresa.php">Cadastre-se aqui</a></p>
        </div>
        
        
        <?php endif; ?>
      </form>
    </div>
  </main>

  <footer class="cadastro-footer">
    <p>&copy; <?php echo date("Y"); ?> DuoDates. Todos os direitos reservados.</p>
  </footer>
</body>
</html>