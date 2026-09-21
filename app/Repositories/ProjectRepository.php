<?php
namespace App\Repositories;

use App\Helpers\Database;
use PDO;

final class ProjectRepository
{
    public function all(): array
    {
        $sql = 'SELECT p.*, u.nombre AS propietario_nombre
                FROM proyectos p
                LEFT JOIN usuarios u ON u.id = p.usuario_propietario_id
                WHERE p.deleted_at IS NULL
                ORDER BY p.updated_at DESC, p.id DESC';
        return Database::connection()->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, u.nombre AS propietario_nombre
             FROM proyectos p
             LEFT JOIN usuarios u ON u.id = p.usuario_propietario_id
             WHERE p.id = :id AND p.deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO proyectos
                (codigo, nombre, slug, dominio, descripcion, estado, usuario_propietario_id, created_by)
                VALUES (:codigo, :nombre, :slug, :dominio, :descripcion, :estado, :usuario_propietario_id, :created_by)'
            );
            $stmt->execute($data);
            $id = (int) $pdo->lastInsertId();

            $cfg = $pdo->prepare(
                'INSERT INTO proyecto_configuracion (proyecto_id, idioma, zona_horaria, nombre_comercial)
                 VALUES (:proyecto_id, :idioma, :zona_horaria, :nombre_comercial)'
            );
            $cfg->execute([
                'proyecto_id' => $id,
                'idioma' => 'es',
                'zona_horaria' => 'America/Bogota',
                'nombre_comercial' => $data['nombre'],
            ]);

            $tokens = $pdo->prepare(
                'INSERT INTO proyecto_design_tokens (proyecto_id, tokens_json)
                 VALUES (:proyecto_id, :tokens_json)'
            );
            $tokens->execute([
                'proyecto_id' => $id,
                'tokens_json' => json_encode([
                    'primary' => '#2043BF',
                    'secondary' => '#3974DC',
                    'accent' => '#E7DF68',
                    'background' => '#FFFFFF',
                    'surface' => '#FFFFFF',
                    'text' => '#1D1D1F',
                    'muted' => '#71717A',
                    'font_heading' => 'Inter',
                    'font_body' => 'Inter',
                    'container_width' => '1280px',
                    'section_spacing' => '96px',
                    'border_radius' => '12px',
                ], JSON_UNESCAPED_SLASHES),
            ]);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function slugExists(string $slug): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM proyectos WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return (bool)$stmt->fetchColumn();
    }

    public function nextCode(): string
    {
        $next = (int) Database::connection()->query('SELECT COALESCE(MAX(id),0)+1 FROM proyectos')->fetchColumn();
        return 'PRJ-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
