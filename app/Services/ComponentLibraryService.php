<?php
namespace App\Services;

use App\Repositories\ComponentLibraryRepository;
use InvalidArgumentException;

final class ComponentLibraryService
{
    public function __construct(private readonly ComponentLibraryRepository $repo=new ComponentLibraryRepository()) { $this->repo->ensureSchema(); }
    public function ensureInfrastructure(): void { $this->repo->ensureSchema(); }
    public function all(?int $projectId=null): array { return $this->repo->all($projectId); }
    public function projects(): array { return $this->repo->projects(); }
    public function find(int $id): array { $row=$this->repo->find($id); if(!$row) throw new InvalidArgumentException('La librería no existe.'); return $row; }
    public function create(string $name, ?int $projectId, int $userId): int { return $this->repo->create($name,$projectId,$userId); }
    public function assign(int $componentId,int $libraryId): void { $this->repo->assign($componentId,$libraryId); }
    public function forComponent(int $componentId): ?array { return $this->repo->libraryForComponent($componentId); }
    public function categoryCounts(int $libraryId,bool $approvedOnly=false): array { return $this->repo->categoryCounts($libraryId,$approvedOnly); }
    public function preferredForProject(int $projectId): ?array { return $this->repo->findForProject($projectId); }
}
