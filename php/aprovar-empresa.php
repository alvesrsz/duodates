<?php
session_start();
include '../conexao.php';

// Segurança: Só admin
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_conta'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

if (isset($_GET['id'])) {
    $id_usuario = (int)$_GET['id'];

    $conn->begin_transaction();
    try {
        // 1. Pega dados do PENDENTE
        $stmt = $conn->prepare("SELECT * FROM locais_pendentes WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_usuario);
        $stmt->execute();
        $pendente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$pendente) throw new Exception("Local pendente não encontrado.");

        // 2. Insere na tabela OFICIAL (locais)
        $sql = "INSERT INTO locais (id_categoria, titulo, descricao, imagem_url, local_info, horario_info, link_botao, texto_botao, slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssss", $pendente['id_categoria'], $pendente['titulo'], $pendente['descricao'], $pendente['imagem_url'], $pendente['local_info'], $pendente['horario_info'], $pendente['link_botao'], $pendente['texto_botao'], $pendente['slug']);
        $stmt->execute();
        $novo_id_local = $conn->insert_id; // ID do local oficial
        $stmt->close();

        // 3. Processa Tags
        if (!empty($pendente['tags_selecionadas'])) {
            $tags = explode(',', $pendente['tags_selecionadas']);
            $stmt = $conn->prepare("INSERT INTO local_tags (id_local_fk, id_tag_fk) VALUES (?, ?)");
            foreach ($tags as $t) {
                if(is_numeric(trim($t))) {
                    $stmt->bind_param("ii", $novo_id_local, trim($t));
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        // 4. VINCULA O USUÁRIO (O passo que faz aparecer no dashboard)
        $stmt = $conn->prepare("UPDATE usuarios SET status_aprovacao = 'aprovado', id_local_associado = ? WHERE id = ?");
        $stmt->bind_param("ii", $novo_id_local, $id_usuario);
        $stmt->execute();
        $stmt->close();

        // 5. Deleta o Pendente
        $stmt = $conn->prepare("DELETE FROM locais_pendentes WHERE id = ?");
        $stmt->bind_param("i", $pendente['id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        header("Location: admin.php?view=pendentes&msg=aprovado");

    } catch (Exception $e) {
        $conn->rollback();
        die("Erro: " . $e->getMessage());
    }
}
?>