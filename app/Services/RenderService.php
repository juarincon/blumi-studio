<?php
namespace App\Services;

final class RenderService
{
    /**
     * Renderiza una instancia usando exclusivamente placeholders controlados.
     * $globals permite valores del proyecto (por ejemplo site_logo/site_name)
     * sin convertirlos en campos editables de cada componente.
     *
     * @param array<string,array{value:mixed,type?:string}> $globals
     */
    public function renderInstance(array $instance, array $globals = []): string
    {
        $schema = $this->decode($instance['schema_campos'] ?? '{}');
        $content = $this->decode($instance['contenido_json'] ?? '{}');
        $content = $this->resolveSmartLinks($content, $globals);
        $styles = $this->decode($instance['estilos_override_json'] ?? '{}');
        $hiddenFields = array_values(array_filter(
            is_array($styles['hidden_fields'] ?? null) ? $styles['hidden_fields'] : [],
            static fn($value): bool => is_string($value) && $value !== ''
        ));
        $hiddenLookup = array_fill_keys($hiddenFields, true);
        $markers = [];
        $template = (string)($instance['html_template'] ?? '');

        $rendered = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function (array $match) use ($schema, $content, $globals, $hiddenLookup, &$markers): string {
            $key = $match[1];

            if (isset($hiddenLookup[$key])) {
                $marker = '__BLUMI_HIDE_' . strtoupper(bin2hex($key)) . '__';
                $markers[$marker] = $key;
                return $marker;
            }

            if (array_key_exists($key, $schema)) {
                $definition = is_array($schema[$key]) ? $schema[$key] : [];
                $default = (string)($definition['default'] ?? '');
                $hasContent = array_key_exists($key, $content);
                $contentValue = $hasContent ? (string)$content[$key] : '';

                $useGlobal = array_key_exists($key, $globals)
                    && (!$hasContent || $contentValue === '' || $contentValue === $default);

                if ($useGlobal) {
                    $global = is_array($globals[$key]) ? $globals[$key] : ['value' => $globals[$key]];
                    $value = (string)($global['value'] ?? '');
                    $type = (string)($global['type'] ?? ($definition['type'] ?? 'text'));
                } else {
                    $value = $hasContent ? $contentValue : $default;
                    $type = (string)($definition['type'] ?? 'text');
                }
            } elseif (array_key_exists($key, $globals)) {
                $global = is_array($globals[$key]) ? $globals[$key] : ['value' => $globals[$key]];
                $value = (string)($global['value'] ?? '');
                $type = (string)($global['type'] ?? 'text');
            } else {
                return '';
            }

            if (in_array($type, ['url', 'image'], true)) {
                $value = $this->safeUrl($value);
            }

            return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $template) ?? '';

        return $markers ? $this->applyHiddenMarkers($rendered, $markers) : $rendered;
    }

