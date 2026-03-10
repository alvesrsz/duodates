<?php
// Arquivo: cadastro-empresa.php
session_start();
include('../conexao.php');

$error_message = '';

// Se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nome_empresa = trim($_POST['nome_empresa']); 
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    $cnpj = trim($_POST['cnpj']);
    $cidade_bairro = trim($_POST['cidade_bairro']);

    // Validações (as mesmas que você já tinha)
    if (empty($nome_empresa) || empty($email) || empty($senha) || empty($confirmar_senha) || empty($cnpj) || empty($cidade_bairro)) {
        $error_message = "Todos os campos são obrigatórios.";
    } elseif ($senha !== $confirmar_senha) {
        $error_message = "As senhas não coincidem.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Formato de e-mail inválido.";
    } elseif (strlen($senha) < 6) {
        $error_message = "A senha deve ter pelo menos 6 caracteres.";
    } else {
        // Verificação de e-mail duplicado (importante)
        if ($conn) {
            $stmt_check = $conn->prepare("SELECT email FROM usuarios WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "Este e-mail já está cadastrado.";
            } else {
                // SUCESSO! Dados validados. Agora, em vez de salvar no banco, salvamos na SESSÃO.
                
                // Criptografa a senha para guardar na sessão
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

                // Guarda todos os dados em um array na sessão
                $_SESSION['dados_cadastro_empresa'] = [
                    'nome_empresa' => $nome_empresa,
                    'email' => $email,
                    'senha_hash' => $senha_hash,
                    'cnpj' => $cnpj,
                    'cidade_bairro' => $cidade_bairro
                ];

                // Redireciona o usuário para a segunda etapa
                header("Location: ../php/adicionar-lugar.php");
                exit(); // Encerra o script para garantir o redirecionamento
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
 <title>Criar Conta Empresarial - DuoDates</title>
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
 <link rel="stylesheet" href="../css/cadastro.css" />
</head>
<body>
 <header class="cadastro-header">
   <div class="logo-container">
     <a href="../index.php" class="logo-text-link">
       <span class="logo-text">DuoDates</span>
     </a>
   </div>
 </header>

 <main class="cadastro-main">
   <div class="cadastro-container">
     <form class="cadastro-form" method="POST" action="../php/cadastro-empresa.php">
       <h1>Crie sua Conta Empresarial</h1>

       <?php if (!empty($error_message)): ?>
         <p class="error-message"><?php echo $error_message; ?></p>
       <?php endif; ?>
       
       <div class="input-group">
         <label for="nome_empresa">Nome da Empresa</label>
         <input type="text" id="nome_empresa" name="nome_empresa" placeholder="Nome fantasia da sua empresa" required value="<?php echo isset($_POST['nome_empresa']) ? htmlspecialchars($_POST['nome_empresa']) : ''; ?>" />
       </div>
       
       <div class="input-group">
         <label for="cnpj">CNPJ</label>
         <input type="text" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required value="<?php echo isset($_POST['cnpj']) ? htmlspecialchars($_POST['cnpj']) : ''; ?>" />
       </div>

       <div class="input-group">
         <label for="email">E-mail Comercial</label>
         <input type="email" id="email" name="email" placeholder="contato@suaempresa.com" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
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
         <label for="cidade_bairro">Endereço (Cidade/Bairro)</label>
         <input type="text" id="cidade_bairro" name="cidade_bairro" placeholder="Cidade e bairro da empresa" required value="<?php echo isset($_POST['cidade_bairro']) ? htmlspecialchars($_POST['cidade_bairro']) : ''; ?>" />
       </div>

       <button type="submit" class="btn-cadastro-submit">Adicionar Lugar</button>

       <div class="login-link-container" style="margin-top: 1rem; border-top: 1px solid var(--input-border-color); padding-top: 1rem;">
           <p>Não é uma empresa? <a href="../php/telacadastro.php">Crie uma conta de usuário</a></p>
       </div>

     </form>
   </div>
 </main>

 <footer class="cadastro-footer">
   <p>&copy; <?php echo date("Y"); ?> DuoDates. Todos os direitos reservados.</p>
 </footer>
</body>
</html>