<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| ANALYTICS & MARKETING ATTRIBUTION + GOOGLE ADS VERIFICATION - Template
|--------------------------------------------------------------------------
| Privacy-safe local attribution layer for GTM/GA4/GSC, multi-pixel readiness,
| Google Ads tracking helper and click attribution capture
| untuk kebutuhan tracking iklan lanjutan. File ini menghindari nama, nomor telepon, email,
| token URLs, and payment details.
|--------------------------------------------------------------------------
*/

if (!function_exists('analytics_settings_file')) {
    function analytics_settings_file(): string
    {
        return STORAGE_PATH . '/analytics-settings.json';
    }
}

if (!function_exists('analytics_cookie_name')) {
    function analytics_cookie_name(): string
    {
        return 'hq_marketing_attr';
    }
}

if (!function_exists('analytics_clean')) {
    function analytics_clean(string $value, int $max = 160): string
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

if (!function_exists('analytics_id_clean')) {
    function analytics_id_clean(string $value, int $max = 80): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9_\-\.]/', '', (string)$value) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}


if (!function_exists('analytics_pixel_mode')) {
    function analytics_pixel_mode(string $value): string
    {
        $value = strtolower(trim($value));
        return in_array($value, ['gtm_only', 'direct', 'hybrid'], true) ? $value : 'gtm_only';
    }
}

if (!function_exists('analytics_digits_clean')) {
    function analytics_digits_clean(string $value, int $max = 40): string
    {
        $value = preg_replace('/[^0-9]/', '', trim($value)) ?: '';
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('analytics_google_ads_id_clean')) {
    function analytics_google_ads_id_clean(string $value): string
    {
        $value = strtoupper(analytics_id_clean($value, 40));
        if ($value !== '' && preg_match('/^[0-9]{6,20}$/', $value)) {
            $value = 'AW-' . $value;
        }
        return preg_match('/^AW-[0-9]{6,20}$/', $value) ? $value : '';
    }
}


if (!function_exists('analytics_google_ads_event_defaults')) {
    function analytics_google_ads_event_defaults(): array
    {
        return [
            'contact_whatsapp' => [
                'label' => 'Klik WhatsApp',
                'description' => 'Klik tombol/link WhatsApp dari website.',
                'recommended_goal' => 'Contact / Lead',
                'enabled' => true,
                'conversion_label' => '',
            ],
            'submit_inquiry' => [
                'label' => 'Submit Inquiry',
                'description' => 'Form inquiry/lead berhasil dikirim.',
                'recommended_goal' => 'Lead',
                'enabled' => true,
                'conversion_label' => '',
            ],
            'begin_checkout' => [
                'label' => 'Mulai Checkout',
                'description' => 'User mengirim draft order/checkout.',
                'recommended_goal' => 'Begin checkout / Lead',
                'enabled' => true,
                'conversion_label' => '',
            ],
            'order_success' => [
                'label' => 'Order Berhasil',
                'description' => 'Halaman order-success terbuka setelah order diterima.',
                'recommended_goal' => 'Purchase / Submit lead form',
                'enabled' => true,
                'conversion_label' => '',
            ],
            'upload_payment_proof' => [
                'label' => 'Upload Bukti Pembayaran',
                'description' => 'Customer mengirim bukti pembayaran.',
                'recommended_goal' => 'Qualified lead / Purchase assist',
                'enabled' => true,
                'conversion_label' => '',
            ],
        ];
    }
}

if (!function_exists('analytics_google_ads_event_map_normalize')) {
    function analytics_google_ads_event_map_normalize(array $events, string $fallbackLabel = ''): array
    {
        $defaults = analytics_google_ads_event_defaults();
        $normalized = [];

        foreach ($defaults as $eventName => $default) {
            $row = is_array($events[$eventName] ?? null) ? (array)$events[$eventName] : [];
            $normalized[$eventName] = [
                'label' => analytics_clean((string)($row['label'] ?? $default['label']), 80) ?: (string)$default['label'],
                'description' => (string)$default['description'],
                'recommended_goal' => (string)$default['recommended_goal'],
                'enabled' => array_key_exists('enabled', $row) ? !empty($row['enabled']) : !empty($default['enabled']),
                'conversion_label' => analytics_id_clean((string)($row['conversion_label'] ?? ''), 80),
            ];
        }

        return $normalized;
    }
}

if (!function_exists('analytics_google_ads_event_map_from_post')) {
    function analytics_google_ads_event_map_from_post(array $post): array
    {
        $events = [];
        foreach (analytics_google_ads_event_defaults() as $eventName => $default) {
            $prefix = 'google_ads_event_' . $eventName . '_';
            $events[$eventName] = [
                'label' => (string)($default['label'] ?? $eventName),
                'enabled' => !empty($post[$prefix . 'enabled']),
                'conversion_label' => analytics_id_clean((string)($post[$prefix . 'conversion_label'] ?? ''), 80),
            ];
        }
        return $events;
    }
}

if (!function_exists('analytics_custom_meta_normalize')) {
    function analytics_custom_meta_normalize(string $value): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
        $safe = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            if (!preg_match('/<meta\s+([^>]+)>/i', $line, $match)) {
                continue;
            }
            $attrs = [];
            if (preg_match_all('/([a-zA-Z0-9_:\-]+)\s*=\s*(["\'])(.*?)\2/', (string)$match[1], $attrMatches, PREG_SET_ORDER)) {
                foreach ($attrMatches as $attr) {
                    $key = strtolower((string)$attr[1]);
                    if (in_array($key, ['name', 'property', 'http-equiv', 'content'], true)) {
                        $attrs[$key] = analytics_clean((string)$attr[3], $key === 'content' ? 180 : 80);
                    }
                }
            }
            $nameKey = isset($attrs['name']) ? 'name' : (isset($attrs['property']) ? 'property' : (isset($attrs['http-equiv']) ? 'http-equiv' : ''));
            $nameValue = $nameKey !== '' ? (string)$attrs[$nameKey] : '';
            $content = (string)($attrs['content'] ?? '');
            if ($nameKey === '' || $nameValue === '' || $content === '') {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_:\.\-]+$/', $nameValue)) {
                continue;
            }
            $safe[] = '<meta ' . $nameKey . '="' . htmlspecialchars($nameValue, ENT_QUOTES, 'UTF-8') . '" content="' . htmlspecialchars($content, ENT_QUOTES, 'UTF-8') . '">';
            if (count($safe) >= 8) {
                break;
            }
        }
        return implode("\n", array_values(array_unique($safe)));
    }
}

if (!function_exists('analytics_default_settings')) {
    function analytics_default_settings(): array
    {
        return [
            'enabled' => true,
            'internal_attribution_enabled' => true,
            'datalayer_enabled' => true,
            'debug' => false,
            'cookie_days' => 90,
            'pixel_mode' => 'gtm_only',
            'gtm' => [
                'enabled' => false,
                'container_id' => '',
            ],
            'ga4' => [
                'enabled' => false,
                'measurement_id' => '',
            ],
            'gsc' => [
                'verification' => '',
            ],
            'pixels' => [
                'meta' => [
                    'enabled' => false,
                    'pixel_id' => '',
                ],
                'tiktok' => [
                    'enabled' => false,
                    'pixel_id' => '',
                ],
                'google_ads' => [
                    'enabled' => false,
                    'conversion_id' => '',
                    'conversion_label' => '',
                    'fire_conversion_events' => true,
                    'event_labels' => analytics_google_ads_event_defaults(),
                ],
                'microsoft_uet' => [
                    'enabled' => false,
                    'tag_id' => '',
                ],
                'linkedin' => [
                    'enabled' => false,
                    'partner_id' => '',
                ],
            ],
            'custom_meta' => '',
            'privacy' => [
                'strip_pii' => true,
                'track_admin' => false,
            ],
            'updated_at' => null,
        ];
    }
}

