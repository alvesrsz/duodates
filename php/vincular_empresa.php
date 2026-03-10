<?php
session_start();
include('../conexao.php');

// Verifica se está logado
if (!isset($_SESSION['id'])) {
    die("Erro: Você precisa fazer login na sua conta empresarial primeiro.");
}

$msg = "";

// Lógica de Vinculação
if (isset($_POST['vincular_id'])) {
    $id_local_escolhido = intval($_POST['vincular_id']);
    $id_usuario = $_SESSION['id'];

    // Atualiza o usuário para ser dono deste local e aprova a conta
    $sql = "UPDATE usuarios SET id_local_associado = ?, status_aprovacao = 'aprovado' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_local_escolhido, $id_usuario);
    
    if ($stmt->execute()) {
        $msg = "<div style='background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border-radius:5px;'>
                    ✅ Conta vinculada com sucesso! <a href='dashboard_empresa.php'>Ir para o Dashboard</a>
                </div>";
        // Atualiza a sessão para refletir a mudança na hora
        $_SESSION['status_aprovacao'] = 'aprovado';
    } else {
        $msg = "<div style='color:red;'>Erro ao vincular: " . $conn->error . "</div>";
    }
}

// Busca os últimos 5 locais cadastrados no sistema para você escolher
$locais = [];
if ($conn) {
    $res = $conn->query("SELECT id_local, titulo, criado_em FROM locais ORDER BY id_local DESC LIMIT 5");
    if ($res) {
        $locais = $res->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reparar Conta Empresarial</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-width: 500px; width: 100%; }
        h2 { text-align: center; color: #333; }
        .item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #eee; }
        .btn { background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn:hover { background: #218838; }
        .warning { background: #fff3cd; color: #856404; padding: 10px; font-size: 13px; margin-bottom: 15px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔧 Vincular Minha Empresa</h2>
        <?php echo $msg; ?>
        
        <div class="warning">
            <strong>Atenção:</strong> Identifique qual destes locais é o seu e clique em "É o meu". Isso vai corrigir o erro "Nenhum Local Vinculado".
        </div>

        <?php if (empty($locais)): ?>
            <p style="text-align:center; color:red;">Nenhum local encontrado na tabela 'locais'. Cadastre um primeiro.</p>
        <?php else: ?>
            <?php foreach($locais as $local): ?>
                <div class="item">
                    <div>
                        <strong>ID: <?php echo $local['id_local']; ?></strong><br>
                        <?php echo htmlspecialchars($local['titulo']); ?>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="vincular_id" value="<?php echo $local['id_local']; ?>">
                        <button type="submit" class="btn">É o meu</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>