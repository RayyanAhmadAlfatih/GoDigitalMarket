<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| LANDING PAGE ANALYTICS DASHBOARD - Template
|--------------------------------------------------------------------------
| Reads local conversion, inquiry, and order JSONL logs to create per-LP
| dashboard insights, alur metrics, promosi/sumber/A-B breakdowns, and trend
| reporting without external dependencies. It intentionally avoids storing new
| PII beyond existing inquiry/order logs and keeps tracking local.
|--------------------------------------------------------------------------
*/

if (!function_exists('landing_page_analytics_enabled')) {
    function landing_page_analytics_enabled(): bool
    {
        return function_exists('conversion_tracking_enabled') ? conversion_tracking_enabled() : true;
    }
}

if (!function_exists('landing_page_analytics_clean')) {
    function landing_page_analytics_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('landing_page_analytics_range_days')) {
    function landing_page_analytics_range_days(string $range): int
    {
        $range = strtolower(trim($range));
        if (in_array($range, ['all', 'custom', 'year'], true)) {
            return 0;
        }
        $days = (int)$range;
        return $days > 0 ? max(1, min(3650, $days)) : 30;
    }
}

if (!function_exists('landing_page_analytics_date_filter')) {
    function landing_page_analytics_date_filter(array $query): array
    {
        $range = strtolower(trim((string)($query['range'] ?? '30')));
        if (!in_array($range, ['7', '14', '30', '60', '90', '180', '365', 'all', 'custom'], true)) {
            $range = '30';
        }
        $filters = [];
        $days = landing_page_analytics_range_days($range);
        if ($range === 'custom') {
            $from = trim((string)($query['from'] ?? ''));
            $to = trim((string)($query['to'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $filters['_start_ts'] = strtotime($from . ' 00:00:00') ?: 0;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $filters['_end_ts'] = strtotime($to . ' 23:59:59') ?: 0;
            }
            $days = 0;
        } elseif ($range === 'all') {
            $filters['_all_time'] = true;
            $days = 0;
        }
        return ['range' => $range, 'days' => $days, 'filters' => $filters];
    }
}

if (!function_exists('landing_page_analytics_slug_from_path')) {
    function landing_page_analytics_slug_from_path(string $pathOrUrl): string
    {
        $pathOrUrl = trim($pathOrUrl);
        if ($pathOrUrl === '') {
            return '';
        }
        $path = (string)(parse_url($pathOrUrl, PHP_URL_PATH) ?: $pathOrUrl);
        $base = trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?: ''), '/');
        $path = trim($path, '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            $path = trim(substr($path, strlen($base)), '/');
        }
        if (preg_match('#^(lp|landing)/([a-z0-9\-]+)$#i', $path, $m)) {
            return slugify((string)$m[2]);
        }
        return '';
    }
}

if (!function_exists('landing_page_analytics_slug_from_item')) {
    function landing_page_analytics_slug_from_item(array $item): string
    {
        foreach (['landing_page_slug', 'lp_slug'] as $key) {
            $slug = slugify((string)($item[$key] ?? ''));
            if ($slug !== '') {
                return $slug;
            }
        }
        foreach (['page_path', 'item_url', 'product_url', 'target_url', 'first_touch_landing_page'] as $key) {
            $slug = landing_page_analytics_slug_from_path((string)($item[$key] ?? ''));
            if ($slug !== '') {
                return $slug;
            }
        }
        $attr = is_array($item['attribution'] ?? null) ? (array)$item['attribution'] : [];
        foreach (['last_touch', 'first_touch'] as $touchKey) {
            $touch = is_array($attr[$touchKey] ?? null) ? (array)$attr[$touchKey] : [];
            $slug = landing_page_analytics_slug_from_path((string)($touch['landing_page'] ?? ''));
            if ($slug !== '') {
                return $slug;
            }
        }
        return '';
    }
}

if (!function_exists('landing_page_analytics_touch')) {
    function landing_page_analytics_touch(array $item): array
    {
        $attr = is_array($item['attribution'] ?? null) ? (array)$item['attribution'] : [];
        $last = is_array($attr['last_touch'] ?? null) ? (array)$attr['last_touch'] : [];
        return [
            'sumber' => landing_page_analytics_clean((string)($item['utm_sumber'] ?? $last['sumber'] ?? ''), 80),
            'medium' => landing_page_analytics_clean((string)($item['utm_medium'] ?? $last['medium'] ?? ''), 80),
            'promosi' => landing_page_analytics_clean((string)($item['utm_promosi'] ?? $last['promosi'] ?? ''), 120),
            'content' => landing_page_analytics_clean((string)($item['utm_content'] ?? $last['content'] ?? ''), 120),
            'term' => landing_page_analytics_clean((string)($item['utm_term'] ?? $last['term'] ?? ''), 120),
            'gclid' => landing_page_analytics_clean((string)($item['gclid'] ?? $last['gclid'] ?? ''), 220),
            'gbraid' => landing_page_analytics_clean((string)($item['gbraid'] ?? $last['gbraid'] ?? ''), 220),
            'wbraid' => landing_page_analytics_clean((string)($item['wbraid'] ?? $last['wbraid'] ?? ''), 220),
            'fbclid' => landing_page_analytics_clean((string)($item['fbclid'] ?? $last['fbclid'] ?? ''), 220),
            'ttclid' => landing_page_analytics_clean((string)($item['ttclid'] ?? $last['ttclid'] ?? ''), 220),
            'channel_group' => landing_page_analytics_clean((string)($item['marketing_channel'] ?? $last['channel_group'] ?? ''), 60),
        ];
    }
}

if (!function_exists('landing_page_analytics_sumber_bucket')) {
    function landing_page_analytics_sumber_bucket(array $item): string
    {
        $touch = landing_page_analytics_touch($item);
        $sumber = strtolower((string)$touch['sumber']);
        $medium = strtolower((string)$touch['medium']);
        $channel = strtolower((string)$touch['channel_group']);

        if ($touch['gclid'] !== '' || $touch['gbraid'] !== '' || $touch['wbraid'] !== '' || str_contains($sumber, 'google') && in_array($medium, ['cpc', 'ppc', 'paidsearch', 'paid_search', 'sem', 'ads'], true)) {
            return 'Google Ads';
        }
        if ($touch['fbclid'] !== '' || str_contains($sumber, 'facebook') || str_contains($sumber, 'instagram') || str_contains($sumber, 'meta') || $channel === 'paid_social') {
            return 'Meta Ads';
        }
        if ($touch['ttclid'] !== '' || str_contains($sumber, 'tiktok')) {
            return 'TikTok Ads';
        }
        if (str_contains($channel, 'organic') || in_array($medium, ['organic', 'seo'], true)) {
            return 'Organic';
        }
        if ($channel === 'direct' || $sumber === '' || $sumber === 'direct') {
            return 'Direct';
        }
        return 'Campaign / Referral';
    }
}

if (!function_exists('landing_page_analytics_event_kind')) {
    function landing_page_analytics_event_kind(array $event): string
    {
        $type = strtolower((string)($event['type'] ?? ''));
        $channel = strtolower((string)($event['channel'] ?? ''));
        $intent = strtolower((string)($event['intent'] ?? ''));
        $analyticsEvent = strtolower((string)($event['analytics_event'] ?? ''));
        if (in_array($type, ['landing_page_kunjungan', 'landing_page_view'], true) || $analyticsEvent === 'page_kunjungan' || str_contains($intent, 'kunjungan') || ($channel === 'landing_page' && str_contains($intent, 'view'))) {
            return 'page_kunjungan';
        }
        if ($type === 'form_submit' || $channel === 'form' || str_contains($analyticsEvent, 'submit_inquiry')) {
            return 'form_submit';
        }
        if ($channel === 'checkout' || $type === 'order_submit' || str_contains($analyticsEvent, 'checkout')) {
            return 'order';
        }
        if ($channel === 'whatsapp' || !empty($event['is_whatsapp']) || $type === 'whatsapp' || $type === 'landing-cta') {
            return 'cta_click';
        }
        return 'interaction';
    }
}

