<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| FIRST RUN INSTALLER HELPER
|--------------------------------------------------------------------------
| A safe setup layer for fresh uploads. The installer only opens while the
| owner login is not ready, or when an authenticated owner explicitly opens it.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('first_run_state_file')) {
    function first_run_state_file(): string
    {
        return STORAGE_PATH . '/first-run-setup.json';
    }
}

if (!function_exists('first_run_clean_text')) {
    function first_run_clean_text(mixed $value, int $max = 180): string
    {
        $value = trim(strip_tags((string)$value));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('first_run_bool_env')) {
    function first_run_bool_env(string $key, bool $default = false): bool
    {
        $value = strtolower(trim((string)($_ENV[$key] ?? ($default ? 'true' : 'false'))));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('first_run_has_owner_user')) {
    function first_run_has_owner_user(): bool
    {
        if (!function_exists('admin_users_read_all')) {
            return false;
        }

        foreach (admin_users_read_all() as $row) {
            if ((string)($row['role'] ?? '') === 'owner' && (string)($row['status'] ?? 'active') === 'active' && (string)($row['password_hash'] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('first_run_owner_auth_ready')) {
    function first_run_owner_auth_ready(): bool
    {
        if (first_run_has_owner_user()) {
            return true;
        }

        if (function_exists('admin_auth_password_needs_setup')) {
            return !admin_auth_password_needs_setup();
        }

        $password = trim((string)($_ENV['ADMIN_PASSWORD'] ?? ''));
        return $password !== '' && (!function_exists('admin_password_needs_setup') || !admin_password_needs_setup($password));
    }
}

if (!function_exists('first_run_installer_open')) {
    function first_run_installer_open(): bool
    {
        // Once an owner exists, installer access is owner-only.
        if (first_run_owner_auth_ready()) {
            return function_exists('admin_auth_is_logged_in')
                && admin_auth_is_logged_in()
                && function_exists('admin_users_current_role')
                && admin_users_current_role() === 'owner';
        }

        // Local development may bootstrap without a token.
        if (defined('APP_ENV') && APP_ENV !== 'production' && function_exists('app_is_localhost') && app_is_localhost()) {
            return true;
        }

        // Production bootstrap must be explicitly enabled and protected by a strong secret.
        if (!first_run_bool_env('INSTALLER_ENABLED', false)) {
            return false;
        }

        $expected = trim((string)($_ENV['INSTALLER_TOKEN'] ?? ''));
        if (strlen($expected) < 24) {
            return false;
        }

        $provided = trim((string)(
            $_SERVER['HTTP_X_INSTALLER_TOKEN']
            ?? $_GET['setup_token']
            ?? $_POST['setup_token']
            ?? $_SESSION['first_run_installer_token']
            ?? ''
        ));

        if ($provided === '' || !hash_equals($expected, $provided)) {
            return false;
        }

        $_SESSION['first_run_installer_token'] = $provided;
        return true;
    }
}

if (!function_exists('first_run_is_completed')) {
    function first_run_is_completed(): bool
    {
        return first_run_owner_auth_ready();
    }
}

if (!function_exists('first_run_check')) {
    function first_run_check(string $id, string $label, bool $ok, string $note = '', string $level = 'required'): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'ok' => $ok,
            'status' => $ok ? 'ok' : ($level === 'optional' ? 'warning' : 'error'),
            'level' => $level,
            'note' => $note,
        ];
    }
}

if (!function_exists('first_run_is_writable_target')) {
    function first_run_is_writable_target(string $path): bool
    {
        if (is_dir($path)) {
            return is_writable($path);
        }

        $dir = dirname($path);
        if (is_file($path)) {
            return is_writable($path);
        }

        return is_dir($dir) && is_writable($dir);
    }
}

if (!function_exists('first_run_readiness_checks')) {
    function first_run_readiness_checks(): array
    {
        $envPath = function_exists('app_env_path') ? app_env_path() : ROOT_PATH . '/.env';

        $checks = [
            first_run_check('php-version', 'PHP 8.1+', version_compare(PHP_VERSION, '8.1.0', '>='), 'Versi terdeteksi: ' . PHP_VERSION),
            first_run_check('storage-writable', 'Folder storage writable', first_run_is_writable_target(STORAGE_PATH), 'Dipakai untuk pengaturan, konten file-based, dan fallback.'),
            first_run_check('logs-writable', 'Folder logs writable', first_run_is_writable_target(LOGS_PATH), 'Dipakai untuk log aman dan audit sistem.'),
            first_run_check('cache-writable', 'Folder cache writable', first_run_is_writable_target(CACHE_PATH), 'Dipakai untuk cache ringan dan file sementara.'),
            first_run_check('uploads-writable', 'Folder upload writable', first_run_is_writable_target(ASSETS_PATH . '/uploads'), 'Dipakai untuk logo, gambar produk, media, dan file form.'),
            first_run_check('env-writable', '.env bisa dibuat/ditulis', first_run_is_writable_target($envPath), 'Installer perlu menulis APP_URL dan owner login.'),
            first_run_check('database-sql', 'database.sql tersedia', is_file(ROOT_PATH . '/database.sql'), 'Dipakai saat website ingin memakai MySQL.'),
            first_run_check('mysql-storage-schema', 'Schema runtime MySQL tersedia', is_file(ROOT_PATH . '/database/mysql-storage-schema.sql'), 'Dipakai untuk migrasi runtime bertahap.'),
            first_run_check('openssl', 'Extension OpenSSL aktif', extension_loaded('openssl'), 'Dipakai untuk token, password, dan proses keamanan.'),
            first_run_check('json', 'Extension JSON aktif', extension_loaded('json'), 'Dipakai hampir semua storage dan API.'),
            first_run_check('pdo-mysql', 'PDO MySQL tersedia', extension_loaded('pdo_mysql'), 'Wajib jika nanti mengaktifkan runtime MySQL.', 'optional'),
            first_run_check('gd-or-imagick', 'GD/Imagick tersedia', extension_loaded('gd') || extension_loaded('imagick'), 'Direkomendasikan untuk upload dan optimasi gambar.', 'optional'),
            first_run_check('https', 'HTTPS aktif', app_is_https() || app_is_localhost(), app_is_localhost() ? 'Localhost boleh HTTP saat testing.' : 'Aktifkan SSL sebelum live production.', 'optional'),
        ];

        $requiredOk = true;
        foreach ($checks as $check) {
            if (($check['level'] ?? 'required') === 'required' && empty($check['ok'])) {
                $requiredOk = false;
                break;
            }
        }

        return [
            'checks' => $checks,
            'required_ok' => $requiredOk,
            'completed' => first_run_is_completed(),
            'installer_open' => first_run_installer_open(),
        ];
    }
}

if (!function_exists('first_run_password_strength')) {
    function first_run_password_strength(string $password): array
    {
        $password = trim($password);
        $score = 0;
        $notes = [];

        if (strlen($password) >= 10) { $score += 2; } else { $notes[] = 'minimal 10 karakter'; }
        if (preg_match('/[A-Z]/', $password)) { $score++; } else { $notes[] = 'huruf besar'; }
        if (preg_match('/[a-z]/', $password)) { $score++; } else { $notes[] = 'huruf kecil'; }
        if (preg_match('/[0-9]/', $password)) { $score++; } else { $notes[] = 'angka'; }
        if (preg_match('/[^A-Za-z0-9]/', $password)) { $score++; } else { $notes[] = 'simbol'; }

        if (function_exists('admin_password_needs_setup') && admin_password_needs_setup($password)) {
            $score = 0;
            $notes[] = 'jangan memakai password default';
        }

        return [
            'ok' => $score >= 5,
            'score' => min(6, $score),
            'message' => $score >= 5 ? 'Password cukup kuat.' : 'Password perlu: ' . implode(', ', array_unique($notes)) . '.',
        ];
    }
}

if (!function_exists('first_run_save_state')) {
    function first_run_save_state(array $state): void
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }

        $state = array_merge([
            'installed_at' => date(DATE_ATOM),
            'updated_at' => date(DATE_ATOM),
            'completed' => true,
            'app_url' => defined('BASE_URL') ? BASE_URL : '',
            'business_name' => defined('SITE_NAME') ? SITE_NAME : '',
            'owner_login' => '',
        ], $state);

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            @file_put_contents(first_run_state_file(), $json . PHP_EOL, LOCK_EX);
            @chmod(first_run_state_file(), 0644);
        }
    }
}

if (!function_exists('first_run_install')) {
    function first_run_install(array $input): array
    {
        if (!first_run_installer_open()) {
            return ['ok' => false, 'message' => 'Installer sudah terkunci. Login sebagai owner untuk membuka pengaturan awal.'];
        }

        $readiness = first_run_readiness_checks();
        if (empty($readiness['required_ok'])) {
            return ['ok' => false, 'message' => 'Perbaiki checklist wajib terlebih dulu, terutama permission folder dan .env.'];
        }

        $appUrl = trim((string)($input['app_url'] ?? BASE_URL));
        if ($appUrl === '' || !filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $appUrl)) {
            return ['ok' => false, 'message' => 'Isi URL website yang valid, contoh https://domain-anda.com.'];
        }
        $appUrl = rtrim($appUrl, '/');

        $businessName = first_run_clean_text($input['business_name'] ?? SITE_NAME, 90);
        $businessName = $businessName !== '' ? $businessName : 'Website UMKM';
        $email = strtolower(trim((string)($input['email'] ?? SITE_EMAIL)));
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $whatsapp = preg_replace('/\D+/', '', (string)($input['whatsapp'] ?? SITE_WHATSAPP)) ?: '';
        $themePreset = first_run_clean_text($input['theme_preset'] ?? 'biru-profesional', 60);
        if (!function_exists('theme_presets') || !isset(theme_presets()[$themePreset])) {
            $themePreset = 'biru-profesional';
        }

        $ownerName = first_run_clean_text($input['owner_name'] ?? 'Owner', 90) ?: 'Owner';
        $ownerEmail = strtolower(trim((string)($input['owner_email'] ?? $email)));
        $ownerEmail = filter_var($ownerEmail, FILTER_VALIDATE_EMAIL) ? $ownerEmail : '';
        $ownerUsername = function_exists('admin_users_username') ? admin_users_username((string)($input['owner_username'] ?? 'owner')) : 'owner';
        if ($ownerEmail === '' && $ownerUsername === '') {
            $ownerUsername = 'owner';
        }

        $password = (string)($input['owner_password'] ?? '');
        $confirm = (string)($input['owner_password_confirm'] ?? '');
        if ($password === '' && first_run_owner_auth_ready()) {
            $password = $confirm = '';
        } elseif (!hash_equals($password, $confirm)) {
            return ['ok' => false, 'message' => 'Konfirmasi password owner belum sama.'];
        } else {
            $strength = first_run_password_strength($password);
            if (empty($strength['ok'])) {
                return ['ok' => false, 'message' => (string)$strength['message']];
            }
        }

        $envUpdates = [
            'APP_URL' => $appUrl,
            'ADMIN_SESSION_TIMEOUT' => (string)max(900, min((int)($input['admin_session_timeout'] ?? 7200), 86400)),
            'INSTALLER_ENABLED' => 'false',
        ];

        if ($email !== '') {
            $envUpdates['EMAIL_ADMIN_TO'] = $email;
            $envUpdates['EMAIL_FROM'] = $email;
        }

        $envResult = function_exists('app_env_update') ? app_env_update($envUpdates) : ['success' => false, 'message' => 'Env writer belum tersedia.'];
        if (empty($envResult['success'])) {
            return ['ok' => false, 'message' => (string)($envResult['message'] ?? 'Gagal menyimpan .env.')];
        }

        if (function_exists('theme_save_settings')) {
            try {
                $themeInput = array_merge(theme_settings(), [
                    'business_name' => $businessName,
                    'email' => $email !== '' ? $email : SITE_EMAIL,
                    'whatsapp' => $whatsapp !== '' ? $whatsapp : SITE_WHATSAPP,
                    'phone' => $whatsapp !== '' ? $whatsapp : SITE_PHONE,
                    'theme_preset' => $themePreset,
                    'login_footer_note' => 'Dashboard resmi {business_name}',
                ]);

                if (function_exists('theme_apply_preset')) {
                    $themeInput = theme_apply_preset($themeInput, $themePreset);
                }

                theme_save_settings($themeInput);
            } catch (Throwable $e) {
                return ['ok' => false, 'message' => 'Brand awal belum bisa disimpan: ' . $e->getMessage()];
            }
        }

        $ownerLogin = $ownerEmail !== '' ? $ownerEmail : $ownerUsername;
        if ($password !== '' && function_exists('admin_users_save_record')) {
            $existingId = null;
            if (function_exists('admin_users_find_by_login')) {
                $existing = $ownerLogin !== '' ? admin_users_find_by_login($ownerLogin) : null;
                $existingId = is_array($existing) ? (string)($existing['id'] ?? '') : null;
            }

            $saved = admin_users_save_record([
                'name' => $ownerName,
                'email' => $ownerEmail,
                'username' => $ownerUsername,
                'role' => 'owner',
                'status' => 'active',
                'password' => $password,
                'notes' => 'Akun owner utama dibuat dari First Run Setup.',
            ], $existingId ?: null);

            if (empty($saved['ok'])) {
                return ['ok' => false, 'message' => (string)($saved['message'] ?? 'Gagal membuat akun owner.')];
            }
        }

        first_run_save_state([
            'updated_at' => date(DATE_ATOM),
            'completed' => first_run_owner_auth_ready() || $password !== '',
            'app_url' => $appUrl,
            'business_name' => $businessName,
            'owner_login' => $ownerLogin,
            'php_version' => PHP_VERSION,
        ]);

        if (function_exists('activity_log_record')) {
            activity_log_record('first_run_setup', 'system', null, 'Pengaturan awal website diselesaikan.', ['app_url' => $appUrl, 'owner_login' => $ownerLogin]);
        }

        return ['ok' => true, 'message' => 'Setup awal berhasil disimpan. Silakan login memakai akun owner yang baru dibuat.', 'owner_login' => $ownerLogin];
    }
}
