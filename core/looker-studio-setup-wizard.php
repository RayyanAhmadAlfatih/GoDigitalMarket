<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('looker_studio_setup_wizard_steps')) {
    function looker_studio_setup_wizard_steps(): array
    {
        $settings = function_exists('cloud_backup_settings') ? cloud_backup_settings(true) : [];
        $report = function_exists('looker_studio_report') ? looker_studio_report() : [];
        $readiness = function_exists('looker_studio_visual_readiness') ? looker_studio_visual_readiness() : [];
        $apiUrl = function_exists('looker_studio_api_url') ? looker_studio_api_url() : url('api/looker-studio-data');
        $siteUrl = defined('SITE_URL') ? SITE_URL : url('');
        $isHttps = str_starts_with((string)$siteUrl, 'https://') || str_contains((string)$siteUrl, 'localhost') || str_contains((string)$siteUrl, '127.0.0.1');
        $tokenReady = !empty($report['token_ready']) || trim((string)($settings['looker_connector_token'] ?? '')) !== '';
        $enabled = !empty($report['enabled']);
        $connectorReady = !empty($report['connector_ready']);
        $readySources = (int)($readiness['ready_sources'] ?? 0);
        $totalSources = (int)($readiness['total_sources'] ?? 0);

        return [
            [
                'key' => 'site-url',
                'title' => 'Pastikan domain website siap HTTPS',
                'status' => $isHttps ? 'ready' : 'warning',
                'summary' => $isHttps ? 'URL website aman untuk dibaca Apps Script/Looker Studio.' : 'Gunakan HTTPS di domain production agar connector bisa membaca data dengan stabil.',
                'detail' => $siteUrl,
            ],
            [
                'key' => 'direct-toggle',
                'title' => 'Aktifkan koneksi langsung Looker Studio',
                'status' => $enabled ? 'ready' : 'todo',
                'summary' => $enabled ? 'Koneksi langsung sudah aktif.' : 'Centang opsi koneksi langsung di pengaturan Backup & Sync Data.',
                'detail' => 'Pengaturan: Aktifkan koneksi langsung Looker Studio',
            ],
            [
                'key' => 'token',
                'title' => 'Siapkan token connector',
                'status' => $tokenReady ? 'ready' : 'todo',
                'summary' => $tokenReady ? 'Token connector sudah tersedia.' : 'Isi token khusus Looker Studio. Token ini dipakai oleh Apps Script connector.',
                'detail' => 'Token tidak ditampilkan di source dan tidak dikirim ke sheet.',
            ],
            [
                'key' => 'api',
                'title' => 'Endpoint API Looker Studio tersedia',
                'status' => $apiUrl !== '' ? 'ready' : 'warning',
                'summary' => $apiUrl !== '' ? 'Endpoint data sudah bisa dipakai connector.' : 'Endpoint belum terbaca.',
                'detail' => $apiUrl,
            ],
            [
                'key' => 'connector-code',
                'title' => 'Kode Community Connector siap salin',
                'status' => $connectorReady ? 'ready' : 'warning',
                'summary' => $connectorReady ? 'Kode connector siap ditempel di Google Apps Script.' : 'Cek file connector dan fungsi Apps Script.',
                'detail' => 'Salin kode dari bagian Looker Studio Direct.',
            ],
            [
                'key' => 'sources',
                'title' => 'Source data visual siap dibaca',
                'status' => $readySources >= 10 ? 'ready' : 'warning',
                'summary' => $readySources . '/' . max(1, $totalSources) . ' source punya schema visual.',
                'detail' => 'Pilih source seperti orders, form_submissions, atau seo_profit_attribution untuk test pertama.',
            ],
            [
                'key' => 'looker-create-source',
                'title' => 'Buat data source di Looker Studio',
                'status' => 'manual',
                'summary' => 'Langkah ini dilakukan di Looker Studio setelah Apps Script connector dideploy.',
                'detail' => 'Pilih Community Connector, masukkan API URL dan token, lalu pilih source data.',
            ],
        ];
    }
}

if (!function_exists('looker_studio_setup_wizard_readiness')) {
    function looker_studio_setup_wizard_readiness(): array
    {
        $steps = looker_studio_setup_wizard_steps();
        $ready = count(array_filter($steps, static fn(array $step): bool => ($step['status'] ?? '') === 'ready'));
        $warnings = count(array_filter($steps, static fn(array $step): bool => in_array((string)($step['status'] ?? ''), ['warning', 'todo'], true)));
        $manual = count(array_filter($steps, static fn(array $step): bool => ($step['status'] ?? '') === 'manual'));
        $next = 'Buka Looker Studio dan buat data source dari Community Connector.';
        foreach ($steps as $step) {
            if (in_array((string)($step['status'] ?? ''), ['todo', 'warning'], true)) {
                $next = (string)($step['summary'] ?? $step['title'] ?? $next);
                break;
            }
        }

        return [
            'steps' => $steps,
            'ready' => $ready,
            'warnings' => $warnings,
            'manual' => $manual,
            'total' => count($steps),
            'score' => count($steps) > 0 ? (int)round(($ready / count($steps)) * 100) : 0,
            'next_action' => $next,
        ];
    }
}

