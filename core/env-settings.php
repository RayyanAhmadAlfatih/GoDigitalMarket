<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ADMIN ENV SETTINGS HELPER
|--------------------------------------------------------------------------
| Safe .env writer for admin-managed settings such as SMTP and security.
| It preserves existing unknown keys/comments and never adds .env to release ZIP.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('app_env_path')) {
    function app_env_path(): string
    {
        return ROOT_PATH . '/.env';
    }
}

if (!function_exists('app_env_clean_key')) {
    function app_env_clean_key(string $key): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9_]+/', '', strtoupper(trim($key))) ?: '');
    }
}

if (!function_exists('app_env_clean_value')) {
    function app_env_clean_value(string $value, int $max = 1200): string
    {
        $value = trim(str_replace(["\r", "\n", "\0"], ' ', $value));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('app_env_read_lines')) {
    function app_env_read_lines(): array
    {
        $path = app_env_path();
        if (is_file($path)) {
            return file($path, FILE_IGNORE_NEW_LINES) ?: [];
        }
        return [
            '# Auto-generated from admin dashboard. Review before production live.',
            'APP_URL=' . (defined('SITE_URL') ? SITE_URL : ''),
        ];
    }
}

if (!function_exists('app_env_update')) {
    function app_env_update(array $updates): array
    {
        $path = app_env_path();
        $lines = app_env_read_lines();
        $normalized = [];

        foreach ($updates as $key => $value) {
            $cleanKey = app_env_clean_key((string)$key);
            if ($cleanKey === '') {
                continue;
            }
            $normalized[$cleanKey] = app_env_clean_value((string)$value, 2400);
            $_ENV[$cleanKey] = $normalized[$cleanKey];
        }

        if (!$normalized) {
            return ['success' => false, 'message' => 'Tidak ada setting yang perlu disimpan.'];
        }

        $seen = [];
        foreach ($lines as $idx => $line) {
            $trim = trim((string)$line);
            if ($trim === '' || str_starts_with($trim, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key] = explode('=', $line, 2);
            $cleanKey = app_env_clean_key($key);
            if ($cleanKey !== '' && array_key_exists($cleanKey, $normalized)) {
                $lines[$idx] = $cleanKey . '=' . $normalized[$cleanKey];
                $seen[$cleanKey] = true;
            }
        }

        $missing = array_diff_key($normalized, $seen);
        if ($missing) {
            $lines[] = '';
            $lines[] = '# Managed from admin dashboard';
            foreach ($missing as $key => $value) {
                $lines[] = $key . '=' . $value;
            }
        }

        $data = implode(PHP_EOL, $lines) . PHP_EOL;
        $ok = @file_put_contents($path, $data, LOCK_EX) !== false;
        if ($ok) {
            @chmod($path, 0600);
        }

        return [
            'success' => $ok,
            'message' => $ok ? 'Pengaturan berhasil disimpan ke file .env.' : 'Gagal menulis file .env. Pastikan folder website writable oleh PHP.',
            'path' => $path,
        ];
    }
}

if (!function_exists('app_env_mask_secret')) {
    function app_env_mask_secret(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Belum diisi';
        }
        $len = strlen($value);
        if ($len <= 6) {
            return str_repeat('•', $len);
        }
        return substr($value, 0, 2) . str_repeat('•', max(4, $len - 4)) . substr($value, -2);
    }
}
