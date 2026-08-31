<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| Template ADMIN ACTIVITY LOG ENGINE
|--------------------------------------------------------------------------
| Audit trail ringan untuk panel admin. Default memakai JSONL agar aman
| untuk hosting umum dan tidak memaksa database aktif.
|--------------------------------------------------------------------------
*/

if (!function_exists('activity_log_enabled')) {
    function activity_log_enabled(): bool
    {
        $value = strtolower(trim((string)($_ENV['ENABLE_ADMIN_ACTIVITY_LOG'] ?? 'true')));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('activity_log_path')) {
    function activity_log_path(): string
    {
        return STORAGE_PATH . '/admin-activity.log';
    }
}

if (!function_exists('activity_log_directory_ready')) {
    function activity_log_directory_ready(): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }

        return is_dir(STORAGE_PATH) && is_writable(STORAGE_PATH);
    }
}

if (!function_exists('activity_log_sensitive_key')) {
    function activity_log_sensitive_key(string $key): bool
    {
        $key = strtolower($key);
        foreach (['password', 'passwd', 'pass', 'token', 'secret', 'csrf', '_token', 'cookie', 'authorization', 'smtp_password', 'db_pass'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('activity_log_sanitize_context')) {
    function activity_log_sanitize_context(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 4) {
            return '[depth-limit]';
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $keyString = is_string($key) ? $key : (string)$key;
                if (activity_log_sensitive_key($keyString)) {
                    $clean[$keyString] = '[redacted]';
                    continue;
                }
                $clean[$keyString] = activity_log_sanitize_context($item, $depth + 1);
            }
            return $clean;
        }

        if (is_object($value)) {
            return activity_log_sanitize_context((array)$value, $depth + 1);
        }

        if (is_string($value)) {
            $value = trim($value);
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($length > 260) {
                return (function_exists('mb_substr') ? mb_substr($value, 0, 260) : substr($value, 0, 260)) . '…';
            }
            return $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return (string)$value;
    }
}

if (!function_exists('activity_log_request_ip')) {
    function activity_log_request_ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') {
                continue;
            }
            if (str_contains($candidate, ',')) {
                $candidate = trim(explode(',', $candidate)[0]);
            }
            return $candidate;
        }

        return 'unknown';
    }
}

if (!function_exists('activity_log_current_path')) {
    function activity_log_current_path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '/';
    }
}

if (!function_exists('activity_log_storage_mode')) {
    function activity_log_storage_mode(): string
    {
        if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('activity_logs')) {
            return 'mysql+jsonl';
        }
        return function_exists('db_available') && db_available() ? 'mysql-ready/jsonl' : 'jsonl';
    }
}

if (!function_exists('activity_log_admin_state')) {
    function activity_log_admin_state(): array
    {
        return [
            'logged_in' => !empty($_SESSION['admin_articles_logged_in']),
            'session_id' => session_id() ? substr(hash('sha256', session_id()), 0, 12) : '',
        ];
    }
}

if (!function_exists('activity_log_record')) {
    function activity_log_record(
        string $action,
        string $entity = 'system',
        int|string|null $entityId = null,
        string $message = '',
        array $context = []
    ): bool {
        if (!activity_log_enabled() || !activity_log_directory_ready()) {
            return false;
        }

        try {
            $id = bin2hex(random_bytes(8));
        } catch (Throwable $e) {
            $id = uniqid('log_', true);
        }

        $record = [
            'id' => $id,
            'time' => date('Y-m-d H:i:s'),
            'iso_time' => date(DATE_ATOM),
            'action' => trim($action) ?: 'unknown',
            'entity' => trim($entity) ?: 'system',
            'entity_id' => $entityId,
            'message' => trim($message),
            'context' => activity_log_sanitize_context($context),
            'admin' => activity_log_admin_state(),
            'request' => [
                'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
                'path' => activity_log_current_path(),
                'ip' => activity_log_request_ip(),
                'user_agent' => function_exists('mb_substr') ? mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 220) : substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI'), 0, 220),
            ],
            'storage' => activity_log_storage_mode(),
        ];

        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return false;
        }

        $mysqlOk = false;
        $mysqlActive = function_exists('storage_mysql_enabled') && storage_mysql_enabled('activity_logs');
        if ($mysqlActive && function_exists('storage_adapter_mysql_append_activity_log')) {
            $mysqlOk = storage_adapter_mysql_append_activity_log($record);
        }
        $fileOk = @file_put_contents(activity_log_path(), $json . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
        if ($mysqlActive) {
            return $mysqlOk || (function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled() && $fileOk);
        }
        return $fileOk;
    }
}

