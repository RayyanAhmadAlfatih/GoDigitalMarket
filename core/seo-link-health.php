<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_link_health_normalize_url')) {
    function seo_link_health_normalize_url(string $href): string
    {
        $href = trim(html_entity_decode($href, ENT_QUOTES, 'UTF-8'));
        if ($href === '' || str_starts_with($href, '#')) {
            return '';
        }

        $lower = strtolower($href);
        foreach (['mailto:', 'tel:', 'sms:', 'whatsapp:', 'javascript:', 'data:'] as $skip) {
            if (str_starts_with($lower, $skip)) {
                return '';
            }
        }

        $site = parse_url(SITE_URL) ?: [];
        $siteHost = strtolower((string)($site['host'] ?? ''));
        $sitePath = trim((string)($site['path'] ?? ''), '/');
        $parts = parse_url($href);

        if ($parts === false) {
            return '';
        }

        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host !== '' && $siteHost !== '' && $host !== $siteHost) {
            return '';
        }

        $path = (string)($parts['path'] ?? '');
        if ($path === '') {
            return '';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        if ($sitePath !== '' && str_starts_with(trim($path, '/'), $sitePath)) {
            $path = '/' . trim(substr(trim($path, '/'), strlen($sitePath)), '/');
        }

        $path = preg_replace('#/+#', '/', $path) ?: $path;
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '/';
        }

        return rtrim($path, '/');
    }
}

if (!function_exists('seo_link_health_url_label')) {
    function seo_link_health_url_label(string $url): string
    {
        $path = seo_link_health_normalize_url($url);
        return $path === '/' ? 'homepage' : trim($path, '/');
    }
}

if (!function_exists('seo_link_health_extract_anchor_links')) {
    function seo_link_health_extract_anchor_links(string $html): array
    {
        $links = [];
        if ($html === '') {
            return [];
        }

        if (preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $href = trim((string)($match[1] ?? ''));
                $anchor = trim(strip_tags((string)($match[2] ?? '')));
                $links[] = ['href' => $href, 'anchor' => $anchor !== '' ? $anchor : seo_link_health_url_label($href)];
            }
        }

        return $links;
    }
}

if (!function_exists('seo_link_health_collect_urls')) {
    function seo_link_health_collect_urls(mixed $data): array
    {
        $urls = [];
        $walk = static function (mixed $value, string $key = '') use (&$walk, &$urls): void {
            if (is_array($value)) {
                foreach ($value as $childKey => $childValue) {
                    $walk($childValue, (string)$childKey);
                }
                return;
            }

            if (!is_scalar($value)) {
                return;
            }

            $text = trim((string)$value);
            if ($text === '') {
                return;
            }

            $key = strtolower($key);
            $looksLikeUrlField = str_contains($key, 'url') || str_contains($key, 'href') || str_contains($key, 'link') || str_contains($key, 'path');
            $looksLikeUrlValue = str_starts_with($text, '/') || preg_match('#^https?://#i', $text) === 1;
            if ($looksLikeUrlField && $looksLikeUrlValue) {
                $urls[] = ['href' => $text, 'anchor' => seo_link_health_url_label($text)];
            }
        };
        $walk($data);

        $seen = [];
        return array_values(array_filter($urls, static function (array $row) use (&$seen): bool {
            $href = (string)($row['href'] ?? '');
            if ($href === '' || isset($seen[$href])) {
                return false;
            }
            $seen[$href] = true;
            return true;
        }));
    }
}