if (!function_exists('analytics_deep_merge')) {
    function analytics_deep_merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = analytics_deep_merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}

if (!function_exists('analytics_normalize_settings')) {
    function analytics_normalize_settings(array $settings): array
    {
        $settings = analytics_deep_merge(analytics_default_settings(), $settings);
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['internal_attribution_enabled'] = !empty($settings['internal_attribution_enabled']);
        $settings['datalayer_enabled'] = !empty($settings['datalayer_enabled']);
        $settings['debug'] = !empty($settings['debug']);
        $settings['cookie_days'] = max(1, min(730, (int)($settings['cookie_days'] ?? 90)));
        $settings['pixel_mode'] = analytics_pixel_mode((string)($settings['pixel_mode'] ?? 'gtm_only'));

        $settings['gtm']['enabled'] = !empty($settings['gtm']['enabled']);
        $settings['gtm']['container_id'] = analytics_id_clean((string)($settings['gtm']['container_id'] ?? ''), 40);
        if ($settings['gtm']['container_id'] !== '' && !preg_match('/^GTM-[A-Z0-9]+$/i', $settings['gtm']['container_id'])) {
            $settings['gtm']['container_id'] = '';
        }

        $settings['ga4']['enabled'] = !empty($settings['ga4']['enabled']);
        $settings['ga4']['measurement_id'] = analytics_id_clean((string)($settings['ga4']['measurement_id'] ?? ''), 40);
        if ($settings['ga4']['measurement_id'] !== '' && !preg_match('/^G-[A-Z0-9]+$/i', $settings['ga4']['measurement_id'])) {
            $settings['ga4']['measurement_id'] = '';
        }

        $settings['gsc']['verification'] = analytics_clean((string)($settings['gsc']['verification'] ?? ''), 160);

        foreach (['meta', 'tiktok', 'google_ads', 'microsoft_uet', 'linkedin'] as $platform) {
            $settings['pixels'][$platform]['enabled'] = !empty($settings['pixels'][$platform]['enabled']);
        }
        $settings['pixels']['meta']['pixel_id'] = analytics_digits_clean((string)($settings['pixels']['meta']['pixel_id'] ?? ''), 32);
        $settings['pixels']['tiktok']['pixel_id'] = analytics_id_clean((string)($settings['pixels']['tiktok']['pixel_id'] ?? ''), 40);
        if ($settings['pixels']['tiktok']['pixel_id'] !== '' && !preg_match('/^[A-Z0-9]{8,40}$/i', $settings['pixels']['tiktok']['pixel_id'])) {
            $settings['pixels']['tiktok']['pixel_id'] = '';
        }
        $settings['pixels']['google_ads']['conversion_id'] = analytics_google_ads_id_clean((string)($settings['pixels']['google_ads']['conversion_id'] ?? ''));
        $settings['pixels']['google_ads']['conversion_label'] = analytics_id_clean((string)($settings['pixels']['google_ads']['conversion_label'] ?? ''), 80);
        $settings['pixels']['google_ads']['fire_conversion_events'] = !array_key_exists('fire_conversion_events', (array)($settings['pixels']['google_ads'] ?? [])) || !empty($settings['pixels']['google_ads']['fire_conversion_events']);
        $settings['pixels']['google_ads']['event_labels'] = analytics_google_ads_event_map_normalize(
            is_array($settings['pixels']['google_ads']['event_labels'] ?? null) ? (array)$settings['pixels']['google_ads']['event_labels'] : [],
            (string)$settings['pixels']['google_ads']['conversion_label']
        );
        $settings['pixels']['microsoft_uet']['tag_id'] = analytics_digits_clean((string)($settings['pixels']['microsoft_uet']['tag_id'] ?? ''), 32);
        $settings['pixels']['linkedin']['partner_id'] = analytics_digits_clean((string)($settings['pixels']['linkedin']['partner_id'] ?? ''), 32);
        $settings['custom_meta'] = analytics_custom_meta_normalize((string)($settings['custom_meta'] ?? ''));

        $settings['privacy']['strip_pii'] = true;
        $settings['privacy']['track_admin'] = false;

        return $settings;
    }
}

if (!function_exists('analytics_read_settings')) {
    function analytics_read_settings(): array
    {
        $file = analytics_settings_file();
        if (!is_file($file)) {
            return analytics_default_settings();
        }

        $decoded = json_decode((string)@file_get_contents($file), true);
        return analytics_normalize_settings(is_array($decoded) ? $decoded : []);
    }
}

if (!function_exists('analytics_write_settings')) {
    function analytics_write_settings(array $settings): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }

        $settings = analytics_normalize_settings($settings);
        $settings['updated_at'] = date('c');

        return @file_put_contents(
            analytics_settings_file(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        ) !== false;
    }
}

if (!function_exists('analytics_settings_from_post')) {
    function analytics_settings_from_post(array $post): array
    {
        return analytics_normalize_settings([
            'enabled' => !empty($post['enabled']),
            'internal_attribution_enabled' => !empty($post['internal_attribution_enabled']),
            'datalayer_enabled' => !empty($post['datalayer_enabled']),
            'debug' => !empty($post['debug']),
            'cookie_days' => (int)($post['cookie_days'] ?? 90),
            'pixel_mode' => analytics_pixel_mode((string)($post['pixel_mode'] ?? 'gtm_only')),
            'gtm' => [
                'enabled' => !empty($post['gtm_enabled']),
                'container_id' => analytics_id_clean((string)($post['gtm_container_id'] ?? ''), 40),
            ],
            'ga4' => [
                'enabled' => !empty($post['ga4_enabled']),
                'measurement_id' => analytics_id_clean((string)($post['ga4_measurement_id'] ?? ''), 40),
            ],
            'gsc' => [
                'verification' => analytics_clean((string)($post['gsc_verification'] ?? ''), 160),
            ],
            'pixels' => [
                'meta' => [
                    'enabled' => !empty($post['meta_pixel_enabled']),
                    'pixel_id' => analytics_digits_clean((string)($post['meta_pixel_id'] ?? ''), 32),
                ],
                'tiktok' => [
                    'enabled' => !empty($post['tiktok_pixel_enabled']),
                    'pixel_id' => analytics_id_clean((string)($post['tiktok_pixel_id'] ?? ''), 40),
                ],
                'google_ads' => [
                    'enabled' => !empty($post['google_ads_enabled']),
                    'conversion_id' => analytics_google_ads_id_clean((string)($post['google_ads_conversion_id'] ?? '')),
                    'conversion_label' => analytics_id_clean((string)($post['google_ads_conversion_label'] ?? ''), 80),
                    'fire_conversion_events' => !empty($post['google_ads_fire_conversion_events']),
                    'event_labels' => analytics_google_ads_event_map_from_post($post),
                ],
                'microsoft_uet' => [
                    'enabled' => !empty($post['microsoft_uet_enabled']),
                    'tag_id' => analytics_digits_clean((string)($post['microsoft_uet_id'] ?? ''), 32),
                ],
                'linkedin' => [
                    'enabled' => !empty($post['linkedin_enabled']),
                    'partner_id' => analytics_digits_clean((string)($post['linkedin_partner_id'] ?? ''), 32),
                ],
            ],
            'custom_meta' => (string)($post['custom_meta'] ?? ''),
        ]);
    }
}

