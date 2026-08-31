<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| GOOGLE ADS API SECURE CREDENTIAL VAULT - Template
|--------------------------------------------------------------------------
| Local, admin-only credential storage foundation for Google Ads API.
| Secrets are encrypted at rest when openssl is available. The vault never
| exposes raw developer token, client secret, or refresh token in admin UI.
| Menyimpan data koneksi Google Ads untuk kebutuhan tracking lanjutan.
|--------------------------------------------------------------------------
*/

if (!function_exists('google_ads_vault_file')) {
    function google_ads_vault_file(): string
    {
        return STORAGE_PATH . '/google-ads-api-credentials.json';
    }
}

if (!function_exists('google_ads_vault_clean')) {
    function google_ads_vault_clean(string $value, int $max = 240): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$value) ?: '';
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        $value = trim($value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('google_ads_vault_secret_clean')) {
    function google_ads_vault_secret_clean(string $value, int $max = 2000): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/', '', (string)$value) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('google_ads_vault_id_clean')) {
    function google_ads_vault_id_clean(string $value, int $max = 180): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9_\.\-:\/]/', '', (string)$value) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('google_ads_vault_digits_clean')) {
    function google_ads_vault_digits_clean(string $value, int $max = 30): string
    {
        $value = preg_replace('/[^0-9]/', '', trim($value)) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('google_ads_vault_mask')) {
    function google_ads_vault_mask(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }
        $len = strlen($secret);
        if ($len <= 10) {
            return str_repeat('•', max(6, $len));
        }
        return substr($secret, 0, 5) . str_repeat('•', 8) . substr($secret, -4);
    }
}

if (!function_exists('google_ads_vault_key_material')) {
    function google_ads_vault_key_material(): string
    {
        $envKey = (string)($_ENV['GOOGLE_ADS_VAULT_KEY'] ?? '');
        if (trim($envKey) !== '') {
            return $envKey;
        }
        $appKey = (string)($_ENV['APP_KEY'] ?? '');
        if (trim($appKey) !== '') {
            return $appKey;
        }
        return 'pusat-produk/layanan-produk-layanan-google-ads-vault-local-fallback';
    }
}

if (!function_exists('google_ads_vault_encrypt')) {
    function google_ads_vault_encrypt(string $plain): array
    {
        $plain = google_ads_vault_secret_clean($plain);
        if ($plain === '') {
            return ['value' => '', 'encrypted' => true, 'alg' => 'aes-256-gcm', 'iv' => '', 'tag' => ''];
        }

        if (function_exists('openssl_encrypt')) {
            $key = hash('sha256', google_ads_vault_key_material(), true);
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher !== false && $tag !== '') {
                return [
                    'value' => base64_encode($cipher),
                    'encrypted' => true,
                    'alg' => 'aes-256-gcm',
                    'iv' => base64_encode($iv),
                    'tag' => base64_encode($tag),
                ];
            }
        }

        // Last-resort compatibility fallback for hosts without openssl.
        // It keeps the file non-plain-text, but halaman Cek Sistem akan memberi peringatan.
        return [
            'value' => base64_encode($plain),
            'encrypted' => false,
            'alg' => 'base64-fallback',
            'iv' => '',
            'tag' => '',
        ];
    }
}

if (!function_exists('google_ads_vault_decrypt')) {
    function google_ads_vault_decrypt(array $secret): string
    {
        $value = (string)($secret['value'] ?? '');
        if ($value === '') {
            return '';
        }
        $alg = (string)($secret['alg'] ?? '');
        if (!empty($secret['encrypted']) && $alg === 'aes-256-gcm' && function_exists('openssl_decrypt')) {
            $key = hash('sha256', google_ads_vault_key_material(), true);
            $cipher = base64_decode($value, true);
            $iv = base64_decode((string)($secret['iv'] ?? ''), true);
            $tag = base64_decode((string)($secret['tag'] ?? ''), true);
            if ($cipher !== false && $iv !== false && $tag !== false) {
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
                return $plain !== false ? google_ads_vault_secret_clean($plain) : '';
            }
        }
        if ($alg === 'base64-fallback') {
            $plain = base64_decode($value, true);
            return $plain !== false ? google_ads_vault_secret_clean($plain) : '';
        }
        return '';
    }
}

