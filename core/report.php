<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| LIGHTWEIGHT REPORT DASHBOARD ENGINE - Template
|--------------------------------------------------------------------------
| File-based reporting layer for sales, leads, orders, and manual payment
| proof flow. This intentionally stays lightweight and shared-hosting-safe.
|--------------------------------------------------------------------------
*/

if (!function_exists('report_clean')) {
    function report_clean(string $value, int $max = 120): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('report_allowed_ranges')) {
    function report_allowed_ranges(): array
    {
        return ['7', '14', '30', '60', '90', '180', '365', 'year', 'all', 'custom'];
    }
}

if (!function_exists('report_normalize_range')) {
    function report_normalize_range(string $range): string
    {
        $range = strtolower(trim($range));
        return in_array($range, report_allowed_ranges(), true) ? $range : '30';
    }
}

if (!function_exists('report_date_input')) {
    function report_date_input(string $value): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}

if (!function_exists('report_window_from_filters')) {
    function report_window_from_filters(int $days = 30, array $filters = []): array
    {
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;

        if ($startTs > 0 || $endTs > 0) {
            return [
                'start_ts' => $startTs > 0 ? $startTs : null,
                'end_ts' => $endTs > 0 ? $endTs : time(),
                'all_time' => false,
            ];
        }

        if (!empty($filters['_all_time']) || $days <= 0) {
            return [
                'start_ts' => null,
                'end_ts' => time(),
                'all_time' => true,
            ];
        }

        $days = max(1, min(3650, $days));
        return [
            'start_ts' => time() - ($days * 86400),
            'end_ts' => time(),
            'all_time' => false,
        ];
    }
}

if (!function_exists('report_filters_from_request')) {
    function report_filters_from_request(array $source): array
    {
        $range = report_normalize_range((string)($source['range'] ?? ($source['days'] ?? '30')));
        $year = trim((string)($source['year'] ?? date('Y')));
        if (!preg_match('/^\d{4}$/', $year)) {
            $year = date('Y');
        }

        $filters = array_filter([
            'source' => report_clean((string)($source['source'] ?? ''), 80),
            'category' => report_clean((string)($source['category'] ?? ''), 80),
            'location' => report_clean((string)($source['location'] ?? ''), 100),
            'need' => report_clean((string)($source['need'] ?? ''), 120),
            'status' => report_clean((string)($source['status'] ?? ''), 80),
            'payment_status' => report_clean((string)($source['payment_status'] ?? ''), 80),
            'payment_method' => report_clean((string)($source['payment_method'] ?? ''), 80),
            'product_title' => report_clean((string)($source['product_title'] ?? ''), 120),
            'search' => report_clean((string)($source['search'] ?? ''), 120),
        ], static fn($value): bool => $value !== '' && $value !== null && $value !== false);

        if ($range === 'all') {
            $filters['_all_time'] = true;
        } elseif ($range === 'year') {
            $filters['_start_ts'] = strtotime($year . '-01-01 00:00:00') ?: 0;
            $filters['_end_ts'] = strtotime($year . '-12-31 23:59:59') ?: time();
            $filters['_year'] = $year;
        } elseif ($range === 'custom') {
            $from = report_date_input((string)($source['date_from'] ?? ''));
            $to = report_date_input((string)($source['date_to'] ?? ''));
            if ($from !== '') {
                $filters['_start_ts'] = strtotime($from . ' 00:00:00') ?: 0;
                $filters['_date_from'] = $from;
            }
            if ($to !== '') {
                $filters['_end_ts'] = strtotime($to . ' 23:59:59') ?: time();
                $filters['_date_to'] = $to;
            }
        }

        return [
            'range' => $range,
            'year' => $year,
            'days' => in_array($range, ['all', 'year', 'custom'], true) ? 0 : max(1, min(3650, (int)$range)),
            'filters' => $filters,
            'date_from' => (string)($filters['_date_from'] ?? ''),
            'date_to' => (string)($filters['_date_to'] ?? ''),
        ];
    }
}

