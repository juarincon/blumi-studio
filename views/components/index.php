<?php
use App\Helpers\Csrf;
$pageTitle='Componentes · Blumi Studio'; $headerTitle='Componentes'; $showNewProjectButton=false;
require dirname(__DIR__).'/partials/head.php'; require dirname(__DIR__).'/partials/app_shell_start.php';
$success=$_SESSION['flash_success']??null; $error=$_SESSION['flash_error']??null; unset($_SESSION['flash_success'],$_SESSION['flash_error']);
$mode=$mode??'libraries';
?>
<?php if($mode==='libraries'): ?>
<div class="page-intro-row component-hub-head"><div><span class="eyebrow">BIBLIOTECAS</span><h2>Componentes</h2><p>Organiza las piezas por proyecto o colección. Dentro de cada librería podrás filtrar por Navbar, Hero, CTA y demás tipos disponibles.</p></div><div class="page-intro-actions"><button class="button button-secondary" type="button" data-open-modal="new-library-modal">+ Nueva librería</button><a class="button button-primary" href="index.php?route=component_factory.create">+ Nuevo componente</a></div></div>
<div class="component-library-hub-grid">
<?php foreach($libraries as $lib): ?>
<a class="component-library-hub-card" href="index.php?route=component_factory&library_id=<?= (int)$lib['id'] ?>">
    <div class="library-hub-top"><span class="library-hub-icon"><?= $lib['is_system']?'◆':'◇' ?></span><?php if($lib['proyecto_id']): ?><span class="mini-badge">PROYECTO</span><?php elseif($lib['is_system']): ?><span class="mini-badge">SISTEMA</span><?php endif; ?></div>
    <strong><?= htmlspecialchars($lib['nombre']) ?></strong>
    <p><?= htmlspecialchars((string)($lib['descripcion']??($lib['proyecto_nombre']?'Componentes del proyecto '.$lib['proyecto_nombre']:'Colección reutilizable'))) ?></p>
    <div class="library-hub-stats"><span><b><?= (int)$lib['component_count'] ?></b> componentes</span><span><b><?= (int)$lib['approved_count'] ?></b> aprobados</span><span><b><?= (int)$lib['category_count'] ?></b> tipos</span></div>
</a>
<?php endforeach; ?>
</div>
<div class="modal-backdrop" id="new-library-modal" aria-hidden="true"><div class="modal-card modal-card-small" role="dialog" aria-modal="true"><div class="modal-head"><div><span class="eyebrow">NUEVA LIBRERÍA</span><h3>Crear colección</h3><p>Puede ser general o estar vinculada a un proyecto.</p></div><button class="modal-close" type="button" data-close-modal>×</button></div><form class="stack-form" action="index.php?route=component_library.create" method="post"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><label class="field"><span>Nombre *</span><input name="name" required placeholder="Ej. Landing inmobiliaria"></label><label class="field"><span>Proyecto relacionado</span><select name="project_id"><option value="0">Sin proyecto</option><?php foreach(($projects??[]) as $project): ?><option value="<?= (int)$project['id'] ?>"><?= htmlspecialchars($project['nombre']) ?></option><?php endforeach; ?></select><small>Los proyectos existentes ya tienen una librería automática.</small></label><button class="button button-primary button-full" type="submit">Crear librería</button></form></div></div>
<?php else: ?>
<div class="page-intro-row component-library-detail-head"><div><a class="back-link" href="index.php?route=component_factory">← Librerías</a><span class="eyebrow"><?= !empty($selectedLibrary['proyecto_id'])?'LIBRERÍA DE PROYECTO':'LIBRERÍA' ?></span><h2><?= htmlspecialchars($selectedLibrary['nombre']) ?></h2><p><?= count($components) ?> componente<?= count($components)===1?'':'s' ?> en la selección actual.</p></div><a class="button button-primary" href="index.php?route=component_factory.create&library_id=<?= (int)$selectedLibrary['id'] ?>">+ Nuevo componente</a></div>
<div class="component-type-tabs">
<a class="<?= !$selectedCategoryId?'is-active':'' ?>" href="index.php?route=component_factory&library_id=<?= (int)$selectedLibrary['id'] ?>">Todos</a>
<?php foreach($categoryCounts as $cat): ?><a class="<?= (int)$selectedCategoryId===(int)$cat['id']?'is-active':'' ?>" href="index.php?route=component_factory&library_id=<?= (int)$selectedLibrary['id'] ?>&category_id=<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?> <span><?= (int)$cat['component_count'] ?></span></a><?php endforeach; ?>
</div>
<div class="component-factory-grid">
<?php foreach($components as $c): ?>
<a class="factory-card" href="index.php?route=component_factory.show&id=<?= (int)$c['id'] ?>">
  <div class="factory-card-preview"><?php if(!empty($c['reference_image_path'])): ?><img src="<?= htmlspecialchars($c['reference_image_path']) ?>" alt="Referencia"><?php else: ?><img src="assets/img/blumi-component-placeholder.svg" alt="Blumi placeholder"><?php endif; ?><span class="mini-badge"><?= htmlspecialchars($c['categoria_nombre']) ?></span></div>
  <div class="factory-card-body"><strong><?= htmlspecialchars($c['nombre']) ?></strong><code><?= htmlspecialchars($c['codigo']) ?></code><div class="factory-card-meta"><span class="status-dot status-<?= htmlspecialchars($c['estado']) ?>"><?= htmlspecialchars(str_replace('_',' ',$c['estado'])) ?></span><span><?= (int)$c['children_count'] ?> variantes</span><span><?= (int)$c['usage_count'] ?> usos</span></div></div>
</a>
<?php endforeach; ?>
</div>
<?php if(!$components): ?><div class="empty-panel"><h3>No hay componentes en este filtro</h3><p>Crea uno desde una captura o cambia el tipo seleccionado.</p></div><?php endif; ?>
<?php endif; ?>
<?php if($success): ?><div class="toast toast-success"><strong>Listo</strong><span><?= htmlspecialchars($success) ?></span></div><?php endif; ?>
<?php if($error): ?><div class="toast toast-error"><strong>Revisa esto</strong><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
<?php require dirname(__DIR__).'/partials/app_shell_end.php'; ?>
