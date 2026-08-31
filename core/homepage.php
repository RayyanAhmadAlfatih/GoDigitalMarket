<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| HOMEPAGE MODE ENGINE
|--------------------------------------------------------------------------
| Lightweight settings for choosing the public homepage style.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('homepage_storage_file')) {
    function homepage_storage_file(): string
    {
        return STORAGE_PATH . '/homepage-settings.json';
    }
}

if (!function_exists('homepage_modes')) {
    function homepage_modes(): array
    {
        return [
            'company_profile' => [
                'label' => 'Company Profile',
                'description' => 'Cocok untuk memperkenalkan bisnis, layanan, portofolio, dan kontak utama.',
                'hero_eyebrow' => 'Website resmi bisnis Anda',
                'hero_title' => 'Kenali Bisnis Kami dan Solusi yang Kami Tawarkan',
                'hero_description' => 'Tampilkan profil bisnis, layanan utama, keunggulan, artikel, dan kontak dalam satu halaman beranda yang rapi.',
                'primary_label' => 'Lihat Layanan',
                'primary_url' => '/layanan',
                'secondary_label' => 'Hubungi Kami',
                'secondary_url' => '/kontak',
            ],
            'personal_brand' => [
                'label' => 'Personal Branding',
                'description' => 'Cocok untuk expert, mentor, kreator, atau profesional yang ingin membangun authority sekaligus menjual produk/jasa.',
                'hero_eyebrow' => 'Bangun trust lewat personal brand',
                'hero_title' => 'Kenali Value, Karya, dan Solusi yang Bisa Saya Bantu',
                'hero_description' => 'Gunakan beranda untuk memperkenalkan figur, value utama, produk, program, portfolio, artikel, dan CTA konsultasi.',
                'primary_label' => 'Mulai Konsultasi',
                'primary_url' => '/kontak',
                'secondary_label' => 'Lihat Portfolio',
                'secondary_url' => '/portfolio',
            ],
            'portfolio' => [
                'label' => 'Portfolio / Showcase',
                'description' => 'Cocok untuk menampilkan karya, case study, project, testimoni, dan tombol inquiry.',
                'hero_eyebrow' => 'Showcase karya dan bukti hasil',
                'hero_title' => 'Lihat Karya, Project, dan Solusi yang Pernah Dikerjakan',
                'hero_description' => 'Tampilkan portfolio, layanan, artikel pendukung, dan form inquiry dalam alur yang simpel namun siap scale.',
                'primary_label' => 'Bahas Project',
                'primary_url' => '/kontak',
                'secondary_label' => 'Lihat Showcase',
                'secondary_url' => '/portfolio',
            ],
            'catalog' => [
                'label' => 'Katalog Produk/Jasa',
                'description' => 'Cocok untuk toko, jasa, menu, paket, produk digital, dan booking.',
                'hero_eyebrow' => 'Katalog siap jualan',
                'hero_title' => 'Temukan Produk dan Layanan yang Sesuai Kebutuhan Anda',
                'hero_description' => 'Tampilkan produk, jasa, paket, menu, digital product, atau booking dengan CTA WhatsApp dan checkout yang mudah dipakai.',
                'primary_label' => 'Lihat Katalog',
                'primary_url' => '/katalog',
                'secondary_label' => 'Konsultasi',
                'secondary_url' => '/kontak',
            ],
            'sales_page' => [
                'label' => 'Sales Page',
                'description' => 'Cocok untuk halaman penawaran yang fokus ke CTA, benefit, dan konversi.',
                'hero_eyebrow' => 'Penawaran pilihan',
                'hero_title' => 'Solusi Praktis untuk Kebutuhan Bisnis dan Pelanggan Anda',
                'hero_description' => 'Gunakan beranda sebagai halaman penjualan dengan headline jelas, benefit utama, produk unggulan, FAQ, dan tombol aksi.',
                'primary_label' => 'Ambil Penawaran',
                'primary_url' => '/checkout',
                'secondary_label' => 'Tanya Dulu',
                'secondary_url' => '/kontak',
            ],
            'lead_generation' => [
                'label' => 'Lead Generation',
                'description' => 'Cocok untuk mengumpulkan inquiry, konsultasi, booking, dan permintaan penawaran.',
                'hero_eyebrow' => 'Konsultasi mudah',
                'hero_title' => 'Ceritakan Kebutuhan Anda, Tim Kami Siap Membantu',
                'hero_description' => 'Arahkan pengunjung untuk mengisi form singkat, konsultasi WhatsApp, atau memilih layanan yang paling relevan.',
                'primary_label' => 'Isi Form',
                'primary_url' => '#form-konsultasi',
                'secondary_label' => 'Chat WhatsApp',
                'secondary_url' => '/kontak',
            ],
            'simple_brand' => [
                'label' => 'Simple Brand Page',
                'description' => 'Cocok untuk beranda sederhana yang fokus ke brand, ringkasan, dan tombol kontak.',
                'hero_eyebrow' => 'Website resmi',
                'hero_title' => 'Selamat Datang di Website Resmi Kami',
                'hero_description' => 'Beranda sederhana untuk menampilkan ringkasan bisnis, link penting, produk atau layanan pilihan, dan kontak utama.',
                'primary_label' => 'Hubungi Kami',
                'primary_url' => '/kontak',
                'secondary_label' => 'Lihat Katalog',
                'secondary_url' => '/katalog',
            ],
        ];
    }
}


