<?php
session_start();
include '../conexao.php';

// --- 1. VERIFICAÇÃO DE SEGURANÇA ---
// Garante que apenas usuários 'empresarial' ou 'admin' acessem
if (!isset($_SESSION['usuario']) || ($_SESSION['tipo_conta'] !== 'empresarial' && $_SESSION['tipo_conta'] !== 'admin')) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['id'];
$local_id = null;

// --- 2. BUSCA AUTOMÁTICA DO LOCAL DA EMPRESA ---
// Pega o ID do local associado a este usuário logado
$stmt_auth = $conn->prepare("SELECT id_local_associado FROM usuarios WHERE id = ?");
$stmt_auth->bind_param("i", $user_id);
$stmt_auth->execute();
$res_auth = $stmt_auth->get_result();
if ($row_auth = $res_auth->fetch_assoc()) {
    $local_id = $row_auth['id_local_associado'];
}
$stmt_auth->close();

// Se não tiver local vinculado, avisa e volta
if (!$local_id) {
    echo "<script>alert('Sua conta não possui um local vinculado.'); window.location.href='dashboard_empresa.php';</script>";
    exit();
}

// Variáveis de mensagem
$msg_sucesso = "";
$msg_erro = "";
$selected_tag_ids = []; 

// --- 3. SALVAR ALTERAÇÕES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction();
    try {
        // Recebe os dados do formulário
        $descricao    = $_POST['descricao'];
        $local_info   = $_POST['local_info'];   // Endereço
        $horario_info = $_POST['horario_info']; // Horário
        $imagem_url   = $_POST['imagem_url'];
        $link_botao   = $_POST['link_botao'];
        $texto_botao  = $_POST['texto_botao'];

        // Atualiza a tabela locais
        $sql_update = "UPDATE locais SET 
                        descricao = ?, 
                        local_info = ?, 
                        horario_info = ?, 
                        imagem_url = ?, 
                        link_botao = ?, 
                        texto_botao = ?
                       WHERE id_local = ?";
        
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssssssi", $descricao, $local_info, $horario_info, $imagem_url, $link_botao, $texto_botao, $local_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Erro ao atualizar os dados principais.");
        }
        $stmt->close();

        // Atualiza as Tags (Características)
        // 1. Limpa as antigas
        $stmt_del = $conn->prepare("DELETE FROM local_tags WHERE id_local_fk = ?");
        $stmt_del->bind_param("i", $local_id);
        $stmt_del->execute();
        $stmt_del->close();

        // 2. Insere as novas
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            $stmt_ins = $conn->prepare("INSERT INTO local_tags (id_local_fk, id_tag_fk) VALUES (?, ?)");
            foreach ($_POST['tags'] as $tag_id) {
                $stmt_ins->bind_param("ii", $local_id, $tag_id);
                $stmt_ins->execute();
            }
            $stmt_ins->close();
        }

        $conn->commit();
        $msg_sucesso = "Informações atualizadas com sucesso!";

    } catch (Exception $e) {
        $conn->rollback();
        $msg_erro = "Erro ao salvar: " . $e->getMessage();
    }
}

// --- 4. CARREGAR DADOS PARA O FORMULÁRIO ---
// Busca dados do local
$stmt = $conn->prepare("SELECT * FROM locais WHERE id_local = ?");
$stmt->bind_param("i", $local_id);
$stmt->execute();
$local = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Busca todas as tags disponíveis (para o checkbox)
$all_tags_result = $conn->query("SELECT * FROM tags ORDER BY nome_tag ASC");
$all_tags = ($all_tags_result) ? $all_tags_result->fetch_all(MYSQLI_ASSOC) : [];

// Busca as tags que o local JÁ TEM (para marcar como checked)
$stmt_mytags = $conn->prepare("SELECT id_tag_fk FROM local_tags WHERE id_local_fk = ?");
$stmt_mytags->bind_param("i", $local_id);
$stmt_mytags->execute();
$res_mytags = $stmt_mytags->get_result();
while ($row = $res_mytags->fetch_assoc()) {
    $selected_tag_ids[] = $row['id_tag_fk'];
}
$stmt_mytags->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Editar Meu Local - DuoDates</title>
    <link rel="stylesheet" href="../css/dashboard.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* CSS Específico do Formulário */
        .form-panel { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 900px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #444; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        
        /* Checkboxes de Tags */
        .tags-container { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); 
            gap: 10px; 
            padding: 15px; 
            border: 1px solid #eee; 
            background: #fcfcfc;
            border-radius: 6px; 
            max-height: 250px; 
            overflow-y: auto; 
        }
        .tag-label { display: flex; align-items: center; font-size: 14px; cursor: pointer; color: #555; }
        .tag-label input { margin-right: 8px; width: 16px; height: 16px; accent-color: #e94d65; cursor: pointer; }
        
        /* Botões */
        .btn-save { background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: 0.3s; }
        .btn-save:hover { background-color: #218838; }
        
        /* Mensagens */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3><?php echo htmlspecialchars($local['titulo']); ?></h3>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="dashboard_empresa.php"><i class="fas fa-chart-line"></i> <span>Visão Geral</span></a></li>
                    <li><a href="gerenciar_agendamentos.php"><i class="fas fa-calendar-check"></i> <span>Agendamentos</span></a></li>
                    <li><a href="editar_meu_local.php" class="active"><i class="fas fa-edit"></i> <span>Editar Local</span></a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <h1>Editar Informações</h1>
            </header>

            <div class="form-panel">
                <?php if ($msg_sucesso): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $msg_sucesso; ?></div>
                <?php endif; ?>
                <?php if ($msg_erro): ?>
                    <div class="alert alert-error"><i class="fas fa-times-circle"></i> <?php echo $msg_erro; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label>Nome do Estabelecimento</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($local['titulo']); ?>" disabled style="background:#eee; color:#777;">
                        <small style="color:#999;">O nome não pode ser alterado por aqui. Contate o suporte se necessário.</small>
                    </div>

                    <div class="form-group">
                        <label>URL da Imagem de Capa</label>
                        <input type="text" name="imagem_url" class="form-control" value="<?php echo htmlspecialchars($local['imagem_url']); ?>" placeholder="https://exemplo.com/foto.jpg" required>
                    </div>

                    <div class="form-group">
                        <label>Descrição</label>
                        <textarea name="descricao" class="form-control" rows="5" required><?php echo htmlspecialchars($local['descricao']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Características (Tags)</label>
                        <div class="tags-container"> 
                            <?php foreach($all_tags as $tag): ?>
                                <label class="tag-label">
                                    <input type="checkbox" name="tags[]" value="<?php echo $tag['id_tag']; ?>"
                                    <?php echo in_array($tag['id_tag'], $selected_tag_ids) ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($tag['nome_tag']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Endereço / Localização</label>
                        <input type="text" name="local_info" class="form-control" value="<?php echo htmlspecialchars($local['local_info']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Horário de Funcionamento</label>
                        <input type="text" name="horario_info" class="form-control" value="<?php echo htmlspecialchars($local['horario_info']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Texto do Botão (ex: Ver Cardápio)</label>
                        <input type="text" name="texto_botao" class="form-control" value="<?php echo htmlspecialchars($local['texto_botao']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Link do Botão (URL)</label>
                        <input type="url" name="link_botao" class="form-control" value="<?php echo htmlspecialchars($local['link_botao']); ?>">
                    </div>

                    <button type="submit" class="btn-save"><i class="fas fa-save"></i> Salvar Alterações</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>