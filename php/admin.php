<?php
session_start();
// --- MODO DETETIVE DESATIVADO: O código deve estar 100% funcional agora ---
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
// -------------------------------------------------------------------------

include '../conexao.php';

<<<<<<< HEAD
// ═══════════════════════════════════════════════════════════════════
// ▼ INTERCEPTAÇÃO: se for empresa caindo aqui, vai pro dashboard certo
// ═══════════════════════════════════════════════════════════════════
if (isset($_SESSION['tipo_conta']) && $_SESSION['tipo_conta'] === 'empresarial') {
    header('Location: dashboard_empresa.php');
    exit();
}
// ═══════════════════════════════════════════════════════════════════

=======
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
// --- VERIFICAÇÃO DE SEGURANÇA ---
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_conta'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// --- LÓGICA DE VISUALIZAÇÃO ---
$view = $_GET['view'] ?? 'dashboard';

function executarConsulta($conn, $sql) {
    $resultado = $conn->query($sql);
    return ($resultado === false) ? null : $resultado;
}

// --- BUSCA DE DADOS COM BASE NA VISUALIZAÇÃO ---

if ($view == 'dashboard') {
    // Consultas para o Dashboard
    $total_usuarios = executarConsulta($conn, "SELECT COUNT(*) FROM usuarios WHERE status_aprovacao = 'aprovado' OR tipo_conta = 'usuario'")->fetch_row()[0] ?? 0;
    $total_locais = executarConsulta($conn, "SELECT COUNT(*) FROM locais")->fetch_row()[0] ?? 0;
    $total_agendamentos = executarConsulta($conn, "SELECT COUNT(*) FROM agendamentos")->fetch_row()[0] ?? 0;
    $total_favoritos = executarConsulta($conn, "SELECT COUNT(*) FROM favoritos")->fetch_row()[0] ?? 0;
    $total_pendentes = executarConsulta($conn, "SELECT COUNT(u.id) FROM usuarios u JOIN locais_pendentes lp ON u.id = lp.id_usuario WHERE u.status_aprovacao = 'pendente'")->fetch_row()[0] ?? 0;
    $result_usuarios_recentes = executarConsulta($conn, "SELECT nome, email, criado_em FROM usuarios ORDER BY criado_em DESC LIMIT 5");
    
    // Gráficos...
    $result_locais_por_categoria = executarConsulta($conn, "SELECT c.nome, COUNT(l.id_local) as total FROM locais l JOIN categorias c ON l.id_categoria = c.id_categoria GROUP BY c.nome ORDER BY total DESC");
    $labels_grafico_donut = [];
    $dados_grafico_donut = [];
    if ($result_locais_por_categoria) {
        while ($row = $result_locais_por_categoria->fetch_assoc()) {
            $labels_grafico_donut[] = $row['nome'];
            $dados_grafico_donut[] = $row['total'];
        }
    }
    
    $sql_novos_usuarios = "SELECT DATE(criado_em) as dia, COUNT(*) as total FROM usuarios WHERE criado_em >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(criado_em) ORDER BY DATE(criado_em) ASC";
    $result_novos_usuarios = executarConsulta($conn, $sql_novos_usuarios);
    $dados_novos_usuarios = [];
    if ($result_novos_usuarios) {
        while($row = $result_novos_usuarios->fetch_assoc()){
            $dados_novos_usuarios[$row['dia']] = $row['total'];
        }
    }
    $labels_grafico_linha = [];
    $dados_grafico_linha = [];
    for ($i = 6; $i >= 0; $i--) {
        $data = date('Y-m-d', strtotime("-$i days"));
        $labels_grafico_linha[] = date('d/m', strtotime($data));
        $dados_grafico_linha[] = $dados_novos_usuarios[$data] ?? 0;
    }
    
    $sql_top_favoritos = "SELECT l.titulo, COUNT(f.id) as total_favoritos FROM favoritos f JOIN locais l ON f.place_id = l.slug GROUP BY l.titulo ORDER BY total_favoritos DESC LIMIT 5";
    $result_top_favoritos = executarConsulta($conn, $sql_top_favoritos);

} elseif ($view == 'pendentes') {
    $sql_pendentes = "SELECT 
                          u.id as id_usuario, u.nome as nome_empresa, lp.titulo as nome_local, lp.id AS id_local_pendente
                        FROM usuarios u
                        JOIN locais_pendentes lp ON u.id = lp.id_usuario
                        WHERE u.status_aprovacao = 'pendente'
                        ORDER BY u.criado_em ASC";
    $result_pendentes = executarConsulta($conn, $sql_pendentes);

} elseif ($view == 'detalhe_pendente') {
    $id_local_pendente = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id_local_pendente) {
        $sql_detalhe = "SELECT 
                              u.nome as nome_empresa, u.email, u.cnpj, u.cidade_bairro, u.criado_em, u.id as id_usuario,
                              lp.*, 
                              c.nome as nome_categoria
                          FROM locais_pendentes lp
                          JOIN usuarios u ON lp.id_usuario = u.id
                          JOIN categorias c ON lp.id_categoria = c.id_categoria
                          WHERE lp.id = ?";
        
        $stmt = $conn->prepare($sql_detalhe);
        $stmt->bind_param("i", $id_local_pendente);
        $stmt->execute();
        $result_detalhe = $stmt->get_result();
        if ($result_detalhe->num_rows > 0) {
            $detalhes = $result_detalhe->fetch_assoc();
        }
    }
} elseif ($view == 'usuarios') {
    $sql_usuarios = "SELECT id, nome, email, tipo_conta, criado_em FROM usuarios ORDER BY nome ASC";
    $resultado_usuarios = $conn->query($sql_usuarios);

} elseif ($view == 'locais') {
    
    // --- NOVO: Buscar categorias para o filtro ---
    $lista_categorias_filtro = [];
    $result_categorias = $conn->query("SELECT nome FROM categorias ORDER BY nome ASC");
    if ($result_categorias) {
        while ($row = $result_categorias->fetch_assoc()) {
            $lista_categorias_filtro[] = $row['nome'];
        }
    }
    // --- FIM NOVO ---

    // CÓDIGO CORRIGIDO FINAL: Usando id_local_fk e id_tag_fk para trazer as tags
    $sql_locais = "SELECT 
                        l.id_local, 
                        l.titulo, 
                        l.imagem_url, 
                        c.nome AS nome_categoria,
                        GROUP_CONCAT(t.nome_tag SEPARATOR ', ') AS tags_locais
                    FROM locais AS l 
                    JOIN categorias AS c ON l.id_categoria = c.id_categoria
                    LEFT JOIN local_tags AS lt ON l.id_local = lt.id_local_fk  /* CORRIGIDO */
                    LEFT JOIN tags AS t ON lt.id_tag_fk = t.id_tag             /* CORRIGIDO */
                    GROUP BY l.id_local, l.titulo, l.imagem_url, c.nome
                    ORDER BY c.nome ASC, l.titulo ASC";
    
    $resultado_locais = $conn->query($sql_locais); 

} elseif ($view == 'agendamentos') {
    $sql_agendamentos = "SELECT 
                              ag.data_agendada,
                              ag.titulo_evento,
                              u.nome as nome_usuario,
                              l.titulo as nome_local,
                              ag.criado_em
                            FROM agendamentos ag
                            JOIN usuarios u ON ag.id_usuario = u.id
                            JOIN locais l ON ag.id_local = l.id_local
                            ORDER BY ag.data_agendada DESC";
    $resultado_agendamentos = $conn->query($sql_agendamentos);

} elseif ($view == 'tags') {
    
    // Listas para as caixas de seleção
    $lista_categorias = [
        "AMBIENTE E VIBE",
        "CULINÁRIA E CARDÁPIO",
        "ATIVIDADES E CARACTERÍSTICAS ESPECIAIS",
        "OCASIÃO IDEAL",
        "INCLUSÃO E ACESSIBILIDADE",
        "FAIXA DE PREÇO"
    ];
    
    $lista_subcategorias = [
        "VIBE PRINCIPAL",
        "ESTILO DO LOCAL",
        "LOCALIZAÇÃO/ESTRUTURA",
        "TIPO DE COZINHA (INTERNACIONAL)",
        "TIPO DE COZINHA (LOCAL E ESTILOS)",
        "FOCO PRINCIPAL / ESPECIALIDADES",
        "OPÇÕES DIETÉTICAS",
        "MODELO DE SERVIÇO",
        "PERFORMANCES",
        "JOGOS E COMPETIÇÕES",
        "CULTURA E APRENDIZADO",
        "DIFERENCIAIS",
        "NATUREZA E BEM-ESTAR",
        "TIPO DE ENCONTRO",
        "COMEMORAÇÕES",
        "PERÍODO DO DIA",
        "NULL"
    ];

    // Variáveis de feedback
    $mensagem_tags = '';
    $tipo_mensagem_tags = '';

    // AÇÃO 1: ADICIONAR NOVA TAG
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar_tag') {
        $nome_tag = trim($_POST['nome_tag']);
        $categoria = trim($_POST['categoria']);
        $subcategoria = trim($_POST['subcategoria']);

        if (!empty($nome_tag) && !empty($categoria) && !empty($subcategoria)) {
            $nome_tag_seguro = $conn->real_escape_string($nome_tag);
            $categoria_segura = $conn->real_escape_string($categoria);
            $subcategoria_segura = $conn->real_escape_string($subcategoria);

            $sql_insert = "INSERT INTO tags (nome_tag, categoria, subcategoria) 
                           VALUES ('$nome_tag_seguro', '$categoria_segura', '$subcategoria_segura')";

            if ($conn->query($sql_insert) === TRUE) {
                $_SESSION['admin_tag_message'] = ['tipo' => 'success', 'texto' => 'Tag "' . htmlspecialchars($nome_tag) . '" adicionada!'];
            } else {
                if ($conn->errno == 1062) { 
                    $_SESSION['admin_tag_message'] = ['tipo' => 'error', 'texto' => 'Erro: Essa tag já existe.'];
                } else {
                    $_SESSION['admin_tag_message'] = ['tipo' => 'error', 'texto' => 'Erro ao adicionar a tag: ' . $conn->error];
                }
            }
        } else {
            $_SESSION['admin_tag_message'] = ['tipo' => 'error', 'texto' => 'Erro: Todos os campos (Tag, Categoria, Subcategoria) são obrigatórios.'];
        }
        
        header("Location: ../php/admin.php?view=tags"); 
        exit;
    }

    // AÇÃO 2: EXCLUIR UMA TAG
    if (isset($_GET['acao']) && $_GET['acao'] == 'excluir_tag' && isset($_GET['id_tag'])) {
        $id_tag_excluir = (int)$_GET['id_tag'];
        $sql_delete = "DELETE FROM tags WHERE id_tag = $id_tag_excluir";

        if ($conn->query($sql_delete) === TRUE) {
            $_SESSION['admin_tag_message'] = ['tipo' => 'success', 'texto' => 'Tag excluída com sucesso.'];
        } else {
            $_SESSION['admin_tag_message'] = ['tipo' => 'error', 'texto' => 'Erro ao excluir a tag: ' . $conn->error];
        }
        header("Location: ../php/admin.php?view=tags"); 
        exit;
    }

    // Checa se há mensagens de feedback da sessão (para mostrar no HTML)
    if (isset($_SESSION['admin_tag_message'])) {
        $mensagem_tags = $_SESSION['admin_tag_message']['texto'];
        $tipo_mensagem_tags = $_SESSION['admin_tag_message']['tipo'] == 'success' ? 'success' : 'error';
        unset($_SESSION['admin_tag_message']);
    }

    // Buscar todas as tags existentes para listar
    $sql_select_tags = "SELECT * FROM tags ORDER BY categoria, subcategoria, nome_tag ASC";
    $resultado_tags = executarConsulta($conn, $sql_select_tags);