if (!function_exists('activity_log_read')) {
    function activity_log_read(int $limit = 200, array $filters = []): array
    {
        if (function_exists('storage_adapter_mysql_read_activity_logs') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('activity_logs')) {
            $mysqlRows = storage_adapter_mysql_read_activity_logs($limit, $filters);
            if (is_array($mysqlRows)) {
                return $mysqlRows;
            }
        }

        $path = activity_log_path();
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $limit = max(1, min(5000, $limit));
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $rows = [];
        foreach (array_reverse($lines) as $line) {
            $decoded = json_decode((string)$line, true);
            if (!is_array($decoded)) {
                continue;
            }

            $action = strtolower((string)($filters['action'] ?? ''));
            $entity = strtolower((string)($filters['entity'] ?? ''));
            $q = strtolower((string)($filters['q'] ?? ''));

            if ($action !== '' && strtolower((string)($decoded['action'] ?? '')) !== $action) {
                continue;
            }
            if ($entity !== '' && strtolower((string)($decoded['entity'] ?? '')) !== $entity) {
                continue;
            }
            if ($q !== '') {
                $haystack = strtolower(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                if (!str_contains($haystack, $q)) {
                    continue;
                }
            }

            $rows[] = $decoded;
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }
}

if (!function_exists('activity_log_count')) {
    function activity_log_count(): int
    {
        if (function_exists('storage_adapter_mysql_count_activity_logs') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('activity_logs')) {
            $mysqlCount = storage_adapter_mysql_count_activity_logs();
            if (is_int($mysqlCount)) {
                return $mysqlCount;
            }
        }

        $path = activity_log_path();
        if (!is_file($path) || !is_readable($path)) {
            return 0;
        }

        $count = 0;
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return 0;
        }

        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $count++;
            }
        }
        fclose($handle);

        return $count;
    }
}

if (!function_exists('activity_log_stats')) {
    function activity_log_stats(): array
    {
        $rows = activity_log_read(1000);
        $byAction = [];
        $byEntity = [];
        $lastTime = '';

        foreach ($rows as $row) {
            $action = (string)($row['action'] ?? 'unknown');
            $entity = (string)($row['entity'] ?? 'unknown');
            $byAction[$action] = ($byAction[$action] ?? 0) + 1;
            $byEntity[$entity] = ($byEntity[$entity] ?? 0) + 1;
            if ($lastTime === '') {
                $lastTime = (string)($row['time'] ?? '');
            }
        }

        arsort($byAction);
        arsort($byEntity);

        return [
            'enabled' => activity_log_enabled(),
            'total' => activity_log_count(),
            'recent_loaded' => count($rows),
            'by_action' => $byAction,
            'by_entity' => $byEntity,
            'last_time' => $lastTime,
            'path' => activity_log_path(),
            'writable' => activity_log_directory_ready(),
            'size_bytes' => is_file(activity_log_path()) ? (int)@filesize(activity_log_path()) : 0,
        ];
    }
}

if (!function_exists('activity_log_prune')) {
    function activity_log_prune(int $keepLast = 1000): int
    {
        $path = activity_log_path();
        if (!is_file($path) || !is_readable($path) || !is_writable($path)) {
            return 0;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return 0;
        }

        $original = count($lines);
        $keepLast = max(10, min(10000, $keepLast));
        $kept = array_slice($lines, -$keepLast);
        @file_put_contents($path, $kept ? implode(PHP_EOL, $kept) . PHP_EOL : '', LOCK_EX);

        return max(0, $original - count($kept));
    }
}

