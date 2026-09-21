<?php
use App\Helpers\Csrf;
$pageTitle = htmlspecialchars($page['nombre']) . ' · Builder · Blumi Studio';
require dirname(__DIR__) . '/partials/head.php';
$user = $_SESSION['user'] ?? [];
$role = (string)($user['role'] ?? 'cliente');
$canBuild = in_array($role, ['superadmin', 'builder'], true);
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$selectedId = (int)($selectedInstance['id'] ?? 0);
$selectedSchema = is_array($selectedInstance['schema'] ?? null) ? $selectedInstance['schema'] : [];
$selectedContent = is_array($selectedInstance['content'] ?? null) ? $selectedInstance['content'] : [];
$selectedLinkMeta = is_array($selectedContent['__blumi_links'] ?? null) ? $selectedContent['__blumi_links'] : [];
$currentPageSections = is_array($linkSectionsByPage[(int)$page['id']] ?? null) ? $linkSectionsByPage[(int)$page['id']] : [];
$selectedStyles = is_array($selectedInstance['styles'] ?? null) ? $selectedInstance['styles'] : [];
$selectedStyleControls = is_array($selectedInstance['style_controls'] ?? null) ? $selectedInstance['style_controls'] : [];
$selectedVisibilityControls = is_array($selectedInstance['visibility_controls'] ?? null) ? $selectedInstance['visibility_controls'] : [];
$selectedCategory = strtolower((string)($selectedInstance['categoria_slug'] ?? ''));
$selectedIsGlobal = in_array($selectedCategory, ['navbar','footer'], true);
$personalizationTargets = is_array($personalizationTargets ?? null) ? array_values(array_filter($personalizationTargets, 'is_string')) : [];
$selectedHasCustomization = !empty($selectedInstance['customization']['targets'] ?? []);
$bodyInstanceCount = count(array_filter($instances ?? [], static fn(array $row): bool => empty($row['is_global_layout'])));
// Controles universales del inspector. No dependemos del schema histórico del componente:
// cualquier instancia puede elegir su modo de ancho sin regenerarse.
if ($selectedInstance) {
    $universalInspectorControls = [
        'section_background' => ['type'=>'color','label'=>'Color lateral / fondo de sección'],
        'surface_color' => ['type'=>'color','label'=>'Fondo del componente'],
        'heading_color' => ['type'=>'color','label'=>'Títulos'],
        'text_color' => ['type'=>'color','label'=>'Texto general'],
        'layout_width' => ['type'=>'layout_width','label'=>'Ancho del componente'],
    ];
    if ($selectedCategory === 'navbar') {
        $universalInspectorControls['navbar_position'] = ['type'=>'navbar_position','label'=>'Posición del navbar'];
    }
    // Los controles de layout pertenecen a Blumi y deben prevalecer aunque el componente
    // tenga un schema antiguo o generado por IA sin estos controles.
    $selectedStyleControls = array_merge($selectedStyleControls, $universalInspectorControls);
}
$hiddenFields = array_values(array_filter(is_array($selectedStyles['hidden_fields'] ?? null) ? $selectedStyles['hidden_fields'] : [], 'is_string'));
$backgroundMode = (string)($selectedStyles['background_mode'] ?? 'color');
$backgroundImage = (string)($selectedStyles['background_image'] ?? '');
if ($selectedInstance && !$selectedStyleControls && $canBuild ?? false) {
    $categorySlug = strtolower((string)($selectedInstance['categoria_slug'] ?? ''));
    if ($categorySlug === 'navbar') {
        $selectedStyleControls = [
            'section_background' => ['type'=>'color','label'=>'Color lateral / fondo de sección'],
            'surface_color' => ['type'=>'color','label'=>'Fondo del componente'],
            'link_color' => ['type'=>'color','label'=>'Enlaces'],
            'active_link_color' => ['type'=>'color','label'=>'Enlace activo'],
            'heading_color' => ['type'=>'color','label'=>'Títulos'],
            'button_background' => ['type'=>'color','label'=>'Fondo CTA'],
            'button_text' => ['type'=>'color','label'=>'Texto CTA'],
            'section_radius' => ['type'=>'radius','label'=>'Radio del navbar'],
            'button_radius' => ['type'=>'radius','label'=>'Radio CTA'],
            'section_shadow' => ['type'=>'shadow','label'=>'Sombra del navbar'],
            'layout_width' => ['type'=>'layout_width','label'=>'Ancho del componente'],
            'navbar_position' => ['type'=>'navbar_position','label'=>'Posición del navbar'],
            'shadow_direction' => ['type'=>'shadow_direction','label'=>'Dirección de la sombra'],
            'shadow_opacity' => ['type'=>'shadow_opacity','label'=>'Opacidad de la sombra'],
        ];
    }
}
$safeComponentCss = str_ireplace('</style', '<\\/style', (string)$componentCss);
$safeFontCss = str_ireplace('</style', '<\\/style', (string)($projectFontCss ?? ''));
$safeComponentJs = str_ireplace('</script', '<\/script', (string)($componentJs ?? ''));
$responsiveFontLinks = '';
foreach (($googleFontUrls ?? []) as $fontUrl) {
    $responsiveFontLinks .= '<link rel="stylesheet" href="' . htmlspecialchars((string)$fontUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
}
$responsiveBody = '';
foreach ($instances as $instance) {
    $responsiveBody .= '<section id="blumi-section-' . (int)$instance['id'] . '" class="bl-page-component" data-blumi-instance="' . (int)$instance['id'] . '" data-blumi-category="' . htmlspecialchars((string)($instance['categoria_slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><div class="bl-section-background"><div class="bl-site-frame"><div class="bl-component-surface"><div class="bl-component-stage">' . (string)$instance['rendered_html'] . '</div></div></div></div></section>';
}
$responsiveThemeCss = ':root{' .
    '--site-primary:' . ($design['primary_color'] ?? '#2043BF') . ';' .
    '--site-secondary:' . ($design['secondary_color'] ?? '#3974DC') . ';' .
    '--site-accent:' . ($design['accent_color'] ?? '#E7DF68') . ';' .
    '--site-background:' . ($design['background_color'] ?? '#FFFFFF') . ';' .
    '--site-surface:' . ($design['surface_color'] ?? '#FFFFFF') . ';' .
    '--site-text:' . ($design['text_color'] ?? '#1D1D1F') . ';' .
    '--site-muted:' . ($design['muted_color'] ?? '#71717A') . ';' .
    '--site-radius:' . ($design['border_radius'] ?? '12px') . ';' .
    '--site-container-normal:' . ($design['container_width'] ?? '1280px') . ';' .
    '--site-container-wide:1600px;' .
    '--site-container:var(--site-container-normal);' .
    '--site-section-space-base:' . ($design['section_spacing'] ?? '96px') . ';' .
    '--site-section-space:var(--site-section-space-base);' .
    '--site-gutter:32px;' .
    '--site-font-heading:\'' . ($design['heading_font'] ?? 'Inter') . '\',sans-serif;' .
    '--site-font-body:\'' . ($design['body_font'] ?? 'Inter') . '\',sans-serif;' .
    '--font-display-xl:clamp(72px,4.3vw,108px);--font-display:clamp(60px,3.55vw,88px);--font-h1:clamp(48px,2.9vw,72px);--font-h2:clamp(40px,2.4vw,60px);--font-h3:clamp(32px,1.9vw,48px);--font-h4:clamp(24px,1.45vw,34px);--font-body-lg:clamp(18px,.95vw,20px);--font-body:clamp(16px,.85vw,18px);--font-small:clamp(14px,.74vw,15px);--font-caption:12px;' .
    '--space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;--space-5:20px;--space-6:24px;--space-8:clamp(32px,2vw,44px);--space-10:clamp(40px,2.4vw,52px);--space-12:clamp(48px,2.8vw,60px);--space-16:clamp(64px,3.6vw,80px);--space-20:clamp(80px,4.4vw,96px);--space-24:clamp(96px,5.2vw,120px);' .
    '--radius-sm:8px;--radius-md:12px;--radius-lg:16px;--radius-xl:20px;--radius-pill:999px;}' .
    '*{box-sizing:border-box}html,body{margin:0;padding:0;width:100%;min-height:100%;background:var(--site-background);color:var(--site-text);font-family:var(--site-font-body)}' .
    'h1,h2,h3,h4,h5,h6{font-family:var(--site-font-heading)}' .
    '.bl-page-component,.bl-section-background,.bl-component-surface,.bl-component-stage{width:100%;max-width:none;margin:0;min-width:0}' .
    '.bl-section-background{background:var(--site-background)}' .
    '.bl-site-frame{width:100%;max-width:calc(var(--site-container) + (var(--site-gutter) * 2));margin:0 auto;padding-inline:var(--site-gutter);min-width:0}' .
    '.bl-component-stage>*{width:100%!important;max-width:none!important;margin:0!important;min-width:0}' .
    '@media(min-width:1600px){:root{--site-gutter:48px;--site-section-space:calc(var(--site-section-space-base) + 8px)}}@media(min-width:1900px){:root{--site-container-wide:1760px;--site-gutter:56px;--site-section-space:calc(var(--site-section-space-base) + 16px)}}@media(min-width:2400px){:root{--site-container-wide:2080px;--site-gutter:64px;--site-section-space:calc(var(--site-section-space-base) + 32px)}}@media(max-width:1199px){:root{--site-gutter:24px}}@media(max-width:767px){:root{--site-gutter:14px}}';
$responsivePreviewDoc = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' .
    $responsiveFontLinks . '<style>' . $safeFontCss . $responsiveThemeCss . $safeComponentCss . '</style></head><body>' .
    $responsiveBody . (trim((string)($componentJs ?? '')) !== '' ? '<script>' . $safeComponentJs . '<\/script>' : '') .
    '</body></html>';
?>
<?php foreach (($googleFontUrls ?? []) as $fontUrl): ?><link rel="stylesheet" href="<?= htmlspecialchars($fontUrl) ?>"><?php endforeach; ?>
<style id="blumi-project-design">
<?= $safeFontCss ?>
.canvas-page{
--site-primary:<?= htmlspecialchars($design['primary_color'] ?? '#2043BF') ?>;
--site-secondary:<?= htmlspecialchars($design['secondary_color'] ?? '#3974DC') ?>;
--site-accent:<?= htmlspecialchars($design['accent_color'] ?? '#E7DF68') ?>;
--site-background:<?= htmlspecialchars($design['background_color'] ?? '#FFFFFF') ?>;
--site-surface:<?= htmlspecialchars($design['surface_color'] ?? '#FFFFFF') ?>;
--site-text:<?= htmlspecialchars($design['text_color'] ?? '#1D1D1F') ?>;
--site-muted:<?= htmlspecialchars($design['muted_color'] ?? '#71717A') ?>;
--site-radius:<?= htmlspecialchars($design['border_radius'] ?? '12px') ?>;
--site-container-normal:<?= htmlspecialchars($design['container_width'] ?? '1280px') ?>;
--site-container-wide:1600px;
--site-container:var(--site-container-normal);
--site-section-space-base:<?= htmlspecialchars($design['section_spacing'] ?? '96px') ?>;
--site-section-space:var(--site-section-space-base);
--site-gutter:32px;
--site-font-heading:'<?= htmlspecialchars($design['heading_font'] ?? 'Inter') ?>',sans-serif;
--site-font-body:'<?= htmlspecialchars($design['body_font'] ?? 'Inter') ?>',sans-serif;
--font-display-xl:clamp(72px,4.3vw,108px);--font-display:clamp(60px,3.55vw,88px);--font-h1:clamp(48px,2.9vw,72px);--font-h2:clamp(40px,2.4vw,60px);--font-h3:clamp(32px,1.9vw,48px);--font-h4:clamp(24px,1.45vw,34px);--font-body-lg:clamp(18px,.95vw,20px);--font-body:clamp(16px,.85vw,18px);--font-small:clamp(14px,.74vw,15px);--font-caption:12px;
--space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;--space-5:20px;--space-6:24px;--space-8:clamp(32px,2vw,44px);--space-10:clamp(40px,2.4vw,52px);--space-12:clamp(48px,2.8vw,60px);--space-16:clamp(64px,3.6vw,80px);--space-20:clamp(80px,4.4vw,96px);--space-24:clamp(96px,5.2vw,120px);
--radius-sm:8px;--radius-md:12px;--radius-lg:16px;--radius-xl:20px;--radius-pill:999px;--container-width:var(--site-container);
font-family:var(--site-font-body);background:var(--site-background);
}
.canvas-page h1,.canvas-page h2,.canvas-page h3,.canvas-page h4,.canvas-page h5,.canvas-page h6{font-family:var(--site-font-heading);}
.canvas-page p,.canvas-page a,.canvas-page button,.canvas-page input,.canvas-page textarea,.canvas-page select{font-family:var(--site-font-body);}
.bl-page-component{width:100%;max-width:none;margin:0;padding:0;min-width:0}.bl-section-background{width:100%;max-width:none;margin:0;background:var(--site-background);min-width:0}.bl-site-frame{width:100%;max-width:calc(var(--site-container) + (var(--site-gutter) * 2));margin:0 auto;padding-left:var(--site-gutter);padding-right:var(--site-gutter);min-width:0;box-sizing:border-box}.bl-component-surface{width:100%;max-width:none;margin:0;background:transparent;min-width:0}.bl-page-component[data-blumi-category="navbar"] .bl-component-surface,.bl-page-component[data-blumi-category="footer"] .bl-component-surface{background:var(--site-surface)}.bl-component-stage{width:100%;min-width:0}.bl-component-stage>*{width:100%!important;max-width:none!important;margin:0!important;min-width:0}.bl-component-stage>*>*{min-width:0}
@media(min-width:1600px){.canvas-page{--site-gutter:48px;--site-section-space:calc(var(--site-section-space-base) + 8px)}}
@media(min-width:1900px){.canvas-page{--site-container-wide:1760px;--site-gutter:56px;--site-section-space:calc(var(--site-section-space-base) + 16px)}}
@media(min-width:2400px){.canvas-page{--site-container-wide:2080px;--site-gutter:64px;--site-section-space:calc(var(--site-section-space-base) + 32px)}}
@media(max-width:1199px){.canvas-page{--site-gutter:24px}}
@media(max-width:767px){.canvas-page{--site-gutter:14px}}
</style>
<?php if (trim((string)$componentCss) !== ''): ?>
<style id="blumi-component-styles"><?= $safeComponentCss ?></style>
<?php endif; ?>
<div class="builder-shell">
    <header class="builder-topbar">
        <div class="builder-breadcrumb">
            <a class="builder-back-project" href="index.php?route=projects.show&id=<?= (int)$page['proyecto_id'] ?>" title="Volver al proyecto y sus páginas">← Volver al proyecto</a>
            <span class="builder-breadcrumb-context">
                <a class="builder-brand" href="index.php"><span class="brand-word">Blumi</span><span class="brand-sub">Studio</span></a>
                <span class="crumb-separator">/</span>
                <a href="index.php?route=projects.show&id=<?= (int)$page['proyecto_id'] ?>"><?= htmlspecialchars($page['proyecto_nombre']) ?></a>
                <span class="crumb-separator">/</span>
                <strong><?= htmlspecialchars($page['nombre']) ?></strong>
            </span>
        </div>
        <div class="viewport-switch" aria-label="Tamaño del preview">
            <button class="viewport-button is-active" type="button" data-viewport="desktop">Desktop</button>
            <button class="viewport-button" type="button" data-viewport="tablet">Tablet</button>
            <button class="viewport-button" type="button" data-viewport="mobile">Mobile</button>
        </div>
        <div class="builder-actions">
            <a class="button button-secondary button-small" href="index.php?route=design.show&id=<?= (int)$page['proyecto_id'] ?>">Diseño</a>
            <?php if (empty($brandReadiness['ready'])): ?><a class="builder-brand-warning" href="index.php?route=design.show&id=<?= (int)$page['proyecto_id'] ?>">Falta logo</a><?php endif; ?>
            <span class="save-state"><?= $success ? 'Guardado' : 'Sin cambios' ?></span>
            <?php if ($canBuild): ?>
                <a class="button button-secondary button-small" href="index.php?route=build.download&page_id=<?= (int)$page['id'] ?>" title="Descarga el sitio completo con todas sus páginas y assets compartidos">Descargar sitio</a>
                <form action="index.php?route=build.publish" method="post" target="_blank" style="margin:0">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                    <button class="button button-primary button-small" type="submit" <?= empty($brandReadiness['ready']) ? 'disabled' : '' ?> title="<?= empty($brandReadiness['ready']) ? 'Sube el logo principal antes de publicar' : 'Publica todas las páginas del proyecto y abre la página actual' ?>">Publicar sitio</button>
                </form>
            <?php endif; ?>
            <span class="user-badge builder-user"><?= htmlspecialchars(mb_substr($user['name'] ?? 'U', 0, 1)) ?></span>
        </div>
    </header>

    <aside class="builder-left">
        <a class="builder-project-return" href="index.php?route=projects.show&id=<?= (int)$page['proyecto_id'] ?>">
            <span aria-hidden="true">←</span>
            <span><small>PROYECTO</small><strong><?= htmlspecialchars($page['proyecto_nombre']) ?></strong></span>
        </a>
        <div class="builder-panel-head builder-left-head">
            <div><span class="eyebrow">PÁGINAS</span><strong>Estructura</strong></div>
            <div class="builder-left-head-actions">
                <?php if ($canBuild): ?><button class="tiny-icon-button" type="button" data-open-modal="builder-page-modal" title="Nueva página">+</button><?php endif; ?>
                <button class="tiny-icon-button builder-left-toggle" type="button" data-builder-left-toggle title="Ocultar panel izquierdo" aria-label="Ocultar panel izquierdo">‹</button>
            </div>
        </div>
        <nav class="builder-pages">
            <?php foreach ($pages as $projectPage): ?>
                <a class="builder-page-item <?= (int)$projectPage['id'] === (int)$page['id'] ? 'is-active' : '' ?>" href="index.php?route=builder&page_id=<?= (int)$projectPage['id'] ?>">
                    <span>▱</span>
                    <div><strong><?= htmlspecialchars($projectPage['nombre']) ?></strong><small>/<?= htmlspecialchars($projectPage['slug']) ?></small></div>
                    <?php if ((int)$projectPage['es_inicio'] === 1): ?><i>⌂</i><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="builder-divider"></div>
        <div class="builder-panel-head compact">
            <div><span class="eyebrow">SECCIONES</span><strong><?= count($instances) ?> componentes</strong></div>
            <?php if ($canBuild): ?><button class="tiny-icon-button" type="button" data-open-modal="component-library" title="Agregar sección">+</button><?php endif; ?>
        </div>

        <?php if (!$instances): ?>
            <div class="sections-empty">
                <p>Esta página aún no tiene secciones.</p>
                <?php if ($canBuild): ?>
                    <button class="button button-secondary button-small" type="button" data-open-modal="component-library">+ Agregar sección</button>
                    <small>Elige una estructura de la biblioteca. Después solo editas sus campos.</small>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="builder-sections-list">
                <?php foreach ($instances as $index => $instance): ?>
                    <a class="builder-section-item <?= (int)$instance['id'] === $selectedId ? 'is-active' : '' ?>" href="index.php?route=builder&page_id=<?= (int)$page['id'] ?>&instance_id=<?= (int)$instance['id'] ?>#blumi-section-<?= (int)$instance['id'] ?>" data-builder-section-link data-instance-id="<?= (int)$instance['id'] ?>">
                        <span class="section-grip">⋮⋮</span>
                        <div>
                            <strong><?= htmlspecialchars($instance['componente_nombre']) ?> <?php if (!empty($instance['is_global_layout'])): ?><span class="mini-badge">Global</span><?php endif; ?></strong>
                            <small><?= htmlspecialchars($instance['categoria_nombre']) ?><?= !empty($instance['is_global_layout']) ? ' · todas las páginas' : ' · ' . ($index + 1) ?></small>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($canBuild): ?>
                <div class="section-add-bottom"><button class="button button-secondary button-small" type="button" data-open-modal="component-library">+ Agregar sección</button></div>
            <?php endif; ?>
        <?php endif; ?>
        <button class="builder-left-restore" type="button" data-builder-left-toggle title="Mostrar panel izquierdo" aria-label="Mostrar panel izquierdo">›</button>
    </aside>

    <main class="builder-workspace">
        <div class="canvas-toolbar">
            <div class="builder-page-nav" data-page-switcher>
                <span class="builder-page-nav-label">Página</span>
                <button class="builder-page-switcher" type="button" data-page-switcher-toggle aria-expanded="false">
                    <span class="builder-page-switcher-icon">▱</span>
                    <span class="builder-page-switcher-copy"><strong><?= htmlspecialchars($page['nombre']) ?></strong><small><?= (int)$page['es_inicio'] === 1 ? 'Página de inicio' : 'Página del proyecto' ?></small></span>
                    <span class="builder-page-switcher-chevron">⌄</span>
                </button>
                <div class="builder-page-menu" data-page-switcher-menu hidden>
                    <div class="builder-page-menu-title">Páginas del proyecto</div>
                    <?php foreach ($pages as $projectPage): ?>
                        <a class="builder-page-menu-item <?= (int)$projectPage['id']===(int)$page['id']?'is-active':'' ?>" href="index.php?route=builder&page_id=<?= (int)$projectPage['id'] ?>">
                            <span class="builder-page-menu-icon"><?= (int)$projectPage['es_inicio']===1?'⌂':'▱' ?></span>
                            <span><strong><?= htmlspecialchars($projectPage['nombre']) ?></strong><small>/<?= htmlspecialchars($projectPage['slug']) ?></small></span>
                            <?php if ((int)$projectPage['id']===(int)$page['id']): ?><i>✓</i><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($canBuild): ?><button class="builder-page-menu-create" type="button" data-open-modal="builder-page-modal"><span>＋</span>Nueva página</button><?php endif; ?>
                </div>
                <span class="builder-page-route">/<?= htmlspecialchars($page['slug']) ?></span>
            </div>
            <span class="canvas-size" data-canvas-size>Desktop · 1200px</span>
        </div>
        <div class="canvas-stage">
            <div class="canvas-page canvas-page-interactive" data-canvas data-desktop-canvas>
                <?php if (!$instances): ?>
                    <div class="canvas-empty">
                        <div class="canvas-empty-mark">B</div>
                        <span class="eyebrow">PÁGINA VACÍA</span>
                        <h1><?= htmlspecialchars($page['nombre']) ?></h1>
                        <p>Agrega una primera sección y Blumi construirá la página usando componentes reutilizables.</p>
                        <?php if ($canBuild): ?><button class="button button-primary" type="button" data-open-modal="component-library">+ Agregar primera sección</button><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="rendered-page">
                        <?php foreach ($instances as $instance): ?>
                            <?php if (!empty($instance['is_global_layout']) && strtolower((string)($instance['categoria_slug'] ?? '')) === 'footer' && $bodyInstanceCount === 0): ?>
                                <div class="canvas-empty" style="min-height:220px;margin:0;border-radius:0">
                                    <span class="eyebrow">CUERPO DE LA PÁGINA</span><h2><?= htmlspecialchars($page['nombre']) ?></h2><p>Navbar y Footer son globales. Agrega aquí las secciones propias de esta página.</p>
                                    <?php if ($canBuild): ?><button class="button button-primary" type="button" data-open-modal="component-library">+ Agregar sección</button><?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <section id="blumi-section-<?= (int)$instance['id'] ?>" class="canvas-component <?= (int)$instance['id'] === $selectedId ? 'is-selected' : '' ?> <?= !empty($instance['is_global_layout']) ? 'is-global-layout' : '' ?>" data-instance-id="<?= (int)$instance['id'] ?>">
                                <a class="canvas-component-select" href="index.php?route=builder&page_id=<?= (int)$page['id'] ?>&instance_id=<?= (int)$instance['id'] ?>#blumi-section-<?= (int)$instance['id'] ?>" data-builder-canvas-select data-instance-id="<?= (int)$instance['id'] ?>" aria-label="Editar <?= htmlspecialchars($instance['componente_nombre']) ?>"></a>
                                <span class="canvas-component-label"><?= htmlspecialchars($instance['componente_nombre']) ?></span>
                                <div class="bl-page-component" data-blumi-instance="<?= (int)$instance['id'] ?>" data-blumi-category="<?= htmlspecialchars((string)($instance['categoria_slug'] ?? '')) ?>"><div class="bl-section-background"><div class="bl-site-frame"><div class="bl-component-surface"><div class="bl-component-stage"><?= $instance['rendered_html'] ?></div></div></div></div></div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <iframe class="canvas-responsive-frame" data-responsive-canvas title="Vista responsive de <?= htmlspecialchars($page['nombre']) ?>" srcdoc="<?= htmlspecialchars($responsivePreviewDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></iframe>
        </div>
    </main>

    <aside class="builder-inspector">
        <?php if ($selectedInstance): ?>
            <div class="inspector-head inspector-component-head">
                <div><span class="eyebrow">SECCIÓN SELECCIONADA</span><strong><?= htmlspecialchars($selectedInstance['componente_nombre']) ?></strong></div>
                <span class="mini-badge"><?= htmlspecialchars($selectedInstance['categoria_nombre']) ?></span>
            </div>
            <?php if ($canBuild): ?>
            <section class="blumi-personalize-card <?= $selectedIsGlobal ? 'is-global-scope' : '' ?>" data-personalize-panel
                data-instance-id="<?= (int)$selectedInstance['id'] ?>"
                data-csrf="<?= htmlspecialchars(Csrf::token()) ?>"
                data-available-targets="<?= htmlspecialchars(json_encode($personalizationTargets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                <div class="blumi-personalize-card-head">
                    <div class="blumi-personalize-title"><span class="blumi-personalize-mark">✦</span><div><strong>Personalizar con Blumi</strong><small>Ajusta lo que ya existe sin alterar el componente maestro.</small></div></div>
                    <?php if ($selectedHasCustomization): ?><span class="blumi-personalize-status">Personalizado</span><?php endif; ?>
                </div>
                <?php if (!$personalizationTargets): ?>
                    <div class="blumi-personalize-locked"><strong>Sin elementos identificables</strong><span>Este componente antiguo no expone targets suficientemente seguros para la selección visual.</span></div>
                <?php else: ?>
                    <?php if ($selectedIsGlobal): ?>
                    <div class="blumi-personalize-global-note"><strong>Personalización global</strong><span>Los cambios que apliques a este <?= $selectedCategory==='navbar'?'Navbar':'Footer' ?> se verán en todas las páginas del proyecto.</span></div>
                    <?php endif; ?>
                    <div class="blumi-personalize-chat">
                        <div class="blumi-personalize-message"><span>Blumi</span><p><?= $selectedIsGlobal ? 'Selecciona dentro del componente global exactamente lo que quieres ajustar. El cambio conservará su función y afectará todas las páginas.' : 'Selecciona en el Canvas exactamente lo que quieres ajustar. Puedes marcar varios elementos.' ?></p></div>
                        <div class="blumi-personalize-selection" data-personalize-selection>
                            <span class="blumi-personalize-selection-empty">Aún no has seleccionado elementos.</span>
                        </div>
                        <div class="blumi-personalize-compose">
                            <textarea rows="4" data-personalize-draft placeholder="Ej. Dale un poco más de aire a este texto hacia la izquierda."></textarea>
                            <div class="blumi-personalize-compose-foot"><small data-personalize-hint>Primero activa la selección visual.</small><button class="button button-secondary button-small" type="button" data-personalize-toggle>Seleccionar en Canvas</button></div>
                            <button class="button button-primary blumi-personalize-generate" type="button" data-personalize-generate disabled>Generar propuesta</button>
                        </div>
                        <div class="blumi-personalize-proposal" data-personalize-proposal hidden>
                            <div class="blumi-personalize-proposal-head"><span>BLUMI ENTENDIÓ</span><strong data-personalize-summary></strong></div>
                            <div class="blumi-personalize-proposal-body">
                                <div><small>Cambiará</small><ul data-personalize-will-change></ul></div>
                                <div><small>Mantendrá</small><ul data-personalize-will-keep></ul></div>
                            </div>
                            <div class="blumi-personalize-preview-switch" data-personalize-preview-switch>
                                <button type="button" class="is-active" data-personalize-view="original">Original</button>
                                <button type="button" data-personalize-view="proposal">Propuesta</button>
                            </div>
                            <div class="blumi-personalize-proposal-actions">
                                <button class="button button-secondary button-small" type="button" data-personalize-discard>Descartar</button>
                                <button class="button button-primary button-small" type="button" data-personalize-apply>Aplicar cambio</button>
                            </div>
                        </div>
                        <div class="blumi-personalize-variant" data-personalize-variant hidden>
                            <strong>Este cambio necesita una variante</strong>
                            <span data-personalize-variant-reason>La petición cambia la estructura o el concepto del componente.</span>
                        </div>
                        <div class="blumi-personalize-phase-note">Personalizar trabaja únicamente sobre los elementos seleccionados y conserva el componente maestro.</div>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <div class="inspector-tabs" data-inspector-tabs data-page-id="<?= (int)$page['id'] ?>" data-instance-id="<?= (int)$selectedInstance['id'] ?>">
                <button class="inspector-tab is-active" type="button" data-inspector-tab="content">Contenido</button>
                <?php if ($canBuild): ?><button class="inspector-tab" type="button" data-inspector-tab="style">Estilo</button><?php endif; ?>
            </div>

            <div data-inspector-panel="content">
                <form action="index.php?route=components.update" method="post" enctype="multipart/form-data" class="inspector-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                    <input type="hidden" name="instance_id" value="<?= (int)$selectedInstance['id'] ?>">
                    <section class="inspector-section is-open">
                        <div class="inspector-static-title">Contenido</div>
                        <div class="inspector-content inspector-fields">
                            <?php $visibleFields = 0; ?>
                            <?php foreach ($selectedSchema as $key => $definition): ?>
                                <?php
                                    if (!is_array($definition)) continue;
                                    if ($role === 'cliente' && empty($definition['client_editable'])) continue;
                                    $type = (string)($definition['type'] ?? 'text');
                                    if (!in_array($type, ['text','textarea','url','email','phone','number','image'], true)) continue;
                                    $visibleFields++;
                                    $label = (string)($definition['label'] ?? $key);
                                    $defaultValue = (string)($definition['default'] ?? '');
                                    $value = (string)($selectedContent[$key] ?? $defaultValue);
                                    $effectiveImageValue = $value;
                                    $isUsingProjectLogo = false;
                                    if ($type === 'image' && (string)$key === 'site_logo' && !empty($design['logo_ruta'])) {
                                        if ($value === '' || $value === $defaultValue || str_contains($value, 'blumi-')) {
                                            $effectiveImageValue = (string)$design['logo_ruta'];
                                            $isUsingProjectLogo = true;
                                        }
                                    }
                                    $advanced = in_array($type, ['url','email','phone','number','image'], true) && $role !== 'cliente';
                                ?>
                                <?php if ($type === 'url'): ?>
                                    <?php
                                        $storedMeta = is_array($selectedLinkMeta[$key] ?? null) ? $selectedLinkMeta[$key] : [];
                                        $linkType = (string)($storedMeta['type'] ?? '');
                                        if ($linkType === '') {
                                            if ($value === '' || $value === '#') $linkType = 'none';
                                            elseif (str_starts_with(strtolower($value), 'mailto:')) $linkType = 'email';
                                            elseif (str_starts_with(strtolower($value), 'tel:')) $linkType = 'phone';
                                            else $linkType = 'external';
                                        }
                                        $linkPageId = (int)($storedMeta['page_id'] ?? $page['id']);
                                        $linkSectionId = (int)($storedMeta['section_id'] ?? 0);
                                        $linkRawValue = (string)($storedMeta['value'] ?? $value);
                                        if ($linkType === 'email') $linkRawValue = preg_replace('/^mailto:/i','',$linkRawValue) ?? $linkRawValue;
                                        if ($linkType === 'phone') $linkRawValue = preg_replace('/^tel:/i','',$linkRawValue) ?? $linkRawValue;
                                    ?>
                                    <div class="smart-link-card" data-smart-link-field>
                                        <div class="smart-link-head"><div><span><?= htmlspecialchars($label) ?><?= !empty($definition['required']) ? ' *' : '' ?></span><small>Elige el destino. Blumi crea la ruta por ti.</small></div><span class="smart-link-badge">Enlace</span></div>
                                        <input type="hidden" name="<?= htmlspecialchars((string)$key) ?>" value="<?= htmlspecialchars($value) ?>">
                                        <label class="field compact-field smart-link-type"><span>Destino</span><select name="link_meta[<?= htmlspecialchars((string)$key) ?>][type]" data-smart-link-type>
                                            <option value="none" <?= $linkType==='none'?'selected':'' ?>>Sin enlace</option>
                                            <option value="section" <?= $linkType==='section'?'selected':'' ?>>Sección de esta página</option>
                                            <option value="page" <?= $linkType==='page'?'selected':'' ?>>Página del proyecto</option>
                                            <option value="page_section" <?= $linkType==='page_section'?'selected':'' ?>>Página + sección</option>
                                            <option value="external" <?= $linkType==='external'?'selected':'' ?>>URL externa o personalizada</option>
                                            <option value="email" <?= $linkType==='email'?'selected':'' ?>>Email</option>
                                            <option value="phone" <?= $linkType==='phone'?'selected':'' ?>>Teléfono</option>
                                        </select></label>
                                        <div class="smart-link-panel" data-smart-link-panel="section" <?= $linkType==='section'?'':'hidden' ?>>
                                            <label class="field compact-field"><span>Sección</span><select name="link_meta[<?= htmlspecialchars((string)$key) ?>][section_id]" data-smart-link-current-section>
                                                <option value="0">Selecciona una sección…</option>
                                                <?php foreach ($currentPageSections as $sectionOption): ?><option value="<?= (int)$sectionOption['id'] ?>" <?= $linkSectionId===(int)$sectionOption['id']?'selected':'' ?>><?= htmlspecialchars($sectionOption['label']) ?><?= $sectionOption['category']!==''?' · '.htmlspecialchars($sectionOption['category']):'' ?></option><?php endforeach; ?>
                                            </select></label>
                                        </div>
                                        <div class="smart-link-panel" data-smart-link-panel="page" <?= $linkType==='page'?'':'hidden' ?>>
                                            <label class="field compact-field"><span>Página</span><select name="link_meta[<?= htmlspecialchars((string)$key) ?>][page_id]">
                                                <?php foreach ($pages as $linkPage): ?><option value="<?= (int)$linkPage['id'] ?>" <?= $linkPageId===(int)$linkPage['id']?'selected':'' ?>><?= htmlspecialchars($linkPage['nombre']) ?><?= (int)$linkPage['es_inicio']===1?' · Inicio':'' ?></option><?php endforeach; ?>
                                            </select></label>
                                        </div>
                                        <div class="smart-link-panel smart-link-page-section" data-smart-link-panel="page_section" <?= $linkType==='page_section'?'':'hidden' ?>>
                                            <label class="field compact-field"><span>Página</span><select name="link_meta[<?= htmlspecialchars((string)$key) ?>][page_id]" data-smart-link-page>
                                                <?php foreach ($pages as $linkPage): ?><option value="<?= (int)$linkPage['id'] ?>" <?= $linkPageId===(int)$linkPage['id']?'selected':'' ?>><?= htmlspecialchars($linkPage['nombre']) ?><?= (int)$linkPage['es_inicio']===1?' · Inicio':'' ?></option><?php endforeach; ?>
                                            </select></label>
                                            <label class="field compact-field"><span>Sección</span><select name="link_meta[<?= htmlspecialchars((string)$key) ?>][section_id]" data-smart-link-section>
                                                <option value="0">Selecciona una sección…</option>
                                                <?php foreach (($linkSectionsByPage ?? []) as $sectionPageId => $sectionOptions): foreach ($sectionOptions as $sectionOption): ?><option value="<?= (int)$sectionOption['id'] ?>" data-page-id="<?= (int)$sectionPageId ?>" <?= $linkSectionId===(int)$sectionOption['id']?'selected':'' ?>><?= htmlspecialchars($sectionOption['label']) ?><?= $sectionOption['category']!==''?' · '.htmlspecialchars($sectionOption['category']):'' ?></option><?php endforeach; endforeach; ?>
                                            </select></label>
                                        </div>
                                        <div class="smart-link-panel" data-smart-link-panel="external" <?= $linkType==='external'?'':'hidden' ?>><label class="field compact-field"><span>URL</span><input name="link_meta[<?= htmlspecialchars((string)$key) ?>][value]" value="<?= htmlspecialchars($linkRawValue) ?>" placeholder="https://ejemplo.com"></label></div>
                                        <div class="smart-link-panel" data-smart-link-panel="email" <?= $linkType==='email'?'':'hidden' ?>><label class="field compact-field"><span>Email</span><input type="email" name="link_meta[<?= htmlspecialchars((string)$key) ?>][value]" value="<?= htmlspecialchars($linkRawValue) ?>" placeholder="hola@ejemplo.com"></label></div>
                                        <div class="smart-link-panel" data-smart-link-panel="phone" <?= $linkType==='phone'?'':'hidden' ?>><label class="field compact-field"><span>Teléfono</span><input name="link_meta[<?= htmlspecialchars((string)$key) ?>][value]" value="<?= htmlspecialchars($linkRawValue) ?>" placeholder="+57 300 000 0000"></label></div>
                                    </div>
                                <?php else: ?>
                                <label class="field compact-field <?= $advanced ? 'field-technical' : '' ?>">
                                    <span><?= htmlspecialchars($label) ?><?= !empty($definition['required']) ? ' *' : '' ?></span>
                                    <?php if ($type === 'image'): ?>
                                        <small><?= !empty($isUsingProjectLogo) ? 'Usando el logo principal configurado en Diseño. Puedes subir uno distinto solo para esta sección.' : 'Reemplaza este recurso con tu SVG, WebP, PNG o JPG. Si no subes nada, se conserva el actual.' ?></small>
                                        <?php if ($effectiveImageValue !== ''): ?><div class="inspector-image-preview"><img src="<?= htmlspecialchars($effectiveImageValue) ?>" alt=""></div><?php endif; ?>
                                        <input type="hidden" name="<?= htmlspecialchars((string)$key) ?>" value="<?= htmlspecialchars($value) ?>">
                                        <input type="file" name="__file_<?= htmlspecialchars((string)$key) ?>" accept="image/svg+xml,image/webp,image/png,image/jpeg,.svg,.webp,.png,.jpg,.jpeg">
                                    <?php elseif ($type === 'textarea'): ?>
                                        <textarea name="<?= htmlspecialchars((string)$key) ?>" rows="4" <?= !empty($definition['required']) ? 'required' : '' ?>><?= htmlspecialchars($value) ?></textarea>
                                    <?php else: ?>
                                        <input type="<?= $type === 'number' ? 'number' : ($type === 'email' ? 'email' : 'text') ?>" name="<?= htmlspecialchars((string)$key) ?>" value="<?= htmlspecialchars($value) ?>" <?= !empty($definition['required']) ? 'required' : '' ?>>
                                    <?php endif; ?>
                                </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($visibleFields === 0): ?><p class="inspector-empty-text">Esta sección no tiene campos editables para tu perfil.</p><?php endif; ?>
                        </div>
                    </section>
                    <?php if ($visibleFields > 0): ?><div class="inspector-save"><button class="button button-primary button-full" type="submit">Guardar contenido</button></div><?php endif; ?>
                </form>
            </div>

            <?php if ($canBuild): ?>
            <div data-inspector-panel="style" hidden>
                <form action="index.php?route=components.style" method="post" enctype="multipart/form-data" class="inspector-form">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                    <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                    <input type="hidden" name="instance_id" value="<?= (int)$selectedInstance['id'] ?>">
                    <section class="inspector-section is-open">
                        <div class="inspector-static-title">Visibilidad</div>
                        <div class="inspector-content inspector-fields">
                            <p class="inspector-help">Oculta elementos de esta instancia sin borrarlos del componente maestro. Puedes volver a mostrarlos cuando quieras.</p>
                            <?php if ($selectedVisibilityControls): ?>
                                <div class="visibility-control-list">
                                    <?php foreach ($selectedVisibilityControls as $fieldKey => $visibilityDef): ?>
                                        <?php $isHidden = in_array((string)$fieldKey, $hiddenFields, true); ?>
                                        <label class="visibility-control-row">
                                            <span><?= htmlspecialchars((string)($visibilityDef['label'] ?? $fieldKey)) ?></span>
                                            <input type="checkbox" name="hide__<?= htmlspecialchars((string)$fieldKey) ?>" value="1" <?= $isHidden ? 'checked' : '' ?>>
                                            <i aria-hidden="true"></i>
                                            <small><?= $isHidden ? 'Oculto' : 'Visible' ?></small>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="inspector-empty-text">Este componente no expone elementos ocultables todavía.</p>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="inspector-section is-open">
                        <div class="inspector-static-title">Fondo</div>
                        <div class="inspector-content inspector-fields">
                            <p class="inspector-help"><strong>Color lateral / fondo de sección</strong> pinta los espacios laterales. <strong>Fondo del componente</strong> pinta el módulo dentro de esos márgenes. Si eliges imagen, reemplaza únicamente el fondo del componente.</p>
                            <div class="background-mode-switch">
                                <label class="background-mode-option <?= $backgroundMode !== 'image' ? 'is-selected' : '' ?>">
                                    <input type="radio" name="background_mode" value="color" <?= $backgroundMode !== 'image' ? 'checked' : '' ?>>
                                    <strong>Color</strong><small>Usar paleta del proyecto</small>
                                </label>
                                <label class="background-mode-option <?= $backgroundMode === 'image' ? 'is-selected' : '' ?>">
                                    <input type="radio" name="background_mode" value="image" <?= $backgroundMode === 'image' ? 'checked' : '' ?>>
                                    <strong>Imagen</strong><small>Cover + centrada</small>
                                </label>
                            </div>
                            <div class="background-image-control" data-background-image-control <?= $backgroundMode === 'image' ? '' : 'hidden' ?>>
                                <?php if ($backgroundImage !== ''): ?>
                                    <div class="background-image-preview" style="background-image:url('<?= htmlspecialchars($backgroundImage, ENT_QUOTES) ?>')"></div>
                                    <small>Imagen actual. Sube otra para reemplazarla.</small>
                                <?php endif; ?>
                                <input type="file" name="background_image" accept="image/svg+xml,image/webp,image/png,image/jpeg,.svg,.webp,.png,.jpg,.jpeg">
                            </div>
                        </div>
                    </section>

                    <section class="inspector-section is-open">
                        <div class="inspector-static-title">Apariencia</div>
                        <div class="inspector-content inspector-fields">
                            <p class="inspector-help">Solo modifica apariencia superficial. La estructura y el responsive del módulo permanecen intactos.</p>
                            <?php
                            $paletteOptions = [
                                'inherit' => ['Automático', null],
                                'primary' => ['Principal', $design['primary_color'] ?? '#2043BF'],
                                'secondary' => ['Secundario', $design['secondary_color'] ?? '#3974DC'],
                                'accent' => ['Acento', $design['accent_color'] ?? '#E7DF68'],
                                'background' => ['Fondo', $design['background_color'] ?? '#FFFFFF'],
                                'surface' => ['Superficie', $design['surface_color'] ?? '#FFFFFF'],
                                'text' => ['Texto', $design['text_color'] ?? '#1D1D1F'],
                                'muted' => ['Texto suave', $design['muted_color'] ?? '#71717A'],
                                'transparent' => ['Transparente', 'transparent'],
                            ];
                            $radiusOptions = ['inherit'=>'Automático','none'=>'Ninguno','soft'=>'Suave','medium'=>'Medio','large'=>'Grande','pill'=>'Pill'];
                            $shadowOptions = ['inherit'=>'Automático','none'=>'Ninguna','subtle'=>'Sutil','medium'=>'Media','large'=>'Grande'];
                            $shadowDirectionOptions = ['inherit'=>'Automático','bottom'=>'Abajo','top'=>'Arriba','both'=>'Arriba y abajo','around'=>'Alrededor'];
                            $shadowOpacityOptions = ['inherit'=>'Automático','soft'=>'8% · Suave','medium'=>'14% · Media','strong'=>'22% · Alta','solid'=>'32% · Fuerte'];
                            $navbarPositionOptions = ['inherit'=>'Automático','static'=>'Estático','fixed'=>'Fijo arriba'];
                            $layoutWidthOptions = ['inherit'=>'Automático según tipo','normal'=>'Normal','wide'=>'Amplio','full'=>'Full bleed'];
                            ?>
                            <?php foreach ($selectedStyleControls as $styleKey => $styleDef): ?>
                                <?php if (!is_array($styleDef)) continue; $styleType=(string)($styleDef['type']??'color'); $styleLabel=(string)($styleDef['label']??$styleKey); if ((string)$styleKey === 'section_background') $styleLabel='Color lateral / fondo de sección'; if ((string)$styleKey === 'surface_color') $styleLabel='Fondo del componente'; $styleValue=(string)($selectedStyles[$styleKey]??'inherit'); ?>
                                <div class="field compact-field style-control-field">
                                    <span><?= htmlspecialchars($styleLabel) ?></span>
                                    <?php if ($styleType === 'radius'): ?>
                                        <select name="<?= htmlspecialchars((string)$styleKey) ?>"><?php foreach($radiusOptions as $v=>$l): ?><option value="<?= htmlspecialchars($v) ?>" <?= $styleValue===$v?'selected':'' ?>><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select>
                                    <?php elseif ($styleType === 'shadow'): ?>
                                        <select name="<?= htmlspecialchars((string)$styleKey) ?>"><?php foreach($shadowOptions as $v=>$l): ?><option value="<?= htmlspecialchars($v) ?>" <?= $styleValue===$v?'selected':'' ?>><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select>
                                    <?php elseif ($styleType === 'shadow_direction'): ?>
                                        <select name="<?= htmlspecialchars((string)$styleKey) ?>"><?php foreach($shadowDirectionOptions as $v=>$l): ?><option value="<?= htmlspecialchars($v) ?>" <?= $styleValue===$v?'selected':'' ?>><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select>
                                    <?php elseif ($styleType === 'shadow_opacity'): ?>
                                        <select name="<?= htmlspecialchars((string)$styleKey) ?>"><?php foreach($shadowOpacityOptions as $v=>$l): ?><option value="<?= htmlspecialchars($v) ?>" <?= $styleValue===$v?'selected':'' ?>><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select>
                                    <?php elseif ($styleType === 'layout_width'): ?>
                                        <select name="<?= htmlspecialchars((string)$styleKey) ?>"><?php foreach($layoutWidthOptions as $v=>$l): ?><option value="<?= htmlspecialchars($v) ?>" <?= $styleValue===$v?'selected':'' ?>><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select>
                                        <small class="field-hint">Normal usa el contenedor del proyecto, Amplio aprovecha más pantalla y Full bleed lleva el contenido hasta los bordes.</small>
                                    <?php elseif ($styleType === 'navbar_position'): ?>
                                        <select name="<?= htmlspecialchars((string)$styleKey) ?>"><?php foreach($navbarPositionOptions as $v=>$l): ?><option value="<?= htmlspecialchars($v) ?>" <?= $styleValue===$v?'selected':'' ?>><?= htmlspecialchars($l) ?></option><?php endforeach; ?></select>
                                        <small class="field-hint">Fijo arriba permanece visible al desplazarte sin tapar la sección siguiente.</small>
                                    <?php else: ?>
                                        <div class="palette-token-grid">
                                            <?php foreach($paletteOptions as $v=>$meta): ?>
                                                <label class="palette-token <?= $styleValue===$v?'is-selected':'' ?>" data-token-label="<?= htmlspecialchars((string)$meta[0]) ?>" data-token-value="<?= htmlspecialchars($v) ?>">
                                                    <input type="radio" name="<?= htmlspecialchars((string)$styleKey) ?>" value="<?= htmlspecialchars($v) ?>" <?= $styleValue===$v?'checked':'' ?>>
                                                    <i style="<?= $meta[1] ? 'background:'.htmlspecialchars((string)$meta[1]) : '' ?>"></i><small><?= htmlspecialchars((string)$meta[0]) ?></small>
                                                    <b class="palette-token-check" aria-hidden="true">✓</b>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="style-selection-status" data-style-selection-status>Seleccionado: <strong><?= htmlspecialchars((string)($paletteOptions[$styleValue][0] ?? 'Automático')) ?></strong></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <div class="inspector-save inspector-save-split">
                        <button class="button button-primary" type="submit">Guardar estilo</button>
                        <button class="button button-secondary" type="submit" name="reset_styles" value="1">Restablecer</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($canBuild): ?>
                <section class="inspector-section">
                    <div class="inspector-static-title">Orden y sección</div>
                    <?php if ($selectedIsGlobal): ?>
                        <div class="inspector-content"><small>Este <?= $selectedCategory==='navbar'?'Navbar':'Footer' ?> es global: aparece en todas las páginas y su posición está protegida.</small></div>
                    <?php else: ?>
                    <div class="inspector-actions-grid">
                        <form action="index.php?route=components.move" method="post">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>"><input type="hidden" name="instance_id" value="<?= (int)$selectedInstance['id'] ?>"><input type="hidden" name="direction" value="up">
                            <button class="button button-secondary button-small button-full" type="submit">↑ Subir</button>
                        </form>
                        <form action="index.php?route=components.move" method="post">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>"><input type="hidden" name="instance_id" value="<?= (int)$selectedInstance['id'] ?>"><input type="hidden" name="direction" value="down">
                            <button class="button button-secondary button-small button-full" type="submit">↓ Bajar</button>
                        </form>
                    </div>
                    <?php endif; ?>
                    <form class="inspector-delete-form" action="index.php?route=components.remove" method="post" data-confirm="¿Quitar esta sección de la página? Podremos añadirla de nuevo desde la biblioteca.">
                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>"><input type="hidden" name="instance_id" value="<?= (int)$selectedInstance['id'] ?>">
                        <button class="button button-danger-soft button-small button-full" type="submit"><?= $selectedIsGlobal ? 'Eliminar ' . ($selectedCategory==='navbar'?'Navbar':'Footer') . ' global' : 'Quitar sección' ?></button>
                    </form>
                </section>
            <?php endif; ?>
        <?php else: ?>
            <div class="inspector-head"><span class="eyebrow">PROPIEDADES</span><strong>Página</strong></div>
            <section class="inspector-section is-open">
                <div class="inspector-static-title">Información</div>
                <div class="inspector-content">
                    <?php if ($canBuild): ?><form method="post" action="index.php?route=pages.rename" class="form-stack"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>"><label class="field compact-field"><span>Nombre</span><input name="nombre" value="<?= htmlspecialchars($page['nombre']) ?>" required></label><button class="button button-secondary button-small" type="submit">Renombrar página</button></form><?php else: ?><label class="field compact-field"><span>Nombre</span><input value="<?= htmlspecialchars($page['nombre']) ?>" disabled></label><?php endif; ?>
                    <label class="field compact-field"><span>Dirección</span><input value="/<?= htmlspecialchars($page['slug']) ?>" disabled></label>
                    <div class="inspector-meta"><span>Estado</span><strong><?= htmlspecialchars(ucfirst($page['estado'])) ?></strong></div>
                    <div class="inspector-meta"><span>Página de inicio</span><strong><?= (int)$page['es_inicio'] === 1 ? 'Sí' : 'No' ?></strong></div>
                </div>
            </section>
            <div class="inspector-note"><span class="inspector-note-dot"></span><p>Haz clic sobre una sección del canvas o de la lista izquierda para editarla.</p></div>
        <?php endif; ?>
    </aside>
</div>

<?php if ($canBuild): ?>
<div class="modal-backdrop" id="builder-page-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="builder-new-page-title">
        <div class="modal-head">
            <div><span class="eyebrow">NUEVA PÁGINA</span><h2 id="builder-new-page-title">Crear página</h2><p>Solo dinos el nombre. Blumi prepara la dirección automáticamente.</p></div>
            <button class="icon-button" type="button" data-close-modal aria-label="Cerrar">×</button>
        </div>
        <form method="post" action="index.php?route=pages.create" class="modal-body form-stack">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
            <input type="hidden" name="proyecto_id" value="<?= (int)$page['proyecto_id'] ?>">
            <label class="field"><span>Nombre de la página *</span><input type="text" name="nombre" placeholder="Ej. Productos" required><small>Al crearla, Blumi la abrirá inmediatamente y mantendrá el Navbar y Footer globales.</small></label>
            <details class="advanced-options"><summary>Opciones avanzadas</summary><div class="advanced-options-body"><label class="field"><span>Dirección personalizada <em>opcional</em></span><input type="text" name="slug" placeholder="productos"><small>Si la dejas vacía, se genera desde el nombre.</small></label></div></details>
            <div class="modal-actions"><button class="button button-secondary" type="button" data-close-modal>Cancelar</button><button class="button button-primary" type="submit">Crear y abrir</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canBuild): ?>
<?php
$builderCategoryMap=[];
foreach($library as $component){
    $lid=(int)($component['library_id']??0); $slug=(string)($component['categoria_slug']??'custom');
    if(!isset($builderCategoryMap[$lid][$slug])) $builderCategoryMap[$lid][$slug]=['name'=>(string)($component['categoria_nombre']??$slug),'count'=>0];
    $builderCategoryMap[$lid][$slug]['count']++;
}
$preferredHasComponents=false;
foreach($library as $component){ if((int)($component['library_id']??0)===(int)($preferredLibraryId??0)){ $preferredHasComponents=true; break; } }
$initialLibraryId=$preferredHasComponents?(int)$preferredLibraryId:0;
$projectLibraries=array_values(array_filter(($componentLibraries??[]),fn($lib)=>(int)($lib['proyecto_id']??0)===(int)$page['proyecto_id']));
$canvasFactoryAutoOpen=!empty($_GET['open_component_creator']);
?>
<div class="modal-backdrop component-library-modal" id="component-library" aria-hidden="true" data-canvas-factory-auto-open="<?= $canvasFactoryAutoOpen?'1':'0' ?>">
    <div class="modal-card modal-card-library" role="dialog" aria-modal="true" aria-labelledby="component-library-title">
        <div class="modal-head">
            <div><span class="eyebrow">BIBLIOTECA</span><h3 id="component-library-title">Agregar una sección</h3><p>Elige una librería y filtra por tipo. Solo se muestran componentes aprobados.</p></div>
            <button class="modal-close" type="button" data-close-modal aria-label="Cerrar">×</button>
        </div>
        <div class="canvas-factory-tabs" role="tablist" aria-label="Agregar sección">
            <button type="button" class="is-active" data-canvas-factory-tab="library">Desde librería</button>
            <button type="button" data-canvas-factory-tab="create">✦ Crear nuevo componente</button>
        </div>
        <div data-canvas-factory-panel="library">
        <div class="component-library-toolbar" data-library-browser>
            <label class="library-select-field"><span>Librería</span><select data-library-filter>
                <option value="0" <?= $initialLibraryId===0?'selected':'' ?>>Todas las librerías</option>
                <?php foreach(($componentLibraries??[]) as $lib): ?>
                    <option value="<?= (int)$lib['id'] ?>" <?= $initialLibraryId===(int)$lib['id']?'selected':'' ?>><?= htmlspecialchars($lib['nombre']) ?> · <?= (int)$lib['approved_count'] ?></option>
                <?php endforeach; ?>
            </select></label>
            <input class="component-search" type="search" placeholder="Buscar componente..." data-component-search>
        </div>
        <div class="component-category-chips" data-component-category-chips>
            <button type="button" class="is-active" data-category-filter="all">Todos <span><?= count($library) ?></span></button>
            <?php
            $globalCats=[];
            foreach($library as $component){$slug=(string)($component['categoria_slug']??'custom'); if(!isset($globalCats[$slug]))$globalCats[$slug]=['name'=>(string)$component['categoria_nombre'],'count'=>0];$globalCats[$slug]['count']++;}
            uasort($globalCats,fn($a,$b)=>strcmp($a['name'],$b['name']));
            foreach($globalCats as $slug=>$meta): ?>
                <button type="button" data-category-filter="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($meta['name']) ?> <span><?= (int)$meta['count'] ?></span></button>
            <?php endforeach; ?>
        </div>
        <div class="component-library-grid" data-component-grid>
            <?php foreach ($library as $component): ?>
                <?php
                    $previewCss = str_ireplace('</style', '<\/style', (string)($component['css'] ?? ''));
                    $previewHtml = (string)($component['preview_html'] ?? '');
                    $previewDoc = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                        . '<style>html,body{margin:0;padding:0;min-height:100%;overflow:hidden;background:var(--site-background,#fff)}*{box-sizing:border-box}'
                        . ':root{--site-primary:' . htmlspecialchars((string)($design['primary_color'] ?? '#2043BF'), ENT_QUOTES) . ';'
                        . '--site-secondary:' . htmlspecialchars((string)($design['secondary_color'] ?? '#3974DC'), ENT_QUOTES) . ';'
                        . '--site-accent:' . htmlspecialchars((string)($design['accent_color'] ?? '#E7DF68'), ENT_QUOTES) . ';'
                        . '--site-background:' . htmlspecialchars((string)($design['background_color'] ?? '#FFFFFF'), ENT_QUOTES) . ';'
                        . '--site-surface:' . htmlspecialchars((string)($design['surface_color'] ?? '#FFFFFF'), ENT_QUOTES) . ';'
                        . '--site-text:' . htmlspecialchars((string)($design['text_color'] ?? '#1D1D1F'), ENT_QUOTES) . ';'
                        . '--site-muted:' . htmlspecialchars((string)($design['muted_color'] ?? '#71717A'), ENT_QUOTES) . ';'
                        . '--site-radius:' . htmlspecialchars((string)($design['border_radius'] ?? '12px'), ENT_QUOTES) . ';'
                        . '--site-container-normal:' . htmlspecialchars((string)($design['container_width'] ?? '1280px'), ENT_QUOTES) . ';--site-container-wide:1600px;--site-container:var(--site-container-normal);--site-gutter:32px;'
                        . '--site-section-space-base:' . htmlspecialchars((string)($design['section_spacing'] ?? '96px'), ENT_QUOTES) . ';--site-section-space:var(--site-section-space-base);'
                        . '--site-font-heading:' . json_encode((string)($design['heading_font'] ?? 'Inter')) . ',sans-serif;'
                        . '--site-font-body:' . json_encode((string)($design['body_font'] ?? 'Inter')) . ',sans-serif;'
                        . '--font-display-xl:clamp(72px,4.3vw,108px);--font-display:clamp(60px,3.55vw,88px);--font-h1:clamp(48px,2.9vw,72px);--font-h2:clamp(40px,2.4vw,60px);--font-h3:clamp(32px,1.9vw,48px);--font-h4:clamp(24px,1.45vw,34px);--font-body-lg:clamp(18px,.95vw,20px);--font-body:clamp(16px,.85vw,18px);--font-small:clamp(14px,.74vw,15px);--font-caption:12px;'
                        . '--space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;--space-5:20px;--space-6:24px;--space-8:clamp(32px,2vw,44px);--space-10:clamp(40px,2.4vw,52px);--space-12:clamp(48px,2.8vw,60px);--space-16:clamp(64px,3.6vw,80px);--space-20:clamp(80px,4.4vw,96px);--space-24:clamp(96px,5.2vw,120px);'
                        . '--radius-sm:8px;--radius-md:12px;--radius-lg:16px;--radius-xl:20px;--radius-pill:999px;--container-width:var(--site-container)}'
                        . 'body{font-family:var(--site-font-body);color:var(--site-text)}h1,h2,h3,h4,h5,h6{font-family:var(--site-font-heading)}'
                        . '.bl-page-section{width:100%;margin:0;padding:0}.bl-section-background{width:100%;background:var(--site-background)}.bl-site-frame{width:100%;max-width:calc(var(--site-container) + (var(--site-gutter) * 2));margin:0 auto;padding-left:var(--site-gutter);padding-right:var(--site-gutter);box-sizing:border-box}.bl-component-surface{width:100%;background:transparent}.bl-component-stage>*{width:100%!important;max-width:none!important;margin:0!important}@media(min-width:1600px){:root{--site-gutter:48px;--site-section-space:calc(var(--site-section-space-base) + 8px)}}@media(min-width:1900px){:root{--site-container-wide:1760px;--site-gutter:56px;--site-section-space:calc(var(--site-section-space-base) + 16px)}}@media(min-width:2400px){:root{--site-container-wide:2080px;--site-gutter:64px;--site-section-space:calc(var(--site-section-space-base) + 32px)}}'
                        . $previewCss . '</style></head><body><section class="bl-page-section"><div class="bl-section-background"><div class="bl-site-frame"><div class="bl-component-surface"><div class="bl-component-stage">' . $previewHtml . '</div></div></div></div></section></body></html>';
                ?>
                <article class="library-component-card" data-component-card data-library-id="<?= (int)($component['library_id']??0) ?>" data-category="<?= htmlspecialchars((string)($component['categoria_slug']??'custom')) ?>" data-search="<?= htmlspecialchars(strtolower($component['nombre'] . ' ' . $component['categoria_nombre'] . ' ' . ($component['library_name']??'') . ' ' . $component['codigo'])) ?>">
                    <div class="library-real-preview"><iframe title="Vista previa de <?= htmlspecialchars($component['nombre']) ?>" loading="lazy" tabindex="-1" aria-hidden="true" srcdoc="<?= htmlspecialchars($previewDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></iframe><span class="library-preview-badge"><?= htmlspecialchars($component['categoria_nombre']) ?></span></div>
                    <div class="library-card-body"><div><strong><?= htmlspecialchars($component['nombre']) ?></strong><small><?= htmlspecialchars((string)($component['library_name']??'Sin librería')) ?></small></div>
                        <form action="index.php?route=components.add" method="post"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>"><input type="hidden" name="component_id" value="<?= (int)$component['id'] ?>"><button class="button button-primary button-small" type="submit">Agregar</button></form>
                    </div>
                </article>
            <?php endforeach; ?>
            <div class="library-filter-empty" data-library-empty hidden>No hay componentes aprobados con estos filtros.</div>
        </div>
        </div>

        <div class="canvas-factory-create-panel" data-canvas-factory-panel="create" hidden>
            <div class="canvas-factory-context">
                <span class="canvas-factory-context-mark">✦</span>
                <div><small>CREANDO PARA</small><strong><?= htmlspecialchars($page['proyecto_nombre']) ?> · <?= htmlspecialchars($page['nombre']) ?></strong><span>Al aprobar, Blumi insertará el componente automáticamente en esta página.</span></div>
            </div>

            <div class="canvas-factory-library-setup" data-canvas-library-setup <?= $projectLibraries?'hidden':'' ?>>
                <strong>Primero crea una librería para este proyecto</strong>
                <p>Todo componente debe pertenecer a una librería. La creamos aquí sin salir del Canvas.</p>
                <div class="canvas-factory-inline-row">
                    <input type="text" maxlength="120" value="<?= htmlspecialchars($page['proyecto_nombre']) ?>" data-canvas-library-name aria-label="Nombre de la librería">
                    <button class="button button-primary button-small" type="button" data-create-canvas-library>Crear librería</button>
                </div>
                <span class="canvas-factory-inline-error" data-canvas-library-error hidden></span>
            </div>

            <form action="index.php?route=component_factory.generate" method="post" enctype="multipart/form-data" class="canvas-factory-form" data-canvas-ai-generation-form <?= $projectLibraries?'':'hidden' ?>>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
                <input type="hidden" name="canvas_page_id" value="<?= (int)$page['id'] ?>">
                <input type="hidden" name="project_id" value="<?= (int)$page['proyecto_id'] ?>" data-canvas-project-id>
                <div class="canvas-factory-form-grid">
                    <label class="field"><span>Librería *</span><select name="library_id" required data-canvas-factory-library><option value="">Selecciona...</option><?php foreach($projectLibraries as $lib): ?><option value="<?= (int)$lib['id'] ?>" <?= (int)($preferredLibraryId??0)===(int)$lib['id']?'selected':'' ?>><?= htmlspecialchars($lib['nombre']) ?></option><?php endforeach; ?></select></label>
                    <label class="field"><span>Tipo *</span><select name="category_id" required><option value="">Selecciona...</option><?php foreach(($factoryCategories??[]) as $cat): ?><?php if(in_array(strtolower((string)($cat['slug']??'')),['navbar','footer'],true)) continue; ?><option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option><?php endforeach; ?></select></label>
                </div>
                <label class="field"><span>Nombre del componente <em>opcional</em></span><input name="component_name" maxlength="160" placeholder="Ej. Hero editorial de campaña"></label>
                <label class="field canvas-factory-reference"><span>Referencia visual <em>opcional</em></span><input type="file" name="reference" accept="image/png,image/jpeg,image/webp,image/avif,.jpg,.jpeg,.jfif,.png,.webp,.avif" data-canvas-factory-reference><small>JPG, PNG o WebP · máximo 8 MB. También puedes crear solo con una descripción.</small></label>
                <label class="field"><span>¿Qué quieres crear?</span><textarea name="instruction" rows="6" data-canvas-factory-intent placeholder="Ej. Quiero un hero editorial con imagen a la derecha, texto grande y un CTA. En móvil apila el contenido."></textarea><small>Descríbelo con lenguaje visual. Blumi aplica automáticamente el sistema de diseño del proyecto.</small></label>
                <div class="factory-inline-error" data-canvas-factory-error hidden>Sube una referencia o describe el componente que quieres crear.</div>
                <div class="canvas-factory-contract"><span>Blumi mantendrá:</span><p>Design system del proyecto · Desktop/Tablet/Mobile · contenido editable · HTML/CSS/JS vanilla · sin dependencias externas.</p></div>
                <button class="button button-primary button-full canvas-factory-create-button" type="submit">✦ Crear componente con Blumi</button>
            </form>
        </div>
    </div>
</div>
<script>
(function(){
 const root=document.querySelector('[data-library-browser]'); if(!root)return;
 const select=root.querySelector('[data-library-filter]'), search=root.querySelector('[data-component-search]');
 const chips=[...document.querySelectorAll('[data-category-filter]')], cards=[...document.querySelectorAll('[data-component-card]')], empty=document.querySelector('[data-library-empty]');
 let category='all';
 const refresh=()=>{ const lib=select?.value||'0', q=(search?.value||'').trim().toLowerCase(); let shown=0;
   cards.forEach(card=>{const okLib=lib==='0'||card.dataset.libraryId===lib; const okCat=category==='all'||card.dataset.category===category; const okSearch=!q||(card.dataset.search||'').includes(q); const show=okLib&&okCat&&okSearch; card.hidden=!show; if(show)shown++;});
   chips.forEach(chip=>{ if(chip.dataset.categoryFilter==='all'){chip.hidden=false;return;} const slug=chip.dataset.categoryFilter; const exists=cards.some(card=>(lib==='0'||card.dataset.libraryId===lib)&&card.dataset.category===slug); chip.hidden=!exists; if(!exists&&category===slug){category='all'; chips.forEach(c=>c.classList.toggle('is-active',c.dataset.categoryFilter==='all'));}});
   if(empty) empty.hidden=shown!==0;
 };
 select?.addEventListener('change',()=>{category='all';chips.forEach(c=>c.classList.toggle('is-active',c.dataset.categoryFilter==='all'));refresh();}); search?.addEventListener('input',refresh);
 chips.forEach(chip=>chip.addEventListener('click',()=>{category=chip.dataset.categoryFilter||'all';chips.forEach(c=>c.classList.toggle('is-active',c===chip));refresh();})); refresh();
})();
</script>
<script>
(function(){
 const modal=document.getElementById('component-library'); if(!modal)return;
 const tabs=[...modal.querySelectorAll('[data-canvas-factory-tab]')];
 const panels=[...modal.querySelectorAll('[data-canvas-factory-panel]')];
 const setTab=(name)=>{
   tabs.forEach(btn=>btn.classList.toggle('is-active',btn.dataset.canvasFactoryTab===name));
   panels.forEach(panel=>panel.hidden=panel.dataset.canvasFactoryPanel!==name);
   const title=modal.querySelector('#component-library-title');
   const copy=title?.nextElementSibling;
   if(title) title.textContent=name==='create'?'Crear un componente':'Agregar una sección';
   if(copy) copy.textContent=name==='create'?'Créalo, revísalo y al aprobarlo Blumi lo insertará en esta página.':'Elige una librería y filtra por tipo. Solo se muestran componentes aprobados.';
 };
 tabs.forEach(btn=>btn.addEventListener('click',()=>setTab(btn.dataset.canvasFactoryTab||'library')));
 if(modal.dataset.canvasFactoryAutoOpen==='1'){ modal.classList.add('is-open');modal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');setTab('create'); }

 const createLibBtn=modal.querySelector('[data-create-canvas-library]');
 const libName=modal.querySelector('[data-canvas-library-name]');
 const libError=modal.querySelector('[data-canvas-library-error]');
 const setup=modal.querySelector('[data-canvas-library-setup]');
 const form=modal.querySelector('[data-canvas-ai-generation-form]');
 const libSelect=modal.querySelector('[data-canvas-factory-library]');
 const projectId=modal.querySelector('[data-canvas-project-id]')?.value||'';
 createLibBtn?.addEventListener('click',async()=>{
   const name=(libName?.value||'').trim();
   if(!name){if(libError){libError.hidden=false;libError.textContent='Escribe un nombre para la librería.';}return;}
   createLibBtn.disabled=true;createLibBtn.textContent='Creando…';if(libError)libError.hidden=true;
   try{
     const body=new FormData();body.append('_csrf','<?= htmlspecialchars(Csrf::token(),ENT_QUOTES) ?>');body.append('project_id',projectId);body.append('name',name);
     const response=await fetch('index.php?route=component_library.create_inline',{method:'POST',body,headers:{'X-Requested-With':'XMLHttpRequest'}});
     const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'No se pudo crear la librería.');
     if(libSelect){libSelect.innerHTML='';const option=document.createElement('option');option.value=String(data.library.id);option.textContent=data.library.name;option.selected=true;libSelect.appendChild(option);}
     if(setup)setup.hidden=true;if(form)form.hidden=false;
   }catch(error){if(libError){libError.hidden=false;libError.textContent=error.message||'No se pudo crear la librería.';}}
   finally{createLibBtn.disabled=false;createLibBtn.textContent='Crear librería';}
 });

 const reference=modal.querySelector('[data-canvas-factory-reference]');
 const intent=modal.querySelector('[data-canvas-factory-intent]');
 const formError=modal.querySelector('[data-canvas-factory-error]');
 form?.addEventListener('submit',(event)=>{
   const hasReference=!!(reference?.files?.length),hasIntent=!!(intent?.value.trim());
   if(!form.checkValidity()){event.preventDefault();form.reportValidity();return;}
   if(!hasReference&&!hasIntent){event.preventDefault();if(formError)formError.hidden=false;intent?.focus();return;}
   if(formError)formError.hidden=true;
   const loader=document.querySelector('[data-canvas-factory-loader]');
   if(loader){loader.hidden=false;document.body.classList.add('canvas-factory-busy');}
   form.querySelectorAll('button[type="submit"]').forEach(btn=>{btn.disabled=true;btn.textContent='Creando…';});
 });
 const clearError=()=>{if(formError)formError.hidden=true;};reference?.addEventListener('change',clearError);intent?.addEventListener('input',clearError);
})();
</script>
<?php endif; ?>

<div class="canvas-factory-loader" data-canvas-factory-loader hidden aria-live="polite" aria-busy="true">
    <div class="canvas-factory-loader-card">
        <div class="canvas-factory-loader-mark">B</div>
        <strong>Blumi está creando el componente</strong>
        <span>Interpretando tu idea, construyendo la pieza y preparando Desktop, Tablet y Mobile…</span>
        <div class="canvas-factory-loader-line"><i></i></div>
        <small>Cuando termine pasarás a revisión antes de insertarlo en la página.</small>
    </div>
</div>

<div class="blumi-personalize-loader" data-personalize-loader hidden aria-live="polite" aria-busy="true">
    <div class="blumi-personalize-loader-card">
        <div class="blumi-personalize-loader-mark">✦</div>
        <strong data-personalize-loader-title>Blumi está preparando el cambio</strong>
        <span data-personalize-loader-message>Analizando el elemento y validando una propuesta segura…</span>
        <div class="blumi-personalize-loader-line"><i></i></div>
    </div>
</div>

<?php if ($success): ?><div class="toast toast-success"><strong>Listo</strong><span><?= htmlspecialchars($success) ?></span></div><?php endif; ?>
<?php if ($error): ?><div class="toast toast-error"><strong>Revisa esto</strong><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
<?php if (trim((string)($componentJs ?? '')) !== ''): ?><script><?= $safeComponentJs ?></script><?php endif; ?>
<script src="assets/js/app.js?v=9.1"></script>
</body>
</html>
