<?php
// ===================================================================
// ARQUIVO: ativar-conta.php (VERSÃO CORRIGIDA)
// ===================================================================

// Esconder erros em produção, mas pode ser útil mostrar durante o desenvolvimento
ini_set('display_errors', 1); // Mude para 0 em produção
ini_set('display_startup_errors', 1); // Mude para 0 em produção
error_reporting(E_ALL); // Mude para 0 em produção

// ===================================================================
// PASSO A: CONEXÃO COM O BANCO DE DADOS 
// ===================================================================
// Inclui seu arquivo de conexão principal, se tiver um
// Se não, mantenha os dados de conexão aqui
include('../conexao.php'); // <-- Use include('conexao.php') SE você já tem um arquivo centralizado
/*
// Se não usar include('conexao.php'), descomente e preencha abaixo:
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "db_duodate";
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    // Log do erro real para o administrador
    error_log("Falha na conexão com DB: " . mysqli_connect_error());
    // Mensagem genérica para o usuário
    die("Falha na conexão com o sistema. Tente novamente mais tarde.");
}
mysqli_set_charset($conn, "utf8mb4"); // Garante o charset correto
*/

// ===================================================================
// PASSO B: LÓGICA DE ATIVAÇÃO CORRIGIDA
// ===================================================================

$pagina_erro = "../php/login.php"; // Página para onde redirecionar em caso de erro
$pagina_sucesso_ativacao = "../php/login.php"; // Página após ativação BEM SUCEDIDA (Pode ser login para ele entrar)
$mensagem_usuario = ""; // Mensagem a ser exibida na página de erro

if (isset($_GET['token']) && !empty(trim($_GET['token']))) {
    
    $token_recebido = trim($_GET['token']);

    // --- CORREÇÃO 1: Usar 'activation_token' e 'is_email_confirmed' ---
    // Procura o usuário pelo token E verifica se ele ainda NÃO foi confirmado (is_email_confirmed = 0)
    $sql_procurar = "SELECT id, nome FROM usuarios WHERE activation_token = ? AND is_email_confirmed = 0";
    
    $stmt = mysqli_prepare($conn, $sql_procurar);
    
    if ($stmt === false) {
        error_log("Erro ao preparar SELECT: " . mysqli_error($conn));
        $mensagem_usuario = "Ocorreu um erro no sistema (P1). Por favor, tente novamente.";
    } else {
        mysqli_stmt_bind_param($stmt, "s", $token_recebido);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);

        // Verifica se ENCONTROU exatamente UM usuário não ativado com esse token
        if ($resultado && mysqli_num_rows($resultado) == 1) {
            
            $usuario = mysqli_fetch_assoc($resultado);
            $user_id_encontrado = $usuario['id'];
            $nome_encontrado = $usuario['nome']; // Pega o nome para possível uso futuro
            
            mysqli_stmt_close($stmt); // Fecha o statement SELECT

            // --- CORREÇÃO 2: Atualizar 'is_email_confirmed' e limpar 'activation_token' ---
            // Define is_email_confirmed = 1 e limpa o token para não ser reutilizado
            $sql_ativar = "UPDATE usuarios SET is_email_confirmed = 1, activation_token = NULL WHERE id = ?";
            
            $stmt_ativar = mysqli_prepare($conn, $sql_ativar);
            
            if ($stmt_ativar === false) {
                error_log("Erro ao preparar UPDATE: " . mysqli_error($conn));
                $mensagem_usuario = "Ocorreu um erro no sistema (A1). Por favor, tente novamente.";
            } else {
                mysqli_stmt_bind_param($stmt_ativar, "i", $user_id_encontrado);
                
                // Executa a atualização
                if (mysqli_stmt_execute($stmt_ativar)) {
                    mysqli_stmt_close($stmt_ativar); // Fecha o statement UPDATE
                    mysqli_close($conn); // Fecha a conexão com o banco

                    // SUCESSO! Redireciona para a página de login com mensagem de sucesso
                    // Você pode adicionar uma mensagem na URL se quiser: ?ativacao=success
                    header("Location: " . $pagina_sucesso_ativacao . "?ativacao=success");
                    exit; // Encerra o script após o redirecionamento
                } else {
                     error_log("Erro ao executar UPDATE: " . mysqli_stmt_error($stmt_ativar));
                     $mensagem_usuario = "Não foi possível ativar sua conta (A2). Por favor, tente novamente.";
                }
                // Garante que o statement seja fechado mesmo em caso de erro na execução
                 if(isset($stmt_ativar) && $stmt_ativar) mysqli_stmt_close($stmt_ativar);
            }
            
        } else {
            // Não encontrou usuário com esse token OU ele já estava ativo (is_email_confirmed != 0)
             if(isset($stmt) && $stmt) mysqli_stmt_close($stmt); // Garante fechar o SELECT
            $mensagem_usuario = "Este link de ativação é inválido ou já foi utilizado.";
        }
    }
    
} else {
    // O parâmetro 'token' não foi encontrado na URL ou está vazio
    $mensagem_usuario = "Token de ativação não fornecido ou link inválido.";
}

// Se chegou até aqui, houve algum erro. Fecha a conexão se ainda estiver aberta.
if (isset($conn) && $conn) {
    mysqli_close($conn);
}

// ===================================================================
// PASSO C: PÁGINA DE ERRO (HTML - Exibida apenas se houve erro)
// ===================================================================
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativação de Conta - DuoDates</title>
    <style>
        body { font-family: 'Poppins', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #F5F0E8; margin: 0; padding: 20px; box-sizing: border-box; }
        .container { text-align: center; padding: 30px 40px; border-radius: 15px; background-color: #ffffff; box-shadow: 0 5px 15px rgba(0,0,0,0.08); border: 1px solid #EAE5E3; max-width: 500px; width: 100%; }
        h1 { font-family: 'Playfair Display', serif; color: #702632; margin-bottom: 15px; font-size: 1.8rem; }
        p { font-size: 1rem; color: #4B3F3A; line-height: 1.6; margin-bottom: 25px; }
        a.btn-login { display: inline-block; padding: 12px 30px; background-color: #702632; color: #ffffff; text-decoration: none; border-radius: 50px; font-weight: 600; transition: background-color 0.3s ease; }
        a.btn-login:hover { background-color: #501a23; }
        .icon-error { font-size: 2.5rem; color: #dc3545; margin-bottom: 10px; } /* Ícone de erro */
    </style>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css"> </head>
<body>
    <div class="container">
        <div class="icon-error"><i class="fas fa-times-circle"></i></div> 
        <h1>Falha na Ativação</h1>
        
        <p><?php echo htmlspecialchars($mensagem_usuario); ?></p>
        
        <a href="<?php echo htmlspecialchars($pagina_erro); ?>" class="btn-login">Ir para o Login</a>
    </div>
</body>
</html>