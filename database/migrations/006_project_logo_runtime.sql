SET NAMES utf8mb4;

/*
 * Blumi Studio - Fase 4.2
 * El logo principal pasa a ser un dato global del proyecto consumido por
 * Navbar/Footer. No se duplica como texto dentro de cada instancia.
 */

UPDATE componentes
SET html_template = '<nav class="bl-navbar"><div class="bl-navbar__inner"><a class="bl-navbar__brand" href="{{brand_url}}"><img class="bl-navbar__logo" src="{{site_logo}}" alt="{{site_name}}"></a><div class="bl-navbar__links"><a href="{{link_1_url}}">{{link_1_text}}</a><a href="{{link_2_url}}">{{link_2_text}}</a><a href="{{link_3_url}}">{{link_3_text}}</a></div><a class="bl-navbar__cta" href="{{cta_url}}">{{cta_text}}</a></div></nav>',
    css = '.bl-navbar{font-family:var(--site-font-body,Inter,sans-serif);background:var(--site-surface,#fff);border-bottom:1px solid color-mix(in srgb,var(--site-text,#18181b) 12%,transparent)}.bl-navbar__inner{max-width:var(--site-container,1280px);margin:0 auto;padding:18px 28px;display:flex;align-items:center;gap:28px}.bl-navbar__brand{display:flex;align-items:center;margin-right:auto;text-decoration:none}.bl-navbar__logo{display:block;max-width:168px;max-height:48px;width:auto;height:auto;object-fit:contain}.bl-navbar__links{display:flex;gap:24px}.bl-navbar__links a{color:var(--site-muted,#71717a);text-decoration:none;font-size:14px}.bl-navbar__cta{padding:10px 16px;border-radius:var(--site-radius,12px);background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);text-decoration:none;font-size:13px;font-weight:700;transition:transform .18s ease,opacity .18s ease}.bl-navbar__cta:hover{transform:translateY(-1px);opacity:.94}@media(max-width:700px){.bl-navbar__links{display:none}.bl-navbar__inner{padding:14px 18px}.bl-navbar__logo{max-width:132px;max-height:40px}.bl-navbar__cta{padding:9px 12px}}',
    schema_campos = JSON_REMOVE(schema_campos, '$.brand_name'),
    updated_at = NOW()
WHERE codigo = 'navbar_01' AND deleted_at IS NULL;

UPDATE componentes
SET html_template = '<footer class="bl-footer"><div class="bl-footer__inner"><img class="bl-footer__logo" src="{{site_logo}}" alt="{{site_name}}"><p>{{text}}</p><a href="{{link_url}}">{{link_text}}</a></div></footer>',
    css = '.bl-footer{font-family:var(--site-font-body,Inter,sans-serif);background:var(--site-surface,#fff);padding:56px 28px;border-top:1px solid color-mix(in srgb,var(--site-text,#18181b) 10%,transparent)}.bl-footer__inner{max-width:var(--site-container,1280px);margin:0 auto}.bl-footer__logo{display:block;max-width:150px;max-height:54px;width:auto;height:auto;object-fit:contain;margin-bottom:12px}.bl-footer p{max-width:560px;margin:0 0 12px;color:var(--site-muted,#71717a);line-height:1.65}.bl-footer a{color:var(--site-primary,#2043bf);font-weight:700;text-decoration:none}',
    schema_campos = JSON_REMOVE(schema_campos, '$.brand_name'),
    updated_at = NOW()
WHERE codigo = 'footer_01' AND deleted_at IS NULL;
