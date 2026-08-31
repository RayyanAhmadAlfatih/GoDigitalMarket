<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| STORAGE ADAPTER FOUNDATION
|--------------------------------------------------------------------------
| Prepares the website to move from lightweight file/JSON storage to MySQL
| without forcing existing stable modules to switch immediately. Default is
| deliberately safe: file-based remains active until admin enables MySQL per
| collection after DB connection and tables are ready.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('storage_adapter_settings_file')) {
    function storage_adapter_settings_file(): string
    {
        return STORAGE_PATH . '/storage-settings.json';
    }
}

if (!function_exists('storage_adapter_valid_driver')) {
    function storage_adapter_valid_driver(string $driver): string
    {
        $driver = strtolower(trim($driver));
        return in_array($driver, ['file', 'mysql', 'hybrid'], true) ? $driver : 'file';
    }
}

if (!function_exists('storage_adapter_bool')) {
    function storage_adapter_bool(mixed $value, bool $default = false): bool
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

if (!function_exists('storage_adapter_default_collections')) {
    function storage_adapter_default_collections(): array
    {
        return [
            'products' => [
                'label' => 'Produk / Katalog',
                'module' => 'Konten & SEO',
                'json_file' => 'products.json',
                'mysql_table' => 'products',
                'record_type' => 'json-array',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Sudah punya CRUD MySQL-ready, tetapi tetap file-based sampai admin mengaktifkan mode MySQL.',
            ],
            'articles' => [
                'label' => 'Artikel',
                'module' => 'Konten & SEO',
                'json_file' => 'articles.json',
                'mysql_table' => 'articles',
                'record_type' => 'json-array',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Sudah punya CRUD MySQL-ready, tetapi tetap file-based sampai admin mengaktifkan mode MySQL.',
            ],
            'landing_pages' => [
                'label' => 'Landing Page Builder',
                'module' => 'Landing Page Builder',
                'json_file' => 'landing-pages.json',
                'mysql_table' => 'ugrowth_landing_pages',
                'record_type' => 'json-array',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Landing page bisa dibaca/ditulis ke MySQL runtime jika collection diaktifkan. File JSON tetap menjadi mirror dan fallback aman.',
            ],
            'custom_forms' => [
                'label' => 'Custom Form',
                'module' => 'Form Builder',
                'json_file' => 'custom-forms.json',
                'mysql_table' => 'ugrowth_custom_forms',
                'record_type' => 'json-array',
                'ready_for_mysql' => false,
                'default_mysql' => false,
                'note' => 'Form config tetap JSON sampai migrasi collection generic dibuat.',
            ],
            'form_submissions' => [
                'label' => 'Data Masuk Form',
                'module' => 'Lead & Customer',
                'json_file' => 'custom-form-submissions-*.jsonl',
                'mysql_table' => 'ugrowth_form_submissions',
                'record_type' => 'jsonl',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Data lead bisa masuk MySQL jika driver, koneksi, tabel, dan collection sudah diaktifkan admin. File mirror tetap aman.',
            ],
            'inquiries' => [
                'label' => 'Inbox Lead / Form Sederhana',
                'module' => 'Lead & Customer',
                'json_file' => 'inquiries-*.jsonl',
                'mysql_table' => 'ugrowth_inquiries',
                'record_type' => 'jsonl',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Lead dari form inquiry lama/sederhana bisa masuk MySQL runtime. JSONL tetap menjadi mirror/fallback.',
            ],
            'orders' => [
                'label' => 'Order / Checkout',
                'module' => 'Order & Penjualan',
                'json_file' => 'orders-*.jsonl',
                'mysql_table' => 'ugrowth_orders',
                'record_type' => 'jsonl',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Order bisa masuk MySQL jika driver, koneksi, tabel, dan collection sudah diaktifkan admin. File mirror tetap aman.',
            ],
            'payment_proofs' => [
                'label' => 'Bukti Pembayaran',
                'module' => 'Pembayaran',
                'json_file' => 'payment-proofs-*.jsonl',
                'mysql_table' => 'ugrowth_payment_proofs',
                'record_type' => 'jsonl',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Metadata bukti pembayaran bisa masuk MySQL runtime. File upload tetap di storage privat dan JSONL tetap menjadi mirror/fallback.',
            ],
            'buyer_accounts' => [
                'label' => 'Akun Pembeli / Member',
                'module' => 'Member Area',
                'json_file' => 'buyer-accounts.json',
                'mysql_table' => 'ugrowth_buyer_accounts',
                'record_type' => 'json-array',
                'ready_for_mysql' => false,
                'default_mysql' => false,
                'note' => 'Fondasi member tetap aman di JSON sambil disiapkan schema buyer.',
            ],
            'member_access' => [
                'label' => 'Akses Produk Digital',
                'module' => 'Member Area',
                'json_file' => 'member-access.json',
                'mysql_table' => 'ugrowth_member_access',
                'record_type' => 'json-array',
                'ready_for_mysql' => false,
                'default_mysql' => false,
                'note' => 'Akses produk digital akan menjadi prioritas migrasi setelah order/payment stabil.',
            ],
            'analytics_events' => [
                'label' => 'Analytics / Lead Events',
                'module' => 'Marketing & Analytics',
                'json_file' => 'lead-events-*.jsonl',
                'mysql_table' => 'ugrowth_analytics_events',
                'record_type' => 'jsonl',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Event lead, CTA, channel, campaign, dan analytics bisa masuk MySQL runtime untuk dashboard/Looker Studio. JSONL tetap menjadi mirror/fallback.',
            ],
            'email_logs' => [
                'label' => 'Riwayat Email',
                'module' => 'Sistem',
                'json_file' => 'email-events-*.jsonl',
                'mysql_table' => 'ugrowth_email_logs',
                'record_type' => 'jsonl',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Riwayat email bisa masuk MySQL runtime agar admin dan laporan lebih mudah membaca performa notifikasi. JSONL tetap menjadi mirror/fallback.',
            ],
            'activity_logs' => [
                'label' => 'Log Aktivitas Admin',
                'module' => 'Sistem',
                'json_file' => 'admin-activity.log',
                'mysql_table' => 'ugrowth_activity_logs',
                'record_type' => 'jsonl',
                'ready_for_mysql' => true,
                'default_mysql' => false,
                'note' => 'Log aktivitas admin bisa masuk MySQL runtime untuk audit operasional. File log tetap menjadi mirror/fallback dan tidak ikut ZIP rilis.',
            ],
        ];
    }
}

if (!function_exists('storage_adapter_default_settings')) {
    function storage_adapter_default_settings(): array
    {
        $envDriver = storage_adapter_valid_driver((string)($_ENV['STORAGE_DRIVER'] ?? 'file'));
        $collections = [];
        foreach (storage_adapter_default_collections() as $key => $meta) {
            $envKey = 'STORAGE_MYSQL_' . strtoupper($key);
            $collections[$key] = [
                'mysql_enabled' => storage_adapter_bool($_ENV[$envKey] ?? ($meta['default_mysql'] ?? false), false),
                'last_migration_at' => '',
                'last_migration_note' => '',
            ];
        }

        return [
            'schema' => 'ugrowth-storage-settings-v1',
            'driver' => $envDriver,
            'safe_fallback' => true,
            'mysql_auto_switch' => false,
            'collections' => $collections,
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('storage_adapter_normalize_settings')) {
    function storage_adapter_normalize_settings(array $settings): array
    {
        $defaults = storage_adapter_default_settings();
        $settings['schema'] = (string)($settings['schema'] ?? $defaults['schema']);
        $settings['driver'] = storage_adapter_valid_driver((string)($settings['driver'] ?? $defaults['driver']));
        $settings['safe_fallback'] = storage_adapter_bool($settings['safe_fallback'] ?? true, true);
        $settings['mysql_auto_switch'] = storage_adapter_bool($settings['mysql_auto_switch'] ?? false, false);
        $settings['collections'] = is_array($settings['collections'] ?? null) ? $settings['collections'] : [];

        foreach (storage_adapter_default_collections() as $key => $meta) {
            $current = is_array($settings['collections'][$key] ?? null) ? $settings['collections'][$key] : [];
            $settings['collections'][$key] = [
                'mysql_enabled' => storage_adapter_bool($current['mysql_enabled'] ?? $defaults['collections'][$key]['mysql_enabled'] ?? false, false),
                'last_migration_at' => trim((string)($current['last_migration_at'] ?? '')),
                'last_migration_note' => trim((string)($current['last_migration_note'] ?? '')),
            ];
        }

        $settings['updated_at'] = trim((string)($settings['updated_at'] ?? $defaults['updated_at']));
        return $settings;
    }
}

if (!function_exists('storage_adapter_settings')) {
    function storage_adapter_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = storage_adapter_settings_file();
        if (!is_file($file)) {
            $cached = storage_adapter_default_settings();
            return $cached;
        }

        $decoded = json_decode((string)@file_get_contents($file), true);
        $cached = storage_adapter_normalize_settings(is_array($decoded) ? $decoded : []);
        return $cached;
    }
}

if (!function_exists('storage_adapter_write_settings')) {
    function storage_adapter_write_settings(array $settings, bool $throw = true): bool
    {
        $settings = storage_adapter_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || @file_put_contents(storage_adapter_settings_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Pengaturan Storage & Database belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(storage_adapter_settings_file(), 0644);
        storage_adapter_settings(true);
        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'storage-database', null, 'Menyimpan pengaturan Storage & Database.', ['driver' => $settings['driver']]);
        }
        return true;
    }
}

if (!function_exists('storage_adapter_save_from_post')) {
    function storage_adapter_save_from_post(array $post): bool
    {
        $settings = storage_adapter_settings(true);
        $settings['driver'] = storage_adapter_valid_driver((string)($post['storage_driver'] ?? 'file'));
        $settings['safe_fallback'] = !empty($post['safe_fallback']);
        $enabled = is_array($post['mysql_collections'] ?? null) ? array_map('strval', (array)$post['mysql_collections']) : [];
        foreach (storage_adapter_default_collections() as $key => $meta) {
            $settings['collections'][$key]['mysql_enabled'] = in_array($key, $enabled, true) && !empty($meta['ready_for_mysql']);
        }
        return storage_adapter_write_settings($settings, true);
    }
}

if (!function_exists('storage_adapter_driver')) {
    function storage_adapter_driver(): string
    {
        return storage_adapter_valid_driver((string)(storage_adapter_settings()['driver'] ?? 'file'));
    }
}

if (!function_exists('storage_adapter_mysql_driver_requested')) {
    function storage_adapter_mysql_driver_requested(): bool
    {
        return in_array(storage_adapter_driver(), ['mysql', 'hybrid'], true);
    }
}

if (!function_exists('storage_adapter_table_exists')) {
    function storage_adapter_table_exists(string $table): bool
    {
        static $cache = [];
        $table = trim($table);
        if ($table === '' || !function_exists('db_available') || !db_available()) {
            return false;
        }
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }
        try {
            $stmt = db()->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            $cache[$table] = (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            $cache[$table] = false;
        }
        return $cache[$table];
    }
}

if (!function_exists('storage_adapter_mysql_enabled')) {
    function storage_adapter_mysql_enabled(string $collection): bool
    {
        $collections = storage_adapter_default_collections();
        if (!isset($collections[$collection])) {
            return false;
        }
        $settings = storage_adapter_settings();
        $collectionSettings = is_array($settings['collections'][$collection] ?? null) ? $settings['collections'][$collection] : [];
        $mysqlEnabled = storage_adapter_bool($collectionSettings['mysql_enabled'] ?? false, false);
        $driverOk = storage_adapter_mysql_driver_requested();
        $table = (string)($collections[$collection]['mysql_table'] ?? '');

        return $driverOk
            && $mysqlEnabled
            && !empty($collections[$collection]['ready_for_mysql'])
            && function_exists('db_available')
            && db_available()
            && storage_adapter_table_exists($table);
    }
}

if (!function_exists('storage_mysql_enabled')) {
    function storage_mysql_enabled(string $collection): bool
    {
        return storage_adapter_mysql_enabled($collection);
    }
}


if (!function_exists('storage_adapter_safe_fallback_enabled')) {
    function storage_adapter_safe_fallback_enabled(): bool
    {
        return storage_adapter_bool(storage_adapter_settings()['safe_fallback'] ?? true, true);
    }
}

if (!function_exists('storage_adapter_mysql_json')) {
    function storage_adapter_mysql_json(array $record): ?string
    {
        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }
}

if (!function_exists('storage_adapter_first_value')) {
    function storage_adapter_first_value(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = $values[$key];
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $value = trim(strip_tags((string)$value));
            if ($value !== '') {
                return function_exists('mb_substr') ? mb_substr($value, 0, 190) : substr($value, 0, 190);
            }
        }
        return '';
    }
}

