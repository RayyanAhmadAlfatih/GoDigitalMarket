<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| PHP COMPATIBILITY
|--------------------------------------------------------------------------
| array_is_list() native tersedia mulai PHP 8.1. Polyfill ini menjaga
| halaman Cek Sistem tetap jalan di XAMPP/PHP 8.0 tanpa mengubah logic.
|--------------------------------------------------------------------------
*/
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        $expectedKey = 0;
        foreach ($array as $key => $_value) {
            if ($key !== $expectedKey) {
                return false;
            }
            $expectedKey++;
        }
        return true;
    }
}

/*
|--------------------------------------------------------------------------
| Template DATA HEALTH ENGINE
|--------------------------------------------------------------------------
| Audit ringan untuk storage, produk, artikel, order, payment proof,
| reminder, activity log, route admin, dan file SEO penting.
|--------------------------------------------------------------------------
*/


if (!function_exists('data_health_clean_meta')) {
    function data_health_clean_meta(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $meta[$key] = data_health_clean_meta($value);
                continue;
            }
            if (is_string($value) && defined('ROOT_PATH') && str_starts_with($value, ROOT_PATH)) {
                $relative = ltrim(str_replace('\\', '/', substr($value, strlen(ROOT_PATH))), '/');
                $meta[$key] = $relative !== '' ? $relative : 'root';
            }
        }
        return $meta;
    }
}

if (!function_exists('data_health_check')) {
    function data_health_check(string $key, string $label, string $status, string $message, array $meta = []): array
    {
        $allowed = ['ok', 'warning', 'error', 'info'];
        if (!in_array($status, $allowed, true)) {
            $status = 'info';
        }
        $meta = data_health_clean_meta($meta);
        return compact('key', 'label', 'status', 'message', 'meta');
    }
}

if (!function_exists('data_health_json_file_status')) {
    function data_health_json_file_status(string $path, string $label, bool $required = false): array
    {
        if (!is_file($path)) {
            return data_health_check(
                'json_' . md5($path),
                $label,
                $required ? 'warning' : 'info',
                $required ? 'File belum ditemukan.' : 'File belum dibuat. Sistem akan membuat otomatis saat ada data.',
                ['path' => $path]
            );
        }

        $raw = (string)@file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) && trim($raw) !== '[]') {
            return data_health_check('json_' . md5($path), $label, 'error', 'JSON tidak valid atau rusak.', ['path' => $path]);
        }

        return data_health_check('json_' . md5($path), $label, 'ok', 'JSON valid dan bisa dibaca.', [
            'path' => $path,
            'size_bytes' => (int)@filesize($path),
            'rows' => is_array($decoded) && array_is_list($decoded) ? count($decoded) : null,
        ]);
    }
}

if (!function_exists('data_health_jsonl_files_status')) {
    function data_health_jsonl_files_status(array $files, string $label): array
    {
        $files = array_values($files);
        $invalid = [];
        $rows = 0;

        foreach ($files as $file) {
            if (!is_file((string)$file) || !is_readable((string)$file)) {
                continue;
            }
            $handle = @fopen((string)$file, 'rb');
            if (!$handle) {
                $invalid[] = (string)$file;
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $rows++;
                if (!is_array(json_decode($line, true))) {
                    $invalid[] = (string)$file;
                    break;
                }
            }
            fclose($handle);
        }

        if ($invalid) {
            return data_health_check('jsonl_' . md5($label), $label, 'error', 'Ada file JSONL yang tidak valid.', ['invalid_files' => array_values(array_unique($invalid)), 'files' => count($files), 'rows' => $rows]);
        }

        return data_health_check('jsonl_' . md5($label), $label, $files ? 'ok' : 'info', $files ? 'File JSONL valid.' : 'Belum ada file log JSONL.', ['files' => count($files), 'rows' => $rows]);
    }
}

if (!function_exists('data_health_duplicate_slugs')) {
    function data_health_duplicate_slugs(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $counts[$slug] = ($counts[$slug] ?? 0) + 1;
        }
        return array_keys(array_filter($counts, static fn(int $count): bool => $count > 1));
    }
}

if (!function_exists('data_health_missing_fields')) {
    function data_health_missing_fields(array $rows, array $fields): array
    {
        $result = [];
        foreach ($rows as $row) {
            $id = (string)($row['id'] ?? ($row['slug'] ?? '-'));
            foreach ($fields as $field) {
                if (trim((string)($row[$field] ?? '')) === '') {
                    $result[$field][] = $id;
                }
            }
        }
        return $result;
    }
}

if (!function_exists('data_health_local_image_missing')) {
    function data_health_local_image_missing(array $rows): array
    {
        $missing = [];
        foreach ($rows as $row) {
            $image = trim((string)($row['image'] ?? ''));
            if ($image === '' || str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, 'data:')) {
                continue;
            }
            $pathPart = parse_url($image, PHP_URL_PATH) ?: $image;
            $relative = ltrim((string)$pathPart, '/');
            if (!is_file(ROOT_PATH . '/' . $relative)) {
                $missing[] = [
                    'id' => $row['id'] ?? null,
                    'slug' => $row['slug'] ?? '',
                    'image' => $image,
                ];
            }
        }
        return $missing;
    }
}


if (!function_exists('data_health_status_summary')) {
    function data_health_status_summary(array $checks): array
    {
        $summary = ['ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = (string)($check['status'] ?? 'info');
            $summary[$status] = ($summary[$status] ?? 0) + 1;
        }
        $overall = $summary['error'] > 0 ? 'error' : ($summary['warning'] > 0 ? 'warning' : 'ok');
        return ['overall' => $overall, 'counts' => $summary];
    }
}

if (!function_exists('data_health_php_extension_checks')) {
    function data_health_php_extension_checks(): array
    {
        $required = ['json', 'openssl', 'fileinfo'];
        $recommended = ['curl', 'gd', 'mbstring'];
        $loadedRequired = [];
        $missingRequired = [];
        $loadedRecommended = [];
        $missingRecommended = [];

        foreach ($required as $extension) {
            if (extension_loaded($extension)) {
                $loadedRequired[] = $extension;
            } else {
                $missingRequired[] = $extension;
            }
        }

        foreach ($recommended as $extension) {
            if (extension_loaded($extension)) {
                $loadedRecommended[] = $extension;
            } else {
                $missingRecommended[] = $extension;
            }
        }

        if (extension_loaded('imagick')) {
            $loadedRecommended[] = 'imagick';
            $missingRecommended = array_values(array_diff($missingRecommended, ['gd']));
        }

        $status = empty($missingRequired) ? (empty($missingRecommended) ? 'ok' : 'warning') : 'error';
        $message = empty($missingRequired)
            ? (empty($missingRecommended)
                ? 'PHP extension wajib dan rekomendasi tersedia.'
                : 'PHP extension wajib tersedia. Beberapa extension rekomendasi belum aktif.')
            : 'Ada PHP extension wajib yang belum aktif.';

        return [
            'status' => $status,
            'message' => $message,
            'meta' => [
                'required_loaded' => $loadedRequired,
                'required_missing' => $missingRequired,
                'recommended_loaded' => array_values(array_unique($loadedRecommended)),
                'recommended_missing' => $missingRecommended,
            ],
        ];
    }
}

