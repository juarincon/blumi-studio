SET NAMES utf8mb4;

/*
 * Blumi Studio - Fase 4
 * Identidad visual del proyecto: colores, tipografias, referencias y fuentes locales.
 */

ALTER TABLE proyecto_design_tokens
    ADD COLUMN primary_color VARCHAR(20) NOT NULL DEFAULT '#2043BF' AFTER proyecto_id,
    ADD COLUMN secondary_color VARCHAR(20) NOT NULL DEFAULT '#3974DC' AFTER primary_color,
    ADD COLUMN accent_color VARCHAR(20) NOT NULL DEFAULT '#E7DF68' AFTER secondary_color,
    ADD COLUMN background_color VARCHAR(20) NOT NULL DEFAULT '#FFFFFF' AFTER accent_color,
    ADD COLUMN surface_color VARCHAR(20) NOT NULL DEFAULT '#FFFFFF' AFTER background_color,
    ADD COLUMN text_color VARCHAR(20) NOT NULL DEFAULT '#1D1D1F' AFTER surface_color,
    ADD COLUMN muted_color VARCHAR(20) NOT NULL DEFAULT '#71717A' AFTER text_color,
    ADD COLUMN heading_font VARCHAR(120) NOT NULL DEFAULT 'Inter' AFTER muted_color,
    ADD COLUMN heading_font_source ENUM('system','google','local') NOT NULL DEFAULT 'system' AFTER heading_font,
    ADD COLUMN heading_font_url VARCHAR(500) NULL AFTER heading_font_source,
    ADD COLUMN body_font VARCHAR(120) NOT NULL DEFAULT 'Inter' AFTER heading_font_url,
    ADD COLUMN body_font_source ENUM('system','google','local') NOT NULL DEFAULT 'system' AFTER body_font,
    ADD COLUMN body_font_url VARCHAR(500) NULL AFTER body_font_source,
    ADD COLUMN border_radius VARCHAR(20) NOT NULL DEFAULT '12px' AFTER body_font_url,
    ADD COLUMN container_width VARCHAR(20) NOT NULL DEFAULT '1280px' AFTER border_radius,
    ADD COLUMN section_spacing VARCHAR(20) NOT NULL DEFAULT '96px' AFTER container_width,
    ADD COLUMN style_tags_json JSON NULL AFTER section_spacing;

UPDATE proyecto_design_tokens
SET
    primary_color = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.primary')), primary_color),
    secondary_color = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.secondary')), secondary_color),
    accent_color = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.accent')), accent_color),
    background_color = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.background')), background_color),
    surface_color = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.surface')), surface_color),
    text_color = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.text')), text_color),
    muted_color = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.muted')), muted_color),
    heading_font = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.font_heading')), heading_font),
    body_font = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.font_body')), body_font),
    border_radius = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.border_radius')), border_radius),
    container_width = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.container_width')), container_width),
    section_spacing = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(tokens_json, '$.section_spacing')), section_spacing);

CREATE TABLE IF NOT EXISTS proyecto_fuentes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proyecto_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    peso SMALLINT UNSIGNED NOT NULL DEFAULT 400,
    estilo ENUM('normal','italic') NOT NULL DEFAULT 'normal',
    formato ENUM('woff2','woff') NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    tamano_bytes BIGINT UNSIGNED NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_proyecto_fuentes_archivo (proyecto_id, slug, peso, estilo, deleted_at),
    KEY idx_proyecto_fuentes_proyecto (proyecto_id, nombre),
    CONSTRAINT fk_proyecto_fuentes_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_proyecto_fuentes_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proyecto_referencias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proyecto_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('logo','referencia','favicon') NOT NULL DEFAULT 'referencia',
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_proyecto_referencias_proyecto (proyecto_id, tipo, created_at),
    CONSTRAINT fk_proyecto_referencias_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_proyecto_referencias_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_proyecto_referencias_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* Los componentes base ahora respetan las fuentes del proyecto. */
UPDATE componentes
SET css = REPLACE(css, 'font-family:Arial,sans-serif', 'font-family:var(--site-font-body,Arial,sans-serif)')
WHERE codigo IN ('navbar_01','hero_01','cta_01','footer_01');

UPDATE componentes
SET css = REPLACE(css, '.bl-hero h1{', '.bl-hero h1{font-family:var(--site-font-heading,var(--site-font-body,Arial,sans-serif));')
WHERE codigo = 'hero_01';

UPDATE componentes
SET css = REPLACE(css, '.bl-cta h2{', '.bl-cta h2{font-family:var(--site-font-heading,var(--site-font-body,Arial,sans-serif));')
WHERE codigo = 'cta_01';

UPDATE componentes
SET css = REPLACE(css, 'max-width:1180px', 'max-width:var(--site-container,1180px)')
WHERE codigo IN ('navbar_01','footer_01');

UPDATE componentes
SET css = REPLACE(css, 'max-width:1120px', 'max-width:var(--site-container,1120px)')
WHERE codigo = 'cta_01';

UPDATE componentes
SET css = REPLACE(css, 'padding:104px 28px', 'padding:var(--site-section-space,96px) 28px')
WHERE codigo = 'hero_01';

UPDATE componentes
SET css = REPLACE(css, 'border-radius:12px', 'border-radius:var(--site-radius,12px)')
WHERE codigo IN ('navbar_01','hero_01','cta_01');
