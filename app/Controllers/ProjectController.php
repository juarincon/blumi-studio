<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Middleware\Auth;
use App\Services\PageService;
use App\Services\ProjectService;
use InvalidArgumentException;

final class ProjectController
{
    public function create(): void
    {
        Auth::requireRole(['superadmin', 'builder']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            $_SESSION['flash_error'] = 'La solicitud expiró. Inténtalo nuevamente.';
            header('Location: index.php');
            exit;
        }

        try {
            $id = (new ProjectService())->create($_POST, (int)$_SESSION['user']['id']);
            $_SESSION['flash_success'] = 'Proyecto creado. Ahora define su identidad visual.';
            header('Location: index.php?route=design.show&id=' . $id);
            exit;
        } catch (InvalidArgumentException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log($e->__toString());
            $_SESSION['flash_error'] = 'No se pudo crear el proyecto.';
        }

        header('Location: index.php');
        exit;
    }

    public function show(): void
    {
        Auth::requireLogin();
        $id = (int)($_GET['id'] ?? 0);
        $project = (new ProjectService())->find($id);
        if (!$project) {
            http_response_code(404);
            echo 'Proyecto no encontrado.';
            return;
        }
        $pages = (new PageService())->forProject($id);
        View::render('projects/show', ['project' => $project, 'pages' => $pages]);
    }
}
