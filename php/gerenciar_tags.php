<?php
// (O dashboard_empresa.php já cuidou da $conn)

// --- LÓGICA DE BACK-END (Processar ações) ---

$mensagem_tags = '';
$tipo_mensagem_tags = '';

// AÇÃO 1: ADICIONAR NOVA TAG
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar_tag') {
    
    // Pegamos os 3 campos do formulário
    $nome_tag = trim($_POST['nome_tag']);
    $categoria = trim($_POST['categoria']);
    $subcategoria = trim($_POST['subcategoria']);
    
    if (!empty($nome_tag) && !empty($categoria) && !empty($subcategoria)) {
        
        $nome_tag_seguro = $conn->real_escape_string($nome_tag);
        $categoria_segura = $conn->real_escape_string($categoria);
        $subcategoria_segura = $conn->real_escape_string($subcategoria);
        
        // SQL atualizado para incluir as novas colunas
        $sql = "INSERT INTO tags (nome_tag, categoria, subcategoria) 
                VALUES ('$nome_tag_seguro', '$categoria_segura', '$subcategoria_segura')";
        
        if ($conn->query($sql) === TRUE) {
            $_SESSION['mensagem_tags'] = ['tipo' => 'sucesso', 'texto' => 'Tag "' . htmlspecialchars($nome_tag) . '" adicionada!'];
        } else {
            if ($conn->errno == 1062) { // 1062 = Erro de Chave Duplicada (nome_tag é UNIQUE)
                $_SESSION['mensagem_tags'] = ['tipo' => 'erro', 'texto' => 'Erro: A tag "' . htmlspecialchars($nome_tag) . '" já existe.'];
            } else {
                $_SESSION['mensagem_tags'] = ['tipo' => 'erro', 'texto' => 'Erro ao adicionar a tag: ' . $conn->error];
            }
        }
    } else {
        $_SESSION['mensagem_tags'] = ['tipo' => 'erro', 'texto' => 'Erro: Todos os campos (Tag, Categoria, Subcategoria) são obrigatórios.'];
    }
    
    header("Location: ../php/dashboard_empresa.php?pagina=tags");
    exit;
}

// AÇÃO 2: EXCLUIR UMA TAG
if (isset($_GET['acao']) && $_GET['acao'] == 'excluir_tag' && isset($_GET['id_tag'])) {
    // (O código de exclusão não muda em nada)
    $id_tag_excluir = (int)$_GET['id_tag'];
    $sql = "DELETE FROM tags WHERE id_tag = $id_tag_excluir";
    
    if ($conn->query($sql) === TRUE) {
        $_SESSION['mensagem_tags'] = ['tipo' => 'sucesso', 'texto' => 'Tag excluída com sucesso.'];
    } else {
        $_SESSION['mensagem_tags'] = ['tipo' => 'erro', 'texto' => 'Erro ao excluir a tag: ' . $conn->error];
    }
    
    header("Location: ../php/dashboard_empresa.php?pagina=tags");
    exit;
}


// --- LÓGICA DE FRONT-END (Carregar dados para exibir) ---

// SQL atualizado para ordenar pela hierarquia (ISSO É IMPORTANTE!)
$sql_select = "SELECT * FROM tags ORDER BY categoria, subcategoria, nome_tag ASC";
$resultado_tags = $conn->query($sql_select);

if (isset($_SESSION['mensagem_tags'])) {
    $mensagem_tags = $_SESSION['mensagem_tags']['texto'];
    $tipo_mensagem_tags = $_SESSION['mensagem_tags']['tipo'];
    unset($_SESSION['mensagem_tags']);
}

?>

<header class="main-header">
    <h1>Gerenciar Tags</h1>
    <p style="color: #666; margin-top: 5px;">Adicione ou remova as tags que categorizam os locais.</p>
</header>

<div class="cards-container">
    <div class="card report-card" style="width: 100%;">
        
        <?php if (!empty($mensagem_tags)): ?>
            <div class="mensagem <?php echo $tipo_mensagem_tags; ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; background-color: <?php echo $tipo_mensagem_tags == 'sucesso' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $tipo_mensagem_tags == 'sucesso' ? '#155724' : '#721c24'; ?>;">
                <?php echo $mensagem_tags; ?>
            </div>
        <?php endif; ?>

        <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px;">Adicionar Nova Tag</h4>
        <form action="../php/dashboard_empresa.php?pagina=tags" method="POST" style="margin-top: 15px;">
            <input type="hidden" name="acao" value="adicionar_tag">
            
            <div style="margin-bottom: 10px;">
                <label for="categoria" style="font-weight: bold; display: block; margin-bottom: 5px;">Categoria:</label>
                <input type="text" id="categoria" name="categoria" required placeholder="Ex: AMBIENTE E VIBE" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 300px;">
            </div>
            
            <div style="margin-bottom: 10px;">
                <label for="subcategoria" style="font-weight: bold; display: block; margin-bottom: 5px;">Subcategoria:</label>
                <input type="text" id="subcategoria" name="subcategoria" required placeholder="Ex: VIBE PRINCIPAL" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 300px;">
            </div>

            <div style="margin-bottom: 10px;">
                <label for="nome_tag" style="font-weight: bold; display: block; margin-bottom: 5px;">Nome da Tag:</label>
                <input type="text" id="nome_tag" name="nome_tag" required placeholder="Ex: Romântico" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px; min-width: 300px;">
            </div>
            
            <button type="submit" style="padding: 9px 15px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; vertical-align: middle;">Adicionar</button>
        </form>

        <h4 style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top: 30px;">Tags Existentes</h4>
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <thead>
                <tr style="background-color: #f9f9f9;">
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Categoria</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Subcategoria</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Tag</th>
                    <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($resultado_tags->num_rows > 0):
                    while ($tag = $resultado_tags->fetch_assoc()):
                ?>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 10px;"><?php echo htmlspecialchars($tag['categoria']); ?></td>
                    <td style="border: 1px solid #ddd; padding: 10px;"><?php echo htmlspecialchars($tag['subcategoria']); ?></td>
                    <td style="border: 1px solid #ddd; padding: 10px; font-weight: bold;"><?php echo htmlspecialchars($tag['nome_tag']); ?></td>
                    <td style="border: 1px solid #ddd; padding: 10px;">
                        <a href="../php/dashboard_empresa.php?pagina=tags&acao=excluir_tag&id_tag=<?php echo $tag['id_tag']; ?>" 
                           style="color: #dc3545; text-decoration: none; font-weight: bold;"
                           onclick="return confirm('Tem certeza que deseja excluir a tag \'<?php echo htmlspecialchars($tag['nome_tag']); ?>\'?');">
                           Excluir
                        </a>
                    </td>
                </tr>
                <?php
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="4" style="border: 1px solid #ddd; padding: 10px; text-align: center;">Nenhuma tag cadastrada.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

    </div>
</div>
