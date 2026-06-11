<?php
// bemvinda.php
session_start();

// 1. Verifica se o ID e o Nome do usuário foram passados pela URL
if (!isset($_GET['user_id']) || empty($_GET['user_id']) || !isset($_GET['nome']) || empty($_GET['nome'])) {
    header("Location: ../php/login.php");
    exit();
}

// 2. Pega as informações da URL de forma segura
$user_id = htmlspecialchars($_GET['user_id']);
$nome_completo = htmlspecialchars($_GET['nome']);

// 3. Pega apenas o primeiro nome para uma saudação mais amigável
$partes_nome = explode(' ', $nome_completo);
$primeiro_nome = $partes_nome[0];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Seja Bem-Vindo(a) - DuoDates</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../css/bemvinda.css" />
  
</head>
<body>

  <main class="welcome-container">
     
    <div class="welcome-left">
      <span class="logo-text">DuoDates</span>
      <img src="../images/logoduodates.png" alt="Logo DuoDates" class="logo-img">
    </div>
    
    <div class="welcome-right">
      <h1>SEJA BEM-VINDA <?php echo strtoupper(htmlspecialchars($primeiro_nome)); ?>!</h1>
      
      <p class="subtitle">Vamos descobrir a sua <strong>essência</strong> para o date perfeito!</p>
      
      <p class="description">Em 3 passos rápidos, vamos entender seus gostos para criar sugestões que são a sua cara.</p>
      
      <a href="../php/questionario.php?user_id=<?php echo $user_id; ?>" class="btn-comecar">COMEÇAR</a>
    </div>

  </main>

</body>
</html>

