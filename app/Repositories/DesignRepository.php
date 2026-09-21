<?php
namespace App\Repositories;

use App\Helpers\Database;
use PDO;

final class DesignRepository
{
    public function get(int $projectId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT dt.*, pc.logo_asset_id, pc.favicon_asset_id, pc.nombre_comercial,
                    la.ruta AS logo_ruta, la.original_filename AS logo_nombre
             FROM proyecto_design_tokens dt
             LEFT JOIN proyecto_configuracion pc ON pc.proyecto_id = dt.proyecto_id
             LEFT JOIN assets la ON la.id = pc.logo_asset_id AND la.deleted_at IS NULL
             WHERE dt.proyecto_id = :project_id
             LIMIT 1'
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function update(int $projectId, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE proyecto_design_tokens SET
                primary_color = :primary_color,
                secondary_color = :secondary_color,
                accent_color = :accent_color,
                background_color = :background_color,
                surface_color = :surface_color,
                text_color = :text_color,
                muted_color = :muted_color,
                heading_font = :heading_font,
                heading_font_source = :heading_font_source,
                heading_font_url = :heading_font_url,
                body_font = :body_font,
                body_font_source = :body_font_source,
                body_font_url = :body_font_url,
                border_radius = :border_radius,
                container_width = :container_width,
                section_spacing = :section_spacing,
                style_tags_json = :style_tags_json,
                tokens_json = :tokens_json
             WHERE proyecto_id = :project_id'
        );
        $stmt->execute($data + ['project_id' => $projectId]);
    }

    public function fonts(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM proyecto_fuentes
             WHERE proyecto_id = :project_id AND deleted_at IS NULL
             ORDER BY nombre, peso, estilo'
        );
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addFont(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO proyecto_fuentes
             (proyecto_id,nombre,slug,peso,estilo,formato,filename,original_filename,ruta,mime_type,tamano_bytes,uploaded_by)
             VALUES
             (:proyecto_id,:nombre,:slug,:peso,:estilo,:formato,:filename,:original_filename,:ruta,:mime_type,:tamano_bytes,:uploaded_by)'
        );
        $stmt->execute($data);
        return (int)Database::connection()->lastInsertId();
    }

    public function references(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT pr.*, a.ruta, a.original_filename, a.mime_type, a.width, a.height
             FROM proyecto_referencias pr
             INNER JOIN assets a ON a.id = pr.asset_id AND a.deleted_at IS NULL
             WHERE pr.proyecto_id = :project_id AND pr.deleted_at IS NULL
             ORDER BY pr.es_principal DESC, pr.created_at DESC'
        );
        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addReference(int $projectId, int $assetId, string $type, bool $principal, int $userId): void
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($principal && $type === 'logo') {
                $clear = $pdo->prepare("UPDATE proyecto_referencias SET es_principal = 0 WHERE proyecto_id = :project_id AND tipo = 'logo' AND deleted_at IS NULL");
                $clear->execute(['project_id' => $projectId]);
                $cfg = $pdo->prepare('UPDATE proyecto_configuracion SET logo_asset_id = :asset_id WHERE proyecto_id = :project_id');
                $cfg->execute(['asset_id' => $assetId, 'project_id' => $projectId]);
            }
            $stmt = $pdo->prepare(
                'INSERT INTO proyecto_referencias (proyecto_id,asset_id,tipo,es_principal,created_by)
                 VALUES (:project_id,:asset_id,:tipo,:principal,:created_by)'
            );
            $stmt->execute([
                'project_id' => $projectId,
                'asset_id' => $assetId,
                'tipo' => $type,
                'principal' => $principal ? 1 : 0,
                'created_by' => $userId,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
