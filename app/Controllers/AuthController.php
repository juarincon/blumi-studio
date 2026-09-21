<?php
namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\AuthService;

final class AuthController
{
    public function login(): void
    {
        if (!empty($_SESSION['user'])) {
            header('Location: index.php');
            exit;
        }
        View::render('auth/login', ['error' => null]);
    }

    public function authenticate(): void
    {
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            View::render('auth/login', ['error' => 'La sesión del formulario expiró. Inténtalo nuevamente.']);
            return;
        }

        $service = new AuthService();
        if (!$service->attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) {
            View::render('auth/login', ['error' => 'Correo o contraseña incorrectos.']);
            return;
        }

        header('Location: index.php');
        exit;
    }

    public function logout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'Solicitud inválida.';
            return;
        }
        (new AuthService())->logout();
        header('Location: index.php?route=login');
        exit;
    }
}
