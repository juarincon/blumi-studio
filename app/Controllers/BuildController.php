<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Middleware\Auth;
use App\Services\BuildService;

final class BuildController
{
    public function download(): void
    {
        Auth::requireRole(['superadmin','builder']);
        $pageId = (int)($_GET['page_id'] ?? 0);
        try {
            $build = (new BuildService())->generateSite($pageId);
            $zip = $build['zip'];
            if (!is_file($zip)) throw new \RuntimeException('No se encontró el build generado.');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zip) . '"');
            header('Content-Length: ' . filesize($zip));
            readfile($zip);
            exit;
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: index.php?route=builder&page_id=' . $pageId);
            exit;
        }
    }

    public function publish(): void
    {
        Auth::requireRole(['superadmin','builder']);
        $pageId = (int)($_POST['page_id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419); exit('Solicitud expirada.');
        }
        try {
            $build = (new BuildService())->publishSite($pageId);
            header('Location: ' . $build['public_url_path']);
            exit;
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = $e->getMessage();
            header('Location: index.php?route=builder&page_id=' . $pageId);
            exit;
        }
    }
}
