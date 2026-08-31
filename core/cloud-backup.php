<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('cloud_backup_connector_dir')) {
    function cloud_backup_connector_dir(): string
    {
        return ROOT_PATH . '/integrations/google-sheets';
    }
}

if (!function_exists('cloud_backup_apps_script_file')) {
    function cloud_backup_apps_script_file(): string
    {
        return cloud_backup_connector_dir() . '/apps-script-connector.js';
    }
}

if (!function_exists('cloud_backup_apps_script_code')) {
    function cloud_backup_apps_script_code(): string
    {
        $file = cloud_backup_apps_script_file();
        if (is_file($file)) {
            return (string)file_get_contents($file);
        }
        return '';
    }
}

if (!function_exists('cloud_backup_apps_script_ready')) {
    function cloud_backup_apps_script_ready(): bool
    {
        $code = cloud_backup_apps_script_code();
        return $code !== ''
            && str_contains($code, 'function doPost')
            && str_contains($code, 'SpreadsheetApp')
            && str_contains($code, 'setupUGrowthSheets')
            && str_contains($code, 'SYNC_TOKEN');
    }
}

if (!function_exists('cloud_backup_setup_steps')) {
    function cloud_backup_setup_steps(): array
    {
        return [
            'Buat Google Spreadsheet kosong untuk dashboard U-Growth.',
            'Buka Extensions → Apps Script, hapus isi awal, lalu tempel kode connector dari halaman ini.',
            'Di Apps Script, jalankan setupUGrowthSheets sekali untuk membuat tab standar.',
            'Deploy sebagai Web App dengan akses Anyone with the link, lalu salin URL Web App ke pengaturan U-Growth.',
            'Isi token yang sama di Script Properties dan di pengaturan U-Growth, lalu coba tombol Kirim ke Cloud.',
        ];
    }
}

if (!function_exists('cloud_backup_settings_file')) {
    function cloud_backup_settings_file(): string
    {
        return STORAGE_PATH . '/cloud-backup-settings.json';
    }
}

if (!function_exists('cloud_backup_export_dir')) {
    function cloud_backup_export_dir(): string
    {
        return STORAGE_PATH . '/exports/cloud-sync';
    }
}

if (!function_exists('cloud_backup_valid_frequency')) {
    function cloud_backup_valid_frequency(string $frequency): string
    {
        $frequency = strtolower(trim($frequency));
        return in_array($frequency, ['manual', 'hourly', 'daily', 'weekly'], true) ? $frequency : 'manual';
    }
}


if (!function_exists('cloud_backup_valid_sync_mode')) {
    function cloud_backup_valid_sync_mode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['replace', 'append'], true) ? $mode : 'replace';
    }
}

if (!function_exists('cloud_backup_int_range')) {
    function cloud_backup_int_range(mixed $value, int $default, int $min, int $max): int
    {
        $number = is_numeric($value) ? (int)$value : $default;
        return max($min, min($max, $number));
    }
}

if (!function_exists('cloud_backup_bool')) {
    function cloud_backup_bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }
        return $default;
    }
}

