<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO PROFIT ATTRIBUTION BRIDGE
|--------------------------------------------------------------------------
| Reads existing Lead Tracking logs and connects SEO pages with clicks,
| leads, order, payment, CTA placement, and profit actions. This module is
| a bridge, not a second tracking system.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_profit_clean')) {
    function seo_profit_clean(mixed $value, int $max = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('seo_profit_id')) {
    function seo_profit_id(string $value = ''): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');

        return substr($value, 0, 140);
    }
}

if (!function_exists('seo_profit_path')) {
    function seo_profit_path(string $url): string
    {
        $path = trim((string)(parse_url($url, PHP_URL_PATH) ?: $url));
        $path = preg_replace('#/index\.php$#i', '/', $path) ?: $path;
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}

if (!function_exists('seo_profit_storage_file')) {
    function seo_profit_storage_file(): string
    {
        return STORAGE_PATH . '/seo-profit-attribution-decisions.json';
    }
}

if (!function_exists('seo_profit_decision_options')) {
    function seo_profit_decision_options(): array
    {
        return [
            'monitor' => 'Pantau dulu',
            'scale' => 'Scale konten/traffic',
            'improve_cta' => 'Perbaiki CTA/offer',
            'add_internal_link' => 'Tambah internal link',
            'refresh_content' => 'Update konten SEO',
            'create_followup' => 'Buat follow-up lead',
        ];
    }
}

if (!function_exists('seo_profit_default_settings')) {
    function seo_profit_default_settings(): array
    {
        return [
            'decisions' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_profit_normalize_decision')) {
    function seo_profit_normalize_decision(array $decision): array
    {
        $options = seo_profit_decision_options();
        $status = (string)($decision['status'] ?? 'monitor');
        if (!isset($options[$status])) {
            $status = 'monitor';
        }

        return [
            'page_id' => seo_profit_id((string)($decision['page_id'] ?? '')),
            'status' => $status,
            'note' => seo_profit_clean($decision['note'] ?? '', 360),
            'updated_at' => seo_profit_clean($decision['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_profit_normalize_settings')) {
    function seo_profit_normalize_settings(array $settings): array
    {
        $settings = array_merge(seo_profit_default_settings(), $settings);
        $decisions = [];

        foreach ((array)($settings['decisions'] ?? []) as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $normalized = seo_profit_normalize_decision($decision);
            if ((string)$normalized['page_id'] === '') {
                continue;
            }
            $decisions[(string)$normalized['page_id']] = $normalized;
        }

        return [
            'decisions' => $decisions,
            'updated_at' => seo_profit_clean($settings['updated_at'] ?? date(DATE_ATOM), 80),
        ];
    }
}

if (!function_exists('seo_profit_settings')) {
    function seo_profit_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = seo_profit_storage_file();
        if (!is_file($file)) {
            $cached = seo_profit_normalize_settings(seo_profit_default_settings());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = seo_profit_normalize_settings(seo_profit_default_settings());
            return $cached;
        }

        $cached = seo_profit_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('seo_profit_write_settings')) {
    function seo_profit_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = seo_profit_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(seo_profit_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan SEO Profit Attribution belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(seo_profit_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'seo-profit-attribution', null, 'Menyimpan keputusan SEO Profit Attribution Bridge.');
        }

        return true;
    }
}

if (!function_exists('seo_profit_update_decision')) {
    function seo_profit_update_decision(string $pageId, string $status, string $note = ''): bool
    {
        $pageId = seo_profit_id($pageId);
        if ($pageId === '') {
            throw new RuntimeException('ID halaman SEO tidak valid.');
        }

        $settings = seo_profit_settings(true);
        $settings['decisions'][$pageId] = seo_profit_normalize_decision([
            'page_id' => $pageId,
            'status' => $status,
            'note' => $note,
            'updated_at' => date(DATE_ATOM),
        ]);

        return seo_profit_write_settings($settings, true);
    }
}

if (!function_exists('seo_profit_reset_decisions')) {
    function seo_profit_reset_decisions(): void
    {
        if (is_file(seo_profit_storage_file())) {
            @unlink(seo_profit_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'seo-profit-attribution', null, 'Reset catatan SEO Profit Attribution Bridge.');
        }
    }
}

if (!function_exists('seo_profit_event_group')) {
    function seo_profit_event_group(array $event): string
    {
        if (function_exists('conversion_event_group')) {
            return conversion_event_group($event);
        }

        $blob = strtolower(implode(' ', array_map(static fn($value): string => is_scalar($value) ? (string)$value : '', [
            $event['source'] ?? '',
            $event['type'] ?? '',
            $event['channel'] ?? '',
            $event['label'] ?? '',
            $event['page_path'] ?? '',
            $event['target_url'] ?? '',
        ])));

        if (str_contains($blob, 'order') || str_contains($blob, 'checkout')) {
            return 'order';
        }
        if (str_contains($blob, 'payment') || str_contains($blob, 'invoice')) {
            return 'payment';
        }
        if (str_contains($blob, 'whatsapp') || str_contains($blob, 'form')) {
            return 'inquiry';
        }
        if (str_contains($blob, 'page_view')) {
            return 'page_view';
        }

        return 'interaction';
    }
}

if (!function_exists('seo_profit_event_blob')) {
    function seo_profit_event_blob(array $event): string
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
            $event['landing_page_slug'] ?? '',
            $event['cta_deployment_id'] ?? '',
            $event['offer_variant_id'] ?? '',
            $event['cta_placement'] ?? '',
        ])));
    }
}

