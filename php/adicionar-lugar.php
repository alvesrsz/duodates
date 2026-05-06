<?php
// Habilita a exibição de todos os erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include('../conexao.php');

// --- INCLUSÃO DAS CLASSES DO PHPMailer ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../src/Exception.php';
require '../src/PHPMailer.php';
require '../src/SMTP.php';
// --- FIM DA INCLUSÃO ---

// --- FUNÇÃO HELPER ---
function gerarSlug($string) {
    $string = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $string);
    $string = preg_replace('/[\s-]+/', '-', $string);
    return strtolower(trim($string, '-'));
}

// --- VERIFICAÇÃO DE ACESSO ---
$is_admin = (isset($_SESSION['tipo_conta']) && $_SESSION['tipo_conta'] === 'admin');
$is_business_signup = isset($_SESSION['dados_cadastro_empresa']);
<<<<<<< HEAD
// NOVA REGRA: Verifica se é uma empresa já logada no sistema
$is_logged_business = (isset($_SESSION['tipo_conta']) && $_SESSION['tipo_conta'] === 'empresarial' && isset($_SESSION['id']));

// Se não for admin, não estiver no fluxo de cadastro E não for uma empresa logada, redireciona.
if (!$is_admin && !$is_business_signup && !$is_logged_business) {
    header("Location: ../index.php"); 
=======

// Se não for um admin E não estiver no fluxo de cadastro de empresa, redireciona.
if (!$is_admin && !$is_business_signup) {
    header("Location: ../index.php"); // Redireciona para a página inicial
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
    exit();
}

// --- BUSCA 1: CATEGORIAS (Para o dropdown principal) ---
$categorias = [];
<<<<<<< HEAD
=======
// Usando o nome da coluna correto de 'categorias' (id_categoria)
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
$result_categorias = $conn->query("SELECT id_categoria, nome FROM categorias ORDER BY nome ASC");
if ($result_categorias) {
    $categorias = $result_categorias->fetch_all(MYSQLI_ASSOC);
}

// --- BUSCA 2: TAGS AGRUPADAS (Para os checkboxes) ---
$tags_agrupadas = [];
<<<<<<< HEAD
$sql_tags = "SELECT t.id_tag AS tag_id, t.nome_tag AS tag_nome, t.categoria AS categoria_nome
             FROM tags t ORDER BY t.categoria, t.nome_tag";
$result_tags = $conn->query($sql_tags);
if ($result_tags) {
    while ($row = $result_tags->fetch_assoc()) {
=======
// Usando os nomes corretos das colunas de 'tags' (id_tag, nome_tag, categoria)
$sql_tags = "SELECT t.id_tag AS tag_id, t.nome_tag AS tag_nome, t.categoria AS categoria_nome
             FROM tags t
             ORDER BY t.categoria, t.nome_tag";

$result_tags = $conn->query($sql_tags);
if ($result_tags) {
    while ($row = $result_tags->fetch_assoc()) {
        // Agrupa as tags pelo nome da categoria
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
        $tags_agrupadas[$row['categoria_nome']][] = [
            'id' => $row['tag_id'],
            'nome' => $row['tag_nome']
        ];
    }
}

$mensagem_erro = '';
$mensagem_sucesso = '';

// --- LÓGICA DE SUBMISSÃO DO FORMULÁRIO ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
<<<<<<< HEAD
    // Upload de imagem
=======
    // Upload de imagem (comum para ambos os casos)
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
    $imagem_url = '';
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $diretorio_uploads = '../uploads/';
        if (!is_dir($diretorio_uploads)) { mkdir($diretorio_uploads, 0777, true); }
        $nome_arquivo = uniqid() . '-' . basename($_FILES["imagem"]["name"]);
        $caminho_completo = $diretorio_uploads . $nome_arquivo;

        if (move_uploaded_file($_FILES["imagem"]["tmp_name"], $caminho_completo)) {
            $imagem_url = $caminho_completo;
        } else {
            $mensagem_erro = "Erro: Falha ao mover o arquivo de imagem.";
        }
    } else {
        $mensagem_erro = "Erro: Nenhuma imagem foi enviada ou ocorreu um problema.";
    }

    if (empty($mensagem_erro)) {
<<<<<<< HEAD
        // Coletando dados
        $tags_selecionadas = isset($_POST['tags']) ? $_POST['tags'] : [];
        $id_categoria = $_POST['id_categoria'];
=======
        // --- COLETANDO TODOS OS DADOS ---
        $tags_selecionadas = isset($_POST['tags']) ? $_POST['tags'] : []; // Array de IDs
        $id_categoria = $_POST['id_categoria']; // <-- MANTIDO E OBRIGATÓRIO
        
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
        $titulo = $_POST['titulo'];
        $slug = gerarSlug($titulo);
        $descricao = $_POST['descricao'];
        $local_info = $_POST['local_info'];
        $horario_info = $_POST['horario_info'];
        $link_botao = $_POST['link_botao'];
        $texto_botao = $_POST['texto_botao'];
        
<<<<<<< HEAD
        // Validações
=======
        // --- VALIDAÇÃO (CATEGORIA PRINCIPAL) ---
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
        if (empty($id_categoria)) {
             $mensagem_erro = "Erro: Você deve selecionar com qual categoria seu local mais combina.";
        }
        
<<<<<<< HEAD
        if (empty($tags_selecionadas)) {
             $mensagem_erro = "Erro: Você deve selecionar pelo menos uma tag.";
        } else if (empty($mensagem_erro)) {
=======
        // --- NOVA VALIDAÇÃO DE TAGS (pelo menos 1 por grupo) ---
        if (empty($tags_selecionadas)) {
             $mensagem_erro = "Erro: Você deve selecionar pelo menos uma tag.";
        } else if (empty($mensagem_erro)) { // Só checa se não houver outro erro
            // $tags_agrupadas foi definido lá em cima, então podemos usá-lo aqui
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
            $categorias_obrigatorias_nomes = array_keys($tags_agrupadas);
            $categorias_selecionadas_encontradas = [];

            foreach ($tags_agrupadas as $categoria_nome => $tags_dessa_categoria) {
                foreach ($tags_dessa_categoria as $tag) {
                    if (in_array($tag['id'], $tags_selecionadas)) {
                        $categorias_selecionadas_encontradas[] = $categoria_nome;
<<<<<<< HEAD
                        break; 
=======
                        break; // Otimização: para a próxima categoria
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                    }
                }
            }

<<<<<<< HEAD
            $categorias_faltando = array_diff($categorias_obrigatorias_nomes, array_unique($categorias_selecionadas_encontradas));
=======
            // Compara os grupos que deveriam ter (obrigatorios) com os que tiveram (encontrados)
            $categorias_faltando = array_diff($categorias_obrigatorias_nomes, array_unique($categorias_selecionadas_encontradas));

>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
            if (!empty($categorias_faltando)) {
                $mensagem_erro = "Erro: Você deve selecionar pelo menos uma tag de CADA grupo. <br>Grupos faltando: " . implode(', ', $categorias_faltando);
            }
        }
<<<<<<< HEAD

        // --- 1. LÓGICA PARA ADMINISTRADORES ---
        if ($is_admin && empty($mensagem_erro)) {
            $sql_admin_insert = "INSERT INTO locais (id_categoria, titulo, descricao, imagem_url, local_info, horario_info, link_botao, texto_botao, slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_admin_insert);
            $stmt->bind_param("issssssss", $id_categoria, $titulo, $descricao, $imagem_url, $local_info, $horario_info, $link_botao, $texto_botao, $slug);
            
            if ($stmt->execute()) {
                $novo_local_id = $conn->insert_id;
=======
        // --- FIM DA NOVA VALIDAÇÃO ---


        // --- LÓGICA PARA ADMINISTRADORES ---
        if ($is_admin && empty($mensagem_erro)) {
            
            // Query com 'id_categoria' (para resolver o erro)
            $sql_admin_insert = "INSERT INTO locais (id_categoria, titulo, descricao, imagem_url, local_info, horario_info, link_botao, texto_botao, slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_admin_insert);
            
            // bind_param com "i" para id_categoria
            $stmt->bind_param("issssssss", $id_categoria, $titulo, $descricao, $imagem_url, $local_info, $horario_info, $link_botao, $texto_botao, $slug);
            
            if ($stmt->execute()) {
                // --- Inserir na tabela local_tags ---
                $novo_local_id = $conn->insert_id; // Pega o ID do local recém-criado
                
                // Usando os nomes de coluna de 'local_tags' (id_local_fk, id_tag_fk)
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                $stmt_tags = $conn->prepare("INSERT INTO local_tags (id_local_fk, id_tag_fk) VALUES (?, ?)");
                foreach ($tags_selecionadas as $tag_id) {
                    $stmt_tags->bind_param("ii", $novo_local_id, $tag_id);
                    $stmt_tags->execute();
                }
                $stmt_tags->close();
<<<<<<< HEAD
=======
                // --- FIM DA ADIÇÃO ---
                
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                header("Location: ../php/gerenciar_locais.php?status=sucesso_cadastro");
                exit();
            } else {
                $mensagem_erro = "Erro ao cadastrar o local: " . $conn->error;
            }
            $stmt->close();

<<<<<<< HEAD
        // --- 2. LÓGICA PARA CADASTRO DE NOVAS EMPRESAS (Primeiro Acesso) ---
=======
        // --- LÓGICA PARA CADASTRO DE NOVAS EMPRESAS ---
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
        } elseif ($is_business_signup && empty($mensagem_erro)) {
            $conn->begin_transaction();
            try {
                $dados_empresa = $_SESSION['dados_cadastro_empresa'];
                $tipo_conta = 'empresarial';
                $status_aprovacao = 'pendente'; 
                
                $sql_user = "INSERT INTO usuarios (nome, email, senha, cidade_bairro, tipo_conta, cnpj, status_aprovacao) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt_user = $conn->prepare($sql_user);
                $stmt_user->bind_param("sssssss", $dados_empresa['nome_empresa'], $dados_empresa['email'], $dados_empresa['senha_hash'], $dados_empresa['cidade_bairro'], $tipo_conta, $dados_empresa['cnpj'], $status_aprovacao);
                $stmt_user->execute();
                
                $id_usuario_empresa = $conn->insert_id;
<<<<<<< HEAD
                $tags_string = implode(',', $tags_selecionadas); 

                $sql_local_pendente = "INSERT INTO locais_pendentes (id_usuario, id_categoria, titulo, descricao, imagem_url, local_info, horario_info, link_botao, texto_botao, slug, tags_selecionadas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_local_pendente = $conn->prepare($sql_local_pendente);
                $stmt_local_pendente->bind_param("iisssssssss", $id_usuario_empresa, $id_categoria, $titulo, $descricao, $imagem_url, $local_info, $horario_info, $link_botao, $texto_botao, $slug, $tags_string);
                $stmt_local_pendente->execute();

=======

                // Converter array de tags em string
                $tags_string = implode(',', $tags_selecionadas); // Ex: "1,5,12"

                // Query ATUALIZADA (com id_categoria e tags_selecionadas)
                $sql_local_pendente = "INSERT INTO locais_pendentes (id_usuario, id_categoria, titulo, descricao, imagem_url, local_info, horario_info, link_botao, texto_botao, slug, tags_selecionadas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_local_pendente = $conn->prepare($sql_local_pendente);
                
                // bind_param ATUALIZADO (com "i" de id_categoria e "s" de tags_string)
                $stmt_local_pendente->bind_param("iisssssssss", $id_usuario_empresa, $id_categoria, $titulo, $descricao, $imagem_url, $local_info, $horario_info, $link_botao, $texto_botao, $slug, $tags_string);
                $stmt_local_pendente->execute();

                // Lógica de envio de e-mail (como já estava)
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                $token_ativacao = bin2hex(random_bytes(32));
                $stmt_token = $conn->prepare("UPDATE usuarios SET activation_token = ? WHERE id = ?");
                $stmt_token->bind_param("si", $token_ativacao, $id_usuario_empresa);
                $stmt_token->execute();
                $stmt_token->close();
                
                $mail = new PHPMailer(true);
<<<<<<< HEAD
=======
                // ... (configurações do PHPMailer) ...
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'duodatess@gmail.com';
                $mail->Password = 'dgpc gynr ekza orxe';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->CharSet = 'UTF-8';
                $mail->setFrom('duodatess@gmail.com', 'DuoDates');
                $mail->addAddress($dados_empresa['email'], $dados_empresa['nome_empresa']);
                $link_ativacao = "http://duodate.ct.ws/../php/ativar-empresa.php?token=" . $token_ativacao;
                $mail->isHTML(true);
                $mail->Subject = 'Ative sua conta empresarial no DuoDates';
                $mail->Body    = "<h1>Bem-vindo(a) ao DuoDates!</h1><p>Olá, <strong>{$dados_empresa['nome_empresa']}</strong>.</p><p>Para ativar sua conta e prosseguir com a análise do seu cadastro, por favor, clique no link abaixo:</p><p><a href='{$link_ativacao}' style='background-color:#5E313D; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Ativar Minha Conta</a></p>";
                $mail->send();
                
                $conn->commit();
                
                unset($_SESSION['dados_cadastro_empresa']);
                header("Location: ../php/login.php?status=cadastro_concluido");
                exit();

            } catch (Exception $e) {
                $conn->rollback();
<<<<<<< HEAD
=======
                // Verificando se o erro é sobre 'tags_selecionadas'
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                if (strpos($e->getMessage(), 'Unknown column') !== false && strpos($e->getMessage(), 'tags_selecionadas') !== false) {
                    $mensagem_erro = "Erro de Banco de Dados: A coluna 'tags_selecionadas' não foi encontrada na tabela 'locais_pendentes'. Por favor, execute o comando SQL de alteração.";
                } else {
                    $erro_especifico = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
                    $mensagem_erro = "Ocorreu um erro ao salvar os dados: " . $erro_especifico;
                }
            }
            if(isset($stmt_user)) $stmt_user->close();
            if(isset($stmt_local_pendente)) $stmt_local_pendente->close();
<<<<<<< HEAD
            
        // --- 3. LÓGICA PARA EMPRESA JÁ LOGADA (Adicionando local pelo Dashboard) ---
        } elseif ($is_logged_business && empty($mensagem_erro)) {
            $id_usuario_logado = $_SESSION['id'];
            $tags_string = implode(',', $tags_selecionadas);

            $sql_local_pendente = "INSERT INTO locais_pendentes (id_usuario, id_categoria, titulo, descricao, imagem_url, local_info, horario_info, link_botao, texto_botao, slug, tags_selecionadas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_local_pendente = $conn->prepare($sql_local_pendente);
            $stmt_local_pendente->bind_param("iisssssssss", $id_usuario_logado, $id_categoria, $titulo, $descricao, $imagem_url, $local_info, $horario_info, $link_botao, $texto_botao, $slug, $tags_string);

            if ($stmt_local_pendente->execute()) {
                // Redireciona de volta para o dashboard da empresa
                header("Location: ../php/dashboard-empresa.php?status=em_analise");
                exit();
            } else {
                $mensagem_erro = "Erro ao enviar o local para análise: " . $conn->error;
            }
            $stmt_local_pendente->close();
        }
    } 
} 
=======
        }
    } // Fim do if (empty($mensagem_erro))
} // Fim do if ($_SERVER["REQUEST_METHOD"] == "POST")
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <link rel="stylesheet" href="../css/adicionar-lugar.css">
    <meta charset="UTF-8">
    <title>Adicionar Novo Lugar</title>
    <style>
        .form-description {
            font-size: 0.9em;
            color: #555;
            margin-top: -10px;
            margin-bottom: 15px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <header class="page-header">
            <h1><?php echo $is_admin ? 'Adicionar Novo Local (Admin)' : 'Adicionar Seu Lugar'; ?></h1>
        </header>

        <main class="content-main">
            <?php if (!empty($mensagem_erro)): ?>
                <p class='message error'><?php echo $mensagem_erro; ?></p>
            <?php endif; ?>
        
            <form action="../php/adicionar-lugar.php" method="post" enctype="multipart/form-data" onsubmit="return validarFormulario()">
                
                <label>Selecione as tags do seu local:</label>
                <p class="form-description">
                    Selecione todas as tags que seu local se encaixe, isso facilitará que o usuario encontre o seu lacal ideal. <br>
                    <strong>É obrigatório selecionar pelo menos uma tag de cada grupo.</strong>
                </p>

                <div class="tags-container">
                
                    <?php if (empty($tags_agrupadas)): ?>
                        <p>Nenhuma tag cadastrada.</p>
                    <?php else: ?>
                        <?php foreach ($tags_agrupadas as $categoria_nome => $tags): ?>
                            <details class="tag-category-group" open>
                                <summary class="tag-category-title">
                                    <?php echo htmlspecialchars($categoria_nome); ?>
                                </summary>
                                <div class="tag-checkbox-list">
                                    <?php foreach ($tags as $tag): ?>
                                        <div class="tag-item">
                                            <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>" id="tag_<?php echo $tag['id']; ?>">
                                            <label for="tag_<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['nome']); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>
                
                </div>

                <label for="id_categoria">Com qual categoria seu local mais combina?</label>
                <select id="id_categoria" name="id_categoria" required>
                    <option value="" disabled selected>-- Escolha uma categoria --</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?php echo $categoria['id_categoria']; ?>">
                            <?php echo htmlspecialchars($categoria['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>


                <label for="titulo">Título do Lugar:</label>
                <input type="text" id="titulo" name="titulo" required>

                <label for="descricao">Descrição:</label>
                <textarea id="descricao" name="descricao" rows="4" required></textarea>

                <label for="local_info">Endereço / Localização:</label>
                <input type="text" id="local_info" name="local_info" required>

                <label for="horario_info">Horário de Funcionamento:</label>
                <input type="text" id="horario_info" name="horario_info">

                <label for="link_botao">Link do Botão (Ex: Instagram, WhatsApp):</label>
                <input type="url" id="link_botao" name="link_botao">
                
                <label for="texto_botao">Texto do Botão:</label>
                <input type="text" id="texto_botao" name="texto_botao" value="Ver Mais" required>

                <label for="imagem">Imagem do Lugar:</label>
                <input type="file" id="imagem" name="imagem" accept="image/png, image/jpeg" required>

                <button type="submit"><?php echo $is_admin ? 'Cadastrar Local' : 'Finalizar e Solicitar Cadastro'; ?></button>
            </form>
        </main>
    </div>

    <script>
        function validarFormulario() {
<<<<<<< HEAD
            const gruposDeTags = document.querySelectorAll('.tag-category-group');
            let categoriasFaltando = [];

            for (const grupo of gruposDeTags) {
                const nomeCategoria = grupo.querySelector('.tag-category-title').innerText;
                const checkboxesMarcados = grupo.querySelectorAll('input[type="checkbox"]:checked');
                
=======
            // Pega todos os grupos de tags (os <details>)
            const gruposDeTags = document.querySelectorAll('.tag-category-group');
            let categoriasFaltando = [];

            // Passa por cada grupo
            for (const grupo of gruposDeTags) {
                // Pega o nome do grupo (pelo <summary>)
                const nomeCategoria = grupo.querySelector('.tag-category-title').innerText;
                
                // Procura por checkboxes MARCADOS dentro desse grupo
                const checkboxesMarcados = grupo.querySelectorAll('input[type="checkbox"]:checked');
                
                // Se não achou nenhum marcado, adiciona na lista de erros
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                if (checkboxesMarcados.length === 0) {
                    categoriasFaltando.push(nomeCategoria);
                }
            }

<<<<<<< HEAD
            if (categoriasFaltando.length > 0) {
                alert('Erro: Você deve selecionar pelo menos uma tag de cada grupo.\n\nGrupos faltando:\n- ' + categoriasFaltando.join('\n- '));
                return false; 
            }

=======
            // Se a lista de erros não estiver vazia, mostra o alerta
            if (categoriasFaltando.length > 0) {
                alert('Erro: Você deve selecionar pelo menos uma tag de cada grupo.\n\nGrupos faltando:\n- ' + categoriasFaltando.join('\n- '));
                return false; // Impede o envio do formulário
            }

            // Se passou por tudo, permite o envio
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
            return true; 
        }
    </script>
</body>
</html>
<?php
// Fecha a conexão com o banco de dados de forma segura
if (isset($conn) && $conn) {
    $conn->close();
}
?>