<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_growth_normalize_text')) {
    function seo_growth_normalize_text(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(' ', array_map('strval', $value));
        }

        if (function_exists('universal_seo_text')) {
            $text = universal_seo_text($value);
        } else {
            $text = trim((string)$value);
        }

        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?: '';
        $text = preg_replace('/\s+/u', ' ', $text) ?: '';
        return trim($text);
    }
}

if (!function_exists('seo_growth_stopwords')) {
    function seo_growth_stopwords(): array
    {
        return [
            'yang'=>true,'dan'=>true,'atau'=>true,'untuk'=>true,'dengan'=>true,'dari'=>true,'ke'=>true,'di'=>true,'ini'=>true,'itu'=>true,
            'kami'=>true,'anda'=>true,'dalam'=>true,'pada'=>true,'adalah'=>true,'bisa'=>true,'agar'=>true,'lebih'=>true,'jadi'=>true,
            'produk'=>true,'jasa'=>true,'layanan'=>true,'artikel'=>true,'landing'=>true,'page'=>true,'halaman'=>true,'katalog'=>true,
            'umkm'=>true,'template'=>true,'website'=>true,'bisnis'=>true,'best'=>true,'seller'=>true,'paket'=>true,'harga'=>true,
            'the'=>true,'a'=>true,'an'=>true,'and'=>true,'or'=>true,'for'=>true,'to'=>true,'of'=>true,'in'=>true,'on'=>true,
        ];
    }
}

if (!function_exists('seo_growth_tokens')) {
    function seo_growth_tokens(mixed $value, int $limit = 12): array
    {
        $text = seo_growth_normalize_text($value);
        if ($text === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $text) ?: [];
        $stop = seo_growth_stopwords();
        $counts = [];
        foreach ($parts as $word) {
            $word = trim((string)$word, " \t\n\r\0\x0B-");
            $length = function_exists('mb_strlen') ? mb_strlen($word, 'UTF-8') : strlen($word);
            if ($length < 3 || isset($stop[$word]) || is_numeric($word)) {
                continue;
            }
            $counts[$word] = ($counts[$word] ?? 0) + 1;
        }

        arsort($counts);
        return array_slice(array_keys($counts), 0, max(1, $limit));
    }
}

if (!function_exists('seo_growth_item_tokens')) {
    function seo_growth_item_tokens(array $item): array
    {
        $keywords = $item['keywords'] ?? [];
        $raw = implode(' ', array_filter([
            (string)($item['title'] ?? ''),
            (string)($item['slug'] ?? ''),
            is_array($keywords) ? implode(' ', array_map('strval', $keywords)) : (string)$keywords,
            (string)($item['meta_description'] ?? ''),
            (string)($item['body'] ?? ''),
        ]));

        return seo_growth_tokens($raw, 18);
    }
}

if (!function_exists('seo_growth_overlap_score')) {
    function seo_growth_overlap_score(array $a, array $b): int
    {
        if (!$a || !$b) {
            return 0;
        }

        $setB = array_fill_keys($b, true);
        $score = 0;
        foreach ($a as $index => $token) {
            if (isset($setB[$token])) {
                $score += max(1, 8 - (int)floor($index / 2));
            }
        }
        return $score;
    }
}

if (!function_exists('seo_growth_intent_label')) {
    function seo_growth_intent_label(string $type): string
    {
        return match ($type) {
            'product' => 'Transaksi Produk',
            'service' => 'Transaksi Jasa',
            'landing_page' => 'Conversion Landing',
            'seo_landing' => 'Akuisisi SEO',
            'article' => 'Edukasi & Authority',
            'portfolio' => 'Trust / Bukti Karya',
            default => 'Brand / Navigasi',
        };
    }
}

if (!function_exists('seo_growth_priority_label')) {
    function seo_growth_priority_label(int $score): string
    {
        return $score >= 85 ? 'Tinggi' : ($score >= 65 ? 'Sedang' : 'Rendah');
    }
}

if (!function_exists('seo_growth_priority_class')) {
    function seo_growth_priority_class(int $score): string
    {
        return $score >= 85 ? 'admin-status-pill admin-status-pill--error' : ($score >= 65 ? 'admin-status-pill admin-status-pill--warning' : 'admin-status-pill admin-status-pill--info');
    }
}