if (!function_exists('seo_profit_page_id')) {
    function seo_profit_page_id(array $item): string
    {
        $type = seo_profit_id((string)($item['type'] ?? 'page'));
        $slug = seo_profit_id((string)($item['slug'] ?? ''));
        $path = seo_profit_id(seo_profit_path((string)($item['url'] ?? '')));
        $id = seo_profit_id((string)($item['id'] ?? ''));

        return seo_profit_id($type . '-' . ($slug ?: ($id ?: $path)));
    }
}

if (!function_exists('seo_profit_type_label')) {
    function seo_profit_type_label(string $type): string
    {
        if (function_exists('universal_seo_type_label')) {
            return universal_seo_type_label($type);
        }

        return match ($type) {
            'article' => 'Artikel',
            'landing_page' => 'Landing Page',
            'seo_landing' => 'SEO Landing',
            'product' => 'Produk',
            'service' => 'Layanan',
            'portfolio' => 'Portfolio',
            default => 'Halaman',
        };
    }
}

if (!function_exists('seo_profit_attribution_items')) {
    function seo_profit_attribution_items(string $type = 'all'): array
    {
        $items = function_exists('universal_seo_summary')
            ? (array)(universal_seo_summary('all')['items'] ?? [])
            : (function_exists('universal_seo_items') ? universal_seo_items(true) : []);

        $allowed = ['article', 'landing_page', 'seo_landing', 'static_page', 'product', 'service', 'portfolio'];
        $items = array_values(array_filter($items, static function (array $item) use ($allowed, $type): bool {
            $itemType = (string)($item['type'] ?? '');
            if (!in_array($itemType, $allowed, true)) {
                return false;
            }
            if ($type !== 'all' && $itemType !== $type) {
                return false;
            }
            return !array_key_exists('indexable', $item) || !empty($item['indexable']);
        }));

        return array_map(static function (array $item): array {
            $path = seo_profit_path((string)($item['url'] ?? ''));
            $item['page_id'] = seo_profit_page_id($item);
            $item['page_path'] = $path;
            $item['type_label'] = seo_profit_type_label((string)($item['type'] ?? 'page'));
            return $item;
        }, $items);
    }
}

if (!function_exists('seo_profit_word_tokens')) {
    function seo_profit_word_tokens(string $value, int $limit = 8): array
    {
        $value = strtolower(seo_profit_clean($value, 240));
        $tokens = preg_split('/[^a-z0-9]+/i', $value) ?: [];
        $tokens = array_values(array_filter(array_unique($tokens), static fn(string $token): bool => strlen($token) >= 4));

        return array_slice($tokens, 0, max(1, $limit));
    }
}

