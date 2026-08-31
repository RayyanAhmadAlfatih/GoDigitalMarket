<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONTENT PERFORMANCE INSIGHT ENGINE
|--------------------------------------------------------------------------
| Connects SEO inventory with local lead-event logs, inquiries, and order
| summaries so UMKM owners can see which content is only visible, which one
| creates intent, and which one deserves conversion/SEO polishing.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('content_performance_clean')) {
    function content_performance_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('content_performance_normalize_path')) {
    function content_performance_normalize_path(string $url): string
    {
        if (function_exists('seo_link_health_normalize_url')) {
            return seo_link_health_normalize_url($url);
        }

        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?? $url);
        $basePath = trim((string)(parse_url(SITE_URL, PHP_URL_PATH) ?? ''), '/');
        $path = '/' . trim($path, '/');
        if ($basePath !== '' && str_starts_with(trim($path, '/'), $basePath)) {
            $path = '/' . trim(substr(trim($path, '/'), strlen($basePath)), '/');
        }

        $path = preg_replace('#/+#', '/', $path) ?: $path;
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}

if (!function_exists('content_performance_item_label')) {
    function content_performance_item_label(string $type): string
    {
        if (function_exists('universal_seo_type_label')) {
            return universal_seo_type_label($type);
        }

        return match ($type) {
            'product' => 'Produk',
            'service' => 'Layanan',
            'article' => 'Artikel',
            'landing_page' => 'Landing Page',
            'seo_landing' => 'SEO Landing',
            'portfolio' => 'Portfolio',
            'static_page' => 'Halaman',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}

if (!function_exists('content_performance_event_paths')) {
    function content_performance_event_paths(array $event): array
    {
        $paths = [];
        foreach (['page_path', 'target_url', 'landing_page', 'first_touch_landing_page', 'last_landing_page'] as $key) {
            $path = content_performance_normalize_path((string)($event[$key] ?? ''));
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        $source = strtolower((string)($event['source'] ?? ''));
        if ($source !== '') {
            foreach (['homepage' => '/', 'hero' => '/', 'article' => '/artikel', 'katalog' => '/katalog', 'catalog' => '/katalog', 'portfolio' => '/portfolio', 'landing' => '/landing'] as $needle => $fallback) {
                if (str_contains($source, $needle)) {
                    $paths[] = $fallback;
                }
            }
        }

        return array_values(array_unique(array_filter($paths, static fn(string $path): bool => $path !== '')));
    }
}

if (!function_exists('content_performance_event_stage')) {
    function content_performance_event_stage(array $event): string
    {
        $group = function_exists('conversion_event_group') ? conversion_event_group($event) : '';
        $kind = strtolower((string)($event['_event_kind'] ?? ''));
        $channel = strtolower((string)($event['channel'] ?? ''));
        $blob = function_exists('conversion_event_text_blob') ? conversion_event_text_blob($event) : strtolower(json_encode($event) ?: '');

        if ($group === 'order' || $channel === 'checkout' || str_contains($blob, 'order-submit') || str_contains($blob, 'checkout')) {
            return 'order';
        }
        if ($group === 'inquiry' || $channel === 'form' || str_contains($blob, 'inquiry') || str_contains($blob, 'form-submit')) {
            return 'inquiry';
        }
        if (!empty($event['is_whatsapp']) || $channel === 'whatsapp' || str_contains($blob, 'whatsapp') || str_contains($blob, 'wa.me')) {
            return 'whatsapp';
        }
        if ($kind === 'high_intent' || $group === 'conversion') {
            return 'high_intent';
        }

        return 'interaction';
    }
}

if (!function_exists('content_performance_score')) {
    function content_performance_score(array $metrics, int $seoScore): int
    {
        $score = 0;
        $score += min(25, (int)$metrics['interactions'] * 5);
        $score += min(18, (int)$metrics['high_intent'] * 6);
        $score += min(20, (int)$metrics['whatsapp'] * 7);
        $score += min(18, (int)$metrics['inquiries'] * 9);
        $score += min(14, (int)$metrics['orders'] * 14);
        $score += min(5, (int)round($seoScore / 20));
        return min(100, $score);
    }
}

if (!function_exists('content_performance_bucket')) {
    function content_performance_bucket(array $row): array
    {
        $score = (int)($row['performance_score'] ?? 0);
        $interactions = (int)($row['metrics']['interactions'] ?? 0);
        $intent = (int)($row['metrics']['high_intent'] ?? 0) + (int)($row['metrics']['whatsapp'] ?? 0) + (int)($row['metrics']['inquiries'] ?? 0) + (int)($row['metrics']['orders'] ?? 0);
        $seoScore = (int)($row['seo_score'] ?? 0);
        $type = (string)($row['type'] ?? '');

        if ($score >= 55 && $intent > 0) {
            return ['key' => 'scale_winner', 'label' => 'Scale Winner', 'tone' => 'ok', 'note' => 'Sudah punya sinyal intent. Perkuat CTA, offer, dan internal link supaya makin dekat ke transaksi.'];
        }
        if ($interactions >= 3 && $intent === 0) {
            return ['key' => 'cta_polish', 'label' => 'CTA Polish', 'tone' => 'warning', 'note' => 'Ada interaksi, tapi belum ada sinyal intent kuat. Perjelas CTA, trust, form, atau WhatsApp.'];
        }
        if ($seoScore < 75) {
            return ['key' => 'seo_boost', 'label' => 'SEO Boost', 'tone' => 'info', 'note' => 'Perlu polesan SEO agar peluang ranking dan klik organik lebih kuat.'];
        }
        if (in_array($type, ['product', 'service', 'landing_page', 'seo_landing'], true)) {
            return ['key' => 'build_support', 'label' => 'Build Support', 'tone' => 'info', 'note' => 'Money page butuh artikel pendukung dan internal link agar traffic organik lebih terarah.'];
        }

        return ['key' => 'monitor', 'label' => 'Monitor', 'tone' => 'neutral', 'note' => 'Pantau dulu. Jika sudah ada traffic, arahkan ke halaman penawaran yang relevan.'];
    }
}

if (!function_exists('content_performance_recommendation')) {
    function content_performance_recommendation(array $row): string
    {
        $bucket = (string)($row['bucket']['key'] ?? 'monitor');
        $type = (string)($row['type'] ?? '');
        $issues = (array)($row['issues'] ?? []);
        $hasImageIssue = false;
        $hasMetaIssue = false;
        foreach ($issues as $issue) {
            $field = strtolower((string)($issue['field'] ?? ''));
            $title = strtolower((string)($issue['title'] ?? ''));
            if (str_contains($field, 'image') || str_contains($title, 'gambar') || str_contains($title, 'alt')) {
                $hasImageIssue = true;
            }
            if (str_contains($field, 'meta') || str_contains($title, 'meta') || str_contains($title, 'title')) {
                $hasMetaIssue = true;
            }
        }

        if ($bucket === 'scale_winner') {
            return 'Jadikan prioritas campaign: tambah testimoni/FAQ, pasang internal link dari artikel pendukung, dan arahkan CTA ke form/WhatsApp/checkout paling jelas.';
        }
        if ($bucket === 'cta_polish') {
            return 'Perkuat conversion: tambah CTA di atas dan tengah halaman, tampilkan benefit spesifik, bukti sosial, dan pilihan kontak cepat.';
        }
        if ($hasMetaIssue) {
            return 'Rapikan meta title/description dan snippet agar klik organik lebih kuat sebelum menambah konten baru.';
        }
        if ($hasImageIssue) {
            return 'Ganti placeholder dengan gambar asli brand/produk dan isi alt text yang spesifik sesuai keyword halaman.';
        }
        if (in_array($type, ['product', 'service', 'landing_page', 'seo_landing'], true)) {
            return 'Buat 2-3 artikel pendukung yang menaut ke halaman ini memakai anchor natural sesuai masalah calon pembeli.';
        }

        return 'Pantau performa. Hubungkan konten ini ke money page yang relevan dan update CTA kalau mulai ada sinyal klik.';
    }
}

if (!function_exists('content_performance_summary')) {
    function content_performance_summary(int $days = 30, array $filters = []): array
    {
        $seo = function_exists('universal_seo_summary') ? universal_seo_summary('all') : ['items' => []];
        $items = array_values(array_filter((array)($seo['items'] ?? []), static fn(array $item): bool => !empty($item['indexable'])));
        $leadFilters = function_exists('report_filters_for') ? report_filters_for($filters, 'leads') : $filters;
        $events = function_exists('conversion_read_lead_events') ? conversion_read_lead_events($days, $leadFilters, 120000) : [];

        $pathMap = [];
        $rows = [];
        foreach ($items as $index => $item) {
            $url = (string)($item['url'] ?? '');
            $path = content_performance_normalize_path($url);
            if ($path === '') {
                continue;
            }

            $row = [
                'id' => (string)(($item['type'] ?? 'page') . '-' . ($item['id'] ?? $index)),
                'type' => (string)($item['type'] ?? 'page'),
                'type_label' => content_performance_item_label((string)($item['type'] ?? 'page')),
                'title' => content_performance_clean((string)($item['title'] ?? 'Halaman'), 160),
                'url' => $url,
                'path' => $path,
                'edit_url' => (string)($item['edit_url'] ?? ''),
                'seo_score' => (int)($item['score'] ?? 0),
                'grade' => (string)($item['grade'] ?? ''),
                'schema' => (string)($item['schema_type'] ?? ''),
                'issues' => (array)($item['issues'] ?? []),
                'metrics' => [
                    'interactions' => 0,
                    'high_intent' => 0,
                    'whatsapp' => 0,
                    'inquiries' => 0,
                    'orders' => 0,
                    'support' => 0,
                ],
                'sources' => [],
                'channels' => [],
                'latest_event_at' => '',
            ];
            $rows[$path] = $row;
            $pathMap[$path] = $path;
        }

        // Make public hub pages visible even if inventory is focused on detail pages.
        foreach ([
            '/' => ['homepage', 'Homepage'],
            '/katalog' => ['static_page', 'Katalog Produk/Jasa'],
            '/layanan' => ['static_page', 'Layanan'],
            '/artikel' => ['static_page', 'Artikel'],
            '/portfolio' => ['portfolio', 'Portfolio'],
        ] as $path => $meta) {
            if (!isset($rows[$path])) {
                $rows[$path] = [
                    'id' => $meta[0] . '-' . trim($path, '/'),
                    'type' => $meta[0],
                    'type_label' => content_performance_item_label($meta[0]),
                    'title' => $meta[1],
                    'url' => url(trim($path, '/')),
                    'path' => $path,
                    'edit_url' => $path === '/' ? url('admin/homepage') : '',
                    'seo_score' => 70,
                    'grade' => 'B',
                    'schema' => 'WebPage',
                    'issues' => [],
                    'metrics' => ['interactions' => 0, 'high_intent' => 0, 'whatsapp' => 0, 'inquiries' => 0, 'orders' => 0, 'support' => 0],
                    'sources' => [],
                    'channels' => [],
                    'latest_event_at' => '',
                ];
            }
        }

        $matchPath = static function (string $eventPath) use ($rows): string {
            $eventPath = content_performance_normalize_path($eventPath);
            if ($eventPath === '') {
                return '';
            }
            if (isset($rows[$eventPath])) {
                return $eventPath;
            }
            foreach (array_keys($rows) as $candidate) {
                if ($candidate !== '/' && (str_starts_with($eventPath . '/', $candidate . '/') || str_starts_with($candidate . '/', $eventPath . '/'))) {
                    return $candidate;
                }
            }
            if (str_starts_with($eventPath, '/artikel/')) {
                return isset($rows['/artikel']) ? '/artikel' : '';
            }
            if (str_starts_with($eventPath, '/produk/')) {
                return isset($rows['/katalog']) ? '/katalog' : '';
            }
            if (str_starts_with($eventPath, '/lp/') || str_starts_with($eventPath, '/landing/')) {
                foreach (array_keys($rows) as $candidate) {
                    if (str_starts_with($candidate, '/lp/') || str_starts_with($candidate, '/landing/')) {
                        return $candidate;
                    }
                }
            }
            return '';
        };

        foreach ($events as $event) {
            $paths = content_performance_event_paths((array)$event);
            $stage = content_performance_event_stage((array)$event);
            $matched = '';
            foreach ($paths as $path) {
                $matched = $matchPath($path);
                if ($matched !== '') {
                    break;
                }
            }
            if ($matched === '' || !isset($rows[$matched])) {
                continue;
            }

            $rows[$matched]['metrics']['interactions']++;
            if ($stage === 'high_intent') {
                $rows[$matched]['metrics']['high_intent']++;
            } elseif ($stage === 'whatsapp') {
                $rows[$matched]['metrics']['whatsapp']++;
                $rows[$matched]['metrics']['high_intent']++;
            } elseif ($stage === 'inquiry') {
                $rows[$matched]['metrics']['inquiries']++;
                $rows[$matched]['metrics']['high_intent']++;
            } elseif ($stage === 'order') {
                $rows[$matched]['metrics']['orders']++;
                $rows[$matched]['metrics']['high_intent']++;
            } else {
                $rows[$matched]['metrics']['support']++;
            }

            $source = content_performance_clean((string)($event['source'] ?? 'Tidak diketahui'), 80) ?: 'Tidak diketahui';
            $channel = content_performance_clean((string)($event['channel'] ?? 'click'), 50) ?: 'click';
            $rows[$matched]['sources'][$source] = ((int)($rows[$matched]['sources'][$source] ?? 0)) + 1;
            $rows[$matched]['channels'][$channel] = ((int)($rows[$matched]['channels'][$channel] ?? 0)) + 1;
            $ts = (int)($event['_ts'] ?? 0);
            if ($ts > 0 && ($rows[$matched]['latest_event_at'] === '' || strtotime((string)$rows[$matched]['latest_event_at']) < $ts)) {
                $rows[$matched]['latest_event_at'] = date('Y-m-d H:i', $ts);
            }
        }

        $rows = array_values(array_map(static function (array $row): array {
            arsort($row['sources']);
            arsort($row['channels']);
            $row['sources'] = array_slice($row['sources'], 0, 5, true);
            $row['channels'] = array_slice($row['channels'], 0, 5, true);
            $row['performance_score'] = content_performance_score($row['metrics'], (int)$row['seo_score']);
            $row['intent_rate'] = (int)$row['metrics']['interactions'] > 0
                ? round((((int)$row['metrics']['high_intent'] + (int)$row['metrics']['whatsapp'] + (int)$row['metrics']['inquiries'] + (int)$row['metrics']['orders']) / (int)$row['metrics']['interactions']) * 100, 1)
                : 0.0;
            $row['bucket'] = content_performance_bucket($row);
            $row['recommendation'] = content_performance_recommendation($row);
            return $row;
        }, $rows));

        usort($rows, static function (array $a, array $b): int {
            return ((int)($b['performance_score'] ?? 0) <=> (int)($a['performance_score'] ?? 0))
                ?: ((int)($b['metrics']['interactions'] ?? 0) <=> (int)($a['metrics']['interactions'] ?? 0))
                ?: ((int)($b['seo_score'] ?? 0) <=> (int)($a['seo_score'] ?? 0));
        });

        $buckets = [
            'scale_winner' => [],
            'cta_polish' => [],
            'seo_boost' => [],
            'build_support' => [],
            'monitor' => [],
        ];
        $totals = ['interactions' => 0, 'high_intent' => 0, 'whatsapp' => 0, 'inquiries' => 0, 'orders' => 0, 'support' => 0];
        foreach ($rows as $row) {
            $key = (string)($row['bucket']['key'] ?? 'monitor');
            $buckets[$key][] = $row;
            foreach ($totals as $metric => $value) {
                $totals[$metric] += (int)($row['metrics'][$metric] ?? 0);
            }
        }

        $activePages = count(array_filter($rows, static fn(array $row): bool => (int)($row['metrics']['interactions'] ?? 0) > 0));
        $avgScore = count($rows) > 0 ? (int)round(array_sum(array_map(static fn(array $row): int => (int)($row['performance_score'] ?? 0), $rows)) / count($rows)) : 0;
        $conversionReady = count((array)$buckets['scale_winner']);
        $needsCta = count((array)$buckets['cta_polish']);
        $needsSeo = count((array)$buckets['seo_boost']);

        $actionPlan = [];
        if ($conversionReady > 0) {
            $actionPlan[] = 'Scale ' . $conversionReady . ' halaman dengan sinyal intent tertinggi: tambahkan testimoni, FAQ, CTA, dan internal link dari artikel pendukung.';
        }
        if ($needsCta > 0) {
            $actionPlan[] = 'Polish CTA di ' . $needsCta . ' halaman yang sudah punya interaksi tetapi belum cukup mendorong WhatsApp/form/order.';
        }
        if ($needsSeo > 0) {
            $actionPlan[] = 'Prioritaskan SEO boost untuk halaman dengan skor rendah: meta, gambar, alt text, dan konten tipis.';
        }
        if ($activePages === 0) {
            $actionPlan[] = 'Aktifkan tracking lead dan mulai sebar CTA dari homepage/katalog/artikel agar performa konten mulai terbaca.';
        }
        $actionPlan[] = 'Hubungkan halaman performa terbaik ke Growth Insight agar keputusan konten, offer, dan follow-up bisa dibaca bareng.';

        return [
            'generated_at' => date('c'),
            'days' => $days,
            'filters' => $filters,
            'metrics' => [
                'pages_total' => count($rows),
                'active_pages' => $activePages,
                'performance_score_avg' => $avgScore,
                'scale_winners' => $conversionReady,
                'cta_polish' => $needsCta,
                'seo_boost' => $needsSeo,
                'build_support' => count((array)$buckets['build_support']),
                'events_total' => count($events),
            ] + $totals,
            'buckets' => array_map(static fn(array $bucketRows): array => array_slice($bucketRows, 0, 8), $buckets),
            'rows' => $rows,
            'action_plan' => $actionPlan,
        ];
    }
}
