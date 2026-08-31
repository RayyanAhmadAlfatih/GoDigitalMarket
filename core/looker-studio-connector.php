<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('looker_studio_connector_dir')) {
    function looker_studio_connector_dir(): string
    {
        return ROOT_PATH . '/integrations/looker-studio';
    }
}

if (!function_exists('looker_studio_connector_file')) {
    function looker_studio_connector_file(): string
    {
        return looker_studio_connector_dir() . '/community-connector.js';
    }
}

if (!function_exists('looker_studio_connector_code')) {
    function looker_studio_connector_code(): string
    {
        $file = looker_studio_connector_file();
        return is_file($file) ? (string)file_get_contents($file) : '';
    }
}

if (!function_exists('looker_studio_connector_ready')) {
    function looker_studio_connector_ready(): bool
    {
        $code = looker_studio_connector_code();
        return $code !== ''
            && str_contains($code, 'DataStudioApp.createCommunityConnector')
            && str_contains($code, 'function getConfig')
            && str_contains($code, 'function getSchema')
            && str_contains($code, 'function getData')
            && str_contains($code, 'UrlFetchApp.fetch');
    }
}

if (!function_exists('looker_studio_connector_token')) {
    function looker_studio_connector_token(): string
    {
        $settings = function_exists('cloud_backup_settings') ? cloud_backup_settings(true) : [];
        $token = trim((string)($settings['looker_connector_token'] ?? ''));
        if ($token !== '') {
            return $token;
        }
        $envToken = trim((string)($_ENV['LOOKER_STUDIO_CONNECTOR_TOKEN'] ?? ''));
        if ($envToken !== '') {
            return $envToken;
        }
        return trim((string)($settings['apps_script_token'] ?? ($_ENV['CLOUD_SYNC_WEBHOOK_TOKEN'] ?? '')));
    }
}

if (!function_exists('looker_studio_connector_enabled')) {
    function looker_studio_connector_enabled(): bool
    {
        $settings = function_exists('cloud_backup_settings') ? cloud_backup_settings(true) : [];
        return !empty($settings['looker_direct_enabled']);
    }
}

if (!function_exists('looker_studio_api_url')) {
    function looker_studio_api_url(): string
    {
        return url('api/looker-studio-data');
    }
}

if (!function_exists('looker_studio_direct_sources')) {
    function looker_studio_direct_sources(): array
    {
        $sources = function_exists('cloud_backup_default_sources') ? cloud_backup_default_sources() : [];
        $sources['landing_page_analytics'] = [
            'label' => 'Landing Page Analytics',
            'sheet_name' => 'landing_page_analytics',
            'collection' => 'landing_page_analytics',
            'recommended' => true,
            'note' => 'Performa landing page, view, lead, CTA, dan conversion point.',
        ];
        $sources['offer_cta_tests'] = [
            'label' => 'Offer & CTA Testing',
            'sheet_name' => 'offer_cta_tests',
            'collection' => 'offer_cta_tests',
            'recommended' => true,
            'note' => 'Hasil eksperimen offer, CTA, status testing, dan catatan eksekusi.',
        ];
        $sources['cta_results'] = [
            'label' => 'CTA Result Tracker',
            'sheet_name' => 'cta_results',
            'collection' => 'cta_results',
            'recommended' => true,
            'note' => 'Tracking hasil CTA berdasarkan halaman, periode, dan conversion.',
        ];
        $sources['seo_profit_attribution'] = [
            'label' => 'SEO Profit Attribution',
            'sheet_name' => 'seo_profit_attribution',
            'collection' => 'seo_profit_attribution',
            'recommended' => true,
            'note' => 'Jembatan performa SEO ke lead, order, omzet, dan action plan.',
        ];
        $sources['profit_actions'] = [
            'label' => 'Profit Action Dashboard',
            'sheet_name' => 'profit_actions',
            'collection' => 'profit_actions',
            'recommended' => false,
            'note' => 'Catatan tindakan growth, PIC, status, dan prioritas owner.',
        ];
        $sources['seo_campaign_calendar'] = [
            'label' => 'SEO Campaign Calendar',
            'sheet_name' => 'seo_campaign_calendar',
            'collection' => 'seo_campaign_calendar',
            'recommended' => false,
            'note' => 'Sprint SEO, deadline, PIC, status, dan catatan campaign.',
        ];
        $sources['lead_quality_scores'] = [
            'label' => 'Lead Priority Scoring',
            'sheet_name' => 'lead_quality_scores',
            'collection' => 'lead_quality_scores',
            'recommended' => true,
            'note' => 'Skor kualitas lead, readiness follow-up, dan sumber prospek.',
        ];
        return $sources;
    }
}

