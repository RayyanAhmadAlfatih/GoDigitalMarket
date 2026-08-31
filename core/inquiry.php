<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| FORM INQUIRY / LEAD CAPTURE HELPERS - Template
|--------------------------------------------------------------------------
| Lightweight, file-based inquiry system for UMKM mini marketplace. Data is
| stored in monthly JSONL files and can be reviewed from the admin inbox.
|--------------------------------------------------------------------------
*/

if (!function_exists('inquiry_enabled')) {
    function inquiry_enabled(): bool
    {
        $value = strtolower(trim((string)($_ENV['ENABLE_INQUIRY_FORM'] ?? 'true')));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('inquiry_clean')) {
    function inquiry_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('inquiry_multiline_clean')) {
    function inquiry_multiline_clean(string $value, int $max = 1200): string
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

if (!function_exists('inquiry_phone_clean')) {
    function inquiry_phone_clean(string $phone): string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[^0-9+]/', '', $phone) ?: '';
        return function_exists('mb_substr') ? mb_substr($phone, 0, 24) : substr($phone, 0, 24);
    }
}

if (!function_exists('inquiry_log_file')) {
    function inquiry_log_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/inquiries-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('inquiry_status_file')) {
    function inquiry_status_file(): string
    {
        return STORAGE_PATH . '/inquiry-status.json';
    }
}

if (!function_exists('inquiry_read_statuses')) {
    function inquiry_read_statuses(): array
    {
        $file = inquiry_status_file();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('inquiry_write_statuses')) {
    function inquiry_write_statuses(array $statuses): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        return @file_put_contents(
            inquiry_status_file(),
            json_encode($statuses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        ) !== false;
    }
}

if (!function_exists('inquiry_update_status')) {
    function inquiry_update_status(string $id, string $status, string $note = ''): bool
    {
        $id = inquiry_clean($id, 80);
        $status = inquiry_clean($status, 40);
        $allowed = ['Baru', 'Dihubungi', 'Deal', 'Tidak Jadi', 'Spam'];
        if ($id === '' || !in_array($status, $allowed, true)) {
            return false;
        }
        $statuses = inquiry_read_statuses();
        $statuses[$id] = [
            'status' => $status,
            'note' => inquiry_multiline_clean($note, 400),
            'updated_at' => date('c'),
        ];
        return inquiry_write_statuses($statuses);
    }
}

if (!function_exists('inquiry_rate_limit_key')) {
    function inquiry_rate_limit_key(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        return hash('sha256', $ip . '|' . $ua . '|' . (string)($_ENV['APP_KEY'] ?? 'produk-inquiry'));
    }
}

if (!function_exists('inquiry_rate_limit_file')) {
    function inquiry_rate_limit_file(): string
    {
        return CACHE_PATH . '/inquiry-rate-limit.json';
    }
}

if (!function_exists('inquiry_is_rate_limited')) {
    function inquiry_is_rate_limited(): bool
    {
        $now = time();
        $lastSubmit = (int)($_SESSION['last_inquiry_submit_at'] ?? 0);
        if ($lastSubmit > 0 && ($now - $lastSubmit) < 15) {
            return true;
        }

        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }

        $file = inquiry_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = inquiry_rate_limit_key();
        $bucket = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => ((int)$ts) > (time() - 3600)));

        if (count($bucket) >= 8) {
            return true;
        }

        return false;
    }
}

if (!function_exists('inquiry_touch_rate_limit')) {
    function inquiry_touch_rate_limit(): void
    {
        $_SESSION['last_inquiry_submit_at'] = time();
        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        $file = inquiry_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = inquiry_rate_limit_key();
        $data[$key] = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => ((int)$ts) > (time() - 3600)));
        $data[$key][] = time();
        if (count($data) > 500) {
            $data = array_slice($data, -500, null, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}



if (!function_exists('inquiry_phone_for_whatsapp')) {
    function inquiry_phone_for_whatsapp(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }
        return $digits;
    }
}

