<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONTENT ENGINE
|--------------------------------------------------------------------------
| SEO content management helpers
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| LOAD ARTICLES
|--------------------------------------------------------------------------
*/

function article_with_source(array $article, string $source): array
{
    $article['_source'] = $article['_source'] ?? $source;
    $article['source'] = $article['source'] ?? $source;
    return $article;
}

function article_dedupe_by_slug(array $articles): array
{
    $unique = [];
    foreach ($articles as $article) {
        $slug = (string)($article['slug'] ?? '');
        if ($slug === '') {
            $slug = slugify((string)($article['title'] ?? 'artikel'));
            $article['slug'] = $slug;
        }
        // Later items win. Stored/admin/import content safely overrides default example content with the same slug.
        $unique[$slug] = $article;
    }
    return array_values($unique);
}

function all_articles(): array
{
    static $articles = null;

    if ($articles === null) {
        $storedArticles = array_map(
            static fn(array $article): array => normalize_article(article_with_source($article, (string)($article['source'] ?? 'admin'))),
            article_storage_all()
        );

        if ($storedArticles || article_starter_content_initialized()) {
            $publicArticles = array_values(array_filter(
                $storedArticles,
                static fn(array $article): bool => (string)($article['status'] ?? 'published') === 'published'
            ));
            $articles = article_dedupe_by_slug($publicArticles);
        } else {
            $seedArticles = array_map(
                static fn(array $article): array => normalize_article(article_with_source($article, 'seed')),
                require DATA_PATH . '/articles.php'
            );
            $articles = article_dedupe_by_slug(array_values(array_filter(
                $seedArticles,
                static fn(array $article): bool => (string)($article['status'] ?? 'published') === 'published'
            )));
        }

        usort($articles, static function (array $a, array $b): int {
            return strtotime($b['published_at'] ?? '') <=> strtotime($a['published_at'] ?? '');
        });
    }

    return $articles;
}

function article_storage_path(): string
{
    return STORAGE_PATH . '/articles.json';
}

function article_storage_mode(): string
{
    return (function_exists('storage_mysql_enabled') && storage_mysql_enabled('articles')) ? 'mysql' : 'json';
}

function normalize_article(array $article): array
{
    $article['id'] = (int)($article['id'] ?? time());
    $article['title'] = trim((string)($article['title'] ?? ''));
    $article['slug'] = slugify((string)(($article['slug'] ?? '') ?: $article['title']));
    $article['category'] = article_category_label(trim((string)($article['category'] ?? 'Layanan')));
    $article['excerpt'] = trim((string)($article['excerpt'] ?? ''));
    $article['image'] = trim((string)($article['image'] ?? ''));
    $article['author'] = trim((string)($article['author'] ?? SITE_NAME));
    $article['published_at'] = trim((string)($article['published_at'] ?? date('Y-m-d H:i:s')));
    $article['updated_at'] = trim((string)($article['updated_at'] ?? date('Y-m-d H:i:s')));
    $status = strtolower(trim((string)($article['status'] ?? 'published')));
    $article['status'] = in_array($status, ['draft', 'published'], true) ? $status : 'published';
    $article['featured'] = (bool)($article['featured'] ?? false);
    $article['content'] = (string)($article['content'] ?? '');
    $article['meta_title'] = trim((string)($article['meta_title'] ?? ''));
    $article['meta_description'] = trim((string)($article['meta_description'] ?? ''));
    $article['meta_keywords'] = trim((string)($article['meta_keywords'] ?? ''));
    $article['canonical_url'] = trim((string)($article['canonical_url'] ?? ''));
    $article['og_title'] = trim((string)($article['og_title'] ?? ''));
    $article['og_description'] = trim((string)($article['og_description'] ?? ''));
    $article['focus_keyword'] = trim((string)($article['focus_keyword'] ?? ''));
    $article['robots'] = trim((string)($article['robots'] ?? 'index, follow'));
    $article['breadcrumb_title'] = trim((string)($article['breadcrumb_title'] ?? ($article['title'] ?? '')));
    $article['image_alt'] = trim((string)($article['image_alt'] ?? ($article['title'] ?? '')));
    $article['image_title'] = trim((string)($article['image_title'] ?? ($article['title'] ?? '')));
    $article['schema_type'] = trim((string)($article['schema_type'] ?? 'Article'));
    $article['source'] = trim((string)($article['source'] ?? 'admin'));
    $article['_source'] = $article['source'];
    $article['legacy_url'] = trim((string)($article['legacy_url'] ?? ''));
    $article['original_url'] = trim((string)($article['original_url'] ?? ''));
    $article['wp_post_id'] = trim((string)($article['wp_post_id'] ?? ''));
    $article['wp_post_type'] = trim((string)($article['wp_post_type'] ?? ''));
    $article['migration_batch_id'] = trim((string)($article['migration_batch_id'] ?? ''));
    $article['faq_json'] = trim((string)($article['faq_json'] ?? ''));
    $article['whatsapp_label'] = trim((string)($article['whatsapp_label'] ?? 'Chat WhatsApp'));
    $article['whatsapp_phone'] = preg_replace('/\D+/', '', (string)($article['whatsapp_phone'] ?? ''));
    $article['whatsapp_text'] = trim((string)($article['whatsapp_text'] ?? ''));

    if (is_string($article['keywords'] ?? null)) {
        $article['keywords'] = array_values(array_filter(array_map('trim', explode(',', (string)$article['keywords']))));
    }

    if (!is_array($article['keywords'] ?? null)) {
        $article['keywords'] = [];
    }

    $article['reading_time'] = (string)($article['reading_time'] ?? '') ?: estimate_reading_time($article['content']);
    $article['faq'] = is_array($article['faq'] ?? null) ? $article['faq'] : [];

    return $article;
}

function article_storage_all(): array
{
    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('articles')) {
        $rows = db()->query('SELECT * FROM articles ORDER BY published_at DESC, id DESC')->fetchAll();
        return array_map(static function (array $row): array {
            $row['featured'] = (bool)($row['featured'] ?? false);
            $row['keywords'] = array_values(array_filter(array_map('trim', explode(',', (string)($row['keywords'] ?? '')))));
            $row['faq_json'] = (string)($row['faq_json'] ?? '');
            return normalize_article($row);
        }, $rows ?: []);
    }

    article_runtime_bootstrap_from_seed_if_needed();

    $customPath = article_storage_path();
    if (!is_file($customPath)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($customPath), true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_map('normalize_article', $decoded);
}

function managed_articles(): array
{
    return article_storage_all();
}