if (!function_exists('google_ads_vault_default')) {
    function google_ads_vault_default(): array
    {
        return [
            'enabled' => false,
            'api_version' => '',
            'login_customer_id' => '',
            'notes' => '',
            'secrets' => [
                'developer_token' => google_ads_vault_encrypt(''),
                'client_id' => google_ads_vault_encrypt(''),
                'client_secret' => google_ads_vault_encrypt(''),
                'refresh_token' => google_ads_vault_encrypt(''),
            ],
            'meta' => [
                'created_at' => null,
                'updated_at' => null,
                'last_rotated_at' => null,
            ],
        ];
    }
}

if (!function_exists('google_ads_vault_normalize_api_version')) {
    function google_ads_vault_normalize_api_version(string $value): string
    {
        $value = strtolower(google_ads_vault_id_clean($value, 12));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^[0-9]{1,3}$/', $value)) {
            $value = 'v' . $value;
        }
        return preg_match('/^v[0-9]{1,3}$/', $value) ? $value : '';
    }
}

if (!function_exists('google_ads_vault_read_raw')) {
    function google_ads_vault_read_raw(): array
    {
        $file = google_ads_vault_file();
        if (!is_file($file)) {
            return google_ads_vault_default();
        }
        $decoded = json_decode((string)@file_get_contents($file), true);
        $data = is_array($decoded) ? $decoded : [];
        $default = google_ads_vault_default();
        $data = array_replace_recursive($default, $data);
        $data['enabled'] = !empty($data['enabled']);
        $data['api_version'] = google_ads_vault_normalize_api_version((string)($data['api_version'] ?? ''));
        $data['login_customer_id'] = google_ads_vault_digits_clean((string)($data['login_customer_id'] ?? ''), 20);
        $data['notes'] = google_ads_vault_clean((string)($data['notes'] ?? ''), 500);
        foreach (['developer_token', 'client_id', 'client_secret', 'refresh_token'] as $key) {
            if (!is_array($data['secrets'][$key] ?? null)) {
                $data['secrets'][$key] = google_ads_vault_encrypt('');
            }
        }
        $data['meta'] = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $data['meta']['created_at'] = google_ads_vault_clean((string)($data['meta']['created_at'] ?? ''), 40) ?: null;
        $data['meta']['updated_at'] = google_ads_vault_clean((string)($data['meta']['updated_at'] ?? ''), 40) ?: null;
        $data['meta']['last_rotated_at'] = google_ads_vault_clean((string)($data['meta']['last_rotated_at'] ?? ''), 40) ?: null;
        return $data;
    }
}

if (!function_exists('google_ads_vault_write_raw')) {
    function google_ads_vault_write_raw(array $vault): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        $vault['enabled'] = !empty($vault['enabled']);
        $vault['api_version'] = google_ads_vault_normalize_api_version((string)($vault['api_version'] ?? ''));
        $vault['login_customer_id'] = google_ads_vault_digits_clean((string)($vault['login_customer_id'] ?? ''), 20);
        $vault['notes'] = google_ads_vault_clean((string)($vault['notes'] ?? ''), 500);
        $vault['meta'] = is_array($vault['meta'] ?? null) ? $vault['meta'] : [];
        if (empty($vault['meta']['created_at'])) {
            $vault['meta']['created_at'] = date('c');
        }
        $vault['meta']['updated_at'] = date('c');
        $ok = @file_put_contents(
            google_ads_vault_file(),
            json_encode($vault, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        ) !== false;
        if ($ok) {
            @chmod(google_ads_vault_file(), 0660);
        }
        return $ok;
    }
}

