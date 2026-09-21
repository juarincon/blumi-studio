<?php
namespace App\Services;

final class CustomizationCssCompiler
{
    /** @var array<string,string> */
    private array $media = [
        'desktop' => '(min-width:1200px)',
        'tablet' => '(min-width:768px) and (max-width:1199px)',
        'mobile' => '(max-width:767px)',
    ];

    public function compileInstance(array $instance): string
    {
        $id = (int)($instance['id'] ?? 0);
        if ($id <= 0) return '';

        $state = $instance['personalizacion_json'] ?? '{}';
        if (is_string($state)) $state = json_decode($state, true);
        if (!is_array($state)) return '';

        return $this->compileState($state, $id);
    }

    public function compileState(array $state, int $instanceId): string
    {
        if ($instanceId <= 0) return '';
        $targets = $state['targets'] ?? null;
        if (!is_array($targets) || $targets === []) return '';

        $scope = '[data-blumi-instance="' . $instanceId . '"]';
        $rules = ['base'=>[], 'desktop'=>[], 'tablet'=>[], 'mobile'=>[]];

        foreach ($targets as $target => $breakpoints) {
            if (!is_string($target) || !is_array($breakpoints)) continue;
            $selector = $this->selectorForTarget($target);
            if ($selector === null) continue;

            foreach ($breakpoints as $breakpoint => $declarations) {
                $breakpoint = strtolower((string)$breakpoint);
                if (!isset($rules[$breakpoint]) || !is_array($declarations) || $declarations === []) continue;

                $parts = [];
                foreach ($declarations as $property => $value) {
                    if (!is_string($property) || !is_scalar($value)) continue;
                    $property = strtolower(trim($property));
                    $value = trim((string)$value);
                    if ($property === '' || $value === '') continue;

                    // Defensa en profundidad: aunque el estado persistido debería haber
                    // pasado por CustomizationValidator, nunca compilamos datos de DB
                    // como CSS sin volver a validar la declaración.
                    try {
                        $validated = (new CustomizationValidator())->validatePatch([
                            'operations' => [[
                                'target' => $target,
                                'breakpoint' => $breakpoint,
                                'property' => $property,
                                'value' => $value,
                            ]],
                        ], [$target]);
                        $safe = $validated['operations'][0] ?? null;
                        if (!is_array($safe)) continue;
                        $property = (string)$safe['property'];
                        $value = (string)$safe['value'];
                    } catch (\Throwable) {
                        continue;
                    }

                    $parts[] = $property . ':' . $value . ' !important';
                }
                if ($parts) $rules[$breakpoint][] = $scope . ' ' . $selector . '{' . implode(';', $parts) . ';}';
            }
        }

        $out = [];
        if ($rules['base']) $out[] = implode("\n", $rules['base']);
        foreach (['desktop','tablet','mobile'] as $breakpoint) {
            if (!$rules[$breakpoint]) continue;
            $out[] = '@media ' . $this->media[$breakpoint] . "{\n" . implode("\n", $rules[$breakpoint]) . "\n}";
        }
        return implode("\n", $out);
    }

    private function selectorForTarget(string $target): ?string
    {
        if (preg_match('/^class:([A-Za-z_][A-Za-z0-9_-]{0,79})$/', $target, $m)) return '.' . $m[1];
        if (preg_match('/^id:([A-Za-z_][A-Za-z0-9_-]{0,79})$/', $target, $m)) return '#' . $m[1];
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $target)) return '[data-blumi-element="' . $target . '"]';
        return null;
    }
}
