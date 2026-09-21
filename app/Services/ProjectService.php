<?php
namespace App\Services;

use App\Repositories\ProjectRepository;
use InvalidArgumentException;

final class ProjectService
{
    public function __construct(private readonly ProjectRepository $projects = new ProjectRepository()) {}

    public function all(): array
    {
        return $this->projects->all();
    }

    public function find(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return $this->projects->find($id);
    }

    public function create(array $input, int $userId): int
    {
        $name = trim((string)($input['nombre'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('El nombre del proyecto es obligatorio.');
        }

        $slug = $this->slugify((string)($input['slug'] ?? $name));
        if ($slug === '') {
            $slug = 'proyecto';
        }
        $baseSlug = $slug;
        $suffix = 2;
        while ($this->projects->slugExists($slug)) {
            $slug = $baseSlug . '-' . $suffix++;
        }

        return $this->projects->create([
            'codigo' => $this->projects->nextCode(),
            'nombre' => $name,
            'slug' => $slug,
            'dominio' => trim((string)($input['dominio'] ?? '')) ?: null,
            'descripcion' => trim((string)($input['descripcion'] ?? '')) ?: null,
            'estado' => 'borrador',
            'usuario_propietario_id' => $userId,
            'created_by' => $userId,
        ]);
    }

    private function slugify(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}
