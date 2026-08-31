<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| LANDING PAGE A/B TESTING - Template
|--------------------------------------------------------------------------
| Lightweight page-level A/B testing for CTA copy/URL and form headline/
| submit button. Configuration is stored inside landing-pages.json so it
| stays shared-hosting friendly and revision-safe.
|--------------------------------------------------------------------------
*/

if (!function_exists('landing_page_ab_clean_text')) {
    function landing_page_ab_clean_text(string $value, int $max = 140): string
    {
        if (function_exists('conversion_clean_text')) {
            return conversion_clean_text($value, $max);
        }
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('landing_page_ab_default_config')) {
    function landing_page_ab_default_config(): array
    {
        return [
            'cta' => [
                'enabled' => false,
                'name' => 'CTA Button Test',
                'variant_a' => ['label' => 'A - Control', 'button_text' => '', 'button_url' => ''],
                'variant_b' => ['label' => 'B - Variant', 'button_text' => '', 'button_url' => ''],
            ],
            'form' => [
                'enabled' => false,
                'name' => 'Form Copy Test',
                'variant_a' => ['label' => 'A - Control', 'headline' => '', 'text' => '', 'submit_text' => '', 'lead_segment' => ''],
                'variant_b' => ['label' => 'B - Variant', 'headline' => '', 'text' => '', 'submit_text' => '', 'lead_segment' => ''],
            ],
        ];
    }
}

if (!function_exists('landing_page_ab_clean_slug')) {
    function landing_page_ab_clean_slug(string $value, string $fallback = ''): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\- ]+/', '', $value) ?: '';
        $value = trim(preg_replace('/\s+/', '-', $value) ?: '', '-_');
        if ($value === '') {
            $value = $fallback;
        }
        return substr($value, 0, 80);
    }
}

if (!function_exists('landing_page_ab_sanitize_variant')) {
    function landing_page_ab_sanitize_variant(mixed $variant, string $type, string $fallbackLabel): array
    {
        $variant = is_array($variant) ? $variant : [];
        $row = ['label' => landing_page_ab_clean_text((string)($variant['label'] ?? $fallbackLabel), 60) ?: $fallbackLabel];
        if ($type === 'cta') {
            $row['button_text'] = landing_page_ab_clean_text((string)($variant['button_text'] ?? ''), 90);
            $row['button_url'] = function_exists('landing_page_clean_url') ? landing_page_clean_url((string)($variant['button_url'] ?? '')) : trim((string)($variant['button_url'] ?? ''));
        } else {
            $row['headline'] = landing_page_ab_clean_text((string)($variant['headline'] ?? ''), 180);
            $row['text'] = landing_page_ab_clean_text((string)($variant['text'] ?? ''), 360);
            $row['submit_text'] = landing_page_ab_clean_text((string)($variant['submit_text'] ?? ''), 90);
            $row['lead_segment'] = landing_page_ab_clean_slug((string)($variant['lead_segment'] ?? ''));
        }
        return $row;
    }
}

if (!function_exists('landing_page_ab_sanitize_config')) {
    function landing_page_ab_sanitize_config(mixed $config): array
    {
        $config = is_array($config) ? $config : [];
        $defaults = landing_page_ab_default_config();
        $clean = [];
        foreach (['cta', 'form'] as $type) {
            $src = is_array($config[$type] ?? null) ? (array)$config[$type] : [];
            $clean[$type] = [
                'enabled' => !empty($src['enabled']),
                'name' => landing_page_ab_clean_text((string)($src['name'] ?? $defaults[$type]['name']), 90) ?: $defaults[$type]['name'],
                'variant_a' => landing_page_ab_sanitize_variant($src['variant_a'] ?? [], $type, 'A - Control'),
                'variant_b' => landing_page_ab_sanitize_variant($src['variant_b'] ?? [], $type, 'B - Variant'),
            ];
        }
        return $clean;
    }
}

