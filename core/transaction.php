<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| TRANSACTION FLOW HARDENING + READINESS AUDIT - Template
|--------------------------------------------------------------------------
| Lightweight file-based audit helpers for the UMKM mini marketplace.
| This is still manual payment/invoice flow, but prepares safer transaction
| operations before future QRIS/payment gateway automation.
|--------------------------------------------------------------------------
*/

if (!function_exists('transaction_clean')) {
    function transaction_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('transaction_multiline_clean')) {
    function transaction_multiline_clean(string $value, int $max = 1200): string
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

if (!function_exists('transaction_audit_file')) {
    function transaction_audit_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/transaction-audit-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('transaction_store_event')) {
    function transaction_store_event(array $event): bool
    {
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }
        $now = time();
        $event = array_merge([
            'id' => 'tx_' . date('YmdHis', $now) . '_' . bin2hex(random_bytes(4)),
            'time' => date('c', $now),
            'category' => 'transaction',
            'action' => 'updated',
            'target_type' => '',
            'target_id' => '',
            'target_ref' => '',
            'admin_context' => 'admin',
            'page_path' => function_exists('current_uri') ? current_uri() : (string)($_SERVER['REQUEST_URI'] ?? ''),
        ], $event);

        foreach (['category', 'action', 'target_type', 'target_id', 'target_ref', 'admin_context', 'page_path'] as $key) {
            $event[$key] = transaction_clean((string)($event[$key] ?? ''), 160);
        }
        if (isset($event['note'])) {
            $event['note'] = transaction_multiline_clean((string)$event['note'], 800);
        }
        return @file_put_contents(
            transaction_audit_file($now),
            json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        ) !== false;
    }
}

if (!function_exists('transaction_log_files')) {
    function transaction_log_files(int $days = 30, array $filters = []): array
    {
        $files = glob(LOGS_PATH . '/transaction-audit-*.jsonl') ?: [];
        rsort($files);
        if (!empty($filters['_all_time']) || $days <= 0 || isset($filters['_start_ts']) || isset($filters['_end_ts'])) {
            return $files;
        }
        $start = strtotime('-' . max(1, min(3650, $days)) . ' days');
        return array_values(array_filter($files, static function (string $file) use ($start): bool {
            if (!preg_match('/transaction-audit-(\d{4})-(\d{2})\.jsonl$/', $file, $m)) {
                return true;
            }
            $monthTs = strtotime($m[1] . '-' . $m[2] . '-01 23:59:59') ?: time();
            return $monthTs >= $start;
        }));
    }
}

if (!function_exists('transaction_event_timestamp')) {
    function transaction_event_timestamp(array $event): int
    {
        $time = (string)($event['time'] ?? '');
        $ts = $time !== '' ? strtotime($time) : 0;
        return $ts ?: 0;
    }
}

