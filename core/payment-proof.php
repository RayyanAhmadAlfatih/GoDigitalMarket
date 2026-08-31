<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| PAYMENT PROOF ENGINE - Template
|--------------------------------------------------------------------------
| Lightweight file-based payment proof upload and admin verification flow.
| This keeps manual transfer/QRIS validation under admin control and does
| not act as a payment gateway.
|--------------------------------------------------------------------------
*/

if (!function_exists('payment_proof_enabled')) {
    function payment_proof_enabled(): bool
    {
        $value = strtolower(trim((string)($_ENV['ENABLE_PAYMENT_PROOF_UPLOAD'] ?? 'true')));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('payment_proof_clean')) {
    function payment_proof_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('payment_proof_multiline_clean')) {
    function payment_proof_multiline_clean(string $value, int $max = 800): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace("/\r\n|\r/", "\n", (string)$value);
        $value = preg_replace('/[ \t]+/', ' ', (string)$value);
        $value = preg_replace('/\n{3,}/', "\n\n", (string)$value);
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('payment_proof_phone_clean')) {
    function payment_proof_phone_clean(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($phone)) ?: '';
        return function_exists('mb_substr') ? mb_substr($phone, 0, 24) : substr($phone, 0, 24);
    }
}

if (!function_exists('payment_proof_storage_dir')) {
    function payment_proof_storage_dir(): string
    {
        return STORAGE_PATH . '/payment-proofs';
    }
}

if (!function_exists('payment_proof_log_file')) {
    function payment_proof_log_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/payment-proofs-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('payment_proof_status_file')) {
    function payment_proof_status_file(): string
    {
        return STORAGE_PATH . '/payment-proof-status.json';
    }
}

if (!function_exists('payment_proof_allowed_statuses')) {
    function payment_proof_allowed_statuses(): array
    {
        return ['Menunggu Review', 'Valid', 'Tidak Valid', 'Perlu Konfirmasi', 'DP Masuk', 'Lunas', 'Spam'];
    }
}

if (!function_exists('payment_proof_methods')) {
    function payment_proof_methods(): array
    {
        return ['Transfer Bank', 'QRIS Manual', 'Tunai', 'Lainnya'];
    }
}

if (!function_exists('payment_proof_normalize_status')) {
    function payment_proof_normalize_status(string $status): string
    {
        $status = payment_proof_clean($status, 60);
        return in_array($status, payment_proof_allowed_statuses(), true) ? $status : 'Menunggu Review';
    }
}

if (!function_exists('payment_proof_read_statuses')) {
    function payment_proof_read_statuses(): array
    {
        $file = payment_proof_status_file();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('payment_proof_write_statuses')) {
    function payment_proof_write_statuses(array $statuses): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        return @file_put_contents(
            payment_proof_status_file(),
            json_encode($statuses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        ) !== false;
    }
}

if (!function_exists('payment_proof_update_status')) {
    function payment_proof_update_status(string $id, string $status, string $note = ''): bool
    {
        $id = payment_proof_clean($id, 80);
        if ($id === '') {
            return false;
        }
        $statuses = payment_proof_read_statuses();
        $current = is_array($statuses[$id] ?? null) ? $statuses[$id] : [];
        $statuses[$id] = array_merge($current, [
            'status' => payment_proof_normalize_status($status),
            'admin_note' => payment_proof_multiline_clean($note, 500),
            'updated_at' => date('c'),
        ]);
        $ok = payment_proof_write_statuses($statuses);
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('update_status', 'payment_proof', $id, 'Status bukti pembayaran diperbarui.', [
                'status' => $statuses[$id]['status'] ?? '',
            ]);
        }
        return $ok;
    }
}

if (!function_exists('payment_proof_rate_limit_file')) {
    function payment_proof_rate_limit_file(): string
    {
        return CACHE_PATH . '/payment-proof-rate-limit.json';
    }
}

if (!function_exists('payment_proof_rate_limit_key')) {
    function payment_proof_rate_limit_key(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        return hash('sha256', $ip . '|' . $ua . '|' . (string)($_ENV['APP_KEY'] ?? 'payment-proof'));
    }
}

