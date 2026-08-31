<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| CONVERSION & LEAD TRACKING HELPERS
|--------------------------------------------------------------------------
| Lightweight helpers for WhatsApp lead source clarity and optional local
| click tracking. No external analytics dependency is required.
|--------------------------------------------------------------------------
*/

if (!function_exists('conversion_tracking_enabled')) {

    function conversion_tracking_enabled(): bool
    {
        $value = strtolower(trim((string)($_ENV['ENABLE_LEAD_TRACKING'] ?? 'true')));

        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('conversion_clean_text')) {

    function conversion_clean_text(string $text, int $max = 120): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?: '');

        if ($text === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }

        return substr($text, 0, $max);
    }
}

if (!function_exists('conversion_context_line')) {

    function conversion_context_line(array $context = []): string
    {
        $source = conversion_clean_text((string)($context['source'] ?? 'Website'), 40);
        $title = conversion_clean_text((string)($context['title'] ?? ''), 90);
        $category = conversion_clean_text((string)($context['category'] ?? ''), 50);
        $location = conversion_clean_text((string)($context['location'] ?? ''), 50);

        $parts = [];

        if ($source !== '') {
            $parts[] = $source;
        }

        if ($title !== '') {
            $parts[] = $title;
        }

        if ($category !== '') {
            $parts[] = $category;
        }

        if ($location !== '') {
            $parts[] = $location;
        }

        if (!$parts) {
            return '';
        }

        return 'Sumber: ' . implode(' - ', array_unique($parts));
    }
}

if (!function_exists('conversion_is_whatsapp_url')) {

    function conversion_is_whatsapp_url(string $url): bool
    {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));

        return $host === 'wa.me' || $host === 'api.whatsapp.com' || str_ends_with($host, '.whatsapp.com');
    }
}

if (!function_exists('conversion_optimize_whatsapp_url')) {

    function conversion_optimize_whatsapp_url(string $url, array $context = []): string
    {
        if (!conversion_is_whatsapp_url($url)) {
            return $url;
        }

        $parts = parse_url($url);

        if (!$parts || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }

        $message = trim((string)($query['text'] ?? ''));
        $sourceLine = conversion_context_line($context);

        if ($sourceLine !== '' && !str_contains($message, 'Sumber:')) {
            $message = trim($message . "\n\n" . $sourceLine);
        }

        if ($message !== '') {
            $query['text'] = $message;
        }

        $scheme = (string)($parts['scheme'] ?? 'https');
        $host = (string)$parts['host'];
        $path = (string)($parts['path'] ?? '');
        $rebuilt = $scheme . '://' . $host . $path;

        if ($query) {
            $rebuilt .= '?' . http_build_query($query);
        }

        return $rebuilt;
    }
}

if (!function_exists('conversion_optimize_url')) {

    function conversion_optimize_url(string $url, array $context = []): string
    {
        return conversion_is_whatsapp_url($url)
            ? conversion_optimize_whatsapp_url($url, $context)
            : $url;
    }
}

