<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WEBSITE STARTER WIZARD & NICHE PRESET ENGINE
|--------------------------------------------------------------------------
| Lightweight setup assistant for UMKM Growth Web Template. It helps admin
| map a niche into business mode, category labels, starter SEO pages,
| conversion blocks, and onboarding checklist without deleting existing data.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('starter_wizard_storage_file')) {
    function starter_wizard_storage_file(): string
    {
        return STORAGE_PATH . '/website-starter-wizard.json';
    }
}

if (!function_exists('starter_wizard_clean')) {
    function starter_wizard_clean(mixed $value, int $max = 180): string
    {
        $value = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$value)));
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('starter_wizard_presets')) {
    function starter_wizard_presets(): array
    {
        return [
            'hybrid_umkm' => [
                'label' => 'Universal Business Growth',
                'badge' => 'Paling Fleksibel',
                'mode' => 'hybrid',
                'layout' => 'multi_page',
                'schema' => 'LocalBusiness',
                'headline' => 'Website serbaguna untuk produk, jasa, artikel SEO, landing page, WhatsApp, form, dan checkout.',
                'fit' => 'Cocok untuk UMKM yang model bisnisnya campuran atau masih berkembang.',
                'primary_cta' => 'Konsultasi / Pesan',
                'labels' => ['catalog' => 'Katalog', 'product' => 'Produk/Jasa', 'service' => 'Layanan', 'portfolio' => 'Portfolio', 'checkout' => 'Checkout', 'lead' => 'Lead', 'article' => 'Artikel', 'primary_cta' => 'Konsultasi / Pesan'],
                'categories' => [
                    'catalog' => ['Produk Fisik', 'Jasa & Layanan', 'Produk Digital', 'Paket / Bundle', 'Promo'],
                    'article' => ['Panduan Bisnis', 'Produk & Layanan', 'Marketing & SEO', 'Info Lokal', 'FAQ'],
                    'portfolio' => ['Case Study', 'Project Client', 'Testimoni / Result'],
                ],
                'starter_pages' => ['Beranda growth', 'Katalog utama', 'Artikel edukasi', 'Landing page offer', 'Kontak/WhatsApp'],
                'blocks' => ['Hero CTA', 'Produk unggulan', 'Benefit', 'Testimoni', 'FAQ', 'Form lead', 'CTA WhatsApp'],
                'sprint' => ['Isi profil brand dan CTA utama', 'Upload 5 produk/layanan prioritas', 'Buat 3 artikel support', 'Pasang internal link ke money page', 'Pantau Growth Snapshot dan Conversion Opportunities'],
            ],
            'qurban_aqiqah' => [
                'label' => 'Qurban / Aqiqah Growth',
                'badge' => 'Starter Profesional',
                'mode' => 'umkm_shop',
                'layout' => 'multi_page',
                'schema' => 'LocalBusiness',
                'headline' => 'Naik level dari katalog hewan menjadi mesin lead, booking, invoice, bukti bayar, dan edukasi musiman.',
                'fit' => 'Cocok untuk paket sapi, kambing, domba, aqiqah, dokumentasi, dan reminder pembayaran.',
                'primary_cta' => 'Booking / Konsultasi Stok',
                'labels' => ['catalog' => 'Katalog Paket', 'product' => 'Paket Qurban/Aqiqah', 'service' => 'Layanan', 'portfolio' => 'Dokumentasi', 'checkout' => 'Booking Paket', 'lead' => 'Calon Jamaah/Customer', 'article' => 'Panduan Qurban', 'primary_cta' => 'Booking / Konsultasi Stok'],
                'categories' => [
                    'catalog' => ['Sapi Qurban', 'Kambing Qurban', 'Domba Qurban', 'Paket Aqiqah', 'Paket Kolektif', 'Promo Musim Qurban'],
                    'article' => ['Panduan Qurban', 'Panduan Aqiqah', 'Hukum & Syarat', 'Tips Memilih Hewan', 'FAQ Pembayaran'],
                    'portfolio' => ['Dokumentasi Penyembelihan', 'Testimoni Customer', 'Distribusi & Laporan'],
                ],
                'starter_pages' => ['Paket qurban best seller', 'Paket aqiqah', 'Cara booking', 'Panduan memilih hewan', 'Upload bukti bayar'],
                'blocks' => ['Stok paket', 'Trust badge', 'Dokumentasi', 'FAQ syariat', 'Form booking', 'Reminder pembayaran'],
                'sprint' => ['Rapikan paket utama dan harga', 'Buat artikel syarat hewan dan cara booking', 'Tambah FAQ pembayaran/dokumentasi', 'Pasang CTA WhatsApp per paket', 'Pantau inquiry, invoice, dan bukti bayar'],
            ],
            'kuliner' => [
                'label' => 'Kuliner / Food UMKM',
                'badge' => 'Order Harian',
                'mode' => 'umkm_shop',
                'layout' => 'multi_page',
                'schema' => 'LocalBusiness',
                'headline' => 'Dorong menu best seller, paket catering, frozen food, hampers, dan pre-order via WhatsApp/form.',
                'fit' => 'Cocok untuk resto kecil, snack, katering, frozen food, hampers, dan pre-order lokal.',
                'primary_cta' => 'Pesan Sekarang',
                'labels' => ['catalog' => 'Menu & Paket', 'product' => 'Menu', 'service' => 'Paket Catering', 'portfolio' => 'Galeri/Testimoni', 'checkout' => 'Pre-order', 'lead' => 'Prospek Order', 'article' => 'Ide Menu', 'primary_cta' => 'Pesan Sekarang'],
                'categories' => [
                    'catalog' => ['Menu Best Seller', 'Paket Catering', 'Frozen Food', 'Snack Box', 'Hampers', 'Promo Harian'],
                    'article' => ['Ide Menu Acara', 'Tips Penyimpanan', 'Paket Hemat', 'Info Area Delivery', 'FAQ Pre-order'],
                    'portfolio' => ['Galeri Menu', 'Review Pelanggan', 'Event Catering'],
                ],
                'starter_pages' => ['Menu best seller', 'Paket catering', 'Area delivery', 'Artikel menu acara', 'Form pre-order'],
                'blocks' => ['Foto menu', 'Harga paket', 'Review', 'Area layanan', 'FAQ pre-order', 'CTA WhatsApp'],
                'sprint' => ['Upload menu unggulan', 'Buat halaman paket catering/hampers', 'Tulis artikel ide menu acara', 'Tambahkan trust dari review pelanggan', 'Pantau CTA WhatsApp dan form pre-order'],
            ],
            'jasa_layanan' => [
                'label' => 'Jasa & Layanan',
                'badge' => 'Inquiry & Quotation',
                'mode' => 'services',
                'layout' => 'multi_page',
                'schema' => 'Service',
                'headline' => 'Ubah halaman layanan menjadi jalur konsultasi, form inquiry, quotation, follow-up, dan closing.',
                'fit' => 'Cocok untuk agency, bengkel, klinik, travel, konsultan, kursus, dan layanan lokal.',
                'primary_cta' => 'Minta Penawaran',
                'labels' => ['catalog' => 'Paket Layanan', 'product' => 'Paket', 'service' => 'Layanan', 'portfolio' => 'Case Study', 'checkout' => 'Minta Penawaran', 'lead' => 'Prospek', 'article' => 'Insight', 'primary_cta' => 'Minta Penawaran'],
                'categories' => [
                    'catalog' => ['Paket Basic', 'Paket Premium', 'Konsultasi', 'Maintenance', 'Custom Project'],
                    'article' => ['Problem & Solusi', 'Biaya Jasa', 'Panduan Memilih Vendor', 'Studi Kasus', 'FAQ Layanan'],
                    'portfolio' => ['Case Study', 'Before After', 'Project Client', 'Testimoni'],
                ],
                'starter_pages' => ['Paket layanan', 'Case study', 'FAQ biaya', 'Form konsultasi', 'Artikel problem-solution'],
                'blocks' => ['Benefit layanan', 'Proses kerja', 'Case study', 'Testimoni', 'FAQ harga', 'Form inquiry'],
                'sprint' => ['Tentukan layanan prioritas', 'Buat 2 case study', 'Tulis FAQ biaya dan proses', 'Pasang form konsultasi', 'Follow-up lead via Action Center'],
            ],
            'personal_branding' => [
                'label' => 'Personal Branding + Produk',
                'badge' => 'Authority Builder',
                'mode' => 'personal_brand',
                'layout' => 'multi_page',
                'schema' => 'Person',
                'headline' => 'Bangun authority personal sambil tetap punya jalur produk, program, kelas, jasa, dan konsultasi.',
                'fit' => 'Cocok untuk mentor, trainer, kreator, freelancer, speaker, dan expert.',
                'primary_cta' => 'Mulai Konsultasi',
                'labels' => ['catalog' => 'Produk & Program', 'product' => 'Produk', 'service' => 'Program', 'portfolio' => 'Karya', 'checkout' => 'Daftar / Beli', 'lead' => 'Audiens', 'article' => 'Insight', 'primary_cta' => 'Mulai Konsultasi'],
                'categories' => [
                    'catalog' => ['Kelas Online', 'Mentoring', 'E-book', 'Template', 'Konsultasi'],
                    'article' => ['Insight Personal', 'Panduan Praktis', 'Case Study', 'Opini Industri', 'FAQ Program'],
                    'portfolio' => ['Karya', 'Media/Publikasi', 'Testimoni Peserta', 'Client Result'],
                ],
                'starter_pages' => ['Profil personal', 'Program/kelas', 'Artikel authority', 'Testimoni', 'Lead magnet'],
                'blocks' => ['Hero personal', 'Authority proof', 'Program utama', 'Testimoni', 'FAQ', 'CTA konsultasi'],
                'sprint' => ['Rapikan positioning personal', 'Buat halaman program utama', 'Tulis 3 artikel authority', 'Tambah lead magnet/form', 'Pantau leads dan top content'],
            ],
            'portfolio_showcase' => [
                'label' => 'Portfolio / Showcase',
                'badge' => 'Lead Project',
                'mode' => 'portfolio',
                'layout' => 'one_page',
                'schema' => 'CreativeWork',
                'headline' => 'Portfolio tidak berhenti sebagai galeri, tetapi diarahkan ke inquiry, quotation, dan project baru.',
                'fit' => 'Cocok untuk designer, developer, fotografer, videografer, studio kreatif, dan freelancer.',
                'primary_cta' => 'Bahas Project',
                'labels' => ['catalog' => 'Showcase', 'product' => 'Item', 'service' => 'Layanan', 'portfolio' => 'Portfolio', 'checkout' => 'Minta Penawaran', 'lead' => 'Inquiry', 'article' => 'Cerita Karya', 'primary_cta' => 'Bahas Project'],
                'categories' => [
                    'catalog' => ['Showcase Utama', 'Paket Project', 'Konsultasi'],
                    'article' => ['Cerita Project', 'Proses Kerja', 'Tips Client', 'Behind The Scene', 'FAQ Project'],
                    'portfolio' => ['Website / Aplikasi', 'Desain / Kreatif', 'Foto / Video', 'Campaign', 'Case Study'],
                ],
                'starter_pages' => ['One-page portfolio', 'Daftar karya', 'Paket layanan', 'Case study', 'Form project'],
                'blocks' => ['Hero karya', 'Grid portfolio', 'Process', 'Testimoni', 'FAQ project', 'CTA quotation'],
                'sprint' => ['Pilih 6 karya terbaik', 'Tulis 2 case study', 'Tambahkan proses kerja dan FAQ', 'Pasang CTA project', 'Pantau showcase dengan Content Performance'],
            ],
            'digital_course' => [
                'label' => 'Produk Digital / Course',
                'badge' => 'Lead Magnet → Checkout',
                'mode' => 'digital_course',
                'layout' => 'multi_page',
                'schema' => 'Course',
                'headline' => 'Hubungkan konten edukasi ke lead magnet, e-book, template, kelas, membership, dan checkout.',
                'fit' => 'Cocok untuk kursus online, e-book, template, digital download, membership, dan bundle.',
                'primary_cta' => 'Akses Sekarang',
                'labels' => ['catalog' => 'Kelas & Produk Digital', 'product' => 'Produk Digital', 'service' => 'Program', 'portfolio' => 'Hasil Peserta', 'checkout' => 'Daftar Sekarang', 'lead' => 'Peserta', 'article' => 'Materi Gratis', 'primary_cta' => 'Akses Sekarang'],
                'categories' => [
                    'catalog' => ['E-book', 'Template', 'Kelas Online', 'Bundle', 'Membership', 'Promo Launching'],
                    'article' => ['Tutorial', 'Checklist', 'Comparison', 'Lesson Preview', 'FAQ Kelas'],
                    'portfolio' => ['Hasil Peserta', 'Review Produk', 'Studi Kasus', 'Before After'],
                ],
                'starter_pages' => ['Halaman produk digital', 'Lead magnet', 'Checkout', 'FAQ akses', 'Artikel tutorial'],
                'blocks' => ['Preview materi', 'Bonus', 'Testimoni', 'FAQ akses', 'Guarantee/trust', 'Checkout CTA'],
                'sprint' => ['Siapkan produk/kelas utama', 'Buat lead magnet', 'Tulis artikel tutorial', 'Pasang checkout dan follow-up', 'Pantau funnel lead ke order'],
            ],
        ];
    }
}