if (!function_exists('looker_studio_source_meta')) {
    function looker_studio_source_meta(string $source): ?array
    {
        $sources = looker_studio_direct_sources();
        return $sources[$source] ?? null;
    }
}

if (!function_exists('looker_studio_field_type')) {
    function looker_studio_field_type(string $field, mixed $sample): string
    {
        $fieldLower = strtolower($field);
        if (str_contains($fieldLower, 'date') || str_contains($fieldLower, '_at') || str_contains($fieldLower, 'tanggal')) {
            return 'YEAR_MONTH_DAY';
        }
        if (is_int($sample)) {
            return 'NUMBER';
        }
        if (is_float($sample) || (is_string($sample) && preg_match('/^-?\d+\.\d+$/', trim($sample)))) {
            return 'NUMBER';
        }
        if (is_numeric($sample) && preg_match('/(total|amount|price|harga|omzet|revenue|score|count|qty|jumlah|lead|order|conversion|rate|nilai|biaya|ongkir|subtotal)/i', $field)) {
            return 'NUMBER';
        }
        return 'TEXT';
    }
}

if (!function_exists('looker_studio_field_label')) {
    function looker_studio_field_label(string $field): string
    {
        $label = str_replace(['_', '-'], ' ', $field);
        $label = preg_replace('/\s+/', ' ', trim($label)) ?: $field;
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        }
        return ucwords($label);
    }
}

if (!function_exists('looker_studio_infer_schema')) {
    function looker_studio_infer_schema(array $rows): array
    {
        $fields = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $key => $value) {
                $key = (string)$key;
                if ($key === '' || isset($fields[$key])) {
                    continue;
                }
                $fields[$key] = [
                    'name' => $key,
                    'label' => looker_studio_field_label($key),
                    'type' => looker_studio_field_type($key, $value),
                ];
            }
        }
        foreach (['_source', '_exported_at'] as $fallback) {
            if (!isset($fields[$fallback])) {
                $fields[$fallback] = [
                    'name' => $fallback,
                    'label' => looker_studio_field_label($fallback),
                    'type' => $fallback === '_exported_at' ? 'YEAR_MONTH_DAY' : 'TEXT',
                ];
            }
        }
        return array_values($fields);
    }
}


if (!function_exists('looker_studio_dashboard_sources')) {
    function looker_studio_dashboard_sources(): array
    {
        return [
            'orders',
            'form_submissions',
            'analytics_events',
            'landing_page_analytics',
            'offer_cta_tests',
            'cta_placements',
            'cta_results',
            'seo_profit_attribution',
            'profit_actions',
            'seo_campaign_calendar',
            'lead_quality_scores',
            'internal_link_cta',
            'seo_content_refresh',
            'seo_money_pages',
            'buyer_accounts',
            'member_access',
            'payment_proofs',
            'email_logs',
        ];
    }
}