if (!function_exists('cloud_backup_default_sources')) {
    function cloud_backup_default_sources(): array
    {
        return [
            'form_submissions' => [
                'label' => 'Lead / Data Masuk Form',
                'sheet_name' => 'leads',
                'collection' => 'form_submissions',
                'recommended' => true,
                'note' => 'Data prospek dari form custom dan lead magnet.',
            ],
            'orders' => [
                'label' => 'Order / Checkout',
                'sheet_name' => 'orders',
                'collection' => 'orders',
                'recommended' => true,
                'note' => 'Data transaksi, status bayar, total, dan kontak pembeli.',
            ],
            'analytics_events' => [
                'label' => 'Analytics / Event Tracking',
                'sheet_name' => 'analytics_events',
                'collection' => 'analytics_events',
                'recommended' => true,
                'note' => 'Event lead, klik WA, campaign, dan aktivitas konversi.',
            ],
            'payment_proofs' => [
                'label' => 'Bukti Pembayaran',
                'sheet_name' => 'payment_proofs',
                'collection' => 'payment_proofs',
                'recommended' => false,
                'note' => 'Metadata bukti bayar. File upload asli tetap berada di storage privat.',
            ],
            'buyer_accounts' => [
                'label' => 'Pembeli / Member',
                'sheet_name' => 'customers',
                'collection' => 'buyer_accounts',
                'recommended' => false,
                'note' => 'Akun pembeli dan data kontak member.',
            ],
            'member_access' => [
                'label' => 'Akses Produk Digital',
                'sheet_name' => 'member_access',
                'collection' => 'member_access',
                'recommended' => false,
                'note' => 'Daftar akses produk digital/course yang aktif.',
            ],
            'email_logs' => [
                'label' => 'Riwayat Email',
                'sheet_name' => 'email_logs',
                'collection' => 'email_logs',
                'recommended' => false,
                'note' => 'Log pengiriman email sistem.',
            ],
            'landing_page_analytics' => [
                'label' => 'Landing Page Analytics',
                'sheet_name' => 'landing_page_analytics',
                'collection' => 'landing_page_analytics',
                'recommended' => true,
                'note' => 'Performa landing page untuk dashboard visual owner.',
            ],
            'offer_cta_tests' => [
                'label' => 'Offer & CTA Testing',
                'sheet_name' => 'offer_cta_tests',
                'collection' => 'offer_cta_tests',
                'recommended' => true,
                'note' => 'Eksperimen offer dan CTA yang sedang diuji atau siap dipakai.',
            ],
            'cta_placements' => [
                'label' => 'CTA Placement',
                'sheet_name' => 'cta_placements',
                'collection' => 'cta_placements',
                'recommended' => false,
                'note' => 'Rencana penempatan CTA di halaman/artikel/landing page.',
            ],
            'cta_results' => [
                'label' => 'CTA Result Tracker',
                'sheet_name' => 'cta_results',
                'collection' => 'cta_results',
                'recommended' => true,
                'note' => 'Hasil performa CTA berdasarkan klik, lead, dan order.',
            ],
            'seo_profit_attribution' => [
                'label' => 'SEO Profit Attribution',
                'sheet_name' => 'seo_profit_attribution',
                'collection' => 'seo_profit_attribution',
                'recommended' => true,
                'note' => 'Kontribusi halaman SEO ke lead, order, dan peluang revenue.',
            ],
            'profit_actions' => [
                'label' => 'Profit Action Dashboard',
                'sheet_name' => 'profit_actions',
                'collection' => 'profit_actions',
                'recommended' => false,
                'note' => 'Action plan growth, prioritas, status, dan PIC.',
            ],
            'seo_campaign_calendar' => [
                'label' => 'SEO Campaign Calendar',
                'sheet_name' => 'seo_campaign_calendar',
                'collection' => 'seo_campaign_calendar',
                'recommended' => false,
                'note' => 'Sprint SEO, deadline, PIC, dan status campaign.',
            ],
            'lead_quality_scores' => [
                'label' => 'Lead Priority Scoring',
                'sheet_name' => 'lead_quality_scores',
                'collection' => 'lead_quality_scores',
                'recommended' => true,
                'note' => 'Skor kualitas lead dan prioritas follow-up.',
            ],
            'internal_link_cta' => [
                'label' => 'Internal Link & CTA Injection',
                'sheet_name' => 'internal_link_cta',
                'collection' => 'internal_link_cta',
                'recommended' => false,
                'note' => 'Queue internal link dan CTA untuk memperkuat conversion path.',
            ],
            'seo_content_refresh' => [
                'label' => 'SEO Content Refresh',
                'sheet_name' => 'seo_content_refresh',
                'collection' => 'seo_content_refresh',
                'recommended' => false,
                'note' => 'Rencana refresh konten SEO dan prioritas per halaman.',
            ],
            'seo_money_pages' => [
                'label' => 'SEO Money Page Optimizer',
                'sheet_name' => 'seo_money_pages',
                'collection' => 'seo_money_pages',
                'recommended' => false,
                'note' => 'Optimasi money page berdasarkan intent, CTA, trust, dan conversion.',
            ],
        ];
    }
}

