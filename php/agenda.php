<?php
session_start();

// Se o usuário não estiver logado, redireciona para a página de login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Agenda - DuoDates</title>
    <link rel="stylesheet" href="../css/agenda.css">
    
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>
</head>
<body>
    <header>
        <a href="../php/perfil.php" class="back-button-link">
            <i class="arrow"></i>
        </a>
        <h1>DuoDates</h1>
    </header>

    <main class="container">
        <div class="page-title">
            <h2>Minha Agenda de Dates</h2>
            <p>Aqui estão todos os seus encontros agendados.</p>
        </div>

        <div id="calendar-container">
            <div id="calendar"></div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },

                buttonText: {
                    today: 'Hoje',
                    month: 'Mês',
                    week: 'Semana',
                    list: 'Lista'
                },

                events: '../php/buscar_agendamentos.php',

                handleWindowResize: true,
                dayMaxEventRows: true
            });

            calendar.render();
        });
    </script>
</body>
</html>