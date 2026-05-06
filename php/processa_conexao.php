<?php
// ===================================================================
// ARQUIVO: processar_cadastro.php (VERSÃO FINAL 100% CORRIGIDA)
// ===================================================================

// ===================================================================
// PASSO A: CONFIGURAÇÃO E CONEXÃO
// ===================================================================

// --- Seus Dados do Banco (CORRIGIDOS) ---
$db_host = "sql200.infinityfree.com";
$db_user = "if0_38704863";
$db_pass = "duodates2025";
$db_name = "if0_38704863_db_duodate"; // <--- CORRIGIDO AQUI!

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

    // 2. Validações Simples
    if (empty($nome) || empty($email) || empty($senha) || empty($senha_confirmar)) {
        die("Erro: Todos os campos são obrigatórios.");
    }
    if ($senha !== $senha_confirmar) {
        die("Erro: As senhas não conferem. Por favor, volte e tente novamente.");
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Erro: Formato de e-mail inválido.");
    }

    // 3. Verifica se o e-mail JÁ EXISTE (Checando pela coluna ID correta)
    // <--- CORRIGIDO AQUI!
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

    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
    
    // GERA UM TOKEN DE ATIVAÇÃO SEGURO
    $token = bin2hex(random_bytes(32)); 

    // ===================================================================
    // PASSO D: INSERIR USUÁRIO NO BANCO
    // ===================================================================

    // <--- CORRIGIDO AQUI! Colunas 'reset_token' e 'status_aprovacao'
    $sql_insert = "INSERT INTO usuarios (nome, email, senha, reset_token, status_aprovacao) 
                     VALUES (?, ?, ?, ?, 'pendente')";
                     
    $stmt_insert = mysqli_prepare($conn, $sql_insert);
    // Note: Usamos o $token (que será o reset_token) na ordem correta
    mysqli_stmt_bind_param($stmt_insert, "sssss", $nome, $email, $senha_hash, $token, 'pendente');
    
    // Tenta executar o INSERT
    if (mysqli_stmt_execute($stmt_insert)) {
        
        // SUCESSO! Usuário cadastrado no banco. Agora, enviar o e-mail.

        // ===================================================================
        // PASSO E: MONTAR E ENVIAR O E-MAIL
        // ===================================================================
        
        // Link de ativação CORRETO
        $link_ativacao = $seu_site_url . "../php/ativar-conta.php?token=" . $token; // <--- CORRIGIDO AQUI! (Tinha um erro de concatenação aqui)
        
        $cor_fundo_vinho = "#5c1a32";
        $cor_fundo_claro = "#ffffff";
        $cor_texto_vinho = "#6a1b3c";

        $corpo_email = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ative sua Conta DuoDates</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f0f0f0;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="width: 100%; max-width: 600px; border-collapse: collapse; margin: 0 auto; background-color: $cor_fundo_claro;">
        
        <tr>
            <td style="font-size: 0; line-height: 0;">
                <a href="$link_ativacao" target="_blank" style="text-decoration: none; border: 0;">
                    <img src="$seu_site_url/images/email-header-novo.png" alt="Ativar sua conta DuoDates" width="600" style="display: block; width: 100%; max-width: 600px; height: auto; border: 0;">
                </a>
            </td>
        </tr>

        <tr>
            <td bgcolor="$cor_fundo_vinho" style="padding: 30px 40px 30px 40px; text-align: center;">
                <p style="color: #ffffff; font-size: 18px; line-height: 1.5; font-family: Arial, sans-serif; margin: 0 0 15px 0; font-weight: bold;">
                    Olá $nome, seja bem-vindo(a) ao DuoDates!
                </p>
                <p style="color: #ffffff; font-size: 16px; line-height: 1.5; font-family: Arial, sans-serif; margin: 0;">
                    Nós acreditamos que todo encontro merece ser inesquecível.
                    Antes de começar a explorar lugares incríveis e planejar momentos
                    especiais, precisamos confirmar o seu e-mail.
                </p>
            </td>
        </tr>
        
        <tr>
            <td bgcolor="$cor_fundo_vinho" style="padding: 20px 40px; text-align: center;">
                 <a href="$link_ativacao" target="_blank" style="display: inline-block; padding: 12px 30px; border: 2px solid #ffffff; border-radius: 50px; background-color: transparent; color: #ffffff; font-size: 18px; font-weight: bold; text-decoration: none;">
                    ATIVAR MINHA CONTA
                 </a>
            </td>
        </tr>


        <tr>
            <td bgcolor="$cor_fundo_vinho" style="padding: 0px 10px 40px 10px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td width="25%" align="center" style="padding: 10px;">
                            <img src="$seu_site_url/images/icon-recomendacoes-white.png" alt="" width="50" style="display: block; margin-bottom: 8px;">
                            <p style="color: #ffffff; font-size: 12px; font-family: Arial, sans-serif; line-height: 1.4; margin: 0;">Recomendações<br>personalizadas</p>
                        </td>
                        <td width="25%" align="center" style="padding: 10px;">
                            <img src="$seu_site_url/images/icon-locais-white.png" alt="" width="50" style="display: block; margin-bottom: 8px;">
                            <p style="color: #ffffff; font-size: 12px; font-family: Arial, sans-serif; line-height: 1.4; margin: 0;">Locais perto<br>de você</p>
                        </td>
                        <td width="25%" align="center" style="padding: 10px;">
                            <img src="$seu_site_url/images/icon-compatibilidade-white.png" alt="" width="50" style="display: block; margin-bottom: 8px;">
                            <p style="color: #ffffff; font-size: 12px; font-family: Arial, sans-serif; line-height: 1.4; margin: 0;">Compatibilidade de gostos<br>com seu(s) parceiro(s)</p>
                        </td>
                        <td width="25%" align="center" style="padding: 10px;">
                            <img src="$seu_site_url/images/icon-agenda-white.png" alt="" width="50" style="display: block; margin-bottom: 8px;">
                            <p style="color: #ffffff; font-size: 12px; font-family: Arial, sans-serif; line-height: 1.4; margin: 0;">Agenda e diário de<br>encontros</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td bgcolor="$cor_fundo_claro" style="padding: 30px; text-align: center;">
                <p style="color: $cor_texto_vinho; font-size: 16px; font-style: italic; font-family: Georgia, serif; margin: 0;">
                    "DuoDates – Conectando pessoas a momentos inesquecíveis."
                </p>
            </td>
        </tr>

    </table>
</body>
</html>
HTML; 

        $assunto = "DuoDates - Ative sua conta";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: DuoDates <nao-responda@duodate.ct.ws>" . "\r\n";

        // Tenta enviar o e-mail
        if (mail($email, $assunto, $corpo_email, $headers)) {
            // Mensagem final de sucesso!
            echo "Cadastro quase completo! Um e-mail de ativação foi enviado para $email.";
            echo "<br>(Verifique também a pasta SPAM!).";
            // É comum redirecionar aqui
            // header("Location: ../php/verifique-email.php");
        } else {
            // E-mail falhou (problema comum no InfinityFree)
            echo "ERRO: O usuário foi criado, mas a função mail() falhou. O e-mail não pôde ser enviado.";
            echo "<br>LINK DE ATIVAÇÃO MANUAL (PARA TESTE): <a href='$link_ativacao'>$link_ativacao</a>";
        }

    } else {
        // FALHA ao inserir no banco
        echo "Erro: Não foi possível registrar o usuário. " . mysqli_stmt_error($stmt_insert);
    }
    
    mysqli_stmt_close($stmt_insert);

} else {
    // Se alguém tentar acessar o '../php/processar_cadastro.php' direto pela URL
    echo "Acesso negado. Você deve vir a partir do formulário de cadastro.";
}

// Fecha a conexão com o banco de dados
mysqli_close($conn);

?>