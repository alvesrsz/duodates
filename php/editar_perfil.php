<?php
session_start();
include '../conexao.php'; // Inclui seu arquivo de conexão mysqli

// Proteção: verifica se o usuário está logado e se o método é POST
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../php/login.php');
    exit();
}

$id_usuario_logado = $_SESSION['user_id'];
$novo_nome = trim($_POST['nome']);
$caminho_foto = null;

// Validação do nome
if (empty($novo_nome)) {
    $_SESSION['error_message'] = "O nome de usuário não pode ficar em branco.";
    header('Location: ../php/perfil.php');
    exit();
}

// --- Lógica para o Upload da Foto ---
if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
    $upload_dir = '../uploads/';
    
    // --- VERIFICAÇÃO IMPORTANTE: Checa se o diretório existe e se tem permissão de escrita ---
    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
        $_SESSION['error_message'] = "Erro de servidor: O diretório de uploads não existe ou não tem permissão de escrita.";
        header('Location: ../php/perfil.php');
        exit();
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['foto_perfil']['type'];

    if (in_array($file_type, $allowed_types)) {
        // Gera um nome de arquivo único para evitar conflitos
        $file_extension = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
        $nome_unico = uniqid('user_' . $id_usuario_logado . '_', true) . '.' . $file_extension;
        $caminho_foto = $upload_dir . $nome_unico;

        // Move o arquivo para a pasta de uploads
        if (!move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $caminho_foto)) {
            $_SESSION['error_message'] = "Erro ao salvar a nova foto.";
            header('Location: ../php/perfil.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Formato de arquivo não permitido. Use JPG, PNG ou GIF.";
        header('Location: ../php/perfil.php');
        exit();
    }
}

// --- Atualização no Banco de Dados ---
$sql_parts = [];
$params = [];
$types = "";

// Adiciona o nome à query (sempre será atualizado)
$sql_parts[] = "nome = ?";
$params[] = $novo_nome;
$types .= "s";

// Adiciona a foto à query APENAS se uma nova foto foi enviada
if ($caminho_foto !== null) {
    $sql_parts[] = "foto_perfil = ?";
    $params[] = $caminho_foto;
    $types .= "s";
}

// Finaliza a query
$params[] = $id_usuario_logado;
$types .= "i";

// ▼▼▼ CORREÇÃO PRINCIPAL AQUI ▼▼▼
// Alterado 'id_usuario = ?' para 'id = ?' para corresponder à sua tabela
$sql = "UPDATE usuarios SET " . implode(", ", $sql_parts) . " WHERE id = ?";

// Se não houver partes para atualizar (ex: só clicou em salvar sem mudar nada), não faz nada
if (count($sql_parts) > 0) {
    $stmt = $conn->prepare($sql);
    
    // Usa o operador splat (...) para passar os parâmetros dinamicamente
    if ($stmt) {
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            // Atualiza a sessão com o novo nome
            $_SESSION['usuario'] = $novo_nome;
            $_SESSION['success_message'] = "Perfil atualizado com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao atualizar o perfil: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Erro ao preparar a query: " . $conn->error;
    }
} else {
    // Caso o usuário não altere nem nome nem foto
    $_SESSION['success_message'] = "Nenhuma alteração foi feita.";
}

$conn->close();

header('Location: ../php/perfil.php');
exit();
?>