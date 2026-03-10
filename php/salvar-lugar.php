<?php

// --- 1. CONFIGURAÇÃO DO BANCO DE DADOS ---

session_start();
include '../conexao.php';

if ($conexao->connect_error) {
    die("Falha na conexão: " . $conexao->connect_error);
}

// --- 3. VERIFICA SE O FORMULÁRIO FOI ENVIADO ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 4. COLETA DOS DADOS DO FORMULÁRIO ---
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $id_categoria = $_POST['id_categoria'];
    $link_botao = $_POST['link_botao'];
    
    // --- 5. TRATAMENTO DO UPLOAD DA IMAGEM ---
    $imagem_url = ''; // Valor padrão
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
        $diretorio_upload = "uploads/"; // Verifique se esta pasta existe e tem permissão de escrita
        
        $nome_arquivo = uniqid() . '_' . basename($_FILES["imagem"]["name"]);
        $caminho_completo = $diretorio_upload . $nome_arquivo;

        if (move_uploaded_file($_FILES["imagem"]["tmp_name"], $caminho_completo)) {
            $imagem_url = $caminho_completo; 
        } else {
            die("Erro ao fazer o upload da imagem. Verifique as permissões da pasta 'uploads'.");
        }
    } else {
        die("Nenhuma imagem enviada ou ocorreu um erro no upload.");
    }

    // --- 6. PREPARAÇÃO E EXECUÇÃO DA QUERY SQL ---
    // ATENÇÃO: Verifique o nome da sua tabela. Vou usar 'lugares' como exemplo.
    // Também adicionei uma coluna 'status' para moderação, você pode remover se não precisar.
    $sql = "INSERT INTO lugares (id_categoria, titulo, descricao, imagem_url, link_botao, status) VALUES (?, ?, ?, ?, ?, 'pendente')";
    
    $stmt = $conexao->prepare($sql);

    // O 'i' significa que id_categoria é um Inteiro, e os 's' significam que os outros são Strings.
    $stmt->bind_param("issss", $id_categoria, $titulo, $descricao, $imagem_url, $link_botao);

    if ($stmt->execute()) {
        echo "<h1>Lugar enviado com sucesso!</h1>";
        echo "<p>Obrigado! O local será revisado por um administrador.</p>";
        echo "<a href='../php/adicionar-lugar.php'>Adicionar outro lugar</a>";
    } else {
        echo "Erro ao salvar no banco de dados: " . $stmt->error;
    }

    $stmt->close();
    $conexao->close();

} else {
    echo "Acesso inválido.";
}
?>