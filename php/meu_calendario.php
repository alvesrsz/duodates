<?php
session_start();
include '../conexao.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../php/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$userName = "Usuário";
$userEmail = "";
$profilePhotoUrl = "../images/perfil.png";

$sql_user = "SELECT nome, email, foto_perfil FROM usuarios WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($user_data = $result_user->fetch_assoc()) {
    $userName = $user_data['nome'];
    $userEmail = $user_data['email'];
    if (!empty($user_data['foto_perfil']) && file_exists($user_data['foto_perfil'])) {
        $profilePhotoUrl = $user_data['foto_perfil'];
    }
}
$stmt_user->close();

$agendamentos = [];
$sql_agendamentos = "SELECT ag.id_agendamento, ag.data_agendada, ag.titulo_evento, ag.STATUS, l.titulo as nome_local
                     FROM agendamentos ag 
                     JOIN locais l ON ag.id_local = l.id_local 
                     WHERE ag.id_usuario = ? 
                     ORDER BY ag.data_agendada ASC";
$stmt_agendamentos = $conn->prepare($sql_agendamentos);
$stmt_agendamentos->bind_param("i", $user_id);
$stmt_agendamentos->execute();
$result_agendamentos = $stmt_agendamentos->get_result();
while ($row = $result_agendamentos->fetch_assoc()) {
    $agendamentos[] = $row;
}
$stmt_agendamentos->close();

// Notificações
$notificacoes = [];
$sql_lembretes = "SELECT ag.titulo_evento, ag.data_agendada, l.titulo as nome_local 
                  FROM agendamentos ag JOIN locais l ON ag.id_local = l.id_local 
                  WHERE ag.id_usuario = ? AND ag.data_agendada >= NOW() LIMIT 3";
$stmt_lembretes = $conn->prepare($sql_lembretes);
$stmt_lembretes->bind_param("i", $user_id);
$stmt_lembretes->execute();
$res_lembretes = $stmt_lembretes->get_result();
while ($row = $res_lembretes->fetch_assoc()) {
    $notificacoes[] = ['tipo' => 'lembrete', 'titulo' => $row['titulo_evento'], 'local' => $row['nome_local'], 'data' => $row['data_agendada'], 'link' => '#'];
}
$stmt_lembretes->close();
$total_notificacoes = count($notificacoes);

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Calendário - Duo Dates</title>
    <link rel="stylesheet" href="../css/login-feito.css">
    <link rel="stylesheet" href="../css/meu_calendario.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <header class="main-header">
         <div class="header-left">
             <a href="../index.php" class="logo-link">
                 <span class="logo">Duo Dates</span>
                 <img src="../images/logoduodates.png" alt="Logo Duo Dates" class="logo-image">
             </a>
         </div>
         <div class="header-pages">
            <a href="../php/meuslugaresideais.php" title="Meus Lugares"><i class="fas fa-map-marker-alt"></i></a>
            <a href="../php/meu_calendario.php" title="Calendário"><i class="far fa-calendar-alt"></i></a>
            <a href="../php/meus_dates.php" title="Meus Dates"><i class="fas fa-user-friends"></i></a>
            <a href="../php/adicionar_local.php" title="Adicionar Local"><i class="fas fa-plus"></i></a>
         </div>
        <div class="header-icons">
            <a href="../php/favoritos.php"><i class="far fa-heart"></i></a>
            
            <div class="notification-icon-container">
                <i class="far fa-bell <?php echo ($total_notificacoes > 0) ? 'has-notifications' : ''; ?>" id="notification-bell-icon"></i>
                <div class="notification-panel" id="notification-panel">
                    <div class="notification-header">Notificações</div>
                    <div class="notification-list">
                         <?php if (empty($notificacoes)): ?>
                             <div class="notification-item empty">Nenhuma notificação nova.</div>
                         <?php else: ?>
                             <?php foreach ($notificacoes as $notif): ?>
                                 <a href="#" class="notification-item">
                                     <i class="fas fa-calendar-check notification-icon reminder"></i>
                                     <div><strong>Lembrete:</strong> <?php echo htmlspecialchars($notif['titulo']); ?> <small><?php echo date('d/m', strtotime($notif['data'])); ?></small></div>
                                 </a>
                             <?php endforeach; ?>
                         <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="profile-dashboard calendar-layout">
        <aside class="profile-sidebar">
            <div class="user-info">
                <img src="<?php echo htmlspecialchars($profilePhotoUrl); ?>" alt="Foto de Perfil" class="profile-photo" onerror="this.onerror=null; this.src='../images/perfil.png';">
                <h3><?php echo htmlspecialchars($userName); ?></h3>
                <p><?php echo htmlspecialchars($userEmail); ?></p>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item"><a href="../php/login-feito.php"><i class="fas fa-home"></i> <span>Meu Perfil</span></a></li>
                    <li class="nav-item"><a href="../php/mudar_essencia.php"><i class="fas fa-clipboard-list"></i><span> Minha Essência</span></a></li>
                    <li class="nav-item"><a href="../php/meus_dates.php"><i class="fas fa-user-friends"></i> <span>Meus Dates</span></a></li>
                    <li class="nav-item"><a href="../php/editar_perfil.php"><i class="fas fa-user-edit"></i> <span>Editar Perfil</span></a></li>
                    <li class="nav-item"><a href="../php/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Sair</span></a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <section class="section-container">
                <div class="section-header"><h2>MEU CALENDÁRIO DE DATES</h2></div>

                <div id="calendar-container">
                    <div id="calendar"></div>
                </div>
                
                <div class="add-new-section">
                    <p>Para adicionar um novo encontro, explore as categorias e use o botão "Agendar Date" no local desejado.</p>
                    <a href="../php/login-feito.php#explore" class="btn-primary"><i class="fas fa-search-location"></i> Explorar Lugares</a>
                </div>

                <div class="agendamento-list">
                    <?php if (empty($agendamentos)): ?>
                        <div class="empty-state-calendar">
                            <i class="fas fa-calendar-times"></i>
                            <p>Você ainda não tem nenhum date agendado.</p>
                            <span>Que tal explorar alguns lugares e marcar o próximo encontro?</span>
                        </div>
                    <?php else: ?>
                        <h4>Seus Próximos Dates:</h4>
                        <?php setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese'); ?>
                        <?php foreach ($agendamentos as $ag): ?>
                            <div class="agendamento-item">
                                <div class="agendamento-date">
                                    <span class="day"><?php echo date('d', strtotime($ag['data_agendada'])); ?></span>
                                    <span class="month"><?php echo strftime('%b', strtotime($ag['data_agendada'])); ?></span>
                                    <span class="time"><?php echo date('H:i', strtotime($ag['data_agendada'])); ?></span>
                                </div>
                                <div class="agendamento-details">
                                    <span class="event-title"><?php echo htmlspecialchars($ag['titulo_evento']); ?></span>
                                    <span class="event-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ag['nome_local']); ?></span>
                                    <div class="agendamento-actions">

    <?php if(isset($ag['STATUS']) && $ag['STATUS'] == 'aprovado'): ?>
        <a href="confirmar_agendamento.php?id=<?php echo $ag['id_agendamento']; ?>" class="btn-confirm">
            Confirmar
        </a>
    <?php endif; ?>