if (!function_exists('landing_page_analytics_empty_row')) {
    function landing_page_analytics_empty_row(array $page): array
    {
        return [
            'id' => (string)($page['id'] ?? ''),
            'slug' => (string)($page['slug'] ?? ''),
            'title' => (string)($page['title'] ?? ''),
            'status' => (string)($page['status'] ?? ''),
            'url' => function_exists('landing_page_url') ? landing_page_url((string)($page['slug'] ?? '')) : '',
            'page_kunjungan' => 0,
            'cta_click' => 0,
            'form_submit' => 0,
            'inquiry' => 0,
            'order' => 0,
            'conversion_rate' => 0.0,
            'sumbers' => [
                'Google Ads' => 0,
                'Meta Ads' => 0,
                'TikTok Ads' => 0,
                'Organic' => 0,
                'Direct' => 0,
                'Campaign / Referral' => 0,
            ],
            'utm_promosis' => [],
            'latest_at' => '',
        ];
    }
}

if (!function_exists('landing_page_analytics_touch_promosi_key')) {
    function landing_page_analytics_touch_promosi_key(array $item): string
    {
        $touch = landing_page_analytics_touch($item);
        $promosi = trim((string)$touch['promosi']);
        if ($promosi === '') {
            return 'Tanpa promosi';
        }
        return $promosi;
    }
}

if (!function_exists('landing_page_analytics_bump_latest')) {
    function landing_page_analytics_bump_latest(array &$row, array $item): void
    {
        $time = (string)($item['time'] ?? $item['created_at'] ?? '');
        $ts = $time !== '' ? strtotime($time) : (int)($item['_ts'] ?? 0);
        if ($ts <= 0) {
            return;
        }
        $current = (string)($row['latest_at'] ?? '');
        $currentTs = $current !== '' ? strtotime($current) : 0;
        if ($ts > $currentTs) {
            $row['latest_at'] = date('c', $ts);
        }
    }
}

if (!function_exists('landing_page_analytics_lead_segment')) {
    function landing_page_analytics_lead_segment(array $item): string
    {
        $segment = strtolower(trim((string)($item['lead_segment'] ?? '')));
        $segment = preg_replace('/[^a-z0-9_\- ]+/', '', $segment) ?: '';
        return substr(trim(preg_replace('/\s+/', '-', $segment) ?: '', '-_'), 0, 80);
    }
}

if (!function_exists('landing_page_analytics_lead_tags_text')) {
    function landing_page_analytics_lead_tags_text(array $item): string
    {
        $tags = $item['lead_tags'] ?? [];
        if (is_string($tags)) {
            $tags = preg_split('/[,;\r\n]+/', $tags) ?: [];
        }
        if (!is_array($tags)) {
            return '';
        }
        $clean = [];
        foreach ($tags as $tag) {
            $tag = strtolower(trim((string)$tag));
            $tag = preg_replace('/[^a-z0-9_\- ]+/', '', $tag) ?: '';
            $tag = trim(preg_replace('/\s+/', '-', $tag) ?: '', '-_');
            if ($tag !== '' && !in_array($tag, $clean, true)) {
                $clean[] = $tag;
            }
            if (count($clean) >= 12) { break; }
        }
        return implode(', ', $clean);
    }
}



if (!function_exists('landing_page_analytics_cta_signal_key')) {
    function landing_page_analytics_cta_signal_key(array $event): string
    {
        $signal = landing_page_analytics_clean((string)($event['cta_signal'] ?? ''), 90);
        if ($signal !== '') {
            return $signal;
        }
        $role = landing_page_analytics_clean((string)($event['lp_cta_role'] ?? $event['cta_role'] ?? ''), 50);
        $block = landing_page_analytics_clean((string)($event['lp_block_type'] ?? $event['block_type'] ?? ''), 60);
        if ($block !== '' || $role !== '') {
            return trim($block . ($role !== '' ? ' / ' . $role : ''));
        }
        return landing_page_analytics_clean((string)($event['label'] ?? 'CTA umum'), 90) ?: 'CTA umum';
    }
}

if (!function_exists('landing_page_analytics_window')) {
    function landing_page_analytics_window(int $days = 30, array $filters = []): array
    {
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        $end = $endTs > 0 ? $endTs : time();

        if ($startTs > 0 || $endTs > 0) {
            return [
                'start' => $startTs > 0 ? $startTs : null,
                'end' => $end,
                'all_time' => false,
            ];
        }

        if (!empty($filters['_all_time']) || $days <= 0) {
            return [
                'start' => null,
                'end' => $end,
                'all_time' => true,
            ];
        }

        $days = max(1, min(3650, $days));
        $endDay = strtotime(date('Y-m-d 23:59:59', $end)) ?: $end;
        $start = strtotime('-' . ($days - 1) . ' days', strtotime(date('Y-m-d 00:00:00', $end))) ?: ($end - ($days * 86400));

        return [
            'start' => $start,
            'end' => $endDay,
            'all_time' => false,
        ];
    }
}

if (!function_exists('landing_page_analytics_metric_keys')) {
    function landing_page_analytics_metric_keys(): array
    {
        return ['page_kunjungan', 'cta_click', 'form_submit', 'inquiry', 'order'];
    }
}

if (!function_exists('landing_page_analytics_zero_metrics')) {
    function landing_page_analytics_zero_metrics(): array
    {
        return [
            'page_kunjungan' => 0,
            'cta_click' => 0,
            'form_submit' => 0,
            'inquiry' => 0,
            'order' => 0,
            'lead_total' => 0,
            'conversions' => 0,
            'conversion_rate' => 0.0,
            'cta_rate' => 0.0,
            'lead_rate' => 0.0,
            'order_rate' => 0.0,
        ];
    }
}

if (!function_exists('landing_page_analytics_finalize_metrics')) {
    function landing_page_analytics_finalize_metrics(array $metrics): array
    {
        foreach (landing_page_analytics_zero_metrics() as $key => $default) {
            if (!array_key_exists($key, $metrics)) {
                $metrics[$key] = $default;
            }
        }
        $kunjungans = max(0, (int)$metrics['page_kunjungan']);
        $leads = (int)$metrics['form_submit'] + (int)$metrics['inquiry'];
        $conversions = $leads + (int)$metrics['order'];
        $metrics['lead_total'] = $leads;
        $metrics['conversions'] = $conversions;
        $metrics['conversion_rate'] = $kunjungans > 0 ? round(($conversions / $kunjungans) * 100, 2) : 0.0;
        $metrics['cta_rate'] = $kunjungans > 0 ? round(((int)$metrics['cta_click'] / $kunjungans) * 100, 2) : 0.0;
        $metrics['lead_rate'] = $kunjungans > 0 ? round(($leads / $kunjungans) * 100, 2) : 0.0;
        $metrics['order_rate'] = $kunjungans > 0 ? round(((int)$metrics['order'] / $kunjungans) * 100, 2) : 0.0;
        return $metrics;
    }
}

if (!function_exists('landing_page_analytics_timeline_seed')) {
    function landing_page_analytics_timeline_seed(int $days = 30, array $filters = []): array
    {
        $window = landing_page_analytics_window($days, $filters);
        $end = (int)($window['end'] ?? time());
        $start = $window['start'] !== null ? (int)$window['start'] : (strtotime('-29 days', strtotime(date('Y-m-d 00:00:00', $end))) ?: (time() - (29 * 86400)));
        $spanDays = max(1, (int)ceil(($end - $start) / 86400) + 1);
        $period = $spanDays > 62 ? 'month' : 'day';
        $timeline = [];

        if ($period === 'month') {
            $cursor = strtotime(date('Y-m-01 00:00:00', $start)) ?: $start;
            $endMonth = strtotime(date('Y-m-01 00:00:00', $end)) ?: $end;
            $guard = 0;
            while ($cursor <= $endMonth && $guard < 120) {
                $key = date('Y-m', $cursor);
                $timeline[$key] = array_merge(['key' => $key, 'label' => date('M Y', $cursor), 'total' => 0], landing_page_analytics_zero_metrics());
                $cursor = strtotime('+1 month', $cursor) ?: ($cursor + 2678400);
                $guard++;
            }
        } else {
            $cursor = strtotime(date('Y-m-d 00:00:00', $start)) ?: $start;
            $endDay = strtotime(date('Y-m-d 00:00:00', $end)) ?: $end;
            $guard = 0;
            while ($cursor <= $endDay && $guard < 90) {
                $key = date('Y-m-d', $cursor);
                $timeline[$key] = array_merge(['key' => $key, 'label' => date('d M', $cursor), 'total' => 0], landing_page_analytics_zero_metrics());
                $cursor = strtotime('+1 day', $cursor) ?: ($cursor + 86400);
                $guard++;
            }
        }

        return ['period' => $period, 'window' => $window, 'items' => $timeline];
    }
}

