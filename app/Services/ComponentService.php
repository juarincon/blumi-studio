<?php
namespace App\Services;

use App\Repositories\ComponentRepository;
use App\Repositories\PageRepository;
use InvalidArgumentException;
use RuntimeException;

final class ComponentService
{
    public function __construct(
        private readonly ComponentRepository $components = new ComponentRepository(),
        private readonly PageRepository $pages = new PageRepository()
    ) {}

    public function library(): array
    {
        return $this->components->approvedLibrary();
    }

    public function instancesForPage(int $pageId): array
    {
        $this->requirePage($pageId);
        return $this->components->instancesForPage($pageId);
    }

    public function findInstance(int $instanceId): array
    {
        $instance = $this->components->findInstance($instanceId);
        if (!$instance) {
            throw new RuntimeException('La sección no existe o fue eliminada.');
        }
        $instance['schema'] = $this->decodeObject($instance['schema_campos'] ?? '{}');
        $instance['content'] = $this->decodeObject($instance['contenido_json'] ?? '{}');
        $instance['styles'] = $this->decodeObject($instance['estilos_override_json'] ?? '{}');
        $instance['customization'] = $this->decodeObject($instance['personalizacion_json'] ?? '{}');
        $instance['config'] = $this->decodeObject($instance['schema_configuracion'] ?? '{}');
        $instance['style_controls'] = $this->styleControlsFor($instance);
        $instance['visibility_controls'] = $this->visibilityControlsFor($instance);
        return $instance;
    }

    public function addToPage(int $pageId, int $componentId, int $userId): int
    {
        $this->requirePage($pageId);
        $component = $this->components->findApproved($componentId);
        if (!$component) {
            throw new InvalidArgumentException('El componente seleccionado no está disponible.');
        }

        $schema = $this->decodeObject($component['schema_campos'] ?? '{}');
        $defaults = [];
        foreach ($schema as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }
            $defaults[$key] = isset($definition['default']) && is_scalar($definition['default'])
                ? (string)$definition['default']
                : '';
        }

