<?php
session_start();
include '../conexao.php'; // Inclui a conexão

// ===================================================================
// 1. VERIFICAR LOGIN (MUDANÇA: Usando SESSÃO)
// ===================================================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../php/login.php"); // Se não estiver logado, vai para o login
    exit();
}
$user_id = $_SESSION['user_id']; // Pega o ID do usuário logado

// ===================================================================
// 2. BUSCAR PREFERÊNCIAS ATUAIS DO BANCO
// ===================================================================
$prefs = [
    'vibe' => [],      // Tags da Pergunta 1
    'food' => [],      // JSON da Pergunta 2 (com tags)
    'atividade' => [], // Tags da Pergunta 3
    'comfort' => []    // Tags da Pergunta 5 (Conforto)
];

// Usamos as colunas do seu banco de dados
// <-- CORREÇÃO: Removido pref_orcamento do SELECT
$sql_prefs = "SELECT pref_vibe, pref_food_ranking, pref_atividade, pref_comfort FROM usuarios WHERE id = ?";
$stmt_prefs = $conn->prepare($sql_prefs);

if ($stmt_prefs) {
    $stmt_prefs->bind_param("i", $user_id);
    $stmt_prefs->execute();
    $result_prefs = $stmt_prefs->get_result();
    
    if ($data = $result_prefs->fetch_assoc()) {
        $prefs['vibe'] = !empty($data['pref_vibe']) ? array_map('trim', explode(',', $data['pref_vibe'])) : [];
        $prefs['atividade'] = !empty($data['pref_atividade']) ? array_map('trim', explode(',', $data['pref_atividade'])) : [];
        $prefs['comfort'] = !empty($data['pref_comfort']) ? array_map('trim', explode(',', $data['pref_comfort'])) : [];
        $prefs['food'] = !empty($data['pref_food_ranking']) ? json_decode($data['pref_food_ranking'], true) : [];
    }
    $stmt_prefs->close();
}
$conn->close();

// ===================================================================
// 3. MAPAS REVERSOS (Tag do DB -> 'value' do HTML do questionário)
// ===================================================================

// P1: Vibe (Tag -> Valor do HTML)
$map_vibe_reverse = [
    'Romântico' => 'Aconchego & Conexão',
    'Calmo' => 'Aconchego & Conexão',
    'Íntimista' => 'Aconchego & Conexão',
    'Agitado' => 'Agito & Diversão',
    'Social / Para Grupos' => 'Agito & Diversão',
    'Descontraído' => 'Agito & Diversão',
    'Ao Ar Livre' => 'Aventura & Descoberta',
    'Trilha / Caminhada' => 'Aventura & Descoberta', // Adicionado para cobrir todas as tags
    'Parque / Praça' => 'Aventura & Descoberta', // Adicionado para cobrir todas as tags
    'Sofisticado' => 'Sofisticado & Romântico',
    'À la carte' => 'Sofisticado & Romântico'
];

// P2: Food (Tag -> Valor do HTML)
$map_food_reverse = [
    'Hamburgueria' => 'Hambúrguer',
    'Pizzaria' => 'Pizza',
    'Tapas / Petiscos' => 'Bar & Petiscos',
    'Adega / Vinhos' => 'Vinhos & Jantar',
    'Jantar' => 'Vinhos & Jantar',
    'Italiana' => 'Italiana',
    'Japonesa' => 'Japonesa',
    'Cafeteria' => 'Cafeteria',
    'Opções Veganas' => 'Opções dietéticas',
    'Opções Vegetarianas' => 'Opções dietéticas',
    'Opções Sem Glúten' => 'Opções dietéticas',
    'Opções Sem Lactose' => 'Opções dietéticas',
    'Opções Low Carb' => 'Opções dietéticas',
    'Orgânico' => 'Opções dietéticas'
];