if (!function_exists('data_health_runtime_safety_files')) {
    function data_health_runtime_safety_files(): array
    {
        $files = [
            ROOT_PATH . '/.htaccess' => 'Root rewrite/security .htaccess',
            STORAGE_PATH . '/.htaccess' => 'Storage deny .htaccess',
            LOGS_PATH . '/.htaccess' => 'Logs deny .htaccess',
            CACHE_PATH . '/.htaccess' => 'Cache deny .htaccess',
            PAGES_PATH . '/.htaccess' => 'Pages direct-access deny .htaccess',
            CORE_PATH . '/.htaccess' => 'Core direct-access deny .htaccess',
            COMPONENTS_PATH . '/.htaccess' => 'Components direct-access deny .htaccess',
            DATA_PATH . '/.htaccess' => 'Data direct-access deny .htaccess',
            ROOT_PATH . '/docs/.htaccess' => 'Docs direct-access deny .htaccess',
            FEEDS_PATH . '/.htaccess' => 'Feeds direct-access deny .htaccess',
            ASSETS_PATH . '/uploads/.htaccess' => 'Uploads script-block .htaccess',
            STORAGE_PATH . '/form-files/.gitkeep' => 'Private form upload directory',
        ];

        $missing = [];
        $present = [];

        foreach ($files as $path => $label) {
            if (is_file((string)$path)) {
                $present[] = $label;
            } else {
                $missing[] = $label;
            }
        }

        return [
            'status' => empty($missing) ? 'ok' : 'warning',
            'message' => empty($missing)
                ? 'File proteksi runtime tersedia.'
                : 'Ada file proteksi runtime yang belum tersedia. Di Apache/LiteSpeed sebaiknya dilengkapi sebelum live.',
            'meta' => [
                'present' => $present,
                'missing' => $missing,
            ],
        ];
    }
}

if (!function_exists('data_health_route_target_files')) {
    function data_health_route_target_files(): array
    {
        $index = ROOT_PATH . '/index.php';
        $missing = [];
        $checked = 0;

        if (!is_file($index)) {
            return [
                'status' => 'error',
                'message' => 'index.php tidak ditemukan.',
                'meta' => ['checked' => 0, 'missing' => ['index.php']],
            ];
        }

        $source = (string)@file_get_contents($index);
        if (preg_match_all("#'([^']+)'\s*=>\s*'([^']+\.php)'#", $source, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $route = (string)$match[1];
                $target = (string)$match[2];
                $checked++;
                $targetPath = ROOT_PATH . '/pages/' . $target;
                if (!is_file($targetPath)) {
                    $missing[] = $route . ' => pages/' . $target;
                }
            }
        }

        return [
            'status' => empty($missing) ? 'ok' : 'error',
            'message' => empty($missing)
                ? 'Target file route utama tersedia.'
                : 'Ada route yang mengarah ke file halaman yang hilang.',
            'meta' => [
                'checked_routes' => $checked,
                'missing' => $missing,
            ],
        ];
    }
}

if (!function_exists('data_health_template_readiness')) {
    function data_health_template_readiness(): array
    {
        $docs = [
            ROOT_PATH . '/README.md',
            ROOT_PATH . '/docs/Panduan-UMKM-Growth-Web-Template.pdf',
        ];
        $missingDocs = array_values(array_filter($docs, static fn(string $path): bool => !is_file($path)));
        $brandFields = [SITE_NAME, SITE_TAGLINE, SITE_PHONE, SITE_WHATSAPP, SITE_EMAIL];
        $filledBrandFields = count(array_filter($brandFields, static fn(string $value): bool => trim($value) !== ''));

        $status = empty($missingDocs) && $filledBrandFields >= 4 ? 'ok' : 'warning';

        return [
            'status' => $status,
            'message' => $status === 'ok'
                ? 'Fondasi website UMKM siap dicek. Pengaturan brand, data awal, dan dokumen pendukung tersedia.'
                : 'Kesiapan website belum lengkap. Lengkapi pengaturan brand dan dokumen pendukung sebelum live.',
            'meta' => [
                'brand_fields_filled' => $filledBrandFields,
                'brand_fields_total' => count($brandFields),
                'missing_docs' => array_map('basename', $missingDocs),
            ],
        ];
    }
}

if (!function_exists('data_health_cloud_sync_readiness')) {
    function data_health_cloud_sync_readiness(): array
    {
        $report = function_exists('cloud_backup_report') ? cloud_backup_report() : [];
        $health = (array)($report['sync_health'] ?? (function_exists('cloud_backup_health_report') ? cloud_backup_health_report() : []));
        $score = (int)($health['score'] ?? 0);
        $endpointReady = !empty($report['endpoint_ready']);
        $tokenReady = !empty($report['token_ready']);
        $templateReady = !empty($report['apps_script_template_ready']);
        $status = (!$endpointReady && !$tokenReady) ? 'info' : ($score >= 80 && $templateReady ? 'ok' : 'warning');
        return [
            'status' => $status,
            'message' => $status === 'ok'
                ? 'Backup & Sync Data siap dipantau. Endpoint, template, dan health check tersedia.'
                : ($status === 'info' ? 'Backup cloud masih manual. Export lokal tetap aman sampai endpoint/token diisi.' : 'Backup cloud belum lengkap. Lengkapi endpoint/token sebelum sync otomatis.'),
            'meta' => [
                'score' => $score,
                'endpoint_ready' => $endpointReady,
                'token_ready' => $tokenReady,
                'template_ready' => $templateReady,
                'max_sync_rows' => (int)($report['max_sync_rows'] ?? 0),
                'retry_attempts' => (int)($report['retry_attempts'] ?? 0),
            ],
        ];
    }
}

if (!function_exists('data_health_google_sheets_hardening')) {
    function data_health_google_sheets_hardening(): array
    {
        $file = ROOT_PATH . '/integrations/google-sheets/apps-script-connector.js';
        $code = is_file($file) ? (string)@file_get_contents($file) : '';
        $checks = [
            'version' => str_contains($code, 'UGROWTH_CONNECTOR_VERSION'),
            'row_limit' => str_contains($code, 'UGROWTH_MAX_ROWS_PER_REQUEST'),
            'sync_log' => str_contains($code, '_sync_log') && str_contains($code, 'appendSyncLog_'),
            'payload_id' => str_contains($code, 'payload_id'),
            'checksum' => str_contains($code, 'checksum'),
            'token' => str_contains($code, 'SYNC_TOKEN'),
        ];
        $passed = count(array_filter($checks));
        return [
            'status' => $passed === count($checks) ? 'ok' : 'warning',
            'message' => $passed === count($checks)
                ? 'Connector Google Sheets punya guard payload, checksum, batas row, dan sync log.'
                : 'Connector Google Sheets belum lengkap untuk reliability guard.',
            'meta' => ['passed' => $passed, 'total' => count($checks), 'checks' => $checks],
        ];
    }
}

if (!function_exists('data_health_looker_hardening')) {
    function data_health_looker_hardening(): array
    {
        $file = ROOT_PATH . '/integrations/looker-studio/community-connector.js';
        $api = ROOT_PATH . '/pages/looker-studio-data.php';
        $code = is_file($file) ? (string)@file_get_contents($file) : '';
        $apiCode = is_file($api) ? (string)@file_get_contents($api) : '';
        $checks = [
            'https_only' => str_contains($code, 'URL API harus HTTPS'),
            'token_required' => str_contains($code, 'Token koneksi belum diisi'),
            'retry_fetch' => str_contains($code, 'attempt <= 2'),
            'max_rows' => str_contains($code, 'MAX_LOOKER_ROWS'),
            'api_source_sanitized' => str_contains($apiCode, "preg_replace('/[^a-zA-Z0-9_]+/'"),
            'api_nosniff' => str_contains($apiCode, 'X-Content-Type-Options'),
        ];
        $passed = count(array_filter($checks));
        return [
            'status' => $passed === count($checks) ? 'ok' : 'warning',
            'message' => $passed === count($checks)
                ? 'Looker Studio connector dan endpoint API punya guard HTTPS, token, limit, sanitasi source, dan retry ringan.'
                : 'Hardening Looker Studio belum lengkap.',
            'meta' => ['passed' => $passed, 'total' => count($checks), 'checks' => $checks],
        ];
    }
}