if (!function_exists('homepage_source_options')) {
    function homepage_source_options(): array
    {
        return [
            'template' => [
                'label' => 'Mode Beranda Template',
                'description' => 'Beranda memakai pengaturan headline, section, katalog, artikel, dan form dari halaman ini.',
            ],
            'landing_page' => [
                'label' => 'Landing Page Builder',
                'description' => 'Beranda memakai salah satu landing page yang sudah dipublish dari Landing Page Builder.',
            ],
        ];
    }
}


if (!function_exists('homepage_section_definitions')) {
    function homepage_section_definitions(): array
    {
        return [
            'hero' => [
                'label' => 'Hero Utama',
                'short_label' => 'Hero',
                'description' => 'Headline pertama, tombol utama, dan pesan paling penting di homepage.',
                'default_enabled' => true,
            ],
            'trustbar' => [
                'label' => 'Benefit Singkat',
                'short_label' => 'Benefit',
                'description' => 'Ringkasan poin keunggulan di bawah hero.',
                'default_enabled' => true,
            ],
            'profile_intro' => [
                'label' => 'Pengantar Beranda',
                'short_label' => 'Pengantar',
                'description' => 'Section pengantar sesuai mode homepage yang dipilih.',
                'default_enabled' => true,
            ],
            'featured_catalog' => [
                'label' => 'Katalog Pilihan',
                'short_label' => 'Katalog',
                'description' => 'Produk, jasa, paket, atau penawaran unggulan dari katalog.',
                'default_enabled' => true,
            ],
            'services_highlight' => [
                'label' => 'Layanan Pilihan',
                'short_label' => 'Layanan',
                'description' => 'Highlight layanan atau jasa agar homepage cocok untuk bisnis service.',
                'default_enabled' => false,
            ],
            'business_fit' => [
                'label' => 'Jenis Bisnis yang Cocok',
                'short_label' => 'Cocok Untuk',
                'description' => 'Penjelasan fleksibilitas website untuk banyak jenis bisnis.',
                'default_enabled' => true,
            ],
            'latest_articles' => [
                'label' => 'Artikel Terbaru',
                'short_label' => 'Artikel',
                'description' => 'Konten edukasi dan SEO terbaru dari blog.',
                'default_enabled' => true,
            ],
            'trust_conversion' => [
                'label' => 'Trust, Testimoni, FAQ & CTA',
                'short_label' => 'Trust/CTA',
                'description' => 'Block dari Trust & Conversion Builder seperti testimoni, FAQ, garansi, badge, before-after, dan CTA.',
                'default_enabled' => true,
            ],
            'custom_sections' => [
                'label' => 'Section Tambahan',
                'short_label' => 'Custom',
                'description' => 'Section tambahan yang dikelola dari Konten Template.',
                'default_enabled' => true,
            ],
            'lead_form' => [
                'label' => 'Form Konsultasi',
                'short_label' => 'Form',
                'description' => 'Form lead/inquiry utama untuk menangkap prospek dari homepage.',
                'default_enabled' => true,
            ],
            'portfolio_highlight' => [
                'label' => 'Portfolio / Showcase',
                'short_label' => 'Portfolio',
                'description' => 'Highlight karya, project, case study, atau bukti hasil.',
                'default_enabled' => false,
            ],
        ];
    }
}