if (!function_exists('seo_growth_anchor_suggestion')) {
    function seo_growth_anchor_suggestion(array $source, array $target, array $sharedTokens): string
    {
        $tokens = array_slice($sharedTokens, 0, 3);
        if ($tokens) {
            return implode(' ', $tokens) . ' ' . (in_array((string)($target['type'] ?? ''), ['product', 'service'], true) ? 'terbaik' : 'lengkap');
        }

        $title = trim((string)($target['title'] ?? 'halaman terkait'));
        return $title !== '' ? $title : 'halaman terkait';
    }
}

if (!function_exists('seo_growth_money_pages')) {
    function seo_growth_money_pages(array $items): array
    {
        $moneyTypes = ['product', 'service', 'landing_page', 'seo_landing'];
        $pages = array_values(array_filter($items, static fn(array $item): bool => !empty($item['indexable']) && in_array((string)($item['type'] ?? ''), $moneyTypes, true)));
        usort($pages, static function (array $a, array $b): int {
            $aScore = (int)($a['score'] ?? 0) + ((string)($a['type'] ?? '') === 'landing_page' ? 7 : 0);
            $bScore = (int)($b['score'] ?? 0) + ((string)($b['type'] ?? '') === 'landing_page' ? 7 : 0);
            return $bScore <=> $aScore;
        });
        return $pages;
    }
}

if (!function_exists('seo_growth_support_pages')) {
    function seo_growth_support_pages(array $items): array
    {
        $supportTypes = ['article', 'portfolio', 'static_page'];
        return array_values(array_filter($items, static fn(array $item): bool => !empty($item['indexable']) && in_array((string)($item['type'] ?? ''), $supportTypes, true)));
    }
}

if (!function_exists('seo_growth_link_recommendations')) {
    function seo_growth_link_recommendations(array $items, int $limit = 16): array
    {
        $moneyPages = seo_growth_money_pages($items);
        $supportPages = seo_growth_support_pages($items);
        $recommendations = [];

        foreach ($supportPages as $source) {
            $sourceTokens = seo_growth_item_tokens($source);
            foreach ($moneyPages as $target) {
                $targetUrl = (string)($target['url'] ?? '');
                $sourceUrl = (string)($source['url'] ?? '');
                if ($targetUrl === '' || $sourceUrl === '' || $targetUrl === $sourceUrl) {
                    continue;
                }

                $targetTokens = seo_growth_item_tokens($target);
                $shared = array_values(array_intersect($sourceTokens, $targetTokens));
                $overlap = seo_growth_overlap_score($sourceTokens, $targetTokens);
                $targetInternal = (int)($target['internal_link_count'] ?? 0);
                $targetScore = (int)($target['score'] ?? 0);
                $sourceScore = (int)($source['score'] ?? 0);
                $priorityScore = min(100, max(25, ($overlap * 6) + max(0, 88 - $targetScore) + max(0, 4 - $targetInternal) * 8 + ($sourceScore >= 75 ? 10 : 0)));

                if ($overlap < 2 && $priorityScore < 58) {
                    continue;
                }

                $recommendations[] = [
                    'source_title' => (string)($source['title'] ?? ''),
                    'source_type' => (string)($source['type'] ?? ''),
                    'source_url' => $sourceUrl,
                    'source_edit_url' => (string)($source['edit_url'] ?? ''),
                    'target_title' => (string)($target['title'] ?? ''),
                    'target_type' => (string)($target['type'] ?? ''),
                    'target_url' => $targetUrl,
                    'target_edit_url' => (string)($target['edit_url'] ?? ''),
                    'shared_tokens' => array_slice($shared, 0, 5),
                    'anchor' => seo_growth_anchor_suggestion($source, $target, $shared),
                    'priority_score' => $priorityScore,
                    'priority_label' => seo_growth_priority_label($priorityScore),
                    'reason' => $targetInternal <= 1
                        ? 'Target masih minim internal link. Cocok diberi dorongan dari halaman pendukung.'
                        : 'Topik halaman cukup nyambung. Bisa dipakai untuk memperkuat authority dan alur conversion.',
                ];
            }
        }

        usort($recommendations, static fn(array $a, array $b): int => ((int)($b['priority_score'] ?? 0) <=> (int)($a['priority_score'] ?? 0)) ?: strcmp((string)($a['target_title'] ?? ''), (string)($b['target_title'] ?? '')));
        return array_slice($recommendations, 0, max(1, $limit));
    }
}