if (!function_exists('landing_page_analytics_timeline_key')) {
    function landing_page_analytics_timeline_key(int $timestamp, string $period): string
    {
        return $period === 'month' ? date('Y-m', $timestamp) : date('Y-m-d', $timestamp);
    }
}

if (!function_exists('landing_page_analytics_add_metric')) {
    function landing_page_analytics_add_metric(array &$metrics, string $kind, int $amount = 1): void
    {
        if (!in_array($kind, landing_page_analytics_metric_keys(), true)) {
            return;
        }
        $amount = max(1, $amount);
        $metrics[$kind] = (int)($metrics[$kind] ?? 0) + $amount;
        if (in_array($kind, ['form_submit', 'inquiry', 'order'], true)) {
            $metrics['conversions'] = (int)($metrics['conversions'] ?? 0) + $amount;
        }
        $metrics['total'] = (int)($metrics['total'] ?? 0) + $amount;
    }
}

if (!function_exists('landing_page_analytics_breakdown_row')) {
    function landing_page_analytics_breakdown_row(string $label): array
    {
        return array_merge(['label' => $label, 'latest_at' => ''], landing_page_analytics_zero_metrics());
    }
}

if (!function_exists('landing_page_analytics_breakdown_add')) {
    function landing_page_analytics_breakdown_add(array &$breakdown, string $key, string $kind, array $item = []): void
    {
        $key = trim($key) !== '' ? trim($key) : 'Tanpa data';
        if (!isset($breakdown[$key])) {
            $breakdown[$key] = landing_page_analytics_breakdown_row($key);
        }
        landing_page_analytics_add_metric($breakdown[$key], $kind);
        if ($item) {
            landing_page_analytics_bump_latest($breakdown[$key], $item);
        }
    }
}

if (!function_exists('landing_page_analytics_finalize_breakdown')) {
    function landing_page_analytics_finalize_breakdown(array $breakdown, int $limit = 12): array
    {
        foreach ($breakdown as &$row) {
            $row = landing_page_analytics_finalize_metrics($row);
        }
        unset($row);
        uasort($breakdown, static function (array $a, array $b): int {
            $scoreA = (int)($a['conversions'] ?? 0) * 10 + (int)($a['page_kunjungan'] ?? 0) + (int)($a['cta_click'] ?? 0);
            $scoreB = (int)($b['conversions'] ?? 0) * 10 + (int)($b['page_kunjungan'] ?? 0) + (int)($b['cta_click'] ?? 0);
            return $scoreB <=> $scoreA;
        });
        return array_slice(array_values($breakdown), 0, max(1, $limit));
    }
}

if (!function_exists('landing_page_analytics_timeline_add')) {
    function landing_page_analytics_timeline_add(array &$timelineBundle, array $item, string $kind): void
    {
        if (!in_array($kind, landing_page_analytics_metric_keys(), true)) {
            return;
        }
        $time = (string)($item['time'] ?? $item['created_at'] ?? '');
        $timestamp = $time !== '' ? (strtotime($time) ?: 0) : (int)($item['_ts'] ?? 0);
        if ($timestamp <= 0) {
            return;
        }
        $period = (string)($timelineBundle['period'] ?? 'day');
        $key = landing_page_analytics_timeline_key($timestamp, $period);
        if (!isset($timelineBundle['items'][$key])) {
            return;
        }
        landing_page_analytics_add_metric($timelineBundle['items'][$key], $kind);
    }
}

if (!function_exists('landing_page_analytics_build_alur')) {
    function landing_page_analytics_build_alur(array $totals): array
    {
        $kunjungans = (int)($totals['page_kunjungan'] ?? 0);
        $cta = (int)($totals['cta_click'] ?? 0);
        $forms = (int)($totals['form_submit'] ?? 0);
        $inquiries = (int)($totals['inquiry'] ?? 0);
        $leads = $forms + $inquiries;
        $orders = (int)($totals['order'] ?? 0);
        $pct = static fn(int $value, int $base): float => $base > 0 ? round(($value / $base) * 100, 2) : 0.0;

        return [
            'stages' => [
                ['key' => 'page_kunjungan', 'label' => 'Kunjungan', 'value' => $kunjungans, 'rate' => 100.0],
                ['key' => 'cta_click', 'label' => 'Tombol Click', 'value' => $cta, 'rate' => $pct($cta, max(1, $kunjungans))],
                ['key' => 'lead_total', 'label' => 'Lead', 'value' => $leads, 'rate' => $pct($leads, max(1, $kunjungans))],
                ['key' => 'order', 'label' => 'Order', 'value' => $orders, 'rate' => $pct($orders, max(1, $kunjungans))],
            ],
            'cta_rate' => $pct($cta, max(1, $kunjungans)),
            'lead_rate' => $pct($leads, max(1, $kunjungans)),
            'order_rate' => $pct($orders, max(1, $kunjungans)),
            'lead_from_cta_rate' => $pct($leads, max(1, $cta)),
            'order_from_lead_rate' => $pct($orders, max(1, $leads)),
        ];
    }
}

if (!function_exists('landing_page_analytics_build_insights')) {
    function landing_page_analytics_build_insights(array $totals, array $rows, array $sumbers, array $promosis, array $ctaSignals = []): array
    {
        $insights = [];
        $kunjungans = (int)($totals['page_kunjungan'] ?? 0);
        $cta = (int)($totals['cta_click'] ?? 0);
        $leads = (int)($totals['lead_total'] ?? ((int)($totals['form_submit'] ?? 0) + (int)($totals['inquiry'] ?? 0)));
        $orders = (int)($totals['order'] ?? 0);

        if ($kunjungans <= 0) {
            $insights[] = ['tone' => 'warning', 'title' => 'Tracking siap, data belum masuk', 'text' => 'Buka landing page publik atau jalankan promosi agar grafik kunjungan, klik tombol, dan lead mulai terisi.'];
        } elseif ($cta <= 0) {
            $insights[] = ['tone' => 'warning', 'title' => 'Kunjungan sudah ada, Tombol belum bergerak', 'text' => 'Periksa posisi tombol utama, copy Tombol, dan pastikan tombol WA/form terlihat jelas di above the fold.'];
        } elseif ($leads <= 0) {
            $insights[] = ['tone' => 'warning', 'title' => 'Klik ada, lead belum masuk', 'text' => 'Form atau pesan WhatsApp perlu dibuat lebih ringan. Coba kurangi field wajib dan gunakan offer yang lebih low barrier.'];
        } elseif ($orders <= 0) {
            $insights[] = ['tone' => 'info', 'title' => 'Lead sudah masuk, order belum tercatat', 'text' => 'Follow-up lead cepat dan pastikan checkout/order draft dipakai agar performa promosi bisa terbaca sampai order.'];
        } else {
            $insights[] = ['tone' => 'success', 'title' => 'Alur customer sudah terbaca', 'text' => 'Landing page sudah mencatat kunjungan, klik tombol, lead, dan order. Fokus berikutnya adalah memperkuat sumber promosi dengan hasil terbaik.'];
        }

        $winner = $rows[0] ?? [];
        if (!empty($winner['slug']) && (int)($winner['score'] ?? 0) > 0) {
            $insights[] = ['tone' => 'success', 'title' => 'Landing Page teratas', 'text' => (string)($winner['title'] ?? $winner['slug']) . ' sedang jadi landing page dengan skor performa tertinggi pada rentang ini.'];
        }

        $topSource = $sumbers[0] ?? [];
        if (!empty($topSource['label']) && ((int)($topSource['total'] ?? 0) > 0 || (int)($topSource['page_kunjungan'] ?? 0) > 0 || (int)($topSource['conversions'] ?? 0) > 0)) {
            $insights[] = ['tone' => 'info', 'title' => 'Source dominan', 'text' => (string)$topSource['label'] . ' menjadi sumber traffic/aksi paling kuat di rentang laporan ini.'];
        }

        $topCampaign = $promosis[0] ?? [];
        if (!empty($topCampaign['label']) && (string)$topCampaign['label'] !== 'Tanpa promosi' && ((int)($topCampaign['total'] ?? 0) > 0 || (int)($topCampaign['conversions'] ?? 0) > 0)) {
            $insights[] = ['tone' => 'info', 'title' => 'Campaign aktif', 'text' => 'Campaign ' . (string)$topCampaign['label'] . ' memiliki aktivitas tertinggi dan layak dicek kualitas lead-nya.'];
        }

        foreach (landing_page_analytics_signal_insights($ctaSignals) as $signalInsight) {
            $insights[] = $signalInsight;
        }

        return array_slice($insights, 0, 6);
    }
}


