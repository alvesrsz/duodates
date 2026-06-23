<?php
session_start();
include('../conexao.php');

header('Content-Type: application/json');

// Aceita admin, empresa no fluxo de cadastro, empresa logada ou usuário comum logado
$autorizado = isset($_SESSION['user_id'])
    || isset($_SESSION['dados_cadastro_empresa'])
    || (isset($_SESSION['tipo_conta']) && $_SESSION['tipo_conta'] === 'admin');

if (!$autorizado) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit();
}

$titulo   = trim($_POST['titulo']   ?? '');
$descricao = trim($_POST['descricao'] ?? '');

if (empty($titulo) && empty($descricao)) {
    echo json_encode(['erro' => 'Preencha o título e/ou a descrição do lugar antes de sugerir tags.']);
    exit();
}

// Busca todas as tags do banco
$tags = [];
$result = $conn->query("SELECT id_tag, nome_tag, categoria FROM tags ORDER BY categoria, nome_tag");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tags[] = $row;
    }
}

if (empty($tags)) {
    echo json_encode(['erro' => 'Nenhuma tag cadastrada no sistema ainda.']);
    exit();
}

// Monta a lista de tags para o prompt
$tags_lista = '';
foreach ($tags as $tag) {
    $tags_lista .= "ID {$tag['id_tag']}: {$tag['nome_tag']} (Grupo: {$tag['categoria']})\n";
}

$texto_local = "Título: {$titulo}";
if (!empty($descricao)) {
    $texto_local .= "\nDescrição: {$descricao}";
}

$prompt = "Você é um assistente que categoriza locais para encontros e dates.\n\n"
    . "Analise o local abaixo e escolha as tags mais adequadas da lista.\n\n"
    . "LOCAL:\n{$texto_local}\n\n"
    . "TAGS DISPONÍVEIS:\n{$tags_lista}\n"
    . "Retorne SOMENTE um JSON válido no formato {\"ids\": [1, 2, 3]} com os IDs das tags que combinam com o local. "
    . "Selecione pelo menos uma tag de cada grupo quando possível. Não inclua nenhum texto fora do JSON.";

// Chama a Groq API
$api_key = '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';

$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => [['role' => 'user', 'content' => $prompt]],
    'temperature' => 0.2,
    'max_tokens'  => 300,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $api_key,
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$resposta_raw = curl_exec($ch);
$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error   = curl_error($ch);
curl_close($ch);

if ($resposta_raw === false || !empty($curl_error)) {
    echo json_encode(['erro' => 'Falha na conexão com a IA: ' . $curl_error]);
    exit();
}

if ($http_code !== 200) {
    echo json_encode(['erro' => "A API retornou erro HTTP {$http_code}. Tente novamente."]);
    exit();
}

$data      = json_decode($resposta_raw, true);
$conteudo  = $data['choices'][0]['message']['content'] ?? '';

// Extrai o JSON da resposta (mesmo que venha com texto ao redor)
preg_match('/\{[^{}]*"ids"\s*:\s*\[[^\]]*\][^{}]*\}/', $conteudo, $matches);
$json_str = $matches[0] ?? $conteudo;
$resultado = json_decode($json_str, true);

if (!isset($resultado['ids']) || !is_array($resultado['ids'])) {
    echo json_encode(['erro' => 'A IA retornou uma resposta inesperada. Tente novamente.']);
    exit();
}

// Valida os IDs contra os que existem no banco
$ids_validos   = array_map('intval', array_column($tags, 'id_tag'));
$ids_sugeridos = array_values(array_filter(
    array_map('intval', $resultado['ids']),
    fn($id) => in_array($id, $ids_validos)
));

$conn->close();
echo json_encode(['ids' => $ids_sugeridos]);
?>