if (!function_exists('seo_growth_content_gap_ideas')) {
    function seo_growth_content_gap_ideas(array $items, int $limit = 12): array
    {
        $moneyPages = seo_growth_money_pages($items);
        $supportPages = seo_growth_support_pages($items);
        $supportTokenMap = [];
        foreach ($supportPages as $support) {
            foreach (seo_growth_item_tokens($support) as $token) {
                $supportTokenMap[$token] = true;
            }
        }

        $ideas = [];
        foreach ($moneyPages as $page) {
            $tokens = seo_growth_item_tokens($page);
            $main = array_values(array_filter($tokens, static fn(string $token): bool => !isset($supportTokenMap[$token])));
            if (!$main) {
                $main = array_slice($tokens, 0, 3);
            }

            $topic = trim(implode(' ', array_slice($main, 0, 3)));
            $type = (string)($page['type'] ?? 'product');
            $isService = $type === 'service';
            $isLanding = in_array($type, ['landing_page', 'seo_landing'], true);
            $baseTitle = trim((string)($page['title'] ?? 'penawaran utama'));

            $ideas[] = [
                'target_title' => $baseTitle,
                'target_type' => $type,
                'target_url' => (string)($page['url'] ?? ''),
                'idea_title' => $isLanding
                    ? 'Panduan memilih ' . ($topic ?: $baseTitle) . ' sebelum menghubungi admin'
                    : ($isService ? 'Cara memilih layanan ' : 'Tips memilih ') . ($topic ?: $baseTitle) . ' yang tepat',
                'intent' => $isLanding ? 'Commercial Investigation' : ($isService ? 'Service Consideration' : 'Product Consideration'),
                'format' => $isLanding ? 'Artikel pendukung + FAQ' : 'Artikel SEO + CTA internal link',
                'priority_score' => min(100, max(45, 100 - (int)($page['score'] ?? 70) + ((int)($page['internal_link_count'] ?? 0) <= 1 ? 24 : 8) + ((int)($page['meta']['body_words'] ?? 0) < 250 ? 18 : 5))),
                'keyword_seed' => array_slice($tokens, 0, 5),
                'brief' => 'Buat konten edukasi yang menjawab keraguan calon pembeli, lalu arahkan ke halaman target dengan CTA natural.',
            ];
        }

        usort($ideas, static fn(array $a, array $b): int => ((int)($b['priority_score'] ?? 0) <=> (int)($a['priority_score'] ?? 0)) ?: strcmp((string)($a['idea_title'] ?? ''), (string)($b['idea_title'] ?? '')));
        return array_slice($ideas, 0, max(1, $limit));
    }
}

if (!function_exists('seo_growth_cluster_map')) {
    function seo_growth_cluster_map(array $items, int $limit = 8): array
    {
        $clusters = [];
        foreach ($items as $item) {
            if (empty($item['indexable'])) {
                continue;
            }
            $tokens = seo_growth_item_tokens($item);
            $clusterKey = $tokens[0] ?? (string)($item['type'] ?? 'umkm');
            $clusterKey = $clusterKey !== '' ? $clusterKey : 'umkm';
            if (!isset($clusters[$clusterKey])) {
                $clusters[$clusterKey] = [
                    'cluster' => $clusterKey,
                    'pages' => [],
                    'money_pages' => 0,
                    'support_pages' => 0,
                    'score_sum' => 0,
                    'tokens' => [],
                ];
            }
            $type = (string)($item['type'] ?? 'static_page');
            $clusters[$clusterKey]['pages'][] = $item;
            $clusters[$clusterKey]['score_sum'] += (int)($item['score'] ?? 0);
            if (in_array($type, ['product', 'service', 'landing_page', 'seo_landing'], true)) {
                $clusters[$clusterKey]['money_pages']++;
            } else {
                $clusters[$clusterKey]['support_pages']++;
            }
            foreach (array_slice($tokens, 0, 5) as $token) {
                $clusters[$clusterKey]['tokens'][$token] = ($clusters[$clusterKey]['tokens'][$token] ?? 0) + 1;
            }
        }

        $rows = [];
        foreach ($clusters as $cluster) {
            $total = count($cluster['pages']);
            arsort($cluster['tokens']);
            $rows[] = [
                'cluster' => $cluster['cluster'],
                'total_pages' => $total,
                'money_pages' => (int)$cluster['money_pages'],
                'support_pages' => (int)$cluster['support_pages'],
                'score_average' => $total > 0 ? (int)round($cluster['score_sum'] / $total) : 100,
                'keyword_seeds' => array_slice(array_keys($cluster['tokens']), 0, 6),
                'pages' => array_slice($cluster['pages'], 0, 5),
            ];
        }

        usort($rows, static fn(array $a, array $b): int => ((int)($b['money_pages'] ?? 0) <=> (int)($a['money_pages'] ?? 0)) ?: ((int)($b['total_pages'] ?? 0) <=> (int)($a['total_pages'] ?? 0)));
        return array_slice($rows, 0, max(1, $limit));
    }
}