if (!function_exists('landing_page_analytics_signal_insights')) {
    function landing_page_analytics_signal_insights(array $ctaSignals): array
    {
        $insights = [];
        foreach ($ctaSignals as $signal) {
            $label = landing_page_analytics_clean((string)($signal['label'] ?? ''), 90);
            if ($label === '' || $label === 'Belum ada sinyal CTA') {
                continue;
            }
            $clicks = (int)($signal['cta_click'] ?? 0);
            $forms = (int)($signal['form_submit'] ?? 0);
            $leads = (int)($signal['lead_total'] ?? 0);
            $rate = (float)($signal['conversion_rate'] ?? 0);
            if ($clicks <= 0 && $forms <= 0 && $leads <= 0) {
                continue;
            }
            if ($clicks > 0 && $leads <= 0 && $forms <= 0) {
                $insights[] = [
                    'tone' => 'warning',
                    'title' => 'Sinyal ' . $label . ' sudah diklik',
                    'text' => 'Klik sudah tercatat, tapi belum menjadi lead. Periksa alur WhatsApp/form dari tombol ini.',
                ];
            } elseif ($leads > 0 || $forms > 0) {
                $insights[] = [
                    'tone' => 'success',
                    'title' => 'Sinyal ' . $label . ' menghasilkan lead',
                    'text' => 'Sinyal ini mulai membawa aksi bernilai. Pertahankan posisi dan copy-nya sambil pantau kualitas lead.',
                ];
            } elseif ($rate > 0) {
                $insights[] = [
                    'tone' => 'info',
                    'title' => 'Sinyal ' . $label . ' mulai aktif',
                    'text' => 'Gunakan label ini untuk membaca section mana yang paling memancing aksi calon pembeli.',
                ];
            }
            if (count($insights) >= 2) {
                break;
            }
        }
        return $insights;
    }
}

if (!function_exists('landing_page_analytics_build_action_plan')) {
    function landing_page_analytics_build_action_plan(array $totals, array $rows, array $ctaSignals, array $sumbers, array $promosis, array $abRows = []): array
    {
        $kunjungans = (int)($totals['page_kunjungan'] ?? 0);
        $cta = (int)($totals['cta_click'] ?? 0);
        $leads = (int)($totals['lead_total'] ?? ((int)($totals['form_submit'] ?? 0) + (int)($totals['inquiry'] ?? 0)));
        $orders = (int)($totals['order'] ?? 0);
        $ctaRate = (float)($totals['cta_rate'] ?? 0);
        $leadRate = (float)($totals['lead_rate'] ?? 0);
        $plans = [];

        $add = static function (string $tone, string $title, string $text, string $action, string $badge = 'Prioritas') use (&$plans): void {
            $plans[] = [
                'tone' => $tone,
                'badge' => $badge,
                'title' => $title,
                'text' => $text,
                'action' => $action,
            ];
        };

        if ($kunjungans <= 0) {
            $add('warning', 'Validasi tracking publik dulu', 'Belum ada kunjungan LP pada rentang ini. Data insight belum bisa dipakai untuk mengambil keputusan campaign.', 'Buka URL LP publik, klik CTA sekali sebagai test, lalu cek apakah event masuk ke analytics.', 'Mulai');
        } elseif ($cta <= 0) {
            $add('danger', 'Perbaiki first screen dan CTA utama', 'Kunjungan sudah masuk, tapi tombol belum bergerak. Ini biasanya tanda offer atau posisi tombol belum cukup jelas.', 'Perkuat headline, ubah copy tombol, dan pastikan CTA utama terlihat tanpa scroll.', 'Urgent');
        } elseif ($leads <= 0) {
            $add('warning', 'Kurangi hambatan sebelum lead masuk', 'Tombol sudah diklik, tapi belum ada form submit/inquiry. Calon pembeli tertarik, tapi belum lanjut aksi.', 'Ringankan form, gunakan tombol WhatsApp cepat, dan tulis benefit singkat di dekat CTA.', 'Prioritas');
        } elseif ($orders <= 0) {
            $add('info', 'Rapikan follow-up setelah lead', 'Lead sudah masuk, tapi order belum tercatat. Masalahnya kemungkinan bukan di traffic, tapi di proses follow-up/closing.', 'Cek template balasan WA, follow-up CRM, dan catat order agar attribution terbaca sampai penjualan.', 'Next');
        } else {
            $add('success', 'Siap scale bertahap', 'Alur kunjungan, klik, lead, dan order sudah terbaca. LP ini layak dipantau untuk penambahan traffic bertahap.', 'Tambah budget/traffic pelan-pelan sambil pantau sumber promosi dan CTA signal terbaik.', 'Scale');
        }

        foreach ($ctaSignals as $signal) {
            $label = landing_page_analytics_clean((string)($signal['label'] ?? ''), 90);
            if ($label === '' || $label === 'Belum ada sinyal CTA') {
                continue;
            }
            $clicks = (int)($signal['cta_click'] ?? 0);
            $signalLeads = (int)($signal['lead_total'] ?? 0);
            if ($clicks >= 5 && $signalLeads <= 0) {
                $add('warning', 'Audit CTA signal: ' . $label, 'Sinyal ini punya klik, tapi belum terlihat menghasilkan lead.', 'Cek URL, pesan WhatsApp, form tujuan, dan relevansi copy pada blok tersebut.', 'CTA');
                break;
            }
            if ($signalLeads > 0) {
                $add('success', 'Pertahankan CTA signal: ' . $label, 'Sinyal ini mulai menghasilkan lead dan bisa dijadikan patokan copy/posisi tombol lain.', 'Duplikasi gaya copy/posisi sinyal ini ke section yang kliknya masih rendah.', 'Winner');
                break;
            }
        }

        $topSource = $sumbers[0] ?? [];
        if (!empty($topSource['label']) && ((int)($topSource['page_kunjungan'] ?? 0) > 0 || (int)($topSource['conversions'] ?? 0) > 0)) {
            $label = landing_page_analytics_clean((string)$topSource['label'], 80);
            if ((int)($topSource['conversions'] ?? 0) <= 0 && (int)($topSource['page_kunjungan'] ?? 0) >= 20) {
                $add('warning', 'Source ' . $label . ' perlu dicek kualitasnya', 'Traffic dari sumber ini mulai masuk, tapi belum menghasilkan conversion.', 'Cek targeting campaign, keyword/audience, dan kesesuaian pesan iklan dengan hero LP.', 'Traffic');
            } elseif ((int)($topSource['conversions'] ?? 0) > 0) {
                $add('info', 'Source ' . $label . ' layak dipantau', 'Sumber ini membawa conversion pada rentang laporan.', 'Simpan sebagai kandidat source utama untuk campaign berikutnya.', 'Source');
            }
        }

        $topCampaign = $promosis[0] ?? [];
        if (!empty($topCampaign['label']) && (string)$topCampaign['label'] !== 'Tanpa promosi' && ((int)($topCampaign['total'] ?? 0) > 0 || (int)($topCampaign['conversions'] ?? 0) > 0)) {
            $add('info', 'Review campaign: ' . landing_page_analytics_clean((string)$topCampaign['label'], 80), 'Campaign ini paling aktif pada rentang laporan.', 'Bandingkan kualitas lead dan gunakan UTM yang konsisten agar report makin bersih.', 'Campaign');
        }

        if (!empty($abRows)) {
            $add('info', 'Baca hasil dari modul Tes A/B existing', 'Data variasi sudah tersedia dari fitur A/B lama, jadi tidak perlu membuat tracking A/B baru.', 'Gunakan tabel Tes A/B di bawah untuk melihat variasi yang mulai unggul.', 'Existing');
        }

        if ($kunjungans >= 50 && $ctaRate < 2.0) {
            $add('warning', 'CTA rate masih rendah', 'Rasio klik tombol masih di bawah 2% pada traffic yang mulai cukup.', 'Uji copy CTA di modul Tes A/B existing atau pakai Smart Preset untuk section penawaran lebih kuat.', 'Rate');
        }
        if ($kunjungans >= 50 && $leadRate < 1.0) {
            $add('warning', 'Lead rate perlu dinaikkan', 'Traffic sudah cukup, tapi lead rate masih rendah.', 'Buat offer low barrier seperti konsultasi gratis, katalog, voucher, atau checklist download.', 'Lead');
        }

        return array_slice($plans, 0, 6);
    }
}

