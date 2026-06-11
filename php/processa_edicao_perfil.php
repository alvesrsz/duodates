<?php
session_start();
include '../conexao.php';

// Proteção: verifica se o usuário está logado e se o método é POST
if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../php/login.php');
    exit();
}

$id_usuario_logado = $_SESSION['user_id'];
$novo_nome = trim($_POST['nome']);
$caminho_foto = null;

if (empty($novo_nome)) {
    $_SESSION['error_message'] = "O nome de usuário não pode ficar em branco.";
    header('Location: ../php/editar_perfil.php');
    exit();
}

// --- Lógica para o Upload da Foto ---
if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] == 0) {
    $upload_dir = '../uploads/';
    
    if (!is_dir($upload_dir) || !is_writable($upload_dir)) {
        $_SESSION['error_message'] = "Erro de servidor: O diretório de uploads não existe ou não tem permissão de escrita.";
        header('Location: ../php/editar_perfil.php');
        exit();
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['foto_perfil']['type'];

    if (in_array($file_type, $allowed_types)) {
        $file_extension = pathinfo($_FILES['foto_perfil']['name'], PATHINFO_EXTENSION);
        $nome_unico = 'user_' . $id_usuario_logado . '_' . time() . '.' . $file_extension;
        $caminho_foto = $upload_dir . $nome_unico;

        if (!move_uploaded_file($_FILES['foto_perfil']['tmp_name'], $caminho_foto)) {
            $_SESSION['error_message'] = "Erro ao salvar a nova foto.";
            header('Location: ../php/editar_perfil.php');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Formato de arquivo não permitido. Use JPG, PNG ou GIF.";
        header('Location: ../php/editar_perfil.php');
        exit();
    }
}

// --- Atualização no Banco de Dados ---
$sql_parts = [];
$params = [];
$types = "";

$sql_parts[] = "nome = ?";
$params[] = $novo_nome;
$types .= "s";

if ($caminho_foto !== null) {
    $sql_parts[] = "foto_perfil = ?";
    $params[] = $caminho_foto;
    $types .= "s";
}

if (count($sql_parts) > 0) {
    $params[] = $id_usuario_logado;
    $types .= "i";
    $sql = "UPDATE usuarios SET " . implode(", ", $sql_parts) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
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
     $_SESSION['success_message'] = "Nenhuma alteração foi feita.";
}

$conn->close();
header('Location: ../php/editar_perfil.php');
exit();
?>