if (!function_exists('wa_link_contextual')) {

    function wa_link_contextual(string $message = '', array $context = [], ?string $phone = null): string
    {
        $phone = preg_replace('/\D+/', '', (string)($phone ?: SITE_WHATSAPP));
        $message = trim($message);
        $sourceLine = conversion_context_line($context);

        if ($sourceLine !== '' && !str_contains($message, 'Sumber:')) {
            $message = trim($message . "\n\n" . $sourceLine);
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }
}

if (!function_exists('conversion_link_attrs')) {

    function conversion_link_attrs(array $context = []): string
    {
        $source = conversion_clean_text((string)($context['source'] ?? 'website'), 50);
        $type = conversion_clean_text((string)($context['type'] ?? 'click'), 40);
        $category = conversion_clean_text((string)($context['category'] ?? ''), 60);
        $location = conversion_clean_text((string)($context['location'] ?? ''), 60);
        $label = conversion_clean_text((string)($context['label'] ?? ''), 80);
        $intent = conversion_clean_text((string)($context['intent'] ?? ''), 60);
        $channel = conversion_clean_text((string)($context['channel'] ?? ''), 50);

        $attrs = [
            'data-conversion-track' => '1',
            'data-lead-source' => $source,
            'data-lead-type' => $type,
        ];

        foreach ([
            'data-lead-category' => $category,
            'data-lead-location' => $location,
            'data-lead-label' => $label,
            'data-lead-intent' => $intent,
            'data-lead-channel' => $channel,
            'data-landing-page-slug' => conversion_clean_text((string)($context['landing_page_slug'] ?? ''), 120),
            'data-landing-page-id' => conversion_clean_text((string)($context['landing_page_id'] ?? ''), 90),
            'data-lp-block-type' => conversion_clean_text((string)($context['block_type'] ?? ''), 60),
            'data-lp-block-index' => conversion_clean_text((string)($context['block_index'] ?? ''), 20),
            'data-lp-block-goal' => conversion_clean_text((string)($context['block_goal'] ?? ''), 40),
            'data-lp-cta-role' => conversion_clean_text((string)($context['cta_role'] ?? ''), 40),
            'data-cta-signal' => conversion_clean_text((string)($context['cta_signal'] ?? ''), 90),
            'data-cta-signal-label' => conversion_clean_text((string)($context['cta_signal_label'] ?? ''), 120),
        ] as $key => $value) {
            if ($value !== '') {
                $attrs[$key] = $value;
            }
        }

        $html = [];
        foreach ($attrs as $key => $value) {
            $html[] = $key . '="' . esc((string)$value) . '"';
        }

        return implode(' ', $html);
    }
}

if (!function_exists('conversion_normalize_event')) {

    function conversion_normalize_event(array $payload): array
    {
        $clean = static function (string $key, int $max = 160) use ($payload): string {
            return conversion_clean_text((string)($payload[$key] ?? ''), $max);
        };

        $target = trim((string)($payload['target_url'] ?? ''));
        if ($target !== '' && !preg_match('#^https?://#i', $target) && !str_starts_with($target, '/')) {
            $target = '';
        }

        $isWhatsapp = conversion_is_whatsapp_url($target);
        $channel = strtolower($clean('channel', 50));
        $type = strtolower($clean('type', 50));

        if ($channel === '') {
            if ($isWhatsapp) {
                $channel = 'whatsapp';
            } elseif (in_array($type, ['form', 'checkout', 'payment', 'qris', 'order'], true)) {
                $channel = $type;
            } else {
                $path = strtolower((string)(parse_url($target, PHP_URL_PATH) ?? ''));
                if (str_contains($path, 'checkout')) {
                    $channel = 'checkout';
                } elseif (str_contains($path, 'payment') || str_contains($path, 'qris')) {
                    $channel = 'payment';
                } else {
                    $channel = 'click';
                }
            }
        }

        return [
            'time' => date('c'),
            'event_id' => function_exists('server_conversion_clean_event_id') ? server_conversion_clean_event_id((string)($payload['event_id'] ?? '')) : conversion_clean_text((string)($payload['event_id'] ?? ''), 90),
            'cta_deployment_id' => function_exists('cta_result_id') ? cta_result_id((string)($payload['cta_deployment_id'] ?? '')) : $clean('cta_deployment_id', 120),
            'offer_variant_id' => function_exists('cta_result_id') ? cta_result_id((string)($payload['offer_variant_id'] ?? '')) : $clean('offer_variant_id', 120),
            'cta_placement' => function_exists('cta_result_id') ? cta_result_id((string)($payload['cta_placement'] ?? '')) : $clean('cta_placement', 120),
            'source' => $clean('source', 80),
            'type' => $clean('type', 50),
            'channel' => conversion_clean_text($channel, 50),
            'category' => $clean('category', 80),
            'location' => $clean('location', 80),
            'intent' => $clean('intent', 80),
            'label' => $clean('label', 120),
            'landing_page_slug' => function_exists('slugify') ? slugify((string)($payload['landing_page_slug'] ?? '')) : $clean('landing_page_slug', 120),
            'landing_page_id' => $clean('landing_page_id', 90),
            'page_path' => $clean('page_path', 180),
            'target_url' => conversion_clean_text($target, 260),
            'target_host' => conversion_clean_text((string)(parse_url($target, PHP_URL_HOST) ?? ''), 120),
            'is_whatsapp' => $isWhatsapp,
            'lp_block_type' => $clean('block_type', 60),
            'lp_block_index' => $clean('block_index', 20),
            'lp_block_goal' => $clean('block_goal', 40),
            'lp_cta_role' => $clean('cta_role', 40),
            'cta_signal' => $clean('cta_signal', 90),
            'cta_signal_label' => $clean('cta_signal_label', 120),
            'ab_test_type' => function_exists('landing_page_ab_clean_slug') ? landing_page_ab_clean_slug((string)($payload['ab_test_type'] ?? '')) : $clean('ab_test_type', 40),
            'ab_test_id' => function_exists('landing_page_ab_clean_slug') ? landing_page_ab_clean_slug((string)($payload['ab_test_id'] ?? '')) : $clean('ab_test_id', 90),
            'ab_test_name' => $clean('ab_test_name', 100),
            'ab_variant' => in_array(strtolower((string)($payload['ab_variant'] ?? '')), ['a', 'b'], true) ? strtolower((string)$payload['ab_variant']) : '',
            'ab_variant_label' => $clean('ab_variant_label', 80),
            'ab_cta_test_id' => function_exists('landing_page_ab_clean_slug') ? landing_page_ab_clean_slug((string)($payload['ab_cta_test_id'] ?? '')) : $clean('ab_cta_test_id', 90),
            'ab_cta_test_name' => $clean('ab_cta_test_name', 100),
            'ab_cta_variant' => in_array(strtolower((string)($payload['ab_cta_variant'] ?? '')), ['a', 'b'], true) ? strtolower((string)$payload['ab_cta_variant']) : '',
            'ab_cta_variant_label' => $clean('ab_cta_variant_label', 80),
            'ab_form_test_id' => function_exists('landing_page_ab_clean_slug') ? landing_page_ab_clean_slug((string)($payload['ab_form_test_id'] ?? '')) : $clean('ab_form_test_id', 90),
            'ab_form_test_name' => $clean('ab_form_test_name', 100),
            'ab_form_variant' => in_array(strtolower((string)($payload['ab_form_variant'] ?? '')), ['a', 'b'], true) ? strtolower((string)$payload['ab_form_variant']) : '',
            'ab_form_variant_label' => $clean('ab_form_variant_label', 80),
        ];
    }
}

if (!function_exists('conversion_store_event')) {

    function conversion_store_event(array $event): bool
    {
        if (!conversion_tracking_enabled()) {
            return false;
        }

        if (!defined('LOGS_PATH')) {
            return false;
        }

        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }

        if (function_exists('analytics_enrich_conversion_event')) {
            $event = analytics_enrich_conversion_event($event);
        }

        if (function_exists('server_conversion_attach_event_id')) {
            $event = server_conversion_attach_event_id($event);
        }

        if (function_exists('server_conversion_enqueue_event')) {
            server_conversion_enqueue_event($event, 'lead_event');
        }

        $mysqlOk = false;
        $mysqlActive = function_exists('storage_mysql_enabled') && storage_mysql_enabled('analytics_events');
        if ($mysqlActive && function_exists('storage_adapter_mysql_append_analytics_event')) {
            $mysqlOk = storage_adapter_mysql_append_analytics_event($event);
        }

        $file = LOGS_PATH . '/lead-events-' . date('Y-m') . '.jsonl';
        $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $fileOk = @file_put_contents($file, $line, FILE_APPEND | LOCK_EX) !== false;

        if ($mysqlActive) {
            return $mysqlOk || (function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled() && $fileOk);
        }
        return $fileOk;
    }
}