if (!function_exists('data_health_report')) {
    function data_health_report(): array
    {
        $checks = [];

        $adminPassword = trim((string)($_ENV['ADMIN_PASSWORD'] ?? ''));
        $adminAuthReady = function_exists('admin_auth_password_needs_setup') ? !admin_auth_password_needs_setup() : ($adminPassword !== '' && (!function_exists('admin_password_needs_setup') || !admin_password_needs_setup($adminPassword)));
        $checks[] = data_health_check('php_version', 'Versi PHP', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'warning', 'PHP aktif: ' . PHP_VERSION . '. Rekomendasi minimal PHP 8.1.');
        $checks[] = data_health_check('admin_password', 'Akses Owner Admin', $adminAuthReady ? 'ok' : 'warning', $adminAuthReady ? 'Akses owner/admin sudah aman.' : 'Akses owner belum aman. Jalankan /install atau buat password kuat sebelum live.');
        $checks[] = data_health_check('site_url', 'URL Website', filter_var(SITE_URL, FILTER_VALIDATE_URL) ? 'ok' : 'warning', 'SITE_URL saat ini: ' . SITE_URL);
        $storageReport = function_exists('storage_adapter_report') ? storage_adapter_report() : [];
        $storageDriver = (string)($storageReport['driver'] ?? 'file');
        $activeMysqlCollections = (int)($storageReport['summary']['mysql_active_collections'] ?? 0);
        $checks[] = data_health_check('db_status', 'Database & Storage', db_available() ? 'ok' : 'info', db_configured() ? (db_available() ? 'Database MySQL terkoneksi. Mode storage: ' . strtoupper($storageDriver) . '. Collection MySQL aktif: ' . $activeMysqlCollections . '.' : 'Konfigurasi DB ada, tetapi koneksi belum tersedia. Fallback JSON tetap aktif.') : 'Database belum dikonfigurasi. Website memakai penyimpanan file/JSON.', ['storage_driver' => $storageDriver, 'mysql_active_collections' => $activeMysqlCollections]);

        $cloudSyncReadiness = data_health_cloud_sync_readiness();
        $checks[] = data_health_check('cloud_sync_reliability', 'Backup & Sync Reliability', (string)$cloudSyncReadiness['status'], (string)$cloudSyncReadiness['message'], (array)$cloudSyncReadiness['meta']);
        $googleSheetsHardening = data_health_google_sheets_hardening();
        $checks[] = data_health_check('google_sheets_connector_hardening', 'Google Sheets Connector Guard', (string)$googleSheetsHardening['status'], (string)$googleSheetsHardening['message'], (array)$googleSheetsHardening['meta']);
        $lookerHardening = data_health_looker_hardening();
        $checks[] = data_health_check('looker_connector_hardening', 'Looker Studio Connector Guard', (string)$lookerHardening['status'], (string)$lookerHardening['message'], (array)$lookerHardening['meta']);

        $extensionStatus = data_health_php_extension_checks();
        $checks[] = data_health_check('php_extensions_live', 'Ekstensi PHP', (string)$extensionStatus['status'], (string)$extensionStatus['message'], (array)$extensionStatus['meta']);

        $runtimeSafety = data_health_runtime_safety_files();
        $checks[] = data_health_check('runtime_safety_files', 'Proteksi Folder Data', (string)$runtimeSafety['status'], (string)$runtimeSafety['message'], (array)$runtimeSafety['meta']);

        $routeTargets = data_health_route_target_files();
        $checks[] = data_health_check('route_target_files', 'File Route Website', (string)$routeTargets['status'], (string)$routeTargets['message'], (array)$routeTargets['meta']);

        $templateReadiness = data_health_template_readiness();
        $checks[] = data_health_check('template_readiness_umkm', 'Kesiapan Website UMKM', (string)$templateReadiness['status'], (string)$templateReadiness['message'], (array)$templateReadiness['meta']);

        foreach ([
            STORAGE_PATH => 'Storage',
            CACHE_PATH => 'Cache',
            LOGS_PATH => 'Logs',
            ASSETS_PATH . '/uploads' => 'Upload Media',
            STORAGE_PATH . '/payment-proofs' => 'Upload Bukti Pembayaran',
            function_exists('maintenance_backup_dir') ? maintenance_backup_dir() : CACHE_PATH . '/maintenance-backups' => 'Backup Website',
        ] as $path => $label) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $checks[] = data_health_check(
                'dir_' . md5((string)$path),
                $label,
                $exists && $writable ? 'ok' : ($exists ? 'warning' : 'info'),
                $exists ? ($writable ? 'Folder ada dan writable.' : 'Folder ada tapi belum writable.') : 'Folder belum ada, akan dibuat saat fitur dipakai.',
                ['path' => $path]
            );
        }

        if (function_exists('product_storage_path')) {
            $checks[] = data_health_json_file_status(product_storage_path(), 'Data Produk');
        }
        if (function_exists('article_storage_path')) {
            $checks[] = data_health_json_file_status(article_storage_path(), 'Data Artikel');
        }
        if (function_exists('order_status_file')) {
            $checks[] = data_health_json_file_status(order_status_file(), 'Status Order');
        }
        if (function_exists('payment_proof_status_file')) {
            $checks[] = data_health_json_file_status(payment_proof_status_file(), 'Status Bukti Pembayaran');
        }
        if (function_exists('payment_settings_file')) {
            $checks[] = data_health_json_file_status(payment_settings_file(), 'Pengaturan Pembayaran');
        }
        if (function_exists('payment_gateway_settings_file')) {
            $checks[] = data_health_json_file_status(payment_gateway_settings_file(), 'Pengaturan Pembayaran Otomatis');
        }
        if (function_exists('payment_gateway_transaction_file')) {
            $checks[] = data_health_json_file_status(payment_gateway_transaction_file(), 'Transaksi Pembayaran Otomatis');
        }
        if (function_exists('analytics_settings_file')) {
            $checks[] = data_health_json_file_status(analytics_settings_file(), 'Pengaturan Analytics');
        }
        if (function_exists('marketing_integration_settings_file')) {
            $checks[] = data_health_json_file_status(marketing_integration_settings_file(), 'Pengaturan Email/WhatsApp Otomatis');
        }
        if (function_exists('server_conversion_settings_file')) {
            $checks[] = data_health_json_file_status(server_conversion_settings_file(), 'Pengaturan Konversi Iklan');
        }
        if (function_exists('server_conversion_queue_file')) {
            $checks[] = data_health_json_file_status(server_conversion_queue_file(), 'Data Pengiriman Konversi');
        }
        if (function_exists('server_conversion_google_ads_queue_file')) {
            $checks[] = data_health_json_file_status(server_conversion_google_ads_queue_file(), 'Data Konversi Google Ads');
        }
        if (function_exists('seo_landing_storage_path')) {
            $checks[] = data_health_json_file_status(seo_landing_storage_path(), 'Data SEO Landing');
        }
        if (function_exists('landing_page_storage_path')) {
            $checks[] = data_health_json_file_status(landing_page_storage_path(), 'Data Landing Page');
        }
        if (function_exists('landing_page_template_storage_path')) {
            $checks[] = data_health_json_file_status(landing_page_template_storage_path(), 'Library Landing Page');
        }

        $checks[] = data_health_jsonl_files_status(function_exists('order_log_files') ? order_log_files(3650) : [], 'Log Order');
        $checks[] = data_health_jsonl_files_status(function_exists('payment_proof_log_files') ? payment_proof_log_files(3650) : [], 'Log Bukti Pembayaran');
        $checks[] = data_health_jsonl_files_status(function_exists('payment_gateway_log_files') ? payment_gateway_log_files(3650) : [], 'Log Pembayaran Otomatis');
        $checks[] = data_health_jsonl_files_status(function_exists('inquiry_log_files') ? inquiry_log_files(3650) : [], 'Log Form Masuk');
        $checks[] = data_health_jsonl_files_status(function_exists('crm_log_files') ? crm_log_files(3650) : [], 'Log Follow-up');
        $checks[] = data_health_jsonl_files_status(function_exists('notification_log_files') ? notification_log_files(3650) : [], 'Email Notification JSONL Logs');
        $checks[] = data_health_jsonl_files_status(function_exists('payment_reminder_log_files') ? payment_reminder_log_files(3650) : [], 'Payment Reminder JSONL Logs');
        $checks[] = data_health_jsonl_files_status(function_exists('transaction_log_files') ? transaction_log_files(3650) : [], 'Transaction Audit JSONL Logs');
        $checks[] = data_health_jsonl_files_status(function_exists('marketing_integration_log_files') ? marketing_integration_log_files(3650) : [], 'Mailketing & Fonnte JSONL Logs');
        $checks[] = data_health_jsonl_files_status(function_exists('server_conversion_log_files') ? server_conversion_log_files(3650) : [], 'Server-Side Conversion JSONL Logs');
        $checks[] = data_health_jsonl_files_status(function_exists('conversion_lead_log_files') ? conversion_lead_log_files(3650) : [], 'Landing Page / Lead Event JSONL Logs');

        $products = function_exists('all_products') ? all_products() : [];
        $articles = function_exists('all_articles') ? all_articles() : [];
        $orders = function_exists('order_read_all') ? order_read_all(3650, [], 100000) : [];
        $proofs = function_exists('payment_proof_read_all') ? payment_proof_read_all(3650, [], 100000) : [];
        $inquiries = function_exists('inquiry_read_all') ? inquiry_read_all(3650, [], 100000) : [];
        $followups = function_exists('crm_read_all') ? crm_read_all(3650, [], 100000) : [];
        $notifications = function_exists('notification_read_all') ? notification_read_all(3650, [], 100000) : [];
        $reportSummary = function_exists('report_dashboard_summary') ? report_dashboard_summary(30, []) : [];
        $seoQualitySummary = function_exists('seo_quality_summary') ? seo_quality_summary('all') : [];
        $mediaLibrarySummary = function_exists('media_library_summary') ? media_library_summary() : [];
        $seoLandingSummary = function_exists('seo_landing_summary') ? seo_landing_summary() : [];
        $paymentGatewaySummary = function_exists('payment_gateway_summary') ? payment_gateway_summary() : [];
        $analyticsSummary = function_exists('analytics_channel_dashboard_summary') ? analytics_channel_dashboard_summary(30, []) : [];
        $analyticsPixelSummary = function_exists('analytics_pixel_status_summary') ? analytics_pixel_status_summary() : [];
        $googleAdsDirectStatus = function_exists('analytics_google_ads_direct_status') ? analytics_google_ads_direct_status() : [];
        $googleAdsVerification = function_exists('analytics_google_ads_verification_summary') ? analytics_google_ads_verification_summary() : [];
        $marketingIntegrationSummary = function_exists('marketing_integration_summary') ? marketing_integration_summary() : [];
        $serverConversionSummary = function_exists('server_conversion_status_summary') ? server_conversion_status_summary() : [];
        $serverConversionUxSummary = function_exists('server_conversion_ux_sync_summary') ? server_conversion_ux_sync_summary() : [];
        $googleAdsVaultStatus = function_exists('google_ads_vault_status') ? google_ads_vault_status() : [];
        $landingBuilderSummary = function_exists('landing_page_summary') ? landing_page_summary() : ['counts' => []];
        $landingTemplateSummary = function_exists('landing_page_template_summary') ? landing_page_template_summary() : ['counts' => []];
        $landingFormSegmentationSummary = function_exists('landing_page_form_segmentation_summary') ? landing_page_form_segmentation_summary() : [];
        $landingAnalyticsSummary = function_exists('landing_page_analytics_report') ? landing_page_analytics_report(30, []) : [];

        $productDuplicateSlugs = data_health_duplicate_slugs($products);
        $articleDuplicateSlugs = data_health_duplicate_slugs($articles);
        $missingProductFields = data_health_missing_fields($products, ['title', 'slug', 'category', 'image']);
        $missingArticleFields = data_health_missing_fields($articles, ['title', 'slug', 'content']);
        $missingProductImages = data_health_local_image_missing($products);
        $missingArticleImages = data_health_local_image_missing($articles);

        $checks[] = data_health_check('product_count', 'Jumlah Produk', count($products) > 0 ? 'ok' : 'warning', count($products) . ' produk terdeteksi.', ['count' => count($products)]);
        $checks[] = data_health_check('article_count', 'Jumlah Artikel', count($articles) > 0 ? 'ok' : 'warning', count($articles) . ' artikel terdeteksi.', ['count' => count($articles)]);
        $checks[] = data_health_check('orders_count', 'Jumlah Order', 'info', count($orders) . ' order tersimpan.', ['count' => count($orders)]);
        $checks[] = data_health_check('payment_proofs_count', 'Jumlah Bukti Pembayaran', 'info', count($proofs) . ' bukti pembayaran tersimpan.', ['count' => count($proofs)]);
        $checks[] = data_health_check('inquiries_count', 'Jumlah Lead/Form', 'info', count($inquiries) . ' lead/form tersimpan.', ['count' => count($inquiries)]);
        $checks[] = data_health_check('followups_count', 'Jumlah Follow-up', 'info', count($followups) . ' follow-up tersimpan.', ['count' => count($followups)]);
        $checks[] = data_health_check('notifications_count', 'Jumlah Riwayat Email', 'info', count($notifications) . ' riwayat email tersimpan.', ['count' => count($notifications)]);
        $checks[] = data_health_check('report_dashboard_engine', 'Laporan Sistem', !empty($reportSummary) ? 'ok' : 'warning', !empty($reportSummary) ? 'Laporan bisa membaca data lead, order, dan pembayaran.' : 'Laporan belum bisa membuat ringkasan.', ['has_summary' => !empty($reportSummary)]);
        $checks[] = data_health_check('seo_quality_engine', 'Cek SEO Sistem', !empty($seoQualitySummary) ? 'ok' : 'warning', !empty($seoQualitySummary) ? 'Cek SEO bisa audit produk/artikel.' : 'Cek SEO belum bisa membuat ringkasan.', ['average_score' => $seoQualitySummary['score_average'] ?? null, 'items' => $seoQualitySummary['counts']['items'] ?? null, 'issues' => $seoQualitySummary['counts']['issues'] ?? null]);
        $checks[] = data_health_check('media_library_engine', 'Media Library Sistem', !empty($mediaLibrarySummary) ? 'ok' : 'warning', !empty($mediaLibrarySummary) ? 'Media Library bisa scan gambar lokal.' : 'Media Library belum bisa membaca file gambar.', ['total' => $mediaLibrarySummary['total'] ?? null, 'large' => $mediaLibrarySummary['large'] ?? null, 'missing_alt' => $mediaLibrarySummary['missing_alt'] ?? null]);
        $checks[] = data_health_check('seo_landing_engine', 'SEO Landing Registry Sistem', !empty($seoLandingSummary) ? 'ok' : 'warning', !empty($seoLandingSummary) ? 'SEO Landing Registry bisa generate clean URL dari produk.' : 'SEO Landing Registry belum bisa generate data.', ['total' => $seoLandingSummary['counts']['total'] ?? null, 'indexable' => $seoLandingSummary['counts']['indexable'] ?? null, 'disabled' => $seoLandingSummary['counts']['disabled'] ?? null]);
        $checks[] = data_health_check('payment_gateway_engine', 'Pembayaran Otomatis Sistem', !empty($paymentGatewaySummary) ? 'ok' : 'warning', !empty($paymentGatewaySummary) ? 'Pembayaran Otomatis bisa membaca konfigurasi dan catatan webhook.' : 'Pembayaran Otomatis belum bisa membuat ringkasan.', ['enabled' => $paymentGatewaySummary['enabled'] ?? null, 'enabled_count' => $paymentGatewaySummary['enabled_count'] ?? null, 'configured_count' => $paymentGatewaySummary['configured_count'] ?? null, 'webhook_events_30d' => $paymentGatewaySummary['webhook_events_30d'] ?? null]);
        $checks[] = data_health_check('analytics_engine', 'Analytics & Iklan Sistem', !empty($analyticsSummary) ? 'ok' : 'warning', !empty($analyticsSummary) ? 'Analytics iklan bisa membaca channel lead/order.' : 'Analytics iklan belum bisa membuat ringkasan.', ['lead_events' => $analyticsSummary['totals']['lead_events'] ?? null, 'orders' => $analyticsSummary['totals']['orders'] ?? null, 'channels' => isset($analyticsSummary['channels']) ? count((array)$analyticsSummary['channels']) : null]);
        $checks[] = data_health_check('analytics_multi_pixel', 'Multi Pixel & Ads Platform Settings', !empty($analyticsPixelSummary) ? 'ok' : 'warning', !empty($analyticsPixelSummary) ? 'Multi pixel settings bisa dibaca dan ID platform divalidasi format dasar.' : 'Multi pixel settings belum bisa dibaca.', ['mode' => $analyticsPixelSummary['mode'] ?? null, 'direct_enabled' => $analyticsPixelSummary['direct_enabled'] ?? null, 'active_pixels' => $analyticsPixelSummary['active_count'] ?? null, 'custom_meta' => $analyticsPixelSummary['custom_meta_count'] ?? null]);
        $checks[] = data_health_check(
            'analytics_google_ads_website_tracking',
            'Tracking Konversi Google Ads',
            empty($googleAdsDirectStatus['enabled']) ? 'info' : (!empty($googleAdsDirectStatus['tracking_ready']) ? 'ok' : 'warning'),
            empty($googleAdsDirectStatus['enabled'])
                ? 'Google Ads website tracking nonaktif. Ini normal jika belum menjalankan Google Ads.'
                : (!empty($googleAdsDirectStatus['tracking_ready'])
                    ? (string)($googleAdsDirectStatus['message'] ?? 'Google Ads website tracking siap.')
                    : 'Google Ads aktif, tetapi ID Google Tag, label konversi, atau mode pemasangan belum lengkap.'),
            [
                'status_label' => $googleAdsDirectStatus['status_label'] ?? null,
                'direct_ready' => $googleAdsDirectStatus['direct_ready'] ?? null,
                'gtm_ready' => $googleAdsDirectStatus['gtm_ready'] ?? null,
                'ready_event_count' => $googleAdsDirectStatus['ready_event_count'] ?? null,
                'missing_label_events' => $googleAdsDirectStatus['missing_label_events'] ?? [],
            ]
        );
        $checks[] = data_health_check(
            'analytics_google_ads_verification_center',
            'Google Ads Tracking Verification Center',
            empty($googleAdsDirectStatus['enabled']) ? 'info' : ((int)($googleAdsVerification['ok_count'] ?? 0) >= 5 ? 'ok' : 'warning'),
            empty($googleAdsDirectStatus['enabled'])
                ? 'Verification Center tersedia, tetapi Google Ads tracking belum diaktifkan.'
                : ((int)($googleAdsVerification['ok_count'] ?? 0) >= 5
                    ? 'Pengecekan siap. Buka halaman tes untuk memastikan tracking terbaca.'
                    : 'Sebagian checklist belum siap. Cek ID Google Tag, label konversi, mode pemasangan, atau halaman tes.'),
            [
                'status_label' => $googleAdsVerification['status_label'] ?? null,
                'ok_count' => $googleAdsVerification['ok_count'] ?? null,
                'total' => $googleAdsVerification['total'] ?? null,
                'test_url' => $googleAdsVerification['test_url'] ?? null,
                'anti_double_fire' => true,
            ]
        );
        $checks[] = data_health_check('landing_page_builder_template7', 'Landing Page Builder Visual + Form', function_exists('landing_page_summary') ? 'ok' : 'warning', function_exists('landing_page_summary') ? 'Sistem landing page builder visual bisa dibaca. Admin dapat membuat LP direct selling, blok form custom, SEO, dan tracking.' : 'Sistem landing page builder belum tersedia.', ['total' => $landingBuilderSummary['counts']['total'] ?? null, 'published' => $landingBuilderSummary['counts']['published'] ?? null, 'indexable' => $landingBuilderSummary['counts']['indexable'] ?? null]);
        $checks[] = data_health_check('landing_page_template_library_template8_6', 'Landing Page Template Library', function_exists('landing_page_template_summary') ? 'ok' : 'warning', function_exists('landing_page_template_summary') ? 'Template Library siap. Admin bisa menyimpan LP sebagai template, membuat LP dari Template Saya, dan duplikat LP existing.' : 'Template Library belum tersedia.', ['total_templates' => $landingTemplateSummary['counts']['total'] ?? null, 'with_form' => $landingTemplateSummary['counts']['with_form'] ?? null, 'with_seo' => $landingTemplateSummary['counts']['with_seo'] ?? null, 'with_tracking' => $landingTemplateSummary['counts']['with_tracking'] ?? null]);
        $checks[] = data_health_check(
            'landing_page_form_builder',
            'Landing Page Form Builder Advanced + Lead Segmentation',
            function_exists('landing_page_form_segmentation_summary') ? 'ok' : 'warning',
            function_exists('landing_page_form_segmentation_summary')
                ? 'Form custom siap dipakai dengan berbagai jenis kolom dan pengelompokan lead.'
                : 'Landing Page Form Builder Advanced belum tersedia.',
            [
                'forms' => $landingFormSegmentationSummary['forms'] ?? null,
                'segmented_forms' => $landingFormSegmentationSummary['segmented_forms'] ?? null,
                'custom_list_forms' => $landingFormSegmentationSummary['custom_list_forms'] ?? null,
                'segments' => $landingFormSegmentationSummary['segments'] ?? [],
                'field_types' => $landingFormSegmentationSummary['field_types'] ?? [],
            ]
        );
        $checks[] = data_health_check(
            'landing_page_analytics',
            'Landing Page Analytics + Conversion Report',
            function_exists('landing_page_analytics_report') ? (!empty($landingAnalyticsSummary['tracking_ready']) ? 'ok' : 'warning') : 'warning',
            function_exists('landing_page_analytics_report')
                ? (!empty($landingAnalyticsSummary['tracking_ready']) ? 'Laporan landing page siap membaca kunjungan, klik tombol, form masuk, order, sumber traffic, dan kategori lead.' : 'Laporan landing page tersedia, tetapi fitur tracking lead perlu dicek.')
                : 'Sistem Landing Page Analytics belum tersedia.',
            [
                'page_view_30d' => $landingAnalyticsSummary['totals']['page_view'] ?? null,
                'cta_click_30d' => $landingAnalyticsSummary['totals']['cta_click'] ?? null,
                'inquiry_30d' => $landingAnalyticsSummary['totals']['inquiry'] ?? null,
                'order_30d' => $landingAnalyticsSummary['totals']['order'] ?? null,
                'conversion_rate_30d' => $landingAnalyticsSummary['totals']['conversion_rate'] ?? null,
            ]
        );
        $checks[] = data_health_check('marketing_integrations_engine', 'Mailketing & Fonnte Integration Sistem', !empty($marketingIntegrationSummary) ? 'ok' : 'warning', !empty($marketingIntegrationSummary) ? 'Mailketing/Fonnte settings dan log bisa dibaca.' : 'Mailketing/Fonnte integration belum bisa membuat ringkasan.', ['enabled' => $marketingIntegrationSummary['enabled'] ?? null, 'mailketing_configured' => $marketingIntegrationSummary['mailketing']['configured'] ?? null, 'buyer_list_configured' => $marketingIntegrationSummary['mailketing']['buyer_configured'] ?? null, 'sync_buyer' => $marketingIntegrationSummary['mailketing']['sync_buyer'] ?? null, 'fonnte_configured' => $marketingIntegrationSummary['fonnte']['configured'] ?? null, 'recent_logs' => $marketingIntegrationSummary['recent_logs'] ?? null]);
        $checks[] = data_health_check('mailketing_buyer_list_id', 'Mailketing Buyer List ID', empty($marketingIntegrationSummary['enabled']) ? 'info' : (!empty($marketingIntegrationSummary['mailketing']['buyer_configured']) ? 'ok' : 'warning'), empty($marketingIntegrationSummary['enabled']) ? 'Integrasi Mailketing/Fonnte belum aktif.' : (!empty($marketingIntegrationSummary['mailketing']['buyer_configured']) ? 'Buyer List ID siap untuk lead/order yang sudah bayar.' : 'Buyer List ID belum lengkap. Isi list khusus buyer agar customer DP/Lunas masuk ke list buyer.'), ['buyer_list_id_set' => !empty($marketingIntegrationSummary['mailketing']['buyer_list_id']), 'sync_buyer' => $marketingIntegrationSummary['mailketing']['sync_buyer'] ?? null]);
        $checks[] = data_health_check('server_conversion_engine', 'Pengiriman Data Iklan dari Server', !empty($serverConversionSummary) ? 'ok' : 'warning', !empty($serverConversionSummary) ? 'Pengaturan pengiriman data iklan, status, dan riwayat bisa dibaca.' : 'Ringkasan pengiriman data iklan belum bisa dibuat.', ['enabled' => $serverConversionSummary['enabled'] ?? null, 'test_mode' => $serverConversionSummary['test_mode'] ?? null, 'pending' => $serverConversionSummary['queue_counts']['pending'] ?? null, 'failed' => $serverConversionSummary['queue_counts']['failed'] ?? null, 'ignored' => $serverConversionSummary['queue_counts']['ignored'] ?? null]);
        $checks[] = data_health_check('server_conversion_ux_sync', 'Sinkronisasi Pixel Iklan', empty($serverConversionUxSummary['warning_count']) ? 'ok' : 'warning', empty($serverConversionUxSummary['warning_count']) ? 'Pixel browser dan server tidak terdeteksi bentrok.' : (int)$serverConversionUxSummary['warning_count'] . ' peringatan sinkronisasi pixel perlu dicek admin.', ['hybrid_ready_count' => $serverConversionUxSummary['hybrid_ready_count'] ?? null, 'warnings' => $serverConversionUxSummary['warnings'] ?? []]);

        $serverQueueCounts = (array)($serverConversionSummary['queue_counts'] ?? []);
        $serverQueueTotal = (int)($serverQueueCounts['total'] ?? 0);
        $serverQueueFailed = (int)($serverQueueCounts['failed'] ?? 0);
        $serverCron = (array)($serverConversionSummary['cron'] ?? []);
        $serverSendingMode = (string)($serverConversionSummary['sending_mode'] ?? 'manual');
        $serverAutoMode = in_array($serverSendingMode, ['auto', 'hybrid'], true);
        $checks[] = data_health_check('server_conversion_queue_size', 'Ukuran Data Meta/TikTok', $serverQueueTotal > 1800 ? 'warning' : 'ok', $serverQueueTotal > 1800 ? 'Data pengiriman server mendekati batas penyimpanan. Bersihkan data terkirim lama atau export CSV.' : 'Ukuran data pengiriman server masih aman.', ['total' => $serverQueueTotal, 'pending' => $serverQueueCounts['pending'] ?? null, 'sent' => $serverQueueCounts['sent'] ?? null, 'failed' => $serverQueueFailed, 'ignored' => $serverQueueCounts['ignored'] ?? null, 'old_sent' => $serverQueueCounts['old_sent'] ?? null]);
        $checks[] = data_health_check('server_conversion_failed_count', 'Data Meta/TikTok Gagal', $serverQueueFailed >= 25 ? 'warning' : 'ok', $serverQueueFailed >= 25 ? $serverQueueFailed . ' data gagal perlu dicek di halaman monitoring.' : 'Jumlah data gagal masih aman.', ['failed' => $serverQueueFailed]);
        $checks[] = data_health_check('server_conversion_cron_token', 'Token Jadwal Pengiriman', (!empty($serverCron['enabled']) && empty($serverCron['token_set'])) ? 'warning' : 'ok', (!empty($serverCron['enabled']) && empty($serverCron['token_set'])) ? 'Pengiriman terjadwal aktif, tetapi token jadwal belum diisi.' : 'Token jadwal pengiriman tidak bermasalah.', ['cron_enabled' => $serverCron['enabled'] ?? null, 'token_set' => $serverCron['token_set'] ?? null, 'last_run_at' => $serverCron['last_run_at'] ?? null]);
        $checks[] = data_health_check('server_conversion_auto_without_token', 'Kesiapan Pengiriman Otomatis', ($serverAutoMode && empty($serverCron['token_set'])) ? 'warning' : 'ok', ($serverAutoMode && empty($serverCron['token_set'])) ? 'Mode otomatis aktif, tetapi token jadwal kosong. Isi token sebelum dipakai live.' : 'Mode otomatis dan token jadwal tidak bermasalah.', ['sending_mode' => $serverSendingMode, 'cron_enabled' => $serverCron['enabled'] ?? null, 'token_set' => $serverCron['token_set'] ?? null]);

        $googleAdsDasar = (array)($serverConversionSummary['platforms']['google_ads'] ?? []);
        $googleAdsEnabled = !empty($googleAdsDasar['enabled']);
        $googleAdsConfigured = !empty($googleAdsDasar['configured']);
        $googleAdsMapping = (array)($googleAdsDasar['mapping'] ?? []);
        $googleAdsQueue = (array)($googleAdsDasar['queue'] ?? []);
        $googleAdsQueueCounts = (array)($googleAdsQueue['counts'] ?? []);
        $googleAdsMappingReady = !empty($googleAdsDasar['mapping_ready']);
        $googleAdsCaptureEnabled = !empty($googleAdsDasar['capture_click_ids_enabled']);
        $checks[] = data_health_check(
            'server_conversion_google_ads_foundation',
            'Google Ads Conversion Dasar',
            ($googleAdsEnabled && (!$googleAdsConfigured || !$googleAdsMappingReady)) ? 'warning' : ($googleAdsEnabled ? 'ok' : 'info'),
            !$googleAdsEnabled
                ? 'Tracking Google Ads belum aktif. Ini normal jika belum memakai iklan Google.'
                : ($googleAdsConfigured && $googleAdsMappingReady
                    ? 'Tracking Google Ads siap mencatat data klik dan pengelompokan event. Pengiriman otomatis tetap nonaktif sampai data koneksi lengkap.'
                    : 'Tracking Google Ads aktif, tetapi beberapa pengaturan iklan belum lengkap.'),
            [
                'enabled' => $googleAdsEnabled,
                'configured' => $googleAdsConfigured,
                'mapping_ready' => $googleAdsMappingReady,
                'foundation_only' => $googleAdsDasar['foundation_only'] ?? true,
                'sender_ready' => $googleAdsDasar['sender_ready'] ?? false,
                'status_label' => $googleAdsDasar['status_label'] ?? null,
            ]
        );
        $checks[] = data_health_check(
            'server_conversion_google_ads_click_id_capture',
            'Perekaman Klik Google Ads',
            ($googleAdsEnabled && !$googleAdsCaptureEnabled) ? 'warning' : ($googleAdsEnabled ? 'ok' : 'info'),
            !$googleAdsEnabled
                ? 'Perekaman klik Google Ads belum diperlukan karena tracking iklan belum aktif.'
                : ($googleAdsCaptureEnabled
                    ? 'Pencatatan klik Google Ads aktif.'
                    : 'Google Ads aktif tetapi capture click ID dimatikan. Offline conversion sulit dipakai tanpa click ID.'),
            [
                'capture_enabled' => $googleAdsCaptureEnabled,
                'queue_enabled' => $googleAdsDasar['queue_enabled'] ?? null,
                'click_types' => $googleAdsQueue['click_types'] ?? [],
            ]
        );
        $checks[] = data_health_check(
            'server_conversion_google_ads_mapping',
            'Google Ads Event Mapping',
            ($googleAdsEnabled && !$googleAdsMappingReady) ? 'warning' : ($googleAdsEnabled ? 'ok' : 'info'),
            !$googleAdsEnabled
                ? 'Mapping Google Ads nonaktif bersama foundation.'
                : ($googleAdsMappingReady
                    ? (int)($googleAdsMapping['ready_count'] ?? 0) . ' aturan tracking siap dipakai.'
                    : 'Belum ada aturan tracking Google Ads yang siap. Lengkapi pengaturan untuk lead, order, atau pembelian.'),
            [
                'enabled_count' => $googleAdsMapping['enabled_count'] ?? null,
                'ready_count' => $googleAdsMapping['ready_count'] ?? null,
                'total' => $googleAdsMapping['total'] ?? null,
            ]
        );
        $checks[] = data_health_check(
            'server_conversion_google_ads_queue_readiness',
            'Kesiapan Data Google Ads',
            ($googleAdsEnabled && (int)($googleAdsQueueCounts['total'] ?? 0) > 0 && (int)($googleAdsQueueCounts['ready_for_sender'] ?? 0) === 0) ? 'warning' : ($googleAdsEnabled ? 'ok' : 'info'),
            !$googleAdsEnabled
                ? 'Penyimpanan data Google Ads nonaktif.'
                : ((int)($googleAdsQueueCounts['total'] ?? 0) === 0
                    ? 'Belum ada data klik Google Ads. Ini normal sebelum ada traffic dari iklan Google.'
                    : ((int)($googleAdsQueueCounts['ready_for_sender'] ?? 0) > 0
                        ? (int)$googleAdsQueueCounts['ready_for_sender'] . ' data siap untuk pengiriman otomatis.'
                        : 'Ada data Google Ads, tetapi belum siap dikirim. Cek pengaturan tracking yang belum lengkap.')),
            $googleAdsQueueCounts
        );
        $checks[] = data_health_check(
            'server_conversion_google_ads_credential_vault',
            'Penyimpanan Data Koneksi Google Ads',
            !$googleAdsEnabled
                ? 'info'
                : (!empty($googleAdsVaultStatus['sender_prereq_ready']) ? 'ok' : 'warning'),
            !$googleAdsEnabled
                ? 'Data koneksi belum diperlukan karena tracking Google Ads nonaktif.'
                : (!empty($googleAdsVaultStatus['sender_prereq_ready'])
                    ? 'Data koneksi Google Ads sudah siap.'
                    : (empty($googleAdsVaultStatus['enabled'])
                        ? 'Tracking Google Ads aktif, tetapi data koneksi belum diaktifkan.'
                        : 'Data koneksi Google Ads aktif, tetapi beberapa pengaturan akun dan tracking belum lengkap.')),
            [
                'vault_enabled' => $googleAdsVaultStatus['enabled'] ?? null,
                'developer_token_set' => $googleAdsVaultStatus['developer_token_set'] ?? null,
                'oauth_ready' => $googleAdsVaultStatus['oauth_ready'] ?? null,
                'sender_prereq_ready' => $googleAdsVaultStatus['sender_prereq_ready'] ?? null,
                'encrypted_all' => $googleAdsVaultStatus['encrypted_all'] ?? null,
                'openssl_available' => $googleAdsVaultStatus['openssl_available'] ?? null,
                'status_label' => $googleAdsVaultStatus['status_label'] ?? null,
            ]
        );
        $checks[] = data_health_check(
            'server_conversion_google_ads_oauth_status',
            'Status Koneksi Google Ads',
            $googleAdsEnabled ? 'info' : 'info',
            $googleAdsEnabled
                ? 'Sistem sudah menyediakan Google Ads Sender Beta yang eksplisit opt-in, dengan validate-only mode sebagai default aman.'
                : 'Koneksi Google Ads belum dipakai.',
            [
                'oauth_required' => $googleAdsDasar['oauth_required'] ?? true,
                'sender_ready' => $googleAdsDasar['sender_ready'] ?? false,
                'sender_prereq_ready' => $googleAdsDasar['sender_prereq_ready'] ?? false,
            ]
        );

        $googleAdsSender = (array)($googleAdsDasar['sender'] ?? []);
        $checks[] = data_health_check(
            'server_conversion_google_ads_sender_beta',
            'Pengiriman Data Google Ads',
            !$googleAdsEnabled
                ? 'info'
                : (!empty($googleAdsSender['enabled']) ? (!empty($googleAdsSender['ready']) ? 'ok' : 'warning') : 'info'),
            !$googleAdsEnabled
                ? 'Pengiriman data belum diperlukan karena tracking Google Ads belum aktif.'
                : (!empty($googleAdsSender['enabled'])
                    ? (!empty($googleAdsSender['ready'])
                        ? ((empty($googleAdsSender['validate_only']) ? 'Mode live aktif. Pastikan pengaturan Google Ads sudah benar.' : 'Mode tes aktif dan koneksi Google Ads siap dicek.'))
                        : 'Pengiriman data aktif, tetapi beberapa pengaturan Google Ads belum lengkap.')
                    : 'Pengiriman data masih nonaktif. Data tersimpan aman sebagai catatan persiapan.'),
            [
                'enabled' => $googleAdsSender['enabled'] ?? null,
                'ready' => $googleAdsSender['ready'] ?? null,
                'validate_only' => $googleAdsSender['validate_only'] ?? null,
                'max_events_per_run' => $googleAdsSender['max_events_per_run'] ?? null,
                'last_run_at' => $googleAdsSender['last_run_at'] ?? null,
                'last_result' => $googleAdsSender['last_result'] ?? [],
            ]
        );

        $checks[] = data_health_check('product_duplicate_slugs', 'Slug Produk Duplikat', empty($productDuplicateSlugs) ? 'ok' : 'error', empty($productDuplicateSlugs) ? 'Tidak ada slug produk duplikat.' : count($productDuplicateSlugs) . ' slug produk duplikat ditemukan.', ['duplicates' => $productDuplicateSlugs]);
        $checks[] = data_health_check('article_duplicate_slugs', 'Slug Artikel Duplikat', empty($articleDuplicateSlugs) ? 'ok' : 'error', empty($articleDuplicateSlugs) ? 'Tidak ada slug artikel duplikat.' : count($articleDuplicateSlugs) . ' slug artikel duplikat ditemukan.', ['duplicates' => $articleDuplicateSlugs]);
        $checks[] = data_health_check('product_required_fields', 'Field Wajib Produk', empty(array_filter($missingProductFields)) ? 'ok' : 'warning', empty(array_filter($missingProductFields)) ? 'Field penting produk aman.' : 'Ada field produk yang masih kosong.', $missingProductFields);
        $checks[] = data_health_check('article_required_fields', 'Field Wajib Artikel', empty(array_filter($missingArticleFields)) ? 'ok' : 'warning', empty(array_filter($missingArticleFields)) ? 'Field penting artikel aman.' : 'Ada field artikel yang masih kosong.', $missingArticleFields);
        $checks[] = data_health_check('product_images', 'Gambar Produk Lokal', empty($missingProductImages) ? 'ok' : 'warning', empty($missingProductImages) ? 'Path gambar produk lokal aman.' : count($missingProductImages) . ' gambar produk lokal tidak ditemukan.', ['missing' => array_slice($missingProductImages, 0, 20)]);
        $checks[] = data_health_check('article_images', 'Gambar Artikel Lokal', empty($missingArticleImages) ? 'ok' : 'warning', empty($missingArticleImages) ? 'Path gambar artikel lokal aman.' : count($missingArticleImages) . ' gambar artikel lokal tidak ditemukan.', ['missing' => array_slice($missingArticleImages, 0, 20)]);

        foreach ([
            ROOT_PATH . '/robots.txt' => 'robots.txt',
            ROOT_PATH . '/sitemap.xml.php' => 'sitemap.xml.php',
            ROOT_PATH . '/feeds/rss.php' => 'RSS feed',
            ROOT_PATH . '/.htaccess' => '.htaccess',
            ROOT_PATH . '/manifest.json' => 'manifest.json',
        ] as $path => $label) {
            $checks[] = data_health_check('seo_file_' . md5($path), $label, is_file($path) ? 'ok' : 'warning', is_file($path) ? 'File tersedia.' : 'File belum ditemukan.', ['path' => $path]);
        }

        foreach ([
            'pages/order-status.php' => 'Public Order Status / Cek Order',
            'pages/order-success.php' => 'Public Order Success',
            'pages/invoice.php' => 'Public Invoice',
            'pages/payment-proof-submit.php' => 'Payment Proof Submit',
        ] as $file => $label) {
            $path = ROOT_PATH . '/' . $file;
            $checks[] = data_health_check('public_order_page_' . md5($file), $label, is_file($path) ? 'ok' : 'error', is_file($path) ? 'Halaman publik order tersedia.' : 'Halaman publik order hilang.', ['path' => $file]);
        }

        foreach ([
            'pages/admin-produk.php' => 'Admin Produk',
            'pages/admin-artikel.php' => 'Admin Artikel',
            'pages/admin-leads.php' => 'Admin Leads',
            'pages/admin-analytics.php' => 'Admin Analytics & Iklan',
            'pages/admin-marketing-integrations.php' => 'WhatsApp & Email Marketing',
            'pages/admin-commerce-insight.php' => 'Laporan & Insight Penjualan',
            'pages/admin-seo-quality.php' => 'Admin Cek SEO',
            'pages/admin-media-library.php' => 'Admin Media Library',
            'pages/admin-seo-landings.php' => 'Admin SEO Landings',
            'pages/seo-landing.php' => 'Public SEO Landing Renderer',
            'pages/admin-landing-pages.php' => 'Admin Landing Page Builder',
            'pages/landing-page.php' => 'Public Landing Page Renderer',
            'pages/admin-inquiries.php' => 'Admin Inquiries',
            'pages/admin-orders.php' => 'Admin Orders',
            'pages/admin-payment-settings.php' => 'Admin Payment Settings',
            'pages/admin-payment-gateway.php' => 'Admin Pembayaran Otomatis',
            'pages/payment-gateway-webhook.php' => 'Pembayaran Otomatis Webhook Endpoint',
            'pages/admin-payment-proofs.php' => 'Admin Payment Proofs',
            'pages/admin-payment-reminders.php' => 'Admin Payment Reminders',
            'pages/admin-transaction-audit.php' => 'Admin Transaction Audit',
            'pages/admin-activity-log.php' => 'Admin Activity Log',
            'pages/admin-data-health.php' => 'Admin Cek Sistem',
            'pages/admin-maintenance.php' => 'Admin Maintenance',
        ] as $file => $label) {
            $path = ROOT_PATH . '/' . $file;
            $checks[] = data_health_check('admin_page_' . md5($file), $label, is_file($path) ? 'ok' : 'error', is_file($path) ? 'Halaman admin tersedia.' : 'Halaman admin hilang.', ['path' => $file]);
        }

        $activityStats = function_exists('activity_log_stats') ? activity_log_stats() : ['total' => 0, 'writable' => false];
        $checks[] = data_health_check('activity_log', 'Activity Log', ($activityStats['writable'] ?? false) ? 'ok' : 'warning', ($activityStats['writable'] ?? false) ? 'Activity log writable.' : 'Activity log belum writable.', $activityStats);

        $summary = data_health_status_summary($checks);

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'site_url' => SITE_URL,
            'environment' => APP_ENV,
            'summary' => $summary,
            'checks' => $checks,
            'counts' => [
                'products' => count($products),
                'articles' => count($articles),
                'orders' => count($orders),
                'payment_proofs' => count($proofs),
                'inquiries' => count($inquiries),
                'followups' => count($followups),
                'notifications' => count($notifications),
                'seo_landings' => (int)($seoLandingSummary['counts']['indexable'] ?? 0),
                'payment_gateway_webhooks' => (int)($paymentGatewaySummary['webhook_events_30d'] ?? 0),
                'marketing_integration_logs' => (int)($marketingIntegrationSummary['recent_logs'] ?? 0),
                'server_conversion_pending' => (int)($serverConversionSummary['queue_counts']['pending'] ?? 0),
                'activity_log' => (int)($activityStats['total'] ?? 0),
            ],
        ];
    }
}
