<?php
session_start();
include '../conexao.php'; // Sua conexão com o banco

// --- 1. CONFIGURAÇÕES E SEGURANÇA GERAL ---
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php'); // Redireciona se não estiver logado
    exit();
}
$user_id = $_SESSION['user_id'];

// *** NOVA LÓGICA: PEGAR O ID DA CONEXÃO ***
if (!isset($_GET['id_conexao']) || !is_numeric($_GET['id_conexao'])) {
    echo "Erro: ID da conexão não fornecido.";
    exit();
}
$id_conexao = (int)$_GET['id_conexao'];

// --- FLUXO PRINCIPAL: PROCESSAMENTO (POST) OU EXIBIÇÃO (GET) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // =================================================================================
    // A) LÓGICA DE PROCESSAMENTO (SALVAR AS RESPOSTAS NO BANCO DE DADOS)
    // =================================================================================
    
    $respostas_recebidas = $_POST['resposta'] ?? [];
    
    // *** NOVA LÓGICA: PEGAR O ID DA CONEXÃO DO POST ***
    if (!isset($_POST['id_conexao']) || !is_numeric($_POST['id_conexao'])) {
        echo "Erro: ID da conexão perdido durante o envio.";
        exit();
    }
    $id_conexao_post = (int)$_POST['id_conexao'];
    
    $conn->begin_transaction();

    try {
        // 1. Limpa respostas antigas DO USUÁRIO PARA ESTA CONEXÃO
        $sql_delete = "DELETE FROM respostas_usuario WHERE id_usuario_fk = ? AND id_conexao_fk = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("ii", $user_id, $id_conexao_post);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        // 2. Processa e salva as novas respostas
        foreach ($respostas_recebidas as $id_questao => $valor_resposta) {
            
            $tags_do_usuario = is_array($valor_resposta) ? $valor_resposta : explode(',', $valor_resposta);
            $tags_do_usuario = array_filter(array_map('trim', $tags_do_usuario));

            foreach ($tags_do_usuario as $tag_id) {
                $tag_id_num = (int)$tag_id; 
                $peso = 1; 

                if ($tag_id_num > 0) { 
                    // *** CORREÇÃO: ADICIONA id_conexao_fk AO INSERT ***
                    $sql_insert = "INSERT INTO respostas_usuario (id_usuario_fk, id_conexao_fk, id_questao_fk, resposta_selecionada, peso_atribuido) VALUES (?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    // Tipos: i = integer
                    $stmt_insert->bind_param("iiisi", $user_id, $id_conexao_post, $id_questao, $tag_id, $peso); 
                    $stmt_insert->execute();
                    $stmt_insert->close();
                }
            }
        }
        
        // 3. Limpa o score antigo na tabela 'conexoes' para forçar recálculo
        $sql_clear_score = "UPDATE conexoes SET compatibilidade_score = NULL WHERE id_conexao = ?";
        $stmt_clear = $conn->prepare($sql_clear_score);
        $stmt_clear->bind_param("i", $id_conexao_post);
        $stmt_clear->execute();
        $stmt_clear->close();
        

        // Tudo deu certo: Confirma e redireciona
        $conn->commit();
        // Redireciona de volta para meus_dates.php
        header("Location: ../php/meus_dates.php?status=completed");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "Erro ao processar respostas: " . htmlspecialchars($e->getMessage()); 
        exit();
    }

} else { 
    // =================================================================================
    // B) LÓGICA DE EXIBIÇÃO (BUSCAR PERGUNTAS E MONTAR O HTML)
    // =================================================================================

    // --- BUSCA DAS PERGUNTAS (Removendo a Pergunta 4) ---
    $sql_questoes = "SELECT * FROM questoes_compatibilidade WHERE id_questao != 4 ORDER BY id_questao ASC";
    $resultado_questoes = $conn->query($sql_questoes);

    if (!$resultado_questoes || $resultado_questoes->num_rows === 0) {
        echo "Nenhuma pergunta encontrada no questionário.";
        exit(); 
    }

    $todas_questoes = [];
    while($row = $resultado_questoes->fetch_assoc()) {
        $todas_questoes[] = $row;
    }
    $total_questoes = count($todas_questoes);
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Descubra sua Compatibilidade - DuoDates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css?v=2.3"> </head>
<body>
    <div class="questionario-container">

        <form id="form-questionario" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?id_conexao=<?php echo $id_conexao; ?>" method="POST">
            
            <input type="hidden" name="id_conexao" value="<?php echo $id_conexao; ?>">
            
            <?php 
            foreach ($todas_questoes as $index => $questao):
                $numero_questao = $index + 1;
                $id_questao = $questao['id_questao'];
                $tipo_resposta = $questao['tipo_resposta']; 
                $classe_ativa = ($index === 0) ? 'ativa' : ''; 
            ?>
            <div class="pergunta-bloco <?php echo $classe_ativa; ?>" data-indice="<?php echo $index; ?>">
                
                <div class="questionario-header">
                    <div class="logo-duodates">DuoDates</div>
                    <h2><?php echo htmlspecialchars($questao['texto_questao']); ?></h2>
                    <p class="progresso-texto">Pergunta <?php echo $numero_questao; ?> de <?php echo $total_questoes; ?></p>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="progress-bar-<?php echo $index; ?>"></div>
                    </div>
                </div>

                <div class="opcoes-container">
                    <?php 
                    $opcoes = ['a', 'b', 'c', 'd', 'e', 'f', 'g']; 
                    foreach ($opcoes as $letra): 
                        $texto_opcao = $questao['opcao_' . $letra . '_texto'] ?? null; 
                        $img_url = $questao['opcao_' . $letra . '_img_url'] ?? null;

                        if (!empty($texto_opcao)):
                            
                            $tag_id_para_salvar = $questao['opcao_' . $letra . '_tag_id'] ?? $texto_opcao; 
                            
                            $id_input = "q{$id_questao}_{$letra}";
                            $inputType = ($tipo_resposta === 'checkbox') ? 'checkbox' : 'radio';
                            $name_input = ($tipo_resposta === 'checkbox') ? "resposta[{$id_questao}][]" : "resposta[{$id_questao}]"; 
                            $required = ($tipo_resposta === 'radio') ? 'required' : ''; 
                    ?>
                    <div class="opcao-item">
                        <input type="<?php echo $inputType; ?>" 
                               id="<?php echo $id_input; ?>" 
                               name="<?php echo $name_input; ?>" 
                               value="<?php echo htmlspecialchars($tag_id_para_salvar); ?>" <?php echo $required; ?> 
                               data-questao-id="<?php echo $id_questao; ?>"
                               onchange="habilitarProximo(<?php echo $index; ?>)"> 
                        <label for="<?php echo $id_input; ?>">
                             <?php if (!empty($img_url)): ?>
                                <img src="<?php echo htmlspecialchars($img_url); ?>" alt="" class="opcao-imagem"> 
                            <?php endif; ?>
                            <span class="opcao-texto"><?php echo htmlspecialchars($texto_opcao); ?></span>
                        </label>
                    </div>
                    <?php 
                        endif; 
                    endforeach; 
                    ?>
                </div>

                <div class="navegacao-botoes">
                    <button type="button" class="botao-questionario btn-anterior" onclick="mostrarPerguntaAnterior(<?php echo $index; ?>)" <?php echo ($index === 0) ? 'disabled' : ''; ?>>
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    
                    <?php if ($numero_questao < $total_questoes): ?>
                        <button type="button" class="botao-questionario btn-proximo" id="btn-proximo-<?php echo $index; ?>" onclick="mostrarProximaPergunta(<?php echo $index; ?>)" disabled>
                            Próximo <i class="fas fa-arrow-right"></i>
                        </button>
                    <?php else: ?>
                        <button type="button" class="botao-questionario btn-proximo" id="btn-proximo-<?php echo $index; ?>" onclick="iniciarFinalizacao(<?php echo $index; ?>)" disabled> 
                            Finalizar <i class="fas fa-check"></i>
                        </button>
                    <?php endif; ?>
                </div>
                
            </div> <?php endforeach; ?>

        </form> 
        
        <div id="loading-compatibilidade" style="display: none; text-align: center; margin-top: 50px;">
            <div class="coracao-animado"><i class="fas fa-heart"></i></div> 
            <p style="font-size: 1.3rem; color: var(--cor-vinho-claro); font-weight: 600; letter-spacing: 1px; line-height: 1.5;">
                CARREGANDO<br>COMPATIBILIDADE...
            </p>
        </div>
    </div> 
    <script>
        // SEU JAVASCRIPT AQUI (NÃO MUDA NADA, PODE COPIAR E COLAR O ANTIGO)
        const perguntas = document.querySelectorAll('.pergunta-bloco');
        let perguntaAtualIndice = 0;
        const totalPerguntas = <?php echo $total_questoes; ?>;

        function atualizarBarraProgresso(indice) {
            const progresso = ((indice + 1) / totalPerguntas) * 100;
            const progressBarAtual = document.getElementById(`progress-bar-${indice}`);
             if (progressBarAtual) progressBarAtual.style.width = progresso + '%';
             for (let i = 0; i < indice; i++) {
                 const prevProgressBar = document.getElementById(`progress-bar-${i}`);
                 if (prevProgressBar) prevProgressBar.style.width = '100%';
             }
             for (let i = indice + 1; i < totalPerguntas; i++) {
                 const nextProgressBar = document.getElementById(`progress-bar-${i}`);
                 if (nextProgressBar) nextProgressBar.style.width = '0%';
             }
        }
        
        function habilitarProximo(indice) {
            const blocoPergunta = perguntas[indice];
            const inputs = blocoPergunta.querySelectorAll('input[type="radio"], input[type="checkbox"]');
            let selecionado = false;
            inputs.forEach(input => {
                if (input.checked) {
                    selecionado = true;
                }
            });
            
            const btnProximo = document.getElementById(`btn-proximo-${indice}`);
            if (btnProximo) {
                 btnProximo.disabled = !selecionado; 
            }
        }


        function mostrarPergunta(indice) {
            perguntas.forEach((pergunta, i) => {
                pergunta.classList.remove('ativa');
                if (i === indice) {
                    pergunta.classList.add('ativa');
                }
            });
            perguntaAtualIndice = indice;
            atualizarBarraProgresso(indice);
             habilitarProximo(indice); 
        }

        function mostrarProximaPergunta(indiceAtual) {
             const blocoPergunta = perguntas[indiceAtual];
             const inputs = blocoPergunta.querySelectorAll('input[type="radio"], input[type="checkbox"]');
             let selecionado = false;
             inputs.forEach(input => { if (input.checked) selecionado = true; });
             
             if(selecionado && indiceAtual < perguntas.length - 1) { 
                mostrarPergunta(indiceAtual + 1);
            } else if (!selecionado) {
                 alert("Por favor, selecione uma ou mais opções para continuar.");
            }
        }

        function mostrarPerguntaAnterior(indiceAtual) {
            if (indiceAtual > 0) {
                mostrarPergunta(indiceAtual - 1);
            }
        }
        
        function iniciarFinalizacao(indiceAtual) {
             const blocoPergunta = perguntas[indiceAtual];
             const inputs = blocoPergunta.querySelectorAll('input[type="radio"], input[type="checkbox"]');
             let selecionado = false;
             inputs.forEach(input => { if (input.checked) selecionado = true; });

             if (selecionado) {
                if(perguntas[indiceAtual]) {
                    perguntas[indiceAtual].classList.add('saindo'); 
                    setTimeout(() => {
                         if(perguntas[indiceAtual]) perguntas[indiceAtual].style.display = 'none'; 
                         const loadingDiv = document.getElementById('loading-compatibilidade');
                         if (loadingDiv) loadingDiv.style.display = 'block'; 
                    }, 500); 
                } else {
                     const loadingDiv = document.getElementById('loading-compatibilidade');
                     if (loadingDiv) loadingDiv.style.display = 'block'; 
                }

                // SUBMETE O FORMULÁRIO
                setTimeout(() => {
                    document.getElementById('form-questionario').submit();
                }, 2500); 
             } else {
                 alert("Por favor, selecione uma ou mais opções para finalizar.");
             }
        }

        // Inicializa a visualização
        document.addEventListener('DOMContentLoaded', () => {
             mostrarPergunta(0); 
        });
    </script>
</body>
</html>