if (!function_exists('report_filters_for')) {
    function report_filters_for(array $filters, string $area): array
    {
        $common = [];
        foreach (['_all_time', '_start_ts', '_end_ts', '_year', '_date_from', '_date_to', 'source', 'category', 'location', 'need', 'search'] as $key) {
            if (array_key_exists($key, $filters)) {
                $common[$key] = $filters[$key];
            }
        }

        if ($area === 'orders') {
            foreach (['status', 'payment_status', 'payment_method', 'product_title'] as $key) {
                if (array_key_exists($key, $filters)) {
                    $common[$key] = $filters[$key];
                }
            }
            return $common;
        }

        if ($area === 'proofs') {
            foreach (['status', 'payment_method', 'product_title'] as $key) {
                if (array_key_exists($key, $filters)) {
                    $common[$key] = $filters[$key];
                }
            }
            return $common;
        }

        if ($area === 'leads') {
            foreach (['intent'] as $key) {
                if (array_key_exists($key, $filters)) {
                    $common[$key] = $filters[$key];
                }
            }
            return $common;
        }

        return $common;
    }
}

if (!function_exists('report_event_ts')) {
    function report_event_ts(array $item): int
    {
        if (isset($item['_ts'])) {
            return (int)$item['_ts'];
        }
        foreach (['time', 'created_at', 'submitted_at', 'updated_at', 'date'] as $key) {
            $value = trim((string)($item[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $ts = strtotime($value);
            if ($ts !== false) {
                return $ts;
            }
        }
        return 0;
    }
}

if (!function_exists('report_order_value')) {
    function report_order_value(array $order): int
    {
        if (function_exists('order_invoice_total')) {
            return max(0, (int)order_invoice_total($order));
        }
        return max(0, ((int)($order['price'] ?? 0)) * max(1, (int)($order['quantity'] ?? 1)));
    }
}

if (!function_exists('report_count_by')) {
    function report_count_by(array $items, string $key, int $limit = 8): array
    {
        $counts = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = trim((string)($item[$key] ?? ''));
            $value = $value !== '' ? $value : 'Tidak diketahui';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('report_daily_template')) {
    function report_daily_template(?int $startTs, ?int $endTs, bool $allTime, int $fallbackDays = 30): array
    {
        $endTs = $endTs ?: time();
        if ($startTs === null || $startTs <= 0) {
            $fallbackDays = $allTime ? 30 : max(1, min(120, $fallbackDays));
            $startTs = strtotime('-' . ($fallbackDays - 1) . ' days', strtotime(date('Y-m-d 00:00:00', $endTs))) ?: time();
        }

        $diffDays = max(1, (int)ceil(($endTs - $startTs) / 86400) + 1);
        if ($diffDays > 120) {
            $startTs = strtotime('-119 days', strtotime(date('Y-m-d 00:00:00', $endTs))) ?: $startTs;
            $diffDays = 120;
        }

        $daily = [];
        for ($i = $diffDays - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days', strtotime(date('Y-m-d 00:00:00', $endTs))));
            $daily[$day] = [
                'date' => $day,
                'lead_events' => 0,
                'inquiries' => 0,
                'orders' => 0,
                'payment_proofs' => 0,
                'sales_estimate' => 0,
                'payment_amount' => 0,
            ];
        }
        return $daily;
    }
}

if (!function_exists('report_daily_series')) {
    function report_daily_series(array $leadEvents, array $inquiries, array $orders, array $proofs, array $window, int $fallbackDays = 30): array
    {
        $daily = report_daily_template(
            $window['start_ts'] ?? null,
            $window['end_ts'] ?? null,
            !empty($window['all_time']),
            $fallbackDays
        );

        foreach ($leadEvents as $event) {
            $day = date('Y-m-d', report_event_ts((array)$event));
            if (isset($daily[$day])) {
                $daily[$day]['lead_events']++;
            }
        }
        foreach ($inquiries as $item) {
            $day = date('Y-m-d', report_event_ts((array)$item));
            if (isset($daily[$day])) {
                $daily[$day]['inquiries']++;
            }
        }
        foreach ($orders as $order) {
            $day = date('Y-m-d', report_event_ts((array)$order));
            if (isset($daily[$day])) {
                $daily[$day]['orders']++;
                $daily[$day]['sales_estimate'] += report_order_value((array)$order);
            }
        }
        foreach ($proofs as $proof) {
            $day = date('Y-m-d', report_event_ts((array)$proof));
            if (isset($daily[$day])) {
                $daily[$day]['payment_proofs']++;
                $daily[$day]['payment_amount'] += max(0, (int)($proof['amount'] ?? 0));
            }
        }

        return $daily;
    }
}

if (!function_exists('report_payment_amount')) {
    function report_payment_amount(array $proofs, bool $validOnly = false): int
    {
        $total = 0;
        foreach ($proofs as $proof) {
            $status = (string)($proof['status'] ?? 'Menunggu Review');
            if ($validOnly && !in_array($status, ['Valid', 'DP Masuk', 'Lunas'], true)) {
                continue;
            }
            $total += max(0, (int)($proof['amount'] ?? 0));
        }
        return $total;
    }
}

if (!function_exists('report_dashboard_summary')) {
    function report_dashboard_summary(int $days = 30, array $filters = []): array
    {
        $days = $days > 0 ? max(1, min(3650, $days)) : 0;
        $window = report_window_from_filters($days, $filters);

        $leadFilters = report_filters_for($filters, 'leads');
        $inquiryFilters = report_filters_for($filters, 'inquiries');
        $orderFilters = report_filters_for($filters, 'orders');
        $proofFilters = report_filters_for($filters, 'proofs');

        $rawLeadEvents = function_exists('conversion_read_lead_events') ? conversion_read_lead_events($days, $leadFilters, 120000) : [];
        $leadEvents = function_exists('conversion_dedupe_lead_events') ? conversion_dedupe_lead_events($rawLeadEvents, 10) : $rawLeadEvents;
        $reportLeadRecent = function_exists('conversion_prioritized_lead_events') ? conversion_prioritized_lead_events($rawLeadEvents, 12) : array_slice($leadEvents, 0, 12);
        $inquiries = function_exists('inquiry_read_all') ? inquiry_read_all($days, $inquiryFilters, 50000) : [];
        $orders = function_exists('order_read_all') ? order_read_all($days, $orderFilters, 50000) : [];
        $proofs = function_exists('payment_proof_read_all') ? payment_proof_read_all($days, $proofFilters, 50000) : [];

        $salesEstimate = 0;
        $paidOrderValue = 0;
        $paymentWaiting = 0;
        $completedOrders = 0;
        $cancelledOrders = 0;
        $newOrders = 0;

        foreach ($orders as $order) {
            $value = report_order_value((array)$order);
            $salesEstimate += $value;
            $status = (string)($order['status'] ?? 'Baru');
            $paymentStatus = (string)($order['payment_status'] ?? 'Belum Ditagih');
            if ($status === 'Baru') {
                $newOrders++;
            }
            if (in_array($status, ['Selesai', 'Deal'], true)) {
                $completedOrders++;
            }
            if (in_array($status, ['Batal', 'Spam'], true)) {
                $cancelledOrders++;
            }
            if ($status === 'Menunggu Pembayaran' || $paymentStatus === 'Menunggu Pembayaran') {
                $paymentWaiting++;
            }
            if (in_array($paymentStatus, ['DP Masuk', 'Lunas'], true)) {
                $paidOrderValue += $value;
            }
        }

        $validProofs = 0;
        $pendingProofs = 0;
        foreach ($proofs as $proof) {
            $status = (string)($proof['status'] ?? 'Menunggu Review');
            if ($status === 'Menunggu Review') {
                $pendingProofs++;
            }
            if (in_array($status, ['Valid', 'DP Masuk', 'Lunas'], true)) {
                $validProofs++;
            }
        }

        $daily = report_daily_series($leadEvents, $inquiries, $orders, $proofs, $window, $days ?: 30);
        $maxDaily = 0;
        foreach ($daily as $row) {
            $maxDaily = max($maxDaily, (int)$row['lead_events'], (int)$row['inquiries'], (int)$row['orders'], (int)$row['payment_proofs']);
        }

        $totalLeadsRaw = count($rawLeadEvents);
        $totalLeads = count($leadEvents);
        $totalInquiries = count($inquiries);
        $totalOrders = count($orders);
        $totalProofs = count($proofs);
        $leadToOrderRate = $totalLeads > 0 ? round(($totalOrders / $totalLeads) * 100, 1) : 0.0;
        $inquiryToOrderRate = $totalInquiries > 0 ? round(($totalOrders / $totalInquiries) * 100, 1) : 0.0;
        $proofToOrderRate = $totalOrders > 0 ? round(($totalProofs / $totalOrders) * 100, 1) : 0.0;

        return [
            'generated_at' => date('c'),
            'days' => $days,
            'filters' => $filters,
            'window' => [
                'start' => $window['start_ts'] ? date('Y-m-d', (int)$window['start_ts']) : null,
                'end' => $window['end_ts'] ? date('Y-m-d', (int)$window['end_ts']) : null,
                'all_time' => !empty($window['all_time']),
            ],
            'sales' => [
                'estimate' => $salesEstimate,
                'paid_order_value' => $paidOrderValue,
                'payment_amount_total' => report_payment_amount($proofs, false),
                'payment_amount_valid' => report_payment_amount($proofs, true),
                'average_order_value' => $totalOrders > 0 ? (int)round($salesEstimate / $totalOrders) : 0,
            ],
            'lead' => [
                'events' => $totalLeads,
                'events_raw' => $totalLeadsRaw,
                'events_compact' => $totalLeads,
                'high_intent' => count(array_filter($leadEvents, static fn($event): bool => (string)($event['_event_kind'] ?? '') === 'high_intent')),
                'support' => count(array_filter($leadEvents, static fn($event): bool => (string)($event['_event_kind'] ?? '') !== 'high_intent')),
                'whatsapp' => count(array_filter($leadEvents, static fn($event): bool => !empty($event['is_whatsapp']))),
                'inquiries' => $totalInquiries,
                'new_inquiries' => count(array_filter($inquiries, static fn($item): bool => (string)($item['status'] ?? 'Baru') === 'Baru')),
            ],
            'order' => [
                'total' => $totalOrders,
                'new' => $newOrders,
                'completed' => $completedOrders,
                'cancelled' => $cancelledOrders,
                'payment_waiting' => $paymentWaiting,
            ],
            'payment' => [
                'proofs' => $totalProofs,
                'pending_proofs' => $pendingProofs,
                'valid_proofs' => $validProofs,
                'proof_to_order_rate' => $proofToOrderRate,
            ],
            'conversion' => [
                'lead_to_order_rate' => $leadToOrderRate,
                'inquiry_to_order_rate' => $inquiryToOrderRate,
            ],
            'breakdowns' => [
                'order_status' => report_count_by($orders, 'status', 10),
                'payment_status' => report_count_by($orders, 'payment_status', 10),
                'payment_method' => report_count_by($orders, 'payment_method', 10),
                'product' => report_count_by($orders, 'product_title', 10),
                'location' => report_count_by($orders, 'location', 10),
                'lead_channel' => report_count_by($leadEvents, 'channel', 10),
                'lead_group' => report_count_by($leadEvents, '_event_group_label', 10),
                'lead_source' => report_count_by($leadEvents, 'source', 10),
                'marketing_channel' => function_exists('analytics_count_channels_for_report') ? analytics_count_channels_for_report($leadEvents, $inquiries, $orders) : [],
                'inquiry_need' => report_count_by($inquiries, 'need', 10),
                'proof_status' => report_count_by($proofs, 'status', 10),
            ],
            'daily' => $daily,
            'max_daily' => max(1, $maxDaily),
            'recent' => [
                'orders' => array_slice($orders, 0, 12),
                'inquiries' => array_slice($inquiries, 0, 10),
                'proofs' => array_slice($proofs, 0, 10),
                'leads' => $reportLeadRecent,
            ],
        ];
    }
}

if (!function_exists('report_range_label')) {
    function report_range_label(string $range, array $params = []): string
    {
        $range = report_normalize_range($range);
        if ($range === 'all') {
            return 'Semua data';
        }
        if ($range === 'year') {
            return 'Tahun ' . report_clean((string)($params['year'] ?? date('Y')), 4);
        }
        if ($range === 'custom') {
            $from = report_date_input((string)($params['date_from'] ?? ''));
            $to = report_date_input((string)($params['date_to'] ?? ''));
            if ($from !== '' && $to !== '') {
                return date('d M Y', strtotime($from)) . ' - ' . date('d M Y', strtotime($to));
            }
            if ($from !== '') {
                return 'Mulai ' . date('d M Y', strtotime($from));
            }
            if ($to !== '') {
                return 'Sampai ' . date('d M Y', strtotime($to));
            }
            return 'Custom tanggal belum dipilih';
        }
        return $range . ' hari terakhir';
    }
}

if (!function_exists('report_current_url')) {
    function report_current_url(array $params, array $extra = []): string
    {
        $query = array_merge([
            'range' => (string)($params['range'] ?? '30'),
            'year' => (string)($params['year'] ?? ''),
            'date_from' => (string)($params['date_from'] ?? ''),
            'date_to' => (string)($params['date_to'] ?? ''),
            'source' => (string)(($params['filters']['source'] ?? '') ?: ''),
            'category' => (string)(($params['filters']['category'] ?? '') ?: ''),
            'location' => (string)(($params['filters']['location'] ?? '') ?: ''),
            'need' => (string)(($params['filters']['need'] ?? '') ?: ''),
            'status' => (string)(($params['filters']['status'] ?? '') ?: ''),
            'payment_status' => (string)(($params['filters']['payment_status'] ?? '') ?: ''),
            'payment_method' => (string)(($params['filters']['payment_method'] ?? '') ?: ''),
            'search' => (string)(($params['filters']['search'] ?? '') ?: ''),
        ], $extra);

        $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
        return url('admin/reports' . ($query ? '?' . http_build_query($query) : ''));
    }
}

if (!function_exists('report_export_summary_csv')) {
    function report_export_summary_csv(array $summary): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="report-summary-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['section', 'metric', 'value']);
        foreach (['sales', 'lead', 'order', 'payment', 'conversion'] as $section) {
            foreach ((array)($summary[$section] ?? []) as $metric => $value) {
                fputcsv($out, [$section, $metric, is_scalar($value) ? (string)$value : json_encode($value)]);
            }
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('report_export_daily_csv')) {
    function report_export_daily_csv(array $summary): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="report-daily-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['date', 'lead_events', 'inquiries', 'orders', 'payment_proofs', 'sales_estimate', 'payment_amount']);
        foreach ((array)($summary['daily'] ?? []) as $row) {
            fputcsv($out, [
                (string)($row['date'] ?? ''),
                (int)($row['lead_events'] ?? 0),
                (int)($row['inquiries'] ?? 0),
                (int)($row['orders'] ?? 0),
                (int)($row['payment_proofs'] ?? 0),
                (int)($row['sales_estimate'] ?? 0),
                (int)($row['payment_amount'] ?? 0),
            ]);
        }
        fclose($out);
        exit;
    }
}
