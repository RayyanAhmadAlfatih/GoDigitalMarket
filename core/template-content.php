<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| EDITABLE TEMPLATE CONTENT ENGINE
|--------------------------------------------------------------------------
| Keeps built-in public template pages editable from the admin dashboard.
| Default blue professional template content becomes starter content, not
| locked/hardcoded content. Safe, file-based, and shared-hosting friendly.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('template_content_storage_file')) {
    function template_content_storage_file(): string
    {
        return STORAGE_PATH . '/template-content.json';
    }
}

if (!function_exists('template_content_clean_text')) {
    function template_content_clean_text(mixed $value, int $max = 220): string
    {
        $value = trim((string)preg_replace('/\s+/', ' ', strip_tags((string)$value)));
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('template_content_safe_html')) {
    function template_content_safe_html(mixed $value, int $max = 8000): string
    {
        $html = (string)$value;
        $html = preg_replace('#<\s*(script|iframe|object|embed|style|link|meta|base|form|input|button|textarea|select)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? '';
        $html = preg_replace('#<\s*(script|iframe|object|embed|style|link|meta|base|form|input|button|textarea|select)[^>]*?/?>#is', '', $html) ?? '';
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
        $html = preg_replace('/(href|src)\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', '$1="#"', $html) ?? '';
        $html = strip_tags($html, '<p><br><strong><b><em><i><ul><ol><li><a><h2><h3><h4><blockquote><span><small>');
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($html, 0, $max, 'UTF-8') : substr($html, 0, $max);
    }
}

if (!function_exists('template_content_apply_tokens')) {
    function template_content_apply_tokens(string $content): string
    {
        $replacements = [
            '{site_name}' => SITE_NAME,
            '{site_tagline}' => SITE_TAGLINE,
            '{whatsapp}' => SITE_WHATSAPP,
            '{phone}' => SITE_PHONE,
            '{email}' => SITE_EMAIL,
            '{site_url}' => SITE_URL,
        ];
        return strtr($content, $replacements);
    }
}


if (!function_exists('template_content_normalize_section_id')) {
    function template_content_normalize_section_id(mixed $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return 'section_' . substr(md5((string)microtime(true)), 0, 8);
        }
        $id = function_exists('slugify') ? slugify($value) : strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $value));
        $id = trim((string)$id, '-');
        return $id !== '' ? str_replace('-', '_', $id) : 'section_' . substr(md5($value), 0, 8);
    }
}

if (!function_exists('template_content_clean_url')) {
    function template_content_clean_url(mixed $value, string $fallback = '#'): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return $fallback;
        }
        if ($value === '#' || str_starts_with($value, '#')) {
            return preg_match('/^#[a-zA-Z0-9_\-]*$/', $value) ? $value : $fallback;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if (preg_match('#^(mailto:|tel:)#i', $value)) {
            return $value;
        }
        $value = '/' . ltrim($value, '/');
        return preg_match('#^/[a-zA-Z0-9._~/%?=&+\-]*$#', $value) ? $value : $fallback;
    }
}

if (!function_exists('template_content_normalize_section')) {
    function template_content_normalize_section(array $section, array $defaults = []): array
    {
        $section = array_merge($defaults, $section);
        $id = template_content_normalize_section_id($section['id'] ?? ($defaults['id'] ?? $section['label'] ?? 'section'));
        $status = (string)($section['status'] ?? 'visible');
        if (!in_array($status, ['visible', 'hidden'], true)) {
            $status = 'visible';
        }

        return [
            'id' => $id,
            'label' => template_content_clean_text($section['label'] ?? ($defaults['label'] ?? $id), 90) ?: ucfirst(str_replace('_', ' ', $id)),
            'status' => $status,
            'locked' => !empty($section['locked']),
            'eyebrow' => template_content_clean_text($section['eyebrow'] ?? '', 80),
            'title' => template_content_clean_text($section['title'] ?? '', 140),
            'description' => template_content_clean_text($section['description'] ?? '', 320),
            'body_html' => template_content_safe_html($section['body_html'] ?? '', 12000),
            'button_label' => template_content_clean_text($section['button_label'] ?? '', 60),
            'button_url' => template_content_clean_url($section['button_url'] ?? '', '#'),
            'secondary_button_label' => template_content_clean_text($section['secondary_button_label'] ?? '', 60),
            'secondary_button_url' => template_content_clean_url($section['secondary_button_url'] ?? '', '#'),
            'updated_at' => template_content_clean_text($section['updated_at'] ?? '', 40),
        ];
    }
}