if (!function_exists('cloud_backup_default_settings')) {
    function cloud_backup_default_settings(): array
    {
        $sources = [];
        foreach (cloud_backup_default_sources() as $key => $source) {
            $sources[$key] = [
                'enabled' => !empty($source['recommended']),
                'sheet_name' => (string)($source['sheet_name'] ?? $key),
                'last_export_at' => '',
                'last_export_rows' => 0,
                'last_sync_at' => '',
                'last_sync_rows' => 0,
                'last_status' => '',
                'last_error' => '',
                'last_response_status' => '',
                'last_payload_hash' => '',
            ];
        }

        return [
            'schema' => 'cloud-backup-settings',
            'enabled' => false,
            'frequency' => 'manual',
            'destination_google_sheets' => true,
            'destination_google_drive' => false,
            'apps_script_url' => (string)($_ENV['CLOUD_SYNC_WEBHOOK_URL'] ?? ''),
            'apps_script_token' => (string)($_ENV['CLOUD_SYNC_WEBHOOK_TOKEN'] ?? ''),
            'spreadsheet_id' => (string)($_ENV['GOOGLE_SPREADSHEET_ID'] ?? ''),
            'drive_folder_id' => (string)($_ENV['GOOGLE_DRIVE_FOLDER_ID'] ?? ''),
            'looker_studio_ready' => true,
            'looker_direct_enabled' => false,
            'looker_connector_token' => (string)($_ENV['LOOKER_STUDIO_CONNECTOR_TOKEN'] ?? ''),
            'sync_mode' => (string)($_ENV['CLOUD_SYNC_MODE'] ?? 'replace'),
            'max_sync_rows' => (int)($_ENV['CLOUD_SYNC_MAX_ROWS'] ?? 1000),
            'retry_attempts' => (int)($_ENV['CLOUD_SYNC_RETRY_ATTEMPTS'] ?? 1),
            'sync_timeout_seconds' => (int)($_ENV['CLOUD_SYNC_TIMEOUT_SECONDS'] ?? 15),
            'sync_log_enabled' => true,
            'sources' => $sources,
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('cloud_backup_normalize_settings')) {
    function cloud_backup_normalize_settings(array $settings): array
    {
        $defaults = cloud_backup_default_settings();
        $settings['schema'] = 'cloud-backup-settings';
        $settings['enabled'] = cloud_backup_bool($settings['enabled'] ?? $defaults['enabled'], false);
        $settings['frequency'] = cloud_backup_valid_frequency((string)($settings['frequency'] ?? $defaults['frequency']));
        $settings['destination_google_sheets'] = cloud_backup_bool($settings['destination_google_sheets'] ?? true, true);
        $settings['destination_google_drive'] = cloud_backup_bool($settings['destination_google_drive'] ?? false, false);
        $settings['apps_script_url'] = trim((string)($settings['apps_script_url'] ?? $defaults['apps_script_url']));
        $settings['apps_script_token'] = trim((string)($settings['apps_script_token'] ?? $defaults['apps_script_token']));
        $settings['spreadsheet_id'] = trim((string)($settings['spreadsheet_id'] ?? $defaults['spreadsheet_id']));
        $settings['drive_folder_id'] = trim((string)($settings['drive_folder_id'] ?? $defaults['drive_folder_id']));
        $settings['looker_studio_ready'] = cloud_backup_bool($settings['looker_studio_ready'] ?? true, true);
        $settings['looker_direct_enabled'] = cloud_backup_bool($settings['looker_direct_enabled'] ?? false, false);
        $settings['looker_connector_token'] = trim((string)($settings['looker_connector_token'] ?? $defaults['looker_connector_token'] ?? ''));
        $settings['sync_mode'] = cloud_backup_valid_sync_mode((string)($settings['sync_mode'] ?? $defaults['sync_mode'] ?? 'replace'));
        $settings['max_sync_rows'] = cloud_backup_int_range($settings['max_sync_rows'] ?? $defaults['max_sync_rows'] ?? 1000, 1000, 1, 10000);
        $settings['retry_attempts'] = cloud_backup_int_range($settings['retry_attempts'] ?? $defaults['retry_attempts'] ?? 1, 1, 0, 3);
        $settings['sync_timeout_seconds'] = cloud_backup_int_range($settings['sync_timeout_seconds'] ?? $defaults['sync_timeout_seconds'] ?? 15, 15, 5, 60);
        $settings['sync_log_enabled'] = cloud_backup_bool($settings['sync_log_enabled'] ?? true, true);
        $settings['sources'] = is_array($settings['sources'] ?? null) ? $settings['sources'] : [];

        foreach (cloud_backup_default_sources() as $key => $source) {
            $current = is_array($settings['sources'][$key] ?? null) ? $settings['sources'][$key] : [];
            $sheetName = trim((string)($current['sheet_name'] ?? $source['sheet_name'] ?? $key));
            $sheetName = preg_replace('/[^a-zA-Z0-9_ -]+/', '', $sheetName) ?: (string)($source['sheet_name'] ?? $key);
            $settings['sources'][$key] = [
                'enabled' => cloud_backup_bool($current['enabled'] ?? !empty($source['recommended']), !empty($source['recommended'])),
                'sheet_name' => substr($sheetName, 0, 80),
                'last_export_at' => trim((string)($current['last_export_at'] ?? '')),
                'last_export_rows' => max(0, (int)($current['last_export_rows'] ?? 0)),
                'last_sync_at' => trim((string)($current['last_sync_at'] ?? '')),
                'last_sync_rows' => max(0, (int)($current['last_sync_rows'] ?? 0)),
                'last_status' => trim((string)($current['last_status'] ?? '')),
                'last_error' => substr(trim((string)($current['last_error'] ?? '')), 0, 240),
                'last_response_status' => substr(trim((string)($current['last_response_status'] ?? '')), 0, 120),
                'last_payload_hash' => substr(trim((string)($current['last_payload_hash'] ?? '')), 0, 80),
            ];
        }
        $settings['updated_at'] = trim((string)($settings['updated_at'] ?? $defaults['updated_at']));
        return $settings;
    }
}

if (!function_exists('cloud_backup_settings')) {
    function cloud_backup_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }
        $file = cloud_backup_settings_file();
        if (!is_file($file)) {
            return $cached = cloud_backup_default_settings();
        }
        $decoded = json_decode((string)@file_get_contents($file), true);
        return $cached = cloud_backup_normalize_settings(is_array($decoded) ? $decoded : []);
    }
}

