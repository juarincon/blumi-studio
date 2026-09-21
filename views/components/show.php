<?php
use App\Helpers\Csrf;
$pageTitle=htmlspecialchars($component['nombre']).' · Componentes · Blumi'; $headerTitle=$component['nombre']; $showNewProjectButton=false;
require dirname(__DIR__).'/partials/head.php'; require dirname(__DIR__).'/partials/app_shell_start.php';
$success=$_SESSION['flash_success']??null;$error=$_SESSION['flash_error']??null;unset($_SESSION['flash_success'],$_SESSION['flash_error']);
$approved=$component['estado']==='aprobado';
$currentLibraryId=(int)($component['library_id']??0); $currentCategoryId=(int)$component['categoria_id'];
$config=json_decode((string)($component['schema_configuracion']??'{}'),true) ?: [];
$interpretation=is_array($config['interpretation']??null)?$config['interpretation']:null;
$canvasPageId=(int)($canvasPageId??0);
?>
<div class="component-review-head">
  <div>
    <span class="eyebrow"><?= htmlspecialchars($component['categoria_nombre']) ?></span>
    <h2><?= htmlspecialchars($component['nombre']) ?></h2>
    <p><code><?= htmlspecialchars($component['codigo']) ?></code> · <?= $approved?'Aprobado e inmutable':'Pendiente de revisión' ?><?= $component['library_name']?' · '.htmlspecialchars($component['library_name']):'' ?><?php if(!empty($component['parent_component_id'])): ?> · Variante de #<?= (int)$component['parent_component_id'] ?><?php endif; ?></p>
  </div>
  <div class="component-review-head-actions">
    <?php if($canvasPageId>0): ?><a class="button button-secondary" href="index.php?route=builder&page_id=<?= $canvasPageId ?>&open_component_creator=1">← Volver al Canvas</a><?php else: ?><a class="button button-secondary" href="index.php?route=component_factory<?= $currentLibraryId?'&library_id='.$currentLibraryId:'' ?>">← Biblioteca</a><?php endif; ?>
    <?php if($approved && $currentLibraryId>0): ?><a class="button button-primary" href="index.php?route=component_factory.create&library_id=<?= $currentLibraryId ?>">+ Nuevo componente</a><?php endif; ?>
  </div>
</div>

<?php if($canvasPageId>0): ?>
<div class="canvas-factory-review-context">
  <span class="canvas-factory-review-mark">✦</span>
  <div><strong>Creado desde el Canvas</strong><span>Al aprobarlo, Blumi lo guardará en la librería seleccionada y lo insertará automáticamente en la página.</span></div>
</div>
<?php endif; ?>

