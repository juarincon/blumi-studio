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