if (!function_exists('cloud_backup_write_settings')) {
    function cloud_backup_write_settings(array $settings): bool
    {
        $settings = cloud_backup_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);
        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            throw new RuntimeException('Folder storage belum bisa dibuat.');
        }
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || @file_put_contents(cloud_backup_settings_file(), $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Pengaturan backup cloud belum bisa disimpan. Cek permission storage.');
        }
        @chmod(cloud_backup_settings_file(), 0644);
        cloud_backup_settings(true);
        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'cloud-backup', null, 'Menyimpan pengaturan backup dan sinkronisasi data.', ['frequency' => $settings['frequency']]);
        }
        return true;
    }
}

if (!function_exists('cloud_backup_save_from_post')) {
    function cloud_backup_save_from_post(array $post): bool
    {
        $settings = cloud_backup_settings(true);
        $settings['enabled'] = !empty($post['enabled']);
        $settings['frequency'] = cloud_backup_valid_frequency((string)($post['frequency'] ?? 'manual'));
        $settings['destination_google_sheets'] = !empty($post['destination_google_sheets']);
        $settings['destination_google_drive'] = !empty($post['destination_google_drive']);
        $settings['apps_script_url'] = trim((string)($post['apps_script_url'] ?? ''));
        $settings['apps_script_token'] = trim((string)($post['apps_script_token'] ?? ''));
        $settings['spreadsheet_id'] = trim((string)($post['spreadsheet_id'] ?? ''));
        $settings['drive_folder_id'] = trim((string)($post['drive_folder_id'] ?? ''));
        $settings['looker_direct_enabled'] = !empty($post['looker_direct_enabled']);
        $settings['looker_connector_token'] = trim((string)($post['looker_connector_token'] ?? ''));
        $settings['sync_mode'] = cloud_backup_valid_sync_mode((string)($post['sync_mode'] ?? 'replace'));
        $settings['max_sync_rows'] = cloud_backup_int_range($post['max_sync_rows'] ?? 1000, 1000, 1, 10000);
        $settings['retry_attempts'] = cloud_backup_int_range($post['retry_attempts'] ?? 1, 1, 0, 3);
        $settings['sync_timeout_seconds'] = cloud_backup_int_range($post['sync_timeout_seconds'] ?? 15, 15, 5, 60);
        $settings['sync_log_enabled'] = !empty($post['sync_log_enabled']);
        $enabledSources = is_array($post['sources'] ?? null) ? array_map('strval', (array)$post['sources']) : [];
        $sheetNames = is_array($post['sheet_names'] ?? null) ? (array)$post['sheet_names'] : [];
        foreach (cloud_backup_default_sources() as $key => $source) {
            $settings['sources'][$key]['enabled'] = in_array($key, $enabledSources, true);
            $settings['sources'][$key]['sheet_name'] = trim((string)($sheetNames[$key] ?? $source['sheet_name'] ?? $key));
        }
        return cloud_backup_write_settings($settings);
    }
}

if (!function_exists('cloud_backup_mask')) {
    function cloud_backup_mask(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Belum diisi';
        }
        if (strlen($value) <= 12) {
            return str_repeat('•', max(4, strlen($value)));
        }
        return substr($value, 0, 6) . '••••••' . substr($value, -4);
    }
}


if (!function_exists('cloud_backup_sync_log_file')) {
    function cloud_backup_sync_log_file(): string
    {
        return LOGS_PATH . '/cloud-sync.jsonl';
    }
}

if (!function_exists('cloud_backup_payload_hash')) {
    function cloud_backup_payload_hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: serialize($payload));
    }
}

if (!function_exists('cloud_backup_sensitive_key')) {
    function cloud_backup_sensitive_key(string $key): bool
    {
        return (bool)preg_match('/(password|pass|token|secret|credential|apikey|api_key|authorization|csrf|session|cookie|private_key|smtp_password|proof_file|file_path|absolute_path|tmp_name)/i', $key);
    }
}

if (!function_exists('cloud_backup_redact_value')) {
    function cloud_backup_redact_value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return '[redacted]';
    }
}

if (!function_exists('cloud_backup_safe_scalar')) {
    function cloud_backup_safe_scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value) || $value === null) {
            $text = (string)$value;
        } else {
            $text = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }
        if (defined('ROOT_PATH') && ROOT_PATH !== '') {
            $text = str_replace(str_replace('\\', '/', ROOT_PATH), '[root]', str_replace('\\', '/', $text));
        }
        return function_exists('mb_substr') ? mb_substr($text, 0, 5000) : substr($text, 0, 5000);
    }
}

if (!function_exists('cloud_backup_append_sync_log')) {
    function cloud_backup_append_sync_log(array $entry): bool
    {
        $settings = cloud_backup_settings(true);
        if (empty($settings['sync_log_enabled'])) {
            return false;
        }
        $entry['logged_at'] = $entry['logged_at'] ?? date(DATE_ATOM);
        $entry = array_diff_key($entry, array_flip(['apps_script_token', 'looker_connector_token', 'token']));
        if (!is_dir(LOGS_PATH) && !mkdir(LOGS_PATH, 0775, true) && !is_dir(LOGS_PATH)) {
            return false;
        }
        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return false;
        }
        return @file_put_contents(cloud_backup_sync_log_file(), $line . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }
}

