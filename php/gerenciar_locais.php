<?php
session_start();
include '../conexao.php';

// --- VERIFICAÇÃO DE SEGURANÇA COMPLETA ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['tipo_conta']) || $_SESSION['tipo_conta'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// --- 1. BUSCAR DADOS PARA OS FILTROS ---

// Buscar todas as categorias
$lista_categorias = [];
$result_cats = $conn->query("SELECT id_categoria, nome FROM categorias ORDER BY nome ASC");
if ($result_cats) {
    $lista_categorias = $result_cats->fetch_all(MYSQLI_ASSOC);
}

// Buscar todas as tags
$lista_tags = [];
// Assumindo os nomes de coluna de 'tags' (id_tag, nome_tag)
$result_tags = $conn->query("SELECT id_tag, nome_tag FROM tags ORDER BY nome_tag ASC");
if ($result_tags) {
    $lista_tags = $result_tags->fetch_all(MYSQLI_ASSOC);
}

// --- 2. PEGAR VALORES DOS FILTROS (via GET) ---
$filtro_titulo = $_GET['filtro_titulo'] ?? '';
$filtro_categoria = $_GET['filtro_categoria'] ?? '';
$filtro_tag = $_GET['filtro_tag'] ?? '';

// --- 3. CONSTRUÇÃO DA CONSULTA SQL DINÂMICA ---

// Cláusulas base
$sql_select = "SELECT DISTINCT l.id_local, l.titulo, l.imagem_url, c.nome AS nome_categoria";
$sql_from = " FROM locais AS l JOIN categorias AS c ON l.id_categoria = c.id_categoria";
$sql_joins = ""; // Joins adicionais (para tags)
$sql_where_clauses = []; // Array para as cláusulas WHERE
$params = []; // Array para os parâmetros do prepared statement
$types = ""; // String para os tipos de parâmetros

// Adicionar filtro de TÍTULO
if (!empty($filtro_titulo)) {
    $sql_where_clauses[] = "l.titulo LIKE ?";
    $params[] = "%" . $filtro_titulo . "%";
    $types .= "s";
}

// Adicionar filtro de CATEGORIA
if (!empty($filtro_categoria)) {
    $sql_where_clauses[] = "l.id_categoria = ?";
    $params[] = $filtro_categoria;
    $types .= "i";
}

// Adicionar filtro de TAG
if (!empty($filtro_tag)) {
    // Adiciona os JOINS necessários SÓ se o filtro de tag estiver ativo
    // Assumindo 'id_local_fk' e 'id_tag_fk' na tabela 'local_tags'
    $sql_joins .= " JOIN local_tags lt ON l.id_local = lt.id_local_fk";
    $sql_joins .= " JOIN tags t ON lt.id_tag_fk = t.id_tag";
    
    $sql_where_clauses[] = "lt.id_tag_fk = ?";
    $params[] = $filtro_tag;
    $types .= "i";
}

// Montar a consulta final
$sql_locais = $sql_select . $sql_from . $sql_joins;

if (count($sql_where_clauses) > 0) {
    $sql_locais .= " WHERE " . implode(" AND ", $sql_where_clauses);
}

$sql_locais .= " ORDER BY c.nome ASC, l.titulo ASC";

// Preparar e executar a consulta
$stmt = $conn->prepare($sql_locais);
if (count($params) > 0) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado_locais = $stmt->get_result();

// Fechamos a conexão aqui, após todas as consultas
$conn->close();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Locais - DuoDates Admin</title>
<<<<<<< HEAD
    <link rel="stylesheet" href="../css/admin.css?v=1.1">
=======
    <link rel="stylesheet" href="admin.css?v=1.1">
>>>>>>> a43d483c9258fe940c36652caaa0ab57ce59bc08
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .filter-form {
            background-color: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            min-width: 150px;
        }
        .filter-group label {
            font-weight: bold;
            font-size: 0.9em;
            margin-bottom: 5px;
            color: #333;
        }
        .filter-group input,
        .filter-group select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 1em;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        .filter-actions button,
        .filter-actions a {
            padding: 8px 15px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            font-size: 0.9em;
            white-space: nowrap;
        }
        .filter-actions button[type="submit"] {
            background-color: #5E313D; /* Cor principal */
            color: white;
        }
        .filter-actions a {
            background-color: #f0f0f0;
            color: #555;
            border: 1px solid #ccc;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2>DuoDates Admin</h2>
            </div>
            <nav class="sidebar-nav">
                <a href="../php/admin.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../php/gerenciar_usuarios.php" class="nav-item"><i class="fas fa-users"></i> Gerenciar Usuários</a>
                <a href="../php/gerenciar_locais.php" class="nav-item active"><i class="fas fa-map-marker-alt"></i> Gerenciar Locais</a>
                <a href="#" class="nav-item"><i class="fas fa-calendar-alt"></i> Ver Agendamentos</a>
                <a href="../index.php" class="nav-item"><i class="fas fa-globe"></i> Ver Site</a>
            </nav>
        </aside>

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

                    <form class="filter-form" method="GET" action="../php/gerenciar_locais.php">
                        <div class="filter-group">
                            <label for="filtro_titulo">Filtrar por Título</label>
                            <input type="text" id="filtro_titulo" name="filtro_titulo" value="<?php echo htmlspecialchars($filtro_titulo); ?>" placeholder="Ex: Restaurante...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="filtro_categoria">Filtrar por Categoria</label>
                            <select id="filtro_categoria" name="filtro_categoria">
                                <option value="">-- Todas as Categorias --</option>
                                <?php foreach ($lista_categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>" <?php echo ($categoria['id_categoria'] == $filtro_categoria) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="filtro_tag">Filtrar por Tag</label>
                            <select id="filtro_tag" name="filtro_tag">
                                <option value="">-- Todas as Tags --</option>
                                <?php foreach ($lista_tags as $tag): ?>
                                    <option value="<?php echo $tag['id_tag']; ?>" <?php echo ($tag['id_tag'] == $filtro_tag) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tag['nome_tag']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                            <a href="../php/gerenciar_locais.php">Limpar</a>
                        </div>
                    </form>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Título</th>
                                <th>Categoria</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultado_locais->num_rows > 0): ?>
                                <?php while($local = $resultado_locais->fetch_assoc()): ?>
                                <tr id="local-row-<?php echo $local['id_local']; ?>">
                                    <td>
                                        <img src="<?php echo htmlspecialchars($local['imagem_url']); ?>" alt="<?php echo htmlspecialchars($local['titulo']); ?>" class="table-image-thumbnail">
                                    </td>
                                    <td><?php echo htmlspecialchars($local['titulo']); ?></td>
                                    <td><?php echo htmlspecialchars($local['nome_categoria']); ?></td>
                                    <td class="actions-cell">
                                        <a href="../php/editar_local.php?id=<?php echo $local['id_local']; ?>" class="action-btn edit">Editar</a>
                                        <button class="action-btn delete" data-id="<?php echo $local['id_local']; ?>">Deletar</button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">Nenhum local encontrado com esses filtros. <a href="gerenciar_locais.php">Limpar filtros</a>.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
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
                            alert(data.message);
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
</body>
</html>