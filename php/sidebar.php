<?php
// A variável $view precisa ser definida ANTES de incluir este arquivo.
?>
<aside class="sidebar">
    <div class="sidebar-header"><h2>DuoDates Admin</h2></div>
    <nav class="sidebar-nav">
        <a href="../php/admin.php?view=dashboard" class="nav-item <?php if ($view == 'dashboard') echo 'active'; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="../php/admin.php?view=pendentes" class="nav-item <?php if (in_array($view, ['pendentes', 'detalhe_pendente'])) echo 'active'; ?>">
            <i class="fas fa-hourglass-half"></i> Locais Pendentes
        </a>
        <a href="../php/admin.php?view=usuarios" class="nav-item <?php if ($view == 'usuarios') echo 'active'; ?>">
            <i class="fas fa-users"></i> Gerenciar Usuários
        </a>
        <a href="../php/admin.php?view=locais" class="nav-item <?php if ($view == 'locais') echo 'active'; ?>">
            <i class="fas fa-map-marker-alt"></i> Gerenciar Locais
        </a>

        <a href="../php/admin.php?view=tags" class="nav-item <?php if ($view == 'tags') echo 'active'; ?>">
            <i class="fas fa-tags"></i> Gerenciar Tags
        </a>

        <a href="../php/admin.php?view=agendamentos" class="nav-item <?php if ($view == 'agendamentos') echo 'active'; ?>">
            <i class="fas fa-calendar-alt"></i> Ver Agendamentos
        </a>
        <a href="../php/adicionar-lugar.php" class="nav-item">
            <i class="fas fa-plus-circle"></i> Adicionar Local
        </a>
        
        <a href="../php/admin.php?view=categorias" class="nav-item <?php if ($view == 'categorias') echo 'active'; ?>">
            <i class="fas fa-list-alt"></i> Gerenciar Categorias
        </a>
        <a href="../index.php" class="nav-item">
            <i class="fas fa-globe"></i> Ver Site
        </a>        
    </nav>
</aside>