if (!function_exists('seo_profit_match_score')) {
    function seo_profit_match_score(array $item, array $event): int
    {
        $score = 0;
        $pagePath = seo_profit_path((string)($item['url'] ?? $item['page_path'] ?? '/'));
        $eventPage = seo_profit_path((string)($event['page_path'] ?? '/'));
        $targetPath = seo_profit_path((string)($event['target_url'] ?? ''));
        $slug = seo_profit_id((string)($item['slug'] ?? ''));
        $title = strtolower(seo_profit_clean((string)($item['title'] ?? ''), 160));
        $blob = seo_profit_event_blob($event);

        if ($pagePath === $eventPage) {
            $score += 120;
        } elseif ($pagePath !== '/' && str_starts_with($eventPage . '/', $pagePath . '/')) {
            $score += 75;
        }

        if ($pagePath !== '/' && $targetPath === $pagePath) {
            $score += 60;
        } elseif ($pagePath !== '/' && $targetPath !== '/' && str_starts_with($targetPath . '/', $pagePath . '/')) {
            $score += 35;
        }

        if ($slug !== '') {
            $eventLandingSlug = seo_profit_id((string)($event['landing_page_slug'] ?? ''));
            if ($eventLandingSlug !== '' && $eventLandingSlug === $slug) {
                $score += 85;
            }
            if (str_contains($eventPage, '/' . $slug) || str_contains($targetPath, '/' . $slug) || str_contains($blob, $slug)) {
                $score += 45;
            }
        }

        $pageId = seo_profit_id((string)($item['page_id'] ?? seo_profit_page_id($item)));
        if ($pageId !== '' && str_contains($blob, $pageId)) {
            $score += 60;
        }

        foreach (seo_profit_word_tokens($title, 5) as $token) {
            if (str_contains($blob, $token)) {
                $score += 8;
            }
        }

        $keywords = (array)($item['keywords'] ?? []);
        foreach (array_slice($keywords, 0, 4) as $keyword) {
            $keyword = strtolower(seo_profit_clean($keyword, 70));
            if ($keyword !== '' && str_contains($blob, $keyword)) {
                $score += 12;
            }
        }

        return $score;
    }
}

if (!function_exists('seo_profit_empty_metrics')) {
    function seo_profit_empty_metrics(): array
    {
        return [
            'events' => 0,
            'views' => 0,
            'clicks' => 0,
            'leads' => 0,
            'orders' => 0,
            'payments' => 0,
            'high_intent' => 0,
            'assisted' => 0,
            'by_channel' => [],
            'by_label' => [],
            'recent_events' => [],
            'last_event_at' => '',
        ];
    }
}

if (!function_exists('seo_profit_register_event')) {
    function seo_profit_register_event(array $metrics, array $event, int $matchScore): array
    {
        $metrics['events']++;
        $group = seo_profit_event_group($event);
        $channel = seo_profit_clean((string)($event['channel'] ?? 'click'), 50) ?: 'click';
        $label = seo_profit_clean((string)($event['label'] ?? $group), 90) ?: $group;

        if ($group === 'page_view') {
            $metrics['views']++;
        } elseif ($group === 'order') {
            $metrics['orders']++;
            $metrics['high_intent']++;
        } elseif ($group === 'payment') {
            $metrics['payments']++;
            $metrics['orders']++;
            $metrics['high_intent']++;
        } elseif (in_array($group, ['inquiry', 'conversion'], true)) {
            $metrics['leads']++;
            $metrics['high_intent']++;
        } else {
            $metrics['clicks']++;
        }

        if ($matchScore < 90 && in_array($group, ['order', 'payment', 'inquiry', 'conversion'], true)) {
            $metrics['assisted']++;
        }

        $metrics['by_channel'][$channel] = ((int)($metrics['by_channel'][$channel] ?? 0)) + 1;
        $metrics['by_label'][$label] = ((int)($metrics['by_label'][$label] ?? 0)) + 1;

        $ts = (int)($event['_ts'] ?? (function_exists('conversion_event_timestamp') ? conversion_event_timestamp($event) : strtotime((string)($event['time'] ?? ''))));
        if ($ts > 0) {
            if ((string)$metrics['last_event_at'] === '' || $ts > (int)strtotime((string)$metrics['last_event_at'])) {
                $metrics['last_event_at'] = date(DATE_ATOM, $ts);
            }
        }

        if (count((array)$metrics['recent_events']) < 6) {
            $event['_match_score'] = $matchScore;
            $metrics['recent_events'][] = $event;
        }

        return $metrics;
    }
}

if (!function_exists('seo_profit_rates')) {
    function seo_profit_rates(array $metrics): array
    {
        $clickBase = max(1, (int)($metrics['clicks'] ?? 0) + (int)($metrics['leads'] ?? 0) + (int)($metrics['orders'] ?? 0));
        $eventBase = max(1, (int)($metrics['events'] ?? 0));

        return [
            'click_rate' => round((((int)($metrics['clicks'] ?? 0)) / $eventBase) * 100, 1),
            'lead_rate' => round((((int)($metrics['leads'] ?? 0)) / $clickBase) * 100, 1),
            'order_rate' => round((((int)($metrics['orders'] ?? 0)) / $clickBase) * 100, 1),
            'profit_signal_rate' => round((((int)($metrics['high_intent'] ?? 0)) / $eventBase) * 100, 1),
        ];
    }
}