<?php if($interpretation): ?>
<section class="factory-interpretation-card">
  <div class="factory-interpretation-head"><div><span class="eyebrow">BLUMI INTERPRETÓ</span><h3><?= htmlspecialchars((string)($interpretation['summary']??'Interpretación del componente')) ?></h3></div><span class="factory-interpretation-mode"><?= htmlspecialchars(str_replace('_',' ',(string)($interpretation['mode']??''))) ?></span></div>
  <div class="factory-interpretation-grid">
    <?php if(!empty($interpretation['keep']) && is_array($interpretation['keep'])): ?><div><strong>Conservar</strong><ul><?php foreach(array_slice($interpretation['keep'],0,6) as $item): ?><li><?= htmlspecialchars((string)$item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if(!empty($interpretation['change']) && is_array($interpretation['change'])): ?><div><strong>Cambiar</strong><ul><?php foreach(array_slice($interpretation['change'],0,6) as $item): ?><li><?= htmlspecialchars((string)$item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if(!empty($interpretation['editable']) && is_array($interpretation['editable'])): ?><div><strong>Editable</strong><p><?= htmlspecialchars(implode(' · ',array_map('strval',array_slice($interpretation['editable'],0,8)))) ?></p></div><?php endif; ?>
    <div><strong>Responsive</strong><p><?= htmlspecialchars((string)($interpretation['responsive']??'Adaptación automática')) ?></p></div>
  </div>
  <?php if(!empty($interpretation['recommended_width'])): ?><div class="factory-width-recommendation">Ancho sugerido: <strong><?= htmlspecialchars((string)$interpretation['recommended_width']) ?></strong></div><?php endif; ?>
</section>
<?php endif; ?>

<section class="review-reference-stack">
  <div class="panel-title"><strong>Referencia original</strong><span>Solo comparación</span></div>
  <div class="review-reference-canvas"><?php if($component['reference_image_path']): ?><img src="<?= htmlspecialchars($component['reference_image_path']) ?>" alt="Referencia original"><?php else: ?><div class="reference-empty">Generado sin referencia visual</div><?php endif; ?></div>
</section>

<section class="review-result-stack">
  <div class="panel-title review-result-toolbar">
    <div><strong>Resultado Blumi</strong><span>Revisa la pieza a un ancho real antes de aprobar.</span></div>
    <div class="review-preview-controls">
      <div class="factory-viewport-switch"><button type="button" data-review-viewport="1440" class="is-active">Desktop</button><button type="button" data-review-viewport="768">Tablet</button><button type="button" data-review-viewport="390">Mobile</button></div>
      <div class="review-zoom-switch"><button type="button" data-review-zoom="0.5">50%</button><button type="button" data-review-zoom="0.75" class="is-active">75%</button><button type="button" data-review-zoom="1">100%</button></div>
    </div>
  </div>
  <div class="component-review-preview-viewport" data-review-shell><div class="component-review-preview-scale" data-review-scale><iframe id="componentPreviewFrame" src="index.php?route=component_factory.preview&id=<?= (int)$component['id'] ?>" sandbox="allow-scripts"></iframe></div></div>
</section>

<section class="component-css-editor" id="component-css-editor">
  <div class="component-css-editor-head">
    <div>
      <span class="eyebrow">Ajuste fino</span>
      <h3>CSS del componente</h3>
      <p>Puedes modificar propiedades, valores, animaciones, media queries y efectos. Blumi no permite cambiar, agregar ni eliminar nombres de clases.</p>
    </div>
    <div class="css-editor-status"><?= $approved?'Solo lectura · duplica para editar':'Editable antes de aprobar' ?></div>
  </div>
  <form action="index.php?route=component_factory.save_css" method="post" class="component-css-form" data-component-working data-loader-title="Guardando CSS" data-loader-message="Validando clases y actualizando el preview…">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$component['id'] ?>">
    <?php if($canvasPageId>0): ?><input type="hidden" name="canvas_page_id" value="<?= $canvasPageId ?>"><?php endif; ?>
    <textarea id="componentCssTextarea" name="css" spellcheck="false" <?= $approved?'readonly':'' ?>><?= htmlspecialchars((string)($component['css']??'')) ?></textarea>
    <div class="component-css-actions">
      <span class="css-editor-note">HTML, clases y JavaScript permanecen bloqueados.</span>
      <button class="button button-secondary" type="button" id="previewCssButton">Aplicar al preview</button>
      <button class="button button-primary" type="submit" <?= $approved?'disabled':'' ?>>Guardar CSS</button>
    </div>
  </form>
</section>

<div class="component-approval-dock">
<?php if(!$approved): ?>
<form action="index.php?route=component_factory.approve" method="post" class="approval-classification-form" data-component-working data-loader-title="Guardando componente" data-loader-message="Clasificando, bloqueando y preparando la librería…">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$component['id'] ?>">
  <?php if($canvasPageId>0): ?><input type="hidden" name="canvas_page_id" value="<?= $canvasPageId ?>"><?php endif; ?>
  <?php if($canvasPageId>0): ?>
  <label><span>Librería</span><input type="hidden" name="library_id" value="<?= $currentLibraryId ?>"><input type="text" value="<?= htmlspecialchars((string)$component['library_name']) ?>" readonly></label>
  <?php else: ?>
  <label><span>Librería</span><select name="library_id" required><?php foreach($libraries as $lib): ?><option value="<?= (int)$lib['id'] ?>" <?= $currentLibraryId===(int)$lib['id']?'selected':'' ?>><?= htmlspecialchars($lib['nombre']) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <label><span>Tipo</span><select name="category_id" required><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>" <?= $currentCategoryId===(int)$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['nombre']) ?></option><?php endforeach; ?></select></label>
  <label class="approval-name"><span>Nombre</span><input name="name" required maxlength="160" value="<?= htmlspecialchars($component['nombre']) ?>"></label>
  <button class="button button-primary" type="submit"><?= $canvasPageId>0?'Aprobar e insertar en página':'Aprobar y guardar' ?></button>
</form>
<?php else: ?><div class="approval-locked-copy"><div><strong>Componente aprobado</strong><span><?= htmlspecialchars((string)$component['library_name']) ?> · <?= htmlspecialchars($component['categoria_nombre']) ?></span></div><?php if($canvasPageId>0): ?><form action="index.php?route=components.add" method="post" style="margin:0"><input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="page_id" value="<?= $canvasPageId ?>"><input type="hidden" name="component_id" value="<?= (int)$component['id'] ?>"><button class="button button-primary" type="submit">Insertar en esta página</button></form><?php elseif($currentLibraryId>0): ?><a class="button button-primary" href="index.php?route=component_factory.create&library_id=<?= $currentLibraryId ?>">+ Nuevo componente</a><?php endif; ?></div><?php endif; ?>
<form action="index.php?route=component_factory.duplicate" method="post" class="variant-form" data-component-working data-loader-title="Creando variante con Blumi AI" data-loader-message="Conservando la estructura y aplicando únicamente tu instrucción…">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>"><input type="hidden" name="id" value="<?= (int)$component['id'] ?>"><?php if($canvasPageId>0): ?><input type="hidden" name="canvas_page_id" value="<?= $canvasPageId ?>"><?php endif; ?><input name="instruction" required placeholder="Ej. reduce el alto y conserva todo lo demás"><button class="button button-secondary" type="submit">Duplicar + cambiar con IA</button>
</form>
</div>

<div class="component-code-grid">
  <details><summary>HTML · solo lectura</summary><pre><?= htmlspecialchars($component['html_template']) ?></pre></details>
  <details><summary>JS · solo lectura</summary><pre><?= htmlspecialchars((string)$component['js']) ?></pre></details>
  <details><summary>Campos</summary><pre><?= htmlspecialchars(json_encode(json_decode((string)$component['schema_campos'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre></details>
</div>

<div class="component-working-overlay" id="componentWorkingOverlay" hidden aria-live="polite" aria-busy="true">
  <div class="component-working-card">
    <div class="component-working-spinner" aria-hidden="true"></div>
    <strong id="componentWorkingTitle">Procesando</strong>
    <span id="componentWorkingMessage">Espera un momento…</span>
  </div>
</div>

<script>
(function(){
  const frame=document.getElementById('componentPreviewFrame'),scale=document.querySelector('[data-review-scale]'),shell=document.querySelector('[data-review-shell]');
  if(frame&&scale&&shell){
    let width=1440,zoom=.75;
    const draw=()=>{scale.style.width=width+'px';frame.style.width=width+'px';scale.style.transform='scale('+zoom+')';shell.style.setProperty('--review-scaled-width',(width*zoom)+'px');scale.style.height=Math.max(420,720*zoom)+'px';};
    document.querySelectorAll('[data-review-viewport]').forEach(btn=>btn.addEventListener('click',()=>{width=Number(btn.dataset.reviewViewport)||1440;document.querySelectorAll('[data-review-viewport]').forEach(b=>b.classList.toggle('is-active',b===btn));draw();}));
    document.querySelectorAll('[data-review-zoom]').forEach(btn=>btn.addEventListener('click',()=>{zoom=Number(btn.dataset.reviewZoom)||.75;document.querySelectorAll('[data-review-zoom]').forEach(b=>b.classList.toggle('is-active',b===btn));draw();}));
    draw();
  }

  const cssField=document.getElementById('componentCssTextarea');
  const previewCss=document.getElementById('previewCssButton');
  if(cssField&&previewCss&&frame){
    previewCss.addEventListener('click',()=>{
      try{
        const doc=frame.contentDocument||frame.contentWindow.document;
        let style=doc.getElementById('blumiManualCssPreview');
        if(!style){style=doc.createElement('style');style.id='blumiManualCssPreview';doc.head.appendChild(style);}
        style.textContent=cssField.value;
        previewCss.textContent='Aplicado al preview';
        setTimeout(()=>previewCss.textContent='Aplicar al preview',1600);
      }catch(e){ alert('No se pudo aplicar el CSS al preview.'); }
    });
  }

  const overlay=document.getElementById('componentWorkingOverlay'),title=document.getElementById('componentWorkingTitle'),message=document.getElementById('componentWorkingMessage');
  let busy=false;
  document.querySelectorAll('form[data-component-working]').forEach(form=>form.addEventListener('submit',(event)=>{
    if(busy){ event.preventDefault(); return; }
    if(!form.checkValidity()) return;
    busy=true;
    if(overlay){
      title.textContent=form.dataset.loaderTitle||'Procesando';
      message.textContent=form.dataset.loaderMessage||'Espera un momento…';
      overlay.hidden=false;
      document.body.classList.add('component-review-busy');
    }
    form.querySelectorAll('button, input, select, textarea').forEach(field=>{
      if(field.type!=='hidden') field.setAttribute('aria-disabled','true');
    });
    form.querySelectorAll('button[type="submit"]').forEach(btn=>{btn.disabled=true;btn.dataset.originalText=btn.textContent;btn.textContent='Procesando…';});
  }));
})();
</script>
<?php if($success): ?><div class="toast toast-success"><strong>Listo</strong><span><?= htmlspecialchars($success) ?></span></div><?php endif; ?><?php if($error): ?><div class="toast toast-error"><strong>Revisa esto</strong><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
<?php require dirname(__DIR__).'/partials/app_shell_end.php'; ?>
