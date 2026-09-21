<?php
namespace App\Helpers;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $path = dirname(__DIR__, 2) . '/views/' . $view . '.php';
        if (!is_file($path)) {
            http_response_code(500);
            echo 'Vista no encontrada.';
            return;
        }
        extract($data, EXTR_SKIP);
        require $path;
    }
}