if (!function_exists('looker_studio_setup_wizard_source_test')) {
    function looker_studio_setup_wizard_source_test(string $source = 'orders'): array
    {
        $source = preg_replace('/[^a-zA-Z0-9_]+/', '', trim($source)) ?: 'orders';
        if (!function_exists('looker_studio_source_meta') || !looker_studio_source_meta($source)) {
            return [
                'ok' => false,
                'source' => $source,
                'label' => $source,
                'message' => 'Sumber data tidak dikenali.',
                'records' => 0,
                'fields' => 0,
                'schema' => [],
                'rows' => [],
                'recommended_charts' => [],
            ];
        }

        $preview = function_exists('looker_studio_source_preview') ? looker_studio_source_preview($source, 5) : [];
        $schema = (array)($preview['schema'] ?? (function_exists('looker_studio_schema') ? looker_studio_schema($source) : []));
        $rows = (array)($preview['rows'] ?? []);
        $records = (int)($preview['records'] ?? count($rows));
        $fieldCount = count($schema);
        $ok = $fieldCount >= 3;

        return [
            'ok' => $ok,
            'source' => $source,
            'label' => (string)($preview['label'] ?? $source),
            'message' => $ok
                ? ($records > 0 ? 'Source siap dipakai. Data sudah terbaca.' : 'Schema siap. Data akan terisi setelah ada aktivitas website.')
                : 'Schema source belum cukup untuk divisualisasikan.',
            'records' => $records,
            'fields' => $fieldCount,
            'schema' => $schema,
            'rows' => $rows,
            'recommended_charts' => (array)($preview['recommended_charts'] ?? (function_exists('looker_studio_recommended_charts') ? looker_studio_recommended_charts($source) : [])),
        ];
    }
}

if (!function_exists('looker_studio_setup_wizard_test_urls')) {
    function looker_studio_setup_wizard_test_urls(string $source = 'orders'): array
    {
        $source = preg_replace('/[^a-zA-Z0-9_]+/', '', trim($source)) ?: 'orders';
        $apiUrl = function_exists('looker_studio_api_url') ? looker_studio_api_url() : url('api/looker-studio-data');
        $separator = str_contains($apiUrl, '?') ? '&' : '?';
        $tokenPlaceholder = 'TOKEN_ANDA';
        return [
            'status' => $apiUrl . $separator . 'action=status&token=' . $tokenPlaceholder,
            'sources' => $apiUrl . $separator . 'action=sources&token=' . $tokenPlaceholder,
            'schema' => $apiUrl . $separator . 'action=schema&source=' . rawurlencode($source) . '&token=' . $tokenPlaceholder,
            'preview' => $apiUrl . $separator . 'action=preview&source=' . rawurlencode($source) . '&limit=5&token=' . $tokenPlaceholder,
        ];
    }
}

if (!function_exists('looker_studio_setup_wizard_dashboard_checklist')) {
    function looker_studio_setup_wizard_dashboard_checklist(): array
    {
        return [
            'Owner Overview' => [
                'Gunakan source orders, form_submissions, dan profit_actions.',
                'Buat scorecard omzet, order masuk, lead baru, dan order belum bayar.',
                'Tambahkan grafik omzet harian dan lead vs order.',
            ],
            'Sales & Payment' => [
                'Gunakan source orders dan payment_proofs.',
                'Filter payment_status untuk melihat belum bayar, DP, dan lunas.',
                'Tambahkan tabel invoice/order yang perlu follow-up.',
            ],
            'SEO Profit' => [
                'Gunakan source seo_profit_attribution, seo_content_refresh, dan seo_money_pages.',
                'Urutkan halaman berdasarkan lead, order, dan omzet.',
                'Tampilkan action plan agar owner tahu prioritas eksekusi.',
            ],
            'CTA & Campaign' => [
                'Gunakan source offer_cta_tests, cta_results, dan seo_campaign_calendar.',
                'Bandingkan view, click, lead, conversion rate, dan status eksperimen.',
                'Tandai campaign yang mendekati deadline.',
            ],
            'Member & Digital Product' => [
                'Gunakan source member_access, buyer_accounts, dan orders.',
                'Tampilkan akses aktif, expired, produk digital terlaris, dan pembeli aktif.',
                'Tambahkan filter produk dan status akses.',
            ],
        ];
    }
}