if (!function_exists('seo_link_health_raw_sources')) {
    function seo_link_health_raw_sources(bool $fresh = false): array
    {
        $sources = [];

        foreach (function_exists('all_articles') ? all_articles() : [] as $article) {
            $slug = (string)($article['slug'] ?? '');
            $sources[] = [
                'type' => 'article',
                'type_label' => function_exists('universal_seo_type_label') ? universal_seo_type_label('article') : 'Artikel',
                'title' => (string)($article['title'] ?? 'Artikel'),
                'url' => $slug !== '' && function_exists('article_url') ? article_url($slug) : url('artikel'),
                'edit_url' => url('admin/artikel?action=edit&id=' . (int)($article['id'] ?? 0)),
                'html' => (string)($article['content'] ?? '') . ' ' . (string)($article['excerpt'] ?? ''),
                'extra_links' => [],
            ];
        }

        foreach (function_exists('all_products') ? all_products() : [] as $product) {
            $slug = (string)($product['slug'] ?? '');
            $rawCategory = strtolower((string)($product['category'] ?? '') . ' ' . (string)($product['item_type_key'] ?? ''));
            $type = (str_contains($rawCategory, 'jasa') || str_contains($rawCategory, 'layanan') || str_contains($rawCategory, 'service')) ? 'service' : 'product';
            $sources[] = [
                'type' => $type,
                'type_label' => function_exists('universal_seo_type_label') ? universal_seo_type_label($type) : ($type === 'service' ? 'Layanan' : 'Produk'),
                'title' => (string)($product['title'] ?? 'Produk/Jasa'),
                'url' => $slug !== '' && function_exists('product_url') ? product_url($slug) : url('katalog'),
                'edit_url' => url('admin/produk?action=edit&id=' . (int)($product['id'] ?? 0)),
                'html' => (string)($product['content'] ?? '') . ' ' . (string)($product['description'] ?? '') . ' ' . (string)($product['excerpt'] ?? ''),
                'extra_links' => seo_link_health_collect_urls($product),
            ];
        }

        foreach (function_exists('landing_page_all') ? landing_page_all($fresh) : [] as $landing) {
            $slug = (string)($landing['slug'] ?? '');
            $sources[] = [
                'type' => 'landing_page',
                'type_label' => 'Landing Page',
                'title' => (string)($landing['title'] ?? 'Landing Page'),
                'url' => $slug !== '' && function_exists('landing_page_url') ? landing_page_url($slug) : url('landing'),
                'edit_url' => url('admin/landing-pages?builder=' . rawurlencode((string)($landing['id'] ?? $slug))),
                'html' => '',
                'extra_links' => seo_link_health_collect_urls($landing['blocks'] ?? []),
            ];
        }

        foreach (function_exists('seo_landing_public_records') ? seo_landing_public_records(true) : [] as $landing) {
            $sources[] = [
                'type' => 'seo_landing',
                'type_label' => 'SEO Landing',
                'title' => (string)($landing['title'] ?? $landing['h1'] ?? 'SEO Landing'),
                'url' => (string)($landing['url'] ?? url((string)($landing['path'] ?? ''))),
                'edit_url' => url('admin/seo-landings'),
                'html' => (string)($landing['description'] ?? '') . ' ' . (string)($landing['summary'] ?? ''),
                'extra_links' => seo_link_health_collect_urls($landing),
            ];
        }

        return $sources;
    }
}

