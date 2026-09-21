<?php
namespace App\Repositories;

use App\Helpers\Database;

final class ProjectLayoutRepository
{
    private static bool $ready = false;

    public function ensureSchema(): void
    {
        if (self::$ready) return;
        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS proyecto_layout (
            proyecto_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            navbar_instance_id BIGINT UNSIGNED NULL,
            footer_instance_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_proyecto_layout_navbar (navbar_instance_id),
            KEY idx_proyecto_layout_footer (footer_instance_id),
            CONSTRAINT fk_proyecto_layout_proyecto FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
            CONSTRAINT fk_proyecto_layout_navbar FOREIGN KEY (navbar_instance_id) REFERENCES pagina_componentes(id) ON UPDATE CASCADE ON DELETE SET NULL,
            CONSTRAINT fk_proyecto_layout_footer FOREIGN KEY (footer_instance_id) REFERENCES pagina_componentes(id) ON UPDATE CASCADE ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO proyecto_layout (proyecto_id) SELECT id FROM proyectos WHERE deleted_at IS NULL");

        // Migra de forma conservadora el primer Navbar/Footer existente de cada proyecto.
        // Los duplicados se conservan en BD pero se ocultan logicamente para cumplir la regla 1 por proyecto.
        foreach (['navbar' => 'navbar_instance_id', 'footer' => 'footer_instance_id'] as $slug => $column) {
            $pdo->exec("UPDATE proyecto_layout pl
                SET {$column} = (
                    SELECT pc.id FROM pagina_componentes pc
                    INNER JOIN paginas p ON p.id=pc.pagina_id AND p.deleted_at IS NULL
                    INNER JOIN componentes c ON c.id=pc.componente_id AND c.deleted_at IS NULL
                    INNER JOIN componentes_categorias cc ON cc.id=c.categoria_id
                    WHERE p.proyecto_id=pl.proyecto_id AND pc.deleted_at IS NULL AND cc.slug='{$slug}'
                    ORDER BY p.es_inicio DESC, pc.orden ASC, pc.id ASC LIMIT 1
                )
                WHERE {$column} IS NULL");
            $pdo->exec("UPDATE pagina_componentes pc
                INNER JOIN paginas p ON p.id=pc.pagina_id
                INNER JOIN componentes c ON c.id=pc.componente_id
                INNER JOIN componentes_categorias cc ON cc.id=c.categoria_id
                INNER JOIN proyecto_layout pl ON pl.proyecto_id=p.proyecto_id
                SET pc.deleted_at=NOW(), pc.visible=0
                WHERE pc.deleted_at IS NULL AND cc.slug='{$slug}' AND pc.id <> COALESCE(pl.{$column},0)");
        }
        self::$ready = true;
    }

    public function get(int $projectId): array
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare('SELECT * FROM proyecto_layout WHERE proyecto_id=:id LIMIT 1');
        $stmt->execute(['id'=>$projectId]);
        $row = $stmt->fetch();
        if (!$row) {
            Database::connection()->prepare('INSERT IGNORE INTO proyecto_layout (proyecto_id) VALUES (:id)')->execute(['id'=>$projectId]);
            return ['proyecto_id'=>$projectId,'navbar_instance_id'=>null,'footer_instance_id'=>null];
        }
        return $row;
    }

    public function slot(int $projectId, string $type): ?int
    {
        $row = $this->get($projectId);
        $key = $type === 'footer' ? 'footer_instance_id' : 'navbar_instance_id';
        return !empty($row[$key]) ? (int)$row[$key] : null;
    }

    public function setSlot(int $projectId, string $type, ?int $instanceId): void
    {
        $this->ensureSchema();
        $column = $type === 'footer' ? 'footer_instance_id' : 'navbar_instance_id';
        $this->get($projectId);
        $stmt = Database::connection()->prepare("UPDATE proyecto_layout SET {$column}=:instance_id WHERE proyecto_id=:project_id");
        $stmt->bindValue(':instance_id', $instanceId, $instanceId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
        $stmt->bindValue(':project_id', $projectId, \PDO::PARAM_INT);
        $stmt->execute();
    }
}
