<?php
namespace App\Middleware;

final class Auth
{
    public static function requireLogin(): void
    {
        if (empty($_SESSION['user'])) {
            header('Location: index.php?route=login');
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $role = $_SESSION['user']['role'] ?? null;
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            echo 'No tienes permisos para realizar esta acción.';
            exit;
        }
    }
}
