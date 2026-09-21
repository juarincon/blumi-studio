<?php
/**
 * Blumi Studio - limpieza de builds historicos.
 *
 * Uso seguro (solo muestra lo que borraria):
 *   php tools/cleanup_builds.php
 *
 * Aplicar limpieza:
 *   php tools/cleanup_builds.php --apply
 *
 * Regla:
 * - Conserva builds estables sin timestamp (v1.1.2+).
 * - Para cada serie legacy <prefijo>-YYYYmmdd-HHmmss conserva solo la fecha mas reciente.
 * - Conserva juntos directorio y ZIP de la ultima fecha cuando ambos existen.
 * - Elimina temporales/backups antiguos de mas de 6 horas.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$buildRoot = $root . '/storage/builds';
$apply = in_array('--apply', $argv ?? [], true);

if (!is_dir($buildRoot)) {
    fwrite(STDOUT, "No existe storage/builds. No hay nada que limpiar.\n");
    exit(0);
}

function itemSize(string $path): int
{
    if (is_file($path)) return (int)(filesize($path) ?: 0);
    if (!is_dir($path)) return 0;
    $size = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile()) $size += (int)$file->getSize();
    }
    return $size;
}

function removeTree(string $path): bool
{
    if (is_file($path) || is_link($path)) return @unlink($path);
    if (!is_dir($path)) return true;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) {
            if (!@rmdir($item->getPathname())) return false;
        } else {
            if (!@unlink($item->getPathname())) return false;
        }
    }
    return @rmdir($path);
}

function humanBytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float)$bytes;
    $i = 0;
    while ($value >= 1024 && $i < count($units) - 1) {
        $value /= 1024;
        $i++;
    }
    return number_format($value, $i === 0 ? 0 : 2) . ' ' . $units[$i];
}

$groups = [];
$staleTemp = [];
$now = time();

foreach (new DirectoryIterator($buildRoot) as $item) {
    if ($item->isDot()) continue;
    $name = $item->getFilename();
    $path = $item->getPathname();

    // Temporales y backups de builds interrumpidos. No tocar archivos recientes
    // para evitar interferir con una generacion que pudiera estar en curso.
    if (str_contains($name, '.tmp-') || str_ends_with($name, '.bak')) {
        if (($now - (int)$item->getMTime()) >= 21600) {
            $staleTemp[] = $path;
        }
        continue;
    }

    // Stable builds v1.1.2+ do not include timestamp: always preserve them.
    if (!preg_match('/^(?<prefix>.+)-(?<stamp>\d{8}-\d{6})(?<zip>\.zip)?$/', $name, $m)) {
        continue;
    }

    $prefix = (string)$m['prefix'];
    $stamp = (string)$m['stamp'];
    $groups[$prefix][$stamp][] = $path;
}

$delete = $staleTemp;
$kept = [];

foreach ($groups as $prefix => $versions) {
    krsort($versions, SORT_STRING);
    $latestStamp = array_key_first($versions);
    foreach ($versions as $stamp => $paths) {
        if ($stamp === $latestStamp) {
            foreach ($paths as $path) $kept[] = $path;
            continue;
        }
        foreach ($paths as $path) $delete[] = $path;
    }
}

$delete = array_values(array_unique($delete));
$bytes = 0;
foreach ($delete as $path) $bytes += itemSize($path);

fwrite(STDOUT, "Blumi build cleanup\n");
fwrite(STDOUT, "Ruta: {$buildRoot}\n");
fwrite(STDOUT, "Modo: " . ($apply ? 'APLICAR' : 'SIMULACION') . "\n\n");
fwrite(STDOUT, 'Series legacy detectadas: ' . count($groups) . "\n");
fwrite(STDOUT, 'Elementos de ultima version preservados: ' . count($kept) . "\n");
fwrite(STDOUT, 'Elementos a eliminar: ' . count($delete) . "\n");
fwrite(STDOUT, 'Espacio recuperable aprox.: ' . humanBytes($bytes) . "\n");

if (!$delete) {
    fwrite(STDOUT, "\nNo hay builds historicos para limpiar.\n");
    exit(0);
}

if (!$apply) {
    fwrite(STDOUT, "\nNo se elimino nada. Revisa el resumen y ejecuta:\n");
    fwrite(STDOUT, "php tools/cleanup_builds.php --apply\n");
    exit(0);
}

$removed = 0;
$failed = [];
foreach ($delete as $path) {
    if (removeTree($path)) $removed++;
    else $failed[] = $path;
}

fwrite(STDOUT, "\nLimpieza terminada.\n");
fwrite(STDOUT, "Eliminados: {$removed}\n");
if ($failed) {
    fwrite(STDERR, "No se pudieron eliminar " . count($failed) . " elementos:\n");
    foreach ($failed as $path) fwrite(STDERR, "- {$path}\n");
    exit(1);
}

fwrite(STDOUT, 'Espacio recuperado aprox.: ' . humanBytes($bytes) . "\n");
exit(0);