if (!function_exists('landing_page_analytics_campaign_readiness')) {
    function landing_page_analytics_campaign_readiness(array $totals, array $rows, array $ctaSignals, array $promosis, bool $trackingReady): array
    {
        $kunjungans = (int)($totals['page_kunjungan'] ?? 0);
        $cta = (int)($totals['cta_click'] ?? 0);
        $leads = (int)($totals['lead_total'] ?? 0);
        $published = 0;
        foreach ($rows as $row) {
            if ((string)($row['status'] ?? '') === 'published') {
                $published++;
            }
        }
        $hasSignal = false;
        foreach ($ctaSignals as $signal) {
            if ((string)($signal['label'] ?? '') !== 'Belum ada sinyal CTA' && ((int)($signal['cta_click'] ?? 0) > 0 || (int)($signal['form_submit'] ?? 0) > 0 || (int)($signal['lead_total'] ?? 0) > 0)) {
                $hasSignal = true;
                break;
            }
        }
        $hasCampaign = false;
        foreach ($promosis as $promo) {
            if ((string)($promo['label'] ?? '') !== 'Tanpa promosi' && ((int)($promo['total'] ?? 0) > 0 || (int)($promo['page_kunjungan'] ?? 0) > 0 || (int)($promo['conversions'] ?? 0) > 0)) {
                $hasCampaign = true;
                break;
            }
        }

        $checks = [
            ['key' => 'tracking', 'label' => 'Tracking lokal aktif', 'ok' => $trackingReady, 'message' => $trackingReady ? 'Endpoint dan core tracking siap membaca event.' : 'Aktifkan tracking lokal dan cek endpoint lead-event.'],
            ['key' => 'published', 'label' => 'Ada LP published', 'ok' => $published > 0, 'message' => $published > 0 ? $published . ' landing page published terbaca.' : 'Publish minimal satu landing page sebelum scale iklan.'],
            ['key' => 'traffic', 'label' => 'Kunjungan mulai masuk', 'ok' => $kunjungans > 0, 'message' => $kunjungans > 0 ? number_format($kunjungans) . ' kunjungan terbaca.' : 'Belum ada data kunjungan pada rentang ini.'],
            ['key' => 'cta_signal', 'label' => 'CTA signal terbaca', 'ok' => $cta > 0 || $hasSignal, 'message' => ($cta > 0 || $hasSignal) ? 'Klik tombol/form sudah punya sinyal.' : 'Klik CTA belum tercatat, cek tombol dan label tracking.'],
            ['key' => 'lead_path', 'label' => 'Jalur lead terbaca', 'ok' => $leads > 0, 'message' => $leads > 0 ? number_format($leads) . ' lead/inquiry terbaca.' : 'Belum ada lead, gunakan form/WA yang lebih mudah.'],
            ['key' => 'campaign_label', 'label' => 'Campaign/UTM rapi', 'ok' => $hasCampaign, 'message' => $hasCampaign ? 'Campaign/UTM mulai terbaca.' : 'Gunakan UTM/tracking label agar sumber iklan terbaca bersih.'],
        ];

        $ok = count(array_filter($checks, static fn(array $check): bool => !empty($check['ok'])));
        $score = (int)round(($ok / max(1, count($checks))) * 100);
        $tone = $score >= 80 ? 'success' : ($score >= 55 ? 'warning' : 'danger');
        $label = $score >= 80 ? 'Siap campaign' : ($score >= 55 ? 'Cukup siap' : 'Perlu dirapikan');

        return [
            'score' => $score,
            'tone' => $tone,
            'label' => $label,
            'ok_count' => $ok,
            'total_count' => count($checks),
            'checks' => $checks,
        ];
    }
}



if (!function_exists('landing_page_analytics_primary_page')) {
    function landing_page_analytics_primary_page(array $rows): array
    {
        if (!$rows) {
            return [];
        }
        $ranked = array_values($rows);
        usort($ranked, static function (array $a, array $b): int {
            $scoreA = (int)($a['page_kunjungan'] ?? 0) * 2 + (int)($a['cta_click'] ?? 0) * 4 + (int)($a['lead_total'] ?? 0) * 8 + (int)($a['order'] ?? 0) * 14;
            $scoreB = (int)($b['page_kunjungan'] ?? 0) * 2 + (int)($b['cta_click'] ?? 0) * 4 + (int)($b['lead_total'] ?? 0) * 8 + (int)($b['order'] ?? 0) * 14;
            return $scoreB <=> $scoreA;
        });
        return $ranked[0] ?? [];
    }
}

if (!function_exists('landing_page_analytics_action_url')) {
    function landing_page_analytics_action_url(string $type, array $page = []): string
    {
        $slug = slugify((string)($page['slug'] ?? ''));
        $id = (string)($page['id'] ?? $slug);
        if ($type === 'edit' && $id !== '') {
            return url('admin/landing-pages?builder=' . rawurlencode($id));
        }
        if ($type === 'analytics' && $slug !== '') {
            return url('admin/landing-page-analytics?lp=' . rawurlencode($slug));
        }
        if ($type === 'optimization') {
            return url('admin/landing-page-optimization' . ($slug !== '' ? '?lp=' . rawurlencode($slug) : ''));
        }
        if ($type === 'ab') {
            return url('admin/landing-page-analytics' . ($slug !== '' ? '?lp=' . rawurlencode($slug) . '#lp-analytics-performance' : ''));
        }
        return url('admin/landing-pages');
    }
}

