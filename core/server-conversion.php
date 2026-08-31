<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| GOOGLE ADS OFFLINE/SERVER-SIDE CONVERSION SENDER BETA - Template
|--------------------------------------------------------------------------
| Local-first settings and log storage for ad conversion APIs
| such as Meta CAPI, TikTok Events API, and Google Ads conversion upload.
| Manual-first conversion sender with retry, response log, preview, fail-safe processing, admin UX sync, scheduled processing, Google Ads readiness, and privacy-safe logs.
|--------------------------------------------------------------------------
*/

if (!function_exists('server_conversion_settings_file')) {
    function server_conversion_settings_file(): string
    {
        return STORAGE_PATH . '/server-conversion-settings.json';
    }
}

if (!function_exists('server_conversion_queue_file')) {
    function server_conversion_queue_file(): string
    {
        return STORAGE_PATH . '/server-conversion-queue.json';
    }
}

if (!function_exists('server_conversion_google_ads_queue_file')) {
    function server_conversion_google_ads_queue_file(): string
    {
        return STORAGE_PATH . '/google-ads-conversion-queue.json';
    }
}

if (!function_exists('server_conversion_log_file')) {
    function server_conversion_log_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/server-conversions-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('server_conversion_log_files')) {
    function server_conversion_log_files(int $days = 3650): array
    {
        if (!defined('LOGS_PATH') || !is_dir(LOGS_PATH)) {
            return [];
        }

        $files = glob(LOGS_PATH . '/server-conversions-*.jsonl') ?: [];
        if ($days > 0) {
            $min = strtotime('-' . max(1, min(3650, $days)) . ' days') ?: 0;
            $files = array_values(array_filter($files, static fn(string $file): bool => @filemtime($file) >= $min));
        }
        rsort($files, SORT_STRING);
        return $files;
    }
}

if (!function_exists('server_conversion_clean')) {
    function server_conversion_clean(string $value, int $max = 160): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$value) ?: '';
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('server_conversion_secret_clean')) {
    function server_conversion_secret_clean(string $value, int $max = 700): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x1F\x7F\s]+/', '', (string)$value) ?: '';
        $value = preg_replace('/[^A-Za-z0-9_\-\.\|:~]/', '', $value) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('server_conversion_digits_clean')) {
    function server_conversion_digits_clean(string $value, int $max = 40): string
    {
        $value = preg_replace('/[^0-9]/', '', trim($value)) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('server_conversion_id_clean')) {
    function server_conversion_id_clean(string $value, int $max = 100): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9_\-\.\/]/', '', (string)$value) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('server_conversion_clean_event_id')) {
    function server_conversion_clean_event_id(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_\-]/', '', trim($value)) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, 90, 'UTF-8') : substr($value, 0, 90);
    }
}

if (!function_exists('server_conversion_mask_secret')) {
    function server_conversion_mask_secret(string $secret): string
    {
        $secret = server_conversion_secret_clean($secret);
        if ($secret === '') {
            return 'Belum diisi';
        }
        if (strlen($secret) <= 10) {
            return str_repeat('•', max(6, strlen($secret)));
        }
        return substr($secret, 0, 5) . str_repeat('•', 8) . substr($secret, -4);
    }
}

if (!function_exists('server_conversion_google_ads_default_mapping')) {
    function server_conversion_google_ads_default_mapping(): array
    {
        return [
            'contact_whatsapp' => [
                'enabled' => true,
                'label' => 'WhatsApp Lead',
                'conversion_action_id' => '',
                'default_value' => 0,
            ],
            'submit_inquiry' => [
                'enabled' => true,
                'label' => 'Form Lead / Inquiry',
                'conversion_action_id' => '',
                'default_value' => 0,
            ],
            'begin_checkout' => [
                'enabled' => true,
                'label' => 'Checkout / Order Intent',
                'conversion_action_id' => '',
                'default_value' => 0,
            ],
            'order_success' => [
                'enabled' => true,
                'label' => 'Order Success',
                'conversion_action_id' => '',
                'default_value' => 0,
            ],
            'upload_payment_proof' => [
                'enabled' => true,
                'label' => 'Payment Proof / Purchase Signal',
                'conversion_action_id' => '',
                'default_value' => 0,
            ],
        ];
    }
}

if (!function_exists('server_conversion_default_settings')) {
    function server_conversion_default_settings(): array
    {
        return [
            'enabled' => false,
            'test_mode' => true,
            'advanced_matching_enabled' => false,
            'queue_high_intent_only' => true,
            'sending_mode' => 'manual',
            'max_events_per_run' => 20,
            'cron' => [
                'enabled' => false,
                'token' => '',
                'max_events_per_run' => 20,
                'retry_failed' => true,
                'last_run_at' => null,
                'last_result' => [],
            ],
            'sync' => [
                'meta_use_browser_pixel_id' => true,
                'tiktok_use_browser_pixel_id' => true,
            ],
            'meta' => [
                'enabled' => false,
                'dataset_id' => '',
                'access_token' => '',
                'api_version' => 'v20.0',
                'test_event_code' => '',
            ],
            'tiktok' => [
                'enabled' => false,
                'pixel_id' => '',
                'access_token' => '',
                'api_version' => 'v1.3',
                'test_event_code' => '',
            ],
            'google_ads' => [
                'enabled' => false,
                'customer_id' => '',
                'conversion_action_id' => '',
                'capture_click_ids_enabled' => true,
                'queue_enabled' => true,
                'currency' => 'IDR',
                'mapping' => server_conversion_google_ads_default_mapping(),
                'sender' => [
                    'enabled' => false,
                    'validate_only' => true,
                    'partial_failure' => true,
                    'max_events_per_run' => 10,
                    'last_run_at' => null,
                    'last_result' => [],
                ],
                'oauth' => [
                    'developer_token_set' => false,
                    'oauth_connected' => false,
                ],
            ],
            'privacy' => [
                'strip_pii' => true,
                'hash_advanced_matching_only_with_consent' => true,
            ],
            'updated_at' => null,
        ];
    }
}

if (!function_exists('server_conversion_deep_merge')) {
    function server_conversion_deep_merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = server_conversion_deep_merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}

if (!function_exists('server_conversion_normalize_settings')) {
    function server_conversion_normalize_settings(array $settings): array
    {
        $settings = server_conversion_deep_merge(server_conversion_default_settings(), $settings);
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['test_mode'] = !empty($settings['test_mode']);
        $settings['advanced_matching_enabled'] = !empty($settings['advanced_matching_enabled']);
        $settings['queue_high_intent_only'] = !empty($settings['queue_high_intent_only']);
        $mode = (string)($settings['sending_mode'] ?? 'manual');
        $settings['sending_mode'] = in_array($mode, ['manual', 'auto', 'hybrid', 'disabled'], true) ? $mode : 'manual';
        $settings['max_events_per_run'] = max(1, min(100, (int)($settings['max_events_per_run'] ?? 20)));
        $settings['cron']['enabled'] = !empty($settings['cron']['enabled']);
        $settings['cron']['token'] = server_conversion_secret_clean((string)($settings['cron']['token'] ?? ''), 160);
        $settings['cron']['max_events_per_run'] = max(1, min(100, (int)($settings['cron']['max_events_per_run'] ?? $settings['max_events_per_run'] ?? 20)));
        $settings['cron']['retry_failed'] = !array_key_exists('retry_failed', (array)($settings['cron'] ?? [])) || !empty($settings['cron']['retry_failed']);
        $settings['cron']['last_run_at'] = server_conversion_clean((string)($settings['cron']['last_run_at'] ?? ''), 40) ?: null;
        $settings['cron']['last_result'] = is_array($settings['cron']['last_result'] ?? null) ? $settings['cron']['last_result'] : [];
        $settings['sync']['meta_use_browser_pixel_id'] = !empty($settings['sync']['meta_use_browser_pixel_id']);
        $settings['sync']['tiktok_use_browser_pixel_id'] = !empty($settings['sync']['tiktok_use_browser_pixel_id']);

        foreach (['meta', 'tiktok', 'google_ads'] as $platform) {
            $settings[$platform]['enabled'] = !empty($settings[$platform]['enabled']);
        }

        $settings['meta']['dataset_id'] = server_conversion_digits_clean((string)($settings['meta']['dataset_id'] ?? ''), 40);
        $settings['meta']['access_token'] = server_conversion_secret_clean((string)($settings['meta']['access_token'] ?? ''), 700);
        $settings['meta']['api_version'] = server_conversion_id_clean((string)($settings['meta']['api_version'] ?? 'v20.0'), 20);
        if (!preg_match('/^v[0-9]+\.[0-9]+$/', (string)$settings['meta']['api_version'])) {
            $settings['meta']['api_version'] = 'v20.0';
        }
        $settings['meta']['test_event_code'] = server_conversion_id_clean((string)($settings['meta']['test_event_code'] ?? ''), 80);

        $settings['tiktok']['pixel_id'] = server_conversion_id_clean((string)($settings['tiktok']['pixel_id'] ?? ''), 50);
        if ($settings['tiktok']['pixel_id'] !== '' && !preg_match('/^[A-Z0-9]{8,50}$/i', (string)$settings['tiktok']['pixel_id'])) {
            $settings['tiktok']['pixel_id'] = '';
        }
        $settings['tiktok']['access_token'] = server_conversion_secret_clean((string)($settings['tiktok']['access_token'] ?? ''), 700);
        $settings['tiktok']['api_version'] = server_conversion_id_clean((string)($settings['tiktok']['api_version'] ?? 'v1.3'), 20);
        if (!preg_match('/^v[0-9]+\.[0-9]+$/', (string)$settings['tiktok']['api_version'])) {
            $settings['tiktok']['api_version'] = 'v1.3';
        }
        $settings['tiktok']['test_event_code'] = server_conversion_id_clean((string)($settings['tiktok']['test_event_code'] ?? ''), 80);

        $settings['google_ads']['customer_id'] = server_conversion_digits_clean((string)($settings['google_ads']['customer_id'] ?? ''), 20);
        $settings['google_ads']['conversion_action_id'] = server_conversion_id_clean((string)($settings['google_ads']['conversion_action_id'] ?? ''), 160);
        $settings['google_ads']['capture_click_ids_enabled'] = !array_key_exists('capture_click_ids_enabled', (array)($settings['google_ads'] ?? [])) || !empty($settings['google_ads']['capture_click_ids_enabled']);
        $settings['google_ads']['queue_enabled'] = !array_key_exists('queue_enabled', (array)($settings['google_ads'] ?? [])) || !empty($settings['google_ads']['queue_enabled']);
        $currency = strtoupper(server_conversion_id_clean((string)($settings['google_ads']['currency'] ?? 'IDR'), 3));
        $settings['google_ads']['currency'] = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : 'IDR';
        $baseMapping = server_conversion_google_ads_default_mapping();
        $incomingMapping = is_array($settings['google_ads']['mapping'] ?? null) ? $settings['google_ads']['mapping'] : [];
        $settings['google_ads']['mapping'] = [];
        foreach ($baseMapping as $eventName => $defaultRow) {
            $row = is_array($incomingMapping[$eventName] ?? null) ? $incomingMapping[$eventName] : [];
            $settings['google_ads']['mapping'][$eventName] = [
                'enabled' => !array_key_exists('enabled', $row) || !empty($row['enabled']),
                'label' => server_conversion_clean((string)($row['label'] ?? $defaultRow['label']), 80),
                'conversion_action_id' => server_conversion_id_clean((string)($row['conversion_action_id'] ?? $defaultRow['conversion_action_id'] ?? ''), 160),
                'default_value' => max(0, min(999999999, (int)($row['default_value'] ?? $defaultRow['default_value'] ?? 0))),
            ];
        }
        $settings['google_ads']['sender'] = is_array($settings['google_ads']['sender'] ?? null) ? $settings['google_ads']['sender'] : [];
        $settings['google_ads']['sender']['enabled'] = !empty($settings['google_ads']['sender']['enabled']);
        $settings['google_ads']['sender']['validate_only'] = !array_key_exists('validate_only', (array)($settings['google_ads']['sender'] ?? [])) || !empty($settings['google_ads']['sender']['validate_only']);
        $settings['google_ads']['sender']['partial_failure'] = !array_key_exists('partial_failure', (array)($settings['google_ads']['sender'] ?? [])) || !empty($settings['google_ads']['sender']['partial_failure']);
        $settings['google_ads']['sender']['max_events_per_run'] = max(1, min(100, (int)($settings['google_ads']['sender']['max_events_per_run'] ?? 10)));
        $settings['google_ads']['sender']['last_run_at'] = server_conversion_clean((string)($settings['google_ads']['sender']['last_run_at'] ?? ''), 40) ?: null;
        $settings['google_ads']['sender']['last_result'] = is_array($settings['google_ads']['sender']['last_result'] ?? null) ? $settings['google_ads']['sender']['last_result'] : [];

        $settings['google_ads']['oauth'] = is_array($settings['google_ads']['oauth'] ?? null) ? $settings['google_ads']['oauth'] : [];
        $settings['google_ads']['oauth']['developer_token_set'] = !empty($settings['google_ads']['oauth']['developer_token_set']);
        $settings['google_ads']['oauth']['oauth_connected'] = !empty($settings['google_ads']['oauth']['oauth_connected']);

        $settings['privacy']['strip_pii'] = true;
        $settings['privacy']['hash_advanced_matching_only_with_consent'] = true;

        return $settings;
    }
}