/*
|--------------------------------------------------------------------------
| LEAD TRACKING DASHBOARD HELPERS - Template
|--------------------------------------------------------------------------
| Reads local JSONL lead logs and converts them into lightweight dashboard
| summaries for the admin panel. Template adds historical ranges, custom date
| windows, charts, and multi-channel conversion foundations.
|--------------------------------------------------------------------------
*/

if (!function_exists('conversion_event_timestamp')) {

    function conversion_event_timestamp(array $event): int
    {
        $time = (string)($event['time'] ?? '');
        $timestamp = $time !== '' ? strtotime($time) : false;

        return $timestamp !== false ? (int)$timestamp : 0;
    }
}

if (!function_exists('conversion_dashboard_clean_filter')) {

    function conversion_dashboard_clean_filter(string $value, int $max = 80): string
    {
        return conversion_clean_text($value, $max);
    }
}

if (!function_exists('conversion_lead_log_files')) {

    function conversion_lead_log_files(int $days = 30, ?int $startTs = null, ?int $endTs = null): array
    {
        if (!defined('LOGS_PATH') || !is_dir(LOGS_PATH)) {
            return [];
        }

        $files = glob(LOGS_PATH . '/lead-events-*.jsonl') ?: [];

        if ($days > 0 && $startTs === null) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }

        $startMonth = $startTs ? strtotime(date('Y-m-01 00:00:00', $startTs)) : null;
        $endMonth = $endTs ? strtotime(date('Y-m-01 23:59:59', $endTs)) : null;

        $files = array_values(array_filter($files, static function (string $file) use ($startMonth, $endMonth): bool {
            if (!preg_match('/lead-events-(\d{4})-(\d{2})\.jsonl$/', $file, $matches)) {
                return false;
            }

            $fileMonth = strtotime($matches[1] . '-' . $matches[2] . '-01 00:00:00') ?: 0;

            if ($startMonth !== null && $fileMonth < $startMonth) {
                return false;
            }

            if ($endMonth !== null && $fileMonth > $endMonth) {
                return false;
            }

            return true;
        }));

        rsort($files, SORT_STRING);

        return $files;
    }
}

