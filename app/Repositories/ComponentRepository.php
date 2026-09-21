<?php
namespace App\Repositories;

use App\Helpers\Database;

final class ComponentRepository
{
    public function __construct(private readonly ComponentLibraryRepository $libraries=new ComponentLibraryRepository()) { $this->libraries->ensureSchema(); }
    public function approvedLibrary(): array
    {
        $stmt = Database::connection()->query(
            "SELECT c.*, cc.nombre AS categoria_nombre, cc.slug AS categoria_slug, cc.orden AS categoria_orden,
                    cli.library_id, cl.nombre AS library_name, cl.slug AS library_slug, cl.proyecto_id AS library_project_id
             FROM componentes c
             INNER JOIN componentes_categorias cc ON cc.id = c.categoria_id
             INNER JOIN component_library_items cli ON cli.component_id=c.id
             INNER JOIN component_libraries cl ON cl.id=cli.library_id AND cl.deleted_at IS NULL AND cl.archived_at IS NULL
             WHERE c.estado = 'aprobado' AND c.deleted_at IS NULL AND c.archived_at IS NULL
             ORDER BY cl.nombre ASC, cc.orden ASC, c.nombre ASC, c.id ASC"
        );
        return $stmt->fetchAll();
    }

    public function findApproved(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.*, cc.nombre AS categoria_nombre, cc.slug AS categoria_slug,
                    cli.library_id, cl.nombre AS library_name, cl.slug AS library_slug, cl.proyecto_id AS library_project_id
             FROM componentes c
             INNER JOIN componentes_categorias cc ON cc.id = c.categoria_id
             INNER JOIN component_library_items cli ON cli.component_id=c.id
             INNER JOIN component_libraries cl ON cl.id=cli.library_id AND cl.deleted_at IS NULL AND cl.archived_at IS NULL
             WHERE c.id = :id AND c.estado = 'aprobado' AND c.deleted_at IS NULL AND c.archived_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function instancesForPage(int $pageId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT pc.*, c.codigo, c.nombre AS componente_nombre, c.html_template, c.css, c.js,
                    c.schema_campos, c.schema_configuracion, c.estado AS componente_estado,
                    cc.nombre AS categoria_nombre, cc.slug AS categoria_slug
             FROM pagina_componentes pc
             INNER JOIN componentes c ON c.id = pc.componente_id
             INNER JOIN componentes_categorias cc ON cc.id = c.categoria_id
             WHERE pc.pagina_id = :pagina_id AND pc.deleted_at IS NULL
             ORDER BY pc.orden ASC, pc.id ASC"
        );
        $stmt->execute(['pagina_id' => $pageId]);
        return $stmt->fetchAll();
    }