if (!function_exists('server_conversion_read_settings')) {
    function server_conversion_read_settings(): array
    {
        $file = server_conversion_settings_file();
        if (!is_file($file)) {
            return server_conversion_normalize_settings(server_conversion_default_settings());
        }

        $data = json_decode((string)@file_get_contents($file), true);
        return server_conversion_normalize_settings(is_array($data) ? $data : []);
    }
}

if (!function_exists('server_conversion_write_settings')) {
    function server_conversion_write_settings(array $settings): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }

        $settings = server_conversion_normalize_settings($settings);
        $settings['updated_at'] = date('c');

        return @file_put_contents(
            server_conversion_settings_file(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        ) !== false;
    }
}

if (!function_exists('server_conversion_settings_from_post')) {
    function server_conversion_settings_from_post(array $post, array $current = []): array
    {
        $current = server_conversion_normalize_settings($current ?: server_conversion_read_settings());
        $analytics = function_exists('analytics_read_settings') ? analytics_read_settings() : [];
        $browserMetaId = function_exists('analytics_digits_clean')
            ? analytics_digits_clean((string)($analytics['pixels']['meta']['pixel_id'] ?? ''), 40)
            : server_conversion_digits_clean((string)($analytics['pixels']['meta']['pixel_id'] ?? ''), 40);
        $browserTikTokId = function_exists('analytics_id_clean')
            ? analytics_id_clean((string)($analytics['pixels']['tiktok']['pixel_id'] ?? ''), 50)
            : server_conversion_id_clean((string)($analytics['pixels']['tiktok']['pixel_id'] ?? ''), 50);

        $metaToken = server_conversion_secret_clean((string)($post['server_meta_access_token'] ?? ''));
        $tiktokToken = server_conversion_secret_clean((string)($post['server_tiktok_access_token'] ?? ''));
        $cronToken = server_conversion_secret_clean((string)($post['server_conversion_cron_token'] ?? ''), 160);
        $metaUseBrowser = !empty($post['server_meta_use_browser_pixel_id']);
        $tiktokUseBrowser = !empty($post['server_tiktok_use_browser_pixel_id']);

        $metaDatasetId = server_conversion_digits_clean((string)($post['server_meta_dataset_id'] ?? ''), 40);
        if ($metaUseBrowser && $browserMetaId !== '') {
            $metaDatasetId = $browserMetaId;
        }

        $tiktokPixelId = server_conversion_id_clean((string)($post['server_tiktok_pixel_id'] ?? ''), 50);
        if ($tiktokUseBrowser && $browserTikTokId !== '') {
            $tiktokPixelId = $browserTikTokId;
        }

        return server_conversion_normalize_settings([
            'enabled' => !empty($post['server_conversion_enabled']),
            'test_mode' => !empty($post['server_conversion_test_mode']),
            'advanced_matching_enabled' => !empty($post['server_conversion_advanced_matching_enabled']),
            'queue_high_intent_only' => !empty($post['server_conversion_queue_high_intent_only']),
            'sending_mode' => server_conversion_id_clean((string)($post['server_conversion_sending_mode'] ?? 'manual'), 20),
            'max_events_per_run' => (int)($post['server_conversion_max_events_per_run'] ?? 20),
            'cron' => [
                'enabled' => !empty($post['server_conversion_cron_enabled']),
                'token' => $cronToken !== '' ? $cronToken : (string)($current['cron']['token'] ?? ''),
                'max_events_per_run' => (int)($post['server_conversion_cron_max_events_per_run'] ?? ($current['cron']['max_events_per_run'] ?? 20)),
                'retry_failed' => !empty($post['server_conversion_cron_retry_failed']),
                'last_run_at' => $current['cron']['last_run_at'] ?? null,
                'last_result' => is_array($current['cron']['last_result'] ?? null) ? $current['cron']['last_result'] : [],
            ],
            'sync' => [
                'meta_use_browser_pixel_id' => $metaUseBrowser,
                'tiktok_use_browser_pixel_id' => $tiktokUseBrowser,
            ],
            'meta' => [
                'enabled' => !empty($post['server_meta_enabled']),
                'dataset_id' => $metaDatasetId,
                'access_token' => $metaToken !== '' ? $metaToken : (string)($current['meta']['access_token'] ?? ''),
                'api_version' => server_conversion_id_clean((string)($post['server_meta_api_version'] ?? ($current['meta']['api_version'] ?? 'v20.0')), 20),
                'test_event_code' => server_conversion_id_clean((string)($post['server_meta_test_event_code'] ?? ''), 80),
            ],
            'tiktok' => [
                'enabled' => !empty($post['server_tiktok_enabled']),
                'pixel_id' => $tiktokPixelId,
                'access_token' => $tiktokToken !== '' ? $tiktokToken : (string)($current['tiktok']['access_token'] ?? ''),
                'api_version' => server_conversion_id_clean((string)($post['server_tiktok_api_version'] ?? ($current['tiktok']['api_version'] ?? 'v1.3')), 20),
                'test_event_code' => server_conversion_id_clean((string)($post['server_tiktok_test_event_code'] ?? ''), 80),
            ],
            'google_ads' => [
                'enabled' => !empty($post['server_google_ads_enabled']),
                'customer_id' => server_conversion_digits_clean((string)($post['server_google_ads_customer_id'] ?? ''), 20),
                'conversion_action_id' => server_conversion_id_clean((string)($post['server_google_ads_conversion_action_id'] ?? ''), 160),
                'capture_click_ids_enabled' => !empty($post['server_google_ads_capture_click_ids_enabled']),
                'queue_enabled' => !empty($post['server_google_ads_queue_enabled']),
                'currency' => server_conversion_id_clean((string)($post['server_google_ads_currency'] ?? ($current['google_ads']['currency'] ?? 'IDR')), 3),
                'mapping' => server_conversion_google_ads_mapping_from_post($post, (array)($current['google_ads']['mapping'] ?? [])),
                'sender' => [
                    'enabled' => !empty($post['server_google_ads_sender_enabled']),
                    'validate_only' => !empty($post['server_google_ads_sender_validate_only']),
                    'partial_failure' => !empty($post['server_google_ads_sender_partial_failure']),
                    'max_events_per_run' => (int)($post['server_google_ads_sender_max_events_per_run'] ?? ($current['google_ads']['sender']['max_events_per_run'] ?? 10)),
                    'last_run_at' => $current['google_ads']['sender']['last_run_at'] ?? null,
                    'last_result' => is_array($current['google_ads']['sender']['last_result'] ?? null) ? $current['google_ads']['sender']['last_result'] : [],
                ],
                'oauth' => [
                    'developer_token_set' => !empty($current['google_ads']['oauth']['developer_token_set']),
                    'oauth_connected' => !empty($current['google_ads']['oauth']['oauth_connected']),
                ],
            ],
        ]);
    }
}

if (!function_exists('server_conversion_google_ads_mapping_from_post')) {
    function server_conversion_google_ads_mapping_from_post(array $post, array $current = []): array
    {
        $defaults = server_conversion_google_ads_default_mapping();
        $mapping = [];
        foreach ($defaults as $eventName => $defaultRow) {
            $prefix = 'server_google_ads_map_' . $eventName . '_';
            $currentRow = is_array($current[$eventName] ?? null) ? $current[$eventName] : [];
            $mapping[$eventName] = [
                'enabled' => !empty($post[$prefix . 'enabled']),
                'label' => server_conversion_clean((string)($post[$prefix . 'label'] ?? $currentRow['label'] ?? $defaultRow['label']), 80),
                'conversion_action_id' => server_conversion_id_clean((string)($post[$prefix . 'conversion_action_id'] ?? $currentRow['conversion_action_id'] ?? ''), 160),
                'default_value' => max(0, min(999999999, (int)($post[$prefix . 'default_value'] ?? $currentRow['default_value'] ?? $defaultRow['default_value'] ?? 0))),
            ];
        }
        return $mapping;
    }
}

if (!function_exists('server_conversion_google_ads_read_queue')) {
    function server_conversion_google_ads_read_queue(): array
    {
        $file = server_conversion_google_ads_queue_file();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }
}

if (!function_exists('server_conversion_google_ads_write_queue')) {
    function server_conversion_google_ads_write_queue(array $queue): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        $queue = array_values(array_slice($queue, -2000));
        return @file_put_contents(
            server_conversion_google_ads_queue_file(),
            json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        ) !== false;
    }
}

if (!function_exists('server_conversion_google_ads_event_mapping')) {
    function server_conversion_google_ads_event_mapping(string $eventName, array $settings): array
    {
        $eventName = server_conversion_clean($eventName, 80);
        $mapping = is_array($settings['google_ads']['mapping'] ?? null) ? $settings['google_ads']['mapping'] : [];
        $row = is_array($mapping[$eventName] ?? null) ? $mapping[$eventName] : [];
        if (!$row && $eventName === 'Purchase') {
            $row = (array)($mapping['order_success'] ?? []);
        }
        $fallbackAction = server_conversion_id_clean((string)($settings['google_ads']['conversion_action_id'] ?? ''), 160);
        $action = server_conversion_id_clean((string)($row['conversion_action_id'] ?? ''), 160);
        if ($action === '') {
            $action = $fallbackAction;
        }
        return [
            'enabled' => !empty($row) ? !empty($row['enabled']) : in_array($eventName, array_keys(server_conversion_google_ads_default_mapping()), true),
            'label' => server_conversion_clean((string)($row['label'] ?? $eventName), 80),
            'conversion_action_id' => $action,
            'uses_fallback_action' => $action !== '' && $action === $fallbackAction && (string)($row['conversion_action_id'] ?? '') === '',
            'default_value' => max(0, (int)($row['default_value'] ?? 0)),
        ];
    }
}

if (!function_exists('server_conversion_google_ads_click_id_from_event')) {
    function server_conversion_google_ads_click_id_from_event(array $event): array
    {
        foreach (['gclid', 'gbraid', 'wbraid'] as $key) {
            $direct = server_conversion_clean((string)($event[$key] ?? ''), 220);
            if ($direct !== '') {
                return ['type' => $key, 'value' => $direct, 'source' => 'event'];
            }
        }

        $googleValue = server_conversion_clean((string)($event['google_ads_click_id'] ?? ''), 220);
        $googleType = server_conversion_clean((string)($event['google_ads_click_id_type'] ?? ''), 30);
        if ($googleValue !== '' && in_array($googleType, ['gclid', 'gbraid', 'wbraid'], true)) {
            return ['type' => $googleType, 'value' => $googleValue, 'source' => 'event_google_ads_click_id'];
        }

        $attribution = is_array($event['attribution'] ?? null) ? $event['attribution'] : [];
        foreach (['last_touch', 'first_touch'] as $touchKey) {
            $touch = is_array($attribution[$touchKey] ?? null) ? $attribution[$touchKey] : [];
            foreach (['gclid', 'gbraid', 'wbraid'] as $key) {
                $value = server_conversion_clean((string)($touch[$key] ?? ''), 220);
                if ($value !== '') {
                    return ['type' => $key, 'value' => $value, 'source' => $touchKey];
                }
            }
            $value = server_conversion_clean((string)($touch['google_ads_click_id'] ?? ''), 220);
            $type = server_conversion_clean((string)($touch['google_ads_click_id_type'] ?? ''), 30);
            if ($value !== '' && in_array($type, ['gclid', 'gbraid', 'wbraid'], true)) {
                return ['type' => $type, 'value' => $value, 'source' => $touchKey];
            }
        }

        return ['type' => '', 'value' => '', 'source' => ''];
    }
}

if (!function_exists('server_conversion_google_ads_conversion_value')) {
    function server_conversion_google_ads_conversion_value(array $event, array $mapping): int
    {
        $value = (int)($event['value'] ?? $event['price'] ?? $event['amount'] ?? $event['total'] ?? 0);
        if ($value <= 0) {
            $value = (int)($mapping['default_value'] ?? 0);
        }
        return max(0, min(999999999, $value));
    }
}