if (!function_exists('inquiry_validate_payload')) {
    function inquiry_validate_payload(array $payload): array
    {
        $errors = [];
        $name = inquiry_clean((string)($payload['name'] ?? ''), 80);
        $phone = inquiry_phone_clean((string)($payload['phone'] ?? ''));
        $need = inquiry_clean((string)($payload['need'] ?? $payload['service'] ?? ''), 120);
        $message = inquiry_multiline_clean((string)($payload['message'] ?? ''), 1200);
        $honeypot = trim((string)($payload['website'] ?? $payload['url'] ?? ''));
        $email = inquiry_clean((string)($payload['email'] ?? ''), 120);
        $consent = (string)($payload['consent_contact'] ?? '') !== '';

        if ($honeypot !== '') {
            $errors[] = 'Permintaan tidak dapat diproses.';
        }
        if (strlen($name) < 2) {
            $errors[] = 'Nama wajib diisi minimal 2 karakter.';
        }
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) < 8 || strlen($digits) > 16) {
            $errors[] = 'Nomor WhatsApp/telepon belum valid.';
        }
        if ($need === '') {
            $errors[] = 'Kebutuhan wajib dipilih atau diisi.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email belum valid.';
        }
        if (!$consent) {
            $errors[] = 'Centang persetujuan untuk dihubungi admin.';
        }
        if ($message !== '' && strlen($message) < 4) {
            $errors[] = 'Catatan terlalu pendek.';
        }

        return $errors;
    }
}

if (!function_exists('inquiry_sanitize_custom_fields')) {
    function inquiry_sanitize_custom_fields(array $payload): array
    {
        $fields = [];
        $values = isset($payload['custom_fields']) && is_array($payload['custom_fields']) ? $payload['custom_fields'] : [];
        $labels = isset($payload['custom_labels']) && is_array($payload['custom_labels']) ? $payload['custom_labels'] : [];

        foreach ($values as $key => $value) {
            $key = preg_replace('/[^a-z0-9_\-]+/i', '_', (string)$key) ?: '';
            $key = trim($key, '_-');
            if ($key === '') {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', array_map(static fn($row): string => trim((string)$row), $value));
            }
            $cleanValue = inquiry_multiline_clean((string)$value, 600);
            if ($cleanValue === '') {
                continue;
            }
            $label = inquiry_clean((string)($labels[$key] ?? ucwords(str_replace(['_', '-'], ' ', $key))), 90);
            $fields[] = [
                'key' => inquiry_clean($key, 60),
                'label' => $label,
                'value' => $cleanValue,
            ];
            if (count($fields) >= 18) {
                break;
            }
        }

        return $fields;
    }
}

if (!function_exists('inquiry_append_custom_fields_to_message')) {
    function inquiry_append_custom_fields_to_message(string $message, array $customFields): string
    {
        if (!$customFields) {
            return $message;
        }
        $lines = [];
        foreach ($customFields as $field) {
            $label = (string)($field['label'] ?? $field['key'] ?? 'Field');
            $value = (string)($field['value'] ?? '');
            if ($value !== '') {
                $lines[] = '- ' . $label . ': ' . $value;
            }
        }
        if (!$lines) {
            return $message;
        }
        $extra = "Detail Form Landing Page:\n" . implode("\n", $lines);
        return inquiry_multiline_clean(trim($message . "\n\n" . $extra), 1800);
    }
}

if (!function_exists('inquiry_sanitize_tags')) {
    function inquiry_sanitize_tags(string|array $tags): array
    {
        if (is_string($tags)) {
            $tags = preg_split('/[,;\r\n]+/', $tags) ?: [];
        }
        if (!is_array($tags)) {
            return [];
        }
        $clean = [];
        foreach ($tags as $tag) {
            $tag = strtolower(trim((string)$tag));
            $tag = preg_replace('/[^a-z0-9_\- ]+/', '', $tag) ?: '';
            $tag = trim(preg_replace('/\s+/', '-', $tag) ?: '', '-_');
            if ($tag === '' || isset($clean[$tag])) {
                continue;
            }
            $clean[$tag] = $tag;
            if (count($clean) >= 12) {
                break;
            }
        }
        return array_values($clean);
    }
}