if (!function_exists('template_content_normalize_sections')) {
    function template_content_normalize_sections(mixed $sections, mixed $defaults = []): array
    {
        $defaults = is_array($defaults) ? $defaults : [];
        $sections = is_array($sections) ? $sections : [];
        $normalized = [];

        foreach ($defaults as $id => $default) {
            if (!is_array($default)) {
                continue;
            }
            $default['id'] = (string)($default['id'] ?? $id);
            $posted = $sections[$id] ?? [];
            $normalized[$id] = template_content_normalize_section(is_array($posted) ? $posted : [], $default);
        }

        foreach ($sections as $id => $section) {
            if (isset($normalized[$id]) || !is_array($section)) {
                continue;
            }
            $section['id'] = (string)($section['id'] ?? $id);
            $normalized[(string)$id] = template_content_normalize_section($section, ['id' => (string)$id, 'label' => (string)$id]);
        }

        return $normalized;
    }
}

if (!function_exists('template_content_section')) {
    function template_content_section(array $page, string $sectionId): array
    {
        $sections = (array)($page['sections'] ?? []);
        return is_array($sections[$sectionId] ?? null) ? (array)$sections[$sectionId] : [];
    }
}

if (!function_exists('template_content_section_visible')) {
    function template_content_section_visible(array $page, string $sectionId): bool
    {
        $section = template_content_section($page, $sectionId);
        if (!$section) {
            return true;
        }
        return (string)($section['status'] ?? 'visible') === 'visible';
    }
}

