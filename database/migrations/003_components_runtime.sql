SET NAMES utf8mb4;

/*
 * Blumi Studio - Fase 3
 * Componentes base y runtime seguro.
 * Los componentes incluidos por el sistema no requieren un usuario creador,
 * por eso created_by pasa a aceptar NULL. Los componentes creados desde la UI
 * seguirán guardando siempre el ID del usuario que los creó.
 */
ALTER TABLE componentes
    MODIFY created_by BIGINT UNSIGNED NULL;

ALTER TABLE componentes_versiones
    MODIFY created_by BIGINT UNSIGNED NULL;

/* Navbar base */
INSERT INTO componentes
(codigo, nombre, categoria_id, descripcion, html_template, css, js, schema_campos, schema_configuracion, estado, origen, version_actual, created_by)
SELECT
    'navbar_01',
    'Navbar limpio',
    cc.id,
    'Navegación sencilla con marca, tres enlaces y llamada a la acción.',
    '<nav class="bl-navbar"><div class="bl-navbar__inner"><a class="bl-navbar__brand" href="{{brand_url}}">{{brand_name}}</a><div class="bl-navbar__links"><a href="{{link_1_url}}">{{link_1_text}}</a><a href="{{link_2_url}}">{{link_2_text}}</a><a href="{{link_3_url}}">{{link_3_text}}</a></div><a class="bl-navbar__cta" href="{{cta_url}}">{{cta_text}}</a></div></nav>',
    '.bl-navbar{font-family:var(--site-font-body,Inter,sans-serif);background:var(--site-surface,#fff);border-bottom:1px solid color-mix(in srgb,var(--site-text,#18181b) 12%,transparent)}.bl-navbar__inner{max-width:var(--site-container,1280px);margin:0 auto;padding:18px 28px;display:flex;align-items:center;gap:28px}.bl-navbar__brand{font-family:var(--site-font-heading,Inter,sans-serif);font-weight:800;font-size:20px;color:var(--site-text,#18181b);text-decoration:none;margin-right:auto}.bl-navbar__links{display:flex;gap:24px}.bl-navbar__links a{color:var(--site-muted,#71717a);text-decoration:none;font-size:14px}.bl-navbar__cta{padding:10px 16px;border-radius:var(--site-radius,12px);background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);text-decoration:none;font-size:13px;font-weight:700;transition:transform .18s ease,opacity .18s ease}.bl-navbar__cta:hover{transform:translateY(-1px);opacity:.94}@media(max-width:700px){.bl-navbar__links{display:none}.bl-navbar__inner{padding:14px 18px}.bl-navbar__cta{padding:9px 12px}}',
    NULL,
    JSON_OBJECT(
      'brand_name', JSON_OBJECT('type','text','label','Marca','default','Blumi','required',true,'client_editable',true),
      'brand_url', JSON_OBJECT('type','url','label','Enlace de la marca','default','#','required',false,'client_editable',false),
      'link_1_text', JSON_OBJECT('type','text','label','Enlace 1','default','Inicio','required',false,'client_editable',true),
      'link_1_url', JSON_OBJECT('type','url','label','URL enlace 1','default','#','required',false,'client_editable',false),
      'link_2_text', JSON_OBJECT('type','text','label','Enlace 2','default','Servicios','required',false,'client_editable',true),
      'link_2_url', JSON_OBJECT('type','url','label','URL enlace 2','default','#','required',false,'client_editable',false),
      'link_3_text', JSON_OBJECT('type','text','label','Enlace 3','default','Nosotros','required',false,'client_editable',true),
      'link_3_url', JSON_OBJECT('type','url','label','URL enlace 3','default','#','required',false,'client_editable',false),
      'cta_text', JSON_OBJECT('type','text','label','Texto del botón','default','Contáctanos','required',false,'client_editable',true),
      'cta_url', JSON_OBJECT('type','url','label','URL del botón','default','#','required',false,'client_editable',false)
    ),
    JSON_OBJECT(),
    'aprobado','manual',1,NULL
FROM componentes_categorias cc
WHERE cc.slug = 'navbar'
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);