if (!function_exists('looker_studio_curated_schemas')) {
    function looker_studio_curated_schemas(): array
    {
        $date = static fn(string $name, string $label): array => ['name' => $name, 'label' => $label, 'type' => 'YEAR_MONTH_DAY'];
        $text = static fn(string $name, string $label): array => ['name' => $name, 'label' => $label, 'type' => 'TEXT'];
        $num = static fn(string $name, string $label): array => ['name' => $name, 'label' => $label, 'type' => 'NUMBER'];
        $base = [$text('_source', 'Sumber Data'), $date('_exported_at', 'Waktu Export')];

        return [
            'orders' => array_merge([
                $date('date', 'Tanggal Order'), $text('order_ref', 'Nomor Order'), $text('customer_name', 'Nama Pembeli'),
                $text('phone', 'WhatsApp'), $text('email', 'Email'), $text('product_name', 'Produk'), $text('source', 'Sumber Lead'),
                $text('campaign', 'Campaign'), $text('order_status', 'Status Order'), $text('payment_status', 'Status Pembayaran'),
                $num('subtotal', 'Subtotal'), $num('shipping_cost', 'Ongkir'), $num('total', 'Total Order'),
                $num('paid_amount', 'Nominal Dibayar'), $num('pending_amount', 'Sisa Tagihan'), $num('conversion_value', 'Nilai Konversi'),
                $text('payment_channel', 'Metode Pembayaran'), $text('city', 'Kota'), $text('province', 'Provinsi'),
            ], $base),
            'form_submissions' => array_merge([
                $date('date', 'Tanggal Lead'), $text('lead_name', 'Nama Lead'), $text('phone', 'WhatsApp'), $text('email', 'Email'),
                $text('form_name', 'Nama Form'), $text('page_url', 'Halaman Asal'), $text('source', 'Sumber Lead'), $text('campaign', 'Campaign'),
                $text('lead_status', 'Status Lead'), $num('lead_score', 'Skor Lead'), $text('followup_status', 'Status Follow-up'),
                $text('needs', 'Kebutuhan'), $text('message', 'Pesan'),
            ], $base),
            'analytics_events' => array_merge([
                $date('date', 'Tanggal Event'), $text('event_name', 'Nama Event'), $text('page_url', 'Halaman'), $text('source', 'Source'),
                $text('medium', 'Medium'), $text('campaign', 'Campaign'), $text('content', 'Content'), $text('visitor_id', 'Visitor ID'),
                $text('lead_id', 'Lead ID'), $text('order_ref', 'Nomor Order'), $num('value', 'Nilai Event'),
            ], $base),
            'landing_page_analytics' => array_merge([
                $text('page_title', 'Judul Landing Page'), $text('page_url', 'URL Landing Page'), $num('views', 'View'),
                $num('cta_clicks', 'Klik CTA'), $num('leads', 'Lead'), $num('orders', 'Order'), $num('revenue', 'Omzet'),
                $num('conversion_rate', 'Conversion Rate'), $text('best_cta', 'CTA Terbaik'), $text('action_plan', 'Action Plan'),
            ], $base),
            'offer_cta_tests' => array_merge([
                $text('variant_name', 'Nama Variant'), $text('status', 'Status Testing'), $text('page_url', 'Halaman'), $text('offer', 'Offer'),
                $text('cta_text', 'Teks CTA'), $num('views', 'View'), $num('clicks', 'Klik'), $num('leads', 'Lead'),
                $num('orders', 'Order'), $num('conversion_rate', 'Conversion Rate'), $text('notes', 'Catatan'),
            ], $base),
            'cta_placements' => array_merge([
                $text('placement', 'Penempatan CTA'), $text('page_title', 'Judul Halaman'), $text('page_url', 'URL Halaman'),
                $text('cta_text', 'Teks CTA'), $text('priority', 'Prioritas'), $text('status', 'Status'), $text('note', 'Catatan'),
            ], $base),
            'cta_results' => array_merge([
                $text('placement', 'Penempatan CTA'), $text('page_url', 'Halaman'), $text('period', 'Periode'), $num('views', 'View'),
                $num('clicks', 'Klik'), $num('leads', 'Lead'), $num('orders', 'Order'), $num('revenue', 'Omzet'),
                $num('conversion_rate', 'Conversion Rate'), $text('winner', 'Pemenang'),
            ], $base),
            'seo_profit_attribution' => array_merge([
                $text('page_title', 'Judul Halaman'), $text('page_url', 'URL Halaman'), $text('keyword', 'Keyword'), $num('traffic', 'Traffic'),
                $num('leads', 'Lead'), $num('orders', 'Order'), $num('revenue', 'Omzet'), $num('conversion_rate', 'Conversion Rate'),
                $text('priority', 'Prioritas'), $text('action_plan', 'Action Plan'),
            ], $base),
            'profit_actions' => array_merge([
                $text('action_title', 'Judul Action'), $text('priority', 'Prioritas'), $text('status', 'Status'), $text('pic', 'PIC'),
                $date('due_date', 'Deadline'), $num('impact_score', 'Skor Dampak'), $text('note', 'Catatan'),
            ], $base),
            'seo_campaign_calendar' => array_merge([
                $text('task_title', 'Judul Tugas'), $text('campaign', 'Campaign'), $text('status', 'Status'), $text('pic', 'PIC'),
                $date('deadline', 'Deadline'), $text('priority', 'Prioritas'), $text('note', 'Catatan'),
            ], $base),
            'lead_quality_scores' => array_merge([
                $date('date', 'Tanggal Lead'), $text('lead_name', 'Nama Lead'), $text('source', 'Sumber Lead'), $num('lead_score', 'Skor Lead'),
                $text('temperature', 'Suhu Lead'), $text('followup_status', 'Status Follow-up'), $text('recommended_action', 'Rekomendasi Aksi'),
            ], $base),
            'internal_link_cta' => array_merge([
                $text('page_title', 'Judul Halaman'), $text('page_url', 'URL Sumber'), $text('target_url', 'URL Tujuan'),
                $text('cta_text', 'Teks CTA'), $text('priority', 'Prioritas'), $text('status', 'Status'), $text('note', 'Catatan'),
            ], $base),
            'seo_content_refresh' => array_merge([
                $text('page_title', 'Judul Konten'), $text('page_url', 'URL Konten'), $date('last_refresh', 'Terakhir Refresh'),
                $text('next_action', 'Aksi Berikutnya'), $text('priority', 'Prioritas'), $text('status', 'Status'), $text('note', 'Catatan'),
            ], $base),
            'seo_money_pages' => array_merge([
                $text('page_title', 'Judul Money Page'), $text('page_url', 'URL Money Page'), $text('intent', 'Intent'),
                $text('status', 'Status'), $text('priority', 'Prioritas'), $num('conversion_score', 'Skor Konversi'), $text('note', 'Catatan'),
            ], $base),
            'buyer_accounts' => array_merge([
                $date('date', 'Tanggal Dibuat'), $text('buyer_name', 'Nama Pembeli'), $text('email', 'Email'), $text('phone', 'WhatsApp'),
                $num('total_orders', 'Total Order'), $num('total_spent', 'Total Belanja'), $text('status', 'Status Member'),
            ], $base),
            'member_access' => array_merge([
                $date('date', 'Tanggal Akses'), $text('buyer_name', 'Nama Pembeli'), $text('product_name', 'Produk'), $text('order_ref', 'Nomor Order'),
                $text('access_status', 'Status Akses'), $date('expires_at', 'Masa Akses'), $text('license_key', 'License Key'),
            ], $base),
            'payment_proofs' => array_merge([
                $date('date', 'Tanggal Upload'), $text('order_ref', 'Nomor Order'), $text('buyer_name', 'Nama Pembeli'), $num('amount', 'Nominal'),
                $text('payment_status', 'Status Pembayaran'), $text('proof_status', 'Status Bukti'), $text('payment_channel', 'Metode Pembayaran'),
            ], $base),
            'email_logs' => array_merge([
                $date('date', 'Tanggal Email'), $text('recipient', 'Penerima'), $text('subject', 'Subjek'), $text('template', 'Template'),
                $text('status', 'Status'), $text('module', 'Modul'),
            ], $base),
        ];
    }
}