if (!function_exists('cloud_backup_sync_history')) {
    function cloud_backup_sync_history(int $limit = 20): array
    {
        $file = cloud_backup_sync_log_file();
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_slice($lines, -max(1, min(200, $limit)));
        $rows = [];
        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode((string)$line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
        return $rows;
    }
}

if (!function_exists('cloud_backup_source_records')) {
    function cloud_backup_source_records(string $source, int $limit = 1000): array
    {
        $sources = cloud_backup_default_sources();
        if (!isset($sources[$source])) {
            return [];
        }
        $collection = (string)($sources[$source]['collection'] ?? $source);
        $records = [];
        if (function_exists('storage_adapter_collection_records')) {
            $records = storage_adapter_collection_records($collection);
        }
        if (!$records && $collection === 'analytics_events' && is_file(STORAGE_PATH . '/lead-events.jsonl') && function_exists('storage_adapter_read_jsonl_records')) {
            $records = storage_adapter_read_jsonl_records('lead-events.jsonl');
        }
        if (!$records && function_exists('looker_studio_custom_source_records')) {
            $records = looker_studio_custom_source_records($source);
        }
        $records = array_values(array_filter($records, 'is_array'));
        return array_slice($records, 0, max(1, min(10000, $limit)));
    }
}

if (!function_exists('cloud_backup_flatten_record')) {
    function cloud_backup_flatten_record(array $record, string $prefix = ''): array
    {
        $flat = [];
        foreach ($record as $key => $value) {
            $safeKey = preg_replace('/[^a-zA-Z0-9_]+/', '_', trim((string)$key));
            $name = $prefix === '' ? $safeKey : $prefix . '_' . $safeKey;
            if (cloud_backup_sensitive_key($name)) {
                $flat[$name] = cloud_backup_redact_value($value);
                continue;
            }
            if (is_array($value)) {
                $isList = array_keys($value) === range(0, count($value) - 1);
                if ($isList) {
                    $flat[$name] = implode(', ', array_map(static fn($v): string => is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $value));
                } else {
                    foreach (cloud_backup_flatten_record($value, $name) as $childKey => $childValue) {
                        $flat[$childKey] = $childValue;
                    }
                }
                continue;
            }
            $flat[$name] = cloud_backup_safe_scalar($value);
        }
        return $flat;
    }
}

if (!function_exists('cloud_backup_rows')) {
    function cloud_backup_rows(string $source, int $limit = 1000): array
    {
        $records = cloud_backup_source_records($source, $limit);
        $rows = [];
        foreach ($records as $record) {
            $flat = cloud_backup_flatten_record($record);
            $flat['_source'] = $source;
            $flat['_exported_at'] = date(DATE_ATOM);
            $flat['_sync_row_hash'] = substr(cloud_backup_payload_hash($flat), 0, 16);
            $rows[] = $flat;
        }
        return $rows;
    }
}

if (!function_exists('cloud_backup_csv_escape')) {
    function cloud_backup_csv_escape(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}

if (!function_exists('cloud_backup_build_csv')) {
    function cloud_backup_build_csv(array $rows): string
    {
        if (!$rows) {
            return '';
        }
        $headers = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                if (!in_array($key, $headers, true)) {
                    $headers[] = $key;
                }
            }
        }
        $lines = [implode(',', array_map('cloud_backup_csv_escape', $headers))];
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = cloud_backup_csv_escape((string)($row[$header] ?? ''));
            }
            $lines[] = implode(',', $line);
        }
        return implode("\n", $lines) . "\n";
    }
}

