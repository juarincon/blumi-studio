SET NAMES utf8mb4;

/*
 * Blumi Studio - Fase 4.1
 * Hace que los componentes base existentes consuman el Design System del proyecto.
 * No cambia contenido ni relaciones. Solo actualiza CSS de componentes maestros.
 */

UPDATE componentes
SET css = '.bl-navbar{font-family:var(--site-font-body,Inter,sans-serif);background:var(--site-surface,#fff);border-bottom:1px solid color-mix(in srgb,var(--site-text,#18181b) 12%,transparent)}.bl-navbar__inner{max-width:var(--site-container,1280px);margin:0 auto;padding:18px 28px;display:flex;align-items:center;gap:28px}.bl-navbar__brand{font-family:var(--site-font-heading,Inter,sans-serif);font-weight:800;font-size:20px;color:var(--site-text,#18181b);text-decoration:none;margin-right:auto}.bl-navbar__links{display:flex;gap:24px}.bl-navbar__links a{color:var(--site-muted,#71717a);text-decoration:none;font-size:14px}.bl-navbar__cta{padding:10px 16px;border-radius:var(--site-radius,12px);background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);text-decoration:none;font-size:13px;font-weight:700;transition:transform .18s ease,opacity .18s ease}.bl-navbar__cta:hover{transform:translateY(-1px);opacity:.94}@media(max-width:700px){.bl-navbar__links{display:none}.bl-navbar__inner{padding:14px 18px}.bl-navbar__cta{padding:9px 12px}}',
    updated_at = NOW()
WHERE codigo = 'navbar_01' AND deleted_at IS NULL;

UPDATE componentes
SET css = '.bl-hero{font-family:var(--site-font-body,Inter,sans-serif);background:var(--site-background,#fff);padding:var(--site-section-space,96px) 28px}.bl-hero__inner{max-width:min(900px,var(--site-container,1280px));margin:0 auto;text-align:center}.bl-hero__eyebrow{display:inline-block;margin-bottom:16px;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--site-primary,#2043bf)}.bl-hero h1{font-family:var(--site-font-heading,Inter,sans-serif);margin:0 auto 20px;max-width:820px;font-size:clamp(42px,7vw,78px);line-height:.98;letter-spacing:-.055em;color:var(--site-text,#18181b)}.bl-hero p{max-width:660px;margin:0 auto 28px;font-size:18px;line-height:1.65;color:var(--site-muted,#71717a)}.bl-hero__button{display:inline-block;padding:14px 20px;border-radius:var(--site-radius,12px);background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);text-decoration:none;font-size:14px;font-weight:700;box-shadow:0 12px 28px color-mix(in srgb,var(--site-primary,#2043bf) 18%,transparent);transition:transform .18s ease,box-shadow .18s ease}.bl-hero__button:hover{transform:translateY(-2px);box-shadow:0 16px 32px color-mix(in srgb,var(--site-primary,#2043bf) 24%,transparent)}@media(max-width:600px){.bl-hero{padding:calc(var(--site-section-space,96px) * .72) 20px}.bl-hero p{font-size:16px}}',
    updated_at = NOW()
WHERE codigo = 'hero_01' AND deleted_at IS NULL;

UPDATE componentes
SET css = '.bl-cta{font-family:var(--site-font-body,Inter,sans-serif);padding:calc(var(--site-section-space,96px) * .75) 28px;background:var(--site-background,#fff)}.bl-cta__inner{max-width:min(1120px,var(--site-container,1280px));margin:0 auto;padding:48px;border-radius:calc(var(--site-radius,12px) * 1.5);background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);display:flex;align-items:center;gap:40px}.bl-cta__inner>div{flex:1}.bl-cta span{font-size:11px;font-weight:800;letter-spacing:.13em;color:var(--site-accent,#e7df68)}.bl-cta h2{font-family:var(--site-font-heading,Inter,sans-serif);margin:8px 0 10px;font-size:38px;line-height:1.08;letter-spacing:-.035em;color:var(--site-surface,#fff)}.bl-cta p{margin:0;max-width:620px;color:color-mix(in srgb,var(--site-surface,#fff) 78%,transparent);line-height:1.6}.bl-cta a{white-space:nowrap;padding:13px 18px;border-radius:var(--site-radius,12px);background:var(--site-accent,#e7df68);color:var(--site-text,#1d1d1f);text-decoration:none;font-weight:800;font-size:13px;transition:transform .18s ease}.bl-cta a:hover{transform:translateY(-2px)}@media(max-width:720px){.bl-cta{padding:calc(var(--site-section-space,96px) * .52) 18px}.bl-cta__inner{padding:32px;display:block}.bl-cta a{display:inline-block;margin-top:24px}.bl-cta h2{font-size:31px}}',
    updated_at = NOW()
WHERE codigo = 'cta_01' AND deleted_at IS NULL;

UPDATE componentes
SET css = '.bl-footer{font-family:var(--site-font-body,Inter,sans-serif);background:var(--site-surface,#fff);border-top:1px solid color-mix(in srgb,var(--site-text,#18181b) 12%,transparent)}.bl-footer__inner{max-width:var(--site-container,1280px);margin:0 auto;padding:34px 28px;display:flex;align-items:center;gap:24px;color:var(--site-muted,#71717a);font-size:13px}.bl-footer strong{font-family:var(--site-font-heading,Inter,sans-serif);color:var(--site-text,#18181b);font-size:16px}.bl-footer p{margin:0 auto 0 0}.bl-footer a{color:var(--site-primary,#2043bf);text-decoration:none;font-weight:700}@media(max-width:620px){.bl-footer__inner{align-items:flex-start;flex-direction:column;gap:10px}.bl-footer p{margin:0}}',
    updated_at = NOW()
WHERE codigo = 'footer_01' AND deleted_at IS NULL;