if (!function_exists('homepage_default_section_order')) {
    function homepage_default_section_order(): array
    {
        return [
            'hero',
            'trustbar',
            'profile_intro',
            'featured_catalog',
            'business_fit',
            'latest_articles',
            'custom_sections',
            'trust_conversion',
            'lead_form',
            'services_highlight',
            'portfolio_highlight',
        ];
    }
}

if (!function_exists('homepage_normalize_section_order')) {
    function homepage_normalize_section_order(mixed $order): array
    {
        $definitions = homepage_section_definitions();
        $defaultOrder = homepage_default_section_order();
        $normalized = [];

        foreach ((array)$order as $key) {
            $key = strtolower(trim((string)$key));
            $key = str_replace('-', '_', $key);
            $key = preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
            if ($key !== '' && isset($definitions[$key]) && !in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        foreach ($defaultOrder as $key) {
            if (isset($definitions[$key]) && !in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        return $normalized ?: $defaultOrder;
    }
}

if (!function_exists('homepage_published_landing_pages')) {
    function homepage_published_landing_pages(): array
    {
        if (!function_exists('landing_page_all')) {
            return [];
        }

        $pages = [];
        foreach (landing_page_all(true) as $page) {
            if ((string)($page['status'] ?? '') !== 'published') {
                continue;
            }
            $pages[] = $page;
        }

        usort($pages, static function (array $a, array $b): int {
            $aDate = strtotime((string)($a['updated_at'] ?? $a['created_at'] ?? '')) ?: 0;
            $bDate = strtotime((string)($b['updated_at'] ?? $b['created_at'] ?? '')) ?: 0;

            if ($aDate === $bDate) {
                return strcasecmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
            }

            return $bDate <=> $aDate;
        });

        return $pages;
    }
}

if (!function_exists('homepage_find_published_landing_page')) {
    function homepage_find_published_landing_page(string $slug): ?array
    {
        $slug = slugify($slug);
        if ($slug === '') {
            return null;
        }

        if (function_exists('landing_page_public_find')) {
            $page = landing_page_public_find($slug);
            if (is_array($page)) {
                return $page;
            }
        }

        foreach (homepage_published_landing_pages() as $page) {
            if ((string)($page['slug'] ?? '') === $slug) {
                return $page;
            }
        }

        return null;
    }
}

if (!function_exists('homepage_selected_landing_page')) {
    function homepage_selected_landing_page(?array $settings = null): ?array
    {
        $settings = $settings ?? homepage_settings();

        if ((string)($settings['source'] ?? 'template') !== 'landing_page') {
            return null;
        }

        return homepage_find_published_landing_page((string)($settings['landing_page_slug'] ?? ''));
    }
}

if (!function_exists('homepage_default_settings')) {
    function homepage_default_settings(): array
    {
        $mode = 'catalog';
        $modeDefaults = homepage_modes()[$mode];

        return [
            'source' => 'template',
            'landing_page_slug' => '',
            'mode' => $mode,
            'hero' => [
                'eyebrow' => 'Website siap jualan untuk UMKM',
                'title' => 'Bangun Website Produk, Jasa, dan Landing Page dalam Satu Template',
                'description' => 'Gunakan template ini untuk company profile, katalog produk, sales page, form lead, checkout manual, dan konsultasi WhatsApp. Semua konten bisa disesuaikan dengan brand bisnis Anda.',
                'primary_label' => 'Lihat Katalog',
                'primary_url' => '/katalog',
                'secondary_label' => 'Konsultasi',
                'secondary_url' => '/kontak',
            ],
            'sections' => [
                'hero' => true,
                'trustbar' => true,
                'profile_intro' => true,
                'featured_catalog' => true,
                'services_highlight' => false,
                'business_fit' => true,
                'latest_articles' => true,
                'trust_conversion' => true,
                'custom_sections' => true,
                'lead_form' => true,
                'portfolio_highlight' => false,
            ],
            'section_order' => homepage_default_section_order(),
            'trust_items' => [
                ['title' => 'Multi', 'text' => 'Cocok untuk produk, jasa, digital, dan booking'],
                ['title' => 'SEO', 'text' => 'Meta, schema, sitemap, artikel, dan clean slug'],
                ['title' => 'WA', 'text' => 'CTA WhatsApp dan form inquiry siap pakai'],
                ['title' => 'Checkout', 'text' => 'Order manual dan instruksi pembayaran'],
            ],
            'profile_intro' => [
                'eyebrow' => 'Beranda fleksibel',
                'title' => 'Pilih Gaya Beranda Sesuai Cara Bisnis Anda Berjualan',
                'description' => 'Beranda bisa diarahkan menjadi company profile, katalog, sales page, lead form, atau halaman brand sederhana.',
            ],
            'featured_catalog' => [
                'eyebrow' => 'Katalog pilihan',
                'title' => 'Contoh Katalog Produk & Layanan',
                'description' => 'Katalog contoh ini bisa diganti admin dengan produk, jasa, harga, gambar, kategori, deskripsi, dan tombol CTA sesuai bisnis masing-masing.',
                'button_label' => 'Buka Semua Katalog',
                'button_url' => '/katalog',
                'limit' => 6,
            ],
            'business_fit' => [
                'eyebrow' => 'Cocok untuk banyak bisnis',
                'title' => 'Fleksibel untuk Banyak Jenis Bisnis',
                'description' => 'Template ini disiapkan agar bisa dipakai UMKM yang menjual barang, jasa, layanan, paket, menu kuliner, produk digital, booking, sampai company profile.',
            ],
            'services_highlight' => [
                'eyebrow' => 'Layanan pilihan',
                'title' => 'Layanan yang Bisa Disesuaikan dengan Kebutuhan Anda',
                'description' => 'Tampilkan jasa, konsultasi, booking, paket layanan, atau penawaran custom yang paling penting untuk calon pelanggan.',
                'button_label' => 'Lihat Semua Layanan',
                'button_url' => '/layanan',
                'limit' => 3,
            ],
            'latest_articles' => [
                'eyebrow' => 'Artikel & edukasi',
                'title' => 'Artikel Terbaru',
                'description' => 'Gunakan blog untuk edukasi pelanggan, menjawab FAQ, dan memperkuat SEO bisnis.',
                'limit' => 3,
            ],
            'lead_form' => [
                'title' => 'Butuh Bantuan Memilih Produk atau Layanan?',
                'text' => 'Isi form singkat ini untuk konsultasi, permintaan penawaran, booking, atau pertanyaan lainnya.',
                'button' => 'Kirim Permintaan',
            ],
            'portfolio_highlight' => [
                'eyebrow' => 'Portfolio & showcase',
                'title' => 'Bukti Karya, Project, atau Penawaran Unggulan',
                'description' => 'Tampilkan karya, case study, project, produk unggulan, atau bukti hasil agar pengunjung lebih yakin.',
                'button_label' => 'Lihat Portfolio',
                'button_url' => '/portfolio',
                'limit' => 3,
            ],
            'updated_at' => '',
        ];
    }
}

if (!function_exists('homepage_array_is_list')) {
    function homepage_array_is_list(array $array): bool
    {
        $expected = 0;
        foreach (array_keys($array) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }
}

if (!function_exists('homepage_deep_merge')) {
    function homepage_deep_merge(array $defaults, array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && !homepage_array_is_list($value)) {
                $defaults[$key] = homepage_deep_merge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}

if (!function_exists('homepage_bool')) {
    function homepage_bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return $default;
    }
}

if (!function_exists('homepage_clean_text')) {
    function homepage_clean_text(mixed $value, int $limit = 180): string
    {
        $value = trim(strip_tags((string)$value));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }
}

if (!function_exists('homepage_clean_url')) {
    function homepage_clean_url(mixed $value, string $fallback = '/'): string
    {
        $value = trim((string)$value);

        if ($value === '') {
            return $fallback;
        }

        if ($value === '#') {
            return '#';
        }

        if (str_starts_with($value, '#')) {
            return preg_match('/^#[a-zA-Z0-9_\-]+$/', $value) ? $value : $fallback;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (preg_match('#^(mailto:|tel:)#i', $value)) {
            return $value;
        }

        $value = '/' . ltrim($value, '/');

        return preg_match('#^/[a-zA-Z0-9._~/%?=&+\-]*$#', $value)
            ? $value
            : $fallback;
    }
}

if (!function_exists('homepage_url_to_href')) {
    function homepage_url_to_href(string $urlValue): string
    {
        $urlValue = trim($urlValue);

        if ($urlValue === '') {
            return url('');
        }

        if ($urlValue === '#' || str_starts_with($urlValue, '#')) {
            return $urlValue;
        }

        if (filter_var($urlValue, FILTER_VALIDATE_URL) || preg_match('#^(mailto:|tel:)#i', $urlValue)) {
            return $urlValue;
        }

        return url(ltrim($urlValue, '/'));
    }
}

if (!function_exists('homepage_normalize_settings')) {
    function homepage_normalize_settings(array $settings): array
    {
        $defaults = homepage_default_settings();
        $settings = homepage_deep_merge($defaults, $settings);
        $modes = homepage_modes();

        $mode = (string)($settings['mode'] ?? $defaults['mode']);
        if (!isset($modes[$mode])) {
            $mode = $defaults['mode'];
        }
        $settings['mode'] = $mode;

        $source = (string)($settings['source'] ?? 'template');
        if (!isset(homepage_source_options()[$source])) {
            $source = 'template';
        }

        $landingPageSlug = slugify((string)($settings['landing_page_slug'] ?? ''));
        if ($source === 'landing_page' && ($landingPageSlug === '' || homepage_find_published_landing_page($landingPageSlug) === null)) {
            $source = 'template';
            $landingPageSlug = '';
        }

        $settings['source'] = $source;
        $settings['landing_page_slug'] = $landingPageSlug;

        $hero = (array)($settings['hero'] ?? []);
        $modeDefaults = $modes[$mode];
        $heroFallbacks = [
            'eyebrow' => $modeDefaults['hero_eyebrow'],
            'title' => $modeDefaults['hero_title'],
            'description' => $modeDefaults['hero_description'],
            'primary_label' => $modeDefaults['primary_label'],
            'primary_url' => $modeDefaults['primary_url'],
            'secondary_label' => $modeDefaults['secondary_label'],
            'secondary_url' => $modeDefaults['secondary_url'],
        ];

        $settings['hero'] = [
            'eyebrow' => homepage_clean_text($hero['eyebrow'] ?? $heroFallbacks['eyebrow'], 120) ?: $heroFallbacks['eyebrow'],
            'title' => homepage_clean_text($hero['title'] ?? $heroFallbacks['title'], 140) ?: $heroFallbacks['title'],
            'description' => homepage_clean_text($hero['description'] ?? $heroFallbacks['description'], 320) ?: $heroFallbacks['description'],
            'primary_label' => homepage_clean_text($hero['primary_label'] ?? $heroFallbacks['primary_label'], 40) ?: $heroFallbacks['primary_label'],
            'primary_url' => homepage_clean_url($hero['primary_url'] ?? $heroFallbacks['primary_url'], $heroFallbacks['primary_url']),
            'secondary_label' => homepage_clean_text($hero['secondary_label'] ?? $heroFallbacks['secondary_label'], 40) ?: $heroFallbacks['secondary_label'],
            'secondary_url' => homepage_clean_url($hero['secondary_url'] ?? $heroFallbacks['secondary_url'], $heroFallbacks['secondary_url']),
        ];

        $sectionDefaults = (array)$defaults['sections'];
        $sections = (array)($settings['sections'] ?? []);
        foreach ($sectionDefaults as $key => $fallback) {
            $settings['sections'][$key] = homepage_bool($sections[$key] ?? $fallback, (bool)$fallback);
        }

        $settings['section_order'] = homepage_normalize_section_order($settings['section_order'] ?? homepage_default_section_order());

        $trustItems = [];
        foreach ((array)($settings['trust_items'] ?? []) as $item) {
            $title = homepage_clean_text($item['title'] ?? '', 28);
            $text = homepage_clean_text($item['text'] ?? '', 90);
            if ($title === '' && $text === '') {
                continue;
            }
            $trustItems[] = [
                'title' => $title ?: 'Info',
                'text' => $text ?: 'Keterangan singkat bisnis Anda.',
            ];
        }
        $settings['trust_items'] = array_slice($trustItems ?: $defaults['trust_items'], 0, 4);

        foreach (['profile_intro', 'featured_catalog', 'services_highlight', 'business_fit', 'latest_articles', 'lead_form', 'portfolio_highlight'] as $section) {
            $values = (array)($settings[$section] ?? []);
            $fallback = (array)($defaults[$section] ?? []);

            foreach ($fallback as $field => $fallbackValue) {
                if (in_array($field, ['limit'], true)) {
                    $settings[$section][$field] = max(1, min(12, (int)($values[$field] ?? $fallbackValue)));
                    continue;
                }

                if (str_ends_with((string)$field, '_url')) {
                    $settings[$section][$field] = homepage_clean_url($values[$field] ?? $fallbackValue, (string)$fallbackValue);
                    continue;
                }

                $settings[$section][$field] = homepage_clean_text($values[$field] ?? $fallbackValue, 260) ?: (string)$fallbackValue;
            }
        }

        $settings['updated_at'] = homepage_clean_text($settings['updated_at'] ?? '', 60);

        return $settings;
    }
}

if (!function_exists('homepage_settings')) {
    function homepage_settings(): array
    {
        static $cached = null;

        if (is_array($cached)) {
            return $cached;
        }

        $defaults = homepage_default_settings();
        $file = homepage_storage_file();

        if (!is_file($file)) {
            $cached = homepage_normalize_settings($defaults);
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);

        if (!is_array($decoded)) {
            $cached = homepage_normalize_settings($defaults);
            return $cached;
        }

        $cached = homepage_normalize_settings(homepage_deep_merge($defaults, $decoded));

        return $cached;
    }
}

if (!function_exists('homepage_settings_from_post')) {
    function homepage_settings_from_post(array $input): array
    {
        $current = homepage_settings();
        $mode = homepage_clean_text($input['mode'] ?? ($current['mode'] ?? 'catalog'), 60);
        if (!isset(homepage_modes()[$mode])) {
            $mode = 'catalog';
        }

        $source = homepage_clean_text($input['source'] ?? ($current['source'] ?? 'template'), 40);
        if (!isset(homepage_source_options()[$source])) {
            $source = 'template';
        }

        $landingPageSlug = slugify((string)($input['landing_page_slug'] ?? ($current['landing_page_slug'] ?? '')));
        if ($source === 'landing_page' && homepage_find_published_landing_page($landingPageSlug) === null) {
            throw new RuntimeException('Pilih landing page yang sudah dipublish terlebih dahulu sebelum dijadikan beranda.');
        }

        $settings = $current;
        $settings['source'] = $source;
        $settings['landing_page_slug'] = $source === 'landing_page' ? $landingPageSlug : '';
        $settings['mode'] = $mode;
        $settings['hero'] = [
            'eyebrow' => homepage_clean_text($input['hero_eyebrow'] ?? '', 120),
            'title' => homepage_clean_text($input['hero_title'] ?? '', 140),
            'description' => homepage_clean_text($input['hero_description'] ?? '', 320),
            'primary_label' => homepage_clean_text($input['hero_primary_label'] ?? '', 40),
            'primary_url' => homepage_clean_url($input['hero_primary_url'] ?? '', '/katalog'),
            'secondary_label' => homepage_clean_text($input['hero_secondary_label'] ?? '', 40),
            'secondary_url' => homepage_clean_url($input['hero_secondary_url'] ?? '', '/kontak'),
        ];

        $sections = (array)($input['sections'] ?? []);
        foreach (array_keys((array)homepage_default_settings()['sections']) as $key) {
            $settings['sections'][$key] = isset($sections[$key]);
        }

        $orderKeys = (array)($input['section_order_key'] ?? []);
        $orderRanks = (array)($input['section_order_rank'] ?? []);
        if ($orderKeys) {
            $rankedSections = [];
            foreach ($orderKeys as $index => $key) {
                $cleanKey = strtolower(trim((string)$key));
                $cleanKey = str_replace('-', '_', $cleanKey);
                $cleanKey = preg_replace('/[^a-z0-9_]/', '', $cleanKey) ?? '';
                if ($cleanKey === '' || !isset(homepage_section_definitions()[$cleanKey])) {
                    continue;
                }
                $rankedSections[] = [
                    'key' => $cleanKey,
                    'rank' => max(1, min(999, (int)($orderRanks[$index] ?? (($index + 1) * 10)))),
                    'index' => (int)$index,
                ];
            }
            usort($rankedSections, static fn(array $a, array $b): int => ((int)$a['rank'] <=> (int)$b['rank']) ?: ((int)$a['index'] <=> (int)$b['index']));
            $settings['section_order'] = array_map(static fn(array $row): string => (string)$row['key'], $rankedSections);
        } else {
            $settings['section_order'] = (array)($input['section_order'] ?? ($current['section_order'] ?? homepage_default_section_order()));
        }

        $trustTitles = (array)($input['trust_title'] ?? []);
        $trustTexts = (array)($input['trust_text'] ?? []);
        $trustItems = [];
        for ($i = 0; $i < 4; $i++) {
            $title = homepage_clean_text($trustTitles[$i] ?? '', 28);
            $text = homepage_clean_text($trustTexts[$i] ?? '', 90);
            if ($title === '' && $text === '') {
                continue;
            }
            $trustItems[] = ['title' => $title, 'text' => $text];
        }
        $settings['trust_items'] = $trustItems ?: homepage_default_settings()['trust_items'];

        $sectionTextMap = [
            'profile_intro' => ['eyebrow', 'title', 'description'],
            'featured_catalog' => ['eyebrow', 'title', 'description', 'button_label', 'button_url', 'limit'],
            'services_highlight' => ['eyebrow', 'title', 'description', 'button_label', 'button_url', 'limit'],
            'business_fit' => ['eyebrow', 'title', 'description'],
            'latest_articles' => ['eyebrow', 'title', 'description', 'limit'],
            'lead_form' => ['title', 'text', 'button'],
            'portfolio_highlight' => ['eyebrow', 'title', 'description', 'button_label', 'button_url', 'limit'],
        ];

        foreach ($sectionTextMap as $section => $fields) {
            foreach ($fields as $field) {
                $inputKey = $section . '_' . $field;
                if ($field === 'limit') {
                    $settings[$section][$field] = max(1, min(12, (int)($input[$inputKey] ?? ($settings[$section][$field] ?? 3))));
                } elseif (str_ends_with($field, '_url')) {
                    $settings[$section][$field] = homepage_clean_url($input[$inputKey] ?? '', (string)(homepage_default_settings()[$section][$field] ?? '/'));
                } else {
                    $settings[$section][$field] = homepage_clean_text($input[$inputKey] ?? '', $field === 'description' || $field === 'text' ? 260 : 100);
                }
            }
        }

        $settings['updated_at'] = date(DATE_ATOM);

        return homepage_normalize_settings($settings);
    }
}

if (!function_exists('homepage_save_settings')) {
    function homepage_save_settings(array $settings): array
    {
        $settings = homepage_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            throw new RuntimeException('Folder penyimpanan belum bisa dibuat.');
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false || file_put_contents(homepage_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Pengaturan beranda gagal disimpan. Cek permission folder storage.');
        }

        @chmod(homepage_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'homepage', null, 'Pengaturan beranda diperbarui.', [
                'source' => $settings['source'] ?? 'template',
                'mode' => $settings['mode'] ?? 'catalog',
                'landing_page_slug' => $settings['landing_page_slug'] ?? '',
            ]);
        }

        return $settings;
    }
}

if (!function_exists('homepage_reset_settings')) {
    function homepage_reset_settings(): void
    {
        homepage_save_settings(homepage_default_settings());
    }
}


if (!function_exists('homepage_ordered_sections')) {
    function homepage_ordered_sections(?array $settings = null): array
    {
        $settings = $settings ?? homepage_settings();
        return homepage_normalize_section_order($settings['section_order'] ?? homepage_default_section_order());
    }
}

if (!function_exists('homepage_section_enabled')) {
    function homepage_section_enabled(string $key): bool
    {
        $settings = homepage_settings();

        return !empty($settings['sections'][$key]);
    }
}

if (!function_exists('homepage_mode_cards')) {
    function homepage_mode_cards(string $mode): array
    {
        $cards = [
            'company_profile' => [
                ['title' => 'Profil Bisnis', 'text' => 'Tampilkan cerita brand, keunggulan, dan cara kerja.'],
                ['title' => 'Layanan Utama', 'text' => 'Arahkan calon pelanggan ke layanan yang paling penting.'],
                ['title' => 'Kontak Cepat', 'text' => 'Permudah pengunjung menghubungi bisnis Anda.'],
            ],
            'catalog' => [
                ['title' => 'Produk Fisik', 'text' => 'Cocok untuk toko, retail, hampers, fashion, dan kuliner.'],
                ['title' => 'Jasa & Paket', 'text' => 'Tampilkan layanan, booking, konsultasi, atau quotation.'],
                ['title' => 'Digital & Menu', 'text' => 'Mendukung produk digital, kelas, menu, dan penawaran custom.'],
            ],
            'sales_page' => [
                ['title' => 'Headline Jelas', 'text' => 'Fokus ke masalah pelanggan dan penawaran utama.'],
                ['title' => 'Benefit Kuat', 'text' => 'Tampilkan alasan kenapa pelanggan perlu memilih Anda.'],
                ['title' => 'CTA Terarah', 'text' => 'Arahkan ke checkout, WhatsApp, atau form konsultasi.'],
            ],
            'lead_generation' => [
                ['title' => 'Form Singkat', 'text' => 'Kumpulkan nama, kontak, kebutuhan, dan pesan pelanggan.'],
                ['title' => 'Inquiry Rapi', 'text' => 'Data masuk bisa dipantau dari dashboard admin.'],
                ['title' => 'Follow-up Mudah', 'text' => 'Tim bisa menghubungi calon pelanggan lebih cepat.'],
            ],
            'simple_brand' => [
                ['title' => 'Ringkas', 'text' => 'Beranda sederhana untuk bisnis yang ingin tampil cepat.'],
                ['title' => 'Mudah Diarahkan', 'text' => 'Tambahkan tombol ke katalog, kontak, atau WhatsApp.'],
                ['title' => 'Tetap Profesional', 'text' => 'Tampilan tetap rapi mengikuti warna brand.'],
            ],
        ];

        return $cards[$mode] ?? $cards['catalog'];
    }
}
