<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| DYNAMIC CONTENT ENGINE
|--------------------------------------------------------------------------
| Controlled dynamic content for homepage sections.
| Rotation is deterministic by date/week, not random on every reload.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

function dynamic_hash_index(string $seed, int $total): int
{
    if ($total <= 0) {
        return 0;
    }

    return (int)(abs(crc32($seed)) % $total);
}

function dynamic_rotate_items(array $items, int $limit, string $seed): array
{
    $items = array_values(array_filter($items, static fn($item): bool => is_array($item)));
    $total = count($items);

    if ($total === 0 || $limit <= 0) {
        return [];
    }

    $start = dynamic_hash_index($seed, $total);
    $rotated = [];

    for ($i = 0; $i < $total; $i++) {
        $rotated[] = $items[($start + $i) % $total];
    }

    return array_slice($rotated, 0, $limit);
}

function dynamic_format_date_id(?string $date = null): string
{
    $timestamp = $date ? strtotime($date) : time();

    if (!$timestamp) {
        $timestamp = time();
    }

    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)] ?? date('F', $timestamp);
    $year = date('Y', $timestamp);

    return $day . ' ' . $month . ' ' . $year;
}

function dynamic_content_updated_at(): string
{
    $dates = [];

    foreach (all_products() as $product) {
        foreach (['updated_at', 'published_at', 'created_at'] as $field) {
            if (!empty($product[$field]) && strtotime((string)$product[$field])) {
                $dates[] = strtotime((string)$product[$field]);
                break;
            }
        }
    }

    foreach (all_articles() as $article) {
        foreach (['updated_at', 'published_at', 'created_at'] as $field) {
            if (!empty($article[$field]) && strtotime((string)$article[$field])) {
                $dates[] = strtotime((string)$article[$field]);
                break;
            }
        }
    }

    if (!$dates) {
        return dynamic_format_date_id();
    }

    return dynamic_format_date_id(date('Y-m-d H:i:s', max($dates)));
}

function dynamic_available_products(): array
{
    return array_values(array_filter(all_products(), static function (array $product): bool {
        $slug = trim((string)($product['slug'] ?? ''));
        $title = trim((string)($product['title'] ?? ''));
        $status = strtolower((string)($product['stock_status'] ?? 'in_stock'));

        return $slug !== '' && $title !== '' && $status !== 'out_of_stock';
    }));
}

function dynamic_daily_recommended_products(int $limit = 3): array
{
    $products = dynamic_available_products();

    usort($products, static function (array $a, array $b): int {
        $featuredCompare = (int)($b['featured'] ?? false) <=> (int)($a['featured'] ?? false);

        if ($featuredCompare !== 0) {
            return $featuredCompare;
        }

        return strtotime((string)($b['published_at'] ?? $b['created_at'] ?? ''))
            <=> strtotime((string)($a['published_at'] ?? $a['created_at'] ?? ''));
    });

    return dynamic_rotate_items($products, $limit, 'daily-products-' . date('Y-m-d'));
}

function dynamic_weekly_articles(int $limit = 3): array
{
    $articles = array_values(array_filter(all_articles(), static function (array $article): bool {
        return trim((string)($article['slug'] ?? '')) !== ''
            && trim((string)($article['title'] ?? '')) !== '';
    }));

    usort($articles, static function (array $a, array $b): int {
        $featuredCompare = (int)($b['featured'] ?? false) <=> (int)($a['featured'] ?? false);

        if ($featuredCompare !== 0) {
            return $featuredCompare;
        }

        return strtotime((string)($b['published_at'] ?? $b['created_at'] ?? ''))
            <=> strtotime((string)($a['published_at'] ?? $a['created_at'] ?? ''));
    });

    return dynamic_rotate_items($articles, $limit, 'weekly-articles-' . date('o-W'));
}

function dynamic_latest_products(int $limit = 3): array
{
    return array_slice(latest_products($limit), 0, $limit);
}

function dynamic_popular_products(int $limit = 3): array
{
    $featured = featured_products(50);

    if (count($featured) < $limit) {
        $slugs = array_map(static fn(array $product): string => (string)($product['slug'] ?? ''), $featured);

        $extra = array_values(array_filter(dynamic_available_products(), static function (array $product) use ($slugs): bool {
            return !in_array((string)($product['slug'] ?? ''), $slugs, true);
        }));

        usort($extra, static function (array $a, array $b): int {
            $priceCompare = (int)($b['price'] ?? 0) <=> (int)($a['price'] ?? 0);

            if ($priceCompare !== 0) {
                return $priceCompare;
            }

            return strtotime((string)($b['published_at'] ?? $b['created_at'] ?? ''))
                <=> strtotime((string)($a['published_at'] ?? $a['created_at'] ?? ''));
        });

        $featured = array_merge($featured, $extra);
    }

    return dynamic_rotate_items($featured, $limit, 'popular-products-' . date('Y-m-d'));
}

function dynamic_area_layanan_update(): array
{
    $updates = [
        [
            'location' => 'Jakarta Selatan',
            'title' => 'Update Area Layanan Jakarta Hari Ini',
            'text' => 'Tim melakukan pengecekan katalog, stok, kebersihan data, dan kesiapan layanan agar pilihan produk tetap akurat untuk pelanggan.',
        ],
        [
            'location' => 'Tangerang Selatan dan Sekitarnya',
            'title' => 'Update Area Layanan Tangerang Selatan Hari Ini',
            'text' => 'Pilihan produk dicek berkala, mulai dari spesifikasi, ketersediaan, area layanan, hingga kesiapan pengiriman untuk pelanggan sekitar.',
        ],
        [
            'location' => 'Depok dan Sekitarnya',
            'title' => 'Update Area Layanan Depok dan Sekitarnya',
            'text' => 'Stok produk fisik, paket, dan layanan dipantau agar pelanggan bisa mendapatkan rekomendasi produk/layanan sesuai budget, kelas, dan kebutuhan produk keluarga atau lembaga.',
        ],
        [
            'location' => 'Bekasi & Bandung',
            'title' => 'Update Titik Layanan Bekasi & Bandung',
            'text' => 'Tim layanan memantau kesiapan distribusi dan konsultasi untuk pelanggan area Jabodetabek, Bekasi, Bandung, serta sekitarnya.',
        ],
        [
            'location' => 'Surabaya',
            'title' => 'Update Area Layanan Surabaya Hari Ini',
            'text' => 'Area Surabaya menjadi salah satu titik layanan untuk memperkuat stok, jadwal, dan standar layanan customer.',
        ],
        [
            'location' => 'Bali',
            'title' => 'Update Area Bali',
            'text' => 'Area Bali dipantau sebagai bagian dari peningkatan layanan agar ketersediaan produk dan layanan tetap terjaga.',
        ],
    ];

    $update = dynamic_rotate_items($updates, 1, 'area-layanan-update-' . date('Y-m-d'))[0] ?? $updates[0];
    $update['date'] = dynamic_format_date_id();

    return $update;
}

function dynamic_rotating_faq(int $limit = 4): array
{
    $faq = [
        [
            'question' => 'Apakah stok/ketersediaan produk selalu sama setiap hari?',
            'answer' => 'Stok dapat berubah karena proses booking, survey, dan distribusi. Untuk kepastian stok terbaru, pelanggan disarankan menghubungi admin terlebih dahulu.',
        ],
        [
            'question' => 'Apakah bisa konsultasi dulu sebelum memilih produk fisik, paket, atau layanan?',
            'answer' => 'Bisa. Tim admin dapat membantu menyesuaikan pilihan produk atau layanan dengan budget, area layanan, kelas/tipe item, dan kebutuhan produk atau layanan.',
        ],
        [
            'question' => 'Apakah tersedia pilihan produk atau layanan ekonomis, medium, dan premium?',
            'answer' => 'Tersedia. Katalog dapat difilter berdasarkan kelas atau tier agar pelanggan lebih mudah membandingkan pilihan produk atau layanan.',
        ],
        [
            'question' => 'Apakah pelanggan bisa survey area layanan?',
            'answer' => 'Untuk beberapa lokasi, survey area layanan dapat dikonsultasikan dengan admin agar jadwal dan titik layanan bisa disesuaikan.',
        ],
        [
            'question' => 'Apakah harga produk/layanan bisa berubah?',
            'answer' => 'Harga dapat berubah mengikuti spesifikasi, jenis produk/layanan, kelas, stok, dan periode mendekati periode promo. Karena itu konfirmasi admin tetap disarankan.',
        ],
        [
            'question' => 'Apakah tersedia layanan layanan?',
            'answer' => 'Tersedia layanan layanan dengan pilihan paket atau layanan, termasuk konsultasi paket sesuai kebutuhan keluarga.',
        ],
        [
            'question' => 'Bagaimana cara memilih produk yang sesuai?',
            'answer' => 'Pilih berdasarkan budget, spesifikasi, jenis produk/layanan, area layanan, dan kebutuhan penerima manfaat. Admin dapat membantu memberi rekomendasi.',
        ],
    ];

    return dynamic_rotate_items($faq, $limit, 'faq-' . date('Y-m-d'));
}

/*
|--------------------------------------------------------------------------
| DYNAMIC CONTENT PHASE 2 HELPERS
|--------------------------------------------------------------------------
| Lightweight programmatic SEO helpers for product detail, article detail,
| catalog pages, and produk/layanan landing pages. All helpers are pure PHP,
| deterministic, and backward compatible with existing seed/admin data.
|--------------------------------------------------------------------------
*/

if (!function_exists('dynamic_text_clean')) {
    function dynamic_text_clean(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
    }
}

if (!function_exists('dynamic_first_filled')) {
    function dynamic_first_filled(array $values, string $fallback = ''): string
    {
        foreach ($values as $value) {
            $text = trim((string)$value);
            if ($text !== '') {
                return $text;
            }
        }

        return $fallback;
    }
}


/*
|--------------------------------------------------------------------------
| DYNAMIC CONTENT RELEVANCE GUARD v3
|--------------------------------------------------------------------------
| Universal, niche-aware matching layer. It keeps homepage content broad,
| but makes article/product/landing detail recommendations strict enough so
| unrelated content is not forced into thin pages. This stays lightweight and
| shared-hosting friendly: no external AI, no database dependency, no random
| matching.
|--------------------------------------------------------------------------
*/

