<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| PAYMENT REMINDER CENTER - Template
|--------------------------------------------------------------------------
| Lightweight file-based helpers to monitor manual invoices, calculate H+1,
| H+2, due-today and expired states, then help admin follow up via WhatsApp
| or email. This is not an automatic scheduler/cron; it prepares a practical
| manual reminder center for UMKM-friendly payment follow-up.
|--------------------------------------------------------------------------
*/

if (!function_exists('payment_reminder_enabled')) {
    function payment_reminder_enabled(): bool
    {
        $value = strtolower(trim((string)($_ENV['ENABLE_PAYMENT_REMINDERS'] ?? 'true')));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('payment_reminder_clean')) {
    function payment_reminder_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('payment_reminder_multiline_clean')) {
    function payment_reminder_multiline_clean(string $value, int $max = 1600): string
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

if (!function_exists('payment_reminder_completed_statuses')) {
    function payment_reminder_completed_statuses(): array
    {
        return ['DP Masuk', 'Lunas', 'Tidak Perlu Payment', 'Refund'];
    }
}


if (!function_exists('payment_reminder_record_completion')) {
    function payment_reminder_record_completion(array $order, string $previousStatus = '', string $source = 'order_update_status'): bool
    {
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? '');
        $paymentStatus = (string)($order['payment_status'] ?? '');
        if ($ref === '' || !in_array($paymentStatus, payment_reminder_completed_statuses(), true)) {
            return false;
        }
        return payment_reminder_store_event([
            'type' => 'payment_completed_runtime',
            'order_id' => (string)($order['id'] ?? ''),
            'order_ref' => $ref,
            'invoice_number' => function_exists('order_invoice_number') ? order_invoice_number($order) : (string)($order['invoice_number'] ?? ''),
            'stage' => 'Pembayaran selesai',
            'stage_key' => 'completed',
            'channel' => 'system',
            'status' => 'closed',
            'previous_payment_status' => payment_reminder_clean($previousStatus, 80),
            'payment_status' => payment_reminder_clean($paymentStatus, 80),
            'source' => payment_reminder_clean($source, 80),
            'note' => 'Order sudah masuk status pembayaran selesai sehingga tidak lagi menjadi kandidat reminder aktif.',
        ]);
    }
}

if (!function_exists('payment_reminder_log_file')) {
    function payment_reminder_log_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/payment-reminders-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('payment_reminder_store_event')) {
    function payment_reminder_store_event(array $event): bool
    {
        if (!payment_reminder_enabled()) {
            return false;
        }
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }
        $event = array_merge([
            'time' => date('c'),
            'type' => 'manual_reminder',
            'channel' => 'note',
            'status' => 'recorded',
        ], $event);
        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return @file_put_contents(payment_reminder_log_file(), $line, FILE_APPEND | LOCK_EX) !== false;
    }
}

