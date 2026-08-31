<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| MINI CRM / FOLLOW-UP HELPERS - Template
|--------------------------------------------------------------------------
| Lightweight follow-up history and reminder foundation. Designed to stay
| storage-friendly while helping admins prioritize
| warm/hot leads from inquiry and order flows.
|--------------------------------------------------------------------------
*/

if (!function_exists('crm_enabled')) {
    function crm_enabled(): bool
    {
        $value = strtolower(trim((string)($_ENV['ENABLE_MINI_CRM'] ?? 'true')));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('crm_clean')) {
    function crm_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('crm_multiline_clean')) {
    function crm_multiline_clean(string $value, int $max = 1200): string
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

if (!function_exists('crm_allowed_target_types')) {
    function crm_allowed_target_types(): array
    {
        return ['order', 'inquiry', 'manual'];
    }
}

if (!function_exists('crm_priorities')) {
    function crm_priorities(): array
    {
        return [
            'Rendah' => 'Lead masih awal / belum prioritas',
            'Normal' => 'Lead perlu follow-up wajar',
            'Tinggi' => 'Lead aktif dan perlu diprioritaskan',
            'Sangat Panas' => 'Hot lead / mendekati closing',
        ];
    }
}

if (!function_exists('crm_outcomes')) {
    function crm_outcomes(): array
    {
        return [
            'Catatan' => 'Catatan internal',
            'Chat Terkirim' => 'Sudah dihubungi via WhatsApp/telepon',
            'Menunggu Respon' => 'Admin menunggu balasan customer',
            'Minta Follow-up Lagi' => 'Customer minta dihubungi ulang',
            'Kirim Invoice' => 'Invoice/instruksi pembayaran dikirim',
            'Reminder Pembayaran' => 'Follow-up pembayaran/DP/pelunasan',
            'Deal' => 'Customer setuju lanjut',
            'Tidak Jadi' => 'Lead tidak lanjut',
        ];
    }
}

if (!function_exists('crm_normalize_priority')) {
    function crm_normalize_priority(string $priority): string
    {
        $priority = crm_clean($priority, 40);
        return array_key_exists($priority, crm_priorities()) ? $priority : 'Normal';
    }
}

if (!function_exists('crm_normalize_outcome')) {
    function crm_normalize_outcome(string $outcome): string
    {
        $outcome = crm_clean($outcome, 60);
        return array_key_exists($outcome, crm_outcomes()) ? $outcome : 'Catatan';
    }
}

if (!function_exists('crm_log_file')) {
    function crm_log_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/followups-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('crm_log_files')) {
    function crm_log_files(int $days = 365, array $filters = []): array
    {
        if (!defined('LOGS_PATH') || !is_dir(LOGS_PATH)) {
            return [];
        }
        $files = glob(LOGS_PATH . '/followups-*.jsonl') ?: [];
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) {
            $days = 0;
        }
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $startMonth = $startTs > 0 ? strtotime(date('Y-m-01 00:00:00', $startTs)) : null;
        $endMonth = $endTs > 0 ? strtotime(date('Y-m-01 00:00:00', $endTs)) : null;
        $files = array_values(array_filter($files, static function (string $file) use ($startMonth, $endMonth): bool {
            if (!preg_match('/followups-(\d{4})-(\d{2})\.jsonl$/', $file, $matches)) {
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

if (!function_exists('crm_event_timestamp')) {
    function crm_event_timestamp(array $event): int
    {
        $time = (string)($event['time'] ?? '');
        $timestamp = $time !== '' ? strtotime($time) : false;
        return $timestamp !== false ? (int)$timestamp : 0;
    }
}

if (!function_exists('crm_due_timestamp')) {
    function crm_due_timestamp(array $event): int
    {
        $date = trim((string)($event['next_followup_date'] ?? ''));
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 0;
        }
        $time = trim((string)($event['next_followup_time'] ?? ''));
        if ($time === '' || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '09:00';
        }
        $timestamp = strtotime($date . ' ' . $time . ':00');
        return $timestamp !== false ? (int)$timestamp : 0;
    }
}

if (!function_exists('crm_store_followup')) {
    function crm_store_followup(array $payload): bool
    {
        if (!crm_enabled()) {
            return false;
        }
        $targetType = crm_clean((string)($payload['target_type'] ?? ''), 30);
        if (!in_array($targetType, crm_allowed_target_types(), true)) {
            return false;
        }
        $targetId = crm_clean((string)($payload['target_id'] ?? ''), 100);
        if ($targetId === '') {
            return false;
        }
        $note = crm_multiline_clean((string)($payload['note'] ?? ''), 1000);
        $outcome = crm_normalize_outcome((string)($payload['outcome'] ?? 'Catatan'));
        if ($note === '' && $outcome === 'Catatan') {
            return false;
        }
        $nextDate = trim((string)($payload['next_followup_date'] ?? ''));
        $nextTime = trim((string)($payload['next_followup_time'] ?? ''));
        $nextDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextDate) ? $nextDate : '';
        $nextTime = preg_match('/^\d{2}:\d{2}$/', $nextTime) ? $nextTime : '';

        $event = [
            'id' => 'fup_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)),
            'time' => date('c'),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_ref' => crm_clean((string)($payload['target_ref'] ?? ''), 100),
            'target_name' => crm_clean((string)($payload['target_name'] ?? ''), 100),
            'phone' => crm_clean((string)($payload['phone'] ?? ''), 40),
            'email' => crm_clean((string)($payload['email'] ?? ''), 140),
            'subject' => crm_clean((string)($payload['subject'] ?? ''), 160),
            'priority' => crm_normalize_priority((string)($payload['priority'] ?? 'Normal')),
            'outcome' => $outcome,
            'note' => $note,
            'next_followup_date' => $nextDate,
            'next_followup_time' => $nextTime,
            'created_by' => crm_clean((string)($payload['created_by'] ?? 'admin'), 80),
            'source' => crm_clean((string)($payload['source'] ?? 'admin'), 80),
        ];

        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }
        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return @file_put_contents(crm_log_file(), $line, FILE_APPEND | LOCK_EX) !== false;
    }
}

if (!function_exists('crm_matches_filters')) {
    function crm_matches_filters(array $event, array $filters = []): bool
    {
        foreach (['target_type', 'priority', 'outcome'] as $key) {
            $filter = strtolower(trim((string)($filters[$key] ?? '')));
            if ($filter === '') {
                continue;
            }
            $value = strtolower(trim((string)($event[$key] ?? '')));
            if ($value === '' || $value !== $filter) {
                return false;
            }
        }
        $due = strtolower(trim((string)($filters['due'] ?? '')));
        if ($due !== '') {
            $dueTs = crm_due_timestamp($event);
            $todayStart = strtotime(date('Y-m-d 00:00:00')) ?: time();
            $todayEnd = strtotime(date('Y-m-d 23:59:59')) ?: time();
            if ($due === 'today' && !($dueTs >= $todayStart && $dueTs <= $todayEnd)) {
                return false;
            }
            if ($due === 'overdue' && !($dueTs > 0 && $dueTs < $todayStart)) {
                return false;
            }
            if ($due === 'upcoming' && !($dueTs > $todayEnd)) {
                return false;
            }
            if ($due === 'scheduled' && !($dueTs > 0)) {
                return false;
            }
        }
        $search = strtolower(trim((string)($filters['search'] ?? '')));
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array_map('strval', [
                $event['target_ref'] ?? '',
                $event['target_name'] ?? '',
                $event['phone'] ?? '',
                $event['email'] ?? '',
                $event['subject'] ?? '',
                $event['note'] ?? '',
                $event['outcome'] ?? '',
            ])));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('crm_read_all')) {
    function crm_read_all(int $days = 365, array $filters = [], int $max = 10000): array
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
        $events = [];
        foreach (crm_log_files($days, $filters) as $file) {
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $event = json_decode($line, true);
                if (!is_array($event)) {
                    continue;
                }
                $ts = crm_event_timestamp($event);
                if ($ts <= 0) {
                    continue;
                }
                if ($startTs > 0 && $ts < $startTs) {
                    continue;
                }
                if ($endTs > 0 && $ts > $endTs) {
                    continue;
                }
                if (!crm_matches_filters($event, $filters)) {
                    continue;
                }
                $event['_ts'] = $ts;
                $event['_due_ts'] = crm_due_timestamp($event);
                $events[] = $event;
                if (count($events) >= $max) {
                    break 2;
                }
            }
            fclose($handle);
        }
        usort($events, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
        return $events;
    }
}

if (!function_exists('crm_recent_for_target')) {
    function crm_recent_for_target(string $targetType, string $targetId, int $limit = 5): array
    {
        $targetType = crm_clean($targetType, 30);
        $targetId = crm_clean($targetId, 100);
        if ($targetType === '' || $targetId === '') {
            return [];
        }
        $events = crm_read_all(0, ['_all_time' => true, 'target_type' => $targetType], 50000);
        $events = array_values(array_filter($events, static fn(array $event): bool => (string)($event['target_id'] ?? '') === $targetId));
        return array_slice($events, 0, max(1, $limit));
    }
}

if (!function_exists('crm_count_by')) {
    function crm_count_by(array $events, string $key, int $limit = 8): array
    {
        $counts = [];
        foreach ($events as $event) {
            $value = trim((string)($event[$key] ?? '')) ?: 'Tidak diketahui';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('crm_summary')) {
    function crm_summary(int $days = 365, array $filters = []): array
    {
        $events = crm_read_all($days, $filters, 50000);
        $todayStart = strtotime(date('Y-m-d 00:00:00')) ?: time();
        $todayEnd = strtotime(date('Y-m-d 23:59:59')) ?: time();
        $today = 0;
        $overdue = 0;
        $upcoming = 0;
        $hot = 0;
        foreach ($events as $event) {
            $dueTs = (int)($event['_due_ts'] ?? 0);
            if ($dueTs >= $todayStart && $dueTs <= $todayEnd) {
                $today++;
            }
            if ($dueTs > 0 && $dueTs < $todayStart) {
                $overdue++;
            }
            if ($dueTs > $todayEnd) {
                $upcoming++;
            }
            if (in_array((string)($event['priority'] ?? ''), ['Tinggi', 'Sangat Panas'], true)) {
                $hot++;
            }
        }
        $scheduled = array_values(array_filter($events, static fn(array $event): bool => (int)($event['_due_ts'] ?? 0) > 0));
        usort($scheduled, static fn(array $a, array $b): int => ((int)($a['_due_ts'] ?? 0)) <=> ((int)($b['_due_ts'] ?? 0)));
        return [
            'total' => count($events),
            'today' => $today,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'hot' => $hot,
            'by_priority' => crm_count_by($events, 'priority', 8),
            'by_outcome' => crm_count_by($events, 'outcome', 8),
            'by_target_type' => crm_count_by($events, 'target_type', 8),
            'recent' => array_slice($events, 0, 30),
            'scheduled' => array_slice($scheduled, 0, 30),
        ];
    }
}

if (!function_exists('crm_temperature_from_order')) {
    function crm_temperature_from_order(array $order): string
    {
        $status = (string)($order['status'] ?? '');
        $payment = (string)($order['payment_status'] ?? '');
        if (in_array($payment, ['DP Masuk', 'Lunas'], true) || in_array($status, ['Deal', 'Menunggu Pembayaran'], true)) {
            return 'Sangat Panas';
        }
        if ($status === 'Diproses' || $payment === 'Menunggu Pembayaran') {
            return 'Tinggi';
        }
        if ($status === 'Baru') {
            return 'Normal';
        }
        return 'Rendah';
    }
}

if (!function_exists('crm_temperature_from_inquiry')) {
    function crm_temperature_from_inquiry(array $inquiry): string
    {
        $status = (string)($inquiry['status'] ?? '');
        if ($status === 'Deal') {
            return 'Sangat Panas';
        }
        if ($status === 'Dihubungi') {
            return 'Tinggi';
        }
        if ($status === 'Baru') {
            return 'Normal';
        }
        return 'Rendah';
    }
}

if (!function_exists('crm_next_label')) {
    function crm_next_label(array $event): string
    {
        $dueTs = crm_due_timestamp($event);
        if ($dueTs <= 0) {
            return 'Belum dijadwalkan';
        }
        $today = date('Y-m-d');
        $date = date('Y-m-d', $dueTs);
        if ($date === $today) {
            return 'Hari ini ' . date('H:i', $dueTs);
        }
        if ($date < $today) {
            return 'Terlambat: ' . date('d M Y H:i', $dueTs);
        }
        return date('d M Y H:i', $dueTs);
    }
}

if (!function_exists('crm_status_class')) {
    function crm_status_class(string $value): string
    {
        return function_exists('slugify') ? slugify($value) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value));
    }
}