if (!function_exists('seo_link_health_summary')) {
    function seo_link_health_summary(): array
    {
        $seo = function_exists('universal_seo_summary') ? universal_seo_summary('all') : ['items' => []];
        $items = (array)($seo['items'] ?? []);
        $targetMap = [];
        foreach ($items as $item) {
            if (empty($item['indexable'])) {
                continue;
            }
            $path = seo_link_health_normalize_url((string)($item['url'] ?? ''));
            if ($path === '') {
                continue;
            }
            $targetMap[$path] = $item;
        }

        $ignoredPrefixes = ['/admin', '/assets', '/storage', '/uploads', '/images'];
        $links = [];
        $incoming = [];
        foreach (seo_link_health_raw_sources(true) as $source) {
            $sourceUrl = (string)($source['url'] ?? '');
            $sourcePath = seo_link_health_normalize_url($sourceUrl);
            $sourceLinks = array_merge(
                seo_link_health_extract_anchor_links((string)($source['html'] ?? '')),
                (array)($source['extra_links'] ?? [])
            );

            $seenInSource = [];
            foreach ($sourceLinks as $link) {
                $href = (string)($link['href'] ?? '');
                $path = seo_link_health_normalize_url($href);
                if ($path === '' || $path === $sourcePath) {
                    continue;
                }
                foreach ($ignoredPrefixes as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        continue 2;
                    }
                }
                if (isset($seenInSource[$path])) {
                    continue;
                }
                $seenInSource[$path] = true;

                $target = $targetMap[$path] ?? null;
                if ($target) {
                    $incoming[$path] = ($incoming[$path] ?? 0) + 1;
                }

                $links[] = [
                    'source_title' => (string)($source['title'] ?? 'Halaman sumber'),
                    'source_type' => (string)($source['type'] ?? 'static_page'),
                    'source_type_label' => (string)($source['type_label'] ?? 'Halaman'),
                    'source_url' => $sourceUrl,
                    'source_edit_url' => (string)($source['edit_url'] ?? ''),
                    'href' => $href,
                    'path' => $path,
                    'anchor' => trim((string)($link['anchor'] ?? '')) ?: seo_link_health_url_label($href),
                    'status' => $target ? 'ok' : 'broken',
                    'target_title' => $target ? (string)($target['title'] ?? '') : '',
                    'target_type' => $target ? (string)($target['type'] ?? '') : '',
                    'target_type_label' => $target && function_exists('universal_seo_type_label') ? universal_seo_type_label((string)($target['type'] ?? 'static_page')) : '',
                    'target_edit_url' => $target ? (string)($target['edit_url'] ?? '') : '',
                ];
            }
        }

        $moneyTypes = ['product', 'service', 'landing_page', 'seo_landing'];
        $lowTargets = [];
        foreach ($items as $item) {
            if (empty($item['indexable']) || !in_array((string)($item['type'] ?? ''), $moneyTypes, true)) {
                continue;
            }
            $path = seo_link_health_normalize_url((string)($item['url'] ?? ''));
            $incomingCount = (int)($incoming[$path] ?? 0);
            if ($incomingCount <= 1) {
                $lowTargets[] = $item + [
                    'incoming_count' => $incomingCount,
                    'recommended_action' => $incomingCount === 0
                        ? 'Tambahkan 2-3 internal link dari artikel, portfolio, atau landing page pendukung.'
                        : 'Tambah minimal 1 internal link lagi dari konten yang topiknya paling dekat.',
                ];
            }
        }

        usort($lowTargets, static fn(array $a, array $b): int => ((int)($a['incoming_count'] ?? 0) <=> (int)($b['incoming_count'] ?? 0)) ?: ((int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0)));

        $recommendations = function_exists('seo_growth_link_recommendations') ? seo_growth_link_recommendations($items, 24) : [];
        $broken = array_values(array_filter($links, static fn(array $row): bool => (string)($row['status'] ?? '') === 'broken'));
        $ok = count($links) - count($broken);
        $healthScore = $links ? max(0, min(100, (int)round(($ok / max(1, count($links))) * 100) - min(20, count($lowTargets) * 2))) : 100;

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'metrics' => [
                'pages' => count($items),
                'internal_links' => count($links),
                'ok_links' => $ok,
                'broken_links' => count($broken),
                'low_targets' => count($lowTargets),
                'recommendations' => count($recommendations),
                'health_score' => $healthScore,
            ],
            'links' => $links,
            'broken_links' => $broken,
            'low_targets' => array_slice($lowTargets, 0, 20),
            'recommendations' => $recommendations,
            'incoming_map' => $incoming,
            'action_plan' => seo_link_health_action_plan($healthScore, count($broken), count($lowTargets), count($recommendations)),
        ];
    }
}

if (!function_exists('seo_link_health_action_plan')) {
    function seo_link_health_action_plan(int $score, int $broken, int $lowTargets, int $recommendations): array
    {
        $plan = [];
        if ($broken > 0) {
            $plan[] = 'Fix link internal yang rusak dulu agar user dan crawler tidak mentok di halaman 404.';
        }
        if ($lowTargets > 0) {
            $plan[] = 'Dorong money page yang masih minim incoming link dari artikel, portfolio, landing page, atau FAQ.';
        }
        if ($recommendations > 0) {
            $plan[] = 'Eksekusi rekomendasi anchor text dari halaman pendukung ke produk/jasa/landing page prioritas.';
        }
        if ($score >= 90 && !$plan) {
            $plan[] = 'Struktur link internal sudah solid. Lanjut tambah cluster konten baru untuk memperbesar topical authority.';
        }
        return $plan ?: ['Mulai dari 3 internal link paling berdampak ke halaman produk/jasa utama.'];
    }
}

if (!function_exists('seo_link_health_status_label')) {
    function seo_link_health_status_label(string $status): string
    {
        return $status === 'broken' ? 'Perlu Fix' : 'Aman';
    }
}

if (!function_exists('seo_link_health_status_class')) {
    function seo_link_health_status_class(string $status): string
    {
        return $status === 'broken' ? 'admin-status-pill admin-status-pill--error' : 'admin-status-pill admin-status-pill--ok';
    }
}
