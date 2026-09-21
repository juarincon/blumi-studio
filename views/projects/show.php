<?php
use App\Helpers\Csrf;
$pageTitle = htmlspecialchars($project['nombre']) . ' · Blumi Studio';
$headerTitle = $project['nombre'];
$showNewProjectButton = false;
require dirname(__DIR__) . '/partials/head.php';
require dirname(__DIR__) . '/partials/app_shell_start.php';
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<?php if ($success): ?><div class="toast toast-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="toast toast-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="project-heading">
    <div>
        <div class="breadcrumb"><a href="index.php">Proyectos</a><span>/</span><strong><?= htmlspecialchars($project['nombre']) ?></strong></div>
        <div class="project-title-line">
            <span class="project-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($project['nombre'], 0, 1))) ?></span>
            <div>
                <h2><?= htmlspecialchars($project['nombre']) ?></h2>
                <p><?= htmlspecialchars($project['dominio'] ?: 'Dominio todavía no configurado') ?> · <?= htmlspecialchars($project['codigo']) ?></p>
            </div>
        </div>
    </div>
    <span class="status status-<?= htmlspecialchars($project['estado']) ?>"><?= htmlspecialchars(ucfirst($project['estado'])) ?></span>
</div>

<nav class="project-tabs" aria-label="Secciones del proyecto">
    <a class="project-tab is-active" href="#">Páginas</a>
    <a class="project-tab" href="index.php?route=design.show&id=<?= (int)$project['id'] ?>">Diseño</a>
    <span class="project-tab is-disabled">Assets <small>Próx.</small></span>
    <span class="project-tab is-disabled">SEO <small>Próx.</small></span>
    <span class="project-tab is-disabled">Integraciones <small>Próx.</small></span>
</nav>

<div class="project-panel">
    <div class="panel-head">
        <div>
            <span class="eyebrow">ESTRUCTURA DEL SITIO</span>
            <h3>Páginas</h3>
            <p>Cada página tendrá sus propias instancias de componentes y contenido.</p>
        </div>
        <?php if (($_SESSION['user']['role'] ?? '') !== 'cliente'): ?>
            <button class="button button-primary" type="button" data-open-modal="page-modal">+ Nueva página</button>
        <?php endif; ?>
    </div>

    <?php if (!$pages): ?>
        <div class="project-empty">
            <span class="empty-page-icon">□</span>
            <h4>Aún no hay páginas.</h4>
            <p>Crea la primera. Automáticamente se definirá como página de inicio.</p>
            <?php if (($_SESSION['user']['role'] ?? '') !== 'cliente'): ?>
                <button class="button button-primary" type="button" data-open-modal="page-modal">Crear página de inicio</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="pages-table-wrap">
            <table class="pages-table">
                <thead>
                    <tr>
                        <th>Página</th>
                        <th>Ruta</th>
                        <th>Estado</th>
                        <th>Actualización</th>
                        <th class="table-actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td>
                            <div class="page-name-cell">
                                <span class="page-file-icon">▱</span>
                                <div>
                                    <strong><?= htmlspecialchars($page['nombre']) ?></strong>
                                    <?php if ((int)$page['es_inicio'] === 1): ?><span class="mini-badge">Inicio</span><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><code>/<?= htmlspecialchars($page['slug']) ?></code></td>
                        <td><span class="status status-<?= htmlspecialchars($page['estado']) ?>"><?= htmlspecialchars(ucfirst($page['estado'])) ?></span></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($page['updated_at']))) ?></td>
                        <td class="table-actions">
                            <a class="button button-secondary button-small" href="index.php?route=builder&page_id=<?= (int)$page['id'] ?>">Abrir builder</a>
                            <?php if (($_SESSION['user']['role'] ?? '') !== 'cliente' && !(int)$page['es_inicio']): ?>
                                <form method="post" action="index.php?route=pages.archive" data-confirm="¿Archivar esta página? No se borrará físicamente.">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                                    <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                                    <input type="hidden" name="proyecto_id" value="<?= (int)$project['id'] ?>">
                                    <button class="icon-button" type="submit" title="Archivar">×</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-backdrop" id="page-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="new-page-title">
        <div class="modal-head">
            <div>
                <span class="eyebrow">NUEVA PÁGINA</span>
                <h2 id="new-page-title">Agregar página</h2>
            </div>
            <button class="icon-button" type="button" data-close-modal>×</button>
        </div>
        <form method="post" action="index.php?route=pages.create" class="modal-body form-stack">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="proyecto_id" value="<?= (int)$project['id'] ?>">
            <label class="field">
                <span>¿Cómo se llama la página? *</span>
                <input type="text" name="nombre" placeholder="Ej. Servicios" required autofocus>
                <small>Blumi se encarga automáticamente de crear una dirección válida para esta página.</small>
            </label>

            <details class="advanced-options">
                <summary>Opciones avanzadas</summary>
                <div class="advanced-options-body">
                    <label class="field">
                        <span>Dirección personalizada <em>opcional</em></span>
                        <input type="text" name="slug" placeholder="servicios">
                        <small>Puedes escribirla como quieras; Blumi quitará espacios, tildes y caracteres no válidos. Si ya existe, agregará -2, -3, etc.</small>
                    </label>

                </div>
            </details>
            <div class="modal-actions">
                <button class="button button-secondary" type="button" data-close-modal>Cancelar</button>
                <button class="button button-primary" type="submit">Crear y abrir builder</button>
            </div>
        </form>
    </div>
</div>

<?php require dirname(__DIR__) . '/partials/app_shell_end.php'; ?>