/* Hero base */
INSERT INTO componentes
(codigo, nombre, categoria_id, descripcion, html_template, css, js, schema_campos, schema_configuracion, estado, origen, version_actual, created_by)
SELECT
    'hero_01',
    'Hero editorial',
    cc.id,
    'Hero centrado con eyebrow, título, descripción y CTA.',
    '<section class="bl-hero"><div class="bl-hero__inner"><span class="bl-hero__eyebrow">{{eyebrow}}</span><h1>{{title}}</h1><p>{{description}}</p><a class="bl-hero__button" href="{{button_url}}">{{button_text}}</a></div></section>',
    '.bl-hero{font-family:var(--site-font-body,Inter,sans-serif);background:var(--site-background,#fff);padding:var(--site-section-space,96px) 28px}.bl-hero__inner{max-width:min(900px,var(--site-container,1280px));margin:0 auto;text-align:center}.bl-hero__eyebrow{display:inline-block;margin-bottom:16px;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--site-primary,#2043bf)}.bl-hero h1{font-family:var(--site-font-heading,Inter,sans-serif);margin:0 auto 20px;max-width:820px;font-size:clamp(42px,7vw,78px);line-height:.98;letter-spacing:-.055em;color:var(--site-text,#18181b)}.bl-hero p{max-width:660px;margin:0 auto 28px;font-size:18px;line-height:1.65;color:var(--site-muted,#71717a)}.bl-hero__button{display:inline-block;padding:14px 20px;border-radius:var(--site-radius,12px);background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);text-decoration:none;font-size:14px;font-weight:700;box-shadow:0 12px 28px color-mix(in srgb,var(--site-primary,#2043bf) 18%,transparent);transition:transform .18s ease,box-shadow .18s ease}.bl-hero__button:hover{transform:translateY(-2px);box-shadow:0 16px 32px color-mix(in srgb,var(--site-primary,#2043bf) 24%,transparent)}@media(max-width:600px){.bl-hero{padding:calc(var(--site-section-space,96px) * .72) 20px}.bl-hero p{font-size:16px}}',
    NULL,
    JSON_OBJECT(
      'eyebrow', JSON_OBJECT('type','text','label','Texto superior','default','NUEVA EXPERIENCIA','required',false,'client_editable',true),
      'title', JSON_OBJECT('type','text','label','Título principal','default','Una página clara empieza con una buena estructura.','required',true,'client_editable',true),
      'description', JSON_OBJECT('type','textarea','label','Descripción','default','Edita este contenido desde el panel derecho. No necesitas tocar HTML ni CSS.','required',false,'client_editable',true),
      'button_text', JSON_OBJECT('type','text','label','Texto del botón','default','Conocer más','required',false,'client_editable',true),
      'button_url', JSON_OBJECT('type','url','label','URL del botón','default','#','required',false,'client_editable',false)
    ),
    JSON_OBJECT(),
    'aprobado','manual',1,NULL
FROM componentes_categorias cc
WHERE cc.slug = 'hero'
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);