if (!function_exists('payment_reminder_log_files')) {
    function payment_reminder_log_files(int $days = 30, array $filters = []): array
    {
        if (!defined('LOGS_PATH') || !is_dir(LOGS_PATH)) {
            return [];
        }
        $files = glob(LOGS_PATH . '/payment-reminders-*.jsonl') ?: [];
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
            if (!preg_match('/payment-reminders-(\d{4})-(\d{2})\.jsonl$/', $file, $matches)) {
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

if (!function_exists('payment_reminder_event_timestamp')) {
    function payment_reminder_event_timestamp(array $event): int
    {
        $time = (string)($event['time'] ?? '');
        $timestamp = $time !== '' ? strtotime($time) : false;
        return $timestamp !== false ? (int)$timestamp : 0;
    }
}

if (!function_exists('payment_reminder_read_events')) {
    function payment_reminder_read_events(int $days = 365, array $filters = [], int $max = 5000): array
    {
        $max = max(50, min(50000, $max));
        $events = [];
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) {
            $days = 0;
        }
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        foreach (payment_reminder_log_files($days, $filters) as $file) {
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
                $ts = payment_reminder_event_timestamp($event);
                if ($ts <= 0) {
                    continue;
                }
                if ($startTs > 0 && $ts < $startTs) {
                    continue;
                }
                if ($endTs > 0 && $ts > $endTs) {
                    continue;
                }
                foreach (['order_ref', 'channel', 'stage'] as $key) {
                    $filter = strtolower(trim((string)($filters[$key] ?? '')));
                    if ($filter !== '' && !str_contains(strtolower((string)($event[$key] ?? '')), $filter)) {
                        continue 2;
                    }
                }
                $event['_ts'] = $ts;
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

if (!function_exists('payment_reminder_last_events_by_order')) {
    function payment_reminder_last_events_by_order(int $days = 3650): array
    {
        $events = payment_reminder_read_events($days, ['_all_time' => true], 50000);
        $map = [];
        foreach ($events as $event) {
            $ref = payment_reminder_clean((string)($event['order_ref'] ?? ''), 80);
            if ($ref === '') {
                continue;
            }
            if (!isset($map[$ref]) || (int)($event['_ts'] ?? 0) > (int)($map[$ref]['_ts'] ?? 0)) {
                $map[$ref] = $event;
            }
        }
        return $map;
    }
}

if (!function_exists('payment_reminder_invoice_started_at')) {
    function payment_reminder_invoice_started_at(array $order): int
    {
        foreach (['invoice_updated_at', 'status_updated_at', 'time'] as $key) {
            $raw = (string)($order[$key] ?? '');
            if ($raw === '') {
                continue;
            }
            $ts = strtotime($raw);
            if ($ts !== false) {
                return (int)$ts;
            }
        }
        return time();
    }
}

if (!function_exists('payment_reminder_due_date')) {
    function payment_reminder_due_date(array $order): string
    {
        $due = payment_reminder_clean((string)($order['invoice_due_date'] ?? ''), 20);
        if ($due !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            return $due;
        }
        if (function_exists('order_invoice_default_due_date')) {
            return order_invoice_default_due_date();
        }
        return date('Y-m-d', strtotime('+3 days'));
    }
}

if (!function_exists('payment_reminder_meta')) {
    function payment_reminder_meta(array $order): array
    {
        $startedAt = payment_reminder_invoice_started_at($order);
        $startedDate = date('Y-m-d', $startedAt);
        $today = strtotime(date('Y-m-d 00:00:00')) ?: time();
        $startDay = strtotime($startedDate . ' 00:00:00') ?: $startedAt;
        $ageDays = max(0, (int)floor(($today - $startDay) / 86400));

        $dueDate = payment_reminder_due_date($order);
        $dueTs = strtotime($dueDate . ' 00:00:00') ?: strtotime(date('Y-m-d 00:00:00', strtotime('+3 days')));
        $daysToDue = (int)floor(($dueTs - $today) / 86400);
        $daysOverdue = $daysToDue < 0 ? abs($daysToDue) : 0;

        $paymentStatus = (string)($order['payment_status'] ?? 'Belum Ditagih');
        $isCompleted = in_array($paymentStatus, payment_reminder_completed_statuses(), true);
        $stage = 'Belum Perlu Reminder';
        $stageKey = 'not_due';
        $priority = 'Normal';

        if ($isCompleted) {
            $stage = 'Pembayaran selesai';
            $stageKey = 'completed';
            $priority = 'Rendah';
        } elseif ($daysToDue < 0) {
            $stage = 'Kadaluarsa H+' . $daysOverdue;
            $stageKey = 'expired';
            $priority = 'Sangat Panas';
        } elseif ($daysToDue === 0) {
            $stage = 'Jatuh tempo hari ini';
            $stageKey = 'due_today';
            $priority = 'Sangat Panas';
        } elseif ($daysToDue === 1) {
            $stage = 'H-1 jatuh tempo';
            $stageKey = 'due_tomorrow';
            $priority = 'Tinggi';
        } elseif ($ageDays >= 1) {
            $stage = 'H+' . $ageDays . ' sejak invoice';
            $stageKey = 'h_plus_' . min(30, $ageDays);
            $priority = $ageDays >= 2 ? 'Tinggi' : 'Normal';
        }

        return [
            'invoice_started_at' => date('c', $startedAt),
            'invoice_started_label' => date('d M Y', $startedAt),
            'age_days' => $ageDays,
            'due_date' => $dueDate,
            'due_label' => date('d M Y', $dueTs),
            'days_to_due' => $daysToDue,
            'days_overdue' => $daysOverdue,
            'stage' => $stage,
            'stage_key' => $stageKey,
            'priority' => $priority,
            'is_completed' => $isCompleted,
        ];
    }
}

if (!function_exists('payment_reminder_order_is_candidate')) {
    function payment_reminder_order_is_candidate(array $order, bool $includeCompleted = false): bool
    {
        $invoice = function_exists('order_invoice_number') ? order_invoice_number($order) : (string)($order['invoice_number'] ?? '');
        $hasInvoiceSignal = $invoice !== '' || (int)($order['invoice_total'] ?? 0) > 0 || !empty($order['invoice_due_date']) || !empty($order['invoice_payment_instruction']);
        if (!$hasInvoiceSignal) {
            return false;
        }
        $paymentStatus = (string)($order['payment_status'] ?? 'Belum Ditagih');
        if (!$includeCompleted && in_array($paymentStatus, payment_reminder_completed_statuses(), true)) {
            return false;
        }
        $status = (string)($order['status'] ?? '');
        if (in_array($status, ['Batal', 'Selesai', 'Spam'], true) && !$includeCompleted) {
            return false;
        }
        return true;
    }
}

if (!function_exists('payment_reminder_candidates')) {
    function payment_reminder_candidates(int $days = 0, array $filters = [], int $max = 5000): array
    {
        $includeCompleted = !empty($filters['include_completed']);
        $orders = function_exists('order_read_all') ? order_read_all($days, $filters, $max) : [];
        $lastMap = payment_reminder_last_events_by_order();
        $items = [];
        foreach ($orders as $order) {
            if (!payment_reminder_order_is_candidate($order, $includeCompleted)) {
                continue;
            }
            $meta = payment_reminder_meta($order);
            $stageFilter = payment_reminder_clean((string)($filters['stage'] ?? ''), 60);
            if ($stageFilter !== '' && $stageFilter !== $meta['stage_key']) {
                continue;
            }
            $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? '');
            $order['_reminder'] = $meta;
            $order['_last_reminder'] = $lastMap[$ref] ?? null;
            $items[] = $order;
        }
        usort($items, static function (array $a, array $b): int {
            $am = (array)($a['_reminder'] ?? []);
            $bm = (array)($b['_reminder'] ?? []);
            $ap = ['Sangat Panas' => 4, 'Tinggi' => 3, 'Normal' => 2, 'Rendah' => 1][(string)($am['priority'] ?? 'Normal')] ?? 2;
            $bp = ['Sangat Panas' => 4, 'Tinggi' => 3, 'Normal' => 2, 'Rendah' => 1][(string)($bm['priority'] ?? 'Normal')] ?? 2;
            if ($ap !== $bp) {
                return $bp <=> $ap;
            }
            return ((int)($a['_ts'] ?? 0)) <=> ((int)($b['_ts'] ?? 0));
        });
        return $items;
    }
}

if (!function_exists('payment_reminder_summary')) {
    function payment_reminder_summary(array $items): array
    {
        $summary = [
            'total' => count($items),
            'due_today' => 0,
            'due_tomorrow' => 0,
            'expired' => 0,
            'h_plus' => 0,
            'hot' => 0,
            'by_stage' => [],
            'by_payment_status' => [],
            'recent' => array_slice($items, 0, 60),
        ];
        foreach ($items as $item) {
            $meta = (array)($item['_reminder'] ?? []);
            $stageKey = (string)($meta['stage_key'] ?? 'not_due');
            $stage = (string)($meta['stage'] ?? 'Belum Perlu Reminder');
            $summary['by_stage'][$stage] = ($summary['by_stage'][$stage] ?? 0) + 1;
            $paymentStatus = (string)($item['payment_status'] ?? 'Belum Ditagih');
            $summary['by_payment_status'][$paymentStatus] = ($summary['by_payment_status'][$paymentStatus] ?? 0) + 1;
            if ($stageKey === 'due_today') { $summary['due_today']++; }
            if ($stageKey === 'due_tomorrow') { $summary['due_tomorrow']++; }
            if ($stageKey === 'expired') { $summary['expired']++; }
            if (str_starts_with($stageKey, 'h_plus_')) { $summary['h_plus']++; }
            if (in_array((string)($meta['priority'] ?? ''), ['Tinggi', 'Sangat Panas'], true)) { $summary['hot']++; }
        }
        arsort($summary['by_stage']);
        arsort($summary['by_payment_status']);
        return $summary;
    }
}

if (!function_exists('payment_reminder_whatsapp_message')) {
    function payment_reminder_whatsapp_message(array $order): string
    {
        $meta = (array)($order['_reminder'] ?? payment_reminder_meta($order));
        $name = payment_reminder_clean((string)($order['name'] ?? 'Kak'), 80) ?: 'Kak';
        $total = function_exists('order_invoice_total') ? order_invoice_total($order) : 0;
        $message = "Halo " . $name . ", izin mengingatkan invoice pesanan Anda di " . SITE_NAME . ".\n\n";
        $message .= "No. Invoice: " . (function_exists('order_invoice_number') ? order_invoice_number($order) : (string)($order['invoice_number'] ?? '-')) . "\n";
        $message .= "No. Order: " . (function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? '-')) . "\n";
        $message .= "Produk/Layanan: " . (string)($order['product_title'] ?? 'Pesanan') . "\n";
        if ($total > 0 && function_exists('rupiah')) {
            $message .= "Total Invoice: " . rupiah($total) . "\n";
        }
        $message .= "Jatuh Tempo: " . (string)($meta['due_label'] ?? '-') . "\n";
        $message .= "Status: " . (string)($order['payment_status'] ?? 'Menunggu Pembayaran') . "\n\n";
        if ((string)($meta['stage_key'] ?? '') === 'expired') {
            $message .= "Invoice sudah melewati batas waktu follow-up. Jika masih ingin melanjutkan pesanan, mohon konfirmasi agar admin bisa bantu cek ulang stok/jadwal.\n\n";
        } elseif ((string)($meta['stage_key'] ?? '') === 'due_today') {
            $message .= "Invoice jatuh tempo hari ini. Jika sudah melakukan pembayaran, mohon upload/kirim bukti pembayaran agar admin bisa bantu verifikasi.\n\n";
        } else {
            $message .= "Jika sudah melakukan pembayaran, mohon upload/kirim bukti pembayaran agar admin bisa bantu verifikasi.\n\n";
        }
        if (function_exists('order_public_invoice_url')) {
            $message .= "Link invoice: " . order_public_invoice_url($order) . "\n";
        }
        $message .= "\nTerima kasih.";
        return payment_reminder_multiline_clean($message, 1800);
    }
}

if (!function_exists('payment_reminder_email_subject')) {
    function payment_reminder_email_subject(array $order): string
    {
        $invoice = function_exists('order_invoice_number') ? order_invoice_number($order) : (string)($order['invoice_number'] ?? 'INV');
        return 'Reminder invoice: ' . $invoice;
    }
}

if (!function_exists('payment_reminder_email_body')) {
    function payment_reminder_email_body(array $order): string
    {
        $meta = (array)($order['_reminder'] ?? payment_reminder_meta($order));
        $total = function_exists('order_invoice_total') ? order_invoice_total($order) : 0;
        $fields = [
            'No. Invoice' => function_exists('order_invoice_number') ? order_invoice_number($order) : (string)($order['invoice_number'] ?? '-'),
            'No. Order' => function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? '-'),
            'Produk/Layanan' => (string)($order['product_title'] ?? 'Pesanan'),
            'Status Pembayaran' => (string)($order['payment_status'] ?? 'Menunggu Pembayaran'),
            'Jatuh Tempo' => (string)($meta['due_label'] ?? '-'),
            'Tahap Reminder' => (string)($meta['stage'] ?? '-'),
        ];
        if ($total > 0 && function_exists('rupiah')) {
            $fields['Total Invoice'] = rupiah($total);
        }
        $notes = ['Jika sudah melakukan pembayaran, silakan upload bukti pembayaran melalui halaman invoice atau hubungi admin WhatsApp.'];
        return function_exists('notification_render_template')
            ? notification_render_template(
                'Reminder Invoice / Pembayaran',
                "Halo " . (string)($order['name'] ?? 'Kak') . ",\n\nIni adalah reminder untuk invoice pesanan Anda.",
                $fields,
                'Buka invoice',
                function_exists('order_public_invoice_url') ? order_public_invoice_url($order) : '',
                $notes
            )
            : payment_reminder_whatsapp_message($order);
    }
}

if (!function_exists('payment_reminder_send_email')) {
    function payment_reminder_send_email(array $order): bool
    {
        $email = trim((string)($order['email'] ?? ''));
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? '');
        if (!function_exists('notification_send_email')) {
            payment_reminder_store_event(['order_ref' => $ref, 'channel' => 'email', 'status' => 'failed', 'error' => 'notification_send_email tidak tersedia.']);
            return false;
        }
        $ok = notification_send_email($email, payment_reminder_email_subject($order), payment_reminder_email_body($order), ['type' => 'payment_reminder_customer', 'target_type' => 'order', 'target_ref' => $ref]);
        payment_reminder_store_event([
            'order_id' => (string)($order['id'] ?? ''),
            'order_ref' => $ref,
            'invoice_number' => function_exists('order_invoice_number') ? order_invoice_number($order) : (string)($order['invoice_number'] ?? ''),
            'stage' => (string)(payment_reminder_meta($order)['stage'] ?? ''),
            'channel' => 'email',
            'status' => $ok ? 'sent' : 'failed',
            'target' => $email,
        ]);
        return $ok;
    }
}
