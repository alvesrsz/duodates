<?php
// ../php/buscar_ticketmaster.php

$apiKey = 'SV9ymMbjQ59AqO0Iuuw0BHGCsN3mEKIK'; 

$cidade = 'Brasilia';
$paisCodigo = 'BR'; 
$quantidadeResultados = 5; 

$agoraUTC = gmdate('Y-m-d\TH:i:s\Z'); 
$apiUrl = "https://app.ticketmaster.com/discovery/v2/events.json?" . http_build_query([
    'apikey' => $apiKey,
    'city' => $cidade,
    'countryCode' => $paisCodigo,
    'size' => $quantidadeResultados,
    'sort' => 'date,asc',
    'startDateTime' => $agoraUTC 
]);

$eventosFormatados = []; 

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    $eventosFormatados = ['error' => 'Falha ao conectar com Ticketmaster.'];
} else {
    $dados = json_decode($response, true);

    if (isset($dados['_embedded']['events']) && count($dados['_embedded']['events']) > 0) {
        
        // Array para tradução manual dos meses (evita erros do strftime em PHP 8.1+)
        $meses = [
            'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr',
            'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
            'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
        ];

        foreach ($dados['_embedded']['events'] as $evento) {
            $nomeEvento = $evento['name'] ?? 'Nome Indisponível';
            $urlEvento = $evento['url'] ?? '#';
            $dataEvento = $evento['dates']['start']['localDate'] ?? '';
            $horaEvento = $evento['dates']['start']['localTime'] ?? '';
            $localNome = $evento['_embedded']['venues'][0]['name'] ?? 'Local a confirmar';

            $dataHoraFormatada = '';
            if ($dataEvento) {
                 $timestamp = strtotime($dataEvento . ' ' . $horaEvento);
                 if ($timestamp) {
                     // Formata dia e hora
                     $dia = date('d', $timestamp);
                     $mesIngles = date('M', $timestamp);
                     $hora = date('H:i', $timestamp);
                     $mesPt = $meses[$mesIngles] ?? $mesIngles;
                     
                     $dataHoraFormatada = "$dia/$mesPt $hora";
                 }
            }

            $eventosFormatados[] = [
                'nome' => $nomeEvento,
                'url' => $urlEvento,
                'dataHora' => $dataHoraFormatada,
                'local' => $localNome
            ];
        }
    } elseif (isset($dados['page']['totalElements']) && $dados['page']['totalElements'] === 0) {
         $eventosFormatados = ['info' => 'Nenhum evento encontrado em Brasília.'];
    } else {
        $eventosFormatados = ['error' => 'Resposta inesperada da API.'];
    }
}
?>