if (!function_exists('google_ads_vault_upsert_from_post')) {
    function google_ads_vault_upsert_from_post(array $post): bool
    {
        $current = google_ads_vault_read_raw();
        $now = date('c');
        $vault = $current;
        $vault['enabled'] = !empty($post['google_ads_api_vault_enabled']);
        $vault['api_version'] = google_ads_vault_normalize_api_version((string)($post['google_ads_api_version'] ?? ($current['api_version'] ?? '')));
        $vault['login_customer_id'] = google_ads_vault_digits_clean((string)($post['google_ads_login_customer_id'] ?? ($current['login_customer_id'] ?? '')), 20);
        $vault['notes'] = google_ads_vault_clean((string)($post['google_ads_api_notes'] ?? ($current['notes'] ?? '')), 500);
        $vault['secrets'] = is_array($current['secrets'] ?? null) ? $current['secrets'] : google_ads_vault_default()['secrets'];

        $rotated = false;
        foreach ([
            'developer_token' => 'google_ads_developer_token',
            'client_id' => 'google_ads_oauth_client_id',
            'client_secret' => 'google_ads_oauth_client_secret',
            'refresh_token' => 'google_ads_refresh_token',
        ] as $secretKey => $postKey) {
            $incoming = google_ads_vault_secret_clean((string)($post[$postKey] ?? ''));
            if ($incoming !== '') {
                $vault['secrets'][$secretKey] = google_ads_vault_encrypt($incoming);
                $rotated = true;
            }
        }
        $vault['meta'] = is_array($current['meta'] ?? null) ? $current['meta'] : [];
        if (empty($vault['meta']['created_at'])) {
            $vault['meta']['created_at'] = $now;
        }
        if ($rotated) {
            $vault['meta']['last_rotated_at'] = $now;
        }
        return google_ads_vault_write_raw($vault);
    }
}

if (!function_exists('google_ads_vault_clear')) {
    function google_ads_vault_clear(): bool
    {
        $file = google_ads_vault_file();
        if (is_file($file)) {
            return @unlink($file);
        }
        return true;
    }
}

if (!function_exists('google_ads_vault_secret_status')) {
    function google_ads_vault_secret_status(array $vault, string $key): array
    {
        $secret = is_array($vault['secrets'][$key] ?? null) ? (array)$vault['secrets'][$key] : google_ads_vault_encrypt('');
        $plain = google_ads_vault_decrypt($secret);
        return [
            'set' => $plain !== '',
            'mask' => google_ads_vault_mask($plain),
            'encrypted' => !empty($secret['encrypted']) && (string)($secret['alg'] ?? '') === 'aes-256-gcm',
            'alg' => (string)($secret['alg'] ?? ''),
        ];
    }
}

