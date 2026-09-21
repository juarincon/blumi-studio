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