if (!function_exists('conversion_available_lead_years')) {

    function conversion_available_lead_years(): array
    {
        if (!defined('LOGS_PATH') || !is_dir(LOGS_PATH)) {
            return [date('Y')];
        }

        $years = [];
        foreach (glob(LOGS_PATH . '/lead-events-*.jsonl') ?: [] as $file) {
            if (preg_match('/lead-events-(\d{4})-\d{2}\.jsonl$/', $file, $matches)) {
                $years[] = (string)$matches[1];
            }
        }

        $years = array_values(array_unique($years));
        rsort($years, SORT_STRING);

        return $years ?: [date('Y')];
    }
}

if (!function_exists('conversion_dashboard_window')) {

    function conversion_dashboard_window(int $days = 30, array $filters = []): array
    {
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        $allTime = !empty($filters['_all_time']) || $days <= 0;

        if ($startTs > 0 || $endTs > 0) {
            return [
                'start' => $startTs > 0 ? $startTs : null,
                'end' => $endTs > 0 ? $endTs : time(),
                'all_time' => false,
            ];
        }

        if ($allTime) {
            return ['start' => null, 'end' => time(), 'all_time' => true];
        }

        $days = max(1, min(3650, $days));

        return [
            'start' => time() - ($days * 86400),
            'end' => time(),
            'all_time' => false,
        ];
    }
}

if (!function_exists('conversion_event_matches_filters')) {

    function conversion_event_matches_filters(array $event, array $filters = []): bool
    {
        foreach (['source', 'category', 'location', 'type', 'intent', 'channel'] as $key) {
            $filter = strtolower(trim((string)($filters[$key] ?? '')));

            if ($filter === '') {
                continue;
            }

            $value = strtolower(trim((string)($event[$key] ?? '')));

            if ($value === '' || !str_contains($value, $filter)) {
                return false;
            }
        }

        $eventGroupFilter = strtolower(trim((string)($filters['event_group'] ?? '')));
        if ($eventGroupFilter !== '') {
            $eventGroup = strtolower((string)($event['_event_group'] ?? ''));
            $eventKind = strtolower((string)($event['_event_kind'] ?? ''));
            $channel = strtolower((string)($event['channel'] ?? ''));

            if ($eventGroupFilter === 'high_intent') {
                if ($eventKind !== 'high_intent') {
                    return false;
                }
            } elseif ($eventGroupFilter === 'support') {
                if ($eventKind !== 'support') {
                    return false;
                }
            } elseif ($eventGroupFilter === 'whatsapp') {
                if (empty($event['is_whatsapp']) && $channel !== 'whatsapp') {
                    return false;
                }
            } elseif ($eventGroup !== $eventGroupFilter && $channel !== $eventGroupFilter) {
                return false;
            }
        }

        if (!empty($filters['whatsapp_only']) && empty($event['is_whatsapp'])) {
            return false;
        }

        return true;
    }
}

