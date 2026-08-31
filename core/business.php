<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UNIVERSAL BUSINESS MODE & FLEXIBLE CATEGORY ENGINE
|--------------------------------------------------------------------------
| File-based settings for turning this template into a toko produk, jasa,
| personal branding, portfolio, company profile, course, landing page, or
| hybrid growth website without breaking existing catalog/order features.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('business_storage_file')) {
    function business_storage_file(): string
    {
        return STORAGE_PATH . '/business-settings.json';
    }
}

if (!function_exists('business_mode_definitions')) {
    function business_mode_definitions(): array
    {
        return [
            'umkm_shop' => [
                'label' => 'UMKM Toko Produk',
                'description' => 'Untuk bisnis yang fokus menjual produk fisik, katalog, checkout, invoice, dan WhatsApp order.',
                'recommended_homepage' => 'catalog',
                'schema' => 'LocalBusiness',
                'labels' => [
                    'catalog' => 'Katalog',
                    'product' => 'Produk',
                    'service' => 'Layanan',
                    'portfolio' => 'Portfolio',
                    'checkout' => 'Checkout',
                    'lead' => 'Lead',
                    'article' => 'Artikel',
                    'primary_cta' => 'Pesan via WhatsApp',
                ],
                'visibility' => ['catalog' => true, 'services' => true, 'portfolio' => false, 'articles' => true, 'checkout' => true, 'lead_forms' => true],
            ],
            'services' => [
                'label' => 'Jasa & Layanan',
                'description' => 'Untuk agency, konsultan, bengkel, klinik, edukasi offline, dan jasa profesional.',
                'recommended_homepage' => 'service',
                'schema' => 'Service',
                'labels' => [
                    'catalog' => 'Paket Layanan',
                    'product' => 'Paket',
                    'service' => 'Layanan',
                    'portfolio' => 'Case Study',
                    'checkout' => 'Request Penawaran',
                    'lead' => 'Prospek',
                    'article' => 'Insight',
                    'primary_cta' => 'Konsultasi Layanan',
                ],
                'visibility' => ['catalog' => true, 'services' => true, 'portfolio' => true, 'articles' => true, 'checkout' => true, 'lead_forms' => true],
            ],
            'personal_brand' => [
                'label' => 'Personal Branding + Produk',
                'description' => 'Untuk mentor, kreator, trainer, expert, public figure, atau profesional yang membangun authority dan menjual produk/jasa.',
                'recommended_homepage' => 'profile',
                'schema' => 'Person',
                'labels' => [
                    'catalog' => 'Produk & Program',
                    'product' => 'Produk',
                    'service' => 'Program',
                    'portfolio' => 'Karya',
                    'checkout' => 'Daftar / Beli',
                    'lead' => 'Audiens',
                    'article' => 'Insight',
                    'primary_cta' => 'Mulai Konsultasi',
                ],
                'visibility' => ['catalog' => true, 'services' => true, 'portfolio' => true, 'articles' => true, 'checkout' => true, 'lead_forms' => true],
            ],
            'portfolio' => [
                'label' => 'Portfolio / Showcase',
                'description' => 'Untuk menampilkan karya, case study, client result, galeri, dan tombol kontak tanpa harus banyak produk.',
                'recommended_homepage' => 'portfolio',
                'schema' => 'CreativeWork',
                'labels' => [
                    'catalog' => 'Showcase',
                    'product' => 'Item',
                    'service' => 'Layanan',
                    'portfolio' => 'Portfolio',
                    'checkout' => 'Minta Penawaran',
                    'lead' => 'Inquiry',
                    'article' => 'Cerita Karya',
                    'primary_cta' => 'Bahas Project',
                ],
                'visibility' => ['catalog' => false, 'services' => true, 'portfolio' => true, 'articles' => true, 'checkout' => false, 'lead_forms' => true],
            ],
            'company_profile' => [
                'label' => 'Company Profile',
                'description' => 'Untuk profil perusahaan/organisasi dengan layanan, artikel trust, kontak, dan halaman tentang yang kuat.',
                'recommended_homepage' => 'profile',
                'schema' => 'Organization',
                'labels' => [
                    'catalog' => 'Solusi',
                    'product' => 'Solusi',
                    'service' => 'Layanan',
                    'portfolio' => 'Project',
                    'checkout' => 'Hubungi Sales',
                    'lead' => 'Inquiry',
                    'article' => 'Berita & Insight',
                    'primary_cta' => 'Hubungi Tim',
                ],
                'visibility' => ['catalog' => true, 'services' => true, 'portfolio' => true, 'articles' => true, 'checkout' => false, 'lead_forms' => true],
            ],
            'digital_course' => [
                'label' => 'Produk Digital / Course',
                'description' => 'Untuk e-book, kelas online, template, file digital, membership, dan checkout digital.',
                'recommended_homepage' => 'sales',
                'schema' => 'Course',
                'labels' => [
                    'catalog' => 'Kelas & Produk Digital',
                    'product' => 'Produk Digital',
                    'service' => 'Program',
                    'portfolio' => 'Hasil Peserta',
                    'checkout' => 'Daftar Sekarang',
                    'lead' => 'Peserta',
                    'article' => 'Materi Gratis',
                    'primary_cta' => 'Akses Sekarang',
                ],
                'visibility' => ['catalog' => true, 'services' => true, 'portfolio' => true, 'articles' => true, 'checkout' => true, 'lead_forms' => true],
            ],
            'landing_page' => [
                'label' => 'Landing Page / Lead Magnet',
                'description' => 'Untuk campaign fokus satu halaman, form lead, WA CTA, dan penawaran spesifik.',
                'recommended_homepage' => 'sales',
                'schema' => 'WebPage',
                'labels' => [
                    'catalog' => 'Penawaran',
                    'product' => 'Offer',
                    'service' => 'Benefit',
                    'portfolio' => 'Bukti',
                    'checkout' => 'Ambil Offer',
                    'lead' => 'Lead',
                    'article' => 'Edukasi',
                    'primary_cta' => 'Ambil Penawaran',
                ],
                'visibility' => ['catalog' => false, 'services' => false, 'portfolio' => false, 'articles' => false, 'checkout' => true, 'lead_forms' => true],
            ],
            'hybrid' => [
                'label' => 'Hybrid Growth Website',
                'description' => 'Mode paling fleksibel untuk gabungan produk, jasa, portfolio, artikel SEO, form, WhatsApp, dan checkout.',
                'recommended_homepage' => 'catalog',
                'schema' => 'LocalBusiness',
                'labels' => [
                    'catalog' => 'Katalog',
                    'product' => 'Produk/Jasa',
                    'service' => 'Layanan',
                    'portfolio' => 'Portfolio',
                    'checkout' => 'Checkout',
                    'lead' => 'Lead',
                    'article' => 'Artikel',
                    'primary_cta' => 'Konsultasi / Pesan',
                ],
                'visibility' => ['catalog' => true, 'services' => true, 'portfolio' => true, 'articles' => true, 'checkout' => true, 'lead_forms' => true],
            ],
        ];
    }
}

