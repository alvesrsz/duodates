<?php
session_start();
include '../conexao.php';

// Verificação de segurança
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$local_id = $_GET['id'] ?? null;
$local = null;
$categorias = [];
$all_tags = []; // Array para todas as tags disponíveis
$selected_tag_ids = []; // Array para os IDs das tags selecionadas para este local
$mensagem = '';
$success_message = $_SESSION['success_message'] ?? null; // Pega mensagem de sucesso da sessão
$error_message = $_SESSION['error_message'] ?? null;   // Pega mensagem de erro da sessão
unset($_SESSION['success_message'], $_SESSION['error_message']); // Limpa as mensagens

if (!$local_id) {
    // Redireciona para a lista de locais se não houver ID
    header('Location: ../php/admin.php?view=locais');
    exit();
}

// --- LÓGICA PARA ATUALIZAR O LOCAL E AS TAGS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->begin_transaction(); // Inicia a transação
    try {
        // Coleta dos dados do formulário do local
        $id_local_post = $_POST['id_local'];
        $titulo = $_POST['titulo'];
        $descricao = $_POST['descricao'];
        $id_categoria = $_POST['id_categoria'];
        $local_info = $_POST['local_info'];
        $horario_info = $_POST['horario_info'];
        $imagem_url = $_POST['imagem_url'];
        $link_botao = $_POST['link_botao'];
        $texto_botao = $_POST['texto_botao'];

        // 1. Atualiza os dados principais do local
        $sql_update = "UPDATE locais SET 
                        titulo = ?, descricao = ?, id_categoria = ?, local_info = ?, 
                        horario_info = ?, imagem_url = ?, link_botao = ?, texto_botao = ?
                       WHERE id_local = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssisssssi", $titulo, $descricao, $id_categoria, $local_info, $horario_info, $imagem_url, $link_botao, $texto_botao, $id_local_post);
        if (!$stmt->execute()) {
            throw new Exception("Erro ao atualizar dados do local: " . $stmt->error);
        }
        $stmt->close();

        // 2. Processa as TAGS (lógica inalterada, já funciona com array)
        $submitted_tag_ids = $_POST['tags'] ?? []; // Pega os IDs das tags enviadas (dos checkboxes)

        // 2a. Apaga todas as associações de tags existentes para este local
        $sql_delete_tags = "DELETE FROM local_tags WHERE id_local_fk = ?";
        $stmt_delete = $conn->prepare($sql_delete_tags);
        $stmt_delete->bind_param("i", $id_local_post);
        if (!$stmt_delete->execute()) {
             throw new Exception("Erro ao limpar tags antigas: " . $stmt_delete->error);
        }
        $stmt_delete->close();

        // 2b. Insere as novas associações selecionadas
        if (!empty($submitted_tag_ids)) {
            $sql_insert_tag = "INSERT INTO local_tags (id_local_fk, id_tag_fk) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert_tag);
            foreach ($submitted_tag_ids as $tag_id) {
                $tag_id_int = filter_var($tag_id, FILTER_VALIDATE_INT);
                if ($tag_id_int) {
                    $stmt_insert->bind_param("ii", $id_local_post, $tag_id_int);
                    if (!$stmt_insert->execute()) {
                         throw new Exception("Erro ao inserir tag ID $tag_id_int: " . $stmt_insert->error);
                    }
                }
            }
            $stmt_insert->close();
        }

        $conn->commit();
        $_SESSION['success_message'] = "Local atualizado com sucesso!";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Erro ao atualizar: " . $e->getMessage();
    }

    header('Location: editar_local.php?id=' . $id_local_post);
    exit();
}

// --- LÓGICA PARA BUSCAR DADOS DO LOCAL (PARA PREENCHER O FORMULÁRIO) ---
// (Código de busca de local, categorias e tags permanece o mesmo)
$sql_local = "SELECT * FROM locais WHERE id_local = ?";
$stmt_local = $conn->prepare($sql_local);
$stmt_local->bind_param("i", $local_id);
$stmt_local->execute();
$result_local = $stmt_local->get_result();
if ($result_local->num_rows > 0) { $local = $result_local->fetch_assoc(); } else { header('Location: admin.php?view=locais'); exit(); }
$stmt_local->close();

$result_categorias = $conn->query("SELECT * FROM categorias ORDER BY nome");
if ($result_categorias) { $categorias = $result_categorias->fetch_all(MYSQLI_ASSOC); }

$result_all_tags = $conn->query("SELECT id_tag, nome_tag FROM tags ORDER BY nome_tag ASC");
if($result_all_tags) { $all_tags = $result_all_tags->fetch_all(MYSQLI_ASSOC); }

