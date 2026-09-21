<?php
namespace App\Services;

use App\Repositories\PageRepository;
use App\Repositories\ProjectRepository;
use InvalidArgumentException;
use RuntimeException;

final class PageService
{
    public function __construct(
        private readonly PageRepository $pages = new PageRepository(),
        private readonly ProjectRepository $projects = new ProjectRepository()
    ) {}

    public function forProject(int $projectId): array
    {
        $this->requireProject($projectId);
        return $this->pages->forProject($projectId);
    }

    public function find(int $pageId): array
    {
        $page = $this->pages->find($pageId);
        if (!$page) {
            throw new RuntimeException('La página no existe o fue archivada.');
        }
        return $page;
    }

    public function create(array $input, int $userId): int
    {
        $projectId = (int)($input['proyecto_id'] ?? 0);
        $this->requireProject($projectId);

        $name = trim((string)($input['nombre'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('El nombre de la página es obligatorio.');
        }

        // El slug es un detalle técnico: se genera y corrige automáticamente.
        // Si un usuario avanzado escribe uno, se toma como preferencia, no como bloqueo.
        $preferredSlug = trim((string)($input['slug'] ?? ''));
        $slugBase = $this->slugify($preferredSlug !== '' ? $preferredSlug : $name);
        if ($slugBase === '') {
            $slugBase = 'pagina';
        }
        $slug = $this->uniqueSlug($projectId, $slugBase);

        $count = $this->pages->activeCount($projectId);
        $isHome = $count === 0;

        try {
            return $this->pages->create([
                'proyecto_id' => $projectId,
                'nombre' => $name,
                'slug' => $slug,
                'estado' => 'borrador',
                'orden' => $this->pages->nextOrder($projectId),
                'es_inicio' => $isHome ? 1 : 0,
                'visible' => 1,
                'created_by' => $userId,
            ]);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw new RuntimeException('No se pudo reservar la ruta automática. Inténtalo nuevamente.');
            }
            throw $e;
        }
    }

    public function rename(int $pageId, string $name): array
    {
        $page=$this->find($pageId);
        $name=trim($name);
        if ($name === '') throw new InvalidArgumentException('El nombre de la página es obligatorio.');
        if (mb_strlen($name) > 140) throw new InvalidArgumentException('El nombre de la página es demasiado largo.');
        $this->pages->rename($pageId,$name);
        return $this->find($pageId);
    }

    public function archive(int $pageId): int
    {
        $page = $this->find($pageId);
        if ((int)$page['es_inicio'] === 1) {
            throw new InvalidArgumentException('No puedes archivar la página de inicio. Define otra página como inicio primero.');
        }
        $this->pages->softDelete($pageId);
        return (int)$page['proyecto_id'];
    }

    private function requireProject(int $projectId): array
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Proyecto inválido.');
        }
        $project = $this->projects->find($projectId);
        if (!$project) {
            throw new RuntimeException('El proyecto no existe o fue archivado.');
        }
        return $project;
    }

    private function uniqueSlug(int $projectId, string $base): string
    {
        $candidate = $base;
        $suffix = 2;

        while ($this->pages->slugExists($projectId, $candidate)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugify(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
