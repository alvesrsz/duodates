<?php
// Linhas para exibir erros e nos ajudar a depurar o código.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('../conexao.php');

// Usa as classes do PHPMailer que vamos chamar
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Inclui os arquivos necessários do PHPMailer (método manual)
require '../src/Exception.php';
require '../src/PHPMailer.php';
require '../src/SMTP.php';

$mensagem = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Por favor, insira um formato de e-mail válido.";
    } else {
        if ($conn) {
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $token = bin2hex(random_bytes(32));
                $expira_em = date("Y-m-d H:i:s", time() + 3600); // Token válido por 1 hora

                $update_stmt = $conn->prepare("UPDATE usuarios SET reset_token = ?, reset_token_expires_at = ? WHERE email = ?");
                $update_stmt->bind_param("sss", $token, $expira_em, $email);
                
                if ($update_stmt->execute()) {
                    $mail = new PHPMailer(true);

                    try {
                        // Configurações do Servidor SMTP para InfinityFree com Gmail
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'duodatess@gmail.com';     // <-- 1. COLOQUE SEU E-MAIL AQUI
                        $mail->Password   = 'dgpc gynr ekza orxe';        // <-- 2. COLOQUE SUA SENHA DE APP AQUI
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        // Remetente e Destinatário
                        $mail->setFrom('duodatess@gmail.com', 'DuoDates'); // <-- 3. COLOQUE SEU E-MAIL AQUI
                        $mail->addAddress($email);

                        // Conteúdo do E-mail
                        $mail->isHTML(true);
                        $mail->CharSet = 'UTF-8';
                        $mail->Subject = 'Redefinicao de Senha - DuoDates';
                        
                        // Link que será enviado para o usuário
                        $link_redefinicao = "http://duodate.ct.ws/php/redefinir-senha.php?token=" . $token; // <-- 4. COLOQUE SEU DOMÍNIO AQUI

                        $mail->Body    = "
                            <h1>Olá!</h1>
                            <p>Recebemos uma solicitação para redefinir sua senha na plataforma DuoDates.</p>
                            <p>Para criar uma nova senha, clique no link abaixo:</p>
                            <p><a href='{$link_redefinicao}'>Redefinir Minha Senha</a></p>
                            <p>Este link é válido por 1 hora.</p>
                            <p>Se você não solicitou isso, pode ignorar este e-mail.</p>
                        ";
                        $mail->AltBody = "Para redefinir sua senha, copie e cole o seguinte link no seu navegador: {$link_redefinicao}";

                        $mail->send();
                        $mensagem = "Se um e-mail correspondente for encontrado, um link de redefinição será enviado.";
                        
                    } catch (Exception $e) {
                        // SE O ENVIO FALHAR, ESTA LINHA VAI MOSTRAR O ERRO NA TELA!
                        $mensagem = "Erro ao enviar e-mail. Mailer Error: {$mail->ErrorInfo}";
                    }
                }
                $update_stmt->close();
            } else {
                 // Mensagem genérica mesmo se o e-mail não for encontrado, por segurança
                $mensagem = "Se um e-mail correspondente for encontrado, um link de redefinição será enviado.";
            }
            $conn->close();
        } else {
            $mensagem = "Erro de conexão com o banco de dados.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Esqueci a Senha - DuoDates</title>
    <link rel="stylesheet" href="../css/login.css" />
    <style>
        /* Adicione este estilo para destacar a mensagem de erro */
        .error-message {
            color: #D8000C;
            background-color: #FFD2D2;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 15px;
        }
        .success-message {
            color: #4F8A10;
            background-color: #DFF2BF;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 15px;
        }
        .login-container h1 {
           color:  #800020;
           text-align: center;
        }
    </style>
</head>
<body>
    <main class="login-main">
        <div class="login-container">
            <h1>Recuperar Senha</h1>
            
            <p>Digite seu e-mail para receber o link de redefinição.</p>
            
            <?php if (!empty($mensagem)): ?>
                <?php if (strpos($mensagem, 'Erro') !== false): ?>
                    <p class="error-message"><?php echo $mensagem; ?></p>
                <?php else: ?>
                    <p class="success-message"><?php echo $mensagem; ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <form method="POST" action="../php/esqueci-senha.php">
                <div class="input-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required />
                </div>
                <button type="submit" class="btn-login-submit">ENVIAR LINK</button>
                 <div class="login-links">
                    <a href="../php/login.php" class="signup-link">Voltar para o Login</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>