if (!function_exists('seo_profit_page_score')) {
    function seo_profit_page_score(array $item, array $metrics): int
    {
        $seoScore = (int)($item['score'] ?? 70);
        $score = (int)round($seoScore * 0.35);
        $score += min(18, (int)($metrics['events'] ?? 0) * 2);
        $score += min(20, (int)($metrics['clicks'] ?? 0) * 4);
        $score += min(28, (int)($metrics['leads'] ?? 0) * 9);
        $score += min(32, (int)($metrics['orders'] ?? 0) * 14);
        $score += min(10, (int)($metrics['payments'] ?? 0) * 5);

        return max(0, min(100, $score));
    }
}

if (!function_exists('seo_profit_recommendation')) {
    function seo_profit_recommendation(array $item, array $metrics): array
    {
        $events = (int)($metrics['events'] ?? 0);
        $clicks = (int)($metrics['clicks'] ?? 0);
        $leads = (int)($metrics['leads'] ?? 0);
        $orders = (int)($metrics['orders'] ?? 0);
        $seoScore = (int)($item['score'] ?? 70);
        $type = (string)($item['type'] ?? 'page');

        if (!function_exists('conversion_tracking_enabled') || !conversion_tracking_enabled()) {
            return ['tone' => 'check', 'title' => 'Aktifkan Lead Tracking', 'text' => 'Tracking Lead belum aktif, jadi kontribusi halaman SEO belum bisa dibaca dari data real.'];
        }

        if ($orders > 0) {
            return ['tone' => 'scale', 'title' => 'Halaman membawa order', 'text' => 'Pertahankan halaman ini, tambah internal link, dan masukkan ke campaign profit karena sudah punya sinyal order/payment.'];
        }

        if ($leads > 0) {
            return ['tone' => 'keep', 'title' => 'Halaman membawa lead', 'text' => 'Halaman ini sudah menghasilkan prospek. Perkuat follow-up, test CTA, dan arahkan ke offer yang lebih jelas.'];
        }

        if ($clicks >= 3 && $leads === 0) {
            return ['tone' => 'improve', 'title' => 'Klik ada, lead belum masuk', 'text' => 'CTA atau offer di halaman ini perlu dipoles: perjelas manfaat, proof, tombol, atau jalur WhatsApp/form.'];
        }

        if ($events > 0 && $clicks === 0 && $leads === 0) {
            return ['tone' => 'place', 'title' => 'Traffic ada, CTA belum kuat', 'text' => 'Tambahkan CTA tengah/bawah artikel, trust block, atau link ke produk/jasa agar traffic tidak berhenti sebagai pembaca.'];
        }

        if ($events === 0 && in_array($type, ['article', 'landing_page', 'seo_landing'], true)) {
            return ['tone' => 'boost', 'title' => 'Belum ada sinyal lead', 'text' => 'Pastikan halaman terindex, punya internal link, dan CTA yang jelas. Setelah ada traffic, hasil akan terbaca dari Tracking Lead.'];
        }

        if ($seoScore < 75) {
            return ['tone' => 'seo', 'title' => 'SEO perlu dipoles', 'text' => 'Perkuat title, description, konten, internal link, dan proof agar halaman lebih siap mendatangkan traffic berkualitas.'];
        }

        return ['tone' => 'monitor', 'title' => 'Pantau dulu', 'text' => 'Belum ada sinyal kuat. Pantau beberapa hari lagi atau dorong traffic melalui internal link dan campaign.'];
    }
}

if (!function_exists('seo_profit_analyze_item')) {
    function seo_profit_analyze_item(array $item, array $events, array $decision = []): array
    {
        $metrics = seo_profit_empty_metrics();
        foreach ($events as $event) {
            $matchScore = seo_profit_match_score($item, $event);
            if ($matchScore < 45) {
                continue;
            }
            $metrics = seo_profit_register_event($metrics, $event, $matchScore);
        }

        arsort($metrics['by_channel']);
        arsort($metrics['by_label']);
        $metrics += seo_profit_rates($metrics);
        $score = seo_profit_page_score($item, $metrics);
        $recommendation = seo_profit_recommendation($item, $metrics);

        return [
            'item' => $item,
            'metrics' => $metrics,
            'profit_score' => $score,
            'recommendation' => $recommendation,
            'decision' => $decision,
        ];
    }
}