if (!function_exists('server_conversion_google_ads_prepare_payload')) {
    function server_conversion_google_ads_prepare_payload(array $event, array $settings, string $source = 'conversion_event'): array
    {
        $event = server_conversion_attach_event_id($event);
        $eventName = (string)($event['analytics_event'] ?? (function_exists('analytics_event_name') ? analytics_event_name($event) : 'custom_event'));
        $mapping = server_conversion_google_ads_event_mapping($eventName, $settings);
        $click = server_conversion_google_ads_click_id_from_event($event);
        $timestamp = function_exists('conversion_event_timestamp') ? conversion_event_timestamp($event) : (strtotime((string)($event['time'] ?? '')) ?: time());
        $timestamp = $timestamp > 0 ? $timestamp : time();
        $value = server_conversion_google_ads_conversion_value($event, $mapping);
        $status = 'ready_for_sender';
        $reason = 'Mapping dan Google click ID sudah tersedia. Sender tetap disabled sampai Google Ads pengiriman data diaktifkan.';
        if (empty($settings['google_ads']['enabled']) || empty($settings['google_ads']['queue_enabled'])) {
            $status = 'disabled';
            $reason = 'Penyimpanan data Google Ads nonaktif.';
        } elseif (empty($mapping['enabled'])) {
            $status = 'mapping_disabled';
            $reason = 'Mapping event Google Ads dinonaktifkan.';
        } elseif ((string)$mapping['conversion_action_id'] === '') {
            $status = 'missing_mapping';
            $reason = 'Pengaturan konversi belum diisi untuk aktivitas ini.';
        } elseif ((string)$click['value'] === '') {
            $status = 'missing_click_id';
            $reason = 'Tidak ada data klik iklan dari aktivitas customer.';
        }

        $payload = [
            'event_id' => (string)$event['event_id'],
            'event_name' => server_conversion_clean($eventName, 80),
            'source' => server_conversion_clean($source, 80),
            'created_at' => date('c'),
            'conversion_date_time' => date('c', $timestamp),
            'customer_id' => server_conversion_digits_clean((string)($settings['google_ads']['customer_id'] ?? ''), 20),
            'conversion_action_id' => (string)$mapping['conversion_action_id'],
            'conversion_label' => (string)$mapping['label'],
            'conversion_value' => $value,
            'currency' => server_conversion_id_clean((string)($settings['google_ads']['currency'] ?? 'IDR'), 3) ?: 'IDR',
            'click_id_type' => (string)$click['type'],
            'click_id' => (string)$click['value'],
            'click_id_source' => (string)$click['source'],
            'gclid' => (string)$click['type'] === 'gclid' ? (string)$click['value'] : '',
            'gbraid' => (string)$click['type'] === 'gbraid' ? (string)$click['value'] : '',
            'wbraid' => (string)$click['type'] === 'wbraid' ? (string)$click['value'] : '',
            'page_path' => function_exists('analytics_safe_path') ? analytics_safe_path((string)($event['page_path'] ?? '/')) : server_conversion_clean((string)($event['page_path'] ?? '/'), 180),
            'utm_source' => server_conversion_clean((string)($event['utm_source'] ?? ''), 80),
            'utm_medium' => server_conversion_clean((string)($event['utm_medium'] ?? ''), 80),
            'utm_campaign' => server_conversion_clean((string)($event['utm_campaign'] ?? ''), 120),
            'status' => $status,
            'reason' => $reason,
            'sender_ready' => false,
            'oauth_required' => true,
            'pii_safe' => true,
        ];

        return $payload;
    }
}

if (!function_exists('server_conversion_google_ads_should_queue')) {
    function server_conversion_google_ads_should_queue(array $event, array $settings): bool
    {
        if (function_exists('analytics_is_admin_request') && analytics_is_admin_request()) {
            return false;
        }
        if (empty($settings['enabled']) || empty($settings['google_ads']['enabled']) || empty($settings['google_ads']['queue_enabled']) || empty($settings['google_ads']['capture_click_ids_enabled'])) {
            return false;
        }
        $name = (string)($event['analytics_event'] ?? (function_exists('analytics_event_name') ? analytics_event_name($event) : ''));
        $allowed = array_keys(server_conversion_google_ads_default_mapping());
        return in_array($name, $allowed, true);
    }
}

if (!function_exists('server_conversion_google_ads_enqueue_event')) {
    function server_conversion_google_ads_enqueue_event(array $event, string $source = 'conversion_event'): bool
    {
        $settings = server_conversion_read_settings();
        if (!server_conversion_google_ads_should_queue($event, $settings)) {
            return false;
        }
        $payload = server_conversion_google_ads_prepare_payload($event, $settings, $source);
        if ((string)($payload['status'] ?? '') === 'disabled' || (string)($payload['status'] ?? '') === 'mapping_disabled') {
            return false;
        }

        $queue = server_conversion_google_ads_read_queue();
        foreach ($queue as $row) {
            if ((string)($row['event_id'] ?? '') === (string)$payload['event_id'] && (string)($row['event_name'] ?? '') === (string)$payload['event_name']) {
                return true;
            }
        }

        $row = [
            'id' => 'gacq_' . date('YmdHis') . '_' . substr(hash('sha256', (string)$payload['event_id'] . random_bytes(8)), 0, 12),
            'event_id' => (string)$payload['event_id'],
            'event_name' => (string)$payload['event_name'],
            'status' => (string)$payload['status'],
            'reason' => (string)$payload['reason'],
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'attempts' => 0,
            'payload' => $payload,
        ];
        $queue[] = $row;
        $ok = server_conversion_google_ads_write_queue($queue);
        if ($ok) {
            server_conversion_log([
                'action' => 'google_ads_foundation_queued',
                'platform' => 'google_ads',
                'status' => (string)$payload['status'],
                'event_id' => (string)$payload['event_id'],
                'event_name' => (string)$payload['event_name'],
                'click_id_type' => (string)$payload['click_id_type'],
                'conversion_action_id' => (string)$payload['conversion_action_id'],
                'source' => $source,
            ]);
        }
        return $ok;
    }
}

if (!function_exists('server_conversion_google_ads_queue_summary')) {
    function server_conversion_google_ads_queue_summary(int $limit = 10): array
    {
        $queue = server_conversion_google_ads_read_queue();
        $counts = [
            'total' => count($queue),
            'ready_for_sender' => 0,
            'sent' => 0,
            'validated' => 0,
            'failed' => 0,
            'missing_click_id' => 0,
            'missing_mapping' => 0,
            'mapping_disabled' => 0,
            'ignored' => 0,
            'other' => 0,
        ];
        $clickTypes = ['gclid' => 0, 'gbraid' => 0, 'wbraid' => 0, 'none' => 0];
        foreach ($queue as $row) {
            $status = (string)($row['status'] ?? 'other');
            if (isset($counts[$status])) {
                $counts[$status]++;
            } else {
                $counts['other']++;
            }
            $payload = (array)($row['payload'] ?? []);
            $type = (string)($payload['click_id_type'] ?? '');
            if (isset($clickTypes[$type])) {
                $clickTypes[$type]++;
            } else {
                $clickTypes['none']++;
            }
        }
        return [
            'counts' => $counts,
            'click_types' => $clickTypes,
            'recent' => array_reverse(array_slice($queue, -max(1, $limit))),
            'queue_file_exists' => is_file(server_conversion_google_ads_queue_file()),
            'queue_file' => server_conversion_google_ads_queue_file(),
        ];
    }
}

if (!function_exists('server_conversion_google_ads_mapping_summary')) {
    function server_conversion_google_ads_mapping_summary(?array $settings = null): array
    {
        $settings = $settings ? server_conversion_normalize_settings($settings) : server_conversion_read_settings();
        $mapping = (array)($settings['google_ads']['mapping'] ?? []);
        $enabled = 0;
        $ready = 0;
        $rows = [];
        foreach (server_conversion_google_ads_default_mapping() as $eventName => $defaultRow) {
            $row = (array)($mapping[$eventName] ?? []);
            $isEnabled = !empty($row['enabled']);
            $action = server_conversion_id_clean((string)($row['conversion_action_id'] ?? ''), 160);
            $fallback = server_conversion_id_clean((string)($settings['google_ads']['conversion_action_id'] ?? ''), 160);
            if ($action === '') {
                $action = $fallback;
            }
            if ($isEnabled) {
                $enabled++;
            }
            if ($isEnabled && $action !== '') {
                $ready++;
            }
            $rows[$eventName] = [
                'event_name' => $eventName,
                'label' => server_conversion_clean((string)($row['label'] ?? $defaultRow['label']), 80),
                'enabled' => $isEnabled,
                'conversion_action_id' => $action,
                'uses_fallback_action' => $action !== '' && (string)($row['conversion_action_id'] ?? '') === '',
                'default_value' => (int)($row['default_value'] ?? $defaultRow['default_value'] ?? 0),
                'ready' => $isEnabled && $action !== '',
            ];
        }
        return [
            'enabled_count' => $enabled,
            'ready_count' => $ready,
            'total' => count(server_conversion_google_ads_default_mapping()),
            'rows' => $rows,
        ];
    }
}