// P3: Atividade (Tag -> Valor do HTML)
$map_activity_reverse = [
    'Música ao Vivo' => 'Música ao Vivo',
    'Exposição de Arte / Galeria' => 'Arte & Cultura',
    'Cinema' => 'Arte & Cultura',
    'Teatro' => 'Arte & Cultura',
    'Museu' => 'Arte & Cultura',
    'Show /Apres.' => 'Show & Apresentações',
    'Jogos de Tabuleiro' => 'Jogos & Competições',
    'Fliperama / Arcade' => 'Jogos & Competições',
    'Sinuca / Bilhar' => 'Jogos & Competições',
    'Boliche' => 'Jogos & Competições',
    'Calmo' => 'Som ambiente & calmo'
];

// <-- CORREÇÃO: P4 (ORÇAMENTO) MAPA REVERSO REMOVIDO -->

// P5 (Agora P4): Conforto (Tag -> Valor do HTML)
$map_comfort_reverse = [
    'Acessível para Cadeirantes (Rampas, banheiros adaptados)' => 'Acessibilidade Física',
    'Espaço LGBTQIAP+ Friendly (Ambiente seguro e acolhedor)' => 'Ambiente Seguro',
    'Espaço Sensorialmente Amigável (Luz baixa, pouco ruído, para pessoas no espectro autista)' => 'Baixo Estímulo',
    'Cardápio em Braile' => 'Cardápio Acessível',
    'Comunicação em Libras (Atendentes que se comunicam em Libras)' => 'Comunicação em Libras'
    // 'Nenhum' é tratado separadamente
];

// ===================================================================
// 4. FUNÇÕES HELPER (PHP) PARA MARCAR OS CAMPOS NO HTML
// ===================================================================

// Função para checar o radio (Passo 1)
function isCheckedRadio($value, $prefs_vibe_array, $map_reverse) {
    foreach ($map_reverse as $tag => $val) {
        if ($val == $value && in_array($tag, $prefs_vibe_array)) {
            echo 'checked';
            return;
        }
    }
}

// Função para checar os checkboxes (Passos 3, 4)
function isCheckedCheckbox($value, $prefs_array, $map_reverse) {
    foreach ($map_reverse as $tag => $val) {
        if ($val == $value && in_array($tag, $prefs_array)) {
            echo 'checked';
            return;
        }
    }
}

// Função para checar o "Nenhum" (Passo 4 - Conforto)
function isCheckedNenhum($prefs_comfort_array) {
    if (empty($prefs_comfort_array)) { // Se o array de conforto estiver VAZIO, marca "Nenhum"
        echo 'checked';
    }
}

