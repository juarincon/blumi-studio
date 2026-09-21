SET NAMES utf8mb4;

/* Blumi Studio v0.5.1 - Constitución de componentes Blumi */
ALTER TABLE componentes
    ADD COLUMN standard_version VARCHAR(20) NOT NULL DEFAULT '1.0' AFTER ai_model,
    ADD COLUMN creative_profile VARCHAR(40) NOT NULL DEFAULT 'blumi' AFTER standard_version;

UPDATE componentes
SET standard_version='1.0', creative_profile='blumi'
WHERE deleted_at IS NULL;
