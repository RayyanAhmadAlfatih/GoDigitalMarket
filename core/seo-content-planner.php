<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_content_title_case')) {
    function seo_content_title_case(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?: '');
        if ($text === '') {
            return 'Topik Utama';
        }
        return function_exists('mb_convert_case') ? mb_convert_case($text, MB_CASE_TITLE, 'UTF-8') : ucwords($text);
    }
}

if (!function_exists('seo_content_slug_id')) {
    function seo_content_slug_id(string $text): string
    {
        $text = function_exists('slugify') ? slugify($text) : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $text) ?: '', '-'));
        return $text !== '' ? $text : 'content-brief';
    }
}

if (!function_exists('seo_content_priority_label')) {
    function seo_content_priority_label(int $score): string
    {
        if (function_exists('seo_growth_priority_label')) {
            return seo_growth_priority_label($score);
        }
        return $score >= 85 ? 'Tinggi' : ($score >= 65 ? 'Sedang' : 'Rendah');
    }
}

if (!function_exists('seo_content_priority_class')) {
    function seo_content_priority_class(int $score): string
    {
        if (function_exists('seo_growth_priority_class')) {
            return seo_growth_priority_class($score);
        }
        return $score >= 85 ? 'admin-status-pill admin-status-pill--error' : ($score >= 65 ? 'admin-status-pill admin-status-pill--warning' : 'admin-status-pill admin-status-pill--info');
    }
}

if (!function_exists('seo_content_intent_label')) {
    function seo_content_intent_label(string $intent): string
    {
        return match ($intent) {
            'transactional' => 'Transaksional',
            'commercial' => 'Commercial Investigation',
            'educational' => 'Edukasi SEO',
            'trust' => 'Trust Builder',
            'local' => 'Local SEO',
            default => 'Growth Content',
        };
    }
}

if (!function_exists('seo_content_type_label')) {
    function seo_content_type_label(string $type): string
    {
        return match ($type) {
            'article' => 'Artikel SEO',
            'faq' => 'FAQ / Objection Handling',
            'comparison' => 'Konten Perbandingan',
            'case_study' => 'Portfolio / Studi Kasus',
            'landing_support' => 'Support Landing Page',
            default => 'Konten Growth',
        };
    }
}

if (!function_exists('seo_content_extract_tokens')) {
    function seo_content_extract_tokens(array $seed): array
    {
        $raw = implode(' ', array_map(static fn(mixed $value): string => is_array($value) ? implode(' ', array_map('strval', $value)) : (string)$value, $seed));
        if (function_exists('seo_growth_tokens')) {
            return seo_growth_tokens($raw, 8);
        }
        $raw = strtolower(strip_tags($raw));
        $parts = preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $raw) ?: '') ?: [];
        $parts = array_values(array_filter($parts, static fn(string $word): bool => strlen($word) >= 3));
        return array_slice(array_values(array_unique($parts)), 0, 8);
    }
}

if (!function_exists('seo_content_brief_outline')) {
    function seo_content_brief_outline(string $title, array $keywords, string $intent, string $targetTitle): array
    {
        $mainKeyword = $keywords[0] ?? $targetTitle;
        $mainKeyword = trim((string)$mainKeyword) !== '' ? (string)$mainKeyword : 'bisnis';

        if ($intent === 'commercial' || $intent === 'transactional') {
            return [
                'Masalah utama calon pembeli sebelum memilih ' . $mainKeyword,
                'Kriteria memilih solusi/produk yang tepat dan aman',
                'Perbandingan opsi, paket, harga, atau cara kerja secara natural',
                'Bukti trust: testimoni, studi kasus, proses, garansi, atau FAQ',
                'Arahkan ke ' . ($targetTitle !== '' ? $targetTitle : 'halaman penawaran') . ' dengan CTA WhatsApp/form/checkout',
            ];
        }

        if ($intent === 'trust') {
            return [
                'Ceritakan konteks masalah dan tujuan customer',
                'Tampilkan proses kerja, solusi, dan alasan strategi dipilih',
                'Tunjukkan hasil, dampak, atau perubahan yang relevan',
                'Tambahkan pembelajaran dan FAQ singkat',
                'Arahkan pembaca ke layanan, portfolio, atau form konsultasi',
            ];
        }

        return [
            'Jawab pertanyaan utama pembaca sejak paragraf awal',
            'Bahas poin penting dengan contoh sesuai niche bisnis',
            'Tambahkan checklist, tips praktis, atau kesalahan yang perlu dihindari',
            'Sisipkan internal link ke halaman produk/jasa/landing yang relevan',
            'Tutup dengan CTA ringan menuju WhatsApp, form, katalog, atau checkout',
        ];
    }
}

