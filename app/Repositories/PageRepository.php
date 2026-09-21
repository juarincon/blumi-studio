<?php
namespace App\Repositories;

use App\Helpers\Database;
use PDO;

final class PageRepository
{
    public function forProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM paginas
             WHERE proyecto_id = :proyecto_id AND deleted_at IS NULL
             ORDER BY es_inicio DESC, orden ASC, id ASC'
        );
        $stmt->execute(['proyecto_id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, pr.nombre AS proyecto_nombre, pr.codigo AS proyecto_codigo, pr.slug AS proyecto_slug
             FROM paginas p
             INNER JOIN proyectos pr ON pr.id = p.proyecto_id
             WHERE p.id = :id AND p.deleted_at IS NULL AND pr.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function homeForProject(int $projectId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM paginas WHERE proyecto_id=:project_id AND es_inicio=1 AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['project_id'=>$projectId]);
        $row=$stmt->fetch();
        return $row ?: null;
    }

    public function rename(int $id, string $name): void
    {
        $stmt=Database::connection()->prepare('UPDATE paginas SET nombre=:nombre WHERE id=:id AND deleted_at IS NULL');
        $stmt->execute(['nombre'=>$name,'id'=>$id]);
    }

    public function create(array $data): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if (!empty($data['es_inicio'])) {
                $clear = $pdo->prepare('UPDATE paginas SET es_inicio = 0 WHERE proyecto_id = :proyecto_id AND deleted_at IS NULL');
                $clear->execute(['proyecto_id' => $data['proyecto_id']]);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO paginas
                (proyecto_id, nombre, slug, estado, orden, es_inicio, visible, created_by)
                VALUES (:proyecto_id, :nombre, :slug, :estado, :orden, :es_inicio, :visible, :created_by)'
            );
            $stmt->execute($data);
            $id = (int)$pdo->lastInsertId();

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function nextOrder(int $projectId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(orden), 0) + 10 FROM paginas WHERE proyecto_id = :proyecto_id AND deleted_at IS NULL'
        );
        $stmt->execute(['proyecto_id' => $projectId]);
        return (int)$stmt->fetchColumn();
    }

    public function slugExists(int $projectId, string $slug): bool
    {
        $stmt = Database::connection()->prepare(
            'SELECT 1 FROM paginas
             WHERE proyecto_id = :proyecto_id AND slug = :slug AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([
            'proyecto_id' => $projectId,
            'slug' => $slug,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    public function activeCount(int $projectId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM paginas WHERE proyecto_id = :proyecto_id AND deleted_at IS NULL'
        );
        $stmt->execute(['proyecto_id' => $projectId]);
        return (int)$stmt->fetchColumn();
    }

    public function softDelete(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE paginas SET deleted_at = NOW(), visible = 0 WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }
}
