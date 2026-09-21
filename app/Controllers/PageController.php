<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\Auth;
use App\Services\ComponentService;
use App\Services\ComponentLibraryService;
use App\Services\ComponentCustomizationService;
use App\Services\ComponentFactoryService;
use App\Services\DesignService;
use App\Services\PageService;
use App\Services\RenderService;
use InvalidArgumentException;

final class PageController
{
    public function create(): void
    {
        Auth::requireRole(['superadmin', 'builder']);
        $projectId = (int)($_POST['proyecto_id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            $_SESSION['flash_error'] = 'La solicitud expiró. Inténtalo nuevamente.';
            $this->redirectProject($projectId);
        }

        try {
            $pageId = (new PageService())->create($_POST, (int)$_SESSION['user']['id']);
            $_SESSION['flash_success'] = 'Página creada correctamente.';
            header('Location: index.php?route=builder&page_id=' . $pageId);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo crear la página.';
        }

        $this->redirectProject($projectId);
    }

    public function builder(): void
    {
        Auth::requireLogin();
        $pageId = (int)($_GET['page_id'] ?? 0);
        try {
            $pageService = new PageService();
            $componentService = new ComponentService();
            $renderer = new RenderService();
            $designService = new DesignService();

            $page = $pageService->find($pageId);
            $pages = $pageService->forProject((int)$page['proyecto_id']);
            $instances = $componentService->instancesForPage($pageId);
            $libraryService = new ComponentLibraryService();
            $library = $componentService->library();
            $componentLibraries = $libraryService->all((int)$page['proyecto_id']);
            $preferredLibrary = $libraryService->preferredForProject((int)$page['proyecto_id']);
            $design = $designService->get((int)$page['proyecto_id']);
            $projectFonts = $designService->fonts((int)$page['proyecto_id']);
            $brandReadiness = $designService->readiness((int)$page['proyecto_id']);
            $factoryCategories = (new ComponentFactoryService())->categories();

            // Catálogo de destinos internos para Smart Links.
            $linkSectionsByPage = [];
            foreach ($pages as $linkPage) {
                $linkPageId = (int)($linkPage['id'] ?? 0);
                $linkSectionsByPage[$linkPageId] = [];
                foreach ($componentService->instancesForPage($linkPageId) as $linkInstance) {
                    $category = strtolower((string)($linkInstance['categoria_slug'] ?? ''));
                    if (in_array($category, ['navbar','footer'], true)) continue;
                    $linkSectionsByPage[$linkPageId][] = [
                        'id' => (int)$linkInstance['id'],
                        'label' => (string)(($linkInstance['nombre_interno'] ?? '') ?: ($linkInstance['componente_nombre'] ?? 'Sección')),
                        'category' => (string)($linkInstance['categoria_nombre'] ?? ''),
                    ];
                }
            }

            $projectGlobals = [
                'site_logo' => ['value' => (string)($design['logo_ruta'] ?? ''), 'type' => 'image'],
                'site_name' => ['value' => (string)($design['nombre_comercial'] ?: $page['proyecto_nombre']), 'type' => 'text'],
                'site_home_url' => ['value' => '#', 'type' => 'url'],
            ];

            // La biblioteca del builder debe mostrar el componente real, no un
            // placeholder por categoria. Se renderiza con defaults + identidad del proyecto.
            foreach ($library as &$libraryComponent) {
                $libraryComponent['preview_html'] = $renderer->renderLibraryComponent($libraryComponent, $projectGlobals);
            }
            unset($libraryComponent);

            $selectedInstance = null;
            $selectedId = (int)($_GET['instance_id'] ?? 0);
            if ($selectedId > 0) {
                try {
                    $candidate = $componentService->findInstance($selectedId);
                    if ((int)$candidate['pagina_id'] === $pageId) {
                        $selectedInstance = $candidate;
                    }
                } catch (\Throwable) {
                    $selectedInstance = null;
                }
            }

            $personalizationTargets = [];
            if ($selectedInstance) {
                try {
                    $personalizationTargets = (new ComponentCustomizationService())->availableTargets((int)$selectedInstance['id']);
                } catch (\Throwable $e) {
                    error_log('Blumi personalization targets: ' . $e->getMessage());
                    $personalizationTargets = [];
                }
            }

            $renderedInstances = [];
            foreach ($instances as $instance) {
                $instance['rendered_html'] = $renderer->renderInstance($instance, $projectGlobals);
                $renderedInstances[] = $instance;
            }

            View::render('builder/index', [
                'page' => $page,
                'pages' => $pages,
                'instances' => $renderedInstances,
                'library' => $library,
                'componentLibraries' => $componentLibraries,
                'factoryCategories' => $factoryCategories,
                'preferredLibraryId' => (int)($preferredLibrary['id'] ?? 0),
                'selectedInstance' => $selectedInstance,
                'personalizationTargets' => $personalizationTargets,
                'componentCss' => $renderer->collectCss($instances) . "\n" . $renderer->collectInstanceStyleCss($instances),
                'componentJs' => $renderer->collectJs($instances),
                'design' => $design,
                'projectFontCss' => $designService->fontFaceCss($projectFonts),
                'googleFontUrls' => $designService->googleFontUrls($design),
                'brandReadiness' => $brandReadiness,
                'linkSectionsByPage' => $linkSectionsByPage,
            ]);
        } catch (\Throwable $e) {
            error_log($e->__toString());
            http_response_code(404);
            echo 'La página solicitada no existe.';
        }
    }

    public function archive(): void
    {
        Auth::requireRole(['superadmin', 'builder']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'Solicitud expirada.';
            exit;
        }

        try {
            $projectId = (new PageService())->archive((int)($_POST['page_id'] ?? 0));
            $_SESSION['flash_success'] = 'Página archivada.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            $page = null;
            try { $page = (new PageService())->find((int)($_POST['page_id'] ?? 0)); } catch (\Throwable) {}
            $projectId = (int)($page['proyecto_id'] ?? 0);
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo archivar la página.';
            $projectId = (int)($_POST['proyecto_id'] ?? 0);
        }

        $this->redirectProject($projectId);
    }

    private function redirectProject(int $projectId): never
    {
        header('Location: index.php?route=projects.show&id=' . max(0, $projectId));
        exit;
    }
}