if (!function_exists('landing_page_analytics_next_action_board')) {
    function landing_page_analytics_next_action_board(array $totals, array $rows, array $ctaSignals, array $sumbers, array $promosis, array $readiness = [], array $abRows = []): array
    {
        $kunjungans = (int)($totals['page_kunjungan'] ?? 0);
        $cta = (int)($totals['cta_click'] ?? 0);
        $leads = (int)($totals['lead_total'] ?? ((int)($totals['form_submit'] ?? 0) + (int)($totals['inquiry'] ?? 0)));
        $orders = (int)($totals['order'] ?? 0);
        $ctaRate = (float)($totals['cta_rate'] ?? 0);
        $leadRate = (float)($totals['lead_rate'] ?? 0);
        $cvr = (float)($totals['conversion_rate'] ?? 0);
        $focusPage = landing_page_analytics_primary_page($rows);
        $focusTitle = landing_page_analytics_clean((string)($focusPage['title'] ?? 'Landing Page utama'), 90);
        $focusSlug = slugify((string)($focusPage['slug'] ?? ''));
        $stage = 'scale';
        $tone = 'success';
        $title = 'Siap scale bertahap';
        $summary = 'Alur sudah mulai terbaca. Fokus berikutnya adalah memperbesar traffic secara bertahap dan menjaga kualitas lead/order.';
        $focusLabel = 'Scale';

        if ($kunjungans <= 0) {
            $stage = 'tracking';
            $tone = 'warning';
            $title = 'Validasi tracking & kunjungan dulu';
            $summary = 'Belum ada kunjungan LP pada rentang ini, jadi sistem belum punya bahan untuk membaca performa campaign.';
            $focusLabel = 'Mulai';
        } elseif ($cta <= 0) {
            $stage = 'first_screen';
            $tone = 'danger';
            $title = 'Perbaiki first screen dan CTA utama';
            $summary = 'Pengunjung sudah datang, tapi belum klik tombol. Biasanya offer, headline, atau tombol belum cukup jelas.';
            $focusLabel = 'Urgent';
        } elseif ($leads <= 0) {
            $stage = 'lead_path';
            $tone = 'warning';
            $title = 'Klik sudah ada, jalur lead perlu dipermudah';
            $summary = 'Calon pembeli sudah menunjukkan minat, tapi belum menjadi lead. Form/WhatsApp perlu dibuat lebih ringan.';
            $focusLabel = 'Prioritas';
        } elseif ($orders <= 0) {
            $stage = 'follow_up';
            $tone = 'info';
            $title = 'Lead masuk, perkuat follow-up dan closing';
            $summary = 'Landing page mulai bekerja. Sekarang pastikan admin cepat follow-up dan order tercatat agar attribution tidak putus.';
            $focusLabel = 'Follow-up';
        }

        $actions = [];
        $push = static function (string $badge, string $actionTitle, string $text, string $button, string $url, string $actionTone = 'info') use (&$actions): void {
            $actions[] = [
                'badge' => $badge,
                'title' => $actionTitle,
                'text' => $text,
                'button' => $button,
                'url' => $url,
                'tone' => $actionTone,
            ];
        };

        if ($stage === 'tracking') {
            $push('1', 'Test URL publik dan klik CTA sekali', 'Buka LP publik, lakukan satu klik tombol/form, lalu cek apakah event masuk ke analytics.', 'Buka Analytics', landing_page_analytics_action_url('analytics', $focusPage), 'warning');
            $push('2', 'Pastikan minimal satu LP published', 'Sebelum iklan, pastikan LP yang akan dipakai sudah published dan tidak kosong.', 'Buka Builder', landing_page_analytics_action_url('edit', $focusPage), 'info');
        } elseif ($stage === 'first_screen') {
            $push('1', 'Perkuat headline dan tombol utama', 'Buat headline lebih spesifik, jelaskan manfaat, dan tampilkan CTA tanpa scroll.', 'Edit LP', landing_page_analytics_action_url('edit', $focusPage), 'danger');
            $push('2', 'Cek offer dengan Smart Preset', 'Tambahkan section trust/benefit/pricing agar calon pembeli punya alasan untuk klik.', 'Buka Builder', landing_page_analytics_action_url('edit', $focusPage), 'warning');
        } elseif ($stage === 'lead_path') {
            $push('1', 'Ringankan form atau arahkan ke WhatsApp', 'Jika klik tinggi tapi lead kosong, kurangi field form dan buat pesan WhatsApp lebih jelas.', 'Edit Jalur Lead', landing_page_analytics_action_url('edit', $focusPage), 'warning');
            $push('2', 'Audit CTA signal yang diklik', 'Lihat sinyal mana yang kliknya bergerak lalu cek tujuan tombol/form dari section tersebut.', 'Lihat CTA Signal', landing_page_analytics_action_url('analytics', $focusPage), 'info');
        } elseif ($stage === 'follow_up') {
            $push('1', 'Rapikan follow-up lead', 'Lead sudah masuk. Siapkan template balasan WA dan pastikan order dicatat agar funnel terbaca sampai closing.', 'Lihat Lead', landing_page_analytics_action_url('analytics', $focusPage), 'info');
            $push('2', 'Tambah trust dekat CTA', 'Testimoni, FAQ, garansi, atau bukti proses bisa membantu lead lebih yakin untuk lanjut order.', 'Edit Trust Section', landing_page_analytics_action_url('edit', $focusPage), 'info');
        } else {
            $push('1', 'Scale sumber terbaik perlahan', 'Tambah traffic bertahap pada sumber/campaign yang membawa conversion, sambil pantau lead quality.', 'Lihat Traffic', landing_page_analytics_action_url('analytics', $focusPage), 'success');
            $push('2', 'Duplikasi pola CTA winner', 'Gunakan gaya copy/posisi CTA signal terbaik ke section lain atau landing page campaign baru.', 'Buka Builder', landing_page_analytics_action_url('edit', $focusPage), 'success');
        }

        if ($kunjungans >= 30 && $cta > 0 && empty($abRows)) {
            $push('Tes A/B existing', 'Aktifkan Tes A/B lama hanya pada LP yang sudah punya traffic', 'Tidak membuat modul baru. Gunakan fitur Tes A/B existing untuk menguji headline/CTA/form secara terkontrol.', 'Buka Builder', landing_page_analytics_action_url('edit', $focusPage), 'info');
        } elseif (!empty($abRows)) {
            $push('Tes A/B existing', 'Baca variasi yang mulai unggul', 'Data A/B existing sudah tersedia. Gunakan sebagai bahan keputusan, bukan menambah tracking baru.', 'Lihat Performa', landing_page_analytics_action_url('ab', $focusPage), 'info');
        }

        $bestSignal = [];
        foreach ($ctaSignals as $signal) {
            if ((string)($signal['label'] ?? '') === 'Belum ada sinyal CTA') {
                continue;
            }
            if ((int)($signal['lead_total'] ?? 0) > 0 || (int)($signal['cta_click'] ?? 0) > 0) {
                $bestSignal = $signal;
                break;
            }
        }
        $bestSource = [];
        foreach ($sumbers as $source) {
            if ((int)($source['page_kunjungan'] ?? 0) > 0 || (int)($source['conversions'] ?? 0) > 0) {
                $bestSource = $source;
                break;
            }
        }
        $bestCampaign = [];
        foreach ($promosis as $promo) {
            if ((string)($promo['label'] ?? '') !== 'Tanpa promosi' && ((int)($promo['total'] ?? 0) > 0 || (int)($promo['conversions'] ?? 0) > 0)) {
                $bestCampaign = $promo;
                break;
            }
        }

        $weekly = [
            ['day' => 'Hari 1', 'task' => $stage === 'tracking' ? 'Validasi tracking publik dan publish LP utama.' : 'Perbaiki satu bottleneck terbesar dulu: ' . $title . '.'],
            ['day' => 'Hari 2-3', 'task' => 'Pantau CTA signal, sumber traffic, dan lead yang masuk dari analytics existing.'],
            ['day' => 'Hari 4-7', 'task' => $orders > 0 ? 'Scale traffic kecil-kecilan pada sumber terbaik sambil jaga kualitas lead/order.' : 'Jangan scale besar dulu; kuatkan offer, form/WA, trust, dan follow-up.'],
        ];

        $readinessScore = (int)($readiness['score'] ?? 0);
        $riskFlags = [];
        if ($readinessScore < 55) {
            $riskFlags[] = 'Campaign readiness masih rendah; jangan langsung naikkan budget besar.';
        }
        if ($kunjungans >= 50 && $ctaRate < 2.0) {
            $riskFlags[] = 'CTA rate rendah pada traffic yang mulai cukup.';
        }
        if ($kunjungans >= 50 && $leadRate < 1.0) {
            $riskFlags[] = 'Lead rate rendah; offer atau form perlu diringankan.';
        }
        if ($cvr > 0 && $orders <= 0 && $leads > 0) {
            $riskFlags[] = 'Lead sudah ada, tapi order belum tercatat. Pastikan follow-up dan pencatatan order rapi.';
        }

        return [
            'stage' => $stage,
            'tone' => $tone,
            'focus_label' => $focusLabel,
            'title' => $title,
            'summary' => $summary,
            'focus_page' => [
                'title' => $focusTitle,
                'slug' => $focusSlug,
                'edit_url' => landing_page_analytics_action_url('edit', $focusPage),
                'analytics_url' => landing_page_analytics_action_url('analytics', $focusPage),
            ],
            'quick_actions' => array_slice($actions, 0, 4),
            'weekly_playbook' => $weekly,
            'best_signal' => $bestSignal,
            'best_source' => $bestSource,
            'best_campaign' => $bestCampaign,
            'risk_flags' => $riskFlags,
        ];
    }
}