// ===================================================================
// SEÇÃO: GERENCIAR CATEGORIAS (FINAL COM LINK E ÍCONE)
// ===================================================================
} elseif ($view == 'categorias') {

    $mensagem_cat = '';
    $tipo_mensagem_cat = '';
    
    // --- Função helper para Upload ---
    function uploadImagemCategoria($file) {
        if (isset($file) && $file['error'] == 0) {
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($fileExt, $allowed)) {
                if ($fileSize < 5000000) { // 5MB
                    $newFileName = uniqid('cat_', true) . '.' . $fileExt;
                    $fileDestination = '../uploads/categorias/' . $newFileName;
                    if (move_uploaded_file($fileTmpName, $fileDestination)) {
                        return ['success' => true, 'path' => $fileDestination]; // Sucesso
                    } else {
                        return ['success' => false, 'message' => 'Erro: Falha ao mover o arquivo.'];
                    }
                } else {
                    return ['success' => false, 'message' => 'Erro: O arquivo é muito grande (limite de 5MB).'];
                }
            } else {
                return ['success' => false, 'message' => 'Erro: Tipo de arquivo não permitido.'];
            }
        }
        return ['success' => false, 'message' => 'Erro: Nenhum arquivo enviado ou erro no upload.'];
    }
    // --- Fim da Função Helper ---

    // ==================================
    // AÇÃO 1: ADICIONAR NOVA CATEGORIA (COM TUDO)
    // ==================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'adicionar_categoria') {
        $nome_categoria = trim($_POST['nome_categoria']);
        $link_pagina = trim($_POST['link_pagina']);
        $icone_fa = trim($_POST['icone_fa']);
        
        $uploadResult = uploadImagemCategoria($_FILES['imagem_categoria']);
        
        if (!empty($nome_categoria) && !empty($link_pagina) && !empty($icone_fa) && $uploadResult['success']) {
            $fileDestination = $uploadResult['path'];

            $sql_check = "SELECT COUNT(*) FROM categorias WHERE nome = ?";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bind_param("s", $nome_categoria);
            $stmt_check->execute();
            $count = $stmt_check->get_result()->fetch_row()[0];

            if ($count > 0) {
                $_SESSION['admin_cat_message'] = ['tipo' => 'error', 'texto' => 'Erro: A categoria "' . htmlspecialchars($nome_categoria) . '" já existe.'];
                if (file_exists($fileDestination)) { unlink($fileDestination); }
            } else {
                $sql_insert = "INSERT INTO categorias (nome, imagem_url, link_pagina, icone_fa) VALUES (?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("ssss", $nome_categoria, $fileDestination, $link_pagina, $icone_fa);
                if ($stmt_insert->execute()) {
                    $_SESSION['admin_cat_message'] = ['tipo' => 'success', 'texto' => 'Categoria "' . htmlspecialchars($nome_categoria) . '" adicionada com sucesso.'];
                } else {
                    $_SESSION['admin_cat_message'] = ['tipo' => 'error', 'texto' => 'Erro ao adicionar no banco de dados: ' . $conn->error];
                    if (file_exists($fileDestination)) { unlink($fileDestination); }
                }
            }
        } else {
            if (!$uploadResult['success']) {
                $_SESSION['admin_cat_message'] = ['tipo' => 'error', 'texto' => $uploadResult['message']];
            } else {
                $_SESSION['admin_cat_message'] = ['tipo' => 'error', 'texto' => 'Erro: Todos os campos (Nome, Imagem, Link, Ícone) são obrigatórios.'];
            }
            if (isset($uploadResult['path']) && file_exists($uploadResult['path'])) {
                 unlink($uploadResult['path']);
            }
        }
        header("Location:../php/admin.php?view=categorias"); 
        exit;
    }

    // ==================================
    // AÇÃO 2: ATUALIZAR CATEGORIA EXISTENTE (Formulário único)
    // ==================================
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'atualizar_categoria_antiga') {
        $id_categoria = (int)$_POST['id_categoria'];
        $link_pagina = trim($_POST['link_pagina']);
        $icone_fa = trim($_POST['icone_fa']);

        if ($id_categoria > 0) {
            // Primeiro, atualiza os campos de texto (link e icone)
            $sql_update_texto = "UPDATE categorias SET link_pagina = ?, icone_fa = ? WHERE id_categoria = ?";
            $stmt_update_texto = $conn->prepare($sql_update_texto);
            $stmt_update_texto->bind_param("ssi", $link_pagina, $icone_fa, $id_categoria);
            $stmt_update_texto->execute();

            // Segundo, verifica se uma NOVA imagem foi enviada
            if (isset($_FILES['imagem_categoria']) && $_FILES['imagem_categoria']['error'] == 0) {
                
                // Antes de subir a nova, pega o caminho da imagem antiga para deletar
                $sql_get_img = "SELECT imagem_url FROM categorias WHERE id_categoria = ?";
                $stmt_get_img = $conn->prepare($sql_get_img);
                $stmt_get_img->bind_param("i", $id_categoria);
                $stmt_get_img->execute();
                $imagem_antiga = $stmt_get_img->get_result()->fetch_assoc()['imagem_url'];

                // Faz o upload da nova imagem
                $uploadResult = uploadImagemCategoria($_FILES['imagem_categoria']);
                
                if ($uploadResult['success']) {
                    $fileDestination = $uploadResult['path'];
                    
                    // Atualiza o banco com a nova imagem
                    $sql_update_img = "UPDATE categorias SET imagem_url = ? WHERE id_categoria = ?";
                    $stmt_update_img = $conn->prepare($sql_update_img);
                    $stmt_update_img->bind_param("si", $fileDestination, $id_categoria);
                    
                    if ($stmt_update_img->execute()) {
                        // Deleta a imagem antiga do servidor se a atualização do BD foi bem-sucedida
                        if (!empty($imagem_antiga) && file_exists($imagem_antiga)) {
                            unlink($imagem_antiga);
                        }
                        $_SESSION['admin_cat_message'] = ['tipo' => 'success', 'texto' => 'Categoria atualizada com sucesso.'];
                    } else {
                        $_SESSION['admin_cat_message'] = ['tipo' => 'error', 'texto' => 'Erro ao atualizar a imagem no banco.'];
                        if (file_exists($fileDestination)) { unlink($fileDestination); }
                    }
                } else {
                    // Upload da nova imagem falhou
                    $_SESSION['admin_cat_message'] = ['tipo' => 'error', 'texto' => 'Campos de texto atualizados, mas ' . $uploadResult['message']];
                }
            } else {
                // Nenhuma nova imagem foi enviada, apenas os textos foram atualizados
                $_SESSION['admin_cat_message'] = ['tipo' => 'success', 'texto' => 'Categoria (Link e Ícone) atualizada com sucesso.'];
            }
        } else {
            $_SESSION['admin_cat_message'] = ['tipo' => 'error', 'texto' => 'Erro: Você precisa selecionar uma categoria.'];
        }
        
        header("Location: ../php/admin.php?view=categorias"); 
        exit;
    }


    // ==================================
    // AÇÃO 3: EXCLUIR UMA CATEGORIA
    // ==================================
    if (isset($_GET['acao']) && $_GET['acao'] == 'excluir_categoria' && isset($_GET['id'])) {
        $id_cat_excluir = (int)$_GET['id'];
        
        $sql_check = "SELECT COUNT(*) FROM locais WHERE id_categoria = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $id_cat_excluir);
        $stmt_check->execute();
        $count_locais = $stmt_check->get_result()->fetch_row()[0];
        
        if ($count_locais > 0) {
            $_SESSION['admin_cat_message'] = [
                'tipo' => 'error', 
                'texto' => 'Erro: Esta categoria está sendo usada por ' . $count_locais . ' local(is).'
            ];
        } else {
            $sql_get_img = "SELECT imagem_url FROM categorias WHERE id_categoria = ?";
            $stmt_get_img = $conn->prepare($sql_get_img);
            $stmt_get_img->bind_param("i", $id_cat_excluir);
            $stmt_get_img->execute();
            $imagem_para_excluir = $stmt_get_img->get_result()->fetch_assoc()['imagem_url'];

            $sql_delete = "DELETE FROM categorias WHERE id_categoria = ?";
            $stmt_delete = $conn->prepare($sql_delete);
            $stmt_delete->bind_param("i", $id_cat_excluir);

            if ($stmt_delete->execute()) {
                if (!empty($imagem_para_excluir) && file_exists($imagem_para_excluir)) {
                    unlink($imagem_para_excluir);
                }
                $_SESSION['admin_cat_message'] = ['tipo' => 'success', 'texto' => 'Categoria excluída com sucesso.'];
            } else {
                $_SESSION['admin_cat_message'] = ['tipo' => 'error', 'texto' => 'Erro ao excluir a categoria: ' . $conn->error];
            }
        }
        header("Location: ../php/admin.php?view=categorias"); 
        exit;
    }
    
    // Checa se há mensagens de feedback da sessão
    if (isset($_SESSION['admin_cat_message'])) {
        $mensagem_cat = $_SESSION['admin_cat_message']['texto'];
        $tipo_mensagem_cat = $_SESSION['admin_cat_message']['tipo'] == 'success' ? 'success' : 'error';
        unset($_SESSION['admin_cat_message']);
    }

    // Buscar TODAS as categorias para a tabela de listagem
    $sql_select_todas = "SELECT * FROM categorias ORDER BY nome ASC";
    $resultado_categorias = executarConsulta($conn, $sql_select_todas);
    
    // Clonar resultado para o dropdown
    $resultado_categorias_dropdown = executarConsulta($conn, "SELECT id_categoria, nome FROM categorias ORDER BY nome ASC");
    $categorias_para_dropdown = [];
    if ($resultado_categorias_dropdown) {
        while ($row = $resultado_categorias_dropdown->fetch_assoc()) {
            $categorias_para_dropdown[] = $row;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - DuoDates</title>
    <link rel="stylesheet" href="../css/admin.css?v=1.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include '../php/sidebar.php'; ?>
        
        <?php if ($view == 'dashboard'): ?>
            <main class="main-content">
                <header class="main-header">
                    <h3>Visão Geral do Sistema</h3>
                    <div class="header-actions"><a href="../php/perfil.php">Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</a></div>
                </header>
                <div class="content-grid">
                    <div class="stat-card blue"><h4>Total de Usuários</h4><p><?php echo $total_usuarios; ?></p><i class="fas fa-users"></i></div>
                    <div class="stat-card green"><h4>Total de Locais</h4><p><?php echo $total_locais; ?></p><i class="fas fa-map-marker-alt"></i></div>
                    <div class="stat-card red"><h4>Dates Agendados</h4><p><?php echo $total_agendamentos; ?></p><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-card yellow"><h4>Locais Favoritados</h4><p><?php echo $total_favoritos; ?></p><i class="fas fa-heart"></i></div>
                    <div class="stat-card purple"><h4>Pendentes</h4><p><?php echo $total_pendentes; ?></p><i class="fas fa-hourglass-start"></i></div>
                    <div class="panel large"><h4>Novos Usuários (Últimos 7 dias)</h4><canvas id="novosUsuariosChart"></canvas></div>
                    <div class="panel"><h4>Top 5 Locais Favoritados</h4>
                        <ol class="top-list">
                            <?php if ($result_top_favoritos && $result_top_favoritos->num_rows > 0): while($local = $result_top_favoritos->fetch_assoc()): ?>
                            <li><span><?php echo htmlspecialchars($local['titulo']); ?></span><span><?php echo $local['total_favoritos']; ?> <i class="fas fa-heart"></i></span></li>
                            <?php endwhile; else: ?><li>Nenhum local favoritado ainda.</li><?php endif; ?>
                        </ol>
                    </div>
                    <div class="panel large"><h4>Usuários Recentes</h4>
                        <table class="data-table">
                            <thead><tr><th>Nome</th><th>Email</th><th>Data de Cadastro</th></tr></thead>
                            <tbody>
                                <?php if ($result_usuarios_recentes && $result_usuarios_recentes->num_rows > 0): while($usuario = $result_usuarios_recentes->fetch_assoc()): ?>
                                <tr><td><?php echo htmlspecialchars($usuario['nome']); ?></td><td><?php echo htmlspecialchars($usuario['email']); ?></td><td><?php echo date('d/m/Y', strtotime($usuario['criado_em'])); ?></td></tr>
                                <?php endwhile; else: ?><tr><td colspan="3">Nenhum usuário recente.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="panel"><h4>Locais por Categoria</h4><canvas id="locaisPorCategoriaChart"></canvas></div>
                </div>
            </main>

        <?php elseif ($view == 'pendentes'): ?>
            <main class="main-content">
                <header class="main-header"><h3><i class="fas fa-hourglass-half"></i> Solicitações de Cadastro Pendentes</h3></header>
                <div class="panel large full-width"> 
                    <table class="data-table">
                        <thead><tr><th>Nome da Empresa</th><th>Local Cadastrado</th><th>Ações</th></tr></thead>
                        <tbody>
                            <?php if ($result_pendentes && $result_pendentes->num_rows > 0): ?>
                                <?php while($solicitacao = $result_pendentes->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($solicitacao['nome_empresa']); ?></td>
                                        <td><?php echo htmlspecialchars($solicitacao['nome_local']); ?></td>
                                        <td class="actions">
                                            <a href="../php/admin.php?view=detalhe_pendente&id=<?php echo $solicitacao['id_local_pendente']; ?>" class="btn-view"><i class="fas fa-eye"></i> Ver Detalhes</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3">Nenhuma solicitação pendente no momento.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>

        <?php elseif ($view == 'detalhe_pendente' && isset($detalhes)): ?>
            <main class="main-content">
                   <header class="main-header"><h3><i class="fas fa-file-alt"></i> Detalhes da Solicitação</h3></header>
                   <div class="detail-view">
                       <div class="detail-grid">
                           <div class="detail-section">
                               <h4><i class="fas fa-building"></i> Dados da Empresa</h4>
                               <div class="detail-item"><strong>Nome:</strong> <span><?php echo htmlspecialchars($detalhes['nome_empresa']); ?></span></div>
                               <div class="detail-item"><strong>Email:</strong> <span><?php echo htmlspecialchars($detalhes['email']); ?></span></div>
                               <div class="detail-item"><strong>CNPJ:</strong> <span><?php echo htmlspecialchars($detalhes['cnpj']); ?></span></div>
                               <div class="detail-item"><strong>Cidade/Bairro:</strong> <span><?php echo htmlspecialchars($detalhes['cidade_bairro']); ?></span></div>
                               <div class="detail-item"><strong>Data Solicitação:</strong> <span><?php echo date('d/m/Y H:i', strtotime($detalhes['criado_em'])); ?></span></div>
                           </div>
                           <div class="detail-section">
                               <h4><i class="fas fa-map-marker-alt"></i> Dados do Local</h4>
                               <div class="detail-item"><strong>Título:</strong> <span><?php echo htmlspecialchars($detalhes['titulo']); ?></span></div>
                               <div class="detail-item"><strong>Categoria:</strong> <span><?php echo htmlspecialchars($detalhes['nome_categoria']); ?></span></div>
                               <div class="detail-item"><strong>Endereço/Local:</strong> <span><?php echo htmlspecialchars($detalhes['local_info']); ?></span></div>
                               <div class="detail-item"><strong>Horário:</strong> <span><?php echo htmlspecialchars($detalhes['horario_info']); ?></span></div>
                           </div>
                       </div>
                       <div class="detail-section">
                           <h4><i class="fas fa-info-circle"></i> Descrição, Imagem e Link</h4>
                           <div class="detail-item"><strong>Descrição Completa:</strong> <span><?php echo nl2br(htmlspecialchars($detalhes['descricao'])); ?></span></div>
                           <div class="detail-item"><strong>Link do Botão:</strong> <a href="<?php echo htmlspecialchars($detalhes['link_botao']); ?>" target="_blank"><?php echo htmlspecialchars($detalhes['link_botao']); ?></a></div>
                           <div class="detail-item"><strong>Texto do Botão:</strong> <span><?php echo htmlspecialchars($detalhes['texto_botao']); ?></span></div>
                           <div class="detail-item"><strong>Imagem Enviada:</strong><br><img src="<?php echo htmlspecialchars($detalhes['imagem_url']); ?>" alt="Imagem do local" class="detail-img"></div>
                       </div>
                       <div class="detail-actions actions">
                           <h4>Aprovar ou Reprovar esta Solicitação:</h4>
                           <a href="../php/aprovar-empresa.php?id=<?php echo $detalhes['id_usuario']; ?>" class="btn-approve"><i class="fas fa-check"></i> Aprovar</a>
                           <a href="../php/reprovar-empresa.php?id=<?php echo $detalhes['id_usuario']; ?>" class="btn-reject"><i class="fas fa-times"></i> Reprovar</a>
                       </div>
                    </div>
            </main>
            
        <?php elseif ($view == 'usuarios'): ?>
            <main class="main-content">
                <header class="main-header">
                    <h3>Gerenciar Usuários</h3>
                    <div class="header-actions">
                        <a href="../php/perfil.php">Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</a>
                    </div>
                </header>

                <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="message success"><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
                <?php endif; ?>
                <?php if(isset($_SESSION['error_message'])): ?>
                    <div class="message error"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>

                <div class="content-grid">
                    <div class="panel large">
                        <h4>Todos os Usuários</h4>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Tipo de Conta</th>
                                    <th>Data de Cadastro</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($resultado_usuarios->num_rows > 0): ?>
                                    <?php while($usuario = $resultado_usuarios->fetch_assoc()): ?>
                                    <tr id="user-row-<?php echo $usuario['id']; ?>">
                                        <td><?php echo $usuario['id']; ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td><?php echo ucfirst(htmlspecialchars($usuario['tipo_conta'])); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($usuario['criado_em'])); ?></td>
                                        <td class="actions-cell">
                                            <a href="../php/editar_usuario.php?id=<?php echo $usuario['id']; ?>" class="action-btn edit">Editar</a>
                                            <button class="action-btn delete" data-id="<?php echo $usuario['id']; ?>">Deletar</button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">Nenhum usuário encontrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const deleteButtons = document.querySelectorAll('.action-btn.delete');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const userId = this.dataset.id;
                        if (confirm('Tem certeza que deseja deletar este usuário? Esta ação não pode ser desfeita.')) {
                            fetch('../php/deletar_usuario.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ user_id: userId })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    document.getElementById('user-row-' + userId).remove();
                                } else {
                                    alert('Erro: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                alert('Ocorreu um erro de comunicação.');
                            });
                        }
                    });
                });
            });
            </script>

        <?php elseif ($view == 'locais'): ?>
            <main class="main-content">
                <header class="main-header">
                    <h3>Gerenciar Locais</h3>
                    <div class="header-actions">
                        <a href="../php/perfil.php">Bem-vindo, <?php echo htmlspecialchars($_SESSION['usuario']); ?>!</a>
                    </div>
                </header>

                <div class="content-grid">
                    <div class="panel large">
                        <h4>Todos os Locais Cadastrados</h4>

                        <div class="filtros-container" style="display: flex; gap: 10px; margin-bottom: 15px; margin-top: 10px; flex-wrap: wrap; background-color: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            
                            <div style="flex: 1; min-width: 180px;">
                                <label for="filtro-titulo" style="font-weight: bold; font-size: 12px; display: block; margin-bottom: 4px;">Filtrar por Título:</label>
                                <input type="text" id="filtro-titulo" oninput="filtrarTabelaLocais()" placeholder="Digite o título..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>

                            <div style="flex: 1; min-width: 180px;">
                                <label for="filtro-categoria" style="font-weight: bold; font-size: 12px; display: block; margin-bottom: 4px;">Filtrar Categoria:</label>
                                <select id="filtro-categoria" onchange="filtrarTabelaLocais()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: white;">
                                    <option value="">-- Todas Categorias --</option>
                                    <?php foreach ($lista_categorias_filtro as $categoria_item): ?>
                                        <option value="<?php echo htmlspecialchars($categoria_item); ?>"><?php echo htmlspecialchars($categoria_item); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="flex: 1; min-width: 180px;">
                                <label for="filtro-tag" style="font-weight: bold; font-size: 12px; display: block; margin-bottom: 4px;">Filtrar por Tag:</label>
                                <input type="text" id="filtro-tag" oninput="filtrarTabelaLocais()" placeholder="Digite uma tag..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>

                        </div>
                        <div style="max-height: 600px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Título</th>
                                        <th>Tags</th> 
                                        <th>Categoria</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-locais-corpo"> 
                                    <?php if ($resultado_locais && $resultado_locais->num_rows > 0): ?>
                                        <?php while($local = $resultado_locais->fetch_assoc()): ?>
                                        <tr id="local-row-<?php echo $local['id_local']; ?>">
                                            <td>
                                                <img src="<?php echo htmlspecialchars($local['imagem_url']); ?>" alt="<?php echo htmlspecialchars($local['titulo']); ?>" class="table-image-thumbnail">
                                            </td>
                                            <td><?php echo htmlspecialchars($local['titulo']); ?></td>
                                            
                                            <td>
                                                <?php 
                                                    if (!empty($local['tags_locais'])): 
                                                        $tags_array = explode(', ', $local['tags_locais']);
                                                        ?>
                                                        <div class="tag-container"> <?php 
                                                                // Loop por cada tag para aplicar o estilo "badge"
                                                                foreach ($tags_array as $tag): 
                                                                    echo '<span class="tag-item">' . htmlspecialchars($tag) . '</span>';
                                                                endforeach;
                                                            ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color:#aaa;">Nenhuma Tag</span>
                                                    <?php endif; ?>
                                            </td>
                                            
                                            <td><?php echo htmlspecialchars($local['nome_categoria']); ?></td>
                                            <td class="actions-cell">
                                                <a href="../php/editar_local.php?id=<?php echo $local['id_local']; ?>" class="action-btn edit">Editar</a>
                                                <button class="action-btn delete" data-id="<?php echo $local['id_local']; ?>">Deletar</button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr id="nenhum-local-row">
                                            <td colspan="5">Nenhum local encontrado.</td>
                                        </tr>
                                    <?php endif; ?>

                                    <tr id="nenhum-resultado-local-row" style="display: none;">
                                        <td colspan="5">Nenhum resultado encontrado para este filtro.</td>
                                    </tr>

                                </tbody>
                            </table>
                        </div> </div>
                </div>
            </main>
            
            <script>
