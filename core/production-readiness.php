<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Template Production Release Candidate / Final Hardening
|--------------------------------------------------------------------------
| Lightweight go-live audit helpers for shared hosting/VPS deployment.
| This layer does not replace Cek Sistem; it summarizes the release gate.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('production_readiness_check')) {
    function production_readiness_check(string $key, string $label, string $status, string $message, array $meta = [], string $action = ''): array
    {
        $allowed = ['ok', 'warning', 'error', 'info'];
        $status = in_array($status, $allowed, true) ? $status : 'info';

        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'meta' => $meta,
            'action' => $action,
        ];
    }
}

if (!function_exists('production_readiness_count_statuses')) {
    function production_readiness_count_statuses(array $checks): array
    {
        $counts = ['ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = (string)($check['status'] ?? 'info');
            if (!isset($counts[$status])) {
                $status = 'info';
            }
            $counts[$status]++;
        }
        return $counts;
    }
}

if (!function_exists('production_readiness_score')) {
    function production_readiness_score(array $checks): int
    {
        if (empty($checks)) {
            return 0;
        }

        $weights = ['ok' => 100, 'info' => 82, 'warning' => 58, 'error' => 0];
        $total = 0;
        foreach ($checks as $check) {
            $status = (string)($check['status'] ?? 'info');
            $total += (int)($weights[$status] ?? 82);
        }

        return max(0, min(100, (int)round($total / max(1, count($checks)))));
    }
}

if (!function_exists('production_readiness_gate')) {
    function production_readiness_gate(array $checks): array
    {
        $counts = production_readiness_count_statuses($checks);
        $score = production_readiness_score($checks);
        $errors = (int)($counts['error'] ?? 0);
        $warnings = (int)($counts['warning'] ?? 0);

        if ($errors > 0) {
            return [
                'status' => 'error',
                'label' => 'Belum siap live',
                'message' => 'Masih ada blocking issue yang perlu dibereskan sebelum upload/live production.',
                'score' => $score,
            ];
        }

        if ($warnings > 0) {
            return [
                'status' => 'warning',
                'label' => 'Siap staging / perlu review',
                'message' => 'Tidak ada error fatal, tetapi beberapa warning masih perlu dicek sebelum iklan/traffic besar.',
                'score' => $score,
            ];
        }

        return [
            'status' => 'ok',
            'label' => 'Go-live ready',
            'message' => 'Checklist inti aman. Tetap lakukan backup dan smoke test setelah upload ke VPS/hosting.',
            'score' => $score,
        ];
    }
}

if (!function_exists('production_readiness_ini_bytes')) {
    function production_readiness_ini_bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $last = strtolower($value[strlen($value) - 1]);
        $number = (float)$value;
        return match ($last) {
            'g' => (int)($number * 1024 * 1024 * 1024),
            'm' => (int)($number * 1024 * 1024),
            'k' => (int)($number * 1024),
            default => (int)$number,
        };
    }
}

if (!function_exists('production_readiness_readable_size')) {
    function production_readiness_readable_size(int $bytes): string
    {
        if (function_exists('maintenance_readable_size')) {
            return maintenance_readable_size($bytes);
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = max(0, $bytes);
        $unit = 0;
        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }
        return rtrim(rtrim(number_format((float)$size, 2, '.', ''), '0'), '.') . ' ' . $units[$unit];
    }
}