if (!function_exists('payment_proof_is_rate_limited')) {
    function payment_proof_is_rate_limited(): bool
    {
        $now = time();
        $lastSubmit = (int)($_SESSION['last_payment_proof_submit_at'] ?? 0);
        if ($lastSubmit > 0 && ($now - $lastSubmit) < 20) {
            return true;
        }
        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        $file = payment_proof_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = payment_proof_rate_limit_key();
        $bucket = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => ((int)$ts) > ($now - 3600)));
        return count($bucket) >= 6;
    }
}

if (!function_exists('payment_proof_touch_rate_limit')) {
    function payment_proof_touch_rate_limit(): void
    {
        $_SESSION['last_payment_proof_submit_at'] = time();
        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        $file = payment_proof_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = payment_proof_rate_limit_key();
        $data[$key] = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => ((int)$ts) > (time() - 3600)));
        $data[$key][] = time();
        if (count($data) > 500) {
            $data = array_slice($data, -500, null, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}

if (!function_exists('payment_proof_max_upload_bytes')) {
    function payment_proof_max_upload_bytes(): int
    {
        $mb = max(1, min(10, (int)($_ENV['PAYMENT_PROOF_MAX_MB'] ?? 5)));
        return $mb * 1024 * 1024;
    }
}

if (!function_exists('payment_proof_allowed_mimes')) {
    function payment_proof_allowed_mimes(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
    }
}

if (!function_exists('payment_proof_validate_payload')) {
    function payment_proof_validate_payload(array $payload, array $file, ?array $order = null): array
    {
        $errors = [];
        $name = payment_proof_clean((string)($payload['payer_name'] ?? ''), 80);
        $phone = payment_proof_phone_clean((string)($payload['payer_phone'] ?? ''));
        $amount = (int)(preg_replace('/[^0-9]/', '', (string)($payload['amount'] ?? '')) ?: 0);
        $method = payment_proof_clean((string)($payload['payment_method'] ?? ''), 80);
        $honeypot = trim((string)($payload['website'] ?? $payload['url'] ?? ''));

        if ($honeypot !== '') {
            $errors[] = 'Permintaan tidak dapat diproses.';
        }
        if (!$order) {
            $errors[] = 'Order/invoice tidak ditemukan atau token tidak valid.';
        }
        if (strlen($name) < 2) {
            $errors[] = 'Nama pengirim wajib diisi minimal 2 karakter.';
        }
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) < 8 || strlen($digits) > 16) {
            $errors[] = 'Nomor WhatsApp/telepon belum valid.';
        }
        if ($amount <= 0) {
            $errors[] = 'Nominal pembayaran wajib diisi.';
        }
        if ($method !== '' && !in_array($method, payment_proof_methods(), true)) {
            $errors[] = 'Metode pembayaran belum valid.';
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'File bukti pembayaran wajib diupload.';
        } elseif ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > payment_proof_max_upload_bytes()) {
            $errors[] = 'Ukuran file bukti pembayaran terlalu besar atau tidak valid.';
        }
        return $errors;
    }
}

if (!function_exists('payment_proof_store_file')) {
    function payment_proof_store_file(array $file, string $proofId): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            return ['ok' => false, 'message' => 'File bukti pembayaran tidak valid.'];
        }

        $tmp = (string)$file['tmp_name'];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $mime = (string)mime_content_type($tmp);
        }
        $allowed = payment_proof_allowed_mimes();
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, PNG, WebP, atau PDF.'];
        }
        if ($mime !== 'application/pdf' && @getimagesize($tmp) === false) {
            return ['ok' => false, 'message' => 'File gambar bukti pembayaran tidak valid.'];
        }

        $year = date('Y');
        $month = date('m');
        $dir = payment_proof_storage_dir() . '/' . $year . '/' . $month;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir)) {
            return ['ok' => false, 'message' => 'Folder penyimpanan bukti pembayaran belum bisa dibuat.'];
        }

        $ext = $allowed[$mime];
        $safeId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $proofId) ?: bin2hex(random_bytes(6));
        $name = 'proof-' . $safeId . '.' . $ext;
        $path = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $path)) {
            return ['ok' => false, 'message' => 'File bukti pembayaran belum bisa disimpan.'];
        }
        @chmod($path, 0644);

        $relative = $year . '/' . $month . '/' . $name;
        return [
            'ok' => true,
            'relative_path' => $relative,
            'mime' => $mime,
            'size' => (int)($file['size'] ?? 0),
            'original_name' => payment_proof_clean((string)($file['name'] ?? ''), 180),
        ];
    }
}

