<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| LEAD QUALITY & FOLLOW-UP OPPORTUNITY SCORING
|--------------------------------------------------------------------------
| Reads existing inquiries, orders, follow-up notes, and Lead Tracking events
| to rank which lead/opportunity should be followed up first. This is not a
| new tracking system; it bridges existing data into an execution queue.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('lead_quality_clean')) {
    function lead_quality_clean(mixed $value, int $max = 220): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('lead_quality_id')) {
    function lead_quality_id(string $value = ''): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-:\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');
        return substr($value, 0, 150);
    }
}

if (!function_exists('lead_quality_storage_file')) {
    function lead_quality_storage_file(): string
    {
        return STORAGE_PATH . '/lead-quality-followup-scoring.json';
    }
}

if (!function_exists('lead_quality_status_options')) {
    function lead_quality_status_options(): array
    {
        return [
            'new_opportunity' => 'Opportunity baru',
            'contact_today' => 'Follow-up hari ini',
            'scheduled' => 'Sudah dijadwalkan',
            'waiting_response' => 'Menunggu respon',
            'waiting_payment' => 'Menunggu pembayaran',
            'won' => 'Deal / selesai',
            'lost' => 'Tidak lanjut',
            'hold' => 'Tahan dulu',
        ];
    }
}

if (!function_exists('lead_quality_filter_options')) {
    function lead_quality_filter_options(): array
    {
        return [
            'all' => 'Semua Status',
            'open' => 'Belum Selesai',
            'new_opportunity' => 'Opportunity Baru',
            'contact_today' => 'Follow-up Hari Ini',
            'scheduled' => 'Sudah Dijadwalkan',
            'waiting_response' => 'Menunggu Respon',
            'waiting_payment' => 'Menunggu Pembayaran',
            'won' => 'Deal/Selesai',
            'lost' => 'Tidak Lanjut',
            'hold' => 'Ditahan',
        ];
    }
}

if (!function_exists('lead_quality_priority_options')) {
    function lead_quality_priority_options(): array
    {
        return [
            'all' => 'Semua Prioritas',
            'hot' => 'Hot',
            'warm' => 'Warm',
            'watch' => 'Watch',
            'cold' => 'Cold',
        ];
    }
}

if (!function_exists('lead_quality_type_options')) {
    function lead_quality_type_options(): array
    {
        return [
            'all' => 'Semua Sumber Lead',
            'order' => 'Order',
            'inquiry' => 'Inbox Lead / Form',
            'lead_event' => 'Tracking Lead',
        ];
    }
}

