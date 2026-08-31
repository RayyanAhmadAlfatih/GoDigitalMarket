<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CTA RESULT TRACKER & LEAD TRACKING BRIDGE
|--------------------------------------------------------------------------
| Reads existing Lead Tracking logs and connects them with Offer Lab + CTA
| Placement. This module does not create a second tracking system.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('cta_result_clean')) {
    function cta_result_clean(mixed $value, int $max = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('cta_result_id')) {
    function cta_result_id(string $value = ''): string
    {
        if (function_exists('cta_placement_id')) {
            return cta_placement_id($value);
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]+/', '-', $value) ?: '';
        $value = trim($value, '-');

        return substr($value, 0, 120);
    }
}

if (!function_exists('cta_result_storage_file')) {
    function cta_result_storage_file(): string
    {
        return STORAGE_PATH . '/cta-result-tracker-decisions.json';
    }
}

if (!function_exists('cta_result_decision_options')) {
    function cta_result_decision_options(): array
    {
        return [
            'monitor' => 'Pantau dulu',
            'keep' => 'Lanjutkan',
            'scale' => 'Scale / perbanyak placement',
            'improve' => 'Perbaiki copy/offer',
            'replace' => 'Ganti CTA',
        ];
    }
}

if (!function_exists('cta_result_default_settings')) {
    function cta_result_default_settings(): array
    {
        return [
            'decisions' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('cta_result_normalize_decision')) {
    function cta_result_normalize_decision(array $decision): array
    {
        $options = cta_result_decision_options();
        $status = (string)($decision['status'] ?? 'monitor');
        if (!isset($options[$status])) {
            $status = 'monitor';
        }

        return [
            'deployment_id' => cta_result_id((string)($decision['deployment_id'] ?? '')),
            'status' => $status,
            'note' => cta_result_clean($decision['note'] ?? '', 360),
            'updated_at' => cta_result_clean($decision['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('cta_result_normalize_settings')) {
    function cta_result_normalize_settings(array $settings): array
    {
        $defaults = cta_result_default_settings();
        $settings = array_merge($defaults, $settings);
        $decisions = [];
        foreach ((array)($settings['decisions'] ?? []) as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $normalized = cta_result_normalize_decision($decision);
            $id = (string)$normalized['deployment_id'];
            if ($id === '') {
                continue;
            }
            $decisions[$id] = $normalized;
        }

        return [
            'decisions' => $decisions,
            'updated_at' => cta_result_clean($settings['updated_at'] ?? date(DATE_ATOM), 80),
        ];
    }
}

if (!function_exists('cta_result_settings')) {
    function cta_result_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = cta_result_storage_file();
        if (!is_file($file)) {
            $cached = cta_result_normalize_settings(cta_result_default_settings());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = cta_result_normalize_settings(cta_result_default_settings());
            return $cached;
        }

        $cached = cta_result_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('cta_result_write_settings')) {
    function cta_result_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = cta_result_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(cta_result_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Keputusan CTA Result Tracker belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(cta_result_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'cta-result-tracker', null, 'Menyimpan keputusan hasil CTA dari Lead Tracking Bridge.');
        }

        return true;
    }
}

if (!function_exists('cta_result_update_decision')) {
    function cta_result_update_decision(string $deploymentId, string $status, string $note = ''): bool
    {
        $deploymentId = cta_result_id($deploymentId);
        if ($deploymentId === '') {
            throw new RuntimeException('ID deployment tidak valid.');
        }

        $settings = cta_result_settings(true);
        $settings['decisions'][$deploymentId] = cta_result_normalize_decision([
            'deployment_id' => $deploymentId,
            'status' => $status,
            'note' => $note,
            'updated_at' => date(DATE_ATOM),
        ]);

        return cta_result_write_settings($settings, true);
    }
}

if (!function_exists('cta_result_reset_decisions')) {
    function cta_result_reset_decisions(): void
    {
        if (is_file(cta_result_storage_file())) {
            @unlink(cta_result_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'cta-result-tracker', null, 'Reset keputusan CTA Result Tracker.');
        }
    }
}

if (!function_exists('cta_result_path')) {
    function cta_result_path(string $url): string
    {
        $path = trim((string)(parse_url($url, PHP_URL_PATH) ?: $url));
        if ($path === '') {
            return '/';
        }
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}

if (!function_exists('cta_result_contains')) {
    function cta_result_contains(string $haystack, string $needle): bool
    {
        $haystack = strtolower(trim($haystack));
        $needle = strtolower(trim($needle));
        return $needle !== '' && $haystack !== '' && str_contains($haystack, $needle);
    }
}

if (!function_exists('cta_result_event_blob')) {
    function cta_result_event_blob(array $event): string
    {
        return strtolower(implode(' ', array_map(static fn($value): string => is_scalar($value) ? (string)$value : '', [
            $event['source'] ?? '',
            $event['type'] ?? '',
            $event['channel'] ?? '',
            $event['category'] ?? '',
            $event['location'] ?? '',
            $event['intent'] ?? '',
            $event['label'] ?? '',
            $event['page_path'] ?? '',
            $event['target_url'] ?? '',
            $event['cta_deployment_id'] ?? '',
            $event['offer_variant_id'] ?? '',
            $event['cta_placement'] ?? '',
        ])));
    }
}

if (!function_exists('cta_result_event_kind')) {
    function cta_result_event_kind(array $event): string
    {
        $group = (string)($event['_event_group'] ?? (function_exists('conversion_event_group') ? conversion_event_group($event) : 'interaction'));
        $channel = strtolower((string)($event['channel'] ?? ''));

        if (in_array($group, ['order', 'payment'], true)) {
            return 'order';
        }
        if (in_array($group, ['inquiry', 'conversion'], true) || $channel === 'form' || $channel === 'whatsapp' || !empty($event['is_whatsapp'])) {
            return 'lead';
        }
        if ($group === 'page_view') {
            return 'view';
        }

        return 'click';
    }
}

if (!function_exists('cta_result_deployment_match_score')) {
    function cta_result_deployment_match_score(array $deployment, array $event): int
    {
        $score = 0;
        $blob = cta_result_event_blob($event);
        $deploymentId = cta_result_id((string)($deployment['id'] ?? ''));
        $variantId = cta_result_id((string)($deployment['variant_id'] ?? ''));
        $eventDeploymentId = cta_result_id((string)($event['cta_deployment_id'] ?? ''));
        $eventVariantId = cta_result_id((string)($event['offer_variant_id'] ?? ''));
        $eventPlacement = cta_result_id((string)($event['cta_placement'] ?? ''));
        $placement = cta_result_id((string)($deployment['placement'] ?? ''));

        if ($deploymentId !== '' && $eventDeploymentId !== '' && $deploymentId === $eventDeploymentId) {
            $score += 100;
        }
        if ($variantId !== '' && $eventVariantId !== '' && $variantId === $eventVariantId) {
            $score += 80;
        }
        if ($placement !== '' && $eventPlacement !== '' && $placement === $eventPlacement) {
            $score += 45;
        }

        $ctaLabel = strtolower(cta_result_clean($deployment['cta_label'] ?? '', 90));
        $headline = strtolower(cta_result_clean($deployment['headline'] ?? '', 120));
        $variantTitle = strtolower(cta_result_clean($deployment['variant_title'] ?? '', 120));
        if ($ctaLabel !== '' && cta_result_contains($blob, $ctaLabel)) {
            $score += 25;
        }
        if ($headline !== '' && strlen($headline) >= 12 && cta_result_contains($blob, $headline)) {
            $score += 20;
        }
        if ($variantTitle !== '' && strlen($variantTitle) >= 6 && cta_result_contains($blob, $variantTitle)) {
            $score += 12;
        }

        $eventTarget = cta_result_path((string)($event['target_url'] ?? ''));
        $eventPage = cta_result_path((string)($event['page_path'] ?? ''));
        $deploymentCta = cta_result_path((string)($deployment['cta_url'] ?? ''));
        $deploymentTarget = cta_result_path((string)($deployment['target_url'] ?? ''));

        if ($deploymentCta !== '/' && $eventTarget === $deploymentCta) {
            $score += 30;
        }
        if ($deploymentTarget !== '/' && ($eventPage === $deploymentTarget || str_starts_with($eventPage, $deploymentTarget . '/'))) {
            $score += 28;
        }
        if ($deploymentTarget === '/' && $eventPage === '/') {
            $score += 22;
        }

        $placementMap = [
            'homepage_hero' => ['homepage', 'hero', '/'],
            'homepage_mid' => ['homepage', 'beranda', '/'],
            'homepage_bottom' => ['homepage', 'form-konsultasi', 'homepage-form'],
            'article_inline' => ['artikel', '/artikel', 'blog'],
            'landing_page' => ['landing', 'lp', '/landing'],
            'product_detail' => ['produk', 'katalog', '/produk', '/katalog'],
            'trust_block' => ['trust', 'testimoni', 'faq', 'garansi'],
            'form_page' => ['form', 'lead magnet', 'kontak'],
            'follow_up' => ['follow', 'crm', 'whatsapp'],
            'campaign_playbook' => ['campaign', 'playbook'],
        ];

        foreach (($placementMap[$placement] ?? []) as $hint) {
            if ($hint === '/') {
                if ($eventPage === '/') {
                    $score += 8;
                }
                continue;
            }
            if (cta_result_contains($blob, (string)$hint)) {
                $score += 8;
                break;
            }
        }

        return min(100, $score);
    }
}

if (!function_exists('cta_result_empty_metrics')) {
    function cta_result_empty_metrics(): array
    {
        return [
            'events' => 0,
            'views' => 0,
            'clicks' => 0,
            'leads' => 0,
            'orders' => 0,
            'last_event_at' => '',
            'matched_score_total' => 0,
            'recent_events' => [],
            'by_channel' => [],
            'by_page' => [],
        ];
    }
}

if (!function_exists('cta_result_register_metric')) {
    function cta_result_register_metric(array $metrics, array $event, int $matchScore): array
    {
        $kind = cta_result_event_kind($event);
        $metrics['events']++;
        $metrics['matched_score_total'] += $matchScore;

        if ($kind === 'view') {
            $metrics['views']++;
        } elseif ($kind === 'order') {
            $metrics['orders']++;
            $metrics['leads']++;
            $metrics['clicks']++;
        } elseif ($kind === 'lead') {
            $metrics['leads']++;
            $metrics['clicks']++;
        } else {
            $metrics['clicks']++;
        }

        $ts = (int)($event['_ts'] ?? (function_exists('conversion_event_timestamp') ? conversion_event_timestamp($event) : 0));
        if ($ts > 0 && ((string)($metrics['last_event_at'] ?? '') === '' || $ts > (int)strtotime((string)$metrics['last_event_at']))) {
            $metrics['last_event_at'] = date(DATE_ATOM, $ts);
        }

        $channel = cta_result_clean($event['channel'] ?? 'Tidak diketahui', 80) ?: 'Tidak diketahui';
        $page = cta_result_path((string)($event['page_path'] ?? '/'));
        $metrics['by_channel'][$channel] = (int)($metrics['by_channel'][$channel] ?? 0) + 1;
        $metrics['by_page'][$page] = (int)($metrics['by_page'][$page] ?? 0) + 1;

        if (count($metrics['recent_events']) < 6) {
            $metrics['recent_events'][] = $event + ['_match_score' => $matchScore, '_cta_result_kind' => $kind];
        }

        return $metrics;
    }
}

if (!function_exists('cta_result_rates')) {
    function cta_result_rates(array $metrics): array
    {
        $clicks = max(0, (int)($metrics['clicks'] ?? 0));
        $leads = max(0, (int)($metrics['leads'] ?? 0));
        $orders = max(0, (int)($metrics['orders'] ?? 0));

        return [
            'lead_rate' => $clicks > 0 ? round(($leads / $clicks) * 100, 1) : 0.0,
            'order_rate' => $clicks > 0 ? round(($orders / $clicks) * 100, 1) : 0.0,
        ];
    }
}

if (!function_exists('cta_result_result_score')) {
    function cta_result_result_score(array $metrics, array $deployment): int
    {
        $rates = cta_result_rates($metrics);
        $score = 0;
        $score += min(20, (int)($metrics['clicks'] ?? 0) * 3);
        $score += min(35, (int)($metrics['leads'] ?? 0) * 12);
        $score += min(30, (int)($metrics['orders'] ?? 0) * 18);
        $score += min(10, (int)floor((float)$rates['lead_rate'] / 2));
        $score += (string)($deployment['status'] ?? '') === 'deployed' || (string)($deployment['status'] ?? '') === 'monitoring' ? 5 : 0;

        $lastTs = strtotime((string)($metrics['last_event_at'] ?? '')) ?: 0;
        if ($lastTs > 0 && $lastTs >= strtotime('-7 days')) {
            $score += 8;
        }

        return max(0, min(100, $score));
    }
}

if (!function_exists('cta_result_recommendation')) {
    function cta_result_recommendation(array $metrics, array $deployment): array
    {
        $events = (int)($metrics['events'] ?? 0);
        $clicks = (int)($metrics['clicks'] ?? 0);
        $leads = (int)($metrics['leads'] ?? 0);
        $orders = (int)($metrics['orders'] ?? 0);
        $status = (string)($deployment['status'] ?? 'planned');
        $rates = cta_result_rates($metrics);

        if ($orders > 0) {
            return ['tone' => 'scale', 'title' => 'Layak di-scale', 'text' => 'CTA ini sudah punya sinyal order/payment. Pertahankan dan coba pasang ke area sejenis.'];
        }
        if ($leads >= 2 || (float)$rates['lead_rate'] >= 20.0) {
            return ['tone' => 'keep', 'title' => 'Pertahankan dan pantau', 'text' => 'CTA ini mulai menghasilkan lead. Jangan buru-buru diganti; pantau beberapa hari lagi.'];
        }
        if ($clicks >= 5 && $leads === 0) {
            return ['tone' => 'improve', 'title' => 'Perbaiki offer atau form', 'text' => 'Ada klik, tapi belum jadi lead. Coba perkuat proof, headline, atau sederhanakan langkah berikutnya.'];
        }
        if ($events === 0 && in_array($status, ['deployed', 'monitoring'], true)) {
            return ['tone' => 'check', 'title' => 'Cek tracking atau traffic', 'text' => 'CTA sudah ditandai terpasang, tapi belum terbaca di Lead Tracking. Cek halaman, link, atau tunggu traffic masuk.'];
        }
        if ($events === 0) {
            return ['tone' => 'monitor', 'title' => 'Belum ada sinyal', 'text' => 'Pasang CTA atau ubah status ke monitoring setelah placement benar-benar live.'];
        }

        return ['tone' => 'monitor', 'title' => 'Pantau dulu', 'text' => 'Sinyal awal sudah ada, tapi belum cukup untuk mengambil keputusan besar.'];
    }
}

if (!function_exists('cta_result_find_variant')) {
    function cta_result_find_variant(string $variantId): ?array
    {
        $variantId = cta_result_id($variantId);
        if ($variantId === '' || !function_exists('offer_cta_lab_settings')) {
            return null;
        }

        $settings = offer_cta_lab_settings(true);
        foreach ((array)($settings['variants'] ?? []) as $variant) {
            if (is_array($variant) && (string)($variant['id'] ?? '') === $variantId) {
                return $variant;
            }
        }

        return null;
    }
}

if (!function_exists('cta_result_analyze_deployment')) {
    function cta_result_analyze_deployment(array $deployment, array $events, array $decision = []): array
    {
        $metrics = cta_result_empty_metrics();
        foreach ($events as $event) {
            $matchScore = cta_result_deployment_match_score($deployment, $event);
            if ($matchScore < 35) {
                continue;
            }
            $metrics = cta_result_register_metric($metrics, $event, $matchScore);
        }

        arsort($metrics['by_channel']);
        arsort($metrics['by_page']);
        $rates = cta_result_rates($metrics);
        $score = cta_result_result_score($metrics, $deployment);
        $recommendation = cta_result_recommendation($metrics, $deployment);
        $variant = cta_result_find_variant((string)($deployment['variant_id'] ?? ''));

        return [
            'deployment' => $deployment,
            'variant' => $variant,
            'metrics' => $metrics + $rates,
            'result_score' => $score,
            'recommendation' => $recommendation,
            'decision' => $decision,
        ];
    }
}

if (!function_exists('cta_result_unmatched_opportunities')) {
    function cta_result_unmatched_opportunities(array $events, array $deploymentResults, int $limit = 8): array
    {
        $matched = [];
        foreach ($deploymentResults as $result) {
            foreach ((array)($result['metrics']['recent_events'] ?? []) as $event) {
                $matched[(string)($event['event_id'] ?? '') . '|' . (string)($event['_ts'] ?? '') . '|' . (string)($event['label'] ?? '')] = true;
            }
        }

        $groups = [];
        foreach ($events as $event) {
            $kind = cta_result_event_kind($event);
            if (!in_array($kind, ['lead', 'order', 'click'], true)) {
                continue;
            }

            $wasMatched = false;
            foreach ($deploymentResults as $result) {
                if (cta_result_deployment_match_score((array)($result['deployment'] ?? []), $event) >= 35) {
                    $wasMatched = true;
                    break;
                }
            }
            if ($wasMatched) {
                continue;
            }

            $page = cta_result_path((string)($event['page_path'] ?? '/'));
            $label = cta_result_clean($event['label'] ?? 'CTA', 90) ?: 'CTA';
            $key = $page . '|' . strtolower($label);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'page_path' => $page,
                    'label' => $label,
                    'channel' => cta_result_clean($event['channel'] ?? 'click', 60),
                    'events' => 0,
                    'leads' => 0,
                    'orders' => 0,
                    'last_event_at' => '',
                ];
            }
            $groups[$key]['events']++;
            if ($kind === 'lead') {
                $groups[$key]['leads']++;
            }
            if ($kind === 'order') {
                $groups[$key]['orders']++;
            }
            $ts = (int)($event['_ts'] ?? 0);
            if ($ts > 0 && ((string)$groups[$key]['last_event_at'] === '' || $ts > (int)strtotime((string)$groups[$key]['last_event_at']))) {
                $groups[$key]['last_event_at'] = date(DATE_ATOM, $ts);
            }
        }

        $items = array_values($groups);
        usort($items, static function (array $a, array $b): int {
            $as = ((int)$a['orders'] * 5) + ((int)$a['leads'] * 3) + (int)$a['events'];
            $bs = ((int)$b['orders'] * 5) + ((int)$b['leads'] * 3) + (int)$b['events'];
            return ($bs <=> $as) ?: strcmp((string)$a['page_path'], (string)$b['page_path']);
        });

        return array_slice($items, 0, max(1, $limit));
    }
}

if (!function_exists('cta_result_bridge_summary')) {
    function cta_result_bridge_summary(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $leadTrackingAvailable = function_exists('conversion_read_lead_events');
        $placementAvailable = function_exists('cta_placement_settings');
        $offerAvailable = function_exists('offer_cta_lab_settings');
        $trackingEnabled = function_exists('conversion_tracking_enabled') ? conversion_tracking_enabled() : false;

        $events = [];
        if ($leadTrackingAvailable) {
            $events = conversion_dedupe_lead_events(conversion_read_lead_events($days, [], 200000), 10);
        }

        $placementSettings = $placementAvailable ? cta_placement_settings(true) : [];
        $deployments = array_values((array)($placementSettings['deployments'] ?? []));
        $decisions = (array)(cta_result_settings(true)['decisions'] ?? []);
        $deploymentResults = [];

        foreach ($deployments as $deployment) {
            if (!is_array($deployment)) {
                continue;
            }
            $id = (string)($deployment['id'] ?? '');
            $deploymentResults[] = cta_result_analyze_deployment($deployment, $events, (array)($decisions[$id] ?? []));
        }

        usort($deploymentResults, static function (array $a, array $b): int {
            return ((int)($b['result_score'] ?? 0) <=> (int)($a['result_score'] ?? 0))
                ?: ((int)($b['metrics']['events'] ?? 0) <=> (int)($a['metrics']['events'] ?? 0));
        });

        $totalClicks = 0;
        $totalLeads = 0;
        $totalOrders = 0;
        $withSignal = 0;
        $needsAction = 0;
        $scaleReady = 0;
        $bridgeScore = 0;

        foreach ($deploymentResults as $result) {
            $metrics = (array)($result['metrics'] ?? []);
            $totalClicks += (int)($metrics['clicks'] ?? 0);
            $totalLeads += (int)($metrics['leads'] ?? 0);
            $totalOrders += (int)($metrics['orders'] ?? 0);
            if ((int)($metrics['events'] ?? 0) > 0) {
                $withSignal++;
            }
            $tone = (string)($result['recommendation']['tone'] ?? 'monitor');
            if (in_array($tone, ['improve', 'replace', 'check'], true)) {
                $needsAction++;
            }
            if (in_array($tone, ['scale', 'keep'], true)) {
                $scaleReady++;
            }
            $bridgeScore += (int)($result['result_score'] ?? 0);
        }

        $bridgeScore = $deploymentResults ? (int)round($bridgeScore / count($deploymentResults)) : 0;
        $unmatched = cta_result_unmatched_opportunities($events, $deploymentResults, 8);

        $topFocus = 'Pasang minimal satu winner CTA lalu pantau lewat Lead Tracking.';
        if (!$trackingEnabled) {
            $topFocus = 'Aktifkan Lead Tracking agar hasil CTA bisa terbaca.';
        } elseif (!$deployments) {
            $topFocus = 'Buat rencana CTA Placement dari Offer Lab dulu.';
        } elseif ($needsAction > 0) {
            $topFocus = 'Perbaiki CTA yang punya klik tapi belum menghasilkan lead/order.';
        } elseif ($scaleReady > 0) {
            $topFocus = 'Ada CTA yang layak dipertahankan atau di-scale ke area lain.';
        } elseif ($withSignal === 0) {
            $topFocus = 'CTA sudah direncanakan, sekarang pastikan placement live dan tracking masuk.';
        }

        return [
            'days' => $days,
            'tracking_enabled' => $trackingEnabled,
            'lead_tracking_available' => $leadTrackingAvailable,
            'placement_available' => $placementAvailable,
            'offer_available' => $offerAvailable,
            'total_events' => count($events),
            'total_deployments' => count($deployments),
            'deployments_with_signal' => $withSignal,
            'total_clicks' => $totalClicks,
            'total_leads' => $totalLeads,
            'total_orders' => $totalOrders,
            'needs_action' => $needsAction,
            'scale_ready' => $scaleReady,
            'bridge_score' => $bridgeScore,
            'lead_rate' => $totalClicks > 0 ? round(($totalLeads / $totalClicks) * 100, 1) : 0.0,
            'order_rate' => $totalClicks > 0 ? round(($totalOrders / $totalClicks) * 100, 1) : 0.0,
            'top_focus' => $topFocus,
            'deployment_results' => $deploymentResults,
            'unmatched_opportunities' => $unmatched,
            'recent_events' => array_slice($events, 0, 20),
            'generated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('cta_result_export_csv')) {
    function cta_result_export_csv(array $results): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cta-result-bridge-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['deployment_id', 'variant_title', 'placement', 'status', 'clicks', 'leads', 'orders', 'lead_rate', 'order_rate', 'score', 'recommendation', 'decision']);
        foreach ($results as $result) {
            $deployment = (array)($result['deployment'] ?? []);
            $metrics = (array)($result['metrics'] ?? []);
            $recommendation = (array)($result['recommendation'] ?? []);
            $decision = (array)($result['decision'] ?? []);
            fputcsv($out, [
                (string)($deployment['id'] ?? ''),
                (string)($deployment['variant_title'] ?? ''),
                (string)($deployment['placement_label'] ?? $deployment['placement'] ?? ''),
                (string)($deployment['status'] ?? ''),
                (int)($metrics['clicks'] ?? 0),
                (int)($metrics['leads'] ?? 0),
                (int)($metrics['orders'] ?? 0),
                (string)($metrics['lead_rate'] ?? 0),
                (string)($metrics['order_rate'] ?? 0),
                (int)($result['result_score'] ?? 0),
                (string)($recommendation['title'] ?? ''),
                (string)($decision['status'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
