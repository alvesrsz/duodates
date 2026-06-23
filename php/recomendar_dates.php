<?php
session_start();
include('../conexao.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['erro' => 'Não autorizado.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido.']);
    exit();
}

$user_id    = (int)$_SESSION['user_id'];
$partner_id = (int)($_POST['partner_id'] ?? 0);
$id_conexao = (int)($_POST['id_conexao'] ?? 0);

if (!$partner_id || !$id_conexao) {
    echo json_encode(['erro' => 'Parâmetros inválidos.']);
    exit();
}

// --- 1. Perfis dos dois usuários (Sistema 1) ---
$sql_user = "SELECT nome, pref_vibe, pref_food_ranking, pref_atividade, pref_comfort FROM usuarios WHERE id = ?";

$stmt = $conn->prepare($sql_user);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt2 = $conn->prepare($sql_user);
$stmt2->bind_param("i", $partner_id);
$stmt2->execute();
$partner_data = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

if (!$user_data || !$partner_data) {
    echo json_encode(['erro' => 'Usuário não encontrado.']);
    exit();
}

// --- 2. Respostas do questionário (Sistema 2) com nomes das tags ---
function getTagsDoQuestionario($uid, $id_conexao, $conn) {
    $sql = "SELECT t.nome_tag, t.categoria
            FROM respostas_usuario r
            JOIN tags t ON r.resposta_selecionada = t.id_tag
            WHERE r.id_usuario_fk = ? AND r.id_conexao_fk = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    $stmt->bind_param("ii", $uid, $id_conexao);
    $stmt->execute();
    $result = $stmt->get_result();
    $tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags[] = $row['nome_tag'] . ' (' . $row['categoria'] . ')';
    }
    $stmt->close();
    return array_unique($tags);
}

$tags_user    = getTagsDoQuestionario($user_id, $id_conexao, $conn);
$tags_partner = getTagsDoQuestionario($partner_id, $id_conexao, $conn);

// --- 3. Eventos do Ticketmaster ---
$eventosTexto = '';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://app.ticketmaster.com/discovery/v2/events.json?" . http_build_query([
    'apikey'        => 'SV9ymMbjQ59AqO0Iuuw0BHGCsN3mEKIK',
    'countryCode'   => 'BR',
    'stateCode'     => 'DF',
    'size'          => 5,
    'sort'          => 'date,asc',
    'startDateTime' => gmdate('Y-m-d\TH:i:s\Z'),
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
$tmResponse = curl_exec($ch);
$tmCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($tmResponse !== false && $tmCode === 200) {
    $tmDados = json_decode($tmResponse, true);
    foreach ($tmDados['_embedded']['events'] ?? [] as $ev) {
        $nome  = $ev['name'] ?? 'Evento';
        $data  = $ev['dates']['start']['localDate'] ?? '';
        $local = $ev['_embedded']['venues'][0]['name'] ?? 'Local a confirmar';
        $generos = [];
        foreach ($ev['classifications'] ?? [] as $cl) {
            if (!empty($cl['segment']['name']) && $cl['segment']['name'] !== 'Undefined') {
                $generos[] = $cl['segment']['name'];
            }
            if (!empty($cl['genre']['name']) && $cl['genre']['name'] !== 'Undefined') {
                $generos[] = $cl['genre']['name'];
            }
        }
        $tipo = implode('/', array_unique($generos)) ?: 'Evento';
        $eventosTexto .= "- {$nome} | {$tipo} | {$local} | {$data}\n";
    }
}
if (empty($eventosTexto)) {
    $eventosTexto = "Nenhum evento disponível no momento.";
}

// --- 4. Monta o prompt ---
$nome_u = $user_data['nome'];
$nome_p = $partner_data['nome'];

$perfil = function($d) {
    $partes = [];
    if (!empty($d['pref_vibe']))      $partes[] = 'Vibe: ' . $d['pref_vibe'];
    if (!empty($d['pref_atividade'])) $partes[] = 'Atividades preferidas: ' . $d['pref_atividade'];
    if (!empty($d['pref_comfort']))   $partes[] = 'Conforto: ' . $d['pref_comfort'];
    if (!empty($d['pref_food_ranking'])) {
        $food = json_decode($d['pref_food_ranking'], true);
        if (is_array($food)) {
            arsort($food);
            $partes[] = 'Culinária favorita: ' . implode(', ', array_slice(array_keys($food), 0, 3));
        }
    }
    return implode(' | ', $partes) ?: 'Perfil não preenchido.';
};

$tags_u_str = empty($tags_user)    ? 'Não respondeu ao questionário.' : implode(', ', $tags_user);
$tags_p_str = empty($tags_partner) ? 'Não respondeu ao questionário.' : implode(', ', $tags_partner);

$prompt = "Você é um assistente especialista em planejar experiências românticas e dates para casais.\n\n"
    . "Analise os perfis do casal e gere uma recomendação personalizada.\n\n"
    . "=== PERFIL DE {$nome_u} ===\n"
    . $perfil($user_data) . "\n"
    . "Interesses do questionário: {$tags_u_str}\n\n"
    . "=== PERFIL DE {$nome_p} ===\n"
    . $perfil($partner_data) . "\n"
    . "Interesses do questionário: {$tags_p_str}\n\n"
    . "=== EVENTOS DISPONÍVEIS EM BRASÍLIA ===\n"
    . $eventosTexto . "\n"
    . "Retorne SOMENTE um JSON válido, sem texto fora dele, com exatamente estas 3 chaves:\n"
    . "{\n"
    . "  \"analise\": \"2 a 3 frases descrevendo quem são {$nome_u} e {$nome_p} como casal: o que têm em comum, qual é a energia deles juntos e o que os torna especiais.\",\n"
    . "  \"sugestao\": \"Uma sugestão de date específica e detalhada para este casal: tipo de local, ambiente, o que fariam, por que combina com eles. De 3 a 4 frases.\",\n"
    . "  \"evento\": \"Se algum evento da lista combinar com o perfil do casal, indique o nome e explique em 1 a 2 frases por que é ideal para eles. Se nenhum combinar, sugira um tipo de evento que eles curtiriam.\"\n"
    . "}\n"
    . "Responda em português do Brasil.";

// --- 5. Chama a Groq API ---
$payload = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => [['role' => 'user', 'content' => $prompt]],
    'temperature' => 0.7,
    'max_tokens'  => 700,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer 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',
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$resposta_raw = curl_exec($ch);
$http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error   = curl_error($ch);
curl_close($ch);

if ($resposta_raw === false || !empty($curl_error)) {
    echo json_encode(['erro' => 'Falha na conexão com a IA: ' . $curl_error]);
    exit();
}

if ($http_code !== 200) {
    echo json_encode(['erro' => "A API retornou erro HTTP {$http_code}."]);
    exit();
}

$data_groq = json_decode($resposta_raw, true);
$conteudo  = $data_groq['choices'][0]['message']['content'] ?? '';

preg_match('/\{[\s\S]*\}/u', $conteudo, $matches);
$json_str  = $matches[0] ?? $conteudo;
$resultado = json_decode($json_str, true);

if (!isset($resultado['analise'], $resultado['sugestao'], $resultado['evento'])) {
    echo json_encode(['erro' => 'A IA retornou uma resposta inesperada. Tente novamente.']);
    exit();
}

$conn->close();
echo json_encode($resultado);
?>
