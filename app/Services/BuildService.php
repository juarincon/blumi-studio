<?php
namespace App\Services;

use App\Repositories\ComponentRepository;
use App\Repositories\PageRepository;
use RuntimeException;
use ZipArchive;

final class BuildService
{
    public function __construct(
        private readonly PageRepository $pages = new PageRepository(),
        private readonly ComponentRepository $components = new ComponentRepository(),
        private readonly ComponentService $componentService = new ComponentService(),
        private readonly DesignService $designs = new DesignService(),
        private readonly RenderService $renderer = new RenderService()
    ) {}

    /**
     * Compatibilidad: desde v1 el build siempre corresponde al sitio completo.
     * @return array{directory:string,index:string,zip:string,public_url_path:string,pages_count:int,warnings:array}
     */
    public function generatePage(int $pageId): array
    {
        return $this->generateSite($pageId);
    }

    /**
     * Genera un sitio multipagina con assets compartidos.
     * Home vive en /index.html y las demas paginas en /slug/index.html.
     *
     * @return array{directory:string,index:string,zip:string,public_url_path:string,pages_count:int,warnings:array}
     */
    public function generateSite(int $currentPageId): array
    {
        $currentPage = $this->pages->find($currentPageId);
        if (!$currentPage) {
            throw new RuntimeException('La pagina no existe.');
        }

        $projectId = (int)$currentPage['proyecto_id'];
        $this->designs->assertReadyForExport($projectId);

        $allProjectPages = $this->pages->forProject($projectId);
        $projectPages = array_values(array_filter($allProjectPages, static function (array $page): bool {
            return (int)($page['visible'] ?? 1) === 1 && strtolower((string)($page['estado'] ?? 'borrador')) !== 'oculto';
        }));
        if (!$projectPages) {
            throw new RuntimeException('El proyecto no tiene paginas disponibles para publicar.');
        }

        $homePages = array_values(array_filter($projectPages, static fn(array $page): bool => (int)($page['es_inicio'] ?? 0) === 1));
        if (count($homePages) !== 1) {
            throw new RuntimeException('El proyecto debe tener exactamente una pagina de inicio antes de generar el sitio.');
        }

        $design = $this->designs->get($projectId);
        $fonts = $this->designs->fonts($projectId);

        $instancesByPage = [];
        $uniqueInstances = [];
        foreach ($projectPages as $page) {
            $pid = (int)$page['id'];
            $instances = $this->componentService->instancesForPage($pid);
            $instancesByPage[$pid] = $instances;
            foreach ($instances as $instance) {
                $iid = (int)($instance['id'] ?? 0);
                if ($iid > 0) $uniqueInstances[$iid] = $instance;
            }
        }

        $preflight = $this->preflight($projectPages, $instancesByPage);
        if ($preflight['errors']) {
            throw new RuntimeException("No se puede generar el sitio:\n- " . implode("\n- ", $preflight['errors']));
        }

        $componentCss = $this->renderer->collectCss(array_values($uniqueInstances));
        $instanceStyleCss = $this->renderer->collectInstanceStyleCss(array_values($uniqueInstances));
        $componentJs = $this->renderer->collectJs(array_values($uniqueInstances));
        $fontCss = $this->designs->fontFaceCss($fonts);
        $googleFonts = $this->designs->googleFontUrls($design);
        // Conservamos una copia del CSS con rutas de assets relativas a public/.
        // La version reescrita usa ../../ para ejecutarse desde assets/css/site.css,
        // pero esa ruta reescrita no debe usarse para descubrir que archivos copiar.
        $siteCssSource = $this->siteCss($design, $fontCss) . "\n" . $componentCss . "\n" . $instanceStyleCss;
        $siteCss = $this->rewriteCssAssetUrlsForBuild($siteCssSource);
        $siteJs = $this->siteJs() . "\n" . $componentJs;

        $projectSlug = $this->safeSlug((string)$currentPage['proyecto_slug']);
        $buildKey = $projectSlug . '-site';
        $root = dirname(__DIR__, 2);
        $buildRoot = $root . '/storage/builds';
        $this->makeDir($buildRoot);

        // v1.1.2: cada proyecto conserva un unico build descargable actual.
        // Se construye primero en una ruta temporal; el build valido anterior no se toca
        // hasta que el nuevo directorio y ZIP hayan terminado correctamente.
        $nonce = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $buildDir = $buildRoot . '/' . $buildKey;
        $tempBuildDir = $buildRoot . '/.' . $buildKey . '.tmp-' . $nonce;
        $tempZipPath = $buildRoot . '/.' . $buildKey . '.tmp-' . $nonce . '.zip';
        $zipPath = $buildRoot . '/' . $buildKey . '.zip';

        if (is_dir($tempBuildDir)) $this->removeDir($tempBuildDir);
        if (is_file($tempZipPath)) @unlink($tempZipPath);
        $this->makeDir($tempBuildDir . '/assets/css');
        $this->makeDir($tempBuildDir . '/assets/js');
        $workingBuildDir = $tempBuildDir;
        file_put_contents($workingBuildDir . '/assets/css/site.css', $siteCss);
        file_put_contents($workingBuildDir . '/assets/js/site.js', $siteJs);

        // Descubrir assets sobre el CSS original. Si usamos el CSS ya reescrito,
        // las imagenes de fondo quedan como ../../uploads/... y el recolector historico
        // no las detecta, por lo que funcionan en Canvas pero desaparecen al publicar.
        $assetDiscoveryContent = $siteCssSource . "\n" . $siteJs;
        $currentPublicPath = '';
        $homeIndexPath = $buildDir . '/index.html';

        foreach ($projectPages as $page) {
            $pageId = (int)$page['id'];
            $isHome = (int)($page['es_inicio'] ?? 0) === 1;
            $pageSlug = $isHome ? '' : $this->safeSlug((string)$page['slug']);
            $assetPrefix = $isHome ? '' : '../';
            $pageDir = $isHome ? $workingBuildDir : $workingBuildDir . '/' . $pageSlug;
            $this->makeDir($pageDir);

            $globals = [
                'site_logo' => ['value' => (string)($design['logo_ruta'] ?? ''), 'type' => 'image'],
                'site_name' => ['value' => (string)($design['nombre_comercial'] ?: $page['proyecto_nombre']), 'type' => 'text'],
                'site_home_url' => ['value' => $isHome ? './' : '../', 'type' => 'url'],
                'render_mode' => 'build',
                'current_page_id' => $pageId,
                'current_page_slug' => (string)$page['slug'],
                'current_page_is_home' => $isHome,
                'project_pages' => $projectPages,
            ];

            $body = '';
            foreach ($instancesByPage[$pageId] ?? [] as $instance) {
                $instanceId = (int)($instance['id'] ?? 0);
                $categorySlug = htmlspecialchars((string)($instance['categoria_slug'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $body .= "\n<section id=\"blumi-section-{$instanceId}\" class=\"bl-page-section\" data-blumi-instance=\"{$instanceId}\" data-blumi-category=\"{$categorySlug}\">\n";
                $body .= "<div class=\"bl-section-background\">\n";
                $body .= "<div class=\"bl-site-frame\"><div class=\"bl-component-surface\"><div class=\"bl-component-stage\">\n";
                $body .= $this->renderer->renderInstance($instance, $globals);
                $body .= "\n</div></div></div>\n";
                $body .= "</div>\n";
                $body .= "</section>\n";
            }

            // Los assets dentro del HTML del componente se expresan desde la raiz del sitio.
            // Para paginas anidadas agregamos ../ sin alterar links inteligentes entre paginas.
            $assetDiscoveryContent .= "\n" . $body;
            $bodyForPage = $this->rewriteHtmlAssetUrlsForPage($body, $assetPrefix);
            $html = $this->document(
                title: (string)$page['nombre'],
                body: $bodyForPage,
                googleFonts: $googleFonts,
                assetPrefix: $assetPrefix
            );
            file_put_contents($pageDir . '/index.html', $html);

            if ($pageId === $currentPageId) {
                $currentPublicPath = 'sites/' . $projectSlug . '/' . ($isHome ? '' : $pageSlug . '/');
            }
        }

        $this->copyReferencedAssets($assetDiscoveryContent, $workingBuildDir);

        try {
            $this->zipDirectory($workingBuildDir, $tempZipPath);
            if (!is_file($tempZipPath) || filesize($tempZipPath) <= 0) {
                throw new RuntimeException('El ZIP temporal del sitio no se genero correctamente.');
            }

            // Solo despues de tener un build completo reemplazamos el build anterior.
            $this->replaceDirectory($workingBuildDir, $buildDir);
            $this->replaceFile($tempZipPath, $zipPath);

            // Limpia automaticamente exports historicos de este mismo proyecto.
            $this->cleanupHistoricalBuildsForProject($buildRoot, $projectSlug, [$buildDir, $zipPath]);
        } catch (\Throwable $e) {
            if (is_dir($workingBuildDir)) $this->removeDir($workingBuildDir);
            if (is_file($tempZipPath)) @unlink($tempZipPath);
            throw $e;
        }

        if ($currentPublicPath === '') {
            $currentPublicPath = 'sites/' . $projectSlug . '/';
        }

        return [
            'directory' => $buildDir,
            'index' => $homeIndexPath,
            'zip' => $zipPath,
            'public_url_path' => $currentPublicPath,
            'pages_count' => count($projectPages),
            'warnings' => $preflight['warnings'],
        ];
    }

    /** Compatibilidad: publicar pagina ahora publica todo el proyecto. */
    public function publishPage(int $pageId): array
    {
        return $this->publishSite($pageId);
    }

    /** Publica el sitio completo dentro de public/sites/<proyecto>/ y devuelve la URL de la pagina actual. */
    public function publishSite(int $currentPageId): array
    {
        $build = $this->generateSite($currentPageId);
        $page = $this->pages->find($currentPageId);
        if (!$page) throw new RuntimeException('La pagina no existe.');

        $root = dirname(__DIR__, 2);
        $projectSlug = $this->safeSlug((string)$page['proyecto_slug']);
        $target = $root . '/public/sites/' . $projectSlug;
        if (is_dir($target)) $this->removeDir($target);
        $this->copyDir($build['directory'], $target);
        $build['published_directory'] = $target;
        return $build;
    }

    /**
     * @param array<int,array> $pages
     * @param array<int,array<int,array>> $instancesByPage
     * @return array{errors:array<int,string>,warnings:array<int,string>}
     */
    private function preflight(array $pages, array $instancesByPage): array
    {
        $errors = [];
        $warnings = [];
        $pageMap = [];
        $sectionMap = [];
        $slugs = [];

        foreach ($pages as $page) {
            $pid = (int)($page['id'] ?? 0);
            if ($pid <= 0) continue;
            $pageMap[$pid] = $page;
            $slug = strtolower(trim((string)($page['slug'] ?? '')));
            if ($slug === '') $errors[] = 'Una pagina no tiene ruta valida.';
            if ($slug !== '' && isset($slugs[$slug])) $errors[] = 'Hay dos paginas con la ruta /' . $slug . '/.';
            $slugs[$slug] = true;

            $bodyCount = 0;
            foreach ($instancesByPage[$pid] ?? [] as $instance) {
                $iid = (int)($instance['id'] ?? 0);
                if ($iid > 0) $sectionMap[$pid][$iid] = true;
                if (empty($instance['is_global_layout'])) $bodyCount++;
            }
            if ($bodyCount === 0) {
                $warnings[] = 'La pagina "' . (string)($page['nombre'] ?? 'Sin nombre') . '" no tiene secciones propias.';
            }
        }

        $checkedInstances = [];
        foreach ($instancesByPage as $currentPageId => $instances) {
            foreach ($instances as $instance) {
                $iid = (int)($instance['id'] ?? 0);
                // Globales pueden aparecer en todas las paginas: validar una sola vez por pagina actual,
                // porque los enlaces tipo section dependen del contexto.
                $key = $currentPageId . ':' . $iid;
                if (isset($checkedInstances[$key])) continue;
                $checkedInstances[$key] = true;

                $content = json_decode((string)($instance['contenido_json'] ?? '{}'), true);
                if (!is_array($content) || !is_array($content['__blumi_links'] ?? null)) continue;
                foreach ($content['__blumi_links'] as $fieldKey => $meta) {
                    if (!is_array($meta)) continue;
                    $type = strtolower((string)($meta['type'] ?? 'none'));
                    $source = (string)(($instance['nombre_interno'] ?? '') ?: ($instance['componente_nombre'] ?? 'Componente'));
                    if ($type === 'section') {
                        $sectionId = (int)($meta['section_id'] ?? 0);
                        if ($sectionId <= 0 || empty($sectionMap[(int)$currentPageId][$sectionId])) {
                            $errors[] = $source . ': el enlace "' . (string)$fieldKey . '" apunta a una seccion que ya no existe en esta pagina.';
                        }
                    } elseif ($type === 'page' || $type === 'page_section') {
                        $targetPageId = (int)($meta['page_id'] ?? 0);
                        if ($targetPageId <= 0 || !isset($pageMap[$targetPageId])) {
                            $errors[] = $source . ': el enlace "' . (string)$fieldKey . '" apunta a una pagina no disponible.';
                            continue;
                        }
                        if ($type === 'page_section') {
                            $sectionId = (int)($meta['section_id'] ?? 0);
                            if ($sectionId <= 0 || empty($sectionMap[$targetPageId][$sectionId])) {
                                $errors[] = $source . ': el enlace "' . (string)$fieldKey . '" apunta a una seccion que ya no existe.';
                            }
                        }
                    }
                }
            }
        }

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    private function siteCss(array $d, string $fontCss): string
    {
        $escFontHeading = str_replace(["'", '"'], '', (string)($d['heading_font'] ?? 'Inter'));
        $escFontBody = str_replace(["'", '"'], '', (string)($d['body_font'] ?? 'Inter'));
        $css = $fontCss . "\n";
        $css .= ':root{';
        $css .= '--site-primary:' . ($d['primary_color'] ?? '#2043BF') . ';';
        $css .= '--site-secondary:' . ($d['secondary_color'] ?? '#3974DC') . ';';
        $css .= '--site-accent:' . ($d['accent_color'] ?? '#E7DF68') . ';';
        $css .= '--site-background:' . ($d['background_color'] ?? '#FFFFFF') . ';';
        $css .= '--site-surface:' . ($d['surface_color'] ?? '#FFFFFF') . ';';
        $css .= '--site-text:' . ($d['text_color'] ?? '#1D1D1F') . ';';
        $css .= '--site-muted:' . ($d['muted_color'] ?? '#71717A') . ';';
        $css .= '--site-radius:' . ($d['border_radius'] ?? '12px') . ';';
        $css .= '--site-container-normal:' . ($d['container_width'] ?? '1280px') . ';';
        $css .= '--site-container-wide:1600px;';
        $css .= '--site-container:var(--site-container-normal);';
        $css .= '--site-section-space-base:' . ($d['section_spacing'] ?? '96px') . ';';
        $css .= '--site-section-space:var(--site-section-space-base);';
        $css .= '--site-gutter:32px;';
        $css .= "--site-font-heading:'{$escFontHeading}',sans-serif;--site-font-body:'{$escFontBody}',sans-serif;";
        $css .= '--font-display-xl:clamp(72px,4.3vw,108px);--font-display:clamp(60px,3.55vw,88px);--font-h1:clamp(48px,2.9vw,72px);--font-h2:clamp(40px,2.4vw,60px);--font-h3:clamp(32px,1.9vw,48px);--font-h4:clamp(24px,1.45vw,34px);--font-body-lg:clamp(18px,.95vw,20px);--font-body:clamp(16px,.85vw,18px);--font-small:clamp(14px,.74vw,15px);--font-caption:12px;';
        $css .= '--space-1:4px;--space-2:8px;--space-3:12px;--space-4:16px;--space-5:20px;--space-6:24px;--space-8:clamp(32px,2vw,44px);--space-10:clamp(40px,2.4vw,52px);--space-12:clamp(48px,2.8vw,60px);--space-16:clamp(64px,3.6vw,80px);--space-20:clamp(80px,4.4vw,96px);--space-24:clamp(96px,5.2vw,120px);';
        $css .= '--radius-sm:8px;--radius-md:12px;--radius-lg:16px;--radius-xl:20px;--radius-pill:999px;--container-width:var(--site-container);';
        $css .= '}';
        $css .= '*{box-sizing:border-box}html{scroll-behavior:smooth;width:100%}body{margin:0;width:100%;min-width:0;overflow-x:hidden;background:var(--site-background);color:var(--site-text);font-family:var(--site-font-body);font-size:var(--font-body);line-height:1.5}';
        $css .= '.bl-page-section{width:100%;max-width:none;margin:0;padding:0;min-width:0}.bl-section-background{width:100%;max-width:none;margin:0;background:var(--site-background);min-width:0}.bl-site-frame{width:100%;max-width:calc(var(--site-container) + (var(--site-gutter) * 2));margin:0 auto;padding-left:var(--site-gutter);padding-right:var(--site-gutter);min-width:0}.bl-component-surface{width:100%;max-width:none;margin:0;background:transparent;min-width:0}.bl-page-section[data-blumi-category="navbar"] .bl-component-surface,.bl-page-section[data-blumi-category="footer"] .bl-component-surface{background:var(--site-surface)}.bl-component-stage{width:100%;min-width:0}.bl-component-stage>*{width:100%!important;max-width:none!important;margin:0!important;min-width:0}.bl-component-stage>*>*{min-width:0}';
        $css .= 'img,svg,video{max-width:100%;height:auto}button,input,textarea,select{font:inherit}h1,h2,h3,h4,h5,h6{font-family:var(--site-font-heading);margin-top:0}';
        $css .= '@media(min-width:1600px){:root{--site-gutter:48px;--site-section-space:calc(var(--site-section-space-base) + 8px)}}';
        $css .= '@media(min-width:1900px){:root{--site-container-wide:1760px;--site-gutter:56px;--site-section-space:calc(var(--site-section-space-base) + 16px)}}';
        $css .= '@media(min-width:2400px){:root{--site-container-wide:2080px;--site-gutter:64px;--site-section-space:calc(var(--site-section-space-base) + 32px)}}';
        $css .= '@media(max-width:1199px){:root{--site-gutter:24px}}';
        $css .= '@media(max-width:900px){:root{--font-display-xl:60px;--font-display:52px;--font-h1:42px;--font-h2:36px;--font-h3:28px;--font-h4:22px}}';
        $css .= '@media(max-width:767px){:root{--site-gutter:14px}}';
        $css .= '@media(max-width:600px){:root{--font-display-xl:44px;--font-display:40px;--font-h1:36px;--font-h2:32px;--font-h3:26px;--font-h4:20px;--font-body-lg:17px}}';
        return $css;
    }

    private function siteJs(): string
    {
        return <<<'JS'
// Formularios MVP: front-end unicamente. No envian informacion a ningun servidor.
document.addEventListener('submit', function (event) {
  var form = event.target;
  if (form && form.matches('form[data-blumi-form="frontend"]')) {
    event.preventDefault();
  }
});
JS;
    }

    private function document(string $title, string $body, array $googleFonts, string $assetPrefix = ''): string
    {
        $links = '';
        foreach ($googleFonts as $url) {
            $links .= '<link rel="stylesheet" href="' . htmlspecialchars((string)$url, ENT_QUOTES) . '">' . "\n";
        }
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $assetPrefix = $assetPrefix === '../' ? '../' : '';
        return "<!doctype html>\n" .
            "<html lang=\"es\">\n" .
            "<head>\n" .
            "<meta charset=\"utf-8\">\n" .
            "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n" .
            '<title>' . $safeTitle . "</title>\n" .
            $links .
            '<link rel="stylesheet" href="' . $assetPrefix . "assets/css/site.css\">\n" .
            "</head>\n" .
            "<body>\n" .
            $body . "\n" .
            '<script src="' . $assetPrefix . "assets/js/site.js\"></script>\n" .
            "</body>\n" .
            "</html>\n";
    }

    private function rewriteHtmlAssetUrlsForPage(string $html, string $prefix): string
    {
        if ($prefix === '') return $html;
        $html = preg_replace_callback(
            '#(?P<attr>\b(?:src|href|poster|data-src)=)(?P<q>["\'])(?P<path>(?:uploads|assets)/(?!css/site\.css|js/site\.js)[^"\']+)(?P=q)#i',
            static function (array $m) use ($prefix): string {
                return (string)$m['attr'] . (string)$m['q'] . $prefix . ltrim((string)$m['path'], '/') . (string)$m['q'];
            },
            $html
        ) ?? $html;
        return preg_replace_callback(
            '#url\((?P<q>["\']?)(?P<path>(?:uploads|assets)/(?!css/site\.css|js/site\.js)[^)"\']+)(?P=q)\)#i',
            static function (array $m) use ($prefix): string {
                $q = (string)($m['q'] ?? '');
                return 'url(' . $q . $prefix . ltrim((string)$m['path'], '/') . $q . ')';
            },
            $html
        ) ?? $html;
    }

    private function rewriteCssAssetUrlsForBuild(string $css): string
    {
        return preg_replace_callback(
            "#url\\(([\'\"]?)(?P<path>(?:uploads|assets)/(?!css/site\\.css|js/site\\.js)[a-zA-Z0-9_./ -]+)\\1\\)#i",
            static function (array $match): string {
                $path = str_replace('\\', '/', (string)($match['path'] ?? ''));
                return 'url("../../' . ltrim($path, '/') . '")';
            },
            $css
        ) ?? $css;
    }

    private function copyReferencedAssets(string $content, string $buildDir): void
    {
        preg_match_all('#(?:(?:src|href|poster|data-src)=["\']|url\(["\']?)(?P<path>(?:(?:\.\./)+)?(?:uploads|assets)/(?!css/site\.css|js/site\.js)[a-zA-Z0-9_./ -]+\.(?:svg|webp|png|jpe?g|gif|woff2?|ttf|otf))#i', $content, $matches);
        $paths = array_map(
            static fn(string $path): string => preg_replace('#^(?:\.\./)+#', '', str_replace('\\', '/', $path)) ?? $path,
            $matches['path'] ?? []
        );
        $paths = array_values(array_unique($paths));
        $publicRoot = dirname(__DIR__, 2) . '/public/';
        foreach ($paths as $relative) {
            $relative = str_replace('..', '', trim($relative));
            $source = $publicRoot . $relative;
            if (!is_file($source)) continue;
            $target = $buildDir . '/' . $relative;
            $this->makeDir(dirname($target));
            copy($source, $target);
        }
    }

    private function zipDirectory(string $source, string $zipPath): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive no esta habilitado. Activalo para descargar builds ZIP.');
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el ZIP del sitio.');
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $full = $file->getPathname();
            $local = substr($full, strlen($source) + 1);
            $local = str_replace('\\', '/', $local);
            $zip->addFile($full, $local);
        }
        $zip->close();
    }

    /**
     * Reemplaza un directorio solo despues de que el nuevo build ya fue generado.
     * Se usa un backup corto para poder restaurar si rename() falla.
     */
    private function replaceDirectory(string $source, string $target): void
    {
        $backup = $target . '.bak';
        if (is_dir($backup)) $this->removeDir($backup);

        if (is_dir($target) && !@rename($target, $backup)) {
            throw new RuntimeException('No se pudo preparar el reemplazo del build anterior.');
        }

        if (!@rename($source, $target)) {
            if (is_dir($backup)) @rename($backup, $target);
            throw new RuntimeException('No se pudo activar el nuevo build del proyecto.');
        }

        if (is_dir($backup)) $this->removeDir($backup);
    }

    /** Reemplazo seguro del ZIP descargable. */
    private function replaceFile(string $source, string $target): void
    {
        $backup = $target . '.bak';
        if (is_file($backup)) @unlink($backup);

        if (is_file($target) && !@rename($target, $backup)) {
            throw new RuntimeException('No se pudo preparar el reemplazo del ZIP anterior.');
        }

        if (!@rename($source, $target)) {
            if (is_file($backup)) @rename($backup, $target);
            throw new RuntimeException('No se pudo activar el nuevo ZIP del proyecto.');
        }

        if (is_file($backup)) @unlink($backup);
    }

    /**
     * Elimina exports con timestamp de versiones anteriores para el proyecto actual.
     * Nunca toca el build estable <slug>-site ni su ZIP.
     */
    private function cleanupHistoricalBuildsForProject(string $buildRoot, string $projectSlug, array $keepPaths): void
    {
        if (!is_dir($buildRoot)) return;
        $keep = array_fill_keys(array_map(static fn(string $p): string => str_replace('\\', '/', $p), $keepPaths), true);
        $quoted = preg_quote($projectSlug, '#');

        foreach (new \DirectoryIterator($buildRoot) as $item) {
            if ($item->isDot()) continue;
            $name = $item->getFilename();
            $full = str_replace('\\', '/', $item->getPathname());
            if (isset($keep[$full])) continue;

            // Formatos legacy: <proyecto>-site-YYYYmmdd-HHmmss y
            // <proyecto>-<pagina>-YYYYmmdd-HHmmss, con o sin .zip.
            if (!preg_match('#^' . $quoted . '-.+-\\d{8}-\\d{6}(?:\\.zip)?$#i', $name)) continue;

            if ($item->isDir()) $this->removeDir($item->getPathname());
            elseif ($item->isFile()) @unlink($item->getPathname());
        }
    }

    private function safeSlug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? 'site';
        return trim($value, '-') ?: 'site';
    }

    private function makeDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta de build.');
        }
    }

    private function copyDir(string $source, string $target): void
    {
        $this->makeDir($target);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $dest = $target . '/' . str_replace('\\', '/', $iterator->getSubPathName());
            if ($item->isDir()) $this->makeDir($dest); else copy($item->getPathname(), $dest);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