if (!function_exists('landing_page_ab_config_from_post')) {
    function landing_page_ab_config_from_post(array $post): array
    {
        return landing_page_ab_sanitize_config([
            'cta' => [
                'enabled' => !empty($post['ab_cta_enabled']),
                'name' => (string)($post['ab_cta_name'] ?? 'CTA Button Test'),
                'variant_a' => ['label' => (string)($post['ab_cta_a_label'] ?? 'A - Control'), 'button_text' => (string)($post['ab_cta_a_text'] ?? ''), 'button_url' => (string)($post['ab_cta_a_url'] ?? '')],
                'variant_b' => ['label' => (string)($post['ab_cta_b_label'] ?? 'B - Variant'), 'button_text' => (string)($post['ab_cta_b_text'] ?? ''), 'button_url' => (string)($post['ab_cta_b_url'] ?? '')],
            ],
            'form' => [
                'enabled' => !empty($post['ab_form_enabled']),
                'name' => (string)($post['ab_form_name'] ?? 'Form Copy Test'),
                'variant_a' => ['label' => (string)($post['ab_form_a_label'] ?? 'A - Control'), 'headline' => (string)($post['ab_form_a_headline'] ?? ''), 'text' => (string)($post['ab_form_a_text'] ?? ''), 'submit_text' => (string)($post['ab_form_a_submit'] ?? ''), 'lead_segment' => (string)($post['ab_form_a_segment'] ?? '')],
                'variant_b' => ['label' => (string)($post['ab_form_b_label'] ?? 'B - Variant'), 'headline' => (string)($post['ab_form_b_headline'] ?? ''), 'text' => (string)($post['ab_form_b_text'] ?? ''), 'submit_text' => (string)($post['ab_form_b_submit'] ?? ''), 'lead_segment' => (string)($post['ab_form_b_segment'] ?? '')],
            ],
        ]);
    }
}

if (!function_exists('landing_page_ab_has_active')) {
    function landing_page_ab_has_active(array $page): bool
    {
        $cfg = landing_page_ab_sanitize_config($page['ab_tests'] ?? []);
        return !empty($cfg['cta']['enabled']) || !empty($cfg['form']['enabled']);
    }
}

if (!function_exists('landing_page_ab_cookie_name')) {
    function landing_page_ab_cookie_name(array $page, string $type): string
    {
        $id = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($page['id'] ?? $page['slug'] ?? 'lp')) ?: 'lp';
        return 'lp_ab_' . substr(md5($id), 0, 12) . '_' . $type;
    }
}