if (!function_exists('seo_content_brief_questions')) {
    function seo_content_brief_questions(array $keywords, string $targetTitle): array
    {
        $main = $keywords[0] ?? $targetTitle;
        $main = trim((string)$main) !== '' ? (string)$main : 'produk atau layanan ini';
        return [
            'Apa yang perlu diperhatikan sebelum memilih ' . $main . '?',
            'Berapa kisaran biaya atau paket yang paling cocok?',
            'Bagaimana proses order, konsultasi, atau pengerjaannya?',
            'Apa bedanya solusi ini dengan opsi lain?',
            'Kapan calon customer sebaiknya menghubungi admin?',
        ];
    }
}

if (!function_exists('seo_content_build_brief')) {
    function seo_content_build_brief(array $source, int $index = 0): array
    {
        $targetTitle = trim((string)($source['target_title'] ?? $source['title'] ?? 'Halaman utama'));
        $targetType = (string)($source['target_type'] ?? $source['type'] ?? 'product');
        $targetUrl = (string)($source['target_url'] ?? $source['url'] ?? '');
        $keywords = array_values(array_filter(array_map('strval', (array)($source['keyword_seed'] ?? $source['shared_tokens'] ?? []))));
        if (!$keywords) {
            $keywords = seo_content_extract_tokens([$targetTitle, $source['idea_title'] ?? '', $source['brief'] ?? '']);
        }

        $priority = (int)($source['priority_score'] ?? 70);
        $ideaTitle = trim((string)($source['idea_title'] ?? ''));
        if ($ideaTitle === '') {
            $ideaTitle = 'Panduan lengkap ' . ($keywords ? implode(' ', array_slice($keywords, 0, 3)) : $targetTitle) . ' untuk calon customer';
        }

        $intent = 'educational';
        $contentType = 'article';
        if (in_array($targetType, ['product', 'service', 'landing_page', 'seo_landing'], true)) {
            $intent = $priority >= 80 ? 'commercial' : 'educational';
            $contentType = $targetType === 'seo_landing' || $targetType === 'landing_page' ? 'landing_support' : 'article';
        }
        if (str_contains(strtolower($ideaTitle), 'memilih') || str_contains(strtolower($ideaTitle), 'sebelum')) {
            $intent = 'commercial';
        }
        if (str_contains(strtolower($ideaTitle), 'testimoni') || str_contains(strtolower($ideaTitle), 'hasil') || str_contains(strtolower($ideaTitle), 'studi kasus')) {
            $intent = 'trust';
            $contentType = 'case_study';
        }

        $week = 'Minggu ' . (string)(($index % 4) + 1);
        $id = seo_content_slug_id($ideaTitle . '-' . $index);
        $cta = in_array($targetType, ['product', 'service'], true)
            ? 'Arahkan pembaca ke katalog/halaman detail lalu CTA WhatsApp, form, atau checkout.'
            : 'Arahkan pembaca ke landing page, form, atau halaman konsultasi yang paling relevan.';

        return [
            'id' => $id,
            'week' => $week,
            'priority_score' => min(100, max(30, $priority)),
            'priority_label' => seo_content_priority_label($priority),
            'title' => $ideaTitle,
            'content_type' => $contentType,
            'content_type_label' => seo_content_type_label($contentType),
            'intent' => $intent,
            'intent_label' => seo_content_intent_label($intent),
            'target_title' => $targetTitle,
            'target_type' => $targetType,
            'target_url' => $targetUrl,
            'keyword_seed' => array_slice($keywords, 0, 6),
            'suggested_slug' => seo_content_slug_id($ideaTitle),
            'meta_title_template' => function_exists('mb_substr') ? mb_substr($ideaTitle, 0, 58, 'UTF-8') : substr($ideaTitle, 0, 58),
            'meta_description_template' => 'Pelajari ' . strtolower($ideaTitle) . ' sebelum memilih solusi terbaik untuk kebutuhan Anda.',
            'outline' => seo_content_brief_outline($ideaTitle, $keywords, $intent, $targetTitle),
            'faq_questions' => seo_content_brief_questions($keywords, $targetTitle),
            'internal_link_target' => $targetUrl,
            'internal_link_anchor' => $keywords ? implode(' ', array_slice($keywords, 0, 3)) : $targetTitle,
            'cta_note' => $cta,
            'brief_note' => (string)($source['brief'] ?? 'Buat konten yang menjawab kebutuhan pembaca, membangun trust, lalu mengarah ke conversion.'),
        ];
    }
}