if (!function_exists('conversion_read_lead_events')) {

    function conversion_read_lead_events(int $days = 30, array $filters = [], int $maxEvents = 120000): array
    {
        $maxEvents = max(100, min(200000, $maxEvents));
        $window = conversion_dashboard_window($days, $filters);
        $startTs = $window['start'];
        $endTs = $window['end'] ?: time();
        $events = [];

        if (function_exists('storage_adapter_mysql_read_analytics_events') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('analytics_events')) {
            $mysqlEvents = storage_adapter_mysql_read_analytics_events($days, $filters, $maxEvents);
            if (is_array($mysqlEvents)) {
                return $mysqlEvents;
            }
        }

        foreach (conversion_lead_log_files($days, $startTs, $endTs) as $file) {
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

                $timestamp = conversion_event_timestamp($event);
                if ($timestamp <= 0) {
                    continue;
                }

                if ($startTs !== null && $timestamp < $startTs) {
                    continue;
                }

                if ($endTs !== null && $timestamp > $endTs) {
                    continue;
                }

                $event['_ts'] = $timestamp;
                $event = function_exists('conversion_enrich_lead_event') ? conversion_enrich_lead_event($event) : $event;

                if (!conversion_event_matches_filters($event, $filters)) {
                    continue;
                }

                $events[] = $event;

                if (count($events) >= $maxEvents) {
                    break 2;
                }
            }

            fclose($handle);
        }

        usort($events, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));

        return $events;
    }
}