if (!function_exists('starter_wizard_default_state')) {
    function starter_wizard_default_state(): array
    {
        return [
            'selected_preset' => 'hybrid_umkm',
            'completed_steps' => [],
            'applied_presets' => [],
            'setup_mode' => 'preset_full',
            'custom_mode_enabled' => false,
            'starter_content_policy' => 'editable',
            'updated_at' => '',
        ];
    }
}

if (!function_exists('starter_wizard_state')) {
    function starter_wizard_state(): array
    {
        $defaults = starter_wizard_default_state();
        $file = starter_wizard_storage_file();
        if (!is_file($file)) {
            return $defaults;
        }
        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            return $defaults;
        }
        $decoded['completed_steps'] = is_array($decoded['completed_steps'] ?? null) ? array_values(array_unique(array_map('strval', $decoded['completed_steps']))) : [];
        $decoded['applied_presets'] = is_array($decoded['applied_presets'] ?? null) ? $decoded['applied_presets'] : [];
        $decoded['setup_mode'] = in_array((string)($decoded['setup_mode'] ?? 'preset_full'), ['preset_full', 'preset_structure', 'scratch'], true) ? (string)$decoded['setup_mode'] : 'preset_full';
        $decoded['custom_mode_enabled'] = !empty($decoded['custom_mode_enabled']);
        $decoded['starter_content_policy'] = 'editable';
        return array_merge($defaults, $decoded);
    }
}

if (!function_exists('starter_wizard_save_state')) {
    function starter_wizard_save_state(array $state): void
    {
        $state = array_merge(starter_wizard_default_state(), $state);
        $state['updated_at'] = date('c');
        $dir = dirname(starter_wizard_storage_file());
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents(starter_wizard_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Starter Wizard gagal menyimpan state. Pastikan folder storage dapat ditulis.');
        }
    }
}

if (!function_exists('starter_wizard_preset')) {
    function starter_wizard_preset(string $key): array
    {
        $presets = starter_wizard_presets();
        return $presets[$key] ?? $presets['hybrid_umkm'];
    }
}

if (!function_exists('starter_wizard_category_rows')) {
    function starter_wizard_category_rows(array $labels): array
    {
        $rows = [];
        foreach ($labels as $label) {
            $label = starter_wizard_clean($label, 80);
            if ($label === '') {
                continue;
            }
            $rows[] = ['label' => $label, 'description' => 'Kategori starter dari Website Starter Wizard.', 'enabled' => true];
        }
        return $rows;
    }
}

if (!function_exists('starter_wizard_merge_category_rows')) {
    function starter_wizard_merge_category_rows(array $presetRows, array $currentRows): array
    {
        $merged = [];
        $seen = [];
        foreach (array_merge($presetRows, $currentRows) as $row) {
            if (is_string($row)) {
                $row = ['label' => $row, 'description' => '', 'enabled' => true];
            }
            if (!is_array($row)) {
                continue;
            }
            $label = starter_wizard_clean($row['label'] ?? '', 80);
            if ($label === '') {
                continue;
            }
            $slug = function_exists('slugify') ? slugify($label) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $label));
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;
            $merged[] = [
                'label' => $label,
                'slug' => $slug,
                'description' => starter_wizard_clean($row['description'] ?? 'Kategori starter dari Website Starter Wizard.', 180),
                'enabled' => !array_key_exists('enabled', $row) || (bool)$row['enabled'],
            ];
        }
        return $merged;
    }
}