if (!function_exists('looker_studio_curated_schema')) {
    function looker_studio_curated_schema(string $source): array
    {
        $schemas = looker_studio_curated_schemas();
        return $schemas[$source] ?? [];
    }
}

if (!function_exists('looker_studio_pick_value')) {
    function looker_studio_pick_value(array $row, array $keys, mixed $default = ''): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== '' && $row[$key] !== null) {
                return $row[$key];
            }
        }
        return $default;
    }
}

if (!function_exists('looker_studio_num')) {
    function looker_studio_num(mixed $value): float|int
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $value = preg_replace('/[^0-9\-\.]+/', '', (string)$value);
        if ($value === '' || $value === '-' || $value === '.') {
            return 0;
        }
        $number = (float)$value;
        return floor($number) === $number ? (int)$number : $number;
    }
}

if (!function_exists('looker_studio_percent')) {
    function looker_studio_percent(mixed $value): float
    {
        $number = (float)looker_studio_num($value);
        if ($number > 0 && $number <= 1) {
            return round($number * 100, 2);
        }
        return round($number, 2);
    }
}

if (!function_exists('looker_studio_date_value')) {
    function looker_studio_date_value(mixed $value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^\d{8}$/', $raw)) {
            return $raw;
        }
        $time = strtotime($raw);
        return $time ? gmdate('Y-m-d', $time) : '';
    }
}

if (!function_exists('looker_studio_text_value')) {
    function looker_studio_text_value(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }
        return trim((string)$value);
    }
}