if (!function_exists('conversion_count_by')) {

    function conversion_count_by(array $events, string $key, int $limit = 10): array
    {
        $counts = [];

        foreach ($events as $event) {
            $value = trim((string)($event[$key] ?? ''));
            $value = $value !== '' ? $value : 'Tidak diketahui';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        arsort($counts);

        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('conversion_count_pages')) {

    function conversion_count_pages(array $events, int $limit = 10): array
    {
        $counts = [];

        foreach ($events as $event) {
            $path = trim((string)($event['page_path'] ?? ''));
            $path = $path !== '' ? $path : '/';
            $counts[$path] = ($counts[$path] ?? 0) + 1;
        }

        arsort($counts);

        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('conversion_event_text_blob')) {

    function conversion_event_text_blob(array $event): string
    {
        return strtolower(implode(' ', array_map(static fn($value): string => is_scalar($value) ? (string)$value : '', [
            $event['source'] ?? '',
            $event['type'] ?? '',
            $event['channel'] ?? '',
            $event['intent'] ?? '',
            $event['label'] ?? '',
            $event['page_path'] ?? '',
            $event['target_url'] ?? '',
        ])));
    }
}

if (!function_exists('conversion_event_group')) {

    function conversion_event_group(array $event): string
    {
        $blob = conversion_event_text_blob($event);
        $channel = strtolower(trim((string)($event['channel'] ?? '')));

        if (!empty($event['is_whatsapp']) || $channel === 'whatsapp' || str_contains($blob, 'whatsapp') || str_contains($blob, 'wa.me')) {
            return 'conversion';
        }

        if (str_contains($blob, 'payment-proof') || str_contains($blob, 'proof_submit') || str_contains($blob, 'proof-submit') || str_contains($blob, 'bukti pembayaran')) {
            return 'payment';
        }

        if (str_contains($blob, 'invoice_view') || str_contains($blob, 'invoice-view') || str_contains($blob, 'public-invoice-view') || str_contains($blob, '/invoice')) {
            return 'page_view';
        }

        if (str_contains($blob, 'order_status') || str_contains($blob, 'order-status') || str_contains($blob, 'public-order-status') || str_contains($blob, '/order-status')) {
            return 'page_view';
        }

        if (str_contains($blob, 'order_submit') || str_contains($blob, 'order-submit') || str_contains($blob, 'checkout_submit') || str_contains($blob, 'checkout-submit')) {
            return 'order';
        }

        if (str_contains($blob, 'inquiry_submit') || str_contains($blob, 'inquiry-submit') || str_contains($blob, 'form_submit') || str_contains($blob, 'form-submit')) {
            return 'inquiry';
        }

        if ($channel === 'checkout') {
            return 'order';
        }

        if ($channel === 'payment') {
            return 'payment';
        }

        if ($channel === 'form') {
            return 'inquiry';
        }

        if (str_contains($blob, 'page_view') || str_contains($blob, 'viewed')) {
            return 'page_view';
        }

        return 'interaction';
    }
}

if (!function_exists('conversion_event_group_label')) {

    function conversion_event_group_label(string $group): string
    {
        return match ($group) {
            'order' => 'Order',
            'payment' => 'Payment',
            'inquiry' => 'Inquiry',
            'conversion' => 'Conversion',
            'page_view' => 'Page View',
            default => 'Interaction',
        };
    }
}

if (!function_exists('conversion_event_kind')) {

    function conversion_event_kind(array $event): string
    {
        $group = (string)($event['_event_group'] ?? conversion_event_group($event));

        if (in_array($group, ['order', 'payment', 'inquiry', 'conversion'], true)) {
            return 'high_intent';
        }

        return 'support';
    }
}

if (!function_exists('conversion_event_priority')) {

    function conversion_event_priority(array $event): int
    {
        $group = (string)($event['_event_group'] ?? conversion_event_group($event));

        return match ($group) {
            'order' => 260,
            'payment' => 240,
            'inquiry' => 220,
            'conversion' => 200,
            'page_view' => 80,
            default => 60,
        };
    }
}

if (!function_exists('conversion_enrich_lead_event')) {

    function conversion_enrich_lead_event(array $event): array
    {
        if (empty($event['channel'])) {
            $event['channel'] = !empty($event['is_whatsapp']) ? 'whatsapp' : ((string)($event['type'] ?? 'click') ?: 'click');
        }

        $group = conversion_event_group($event);
        $event['_event_group'] = $group;
        $event['_event_group_label'] = conversion_event_group_label($group);
        $event['_event_kind'] = conversion_event_kind($event);
        $event['_event_priority'] = conversion_event_priority($event);
        $event['_repeat_count'] = (int)($event['_repeat_count'] ?? 1);

        return $event;
    }
}

if (!function_exists('conversion_event_dedupe_key')) {

    function conversion_event_dedupe_key(array $event): string
    {
        $path = trim((string)($event['page_path'] ?? ''));
        $target = trim((string)($event['target_url'] ?? ''));
        $pathOnly = $path !== '' ? (string)(parse_url($path, PHP_URL_PATH) ?: $path) : '';
        $query = (string)(parse_url($path, PHP_URL_QUERY) ?: '');
        $queryData = [];
        if ($query !== '') {
            parse_str($query, $queryData);
        }

        $targetPath = $target !== '' ? (string)(parse_url($target, PHP_URL_PATH) ?: '') : '';
        $targetQuery = $target !== '' ? (string)(parse_url($target, PHP_URL_QUERY) ?: '') : '';
        if ($targetQuery !== '') {
            $targetData = [];
            parse_str($targetQuery, $targetData);
            $queryData = array_merge($queryData, $targetData);
        }

        $safeRef = '';
        foreach (['ref', 'order_ref', 'invoice', 'inv', 'id'] as $key) {
            if (!empty($queryData[$key]) && is_scalar($queryData[$key])) {
                $safeRef = conversion_clean_text((string)$queryData[$key], 80);
                break;
            }
        }

        $label = conversion_clean_text((string)($event['label'] ?? ''), 80);
        $stableLabel = $safeRef !== '' ? $safeRef : $label;

        $parts = [
            (string)($event['_event_group'] ?? conversion_event_group($event)),
            strtolower((string)($event['source'] ?? '')),
            strtolower((string)($event['type'] ?? '')),
            strtolower((string)($event['channel'] ?? '')),
            strtolower((string)($event['intent'] ?? '')),
            strtolower($pathOnly),
            strtolower($targetPath),
            strtolower($stableLabel),
        ];

        return sha1(implode('|', $parts));
    }
}

if (!function_exists('conversion_dedupe_lead_events')) {

    function conversion_dedupe_lead_events(array $events, int $bucketMinutes = 10): array
    {
        $bucketSeconds = max(60, min(3600, $bucketMinutes * 60));
        $result = [];
        $index = [];

        usort($events, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? conversion_event_timestamp($b))) <=> ((int)($a['_ts'] ?? conversion_event_timestamp($a))));

        foreach ($events as $event) {
            $event = conversion_enrich_lead_event($event);
            $timestamp = (int)($event['_ts'] ?? conversion_event_timestamp($event));
            if ($timestamp <= 0) {
                continue;
            }

            $bucket = (string)floor($timestamp / $bucketSeconds);
            $key = conversion_event_dedupe_key($event) . '|' . $bucket;

            if (isset($index[$key])) {
                $position = $index[$key];
                $result[$position]['_repeat_count'] = (int)($result[$position]['_repeat_count'] ?? 1) + 1;
                $result[$position]['_duplicate_last_ts'] = max((int)($result[$position]['_duplicate_last_ts'] ?? $timestamp), $timestamp);
                continue;
            }

            $event['_repeat_count'] = 1;
            $event['_dedupe_bucket'] = $bucket;
            $index[$key] = count($result);
            $result[] = $event;
        }

        return $result;
    }
}

if (!function_exists('conversion_prioritized_lead_events')) {

    function conversion_prioritized_lead_events(array $events, int $limit = 12): array
    {
        $events = conversion_dedupe_lead_events($events, 10);
        usort($events, static function (array $a, array $b): int {
            $priority = ((int)($b['_event_priority'] ?? 0)) <=> ((int)($a['_event_priority'] ?? 0));
            if ($priority !== 0) {
                return $priority;
            }
            return ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0));
        });

        return array_slice($events, 0, max(1, $limit));
    }
}