if (!function_exists('landing_page_analytics_report')) {
    function landing_page_analytics_report(int $days = 30, array $filters = []): array
    {
        $pages = function_exists('landing_page_all') ? landing_page_all(true) : [];
        $bySlug = [];
        foreach ($pages as $page) {
            $page = function_exists('landing_page_normalize') ? landing_page_normalize($page) : $page;
            $slug = (string)($page['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $bySlug[$slug] = landing_page_analytics_empty_row($page);
        }

        $selectedSlug = slugify((string)($filters['lp_slug'] ?? ''));
        $selectedSegment = landing_page_analytics_lead_segment(['lead_segment' => (string)($filters['lead_segment'] ?? '')]);
        $timelineBundle = landing_page_analytics_timeline_seed($days, $filters);
        $sumberBreakdown = [
            'Google Ads' => landing_page_analytics_breakdown_row('Google Ads'),
            'Meta Ads' => landing_page_analytics_breakdown_row('Meta Ads'),
            'TikTok Ads' => landing_page_analytics_breakdown_row('TikTok Ads'),
            'Organic' => landing_page_analytics_breakdown_row('Organic'),
            'Direct' => landing_page_analytics_breakdown_row('Direct'),
            'Campaign / Referral' => landing_page_analytics_breakdown_row('Campaign / Referral'),
        ];
        $promosiBreakdown = [];
        $abBreakdown = [];
        $ctaSignalBreakdown = [];
        $rawEventCount = 0;
        $compactEventCount = 0;

        $eventsRaw = function_exists('conversion_read_lead_events') ? conversion_read_lead_events($days, $filters, 120000) : [];
        $rawEventCount = count($eventsRaw);
        $events = function_exists('conversion_dedupe_lead_events') ? conversion_dedupe_lead_events($eventsRaw, 10) : $eventsRaw;
        $compactEventCount = count($events);
        foreach ($events as $event) {
            $slug = landing_page_analytics_slug_from_item($event);
            if ($slug === '' || ($selectedSlug !== '' && $slug !== $selectedSlug)) {
                continue;
            }
            if (!isset($bySlug[$slug])) {
                $bySlug[$slug] = landing_page_analytics_empty_row(['slug' => $slug, 'title' => ucwords(str_replace('-', ' ', $slug)), 'status' => 'unknown']);
            }
            $kind = landing_page_analytics_event_kind($event);
            if (!in_array($kind, landing_page_analytics_metric_keys(), true)) {
                continue;
            }
            landing_page_analytics_add_metric($bySlug[$slug], $kind);
            $bucket = landing_page_analytics_sumber_bucket($event);
            $bySlug[$slug]['sumbers'][$bucket] = (int)($bySlug[$slug]['sumbers'][$bucket] ?? 0) + 1;
            $promosi = landing_page_analytics_touch_promosi_key($event);
            $bySlug[$slug]['utm_promosis'][$promosi] = (int)($bySlug[$slug]['utm_promosis'][$promosi] ?? 0) + 1;
            landing_page_analytics_breakdown_add($sumberBreakdown, $bucket, $kind, $event);
            landing_page_analytics_breakdown_add($promosiBreakdown, $promosi, $kind, $event);
            if (in_array($kind, ['cta_click', 'form_submit'], true)) {
                landing_page_analytics_breakdown_add($ctaSignalBreakdown, landing_page_analytics_cta_signal_key($event), $kind, $event);
            }
            landing_page_analytics_timeline_add($timelineBundle, $event, $kind);
            landing_page_analytics_bump_latest($bySlug[$slug], $event);
            if (function_exists('landing_page_analytics_ab_add')) {
                landing_page_analytics_ab_add($abBreakdown, $event, $kind, $slug);
            }
        }

        $inquiries = function_exists('inquiry_read_all') ? inquiry_read_all($days, $filters, 50000) : [];
        $leadRows = [];
        $segmentTotals = [];
        foreach ($inquiries as $inquiry) {
            $slug = landing_page_analytics_slug_from_item($inquiry);
            if ($slug === '' || ($selectedSlug !== '' && $slug !== $selectedSlug)) {
                continue;
            }
            if ($selectedSegment !== '' && landing_page_analytics_lead_segment($inquiry) !== $selectedSegment) {
                continue;
            }
            if (!isset($bySlug[$slug])) {
                $bySlug[$slug] = landing_page_analytics_empty_row(['slug' => $slug, 'title' => ucwords(str_replace('-', ' ', $slug)), 'status' => 'unknown']);
            }
            landing_page_analytics_add_metric($bySlug[$slug], 'inquiry');
            $bucket = landing_page_analytics_sumber_bucket($inquiry);
            $bySlug[$slug]['sumbers'][$bucket] = (int)($bySlug[$slug]['sumbers'][$bucket] ?? 0) + 1;
            $promosi = landing_page_analytics_touch_promosi_key($inquiry);
            $bySlug[$slug]['utm_promosis'][$promosi] = (int)($bySlug[$slug]['utm_promosis'][$promosi] ?? 0) + 1;
            landing_page_analytics_breakdown_add($sumberBreakdown, $bucket, 'inquiry', $inquiry);
            landing_page_analytics_breakdown_add($promosiBreakdown, $promosi, 'inquiry', $inquiry);
            landing_page_analytics_timeline_add($timelineBundle, $inquiry, 'inquiry');
            landing_page_analytics_bump_latest($bySlug[$slug], $inquiry);
            $leadSegment = landing_page_analytics_lead_segment($inquiry);
            if ($leadSegment !== '') {
                $segmentTotals[$leadSegment] = (int)($segmentTotals[$leadSegment] ?? 0) + 1;
            }
            if (function_exists('landing_page_analytics_ab_add')) {
                landing_page_analytics_ab_add($abBreakdown, $inquiry, 'inquiry', $slug);
            }
            $leadRows[] = landing_page_analytics_lead_row($inquiry, $slug);
        }

        $orders = function_exists('order_read_all') ? order_read_all($days, $filters, 50000) : [];
        foreach ($orders as $order) {
            $slug = landing_page_analytics_slug_from_item($order);
            if ($slug === '' || ($selectedSlug !== '' && $slug !== $selectedSlug)) {
                continue;
            }
            if (!isset($bySlug[$slug])) {
                $bySlug[$slug] = landing_page_analytics_empty_row(['slug' => $slug, 'title' => ucwords(str_replace('-', ' ', $slug)), 'status' => 'unknown']);
            }
            landing_page_analytics_add_metric($bySlug[$slug], 'order');
            $bucket = landing_page_analytics_sumber_bucket($order);
            $bySlug[$slug]['sumbers'][$bucket] = (int)($bySlug[$slug]['sumbers'][$bucket] ?? 0) + 1;
            $promosi = landing_page_analytics_touch_promosi_key($order);
            $bySlug[$slug]['utm_promosis'][$promosi] = (int)($bySlug[$slug]['utm_promosis'][$promosi] ?? 0) + 1;
            landing_page_analytics_breakdown_add($sumberBreakdown, $bucket, 'order', $order);
            landing_page_analytics_breakdown_add($promosiBreakdown, $promosi, 'order', $order);
            landing_page_analytics_timeline_add($timelineBundle, $order, 'order');
            landing_page_analytics_bump_latest($bySlug[$slug], $order);
            if (function_exists('landing_page_analytics_ab_add')) {
                landing_page_analytics_ab_add($abBreakdown, $order, 'order', $slug);
            }
        }

        foreach ($bySlug as &$row) {
            $row = landing_page_analytics_finalize_metrics($row);
            $row['score'] = (int)($row['page_kunjungan'] ?? 0)
                + (int)($row['cta_click'] ?? 0) * 2
                + (int)($row['lead_total'] ?? 0) * 5
                + (int)($row['order'] ?? 0) * 12;
            arsort($row['sumbers']);
            arsort($row['utm_promosis']);
        }
        unset($row);

        $rows = array_values($bySlug);
        if ($selectedSlug !== '') {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (string)($row['slug'] ?? '') === $selectedSlug));
        }
        usort($rows, static function (array $a, array $b): int {
            return ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0));
        });

        usort($leadRows, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));

        $totals = landing_page_analytics_zero_metrics();
        $sumberTotals = ['Google Ads' => 0, 'Meta Ads' => 0, 'TikTok Ads' => 0, 'Organic' => 0, 'Direct' => 0, 'Campaign / Referral' => 0];
        foreach ($rows as $row) {
            foreach (landing_page_analytics_metric_keys() as $key) {
                $totals[$key] += (int)($row[$key] ?? 0);
            }
            foreach ((array)($row['sumbers'] ?? []) as $sumber => $count) {
                $sumberTotals[$sumber] = (int)($sumberTotals[$sumber] ?? 0) + (int)$count;
            }
        }
        $totals = landing_page_analytics_finalize_metrics($totals);
        arsort($sumberTotals);
        arsort($segmentTotals);

        $timelineItems = array_values($timelineBundle['items'] ?? []);
        foreach ($timelineItems as &$timelineRow) {
            $timelineRow = landing_page_analytics_finalize_metrics($timelineRow);
        }
        unset($timelineRow);
        $maxTimelineTotal = 0;
        foreach ($timelineItems as $timelineRow) {
            $maxTimelineTotal = max($maxTimelineTotal, (int)($timelineRow['total'] ?? 0), (int)($timelineRow['page_kunjungan'] ?? 0), (int)($timelineRow['conversions'] ?? 0));
        }

        $sumberBreakdownRows = landing_page_analytics_finalize_breakdown($sumberBreakdown, 8);
        $promosiBreakdownRows = landing_page_analytics_finalize_breakdown($promosiBreakdown ?: ['Tanpa promosi' => landing_page_analytics_breakdown_row('Tanpa promosi')], 12);
        $abBreakdownRows = function_exists('landing_page_analytics_ab_finalize') ? landing_page_analytics_ab_finalize($abBreakdown) : [];
        $ctaSignalRows = landing_page_analytics_finalize_breakdown($ctaSignalBreakdown ?: ['Belum ada sinyal CTA' => landing_page_analytics_breakdown_row('Belum ada sinyal CTA')], 12);
        $alur = landing_page_analytics_build_alur($totals);
        $trackingReady = landing_page_analytics_enabled() && function_exists('conversion_store_event');
        $insights = landing_page_analytics_build_insights($totals, $rows, $sumberBreakdownRows, $promosiBreakdownRows, $ctaSignalRows);
        $actionPlan = landing_page_analytics_build_action_plan($totals, $rows, $ctaSignalRows, $sumberBreakdownRows, $promosiBreakdownRows, $abBreakdownRows);
        $campaignReadiness = landing_page_analytics_campaign_readiness($totals, $rows, $ctaSignalRows, $promosiBreakdownRows, $trackingReady);
        $nextActionBoard = landing_page_analytics_next_action_board($totals, $rows, $ctaSignalRows, $sumberBreakdownRows, $promosiBreakdownRows, $campaignReadiness, $abBreakdownRows);

        return [
            'generated_at' => date('c'),
            'days' => $days,
            'filters' => $filters,
            'totals' => $totals,
            'alur' => $alur,
            'sumbers' => $sumberTotals,
            'sumber_breakdown' => $sumberBreakdownRows,
            'promosi_breakdown' => $promosiBreakdownRows,
            'ab_breakdown' => $abBreakdownRows,
            'cta_signal_breakdown' => $ctaSignalRows,
            'segments' => $segmentTotals,
            'rows' => $rows,
            'leads' => $leadRows,
            'timeline' => $timelineItems,
            'timeline_period' => (string)($timelineBundle['period'] ?? 'day'),
            'timeline_max' => $maxTimelineTotal,
            'insights' => $insights,
            'action_plan' => $actionPlan,
            'campaign_readiness' => $campaignReadiness,
            'next_action_board' => $nextActionBoard,
            'selected_page' => $rows[0] ?? null,
            'raw_event_count' => $rawEventCount,
            'compact_event_count' => $compactEventCount,
            'tracking_ready' => $trackingReady,
        ];
    }
}

