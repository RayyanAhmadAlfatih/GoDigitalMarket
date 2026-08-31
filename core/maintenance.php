<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| Template ADMIN MAINTENANCE ENGINE
|--------------------------------------------------------------------------
| Helper ringan untuk backup export, cache cleanup, dan ringkasan storage.
| Semua backup sengaja tidak memasukkan .env agar secret live tidak ikut
| terunduh/tersebar.
|--------------------------------------------------------------------------
*/

if (!function_exists('maintenance_readable_size')) {
    function maintenance_readable_size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = max(0, $bytes);
        $index = 0;

        while ($size >= 1024 && $index < count($units) - 1) {
            $size = $size / 1024;
            $index++;
        }

        return number_format($size, $index === 0 ? 0 : 1) . ' ' . $units[$index];
    }
}

if (!function_exists('maintenance_backup_dir')) {
    function maintenance_backup_dir(): string
    {
        return CACHE_PATH . '/maintenance-backups';
    }
}

if (!function_exists('maintenance_normalize_path')) {
    function maintenance_normalize_path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}

if (!function_exists('maintenance_relative_path')) {
    function maintenance_relative_path(string $path): string
    {
        $root = rtrim(maintenance_normalize_path(ROOT_PATH), '/') . '/';
        $normalized = maintenance_normalize_path($path);

        if (str_starts_with($normalized, $root)) {
            return ltrim(substr($normalized, strlen($root)), '/');
        }

        return basename($normalized);
    }
}

if (!function_exists('maintenance_ensure_backup_dir')) {
    function maintenance_ensure_backup_dir(): bool
    {
        $dir = maintenance_backup_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir)) {
            @chmod($dir, 0775);
        }
        return is_dir($dir) && is_writable($dir);
    }
}

if (!function_exists('maintenance_collect_files')) {
    function maintenance_collect_files(): array
    {
        $roots = [
            STORAGE_PATH,
            LOGS_PATH,
            ASSETS_PATH . '/uploads',
        ];

        $files = [];
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (!$item instanceof SplFileInfo || !$item->isFile()) {
                    continue;
                }

                $path = $item->getPathname();
                $relative = maintenance_relative_path($path);

                if (str_contains($relative, 'maintenance-backups/')) {
                    continue;
                }
                if (basename($relative) === '.htaccess') {
                    continue;
                }
                if (in_array(basename($relative), ['payment-gateway-settings.json', 'analytics-settings.json', 'marketing-integrations.json', 'server-conversion-settings.json', 'google-ads-api-credentials.json'], true)) {
                    continue;
                }

                $files[] = $path;
            }
        }

        sort($files);
        return $files;
    }
}

if (!function_exists('maintenance_directory_stats')) {
    function maintenance_directory_stats(string $path): array
    {
        $files = 0;
        $size = 0;
        $exists = is_dir($path);

        if ($exists) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if ($item instanceof SplFileInfo && $item->isFile()) {
                    $files++;
                    $size += (int)$item->getSize();
                }
            }
        }

        return [
            'path' => $path,
            'exists' => $exists,
            'writable' => $exists && is_writable($path),
            'files' => $files,
            'size_bytes' => $size,
            'size_label' => maintenance_readable_size($size),
        ];
    }
}

if (!function_exists('maintenance_storage_overview')) {
    function maintenance_storage_overview(): array
    {
        $paths = [
            'storage' => STORAGE_PATH,
            'logs' => LOGS_PATH,
            'cache' => CACHE_PATH,
            'uploads' => ASSETS_PATH . '/uploads',
            'backups' => maintenance_backup_dir(),
        ];

        $overview = [];
        foreach ($paths as $key => $path) {
            $overview[$key] = maintenance_directory_stats($path);
        }

        return $overview;
    }
}

if (!function_exists('maintenance_backup_manifest')) {
    function maintenance_backup_manifest(array $files): array
    {
        $items = [];
        $total = 0;

        foreach ($files as $file) {
            $size = is_file($file) ? (int)@filesize($file) : 0;
            $total += $size;
            $items[] = [
                'path' => maintenance_relative_path((string)$file),
                'size_bytes' => $size,
                'modified_at' => is_file($file) ? date('c', (int)@filemtime($file)) : null,
            ];
        }

        return [
            'generated_at' => date('c'),
            'site_url' => SITE_URL,
            'app_version' => 'Template',
            'note' => 'Backup admin maintenance. File .env, payment-gateway-settings.json, analytics-settings.json, marketing-integrations.json, server-conversion-settings.json, dan google-ads-api-credentials.json tidak disertakan agar secret live tidak ikut terbawa.',
            'files_count' => count($files),
            'total_size_bytes' => $total,
            'files' => $items,
        ];
    }
}


if (!function_exists('maintenance_zip_dos_time')) {
    function maintenance_zip_dos_time(int $timestamp): array
    {
        $date = getdate($timestamp > 0 ? $timestamp : time());
        $year = max(1980, (int)$date['year']);
        $dosTime = ((int)$date['hours'] << 11) | ((int)$date['minutes'] << 5) | ((int)floor((int)$date['seconds'] / 2));
        $dosDate = (($year - 1980) << 9) | ((int)$date['mon'] << 5) | (int)$date['mday'];
        return [$dosTime, $dosDate];
    }
}