if (!function_exists('seo_growth_sprint_plan')) {
    function seo_growth_sprint_plan(array $planner): array
    {
        $quickLinks = (array)($planner['link_recommendations'] ?? []);
        $ideas = (array)($planner['content_gap_ideas'] ?? []);
        $clusters = (array)($planner['clusters'] ?? []);
        $summary = (array)($planner['summary'] ?? []);
        $counts = (array)($summary['counts'] ?? []);

        return [
            [
                'week' => 'Minggu 1',
                'focus' => 'Fix fondasi halaman prioritas',
                'task' => ((int)($counts['warning'] ?? 0) + (int)($counts['error'] ?? 0)) > 0
                    ? 'Poles title, description, alt image, dan konten tipis pada halaman yang sudah ada.'
                    : 'Review halaman terbaik dan siapkan internal link menuju penawaran utama.',
            ],
            [
                'week' => 'Minggu 2',
                'focus' => 'Bangun internal link',
                'task' => $quickLinks
                    ? 'Tambahkan ' . min(5, count($quickLinks)) . ' internal link dari artikel/portfolio ke produk, layanan, atau landing page.'
                    : 'Tambahkan CTA/link dari halaman edukasi ke halaman transaksi yang paling penting.',
            ],
            [
                'week' => 'Minggu 3',
                'focus' => 'Tambah konten pendukung',
                'task' => $ideas
                    ? 'Buat 2-3 artikel dari ide prioritas: ' . (string)($ideas[0]['idea_title'] ?? 'konten pendukung produk/jasa') . '.'
                    : 'Buat artikel FAQ, perbandingan, testimoni, atau studi kasus sesuai niche bisnis.',
            ],
            [
                'week' => 'Minggu 4',
                'focus' => 'Perkuat cluster dan conversion',
                'task' => $clusters
                    ? 'Rapikan cluster ' . (string)($clusters[0]['cluster'] ?? 'utama') . ' dengan landing page, artikel pendukung, dan CTA WhatsApp/form.'
                    : 'Buat satu cluster konten utama yang menghubungkan artikel, katalog, landing page, dan form.',
            ],
        ];
    }
}

if (!function_exists('seo_growth_planner_summary')) {
    function seo_growth_planner_summary(): array
    {
        $summary = function_exists('universal_seo_summary') ? universal_seo_summary('all') : ['items' => [], 'counts' => []];
        $items = (array)($summary['items'] ?? []);
        $linkRecommendations = seo_growth_link_recommendations($items, 20);
        $contentGapIdeas = seo_growth_content_gap_ideas($items, 16);
        $clusters = seo_growth_cluster_map($items, 10);
        $moneyPages = seo_growth_money_pages($items);
        $supportPages = seo_growth_support_pages($items);

        $planner = [
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'money_page_count' => count($moneyPages),
            'support_page_count' => count($supportPages),
            'link_recommendations' => $linkRecommendations,
            'content_gap_ideas' => $contentGapIdeas,
            'clusters' => $clusters,
        ];
        $planner['sprint_plan'] = seo_growth_sprint_plan($planner);

        return $planner;
    }
}
