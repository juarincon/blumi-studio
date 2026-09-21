<?php
use App\Helpers\Csrf;
$user = $_SESSION['user'] ?? [];
$route = trim((string)($_GET['route'] ?? ''));
$isComponentsArea = $route === 'component_factory'
    || str_starts_with($route, 'component_factory.')
    || $route === 'component_library'
    || str_starts_with($route, 'component_library.');
$isUpdaterArea = $route === 'updater' || str_starts_with($route, 'updater.');
$isProjectsArea = !$isComponentsArea && !$isUpdaterArea;
?>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand-mark">
            <span class="brand-word">Blumi</span>
            <span class="brand-sub">Studio</span>
        </div>
        <nav class="sidebar-nav" aria-label="Principal">
            <a class="nav-item <?= $isProjectsArea ? 'is-active' : '' ?>" href="index.php" <?= $isProjectsArea ? 'aria-current="page"' : '' ?>>
                <span class="nav-icon">▣</span><span>Proyectos</span>
            </a>
            <a class="nav-item <?= $isComponentsArea ? 'is-active' : '' ?>" href="index.php?route=component_factory" <?= $isComponentsArea ? 'aria-current="page"' : '' ?>><span class="nav-icon">◇</span><span>Componentes</span></a>
            <span class="nav-item is-disabled"><span class="nav-icon">▧</span><span>Templates</span><small>Próx.</small></span>
            <span class="nav-item is-disabled"><span class="nav-icon">□</span><span>Assets</span><small>Próx.</small></span>
            <?php if (($user['role'] ?? '') === 'superadmin'): ?>
                <a class="nav-item <?= $isUpdaterArea ? 'is-active' : '' ?>" href="index.php?route=updater" <?= $isUpdaterArea ? 'aria-current="page"' : '' ?>><span class="nav-icon">↻</span><span>Actualizar</span></a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <span class="user-badge"><?= htmlspecialchars(mb_substr($user['name'] ?? 'U', 0, 1)) ?></span>
            <div class="user-copy">
                <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong>
                <span><?= htmlspecialchars($user['role'] ?? '') ?></span>
            </div>
            <form action="index.php?route=logout" method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <button class="icon-button" type="submit" title="Cerrar sesión">↗</button>
            </form>
        </div>
    </aside>
    <main class="main-area">
        <header class="topbar">
            <div>
                <span class="eyebrow">BLUMI STUDIO</span>
                <h1><?= htmlspecialchars($headerTitle ?? 'Proyectos') ?></h1>
            </div>
            <div class="topbar-actions">
                <?php if (($showNewProjectButton ?? true) && ($user['role'] ?? '') !== 'cliente'): ?>
                    <button class="button button-primary" type="button" data-open-modal="project-modal">+ Nuevo proyecto</button>
                <?php endif; ?>
            </div>
        </header>
        <div class="workspace-grid"></div>
        <section class="content-area">