if (!function_exists('conversion_daily_counts')) {

    function conversion_daily_counts(array $events, int $days = 30, ?int $startTs = null, ?int $endTs = null): array
    {
        $daily = [];
        $endTs = $endTs ?: time();

        if ($startTs === null) {
            $days = max(1, min(120, $days));
            $startTs = strtotime('-' . ($days - 1) . ' days', strtotime(date('Y-m-d 00:00:00', $endTs))) ?: time();
        } else {
            $diffDays = max(1, (int)ceil(($endTs - $startTs) / 86400) + 1);
            $days = min(120, $diffDays);
            if ($diffDays > 120) {
                $startTs = strtotime('-119 days', strtotime(date('Y-m-d 00:00:00', $endTs))) ?: $startTs;
            }
        }

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days', strtotime(date('Y-m-d 00:00:00', $endTs))));
            $daily[$day] = 0;
        }

        foreach ($events as $event) {
            $timestamp = (int)($event['_ts'] ?? conversion_event_timestamp($event));
            if ($timestamp <= 0) {
                continue;
            }

            $day = date('Y-m-d', $timestamp);
            if (array_key_exists($day, $daily)) {
                $daily[$day]++;
            }
        }

        return $daily;
    }
}

if (!function_exists('conversion_monthly_counts')) {

    function conversion_monthly_counts(array $events, ?int $startTs = null, ?int $endTs = null): array
    {
        $months = [];
        $endTs = $endTs ?: time();

        if ($startTs === null) {
            $oldest = null;
            foreach ($events as $event) {
                $timestamp = (int)($event['_ts'] ?? conversion_event_timestamp($event));
                if ($timestamp > 0 && ($oldest === null || $timestamp < $oldest)) {
                    $oldest = $timestamp;
                }
            }
            $startTs = $oldest ?: strtotime('-11 months', $endTs);
        }

        $cursor = strtotime(date('Y-m-01 00:00:00', $startTs)) ?: $startTs;
        $endMonth = strtotime(date('Y-m-01 00:00:00', $endTs)) ?: $endTs;
        $guard = 0;

        while ($cursor <= $endMonth && $guard < 120) {
            $months[date('Y-m', $cursor)] = 0;
            $cursor = strtotime('+1 month', $cursor) ?: ($cursor + 2678400);
            $guard++;
        }

        foreach ($events as $event) {
            $timestamp = (int)($event['_ts'] ?? conversion_event_timestamp($event));
            if ($timestamp <= 0) {
                continue;
            }

            $month = date('Y-m', $timestamp);
            if (array_key_exists($month, $months)) {
                $months[$month]++;
            }
        }

        return $months;
    }
}

if (!function_exists('conversion_hourly_counts')) {

    function conversion_hourly_counts(array $events): array
    {
        $hourly = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hourly[str_pad((string)$hour, 2, '0', STR_PAD_LEFT)] = 0;
        }

        foreach ($events as $event) {
            $timestamp = (int)($event['_ts'] ?? conversion_event_timestamp($event));
            if ($timestamp <= 0) {
                continue;
            }

            $hour = date('H', $timestamp);
            $hourly[$hour] = ($hourly[$hour] ?? 0) + 1;
        }

        return $hourly;
    }
}