if (!function_exists('starter_wizard_empty_categories')) {
    function starter_wizard_empty_categories(): array
    {
        return ['catalog' => [], 'article' => [], 'portfolio' => []];
    }
}

if (!function_exists('starter_wizard_scratch_business_settings')) {
    function starter_wizard_scratch_business_settings(string $presetKey = 'hybrid_umkm'): array
    {
        $definitions = function_exists('business_mode_definitions') ? business_mode_definitions() : [];
        $hybrid = (array)($definitions['hybrid'] ?? []);
        $settings = function_exists('business_default_settings') ? business_default_settings() : [];
        $settings['business_mode'] = 'hybrid';
        $settings['layout_mode'] = 'multi_page';
        $settings['schema_profile'] = (string)($hybrid['schema'] ?? 'LocalBusiness');
        $settings['recommended_homepage'] = (string)($hybrid['recommended_homepage'] ?? 'catalog');
        $settings['labels'] = (array)($hybrid['labels'] ?? [
            'catalog' => 'Katalog',
            'product' => 'Produk/Jasa',
            'service' => 'Layanan',
            'portfolio' => 'Portfolio',
            'checkout' => 'Checkout',
            'lead' => 'Lead',
            'article' => 'Artikel',
            'primary_cta' => 'Konsultasi / Pesan',
        ]);
        $settings['visibility'] = (array)($hybrid['visibility'] ?? ['catalog' => true, 'services' => true, 'portfolio' => true, 'articles' => true, 'checkout' => true, 'lead_forms' => true]);
        $settings['categories'] = starter_wizard_empty_categories();
        $settings['allow_empty_categories'] = true;
        $settings['notes'] = starter_wizard_clean('Build From Scratch aktif. Preset ' . $presetKey . ' hanya menjadi referensi, tidak mengisi kategori atau konten contoh.', 300);
        return $settings;
    }
}


if (!function_exists('starter_wizard_navigation_for_preset')) {
    function starter_wizard_navigation_for_preset(string $key, string $setupMode = 'preset_full'): array
    {
        $preset = starter_wizard_preset($key);
        $labels = (array)($preset['labels'] ?? []);
        $catalogLabel = starter_wizard_clean($labels['catalog'] ?? 'Katalog', 60) ?: 'Katalog';
        $serviceLabel = starter_wizard_clean($labels['service'] ?? 'Layanan', 60) ?: 'Layanan';
        $portfolioLabel = starter_wizard_clean($labels['portfolio'] ?? 'Portfolio', 60) ?: 'Portfolio';
        $articleLabel = starter_wizard_clean($labels['article'] ?? 'Artikel', 60) ?: 'Artikel';
        $primaryCta = starter_wizard_clean($preset['primary_cta'] ?? 'Konsultasi / Pesan', 60) ?: 'Konsultasi / Pesan';
        $headline = starter_wizard_clean($preset['headline'] ?? 'Website growth untuk UMKM.', 180);
        $fit = starter_wizard_clean($preset['fit'] ?? 'Cocok untuk berbagai niche UMKM.', 220);
        $mode = (string)($preset['mode'] ?? 'hybrid');

        $showPortfolio = in_array($key, ['qurban_aqiqah', 'kuliner', 'jasa_layanan', 'personal_branding', 'portfolio_showcase', 'digital_course'], true)
            || in_array($mode, ['portfolio', 'personal_brand', 'services', 'digital_course'], true);

        if ($setupMode === 'preset_structure') {
            $headline = 'Struktur website ' . starter_wizard_clean($preset['label'] ?? 'UMKM', 80) . ' siap diisi dari dashboard.';
            $fit = 'Menu, label, kategori, dan CTA mengikuti niche. Konten detail tetap bebas diisi admin.';
        }

        $settings = function_exists('navigation_settings') ? navigation_settings() : (function_exists('navigation_default_settings') ? navigation_default_settings() : []);
        $settings = is_array($settings) ? $settings : [];
        $defaults = function_exists('navigation_default_settings') ? navigation_default_settings() : [];
        $header = is_array($settings['header'] ?? null) ? $settings['header'] : (array)($defaults['header'] ?? []);
        $footer = is_array($settings['footer'] ?? null) ? $settings['footer'] : (array)($defaults['footer'] ?? []);

        $header['topbar_text'] = $headline;
        $header['show_header_cta'] = true;
        $header['header_cta_label'] = $primaryCta;
        $header['header_cta_url'] = '/kontak';

        $footer['brand_description'] = $fit;

        return [
            'header' => $header,
            'menu_items' => [
                ['label' => 'Home', 'url' => '/', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => $catalogLabel, 'url' => '/katalog', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => $serviceLabel, 'url' => '/layanan', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => $portfolioLabel, 'url' => '/portfolio', 'enabled' => $showPortfolio, 'new_tab' => false, 'children' => []],
                ['label' => $articleLabel, 'url' => '/artikel', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Tentang Kami', 'url' => '/tentang-kami', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Kontak', 'url' => '/kontak', 'enabled' => true, 'new_tab' => false, 'children' => []],
            ],
            'footer' => $footer,
            'footer_columns' => [
                [
                    'title' => $catalogLabel,
                    'links' => [
                        ['label' => 'Semua ' . $catalogLabel, 'url' => '/katalog', 'enabled' => true, 'new_tab' => false],
                        ['label' => $serviceLabel, 'url' => '/layanan', 'enabled' => true, 'new_tab' => false],
                        ['label' => $portfolioLabel, 'url' => '/portfolio', 'enabled' => $showPortfolio, 'new_tab' => false],
                    ],
                ],
                [
                    'title' => 'Konten & Trust',
                    'links' => [
                        ['label' => $articleLabel, 'url' => '/artikel', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Tentang Kami', 'url' => '/tentang-kami', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Kontak', 'url' => '/kontak', 'enabled' => true, 'new_tab' => false],
                    ],
                ],
                [
                    'title' => 'Aksi Growth',
                    'links' => [
                        ['label' => $primaryCta, 'url' => '/kontak', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Checkout', 'url' => '/checkout', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Sitemap', 'url' => '/sitemap.xml', 'enabled' => true, 'new_tab' => false],
                    ],
                ],
            ],
            'bottom_links' => (array)($settings['bottom_links'] ?? ($defaults['bottom_links'] ?? [])),
        ];
    }
}