if (!function_exists('production_readiness_feature_matrix')) {
    function production_readiness_feature_matrix(): array
    {
        return [
            'Landing Page Builder' => function_exists('landing_page_all'),
            'Template & Preset System' => function_exists('landing_page_templates_all') && function_exists('landing_page_preset_sections'),
            'Draft/Publish + Revision' => function_exists('landing_page_revision_count'),
            'Analisis Landing Page Dashboard' => function_exists('landing_page_analytics_report'),
            'Tes Variasi Tombol/Form' => function_exists('landing_page_ab_context'),
            'AI Copy / SEO Assistant' => function_exists('landing_page_ai_assistant_seed'),
            'Optimasi Landing Page Center' => function_exists('landing_page_optimization_report'),
            'Media & Gambar SEO Manager' => function_exists('media_library_scan'),
            'Maintenance Backup' => function_exists('maintenance_create_backup'),
            'Cek Sistem Audit' => function_exists('data_health_report'),
            'Member Area Buyer' => function_exists('buyer_account_current') && function_exists('member_access_record_for_order'),
            'Dashboard Member Polish' => function_exists('member_dashboard_summary') && is_file(ROOT_PATH . '/pages/member-area.php'),
        ];
    }
}

if (!function_exists('production_readiness_report')) {
    function production_readiness_report(): array
    {
        $checks = [];
        $isLocal = function_exists('app_is_localhost') ? app_is_localhost() : in_array($_SERVER['HTTP_HOST'] ?? 'localhost', ['localhost', '127.0.0.1'], true);
        $adminPassword = trim((string)($_ENV['ADMIN_PASSWORD'] ?? ''));
        $envPath = ROOT_PATH . '/.env';
        $envExamplePath = ROOT_PATH . '/.env.example';
        $siteUrl = (string)SITE_URL;
        $appUrlEnv = trim((string)($_ENV['APP_URL'] ?? ''));

        $checks[] = production_readiness_check(
            'app_version',
            'Status Aplikasi',
            defined('APP_VERSION') && APP_VERSION === 'Template' ? 'ok' : 'warning',
            'Versi aktif: ' . (defined('APP_VERSION') ? APP_VERSION : 'unknown') . '.',
            ['expected' => 'Template']
        );

        $checks[] = production_readiness_check(
            'php_version',
            'Versi PHP',
            version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'warning',
            'PHP aktif: ' . PHP_VERSION . '. Rekomendasi minimal PHP 8.1.',
            ['php_version' => PHP_VERSION],
            'Gunakan PHP 8.1 atau lebih baru di hosting.'
        );

        $checks[] = production_readiness_check(
            'app_env',
            'Deteksi Environment',
            APP_ENV === 'production' ? 'ok' : 'info',
            APP_ENV === 'production' ? 'Environment production terdeteksi.' : 'Environment development terdeteksi karena host lokal. Ini normal saat testing localhost.',
            ['APP_ENV' => APP_ENV, 'host' => function_exists('app_host') ? app_host() : ($_SERVER['HTTP_HOST'] ?? '')]
        );

        $siteUrlLooksLive = filter_var($siteUrl, FILTER_VALIDATE_URL) && !str_contains($siteUrl, 'domain-anda.com');
        $checks[] = production_readiness_check(
            'site_url',
            'APP_URL / SITE_URL',
            $siteUrlLooksLive ? 'ok' : ($isLocal ? 'info' : 'warning'),
            'SITE_URL saat ini: ' . $siteUrl . '.',
            ['APP_URL_ENV' => $appUrlEnv, 'SITE_URL' => $siteUrl],
            'Set APP_URL di .env sesuai domain final website.'
        );

        $checks[] = production_readiness_check(
            'env_file',
            'File Konfigurasi .env',
            is_file($envPath) ? 'ok' : ($isLocal ? 'info' : 'warning'),
            is_file($envPath) ? '.env tersedia di server.' : '.env belum ada di package/test folder. Buat dari .env.example saat live.',
            ['env_exists' => is_file($envPath), 'env_example_exists' => is_file($envExamplePath)],
            'Copy .env.example menjadi .env, lalu isi APP_URL, password admin, dan pengaturan pembayaran/analytics yang dipakai.'
        );

        $adminAuthReady = function_exists('admin_auth_password_needs_setup') ? !admin_auth_password_needs_setup() : ($adminPassword !== '' && function_exists('admin_password_needs_setup') && !admin_password_needs_setup($adminPassword));
        $checks[] = production_readiness_check(
            'admin_password',
            'Akses Owner Admin',
            $adminAuthReady ? 'ok' : 'warning',
            $adminAuthReady ? 'Akses owner/admin sudah aman.' : 'Akses owner/admin belum aman. Jalankan /install atau buat password kuat sebelum live.',
            ['configured' => $adminAuthReady],
            'Gunakan akun owner dengan password kuat minimal 12 karakter, gabungan huruf besar/kecil, angka, dan simbol.'
        );

        $checks[] = production_readiness_check(
            'app_debug',
            'Tampilan Error',
            APP_ENV === 'production' && APP_DEBUG ? 'error' : 'ok',
            APP_ENV === 'production' && APP_DEBUG ? 'APP_DEBUG aktif di production.' : 'Debug display aman sesuai environment.',
            ['APP_DEBUG' => APP_DEBUG, 'display_errors' => ini_get('display_errors')]
        );

        if (function_exists('data_health_php_extension_checks')) {
            $ext = data_health_php_extension_checks();
            $extStatus = (string)($ext['status'] ?? 'info');
            // Kesiapan Website memberi warning agar environment test/localhost tidak dianggap gagal total.
            // Detail extension wajib tetap muncul di meta/action untuk dibereskan saat setup hosting/VPS.
            if ($extStatus === 'error') {
                $extStatus = 'warning';
            }
            $checks[] = production_readiness_check(
                'php_extensions',
                'Ekstensi PHP',
                $extStatus,
                (string)($ext['message'] ?? 'Extension check selesai.'),
                (array)($ext['meta'] ?? []),
                'Aktifkan json, mbstring, openssl, fileinfo. GD/Imagick direkomendasikan untuk optimasi gambar.'
            );
        }

        $uploadMax = production_readiness_ini_bytes((string)ini_get('upload_max_filesize'));
        $postMax = production_readiness_ini_bytes((string)ini_get('post_max_size'));
        $checks[] = production_readiness_check(
            'upload_limits',
            'Batas Upload',
            ($uploadMax >= 5 * 1024 * 1024 && $postMax >= 8 * 1024 * 1024) ? 'ok' : 'warning',
            'upload_max_filesize: ' . (string)ini_get('upload_max_filesize') . ', post_max_size: ' . (string)ini_get('post_max_size') . '.',
            ['upload_max_bytes' => $uploadMax, 'post_max_bytes' => $postMax],
            'Untuk upload gambar dan bukti pembayaran, gunakan batas upload minimal 8M dan post minimal 12M.'
        );

        foreach ([
            STORAGE_PATH => 'Storage',
            LOGS_PATH => 'Logs',
            CACHE_PATH => 'Cache',
            ASSETS_PATH . '/uploads' => 'Assets Uploads',
            STORAGE_PATH . '/payment-proofs' => 'Payment Proof Uploads',
        ] as $path => $label) {
            $exists = is_dir((string)$path);
            $writable = $exists && is_writable((string)$path);
            $checks[] = production_readiness_check(
                'dir_' . md5((string)$path),
                $label . ' Writable',
                $exists && $writable ? 'ok' : ($exists ? 'warning' : 'error'),
                $exists ? ($writable ? $label . ' tersedia dan writable.' : $label . ' ada tapi belum writable.') : $label . ' belum tersedia.',
                ['path' => $path],
                'Pastikan permission folder runtime umumnya 775/755 sesuai owner web server.'
            );
        }

        if (function_exists('data_health_runtime_safety_files')) {
            $runtime = data_health_runtime_safety_files();
            $checks[] = production_readiness_check(
                'runtime_safety',
                'Proteksi Folder Data',
                (string)($runtime['status'] ?? 'info'),
                (string)($runtime['message'] ?? 'Runtime safety check selesai.'),
                (array)($runtime['meta'] ?? []),
                'Pastikan storage/logs/cache tidak bisa diakses publik, khususnya di Apache/LiteSpeed.'
            );
        }

        $rootHtaccess = ROOT_PATH . '/.htaccess';
        $rootHtaccessSource = is_file($rootHtaccess) ? (string)@file_get_contents($rootHtaccess) : '';
        $checks[] = production_readiness_check(
            'root_htaccess',
            'Proteksi .htaccess',
            (is_file($rootHtaccess) && str_contains($rootHtaccessSource, 'RewriteEngine On') && str_contains($rootHtaccessSource, 'BLOCK SENSITIVE FILES')) ? 'ok' : 'warning',
            is_file($rootHtaccess) ? 'Root .htaccess tersedia.' : 'Root .htaccess belum ditemukan.',
            ['rewrite' => str_contains($rootHtaccessSource, 'RewriteEngine On'), 'sensitive_block' => str_contains($rootHtaccessSource, 'BLOCK SENSITIVE FILES')],
            'Upload .htaccess jika memakai Apache/LiteSpeed. Untuk Nginx, gunakan aturan proteksi folder sesuai hosting.'
        );

        if (function_exists('data_health_route_target_files')) {
            $routes = data_health_route_target_files();
            $checks[] = production_readiness_check(
                'route_targets',
                'File Route Website',
                (string)($routes['status'] ?? 'info'),
                (string)($routes['message'] ?? 'Route target check selesai.'),
                (array)($routes['meta'] ?? [])
            );
        }

        $checks[] = production_readiness_check(
            'seo_files',
            'Robots & Sitemap',
            (is_file(ROOT_PATH . '/robots.txt') && is_file(ROOT_PATH . '/sitemap.xml.php')) ? 'ok' : 'warning',
            'robots.txt dan sitemap.xml.php ' . ((is_file(ROOT_PATH . '/robots.txt') && is_file(ROOT_PATH . '/sitemap.xml.php')) ? 'tersedia.' : 'perlu dicek.'),
            ['robots' => is_file(ROOT_PATH . '/robots.txt'), 'sitemap_php' => is_file(ROOT_PATH . '/sitemap.xml.php')]
        );

        $featureMatrix = production_readiness_feature_matrix();
        $missingFeatures = array_keys(array_filter($featureMatrix, static fn(bool $ready): bool => !$ready));
        $checks[] = production_readiness_check(
            'feature_matrix',
            'Fitur Website',
            empty($missingFeatures) ? 'ok' : 'warning',
            empty($missingFeatures) ? 'Fitur utama website terdeteksi.' : 'Ada fitur yang belum terdeteksi: ' . implode(', ', $missingFeatures),
            ['features' => $featureMatrix]
        );

        $products = function_exists('all_products') ? all_products() : [];
        $articles = function_exists('all_articles') ? all_articles() : [];
        $landingPages = function_exists('landing_page_all') ? landing_page_all(true) : [];
        $publishedLandingPages = array_values(array_filter($landingPages, static fn(array $page): bool => (string)($page['status'] ?? '') === 'published'));

        $checks[] = production_readiness_check('product_catalog', 'Katalog Produk', count($products) > 0 ? 'ok' : 'warning', count($products) . ' produk terdeteksi.', ['count' => count($products)]);
        $checks[] = production_readiness_check('article_content', 'Konten Artikel', count($articles) > 0 ? 'ok' : 'warning', count($articles) . ' artikel terdeteksi.', ['count' => count($articles)]);
        $checks[] = production_readiness_check('landing_pages', 'Landing Page', count($landingPages) > 0 ? 'ok' : 'warning', count($landingPages) . ' landing page terdeteksi, ' . count($publishedLandingPages) . ' published.', ['total' => count($landingPages), 'published' => count($publishedLandingPages)]);

        $memberPageSource = is_file(ROOT_PATH . '/pages/member-area.php') ? (string)@file_get_contents(ROOT_PATH . '/pages/member-area.php') : '';
        $memberPolishReady = function_exists('member_dashboard_summary')
            && function_exists('buyer_account_update_profile')
            && str_contains($memberPageSource, 'Akses Produk')
            && str_contains($memberPageSource, 'Riwayat Pembelian')
            && str_contains($memberPageSource, 'Profil & Password')
            && str_contains($memberPageSource, 'Bantuan')
            && str_contains($memberPageSource, 'update_profile');
        $checks[] = production_readiness_check(
            'member_area_buyer_experience',
            'Member Area Buyer Experience',
            $memberPolishReady ? 'ok' : 'warning',
            $memberPolishReady ? 'Dashboard pembeli sudah punya akses produk, riwayat pembelian, profil/password, bantuan, dan profile update.' : 'Member Area perlu dicek lagi agar pengalaman buyer tidak membingungkan.',
            ['dashboard_helper' => function_exists('member_dashboard_summary'), 'profile_update' => function_exists('buyer_account_update_profile'), 'member_page' => is_file(ROOT_PATH . '/pages/member-area.php')],
            'Pastikan buyer bisa login, melihat produk, cek riwayat, update profil, dan minta bantuan dari member area.'
        );

        if (function_exists('landing_page_optimization_report')) {
            $lpOpt = landing_page_optimization_report(30, []);
            $summary = (array)($lpOpt['summary'] ?? []);
            $issueCount = (int)($summary['needs_fix'] ?? 0) + (int)($summary['critical'] ?? 0);
            $checks[] = production_readiness_check(
                'lp_optimization',
                'Pemeriksaan Landing Page',
                $issueCount > 0 ? 'warning' : 'ok',
                $issueCount > 0 ? $issueCount . ' Landing Page masuk prioritas perbaikan.' : 'Tidak ada catatan prioritas tinggi pada landing page.',
                ['avg_score' => $summary['avg_score'] ?? null, 'ready' => $summary['ready'] ?? null, 'needs_fix' => $summary['needs_fix'] ?? null, 'critical' => $summary['critical'] ?? null],
                'Buka halaman Optimasi Landing Page sebelum menjalankan promosi iklan besar.'
            );
        }

        if (function_exists('media_library_summary')) {
            $mediaSummary = media_library_summary();
            $checks[] = production_readiness_check(
                'media_asset_seo',
                'Media & Gambar SEO',
                ((int)($mediaSummary['missing_alt'] ?? 0) > 0 || (int)($mediaSummary['large_images'] ?? 0) > 0) ? 'warning' : 'ok',
                'Asset score: ' . (string)($mediaSummary['score'] ?? $mediaSummary['asset_score'] ?? '-') . '. Alt kosong: ' . (int)($mediaSummary['missing_alt'] ?? 0) . ', gambar besar: ' . (int)($mediaSummary['large_images'] ?? 0) . '.',
                $mediaSummary,
                'Buka Media & Gambar SEO untuk bulk alt text dan audit gambar sebelum live final.'
            );
        }

        if (function_exists('landing_page_analytics_report')) {
            $lpAnalytics = landing_page_analytics_report(30, []);
            $checks[] = production_readiness_check(
                'lp_analytics_tracking',
                'Tracking Landing Page',
                !empty($lpAnalytics['tracking_ready']) ? 'ok' : 'warning',
                !empty($lpAnalytics['tracking_ready']) ? 'Tracking Landing Page siap membaca kunjungan, Tombol, form, lead, order, sumber promosi, dan A/B variasi.' : 'Landing Page analytics ada, tetapi kesiapan tracking perlu dicek.',
                ['totals' => $lpAnalytics['totals'] ?? [], 'tracking_ready' => $lpAnalytics['tracking_ready'] ?? null],
                'Pastikan pencatatan lead bisa diakses dan tidak diblokir cache atau keamanan server.'
            );
        }

        $backupDir = function_exists('maintenance_backup_dir') ? maintenance_backup_dir() : CACHE_PATH . '/maintenance-backups';
        $checks[] = production_readiness_check(
            'backup_readiness',
            'Backup & Perawatan',
            (function_exists('maintenance_create_backup') && is_dir($backupDir) && is_writable($backupDir)) ? 'ok' : 'warning',
            function_exists('maintenance_create_backup') ? 'Fitur backup tersedia.' : 'Fitur backup belum tersedia.',
            ['backup_dir' => $backupDir, 'backup_dir_writable' => is_dir($backupDir) && is_writable($backupDir), 'zip_archive' => class_exists('ZipArchive'), 'fallback_zip' => function_exists('maintenance_create_backup')],
            'Download backup ZIP dari halaman Perawatan Website sebelum upload versi baru.'
        );

        if (function_exists('db_configured') && function_exists('db_available')) {
            $configured = db_configured();
            $available = db_available();
            $storageReport = function_exists('storage_adapter_report') ? storage_adapter_report() : [];
            $storageDriver = (string)($storageReport['driver'] ?? 'file');
            $activeMysqlCollections = (int)($storageReport['summary']['mysql_active_collections'] ?? 0);
            $checks[] = production_readiness_check(
                'database_mode',
                'Mode Database / JSON',
                ($storageDriver === 'file' || $available) ? 'ok' : 'warning',
                $configured ? ($available ? 'Database MySQL terkoneksi. Mode storage: ' . strtoupper($storageDriver) . '. Collection MySQL aktif: ' . $activeMysqlCollections . '.' : 'Konfigurasi database ada, tapi koneksi belum tersedia. Fallback JSON masih menjaga website tetap jalan.') : 'Database belum dikonfigurasi. Mode JSON aktif dan tetap bisa dipakai untuk website ringan.',
                ['configured' => $configured, 'available' => $available, 'storage_driver' => $storageDriver, 'mysql_active_collections' => $activeMysqlCollections]
            );
        }

        $counts = production_readiness_count_statuses($checks);
        $gate = production_readiness_gate($checks);

        return [
            'generated_at' => date('c'),
            'environment' => APP_ENV,
            'site_url' => SITE_URL,
            'app_version' => defined('APP_VERSION') ? APP_VERSION : 'unknown',
            'score' => production_readiness_score($checks),
            'gate' => $gate,
            'summary' => [
                'overall' => (string)($gate['status'] ?? 'info'),
                'counts' => $counts,
            ],
            'counts' => [
                'products' => count($products),
                'articles' => count($articles),
                'landing_pages' => count($landingPages),
                'landing_pages_published' => count($publishedLandingPages),
                'features_ready' => count(array_filter($featureMatrix)),
                'features_total' => count($featureMatrix),
            ],
            'checks' => $checks,
            'launch_steps' => [
                'Backup data dari Maintenance Center.',
                'Upload sumber website ke hosting.',
                'Copy .env.example menjadi .env dan isi APP_URL + Password Admin kuat.',
                'Pastikan folder storage, logs, cache, dan assets/uploads writable.',
                'Buka Cek Sistem dan Kesiapan Website untuk cek warning terakhir.',
                'Smoke test halaman publik: /, /katalog, /artikel, /lp/{slug}, /checkout, /order-status.',
                'Clear cache setelah upload dan sebelum traffic iklan besar.',
            ],
        ];
    }
}

if (!function_exists('production_readiness_csv_rows')) {
    function production_readiness_csv_rows(array $report): array
    {
        $rows = [];
        foreach ((array)($report['checks'] ?? []) as $check) {
            $rows[] = [
                'status' => (string)($check['status'] ?? 'info'),
                'check' => (string)($check['label'] ?? ''),
                'message' => (string)($check['message'] ?? ''),
                'action' => (string)($check['action'] ?? ''),
                'meta' => json_encode($check['meta'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }
        return $rows;
    }
}