if (!function_exists('landing_page_ab_pick_variant')) {
    function landing_page_ab_pick_variant(array $page, string $type): string
    {
        $type = in_array($type, ['cta', 'form'], true) ? $type : 'cta';
        $queryKey = $type === 'cta' ? 'ab_cta' : 'ab_form';
        $forced = strtolower(trim((string)($_GET[$queryKey] ?? '')));
        if (in_array($forced, ['a', 'b'], true)) {
            return $forced;
        }
        $cookie = landing_page_ab_cookie_name($page, $type);
        $saved = strtolower(trim((string)($_COOKIE[$cookie] ?? '')));
        if (in_array($saved, ['a', 'b'], true)) {
            return $saved;
        }
        try {
            $variant = random_int(0, 1) === 1 ? 'b' : 'a';
        } catch (Throwable) {
            $seed = (string)($page['id'] ?? $page['slug'] ?? '') . '|' . (string)($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string)($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $type;
            $variant = (crc32($seed) % 2) === 1 ? 'b' : 'a';
        }
        if (!headers_sent()) {
            setcookie($cookie, $variant, ['expires' => time() + 60 * 60 * 24 * 30, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => false, 'samesite' => 'Lax']);
        }
        return $variant;
    }
}

if (!function_exists('landing_page_ab_context')) {
    function landing_page_ab_context(array $page): array
    {
        $cfg = landing_page_ab_sanitize_config($page['ab_tests'] ?? []);
        $context = ['active' => false, 'cta' => null, 'form' => null];
        foreach (['cta', 'form'] as $type) {
            if (empty($cfg[$type]['enabled'])) {
                continue;
            }
            $variantKey = landing_page_ab_pick_variant($page, $type);
            $variant = (array)($cfg[$type][$variantKey === 'b' ? 'variant_b' : 'variant_a'] ?? []);
            $context[$type] = [
                'test_id' => landing_page_ab_clean_slug((string)($page['slug'] ?? 'lp') . '-' . $type),
                'test_name' => (string)($cfg[$type]['name'] ?? strtoupper($type) . ' Test'),
                'variant' => $variantKey,
                'variant_label' => (string)($variant['label'] ?? strtoupper($variantKey)),
                'variant_data' => $variant,
            ];
            $context['active'] = true;
        }
        return $context;
    }
}

if (!function_exists('landing_page_ab_prepare_public_page')) {
    function landing_page_ab_prepare_public_page(array $page): array
    {
        $context = landing_page_ab_context($page);
        $GLOBALS['landing_page_ab_context'] = $context;
        $page['_ab_context'] = $context;
        return $page;
    }
}

if (!function_exists('landing_page_ab_current')) {
    function landing_page_ab_current(string $type): ?array
    {
        $ctx = is_array($GLOBALS['landing_page_ab_context'] ?? null) ? (array)$GLOBALS['landing_page_ab_context'] : [];
        return is_array($ctx[$type] ?? null) ? (array)$ctx[$type] : null;
    }
}

if (!function_exists('landing_page_ab_apply_cta')) {
    function landing_page_ab_apply_cta(string $text, string $url): array
    {
        $ctx = landing_page_ab_current('cta');
        if (!$ctx) {
            return [$text, $url];
        }
        $variant = is_array($ctx['variant_data'] ?? null) ? (array)$ctx['variant_data'] : [];
        $newText = landing_page_ab_clean_text((string)($variant['button_text'] ?? ''), 90);
        $newUrl = function_exists('landing_page_clean_url') ? landing_page_clean_url((string)($variant['button_url'] ?? '')) : trim((string)($variant['button_url'] ?? ''));
        return [$newText !== '' ? $newText : $text, $newUrl !== '' ? $newUrl : $url];
    }
}

if (!function_exists('landing_page_ab_apply_form_block')) {
    function landing_page_ab_apply_form_block(array $block): array
    {
        $ctx = landing_page_ab_current('form');
        if (!$ctx) {
            return $block;
        }
        $variant = is_array($ctx['variant_data'] ?? null) ? (array)$ctx['variant_data'] : [];
        foreach (['headline', 'text', 'submit_text'] as $key) {
            $value = landing_page_ab_clean_text((string)($variant[$key] ?? ''), $key === 'headline' ? 180 : 360);
            if ($value !== '') {
                $block[$key] = $value;
            }
        }
        $segment = landing_page_ab_clean_slug((string)($variant['lead_segment'] ?? ''));
        if ($segment !== '') {
            $block['lead_segment'] = $segment;
        }
        return $block;
    }
}

if (!function_exists('landing_page_ab_attrs')) {
    function landing_page_ab_attrs(string $type): string
    {
        $ctx = landing_page_ab_current($type);
        if (!$ctx) {
            return '';
        }
        $attrs = [
            'data-ab-test-type' => $type,
            'data-ab-test-id' => (string)($ctx['test_id'] ?? ''),
            'data-ab-test-name' => (string)($ctx['test_name'] ?? ''),
            'data-ab-variant' => (string)($ctx['variant'] ?? ''),
            'data-ab-variant-label' => (string)($ctx['variant_label'] ?? ''),
        ];
        $html = [];
        foreach ($attrs as $key => $value) {
            if ($value !== '') {
                $html[] = $key . '="' . esc($value) . '"';
            }
        }
        return $html ? ' ' . implode(' ', $html) : '';
    }
}

if (!function_exists('landing_page_ab_hidden_inputs')) {
    function landing_page_ab_hidden_inputs(string $type): string
    {
        $ctx = landing_page_ab_current($type);
        if (!$ctx) {
            return '';
        }
        $fields = [
            'ab_test_type' => $type,
            'ab_test_id' => (string)($ctx['test_id'] ?? ''),
            'ab_test_name' => (string)($ctx['test_name'] ?? ''),
            'ab_variant' => (string)($ctx['variant'] ?? ''),
            'ab_variant_label' => (string)($ctx['variant_label'] ?? ''),
        ];
        $html = '';
        foreach ($fields as $name => $value) {
            if ($value !== '') {
                $html .= '<input type="hidden" name="' . esc($name) . '" value="' . esc($value) . '">';
            }
        }
        return $html;
    }
}

if (!function_exists('landing_page_ab_page_event_payload')) {
    function landing_page_ab_page_event_payload(): array
    {
        $ctx = is_array($GLOBALS['landing_page_ab_context'] ?? null) ? (array)$GLOBALS['landing_page_ab_context'] : [];
        $payload = [];
        foreach (['cta', 'form'] as $type) {
            if (!is_array($ctx[$type] ?? null)) {
                continue;
            }
            $payload['ab_' . $type . '_test_id'] = (string)($ctx[$type]['test_id'] ?? '');
            $payload['ab_' . $type . '_test_name'] = (string)($ctx[$type]['test_name'] ?? '');
            $payload['ab_' . $type . '_variant'] = (string)($ctx[$type]['variant'] ?? '');
            $payload['ab_' . $type . '_variant_label'] = (string)($ctx[$type]['variant_label'] ?? '');
        }
        return $payload;
    }
}

if (!function_exists('landing_page_ab_breakdown_row')) {
    function landing_page_ab_breakdown_row(string $testType, string $variant, string $label = ''): array
    {
        return [
            'test_type' => $testType,
            'variant' => $variant,
            'variant_label' => $label !== '' ? $label : strtoupper($variant),
            'page_view' => 0,
            'cta_click' => 0,
            'form_submit' => 0,
            'inquiry' => 0,
            'order' => 0,
            'lead_total' => 0,
            'conversions' => 0,
            'conversion_rate' => 0.0,
            'cta_rate' => 0.0,
            'latest_at' => '',
        ];
    }
}

if (!function_exists('landing_page_analytics_ab_add')) {
    function landing_page_analytics_ab_add(array &$breakdown, array $item, string $kind, string $slug): void
    {
        $pairs = [];
        $genericType = landing_page_ab_clean_slug((string)($item['ab_test_type'] ?? ''));
        $genericVariant = strtolower(trim((string)($item['ab_variant'] ?? '')));
        if (in_array($genericVariant, ['a', 'b'], true)) {
            $pairs[] = [
                $genericType !== '' ? $genericType : 'form',
                $genericVariant,
                (string)($item['ab_variant_label'] ?? ''),
                landing_page_ab_clean_slug((string)($item['ab_test_id'] ?? '')),
                landing_page_ab_clean_text((string)($item['ab_test_name'] ?? ''), 100),
            ];
        }

        foreach (['cta', 'form'] as $type) {
            $variant = strtolower(trim((string)($item['ab_' . $type . '_variant'] ?? '')));
            if (in_array($variant, ['a', 'b'], true)) {
                $pairs[] = [
                    $type,
                    $variant,
                    (string)($item['ab_' . $type . '_variant_label'] ?? ''),
                    landing_page_ab_clean_slug((string)($item['ab_' . $type . '_test_id'] ?? '')),
                    landing_page_ab_clean_text((string)($item['ab_' . $type . '_test_name'] ?? $item['ab_test_name'] ?? ''), 100),
                ];
            }
        }

        foreach ($pairs as [$type, $variant, $label, $testId, $testName]) {
            $key = $slug . '|' . $type . '|' . $variant;
            if (!isset($breakdown[$key])) {
                $breakdown[$key] = landing_page_ab_breakdown_row($type, $variant, $label);
                $breakdown[$key]['slug'] = $slug;
                $breakdown[$key]['test_id'] = $testId !== '' ? $testId : landing_page_ab_clean_slug($slug . '-' . $type);
                $breakdown[$key]['test_name'] = $testName !== '' ? $testName : strtoupper($type) . ' Test';
            }
            if (function_exists('landing_page_analytics_add_metric')) {
                landing_page_analytics_add_metric($breakdown[$key], $kind);
            } elseif (isset($breakdown[$key][$kind])) {
                $breakdown[$key][$kind]++;
            }
            if ($label !== '') {
                $breakdown[$key]['variant_label'] = $label;
            }
            if ($testId !== '') {
                $breakdown[$key]['test_id'] = $testId;
            }
            if ($testName !== '') {
                $breakdown[$key]['test_name'] = $testName;
            }
            $time = (string)($item['time'] ?? '');
            if ($time !== '' && strcmp($time, (string)($breakdown[$key]['latest_at'] ?? '')) > 0) {
                $breakdown[$key]['latest_at'] = $time;
            }
        }
    }
}

if (!function_exists('landing_page_analytics_ab_finalize')) {
    function landing_page_analytics_ab_finalize(array $breakdown): array
    {
        $rows = [];
        foreach ($breakdown as $row) {
            if (function_exists('landing_page_analytics_finalize_metrics')) {
                $row = landing_page_analytics_finalize_metrics($row);
            } else {
                $views = max(0, (int)($row['page_view'] ?? 0));
                $leadTotal = (int)($row['form_submit'] ?? 0) + (int)($row['inquiry'] ?? 0);
                $row['lead_total'] = $leadTotal;
                $row['conversions'] = $leadTotal + (int)($row['order'] ?? 0);
                $row['conversion_rate'] = $views > 0 ? round(((int)$row['conversions'] / $views) * 100, 2) : 0.0;
                $row['cta_rate'] = $views > 0 ? round(((int)($row['cta_click'] ?? 0) / $views) * 100, 2) : 0.0;
            }
            $row['score'] = (int)($row['page_view'] ?? 0) + (int)($row['cta_click'] ?? 0) * 2 + (int)($row['lead_total'] ?? 0) * 5 + (int)($row['order'] ?? 0) * 12;
            $rows[] = $row;
        }
        usort($rows, static fn(array $a, array $b): int => ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0)));
        return $rows;
    }
}
