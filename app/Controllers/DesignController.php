<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\Auth;
use App\Services\AssetService;
use App\Services\DesignService;
use App\Services\PageService;
use App\Services\ProjectService;
use InvalidArgumentException;

final class DesignController
{
    public function show(): void
    {
        Auth::requireLogin();
        $projectId = (int)($_GET['id'] ?? 0);
        $project = (new ProjectService())->find($projectId);
        if (!$project) {
            http_response_code(404);
            echo 'Proyecto no encontrado.';
            return;
        }

        $designService = new DesignService();
        $design = $designService->get($projectId);
        $fonts = $designService->fonts($projectId);
        View::render('design/index', [
            'project' => $project,
            'pages' => (new PageService())->forProject($projectId),
            'design' => $design,
            'fonts' => $fonts,
            'references' => $designService->references($projectId),
            'googleFonts' => $designService->googleFonts(),
            'fontFaceCss' => $designService->fontFaceCss($fonts),
            'googleFontUrls' => $designService->googleFontUrls($design),
            'brandReadiness' => $designService->readiness($projectId),
        ]);
    }

    public function update(): void
    {
        Auth::requireRole(['superadmin','builder']);
        $projectId = (int)($_POST['proyecto_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            $_SESSION['flash_error'] = 'La solicitud expiró.';
            $this->redirect($projectId);
        }

        try {
            (new DesignService())->update($projectId, $_POST);
            $_SESSION['flash_success'] = 'Identidad visual guardada.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo guardar la identidad visual.';
        }
        $this->redirect($projectId);
    }

    public function uploadBrandAsset(): void
    {
        Auth::requireRole(['superadmin','builder']);
        $projectId = (int)($_POST['proyecto_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            $_SESSION['flash_error'] = 'La solicitud expiró.';
            $this->redirect($projectId);
        }

        try {
            $kind = (string)($_POST['tipo'] ?? 'referencia');
            $assetType = $kind === 'logo' ? 'logo' : 'image';
            $asset = (new AssetService())->uploadImage($projectId, $_FILES['archivo'] ?? [], (int)$_SESSION['user']['id'], $assetType);
            (new DesignService())->addReference($projectId, (int)$asset['id'], $kind, (int)$_SESSION['user']['id']);
            $_SESSION['flash_success'] = $kind === 'logo' ? 'Logo actualizado.' : 'Referencia visual agregada.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo subir el archivo.';
        }
        $this->redirect($projectId);
    }

    public function uploadFont(): void
    {
        Auth::requireRole(['superadmin','builder']);
        $projectId = (int)($_POST['proyecto_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            $_SESSION['flash_error'] = 'La solicitud expiró.';
            $this->redirect($projectId);
        }

        try {
            $upload = (new AssetService())->uploadFont(
                $projectId,
                $_FILES['archivo'] ?? [],
                (int)$_SESSION['user']['id'],
                (string)($_POST['nombre'] ?? ''),
                (int)($_POST['peso'] ?? 400),
                (string)($_POST['estilo'] ?? 'normal')
            );
            (new DesignService())->addFont($projectId, $upload);
            $_SESSION['flash_success'] = 'Fuente agregada. Ya puedes seleccionarla en tipografía.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo subir la fuente.';
        }
        $this->redirect($projectId);
    }

    private function redirect(int $projectId): never
    {
        header('Location: index.php?route=design.show&id=' . max(0, $projectId));
        exit;
    }
}