if (!function_exists('dynamic_v3_lower')) {
    function dynamic_v3_lower(string $value): string
    {
        $value = trim($value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('dynamic_v3_stopwords')) {
    function dynamic_v3_stopwords(): array
    {
        return [
            'yang' => true, 'dan' => true, 'atau' => true, 'untuk' => true, 'dengan' => true, 'dalam' => true,
            'pada' => true, 'dari' => true, 'ke' => true, 'di' => true, 'ini' => true, 'itu' => true,
            'cara' => true, 'tips' => true, 'agar' => true, 'bisa' => true, 'akan' => true, 'lebih' => true,
            'sebagai' => true, 'adalah' => true, 'karena' => true, 'juga' => true, 'saja' => true,
            'produk' => true, 'layanan' => true, 'paket' => true, 'jasa' => true, 'halaman' => true,
            'umkm' => true, 'bisnis' => true, 'toko' => true, 'fokus' => true, 'menjual' => true,
            'pelanggan' => true, 'customer' => true, 'admin' => true, 'website' => true, 'template' => true,
            'contoh' => true, 'cocok' => true, 'seperti' => true, 'item' => true, 'siap' => true, 'pakai' => true,
            'detail' => true, 'kebutuhan' => true, 'mudah' => true, 'terkait' => true, 'pilihan' => true,
            'the' => true, 'and' => true, 'for' => true, 'with' => true, 'from' => true, 'your' => true,
        ];
    }
}

if (!function_exists('dynamic_v3_flatten_values')) {
    function dynamic_v3_flatten_values(mixed $value): array
    {
        $out = [];
        if (is_array($value)) {
            foreach ($value as $child) {
                foreach (dynamic_v3_flatten_values($child) as $text) {
                    $out[] = $text;
                }
            }
            return $out;
        }

        if (is_scalar($value)) {
            $text = dynamic_text_clean((string)$value);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }
}

if (!function_exists('dynamic_v3_tokenize')) {
    function dynamic_v3_tokenize(mixed $value): array
    {
        $stop = dynamic_v3_stopwords();
        $tokens = [];
        foreach (dynamic_v3_flatten_values($value) as $text) {
            $text = dynamic_v3_lower(str_replace(['_', '-', '/', '|'], ' ', $text));
            $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];
            foreach ($parts as $token) {
                $token = trim($token);
                if ($token === '' || isset($stop[$token])) {
                    continue;
                }
                if (function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') < 3 : strlen($token) < 3) {
                    continue;
                }
                if (preg_match('/^\d+$/', $token)) {
                    continue;
                }
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }
}

if (!function_exists('dynamic_v3_add_weighted_tokens')) {
    function dynamic_v3_add_weighted_tokens(array &$weighted, mixed $value, int $weight): void
    {
        foreach (dynamic_v3_tokenize($value) as $token) {
            $weighted[$token] = max((int)($weighted[$token] ?? 0), $weight);
        }
    }
}

if (!function_exists('dynamic_v3_clean_phrases')) {
    function dynamic_v3_clean_phrases(mixed $value): array
    {
        $phrases = [];
        foreach (dynamic_v3_flatten_values($value) as $text) {
            foreach (preg_split('/[,;|]+/u', $text) ?: [] as $part) {
                $phrase = dynamic_v3_lower(dynamic_text_clean($part));
                $phrase = trim($phrase, " \t\n\r\0\x0B-/");
                if ($phrase === '') {
                    continue;
                }
                if ((function_exists('mb_strlen') ? mb_strlen($phrase, 'UTF-8') : strlen($phrase)) < 3) {
                    continue;
                }
                $phrases[] = $phrase;
            }
        }

        return array_values(array_unique($phrases));
    }
}

if (!function_exists('dynamic_v3_business_mode_context')) {
    function dynamic_v3_business_mode_context(): array
    {
        $settings = function_exists('business_settings') ? business_settings() : [];
        $mode = (string)($settings['mode'] ?? $settings['business_mode'] ?? 'hybrid');
        $definitions = function_exists('business_mode_definitions') ? business_mode_definitions() : [];
        $definition = is_array($definitions[$mode] ?? null) ? $definitions[$mode] : [];
        $labels = is_array($definition['labels'] ?? null) ? $definition['labels'] : [];

        $keywords = array_values(array_filter([
            $definition['label'] ?? '',
            $definition['description'] ?? '',
            $labels['catalog'] ?? '',
            $labels['product'] ?? '',
            $labels['service'] ?? '',
            $labels['article'] ?? '',
            $labels['primary_cta'] ?? '',
        ], static fn(mixed $value): bool => trim((string)$value) !== ''));

        return [
            'mode' => $mode,
            'label' => (string)($definition['label'] ?? 'Hybrid Growth Website'),
            'keywords' => $keywords,
        ];
    }
}

if (!function_exists('dynamic_v3_item_profile')) {
    function dynamic_v3_item_profile(array $item, string $type = 'content'): array
    {
        $business = dynamic_v3_business_mode_context();
        $title = dynamic_first_filled([$item['title'] ?? '', $item['name'] ?? ''], 'Konten');
        $slug = (string)($item['slug'] ?? $item['url'] ?? '');
        $category = dynamic_first_filled([$item['category'] ?? '', $item['type'] ?? '', $item['post_type'] ?? ''], '');
        $subcategory = dynamic_first_filled([$item['subcategory'] ?? '', $item['breed'] ?? '', $item['tier'] ?? '', $item['segment'] ?? ''], '');
        $location = dynamic_first_filled([$item['location'] ?? '', $item['city'] ?? '', $item['area'] ?? ''], '');
        $tags = array_merge(
            dynamic_v3_flatten_values($item['tags'] ?? []),
            dynamic_v3_flatten_values($item['tag'] ?? []),
            dynamic_v3_flatten_values($item['keywords'] ?? []),
            dynamic_v3_flatten_values($item['focus_keyword'] ?? ''),
            dynamic_v3_flatten_values($item['meta_keywords'] ?? '')
        );
        $body = implode(' ', dynamic_v3_flatten_values([
            $item['excerpt'] ?? '',
            $item['description'] ?? '',
            $item['short_description'] ?? '',
            $item['content'] ?? '',
            $item['body'] ?? '',
        ]));

        $weighted = [];
        dynamic_v3_add_weighted_tokens($weighted, [$category], 10);
        dynamic_v3_add_weighted_tokens($weighted, [$subcategory], 8);
        dynamic_v3_add_weighted_tokens($weighted, $tags, 9);
        dynamic_v3_add_weighted_tokens($weighted, [$title], 7);
        dynamic_v3_add_weighted_tokens($weighted, [$slug], 6);
        dynamic_v3_add_weighted_tokens($weighted, [$location], 5);
        dynamic_v3_add_weighted_tokens($weighted, [$item['animal_type'] ?? '', $item['product_type'] ?? '', $item['service_type'] ?? '', $item['digital_type'] ?? ''], 6);
        if ($type === 'site') {
            dynamic_v3_add_weighted_tokens($weighted, $business['keywords'] ?? [], 3);
        }
        dynamic_v3_add_weighted_tokens($weighted, [$body], 2);

        $priorityPhrases = dynamic_v3_clean_phrases(array_merge([
            $category,
            $subcategory,
            $location,
            $item['animal_type'] ?? '',
            $item['product_type'] ?? '',
            $item['service_type'] ?? '',
            $item['digital_type'] ?? '',
        ], $tags));

        $searchText = dynamic_v3_lower(dynamic_text_clean(implode(' ', [
            $title,
            $slug,
            $category,
            $subcategory,
            $location,
            implode(' ', $tags),
            $body,
        ])));

        return [
            'type' => $type,
            'title' => $title,
            'slug' => slugify($slug !== '' ? $slug : $title),
            'category' => dynamic_v3_lower($category),
            'subcategory' => dynamic_v3_lower($subcategory),
            'location' => dynamic_v3_lower($location),
            'business_mode' => (string)($business['mode'] ?? 'hybrid'),
            'business_label' => (string)($business['label'] ?? 'Hybrid Growth Website'),
            'tokens' => $weighted,
            'priority_phrases' => $priorityPhrases,
            'search_text' => $searchText,
            'item_type_key' => ($type === 'product' && function_exists('product_item_type_key')) ? product_item_type_key($item) : '',
            'item_type_label' => ($type === 'product' && function_exists('product_item_type_label')) ? product_item_type_label($item) : '',
            'token_count' => count($weighted),
        ];
    }
}

if (!function_exists('dynamic_v3_relevance_score')) {
    function dynamic_v3_relevance_score(array $sourceProfile, array $candidateProfile): array
    {
        $score = 0;
        $reasons = [];
        $overlap = [];

        if (($sourceProfile['category'] ?? '') !== '' && ($sourceProfile['category'] ?? '') === ($candidateProfile['category'] ?? '')) {
            $score += 24;
            $reasons[] = 'kategori sama';
        }

        if (($sourceProfile['subcategory'] ?? '') !== '' && ($sourceProfile['subcategory'] ?? '') === ($candidateProfile['subcategory'] ?? '')) {
            $score += 14;
            $reasons[] = 'subkategori/tipe sama';
        }

        if (($sourceProfile['location'] ?? '') !== '' && ($sourceProfile['location'] ?? '') === ($candidateProfile['location'] ?? '')) {
            $score += 8;
            $reasons[] = 'lokasi sama';
        }

        if (($sourceProfile['type'] ?? '') === 'product' && ($candidateProfile['type'] ?? '') === 'product') {
            $sourceItemType = (string)($sourceProfile['item_type_key'] ?? '');
            $candidateItemType = (string)($candidateProfile['item_type_key'] ?? '');
            if ($sourceItemType !== '' && $candidateItemType !== '') {
                if ($sourceItemType === $candidateItemType) {
                    $score += 12;
                    $reasons[] = 'tipe item sama';
                } elseif (($sourceProfile['category'] ?? '') !== ($candidateProfile['category'] ?? '')) {
                    $score -= 18;
                }
            }
        }

        $candidateText = (string)($candidateProfile['search_text'] ?? '');
        foreach ((array)($sourceProfile['priority_phrases'] ?? []) as $phrase) {
            if ($phrase !== '' && str_contains($candidateText, $phrase)) {
                $score += 8;
                $reasons[] = 'frasa: ' . $phrase;
            }
        }

        $candidateTokens = (array)($candidateProfile['tokens'] ?? []);
        foreach ((array)($sourceProfile['tokens'] ?? []) as $token => $sourceWeight) {
            if (!isset($candidateTokens[$token])) {
                continue;
            }
            $weight = min(8, max(1, (int)$sourceWeight, (int)$candidateTokens[$token]));
            $score += $weight;
            $overlap[] = (string)$token;
        }

        if ($overlap) {
            $overlap = array_values(array_unique($overlap));
            $reasons[] = 'keyword cocok: ' . implode(', ', array_slice($overlap, 0, 5));
        }

        $score = max(0, min(100, $score));
        $reasons = array_values(array_unique(array_filter($reasons)));

        return [
            'score' => $score,
            'reasons' => array_slice($reasons, 0, 6),
            'tokens' => array_slice(array_values(array_unique($overlap)), 0, 12),
        ];
    }
}

if (!function_exists('dynamic_v3_relevance_threshold')) {
    function dynamic_v3_relevance_threshold(string $context = 'detail'): int
    {
        return match ($context) {
            'homepage' => 5,
            'landing' => 16,
            'article_detail' => 18,
            'product_detail' => 24,
            'strict' => 24,
            default => 12,
        };
    }
}

if (!function_exists('dynamic_v3_rank_related_items')) {
    function dynamic_v3_rank_related_items(array $source, array $candidates, string $sourceType, string $candidateType, int $limit = 3, string $context = 'detail'): array
    {
        $sourceProfile = dynamic_v3_item_profile($source, $sourceType);
        $sourceSlug = (string)($sourceProfile['slug'] ?? '');
        $threshold = dynamic_v3_relevance_threshold($context);
        $ranked = [];

        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $candidateProfile = dynamic_v3_item_profile($candidate, $candidateType);
            $candidateSlug = (string)($candidateProfile['slug'] ?? '');
            if ($sourceSlug !== '' && $candidateSlug !== '' && $sourceSlug === $candidateSlug && $sourceType === $candidateType) {
                continue;
            }

            $match = dynamic_v3_relevance_score($sourceProfile, $candidateProfile);
            if ((int)$match['score'] < $threshold) {
                continue;
            }

            $candidate['_dynamic_relevance'] = [
                'score' => (int)$match['score'],
                'label' => dynamic_v3_relevance_label((int)$match['score']),
                'reasons' => (array)$match['reasons'],
                'tokens' => (array)$match['tokens'],
            ];
            $ranked[] = $candidate;
        }

        usort($ranked, static function (array $a, array $b): int {
            $scoreCompare = (int)($b['_dynamic_relevance']['score'] ?? 0) <=> (int)($a['_dynamic_relevance']['score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            $dateA = strtotime((string)($a['updated_at'] ?? $a['published_at'] ?? $a['created_at'] ?? '')) ?: 0;
            $dateB = strtotime((string)($b['updated_at'] ?? $b['published_at'] ?? $b['created_at'] ?? '')) ?: 0;
            return $dateB <=> $dateA;
        });

        return array_slice($ranked, 0, $limit);
    }
}

if (!function_exists('dynamic_v3_relevance_label')) {
    function dynamic_v3_relevance_label(int $score): string
    {
        if ($score >= 45) {
            return 'Sangat relevan';
        }
        if ($score >= 24) {
            return 'Relevan';
        }
        return 'Cukup relevan';
    }
}

if (!function_exists('dynamic_v3_relevance_badge')) {
    function dynamic_v3_relevance_badge(array $item): string
    {
        $rel = is_array($item['_dynamic_relevance'] ?? null) ? $item['_dynamic_relevance'] : [];
        $score = (int)($rel['score'] ?? 0);
        $reasons = (array)($rel['reasons'] ?? []);
        if ($score <= 0) {
            return '';
        }
        $text = ($rel['label'] ?? 'Relevan') . ' · skor ' . $score;
        if ($reasons) {
            $text .= ' · ' . implode(' · ', array_slice(array_map('strval', $reasons), 0, 2));
        }
        return $text;
    }
}

if (!function_exists('dynamic_v3_homepage_source')) {
    function dynamic_v3_homepage_source(): array
    {
        $business = dynamic_v3_business_mode_context();
        return [
            'title' => SITE_NAME . ' ' . ((string)($business['label'] ?? 'Website Growth')),
            'category' => (string)($business['label'] ?? ''),
            'keywords' => (array)($business['keywords'] ?? []),
            'content' => implode(' ', (array)($business['keywords'] ?? [])),
            'slug' => 'homepage',
        ];
    }
}

if (!function_exists('dynamic_v3_contextual_homepage_articles')) {
    function dynamic_v3_contextual_homepage_articles(int $limit = 3): array
    {
        $ranked = dynamic_v3_rank_related_items(dynamic_v3_homepage_source(), all_articles(), 'site', 'article', $limit, 'homepage');
        if (count($ranked) >= $limit) {
            return $ranked;
        }

        $existing = array_flip(array_map(static fn(array $item): string => slugify((string)($item['slug'] ?? '')), $ranked));
        foreach (latest_articles($limit * 2) as $article) {
            $slug = slugify((string)($article['slug'] ?? ''));
            if ($slug !== '' && !isset($existing[$slug])) {
                $article['_dynamic_relevance'] = ['score' => 5, 'label' => 'Konten terbaru', 'reasons' => ['fallback homepage'], 'tokens' => []];
                $ranked[] = $article;
                $existing[$slug] = true;
            }
            if (count($ranked) >= $limit) {
                break;
            }
        }
        return array_slice($ranked, 0, $limit);
    }
}

if (!function_exists('dynamic_v3_contextual_homepage_products')) {
    function dynamic_v3_contextual_homepage_products(int $limit = 3): array
    {
        $ranked = dynamic_v3_rank_related_items(dynamic_v3_homepage_source(), dynamic_available_products(), 'site', 'product', $limit, 'homepage');
        if (count($ranked) >= $limit) {
            return $ranked;
        }

        $existing = array_flip(array_map(static fn(array $item): string => slugify((string)($item['slug'] ?? '')), $ranked));
        foreach (dynamic_daily_recommended_products($limit * 2) as $product) {
            $slug = slugify((string)($product['slug'] ?? ''));
            if ($slug !== '' && !isset($existing[$slug])) {
                $product['_dynamic_relevance'] = ['score' => 5, 'label' => 'Rekomendasi homepage', 'reasons' => ['fallback homepage'], 'tokens' => []];
                $ranked[] = $product;
                $existing[$slug] = true;
            }
            if (count($ranked) >= $limit) {
                break;
            }
        }
        return array_slice($ranked, 0, $limit);
    }
}

if (!function_exists('dynamic_v3_guard_report')) {
    function dynamic_v3_guard_report(int $limit = 80): array
    {
        $business = dynamic_v3_business_mode_context();
        $articles = all_articles();
        $products = dynamic_available_products();
        $rows = [];
        $counts = [
            'items' => 0,
            'strong' => 0,
            'ok' => 0,
            'weak' => 0,
            'empty_context' => 0,
            'article_rows' => 0,
            'product_rows' => 0,
        ];

        foreach (array_slice($articles, 0, $limit) as $article) {
            $profile = dynamic_v3_item_profile($article, 'article');
            $relatedArticles = dynamic_article_related_articles($article, 3);
            $relatedProducts = dynamic_article_related_products($article, 3);
            $bestScores = array_merge(
                array_map(static fn(array $item): int => (int)($item['_dynamic_relevance']['score'] ?? 0), $relatedArticles),
                array_map(static fn(array $item): int => (int)($item['_dynamic_relevance']['score'] ?? 0), $relatedProducts)
            );
            $best = $bestScores ? max($bestScores) : 0;
            $status = $best >= 24 ? 'strong' : ($best >= 14 ? 'ok' : 'weak');
            if ((int)$profile['token_count'] < 4) {
                $counts['empty_context']++;
            }
            $counts[$status]++;
            $counts['items']++;
            $counts['article_rows']++;
            $rows[] = [
                'type' => 'Artikel',
                'title' => (string)($article['title'] ?? 'Artikel'),
                'url' => function_exists('article_permalink') ? article_permalink($article) : url('artikel/' . slugify((string)($article['slug'] ?? ''))),
                'category' => (string)($article['category'] ?? ''),
                'token_count' => (int)$profile['token_count'],
                'best_score' => $best,
                'status' => $status,
                'related_count' => count($relatedArticles) + count($relatedProducts),
                'note' => $status === 'weak' ? 'Perlu kategori/tag/keyword yang lebih spesifik atau konten pendukung yang lebih dekat.' : 'Dynamic content masih punya relasi yang aman.',
            ];
        }

        foreach (array_slice($products, 0, $limit) as $product) {
            $profile = dynamic_v3_item_profile($product, 'product');
            $relatedProducts = dynamic_products_by_semantic_match($product, 3);
            $relatedArticles = dynamic_product_related_articles($product, 3);
            $bestScores = array_merge(
                array_map(static fn(array $item): int => (int)($item['_dynamic_relevance']['score'] ?? 0), $relatedProducts),
                array_map(static fn(array $item): int => (int)($item['_dynamic_relevance']['score'] ?? 0), $relatedArticles)
            );
            $best = $bestScores ? max($bestScores) : 0;
            $status = $best >= 24 ? 'strong' : ($best >= 14 ? 'ok' : 'weak');
            if ((int)$profile['token_count'] < 4) {
                $counts['empty_context']++;
            }
            $counts[$status]++;
            $counts['items']++;
            $counts['product_rows']++;
            $rows[] = [
                'type' => 'Produk/Layanan',
                'title' => (string)($product['title'] ?? 'Produk'),
                'url' => function_exists('product_permalink') ? product_permalink($product) : url('produk/' . slugify((string)($product['slug'] ?? ''))),
                'category' => (string)($product['category'] ?? ''),
                'token_count' => (int)$profile['token_count'],
                'best_score' => $best,
                'status' => $status,
                'related_count' => count($relatedProducts) + count($relatedArticles),
                'note' => $status === 'weak' ? 'Perlu tag/keyword/kategori produk yang lebih jelas atau artikel pendukung yang relevan.' : 'Dynamic content masih punya relasi yang aman.',
            ];
        }

        $health = $counts['items'] > 0 ? (int)round((($counts['strong'] * 100) + ($counts['ok'] * 72) + ($counts['weak'] * 35)) / $counts['items']) : 100;

        return [
            'version' => 'Dynamic Content Relevance Guard v4 - Product Detail Merge',
            'business' => $business,
            'health_score' => max(0, min(100, $health)),
            'counts' => $counts,
            'rows' => $rows,
            'generated_at' => date(DATE_ATOM),
            'guardrails' => [
                'Homepage boleh lebih luas, tapi tetap mengikuti niche bisnis utama.',
                'Artikel/detail produk/landing page memakai strict relevance threshold; product detail memakai threshold lebih ketat agar lintas tipe tidak random.',
                'Tidak ada fallback acak di detail page jika skor relevansi terlalu rendah.',
                'Rekomendasi dipilih dari kategori, tag, keyword, slug, judul, isi konten, tipe item, lokasi, dan business mode.',
            ],
        ];
    }
}

if (!function_exists('dynamic_infer_animal_type')) {
    function dynamic_infer_animal_type(array $item): string
    {
        // Legacy helper name kept for backward compatibility.
        // The returned value is now universal: physical, service, digital, course, menu, package, booking, or custom.
        if (function_exists('product_item_type_label')) {
            return product_item_type_label($item);
        }

        $explicit = trim((string)($item['item_type'] ?? $item['animal_type'] ?? $item['product_type'] ?? $item['type'] ?? ''));
        if ($explicit !== '') {
            return ucwords(str_replace(['_', '-'], ' ', $explicit));
        }

        $haystack = strtolower(dynamic_text_clean(implode(' ', [
            $item['title'] ?? '',
            $item['category'] ?? '',
            $item['subcategory'] ?? '',
            $item['description'] ?? '',
            $item['content'] ?? '',
        ])));

        if (str_contains($haystack, 'digital') || str_contains($haystack, 'ebook') || str_contains($haystack, 'course')) {
            return 'Produk Digital';
        }
        if (str_contains($haystack, 'jasa') || str_contains($haystack, 'layanan') || str_contains($haystack, 'konsultasi')) {
            return 'Jasa / Layanan';
        }
        if (str_contains($haystack, 'paket') || str_contains($haystack, 'bundle')) {
            return 'Paket / Bundle';
        }

        return 'Produk Fisik';
    }
}

if (!function_exists('dynamic_product_universal_terms')) {
    function dynamic_product_universal_terms(array $product): array
    {
        $typeKey = function_exists('product_item_type_key') ? product_item_type_key($product) : 'physical';
        $typeLabel = function_exists('product_item_type_label') ? product_item_type_label($product) : dynamic_infer_animal_type($product);
        $typeIcon = function_exists('product_item_type_icon') ? product_item_type_icon($product) : '📦';
        $isDigital = function_exists('product_is_digital') ? product_is_digital($product) : in_array($typeKey, ['digital', 'course', 'ebook'], true);
        $isService = function_exists('product_is_service_like') ? product_is_service_like($product) : in_array($typeKey, ['service', 'booking'], true);

        $terms = [
            'physical' => [
                'noun' => 'produk fisik',
                'customer' => 'pembeli',
                'action' => 'memesan',
                'guide_title' => 'Panduan Memilih & Memesan Produk',
                'confirm_label' => 'Cek Sebelum Memesan',
                'ready_title' => 'Siap Memesan Produk Ini?',
                'ready_text' => 'Gunakan form pemesanan agar kebutuhan, jumlah, alamat, dan preferensi pembayaran tercatat rapi di dashboard admin.',
                'related_title' => 'Produk Terkait',
                'related_text' => 'Lihat juga pilihan produk lain yang masih relevan dengan kebutuhan ini.',
            ],
            'service' => [
                'noun' => 'layanan',
                'customer' => 'calon klien',
                'action' => 'berkonsultasi',
                'guide_title' => 'Panduan Konsultasi & Pemesanan Layanan',
                'confirm_label' => 'Cek Sebelum Konsultasi',
                'ready_title' => 'Siap Konsultasi Layanan Ini?',
                'ready_text' => 'Gunakan form konsultasi agar kebutuhan, scope pekerjaan, lokasi, jadwal, dan budget awal tercatat jelas.',
                'related_title' => 'Layanan Terkait',
                'related_text' => 'Lihat juga layanan lain yang masih relevan dengan kebutuhan ini.',
            ],
            'digital' => [
                'noun' => 'produk digital',
                'customer' => 'pembeli digital',
                'action' => 'membeli dan mengakses',
                'guide_title' => 'Panduan Akses Produk Digital',
                'confirm_label' => 'Cek Sebelum Membeli',
                'ready_title' => 'Siap Membeli Produk Digital Ini?',
                'ready_text' => 'Gunakan form pembelian agar akses file, link, atau member area bisa ditindaklanjuti dengan rapi setelah pembayaran.',
                'related_title' => 'Produk Digital Terkait',
                'related_text' => 'Lihat juga produk digital lain yang masih satu topik atau melengkapi kebutuhan ini.',
            ],
            'course' => [
                'noun' => 'kelas online',
                'customer' => 'peserta',
                'action' => 'mendaftar dan belajar',
                'guide_title' => 'Panduan Akses Kelas / Program',
                'confirm_label' => 'Cek Sebelum Daftar',
                'ready_title' => 'Siap Daftar Program Ini?',
                'ready_text' => 'Gunakan form pendaftaran agar akses kelas, jadwal, materi, dan kebutuhan belajar tercatat rapi.',
                'related_title' => 'Program Terkait',
                'related_text' => 'Lihat juga kelas, materi, atau program lain yang relevan dengan topik ini.',
            ],
            'ebook' => [
                'noun' => 'e-book / file download',
                'customer' => 'pembaca',
                'action' => 'membeli dan mengunduh',
                'guide_title' => 'Panduan Download & Penggunaan File',
                'confirm_label' => 'Cek Sebelum Download',
                'ready_title' => 'Siap Download Produk Ini?',
                'ready_text' => 'Gunakan form pembelian agar akses file dan instruksi download tercatat rapi setelah pembayaran.',
                'related_title' => 'File Digital Terkait',
                'related_text' => 'Lihat juga file, template, atau panduan lain yang masih satu kebutuhan.',
            ],
            'package' => [
                'noun' => 'paket / bundle',
                'customer' => 'pembeli paket',
                'action' => 'mengambil paket',
                'guide_title' => 'Panduan Memilih Paket',
                'confirm_label' => 'Cek Sebelum Ambil Paket',
                'ready_title' => 'Siap Ambil Paket Ini?',
                'ready_text' => 'Gunakan form pemesanan agar isi paket, jumlah, jadwal, dan preferensi layanan tercatat rapi.',
                'related_title' => 'Paket Terkait',
                'related_text' => 'Lihat juga paket atau bundle lain yang masih relevan dengan kebutuhan ini.',
            ],
            'menu' => [
                'noun' => 'menu kuliner',
                'customer' => 'pelanggan',
                'action' => 'memesan menu',
                'guide_title' => 'Panduan Memilih & Memesan Menu',
                'confirm_label' => 'Cek Sebelum Memesan',
                'ready_title' => 'Siap Pesan Menu Ini?',
                'ready_text' => 'Gunakan form pemesanan agar jumlah, alamat, jadwal, dan catatan khusus tercatat jelas.',
                'related_title' => 'Menu Terkait',
                'related_text' => 'Lihat juga menu atau paket kuliner lain yang masih sesuai selera dan kebutuhan ini.',
            ],
            'booking' => [
                'noun' => 'booking / reservasi',
                'customer' => 'calon pelanggan',
                'action' => 'booking jadwal',
                'guide_title' => 'Panduan Booking & Reservasi',
                'confirm_label' => 'Cek Sebelum Booking',
                'ready_title' => 'Siap Booking Jadwal?',
                'ready_text' => 'Gunakan form booking agar jadwal, lokasi, kebutuhan, dan preferensi layanan tercatat rapi.',
                'related_title' => 'Pilihan Booking Terkait',
                'related_text' => 'Lihat juga pilihan booking atau layanan lain yang masih relevan.',
            ],
            'custom' => [
                'noun' => 'custom order',
                'customer' => 'calon pelanggan',
                'action' => 'request kebutuhan',
                'guide_title' => 'Panduan Request Custom Order',
                'confirm_label' => 'Cek Sebelum Request',
                'ready_title' => 'Siap Request Custom Order?',
                'ready_text' => 'Gunakan form inquiry agar kebutuhan custom, referensi, budget, dan deadline bisa ditindaklanjuti dengan jelas.',
                'related_title' => 'Custom Order Terkait',
                'related_text' => 'Lihat juga pilihan custom order atau layanan lain yang masih dekat dengan kebutuhan ini.',
            ],
        ];

        $base = $terms[$typeKey] ?? $terms[$isDigital ? 'digital' : ($isService ? 'service' : 'physical')];
        $base['type_key'] = $typeKey;
        $base['type_label'] = $typeLabel;
        $base['type_icon'] = $typeIcon;
        $base['is_digital'] = $isDigital;
        $base['is_service'] = $isService;

        return $base;
    }
}

if (!function_exists('dynamic_product_context')) {
    function dynamic_product_context(array $product): array
    {
        $terms = dynamic_product_universal_terms($product);
        $title = dynamic_first_filled([$product['title'] ?? '', $product['name'] ?? ''], 'Produk / Layanan');
        $category = dynamic_first_filled([$product['category'] ?? '', $product['type'] ?? ''], 'Katalog');
        $subcategory = dynamic_first_filled([$product['subcategory'] ?? '', $product['breed'] ?? '', $product['segment'] ?? ''], $terms['type_label']);
        $tier = dynamic_first_filled([$product['tier'] ?? '', $product['package'] ?? '', $subcategory], 'Pilihan Utama');
        $location = dynamic_first_filled([$product['location'] ?? '', $product['city'] ?? '', $product['area'] ?? ''], 'online / area layanan');
        $weight = dynamic_first_filled([$product['weight'] ?? '', $product['specification'] ?? '', $product['duration'] ?? ''], $terms['is_digital'] ? 'akses digital' : 'menyesuaikan kebutuhan');
        $price = function_exists('product_price') ? product_price($product) : (int)($product['price'] ?? 0);

        $keywords = array_values(array_filter(array_map('strval', array_merge(
            dynamic_v3_flatten_values($product['keywords'] ?? []),
            dynamic_v3_flatten_values($product['tags'] ?? []),
            dynamic_v3_flatten_values($product['focus_keyword'] ?? ''),
            [$title, $category, $subcategory, $terms['type_label'], $location]
        ))));

        $mainKeyword = dynamic_first_filled([
            $product['focus_keyword'] ?? '',
            $keywords[0] ?? '',
            trim($category . ' ' . $terms['noun']),
        ], $terms['noun']);

        return [
            'title' => $title,
            'animal_type' => $terms['type_label'], // Backward compatible key; no longer qurban-only.
            'item_type' => $terms['type_label'],
            'item_type_key' => $terms['type_key'],
            'item_type_icon' => $terms['type_icon'],
            'category' => $category,
            'subcategory' => $subcategory,
            'breed' => $subcategory, // Legacy compatible key.
            'tier' => $tier,
            'location' => $location,
            'weight' => $weight,
            'price' => $price,
            'is_layanan' => (bool)$terms['is_service'],
            'is_service' => (bool)$terms['is_service'],
            'is_digital' => (bool)$terms['is_digital'],
            'main_keyword' => $mainKeyword,
            'keywords' => array_values(array_unique($keywords)),
            'terms' => $terms,
        ];
    }
}

if (!function_exists('dynamic_product_seo_paragraphs')) {
    function dynamic_product_seo_paragraphs(array $product): array
    {
        $ctx = dynamic_product_context($product);
        $terms = (array)($ctx['terms'] ?? dynamic_product_universal_terms($product));
        $description = dynamic_text_clean($product['excerpt'] ?? $product['description'] ?? '');
        $category = strtolower((string)$ctx['category']);
        $location = (string)$ctx['location'];
        $keyword = strtolower((string)$ctx['main_keyword']);

        $first = $description !== ''
            ? limit_chars($description, 230)
            : sprintf(
                '%s adalah %s dalam kategori %s yang bisa dipertimbangkan untuk kebutuhan %s. Informasi produk, harga, spesifikasi, dan ketersediaan dibuat jelas agar pengunjung lebih mudah mengambil keputusan.',
                (string)$ctx['title'],
                strtolower((string)$ctx['item_type']),
                $category,
                $keyword
            );

        $second = sprintf(
            'Sebelum %s, pelanggan bisa membandingkan detail seperti kategori, fitur utama, area/kanal layanan %s, status ketersediaan, dan estimasi harga agar pilihan yang diambil lebih sesuai dengan kebutuhan.',
            (string)($terms['action'] ?? 'memesan'),
            $location
        );

        if (!empty($ctx['is_digital'])) {
            $second = sprintf(
                'Untuk produk digital, cek format file atau akses, cara penggunaan, mode pengiriman, serta dukungan setelah pembelian agar %s benar-benar sesuai kebutuhan.',
                strtolower((string)$ctx['title'])
            );
        } elseif (!empty($ctx['is_service'])) {
            $second = sprintf(
                'Untuk layanan, cek scope pekerjaan, jadwal, area layanan %s, output yang didapat, serta alur konsultasi agar kebutuhan bisa ditangani dengan jelas.',
                $location
            );
        }

        return array_values(array_filter([$first, $second], static fn(string $text): bool => trim($text) !== ''));
    }
}

if (!function_exists('dynamic_product_trust_items')) {
    function dynamic_product_trust_items(array $product): array
    {
        $ctx = dynamic_product_context($product);
        $terms = (array)($ctx['terms'] ?? dynamic_product_universal_terms($product));

        $items = [
            [
                'title' => 'Data Produk/Layanan Tercatat',
                'text' => 'Informasi utama seperti kategori, harga, status, spesifikasi, dan CTA disiapkan agar mudah dicek ulang oleh pelanggan maupun admin.',
            ],
            [
                'title' => 'Konsultasi Sesuai Kebutuhan',
                'text' => 'Admin dapat membantu memastikan pilihan ' . strtolower((string)$ctx['item_type']) . ' sesuai budget, tujuan, lokasi, dan preferensi pelanggan.',
            ],
            [
                'title' => 'Konfirmasi Sebelum ' . ucfirst((string)($terms['action'] ?? 'memesan')),
                'text' => 'Pelanggan tetap dapat mengonfirmasi stok, jadwal, akses, scope layanan, atau detail teknis sebelum lanjut ke pembayaran atau booking.',
            ],
        ];

        if (!empty($ctx['is_digital'])) {
            $items[2] = [
                'title' => 'Akses Digital Bisa Dikonfirmasi',
                'text' => 'Format file, link akses, member area, dan instruksi penggunaan dapat dijelaskan sebelum pembelian agar pelanggan tidak bingung setelah checkout.',
            ];
        } elseif (!empty($ctx['is_service'])) {
            $items[2] = [
                'title' => 'Scope Layanan Bisa Disepakati',
                'text' => 'Output pekerjaan, jadwal, revisi, area layanan, dan kebutuhan teknis bisa dikonfirmasi sebelum proses layanan dimulai.',
            ];
        }

        return $items;
    }
}

if (!function_exists('dynamic_product_faq')) {
    function dynamic_product_faq(array $product, int $limit = 5): array
    {
        $ctx = dynamic_product_context($product);
        $terms = (array)($ctx['terms'] ?? dynamic_product_universal_terms($product));
        $customFaq = is_array($product['faq'] ?? null) ? $product['faq'] : [];

        $faq = [
            [
                'question' => 'Apakah ' . $ctx['title'] . ' masih tersedia?',
                'answer' => 'Ketersediaan dapat berubah mengikuti stok, jadwal, kapasitas layanan, atau akses digital. Silakan hubungi admin untuk konfirmasi terbaru sebelum melanjutkan.',
            ],
            [
                'question' => 'Apa yang perlu dicek sebelum ' . ($terms['action'] ?? 'memesan') . '?',
                'answer' => 'Cek kategori, spesifikasi, benefit, area/kanal layanan, harga, metode pembayaran, dan catatan khusus agar pilihan sesuai kebutuhan.',
            ],
            [
                'question' => 'Apakah bisa konsultasi dulu?',
                'answer' => 'Bisa. Admin dapat membantu menjelaskan detail produk atau layanan, memberi rekomendasi, dan memastikan pilihan sesuai budget serta tujuan penggunaan.',
            ],
            [
                'question' => 'Bagaimana proses pemesanan atau pembeliannya?',
                'answer' => 'Klik tombol CTA pada halaman ini, isi form checkout atau inquiry, lalu admin akan menindaklanjuti detail kebutuhan, pembayaran, pengiriman, akses, atau jadwal layanan.',
            ],
            [
                'question' => 'Apakah harga bisa berubah?',
                'answer' => 'Harga dapat berubah mengikuti stok, periode promo, spesifikasi, scope layanan, atau kebutuhan custom. Konfirmasi admin tetap disarankan sebelum pembayaran.',
            ],
        ];

        if (!empty($ctx['is_digital'])) {
            $faq[1] = [
                'question' => 'Bagaimana cara mendapatkan akses digitalnya?',
                'answer' => 'Akses bisa berupa link, file download, atau member area sesuai pengaturan produk. Detail akses akan diinformasikan setelah pembayaran atau konfirmasi admin.',
            ];
            $faq[4] = [
                'question' => 'Apakah produk digital bisa langsung digunakan?',
                'answer' => 'Sebagian produk digital bisa langsung digunakan setelah akses diberikan. Cek instruksi penggunaan, format file, dan kebutuhan aplikasi pendukung pada deskripsi produk.',
            ];
        } elseif (!empty($ctx['is_service'])) {
            $faq[1] = [
                'question' => 'Bagaimana menentukan scope layanan?',
                'answer' => 'Scope layanan ditentukan dari kebutuhan, target hasil, jadwal, lokasi/kanal kerja, dan tingkat kompleksitas. Admin dapat membantu membuat penawaran yang sesuai.',
            ];
            $faq[4] = [
                'question' => 'Apakah jadwal layanan bisa disesuaikan?',
                'answer' => 'Jadwal dapat dikonsultasikan mengikuti kapasitas tim, lokasi, dan kebutuhan pekerjaan. Hubungi admin untuk mengecek slot yang tersedia.',
            ];
        }

        $merged = array_merge($customFaq, $faq);
        $clean = [];
        foreach ($merged as $item) {
            if (!is_array($item) || empty($item['question']) || empty($item['answer'])) {
                continue;
            }
            $key = dynamic_v3_lower((string)$item['question']);
            if (isset($clean[$key])) {
                continue;
            }
            $clean[$key] = [
                'question' => dynamic_text_clean($item['question']),
                'answer' => dynamic_text_clean($item['answer']),
            ];
        }

        return array_slice(array_values($clean), 0, $limit);
    }
}

if (!function_exists('dynamic_products_by_location')) {
    function dynamic_products_by_location(array $currentProduct, int $limit = 3): array
    {
        $location = strtolower(trim((string)($currentProduct['location'] ?? '')));
        if ($location === '') {
            return [];
        }

        $sameLocation = array_values(array_filter(dynamic_available_products(), static function (array $product) use ($location): bool {
            return strtolower(trim((string)($product['location'] ?? ''))) === $location;
        }));

        return dynamic_v3_rank_related_items(
            $currentProduct,
            $sameLocation,
            'product',
            'product',
            $limit,
            'product_detail'
        );
    }
}

if (!function_exists('dynamic_products_by_semantic_match')) {
    function dynamic_products_by_semantic_match(array $currentProduct, int $limit = 3): array
    {
        return dynamic_v3_rank_related_items(
            $currentProduct,
            dynamic_available_products(),
            'product',
            'product',
            $limit,
            'product_detail'
        );
    }
}

if (!function_exists('dynamic_article_content_with_toc')) {
    function dynamic_article_content_with_toc(string $html): array
    {
        $toc = [];
        $counter = 0;

        $content = preg_replace_callback('/<(h2|h3)([^>]*)>(.*?)<\/\\1>/is', static function (array $matches) use (&$toc, &$counter): string {
            $tag = strtolower($matches[1]);
            $attrs = (string)$matches[2];
            $inner = (string)$matches[3];
            $text = dynamic_text_clean($inner);

            if ($text === '') {
                return $matches[0];
            }

            $id = '';
            if (preg_match('/\sid=["\']([^"\']+)["\']/i', $attrs, $idMatch)) {
                $id = trim((string)$idMatch[1]);
            }

            if ($id === '') {
                $id = slugify($text) ?: 'bagian-artikel';
                $counter++;
                $id .= '-' . $counter;
                $attrs = rtrim($attrs) . ' id="' . esc($id) . '"';
            }

            $toc[] = [
                'id' => $id,
                'title' => $text,
                'level' => $tag,
            ];

            return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
        }, $html) ?? $html;

        return ['content' => $content, 'toc' => $toc];
    }
}

if (!function_exists('dynamic_article_faq')) {
    function dynamic_article_faq(array $article, int $limit = 4): array
    {
        $custom = function_exists('article_faq_items') ? article_faq_items($article) : [];
        $category = dynamic_first_filled([$article['category'] ?? ''], 'Produk & Layanan');
        $title = dynamic_first_filled([$article['title'] ?? ''], 'artikel ini');

        $generic = [
            [
                'question' => 'Apakah informasi dalam artikel ini bisa dijadikan panduan awal?',
                'answer' => 'Bisa. Artikel ini disusun sebagai panduan awal. Untuk keputusan pembelian atau layanan, pelanggan tetap disarankan berkonsultasi dengan admin agar sesuai kebutuhan dan kondisi terbaru.',
            ],
            [
                'question' => 'Apakah tersedia konsultasi setelah membaca ' . $title . '?',
                'answer' => 'Tersedia konsultasi melalui WhatsApp untuk membahas kebutuhan produk, layanan, pilihan produk atau layanan, budget, area layanan, dan jadwal layanan.',
            ],
            [
                'question' => 'Apakah ada rekomendasi produk terkait kategori ' . $category . '?',
                'answer' => 'Halaman artikel dapat menampilkan rekomendasi produk yang relevan agar pembaca lebih mudah lanjut membandingkan pilihan produk atau layanan atau paket layanan.',
            ],
            [
                'question' => 'Apakah artikel akan diperbarui?',
                'answer' => 'Konten dapat diperbarui mengikuti stok, musim produk, layanan layanan, dan kebutuhan informasi pelanggan.',
            ],
        ];

        $merged = array_merge($custom, $generic);
        $clean = [];
        foreach ($merged as $item) {
            if (!is_array($item) || empty($item['question']) || empty($item['answer'])) {
                continue;
            }
            $clean[] = [
                'question' => dynamic_text_clean($item['question']),
                'answer' => dynamic_text_clean($item['answer']),
            ];
        }

        return array_slice($clean, 0, $limit);
    }
}

if (!function_exists('dynamic_article_related_products')) {
    function dynamic_article_related_products(array $article, int $limit = 3): array
    {
        return dynamic_v3_rank_related_items(
            $article,
            dynamic_available_products(),
            'article',
            'product',
            $limit,
            'article_detail'
        );
    }
}

if (!function_exists('dynamic_catalog_label')) {
    function dynamic_catalog_label(array $filters, string $fallback = 'Katalog Produk & Layanan'): string
    {
        $parts = [];
        if (trim((string)($filters['q'] ?? '')) !== '') { $parts[] = 'pencarian "' . trim((string)$filters['q']) . '"'; }
        if (trim((string)($filters['animal_type'] ?? '')) !== '') { $parts[] = trim((string)$filters['animal_type']); }
        if (trim((string)($filters['category'] ?? '')) !== '') { $parts[] = trim((string)$filters['category']); }
        if (trim((string)($filters['tier'] ?? '')) !== '') { $parts[] = 'kelas ' . trim((string)$filters['tier']); }
        if (trim((string)($filters['location'] ?? '')) !== '') { $parts[] = 'lokasi ' . trim((string)$filters['location']); }

        if (!$parts) {
            return $fallback;
        }

        return 'Katalog ' . implode(' ', $parts);
    }
}

if (!function_exists('dynamic_catalog_intro')) {
    function dynamic_catalog_intro(array $filters, int $totalProducts, string $fallback = 'Katalog Produk & Layanan'): array
    {
        $label = dynamic_catalog_label($filters, $fallback);
        $location = dynamic_first_filled([$filters['location'] ?? ''], 'Jakarta, Bandung, Surabaya, Yogyakarta, dan area layanan lain');
        $animal = dynamic_first_filled([$filters['animal_type'] ?? '', $filters['category'] ?? ''], 'produk fisik, paket, dan layanan');
        $tier = dynamic_first_filled([$filters['tier'] ?? ''], 'ekonomis, medium, sampai premium');

        return [
            'title' => $label,
            'description' => sprintf(
                'Temukan %s untuk kebutuhan produk dan layanan dengan pilihan kelas %s, area layanan %s, serta status stok yang bisa dikonfirmasi langsung ke admin.',
                strtolower($animal),
                strtolower($tier),
                $location
            ),
            'summary' => sprintf('%d produk ditemukan. Gunakan filter untuk mempersempit pilihan berdasarkan jenis produk/layanan, kategori, kelas harga, lokasi, dan status stok.', $totalProducts),
        ];
    }
}



if (!function_exists('dynamic_catalog_has_ephemeral_filter')) {
    function dynamic_catalog_has_ephemeral_filter(array $filters): bool
    {
        return trim((string)($filters['q'] ?? '')) !== ''
            || trim((string)($filters['stock_status'] ?? '')) !== '';
    }
}

if (!function_exists('dynamic_catalog_canonical')) {
    function dynamic_catalog_canonical(array $filters, int $page = 1): string
    {
        if ($page <= 1 && function_exists('seo_landing_canonical_for_filters')) {
            $cleanCanonical = seo_landing_canonical_for_filters($filters);
            if (is_string($cleanCanonical) && trim($cleanCanonical) !== '') {
                return $cleanCanonical;
            }
        }

        $query = [];
        foreach (['category', 'animal_type', 'tier', 'location'] as $key) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value !== '') {
                $query[$key] = $value;
            }
        }

        if ($page > 1) {
            $query['page'] = $page;
        }

        $path = 'katalog';
        if ($query) {
            $path .= '?' . http_build_query($query);
        }

        return url($path);
    }
}

if (!function_exists('dynamic_catalog_robots')) {
    function dynamic_catalog_robots(array $filters): string
    {
        return dynamic_catalog_has_ephemeral_filter($filters) ? 'noindex, follow' : 'index, follow';
    }
}

if (!function_exists('dynamic_catalog_active_chips')) {
    function dynamic_catalog_active_chips(array $filters): array
    {
        $labels = [
            'q' => 'Keyword',
            'category' => 'Kategori',
            'animal_type' => 'Jenis',
            'tier' => 'Kelas',
            'location' => 'Lokasi',
            'stock_status' => 'Status',
        ];

        $chips = [];
        foreach ($labels as $key => $label) {
            $value = trim((string)($filters[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $chips[] = ['label' => $label, 'value' => $value];
        }

        return $chips;
    }
}

if (!function_exists('dynamic_catalog_suggestions')) {
    function dynamic_catalog_suggestions(): array
    {
        return [
            ['label' => 'Produk Fisik', 'url' => url('katalog')],
            ['label' => 'Paket & Layanan', 'url' => url('katalog-paket')],
            ['label' => 'Paket Layanan', 'url' => url('layanan')],
            ['label' => 'Semua Produk', 'url' => url('katalog')],
        ];
    }
}

if (!function_exists('dynamic_item_list_schema_array')) {
    function dynamic_item_list_schema_array(array $items, string $name, string $url, string $type = 'CollectionPage'): array
    {
        $elements = [];
        foreach (array_values($items) as $index => $item) {
            $itemName = (string)($item['title'] ?? $item['name'] ?? 'Item');
            $itemUrl = isset($item['slug'])
                ? (isset($item['price']) ? product_url((string)$item['slug']) : article_url((string)$item['slug']))
                : $url;

            $elements[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $itemName,
                'url' => $itemUrl,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $name,
            'url' => $url,
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => $elements,
            ],
        ];
    }
}

if (!function_exists('dynamic_landing_profile')) {
    function dynamic_landing_profile(string $key): array
    {
        $profiles = [
            'produk-fisik' => [
                'title' => 'Produk Fisik Pilihan untuk Rumah, Komunitas, dan Perusahaan',
                'short_title' => 'Produk Fisik',
                'description' => 'Pilihan produk fisik dengan kelas ekonomis, medium, dan premium yang bisa disesuaikan dengan kebutuhan bisnis.',
                'filters' => ['animal_type' => 'Produk Fisik'],
                'keywords' => ['produk fisik', 'produk', 'retail', 'premium', 'hemat', 'custom'],
                'cta' => 'saya ingin konsultasi pilihan produk fisik.',
                'education' => [
                    'Pilih produk fisik berdasarkan spesifikasi, kesehatan, kelas harga, dan area layanan.',
                    'Untuk komunitas atau perusahaan, admin dapat membantu rekomendasi produk fisik sesuai kebutuhan tim atau penerima manfaat.',
                    'Konfirmasi stok, foto/video, dan jadwal pengecekan sebelum booking agar pilihan lebih aman.',
                ],
            ],
            'paket-layanan' => [
                'title' => 'Paket & Layanan Produk/Layanan Siap Konsultasi',
                'short_title' => 'Paket & Layanan',
                'description' => 'Pilihan paket dan layanan untuk produk maupun layanan dengan opsi kelas ekonomis, medium, premium, serta layanan konsultasi kebutuhan keluarga.',
                'filters' => ['animal_type' => 'Paket'],
                'keywords' => ['paket', 'layanan', 'produk', 'layanan'],
                'cta' => 'saya ingin konsultasi paket atau layanan.',
                'education' => [
                    'Sesuaikan pilihan paket atau layanan dengan kebutuhan produk, layanan, budget, dan lokasi.',
                    'Untuk layanan, konsultasikan kebutuhan jumlah item dan opsi layanan tambahan.',
                    'Admin dapat membantu cek stok terbaru serta rekomendasi produk atau layanan yang paling sesuai.',
                ],
            ],
            'layanan' => [
                'title' => 'Paket Layanan Paket & Layanan Praktis dan Amanah',
                'short_title' => 'Paket Layanan',
                'description' => 'Layanan layanan dengan pilihan paket atau layanan, konsultasi paket, dan opsi kebutuhan keluarga yang bisa disesuaikan.',
                'filters' => ['category' => 'Layanan'],
                'keywords' => ['layanan', 'paket', 'layanan', 'paket layanan'],
                'cta' => 'saya ingin konsultasi paket layanan.',
                'education' => [
                    'Pilih paket layanan sesuai kebutuhan keluarga, jumlah porsi, dan area layanan.',
                    'Pastikan jenis produk/layanan, jadwal, serta detail layanan sudah dikonfirmasi sebelum booking.',
                    'Admin dapat membantu menyesuaikan paket agar lebih praktis dan sesuai budget.',
                ],
            ],
            'layanan-paket' => [
                'title' => 'Layanan Paket untuk Kebutuhan Keluarga',
                'short_title' => 'Layanan Paket',
                'description' => 'Pilihan paket layanan dengan konsultasi paket, ketersediaan stok, dan layanan yang dapat disesuaikan kebutuhan keluarga.',
                'filters' => ['animal_type' => 'Paket', 'category' => 'Layanan'],
                'keywords' => ['layanan', 'paket', 'paket layanan'],
                'cta' => 'saya ingin konsultasi layanan paket.',
                'education' => [
                    'Paket layanan cocok untuk keluarga yang ingin layanan praktis dengan pilihan harga bertingkat.',
                    'Konfirmasi stok dan detail layanan sebelum menentukan paket.',
                    'Admin dapat membantu menyesuaikan kebutuhan dengan lokasi dan jadwal acara.',
                ],
            ],
            'layanan-layanan' => [
                'title' => 'Layanan Layanan dengan Pilihan Paket Fleksibel',
                'short_title' => 'Layanan Layanan',
                'description' => 'Pilihan layanan layanan untuk kebutuhan keluarga dengan konsultasi paket, stok, lokasi layanan, dan kebutuhan acara.',
                'filters' => ['animal_type' => 'Layanan', 'category' => 'Layanan'],
                'keywords' => ['layanan', 'layanan', 'layanan layanan'],
                'cta' => 'saya ingin konsultasi layanan layanan.',
                'education' => [
                    'Layanan layanan bisa menjadi pilihan untuk keluarga yang mencari alternatif paket layanan.',
                    'Tanyakan detail stok, harga, dan opsi layanan kepada admin.',
                    'Sesuaikan paket dengan kebutuhan acara, budget, dan lokasi layanan.',
                ],
            ],
        ];

        $profile = $profiles[$key] ?? $profiles['layanan'];
        $profile['key'] = $key;
        $profile['locations'] = ['Jakarta Selatan', 'Tangerang Selatan', 'Depok', 'Area Lokal', 'Bekasi', 'Bandung', 'Surabaya', 'Bali'];

        return $profile;
    }
}

if (!function_exists('dynamic_landing_products')) {
    function dynamic_landing_products(array $profile, int $limit = 6): array
    {
        $source = [
            'title' => (string)($profile['title'] ?? $profile['short_title'] ?? 'Landing Page'),
            'slug' => (string)($profile['key'] ?? ''),
            'category' => (string)($profile['short_title'] ?? $profile['title'] ?? ''),
            'keywords' => (array)($profile['keywords'] ?? []),
            'content' => implode(' ', array_merge((array)($profile['keywords'] ?? []), (array)($profile['education'] ?? []))),
        ];

        $filters = $profile['filters'] ?? [];
        $filtered = is_array($filters) ? filter_products($filters) : [];
        $candidates = $filtered ?: dynamic_available_products();

        $products = dynamic_v3_rank_related_items($source, $candidates, 'landing', 'product', $limit, 'landing');
        if (count($products) < $limit && $filtered) {
            $existing = array_flip(array_map(static fn(array $item): string => slugify((string)($item['slug'] ?? '')), $products));
            foreach ($filtered as $product) {
                $slug = slugify((string)($product['slug'] ?? ''));
                if ($slug === '' || isset($existing[$slug])) {
                    continue;
                }
                $product['_dynamic_relevance'] = [
                    'score' => 12,
                    'label' => 'Sesuai filter landing',
                    'reasons' => ['filter landing page'],
                    'tokens' => [],
                ];
                $products[] = $product;
                $existing[$slug] = true;
                if (count($products) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($products, 0, $limit);
    }
}

if (!function_exists('dynamic_landing_articles')) {
    function dynamic_landing_articles(array $profile, int $limit = 3): array
    {
        $source = [
            'title' => (string)($profile['title'] ?? $profile['short_title'] ?? 'Landing Page'),
            'slug' => (string)($profile['key'] ?? ''),
            'category' => (string)($profile['short_title'] ?? $profile['title'] ?? ''),
            'keywords' => (array)($profile['keywords'] ?? []),
            'content' => implode(' ', array_merge((array)($profile['keywords'] ?? []), (array)($profile['education'] ?? []))),
        ];

        return dynamic_v3_rank_related_items($source, all_articles(), 'landing', 'article', $limit, 'landing');
    }
}

if (!function_exists('dynamic_landing_faq')) {
    function dynamic_landing_faq(array $profile): array
    {
        $shortTitle = (string)($profile['short_title'] ?? 'Produk & Layanan');

        return [
            [
                'question' => 'Bagaimana cara konsultasi ' . strtolower($shortTitle) . '?',
                'answer' => 'Klik tombol WhatsApp, sampaikan kebutuhan, lokasi, budget, dan jadwal. Admin akan membantu memberikan rekomendasi yang sesuai.',
            ],
            [
                'question' => 'Apakah stok dan harga selalu sama?',
                'answer' => 'Stok dan harga dapat berubah mengikuti kondisi area layanan, spesifikasi produk, lokasi, dan periode pemesanan. Konfirmasi admin tetap disarankan.',
            ],
            [
                'question' => 'Apakah bisa memilih kelas ekonomis, medium, atau premium?',
                'answer' => 'Bisa. Pilihan kelas dapat disesuaikan dengan kebutuhan dan budget pelanggan.',
            ],
            [
                'question' => 'Apakah tersedia layanan area Jakarta, Bandung, Surabaya, dan Yogyakarta?',
                'answer' => 'Beberapa titik layanan tersedia dan dapat dikonsultasikan sesuai stok, jadwal, serta kebutuhan pengiriman atau survey.',
            ],
        ];
    }
}

/*
|--------------------------------------------------------------------------
| DYNAMIC CONTENT PHASE 2 - Template DETAIL DEEPENING HELPERS
|--------------------------------------------------------------------------
| Natural customer-facing helper data for product and article detail pages.
| These helpers keep the SEO/internal-linking logic behind the scenes while
| rendering useful buyer guidance on the front-end.
|--------------------------------------------------------------------------
*/

if (!function_exists('dynamic_product_booking_guide')) {
    function dynamic_product_booking_guide(array $product): array
    {
        $ctx = dynamic_product_context($product);
        $terms = (array)($ctx['terms'] ?? dynamic_product_universal_terms($product));
        $title = (string)$ctx['title'];
        $type = strtolower((string)$ctx['item_type']);
        $category = strtolower((string)$ctx['category']);
        $location = (string)$ctx['location'];
        $action = (string)($terms['action'] ?? 'memesan');

        $suitableFor = [
            'Pelanggan yang ingin membandingkan ' . $type . ' berdasarkan kebutuhan, budget, fitur, dan manfaat utama.',
            'Pengunjung yang ingin memahami detail ' . $category . ' sebelum ' . $action . ' agar keputusan lebih yakin.',
            'Tim, keluarga, bisnis, komunitas, atau individu yang membutuhkan solusi sesuai konteks dan tujuan penggunaan.',
        ];

        $confirmations = [
            'Ketersediaan, harga terbaru, dan status item.',
            'Spesifikasi, benefit, fitur utama, atau scope layanan yang didapat.',
            'Area/kanal layanan, metode akses, pengiriman, jadwal, atau kebutuhan teknis.',
            'Metode pembayaran, alur checkout, dan catatan khusus sebelum konfirmasi.',
        ];

        $steps = [
            [
                'title' => 'Ceritakan kebutuhan',
                'text' => 'Sampaikan tujuan, budget, lokasi/kanal, jumlah, jadwal, atau hasil yang diharapkan agar rekomendasi lebih tepat.',
            ],
            [
                'title' => 'Cek kecocokan',
                'text' => 'Bandingkan ' . $title . ' dengan kategori, spesifikasi, benefit, dan konteks kebutuhan yang sedang dicari.',
            ],
            [
                'title' => 'Konfirmasi detail',
                'text' => 'Pastikan harga, stok, akses, scope, pengiriman, atau jadwal layanan sebelum melanjutkan proses.',
            ],
            [
                'title' => 'Lanjut proses',
                'text' => 'Gunakan tombol checkout, inquiry, atau WhatsApp agar admin bisa menindaklanjuti data kebutuhan dengan rapi.',
            ],
        ];

        $priceFactors = [
            'Kategori, tipe item, fitur, dan spesifikasi yang dipilih.',
            'Jumlah, durasi, scope pekerjaan, level paket, atau kebutuhan custom.',
            'Lokasi, kanal akses, pengiriman, jadwal layanan, atau kapasitas tim.',
            'Periode promo, stok, urgensi, dan dukungan setelah pembelian.',
        ];

        if (!empty($ctx['is_digital'])) {
            $suitableFor = [
                'Pengguna yang membutuhkan file, template, materi, e-book, kelas, atau akses digital yang bisa dipakai mandiri.',
                'Pembeli yang ingin memahami format akses, instruksi penggunaan, dan benefit sebelum membeli.',
                'Bisnis, creator, pelajar, atau profesional yang ingin mempercepat pekerjaan dengan aset digital siap pakai.',
            ];
            $confirmations = [
                'Format file, link akses, member area, atau metode download.',
                'Instruksi penggunaan, lisensi, kebutuhan aplikasi pendukung, dan batasan akses.',
                'Cara mendapatkan akses setelah pembayaran dan dukungan jika link/file bermasalah.',
                'Kesesuaian topik produk digital dengan kebutuhan belajar, kerja, atau bisnis.',
            ];
            $steps[1]['title'] = 'Cek format akses';
            $steps[1]['text'] = 'Pastikan jenis file, materi, link, atau member area sesuai perangkat dan kebutuhan penggunaan.';
            $steps[3]['title'] = 'Beli & akses';
            $steps[3]['text'] = 'Lanjutkan checkout lalu ikuti instruksi akses digital dari admin atau sistem member area.';
        } elseif (!empty($ctx['is_service'])) {
            $suitableFor = [
                'Calon klien yang membutuhkan layanan dengan scope, target hasil, dan jadwal yang perlu dikonsultasikan.',
                'Bisnis atau individu yang ingin memastikan kebutuhan bisa dikerjakan sebelum melakukan pembayaran.',
                'Tim yang ingin membandingkan paket layanan, output pekerjaan, dan estimasi timeline secara jelas.',
            ];
            $confirmations = [
                'Scope pekerjaan, output yang didapat, batas revisi, dan kebutuhan data dari klien.',
                'Jadwal pengerjaan, area layanan, kanal komunikasi, dan PIC yang menindaklanjuti.',
                'Estimasi biaya, termin pembayaran, dan kebutuhan tambahan di luar paket.',
                'Target hasil dan indikator keberhasilan layanan agar ekspektasi jelas sejak awal.',
            ];
            $steps[1]['title'] = 'Diskusikan scope';
            $steps[1]['text'] = 'Admin membantu mengecek kebutuhan, target, timeline, dan paket layanan yang paling cocok.';
            $steps[3]['title'] = 'Booking / mulai layanan';
            $steps[3]['text'] = 'Setelah scope cocok, lanjutkan proses booking, pembayaran, atau persiapan data awal layanan.';
        }

        return [
            'title' => (string)($terms['guide_title'] ?? 'Panduan Memilih'),
            'confirm_label' => (string)($terms['confirm_label'] ?? 'Cek Sebelum Memesan'),
            'ready_title' => (string)($terms['ready_title'] ?? 'Siap Melanjutkan?'),
            'ready_text' => (string)($terms['ready_text'] ?? 'Gunakan form agar kebutuhan tercatat rapi.'),
            'related_title' => (string)($terms['related_title'] ?? 'Rekomendasi Terkait'),
            'related_text' => (string)($terms['related_text'] ?? 'Lihat juga pilihan lain yang masih relevan.'),
            'suitable_for' => $suitableFor,
            'confirmations' => $confirmations,
            'steps' => $steps,
            'price_factors' => $priceFactors,
        ];
    }
}

if (!function_exists('dynamic_product_context_links')) {
    function dynamic_product_context_links(array $product): array
    {
        $ctx = dynamic_product_context($product);
        $links = [];
        $catalogBase = !empty($ctx['is_service']) ? 'layanan' : 'katalog';

        if ($ctx['item_type'] !== '') {
            $links[] = [
                'label' => 'Tipe: ' . $ctx['item_type'],
                'url' => url($catalogBase . '?' . http_build_query(['item_type' => $ctx['item_type_key']])),
                'text' => 'Lihat pilihan dengan tipe item yang sama.',
                'group' => 'Tipe Item',
            ];
        }

        if ($ctx['category'] !== '') {
            $links[] = [
                'label' => 'Kategori ' . $ctx['category'],
                'url' => url($catalogBase . '?' . http_build_query(['category' => $ctx['category']])),
                'text' => 'Bandingkan item dalam kategori yang sama.',
                'group' => 'Kategori',
            ];
        }

        if ($ctx['subcategory'] !== '' && strtolower($ctx['subcategory']) !== strtolower($ctx['item_type'])) {
            $links[] = [
                'label' => 'Topik ' . $ctx['subcategory'],
                'url' => url($catalogBase . '?' . http_build_query(['subcategory' => $ctx['subcategory']])),
                'text' => 'Jelajahi pilihan dengan subkategori atau topik serupa.',
                'group' => 'Topik',
            ];
        }

        if ($ctx['tier'] !== '' && strtolower($ctx['tier']) !== 'pilihan utama') {
            $links[] = [
                'label' => 'Paket/Kelas ' . $ctx['tier'],
                'url' => url($catalogBase . '?' . http_build_query(['tier' => $ctx['tier']])),
                'text' => 'Lihat pilihan dengan level paket atau kelas yang mirip.',
                'group' => 'Paket',
            ];
        }

        if ($ctx['location'] !== '' && strtolower($ctx['location']) !== 'online / area layanan') {
            $links[] = [
                'label' => 'Area/Kanal ' . $ctx['location'],
                'url' => url($catalogBase . '?' . http_build_query(['location' => $ctx['location']])),
                'text' => 'Temukan pilihan berdasarkan lokasi, kanal, atau area layanan yang sama.',
                'group' => 'Area/Kanal',
            ];
        }

        $links[] = ['label' => !empty($ctx['is_service']) ? 'Semua Layanan' : 'Semua Katalog', 'url' => url($catalogBase), 'text' => 'Kembali ke daftar utama.', 'group' => 'Katalog'];

        $unique = [];
        foreach ($links as $link) {
            $key = (string)($link['label'] ?? '') . '|' . (string)($link['url'] ?? '');
            if ($key !== '|' && !isset($unique[$key])) {
                $unique[$key] = $link;
            }
        }

        return array_values($unique);
    }
}

if (!function_exists('dynamic_product_related_articles')) {
    function dynamic_product_related_articles(array $product, int $limit = 3): array
    {
        return dynamic_v3_rank_related_items(
            $product,
            all_articles(),
            'product',
            'article',
            $limit,
            'product_detail'
        );
    }
}

if (!function_exists('dynamic_product_howto_schema_array')) {
    function dynamic_product_howto_schema_array(array $product, array $guide): array
    {
        $ctx = dynamic_product_context($product);
        $steps = [];

        foreach (($guide['steps'] ?? []) as $index => $step) {
            if (!is_array($step)) {
                continue;
            }
            $steps[] = [
                '@type' => 'HowToStep',
                'position' => $index + 1,
                'name' => dynamic_text_clean($step['title'] ?? ''),
                'text' => dynamic_text_clean($step['text'] ?? ''),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'HowTo',
            'name' => (string)($guide['title'] ?? ('Panduan memilih ' . $ctx['title'])),
            'description' => 'Panduan singkat untuk memahami kebutuhan, mengecek kecocokan, mengonfirmasi detail, dan melanjutkan proses dengan admin.',
            'totalTime' => 'PT10M',
            'step' => $steps,
        ];
    }
}

if (!function_exists('dynamic_article_summary_points')) {
    function dynamic_article_summary_points(array $article, string $articleBody = ''): array
    {
        $title = dynamic_first_filled([$article['title'] ?? ''], 'artikel ini');
        $category = dynamic_first_filled([$article['category'] ?? ''], 'Produk & Layanan');
        $excerpt = dynamic_text_clean($article['excerpt'] ?? '');

        $points = [
            'Artikel ini membahas ' . $title . ' sebagai panduan awal sebelum memilih produk atau layanan ' . strtolower($category) . '.',
            'Pembaca bisa memakai informasi ini untuk membandingkan kebutuhan, budget, lokasi layanan, dan pilihan produk atau layanan yang tersedia.',
            'Untuk keputusan final, stok dan detail layanan tetap sebaiknya dikonfirmasi melalui admin agar sesuai kondisi terbaru.',
        ];

        if ($excerpt !== '') {
            $points[0] = limit_chars($excerpt, 150);
        }

        return $points;
    }
}

if (!function_exists('dynamic_article_next_steps')) {
    function dynamic_article_next_steps(array $article): array
    {
        $category = strtolower(dynamic_first_filled([$article['category'] ?? ''], 'produk dan layanan'));

        return [
            [
                'title' => 'Bandingkan pilihan produk',
                'text' => 'Lihat katalog yang sesuai dengan topik artikel untuk membandingkan jenis produk/layanan, kelas harga, dan area layanan.',
            ],
            [
                'title' => 'Catat kebutuhan utama',
                'text' => 'Siapkan catatan seperti budget, jumlah item, area layanan, jadwal, dan kebutuhan ' . $category . ' sebelum konsultasi.',
            ],
            [
                'title' => 'Konfirmasi ke admin',
                'text' => 'Hubungi admin untuk cek stok, harga terbaru, foto/video, dan rekomendasi yang paling sesuai.',
            ],
        ];
    }
}

if (!function_exists('dynamic_article_topic_links')) {
    function dynamic_article_topic_links(array $article): array
    {
        $text = strtolower(dynamic_text_clean(implode(' ', [
            $article['title'] ?? '',
            $article['category'] ?? '',
            $article['excerpt'] ?? '',
            $article['content'] ?? '',
            $article['focus_keyword'] ?? '',
            $article['meta_keywords'] ?? '',
        ])));

        $links = [
            ['token' => 'produk fisik', 'label' => 'Produk Fisik', 'url' => url('katalog')],
            ['token' => 'paket', 'label' => 'Paket & Layanan', 'url' => url('katalog-paket')],
            ['token' => 'layanan', 'label' => 'Layanan Layanan', 'url' => url('layanan-layanan')],
            ['token' => 'layanan', 'label' => 'Paket Layanan', 'url' => url('layanan')],
            ['token' => 'produk', 'label' => 'Paket Produk', 'url' => url('paket-produk')],
        ];

        $matched = [];
        foreach ($links as $link) {
            if (str_contains($text, (string)$link['token'])) {
                $matched[] = ['label' => $link['label'], 'url' => $link['url']];
            }
        }

        if (!$matched) {
            $matched = [
                ['label' => 'Katalog Produk', 'url' => url('katalog')],
                ['label' => 'Artikel Lainnya', 'url' => url('artikel')],
                ['label' => 'Konsultasi Layanan', 'url' => url('layanan')],
            ];
        }

        $matched[] = ['label' => 'Semua Artikel', 'url' => url('artikel')];

        $unique = [];
        foreach ($matched as $link) {
            $key = (string)($link['label'] ?? '');
            if ($key !== '' && !isset($unique[$key])) {
                $unique[$key] = $link;
            }
        }

        return array_values($unique);
    }
}

/*
|--------------------------------------------------------------------------
| DYNAMIC CONTENT PHASE 2 - Template CATALOG & LANDING DEEPENING
|--------------------------------------------------------------------------
| Customer-friendly helpers for catalog guidance, landing page service flow,
| local area content, and internal linking. Technical SEO logic stays hidden.
|--------------------------------------------------------------------------
*/



if (!function_exists('dynamic_article_related_articles')) {
    function dynamic_article_related_articles(array $article, int $limit = 3): array
    {
        return dynamic_v3_rank_related_items(
            $article,
            all_articles(),
            'article',
            'article',
            $limit,
            'article_detail'
        );
    }
}

if (!function_exists('dynamic_catalog_quick_paths')) {
    function dynamic_catalog_quick_paths(array $filters = []): array
    {
        $paths = [
            ['label' => 'Produk Fisik', 'url' => url('katalog'), 'text' => 'Lihat pilihan produk fisik untuk keluarga, komunitas, dan perusahaan.'],
            ['label' => 'Paket & Layanan', 'url' => url('katalog-paket'), 'text' => 'Bandingkan paket dan layanan untuk produk atau layanan.'],
            ['label' => 'Paket Layanan', 'url' => url('layanan'), 'text' => 'Pilih paket layanan sesuai kebutuhan keluarga.'],
            ['label' => 'Produk Fisik Premium', 'url' => url('katalog?' . http_build_query(['animal_type' => 'Produk Fisik', 'tier' => 'Premium'])), 'text' => 'Opsi produk fisik besar untuk kebutuhan produk istimewa.'],
            ['label' => 'Pilihan Ekonomis', 'url' => url('katalog?' . http_build_query(['tier' => 'Ekonomis'])), 'text' => 'Cari produk atau layanan dengan budget lebih hemat dan tetap layak.'],
            ['label' => 'Area Layanan', 'url' => url('katalog?' . http_build_query(['location' => 'Online'])), 'text' => 'Mulai dari area layanan online atau kota terdekat.'],
        ];

        $activeAnimal = strtolower(trim((string)($filters['animal_type'] ?? '')));
        $activeCategory = strtolower(trim((string)($filters['category'] ?? '')));

        if ($activeAnimal === 'produk fisik') {
            array_unshift($paths, ['label' => 'Produk Fisik Premium', 'url' => url('katalog?' . http_build_query(['animal_type' => 'Produk Fisik', 'tier' => 'Premium'])), 'text' => 'Cari opsi produk fisik premium yang tersedia di katalog.']);
        }

        if (str_contains($activeCategory, 'layanan')) {
            array_unshift($paths, ['label' => 'Layanan Paket', 'url' => url('layanan-paket'), 'text' => 'Lihat pilihan paket layanan dan konsultasi paket.']);
        }

        $unique = [];
        foreach ($paths as $path) {
            $key = (string)($path['label'] ?? '');
            if ($key !== '' && !isset($unique[$key])) {
                $unique[$key] = $path;
            }
        }

        return array_slice(array_values($unique), 0, 6);
    }
}

if (!function_exists('dynamic_catalog_buyer_path')) {
    function dynamic_catalog_buyer_path(array $filters = []): array
    {
        $label = dynamic_catalog_label($filters, 'produk produk dan layanan');

        return [
            [
                'title' => 'Tentukan kebutuhan',
                'text' => 'Pilih apakah kebutuhan untuk produk keluarga, komunitas, perusahaan, atau layanan keluarga.',
            ],
            [
                'title' => 'Bandingkan pilihan',
                'text' => 'Gunakan katalog untuk membandingkan jenis produk/layanan, kelas harga, area layanan, dan ketersediaan stok.',
            ],
            [
                'title' => 'Konsultasi admin',
                'text' => 'Sampaikan pilihan ' . strtolower($label) . ', budget, lokasi, dan jadwal agar admin bisa memberi rekomendasi yang paling sesuai.',
            ],
            [
                'title' => 'Konfirmasi booking',
                'text' => 'Pastikan foto/video, harga terbaru, lokasi, dan jadwal sudah jelas sebelum melakukan booking.',
            ],
        ];
    }
}

if (!function_exists('dynamic_catalog_faq')) {
    function dynamic_catalog_faq(array $filters = []): array
    {
        $animal = dynamic_first_filled([$filters['animal_type'] ?? ''], 'produk fisik, paket, atau layanan');
        $location = dynamic_first_filled([$filters['location'] ?? ''], 'area layanan terdekat');
        $tier = dynamic_first_filled([$filters['tier'] ?? ''], 'ekonomis, medium, atau premium');

        return [
            [
                'question' => 'Bagaimana cara memilih produk yang paling sesuai?',
                'answer' => 'Mulai dari kebutuhan utama, budget, jenis produk/layanan, area layanan, dan status stok. Setelah itu konsultasikan ke admin untuk cek rekomendasi terbaru.',
            ],
            [
                'question' => 'Apakah pilihan ' . strtolower($animal) . ' bisa dikonsultasikan dulu?',
                'answer' => 'Bisa. Admin dapat membantu membandingkan pilihan berdasarkan spesifikasi, kelas harga, lokasi, stok, dan kebutuhan produk atau layanan.',
            ],
            [
                'question' => 'Apakah tersedia kelas ' . strtolower($tier) . '?',
                'answer' => 'Pilihan kelas bergantung pada stok yang tersedia. Gunakan filter kelas di katalog atau hubungi admin untuk rekomendasi yang paling mendekati budget.',
            ],
            [
                'question' => 'Apakah bisa menanyakan stok untuk ' . strtolower($location) . '?',
                'answer' => 'Bisa. Ketersediaan lokasi dan jadwal survey atau pengiriman dapat dikonfirmasi langsung melalui WhatsApp admin.',
            ],
        ];
    }
}

if (!function_exists('dynamic_catalog_local_panels')) {
    function dynamic_catalog_local_panels(): array
    {
        return [
            [
                'location' => 'Jakarta Selatan',
                'text' => 'Cocok untuk pelanggan sekitar area layanan yang ingin mulai dari titik terdekat dan konsultasi pilihan produk atau layanan.',
                'url' => url('katalog?' . http_build_query(['location' => 'Jakarta Selatan'])),
            ],
            [
                'location' => 'Tangerang Selatan',
                'text' => 'Dapat menjadi opsi untuk cek ketersediaan produk fisik, paket, atau layanan di area sekitar.',
                'url' => url('katalog?' . http_build_query(['location' => 'Tangerang Selatan'])),
            ],
            [
                'location' => 'Bekasi & Bandung',
                'text' => 'Area layanan untuk pelanggan yang membutuhkan konsultasi stok, pengiriman, atau rekomendasi produk dari titik layanan terkait.',
                'url' => url('katalog?' . http_build_query(['location' => 'Bekasi'])),
            ],
        ];
    }
}

if (!function_exists('dynamic_landing_service_steps')) {
    function dynamic_landing_service_steps(array $profile): array
    {
        $shortTitle = (string)($profile['short_title'] ?? 'Produk & Layanan');

        return [
            [
                'title' => 'Konsultasi kebutuhan',
                'text' => 'Sampaikan kebutuhan ' . strtolower($shortTitle) . ', budget, lokasi, dan jadwal agar admin bisa memberi arahan awal.',
            ],
            [
                'title' => 'Cek rekomendasi produk',
                'text' => 'Admin membantu mencocokkan pilihan produk dengan stok, kelas harga, area layanan, dan kebutuhan acara.',
            ],
            [
                'title' => 'Konfirmasi detail',
                'text' => 'Pastikan harga terbaru, foto/video, status stok, lokasi, serta jadwal survey atau pengiriman sudah jelas.',
            ],
            [
                'title' => 'Booking pilihan terbaik',
                'text' => 'Setelah cocok, pelanggan dapat melanjutkan booking sesuai arahan admin dan prosedur yang berlaku.',
            ],
        ];
    }
}

if (!function_exists('dynamic_landing_package_options')) {
    function dynamic_landing_package_options(array $profile): array
    {
        $key = (string)($profile['key'] ?? 'layanan');

        if ($key === 'produk-fisik') {
            return [
                ['title' => 'Ekonomis', 'text' => 'Pilihan produk fisik dengan budget lebih hemat untuk kebutuhan keluarga atau patungan.'],
                ['title' => 'Medium', 'text' => 'Opsi seimbang antara spesifikasi, harga, dan kebutuhan customer.'],
                ['title' => 'Premium', 'text' => 'Pilihan produk fisik premium dengan spesifikasi lebih lengkap sesuai stok tersedia.'],
            ];
        }

        if (in_array($key, ['layanan', 'layanan-paket', 'layanan-layanan'], true)) {
            return [
                ['title' => 'Paket Praktis', 'text' => 'Cocok untuk keluarga yang ingin konsultasi pilihan produk atau layanan dan kebutuhan acara secara simpel.'],
                ['title' => 'Paket Keluarga', 'text' => 'Pilihan yang bisa disesuaikan dengan jumlah kebutuhan, lokasi, dan jadwal acara.'],
                ['title' => 'Paket Fleksibel', 'text' => 'Admin membantu menyesuaikan pilihan paket atau layanan dengan budget dan ketersediaan stok.'],
            ];
        }

        return [
            ['title' => 'Ekonomis', 'text' => 'Pilihan hemat untuk kebutuhan produk atau layanan dengan stok yang bisa dikonfirmasi.'],
            ['title' => 'Medium', 'text' => 'Pilihan seimbang untuk keluarga yang ingin membandingkan ukuran dan harga.'],
            ['title' => 'Premium', 'text' => 'Pilihan lebih tinggi untuk kebutuhan khusus sesuai ketersediaan produk/layanan.'],
        ];
    }
}

if (!function_exists('dynamic_landing_location_cards')) {
    function dynamic_landing_location_cards(array $profile): array
    {
        $shortTitle = strtolower((string)($profile['short_title'] ?? 'produk dan layanan'));
        $locations = $profile['locations'] ?? [];
        if (!is_array($locations) || !$locations) {
            $locations = ['Jakarta Selatan', 'Tangerang Selatan', 'Area Lokal', 'Bekasi', 'Bandung', 'Surabaya'];
        }

        $cards = [];
        foreach ($locations as $location) {
            $locationText = (string)$location;
            $cards[] = [
                'title' => $locationText,
                'text' => 'Cek ketersediaan ' . $shortTitle . ' untuk area ' . $locationText . ' dan sekitarnya melalui konsultasi admin.',
                'url' => url('katalog?' . http_build_query(['location' => $locationText])),
            ];
        }

        return array_slice($cards, 0, 8);
    }
}

if (!function_exists('dynamic_landing_related_links')) {
    function dynamic_landing_related_links(array $profile): array
    {
        $key = (string)($profile['key'] ?? 'layanan');
        $links = [
            ['label' => 'Katalog Produk', 'url' => url('katalog')],
            ['label' => 'Artikel Panduan', 'url' => url('artikel')],
            ['label' => 'Kontak Admin', 'url' => url('kontak')],
        ];

        if ($key !== 'produk-fisik') {
            $links[] = ['label' => 'Produk Fisik', 'url' => url('katalog')];
        }
        if ($key !== 'paket-layanan') {
            $links[] = ['label' => 'Paket & Layanan', 'url' => url('katalog-paket')];
        }
        if ($key !== 'layanan') {
            $links[] = ['label' => 'Paket Layanan', 'url' => url('layanan')];
        }
        if ($key !== 'layanan-paket') {
            $links[] = ['label' => 'Layanan Paket', 'url' => url('layanan-paket')];
        }
        if ($key !== 'layanan-layanan') {
            $links[] = ['label' => 'Layanan Layanan', 'url' => url('layanan-layanan')];
        }

        $unique = [];
        foreach ($links as $link) {
            $label = (string)($link['label'] ?? '');
            if ($label !== '' && !isset($unique[$label])) {
                $unique[$label] = $link;
            }
        }

        return array_values($unique);
    }
}

if (!function_exists('dynamic_service_schema_array')) {
    function dynamic_service_schema_array(array $profile, string $url): array
    {
        $shortTitle = (string)($profile['short_title'] ?? 'Produk & Layanan');
        $description = (string)($profile['description'] ?? 'Layanan konsultasi produk dan layanan.');
        $locations = $profile['locations'] ?? [];
        if (!is_array($locations)) {
            $locations = [];
        }

        $areaServed = array_map(static fn($location): array => [
            '@type' => 'Place',
            'name' => (string)$location,
        ], array_slice($locations, 0, 8));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $shortTitle,
            'description' => $description,
            'provider' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
                'url' => SITE_URL,
            ],
            'areaServed' => $areaServed,
            'url' => $url,
            'serviceType' => $shortTitle,
        ];
    }
}

/*
|--------------------------------------------------------------------------
| DYNAMIC CONTENT PHASE 2 - Template INTERNAL LINKING & SCHEMA REFINEMENT
|--------------------------------------------------------------------------
| Natural internal-linking helpers for product, article, catalog, and landing
| pages. Front-end labels stay customer-friendly while schema/meta support
| remains behind the scenes.
|--------------------------------------------------------------------------
*/

if (!function_exists('dynamic_v26_clean_url')) {
    function dynamic_v26_clean_url(string $url): string
    {
        return trim($url);
    }
}

if (!function_exists('dynamic_v26_unique_links')) {
    function dynamic_v26_unique_links(array $links, int $limit = 8): array
    {
        $unique = [];

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $label = dynamic_text_clean($link['label'] ?? '');
            $url = dynamic_v26_clean_url((string)($link['url'] ?? ''));
            $text = dynamic_text_clean($link['text'] ?? '');
            $group = dynamic_text_clean($link['group'] ?? 'Terkait');

            if ($label === '' || $url === '') {
                continue;
            }

            $key = strtolower($label . '|' . $url);
            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = [
                'label' => $label,
                'url' => $url,
                'text' => $text,
                'group' => $group,
            ];
        }

        return array_slice(array_values($unique), 0, $limit);
    }
}

if (!function_exists('dynamic_v26_location_links')) {
    function dynamic_v26_location_links(int $limit = 8): array
    {
        $locations = ['Jakarta Selatan', 'Tangerang Selatan', 'Depok', 'Area Lokal', 'Bekasi', 'Bandung', 'Surabaya', 'Bali'];
        $links = [];

        foreach ($locations as $location) {
            $links[] = [
                'label' => $location,
                'url' => url('katalog?' . http_build_query(['location' => $location])),
                'text' => 'Cek pilihan produk atau layanan dan konsultasi stok untuk area ' . $location . '.',
                'group' => 'Area Layanan',
            ];
        }

        return array_slice($links, 0, $limit);
    }
}

if (!function_exists('dynamic_v26_core_links')) {
    function dynamic_v26_core_links(): array
    {
        $catalogLabel = function_exists('business_label') ? business_label('catalog', 'Katalog') : 'Katalog';
        $serviceLabel = function_exists('business_label') ? business_label('service', 'Layanan') : 'Layanan';
        $articleLabel = function_exists('business_label') ? business_label('article', 'Artikel') : 'Artikel';

        return [
            [
                'label' => $catalogLabel,
                'url' => url('katalog'),
                'text' => 'Bandingkan pilihan produk, paket, atau item katalog yang tersedia.',
                'group' => 'Katalog',
            ],
            [
                'label' => 'Produk Digital / Fisik',
                'url' => url('katalog'),
                'text' => 'Lihat pilihan produk fisik, digital, menu, paket, atau custom order yang relevan.',
                'group' => 'Katalog',
            ],
            [
                'label' => 'Paket & Bundle',
                'url' => url('katalog-paket'),
                'text' => 'Bandingkan paket, bundle, atau kombinasi produk dan layanan.',
                'group' => 'Katalog',
            ],
            [
                'label' => $serviceLabel,
                'url' => url('layanan'),
                'text' => 'Konsultasikan kebutuhan layanan, scope pekerjaan, jadwal, atau paket jasa.',
                'group' => 'Layanan',
            ],
            [
                'label' => $articleLabel . ' Panduan',
                'url' => url('artikel'),
                'text' => 'Baca panduan terkait sebelum memilih produk, jasa, paket, atau solusi digital.',
                'group' => 'Panduan',
            ],
            [
                'label' => 'Kontak Admin',
                'url' => url('kontak'),
                'text' => 'Hubungi admin untuk konfirmasi ketersediaan, jadwal, akses, scope, atau penawaran.',
                'group' => 'Konsultasi',
            ],
        ];
    }
}

if (!function_exists('dynamic_v26_product_links')) {
    function dynamic_v26_product_links(array $product, int $limit = 8): array
    {
        $ctx = dynamic_product_context($product);
        $links = dynamic_product_context_links($product);
        $terms = (array)($ctx['terms'] ?? dynamic_product_universal_terms($product));

        $links[] = [
            'label' => 'Panduan Terkait',
            'url' => url('artikel'),
            'text' => 'Baca panduan yang relevan sebelum ' . (string)($terms['action'] ?? 'memesan') . '.',
            'group' => 'Panduan',
        ];

        if (!empty($ctx['is_digital'])) {
            $links[] = [
                'label' => 'Produk Digital Lainnya',
                'url' => url('katalog?' . http_build_query(['item_type' => $ctx['item_type_key']])),
                'text' => 'Bandingkan file, akses, kelas, atau aset digital lain yang relevan.',
                'group' => 'Katalog',
            ];
        } elseif (!empty($ctx['is_service'])) {
            $links[] = [
                'label' => 'Layanan Lainnya',
                'url' => url('layanan?' . http_build_query(['category' => $ctx['category']])),
                'text' => 'Lihat layanan atau paket jasa lain dalam kategori serupa.',
                'group' => 'Layanan',
            ];
        } else {
            $links[] = [
                'label' => 'Produk Sejenis',
                'url' => url('katalog?' . http_build_query(['category' => $ctx['category']])),
                'text' => 'Lihat produk lain dengan kategori atau kebutuhan yang mirip.',
                'group' => 'Katalog',
            ];
        }

        $links = array_merge($links, dynamic_v26_core_links());

        return dynamic_v26_unique_links($links, $limit);
    }
}

if (!function_exists('dynamic_v26_article_links')) {
    function dynamic_v26_article_links(array $article, int $limit = 8): array
    {
        $topicLinks = dynamic_article_topic_links($article);
        $links = [];

        foreach ($topicLinks as $link) {
            $links[] = [
                'label' => $link['label'] ?? '',
                'url' => $link['url'] ?? '',
                'text' => 'Lanjutkan ke halaman terkait untuk membandingkan pilihan dan membaca informasi tambahan.',
                'group' => 'Topik Terkait',
            ];
        }

        foreach (dynamic_article_related_products($article, 3) as $product) {
            if (empty($product['slug'])) {
                continue;
            }
            $links[] = [
                'label' => (string)($product['title'] ?? 'Produk Terkait'),
                'url' => product_url((string)$product['slug']),
                'text' => 'Produk ini relevan dengan pembahasan artikel.',
                'group' => 'Produk Rekomendasi',
            ];
        }

        return dynamic_v26_unique_links(array_merge($links, dynamic_v26_core_links()), $limit);
    }
}

if (!function_exists('dynamic_v26_catalog_links')) {
    function dynamic_v26_catalog_links(array $filters = [], int $limit = 10): array
    {
        $links = [];

        foreach (dynamic_catalog_quick_paths($filters) as $path) {
            $links[] = [
                'label' => $path['label'] ?? '',
                'url' => $path['url'] ?? '',
                'text' => $path['text'] ?? '',
                'group' => 'Arah Cepat',
            ];
        }

        $location = trim((string)($filters['location'] ?? ''));
        if ($location !== '') {
            $links[] = [
                'label' => 'Produk Area ' . $location,
                'url' => url('katalog?' . http_build_query(['location' => $location])),
                'text' => 'Lihat pilihan produk yang terkait dengan area layanan ini.',
                'group' => 'Area Layanan',
            ];
        }

        return dynamic_v26_unique_links(array_merge($links, dynamic_v26_core_links(), dynamic_v26_location_links()), $limit);
    }
}

if (!function_exists('dynamic_v26_landing_links')) {
    function dynamic_v26_landing_links(array $profile, int $limit = 10): array
    {
        $links = [];
        foreach (dynamic_landing_related_links($profile) as $link) {
            $links[] = [
                'label' => $link['label'] ?? '',
                'url' => $link['url'] ?? '',
                'text' => 'Halaman ini membantu membandingkan pilihan dan melanjutkan konsultasi.',
                'group' => 'Halaman Terkait',
            ];
        }

        $links = array_merge($links, dynamic_v26_location_links(6));

        return dynamic_v26_unique_links($links, $limit);
    }
}

if (!function_exists('dynamic_v26_link_groups')) {
    function dynamic_v26_link_groups(array $links): array
    {
        $groups = [];
        foreach ($links as $link) {
            $group = (string)($link['group'] ?? 'Terkait');
            $groups[$group][] = $link;
        }
        return $groups;
    }
}

if (!function_exists('dynamic_v26_navigation_schema_array')) {
    function dynamic_v26_navigation_schema_array(array $links, string $name = 'Halaman Terkait'): array
    {
        $items = [];
        foreach (dynamic_v26_unique_links($links, 12) as $index => $link) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string)$link['label'],
                'url' => (string)$link['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'itemListElement' => $items,
        ];
    }
}

if (!function_exists('dynamic_v26_webpage_schema_array')) {
    function dynamic_v26_webpage_schema_array(string $name, string $description, string $url, array $links = [], string $type = 'WebPage'): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => dynamic_text_clean($name),
            'description' => dynamic_text_clean($description),
            'url' => $url,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => SITE_NAME,
                'url' => SITE_URL,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
                'url' => SITE_URL,
            ],
        ];

        $related = [];
        foreach (dynamic_v26_unique_links($links, 10) as $link) {
            $related[] = (string)$link['url'];
        }

        if ($related) {
            $schema['relatedLink'] = $related;
        }

        return $schema;
    }
}

/*
|--------------------------------------------------------------------------
| Template FINAL UX + LOCAL AREA + UMKM TEMPLATE READINESS HELPERS
|--------------------------------------------------------------------------
| Public labels stay customer-friendly. These helpers centralize local-area
| content and reusable marketplace guidance so the project can later be
| adapted as a mini marketplace template for other UMKM products/services.
|--------------------------------------------------------------------------
*/

if (!function_exists('dynamic_v28_location_profiles')) {
    function dynamic_v28_location_profiles(): array
    {
        return [
            [
                'name' => 'Jakarta Selatan',
                'title' => 'Produk Pilihan Jakarta Selatan',
                'summary' => 'Pilihan area Jakarta membantu customer cek stok, paket, dan layanan dengan konsultasi yang lebih dekat.',
                'url' => url('katalog?' . http_build_query(['location' => 'Jakarta Selatan'])),
                'cta' => 'Cek Produk Jakarta',
            ],
            [
                'name' => 'Tangerang Selatan',
                'title' => 'Area Layanan Produk Tangerang Selatan',
                'summary' => 'Area Tangerang Selatan cocok untuk pelanggan yang ingin membandingkan stok produk dan layanan wilayah sekitar.',
                'url' => url('katalog?' . http_build_query(['location' => 'Tangerang Selatan'])),
                'cta' => 'Lihat Produk Tangerang Selatan',
            ],
            [
                'name' => 'Depok',
                'title' => 'Produk Pilihan Depok',
                'summary' => 'Depok menjadi salah satu area layanan untuk konsultasi stok, survey, dan pengiriman produk.',
                'url' => url('katalog?' . http_build_query(['location' => 'Depok'])),
                'cta' => 'Cek Produk Depok',
            ],
            [
                'name' => 'Area Lokal',
                'title' => 'Produk Pilihan Area Lokal',
                'summary' => 'Area lokal membantu customer menemukan pilihan produk atau layanan berdasarkan kebutuhan, budget, dan ketersediaan stok.',
                'url' => url('katalog?' . http_build_query(['location' => 'Area Lokal'])),
                'cta' => 'Lihat Produk Area Lokal',
            ],
            [
                'name' => 'Bekasi',
                'title' => 'Produk & Layanan Bekasi',
                'summary' => 'Pelanggan Bekasi bisa konsultasi pilihan produk dan layanan sesuai kebutuhan keluarga, komunitas, atau perusahaan.',
                'url' => url('katalog?' . http_build_query(['location' => 'Bekasi'])),
                'cta' => 'Cek Produk Bekasi',
            ],
            [
                'name' => 'Bandung',
                'title' => 'Produk & Layanan Bandung',
                'summary' => 'Area Bandung mendukung konsultasi pilihan produk fisik, paket, dan layanan dengan informasi stok dan jadwal yang bisa dikonfirmasi admin.',
                'url' => url('katalog?' . http_build_query(['location' => 'Bandung'])),
                'cta' => 'Cek Produk Bandung',
            ],
            [
                'name' => 'Surabaya',
                'title' => 'Produk & Layanan Surabaya',
                'summary' => 'Surabaya menjadi titik penting untuk pilihan produk fisik dengan pertimbangan spesifikasi, kelas harga, dan kesiapan distribusi.',
                'url' => url('katalog?' . http_build_query(['location' => 'Surabaya'])),
                'cta' => 'Lihat Produk Surabaya',
            ],
            [
                'name' => 'Bali',
                'title' => 'Produk & Layanan Bali',
                'summary' => 'Bali menjadi area layanan tambahan untuk pelanggan yang membutuhkan rekomendasi stok dan konsultasi produk/layanan.',
                'url' => url('katalog?' . http_build_query(['location' => 'Bali'])),
                'cta' => 'Cek Produk Bali',
            ],
        ];
    }
}

if (!function_exists('dynamic_v28_location_faq')) {
    function dynamic_v28_location_faq(): array
    {
        return [
            [
                'question' => 'Apakah bisa memilih produk berdasarkan area layanan?',
                'answer' => 'Bisa. Gunakan filter lokasi di katalog untuk melihat produk yang paling dekat dengan area layanan atau titik layanan yang tersedia.',
            ],
            [
                'question' => 'Apakah area layanan memengaruhi jadwal survey dan pengiriman?',
                'answer' => 'Lokasi dapat membantu admin memberi rekomendasi jadwal survey, pengiriman, dan pilihan stok yang paling sesuai dengan kebutuhan pelanggan.',
            ],
            [
                'question' => 'Bagaimana kalau produk di lokasi terdekat belum tersedia?',
                'answer' => 'Admin dapat membantu mencarikan alternatif dari lokasi lain yang masih relevan dengan kebutuhan, budget, dan jadwal pelanggan.',
            ],
        ];
    }
}

if (!function_exists('dynamic_v28_buyer_reassurance_items')) {
    function dynamic_v28_buyer_reassurance_items(): array
    {
        return [
            [
                'title' => 'Cek Stok Sebelum Booking',
                'text' => 'Stok bisa berubah, jadi pelanggan disarankan konfirmasi dulu melalui WhatsApp sebelum menentukan pilihan.',
            ],
            [
                'title' => 'Bisa Minta Rekomendasi',
                'text' => 'Admin dapat membantu menyesuaikan pilihan berdasarkan budget, jenis produk/layanan, kelas, lokasi, dan kebutuhan acara.',
            ],
            [
                'title' => 'Data Produk Lebih Jelas',
                'text' => 'Produk ditampilkan dengan foto, harga, lokasi, status stok, dan detail penting agar lebih mudah dibandingkan.',
            ],
        ];
    }
}

if (!function_exists('dynamic_v28_umkm_template_principles')) {
    function dynamic_v28_umkm_template_principles(): array
    {
        return [
            'Gunakan struktur produk yang bisa diganti untuk barang, layanan, jasa, paket, atau lokasi layanan UMKM.',
            'Pisahkan data bisnis, kategori, lokasi, dan konten edukasi agar template mudah diadaptasi tanpa membongkar core engine.',
            'Pertahankan halaman katalog, detail produk, artikel, landing kategori, kontak, sitemap, robots, dan schema sebagai fondasi standar.',
            'Jaga semua copy publik tetap natural untuk pelanggan; logika SEO, schema, dan internal linking berjalan di belakang layar.',
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Template - LOCAL SEO CLUSTER STRENGTHENING
|--------------------------------------------------------------------------
| Customer-facing helpers for local content clusters. These functions keep
| area pages, article hubs, catalog links, and WhatsApp intent consistent
| without showing technical SEO terms to visitors.
|--------------------------------------------------------------------------
*/

if (!function_exists('dynamic_template_location_clusters')) {
    function dynamic_template_location_clusters(): array
    {
        $profiles = function_exists('dynamic_v28_location_profiles') ? dynamic_v28_location_profiles() : [];
        $fallback = [
            ['name' => 'Jakarta Selatan', 'title' => 'Produk Pilihan Jakarta Selatan', 'summary' => 'Pilihan area Jakarta untuk customer dan sekitarnya.'],
            ['name' => 'Tangerang Selatan', 'title' => 'Area Layanan Produk Tangerang Selatan', 'summary' => 'Area Tangerang Selatan untuk cek pilihan produk fisik, paket, dan layanan.'],
            ['name' => 'Depok', 'title' => 'Produk Pilihan Depok', 'summary' => 'Area Depok untuk konsultasi stok dan kebutuhan produk.'],
            ['name' => 'Area Lokal', 'title' => 'Produk Pilihan Area Lokal', 'summary' => 'Area lokal untuk membandingkan layanan dan stok.'],
            ['name' => 'Bekasi', 'title' => 'Produk & Layanan Bekasi', 'summary' => 'Area Bekasi untuk konsultasi produk dan layanan keluarga.'],
            ['name' => 'Bandung', 'title' => 'Produk & Layanan Bandung', 'summary' => 'Area Bandung untuk cek layanan dan stok sesuai kebutuhan.'],
            ['name' => 'Surabaya', 'title' => 'Produk & Layanan Surabaya', 'summary' => 'Area Surabaya untuk pilihan produk fisik dan distribusi.'],
            ['name' => 'Bali', 'title' => 'Produk & Layanan Bali', 'summary' => 'Area Bali untuk konsultasi pilihan produk atau layanan.'],
        ];

        if (!$profiles) {
            $profiles = $fallback;
        }

        $clusters = [];
        foreach ($profiles as $profile) {
            if (!is_array($profile)) {
                continue;
            }

            $name = trim((string)($profile['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $slug = slugify($name);
            $clusters[] = [
                'name' => $name,
                'slug' => $slug,
                'title' => trim((string)($profile['title'] ?? ('Produk & Layanan ' . $name))),
                'summary' => trim((string)($profile['summary'] ?? ('Panduan layanan produk dan layanan untuk area ' . $name . '.'))),
                'url' => url('katalog?' . http_build_query(['location' => $name])),
                'article_url' => url('artikel?' . http_build_query(['kategori' => 'info-lokal', 'lokasi' => $slug])),
                'produk_url' => url('katalog?' . http_build_query(['location' => $name, 'category' => 'Produk'])),
                'layanan_url' => url('katalog?' . http_build_query(['location' => $name, 'category' => 'Layanan'])),
                'wa_url' => wa_link('saya ingin tanya stok dan layanan untuk area ' . $name . '.'),
                'cta' => trim((string)($profile['cta'] ?? ('Cek Produk ' . $name))),
            ];
        }

        return $clusters;
    }
}

if (!function_exists('dynamic_template_location_by_slug')) {
    function dynamic_template_location_by_slug(string $slug): ?array
    {
        $slug = slugify($slug);
        if ($slug === '') {
            return null;
        }

        foreach (dynamic_template_location_clusters() as $location) {
            if ((string)$location['slug'] === $slug) {
                return $location;
            }
        }

        return null;
    }
}

if (!function_exists('dynamic_template_filter_articles_by_location')) {
    function dynamic_template_filter_articles_by_location(array $articles, string $locationName): array
    {
        $locationName = trim($locationName);
        if ($locationName === '') {
            return $articles;
        }

        $tokens = array_values(array_unique(array_filter(array_map('strtolower', [
            $locationName,
            str_replace([' Jawa Tengah', ' Area Lokal'], '', $locationName),
            ...explode(' ', $locationName),
        ]))));

        $scored = [];
        foreach ($articles as $article) {
            if (!is_array($article)) {
                continue;
            }

            $text = strtolower(strip_tags(implode(' ', [
                $article['title'] ?? '',
                $article['category'] ?? '',
                $article['excerpt'] ?? '',
                $article['content'] ?? '',
                $article['focus_keyword'] ?? '',
                $article['meta_keywords'] ?? '',
                implode(' ', is_array($article['keywords'] ?? null) ? $article['keywords'] : []),
            ])));

            $score = 0;
            foreach ($tokens as $token) {
                $token = trim((string)$token);
                if ($token === '' || strlen($token) < 4) {
                    continue;
                }
                if (str_contains($text, $token)) {
                    $score += ($token === strtolower($locationName)) ? 5 : 2;
                }
            }

            if ($score > 0) {
                $article['_local_score'] = $score;
                $scored[] = $article;
            }
        }

        usort($scored, static function (array $a, array $b): int {
            $scoreCompare = (int)($b['_local_score'] ?? 0) <=> (int)($a['_local_score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strtotime((string)($b['updated_at'] ?? $b['published_at'] ?? $b['created_at'] ?? ''))
                <=> strtotime((string)($a['updated_at'] ?? $a['published_at'] ?? $a['created_at'] ?? ''));
        });

        return array_map(static function (array $article): array {
            unset($article['_local_score']);
            return $article;
        }, $scored);
    }
}

if (!function_exists('dynamic_template_article_location_links')) {
    function dynamic_template_article_location_links(array $article, int $limit = 4): array
    {
        $text = strtolower(strip_tags(implode(' ', [
            $article['title'] ?? '',
            $article['category'] ?? '',
            $article['excerpt'] ?? '',
            $article['content'] ?? '',
            $article['focus_keyword'] ?? '',
            $article['meta_keywords'] ?? '',
        ])));

        $matches = [];
        foreach (dynamic_template_location_clusters() as $location) {
            $name = strtolower((string)$location['name']);
            $short = strtolower(str_replace([' jawa tengah', ' bogor'], '', (string)$location['name']));
            if (str_contains($text, $name) || ($short !== '' && strlen($short) > 3 && str_contains($text, $short))) {
                $matches[] = $location;
            }
        }

        if (!$matches) {
            $matches = array_slice(dynamic_template_location_clusters(), 0, $limit);
        }

        return array_slice($matches, 0, $limit);
    }
}

if (!function_exists('dynamic_template_local_article_groups')) {
    function dynamic_template_local_article_groups(int $limitPerLocation = 3): array
    {
        $groups = [];
        $infoLocalArticles = articles_by_categories(['Info Lokal', 'Update Area Layanan', 'Promo & Layanan'], 1000);

        foreach (dynamic_template_location_clusters() as $location) {
            $articles = dynamic_template_filter_articles_by_location($infoLocalArticles, (string)$location['name']);
            $groups[] = [
                'location' => $location,
                'articles' => array_slice($articles, 0, $limitPerLocation),
            ];
        }

        return $groups;
    }
}

if (!function_exists('dynamic_template_location_funnel_cards')) {
    function dynamic_template_location_funnel_cards(array $location): array
    {
        $name = (string)($location['name'] ?? 'area layanan');
        return [
            [
                'label' => 'Cek Stok',
                'title' => 'Produk Area ' . $name,
                'text' => 'Lihat pilihan produk yang bisa dikonsultasikan berdasarkan lokasi layanan ini.',
                'url' => (string)($location['url'] ?? url('katalog')),
            ],
            [
                'label' => 'Produk',
                'title' => 'Pilihan Produk ' . $name,
                'text' => 'Bandingkan produk fisik, paket, atau layanan untuk kebutuhan produk keluarga, komunitas, atau lembaga.',
                'url' => (string)($location['produk_url'] ?? url('katalog?category=Produk')),
            ],
            [
                'label' => 'Layanan',
                'title' => 'Layanan Layanan ' . $name,
                'text' => 'Konsultasikan pilihan paket atau layanan layanan sesuai kebutuhan keluarga.',
                'url' => (string)($location['layanan_url'] ?? url('layanan')),
            ],
        ];
    }
}

if (!function_exists('dynamic_template_location_schema_array')) {
    function dynamic_template_location_schema_array(array $location, string $url): array
    {
        $name = (string)($location['name'] ?? 'Area Layanan');
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'Panduan Produk & Layanan ' . $name,
            'description' => (string)($location['summary'] ?? ('Panduan layanan produk dan layanan untuk area ' . $name . '.')),
            'url' => $url,
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => SITE_NAME,
                'url' => SITE_URL,
            ],
            'about' => [
                '@type' => 'Place',
                'name' => $name,
            ],
            'provider' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
                'url' => SITE_URL,
            ],
        ];
    }
}
