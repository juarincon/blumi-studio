<?php
use App\Helpers\Csrf;
$headerTitle = 'Actualizaciones';
$showNewProjectButton = false;
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/app_shell_start.php';
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<div class="update-layout">
    <?php if ($flashSuccess): ?><div class="update-notice is-success"><?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="update-notice is-error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>

    <section class="panel update-card">
        <span class="eyebrow">MANTENIMIENTO</span>
        <h2>Instalar un fix</h2>
        <p>Sube el ZIP que te entregue Blumi. La herramienta detecta las rutas y reemplaza los archivos automáticamente.</p>
        <div class="update-root"><span>Raíz detectada</span><code><?= htmlspecialchars($root) ?></code></div>
        <form action="index.php?route=updater.preview" method="post" enctype="multipart/form-data" class="update-upload-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <label class="update-drop">
                <strong>Paquete ZIP</strong>
                <span>Máximo 20 MB · app/, public/, views/ y config/</span>
                <input type="file" name="package" accept=".zip,application/zip" required>
            </label>
            <button class="button button-primary" type="submit">Revisar paquete</button>
        </form>
    </section>

    <?php if (!empty($preview)): ?>
    <section class="panel update-card">
        <div class="update-review-head">
            <div><span class="eyebrow">VISTA PREVIA</span><h2><?= htmlspecialchars($preview['original_name'] ?? 'Paquete') ?></h2></div>
            <span class="update-count"><?= count($preview['files'] ?? []) ?> archivo(s)</span>
        </div>
        <div class="update-file-list">
            <?php foreach (($preview['files'] ?? []) as $file): ?>
                <div class="update-file-row">
                    <code><?= htmlspecialchars($file['path']) ?></code>
                    <span class="update-status <?= ($file['status'] ?? '') === 'agregar' ? 'is-add' : '' ?>"><?= htmlspecialchars($file['status']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="update-warning">Antes de reemplazar, Blumi crea un backup ZIP automático de todos los archivos existentes afectados.</div>
        <form action="index.php?route=updater.apply" method="post">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($preview['token'] ?? '') ?>">
            <button class="button button-primary" type="submit">Aplicar actualización</button>
        </form>
    </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../partials/app_shell_end.php'; ?>