<a href="javascript:void(0);" 
class="btn-edit"
onclick="abrirModalEditar(
<?php echo $ag['id_agendamento']; ?>,
'<?php echo htmlspecialchars($ag['titulo_evento'], ENT_QUOTES); ?>',
'<?php echo date('Y-m-d\TH:i', strtotime($ag['data_agendada'])); ?>'
)">
Alterar
</a>

    <a href="excluir_agendamento.php?id=<?php echo $ag['id_agendamento']; ?>" 
       onclick="return confirm('Tem certeza que deseja excluir?')" 
       class="btn-delete">
       Excluir
    </a>

</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'pt-br',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,listWeek'
                },
                buttonText: { today: 'Hoje', month: 'Mês', list: 'Lista' },
                events: '../php/buscar_agendamentos.php', 
                handleWindowResize: true,
                dayMaxEventRows: true,
                height: 'auto',
                contentHeight: 'auto'
            });
            calendar.render();

            const bellIconContainer = document.querySelector('.notification-icon-container');
            const notificationPanel = document.getElementById('notification-panel');
            if (bellIconContainer && notificationPanel) {
                bellIconContainer.addEventListener('click', function(event) { event.stopPropagation(); notificationPanel.classList.toggle('show'); });
                document.addEventListener('click', function(event) { if (notificationPanel.classList.contains('show') && !notificationPanel.contains(event.target)) { notificationPanel.classList.remove('show'); } });
            }
        });
    </script>
    <script>
function abrirModalEditar(id, titulo, data) {
    const modal = document.getElementById('modalEditar');
modal.classList.add('ativo');
    
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-titulo').value = titulo;
    document.getElementById('edit-data').value = data;
}

function fecharModal() {
    document.getElementById('modalEditar').style.display = 'none';
}

function salvarEdicao() {
    const id = document.getElementById('edit-id').value;
    const titulo = document.getElementById('edit-titulo').value;
    const data = document.getElementById('edit-data').value;

    fetch('editar_agendamento.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&titulo=${encodeURIComponent(titulo)}&data=${data}`
    })
    .then(() => location.reload());
}
</script>
<!-- MODAL EDITAR -->
<div id="modalEditar" class="modal-overlay">
    <div class="modal-container">

        <div class="modal-header">
            <h3>Editar Date</h3>
            <span class="modal-close" onclick="fecharModal()">&times;</span>
        </div>

        <div class="modal-body">
            <input type="hidden" id="edit-id">

            <div class="form-group">
                <label>Título do encontro</label>
                <input type="text" id="edit-titulo" class="input-field">
            </div>

            <div class="form-group">
                <label>Data e horário</label>
                <input type="datetime-local" id="edit-data" class="input-field">
            </div>
        </div>

<div class="modal-footer">
    <button onclick="salvarEdicao()" class="btn-confirm">
        <i class="fas fa-check"></i> Salvar
    </button>

    <button onclick="fecharModal()" class="btn-delete">
        <i class="fas fa-times"></i> Cancelar
    </button>
</div>

    </div>
</div>
</body>
</html>