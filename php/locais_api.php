<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locais DuoDates</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        h2 {
            color: #e84393;
        }
        #lista-locais {
            list-style: none;
            padding: 0;
            width: 100%;
            max-width: 600px;
        }
        .card {
            background: #fff;
            padding: 15px 20px;
            margin-bottom: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .nome {
            font-size: 18px;
            font-weight: bold;
            color: #2d3436;
            margin-bottom: 5px;
        }
        .endereco {
            font-size: 14px;
            color: #636e72;
        }
    </style>
</head>
<body>
    <h2>Restaurantes Encontrados</h2>
    <ul id="lista-locais"></ul>

    <script>
        fetch('teste_api.php')
            .then(response => response.json())
            .then(data => {
                const lista = document.getElementById('lista-locais');
                data.features.forEach(local => {
                    const nome = local.properties.name || 'Local sem nome';
                    const endereco = local.properties.address_line2 || 'Endereço não disponível';
                    
                    const item = document.createElement('li');
                    item.className = 'card';
                    item.innerHTML = `<div class="nome">${nome}</div><div class="endereco">${endereco}</div>`;
                    lista.appendChild(item);
                });
            })
            .catch(error => {
                console.error('Erro:', error);
                document.getElementById('lista-locais').innerHTML = '<li>Erro ao carregar locais.</li>';
            });
    </script>
</body>
</html>