        return $this->components->createInstance([
            'pagina_id' => $pageId,
            'componente_id' => $componentId,
            'orden' => $this->components->nextOrder($pageId),
            'nombre_interno' => $component['nombre'],
            'visible' => 1,
            'contenido_json' => $this->encode($defaults),
            'configuracion_json' => '{}',
            'estilos_override_json' => '{}',
            'version_componente' => (int)$component['version_actual'],
            'created_by' => $userId,
        ]);
    }

    public function updateContent(int $instanceId, array $input, array $files, string $role, int $userId): array
    {
        $instance = $this->findInstance($instanceId);
        $schema = $instance['schema'];
        $current = $instance['content'];
        $errors = [];
        $assets = new AssetService();

        foreach ($schema as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }

            if ($role === 'cliente' && empty($definition['client_editable'])) {
                continue;
            }

            $type = (string)($definition['type'] ?? 'text');
            if (!in_array($type, ['text', 'textarea', 'url', 'email', 'phone', 'number', 'image'], true)) {
                continue;
            }

            if ($type === 'image') {
                $fileKey = '__file_' . $key;
                $uploaded = $files[$fileKey] ?? null;
                $uploadError = is_array($uploaded) ? (int)($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

                if (is_array($uploaded) && $uploadError !== UPLOAD_ERR_NO_FILE) {
                    try {
                        $assetType = str_contains(strtolower($key), 'logo') ? 'logo' : 'image';
                        $asset = $assets->uploadImage((int)$instance['proyecto_id'], $uploaded, $userId, $assetType);
                        $value = (string)$asset['path'];
                    } catch (\Throwable $e) {
                        $errors[] = (string)($definition['label'] ?? $key) . ': ' . $e->getMessage();
                        continue;
                    }
                } else {
                    $value = trim((string)($input[$key] ?? ($current[$key] ?? '')));
                }
            } else {
                $value = trim((string)($input[$key] ?? ''));
            }

            // Smart Links son contenido estructurado. Una regresión posterior a v0.9
            // había dejado el UI pero había perdido esta persistencia, por lo que el
            // botón "Guardar contenido" parecía no hacer nada para los campos URL.
            if ($type === 'url' && isset($input['link_meta'][$key]) && is_array($input['link_meta'][$key])) {
                $linkMeta = $this->normalizeLinkMeta(
                    $instance,
                    $key,
                    $input['link_meta'][$key],
                    (int)($input['page_id'] ?? $instance['pagina_id'] ?? 0)
                );
                if (!isset($current['__blumi_links']) || !is_array($current['__blumi_links'])) {
                    $current['__blumi_links'] = [];
                }
                $current['__blumi_links'][$key] = $linkMeta;
                $value = $this->fallbackValueForLinkMeta($linkMeta);
            }

            if (!empty($definition['required']) && $value === '') {
                $errors[] = (string)($definition['label'] ?? $key) . ' es obligatorio.';
                continue;
            }

            if ($type === 'email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = (string)($definition['label'] ?? $key) . ' no tiene un email válido.';
                continue;
            }

            $current[$key] = $value;
        }

        if ($errors) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->components->updateInstanceContent($instanceId, $this->encode($current));
        return $this->findInstance($instanceId);
    }

    public function updateStyles(int $instanceId, array $input, array $files = [], int $userId = 0): array
    {
        $instance = $this->findInstance($instanceId);
        $controls = is_array($instance['style_controls'] ?? null) ? $instance['style_controls'] : [];
        $current = is_array($instance['styles'] ?? null) ? $instance['styles'] : [];

        if (!empty($input['reset_styles'])) {
            $this->components->updateInstanceStyles($instanceId, '{}');
            return $this->findInstance($instanceId);
        }

        $palette = ['inherit','primary','secondary','accent','background','surface','text','muted','transparent'];
        $radii = ['inherit','none','soft','medium','large','pill'];
        $shadows = ['inherit','none','subtle','medium','large'];
        $shadowDirections = ['inherit','bottom','top','both','around'];
        $shadowOpacities = ['inherit','soft','medium','strong','solid'];
        $navbarPositions = ['inherit','static','fixed'];
        $layoutWidths = ['inherit','normal','wide','full'];

        foreach ($controls as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) continue;
            $type = (string)($definition['type'] ?? 'color');
            $value = trim((string)($input[$key] ?? 'inherit'));
            $allowed = match ($type) {
                'radius' => $radii,
                'shadow' => $shadows,
                'shadow_direction' => $shadowDirections,
                'shadow_opacity' => $shadowOpacities,
                'navbar_position' => $navbarPositions,
                'layout_width' => $layoutWidths,
                default => $palette,
            };
            if (!in_array($value, $allowed, true)) {
                throw new InvalidArgumentException('Valor de estilo no permitido para ' . (string)($definition['label'] ?? $key) . '.');
            }
            if ($value === 'inherit') unset($current[$key]); else $current[$key] = $value;
        }

        // Visibilidad por instancia. No elimina contenido ni modifica el componente maestro.
        $hiddenFields = [];
        foreach ($instance['visibility_controls'] ?? [] as $fieldKey => $definition) {
            if (!is_string($fieldKey)) continue;
            if (!empty($input['hide__' . $fieldKey])) $hiddenFields[] = $fieldKey;
        }
        if ($hiddenFields) $current['hidden_fields'] = array_values(array_unique($hiddenFields));
        else unset($current['hidden_fields']);

        // Fondo de sección: color o imagen, nunca ambos activos al mismo tiempo.
        $backgroundMode = (string)($input['background_mode'] ?? ($current['background_mode'] ?? 'color'));
        if (!in_array($backgroundMode, ['color','image'], true)) $backgroundMode = 'color';
        $current['background_mode'] = $backgroundMode;
        if ($backgroundMode === 'image') {
            $uploaded = $files['background_image'] ?? null;
            if (is_array($uploaded) && (int)($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                if ($userId <= 0) throw new InvalidArgumentException('No se pudo identificar al usuario para guardar la imagen.');
                $asset = (new AssetService())->uploadImage((int)$instance['proyecto_id'], $uploaded, $userId, 'image');
                $current['background_image'] = (string)$asset['path'];
            }
            if (empty($current['background_image'])) {
                throw new InvalidArgumentException('Selecciona una imagen para usarla como fondo.');
            }
        }

        $this->components->updateInstanceStyles($instanceId, $this->encode($current));
        return $this->findInstance($instanceId);
    }

    public function remove(int $instanceId): int
    {
        $instance = $this->findInstance($instanceId);
        $this->components->softDeleteInstance($instanceId);
        return (int)$instance['pagina_id'];
    }

    public function move(int $instanceId, string $direction): int
    {
        $instance = $this->findInstance($instanceId);
        $all = $this->components->instancesForPage((int)$instance['pagina_id']);
        $index = null;
        foreach ($all as $i => $row) {
            if ((int)$row['id'] === $instanceId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return (int)$instance['pagina_id'];
        }

        $otherIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($all[$otherIndex])) {
            return (int)$instance['pagina_id'];
        }

        $other = $all[$otherIndex];
        $this->components->setOrder($instanceId, (int)$other['orden']);
        $this->components->setOrder((int)$other['id'], (int)$instance['orden']);
        return (int)$instance['pagina_id'];
    }

    private function requirePage(int $pageId): array
    {
        $page = $this->pages->find($pageId);
        if (!$page) {
            throw new InvalidArgumentException('La página no existe o fue archivada.');
        }
        return $page;
    }

    private function styleControlsFor(array $instance): array
    {
        $config = $this->decodeObject((string)($instance['schema_configuracion'] ?? '{}'));
        $declared = $config['style_controls'] ?? null;
        $universal = [
            'section_background' => ['type'=>'color','label'=>'Color lateral / fondo de sección'],
            'surface_color' => ['type'=>'color','label'=>'Fondo del componente'],
            'heading_color' => ['type'=>'color','label'=>'Títulos'],
            'text_color' => ['type'=>'color','label'=>'Texto general'],
            'layout_width' => ['type'=>'layout_width','label'=>'Ancho del componente'],
        ];
        $category = strtolower((string)($instance['categoria_slug'] ?? ''));
        if ($category === '') {
            $category = strtolower((string)($instance['categoria_nombre'] ?? ''));
        }
        // Posición del navbar es una capacidad de layout de Blumi, no del schema generado.
        // Por eso debe existir en TODOS los navbars, incluso si la IA declaró sus propios
        // style_controls o si el componente fue creado antes de que existiera este control.
        if ($category === 'navbar') {
            $universal['navbar_position'] = ['type'=>'navbar_position','label'=>'Posición del navbar'];
        }
        if (is_array($declared) && $declared) {
            // Los componentes pueden declarar controles específicos, pero los controles
            // universales de Blumi prevalecen para mantener un contrato consistente.
            return array_merge($declared, $universal);
        }

        $base = [
            'section_background' => ['type'=>'color','label'=>'Color lateral / fondo de sección'],
            'surface_color' => ['type'=>'color','label'=>'Fondo del componente'],
            'text_color' => ['type'=>'color','label'=>'Texto general'],
            'heading_color' => ['type'=>'color','label'=>'Títulos'],
            'link_color' => ['type'=>'color','label'=>'Enlaces'],
            'button_background' => ['type'=>'color','label'=>'Fondo de botones'],
            'button_text' => ['type'=>'color','label'=>'Texto de botones'],
            'section_radius' => ['type'=>'radius','label'=>'Radio de sección'],
            'button_radius' => ['type'=>'radius','label'=>'Radio de botones'],
            'section_shadow' => ['type'=>'shadow','label'=>'Sombra de sección'],
            'layout_width' => ['type'=>'layout_width','label'=>'Ancho del componente'],
            'shadow_direction' => ['type'=>'shadow_direction','label'=>'Dirección de la sombra'],
            'shadow_opacity' => ['type'=>'shadow_opacity','label'=>'Opacidad de la sombra'],
        ];

        if ($category === 'navbar') {
            $base = [
                'section_background' => ['type'=>'color','label'=>'Color lateral / fondo de sección'],
                'surface_color' => ['type'=>'color','label'=>'Fondo del componente'],
                'link_color' => ['type'=>'color','label'=>'Enlaces'],
                'active_link_color' => ['type'=>'color','label'=>'Enlace activo'],
                'heading_color' => ['type'=>'color','label'=>'Títulos'],
                'button_background' => ['type'=>'color','label'=>'Fondo CTA'],
                'button_text' => ['type'=>'color','label'=>'Texto CTA'],
                'section_radius' => ['type'=>'radius','label'=>'Radio del navbar'],
                'button_radius' => ['type'=>'radius','label'=>'Radio CTA'],
                'section_shadow' => ['type'=>'shadow','label'=>'Sombra del navbar'],
                'layout_width' => ['type'=>'layout_width','label'=>'Ancho del componente'],
                'shadow_direction' => ['type'=>'shadow_direction','label'=>'Dirección de la sombra'],
                'shadow_opacity' => ['type'=>'shadow_opacity','label'=>'Opacidad de la sombra'],
                'navbar_position' => ['type'=>'navbar_position','label'=>'Posición del navbar'],
            ];
        }
        return $base;
    }

    private function visibilityControlsFor(array $instance): array
    {
        $schema = $this->decodeObject((string)($instance['schema_campos'] ?? '{}'));
        $controls = [];
        foreach ($schema as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) continue;
            $type = strtolower((string)($definition['type'] ?? 'text'));
            // Los campos URL controlan destinos, no elementos visuales independientes.
            if ($type === 'url') continue;
            if (!in_array($type, ['text','textarea','image','email','phone','number'], true)) continue;
            $controls[$key] = [
                'label' => (string)($definition['label'] ?? $key),
                'type' => $type,
            ];
        }
        return $controls;
    }


    private function normalizeLinkMeta(array $instance, string $fieldKey, array $meta, int $currentPageId): array
    {
        $type = strtolower(trim((string)($meta['type'] ?? 'none')));
        $allowed = ['none','section','page','page_section','external','email','phone'];
        if (!in_array($type, $allowed, true)) $type = 'none';

        $projectId = (int)($instance['proyecto_id'] ?? 0);
        $result = ['type' => $type];

        if ($type === 'section') {
            $targetPageId = $currentPageId > 0 ? $currentPageId : (int)($instance['pagina_id'] ?? 0);
            $targetInstanceId = (int)($meta['section_id'] ?? 0);
            $this->assertLinkSectionBelongsToPage($projectId, $targetPageId, $targetInstanceId);
            $result['page_id'] = $targetPageId;
            $result['section_id'] = $targetInstanceId;
        } elseif ($type === 'page') {
            $targetPageId = (int)($meta['page_id'] ?? 0);
            $this->assertLinkPageBelongsToProject($projectId, $targetPageId);
            $result['page_id'] = $targetPageId;
        } elseif ($type === 'page_section') {
            $targetPageId = (int)($meta['page_id'] ?? 0);
            $targetInstanceId = (int)($meta['section_id'] ?? 0);
            $this->assertLinkPageBelongsToProject($projectId, $targetPageId);
            $this->assertLinkSectionBelongsToPage($projectId, $targetPageId, $targetInstanceId);
            $result['page_id'] = $targetPageId;
            $result['section_id'] = $targetInstanceId;
        } elseif ($type === 'external') {
            $url = trim((string)($meta['value'] ?? ''));
            if ($url === '') throw new InvalidArgumentException('Escribe una URL para ' . $fieldKey . '.');
            if (!preg_match('#^(https?://|/|#)#i', $url)) $url = 'https://' . $url;
            $result['value'] = $url;
        } elseif ($type === 'email') {
            $email = trim((string)($meta['value'] ?? ''));
            $email = preg_replace('/^mailto:/i', '', $email) ?? $email;
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Escribe un email válido para ' . $fieldKey . '.');
            }
            $result['value'] = $email;
        } elseif ($type === 'phone') {
            $phone = trim((string)($meta['value'] ?? ''));
            $phone = preg_replace('/^tel:/i', '', $phone) ?? $phone;
            if ($phone === '') throw new InvalidArgumentException('Escribe un teléfono para ' . $fieldKey . '.');
            $result['value'] = $phone;
        }

        return $result;
    }

    private function fallbackValueForLinkMeta(array $meta): string
    {
        return match ((string)($meta['type'] ?? 'none')) {
            'external' => (string)($meta['value'] ?? '#'),
            'email' => 'mailto:' . (string)($meta['value'] ?? ''),
            'phone' => 'tel:' . (string)($meta['value'] ?? ''),
            default => '#',
        };
    }

    private function assertLinkPageBelongsToProject(int $projectId, int $pageId): void
    {
        $page = $this->pages->find($pageId);
        if (!$page || (int)$page['proyecto_id'] !== $projectId) {
            throw new InvalidArgumentException('La página seleccionada ya no está disponible en este proyecto.');
        }
    }

    private function assertLinkSectionBelongsToPage(int $projectId, int $pageId, int $instanceId): void
    {
        $this->assertLinkPageBelongsToProject($projectId, $pageId);
        $valid = false;
        foreach ($this->components->instancesForPage($pageId) as $row) {
            if ((int)($row['id'] ?? 0) !== $instanceId) continue;
            $category = strtolower((string)($row['categoria_slug'] ?? ''));
            if (in_array($category, ['navbar','footer'], true)) break;
            $valid = true;
            break;
        }
        if (!$valid) {
            throw new InvalidArgumentException('La sección seleccionada ya no está disponible en esa página.');
        }
    }

    private function decodeObject(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encode(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('No se pudo guardar la configuración del componente.');
        }
        return $json;
    }
}