if (!function_exists('inquiry_sanitize_segment')) {
    function inquiry_sanitize_segment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\- ]+/', '', $value) ?: '';
        return substr(trim(preg_replace('/\s+/', '-', $value) ?: '', '-_'), 0, 80);
    }
}


if (!function_exists('inquiry_normalize_payload')) {
    function inquiry_normalize_payload(array $payload): array
    {
        $id = 'inq_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $page = inquiry_clean((string)($payload['page_path'] ?? $payload['source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? current_url())), 220);
        $customFields = inquiry_sanitize_custom_fields($payload);
        $message = inquiry_append_custom_fields_to_message(inquiry_multiline_clean((string)($payload['message'] ?? ''), 1200), $customFields);

        $leadPriority = strtolower(trim((string)($payload['lead_priority'] ?? '')));
        if (!in_array($leadPriority, ['cold', 'warm', 'hot', 'vip'], true)) {
            $leadPriority = '';
        }
        $leadScore = max(0, min(100, (int)($payload['lead_score'] ?? 0)));

        return [
            'id' => $id,
            'time' => date('c'),
            'status' => 'Baru',
            'name' => inquiry_clean((string)($payload['name'] ?? ''), 80),
            'phone' => inquiry_phone_clean((string)($payload['phone'] ?? '')),
            'email' => inquiry_clean((string)($payload['email'] ?? ''), 120),
            'consent_contact' => ((string)($payload['consent_contact'] ?? '') !== '') ? 'yes' : 'no',
            'need' => inquiry_clean((string)($payload['need'] ?? $payload['service'] ?? ''), 120),
            'location' => inquiry_clean((string)($payload['location'] ?? ''), 100),
            'message' => $message,
            'custom_fields' => $customFields,
            'mailketing_list_id' => inquiry_clean((string)($payload['mailketing_list_id'] ?? ''), 80),
            'lp_form_name' => inquiry_clean((string)($payload['lp_form_name'] ?? ''), 120),
            'lead_segment' => inquiry_sanitize_segment((string)($payload['lead_segment'] ?? '')),
            'lead_tags' => inquiry_sanitize_tags($payload['lead_tags'] ?? ''),
            'lead_priority' => $leadPriority,
            'lead_stage' => inquiry_sanitize_segment((string)($payload['lead_stage'] ?? '')),
            'lead_score' => $leadScore,
            'source' => inquiry_clean((string)($payload['source'] ?? 'website-form'), 80),
            'category' => inquiry_clean((string)($payload['category'] ?? ''), 80),
            'intent' => inquiry_clean((string)($payload['intent'] ?? 'inquiry'), 80),
            'label' => inquiry_clean((string)($payload['label'] ?? 'Form Inquiry'), 120),
            'item_title' => inquiry_clean((string)($payload['item_title'] ?? ''), 140),
            'item_url' => inquiry_clean((string)($payload['item_url'] ?? ''), 220),
            'landing_page_slug' => function_exists('slugify') ? slugify((string)($payload['landing_page_slug'] ?? '')) : inquiry_clean((string)($payload['landing_page_slug'] ?? ''), 120),
            'landing_page_id' => inquiry_clean((string)($payload['landing_page_id'] ?? ''), 90),
            'ab_test_type' => function_exists('landing_page_ab_clean_slug') ? landing_page_ab_clean_slug((string)($payload['ab_test_type'] ?? '')) : inquiry_clean((string)($payload['ab_test_type'] ?? ''), 40),
            'ab_test_id' => function_exists('landing_page_ab_clean_slug') ? landing_page_ab_clean_slug((string)($payload['ab_test_id'] ?? '')) : inquiry_clean((string)($payload['ab_test_id'] ?? ''), 90),
            'ab_test_name' => inquiry_clean((string)($payload['ab_test_name'] ?? ''), 100),
            'ab_variant' => in_array(strtolower((string)($payload['ab_variant'] ?? '')), ['a', 'b'], true) ? strtolower((string)$payload['ab_variant']) : '',
            'ab_variant_label' => inquiry_clean((string)($payload['ab_variant_label'] ?? ''), 80),
            'page_path' => $page,
            'referrer' => inquiry_clean((string)($_SERVER['HTTP_REFERER'] ?? ''), 220),
            'attribution' => function_exists('analytics_current_attribution') ? analytics_current_attribution() : [],
            'marketing_channel' => function_exists('analytics_attribution_channel') ? analytics_attribution_channel(function_exists('analytics_current_attribution') ? analytics_current_attribution() : []) : '',
            'ip_hash' => inquiry_rate_limit_key(),
        ];
    }
}