if (!function_exists('server_conversion_google_ads_debug_rows')) {
    function server_conversion_google_ads_debug_rows(int $limit = 50): array
    {
        $rows = [];
        foreach (array_reverse(server_conversion_google_ads_read_queue()) as $row) {
            $payload = (array)($row['payload'] ?? []);
            $rows[] = [
                'id' => (string)($row['id'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'event_name' => (string)($row['event_name'] ?? ''),
                'event_id' => (string)($row['event_id'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'reason' => (string)($row['reason'] ?? ''),
                'click_id_type' => (string)($payload['click_id_type'] ?? ''),
                'click_id_mask' => server_conversion_mask_secret((string)($payload['click_id'] ?? '')),
                'conversion_action_id' => (string)($payload['conversion_action_id'] ?? ''),
                'conversion_value' => (int)($payload['conversion_value'] ?? 0),
                'currency' => (string)($payload['currency'] ?? 'IDR'),
                'attempts' => (int)($row['attempts'] ?? 0),
                'http_status' => (int)($row['http_status'] ?? 0),
                'last_response' => server_conversion_clean((string)($row['last_response'] ?? ''), 240),
                'last_error' => server_conversion_clean((string)($row['last_error'] ?? ''), 240),
                'sent_at' => (string)($row['sent_at'] ?? ''),
            ];
            if (count($rows) >= max(1, min(500, $limit))) {
                break;
            }
        }
        return $rows;
    }
}

if (!function_exists('server_conversion_google_ads_clear_old')) {
    function server_conversion_google_ads_clear_old(int $days = 90): int
    {
        $days = max(1, min(3650, $days));
        $cutoff = strtotime('-' . $days . ' days') ?: 0;
        $queue = server_conversion_google_ads_read_queue();
        $kept = [];
        $removed = 0;
        foreach ($queue as $row) {
            $time = strtotime((string)($row['updated_at'] ?? $row['created_at'] ?? '')) ?: 0;
            if ($time > 0 && $time < $cutoff) {
                $removed++;
                continue;
            }
            $kept[] = $row;
        }
        if ($removed > 0) {
            server_conversion_google_ads_write_queue($kept);
            server_conversion_log(['action' => 'google_ads_clear_old_foundation', 'platform' => 'google_ads', 'status' => 'ok', 'removed' => $removed, 'days' => $days]);
        }
        return $removed;
    }
}

if (!function_exists('server_conversion_google_ads_export_csv')) {
    function server_conversion_google_ads_export_csv(): void
    {
        $filename = 'google-ads-conversion-foundation-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'wb');
        if (!$out) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['created_at', 'updated_at', 'status', 'event_name', 'event_id', 'click_id_type', 'click_id_mask', 'conversion_action_id', 'conversion_value', 'currency', 'attempts', 'http_status', 'last_response', 'last_error', 'reason']);
        foreach (server_conversion_google_ads_debug_rows(2000) as $row) {
            fputcsv($out, [
                $row['created_at'],
                $row['updated_at'],
                $row['status'],
                $row['event_name'],
                $row['event_id'],
                $row['click_id_type'],
                $row['click_id_mask'],
                $row['conversion_action_id'],
                $row['conversion_value'],
                $row['currency'],
                $row['attempts'],
                $row['http_status'],
                $row['last_response'],
                $row['last_error'],
                $row['reason'],
            ]);
        }
        fclose($out);
        exit;
    }
}


if (!function_exists('server_conversion_http_post_form')) {
    function server_conversion_http_post_form(string $url, array $fields, array $headers = [], int $timeout = 12): array
    {
        $body = http_build_query($fields);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Accept: application/json';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
                CURLOPT_TIMEOUT => $timeout,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $responseText = is_string($response) ? $response : '';
            return [
                'ok' => $status >= 200 && $status < 300,
                'http_status' => $status,
                'response' => server_conversion_clean($responseText, 900),
                'error' => server_conversion_clean($error, 300),
            ];
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ((array)($http_response_header ?? []) as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$line, $m)) {
                $status = (int)$m[1];
                break;
            }
        }
        $responseText = is_string($response) ? $response : '';
        return [
            'ok' => $status >= 200 && $status < 300,
            'http_status' => $status,
            'response' => server_conversion_clean($responseText, 900),
            'error' => $responseText === '' && $status === 0 ? 'HTTP request failed or blocked by hosting.' : '',
        ];
    }
}

if (!function_exists('server_conversion_google_ads_sender_status')) {
    function server_conversion_google_ads_sender_status(?array $settings = null): array
    {
        $settings = $settings ? server_conversion_normalize_settings($settings) : server_conversion_read_settings();
        $sender = (array)($settings['google_ads']['sender'] ?? []);
        $vault = function_exists('google_ads_vault_status') ? google_ads_vault_status($settings) : ['sender_prereq_ready' => false, 'api_ready' => false];
        $enabled = !empty($settings['google_ads']['enabled']) && !empty($sender['enabled']);
        $ready = $enabled && !empty($vault['sender_prereq_ready']);
        return [
            'enabled' => $enabled,
            'validate_only' => !empty($sender['validate_only']),
            'partial_failure' => !empty($sender['partial_failure']),
            'max_events_per_run' => (int)($sender['max_events_per_run'] ?? 10),
            'last_run_at' => $sender['last_run_at'] ?? null,
            'last_result' => is_array($sender['last_result'] ?? null) ? $sender['last_result'] : [],
            'ready' => $ready,
            'api_ready' => !empty($vault['api_ready']),
            'sender_prereq_ready' => !empty($vault['sender_prereq_ready']),
            'status_label' => !$enabled ? 'Pengiriman Nonaktif' : ($ready ? (!empty($sender['validate_only']) ? 'Mode Tes Siap' : 'Pengiriman Live Siap') : 'Data Koneksi/Pengaturan Belum Lengkap'),
            'message' => !$enabled
                ? 'Pengiriman Google Ads belum aktif. Data tetap disimpan aman sebagai riwayat.'
                : ($ready
                    ? (!empty($sender['validate_only']) ? 'Mode tes siap mengecek data ke Google Ads tanpa mencatat konversi live.' : 'Pengiriman live siap. Pastikan ID konversi dan data klik sudah valid.')
                    : 'Pengiriman data aktif, tetapi beberapa pengaturan Google Ads belum lengkap.'),
        ];
    }
}

if (!function_exists('server_conversion_google_ads_normalize_conversion_action')) {
    function server_conversion_google_ads_normalize_conversion_action(string $action, string $customerId): string
    {
        $action = server_conversion_id_clean($action, 180);
        $customerId = server_conversion_digits_clean($customerId, 20);
        if ($action === '' || $customerId === '') {
            return '';
        }
        if (preg_match('#^customers/[0-9]+/conversionActions/[0-9]+$#', $action)) {
            return $action;
        }
        $digits = server_conversion_digits_clean($action, 30);
        if ($digits !== '') {
            return 'customers/' . $customerId . '/conversionActions/' . $digits;
        }
        return '';
    }
}

if (!function_exists('server_conversion_google_ads_datetime')) {
    function server_conversion_google_ads_datetime(string $value): string
    {
        $ts = strtotime($value) ?: time();
        return date('Y-m-d H:i:sP', $ts);
    }
}

if (!function_exists('server_conversion_google_ads_upload_payload')) {
    function server_conversion_google_ads_upload_payload(array $row, array $settings): array
    {
        $payload = (array)($row['payload'] ?? []);
        $customerId = server_conversion_digits_clean((string)($payload['customer_id'] ?? $settings['google_ads']['customer_id'] ?? ''), 20);
        $conversionAction = server_conversion_google_ads_normalize_conversion_action((string)($payload['conversion_action_id'] ?? ''), $customerId);
        $conversion = [
            'conversionAction' => $conversionAction,
            'conversionDateTime' => server_conversion_google_ads_datetime((string)($payload['conversion_date_time'] ?? $row['created_at'] ?? 'now')),
            'conversionValue' => (float)max(0, (int)($payload['conversion_value'] ?? 0)),
            'currencyCode' => server_conversion_id_clean((string)($payload['currency'] ?? $settings['google_ads']['currency'] ?? 'IDR'), 3) ?: 'IDR',
            'orderId' => server_conversion_clean_event_id((string)($payload['event_id'] ?? $row['event_id'] ?? '')),
        ];
        foreach (['gclid', 'gbraid', 'wbraid'] as $clickKey) {
            $clickValue = server_conversion_clean((string)($payload[$clickKey] ?? ''), 220);
            if ($clickValue !== '') {
                $conversion[$clickKey] = $clickValue;
                break;
            }
        }
        return [
            'customer_id' => $customerId,
            'body' => [
                'conversions' => [$conversion],
                'partialFailure' => !empty($settings['google_ads']['sender']['partial_failure']),
                'validateOnly' => !empty($settings['google_ads']['sender']['validate_only']),
            ],
        ];
    }
}

if (!function_exists('server_conversion_google_ads_access_token')) {
    function server_conversion_google_ads_access_token(array $credentials): array
    {
        if (empty($credentials['oauth_ready'])) {
            return ['ok' => false, 'http_status' => 0, 'access_token' => '', 'response' => '', 'error' => 'Client ID, Client Secret, atau token koneksi belum lengkap.'];
        }
        $result = server_conversion_http_post_form('https://oauth2.googleapis.com/token', [
            'client_id' => (string)$credentials['client_id'],
            'client_secret' => (string)$credentials['client_secret'],
            'refresh_token' => (string)$credentials['refresh_token'],
            'grant_type' => 'refresh_token',
        ], [], 15);
        $decoded = json_decode((string)($result['response'] ?? ''), true);
        $token = is_array($decoded) ? (string)($decoded['access_token'] ?? '') : '';
        if (!empty($result['ok']) && $token !== '') {
            return ['ok' => true, 'http_status' => (int)$result['http_status'], 'access_token' => $token, 'response' => 'Token koneksi berhasil didapatkan.', 'error' => ''];
        }
        $error = is_array($decoded) ? (string)($decoded['error_description'] ?? $decoded['error'] ?? '') : '';
        return ['ok' => false, 'http_status' => (int)($result['http_status'] ?? 0), 'access_token' => '', 'response' => '', 'error' => server_conversion_clean($error ?: (string)($result['error'] ?? $result['response'] ?? 'Token koneksi gagal dibuat.'), 300)];
    }
}

if (!function_exists('server_conversion_google_ads_send_row')) {
    function server_conversion_google_ads_send_row(array $row, array $settings, ?string $accessToken = null): array
    {
        $settings = server_conversion_normalize_settings($settings);
        $senderStatus = server_conversion_google_ads_sender_status($settings);
        if (empty($senderStatus['enabled'])) {
            return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Pengiriman Google Ads belum aktif.'];
        }
        if (empty($senderStatus['sender_prereq_ready'])) {
            return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Data koneksi, Customer ID, atau ID konversi belum lengkap.'];
        }
        $rowStatus = (string)($row['status'] ?? '');
        if (!in_array($rowStatus, ['ready_for_sender', 'failed'], true)) {
            return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Row tidak ready untuk sender: ' . $rowStatus];
        }
        $upload = server_conversion_google_ads_upload_payload($row, $settings);
        $customerId = (string)($upload['customer_id'] ?? '');
        $body = (array)($upload['body'] ?? []);
        $conversion = (array)($body['conversions'][0] ?? []);
        if ($customerId === '' || (string)($conversion['conversionAction'] ?? '') === '') {
            return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Pengaturan akun atau konversi Google Ads belum lengkap.'];
        }
        if (empty($conversion['gclid']) && empty($conversion['gbraid']) && empty($conversion['wbraid'])) {
            return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Tidak ada data klik iklan.'];
        }
        $credentials = function_exists('google_ads_vault_credentials') ? google_ads_vault_credentials() : [];
        if (empty($credentials['developer_ready'])) {
            return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Token koneksi Google Ads belum tersedia.'];
        }
        if ($accessToken === null || $accessToken === '') {
            $tokenResult = server_conversion_google_ads_access_token($credentials);
            if (empty($tokenResult['ok'])) {
                return $tokenResult;
            }
            $accessToken = (string)$tokenResult['access_token'];
        }
        $apiVersion = server_conversion_id_clean((string)($credentials['api_version'] ?? ''), 12) ?: 'v24';
        if (!preg_match('/^v[0-9]{1,3}$/', $apiVersion)) {
            $apiVersion = 'v24';
        }
        $url = 'https://googleads.googleapis.com/' . rawurlencode($apiVersion) . '/customers/' . rawurlencode($customerId) . ':uploadClickConversions';
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'developer-token: ' . (string)$credentials['developer_token'],
        ];
        if (!empty($credentials['login_customer_id'])) {
            $headers[] = 'login-customer-id: ' . (string)$credentials['login_customer_id'];
        }
        $result = server_conversion_http_post_json($url, $headers, $body, 20);
        $decoded = json_decode((string)($result['response'] ?? ''), true);
        $partialError = '';
        if (is_array($decoded) && !empty($decoded['partialFailureError'])) {
            $partial = (array)$decoded['partialFailureError'];
            $partialError = server_conversion_clean((string)($partial['message'] ?? json_encode($partial)), 300);
        }
        if ($partialError !== '') {
            $result['ok'] = false;
            $result['error'] = $partialError;
        }
        $result['platform_event'] = 'UploadClickConversions';
        $result['validate_only'] = !empty($settings['google_ads']['sender']['validate_only']);
        $result['request_summary'] = 'customer=' . $customerId . '; action=' . (string)($conversion['conversionAction'] ?? '') . '; click=' . (!empty($conversion['gclid']) ? 'gclid' : (!empty($conversion['gbraid']) ? 'gbraid' : 'wbraid'));
        return $result;
    }
}