/*
|--------------------------------------------------------------------------
| GET ARTICLE BY SLUG
|--------------------------------------------------------------------------
*/

function get_article_by_slug(
    string $slug
): ?array {

    foreach (all_articles() as $article) {

        if (
            ($article['slug'] ?? '') === $slug
        ) {

            return $article;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| GET ARTICLE BY ID
|--------------------------------------------------------------------------
*/

function get_article_by_id(
    int $id
): ?array {

    foreach (all_articles() as $article) {

        if (
            (int)($article['id'] ?? 0) === $id
        ) {

            return $article;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| GET FEATURED ARTICLES
|--------------------------------------------------------------------------
*/

function featured_articles(
    int $limit = 6
): array {

    $results = array_filter(

        all_articles(),

        static function ($article) {

            return
                (bool)($article['featured'] ?? false);
        }
    );

    return array_slice(
        array_values($results),
        0,
        $limit
    );
}

/*
|--------------------------------------------------------------------------
| GET LATEST ARTICLES
|--------------------------------------------------------------------------
*/

function latest_articles(
    int $limit = 10
): array {

    $articles = all_articles();

    usort(

        $articles,

        static function (
            array $a,
            array $b
        ): int {

            return strtotime(
                $b['published_at'] ?? ''
            ) <=> strtotime(
                $a['published_at'] ?? ''
            );
        }
    );

    return array_slice(
        $articles,
        0,
        $limit
    );
}

/*
|--------------------------------------------------------------------------
| GET ARTICLES BY CATEGORY
|--------------------------------------------------------------------------
*/

function articles_by_category(
    string $category,
    int $limit = 10
): array {

    return articles_by_categories([$category], $limit);
}

if (!function_exists('articles_by_categories')) {

    function articles_by_categories(
        array $categories,
        int $limit = 10,
        array $excludeSlugs = []
    ): array {

        $wanted = array_values(array_unique(array_filter(array_map(
            static fn($category): string => article_category_label((string)$category),
            $categories
        ))));

        if (!$wanted) {
            return [];
        }

        $exclude = array_flip(array_filter(array_map('strval', $excludeSlugs)));
        $results = [];

        foreach (all_articles() as $article) {
            $slug = (string)($article['slug'] ?? '');
            if ($slug !== '' && isset($exclude[$slug])) {
                continue;
            }

            $category = article_category_label((string)($article['category'] ?? ''));
            if (in_array($category, $wanted, true)) {
                $results[] = $article;
            }
        }

        return array_slice($results, 0, max(0, $limit));
    }
}

if (!function_exists('latest_articles_by_updated')) {

    function latest_articles_by_updated(
        int $limit = 10,
        array $excludeSlugs = []
    ): array {

        $exclude = array_flip(array_filter(array_map('strval', $excludeSlugs)));
        $articles = [];

        foreach (all_articles() as $article) {
            $slug = (string)($article['slug'] ?? '');
            if ($slug !== '' && isset($exclude[$slug])) {
                continue;
            }
            $articles[] = $article;
        }

        usort($articles, static function (array $a, array $b): int {
            $aTime = strtotime((string)($a['updated_at'] ?? $a['published_at'] ?? '')) ?: 0;
            $bTime = strtotime((string)($b['updated_at'] ?? $b['published_at'] ?? '')) ?: 0;
            return $bTime <=> $aTime;
        });

        return array_slice($articles, 0, max(0, $limit));
    }
}

/*
|--------------------------------------------------------------------------
| RELATED ARTICLES
|--------------------------------------------------------------------------
*/

function related_articles(
    array $currentArticle,
    int $limit = 3
): array {

    $currentSlug = (string)($currentArticle['slug'] ?? '');
    $currentCategory = article_category_label((string)($currentArticle['category'] ?? ''));
    $relatedCategories = article_related_category_labels($currentCategory);
    $currentTokens = article_relation_tokens($currentArticle);
    $scored = [];

    foreach (all_articles() as $article) {
        $slug = (string)($article['slug'] ?? '');
        if ($slug === $currentSlug) {
            continue;
        }

        $category = article_category_label((string)($article['category'] ?? ''));
        $score = 0;

        if ($category === $currentCategory) {
            $score += 60;
        } elseif (in_array($category, $relatedCategories, true)) {
            $score += 25;
        }

        $haystack = strtolower(strip_tags(implode(' ', [
            $article['title'] ?? '',
            $article['slug'] ?? '',
            $article['excerpt'] ?? '',
            $article['content'] ?? '',
            $article['focus_keyword'] ?? '',
            $article['meta_keywords'] ?? '',
            implode(' ', $article['keywords'] ?? []),
            implode(' ', is_array($article['tags'] ?? null) ? $article['tags'] : []),
        ])));

        $titleHaystack = strtolower((string)($article['title'] ?? '') . ' ' . (string)($article['slug'] ?? ''));
        foreach ($currentTokens as $token) {
            if ($token !== '' && strlen($token) >= 4 && str_contains($haystack, $token)) {
                $score += str_contains($titleHaystack, $token) ? 8 : 4;
            }
        }

        if (!empty($article['featured'])) {
            $score += 2;
        }

        if ($score > 0) {
            $scored[] = [
                'score' => $score,
                'time' => strtotime((string)($article['updated_at'] ?? $article['published_at'] ?? '')) ?: 0,
                'article' => $article,
            ];
        }
    }

    usort($scored, static function (array $a, array $b): int {
        if ($a['score'] === $b['score']) {
            return $b['time'] <=> $a['time'];
        }
        return $b['score'] <=> $a['score'];
    });

    $results = array_map(static fn(array $row): array => $row['article'], $scored);

    if (count($results) < $limit) {
        $existing = array_flip(array_filter(array_map(
            static fn(array $article): string => (string)($article['slug'] ?? ''),
            $results
        )));
        if ($currentSlug !== '') {
            $existing[$currentSlug] = true;
        }

        foreach (latest_articles_by_updated($limit * 2, array_keys($existing)) as $fallback) {
            $results[] = $fallback;
            if (count($results) >= $limit) {
                break;
            }
        }
    }

    return array_slice($results, 0, $limit);
}

/*
|--------------------------------------------------------------------------
| SEARCH ARTICLES
|--------------------------------------------------------------------------
*/

function search_articles(
    string $keyword,
    int $limit = 10
): array {

    $keyword =
        strtolower(trim($keyword));

    if ($keyword === '') {
        return [];
    }

    $results = [];

    foreach (all_articles() as $article) {

        $haystack = strtolower(

            implode(

                ' ',

                [

                    $article['title'] ?? '',
                    $article['excerpt'] ?? '',
                    $article['content'] ?? '',
                    implode(
                        ' ',
                        $article['keywords'] ?? []
                    ),
                ]
            )
        );

        if (
            str_contains(
                $haystack,
                $keyword
            )
        ) {

            $results[] = $article;
        }
    }

    return array_slice(
        $results,
        0,
        $limit
    );
}

/*
|--------------------------------------------------------------------------
| GET ALL ARTICLE CATEGORIES
|--------------------------------------------------------------------------
*/

function article_categories(): array
{
    return article_category_labels();
}

if (!function_exists('article_category_definitions')) {

    function article_category_definitions(): array
    {
        $defaults = [
            'panduan-bisnis' => [
                'label' => 'Panduan Bisnis',
                'description' => 'Tips mengelola katalog, penawaran, trust, dan komunikasi pelanggan.',
                'cta' => 'Baca panduan bisnis',
            ],
            'produk-layanan' => [
                'label' => 'Produk & Layanan',
                'description' => 'Edukasi membuat deskripsi produk, jasa, paket, dan booking yang mudah dipahami.',
                'cta' => 'Baca panduan produk',
            ],
            'marketing-seo' => [
                'label' => 'Marketing & SEO',
                'description' => 'Konten edukasi seputar artikel SEO, landing page, CTA, dan optimasi konversi.',
                'cta' => 'Baca marketing & SEO',
            ],
            'checkout-pembayaran' => [
                'label' => 'Checkout & Pembayaran',
                'description' => 'Panduan alur order, pembayaran manual, invoice, dan follow-up pelanggan.',
                'cta' => 'Baca panduan checkout',
            ],
            'info-lokal' => [
                'label' => 'Info Lokal',
                'description' => 'Informasi area layanan, cabang, pengiriman, dan kebutuhan pelanggan lokal.',
                'cta' => 'Baca info lokal',
            ],
            'promo-layanan' => [
                'label' => 'Promo & Layanan',
                'description' => 'Informasi promo, paket, campaign, dan cara konsultasi dengan admin.',
                'cta' => 'Lihat promo & layanan',
            ],
        ];

        if (function_exists('business_category_definition_map')) {
            $custom = business_category_definition_map('article');
            if ($custom) {
                return $custom + $defaults;
            }
        }

        return $defaults;
    }
}

if (!function_exists('article_category_labels')) {

    function article_category_labels(): array
    {
        return array_values(array_map(
            static fn(array $item): string => (string)$item['label'],
            article_category_definitions()
        ));
    }
}

if (!function_exists('article_category_aliases')) {

    function article_category_aliases(): array
    {
        return [
            'panduan-bisnis' => 'Panduan Bisnis',
            'bisnis' => 'Panduan Bisnis',
            'produk' => 'Produk & Layanan',
            'layanan' => 'Produk & Layanan',
            'produk-layanan' => 'Produk & Layanan',
            'jasa' => 'Produk & Layanan',
            'marketing' => 'Marketing & SEO',
            'seo' => 'Marketing & SEO',
            'marketing-seo' => 'Marketing & SEO',
            'checkout' => 'Checkout & Pembayaran',
            'pembayaran' => 'Checkout & Pembayaran',
            'checkout-pembayaran' => 'Checkout & Pembayaran',
            'info-lokal' => 'Info Lokal',
            'lokasi' => 'Info Lokal',
            'area' => 'Info Lokal',
            'promo-layanan' => 'Promo & Layanan',
            'promo' => 'Promo & Layanan',
        ];
    }
}

if (!function_exists('article_category_label')) {

    function article_category_label(string $category): string
    {
        $category = trim($category);
        if ($category === '') {
            return 'Panduan Bisnis';
        }

        foreach (article_category_definitions() as $definition) {
            if (strcasecmp((string)$definition['label'], $category) === 0) {
                return (string)$definition['label'];
            }
        }

        $slug = slugify($category);
        $aliases = article_category_aliases();
        if (isset($aliases[$slug])) {
            return $aliases[$slug];
        }

        if (function_exists('article_category_by_slug')) {
            $definition = article_category_by_slug($slug);
            if (is_array($definition) && !empty($definition['label'])) {
                return (string)$definition['label'];
            }
        }

        return $category;
    }
}

if (!function_exists('article_category_slug')) {

    function article_category_slug(string $category): string
    {
        return slugify(article_category_label($category));
    }
}

if (!function_exists('article_category_by_slug')) {

    function article_category_by_slug(string $slug): ?array
    {
        $slug = slugify($slug);
        $definitions = article_category_definitions();

        if (isset($definitions[$slug])) {
            return $definitions[$slug] + ['slug' => $slug];
        }

        $label = article_category_aliases()[$slug] ?? null;
        if ($label !== null) {
            $canonicalSlug = slugify($label);
            if (isset($definitions[$canonicalSlug])) {
                return $definitions[$canonicalSlug] + ['slug' => $canonicalSlug];
            }
        }

        return null;
    }
}

if (!function_exists('article_related_category_labels')) {

    function article_related_category_labels(string $category): array
    {
        $category = article_category_label($category);

        $map = [
            'Produk' => ['Panduan Produk', 'Informasi Islami', 'Info Lokal', 'Promo & Layanan'],
            'Layanan' => ['Keluarga Muslim', 'Informasi Islami', 'Panduan Produk', 'Promo & Layanan'],
            'Panduan Produk' => ['Produk', 'Layanan', 'Kuliner Daging', 'Info Lokal'],
            'Informasi Islami' => ['Produk', 'Layanan', 'Keluarga Muslim'],
            'Keluarga Muslim' => ['Layanan', 'Informasi Islami', 'Kuliner Daging'],
            'Kuliner Daging' => ['Produk', 'Layanan', 'Keluarga Muslim'],
            'Info Lokal' => ['Produk', 'Layanan', 'Update Lokasi Usaha', 'Promo & Layanan'],
            'Update Lokasi Usaha' => ['Info Lokal', 'Produk', 'Layanan', 'Promo & Layanan'],
            'Promo & Layanan' => ['Produk', 'Layanan', 'Info Lokal', 'Update Lokasi Usaha'],
        ];

        return $map[$category] ?? ['Produk', 'Layanan'];
    }
}

if (!function_exists('article_relation_tokens')) {

    function article_relation_tokens(array $article): array
    {
        $text = strtolower(strip_tags(implode(' ', [
            $article['title'] ?? '',
            $article['category'] ?? '',
            $article['excerpt'] ?? '',
            $article['focus_keyword'] ?? '',
            $article['meta_keywords'] ?? '',
            implode(' ', $article['keywords'] ?? []),
        ])));

        $raw = preg_split('/[^a-z0-9]+/i', $text) ?: [];
        $stop = array_flip(['yang','dan','atau','untuk','dengan','dari','pada','agar','cara','tips','artikel','panduan','ini','itu','the','and','for']);
        $tokens = [];

        foreach ($raw as $token) {
            $token = strtolower(trim($token));
            if ($token === '' || strlen($token) < 4 || isset($stop[$token])) {
                continue;
            }
            $tokens[$token] = true;
        }

        return array_slice(array_keys($tokens), 0, 16);
    }
}



if (!function_exists('article_category_funnel_profile')) {

    function article_category_funnel_profile(string $category): array
    {
        $label = article_category_label($category);
        $slug = article_category_slug($label);

        $profiles = [
            'Produk' => [
                'stage' => 'Siap Memilih Produk Produk',
                'headline' => 'Butuh Rekomendasi Produk Produk?',
                'text' => 'Lanjutkan dari artikel ini ke katalog produk untuk membandingkan produk, layanan, atau paket berdasarkan lokasi, kelas harga, dan kebutuhan keluarga/lembaga.',
                'primary_label' => 'Lihat Katalog Produk',
                'primary_url' => url('katalog?' . http_build_query(['category' => 'produk'])),
                'secondary_label' => 'Konsultasi Produk',
                'secondary_url' => wa_link('saya selesai membaca artikel produk dan ingin konsultasi pilihan produk produk.'),
                'intent' => 'conversion',
            ],
            'Layanan' => [
                'stage' => 'Siap Menyiapkan Layanan',
                'headline' => 'Ingin Konsultasi Paket Layanan?',
                'text' => 'Setelah membaca panduan layanan, Anda bisa lanjut melihat pilihan layanan/paket layanan atau konsultasi jadwal, jumlah produk, dan kebutuhan keluarga.',
                'primary_label' => 'Lihat Paket Layanan',
                'primary_url' => url('layanan'),
                'secondary_label' => 'Konsultasi Layanan',
                'secondary_url' => wa_link('saya selesai membaca artikel layanan dan ingin konsultasi paket layanan.'),
                'intent' => 'conversion',
            ],
            'Panduan Produk' => [
                'stage' => 'Bandingkan Pilihan Produk',
                'headline' => 'Mau Bandingkan Produk yang Cocok?',
                'text' => 'Gunakan panduan ini untuk membandingkan produk, layanan, atau paket berdasarkan jenis, spesifikasi, lokasi lokasi usaha, dan kelas harga.',
                'primary_label' => 'Bandingkan di Katalog',
                'primary_url' => url('katalog'),
                'secondary_label' => 'Minta Rekomendasi',
                'secondary_url' => wa_link('saya selesai membaca panduan produk dan ingin minta rekomendasi produk.'),
                'intent' => 'consideration',
            ],
            'Informasi Islami' => [
                'stage' => 'Lanjut ke Panduan Ibadah',
                'headline' => 'Butuh Panduan Produk atau Layanan?',
                'text' => 'Artikel Islami bisa menjadi pintu awal sebelum memilih layanan produk atau layanan yang sesuai kebutuhan keluarga.',
                'primary_label' => 'Baca Panduan Produk',
                'primary_url' => url('artikel?' . http_build_query(['kategori' => 'produk'])),
                'secondary_label' => 'Baca Panduan Layanan',
                'secondary_url' => url('artikel?' . http_build_query(['kategori' => 'layanan'])),
                'intent' => 'education',
            ],
            'Keluarga Muslim' => [
                'stage' => 'Persiapan Acara Keluarga',
                'headline' => 'Sedang Menyiapkan Syukuran atau Layanan?',
                'text' => 'Lanjutkan ke panduan layanan dan layanan keluarga untuk membantu menyiapkan acara dengan lebih praktis.',
                'primary_label' => 'Lihat Panduan Layanan',
                'primary_url' => url('artikel?' . http_build_query(['kategori' => 'layanan'])),
                'secondary_label' => 'Konsultasi Acara',
                'secondary_url' => wa_link('saya selesai membaca artikel keluarga muslim dan ingin konsultasi kebutuhan layanan/syukuran.'),
                'intent' => 'education',
            ],
            'Kuliner Daging' => [
                'stage' => 'Tips Setelah Mendapat Daging',
                'headline' => 'Butuh Panduan Produk yang Praktis?',
                'text' => 'Artikel kuliner membantu setelah pembagian daging. Jika sedang menyiapkan produk, Anda juga bisa melihat pilihan produk dan layanan produk yang tersedia.',
                'primary_label' => 'Lihat Panduan Produk',
                'primary_url' => url('artikel?' . http_build_query(['kategori' => 'produk'])),
                'secondary_label' => 'Lihat Katalog Produk',
                'secondary_url' => url('katalog?' . http_build_query(['category' => 'produk'])),
                'intent' => 'awareness',
            ],
            'Info Lokal' => [
                'stage' => 'Cari Layanan Terdekat',
                'headline' => 'Ingin Cek Area Layanan Terdekat?',
                'text' => 'Lanjutkan ke halaman lokasi lokasi usaha untuk melihat area layanan, pilihan survey, dan katalog berdasarkan lokasi.',
                'primary_label' => 'Cek Area Layanan',
                'primary_url' => url('lokasi-lokasi usaha'),
                'secondary_label' => 'Tanya Lokasi Terdekat',
                'secondary_url' => wa_link('saya selesai membaca info lokal dan ingin tanya lokasi layanan terdekat.'),
                'intent' => 'local',
            ],
            'Update Lokasi Usaha' => [
                'stage' => 'Cek Stok & Survey',
                'headline' => 'Mau Cek Stok atau Jadwal Survey?',
                'text' => 'Update lokasi usaha membantu Anda melihat aktivitas terbaru. Untuk stok real-time, silakan hubungi admin agar dibantu cek pilihan yang masih tersedia.',
                'primary_label' => 'Lihat Katalog Terbaru',
                'primary_url' => url('katalog'),
                'secondary_label' => 'Tanya Stok Lokasi Usaha',
                'secondary_url' => wa_link('saya selesai membaca update lokasi usaha dan ingin cek stok produk terbaru.'),
                'intent' => 'trust',
            ],
            'Promo & Layanan' => [
                'stage' => 'Siap Konsultasi Layanan',
                'headline' => 'Ingin Booking atau Tanya Paket?',
                'text' => 'Artikel layanan biasanya dekat dengan kebutuhan pembelian. Hubungi admin untuk cek paket, harga, stok, dan jadwal layanan.',
                'primary_label' => 'Chat WhatsApp',
                'primary_url' => wa_link('saya selesai membaca artikel promo/layanan dan ingin konsultasi paket.'),
                'secondary_label' => 'Lihat Katalog',
                'secondary_url' => url('katalog'),
                'intent' => 'conversion',
            ],
        ];

        $profile = $profiles[$label] ?? $profiles['Produk'];
        $profile['label'] = $label;
        $profile['slug'] = $slug;

        return $profile;
    }
}

if (!function_exists('article_conversion_cta')) {

    function article_conversion_cta(array $article): array
    {
        $profile = article_category_funnel_profile((string)($article['category'] ?? 'Produk'));
        $title = trim((string)($article['title'] ?? 'artikel ini'));

        if (!empty($article['whatsapp_text']) && !empty($article['whatsapp_phone'])) {
            $profile['secondary_url'] = 'https://wa.me/' . preg_replace('/\D+/', '', (string)$article['whatsapp_phone']) . '?text=' . rawurlencode((string)$article['whatsapp_text']);
            $profile['secondary_label'] = trim((string)($article['whatsapp_label'] ?? 'Chat WhatsApp')) ?: 'Chat WhatsApp';
        }

        $profile['tracking_source'] = 'artikel';
        $profile['tracking_category'] = article_category_slug((string)($article['category'] ?? 'artikel'));
        $profile['tracking_title'] = $title;

        $conversionContext = [
            'source' => 'Artikel',
            'title' => $title,
            'category' => article_category_label((string)($article['category'] ?? 'Artikel')),
            'intent' => (string)($profile['intent'] ?? ''),
        ];

        if (function_exists('conversion_optimize_url')) {
            $profile['primary_url'] = conversion_optimize_url((string)($profile['primary_url'] ?? ''), $conversionContext);
            $profile['secondary_url'] = conversion_optimize_url((string)($profile['secondary_url'] ?? ''), $conversionContext);
        }

        return $profile;
    }
}

if (!function_exists('article_hub_funnel_cards')) {

    function article_hub_funnel_cards(): array
    {
        return [
            [
                'label' => 'Baru Mulai Riset',
                'title' => 'Pahami Produk & Layanan Dulu',
                'text' => 'Mulai dari artikel dasar tentang produk, layanan, syarat produk, dan persiapan keluarga.',
                'url' => url('artikel?' . http_build_query(['kategori' => 'produk'])),
            ],
            [
                'label' => 'Sedang Membandingkan',
                'title' => 'Bandingkan Produk dan Lokasi',
                'text' => 'Lanjutkan ke panduan produk, info lokal, dan katalog agar pilihan lebih mudah dibandingkan.',
                'url' => url('artikel?' . http_build_query(['kategori' => 'panduan-produk'])),
            ],
            [
                'label' => 'Siap Konsultasi',
                'title' => 'Cek Stok dan Tanya Admin',
                'text' => 'Jika sudah dekat dengan keputusan, cek stok, lokasi lokasi usaha, dan layanan booking melalui WhatsApp.',
                'url' => function_exists('wa_link_contextual')
                    ? wa_link_contextual('saya ingin konsultasi setelah membaca panduan artikel di website.', [
                        'source' => 'Pusat Artikel',
                        'title' => 'Siap Konsultasi',
                        'category' => 'Produk & Layanan',
                    ])
                    : wa_link('saya ingin konsultasi setelah membaca panduan artikel di website.'),
                'external' => true,
            ],
        ];
    }
}

if (!function_exists('article_funnel_guides')) {

    function article_funnel_guides(int $limit = 3, array $excludeSlugs = []): array
    {
        $priority = articles_by_categories(
            ['Panduan Bisnis', 'Produk & Layanan', 'Marketing & SEO', 'Checkout & Pembayaran', 'Info Lokal'],
            $limit * 3,
            $excludeSlugs
        );

        usort($priority, static function (array $a, array $b): int {
            $aFeatured = !empty($a['featured']) ? 1 : 0;
            $bFeatured = !empty($b['featured']) ? 1 : 0;
            if ($aFeatured === $bFeatured) {
                $aTime = strtotime((string)($a['updated_at'] ?? $a['published_at'] ?? '')) ?: 0;
                $bTime = strtotime((string)($b['updated_at'] ?? $b['published_at'] ?? '')) ?: 0;
                return $bTime <=> $aTime;
            }
            return $bFeatured <=> $aFeatured;
        });

        return array_slice($priority, 0, max(0, $limit));
    }
}

/*
|--------------------------------------------------------------------------
| GET FEATURED ARTICLE
|--------------------------------------------------------------------------
*/

function featured_article(): ?array
{
    $featured =
        featured_articles(1);

    return $featured[0] ?? null;
}

/*
|--------------------------------------------------------------------------
| ARTICLE EXISTS
|--------------------------------------------------------------------------
*/

function article_exists(
    string $slug
): bool {

    return
        get_article_by_slug($slug) !== null;
}

/*
|--------------------------------------------------------------------------
| ARTICLE URL
|--------------------------------------------------------------------------
*/

function article_permalink(
    array $article
): string {

    return article_url(
        (string)(
            $article['slug'] ?? ''
        )
    );
}

/*
|--------------------------------------------------------------------------
| ESTIMATED READING TIME
|--------------------------------------------------------------------------
*/

function estimate_reading_time(
    string $html
): string {

    $text =
        strip_tags($html);

    $wordCount =
        str_word_count($text);

    $minutes =
        max(
            1,
            (int)ceil($wordCount / 200)
        );

    return
        $minutes . ' Menit';
}

/*
|--------------------------------------------------------------------------
| ARTICLE META TITLE
|--------------------------------------------------------------------------
*/

function article_meta_title(
    array $article
): string {

    return meta_title(
        (string)(
            $article['title'] ?? ''
        )
    );
}

/*
|--------------------------------------------------------------------------
| ARTICLE META DESCRIPTION
|--------------------------------------------------------------------------
*/

function article_meta_description(
    array $article
): string {

    return limit_chars(

        strip_tags(
            (string)(
                $article['excerpt'] ?? ''
            )
        ),

        155
    );
}

/*
|--------------------------------------------------------------------------
| ARTICLE JSON-LD READY
|--------------------------------------------------------------------------
*/

function article_schema_ready(
    array $article
): array {

    return [

        'headline' =>
            $article['title'] ?? '',

        'description' =>
            $article['excerpt'] ?? '',

        'image' =>
            $article['image'] ?? '',

        'datePublished' =>
            $article['published_at'] ?? '',

        'dateModified' =>
            $article['updated_at']
                ?? $article['published_at']
                ?? '',

        'author' => [

            '@type' => 'Organization',

            'name' => SITE_NAME,
        ],

        'publisher' => [

            '@type' => 'Organization',

            'name' => SITE_NAME,
        ],
    ];
}

/*
|--------------------------------------------------------------------------
| ARTICLE BY SLUG
|--------------------------------------------------------------------------
*/

if (!function_exists('article_by_slug')) {

    function article_by_slug(string $slug): ?array
    {
        foreach (all_articles() as $article) {

            if (
                ($article['slug'] ?? '')
                === $slug
            ) {
                return $article;
            }
        }

        return null;
    }
}

/*
|--------------------------------------------------------------------------
| ARTICLE ADMIN CRUD
|--------------------------------------------------------------------------
*/


function article_slug_unique(string $slug, ?int $ignoreId = null): string
{
    $base = slugify($slug ?: 'artikel');
    $candidate = $base;
    $counter = 2;

    while (true) {
        $exists = false;
        foreach (all_articles() as $article) {
            if (($article['slug'] ?? '') === $candidate && (int)($article['id'] ?? 0) !== (int)$ignoreId) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            return $candidate;
        }
        $candidate = $base . '-' . $counter;
        $counter++;
    }
}

function article_seed_articles(): array
{
    return array_map(
        static fn(array $article): array => article_with_source($article, 'seed'),
        require DATA_PATH . '/articles.php'
    );
}

function article_convert_seed_to_storage(): array
{
    $bootstrap = article_runtime_bootstrap_from_seed_if_needed();
    if ((int)($bootstrap['created'] ?? 0) > 0) {
        return ['created' => (int)$bootstrap['created'], 'skipped' => (int)($bootstrap['skipped'] ?? 0)];
    }

    $created = 0;
    $skipped = 0;
    $existingSlugs = array_flip(array_map(static fn(array $a): string => (string)($a['slug'] ?? ''), managed_articles()));

    foreach (article_seed_articles() as $seed) {
        $slug = (string)($seed['slug'] ?? '');
        if ($slug === '' || isset($existingSlugs[$slug])) {
            $skipped++;
            continue;
        }
        $seed['id'] = article_next_id() + $created;
        $seed['source'] = 'starter-content';
        $seed['_source'] = 'starter-content';
        $seed['updated_at'] = date('Y-m-d H:i:s');
        if (article_create($seed) > 0) {
            $created++;
            $existingSlugs[$slug] = true;
        } else {
            $skipped++;
        }
    }

    article_mark_starter_content_initialized();

    return ['created' => $created, 'skipped' => $skipped];
}

function article_source_label(array $article): string
{
    return match ((string)($article['source'] ?? $article['_source'] ?? 'admin')) {
        'seed' => 'Contoh Awal',
        'seed-converted' => 'Konten Awal Tersimpan',
        'starter-content' => 'Konten Awal',
        'import' => 'Import',
        'wp-import' => 'Import WordPress',
        default => 'Admin',
    };
}

function save_custom_article(array $article): bool
{
    return article_create($article) > 0;
}

function article_create(array $article): int
{
    $article = normalize_article($article);
    if (($article['source'] ?? '') !== 'seed-converted') {
        $article['slug'] = article_slug_unique((string)($article['slug'] ?: $article['title']));
    }
    $article['id'] = article_next_id();
    $article['updated_at'] = date('Y-m-d H:i:s');

    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('articles')) {
        $stmt = db()->prepare('INSERT INTO articles (title, slug, category, excerpt, image, author, published_at, status, reading_time, featured, keywords, content, meta_title, meta_description, meta_keywords, canonical_url, og_title, og_description, focus_keyword, robots, breadcrumb_title, image_alt, image_title, schema_type, faq_json, whatsapp_label, whatsapp_phone, whatsapp_text, source) VALUES (:title, :slug, :category, :excerpt, :image, :author, :published_at, :status, :reading_time, :featured, :keywords, :content, :meta_title, :meta_description, :meta_keywords, :canonical_url, :og_title, :og_description, :focus_keyword, :robots, :breadcrumb_title, :image_alt, :image_title, :schema_type, :faq_json, :whatsapp_label, :whatsapp_phone, :whatsapp_text, :source)');
        $stmt->execute(article_db_payload($article));
        $insertId = (int) db()->lastInsertId();
        if ($insertId > 0 && function_exists('activity_log_record')) {
            activity_log_record('create', 'article', $insertId, 'Artikel dibuat.', ['title' => $article['title'] ?? '', 'slug' => $article['slug'] ?? '', 'storage' => 'database']);
        }
        return $insertId;
    }

    $articles = managed_articles();
    array_unshift($articles, $article);
    $ok = article_write_json($articles);
    if ($ok && function_exists('activity_log_record')) {
        activity_log_record('create', 'article', (int)$article['id'], 'Artikel dibuat.', ['title' => $article['title'] ?? '', 'slug' => $article['slug'] ?? '', 'storage' => 'json']);
    }
    return $ok ? (int)$article['id'] : 0;
}

function article_update(int $id, array $article): bool
{
    $article = normalize_article($article);
    $article['slug'] = article_slug_unique((string)($article['slug'] ?: $article['title']), $id);
    $article['id'] = $id;
    $article['updated_at'] = date('Y-m-d H:i:s');

    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('articles')) {
        $payload = article_db_payload($article);
        $payload['id'] = $id;
        $stmt = db()->prepare('UPDATE articles SET title=:title, slug=:slug, category=:category, excerpt=:excerpt, image=:image, author=:author, published_at=:published_at, status=:status, reading_time=:reading_time, featured=:featured, keywords=:keywords, content=:content, meta_title=:meta_title, meta_description=:meta_description, meta_keywords=:meta_keywords, canonical_url=:canonical_url, og_title=:og_title, og_description=:og_description, focus_keyword=:focus_keyword, robots=:robots, breadcrumb_title=:breadcrumb_title, image_alt=:image_alt, image_title=:image_title, schema_type=:schema_type, faq_json=:faq_json, whatsapp_label=:whatsapp_label, whatsapp_phone=:whatsapp_phone, whatsapp_text=:whatsapp_text, source=:source WHERE id=:id');
        $ok = $stmt->execute($payload);
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('update', 'article', $id, 'Artikel diperbarui.', ['title' => $article['title'] ?? '', 'slug' => $article['slug'] ?? '', 'storage' => 'database']);
        }
        return $ok;
    }

    $articles = managed_articles();
    foreach ($articles as $index => $existing) {
        if ((int)($existing['id'] ?? 0) === $id) {
            $articles[$index] = $article;
            $ok = article_write_json($articles);
            if ($ok && function_exists('activity_log_record')) {
                activity_log_record('update', 'article', $id, 'Artikel diperbarui.', ['title' => $article['title'] ?? '', 'slug' => $article['slug'] ?? '', 'storage' => 'json']);
            }
            return $ok;
        }
    }

    return false;
}

function article_delete(int $id): bool
{
    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('articles')) {
        $stmt = db()->prepare('DELETE FROM articles WHERE id = :id');
        $ok = $stmt->execute(['id' => $id]);
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('delete', 'article', $id, 'Artikel dihapus.', ['storage' => 'database']);
        }
        return $ok;
    }

    $before = managed_articles();
    $deletedArticle = null;
    foreach ($before as $articleRow) {
        if ((int)($articleRow['id'] ?? 0) === $id) {
            $deletedArticle = $articleRow;
            break;
        }
    }
    $articles = array_values(array_filter($before, static fn(array $article): bool => (int)($article['id'] ?? 0) !== $id));
    $ok = article_write_json($articles);
    if ($ok && function_exists('activity_log_record')) {
        activity_log_record('delete', 'article', $id, 'Artikel dihapus.', ['title' => $deletedArticle['title'] ?? '', 'slug' => $deletedArticle['slug'] ?? '', 'storage' => 'json']);
    }
    return $ok;
}


function article_delete_many(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return ['deleted' => 0, 'skipped' => 0];
    }

    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('articles')) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare('DELETE FROM articles WHERE id IN (' . $placeholders . ')');
        $stmt->execute($ids);
        $deleted = $stmt->rowCount();
        if ($deleted > 0 && function_exists('activity_log_record')) {
            activity_log_record('bulk_delete', 'article', null, 'Artikel dihapus massal.', ['deleted' => $deleted, 'ids' => $ids, 'storage' => 'database']);
        }
        return ['deleted' => $deleted, 'skipped' => 0];
    }

    $before = managed_articles();
    $after = array_values(array_filter($before, static fn(array $article): bool => !in_array((int)($article['id'] ?? 0), $ids, true)));
    $deleted = count($before) - count($after);

    $ok = article_write_json($after);
    if ($ok && $deleted > 0 && function_exists('activity_log_record')) {
        activity_log_record('bulk_delete', 'article', null, 'Artikel dihapus massal.', ['deleted' => $deleted, 'ids' => $ids, 'storage' => 'json']);
    }
    return $ok ? ['deleted' => $deleted, 'skipped' => count($ids) - $deleted] : ['deleted' => 0, 'skipped' => count($ids)];
}

