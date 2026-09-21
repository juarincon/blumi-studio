<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Middleware\Auth;
use App\Services\ComponentService;
use App\Services\ComponentCustomizationService;
use InvalidArgumentException;

final class ComponentController
{
    public function add(): void
    {
        Auth::requireRole(['superadmin', 'builder']);
        $pageId = (int)($_POST['page_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            $this->expired($pageId);
        }

        try {
            $instanceId = (new ComponentService())->addToPage(
                $pageId,
                (int)($_POST['component_id'] ?? 0),
                (int)$_SESSION['user']['id']
            );
            $_SESSION['flash_success'] = 'Sección agregada.';
            $this->redirectBuilder($pageId, $instanceId);
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo agregar la sección.';
        }
        $this->redirectBuilder($pageId);
    }

    public function update(): void
    {
        Auth::requireLogin();
        $instanceId = (int)($_POST['instance_id'] ?? 0);
        $pageId = (int)($_POST['page_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            $this->expired($pageId);
        }

        try {
            $role = (string)($_SESSION['user']['role'] ?? 'cliente');
            (new ComponentService())->updateContent($instanceId, $_POST, $_FILES, $role, (int)$_SESSION['user']['id']);
            $_SESSION['flash_success'] = 'Contenido guardado.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudieron guardar los cambios.';
        }
        $this->redirectBuilder($pageId, $instanceId);
    }

    public function updateStyle(): void
    {
        Auth::requireRole(['superadmin', 'builder']);
        $instanceId = (int)($_POST['instance_id'] ?? 0);
        $pageId = (int)($_POST['page_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            $this->expired($pageId);
        }

        try {
            (new ComponentService())->updateStyles($instanceId, $_POST, $_FILES, (int)($_SESSION['user']['id'] ?? 0));
            $_SESSION['flash_success'] = !empty($_POST['reset_styles']) ? 'Estilo restablecido.' : 'Estilo actualizado.';
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo guardar el estilo.';
        }
        $this->redirectBuilder($pageId, $instanceId);
    }

    public function customizationPropose(): void
    {
        Auth::requireRole(['superadmin', 'builder']);
        $data = $this->jsonBody();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($data['_csrf'] ?? null)) {
            $this->json(['ok'=>false,'error'=>'La solicitud expiró. Recarga la página e inténtalo nuevamente.'], 419);
        }
        try {
            $targets = is_array($data['targets'] ?? null) ? $data['targets'] : [];
            $result = (new ComponentCustomizationService())->propose(
                (int)($data['instance_id'] ?? 0),
                $targets,
                trim((string)($data['instruction'] ?? ''))
            );
            $this->json(['ok'=>true] + $result);
        } catch (InvalidArgumentException $e) {
            $this->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $this->json(['ok'=>false,'error'=>'No se pudo generar una propuesta segura. Intenta describir el ajuste de otra forma.'], 500);
        }
    }

    public function customizationApply(): void
    {
        Auth::requireRole(['superadmin', 'builder']);
        $data = $this->jsonBody();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($data['_csrf'] ?? null)) {
            $this->json(['ok'=>false,'error'=>'La solicitud expiró. Recarga la página e inténtalo nuevamente.'], 419);
        }
        try {
            $patch = is_array($data['patch'] ?? null) ? $data['patch'] : [];
            $interpretation = is_array($data['interpretation'] ?? null) ? $data['interpretation'] : null;
            $state = (new ComponentCustomizationService())->applyPatch(
                (int)($data['instance_id'] ?? 0),
                $patch,
                (int)($_SESSION['user']['id'] ?? 0),
                trim((string)($data['instruction'] ?? '')),
                $interpretation
            );
            $this->json(['ok'=>true,'state'=>$state]);
        } catch (InvalidArgumentException $e) {
            $this->json(['ok'=>false,'error'=>$e->getMessage()], 422);
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $this->json(['ok'=>false,'error'=>'No se pudo aplicar la personalización.'], 500);
        }
    }

    public function remove(): void
    {
        Auth::requireRole(['superadmin', 'builder']);
        $pageId = (int)($_POST['page_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            $this->expired($pageId);
        }

        try {
            $pageId = (new ComponentService())->remove((int)($_POST['instance_id'] ?? 0));
            $_SESSION['flash_success'] = 'Sección eliminada de la página.';
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo eliminar la sección.';
        }
        $this->redirectBuilder($pageId);
    }

    public function move(): void
    {
        Auth::requireRole(['superadmin', 'builder']);
        $pageId = (int)($_POST['page_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            $this->expired($pageId);
        }

        try {
            $direction = ($_POST['direction'] ?? '') === 'up' ? 'up' : 'down';
            $pageId = (new ComponentService())->move((int)($_POST['instance_id'] ?? 0), $direction);
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo mover la sección.';
        }
        $this->redirectBuilder($pageId, (int)($_POST['instance_id'] ?? 0));
    }

    /** @return array<string,mixed> */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : [];
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function expired(int $pageId): never
    {
        http_response_code(419);
        $_SESSION['flash_error'] = 'La solicitud expiró. Inténtalo nuevamente.';
        $this->redirectBuilder($pageId);
    }

    private function redirectBuilder(int $pageId, int $instanceId = 0): never
    {
        $url = 'index.php?route=builder&page_id=' . max(0, $pageId);
        if ($instanceId > 0) {
            $url .= '&instance_id=' . $instanceId . '#blumi-instance-' . $instanceId;
        }
        header('Location: ' . $url);
        exit;
    }
}
