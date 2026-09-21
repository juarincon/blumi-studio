<?php
namespace App\Services;

use RuntimeException;
use ZipArchive;

final class UpdateService
{
    private string $root;
    private string $pendingDir;
    private string $backupDir;

    /** @var string[] */
    private array $allowedPrefixes = ['app/', 'public/', 'views/', 'config/'];

    public function __construct()
    {
        $this->root = dirname(__DIR__, 2);
        $this->pendingDir = $this->root . '/storage/updates/pending';
        $this->backupDir = $this->root . '/storage/update-backups';
        $this->ensureDir($this->pendingDir);
        $this->ensureDir($this->backupDir);
    }

    public function projectRoot(): string
    {
        return $this->root;
    }

    public function preview(array $upload): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive no está habilitado en PHP.');
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo recibir el archivo ZIP.');
        }
        $name = (string)($upload['name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('El paquete debe ser un archivo .zip.');
        }
        if (($upload['size'] ?? 0) > 20 * 1024 * 1024) {
            throw new RuntimeException('El paquete supera el máximo de 20 MB.');
        }

        $token = bin2hex(random_bytes(16));
        $target = $this->pendingDir . '/' . $token . '.zip';
        if (!move_uploaded_file((string)$upload['tmp_name'], $target)) {
            throw new RuntimeException('No se pudo guardar temporalmente el paquete.');
        }

        try {
            $files = $this->inspectZip($target);
        } catch (\Throwable $e) {
            @unlink($target);
            throw $e;
        }

        if (!$files) {
            @unlink($target);
            throw new RuntimeException('El paquete no contiene archivos reemplazables de Blumi.');
        }

        return [
            'token' => $token,
            'original_name' => $name,
            'files' => $files,
        ];
    }

    public function apply(string $token): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new RuntimeException('Paquete de actualización inválido.');
        }
        $zipPath = $this->pendingDir . '/' . $token . '.zip';
        if (!is_file($zipPath)) {
            throw new RuntimeException('El paquete temporal ya no existe. Vuelve a seleccionarlo.');
        }

        $entries = $this->inspectZip($zipPath);
        $backup = $this->createBackup($entries);
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('No se pudo abrir el ZIP de actualización.');
        }

        $replaced = [];
        try {
            foreach ($entries as $entry) {
                $relative = $entry['path'];
                $contents = $zip->getFromName($entry['zip_name']);
                if ($contents === false) {
                    throw new RuntimeException('No se pudo leer ' . $relative . ' del paquete.');
                }
                $destination = $this->root . '/' . $relative;
                $this->ensureDir(dirname($destination));
                $tmp = $destination . '.blumi-update-' . bin2hex(random_bytes(4));
                if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
                    throw new RuntimeException('No se pudo escribir temporalmente ' . $relative . '.');
                }
                if (is_file($destination)) {
                    $perms = @fileperms($destination);
                    if ($perms !== false) {
                        @chmod($tmp, $perms & 0777);
                    }
                }
                if (!@rename($tmp, $destination)) {
                    // Algunos hostings permiten escribir el archivo existente pero no
                    // reemplazarlo mediante rename() por ownership/permisos del directorio.
                    // Como ya existe un backup previo, usamos sobrescritura directa como
                    // fallback seguro para archivos existentes.
                    if (is_file($destination) && is_writable($destination)) {
                        $written = @file_put_contents($destination, $contents, LOCK_EX);
                        @unlink($tmp);
                        if ($written === false || $written !== strlen($contents)) {
                            throw new RuntimeException(
                                'No se pudo reemplazar ' . $relative .
                                '. El archivo existe pero PHP no pudo sobrescribirlo.'
                            );
                        }
                    } else {
                        @unlink($tmp);
                        $dir = dirname($destination);
                        throw new RuntimeException(
                            'No se pudo reemplazar ' . $relative .
                            '. Archivo escribible: ' . (is_writable($destination) ? 'si' : 'no') .
                            '; carpeta escribible: ' . (is_writable($dir) ? 'si' : 'no') . '.'
                        );
                    }
                }
                $replaced[] = $relative;
            }
        } finally {
            $zip->close();
        }

        @unlink($zipPath);
        return ['files' => $replaced, 'backup' => $backup];
    }

    private function inspectZip(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('El archivo no es un ZIP válido.');
        }
        $files = [];
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $raw = (string)($stat['name'] ?? '');
                if ($raw === '' || str_ends_with($raw, '/') || str_starts_with($raw, '__MACOSX/')) {
                    continue;
                }
                $relative = $this->normalizeEntry($raw);
                if ($relative === null) {
                    continue;
                }
                $destination = $this->root . '/' . $relative;
                $files[] = [
                    'path' => $relative,
                    'zip_name' => $raw,
                    'status' => is_file($destination) ? 'reemplazar' : 'agregar',
                    'size' => (int)($stat['size'] ?? 0),
                ];
            }
        } finally {
            $zip->close();
        }
        return $files;
    }

    private function normalizeEntry(string $raw): ?string
    {
        $path = str_replace('\\', '/', trim($raw));
        $path = preg_replace('#^\./+#', '', $path) ?? $path;

        // Permite que los paquetes vengan envueltos en una carpeta raíz.
        $parts = explode('/', $path);
        if (count($parts) > 1 && !in_array($parts[0] . '/', $this->allowedPrefixes, true)) {
            array_shift($parts);
            $path = implode('/', $parts);
        }

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '../') || str_contains($path, "\0")) {
            throw new RuntimeException('Ruta insegura detectada en el ZIP: ' . $raw);
        }
        if ($path === '.env' || str_starts_with($path, 'storage/')) {
            throw new RuntimeException('El paquete intenta modificar un archivo protegido: ' . $path);
        }
        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $path;
            }
        }
        return null;
    }

    private function createBackup(array $entries): string
    {
        $name = 'backup-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.zip';
        $path = $this->backupDir . '/' . $name;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el backup previo a la actualización.');
        }
        $manifest = [];
        foreach ($entries as $entry) {
            $relative = $entry['path'];
            $source = $this->root . '/' . $relative;
            if (is_file($source)) {
                $zip->addFile($source, $relative);
                $manifest[] = ['path' => $relative, 'existed' => true];
            } else {
                $manifest[] = ['path' => $relative, 'existed' => false];
            }
        }
        $zip->addFromString('blumi-update-manifest.json', json_encode([
            'created_at' => date(DATE_ATOM),
            'files' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $zip->close();
        return 'storage/update-backups/' . $name;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta: ' . $dir);
        }
    }
}