if (!function_exists('landing_page_analytics_lead_row')) {
    function landing_page_analytics_lead_row(array $inquiry, string $slug): array
    {
        $touch = landing_page_analytics_touch($inquiry);
        $ts = (int)($inquiry['_ts'] ?? (strtotime((string)($inquiry['time'] ?? '')) ?: 0));
        return [
            '_ts' => $ts,
            'time' => (string)($inquiry['time'] ?? ''),
            'landing_slug' => $slug,
            'landing_title' => (string)($inquiry['item_title'] ?? ucwords(str_replace('-', ' ', $slug))),
            'name' => (string)($inquiry['name'] ?? ''),
            'phone' => (string)($inquiry['phone'] ?? ''),
            'email' => (string)($inquiry['email'] ?? ''),
            'need' => (string)($inquiry['need'] ?? ''),
            'form_name' => (string)($inquiry['lp_form_name'] ?? ''),
            'lead_segment' => landing_page_analytics_lead_segment($inquiry),
            'lead_tags' => landing_page_analytics_lead_tags_text($inquiry),
            'lead_priority' => (string)($inquiry['lead_priority'] ?? ''),
            'lead_stage' => (string)($inquiry['lead_stage'] ?? ''),
            'lead_score' => (string)($inquiry['lead_score'] ?? ''),
            'ab_test_type' => (string)($inquiry['ab_test_type'] ?? ''),
            'ab_variasi' => (string)($inquiry['ab_variasi'] ?? ''),
            'ab_variasi_label' => (string)($inquiry['ab_variasi_label'] ?? ''),
            'status' => (string)($inquiry['status'] ?? 'Baru'),
            'promosi' => (string)$touch['promosi'],
            'utm_sumber' => (string)$touch['sumber'],
            'utm_medium' => (string)$touch['medium'],
            'utm_content' => (string)$touch['content'],
            'utm_term' => (string)$touch['term'],
            'sumber_bucket' => landing_page_analytics_sumber_bucket($inquiry),
            'page_path' => (string)($inquiry['page_path'] ?? ''),
        ];
    }
}

if (!function_exists('landing_page_analytics_csv_rows')) {
    function landing_page_analytics_csv_rows(array $report): array
    {
        $rows = [];
        foreach ((array)($report['leads'] ?? []) as $lead) {
            $rows[] = [
                'time' => (string)($lead['time'] ?? ''),
                'landing_page' => (string)($lead['landing_title'] ?? ''),
                'slug' => (string)($lead['landing_slug'] ?? ''),
                'name' => (string)($lead['name'] ?? ''),
                'whatsapp' => (string)($lead['phone'] ?? ''),
                'email' => (string)($lead['email'] ?? ''),
                'need' => (string)($lead['need'] ?? ''),
                'form_name' => (string)($lead['form_name'] ?? ''),
                'lead_segment' => (string)($lead['lead_segment'] ?? ''),
                'lead_tags' => (string)($lead['lead_tags'] ?? ''),
                'lead_priority' => (string)($lead['lead_priority'] ?? ''),
                'lead_stage' => (string)($lead['lead_stage'] ?? ''),
                'lead_score' => (string)($lead['lead_score'] ?? ''),
                'ab_test_type' => (string)($lead['ab_test_type'] ?? ''),
                'ab_variasi' => (string)($lead['ab_variasi'] ?? ''),
                'ab_variasi_label' => (string)($lead['ab_variasi_label'] ?? ''),
                'status' => (string)($lead['status'] ?? ''),
                'sumber_bucket' => (string)($lead['sumber_bucket'] ?? ''),
                'utm_sumber' => (string)($lead['utm_sumber'] ?? ''),
                'utm_medium' => (string)($lead['utm_medium'] ?? ''),
                'utm_promosi' => (string)($lead['promosi'] ?? ''),
                'utm_content' => (string)($lead['utm_content'] ?? ''),
                'utm_term' => (string)($lead['utm_term'] ?? ''),
                'page_path' => (string)($lead['page_path'] ?? ''),
            ];
        }
        return $rows;
    }
}
