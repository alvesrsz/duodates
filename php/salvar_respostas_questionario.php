<?php
session_start();
// Garante que a conexão com o banco seja incluída
include('../conexao.php'); 

// Verifica se a requisição é POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Validar e Obter User ID
    if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
        header("Location: ../php/login.php?error=invalid_user");
        exit();
    }
    $user_id = intval($_POST['user_id']);

    // ===================================================================
    // MAPAS DE TRADUÇÃO (Questionário 'value' -> DB 'nome_tag')
    // ===================================================================

    // Pergunta 1: Vibe
     $map_pergunta1 = [
        "Aconchego & Conexão" => "Romântico,Calmo,Íntimista",
        "Agito & Diversão" => "Agitado,Social / Para Grupos,Descontraído",
        "Aventura & Descoberta" => "Ao Ar Livre,Trilha / Caminhada,Parque / Praça",
        "Elegante & Romântico" => "Sofisticado,Romântico,À la carte"
    ];
    // Nota: O seu questionário (6).php tem "Sofisticado & Romântico" mas o texto é "Elegante & Romântico".
    // O value="Sofisticado & Romântico" é o que importa.

    // Pergunta 2: Food Ranking
    $map_pergunta2_food = [
        "Hambúrguer" => "Hamburgueria",
        "Pizza" => "Pizzaria",
        "Bar & Petiscos" => "Tapas / Petiscos",
        "Vinhos & Jantar" => "Adega / Vinhos,Jantar",
        "Italiana" => "Italiana",
        "Japonesa" => "Japonesa",
        "Cafeteria" => "Cafeteria",
        "Opções dietéticas" => "Opções Veganas,Opções Vegetarianas,Opções Sem Glúten,Opções Sem Lactose,Opções Low Carb,Orgânico"
    ];

    // Pergunta 3: Atividade
    $map_pergunta3_activity = [
        "Música ao Vivo" => "Música ao Vivo",
        "Arte & Cultura" => "Exposição de Arte / Galeria,Cinema,Teatro,Museu",
        "Show & Apresentações" => "Show /Apres.",
        "Jogos & Competições" => "Jogos de Tabuleiro,Fliperama / Arcade,Sinuca / Bilhar,Boliche",
        "Som ambiente & calmo" => "Calmo"
    ];

    // Pergunta 4: Orçamento (NÃO EXISTE NO MUDAR_ESSENCIA.PHP, ENTÃO NÃO PROCESSAMOS)
    
    // Pergunta 5: Conforto
    $map_pergunta5_comfort = [
        "Acessibilidade Física" => "Acessível para Cadeirantes (Rampas, banheiros adaptados)",
        "Ambiente Seguro" => "Espaço LGBTQIAP+ Friendly (Ambiente seguro e acolhedor)",
        "Baixo Estímulo" => "Espaço Sensorialmente Amigável (Luz baixa, pouco ruído, para pessoas no espectro autista)",
        "Cardápio Acessível" => "Cardápio em Braile",
        "Comunicação em Libras" => "Comunicação em Libras (Atendentes que se comunicam em Libras)",
        "Nenhum" => "" // Não salva nada
    ];

    // --- FIM DOS MAPAS ---


    // 2. Processar Respostas
    
    // Pergunta 1 (Vibe)
    $pergunta1_vibe = null;
    if (isset($_POST['pergunta1'])) {
        $resposta_p1 = $_POST['pergunta1'];
        if ($resposta_p1 === "Sofisticado & Romântico") $resposta_p1 = "Elegante & Romântico";
        
        if (isset($map_pergunta1[$resposta_p1])) {
            $pergunta1_vibe = $map_pergunta1[$resposta_p1];
        }
    }

    // Pergunta 2 (Food Ranking)
    $pergunta2_food_ranking_json = null;
    if (isset($_POST['pergunta2_ordem']) && !empty($_POST['pergunta2_ordem'])) {
        $ranking_recebido = json_decode($_POST['pergunta2_ordem'], true);
        $ranking_traduzido = [];
        if (is_array($ranking_recebido)) {
            foreach ($ranking_recebido as $nome_opcao => $ordem) {
                if (isset($map_pergunta2_food[$nome_opcao])) {
                    $tags_traduzidas_string = $map_pergunta2_food[$nome_opcao];
                    $tags_traduzidas_array = explode(',', $tags_traduzidas_string);
                    foreach ($tags_traduzidas_array as $tag) {
                         $ranking_traduzido[trim($tag)] = $ordem;
                    }
                }
            }
        }
        if (!empty($ranking_traduzido)) {
            $pergunta2_food_ranking_json = json_encode($ranking_traduzido);
        }
    }

    // Pergunta 3 (Atividade)
    $pergunta3_tags_finais = [];
    if (isset($_POST['pergunta3']) && is_array($_POST['pergunta3'])) {
        foreach ($_POST['pergunta3'] as $resposta) {
            if (isset($map_pergunta3_activity[$resposta])) {
                $pergunta3_tags_finais[] = $map_pergunta3_activity[$resposta];
            }
        }
    }
    $pergunta3_atividade = !empty($pergunta3_tags_finais) ? implode(',', $pergunta3_tags_finais) : null;

    
    // Pergunta 4 (Orçamento) - VEIO DO FORMULÁRIO?
    // (Seu mudar_essencia.php não envia, mas o questionario.php sim)
    $pergunta4_orcamento = null;
    if (isset($_POST['pergunta4']) && is_array($_POST['pergunta4'])) {
        $pergunta4_tags_finais = [];
        foreach ($_POST['pergunta4'] as $resposta) {
            if (isset($map_pergunta4_budget[$resposta])) {
                $pergunta4_tags_finais[] = $map_pergunta4_budget[$resposta];
            }
        }
        $pergunta4_orcamento = !empty($pergunta4_tags_finais) ? implode(',', $pergunta4_tags_finais) : null;
    } else {
        // Se a pergunta 4 não foi enviada (veio do mudar_essencia.php),
        // precisamos buscar o valor antigo para não apagá-lo por engano.
        $stmt_get_orc = $conn->prepare("SELECT pref_orcamento FROM usuarios WHERE id = ?");
        $stmt_get_orc->bind_param("i", $user_id);
        $stmt_get_orc->execute();
        $result_orc = $stmt_get_orc->get_result();
        if($data_orc = $result_orc->fetch_assoc()) {
            $pergunta4_orcamento = $data_orc['pref_orcamento'];
        }
        $stmt_get_orc->close();
    }


    // Pergunta 5 (Conforto)
    $pergunta5_comfort = null;
    if (isset($_POST['pergunta5']) && is_array($_POST['pergunta5'])) {
        $pergunta5_tags_finais = [];
        $nenhum_selecionado = in_array('Nenhum', $_POST['pergunta5']);
        
        if (!$nenhum_selecionado) {
            foreach ($_POST['pergunta5'] as $resposta) {
                if (isset($map_pergunta5_comfort[$resposta]) && !empty($map_pergunta5_comfort[$resposta])) {
                    $pergunta5_tags_finais[] = $map_pergunta5_comfort[$resposta];
                }
            }
        }
        // Se $pergunta5_tags_finais não estiver vazio, salva a string.
        // Se estava vazio ou "Nenhum" foi marcado, salva NULL (padrão)
        if(!empty($pergunta5_tags_finais)) {
             $pergunta5_comfort = implode(',', $pergunta5_tags_finais);
        }
    } else {
        // Se a pergunta 5 não foi enviada (veio do mudar_essencia.php quebrado)
        // precisamos buscar o valor antigo para não apagá-lo.
        $stmt_get_con = $conn->prepare("SELECT pref_comfort FROM usuarios WHERE id = ?");
        $stmt_get_con->bind_param("i", $user_id);
        $stmt_get_con->execute();
        $result_con = $stmt_get_con->get_result();
        if($data_con = $result_con->fetch_assoc()) {
            $pergunta5_comfort = $data_con['pref_comfort'];
        }
        $stmt_get_con->close();
    }


    // 3. Preparar e Executar o UPDATE no Banco de Dados
    $sql = "UPDATE usuarios SET 
                pref_vibe = ?, 
                pref_food_ranking = ?, 
                pref_atividade = ?, 
                pref_orcamento = ?, 
                pref_comfort = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("sssssi", 
            $pergunta1_vibe, 
            $pergunta2_food_ranking_json,
            $pergunta3_atividade, 
            $pergunta4_orcamento, 
            $pergunta5_comfort, 
            $user_id
        );

        if ($stmt->execute()) {
            
            // =========================================================
            // *** CORREÇÃO: LIMPA O CACHE DE SCORE NAS CONEXÕES ***
            // =========================================================
            // Após salvar o "Minha Essência", o score de todas as conexões
            // desse usuário precisam ser recalculados.
            
            $sql_clear = "UPDATE conexoes SET compatibilidade_score = NULL 
                          WHERE id_solicitante = ? OR id_solicitado = ?";
            $stmt_clear = $conn->prepare($sql_clear);
            if ($stmt_clear) {
                $stmt_clear->bind_param("ii", $user_id, $user_id);
                $stmt_clear->execute();
                $stmt_clear->close();
            }
            // =========================================================
            // *** FIM DA CORREÇÃO ***
            // =========================================================

            // Sucesso! Redireciona para a página principal (login-feito.php)
            header("Location: ../php/login-feito.php?questionario=success"); 
            exit();
        } else {
            // Erro ao executar
            error_log("Erro ao salvar questionário: " . $stmt->error);
            header("Location: ../php/mudar_essencia.php?error=update_failed");
            exit();
        }
        $stmt->close();
    } else {
        // Erro ao preparar
         error_log("Erro ao preparar query: " . $conn->error);
        header("Location: ../php/mudar_essencia.php?error=prepare_failed");
        exit();
    }

    $conn->close();

} else {
    // Se não for POST, redireciona
    header("Location: ../index.php");
    exit();
}
?>