if (!function_exists('activity_log_clear')) {
    function activity_log_clear(): bool
    {
        if (!activity_log_directory_ready()) {
            return false;
        }
        return @file_put_contents(activity_log_path(), '', LOCK_EX) !== false;
    }
}

if (!function_exists('admin_panel_nav_links')) {
    function admin_panel_nav_links(): array
    {
        return [
            'admin/brand' => 'Brand & Warna',
            'admin/navigation' => 'Menu & Footer',
            'admin/business' => 'Mode & Kategori Bisnis',
            'admin/homepage' => 'Atur Beranda',
            'admin/onboarding-assistant' => 'Onboarding Setup Assistant',
            'admin/help-center' => 'Pusat Bantuan Dashboard',
            'admin/menu-features' => 'Menu & Fitur Admin',
            'admin/profit-playbook' => 'Profit Playbook & Campaign Planner',
            'admin/seo-profit-attribution' => 'SEO Profit Attribution Bridge',
            'admin/seo-assisted-journey' => 'SEO Assisted Conversion Journey Map',
            'admin/seo-money-page-optimizer' => 'SEO Money Page Optimizer',
            'admin/money-page-deployment-checklist' => 'Money Page Deployment Checklist',
            'admin/internal-link-cta-injection' => 'Internal Link & CTA Injection Assistant',
            'admin/seo-campaign-calendar' => 'SEO Campaign Calendar & Growth Sprint Planner',
            'admin/u-growth-command-center' => 'U-Growth Command Center',
            'admin/release-audit' => 'Audit Kesiapan Website',
            'admin/produk' => 'Katalog Produk/Jasa',
            'admin/artikel' => 'Artikel',
            'admin/seo-quality' => 'Cek SEO',
            'admin/seo-landings' => 'SEO Landing Pages',
            'admin/media-library' => 'Media & Asset SEO',
            'admin/forms' => 'Form Custom',
            'admin/form-checkout' => 'Form Checkout',
            'admin/orders' => 'Order',
            'admin/inventory' => 'Stock & Inventory',
            'admin/shipping' => 'Shipping & Ongkir',
            'admin/renewal-clv' => 'Renewal & CLV',
            'admin/followups' => 'Follow-up & CRM',
            'admin/reports' => 'Laporan & Insight Penjualan',
            'admin/commerce-insight' => 'Laporan & Insight Penjualan',
            'admin/payment-settings' => 'Pembayaran Manual',
            'admin/payment-gateway' => 'Payment Gateway',
            'admin/payment-proofs' => 'Bukti Pembayaran',
            'admin/payment-reminders' => 'Reminder Pembayaran',
            'admin/transaction-audit' => 'Audit Transaksi',
            'admin/leads' => 'Tracking Lead',
            'admin/inquiries' => 'Inbox Lead / Form',
            'admin/notifications' => 'Riwayat Email',
            'admin/landing-pages' => 'Landing Pages',
            'admin/landing-page-analytics' => 'Analisis Landing Page',
            'admin/landing-page-optimization' => 'Optimasi Landing Page',
            'admin/marketing-analytics' => 'Marketing & Analytics Center',
            'admin/marketing-integrations' => 'WhatsApp & Email Marketing',
            'admin/growth-insights' => 'Growth Insight',
            'admin/seo-growth-planner' => 'SEO Growth Planner',
            'admin/seo-content-planner' => 'SEO Content Planner',
            'admin/analytics' => 'Analytics & Iklan',
            'admin/security' => 'Keamanan',
            'admin/smtp' => 'SMTP / Email Server',
            'admin/activity-log' => 'Log Sistem',
            'admin/data-health' => 'Cek Sistem',
            'admin/production-readiness' => 'Kesiapan Website',
            'admin/maintenance' => 'Backup & Restore',
        ];
    }
}

if (!function_exists('admin_panel_render_nav')) {
    function admin_panel_render_nav(string $active = ''): void
    {
        // Template: sidebar kiri menjadi satu-satunya navigasi utama admin.
        // Function ini dipertahankan sebagai compatibility shim agar halaman lama tetap aman.
        unset($active);
        return;
    }
}
