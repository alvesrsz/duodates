<?php
$apiKey = 'SV9ymMbjQ59AqO0Iuuw0BHGCsN3mEKIK'; 

$agoraUTC = gmdate('Y-m-d\TH:i:s\Z'); 
$apiUrl = "https://app.ticketmaster.com/discovery/v2/events.json?" . http_build_query([
    'apikey' => $apiKey,
    'countryCode' => 'BR',
    'stateCode' => 'DF',
    'size' => 5,
    'sort' => 'date,asc',
    'startDateTime' => $agoraUTC 
]);

$eventosFormatados = []; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response !== false && $httpCode === 200) {
    $dados = json_decode($response, true);
    if (isset($dados['_embedded']['events'])) {
        foreach ($dados['_embedded']['events'] as $evento) {
            $data = $evento['dates']['start']['localDate'] ?? '';
            $hora = $evento['dates']['start']['localTime'] ?? '';
            $dataHora = $data ? date('d/m H:i', strtotime("$data $hora")) : '';
            
            $eventosFormatados[] = [
                'nome' => $evento['name'] ?? 'Indisponível',
                'url' => $evento['url'] ?? '#',
                'dataHora' => $dataHora,
                'local' => $evento['_embedded']['venues'][0]['name'] ?? 'A confirmar'
            ];
        }
    }
}

if (empty($eventosFormatados)) {
    $eventosFormatados = [
        ['nome' => 'Festival Na Praia (Exemplo)', 'url' => '#', 'dataHora' => 'Sáb 18:00', 'local' => 'Setor de Clubes Sul'],
        ['nome' => 'Show Arena BRB (Exemplo)', 'url' => '#', 'dataHora' => 'Dom 20:00', 'local' => 'Arena BRB Mané Garrincha']
    ];
}
?>