<?php
namespace App\Repositories;

use App\Helpers\Database;

final class ComponentFactoryRepository
{
    public function __construct(private readonly ComponentLibraryRepository $libraries=new ComponentLibraryRepository()) { $this->libraries->ensureSchema(); }

    public function categories(): array
    {
        $stmt = Database::connection()->query("SELECT id,nombre,slug,orden FROM componentes_categorias WHERE estado='activo' ORDER BY orden,nombre");
        return $stmt->fetchAll();
    }

    public function all(?int $libraryId=null, ?int $categoryId=null): array
    {
        $where=["c.deleted_at IS NULL","c.archived_at IS NULL"];
        $params=[];
        if($libraryId){ $where[]='cli.library_id=:library_id'; $params['library_id']=$libraryId; }
        if($categoryId){ $where[]='c.categoria_id=:category_id'; $params['category_id']=$categoryId; }
        $sql="SELECT c.*, cc.nombre categoria_nombre, cc.slug categoria_slug, cc.orden categoria_orden,
                    p.codigo parent_codigo, cli.library_id, cl.nombre library_name, cl.slug library_slug, cl.proyecto_id library_project_id,
                    (SELECT COUNT(*) FROM componentes ch WHERE ch.parent_component_id=c.id AND ch.deleted_at IS NULL) children_count,
                    (SELECT COUNT(*) FROM pagina_componentes pc WHERE pc.componente_id=c.id AND pc.deleted_at IS NULL) usage_count
             FROM componentes c
             JOIN componentes_categorias cc ON cc.id=c.categoria_id
             LEFT JOIN componentes p ON p.id=c.parent_component_id
             LEFT JOIN component_library_items cli ON cli.component_id=c.id
             LEFT JOIN component_libraries cl ON cl.id=cli.library_id AND cl.deleted_at IS NULL
             WHERE ".implode(' AND ',$where)."
             ORDER BY cc.orden,c.created_at DESC,c.id DESC";
        $stmt=Database::connection()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.*, cc.nombre categoria_nombre, cc.slug categoria_slug, cc.orden categoria_orden, p.codigo parent_codigo,
                    cli.library_id, cl.nombre library_name, cl.slug library_slug, cl.proyecto_id library_project_id,
                    (SELECT COUNT(*) FROM componentes ch WHERE ch.parent_component_id=c.id AND ch.deleted_at IS NULL) children_count,
                    (SELECT COUNT(*) FROM pagina_componentes pc WHERE pc.componente_id=c.id AND pc.deleted_at IS NULL) usage_count
             FROM componentes c
             JOIN componentes_categorias cc ON cc.id=c.categoria_id
             LEFT JOIN componentes p ON p.id=c.parent_component_id
             LEFT JOIN component_library_items cli ON cli.component_id=c.id
             LEFT JOIN component_libraries cl ON cl.id=cli.library_id AND cl.deleted_at IS NULL
             WHERE c.id=:id AND c.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch();
        return $row ?: null;
    }

    public function nextCode(string $categorySlug): string
    {
        $prefix = preg_replace('/[^a-z0-9]+/','_', strtolower($categorySlug)) ?: 'component';
        $stmt=Database::connection()->prepare("SELECT codigo FROM componentes WHERE codigo LIKE :p ORDER BY id DESC");
        $stmt->execute(['p'=>$prefix.'_%']);
        $max=0;
        foreach ($stmt->fetchAll() as $row) {
            if (preg_match('/_(\d+)$/',(string)$row['codigo'],$m)) $max=max($max,(int)$m[1]);
        }
        return $prefix.'_'.str_pad((string)($max+1),2,'0',STR_PAD_LEFT);
    }

    public function category(int $id): ?array
    {
        $stmt=Database::connection()->prepare("SELECT id,nombre,slug,orden FROM componentes_categorias WHERE id=:id AND estado='activo' LIMIT 1");
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(); return $row ?: null;
    }

    public function create(array $data): int
    {
        $sql="INSERT INTO componentes
            (codigo,nombre,categoria_id,parent_component_id,descripcion,html_template,css,js,schema_campos,schema_configuracion,thumbnail_path,reference_image_path,estado,origen,version_actual,created_by,is_system,is_locked,source_prompt,ai_model)
            VALUES
            (:codigo,:nombre,:categoria_id,:parent_component_id,:descripcion,:html_template,:css,:js,:schema_campos,:schema_configuracion,:thumbnail_path,:reference_image_path,:estado,:origen,1,:created_by,0,:is_locked,:source_prompt,:ai_model)";
        $stmt=Database::connection()->prepare($sql);
        $stmt->execute($data);
        return (int)Database::connection()->lastInsertId();
    }

    public function approve(int $id, int $categoryId, string $name): void
    {
        $stmt=Database::connection()->prepare("UPDATE componentes SET nombre=:nombre,categoria_id=:categoria_id,estado='aprobado',is_locked=1 WHERE id=:id AND deleted_at IS NULL");
        $stmt->execute(['nombre'=>$name,'categoria_id'=>$categoryId,'id'=>$id]);
    }

    public function updateCssDraft(int $id, string $css): void
    {
        $stmt=Database::connection()->prepare("UPDATE componentes SET css=:css WHERE id=:id AND deleted_at IS NULL AND estado<>'aprobado'");
        $stmt->execute(['css'=>$css,'id'=>$id]);
        if($stmt->rowCount()===0){
            $check=Database::connection()->prepare("SELECT estado FROM componentes WHERE id=:id AND deleted_at IS NULL LIMIT 1");
            $check->execute(['id'=>$id]);
            $row=$check->fetch();
            if(!$row) throw new \RuntimeException('El componente no existe.');
            if(($row['estado']??'')==='aprobado') throw new \RuntimeException('El componente aprobado es inmutable. Duplica una variante para editar su CSS.');
        }
    }

    public function saveVersion(int $componentId, array $component, int $userId, ?string $prompt): void
    {
        $stmt=Database::connection()->prepare("INSERT INTO componentes_versiones
            (componente_id,version,html_template,css,js,schema_campos,schema_configuracion,motivo,prompt_ia,created_by)
            VALUES (:componente_id,1,:html_template,:css,:js,:schema_campos,:schema_configuracion,:motivo,:prompt_ia,:created_by)");
        $stmt->execute([
            'componente_id'=>$componentId,
            'html_template'=>$component['html_template'],
            'css'=>$component['css'] ?? '',
            'js'=>$component['js'] ?? '',
            'schema_campos'=>$component['schema_campos'],
            'schema_configuracion'=>$component['schema_configuracion'] ?? '{}',
            'motivo'=>'Creación desde Component Factory',
            'prompt_ia'=>$prompt,
            'created_by'=>$userId,
        ]);
    }
}
