<?php
use App\Helpers\Csrf;
$pageTitle='Crear componente · Blumi Studio'; $headerTitle='Nuevo componente'; $showNewProjectButton=false;
require dirname(__DIR__).'/partials/head.php'; require dirname(__DIR__).'/partials/app_shell_start.php';
$error=$_SESSION['flash_error']??null; unset($_SESSION['flash_error']);
?>
<div class="factory-create-layout factory-intent-layout">
<section class="factory-form-card factory-intent-card">
  <span class="eyebrow">BLUMI AI · CREAR</span>
  <h2>Muéstrame qué quieres construir</h2>
  <p>Sube una referencia, descríbelo con tus palabras o combina ambas. Blumi convierte tu intención en la especificación técnica y luego genera el componente.</p>

  <?php if(!empty($selectedLibrary)): ?>
  <div class="factory-library-context">
    <div><span class="factory-library-context-icon">◇</span><div><small>CREANDO EN</small><strong><?= htmlspecialchars((string)$selectedLibrary['nombre']) ?></strong><?php if(!empty($selectedLibrary['proyecto_nombre'])): ?><span>Proyecto <?= htmlspecialchars((string)$selectedLibrary['proyecto_nombre']) ?></span><?php endif; ?></div></div>
    <a href="index.php?route=component_factory">Cambiar librería</a>
  </div>
  <?php endif; ?>

  <div class="factory-input-modes" aria-label="Formas de crear un componente">
    <div><span class="factory-mode-icon">▧</span><strong>Solo referencia</strong><small>Sube una imagen y Blumi reconstruye su estructura.</small></div>
    <div><span class="factory-mode-icon">✦</span><strong>Referencia + cambios</strong><small>Ej. “haz las fotos más grandes y quita la franja inferior”.</small></div>
    <div><span class="factory-mode-icon">Aa</span><strong>Solo descripción</strong><small>Ej. “quiero un hero editorial con foto a la derecha”.</small></div>
  </div>

  <form action="index.php?route=component_factory.generate" method="post" enctype="multipart/form-data" class="stack-form" data-ai-generation-form novalidate>
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <div class="factory-form-grid">
      <label class="field"><span>Librería *</span><select name="library_id" required><option value="">Selecciona...</option><?php foreach($libraries as $lib): ?><option value="<?= (int)$lib['id'] ?>" <?= (int)($selectedLibraryId??0)===(int)$lib['id']?'selected':'' ?>><?= htmlspecialchars($lib['nombre']) ?></option><?php endforeach; ?></select></label>
      <label class="field"><span>Tipo *</span><select name="category_id" required><option value="">Selecciona...</option><?php foreach($categories as $cat): ?><option value="<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['nombre']) ?></option><?php endforeach; ?></select></label>
    </div>

    <label class="field"><span>Nombre del componente</span><input name="component_name" maxlength="160" placeholder="Ej. Hero editorial de producto"><small>Opcional. Si lo dejas vacío, Blumi propondrá un nombre.</small></label>

    <label class="field factory-reference-field">
      <span>Referencia visual <em>opcional</em></span>
      <input type="file" name="reference" accept="image/png,image/jpeg,image/webp,image/avif,.jpg,.jpeg,.jfif,.png,.webp,.avif" data-factory-reference>
      <span class="factory-upload-copy"><strong>Sube una captura o referencia</strong><small>JPG, PNG o WebP. AVIF se convierte automáticamente si el servidor lo soporta · máximo 8 MB. Si no tienes imagen, describe el componente abajo.</small></span>
    </label>

    <label class="field factory-intent-field">
      <span>¿Qué quieres crear o cambiar? <em>opcional si subes una referencia</em></span>
      <textarea name="instruction" rows="6" data-factory-intent placeholder="Háblale a Blumi como le hablarías a un diseñador. Ej. “Genera esto, pero quita la parte inferior, deja solo tres cards y haz las imágenes más grandes. En móvil quiero una card por vez.”"></textarea>
      <small>No escribas HTML, CSS ni instrucciones técnicas. Blumi se encarga de traducir tu intención.</small>
    </label>

    <div class="factory-inline-error" data-factory-error hidden>Sube una referencia o escribe qué componente quieres crear.</div>

    <details class="factory-advanced-contract">
      <summary>Qué hará Blumi automáticamente</summary>
      <div class="factory-rules factory-rules-compact">
        <ul>
          <li>Interpretará estructura, jerarquía, proporciones y comportamiento visual.</li>
          <li>Definirá Desktop, Tablet y Mobile sin pedirte breakpoints ni CSS.</li>
          <li>Detectará textos, imágenes, enlaces y contenido que debe quedar editable.</li>
          <li>Aplicará el design system del proyecto mediante tokens, sin copiar identidad ajena.</li>
          <li>Generará HTML5 + CSS3 + JavaScript vanilla, sin frameworks ni dependencias externas.</li>
          <li>Respetará el contrato de layout, seguridad y componentes reutilizables de Blumi.</li>
        </ul>
      </div>
    </details>

    <button class="button button-primary button-full factory-create-button" type="submit">Crear componente con Blumi AI</button>
  </form>