if (!function_exists('seo_profit_type_options')) {
    function seo_profit_type_options(): array
    {
        return [
            'all' => 'Semua SEO Page',
            'article' => 'Artikel',
            'landing_page' => 'Landing Page',
            'seo_landing' => 'SEO Landing',
            'product' => business_label('product', 'Produk'),
            'service' => business_label('service', 'Layanan'),
            'static_page' => 'Halaman Utama',
        ];
    }
}

if (!function_exists('seo_profit_action_queue')) {
    function seo_profit_action_queue(array $results, int $limit = 6): array
    {
        $items = [];
        foreach ($results as $result) {
            $tone = (string)($result['recommendation']['tone'] ?? 'monitor');
            $metrics = (array)($result['metrics'] ?? []);
            $item = (array)($result['item'] ?? []);
            $priority = match ($tone) {
                'scale' => 100,
                'keep' => 88,
                'improve' => 78,
                'place' => 70,
                'seo' => 62,
                'boost' => 55,
                default => 40,
            };
            $priority += min(20, ((int)($metrics['orders'] ?? 0) * 5) + ((int)($metrics['leads'] ?? 0) * 3) + (int)($metrics['clicks'] ?? 0));

            $items[] = [
                'priority' => min(100, $priority),
                'page_id' => (string)($item['page_id'] ?? ''),
                'title' => (string)($item['title'] ?? 'Halaman SEO'),
                'url' => (string)($item['url'] ?? ''),
                'edit_url' => (string)($item['edit_url'] ?? ''),
                'type_label' => (string)($item['type_label'] ?? 'Halaman'),
                'recommendation' => (array)($result['recommendation'] ?? []),
                'metrics' => $metrics,
            ];
        }

        usort($items, static fn(array $a, array $b): int => ((int)$b['priority'] <=> (int)$a['priority']) ?: strcmp((string)$a['title'], (string)$b['title']));
        return array_slice($items, 0, max(1, $limit));
    }
}