if (!function_exists('template_content_section_value')) {
    function template_content_section_value(array $page, string $sectionId, string $field, string $fallback = ''): string
    {
        $section = template_content_section($page, $sectionId);
        $value = (string)($section[$field] ?? '');
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('template_content_render_custom_sections')) {
    function template_content_render_custom_sections(array $page, string $prefix = 'custom'): void
    {
        foreach ((array)($page['sections'] ?? []) as $section) {
            if (!is_array($section) || (string)($section['status'] ?? 'visible') !== 'visible') {
                continue;
            }
            $id = (string)($section['id'] ?? '');
            if (!str_starts_with($id, $prefix)) {
                continue;
            }
            $title = (string)($section['title'] ?? '');
            $description = (string)($section['description'] ?? '');
            $body = (string)($section['body_html'] ?? '');
            $eyebrow = (string)($section['eyebrow'] ?? '');
            $buttonLabel = (string)($section['button_label'] ?? '');
            $buttonUrl = (string)($section['button_url'] ?? '#');
            ?>
            <section class="section alt editable-template-custom-section" id="<?= esc($id); ?>">
                <div class="container">
                    <?php if ($eyebrow !== ''): ?><span class="section-eyebrow"><?= esc($eyebrow); ?></span><?php endif; ?>
                    <?php if ($title !== ''): ?><h2 class="title"><?= esc($title); ?></h2><?php endif; ?>
                    <?php if ($description !== ''): ?><p class="center"><?= esc($description); ?></p><?php endif; ?>
                    <?php if ($body !== ''): ?><div class="editable-template-body card"><div class="card-content"><?= template_content_apply_tokens($body); ?></div></div><?php endif; ?>
                    <?php if ($buttonLabel !== ''): ?><p class="center"><a class="btn" href="<?= esc($buttonUrl); ?>"><?= esc($buttonLabel); ?></a></p><?php endif; ?>
                </div>
            </section>
            <?php
        }
    }
}

if (!function_exists('template_content_defaults')) {
    function template_content_defaults(): array
    {
        return [
            'about' => [
                'key' => 'about',
                'label' => 'Tentang Kami',
                'path' => 'tentang-kami',
                'status' => 'published',
                'meta_title' => 'Tentang Kami - {site_name}',
                'meta_description' => 'Profil singkat bisnis, value proposition, keunggulan, dan kontak utama. Konten ini bisa diganti sesuai brand UMKM.',
                'hero_title' => 'Tentang Kami',
                'hero_description' => 'Halaman profil bisnis yang bisa disesuaikan untuk toko, jasa, layanan profesional, kuliner, edukasi, digital product, atau company profile.',
                'primary_title' => 'Ceritakan Bisnis Anda',
                'primary_html' => '<p>Gunakan halaman ini untuk menjelaskan siapa Anda, produk atau layanan utama, area layanan, cara pemesanan, dan alasan pelanggan sebaiknya memilih bisnis Anda.</p><p>Konten default ini sengaja dibuat general agar aman dipakai sebagai template lintas niche.</p>',
                'secondary_title' => 'Keunggulan',
                'secondary_html' => '<ul><li>Konten bisa diedit sesuai brand.</li><li>Cocok untuk produk dan jasa.</li><li>Mendukung katalog, artikel, landing page, form, checkout, dan WhatsApp.</li></ul>',
                'show_in_admin' => true,
                'updated_at' => '',
            ],
            'contact' => [
                'key' => 'contact',
                'label' => 'Kontak',
                'path' => 'kontak',
                'status' => 'published',
                'meta_title' => 'Kontak - {site_name}',
                'meta_description' => 'Hubungi admin untuk konsultasi produk, layanan, pemesanan, kerja sama, atau pertanyaan lain.',
                'hero_title' => 'Kontak',
                'hero_description' => 'Hubungi admin untuk konsultasi produk, layanan, order, kerja sama, atau kebutuhan khusus.',
                'primary_title' => 'Informasi Kontak',
                'primary_html' => '<p><strong>WhatsApp:</strong> {whatsapp}</p><p><strong>Email:</strong> {email}</p><p><strong>Area:</strong> Indonesia / Online</p>',
                'secondary_title' => 'Kirim Pesan',
                'secondary_html' => '<p>Isi form berikut agar admin bisa menghubungi Anda kembali. Teks ini bisa disesuaikan untuk konsultasi, booking, quotation, atau customer support.</p>',
                'show_contact_form' => true,
                'show_in_admin' => true,
                'updated_at' => '',
            ],
            'privacy' => [
                'key' => 'privacy',
                'label' => 'Privacy Policy',
                'path' => 'privacy-policy',
                'status' => 'published',
                'meta_title' => 'Privacy Policy - {site_name}',
                'meta_description' => 'Kebijakan privasi website.',
                'hero_title' => 'Privacy Policy',
                'hero_description' => 'Kebijakan privasi untuk pengunjung, pelanggan, dan pengguna form website.',
                'primary_title' => 'Kebijakan Privasi',
                'primary_html' => '<p>Website ini dapat mengumpulkan data yang dikirim melalui form, seperti nama, nomor WhatsApp, email, pesan, produk yang diminati, dan kebutuhan pelanggan.</p><p>Data digunakan untuk menghubungi pelanggan, memproses inquiry/order, memberikan layanan, dan meningkatkan pengalaman website. Admin website bertanggung jawab menyesuaikan isi kebijakan ini dengan praktik bisnis masing-masing.</p>',
                'secondary_title' => '',
                'secondary_html' => '',
                'show_in_admin' => true,
                'updated_at' => '',
            ],
            'terms' => [
                'key' => 'terms',
                'label' => 'Terms',
                'path' => 'terms',
                'status' => 'published',
                'meta_title' => 'Terms - {site_name}',
                'meta_description' => 'Syarat dan ketentuan penggunaan website.',
                'hero_title' => 'Terms',
                'hero_description' => 'Syarat dan ketentuan umum penggunaan website dan layanan.',
                'primary_title' => 'Syarat & Ketentuan',
                'primary_html' => '<p>Informasi produk, layanan, harga, ketersediaan, jadwal, dan metode pembayaran dapat berubah sesuai kebijakan pemilik bisnis.</p><p>Admin website perlu menyesuaikan halaman ini dengan ketentuan bisnis masing-masing, termasuk pengiriman, refund, garansi, pembatalan, dan layanan pelanggan.</p>',
                'secondary_title' => '',
                'secondary_html' => '',
                'show_in_admin' => true,
                'updated_at' => '',
            ],
            'home' => [
                'key' => 'home',
                'label' => 'Home / Beranda',
                'path' => '',
                'status' => 'published',
                'is_dynamic_template' => true,
                'meta_title' => '{site_name} - Website Siap Growth untuk UMKM',
                'meta_description' => 'Beranda template UMKM Growth yang bisa diedit dari dashboard admin, termasuk hero, CTA, section katalog, artikel, trust, dan form lead.',
                'hero_title' => 'Bangun Website Produk, Jasa, dan Landing Page dalam Satu Template',
                'hero_description' => 'Gunakan template ini untuk company profile, katalog produk, sales page, form lead, checkout manual, dan konsultasi WhatsApp. Semua konten bisa disesuaikan dengan brand bisnis Anda.',
                'primary_title' => 'Beranda fleksibel untuk semua model bisnis',
                'primary_html' => '<p>Section beranda ini bisa diedit, disembunyikan, ditambah, atau dikembalikan ke starter bawaan dari dashboard admin.</p>',
                'secondary_title' => 'Catatan template',
                'secondary_html' => '<p>Konten default warna biru profesional adalah starter content, bukan konten yang terkunci di kode.</p>',
                'show_in_admin' => true,
                'sections' => [
                    'hero' => ['id' => 'hero', 'label' => 'Hero Utama', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Website siap jualan untuk UMKM', 'title' => 'Bangun Website Produk, Jasa, dan Landing Page dalam Satu Template', 'description' => 'Gunakan template ini untuk company profile, katalog produk, sales page, form lead, checkout manual, dan konsultasi WhatsApp. Semua konten bisa disesuaikan dengan brand bisnis Anda.', 'body_html' => '', 'button_label' => 'Lihat Katalog', 'button_url' => '/katalog', 'secondary_button_label' => 'Konsultasi', 'secondary_button_url' => '/kontak'],
                    'trustbar' => ['id' => 'trustbar', 'label' => 'Trust Bar / Highlight Angka', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Trust', 'title' => 'Highlight Keunggulan', 'description' => 'Ubah item trust dari Pengaturan Beranda atau sembunyikan section ini dari Konten Template.', 'body_html' => ''],
                    'profile_intro' => ['id' => 'profile_intro', 'label' => 'Intro Mode Bisnis', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Beranda fleksibel', 'title' => 'Pilih Gaya Beranda Sesuai Cara Bisnis Anda Berjualan', 'description' => 'Beranda bisa diarahkan menjadi company profile, katalog, sales page, lead form, atau halaman brand sederhana.', 'body_html' => ''],
                    'featured_catalog' => ['id' => 'featured_catalog', 'label' => 'Katalog Pilihan', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Katalog pilihan', 'title' => 'Contoh Katalog Produk & Layanan', 'description' => 'Katalog contoh ini bisa diganti admin dengan produk, jasa, harga, gambar, kategori, deskripsi, dan tombol CTA sesuai bisnis masing-masing.', 'body_html' => '', 'button_label' => 'Buka Semua Katalog', 'button_url' => '/katalog'],
                    'business_fit' => ['id' => 'business_fit', 'label' => 'Business Fit / Cocok Untuk', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Cocok untuk banyak bisnis', 'title' => 'Fleksibel untuk Banyak Jenis Bisnis', 'description' => 'Template ini disiapkan agar bisa dipakai UMKM yang menjual barang, jasa, layanan, paket, menu kuliner, produk digital, booking, sampai company profile.', 'body_html' => '<ul><li>Company profile untuk membangun trust.</li><li>Sales page untuk campaign dan penawaran.</li><li>Katalog dan checkout untuk produk atau jasa.</li></ul>'],
                    'latest_articles' => ['id' => 'latest_articles', 'label' => 'Artikel Terbaru', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Artikel & edukasi', 'title' => 'Artikel Terbaru', 'description' => 'Gunakan blog untuk edukasi pelanggan, menjawab FAQ, dan memperkuat SEO bisnis.', 'body_html' => ''],
                    'lead_form' => ['id' => 'lead_form', 'label' => 'Form Lead Beranda', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Form', 'title' => 'Butuh Bantuan Memilih Produk atau Layanan?', 'description' => 'Isi form singkat ini untuk konsultasi, permintaan penawaran, booking, atau pertanyaan lainnya.', 'body_html' => '', 'button_label' => 'Kirim Permintaan', 'button_url' => '#form-konsultasi'],
                ],
                'updated_at' => '',
            ],
            'catalog' => [
                'key' => 'catalog',
                'label' => 'Katalog Produk/Jasa',
                'path' => 'katalog',
                'status' => 'published',
                'is_dynamic_template' => true,
                'meta_title' => 'Katalog Produk & Jasa - {site_name}',
                'meta_description' => 'Katalog produk, jasa, paket, produk digital, booking, dan penawaran bisnis yang bisa diedit dari dashboard admin.',
                'hero_title' => 'Katalog Produk, Jasa, dan Paket Bisnis',
                'hero_description' => 'Temukan produk fisik, jasa, paket layanan, produk digital, booking, atau penawaran lain sesuai kebutuhan Anda.',
                'primary_title' => 'Panduan Memilih Produk/Jasa',
                'primary_html' => '<p>Pilih berdasarkan kebutuhan pelanggan, kategori, harga, benefit, ketersediaan, area layanan, dan metode pemesanan.</p>',
                'secondary_title' => 'Area Layanan Populer',
                'secondary_html' => '<p>Online, Jakarta, Bandung, Surabaya, Yogyakarta, Semarang, dan area lain.</p>',
                'show_in_admin' => true,
                'sections' => [
                    'hero' => ['id' => 'hero', 'label' => 'Hero Katalog', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Katalog Universal', 'title' => 'Produk, Jasa, Paket, E-book, E-course, dan Digital Download', 'description' => 'Gunakan filter untuk menampilkan item yang paling sesuai: produk fisik, jasa, menu, booking, e-book, e-course, template digital, atau custom order.', 'body_html' => ''],
                    'filter_panel' => ['id' => 'filter_panel', 'label' => 'Panel Filter', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Filter', 'title' => 'Filter Katalog', 'description' => 'Bantu pengunjung mencari produk atau jasa berdasarkan kategori, tipe item, kelas, area, dan status.', 'body_html' => ''],
                    'result_intro' => ['id' => 'result_intro', 'label' => 'Judul Hasil Katalog', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Hasil', 'title' => 'Item Ditemukan', 'description' => 'Bandingkan kategori, tipe item, paket, area, akses digital, dan status sebelum lanjut bertanya atau checkout.', 'body_html' => ''],
                    'guide_left' => ['id' => 'guide_left', 'label' => 'Panduan Kiri', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Guide', 'title' => 'Panduan Memilih Produk/Jasa', 'description' => '', 'body_html' => '<p>Pilih berdasarkan kebutuhan pelanggan, kategori, harga, benefit, ketersediaan, area layanan, dan metode pemesanan. Untuk produk digital, perhatikan mode akses seperti download, link khusus, atau member area.</p>'],
                    'guide_right' => ['id' => 'guide_right', 'label' => 'Panduan Kanan', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Area', 'title' => 'Area Layanan Populer', 'description' => 'Gunakan konten ini untuk area, channel, atau kategori cepat.', 'body_html' => '<p>Online, Jakarta, Bandung, Surabaya, Yogyakarta, Semarang, dan area lain.</p>'],
                    'lead_form' => ['id' => 'lead_form', 'label' => 'Form Inquiry Katalog', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Inquiry', 'title' => 'Butuh Dibantu Pilih Produk/Jasa?', 'description' => 'Isi form singkat jika ingin dibantu memilih produk, layanan, paket, atau jadwal yang paling cocok.', 'body_html' => '', 'button_label' => 'Kirim Permintaan', 'button_url' => '#'],
                ],
                'updated_at' => '',
            ],
            'services' => [
                'key' => 'services',
                'label' => 'Layanan',
                'path' => 'layanan',
                'status' => 'published',
                'is_dynamic_template' => true,
                'meta_title' => 'Layanan - {site_name}',
                'meta_description' => 'Halaman layanan, jasa, konsultasi, paket, booking, dan quotation yang bisa diedit dari dashboard admin.',
                'hero_title' => 'Jasa, Paket Layanan, Konsultasi, dan Booking',
                'hero_description' => 'Pilih layanan, paket konsultasi, booking, atau penawaran custom sesuai kebutuhan Anda.',
                'primary_title' => 'Panduan Memilih Layanan',
                'primary_html' => '<p>Bandingkan benefit, cakupan layanan, jadwal, cara kerja, dan metode pemesanan sebelum memilih layanan.</p>',
                'secondary_title' => 'Kanal Layanan Populer',
                'secondary_html' => '<p>Online, konsultasi, booking, onsite, maintenance, dan custom order.</p>',
                'show_in_admin' => true,
                'sections' => [
                    'hero' => ['id' => 'hero', 'label' => 'Hero Layanan', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Layanan Fleksibel', 'title' => 'Jasa, Paket Layanan, Konsultasi, dan Booking', 'description' => 'Gunakan filter untuk menemukan layanan, jasa, booking, konsultasi, paket retainer, atau custom order yang sesuai.', 'body_html' => ''],
                    'filter_panel' => ['id' => 'filter_panel', 'label' => 'Panel Filter Layanan', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Filter', 'title' => 'Filter Layanan', 'description' => 'Bantu pengunjung mencari layanan berdasarkan kategori, tipe, kelas, area, dan status.', 'body_html' => ''],
                    'result_intro' => ['id' => 'result_intro', 'label' => 'Judul Hasil Layanan', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Hasil', 'title' => 'Layanan Ditemukan', 'description' => 'Bandingkan layanan, paket, jadwal, area, dan CTA sebelum lanjut konsultasi atau checkout.', 'body_html' => ''],
                    'guide_left' => ['id' => 'guide_left', 'label' => 'Panduan Kiri', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Guide', 'title' => 'Panduan Memilih Layanan', 'description' => '', 'body_html' => '<p>Bandingkan benefit, cakupan layanan, jadwal, cara kerja, dan metode pemesanan sebelum memilih layanan.</p>'],
                    'guide_right' => ['id' => 'guide_right', 'label' => 'Panduan Kanan', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Kanal', 'title' => 'Kanal Layanan Populer', 'description' => 'Gunakan konten ini untuk tipe layanan, area, atau channel cepat.', 'body_html' => '<p>Online, konsultasi, booking, onsite, maintenance, dan custom order.</p>'],
                    'lead_form' => ['id' => 'lead_form', 'label' => 'Form Inquiry Layanan', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Inquiry', 'title' => 'Butuh Konsultasi Layanan?', 'description' => 'Isi form singkat jika ingin dibantu memilih layanan, paket, jadwal, atau penawaran custom.', 'body_html' => '', 'button_label' => 'Kirim Permintaan', 'button_url' => '#'],
                ],
                'updated_at' => '',
            ],
            'articles' => [
                'key' => 'articles',
                'label' => 'Artikel',
                'path' => 'artikel',
                'status' => 'published',
                'is_dynamic_template' => true,
                'meta_title' => 'Artikel & Panduan Bisnis - {site_name}',
                'meta_description' => 'Kumpulan artikel edukasi yang bisa disesuaikan untuk niche, produk, layanan, SEO, dan kebutuhan pelanggan.',
                'hero_title' => 'Artikel & Panduan Bisnis',
                'hero_description' => 'Kumpulan artikel edukasi untuk membantu pelanggan memahami produk, layanan, checkout, marketing, dan informasi bisnis Anda.',
                'primary_title' => 'Kategori Artikel',
                'primary_html' => '<p>Gunakan kategori untuk mengelompokkan edukasi, FAQ, insight, dan konten pendukung SEO.</p>',
                'secondary_title' => 'Artikel Terbaru',
                'secondary_html' => '<p>Artikel edukasi siap diganti sesuai niche dan target pelanggan bisnis.</p>',
                'show_in_admin' => true,
                'sections' => [
                    'hero' => ['id' => 'hero', 'label' => 'Hero Artikel', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Konten SEO', 'title' => 'Artikel & Panduan Bisnis', 'description' => 'Kumpulan artikel edukasi untuk membantu pelanggan memahami produk, layanan, checkout, marketing, dan informasi bisnis Anda.', 'body_html' => ''],
                    'categories' => ['id' => 'categories', 'label' => 'Kategori Artikel', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Kategori', 'title' => 'Kategori Artikel', 'description' => 'Update edukasi dan panduan bisnis berdasarkan kategori yang bisa dikelola dari admin.', 'body_html' => ''],
                    'article_list' => ['id' => 'article_list', 'label' => 'Daftar Artikel', 'status' => 'visible', 'locked' => true, 'eyebrow' => 'Artikel', 'title' => 'Artikel Terbaru', 'description' => 'Artikel edukasi siap diganti sesuai niche dan target pelanggan bisnis.', 'body_html' => ''],
                ],
                'updated_at' => '',
            ],
        ];
    }
}

if (!function_exists('template_content_normalize_page')) {
    function template_content_normalize_page(array $page, array $defaults = []): array
    {
        $page = array_merge($defaults, $page);
        $page['key'] = template_content_clean_text($page['key'] ?? ($defaults['key'] ?? ''), 40);
        $page['label'] = template_content_clean_text($page['label'] ?? ($defaults['label'] ?? 'Halaman'), 80);
        $page['path'] = trim((string)($page['path'] ?? ($defaults['path'] ?? '')), '/');
        $page['status'] = in_array((string)($page['status'] ?? 'published'), ['published', 'draft', 'hidden'], true) ? (string)$page['status'] : 'published';
        foreach (['meta_title', 'meta_description', 'hero_title', 'hero_description', 'primary_title', 'secondary_title'] as $field) {
            $page[$field] = template_content_clean_text($page[$field] ?? '', $field === 'meta_description' || str_ends_with($field, 'description') ? 220 : 110);
        }
        $page['primary_html'] = template_content_safe_html($page['primary_html'] ?? '');
        $page['secondary_html'] = template_content_safe_html($page['secondary_html'] ?? '');
        $page['show_contact_form'] = !empty($page['show_contact_form']);
        $page['show_in_admin'] = !array_key_exists('show_in_admin', $page) || (bool)$page['show_in_admin'];
        $page['is_dynamic_template'] = !empty($page['is_dynamic_template']);
        $page['sections'] = template_content_normalize_sections($page['sections'] ?? [], $defaults['sections'] ?? []);
        $page['updated_at'] = template_content_clean_text($page['updated_at'] ?? '', 40);
        return $page;
    }
}

if (!function_exists('template_content_all')) {
    function template_content_all(): array
    {
        $defaults = template_content_defaults();
        $file = template_content_storage_file();
        $stored = [];
        if (is_file($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            $stored = is_array($decoded) ? $decoded : [];
        }
        $pages = [];
        foreach ($defaults as $key => $default) {
            $pages[$key] = template_content_normalize_page((array)($stored[$key] ?? []), $default);
        }
        foreach ($stored as $key => $page) {
            if (!isset($pages[$key]) && is_array($page)) {
                $pages[$key] = template_content_normalize_page($page, ['key' => (string)$key, 'label' => (string)$key, 'path' => (string)$key]);
            }
        }
        return $pages;
    }
}

if (!function_exists('template_content_page')) {
    function template_content_page(string $key): array
    {
        $pages = template_content_all();
        $defaults = template_content_defaults();
        return $pages[$key] ?? $defaults[$key] ?? reset($defaults);
    }
}

if (!function_exists('template_content_save_all')) {
    function template_content_save_all(array $pages): void
    {
        $defaults = template_content_defaults();
        $normalized = [];
        foreach ($pages as $key => $page) {
            if (!is_array($page)) {
                continue;
            }
            $normalized[(string)$key] = template_content_normalize_page($page, $defaults[$key] ?? ['key' => (string)$key]);
        }
        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            throw new RuntimeException('Folder storage belum bisa dibuat.');
        }
        $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents(template_content_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Konten template gagal disimpan. Cek permission folder storage.');
        }
        @chmod(template_content_storage_file(), 0644);
    }
}

if (!function_exists('template_content_save_page')) {
    function template_content_save_page(string $key, array $input): array
    {
        $pages = template_content_all();
        if (!isset($pages[$key])) {
            throw new InvalidArgumentException('Halaman template tidak ditemukan.');
        }
        $page = $pages[$key];
        foreach (['meta_title', 'meta_description', 'hero_title', 'hero_description', 'primary_title', 'secondary_title'] as $field) {
            $page[$field] = template_content_clean_text($input[$field] ?? ($page[$field] ?? ''), $field === 'meta_description' || str_ends_with($field, 'description') ? 220 : 110);
        }
        $page['primary_html'] = template_content_safe_html($input['primary_html'] ?? ($page['primary_html'] ?? ''));
        $page['secondary_html'] = template_content_safe_html($input['secondary_html'] ?? ($page['secondary_html'] ?? ''));
        $page['status'] = in_array((string)($input['status'] ?? 'published'), ['published', 'draft', 'hidden'], true) ? (string)$input['status'] : 'published';
        $page['show_contact_form'] = !empty($input['show_contact_form']);

        if (isset($input['sections']) && is_array($input['sections'])) {
            $savedSections = [];
            $currentSections = (array)($page['sections'] ?? []);
            foreach ($input['sections'] as $sectionId => $sectionInput) {
                if (!is_array($sectionInput)) {
                    continue;
                }
                $sectionId = (string)$sectionId;
                $base = (array)($currentSections[$sectionId] ?? ['id' => $sectionId, 'label' => $sectionId]);
                if (!empty($sectionInput['delete_section']) && empty($base['locked'])) {
                    continue;
                }
                $sectionInput['id'] = $sectionId;
                $sectionInput['status'] = !empty($sectionInput['is_visible']) ? 'visible' : 'hidden';
                $sectionInput['locked'] = !empty($base['locked']);
                $sectionInput['updated_at'] = date('c');
                $savedSections[$sectionId] = template_content_normalize_section($sectionInput, $base);
            }

            $newSectionTitle = template_content_clean_text($input['new_section_title'] ?? '', 140);
            $newSectionLabel = template_content_clean_text($input['new_section_label'] ?? $newSectionTitle, 90);
            if ($newSectionTitle !== '' || $newSectionLabel !== '') {
                $newId = 'custom_' . template_content_normalize_section_id($newSectionLabel ?: $newSectionTitle);
                $counter = 2;
                $baseId = $newId;
                while (isset($savedSections[$newId])) {
                    $newId = $baseId . '_' . $counter;
                    $counter++;
                }
                $savedSections[$newId] = template_content_normalize_section([
                    'id' => $newId,
                    'label' => $newSectionLabel ?: 'Section Custom',
                    'status' => 'visible',
                    'locked' => false,
                    'eyebrow' => $input['new_section_eyebrow'] ?? '',
                    'title' => $newSectionTitle,
                    'description' => $input['new_section_description'] ?? '',
                    'body_html' => $input['new_section_body_html'] ?? '',
                    'button_label' => $input['new_section_button_label'] ?? '',
                    'button_url' => $input['new_section_button_url'] ?? '#',
                    'updated_at' => date('c'),
                ]);
            }

            $page['sections'] = $savedSections;
        }

        $page['updated_at'] = date('c');
        $pages[$key] = $page;
        template_content_save_all($pages);
        if (function_exists('activity_log_record')) {
            activity_log_record('update_template_content', 'content', null, 'Admin mengubah konten template publik.', ['page' => $key]);
        }
        return $page;
    }
}

if (!function_exists('template_content_reset_page')) {
    function template_content_reset_page(string $key): array
    {
        $defaults = template_content_defaults();
        if (!isset($defaults[$key])) {
            throw new InvalidArgumentException('Halaman template tidak ditemukan.');
        }
        $pages = template_content_all();
        $pages[$key] = template_content_normalize_page(array_merge($defaults[$key], ['updated_at' => date('c')]), $defaults[$key]);
        template_content_save_all($pages);
        return $pages[$key];
    }
}

if (!function_exists('template_content_public_page')) {
    function template_content_public_page(string $key): array
    {
        $page = template_content_page($key);
        foreach (['meta_title', 'meta_description', 'hero_title', 'hero_description', 'primary_title', 'secondary_title', 'primary_html', 'secondary_html'] as $field) {
            $page[$field] = template_content_apply_tokens((string)($page[$field] ?? ''));
        }
        foreach ((array)($page['sections'] ?? []) as $id => $section) {
            if (!is_array($section)) {
                continue;
            }
            foreach (['eyebrow', 'title', 'description', 'body_html', 'button_label', 'button_url', 'secondary_button_label', 'secondary_button_url'] as $field) {
                $page['sections'][$id][$field] = template_content_apply_tokens((string)($section[$field] ?? ''));
            }
        }
        return $page;
    }
}

if (!function_exists('template_content_inventory')) {
    function template_content_inventory(): array
    {
        $pages = template_content_all();
        $items = [];
        foreach ($pages as $key => $page) {
            $items[] = [
                'key' => $key,
                'label' => (string)($page['label'] ?? $key),
                'path' => (string)($page['path'] ?? ''),
                'status' => (string)($page['status'] ?? 'published'),
                'updated_at' => (string)($page['updated_at'] ?? ''),
                'edit_url' => function_exists('url') ? url('admin/template-content?page=' . rawurlencode((string)$key)) : 'admin/template-content?page=' . rawurlencode((string)$key),
                'public_url' => function_exists('url') ? url((string)($page['path'] ?? '')) : (string)($page['path'] ?? ''),
            ];
        }
        return $items;
    }
}
