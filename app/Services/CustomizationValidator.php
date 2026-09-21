<?php
namespace App\Services;

use InvalidArgumentException;

final class CustomizationValidator
{
    private const BREAKPOINTS = ['base', 'desktop', 'tablet', 'mobile'];

    /** @var array<string,true> */
    private array $allowedProperties = [
        'margin'=>true,'margin-top'=>true,'margin-right'=>true,'margin-bottom'=>true,'margin-left'=>true,
        'padding'=>true,'padding-top'=>true,'padding-right'=>true,'padding-bottom'=>true,'padding-left'=>true,
        'gap'=>true,'row-gap'=>true,'column-gap'=>true,
        'width'=>true,'min-width'=>true,'max-width'=>true,'height'=>true,'min-height'=>true,'max-height'=>true,
        'display'=>true,'flex-direction'=>true,'flex-wrap'=>true,'justify-content'=>true,'align-items'=>true,
        'align-content'=>true,'align-self'=>true,'justify-self'=>true,'order'=>true,
        'grid-template-columns'=>true,'grid-template-rows'=>true,'grid-column'=>true,'grid-row'=>true,
        'position'=>true,'top'=>true,'right'=>true,'bottom'=>true,'left'=>true,
        'transform'=>true,'border-radius'=>true,'box-shadow'=>true,'opacity'=>true,
        'object-fit'=>true,'object-position'=>true,'text-align'=>true,'line-height'=>true,
        'letter-spacing'=>true,'font-size'=>true,'font-weight'=>true,
        'color'=>true,'background'=>true,'background-color'=>true,'border-color'=>true,
        'visibility'=>true,
    ];

    /** @var array<string,true> */
    private array $tokenOnlyColorProperties = [
        'color'=>true,'background'=>true,'background-color'=>true,'border-color'=>true,
    ];

    /**
     * @param array<string,mixed> $patch
     * @param array<int,string> $allowedTargets
     * @return array{operations:array<int,array{target:string,breakpoint:string,property:string,value:string}>}
     */
    public function validatePatch(array $patch, array $allowedTargets): array
    {
        $operations = $patch['operations'] ?? null;
        if (!is_array($operations) || $operations === []) {
            throw new InvalidArgumentException('La personalización no contiene operaciones válidas.');
        }
        if (count($operations) > 80) {
            throw new InvalidArgumentException('La personalización intenta hacer demasiados cambios en una sola operación.');
        }

        $allowedLookup = array_fill_keys($allowedTargets, true);
        $normalized = [];
        foreach ($operations as $index => $operation) {
            if (!is_array($operation)) {
                throw new InvalidArgumentException('La operación #' . ($index + 1) . ' no es válida.');
            }

            $target = trim((string)($operation['target'] ?? ''));
            $breakpoint = strtolower(trim((string)($operation['breakpoint'] ?? 'base')));
            $property = strtolower(trim((string)($operation['property'] ?? '')));
            $value = trim((string)($operation['value'] ?? ''));

            if (!$this->isSafeTarget($target) || !isset($allowedLookup[$target])) {
                throw new InvalidArgumentException('El elemento objetivo no pertenece al componente seleccionado: ' . ($target !== '' ? $target : 'vacío') . '.');
            }
            if (!in_array($breakpoint, self::BREAKPOINTS, true)) {
                throw new InvalidArgumentException('Breakpoint no permitido: ' . $breakpoint . '.');
            }
            if (!isset($this->allowedProperties[$property])) {
                throw new InvalidArgumentException('Propiedad visual no permitida: ' . ($property !== '' ? $property : 'vacía') . '.');
            }

            $normalizedValue = $this->validateValue($property, $value);
            $normalized[] = [
                'target' => $target,
                'breakpoint' => $breakpoint,
                'property' => $property,
                'value' => $normalizedValue,
            ];
        }

        return ['operations' => $normalized];
    }