if (!function_exists('transaction_matches_filters')) {
    function transaction_matches_filters(array $event, array $filters): bool
    {
        foreach (['category', 'action', 'target_type', 'target_ref'] as $key) {
            if (!empty($filters[$key]) && stripos((string)($event[$key] ?? ''), (string)$filters[$key]) === false) {
                return false;
            }
        }
        if (!empty($filters['search'])) {
            $search = strtolower((string)$filters['search']);
            $haystack = strtolower(implode(' ', array_map('strval', [
                $event['action'] ?? '',
                $event['target_ref'] ?? '',
                $event['target_id'] ?? '',
                $event['note'] ?? '',
                $event['page_path'] ?? '',
            ])));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('transaction_read_events')) {
    function transaction_read_events(int $days = 30, array $filters = [], int $max = 5000): array
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
        foreach (transaction_log_files($days, $filters) as $file) {
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
                $ts = transaction_event_timestamp($event);
                if ($ts <= 0) {
                    continue;
                }
                if ($startTs > 0 && $ts < $startTs) {
                    continue;
                }
                if ($endTs > 0 && $ts > $endTs) {
                    continue;
                }
                if (!transaction_matches_filters($event, $filters)) {
                    continue;
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

if (!function_exists('transaction_payment_status_rank')) {
    function transaction_payment_status_rank(string $status): int
    {
        return [
            'Belum Ditagih' => 0,
            'Menunggu Pembayaran' => 1,
            'DP Masuk' => 2,
            'Lunas' => 3,
            'Tidak Perlu Payment' => 3,
            'Refund' => 4,
        ][$status] ?? 0;
    }
}

if (!function_exists('transaction_validate_order_update')) {
    function transaction_validate_order_update(?array $currentOrder, string $newStatus, string $newPaymentStatus, string $paymentNote = ''): array
    {
        $errors = [];
        $warnings = [];
        $newStatus = transaction_clean($newStatus, 60);
        $newPaymentStatus = function_exists('order_normalize_payment_status') ? order_normalize_payment_status($newPaymentStatus) : transaction_clean($newPaymentStatus, 60);
        $paymentNote = transaction_multiline_clean($paymentNote, 800);
        $oldPaymentStatus = $currentOrder ? (string)($currentOrder['payment_status'] ?? 'Belum Ditagih') : 'Belum Ditagih';
        $oldStatus = $currentOrder ? (string)($currentOrder['status'] ?? 'Baru') : 'Baru';

        if ($newPaymentStatus === 'Refund' && strlen($paymentNote) < 8) {
            $errors[] = 'Status Refund wajib disertai catatan pembayaran yang jelas.';
        }
        if (in_array($oldPaymentStatus, ['DP Masuk', 'Lunas'], true)
            && transaction_payment_status_rank($newPaymentStatus) < transaction_payment_status_rank($oldPaymentStatus)
            && strlen($paymentNote) < 8) {
            $errors[] = 'Perubahan mundur dari status pembayaran ' . $oldPaymentStatus . ' membutuhkan catatan koreksi.';
        }
        if (in_array($newStatus, ['Batal', 'Spam'], true) && in_array($newPaymentStatus, ['DP Masuk', 'Lunas'], true) && strlen($paymentNote) < 8) {
            $errors[] = 'Order ' . $newStatus . ' dengan pembayaran ' . $newPaymentStatus . ' membutuhkan catatan admin/pembayaran.';
        }
        if ($newStatus === 'Selesai' && !in_array($newPaymentStatus, ['Lunas', 'Tidak Perlu Payment'], true)) {
            $warnings[] = 'Order ditandai selesai tetapi pembayaran belum Lunas/Tidak Perlu Payment.';
        }
        if ($oldStatus === 'Selesai' && $newStatus !== 'Selesai' && strlen($paymentNote) < 8) {
            $warnings[] = 'Order yang sudah selesai dibuka kembali. Tambahkan catatan agar audit trail lebih jelas.';
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}

if (!function_exists('transaction_order_issue')) {
    function transaction_order_issue(array $order, string $severity, string $code, string $title, string $description): array
    {
        return [
            'order_id' => (string)($order['id'] ?? ''),
            'order_ref' => function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? ''),
            'invoice_number' => function_exists('order_invoice_number') ? order_invoice_number($order) : (string)($order['invoice_number'] ?? ''),
            'customer' => (string)($order['name'] ?? ''),
            'product' => (string)($order['product_title'] ?? ''),
            'status' => (string)($order['status'] ?? ''),
            'payment_status' => (string)($order['payment_status'] ?? ''),
            'severity' => $severity,
            'code' => $code,
            'title' => $title,
            'description' => $description,
        ];
    }
}

if (!function_exists('transaction_order_issues')) {
    function transaction_order_issues(array $order): array
    {
        $issues = [];
        $status = (string)($order['status'] ?? 'Baru');
        $paymentStatus = (string)($order['payment_status'] ?? 'Belum Ditagih');
        $invoiceTotal = function_exists('order_invoice_total') ? order_invoice_total($order) : (int)($order['invoice_total'] ?? 0);
        $dueDate = transaction_clean((string)($order['invoice_due_date'] ?? ''), 20);
        $token = function_exists('order_public_token') ? order_public_token($order) : (string)($order['public_token'] ?? '');
        $paidLike = in_array($paymentStatus, ['DP Masuk', 'Lunas'], true);
        $waitingLike = in_array($paymentStatus, ['Menunggu Pembayaran', 'DP Masuk', 'Lunas'], true) || $status === 'Menunggu Pembayaran';

        if ($token === '') {
            $issues[] = transaction_order_issue($order, 'high', 'missing_public_token', 'Token publik belum tersedia', 'Link invoice/status sebaiknya memakai token agar tidak hanya mengandalkan nomor order.');
        }
        if ($waitingLike && $invoiceTotal <= 0) {
            $issues[] = transaction_order_issue($order, 'medium', 'invoice_total_empty', 'Nominal invoice belum diisi', 'Order sudah masuk tahap pembayaran tetapi nominal invoice masih kosong/0.');
        }
        if ($waitingLike && $dueDate === '') {
            $issues[] = transaction_order_issue($order, 'medium', 'invoice_due_missing', 'Jatuh tempo invoice belum diisi', 'Reminder H+ dan kadaluarsa invoice membutuhkan tanggal jatuh tempo yang jelas.');
        }
        if ($dueDate !== '' && strtotime($dueDate . ' 23:59:59') !== false && strtotime($dueDate . ' 23:59:59') < time() && !in_array($paymentStatus, ['DP Masuk', 'Lunas', 'Refund', 'Tidak Perlu Payment'], true)) {
            $issues[] = transaction_order_issue($order, 'high', 'invoice_overdue_unpaid', 'Invoice melewati jatuh tempo', 'Invoice sudah melewati jatuh tempo dan belum tercatat DP/Lunas.');
        }
        if ($paymentStatus === 'Lunas') {
            $proofs = function_exists('payment_proofs_for_order') ? payment_proofs_for_order($order, 20) : [];
            $hasReviewedProof = false;
            foreach ($proofs as $proof) {
                if (in_array((string)($proof['status'] ?? ''), ['Valid', 'DP Masuk', 'Lunas'], true)) {
                    $hasReviewedProof = true;
                    break;
                }
            }
            if (!$hasReviewedProof) {
                $issues[] = transaction_order_issue($order, 'low', 'paid_without_reviewed_proof', 'Pembayaran lunas tanpa bukti tervalidasi', 'Status Lunas boleh manual, tetapi sebaiknya ada bukti pembayaran yang sudah direview.');
            }
        }
        if ($status === 'Selesai' && !in_array($paymentStatus, ['Lunas', 'Tidak Perlu Payment'], true)) {
            $issues[] = transaction_order_issue($order, 'medium', 'done_unpaid', 'Order selesai tetapi pembayaran belum final', 'Order selesai sebaiknya payment sudah Lunas atau Tidak Perlu Payment.');
        }
        if ($paidLike && $invoiceTotal <= 0) {
            $issues[] = transaction_order_issue($order, 'medium', 'paid_zero_invoice', 'Pembayaran masuk tapi nominal invoice 0', 'DP/Lunas tercatat, tetapi nominal invoice belum diisi.');
        }
        return $issues;
    }
}

if (!function_exists('transaction_readiness_report')) {
    function transaction_readiness_report(array $orders): array
    {
        $issues = [];
        foreach ($orders as $order) {
            foreach (transaction_order_issues($order) as $issue) {
                $issues[] = $issue;
            }
        }
        $counts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($issues as $issue) {
            $sev = (string)($issue['severity'] ?? 'low');
            $counts[$sev] = ($counts[$sev] ?? 0) + 1;
        }
        $score = 100 - (($counts['critical'] ?? 0) * 15) - (($counts['high'] ?? 0) * 10) - (($counts['medium'] ?? 0) * 5) - (($counts['low'] ?? 0) * 2);
        $score = max(0, min(100, $score));
        return [
            'score' => $score,
            'issues' => $issues,
            'counts' => $counts,
            'total_orders' => count($orders),
        ];
    }
}

if (!function_exists('transaction_count_by')) {
    function transaction_count_by(array $items, string $key, int $limit = 8): array
    {
        $counts = [];
        foreach ($items as $item) {
            $value = trim((string)($item[$key] ?? '')) ?: 'Tidak diketahui';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('transaction_flow_checklist')) {
    function transaction_flow_checklist(array $orders): array
    {
        $hasOrder = count($orders) > 0;
        $hasInvoice = false;
        $hasDueDate = false;
        $hasWaitingPayment = false;
        $hasPaymentProof = false;
        $hasPaid = false;
        $hasReminder = false;
        foreach ($orders as $order) {
            if (function_exists('order_invoice_number') && order_invoice_number($order) !== '') {
                $hasInvoice = true;
            }
            if (!empty($order['invoice_due_date'])) {
                $hasDueDate = true;
            }
            if (($order['payment_status'] ?? '') === 'Menunggu Pembayaran' || ($order['status'] ?? '') === 'Menunggu Pembayaran') {
                $hasWaitingPayment = true;
            }
            if (in_array((string)($order['payment_status'] ?? ''), ['DP Masuk', 'Lunas'], true)) {
                $hasPaid = true;
            }
            if (function_exists('payment_proofs_for_order') && payment_proofs_for_order($order, 1)) {
                $hasPaymentProof = true;
            }
            if (function_exists('payment_reminder_last_for_order') && payment_reminder_last_for_order($order)) {
                $hasReminder = true;
            }
        }
        return [
            ['label' => 'Order/checkout masuk', 'done' => $hasOrder, 'note' => 'Data awal calon pesanan dari halaman checkout.'],
            ['label' => 'Invoice manual tersedia', 'done' => $hasInvoice, 'note' => 'Nomor invoice dan instruksi pembayaran bisa dikirim.'],
            ['label' => 'Jatuh tempo invoice', 'done' => $hasDueDate, 'note' => 'Dibutuhkan untuk reminder H+ dan kadaluarsa.'],
            ['label' => 'Status menunggu pembayaran', 'done' => $hasWaitingPayment, 'note' => 'Order sudah masuk tahap follow-up pembayaran.'],
            ['label' => 'Upload bukti pembayaran', 'done' => $hasPaymentProof, 'note' => 'Customer bisa upload bukti dari invoice publik.'],
            ['label' => 'Pembayaran DP/Lunas tercatat', 'done' => $hasPaid, 'note' => 'Admin sudah menandai pembayaran masuk.'],
            ['label' => 'Reminder pembayaran tercatat', 'done' => $hasReminder, 'note' => 'Follow-up invoice dari dashboard reminder.'],
        ];
    }
}

if (!function_exists('transaction_gateway_readiness_notes')) {
    function transaction_gateway_readiness_notes(): array
    {
        return [
            'Pastikan setiap order punya ref publik dan token sebelum link invoice/status dikirim.',
            'Nominal invoice harus final sebelum nanti dibuatkan payment gateway charge/QRIS dinamis.',
            'Payment status Lunas/Refund sebaiknya selalu punya catatan atau bukti pembayaran yang bisa diaudit.',
            'Webhook payment gateway nanti wajib memvalidasi signature, nominal, order ref, dan status idempotent.',
            'Halaman checkout, invoice, order-status, dan bukti pembayaran tetap noindex karena fokusnya conversion, bukan SEO indexing.',
        ];
    }
}
