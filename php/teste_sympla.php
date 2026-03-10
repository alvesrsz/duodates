<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos em Brasília (Sympla)</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #FF7A00; padding-bottom: 10px; }
        ul { list-style: none; padding: 0; }
        li { background: #fafafa; padding: 15px; border-radius: 5px; margin-bottom: 10px; border: 1px solid #eee; }
        li a { font-size: 1.1em; text-decoration: none; color: #0056b3; font-weight: bold; }
        li a:hover { text-decoration: underline; }
        .error { color: #d32f2f; font-weight: bold; }
        .debug-info { background: #fff8e1; border: 1px solid #ffecb3; padding: 15px; margin-top: 20px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: monospace; }
    </style>
</head>
<body>

<div class="container">
    <h1><img src="https://logodownload.org/wp-content/uploads/2019/09/sympla-logo.png" alt="Sympla Logo" height="30" style="vertical-align: middle;"> Eventos em Brasília</h1>
    
    <ul id="event-list">
        <?php
        
        $apiUrl = 'https://www.sympla.com.br/api/v1/search?city=brasilia-df&page=1&type=event';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $jsonResponse = curl_exec($ch);
        curl_close($ch);

        // --- INÍCIO DO CÓDIGO DE DEPURAÇÃO ---
        // Este bloco irá imprimir a resposta exata da API para podermos analisá-la.
        echo "<div class='debug-info'>";
        echo "<strong>--- INFORMAÇÕES DE DEPURAÇÃO ---</strong><br><br>";
        echo "<strong>Resposta Bruta da API:</strong><br>";
        echo htmlspecialchars($jsonResponse);
        echo "<br><br><strong>--- FIM DA DEPURAÇÃO ---</strong>";
        echo "</div>";
        // --- FIM DO CÓDIGO DE DEPURAÇÃO ---

        if ($jsonResponse === false || empty($jsonResponse)) {
            echo "<li class='error'>Falha na comunicação com a API da Sympla.</li>";
        } else {
            $dados = json_decode($jsonResponse, true);

            if (isset($dados['data']) && count($dados['data']) > 0) {
                foreach ($dados['data'] as $evento) {
                    $titulo = $evento['name'];
                    $url_evento = $evento['url'];
                    echo "<li><a href='" . htmlspecialchars($url_evento) . "' target='_blank'>" . htmlspecialchars($titulo) . "</a></li>";
                }
            } else {
                echo "<li class='error'>Nenhum evento encontrado para Brasília no momento.</li>";
            }
        }
        ?>
    </ul>
</div>

</body>
</html>