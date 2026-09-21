<?php
use App\Helpers\Csrf;
$pageTitle = htmlspecialchars($project['nombre']) . ' · Diseño · Blumi Studio';
$headerTitle = $project['nombre'];
$showNewProjectButton = false;
require dirname(__DIR__) . '/partials/head.php';
require dirname(__DIR__) . '/partials/app_shell_start.php';
?>
<?php foreach (($googleFontUrls ?? []) as $fontUrl): ?><link rel="stylesheet" href="<?= htmlspecialchars($fontUrl) ?>"><?php endforeach; ?>
<?php if (!empty($fontFaceCss)): ?><style><?= str_ireplace('</style', '<\/style', $fontFaceCss) ?></style><?php endif; ?>
<?php
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$canEdit = in_array(($_SESSION['user']['role'] ?? ''), ['superadmin','builder'], true);
$styleTags = $design['style_tags'] ?? [];
$logoPath = !empty($design['logo_ruta']) ? $design['logo_ruta'] : null;
$fontFamilies = [];
foreach ($fonts as $font) { $fontFamilies[$font['nombre']] = true; }
$fontFamilies = array_keys($fontFamilies);
?>

<?php if ($success): ?><div class="toast toast-success"><strong>Listo</strong><span><?= htmlspecialchars($success) ?></span></div><?php endif; ?>
<?php if ($error): ?><div class="toast toast-error"><strong>Revisa esto</strong><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>

<div class="project-heading">
    <div>
        <div class="breadcrumb"><a href="index.php">Proyectos</a><span>/</span><a href="index.php?route=projects.show&id=<?= (int)$project['id'] ?>"><?= htmlspecialchars($project['nombre']) ?></a><span>/</span><strong>Diseño</strong></div>
        <div class="project-title-line">
            <span class="project-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($project['nombre'], 0, 1))) ?></span>
            <div><h2>Identidad del proyecto</h2><p>Colores, tipografías y referencias que usarán todas las páginas.</p></div>
        </div>
    </div>
    <span class="status status-<?= htmlspecialchars($project['estado']) ?>"><?= htmlspecialchars(ucfirst($project['estado'])) ?></span>
</div>

<nav class="project-tabs" aria-label="Secciones del proyecto">
    <a class="project-tab" href="index.php?route=projects.show&id=<?= (int)$project['id'] ?>">Páginas</a>
    <a class="project-tab is-active" href="index.php?route=design.show&id=<?= (int)$project['id'] ?>">Diseño</a>
    <span class="project-tab is-disabled">Assets <small>Próx.</small></span>
    <span class="project-tab is-disabled">SEO <small>Próx.</small></span>
    <span class="project-tab is-disabled">Integraciones <small>Próx.</small></span>
</nav>

