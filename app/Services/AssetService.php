<?php
namespace App\Services;

use App\Repositories\AssetRepository;
use InvalidArgumentException;

final class AssetService
{
    public function __construct(private readonly AssetRepository $assets = new AssetRepository()) {}

    public function uploadImage(int $projectId, array $file, int $userId, string $type = 'image'): array
    {
        if ($projectId <= 0) {
            throw new InvalidArgumentException('Proyecto inválido.');
        }
        $this->assertUpload($file);

        $tmp = (string)$file['tmp_name'];
        $mime = $this->mime($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
        ];
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Usa una imagen SVG, PNG, JPG o WebP.');
        }
        if ((int)$file['size'] > 10 * 1024 * 1024) {
            throw new InvalidArgumentException('La imagen supera el límite de 10 MB.');
        }

        $extension = $allowed[$mime];
        $filename = bin2hex(random_bytes(12)) . '.' . $extension;
        $relativeDir = 'uploads/projects/' . $projectId . '/images';
        $targetDir = dirname(__DIR__, 2) . '/public/' . $relativeDir;
        $this->ensureDirectory($targetDir);
        $target = $targetDir . '/' . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            throw new InvalidArgumentException('No se pudo guardar la imagen.');
        }
        if ($mime === 'image/svg+xml') {
            try {
                $this->sanitizeSvgFile($target);
            } catch (\Throwable $e) {
                @unlink($target);
                throw new InvalidArgumentException($e->getMessage());
            }
        }

        $width = null;
        $height = null;
        if ($mime !== 'image/svg+xml') {
            $size = @getimagesize($target);
            if (is_array($size)) {
                $width = (int)($size[0] ?? 0) ?: null;
                $height = (int)($size[1] ?? 0) ?: null;
            }
        }

        $assetId = $this->assets->create([
            'proyecto_id' => $projectId,
            'tipo' => in_array($type, ['image','logo','icon'], true) ? $type : 'image',
            'filename' => $filename,
            'original_filename' => basename((string)$file['name']),
            'ruta' => $relativeDir . '/' . $filename,
            'mime_type' => $mime,
            'tamano_bytes' => (int)$file['size'],
            'width' => $width,
            'height' => $height,
            'alt_text' => null,
            'title' => null,
            'uploaded_by' => $userId,
        ]);

        return ['id' => $assetId, 'path' => $relativeDir . '/' . $filename, 'mime' => $mime];
    }

    public function uploadFont(int $projectId, array $file, int $userId, string $name, int $weight, string $style): array
    {
        $this->assertUpload($file);
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Escribe el nombre de la fuente.');
        }
        if (!in_array($weight, [100,200,300,400,500,600,700,800,900], true)) {
            $weight = 400;
        }
        $style = $style === 'italic' ? 'italic' : 'normal';
        $original = basename((string)$file['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['woff2','woff'], true)) {
            throw new InvalidArgumentException('Solo se permiten fuentes .woff2 o .woff.');
        }
        if ((int)$file['size'] > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('La fuente supera el límite de 5 MB.');
        }
        $mime = $this->mime((string)$file['tmp_name']);
        $allowedMimes = ['font/woff2','font/woff','application/font-woff','application/octet-stream'];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new InvalidArgumentException('El archivo no parece ser una fuente web válida.');
        }

        $slug = $this->slugify($name);
        $filename = $slug . '-' . $weight . '-' . $style . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
        $relativeDir = 'uploads/projects/' . $projectId . '/fonts/' . $slug;
        $targetDir = dirname(__DIR__, 2) . '/public/' . $relativeDir;
        $this->ensureDirectory($targetDir);
        $target = $targetDir . '/' . $filename;
        if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
            throw new InvalidArgumentException('No se pudo guardar la fuente.');
        }

        return [
            'nombre' => $name,
            'slug' => $slug,
            'peso' => $weight,
            'estilo' => $style,
            'formato' => $ext,
            'filename' => $filename,
            'original_filename' => $original,
            'ruta' => $relativeDir . '/' . $filename,
            'mime_type' => $mime,
            'tamano_bytes' => (int)$file['size'],
            'uploaded_by' => $userId,
        ];
    }

    private function assertUpload(array $file): void
    {
        $error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'La imagen supera el límite permitido por el servidor. Reduce su peso e inténtalo de nuevo.',
                UPLOAD_ERR_PARTIAL => 'La imagen se cargó de forma incompleta. Inténtalo nuevamente.',
                UPLOAD_ERR_NO_FILE => 'Selecciona una imagen para subir.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene disponible la carpeta temporal de cargas.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar temporalmente la imagen.',
                UPLOAD_ERR_EXTENSION => 'Una extensión del servidor bloqueó la carga de la imagen.',
                default => 'No se pudo completar la carga de la imagen.',
            };
            throw new InvalidArgumentException($message);
        }
        if (empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new InvalidArgumentException('La carga del archivo no es válida.');
        }
    }

    private function mime(string $path): string
    {
        $detected = '';
        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = (string)$finfo->file($path);
        } catch (\Throwable) {
            $detected = '';
        }

        if (in_array($detected, ['image/jpeg','image/png','image/webp','image/svg+xml'], true)) {
            return $detected;
        }

        $head = (string)@file_get_contents($path, false, null, 0, 512);
        if ($head === '') return $detected;

        if (strlen($head) >= 3 && ord($head[0]) === 0xFF && ord($head[1]) === 0xD8 && ord($head[2]) === 0xFF) {
            return 'image/jpeg';
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (strlen($head) >= 12 && substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        $textHead = ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $head) ?? $head);
        if (preg_match('/^(?:<\?xml[^>]*>\s*)?<svg\b/i', $textHead)) {
            return 'image/svg+xml';
        }

        return $detected;
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new InvalidArgumentException('No se pudo preparar la carpeta de archivos.');
        }
    }

    private function sanitizeSvgFile(string $path): void
    {
        $svg = (string)file_get_contents($path);
        if ($svg === '' || !preg_match('/<svg\b/i', $svg)) {
            throw new InvalidArgumentException('El SVG no es válido.');
        }
        $lower = strtolower($svg);
        foreach (['<!doctype','<!entity','<script','<foreignobject','<iframe','<object','<embed','javascript:','data:text/html','@import'] as $danger) {
            if (str_contains($lower, $danger)) {
                throw new InvalidArgumentException('El SVG contiene contenido no permitido.');
            }
        }
        // Elimina manejadores inline como onclick/onload y referencias externas http(s).
        $svg = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg) ?? $svg;
        $svg = preg_replace('/\s+(?:href|xlink:href)\s*=\s*("|\')https?:\/\/.*?\1/i', '', $svg) ?? $svg;
        if (file_put_contents($path, $svg) === false) {
            throw new InvalidArgumentException('No se pudo preparar el SVG de forma segura.');
        }
    }

    private function slugify(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? 'font';
        return trim($value, '-') ?: 'font';
    }
}