if (!function_exists('business_default_category_bank')) {
    function business_default_category_bank(): array
    {
        return [
            'catalog' => [
                ['label' => 'Produk Fisik', 'description' => 'Barang yang dikirim ke pelanggan.', 'enabled' => true],
                ['label' => 'Jasa & Layanan', 'description' => 'Layanan profesional atau konsultasi.', 'enabled' => true],
                ['label' => 'Produk Digital', 'description' => 'File, template, e-book, atau akses digital.', 'enabled' => true],
                ['label' => 'E-book', 'description' => 'PDF, workbook, panduan, dan file download.', 'enabled' => true],
                ['label' => 'E-course', 'description' => 'Kelas online, video course, dan program belajar.', 'enabled' => true],
                ['label' => 'Kuliner', 'description' => 'Menu makanan, minuman, katering, dan frozen food.', 'enabled' => true],
                ['label' => 'Booking', 'description' => 'Reservasi, appointment, rental, atau layanan terjadwal.', 'enabled' => true],
                ['label' => 'Custom Order', 'description' => 'Pesanan khusus, quotation, atau paket custom.', 'enabled' => true],
                ['label' => 'Paket / Bundle', 'description' => 'Paket gabungan produk/jasa/digital.', 'enabled' => true],
                ['label' => 'Promo', 'description' => 'Campaign, diskon, atau penawaran terbatas.', 'enabled' => true],
            ],
            'article' => [
                ['label' => 'Panduan Bisnis', 'description' => 'Tips mengelola katalog, penawaran, trust, dan komunikasi pelanggan.', 'enabled' => true],
                ['label' => 'Produk & Layanan', 'description' => 'Edukasi produk, jasa, paket, booking, dan value penawaran.', 'enabled' => true],
                ['label' => 'Marketing & SEO', 'description' => 'Artikel SEO, landing page, CTA, conversion, dan campaign.', 'enabled' => true],
                ['label' => 'Checkout & Pembayaran', 'description' => 'Order, invoice, pembayaran manual, dan follow-up pelanggan.', 'enabled' => true],
                ['label' => 'Info Lokal', 'description' => 'Area layanan, cabang, pengiriman, dan kebutuhan lokal.', 'enabled' => true],
                ['label' => 'Promo & Layanan', 'description' => 'Promo, paket campaign, dan update layanan bisnis.', 'enabled' => true],
                ['label' => 'Portfolio / Case Study', 'description' => 'Cerita hasil kerja, project, testimoni, dan bukti sosial.', 'enabled' => true],
                ['label' => 'Personal Branding', 'description' => 'Insight, opini, dan konten authority untuk figur/pakar.', 'enabled' => true],
            ],
            'portfolio' => [
                ['label' => 'Case Study', 'description' => 'Cerita masalah, solusi, proses, dan hasil.', 'enabled' => true],
                ['label' => 'Project Client', 'description' => 'Project yang pernah dikerjakan untuk klien.', 'enabled' => true],
                ['label' => 'Desain / Kreatif', 'description' => 'Logo, desain visual, konten, foto, video, atau aset kreatif.', 'enabled' => true],
                ['label' => 'Website / Aplikasi', 'description' => 'Website, landing page, aplikasi, atau sistem digital.', 'enabled' => true],
                ['label' => 'Testimoni / Result', 'description' => 'Hasil pelanggan, review, dan bukti performa.', 'enabled' => true],
            ],
        ];
    }
}