if (!function_exists('google_ads_vault_status')) {
    function google_ads_vault_status(?array $serverSettings = null): array
    {
        $vault = google_ads_vault_read_raw();
        $serverSettings = $serverSettings ?: (function_exists('server_conversion_read_settings') ? server_conversion_read_settings() : []);
        $googleAds = (array)($serverSettings['google_ads'] ?? []);
        $statuses = [];
        foreach (['developer_token', 'client_id', 'client_secret', 'refresh_token'] as $key) {
            $statuses[$key] = google_ads_vault_secret_status($vault, $key);
        }
        $developerReady = !empty($statuses['developer_token']['set']);
        $oauthReady = !empty($statuses['client_id']['set']) && !empty($statuses['client_secret']['set']) && !empty($statuses['refresh_token']['set']);
        $customerReady = google_ads_vault_digits_clean((string)($googleAds['customer_id'] ?? ''), 20) !== '';
        $conversionReady = google_ads_vault_id_clean((string)($googleAds['conversion_action_id'] ?? ''), 160) !== '';
        $mappingReady = false;
        if (function_exists('server_conversion_google_ads_mapping_summary')) {
            $mapping = server_conversion_google_ads_mapping_summary($serverSettings);
            $mappingReady = (int)($mapping['ready_count'] ?? 0) > 0;
        }
        $apiReady = !empty($vault['enabled']) && $developerReady && $oauthReady;
        $senderPrereqReady = $apiReady && $customerReady && ($conversionReady || $mappingReady);
        $encryptedAll = true;
        foreach ($statuses as $status) {
            if (!empty($status['set']) && empty($status['encrypted'])) {
                $encryptedAll = false;
            }
        }

        return [
            'enabled' => !empty($vault['enabled']),
            'vault_file_exists' => is_file(google_ads_vault_file()),
            'vault_file' => google_ads_vault_file(),
            'api_version' => (string)($vault['api_version'] ?? ''),
            'login_customer_id' => (string)($vault['login_customer_id'] ?? ''),
            'login_customer_id_set' => (string)($vault['login_customer_id'] ?? '') !== '',
            'developer_token_set' => $developerReady,
            'oauth_client_id_set' => !empty($statuses['client_id']['set']),
            'oauth_client_secret_set' => !empty($statuses['client_secret']['set']),
            'refresh_token_set' => !empty($statuses['refresh_token']['set']),
            'developer_token_mask' => (string)($statuses['developer_token']['mask'] ?? ''),
            'client_id_mask' => (string)($statuses['client_id']['mask'] ?? ''),
            'client_secret_mask' => (string)($statuses['client_secret']['mask'] ?? ''),
            'refresh_token_mask' => (string)($statuses['refresh_token']['mask'] ?? ''),
            'oauth_ready' => $oauthReady,
            'developer_ready' => $developerReady,
            'api_ready' => $apiReady,
            'sender_prereq_ready' => $senderPrereqReady,
            'customer_ready' => $customerReady,
            'conversion_ready' => $conversionReady || $mappingReady,
            'encrypted_all' => $encryptedAll,
            'openssl_available' => function_exists('openssl_encrypt') && function_exists('openssl_decrypt'),
            'notes' => (string)($vault['notes'] ?? ''),
            'created_at' => $vault['meta']['created_at'] ?? null,
            'updated_at' => $vault['meta']['updated_at'] ?? null,
            'last_rotated_at' => $vault['meta']['last_rotated_at'] ?? null,
            'status_label' => empty($vault['enabled'])
                ? 'Penyimpanan Nonaktif'
                : ($senderPrereqReady ? 'Data Koneksi Siap' : ($apiReady ? 'Data Koneksi Siap / Pengaturan Event Perlu Dicek' : 'Data Koneksi Belum Lengkap')),
            'message' => empty($vault['enabled'])
                ? 'Penyimpanan data koneksi Google Ads nonaktif. Ini aman jika belum memakai pengiriman otomatis.'
                : ($senderPrereqReady
                    ? 'Data koneksi Google Ads sudah tersimpan aman dan pengiriman dasar siap digunakan.'
                    : ($apiReady
                        ? 'Data koneksi sudah lengkap, tetapi Customer ID atau pengaturan konversi masih perlu dicek.'
                        : 'Lengkapi data koneksi Google Ads. Token tidak akan ditampilkan penuh.')),
        ];
    }
}



if (!function_exists('google_ads_vault_credentials')) {
    function google_ads_vault_credentials(): array
    {
        $vault = google_ads_vault_read_raw();
        $credentials = [
            'enabled' => !empty($vault['enabled']),
            'api_version' => (string)($vault['api_version'] ?: 'v24'),
            'login_customer_id' => google_ads_vault_digits_clean((string)($vault['login_customer_id'] ?? ''), 20),
            'developer_token' => '',
            'client_id' => '',
            'client_secret' => '',
            'refresh_token' => '',
        ];
        foreach (['developer_token', 'client_id', 'client_secret', 'refresh_token'] as $key) {
            $secret = is_array($vault['secrets'][$key] ?? null) ? (array)$vault['secrets'][$key] : google_ads_vault_encrypt('');
            $credentials[$key] = google_ads_vault_decrypt($secret);
        }
        $credentials['oauth_ready'] = $credentials['client_id'] !== '' && $credentials['client_secret'] !== '' && $credentials['refresh_token'] !== '';
        $credentials['developer_ready'] = $credentials['developer_token'] !== '';
        $credentials['api_ready'] = $credentials['enabled'] && $credentials['oauth_ready'] && $credentials['developer_ready'];
        return $credentials;
    }
}

if (!function_exists('google_ads_vault_export_safe')) {
    function google_ads_vault_export_safe(?array $serverSettings = null): array
    {
        return google_ads_vault_status($serverSettings);
    }
}