if (!function_exists('inquiry_store')) {
    function inquiry_store(array $inquiry, bool $idempotent = false): bool
    {
        if (!inquiry_enabled()) {
            return false;
        }
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }

        $mysqlOk = false;
        $mysqlActive = function_exists('storage_mysql_enabled') && storage_mysql_enabled('inquiries');
        if ($mysqlActive && function_exists('storage_adapter_mysql_append_inquiry')) {
            $mysqlOk = storage_adapter_mysql_append_inquiry($inquiry);
        }

        $file = inquiry_log_file();
        $encoded = json_encode($inquiry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fileOk = false;

        if (is_string($encoded)) {
            $line = $encoded . PHP_EOL;
            $inquiryId = inquiry_clean((string)($inquiry['id'] ?? ''), 80);

            if ($idempotent && $inquiryId !== '') {
                $handle = @fopen($file, 'c+');
                if ($handle && @flock($handle, LOCK_EX)) {
                    $exists = false;
                    rewind($handle);
                    while (($existingLine = fgets($handle)) !== false) {
                        $existing = json_decode(trim($existingLine), true);
                        if (is_array($existing) && (string)($existing['id'] ?? '') === $inquiryId) {
                            $exists = true;
                            break;
                        }
                    }

                    if ($exists) {
                        $fileOk = true;
                    } else {
                        fseek($handle, 0, SEEK_END);
                        $fileOk = fwrite($handle, $line) !== false;
                        if ($fileOk) {
                            fflush($handle);
                        }
                    }

                    @flock($handle, LOCK_UN);
                    fclose($handle);
                } elseif (is_resource($handle)) {
                    fclose($handle);
                }
            } else {
                $fileOk = @file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;
            }
        }

        if ($mysqlActive) {
            return $mysqlOk || (function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled() && $fileOk);
        }
        return $fileOk;
    }
}