function article_delete_all_managed(): array
{
    $articles = managed_articles();
    $ids = array_map(static fn(array $article): int => (int)($article['id'] ?? 0), $articles);
    return article_delete_many($ids);
}

function article_delete_media_files(array $articles): int
{
    $deleted = 0;
    $base = realpath(ROOT_PATH . '/assets/uploads/articles');
    if (!$base) {
        return 0;
    }

    foreach ($articles as $article) {
        $image = (string)($article['image'] ?? '');
        if ($image === '' || str_contains($image, '://') || !str_contains($image, '/assets/uploads/articles/')) {
            continue;
        }

        $pathPart = parse_url($image, PHP_URL_PATH) ?: '';
        $relative = substr($pathPart, strpos($pathPart, '/assets/uploads/articles/') + strlen('/assets/uploads/articles/'));
        $target = realpath($base . '/' . basename($relative));

        if ($target && str_starts_with($target, $base) && is_file($target) && unlink($target)) {
            $deleted++;
        }
    }

    return $deleted;
}

function article_admin_find(int $id): ?array
{
    foreach (managed_articles() as $article) {
        if ((int)($article['id'] ?? 0) === $id) {
            return $article;
        }
    }

    return null;
}

function article_write_json(array $articles): bool
{
    $ok = (bool) file_put_contents(
        article_storage_path(),
        json_encode(array_values($articles), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    if ($ok) {
        article_mark_starter_content_initialized();
    }

    return $ok;
}

function article_starter_content_state_path(): string
{
    if (!is_dir(STORAGE_PATH)) { @mkdir(STORAGE_PATH, 0775, true); }
    return STORAGE_PATH . '/starter-content-state.json';
}

function article_starter_content_state(): array
{
    $path = article_starter_content_state_path();
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function article_write_starter_content_state(array $state): bool
{
    return (bool)file_put_contents(
        article_starter_content_state_path(),
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function article_mark_starter_content_initialized(): void
{
    $state = article_starter_content_state();
    $collections = is_array($state['collections'] ?? null) ? $state['collections'] : [];
    $collections['articles'] = [
        'initialized' => true,
        'updated_at' => date('c'),
    ];
    $state['collections'] = $collections;
    $state['updated_at'] = date('c');
    @article_write_starter_content_state($state);
}

function article_starter_content_initialized(): bool
{
    $state = article_starter_content_state();
    $articles = is_array($state['collections']['articles'] ?? null) ? $state['collections']['articles'] : [];
    return !empty($articles['initialized']);
}

function article_runtime_bootstrap_from_seed_if_needed(): array
{
    if (function_exists('storage_mysql_enabled') && storage_mysql_enabled('articles')) {
        return ['created' => 0, 'skipped' => 0, 'initialized' => article_starter_content_initialized(), 'storage' => 'mysql'];
    }

    $customPath = article_storage_path();
    $existing = [];
    if (is_file($customPath)) {
        $decoded = json_decode((string)file_get_contents($customPath), true);
        $existing = is_array($decoded) ? array_values($decoded) : [];
    }

    if ($existing !== []) {
        article_mark_starter_content_initialized();
        return ['created' => 0, 'skipped' => count($existing), 'initialized' => true, 'storage' => 'json'];
    }

    if (article_starter_content_initialized()) {
        return ['created' => 0, 'skipped' => 0, 'initialized' => true, 'storage' => 'json'];
    }

    $created = [];
    $now = date('Y-m-d H:i:s');

    foreach (article_seed_articles() as $index => $seed) {
        $seed = normalize_article(article_with_source($seed, 'starter-content'));
        $seed['source'] = 'starter-content';
        $seed['_source'] = 'starter-content';
        $seed['id'] = (int)($seed['id'] ?? 0) > 0 ? (int)$seed['id'] : (time() + $index + 1);
        $seed['created_at'] = $seed['created_at'] ?? $now;
        $seed['updated_at'] = $now;
        $created[] = $seed;
    }

    if ($created !== [] && article_write_json($created)) {
        return ['created' => count($created), 'skipped' => 0, 'initialized' => true, 'storage' => 'json'];
    }

    return ['created' => 0, 'skipped' => count($created), 'initialized' => false, 'storage' => 'json'];
}

function article_next_id(): int
{
    $ids = array_map(static fn(array $article): int => (int)($article['id'] ?? 0), managed_articles());
    return max([time(), ...$ids]) + 1;
}


function article_first_value(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }

    return $default;
}

function article_import_many(array $rows): array
{
    $created = 0;
    $skipped = 0;
    $logs = [];

    foreach ($rows as $index => $row) {
        $title = article_first_value($row, ['title', 'judul', 'judul_artikel', 'nama_artikel', 'column_3']);
        $content = article_first_value($row, ['content', 'isi', 'isi_artikel', 'body', 'artikel', 'konten', 'column_8']);
        $image = article_first_value($row, ['image', 'gambar', 'link_gambar', 'featured_image', 'image_url', 'url_gambar', 'column_9']);
        $publishedAt = article_first_value($row, ['published_at', 'tanggal', 'date', 'created_at', 'column_2'], date('Y-m-d H:i:s'));
        $waLabel = article_first_value($row, ['whatsapp_label', 'label_wa', 'link_chat_whatsapp', 'cta', 'column_4'], 'Chat WhatsApp');
        $waPhone = preg_replace('/\D+/', '', article_first_value($row, ['whatsapp_phone', 'nomor_wa', 'no_wa', 'phone', 'wa', 'whatsapp', 'column_5']));
        $waText = article_first_value($row, ['whatsapp_text', 'auto_teks_chat_whatsapp', 'auto_text_wa', 'pesan_wa', 'teks_wa', 'column_6']);

        if ($title === '' || trim(strip_tags($content)) === '') {
            $skipped++;
            $logs[] = 'Baris ' . ($index + 1) . ' dilewati: judul/konten kosong. Pastikan kolom judul dan isi artikel terbaca.';
            continue;
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        $metaDescription = article_first_value($row, ['meta_description', 'seo_description', 'deskripsi', 'excerpt', 'ringkasan'], limit_chars($plain, 155));
        $keywords = article_first_value($row, ['keywords', 'keyword', 'meta_keywords', 'seo_keywords']);
        if ($keywords === '') {
            $keywords = implode(', ', array_filter([$title, 'produk', 'layanan', 'layanan', 'paket', 'produk']));
        }

        $payload = [
            'title' => $title,
            'slug' => article_first_value($row, ['slug', 'permalink']),
            'category' => article_first_value($row, ['category', 'kategori'], 'Artikel'),
            'excerpt' => article_first_value($row, ['excerpt', 'ringkasan', 'deskripsi'], $metaDescription),
            'image' => $image,
            'author' => article_first_value($row, ['author', 'penulis'], SITE_NAME),
            'published_at' => $publishedAt,
            'featured' => in_array(strtolower(article_first_value($row, ['featured', 'unggulan', 'column_11'])), ['1','true','ya','yes'], true),
            'keywords' => $keywords,
            'content' => $content,
            'meta_title' => article_first_value($row, ['meta_title', 'seo_title'], $title),
            'meta_description' => $metaDescription,
            'meta_keywords' => article_first_value($row, ['meta_keywords', 'seo_keywords', 'keywords'], $keywords),
            'canonical_url' => article_first_value($row, ['canonical_url', 'canonical']),
            'og_title' => article_first_value($row, ['og_title'], $title),
            'og_description' => article_first_value($row, ['og_description'], $metaDescription),
            'focus_keyword' => article_first_value($row, ['focus_keyword', 'keyword_utama'], $title),
            'robots' => article_first_value($row, ['robots'], 'index, follow'),
            'breadcrumb_title' => article_first_value($row, ['breadcrumb_title'], $title),
            'image_alt' => article_first_value($row, ['image_alt', 'alt_gambar'], $title),
            'image_title' => article_first_value($row, ['image_title', 'title_gambar'], $title),
            'schema_type' => article_first_value($row, ['schema_type'], 'Article'),
            'faq_json' => article_first_value($row, ['faq_json']),
            'whatsapp_label' => $waLabel,
            'whatsapp_phone' => $waPhone,
            'whatsapp_text' => $waText,
            'source' => 'import',
        ];

        if (article_create($payload) > 0) {
            $created++;
            $logs[] = 'Berhasil import: ' . $title;
        } else {
            $skipped++;
            $logs[] = 'Gagal import: ' . $title;
        }
    }

    return ['created' => $created, 'skipped' => $skipped, 'logs' => $logs];
}

function article_db_payload(array $article): array
{
    return [
        'title' => $article['title'],
        'slug' => $article['slug'],
        'category' => $article['category'],
        'excerpt' => $article['excerpt'],
        'image' => $article['image'] ?: null,
        'author' => $article['author'],
        'published_at' => $article['published_at'],
        'status' => $article['status'] ?? 'published',
        'reading_time' => $article['reading_time'],
        'featured' => $article['featured'] ? 1 : 0,
        'keywords' => implode(', ', $article['keywords'] ?? []),
        'content' => $article['content'],
        'meta_title' => $article['meta_title'] ?: null,
        'meta_description' => $article['meta_description'] ?: null,
        'meta_keywords' => $article['meta_keywords'] ?: null,
        'canonical_url' => $article['canonical_url'] ?: null,
        'og_title' => $article['og_title'] ?: null,
        'og_description' => $article['og_description'] ?: null,
        'focus_keyword' => $article['focus_keyword'] ?: null,
        'robots' => $article['robots'] ?: 'index, follow',
        'breadcrumb_title' => $article['breadcrumb_title'] ?: $article['title'],
        'image_alt' => $article['image_alt'] ?: $article['title'],
        'image_title' => $article['image_title'] ?: $article['title'],
        'schema_type' => $article['schema_type'] ?: 'Article',
        'faq_json' => $article['faq_json'] ?: null,
        'whatsapp_label' => $article['whatsapp_label'] ?: 'Chat WhatsApp',
        'whatsapp_phone' => $article['whatsapp_phone'] ?: null,
        'whatsapp_text' => $article['whatsapp_text'] ?: null,
        'source' => $article['source'] ?: 'admin',
    ];
}

function article_faq_items(array $article): array
{
    $raw = trim((string)($article['faq_json'] ?? ''));
    if ($raw === '') {
        return is_array($article['faq'] ?? null) ? $article['faq'] : [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter($decoded, static fn($item): bool => is_array($item) && !empty($item['question']) && !empty($item['answer'])));
}
