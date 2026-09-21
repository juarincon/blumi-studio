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
