<?php
namespace App\Services;

use App\Helpers\Database;
use App\Repositories\ComponentRepository;
use App\Repositories\ProjectLayoutRepository;
use InvalidArgumentException;
use RuntimeException;

final class ComponentCustomizationService
{
    public function __construct(
        private readonly ComponentRepository $components = new ComponentRepository(),
        private readonly ProjectLayoutRepository $projectLayout = new ProjectLayoutRepository(),
        private readonly CustomizationValidator $validator = new CustomizationValidator(),
        private readonly CustomizationCssCompiler $compiler = new CustomizationCssCompiler(),
        private readonly AIComponentService $ai = new AIComponentService()
    ) {}

    /** @return array<int,string> */
    public function availableTargets(int $instanceId): array
    {
        $instance = $this->requireCustomizableInstance($instanceId);
        return $this->validator->targetsFromHtml((string)($instance['html_template'] ?? ''));
    }

    /**
     * Aplica un patch estructurado y devuelve el estado consolidado.
     * Esta API interna se usa en Fase A para pruebas manuales y será reutilizada por el preview/apply de IA.
     *
     * @param array<string,mixed> $patch
     * @return array<string,mixed>
     */
    public function applyPatch(int $instanceId, array $patch, int $userId, ?string $instruction = null, ?array $interpretation = null): array
    {
        if ($userId <= 0) throw new InvalidArgumentException('Usuario inválido para guardar la personalización.');
        $instance = $this->requireCustomizableInstance($instanceId);
        $targets = $this->validator->targetsFromHtml((string)($instance['html_template'] ?? ''));
        if ($targets === []) throw new InvalidArgumentException('Este componente todavía no expone elementos seguros para personalización.');

        $normalizedPatch = $this->validator->validatePatch($patch, $targets);
        $current = $this->decodeState((string)($instance['personalizacion_json'] ?? ''));
        $next = $this->consolidate($current, $normalizedPatch);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->ensureBaselineVersion($instanceId, $userId, $current);
            $this->components->updateInstanceCustomization($instanceId, $this->encode($next));
            $version = $this->components->nextCustomizationVersion($instanceId);
            $this->components->insertCustomizationVersion([
                'instance_id' => $instanceId,
                'version' => $version,
                'instruction' => $instruction,
                'interpretation_json' => $interpretation ? $this->encode($interpretation) : null,
                'patch_json' => $this->encode($normalizedPatch),
                'resulting_customization_json' => $this->encode($next),
                'created_by' => $userId,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        return $next;
    }

    /**
     * Genera y valida una propuesta sin persistirla.
     * @param array<int,string> $selectedTargets
     * @return array<string,mixed>
     */
    public function propose(int $instanceId, array $selectedTargets, string $instruction): array
    {
        $instance = $this->requireCustomizableInstance($instanceId);
        $available = $this->validator->targetsFromHtml((string)($instance['html_template'] ?? ''));
        $lookup = array_fill_keys($available, true);
        $targets = [];
        foreach ($selectedTargets as $target) {
            $target = trim((string)$target);
            if ($target !== '' && isset($lookup[$target])) $targets[$target] = true;
        }
        $targets = array_keys($targets);
        if ($targets === []) throw new InvalidArgumentException('Selecciona al menos un elemento válido en el Canvas.');
        $instruction = trim($instruction);
        if ($instruction === '') throw new InvalidArgumentException('Escribe qué quieres ajustar.');

        $proposal = $this->ai->proposeCustomization($instance, $targets, $instruction);
        $requiresVariant = !empty($proposal['requires_variant']);
        $interpretation = is_array($proposal['interpretation'] ?? null) ? $proposal['interpretation'] : [];
        if ($requiresVariant) {
            return [
                'requires_variant' => true,
                'interpretation' => $interpretation,
                'patch' => ['operations'=>[]],
                'preview_css' => '',
            ];
        }

        $patch = is_array($proposal['patch'] ?? null) ? $proposal['patch'] : [];
        $normalized = $this->validator->validatePatch($patch, $targets);
        $current = $this->decodeState((string)($instance['personalizacion_json'] ?? ''));
        $next = $this->consolidate($current, $normalized);
        $css = $this->compiler->compileState($next, $instanceId);

        return [
            'requires_variant' => false,
            'interpretation' => $interpretation,
            'patch' => $normalized,
            'preview_css' => $css,
        ];
    }

    /** @return array<string,mixed> */
    public function reset(int $instanceId, int $userId): array
    {
        $instance = $this->requireCustomizableInstance($instanceId);
        $current = $this->decodeState((string)($instance['personalizacion_json'] ?? ''));
        $empty = ['version'=>1, 'targets'=>[]];

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->ensureBaselineVersion($instanceId, $userId, $current);
            $this->components->updateInstanceCustomization($instanceId, $this->encode($empty));
            $version = $this->components->nextCustomizationVersion($instanceId);
            $this->components->insertCustomizationVersion([
                'instance_id'=>$instanceId,'version'=>$version,'instruction'=>'Restablecer personalización',
                'interpretation_json'=>null,'patch_json'=>$this->encode(['operations'=>[]]),
                'resulting_customization_json'=>$this->encode($empty),'created_by'=>$userId,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return $empty;
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $instanceId, int $limit = 30): array
    {
        $this->requireCustomizableInstance($instanceId);
        return $this->components->customizationVersions($instanceId, max(1, min(30, $limit)));
    }

    /** @return array<string,mixed> */
    public function restore(int $instanceId, int $version, int $userId): array
    {
        $this->requireCustomizableInstance($instanceId);
        $row = $this->components->findCustomizationVersion($instanceId, $version);
        if (!$row) throw new RuntimeException('La versión de personalización no existe.');

        $state = $this->decodeState((string)($row['resulting_customization_json'] ?? ''));
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $this->components->updateInstanceCustomization($instanceId, $this->encode($state));
            $nextVersion = $this->components->nextCustomizationVersion($instanceId);
            $this->components->insertCustomizationVersion([
                'instance_id'=>$instanceId,'version'=>$nextVersion,'instruction'=>'Restaurado desde versión ' . $version,
                'interpretation_json'=>null,'patch_json'=>$this->encode(['restore_from'=>$version]),
                'resulting_customization_json'=>$this->encode($state),'created_by'=>$userId,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        return $state;
    }

    /** @return array<string,mixed> */
    private function consolidate(array $current, array $patch): array
    {
        $state = $current;
        $state['version'] = 1;
        if (!isset($state['targets']) || !is_array($state['targets'])) $state['targets'] = [];

        foreach ($patch['operations'] ?? [] as $operation) {
            $target = (string)$operation['target'];
            $breakpoint = (string)$operation['breakpoint'];
            $property = (string)$operation['property'];
            $value = (string)$operation['value'];
            if (!isset($state['targets'][$target]) || !is_array($state['targets'][$target])) $state['targets'][$target] = [];
            if (!isset($state['targets'][$target][$breakpoint]) || !is_array($state['targets'][$target][$breakpoint])) $state['targets'][$target][$breakpoint] = [];
            $state['targets'][$target][$breakpoint][$property] = $value;
        }
        ksort($state['targets']);
        return $state;
    }

    private function ensureBaselineVersion(int $instanceId, int $userId, array $current): void
    {
        if ($this->components->hasCustomizationVersions($instanceId)) return;
        $this->components->insertCustomizationVersion([
            'instance_id'=>$instanceId,'version'=>0,'instruction'=>'Estado original',
            'interpretation_json'=>null,'patch_json'=>$this->encode(['operations'=>[]]),
            'resulting_customization_json'=>$this->encode($current),'created_by'=>$userId,
        ]);
    }

    private function requireCustomizableInstance(int $instanceId): array
    {
        $instance = $this->components->findInstance($instanceId);
        if (!$instance) throw new RuntimeException('La sección no existe o fue eliminada.');

        $category = strtolower((string)($instance['categoria_slug'] ?? ''));
        if (in_array($category, ['navbar','footer'], true)) {
            $projectId = (int)($instance['proyecto_id'] ?? 0);
            if ($projectId <= 0) throw new InvalidArgumentException('No se pudo validar el componente global.');
            $slotId = $this->projectLayout->slot($projectId, $category);
            if ($slotId !== $instanceId) {
                throw new InvalidArgumentException('Solo el Navbar o Footer global activo del proyecto puede personalizarse con Blumi.');
            }
            $instance['customization_scope'] = 'project_global';
        } else {
            $instance['customization_scope'] = 'page_instance';
        }
        return $instance;
    }

    /** @return array<string,mixed> */
    private function decodeState(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) return ['version'=>1, 'targets'=>[]];
        if (!isset($decoded['version'])) $decoded['version'] = 1;
        if (!isset($decoded['targets']) || !is_array($decoded['targets'])) $decoded['targets'] = [];
        return $decoded;
    }

    private function encode(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new RuntimeException('No se pudo serializar la personalización.');
        return $json;
    }
}
