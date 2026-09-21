<?php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\BuildController;
use App\Controllers\ComponentController;
use App\Controllers\ComponentFactoryController;
use App\Controllers\DashboardController;
use App\Controllers\DesignController;
use App\Controllers\PageController;
use App\Controllers\ProjectController;
use App\Controllers\UpdaterController;

$route = $_GET['route'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($route) {
        case 'login':
            $controller = new AuthController();
            $method === 'POST' ? $controller->authenticate() : $controller->login();
            break;
        case 'logout':
            (new AuthController())->logout();
            break;
        case 'projects.create':
            (new ProjectController())->create();
            break;
        case 'projects.show':
            (new ProjectController())->show();
            break;
        case 'design.show':
            (new DesignController())->show();
            break;
        case 'design.update':
            (new DesignController())->update();
            break;
        case 'design.upload_brand_asset':
            (new DesignController())->uploadBrandAsset();
            break;
        case 'design.upload_font':
            (new DesignController())->uploadFont();
            break;
        case 'pages.create':
            (new PageController())->create();
            break;
        case 'pages.rename':
            (new PageController())->rename();
            break;
        case 'pages.archive':
            (new PageController())->archive();
            break;
        case 'component_factory':
            (new ComponentFactoryController())->index();
            break;
        case 'component_factory.create':
            (new ComponentFactoryController())->create();
            break;
        case 'component_library.create':
            (new ComponentFactoryController())->createLibrary();
            break;
        case 'component_library.create_inline':
            (new ComponentFactoryController())->createLibraryInline();
            break;
        case 'component_factory.generate':
            (new ComponentFactoryController())->generate();
            break;
        case 'component_factory.show':
            (new ComponentFactoryController())->show();
            break;
        case 'component_factory.save_css':
            (new ComponentFactoryController())->saveCss();
            break;
        case 'component_factory.approve':
            (new ComponentFactoryController())->approve();
            break;
        case 'component_factory.duplicate':
            (new ComponentFactoryController())->duplicate();
            break;
        case 'component_factory.preview':
            (new ComponentFactoryController())->preview();
            break;
        case 'build.download':
            (new BuildController())->download();
            break;
        case 'build.publish':
            (new BuildController())->publish();
            break;
        case 'updater':
            (new UpdaterController())->index();
            break;
        case 'updater.preview':
            (new UpdaterController())->preview();
            break;
        case 'updater.apply':
            (new UpdaterController())->apply();
            break;
        case 'components.add':
            (new ComponentController())->add();
            break;
        case 'components.update':
            (new ComponentController())->update();
            break;
        case 'components.style':
            (new ComponentController())->updateStyle();
            break;
        case 'components.customization.propose':
            (new ComponentController())->customizationPropose();
            break;
        case 'components.customization.apply':
            (new ComponentController())->customizationApply();
            break;
        case 'components.remove':
            (new ComponentController())->remove();
            break;
        case 'components.move':
            (new ComponentController())->move();
            break;
        case 'builder':
            (new PageController())->builder();
            break;
        case 'dashboard':
        default:
            (new DashboardController())->index();
            break;
    }
} catch (Throwable $e) {
    error_log($e->__toString());
    http_response_code(500);
    echo 'Ocurrió un error interno. Revisa storage/logs o el log de PHP.';
}