if (!function_exists('payment_proof_normalize_payload')) {
    function payment_proof_normalize_payload(array $payload, array $fileInfo, array $order): array
    {
        $id = 'payproof_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $amount = (int)(preg_replace('/[^0-9]/', '', (string)($payload['amount'] ?? '')) ?: 0);
        $method = payment_proof_clean((string)($payload['payment_method'] ?? ''), 80);
        if (!in_array($method, payment_proof_methods(), true)) {
            $method = 'Transfer Bank';
        }
        return [
            'id' => $id,
            'time' => date('c'),
            'status' => 'Menunggu Review',
            'order_id' => (string)($order['id'] ?? ''),
            'order_ref' => function_exists('order_public_reference') ? order_public_reference($order) : payment_proof_clean((string)($payload['ref'] ?? ''), 80),
            'order_token' => function_exists('order_public_token') ? order_public_token($order) : payment_proof_clean((string)($payload['token'] ?? ''), 80),
            'invoice_number' => function_exists('order_invoice_number') ? order_invoice_number($order) : '',
            'product_title' => payment_proof_clean((string)($order['product_title'] ?? ''), 140),
            'payer_name' => payment_proof_clean((string)($payload['payer_name'] ?? ''), 80),
            'payer_phone' => payment_proof_phone_clean((string)($payload['payer_phone'] ?? '')),
            'payer_email' => payment_proof_clean((string)($payload['payer_email'] ?? ($order['email'] ?? '')), 120),
            'amount' => $amount,
            'payment_method' => $method,
            'payment_channel' => payment_proof_clean((string)($payload['payment_channel'] ?? ''), 120),
            'note' => payment_proof_multiline_clean((string)($payload['note'] ?? ''), 700),
            'file_path' => (string)($fileInfo['relative_path'] ?? ''),
            'file_mime' => (string)($fileInfo['mime'] ?? ''),
            'file_size' => (int)($fileInfo['size'] ?? 0),
            'file_original_name' => (string)($fileInfo['original_name'] ?? ''),
            'source' => payment_proof_clean((string)($payload['source'] ?? 'public-invoice'), 80),
            'page_path' => payment_proof_clean((string)($payload['page_path'] ?? ($_SERVER['HTTP_REFERER'] ?? current_url())), 240),
            'ip_hash' => payment_proof_rate_limit_key(),
        ];
    }
}

if (!function_exists('payment_proof_store')) {
    function payment_proof_store(array $proof): bool
    {
        if (!payment_proof_enabled()) {
            return false;
        }
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }
        $mysqlOk = false;
        $mysqlActive = function_exists('storage_mysql_enabled') && storage_mysql_enabled('payment_proofs');
        if ($mysqlActive && function_exists('storage_adapter_mysql_append_payment_proof')) {
            $mysqlOk = storage_adapter_mysql_append_payment_proof($proof);
        }
        $line = json_encode($proof, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $fileOk = @file_put_contents(payment_proof_log_file(), $line, FILE_APPEND | LOCK_EX) !== false;
        if ($mysqlActive) {
            return $mysqlOk || (function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled() && $fileOk);
        }
        return $fileOk;
    }
}

if (!function_exists('payment_proof_event_timestamp')) {
    function payment_proof_event_timestamp(array $proof): int
    {
        $time = (string)($proof['time'] ?? '');
        $ts = $time !== '' ? strtotime($time) : false;
        return $ts !== false ? (int)$ts : 0;
    }
}