if (!function_exists('business_default_settings')) {
    function business_default_settings(): array
    {
        $mode = 'hybrid';
        $definitions = business_mode_definitions();
        $modeDefinition = $definitions[$mode];

        return [
            'business_mode' => $mode,
            'layout_mode' => 'multi_page',
            'schema_profile' => (string)$modeDefinition['schema'],
            'recommended_homepage' => (string)$modeDefinition['recommended_homepage'],
            'labels' => $modeDefinition['labels'],
            'visibility' => $modeDefinition['visibility'],
            'categories' => business_default_category_bank(),
            'notes' => '',
            'allow_empty_categories' => false,
            'updated_at' => '',
        ];
    }
}

if (!function_exists('business_array_is_list')) {
    function business_array_is_list(array $array): bool
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

if (!function_exists('business_deep_merge')) {
    function business_deep_merge(array $defaults, array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && !business_array_is_list($value)) {
                $defaults[$key] = business_deep_merge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}

if (!function_exists('business_clean_text')) {
    function business_clean_text(mixed $value, int $limit = 140): string
    {
        $value = trim(strip_tags((string)$value));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }
}

if (!function_exists('business_bool')) {
    function business_bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
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
        return $default;
    }
}

if (!function_exists('business_normalize_category_rows')) {
    function business_normalize_category_rows(mixed $rows, array $fallback = []): array
    {
        $rows = is_array($rows) ? $rows : [];
        $normalized = [];
        $seen = [];

        foreach ($rows as $row) {
            if (is_string($row)) {
                $row = ['label' => $row, 'description' => '', 'enabled' => true];
            }
            if (!is_array($row)) {
                continue;
            }

            $label = business_clean_text($row['label'] ?? '', 80);
            if ($label === '') {
                continue;
            }
            $slug = slugify($label);
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            $normalized[] = [
                'label' => $label,
                'slug' => $slug,
                'description' => business_clean_text($row['description'] ?? '', 180),
                'enabled' => business_bool($row['enabled'] ?? true, true),
            ];
        }

        if (!$normalized && $fallback) {
            return business_normalize_category_rows($fallback, []);
        }

        return array_values($normalized);
    }
}

if (!function_exists('business_normalize_settings')) {
    function business_normalize_settings(array $settings): array
    {
        $defaults = business_default_settings();
        $definitions = business_mode_definitions();
        $mode = (string)($settings['business_mode'] ?? 'hybrid');
        if (!isset($definitions[$mode])) {
            $mode = 'hybrid';
        }
        $modeDefinition = $definitions[$mode];

        $settings['business_mode'] = $mode;
        $settings['layout_mode'] = in_array((string)($settings['layout_mode'] ?? 'multi_page'), ['multi_page', 'one_page'], true)
            ? (string)$settings['layout_mode']
            : 'multi_page';
        $settings['schema_profile'] = business_clean_text($settings['schema_profile'] ?? ($modeDefinition['schema'] ?? 'LocalBusiness'), 50);
        $settings['recommended_homepage'] = business_clean_text($settings['recommended_homepage'] ?? ($modeDefinition['recommended_homepage'] ?? 'catalog'), 50);
        $settings['notes'] = business_clean_text($settings['notes'] ?? '', 300);

        $labels = is_array($settings['labels'] ?? null) ? $settings['labels'] : [];
        $settings['labels'] = [];
        foreach ((array)$defaults['labels'] as $key => $fallback) {
            $settings['labels'][$key] = business_clean_text($labels[$key] ?? ($modeDefinition['labels'][$key] ?? $fallback), 90);
        }

        $visibility = is_array($settings['visibility'] ?? null) ? $settings['visibility'] : [];
        $settings['visibility'] = [];
        foreach ((array)$defaults['visibility'] as $key => $fallback) {
            $settings['visibility'][$key] = business_bool($visibility[$key] ?? ($modeDefinition['visibility'][$key] ?? $fallback), (bool)$fallback);
        }

        $settings['allow_empty_categories'] = business_bool($settings['allow_empty_categories'] ?? false, false);

        $categoryDefaults = business_default_category_bank();
        $categories = is_array($settings['categories'] ?? null) ? $settings['categories'] : [];
        $settings['categories'] = [];
        foreach ($categoryDefaults as $domain => $fallbackRows) {
            $fallback = !empty($settings['allow_empty_categories']) ? [] : $fallbackRows;
            $settings['categories'][$domain] = business_normalize_category_rows($categories[$domain] ?? [], $fallback);
        }

        return $settings;
    }
}

if (!function_exists('business_settings')) {
    function business_settings(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $defaults = business_default_settings();
        $file = business_storage_file();
        if (!is_file($file)) {
            $cached = business_normalize_settings($defaults);
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = business_normalize_settings($defaults);
            return $cached;
        }

        $cached = business_normalize_settings(business_deep_merge($defaults, $decoded));
        return $cached;
    }
}

if (!function_exists('business_save_settings')) {
    function business_save_settings(array $settings): void
    {
        $settings = business_normalize_settings($settings);
        $settings['updated_at'] = date('c');

        $file = business_storage_file();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($file, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Pengaturan mode bisnis gagal disimpan. Pastikan folder storage dapat ditulis.');
        }
    }
}

if (!function_exists('business_reset_settings')) {
    function business_reset_settings(): void
    {
        $file = business_storage_file();
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

if (!function_exists('business_settings_from_post')) {
    function business_settings_from_post(array $post, array $current): array
    {
        $mode = (string)($post['business_mode'] ?? ($current['business_mode'] ?? 'hybrid'));
        $definitions = business_mode_definitions();
        if (!isset($definitions[$mode])) {
            $mode = 'hybrid';
        }

        $applyPreset = !empty($post['apply_mode_preset']);
        $base = $applyPreset ? business_default_settings() : $current;
        $modeDefinition = $definitions[$mode];

        $labels = is_array($base['labels'] ?? null) ? $base['labels'] : [];
        foreach (array_keys((array)business_default_settings()['labels']) as $key) {
            $labels[$key] = business_clean_text($post['label_' . $key] ?? ($applyPreset ? ($modeDefinition['labels'][$key] ?? '') : ($labels[$key] ?? '')), 90);
        }

        $visibility = is_array($base['visibility'] ?? null) ? $base['visibility'] : [];
        $visibilityKeys = array_keys((array)business_default_settings()['visibility']);
        $hasVisibilityPost = false;
        foreach ($visibilityKeys as $key) {
            if (array_key_exists('visibility_' . $key, $post)) {
                $hasVisibilityPost = true;
                break;
            }
        }
        foreach ($visibilityKeys as $key) {
            if ($applyPreset && isset($modeDefinition['visibility'][$key])) {
                $visibility[$key] = (bool)$modeDefinition['visibility'][$key];
            } elseif ($hasVisibilityPost) {
                $visibility[$key] = !empty($post['visibility_' . $key]);
            } else {
                $visibility[$key] = (bool)($visibility[$key] ?? true);
            }
        }

        return [
            'business_mode' => $mode,
            'layout_mode' => in_array((string)($post['layout_mode'] ?? 'multi_page'), ['multi_page', 'one_page'], true) ? (string)$post['layout_mode'] : 'multi_page',
            'schema_profile' => business_clean_text($post['schema_profile'] ?? ($modeDefinition['schema'] ?? 'LocalBusiness'), 50),
            'recommended_homepage' => business_clean_text($modeDefinition['recommended_homepage'] ?? 'catalog', 50),
            'labels' => $labels,
            'visibility' => $visibility,
            'categories' => $base['categories'] ?? business_default_category_bank(),
            'notes' => business_clean_text($post['notes'] ?? '', 300),
            'allow_empty_categories' => !empty($base['allow_empty_categories']),
        ];
    }
}

if (!function_exists('business_category_settings_from_post')) {
    function business_category_settings_from_post(array $post, array $current): array
    {
        $settings = $current;
        $categoryDefaults = business_default_category_bank();
        $settings['categories'] = is_array($settings['categories'] ?? null) ? $settings['categories'] : $categoryDefaults;

        foreach (array_keys($categoryDefaults) as $domain) {
            $labels = is_array($post[$domain . '_label'] ?? null) ? $post[$domain . '_label'] : [];
            $descriptions = is_array($post[$domain . '_description'] ?? null) ? $post[$domain . '_description'] : [];
            $enabled = is_array($post[$domain . '_enabled'] ?? null) ? $post[$domain . '_enabled'] : [];
            $rows = [];

            $max = max(count($labels), count($descriptions), count($enabled));
            for ($i = 0; $i < $max; $i++) {
                $label = business_clean_text($labels[$i] ?? '', 80);
                if ($label === '') {
                    continue;
                }
                $rows[] = [
                    'label' => $label,
                    'description' => business_clean_text($descriptions[$i] ?? '', 180),
                    'enabled' => isset($enabled[$i]),
                ];
            }

            $bulk = trim((string)($post[$domain . '_bulk'] ?? ''));
            if ($bulk !== '') {
                foreach (preg_split('/\R+/', $bulk) ?: [] as $line) {
                    $line = business_clean_text($line, 80);
                    if ($line !== '') {
                        $rows[] = ['label' => $line, 'description' => '', 'enabled' => true];
                    }
                }
            }

            $fallbackRows = !empty($settings['allow_empty_categories']) ? [] : ($categoryDefaults[$domain] ?? []);
            $settings['categories'][$domain] = business_normalize_category_rows($rows, $fallbackRows);
        }

        return $settings;
    }
}

if (!function_exists('business_current_mode')) {
    function business_current_mode(): array
    {
        $settings = business_settings();
        $definitions = business_mode_definitions();
        $mode = (string)($settings['business_mode'] ?? 'hybrid');
        return ($definitions[$mode] ?? $definitions['hybrid']) + ['key' => $mode];
    }
}

if (!function_exists('business_label')) {
    function business_label(string $key, ?string $fallback = null): string
    {
        $settings = business_settings();
        $labels = (array)($settings['labels'] ?? []);
        $value = trim((string)($labels[$key] ?? ''));
        return $value !== '' ? $value : (string)($fallback ?? $key);
    }
}

if (!function_exists('business_visibility')) {
    function business_visibility(string $key, bool $fallback = true): bool
    {
        $settings = business_settings();
        $visibility = (array)($settings['visibility'] ?? []);
        return array_key_exists($key, $visibility) ? (bool)$visibility[$key] : $fallback;
    }
}

if (!function_exists('business_category_rows')) {
    function business_category_rows(string $domain, bool $enabledOnly = true): array
    {
        $settings = business_settings();
        $categories = (array)($settings['categories'] ?? []);
        $defaults = business_default_category_bank();
        $rows = business_normalize_category_rows($categories[$domain] ?? [], $defaults[$domain] ?? []);
        if ($enabledOnly) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => !empty($row['enabled'])));
        }
        return $rows;
    }
}

if (!function_exists('business_category_labels')) {
    function business_category_labels(string $domain, bool $enabledOnly = true): array
    {
        return array_values(array_map(static fn(array $row): string => (string)$row['label'], business_category_rows($domain, $enabledOnly)));
    }
}

if (!function_exists('business_category_definition_map')) {
    function business_category_definition_map(string $domain): array
    {
        $map = [];
        foreach (business_category_rows($domain, true) as $row) {
            $slug = (string)($row['slug'] ?? slugify((string)$row['label']));
            if ($slug === '') {
                continue;
            }
            $map[$slug] = [
                'label' => (string)$row['label'],
                'description' => (string)($row['description'] ?? ''),
                'cta' => 'Lihat ' . (string)$row['label'],
            ];
        }
        return $map;
    }
}

if (!function_exists('business_category_label_by_slug')) {
    function business_category_label_by_slug(string $domain, string $slug): ?string
    {
        $slug = slugify($slug);
        foreach (business_category_rows($domain, false) as $row) {
            if ((string)($row['slug'] ?? slugify((string)$row['label'])) === $slug) {
                return (string)$row['label'];
            }
        }
        return null;
    }
}
