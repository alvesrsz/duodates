<?php
session_start();

// --- Bloco de Conexão e Busca de Dados ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexao.php'; 

$categorias = []; 

if (isset($conn) && $conn) {
    $sql_categorias = "SELECT nome, imagem_url, link_pagina, icone_fa FROM categorias ORDER BY nome ASC";
    $result_categorias = $conn->query($sql_categorias);

    if ($result_categorias) {
        while ($row = $result_categorias->fetch_assoc()) {
            $categorias[] = $row;
        }
        // REMOVIDO o array_chunk. Queremos todas as categorias em uma única fila agora.
    } else {
        die("ERRO DE BANCO DE DADOS: " . htmlspecialchars($conn->error));
    }
} else {
    die("ERRO DE CONEXÃO: Verifique o arquivo conexao.php.");
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Duo Dates | Seu Próximo Date</title>
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
</head>
<body>

  <div class="scroll-container">

    <section class="fullscreen-section hero-redesign-section">
      <header class="new-header">
        <div class="header-left">
          <a href="index.php" class="logo-link">
            <span class="logo">Duo Dates</span>
          </a>
        </div>
        
        <div class="header-center-pin">
          <img src="images/pin-coracao.png" alt="Pin Duo Dates">
        </div>

        <div class="header-right">
          <?php if (isset($_SESSION['tipo_conta'])): ?>
            <a href="php/login-feito.php" class="header-nav-link">Perfil</a>
            <?php if ($_SESSION['tipo_conta'] === 'empresarial' || $_SESSION['tipo_conta'] === 'admin'): ?>
              <a href="php/dashboard_empresa.php" class="header-nav-link">Empresa</a>
              <a href="php/admin.php" class="header-nav-link">Admin</a>
            <?php endif; ?>
            <a href="php/logout.php" class="btn-login-white">LOGOUT</a>
          <?php else: ?>
            <a href="php/login.php" class="btn-login-white">LOGIN</a>
          <?php endif; ?>
        </div>
      </header>

      <main class="new-hero-main">
        <div class="hero-content-wrapper">
          <div class="hero-left-content">
            <h1 class="hero-title">SEU PRÓXIMO DATE<br>A UM CLIQUE DE<br>DISTÂNCIA</h1>
            
            <?php if (isset($_SESSION['tipo_conta']) || isset($_SESSION['usuario'])): ?>
                <a href="#explorar-section" class="btn-comecar-agora">EXPLORAR</a>
            <?php else: ?>
                <a href="php/telacadastro.php" class="btn-comecar-agora">COMEÇAR AGORA</a>
            <?php endif; ?>

            <div class="hero-features">
              <div class="feature-item">
                <div class="diamond-icon"><img src="images/planner.png" alt="Planner"></div>
                <p>Planner<br>Integrado</p>
              </div>
              <div class="feature-item">
                <div class="diamond-icon"><img src="images/lugares.png" alt="Lugares"></div>
                <p>Lugares<br>Ideais</p>
              </div>
              <div class="feature-item">
                <div class="diamond-icon"><img src="images/match.png" alt="Match"></div>
                <p>Match de<br>Interesses</p>
              </div>
              <div class="feature-item">
                <div class="diamond-icon"><img src="images/sintonia.png" alt="Sintonia"></div>
                <p>Sintonia<br>Perfeita</p>
              </div>
            </div>
          </div>

          <div class="hero-right-illustration">
            <img src="images/escritas.png" class="bg-writing" alt="Decoração de escritas">
            <div class="bg-circle"></div>
            <img src="images/mesa-vinho.png" alt="Ilustração de um encontro" class="illustration-img">
          </div>
        </div>
      </main>
    </section>

    <section class="stats-banner">
      <div class="stats-text-area">
        <h2>Por que o Duo Dates?</h2>
        <p>Encontrar o date perfeito vai muito além de um simples match. O Duo Dates une tecnologia e romantismo para criar experiências memoráveis.</p>
      </div>
      <div class="stats-numbers-area">
        <div class="stat-number">12k+</div>
        <div class="stat-number">98%</div>
        <div class="stat-number">500+</div>
      </div>
    </section>

    <section id="explorar-section" class="fullscreen-section date-style-section">
      <div class="style-section-container">
        <h2 class="date-style-title">Qual seu estilo de date?</h2>
        
        <div class="carousel-wrapper">
          <button class="nav-arrow left-arrow"><i class="fas fa-chevron-left"></i></button>
          
         <div class="style-grid-wrapper">
            <div class="style-grid">
              <?php foreach ($categorias as $cat): ?>
                <?php if (isset($_SESSION['tipo_conta']) || isset($_SESSION['usuario'])): ?>
                  <a href="php/<?php echo htmlspecialchars($cat['link_pagina']); ?>" class="style-card">
                <?php else: ?>
                  <a href="#" onclick="exigirLogin(event)" class="style-card">
                <?php endif; ?>
                  <div class="card-image" style="background-image: url('<?php echo htmlspecialchars($cat['imagem_url']); ?>');" loading="lazy">
                    <div class="card-overlay">
                      <i class="<?php echo htmlspecialchars($cat['icone_fa']); ?>"></i>
                      <span><?php echo htmlspecialchars($cat['nome']); ?></span>
                    </div>
                  </div>
                </a> <?php endforeach; ?>
            </div>
          </div>

          <button class="nav-arrow right-arrow"><i class="fas fa-chevron-right"></i></button>
        </div>

      </div>
    </section>

    <section class="fullscreen-section faq-section">
      <div class="faq-container">
        <h2 class="faq-title">Dúvidas Frequentes</h2>
        <div class="faq-list">
          
          <div class="faq-item">
            <button class="faq-question">O aplicativo é exclusivo para casais românticos? <i class="fas fa-chevron-down"></i></button>
            <div class="faq-answer">
              <p>De forma alguma! O DuoDates foi desenhado para qualquer dupla. Ele funciona perfeitamente para dois amigos que nunca sabem onde almoçar, pais e filhos procurando um passeio no fim de semana, ou colegas de trabalho decidindo o local do happy hour.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">E se as nossas respostas forem totalmente diferentes? O sistema trava ou fica sem opções? <i class="fas fa-chevron-down"></i></button>
            <div class="faq-answer">
              <p>Não, o algoritmo possui uma lógica de ponderação de conflitos. Se um usuário escolher "Japonesa" e o outro escolher "Italiana", o sistema busca uma terceira via baseada no histórico de saídas anteriores da dupla ou sugere locais que ofereçam cardápios variados (como praças de alimentação gourmet ou bistrôs contemporâneos), garantindo que ninguém saia prejudicado.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">O DuoDates é um aplicativo para conhecer pessoas novas? <i class="fas fa-chevron-down"></i></button>
            <div class="faq-answer">
              <p>Não. O DuoDates foi feito para quem já tem um parceiro(a) ou amigo(a) e quer facilitar a decisão de onde ir. O objetivo é acabar com a indecisão na hora de escolher um restaurante, bar ou evento.</p>
            </div>
          </div>

          <div class="faq-item">
            <button class="faq-question">O que diferencia o DuoDates dos outros aplicativos de relacionamento? <i class="fas fa-chevron-down"></i></button>
            <div class="faq-answer">
              <p>A maioria dos aplicativos foca apenas na aparência das pessoas, gerando conversas que muitas vezes não saem do virtual. O DuoDates inverte essa lógica: o foco inicial é o interesse compartilhado ou a atividade (ir a um restaurante específico, show, parque).</p>
            </div>
          </div>

        </div>
      </div>
      
      <footer class="main-footer">
        <p>&copy; 2024 Duo Dates. Todos os direitos reservados.</p>
      </footer>
    </section>

  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      
      // ==========================================
      // LÓGICA DO CARROSSEL INFINITO (DESKTOP)
      // ==========================================
      const track = document.querySelector('.style-grid');
      const leftArrow = document.querySelector('.left-arrow');
      const rightArrow = document.querySelector('.right-arrow');

      // Calcula o deslocamento: largura do card (320px) + gap (30px) = 350px
      const cardWidthAndGap = 350; 

      if(leftArrow && rightArrow && track) {
        
        // Botão Próximo (Avança um card e joga o primeiro pro final)
        rightArrow.addEventListener('click', () => {
          const firstCard = track.firstElementChild;
          track.style.transition = 'transform 0.4s ease-in-out';
          track.style.transform = `translateX(-${cardWidthAndGap}px)`;

          setTimeout(() => {
            track.style.transition = 'none';
            track.appendChild(firstCard); // Move o DOM real
            track.style.transform = 'translateX(0)';
          }, 400); // Aguarda o fim da animação do CSS
        });

        // Botão Anterior (Puxa o último card pro começo e anima)
        leftArrow.addEventListener('click', () => {
          const lastCard = track.lastElementChild;
          track.style.transition = 'none';
          track.prepend(lastCard); // Move o DOM real primeiro
          track.style.transform = `translateX(-${cardWidthAndGap}px)`;

          // Timeout curtinho só pro navegador registrar a posição sem animar
          setTimeout(() => {
            track.style.transition = 'transform 0.4s ease-in-out';
            track.style.transform = 'translateX(0)';
          }, 10);
        });
      }

      // ==========================================
      // LÓGICA DO FAQ
      // ==========================================
      const faqQuestions = document.querySelectorAll('.faq-question');
      faqQuestions.forEach(question => {
        question.addEventListener('click', () => {
          const item = question.parentElement;
          item.classList.toggle('active');
        });
      });
    });

    // Função para exibir alerta e redirecionar para o login
    function exigirLogin(event) {
        event.preventDefault(); 
        alert("Para ter acesso a lugares incríveis e ter uma experiência exclusiva, faça o login!");
        window.location.href = "php/login.php"; 
    }
  </script>

  <script>
  (function(){if(!window.chatbase||window.chatbase("getState")!=="initialized"){window.chatbase=(...arguments)=>{if(!window.chatbase.q){window.chatbase.q=[]}window.chatbase.q.push(arguments)};window.chatbase=new Proxy(window.chatbase,{get(target,prop){if(prop==="q"){return target.q}return(...args)=>target(prop,...args)}})}const onLoad=function(){const script=document.createElement("script");script.src="https://www.chatbase.co/embed.min.js";script.id="DRTXXKRSH7Nd-GgMoyh3Y";script.domain="www.chatbase.co";document.body.appendChild(script)};if(document.readyState==="complete"){onLoad()}else{window.addEventListener("load",onLoad)}})();
  </script>

  <?php
  if (isset($conn) && $conn) {
      $conn->close();
  }
  ?>
    
</body>
</html>