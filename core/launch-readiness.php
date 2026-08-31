<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('launch_readiness_item')) {
    function launch_readiness_item(string $key, string $label, string $description, bool $ok, string $action, string $href, int $weight = 10, string $group = 'Setup'): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'status' => $ok ? 'ok' : 'todo',
            'action' => $action,
            'href' => $href,
            'weight' => max(1, $weight),
            'group' => $group,
        ];
    }
}

if (!function_exists('launch_readiness_non_default_text')) {
    function launch_readiness_non_default_text(string $value, array $defaults): bool
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return false;
        }
        foreach ($defaults as $default) {
            if ($value === trim(strtolower((string)$default))) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('launch_readiness_enabled_nav_count')) {
    function launch_readiness_enabled_nav_count(): int
    {
        $nav = function_exists('navigation_settings') ? navigation_settings() : [];
        $items = (array)($nav['primary_menu'] ?? $nav['menus']['primary'] ?? $nav['items'] ?? []);
        $count = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string)($item['label'] ?? ''));
            $url = trim((string)($item['url'] ?? $item['href'] ?? ''));
            $enabled = !isset($item['enabled']) || !empty($item['enabled']);
            if ($enabled && $label !== '' && $url !== '') {
                $count++;
            }
        }
        return $count;
    }
}

