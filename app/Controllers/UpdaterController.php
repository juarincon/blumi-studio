<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\Auth;
use App\Services\UpdateService;

final class UpdaterController
{
    public function index(): void
    {
        Auth::requireRole(['superadmin']);
        $service = new UpdateService();
        View::render('updater/index', [
            'root' => $service->projectRoot(),
            'preview' => $_SESSION['update_preview'] ?? null,
        ]);
    }

    public function preview(): void
    {
        Auth::requireRole(['superadmin']);
        $this->csrf();
        try {
            $_SESSION['update_preview'] = (new UpdateService())->preview($_FILES['package'] ?? []);
            $_SESSION['flash_success'] = 'Paquete leído. Revisa los archivos antes de aplicar.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
            unset($_SESSION['update_preview']);
        }
        header('Location: index.php?route=updater');
        exit;
    }

    public function apply(): void
    {
        Auth::requireRole(['superadmin']);
        $this->csrf();
        try {
            $token = (string)($_POST['token'] ?? '');
            $result = (new UpdateService())->apply($token);
            unset($_SESSION['update_preview']);
            $_SESSION['flash_success'] = 'Actualización aplicada: ' . count($result['files']) . ' archivo(s). Backup: ' . $result['backup'];
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
        header('Location: index.php?route=updater');
        exit;
    }

    private function csrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Solicitud expirada.');
        }
    }
}