<div class="design-layout" data-design-root>
    <main class="design-main">
        <section class="project-panel design-card">
            <div class="panel-head compact-panel-head">
                <div><span class="eyebrow">01 · MARCA</span><h3>Logo y referencias</h3><p>Sube lo que ya tengas. Blumi lo usa como punto de partida, no como una obligación.</p></div>
            </div>
            <div class="brand-assets-grid">
                <div class="brand-drop-card">
                    <div class="brand-drop-preview <?= $logoPath ? 'has-image' : '' ?>" data-palette-source>
                        <?php if ($logoPath): ?><img src="<?= htmlspecialchars($logoPath) ?>" alt="Logo de <?= htmlspecialchars($project['nombre']) ?>" crossorigin="anonymous"><?php else: ?><span class="brand-drop-placeholder">LOGO</span><?php endif; ?>
                    </div>
                    <div class="brand-drop-copy"><strong>Logo principal <span class="required-mark">Obligatorio</span></strong><small>SVG, PNG, JPG o WebP · máximo 10 MB</small></div>
                    <?php if ($canEdit): ?>
                    <form action="index.php?route=design.upload_brand_asset" method="post" enctype="multipart/form-data" class="inline-upload-form">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="proyecto_id" value="<?= (int)$project['id'] ?>"><input type="hidden" name="tipo" value="logo">
                        <label class="button button-secondary button-small file-button">Cambiar logo<input type="file" name="archivo" accept="image/svg+xml,image/png,image/jpeg,image/webp" required data-auto-submit></label>
                    </form>
                    <?php endif; ?>
                </div>

                <div class="brand-drop-card reference-card">
                    <div class="reference-stack">
                        <?php $referenceCount = 0; foreach ($references as $ref): if ($ref['tipo'] !== 'referencia') continue; $referenceCount++; ?>
                            <img src="<?= htmlspecialchars($ref['ruta']) ?>" alt="Referencia visual" title="<?= htmlspecialchars($ref['original_filename']) ?>">
                        <?php endforeach; ?>
                        <?php if ($referenceCount === 0): ?><div class="reference-empty"><span>＋</span><strong>Referencias</strong><small>Web, feed, manual, pieza gráfica...</small></div><?php endif; ?>
                    </div>
                    <?php if ($canEdit): ?>
                    <form action="index.php?route=design.upload_brand_asset" method="post" enctype="multipart/form-data" class="inline-upload-form">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="proyecto_id" value="<?= (int)$project['id'] ?>"><input type="hidden" name="tipo" value="referencia">
                        <label class="button button-secondary button-small file-button">+ Agregar referencia<input type="file" name="archivo" accept="image/svg+xml,image/png,image/jpeg,image/webp" required data-auto-submit></label>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($logoPath): ?>
            <div class="palette-helper">
                <div><strong>¿Quieres partir del logo?</strong><span>Blumi puede detectar una paleta aproximada directamente en tu navegador. Tú decides si aplicarla.</span></div>
                <button class="button button-secondary button-small" type="button" data-extract-palette>Detectar colores</button>
                <div class="detected-palette" data-detected-palette></div>
            </div>
            <?php endif; ?>
        </section>

        <form action="index.php?route=design.update" method="post" class="design-form" data-design-form>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="proyecto_id" value="<?= (int)$project['id'] ?>">

            <section class="project-panel design-card">
                <div class="panel-head compact-panel-head"><div><span class="eyebrow">02 · COLORES</span><h3>Paleta del sitio</h3><p>Usa roles claros: principal, secundario, acento, fondo y texto.</p></div></div>
                <div class="color-token-grid">
                    <?php
                    $colorFields = [
                        'primary_color'=>'Principal','secondary_color'=>'Secundario','accent_color'=>'Acento',
                        'background_color'=>'Fondo','surface_color'=>'Superficie','text_color'=>'Texto','muted_color'=>'Texto suave'
                    ];
                    foreach ($colorFields as $field => $label): ?>
                        <label class="color-token" data-color-token="<?= htmlspecialchars($field) ?>">
                            <span><?= htmlspecialchars($label) ?></span>
                            <div><input type="color" value="<?= htmlspecialchars($design[$field]) ?>" data-color-picker="<?= htmlspecialchars($field) ?>" <?= !$canEdit ? 'disabled' : '' ?>><input type="text" name="<?= htmlspecialchars($field) ?>" value="<?= htmlspecialchars($design[$field]) ?>" maxlength="7" data-color-text="<?= htmlspecialchars($field) ?>" <?= !$canEdit ? 'disabled' : '' ?>></div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="project-panel design-card">
                <div class="panel-head compact-panel-head"><div><span class="eyebrow">03 · TIPOGRAFÍA</span><h3>Fuentes</h3><p>Elige fuentes conocidas, pega una URL de Google Fonts o usa archivos WOFF/WOFF2 propios.</p></div></div>
                <div class="type-grid">
                    <?php foreach ([['heading','Títulos','heading_font','heading_font_source','heading_font_url'],['body','Texto','body_font','body_font_source','body_font_url']] as $fontGroup): [$prefix,$label,$nameKey,$sourceKey,$urlKey]=$fontGroup; ?>
                    <div class="type-card">
                        <span class="eyebrow"><?= htmlspecialchars(strtoupper($label)) ?></span>
                        <label class="field"><span>Fuente</span><input type="text" name="<?= $nameKey ?>" value="<?= htmlspecialchars($design[$nameKey]) ?>" list="font-options" data-font-name="<?= $prefix ?>" <?= !$canEdit ? 'disabled' : '' ?>></label>
                        <label class="field"><span>Origen</span><select name="<?= $sourceKey ?>" data-font-source="<?= $prefix ?>" <?= !$canEdit ? 'disabled' : '' ?>><option value="system" <?= $design[$sourceKey]==='system'?'selected':'' ?>>Sistema</option><option value="google" <?= $design[$sourceKey]==='google'?'selected':'' ?>>Google Fonts</option><option value="local" <?= $design[$sourceKey]==='local'?'selected':'' ?>>Fuente subida</option></select></label>
                        <label class="field google-font-field" data-google-field="<?= $prefix ?>"><span>URL CSS de Google Fonts</span><input type="url" name="<?= $urlKey ?>" value="<?= htmlspecialchars((string)($design[$urlKey] ?? '')) ?>" placeholder="https://fonts.googleapis.com/css2?family=..." <?= !$canEdit ? 'disabled' : '' ?>><small>Solo aceptamos URLs oficiales de Google Fonts.</small></label>
                        <div class="font-sample" data-font-preview="<?= $prefix ?>" style="font-family:'<?= htmlspecialchars($design[$nameKey]) ?>',sans-serif">Aa · Blumi crea sitios claros</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <datalist id="font-options">
                    <?php foreach ($googleFonts as $font): ?><option value="<?= htmlspecialchars($font) ?>"></option><?php endforeach; ?>
                    <?php foreach ($fontFamilies as $font): ?><option value="<?= htmlspecialchars($font) ?>"></option><?php endforeach; ?>
                </datalist>

                <?php if ($fonts): ?>
                <div class="font-library"><strong>Fuentes locales disponibles</strong><div class="font-chips"><?php foreach ($fonts as $font): ?><span class="font-chip"><?= htmlspecialchars($font['nombre']) ?> <small><?= (int)$font['peso'] ?><?= $font['estilo']==='italic'?' italic':'' ?></small></span><?php endforeach; ?></div></div>
                <?php endif; ?>

                <?php if ($canEdit): ?>
                <details class="advanced-options font-upload-details">
                    <summary>Subir fuente propia (.woff2 / .woff)</summary>
                    <div class="advanced-options-body">
                        <p class="helper-copy">Úsala solo si tienes derecho/licencia para alojarla en el sitio.</p>
                        <div class="font-upload-placeholder">El formulario de subida está debajo para no mezclarlo con el guardado general.</div>
                    </div>
                </details>
                <?php endif; ?>
            </section>

            <section class="project-panel design-card">
                <div class="panel-head compact-panel-head"><div><span class="eyebrow">04 · ESTILO</span><h3>Personalidad visual</h3><p>Estas etiquetas servirán después para recomendar componentes antes de generar otros nuevos.</p></div></div>
                <div class="style-tags-grid">
                    <?php foreach (['minimal'=>'Minimal','editorial'=>'Editorial','bold'=>'Bold','playful'=>'Playful','soft'=>'Suave','corporate'=>'Corporativo','luxury'=>'Premium','technical'=>'Técnico','organic'=>'Orgánico','dark'=>'Oscuro'] as $value=>$label): ?>
                    <label class="style-tag"><input type="checkbox" name="style_tags[]" value="<?= $value ?>" <?= in_array($value,$styleTags,true)?'checked':'' ?> <?= !$canEdit ? 'disabled' : '' ?>><span><?= htmlspecialchars($label) ?></span></label>
                    <?php endforeach; ?>
                </div>
                <div class="advanced-design-grid">
                    <label class="field"><span>Radio general</span><select name="border_radius" <?= !$canEdit ? 'disabled' : '' ?>><option value="0px" <?= $design['border_radius']==='0px'?'selected':'' ?>>Recto</option><option value="8px" <?= $design['border_radius']==='8px'?'selected':'' ?>>Sutil</option><option value="12px" <?= $design['border_radius']==='12px'?'selected':'' ?>>Suave</option><option value="20px" <?= $design['border_radius']==='20px'?'selected':'' ?>>Redondeado</option><option value="28px" <?= $design['border_radius']==='28px'?'selected':'' ?>>Muy redondeado</option></select></label>
                    <label class="field"><span>Ancho máximo</span><select name="container_width" <?= !$canEdit ? 'disabled' : '' ?>><option value="1120px" <?= $design['container_width']==='1120px'?'selected':'' ?>>1120 px</option><option value="1200px" <?= $design['container_width']==='1200px'?'selected':'' ?>>1200 px</option><option value="1280px" <?= $design['container_width']==='1280px'?'selected':'' ?>>1280 px</option><option value="1440px" <?= $design['container_width']==='1440px'?'selected':'' ?>>1440 px</option><option value="1600px" <?= $design['container_width']==='1600px'?'selected':'' ?>>1600 px</option><option value="1760px" <?= $design['container_width']==='1760px'?'selected':'' ?>>1760 px</option></select><small>Blumi adapta automáticamente tipografía, espacios y gutters en 1920px y 2560px.</small></label>
                    <label class="field"><span>Espaciado de sección</span><select name="section_spacing" <?= !$canEdit ? 'disabled' : '' ?>><option value="64px" <?= $design['section_spacing']==='64px'?'selected':'' ?>>Compacto</option><option value="80px" <?= $design['section_spacing']==='80px'?'selected':'' ?>>Medio</option><option value="96px" <?= $design['section_spacing']==='96px'?'selected':'' ?>>Amplio</option><option value="120px" <?= $design['section_spacing']==='120px'?'selected':'' ?>>Editorial</option></select></label>
                </div>
            </section>

            <?php if ($canEdit): ?><div class="sticky-save-bar"><div><strong>Identidad del proyecto</strong><span>Los cambios afectarán los componentes que usan tokens globales.</span></div><button class="button button-primary" type="submit">Guardar identidad</button></div><?php endif; ?>
        </form>

        <?php if ($canEdit): ?>
        <section class="project-panel design-card font-upload-section">
            <div class="panel-head compact-panel-head"><div><span class="eyebrow">FUENTES LOCALES</span><h3>Agregar archivo webfont</h3><p>WOFF2 es la opción recomendada. Puedes subir varios pesos de la misma familia.</p></div></div>
            <form action="index.php?route=design.upload_font" method="post" enctype="multipart/form-data" class="font-upload-form">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="proyecto_id" value="<?= (int)$project['id'] ?>">
                <label class="field"><span>Nombre de la familia</span><input type="text" name="nombre" placeholder="Neue Montreal" required></label>
                <label class="field"><span>Peso</span><select name="peso"><option value="400">400 Regular</option><option value="500">500 Medium</option><option value="600">600 Semibold</option><option value="700">700 Bold</option><option value="800">800 ExtraBold</option></select></label>
                <label class="field"><span>Estilo</span><select name="estilo"><option value="normal">Normal</option><option value="italic">Italic</option></select></label>
                <label class="field"><span>Archivo</span><input type="file" name="archivo" accept=".woff2,.woff,font/woff2,font/woff" required></label>
                <button class="button button-secondary" type="submit">Subir fuente</button>
            </form>
        </section>
        <?php endif; ?>
    </main>

    <aside class="design-preview-panel">
        <div class="design-preview-sticky">
            <span class="eyebrow">VISTA PREVIA</span>
            <div class="brand-live-preview" data-brand-preview style="--p:<?= htmlspecialchars($design['primary_color']) ?>;--s:<?= htmlspecialchars($design['secondary_color']) ?>;--a:<?= htmlspecialchars($design['accent_color']) ?>;--bg:<?= htmlspecialchars($design['background_color']) ?>;--surface:<?= htmlspecialchars($design['surface_color']) ?>;--text:<?= htmlspecialchars($design['text_color']) ?>;--muted:<?= htmlspecialchars($design['muted_color']) ?>;--radius:<?= htmlspecialchars($design['border_radius']) ?>;font-family:'<?= htmlspecialchars($design['body_font']) ?>',sans-serif">
                <div class="preview-mini-nav"><?php if ($logoPath): ?><img class="preview-mini-logo" src="<?= htmlspecialchars($logoPath) ?>" alt="Logo <?= htmlspecialchars($project['nombre']) ?>"><?php else: ?><strong><?= htmlspecialchars($project['nombre']) ?></strong><?php endif; ?><span>Inicio</span><span>Servicios</span><b>Contacto</b></div>
                <div class="preview-mini-hero">
                    <small>NUEVA MARCA</small>
                    <h2 data-preview-heading style="font-family:'<?= htmlspecialchars($design['heading_font']) ?>',sans-serif">Una identidad consistente desde el primer componente.</h2>
                    <p>Colores y fuentes se aplican como tokens del proyecto, no como estilos sueltos.</p>
                    <button type="button">Botón principal</button>
                </div>
                <div class="preview-mini-cards"><div></div><div></div><div></div></div>
            </div>
            <div class="preview-token-summary">
                <div><span>Principal</span><i style="background:<?= htmlspecialchars($design['primary_color']) ?>"></i><code data-summary-primary><?= htmlspecialchars($design['primary_color']) ?></code></div>
                <div><span>Títulos</span><strong data-summary-heading><?= htmlspecialchars($design['heading_font']) ?></strong></div>
                <div><span>Texto</span><strong data-summary-body><?= htmlspecialchars($design['body_font']) ?></strong></div>
            </div>
            <?php if ($pages): ?><a class="button button-secondary button-full" href="index.php?route=builder&page_id=<?= (int)$pages[0]['id'] ?>">Ver en el builder</a><?php else: ?><a class="button button-secondary button-full" href="index.php?route=projects.show&id=<?= (int)$project['id'] ?>">Crear primera página</a><?php endif; ?>
        </div>
    </aside>
</div>

<script>window.BLUMI_LOCAL_FONTS = <?= json_encode($fonts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require dirname(__DIR__) . '/partials/app_shell_end.php'; ?>