if (!function_exists('conversion_dashboard_summary')) {

    function conversion_dashboard_summary(int $days = 30, array $filters = []): array
    {
        $window = conversion_dashboard_window($days, $filters);
        $rawEvents = conversion_read_lead_events($days, $filters);
        $viewMode = strtolower(trim((string)($filters['_view_mode'] ?? 'compact')));
        $viewMode = $viewMode === 'raw' ? 'raw' : 'compact';
        $compactEvents = conversion_dedupe_lead_events($rawEvents, 10);
        $events = $viewMode === 'raw' ? $rawEvents : $compactEvents;
        $total = count($events);
        $totalRaw = count($rawEvents);
        $totalCompact = count($compactEvents);
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $todayCount = 0;
        $yesterdayCount = 0;
        $whatsappCount = 0;
        $highIntentCount = 0;
        $supportCount = 0;

        foreach ($events as $event) {
            $timestamp = (int)($event['_ts'] ?? conversion_event_timestamp($event));
            $day = $timestamp > 0 ? date('Y-m-d', $timestamp) : '';

            if ($day === $today) {
                $todayCount++;
            }

            if ($day === $yesterday) {
                $yesterdayCount++;
            }

            if (!empty($event['is_whatsapp'])) {
                $whatsappCount++;
            }

            if ((string)($event['_event_kind'] ?? '') === 'high_intent') {
                $highIntentCount++;
            } else {
                $supportCount++;
            }
        }

        $daily = conversion_daily_counts($events, 30, $window['start'], $window['end']);
        $monthly = conversion_monthly_counts($events, $window['start'], $window['end']);
        $maxDaily = max($daily ?: [0]);
        $maxMonthly = max($monthly ?: [0]);
        $topChannels = conversion_count_by($events, 'channel', 8);
        $topGroups = conversion_count_by($events, '_event_group_label', 8);
        $topCategories = conversion_count_by($events, 'category', 8);
        $topSources = conversion_count_by($events, 'source', 8);
        $topLocations = conversion_count_by($events, 'location', 8);
        $topIntents = conversion_count_by($events, 'intent', 8);
        $topLabels = conversion_count_by($events, 'label', 8);
        $topPages = conversion_count_pages($events, 10);

        return [
            'enabled' => conversion_tracking_enabled(),
            'days' => $days,
            'filters' => $filters,
            'view_mode' => $viewMode,
            'range' => [
                'start' => $window['start'] ? date('Y-m-d', (int)$window['start']) : null,
                'end' => $window['end'] ? date('Y-m-d', (int)$window['end']) : null,
                'all_time' => !empty($window['all_time']),
            ],
            'available_years' => conversion_available_lead_years(),
            'total' => $total,
            'total_raw' => $totalRaw,
            'total_compact' => $totalCompact,
            'today' => $todayCount,
            'yesterday' => $yesterdayCount,
            'whatsapp' => $whatsappCount,
            'high_intent' => $highIntentCount,
            'support' => $supportCount,
            'conversion_rate_label' => $total > 0 ? round(($whatsappCount / $total) * 100, 1) . '%' : '0%',
            'top_category' => $topCategories ? (string)array_key_first($topCategories) : '-',
            'top_source' => $topSources ? (string)array_key_first($topSources) : '-',
            'top_location' => $topLocations ? (string)array_key_first($topLocations) : '-',
            'top_channel' => $topChannels ? (string)array_key_first($topChannels) : '-',
            'daily' => $daily,
            'max_daily' => $maxDaily,
            'monthly' => $monthly,
            'max_monthly' => $maxMonthly,
            'hourly' => conversion_hourly_counts($events),
            'by_channel' => $topChannels,
            'by_group' => $topGroups,
            'by_category' => $topCategories,
            'by_source' => $topSources,
            'by_location' => $topLocations,
            'by_intent' => $topIntents,
            'by_label' => $topLabels,
            'top_pages' => $topPages,
            'recent' => array_slice($events, 0, 30),
            'recent_raw' => array_slice($rawEvents, 0, 30),
            'generated_at' => date('c'),
        ];
    }
}