    /** @return array<int,string> */
    public function targetsFromHtml(string $html): array
    {
        $targets = [];

        if (preg_match_all('/\bdata-blumi-element\s*=\s*(["\'])([^"\']+)\1/i', $html, $matches)) {
            foreach ($matches[2] as $value) {
                $value = trim((string)$value);
                if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $value)) $targets[$value] = true;
            }
        }

        if (preg_match_all('/\bid\s*=\s*(["\'])([^"\']+)\1/i', $html, $matches)) {
            foreach ($matches[2] as $value) {
                $value = trim((string)$value);
                if ($this->isCssIdentifier($value)) $targets['id:' . $value] = true;
            }
        }

        if (preg_match_all('/\bclass\s*=\s*(["\'])([^"\']+)\1/i', $html, $matches)) {
            foreach ($matches[2] as $classList) {
                foreach (preg_split('/\s+/', trim((string)$classList)) ?: [] as $class) {
                    if (!$this->isCssIdentifier($class)) continue;

                    // Los componentes historicos de Blumi usan mayoritariamente clases
                    // bl-generated-*. Son clases propias del componente, no del Builder,
                    // y son targets seguros cuando el Canvas confirma que son unicas.
                    // Seguimos excluyendo clases internas de infraestructura para evitar
                    // que Personalizar pueda apuntar al layout del Builder/renderer.
                    $isGeneratedComponentClass = str_starts_with($class, 'bl-generated-');
                    $isLegacyComponentClass = !str_starts_with($class, 'bl-') && !str_starts_with($class, 'blumi-');
                    if ($isGeneratedComponentClass || $isLegacyComponentClass) {
                        $targets['class:' . $class] = true;
                    }
                }
            }
        }

        $values = array_keys($targets);
        sort($values, SORT_STRING);
        return $values;
    }

    private function validateValue(string $property, string $value): string
    {
        if ($value === '' || strlen($value) > 240) {
            throw new InvalidArgumentException('Valor inválido para ' . $property . '.');
        }

        $lower = strtolower($value);
        foreach (['!important', 'javascript:', 'expression(', 'url(', '@import', '@font-face', '<script', '</script', '{', '}', ';'] as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                throw new InvalidArgumentException('El valor de ' . $property . ' contiene sintaxis no permitida.');
            }
        }

        if (isset($this->tokenOnlyColorProperties[$property])) {
            if (!$this->isAllowedColorToken($value)) {
                throw new InvalidArgumentException('Los colores de Personalizar con Blumi deben usar la paleta del proyecto.');
            }
            return $value;
        }

        if ($property === 'display') return $this->enum($property, $value, ['block','inline','inline-block','flex','inline-flex','grid','inline-grid','none']);
        if ($property === 'flex-direction') return $this->enum($property, $value, ['row','row-reverse','column','column-reverse']);
        if ($property === 'flex-wrap') return $this->enum($property, $value, ['nowrap','wrap','wrap-reverse']);
        if (in_array($property, ['justify-content','align-items','align-content','align-self','justify-self'], true)) {
            return $this->enum($property, $value, ['normal','stretch','center','start','end','flex-start','flex-end','space-between','space-around','space-evenly','baseline','auto']);
        }
        if ($property === 'position') return $this->enum($property, $value, ['static','relative','absolute']);
        if ($property === 'object-fit') return $this->enum($property, $value, ['fill','contain','cover','none','scale-down']);
        if ($property === 'text-align') return $this->enum($property, $value, ['left','right','center','justify','start','end']);
        if ($property === 'visibility') return $this->enum($property, $value, ['visible','hidden']);

        if ($property === 'opacity') {
            if (!is_numeric($value) || (float)$value < 0 || (float)$value > 1) throw new InvalidArgumentException('Opacity debe estar entre 0 y 1.');
            return (string)(float)$value;
        }
        if ($property === 'font-weight') {
            if (!preg_match('/^(?:normal|bold|[1-9]00)$/', $value)) throw new InvalidArgumentException('Peso tipográfico no permitido.');
            return $value;
        }
        if ($property === 'order') {
            if (!preg_match('/^-?\d{1,3}$/', $value) || abs((int)$value) > 100) throw new InvalidArgumentException('Orden fuera de rango.');
            return (string)(int)$value;
        }
        if ($property === 'font-size') {
            $this->assertLengthRange($property, $value, 8, 220, false);
            return $value;
        }
        if ($property === 'letter-spacing') {
            $this->assertLengthRange($property, $value, -32, 64, true);
            return $value;
        }
        if ($property === 'border-radius') {
            if (!$this->isLengthLike($value, false, true)) throw new InvalidArgumentException('Radio no permitido.');
            if (preg_match('/^([0-9]+(?:\.[0-9]+)?)px$/', $value, $m) && (float)$m[1] > 999) throw new InvalidArgumentException('Radio fuera de rango.');
            return $value;
        }
        if (str_starts_with($property, 'margin')) {
            if (!$this->isLengthList($value, true, true, 4)) throw new InvalidArgumentException('Margen no permitido.');
            $this->assertPxTokensInRange($property, $value, -256, 512);
            return $value;
        }
        if (str_starts_with($property, 'padding') || in_array($property, ['gap','row-gap','column-gap'], true)) {
            if (!$this->isLengthList($value, false, false, 4)) throw new InvalidArgumentException('Espaciado no permitido.');
            $this->assertPxTokensInRange($property, $value, 0, 512);
            return $value;
        }
        if (in_array($property, ['top','right','bottom','left'], true)) {
            if (!$this->isLengthLike($value, true, true)) throw new InvalidArgumentException('Posición no permitida.');
            $this->assertPxTokensInRange($property, $value, -1000, 1000);
            return $value;
        }
        if (in_array($property, ['width','min-width','max-width','height','min-height','max-height'], true)) {
            if (!$this->isSizeValue($value)) throw new InvalidArgumentException('Tamaño no permitido para ' . $property . '.');
            return $value;
        }
        if ($property === 'line-height') {
            if (is_numeric($value)) {
                $n = (float)$value;
                if ($n < .6 || $n > 4) throw new InvalidArgumentException('Line-height fuera de rango.');
                return $value;
            }
            if (!$this->isLengthLike($value, false, false)) throw new InvalidArgumentException('Line-height no permitido.');
            return $value;
        }
        if ($property === 'object-position') {
            if (!preg_match('/^(?:left|right|top|bottom|center|[0-9]{1,3}(?:\.[0-9]+)?%)(?:\s+(?:left|right|top|bottom|center|[0-9]{1,3}(?:\.[0-9]+)?%))?$/i', $value)) {
                throw new InvalidArgumentException('Object-position no permitido.');
            }
            return $value;
        }
        if (in_array($property, ['grid-template-columns','grid-template-rows'], true)) {
            if (!$this->isSafeGridTemplate($value)) throw new InvalidArgumentException('Definición de grid no permitida.');
            return $value;
        }
        if (in_array($property, ['grid-column','grid-row'], true)) {
            if (!preg_match('/^(?:auto|span\s+[1-9]\d?|[1-9]\d?)(?:\s*\/\s*(?:auto|span\s+[1-9]\d?|[1-9]\d?))?$/', $value)) throw new InvalidArgumentException('Posición de grid no permitida.');
            return $value;
        }
        if ($property === 'transform') {
            if (!$this->isSafeTransform($value)) throw new InvalidArgumentException('Transform no permitido.');
            return $value;
        }
        if ($property === 'box-shadow') {
            if ($value === 'none') return $value;
            if (!$this->isSafeShadow($value)) throw new InvalidArgumentException('Sombra no permitida.');
            return $value;
        }

        throw new InvalidArgumentException('No se pudo validar el valor de ' . $property . '.');
    }

    private function enum(string $property, string $value, array $allowed): string
    {
        if (!in_array($value, $allowed, true)) throw new InvalidArgumentException('Valor no permitido para ' . $property . '.');
        return $value;
    }

    private function isAllowedColorToken(string $value): bool
    {
        return (bool)preg_match('/^var\(--site-(?:primary|secondary|accent|background|surface|text|muted)\)$/', $value)
            || $value === 'transparent';
    }

    private function isSafeTarget(string $target): bool
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $target)) return true;
        if (preg_match('/^(?:class|id):([A-Za-z_][A-Za-z0-9_-]{0,79})$/', $target, $m)) return $this->isCssIdentifier($m[1]);
        return false;
    }

    private function isCssIdentifier(string $value): bool
    {
        return (bool)preg_match('/^[A-Za-z_][A-Za-z0-9_-]{0,79}$/', $value);
    }

    private function isLengthLike(string $value, bool $allowNegative, bool $allowPercent): bool
    {
        if (in_array($value, ['0','auto'], true)) return true;
        // Tokens de diseño permitidos. Personalizar puede reutilizar la escala de Blumi,
        // pero no inventar variables arbitrarias ni escapar del Design System.
        if (preg_match('/^var\(--(?:space-(?:1|2|3|4|5|6|8|10|12|16|20|24)|font-(?:display-xl|display|h1|h2|h3|h4|body-lg|body|small|caption)|site-(?:gutter|section-space|container|container-normal|container-wide|radius))\)$/', $value)) return true;
        $sign = $allowNegative ? '-?' : '';
        $units = $allowPercent ? '(?:px|rem|em|vw|vh|%)' : '(?:px|rem|em|vw|vh)';
        return (bool)preg_match('/^' . $sign . '\d+(?:\.\d+)?' . $units . '$/', $value)
            || $this->isSafeMathExpression($value);
    }

    private function isLengthList(string $value, bool $allowNegative, bool $allowAuto, int $maxParts): bool
    {
        $parts = preg_split('/\s+/', trim($value)) ?: [];
        if ($parts === [] || count($parts) > $maxParts) return false;
        foreach ($parts as $part) {
            if ($allowAuto && $part === 'auto') continue;
            if (!$this->isLengthLike($part, $allowNegative, true)) return false;
        }
        return true;
    }

    private function isSizeValue(string $value): bool
    {
        if (in_array($value, ['auto','none','min-content','max-content','fit-content'], true)) return true;
        return $this->isLengthLike($value, false, true) || $this->isSafeGridFunction($value);
    }

    private function isSafeGridTemplate(string $value): bool
    {
        if (strlen($value) > 180 || preg_match('/[^A-Za-z0-9_.,()%\s+\-*\/]/', $value)) return false;
        $lower = strtolower($value);
        foreach (['repeat(', 'minmax(', 'fit-content(', 'calc(', 'clamp(', 'var('] as $function) {
            // supported below; merely prevents unknown function names from slipping through
        }
        if (preg_match_all('/([A-Za-z-]+)\(/', $lower, $m)) {
            foreach ($m[1] as $fn) {
                if (!in_array($fn, ['repeat','minmax','fit-content','calc','clamp','var'], true)) return false;
            }
        }
        if (str_contains($lower, 'var(') && !preg_match_all('/var\(--site-[a-z0-9-]+\)/', $lower)) return false;
        return !str_contains($lower, '--') || !preg_match('/var\(--(?!site-)/', $lower);
    }

    private function isSafeGridFunction(string $value): bool
    {
        return (bool)preg_match('/^(?:min|max|clamp|calc|fit-content)\([A-Za-z0-9_.,()%\s+\-*\/]+\)$/', $value);
    }

    private function isSafeMathExpression(string $value): bool
    {
        if (!preg_match('/^(?:calc|clamp|min|max)\([A-Za-z0-9_.,()%\s+\-*\/]+\)$/', $value)) return false;
        if (preg_match_all('/var\((--[^)]+)\)/', $value, $vars)) {
            foreach ($vars[1] as $var) if (!str_starts_with($var, '--site-') && !str_starts_with($var, '--font-') && !str_starts_with($var, '--space-')) return false;
        }
        return true;
    }

    private function isSafeTransform(string $value): bool
    {
        if (strlen($value) > 160) return false;
        if (!preg_match('/^(?:(?:translate(?:X|Y)?\(-?\d+(?:\.\d+)?(?:px|rem|em|%|vw|vh)(?:\s*,\s*-?\d+(?:\.\d+)?(?:px|rem|em|%|vw|vh))?\)|scale(?:X|Y)?\(\d+(?:\.\d+)?\)|rotate\(-?\d+(?:\.\d+)?deg\))\s*)+$/', $value)) return false;
        if (preg_match_all('/scale(?:X|Y)?\((\d+(?:\.\d+)?)\)/', $value, $m)) {
            foreach ($m[1] as $n) if ((float)$n < .25 || (float)$n > 3) return false;
        }
        if (preg_match_all('/translate(?:X|Y)?\((-?\d+(?:\.\d+)?)px/', $value, $m)) {
            foreach ($m[1] as $n) if (abs((float)$n) > 1000) return false;
        }
        return true;
    }

    private function isSafeShadow(string $value): bool
    {
        if (strlen($value) > 180) return false;
        if (!str_contains($value, 'var(--site-') && !str_contains($value, 'color-mix(')) return false;
        if (preg_match('/#[0-9a-f]{3,8}|rgba?\(|hsla?\(/i', $value)) return false;
        return (bool)preg_match('/^[A-Za-z0-9_.,()%\s+\-*\/()-]+$/', $value);
    }

    private function assertLengthRange(string $property, string $value, float $min, float $max, bool $allowNegative): void
    {
        if (!$this->isLengthLike($value, $allowNegative, true)) throw new InvalidArgumentException('Valor no permitido para ' . $property . '.');
        if (preg_match('/^(-?\d+(?:\.\d+)?)px$/', $value, $m)) {
            $n = (float)$m[1];
            if ($n < $min || $n > $max) throw new InvalidArgumentException($property . ' fuera de rango.');
        }
    }

    private function assertPxTokensInRange(string $property, string $value, float $min, float $max): void
    {
        if (preg_match_all('/(-?\d+(?:\.\d+)?)px/', $value, $matches)) {
            foreach ($matches[1] as $raw) {
                $n = (float)$raw;
                if ($n < $min || $n > $max) throw new InvalidArgumentException($property . ' fuera de rango.');
            }
        }
    }
}