// Prepara os dados do Ranking (Passo 2) para o JavaScript
$food_ranking_prefill = [];
if (!empty($prefs['food']) && is_array($prefs['food'])) {
    foreach ($prefs['food'] as $tag => $ordem) {
        if (isset($map_food_reverse[$tag])) {
            $value = $map_food_reverse[$tag];
            if (!isset($food_ranking_prefill[$value]) || $ordem < ($food_ranking_prefill[$value]['ordem'] ?? 1000)) {
                 $food_ranking_prefill[$value] = ['ordem' => $ordem];
            }
        }
    }
}
$food_ranking_json = json_encode($food_ranking_prefill);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Minha Essência - DuoDates</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="../css/questionario.css" />
</head>
<body>
  
  <header class="questionario-header">
    <a href="../php/login-feito.php" class="logo-link">
      <img src="../images/logoduodates.png" alt="Coração" class="logo-img">
      <span>Duo Dates</span>
    </a>
  </header>
  
    <div class="divider-line" style="margin-top: 0.8px;"></div>

  <main class="questionario-main">
    
    <form method="POST" action="../php/salvar_respostas_questionario.php">
      <input type="hidden" name="user_id" value="<?php echo $user_id; ?>"> 

      <div class="question-step" id="step-1">
        <h1>QUAL A VIBE DO SEU ENCONTRO IDEAL?</h1>
        
        <div class="options-container">
          <label>
            <input type="radio" name="pergunta1" value="Aconchego & Conexão" <?php isCheckedRadio('Aconchego & Conexão', $prefs['vibe'], $map_vibe_reverse); ?> required>
            <div class="option-card">
              <img src="https://img.freepik.com/fotos-gratis/um-casal-a-tomar-cafe-juntos_1153-3128.jpg" alt="Aconchego & Conexão">
              <span>Aconchego & Conexão</span>
            </div>
          </label>
          <label>
            <input type="radio" name="pergunta1" value="Agito & Diversão" <?php isCheckedRadio('Agito & Diversão', $prefs['vibe'], $map_vibe_reverse); ?> required>
            <div class="option-card">
              <img src="https://media.istockphoto.com/id/629838114/pt/foto/group-of-people-in-the-bar.jpg?s=612x612&w=0&k=20&c=lYBsCvWC69_txqhkeuhbTiYeTJtw4_OhnF_a5ME9Dkk=" alt="Agito & Diversão">
              <span>Agito & Diversão</span>
            </div>
          </label>
          <label>
            <input type="radio" name="pergunta1" value="Aventura & Descoberta" <?php isCheckedRadio('Aventura & Descoberta', $prefs['vibe'], $map_vibe_reverse); ?> required>
            <div class="option-card">
              <img src="https://planetamacboot.com.br/wp-content/uploads/2025/04/Aventura-em-Familia-940x630.jpg" alt="Aventura & Descoberta">
              <span>Aventura & Descoberta</span>
            </div>
          </label>
          <label>
             <input type="radio" name="pergunta1" value="Sofisticado & Romântico" <?php isCheckedRadio('Sofisticado & Romântico', $prefs['vibe'], $map_vibe_reverse); ?> required>
            <div class="option-card">
              <img src="https://static.vecteezy.com/ti/fotos-gratis/p1/29783634-luz-de-velas-jantar-romantico-intimo-aconchegante-elegante-sofisticado-gratis-foto.jpg" alt="Sofisticado & Romântico">
              <span>Elegante & Romântico</span>
            </div>
          </label>
        </div>
      </div>
      
      <div class="question-step" id="step-2" style="display:none;">
        <h1>O QUE TE DÁ ÁGUA NA BOCA?</h1>
        <p>Organize seus favoritos por ordem de preferência (mínimo 5)</p>
        
        <input type="hidden" id="pergunta2_ordem" name="pergunta2_ordem" value="">
        
        <div class="options-container">
           <div class="option-card" data-value="Hambúrguer">
            <span class="order-badge"></span>
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRwZUSs2sPHsmsKWlljqGXg_2ODrYl10ThPBQ&s" alt="Hambúrguer">
            <span>Hambúrguer</span>
          </div>
          <div class="option-card" data-value="Pizza">
            <span class="order-badge"></span>
            <img src="https://images.aws.nestle.recipes/original/1821a30a8a8acec9f74a1372be582610_sem_t%C3%ADtulo_(18).jpg" alt="Pizza">
            <span>Pizza</span>
          </div>
          <div class="option-card" data-value="Bar & Petiscos">
            <span class="order-badge"></span>
            <img src="https://cdn.abrahao.com.br/base/87d/b7e/713/cardapio-petiscos-bar.jpg" alt="Bar & Petiscos">
            <span>Bar & Petiscos</span>
          </div>
          <div class="option-card" data-value="Vinhos & Jantar">
            <span class="order-badge"></span>
            <img src="https://media.gazetadopovo.com.br/2019/07/29165051/mfabio.jpg" alt="Vinhos & Jantar">
            <span>Vinhos & Jantar</span>
          </div>
          <div class="option-card" data-value="Italiana">
            <span class="order-badge"></span>
            <img src="https://www.cidadaniaitaliana1948.com/wp-content/themes/yootheme/cache/59/Culinaria-italiana-1-590a310a.jpeg" alt="Italiana">
            <span>Italiana</span>
          </div>
          <div class="option-card" data-value="Japonesa">
            <span class="order-badge"></span>
            <img src="https://djapa.com.br/wp-content/uploads/2024/03/comida-japonesa-para-iniciantes.jpg" alt="Japonesa">
            <span>Japonesa</span>
          </div>
          <div class="option-card" data-value="Cafeteria">
            <span class="order-badge"></span>
            <img src="https://blog.archtrends.com/wp-content/uploads/2022/11/cafeteriapequenaabre-2.jpeg" alt="Cafeteria">
            <span>Cafeteria</span>
          </div>
          <div class="option-card" data-value="Opções dietéticas">
            <span class="order-badge"></span>
            <img src="https://media.istockphoto.com/id/1457433817/pt/foto/group-of-healthy-food-for-flexitarian-diet.jpg?s=612x612&w=0&k=20&c=gtYkwgspeAjAIUoSIPOeci2o02IrWwPLJY0Ql5jhiKQ=" alt="Opções dietéticas">
            <span>Opções dietéticas</span>
          </div>
        </div>
        
        <button type="button" class="btn-limpar" id="btnLimparP2">Limpar seleção</button>
      </div>

       <div class="question-step" id="step-3" style="display:none;">
        <h1>E PARA A ATIVIDADE PRINCIPAL, O QUE TE ANIMA?</h1>
        <p>Escolha até 2 dos seus favoritos</p>
        
        <div class="options-container"> 
          <label> 
            <input type="checkbox" name="pergunta3[]" value="Música ao Vivo" <?php isCheckedCheckbox('Música ao Vivo', $prefs['atividade'], $map_activity_reverse); ?>>
            <div class="option-card">
              <img src="https://offloadmedia.feverup.com/saopaulosecreto.com/wp-content/uploads/2021/09/13082917/Cafe-Hotel_Divulgacao-1024x683.jpg" alt="Música ao Vivo"> 
              <span>Música ao Vivo</span>
            </div>
          </label> 
          <label>
            <input type="checkbox" name="pergunta3[]" value="Arte & Cultura" <?php isCheckedCheckbox('Arte & Cultura', $prefs['atividade'], $map_activity_reverse); ?>>
            <div class="option-card">
             <img src="https://www.colegiodosjesuitas.com.br/wp-content/uploads/2021/08/Artigo_Site_Dia-das-Artes.jpg" alt="Arte & Cultura"> 
              <span>Arte & Cultura</span>
            </div>
          </label> 
          <label>
            <input type="checkbox" name="pergunta3[]" value="Show & Apresentações" <?php isCheckedCheckbox('Show & Apresentações', $prefs['atividade'], $map_activity_reverse); ?>>
            <div class="option-card">
             <img src="https://buffetgiardini.com.br/wp-content/uploads/2021/01/grandes-shows-em-eventos-fechados.jpg" alt="Show & Apresentações"> 
              <span>Show & Apresentações</span>
            </div>
          </label> 
          <label>
            <input type="checkbox" name="pergunta3[]" value="Jogos & Competições" <?php isCheckedCheckbox('Jogos & Competições', $prefs['atividade'], $map_activity_reverse); ?>>
            <div class="option-card">
             <img src="https://dynamic-media-cdn.tripadvisor.com/media/photo-o/21/b7/40/a4/caption.jpg?w=500&h=500&s=1" alt="Jogos & Competições"> 
              <span>Jogos & Competições</span>
            </div>
          </label> 
          <label>
            <input type="checkbox" name="pergunta3[]" value="Som ambiente & calmo" <?php isCheckedCheckbox('Som ambiente & calmo', $prefs['atividade'], $map_activity_reverse); ?>>
            <div class="option-card">
             <img src="https://thumbs.dreamstime.com/b/restaurante-moderno-elegantemente-decorado-com-lanternas-e-ilumina%C3%A7%C3%A3o-suave-durante-noite-de-ramad%C3%A3-conceito-jantar-tranquilo-365588907.jpg" alt="Som ambiente & calmo"> 
              <span>Som ambiente & calmo</span>
            </div>
          </label> 
        </div>
      </div>

      <div class="question-step" id="step-4" style="display:none;"> <h1>VAMOS CUIDAR DOS DETALHES. ALGO A CONSIDERAR PARA SEU CONFORTO E SEGURANÇA?</h1> 
        <p>Sua resposta é confidencial e nos ajuda a encontrar lugares 100% preparados para vocês.<br>Você pode selecionar mais de uma opção ou pular esta etapa.</p> 
        
        <div class="options-container"> 
          <label>
            <input type="checkbox" name="pergunta5[]" value="Acessibilidade Física" <?php isCheckedCheckbox('Acessibilidade Física', $prefs['comfort'], $map_comfort_reverse); ?>> 
            <div class="option-card"> 
              <img src="https://blog.laredo.com.br/wp-content/uploads/2018/06/203840-entenda-o-papel-da-acessibilidade-em-academias.jpg" alt="Acessibilidade Física"> 
              <span>Acessibilidade Física</span> 
            </div>
          </label>
          <label>
            <input type="checkbox" name="pergunta5[]" value="Ambiente Seguro" <?php isCheckedCheckbox('Ambiente Seguro', $prefs['comfort'], $map_comfort_reverse); ?>> 
            <div class="option-card"> 
              <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTJinlqnJfK0cjPE00icAA5TcOxKe3miVkYLA&s" alt="Ambiente Seguro"> 
              <span>Ambiente Seguro</span> 
            </div>
          </label>
          <label>
            <input type="checkbox" name="pergunta5[]" value="Baixo Estímulo" <?php isCheckedCheckbox('Baixo Estímulo', $prefs['comfort'], $map_comfort_reverse); ?>> 
            <div class="option-card"> 
               <img src="https://alper-wp.s3.amazonaws.com/2024/01/original-87ae248e4533fd3072f5b09bf0666cfa-scaled-1.jpeg" alt="Baixo Estímulo"> 
              <span>Baixo Estímulo</span> 
            </div>
          </label>
          <label>
            <input type="checkbox" name="pergunta5[]" value="Cardápio Acessível" <?php isCheckedCheckbox('Cardápio Acessível', $prefs['comfort'], $map_comfort_reverse); ?>> 
            <div class="option-card"> 
               <img src="https://portalcomunicace.home.blog/wp-content/uploads/2022/11/cardapio-brasille.jpg?w=1024" alt="Cardápio Acessível"> 
              <span>Cardápio Acessível</span> 
            </div>
          </label>
           <label>
            <input type="checkbox" name="pergunta5[]" value="Comunicação em Libras" <?php isCheckedCheckbox('Comunicação em Libras', $prefs['comfort'], $map_comfort_reverse); ?>> 
            <div class="option-card"> 
               <img src="https://alepa.quartertec.com.br/Content/HtmlEditor/Images/Upload/FILE_dca2d794-be87-4041-a25f-f1c3dfdfe906.jpg" alt="Comunicação em Libras"> 
              <span>Comunicação em Libras</span> 
            </div>
          </label>
            <label>
            <input type="checkbox" name="pergunta5[]" value="Nenhum" <?php isCheckedNenhum($prefs['comfort']); ?>> 
            <div class="option-card"> 
               <img src="https://www.patriciajacob.com.br/wp-content/uploads/2016/07/n%C3%A3o.jpg" alt="Não preciso de nada"> 
                <span>Não preciso de nada específico
                </span> 
               </div>
          </label>
           </div>
    </div>

      
     <div class="navigation-container">
        <button type="button" class="btn-nav btn-voltar-step" id="btnVoltar" style="display: none;">VOLTAR</button> 
        
        <div class="progress-bar">
          <div class="progress-bar-inner" id="progressBar"></div>
        </div>
        
        <button type="button" class="btn-nav" id="btnProximo">PRÓXIMO</button>
        <button type="submit" class="btn-nav" id="btnFinalizar" style="display:none;">SALVAR ALTERAÇÕES</button>
      </div>
      
    </form>
  </main>

  <script>
    // Passa o ranking salvo do PHP para o JavaScript
    const foodRankingPrefill = <?php echo $food_ranking_json; ?>;

    document.addEventListener("DOMContentLoaded", function() {
      // --- CORREÇÃO: CONFIGURAÇÃO GERAL (4 ETAPAS) ---
      const totalSteps = 4; 
      let currentStep = 1;

      const btnProximo = document.getElementById('btnProximo');
      const btnFinalizar = document.getElementById('btnFinalizar');
      const btnVoltar = document.getElementById('btnVoltar');
      const progressBar = document.getElementById('progressBar');

      // --- LÓGICA DO PASSO 2 (RANKING) ---
      let p2_ordem = 1;
      let p2_escolhas = {}; 
      const p2_cards = document.querySelectorAll('#step-2 .option-card');
      const p2_input_hidden = document.getElementById('pergunta2_ordem');
      const p2_btn_limpar = document.getElementById('btnLimparP2');

      // **** LÓGICA DE PRE-FILL PARA PASSO 2 ****
      function preencherRanking() {
          let rankingArray = []; 
          for (const [value, data] of Object.entries(foodRankingPrefill)) {
                rankingArray.push({ value: value, ordem: data.ordem });
          }
          rankingArray.sort((a, b) => a.ordem - b.ordem);
          
          rankingArray.forEach(item => {
              p2_cards.forEach(card => {
                  if (card.dataset.value === item.value) {
                      card.click(); 
                  }
              });
          });
      }

      function resetarPasso2() {
        p2_ordem = 1;
        p2_escolhas = {};
        p2_input_hidden.value = "";
        p2_cards.forEach(card => {
          card.classList.remove('selected');
          card.querySelector('.order-badge').innerText = "";
          card.querySelector('.order-badge').style.display = 'none'; 
        });
      }

      p2_cards.forEach(card => {
        card.addEventListener('click', function() {
          const value = card.dataset.value;
          if (card.classList.contains('selected')) return;
          card.classList.add('selected');
          card.querySelector('.order-badge').style.display = 'block'; 
          card.querySelector('.order-badge').innerText = p2_ordem + 'º';
          p2_escolhas[value] = p2_ordem;
          p2_input_hidden.value = JSON.stringify(p2_escolhas);
          p2_ordem++;
        });
      });

      p2_btn_limpar.addEventListener('click', resetarPasso2);

      // --- LÓGICA DO PASSO 3 (LIMITE DE CHECKBOX ATIVIDADE) ---
      const p3_checkboxes = document.querySelectorAll('#step-3 input[type="checkbox"]');
      const maxSelecoesP3 = 2;

      p3_checkboxes.forEach(checkbox => {
          if (checkbox.checked) {
              checkbox.closest('label').querySelector('.option-card').classList.add('selected');
          }
          checkbox.addEventListener('change', function() {
              const card = this.closest('label').querySelector('.option-card');
              if (this.checked) { card.classList.add('selected'); } 
              else { card.classList.remove('selected'); }

              const selecionados = document.querySelectorAll('#step-3 input[type="checkbox"]:checked').length;
              if (selecionados > maxSelecoesP3) {
                  alert(`Você pode escolher no máximo ${maxSelecoesP3} opções.`);
                  this.checked = false; 
                  card.classList.remove('selected'); 
              }
          });
      });

      // --- CORREÇÃO: LÓGICA DO PASSO 4 (ORÇAMENTO) REMOVIDA ---
      
      // --- CORREÇÃO: LÓGICA DO PASSO 4 (CONFORTO) --- (Antigo Passo 5)
      const p4_comfort_checkboxes = document.querySelectorAll('#step-4 input[type="checkbox"]'); // Seletor atualizado para #step-4
      
      p4_comfort_checkboxes.forEach(checkbox => {
           if (checkbox.checked) {
              checkbox.closest('label').querySelector('.option-card').classList.add('selected');
           }
          checkbox.addEventListener('change', function() {
              const card = this.closest('label').querySelector('.option-card');
              if (this.checked) { card.classList.add('selected'); } 
              else { card.classList.remove('selected'); }
              
              if (this.value === 'Nenhum' && this.checked) {
                  p4_comfort_checkboxes.forEach(cb => { // CORREÇÃO: p4_...
                      if (cb !== this) {
                          cb.checked = false;
                           cb.closest('label').querySelector('.option-card').classList.remove('selected');
                      }
                  });
              } else if (this.value !== 'Nenhum' && this.checked) {
                  const nenhumCheckbox = document.querySelector('#step-4 input[value="Nenhum"]'); // CORREÇÃO: #step-4
                  if (nenhumCheckbox && nenhumCheckbox.checked) {
                      nenhumCheckbox.checked = false;
                      nenhumCheckbox.closest('label').querySelector('.option-card').classList.remove('selected');
                  }
              }
          });
      });

      // --- FUNÇÃO PARA ATUALIZAR VISIBILIDADE DOS BOTÕES (COM VERIFICAÇÃO) ---
      function updateButtonVisibility() {
          if (currentStep === 1) {
              if (btnVoltar) btnVoltar.style.display = 'none'; 
              if (btnProximo) btnProximo.style.display = 'inline-block';
              if (btnFinalizar) btnFinalizar.style.display = 'none';
          } else if (currentStep === totalSteps) { // CORREÇÃO: totalSteps é 4
              if (btnVoltar) btnVoltar.style.display = 'inline-block'; 
              if (btnProximo) btnProximo.style.display = 'none'; 
              if (btnFinalizar) btnFinalizar.style.display = 'inline-block'; 
          } else {
              if (btnVoltar) btnVoltar.style.display = 'inline-block'; 
              if (btnProximo) btnProximo.style.display = 'inline-block'; 
              if (btnFinalizar) btnFinalizar.style.display = 'none';
          }
      }

       // --- FUNÇÃO PARA ATUALIZAR BARRA DE PROGRESSO ---
       function updateProgressBar() {
           let progressPercentage = Math.min((currentStep / totalSteps) * 100, 100); // CORREÇÃO: totalSteps é 4
           progressBar.style.width = progressPercentage + '%';
       }

      // --- LÓGICA DO BOTÃO "PRÓXIMO" ---
      btnProximo.addEventListener('click', function() {
        const currentStepDiv = document.getElementById('step-' + currentStep);
        let isValid = false;
        let alertMsg = "Por favor, responda a pergunta para continuar."; 

        // --- VALIDAÇÃO ---
        if (currentStep === 1) {
          if (currentStepDiv.querySelector('input[name="pergunta1"]:checked')) isValid = true;
        } else if (currentStep === 2) {
          if (Object.keys(p2_escolhas).length >= 5) { isValid = true; } 
          else { alertMsg = "Por favor, ordene pelo menos 5 opções para continuar."; }
        } else if (currentStep === 3) {
          const sel = currentStepDiv.querySelectorAll('input[name="pergunta3[]"]:checked').length;
          if (sel > 0 && sel <= 2) { isValid = true; } 
          else if (sel > 2) { alertMsg = "Escolha no máximo 2 opções."; } 
          else { alertMsg = "Escolha pelo menos 1 opção para continuar."; }
        }
        // --- CORREÇÃO: VALIDAÇÃO DA ETAPA 4 (ORÇAMENTO) REMOVIDA ---
        
        // O Passo 4 (Conforto) é o último, não é validado pelo "PRÓXIMO"

        // --- AÇÃO DE AVANÇAR ---
        if (!isValid) {
            alert(alertMsg); 
            return; 
        }

        currentStepDiv.style.display = 'none';
        currentStep++; 

        const nextStepDiv = document.getElementById('step-' + currentStep);
        if (nextStepDiv) {
          nextStepDiv.style.display = 'block';
        }

        updateProgressBar(); 
        updateButtonVisibility(); 
      });

      // --- LÓGICA DO BOTÃO "VOLTAR" ---
      btnVoltar.addEventListener('click', function() {
          if (currentStep > 1) {
              const currentStepDiv = document.getElementById('step-' + currentStep);
              currentStepDiv.style.display = 'none'; 
              currentStep--; 
              const prevStepDiv = document.getElementById('step-' + currentStep);
              if (prevStepDiv) {
                  prevStepDiv.style.display = 'block'; 
              }
              updateProgressBar(); 
              updateButtonVisibility(); 
          }
      });

      // --- INICIALIZAÇÃO ---
      preencherRanking(); // CHAMA A FUNÇÃO DE PRE-FILL
      updateProgressBar(); 
      updateButtonVisibility(); 

    });
  </script>

</body>
</html>