if (!function_exists('seo_content_extra_backlog_from_clusters')) {
    function seo_content_extra_backlog_from_clusters(array $clusters): array
    {
        $backlog = [];
        foreach ($clusters as $cluster) {
            $clusterName = trim((string)($cluster['cluster'] ?? 'bisnis'));
            $keywords = array_values(array_filter(array_map('strval', (array)($cluster['keyword_seeds'] ?? []))));
            $topic = seo_content_title_case($clusterName);
            $backlog[] = [
                'cluster' => $clusterName,
                'title' => 'FAQ lengkap seputar ' . $topic,
                'content_type' => 'faq',
                'intent' => 'commercial',
                'keyword_seed' => array_slice($keywords, 0, 6),
                'reason' => 'FAQ membantu menangkap long-tail keyword dan menjawab keberatan calon customer.',
                'recommended_cta' => 'Tambahkan CTA ke katalog, layanan, atau form konsultasi yang paling relevan.',
            ];
            $backlog[] = [
                'cluster' => $clusterName,
                'title' => 'Studi kasus / contoh penggunaan ' . $topic,
                'content_type' => 'case_study',
                'intent' => 'trust',
                'keyword_seed' => array_slice($keywords, 0, 6),
                'reason' => 'Konten bukti membantu menaikkan trust dan conversion dari traffic SEO.',
                'recommended_cta' => 'Arahkan ke WhatsApp, form, portfolio, atau halaman penawaran utama.',
            ];
        }
        return $backlog;
    }
}

if (!function_exists('seo_content_calendar')) {
    function seo_content_calendar(array $briefs): array
    {
        $calendar = [];
        for ($i = 1; $i <= 4; $i++) {
            $calendar['Minggu ' . $i] = [
                'week' => 'Minggu ' . $i,
                'theme' => match ($i) {
                    1 => 'Quick win dan fondasi halaman prioritas',
                    2 => 'Konten edukasi dan internal link',
                    3 => 'Konten commercial investigation',
                    default => 'Trust builder, FAQ, dan conversion polish',
                },
                'output_target' => $i === 1 ? '2 artikel + 3 internal link' : ($i === 4 ? '1 studi kasus + 1 FAQ + review CTA' : '2-3 konten pendukung'),
                'items' => [],
            ];
        }

        foreach ($briefs as $brief) {
            $week = (string)($brief['week'] ?? 'Minggu 1');
            if (!isset($calendar[$week])) {
                $calendar[$week] = ['week' => $week, 'theme' => 'Konten growth tambahan', 'output_target' => '2 konten pendukung', 'items' => []];
            }
            $calendar[$week]['items'][] = $brief;
        }

        return array_values($calendar);
    }
}

if (!function_exists('seo_content_planner_summary')) {
    function seo_content_planner_summary(): array
    {
        $growth = function_exists('seo_growth_planner_summary') ? seo_growth_planner_summary() : [];
        $ideas = (array)($growth['content_gap_ideas'] ?? []);
        $links = (array)($growth['link_recommendations'] ?? []);
        $clusters = (array)($growth['clusters'] ?? []);
        $summary = (array)($growth['summary'] ?? []);

        $briefSources = $ideas;
        foreach (array_slice($links, 0, 8) as $link) {
            $briefSources[] = [
                'target_title' => (string)($link['target_title'] ?? ''),
                'target_type' => (string)($link['target_type'] ?? ''),
                'target_url' => (string)($link['target_url'] ?? ''),
                'idea_title' => 'Konten pendukung untuk ' . (string)($link['target_title'] ?? 'halaman utama'),
                'brief' => 'Buat artikel/FAQ singkat yang bisa menjadi sumber internal link menuju target ini.',
                'keyword_seed' => (array)($link['shared_tokens'] ?? []),
                'priority_score' => (int)($link['priority_score'] ?? 65),
            ];
        }

        $seen = [];
        $briefs = [];
        foreach ($briefSources as $index => $source) {
            $brief = seo_content_build_brief((array)$source, $index);
            $key = (string)$brief['suggested_slug'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $briefs[] = $brief;
        }

        usort($briefs, static fn(array $a, array $b): int => ((int)($b['priority_score'] ?? 0) <=> (int)($a['priority_score'] ?? 0)) ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));
        foreach ($briefs as $idx => &$brief) {
            $brief['week'] = 'Minggu ' . (string)(($idx % 4) + 1);
        }
        unset($brief);

        $backlog = seo_content_extra_backlog_from_clusters($clusters);
        $calendar = seo_content_calendar($briefs);
        $keywordSet = [];
        foreach ($briefs as $brief) {
            foreach ((array)($brief['keyword_seed'] ?? []) as $keyword) {
                $keyword = trim((string)$keyword);
                if ($keyword !== '') {
                    $keywordSet[$keyword] = true;
                }
            }
        }

        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'summary' => $summary,
            'growth' => $growth,
            'briefs' => array_slice($briefs, 0, 24),
            'calendar' => $calendar,
            'backlog' => array_slice($backlog, 0, 20),
            'metrics' => [
                'brief_count' => count($briefs),
                'high_priority_count' => count(array_filter($briefs, static fn(array $brief): bool => (int)($brief['priority_score'] ?? 0) >= 85)),
                'cluster_count' => count($clusters),
                'keyword_count' => count($keywordSet),
                'seo_score' => (int)($summary['score_average'] ?? 100),
            ],
        ];
    }
}