</section>

<aside class="factory-help factory-help-brand factory-intent-help">
  <div class="factory-blue-brand"><span>Blumi</span><small>Studio</small></div>
  <h3>Tú describes. Blumi traduce.</h3>
  <p>No necesitas preparar un prompt técnico en otro chat. Una imagen y una frase sencilla ya son suficientes.</p>
  <div class="factory-example-dialogue">
    <small>TÚ</small>
    <p>“Quiero esto, pero sin testimonios y con las fotos más grandes.”</p>
    <small>BLUMI</small>
    <p>Interpreta la referencia, prepara responsive, campos editables y contrato técnico antes de generar.</p>
  </div>
</aside>
</div>

<div class="ai-loader-overlay" data-ai-loader aria-hidden="true"><div class="ai-loader-card"><div class="ai-loader-mark">B</div><h3>Blumi está construyendo el componente</h3><p data-ai-loader-message>Entendiendo lo que quieres…</p><div class="ai-loader-line"><span></span></div><small>Blumi interpreta tu intención y genera la pieza en una sola operación.</small></div></div>
<script>
(function(){
  const form=document.querySelector('[data-ai-generation-form]'),loader=document.querySelector('[data-ai-loader]'),message=document.querySelector('[data-ai-loader-message]'),reference=document.querySelector('[data-factory-reference]'),intent=document.querySelector('[data-factory-intent]'),inlineError=document.querySelector('[data-factory-error]');
  if(!form||!loader)return;
  const messages=['Entendiendo lo que quieres…','Analizando estructura y jerarquía…','Traduciendo tu intención a Blumi…','Construyendo el componente…','Preparando Desktop, Tablet y Mobile…','Detectando contenido editable…','Validando el contrato técnico…','Casi listo…'];
  form.addEventListener('submit',(event)=>{
    const hasReference=!!(reference&&reference.files&&reference.files.length),hasIntent=!!(intent&&intent.value.trim());
    if(!form.checkValidity()){event.preventDefault();form.reportValidity();return;}
    if(!hasReference&&!hasIntent){event.preventDefault();if(inlineError)inlineError.hidden=false;if(intent)intent.focus();return;}
    if(inlineError)inlineError.hidden=true;
    loader.classList.add('is-open');loader.setAttribute('aria-hidden','false');
    let i=0;window.setInterval(()=>{i=(i+1)%messages.length;if(message)message.textContent=messages[i];},1800);
  });
  const clearError=()=>{if(inlineError)inlineError.hidden=true;};
  if(reference)reference.addEventListener('change',clearError);if(intent)intent.addEventListener('input',clearError);
})();
</script>
<?php if($error): ?><div class="toast toast-error"><strong>Revisa esto</strong><span><?= htmlspecialchars($error) ?></span></div><?php endif; ?>
<?php require dirname(__DIR__).'/partials/app_shell_end.php'; ?>