if (!function_exists('cloud_backup_export_source')) {
    function cloud_backup_export_source(string $source, string $format = 'csv', int $limit = 1000): array
    {
        $sources = cloud_backup_default_sources();
        if (!isset($sources[$source])) {
            throw new RuntimeException('Sumber data tidak dikenali.');
        }
        $format = strtolower(trim($format));
        $format = in_array($format, ['csv', 'json'], true) ? $format : 'csv';
        $rows = cloud_backup_rows($source, $limit);
        $dir = cloud_backup_export_dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Folder export cloud sync belum bisa dibuat.');
        }
        $safe = preg_replace('/[^a-z0-9_.-]+/i', '-', $source);
        $file = $dir . '/' . $safe . '-' . date('Ymd-His') . '.' . $format;
        $content = $format === 'json'
            ? (json_encode(['source' => $source, 'exported_at' => date(DATE_ATOM), 'rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') . PHP_EOL
            : cloud_backup_build_csv($rows);
        if (@file_put_contents($file, $content, LOCK_EX) === false) {
            throw new RuntimeException('File export belum bisa dibuat.');
        }
        @chmod($file, 0644);
        $settings = cloud_backup_settings(true);
        $settings['sources'][$source]['last_export_at'] = date(DATE_ATOM);
        $settings['sources'][$source]['last_export_rows'] = count($rows);
        $settings['sources'][$source]['last_status'] = 'exported';
        $settings['sources'][$source]['last_error'] = '';
        cloud_backup_write_settings($settings);
        return [
            'ok' => true,
            'source' => $source,
            'format' => $format,
            'rows' => count($rows),
            'file' => $file,
            'basename' => basename($file),
        ];
    }
}

if (!function_exists('cloud_backup_send_payload')) {
    function cloud_backup_send_payload(array $payload, array $settings): array
    {
        $url = trim((string)($settings['apps_script_url'] ?? ''));
        if ($url === '') {
            return ['ok' => false, 'message' => 'Apps Script URL belum diisi. Export lokal tetap tersedia.'];
        }
        $parts = @parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $allowedHosts = ['script.google.com', 'script.googleusercontent.com'];

        if (
            !is_array($parts)
            || $scheme !== 'https'
            || !in_array($host, $allowedHosts, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
        ) {
            return ['ok' => false, 'message' => 'URL Apps Script harus HTTPS dan memakai host Google Apps Script resmi.'];
        }
        $token = trim((string)($settings['apps_script_token'] ?? ''));
        if ($token !== '') {
            $payload['auth'] = ['token' => $token];
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['ok' => false, 'message' => 'Payload sync tidak valid.'];
        }
        $headers = "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: U-Growth-CloudSync/1.0\r\n";
        if ($token !== '') {
            $headers .= 'X-Ugrowth-Token: ' . str_replace(["\r", "\n"], '', $token) . "\r\n";
        }
        $attempts = cloud_backup_int_range($settings['retry_attempts'] ?? 1, 1, 0, 3) + 1;
        $timeout = cloud_backup_int_range($settings['sync_timeout_seconds'] ?? 15, 15, 5, 60);
        $last = ['ok' => false, 'message' => 'Sync belum dijalankan.', 'status' => '', 'response_preview' => '', 'attempts' => 0];

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => $body,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            $statusLine = is_array($http_response_header ?? null) ? (string)($http_response_header[0] ?? '') : '';
            $ok = str_contains($statusLine, ' 200 ') || str_contains($statusLine, ' 201 ') || str_contains($statusLine, ' 202 ');
            $decoded = json_decode((string)$response, true);
            if ($ok && is_array($decoded) && array_key_exists('ok', $decoded)) {
                $ok = !empty($decoded['ok']);
            }
            $last = [
                'ok' => $ok,
                'message' => $ok ? 'Payload berhasil dikirim ke endpoint cloud.' : ('Endpoint belum mengembalikan status sukses. ' . trim($statusLine)),
                'status' => $statusLine,
                'response_preview' => substr((string)$response, 0, 500),
                'attempts' => $attempt,
            ];
            if ($ok) {
                break;
            }
            if ($attempt < $attempts) {
                usleep(150000 * $attempt);
            }
        }

        return $last;
    }
}

if (!function_exists('cloud_backup_sync_source')) {
    function cloud_backup_sync_source(string $source, int $limit = 1000): array
    {
        $settings = cloud_backup_settings(true);
        $sources = cloud_backup_default_sources();
        if (!isset($sources[$source])) {
            throw new RuntimeException('Sumber data tidak dikenali.');
        }
        $limit = min(max(1, $limit), (int)($settings['max_sync_rows'] ?? 1000));
        $rows = cloud_backup_rows($source, $limit);
        $rowHash = cloud_backup_payload_hash($rows);
        $payloadId = $source . '-' . date('Ymd-His') . '-' . substr($rowHash, 0, 8);
        $payload = [
            'app' => 'U-Growth Web Template',
            'schema_version' => 'ugrowth-cloud-sync-v2',
            'payload_id' => $payloadId,
            'source' => $source,
            'sheet_name' => (string)($settings['sources'][$source]['sheet_name'] ?? $sources[$source]['sheet_name'] ?? $source),
            'spreadsheet_id' => (string)($settings['spreadsheet_id'] ?? ''),
            'drive_folder_id' => (string)($settings['drive_folder_id'] ?? ''),
            'destinations' => [
                'google_sheets' => !empty($settings['destination_google_sheets']),
                'google_drive' => !empty($settings['destination_google_drive']),
            ],
            'exported_at' => date(DATE_ATOM),
            'mode' => cloud_backup_valid_sync_mode((string)($settings['sync_mode'] ?? 'replace')),
            'row_count' => count($rows),
            'rows_checksum' => $rowHash,
            'rows' => $rows,
        ];
        $send = cloud_backup_send_payload($payload, $settings);
        $settings['sources'][$source]['last_sync_at'] = date(DATE_ATOM);
        $settings['sources'][$source]['last_sync_rows'] = count($rows);
        $settings['sources'][$source]['last_status'] = !empty($send['ok']) ? 'synced' : 'sync_failed';
        $settings['sources'][$source]['last_error'] = !empty($send['ok']) ? '' : substr((string)($send['message'] ?? 'Sync gagal.'), 0, 240);
        $settings['sources'][$source]['last_response_status'] = substr((string)($send['status'] ?? ''), 0, 120);
        $settings['sources'][$source]['last_payload_hash'] = substr($rowHash, 0, 80);
        cloud_backup_write_settings($settings);
        cloud_backup_append_sync_log([
            'event' => 'sync_source',
            'source' => $source,
            'payload_id' => $payloadId,
            'rows' => count($rows),
            'payload_hash' => substr($rowHash, 0, 16),
            'ok' => !empty($send['ok']),
            'status' => (string)($send['status'] ?? ''),
            'attempts' => (int)($send['attempts'] ?? 1),
            'message' => (string)($send['message'] ?? ''),
        ]);
        return array_merge($send, ['source' => $source, 'rows' => count($rows), 'payload_id' => $payloadId, 'payload_hash' => $rowHash]);
    }
}

if (!function_exists('cloud_backup_source_rows')) {
    function cloud_backup_source_rows(): array
    {
        $settings = cloud_backup_settings(true);
        $rows = [];
        foreach (cloud_backup_default_sources() as $key => $source) {
            $records = cloud_backup_source_records($key, 10000);
            $rows[] = [
                'key' => $key,
                'label' => (string)($source['label'] ?? $key),
                'sheet_name' => (string)($settings['sources'][$key]['sheet_name'] ?? $source['sheet_name'] ?? $key),
                'enabled' => !empty($settings['sources'][$key]['enabled']),
                'recommended' => !empty($source['recommended']),
                'records' => count($records),
                'note' => (string)($source['note'] ?? ''),
                'last_export_at' => (string)($settings['sources'][$key]['last_export_at'] ?? ''),
                'last_export_rows' => (int)($settings['sources'][$key]['last_export_rows'] ?? 0),
                'last_sync_at' => (string)($settings['sources'][$key]['last_sync_at'] ?? ''),
                'last_sync_rows' => (int)($settings['sources'][$key]['last_sync_rows'] ?? 0),
                'last_status' => (string)($settings['sources'][$key]['last_status'] ?? ''),
                'last_error' => (string)($settings['sources'][$key]['last_error'] ?? ''),
                'last_response_status' => (string)($settings['sources'][$key]['last_response_status'] ?? ''),
                'last_payload_hash' => (string)($settings['sources'][$key]['last_payload_hash'] ?? ''),
            ];
        }
        return $rows;
    }
}

if (!function_exists('cloud_backup_source_health')) {
    function cloud_backup_source_health(array $row): array
    {
        $enabled = !empty($row['enabled']);
        $records = (int)($row['records'] ?? 0);
        $lastStatus = (string)($row['last_status'] ?? '');
        $lastError = (string)($row['last_error'] ?? '');
        $status = 'info';
        $message = 'Sumber data belum aktif atau belum perlu sync.';
        if ($enabled && $records === 0) {
            $status = 'info';
            $message = 'Aktif, tetapi data masih kosong. Ini normal pada paket bersih.';
        }
        if ($enabled && $records > 0 && $lastStatus === '') {
            $status = 'warning';
            $message = 'Ada data, tetapi belum pernah export atau sync.';
        }
        if ($enabled && in_array($lastStatus, ['exported', 'synced'], true)) {
            $status = 'ok';
            $message = $lastStatus === 'synced' ? 'Data terakhir berhasil disinkronkan.' : 'Export lokal terakhir berhasil dibuat.';
        }
        if ($lastStatus === 'sync_failed') {
            $status = 'error';
            $message = $lastError !== '' ? $lastError : 'Sync terakhir gagal.';
        }
        return [
            'source' => (string)($row['key'] ?? ''),
            'label' => (string)($row['label'] ?? ''),
            'status' => $status,
            'message' => $message,
            'records' => $records,
            'last_status' => $lastStatus,
            'last_error' => $lastError,
        ];
    }
}

if (!function_exists('cloud_backup_health_report')) {
    function cloud_backup_health_report(): array
    {
        $settings = cloud_backup_settings(true);
        $rows = cloud_backup_source_rows();
        $checks = [];
        $checks[] = [
            'key' => 'apps_script_url',
            'label' => 'Endpoint Apps Script',
            'status' => trim((string)($settings['apps_script_url'] ?? '')) !== '' ? 'ok' : 'info',
            'message' => trim((string)($settings['apps_script_url'] ?? '')) !== '' ? 'URL endpoint sudah diisi.' : 'Endpoint belum diisi. Export lokal tetap bisa dipakai.',
        ];
        $checks[] = [
            'key' => 'token',
            'label' => 'Token Sync',
            'status' => trim((string)($settings['apps_script_token'] ?? '')) !== '' ? 'ok' : 'warning',
            'message' => trim((string)($settings['apps_script_token'] ?? '')) !== '' ? 'Token sync sudah diisi.' : 'Token sync belum diisi. Tambahkan token sebelum sync ke endpoint publik.',
        ];
        $checks[] = [
            'key' => 'apps_script_template',
            'label' => 'Template Apps Script',
            'status' => cloud_backup_apps_script_ready() ? 'ok' : 'warning',
            'message' => cloud_backup_apps_script_ready() ? 'Kode Apps Script siap dipakai.' : 'Kode Apps Script belum lengkap.',
        ];
        $checks[] = [
            'key' => 'sync_log',
            'label' => 'Log Sinkronisasi',
            'status' => !empty($settings['sync_log_enabled']) ? 'ok' : 'info',
            'message' => !empty($settings['sync_log_enabled']) ? 'Riwayat sync akan dicatat di log privat.' : 'Log sync dinonaktifkan.',
        ];
        foreach ($rows as $row) {
            $health = cloud_backup_source_health($row);
            $checks[] = [
                'key' => 'source_' . (string)($row['key'] ?? ''),
                'label' => (string)($row['label'] ?? ''),
                'status' => $health['status'],
                'message' => $health['message'],
                'meta' => ['records' => (int)($row['records'] ?? 0), 'last_status' => (string)($row['last_status'] ?? '')],
            ];
        }
        $counts = ['ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0];
        foreach ($checks as $check) {
            $status = (string)($check['status'] ?? 'info');
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        $score = (int)round(((($counts['ok'] ?? 0) + (($counts['info'] ?? 0) * 0.5)) / max(1, count($checks))) * 100);
        return [
            'score' => $score,
            'overall' => ($counts['error'] ?? 0) > 0 ? 'error' : ((($counts['warning'] ?? 0) > 0) ? 'warning' : 'ok'),
            'counts' => $counts,
            'checks' => $checks,
            'history' => cloud_backup_sync_history(10),
        ];
    }
}

if (!function_exists('cloud_backup_export_all_enabled')) {
    function cloud_backup_export_all_enabled(string $format = 'json'): array
    {
        $settings = cloud_backup_settings(true);
        $results = [];
        foreach (cloud_backup_default_sources() as $key => $_source) {
            if (empty($settings['sources'][$key]['enabled'])) {
                continue;
            }
            $results[] = cloud_backup_export_source((string)$key, $format, (int)($settings['max_sync_rows'] ?? 1000));
        }
        cloud_backup_append_sync_log([
            'event' => 'export_all_enabled',
            'format' => $format,
            'sources' => count($results),
            'rows' => array_sum(array_map(static fn(array $row): int => (int)($row['rows'] ?? 0), $results)),
            'ok' => true,
        ]);
        return [
            'ok' => true,
            'sources' => count($results),
            'rows' => array_sum(array_map(static fn(array $row): int => (int)($row['rows'] ?? 0), $results)),
            'results' => $results,
        ];
    }
}

if (!function_exists('cloud_backup_report')) {
    function cloud_backup_report(): array
    {
        $settings = cloud_backup_settings(true);
        $rows = cloud_backup_source_rows();
        $health = cloud_backup_health_report();
        return [
            'enabled' => !empty($settings['enabled']),
            'frequency' => (string)($settings['frequency'] ?? 'manual'),
            'sync_mode' => (string)($settings['sync_mode'] ?? 'replace'),
            'max_sync_rows' => (int)($settings['max_sync_rows'] ?? 1000),
            'retry_attempts' => (int)($settings['retry_attempts'] ?? 1),
            'sync_timeout_seconds' => (int)($settings['sync_timeout_seconds'] ?? 15),
            'sync_health_score' => (int)($health['score'] ?? 0),
            'sync_health' => $health,
            'sheets_enabled' => !empty($settings['destination_google_sheets']),
            'drive_enabled' => !empty($settings['destination_google_drive']),
            'endpoint_ready' => trim((string)($settings['apps_script_url'] ?? '')) !== '',
            'token_ready' => trim((string)($settings['apps_script_token'] ?? '')) !== '',
            'spreadsheet_ready' => trim((string)($settings['spreadsheet_id'] ?? '')) !== '',
            'drive_folder_ready' => trim((string)($settings['drive_folder_id'] ?? '')) !== '',
            'apps_script_template_ready' => cloud_backup_apps_script_ready(),
            'looker_direct_enabled' => !empty($settings['looker_direct_enabled']),
            'looker_direct_token_ready' => trim((string)($settings['looker_connector_token'] ?? '')) !== '' || trim((string)($_ENV['LOOKER_STUDIO_CONNECTOR_TOKEN'] ?? '')) !== '',
            'looker_direct_connector_ready' => function_exists('looker_studio_connector_ready') && looker_studio_connector_ready(),
            'looker_direct_api_url' => function_exists('looker_studio_api_url') ? looker_studio_api_url() : '',
            'sources' => $rows,
            'total_records' => array_sum(array_map(static fn(array $row): int => (int)($row['records'] ?? 0), $rows)),
            'updated_at' => (string)($settings['updated_at'] ?? ''),
        ];
    }
}