if (!function_exists('server_conversion_process_google_ads_queue')) {
    function server_conversion_process_google_ads_queue(int $limit = 10, bool $failedOnly = false): array
    {
        $settings = server_conversion_read_settings();
        $sender = (array)($settings['google_ads']['sender'] ?? []);
        $senderStatus = server_conversion_google_ads_sender_status($settings);
        $limit = max(1, min(100, $limit ?: (int)($sender['max_events_per_run'] ?? 10)));
        if (empty($senderStatus['enabled'])) {
            return ['processed' => 0, 'sent' => 0, 'validated' => 0, 'failed' => 0, 'skipped' => 0, 'disabled' => true, 'message' => 'Pengiriman Google Ads belum aktif.'];
        }
        if (empty($senderStatus['sender_prereq_ready'])) {
            return ['processed' => 0, 'sent' => 0, 'validated' => 0, 'failed' => 0, 'skipped' => 0, 'disabled' => true, 'message' => 'Data koneksi, Customer ID, atau ID konversi belum lengkap.'];
        }
        $queue = server_conversion_google_ads_read_queue();
        $processed = 0;
        $sent = 0;
        $validated = 0;
        $failed = 0;
        $skipped = 0;
        $accessToken = null;
        $tokenTried = false;
        foreach ($queue as &$row) {
            if ($processed >= $limit) {
                break;
            }
            $status = (string)($row['status'] ?? '');
            if ($failedOnly) {
                if ($status !== 'failed') {
                    continue;
                }
            } elseif ($status !== 'ready_for_sender') {
                continue;
            }
            if (!$tokenTried) {
                $credentials = function_exists('google_ads_vault_credentials') ? google_ads_vault_credentials() : [];
                $tokenResult = server_conversion_google_ads_access_token($credentials);
                $tokenTried = true;
                if (!empty($tokenResult['ok'])) {
                    $accessToken = (string)$tokenResult['access_token'];
                } else {
                    server_conversion_log([
                        'action' => 'google_ads_oauth',
                        'platform' => 'google_ads',
                        'status' => 'failed',
                        'http_status' => (int)($tokenResult['http_status'] ?? 0),
                        'error' => server_conversion_clean((string)($tokenResult['error'] ?? 'Koneksi gagal'), 300),
                    ]);
                }
            }
            $processed++;
            $row['attempts'] = (int)($row['attempts'] ?? 0) + 1;
            $row['updated_at'] = date('c');
            $result = server_conversion_google_ads_send_row($row, $settings, $accessToken);
            $ok = !empty($result['ok']);
            $validateOnly = !empty($result['validate_only']) || !empty($settings['google_ads']['sender']['validate_only']);
            if ($ok && $validateOnly) {
                $row['status'] = 'validated';
                $validated++;
            } elseif ($ok) {
                $row['status'] = 'sent';
                $row['sent_at'] = date('c');
                $sent++;
            } else {
                $row['status'] = 'failed';
                $failed++;
            }
            $row['reason'] = $ok
                ? ($validateOnly ? 'Mode tes Google Ads sukses. Konversi belum dicatat live.' : 'Konversi Google Ads berhasil dikirim live.')
                : server_conversion_clean((string)($result['error'] ?: $result['response'] ?: 'Google Ads API error.'), 300);
            $row['http_status'] = (int)($result['http_status'] ?? 0);
            $row['last_response'] = server_conversion_clean((string)($result['response'] ?? ''), 700);
            $row['last_error'] = $ok ? '' : server_conversion_clean((string)($result['error'] ?? ''), 300);
            $row['last_request_summary'] = server_conversion_clean((string)($result['request_summary'] ?? ''), 260);
            server_conversion_log([
                'action' => 'google_ads_send',
                'platform' => 'google_ads',
                'event_name' => (string)($row['event_name'] ?? ''),
                'event_id' => (string)($row['event_id'] ?? ''),
                'status' => $ok ? ($validateOnly ? 'validated' : 'sent') : 'failed',
                'http_status' => (int)($result['http_status'] ?? 0),
                'response' => server_conversion_clean((string)($result['response'] ?? ''), 500),
                'error' => server_conversion_clean((string)($result['error'] ?? ''), 300),
                'validate_only' => $validateOnly,
            ]);
        }
        unset($row);
        server_conversion_google_ads_write_queue($queue);
        $settings['google_ads']['sender']['last_run_at'] = date('c');
        $settings['google_ads']['sender']['last_result'] = [
            'processed' => $processed,
            'sent' => $sent,
            'validated' => $validated,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
        server_conversion_write_settings($settings);
        return ['processed' => $processed, 'sent' => $sent, 'validated' => $validated, 'failed' => $failed, 'skipped' => $skipped];
    }
}

if (!function_exists('server_conversion_google_ads_mark_ignored')) {
    function server_conversion_google_ads_mark_ignored(array $ids = []): int
    {
        $ids = array_values(array_filter(array_map('strval', $ids), static fn(string $id): bool => $id !== ''));
        $allFailedWhenNoIds = count($ids) === 0;
        $idMap = array_flip($ids);
        $queue = server_conversion_google_ads_read_queue();
        $count = 0;
        foreach ($queue as &$row) {
            $rowId = (string)($row['id'] ?? '');
            $rowStatus = (string)($row['status'] ?? '');
            if ($allFailedWhenNoIds) {
                if ($rowStatus !== 'failed') {
                    continue;
                }
            } elseif (!isset($idMap[$rowId])) {
                continue;
            }
            if (in_array($rowStatus, ['sent', 'validated'], true)) {
                continue;
            }
            $row['status'] = 'ignored';
            $row['updated_at'] = date('c');
            $row['reason'] = 'Ditandai ignored dari admin.';
            $count++;
        }
        unset($row);
        if ($count > 0) {
            server_conversion_google_ads_write_queue($queue);
            server_conversion_log(['action' => 'google_ads_mark_ignored', 'platform' => 'google_ads', 'status' => 'ignored', 'count' => $count]);
        }
        return $count;
    }
}

if (!function_exists('server_conversion_configured_platforms')) {
    function server_conversion_configured_platforms(?array $settings = null): array
    {
        $settings = $settings ? server_conversion_normalize_settings($settings) : server_conversion_read_settings();
        if (empty($settings['enabled'])) {
            return [];
        }

        $platforms = [];
        if (!empty($settings['meta']['enabled']) && (string)$settings['meta']['dataset_id'] !== '' && (string)$settings['meta']['access_token'] !== '') {
            $platforms['meta'] = 'Meta CAPI';
        }
        if (!empty($settings['tiktok']['enabled']) && (string)$settings['tiktok']['pixel_id'] !== '' && (string)$settings['tiktok']['access_token'] !== '') {
            $platforms['tiktok'] = 'TikTok Events API';
        }
        // Google Ads uses a separate local queue and stays inactive until credentials are available.
        return $platforms;
    }
}

if (!function_exists('server_conversion_event_id')) {
    function server_conversion_event_id(array $event = []): string
    {
        $existing = server_conversion_clean_event_id((string)($event['event_id'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $seed = json_encode([
            $event['time'] ?? microtime(true),
            $event['analytics_event'] ?? '',
            $event['source'] ?? '',
            $event['channel'] ?? '',
            $event['page_path'] ?? '',
            $event['target_url'] ?? '',
            bin2hex(random_bytes(8)),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: bin2hex(random_bytes(16));

        return 'evt_' . date('YmdHis') . '_' . substr(hash('sha256', $seed), 0, 18);
    }
}

if (!function_exists('server_conversion_attach_event_id')) {
    function server_conversion_attach_event_id(array $event): array
    {
        $event['event_id'] = server_conversion_event_id($event);
        return $event;
    }
}

if (!function_exists('server_conversion_consent_granted')) {
    function server_conversion_consent_granted(array $event): bool
    {
        foreach (['contact_consent', 'marketing_consent', 'consent', 'allow_marketing'] as $key) {
            $value = $event[$key] ?? null;
            if ($value === true || $value === 1 || $value === '1' || strtolower((string)$value) === 'yes' || strtolower((string)$value) === 'true') {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('server_conversion_hash_value')) {
    function server_conversion_hash_value(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        return hash('sha256', $value);
    }
}

if (!function_exists('server_conversion_safe_payload')) {
    function server_conversion_safe_payload(array $event, array $settings): array
    {
        $event = server_conversion_attach_event_id($event);
        $eventName = (string)($event['analytics_event'] ?? (function_exists('analytics_event_name') ? analytics_event_name($event) : 'custom_event'));
        $eventGroup = (string)($event['_event_group'] ?? (function_exists('conversion_event_group') ? conversion_event_group($event) : 'interaction'));
        $timestamp = function_exists('conversion_event_timestamp') ? conversion_event_timestamp($event) : (strtotime((string)($event['time'] ?? '')) ?: time());
        $timestamp = $timestamp > 0 ? $timestamp : time();
        $pagePath = function_exists('analytics_safe_path') ? analytics_safe_path((string)($event['page_path'] ?? current_url())) : server_conversion_clean((string)($event['page_path'] ?? '/'), 180);
        $targetPath = function_exists('analytics_safe_path') ? analytics_safe_path((string)($event['target_url'] ?? '')) : server_conversion_clean((string)($event['target_url'] ?? ''), 180);

        $payload = [
            'event_id' => (string)$event['event_id'],
            'event_name' => server_conversion_clean($eventName, 80),
            'event_time' => $timestamp,
            'event_group' => server_conversion_clean($eventGroup, 60),
            'event_kind' => server_conversion_clean((string)($event['_event_kind'] ?? ''), 40),
            'action_source' => 'website',
            'page_path' => $pagePath,
            'target_path' => $targetPath,
            'source' => server_conversion_clean((string)($event['source'] ?? ''), 80),
            'type' => server_conversion_clean((string)($event['type'] ?? ''), 60),
            'channel' => server_conversion_clean((string)($event['channel'] ?? ''), 60),
            'category' => server_conversion_clean((string)($event['category'] ?? ''), 80),
            'location' => server_conversion_clean((string)($event['location'] ?? ''), 80),
            'intent' => server_conversion_clean((string)($event['intent'] ?? ''), 80),
            'label' => server_conversion_clean((string)($event['label'] ?? ''), 120),
            'marketing_channel' => server_conversion_clean((string)($event['marketing_channel'] ?? ''), 50),
            'utm_source' => server_conversion_clean((string)($event['utm_source'] ?? ''), 80),
            'utm_medium' => server_conversion_clean((string)($event['utm_medium'] ?? ''), 80),
            'utm_campaign' => server_conversion_clean((string)($event['utm_campaign'] ?? ''), 120),
            'click_id' => server_conversion_clean((string)($event['click_id'] ?? ''), 220),
            'click_id_type' => server_conversion_clean((string)($event['click_id_type'] ?? ''), 30),
            'gclid' => server_conversion_clean((string)($event['gclid'] ?? ''), 220),
            'gbraid' => server_conversion_clean((string)($event['gbraid'] ?? ''), 220),
            'wbraid' => server_conversion_clean((string)($event['wbraid'] ?? ''), 220),
            'google_ads_click_id' => server_conversion_clean((string)($event['google_ads_click_id'] ?? ''), 220),
            'google_ads_click_id_type' => server_conversion_clean((string)($event['google_ads_click_id_type'] ?? ''), 30),
            'currency' => 'IDR',
            'pii_safe' => true,
        ];

        $value = (int)($event['value'] ?? $event['price'] ?? $event['amount'] ?? 0);
        if ($value > 0) {
            $payload['value'] = $value;
        }

        $advanced = [];
        if (!empty($settings['advanced_matching_enabled']) && server_conversion_consent_granted($event)) {
            $emailHash = server_conversion_hash_value((string)($event['email'] ?? $event['customer_email'] ?? ''));
            $phone = preg_replace('/\D+/', '', (string)($event['phone'] ?? $event['customer_phone'] ?? '')) ?: '';
            $phoneHash = server_conversion_hash_value($phone);
            if ($emailHash !== '') {
                $advanced['email_sha256'] = $emailHash;
            }
            if ($phoneHash !== '') {
                $advanced['phone_sha256'] = $phoneHash;
            }
        }
        if ($advanced) {
            $payload['advanced_matching'] = $advanced;
        }

        return $payload;
    }
}

if (!function_exists('server_conversion_should_queue')) {
    function server_conversion_should_queue(array $event, array $settings): bool
    {
        if (function_exists('analytics_is_admin_request') && analytics_is_admin_request()) {
            return false;
        }

        if (empty($settings['enabled'])) {
            return false;
        }

        $name = (string)($event['analytics_event'] ?? (function_exists('analytics_event_name') ? analytics_event_name($event) : ''));
        $highIntentNames = ['contact_whatsapp', 'begin_checkout', 'submit_inquiry', 'upload_payment_proof', 'order_success'];
        if (in_array($name, $highIntentNames, true)) {
            return true;
        }

        if (empty($settings['queue_high_intent_only'])) {
            return true;
        }

        return false;
    }
}

if (!function_exists('server_conversion_read_queue')) {
    function server_conversion_read_queue(): array
    {
        $file = server_conversion_queue_file();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }
}

if (!function_exists('server_conversion_write_queue')) {
    function server_conversion_write_queue(array $queue): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        $queue = array_values(array_slice($queue, -2000));
        return @file_put_contents(
            server_conversion_queue_file(),
            json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        ) !== false;
    }
}

if (!function_exists('server_conversion_log')) {
    function server_conversion_log(array $entry): bool
    {
        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }
        $entry['time'] = (string)($entry['time'] ?? date('c'));
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return @file_put_contents(server_conversion_log_file(), $line, FILE_APPEND | LOCK_EX) !== false;
    }
}

if (!function_exists('server_conversion_enqueue_event')) {
    function server_conversion_enqueue_event(array $event, string $source = 'conversion_event'): bool
    {
        $settings = server_conversion_read_settings();
        $googleAdsQueued = function_exists('server_conversion_google_ads_enqueue_event') ? server_conversion_google_ads_enqueue_event($event, $source) : false;
        $platforms = server_conversion_configured_platforms($settings);
        if (!$platforms || !server_conversion_should_queue($event, $settings)) {
            return $googleAdsQueued;
        }

        $event = server_conversion_attach_event_id($event);
        $payload = server_conversion_safe_payload($event, $settings);
        $queue = server_conversion_read_queue();

        foreach ($queue as $row) {
            if ((string)($row['event_id'] ?? '') === (string)$payload['event_id'] && (string)($row['event_name'] ?? '') === (string)$payload['event_name']) {
                return false;
            }
        }

        $platformRows = [];
        foreach ($platforms as $key => $label) {
            $platformRows[$key] = [
                'label' => $label,
                'status' => 'pending',
                'last_error' => '',
                'sent_at' => null,
            ];
        }

        $row = [
            'id' => 'scq_' . date('YmdHis') . '_' . substr(hash('sha256', (string)$payload['event_id'] . random_bytes(8)), 0, 12),
            'event_id' => (string)$payload['event_id'],
            'event_name' => (string)$payload['event_name'],
            'event_group' => (string)$payload['event_group'],
            'status' => 'pending',
            'attempts' => 0,
            'source' => server_conversion_clean($source, 80),
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'platforms' => $platformRows,
            'payload' => $payload,
        ];

        $queue[] = $row;
        $ok = server_conversion_write_queue($queue);
        if ($ok) {
            server_conversion_log([
                'action' => 'queued',
                'status' => 'pending',
                'event_id' => (string)$payload['event_id'],
                'event_name' => (string)$payload['event_name'],
                'platforms' => array_keys($platforms),
                'source' => $source,
            ]);
        }
        return $ok || $googleAdsQueued;
    }
}

if (!function_exists('server_conversion_retry_failed')) {
    function server_conversion_retry_failed(): int
    {
        $queue = server_conversion_read_queue();
        $count = 0;
        foreach ($queue as &$row) {
            if ((string)($row['status'] ?? '') === 'failed') {
                $row['status'] = 'pending';
                $row['updated_at'] = date('c');
                $row['retry_requested_at'] = date('c');
                $count++;
                foreach ((array)($row['platforms'] ?? []) as $key => $platform) {
                    if ((string)($platform['status'] ?? '') === 'failed') {
                        $row['platforms'][$key]['status'] = 'pending';
                    }
                }
            }
        }
        unset($row);
        if ($count > 0) {
            server_conversion_write_queue($queue);
            server_conversion_log(['action' => 'retry_failed', 'status' => 'pending', 'count' => $count]);
        }
        return $count;
    }
}

if (!function_exists('server_conversion_prune_queue')) {
    function server_conversion_prune_queue(int $keep = 500): int
    {
        $queue = server_conversion_read_queue();
        $original = count($queue);
        $keep = max(50, min(2000, $keep));
        if ($original <= $keep) {
            return 0;
        }
        $queue = array_slice($queue, -$keep);
        server_conversion_write_queue($queue);
        $removed = $original - count($queue);
        server_conversion_log(['action' => 'prune_queue', 'status' => 'ok', 'removed' => $removed, 'keep' => $keep]);
        return $removed;
    }
}

if (!function_exists('server_conversion_enqueue_test_event')) {
    function server_conversion_enqueue_test_event(): bool
    {
        return server_conversion_enqueue_event([
            'time' => date('c'),
            'source' => 'admin-analytics',
            'type' => 'server_conversion_test',
            'channel' => 'test',
            'category' => 'debug',
            'location' => '',
            'intent' => 'test-queue',
            'label' => 'Server-Side Conversion Queue Test',
            'page_path' => '/admin/analytics',
            'target_url' => '/admin/analytics',
            'analytics_event' => 'submit_inquiry',
            '_event_group' => 'inquiry',
            '_event_kind' => 'high_intent',
            'event_id' => server_conversion_event_id(['analytics_event' => 'submit_inquiry', 'source' => 'admin-test']),
        ], 'admin_test');
    }
}

if (!function_exists('server_conversion_queue_summary')) {
    function server_conversion_queue_summary(int $limit = 12): array
    {
        $settings = server_conversion_read_settings();
        $queue = server_conversion_read_queue();
        $counts = ['total' => count($queue), 'pending' => 0, 'sent' => 0, 'failed' => 0, 'ignored' => 0, 'old_sent' => 0];
        $oldSentCutoff = strtotime('-30 days') ?: 0;
        foreach ($queue as $row) {
            $status = (string)($row['status'] ?? 'pending');
            if (!isset($counts[$status])) {
                $counts[$status] = 0;
            }
            $counts[$status]++;
            if ($status === 'sent') {
                $rowTime = strtotime((string)($row['updated_at'] ?? $row['created_at'] ?? '')) ?: 0;
                if ($rowTime > 0 && $rowTime < $oldSentCutoff) {
                    $counts['old_sent']++;
                }
            }
        }

        $recent = array_reverse(array_slice($queue, -max(1, $limit)));
        return [
            'settings' => $settings,
            'configured_platforms' => server_conversion_configured_platforms($settings),
            'counts' => $counts,
            'recent' => $recent,
            'queue_file_exists' => is_file(server_conversion_queue_file()),
            'queue_file' => server_conversion_queue_file(),
        ];
    }
}

if (!function_exists('server_conversion_status_summary')) {
    function server_conversion_status_summary(?array $settings = null): array
    {
        $settings = $settings ? server_conversion_normalize_settings($settings) : server_conversion_read_settings();
        $queue = server_conversion_queue_summary(5);
        $googleAdsQueue = function_exists('server_conversion_google_ads_queue_summary') ? server_conversion_google_ads_queue_summary(5) : ['counts' => [], 'click_types' => []];
        $googleAdsMapping = function_exists('server_conversion_google_ads_mapping_summary') ? server_conversion_google_ads_mapping_summary($settings) : ['ready_count' => 0, 'enabled_count' => 0, 'total' => 0, 'rows' => []];
        $googleAdsConfigured = (string)$settings['google_ads']['customer_id'] !== '' && (string)$settings['google_ads']['conversion_action_id'] !== '';
        $googleAdsMappingReady = (int)($googleAdsMapping['ready_count'] ?? 0) > 0;
        $googleAdsClickQueueReady = (int)($googleAdsQueue['counts']['ready_for_sender'] ?? 0);
        $googleAdsVault = function_exists('google_ads_vault_status') ? google_ads_vault_status($settings) : [
            'enabled' => false,
            'developer_token_set' => false,
            'oauth_ready' => false,
            'api_ready' => false,
            'sender_prereq_ready' => false,
            'status_label' => 'Vault Unavailable',
            'message' => 'Google Ads penyimpanan data koneksi belum tersedia.',
        ];
        $googleAdsApiReady = !empty($googleAdsVault['api_ready']);
        $googleAdsSenderPrereqReady = !empty($googleAdsVault['sender_prereq_ready']);
        $googleAdsSender = function_exists('server_conversion_google_ads_sender_status') ? server_conversion_google_ads_sender_status($settings) : ['enabled' => false, 'ready' => false, 'validate_only' => true, 'status_label' => 'Pengiriman Data Unavailable'];
        return [
            'enabled' => !empty($settings['enabled']),
            'test_mode' => !empty($settings['test_mode']),
            'sending_mode' => (string)($settings['sending_mode'] ?? 'manual'),
            'max_events_per_run' => (int)($settings['max_events_per_run'] ?? 20),
            'advanced_matching_enabled' => !empty($settings['advanced_matching_enabled']),
            'cron' => [
                'enabled' => !empty($settings['cron']['enabled']),
                'token_set' => (string)($settings['cron']['token'] ?? '') !== '',
                'token_mask' => server_conversion_mask_secret((string)($settings['cron']['token'] ?? '')),
                'max_events_per_run' => (int)($settings['cron']['max_events_per_run'] ?? 20),
                'retry_failed' => !empty($settings['cron']['retry_failed']),
                'last_run_at' => $settings['cron']['last_run_at'] ?? null,
                'last_result' => is_array($settings['cron']['last_result'] ?? null) ? $settings['cron']['last_result'] : [],
            ],
            'platforms' => [
                'meta' => [
                    'enabled' => !empty($settings['meta']['enabled']),
                    'configured' => (string)$settings['meta']['dataset_id'] !== '' && (string)$settings['meta']['access_token'] !== '',
                    'dataset_id' => (string)$settings['meta']['dataset_id'],
                    'token_mask' => server_conversion_mask_secret((string)$settings['meta']['access_token']),
                ],
                'tiktok' => [
                    'enabled' => !empty($settings['tiktok']['enabled']),
                    'configured' => (string)$settings['tiktok']['pixel_id'] !== '' && (string)$settings['tiktok']['access_token'] !== '',
                    'pixel_id' => (string)$settings['tiktok']['pixel_id'],
                    'token_mask' => server_conversion_mask_secret((string)$settings['tiktok']['access_token']),
                ],
                'google_ads' => [
                    'enabled' => !empty($settings['google_ads']['enabled']),
                    'configured' => $googleAdsConfigured,
                    'capture_click_ids_enabled' => !empty($settings['google_ads']['capture_click_ids_enabled']),
                    'queue_enabled' => !empty($settings['google_ads']['queue_enabled']),
                    'mapping_ready' => $googleAdsMappingReady,
                    'mapping' => $googleAdsMapping,
                    'queue' => $googleAdsQueue,
                    'foundation_only' => false,
                    'sender_beta_available' => true,
                    'sender_ready' => !empty($googleAdsSender['ready']),
                    'sender' => $googleAdsSender,
                    'sender_prereq_ready' => $googleAdsSenderPrereqReady,
                    'oauth_required' => !$googleAdsApiReady,
                    'credential_vault' => $googleAdsVault,
                    'status_label' => empty($settings['google_ads']['enabled'])
                        ? 'Disabled'
                        : (!empty($googleAdsSender['ready'])
                            ? (string)($googleAdsSender['status_label'] ?? 'Pengiriman Data Ready')
                            : ($googleAdsSenderPrereqReady
                                ? 'Credential Ready / Enable Pengiriman Data'
                                : (($googleAdsConfigured && $googleAdsMappingReady)
                                    ? 'Capture + Mapping Ready / Credential Needed'
                                    : ($googleAdsConfigured
                                        ? 'Customer Ready / Mapping Perlu Dicek'
                                        : 'Butuh pengaturan akun dan konversi')))),
                    'message' => empty($settings['google_ads']['enabled'])
                        ? 'Tracking Google Ads belum aktif.'
                        : (!empty($googleAdsSender['ready'])
                            ? (string)($googleAdsSender['message'] ?? 'Google Ads Pengiriman Data siap.')
                            : ($googleAdsSenderPrereqReady
                                ? 'Perekaman klik, aturan tracking, antrean data, dan koneksi Google Ads sudah siap. Aktifkan pengiriman jika ingin mulai mode tes atau live.'
                                : (($googleAdsConfigured && $googleAdsMappingReady)
                                ? 'Capture, queue, dan mapping Google Ads siap. Lengkapi Penyimpanan Data Koneksi agar siap lanjut sender API.'
                                : ($googleAdsConfigured
                                    ? 'Customer ID sudah ada, tetapi mapping event perlu dicek agar setiap conversion memakai conversion action yang tepat.'
                                    : 'Lengkapi pengaturan akun dan konversi. Sistem sudah menyiapkan perekaman klik iklan, tetapi pengiriman belum aktif.')))),
                    'customer_id' => (string)$settings['google_ads']['customer_id'],
                    'conversion_action_id' => (string)$settings['google_ads']['conversion_action_id'],
                    'currency' => (string)($settings['google_ads']['currency'] ?? 'IDR'),
                    'ready_for_sender_count' => $googleAdsClickQueueReady,
                ],
            ],
            'queue_counts' => $queue['counts'] ?? [],
        ];
    }
}



if (!function_exists('server_conversion_ux_sync_summary')) {
    function server_conversion_ux_sync_summary(?array $analytics = null, ?array $settings = null): array
    {
        $analytics = $analytics ? (function_exists('analytics_normalize_settings') ? analytics_normalize_settings($analytics) : $analytics) : (function_exists('analytics_read_settings') ? analytics_read_settings() : []);
        $settings = $settings ? server_conversion_normalize_settings($settings) : server_conversion_read_settings();

        $browserMetaId = function_exists('analytics_digits_clean')
            ? analytics_digits_clean((string)($analytics['pixels']['meta']['pixel_id'] ?? ''), 40)
            : server_conversion_digits_clean((string)($analytics['pixels']['meta']['pixel_id'] ?? ''), 40);
        $browserTikTokId = function_exists('analytics_id_clean')
            ? analytics_id_clean((string)($analytics['pixels']['tiktok']['pixel_id'] ?? ''), 50)
            : server_conversion_id_clean((string)($analytics['pixels']['tiktok']['pixel_id'] ?? ''), 50);
        $serverMetaId = server_conversion_digits_clean((string)($settings['meta']['dataset_id'] ?? ''), 40);
        $serverTikTokId = server_conversion_id_clean((string)($settings['tiktok']['pixel_id'] ?? ''), 50);

        $metaBrowserReady = !empty($analytics['pixels']['meta']['enabled']) && $browserMetaId !== '';
        $tiktokBrowserReady = !empty($analytics['pixels']['tiktok']['enabled']) && $browserTikTokId !== '';
        $metaServerReady = !empty($settings['enabled']) && !empty($settings['meta']['enabled']) && $serverMetaId !== '' && (string)($settings['meta']['access_token'] ?? '') !== '';
        $tiktokServerReady = !empty($settings['enabled']) && !empty($settings['tiktok']['enabled']) && $serverTikTokId !== '' && (string)($settings['tiktok']['access_token'] ?? '') !== '';

        $metaMismatch = $browserMetaId !== '' && $serverMetaId !== '' && $browserMetaId !== $serverMetaId;
        $tiktokMismatch = $browserTikTokId !== '' && $serverTikTokId !== '' && strcasecmp($browserTikTokId, $serverTikTokId) !== 0;

        $platforms = [
            'meta' => [
                'label' => 'Meta',
                'browser_id' => $browserMetaId,
                'server_id' => $serverMetaId,
                'use_browser_id' => !empty($settings['sync']['meta_use_browser_pixel_id']),
                'browser_ready' => $metaBrowserReady,
                'server_ready' => $metaServerReady,
                'hybrid_ready' => $metaBrowserReady && $metaServerReady && !$metaMismatch,
                'mismatch' => $metaMismatch,
                'message' => $metaMismatch
                    ? 'Meta Pixel ID browser dan Meta Dataset ID server berbeda. Untuk dedup hybrid, samakan ID.'
                    : (($metaBrowserReady && $metaServerReady) ? 'Siap hybrid dedup: browser pixel + server CAPI memakai ID yang sama.' : (($metaServerReady) ? 'Server CAPI siap. Browser pixel direct belum aktif atau diatur via GTM.' : (($metaBrowserReady) ? 'Browser Meta Pixel siap. Server CAPI perlu token + enable jika ingin hybrid.' : 'Belum dikonfigurasi.'))),
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'browser_id' => $browserTikTokId,
                'server_id' => $serverTikTokId,
                'use_browser_id' => !empty($settings['sync']['tiktok_use_browser_pixel_id']),
                'browser_ready' => $tiktokBrowserReady,
                'server_ready' => $tiktokServerReady,
                'hybrid_ready' => $tiktokBrowserReady && $tiktokServerReady && !$tiktokMismatch,
                'mismatch' => $tiktokMismatch,
                'message' => $tiktokMismatch
                    ? 'TikTok Pixel ID browser dan TikTok Pixel ID server berbeda. Samakan ID agar tracking tidak membingungkan.'
                    : (($tiktokBrowserReady && $tiktokServerReady) ? 'Siap hybrid dedup: browser pixel + server Events API memakai ID yang sama.' : (($tiktokServerReady) ? 'Server TikTok Events API siap. Browser pixel direct belum aktif atau diatur via GTM.' : (($tiktokBrowserReady) ? 'Browser TikTok Pixel siap. Server Events API perlu token + enable jika ingin hybrid.' : 'Belum dikonfigurasi.'))),
            ],
        ];

        $warnings = [];
        foreach ($platforms as $platform => $row) {
            if (!empty($row['mismatch'])) {
                $warnings[] = (string)$row['message'];
            }
        }

        return [
            'platforms' => $platforms,
            'warning_count' => count($warnings),
            'warnings' => $warnings,
            'hybrid_ready_count' => (int)$platforms['meta']['hybrid_ready'] + (int)$platforms['tiktok']['hybrid_ready'],
        ];
    }
}

if (!function_exists('server_conversion_recent_logs')) {
    function server_conversion_recent_logs(int $limit = 20): array
    {
        $rows = [];
        foreach (server_conversion_log_files(365) as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $row = json_decode((string)$lines[$i], true);
                if (is_array($row)) {
                    unset($row['access_token'], $row['token'], $row['request_url']);
                    $rows[] = $row;
                }
                if (count($rows) >= $limit) {
                    break 2;
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('server_conversion_absolute_url')) {
    function server_conversion_absolute_url(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return function_exists('url') ? url('') : (defined('SITE_URL') ? rtrim(SITE_URL, '/') . '/' : '/');
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $path = '/' . ltrim($path, '/');
        return function_exists('url') ? url(ltrim($path, '/')) : (defined('SITE_URL') ? rtrim(SITE_URL, '/') . $path : $path);
    }
}

if (!function_exists('server_conversion_platform_event_name')) {
    function server_conversion_platform_event_name(string $platform, string $eventName): string
    {
        $eventName = trim($eventName);
        $map = [
            'meta' => [
                'PageView' => 'PageView',
                'page_view' => 'PageView',
                'view_page' => 'PageView',
                'ViewContent' => 'ViewContent',
                'view_item' => 'ViewContent',
                'select_item' => 'ViewContent',
                'contact_whatsapp' => 'Contact',
                'Contact' => 'Contact',
                'begin_checkout' => 'InitiateCheckout',
                'InitiateCheckout' => 'InitiateCheckout',
                'submit_inquiry' => 'Lead',
                'Lead' => 'Lead',
                'upload_payment_proof' => 'Purchase',
                'order_success' => 'Purchase',
                'OrderSuccess' => 'Purchase',
                'Purchase' => 'Purchase',
                'view_invoice' => 'PageView',
                'check_order_status' => 'PageView',
            ],
            'tiktok' => [
                'PageView' => 'Pageview',
                'page_view' => 'Pageview',
                'view_page' => 'Pageview',
                'ViewContent' => 'ViewContent',
                'view_item' => 'ViewContent',
                'select_item' => 'ViewContent',
                'contact_whatsapp' => 'Contact',
                'Contact' => 'Contact',
                'begin_checkout' => 'InitiateCheckout',
                'InitiateCheckout' => 'InitiateCheckout',
                'submit_inquiry' => 'SubmitForm',
                'Lead' => 'SubmitForm',
                'upload_payment_proof' => 'CompletePayment',
                'order_success' => 'CompletePayment',
                'OrderSuccess' => 'CompletePayment',
                'Purchase' => 'CompletePayment',
                'view_invoice' => 'Pageview',
                'check_order_status' => 'Pageview',
            ],
        ];
        return (string)($map[$platform][$eventName] ?? ($platform === 'tiktok' ? 'ViewContent' : 'ViewContent'));
    }
}

if (!function_exists('server_conversion_custom_data')) {
    function server_conversion_custom_data(array $payload): array
    {
        $custom = [
            'currency' => (string)($payload['currency'] ?? 'IDR'),
            'content_name' => server_conversion_clean((string)($payload['label'] ?? $payload['event_name'] ?? ''), 120),
            'content_category' => server_conversion_clean((string)($payload['category'] ?? ''), 80),
            'content_type' => server_conversion_clean((string)($payload['type'] ?? ''), 60),
            'marketing_channel' => server_conversion_clean((string)($payload['marketing_channel'] ?? ''), 50),
            'utm_source' => server_conversion_clean((string)($payload['utm_source'] ?? ''), 80),
            'utm_medium' => server_conversion_clean((string)($payload['utm_medium'] ?? ''), 80),
            'utm_campaign' => server_conversion_clean((string)($payload['utm_campaign'] ?? ''), 120),
            'source' => server_conversion_clean((string)($payload['source'] ?? ''), 80),
            'page_path' => server_conversion_clean((string)($payload['page_path'] ?? ''), 180),
        ];
        if (!empty($payload['value'])) {
            $custom['value'] = (float)$payload['value'];
        }
        return array_filter($custom, static fn($value): bool => $value !== '' && $value !== null);
    }
}

if (!function_exists('server_conversion_meta_payload')) {
    function server_conversion_meta_payload(array $row, array $settings): array
    {
        $payload = (array)($row['payload'] ?? []);
        $userData = [];
        $advanced = (array)($payload['advanced_matching'] ?? []);
        if (!empty($advanced['email_sha256'])) {
            $userData['em'] = [(string)$advanced['email_sha256']];
        }
        if (!empty($advanced['phone_sha256'])) {
            $userData['ph'] = [(string)$advanced['phone_sha256']];
        }

        $event = [
            'event_name' => server_conversion_platform_event_name('meta', (string)($payload['event_name'] ?? 'ViewContent')),
            'event_time' => (int)($payload['event_time'] ?? time()),
            'event_id' => (string)($payload['event_id'] ?? $row['event_id'] ?? ''),
            'action_source' => 'website',
            'event_source_url' => server_conversion_absolute_url((string)($payload['page_path'] ?? '/')),
            'user_data' => $userData,
            'custom_data' => server_conversion_custom_data($payload),
        ];
        $body = ['data' => [$event]];
        if (!empty($settings['test_mode']) && !empty($settings['meta']['test_event_code'])) {
            $body['test_event_code'] = (string)$settings['meta']['test_event_code'];
        }
        return $body;
    }
}

if (!function_exists('server_conversion_tiktok_payload')) {
    function server_conversion_tiktok_payload(array $row, array $settings): array
    {
        $payload = (array)($row['payload'] ?? []);
        $advanced = (array)($payload['advanced_matching'] ?? []);
        $user = [];
        if (!empty($advanced['email_sha256'])) {
            $user['email'] = (string)$advanced['email_sha256'];
        }
        if (!empty($advanced['phone_sha256'])) {
            $user['phone'] = (string)$advanced['phone_sha256'];
        }
        if (!empty($payload['click_id'])) {
            $user['ttclid'] = server_conversion_clean((string)$payload['click_id'], 160);
        }

        $properties = server_conversion_custom_data($payload);
        if (!empty($properties['value'])) {
            $properties['value'] = (float)$properties['value'];
        }

        $event = [
            'event' => server_conversion_platform_event_name('tiktok', (string)($payload['event_name'] ?? 'ViewContent')),
            'event_time' => (int)($payload['event_time'] ?? time()),
            'event_id' => (string)($payload['event_id'] ?? $row['event_id'] ?? ''),
            'user' => $user,
            'page' => [
                'url' => server_conversion_absolute_url((string)($payload['page_path'] ?? '/')),
            ],
            'properties' => $properties,
        ];
        $body = [
            'event_source' => 'web',
            'event_source_id' => (string)($settings['tiktok']['pixel_id'] ?? ''),
            'data' => [$event],
        ];
        if (!empty($settings['test_mode']) && !empty($settings['tiktok']['test_event_code'])) {
            $body['test_event_code'] = (string)$settings['tiktok']['test_event_code'];
        }
        return $body;
    }
}

if (!function_exists('server_conversion_http_post_json')) {
    function server_conversion_http_post_json(string $url, array $headers, array $payload, int $timeout = 12): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Payload JSON encode failed.'];
        }

        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
                CURLOPT_TIMEOUT => $timeout,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $responseText = is_string($response) ? $response : '';
            return [
                'ok' => $status >= 200 && $status < 300,
                'http_status' => $status,
                'response' => server_conversion_clean($responseText, 700),
                'error' => server_conversion_clean($error, 300),
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ((array)($http_response_header ?? []) as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string)$line, $m)) {
                $status = (int)$m[1];
                break;
            }
        }
        $responseText = is_string($response) ? $response : '';
        return [
            'ok' => $status >= 200 && $status < 300,
            'http_status' => $status,
            'response' => server_conversion_clean($responseText, 700),
            'error' => $responseText === '' && $status === 0 ? 'HTTP request failed or blocked by hosting.' : '',
        ];
    }
}

if (!function_exists('server_conversion_send_platform')) {
    function server_conversion_send_platform(string $platform, array $row, array $settings): array
    {
        $platform = strtolower($platform);
        if ($platform === 'meta') {
            $dataset = (string)($settings['meta']['dataset_id'] ?? '');
            $token = (string)($settings['meta']['access_token'] ?? '');
            if ($dataset === '' || $token === '') {
                return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Meta CAPI belum lengkap.'];
            }
            $version = (string)($settings['meta']['api_version'] ?? 'v20.0');
            $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($dataset) . '/events?access_token=' . rawurlencode($token);
            $payload = server_conversion_meta_payload($row, $settings);
            $result = server_conversion_http_post_json($url, [], $payload);
            $result['platform_event'] = (string)($payload['data'][0]['event_name'] ?? '');
            return $result;
        }

        if ($platform === 'tiktok') {
            $token = (string)($settings['tiktok']['access_token'] ?? '');
            if ($token === '' || (string)($settings['tiktok']['pixel_id'] ?? '') === '') {
                return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'TikTok Events API belum lengkap.'];
            }
            $version = (string)($settings['tiktok']['api_version'] ?? 'v1.3');
            $url = 'https://business-api.tiktok.com/open_api/' . rawurlencode($version) . '/event/track/';
            $payload = server_conversion_tiktok_payload($row, $settings);
            $result = server_conversion_http_post_json($url, ['Access-Token: ' . $token], $payload);
            $result['platform_event'] = (string)($payload['data'][0]['event'] ?? '');
            return $result;
        }

        return ['ok' => false, 'http_status' => 0, 'response' => '', 'error' => 'Platform belum didukung sender aktif.'];
    }
}

if (!function_exists('server_conversion_recalculate_row_status')) {
    function server_conversion_recalculate_row_status(array $row): array
    {
        $statuses = [];
        foreach ((array)($row['platforms'] ?? []) as $platform) {
            $statuses[] = (string)($platform['status'] ?? 'pending');
        }
        if (!$statuses) {
            $row['status'] = 'pending';
            return $row;
        }
        if (in_array('pending', $statuses, true)) {
            $row['status'] = 'pending';
        } elseif (in_array('failed', $statuses, true)) {
            $row['status'] = 'failed';
        } elseif (count(array_filter($statuses, static fn($s): bool => $s === 'ignored')) === count($statuses)) {
            $row['status'] = 'ignored';
        } else {
            $row['status'] = 'sent';
        }
        return $row;
    }
}

if (!function_exists('server_conversion_process_pending')) {
    function server_conversion_process_pending(int $limit = 20, bool $failedOnly = false): array
    {
        $settings = server_conversion_read_settings();
        $limit = max(1, min(100, $limit ?: (int)($settings['max_events_per_run'] ?? 20)));
        if (empty($settings['enabled']) || (string)($settings['sending_mode'] ?? 'manual') === 'disabled') {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0, 'disabled' => true];
        }
        $queue = server_conversion_read_queue();
        $processed = 0;
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($queue as &$row) {
            if ($processed >= $limit) {
                break;
            }
            $rowStatus = (string)($row['status'] ?? 'pending');
            if ($failedOnly) {
                if ($rowStatus !== 'failed') {
                    continue;
                }
            } elseif ($rowStatus !== 'pending') {
                continue;
            }

            $platforms = (array)($row['platforms'] ?? []);
            if (!$platforms) {
                $skipped++;
                continue;
            }

            $processed++;
            $row['attempts'] = (int)($row['attempts'] ?? 0) + 1;
            $row['updated_at'] = date('c');

            foreach ($platforms as $platformKey => $platformRow) {
                $platformStatus = (string)($platformRow['status'] ?? 'pending');
                if (!$failedOnly && $platformStatus !== 'pending') {
                    continue;
                }
                if ($failedOnly && $platformStatus !== 'failed') {
                    continue;
                }

                $result = server_conversion_send_platform((string)$platformKey, $row, $settings);
                $ok = !empty($result['ok']);
                $row['platforms'][$platformKey]['status'] = $ok ? 'sent' : 'failed';
                $row['platforms'][$platformKey]['last_error'] = $ok ? '' : server_conversion_clean((string)($result['error'] ?: $result['response'] ?: 'Unknown API error'), 300);
                $row['platforms'][$platformKey]['sent_at'] = $ok ? date('c') : null;
                $row['platforms'][$platformKey]['http_status'] = (int)($result['http_status'] ?? 0);
                $row['platforms'][$platformKey]['platform_event'] = (string)($result['platform_event'] ?? '');
                $row['platforms'][$platformKey]['last_response'] = server_conversion_clean((string)($result['response'] ?? ''), 500);

                server_conversion_log([
                    'action' => 'send',
                    'platform' => (string)$platformKey,
                    'event_name' => (string)($row['event_name'] ?? ''),
                    'platform_event' => (string)($result['platform_event'] ?? ''),
                    'event_id' => (string)($row['event_id'] ?? ''),
                    'status' => $ok ? 'sent' : 'failed',
                    'http_status' => (int)($result['http_status'] ?? 0),
                    'response' => server_conversion_clean((string)($result['response'] ?? ''), 500),
                    'error' => server_conversion_clean((string)($result['error'] ?? ''), 300),
                ]);
                $ok ? $sent++ : $failed++;
            }
            $row = server_conversion_recalculate_row_status($row);
        }
        unset($row);

        server_conversion_write_queue($queue);
        return ['processed' => $processed, 'sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }
}

if (!function_exists('server_conversion_mark_failed_ignored')) {
    function server_conversion_mark_failed_ignored(): int
    {
        $queue = server_conversion_read_queue();
        $count = 0;
        foreach ($queue as &$row) {
            if ((string)($row['status'] ?? '') !== 'failed') {
                continue;
            }
            foreach ((array)($row['platforms'] ?? []) as $key => $platform) {
                if ((string)($platform['status'] ?? '') === 'failed') {
                    $row['platforms'][$key]['status'] = 'ignored';
                    $row['platforms'][$key]['ignored_at'] = date('c');
                    $row['platforms'][$key]['last_error'] = '';
                }
            }
            $row['updated_at'] = date('c');
            $row = server_conversion_recalculate_row_status($row);
            $count++;
        }
        unset($row);
        if ($count > 0) {
            server_conversion_write_queue($queue);
            server_conversion_log(['action' => 'mark_failed_ignored', 'status' => 'ignored', 'count' => $count]);
        }
        return $count;
    }
}


if (!function_exists('server_conversion_platform_response_summary')) {
    function server_conversion_platform_response_summary(array $row): string
    {
        $parts = [];
        foreach ((array)($row['platforms'] ?? []) as $platformKey => $platformRow) {
            $status = server_conversion_clean((string)($platformRow['status'] ?? 'pending'), 40);
            $http = (int)($platformRow['http_status'] ?? 0);
            $event = server_conversion_clean((string)($platformRow['platform_event'] ?? ''), 80);
            $error = server_conversion_clean((string)($platformRow['last_error'] ?? ''), 220);
            $response = server_conversion_clean((string)($platformRow['last_response'] ?? ''), 220);
            $summary = strtoupper((string)$platformKey) . ': ' . $status;
            if ($event !== '') {
                $summary .= ' / ' . $event;
            }
            if ($http > 0) {
                $summary .= ' / HTTP ' . $http;
            }
            if ($error !== '') {
                $summary .= ' / ' . $error;
            } elseif ($response !== '') {
                $summary .= ' / ' . $response;
            }
            $parts[] = $summary;
        }
        return implode(' || ', $parts);
    }
}

if (!function_exists('server_conversion_debug_rows')) {
    function server_conversion_debug_rows(int $limit = 50, string $status = ''): array
    {
        $allowed = ['pending', 'sent', 'failed', 'ignored'];
        $status = in_array($status, $allowed, true) ? $status : '';
        $rows = [];
        foreach (array_reverse(server_conversion_read_queue()) as $row) {
            $rowStatus = (string)($row['status'] ?? 'pending');
            if ($status !== '' && $rowStatus !== $status) {
                continue;
            }
            $platformLabels = [];
            foreach ((array)($row['platforms'] ?? []) as $platformKey => $platformRow) {
                $platformLabels[] = strtoupper((string)$platformKey) . ':' . (string)($platformRow['status'] ?? 'pending');
            }
            $rows[] = [
                'id' => (string)($row['id'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'event_name' => (string)($row['event_name'] ?? ''),
                'event_group' => (string)($row['event_group'] ?? ''),
                'event_id' => (string)($row['event_id'] ?? ''),
                'status' => $rowStatus,
                'platforms' => $row['platforms'] ?? [],
                'platform_summary' => implode(', ', $platformLabels),
                'attempts' => (int)($row['attempts'] ?? 0),
                'source' => (string)($row['source'] ?? ''),
                'response_summary' => server_conversion_platform_response_summary($row),
            ];
            if (count($rows) >= max(1, min(500, $limit))) {
                break;
            }
        }
        return $rows;
    }
}

if (!function_exists('server_conversion_mark_ignored')) {
    function server_conversion_mark_ignored(array $ids = []): int
    {
        $ids = array_values(array_filter(array_map('strval', $ids), static fn(string $id): bool => $id !== ''));
        $allFailedWhenNoIds = count($ids) === 0;
        $idMap = array_flip($ids);
        $queue = server_conversion_read_queue();
        $count = 0;

        foreach ($queue as &$row) {
            $rowId = (string)($row['id'] ?? '');
            $rowStatus = (string)($row['status'] ?? 'pending');
            if ($allFailedWhenNoIds) {
                if ($rowStatus !== 'failed') {
                    continue;
                }
            } elseif (!isset($idMap[$rowId])) {
                continue;
            }
            if ($rowStatus === 'sent') {
                continue;
            }

            $changed = false;
            foreach ((array)($row['platforms'] ?? []) as $key => $platform) {
                $platformStatus = (string)($platform['status'] ?? 'pending');
                if (in_array($platformStatus, ['pending', 'failed'], true)) {
                    $row['platforms'][$key]['status'] = 'ignored';
                    $row['platforms'][$key]['ignored_at'] = date('c');
                    $row['platforms'][$key]['last_error'] = '';
                    $changed = true;
                }
            }
            if ($changed) {
                $row['updated_at'] = date('c');
                $row = server_conversion_recalculate_row_status($row);
                $count++;
            }
        }
        unset($row);

        if ($count > 0) {
            server_conversion_write_queue($queue);
            server_conversion_log(['action' => 'mark_ignored', 'status' => 'ignored', 'count' => $count, 'selected' => count($ids)]);
        }
        return $count;
    }
}

if (!function_exists('server_conversion_clear_old_sent_events')) {
    function server_conversion_clear_old_sent_events(int $days = 30): int
    {
        $days = max(1, min(3650, $days));
        $cutoff = strtotime('-' . $days . ' days') ?: 0;
        $queue = server_conversion_read_queue();
        $kept = [];
        $removed = 0;

        foreach ($queue as $row) {
            $status = (string)($row['status'] ?? 'pending');
            $rowTime = strtotime((string)($row['updated_at'] ?? $row['created_at'] ?? '')) ?: 0;
            if ($status === 'sent' && $rowTime > 0 && $rowTime < $cutoff) {
                $removed++;
                continue;
            }
            $kept[] = $row;
        }

        if ($removed > 0) {
            server_conversion_write_queue($kept);
            server_conversion_log(['action' => 'clear_old_sent_events', 'status' => 'ok', 'removed' => $removed, 'days' => $days]);
        }
        return $removed;
    }
}

if (!function_exists('server_conversion_export_debug_csv')) {
    function server_conversion_export_debug_csv(): void
    {
        $filename = 'server-conversion-debug-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'wb');
        if (!$out) {
            exit;
        }
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['created_at', 'updated_at', 'status', 'platforms', 'event_name', 'event_group', 'event_id', 'attempts', 'source', 'response_summary']);
        foreach (server_conversion_debug_rows(2000, '') as $row) {
            fputcsv($out, [
                $row['created_at'],
                $row['updated_at'],
                $row['status'],
                $row['platform_summary'],
                $row['event_name'],
                $row['event_group'],
                $row['event_id'],
                $row['attempts'],
                $row['source'],
                $row['response_summary'],
            ]);
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('server_conversion_cron_token_from_request')) {
    function server_conversion_cron_token_from_request(): string
    {
        $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
            return server_conversion_secret_clean((string)$matches[1], 160);
        }
        $headerToken = (string)($_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
        if ($headerToken !== '') {
            return server_conversion_secret_clean($headerToken, 160);
        }
        return server_conversion_secret_clean((string)($_GET['token'] ?? $_POST['token'] ?? ''), 160);
    }
}

if (!function_exists('server_conversion_process_cron_request')) {
    function server_conversion_process_cron_request(string $providedToken, ?int $limit = null): array
    {
        $settings = server_conversion_read_settings();
        $cron = (array)($settings['cron'] ?? []);
        $storedToken = (string)($cron['token'] ?? '');
        $mode = (string)($settings['sending_mode'] ?? 'manual');

        if (empty($cron['enabled'])) {
            return ['ok' => false, 'status' => 'disabled', 'message' => 'Scheduled retry belum diaktifkan.', 'http_status' => 200];
        }
        if ($storedToken === '') {
            return ['ok' => false, 'status' => 'missing_token', 'message' => 'Cron token belum diset.', 'http_status' => 403];
        }
        if ($providedToken === '' || !hash_equals($storedToken, $providedToken)) {
            server_conversion_log(['action' => 'cron_auth_failed', 'status' => 'forbidden']);
            return ['ok' => false, 'status' => 'forbidden', 'message' => 'Token cron tidak valid.', 'http_status' => 403];
        }
        if (!in_array($mode, ['auto', 'hybrid'], true)) {
            return ['ok' => false, 'status' => 'mode_not_auto', 'message' => 'Sending mode belum auto/hybrid.', 'http_status' => 409];
        }

        $limit = $limit !== null ? $limit : (int)($cron['max_events_per_run'] ?? $settings['max_events_per_run'] ?? 20);
        $limit = max(1, min(100, (int)$limit));
        $pendingResult = server_conversion_process_pending($limit, false);
        $remaining = max(0, $limit - (int)($pendingResult['processed'] ?? 0));
        $retryResult = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        if (!empty($cron['retry_failed']) && $remaining > 0) {
            $retryResult = server_conversion_process_pending($remaining, true);
        }
        $remainingGoogle = max(0, $limit - (int)($pendingResult['processed'] ?? 0) - (int)($retryResult['processed'] ?? 0));
        $googleAdsResult = ['processed' => 0, 'sent' => 0, 'validated' => 0, 'failed' => 0, 'skipped' => 0];
        if ($remainingGoogle > 0 && !empty($settings['google_ads']['sender']['enabled']) && function_exists('server_conversion_process_google_ads_queue')) {
            $googleAdsResult = server_conversion_process_google_ads_queue($remainingGoogle, false);
        }

        $result = [
            'ok' => true,
            'status' => 'ok',
            'mode' => $mode,
            'limit' => $limit,
            'pending' => $pendingResult,
            'retry_failed' => $retryResult,
            'google_ads' => $googleAdsResult,
            'totals' => [
                'processed' => (int)($pendingResult['processed'] ?? 0) + (int)($retryResult['processed'] ?? 0) + (int)($googleAdsResult['processed'] ?? 0),
                'sent' => (int)($pendingResult['sent'] ?? 0) + (int)($retryResult['sent'] ?? 0) + (int)($googleAdsResult['sent'] ?? 0),
                'validated' => (int)($googleAdsResult['validated'] ?? 0),
                'failed' => (int)($pendingResult['failed'] ?? 0) + (int)($retryResult['failed'] ?? 0) + (int)($googleAdsResult['failed'] ?? 0),
                'skipped' => (int)($pendingResult['skipped'] ?? 0) + (int)($retryResult['skipped'] ?? 0) + (int)($googleAdsResult['skipped'] ?? 0),
            ],
            'http_status' => 200,
        ];

        $settings['cron']['last_run_at'] = date('c');
        $settings['cron']['last_result'] = $result['totals'];
        server_conversion_write_settings($settings);
        server_conversion_log(['action' => 'cron_run', 'status' => 'ok', 'limit' => $limit] + $result['totals']);
        return $result;
    }
}

if (!function_exists('server_conversion_payload_preview')) {
    function server_conversion_payload_preview(?string $queueId = null): array
    {
        $settings = server_conversion_read_settings();
        $queue = server_conversion_read_queue();
        $selected = null;
        foreach (array_reverse($queue) as $row) {
            if ($queueId && (string)($row['id'] ?? '') !== $queueId) {
                continue;
            }
            if (!$queueId && !in_array((string)($row['status'] ?? 'pending'), ['pending', 'failed'], true)) {
                continue;
            }
            $selected = $row;
            break;
        }
        if (!$selected && $queue) {
            $selected = $queue[count($queue) - 1];
        }
        if (!$selected) {
            return [];
        }
        $preview = [
            'queue_id' => (string)($selected['id'] ?? ''),
            'event_id' => (string)($selected['event_id'] ?? ''),
            'event_name' => (string)($selected['event_name'] ?? ''),
            'status' => (string)($selected['status'] ?? 'pending'),
            'platforms' => [],
        ];
        foreach (array_keys((array)($selected['platforms'] ?? [])) as $platform) {
            if ($platform === 'meta') {
                $preview['platforms']['meta'] = server_conversion_meta_payload($selected, $settings);
            } elseif ($platform === 'tiktok') {
                $preview['platforms']['tiktok'] = server_conversion_tiktok_payload($selected, $settings);
            } else {
                $preview['platforms'][$platform] = ['note' => 'Sender aktif belum tersedia untuk platform ini.'];
            }
        }
        return $preview;
    }
}