<<<<<<< HEAD
            const temLocaisOriginais = <?php echo ($resultado_locais && $resultado_locais->num_rows > 0) ? 'true' : 'false'; ?>;

            function filtrarTabelaLocais() {
                const filtroTitulo = document.getElementById('filtro-titulo').value.toLowerCase();
                const filtroCat = document.getElementById('filtro-categoria').value;
                const filtroTag = document.getElementById('filtro-tag').value.toLowerCase();
                const tabelaCorpo = document.getElementById('tabela-locais-corpo');
                const linhas = tabelaCorpo.getElementsByTagName('tr');
                const linhaNenhumResultado = document.getElementById('nenhum-resultado-local-row');
                const linhaNenhumLocal = document.getElementById('nenhum-local-row');
                let linhasVisiveis = 0;

                for (let i = 0; i < linhas.length; i++) {
                    const linha = linhas[i];
                    if (linha.id === 'nenhum-local-row' || linha.id === 'nenhum-resultado-local-row') {
                        continue; 
                    }
                    const celulas = linha.getElementsByTagName('td');
                    const valorTitulo = celulas[1].textContent.toLowerCase(); 
                    const valorTags = celulas[2].textContent.toLowerCase(); 
                    const valorCat = celulas[3].textContent;
                    const matchTitulo = (filtroTitulo === "" || valorTitulo.includes(filtroTitulo));
                    const matchCat = (filtroCat === "" || valorCat === filtroCat);
                    const matchTag = (filtroTag === "" || (valorTags.includes(filtroTag) && valorTags !== "nenhuma tag")); 

                    if (matchTitulo && matchCat && matchTag) {
                        linha.style.display = "";
                        linhasVisiveis++;
                    } else {
                        linha.style.display = "none";
                    }
                }

                if (linhaNenhumResultado) {
                    if (linhasVisiveis === 0 && temLocaisOriginais) {
                        linhaNenhumResultado.style.display = "";
                    } else {
                        linhaNenhumResultado.style.display = "none";
                    }
                }
=======
            // Esta constante nos diz se a tabela tinha locais para começar (antes de qualquer filtro)
            const temLocaisOriginais = <?php echo ($resultado_locais && $resultado_locais->num_rows > 0) ? 'true' : 'false'; ?>;

            function filtrarTabelaLocais() {
                // 1. Obter os valores atuais dos filtros
                const filtroTitulo = document.getElementById('filtro-titulo').value.toLowerCase();
                const filtroCat = document.getElementById('filtro-categoria').value;
                // .toLowerCase() torna a busca de texto insensível a maiúsculas/minúsculas
                const filtroTag = document.getElementById('filtro-tag').value.toLowerCase();

                // 2. Obter a tabela e todas as suas linhas (tr)
                const tabelaCorpo = document.getElementById('tabela-locais-corpo');
                const linhas = tabelaCorpo.getElementsByTagName('tr');
                
                // 3. Obter as linhas especiais (para mostrar/esconder depois)
                const linhaNenhumResultado = document.getElementById('nenhum-resultado-local-row');
                const linhaNenhumLocal = document.getElementById('nenhum-local-row');
                
                let linhasVisiveis = 0;

                // 4. Loop por todas as linhas da tabela
                for (let i = 0; i < linhas.length; i++) {
                    const linha = linhas[i];

                    // Ignorar as linhas especiais de "nenhum local" ou "nenhum resultado"
                    if (linha.id === 'nenhum-local-row' || linha.id === 'nenhum-resultado-local-row') {
                        continue; 
                    }

                    // 5. Obter o texto de cada célula da linha atual
                    const celulas = linha.getElementsByTagName('td');
                    // Coluna 1: Título
                    const valorTitulo = celulas[1].textContent.toLowerCase(); 
                    // Coluna 2: Tags
                    const valorTags = celulas[2].textContent.toLowerCase(); 
                    // Coluna 3: Categoria
                    const valorCat = celulas[3].textContent;

                    // 6. Lógica de filtro: verificar se a linha corresponde a TODOS os filtros
                    // (Um filtro vazio "" significa "qualquer um")
                    const matchTitulo = (filtroTitulo === "" || valorTitulo.includes(filtroTitulo));
                    const matchCat = (filtroCat === "" || valorCat === filtroCat);
                    // .includes() verifica se o texto contém
                    const matchTag = (filtroTag === "" || (valorTags.includes(filtroTag) && valorTags !== "nenhuma tag")); 

                    // 7. Mostrar ou esconder a linha
                    if (matchTitulo && matchCat && matchTag) {
                        linha.style.display = ""; // Mostra a linha
                        linhasVisiveis++;
                    } else {
                        linha.style.display = "none"; // Esconde a linha
                    }
                }

                // 8. Lógica para mostrar a mensagem "Nenhum resultado"
                if (linhaNenhumResultado) {
                    if (linhasVisiveis === 0 && temLocaisOriginais) {
                        linhaNenhumResultado.style.display = ""; // Mostra "Nenhum resultado"
                    } else {
                        linhaNenhumResultado.style.display = "none"; // Esconde "Nenhum resultado"
                    }
                }
                
                // 9. Se a tabela estava vazia desde o início, garante que a msg original apareça
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                if(linhaNenhumLocal && !temLocaisOriginais) {
                    linhaNenhumLocal.style.display = "";
                }
            }

<<<<<<< HEAD
=======

            // Script de deleção (já existente, agora dentro do DOMContentLoaded)
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.action-btn.delete').forEach(button => {
                    button.addEventListener('click', function() {
                        const localId = this.dataset.id;
                        if (confirm('Tem certeza que deseja deletar este local? Todos os favoritos e agendamentos associados também serão removidos.')) {
                            fetch('../php/deletar_local.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ local_id: localId })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    document.getElementById('local-row-' + localId).remove();
<<<<<<< HEAD
=======
                                    // Re-avalia a filtragem/contagem após deletar
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                                    filtrarTabelaLocais(); 
                                } else {
                                    alert('Erro: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Erro:', error);
                                alert('Ocorreu um erro de comunicação.');
                            });
                        }
                    });
                });
            });
            </script>
            <?php elseif ($view == 'agendamentos'): ?>
            <main class="main-content">
                <header class="main-header">
                    <h3>Todos os Agendamentos de Dates</h3>
                </header>

                <div class="panel full-width">
                    <h4>Agendamentos Registrados no Sistema</h4>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Data do Evento</th>
                                <th>Título do Evento</th>
                                <th>Usuário</th>
                                <th>Local Agendado</th>
                                <th>Data do Agendamento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultado_agendamentos && $resultado_agendamentos->num_rows > 0): ?>
                                <?php while($agendamento = $resultado_agendamentos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($agendamento['data_agendada'])); ?></td>
                                        <td><?php echo htmlspecialchars($agendamento['titulo_evento']); ?></td>
                                        <td><?php echo htmlspecialchars($agendamento['nome_usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($agendamento['nome_local']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($agendamento['criado_em'])); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5">Nenhum agendamento encontrado no sistema.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </main>

        <?php elseif ($view == 'tags'): ?>
            <main class="main-content">
                <header class="main-header">
                    <h3><i class="fas fa-tags"></i> Gerenciar Tags</h3>
                </header>

                <?php if (!empty($mensagem_tags)): ?>
                    <div class="message <?php echo $tipo_mensagem_tags; ?>" style="margin-bottom: 20px;">
                        <?php echo $mensagem_tags; ?>
                    </div>
                <?php endif; ?>

                <div class="content-grid" style="display: block;">
                    
                    <div class="panel">
                        <h4>Adicionar Nova Tag</h4>

                        <form action="../php/admin.php?view=tags" method="POST" class="detail-section" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            
                            <div>
                                <div class="detail-item">
                                    <label for="categoria" style="font-weight: bold;">Categoria:</label>
                                    <select id="categoria" name="categoria" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: white;">
                                        <option value="" disabled selected>-- Selecione uma Categoria --</option>
                                        <?php foreach ($lista_categorias as $categoria_item): ?>
                                            <option value="<?php echo htmlspecialchars($categoria_item); ?>"><?php echo htmlspecialchars($categoria_item); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="detail-item">
                                    <label for="subcategoria" style="font-weight: bold;">Subcategoria:</label>
                                    <select id="subcategoria" name="subcategoria" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: white;">
                                        <option value="" disabled selected>-- Selecione uma Subcategoria --</option>
                                        <?php foreach ($lista_subcategorias as $subcategoria_item): ?>
                                            <option value="<?php echo htmlspecialchars($subcategoria_item); ?>"><?php echo htmlspecialchars($subcategoria_item); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <div class="detail-item">
                                    <label for="nome_tag" style="font-weight: bold;">Nome da Tag:</label>
                                    <input type="text" id="nome_tag" name="nome_tag" required placeholder="Ex: Romântico" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>

                                <div class="detail-actions actions" style="margin-top: 15px;">
                                    <button type="submit" class="btn-approve"><i class="fas fa-plus"></i> Adicionar Tag</button>
                                </div>
                            </div>

                            <input type="hidden" name="acao" value="adicionar_tag">
                        </form>
                    </div>

                    <div class="panel" style="margin-top: 20px;">
                        <h4>Tags Existentes</h4>

                        <div class="filtros-container" style="display: flex; gap: 10px; margin-bottom: 15px; margin-top: 10px; flex-wrap: wrap; background-color: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            
                            <div style="flex: 1; min-width: 180px;">
                                <label for="filtro-categoria" style="font-weight: bold; font-size: 12px; display: block; margin-bottom: 4px;">Filtrar Categoria:</label>
                                <select id="filtro-categoria" onchange="filtrarTabelaTags()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: white;">
                                    <option value="">-- Todas Categorias --</option>
                                    <?php foreach ($lista_categorias as $categoria_item): ?>
                                        <option value="<?php echo htmlspecialchars($categoria_item); ?>"><?php echo htmlspecialchars($categoria_item); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="flex: 1; min-width: 180px;">
                                <label for="filtro-subcategoria" style="font-weight: bold; font-size: 12px; display: block; margin-bottom: 4px;">Filtrar Subcategoria:</label>
                                <select id="filtro-subcategoria" onchange="filtrarTabelaTags()" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: white;">
                                    <option value="">-- Todas Subcategorias --</option>
                                    <?php foreach ($lista_subcategorias as $subcategoria_item): ?>
                                        <option value="<?php echo htmlspecialchars($subcategoria_item); ?>"><?php echo htmlspecialchars($subcategoria_item); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="flex: 1; min-width: 180px;">
                                <label for="filtro-tag" style="font-weight: bold; font-size: 12px; display: block; margin-bottom: 4px;">Buscar por Tag:</label>
                                <input type="text" id="filtro-tag" oninput="filtrarTabelaTags()" placeholder="Digite o nome da tag..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>

                        </div>
                        <div style="max-height: 450px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                        
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Subcategoria</th>
                                        <th>Tag</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-tags-corpo">
                                    <?php if ($resultado_tags && $resultado_tags->num_rows > 0): ?>
                                        <?php while ($tag = $resultado_tags->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tag['categoria']); ?></td>
                                            <td><?php echo htmlspecialchars($tag['subcategoria']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($tag['nome_tag']); ?></strong></td>
                                            <td class="actions-cell">
                                                <a href="../php/admin.php?view=tags&acao=excluir_tag&id_tag=<?php echo $tag['id_tag']; ?>" 
                                                   class="action-btn delete"
                                                   onclick="return confirm('Tem certeza que deseja excluir a tag \'<?php echo htmlspecialchars($tag['nome_tag']); ?>\'?');">
                                                   Deletar
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr id="nenhuma-tag-row">
                                            <td colspan="4">Nenhuma tag cadastrada ainda.</td>
                                        </tr>
                                    <?php endif; ?>

                                    <tr id="nenhum-resultado-row" style="display: none;">
                                        <td colspan="4">Nenhum resultado encontrado para este filtro.</td>
                                    </tr>
                                </tbody>
                            </table>

                        </div> </div>
                </div>
            </main>
        
            <script>
<<<<<<< HEAD
                const temTagsOriginais = <?php echo ($resultado_tags && $resultado_tags->num_rows > 0) ? 'true' : 'false'; ?>;

                function filtrarTabelaTags() {
                    const filtroCat = document.getElementById('filtro-categoria').value;
                    const filtroSub = document.getElementById('filtro-subcategoria').value;
                    const filtroTag = document.getElementById('filtro-tag').value.toLowerCase();
                    const tabelaCorpo = document.getElementById('tabela-tags-corpo');
                    const linhas = tabelaCorpo.getElementsByTagName('tr');
                    const linhaNenhumResultado = document.getElementById('nenhum-resultado-row');
                    const linhaNenhumaTag = document.getElementById('nenhuma-tag-row');
                    let linhasVisiveis = 0;

                    for (let i = 0; i < linhas.length; i++) {
                        const linha = linhas[i];
                        if (linha.id === 'nenhuma-tag-row' || linha.id === 'nenhum-resultado-row') {
                            continue; 
                        }
=======
                // Esta constante nos diz se a tabela tinha tags para começar (antes de qualquer filtro)
                const temTagsOriginais = <?php echo ($resultado_tags && $resultado_tags->num_rows > 0) ? 'true' : 'false'; ?>;

                function filtrarTabelaTags() {
                    // 1. Obter os valores atuais dos filtros
                    const filtroCat = document.getElementById('filtro-categoria').value;
                    const filtroSub = document.getElementById('filtro-subcategoria').value;
                    // .toLowerCase() torna a busca de texto insensível a maiúsculas/minúsculas
                    const filtroTag = document.getElementById('filtro-tag').value.toLowerCase();

                    // 2. Obter a tabela e todas as suas linhas (tr)
                    const tabelaCorpo = document.getElementById('tabela-tags-corpo');
                    const linhas = tabelaCorpo.getElementsByTagName('tr');
                    
                    // 3. Obter as linhas especiais (para mostrar/esconder depois)
                    const linhaNenhumResultado = document.getElementById('nenhum-resultado-row');
                    const linhaNenhumaTag = document.getElementById('nenhuma-tag-row');
                    
                    let linhasVisiveis = 0;

                    // 4. Loop por todas as linhas da tabela
                    for (let i = 0; i < linhas.length; i++) {
                        const linha = linhas[i];

                        // Ignorar as linhas especiais de "nenhuma tag" ou "nenhum resultado"
                        if (linha.id === 'nenhuma-tag-row' || linha.id === 'nenhum-resultado-row') {
                            continue; 
                        }

                        // 5. Obter o texto de cada célula da linha atual
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                        const celulas = linha.getElementsByTagName('td');
                        const valorCat = celulas[0].textContent;
                        const valorSub = celulas[1].textContent;
                        const valorTag = celulas[2].textContent.toLowerCase();
<<<<<<< HEAD
                        const matchCat = (filtroCat === "" || valorCat === filtroCat);
                        const matchSub = (filtroSub === "" || valorSub === filtroSub);
                        const matchTag = (filtroTag === "" || valorTag.includes(filtroTag));

                        if (matchCat && matchSub && matchTag) {
                            linha.style.display = "";
                            linhasVisiveis++;
                        } else {
                            linha.style.display = "none";
                        }
                    }

                    if (linhaNenhumResultado) {
                        if (linhasVisiveis === 0 && temTagsOriginais) {
                            linhaNenhumResultado.style.display = "";
                        } else {
                            linhaNenhumResultado.style.display = "none";
                        }
                    }
=======

                        // 6. Lógica de filtro: verificar se a linha corresponde a TODOS os filtros
                        // (Um filtro vazio "" significa "qualquer um")
                        const matchCat = (filtroCat === "" || valorCat === filtroCat);
                        const matchSub = (filtroSub === "" || valorSub === filtroSub);
                        const matchTag = (filtroTag === "" || valorTag.includes(filtroTag)); // .includes() verifica se o texto contém

                        // 7. Mostrar ou esconder a linha
                        if (matchCat && matchSub && matchTag) {
                            linha.style.display = ""; // Mostra a linha
                            linhasVisiveis++;
                        } else {
                            linha.style.display = "none"; // Esconde a linha
                        }
                    }

                    // 8. Lógica para mostrar a mensagem "Nenhum resultado"
                    if (linhaNenhumResultado) {
                        if (linhasVisiveis === 0 && temTagsOriginais) {
                            linhaNenhumResultado.style.display = ""; // Mostra "Nenhum resultado"
                        } else {
                            linhaNenhumResultado.style.display = "none"; // Esconde "Nenhum resultado"
                        }
                    }
                    
                    // Se a tabela estava vazia desde o início, garante que a msg original apareça
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
                    if(linhaNenhumaTag && !temTagsOriginais) {
                        linhaNenhumaTag.style.display = "";
                    }
                }
            </script>
        
       <?php elseif ($view == 'categorias'): ?>
            <main class="main-content">
                <header class="main-header">
                    <h3><i class="fas fa-list-alt"></i> Gerenciar Categorias</h3>
                </header>

                <?php if (!empty($mensagem_cat)): ?>
                    <div class="message <?php echo $tipo_mensagem_cat; ?>" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($mensagem_cat); ?>
                    </div>
                <?php endif; ?>

                <div class="content-grid" style="display: block;">
                    
                    <div class="panel">
                        <h4>Adicionar Nova Categoria</h4>
                        <form action="../php/admin.php?view=categorias" method="POST" enctype="multipart/form-data" class="detail-section" style="padding: 0; border: none; background: none;">
                            
                            <div class="detail-item">
                                <label for="nome_categoria" style="font-weight: bold;">Nome da Categoria:</label>
                                <input type="text" id="nome_categoria" name="nome_categoria" required placeholder="Ex: Vida Noturna" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div class="detail-item" style="margin-top: 15px;">
                                <label for="link_pagina" style="font-weight: bold;">Link da Página:</label>
                                <input type="text" id="link_pagina" name="link_pagina" required placeholder="Ex: ../php/aventuras.php" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="detail-item" style="margin-top: 15px;">
                                <label for="icone_fa" style="font-weight: bold;">Código do Ícone (FontAwesome):</label>
                                <input type="text" id="icone_fa" name="icone_fa" required placeholder="Ex: fas fa-person-hiking" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="detail-item" style="margin-top: 15px;">
                                <label for="imagem_categoria_add" style="font-weight: bold;">Imagem da Categoria:</label>
                                <input type="file" id="imagem_categoria_add" name="imagem_categoria" required accept="image/png, image/jpeg, image/gif, image/webp" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <small style="display: block; margin-top: 5px; color: #555;">(Limite: 5MB. Tipos: JPG, PNG, GIF, WebP)</small>
                            </div>
                            <div class="detail-actions actions" style="margin-top: 15px;">
                                <button type="submit" class="btn-approve"><i class="fas fa-plus"></i> Adicionar Categoria</button>
                            </div>
                            <input type="hidden" name="acao" value="adicionar_categoria">
                        </form>
                    </div>
                    
                    <?php if (count($categorias_para_dropdown) > 0): ?>
                    <div class="panel" style="margin-top: 20px;">
                        <h4>Editar Categoria Existente</h4>
                        
                        <form action="../php/admin.php?view=categorias" method="POST" enctype="multipart/form-data" class="detail-section" style="padding: 0; border: none; background: none;">
                            
                            <div class="detail-item">
                                <label for="id_categoria" style="font-weight: bold;">Selecione a Categoria para Editar:</label>
                                <select id="id_categoria" name="id_categoria" required style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background-color: white;">
                                    <option value="" disabled selected>-- Selecione --</option>
                                    <?php foreach ($categorias_para_dropdown as $cat_dd): ?>
                                        <option value="<?php echo $cat_dd['id_categoria']; ?>">
                                            <?php echo htmlspecialchars($cat_dd['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="detail-item" style="margin-top: 15px;">
                                <label for="link_pagina_update" style="font-weight: bold;">Novo Link da Página:</label>
                                <input type="text" id="link_pagina_update" name="link_pagina" placeholder="Preencha para atualizar" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="detail-item" style="margin-top: 15px;">
                                <label for="icone_fa_update" style="font-weight: bold;">Novo Código do Ícone:</label>
                                <input type="text" id="icone_fa_update" name="icone_fa" placeholder="Preencha para atualizar" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div class="detail-item" style="margin-top: 15px;">
                                <label for="imagem_categoria_update" style="font-weight: bold;">Substituir Imagem (Opcional):</label>
                                <input type="file" id="imagem_categoria_update" name="imagem_categoria" accept="image/png, image/jpeg, image/gif, image/webp" style="width: 100%; max-width: 400px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <small style="display: block; margin-top: 5px; color: #555;">(Deixe em branco para não alterar a imagem atual)</small>
                            </div>

                            <div class="detail-actions actions" style="margin-top: 15px;">
                                <button type="submit" class="btn-approve" style="background-color: #ffb400; border-color: #ffb400;"><i class="fas fa-upload"></i> Atualizar Categoria</button>
                            </div>
                            <input type="hidden" name="acao" value="atualizar_categoria_antiga">
                        </form>
                    </div>
                    <?php endif; ?>
                    <div class="panel" style="margin-top: 20px;">
                        <h4>Categorias Existentes</h4>
                        <div style="max-height: 450px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">
                        
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Imagem</th>
                                        <th>Nome</th>
                                        <th>Link da Página</th>
                                        <th>Ícone</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($resultado_categorias && $resultado_categorias->num_rows > 0): ?>
                                        <?php while ($cat = $resultado_categorias->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php if (!empty($cat['imagem_url'])): ?>
                                                    <img src="<?php echo htmlspecialchars($cat['imagem_url']); ?>" alt="<?php echo htmlspecialchars($cat['nome']); ?>" class="table-image-thumbnail">
                                                <?php else: ?>
                                                    <span style="color:#aaa; font-size: 12px;">Sem Imagem</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong><?php echo htmlspecialchars($cat['nome']); ?></strong></td>
                                            
                                            <td style="font-size: 12px; color: #555;">
                                                <?php echo htmlspecialchars($cat['link_pagina']); ?>
                                            </td>
                                            <td style="font-size: 12px; color: #555;">
                                                <i class="<?php echo htmlspecialchars($cat['icone_fa']); ?>"></i> 
                                                (<?php echo htmlspecialchars($cat['icone_fa']); ?>)
                                            </td>
                                            
                                            <td class="actions-cell">
                                                <a href="../php/admin.php?view=categorias&acao=excluir_categoria&id=<?php echo $cat['id_categoria']; ?>" 
                                                   class="action-btn delete"
                                                   onclick="return confirm('Tem certeza que deseja excluir a categoria \'<?php echo htmlspecialchars($cat['nome']); ?>\'? Isso também excluirá a imagem permanentemente.');">
                                                   Deletar
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5">Nenhuma categoria cadastrada ainda.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>

                        </div> 
                    </div>
                </div>
            </main>
              
        <?php else: ?>
            <main class="main-content">
                <header class="main-header"><h3>Página não encontrada</h3></header>
                <div class="panel"><p>A visualização solicitada não existe ou a solicitação não foi encontrada.</p></div>
            </main>
        <?php endif; ?>
        
    </div>

    <?php if ($view == 'dashboard'): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctxDonut = document.getElementById('locaisPorCategoriaChart').getContext('2d');
        new Chart(ctxDonut, { type: 'doughnut', data: { labels: <?php echo json_encode($labels_grafico_donut);?>, datasets: [{ label: 'Nº de Locais', data: <?php echo json_encode($dados_grafico_donut);?>, backgroundColor: ['#E94D73','#5E313D','#800020','#D83C61','#F8B195','#F67280','#C06C84','#6C5B7B'], borderColor: '#fff', borderWidth: 2 }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } });
        const ctxLine = document.getElementById('novosUsuariosChart').getContext('2d');
        new Chart(ctxLine, { type: 'line', data: { labels: <?php echo json_encode($labels_grafico_linha); ?>, datasets: [{ label: 'Novos Usuários', data: <?php echo json_encode($dados_grafico_linha); ?>, backgroundColor: 'rgba(233, 77, 115, 0.2)', borderColor: '#E94D73', borderWidth: 3, fill: true, tension: 0.4 }] }, options: { responsive: true, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } } });
    });
    </script>
    <?php endif; ?>
    
    <?php $conn->close(); ?>
</body>
</html>