if (!function_exists('payment_proof_log_files')) {
    function payment_proof_log_files(int $days = 30, array $filters = []): array
    {
        if (!defined('LOGS_PATH') || !is_dir(LOGS_PATH)) {
            return [];
        }
        $files = glob(LOGS_PATH . '/payment-proofs-*.jsonl') ?: [];
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $startMonth = $startTs > 0 ? strtotime(date('Y-m-01 00:00:00', $startTs)) : null;
        $endMonth = $endTs > 0 ? strtotime(date('Y-m-01 00:00:00', $endTs)) : null;
        $files = array_values(array_filter($files, static function (string $file) use ($startMonth, $endMonth): bool {
            if (!preg_match('/payment-proofs-(\d{4})-(\d{2})\.jsonl$/', $file, $matches)) {
                return false;
            }
            $month = strtotime($matches[1] . '-' . $matches[2] . '-01 00:00:00') ?: 0;
            if ($startMonth !== null && $month < $startMonth) {
                return false;
            }
            if ($endMonth !== null && $month > $endMonth) {
                return false;
            }
            return true;
        }));
        rsort($files, SORT_STRING);
        return $files;
    }
}

if (!function_exists('payment_proof_matches_filters')) {
    function payment_proof_matches_filters(array $proof, array $filters = []): bool
    {
        foreach (['status', 'payment_method', 'order_ref', 'product_title'] as $key) {
            $filter = strtolower(trim((string)($filters[$key] ?? '')));
            if ($filter === '') {
                continue;
            }
            $value = strtolower(trim((string)($proof[$key] ?? '')));
            if ($value === '' || !str_contains($value, $filter)) {
                return false;
            }
        }
        $search = strtolower(trim((string)($filters['search'] ?? '')));
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array_map('strval', [
                $proof['order_ref'] ?? '',
                $proof['invoice_number'] ?? '',
                $proof['payer_name'] ?? '',
                $proof['payer_phone'] ?? '',
                $proof['product_title'] ?? '',
                $proof['note'] ?? '',
            ])));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('payment_proof_read_all')) {
    function payment_proof_read_all(int $days = 30, array $filters = [], int $max = 5000): array
    {
        $max = max(50, min(50000, $max));
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) {
            $days = 0;
        }
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $statuses = payment_proof_read_statuses();
        if (function_exists('storage_adapter_mysql_read_payment_proofs') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('payment_proofs')) {
            $mysqlProofs = storage_adapter_mysql_read_payment_proofs($days, $filters, $max);
            if (is_array($mysqlProofs)) {
                foreach ($mysqlProofs as &$mysqlProof) {
                    $id = (string)($mysqlProof['id'] ?? '');
                    if ($id !== '' && isset($statuses[$id])) {
                        $mysqlProof['status'] = (string)($statuses[$id]['status'] ?? ($mysqlProof['status'] ?? 'Menunggu Review'));
                        $mysqlProof['admin_note'] = (string)($statuses[$id]['admin_note'] ?? '');
                        $mysqlProof['status_updated_at'] = (string)($statuses[$id]['updated_at'] ?? '');
                    }
                }
                unset($mysqlProof);
                return $mysqlProofs;
            }
        }
        $proofs = [];
        foreach (payment_proof_log_files($days, $filters) as $file) {
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $proof = json_decode($line, true);
                if (!is_array($proof)) {
                    continue;
                }
                $ts = payment_proof_event_timestamp($proof);
                if ($ts <= 0) {
                    continue;
                }
                if ($startTs > 0 && $ts < $startTs) {
                    continue;
                }
                if ($endTs > 0 && $ts > $endTs) {
                    continue;
                }
                $id = (string)($proof['id'] ?? '');
                if ($id !== '' && isset($statuses[$id])) {
                    $proof['status'] = (string)($statuses[$id]['status'] ?? ($proof['status'] ?? 'Menunggu Review'));
                    $proof['admin_note'] = (string)($statuses[$id]['admin_note'] ?? '');
                    $proof['status_updated_at'] = (string)($statuses[$id]['updated_at'] ?? '');
                }
                if (!payment_proof_matches_filters($proof, $filters)) {
                    continue;
                }
                $proof['_ts'] = $ts;
                $proofs[] = $proof;
                if (count($proofs) >= $max) {
                    break 2;
                }
            }
            fclose($handle);
        }
        usort($proofs, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
        return $proofs;
    }
}

