<?php
// ===================================================================
// ARQUIVO: processar_cadastro.php (VERSÃO FINAL COM DESIGN DE ALTO CONTRASTE)
// ===================================================================

// ===================================================================
// PASSO A: CONFIGURAÇÃO E CONEXÃO
// ===================================================================

// --- Seus Dados do Banco (Verificados e Corretos) ---
$db_host = "sql200.infinityfree.com";
$db_user = "if0_38704863";
$db_pass = "duodates2025";
$db_name = "if0_38704863_db_duodate";

// --- URL do seu Site (CORRETA com HTTPS) ---
$seu_site_url = "https://duodate.ct.ws";

// --- Conecta ao Banco de Dados ---
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Falha na conexão com o banco de dados: " . mysqli_connect_error());
}

// Define o charset para UTF-8
mysqli_set_charset($conn, "utf8");

// ===================================================================
// PASSO B: RECEBER E VALIDAR DADOS DO FORMULÁRIO
// ===================================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Coleta os dados do formulário
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    $senha_confirmar = $_POST['senha_confirmar'];

    // 2. Validações
    if (empty($nome) || empty($email) || empty($senha) || empty($senha_confirmar)) {
        die("Erro: Todos os campos são obrigatórios.");
    }
    if ($senha !== $senha_confirmar) {
        die("Erro: As senhas não conferem. Por favor, volte e tente novamente.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Erro: Formato de e-mail inválido.");
    }

    // 3. Verifica se o e-mail JÁ EXISTE (Coluna ID CORRIGIDA: 'id')
    $sql_check_email = "SELECT id FROM usuarios WHERE email = ?"; 
    $stmt = mysqli_prepare($conn, $sql_check_email);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
        mysqli_stmt_close($stmt);
        die("Erro: Este e-mail já está em uso. Tente fazer login.");
    }
    mysqli_stmt_close($stmt);

    // ===================================================================
    // PASSO C: PREPARAR DADOS PARA O BANCO
    // ===================================================================

    // Criptografa a senha com segurança
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    
    // Gera um TOKEN de ativação seguro
    $token = bin2hex(random_bytes(32)); 

    // ===================================================================
    // PASSO D: INSERIR USUÁRIO NO BANCO (Colunas CORRIGIDAS)
    // ===================================================================

    $sql_insert = "INSERT INTO usuarios (nome, email, senha, reset_token, status_aprovacao) 
                     VALUES (?, ?, ?, ?, 'pendente')";
                     
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    mysqli_stmt_bind_param($stmt_insert, "sssss", $nome, $email, $senha_hash, $token, 'pendente');
    
    if (mysqli_stmt_execute($stmt_insert)) {
        
        // SUCESSO! Agora, montar e enviar o e-mail.

        // ===================================================================
        // PASSO E: MONTAR E ENVIAR O E-MAIL (DESIGN HIGH-CONTRAST)
        // ===================================================================
        
        $link_ativacao = $seu_site_url . "../php/ativar-conta.php?token=" . $token; 
        
        // Cores
        $cor_vinho_escura = "#5c1a32"; // COR DE DESTAQUE
        $cor_vinho_clara = "#6a1b3c";  // Títulos
        $cor_fundo_claro = "#ffffff";
        $cor_texto_claro = "#ffffff";
        
        // NOVO Assunto para forçar a quebra do cache
        $assunto = "✅ DuoDates: Confirmação Imediata [Vinho Forte]"; 

        $corpo_email = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ative sua Conta DuoDates</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0f0f0; font-family: Arial, sans-serif; line-height: 1.6;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; border-collapse: collapse; margin: 0 auto; background-color: #f0f0f0;">
        
        <tr><td height="30"></td></tr>

        <tr>
            <td align="center" style="padding: 0 20px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: collapse; background-color: $cor_fundo_claro; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    
                    <tr>
                        <td align="center" bgcolor="$cor_vinho_escura" style="padding: 40px 40px; border-radius: 8px 8px 0 0;">
                            <h1 style="color: $cor_texto_claro; font-size: 30px; margin: 0; font-weight: bold; letter-spacing: 2px;">DUODATES</h1>
                            <p style="color: #cccccc; font-size: 14px; margin: 5px 0 0 0;">Confirmação de Cadastro</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 40px; text-align: center;">
                            
                            <h2 style="color: $cor_vinho_clara; font-size: 26px; margin: 0 0 20px 0; font-weight: 600;">
                                Olá $nome!
                            </h2>
                            
                            <p style="color: #333333; font-size: 16px; margin: 0 0 30px 0;">
                                O seu processo de criação de conta está quase no fim. Clique no botão abaixo para concluir a ativação e começar a planejar seus encontros:
                            </p>

                            <table border="0" cellspacing="0" cellpadding="0" align="center">
                                <tr>
                                    <td align="center" style="border-radius: 50px;" bgcolor="$cor_vinho_escura">
                                        <a href="$link_ativacao" target="_blank" style="font-size: 18px; font-family: Arial, sans-serif; color: $cor_texto_claro; text-decoration: none; padding: 15px 30px; border-radius: 50px; display: inline-block; font-weight: bold; border: 1px solid $cor_vinho_escura;">
                                            ATIVAR MINHA CONTA AGORA
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="color: #999999; font-size: 12px; margin: 30px 0 0 0;">
                                Se o botão não funcionar, use o link abaixo.
                            </p>
                            <p style="color: #999999; font-size: 12px; margin: 0; word-break: break-all; padding: 0 10px;">
                                <a href="$link_ativacao" style="color: $cor_vinho_clara; text-decoration: underline;">$link_ativacao</a>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td align="center" bgcolor="#f8f8f8" style="padding: 15px; border-radius: 0 0 8px 8px;">
                             <p style="color: #888888; font-size: 12px; margin: 0;">
                                 DuoDates | Conectando pessoas a momentos inesquecíveis.
                             </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>

        <tr><td height="30"></td></tr>

    </table>
</body>
</html>
HTML; 

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: DuoDates <info@duodate.ct.ws>" . "\r\n"; 

        // Tenta enviar o e-mail
        if (mail($email, $assunto, $corpo_email, $headers)) {
            echo "Cadastro quase completo! Um e-mail de ativação foi enviado para $email.";
            echo "<br>(Verifique também a pasta SPAM!).";
        } else {
            echo "ERRO: O usuário foi criado, mas a função mail() falhou. O e-mail não pôde ser enviado.";
            echo "<br>LINK DE ATIVAÇÃO MANUAL (PARA TESTE): <a href='$link_ativacao'>$link_ativacao</a>";
        }

    } else {
        // FALHA ao inserir no banco
        echo "Erro: Não foi possível registrar o usuário. " . mysqli_stmt_error($stmt_insert);
    }
    
    mysqli_stmt_close($stmt_insert);

} else {
    // Se alguém tentar acessar o 'processar_cadastro.php' direto pela URL
    echo "Acesso negado. Você deve vir a partir do formulário de cadastro.";
}

// Fecha a conexão com o banco de dados
mysqli_close($conn);

?>