if (!function_exists('analytics_is_admin_request')) {
    function analytics_is_admin_request(): bool
    {
        $path = trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? ''), '/');
        $basePath = trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?? ''), '/');
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = trim(substr($path, strlen($basePath)), '/');
        }

        if ($path === 'admin/google-ads-tracking-test') {
            return false;
        }

        return $path === 'admin' || str_starts_with($path, 'admin/') || str_starts_with($path, 'admin-');
    }
}

if (!function_exists('analytics_is_public_tracking_request')) {
    function analytics_is_public_tracking_request(): bool
    {
        if (analytics_is_admin_request()) {
            return false;
        }

        $path = trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? ''), '/');
        foreach (['lead-event', 'payment-gateway/webhook', 'webhook/payment-gateway'] as $blocked) {
            if ($path === $blocked || str_contains($path, $blocked)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('analytics_safe_path')) {
    function analytics_safe_path(string $urlOrPath): string
    {
        $urlOrPath = trim($urlOrPath);
        if ($urlOrPath === '') {
            return '/';
        }

        $parts = parse_url($urlOrPath);
        $path = (string)($parts['path'] ?? $urlOrPath);
        $query = (string)($parts['query'] ?? '');
        $safeQuery = [];

        if ($query !== '') {
            parse_str($query, $queryData);
            foreach ($queryData as $key => $value) {
                $key = strtolower(analytics_id_clean((string)$key, 40));
                if ($key === '' || in_array($key, ['name', 'nama', 'phone', 'wa', 'whatsapp', 'email', 'alamat', 'address', 'token', 'public_token', 'password'], true)) {
                    continue;
                }
                if (!is_scalar($value)) {
                    continue;
                }
                $safeQuery[$key] = analytics_clean((string)$value, 120);
            }
        }

        $path = '/' . ltrim($path, '/');
        return $path . ($safeQuery ? '?' . http_build_query($safeQuery) : '');
    }
}

if (!function_exists('analytics_relevant_query_params')) {
    function analytics_relevant_query_params(array $query): array
    {
        $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'twclid', 'msclkid'];
        $out = [];
        foreach ($keys as $key) {
            if (!isset($query[$key]) || !is_scalar($query[$key])) {
                continue;
            }
            $max = in_array($key, ['gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'twclid', 'msclkid'], true) ? 220 : 120;
            $value = analytics_clean((string)$query[$key], $max);
            if ($value !== '') {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}

if (!function_exists('analytics_google_click_id_from_params')) {
    function analytics_google_click_id_from_params(array $params): array
    {
        foreach (['gclid', 'gbraid', 'wbraid'] as $key) {
            $value = analytics_clean((string)($params[$key] ?? ''), 220);
            if ($value !== '') {
                return ['type' => $key, 'value' => $value];
            }
        }
        return ['type' => '', 'value' => ''];
    }
}

if (!function_exists('analytics_mask_click_id')) {
    function analytics_mask_click_id(string $value): string
    {
        $value = analytics_clean($value, 220);
        if ($value === '') {
            return 'Belum ada';
        }
        if (strlen($value) <= 14) {
            return substr($value, 0, 4) . str_repeat('•', 6);
        }
        return substr($value, 0, 6) . str_repeat('•', 8) . substr($value, -5);
    }
}

if (!function_exists('analytics_referrer_host')) {
    function analytics_referrer_host(string $referrer): string
    {
        $host = strtolower((string)(parse_url($referrer, PHP_URL_HOST) ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?: $host;
        return analytics_clean($host, 120);
    }
}

if (!function_exists('analytics_host_matches')) {
    function analytics_host_matches(string $host, array $needles): bool
    {
        foreach ($needles as $needle) {
            $needle = strtolower((string)$needle);
            if ($host === $needle || str_ends_with($host, '.' . $needle) || str_contains($host, $needle)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('analytics_channel_group')) {
    function analytics_channel_group(array $params, string $referrer = ''): string
    {
        $source = strtolower((string)($params['utm_source'] ?? ''));
        $medium = strtolower((string)($params['utm_medium'] ?? ''));
        $refHost = analytics_referrer_host($referrer);
        $hasPaidClick = !empty($params['gclid']) || !empty($params['gbraid']) || !empty($params['wbraid']) || !empty($params['msclkid']);
        $hasSocialClick = !empty($params['fbclid']) || !empty($params['ttclid']) || !empty($params['twclid']);

        $searchHosts = ['google.', 'bing.com', 'yahoo.', 'duckduckgo.com', 'yandex.', 'baidu.'];
        $socialHosts = ['facebook.com', 'instagram.com', 't.co', 'twitter.com', 'x.com', 'linkedin.com', 'tiktok.com', 'youtube.com', 'pinterest.'];
        $chatHosts = ['whatsapp.com', 'wa.me', 'telegram.', 'line.me'];

        if ($hasPaidClick || in_array($medium, ['cpc', 'ppc', 'paidsearch', 'paid_search', 'sem', 'ads'], true)) {
            return 'paid_search';
        }
        if ($hasSocialClick || str_contains($medium, 'paid_social')) {
            return 'paid_social';
        }
        if (in_array($medium, ['social', 'social-organic', 'organic_social'], true) || analytics_host_matches($refHost, $socialHosts)) {
            return 'organic_social';
        }
        if ($medium === 'email' || str_contains($source, 'mail')) {
            return 'email';
        }
        if (analytics_host_matches($refHost, $chatHosts) || str_contains($source, 'whatsapp')) {
            return 'chat';
        }
        if ($refHost !== '' && analytics_host_matches($refHost, $searchHosts)) {
            return 'organic_search';
        }
        if ($source !== '' || $medium !== '') {
            return 'campaign';
        }
        if ($refHost !== '' && $refHost !== app_host()) {
            return 'referral';
        }
        return 'direct';
    }
}

if (!function_exists('analytics_channel_label')) {
    function analytics_channel_label(string $channel): string
    {
        return match ($channel) {
            'paid_search' => 'Paid Search',
            'paid_social' => 'Paid Social',
            'organic_search' => 'Organic Search',
            'organic_social' => 'Organic Social',
            'email' => 'Email',
            'chat' => 'Chat / WhatsApp',
            'referral' => 'Referral',
            'campaign' => 'Campaign',
            default => 'Direct',
        };
    }
}

if (!function_exists('analytics_build_touch')) {
    function analytics_build_touch(array $queryParams, string $referrer): array
    {
        $params = analytics_relevant_query_params($queryParams);
        $channel = analytics_channel_group($params, $referrer);
        $googleClick = analytics_google_click_id_from_params($params);
        $genericClickId = analytics_clean((string)($googleClick['value'] ?: ($params['fbclid'] ?? $params['ttclid'] ?? $params['twclid'] ?? $params['msclkid'] ?? '')), 220);
        $genericClickType = analytics_clean((string)($googleClick['type'] ?: (!empty($params['fbclid']) ? 'fbclid' : (!empty($params['ttclid']) ? 'ttclid' : (!empty($params['twclid']) ? 'twclid' : (!empty($params['msclkid']) ? 'msclkid' : ''))))), 30);

        return [
            'time' => date('c'),
            'channel_group' => $channel,
            'channel_label' => analytics_channel_label($channel),
            'source' => analytics_clean((string)($params['utm_source'] ?? ($referrer !== '' ? analytics_referrer_host($referrer) : 'direct')), 80),
            'medium' => analytics_clean((string)($params['utm_medium'] ?? ($channel === 'direct' ? '(none)' : 'referral')), 80),
            'campaign' => analytics_clean((string)($params['utm_campaign'] ?? ''), 120),
            'content' => analytics_clean((string)($params['utm_content'] ?? ''), 120),
            'term' => analytics_clean((string)($params['utm_term'] ?? ''), 120),
            'landing_page' => analytics_safe_path(current_url()),
            'referrer_host' => analytics_referrer_host($referrer),
            'click_id' => $genericClickId,
            'click_id_type' => $genericClickType,
            'gclid' => analytics_clean((string)($params['gclid'] ?? ''), 220),
            'gbraid' => analytics_clean((string)($params['gbraid'] ?? ''), 220),
            'wbraid' => analytics_clean((string)($params['wbraid'] ?? ''), 220),
            'google_ads_click_id' => analytics_clean((string)$googleClick['value'], 220),
            'google_ads_click_id_type' => analytics_clean((string)$googleClick['type'], 30),
        ];
    }
}

if (!function_exists('analytics_cookie_decode')) {
    function analytics_cookie_decode(string $value): array
    {
        $decoded = json_decode(base64_decode(strtr($value, '-_', '+/')) ?: '', true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('analytics_cookie_encode')) {
    function analytics_cookie_encode(array $value): string
    {
        return rtrim(strtr(base64_encode(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'), '+/', '-_'), '=');
    }
}

if (!function_exists('analytics_capture_attribution')) {
    function analytics_capture_attribution(): void
    {
        static $captured = false;
        if ($captured) {
            return;
        }
        $captured = true;

        $settings = analytics_read_settings();
        if (empty($settings['enabled']) || empty($settings['internal_attribution_enabled']) || !analytics_is_public_tracking_request()) {
            return;
        }

        $current = analytics_cookie_decode((string)($_COOKIE[analytics_cookie_name()] ?? ''));
        $referrer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $touch = analytics_build_touch($_GET, $referrer);
        $hasSignal = !empty(analytics_relevant_query_params($_GET)) || $touch['channel_group'] !== 'direct' || empty($current['first_touch']);

        if (!$hasSignal) {
            return;
        }

        $payload = [
            'version' => 'v29.32',
            'session_id' => (string)($current['session_id'] ?? bin2hex(random_bytes(8))),
            'first_touch' => is_array($current['first_touch'] ?? null) ? $current['first_touch'] : $touch,
            'last_touch' => $touch,
            'last_seen' => date('c'),
        ];

        $cookieDays = max(1, min(730, (int)($settings['cookie_days'] ?? 90)));
        @setcookie(analytics_cookie_name(), analytics_cookie_encode($payload), [
            'expires' => time() + ($cookieDays * 86400),
            'path' => '/',
            'secure' => app_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[analytics_cookie_name()] = analytics_cookie_encode($payload);
    }
}

if (!function_exists('analytics_current_attribution')) {
    function analytics_current_attribution(): array
    {
        analytics_capture_attribution();
        $decoded = analytics_cookie_decode((string)($_COOKIE[analytics_cookie_name()] ?? ''));
        if (!$decoded) {
            return [];
        }
        return [
            'version' => analytics_clean((string)($decoded['version'] ?? 'v29.32'), 20),
            'session_id' => analytics_clean((string)($decoded['session_id'] ?? ''), 40),
            'first_touch' => is_array($decoded['first_touch'] ?? null) ? $decoded['first_touch'] : [],
            'last_touch' => is_array($decoded['last_touch'] ?? null) ? $decoded['last_touch'] : [],
            'last_seen' => analytics_clean((string)($decoded['last_seen'] ?? ''), 40),
        ];
    }
}

if (!function_exists('analytics_google_ads_click_id_summary')) {
    function analytics_google_ads_click_id_summary(?array $attribution = null): array
    {
        $attribution = $attribution ?? analytics_current_attribution();
        $last = is_array($attribution['last_touch'] ?? null) ? $attribution['last_touch'] : [];
        $first = is_array($attribution['first_touch'] ?? null) ? $attribution['first_touch'] : [];
        $lastId = analytics_clean((string)($last['google_ads_click_id'] ?? ''), 220);
        $firstId = analytics_clean((string)($first['google_ads_click_id'] ?? ''), 220);
        return [
            'has_last_click_id' => $lastId !== '',
            'has_first_click_id' => $firstId !== '',
            'last_click_id_type' => analytics_clean((string)($last['google_ads_click_id_type'] ?? ''), 30),
            'first_click_id_type' => analytics_clean((string)($first['google_ads_click_id_type'] ?? ''), 30),
            'last_click_id_mask' => analytics_mask_click_id($lastId),
            'first_click_id_mask' => analytics_mask_click_id($firstId),
            'last_landing_page' => analytics_clean((string)($last['landing_page'] ?? ''), 180),
            'first_landing_page' => analytics_clean((string)($first['landing_page'] ?? ''), 180),
        ];
    }
}

if (!function_exists('analytics_attribution_channel')) {
    function analytics_attribution_channel(array $attribution): string
    {
        $touch = is_array($attribution['last_touch'] ?? null) ? $attribution['last_touch'] : [];
        return analytics_clean((string)($touch['channel_group'] ?? 'direct'), 40) ?: 'direct';
    }
}

if (!function_exists('analytics_enrich_record')) {
    function analytics_enrich_record(array $record): array
    {
        if (analytics_is_admin_request()) {
            return $record;
        }

        $attribution = analytics_current_attribution();
        if ($attribution) {
            $record['attribution'] = $attribution;
            $last = is_array($attribution['last_touch'] ?? null) ? $attribution['last_touch'] : [];
            $first = is_array($attribution['first_touch'] ?? null) ? $attribution['first_touch'] : [];
            $record['marketing_channel'] = analytics_attribution_channel($attribution);
            $record['marketing_channel_label'] = analytics_channel_label($record['marketing_channel']);
            $record['utm_source'] = analytics_clean((string)($last['source'] ?? ''), 80);
            $record['utm_medium'] = analytics_clean((string)($last['medium'] ?? ''), 80);
            $record['utm_campaign'] = analytics_clean((string)($last['campaign'] ?? ''), 120);
            $record['utm_content'] = analytics_clean((string)($last['content'] ?? ''), 120);
            $record['utm_term'] = analytics_clean((string)($last['term'] ?? ''), 120);
            $record['click_id'] = analytics_clean((string)($last['click_id'] ?? ''), 220);
            $record['click_id_type'] = analytics_clean((string)($last['click_id_type'] ?? ''), 30);
            $record['gclid'] = analytics_clean((string)($last['gclid'] ?? ''), 220);
            $record['gbraid'] = analytics_clean((string)($last['gbraid'] ?? ''), 220);
            $record['wbraid'] = analytics_clean((string)($last['wbraid'] ?? ''), 220);
            $record['google_ads_click_id'] = analytics_clean((string)($last['google_ads_click_id'] ?? ''), 220);
            $record['google_ads_click_id_type'] = analytics_clean((string)($last['google_ads_click_id_type'] ?? ''), 30);
            $record['first_touch_channel'] = analytics_clean((string)($first['channel_group'] ?? ''), 40);
            $record['first_touch_landing_page'] = analytics_clean((string)($first['landing_page'] ?? ''), 180);
        }
        return $record;
    }
}

if (!function_exists('analytics_event_name')) {
    function analytics_event_name(array $event): string
    {
        $blob = function_exists('conversion_event_text_blob') ? conversion_event_text_blob($event) : strtolower(json_encode($event) ?: '');
        $group = function_exists('conversion_event_group') ? conversion_event_group($event) : '';
        $channel = strtolower((string)($event['channel'] ?? ''));

        if (!empty($event['is_whatsapp']) || $channel === 'whatsapp') {
            return 'contact_whatsapp';
        }
        if (str_contains($blob, 'order_success') || str_contains($blob, 'order-success') || str_contains($blob, 'order berhasil')) {
            return 'order_success';
        }
        if ($group === 'order' || str_contains($blob, 'checkout')) {
            return 'begin_checkout';
        }
        if ($group === 'inquiry' || str_contains($blob, 'inquiry')) {
            return 'submit_inquiry';
        }
        if (str_contains($blob, 'payment-proof') || str_contains($blob, 'proof_submit')) {
            return 'upload_payment_proof';
        }
        if (str_contains($blob, '/invoice') || str_contains($blob, 'invoice')) {
            return 'view_invoice';
        }
        if (str_contains($blob, 'order-status')) {
            return 'check_order_status';
        }
        return $group === 'page_view' ? 'page_view' : 'select_item';
    }
}

if (!function_exists('analytics_enrich_conversion_event')) {
    function analytics_enrich_conversion_event(array $event): array
    {
        $event = function_exists('conversion_enrich_lead_event') ? conversion_enrich_lead_event($event) : $event;
        $event['analytics_event'] = analytics_event_name($event);
        $event = analytics_enrich_record($event);
        return $event;
    }
}

if (!function_exists('analytics_datalayer_page_payload')) {
    function analytics_datalayer_page_payload(): array
    {
        $attribution = analytics_current_attribution();
        $last = is_array($attribution['last_touch'] ?? null) ? $attribution['last_touch'] : [];
        $first = is_array($attribution['first_touch'] ?? null) ? $attribution['first_touch'] : [];
        return [
            'event' => 'page_context_ready',
            'site_name' => SITE_NAME,
            'page_path' => analytics_safe_path(current_url()),
            'marketing_channel' => analytics_clean((string)($last['channel_group'] ?? 'direct'), 40) ?: 'direct',
            'marketing_channel_label' => analytics_clean((string)($last['channel_label'] ?? 'Direct'), 40) ?: 'Direct',
            'utm_source' => analytics_clean((string)($last['source'] ?? ''), 80),
            'utm_medium' => analytics_clean((string)($last['medium'] ?? ''), 80),
            'utm_campaign' => analytics_clean((string)($last['campaign'] ?? ''), 120),
            'google_ads_click_id_type' => analytics_clean((string)($last['google_ads_click_id_type'] ?? ''), 30),
            'has_google_ads_click_id' => analytics_clean((string)($last['google_ads_click_id'] ?? ''), 220) !== '',
            'first_touch_channel' => analytics_clean((string)($first['channel_group'] ?? ''), 40),
            'first_touch_landing_page' => analytics_clean((string)($first['landing_page'] ?? ''), 180),
        ];
    }
}


if (!function_exists('analytics_direct_pixels_enabled')) {
    function analytics_direct_pixels_enabled(array $settings): bool
    {
        return !empty($settings['enabled']) && in_array((string)($settings['pixel_mode'] ?? 'gtm_only'), ['direct', 'hybrid'], true);
    }
}

if (!function_exists('analytics_ads_pixel_config')) {
    function analytics_ads_pixel_config(array $settings): array
    {
        $pixels = is_array($settings['pixels'] ?? null) ? $settings['pixels'] : [];
        return [
            'mode' => (string)($settings['pixel_mode'] ?? 'gtm_only'),
            'direct_enabled' => analytics_direct_pixels_enabled($settings),
            'debug' => !empty($settings['debug']),
            'pii_safe' => true,
            'meta' => [
                'enabled' => !empty($pixels['meta']['enabled']) && (string)($pixels['meta']['pixel_id'] ?? '') !== '',
                'pixel_id' => (string)($pixels['meta']['pixel_id'] ?? ''),
            ],
            'tiktok' => [
                'enabled' => !empty($pixels['tiktok']['enabled']) && (string)($pixels['tiktok']['pixel_id'] ?? '') !== '',
                'pixel_id' => (string)($pixels['tiktok']['pixel_id'] ?? ''),
            ],
            'google_ads' => [
                'enabled' => !empty($pixels['google_ads']['enabled']) && (string)($pixels['google_ads']['conversion_id'] ?? '') !== '',
                'conversion_id' => (string)($pixels['google_ads']['conversion_id'] ?? ''),
                'conversion_label' => (string)($pixels['google_ads']['conversion_label'] ?? ''),
                'fire_conversion_events' => !array_key_exists('fire_conversion_events', (array)($pixels['google_ads'] ?? [])) || !empty($pixels['google_ads']['fire_conversion_events']),
                'event_labels' => analytics_google_ads_event_map_normalize(
                    is_array($pixels['google_ads']['event_labels'] ?? null) ? (array)$pixels['google_ads']['event_labels'] : [],
                    (string)($pixels['google_ads']['conversion_label'] ?? '')
                ),
                'dedupe_ttl_seconds' => 1800,
                'verification_url' => function_exists('url') ? url('admin/google-ads-tracking-test') : '/admin/google-ads-tracking-test',
            ],
            'microsoft_uet' => [
                'enabled' => !empty($pixels['microsoft_uet']['enabled']) && (string)($pixels['microsoft_uet']['tag_id'] ?? '') !== '',
                'tag_id' => (string)($pixels['microsoft_uet']['tag_id'] ?? ''),
            ],
            'linkedin' => [
                'enabled' => !empty($pixels['linkedin']['enabled']) && (string)($pixels['linkedin']['partner_id'] ?? '') !== '',
                'partner_id' => (string)($pixels['linkedin']['partner_id'] ?? ''),
            ],
        ];
    }
}

if (!function_exists('analytics_pixel_status_summary')) {
    function analytics_pixel_status_summary(?array $settings = null): array
    {
        $settings = $settings ? analytics_normalize_settings($settings) : analytics_read_settings();
        $config = analytics_ads_pixel_config($settings);
        $rows = [
            'meta' => ['label' => 'Meta Pixel', 'enabled' => $config['meta']['enabled'], 'id' => $config['meta']['pixel_id']],
            'tiktok' => ['label' => 'TikTok Pixel', 'enabled' => $config['tiktok']['enabled'], 'id' => $config['tiktok']['pixel_id']],
            'google_ads' => ['label' => 'Google Ads', 'enabled' => $config['google_ads']['enabled'], 'id' => trim($config['google_ads']['conversion_id'] . '/' . $config['google_ads']['conversion_label'], '/')],
            'microsoft_uet' => ['label' => 'Microsoft UET', 'enabled' => $config['microsoft_uet']['enabled'], 'id' => $config['microsoft_uet']['tag_id']],
            'linkedin' => ['label' => 'LinkedIn Insight', 'enabled' => $config['linkedin']['enabled'], 'id' => $config['linkedin']['partner_id']],
        ];
        $active = 0;
        foreach ($rows as $row) {
            if (!empty($row['enabled'])) {
                $active++;
            }
        }
        return [
            'mode' => (string)($settings['pixel_mode'] ?? 'gtm_only'),
            'direct_enabled' => analytics_direct_pixels_enabled($settings),
            'active_count' => $active,
            'custom_meta_count' => (string)($settings['custom_meta'] ?? '') !== '' ? substr_count((string)$settings['custom_meta'], '<meta ') : 0,
            'platforms' => $rows,
        ];
    }
}


if (!function_exists('analytics_google_ads_direct_status')) {
    function analytics_google_ads_direct_status(?array $settings = null): array
    {
        $settings = $settings ? analytics_normalize_settings($settings) : analytics_read_settings();
        $google = (array)($settings['pixels']['google_ads'] ?? []);
        $events = analytics_google_ads_event_map_normalize(
            is_array($google['event_labels'] ?? null) ? (array)$google['event_labels'] : [],
            (string)($google['conversion_label'] ?? '')
        );
        $conversionId = analytics_google_ads_id_clean((string)($google['conversion_id'] ?? ''));
        $fallbackLabel = analytics_id_clean((string)($google['conversion_label'] ?? ''), 80);
        $directModeReady = analytics_direct_pixels_enabled($settings);
        $gtmReady = !empty($settings['gtm']['enabled']) && analytics_id_clean((string)($settings['gtm']['container_id'] ?? ''), 40) !== '';
        $enabled = !empty($google['enabled']);
        $eventEnabled = !empty($google['fire_conversion_events']);
        $readyEvents = 0;
        $enabledEvents = 0;
        $missingLabels = [];

        foreach ($events as $eventName => $row) {
            if (empty($row['enabled'])) {
                continue;
            }
            $enabledEvents++;
            if ((string)($row['conversion_label'] ?? '') !== '' || $fallbackLabel !== '') {
                $readyEvents++;
            } else {
                $missingLabels[] = $eventName;
            }
        }

        $directReady = $enabled && $eventEnabled && $conversionId !== '' && $directModeReady && $readyEvents > 0;
        $trackingReady = $directReady || ($enabled && $gtmReady);
        $statusLabel = !$enabled
            ? 'Disabled'
            : ($directReady ? 'Pixel Langsung Siap' : ($gtmReady ? 'Google Tag Manager Siap' : 'Perlu Setup'));
        $message = !$enabled
            ? 'Google Ads website conversion tracking belum aktif.'
            : ($directReady
                ? 'Google Ads direct tag siap mengirim conversion event dari website.'
                : ($gtmReady
                    ? 'Google Tag Manager aktif. Pastikan tag konversi Google Ads sudah dibuat.'
                    : 'Isi ID Google Tag, label konversi, dan pilih mode pemasangan.'));

        return [
            'enabled' => $enabled,
            'conversion_id' => $conversionId,
            'fallback_label_set' => $fallbackLabel !== '',
            'fire_conversion_events' => $eventEnabled,
            'direct_mode_ready' => $directModeReady,
            'direct_ready' => $directReady,
            'gtm_ready' => $gtmReady,
            'tracking_ready' => $trackingReady,
            'status_label' => $statusLabel,
            'message' => $message,
            'enabled_event_count' => $enabledEvents,
            'ready_event_count' => $readyEvents,
            'missing_label_events' => $missingLabels,
            'events' => $events,
        ];
    }
}



if (!function_exists('analytics_google_ads_test_events')) {
    function analytics_google_ads_test_events(): array
    {
        return [
            'contact_whatsapp' => [
                'label' => 'Test Klik WhatsApp',
                'description' => 'Simulasi klik CTA WhatsApp dari halaman test tracking.',
                'type' => 'whatsapp',
                'channel' => 'whatsapp',
                'intent' => 'tracking-test-whatsapp',
                'category' => 'google-ads-test',
            ],
            'submit_inquiry' => [
                'label' => 'Test Submit Inquiry',
                'description' => 'Simulasi inquiry/lead masuk tanpa data pribadi customer.',
                'type' => 'form_submit',
                'channel' => 'form',
                'intent' => 'tracking-test-inquiry',
                'category' => 'google-ads-test',
            ],
            'begin_checkout' => [
                'label' => 'Test Mulai Checkout',
                'description' => 'Simulasi user mulai checkout/order intent.',
                'type' => 'order_submit',
                'channel' => 'checkout',
                'intent' => 'tracking-test-checkout',
                'category' => 'google-ads-test',
            ],
            'order_success' => [
                'label' => 'Test Order Success',
                'description' => 'Simulasi event order berhasil untuk mengecek purchase/lead conversion.',
                'type' => 'order_success',
                'channel' => 'checkout',
                'intent' => 'tracking-test-order-success',
                'category' => 'google-ads-test',
            ],
            'upload_payment_proof' => [
                'label' => 'Test Upload Bukti Pembayaran',
                'description' => 'Simulasi upload bukti pembayaran tanpa file dan tanpa data pribadi.',
                'type' => 'proof_submit',
                'channel' => 'payment',
                'intent' => 'tracking-test-payment-proof',
                'category' => 'google-ads-test',
            ],
        ];
    }
}

if (!function_exists('analytics_google_ads_verification_summary')) {
    function analytics_google_ads_verification_summary(?array $settings = null): array
    {
        $settings = $settings ? analytics_normalize_settings($settings) : analytics_read_settings();
        $direct = analytics_google_ads_direct_status($settings);
        $click = analytics_google_ads_click_id_summary();
        $missingLabels = [];
        $eventDefaults = analytics_google_ads_event_defaults();
        foreach ((array)($direct['missing_label_events'] ?? []) as $eventName) {
            $missingLabels[] = (string)($eventDefaults[$eventName]['label'] ?? $eventName);
        }

        $directReady = !empty($direct['direct_ready']);
        $gtmReady = !empty($direct['gtm_ready']);
        $trackingReady = !empty($direct['tracking_ready']);
        $enabled = !empty($direct['enabled']);
        $datalayerReady = !empty($settings['datalayer_enabled']);
        $awReady = (string)($direct['conversion_id'] ?? '') !== '';
        $labelsReady = (int)($direct['ready_event_count'] ?? 0) > 0;
        $testPath = 'admin/google-ads-tracking-test?gclid=test-gclid-template&utm_source=google&utm_medium=cpc&utm_campaign=tracking-test';
        $testUrl = function_exists('url') ? url($testPath) : '/' . $testPath;

        $checks = [
            'enabled' => [
                'label' => 'Tracking Google Ads aktif',
                'ok' => $enabled,
                'message' => $enabled ? 'Tracking Google Ads dari website aktif.' : 'Aktifkan tracking Google Ads dulu.',
            ],
            'aw_id' => [
                'label' => 'AW-ID / Google Tag ID terisi',
                'ok' => $awReady,
                'message' => $awReady ? 'AW-ID siap dipakai.' : 'Isi Google Tag / Conversion ID dari Google Ads.',
            ],
            'labels' => [
                'label' => 'Conversion label event siap',
                'ok' => $labelsReady,
                'message' => $labelsReady ? (int)$direct['ready_event_count'] . ' event label siap.' : 'Isi minimal label WhatsApp Click atau Submit Inquiry.',
            ],
            'mode' => [
                'label' => 'Mode tracking siap',
                'ok' => $directReady || $gtmReady,
                'message' => $directReady ? 'Mode pemasangan siap mengirim konversi.' : ($gtmReady ? 'Google Tag Manager siap, pastikan tag sudah dibuat.' : 'Pilih mode langsung/gabungan atau aktifkan Google Tag Manager.'),
            ],
            'datalayer' => [
                'label' => 'Google Tag Manager aktif',
                'ok' => $datalayerReady,
                'message' => $datalayerReady ? 'Event aman dikirim ke Google Tag Manager.' : 'Aktifkan event Google Tag Manager agar tracking mudah membaca aktivitas.',
            ],
            'click_id_capture' => [
                'label' => 'Click ID Google Ads pernah tertangkap',
                'ok' => !empty($click['has_last_click_id']) || !empty($click['has_first_click_id']),
                'message' => (!empty($click['has_last_click_id']) || !empty($click['has_first_click_id'])) ? 'Ada data klik Google Ads di browser admin.' : 'Belum ada data klik pada browser admin. Buka URL tes untuk simulasi.',
            ],
            'anti_duplicate' => [
                'label' => 'Anti double-fire browser aktif',
                'ok' => true,
                'message' => 'Template menahan conversion event browser dengan event_id yang sama agar tidak dobel fire.',
            ],
        ];

        $okCount = 0;
        foreach ($checks as $check) {
            if (!empty($check['ok'])) {
                $okCount++;
            }
        }
        $total = count($checks);
        $statusLabel = !$enabled ? 'Disabled' : ($trackingReady && $okCount >= 5 ? 'Ready to Verify' : 'Perlu Setup');

        return [
            'status_label' => $statusLabel,
            'ok_count' => $okCount,
            'total' => $total,
            'tracking_ready' => $trackingReady,
            'direct_ready' => $directReady,
            'gtm_ready' => $gtmReady,
            'test_url' => $testUrl,
            'checks' => $checks,
            'missing_label_events' => $missingLabels,
            'click_id' => $click,
            'events' => analytics_google_ads_test_events(),
        ];
    }
}

if (!function_exists('analytics_render_custom_meta')) {
    function analytics_render_custom_meta(string $customMeta): void
    {
        $customMeta = analytics_custom_meta_normalize($customMeta);
        if ($customMeta === '') {
            return;
        }
        foreach (preg_split('/\r\n|\r|\n/', $customMeta) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line !== '') {
                echo "
    " . $line;
            }
        }
        echo "
";
    }
}

if (!function_exists('analytics_render_direct_pixels')) {
    function analytics_render_direct_pixels(array $settings, string $ga4Id = ''): void
    {
        $config = analytics_ads_pixel_config($settings);
        $directEnabled = !empty($config['direct_enabled']);
        if (!$directEnabled && $ga4Id === '') {
            return;
        }

        if ($directEnabled) {
            echo "
    <script>window.__ADS_PIXEL_CONFIG__=" . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";</script>
";
        }

        if ($directEnabled && !empty($config['meta']['enabled'])) {
            $id = analytics_digits_clean((string)$config['meta']['pixel_id'], 32);
            echo "
    <!-- Meta Pixel direct fallback -->
";
            echo "    <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','" . esc($id) . "');fbq('track','PageView');</script>
";
            echo '    <noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=' . esc($id) . '&ev=PageView&noscript=1"></noscript>' . "\n";
        }

        if ($directEnabled && !empty($config['tiktok']['enabled'])) {
            $id = analytics_id_clean((string)$config['tiktok']['pixel_id'], 40);
            echo "
    <!-- TikTok Pixel direct fallback -->
";
            echo "    <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=['page','track','identify','instances','debug','on','off','once','ready','alias','group','enableCookie','disableCookie'];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){var e=ttq._i[t]||[];for(var n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i='https://analytics.tiktok.com/i18n/pixel/events.js';ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e]=+new Date;ttq._o=ttq._o||{};ttq._o[e]=n||{};var o=document.createElement('script');o.type='text/javascript';o.async=!0;o.src=i+'?sdkid='+e+'&lib='+t;var a=document.getElementsByTagName('script')[0];a.parentNode.insertBefore(o,a)};ttq.load('" . esc($id) . "');ttq.page();}(window,document,'ttq');</script>
";
        }

        $googleAdsId = ($directEnabled && !empty($config['google_ads']['enabled'])) ? analytics_google_ads_id_clean((string)$config['google_ads']['conversion_id']) : '';
        $gtagIds = [];
        if ($ga4Id !== '') {
            $gtagIds[] = ['id' => $ga4Id, 'config' => ['send_page_view' => true]];
        }
        if ($googleAdsId !== '') {
            $gtagIds[] = ['id' => $googleAdsId, 'config' => []];
        }
        if ($gtagIds) {
            $firstId = (string)$gtagIds[0]['id'];
            echo "
    <!-- Google gtag direct -->
";
            echo '    <script async src="https://www.googletagmanager.com/gtag/js?id=' . esc($firstId) . '"></script>' . "\n";
            echo "    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());";
            foreach ($gtagIds as $item) {
                $configPayload = !empty($item['config']) ? (array)$item['config'] : new stdClass();
                echo "gtag('config','" . esc((string)$item['id']) . "'," . json_encode($configPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ");";
            }
            echo "</script>
";
        }

        if ($directEnabled && !empty($config['microsoft_uet']['enabled'])) {
            $id = analytics_digits_clean((string)$config['microsoft_uet']['tag_id'], 32);
            echo "
    <!-- Microsoft Ads UET direct fallback -->
";
            echo "    <script>(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[];f=function(){var o={ti:'" . esc($id) . "',enableAutoSpaTracking:true};o.q=w[u];w[u]=new UET(o);w[u].push('pageLoad')};n=d.createElement(t);n.src=r;n.async=1;n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!=='loaded'&&s!=='complete'||(f(),n.onload=n.onreadystatechange=null)};i=d.getElementsByTagName(t)[0];i.parentNode.insertBefore(n,i)})(window,document,'script','//bat.bing.com/bat.js','uetq');</script>
";
        }

        if ($directEnabled && !empty($config['linkedin']['enabled'])) {
            $id = analytics_digits_clean((string)$config['linkedin']['partner_id'], 32);
            echo "
    <!-- LinkedIn Insight direct fallback -->
";
            echo "    <script type=\"text/javascript\">_linkedin_partner_id='" . esc($id) . "';window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];window._linkedin_data_partner_ids.push(_linkedin_partner_id);</script>\n";
            echo "    <script type=\"text/javascript\">(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}var s=document.getElementsByTagName('script')[0];var b=document.createElement('script');b.type='text/javascript';b.async=true;b.src='https://snap.licdn.com/li.lms-analytics/insight.min.js';s.parentNode.insertBefore(b,s);})(window.lintrk);</script>\n";
            echo '    <noscript><img height="1" width="1" style="display:none" alt="" src="https://px.ads.linkedin.com/collect/?pid=' . esc($id) . '&fmt=gif"></noscript>' . "\n";
        }
    }
}

if (!function_exists('analytics_render_head')) {
    function analytics_render_head(): void
    {
        analytics_capture_attribution();
        if (!analytics_is_public_tracking_request()) {
            return;
        }

        $settings = analytics_read_settings();
        if (empty($settings['enabled'])) {
            return;
        }

        $dataLayer = [];
        if (!empty($settings['datalayer_enabled'])) {
            $dataLayer[] = analytics_datalayer_page_payload();
        }

        $gsc = analytics_clean((string)($settings['gsc']['verification'] ?? ''), 160);
        if ($gsc !== '') {
            echo "\n" . '    <meta name="google-site-verification" content="' . esc($gsc) . '">' . "\n";
        }
        analytics_render_custom_meta((string)($settings['custom_meta'] ?? ''));

        if (!empty($settings['datalayer_enabled'])) {
            echo "\n" . '    <script>window.dataLayer=window.dataLayer||[];';
            foreach ($dataLayer as $payload) {
                echo 'window.dataLayer.push(' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
            }
            echo '</script>' . "\n";
        }

        $gtmId = analytics_id_clean((string)($settings['gtm']['container_id'] ?? ''), 40);
        if (!empty($settings['gtm']['enabled']) && $gtmId !== '') {
            echo "\n" . '    <!-- Google Tag Manager -->' . "\n";
            echo "    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc($gtmId) . "');</script>\n";
            echo '    <!-- End Google Tag Manager -->' . "\n";
        }

        $ga4Id = '';
        $rawGa4Id = analytics_id_clean((string)($settings['ga4']['measurement_id'] ?? ''), 40);
        if (!empty($settings['ga4']['enabled']) && $rawGa4Id !== '') {
            $ga4Id = $rawGa4Id;
        }
        analytics_render_direct_pixels($settings, $ga4Id);
    }
}

if (!function_exists('analytics_render_body_noscript')) {
    function analytics_render_body_noscript(): void
    {
        if (!analytics_is_public_tracking_request()) {
            return;
        }
        $settings = analytics_read_settings();
        $gtmId = analytics_id_clean((string)($settings['gtm']['container_id'] ?? ''), 40);
        if (empty($settings['enabled']) || empty($settings['gtm']['enabled']) || $gtmId === '') {
            return;
        }
        echo "\n<!-- Google Tag Manager (noscript) -->\n";
        echo "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=" . esc($gtmId) . "\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
        echo "<!-- End Google Tag Manager (noscript) -->\n";
    }
}

if (!function_exists('analytics_channel_from_item')) {
    function analytics_channel_from_item(array $item): string
    {
        $attr = is_array($item['attribution'] ?? null) ? $item['attribution'] : [];
        $channel = $attr ? analytics_attribution_channel($attr) : '';
        if ($channel !== '') {
            return $channel;
        }

        $source = strtolower((string)($item['utm_source'] ?? ''));
        $medium = strtolower((string)($item['utm_medium'] ?? ''));
        $ref = (string)($item['referrer'] ?? '');
        return analytics_channel_group(['utm_source' => $source, 'utm_medium' => $medium], $ref);
    }
}

if (!function_exists('analytics_channel_dashboard_summary')) {
    function analytics_channel_dashboard_summary(int $days = 30, array $filters = []): array
    {
        $days = $days > 0 ? max(1, min(3650, $days)) : 0;
        $leadEventsRaw = function_exists('conversion_read_lead_events') ? conversion_read_lead_events($days, $filters, 120000) : [];
        $leadEvents = function_exists('conversion_dedupe_lead_events') ? conversion_dedupe_lead_events($leadEventsRaw, 10) : $leadEventsRaw;
        $inquiries = function_exists('inquiry_read_all') ? inquiry_read_all($days, $filters, 50000) : [];
        $orders = function_exists('order_read_all') ? order_read_all($days, $filters, 50000) : [];
        $proofs = function_exists('payment_proof_read_all') ? payment_proof_read_all($days, $filters, 50000) : [];

        $channels = [];
        $ensure = static function (string $channel) use (&$channels): void {
            $channel = $channel !== '' ? $channel : 'direct';
            if (!isset($channels[$channel])) {
                $channels[$channel] = [
                    'channel' => $channel,
                    'label' => analytics_channel_label($channel),
                    'lead_events' => 0,
                    'high_intent' => 0,
                    'support' => 0,
                    'inquiries' => 0,
                    'orders' => 0,
                    'payment_proofs' => 0,
                    'sales_estimate' => 0,
                ];
            }
        };

        foreach ($leadEvents as $event) {
            $channel = analytics_channel_from_item((array)$event);
            $ensure($channel);
            $channels[$channel]['lead_events']++;
            if ((string)($event['_event_kind'] ?? '') === 'high_intent') {
                $channels[$channel]['high_intent']++;
            } else {
                $channels[$channel]['support']++;
            }
        }
        foreach ($inquiries as $item) {
            $channel = analytics_channel_from_item((array)$item);
            $ensure($channel);
            $channels[$channel]['inquiries']++;
        }
        foreach ($orders as $order) {
            $channel = analytics_channel_from_item((array)$order);
            $ensure($channel);
            $channels[$channel]['orders']++;
            $channels[$channel]['sales_estimate'] += function_exists('report_order_value') ? report_order_value((array)$order) : max(0, (int)($order['price'] ?? 0));
        }
        foreach ($proofs as $proof) {
            $channel = analytics_channel_from_item((array)$proof);
            $ensure($channel);
            $channels[$channel]['payment_proofs']++;
        }

        usort($channels, static function (array $a, array $b): int {
            $scoreA = ((int)$a['orders'] * 1000) + ((int)$a['inquiries'] * 100) + ((int)$a['high_intent'] * 10) + (int)$a['lead_events'];
            $scoreB = ((int)$b['orders'] * 1000) + ((int)$b['inquiries'] * 100) + ((int)$b['high_intent'] * 10) + (int)$b['lead_events'];
            return $scoreB <=> $scoreA;
        });

        return [
            'generated_at' => date('c'),
            'days' => $days,
            'settings' => analytics_read_settings(),
            'totals' => [
                'lead_events' => count($leadEvents),
                'lead_events_raw' => count($leadEventsRaw),
                'inquiries' => count($inquiries),
                'orders' => count($orders),
                'payment_proofs' => count($proofs),
            ],
            'channels' => $channels,
        ];
    }
}

if (!function_exists('analytics_export_channel_csv')) {
    function analytics_export_channel_csv(array $summary): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="analytics-channel-summary-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'wb');
        fputcsv($out, ['channel', 'label', 'lead_events', 'high_intent', 'support', 'inquiries', 'orders', 'payment_proofs', 'sales_estimate']);
        foreach ((array)($summary['channels'] ?? []) as $row) {
            fputcsv($out, [
                (string)($row['channel'] ?? ''),
                (string)($row['label'] ?? ''),
                (int)($row['lead_events'] ?? 0),
                (int)($row['high_intent'] ?? 0),
                (int)($row['support'] ?? 0),
                (int)($row['inquiries'] ?? 0),
                (int)($row['orders'] ?? 0),
                (int)($row['payment_proofs'] ?? 0),
                (int)($row['sales_estimate'] ?? 0),
            ]);
        }
        fclose($out);
        exit;
    }
}



if (!function_exists('analytics_count_channels_for_report')) {
    function analytics_count_channels_for_report(array $leadEvents, array $inquiries = [], array $orders = [], int $limit = 10): array
    {
        $counts = [];
        $add = static function (array $item, int $weight = 1) use (&$counts): void {
            $channel = function_exists('analytics_channel_from_item') ? analytics_channel_from_item($item) : 'direct';
            $label = function_exists('analytics_channel_label') ? analytics_channel_label($channel) : ucfirst($channel);
            $counts[$label] = ($counts[$label] ?? 0) + $weight;
        };

        foreach ($leadEvents as $event) {
            $add((array)$event, 1);
        }
        foreach ($inquiries as $item) {
            $add((array)$item, 2);
        }
        foreach ($orders as $order) {
            $add((array)$order, 4);
        }

        arsort($counts);
        return array_slice($counts, 0, max(1, $limit), true);
    }
}