if (!function_exists('seo_profit_summary')) {
    function seo_profit_summary(int $days = 30, string $type = 'all'): array
    {
        $allowedTypes = array_keys(seo_profit_type_options());
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }
        $days = max(1, min(365, $days));
        $leadTrackingAvailable = function_exists('conversion_read_lead_events');
        $trackingEnabled = function_exists('conversion_tracking_enabled') ? conversion_tracking_enabled() : false;
        $events = [];

        if ($leadTrackingAvailable) {
            $rawEvents = conversion_read_lead_events($days, [], 200000);
            $events = function_exists('conversion_dedupe_lead_events') ? conversion_dedupe_lead_events($rawEvents, 10) : $rawEvents;
        }

        $items = seo_profit_attribution_items($type);
        $decisions = (array)(seo_profit_settings(true)['decisions'] ?? []);
        $results = [];

        foreach ($items as $item) {
            $pageId = (string)($item['page_id'] ?? seo_profit_page_id($item));
            $results[] = seo_profit_analyze_item($item, $events, (array)($decisions[$pageId] ?? []));
        }

        usort($results, static function (array $a, array $b): int {
            $am = (array)($a['metrics'] ?? []);
            $bm = (array)($b['metrics'] ?? []);
            $aImpact = ((int)($am['orders'] ?? 0) * 10) + ((int)($am['leads'] ?? 0) * 6) + ((int)($am['clicks'] ?? 0) * 2) + (int)($a['profit_score'] ?? 0);
            $bImpact = ((int)($bm['orders'] ?? 0) * 10) + ((int)($bm['leads'] ?? 0) * 6) + ((int)($bm['clicks'] ?? 0) * 2) + (int)($b['profit_score'] ?? 0);
            return ($bImpact <=> $aImpact) ?: strcmp((string)($a['item']['title'] ?? ''), (string)($b['item']['title'] ?? ''));
        });

        $totalEvents = 0;
        $totalClicks = 0;
        $totalLeads = 0;
        $totalOrders = 0;
        $totalPayments = 0;
        $pagesWithSignal = 0;
        $pagesWithLead = 0;
        $pagesWithOrder = 0;
        $scoreSum = 0;
        $needsCta = 0;
        $scaleReady = 0;

        foreach ($results as $result) {
            $metrics = (array)($result['metrics'] ?? []);
            $totalEvents += (int)($metrics['events'] ?? 0);
            $totalClicks += (int)($metrics['clicks'] ?? 0);
            $totalLeads += (int)($metrics['leads'] ?? 0);
            $totalOrders += (int)($metrics['orders'] ?? 0);
            $totalPayments += (int)($metrics['payments'] ?? 0);
            $scoreSum += (int)($result['profit_score'] ?? 0);
            if ((int)($metrics['events'] ?? 0) > 0) {
                $pagesWithSignal++;
            }
            if ((int)($metrics['leads'] ?? 0) > 0) {
                $pagesWithLead++;
            }
            if ((int)($metrics['orders'] ?? 0) > 0) {
                $pagesWithOrder++;
            }
            $tone = (string)($result['recommendation']['tone'] ?? 'monitor');
            if (in_array($tone, ['improve', 'place', 'seo', 'boost'], true)) {
                $needsCta++;
            }
            if (in_array($tone, ['scale', 'keep'], true)) {
                $scaleReady++;
            }
        }

        $attributionScore = $results ? (int)round($scoreSum / count($results)) : 0;
        $topPage = $results[0] ?? null;
        $topFocus = 'Hubungkan SEO ke CTA dan Lead Tracking agar kontribusi halaman mulai terbaca.';
        if (!$trackingEnabled) {
            $topFocus = 'Aktifkan Lead Tracking agar artikel dan halaman SEO bisa terbaca kontribusinya.';
        } elseif ($totalOrders > 0) {
            $topFocus = 'Ada halaman SEO yang sudah membawa order/payment. Jadikan prioritas campaign dan internal link.';
        } elseif ($totalLeads > 0) {
            $topFocus = 'Ada halaman SEO yang membawa lead. Perkuat follow-up dan test CTA untuk naik ke order.';
        } elseif ($totalClicks > 0) {
            $topFocus = 'Ada klik dari halaman SEO, tapi lead/order belum kuat. Perbaiki CTA, offer, dan trust block.';
        } elseif ($pagesWithSignal === 0) {
            $topFocus = 'Belum ada sinyal dari halaman SEO. Dorong indexing, internal link, dan CTA di artikel/landing page.';
        }

        return [
            'days' => $days,
            'type' => $type,
            'tracking_enabled' => $trackingEnabled,
            'lead_tracking_available' => $leadTrackingAvailable,
            'total_raw_events' => count($events),
            'total_seo_pages' => count($results),
            'pages_with_signal' => $pagesWithSignal,
            'pages_with_lead' => $pagesWithLead,
            'pages_with_order' => $pagesWithOrder,
            'total_events' => $totalEvents,
            'total_clicks' => $totalClicks,
            'total_leads' => $totalLeads,
            'total_orders' => $totalOrders,
            'total_payments' => $totalPayments,
            'needs_cta' => $needsCta,
            'scale_ready' => $scaleReady,
            'attribution_score' => $attributionScore,
            'lead_rate' => $totalClicks > 0 ? round(($totalLeads / $totalClicks) * 100, 1) : 0.0,
            'order_rate' => $totalClicks > 0 ? round(($totalOrders / $totalClicks) * 100, 1) : 0.0,
            'top_focus' => $topFocus,
            'top_page' => $topPage,
            'results' => $results,
            'action_queue' => seo_profit_action_queue($results, 6),
            'generated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_profit_export_csv')) {
    function seo_profit_export_csv(array $results): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="seo-profit-attribution-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['page_id', 'type', 'title', 'url', 'seo_score', 'events', 'clicks', 'leads', 'orders', 'payments', 'lead_rate', 'order_rate', 'profit_score', 'recommendation', 'decision']);
        foreach ($results as $result) {
            $item = (array)($result['item'] ?? []);
            $metrics = (array)($result['metrics'] ?? []);
            $recommendation = (array)($result['recommendation'] ?? []);
            $decision = (array)($result['decision'] ?? []);
            fputcsv($out, [
                (string)($item['page_id'] ?? ''),
                (string)($item['type_label'] ?? $item['type'] ?? ''),
                (string)($item['title'] ?? ''),
                (string)($item['url'] ?? ''),
                (int)($item['score'] ?? 0),
                (int)($metrics['events'] ?? 0),
                (int)($metrics['clicks'] ?? 0),
                (int)($metrics['leads'] ?? 0),
                (int)($metrics['orders'] ?? 0),
                (int)($metrics['payments'] ?? 0),
                (string)($metrics['lead_rate'] ?? 0),
                (string)($metrics['order_rate'] ?? 0),
                (int)($result['profit_score'] ?? 0),
                (string)($recommendation['title'] ?? ''),
                (string)($decision['status'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
