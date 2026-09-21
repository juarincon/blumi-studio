SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('superadmin','builder','cliente') NOT NULL DEFAULT 'builder',
    estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    ultimo_acceso DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proyectos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    dominio VARCHAR(190) NULL,
    subdominio_preview VARCHAR(190) NULL,
    descripcion TEXT NULL,
    estado ENUM('borrador','desarrollo','revision','publicado','archivado') NOT NULL DEFAULT 'borrador',
    usuario_propietario_id BIGINT UNSIGNED NULL,
    plantilla_origen_id BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_proyectos_codigo (codigo),
    UNIQUE KEY uq_proyectos_slug (slug),
    KEY idx_proyectos_estado (estado),
    KEY idx_proyectos_propietario (usuario_propietario_id),
    CONSTRAINT fk_proyectos_propietario FOREIGN KEY (usuario_propietario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_proyectos_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proyecto_configuracion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proyecto_id BIGINT UNSIGNED NOT NULL,
    nombre_comercial VARCHAR(160) NULL,
    idioma VARCHAR(10) NOT NULL DEFAULT 'es',
    zona_horaria VARCHAR(80) NOT NULL DEFAULT 'America/Bogota',
    logo_asset_id BIGINT UNSIGNED NULL,
    favicon_asset_id BIGINT UNSIGNED NULL,
    telefono VARCHAR(50) NULL,
    whatsapp VARCHAR(50) NULL,
    email VARCHAR(190) NULL,
    direccion VARCHAR(255) NULL,
    redes_json JSON NULL,
    seo_global_json JSON NULL,
    configuracion_publicacion_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_proyecto_configuracion_proyecto (proyecto_id),
    CONSTRAINT fk_proyecto_configuracion_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS proyecto_design_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proyecto_id BIGINT UNSIGNED NOT NULL,
    tokens_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_design_tokens_proyecto (proyecto_id),
    CONSTRAINT fk_design_tokens_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS historial (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NULL,
    proyecto_id BIGINT UNSIGNED NULL,
    entidad_tipo VARCHAR(60) NOT NULL,
    entidad_id BIGINT UNSIGNED NULL,
    accion VARCHAR(80) NOT NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_historial_proyecto (proyecto_id, created_at),
    KEY idx_historial_entidad (entidad_tipo, entidad_id),
    CONSTRAINT fk_historial_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_historial_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS paginas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proyecto_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(140) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    estado ENUM('borrador','revision','publicado','oculto') NOT NULL DEFAULT 'borrador',
    orden INT UNSIGNED NOT NULL DEFAULT 0,
    es_inicio TINYINT(1) NOT NULL DEFAULT 0,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    seo_title VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    canonical_url VARCHAR(255) NULL,
    robots_index TINYINT(1) NOT NULL DEFAULT 1,
    robots_follow TINYINT(1) NOT NULL DEFAULT 1,
    og_title VARCHAR(190) NULL,
    og_description VARCHAR(320) NULL,
    og_image_asset_id BIGINT UNSIGNED NULL,
    schema_json JSON NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_paginas_proyecto_slug (proyecto_id, slug),
    KEY idx_paginas_proyecto_orden (proyecto_id, orden, id),
    KEY idx_paginas_estado (proyecto_id, estado),
    KEY idx_paginas_inicio (proyecto_id, es_inicio),
    CONSTRAINT fk_paginas_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_paginas_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    proyecto_id BIGINT UNSIGNED NOT NULL,
    tipo ENUM('image','video','document','logo','icon') NOT NULL DEFAULT 'image',
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NULL,
    tamano_bytes BIGINT UNSIGNED NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    alt_text VARCHAR(255) NULL,
    title VARCHAR(255) NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_assets_proyecto (proyecto_id, created_at),
    KEY idx_assets_tipo (proyecto_id, tipo),
    CONSTRAINT fk_assets_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_assets_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS componentes_categorias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    orden INT UNSIGNED NOT NULL DEFAULT 0,
    estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_componentes_categorias_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS componentes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    categoria_id BIGINT UNSIGNED NOT NULL,
    descripcion TEXT NULL,
    html_template LONGTEXT NOT NULL,
    css LONGTEXT NULL,
    js LONGTEXT NULL,
    schema_campos JSON NOT NULL,
    schema_configuracion JSON NULL,
    thumbnail_path VARCHAR(500) NULL,
    estado ENUM('borrador','pendiente_revision','aprobado','depreciado','archivado') NOT NULL DEFAULT 'borrador',
    origen ENUM('manual','ia_descripcion','ia_imagen','ia_modificacion','proyecto','importado') NOT NULL DEFAULT 'manual',
    version_actual INT UNSIGNED NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archived_at DATETIME NULL,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_componentes_codigo (codigo),
    KEY idx_componentes_categoria_estado (categoria_id, estado),
    CONSTRAINT fk_componentes_categoria FOREIGN KEY (categoria_id) REFERENCES componentes_categorias(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_componentes_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS componentes_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_componentes_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS componentes_tag_rel (
    componente_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (componente_id, tag_id),
    CONSTRAINT fk_componentes_tag_rel_componente FOREIGN KEY (componente_id) REFERENCES componentes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_componentes_tag_rel_tag FOREIGN KEY (tag_id) REFERENCES componentes_tags(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS componentes_versiones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    componente_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    html_template LONGTEXT NOT NULL,
    css LONGTEXT NULL,
    js LONGTEXT NULL,
    schema_campos JSON NOT NULL,
    schema_configuracion JSON NULL,
    motivo VARCHAR(255) NULL,
    prompt_ia TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_componentes_version (componente_id, version),
    CONSTRAINT fk_componentes_versiones_componente FOREIGN KEY (componente_id) REFERENCES componentes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_componentes_versiones_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagina_componentes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pagina_id BIGINT UNSIGNED NOT NULL,
    componente_id BIGINT UNSIGNED NOT NULL,
    orden INT UNSIGNED NOT NULL DEFAULT 0,
    nombre_interno VARCHAR(160) NULL,
    visible TINYINT(1) NOT NULL DEFAULT 1,
    contenido_json JSON NOT NULL,
    configuracion_json JSON NULL,
    estilos_override_json JSON NULL,
    version_componente INT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_pagina_componentes_orden (pagina_id, orden, id),
    KEY idx_pagina_componentes_componente (componente_id),
    CONSTRAINT fk_pagina_componentes_pagina FOREIGN KEY (pagina_id) REFERENCES paginas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pagina_componentes_componente FOREIGN KEY (componente_id) REFERENCES componentes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pagina_componentes_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO componentes_categorias (nombre, slug, orden)
VALUES
('Navbar', 'navbar', 10),
('Hero', 'hero', 20),
('Logos', 'logos', 30),
('Introducción', 'intro', 40),
('About', 'about', 50),
('Servicios', 'services', 60),
('Features', 'features', 70),
('Cards', 'cards', 80),
('Estadísticas', 'statistics', 90),
('Galería', 'gallery', 100),
('Equipo', 'team', 110),
('Testimonios', 'testimonials', 120),
('FAQ', 'faq', 130),
('Pricing', 'pricing', 140),
('Blog', 'blog', 150),
('CTA', 'cta', 160),
('Formularios', 'forms', 170),
('Contacto', 'contact', 180),
('Footer', 'footer', 190),
('Custom', 'custom', 200)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), orden = VALUES(orden);

ALTER TABLE proyecto_configuracion
    ADD CONSTRAINT fk_proyecto_configuracion_logo_asset
        FOREIGN KEY (logo_asset_id) REFERENCES assets(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT fk_proyecto_configuracion_favicon_asset
        FOREIGN KEY (favicon_asset_id) REFERENCES assets(id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE paginas
    ADD CONSTRAINT fk_paginas_og_image_asset
        FOREIGN KEY (og_image_asset_id) REFERENCES assets(id) ON UPDATE CASCADE ON DELETE RESTRICT;


SET FOREIGN_KEY_CHECKS = 1;
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
    '.bl-navbar{font-family:Arial,sans-serif;background:var(--site-surface,#fff);border-bottom:1px solid #e8e8e8}.bl-navbar__inner{max-width:1180px;margin:0 auto;padding:18px 28px;display:flex;align-items:center;gap:28px}.bl-navbar__brand{font-weight:800;font-size:20px;color:var(--site-text,#18181b);text-decoration:none;margin-right:auto}.bl-navbar__links{display:flex;gap:24px}.bl-navbar__links a{color:#52525b;text-decoration:none;font-size:14px}.bl-navbar__cta{padding:10px 16px;border-radius:10px;background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);text-decoration:none;font-size:13px;font-weight:700}@media(max-width:700px){.bl-navbar__links{display:none}.bl-navbar__inner{padding:14px 18px}.bl-navbar__cta{padding:9px 12px}}',
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
    '.bl-hero{font-family:Arial,sans-serif;background:var(--site-background,#f7f7f5);padding:104px 28px}.bl-hero__inner{max-width:900px;margin:0 auto;text-align:center}.bl-hero__eyebrow{display:inline-block;margin-bottom:16px;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--site-primary,#2043bf)}.bl-hero h1{margin:0 auto 20px;max-width:820px;font-size:clamp(42px,7vw,78px);line-height:.98;letter-spacing:-.055em;color:var(--site-text,#18181b)}.bl-hero p{max-width:660px;margin:0 auto 28px;font-size:18px;line-height:1.65;color:var(--site-muted,#71717a)}.bl-hero__button{display:inline-block;padding:14px 20px;border-radius:12px;background:var(--site-primary,#2043bf);color:var(--site-surface,#fff);text-decoration:none;font-size:14px;font-weight:700;box-shadow:0 12px 28px rgba(32,67,191,.16)}@media(max-width:600px){.bl-hero{padding:72px 20px}.bl-hero p{font-size:16px}}',
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
    '.bl-cta{font-family:Arial,sans-serif;padding:72px 28px;background:var(--site-surface,#fff)}.bl-cta__inner{max-width:1120px;margin:0 auto;padding:48px;border-radius:24px;background:#1d1d1f;color:var(--site-surface,#fff);display:flex;align-items:center;gap:40px}.bl-cta__inner>div{flex:1}.bl-cta span{font-size:11px;font-weight:800;letter-spacing:.13em;color:#afc4e8}.bl-cta h2{margin:8px 0 10px;font-size:38px;line-height:1.08;letter-spacing:-.035em}.bl-cta p{margin:0;max-width:620px;color:#c6c6cb;line-height:1.6}.bl-cta a{white-space:nowrap;padding:13px 18px;border-radius:12px;background:#e7df68;color:#1d1d1f;text-decoration:none;font-weight:800;font-size:13px}@media(max-width:720px){.bl-cta{padding:50px 18px}.bl-cta__inner{padding:32px;display:block}.bl-cta a{display:inline-block;margin-top:24px}.bl-cta h2{font-size:31px}}',
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
    '.bl-footer{font-family:Arial,sans-serif;background:#f6f6f4;border-top:1px solid #e8e8e8}.bl-footer__inner{max-width:1180px;margin:0 auto;padding:34px 28px;display:flex;align-items:center;gap:24px;color:var(--site-muted,#71717a);font-size:13px}.bl-footer strong{color:var(--site-text,#18181b);font-size:16px}.bl-footer p{margin:0 auto 0 0}.bl-footer a{color:var(--site-primary,#2043bf);text-decoration:none;font-weight:700}@media(max-width:620px){.bl-footer__inner{align-items:flex-start;flex-direction:column;gap:10px}.bl-footer p{margin:0}}',
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
SET NAMES utf8mb4;

/* Blumi Studio v0.5.0 - Component Factory MVP */
ALTER TABLE componentes
    ADD COLUMN parent_component_id BIGINT UNSIGNED NULL AFTER categoria_id,
    ADD COLUMN reference_image_path VARCHAR(500) NULL AFTER thumbnail_path,
    ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER origen,
    ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER is_system,
    ADD COLUMN source_prompt TEXT NULL AFTER is_locked,
    ADD COLUMN ai_model VARCHAR(100) NULL AFTER source_prompt,
    ADD KEY idx_componentes_parent (parent_component_id),
    ADD CONSTRAINT fk_componentes_parent FOREIGN KEY (parent_component_id)
        REFERENCES componentes(id) ON UPDATE CASCADE ON DELETE RESTRICT;

UPDATE componentes
SET is_system = 1, is_locked = 1
WHERE codigo IN ('navbar_01','hero_01','cta_01','footer_01');

INSERT INTO componentes_categorias (nombre, slug, orden) VALUES
('Banner', 'banner', 25),
('Split content', 'split-content', 55),
('Proceso / Pasos', 'steps', 75),
('Partners', 'partners', 85),
('Portafolio', 'portfolio', 105),
('Timeline', 'timeline', 115),
('Comparativa', 'comparison', 145),
('Newsletter', 'newsletter', 165),
('Mapa / Ubicación', 'map-location', 185)
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), orden=VALUES(orden);
SET NAMES utf8mb4;

/* Blumi Studio v0.5.1 - Constitución de componentes Blumi */
ALTER TABLE componentes
    ADD COLUMN standard_version VARCHAR(20) NOT NULL DEFAULT '1.0' AFTER ai_model,
    ADD COLUMN creative_profile VARCHAR(40) NOT NULL DEFAULT 'blumi' AFTER standard_version;

UPDATE componentes
SET standard_version='1.0', creative_profile='blumi'
WHERE deleted_at IS NULL;