if (!function_exists('lead_quality_default_settings')) {
    function lead_quality_default_settings(): array
    {
        return [
            'items' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('lead_quality_normalize_state')) {
    function lead_quality_normalize_state(array $state): array
    {
        $options = lead_quality_status_options();
        $status = (string)($state['status'] ?? 'new_opportunity');
        if (!isset($options[$status])) {
            $status = 'new_opportunity';
        }

        return [
            'item_id' => lead_quality_id((string)($state['item_id'] ?? '')),
            'status' => $status,
            'note' => lead_quality_clean($state['note'] ?? '', 900),
            'owner' => lead_quality_clean($state['owner'] ?? '', 80),
            'next_followup_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($state['next_followup_date'] ?? '')) ? (string)$state['next_followup_date'] : '',
            'next_followup_time' => preg_match('/^\d{2}:\d{2}$/', (string)($state['next_followup_time'] ?? '')) ? (string)$state['next_followup_time'] : '',
            'updated_at' => lead_quality_clean($state['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('lead_quality_normalize_settings')) {
    function lead_quality_normalize_settings(array $settings): array
    {
        $settings = array_merge(lead_quality_default_settings(), $settings);
        $items = [];
        foreach ((array)($settings['items'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $normalized = lead_quality_normalize_state($state);
            if ((string)$normalized['item_id'] === '') {
                continue;
            }
            $items[(string)$normalized['item_id']] = $normalized;
        }
        return [
            'items' => $items,
            'updated_at' => lead_quality_clean($settings['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('lead_quality_settings')) {
    function lead_quality_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }
        $file = lead_quality_storage_file();
        if (!is_file($file)) {
            $cached = lead_quality_normalize_settings(lead_quality_default_settings());
            return $cached;
        }
        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = lead_quality_normalize_settings(lead_quality_default_settings());
            return $cached;
        }
        $cached = lead_quality_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('lead_quality_write_settings')) {
    function lead_quality_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = lead_quality_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(lead_quality_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan Lead Priority belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }
        @chmod(lead_quality_storage_file(), 0644);
        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'lead-quality-followup-scoring', null, 'Menyimpan status Lead Priority Scoring.');
        }
        return true;
    }
}

if (!function_exists('lead_quality_update_item')) {
    function lead_quality_update_item(string $itemId, string $status, string $note = '', string $owner = '', string $date = '', string $time = ''): bool
    {
        $itemId = lead_quality_id($itemId);
        if ($itemId === '') {
            throw new RuntimeException('ID lead tidak valid.');
        }
        $options = lead_quality_status_options();
        if (!isset($options[$status])) {
            $status = 'new_opportunity';
        }
        $settings = lead_quality_settings(true);
        $settings['items'][$itemId] = lead_quality_normalize_state([
            'item_id' => $itemId,
            'status' => $status,
            'note' => $note,
            'owner' => $owner,
            'next_followup_date' => $date,
            'next_followup_time' => $time,
            'updated_at' => date(DATE_ATOM),
        ]);
        return lead_quality_write_settings($settings, true);
    }
}

if (!function_exists('lead_quality_reset_item')) {
    function lead_quality_reset_item(string $itemId): bool
    {
        $itemId = lead_quality_id($itemId);
        $settings = lead_quality_settings(true);
        unset($settings['items'][$itemId]);
        return lead_quality_write_settings($settings, true);
    }
}

if (!function_exists('lead_quality_reset_all')) {
    function lead_quality_reset_all(): bool
    {
        return lead_quality_write_settings(lead_quality_default_settings(), true);
    }
}

if (!function_exists('lead_quality_days_bonus')) {
    function lead_quality_days_bonus(int $timestamp): int
    {
        if ($timestamp <= 0) {
            return 0;
        }
        $ageDays = max(0, (int)floor((time() - $timestamp) / 86400));
        if ($ageDays === 0) {
            return 18;
        }
        if ($ageDays <= 2) {
            return 14;
        }
        if ($ageDays <= 7) {
            return 10;
        }
        if ($ageDays <= 14) {
            return 6;
        }
        return 0;
    }
}

if (!function_exists('lead_quality_bucket')) {
    function lead_quality_bucket(int $score): string
    {
        if ($score >= 80) {
            return 'hot';
        }
        if ($score >= 60) {
            return 'warm';
        }
        if ($score >= 40) {
            return 'watch';
        }
        return 'cold';
    }
}

if (!function_exists('lead_quality_bucket_label')) {
    function lead_quality_bucket_label(string $bucket): string
    {
        return match ($bucket) {
            'hot' => 'Hot',
            'warm' => 'Warm',
            'watch' => 'Watch',
            'cold' => 'Cold',
            default => ucfirst($bucket ?: 'Lead'),
        };
    }
}

if (!function_exists('lead_quality_key')) {
    function lead_quality_key(string $targetType, string $targetId): string
    {
        return strtolower($targetType) . ':' . lead_quality_id($targetId);
    }
}

if (!function_exists('lead_quality_followup_index')) {
    function lead_quality_followup_index(): array
    {
        $index = [];
        $events = function_exists('crm_read_all') ? crm_read_all(0, ['_all_time' => true], 50000) : [];
        foreach ($events as $event) {
            $type = lead_quality_clean($event['target_type'] ?? '', 30);
            $id = lead_quality_clean($event['target_id'] ?? '', 120);
            if ($type === '' || $id === '') {
                continue;
            }
            $key = lead_quality_key($type, $id);
            if (!isset($index[$key])) {
                $index[$key] = [
                    'count' => 0,
                    'latest' => null,
                    'next_due' => null,
                    'overdue' => false,
                    'today' => false,
                ];
            }
            $index[$key]['count']++;
            if ($index[$key]['latest'] === null || (int)($event['_ts'] ?? 0) > (int)($index[$key]['latest']['_ts'] ?? 0)) {
                $index[$key]['latest'] = $event;
            }
            $dueTs = (int)($event['_due_ts'] ?? 0);
            if ($dueTs > 0) {
                if ($index[$key]['next_due'] === null || $dueTs < (int)($index[$key]['next_due']['_due_ts'] ?? PHP_INT_MAX)) {
                    $index[$key]['next_due'] = $event;
                }
                $todayStart = strtotime(date('Y-m-d 00:00:00')) ?: time();
                $todayEnd = strtotime(date('Y-m-d 23:59:59')) ?: time();
                if ($dueTs < $todayStart) {
                    $index[$key]['overdue'] = true;
                }
                if ($dueTs >= $todayStart && $dueTs <= $todayEnd) {
                    $index[$key]['today'] = true;
                }
            }
        }
        return $index;
    }
}

if (!function_exists('lead_quality_attach_state')) {
    function lead_quality_attach_state(array $item, array $states, array $followups): array
    {
        $id = (string)($item['item_id'] ?? '');
        $state = is_array($states[$id] ?? null) ? $states[$id] : [];
        $followKey = lead_quality_key((string)($item['target_type'] ?? ''), (string)($item['target_id'] ?? ''));
        $follow = is_array($followups[$followKey] ?? null) ? $followups[$followKey] : [];

        $defaultStatus = (string)($item['default_status'] ?? 'new_opportunity');
        $item['status'] = (string)($state['status'] ?? $defaultStatus);
        $item['admin_note'] = (string)($state['note'] ?? '');
        $item['owner'] = (string)($state['owner'] ?? '');
        $item['next_followup_date'] = (string)($state['next_followup_date'] ?? '');
        $item['next_followup_time'] = (string)($state['next_followup_time'] ?? '');
        $item['state_updated_at'] = (string)($state['updated_at'] ?? '');
        $item['followup_count'] = (int)($follow['count'] ?? 0);
        $item['followup_latest'] = is_array($follow['latest'] ?? null) ? $follow['latest'] : null;
        $item['followup_next_due'] = is_array($follow['next_due'] ?? null) ? $follow['next_due'] : null;
        $item['followup_overdue'] = !empty($follow['overdue']);
        $item['followup_today'] = !empty($follow['today']);

        if (!empty($item['followup_overdue'])) {
            $item['score'] = min(100, (int)($item['score'] ?? 0) + 10);
            $item['next_action'] = 'Follow-up overdue, segera hubungi lagi.';
            if ($item['status'] === 'new_opportunity') {
                $item['status'] = 'contact_today';
            }
        } elseif (!empty($item['followup_today'])) {
            $item['score'] = min(100, (int)($item['score'] ?? 0) + 8);
            $item['next_action'] = 'Jadwal follow-up hari ini, prioritaskan.';
        }

        $item['priority'] = lead_quality_bucket((int)($item['score'] ?? 0));
        return $item;
    }
}

if (!function_exists('lead_quality_item_from_order')) {
    function lead_quality_item_from_order(array $order): array
    {
        $id = (string)($order['id'] ?? '');
        $ts = (int)($order['_ts'] ?? (function_exists('order_event_timestamp') ? order_event_timestamp($order) : strtotime((string)($order['time'] ?? ''))));
        $status = (string)($order['status'] ?? 'Baru');
        $payment = (string)($order['payment_status'] ?? 'Belum Ditagih');
        $score = 58 + lead_quality_days_bonus($ts);
        if (in_array($status, ['Baru', 'Diproses', 'Menunggu Pembayaran'], true)) {
            $score += 14;
        }
        if (in_array($payment, ['Belum Ditagih', 'Menunggu Pembayaran'], true)) {
            $score += 14;
        }
        if (in_array($payment, ['DP Masuk', 'Lunas'], true) || in_array($status, ['Deal'], true)) {
            $score += 20;
        }
        if ((int)($order['price'] ?? 0) > 0) {
            $score += 6;
        }
        if ((string)($order['phone'] ?? '') !== '') {
            $score += 8;
        }
        $score = max(0, min(100, $score));
        $defaultStatus = in_array($payment, ['Menunggu Pembayaran', 'Belum Ditagih'], true) ? 'waiting_payment' : 'new_opportunity';
        if (in_array($payment, ['DP Masuk', 'Lunas'], true) || $status === 'Deal') {
            $defaultStatus = 'won';
        }

        return [
            'item_id' => lead_quality_key('order', $id),
            'target_type' => 'order',
            'target_id' => $id,
            'target_ref' => (string)($order['ref'] ?? $id),
            'type' => 'order',
            'type_label' => 'Order',
            'name' => lead_quality_clean($order['name'] ?? '', 90) ?: 'Customer order',
            'phone' => lead_quality_clean($order['phone'] ?? '', 50),
            'email' => lead_quality_clean($order['email'] ?? '', 130),
            'subject' => lead_quality_clean($order['product_title'] ?? $order['need'] ?? 'Order masuk', 160),
            'page_path' => lead_quality_clean($order['page_path'] ?? $order['product_url'] ?? '', 220),
            'source' => lead_quality_clean($order['source'] ?? 'order', 80),
            'stage' => trim($status . ' / ' . $payment, ' /'),
            'score' => $score,
            'priority' => lead_quality_bucket($score),
            'default_status' => $defaultStatus,
            'last_ts' => $ts,
            'last_time' => $ts > 0 ? date('Y-m-d H:i', $ts) : '',
            'reason' => $payment === 'Menunggu Pembayaran' ? 'Order sudah dekat transaksi tapi pembayaran belum masuk.' : 'Order adalah sinyal lead paling dekat ke revenue.',
            'next_action' => $payment === 'Menunggu Pembayaran' ? 'Kirim reminder pembayaran dan link invoice/status order.' : 'Hubungi customer untuk konfirmasi kebutuhan dan langkah berikutnya.',
            'recommended_priority' => $score >= 80 ? 'Sangat Panas' : 'Tinggi',
            'recommended_outcome' => $payment === 'Menunggu Pembayaran' ? 'Reminder Pembayaran' : 'Chat Terkirim',
        ];
    }
}

if (!function_exists('lead_quality_item_from_inquiry')) {
    function lead_quality_item_from_inquiry(array $inquiry): array
    {
        $id = (string)($inquiry['id'] ?? '');
        $ts = (int)($inquiry['_ts'] ?? (function_exists('inquiry_event_timestamp') ? inquiry_event_timestamp($inquiry) : strtotime((string)($inquiry['time'] ?? ''))));
        $status = (string)($inquiry['status'] ?? 'Baru');
        $score = 42 + lead_quality_days_bonus($ts);
        $leadScore = (int)($inquiry['lead_score'] ?? 0);
        if ($leadScore > 0) {
            $score += (int)round($leadScore * 0.32);
        }
        $leadPriority = strtolower((string)($inquiry['lead_priority'] ?? ''));
        if ($leadPriority === 'hot') {
            $score += 18;
        } elseif ($leadPriority === 'vip') {
            $score += 24;
        } elseif ($leadPriority === 'warm') {
            $score += 10;
        }
        if ($status === 'Baru') {
            $score += 12;
        } elseif ($status === 'Dihubungi') {
            $score += 8;
        } elseif ($status === 'Deal') {
            $score += 22;
        }
        if ((string)($inquiry['phone'] ?? '') !== '') {
            $score += 12;
        }
        if ((string)($inquiry['need'] ?? '') !== '') {
            $score += 6;
        }
        $score = max(0, min(100, $score));

        return [
            'item_id' => lead_quality_key('inquiry', $id),
            'target_type' => 'inquiry',
            'target_id' => $id,
            'target_ref' => $id,
            'type' => 'inquiry',
            'type_label' => 'Inbox Lead / Form',
            'name' => lead_quality_clean($inquiry['name'] ?? '', 90) ?: 'Lead form',
            'phone' => lead_quality_clean($inquiry['phone'] ?? '', 50),
            'email' => lead_quality_clean($inquiry['email'] ?? '', 130),
            'subject' => lead_quality_clean($inquiry['need'] ?? $inquiry['item_title'] ?? $inquiry['label'] ?? 'Lead masuk', 160),
            'page_path' => lead_quality_clean($inquiry['page_path'] ?? $inquiry['item_url'] ?? '', 220),
            'source' => lead_quality_clean($inquiry['source'] ?? 'inquiry', 80),
            'stage' => $status,
            'score' => $score,
            'priority' => lead_quality_bucket($score),
            'default_status' => $status === 'Deal' ? 'won' : 'new_opportunity',
            'last_ts' => $ts,
            'last_time' => $ts > 0 ? date('Y-m-d H:i', $ts) : '',
            'reason' => 'Lead mengisi form/inbox dan sudah meninggalkan kontak yang bisa di-follow-up.',
            'next_action' => $status === 'Baru' ? 'Hubungi via WhatsApp/telepon, validasi kebutuhan, lalu tawarkan next step.' : 'Lanjutkan follow-up sesuai status dan catatan terakhir.',
            'recommended_priority' => $score >= 80 ? 'Sangat Panas' : ($score >= 60 ? 'Tinggi' : 'Normal'),
            'recommended_outcome' => 'Chat Terkirim',
        ];
    }
}

if (!function_exists('lead_quality_item_from_event')) {
    function lead_quality_item_from_event(array $event): array
    {
        $ts = (int)($event['_ts'] ?? (function_exists('conversion_event_timestamp') ? conversion_event_timestamp($event) : strtotime((string)($event['time'] ?? ''))));
        $group = (string)($event['_event_group'] ?? (function_exists('conversion_event_group') ? conversion_event_group($event) : 'interaction'));
        $priority = (int)($event['_event_priority'] ?? (function_exists('conversion_event_priority') ? conversion_event_priority($event) : 60));
        $base = match ($group) {
            'order' => 78,
            'payment' => 74,
            'inquiry' => 68,
            'checkout', 'conversion' => 64,
            'whatsapp' => 58,
            default => 42,
        };
        $score = $base + lead_quality_days_bonus($ts) + (int)round(min(260, $priority) / 18);
        if (!empty($event['is_whatsapp'])) {
            $score += 8;
        }
        $score = max(0, min(100, $score));
        $stable = (string)($event['event_id'] ?? '');
        if ($stable === '') {
            $stable = substr(sha1(json_encode([$event['time'] ?? '', $event['page_path'] ?? '', $event['target_url'] ?? '', $event['label'] ?? ''], JSON_UNESCAPED_SLASHES)), 0, 18);
        }
        $targetId = 'event_' . $stable;

        return [
            'item_id' => lead_quality_key('manual', $targetId),
            'target_type' => 'manual',
            'target_id' => $targetId,
            'target_ref' => lead_quality_clean($event['label'] ?? $event['source'] ?? $targetId, 120),
            'type' => 'lead_event',
            'type_label' => 'Tracking Lead',
            'name' => lead_quality_clean($event['label'] ?? 'Lead tracking signal', 100),
            'phone' => '',
            'email' => '',
            'subject' => lead_quality_clean(($event['_event_group_label'] ?? 'Sinyal lead') . ' - ' . ($event['source'] ?? $event['channel'] ?? ''), 160),
            'page_path' => lead_quality_clean($event['page_path'] ?? '', 220),
            'source' => lead_quality_clean($event['source'] ?? $event['channel'] ?? 'lead-tracking', 80),
            'stage' => lead_quality_clean($event['_event_group_label'] ?? $group, 80),
            'score' => $score,
            'priority' => lead_quality_bucket($score),
            'default_status' => 'new_opportunity',
            'last_ts' => $ts,
            'last_time' => $ts > 0 ? date('Y-m-d H:i', $ts) : '',
            'reason' => 'Ada sinyal klik/WhatsApp/checkout dari Lead Tracking existing yang perlu dicek agar tidak bocor.',
            'next_action' => 'Cek sumber halaman dan CTA, lalu follow-up jika ada kontak atau perkuat offer jika belum ada lead.',
            'recommended_priority' => $score >= 80 ? 'Tinggi' : 'Normal',
            'recommended_outcome' => 'Catatan',
        ];
    }
}

if (!function_exists('lead_quality_candidates')) {
    function lead_quality_candidates(int $days = 30): array
    {
        $days = max(7, min(365, $days));
        $items = [];
        if (function_exists('order_read_all')) {
            foreach (order_read_all($days, [], 15000) as $order) {
                $item = lead_quality_item_from_order($order);
                if ((string)($item['target_id'] ?? '') !== '') {
                    $items[(string)$item['item_id']] = $item;
                }
            }
        }
        if (function_exists('inquiry_read_all')) {
            foreach (inquiry_read_all($days, [], 15000) as $inquiry) {
                $item = lead_quality_item_from_inquiry($inquiry);
                if ((string)($item['target_id'] ?? '') !== '') {
                    $items[(string)$item['item_id']] = $item;
                }
            }
        }
        if (function_exists('conversion_read_lead_events')) {
            $events = conversion_read_lead_events($days, [], 40000);
            $events = function_exists('conversion_dedupe_lead_events') ? conversion_dedupe_lead_events($events, 10) : $events;
            foreach ($events as $event) {
                $group = (string)($event['_event_group'] ?? (function_exists('conversion_event_group') ? conversion_event_group($event) : ''));
                if (!in_array($group, ['order', 'payment', 'inquiry', 'checkout', 'conversion', 'whatsapp'], true)) {
                    continue;
                }
                $item = lead_quality_item_from_event($event);
                $items[(string)$item['item_id']] = $item;
            }
        }
        $states = (array)(lead_quality_settings()['items'] ?? []);
        $followups = lead_quality_followup_index();
        $items = array_values(array_map(static fn(array $item): array => lead_quality_attach_state($item, $states, $followups), $items));
        usort($items, static function (array $a, array $b): int {
            $score = ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0));
            if ($score !== 0) {
                return $score;
            }
            return ((int)($b['last_ts'] ?? 0)) <=> ((int)($a['last_ts'] ?? 0));
        });
        return $items;
    }
}

if (!function_exists('lead_quality_matches')) {
    function lead_quality_matches(array $item, string $type = 'all', string $priority = 'all', string $status = 'open'): bool
    {
        if ($type !== 'all' && (string)($item['type'] ?? '') !== $type) {
            return false;
        }
        if ($priority !== 'all' && (string)($item['priority'] ?? '') !== $priority) {
            return false;
        }
        $itemStatus = (string)($item['status'] ?? 'new_opportunity');
        if ($status === 'open') {
            return !in_array($itemStatus, ['won', 'lost', 'hold'], true);
        }
        if ($status !== 'all' && $itemStatus !== $status) {
            return false;
        }
        return true;
    }
}

if (!function_exists('lead_quality_summary')) {
    function lead_quality_summary(int $days = 30, string $type = 'all', string $priority = 'all', string $status = 'open'): array
    {
        $typeOptions = lead_quality_type_options();
        $priorityOptions = lead_quality_priority_options();
        $statusOptions = lead_quality_filter_options();
        $days = max(7, min(365, $days));
        $type = isset($typeOptions[$type]) ? $type : 'all';
        $priority = isset($priorityOptions[$priority]) ? $priority : 'all';
        $status = isset($statusOptions[$status]) ? $status : 'open';

        $all = lead_quality_candidates($days);
        $items = array_values(array_filter($all, static fn(array $item): bool => lead_quality_matches($item, $type, $priority, $status)));

        $counts = [
            'total' => count($all),
            'visible' => count($items),
            'hot' => 0,
            'warm' => 0,
            'watch' => 0,
            'cold' => 0,
            'orders' => 0,
            'inquiries' => 0,
            'lead_events' => 0,
            'contact_today' => 0,
            'waiting_payment' => 0,
            'scheduled' => 0,
            'won' => 0,
            'open' => 0,
        ];
        $scoreSum = 0;
        foreach ($all as $item) {
            $bucket = (string)($item['priority'] ?? 'cold');
            if (isset($counts[$bucket])) {
                $counts[$bucket]++;
            }
            $typeKey = (string)($item['type'] ?? '');
            if ($typeKey === 'order') {
                $counts['orders']++;
            } elseif ($typeKey === 'inquiry') {
                $counts['inquiries']++;
            } elseif ($typeKey === 'lead_event') {
                $counts['lead_events']++;
            }
            $st = (string)($item['status'] ?? 'new_opportunity');
            if (isset($counts[$st])) {
                $counts[$st]++;
            }
            if (!in_array($st, ['won', 'lost', 'hold'], true)) {
                $counts['open']++;
            }
            $scoreSum += (int)($item['score'] ?? 0);
        }
        $avg = $counts['total'] > 0 ? (int)round($scoreSum / $counts['total']) : 0;
        $top = $items[0] ?? null;

        return [
            'days' => $days,
            'filters' => [
                'type' => $type,
                'priority' => $priority,
                'status' => $status,
            ],
            'average_lead_score' => $avg,
            'top_focus' => $top ? 'Prioritaskan ' . (string)($top['name'] ?? 'lead teratas') . ' karena skornya paling dekat ke closing/follow-up.' : 'Belum ada lead/order/sinyal tracking pada periode ini.',
            'counts' => $counts,
            'items' => $items,
            'top_item' => $top,
            'status_options' => $statusOptions,
            'status_action_options' => lead_quality_status_options(),
            'priority_options' => $priorityOptions,
            'type_options' => $typeOptions,
            'generated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('lead_quality_record_crm_followup')) {
    function lead_quality_record_crm_followup(array $payload): bool
    {
        if (!function_exists('crm_store_followup')) {
            return false;
        }
        $targetType = lead_quality_clean($payload['target_type'] ?? 'manual', 30);
        if (!in_array($targetType, ['order', 'inquiry', 'manual'], true)) {
            $targetType = 'manual';
        }
        $targetId = lead_quality_clean($payload['target_id'] ?? '', 120);
        if ($targetId === '') {
            return false;
        }
        return crm_store_followup([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_ref' => lead_quality_clean($payload['target_ref'] ?? '', 120),
            'target_name' => lead_quality_clean($payload['target_name'] ?? '', 120),
            'phone' => lead_quality_clean($payload['phone'] ?? '', 50),
            'email' => lead_quality_clean($payload['email'] ?? '', 140),
            'subject' => lead_quality_clean($payload['subject'] ?? 'Follow-up dari Lead Priority Scoring', 160),
            'priority' => lead_quality_clean($payload['priority'] ?? 'Normal', 40),
            'outcome' => lead_quality_clean($payload['outcome'] ?? 'Catatan', 50),
            'note' => lead_quality_clean($payload['note'] ?? '', 1000),
            'next_followup_date' => lead_quality_clean($payload['next_followup_date'] ?? '', 20),
            'next_followup_time' => lead_quality_clean($payload['next_followup_time'] ?? '', 10),
            'created_by' => 'admin',
            'source' => 'lead-quality-followup-scoring',
        ]);
    }
}

if (!function_exists('lead_quality_export_csv')) {
    function lead_quality_export_csv(array $items): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="lead-quality-followup-scoring-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['priority', 'score', 'type', 'status', 'name', 'phone', 'email', 'subject', 'stage', 'source', 'page_path', 'reason', 'next_action', 'followup_count', 'next_followup_date', 'admin_note']);
        foreach ($items as $item) {
            fputcsv($out, [
                lead_quality_bucket_label((string)($item['priority'] ?? '')),
                (string)($item['score'] ?? 0),
                (string)($item['type_label'] ?? ''),
                (string)($item['status'] ?? ''),
                (string)($item['name'] ?? ''),
                (string)($item['phone'] ?? ''),
                (string)($item['email'] ?? ''),
                (string)($item['subject'] ?? ''),
                (string)($item['stage'] ?? ''),
                (string)($item['source'] ?? ''),
                (string)($item['page_path'] ?? ''),
                (string)($item['reason'] ?? ''),
                (string)($item['next_action'] ?? ''),
                (string)($item['followup_count'] ?? 0),
                (string)($item['next_followup_date'] ?? ''),
                (string)($item['admin_note'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
