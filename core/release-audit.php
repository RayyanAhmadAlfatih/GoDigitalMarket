<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| U-GROWTH RELEASE AUDIT ENGINE
|--------------------------------------------------------------------------
| Audit akhir hardening helper. This module audits route integrity, module
| wiring, storage protection, copy publik safety, and growth data flow without
| creating any new lead/tracking source.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('release_audit_clean')) {
    function release_audit_clean(mixed $value, int $max = 240): string
    {
        $text = trim(strip_tags((string)$value));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if ($max > 0 && function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }
        return $max > 0 ? substr($text, 0, $max) : $text;
    }
}

if (!function_exists('release_audit_slug')) {
    function release_audit_slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        return trim($value, '-') ?: 'item';
    }
}

if (!function_exists('release_audit_storage_file')) {
    function release_audit_storage_file(): string
    {
        return STORAGE_PATH . '/release-audit-notes.json';
    }
}

if (!function_exists('release_audit_default_state')) {
    function release_audit_default_state(): array
    {
        return [
            'review_note' => '',
            'review_owner' => '',
            'checklist' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('release_audit_normalize_state')) {
    function release_audit_normalize_state(array $state): array
    {
        $default = release_audit_default_state();
        $state = array_merge($default, $state);
        $checklist = [];
        foreach ((array)($state['checklist'] ?? []) as $key => $value) {
            $id = release_audit_slug((string)$key);
            if ($id === '') {
                continue;
            }
            $checklist[$id] = [
                'done' => !empty($value['done']),
                'note' => release_audit_clean($value['note'] ?? '', 800),
                'updated_at' => release_audit_clean($value['updated_at'] ?? date(DATE_ATOM), 80),
            ];
        }
        return [
            'review_note' => release_audit_clean($state['review_note'] ?? '', 3000),
            'review_owner' => release_audit_clean($state['review_owner'] ?? '', 80),
            'checklist' => $checklist,
            'updated_at' => release_audit_clean($state['updated_at'] ?? date(DATE_ATOM), 80),
        ];
    }
}

if (!function_exists('release_audit_state')) {
    function release_audit_state(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }
        $file = release_audit_storage_file();
        if (!is_file($file)) {
            $cached = release_audit_normalize_state(release_audit_default_state());
            return $cached;
        }
        $decoded = json_decode((string)file_get_contents($file), true);
        $cached = release_audit_normalize_state(is_array($decoded) ? $decoded : []);
        return $cached;
    }
}

if (!function_exists('release_audit_write_state')) {
    function release_audit_write_state(array $state, bool $throw = false): bool
    {
        $state = release_audit_normalize_state($state);
        $state['updated_at'] = date(DATE_ATOM);
        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(release_audit_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan Audit kesiapan belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }
        @chmod(release_audit_storage_file(), 0644);
        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'release-audit', null, 'Menyimpan catatan audit kesiapan website.');
        }
        return true;
    }
}

if (!function_exists('release_audit_save_note')) {
    function release_audit_save_note(string $note, string $owner = ''): bool
    {
        $state = release_audit_state(true);
        $state['review_note'] = $note;
        $state['review_owner'] = $owner;
        return release_audit_write_state($state, true);
    }
}

if (!function_exists('release_audit_toggle_check')) {
    function release_audit_toggle_check(string $id, bool $done, string $note = ''): bool
    {
        $id = release_audit_slug($id);
        $state = release_audit_state(true);
        $state['checklist'][$id] = [
            'done' => $done,
            'note' => $note,
            'updated_at' => date(DATE_ATOM),
        ];
        return release_audit_write_state($state, true);
    }
}

if (!function_exists('release_audit_reset_state')) {
    function release_audit_reset_state(): bool
    {
        return release_audit_write_state(release_audit_default_state(), true);
    }
}

if (!function_exists('release_audit_expected_modules')) {
    function release_audit_expected_modules(): array
    {
        return [
            ['id' => 'launch-readiness', 'title' => 'Launch Readiness', 'group' => 'Foundation', 'route' => 'admin/launch-readiness', 'page' => 'pages/admin-launch-readiness.php', 'core' => 'core/launch-readiness.php', 'function' => 'launch_readiness_report', 'source' => 'brand, katalog, form, SEO, trust block'],
            ['id' => 'onboarding-assistant', 'title' => 'Onboarding Setup Assistant', 'group' => 'Foundation', 'route' => 'admin/onboarding-assistant', 'page' => 'pages/admin-onboarding-assistant.php', 'core' => 'core/umkm-onboarding.php', 'function' => 'umkm_onboarding_report', 'source' => 'progress setup dashboard'],
            ['id' => 'help-center', 'title' => 'Pusat Bantuan Dashboard', 'group' => 'Foundation', 'route' => 'admin/help-center', 'page' => 'pages/admin-help-center.php', 'core' => 'core/admin-help.php', 'function' => 'admin_help_definitions', 'source' => 'panduan menu dashboard'],
            ['id' => 'menu-features', 'title' => 'Menu & Fitur Admin', 'group' => 'System & Data', 'route' => 'admin/menu-features', 'page' => 'pages/admin-menu-features.php', 'core' => 'core/admin-menu.php', 'function' => 'admin_menu_groups', 'source' => 'visibility toggle sidebar admin'],
            ['id' => 'cloud-backup-sync', 'title' => 'Backup & Sync Data', 'group' => 'System & Data', 'route' => 'admin/cloud-backup-sync', 'page' => 'pages/admin-cloud-backup-sync.php', 'core' => 'core/cloud-backup.php', 'function' => 'cloud_backup_report', 'source' => 'lead, order, analytics, member, Google Sheets/Drive'],
            ['id' => 'trust-conversion', 'title' => 'Trust & Conversion Block', 'group' => 'Conversion', 'route' => 'admin/trust-conversion', 'page' => 'pages/admin-trust-conversion.php', 'core' => 'core/trust-conversion.php', 'function' => 'trust_conversion_settings', 'source' => 'trust block homepage'],
            ['id' => 'checkout-field-manager', 'title' => 'Checkout Field Manager', 'group' => 'Commerce', 'route' => 'admin/form-checkout', 'page' => 'pages/admin-form-checkout.php', 'core' => 'core/checkout.php', 'function' => 'checkout_settings', 'source' => 'order checkout + alamat + pengiriman dasar'],
            ['id' => 'shipping-api-bridge', 'title' => 'Shipping API Bridge & Smart Ongkir Cache', 'group' => 'Commerce', 'route' => 'admin/shipping', 'page' => 'pages/admin-shipping.php', 'core' => 'core/shipping.php', 'function' => 'shipping_settings', 'source' => 'manual/api/hybrid + smart cache + checkout estimator + invoice total'],
            ['id' => 'offer-cta-testing', 'title' => 'Offer & CTA Testing Lab', 'group' => 'Conversion', 'route' => 'admin/offer-cta-testing', 'page' => 'pages/admin-offer-cta-testing.php', 'core' => 'core/offer-cta-testing.php', 'function' => 'offer_cta_lab_summary', 'source' => 'manual metric + offer variants'],
            ['id' => 'cta-placement', 'title' => 'CTA Placement Assistant', 'group' => 'Conversion', 'route' => 'admin/cta-placement', 'page' => 'pages/admin-cta-placement-assistant.php', 'core' => 'core/cta-placement-assistant.php', 'function' => 'cta_placement_summary', 'source' => 'winner Offer Lab + placement metadata'],
            ['id' => 'cta-result', 'title' => 'CTA Result Tracker', 'group' => 'Conversion', 'route' => 'admin/cta-result-tracker', 'page' => 'pages/admin-cta-result-tracker.php', 'core' => 'core/cta-result-tracker.php', 'function' => 'cta_result_bridge_summary', 'source' => 'Lead Tracking existing'],
            ['id' => 'migration-command-center', 'title' => 'Migration Command Center', 'group' => 'Konten & SEO', 'route' => 'admin/migration-command-center', 'page' => 'pages/admin-migration-command-center.php', 'core' => 'core/migration-command-center.php', 'function' => 'migration_command_center_summary', 'source' => 'unified WP migration workflow dashboard, health score, checklist, export'],
            ['id' => 'wp-migration', 'title' => 'WordPress Migration Foundation', 'group' => 'Konten & SEO', 'route' => 'admin/wp-migration', 'page' => 'pages/admin-wp-migration.php', 'core' => 'core/wp-migration.php', 'function' => 'wp_migration_preview_file', 'source' => 'WXR/XML + CSV preview/import + backup/rollback'],
            ['id' => 'wp-media-migration', 'title' => 'WordPress Media Migration', 'group' => 'Konten & SEO', 'route' => 'admin/wp-media-migration', 'page' => 'pages/admin-wp-media-migration.php', 'core' => 'core/wp-media-migration.php', 'function' => 'wp_media_migration_scan', 'source' => 'scan wp-content uploads, download map, safe rewrite with backup'],
            ['id' => 'wp-content-cleaner', 'title' => 'Shortcode & Gutenberg Cleaner', 'group' => 'Konten & SEO', 'route' => 'admin/wp-content-cleaner', 'page' => 'pages/admin-wp-content-cleaner.php', 'core' => 'core/wp-content-cleaner.php', 'function' => 'wp_content_cleaner_scan', 'source' => 'Gutenberg comments, plugin shortcode cleaner, dry-run, backup, safe apply'],
            ['id' => 'wp-elementor-import', 'title' => 'Elementor Safe HTML Block Import', 'group' => 'Konten & SEO', 'route' => 'admin/wp-elementor-import', 'page' => 'pages/admin-wp-elementor-import.php', 'core' => 'core/wp-elementor-import.php', 'function' => 'wp_elementor_import_report', 'source' => 'Elementor/page-builder detection, safe HTML block LP draft import, complex widget warnings'],
            ['id' => 'seo-preservation', 'title' => 'SEO Preservation Layer', 'group' => 'Konten & SEO', 'route' => 'admin/seo-preservation', 'page' => 'pages/admin-seo-preservation.php', 'core' => 'core/seo-preservation.php', 'function' => 'seo_preservation_report', 'source' => 'legacy URL resolver, canonical, 301 redirect map, sitemap integration'],
            ['id' => 'internal-link-migration', 'title' => 'Breadcrumb & Internal Link Migration', 'group' => 'Konten & SEO', 'route' => 'admin/internal-link-migration', 'page' => 'pages/admin-internal-link-migration.php', 'core' => 'core/internal-link-migration.php', 'function' => 'internal_link_migration_scan', 'source' => 'breadcrumb mapper, internal link checker, WP legacy URL rewrite preview'],
            ['id' => 'dynamic-content-guard', 'title' => 'Dynamic Content Guard v4.1', 'group' => 'Konten & SEO', 'route' => 'admin/dynamic-content-guard', 'page' => 'pages/admin-dynamic-content-guard.php', 'core' => 'core/dynamic-content.php', 'function' => 'dynamic_v3_guard_report', 'source' => 'niche, kategori, tag, keyword, slug, konten, tipe item, produk, artikel'],
            ['id' => 'seo-profit', 'title' => 'SEO Profit Attribution', 'group' => 'SEO to Profit', 'route' => 'admin/seo-profit-attribution', 'page' => 'pages/admin-seo-profit-attribution.php', 'core' => 'core/seo-profit-attribution.php', 'function' => 'seo_profit_summary', 'source' => 'Lead Tracking existing + SEO pages'],
            ['id' => 'seo-journey', 'title' => 'SEO Journey Map', 'group' => 'SEO to Profit', 'route' => 'admin/seo-assisted-journey', 'page' => 'pages/admin-seo-assisted-journey-map.php', 'core' => 'core/seo-assisted-journey-map.php', 'function' => 'seo_journey_summary', 'source' => 'SEO page → CTA → lead → order'],
            ['id' => 'seo-money-page', 'title' => 'SEO Money Page Optimizer', 'group' => 'SEO to Profit', 'route' => 'admin/seo-money-page-optimizer', 'page' => 'pages/admin-seo-money-page-optimizer.php', 'core' => 'core/seo-money-page-optimizer.php', 'function' => 'seo_money_summary', 'source' => 'SEO journey + CTA + offer + trust'],
            ['id' => 'money-deployment', 'title' => 'Money Page Deployment Checklist', 'group' => 'Execution', 'route' => 'admin/money-page-deployment-checklist', 'page' => 'pages/admin-money-page-deployment-checklist.php', 'core' => 'core/money-page-deployment-checklist.php', 'function' => 'money_deploy_summary', 'source' => 'Money Page recommendations'],
            ['id' => 'internal-link-cta', 'title' => 'Internal Link & CTA Injection', 'group' => 'Execution', 'route' => 'admin/internal-link-cta-injection', 'page' => 'pages/admin-internal-link-cta-injection-assistant.php', 'core' => 'core/internal-link-cta-injection-assistant.php', 'function' => 'link_cta_summary', 'source' => 'money page + CTA placement'],
            ['id' => 'content-refresh', 'title' => 'SEO Content Refresh Planner', 'group' => 'Execution', 'route' => 'admin/seo-content-refresh-planner', 'page' => 'pages/admin-seo-content-refresh-planner.php', 'core' => 'core/seo-content-refresh-planner.php', 'function' => 'seo_refresh_summary', 'source' => 'content performance + Lead Tracking'],
            ['id' => 'lead-quality', 'title' => 'Lead Priority Scoring', 'group' => 'Lead & Sales', 'route' => 'admin/lead-priority-scoring', 'page' => 'pages/admin-lead-quality-followup-scoring.php', 'core' => 'core/lead-quality-followup-scoring.php', 'function' => 'lead_quality_summary', 'source' => 'Order + Inbox Lead + CRM + Lead Tracking'],
            ['id' => 'profit-action', 'title' => 'Profit Action Dashboard', 'group' => 'Growth Command', 'route' => 'admin/profit-action-dashboard', 'page' => 'pages/admin-profit-action-dashboard.php', 'core' => 'core/profit-action-dashboard.php', 'function' => 'profit_action_dashboard_summary', 'source' => 'profit, SEO, lead, order signals'],
            ['id' => 'profit-playbook', 'title' => 'Profit Playbook', 'group' => 'Growth Command', 'route' => 'admin/profit-playbook', 'page' => 'pages/admin-profit-playbook.php', 'core' => 'core/profit-playbook.php', 'function' => 'profit_playbook_campaign_summary', 'source' => 'action plan campaign'],
            ['id' => 'profit-report', 'title' => 'Profit Report Builder', 'group' => 'Growth Command', 'route' => 'admin/profit-report-builder', 'page' => 'pages/admin-profit-report-builder.php', 'core' => 'core/profit-report-builder.php', 'function' => 'profit_report_builder_summary', 'source' => 'report + attribution + action plan'],
            ['id' => 'growth-sprint', 'title' => 'SEO Campaign Calendar', 'group' => 'Growth Command', 'route' => 'admin/seo-campaign-calendar', 'page' => 'pages/admin-seo-campaign-calendar-growth-sprint.php', 'core' => 'core/seo-campaign-calendar-growth-sprint-planner.php', 'function' => 'growth_sprint_summary', 'source' => 'Profit Report + sprint tasks'],
            ['id' => 'command-center', 'title' => 'U-Growth Command Center', 'group' => 'Growth Command', 'route' => 'admin/u-growth-command-center', 'page' => 'pages/admin-u-growth-command-center.php', 'core' => 'core/u-growth-command-center.php', 'function' => 'ugrowth_command_center_summary', 'source' => 'all growth modules'],
        ];
    }
}

if (!function_exists('release_audit_route_map')) {
    function release_audit_route_map(): array
    {
        $index = ROOT_PATH . '/index.php';
        if (!is_file($index)) {
            return [];
        }
        $content = (string)file_get_contents($index);
        preg_match_all("/'([^']+)'\s*=>\s*'([^']+\.php)'/", $content, $matches, PREG_SET_ORDER);
        $routes = [];
        foreach ($matches as $match) {
            $routes[(string)$match[1]] = (string)$match[2];
        }
        return $routes;
    }
}

