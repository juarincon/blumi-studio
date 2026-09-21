<?php
namespace App\Services;

use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(private readonly UserRepository $users = new UserRepository()) {}

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail(mb_strtolower(trim($email)));
        if (!$user || $user['estado'] !== 'activo' || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['nombre'],
            'email' => $user['email'],
            'role' => $user['rol'],
        ];
        $this->users->touchLastLogin((int) $user['id']);
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
