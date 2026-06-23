<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit;
}

$is_admin          = isset($_SESSION['tipo_conta']) && $_SESSION['tipo_conta'] === 'admin';
$is_business_signup = isset($_SESSION['dados_cadastro_empresa']);
$is_logged_business = isset($_SESSION['tipo_conta']) && $_SESSION['tipo_conta'] === 'empresarial' && isset($_SESSION['id']);

if (!$is_admin && !$is_business_signup && !$is_logged_business) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado.']);
    exit;
}

$nome_lugar = trim($_POST['nome_lugar'] ?? '');
if (empty($nome_lugar)) {
    echo json_encode(['erro' => 'Informe o nome do lugar.']);
    exit;
}

if (file_exists('../conexao.php'))  include '../conexao.php';
elseif (file_exists('conexao.php')) include 'conexao.php';

// Busca tags do banco
$tags_lista = [];
$result_tags = $conn->query("SELECT id_tag, nome_tag, categoria FROM tags ORDER BY categoria, nome_tag");
if ($result_tags) {
    while ($row = $result_tags->fetch_assoc()) $tags_lista[] = $row;
}

// Busca categorias do banco
$categorias_lista = [];
$result_cats = $conn->query("SELECT id_categoria, nome FROM categorias ORDER BY nome");
if ($result_cats) {
    while ($row = $result_cats->fetch_assoc()) $categorias_lista[] = $row;
}

// Monta lista de tags para o prompt
$tags_texto = '';
foreach ($tags_lista as $tag) {
    $tags_texto .= "ID {$tag['id_tag']}: {$tag['nome_tag']} (Grupo: {$tag['categoria']})\n";
}

// Monta lista de categorias para o prompt
$cats_texto = '';
foreach ($categorias_lista as $cat) {
    $cats_texto .= "ID {$cat['id_categoria']}: {$cat['nome']}\n";
}

$lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
$lon = isset($_POST['lon']) ? (float)$_POST['lon'] : null;
$localizacao = ($lat !== null)
    ? "coordenadas geográficas {$lat}, {$lon} no Brasil — use isso para identificar a cidade/bairro mais próximo"
    : "Brasília, DF, Brasil";

$prompt = <<<PROMPT
Você é um especialista em locais para encontros, dates e passeios no Brasil.

O usuário quer cadastrar em um app de dates o seguinte lugar: "{$nome_lugar}"
LOCALIZAÇÃO DO USUÁRIO: {$localizacao}

Com base no nome e na localização, gere todas as informações para o cadastro.
Priorize a unidade ou endereço mais próximo da localização informada.
Se for um lugar famoso com várias unidades (rede), use a unidade mais próxima da localização do usuário.
Se for genérico, crie uma descrição atraente e indique a cidade do usuário como referência de local.

CATEGORIAS DISPONÍVEIS (escolha a mais adequada):
{$cats_texto}

TAGS DISPONÍVEIS (selecione todas que se aplicam ao lugar):
{$tags_texto}

Retorne SOMENTE um JSON válido, sem nenhum texto fora dele:
{
  "titulo": "Nome completo/oficial do lugar",
  "descricao": "Descrição envolvente de 2 a 3 frases destacando o clima, a experiência e por que é ideal para um date ou encontro",
  "local_info": "Endereço completo ou localização genérica (bairro, cidade/estado) caso não saiba o endereço exato",
  "horario_info": "Horário de funcionamento típico deste tipo de estabelecimento",
  "link_botao": "URL do site oficial, Instagram ou Google Maps, se souber — caso contrário deixe vazio",
  "texto_botao": "Texto curto para o botão de ação (ex: Ver no Instagram, Reservar, Site Oficial)",
  "id_categoria": <ID numérico da categoria mais adequada da lista acima>,
  "tag_ids": [<IDs numéricos das tags mais adequadas da lista acima>]
}

Responda em português do Brasil. Seja criativo na descrição para atrair casais e amigos.
PROMPT;

$api_key = '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';

$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => [['role' => 'user', 'content' => $prompt]],
    'temperature' => 0.4,
    'max_tokens'  => 700
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key
    ],
    CURLOPT_TIMEOUT => 25
]);

$response   = curl_exec($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['erro' => 'Falha na conexão com a IA: ' . $curl_error]);
    exit;
}
if ($http_code !== 200) {
    echo json_encode(['erro' => 'A API retornou erro HTTP ' . $http_code . '.']);
    exit;
}

$body    = json_decode($response, true);
$content = $body['choices'][0]['message']['content'] ?? '';

preg_match('/\{[\s\S]*\}/u', $content, $matches);
if (empty($matches)) {
    echo json_encode(['erro' => 'A IA retornou uma resposta inesperada. Tente novamente.']);
    exit;
}

$result = json_decode($matches[0], true);
if (!$result) {
    echo json_encode(['erro' => 'Não foi possível interpretar a resposta da IA.']);
    exit;
}

// Valida IDs de tags retornados
$valid_tag_ids = array_column($tags_lista, 'id_tag');
if (isset($result['tag_ids']) && is_array($result['tag_ids'])) {
    $result['tag_ids'] = array_values(
        array_filter($result['tag_ids'], fn($id) => in_array((int)$id, $valid_tag_ids))
    );
}

// Valida ID de categoria
$valid_cat_ids = array_column($categorias_lista, 'id_categoria');
if (isset($result['id_categoria']) && !in_array((int)$result['id_categoria'], $valid_cat_ids)) {
    $result['id_categoria'] = null;
}

echo json_encode($result);