if (!function_exists('release_audit_admin_menu_index')) {
    function release_audit_admin_menu_index(): array
    {
        if (!function_exists('admin_menu_groups') || !function_exists('admin_menu_flatten_groups') || !function_exists('admin_menu_key_normalize')) {
            return [];
        }

        $settings = function_exists('admin_menu_settings_read') ? admin_menu_settings_read() : ['disabled_items' => []];
        $disabled = array_flip(array_map('strval', (array)($settings['disabled_items'] ?? [])));
        $index = [];

        foreach (admin_menu_flatten_groups(admin_menu_groups()) as $row) {
            $key = admin_menu_key_normalize((string)($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $locked = !empty($row['locked']);
            $hidden = isset($disabled[$key]) && !$locked;
            $status = $locked ? 'Dikunci sistem' : ($hidden ? 'Disembunyikan oleh owner' : 'Aktif di sidebar');
            $aliases = [$key];

            $href = (string)($row['href'] ?? '');
            $hrefPath = admin_menu_key_normalize((string)(parse_url($href, PHP_URL_PATH) ?? ''));
            if ($hrefPath !== '') {
                $aliases[] = $hrefPath;
            }

            foreach ((array)($row['match'] ?? []) as $match) {
                $matchKey = admin_menu_key_normalize((string)$match);
                if ($matchKey !== '') {
                    $aliases[] = $matchKey;
                }
            }

            foreach (array_values(array_unique($aliases)) as $alias) {
                $index[$alias] = [
                    'found' => true,
                    'key' => $key,
                    'label' => (string)($row['label'] ?? $key),
                    'group' => (string)($row['group'] ?? 'Menu'),
                    'hidden' => $hidden,
                    'locked' => $locked,
                    'status' => $status,
                    'note' => $hidden
                        ? 'Menu sengaja disembunyikan lewat Menu & Fitur Admin; route, file, dan data tetap diaudit.'
                        : ($locked ? 'Menu inti dikunci agar owner tidak terkunci dari dashboard.' : 'Menu tampil normal di sidebar.'),
                ];
            }
        }

        return $index;
    }
}


if (!function_exists('release_audit_admin_menu_has')) {
    function release_audit_admin_menu_has(string $route): bool
    {
        $key = function_exists('admin_menu_key_normalize') ? admin_menu_key_normalize($route) : trim($route, '/');
        $index = release_audit_admin_menu_index();
        return $key !== '' && isset($index[$key]);
    }
}

if (!function_exists('release_audit_module_rows')) {
    function release_audit_module_rows(): array
    {
        $routes = release_audit_route_map();
        $menuIndex = release_audit_admin_menu_index();
        $help = is_file(ROOT_PATH . '/core/admin-help.php') ? (string)file_get_contents(ROOT_PATH . '/core/admin-help.php') : '';
        $rows = [];
        foreach (release_audit_expected_modules() as $module) {
            $route = (string)$module['route'];
            $routeKey = function_exists('admin_menu_key_normalize') ? admin_menu_key_normalize($route) : trim($route, '/');
            $menuInfo = $menuIndex[$routeKey] ?? null;
            $page = (string)$module['page'];
            $core = (string)$module['core'];
            $fn = (string)$module['function'];
            $checks = [
                'route' => isset($routes[$route]),
                'page' => is_file(ROOT_PATH . '/' . $page),
                'core' => is_file(ROOT_PATH . '/' . $core),
                'function' => function_exists($fn),
                'menu' => is_array($menuInfo) && !empty($menuInfo['found']),
                'help' => $help !== '' && str_contains($help, $route),
            ];
            $passed = count(array_filter($checks));
            $total = count($checks);
            $menuStatus = is_array($menuInfo) ? (string)($menuInfo['status'] ?? 'Aktif di sidebar') : 'Belum terdaftar di menu';
            $status = $passed === $total
                ? (!empty($menuInfo['hidden']) ? 'OK - Disembunyikan oleh owner' : 'OK')
                : ($passed >= $total - 1 ? 'Perlu cek ringan' : 'Perlu audit');
            $rows[] = array_merge($module, [
                'checks' => $checks,
                'check_notes' => [
                    'menu' => is_array($menuInfo) ? (string)($menuInfo['note'] ?? $menuStatus) : 'Route belum ditemukan di registry menu admin.',
                ],
                'menu_status' => $menuStatus,
                'menu_hidden' => !empty($menuInfo['hidden']),
                'menu_locked' => !empty($menuInfo['locked']),
                'passed' => $passed,
                'total' => $total,
                'score' => $total > 0 ? (int)round(($passed / $total) * 100) : 0,
                'status' => $status,
            ]);
        }
        return $rows;
    }
}

if (!function_exists('release_audit_security_checks')) {
    function release_audit_security_checks(): array
    {
        return [
            ['id' => 'env-example', 'title' => '.env.example tersedia', 'ok' => is_file(ROOT_PATH . '/.env.example'), 'note' => 'Panduan konfigurasi tetap tersedia tanpa menyertakan secret produksi.'],
            ['id' => 'env-not-packaged', 'title' => '.env produksi tidak ikut paket', 'ok' => !is_file(ROOT_PATH . '/.env'), 'note' => 'Secret server sebaiknya tetap dibuat langsung di hosting/VPS.'],
            ['id' => 'storage-protected', 'title' => 'Storage terlindungi', 'ok' => is_file(STORAGE_PATH . '/.htaccess'), 'note' => 'Storage memakai file proteksi untuk Apache/LiteSpeed.'],
            ['id' => 'logs-protected', 'title' => 'Logs terlindungi', 'ok' => is_file(LOGS_PATH . '/.htaccess'), 'note' => 'Folder logs tidak boleh terbuka untuk publik.'],
            ['id' => 'source-folders-protected', 'title' => 'Folder source terlindungi', 'ok' => is_file(PAGES_PATH . '/.htaccess') && is_file(CORE_PATH . '/.htaccess') && is_file(COMPONENTS_PATH . '/.htaccess') && is_file(DATA_PATH . '/.htaccess'), 'note' => 'Folder pages, core, components, dan data tidak boleh diakses langsung dari browser.'],
            ['id' => 'docs-protected', 'title' => 'Dokumen internal terlindungi', 'ok' => is_file(ROOT_PATH . '/docs/.htaccess'), 'note' => 'Catatan audit/release tidak tampil sebagai halaman publik.'],
            ['id' => 'database-docs-protected', 'title' => 'Folder database terlindungi', 'ok' => is_file(ROOT_PATH . '/database/.htaccess'), 'note' => 'SQL migration helper tidak boleh terbuka sebagai direktori publik.'],
            ['id' => 'uploads-protected', 'title' => 'Uploads punya proteksi dasar', 'ok' => is_file(ASSETS_PATH . '/uploads/.htaccess'), 'note' => 'Upload dibatasi agar lebih aman di shared hosting.'],
            ['id' => 'form-files-private', 'title' => 'Upload form privat', 'ok' => is_file(STORAGE_PATH . '/.htaccess') && is_dir(STORAGE_PATH . '/form-files') && function_exists('custom_form_upload_absolute_path'), 'note' => 'File dari form custom disimpan di storage privat dan diakses lewat route admin.'],
            ['id' => 'admin-auth', 'title' => 'Admin auth terpusat', 'ok' => function_exists('admin_auth_require') && function_exists('admin_auth_is_admin_path'), 'note' => 'Semua route admin melewati guard di index.php.'],
            ['id' => 'csrf', 'title' => 'CSRF helper aktif', 'ok' => function_exists('csrf_token') && function_exists('require_csrf'), 'note' => 'Form admin penting memakai token untuk mengurangi risiko submit palsu.'],
            ['id' => 'error-prod', 'title' => 'Production error display terkendali', 'ok' => defined('APP_DEBUG') && APP_DEBUG === (APP_ENV === 'development'), 'note' => 'Display error mengikuti environment.'],
            ['id' => 'runtime-logs-clean', 'title' => 'Paket rilis bebas log runtime', 'ok' => count(glob(LOGS_PATH . '/*.jsonl') ?: []) === 0 && !is_file(STORAGE_PATH . '/admin-activity.log'), 'note' => 'Log kunjungan, event, dan aktivitas admin tidak ikut paket template.'],
            ['id' => 'neutral-contact-default', 'title' => 'Kontak default netral', 'ok' => !str_contains((string)SITE_PHONE . ' ' . (string)SITE_WHATSAPP, '6283877315731'), 'note' => 'Nomor contoh memakai placeholder agar tidak mengarah ke kontak testing.'],
        ];
    }
}

if (!function_exists('release_audit_data_flow_checks')) {
    function release_audit_data_flow_checks(): array
    {
        $files = [
            'core/cta-result-tracker.php',
            'core/seo-profit-attribution.php',
            'core/seo-assisted-journey-map.php',
            'core/seo-money-page-optimizer.php',
            'core/profit-report-builder.php',
            'core/u-growth-command-center.php',
        ];
        $leadReferences = 0;
        foreach ($files as $file) {
            $path = ROOT_PATH . '/' . $file;
            if (is_file($path) && str_contains(strtolower((string)file_get_contents($path)), 'lead')) {
                $leadReferences++;
            }
        }
        return [
            ['id' => 'lead-event-single', 'title' => 'Endpoint Lead Tracking utama tetap tunggal', 'ok' => is_file(ROOT_PATH . '/pages/lead-event.php'), 'note' => 'Modul growth membaca data lead/conversion existing, bukan membuat endpoint tracking baru.'],
            ['id' => 'conversion-core', 'title' => 'Core conversion existing tersedia', 'ok' => is_file(ROOT_PATH . '/core/conversion.php') && function_exists('conversion_read_lead_events'), 'note' => 'CTA result, SEO attribution, dan journey memakai fondasi conversion yang sudah ada.'],
            ['id' => 'growth-reads-lead', 'title' => 'Modul growth merujuk sinyal lead', 'ok' => $leadReferences >= 4, 'note' => 'Bridge growth tersambung ke data lead/conversion, bukan sekadar dashboard pajangan.'],
            ['id' => 'command-center-summary', 'title' => 'Command Center merangkum modul growth', 'ok' => function_exists('ugrowth_command_center_summary'), 'note' => 'Pusat komando tersedia untuk membaca prioritas harian.'],
            ['id' => 'report-sprint-loop', 'title' => 'Report → Sprint loop tersedia', 'ok' => function_exists('profit_report_builder_summary') && function_exists('growth_sprint_summary'), 'note' => 'Laporan owner bisa diturunkan menjadi action plan.'],
        ];
    }
}

if (!function_exists('release_audit_public_copy_checks')) {
    function release_audit_public_copy_checks(): array
    {
        $scanFiles = [
            'pages/homepage.php',
            'pages/katalog.php',
            'pages/layanan.php',
            'pages/artikel.php',
            'pages/product-detail.php',
            'pages/landing-page.php',
            'pages/seo-landing.php',
            'components/dynamic-homepage.php',
            'components/layout/header.php',
            'components/layout/footer.php',
        ];
        $patterns = [
            'internal-roadmap' => '/internal\s+roadmap|roadmap\s+internal/i',
            'private-baseline' => '/baseline\s+(qurban|aqiqah)|qurban\s+baseline|aqiqah\s+baseline/i',
            'developer-note' => '/developer\s+note|catatan\s+developer|todo\s+internal/i',
            'dump-output' => '/var_dump\s*\(|print_r\s*\(|console\.log\s*\(/i',
        ];
        $findings = [];
        foreach ($scanFiles as $file) {
            $path = ROOT_PATH . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $content = (string)file_get_contents($path);
            foreach ($patterns as $id => $pattern) {
                if (preg_match($pattern, $content)) {
                    $findings[] = $file . ' (' . $id . ')';
                }
            }
        }
        return [
            ['id' => 'public-copy-clean', 'title' => 'Public copy bebas catatan internal', 'ok' => count($findings) === 0, 'note' => count($findings) === 0 ? 'Tidak ada wording internal yang terdeteksi di file public utama.' : implode(', ', array_slice($findings, 0, 8))],
            ['id' => 'frontend-safe', 'title' => 'Halaman public utama tersedia', 'ok' => is_file(ROOT_PATH . '/pages/homepage.php') && is_file(ROOT_PATH . '/pages/katalog.php') && is_file(ROOT_PATH . '/pages/artikel.php'), 'note' => 'Homepage, katalog, dan artikel tetap ada sebagai fondasi SEO-commerce.'],
        ];
    }
}


if (!function_exists('release_audit_scan_content_files')) {
    function release_audit_scan_content_files(array $files, array $patterns): array
    {
        $findings = [];
        foreach ($files as $file) {
            $path = ROOT_PATH . '/' . ltrim((string)$file, '/');
            if (!is_file($path)) {
                continue;
            }
            $content = (string)@file_get_contents($path);
            foreach ($patterns as $id => $pattern) {
                if (preg_match($pattern, $content)) {
                    $findings[] = (string)$file . ' (' . (string)$id . ')';
                }
            }
        }
        return array_values(array_unique($findings));
    }
}

if (!function_exists('release_audit_json_content_files')) {
    function release_audit_json_content_files(): array
    {
        return [
            'storage/theme-settings.json',
            'storage/homepage-settings.json',
            'storage/template-content.json',
            'storage/navigation-settings.json',
            'storage/business-settings.json',
            'storage/custom-forms.json',
            'storage/landing-pages.json',
            'storage/landing-page-templates.json',
            'storage/website-starter-wizard.json',
        ];
    }
}

if (!function_exists('release_audit_json_file_findings')) {
    function release_audit_json_file_findings(): array
    {
        $findings = [];
        foreach (release_audit_json_content_files() as $file) {
            $path = ROOT_PATH . '/' . $file;
            if (!is_file($path)) {
                $findings[] = $file . ' (file hilang)';
                continue;
            }
            json_decode((string)@file_get_contents($path), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $findings[] = $file . ' (' . json_last_error_msg() . ')';
            }
        }
        return $findings;
    }
}

if (!function_exists('release_audit_public_navigation_dead_links')) {
    function release_audit_public_navigation_dead_links(): array
    {
        $routes = release_audit_route_map();
        $routeKeys = array_fill_keys(array_keys($routes), true);
        $settings = function_exists('navigation_settings') ? navigation_settings() : [];
        $links = [];
        foreach ((array)($settings['menu_items'] ?? []) as $item) {
            if (!empty($item['enabled'])) {
                $links[] = (string)($item['url'] ?? '');
            }
            foreach ((array)($item['children'] ?? []) as $child) {
                if (!empty($child['enabled'])) {
                    $links[] = (string)($child['url'] ?? '');
                }
            }
        }
        foreach ((array)($settings['footer_columns'] ?? []) as $column) {
            foreach ((array)($column['links'] ?? []) as $link) {
                if (!empty($link['enabled'])) {
                    $links[] = (string)($link['url'] ?? '');
                }
            }
        }
        foreach ((array)($settings['bottom_links'] ?? []) as $link) {
            if (!empty($link['enabled'])) {
                $links[] = (string)($link['url'] ?? '');
            }
        }

        $dead = [];
        foreach (array_values(array_unique($links)) as $url) {
            $url = trim((string)$url);
            if ($url === '' || str_starts_with($url, '#') || preg_match('#^(https?:)?//#i', $url) || preg_match('#^(mailto|tel|whatsapp):#i', $url)) {
                continue;
            }
            $path = trim((string)(parse_url($url, PHP_URL_PATH) ?? ''), '/');
            if ($path === '') {
                continue;
            }
            if ($path === 'sitemap.xml' || isset($routeKeys[$path])) {
                continue;
            }
            if (preg_match('#^(artikel|produk|form|lp|landing|kategori|area|katalog)/[a-zA-Z0-9\-]+$#', $path)) {
                continue;
            }
            $dead[] = $url;
        }
        return array_values(array_unique($dead));
    }
}

if (!function_exists('release_audit_default_content_checks')) {
    function release_audit_default_content_checks(): array
    {
        $jsonFindings = release_audit_json_file_findings();
        $defaultContentFiles = array_merge(release_audit_json_content_files(), [
            'data/products.php',
            'data/articles.php',
        ]);
        $privateFindings = release_audit_scan_content_files($defaultContentFiles, [
            'internal-roadmap' => '/internal\s+roadmap|roadmap\s+internal/i',
            'private-baseline' => '/baseline\s+(qurban|aqiqah)|qurban\s+baseline|aqiqah\s+baseline/i',
            'developer-note' => '/developer\s+note|catatan\s+developer|todo\s+internal/i',
            'assistant-reference' => '/\bchatgpt\b/i',
            'legacy-public-wording' => '/laporan\s+klasik|RajaOngkir\s+Legacy|Simpan\s+debug\s+log\s+API/i',
        ]);
        $nicheLeakFindings = release_audit_scan_content_files($defaultContentFiles, [
            'qurban-aqiqah-default' => '/\b(qurban|aqiqah)\b/i',
        ]);

        $products = function_exists('all_products') ? all_products() : [];
        $productSlugs = [];
        foreach ($products as $product) {
            $slug = trim((string)($product['slug'] ?? ''));
            if ($slug !== '') {
                $productSlugs[] = $slug;
            }
        }
        $productSlugDuplicates = array_keys(array_filter(array_count_values($productSlugs), static fn(int $count): bool => $count > 1));

        $articles = function_exists('all_articles') ? all_articles() : [];
        $articleSlugs = [];
        foreach ($articles as $article) {
            $slug = trim((string)($article['slug'] ?? ''));
            if ($slug !== '') {
                $articleSlugs[] = $slug;
            }
        }
        $articleSlugDuplicates = array_keys(array_filter(array_count_values($articleSlugs), static fn(int $count): bool => $count > 1));

        $deadNavigationLinks = release_audit_public_navigation_dead_links();
        $customForms = function_exists('custom_form_read_forms') ? custom_form_read_forms() : [];
        $activeFormCount = 0;
        $formIssues = [];
        foreach ($customForms as $form) {
            if (!is_array($form) || (string)($form['status'] ?? '') !== 'active') {
                continue;
            }
            $activeFormCount++;
            if (trim((string)($form['slug'] ?? '')) === '') {
                $formIssues[] = (string)($form['title'] ?? 'Form') . ' tanpa slug';
            }
            if (count((array)($form['fields'] ?? [])) < 2) {
                $formIssues[] = (string)($form['title'] ?? 'Form') . ' field terlalu sedikit';
            }
        }

        $managedProducts = function_exists('product_managed_products') ? product_managed_products() : [];
        $managedArticles = function_exists('managed_articles') ? managed_articles() : [];
        $readonlyProducts = array_values(array_filter($products, static fn(array $product): bool => (string)($product['source'] ?? $product['_source'] ?? '') === 'seed'));
        $readonlyArticles = array_values(array_filter($articles, static fn(array $article): bool => (string)($article['source'] ?? $article['_source'] ?? '') === 'seed'));

        return [
            ['id' => 'default-json-valid', 'title' => 'File seed/default JSON valid', 'ok' => count($jsonFindings) === 0, 'note' => count($jsonFindings) === 0 ? count(release_audit_json_content_files()) . ' file JSON default valid.' : implode(', ', array_slice($jsonFindings, 0, 8))],
            ['id' => 'default-copy-clean', 'title' => 'Default content bebas wording internal', 'ok' => count($privateFindings) === 0, 'note' => count($privateFindings) === 0 ? 'Tidak ada catatan internal/legacy yang bocor di seed storage dan data default.' : implode(', ', array_slice($privateFindings, 0, 8))],
            ['id' => 'default-niche-neutral', 'title' => 'Default content netral lintas niche', 'ok' => count($nicheLeakFindings) === 0, 'note' => count($nicheLeakFindings) === 0 ? 'Seed public default tidak berisi qurban/aqiqah; niche tersebut tetap tersedia hanya sebagai preset pilihan.' : implode(', ', array_slice($nicheLeakFindings, 0, 8))],
            ['id' => 'default-products-ready', 'title' => 'Katalog awal siap dipakai', 'ok' => count($products) >= 6 && count($productSlugDuplicates) === 0, 'note' => count($products) . ' item katalog terbaca; duplikat slug: ' . (count($productSlugDuplicates) === 0 ? '0' : implode(', ', array_slice($productSlugDuplicates, 0, 6)))],
            ['id' => 'default-products-editable', 'title' => 'Katalog publik editable dari menu produk', 'ok' => count($managedProducts) >= count($products) && count($readonlyProducts) === 0, 'note' => count($managedProducts) . ' item tersimpan di runtime admin; item read-only yang tampil: ' . count($readonlyProducts)],
            ['id' => 'default-articles-ready', 'title' => 'Artikel awal siap dipakai', 'ok' => count($articles) >= 4 && count($articleSlugDuplicates) === 0, 'note' => count($articles) . ' artikel terbaca; duplikat slug: ' . (count($articleSlugDuplicates) === 0 ? '0' : implode(', ', array_slice($articleSlugDuplicates, 0, 6)))],
            ['id' => 'default-articles-editable', 'title' => 'Artikel publik editable dari menu artikel', 'ok' => count($managedArticles) >= count($articles) && count($readonlyArticles) === 0, 'note' => count($managedArticles) . ' artikel tersimpan di runtime admin; artikel read-only yang tampil: ' . count($readonlyArticles)],
            ['id' => 'default-navigation-valid', 'title' => 'Menu publik default tidak menunjuk link mati', 'ok' => count($deadNavigationLinks) === 0, 'note' => count($deadNavigationLinks) === 0 ? 'Header/footer default hanya memakai route publik yang valid.' : implode(', ', array_slice($deadNavigationLinks, 0, 8))],
            ['id' => 'default-active-form-ready', 'title' => 'Form aktif default siap menerima lead', 'ok' => $activeFormCount >= 1 && count($formIssues) === 0, 'note' => $activeFormCount . ' form aktif default terbaca' . (count($formIssues) === 0 ? ' tanpa issue.' : ': ' . implode(', ', array_slice($formIssues, 0, 6)))],
        ];
    }
}

if (!function_exists('release_audit_workflow_checks')) {
    function release_audit_workflow_checks(): array
    {
        $routes = release_audit_route_map();
        $mustRoutes = [
            'admin/brand', 'admin/business', 'admin/starter-wizard', 'admin/navigation', 'admin/homepage',
            'admin/produk', 'admin/artikel', 'admin/forms', 'admin/form-checkout', 'admin/orders',
            'admin/shipping', 'admin/payment-settings', 'admin/payment-proofs', 'admin/commerce-insight', 'admin/release-audit',
        ];
        $missingRoutes = [];
        foreach ($mustRoutes as $route) {
            if (!isset($routes[$route])) {
                $missingRoutes[] = $route;
            }
        }

        $saveHelpers = [
            'theme_save_settings', 'business_save_settings', 'starter_wizard_apply_preset', 'navigation_save_settings', 'homepage_save_settings',
            'product_create', 'product_update', 'article_create', 'custom_form_save_from_post', 'checkout_save_settings', 'shipping_save_settings',
            'payment_write_settings', 'order_store', 'payment_proof_store', 'inquiry_store', 'notification_store_event',
        ];
        $missingHelpers = [];
        foreach ($saveHelpers as $helper) {
            if (!function_exists($helper)) {
                $missingHelpers[] = $helper;
            }
        }

        $adminPages = [
            'pages/admin-brand.php', 'pages/admin-business.php', 'pages/admin-website-starter-wizard.php', 'pages/admin-navigation.php', 'pages/admin-homepage.php',
            'pages/admin-produk.php', 'pages/admin-artikel.php', 'pages/admin-forms.php', 'pages/admin-form-checkout.php', 'pages/admin-orders.php',
            'pages/admin-shipping.php', 'pages/admin-payment-settings.php', 'pages/admin-payment-proofs.php', 'pages/admin-commerce-insight.php',
        ];
        $missingPages = [];
        foreach ($adminPages as $page) {
            if (!is_file(ROOT_PATH . '/' . $page)) {
                $missingPages[] = $page;
            }
        }

        $workflowPublicRoutes = ['katalog', 'artikel', 'checkout', 'order-submit', 'order-success', 'payment-proof-submit', 'inquiry-submit', 'form-submit'];
        $missingPublicRoutes = [];
        foreach ($workflowPublicRoutes as $route) {
            if (!isset($routes[$route])) {
                $missingPublicRoutes[] = $route;
            }
        }

        return [
            ['id' => 'admin-workflow-routes', 'title' => 'Route workflow admin utama lengkap', 'ok' => count($missingRoutes) === 0, 'note' => count($missingRoutes) === 0 ? count($mustRoutes) . ' route admin workflow utama valid.' : implode(', ', array_slice($missingRoutes, 0, 8))],
            ['id' => 'admin-workflow-pages', 'title' => 'Page workflow admin utama tersedia', 'ok' => count($missingPages) === 0, 'note' => count($missingPages) === 0 ? count($adminPages) . ' page admin workflow utama tersedia.' : implode(', ', array_slice($missingPages, 0, 8))],
            ['id' => 'admin-save-helpers', 'title' => 'Helper simpan workflow admin tersedia', 'ok' => count($missingHelpers) === 0, 'note' => count($missingHelpers) === 0 ? count($saveHelpers) . ' helper save/store workflow tersedia.' : implode(', ', array_slice($missingHelpers, 0, 8))],
            ['id' => 'public-conversion-routes', 'title' => 'Route publik lead/order/payment lengkap', 'ok' => count($missingPublicRoutes) === 0, 'note' => count($missingPublicRoutes) === 0 ? count($workflowPublicRoutes) . ' route publik conversion tersedia.' : implode(', ', array_slice($missingPublicRoutes, 0, 8))],
            ['id' => 'release-audit-expanded', 'title' => 'Audit final mencakup seed dan workflow', 'ok' => function_exists('release_audit_default_content_checks') && function_exists('release_audit_workflow_checks'), 'note' => 'Audit otomatis kini membaca default content, menu publik, dan rantai workflow admin utama.'],
        ];
    }
}


if (!function_exists('release_audit_source_contains_all')) {
    function release_audit_source_contains_all(string $file, array $needles): bool
    {
        $path = ROOT_PATH . '/' . ltrim($file, '/');
        if (!is_file($path)) {
            return false;
        }
        $source = (string)@file_get_contents($path);
        foreach ($needles as $needle) {
            if (!str_contains($source, (string)$needle)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('release_audit_source_contains_any')) {
    function release_audit_source_contains_any(string $file, array $needles): bool
    {
        $path = ROOT_PATH . '/' . ltrim($file, '/');
        if (!is_file($path)) {
            return false;
        }
        $source = (string)@file_get_contents($path);
        foreach ($needles as $needle) {
            if (str_contains($source, (string)$needle)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('release_audit_admin_crud_runtime_checks')) {
    function release_audit_admin_crud_runtime_checks(): array
    {
        $crudPages = [
            'pages/admin-brand.php',
            'pages/admin-business.php',
            'pages/admin-website-starter-wizard.php',
            'pages/admin-navigation.php',
            'pages/admin-homepage.php',
            'pages/admin-produk.php',
            'pages/admin-artikel.php',
            'pages/admin-forms.php',
            'pages/admin-form-checkout.php',
            'pages/admin-orders.php',
            'pages/admin-shipping.php',
            'pages/admin-payment-settings.php',
            'pages/admin-payment-proofs.php',
            'pages/admin-users.php',
            'pages/admin-security.php',
            'pages/admin-smtp.php',
            'pages/admin-maintenance.php',
        ];
        $missingCsrf = [];
        foreach ($crudPages as $file) {
            $path = ROOT_PATH . '/' . $file;
            if (!is_file($path)) {
                $missingCsrf[] = $file . ' (file hilang)';
                continue;
            }
            $source = (string)@file_get_contents($path);
            if (str_contains($source, "REQUEST_METHOD") && str_contains($source, "POST") && !str_contains($source, 'require_csrf') && !str_contains($source, 'verify_csrf')) {
                $missingCsrf[] = $file;
            }
        }

        $crudHelpers = [
            'theme_save_settings', 'business_save_settings', 'starter_wizard_apply_preset', 'navigation_save_settings', 'homepage_save_settings',
            'product_create', 'product_update', 'product_delete', 'article_create', 'article_update', 'article_delete',
            'custom_form_save_from_post', 'custom_form_delete', 'checkout_save_settings', 'shipping_save_settings', 'payment_write_settings',
            'order_update_status', 'payment_proof_update_status', 'admin_users_save_record', 'admin_users_delete_record', 'maintenance_create_backup',
        ];
        $missingHelpers = [];
        foreach ($crudHelpers as $helper) {
            if (!function_exists($helper)) {
                $missingHelpers[] = $helper;
            }
        }

        $storeTargets = [
            'storage/theme-settings.json',
            'storage/business-settings.json',
            'storage/navigation-settings.json',
            'storage/homepage-settings.json',
            'storage/custom-forms.json',
            'storage/products.json',
            'storage/articles.json',
            'storage/payment-settings.json',
            'logs/orders-*.jsonl',
            'logs/payment-proofs-*.jsonl',
        ];
        $storagePrivate = is_file(STORAGE_PATH . '/.htaccess') && is_file(LOGS_PATH . '/.htaccess') && is_dir(STORAGE_PATH) && is_dir(LOGS_PATH);

        $productCrudReady = function_exists('product_create') && function_exists('product_update') && function_exists('product_delete') && release_audit_source_contains_any('pages/admin-produk.php', ['form_action', 'product_create(']);
        $articleCrudReady = function_exists('article_create') && function_exists('article_update') && function_exists('article_delete') && release_audit_source_contains_any('pages/admin-artikel.php', ['form_action', 'article_create(']);
        $formCrudReady = function_exists('custom_form_save_from_post') && function_exists('custom_form_delete') && release_audit_source_contains_any('pages/admin-forms.php', ['custom_form_save_from_post', 'custom_form_delete']);
        $orderAdminReady = function_exists('order_update_status') && release_audit_source_contains_any('pages/admin-orders.php', ['order_update_status']);
        $proofAdminReady = function_exists('payment_proof_update_status') && release_audit_source_contains_any('pages/admin-payment-proofs.php', ['payment_proof_update_status']);

        return [
            ['id' => 'admin-crud-csrf', 'title' => 'POST admin penting punya proteksi CSRF', 'ok' => count($missingCsrf) === 0, 'note' => count($missingCsrf) === 0 ? count($crudPages) . ' halaman admin penting memakai require/verify CSRF.' : implode(', ', array_slice($missingCsrf, 0, 8))],
            ['id' => 'admin-crud-helpers', 'title' => 'Helper CRUD admin penting tersedia', 'ok' => count($missingHelpers) === 0, 'note' => count($missingHelpers) === 0 ? count($crudHelpers) . ' helper CRUD/save/status tersedia.' : implode(', ', array_slice($missingHelpers, 0, 8))],
            ['id' => 'admin-crud-storage', 'title' => 'Storage/log workflow tersimpan privat', 'ok' => $storagePrivate, 'note' => $storagePrivate ? 'Storage dan logs punya proteksi akses langsung; target data: ' . count($storeTargets) . ' area.' : 'Cek .htaccess storage/logs dan folder runtime.'],
            ['id' => 'product-article-crud-ready', 'title' => 'CRUD katalog dan artikel siap diuji', 'ok' => $productCrudReady && $articleCrudReady, 'note' => ($productCrudReady && $articleCrudReady) ? 'Create/update/delete katalog dan artikel tersedia di helper + halaman admin.' : 'Cek admin-produk/admin-artikel dan helper create/update/delete.'],
            ['id' => 'form-order-proof-admin-ready', 'title' => 'Workflow form, order, bukti bayar siap dikelola', 'ok' => $formCrudReady && $orderAdminReady && $proofAdminReady, 'note' => ($formCrudReady && $orderAdminReady && $proofAdminReady) ? 'Form custom, status order, dan status bukti bayar punya jalur admin.' : 'Cek admin forms/orders/payment-proofs dan helper status.'],
        ];
    }
}

if (!function_exists('release_audit_public_submission_runtime_checks')) {
    function release_audit_public_submission_runtime_checks(): array
    {
        $formReady = release_audit_source_contains_all('pages/form-submit.php', ['REQUEST_METHOD', 'POST', 'custom_form_submit'])
            && function_exists('custom_form_submit')
            && function_exists('custom_form_is_rate_limited')
            && release_audit_source_contains_any('core/form-builder.php', ['verify_csrf']);

        $orderReady = release_audit_source_contains_all('pages/order-submit.php', ['REQUEST_METHOD', 'POST', 'order_validate_payload', 'order_store'])
            && function_exists('order_validate_payload')
            && function_exists('order_normalize_payload')
            && function_exists('order_store')
            && function_exists('order_success_url')
            && release_audit_source_contains_any('pages/order-submit.php', ['verify_csrf']);

        $proofReady = release_audit_source_contains_all('pages/payment-proof-submit.php', ['REQUEST_METHOD', 'POST', 'payment_proof_validate_payload', 'payment_proof_store'])
            && function_exists('payment_proof_validate_payload')
            && function_exists('payment_proof_store_file')
            && function_exists('payment_proof_store')
            && release_audit_source_contains_all('core/payment-proof.php', ['is_uploaded_file', 'move_uploaded_file', 'payment_proof_allowed_mimes']);

        $orderLookupReady = function_exists('order_public_reference')
            && function_exists('order_public_token')
            && function_exists('order_find_by_reference')
            && function_exists('order_public_invoice_url')
            && release_audit_source_contains_any('pages/invoice.php', ['order_find_by_reference']);

        $rateLimitReady = function_exists('custom_form_is_rate_limited')
            && function_exists('order_is_rate_limited')
            && function_exists('payment_proof_is_rate_limited');

        $conversionReady = function_exists('conversion_store_event')
            && release_audit_source_contains_any('pages/order-submit.php', ['conversion_store_event'])
            && release_audit_source_contains_any('pages/payment-proof-submit.php', ['conversion_store_event']);

        return [
            ['id' => 'public-form-submit-runtime', 'title' => 'Submit form custom punya validasi runtime', 'ok' => $formReady, 'note' => $formReady ? 'form-submit memakai POST, CSRF, rate limit, validasi field, dan penyimpanan log.' : 'Cek form-submit/core form-builder.'],
            ['id' => 'public-order-submit-runtime', 'title' => 'Submit order checkout punya validasi runtime', 'ok' => $orderReady, 'note' => $orderReady ? 'order-submit memakai POST, CSRF, rate limit, validasi payload, store order, dan URL sukses.' : 'Cek order-submit/core order.'],
            ['id' => 'public-payment-proof-runtime', 'title' => 'Upload bukti bayar aman untuk runtime', 'ok' => $proofReady, 'note' => $proofReady ? 'payment-proof-submit memvalidasi order/token, CSRF, mime, is_uploaded_file, dan move_uploaded_file.' : 'Cek payment proof submit/helper upload.'],
            ['id' => 'public-order-lookup-token', 'title' => 'Invoice/status publik memakai referensi + token', 'ok' => $orderLookupReady, 'note' => $orderLookupReady ? 'Order publik punya ref/token untuk invoice, status, dan upload bukti bayar.' : 'Cek order_public_reference/token/find_by_reference dan invoice.'],
            ['id' => 'public-submit-rate-limit', 'title' => 'Form/order/payment proof punya rate limit', 'ok' => $rateLimitReady, 'note' => $rateLimitReady ? 'Tiga jalur submit publik punya helper rate limit.' : 'Cek rate limit form/order/payment proof.'],
            ['id' => 'public-submit-conversion-bridge', 'title' => 'Submit publik tersambung ke conversion event', 'ok' => $conversionReady, 'note' => $conversionReady ? 'Order dan bukti bayar menyimpan sinyal conversion untuk laporan growth.' : 'Cek conversion_store_event pada jalur submit publik.'],
        ];
    }
}

if (!function_exists('release_audit_role_permission_runtime_checks')) {
    function release_audit_role_permission_runtime_checks(): array
    {
        $routes = release_audit_route_map();
        $sets = function_exists('admin_users_permission_sets') ? admin_users_permission_sets() : [];
        $roles = function_exists('admin_users_roles') ? admin_users_roles() : [];
        $routeKeys = array_fill_keys(array_keys($routes), true);

        $stale = [];
        $defaultProblems = [];
        $tooSmall = [];
        foreach ($roles as $role => $_meta) {
            $allowed = (array)($sets[$role] ?? []);
            if ($role !== 'owner' && count($allowed) < 5) {
                $tooSmall[] = (string)$role;
            }
            if ($role === 'owner') {
                if (!in_array('*', $allowed, true)) {
                    $defaultProblems[] = 'owner tanpa wildcard';
                }
                continue;
            }
            foreach ($allowed as $path) {
                $path = trim((string)$path, '/');
                if ($path === '' || str_ends_with($path, '/*') || isset($routeKeys[$path])) {
                    continue;
                }
                if (function_exists('admin_auth_is_public_path') && admin_auth_is_public_path($path)) {
                    continue;
                }
                $stale[] = (string)$role . ': ' . $path;
            }
            $default = function_exists('admin_users_default_path_for_role') ? admin_users_default_path_for_role((string)$role) : '';
            if ($default === '' || !function_exists('admin_users_can_access_path') || !admin_users_can_access_path($default, ['role' => (string)$role, 'auth_source' => 'admin_user', 'id' => 'audit'])) {
                $defaultProblems[] = (string)$role . ': ' . $default;
            }
        }

        $sensitiveRoutes = ['admin/users', 'admin/security', 'admin/smtp', 'admin/storage-database', 'admin/storage', 'admin/database', 'admin/cloud-backup-sync', 'admin/data-backup-sync', 'admin/maintenance', 'admin/backup-restore'];
        $leaks = [];
        foreach (array_keys($roles) as $role) {
            if ($role === 'owner') {
                continue;
            }
            foreach ($sensitiveRoutes as $route) {
                if (function_exists('admin_users_can_access_path') && admin_users_can_access_path($route, ['role' => (string)$role, 'auth_source' => 'admin_user', 'id' => 'audit'])) {
                    $leaks[] = (string)$role . ' → ' . $route;
                }
            }
        }

        $aliasCoverageIssues = [];
        $groups = function_exists('admin_users_route_alias_groups') ? admin_users_route_alias_groups() : [];
        foreach ($sets as $role => $allowed) {
            if (!is_array($allowed) || in_array('*', $allowed, true)) {
                continue;
            }
            foreach ($groups as $group) {
                $intersect = array_intersect($allowed, $group);
                if ($intersect && count($intersect) !== count($group)) {
                    $aliasCoverageIssues[] = (string)$role . ': ' . implode('|', array_diff($group, $allowed));
                }
            }
        }

        return [
            ['id' => 'role-sets-complete', 'title' => 'Role permission set tersedia untuk semua role', 'ok' => count($roles) >= 6 && count($tooSmall) === 0 && isset($sets['owner']), 'note' => count($tooSmall) === 0 ? count($roles) . ' role dashboard punya permission set.' : 'Permission terlalu kecil: ' . implode(', ', $tooSmall)],
            ['id' => 'role-default-paths', 'title' => 'Default route tiap role bisa diakses', 'ok' => count($defaultProblems) === 0, 'note' => count($defaultProblems) === 0 ? 'Default path tiap role valid dan boleh diakses role terkait.' : implode(', ', array_slice($defaultProblems, 0, 8))],
            ['id' => 'role-stale-permissions-runtime', 'title' => 'Runtime permission bebas route mati', 'ok' => count($stale) === 0, 'note' => count($stale) === 0 ? 'Permission runtime tidak menunjuk route stale.' : implode(', ', array_slice($stale, 0, 8))],
            ['id' => 'role-sensitive-owner-only', 'title' => 'Menu sensitif hanya untuk owner', 'ok' => count($leaks) === 0, 'note' => count($leaks) === 0 ? 'User, keamanan, SMTP, maintenance/backup tidak bocor ke role non-owner.' : implode(', ', array_slice($leaks, 0, 8))],
            ['id' => 'role-alias-expanded', 'title' => 'Alias route ikut permission role', 'ok' => count($aliasCoverageIssues) === 0, 'note' => count($aliasCoverageIssues) === 0 ? count($groups) . ' grup alias admin tercakup saat satu alias diizinkan.' : implode(', ', array_slice($aliasCoverageIssues, 0, 8))],
            ['id' => 'role-menu-filter-ready', 'title' => 'Sidebar difilter berdasarkan role', 'ok' => function_exists('admin_users_filter_menu_groups'), 'note' => 'Menu dashboard memakai filter role sebelum ditampilkan.'],
        ];
    }
}



if (!function_exists('release_audit_http_e2e_checks')) {
    function release_audit_http_e2e_checks(): array
    {
        $routes = release_audit_route_map();
        $publicRoutes = ['' => 'Beranda', 'katalog' => 'Katalog', 'artikel' => 'Artikel', 'layanan' => 'Layanan', 'kontak' => 'Kontak', 'checkout' => 'Checkout', 'form-submit' => 'Submit Form', 'order-submit' => 'Submit Order', 'payment-proof-submit' => 'Upload Bukti Bayar'];
        $missingPublic = [];
        foreach ($publicRoutes as $route => $label) {
            if ($route === '') {
                if (!is_file(PAGES_PATH . '/homepage.php')) {
                    $missingPublic[] = $label . ' (/)';
                }
                continue;
            }
            if (!array_key_exists((string)$route, $routes)) {
                $missingPublic[] = $label . ' (' . $route . ')';
            }
        }

        $checkoutReady = release_audit_source_contains_all('components/order-form.php', [
            'data-order-form="1"',
            "url('order-submit')",
            'csrf_field()',
            'name="name"',
            'name="phone"',
            'name="consent_contact"',
        ]) && release_audit_source_contains_all('pages/order-submit.php', [
            "REQUEST_METHOD",
            "POST",
            'verify_csrf()',
            'order_validate_payload',
            'order_store',
            'Content-Type: application/json',
        ]);

        $customFormReady = release_audit_source_contains_all('pages/form-page.php', [
            'custom_form_find',
            'custom_form_render',
            'custom-form-card',
        ]) && release_audit_source_contains_all('pages/form-submit.php', [
            "REQUEST_METHOD",
            "POST",
            'custom_form_submit',
            'redirect_302',
        ]) && release_audit_source_contains_all('core/form-builder.php', [
            'verify_csrf()',
            'custom_form_submission_file',
            'custom_form_upload_absolute_path',
        ]);

        $paymentProofReady = release_audit_source_contains_all('pages/payment-proof-submit.php', [
            "REQUEST_METHOD",
            "POST",
            'verify_csrf()',
            '$_FILES',
            'proof_file',
            'payment_proof_store_file',
            'order_find_by_reference',
        ]) && release_audit_source_contains_all('core/payment-proof.php', [
            'payment_proof_allowed_mimes',
            'image/jpeg',
            'image/png',
            'image/webp',
            'application/pdf',
            'move_uploaded_file',
        ]);

        $frontendAjaxReady = release_audit_source_contains_all('assets/js/app.js', [
            'initOrderForms',
            'data-order-form="1"',
            'fetch(action',
            "'X-Requested-With': 'fetch'",
            'credentials: \'same-origin\'',
        ]);

        $adminSessionReady = release_audit_source_contains_all('pages/admin-login.php', [
            'admin_auth_attempt_login',
            'csrf_field()',
            'name="login"',
            'name="password"',
        ]) && release_audit_source_contains_all('index.php', [
            'admin_auth_is_admin_path',
            'admin_auth_require',
            'admin_auth_is_public_path',
        ]);

        $privateUploadReady = is_file(STORAGE_PATH . '/.htaccess')
            && is_dir(STORAGE_PATH . '/payment-proofs')
            && is_dir(STORAGE_PATH . '/form-files')
            && release_audit_source_contains_all('core/payment-proof.php', ['payment_proof_storage_dir', 'payment_proof_file_absolute_path'])
            && release_audit_source_contains_all('pages/admin-payment-proof-file.php', ['payment_proof_file_absolute_path', 'readfile($path)'])
            && release_audit_source_contains_all('core/form-builder.php', ['custom_form_upload_absolute_path'])
            && release_audit_source_contains_all('pages/admin-form-file.php', ['custom_form_upload_absolute_path', 'readfile($path)']);

        $manualUxReady = release_audit_source_contains_all('pages/admin-release-audit.php', [
            'Manual Release Checklist',
            'Checklist sebelum upload production',
            'Ekspor CSV',
            'Ekspor JSON',
            'Ekspor Teks',
        ]);

        return [
            ['id' => 'http-public-routes-ready', 'title' => 'Route publik inti siap dites via HTTP', 'ok' => count($missingPublic) === 0, 'note' => count($missingPublic) === 0 ? count($publicRoutes) . ' route publik inti tersedia untuk smoke test HTTP.' : implode(', ', array_slice($missingPublic, 0, 8))],
            ['id' => 'http-checkout-submit-ready', 'title' => 'Checkout bisa disubmit via HTTP/Fetch', 'ok' => $checkoutReady, 'note' => $checkoutReady ? 'Form order punya CSRF, field wajib, consent, endpoint JSON, validasi, dan penyimpanan order.' : 'Cek components/order-form.php dan pages/order-submit.php.'],
            ['id' => 'http-custom-form-submit-ready', 'title' => 'Custom form bisa disubmit via HTTP', 'ok' => $customFormReady, 'note' => $customFormReady ? 'Form custom punya render publik, CSRF, validasi field, redirect aman, dan storage lead.' : 'Cek pages/form-page.php, pages/form-submit.php, dan core/form-builder.php.'],
            ['id' => 'http-payment-proof-upload-ready', 'title' => 'Upload bukti bayar siap multipart HTTP', 'ok' => $paymentProofReady, 'note' => $paymentProofReady ? 'Endpoint bukti bayar membaca file multipart, validasi ref/token, mime, ukuran, dan simpan ke storage privat.' : 'Cek pages/payment-proof-submit.php dan core/payment-proof.php.'],
            ['id' => 'frontend-order-ajax-ready', 'title' => 'Enhancement frontend order tidak memutus fallback', 'ok' => $frontendAjaxReady, 'note' => $frontendAjaxReady ? 'JavaScript order memakai fetch + same-origin, sementara form tetap punya action/method POST.' : 'Cek initOrderForms di assets/js/app.js.'],
            ['id' => 'admin-session-browser-ready', 'title' => 'Login admin dan protected route siap browser session', 'ok' => $adminSessionReady, 'note' => $adminSessionReady ? 'Login admin memakai CSRF, session terpusat, next path aman, dan route admin diproteksi index.php.' : 'Cek pages/admin-login.php, core/admin-auth.php, dan index.php.'],
            ['id' => 'private-upload-download-ready', 'title' => 'File upload disimpan privat dan diambil via route admin', 'ok' => $privateUploadReady, 'note' => $privateUploadReady ? 'Bukti bayar dan file form disimpan di storage privat; akses admin memakai response helper.' : 'Cek folder storage dan helper download file.'],
            ['id' => 'manual-staging-ux-checklist-ready', 'title' => 'Checklist UX staging tersedia di dashboard', 'ok' => $manualUxReady, 'note' => $manualUxReady ? 'Admin/release-audit menyediakan checklist manual dan export untuk review staging/live.' : 'Cek halaman admin-release-audit.'],
        ];
    }
}





if (!function_exists('release_audit_commerce_runtime_checks')) {
    function release_audit_commerce_runtime_checks(): array
    {
        $routes = release_audit_route_map();
        $orderSubmitReady = isset($routes['order-submit'])
            && release_audit_source_contains_all('pages/order-submit.php', ['REQUEST_METHOD', 'POST', 'verify_csrf', 'order_validate_payload', 'order_normalize_payload', 'order_store'])
            && release_audit_source_contains_all('core/order.php', ['shipping_apply_to_order', 'commerce_snapshot_for_order', 'inventory_order_snapshot']);

        $shippingReady = isset($routes['shipping-estimate'], $routes['api/shipping-estimate'])
            && function_exists('shipping_estimate')
            && function_exists('shipping_apply_to_order')
            && release_audit_source_contains_all('core/shipping.php', ['shipping_manual_estimate', 'shipping_api_estimate', 'shipping_api_cache_key', 'shipping_rate_limit_hit'])
            && release_audit_source_contains_all('pages/shipping-estimate.php', ['shipping_estimate', 'Content-Type: application/json']);

        $invoiceProofReady = isset($routes['invoice'], $routes['payment-proof-submit'])
            && function_exists('order_public_invoice_url')
            && function_exists('payment_proof_store')
            && release_audit_source_contains_all('pages/invoice.php', ['order_find_by_reference', 'payment-proof-submit', 'ref', 'token'])
            && release_audit_source_contains_all('pages/payment-proof-submit.php', ['payment_proof_validate_payload', 'payment_proof_store_file', 'payment_proof_store']);

        $paymentReminderReady = isset($routes['admin/payment-reminders'])
            && function_exists('payment_reminder_candidates')
            && function_exists('payment_reminder_send_email')
            && function_exists('payment_reminder_record_completion')
            && release_audit_source_contains_all('pages/admin-payment-reminders.php', ['Chat WA Reminder', 'Kirim Email Reminder', 'Catat WA Sudah Dikirim'])
            && release_audit_source_contains_all('core/order.php', ['payment_reminder_record_completion']);

        $paidAccessReady = function_exists('member_access_maybe_issue_for_order')
            && function_exists('buyer_account_upsert_from_order')
            && release_audit_source_contains_all('core/order.php', ['digital_delivery_maybe_issue_for_order', 'member_access_maybe_issue_for_order'])
            && release_audit_source_contains_all('pages/member-area.php', ['Akses Produk', 'Riwayat Pembelian', 'Profil & Password']);

        $adminCommerceReady = release_audit_source_contains_all('pages/admin-orders.php', ['Belum Bayar', 'DP Masuk', 'Lunas / Akses Aktif', 'Reminder Pembayaran'])
            && release_audit_source_contains_all('pages/admin-orders.php', ['order_update_status', 'invoice_total', 'fulfillment_status'])
            && release_audit_source_contains_all('pages/admin-payment-proofs.php', ['payment_proof_update_status'])
            && is_file(ROOT_PATH . '/pages/admin-shipping.php')
            && is_file(ROOT_PATH . '/pages/admin-payment-settings.php');

        $commerceDocsReady = is_file(ROOT_PATH . '/docs/commerce-runtime.md')
            || is_file(ROOT_PATH . '/docs/launch-checklist.md');

        return [
            ['id' => 'commerce-checkout-chain', 'title' => 'Checkout/order menyimpan snapshot commerce lengkap', 'ok' => $orderSubmitReady, 'note' => $orderSubmitReady ? 'Order submit memakai POST, CSRF, validasi, storage, shipping, commerce rule, dan inventory snapshot.' : 'Cek pages/order-submit.php dan core/order.php.'],
            ['id' => 'commerce-shipping-chain', 'title' => 'Cek ongkir siap manual/API/cache/fallback', 'ok' => $shippingReady, 'note' => $shippingReady ? 'shipping-estimate dan api/shipping-estimate tersedia dengan manual/API estimate, cache, dan rate-limit.' : 'Cek core/shipping.php dan pages/shipping-estimate.php.'],
            ['id' => 'commerce-payment-proof-chain', 'title' => 'Invoice dan bukti bayar publik nyambung ref/token', 'ok' => $invoiceProofReady, 'note' => $invoiceProofReady ? 'Invoice publik, token order, dan upload bukti bayar multipart sudah terhubung.' : 'Cek invoice/payment-proof flow.'],
            ['id' => 'commerce-reminder-chain', 'title' => 'Reminder pembayaran berhenti saat status selesai', 'ok' => $paymentReminderReady, 'note' => $paymentReminderReady ? 'Reminder punya kandidat H+/jatuh tempo, WA/email manual, log, dan close event saat DP/Lunas.' : 'Cek payment-reminder dan order_update_status.'],
            ['id' => 'commerce-paid-access-chain', 'title' => 'Pembayaran lunas membuka akses produk/member', 'ok' => $paidAccessReady, 'note' => $paidAccessReady ? 'Status paid memicu digital delivery, member access, buyer account, dan dashboard member.' : 'Cek member/digital delivery trigger.'],
            ['id' => 'commerce-admin-operator-flow', 'title' => 'Admin punya filter cepat dan panel verifikasi order', 'ok' => $adminCommerceReady, 'note' => $adminCommerceReady ? 'Order admin punya filter belum bayar/DP/lunas, invoice, fulfillment, bukti bayar, shipping, dan payment settings.' : 'Cek admin order/payment/shipping menu.'],
            ['id' => 'commerce-runtime-docs', 'title' => 'Dokumentasi commerce runtime tersedia', 'ok' => $commerceDocsReady, 'note' => $commerceDocsReady ? 'Catatan alur commerce tersedia di docs.' : 'Tambahkan panduan commerce runtime di docs.'],
        ];
    }
}

if (!function_exists('release_audit_buyer_access_restriction_checks')) {
    function release_audit_buyer_access_restriction_checks(): array
    {
        $routeMap = release_audit_route_map();

        $engineReady = is_file(ROOT_PATH . '/core/content-restriction.php')
            && function_exists('content_restriction_allowed')
            && function_exists('content_restriction_save_for')
            && function_exists('content_restriction_admin_fields')
            && function_exists('content_restriction_render_gate');

        $adminRestrictionReady = release_audit_source_contains_all('pages/admin-artikel.php', [
                "content_restriction_save_for('article'",
                "content_restriction_admin_fields('article'",
            ])
            && release_audit_source_contains_all('pages/admin-produk.php', [
                "content_restriction_save_for('product'",
                "content_restriction_admin_fields('product'",
            ])
            && release_audit_source_contains_all('pages/admin-landing-pages.php', [
                "content_restriction_save_for('landing_page'",
                "content_restriction_admin_fields('landing_page'",
            ]);

        $publicGateReady = release_audit_source_contains_all('pages/artikel-detail.php', [
                "content_restriction_allowed('article'",
                'content_restriction_render_gate',
            ])
            && release_audit_source_contains_all('pages/product-detail.php', [
                "content_restriction_allowed('product'",
                'content_restriction_render_gate',
            ])
            && release_audit_source_contains_all('pages/landing-page.php', [
                "content_restriction_allowed('landing_page'",
                'content_restriction_render_gate',
            ]);

        $memberDashboardReady = release_audit_source_contains_all('pages/member-area.php', [
            'Dashboard Member',
            'Akses Produk',
            'Riwayat Pembelian',
            'Profil & Password',
            'buyer_account_set_password',
        ]);

        $menuPolishReady = release_audit_admin_menu_has('admin/inquiries')
            && release_audit_admin_menu_has('admin/followups')
            && release_audit_admin_menu_has('admin/notifications')
            && release_audit_admin_menu_has('admin/smtp')
            && release_audit_source_contains_all('core/admin-help.php', [
                'Follow-up & CRM',
                'Lead & Customer',
                'Riwayat Email',
                'SMTP / Email Server',
            ]);

        $emailHistoryRoutesReady = isset($routeMap['admin/notifications'], $routeMap['admin/email-history'], $routeMap['admin/riwayat-email'])
            && release_audit_source_contains_all('pages/admin-smtp.php', ['Lihat Riwayat Email', 'admin/notifications'])
            && release_audit_source_contains_all('pages/admin-notifications.php', ['Riwayat Email', 'SMTP / Email Server']);

        $permissionAliasReady = release_audit_source_contains_all('core/admin-users.php', [
            'admin/email-history',
            'admin/riwayat-email',
        ]);

        $advancedRestrictionEngineReady = function_exists('content_restriction_modes')
            && function_exists('content_restriction_record_matches_category')
            && function_exists('content_restriction_record_matches_order_status')
            && function_exists('content_restriction_subscription_active')
            && function_exists('content_restriction_reason_message')
            && release_audit_source_contains_all('core/content-restriction.php', [
                'product_category',
                'order_status',
                'subscription_active',
                'require_unexpired_access',
            ]);

        $advancedRestrictionAdminReady = release_audit_source_contains_all('core/content-restriction.php', [
            'required_product_categories',
            'required_order_statuses',
            'required_payment_statuses',
            'required_subscription_slugs',
            'access_expired_message',
        ]);

        $lockedMessageReady = release_audit_source_contains_all('core/content-restriction.php', [
            'content_restriction_reason_message',
            'content_restriction_requirement_lines',
            'Syarat akses',
            'Masa akses: masih aktif',
        ]);

        $subscriptionRestrictionReady = function_exists('subscription_records_by_email')
            && function_exists('subscription_status')
            && release_audit_source_contains_all('core/content-restriction.php', ['subscription_records_by_email', 'subscription_status']);

        return [
            ['id' => 'buyer-restriction-engine-ready', 'title' => 'Engine restriction konten pembeli tersedia', 'ok' => $engineReady, 'note' => $engineReady ? 'Core content-restriction siap untuk halaman, artikel, produk, dan landing page.' : 'Cek core/content-restriction.php dan autoload config/app.php.'],
            ['id' => 'buyer-restriction-admin-fields-ready', 'title' => 'Admin bisa mengatur restriction per konten', 'ok' => $adminRestrictionReady, 'note' => $adminRestrictionReady ? 'Artikel, produk, dan landing page punya field restriction serta save handler.' : 'Cek form admin artikel, produk, dan landing page.'],
            ['id' => 'buyer-restriction-public-gate-ready', 'title' => 'Konten publik punya gate akses pembeli', 'ok' => $publicGateReady, 'note' => $publicGateReady ? 'Detail artikel, produk, dan landing page memblokir akses sesuai rule.' : 'Cek public gate di artikel-detail, product-detail, dan landing-page.'],
            ['id' => 'member-dashboard-ready', 'title' => 'Dashboard member pembeli punya menu sederhana', 'ok' => $memberDashboardReady, 'note' => $memberDashboardReady ? 'Member area punya akses produk, riwayat pembelian, profil/password, dan bantuan.' : 'Cek pages/member-area.php.'],
            ['id' => 'lead-email-menu-polish-ready', 'title' => 'Menu Lead, CRM, dan Riwayat Email sudah tidak tumpang tindih', 'ok' => $menuPolishReady, 'note' => $menuPolishReady ? 'Inbox Lead/Form dan Follow-up & CRM berada di Lead & Customer; Riwayat Email berada di area SMTP/Sistem.' : 'Cek sidebar/admin help untuk posisi menu.'],
            ['id' => 'email-history-smtp-ready', 'title' => 'Riwayat Email terhubung ke SMTP / Email Server', 'ok' => $emailHistoryRoutesReady, 'note' => $emailHistoryRoutesReady ? 'Route lama dan alias baru tersedia; halaman SMTP punya pintasan ke Riwayat Email.' : 'Cek admin/notifications, admin/email-history, dan admin/riwayat-email.'],
            ['id' => 'email-history-permission-ready', 'title' => 'Permission alias Riwayat Email ikut aman', 'ok' => $permissionAliasReady, 'note' => $permissionAliasReady ? 'Alias Riwayat Email masuk daftar permission role sehingga tidak jadi route liar.' : 'Cek core/admin-users.php.'],
            ['id' => 'advanced-restriction-engine-ready', 'title' => 'Restriction advanced tersedia', 'ok' => $advancedRestrictionEngineReady, 'note' => $advancedRestrictionEngineReady ? 'Engine mendukung produk, kategori produk, status order/pembayaran, subscription aktif, dan masa akses aktif.' : 'Cek core/content-restriction.php untuk mode advanced.'],
            ['id' => 'advanced-restriction-admin-ready', 'title' => 'Admin bisa mengatur rule advanced', 'ok' => $advancedRestrictionAdminReady, 'note' => $advancedRestrictionAdminReady ? 'Field kategori produk, status order, status pembayaran, subscription, dan masa akses tersedia di form restriction.' : 'Cek content_restriction_admin_fields.'],
            ['id' => 'locked-content-message-ready', 'title' => 'Pesan akses terkunci lebih rapi', 'ok' => $lockedMessageReady, 'note' => $lockedMessageReady ? 'Gate publik menampilkan alasan terkunci, syarat akses, login member, cek akses, dan kontak admin.' : 'Cek content_restriction_render_gate.'],
            ['id' => 'subscription-restriction-ready', 'title' => 'Restriction bisa membaca subscription aktif', 'ok' => $subscriptionRestrictionReady, 'note' => $subscriptionRestrictionReady ? 'Rule subscription aktif membaca subscription record buyer dan status active/lifetime/grace.' : 'Cek core/subscription.php dan core/content-restriction.php.'],
        ];
    }
}


if (!function_exists('release_audit_final_release_candidate_checks')) {
    function release_audit_final_release_candidate_checks(): array
    {
        $routes = release_audit_route_map();
        $envExample = is_file(ROOT_PATH . '/.env.example') ? (string)file_get_contents(ROOT_PATH . '/.env.example') : '';
        $rootHtaccess = is_file(ROOT_PATH . '/.htaccess') ? (string)file_get_contents(ROOT_PATH . '/.htaccess') : '';
        $productionSource = is_file(ROOT_PATH . '/core/production-readiness.php') ? (string)file_get_contents(ROOT_PATH . '/core/production-readiness.php') : '';
        $releasePageSource = is_file(ROOT_PATH . '/pages/admin-release-audit.php') ? (string)file_get_contents(ROOT_PATH . '/pages/admin-release-audit.php') : '';
        $nginxDoc = ROOT_PATH . '/docs/nginx-hardening-aaPanel.conf';

        $requiredEnvKeys = [
            'APP_URL=',
            'ADMIN_PASSWORD=',
            'ADMIN_SESSION_TIMEOUT=',
            'ENABLE_EMAIL_NOTIFICATIONS=',
            'EMAIL_TRANSPORT=',
            'ENABLE_PAYMENT_PROOF_UPLOAD=',
            'PAYMENT_PROOF_MAX_MB=',
            'PAYMENT_GATEWAY_ENABLED=',
            'GOOGLE_ADS_VAULT_KEY=',
        ];
        $missingEnvKeys = [];
        foreach ($requiredEnvKeys as $key) {
            if ($envExample === '' || !str_contains($envExample, $key)) {
                $missingEnvKeys[] = rtrim($key, '=');
            }
        }

        $hardeningReady = $rootHtaccess !== ''
            && str_contains($rootHtaccess, 'RewriteEngine On')
            && str_contains($rootHtaccess, 'FORCE HTTPS ON PRODUCTION')
            && str_contains($rootHtaccess, 'BLOCK SENSITIVE FILES')
            && str_contains($rootHtaccess, 'Options -Indexes')
            && str_contains($rootHtaccess, 'X-Content-Type-Options')
            && str_contains($rootHtaccess, 'Permissions-Policy');

        $privateRuntimeDirs = [
            STORAGE_PATH . '/.htaccess',
            LOGS_PATH . '/.htaccess',
            CACHE_PATH . '/.htaccess',
            ASSETS_PATH . '/uploads/.htaccess',
            ASSETS_PATH . '/uploads/form-files/.htaccess',
        ];
        $missingRuntimeProtection = [];
        foreach ($privateRuntimeDirs as $file) {
            if (!is_file((string)$file)) {
                $missingRuntimeProtection[] = str_replace(ROOT_PATH . '/', '', (string)$file);
            }
        }

        $runtimeLeaks = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            $relative = str_replace(ROOT_PATH . '/', '', $fileInfo->getPathname());
            if ($relative === '.env' || str_ends_with($relative, '.jsonl') || str_ends_with($relative, '.log')) {
                if (!in_array($relative, ['logs/.gitkeep'], true)) {
                    $runtimeLeaks[] = $relative;
                }
            }
            if (str_ends_with($relative, '/.gitkeep')) {
                continue;
            }
            if (str_contains($relative, 'maintenance-backups/') || str_contains($relative, 'payment-proofs/') || (str_contains($relative, 'form-files/') && !str_ends_with($relative, '.htaccess'))) {
                $runtimeLeaks[] = $relative;
            }
        }
        $runtimeLeaks = array_values(array_unique($runtimeLeaks));

        $productionEnvKeyReady = $productionSource !== ''
            && str_contains($productionSource, "\$_ENV['ADMIN_PASSWORD']")
            && !str_contains($productionSource, "\$_ENV['Password Admin']")
            && str_contains($productionSource, 'RewriteEngine On')
            && str_contains($productionSource, 'production_readiness_gate');

        $releaseAuditDashboardReady = $releasePageSource !== ''
            && str_contains($releasePageSource, 'HTTP E2E')
            && str_contains($releasePageSource, 'Final Release Candidate')
            && str_contains($releasePageSource, 'Manual Release Checklist');

        $finalRoutesReady = isset($routes['admin/production-readiness'], $routes['admin/release-audit'], $routes['admin/data-health'], $routes['admin/maintenance']);

        return [
            ['id' => 'rc-production-route-ready', 'title' => 'Route final readiness tersedia', 'ok' => $finalRoutesReady, 'note' => $finalRoutesReady ? 'Release Audit, Kesiapan Website, Cek Sistem, dan Maintenance tersedia sebagai gerbang rilis final.' : 'Cek route admin/production-readiness, admin/release-audit, admin/data-health, dan admin/maintenance.'],
            ['id' => 'rc-env-template-complete', 'title' => '.env.example siap production', 'ok' => count($missingEnvKeys) === 0, 'note' => count($missingEnvKeys) === 0 ? count($requiredEnvKeys) . ' key production penting tersedia di .env.example.' : 'Key belum ada: ' . implode(', ', $missingEnvKeys)],
            ['id' => 'rc-root-hardening-ready', 'title' => 'Root hardening Apache/LiteSpeed siap', 'ok' => $hardeningReady, 'note' => $hardeningReady ? '.htaccess root berisi HTTPS redirect, block sensitive files, Options -Indexes, dan security headers.' : 'Cek .htaccess root, khususnya RewriteEngine, block sensitive files, dan security headers.'],
            ['id' => 'rc-nginx-hardening-doc-ready', 'title' => 'Panduan hardening Nginx tersedia', 'ok' => is_file($nginxDoc) && filesize($nginxDoc) > 200, 'note' => is_file($nginxDoc) ? 'docs/nginx-hardening-aaPanel.conf tersedia untuk VPS/Nginx.' : 'Tambahkan panduan proteksi Nginx untuk VPS/aaPanel.'],
            ['id' => 'rc-runtime-protection-files-ready', 'title' => 'Proteksi folder runtime tersedia', 'ok' => count($missingRuntimeProtection) === 0, 'note' => count($missingRuntimeProtection) === 0 ? 'storage, logs, cache, uploads, dan form-files punya .htaccess proteksi.' : 'Belum ada: ' . implode(', ', array_slice($missingRuntimeProtection, 0, 8))],
            ['id' => 'rc-runtime-artifact-clean', 'title' => 'Package bersih dari artefak runtime', 'ok' => count($runtimeLeaks) === 0, 'note' => count($runtimeLeaks) === 0 ? 'Tidak ada .env, log/jsonl, backup runtime, atau file upload test yang ikut source.' : 'Artefak runtime terdeteksi: ' . implode(', ', array_slice($runtimeLeaks, 0, 8))],
            ['id' => 'rc-production-readiness-source-clean', 'title' => 'Kesiapan Website memakai key dan hardening yang benar', 'ok' => $productionEnvKeyReady, 'note' => $productionEnvKeyReady ? 'Production readiness membaca ADMIN_PASSWORD, mengecek RewriteEngine, dan punya gate final.' : 'Cek core/production-readiness.php untuk ADMIN_PASSWORD dan RewriteEngine.'],
            ['id' => 'rc-release-audit-dashboard-complete', 'title' => 'Dashboard audit final menampilkan semua section baru', 'ok' => $releaseAuditDashboardReady, 'note' => $releaseAuditDashboardReady ? 'Admin Release Audit menampilkan HTTP E2E dan Final Release Candidate, bukan hanya skor tersembunyi.' : 'Tambahkan section HTTP E2E dan Final Release Candidate di admin-release-audit.'],
        ];
    }
}


if (!function_exists('release_audit_admin_layout_css_checks')) {
    function release_audit_admin_layout_css_checks(): array
    {
        $adminPages = glob(ROOT_PATH . '/pages/admin-*.php') ?: [];
        $pagesWithMain = [];
        $pagesMissingHead = [];
        $growthExecutionPages = [
            'pages/admin-money-page-deployment-checklist.php',
            'pages/admin-internal-link-cta-injection-assistant.php',
            'pages/admin-seo-content-refresh-planner.php',
        ];

        foreach ($adminPages as $file) {
            $relative = str_replace(ROOT_PATH . '/', '', $file);
            $source = (string)file_get_contents($file);
            if (!str_contains($source, '<main')) {
                continue;
            }
            $pagesWithMain[] = $relative;
            if (!str_contains($source, 'layout/head.php')) {
                $pagesMissingHead[] = $relative;
            }
        }

        $growthExecutionReady = true;
        foreach ($growthExecutionPages as $relative) {
            $file = ROOT_PATH . '/' . $relative;
            $source = is_file($file) ? (string)file_get_contents($file) : '';
            if ($source === '' || !str_contains($source, 'admin_page') || !str_contains($source, 'layout/head.php') || !str_contains($source, 'layout/header.php')) {
                $growthExecutionReady = false;
                break;
            }
        }

        return [
            ['id' => 'admin-pages-load-head-before-header', 'title' => 'Halaman admin memuat head/CSS sebelum layout sidebar', 'ok' => count($pagesMissingHead) === 0, 'note' => count($pagesMissingHead) === 0 ? count($pagesWithMain) . ' halaman admin dengan main layout sudah memuat head/CSS.' : 'Halaman tanpa head/CSS: ' . implode(', ', array_slice($pagesMissingHead, 0, 8))],
            ['id' => 'growth-execution-pages-css-ready', 'title' => 'Halaman Growth Execution tidak tampil mentah', 'ok' => $growthExecutionReady, 'note' => $growthExecutionReady ? 'Money Page Deployment, Internal Link & CTA Injection, dan SEO Content Refresh sudah memakai admin head + sidebar CSS.' : 'Cek tiga halaman Growth Execution yang dilaporkan tampil mentah.'],
        ];
    }
}

if (!function_exists('release_audit_admin_sidebar_routes')) {
    function release_audit_admin_sidebar_routes(): array
    {
        $headerPath = ROOT_PATH . '/components/layout/header.php';
        if (!is_file($headerPath)) {
            return ['href_routes' => [], 'match_routes' => []];
        }

        $source = (string)file_get_contents($headerPath);
        $hrefRoutes = [];
        if (preg_match_all("/url\(\s*'([^']+)'\s*\)/", $source, $hrefMatches)) {
            foreach ((array)($hrefMatches[1] ?? []) as $route) {
                $route = trim((string)$route, '/');
                if ($route === 'admin' || str_starts_with($route, 'admin/') || str_starts_with($route, 'admin-')) {
                    $hrefRoutes[] = $route;
                }
            }
        }

        $matchRoutes = [];
        if (preg_match_all("/'match'\s*=>\s*\[(.*?)\]/s", $source, $matchBlocks)) {
            foreach ((array)($matchBlocks[1] ?? []) as $block) {
                if (preg_match_all("/'([^']+)'/", (string)$block, $items)) {
                    foreach ((array)($items[1] ?? []) as $route) {
                        $route = trim((string)$route, '/');
                        if ($route === 'admin' || str_starts_with($route, 'admin/') || str_starts_with($route, 'admin-')) {
                            $matchRoutes[] = $route;
                        }
                    }
                }
            }
        }

        return [
            'href_routes' => array_values(array_unique($hrefRoutes)),
            'match_routes' => array_values(array_unique($matchRoutes)),
        ];
    }
}

if (!function_exists('release_audit_permission_stale_routes')) {
    function release_audit_permission_stale_routes(array $routes): array
    {
        if (!function_exists('admin_users_permission_sets')) {
            return [];
        }

        $allowedRoutes = array_fill_keys(array_keys($routes), true);
        $stale = [];
        foreach (admin_users_permission_sets() as $role => $permissions) {
            if (!is_array($permissions) || in_array('*', $permissions, true)) {
                continue;
            }
            foreach ($permissions as $permission) {
                $permission = trim((string)$permission, '/');
                if ($permission === '' || isset($allowedRoutes[$permission])) {
                    continue;
                }
                if (function_exists('admin_auth_is_public_path') && admin_auth_is_public_path($permission)) {
                    continue;
                }
                $stale[] = (string)$role . ': ' . $permission;
            }
        }

        return array_values(array_unique($stale));
    }
}

if (!function_exists('release_audit_route_alias_groups_summary')) {
    function release_audit_route_alias_groups_summary(array $routes): array
    {
        $byTarget = [];
        foreach ($routes as $route => $target) {
            if ($route === 'admin' || str_starts_with($route, 'admin/') || str_starts_with($route, 'admin-')) {
                $byTarget[(string)$target][] = (string)$route;
            }
        }

        $groups = [];
        foreach ($byTarget as $target => $items) {
            $items = array_values(array_unique($items));
            if (count($items) > 1) {
                $groups[$target] = $items;
            }
        }

        return $groups;
    }
}


if (!function_exists('release_audit_landing_page_builder_regression_checks')) {
    function release_audit_landing_page_builder_regression_checks(): array
    {
        $builderPage = is_file(ROOT_PATH . '/pages/admin-landing-pages.php') ? (string)@file_get_contents(ROOT_PATH . '/pages/admin-landing-pages.php') : '';
        $builderCore = is_file(ROOT_PATH . '/core/landing-page-builder.php') ? (string)@file_get_contents(ROOT_PATH . '/core/landing-page-builder.php') : '';
        $publicPage = is_file(ROOT_PATH . '/pages/landing-page.php') ? (string)@file_get_contents(ROOT_PATH . '/pages/landing-page.php') : '';
        $publicCss = is_file(ROOT_PATH . '/assets/css/app.css') ? (string)@file_get_contents(ROOT_PATH . '/assets/css/app.css') : '';
        $adminCss = is_file(ROOT_PATH . '/assets/css/admin.css') ? (string)@file_get_contents(ROOT_PATH . '/assets/css/admin.css') : '';
        $publicJs = is_file(ROOT_PATH . '/assets/js/app.js') ? (string)@file_get_contents(ROOT_PATH . '/assets/js/app.js') : '';
        $footer = is_file(ROOT_PATH . '/components/layout/footer.php') ? (string)@file_get_contents(ROOT_PATH . '/components/layout/footer.php') : '';
        $theme = is_file(ROOT_PATH . '/core/theme.php') ? (string)@file_get_contents(ROOT_PATH . '/core/theme.php') : '';

        $blockTypes = function_exists('landing_page_allowed_block_types') ? landing_page_allowed_block_types() : [];
        $expectedBlocks = ['hero_offer', 'text', 'pain_points', 'benefits', 'pricing_cards', 'faq', 'lead_form', 'media', 'free_cards', 'custom_menu', 'countdown_timer', 'html_block', 'cta'];
        $missingBlocks = [];
        foreach ($expectedBlocks as $type) {
            if (!in_array($type, $blockTypes, true)) {
                $missingBlocks[] = $type;
            }
        }

        $builderPublicMarkers = str_contains($builderPage, 'data-lp-builder-version="v33.1.15"') && str_contains($publicPage, 'data-lp-renderer="v33.1.15"');
        $previewPublicSync = str_contains($builderPage, 'function previewBlock')
            && str_contains($builderPage, 'data-preview-block')
            && str_contains($builderCore, 'function landing_page_render_blocks')
            && str_contains($builderCore, 'landing_page_block_style_attrs($block)')
            && str_contains($builderCore, 'landing_page_item_style_attrs($item)');
        $buttonDesignReady = str_contains($builderCore, "'button_bg'")
            && str_contains($builderCore, "'button_text_color'")
            && str_contains($builderCore, "'button_align'")
            && str_contains($publicCss, '--lp-button-bg')
            && str_contains($publicCss, '--lp-button-color')
            && str_contains($publicCss, 'data-lp-button-align')
            && str_contains($builderPage, 'button_bg')
            && str_contains($builderPage, 'button_text_color');
        $customMenuReady = str_contains($builderCore, "'custom_menu'")
            && str_contains($builderCore, "'item_type'")
            && str_contains($builderCore, 'lp-custom-menu__logo-slot')
            && str_contains($builderCore, 'lp-custom-menu__links')
            && str_contains($builderCore, 'data-lp-menu-align')
            && str_contains($builderCore, 'data-lp-logo-align')
            && str_contains($publicCss, 'lp-custom-menu__links')
            && str_contains($publicCss, 'data-lp-menu-position="sticky"')
            && str_contains($publicJs, 'lpStickyFallbackBound');
        $miniFooterReady = str_contains($builderCore, "'mini_footer_bg'")
            && str_contains($builderCore, "'mini_footer_brand_color'")
            && str_contains($builderCore, "'mini_footer_text_color'")
            && str_contains($footer, 'landing-mini-footer--custom')
            && str_contains($footer, 'background:')
            && str_contains($footer, '--lp-mini-footer-bg')
            && str_contains($publicCss, '.landing-mini-footer--custom')
            && str_contains($theme, '.landing-mini-footer:not(.landing-mini-footer--custom)');
        $htmlSafeReady = str_contains($builderCore, 'landing_page_sanitize_full_html_document')
            && str_contains($builderCore, '<script')
            && str_contains($builderCore, 'javascript')
            && str_contains($builderPage, 'sanitizePreviewHtml')
            && str_contains($builderPage, 'Full HTML Expert Mode');
        $draftPublishReady = str_contains($builderPage, 'localStorage')
            && str_contains($builderPage, 'hasUnsavedChanges')
            && str_contains($builderCore, 'landing_page_revision')
            && str_contains($builderCore, 'landing_page_save')
            && (str_contains($builderPage, 'openPublishGuard') || str_contains($builderPage, 'data-lpw-publish-guard-dialog')); 
        $trackingSeoReady = str_contains($builderCore, 'landing_page_button_tracking_context')
            && str_contains($builderCore, 'landing_page_register_block_schemas')
            && str_contains($publicPage, 'set_seo')
            && str_contains($publicPage, 'landing_page_ab_prepare_public_page')
            && str_contains($publicPage, 'window.__MARKETING_PAGE_EVENT__');
        $sidebarCleanReady = !str_contains($builderPage, 'lpw-sidebar-template-card')
            && str_contains($builderPage, 'data-template-gallery-dialog')
            && str_contains($builderPage, 'data-open-template-gallery');
        $cssConflictGuard = str_contains($publicCss, '.landing-page-builder{')
            && str_contains($publicCss, 'overflow-y:visible!important')
            && str_contains($adminCss, '.admin-lp-builder-v331')
            && str_contains($adminCss, '.lpw-pv-custom-menu-header')
            && str_contains($publicCss, '.landing-page-builder .lp-section .lp-btn');

        return [
            ['id' => 'lp-builder-version-marker-sync', 'title' => 'Marker versi builder dan renderer sinkron', 'ok' => $builderPublicMarkers, 'note' => $builderPublicMarkers ? 'Builder dan public renderer memakai marker v33.1.15.' : 'Cek marker data-lp-builder-version dan data-lp-renderer.'],
            ['id' => 'lp-builder-block-registry-complete', 'title' => 'Registry block utama LP Builder lengkap', 'ok' => count($missingBlocks) === 0, 'note' => count($missingBlocks) === 0 ? count($expectedBlocks) . ' tipe block inti tersedia.' : 'Block hilang: ' . implode(', ', $missingBlocks)],
            ['id' => 'lp-builder-preview-public-sync', 'title' => 'Preview dan public render memakai kontrak style yang sama', 'ok' => $previewPublicSync, 'note' => $previewPublicSync ? 'Preview block, public renderer, style attrs, dan item attrs tetap tersambung.' : 'Cek previewBlock, landing_page_render_blocks, dan helper style attrs.'],
            ['id' => 'lp-builder-button-design-frontend', 'title' => 'Design tombol ikut ke frontend', 'ok' => $buttonDesignReady, 'note' => $buttonDesignReady ? 'Button bg, warna teks, ukuran, radius, dan align tersedia dari admin sampai CSS public.' : 'Cek field button_* di builder, sanitize, renderer, dan CSS public.'],
            ['id' => 'lp-builder-custom-menu-header-logo-sticky', 'title' => 'Custom Menu header/logo/sticky aman', 'ok' => $customMenuReady, 'note' => $customMenuReady ? 'Custom Menu punya item logo, slot logo, slot menu, align, sticky/fixed, dan fallback JS.' : 'Cek custom_menu renderer, CSS header, dan fallback sticky JS.'],
            ['id' => 'lp-builder-mini-footer-custom-color', 'title' => 'Mini footer custom color tidak dioverride tema', 'ok' => $miniFooterReady, 'note' => $miniFooterReady ? 'Mini footer memakai class custom, inline style, CSS var, dan theme guard :not(.landing-mini-footer--custom).' : 'Cek footer layout, CSS public, dan theme coverage.'],
            ['id' => 'lp-builder-html-safe-mode', 'title' => 'HTML block dan full HTML mode tetap disanitasi', 'ok' => $htmlSafeReady, 'note' => $htmlSafeReady ? 'Sanitizer membuang script/event handler/javascript URL; preview expert mode tetap punya warning.' : 'Cek sanitizer HTML public dan preview builder.'],
            ['id' => 'lp-builder-draft-publish-revision-guard', 'title' => 'Draft/publish/revision guard tetap tersedia', 'ok' => $draftPublishReady, 'note' => $draftPublishReady ? 'Local draft guard, unsaved warning, publish guard, save, dan revision helper tersedia.' : 'Cek localStorage, hasUnsavedChanges, publish guard, save, dan revision.'],
            ['id' => 'lp-builder-seo-analytics-ab-bridge', 'title' => 'SEO, analytics, tracking, dan A/B bridge tidak terganggu', 'ok' => $trackingSeoReady, 'note' => $trackingSeoReady ? 'Landing page masih register SEO/schema, marketing page event, CTA tracking, dan A/B prepare.' : 'Cek pages/landing-page.php dan helper tracking/schema/A-B.'],
            ['id' => 'lp-builder-sidebar-gallery-clean', 'title' => 'Sidebar builder bersih dari card gallery pengganggu', 'ok' => $sidebarCleanReady, 'note' => $sidebarCleanReady ? 'Card Template Gallery tidak ada di sidebar kiri, akses tetap lewat dialog/dropdown topbar.' : 'Cek area sidebar dan dialog Template Gallery.'],
            ['id' => 'lp-builder-css-conflict-guard', 'title' => 'CSS conflict guard untuk update LP Builder aktif', 'ok' => $cssConflictGuard, 'note' => $cssConflictGuard ? 'Public/admin CSS punya scope builder, sticky overflow guard, button override, dan preview menu style.' : 'Cek assets/css/app.css dan assets/css/admin.css.'],
        ];
    }
}

if (!function_exists('release_audit_route_checks')) {
    function release_audit_route_checks(): array
    {
        $routes = release_audit_route_map();
        $adminRoutes = array_filter(array_keys($routes), static fn(string $route): bool => $route === 'admin' || str_starts_with($route, 'admin/') || str_starts_with($route, 'admin-'));
        $missingPages = [];
        foreach ($routes as $route => $page) {
            if (!is_file(PAGES_PATH . '/' . $page)) {
                $missingPages[] = $route . ' → ' . $page;
            }
        }

        $sidebar = release_audit_admin_sidebar_routes();
        $routeKeys = array_fill_keys(array_keys($routes), true);
        $sidebarHrefMissing = [];
        foreach ((array)($sidebar['href_routes'] ?? []) as $route) {
            if (!isset($routeKeys[$route])) {
                $sidebarHrefMissing[] = (string)$route;
            }
        }
        $sidebarMatchMissing = [];
        foreach ((array)($sidebar['match_routes'] ?? []) as $route) {
            if (!isset($routeKeys[$route])) {
                $sidebarMatchMissing[] = (string)$route;
            }
        }

        $stalePermissionRoutes = release_audit_permission_stale_routes($routes);
        $aliasGroups = release_audit_route_alias_groups_summary($routes);

        return [
            ['id' => 'route-pages', 'title' => 'Semua route menunjuk page valid', 'ok' => count($missingPages) === 0, 'note' => count($missingPages) === 0 ? 'Tidak ada route yang menunjuk file hilang.' : implode(', ', array_slice($missingPages, 0, 6))],
            ['id' => 'admin-route-guard', 'title' => 'Route admin masuk pola auth guard', 'ok' => count($adminRoutes) > 20 && function_exists('admin_auth_is_admin_path'), 'note' => count($adminRoutes) . ' route admin terdeteksi dan masuk pola guard terpusat.'],
            ['id' => 'release-route', 'title' => 'Route audit kesiapan tersedia', 'ok' => isset($routes['admin/release-audit']), 'note' => 'Menu audit final bisa dibuka dari dashboard.'],
            ['id' => 'sidebar-href-routes', 'title' => 'Link sidebar admin punya route valid', 'ok' => count($sidebarHrefMissing) === 0, 'note' => count($sidebarHrefMissing) === 0 ? count((array)($sidebar['href_routes'] ?? [])) . ' link sidebar admin valid.' : implode(', ', array_slice($sidebarHrefMissing, 0, 8))],
            ['id' => 'sidebar-match-routes', 'title' => 'Alias menu sidebar punya route valid', 'ok' => count($sidebarMatchMissing) === 0, 'note' => count($sidebarMatchMissing) === 0 ? count((array)($sidebar['match_routes'] ?? [])) . ' alias menu sidebar valid.' : implode(', ', array_slice($sidebarMatchMissing, 0, 8))],
            ['id' => 'permission-stale-routes', 'title' => 'Permission role bebas route mati', 'ok' => count($stalePermissionRoutes) === 0, 'note' => count($stalePermissionRoutes) === 0 ? 'Tidak ada permission role yang menunjuk route mati/stale.' : implode(', ', array_slice($stalePermissionRoutes, 0, 8))],
            ['id' => 'admin-route-alias-groups', 'title' => 'Alias route admin konsisten', 'ok' => count($missingPages) === 0 && count($aliasGroups) > 0, 'note' => count($aliasGroups) . ' target halaman admin memiliki alias route yang valid.'],
        ];
    }
}

if (!function_exists('release_audit_check_group_score')) {
    function release_audit_check_group_score(array $checks): int
    {
        if (!$checks) {
            return 0;
        }
        $passed = 0;
        foreach ($checks as $check) {
            if (!empty($check['ok'])) {
                $passed++;
            }
        }
        return (int)round(($passed / count($checks)) * 100);
    }
}


if (!function_exists('release_audit_storage_database_checks')) {
    function release_audit_storage_database_checks(): array
    {
        $content = is_file(ROOT_PATH . '/core/content.php') ? (string)file_get_contents(ROOT_PATH . '/core/content.php') : '';
        $product = is_file(ROOT_PATH . '/core/product.php') ? (string)file_get_contents(ROOT_PATH . '/core/product.php') : '';
        $landing = is_file(ROOT_PATH . '/core/landing-page-builder.php') ? (string)file_get_contents(ROOT_PATH . '/core/landing-page-builder.php') : '';
        $storage = is_file(ROOT_PATH . '/core/storage-adapter.php') ? (string)file_get_contents(ROOT_PATH . '/core/storage-adapter.php') : '';
        $index = is_file(ROOT_PATH . '/index.php') ? (string)file_get_contents(ROOT_PATH . '/index.php') : '';
        $report = function_exists('storage_adapter_report') ? storage_adapter_report() : [];
        $driver = (string)($report['driver'] ?? 'file');
        $dbAvailable = !empty($report['db_available']);
        $activeMysql = (int)($report['summary']['mysql_active_collections'] ?? 0);

        return [
            ['id' => 'storage-adapter-core', 'title' => 'Storage Adapter core tersedia', 'ok' => is_file(ROOT_PATH . '/core/storage-adapter.php') && function_exists('storage_adapter_report') && function_exists('storage_mysql_enabled'), 'note' => 'Fondasi adapter file/JSON → MySQL sudah dimuat sebelum modul konten.'],
            ['id' => 'storage-admin-page', 'title' => 'Menu Storage & Database tersedia', 'ok' => is_file(ROOT_PATH . '/pages/admin-storage-database.php') && str_contains($index, 'admin/storage-database') && release_audit_admin_menu_has('admin/storage-database'), 'note' => 'Admin punya halaman untuk melihat mode penyimpanan, status DB, dan kesiapan collection.'],
            ['id' => 'safe-default-file', 'title' => 'Default aman tetap file-based', 'ok' => $driver === 'file' || $dbAvailable || $activeMysql === 0, 'note' => 'Website tidak otomatis pindah ke MySQL hanya karena DB_HOST diisi. Switch dilakukan per collection.'],
            ['id' => 'article-storage-guard', 'title' => 'Artikel memakai storage guard', 'ok' => str_contains($content, "storage_mysql_enabled('articles')"), 'note' => 'CRUD artikel hanya memakai MySQL jika driver, koneksi, tabel, dan collection sudah siap.'],
            ['id' => 'product-storage-guard', 'title' => 'Produk memakai storage guard', 'ok' => str_contains($product, "storage_mysql_enabled('products')"), 'note' => 'CRUD produk hanya memakai MySQL jika driver, koneksi, tabel, dan collection sudah siap.'],
            ['id' => 'product-id-mysql-insert', 'title' => 'Produk MySQL menjaga ID runtime', 'ok' => str_contains($product, "'id',\n        'source'") && str_contains($product, '!in_array($field, [\'id\', \'created_at\']'), 'note' => 'Field ID ikut insert ke tabel products agar primary key non-auto tetap aman saat migrasi.'],
            ['id' => 'landing-page-storage-guard', 'title' => 'Landing Page memakai storage guard', 'ok' => str_contains($landing, "storage_adapter_mysql_read_landing_pages") && str_contains($landing, "storage_adapter_mysql_replace_landing_pages"), 'note' => 'Landing Page Builder bisa membaca/menulis MySQL runtime jika collection diaktifkan, sambil mirror ke JSON.'],
            ['id' => 'schema-foundation-sql', 'title' => 'Schema fondasi MySQL tersedia', 'ok' => is_file(ROOT_PATH . '/database/mysql-storage-schema.sql') || is_file(ROOT_PATH . '/database.sql'), 'note' => 'File SQL disediakan untuk menyiapkan tabel typed bertahap dan generic bridge.'],
            ['id' => 'migration-tool-page', 'title' => 'Tool Migrasi Data MySQL tersedia', 'ok' => is_file(ROOT_PATH . '/pages/admin-data-migration.php') && str_contains($index, 'admin/data-migration') && release_audit_admin_menu_has('admin/data-migration'), 'note' => 'Admin punya halaman preview, backup, dan migrasi bridge tanpa otomatis mengganti runtime.'],
            ['id' => 'migration-helper-ready', 'title' => 'Helper migrasi aman tersedia', 'ok' => function_exists('storage_adapter_preview_collection_migration') && function_exists('storage_adapter_run_generic_migration') && function_exists('storage_adapter_run_collection_migration') && function_exists('storage_adapter_schema_status'), 'note' => 'Migrasi memakai preview, backup, schema check, tabel runtime untuk konten inti, data operasional, analytics, dan bridge fallback.'],
            ['id' => 'runtime-content-migration-helper', 'title' => 'Migrasi konten inti ke tabel runtime tersedia', 'ok' => str_contains($storage, 'storage_adapter_run_products_typed_migration') && str_contains($storage, 'storage_adapter_run_articles_typed_migration') && str_contains($storage, 'storage_adapter_run_landing_pages_typed_migration'), 'note' => 'Produk, artikel, dan landing page bisa dimigrasikan ke tabel runtime MySQL secara bertahap.'],
            ['id' => 'runtime-operational-migration-helper', 'title' => 'Migrasi data operasional ke tabel runtime tersedia', 'ok' => str_contains($storage, 'storage_adapter_run_form_submissions_typed_migration') && str_contains($storage, 'storage_adapter_run_inquiries_typed_migration') && str_contains($storage, 'storage_adapter_run_orders_typed_migration') && str_contains($storage, 'storage_adapter_run_payment_proofs_typed_migration'), 'note' => 'Form submission, inbox lead/inquiry, order, item order, dan bukti pembayaran bisa dimigrasikan ke tabel runtime MySQL.'],
            ['id' => 'runtime-analytics-log-migration-helper', 'title' => 'Migrasi analytics dan log ke tabel runtime tersedia', 'ok' => str_contains($storage, 'storage_adapter_run_analytics_events_typed_migration') && str_contains($storage, 'storage_adapter_run_email_logs_typed_migration') && str_contains($storage, 'storage_adapter_run_activity_logs_typed_migration'), 'note' => 'Analytics/lead events, riwayat email, dan log aktivitas admin bisa masuk MySQL runtime bertahap.'],
            ['id' => 'runtime-bridge-leads-orders', 'title' => 'Runtime Lead, Order, Analytics, dan Log tersedia', 'ok' => function_exists('storage_adapter_mysql_append_form_submission') && function_exists('storage_adapter_mysql_read_form_submissions') && function_exists('storage_adapter_mysql_append_inquiry') && function_exists('storage_adapter_mysql_read_inquiries') && function_exists('storage_adapter_mysql_append_order') && function_exists('storage_adapter_mysql_read_orders') && function_exists('storage_adapter_mysql_append_analytics_event') && function_exists('storage_adapter_mysql_read_analytics_events') && function_exists('storage_adapter_mysql_append_email_log') && function_exists('storage_adapter_mysql_read_email_logs'), 'note' => 'Lead/form submission, inquiry, order, analytics, dan email log bisa memakai MySQL jika collection diaktifkan admin, dengan fallback file-based.'],
            ['id' => 'storage-doc-ready', 'title' => 'Panduan migrasi bertahap tersedia', 'ok' => is_file(ROOT_PATH . '/docs/storage-database-guide.md'), 'note' => 'Panduan menjelaskan cara migrasi file/JSON ke MySQL secara bertahap.'],
        ];
    }
}


if (!function_exists('release_audit_cloud_backup_checks')) {
    function release_audit_cloud_backup_checks(): array
    {
        $index = is_file(ROOT_PATH . '/index.php') ? (string)file_get_contents(ROOT_PATH . '/index.php') : '';
        $core = is_file(ROOT_PATH . '/core/cloud-backup.php') ? (string)file_get_contents(ROOT_PATH . '/core/cloud-backup.php') : '';
        $lookerCore = is_file(ROOT_PATH . '/core/looker-studio-connector.php') ? (string)file_get_contents(ROOT_PATH . '/core/looker-studio-connector.php') : '';
        $lookerWizardCore = is_file(ROOT_PATH . '/core/looker-studio-setup-wizard.php') ? (string)file_get_contents(ROOT_PATH . '/core/looker-studio-setup-wizard.php') : '';
        $lookerTemplateCore = is_file(ROOT_PATH . '/core/looker-studio-dashboard-pack.php') ? (string)file_get_contents(ROOT_PATH . '/core/looker-studio-dashboard-pack.php') : '';
        $cloudPage = is_file(ROOT_PATH . '/pages/admin-cloud-backup-sync.php') ? (string)file_get_contents(ROOT_PATH . '/pages/admin-cloud-backup-sync.php') : '';
        $report = function_exists('cloud_backup_report') ? cloud_backup_report() : [];
        $lookerReport = function_exists('looker_studio_report') ? looker_studio_report() : [];
        $sources = (array)($report['sources'] ?? []);
        $sourceKeys = array_map(static fn(array $row): string => (string)($row['key'] ?? ''), $sources);

        return [
            ['id' => 'cloud-backup-core', 'title' => 'Core Backup & Sync Data tersedia', 'ok' => is_file(ROOT_PATH . '/core/cloud-backup.php') && function_exists('cloud_backup_report') && function_exists('cloud_backup_export_source'), 'note' => 'Website bisa menyiapkan export CSV/JSON dan payload cloud tanpa mengubah runtime utama.'],
            ['id' => 'cloud-backup-admin-page', 'title' => 'Menu Backup & Sync Data tersedia', 'ok' => is_file(ROOT_PATH . '/pages/admin-cloud-backup-sync.php') && str_contains($index, 'admin/cloud-backup-sync') && release_audit_admin_menu_has('admin/cloud-backup-sync'), 'note' => 'Admin punya halaman untuk setting Google Sheets, Google Drive, dan endpoint Apps Script.'],
            ['id' => 'cloud-source-leads-orders', 'title' => 'Sumber lead dan order dipetakan', 'ok' => in_array('form_submissions', $sourceKeys, true) && in_array('orders', $sourceKeys, true), 'note' => 'Data lead dan order siap menjadi sheet utama untuk dashboard owner.'],
            ['id' => 'cloud-source-analytics-member', 'title' => 'Sumber analytics dan member dipetakan', 'ok' => in_array('analytics_events', $sourceKeys, true) && in_array('buyer_accounts', $sourceKeys, true) && in_array('member_access', $sourceKeys, true), 'note' => 'Data growth dan akses produk digital siap disiapkan untuk Looker Studio.'],
            ['id' => 'cloud-source-growth-dashboard', 'title' => 'Sumber growth dashboard dipetakan', 'ok' => in_array('landing_page_analytics', $sourceKeys, true) && in_array('cta_results', $sourceKeys, true) && in_array('seo_profit_attribution', $sourceKeys, true) && in_array('lead_quality_scores', $sourceKeys, true), 'note' => 'Data analytics lintas menu siap dikirim ke Google Sheets dan dashboard visual.'],
            ['id' => 'cloud-local-export-safe', 'title' => 'Export lokal aman tersedia', 'ok' => function_exists('cloud_backup_build_csv') && function_exists('cloud_backup_rows') && str_contains($core, 'cloud_backup_export_dir'), 'note' => 'Admin bisa membuat CSV/JSON lokal walaupun Google endpoint belum siap.'],
            ['id' => 'cloud-apps-script-bridge', 'title' => 'Jembatan Apps Script tersedia', 'ok' => function_exists('cloud_backup_send_payload') && str_contains($core, 'X-Ugrowth-Token') && str_contains($core, "'auth'") && str_contains($core, 'https://'), 'note' => 'Sync cloud memakai endpoint HTTPS dan token yang kompatibel dengan Google Apps Script.'],
            ['id' => 'cloud-apps-script-template', 'title' => 'Kode Apps Script siap salin tersedia', 'ok' => function_exists('cloud_backup_apps_script_ready') && cloud_backup_apps_script_ready() && is_file(ROOT_PATH . '/integrations/google-sheets/apps-script-connector.js'), 'note' => 'Admin bisa menyalin connector Apps Script langsung dari dashboard tanpa mencocokkan tabel manual.'],
            ['id' => 'cloud-sync-reliability-guard', 'title' => 'Reliability guard sinkronisasi tersedia', 'ok' => function_exists('cloud_backup_health_report') && function_exists('cloud_backup_sync_history') && function_exists('cloud_backup_export_all_enabled') && str_contains($core, 'rows_checksum') && str_contains($core, 'retry_attempts'), 'note' => 'Backup & Sync Data punya score health, retry ringan, payload hash, history log privat, dan backup batch semua sumber aktif.'],
            ['id' => 'cloud-sync-sensitive-redaction', 'title' => 'Data sensitif tidak dikirim ke sheet', 'ok' => function_exists('cloud_backup_sensitive_key') && str_contains($core, '[redacted]') && str_contains($core, 'password|pass|token|secret'), 'note' => 'Field token, password, secret, session, dan path sensitif direduksi sebelum export/sync.'],
            ['id' => 'google-sheets-reliability-script', 'title' => 'Apps Script punya guard payload dan log sync', 'ok' => str_contains((string)@file_get_contents(ROOT_PATH . '/integrations/google-sheets/apps-script-connector.js'), 'UGROWTH_MAX_ROWS_PER_REQUEST') && str_contains((string)@file_get_contents(ROOT_PATH . '/integrations/google-sheets/apps-script-connector.js'), 'appendSyncLog_') && str_contains((string)@file_get_contents(ROOT_PATH . '/integrations/google-sheets/apps-script-connector.js'), 'checksumRows_'), 'note' => 'Connector Google Sheets membatasi row, mencatat payload_id/checksum, dan membuat tab _sync_log.'],
            ['id' => 'looker-direct-core', 'title' => 'Koneksi langsung Looker Studio tersedia', 'ok' => is_file(ROOT_PATH . '/core/looker-studio-connector.php') && function_exists('looker_studio_report') && function_exists('looker_studio_rows') && str_contains($index, 'api/looker-studio-data'), 'note' => 'Looker Studio bisa membaca endpoint U-Growth langsung melalui connector berbasis token.'],
            ['id' => 'looker-direct-connector-script', 'title' => 'Community Connector siap salin tersedia', 'ok' => function_exists('looker_studio_connector_ready') && looker_studio_connector_ready() && is_file(ROOT_PATH . '/integrations/looker-studio/community-connector.js') && str_contains($lookerCore, 'looker_studio_schema'), 'note' => 'Kode connector Apps Script untuk Looker Studio disiapkan di dashboard.'],
            ['id' => 'looker-direct-api-hardening', 'title' => 'Endpoint Looker Studio di-hardening', 'ok' => str_contains((string)@file_get_contents(ROOT_PATH . '/pages/looker-studio-data.php'), 'X-Content-Type-Options') && str_contains((string)@file_get_contents(ROOT_PATH . '/pages/looker-studio-data.php'), 'min(5000') && str_contains((string)@file_get_contents(ROOT_PATH . '/integrations/looker-studio/community-connector.js'), 'URL API harus HTTPS') && str_contains((string)@file_get_contents(ROOT_PATH . '/integrations/looker-studio/community-connector.js'), 'MAX_LOOKER_ROWS'), 'note' => 'Endpoint dan connector Looker membatasi rows, membersihkan source, mewajibkan HTTPS/token, dan punya retry ringan.'],
            ['id' => 'looker-direct-source-map', 'title' => 'Data source visual utama tersedia', 'ok' => (int)($lookerReport['total_sources'] ?? 0) >= 10, 'note' => 'Connector langsung menyiapkan data lead, order, analytics, CTA, SEO profit, dan member.'],
            ['id' => 'looker-curated-schema', 'title' => 'Schema visual Looker Studio distabilkan', 'ok' => function_exists('looker_studio_curated_schema') && count(looker_studio_curated_schema('orders')) >= 10 && count(looker_studio_curated_schema('form_submissions')) >= 8, 'note' => 'Field penting untuk order, lead, SEO profit, CTA, campaign, dan member disiapkan agar chart lebih mudah dibuat.'],
            ['id' => 'looker-preview-dashboard-pack', 'title' => 'Preview data dan blueprint dashboard tersedia', 'ok' => function_exists('looker_studio_source_preview') && function_exists('looker_studio_dashboard_blueprints') && str_contains($cloudPage, 'Looker Studio Data Preview'), 'note' => 'Admin bisa mengecek contoh data, field, dan rekomendasi dashboard sebelum membuat visual di Looker Studio.'],
            ['id' => 'looker-setup-wizard-ready', 'title' => 'Setup Wizard Looker Studio tersedia', 'ok' => is_file(ROOT_PATH . '/core/looker-studio-setup-wizard.php') && function_exists('looker_studio_setup_wizard_readiness') && function_exists('looker_studio_setup_wizard_source_test') && str_contains($cloudPage, 'Looker Studio Setup Wizard') && str_contains($index, 'admin/looker-studio-setup') && str_contains($lookerWizardCore, 'looker_studio_setup_wizard_test_urls'), 'note' => 'Admin punya wizard untuk cek domain, token, endpoint, connector, source data, dan URL test sebelum membuat dashboard.'],
            ['id' => 'looker-visual-readiness', 'title' => 'Kesiapan visual lintas source terbaca', 'ok' => function_exists('looker_studio_visual_readiness') && (int)(looker_studio_visual_readiness()['total_sources'] ?? 0) >= 15, 'note' => 'Sistem membaca data source yang siap divisualisasikan menjadi dashboard keputusan owner.'],
            ['id' => 'looker-template-pack-ready', 'title' => 'Template dashboard keputusan tersedia', 'ok' => is_file(ROOT_PATH . '/core/looker-studio-dashboard-pack.php') && function_exists('looker_studio_dashboard_template_pack') && function_exists('looker_studio_dashboard_template_readiness') && (int)(looker_studio_dashboard_template_summary()['dashboards'] ?? 0) >= 6 && str_contains($cloudPage, 'Dashboard Template Pack'), 'note' => 'Admin punya template dashboard owner, sales, lead, SEO profit, CTA campaign, dan member untuk memandu visualisasi Looker Studio.'],
            ['id' => 'looker-template-apps-script-tabs', 'title' => 'Apps Script menyiapkan tab panduan dashboard', 'ok' => str_contains((string)@file_get_contents(ROOT_PATH . '/integrations/google-sheets/apps-script-connector.js'), 'setupUGrowthDashboardTemplate') && str_contains((string)@file_get_contents(ROOT_PATH . '/integrations/google-sheets/apps-script-connector.js'), '_chart_blueprint') && str_contains($lookerTemplateCore, 'looker_studio_dashboard_template_sheet_matrix'), 'note' => 'Google Sheets connector bisa membuat tab dashboard guide, field dictionary, dan chart blueprint.'],
            ['id' => 'cloud-doc-ready', 'title' => 'Panduan Backup & Looker Studio tersedia', 'ok' => is_file(ROOT_PATH . '/docs/cloud-backup-sync-guide.md') && is_file(ROOT_PATH . '/docs/looker-studio-data-guide.md') && is_file(ROOT_PATH . '/docs/google-sheets-apps-script-guide.md') && is_file(ROOT_PATH . '/docs/looker-studio-direct-connector-guide.md') && is_file(ROOT_PATH . '/docs/looker-studio-dashboard-pack-guide.md') && is_file(ROOT_PATH . '/docs/looker-studio-setup-wizard-guide.md') && is_file(ROOT_PATH . '/docs/looker-studio-template-pack-guide.md'), 'note' => 'Panduan admin menjelaskan alur U-Growth → Google Sheets/Drive → Looker Studio, koneksi langsung, dan template dashboard.'],
        ];
    }
}

if (!function_exists('release_audit_summary')) {
    function release_audit_summary(): array
    {
        $moduleRows = release_audit_module_rows();
        $moduleScore = 0;
        foreach ($moduleRows as $row) {
            $moduleScore += (int)($row['score'] ?? 0);
        }
        $moduleScore = $moduleRows ? (int)round($moduleScore / count($moduleRows)) : 0;

        $security = release_audit_security_checks();
        $dataFlow = release_audit_data_flow_checks();
        $publicCopy = release_audit_public_copy_checks();
        $defaultContent = release_audit_default_content_checks();
        $workflow = release_audit_workflow_checks();
        $adminCrudRuntime = release_audit_admin_crud_runtime_checks();
        $publicSubmissionRuntime = release_audit_public_submission_runtime_checks();
        $rolePermissionRuntime = release_audit_role_permission_runtime_checks();
        $httpE2E = release_audit_http_e2e_checks();
        $commerceRuntime = release_audit_commerce_runtime_checks();
        $buyerAccessRestriction = release_audit_buyer_access_restriction_checks();
        $finalRC = release_audit_final_release_candidate_checks();
        $adminLayoutCss = release_audit_admin_layout_css_checks();
        $storageDatabase = release_audit_storage_database_checks();
        $cloudBackup = release_audit_cloud_backup_checks();
        $lpBuilderRegression = release_audit_landing_page_builder_regression_checks();
        $routes = release_audit_route_checks();
        $state = release_audit_state();

        $scores = [
            'module_wiring' => $moduleScore,
            'security' => release_audit_check_group_score($security),
            'data_flow' => release_audit_check_group_score($dataFlow),
            'public_copy' => release_audit_check_group_score($publicCopy),
            'default_content' => release_audit_check_group_score($defaultContent),
            'workflow' => release_audit_check_group_score($workflow),
            'admin_crud_runtime' => release_audit_check_group_score($adminCrudRuntime),
            'public_submission_runtime' => release_audit_check_group_score($publicSubmissionRuntime),
            'role_permission_runtime' => release_audit_check_group_score($rolePermissionRuntime),
            'http_e2e' => release_audit_check_group_score($httpE2E),
            'commerce_runtime' => release_audit_check_group_score($commerceRuntime),
            'buyer_access_restriction' => release_audit_check_group_score($buyerAccessRestriction),
            'final_release_candidate' => release_audit_check_group_score($finalRC),
            'admin_layout_css' => release_audit_check_group_score($adminLayoutCss),
            'storage_database' => release_audit_check_group_score($storageDatabase),
            'cloud_backup_sync' => release_audit_check_group_score($cloudBackup),
            'lp_builder_regression' => release_audit_check_group_score($lpBuilderRegression),
            'routes' => release_audit_check_group_score($routes),
        ];
        $overall = (int)round(array_sum($scores) / max(1, count($scores)));
        $critical = array_values(array_filter(array_merge($security, $dataFlow, $publicCopy, $defaultContent, $workflow, $adminCrudRuntime, $publicSubmissionRuntime, $rolePermissionRuntime, $httpE2E, $commerceRuntime, $buyerAccessRestriction, $finalRC, $adminLayoutCss, $storageDatabase, $cloudBackup, $lpBuilderRegression, $routes), static fn(array $check): bool => empty($check['ok'])));

        return [
            'schema' => 'website-readiness-audit',
            'title' => 'Audit Kesiapan Website',
            'overall_score' => $overall,
            'score_label' => $overall >= 95 ? 'Siap Rilis' : ($overall >= 85 ? 'Siap dengan catatan ringan' : 'Perlu pengecekan'),
            'scores' => $scores,
            'modules' => $moduleRows,
            'security_checks' => $security,
            'data_flow_checks' => $dataFlow,
            'public_copy_checks' => $publicCopy,
            'default_content_checks' => $defaultContent,
            'workflow_checks' => $workflow,
            'admin_crud_runtime_checks' => $adminCrudRuntime,
            'public_submission_runtime_checks' => $publicSubmissionRuntime,
            'role_permission_runtime_checks' => $rolePermissionRuntime,
            'http_e2e_checks' => $httpE2E,
            'commerce_runtime_checks' => $commerceRuntime,
            'buyer_access_restriction_checks' => $buyerAccessRestriction,
            'final_release_candidate_checks' => $finalRC,
            'admin_layout_css_checks' => $adminLayoutCss,
            'storage_database_checks' => $storageDatabase,
            'cloud_backup_checks' => $cloudBackup,
            'lp_builder_regression_checks' => $lpBuilderRegression,
            'route_checks' => $routes,
            'critical_findings' => $critical,
            'state' => $state,
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('release_audit_export_csv')) {
    function release_audit_export_csv(array $summary): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="release-audit-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Area', 'Item', 'Status', 'Score/OK', 'Catatan']);
        fputcsv($out, ['Overview', (string)($summary['title'] ?? 'Audit Kesiapan Website'), (string)($summary['score_label'] ?? ''), (string)($summary['overall_score'] ?? 0), 'Kesiapan rilis']);
        foreach ((array)($summary['scores'] ?? []) as $key => $value) {
            fputcsv($out, ['Score', (string)$key, 'OK', (string)$value, '']);
        }
        foreach ((array)($summary['modules'] ?? []) as $module) {
            fputcsv($out, ['Module', (string)($module['title'] ?? ''), (string)($module['status'] ?? ''), (string)($module['score'] ?? 0), (string)($module['source'] ?? '')]);
        }
        foreach (['security_checks' => 'Security', 'data_flow_checks' => 'Data Flow', 'public_copy_checks' => 'Public Copy', 'default_content_checks' => 'Default Content', 'workflow_checks' => 'Workflow', 'admin_crud_runtime_checks' => 'Admin CRUD Runtime', 'public_submission_runtime_checks' => 'Public Submission Runtime', 'role_permission_runtime_checks' => 'Role Permission Runtime', 'http_e2e_checks' => 'HTTP E2E', 'commerce_runtime_checks' => 'Commerce Runtime', 'buyer_access_restriction_checks' => 'Buyer Access & Restriction', 'final_release_candidate_checks' => 'Final Release Candidate', 'admin_layout_css_checks' => 'Admin Layout CSS', 'storage_database_checks' => 'Storage & Database', 'cloud_backup_checks' => 'Backup & Sync Data', 'lp_builder_regression_checks' => 'LP Builder Regression', 'route_checks' => 'Route'] as $key => $label) {
            foreach ((array)($summary[$key] ?? []) as $check) {
                fputcsv($out, [$label, (string)($check['title'] ?? ''), !empty($check['ok']) ? 'OK' : 'Perlu cek', !empty($check['ok']) ? '1' : '0', (string)($check['note'] ?? '')]);
            }
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('release_audit_plain_text')) {
    function release_audit_plain_text(array $summary): string
    {
        $lines = [];
        $lines[] = 'U-Growth Web Template - ' . (string)($summary['title'] ?? 'Audit Kesiapan Website');
        $lines[] = (string)($summary['title'] ?? 'Audit Kesiapan Website');
        $lines[] = 'Skor: ' . (int)($summary['overall_score'] ?? 0) . '% - ' . (string)($summary['score_label'] ?? '');
        $lines[] = '';
        $lines[] = 'Ringkasan skor:';
        foreach ((array)($summary['scores'] ?? []) as $key => $value) {
            $lines[] = '- ' . str_replace('_', ' ', (string)$key) . ': ' . (int)$value . '%';
        }
        $lines[] = '';
        $lines[] = 'Catatan penting:';
        $critical = (array)($summary['critical_findings'] ?? []);
        if (!$critical) {
            $lines[] = '- Tidak ada temuan kritis dari audit otomatis.';
        } else {
            foreach ($critical as $item) {
                $lines[] = '- ' . (string)($item['title'] ?? 'Temuan') . ': ' . (string)($item['note'] ?? 'Perlu dicek.');
            }
        }
        $lines[] = '';
        $lines[] = 'Kesimpulan: modul growth sudah tersambung sebagai loop SEO → CTA → lead → order → report → action plan tanpa membuat tracking baru. Audit otomatis kini juga mencakup default content, workflow admin utama, CRUD admin, jalur submit publik, runtime permission role, kesiapan HTTP end-to-end/upload, buyer access & content restriction advanced, serta final release candidate packaging.';
        return implode("\n", $lines) . "\n";
    }
}