if (!function_exists('looker_studio_apply_curated_schema')) {
    function looker_studio_apply_curated_schema(string $source, array $row): array
    {
        $schema = looker_studio_curated_schema($source);
        if (!$schema) {
            return $row;
        }

        $sourceMap = [
            'orders' => [
                'date' => ['created_at', 'order_date', 'date', 'tanggal', 'submitted_at'], 'order_ref' => ['ref', 'order_ref', 'invoice_ref', 'order_id', 'id'],
                'customer_name' => ['customer_name', 'buyer_name', 'name', 'nama', 'billing_name'], 'phone' => ['phone', 'whatsapp', 'wa', 'billing_phone'],
                'email' => ['email', 'billing_email'], 'product_name' => ['product_name', 'product', 'produk', 'item_name'], 'source' => ['source', 'lead_source', 'utm_source'],
                'campaign' => ['campaign', 'utm_campaign'], 'order_status' => ['order_status', 'status'], 'payment_status' => ['payment_status', 'status_pembayaran'],
                'subtotal' => ['subtotal'], 'shipping_cost' => ['shipping_cost', 'shipping', 'ongkir'], 'total' => ['total', 'grand_total', 'amount'],
                'paid_amount' => ['paid_amount', 'amount_paid', 'dp_amount'], 'pending_amount' => ['pending_amount', 'remaining_amount'], 'conversion_value' => ['conversion_value', 'total', 'grand_total'],
                'payment_channel' => ['payment_channel', 'payment_method', 'metode_pembayaran'], 'city' => ['city', 'kota'], 'province' => ['province', 'provinsi'],
            ],
            'form_submissions' => [
                'date' => ['created_at', 'submitted_at', 'date', 'tanggal'], 'lead_name' => ['lead_name', 'name', 'nama'], 'phone' => ['phone', 'whatsapp', 'wa'],
                'email' => ['email'], 'form_name' => ['form_name', 'form_title', 'source_form'], 'page_url' => ['page_url', 'source_url', 'url'],
                'source' => ['source', 'utm_source'], 'campaign' => ['campaign', 'utm_campaign'], 'lead_status' => ['lead_status', 'status'], 'lead_score' => ['lead_score', 'score'],
                'followup_status' => ['followup_status', 'follow_up_status'], 'needs' => ['needs', 'kebutuhan'], 'message' => ['message', 'pesan', 'notes'],
            ],
            'analytics_events' => [
                'date' => ['created_at', 'event_date', 'date', 'timestamp'], 'event_name' => ['event_name', 'event', 'name'], 'page_url' => ['page_url', 'url', 'path'],
                'source' => ['source', 'utm_source'], 'medium' => ['medium', 'utm_medium'], 'campaign' => ['campaign', 'utm_campaign'], 'content' => ['content', 'utm_content'],
                'visitor_id' => ['visitor_id', 'session_id'], 'lead_id' => ['lead_id'], 'order_ref' => ['order_ref', 'ref'], 'value' => ['value', 'amount', 'total'],
            ],
        ];

        $genericMap = [
            'date' => ['date', 'created_at', 'updated_at', 'tanggal'], 'page_title' => ['page_title', 'title', 'judul'], 'page_url' => ['page_url', 'url', 'path', 'slug'],
            'views' => ['views', 'view', 'impressions'], 'clicks' => ['clicks', 'click', 'cta_clicks'], 'cta_clicks' => ['cta_clicks', 'clicks'],
            'leads' => ['leads', 'lead_count'], 'orders' => ['orders', 'order_count'], 'revenue' => ['revenue', 'omzet', 'sales', 'total'],
            'conversion_rate' => ['conversion_rate', 'cvr', 'rate'], 'status' => ['status'], 'priority' => ['priority', 'prioritas'], 'pic' => ['pic', 'owner'],
            'note' => ['note', 'notes', 'catatan'], 'notes' => ['notes', 'note', 'catatan'], 'action_plan' => ['action_plan', 'next_action', 'recommendation'],
            'deadline' => ['deadline', 'due_date'], 'due_date' => ['due_date', 'deadline'], 'lead_score' => ['lead_score', 'score'],
            'buyer_name' => ['buyer_name', 'customer_name', 'name', 'nama'], 'product_name' => ['product_name', 'product', 'produk'], 'order_ref' => ['order_ref', 'ref', 'order_id'],
            'payment_status' => ['payment_status', 'status_pembayaran'], 'email' => ['email'], 'phone' => ['phone', 'whatsapp', 'wa'], 'subject' => ['subject'],
        ];

        $map = array_merge($genericMap, $sourceMap[$source] ?? []);
        $normalized = [];
        foreach ($schema as $field) {
            $name = (string)($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $keys = $map[$name] ?? [$name];
            $value = looker_studio_pick_value($row, array_unique(array_merge([$name], $keys)), '');
            $type = (string)($field['type'] ?? 'TEXT');
            if ($type === 'NUMBER') {
                $normalized[$name] = ($name === 'conversion_rate') ? looker_studio_percent($value) : looker_studio_num($value);
            } elseif ($type === 'YEAR_MONTH_DAY') {
                $normalized[$name] = looker_studio_date_value($value);
            } else {
                $normalized[$name] = looker_studio_text_value($value);
            }
        }
        if (($normalized['pending_amount'] ?? 0) === 0 && isset($normalized['total'], $normalized['paid_amount'])) {
            $normalized['pending_amount'] = max(0, (float)$normalized['total'] - (float)$normalized['paid_amount']);
        }
        if (($normalized['conversion_rate'] ?? 0) === 0 && isset($normalized['views'])) {
            $base = (float)$normalized['views'];
            $goal = (float)($normalized['orders'] ?? $normalized['leads'] ?? 0);
            $normalized['conversion_rate'] = $base > 0 ? round(($goal / $base) * 100, 2) : 0;
        }
        return $normalized;
    }
}

if (!function_exists('looker_studio_rows')) {
    function looker_studio_rows(string $source, int $limit = 1000, array $requestedFields = []): array
    {
        $meta = looker_studio_source_meta($source);
        if (!$meta) {
            return [];
        }
        if (function_exists('cloud_backup_rows')) {
            $rows = cloud_backup_rows($source, $limit);
        } else {
            $rows = [];
        }
        if (!$rows && function_exists('looker_studio_custom_source_records')) {
            $rows = [];
            foreach (looker_studio_custom_source_records($source) as $record) {
                if (is_array($record)) {
                    $flat = function_exists('cloud_backup_flatten_record') ? cloud_backup_flatten_record($record) : $record;
                    $flat['_source'] = $source;
                    $flat['_exported_at'] = date(DATE_ATOM);
                    $rows[] = $flat;
                }
            }
        }
        $rows = array_values(array_filter($rows, 'is_array'));
        $rows = array_map(static function (array $row) use ($source): array {
            return looker_studio_apply_curated_schema($source, $row);
        }, $rows);
        if ($requestedFields) {
            $allowed = array_fill_keys($requestedFields, true);
            $rows = array_map(static function (array $row) use ($allowed): array {
                return array_intersect_key($row, $allowed);
            }, $rows);
        }
        return array_slice($rows, 0, max(1, $limit));
    }
}

if (!function_exists('looker_studio_schema')) {
    function looker_studio_schema(string $source, int $limit = 250): array
    {
        $curated = looker_studio_curated_schema($source);
        if ($curated) {
            return $curated;
        }
        $rows = looker_studio_rows($source, $limit);
        return looker_studio_infer_schema($rows);
    }
}

if (!function_exists('looker_studio_record_count')) {
    function looker_studio_record_count(string $source, int $limit = 250): int
    {
        $meta = looker_studio_source_meta($source);
        if (!$meta) {
            return 0;
        }
        $collection = (string)($meta['collection'] ?? $source);
        try {
            if (function_exists('storage_adapter_collection_records')) {
                $records = storage_adapter_collection_records($collection);
                if (is_array($records) && $records) {
                    return min(count($records), $limit);
                }
            }
            if ($collection === 'analytics_events' && is_file(STORAGE_PATH . '/lead-events.jsonl') && function_exists('storage_adapter_read_jsonl_records')) {
                return min(count(storage_adapter_read_jsonl_records('lead-events.jsonl')), $limit);
            }
        } catch (Throwable) {
            return 0;
        }
        return 0;
    }
}

if (!function_exists('looker_studio_source_preview')) {
    function looker_studio_source_preview(string $source, int $limit = 5): array
    {
        $meta = looker_studio_source_meta($source);
        if (!$meta) {
            return ['ok' => false, 'message' => 'Sumber data tidak dikenali.', 'source' => $source, 'schema' => [], 'rows' => [], 'records' => 0];
        }
        $rows = looker_studio_rows($source, max(1, $limit));
        $schema = looker_studio_schema($source);
        return [
            'ok' => true,
            'source' => $source,
            'label' => (string)($meta['label'] ?? $source),
            'records' => max(count($rows), looker_studio_record_count($source)),
            'schema' => $schema,
            'rows' => array_slice($rows, 0, max(1, $limit)),
            'recommended_charts' => looker_studio_recommended_charts($source),
        ];
    }
}

if (!function_exists('looker_studio_source_health')) {
    function looker_studio_source_health(string $source): array
    {
        $meta = looker_studio_source_meta($source);
        $schema = looker_studio_schema($source);
        $records = looker_studio_record_count($source);
        return [
            'source' => $source,
            'label' => (string)($meta['label'] ?? $source),
            'ok' => $meta !== null && count($schema) >= 3,
            'records' => $records,
            'fields' => count($schema),
            'status' => $records > 0 ? 'Siap divisualisasikan' : 'Schema siap, data masih kosong',
        ];
    }
}

if (!function_exists('looker_studio_recommended_charts')) {
    function looker_studio_recommended_charts(string $source): array
    {
        $map = [
            'orders' => ['Omzet harian', 'Order berdasarkan status pembayaran', 'Produk terlaris', 'Order belum bayar'],
            'form_submissions' => ['Lead harian', 'Lead berdasarkan sumber', 'Lead score tertinggi', 'Status follow-up'],
            'analytics_events' => ['Event harian', 'Channel campaign', 'Halaman paling aktif'],
            'landing_page_analytics' => ['Landing page dengan conversion rate tertinggi', 'View vs lead', 'Revenue per landing page'],
            'offer_cta_tests' => ['Performa variant CTA', 'Status eksperimen', 'Winner candidate'],
            'cta_results' => ['Klik CTA vs lead', 'Conversion rate per placement', 'Revenue dari CTA'],
            'seo_profit_attribution' => ['Halaman SEO paling menghasilkan omzet', 'Traffic vs lead', 'Prioritas action plan'],
            'lead_quality_scores' => ['Lead panas', 'Lead score per sumber', 'Follow-up priority'],
            'member_access' => ['Akses produk aktif', 'Akses hampir expired', 'Produk digital terlaris'],
        ];
        return $map[$source] ?? ['Jumlah record', 'Status data', 'Prioritas aksi'];
    }
}

if (!function_exists('looker_studio_dashboard_blueprints')) {
    function looker_studio_dashboard_blueprints(): array
    {
        return [
            [
                'key' => 'owner_overview',
                'title' => 'Owner Overview',
                'goal' => 'Melihat kondisi bisnis secara cepat: lead, order, omzet, conversion, dan pekerjaan prioritas.',
                'sources' => ['orders', 'form_submissions', 'seo_profit_attribution', 'profit_actions'],
                'cards' => ['Total omzet', 'Order masuk', 'Lead baru', 'Order belum bayar', 'Action plan prioritas'],
                'charts' => ['Omzet harian', 'Lead vs order', 'Top produk / halaman', 'Status pembayaran'],
            ],
            [
                'key' => 'lead_crm',
                'title' => 'Lead & CRM Dashboard',
                'goal' => 'Memprioritaskan lead yang paling siap difollow-up agar tim tidak salah fokus.',
                'sources' => ['form_submissions', 'lead_quality_scores', 'analytics_events'],
                'cards' => ['Lead baru', 'Lead panas', 'Belum follow-up', 'Sumber lead terbaik'],
                'charts' => ['Lead harian', 'Lead berdasarkan sumber', 'Lead score', 'Follow-up status'],
            ],
            [
                'key' => 'sales_payment',
                'title' => 'Sales & Payment Dashboard',
                'goal' => 'Memantau order, pembayaran, invoice tertunda, dan omzet yang sudah aman.',
                'sources' => ['orders', 'payment_proofs', 'buyer_accounts'],
                'cards' => ['Order masuk', 'Lunas', 'Belum bayar', 'Bukti bayar menunggu cek'],
                'charts' => ['Omzet harian', 'Payment status', 'Produk terlaris', 'Kota pembeli'],
            ],
            [
                'key' => 'seo_profit',
                'title' => 'SEO Profit Dashboard',
                'goal' => 'Membaca halaman SEO mana yang menghasilkan lead/order dan perlu dioptimasi.',
                'sources' => ['seo_profit_attribution', 'seo_content_refresh', 'seo_money_pages', 'internal_link_cta'],
                'cards' => ['Traffic', 'Lead dari SEO', 'Order dari SEO', 'Money page prioritas'],
                'charts' => ['Traffic vs lead', 'Revenue per halaman', 'Prioritas refresh', 'Internal link queue'],
            ],
            [
                'key' => 'campaign_cta',
                'title' => 'CTA & Campaign Dashboard',
                'goal' => 'Melihat eksperimen offer, CTA, dan campaign yang paling menghasilkan konversi.',
                'sources' => ['offer_cta_tests', 'cta_placements', 'cta_results', 'seo_campaign_calendar'],
                'cards' => ['CTA diuji', 'CTA pemenang', 'Campaign aktif', 'Deadline dekat'],
                'charts' => ['View vs click', 'Click vs lead', 'Conversion rate per CTA', 'Campaign status'],
            ],
            [
                'key' => 'member_access',
                'title' => 'Member & Digital Product Dashboard',
                'goal' => 'Memantau akses produk digital, pembeli aktif, dan akses yang akan berakhir.',
                'sources' => ['member_access', 'buyer_accounts', 'orders'],
                'cards' => ['Member aktif', 'Akses aktif', 'Akses expired', 'Produk digital terlaris'],
                'charts' => ['Akses per produk', 'Member baru', 'Akses berdasarkan status'],
            ],
        ];
    }
}

if (!function_exists('looker_studio_visual_readiness')) {
    function looker_studio_visual_readiness(): array
    {
        $health = [];
        foreach (looker_studio_dashboard_sources() as $source) {
            $health[] = looker_studio_source_health($source);
        }
        $ready = count(array_filter($health, static fn(array $row): bool => !empty($row['ok'])));
        return [
            'ready_sources' => $ready,
            'total_sources' => count($health),
            'health' => $health,
            'blueprints' => looker_studio_dashboard_blueprints(),
        ];
    }
}

if (!function_exists('looker_studio_custom_source_records')) {
    function looker_studio_custom_source_records(string $source): array
    {
        try {
            return match ($source) {
                'landing_page_analytics' => function_exists('landing_page_analytics_report')
                    ? (function_exists('landing_page_analytics_csv_rows') ? landing_page_analytics_csv_rows(landing_page_analytics_report(30, [])) : [(array)landing_page_analytics_report(30, [])])
                    : [],
                'offer_cta_tests' => function_exists('offer_cta_lab_settings')
                    ? array_values((array)(offer_cta_lab_settings(true)['variants'] ?? []))
                    : [],
                'cta_placements' => function_exists('cta_placement_settings')
                    ? array_values((array)(cta_placement_settings(true)['deployments'] ?? []))
                    : [],
                'cta_results' => function_exists('cta_result_bridge_summary')
                    ? array_values((array)(cta_result_bridge_summary(30)['deployment_results'] ?? []))
                    : [],
                'seo_profit_attribution' => function_exists('seo_profit_summary')
                    ? array_values((array)(seo_profit_summary(30, 'all')['results'] ?? []))
                    : [],
                'profit_actions' => function_exists('profit_action_dashboard_summary')
                    ? array_values((array)(profit_action_dashboard_summary(30, [])['actions'] ?? []))
                    : [],
                'seo_campaign_calendar' => function_exists('growth_sprint_summary')
                    ? array_values((array)(growth_sprint_summary(14, 30, 'balanced', 'open')['all_tasks'] ?? []))
                    : [],
                'lead_quality_scores' => function_exists('lead_quality_summary')
                    ? array_values((array)(lead_quality_summary(30, 'all', 'all', 'open')['items'] ?? []))
                    : [],
                'internal_link_cta' => function_exists('link_cta_summary')
                    ? array_values((array)(link_cta_summary(30, 'all', 'all', 'open')['items'] ?? link_cta_summary(30, 'all', 'all', 'open')['queue'] ?? []))
                    : [],
                'seo_content_refresh' => function_exists('seo_refresh_summary')
                    ? array_values((array)(seo_refresh_summary(90, 'all', 'all', 'all', 'open')['items'] ?? seo_refresh_summary(90, 'all', 'all', 'all', 'open')['queue'] ?? []))
                    : [],
                'seo_money_pages' => function_exists('seo_money_summary')
                    ? array_values((array)(seo_money_summary(30, 'all', 'all')['items'] ?? seo_money_summary(30, 'all', 'all')['results'] ?? []))
                    : [],
                default => [],
            };
        } catch (Throwable) {
            return [];
        }
    }
}

if (!function_exists('looker_studio_sources_payload')) {
    function looker_studio_sources_payload(): array
    {
        $result = [];
        foreach (looker_studio_direct_sources() as $key => $source) {
            $recordCount = looker_studio_record_count((string)$key);
            $result[] = [
                'key' => (string)$key,
                'label' => (string)($source['label'] ?? $key),
                'sheet_name' => (string)($source['sheet_name'] ?? $key),
                'recommended' => !empty($source['recommended']),
                'records' => $recordCount,
                'fields' => count(looker_studio_schema((string)$key)),
                'status' => $recordCount > 0 ? 'Siap divisualisasikan' : 'Schema siap, data masih kosong',
                'recommended_charts' => looker_studio_recommended_charts((string)$key),
                'note' => (string)($source['note'] ?? ''),
            ];
        }
        return $result;
    }
}

if (!function_exists('looker_studio_report')) {
    function looker_studio_report(): array
    {
        return [
            'enabled' => looker_studio_connector_enabled(),
            'token_ready' => looker_studio_connector_token() !== '',
            'api_url' => looker_studio_api_url(),
            'connector_ready' => looker_studio_connector_ready(),
            'sources' => looker_studio_sources_payload(),
            'total_sources' => count(looker_studio_direct_sources()),
            'visual_readiness' => looker_studio_visual_readiness(),
            'dashboard_blueprints' => looker_studio_dashboard_blueprints(),
        ];
    }
}