if (!function_exists('storage_adapter_mysql_append_form_submission')) {
    function storage_adapter_mysql_append_form_submission(array $entry, bool $force = false): bool
    {
        if (!$force && !storage_adapter_mysql_enabled('form_submissions')) {
            return false;
        }
        if ($force && (!function_exists('db_available') || !db_available() || !storage_adapter_table_exists('ugrowth_form_submissions'))) {
            return false;
        }
        $json = storage_adapter_mysql_json($entry);
        if ($json === null) {
            return false;
        }
        $values = is_array($entry['values'] ?? null) ? (array)$entry['values'] : [];
        if (function_exists('custom_form_submission_contact')) {
            $contact = custom_form_submission_contact($entry);
            $name = (string)($contact['name'] ?? '');
            $email = (string)($contact['email'] ?? '');
            $phone = (string)($contact['phone'] ?? '');
        } else {
            $name = storage_adapter_first_value($values, ['nama', 'name', 'nama_lengkap', 'full_name', 'customer_name']);
            $email = storage_adapter_first_value($values, ['email', 'alamat_email']);
            $phone = storage_adapter_first_value($values, ['whatsapp', 'wa', 'phone', 'telepon', 'no_hp', 'nomor_whatsapp', 'nomor_hp']);
        }
        $createdAt = (string)($entry['created_at'] ?? '');
        $createdSql = ($createdAt !== '' && strtotime($createdAt) !== false) ? date('Y-m-d H:i:s', (int)strtotime($createdAt)) : date('Y-m-d H:i:s');
        try {
            $stmt = db()->prepare('INSERT INTO ugrowth_form_submissions (form_slug, name, email, phone, source, payload, created_at) VALUES (:form_slug, :name, :email, :phone, :source, :payload, :created_at)');
            return $stmt->execute([
                ':form_slug' => (string)($entry['form_slug'] ?? ''),
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':source' => (string)($entry['source_type'] ?? $entry['source_label'] ?? ''),
                ':payload' => $json,
                ':created_at' => $createdSql,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_read_form_submissions')) {
    function storage_adapter_mysql_read_form_submissions(array $filters = [], int $limit = 300): ?array
    {
        if (!storage_adapter_mysql_enabled('form_submissions')) {
            return null;
        }
        $limit = max(1, min(5000, $limit));
        $where = [];
        $params = [];
        $dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($filters['date_from'] ?? '')) ? (string)$filters['date_from'] : '';
        $dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($filters['date_to'] ?? '')) ? (string)$filters['date_to'] : '';
        $formSlug = trim((string)($filters['form_slug'] ?? ''));
        if ($formSlug !== '') {
            $where[] = 'form_slug = :form_slug';
            $params[':form_slug'] = $formSlug;
        }
        if ($dateFrom !== '') {
            $where[] = 'created_at >= :date_from';
            $params[':date_from'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'created_at <= :date_to';
            $params[':date_to'] = $dateTo . ' 23:59:59';
        }
        $sql = 'SELECT payload FROM ugrowth_form_submissions' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY created_at DESC LIMIT :limit';
        try {
            $stmt = db()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = [];
            while ($row = $stmt->fetch()) {
                $decoded = json_decode((string)($row['payload'] ?? ''), true);
                if (is_array($decoded)) {
                    $rows[] = $decoded;
                }
            }
            $search = strtolower(trim(strip_tags((string)($filters['search'] ?? ''))));
            if ($search !== '') {
                $rows = array_values(array_filter($rows, static function (array $row) use ($search): bool {
                    $haystack = strtolower(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    return str_contains($haystack, $search);
                }));
            }
            return array_slice($rows, 0, $limit);
        } catch (Throwable $e) {
            return null;
        }
    }
}


if (!function_exists('storage_adapter_mysql_append_inquiry')) {
    function storage_adapter_mysql_append_inquiry(array $inquiry, bool $force = false): bool
    {
        if (!$force && !storage_adapter_mysql_enabled('inquiries')) {
            return false;
        }
        if ($force && (!function_exists('db_available') || !db_available() || !storage_adapter_table_exists('ugrowth_inquiries'))) {
            return false;
        }
        if (!$force && !storage_adapter_mysql_runtime_ready('inquiries', 'ugrowth_inquiries')) {
            return false;
        }
        $json = storage_adapter_mysql_json($inquiry);
        if ($json === null) { return false; }
        try {
            $stmt = db()->prepare('INSERT INTO ugrowth_inquiries (inquiry_ref, name, email, phone, status, source, payload, created_at) VALUES (:inquiry_ref, :name, :email, :phone, :status, :source, :payload, :created_at) ON DUPLICATE KEY UPDATE name = VALUES(name), email = VALUES(email), phone = VALUES(phone), status = VALUES(status), source = VALUES(source), payload = VALUES(payload)');
            return $stmt->execute([
                ':inquiry_ref' => substr((string)($inquiry['id'] ?? $inquiry['ref'] ?? storage_adapter_record_key('inquiries', $inquiry)), 0, 190),
                ':name' => substr((string)($inquiry['name'] ?? ''), 0, 190),
                ':email' => substr((string)($inquiry['email'] ?? ''), 0, 190),
                ':phone' => substr((string)($inquiry['phone'] ?? ''), 0, 60),
                ':status' => substr((string)($inquiry['status'] ?? 'Baru'), 0, 80),
                ':source' => substr((string)($inquiry['source'] ?? ''), 0, 190),
                ':payload' => $json,
                ':created_at' => storage_adapter_sql_datetime($inquiry['time'] ?? $inquiry['created_at'] ?? null),
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_read_inquiries')) {
    function storage_adapter_mysql_read_inquiries(int $days = 30, array $filters = [], int $max = 5000): ?array
    {
        if (!storage_adapter_mysql_enabled('inquiries')) { return null; }
        $max = max(50, min(50000, $max));
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) { $days = 0; }
        if ($days > 0 && $startTs <= 0) { $startTs = time() - (max(1, min(3650, $days)) * 86400); }
        $where = [];
        $params = [];
        if ($startTs > 0) { $where[] = 'created_at >= :start_at'; $params[':start_at'] = date('Y-m-d H:i:s', $startTs); }
        if ($endTs > 0) { $where[] = 'created_at <= :end_at'; $params[':end_at'] = date('Y-m-d H:i:s', $endTs); }
        $sql = 'SELECT payload, created_at FROM ugrowth_inquiries' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY created_at DESC LIMIT :limit';
        try {
            $stmt = db()->prepare($sql);
            foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
            $stmt->bindValue(':limit', $max, PDO::PARAM_INT);
            $stmt->execute();
            $items = [];
            while ($row = $stmt->fetch()) {
                $inquiry = json_decode((string)($row['payload'] ?? ''), true);
                if (!is_array($inquiry)) { continue; }
                $ts = function_exists('inquiry_event_timestamp') ? inquiry_event_timestamp($inquiry) : (strtotime((string)($inquiry['time'] ?? '')) ?: strtotime((string)($row['created_at'] ?? '')) ?: 0);
                if ($ts > 0) { $inquiry['_ts'] = $ts; }
                if (function_exists('inquiry_matches_filters') && !inquiry_matches_filters($inquiry, $filters)) { continue; }
                $items[] = $inquiry;
                if (count($items) >= $max) { break; }
            }
            usort($items, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
            return $items;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('storage_adapter_mysql_append_order')) {
    function storage_adapter_mysql_append_order(array $order, bool $force = false): bool
    {
        if (!$force && !storage_adapter_mysql_enabled('orders')) {
            return false;
        }
        if ($force && (!function_exists('db_available') || !db_available() || !storage_adapter_table_exists('ugrowth_orders'))) {
            return false;
        }
        $json = storage_adapter_mysql_json($order);
        if ($json === null) {
            return false;
        }
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? $order['id'] ?? '');
        $createdAt = (string)($order['time'] ?? $order['created_at'] ?? '');
        $createdSql = ($createdAt !== '' && strtotime($createdAt) !== false) ? date('Y-m-d H:i:s', (int)strtotime($createdAt)) : date('Y-m-d H:i:s');
        $total = (int)($order['total'] ?? $order['grand_total'] ?? $order['invoice_total'] ?? 0);
        if ($total <= 0) {
            $total = (int)($order['price'] ?? 0) * max(1, (int)($order['quantity'] ?? 1));
            $total += (int)($order['shipping_cost'] ?? 0);
        }
        try {
            $stmt = db()->prepare('INSERT INTO ugrowth_orders (order_ref, customer_name, customer_email, customer_phone, payment_status, order_status, total, payload, created_at, updated_at) VALUES (:order_ref, :customer_name, :customer_email, :customer_phone, :payment_status, :order_status, :total, :payload, :created_at, NOW()) ON DUPLICATE KEY UPDATE customer_name = VALUES(customer_name), customer_email = VALUES(customer_email), customer_phone = VALUES(customer_phone), payment_status = VALUES(payment_status), order_status = VALUES(order_status), total = VALUES(total), payload = VALUES(payload), updated_at = NOW()');
            $ok = $stmt->execute([
                ':order_ref' => $ref,
                ':customer_name' => (string)($order['name'] ?? ''),
                ':customer_email' => (string)($order['email'] ?? ''),
                ':customer_phone' => (string)($order['phone'] ?? ''),
                ':payment_status' => (string)($order['payment_status'] ?? ''),
                ':order_status' => (string)($order['status'] ?? ''),
                ':total' => max(0, $total),
                ':payload' => $json,
                ':created_at' => $createdSql,
            ]);
            if ($ok && $ref !== '' && storage_adapter_table_exists('ugrowth_order_items')) {
                storage_adapter_mysql_replace_order_items($ref, $order);
            }
            return $ok;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_read_orders')) {
    function storage_adapter_mysql_read_orders(int $days = 30, array $filters = [], int $max = 5000): ?array
    {
        if (!storage_adapter_mysql_enabled('orders')) {
            return null;
        }
        $max = max(50, min(50000, $max));
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) {
            $days = 0;
        }
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $where = [];
        $params = [];
        if ($startTs > 0) {
            $where[] = 'created_at >= :start_at';
            $params[':start_at'] = date('Y-m-d H:i:s', $startTs);
        }
        if ($endTs > 0) {
            $where[] = 'created_at <= :end_at';
            $params[':end_at'] = date('Y-m-d H:i:s', $endTs);
        }
        $sql = 'SELECT payload, created_at FROM ugrowth_orders' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY created_at DESC LIMIT :limit';
        try {
            $stmt = db()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $max, PDO::PARAM_INT);
            $stmt->execute();
            $orders = [];
            while ($row = $stmt->fetch()) {
                $order = json_decode((string)($row['payload'] ?? ''), true);
                if (!is_array($order)) {
                    continue;
                }
                if (empty($order['time']) && !empty($row['created_at'])) {
                    $order['time'] = date('c', strtotime((string)$row['created_at']) ?: time());
                }
                if (function_exists('order_event_timestamp')) {
                    $order['_ts'] = order_event_timestamp($order);
                }
                if (function_exists('order_matches_filters') && !order_matches_filters($order, $filters)) {
                    continue;
                }
                $orders[] = $order;
                if (count($orders) >= $max) {
                    break;
                }
            }
            usort($orders, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
            return $orders;
        } catch (Throwable $e) {
            return null;
        }
    }
}


if (!function_exists('storage_adapter_mysql_replace_order_items')) {
    function storage_adapter_mysql_replace_order_items(string $orderRef, array $order): bool
    {
        $orderRef = trim($orderRef);
        if ($orderRef === '' || !function_exists('db_available') || !db_available() || !storage_adapter_table_exists('ugrowth_order_items')) {
            return false;
        }
        $items = [];
        if (isset($order['items']) && is_array($order['items'])) {
            foreach ($order['items'] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }
        if (!$items) {
            $quantity = max(1, (int)($order['quantity'] ?? 1));
            $price = max(0, (int)($order['price'] ?? 0));
            $subtotal = max(0, (int)($order['subtotal'] ?? ($price * $quantity)));
            $items[] = [
                'product_slug' => (string)($order['product_slug'] ?? ''),
                'product_title' => (string)($order['product_title'] ?? $order['item_title'] ?? ''),
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'payload' => $order,
            ];
        }
        try {
            $delete = db()->prepare('DELETE FROM ugrowth_order_items WHERE order_ref = ?');
            $delete->execute([$orderRef]);
            $stmt = db()->prepare('INSERT INTO ugrowth_order_items (order_ref, product_slug, product_title, quantity, subtotal, payload, created_at) VALUES (:order_ref, :product_slug, :product_title, :quantity, :subtotal, :payload, NOW())');
            foreach ($items as $item) {
                $payload = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $qty = max(1, (int)($item['quantity'] ?? 1));
                $subtotal = (int)($item['subtotal'] ?? 0);
                if ($subtotal <= 0) {
                    $subtotal = max(0, (int)($item['price'] ?? 0)) * $qty;
                }
                $stmt->execute([
                    ':order_ref' => $orderRef,
                    ':product_slug' => substr(trim((string)($item['product_slug'] ?? $item['slug'] ?? '')), 0, 190) ?: null,
                    ':product_title' => substr(trim((string)($item['product_title'] ?? $item['title'] ?? $item['name'] ?? '')), 0, 255) ?: null,
                    ':quantity' => $qty,
                    ':subtotal' => max(0, $subtotal),
                    ':payload' => $payload !== false ? $payload : null,
                ]);
            }
            return true;
        } catch (Throwable $e) {
            error_log('[ORDER_ITEMS_DB_REPLACE] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_runtime_ready')) {
    function storage_adapter_mysql_runtime_ready(string $collection, string $table): bool
    {
        if (storage_adapter_mysql_enabled($collection)) {
            return true;
        }
        return function_exists('db_available') && db_available() && storage_adapter_table_exists($table);
    }
}

if (!function_exists('storage_adapter_mysql_append_payment_proof')) {
    function storage_adapter_mysql_append_payment_proof(array $proof, bool $force = false): bool
    {
        if (!$force && !storage_adapter_mysql_enabled('payment_proofs')) {
            return false;
        }
        if (!storage_adapter_mysql_runtime_ready('payment_proofs', 'ugrowth_payment_proofs')) {
            return false;
        }
        $json = storage_adapter_mysql_json($proof);
        if ($json === null) {
            return false;
        }
        $createdSql = storage_adapter_sql_datetime($proof['time'] ?? $proof['created_at'] ?? null);
        try {
            $stmt = db()->prepare('INSERT INTO ugrowth_payment_proofs (order_ref, payer_name, amount, status, file_path, payload, created_at) VALUES (:order_ref, :payer_name, :amount, :status, :file_path, :payload, :created_at)');
            return $stmt->execute([
                ':order_ref' => (string)($proof['order_ref'] ?? $proof['ref'] ?? ''),
                ':payer_name' => (string)($proof['payer_name'] ?? $proof['name'] ?? ''),
                ':amount' => max(0, (int)($proof['amount'] ?? $proof['payment_amount'] ?? $proof['invoice_total'] ?? 0)),
                ':status' => (string)($proof['status'] ?? 'Menunggu Review'),
                ':file_path' => (string)($proof['file_path'] ?? $proof['filename'] ?? ''),
                ':payload' => $json,
                ':created_at' => $createdSql,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_read_payment_proofs')) {
    function storage_adapter_mysql_read_payment_proofs(int $days = 30, array $filters = [], int $max = 5000): ?array
    {
        if (!storage_adapter_mysql_enabled('payment_proofs')) {
            return null;
        }
        $max = max(50, min(50000, $max));
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) {
            $days = 0;
        }
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $where = [];
        $params = [];
        if ($startTs > 0) { $where[] = 'created_at >= :start_at'; $params[':start_at'] = date('Y-m-d H:i:s', $startTs); }
        if ($endTs > 0) { $where[] = 'created_at <= :end_at'; $params[':end_at'] = date('Y-m-d H:i:s', $endTs); }
        $sql = 'SELECT payload, created_at FROM ugrowth_payment_proofs' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY created_at DESC LIMIT :limit';
        try {
            $stmt = db()->prepare($sql);
            foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
            $stmt->bindValue(':limit', $max, PDO::PARAM_INT);
            $stmt->execute();
            $proofs = [];
            while ($row = $stmt->fetch()) {
                $proof = json_decode((string)($row['payload'] ?? ''), true);
                if (!is_array($proof)) { continue; }
                $ts = function_exists('payment_proof_event_timestamp') ? payment_proof_event_timestamp($proof) : (strtotime((string)($proof['time'] ?? '')) ?: strtotime((string)($row['created_at'] ?? '')) ?: 0);
                if ($ts > 0) { $proof['_ts'] = $ts; }
                if (function_exists('payment_proof_matches_filters') && !payment_proof_matches_filters($proof, $filters)) { continue; }
                $proofs[] = $proof;
                if (count($proofs) >= $max) { break; }
            }
            usort($proofs, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
            return $proofs;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('storage_adapter_mysql_append_analytics_event')) {
    function storage_adapter_mysql_append_analytics_event(array $event, bool $force = false): bool
    {
        if (!$force && !storage_adapter_mysql_enabled('analytics_events')) {
            return false;
        }
        if (!storage_adapter_mysql_runtime_ready('analytics_events', 'ugrowth_analytics_events')) {
            return false;
        }
        $json = storage_adapter_mysql_json($event);
        if ($json === null) { return false; }
        $createdSql = storage_adapter_sql_datetime($event['time'] ?? $event['created_at'] ?? null);
        try {
            $stmt = db()->prepare('INSERT INTO ugrowth_analytics_events (event_name, source, page_path, session_id, payload, created_at) VALUES (:event_name, :source, :page_path, :session_id, :payload, :created_at)');
            return $stmt->execute([
                ':event_name' => substr((string)($event['event_name'] ?? $event['type'] ?? $event['channel'] ?? 'lead_event'), 0, 120),
                ':source' => substr((string)($event['source'] ?? $event['channel'] ?? ''), 0, 190),
                ':page_path' => substr((string)($event['page_path'] ?? ''), 0, 255),
                ':session_id' => substr((string)($event['session_id'] ?? $event['event_id'] ?? ''), 0, 190),
                ':payload' => $json,
                ':created_at' => $createdSql,
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_read_analytics_events')) {
    function storage_adapter_mysql_read_analytics_events(int $days = 30, array $filters = [], int $maxEvents = 120000): ?array
    {
        if (!storage_adapter_mysql_enabled('analytics_events')) {
            return null;
        }
        $maxEvents = max(100, min(200000, $maxEvents));
        $window = function_exists('conversion_dashboard_window') ? conversion_dashboard_window($days, $filters) : ['start' => null, 'end' => time(), 'all_time' => $days <= 0];
        $startTs = $window['start'] ?? null;
        $endTs = $window['end'] ?? time();
        $where = [];
        $params = [];
        if ($startTs !== null) { $where[] = 'created_at >= :start_at'; $params[':start_at'] = date('Y-m-d H:i:s', (int)$startTs); }
        if ($endTs !== null) { $where[] = 'created_at <= :end_at'; $params[':end_at'] = date('Y-m-d H:i:s', (int)$endTs); }
        $sql = 'SELECT payload, created_at FROM ugrowth_analytics_events' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY created_at DESC LIMIT :limit';
        try {
            $stmt = db()->prepare($sql);
            foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
            $stmt->bindValue(':limit', $maxEvents, PDO::PARAM_INT);
            $stmt->execute();
            $events = [];
            while ($row = $stmt->fetch()) {
                $event = json_decode((string)($row['payload'] ?? ''), true);
                if (!is_array($event)) { continue; }
                $timestamp = function_exists('conversion_event_timestamp') ? conversion_event_timestamp($event) : (strtotime((string)($event['time'] ?? '')) ?: strtotime((string)($row['created_at'] ?? '')) ?: 0);
                if ($timestamp <= 0) { continue; }
                $event['_ts'] = $timestamp;
                $event = function_exists('conversion_enrich_lead_event') ? conversion_enrich_lead_event($event) : $event;
                if (function_exists('conversion_event_matches_filters') && !conversion_event_matches_filters($event, $filters)) { continue; }
                $events[] = $event;
                if (count($events) >= $maxEvents) { break; }
            }
            usort($events, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
            return $events;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('storage_adapter_mysql_append_email_log')) {
    function storage_adapter_mysql_append_email_log(array $event, bool $force = false): bool
    {
        if (!$force && !storage_adapter_mysql_enabled('email_logs')) {
            return false;
        }
        if (!storage_adapter_mysql_runtime_ready('email_logs', 'ugrowth_email_logs')) {
            return false;
        }
        $json = storage_adapter_mysql_json($event);
        if ($json === null) { return false; }
        try {
            $stmt = db()->prepare('INSERT INTO ugrowth_email_logs (recipient, subject, status, payload, created_at) VALUES (:recipient, :subject, :status, :payload, :created_at)');
            return $stmt->execute([
                ':recipient' => substr((string)($event['to'] ?? $event['recipient'] ?? ''), 0, 190),
                ':subject' => substr((string)($event['subject'] ?? ''), 0, 255),
                ':status' => substr((string)($event['status'] ?? 'logged'), 0, 80),
                ':payload' => $json,
                ':created_at' => storage_adapter_sql_datetime($event['time'] ?? $event['created_at'] ?? null),
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_read_email_logs')) {
    function storage_adapter_mysql_read_email_logs(int $days = 30, array $filters = [], int $max = 5000): ?array
    {
        if (!storage_adapter_mysql_enabled('email_logs')) { return null; }
        $max = max(50, min(50000, $max));
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) { $days = 0; }
        if ($days > 0 && $startTs <= 0) { $startTs = time() - (max(1, min(3650, $days)) * 86400); }
        $where = [];
        $params = [];
        if ($startTs > 0) { $where[] = 'created_at >= :start_at'; $params[':start_at'] = date('Y-m-d H:i:s', $startTs); }
        if ($endTs > 0) { $where[] = 'created_at <= :end_at'; $params[':end_at'] = date('Y-m-d H:i:s', $endTs); }
        $sql = 'SELECT payload, created_at FROM ugrowth_email_logs' . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY created_at DESC LIMIT :limit';
        try {
            $stmt = db()->prepare($sql);
            foreach ($params as $key => $value) { $stmt->bindValue($key, $value); }
            $stmt->bindValue(':limit', $max, PDO::PARAM_INT);
            $stmt->execute();
            $events = [];
            while ($row = $stmt->fetch()) {
                $event = json_decode((string)($row['payload'] ?? ''), true);
                if (!is_array($event)) { continue; }
                $ts = function_exists('notification_event_timestamp') ? notification_event_timestamp($event) : (strtotime((string)($event['time'] ?? '')) ?: strtotime((string)($row['created_at'] ?? '')) ?: 0);
                if ($ts > 0) { $event['_ts'] = $ts; }
                if (function_exists('notification_matches_filters') && !notification_matches_filters($event, $filters)) { continue; }
                $events[] = $event;
                if (count($events) >= $max) { break; }
            }
            usort($events, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
            return $events;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('storage_adapter_mysql_append_activity_log')) {
    function storage_adapter_mysql_append_activity_log(array $record, bool $force = false): bool
    {
        if (!$force && !storage_adapter_mysql_enabled('activity_logs')) { return false; }
        if (!storage_adapter_mysql_runtime_ready('activity_logs', 'ugrowth_activity_logs')) { return false; }
        $json = storage_adapter_mysql_json($record);
        if ($json === null) { return false; }
        $actor = '';
        if (is_array($record['admin'] ?? null)) {
            $actor = (string)($record['admin']['session_id'] ?? '');
        }
        $target = trim((string)($record['entity'] ?? 'system'));
        if (trim((string)($record['entity_id'] ?? '')) !== '') {
            $target .= ':' . trim((string)$record['entity_id']);
        }
        try {
            $stmt = db()->prepare('INSERT INTO ugrowth_activity_logs (actor, action, target, payload, created_at) VALUES (:actor, :action, :target, :payload, :created_at)');
            return $stmt->execute([
                ':actor' => substr($actor, 0, 190),
                ':action' => substr((string)($record['action'] ?? 'unknown'), 0, 120),
                ':target' => substr($target, 0, 190),
                ':payload' => $json,
                ':created_at' => storage_adapter_sql_datetime($record['iso_time'] ?? $record['time'] ?? null),
            ]);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_read_activity_logs')) {
    function storage_adapter_mysql_read_activity_logs(int $limit = 200, array $filters = []): ?array
    {
        if (!storage_adapter_mysql_enabled('activity_logs')) { return null; }
        $limit = max(1, min(5000, $limit));
        try {
            $stmt = db()->prepare('SELECT payload FROM ugrowth_activity_logs ORDER BY created_at DESC, id DESC LIMIT :limit');
            $stmt->bindValue(':limit', $limit * 3, PDO::PARAM_INT);
            $stmt->execute();
            $rows = [];
            $action = strtolower((string)($filters['action'] ?? ''));
            $entity = strtolower((string)($filters['entity'] ?? ''));
            $q = strtolower((string)($filters['q'] ?? ''));
            while ($row = $stmt->fetch()) {
                $decoded = json_decode((string)($row['payload'] ?? ''), true);
                if (!is_array($decoded)) { continue; }
                if ($action !== '' && strtolower((string)($decoded['action'] ?? '')) !== $action) { continue; }
                if ($entity !== '' && strtolower((string)($decoded['entity'] ?? '')) !== $entity) { continue; }
                if ($q !== '') {
                    $haystack = strtolower(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    if (!str_contains($haystack, $q)) { continue; }
                }
                $rows[] = $decoded;
                if (count($rows) >= $limit) { break; }
            }
            return $rows;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('storage_adapter_mysql_count_activity_logs')) {
    function storage_adapter_mysql_count_activity_logs(): ?int
    {
        if (!storage_adapter_mysql_enabled('activity_logs')) { return null; }
        try {
            return (int)(db()->query('SELECT COUNT(*) FROM ugrowth_activity_logs')->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return null;
        }
    }
}


if (!function_exists('storage_adapter_sql_datetime')) {
    function storage_adapter_sql_datetime(mixed $value = null): string
    {
        $raw = trim((string)($value ?? ''));
        $ts = $raw !== '' ? strtotime($raw) : false;
        return date('Y-m-d H:i:s', $ts !== false ? (int)$ts : time());
    }
}

if (!function_exists('storage_adapter_mysql_read_landing_pages')) {
    function storage_adapter_mysql_read_landing_pages(): ?array
    {
        if (!storage_adapter_mysql_enabled('landing_pages')) {
            return null;
        }
        try {
            $stmt = db()->query('SELECT payload FROM ugrowth_landing_pages ORDER BY updated_at DESC, id DESC');
            $rows = $stmt ? $stmt->fetchAll() : [];
            $pages = [];
            foreach ($rows ?: [] as $row) {
                $decoded = json_decode((string)($row['payload'] ?? ''), true);
                if (is_array($decoded)) {
                    $pages[] = $decoded;
                }
            }
            return $pages;
        } catch (Throwable $e) {
            error_log('[LANDING_PAGE_DB_SELECT] ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('storage_adapter_mysql_replace_landing_pages')) {
    function storage_adapter_mysql_replace_landing_pages(array $pages): bool
    {
        if (!storage_adapter_mysql_enabled('landing_pages')) {
            return false;
        }
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM ugrowth_landing_pages');
            $stmt = $pdo->prepare('INSERT INTO ugrowth_landing_pages (slug, title, status, payload, created_at, updated_at) VALUES (:slug, :title, :status, :payload, :created_at, :updated_at)');
            foreach ($pages as $page) {
                if (!is_array($page)) {
                    continue;
                }
                $slug = function_exists('slugify') ? slugify((string)($page['slug'] ?? $page['title'] ?? 'landing-page')) : preg_replace('/[^a-z0-9-]+/i', '-', strtolower((string)($page['slug'] ?? $page['title'] ?? 'landing-page')));
                $slug = trim((string)$slug, '-') ?: ('landing-page-' . substr(hash('sha1', json_encode($page) ?: uniqid('', true)), 0, 10));
                $title = trim((string)($page['title'] ?? 'Landing Page')) ?: 'Landing Page';
                $status = trim((string)($page['status'] ?? 'draft')) ?: 'draft';
                $json = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json === false) {
                    continue;
                }
                $stmt->execute([
                    ':slug' => substr($slug, 0, 190),
                    ':title' => substr($title, 0, 255),
                    ':status' => substr($status, 0, 40),
                    ':payload' => $json,
                    ':created_at' => storage_adapter_sql_datetime($page['created_at'] ?? null),
                    ':updated_at' => storage_adapter_sql_datetime($page['updated_at'] ?? null),
                ]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[LANDING_PAGE_DB_REPLACE] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('storage_adapter_mysql_upsert_landing_page_record')) {
    function storage_adapter_mysql_upsert_landing_page_record(array $page): bool
    {
        if (!function_exists('db_available') || !db_available() || !storage_adapter_table_exists('ugrowth_landing_pages')) {
            return false;
        }
        $slug = function_exists('slugify') ? slugify((string)($page['slug'] ?? $page['title'] ?? 'landing-page')) : preg_replace('/[^a-z0-9-]+/i', '-', strtolower((string)($page['slug'] ?? $page['title'] ?? 'landing-page')));
        $slug = trim((string)$slug, '-') ?: ('landing-page-' . substr(hash('sha1', json_encode($page) ?: uniqid('', true)), 0, 10));
        $title = trim((string)($page['title'] ?? 'Landing Page')) ?: 'Landing Page';
        $status = trim((string)($page['status'] ?? 'draft')) ?: 'draft';
        $json = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        try {
            $stmt = db()->prepare('INSERT INTO ugrowth_landing_pages (slug, title, status, payload, created_at, updated_at) VALUES (:slug, :title, :status, :payload, :created_at, :updated_at) ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status), payload = VALUES(payload), updated_at = VALUES(updated_at)');
            return $stmt->execute([
                ':slug' => substr($slug, 0, 190),
                ':title' => substr($title, 0, 255),
                ':status' => substr($status, 0, 40),
                ':payload' => $json,
                ':created_at' => storage_adapter_sql_datetime($page['created_at'] ?? null),
                ':updated_at' => storage_adapter_sql_datetime($page['updated_at'] ?? null),
            ]);
        } catch (Throwable $e) {
            error_log('[LANDING_PAGE_DB_UPSERT] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('storage_adapter_json_path')) {
    function storage_adapter_json_path(string $file): string
    {
        return STORAGE_PATH . '/' . ltrim($file, '/');
    }
}

if (!function_exists('storage_adapter_count_json_records')) {
    function storage_adapter_count_json_records(string $file, string $recordType = 'json-array'): int
    {
        if (str_contains($file, '*')) {
            $patterns = [];
            if (defined('LOGS_PATH')) { $patterns[] = LOGS_PATH . '/' . basename($file); }
            if (defined('STORAGE_PATH')) { $patterns[] = STORAGE_PATH . '/' . basename($file); }
            $count = 0;
            foreach ($patterns as $pattern) {
                foreach (glob($pattern) ?: [] as $candidate) {
                    if ($recordType === 'jsonl') {
                        $lines = @file($candidate, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        $count += is_array($lines) ? count($lines) : 0;
                    }
                }
            }
            return $count;
        }
        $path = storage_adapter_json_path($file);
        if (!is_file($path)) {
            if ($recordType === 'jsonl' && defined('LOGS_PATH')) {
                $logPath = LOGS_PATH . '/' . basename($file);
                if (is_file($logPath)) {
                    $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    return is_array($lines) ? count($lines) : 0;
                }
            }
            return 0;
        }
        if ($recordType === 'jsonl') {
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return is_array($lines) ? count($lines) : 0;
        }
        $decoded = json_decode((string)@file_get_contents($path), true);
        if (!is_array($decoded)) {
            return 0;
        }
        if (isset($decoded['pages']) && is_array($decoded['pages'])) {
            return count(array_filter($decoded['pages'], 'is_array'));
        }
        if (isset($decoded['items']) && is_array($decoded['items'])) {
            return count(array_filter($decoded['items'], 'is_array'));
        }
        if (isset($decoded['records']) && is_array($decoded['records'])) {
            return count(array_filter($decoded['records'], 'is_array'));
        }
        return count(array_filter($decoded, 'is_array'));
    }
}

if (!function_exists('storage_adapter_count_mysql_records')) {
    function storage_adapter_count_mysql_records(string $table): int
    {
        if (!storage_adapter_table_exists($table)) {
            return 0;
        }
        try {
            $stmt = db()->query('SELECT COUNT(*) FROM `' . str_replace('`', '', $table) . '`');
            return (int)($stmt ? $stmt->fetchColumn() : 0);
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('storage_adapter_collection_rows')) {
    function storage_adapter_collection_rows(): array
    {
        $rows = [];
        $settings = storage_adapter_settings();
        foreach (storage_adapter_default_collections() as $key => $meta) {
            $table = (string)($meta['mysql_table'] ?? '');
            $jsonFile = (string)($meta['json_file'] ?? '');
            $tableReady = storage_adapter_table_exists($table);
            $mysqlEnabled = !empty($settings['collections'][$key]['mysql_enabled']);
            $activeMysql = storage_adapter_mysql_enabled($key);
            $rows[] = array_merge($meta, [
                'key' => $key,
                'json_count' => storage_adapter_count_json_records($jsonFile, (string)($meta['record_type'] ?? 'json-array')),
                'json_exists' => is_file(storage_adapter_json_path($jsonFile)),
                'mysql_table_ready' => $tableReady,
                'mysql_count' => $tableReady ? storage_adapter_count_mysql_records($table) : 0,
                'mysql_enabled' => $mysqlEnabled,
                'active_mode' => $activeMysql ? 'mysql' : 'file',
                'status' => $activeMysql ? 'MySQL aktif' : ($mysqlEnabled ? 'Menunggu tabel/koneksi' : 'File aktif'),
            ]);
        }
        return $rows;
    }
}

if (!function_exists('storage_adapter_report')) {
    function storage_adapter_report(): array
    {
        $settings = storage_adapter_settings();
        $rows = storage_adapter_collection_rows();
        $activeMysql = count(array_filter($rows, static fn(array $row): bool => ($row['active_mode'] ?? '') === 'mysql'));
        $readyTables = count(array_filter($rows, static fn(array $row): bool => !empty($row['mysql_table_ready'])));
        $jsonRecords = array_sum(array_map(static fn(array $row): int => (int)($row['json_count'] ?? 0), $rows));
        $mysqlRecords = array_sum(array_map(static fn(array $row): int => (int)($row['mysql_count'] ?? 0), $rows));

        return [
            'schema' => 'ugrowth-storage-report-v1',
            'driver' => (string)($settings['driver'] ?? 'file'),
            'db_configured' => function_exists('db_configured') ? db_configured() : false,
            'db_available' => function_exists('db_available') ? db_available() : false,
            'safe_fallback' => !empty($settings['safe_fallback']),
            'collections' => $rows,
            'summary' => [
                'collection_total' => count($rows),
                'mysql_active_collections' => $activeMysql,
                'mysql_ready_tables' => $readyTables,
                'json_records' => $jsonRecords,
                'mysql_records' => $mysqlRecords,
            ],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('storage_adapter_read_json')) {
    function storage_adapter_read_json(string $file, array $default = []): array
    {
        $path = storage_adapter_json_path($file);
        if (!is_file($path)) {
            return $default;
        }
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : $default;
    }
}

if (!function_exists('storage_adapter_write_json')) {
    function storage_adapter_write_json(string $file, array $records): bool
    {
        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            return false;
        }
        $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json !== false && @file_put_contents(storage_adapter_json_path($file), $json . PHP_EOL, LOCK_EX) !== false;
    }
}

if (!function_exists('storage_adapter_append_jsonl')) {
    function storage_adapter_append_jsonl(string $file, array $record): bool
    {
        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            return false;
        }
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json !== false && @file_put_contents(storage_adapter_json_path($file), $json . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }
}


/*
|--------------------------------------------------------------------------
| MYSQL MIGRATION TOOL FOUNDATION
|--------------------------------------------------------------------------
| Safe migration helpers preview and copy file/JSON/JSONL records into the
| generic MySQL bridge table without forcing runtime reads. Runtime modules
| use MySQL only when admin enables the related collection from Storage &
| Database.
|--------------------------------------------------------------------------
*/

if (!function_exists('storage_adapter_migration_backup_dir')) {
    function storage_adapter_migration_backup_dir(): string
    {
        return STORAGE_PATH . '/backups/mysql-migration';
    }
}

if (!function_exists('storage_adapter_collection_meta')) {
    function storage_adapter_collection_meta(string $collection): ?array
    {
        $collections = storage_adapter_default_collections();
        return $collections[$collection] ?? null;
    }
}

if (!function_exists('storage_adapter_allowed_migration_collection')) {
    function storage_adapter_allowed_migration_collection(string $collection): bool
    {
        return storage_adapter_collection_meta($collection) !== null;
    }
}

if (!function_exists('storage_adapter_table_columns')) {
    function storage_adapter_table_columns(string $table): array
    {
        static $cache = [];
        $table = trim($table);
        if ($table === '' || !function_exists('db_available') || !db_available() || !storage_adapter_table_exists($table)) {
            return [];
        }
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        try {
            $stmt = db()->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
            $columns = [];
            foreach (($stmt ? $stmt->fetchAll() : []) as $row) {
                $field = (string)($row['Field'] ?? '');
                if ($field !== '') {
                    $columns[] = $field;
                }
            }
            return $cache[$table] = $columns;
        } catch (Throwable $e) {
            return $cache[$table] = [];
        }
    }
}

if (!function_exists('storage_adapter_schema_status')) {
    function storage_adapter_schema_status(): array
    {
        $required = [
            'ugrowth_storage_records' => ['collection', 'record_key', 'record_payload', 'status'],
            'ugrowth_storage_migrations' => ['migration_key', 'collection', 'status', 'migrated_records'],
            'ugrowth_sync_jobs' => ['sync_target', 'collection', 'status'],
        ];
        $typed = [
            'products' => ['id', 'slug', 'title'],
            'articles' => ['id', 'slug', 'title', 'content'],
            'ugrowth_landing_pages' => ['slug', 'title', 'status', 'payload'],
            'ugrowth_custom_forms' => [],
            'ugrowth_form_submissions' => ['form_slug', 'payload', 'created_at'],
            'ugrowth_inquiries' => ['inquiry_ref', 'payload', 'created_at'],
            'ugrowth_orders' => ['order_ref', 'payload', 'created_at'],
            'ugrowth_order_items' => ['order_ref', 'product_slug', 'payload'],
            'ugrowth_payment_proofs' => ['order_ref', 'status', 'payload', 'created_at'],
            'ugrowth_buyer_accounts' => [],
            'ugrowth_member_access' => [],
            'ugrowth_analytics_events' => ['event_name', 'source', 'page_path', 'payload', 'created_at'],
            'ugrowth_email_logs' => ['recipient', 'subject', 'status', 'payload', 'created_at'],
            'ugrowth_activity_logs' => ['actor', 'action', 'target', 'payload', 'created_at'],
        ];

        $rows = [];
        foreach ($required as $table => $columns) {
            $exists = storage_adapter_table_exists($table);
            $availableColumns = $exists ? storage_adapter_table_columns($table) : [];
            $missing = array_values(array_diff($columns, $availableColumns));
            $rows[$table] = [
                'table' => $table,
                'required' => true,
                'exists' => $exists,
                'missing_columns' => $missing,
                'ready' => $exists && !$missing,
                'row_count' => $exists ? storage_adapter_count_mysql_records($table) : 0,
            ];
        }
        foreach ($typed as $table => $columns) {
            $exists = storage_adapter_table_exists((string)$table);
            $availableColumns = $exists ? storage_adapter_table_columns((string)$table) : [];
            $missing = $columns ? array_values(array_diff($columns, $availableColumns)) : [];
            $rows[$table] = [
                'table' => (string)$table,
                'required' => false,
                'exists' => $exists,
                'missing_columns' => $missing,
                'ready' => $exists && !$missing,
                'row_count' => $exists ? storage_adapter_count_mysql_records((string)$table) : 0,
            ];
        }
        return $rows;
    }
}

if (!function_exists('storage_adapter_generic_bridge_ready')) {
    function storage_adapter_generic_bridge_ready(): bool
    {
        $schema = storage_adapter_schema_status();
        foreach (['ugrowth_storage_records', 'ugrowth_storage_migrations'] as $table) {
            if (empty($schema[$table]['ready'])) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('storage_adapter_read_jsonl_records')) {
    function storage_adapter_read_jsonl_records(string $file): array
    {
        $path = storage_adapter_json_path($file);
        if (!is_file($path)) {
            return [];
        }
        $records = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }
        foreach ($lines as $index => $line) {
            $decoded = json_decode((string)$line, true);
            if (is_array($decoded)) {
                $records[] = $decoded;
            } else {
                $records[] = ['message' => trim((string)$line), '_raw_line' => true, '_line' => $index + 1];
            }
        }
        return $records;
    }
}


if (!function_exists('storage_adapter_read_jsonl_log_pattern')) {
    function storage_adapter_read_jsonl_log_pattern(string $basenamePattern): array
    {
        $records = [];
        $patterns = [];
        if (defined('LOGS_PATH')) { $patterns[] = LOGS_PATH . '/' . ltrim($basenamePattern, '/'); }
        if (defined('STORAGE_PATH')) { $patterns[] = STORAGE_PATH . '/' . ltrim($basenamePattern, '/'); }
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (!is_array($lines)) { continue; }
                foreach ($lines as $index => $line) {
                    $decoded = json_decode((string)$line, true);
                    if (is_array($decoded)) {
                        $records[] = $decoded;
                    } else {
                        $records[] = ['message' => trim((string)$line), '_raw_line' => true, '_line' => $index + 1, '_source_file' => basename($file)];
                    }
                }
            }
        }
        return $records;
    }
}

if (!function_exists('storage_adapter_collection_records')) {
    function storage_adapter_collection_records(string $collection): array
    {
        $meta = storage_adapter_collection_meta($collection);
        if (!$meta) {
            return [];
        }

        if ($collection === 'products') {
            $records = [];
            if (function_exists('product_seed_products')) {
                $records = array_merge($records, product_seed_products());
            } elseif (defined('DATA_PATH') && is_file(DATA_PATH . '/products.php')) {
                $seed = require DATA_PATH . '/products.php';
                if (is_array($seed)) {
                    $records = array_merge($records, array_values(array_filter($seed, 'is_array')));
                }
            }
            $stored = storage_adapter_read_json('products.json', []);
            if (is_array($stored)) {
                $records = array_merge($records, array_values(array_filter($stored, 'is_array')));
            }
            if (function_exists('product_dedupe_by_slug')) {
                $records = product_dedupe_by_slug($records);
            }
            return array_values(array_filter($records, 'is_array'));
        }

        if ($collection === 'articles') {
            $records = [];
            if (function_exists('article_seed_articles')) {
                $records = array_merge($records, article_seed_articles());
            } elseif (defined('DATA_PATH') && is_file(DATA_PATH . '/articles.php')) {
                $seed = require DATA_PATH . '/articles.php';
                if (is_array($seed)) {
                    $records = array_merge($records, array_values(array_filter($seed, 'is_array')));
                }
            }
            $stored = storage_adapter_read_json('articles.json', []);
            if (is_array($stored)) {
                $records = array_merge($records, array_values(array_filter($stored, 'is_array')));
            }
            if (function_exists('article_dedupe_by_slug')) {
                $records = article_dedupe_by_slug($records);
            }
            return array_values(array_filter($records, 'is_array'));
        }

        if ($collection === 'landing_pages') {
            if (function_exists('landing_page_read_raw')) {
                return array_values(array_filter(landing_page_read_raw(), 'is_array'));
            }
            $records = storage_adapter_read_json('landing-pages.json', []);
            if (isset($records['pages']) && is_array($records['pages'])) {
                return array_values(array_filter($records['pages'], 'is_array'));
            }
            return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
        }

        if ($collection === 'orders' && defined('LOGS_PATH')) {
            $records = [];
            foreach (glob(LOGS_PATH . '/orders-*.jsonl') ?: [] as $logFile) {
                $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (!is_array($lines)) {
                    continue;
                }
                foreach ($lines as $line) {
                    $decoded = json_decode((string)$line, true);
                    if (is_array($decoded)) {
                        $records[] = $decoded;
                    }
                }
            }
            return $records;
        }

        if ($collection === 'form_submissions' && defined('LOGS_PATH')) {
            return storage_adapter_read_jsonl_log_pattern('custom-form-submissions-*.jsonl');
        }

        if ($collection === 'inquiries' && defined('LOGS_PATH')) {
            return storage_adapter_read_jsonl_log_pattern('inquiries-*.jsonl');
        }

        if ($collection === 'payment_proofs' && defined('LOGS_PATH')) {
            return storage_adapter_read_jsonl_log_pattern('payment-proofs-*.jsonl');
        }

        if ($collection === 'analytics_events' && defined('LOGS_PATH')) {
            return storage_adapter_read_jsonl_log_pattern('lead-events-*.jsonl');
        }

        if ($collection === 'email_logs' && defined('LOGS_PATH')) {
            return storage_adapter_read_jsonl_log_pattern('email-events-*.jsonl');
        }

        if ($collection === 'activity_logs') {
            $path = STORAGE_PATH . '/admin-activity.log';
            if (!is_file($path)) { return []; }
            $records = [];
            $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach (is_array($lines) ? $lines : [] as $index => $line) {
                $decoded = json_decode((string)$line, true);
                if (is_array($decoded)) {
                    $records[] = $decoded;
                } else {
                    $records[] = ['message' => trim((string)$line), '_raw_line' => true, '_line' => $index + 1];
                }
            }
            return $records;
        }

        $file = (string)($meta['json_file'] ?? '');
        $type = (string)($meta['record_type'] ?? 'json-array');
        if ($file === '') {
            return [];
        }
        if ($type === 'jsonl') {
            return storage_adapter_read_jsonl_records($file);
        }
        $records = storage_adapter_read_json($file, []);
        return is_array($records) ? array_values(array_filter($records, 'is_array')) : [];
    }
}

if (!function_exists('storage_adapter_record_key')) {
    function storage_adapter_record_key(string $collection, array $record, int $index = 0): string
    {
        foreach (['id', 'slug', 'ref', 'order_ref', 'email', 'message_id', 'key', 'license_key'] as $field) {
            $value = trim((string)($record[$field] ?? ''));
            if ($value !== '') {
                return substr($collection . ':' . preg_replace('/[^a-zA-Z0-9_.:@-]+/', '-', $value), 0, 190);
            }
        }
        $hash = substr(hash('sha256', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ($collection . ':' . $index)), 0, 24);
        return $collection . ':row-' . ($index + 1) . '-' . $hash;
    }
}

if (!function_exists('storage_adapter_record_ref')) {
    function storage_adapter_record_ref(array $record): string
    {
        foreach (['slug', 'ref', 'order_ref', 'email', 'title', 'name', 'phone'] as $field) {
            $value = trim((string)($record[$field] ?? ''));
            if ($value !== '') {
                return substr($value, 0, 190);
            }
        }
        return '';
    }
}

if (!function_exists('storage_adapter_preview_collection_migration')) {
    function storage_adapter_preview_collection_migration(string $collection): array
    {
        $meta = storage_adapter_collection_meta($collection);
        if (!$meta) {
            return ['ok' => false, 'message' => 'Collection tidak dikenali.', 'records' => 0, 'samples' => []];
        }
        $records = storage_adapter_collection_records($collection);
        $samples = [];
        foreach (array_slice($records, 0, 5) as $i => $record) {
            $samples[] = [
                'key' => storage_adapter_record_key($collection, $record, $i),
                'ref' => storage_adapter_record_ref($record),
                'title' => (string)($record['title'] ?? $record['name'] ?? $record['email'] ?? $record['ref'] ?? $record['order_ref'] ?? '-'),
            ];
        }
        return [
            'ok' => true,
            'collection' => $collection,
            'label' => (string)($meta['label'] ?? $collection),
            'file' => (string)($meta['json_file'] ?? ''),
            'record_type' => (string)($meta['record_type'] ?? 'json-array'),
            'records' => count($records),
            'samples' => $samples,
            'generic_bridge_ready' => storage_adapter_generic_bridge_ready(),
            'db_available' => function_exists('db_available') && db_available(),
        ];
    }
}

if (!function_exists('storage_adapter_create_migration_backup')) {
    function storage_adapter_create_migration_backup(string $collection): string
    {
        $meta = storage_adapter_collection_meta($collection);
        if (!$meta) {
            throw new RuntimeException('Collection tidak dikenali.');
        }
        $file = (string)($meta['json_file'] ?? '');
        $source = storage_adapter_json_path($file);
        if (!is_file($source)) {
            return '';
        }
        $dir = storage_adapter_migration_backup_dir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Folder backup migrasi belum bisa dibuat.');
        }
        $safe = preg_replace('/[^a-z0-9_.-]+/i', '-', $collection . '-' . basename($file));
        $target = $dir . '/' . date('Ymd-His') . '-' . $safe;
        if (!copy($source, $target)) {
            throw new RuntimeException('Backup file sumber migrasi gagal dibuat.');
        }
        @chmod($target, 0644);
        return $target;
    }
}

if (!function_exists('storage_adapter_migration_history')) {
    function storage_adapter_migration_history(int $limit = 20): array
    {
        if (!function_exists('db_available') || !db_available() || !storage_adapter_table_exists('ugrowth_storage_migrations')) {
            return [];
        }
        try {
            $stmt = db()->prepare('SELECT * FROM ugrowth_storage_migrations ORDER BY id DESC LIMIT :limit');
            $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}


if (!function_exists('storage_adapter_typed_migration_table')) {
    function storage_adapter_typed_migration_table(string $collection): string
    {
        return match ($collection) {
            'products' => 'products',
            'articles' => 'articles',
            'landing_pages' => 'ugrowth_landing_pages',
            'form_submissions' => 'ugrowth_form_submissions',
            'inquiries' => 'ugrowth_inquiries',
            'orders' => 'ugrowth_orders',
            'payment_proofs' => 'ugrowth_payment_proofs',
            'analytics_events' => 'ugrowth_analytics_events',
            'email_logs' => 'ugrowth_email_logs',
            'activity_logs' => 'ugrowth_activity_logs',
            default => '',
        };
    }
}

if (!function_exists('storage_adapter_typed_migration_ready')) {
    function storage_adapter_typed_migration_ready(string $collection): bool
    {
        $table = storage_adapter_typed_migration_table($collection);
        if ($table === '') {
            return false;
        }
        $schema = storage_adapter_schema_status();
        return !empty($schema[$table]['ready']);
    }
}

if (!function_exists('storage_adapter_run_products_typed_migration')) {
    function storage_adapter_run_products_typed_migration(array $records): array
    {
        if (!function_exists('product_db_payload') || !function_exists('product_db_insert_fields') || !function_exists('product_db_update_fields')) {
            throw new RuntimeException('Helper produk MySQL belum termuat.');
        }
        $fields = product_db_insert_fields();
        if (!$fields || !in_array('id', $fields, true)) {
            throw new RuntimeException('Tabel products belum siap menerima field id. Import database.sql terbaru terlebih dahulu.');
        }
        $updateFields = array_values(array_filter(product_db_update_fields(), static fn(string $field): bool => $field !== 'id'));
        $cols = implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $fields));
        $holders = ':' . implode(', :', $fields);
        $updates = $updateFields ? implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = VALUES(`' . $field . '`)', $updateFields)) : '`updated_at` = VALUES(`updated_at`)';
        $stmt = db()->prepare("INSERT INTO products ($cols) VALUES ($holders) ON DUPLICATE KEY UPDATE $updates");
        $migrated = 0;
        $skipped = 0;
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                $skipped++;
                continue;
            }
            $record['id'] = (int)($record['id'] ?? 0) > 0 ? (int)$record['id'] : (time() + $index + 1);
            $data = product_db_payload($record);
            foreach ($fields as $field) {
                $stmt->bindValue(':' . $field, $data[$field] ?? null);
            }
            try {
                $stmt->execute();
                $migrated++;
            } catch (Throwable $e) {
                error_log('[PRODUCT_TYPED_MIGRATION] ' . $e->getMessage());
                $skipped++;
            }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_articles_typed_migration')) {
    function storage_adapter_run_articles_typed_migration(array $records): array
    {
        if (!function_exists('normalize_article') || !function_exists('article_db_payload')) {
            throw new RuntimeException('Helper artikel MySQL belum termuat.');
        }
        $fields = array_keys(article_db_payload(normalize_article(['title' => 'Preview', 'content' => 'Preview'])));
        $cols = implode(', ', array_map(static fn(string $field): string => '`' . $field . '`', $fields));
        $holders = ':' . implode(', :', $fields);
        $updates = implode(', ', array_map(static fn(string $field): string => '`' . $field . '` = VALUES(`' . $field . '`)', array_filter($fields, static fn(string $field): bool => $field !== 'slug')));
        $stmt = db()->prepare("INSERT INTO articles ($cols) VALUES ($holders) ON DUPLICATE KEY UPDATE $updates");
        $migrated = 0;
        $skipped = 0;
        foreach ($records as $record) {
            if (!is_array($record)) {
                $skipped++;
                continue;
            }
            $article = normalize_article($record);
            $payload = article_db_payload($article);
            foreach ($fields as $field) {
                $stmt->bindValue(':' . $field, $payload[$field] ?? null);
            }
            try {
                $stmt->execute();
                $migrated++;
            } catch (Throwable $e) {
                error_log('[ARTICLE_TYPED_MIGRATION] ' . $e->getMessage());
                $skipped++;
            }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_landing_pages_typed_migration')) {
    function storage_adapter_run_landing_pages_typed_migration(array $records): array
    {
        $migrated = 0;
        $skipped = 0;
        foreach ($records as $record) {
            if (!is_array($record)) {
                $skipped++;
                continue;
            }
            $page = function_exists('landing_page_normalize') ? landing_page_normalize($record) : $record;
            if (storage_adapter_mysql_upsert_landing_page_record($page)) {
                $migrated++;
            } else {
                $skipped++;
            }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}


if (!function_exists('storage_adapter_run_form_submissions_typed_migration')) {
    function storage_adapter_run_form_submissions_typed_migration(array $records): array
    {
        $migrated = 0; $skipped = 0;
        foreach ($records as $record) {
            if (is_array($record) && storage_adapter_mysql_append_form_submission($record, true)) { $migrated++; } else { $skipped++; }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_inquiries_typed_migration')) {
    function storage_adapter_run_inquiries_typed_migration(array $records): array
    {
        $migrated = 0; $skipped = 0;
        foreach ($records as $record) {
            if (is_array($record) && storage_adapter_mysql_append_inquiry($record, true)) { $migrated++; } else { $skipped++; }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_orders_typed_migration')) {
    function storage_adapter_run_orders_typed_migration(array $records): array
    {
        $migrated = 0; $skipped = 0;
        foreach ($records as $record) {
            if (is_array($record) && storage_adapter_mysql_append_order($record, true)) { $migrated++; } else { $skipped++; }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_payment_proofs_typed_migration')) {
    function storage_adapter_run_payment_proofs_typed_migration(array $records): array
    {
        $migrated = 0; $skipped = 0;
        foreach ($records as $record) {
            if (is_array($record) && storage_adapter_mysql_append_payment_proof($record, true)) { $migrated++; } else { $skipped++; }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_analytics_events_typed_migration')) {
    function storage_adapter_run_analytics_events_typed_migration(array $records): array
    {
        $migrated = 0; $skipped = 0;
        foreach ($records as $record) {
            if (is_array($record) && storage_adapter_mysql_append_analytics_event($record, true)) { $migrated++; } else { $skipped++; }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_email_logs_typed_migration')) {
    function storage_adapter_run_email_logs_typed_migration(array $records): array
    {
        $migrated = 0; $skipped = 0;
        foreach ($records as $record) {
            if (is_array($record) && storage_adapter_mysql_append_email_log($record, true)) { $migrated++; } else { $skipped++; }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_activity_logs_typed_migration')) {
    function storage_adapter_run_activity_logs_typed_migration(array $records): array
    {
        $migrated = 0; $skipped = 0;
        foreach ($records as $record) {
            if (is_array($record) && storage_adapter_mysql_append_activity_log($record, true)) { $migrated++; } else { $skipped++; }
        }
        return ['migrated' => $migrated, 'skipped' => $skipped];
    }
}

if (!function_exists('storage_adapter_run_typed_migration')) {
    function storage_adapter_run_typed_migration(string $collection, bool $makeBackup = true): array
    {
        if (!storage_adapter_allowed_migration_collection($collection)) {
            throw new RuntimeException('Collection migrasi tidak valid.');
        }
        if (!function_exists('db_available') || !db_available()) {
            throw new RuntimeException('Koneksi MySQL belum tersedia. File-based tetap aman.');
        }
        if (!storage_adapter_typed_migration_ready($collection)) {
            throw new RuntimeException('Tabel runtime MySQL untuk collection ini belum siap. Import database.sql dan database/mysql-storage-schema.sql terlebih dahulu.');
        }

        $records = storage_adapter_collection_records($collection);
        $total = count($records);
        $backup = $makeBackup ? storage_adapter_create_migration_backup($collection) : '';
        $migrationKey = 'file-to-mysql-runtime-' . $collection . '-' . date('YmdHis');
        $started = date('Y-m-d H:i:s');
        $logReady = storage_adapter_table_exists('ugrowth_storage_migrations');

        if ($logReady) {
            $stmtLog = db()->prepare('INSERT INTO ugrowth_storage_migrations (migration_key, collection, source_driver, target_driver, total_records, migrated_records, skipped_records, status, notes, started_at) VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?)');
            $stmtLog->execute([$migrationKey, $collection, 'file', 'mysql-runtime-table', $total, 'running', $backup !== '' ? 'Backup: ' . basename($backup) : 'Tanpa backup file sumber.', $started]);
        }

        if ($collection === 'products') {
            $result = storage_adapter_run_products_typed_migration($records);
        } elseif ($collection === 'articles') {
            $result = storage_adapter_run_articles_typed_migration($records);
        } elseif ($collection === 'landing_pages') {
            $result = storage_adapter_run_landing_pages_typed_migration($records);
        } elseif ($collection === 'form_submissions') {
            $result = storage_adapter_run_form_submissions_typed_migration($records);
        } elseif ($collection === 'inquiries') {
            $result = storage_adapter_run_inquiries_typed_migration($records);
        } elseif ($collection === 'orders') {
            $result = storage_adapter_run_orders_typed_migration($records);
        } elseif ($collection === 'payment_proofs') {
            $result = storage_adapter_run_payment_proofs_typed_migration($records);
        } elseif ($collection === 'analytics_events') {
            $result = storage_adapter_run_analytics_events_typed_migration($records);
        } elseif ($collection === 'email_logs') {
            $result = storage_adapter_run_email_logs_typed_migration($records);
        } elseif ($collection === 'activity_logs') {
            $result = storage_adapter_run_activity_logs_typed_migration($records);
        } else {
            throw new RuntimeException('Collection ini belum memiliki migrasi typed.');
        }

        if ($logReady) {
            $stmtDone = db()->prepare('UPDATE ugrowth_storage_migrations SET migrated_records = ?, skipped_records = ?, status = ?, notes = CONCAT(COALESCE(notes, ""), ?), finished_at = ?, updated_at = NOW() WHERE migration_key = ?');
            $stmtDone->execute([(int)$result['migrated'], (int)$result['skipped'], 'completed', '\nSelesai migrasi runtime table.', date('Y-m-d H:i:s'), $migrationKey]);
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('create', 'storage-migration', null, 'Migrasi file ke MySQL runtime selesai.', ['collection' => $collection, 'total' => $total, 'migrated' => (int)$result['migrated']]);
        }

        return [
            'ok' => true,
            'migration_key' => $migrationKey,
            'collection' => $collection,
            'target' => storage_adapter_typed_migration_table($collection),
            'total_records' => $total,
            'migrated_records' => (int)$result['migrated'],
            'skipped_records' => (int)$result['skipped'],
            'backup_file' => $backup,
        ];
    }
}

if (!function_exists('storage_adapter_run_collection_migration')) {
    function storage_adapter_run_collection_migration(string $collection, bool $makeBackup = true): array
    {
        if (storage_adapter_typed_migration_table($collection) !== '') {
            return storage_adapter_run_typed_migration($collection, $makeBackup);
        }
        return storage_adapter_run_generic_migration($collection, $makeBackup);
    }
}

if (!function_exists('storage_adapter_run_generic_migration')) {
    function storage_adapter_run_generic_migration(string $collection, bool $makeBackup = true): array
    {
        if (!storage_adapter_allowed_migration_collection($collection)) {
            throw new RuntimeException('Collection migrasi tidak valid.');
        }
        if (!function_exists('db_available') || !db_available()) {
            throw new RuntimeException('Koneksi MySQL belum tersedia. File-based tetap aman.');
        }
        if (!storage_adapter_generic_bridge_ready()) {
            throw new RuntimeException('Tabel bridge migrasi belum siap. Import database/mysql-storage-schema.sql terlebih dahulu.');
        }

        $records = storage_adapter_collection_records($collection);
        $total = count($records);
        $backup = $makeBackup ? storage_adapter_create_migration_backup($collection) : '';
        $migrationKey = 'file-to-mysql-' . $collection . '-' . date('YmdHis');
        $migrated = 0;
        $skipped = 0;
        $started = date('Y-m-d H:i:s');

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmtLog = $pdo->prepare('INSERT INTO ugrowth_storage_migrations (migration_key, collection, source_driver, target_driver, total_records, migrated_records, skipped_records, status, notes, started_at) VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, ?)');
            $stmtLog->execute([$migrationKey, $collection, 'file', 'mysql-generic-bridge', $total, 'running', $backup !== '' ? 'Backup: ' . basename($backup) : 'Tanpa backup file sumber.', $started]);

            $stmt = $pdo->prepare('INSERT INTO ugrowth_storage_records (collection, record_key, record_ref, record_payload, source, status, created_at, updated_at) VALUES (:collection, :record_key, :record_ref, :record_payload, :source, :status, NOW(), NOW()) ON DUPLICATE KEY UPDATE record_ref = VALUES(record_ref), record_payload = VALUES(record_payload), source = VALUES(source), status = VALUES(status), updated_at = NOW()');

            foreach ($records as $index => $record) {
                if (!is_array($record)) {
                    $skipped++;
                    continue;
                }
                $payload = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($payload === false) {
                    $skipped++;
                    continue;
                }
                $stmt->execute([
                    ':collection' => $collection,
                    ':record_key' => storage_adapter_record_key($collection, $record, $index),
                    ':record_ref' => storage_adapter_record_ref($record) ?: null,
                    ':record_payload' => $payload,
                    ':source' => 'file-migration',
                    ':status' => 'active',
                ]);
                $migrated++;
            }

            $stmtDone = $pdo->prepare('UPDATE ugrowth_storage_migrations SET migrated_records = ?, skipped_records = ?, status = ?, notes = CONCAT(COALESCE(notes, ""), ?), finished_at = ?, updated_at = NOW() WHERE migration_key = ?');
            $stmtDone->execute([$migrated, $skipped, 'completed', '\nSelesai migrasi generic bridge.', date('Y-m-d H:i:s'), $migrationKey]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('create', 'storage-migration', null, 'Migrasi file ke MySQL bridge selesai.', ['collection' => $collection, 'total' => $total, 'migrated' => $migrated]);
        }

        return [
            'ok' => true,
            'migration_key' => $migrationKey,
            'collection' => $collection,
            'total_records' => $total,
            'migrated_records' => $migrated,
            'skipped_records' => $skipped,
            'backup_file' => $backup,
        ];
    }
}