if (!function_exists('maintenance_zip_add_store_entry')) {
    function maintenance_zip_add_store_entry($handle, string $entryName, string $data, int $timestamp, array &$central): bool
    {
        if (!is_resource($handle)) {
            return false;
        }

        $entryName = ltrim(str_replace('\\', '/', $entryName), '/');
        if ($entryName === '') {
            return false;
        }

        $offset = ftell($handle);
        if ($offset === false) {
            return false;
        }

        [$dosTime, $dosDate] = maintenance_zip_dos_time($timestamp);
        $size = strlen($data);
        $crc = (int)sprintf('%u', crc32($data));
        $nameLength = strlen($entryName);

        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0
        );

        fwrite($handle, $localHeader);
        fwrite($handle, $entryName);
        fwrite($handle, $data);

        $central[] = [
            'name' => $entryName,
            'crc' => $crc,
            'size' => $size,
            'time' => $dosTime,
            'date' => $dosDate,
            'offset' => (int)$offset,
        ];

        return true;
    }
}

if (!function_exists('maintenance_create_zip_store')) {
    function maintenance_create_zip_store(string $target, array $files, array $manifest): bool
    {
        $handle = @fopen($target, 'wb');
        if (!$handle) {
            return false;
        }

        $central = [];
        foreach ($files as $file) {
            if (!is_file((string)$file) || !is_readable((string)$file)) {
                continue;
            }
            $data = (string)@file_get_contents((string)$file);
            maintenance_zip_add_store_entry($handle, maintenance_relative_path((string)$file), $data, (int)@filemtime((string)$file), $central);
        }

        maintenance_zip_add_store_entry(
            $handle,
            'backup-manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            time(),
            $central
        );

        $centralOffset = ftell($handle);
        if ($centralOffset === false) {
            fclose($handle);
            return false;
        }

        foreach ($central as $entry) {
            $name = (string)$entry['name'];
            $nameLength = strlen($name);
            $centralHeader = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                (int)$entry['time'],
                (int)$entry['date'],
                (int)$entry['crc'],
                (int)$entry['size'],
                (int)$entry['size'],
                $nameLength,
                0,
                0,
                0,
                0,
                32,
                (int)$entry['offset']
            );
            fwrite($handle, $centralHeader);
            fwrite($handle, $name);
        }

        $centralEnd = ftell($handle);
        if ($centralEnd === false) {
            fclose($handle);
            return false;
        }

        $centralSize = $centralEnd - $centralOffset;
        $count = count($central);
        fwrite($handle, pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, $centralSize, $centralOffset, 0));
        fclose($handle);

        return is_file($target) && (int)@filesize($target) > 0;
    }
}

if (!function_exists('maintenance_create_backup')) {
    function maintenance_create_backup(): array
    {
        if (!maintenance_ensure_backup_dir()) {
            return [
                'success' => false,
                'message' => 'Folder backup belum writable: ' . maintenance_backup_dir(),
            ];
        }

        $files = maintenance_collect_files();
        $filename = 'backup-data-' . date('Ymd-His') . '.zip';
        $target = maintenance_backup_dir() . '/' . $filename;

        $manifest = maintenance_backup_manifest($files);

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                return [
                    'success' => false,
                    'message' => 'Gagal membuat file backup ZIP.',
                ];
            }

            foreach ($files as $file) {
                if (!is_file($file) || !is_readable($file)) {
                    continue;
                }
                $zip->addFile($file, maintenance_relative_path($file));
            }

            $zip->addFromString('backup-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
            $zip->close();
        } else {
            maintenance_create_zip_store($target, $files, $manifest);
        }

        return [
            'success' => is_file($target),
            'message' => is_file($target) ? 'Backup berhasil dibuat.' : 'Backup gagal dibuat.',
            'path' => $target,
            'filename' => $filename,
            'files_count' => count($files),
            'size_bytes' => is_file($target) ? (int)@filesize($target) : 0,
        ];
    }
}

if (!function_exists('maintenance_recent_backups')) {
    function maintenance_recent_backups(int $limit = 10): array
    {
        $rows = [];
        foreach (glob(maintenance_backup_dir() . '/*.zip') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $rows[] = [
                'filename' => basename($file),
                'path' => $file,
                'size_bytes' => (int)@filesize($file),
                'created_at' => date('Y-m-d H:i:s', (int)@filemtime($file)),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']));
        return array_slice($rows, 0, max(1, $limit));
    }
}

if (!function_exists('maintenance_clear_cache_files')) {
    function maintenance_clear_cache_files(): int
    {
        $deleted = 0;
        if (!is_dir(CACHE_PATH)) {
            return 0;
        }

        foreach (glob(CACHE_PATH . '/*') ?: [] as $file) {
            if (is_dir($file) && basename($file) === 'maintenance-backups') {
                continue;
            }
            if (is_file($file) && @unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