$sql_selected_tags = "SELECT id_tag_fk FROM local_tags WHERE id_local_fk = ?";
$stmt_selected_tags = $conn->prepare($sql_selected_tags);
$stmt_selected_tags->bind_param("i", $local_id);
$stmt_selected_tags->execute();
$result_selected_tags = $stmt_selected_tags->get_result();
if ($result_selected_tags) { $selected_tag_ids = array_column($result_selected_tags->fetch_all(MYSQLI_ASSOC), 'id_tag_fk'); }
$stmt_selected_tags->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Local - Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
             <div class="sidebar-header"><h2>DuoDates Admin</h2></div>
             <nav class="sidebar-nav">
                <a href="../php/admin.php?view=dashboard" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../php/admin.php?view=pendentes" class="nav-item"><i class="fas fa-hourglass-half"></i> Locais Pendentes</a>
                <a href="../php/admin.php?view=usuarios" class="nav-item"><i class="fas fa-users"></i> Gerenciar Usuários</a>
                <a href="../php/admin.php?view=locais" class="nav-item active"><i class="fas fa-map-marker-alt"></i> Gerenciar Locais</a>
                <a href="../php/admin.php?view=tags" class="nav-item"><i class="fas fa-tags"></i> Gerenciar Tags</a>
                <a href="../php/admin.php?view=agendamentos" class="nav-item"><i class="fas fa-calendar-alt"></i> Ver Agendamentos</a>
                <a href="../php/adicionar-lugar.php" class="nav-item"><i class="fas fa-plus-circle"></i> Adicionar Local</a>
                <a href="../index.php" class="nav-item"><i class="fas fa-globe"></i> Ver Site</a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="main-header">
                <h3>Editando: <?php echo htmlspecialchars($local['titulo']); ?></h3>
            </header>

            <div class="panel full-width">
                <?php if($success_message): ?><div class="message success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div><?php endif; ?>
                <?php if($error_message): ?><div class="message error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?></div><?php endif; ?>
                <?php if($mensagem): ?><div class="message error"><?php echo $mensagem; ?></div><?php endif; ?>

                <form class="edit-form" method="POST" action="../php/editar_local.php?id=<?php echo $local['id_local']; ?>">
                    <input type="hidden" name="id_local" value="<?php echo $local['id_local']; ?>">

                    <div class="form-group">
                        <label for="titulo">Título do Local</label>
                        <input type="text" id="titulo" name="titulo" value="<?php echo htmlspecialchars($local['titulo']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <textarea id="descricao" name="descricao" rows="5" required><?php echo htmlspecialchars($local['descricao']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="id_categoria">Categoria</label>
                        <select id="id_categoria" name="id_categoria" required>
                            <?php foreach($categorias as $categoria): ?>
                                <option value="<?php echo $categoria['id_categoria']; ?>" <?php echo ($local['id_categoria'] == $categoria['id_categoria']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoria['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tags (selecione uma ou mais)</label>
                        <div class="checkbox-group tag-checkboxes"> <?php foreach($all_tags as $tag): ?>
                                <div class="checkbox-item"> <input type="checkbox" 
                                           id="tag_<?php echo $tag['id_tag']; ?>" 
                                           name="tags[]" 
                                           value="<?php echo $tag['id_tag']; ?>"
                                           <?php echo in_array($tag['id_tag'], $selected_tag_ids) ? 'checked' : ''; ?>
                                           >
                                    <label for="tag_<?php echo $tag['id_tag']; ?>">
                                        <?php echo htmlspecialchars($tag['nome_tag']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small>Marque todas as tags que se aplicam a este local.</small>
                    </div>
                    <div class="form-group">
                        <label for="local_info">Informação de Localização</label>
                        <input type="text" id="local_info" name="local_info" value="<?php echo htmlspecialchars($local['local_info']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="horario_info">Informação de Horário</label>
                        <input type="text" id="horario_info" name="horario_info" value="<?php echo htmlspecialchars($local['horario_info']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="imagem_url">URL da Imagem</label>
                        <input type="text" id="imagem_url" name="imagem_url" value="<?php echo htmlspecialchars($local['imagem_url']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="link_botao">Link do Botão</label>
                        <input type="url" id="link_botao" name="link_botao" value="<?php echo htmlspecialchars($local['link_botao']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="texto_botao">Texto do Botão</label>
                        <input type="text" id="texto_botao" name="texto_botao" value="<?php echo htmlspecialchars($local['texto_botao']); ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-approve"><i class="fas fa-save"></i> Salvar Alterações</button>
                        <a href="../php/admin.php?view=locais" class="btn-reject" style="text-decoration:none;">Cancelar</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>