if (!function_exists('starter_wizard_navigation_for_scratch')) {
    function starter_wizard_navigation_for_scratch(): array
    {
        $settings = function_exists('navigation_settings') ? navigation_settings() : (function_exists('navigation_default_settings') ? navigation_default_settings() : []);
        $settings = is_array($settings) ? $settings : [];
        $defaults = function_exists('navigation_default_settings') ? navigation_default_settings() : [];
        $header = is_array($settings['header'] ?? null) ? $settings['header'] : (array)($defaults['header'] ?? []);
        $footer = is_array($settings['footer'] ?? null) ? $settings['footer'] : (array)($defaults['footer'] ?? []);

        $header['topbar_text'] = 'Bangun struktur website dari nol, lalu isi konten sesuai bisnis Anda.';
        $header['show_header_cta'] = true;
        $header['header_cta_label'] = 'Hubungi Kami';
        $header['header_cta_url'] = '/kontak';
        $footer['brand_description'] = 'Website starter kosong yang siap diisi dari dashboard admin tanpa perlu ngoding.';

        return [
            'header' => $header,
            'menu_items' => [
                ['label' => 'Home', 'url' => '/', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Katalog', 'url' => '/katalog', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Layanan', 'url' => '/layanan', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Portfolio', 'url' => '/portfolio', 'enabled' => false, 'new_tab' => false, 'children' => []],
                ['label' => 'Artikel', 'url' => '/artikel', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Tentang Kami', 'url' => '/tentang-kami', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Kontak', 'url' => '/kontak', 'enabled' => true, 'new_tab' => false, 'children' => []],
            ],
            'footer' => $footer,
            'footer_columns' => [
                [
                    'title' => 'Navigasi',
                    'links' => [
                        ['label' => 'Katalog', 'url' => '/katalog', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Layanan', 'url' => '/layanan', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Artikel', 'url' => '/artikel', 'enabled' => true, 'new_tab' => false],
                    ],
                ],
                [
                    'title' => 'Informasi',
                    'links' => [
                        ['label' => 'Tentang Kami', 'url' => '/tentang-kami', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Kontak', 'url' => '/kontak', 'enabled' => true, 'new_tab' => false],
                    ],
                ],
                [
                    'title' => 'Aksi',
                    'links' => [
                        ['label' => 'Hubungi Kami', 'url' => '/kontak', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Sitemap', 'url' => '/sitemap.xml', 'enabled' => true, 'new_tab' => false],
                    ],
                ],
            ],
            'bottom_links' => (array)($settings['bottom_links'] ?? ($defaults['bottom_links'] ?? [])),
        ];
    }
}

if (!function_exists('starter_wizard_apply_navigation_settings')) {
    function starter_wizard_apply_navigation_settings(string $key, string $setupMode = 'preset_full'): array
    {
        if (!function_exists('navigation_save_settings')) {
            return ['navigation_updated' => false, 'reason' => 'navigation_engine_missing'];
        }

        $settings = $setupMode === 'scratch'
            ? starter_wizard_navigation_for_scratch()
            : starter_wizard_navigation_for_preset($key, $setupMode);
        navigation_save_settings($settings);

        if (function_exists('activity_log_record')) {
            activity_log_record('apply_starter_navigation', 'navigation', null, 'Starter Wizard menerapkan menu/header/footer sesuai mode setup.', ['preset' => $key, 'mode' => $setupMode]);
        }

        return ['navigation_updated' => true, 'preset' => $key, 'mode' => $setupMode];
    }
}

if (!function_exists('starter_wizard_apply_preset')) {
    function starter_wizard_apply_preset(string $key, ?string $setupModeOverride = null): void
    {
        $presets = starter_wizard_presets();
        if (!isset($presets[$key])) {
            throw new InvalidArgumentException('Preset starter tidak ditemukan.');
        }
        $preset = $presets[$key];
        $state = starter_wizard_state();
        $setupMode = $setupModeOverride !== null ? (string)$setupModeOverride : (string)($state['setup_mode'] ?? 'preset_full');
        if (!in_array($setupMode, ['preset_full', 'preset_structure', 'scratch'], true)) {
            $setupMode = 'preset_full';
        }

        if ($setupMode === 'scratch') {
            if (function_exists('business_save_settings')) {
                business_save_settings(starter_wizard_scratch_business_settings($key));
            }
            $publicContentResult = function_exists('starter_wizard_apply_public_template_content')
                ? starter_wizard_apply_public_template_content($key, 'scratch')
                : ['pages_updated' => [], 'homepage_updated' => false, 'mode' => 'scratch'];
            $navigationResult = function_exists('starter_wizard_apply_navigation_settings')
                ? starter_wizard_apply_navigation_settings($key, 'scratch')
                : ['navigation_updated' => false, 'reason' => 'scratch_mode'];

            $state['selected_preset'] = $key;
            $state['setup_mode'] = 'scratch';
            $state['custom_mode_enabled'] = true;
            $state['starter_content_policy'] = 'editable';
            $state['last_public_content_result'] = $publicContentResult;
            $state['last_navigation_result'] = $navigationResult;
            $state['applied_presets'][] = [
                'preset' => $key,
                'label' => (string)($preset['label'] ?? $key),
                'mode' => 'scratch',
                'applied_at' => date('c'),
                'note' => 'Build From Scratch aktif. Preset hanya disimpan sebagai referensi; kategori dan konten publik dibuat kosong/placeholder editable tanpa menghapus produk, artikel, order, lead, atau data lama.',
                'public_pages' => (array)($publicContentResult['pages_updated'] ?? []),
                'navigation_updated' => !empty($navigationResult['navigation_updated']),
            ];
            starter_wizard_save_state($state);

            if (function_exists('activity_log_record')) {
                activity_log_record('apply_starter_wizard_scratch', 'system', null, 'Admin mengaktifkan Build From Scratch dari Starter Wizard.', ['preset' => $key, 'setup_mode' => 'scratch']);
            }
            return;
        }

        $current = function_exists('business_settings') ? business_settings() : [];
        $definitions = function_exists('business_mode_definitions') ? business_mode_definitions() : [];
        $mode = (string)($preset['mode'] ?? 'hybrid');
        if (!isset($definitions[$mode])) {
            $mode = 'hybrid';
        }
        $modeDefinition = $definitions[$mode] ?? [];

        $labels = array_merge((array)($modeDefinition['labels'] ?? []), (array)($preset['labels'] ?? []));
        $visibility = (array)($modeDefinition['visibility'] ?? ($current['visibility'] ?? []));
        $categories = is_array($current['categories'] ?? null) ? (array)$current['categories'] : (function_exists('business_default_category_bank') ? business_default_category_bank() : []);
        foreach (['catalog', 'article', 'portfolio'] as $domain) {
            $presetRows = starter_wizard_category_rows((array)($preset['categories'][$domain] ?? []));
            $currentRows = (array)($categories[$domain] ?? []);
            $categories[$domain] = starter_wizard_merge_category_rows($presetRows, $currentRows);
        }

        $settings = array_merge($current, [
            'business_mode' => $mode,
            'layout_mode' => in_array((string)($preset['layout'] ?? 'multi_page'), ['multi_page', 'one_page'], true) ? (string)$preset['layout'] : 'multi_page',
            'schema_profile' => starter_wizard_clean($preset['schema'] ?? ($modeDefinition['schema'] ?? 'LocalBusiness'), 50),
            'recommended_homepage' => starter_wizard_clean($modeDefinition['recommended_homepage'] ?? 'catalog', 50),
            'labels' => $labels,
            'visibility' => $visibility,
            'categories' => $categories,
            'allow_empty_categories' => false,
            'notes' => starter_wizard_clean('Preset starter terakhir: ' . (string)($preset['label'] ?? $key) . ' · Jalur: ' . $setupMode, 300),
        ]);

        if (function_exists('business_save_settings')) {
            business_save_settings($settings);
        }

        $publicContentResult = function_exists('starter_wizard_apply_public_template_content')
            ? starter_wizard_apply_public_template_content($key, $setupMode)
            : ['pages_updated' => [], 'homepage_updated' => false];
        $navigationResult = function_exists('starter_wizard_apply_navigation_settings')
            ? starter_wizard_apply_navigation_settings($key, $setupMode)
            : ['navigation_updated' => false];

        $state['selected_preset'] = $key;
        $state['setup_mode'] = $setupMode;
        $state['custom_mode_enabled'] = false;
        $state['starter_content_policy'] = 'editable';
        $state['last_public_content_result'] = $publicContentResult;
        $state['last_navigation_result'] = $navigationResult;
        $state['applied_presets'][] = [
            'preset' => $key,
            'label' => (string)($preset['label'] ?? $key),
            'mode' => $setupMode,
            'applied_at' => date('c'),
            'note' => $setupMode === 'preset_structure'
                ? 'Struktur preset diterapkan: mode, label, kategori, menu, header, footer aktif; konten publik disiapkan sebagai placeholder editable untuk diisi admin.'
                : 'Preset penuh diterapkan sebagai editable starter content. Mode, kategori, homepage, katalog, layanan, artikel, menu, header, dan footer diperbarui sesuai niche lalu tetap bisa diedit dari dashboard.',
            'public_pages' => (array)($publicContentResult['pages_updated'] ?? []),
            'navigation_updated' => !empty($navigationResult['navigation_updated']),
        ];
        starter_wizard_save_state($state);

        if (function_exists('activity_log_record')) {
            activity_log_record('apply_starter_wizard', 'system', null, 'Admin menerapkan Website Starter Wizard.', ['preset' => $key, 'mode' => $mode, 'setup_mode' => $setupMode, 'public_pages' => (array)($publicContentResult['pages_updated'] ?? []), 'navigation_updated' => !empty($navigationResult['navigation_updated'])]);
        }
    }
}




if (!function_exists('starter_wizard_setup_modes')) {
    function starter_wizard_setup_modes(): array
    {
        return [
            'preset_full' => [
                'label' => 'Gunakan Preset Niche',
                'badge' => 'Cepat Siap Pakai',
                'description' => 'Sistem menyiapkan mode bisnis, label, kategori, CTA, dan struktur awal sesuai niche. Semua hasilnya tetap bisa diedit dari admin.',
            ],
            'preset_structure' => [
                'label' => 'Struktur Preset, Konten Kosong',
                'badge' => 'Hybrid',
                'description' => 'Gunakan kategori, label, dan arah CTA dari preset, tetapi admin bebas mengisi konten produk/artikel/halaman sendiri.',
            ],
            'scratch' => [
                'label' => 'Bangun dari Nol',
                'badge' => 'Custom Mode',
                'description' => 'Tidak perlu memakai preset. Admin bisa eksplor semua menu, memilih kategori manual, lalu membuat produk, artikel, form, dan landing page dari awal.',
            ],
        ];
    }
}

if (!function_exists('starter_wizard_set_setup_mode')) {
    function starter_wizard_set_setup_mode(string $mode, string $presetKey = 'hybrid_umkm'): array
    {
        $modes = starter_wizard_setup_modes();
        if (!isset($modes[$mode])) {
            $mode = 'preset_full';
        }
        $presets = starter_wizard_presets();
        if (!isset($presets[$presetKey])) {
            $presetKey = 'hybrid_umkm';
        }
        $state = starter_wizard_state();
        $state['setup_mode'] = $mode;
        $state['custom_mode_enabled'] = $mode === 'scratch';
        $state['selected_preset'] = $presetKey;
        $state['starter_content_policy'] = 'editable';
        $state['applied_presets'][] = [
            'preset' => $presetKey,
            'label' => (string)($presets[$presetKey]['label'] ?? $presetKey),
            'mode' => $mode,
            'applied_at' => date('c'),
            'note' => 'Setup mode dipilih dari Starter Wizard. Semua konten tetap editable dari dashboard.',
        ];
        starter_wizard_save_state($state);
        if (function_exists('activity_log_record')) {
            activity_log_record('set_starter_setup_mode', 'system', null, 'Admin memilih jalur setup Starter Wizard.', ['preset' => $presetKey, 'mode' => $mode]);
        }
        return $state;
    }
}

if (!function_exists('starter_wizard_editable_inventory')) {
    function starter_wizard_editable_inventory(array $preset = []): array
    {
        $business = function_exists('business_settings') ? business_settings() : [];
        $catalogCount = function_exists('business_category_labels') ? count(business_category_labels('catalog', true)) : count((array)($preset['categories']['catalog'] ?? []));
        $articleCategoryCount = function_exists('business_category_labels') ? count(business_category_labels('article', true)) : count((array)($preset['categories']['article'] ?? []));
        $portfolioCategoryCount = function_exists('business_category_labels') ? count(business_category_labels('portfolio', true)) : count((array)($preset['categories']['portfolio'] ?? []));
        $products = function_exists('all_products') ? all_products() : [];
        $articles = function_exists('all_articles') ? all_articles() : [];
        $landingPages = function_exists('landing_page_all') ? landing_page_all(false) : [];
        $forms = function_exists('custom_form_read_forms') ? custom_form_read_forms() : [];
        $templatePages = function_exists('template_content_inventory') ? template_content_inventory() : [];
        $homepage = function_exists('homepage_settings') ? homepage_settings() : [];

        return [
            ['area' => 'Brand & Warna', 'status' => 'Editable', 'count' => 1, 'note' => 'Logo, warna biru profesional, kontak, sosial, login branding, dan efek ringan.', 'href' => 'admin/brand'],
            ['area' => 'Mode, Label & Kategori Bisnis', 'status' => 'Editable', 'count' => $catalogCount + $articleCategoryCount + $portfolioCategoryCount, 'note' => 'Kategori produk/jasa, artikel, portfolio, schema, one-page/multi-page, dan label bisnis.', 'href' => 'admin/business'],
            ['area' => 'Beranda Template', 'status' => 'Editable', 'count' => count((array)($homepage['sections'] ?? [])), 'note' => 'Hero, CTA, section beranda, mode homepage, dan sumber landing page.', 'href' => 'admin/homepage'],
            ['area' => 'Halaman Bawaan Publik', 'status' => 'Editable', 'count' => count($templatePages), 'note' => 'Tentang Kami, Kontak, Privacy Policy, Terms bisa diedit dari Konten Template.', 'href' => 'admin/template-content'],
            ['area' => 'Menu & Footer', 'status' => 'Editable', 'count' => 1, 'note' => 'Navigasi global, header, footer, CTA menu, link halaman, plus sinkron preset niche.', 'href' => 'admin/navigation'],
            ['area' => 'Produk / Jasa / Katalog', 'status' => 'Editable', 'count' => count($products), 'note' => 'Produk awal otomatis disiapkan sebagai konten tersimpan agar bisa diedit, disembunyikan, atau dihapus.', 'href' => 'admin/produk'],
            ['area' => 'Artikel SEO', 'status' => 'Editable', 'count' => count($articles), 'note' => 'Artikel awal otomatis disiapkan sebagai konten tersimpan agar bisa diedit, disembunyikan, atau dihapus.', 'href' => 'admin/artikel'],
            ['area' => 'Landing Page Builder', 'status' => 'Editable', 'count' => count($landingPages), 'note' => 'Sales page, lead magnet, page campaign, revision, analytics, dan motion ringan.', 'href' => 'admin/landing-pages'],
            ['area' => 'Form Builder', 'status' => 'Editable', 'count' => count($forms), 'note' => 'Field form, lead magnet, checkout form, dan form custom.', 'href' => 'admin/forms'],
            ['area' => 'SEO & Growth Engine', 'status' => 'Otomatis membaca data editable', 'count' => 1, 'note' => 'Universal SEO, planner, board, checklist, draft publisher, link health, performance, funnel.', 'href' => 'admin/growth-snapshot'],
        ];
    }
}

if (!function_exists('starter_wizard_convert_default_content')) {
    function starter_wizard_convert_default_content(): array
    {
        $result = [
            'products_created' => 0,
            'products_skipped' => 0,
            'articles_created' => 0,
            'articles_skipped' => 0,
            'template_pages_ready' => 0,
            'homepage_ready' => false,
        ];
        if (function_exists('product_convert_seed_to_storage')) {
            $product = product_convert_seed_to_storage();
            $result['products_created'] = (int)($product['created'] ?? 0);
            $result['products_skipped'] = (int)($product['skipped'] ?? 0);
        }
        if (function_exists('article_convert_seed_to_storage')) {
            $article = article_convert_seed_to_storage();
            $result['articles_created'] = (int)($article['created'] ?? 0);
            $result['articles_skipped'] = (int)($article['skipped'] ?? 0);
        }
        if (function_exists('template_content_all') && function_exists('template_content_save_all')) {
            $pages = template_content_all();
            template_content_save_all($pages);
            $result['template_pages_ready'] = count($pages);
        }
        if (function_exists('homepage_settings') && function_exists('homepage_save_settings')) {
            homepage_save_settings(homepage_settings());
            $result['homepage_ready'] = true;
        }
        $state = starter_wizard_state();
        $state['starter_content_policy'] = 'editable';
        $state['default_content_converted_at'] = date('c');
        starter_wizard_save_state($state);
        if (function_exists('activity_log_record')) {
            activity_log_record('convert_default_template_content', 'system', null, 'Admin menyiapkan konten bawaan agar editable.', $result);
        }
        return $result;
    }
}

if (!function_exists('starter_wizard_homepage_mode_for_preset')) {
    function starter_wizard_homepage_mode_for_preset(array $preset): string
    {
        $mode = (string)($preset['mode'] ?? 'hybrid');
        $map = [
            'personal_brand' => 'personal_brand',
            'portfolio' => 'portfolio',
            'company_profile' => 'company_profile',
            'digital_course' => 'sales_page',
            'landing_page' => 'lead_generation',
            'services' => 'lead_generation',
            'umkm_shop' => 'catalog',
            'hybrid' => 'catalog',
        ];
        return $map[$mode] ?? 'catalog';
    }
}

if (!function_exists('starter_wizard_public_copy_for_preset')) {
    function starter_wizard_public_copy_for_preset(string $key, string $setupMode = 'preset_full'): array
    {
        $preset = starter_wizard_preset($key);
        $label = starter_wizard_clean($preset['label'] ?? 'Universal Business Growth', 90);
        $headline = starter_wizard_clean($preset['headline'] ?? 'Website starter untuk UMKM Growth.', 180);
        $fit = starter_wizard_clean($preset['fit'] ?? 'Cocok untuk berbagai niche UMKM.', 220);
        $cta = starter_wizard_clean($preset['primary_cta'] ?? 'Konsultasi / Pesan', 60);
        $labels = (array)($preset['labels'] ?? []);
        $catalogLabel = starter_wizard_clean($labels['catalog'] ?? 'Katalog', 60);
        $productLabel = starter_wizard_clean($labels['product'] ?? 'Produk/Jasa', 60);
        $serviceLabel = starter_wizard_clean($labels['service'] ?? 'Layanan', 60);
        $articleLabel = starter_wizard_clean($labels['article'] ?? 'Artikel', 60);
        $portfolioLabel = starter_wizard_clean($labels['portfolio'] ?? 'Portfolio', 60);
        $catalogCats = array_values(array_filter(array_map(static fn($v): string => starter_wizard_clean($v, 70), (array)($preset['categories']['catalog'] ?? []))));
        $articleCats = array_values(array_filter(array_map(static fn($v): string => starter_wizard_clean($v, 70), (array)($preset['categories']['article'] ?? []))));
        $portfolioCats = array_values(array_filter(array_map(static fn($v): string => starter_wizard_clean($v, 70), (array)($preset['categories']['portfolio'] ?? []))));
        $catalogText = $catalogCats ? implode(', ', array_slice($catalogCats, 0, 5)) : 'kategori utama bisnis';
        $articleText = $articleCats ? implode(', ', array_slice($articleCats, 0, 5)) : 'artikel edukasi dan FAQ';
        $portfolioText = $portfolioCats ? implode(', ', array_slice($portfolioCats, 0, 4)) : 'bukti hasil dan portfolio';

        $structureOnly = $setupMode === 'preset_structure';
        $scratch = $setupMode === 'scratch';

        if ($scratch) {
            $headline = 'Isi judul utama website Anda';
            $fit = 'Custom Mode aktif. Konten contoh dan kategori preset tidak diterapkan; admin bebas menyusun brand, kategori, halaman, produk, artikel, form, CTA, dan landing page dari nol.';
            $catalogText = 'buat kategori manual dari Mode & Kategori Bisnis';
            $articleText = 'buat kategori artikel sesuai target SEO';
            $portfolioText = 'isi portfolio, testimoni, atau case study jika dibutuhkan';
            $cta = 'Isi CTA Utama';
        } elseif ($structureOnly) {
            $headline = 'Isi judul utama untuk ' . $label;
            $fit = 'Struktur niche sudah aktif: label, kategori, CTA, menu, header, dan footer mengikuti preset. Konten publik dibuat sebagai placeholder agar admin mengisinya sendiri.';
        }

        $moneyPageName = $catalogLabel ?: 'Katalog';
        $homeTitle = $scratch ? 'Isi Judul Utama Website' : ($structureOnly ? 'Isi Judul Utama ' . $label : $headline);
        $homeDesc = $scratch ? 'Tulis deskripsi singkat bisnis Anda dari dashboard admin.' : ($structureOnly ? 'Tulis deskripsi utama bisnis ini dari dashboard. Struktur sudah mengikuti niche, tetapi konten detail sengaja belum diisi.' : $fit . ' Semua section starter bisa diedit, ditambah, disembunyikan, atau di-reset dari dashboard.');

        $supportPages = (array)($preset['starter_pages'] ?? []);
        $blocks = (array)($preset['blocks'] ?? []);
        $supportText = $supportPages ? implode(', ', array_slice(array_map('strval', $supportPages), 0, 5)) : 'halaman prioritas, konten pendukung, dan CTA';
        $blockText = $blocks ? implode(', ', array_slice(array_map('strval', $blocks), 0, 5)) : 'hero, benefit, trust, FAQ, form, dan CTA';
        $emptyTitle = $scratch ? 'Isi judul section ini' : 'Isi konten section ini';
        $emptyDescription = $scratch ? 'Tulis konten sesuai kebutuhan bisnis Anda.' : 'Struktur sudah disiapkan. Silakan isi copy, gambar, proof, CTA, dan detail penawaran dari dashboard.';
        $catalogGuide = ($structureOnly || $scratch) ? '<p>Isi panduan memilih, cara order, harga, proses, dan FAQ sesuai bisnis Anda.</p>' : '<p>Jelaskan kriteria memilih, benefit utama, cara order, estimasi proses, pembayaran, dan FAQ singkat sesuai niche ' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '.</p>';
        $serviceGuide = ($structureOnly || $scratch) ? '<p>Isi masalah pelanggan, solusi, proses kerja, estimasi hasil, harga/paket, dan cara mulai konsultasi.</p>' : '<p>Jelaskan masalah pelanggan, solusi yang ditawarkan, proses kerja, estimasi hasil, dan cara mulai konsultasi.</p>';

        return [
            'home_mode' => starter_wizard_homepage_mode_for_preset($preset),
            'home' => [
                'meta_title' => $label . ' - Website Growth Editable',
                'meta_description' => $homeDesc,
                'hero_title' => $homeTitle,
                'hero_description' => $homeDesc,
                'sections' => [
                    'hero' => ['eyebrow' => $structureOnly ? 'Struktur niche siap diedit' : ($scratch ? 'Build from scratch mode' : $label), 'title' => $homeTitle, 'description' => $homeDesc, 'button_label' => $cta, 'button_url' => '/kontak', 'secondary_button_label' => 'Lihat ' . $moneyPageName, 'secondary_button_url' => '/katalog'],
                    'profile_intro' => ['eyebrow' => 'Arah website', 'title' => ($structureOnly || $scratch) ? $emptyTitle : 'Website Ini Disiapkan untuk ' . $label, 'description' => ($structureOnly || $scratch) ? $emptyDescription : $fit, 'body_html' => ($structureOnly || $scratch) ? '<p>Isi profil, value proposition, dan alasan pelanggan harus percaya pada bisnis ini.</p>' : '<p>Semua konten starter bisa diedit dari dashboard admin: section home, katalog, layanan, artikel, CTA, form, dan SEO.</p>'],
                    'featured_catalog' => ['eyebrow' => $catalogLabel, 'title' => ($structureOnly || $scratch) ? 'Isi ' . $moneyPageName . ' Utama' : $moneyPageName . ' Prioritas', 'description' => ($structureOnly || $scratch) ? 'Tambahkan produk, jasa, program, atau paket utama dari dashboard.' : 'Gunakan bagian ini untuk menampilkan ' . strtolower($productLabel) . ' utama seperti ' . $catalogText . '.', 'button_label' => ($structureOnly || $scratch) ? 'Isi Tombol CTA' : 'Buka ' . $moneyPageName, 'button_url' => '/katalog'],
                    'business_fit' => ['eyebrow' => 'Growth path', 'title' => ($structureOnly || $scratch) ? 'Isi Alur Growth Bisnis' : 'Dari Website ke Lead dan Penjualan', 'description' => ($structureOnly || $scratch) ? 'Jelaskan bagaimana pengunjung diarahkan ke lead, WhatsApp/form, order, pembayaran, dan follow-up.' : 'Arahkan pengunjung dari SEO ke ' . strtolower($moneyPageName) . ', WhatsApp/form, order, pembayaran, lalu pantau insight growth.', 'body_html' => ($structureOnly || $scratch) ? '<ul><li>Isi halaman prioritas.</li><li>Isi blok konversi.</li><li>Isi bukti/trust.</li></ul>' : '<ul><li>Halaman prioritas: ' . htmlspecialchars($supportText, ENT_QUOTES, 'UTF-8') . '.</li><li>Blok konversi: ' . htmlspecialchars($blockText, ENT_QUOTES, 'UTF-8') . '.</li><li>Bukti pendukung: ' . htmlspecialchars($portfolioText, ENT_QUOTES, 'UTF-8') . '.</li></ul>'],
                    'latest_articles' => ['eyebrow' => $articleLabel . ' SEO', 'title' => ($structureOnly || $scratch) ? 'Isi Topik ' . $articleLabel : $articleLabel . ' Pendukung ' . $label, 'description' => ($structureOnly || $scratch) ? 'Tambahkan topik artikel pendukung dari SEO Content Planner.' : 'Bangun authority dengan topik: ' . $articleText . '.'],
                    'lead_form' => ['eyebrow' => 'Lead', 'title' => ($structureOnly || $scratch) ? 'Isi Judul Form Lead' : 'Butuh Bantuan atau Konsultasi?', 'description' => ($structureOnly || $scratch) ? 'Tulis ajakan agar pengunjung mengisi form atau menghubungi WhatsApp.' : 'Isi form singkat untuk bertanya tentang ' . strtolower($productLabel) . ', ' . strtolower($serviceLabel) . ', booking, quotation, atau kebutuhan khusus.', 'button_label' => $cta],
                ],
            ],
            'catalog' => [
                'meta_title' => $moneyPageName . ' - ' . $label,
                'meta_description' => 'Halaman ' . strtolower($moneyPageName) . ' untuk ' . $label . ' yang bisa diedit dari dashboard admin.',
                'hero_title' => $moneyPageName . ' untuk ' . $label,
                'hero_description' => 'Temukan ' . strtolower($productLabel) . ', paket, penawaran, atau solusi sesuai kebutuhan. Kategori starter: ' . $catalogText . '.',
                'sections' => [
                    'hero' => ['eyebrow' => $catalogLabel, 'title' => $moneyPageName . ' untuk ' . $label, 'description' => 'Gunakan halaman ini untuk menampilkan ' . strtolower($productLabel) . ', paket, harga, benefit, stok/status, dan CTA ' . strtolower($cta) . '.'],
                    'filter_panel' => ['eyebrow' => 'Filter', 'title' => 'Filter ' . $moneyPageName, 'description' => 'Bantu pengunjung mencari berdasarkan kategori niche: ' . $catalogText . '.'],
                    'result_intro' => ['eyebrow' => 'Hasil', 'title' => $productLabel . ' Ditemukan', 'description' => 'Bandingkan kategori, benefit, harga, status, dan CTA sebelum lanjut bertanya atau checkout.'],
                    'guide_left' => ['eyebrow' => 'Panduan', 'title' => ($structureOnly || $scratch) ? 'Isi Panduan Memilih' : 'Cara Memilih ' . $productLabel, 'body_html' => $catalogGuide],
                    'guide_right' => ['eyebrow' => 'Kategori', 'title' => 'Kategori Populer', 'description' => 'Gunakan kategori ini sebagai pintu masuk cepat.', 'body_html' => '<p>' . htmlspecialchars($catalogText, ENT_QUOTES, 'UTF-8') . '</p>'],
                    'lead_form' => ['eyebrow' => 'Inquiry', 'title' => 'Butuh Dibantu Memilih ' . $productLabel . '?', 'description' => 'Kirim kebutuhan Anda agar admin bisa memberi rekomendasi paling cocok.', 'button_label' => $cta],
                ],
            ],
            'services' => [
                'meta_title' => $serviceLabel . ' - ' . $label,
                'meta_description' => 'Halaman ' . strtolower($serviceLabel) . ' untuk ' . $label . ' yang bisa diedit dari dashboard admin.',
                'hero_title' => $serviceLabel . ' dan Solusi untuk ' . $label,
                'hero_description' => 'Pilih layanan, konsultasi, booking, paket, atau penawaran custom yang paling sesuai dengan kebutuhan Anda.',
                'sections' => [
                    'hero' => ['eyebrow' => $serviceLabel, 'title' => $serviceLabel . ' untuk ' . $label, 'description' => 'Gunakan halaman ini untuk menjelaskan layanan, proses kerja, benefit, harga/paket, bukti hasil, dan CTA konsultasi.'],
                    'filter_panel' => ['eyebrow' => 'Filter', 'title' => 'Filter ' . $serviceLabel, 'description' => 'Bantu pengunjung memilih layanan berdasarkan kategori, kebutuhan, area, jadwal, atau paket.'],
                    'result_intro' => ['eyebrow' => 'Hasil', 'title' => $serviceLabel . ' Ditemukan', 'description' => 'Bandingkan layanan, proses, benefit, dan CTA sebelum lanjut konsultasi.'],
                    'guide_left' => ['eyebrow' => 'Panduan', 'title' => ($structureOnly || $scratch) ? 'Isi Panduan Layanan' : 'Cara Memilih ' . $serviceLabel, 'body_html' => $serviceGuide],
                    'guide_right' => ['eyebrow' => 'Bukti', 'title' => 'Bukti dan Trust', 'description' => 'Tambahkan testimoni, portfolio, FAQ, sertifikasi, atau dokumentasi.', 'body_html' => '<p>' . htmlspecialchars($portfolioText, ENT_QUOTES, 'UTF-8') . '</p>'],
                    'lead_form' => ['eyebrow' => 'Inquiry', 'title' => 'Ingin Konsultasi ' . $serviceLabel . '?', 'description' => 'Kirim kebutuhan singkat agar admin bisa menyiapkan rekomendasi atau penawaran.', 'button_label' => $cta],
                ],
            ],
            'articles' => [
                'meta_title' => $articleLabel . ' - ' . $label,
                'meta_description' => 'Kumpulan ' . strtolower($articleLabel) . ' pendukung SEO untuk niche ' . $label . '.',
                'hero_title' => $articleLabel . ' untuk ' . $label,
                'hero_description' => 'Edukasi pelanggan dengan topik seperti ' . $articleText . ', lalu arahkan ke ' . strtolower($moneyPageName) . ' dan CTA utama.',
                'sections' => [
                    'hero' => ['eyebrow' => 'Konten SEO', 'title' => $articleLabel . ' untuk ' . $label, 'description' => 'Gunakan artikel untuk menjawab pertanyaan pelanggan, membangun trust, dan mengarahkan traffic organik ke money page.'],
                    'categories' => ['eyebrow' => 'Kategori', 'title' => 'Kategori ' . $articleLabel, 'description' => 'Kategori starter: ' . $articleText . '. Semua kategori bisa diedit dari Mode & Kategori Bisnis.'],
                    'article_list' => ['eyebrow' => $articleLabel, 'title' => $articleLabel . ' Terbaru', 'description' => 'Buat konten pendukung dari SEO Content Planner agar traffic organik nyambung ke lead dan conversion.'],
                ],
            ],
        ];
    }
}

if (!function_exists('starter_wizard_apply_public_template_content')) {
    function starter_wizard_apply_public_template_content(string $key, string $setupMode = 'preset_full'): array
    {
        $result = ['pages_updated' => [], 'homepage_updated' => false, 'mode' => $setupMode];
        $copy = starter_wizard_public_copy_for_preset($key, $setupMode);

        if (function_exists('template_content_all') && function_exists('template_content_save_all')) {
            $pages = template_content_all();
            foreach (['home', 'catalog', 'services', 'articles'] as $pageKey) {
                if (!isset($pages[$pageKey]) || !is_array($pages[$pageKey]) || !isset($copy[$pageKey]) || !is_array($copy[$pageKey])) {
                    continue;
                }
                foreach (['meta_title', 'meta_description', 'hero_title', 'hero_description', 'primary_title', 'primary_html', 'secondary_title', 'secondary_html'] as $field) {
                    if (array_key_exists($field, $copy[$pageKey])) {
                        $pages[$pageKey][$field] = (string)$copy[$pageKey][$field];
                    }
                }
                $pages[$pageKey]['niche_preset_key'] = $key;
                $pages[$pageKey]['niche_preset_mode'] = $setupMode;
                $pages[$pageKey]['updated_at'] = date('c');
                $existingSections = (array)($pages[$pageKey]['sections'] ?? []);
                foreach ((array)($copy[$pageKey]['sections'] ?? []) as $sectionId => $sectionValues) {
                    if (!isset($existingSections[$sectionId]) || !is_array($existingSections[$sectionId])) {
                        $existingSections[$sectionId] = ['id' => (string)$sectionId, 'label' => ucwords(str_replace('_', ' ', (string)$sectionId)), 'status' => 'visible', 'locked' => true];
                    }
                    foreach ($sectionValues as $field => $value) {
                        $existingSections[$sectionId][$field] = (string)$value;
                    }
                    $existingSections[$sectionId]['status'] = (string)($existingSections[$sectionId]['status'] ?? 'visible') ?: 'visible';
                    $existingSections[$sectionId]['updated_at'] = date('c');
                }
                $pages[$pageKey]['sections'] = $existingSections;
                $result['pages_updated'][] = $pageKey;
            }
            template_content_save_all($pages);
        }

        if (function_exists('homepage_settings') && function_exists('homepage_save_settings') && isset($copy['home']) && is_array($copy['home'])) {
            $settings = homepage_settings();
            $settings['source'] = 'template';
            $settings['mode'] = (string)($copy['home_mode'] ?? 'catalog');
            $settings['hero'] = [
                'eyebrow' => (string)($copy['home']['sections']['hero']['eyebrow'] ?? 'Website siap growth'),
                'title' => (string)($copy['home']['sections']['hero']['title'] ?? $copy['home']['hero_title'] ?? 'Website Growth'),
                'description' => (string)($copy['home']['sections']['hero']['description'] ?? $copy['home']['hero_description'] ?? ''),
                'primary_label' => (string)($copy['home']['sections']['hero']['button_label'] ?? 'Konsultasi'),
                'primary_url' => (string)($copy['home']['sections']['hero']['button_url'] ?? '/kontak'),
                'secondary_label' => (string)($copy['home']['sections']['hero']['secondary_button_label'] ?? 'Lihat Katalog'),
                'secondary_url' => (string)($copy['home']['sections']['hero']['secondary_button_url'] ?? '/katalog'),
            ];
            foreach (['profile_intro', 'featured_catalog', 'business_fit', 'latest_articles'] as $sectionId) {
                if (!isset($copy['home']['sections'][$sectionId]) || !is_array($copy['home']['sections'][$sectionId])) {
                    continue;
                }
                $settings[$sectionId] = array_merge((array)($settings[$sectionId] ?? []), [
                    'eyebrow' => (string)($copy['home']['sections'][$sectionId]['eyebrow'] ?? ''),
                    'title' => (string)($copy['home']['sections'][$sectionId]['title'] ?? ''),
                    'description' => (string)($copy['home']['sections'][$sectionId]['description'] ?? ''),
                ]);
                if ($sectionId === 'featured_catalog') {
                    $settings[$sectionId]['button_label'] = (string)($copy['home']['sections'][$sectionId]['button_label'] ?? 'Buka Katalog');
                    $settings[$sectionId]['button_url'] = (string)($copy['home']['sections'][$sectionId]['button_url'] ?? '/katalog');
                }
            }
            if (isset($copy['home']['sections']['lead_form'])) {
                $settings['lead_form'] = array_merge((array)($settings['lead_form'] ?? []), [
                    'title' => (string)($copy['home']['sections']['lead_form']['title'] ?? 'Butuh bantuan?'),
                    'text' => (string)($copy['home']['sections']['lead_form']['description'] ?? ''),
                    'button' => (string)($copy['home']['sections']['lead_form']['button_label'] ?? 'Kirim Permintaan'),
                ]);
            }
            $settings['updated_at'] = date('c');
            homepage_save_settings($settings);
            $result['homepage_updated'] = true;
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('apply_public_preset_content', 'content', null, 'Starter Wizard menerapkan konten publik editable sesuai niche.', ['preset' => $key, 'mode' => $setupMode, 'pages' => $result['pages_updated']]);
        }

        return $result;
    }
}

if (!function_exists('starter_wizard_active_summary')) {
    function starter_wizard_active_summary(array $state, array $preset, array $currentBusiness = []): array
    {
        $setupModes = starter_wizard_setup_modes();
        $setupMode = (string)($state['setup_mode'] ?? 'preset_full');
        if (!isset($setupModes[$setupMode])) {
            $setupMode = 'preset_full';
        }
        $presetLabel = (string)($preset['label'] ?? 'Universal Business Growth');
        $setupLabel = (string)($setupModes[$setupMode]['label'] ?? $setupMode);
        $businessLabel = (string)($currentBusiness['label'] ?? 'Hybrid Growth Website');
        $policy = 'Semua hasil starter tetap editable dari dashboard admin.';

        $effect = 'Preset penuh mengisi mode, label, kategori, menu/header/footer, dan copy publik awal sesuai niche.';
        if ($setupMode === 'preset_structure') {
            $effect = 'Struktur niche aktif: mode, label, kategori, menu/header/footer diterapkan; konten publik dibuat kosong/placeholder untuk diisi admin.';
        } elseif ($setupMode === 'scratch') {
            $effect = 'Build from scratch aktif: preset hanya referensi; kategori dan konten publik dibuat kosong/placeholder tanpa menghapus data lama.';
        }

        return [
            ['label' => 'Niche Starter', 'value' => $presetLabel, 'note' => 'Menentukan arah bisnis, label, schema, kategori, CTA, dan saran sprint.'],
            ['label' => 'Setup Path', 'value' => $setupLabel, 'note' => $effect],
            ['label' => 'Mode Bisnis Aktif', 'value' => $businessLabel, 'note' => 'Dipakai frontend, SEO schema, menu, katalog, layanan, artikel, dan growth engine.'],
            ['label' => 'Policy Konten', 'value' => 'Editable Starter Content', 'note' => $policy],
        ];
    }
}


if (!function_exists('starter_wizard_steps')) {
    function starter_wizard_steps(array $preset): array
    {
        return [
            'brand' => ['label' => 'Isi Brand', 'body' => 'Lengkapi logo, warna, nama bisnis, tagline, dan login branding.', 'href' => 'admin/brand'],
            'mode' => ['label' => 'Terapkan Mode', 'body' => 'Pilih preset niche, struktur kosong, atau build from scratch. Semua hasil starter tetap editable.', 'href' => 'admin/starter-wizard'],
            'template_content' => ['label' => 'Edit Konten Template', 'body' => 'Sesuaikan Home, Katalog, Layanan, Artikel, Tentang, Kontak, Privacy Policy, Terms, dan konten bawaan dari dashboard.', 'href' => 'admin/template-content'],
            'navigation' => ['label' => 'Rapikan Menu & Footer', 'body' => 'Sembunyikan menu yang tidak dipakai, tambah menu baru, ubah label navigasi, CTA header, dan link footer.', 'href' => 'admin/navigation'],
            'catalog' => ['label' => 'Isi Katalog', 'body' => 'Tambahkan produk, jasa, paket, atau program utama sebagai money page.', 'href' => 'admin/produk'],
            'content' => ['label' => 'Buat Konten SEO', 'body' => 'Gunakan Content Planner untuk artikel pendukung dan internal link.', 'href' => 'admin/seo-content-planner'],
            'conversion' => ['label' => 'Aktifkan CTA', 'body' => 'Rapikan WhatsApp, form, checkout, trust block, dan follow-up.', 'href' => 'admin/funnel-action-center'],
            'report' => ['label' => 'Pantau Growth', 'body' => 'Baca Growth Snapshot, Content Performance, dan Conversion Opportunities.', 'href' => 'admin/growth-snapshot'],
        ];
    }
}

if (!function_exists('starter_wizard_readiness')) {
    function starter_wizard_readiness(array $state, array $preset): array
    {
        $steps = starter_wizard_steps($preset);
        $done = array_intersect(array_keys($steps), (array)($state['completed_steps'] ?? []));
        $business = function_exists('business_settings') ? business_settings() : [];
        $score = 18 + (count($done) * 10);
        if (!empty($business['business_mode'])) {
            $score += 14;
        }
        if (function_exists('business_category_labels')) {
            $score += min(18, count(business_category_labels('catalog', true)) + count(business_category_labels('article', true)) + count(business_category_labels('portfolio', true)));
        }
        $score = max(1, min(100, (int)$score));
        $label = $score >= 80 ? 'Siap Dipakai UMKM' : ($score >= 62 ? 'Fondasi Bagus' : ($score >= 42 ? 'Perlu Setup Awal' : 'Mulai dari Brand'));
        return ['score' => $score, 'label' => $label, 'done' => count($done), 'total' => count($steps)];
    }
}

if (!function_exists('starter_wizard_current_url')) {
    function starter_wizard_current_url(array $extra = []): string
    {
        $state = function_exists('starter_wizard_state') ? starter_wizard_state() : [];
        $activePreset = (string)($_GET['preset'] ?? ($state['selected_preset'] ?? 'hybrid_umkm'));
        $query = array_merge(['preset' => $activePreset], $extra);
        $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
        return function_exists('url') ? url('admin/starter-wizard' . ($query ? '?' . http_build_query($query) : '')) : 'admin/starter-wizard';
    }
}