    public function findInstance(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT pc.*, p.proyecto_id, p.nombre AS pagina_nombre,
                    c.codigo, c.nombre AS componente_nombre, c.html_template, c.css, c.js,
                    c.schema_campos, c.schema_configuracion, c.estado AS componente_estado,
                    cc.nombre AS categoria_nombre, cc.slug AS categoria_slug
             FROM pagina_componentes pc
             INNER JOIN paginas p ON p.id = pc.pagina_id
             INNER JOIN componentes c ON c.id = pc.componente_id
             INNER JOIN componentes_categorias cc ON cc.id = c.categoria_id
             WHERE pc.id = :id AND pc.deleted_at IS NULL AND p.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function nextOrder(int $pageId): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(MAX(orden), 0) + 10 FROM pagina_componentes WHERE pagina_id = :pagina_id AND deleted_at IS NULL'
        );
        $stmt->execute(['pagina_id' => $pageId]);
        return (int)$stmt->fetchColumn();
    }

    public function createInstance(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO pagina_componentes
             (pagina_id, componente_id, orden, nombre_interno, visible, contenido_json, configuracion_json, estilos_override_json, version_componente, created_by)
             VALUES (:pagina_id, :componente_id, :orden, :nombre_interno, :visible, :contenido_json, :configuracion_json, :estilos_override_json, :version_componente, :created_by)'
        );
        $stmt->execute($data);
        return (int)Database::connection()->lastInsertId();
    }

    public function updateInstanceContent(int $id, string $contentJson): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pagina_componentes SET contenido_json = :contenido_json WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['contenido_json' => $contentJson, 'id' => $id]);
    }

    public function updateInstanceStyles(int $id, string $stylesJson): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pagina_componentes SET estilos_override_json = :estilos_override_json WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['estilos_override_json' => $stylesJson, 'id' => $id]);
    }

    public function softDeleteInstance(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pagina_componentes SET deleted_at = NOW(), visible = 0 WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }

    public function setOrder(int $id, int $order): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pagina_componentes SET orden = :orden WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['orden' => $order, 'id' => $id]);
    }

    public function updateInstanceCustomization(int $id, string $customizationJson): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE pagina_componentes SET personalizacion_json = :personalizacion_json WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['personalizacion_json' => $customizationJson, 'id' => $id]);
    }

    public function hasCustomizationVersions(int $instanceId): bool
    {
        $stmt = Database::connection()->prepare('SELECT 1 FROM component_instance_customization_versions WHERE instance_id=:instance_id LIMIT 1');
        $stmt->execute(['instance_id'=>$instanceId]);
        return (bool)$stmt->fetchColumn();
    }

    public function nextCustomizationVersion(int $instanceId): int
    {
        $stmt = Database::connection()->prepare('SELECT COALESCE(MAX(version),0)+1 FROM component_instance_customization_versions WHERE instance_id=:instance_id');
        $stmt->execute(['instance_id'=>$instanceId]);
        return max(1,(int)$stmt->fetchColumn());
    }

    public function insertCustomizationVersion(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO component_instance_customization_versions
             (instance_id,version,instruction,interpretation_json,patch_json,resulting_customization_json,created_by)
             VALUES (:instance_id,:version,:instruction,:interpretation_json,:patch_json,:resulting_customization_json,:created_by)'
        );
        $stmt->execute($data);
        return (int)Database::connection()->lastInsertId();
    }

    public function customizationVersions(int $instanceId, int $limit=30): array
    {
        $limit=max(1,min(30,$limit));
        $stmt=Database::connection()->prepare(
            'SELECT v.*,u.nombre AS created_by_name FROM component_instance_customization_versions v
             LEFT JOIN usuarios u ON u.id=v.created_by
             WHERE v.instance_id=:instance_id ORDER BY v.version DESC LIMIT '.$limit
        );
        $stmt->execute(['instance_id'=>$instanceId]);
        return $stmt->fetchAll();
    }

    public function findCustomizationVersion(int $instanceId,int $version): ?array
    {
        $stmt=Database::connection()->prepare(
            'SELECT * FROM component_instance_customization_versions WHERE instance_id=:instance_id AND version=:version LIMIT 1'
        );
        $stmt->execute(['instance_id'=>$instanceId,'version'=>$version]);
        $row=$stmt->fetch();
        return $row?:null;
    }

    public function createCustomizationThread(int $instanceId,int $userId): int
    {
        $stmt=Database::connection()->prepare(
            "INSERT INTO component_customization_threads (instance_id,status,created_by) VALUES (:instance_id,'active',:created_by)"
        );
        $stmt->execute(['instance_id'=>$instanceId,'created_by'=>$userId]);
        return (int)Database::connection()->lastInsertId();
    }

    public function addCustomizationMessage(int $threadId,string $role,string $message,?string $metadataJson=null): int
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO component_customization_messages (thread_id,role,message,metadata_json) VALUES (:thread_id,:role,:message,:metadata_json)'
        );
        $stmt->execute(['thread_id'=>$threadId,'role'=>$role,'message'=>$message,'metadata_json'=>$metadataJson]);
        return (int)Database::connection()->lastInsertId();
    }

    public function customizationMessages(int $threadId,int $limit=100): array
    {
        $limit=max(1,min(200,$limit));
        $stmt=Database::connection()->prepare(
            'SELECT * FROM component_customization_messages WHERE thread_id=:thread_id ORDER BY id ASC LIMIT '.$limit
        );
        $stmt->execute(['thread_id'=>$threadId]);
        return $stmt->fetchAll();
    }
}
