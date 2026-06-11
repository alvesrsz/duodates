<?php
include 'reservaconexao.php'; // Conexão com o banco

// Busca os locais do banco de dados
$sql = "SELECT id_local, titulo, local_info FROM locais ORDER BY titulo ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duo Dates - Reservar Date</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
    
    <style>
        /* CSS - Estilos da Página */
        :root { --bg-bege: #F9F4EF; --vinho-forte: #7D1D2C; --texto-cinza: #5A5A5A; --branco: #FFFFFF; }
        body { font-family: 'Montserrat', sans-serif; background-color: var(--bg-bege); margin: 0; color: var(--texto-cinza); }
        header { display: flex; justify-content: space-between; align-items: center; padding: 20px 50px; background-color: var(--bg-bege); }
        
        /* --- ESTILO DA LOGO (IMAGEM + TEXTO) --- */
        .logo {
            display: flex;          /* Coloca imagem e texto lado a lado */
            align-items: center;    /* Centraliza verticalmente */
            gap: 15px;              /* Espaço entre a imagem e o texto */
            
            /* Estilo do Texto */
            font-family: 'Playfair Display', serif;
            font-size: 28px;        /* Tamanho do texto */
            color: var(--vinho-forte);
            font-weight: bold;
            cursor: pointer;        /* Mostra a mãozinha se passar o mouse */
        }

        .logo img {
            height: 45px;           /* Altura da imagem */
            width: auto;
        }

        /* Container Principal */
        .main-container { display: flex; justify-content: center; align-items: center; min-height: 80vh; padding: 20px; }
        .reservation-card { background-color: var(--branco); width: 100%; max-width: 500px; padding: 40px; border-radius: 15px; box-shadow: 0 10px 30px rgba(125, 29, 44, 0.05); text-align: center; }
        h1 { font-family: 'Playfair Display', serif; color: var(--vinho-forte); font-size: 36px; margin-bottom: 10px; }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { font-size: 12px; font-weight: bold; color: var(--vinho-forte); text-transform: uppercase; display: block; margin-bottom: 8px; }
        input, textarea, select { width: 100%; padding: 15px; border: 1px solid #E0E0E0; background-color: #FAFAFA; border-radius: 8px; font-family: 'Montserrat', sans-serif; font-size: 14px; box-sizing: border-box; }
        input:focus, textarea:focus, select:focus { outline: none; border-color: var(--vinho-forte); background-color: white; }
        button.btn-submit { background-color: var(--vinho-forte); color: white; font-family: 'Montserrat', sans-serif; font-weight: 700; text-transform: uppercase; font-size: 14px; padding: 15px 40px; border: none; border-radius: 30px; cursor: pointer; margin-top: 10px; width: 100%; }
        button.btn-submit:hover { transform: scale(1.02); background-color: #5e1520; }
        .decoration { font-size: 40px; margin-bottom: 10px; }
    </style>
</head>
<body>

    <header>
        <div class="logo">
            <img src="../images/logoduodates.png" alt="Duo Dates Logo">
            <span>Duo Dates</span>
        </div>
        
        <nav>
            </nav>
    </header>

    <div class="main-container">
        <div class="reservation-card">
            <div class="decoration">❤️</div>
            <h1>Reservar Date</h1>
            <p style="margin-bottom: 30px; font-size: 14px; color: #888;">Escolha um dos lugares incríveis da nossa lista.</p>

            <form action="salvar_reserva.php" method="POST">
                
                <div class="form-group">
                    <label for="lugar">Escolha o Local</label>
                    <select name="lugar_id" id="lugar" required>
                        <option value="" disabled selected>Selecione um local...</option>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<option value='" . $row['id_local'] . "'>" . $row['titulo'] . "</option>";
                            }
                        } else {
                            echo "<option value='' disabled>Nenhum local encontrado no banco</option>";
                        }
                        ?>
                    </select>
                </div>

                <div style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Data</label>
                        <input type="date" name="data_date" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Horário</label>
                        <input type="time" name="hora_date" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Detalhes</label>
                    <textarea name="notas" rows="3" placeholder="Ex: Mesa no canto..."></textarea>
                </div>

                <button type="submit" class="btn-submit">Confirmar Reserva</button>
            </form>
        </div>
    </div>
</body>
</html>