if (!function_exists('launch_readiness_report')) {
    function launch_readiness_report(): array
    {
        $theme = function_exists('theme_settings') ? theme_settings() : [];
        $business = function_exists('business_settings') ? business_settings() : [];
        $templatePages = function_exists('template_content_all') ? template_content_all() : [];
        $starterState = function_exists('starter_wizard_state') ? starter_wizard_state() : [];
        $products = function_exists('all_products') ? all_products() : [];
        $articles = function_exists('all_articles') ? all_articles() : [];
        $forms = function_exists('custom_form_read_forms') ? custom_form_read_forms() : [];
        $seoSummary = function_exists('seo_quality_summary') ? seo_quality_summary('all') : [];
        $landingPages = function_exists('landing_page_all') ? landing_page_all() : [];
        $trustSummary = function_exists('trust_conversion_summary') ? trust_conversion_summary() : ['enabled_blocks' => 0];

        $businessName = (string)($theme['business_name'] ?? SITE_NAME);
        $logo = trim((string)($theme['logo'] ?? ''));
        $favicon = trim((string)($theme['favicon'] ?? ''));
        $waUrl = trim((string)($theme['primary_cta_url'] ?? $theme['whatsapp_url'] ?? ''));

        $home = (array)($templatePages['home'] ?? []);
        $homeTitle = (string)($home['sections']['hero']['title'] ?? $home['title'] ?? '');
        $homeEdited = launch_readiness_non_default_text($homeTitle, ['UMKM Commerce Template', 'UMKM Growth Web Template', 'Website Growth untuk Bisnis Anda', 'Bangun Website UMKM yang Siap SEO, Lead, dan Scale']);

        $activeProducts = array_values(array_filter($products, static fn($row): bool => is_array($row) && (($row['status'] ?? 'published') !== 'draft')));
        $activeArticles = array_values(array_filter($articles, static fn($row): bool => is_array($row) && (($row['status'] ?? 'published') !== 'draft')));
        $activeForms = array_values(array_filter($forms, static fn($row): bool => is_array($row) && (($row['status'] ?? 'active') === 'active')));
        $publishedLandingPages = array_values(array_filter($landingPages, static fn($row): bool => is_array($row) && (($row['status'] ?? 'draft') === 'published')));

        $adminPassword = (string)($_ENV['ADMIN_PASSWORD'] ?? '');
        $passwordOk = function_exists('admin_auth_password_needs_setup') ? !admin_auth_password_needs_setup() : (function_exists('admin_password_needs_setup') ? !admin_password_needs_setup($adminPassword) : $adminPassword !== '');
        $seoScore = (int)($seoSummary['score'] ?? $seoSummary['avg_score'] ?? 0);

        $items = [
            launch_readiness_item('brand', 'Brand sudah disiapkan', 'Nama bisnis, logo, dan favicon membantu website terlihat milik brand sendiri.', launch_readiness_non_default_text($businessName, ['UMKM Commerce Template']) && ($logo !== '' || $favicon !== ''), 'Lengkapi brand & warna', url('admin/brand'), 12, 'Identitas'),
            launch_readiness_item('business_mode', 'Mode bisnis sudah dipilih', 'Mode bisnis menentukan label, kategori, schema, dan arah konten.', trim((string)($business['business_mode'] ?? '')) !== '', 'Atur mode bisnis', url('admin/business'), 10, 'Identitas'),
            launch_readiness_item('starter_path', 'Starter path sudah jelas', 'Pilih preset penuh, struktur kosong, atau bangun dari nol agar alur setup tidak membingungkan.', trim((string)($starterState['setup_mode'] ?? '')) !== '', 'Buka Starter Wizard', url('admin/starter-wizard'), 10, 'Setup'),
            launch_readiness_item('navigation', 'Menu navigasi sudah dicek', 'Minimal menu utama aktif agar pengunjung mudah menuju halaman penting.', launch_readiness_enabled_nav_count() >= 3, 'Atur menu & footer', url('admin/navigation'), 10, 'Navigasi'),
            launch_readiness_item('homepage', 'Homepage sudah disesuaikan', 'Hero dan pesan utama homepage sebaiknya sudah sesuai brand/niche.', $homeEdited, 'Edit konten homepage', url('admin/template-content?page=home'), 12, 'Konten'),
            launch_readiness_item('catalog', 'Katalog atau layanan utama sudah ada', 'Tambahkan produk, paket, jasa, program, atau penawaran utama.', count($activeProducts) >= 1, 'Kelola katalog', url('admin/produk'), 10, 'Konten'),
            launch_readiness_item('articles', 'Konten SEO awal tersedia', 'Artikel awal membantu traffic organik dan edukasi calon customer.', count($activeArticles) >= 2, 'Kelola artikel', url('admin/artikel'), 8, 'SEO'),
            launch_readiness_item('forms', 'Form lead/checkout aktif', 'Form memudahkan pengunjung meninggalkan data atau melakukan inquiry.', count($activeForms) >= 1, 'Kelola form', url('admin/forms'), 8, 'Konversi'),
            launch_readiness_item('trust_conversion', 'Trust block sudah aktif', 'Benefit, testimoni, FAQ, garansi, badge, before-after, atau CTA block membantu pengunjung lebih yakin.', (int)($trustSummary['enabled_blocks'] ?? 0) >= 3, 'Atur Trust & Conversion', url('admin/trust-conversion'), 8, 'Konversi'),
            launch_readiness_item('cta', 'CTA utama sudah diarahkan', 'CTA WhatsApp/form/checkout harus jelas agar traffic bisa menjadi lead.', $waUrl !== '' || count($activeForms) >= 1, 'Atur CTA & marketing', url('admin/marketing-integrations'), 8, 'Konversi'),
            launch_readiness_item('seo', 'SEO dasar sudah dicek', 'Meta title, deskripsi, schema, dan kualitas konten sebaiknya sudah aman sebelum promosi.', $seoScore >= 60, 'Buka Universal SEO Engine', url('admin/universal-seo'), 8, 'SEO'),
            launch_readiness_item('landing', 'Landing page opsional siap', 'Landing page membantu campaign, ads, lead magnet, dan penawaran khusus.', count($publishedLandingPages) >= 1, 'Kelola landing page', url('admin/landing-pages'), 5, 'Growth'),
            launch_readiness_item('security', 'Keamanan dasar aman', 'Password admin dan guard login harus aman sebelum website dipakai publik.', $passwordOk, 'Cek keamanan', url('admin/security'), 10, 'Sistem'),
        ];

        $totalWeight = 0;
        $doneWeight = 0;
        foreach ($items as $item) {
            $totalWeight += (int)$item['weight'];
            if (($item['status'] ?? '') === 'ok') {
                $doneWeight += (int)$item['weight'];
            }
        }
        $score = $totalWeight > 0 ? (int)round(($doneWeight / $totalWeight) * 100) : 0;
        $status = $score >= 85 ? 'Siap Launch' : ($score >= 65 ? 'Hampir Siap' : 'Perlu Setup Awal');
        $nextItems = array_values(array_filter($items, static fn(array $item): bool => ($item['status'] ?? '') !== 'ok'));

        return [
            'score' => $score,
            'status' => $status,
            'items' => $items,
            'next_items' => array_slice($nextItems, 0, 5),
            'counts' => [
                'products' => count($activeProducts),
                'articles' => count($activeArticles),
                'forms' => count($activeForms),
                'landing_pages' => count($publishedLandingPages),
                'trust_blocks' => (int)($trustSummary['enabled_blocks'] ?? 0),
                'navigation' => launch_readiness_enabled_nav_count(),
                'seo_score' => $seoScore,
            ],
        ];
    }
}
