<?php
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Helpers\Database;

if (PHP_SAPI !== 'cli') {
    exit("Este script solo debe ejecutarse por CLI.\n");
}

$name = $argv[1] ?? null;
$email = $argv[2] ?? null;
$password = $argv[3] ?? null;

if (!$name || !$email || !$password) {
    exit("Uso: php tools/create_admin.php \"Nombre\" correo@dominio.com \"ContrasenaSegura\"\n");
}

$stmt = Database::connection()->prepare(
    'INSERT INTO usuarios (nombre, email, password_hash, rol, estado)
     VALUES (:nombre, :email, :password_hash, :rol, :estado)'
);
$stmt->execute([
    'nombre' => $name,
    'email' => mb_strtolower(trim($email)),
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'rol' => 'superadmin',
    'estado' => 'activo',
]);

echo "Superadmin creado correctamente.\n";