/* CTA base */
INSERT INTO componentes
(codigo, nombre, categoria_id, descripcion, html_template, css, js, schema_campos, schema_configuracion, estado, origen, version_actual, created_by)
SELECT
    'cta_01',
    'CTA simple',
    cc.id,
    'Llamada a la acción compacta con fondo oscuro.',
    '<section class="bl-cta"><div class="bl-cta__inner"><div><span>{{eyebrow}}</span><h2>{{title}}</h2><p>{{description}}</p></div><a href="{{button_url}}">{{button_text}}</a></div></section>',
    '.bl-cta{font-family:var(--site-font-body,Inter,sans-serif);padding:calc(var(--site-section-space,96px) * .75) 28px;background:var(--site-background,#fff)}.bl-cta__inner{max-width:min(1120px,var(--site-container,1280px));margin:0 auto;padding:48px;border-radius:calc(var(--site-radius,12px) * 1.5);background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);display:flex;align-items:center;gap:40px}.bl-cta__inner>div{flex:1}.bl-cta span{font-size:11px;font-weight:800;letter-spacing:.13em;color:var(--site-accent,#e7df68)}.bl-cta h2{font-family:var(--site-font-heading,Inter,sans-serif);margin:8px 0 10px;font-size:38px;line-height:1.08;letter-spacing:-.035em;color:var(--site-surface,#fff)}.bl-cta p{margin:0;max-width:620px;color:color-mix(in srgb,var(--site-surface,#fff) 78%,transparent);line-height:1.6}.bl-cta a{white-space:nowrap;padding:13px 18px;border-radius:var(--site-radius,12px);background:var(--site-accent,#e7df68);color:var(--site-text,#1d1d1f);text-decoration:none;font-weight:800;font-size:13px;transition:transform .18s ease}.bl-cta a:hover{transform:translateY(-2px)}@media(max-width:720px){.bl-cta{padding:calc(var(--site-section-space,96px) * .52) 18px}.bl-cta__inner{padding:32px;display:block}.bl-cta a{display:inline-block;margin-top:24px}.bl-cta h2{font-size:31px}}',
    NULL,
    JSON_OBJECT(
      'eyebrow', JSON_OBJECT('type','text','label','Texto superior','default','¿HABLAMOS?','required',false,'client_editable',true),
      'title', JSON_OBJECT('type','text','label','Título','default','Convierte la idea en el siguiente paso.','required',true,'client_editable',true),
      'description', JSON_OBJECT('type','textarea','label','Descripción','default','Usa esta sección para cerrar la página con una acción clara.','required',false,'client_editable',true),
      'button_text', JSON_OBJECT('type','text','label','Texto del botón','default','Escríbenos','required',false,'client_editable',true),
      'button_url', JSON_OBJECT('type','url','label','URL del botón','default','#','required',false,'client_editable',false)
    ),
    JSON_OBJECT(),
    'aprobado','manual',1,NULL
FROM componentes_categorias cc
WHERE cc.slug = 'cta'
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);

/* Footer base */
INSERT INTO componentes
(codigo, nombre, categoria_id, descripcion, html_template, css, js, schema_campos, schema_configuracion, estado, origen, version_actual, created_by)
SELECT
    'footer_01',
    'Footer esencial',
    cc.id,
    'Footer minimalista para cerrar sitios sencillos.',
    '<footer class="bl-footer"><div class="bl-footer__inner"><strong>{{brand_name}}</strong><p>{{text}}</p><a href="{{link_url}}">{{link_text}}</a></div></footer>',
    '.bl-footer{font-family:var(--site-font-body,Inter,sans-serif);background:var(--site-surface,#fff);border-top:1px solid color-mix(in srgb,var(--site-text,#18181b) 12%,transparent)}.bl-footer__inner{max-width:var(--site-container,1280px);margin:0 auto;padding:34px 28px;display:flex;align-items:center;gap:24px;color:var(--site-muted,#71717a);font-size:13px}.bl-footer strong{font-family:var(--site-font-heading,Inter,sans-serif);color:var(--site-text,#18181b);font-size:16px}.bl-footer p{margin:0 auto 0 0}.bl-footer a{color:var(--site-primary,#2043bf);text-decoration:none;font-weight:700}@media(max-width:620px){.bl-footer__inner{align-items:flex-start;flex-direction:column;gap:10px}.bl-footer p{margin:0}}',
    NULL,
    JSON_OBJECT(
      'brand_name', JSON_OBJECT('type','text','label','Marca','default','Blumi','required',true,'client_editable',true),
      'text', JSON_OBJECT('type','text','label','Texto','default','Todos los derechos reservados.','required',false,'client_editable',true),
      'link_text', JSON_OBJECT('type','text','label','Texto del enlace','default','Contacto','required',false,'client_editable',true),
      'link_url', JSON_OBJECT('type','url','label','URL del enlace','default','#','required',false,'client_editable',false)
    ),
    JSON_OBJECT(),
    'aprobado','manual',1,NULL
FROM componentes_categorias cc
WHERE cc.slug = 'footer'
ON DUPLICATE KEY UPDATE codigo = VALUES(codigo);
