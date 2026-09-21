<?php
use App\Helpers\Csrf;
$pageTitle = 'Proyectos · Blumi Studio';
$headerTitle = 'Proyectos';
require dirname(__DIR__) . '/partials/head.php';
require dirname(__DIR__) . '/partials/app_shell_start.php';
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<?php if ($success): ?><div class="toast toast-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="toast toast-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="section-head">
    <div>
        <h2>Tus proyectos</h2>
        <p>La base del builder: proyectos independientes, configurables y reutilizables.</p>
    </div>
    <div class="filter-pills">
        <button class="filter-pill is-active" type="button">Todos</button>
        <button class="filter-pill" type="button">En desarrollo</button>
        <button class="filter-pill" type="button">Publicados</button>
    </div>
</div>

<?php if (!$projects): ?>
    <div class="empty-state">
        <div class="empty-symbol">B</div>
        <h3>Tu workspace está listo.</h3>
        <p>Crea el primer proyecto y empezaremos a montar páginas y componentes encima de esta base.</p>
        <?php if (($_SESSION['user']['role'] ?? '') !== 'cliente'): ?>
            <button class="button button-primary" type="button" data-open-modal="project-modal">Crear primer proyecto</button>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="project-grid">
        <?php foreach ($projects as $project): ?>
            <article class="project-card">
                <div class="project-preview">
                    <div class="preview-grid"></div>
                    <span class="preview-brand"><?= htmlspecialchars(mb_strtoupper(mb_substr($project['nombre'], 0, 1))) ?></span>
                </div>
                <div class="project-body">
                    <div class="project-meta-row">
                        <span class="status status-<?= htmlspecialchars($project['estado']) ?>"><?= htmlspecialchars(ucfirst($project['estado'])) ?></span>
                        <span class="project-code"><?= htmlspecialchars($project['codigo']) ?></span>
                    </div>
                    <h3><?= htmlspecialchars($project['nombre']) ?></h3>
                    <p><?= htmlspecialchars($project['dominio'] ?: 'Dominio pendiente') ?></p>
                    <div class="project-footer">
                        <span><?= htmlspecialchars($project['propietario_nombre'] ?: 'Sin propietario') ?></span>
                        <a class="button button-secondary button-small" href="index.php?route=projects.show&id=<?= (int)$project['id'] ?>">Entrar</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="modal-backdrop" id="project-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="new-project-title">
        <div class="modal-head">
            <div>
                <span class="eyebrow">NUEVO PROYECTO</span>
                <h2 id="new-project-title">Crear proyecto</h2>
            </div>
            <button class="icon-button" type="button" data-close-modal>×</button>
        </div>
        <form method="post" action="index.php?route=projects.create" class="modal-body form-stack">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <label class="field">
                <span>Nombre *</span>
                <input type="text" name="nombre" placeholder="Only Papas" required>
            </label>
            <details class="advanced-options">
                <summary>Opciones avanzadas</summary>
                <div class="advanced-options-body">
                    <label class="field">
                        <span>Dirección interna <em>opcional</em></span>
                        <input type="text" name="slug" placeholder="only-papas">
                        <small>Si no la escribes, Blumi la crea automáticamente y evita duplicados.</small>
                    </label>
                    <label class="field">
                        <span>Descripción interna</span>
                        <textarea name="descripcion" rows="3" placeholder="Notas del proyecto"></textarea>
                    </label>
                </div>
            </details>
            <label class="field">
                <span>Dominio</span>
                <input type="text" name="dominio" placeholder="onlypapas.com">
            </label>
            <div class="modal-actions">
                <button class="button button-secondary" type="button" data-close-modal>Cancelar</button>
                <button class="button button-primary" type="submit">Crear y definir identidad</button>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/partials/app_shell_end.php'; ?>