    /**
     * Renderiza un componente maestro para miniaturas de biblioteca usando
     * exclusivamente sus defaults de schema y los globals del proyecto.
     */
    public function renderLibraryComponent(array $component, array $globals = []): string
    {
        $schema = $this->decode((string)($component['schema_campos'] ?? '{}'));
        $defaults = [];
        foreach ($schema as $key => $definition) {
            if (!is_string($key) || !is_array($definition)) {
                continue;
            }
            $defaults[$key] = isset($definition['default']) && is_scalar($definition['default'])
                ? (string)$definition['default']
                : '';
        }

        $component['contenido_json'] = json_encode(
            $defaults,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';

        return $this->renderInstance($component, $globals);
    }

    public function collectCss(array $instances): string
    {
        $seen = [];
        $parts = [];
        foreach ($instances as $instance) {
            $key = (int)($instance['componente_id'] ?? 0) . ':' . (int)($instance['version_componente'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $css = trim((string)($instance['css'] ?? ''));
            if ($css !== '') {
                $parts[] = $css;
            }
        }
        return implode("\n", $parts);
    }


    public function collectInstanceStyleCss(array $instances): string
    {
        $parts = [];
        foreach ($instances as $instance) {
            $css = $this->instanceStyleCss($instance);
            if ($css !== '') $parts[] = $css;
            // Personalizar con Blumi es la última capa de la cascada por instancia.
            // El mismo CSS alimenta Canvas, build, publicación y ZIP.
            $customizationCss = $this->customizationCss($instance);
            if ($customizationCss !== '') $parts[] = $customizationCss;
        }
        return implode("\n", $parts);
    }

    public function customizationCss(array $instance): string
    {
        return (new CustomizationCssCompiler())->compileInstance($instance);
    }

    public function instanceStyleCss(array $instance): string
    {
        $styles = $this->decode((string)($instance['estilos_override_json'] ?? '{}'));
        $id = (int)($instance['id'] ?? 0);
        if ($id <= 0) return '';

        $scope = '[data-blumi-instance="' . $id . '"]';
        $category = strtolower((string)($instance['categoria_slug'] ?? $instance['categoria_nombre'] ?? ''));
        $decl = [];
        $colorMap = [
            'primary'=>'var(--site-primary)','secondary'=>'var(--site-secondary)','accent'=>'var(--site-accent)',
            'background'=>'var(--site-background)','surface'=>'var(--site-surface)','text'=>'var(--site-text)',
            'muted'=>'var(--site-muted)','transparent'=>'transparent'
        ];
        $radiusMap = [
            'none'=>'0','soft'=>'var(--radius-sm)','medium'=>'var(--radius-md)',
            'large'=>'var(--radius-xl)','pill'=>'var(--radius-pill)'
        ];
        $shadowSizeMap = [
            'subtle' => ['offset'=>4, 'blur'=>14],
            'medium' => ['offset'=>8, 'blur'=>26],
            'large' => ['offset'=>14, 'blur'=>42],
        ];
        $shadowOpacityMap = [
            'soft' => 8,
            'medium' => 14,
            'strong' => 22,
            'solid' => 32,
        ];

        foreach (['section_background','surface_color','text_color','heading_color','link_color','active_link_color','button_background','button_text'] as $key) {
            $value = (string)($styles[$key] ?? '');
            if ($value !== '' && isset($colorMap[$value])) $decl[$key] = $colorMap[$value];
        }
        foreach (['section_radius','button_radius'] as $key) {
            $value = (string)($styles[$key] ?? '');
            if ($value !== '' && isset($radiusMap[$value])) $decl[$key] = $radiusMap[$value];
        }
        $shadow = (string)($styles['section_shadow'] ?? '');
        $shadowDirection = (string)($styles['shadow_direction'] ?? 'bottom');
        $shadowOpacity = (string)($styles['shadow_opacity'] ?? 'medium');
        if ($shadow === 'none') {
            $decl['section_shadow'] = 'none';
        } elseif (isset($shadowSizeMap[$shadow])) {
            $offset = (int)$shadowSizeMap[$shadow]['offset'];
            $blur = (int)$shadowSizeMap[$shadow]['blur'];
            $opacity = (int)($shadowOpacityMap[$shadowOpacity] ?? $shadowOpacityMap['medium']);
            $color = 'color-mix(in srgb, var(--site-text) ' . $opacity . '%, transparent)';
            $decl['section_shadow'] = match ($shadowDirection) {
                'top' => '0 -' . $offset . 'px ' . $blur . 'px ' . $color,
                'both' => '0 ' . $offset . 'px ' . $blur . 'px ' . $color . ',0 -' . $offset . 'px ' . $blur . 'px ' . $color,
                'around' => '0 0 ' . $blur . 'px ' . $color,
                default => '0 ' . $offset . 'px ' . $blur . 'px ' . $color,
            };
        }

        $out = [];

        // Ancho estructural de la instancia. Automático usa una regla segura por categoría.
        $layoutWidth = (string)($styles['layout_width'] ?? 'inherit');
        if ($layoutWidth === '' || $layoutWidth === 'inherit') {
            $layoutWidth = in_array($category, ['navbar','hero','carousel','carrousel','gallery','galeria','partners','aliados','process','proceso','footer'], true) ? 'wide' : 'normal';
        }
        if ($layoutWidth === 'wide') {
            $out[] = $scope . '{--site-container:var(--site-container-wide,1600px);}';
            $out[] = $scope . '>.bl-section-background>.bl-site-frame{max-width:calc(var(--site-container-wide,1600px) + (var(--site-gutter) * 2));}';
        } elseif ($layoutWidth === 'full') {
            $out[] = $scope . '{--site-container:100vw;--site-gutter:0px;}';
            $out[] = $scope . '>.bl-section-background>.bl-site-frame{max-width:none;padding-left:0;padding-right:0;}';
        } else {
            $out[] = $scope . '{--site-container:var(--site-container-normal,var(--site-container));}';
            $out[] = $scope . '>.bl-section-background>.bl-site-frame{max-width:calc(var(--site-container-normal,var(--site-container)) + (var(--site-gutter) * 2));}';
        }
        if (isset($decl['section_background'])) {
            $out[] = $scope . '>.bl-section-background{background:' . $decl['section_background'] . ' !important;}';
        }
        $backgroundMode = (string)($styles['background_mode'] ?? 'color');
        $backgroundImage = trim((string)($styles['background_image'] ?? ''));
        $surfaceSelector = $scope . '>.bl-section-background>.bl-site-frame>.bl-component-surface';
        // La imagen de fondo vive en una capa propia detrás del contenido.
        // Así nunca puede quedar por encima de títulos, botones o imágenes internas.
        $out[] = $surfaceSelector . '{position:relative;isolation:isolate;}';
        $out[] = $surfaceSelector . '>.bl-component-stage{position:relative;z-index:1;}';
        if ($backgroundMode === 'image' && $backgroundImage !== '') {
            $safeImage = $this->safeUrl($backgroundImage);
            if ($safeImage !== '#') {
                $out[] = $surfaceSelector . '::before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;border-radius:inherit;background-image:url("' . htmlspecialchars($safeImage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '");background-size:cover;background-position:center;background-repeat:no-repeat;}';
                $out[] = $scope . ' .bl-component-stage>*{background:transparent !important;}';
            }
        } else {
            $out[] = $surfaceSelector . '::before{content:none !important;}';
        }
        if (isset($decl['surface_color'])) {
            $out[] = $scope . '>.bl-section-background>.bl-site-frame>.bl-component-surface{background-color:' . $decl['surface_color'] . ' !important;}';
            $out[] = $scope . ' .bl-component-stage>*{background-color:transparent !important;}';
        }
        if (isset($decl['text_color'])) {
            // No sobrescribir --site-text dentro de una instancia. Algunos componentes
            // reutilizan ese token como base estructural (fondos, overlays o bordes),
            // por lo que cambiar "Texto general" podía recolorear todo el módulo.
            // El override de texto se aplica únicamente a contenido tipográfico común.
            $textSelector =
                $scope . ' .bl-component-stage p,' .
                $scope . ' .bl-component-stage li,' .
                $scope . ' .bl-component-stage [class*="description"],' .
                $scope . ' .bl-component-stage [class*="subtitle"],' .
                $scope . ' .bl-component-stage [class*="eyebrow"],' .
                $scope . ' .bl-component-stage [class*="kicker"],' .
                $scope . ' .bl-component-stage [class*="copy"]';
            $out[] = $textSelector . '{color:' . $decl['text_color'] . ' !important;}';
        }
        if (isset($decl['heading_color'])) {
            $out[] = $scope . ' .bl-component-stage h1,' . $scope . ' .bl-component-stage h2,' . $scope . ' .bl-component-stage h3,' . $scope . ' .bl-component-stage h4,' . $scope . ' .bl-component-stage h5,' . $scope . ' .bl-component-stage h6{color:' . $decl['heading_color'] . ' !important;}';
        }
        if (isset($decl['link_color'])) {
            $out[] = $scope . ' .bl-component-stage a{color:' . $decl['link_color'] . ' !important;}';
        }
        if (isset($decl['active_link_color'])) {
            $out[] = $scope . ' .bl-component-stage a.is-active,' . $scope . ' .bl-component-stage a[aria-current="page"]{color:' . $decl['active_link_color'] . ' !important;}';
        }
        $buttonSelector = $scope . ' .bl-component-stage a[class*="cta"],' . $scope . ' .bl-component-stage a[class*="button"],' . $scope . ' .bl-component-stage button,' . $scope . ' .bl-component-stage input[type="submit"]';
        if (isset($decl['button_background'])) $out[] = $buttonSelector . '{background:' . $decl['button_background'] . ' !important;}';
        if (isset($decl['button_text'])) $out[] = $buttonSelector . '{color:' . $decl['button_text'] . ' !important;}';
        if (isset($decl['button_radius'])) $out[] = $buttonSelector . '{border-radius:' . $decl['button_radius'] . ' !important;}';
        if (isset($decl['section_radius'])) {
            $out[] = $scope . '>.bl-section-background>.bl-site-frame>.bl-component-surface{border-radius:' . $decl['section_radius'] . ';overflow:visible;}';
        }
        if ($category === 'navbar') {
            $navbarPosition = (string)($styles['navbar_position'] ?? 'static');

            // La posicion del navbar la controla Blumi desde el wrapper de instancia.
            // Neutralizamos cualquier fixed/sticky que haya venido en el CSS generado
            // para que "Estatico" realmente vuelva al flujo normal y "Fijo arriba"
            // no termine con dos capas de posicionamiento compitiendo entre si.
            $navbarRoot = $scope . ' .bl-component-stage>*';
            $out[] = $navbarRoot . '{position:relative !important;top:auto !important;right:auto !important;bottom:auto !important;left:auto !important;inset:auto !important;}';

            if ($navbarPosition === 'fixed') {
                // Sticky mantiene el navbar visible sin sacarlo del flujo del documento.
                $out[] = $scope . '{position:sticky !important;top:0 !important;right:auto !important;bottom:auto !important;left:auto !important;z-index:1000;}';
                $out[] = $scope . '>.bl-section-background{position:relative !important;top:auto !important;right:auto !important;bottom:auto !important;left:auto !important;}';
            } else {
                // Estatico = flujo normal. No conservar position:relative/sticky/fixed previo.
                $out[] = $scope . '{position:static !important;top:auto !important;right:auto !important;bottom:auto !important;left:auto !important;z-index:auto;}';
                $out[] = $scope . '>.bl-section-background{position:relative !important;top:auto !important;right:auto !important;bottom:auto !important;left:auto !important;}';
            }
        }

        if (isset($decl['section_shadow'])) {
            if ($category === 'navbar') {
                // Navbar: la sombra pertenece a la franja completa, no al contenedor interior.
                if (($styles['navbar_position'] ?? 'static') === 'fixed') {
                    $out[] = $scope . '{z-index:1000;}';
                    $out[] = $scope . '>.bl-section-background{box-shadow:' . $decl['section_shadow'] . ';}';
                } else {
                    // La sombra no debe volver a posicionar un navbar marcado como estatico.
                    $out[] = $scope . '>.bl-section-background{box-shadow:' . $decl['section_shadow'] . ';}';
                }
                $out[] = $scope . '>.bl-section-background>.bl-site-frame>.bl-component-surface{box-shadow:none !important;}';
            } else {
                $out[] = $scope . '>.bl-section-background>.bl-site-frame>.bl-component-surface{box-shadow:' . $decl['section_shadow'] . ';}';
            }
        }
        return implode("\n", $out);
    }

    public function collectJs(array $instances): string
    {
        $seen = [];
        $parts = [];
        foreach ($instances as $instance) {
            $key = (int)($instance['componente_id'] ?? 0) . ':' . (int)($instance['version_componente'] ?? 0);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $js = trim((string)($instance['js'] ?? ''));
            if ($js !== '') {
                $parts[] = "(() => {\n" . $js . "\n})();";
            }
        }
        return implode("\n", $parts);
    }

    /** @param array<string,string> $markers */
    private function applyHiddenMarkers(string $html, array $markers): string
    {
        foreach ($markers as $marker => $fieldKey) {
            $escaped = preg_quote($marker, '#');
            $hidden = false;

            // Elementos visuales cuyo atributo contiene directamente el placeholder (img/src, etc.).
            $html = preg_replace_callback(
                '#<(img|input|source|video)\b([^>]*' . $escaped . '[^>]*)>#i',
                function (array $m) use ($marker, $fieldKey, &$hidden): string {
                    $hidden = true;
                    $attrs = str_replace($marker, '', $m[2]);
                    return '<' . $m[1] . $this->hiddenAttributes($attrs, $fieldKey) . '>';
                },
                $html,
                1
            ) ?? $html;

            if (!$hidden) {
                // Prioridad semántica: enlaces/botones completos, títulos, párrafos, items, etc.
                $tags = ['a','button','h1','h2','h3','h4','h5','h6','p','li','label','figure','picture','article','div','section','span'];
                foreach ($tags as $tag) {
                    $pattern = '#<' . $tag . '\b([^>]*)>((?:(?!</' . $tag . '>).)*' . $escaped . '(?:(?!</' . $tag . '>).)*)</' . $tag . '>#is';
                    $replaced = 0;
                    $html = preg_replace_callback(
                        $pattern,
                        function (array $m) use ($tag, $marker, $fieldKey): string {
                            $inner = str_replace($marker, '', $m[2]);
                            return '<' . $tag . $this->hiddenAttributes($m[1], $fieldKey) . '>' . $inner . '</' . $tag . '>';
                        },
                        $html,
                        1,
                        $replaced
                    ) ?? $html;
                    if ($replaced > 0) {
                        $hidden = true;
                        break;
                    }
                }
            }

            // Último recurso: nunca dejar el marcador visible.
            $html = str_replace($marker, '', $html);
        }
        return $html;
    }

    private function hiddenAttributes(string $attrs, string $fieldKey): string
    {
        $attrs = rtrim($attrs);
        $safeField = htmlspecialchars($fieldKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if (preg_match('/\sstyle=("|\')(.*?)\1/is', $attrs, $match)) {
            $quote = $match[1];
            $style = rtrim(trim($match[2]), ';') . ';display:none!important;';
            $attrs = preg_replace('/\sstyle=("|\')(.*?)\1/is', ' style=' . $quote . $style . $quote, $attrs, 1) ?? $attrs;
        } else {
            $attrs .= ' style="display:none!important;"';
        }
        $attrs .= ' data-blumi-hidden-field="' . $safeField . '"';
        return $attrs;
    }



    private function resolveSmartLinks(array $content, array $globals): array
    {
        $links = $content['__blumi_links'] ?? null;
        if (!is_array($links)) return $content;

        foreach ($links as $fieldKey => $meta) {
            if (!is_string($fieldKey) || !is_array($meta)) continue;
            $content[$fieldKey] = $this->resolveSmartLink($meta, $globals);
        }
        return $content;
    }

    private function resolveSmartLink(array $meta, array $globals): string
    {
        $type = strtolower((string)($meta['type'] ?? 'none'));
        if ($type === 'none' || $type === '') return '#';

        if ($type === 'external') {
            return $this->safeUrl((string)($meta['value'] ?? '#'));
        }
        if ($type === 'email') {
            $email = trim((string)($meta['value'] ?? ''));
            return $email !== '' ? 'mailto:' . preg_replace('/^mailto:/i', '', $email) : '#';
        }
        if ($type === 'phone') {
            $phone = trim((string)($meta['value'] ?? ''));
            return $phone !== '' ? 'tel:' . preg_replace('/^tel:/i', '', $phone) : '#';
        }

        $sectionId = (int)($meta['section_id'] ?? 0);
        if ($type === 'section') {
            return $sectionId > 0 ? '#blumi-section-' . $sectionId : '#';
        }

        $targetPageId = (int)($meta['page_id'] ?? 0);
        $pages = is_array($globals['project_pages'] ?? null) ? $globals['project_pages'] : [];
        $target = null;
        foreach ($pages as $candidate) {
            if (is_array($candidate) && (int)($candidate['id'] ?? 0) === $targetPageId) {
                $target = $candidate;
                break;
            }
        }
        if (!$target) return '#';

        $mode = (string)($globals['render_mode'] ?? 'build');
        if ($mode === 'builder') {
            $url = 'index.php?route=builder&page_id=' . $targetPageId;
            if ($type === 'page_section' && $sectionId > 0) $url .= '#blumi-section-' . $sectionId;
            return $url;
        }

        $currentPageId = (int)($globals['current_page_id'] ?? 0);
        $currentIsHome = !empty($globals['current_page_is_home']);
        $targetIsHome = !empty($target['es_inicio']);
        $targetSlug = trim((string)($target['slug'] ?? ''));

        if ($targetPageId === $currentPageId) {
            $url = './';
        } elseif ($currentIsHome) {
            $url = $targetIsHome ? './' : ($targetSlug !== '' ? $targetSlug . '/' : './');
        } else {
            $url = $targetIsHome ? '../' : '../' . ($targetSlug !== '' ? $targetSlug . '/' : '');
        }

        if ($type === 'page_section' && $sectionId > 0) $url .= '#blumi-section-' . $sectionId;
        return $url;
    }

    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }
        if ($url[0] === '#' || $url[0] === '/') {
            return $url;
        }
        if (preg_match('#^index\.php\?route=builder&page_id=\d+(?:\#blumi-section-\d+)?$#', $url)) {
            return $url;
        }
        // Rutas internas relativas generadas por Smart Links (./, ../, pagina/, pagina/#seccion).
        if (preg_match('#^(?:\.\.?/)?(?:[a-zA-Z0-9_-]+/)*(?:[a-zA-Z0-9_-]+/?)?(?:\#[a-zA-Z0-9_-]+)?$#', $url)) {
            return $url;
        }
        // Assets locales generados por Blumi: uploads/... o assets de marca controlados.
        if (preg_match('#^uploads/[a-zA-Z0-9_./-]+$#', $url) || preg_match('#^assets/img/blumi[a-zA-Z0-9_./-]*$#', $url)) {
            return $url;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return $url;
        }
        return '#';
    }
}
