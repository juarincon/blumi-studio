<?php
namespace App\Repositories;

use App\Helpers\Database;
use InvalidArgumentException;
use PDO;

final class ComponentLibraryRepository
{
    private static bool $ready = false;

    public function ensureSchema(): void
    {
        if (self::$ready) return;
        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS component_libraries (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nombre VARCHAR(160) NOT NULL,
            slug VARCHAR(180) NOT NULL,
            proyecto_id BIGINT UNSIGNED NULL,
            descripcion TEXT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            archived_at DATETIME NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_component_libraries_slug (slug),
            UNIQUE KEY uq_component_libraries_project (proyecto_id),
            KEY idx_component_libraries_active (deleted_at, archived_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS component_library_items (
            component_id BIGINT UNSIGNED NOT NULL,
            library_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (component_id),
            KEY idx_component_library_items_library (library_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->ensureBuiltIns($pdo);
        $this->ensureProjectLibraries($pdo);
        $this->ensureCarouselCategory($pdo);
        $this->backfillComponents($pdo);
        self::$ready = true;
    }

    public function all(?int $projectId = null): array
    {
        $this->ensureSchema();
        $projectId = max(0, (int)$projectId);
        $sql = "SELECT l.*, p.nombre AS proyecto_nombre,
                       COUNT(cli.component_id) AS component_count,
                       COALESCE(SUM(c.estado='aprobado' AND c.deleted_at IS NULL AND c.archived_at IS NULL),0) AS approved_count,
                       COUNT(DISTINCT CASE WHEN c.deleted_at IS NULL AND c.archived_at IS NULL THEN c.categoria_id END) AS category_count
                FROM component_libraries l
                LEFT JOIN proyectos p ON p.id=l.proyecto_id AND p.deleted_at IS NULL
                LEFT JOIN component_library_items cli ON cli.library_id=l.id
                LEFT JOIN componentes c ON c.id=cli.component_id AND c.deleted_at IS NULL
                WHERE l.deleted_at IS NULL AND l.archived_at IS NULL
                GROUP BY l.id
                ORDER BY CASE
                    WHEN :project_id_filter > 0 AND l.proyecto_id=:project_id_match THEN 0
                    WHEN l.slug='predeterminados' THEN 1
                    WHEN l.slug='pruebas' THEN 2
                    ELSE 3 END,
                    l.nombre ASC, l.id ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['project_id_filter'=>$projectId,'project_id_match'=>$projectId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare("SELECT l.*,p.nombre proyecto_nombre FROM component_libraries l LEFT JOIN proyectos p ON p.id=l.proyecto_id WHERE l.id=:id AND l.deleted_at IS NULL AND l.archived_at IS NULL LIMIT 1");
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(); return $row ?: null;
    }

    public function findForProject(int $projectId): ?array
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare("SELECT * FROM component_libraries WHERE proyecto_id=:id AND deleted_at IS NULL AND archived_at IS NULL LIMIT 1");
        $stmt->execute(['id'=>$projectId]);
        $row=$stmt->fetch(); return $row ?: null;
    }

    public function projects(): array
    {
        $this->ensureSchema();
        return Database::connection()->query("SELECT id,nombre,slug FROM proyectos WHERE deleted_at IS NULL AND archived_at IS NULL ORDER BY nombre,id")->fetchAll();
    }

    public function create(string $name, ?int $projectId, int $userId): int
    {
        $this->ensureSchema();
        $name=trim($name);
        if($name==='') throw new InvalidArgumentException('Escribe un nombre para la librería.');
        if($projectId){
            $existing=$this->findForProject($projectId);
            if($existing) return (int)$existing['id'];
        }
        $slug=$this->uniqueSlug($name,$projectId);
        $stmt=Database::connection()->prepare("INSERT INTO component_libraries (nombre,slug,proyecto_id,descripcion,is_system,created_by) VALUES (:nombre,:slug,:proyecto_id,NULL,0,:created_by)");
        $stmt->execute(['nombre'=>$name,'slug'=>$slug,'proyecto_id'=>$projectId ?: null,'created_by'=>$userId ?: null]);
        return (int)Database::connection()->lastInsertId();
    }

    public function assign(int $componentId, int $libraryId): void
    {
        $this->ensureSchema();
        if(!$this->find($libraryId)) throw new InvalidArgumentException('La librería seleccionada no existe.');
        $stmt=Database::connection()->prepare("INSERT INTO component_library_items (component_id,library_id) VALUES (:component_id,:library_id) ON DUPLICATE KEY UPDATE library_id=VALUES(library_id),updated_at=CURRENT_TIMESTAMP");
        $stmt->execute(['component_id'=>$componentId,'library_id'=>$libraryId]);
    }

    public function libraryForComponent(int $componentId): ?array
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare("SELECT l.* FROM component_library_items cli JOIN component_libraries l ON l.id=cli.library_id WHERE cli.component_id=:id AND l.deleted_at IS NULL LIMIT 1");
        $stmt->execute(['id'=>$componentId]);
        $row=$stmt->fetch(); return $row ?: null;
    }

    public function categoryCounts(int $libraryId, bool $approvedOnly=false): array
    {
        $this->ensureSchema();
        $status = $approvedOnly ? " AND c.estado='aprobado'" : '';
        $stmt=Database::connection()->prepare("SELECT cc.id,cc.nombre,cc.slug,cc.orden,COUNT(*) component_count
            FROM component_library_items cli
            JOIN componentes c ON c.id=cli.component_id AND c.deleted_at IS NULL AND c.archived_at IS NULL {$status}
            JOIN componentes_categorias cc ON cc.id=c.categoria_id AND cc.estado='activo'
            WHERE cli.library_id=:library_id
            GROUP BY cc.id,cc.nombre,cc.slug,cc.orden
            ORDER BY cc.orden,cc.nombre");
        $stmt->execute(['library_id'=>$libraryId]);
        return $stmt->fetchAll();
    }

    private function ensureBuiltIns(PDO $pdo): void
    {
        $stmt=$pdo->prepare("INSERT IGNORE INTO component_libraries (nombre,slug,proyecto_id,descripcion,is_system,created_by) VALUES (?,?,?,?,?,NULL)");
        $stmt->execute(['Predeterminados','predeterminados',null,'Componentes base incluidos con Blumi.',1]);
        $stmt->execute(['Pruebas','pruebas',null,'Componentes creados durante pruebas y experimentación.',1]);
    }

    private function ensureProjectLibraries(PDO $pdo): void
    {
        $projects=$pdo->query("SELECT id,nombre,slug FROM proyectos WHERE deleted_at IS NULL AND archived_at IS NULL")->fetchAll();
        $check=$pdo->prepare("SELECT id FROM component_libraries WHERE proyecto_id=:id LIMIT 1");
        $insert=$pdo->prepare("INSERT INTO component_libraries (nombre,slug,proyecto_id,descripcion,is_system,created_by) VALUES (:nombre,:slug,:proyecto_id,:descripcion,0,NULL)");
        foreach($projects as $p){
            $check->execute(['id'=>(int)$p['id']]);
            if($check->fetchColumn()) continue;
            $insert->execute([
                'nombre'=>(string)$p['nombre'],
                'slug'=>'proyecto-'.(int)$p['id'].'-'.$this->slugify((string)$p['slug']),
                'proyecto_id'=>(int)$p['id'],
                'descripcion'=>'Librería vinculada al proyecto '.(string)$p['nombre'].'.',
            ]);
        }
    }

    private function ensureCarouselCategory(PDO $pdo): void
    {
        $stmt=$pdo->prepare("SELECT id FROM componentes_categorias WHERE slug='carousel' LIMIT 1");
        $stmt->execute();
        if(!$stmt->fetchColumn()){
            $pdo->prepare("INSERT INTO componentes_categorias (nombre,slug,orden,estado) VALUES ('Carousel','carousel',82,'activo')")->execute();
        }
    }

    private function backfillComponents(PDO $pdo): void
    {
        $defaults=(int)$pdo->query("SELECT id FROM component_libraries WHERE slug='predeterminados' LIMIT 1")->fetchColumn();
        $tests=(int)$pdo->query("SELECT id FROM component_libraries WHERE slug='pruebas' LIMIT 1")->fetchColumn();
        $stmt=$pdo->prepare("INSERT IGNORE INTO component_library_items (component_id,library_id)
            SELECT c.id, CASE WHEN c.is_system=1 THEN :defaults ELSE :tests END
            FROM componentes c
            LEFT JOIN component_library_items cli ON cli.component_id=c.id
            WHERE cli.component_id IS NULL AND c.deleted_at IS NULL");
        $stmt->execute(['defaults'=>$defaults,'tests'=>$tests]);
    }

    private function uniqueSlug(string $name, ?int $projectId): string
    {
        $base=$this->slugify($name) ?: 'libreria';
        if($projectId) $base='proyecto-'.$projectId.'-'.$base;
        $slug=$base; $i=2;
        $stmt=Database::connection()->prepare("SELECT 1 FROM component_libraries WHERE slug=:slug LIMIT 1");
        while(true){
            $stmt->execute(['slug'=>$slug]);
            if(!$stmt->fetchColumn()) return $slug;
            $slug=$base.'-'.$i++;
        }
    }

    private function slugify(string $value): string
    {
        $value=mb_strtolower(trim($value));
        $trans=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
        if(is_string($trans)&&$trans!=='') $value=$trans;
        $value=preg_replace('/[^a-z0-9]+/','-',$value) ?? $value;
        return trim($value,'-');
    }
}
