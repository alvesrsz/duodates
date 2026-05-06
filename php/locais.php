<?php
// Inclui a conexão e inicia a sessão (ESSENCIAL PARA SEGURANÇA)
include '../conexao.php';

// VERIFICAÇÃO DE LOGIN: Garante que apenas usuários logados acessem
if (!isset($_SESSION['estabelecimento_id'])) {
    header("Location: ../php/login.php"); // Redireciona para o login se não estiver logado
    exit();
}
$id_empresa_logada = $_SESSION['estabelecimento_id'];

$mensagem = "";
$edit_mode = false;
$local_para_editar = null;

// --- LÓGICA DE AÇÕES (ADICIONAR, EDITAR, EXCLUIR) ---

// 1. AÇÃO DE ADICIONAR OU EDITAR UM LOCAL (VIA POST DO FORMULÁRIO)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    // Ação para ADICIONAR um novo local
    if ($action == 'adicionar') {
        $nome_local = $_POST['nome_local'];
        $endereco = $_POST['endereco'];
        $telefone = $_POST['telefone'];
        $horario = $_POST['horario'];

        $sql = "INSERT INTO locais (id_estabelecimento, nome_local, endereco, telefone, horario_funcionamento) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("issss", $id_empresa_logada, $nome_local, $endereco, $telefone, $horario);
        if ($stmt->execute()) {
            $mensagem = "<div class='mensagem-sucesso'>Local adicionado com sucesso!</div>";
        } else {
            $mensagem = "<div class='mensagem-erro'>Erro ao adicionar local.</div>";
        }
    }

    // Ação para SALVAR A EDIÇÃO de um local
    if ($action == 'editar') {
        $local_id = $_POST['local_id'];
        $nome_local = $_POST['nome_local'];
        $endereco = $_POST['endereco'];
        $telefone = $_POST['telefone'];
        $horario = $_POST['horario'];

        $sql = "UPDATE locais SET nome_local = ?, endereco = ?, telefone = ?, horario_funcionamento = ? WHERE id = ? AND id_estabelecimento = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ssssii", $nome_local, $endereco, $telefone, $horario, $local_id, $id_empresa_logada);
        if ($stmt->execute()) {
            $mensagem = "<div class='mensagem-sucesso'>Local atualizado com sucesso!</div>";
        } else {
            $mensagem = "<div class='mensagem-erro'>Erro ao atualizar local.</div>";
        }
    }
    
    // Ação para EXCLUIR um local
    if ($action == 'excluir') {
        $local_id = $_POST['local_id'];
        
        $sql = "DELETE FROM locais WHERE id = ? AND id_estabelecimento = ?";
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param("ii", $local_id, $id_empresa_logada);
        if ($stmt->execute()) {
            $mensagem = "<div class='mensagem-sucesso'>Local excluído com sucesso!</div>";
        } else {
            $mensagem = "<div class='mensagem-erro'>Erro ao excluir local.</div>";
        }
    }
}


// 2. PREPARAR PARA EDITAR (VIA GET DA URL)
// Verifica se a URL tem ?action=edit&id=X
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $edit_mode = true;
    $local_id_para_editar = $_GET['id'];
    
    $sql_edit = "SELECT * FROM locais WHERE id = ? AND id_estabelecimento = ?";
    $stmt_edit = $conexao->prepare($sql_edit);
    $stmt_edit->bind_param("ii", $local_id_para_editar, $id_empresa_logada);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    $local_para_editar = $result_edit->fetch_assoc();
}


// --- LÓGICA PARA LER E EXIBIR OS LOCAIS ---
$sql_select = "SELECT * FROM locais WHERE id_estabelecimento = ?";
$stmt_select = $conexao->prepare($sql_select);
$stmt_select->bind_param("i", $id_empresa_logada);
$stmt_select->execute();
$locais = $stmt_select->get_result();

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Locais - DuoDates</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css"> <style>
        /* Estilos específicos para esta página */
        main { padding: 2rem; }
        .form-gerenciar {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .form-gerenciar h2 { margin-top: 0; }
        .form-gerenciar .form-group {
            margin-bottom: 15px;
        }
        .form-gerenciar label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-gerenciar input[type="text"], .form-gerenciar textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .form-gerenciar .btn-salvar {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-cancelar-edicao {
             background-color: #6c757d;
             color: white;
             padding: 10px 20px;
             border: none;
             border-radius: 4px;
             cursor: pointer;
             text-decoration: none;
        }
        .tabela-locais {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .tabela-locais th, .tabela-locais td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .acoes-tabela a, .acoes-tabela button {
            margin-right: 10px;
            text-decoration: none;
            background: none;
            border: none;
            cursor: pointer;
        }
        .acoes-tabela .btn-editar { color: #007bff; }
        .acoes-tabela .btn-excluir { color: #dc3545; }
        .mensagem-sucesso { padding: 10px; background-color: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 15px; }
        .mensagem-erro { padding: 10px; background-color: #f8d7da; color: #721c24; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
<div class="container-dashboard">
    <nav class="menu-lateral">
        </nav>

    <main>
        <h1>Gerenciar Locais</h1>

        <?php echo $mensagem; ?>

        <div class="form-gerenciar">
            <h2><?php echo $edit_mode ? 'Editando Local' : 'Adicionar Novo Local'; ?></h2>
            <form action="../php/locais.php" method="POST">
                <input type="hidden" name="action" value="<?php echo $edit_mode ? 'editar' : 'adicionar'; ?>">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="local_id" value="<?php echo $local_para_editar['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="nome_local">Nome do Local (Ex: Matriz, Filial Shopping)</label>
                    <input type="text" id="nome_local" name="nome_local" value="<?php echo htmlspecialchars($local_para_editar['nome_local'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="endereco">Endereço Completo</label>
                    <textarea id="endereco" name="endereco" rows="3" required><?php echo htmlspecialchars($local_para_editar['endereco'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone</label>
                    <input type="text" id="telefone" name="telefone" value="<?php echo htmlspecialchars($local_para_editar['telefone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="horario">Horário de Funcionamento</label>
                    <input type="text" id="horario" name="horario" value="<?php echo htmlspecialchars($local_para_editar['horario_funcionamento'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn-salvar"><?php echo $edit_mode ? 'Salvar Alterações' : 'Adicionar Local'; ?></button>
                <?php if ($edit_mode): ?>
                    <a href="../php/locais.php" class="btn-cancelar-edicao">Cancelar Edição</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="lista-locais">
            <h2>Meus Locais Cadastrados</h2>
            <table class="tabela-locais">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Endereço</th>
                        <th>Telefone</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($local = $locais->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($local['nome_local']); ?></td>
                        <td><?php echo nl2br(htmlspecialchars($local['endereco'])); ?></td>
                        <td><?php echo htmlspecialchars($local['telefone']); ?></td>
                        <td class="acoes-tabela">
                            <a href="../php/locais.php?action=edit&id=<?php echo $local['id']; ?>" class="btn-editar" title="Editar"><i class="fas fa-edit"></i></a>
                            
                            <form action="../php/locais.php" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir este local?');">
                                <input type="hidden" name="action" value="excluir">
                                <input type="hidden" name="local_id" value="<?php echo $local['id']; ?>">
                                <button type="submit" class="btn-excluir" title="Excluir"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>