if (!function_exists('inquiry_log_files')) {
    function inquiry_log_files(int $days = 30, array $filters = []): array
    {
        if (!defined('LOGS_PATH') || !is_dir(LOGS_PATH)) {
            return [];
        }
        $files = glob(LOGS_PATH . '/inquiries-*.jsonl') ?: [];
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $startMonth = $startTs > 0 ? strtotime(date('Y-m-01 00:00:00', $startTs)) : null;
        $endMonth = $endTs > 0 ? strtotime(date('Y-m-01 00:00:00', $endTs)) : null;
        $files = array_values(array_filter($files, static function (string $file) use ($startMonth, $endMonth): bool {
            if (!preg_match('/inquiries-(\d{4})-(\d{2})\.jsonl$/', $file, $matches)) {
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

if (!function_exists('inquiry_event_timestamp')) {
    function inquiry_event_timestamp(array $inquiry): int
    {
        $time = (string)($inquiry['time'] ?? '');
        $timestamp = $time !== '' ? strtotime($time) : false;
        return $timestamp !== false ? (int)$timestamp : 0;
    }
}

if (!function_exists('inquiry_matches_filters')) {
    function inquiry_matches_filters(array $inquiry, array $filters = []): bool
    {
        foreach (['status', 'source', 'category', 'location', 'need'] as $key) {
            $filter = strtolower(trim((string)($filters[$key] ?? '')));
            if ($filter === '') {
                continue;
            }
            $value = strtolower(trim((string)($inquiry[$key] ?? '')));
            if ($value === '' || !str_contains($value, $filter)) {
                return false;
            }
        }
        $search = strtolower(trim((string)($filters['search'] ?? '')));
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array_map('strval', [
                $inquiry['name'] ?? '',
                $inquiry['phone'] ?? '',
                $inquiry['need'] ?? '',
                $inquiry['location'] ?? '',
                $inquiry['message'] ?? '',
                $inquiry['item_title'] ?? '',
            ])));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('inquiry_read_all')) {
    function inquiry_read_all(int $days = 30, array $filters = [], int $max = 5000): array
    {
        $max = max(50, min(20000, $max));
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) {
            $days = 0;
        }
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $statuses = inquiry_read_statuses();
        if (function_exists('storage_adapter_mysql_read_inquiries') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('inquiries')) {
            $mysqlInquiries = storage_adapter_mysql_read_inquiries($days, $filters, $max);
            if (is_array($mysqlInquiries)) {
                foreach ($mysqlInquiries as &$mysqlInquiry) {
                    $id = (string)($mysqlInquiry['id'] ?? '');
                    if ($id !== '' && isset($statuses[$id])) {
                        $mysqlInquiry['status'] = (string)($statuses[$id]['status'] ?? ($mysqlInquiry['status'] ?? 'Baru'));
                        $mysqlInquiry['status_note'] = (string)($statuses[$id]['note'] ?? '');
                        $mysqlInquiry['status_updated_at'] = (string)($statuses[$id]['updated_at'] ?? '');
                    }
                }
                unset($mysqlInquiry);
                return $mysqlInquiries;
            }
        }
        $inquiries = [];
        foreach (inquiry_log_files($days, $filters) as $file) {
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $inquiry = json_decode($line, true);
                if (!is_array($inquiry)) {
                    continue;
                }
                $ts = inquiry_event_timestamp($inquiry);
                if ($ts <= 0) {
                    continue;
                }
                if ($startTs > 0 && $ts < $startTs) {
                    continue;
                }
                if ($endTs > 0 && $ts > $endTs) {
                    continue;
                }
                $id = (string)($inquiry['id'] ?? '');
                if ($id !== '' && isset($statuses[$id])) {
                    $inquiry['status'] = (string)($statuses[$id]['status'] ?? ($inquiry['status'] ?? 'Baru'));
                    $inquiry['status_note'] = (string)($statuses[$id]['note'] ?? '');
                    $inquiry['status_updated_at'] = (string)($statuses[$id]['updated_at'] ?? '');
                }
                if (!inquiry_matches_filters($inquiry, $filters)) {
                    continue;
                }
                $inquiry['_ts'] = $ts;
                $inquiries[] = $inquiry;
                if (count($inquiries) >= $max) {
                    break 2;
                }
            }
            fclose($handle);
        }
        usort($inquiries, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
        return $inquiries;
    }
}

if (!function_exists('inquiry_count_by')) {
    function inquiry_count_by(array $inquiries, string $key, int $limit = 8): array
    {
        $counts = [];
        foreach ($inquiries as $inquiry) {
            $value = trim((string)($inquiry[$key] ?? '')) ?: 'Tidak diketahui';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('inquiry_summary')) {
    function inquiry_summary(int $days = 30, array $filters = []): array
    {
        $inquiries = inquiry_read_all($days, $filters, 10000);
        $today = date('Y-m-d');
        $new = 0;
        $todayCount = 0;
        foreach ($inquiries as $item) {
            if (($item['status'] ?? 'Baru') === 'Baru') {
                $new++;
            }
            if (date('Y-m-d', (int)($item['_ts'] ?? time())) === $today) {
                $todayCount++;
            }
        }
        return [
            'total' => count($inquiries),
            'new' => $new,
            'today' => $todayCount,
            'by_status' => inquiry_count_by($inquiries, 'status', 8),
            'by_need' => inquiry_count_by($inquiries, 'need', 8),
            'by_location' => inquiry_count_by($inquiries, 'location', 8),
            'recent' => array_slice($inquiries, 0, 40),
        ];
    }
}