if (!function_exists('payment_proof_count_by')) {
    function payment_proof_count_by(array $proofs, string $key, int $limit = 8): array
    {
        $counts = [];
        foreach ($proofs as $proof) {
            $value = trim((string)($proof[$key] ?? '')) ?: 'Tidak diketahui';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('payment_proof_summary')) {
    function payment_proof_summary(int $days = 30, array $filters = []): array
    {
        $proofs = payment_proof_read_all($days, $filters, 20000);
        $today = date('Y-m-d');
        $todayCount = 0;
        $pending = 0;
        $valid = 0;
        $amount = 0;
        foreach ($proofs as $proof) {
            if (date('Y-m-d', (int)($proof['_ts'] ?? time())) === $today) {
                $todayCount++;
            }
            if (($proof['status'] ?? 'Menunggu Review') === 'Menunggu Review') {
                $pending++;
            }
            if (in_array((string)($proof['status'] ?? ''), ['Valid', 'DP Masuk', 'Lunas'], true)) {
                $valid++;
            }
            $amount += (int)($proof['amount'] ?? 0);
        }
        return [
            'total' => count($proofs),
            'today' => $todayCount,
            'pending' => $pending,
            'valid' => $valid,
            'amount' => $amount,
            'by_status' => payment_proof_count_by($proofs, 'status', 8),
            'by_method' => payment_proof_count_by($proofs, 'payment_method', 8),
            'recent' => array_slice($proofs, 0, 50),
        ];
    }
}

if (!function_exists('payment_proof_find_by_id')) {
    function payment_proof_find_by_id(string $id): ?array
    {
        $id = payment_proof_clean($id, 80);
        if ($id === '') {
            return null;
        }
        foreach (payment_proof_read_all(0, ['_all_time' => true], 50000) as $proof) {
            if ((string)($proof['id'] ?? '') === $id) {
                return $proof;
            }
        }
        return null;
    }
}

if (!function_exists('payment_proofs_for_order')) {
    function payment_proofs_for_order(array|string $order, int $limit = 5): array
    {
        $orderId = '';
        $orderRef = '';
        if (is_array($order)) {
            $orderId = (string)($order['id'] ?? '');
            $orderRef = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? '');
        } else {
            $orderRef = (string)$order;
        }
        $matches = [];
        foreach (payment_proof_read_all(0, ['_all_time' => true], 50000) as $proof) {
            if (($orderId !== '' && (string)($proof['order_id'] ?? '') === $orderId) || ($orderRef !== '' && (string)($proof['order_ref'] ?? '') === $orderRef)) {
                $matches[] = $proof;
                if (count($matches) >= $limit) {
                    break;
                }
            }
        }
        return $matches;
    }
}

if (!function_exists('payment_proof_file_url')) {
    function payment_proof_file_url(array $proof): string
    {
        $path = payment_proof_clean((string)($proof['file_path'] ?? ''), 200);
        return $path !== '' ? url('admin/payment-proof-file?file=' . rawurlencode($path)) : '';
    }
}

if (!function_exists('payment_proof_file_absolute_path')) {
    function payment_proof_file_absolute_path(string $relative): ?string
    {
        $relative = str_replace(['..', '\\'], ['', '/'], trim($relative, '/'));
        if ($relative === '' || !preg_match('#^[0-9]{4}/[0-9]{2}/[a-zA-Z0-9\-_.]+\.(jpg|jpeg|png|webp|pdf)$#i', $relative)) {
            return null;
        }
        $base = realpath(payment_proof_storage_dir());
        $path = realpath(payment_proof_storage_dir() . '/' . $relative);
        if (!$base || !$path || !str_starts_with($path, $base)) {
            return null;
        }
        return $path;
    }
}

if (!function_exists('payment_proof_whatsapp_message')) {
    function payment_proof_whatsapp_message(array $proof): string
    {
        $message = "Halo Admin, saya ingin follow-up bukti pembayaran.\n\n";
        $message .= 'No. Order: ' . (string)($proof['order_ref'] ?? '-') . "\n";
        $message .= 'No. Invoice: ' . (string)($proof['invoice_number'] ?? '-') . "\n";
        $message .= 'Nama: ' . (string)($proof['payer_name'] ?? '-') . "\n";
        $message .= 'Nominal: ' . rupiah((int)($proof['amount'] ?? 0)) . "\n";
        $message .= 'Metode: ' . (string)($proof['payment_method'] ?? '-') . "\n";
        $message .= 'Status review: ' . (string)($proof['status'] ?? 'Menunggu Review') . "\n\n";
        $message .= 'Mohon dibantu cek dan konfirmasi pembayaran saya.';
        return $message;
    }
}
