<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| Template Landing Builder + Tes Variasi Tombol/Form
|--------------------------------------------------------------------------
| Block-based, JSON-backed landing page engine with polished visual repeat
| editors, smart grid rendering, autosave/undo safety, SEO conversion
| health check, template/preset system, and server-side revision history.
|--------------------------------------------------------------------------
*/

if (!function_exists('landing_page_storage_path')) {
    function landing_page_storage_path(): string
    {
        return STORAGE_PATH . '/landing-pages.json';
    }
}


if (!function_exists('landing_page_template_storage_path')) {
    function landing_page_template_storage_path(): string
    {
        return STORAGE_PATH . '/landing-page-templates.json';
    }
}


if (!function_exists('landing_page_revision_storage_path')) {
    function landing_page_revision_storage_path(): string
    {
        return STORAGE_PATH . '/landing-page-revisions.json';
    }
}

if (!function_exists('landing_page_template_categories')) {
    function landing_page_template_categories(): array
    {
        return [
            'Direct Selling',
            'Lead Magnet',
            'Promo Produk',
            'Jasa Lokal',
            'Event / Campaign',
            'WhatsApp Ads',
            'Google Ads Landing',
            'Custom UMKM',
        ];
    }
}


if (!function_exists('landing_page_template_cta_url')) {
    function landing_page_template_cta_url(string $message, string $sumber): string
    {
        if (function_exists('wa_link_contextual')) {
            return wa_link_contextual($message, ['sumber' => $sumber]);
        }
        return '#form-konsultasi';
    }
}

if (!function_exists('landing_page_builtin_templates')) {
    function landing_page_builtin_templates(): array
    {
        $placeholder = function_exists('asset') ? asset('images/placeholder-product.svg') : '/assets/images/placeholder-product.svg';
        return [
            [
                'id' => 'layanan-area-lokal',
                'name' => 'Layanan Lokal - Lead Form Cepat',
                'category' => 'Contoh Siap Pakai',
                'description' => 'Template untuk promosi paket layanan area lokal dengan form konsultasi, Tombol WA, FAQ, dan internal link SEO.',
                'page_title' => 'Paket Layanan Lokal Praktis',
                'slug_base' => 'paket-layanan-area-lokal-praktis',
                'layout_mode' => 'focus',
                'hide_header' => true,
                'hide_footer' => true,
                'hide_floating_wa' => true,
                'show_nav_only' => false,
                'include_seo' => true,
                'include_tracking' => true,
                'meta_title' => 'Paket Layanan Lokal Praktis - Konsultasi Cepat',
                'meta_description' => 'Landing page paket layanan Area Lokal untuk konsultasi cepat, pilihan paket jelas, form lead, Tombol WhatsApp, FAQ, dan rekomendasi layanan terkait.',
                'meta_keywords' => 'paket layanan area-lokal, layanan area-lokal, jasa layanan area-lokal',
                'tracking_label' => 'Landing Page Layanan Lokal Template',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Layanan Lokal', 'headline' => 'Paket Layanan Praktis untuk Keluarga di Area Lokal', 'subheadline' => 'Bingung pilih paket layanan yang sesuai? Isi form atau chat admin, kami bantu arahkan pilihan paket, jadwal, dan lokasi layanan.', 'image' => $placeholder, 'image_alt' => 'Paket layanan Area Lokal', 'primary_text' => 'Konsultasi Paket Layanan', 'primary_url' => landing_page_template_cta_url('saya ingin konsultasi paket layanan Area Lokal.', 'Template Layanan Lokal'), 'secondary_text' => 'Isi Form Cepat', 'secondary_url' => '#form-konsultasi', 'hero_layout' => 'auto', 'hero_position' => 'right', 'bg_color' => '#eff6ff', 'headline_size' => '44px'],
                    ['type' => 'pain_points', 'headline' => 'Biasanya calon customer bingung di bagian ini', 'items' => ['Tidak tahu paket mana yang paling pas untuk keluarga.', 'Butuh estimasi budget dan jadwal tanpa harus bolak-balik tanya.', 'Ingin proses praktis dari konsultasi sampai follow-up admin.']],
                    ['type' => 'benefits', 'headline' => 'Kenapa konsultasi lewat halaman ini?', 'items' => [['title' => 'Admin bantu pilih paket', 'text' => 'Form dibuat agar admin langsung paham kebutuhan awal Anda.'], ['title' => 'Follow-up lebih rapi', 'text' => 'Data masuk dengan kategori yang jelas sehingga respon lebih terarah.'], ['title' => 'Bisa lanjut via WhatsApp', 'text' => 'Tombol dan form diarahkan untuk percakapan yang lebih cepat.']]],
                    ['type' => 'lead_form', 'headline' => 'Isi Form Konsultasi Layanan', 'text' => 'Lengkapi data singkat ini agar admin bisa bantu rekomendasikan paket yang sesuai.', 'submit_text' => 'Kirim Konsultasi', 'success_text' => 'Terima kasih, data konsultasi sudah masuk. Admin akan follow-up.', 'need_default' => 'Konsultasi Paket Layanan Lokal', 'form_name' => 'Form Layanan Lokal', 'lead_segment' => 'layanan-area-lokal', 'lead_tags' => 'layanan,area-lokal,lp-template', 'lead_priority' => 'warm', 'lead_stage' => 'new-lead', 'lead_score' => '60', 'consent_text' => 'Saya bersedia dihubungi admin terkait konsultasi paket layanan.', 'fields' => landing_page_default_form_fields(), 'bg_color' => '#f8fafc'],
                    ['type' => 'faq', 'headline' => 'FAQ Layanan Lokal', 'items' => [['question' => 'Apakah bisa konsultasi dulu?', 'answer' => 'Bisa. Admin akan membantu menjelaskan paket dan estimasi sesuai kebutuhan.'], ['question' => 'Apakah wajib order setelah isi form?', 'answer' => 'Tidak. Form ini untuk konsultasi awal dan follow-up admin.'], ['question' => 'Apakah bisa tanya jadwal?', 'answer' => 'Bisa. Tulis kebutuhan dan perkiraan tanggal di catatan form.']]],
                    ['type' => 'custom_menu', 'engine_internal_links' => true, 'headline' => 'Link Cepat & Rekomendasi', 'text' => 'Baca halaman pendukung sebelum menentukan pilihan.', 'items' => [['title' => 'Paket Layanan', 'text' => 'Info layanan dan paket', 'url' => '/paket-layanan', 'link_url' => '/paket-layanan'], ['title' => 'Area Layanan', 'text' => 'Cek area layanan sekitar', 'url' => '/kontak', 'link_url' => '/kontak'], ['title' => 'Artikel Edukasi', 'text' => 'Baca panduan layanan dan produk', 'url' => '/artikel', 'link_url' => '/artikel']]],
                    ['type' => 'cta', 'headline' => 'Mau dibantu pilih paket sekarang?', 'text' => 'Klik tombol WhatsApp atau isi form agar admin bisa follow-up lebih cepat.', 'button_text' => 'Chat Admin Layanan', 'button_url' => landing_page_template_cta_url('saya ingin konsultasi layanan Area Lokal.', 'Template Layanan Lokal Tombol'), 'bg_color' => '#eff6ff', 'align' => 'center'],
                ],
            ],
            [
                'id' => 'produk-fisik',
                'name' => 'Produk Fisik - Katalog & Offer',
                'category' => 'Contoh Siap Pakai',
                'description' => 'Template untuk promosi produk fisik dengan pricing/offer, benefit, FAQ, dan link ke katalog produk fisik.',
                'page_title' => 'Paket Produk Fisik Pilihan',
                'slug_base' => 'paket-produk-fisik-pilihan',
                'layout_mode' => 'focus',
                'hide_header' => true,
                'hide_footer' => true,
                'hide_floating_wa' => true,
                'show_nav_only' => false,
                'include_seo' => true,
                'include_tracking' => true,
                'meta_title' => 'Paket Produk Fisik - Katalog Produk Fisik Pilihan',
                'meta_description' => 'Landing page paket produk fisik dengan pilihan ekonomis, medium, premium, Tombol WhatsApp, form konsultasi, FAQ, dan internal link katalog produk fisik.',
                'meta_keywords' => 'produk fisik, produk fisik, katalog produk fisik',
                'tracking_label' => 'Landing Page Produk Fisik Template',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Produk Fisik', 'headline' => 'Cari Produk Fisik yang Sesuai Budget dan Kebutuhan?', 'subheadline' => 'Mulai dari pilihan ekonomis sampai premium. Admin bisa bantu cek stok, lokasi, dan rekomendasi produk fisik yang cocok.', 'image' => $placeholder, 'image_alt' => 'Produk Fisik produk pilihan', 'primary_text' => 'Tanya Stok Produk Fisik', 'primary_url' => landing_page_template_cta_url('saya ingin tanya stok produk fisik.', 'Template Produk Fisik'), 'secondary_text' => 'Lihat Katalog Produk Fisik', 'secondary_url' => '/katalog', 'hero_layout' => 'auto', 'hero_position' => 'right', 'bg_color' => '#f8fafc', 'headline_size' => '44px'],
                    ['type' => 'benefits', 'headline' => 'Pilihan Produk Fisik Lebih Mudah Dibandingkan', 'items' => [['title' => 'Tier jelas', 'text' => 'Ekonomis, medium, dan premium bisa diposisikan sesuai budget.'], ['title' => 'Bisa cek lokasi', 'text' => 'Admin membantu cek area layanan dan area layanan yang relevan.'], ['title' => 'Tombol siap tracking', 'text' => 'Tombol dan form sudah diarahkan untuk kebutuhan promosi.']]],
                    ['type' => 'pricing_cards', 'headline' => 'Pilih Kategori Penawaran', 'items' => [['title' => 'Ekonomis', 'price' => 'Budget hemat', 'features' => ['Cocok untuk mulai konsultasi', 'Cek stok produk fisik ekonomis'], 'button_text' => 'Tanya Ekonomis'], ['title' => 'Medium', 'price' => 'Paling populer', 'features' => ['Pilihan aman keluarga/kelompok', 'Admin bantu rekomendasi'], 'button_text' => 'Tanya Medium'], ['title' => 'Premium', 'price' => 'Pilihan terbaik', 'features' => ['Untuk kebutuhan spesial', 'Cek opsi premium terbaru'], 'button_text' => 'Tanya Premium']], 'button_url' => landing_page_template_cta_url('saya ingin tanya paket produk fisik.', 'Template Produk Fisik Pricing')],
                    ['type' => 'custom_menu', 'engine_internal_links' => true, 'headline' => 'Link Cepat Produk', 'text' => 'Arahkan pengunjung ke halaman pendukung agar SEO dan navigasi kuat.', 'items' => [['title' => 'Katalog Produk Fisik', 'text' => 'Pilihan produk fisik dan produk terkait', 'url' => '/katalog', 'link_url' => '/katalog'], ['title' => 'Paket Produk', 'text' => 'Info paket produk', 'url' => '/paket-produk', 'link_url' => '/paket-produk'], ['title' => 'Artikel Edukasi', 'text' => 'Panduan memilih produk', 'url' => '/artikel', 'link_url' => '/artikel']]],
                    ['type' => 'lead_form', 'headline' => 'Minta Rekomendasi Produk Fisik', 'text' => 'Isi data singkat agar admin bisa bantu cek stok dan opsi yang sesuai.', 'submit_text' => 'Kirim Permintaan', 'success_text' => 'Terima kasih, admin akan follow-up.', 'need_default' => 'Konsultasi Produk Fisik', 'form_name' => 'Form Produk Fisik', 'lead_segment' => 'produk-fisik', 'lead_tags' => 'produk,produk fisik,lp-template', 'lead_priority' => 'hot', 'lead_stage' => 'buyer-intent', 'lead_score' => '75', 'fields' => landing_page_default_form_fields()],
                    ['type' => 'faq', 'headline' => 'FAQ Produk Fisik', 'items' => [['question' => 'Bisa cek stok dulu?', 'answer' => 'Bisa. Admin akan bantu cek stok dan opsi yang relevan.'], ['question' => 'Apakah bisa pilih tier?', 'answer' => 'Bisa. Anda bisa tanya pilihan ekonomis, medium, atau premium.']]],
                ],
            ],
            [
                'id' => 'paket-layanan',
                'name' => 'Paket/Layanan - Promo Praktis',
                'category' => 'Contoh Siap Pakai',
                'description' => 'Template promosi paket/layanan untuk layanan atau produk dengan Tombol cepat dan form follow-up.',
                'page_title' => 'Paket Paket dan Layanan Praktis',
                'slug_base' => 'paket-paket-layanan-praktis',
                'layout_mode' => 'focus',
                'hide_header' => true,
                'hide_footer' => true,
                'hide_floating_wa' => true,
                'show_nav_only' => false,
                'include_seo' => true,
                'include_tracking' => true,
                'meta_title' => 'Paket Paket dan Layanan Praktis untuk Layanan/Produk',
                'meta_description' => 'Template landing page paket dan layanan untuk konsultasi layanan atau produk, Tombol WhatsApp, form lead, paket, FAQ, dan internal link.',
                'meta_keywords' => 'paket layanan, layanan produk, paket layanan',
                'tracking_label' => 'Landing Page Paket Layanan Template',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Paket & Layanan', 'headline' => 'Pilih Paket atau Layanan Jadi Lebih Praktis', 'subheadline' => 'Cocok untuk kebutuhan layanan maupun produk. Admin siap bantu arahkan pilihan sesuai budget dan lokasi.', 'image' => $placeholder, 'image_alt' => 'Paket dan layanan pilihan', 'primary_text' => 'Konsultasi Sekarang', 'primary_url' => landing_page_template_cta_url('saya ingin konsultasi paket/layanan.', 'Template Paket Layanan'), 'secondary_text' => 'Lihat Katalog Paket', 'secondary_url' => '/katalog-paket', 'hero_layout' => 'auto', 'hero_position' => 'right', 'bg_color' => '#f8fafc'],
                    ['type' => 'pain_points', 'headline' => 'Yang sering bikin calon pembeli ragu', 'items' => ['Belum tahu beda pilihan paket dan layanan.', 'Ingin tanya ukuran, budget, dan stok terbaru.', 'Butuh admin yang bisa follow-up cepat.']],
                    ['type' => 'pricing_cards', 'headline' => 'Contoh Kategori Pilihan', 'items' => [['title' => 'Ekonomis', 'price' => 'Mulai konsultasi', 'features' => ['Budget hemat', 'Cocok untuk tanya stok'], 'button_text' => 'Tanya Ekonomis'], ['title' => 'Medium', 'price' => 'Rekomendasi', 'features' => ['Pilihan populer', 'Admin bantu arahan'], 'button_text' => 'Tanya Medium'], ['title' => 'Premium', 'price' => 'Pilihan spesial', 'features' => ['Untuk kebutuhan lebih spesial', 'Cek stok premium'], 'button_text' => 'Tanya Premium']], 'button_url' => landing_page_template_cta_url('saya ingin tanya paket atau layanan.', 'Template Paket Layanan Pricing')],
                    ['type' => 'lead_form', 'headline' => 'Isi Form Konsultasi', 'text' => 'Tulis kebutuhan Anda agar admin bisa follow-up lebih tepat.', 'submit_text' => 'Kirim Form', 'success_text' => 'Terima kasih, admin akan menghubungi Anda.', 'need_default' => 'Konsultasi Paket/Layanan', 'form_name' => 'Form Paket Layanan', 'lead_segment' => 'paket-layanan', 'lead_tags' => 'paket,layanan,lp-template', 'lead_priority' => 'warm', 'lead_stage' => 'new-lead', 'lead_score' => '65', 'fields' => landing_page_default_form_fields()],
                    ['type' => 'faq', 'headline' => 'FAQ Paket/Layanan', 'items' => [['question' => 'Bisa konsultasi jenis produk/layanan?', 'answer' => 'Bisa. Admin akan membantu menjelaskan opsi yang tersedia.'], ['question' => 'Apakah bisa tanya lokasi layanan?', 'answer' => 'Bisa. Sertakan lokasi agar admin bisa cek area layanan.']]],
                ],
            ],
            [
                'id' => 'kontak',
                'name' => 'Area Layanan - Local SEO',
                'category' => 'Contoh Siap Pakai',
                'description' => 'Preset SEO lokal untuk halaman area layanan seperti Jakarta, Bandung, Surabaya, atau Yogyakarta.',
                'page_title' => 'Area Layanan Terdekat',
                'slug_base' => 'kontak-area-layanan',
                'layout_mode' => 'website',
                'hide_header' => false,
                'hide_footer' => false,
                'hide_floating_wa' => false,
                'show_nav_only' => false,
                'include_seo' => true,
                'include_tracking' => true,
                'meta_title' => 'Area Layanan Produk dan Layanan',
                'meta_description' => 'Landing page area layanan dan area layanan produk layanan untuk Jakarta, Bandung, Surabaya, Yogyakarta, dan wilayah pendukung.',
                'meta_keywords' => 'area layanan produk, area layanan jasa, area layanan online, area layanan indonesia',
                'tracking_label' => 'Landing Page Area Layanan Template',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Area Layanan', 'headline' => 'Cek Area Layanan Terdekat', 'subheadline' => 'Gunakan halaman ini untuk menjelaskan cakupan area dan jalur konsultasi calon customer.', 'image' => $placeholder, 'image_alt' => 'Lokasi area layanan produk dan layanan', 'primary_text' => 'Tanya Area Layanan', 'primary_url' => landing_page_template_cta_url('saya ingin tanya area layanan.', 'Template Area Layanan'), 'secondary_text' => 'Lihat Katalog', 'secondary_url' => '/katalog', 'hero_layout' => 'auto', 'hero_position' => 'right'],
                    ['type' => 'free_cards', 'headline' => 'Area yang Bisa Dibantu', 'text' => 'Sesuaikan card ini dengan lokasi operasional.', 'items' => [['title' => 'Jakarta', 'text' => 'Untuk calon customer area Jakarta dan sekitarnya.'], ['title' => 'Bandung', 'text' => 'Untuk calon customer area Bandung dan sekitarnya.'], ['title' => 'Surabaya', 'text' => 'Untuk promosi atau penawaran area Surabaya.'], ['title' => 'Yogyakarta', 'text' => 'Untuk kebutuhan Yogyakarta dan sekitarnya.']]],
                    ['type' => 'media', 'headline' => 'Kenapa lokasi penting?', 'text' => 'Lokasi membantu admin menjelaskan stok, estimasi, dan opsi layanan yang lebih relevan dengan calon customer.', 'image' => $placeholder, 'image_alt' => 'Ilustrasi lokasi layanan', 'button_text' => 'Tanya Lokasi', 'button_url' => landing_page_template_cta_url('saya ingin cek lokasi layanan.', 'Template Lokasi Media'), 'media_layout' => 'auto', 'media_position' => 'left'],
                    ['type' => 'faq', 'headline' => 'FAQ Area Layanan', 'items' => [['question' => 'Apakah bisa tanya area layanan dulu?', 'answer' => 'Bisa. Admin akan cek area dan kebutuhan Anda.'], ['question' => 'Apakah semua lokasi selalu tersedia?', 'answer' => 'Ketersediaan bisa berubah, jadi sebaiknya konsultasi dulu.']]],
                    ['type' => 'cta', 'headline' => 'Cek area layanan sekarang', 'text' => 'Klik tombol untuk bertanya lokasi, stok, dan opsi terbaik.', 'button_text' => 'Chat Admin Lokasi', 'button_url' => landing_page_template_cta_url('saya ingin cek area layanan.', 'Template Lokasi Tombol'), 'bg_color' => '#ecfeff', 'align' => 'center'],
                ],
            ],
            [
                'id' => 'long-copy-layanan-produk',
                'name' => 'Long Copy Layanan/Produk - 15 Section',
                'category' => 'Contoh Siap Pakai',
                'description' => 'Template sales page lengkap: headline, penawaran, cerita, masalah, solusi, bukti, bonus, paket, jaminan, dan FAQ.',
                'page_title' => 'Penawaran Layanan dan Produk Terarah',
                'slug_base' => 'penawaran-layanan-produk-terarah',
                'layout_mode' => 'focus',
                'hide_header' => true,
                'hide_footer' => true,
                'hide_floating_wa' => true,
                'show_nav_only' => false,
                'include_seo' => true,
                'include_tracking' => true,
                'meta_title' => 'Penawaran Layanan dan Produk Terarah - Konsultasi Cepat',
                'meta_description' => 'Template long copy layanan dan produk dengan struktur penjualan lengkap, form lead, Tombol WhatsApp, FAQ, pilihan paket, jaminan, dan link halaman terkait.',
                'meta_keywords' => 'landing page layanan, landing page produk, template long copy',
                'tracking_label' => 'Landing Page Long Copy Template',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Penawaran Terarah', 'headline' => 'Pilihan Layanan/Produk Terasa Lebih Tenang Saat Prosesnya Jelas', 'subheadline' => 'Landing page ini disusun untuk membantu calon customer memahami masalah, solusi, penawaran, bukti, dan cara mulai konsultasi.', 'image' => $placeholder, 'image_alt' => 'Penawaran layanan produk', 'primary_text' => 'Mulai Konsultasi', 'primary_url' => '#form-konsultasi', 'secondary_text' => 'Lihat Alurnya', 'secondary_url' => '#detail', 'hero_layout' => 'auto', 'hero_position' => 'right', 'bg_color' => '#eff6ff', 'headline_size' => '44px'],
                    ['type' => 'pricing_cards', 'headline' => 'Mulai dari Konsultasi Dulu', 'text' => 'Calon customer tidak harus langsung order. Buat langkah awal terasa ringan.', 'items' => [['title' => 'Konsultasi Gratis', 'price' => 'Mulai dari tanya dulu', 'features' => ['Cek kebutuhan', 'Cek budget', 'Cek lokasi'], 'button_text' => 'Saya Mau Konsultasi']]],
                    ['type' => 'text', 'headline' => 'Cerita yang Dekat dengan Customer', 'text' => 'Banyak keluarga ingin menjalankan layanan atau produk dengan baik, tapi sering bingung memilih paket, lokasi, jadwal, dan proses follow-up. Section ini bisa diisi dengan cerita yang dekat dengan kondisi customer.'],
                    ['type' => 'pain_points', 'headline' => 'Masalah yang Sering Dialami', 'items' => ['Masalahnya bukan cuma harga, tapi rasa yakin saat memilih.', 'Calon customer butuh penjelasan yang sederhana dan cepat.', 'Tanpa struktur yang jelas, customer mudah menunda keputusan.']],
                    ['type' => 'text', 'headline' => 'Gambaran Proses yang Lebih Mudah', 'text' => 'Bayangkan prosesnya lebih ringan: customer tahu pilihan, tahu langkah berikutnya, lalu admin bisa follow-up dengan data yang sudah jelas.'],
                    ['type' => 'media', 'headline' => 'Solusi Utama', 'text' => 'Perkenalkan layanan utama, cara konsultasi, dan alasan kenapa prosesnya dibuat praktis.', 'image' => $placeholder, 'image_alt' => 'Solusi layanan', 'button_text' => 'Lihat Solusi', 'button_url' => '#form-konsultasi', 'media_layout' => 'auto', 'media_position' => 'left'],
                    ['type' => 'testimonial', 'headline' => 'Bukti dan Kepercayaan', 'items' => [['title' => 'Admin Responsif', 'text' => 'Gunakan section ini untuk testimoni atau bukti layanan.'], ['title' => 'Proses Jelas', 'text' => 'Tambahkan bukti stok, proses, atau review customer.']]],
                    ['type' => 'free_cards', 'headline' => 'Cara Kerja Layanan', 'text' => 'Jelaskan keunggulan dan cara kerja layanan.', 'items' => [['title' => 'Cek Kebutuhan', 'text' => 'Admin membaca kebutuhan calon customer.'], ['title' => 'Rekomendasi Paket', 'text' => 'Customer diarahkan ke opsi paling relevan.'], ['title' => 'Follow-up Terarah', 'text' => 'Percakapan WA/form jadi lebih siap.']]],
                    ['type' => 'free_cards', 'headline' => 'Alur Pemesanan', 'items' => [['title' => '1. Isi Form', 'text' => 'Customer mengisi data singkat.'], ['title' => '2. Admin Cek', 'text' => 'Admin cek kebutuhan dan opsi.'], ['title' => '3. Follow-up', 'text' => 'Customer mendapat arahan lanjutan.']]],
                    ['type' => 'free_cards', 'headline' => 'Bonus atau Nilai Tambah', 'items' => [['title' => 'Panduan Pilih Paket', 'text' => 'Bonus edukasi agar customer lebih yakin.'], ['title' => 'Konsultasi Lokasi', 'text' => 'Admin bantu cek area layanan.']]],
                    ['type' => 'faq', 'headline' => 'Pertanyaan yang Sering Muncul', 'items' => [['question' => 'Kalau masih ragu bagaimana?', 'answer' => 'Mulai dari konsultasi dulu, belum harus order.'], ['question' => 'Kalau budget terbatas?', 'answer' => 'Admin bisa bantu arahkan opsi yang sesuai budget.']]],
                    ['type' => 'pricing_cards', 'headline' => 'Pilihan Paket', 'items' => [['title' => 'Ekonomis', 'price' => 'Hemat', 'features' => ['Untuk mulai konsultasi'], 'button_text' => 'Tanya Ekonomis'], ['title' => 'Medium', 'price' => 'Populer', 'features' => ['Pilihan seimbang'], 'button_text' => 'Tanya Medium'], ['title' => 'Premium', 'price' => 'Spesial', 'features' => ['Untuk kebutuhan lebih tinggi'], 'button_text' => 'Tanya Premium']], 'button_url' => '#form-konsultasi'],
                    ['type' => 'text', 'headline' => 'Jaminan Layanan', 'text' => 'Tambahkan janji layanan yang aman dan realistis, misalnya admin akan membantu menjelaskan opsi secara transparan sebelum customer mengambil keputusan.'],
                    ['type' => 'cta', 'headline' => 'Ketersediaan Terbatas', 'text' => 'Stok, jadwal, dan slot layanan bisa berubah. Konsultasi sekarang agar admin bisa cek opsi terbaru.', 'button_text' => 'Cek Opsi Terbaru', 'button_url' => '#form-konsultasi', 'bg_color' => '#f8fafc', 'align' => 'center'],
                    ['type' => 'lead_form', 'headline' => 'Isi Form Konsultasi', 'text' => 'Lengkapi data agar admin bisa follow-up sesuai kebutuhan.', 'submit_text' => 'Kirim Konsultasi', 'success_text' => 'Terima kasih, admin akan follow-up.', 'need_default' => 'Konsultasi Long Copy landing page', 'form_name' => 'Form Long Copy', 'lead_segment' => 'long-copy-lp', 'lead_tags' => 'long-copy,lp-template', 'lead_priority' => 'hot', 'lead_stage' => 'new-lead', 'lead_score' => '80', 'fields' => landing_page_default_form_fields()],
                ],
            ],
        ];
    }
}

if (!function_exists('landing_page_builtin_template_find')) {
    function landing_page_builtin_template_find(string $id): ?array
    {
        $needle = trim($id);
        foreach (landing_page_builtin_templates() as $template) {
            if ((string)($template['id'] ?? '') === $needle) {
                return $template;
            }
        }
        return null;
    }
}

if (!function_exists('landing_page_builtin_template_to_page_seed')) {
    function landing_page_builtin_template_to_page_seed(array $template): array
    {
        $title = (string)($template['page_title'] ?? $template['name'] ?? 'Landing Page Template');
        $slugBase = (string)($template['slug_base'] ?? $title);
        return [
            'id' => '',
            'title' => $title,
            'slug' => landing_page_unique_slug(slugify($slugBase . '-' . date('Ymd-His'))),
            'status' => 'draft',
            'layout_mode' => (string)($template['layout_mode'] ?? 'focus'),
            'hide_header' => !empty($template['hide_header']),
            'hide_footer' => !empty($template['hide_footer']),
            'hide_floating_wa' => !empty($template['hide_floating_wa']),
            'show_nav_only' => !empty($template['show_nav_only']),
            'mini_footer_brand' => (string)($template['mini_footer_brand'] ?? ''),
            'mini_footer_text' => (string)($template['mini_footer_text'] ?? ''),
            'mini_footer_bg' => (string)($template['mini_footer_bg'] ?? ''),
            'mini_footer_text_color' => (string)($template['mini_footer_text_color'] ?? ''),
            'mini_footer_brand_color' => (string)($template['mini_footer_brand_color'] ?? ''),
            'mini_footer_text_size' => (string)($template['mini_footer_text_size'] ?? ''),
            'mini_footer_align' => (string)($template['mini_footer_align'] ?? ''),
            'indexable' => false,
            'meta_title' => (string)($template['meta_title'] ?? $title),
            'meta_description' => (string)($template['meta_description'] ?? ''),
            'meta_keywords' => (string)($template['meta_keywords'] ?? ''),
            'og_image' => (string)($template['og_image'] ?? ''),
            'tracking_label' => (string)($template['tracking_label'] ?? $title),
            'blocks' => $template['blocks'] ?? landing_page_default_blocks(),
        ];
    }
}

if (!function_exists('landing_page_preset_sections')) {
    function landing_page_preset_sections(): array
    {
        $placeholder = function_exists('asset') ? asset('images/placeholder-product.svg') : '/assets/images/placeholder-product.svg';
        return [
            'strong-headline' => ['label' => 'Strong Headline', 'group' => 'Long Copy', 'block' => ['type' => 'hero_offer', 'eyebrow' => 'Penawaran Utama', 'headline' => 'Headline Besar yang Menyentuh Kebutuhan Customer', 'subheadline' => 'Tulis janji utama yang spesifik, realistis, dan mudah dipahami.', 'image' => $placeholder, 'image_alt' => 'Visual penawaran', 'primary_text' => 'Mulai Konsultasi', 'primary_url' => '#form-konsultasi', 'hero_layout' => 'auto', 'hero_position' => 'right', 'bg_color' => '#eff6ff']],
            'low-barrier-offer' => ['label' => 'Konsultasi Awal', 'group' => 'Long Copy', 'block' => ['type' => 'pricing_cards', 'headline' => 'Mulai dari Konsultasi Dulu', 'text' => 'Buat langkah pertama terasa ringan dan tidak menakutkan.', 'items' => [['title' => 'Konsultasi Awal', 'price' => 'Gratis / ringan', 'features' => ['Tanya kebutuhan', 'Cek opsi', 'Belum harus order'], 'button_text' => 'Saya Mau Konsultasi']], 'button_url' => '#form-konsultasi']],
            'empathy-story' => ['label' => 'Empathy Story', 'group' => 'Long Copy', 'block' => ['type' => 'text', 'headline' => 'Cerita yang Dekat dengan Customer', 'text' => 'Tulis cerita singkat yang membuat pengunjung merasa dipahami sebelum Anda menawarkan solusi.']],
            'problem-reframe' => ['label' => 'Masalah yang Sering Dialami', 'group' => 'Long Copy', 'block' => ['type' => 'pain_points', 'headline' => 'Masalah Utamanya Bukan Sekadar Harga', 'items' => ['Calon customer butuh rasa yakin sebelum memilih.', 'Informasi yang tidak jelas membuat keputusan tertunda.', 'Follow-up yang lambat membuat peluang hilang.']]],
            'future-pacing' => ['label' => 'Gambaran Proses yang Lebih Mudah', 'group' => 'Long Copy', 'block' => ['type' => 'text', 'headline' => 'Bayangkan Prosesnya Lebih Mudah', 'text' => 'Ajak pengunjung membayangkan kondisi setelah memakai layanan: lebih tenang, lebih jelas, dan tahu langkah berikutnya.']],
            'unique-mechanism' => ['label' => 'Cara Kerja Layanan', 'group' => 'Long Copy', 'block' => ['type' => 'free_cards', 'headline' => 'Cara Kerja Layanan', 'items' => [['title' => 'Cek kebutuhan', 'text' => 'Data awal membantu admin memahami kebutuhan.'], ['title' => 'Rekomendasi terarah', 'text' => 'Pilihan disesuaikan dengan budget/lokasi.'], ['title' => 'Follow-up cepat', 'text' => 'Tombol dan form membuat komunikasi lebih siap.']]]],
            'how-it-works' => ['label' => 'Alur Pemesanan', 'group' => 'Long Copy', 'block' => ['type' => 'free_cards', 'headline' => 'Cara Kerja', 'items' => [['title' => '1. Isi Form', 'text' => 'Tulis kebutuhan singkat.'], ['title' => '2. Admin Cek', 'text' => 'Admin cek opsi relevan.'], ['title' => '3. Follow-up', 'text' => 'Lanjut konsultasi via WhatsApp.']]]],
            'bonus-stack' => ['label' => 'Bonus Stack', 'group' => 'Long Copy', 'block' => ['type' => 'free_cards', 'headline' => 'Bonus yang Didapat', 'items' => [['title' => 'Panduan pilihan', 'text' => 'Bantu customer memahami opsi.'], ['title' => 'Konsultasi lokasi', 'text' => 'Bantu cek area layanan.']]]],
            'guarantee' => ['label' => 'Jaminan Layanan', 'group' => 'Long Copy', 'block' => ['type' => 'text', 'headline' => 'Garansi / Janji Layanan', 'text' => 'Tulis janji yang realistis: admin akan menjelaskan opsi dengan transparan sebelum customer mengambil keputusan.']],
            'final-faq-cta' => ['label' => 'FAQ + Tombol Closer', 'group' => 'Closing', 'block' => ['type' => 'faq', 'headline' => 'Pertanyaan Sebelum Mulai', 'items' => [['question' => 'Apakah bisa konsultasi dulu?', 'answer' => 'Bisa. Mulai dari tanya kebutuhan dulu.'], ['question' => 'Apakah wajib order?', 'answer' => 'Tidak. Admin akan bantu jelaskan opsi terlebih dahulu.']]]],
            'internal-links' => ['label' => 'Internal Link SEO', 'group' => 'SEO', 'block' => ['type' => 'custom_menu', 'engine_internal_links' => true, 'headline' => 'Link Cepat & Rekomendasi', 'text' => 'Arahkan pengunjung ke halaman pendukung agar SEO dan navigasi lebih kuat.', 'items' => [['title' => 'Katalog Produk Fisik', 'text' => 'Pilihan produk fisik dan produk terkait', 'url' => '/katalog', 'link_url' => '/katalog'], ['title' => 'Paket Layanan', 'text' => 'Info layanan dan paket', 'url' => '/paket-layanan', 'link_url' => '/paket-layanan'], ['title' => 'Area Layanan', 'text' => 'Cek area layanan Jakarta, Bandung, Surabaya, dan Yogyakarta', 'url' => '/kontak', 'link_url' => '/kontak'], ['title' => 'Artikel Edukasi', 'text' => 'Baca panduan produk dan layanan', 'url' => '/artikel', 'link_url' => '/artikel']]]],
        ];
    }
}

if (!function_exists('landing_page_smart_preset_packs')) {
    function landing_page_smart_preset_packs(): array
    {
        $placeholder = function_exists('asset') ? asset('images/placeholder-product.svg') : '/assets/images/placeholder-product.svg';
        $servicePlaceholder = function_exists('asset') ? asset('images/placeholder-service.svg') : '/assets/images/placeholder-service.svg';
        $waConsult = landing_page_template_cta_url('Halo admin, saya ingin konsultasi dari landing page.', 'LP Smart Preset');
        $formFields = function_exists('landing_page_default_form_fields') ? landing_page_default_form_fields() : [];

        return [
            'sales-page-produk' => [
                'label' => 'Sales Page Produk',
                'category' => 'Produk Fisik / Digital',
                'description' => 'Susunan cepat untuk promosi produk: hero, masalah, benefit, paket, trust, FAQ, dan CTA penutup.',
                'suggested_title' => 'Landing Page Penawaran Produk',
                'suggested_slug' => 'landing-page-penawaran-produk',
                'tracking_label' => 'LP Sales Page Produk',
                'meta_title' => 'Penawaran Produk Pilihan - Konsultasi dan Order Mudah',
                'meta_description' => 'Landing page penawaran produk dengan benefit, paket harga, testimoni, FAQ, dan tombol konsultasi WhatsApp.',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Penawaran Produk', 'headline' => 'Produk Pilihan untuk Kebutuhan Anda', 'subheadline' => 'Jelaskan produk utama, manfaat terbesar, dan alasan kenapa pengunjung perlu mengambil tindakan sekarang.', 'image' => $placeholder, 'image_alt' => 'Produk unggulan', 'primary_text' => 'Tanya Produk Ini', 'primary_url' => $waConsult, 'secondary_text' => 'Lihat Paket', 'secondary_url' => '#paket-harga', 'hero_layout' => 'auto', 'hero_position' => 'right', 'bg_color' => '#eff6ff'],
                    ['type' => 'pain_points', 'headline' => 'Masalah yang Sering Dialami Customer', 'items' => ['Bingung memilih produk yang paling sesuai kebutuhan.', 'Takut salah beli karena informasi kurang jelas.', 'Butuh admin yang bisa memberi rekomendasi dengan cepat.']],
                    ['type' => 'benefits', 'headline' => 'Kenapa Produk Ini Layak Dipilih?', 'items' => [['title' => 'Manfaat jelas', 'text' => 'Tulis manfaat utama yang langsung terasa oleh customer.'], ['title' => 'Pilihan fleksibel', 'text' => 'Cocok untuk berbagai kebutuhan dan budget.'], ['title' => 'Admin siap bantu', 'text' => 'Pengunjung bisa lanjut konsultasi sebelum order.']]],
                    ['type' => 'pricing_cards', 'headline' => 'Pilih Paket yang Paling Sesuai', 'items' => [['title' => 'Basic', 'price' => 'Mulai hemat', 'features' => ['Cocok untuk kebutuhan awal', 'Bisa konsultasi sebelum order'], 'button_text' => 'Tanya Basic'], ['title' => 'Rekomendasi', 'price' => 'Paling populer', 'features' => ['Pilihan aman untuk mayoritas customer', 'Benefit lebih lengkap'], 'button_text' => 'Tanya Paket Rekomendasi'], ['title' => 'Premium', 'price' => 'Terbaik', 'features' => ['Untuk kebutuhan lebih serius', 'Support dan benefit maksimal'], 'button_text' => 'Tanya Premium']], 'button_url' => $waConsult, 'bg_color' => '#f8fafc'],
                    ['type' => 'testimonial', 'headline' => 'Apa Kata Customer?', 'items' => [['title' => 'Customer A', 'text' => 'Produk sesuai kebutuhan dan admin responsif menjelaskan pilihan.'], ['title' => 'Customer B', 'text' => 'Proses tanya sampai order jadi lebih mudah.']]],
                    ['type' => 'faq', 'headline' => 'Pertanyaan Sebelum Order', 'items' => [['question' => 'Apakah bisa konsultasi dulu?', 'answer' => 'Bisa, admin akan membantu menjelaskan pilihan yang sesuai.'], ['question' => 'Apakah wajib langsung order?', 'answer' => 'Tidak, customer bisa bertanya dulu sebelum mengambil keputusan.'], ['question' => 'Bagaimana cara lanjut order?', 'answer' => 'Klik tombol WhatsApp atau isi form jika tersedia.']]],
                    ['type' => 'cta', 'headline' => 'Mau Dibantu Pilih Produk?', 'text' => 'Klik tombol di bawah agar admin bisa bantu rekomendasikan pilihan terbaik.', 'button_text' => 'Chat Admin Sekarang', 'button_url' => $waConsult, 'bg_color' => '#eff6ff', 'align' => 'center'],
                ],
            ],
            'jasa-konsultasi' => [
                'label' => 'Jasa / Konsultasi',
                'category' => 'Layanan',
                'description' => 'Cocok untuk jasa lokal, konsultasi, agency, servis, klinik, kursus, dan bisnis layanan.',
                'suggested_title' => 'Landing Page Jasa dan Konsultasi',
                'suggested_slug' => 'landing-page-jasa-konsultasi',
                'tracking_label' => 'LP Jasa Konsultasi',
                'meta_title' => 'Jasa dan Konsultasi - Booking Lebih Mudah',
                'meta_description' => 'Landing page jasa dengan alur konsultasi, benefit layanan, form lead, FAQ, dan CTA WhatsApp.',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Layanan Profesional', 'headline' => 'Bantu Customer Menyelesaikan Masalah dengan Lebih Mudah', 'subheadline' => 'Tampilkan jasa utama, hasil yang dijanjikan, dan ajakan konsultasi awal yang ringan.', 'image' => $servicePlaceholder, 'image_alt' => 'Layanan profesional', 'primary_text' => 'Konsultasi Layanan', 'primary_url' => '#form-konsultasi', 'secondary_text' => 'Chat WhatsApp', 'secondary_url' => $waConsult, 'bg_color' => '#f8fafc'],
                    ['type' => 'pain_points', 'headline' => 'Biasanya Customer Datang Karena Ini', 'items' => ['Butuh solusi tapi belum tahu harus mulai dari mana.', 'Perlu penjelasan paket layanan yang mudah dipahami.', 'Ingin respon cepat sebelum membuat keputusan.']],
                    ['type' => 'free_cards', 'headline' => 'Alur Kerja Layanan', 'items' => [['title' => '1. Ceritakan kebutuhan', 'text' => 'Customer mengisi form atau chat admin.'], ['title' => '2. Analisa awal', 'text' => 'Admin memahami kebutuhan dan memberi arahan awal.'], ['title' => '3. Rekomendasi layanan', 'text' => 'Customer mendapat opsi layanan yang paling relevan.']]],
                    ['type' => 'benefits', 'headline' => 'Keunggulan Layanan', 'items' => [['title' => 'Lebih terarah', 'text' => 'Kebutuhan customer dipahami sebelum ditawarkan paket.'], ['title' => 'Komunikasi jelas', 'text' => 'Alur, harga, dan follow-up lebih mudah dijelaskan.'], ['title' => 'Siap tracking', 'text' => 'Cocok untuk iklan WhatsApp, Google Ads, dan campaign.']]],
                    ['type' => 'lead_form', 'headline' => 'Form Konsultasi Layanan', 'text' => 'Isi data singkat ini agar admin bisa menyiapkan rekomendasi layanan.', 'submit_text' => 'Kirim Konsultasi', 'success_text' => 'Terima kasih, data konsultasi sudah masuk. Admin akan follow-up.', 'need_default' => 'Konsultasi layanan', 'form_name' => 'Form Konsultasi Jasa', 'lead_segment' => 'jasa-konsultasi', 'lead_tags' => 'jasa,konsultasi,lp-preset', 'lead_priority' => 'warm', 'lead_stage' => 'new-lead', 'lead_score' => '60', 'consent_text' => 'Saya bersedia dihubungi admin terkait konsultasi layanan.', 'fields' => $formFields, 'bg_color' => '#f8fafc'],
                    ['type' => 'faq', 'headline' => 'FAQ Layanan', 'items' => [['question' => 'Apakah bisa konsultasi dulu?', 'answer' => 'Bisa, form ini dibuat untuk konsultasi awal.'], ['question' => 'Apakah langsung mendapat harga?', 'answer' => 'Admin bisa memberi estimasi setelah memahami kebutuhan.']]],
                    ['type' => 'cta', 'headline' => 'Siap Konsultasi Sekarang?', 'text' => 'Mulai dari cerita kebutuhan dulu, admin akan bantu arahkan.', 'button_text' => 'Mulai Konsultasi', 'button_url' => '#form-konsultasi', 'bg_color' => '#eff6ff', 'align' => 'center'],
                ],
            ],
            'lead-magnet' => [
                'label' => 'Lead Magnet',
                'category' => 'List Building',
                'description' => 'Untuk e-book, voucher, katalog PDF, checklist, mini class, atau penawaran gratis yang mengumpulkan lead.',
                'suggested_title' => 'Landing Page Lead Magnet',
                'suggested_slug' => 'landing-page-lead-magnet',
                'tracking_label' => 'LP Lead Magnet',
                'meta_title' => 'Download Panduan Gratis - Isi Form dan Dapatkan Akses',
                'meta_description' => 'Landing page lead magnet dengan benefit download, form, social proof, FAQ, dan CTA follow-up.',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Gratis untuk Anda', 'headline' => 'Dapatkan Panduan / Bonus Gratis dari Kami', 'subheadline' => 'Tawarkan sesuatu yang bernilai agar pengunjung mau mengisi form dan masuk database lead.', 'image' => $placeholder, 'image_alt' => 'Lead magnet gratis', 'primary_text' => 'Ambil Gratis', 'primary_url' => '#form-lead-magnet', 'secondary_text' => 'Lihat Benefit', 'secondary_url' => '#benefit', 'bg_color' => '#eff6ff'],
                    ['type' => 'benefits', 'headline' => 'Apa yang Akan Didapat?', 'items' => [['title' => 'Panduan praktis', 'text' => 'Isi materi mudah dipahami dan langsung bisa digunakan.'], ['title' => 'Hemat waktu', 'text' => 'Customer tidak perlu mencari informasi dari banyak tempat.'], ['title' => 'Bonus follow-up', 'text' => 'Admin bisa membantu menjawab pertanyaan setelah form dikirim.']]],
                    ['type' => 'lead_form', 'headline' => 'Ambil Bonus Gratis', 'text' => 'Isi data singkat ini untuk mendapatkan akses atau follow-up dari admin.', 'submit_text' => 'Kirim & Ambil Bonus', 'success_text' => 'Terima kasih, data sudah masuk. Admin akan mengirimkan akses/bonus.', 'need_default' => 'Minta lead magnet', 'form_name' => 'Form Lead Magnet', 'lead_segment' => 'lead-magnet', 'lead_tags' => 'lead-magnet,download,lp-preset', 'lead_priority' => 'warm', 'lead_stage' => 'new-lead', 'lead_score' => '55', 'consent_text' => 'Saya bersedia dihubungi admin terkait materi dan penawaran lanjutan.', 'fields' => $formFields, 'bg_color' => '#f8fafc'],
                    ['type' => 'free_cards', 'headline' => 'Cocok untuk Siapa?', 'items' => [['title' => 'Pemula', 'text' => 'Butuh gambaran awal sebelum membeli atau konsultasi.'], ['title' => 'Customer serius', 'text' => 'Sedang membandingkan pilihan dan butuh arahan.'], ['title' => 'Calon buyer', 'text' => 'Butuh bukti bahwa bisnis Anda bisa dipercaya.']]],
                    ['type' => 'faq', 'headline' => 'Pertanyaan Tentang Bonus', 'items' => [['question' => 'Apakah benar gratis?', 'answer' => 'Ya, admin dapat mengirimkan materi/akses sesuai pengaturan bisnis.'], ['question' => 'Apakah data saya aman?', 'answer' => 'Gunakan teks consent dan kebijakan privasi sesuai kebutuhan bisnis.']]],
                    ['type' => 'cta', 'headline' => 'Ambil Bonusnya Sekarang', 'text' => 'Isi form agar admin bisa mengirimkan akses dan follow-up.', 'button_text' => 'Isi Form', 'button_url' => '#form-lead-magnet', 'bg_color' => '#eff6ff', 'align' => 'center'],
                ],
            ],
            'promo-event' => [
                'label' => 'Event / Promo',
                'category' => 'Campaign',
                'description' => 'Untuk promo musiman, launching, webinar, event lokal, diskon terbatas, atau campaign cepat.',
                'suggested_title' => 'Landing Page Event dan Promo',
                'suggested_slug' => 'landing-page-event-promo',
                'tracking_label' => 'LP Event Promo',
                'meta_title' => 'Event dan Promo Terbatas - Daftar atau Konsultasi Sekarang',
                'meta_description' => 'Landing page event dan promo dengan highlight penawaran, bonus, paket, FAQ, dan CTA cepat.',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Promo Terbatas', 'headline' => 'Promo Spesial untuk Periode Ini', 'subheadline' => 'Tulis alasan promo ini penting, batas waktu, bonus, dan ajakan action yang jelas.', 'image' => $placeholder, 'image_alt' => 'Promo terbatas', 'primary_text' => 'Ambil Promo', 'primary_url' => $waConsult, 'secondary_text' => 'Lihat Detail', 'secondary_url' => '#detail-promo', 'bg_color' => '#fff7ed'],
                    ['type' => 'countdown_timer', 'headline' => 'Promo Berakhir Dalam', 'text' => 'Gunakan timer untuk membantu calon pembeli tidak menunda keputusan.', 'countdown_deadline' => date('Y-m-d\TH:i', strtotime('+3 days')), 'countdown_timezone' => 'WIB', 'expired_text' => 'Promo sudah berakhir. Silakan hubungi admin untuk promo terbaru.', 'button_text' => 'Ambil Promo Sekarang', 'button_url' => $waConsult, 'bg_color' => '#fff7ed', 'accent_color' => '#f59e0b', 'align' => 'center'],
                    ['type' => 'free_cards', 'headline' => 'Highlight Promo', 'items' => [['title' => 'Bonus terbatas', 'text' => 'Tulis bonus yang hanya berlaku selama campaign.'], ['title' => 'Kuota terbatas', 'text' => 'Jelaskan batas kuota, jadwal, atau periode promo.'], ['title' => 'Action cepat', 'text' => 'Arahkan pengunjung untuk chat atau isi form sekarang.']]],
                    ['type' => 'pricing_cards', 'headline' => 'Pilihan Promo', 'items' => [['title' => 'Early Bird', 'price' => 'Harga spesial', 'features' => ['Untuk pendaftar awal', 'Bonus campaign'], 'button_text' => 'Ambil Early Bird'], ['title' => 'Paket Utama', 'price' => 'Rekomendasi', 'features' => ['Benefit lengkap', 'Cocok untuk mayoritas customer'], 'button_text' => 'Ambil Paket Utama']], 'button_url' => $waConsult],
                    ['type' => 'testimonial', 'headline' => 'Bukti dan Kepercayaan', 'items' => [['title' => 'Customer puas', 'text' => 'Tulis bukti singkat dari customer atau hasil campaign sebelumnya.'], ['title' => 'Proses jelas', 'text' => 'Admin menjelaskan detail sebelum customer memutuskan.']]],
                    ['type' => 'faq', 'headline' => 'FAQ Promo', 'items' => [['question' => 'Sampai kapan promo berlaku?', 'answer' => 'Tulis periode promo atau arahkan customer untuk cek kuota terbaru.'], ['question' => 'Apakah bisa tanya dulu?', 'answer' => 'Bisa, klik tombol WhatsApp untuk konsultasi cepat.']]],
                    ['type' => 'cta', 'headline' => 'Jangan Lewatkan Promo Ini', 'text' => 'Klik tombol sekarang sebelum periode atau kuota promo habis.', 'button_text' => 'Chat Admin Promo', 'button_url' => $waConsult, 'bg_color' => '#fff7ed', 'align' => 'center'],
                ],
            ],
            'personal-branding' => [
                'label' => 'Personal Branding',
                'category' => 'Profil / Portfolio',
                'description' => 'Untuk mentor, kreator, konsultan, freelancer, portofolio, dan program personal brand.',
                'suggested_title' => 'Landing Page Personal Branding',
                'suggested_slug' => 'landing-page-personal-branding',
                'tracking_label' => 'LP Personal Branding',
                'meta_title' => 'Profil Profesional dan Program - Konsultasi Sekarang',
                'meta_description' => 'Landing page personal branding dengan profil, keahlian, portfolio, testimoni, program, dan form konsultasi.',
                'blocks' => [
                    ['type' => 'hero_offer', 'eyebrow' => 'Profil Profesional', 'headline' => 'Bantu Audiens Mengenal Siapa Anda dan Apa yang Bisa Anda Bantu', 'subheadline' => 'Ceritakan positioning, keahlian, dan alasan audiens perlu percaya untuk konsultasi atau bekerja sama.', 'image' => $servicePlaceholder, 'image_alt' => 'Profil profesional', 'primary_text' => 'Konsultasi / Kerja Sama', 'primary_url' => '#form-konsultasi', 'secondary_text' => 'Lihat Program', 'secondary_url' => '#program'],
                    ['type' => 'free_cards', 'headline' => 'Area Keahlian', 'items' => [['title' => 'Keahlian utama', 'text' => 'Tulis bidang yang paling kuat dan relevan.'], ['title' => 'Pengalaman', 'text' => 'Tulis pengalaman atau pencapaian yang membangun trust.'], ['title' => 'Cara membantu', 'text' => 'Jelaskan cara Anda membantu audiens mencapai tujuan.']]],
                    ['type' => 'media', 'headline' => 'Portfolio / Bukti Kerja', 'text' => 'Tambahkan foto, karya, screenshot, atau video singkat sebagai bukti kredibilitas.', 'image' => $servicePlaceholder, 'image_alt' => 'Portfolio personal branding', 'media_layout' => 'auto', 'media_position' => 'left'],
                    ['type' => 'testimonial', 'headline' => 'Apa Kata Mereka?', 'items' => [['title' => 'Klien / Peserta A', 'text' => 'Tulis testimoni singkat yang relevan dengan program atau layanan.'], ['title' => 'Klien / Peserta B', 'text' => 'Tampilkan hasil atau pengalaman positif.']]],
                    ['type' => 'pricing_cards', 'headline' => 'Program / Layanan', 'items' => [['title' => 'Konsultasi 1-on-1', 'price' => 'By request', 'features' => ['Sesi personal', 'Arahan sesuai kebutuhan'], 'button_text' => 'Tanya Konsultasi'], ['title' => 'Program / Mentoring', 'price' => 'Paket khusus', 'features' => ['Pendampingan lebih terstruktur', 'Cocok untuk progres jangka panjang'], 'button_text' => 'Tanya Program']], 'button_url' => '#form-konsultasi'],
                    ['type' => 'lead_form', 'headline' => 'Ajukan Konsultasi / Kerja Sama', 'text' => 'Isi data singkat agar bisa dihubungi untuk diskusi lebih lanjut.', 'submit_text' => 'Kirim Pengajuan', 'success_text' => 'Terima kasih, data sudah masuk. Admin akan follow-up.', 'need_default' => 'Konsultasi personal branding', 'form_name' => 'Form Personal Branding', 'lead_segment' => 'personal-branding', 'lead_tags' => 'personal-branding,portfolio,lp-preset', 'lead_priority' => 'warm', 'lead_stage' => 'new-lead', 'lead_score' => '60', 'fields' => $formFields],
                    ['type' => 'cta', 'headline' => 'Tertarik untuk Diskusi Lebih Lanjut?', 'text' => 'Mulai dari konsultasi awal atau kerja sama sederhana.', 'button_text' => 'Kirim Pengajuan', 'button_url' => '#form-konsultasi', 'bg_color' => '#eff6ff', 'align' => 'center'],
                ],
            ],
        ];
    }
}

if (!function_exists('landing_page_allowed_block_types')) {
    function landing_page_allowed_block_types(): array
    {
        return [
            'hero_offer',
            'pain_points',
            'benefits',
            'product_highlight',
            'pricing_cards',
            'countdown_timer',
            'testimonial',
            'faq',
            'lead_form',
            'custom_menu',
            'media',
            'free_cards',
            'cta',
            'text',
            'html_block',
        ];
    }
}


if (!function_exists('landing_page_apply_lp_optimization_defaults')) {
    function landing_page_apply_lp_optimization_defaults(array $blocks): array
    {
        $map = [
            'hero_offer' => ['block_goal' => 'awareness', 'cta_role' => 'primary', 'section_effect' => 'gradient-glow', 'animation_style' => 'fade-up'],
            'pain_points' => ['block_goal' => 'awareness', 'animation_style' => 'fade-up'],
            'benefits' => ['block_goal' => 'trust', 'section_effect' => 'soft-card', 'animation_style' => 'fade-up'],
            'product_highlight' => ['block_goal' => 'trust', 'section_effect' => 'soft-card', 'animation_style' => 'fade-up'],
            'testimonial' => ['block_goal' => 'trust', 'section_effect' => 'soft-card', 'animation_style' => 'fade-up'],
            'pricing_cards' => ['block_goal' => 'offer', 'cta_role' => 'pricing', 'section_effect' => 'soft-card', 'animation_style' => 'zoom-soft'],
            'countdown_timer' => ['block_goal' => 'offer', 'cta_role' => 'primary', 'section_effect' => 'gradient-glow', 'animation_style' => 'zoom-soft'],
            'faq' => ['block_goal' => 'trust', 'animation_style' => 'fade'],
            'lead_form' => ['block_goal' => 'lead', 'cta_role' => 'form', 'section_effect' => 'soft-card', 'animation_style' => 'fade-up'],
            'cta' => ['block_goal' => 'closing', 'cta_role' => 'closing', 'section_effect' => 'gradient-glow', 'animation_style' => 'zoom-soft'],
            'custom_menu' => ['block_goal' => 'trust', 'animation_style' => 'fade'],
            'media' => ['block_goal' => 'trust', 'animation_style' => 'slide-left'],
            'free_cards' => ['block_goal' => 'trust', 'animation_style' => 'fade-up'],
            'text' => ['block_goal' => 'trust', 'animation_style' => 'fade'],
            'html_block' => ['block_goal' => 'trust', 'animation_style' => 'fade'],
        ];

        foreach ($blocks as &$block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string)($block['type'] ?? 'text');
            foreach (($map[$type] ?? []) as $key => $value) {
                if (!array_key_exists($key, $block) || $block[$key] === '') {
                    $block[$key] = $value;
                }
            }
        }
        unset($block);

        return $blocks;
    }
}

if (!function_exists('landing_page_default_blocks')) {
    function landing_page_default_blocks(): array
    {
        $blocks = [
            [
                'type' => 'hero_offer',
                'eyebrow' => 'Promo Terbatas',
                'headline' => 'Paket Layanan Praktis untuk Keluarga Muslim',
                'subheadline' => 'Konsultasi kebutuhan layanan, pilih paket, dan dapatkan arahan admin melalui WhatsApp tanpa ribet.',
                'image' => asset('images/placeholder-product.svg'),
                'image_alt' => 'Ilustrasi paket layanan dan produk',
                'primary_text' => 'Konsultasi WhatsApp',
                'primary_url' => wa_link_contextual('saya ingin konsultasi paket layanan.', ['sumber' => 'Landing Page Builder', 'title' => 'Hero Tombol']),
                'secondary_text' => 'Lihat Katalog',
                'secondary_url' => url('katalog'),
            ],
            [
                'type' => 'pain_points',
                'headline' => 'Sering bingung pilih paket yang pas?',
                'items' => [
                    'Takut salah pilih spesifikasi atau jenis produk/layanan.',
                    'Butuh admin yang cepat menjelaskan stok dan jadwal.',
                    'Ingin proses lebih praktis dari konsultasi sampai pembayaran.',
                ],
            ],
            [
                'type' => 'benefits',
                'headline' => 'Kenapa pesan lewat kami?',
                'items' => [
                    ['title' => 'Katalog jelas', 'text' => 'Produk, tier, lokasi, dan harga disusun agar mudah dibandingkan.'],
                    ['title' => 'Konsultasi cepat', 'text' => 'Tombol WhatsApp diarahkan langsung ke admin dengan konteks halaman.'],
                    ['title' => 'Siap tracking iklan', 'text' => 'Landing page sudah masuk sistem event tracking website.'],
                ],
            ],
            [
                'type' => 'pricing_cards',
                'headline' => 'Contoh paket penawaran',
                'items' => [
                    ['title' => 'Ekonomis', 'price' => 'Mulai konsultasi', 'features' => ['Cocok untuk budget hemat', 'Bisa tanya stok terbaru'], 'button_text' => 'Tanya Paket Ekonomis'],
                    ['title' => 'Medium', 'price' => 'Rekomendasi', 'features' => ['Pilihan populer keluarga', 'Komunikasi cepat via WA'], 'button_text' => 'Tanya Paket Medium'],
                    ['title' => 'Premium', 'price' => 'Pilihan terbaik', 'features' => ['Untuk kebutuhan lebih spesial', 'Bisa cek opsi terbaik'], 'button_text' => 'Tanya Paket Premium'],
                ],
                'button_url' => wa_link_contextual('saya ingin tanya pilihan paket.', ['sumber' => 'Landing Page Pricing']),
            ],
            [
                'type' => 'faq',
                'headline' => 'Pertanyaan umum',
                'items' => [
                    ['question' => 'Apakah bisa konsultasi dulu?', 'answer' => 'Bisa. Admin akan membantu menjelaskan pilihan paket dan ketersediaan stok.'],
                    ['question' => 'Apakah halaman ini bisa dipakai untuk iklan?', 'answer' => 'Bisa. Landing page dibuat fokus ke penawaran, Tombol, SEO, dan tracking.'],
                ],
            ],

            [
                'type' => 'lead_form',
                'headline' => 'Mau dibantu pilih paket yang paling cocok?',
                'text' => 'Isi form singkat ini. Admin akan follow-up via WhatsApp dan data bisa masuk ke Mailketing/Fonnte sesuai pengaturan integrasi.',
                'submit_text' => 'Kirim Form Konsultasi',
                'success_text' => 'Terima kasih, form sudah masuk. Admin akan segera menghubungi Anda.',
                'need_default' => 'Konsultasi Landing Page',
                'mailketing_list_id' => '',
                'form_name' => 'Form Konsultasi Landing Page',
                'lead_segment' => 'lp-consultation',
                'lead_tags' => 'landing-page,consultation',
                'lead_priority' => 'warm',
                'lead_stage' => 'new-lead',
                'lead_score' => '50',
                'consent_text' => 'Saya bersedia dihubungi admin melalui WhatsApp/telepon/email terkait penawaran ini.',
                'fields' => landing_page_default_form_fields(),
            ],
            [
                'type' => 'cta',
                'headline' => 'Siap konsultasi kebutuhan Anda?',
                'text' => 'Klik tombol di bawah agar admin bisa bantu rekomendasikan paket paling sesuai.',
                'button_text' => 'Chat Admin Sekarang',
                'button_url' => wa_link_contextual('saya ingin konsultasi dari landing page.', ['sumber' => 'Landing Page Final Tombol']),
            ],
        ];

        return landing_page_apply_lp_optimization_defaults($blocks);
    }
}

if (!function_exists('landing_page_seed_pages')) {
    function landing_page_seed_pages(): array
    {
        $now = date('c');
        return [
            [
                'id' => 'lp_seed_layanan_area-lokal',
                'title' => 'Promo Paket Layanan Lokal',
                'slug' => 'promo-paket-layanan-area-lokal',
                'status' => 'published',
                'layout_mode' => 'focus',
                'hide_header' => true,
                'hide_footer' => true,
                'hide_floating_wa' => true,
                'show_nav_only' => false,
                'indexable' => false,
                'meta_title' => 'Promo Paket Layanan Lokal - Konsultasi Cepat',
                'meta_description' => 'Landing page promo paket layanan Area Lokal yang fokus untuk traffic iklan, Tombol WhatsApp, dan tracking conversion.',
                'meta_keywords' => 'promo layanan area-lokal, paket layanan area-lokal, layanan praktis',
                'og_image' => DEFAULT_OG_IMAGE,
                'tracking_label' => 'Landing Page Promo Layanan Lokal',
                'blocks' => landing_page_default_blocks(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];
    }
}

if (!function_exists('landing_page_file_read_raw')) {
    function landing_page_file_read_raw(): array
    {
        $file = landing_page_storage_path();
        if (!is_file($file)) {
            return landing_page_seed_pages();
        }

        $decoded = json_decode((string)@file_get_contents($file), true);
        if (!is_array($decoded)) {
            return landing_page_seed_pages();
        }

        if (isset($decoded['pages']) && is_array($decoded['pages'])) {
            return array_values(array_filter($decoded['pages'], 'is_array'));
        }

        return array_values(array_filter($decoded, 'is_array'));
    }
}

if (!function_exists('landing_page_read_raw')) {
    function landing_page_read_raw(): array
    {
        if (function_exists('storage_adapter_mysql_read_landing_pages') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('landing_pages')) {
            $mysqlPages = storage_adapter_mysql_read_landing_pages();
            if (is_array($mysqlPages) && ($mysqlPages || !(function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled()))) {
                return array_values(array_filter($mysqlPages, 'is_array'));
            }
        }

        return landing_page_file_read_raw();
    }
}

if (!function_exists('landing_page_write_raw')) {
    function landing_page_write_raw(array $pages): bool
    {
        $file = landing_page_storage_path();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = [
            'version' => APP_VERSION,
            'updated_at' => date('c'),
            'pages' => array_values($pages),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }

        $fileOk = @file_put_contents($file, $json, LOCK_EX) !== false;
        if (function_exists('storage_adapter_mysql_replace_landing_pages') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('landing_pages')) {
            $mysqlOk = storage_adapter_mysql_replace_landing_pages($pages);
            if (!$mysqlOk && !(function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled())) {
                return false;
            }
            return $mysqlOk || $fileOk;
        }

        return $fileOk;
    }
}

if (!function_exists('landing_page_normalize_status')) {
    function landing_page_normalize_status(string $status): string
    {
        return in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';
    }
}

if (!function_exists('landing_page_clean_url')) {
    function landing_page_clean_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return sanitize_url($url);
        }
        if (preg_match('#^(assets|uploads|media)/#i', $url) === 1) {
            return '/' . ltrim($url, '/');
        }
        if (preg_match('#^(images|img)/#i', $url) === 1) {
            return asset($url);
        }
        return '';
    }
}

if (!function_exists('landing_page_default_form_fields')) {
    function landing_page_default_form_fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama Lengkap', 'type' => 'text', 'required' => true, 'placeholder' => 'Nama Anda'],
            ['name' => 'phone', 'label' => 'Nomor WhatsApp', 'type' => 'tel', 'required' => true, 'placeholder' => '08xxxxxxxxxx'],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => false, 'placeholder' => 'nama@email.com'],
            ['name' => 'need', 'label' => 'Kebutuhan', 'type' => 'select', 'required' => true, 'placeholder' => 'Pilih kebutuhan', 'options' => ['Konsultasi produk', 'Tanya stok dan harga', 'Minta penawaran', 'Order sekarang']],
            ['name' => 'message', 'label' => 'Catatan Kebutuhan', 'type' => 'textarea', 'required' => false, 'placeholder' => 'Tulis kebutuhan, lokasi, tanggal, atau pertanyaan Anda.'],
        ];
    }
}

if (!function_exists('landing_page_form_field_types')) {
    function landing_page_form_field_types(): array
    {
        return ['text', 'tel', 'email', 'number', 'date', 'select', 'radio', 'checkbox', 'textarea'];
    }
}

if (!function_exists('landing_page_sanitize_form_field_name')) {
    function landing_page_sanitize_form_field_name(string $name, string $fallback = 'field'): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9_\-]+/', '_', $name) ?: '';
        $name = trim($name, '_-');
        if ($name === '') {
            $name = $fallback;
        }
        return substr($name, 0, 40);
    }
}

if (!function_exists('landing_page_sanitize_form_fields')) {
    function landing_page_sanitize_form_fields(mixed $fields): array
    {
        $fields = is_array($fields) ? $fields : landing_page_default_form_fields();
        $allowedTypes = landing_page_form_field_types();
        $reserved = ['name', 'phone', 'email', 'need', 'location', 'message'];
        $clean = [];
        $used = [];

        foreach ($fields as $index => $field) {
            if (!is_array($field)) {
                continue;
            }
            $type = strtolower((string)($field['type'] ?? 'text'));
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'text';
            }
            $name = landing_page_sanitize_form_field_name((string)($field['name'] ?? ''), 'field_' . ((int)$index + 1));
            if (isset($used[$name])) {
                $name .= '_' . ((int)$index + 1);
            }
            $used[$name] = true;
            $label = conversion_clean_text((string)($field['label'] ?? ucwords(str_replace(['_', '-'], ' ', $name))), 90);
            if ($label === '') {
                continue;
            }
            $options = [];
            $rawOptions = $field['options'] ?? [];
            if (is_string($rawOptions)) {
                $rawOptions = preg_split('/\r\n|\r|\n|,/', $rawOptions) ?: [];
            }
            if (is_array($rawOptions)) {
                foreach ($rawOptions as $option) {
                    $option = conversion_clean_text((string)$option, 100);
                    if ($option !== '') {
                        $options[] = $option;
                    }
                    if (count($options) >= 20) {
                        break;
                    }
                }
            }

            if (in_array($type, ['select', 'radio'], true) && !$options) {
                $options = ['Konsultasi produk', 'Tanya stok dan harga', 'Minta penawaran'];
            }
            if ($type === 'checkbox' && !$options) {
                $options = ['Ya, saya setuju'];
            }

            $row = [
                'name' => $name,
                'label' => $label,
                'type' => $type,
                'required' => !empty($field['required']),
                'placeholder' => conversion_clean_text((string)($field['placeholder'] ?? ''), 140),
                'options' => $options,
            ];

            if (in_array($name, $reserved, true)) {
                $row['system_field'] = true;
            }

            $clean[] = $row;
            if (count($clean) >= 18) {
                break;
            }
        }

        return $clean ?: landing_page_default_form_fields();
    }
}

if (!function_exists('landing_page_sanitize_text_list')) {
    function landing_page_sanitize_text_list(mixed $items, int $max = 8): array
    {
        $items = is_array($items) ? $items : [];
        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $title = conversion_clean_text((string)($item['title'] ?? $item['question'] ?? ''), 140);
                $text = conversion_clean_text((string)($item['text'] ?? $item['answer'] ?? ''), 700);
                $price = conversion_clean_text((string)($item['price'] ?? ''), 100);
                $buttonText = conversion_clean_text((string)($item['button_text'] ?? ''), 90);
                $image = landing_page_clean_url((string)($item['image'] ?? ''));
                $imageAlt = conversion_clean_text((string)($item['image_alt'] ?? $title), 180);
                $url = landing_page_clean_url((string)($item['url'] ?? $item['link_url'] ?? $item['button_url'] ?? ''));
                $itemType = in_array((string)($item['item_type'] ?? ''), ['link', 'logo'], true) ? (string)$item['item_type'] : '';
                $logoPosition = landing_page_clean_choice((string)($item['logo_position'] ?? ''), ['left', 'center', 'right']);
                $features = landing_page_sanitize_text_list($item['features'] ?? [], 8);
                $itemDesign = [
                    'item_bg' => landing_page_design_value($item, 'item_bg', 'color'),
                    'item_title_color' => landing_page_design_value($item, 'item_title_color', 'color'),
                    'item_text_color' => landing_page_design_value($item, 'item_text_color', 'color'),
                    'item_button_bg' => landing_page_design_value($item, 'item_button_bg', 'color'),
                    'item_button_text_color' => landing_page_design_value($item, 'item_button_text_color', 'color'),
                    'item_title_size' => landing_page_design_value($item, 'item_title_size', 'px_small'),
                    'item_text_size' => landing_page_design_value($item, 'item_text_size', 'px_small'),
                    'item_radius' => landing_page_design_value($item, 'item_radius', 'radius'),
                    'item_align' => landing_page_design_value($item, 'item_align', 'align'),
                    'item_shadow' => landing_page_design_value($item, 'item_shadow', 'shadow'),
                ];

                $row = [];
                if ($title !== '') {
                    $row['title'] = $title;
                    $row['question'] = $title;
                }
                if ($text !== '') {
                    $row['text'] = $text;
                    $row['answer'] = $text;
                }
                if ($price !== '') {
                    $row['price'] = $price;
                }
                if ($buttonText !== '') {
                    $row['button_text'] = $buttonText;
                }
                if ($image !== '') {
                    $row['image'] = $image;
                    $row['image_alt'] = $imageAlt !== '' ? $imageAlt : $title;
                }
                if ($url !== '') {
                    $row['url'] = $url;
                    $row['link_url'] = $url;
                    $row['button_url'] = $url;
                }
                if ($itemType !== '') {
                    $row['item_type'] = $itemType;
                }
                if ($logoPosition !== '') {
                    $row['logo_position'] = $logoPosition;
                }
                if ($features !== []) {
                    $row['features'] = $features;
                }
                foreach ($itemDesign as $designKey => $designValue) {
                    if ($designValue !== '') {
                        $row[$designKey] = $designValue;
                    }
                }

                if ($row) {
                    $result[] = $row;
                }
            } else {
                $text = conversion_clean_text((string)$item, 260);
                if ($text !== '') {
                    $result[] = $text;
                }
            }
            if (count($result) >= $max) {
                break;
            }
        }
        return $result;
    }
}

if (!function_exists('landing_page_clean_tags')) {
    function landing_page_clean_tags(string|array $tags, int $maxTags = 12): array
    {
        if (is_string($tags)) {
            $tags = preg_split('/[,;\r\n]+/', $tags) ?: [];
        }
        if (!is_array($tags)) {
            return [];
        }
        $result = [];
        foreach ($tags as $tag) {
            $tag = strtolower(trim((string)$tag));
            $tag = preg_replace('/[^a-z0-9_\- ]+/', '', $tag) ?: '';
            $tag = trim(preg_replace('/\s+/', '-', $tag) ?: '', '-_');
            if ($tag === '' || isset($result[$tag])) {
                continue;
            }
            $result[$tag] = $tag;
            if (count($result) >= $maxTags) {
                break;
            }
        }
        return array_values($result);
    }
}

if (!function_exists('landing_page_clean_segment')) {
    function landing_page_clean_segment(string $value, string $fallback = ''): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\- ]+/', '', $value) ?: '';
        $value = trim(preg_replace('/\s+/', '-', $value) ?: '', '-_');
        if ($value === '') {
            $value = $fallback;
        }
        return substr($value, 0, 80);
    }
}


if (!function_exists('landing_page_clean_color')) {
    function landing_page_clean_color(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color) === 1) {
            return strtolower($color);
        }
        return '';
    }
}

if (!function_exists('landing_page_clean_px')) {
    function landing_page_clean_px(string $value, int $min, int $max): string
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{1,3})(px)?$/', $value, $m) !== 1) {
            return '';
        }
        $num = max($min, min($max, (int)$m[1]));
        return $num . 'px';
    }
}


if (!function_exists('landing_page_clean_choice')) {
    function landing_page_clean_choice(string $value, array $allowed, string $fallback = ''): string
    {
        $value = trim($value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}

if (!function_exists('landing_page_design_value')) {
    function landing_page_design_value(array $source, string $key, string $type = 'text'): string
    {
        $value = (string)($source[$key] ?? '');
        if ($type === 'color') {
            return landing_page_clean_color($value);
        }
        if ($type === 'px_small') {
            return landing_page_clean_px($value, 10, 28);
        }
        if ($type === 'px') {
            return landing_page_clean_px($value, 12, 72);
        }
        if ($type === 'radius') {
            return landing_page_clean_px($value, 0, 48);
        }
        if ($type === 'align') {
            return landing_page_clean_choice($value, ['left','center','right']);
        }
        if ($type === 'weight') {
            return landing_page_clean_choice($value, ['normal','500','600','700','800','900','bold']);
        }
        if ($type === 'style') {
            return landing_page_clean_choice($value, ['normal','italic']);
        }
        if ($type === 'decoration') {
            return landing_page_clean_choice($value, ['none','underline']);
        }
        if ($type === 'shadow') {
            return landing_page_clean_choice($value, ['none','soft','medium','strong']);
        }
        return conversion_clean_text($value, 120);
    }
}


if (!function_exists('landing_page_flex_justify_from_align')) {
    function landing_page_flex_justify_from_align(string $align): string
    {
        $align = landing_page_clean_choice($align, ['left', 'center', 'right']);
        if ($align === 'center') {
            return 'center';
        }
        if ($align === 'right') {
            return 'flex-end';
        }
        if ($align === 'left') {
            return 'flex-start';
        }
        return '';
    }
}

if (!function_exists('landing_page_block_style_attrs')) {
    function landing_page_block_style_attrs(array $block): string
    {
        $vars = [];
        $attrs = [];
        $bg = landing_page_clean_color((string)($block['bg_color'] ?? ''));
        $text = landing_page_clean_color((string)($block['text_color'] ?? ''));
        $accent = landing_page_clean_color((string)($block['accent_color'] ?? ''));
        $headlineSize = landing_page_clean_px((string)($block['headline_size'] ?? ''), 22, 72);
        $textSize = landing_page_clean_px((string)($block['text_size'] ?? ''), 13, 28);
        $align = landing_page_clean_choice((string)($block['align'] ?? ''), ['left', 'center', 'right']);
        $weight = landing_page_clean_choice((string)($block['font_weight'] ?? ''), ['normal', 'bold']);
        $style = landing_page_clean_choice((string)($block['font_style'] ?? ''), ['normal', 'italic']);
        $decoration = landing_page_clean_choice((string)($block['text_decoration'] ?? ''), ['none', 'underline']);
        $effect = landing_page_clean_choice((string)($block['section_effect'] ?? ''), ['none','soft-card','gradient-glow','top-wave','bottom-wave','spotlight','divider-line']);
        $animation = landing_page_clean_choice((string)($block['animation_style'] ?? ''), ['inherit','none','fade-up','zoom-soft','fade','slide-left','slide-right']);
        $goal = landing_page_clean_choice((string)($block['block_goal'] ?? ''), ['awareness','trust','offer','lead','closing']);
        $ctaRole = landing_page_clean_choice((string)($block['cta_role'] ?? ''), ['primary','secondary','form','pricing','closing']);

        $designMap = [
            'title_color' => ['--lp-title-color', 'color'],
            'title_size' => ['--lp-title-size', 'px'],
            'title_align' => ['--lp-title-align', 'align'],
            'title_weight' => ['--lp-title-weight', 'weight'],
            'title_style' => ['--lp-title-style', 'style'],
            'title_decoration' => ['--lp-title-decoration', 'decoration'],
            'description_color' => ['--lp-description-color', 'color'],
            'description_size' => ['--lp-description-size', 'px_small'],
            'description_align' => ['--lp-description-align', 'align'],
            'description_weight' => ['--lp-description-weight', 'weight'],
            'description_style' => ['--lp-description-style', 'style'],
            'description_decoration' => ['--lp-description-decoration', 'decoration'],
            'button_bg' => ['--lp-button-bg', 'color'],
            'button_text_color' => ['--lp-button-color', 'color'],
            'button_size' => ['--lp-button-size', 'px_small'],
            'button_radius' => ['--lp-button-radius', 'radius'],
            'button_align' => ['--lp-button-align', 'align'],
            'menu_align' => ['--lp-menu-align', 'align'],
            'logo_align' => ['--lp-logo-align', 'align'],
            'card_bg' => ['--lp-card-bg', 'color'],
            'card_text_color' => ['--lp-card-color', 'color'],
            'card_title_color' => ['--lp-card-title-color', 'color'],
            'card_title_size' => ['--lp-card-title-size', 'px_small'],
            'card_text_size' => ['--lp-card-text-size', 'px_small'],
            'card_radius' => ['--lp-card-radius', 'radius'],
            'card_align' => ['--lp-card-align', 'align'],
            'card_shadow' => ['--lp-card-shadow', 'shadow'],
        ];

        if ($bg !== '') { $vars[] = '--lp-block-bg:' . $bg; $vars[] = 'background:' . $bg; }
        if ($text !== '') { $vars[] = '--lp-block-text:' . $text; $vars[] = 'color:' . $text; }
        if ($accent !== '') { $vars[] = '--lp-block-accent:' . $accent; }
        if ($headlineSize !== '') { $vars[] = '--lp-block-headline-size:' . $headlineSize; }
        if ($textSize !== '') { $vars[] = '--lp-block-text-size:' . $textSize; }
        if ($align !== '') { $vars[] = '--lp-block-align:' . $align; $vars[] = 'text-align:' . $align; }
        $buttonAlign = landing_page_design_value($block, 'button_align', 'align');
        $buttonJustify = landing_page_flex_justify_from_align($buttonAlign);
        if ($buttonJustify !== '') { $vars[] = '--lp-button-justify:' . $buttonJustify; }
        if ($weight !== '') { $vars[] = '--lp-block-font-weight:' . $weight; }
        if ($style !== '') { $vars[] = '--lp-block-font-style:' . $style; }
        if ($decoration !== '') { $vars[] = '--lp-block-decoration:' . $decoration; }

        foreach ($designMap as $field => [$cssVar, $type]) {
            $value = landing_page_design_value($block, $field, $type);
            if ($value !== '') {
                $vars[] = $cssVar . ':' . $value;
            }
        }

        if ($buttonAlign !== '') { $attrs[] = 'data-lp-button-align="' . esc($buttonAlign) . '"'; }
        if ($vars) {
            $attrs[] = 'data-lp-styled="1"';
            $attrs[] = 'style="' . esc(implode(';', $vars)) . '"';
        }
        if ($effect !== '' && $effect !== 'none') { $attrs[] = 'data-lp-effect="' . esc($effect) . '"'; }
        if ($animation !== '' && $animation !== 'inherit') { $attrs[] = 'data-lp-animation="' . esc($animation) . '"'; }
        if ($goal !== '') { $attrs[] = 'data-lp-goal="' . esc($goal) . '"'; }
        if ($ctaRole !== '') { $attrs[] = 'data-lp-cta-role="' . esc($ctaRole) . '"'; }

        return $attrs ? ' ' . implode(' ', $attrs) : '';
    }
}

if (!function_exists('landing_page_item_style_attrs')) {
    function landing_page_item_style_attrs(mixed $item): string
    {
        if (!is_array($item)) {
            return '';
        }
        $vars = [];
        $map = [
            'item_bg' => ['--lp-item-bg', 'color'],
            'item_title_color' => ['--lp-item-title-color', 'color'],
            'item_text_color' => ['--lp-item-text-color', 'color'],
            'item_button_bg' => ['--lp-item-button-bg', 'color'],
            'item_button_text_color' => ['--lp-item-button-color', 'color'],
            'item_title_size' => ['--lp-item-title-size', 'px_small'],
            'item_text_size' => ['--lp-item-text-size', 'px_small'],
            'item_radius' => ['--lp-item-radius', 'radius'],
            'item_align' => ['--lp-item-align', 'align'],
            'item_shadow' => ['--lp-item-shadow', 'shadow'],
        ];
        foreach ($map as $field => [$cssVar, $type]) {
            $value = landing_page_design_value($item, $field, $type);
            if ($value !== '') {
                $vars[] = $cssVar . ':' . $value;
            }
        }
        if (!$vars) {
            return '';
        }
        return ' data-lp-item-styled="1" style="' . esc(implode(';', $vars)) . '"';
    }
}

if (!function_exists('landing_page_sanitize_blocks')) {
    function landing_page_sanitize_blocks(mixed $blocks): array
    {
        $blocks = is_array($blocks) ? $blocks : landing_page_default_blocks();
        $allowed = landing_page_allowed_block_types();
        $clean = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $type = (string)($block['type'] ?? 'text');
            if ($type === 'form') {
                $type = 'lead_form';
            }
            if (!in_array($type, $allowed, true)) {
                $type = 'text';
            }

            $headlineValue = $block['headline'] ?? $block['title'] ?? '';
            $textValue = $block['text'] ?? $block['description'] ?? '';
            $subheadlineValue = $block['subheadline'] ?? ($type === 'hero_offer' ? $textValue : '');
            $primaryTextValue = $block['primary_text'] ?? $block['button_text'] ?? '';
            $primaryUrlValue = $block['primary_url'] ?? $block['button_url'] ?? '';

            $row = [
                'type' => $type,
                'eyebrow' => conversion_clean_text((string)($block['eyebrow'] ?? ''), 90),
                'headline' => conversion_clean_text((string)$headlineValue, 180),
                'subheadline' => conversion_clean_text((string)$subheadlineValue, 360),
                'text' => conversion_clean_text((string)$textValue, 900),
                'html' => ($type === 'html_block' ? (function_exists('wp_elementor_import_sanitize_html') ? wp_elementor_import_sanitize_html((string)($block['html'] ?? $block['content'] ?? $textValue)) : strip_tags((string)($block['html'] ?? $block['content'] ?? $textValue), '<p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><ul><ol><li><a><blockquote><hr><img><figure><figcaption><table><thead><tbody><tfoot><tr><th><td><span><div><section><article>')) : ''),
                'builder_source' => conversion_clean_text((string)($block['builder_source'] ?? $block['page_builder'] ?? ''), 80),
                'builder_confidence' => conversion_clean_text((string)($block['builder_confidence'] ?? ''), 10),
                'complex_widgets' => conversion_clean_text((string)($block['complex_widgets'] ?? ''), 180),
                'image' => landing_page_clean_url((string)($block['image'] ?? '')),
                'image_alt' => conversion_clean_text((string)($block['image_alt'] ?? ''), 180),
                'primary_text' => conversion_clean_text((string)$primaryTextValue, 80),
                'primary_url' => landing_page_clean_url((string)$primaryUrlValue),
                'secondary_text' => conversion_clean_text((string)($block['secondary_text'] ?? ''), 80),
                'secondary_url' => landing_page_clean_url((string)($block['secondary_url'] ?? '')),
                'button_text' => conversion_clean_text((string)($block['button_text'] ?? $primaryTextValue), 80),
                'button_url' => landing_page_clean_url((string)($block['button_url'] ?? $primaryUrlValue)),
                'submit_text' => conversion_clean_text((string)($block['submit_text'] ?? ''), 80),
                'success_text' => conversion_clean_text((string)($block['success_text'] ?? ''), 220),
                'need_default' => conversion_clean_text((string)($block['need_default'] ?? ''), 140),
                'mailketing_list_id' => conversion_clean_text((string)($block['mailketing_list_id'] ?? ''), 80),
                'form_source' => in_array((string)($block['form_source'] ?? 'lp_builtin'), ['lp_builtin','custom_form'], true) ? (string)($block['form_source'] ?? 'lp_builtin') : 'lp_builtin',
                'custom_form_slug' => function_exists('custom_form_slug') ? custom_form_slug((string)($block['custom_form_slug'] ?? ''), '') : conversion_clean_text((string)($block['custom_form_slug'] ?? ''), 120),
                'form_name' => conversion_clean_text((string)($block['form_name'] ?? ''), 120),
                'lead_segment' => landing_page_clean_segment((string)($block['lead_segment'] ?? '')),
                'lead_tags' => implode(',', landing_page_clean_tags($block['lead_tags'] ?? '')),
                'lead_priority' => in_array((string)($block['lead_priority'] ?? ''), ['cold','warm','hot','vip'], true) ? (string)$block['lead_priority'] : '',
                'lead_stage' => landing_page_clean_segment((string)($block['lead_stage'] ?? '')),
                'lead_score' => (string)max(0, min(100, (int)($block['lead_score'] ?? 0))),
                'consent_text' => conversion_clean_text((string)($block['consent_text'] ?? ''), 240),
                'countdown_deadline' => conversion_clean_text((string)($block['countdown_deadline'] ?? ''), 40),
                'countdown_timezone' => conversion_clean_text((string)($block['countdown_timezone'] ?? ''), 40),
                'expired_text' => conversion_clean_text((string)($block['expired_text'] ?? ''), 220),
                'countdown_note' => conversion_clean_text((string)($block['countdown_note'] ?? ''), 180),
                'bg_color' => landing_page_clean_color((string)($block['bg_color'] ?? '')),
                'text_color' => landing_page_clean_color((string)($block['text_color'] ?? '')),
                'accent_color' => landing_page_clean_color((string)($block['accent_color'] ?? '')),
                'headline_size' => landing_page_clean_px((string)($block['headline_size'] ?? ''), 22, 72),
                'text_size' => landing_page_clean_px((string)($block['text_size'] ?? ''), 13, 28),
                'align' => in_array((string)($block['align'] ?? ''), ['left', 'center', 'right'], true) ? (string)$block['align'] : '',
                'font_weight' => in_array((string)($block['font_weight'] ?? ''), ['normal', 'bold'], true) ? (string)$block['font_weight'] : '',
                'font_style' => in_array((string)($block['font_style'] ?? ''), ['normal', 'italic'], true) ? (string)$block['font_style'] : '',
                'text_decoration' => in_array((string)($block['text_decoration'] ?? ''), ['none', 'underline'], true) ? (string)$block['text_decoration'] : '',
                'title_color' => landing_page_design_value($block, 'title_color', 'color'),
                'title_size' => landing_page_design_value($block, 'title_size', 'px'),
                'title_align' => landing_page_design_value($block, 'title_align', 'align'),
                'title_weight' => landing_page_design_value($block, 'title_weight', 'weight'),
                'title_style' => landing_page_design_value($block, 'title_style', 'style'),
                'title_decoration' => landing_page_design_value($block, 'title_decoration', 'decoration'),
                'description_color' => landing_page_design_value($block, 'description_color', 'color'),
                'description_size' => landing_page_design_value($block, 'description_size', 'px_small'),
                'description_align' => landing_page_design_value($block, 'description_align', 'align'),
                'description_weight' => landing_page_design_value($block, 'description_weight', 'weight'),
                'description_style' => landing_page_design_value($block, 'description_style', 'style'),
                'description_decoration' => landing_page_design_value($block, 'description_decoration', 'decoration'),
                'button_bg' => landing_page_design_value($block, 'button_bg', 'color'),
                'button_text_color' => landing_page_design_value($block, 'button_text_color', 'color'),
                'button_size' => landing_page_design_value($block, 'button_size', 'px_small'),
                'button_radius' => landing_page_design_value($block, 'button_radius', 'radius'),
                'button_align' => landing_page_design_value($block, 'button_align', 'align'),
                'card_bg' => landing_page_design_value($block, 'card_bg', 'color'),
                'card_text_color' => landing_page_design_value($block, 'card_text_color', 'color'),
                'card_title_color' => landing_page_design_value($block, 'card_title_color', 'color'),
                'card_title_size' => landing_page_design_value($block, 'card_title_size', 'px_small'),
                'card_text_size' => landing_page_design_value($block, 'card_text_size', 'px_small'),
                'card_radius' => landing_page_design_value($block, 'card_radius', 'radius'),
                'card_align' => landing_page_design_value($block, 'card_align', 'align'),
                'card_shadow' => landing_page_design_value($block, 'card_shadow', 'shadow'),
                'section_effect' => in_array((string)($block['section_effect'] ?? ''), ['none','soft-card','gradient-glow','top-wave','bottom-wave','spotlight','divider-line'], true) ? (string)$block['section_effect'] : '',
                'animation_style' => in_array((string)($block['animation_style'] ?? ''), ['inherit','none','fade-up','zoom-soft','fade','slide-left','slide-right'], true) ? (string)$block['animation_style'] : '',
                'block_goal' => in_array((string)($block['block_goal'] ?? ''), ['awareness','trust','offer','lead','closing'], true) ? (string)$block['block_goal'] : '',
                'cta_role' => in_array((string)($block['cta_role'] ?? ''), ['primary','secondary','form','pricing','closing'], true) ? (string)$block['cta_role'] : '',
                'optimization_note' => conversion_clean_text((string)($block['optimization_note'] ?? ''), 220),
                'hero_layout' => in_array((string)($block['hero_layout'] ?? ''), ['auto','split','media_only','text_only'], true) ? (string)$block['hero_layout'] : '',
                'hero_position' => in_array((string)($block['hero_position'] ?? ''), ['left','right'], true) ? (string)$block['hero_position'] : '',
                'media_layout' => in_array((string)($block['media_layout'] ?? ''), ['auto','split','media_only','text_only'], true) ? (string)$block['media_layout'] : '',
                'media_position' => in_array((string)($block['media_position'] ?? ''), ['left','right'], true) ? (string)$block['media_position'] : '',
                'menu_style' => in_array((string)($block['menu_style'] ?? ''), ['header','cards'], true) ? (string)$block['menu_style'] : '',
                'menu_position' => in_array((string)($block['menu_position'] ?? ''), ['normal','sticky','fixed'], true) ? (string)$block['menu_position'] : '',
                'menu_align' => landing_page_design_value($block, 'menu_align', 'align'),
                'logo_align' => landing_page_design_value($block, 'logo_align', 'align'),
                'engine_internal_links' => !empty($block['engine_internal_links']),
                'items' => landing_page_sanitize_text_list($block['items'] ?? [], 12),
                'fields' => landing_page_sanitize_form_fields($block['fields'] ?? []),
            ];

            $row = array_filter($row, static fn($value): bool => $value !== '' && $value !== []);
            if (!isset($row['type'])) {
                $row['type'] = $type;
            }

            $row = landing_page_apply_lp_optimization_defaults([$row])[0] ?? $row;
            $clean[] = $row;
            if (count($clean) >= 25) {
                break;
            }
        }

        return $clean;
    }
}

if (!function_exists('landing_page_sanitize_full_html_document')) {
    function landing_page_sanitize_full_html_document(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (function_exists('wp_elementor_import_sanitize_html')) {
            $html = wp_elementor_import_sanitize_html($html);
        } else {
            $html = preg_replace('#<script\b[^>]*>[\s\S]*?</script>#i', '', $html) ?? $html;
            $html = preg_replace('#<iframe\b[^>]*>[\s\S]*?</iframe>#i', '', $html) ?? $html;
            $html = preg_replace('#<object\b[^>]*>[\s\S]*?</object>#i', '', $html) ?? $html;
            $html = preg_replace('#<embed\b[^>]*>#i', '', $html) ?? $html;
            $html = strip_tags($html, '<section><article><div><span><p><br><hr><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><ul><ol><li><a><img><figure><figcaption><blockquote><table><thead><tbody><tfoot><tr><th><td><button>');
        }

        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\s+(href|src)\s*=\s*("|\')?\s*javascript:[^"\'\s>]*/i', ' $1="#"', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
        return trim($html);
    }
}

if (!function_exists('landing_page_normalize')) {
    function landing_page_normalize(array $page): array
    {
        $title = conversion_clean_text((string)($page['title'] ?? ''), 180);
        $slug = slugify((string)($page['slug'] ?? $title));
        if ($slug === '') {
            $slug = 'landing-page-' . date('Ymd-His');
        }

        $now = date('c');
        $layoutMode = (string)($page['layout_mode'] ?? 'focus');
        $layoutMode = in_array($layoutMode, ['focus', 'website'], true) ? $layoutMode : 'focus';
        $motionStyle = (string)($page['motion_style'] ?? 'fade-up');
        $motionStyle = in_array($motionStyle, ['fade-up', 'zoom-soft', 'fade'], true) ? $motionStyle : 'fade-up';
        return [
            'id' => conversion_clean_text((string)($page['id'] ?? ('lp_' . bin2hex(random_bytes(6)))), 80),
            'title' => $title !== '' ? $title : 'Landing Page Baru',
            'slug' => $slug,
            'status' => landing_page_normalize_status((string)($page['status'] ?? 'draft')),
            'layout_mode' => $layoutMode,
            'hide_header' => !empty($page['hide_header']),
            'hide_footer' => !empty($page['hide_footer']),
            'hide_floating_wa' => !empty($page['hide_floating_wa']),
            'show_nav_only' => !empty($page['show_nav_only']),
            'mini_footer_brand' => conversion_clean_text((string)($page['mini_footer_brand'] ?? ''), 140),
            'mini_footer_text' => conversion_clean_text((string)($page['mini_footer_text'] ?? ''), 180),
            'mini_footer_bg' => landing_page_clean_color((string)($page['mini_footer_bg'] ?? '')),
            'mini_footer_text_color' => landing_page_clean_color((string)($page['mini_footer_text_color'] ?? '')),
            'mini_footer_brand_color' => landing_page_clean_color((string)($page['mini_footer_brand_color'] ?? '')),
            'mini_footer_text_size' => landing_page_clean_px((string)($page['mini_footer_text_size'] ?? ''), 11, 22),
            'mini_footer_align' => landing_page_clean_choice((string)($page['mini_footer_align'] ?? ''), ['left', 'center', 'right']),
            'motion_enabled' => array_key_exists('motion_enabled', $page) ? !empty($page['motion_enabled']) : true,
            'motion_style' => $motionStyle,
            'indexable' => !empty($page['indexable']),
            'meta_title' => conversion_clean_text((string)($page['meta_title'] ?? $title), 180),
            'meta_description' => conversion_clean_text((string)($page['meta_description'] ?? ''), 300),
            'meta_keywords' => conversion_clean_text((string)($page['meta_keywords'] ?? ''), 300),
            'og_image' => landing_page_clean_url((string)($page['og_image'] ?? '')),
            'tracking_label' => conversion_clean_text((string)($page['tracking_label'] ?? $title), 120),
            'canonical_url' => landing_page_clean_url((string)($page['canonical_url'] ?? '')),
            'legacy_url' => conversion_clean_text((string)($page['legacy_url'] ?? ''), 300),
            'original_url' => conversion_clean_text((string)($page['original_url'] ?? ''), 300),
            'wp_post_id' => conversion_clean_text((string)($page['wp_post_id'] ?? ''), 80),
            'wp_post_type' => conversion_clean_text((string)($page['wp_post_type'] ?? ''), 40),
            'migration_batch_id' => conversion_clean_text((string)($page['migration_batch_id'] ?? ''), 120),
            'ab_tests' => function_exists('landing_page_ab_sanitize_config') ? landing_page_ab_sanitize_config($page['ab_tests'] ?? []) : (is_array($page['ab_tests'] ?? null) ? (array)$page['ab_tests'] : []),
            'full_html_mode' => !empty($page['full_html_mode']),
            'raw_html_document' => landing_page_sanitize_full_html_document((string)($page['raw_html_document'] ?? '')),
            'blocks' => landing_page_sanitize_blocks($page['blocks'] ?? []),
            'created_at' => (string)($page['created_at'] ?? $now),
            'updated_at' => (string)($page['updated_at'] ?? $now),
        ];
    }
}

if (!function_exists('landing_page_all')) {
    function landing_page_all(bool $fresh = false): array
    {
        static $cache = null;
        if ($fresh || $cache === null) {
            $cache = array_map('landing_page_normalize', landing_page_read_raw());
        }
        return $cache;
    }
}

if (!function_exists('landing_page_find')) {
    function landing_page_find(string $idOrSlug): ?array
    {
        $needle = trim($idOrSlug);
        foreach (landing_page_all(true) as $page) {
            if ((string)$page['id'] === $needle || (string)$page['slug'] === $needle) {
                return $page;
            }
        }
        return null;
    }
}

if (!function_exists('landing_page_public_find')) {
    function landing_page_public_find(string $slug): ?array
    {
        $page = landing_page_find(slugify($slug));
        if (!$page || (string)$page['status'] !== 'published') {
            return null;
        }
        return $page;
    }
}

if (!function_exists('landing_page_url')) {
    function landing_page_url(string $slug): string
    {
        return url('lp/' . slugify($slug));
    }
}

if (!function_exists('landing_page_unique_slug')) {
    function landing_page_unique_slug(string $slug, string $exceptId = ''): string
    {
        $base = slugify($slug) ?: 'landing-page';
        $candidate = $base;
        $i = 2;
        $taken = [];
        foreach (landing_page_all(true) as $page) {
            if ((string)$page['id'] !== $exceptId) {
                $taken[(string)$page['slug']] = true;
            }
        }
        while (isset($taken[$candidate])) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }
}



if (!function_exists('landing_page_revision_read_raw')) {
    function landing_page_revision_read_raw(): array
    {
        $file = landing_page_revision_storage_path();
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string)@file_get_contents($file), true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['revisions']) && is_array($decoded['revisions'])) {
            return array_values(array_filter($decoded['revisions'], 'is_array'));
        }

        return array_values(array_filter($decoded, 'is_array'));
    }
}

if (!function_exists('landing_page_revision_write_raw')) {
    function landing_page_revision_write_raw(array $revisions): bool
    {
        $file = landing_page_revision_storage_path();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = [
            'version' => APP_VERSION,
            'updated_at' => date('c'),
            'revisions' => array_values($revisions),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }

        return @file_put_contents($file, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('landing_page_revision_action_label')) {
    function landing_page_revision_action_label(string $action): string
    {
        return match ($action) {
            'create' => 'Create',
            'publish' => 'Publish',
            'draft' => 'Move to Draft',
            'archive' => 'Archive',
            'restore' => 'Restore',
            'duplicate' => 'Duplicate',
            'save_template' => 'Save Template',
            default => 'Save',
        };
    }
}

if (!function_exists('landing_page_revision_normalize')) {
    function landing_page_revision_normalize(array $revision): array
    {
        $snapshot = is_array($revision['snapshot'] ?? null) ? landing_page_normalize((array)$revision['snapshot']) : [];
        $pageId = conversion_clean_text((string)($revision['page_id'] ?? ($snapshot['id'] ?? '')), 80);
        $createdAt = (string)($revision['created_at'] ?? date('c'));
        $number = (int)($revision['revision_number'] ?? 0);

        return [
            'id' => conversion_clean_text((string)($revision['id'] ?? ('rev_' . bin2hex(random_bytes(6)))), 80),
            'page_id' => $pageId,
            'page_title' => conversion_clean_text((string)($revision['page_title'] ?? ($snapshot['title'] ?? 'Landing Page')), 180),
            'page_slug' => slugify((string)($revision['page_slug'] ?? ($snapshot['slug'] ?? ''))),
            'status' => landing_page_normalize_status((string)($revision['status'] ?? ($snapshot['status'] ?? 'draft'))),
            'action' => conversion_clean_text((string)($revision['action'] ?? 'save'), 40),
            'action_label' => conversion_clean_text((string)($revision['action_label'] ?? landing_page_revision_action_label((string)($revision['action'] ?? 'save'))), 80),
            'note' => conversion_clean_text((string)($revision['note'] ?? ''), 220),
            'summary' => is_array($revision['summary'] ?? null) ? array_slice((array)$revision['summary'], 0, 10) : [],
            'revision_number' => $number > 0 ? $number : 1,
            'snapshot' => $snapshot,
            'created_at' => $createdAt,
        ];
    }
}

if (!function_exists('landing_page_revision_diff_summary')) {
    function landing_page_revision_diff_summary(?array $previous, array $current): array
    {
        $summary = [];
        if (!$previous) {
            $summary[] = 'Snapshot awal landing page dibuat.';
        } else {
            foreach (['title' => 'Judul', 'slug' => 'Slug', 'status' => 'Status', 'layout_mode' => 'Layout', 'tracking_label' => 'Tracking label'] as $key => $label) {
                if ((string)($previous[$key] ?? '') !== (string)($current[$key] ?? '')) {
                    $summary[] = $label . ' berubah.';
                }
            }

            foreach (['hide_header' => 'Header', 'hide_footer' => 'Footer', 'hide_floating_wa' => 'Floating WA', 'show_nav_only' => 'Navigasi menu', 'indexable' => 'Indexable SEO'] as $key => $label) {
                if (!empty($previous[$key]) !== !empty($current[$key])) {
                    $summary[] = $label . ' diubah.';
                }
            }

            if ((string)($previous['meta_title'] ?? '') !== (string)($current['meta_title'] ?? '') || (string)($previous['meta_description'] ?? '') !== (string)($current['meta_description'] ?? '')) {
                $summary[] = 'Meta SEO diperbarui.';
            }

            if (json_encode($previous['ab_tests'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($current['ab_tests'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
                $summary[] = 'Konfigurasi Tes Variasi Tombol/Form diperbarui.';
            }

            $oldBlocks = (array)($previous['blocks'] ?? []);
            $newBlocks = (array)($current['blocks'] ?? []);
            if (count($oldBlocks) !== count($newBlocks)) {
                $summary[] = 'Jumlah blok berubah dari ' . count($oldBlocks) . ' ke ' . count($newBlocks) . '.';
            } elseif (json_encode($oldBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !== json_encode($newBlocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) {
                $summary[] = 'Konten/desain blok diperbarui.';
            }
        }

        if (!$summary) {
            $summary[] = 'Disimpan tanpa perubahan besar terdeteksi.';
        }

        return array_slice($summary, 0, 8);
    }
}

if (!function_exists('landing_page_revision_next_number')) {
    function landing_page_revision_next_number(string $pageId, array $revisions): int
    {
        $max = 0;
        foreach ($revisions as $revision) {
            if ((string)($revision['page_id'] ?? '') === $pageId) {
                $max = max($max, (int)($revision['revision_number'] ?? 0));
            }
        }
        return $max + 1;
    }
}

if (!function_exists('landing_page_revision_prune')) {
    function landing_page_revision_prune(array $revisions, int $maxPerPage = 35): array
    {
        $grouped = [];
        foreach ($revisions as $revision) {
            $pageId = (string)($revision['page_id'] ?? '');
            if ($pageId === '') {
                continue;
            }
            $grouped[$pageId][] = $revision;
        }

        $kept = [];
        foreach ($grouped as $rows) {
            usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
            foreach (array_slice($rows, 0, $maxPerPage) as $row) {
                $kept[] = $row;
            }
        }

        usort($kept, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return $kept;
    }
}

if (!function_exists('landing_page_revision_create')) {
    function landing_page_revision_create(array $page, string $action = 'save', string $note = '', ?array $previous = null): ?array
    {
        $snapshot = landing_page_normalize($page);
        $pageId = (string)($snapshot['id'] ?? '');
        if ($pageId === '') {
            return null;
        }

        $revisions = array_map('landing_page_revision_normalize', landing_page_revision_read_raw());
        $revision = [
            'id' => 'rev_' . bin2hex(random_bytes(8)),
            'page_id' => $pageId,
            'page_title' => (string)($snapshot['title'] ?? 'Landing Page'),
            'page_slug' => (string)($snapshot['slug'] ?? ''),
            'status' => (string)($snapshot['status'] ?? 'draft'),
            'action' => $action,
            'action_label' => landing_page_revision_action_label($action),
            'note' => conversion_clean_text($note, 220),
            'summary' => landing_page_revision_diff_summary($previous, $snapshot),
            'revision_number' => landing_page_revision_next_number($pageId, $revisions),
            'snapshot' => $snapshot,
            'created_at' => date('c'),
        ];

        array_unshift($revisions, landing_page_revision_normalize($revision));
        $revisions = landing_page_revision_prune($revisions);
        return landing_page_revision_write_raw($revisions) ? $revision : null;
    }
}

if (!function_exists('landing_page_revisions_for_page')) {
    function landing_page_revisions_for_page(string $pageId, int $limit = 20): array
    {
        $pageId = trim($pageId);
        if ($pageId === '') {
            return [];
        }

        $rows = [];
        foreach (array_map('landing_page_revision_normalize', landing_page_revision_read_raw()) as $revision) {
            if ((string)($revision['page_id'] ?? '') === $pageId) {
                $rows[] = $revision;
            }
        }
        usort($rows, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return array_slice($rows, 0, max(1, $limit));
    }
}

if (!function_exists('landing_page_revision_count')) {
    function landing_page_revision_count(string $pageId): int
    {
        return count(landing_page_revisions_for_page($pageId, 1000));
    }
}

if (!function_exists('landing_page_revision_last')) {
    function landing_page_revision_last(string $pageId): ?array
    {
        $rows = landing_page_revisions_for_page($pageId, 1);
        return $rows[0] ?? null;
    }
}

if (!function_exists('landing_page_revision_find')) {
    function landing_page_revision_find(string $pageId, string $revisionId): ?array
    {
        foreach (landing_page_revisions_for_page($pageId, 1000) as $revision) {
            if ((string)($revision['id'] ?? '') === $revisionId) {
                return $revision;
            }
        }
        return null;
    }
}

if (!function_exists('landing_page_revisions_delete_for_page')) {
    function landing_page_revisions_delete_for_page(string $pageId): bool
    {
        $rows = array_values(array_filter(landing_page_revision_read_raw(), static fn(array $revision): bool => (string)($revision['page_id'] ?? '') !== $pageId));
        return landing_page_revision_write_raw($rows);
    }
}

if (!function_exists('landing_page_restore_revision')) {
    function landing_page_restore_revision(string $pageId, string $revisionId, string $note = ''): ?array
    {
        $current = landing_page_find($pageId);
        $revision = landing_page_revision_find($pageId, $revisionId);
        $snapshot = is_array($revision['snapshot'] ?? null) ? (array)$revision['snapshot'] : [];
        if (!$current || !$snapshot) {
            return null;
        }

        $pages = landing_page_all(true);
        $restored = landing_page_normalize($snapshot);
        $restored['id'] = (string)$current['id'];
        $restored['created_at'] = (string)($current['created_at'] ?? $restored['created_at'] ?? date('c'));
        $restored['updated_at'] = date('c');
        $restored['slug'] = landing_page_unique_slug((string)($restored['slug'] ?? $current['slug'] ?? $restored['title']), (string)$current['id']);

        $updated = false;
        foreach ($pages as $index => $page) {
            if ((string)$page['id'] === (string)$current['id']) {
                $pages[$index] = $restored;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $pages[] = $restored;
        }

        if (!landing_page_write_raw($pages)) {
            return null;
        }

        landing_page_revision_create($restored, 'restore', $note !== '' ? $note : 'Restore dari revisi #' . (int)($revision['revision_number'] ?? 0), $current);
        return $restored;
    }
}

if (!function_exists('landing_page_change_status')) {
    function landing_page_change_status(string $id, string $status, string $note = ''): ?array
    {
        $page = landing_page_find($id);
        if (!$page) {
            return null;
        }
        $page['status'] = landing_page_normalize_status($status);
        if ($page['status'] !== 'published') {
            $page['indexable'] = false;
        }
        return landing_page_save($page, ['note' => $note, 'action' => $page['status']]);
    }
}

if (!function_exists('landing_page_save')) {
    function landing_page_save(array $input, array $options = []): array
    {
        $pages = landing_page_all(true);
        $id = trim((string)($input['id'] ?? ''));
        $existingIndex = null;
        $existing = null;
        foreach ($pages as $index => $page) {
            if ($id !== '' && (string)$page['id'] === $id) {
                $existingIndex = $index;
                $existing = $page;
                break;
            }
        }

        $input['id'] = $id !== '' ? $id : ('lp_' . bin2hex(random_bytes(6)));
        $input['created_at'] = $existing['created_at'] ?? date('c');
        $input['updated_at'] = date('c');
        $input['slug'] = landing_page_unique_slug((string)($input['slug'] ?? $input['title'] ?? ''), (string)$input['id']);

        $page = landing_page_normalize($input);
        if ($existingIndex === null) {
            $pages[] = $page;
        } else {
            $pages[$existingIndex] = $page;
        }

        landing_page_write_raw($pages);

        $action = conversion_clean_text((string)($options['action'] ?? ''), 40);
        if ($action === '') {
            $action = $existingIndex === null ? 'create' : 'save';
        }
        if ($existing && (string)($existing['status'] ?? 'draft') !== (string)($page['status'] ?? 'draft')) {
            $action = match ((string)($page['status'] ?? 'draft')) {
                'published' => 'publish',
                'archived' => 'archive',
                default => 'draft',
            };
        }
        $note = conversion_clean_text((string)($options['note'] ?? ''), 220);
        landing_page_revision_create($page, $action, $note, $existing);

        return $page;
    }
}

if (!function_exists('landing_page_delete')) {
    function landing_page_delete(string $id): bool
    {
        $pages = array_values(array_filter(landing_page_all(true), static fn(array $page): bool => (string)$page['id'] !== $id));
        $ok = landing_page_write_raw($pages);
        if ($ok && function_exists('landing_page_revisions_delete_for_page')) {
            landing_page_revisions_delete_for_page($id);
        }
        return $ok;
    }
}


if (!function_exists('landing_page_duplicate')) {
    function landing_page_duplicate(string $id): ?array
    {
        $sumber = landing_page_find($id);
        if (!$sumber) {
            return null;
        }

        $title = trim((string)($sumber['title'] ?? 'Landing Page'));
        $copyTitle = $title . ' Copy';
        $copy = $sumber;
        unset($copy['id']);
        $copy['title'] = $copyTitle;
        $copy['slug'] = landing_page_unique_slug(slugify($copyTitle));
        $copy['status'] = 'draft';
        $copy['indexable'] = false;
        $copy['tracking_label'] = trim((string)($sumber['tracking_label'] ?? $title)) . ' Copy';
        $copy['created_at'] = date('c');
        $copy['updated_at'] = date('c');

        return landing_page_save($copy);
    }
}

if (!function_exists('landing_page_template_read_raw')) {
    function landing_page_template_read_raw(): array
    {
        $file = landing_page_template_storage_path();
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string)@file_get_contents($file), true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['templates']) && is_array($decoded['templates'])) {
            return array_values(array_filter($decoded['templates'], 'is_array'));
        }

        return array_values(array_filter($decoded, 'is_array'));
    }
}

if (!function_exists('landing_page_template_write_raw')) {
    function landing_page_template_write_raw(array $templates): bool
    {
        $file = landing_page_template_storage_path();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = [
            'version' => APP_VERSION,
            'updated_at' => date('c'),
            'templates' => array_values($templates),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }

        return @file_put_contents($file, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('landing_page_template_normalize')) {
    function landing_page_template_normalize(array $template): array
    {
        $name = conversion_clean_text((string)($template['name'] ?? ''), 160);
        $category = conversion_clean_text((string)($template['category'] ?? 'Custom UMKM'), 80);
        if ($category === '') {
            $category = 'Custom UMKM';
        }
        $layoutMode = (string)($template['layout_mode'] ?? 'focus');
        $layoutMode = in_array($layoutMode, ['focus', 'website'], true) ? $layoutMode : 'focus';
        $includeSeo = !empty($template['include_seo']);
        $includeTracking = !empty($template['include_tracking']);
        $now = date('c');

        return [
            'id' => conversion_clean_text((string)($template['id'] ?? ('lpt_' . bin2hex(random_bytes(6)))), 80),
            'name' => $name !== '' ? $name : 'Template Landing Page',
            'category' => $category,
            'description' => conversion_clean_text((string)($template['description'] ?? ''), 260),
            'layout_mode' => $layoutMode,
            'hide_header' => !empty($template['hide_header']),
            'hide_footer' => !empty($template['hide_footer']),
            'hide_floating_wa' => !empty($template['hide_floating_wa']),
            'show_nav_only' => !empty($template['show_nav_only']),
            'mini_footer_brand' => conversion_clean_text((string)($template['mini_footer_brand'] ?? ''), 140),
            'mini_footer_text' => conversion_clean_text((string)($template['mini_footer_text'] ?? ''), 180),
            'mini_footer_bg' => landing_page_clean_color((string)($template['mini_footer_bg'] ?? '')),
            'mini_footer_text_color' => landing_page_clean_color((string)($template['mini_footer_text_color'] ?? '')),
            'mini_footer_brand_color' => landing_page_clean_color((string)($template['mini_footer_brand_color'] ?? '')),
            'mini_footer_text_size' => landing_page_clean_px((string)($template['mini_footer_text_size'] ?? ''), 11, 22),
            'mini_footer_align' => landing_page_clean_choice((string)($template['mini_footer_align'] ?? ''), ['left', 'center', 'right']),
            'include_seo' => $includeSeo,
            'include_tracking' => $includeTracking,
            'meta_title' => $includeSeo ? conversion_clean_text((string)($template['meta_title'] ?? ''), 180) : '',
            'meta_description' => $includeSeo ? conversion_clean_text((string)($template['meta_description'] ?? ''), 300) : '',
            'meta_keywords' => $includeSeo ? conversion_clean_text((string)($template['meta_keywords'] ?? ''), 300) : '',
            'og_image' => $includeSeo ? landing_page_clean_url((string)($template['og_image'] ?? '')) : '',
            'tracking_label' => $includeTracking ? conversion_clean_text((string)($template['tracking_label'] ?? $name), 120) : '',
            'sumber_lp_id' => conversion_clean_text((string)($template['sumber_lp_id'] ?? ''), 80),
            'blocks' => landing_page_sanitize_blocks($template['blocks'] ?? []),
            'created_at' => (string)($template['created_at'] ?? $now),
            'updated_at' => (string)($template['updated_at'] ?? $now),
        ];
    }
}

if (!function_exists('landing_page_templates_all')) {
    function landing_page_templates_all(bool $fresh = false): array
    {
        static $cache = null;
        if ($fresh || $cache === null) {
            $cache = array_map('landing_page_template_normalize', landing_page_template_read_raw());
        }
        return $cache;
    }
}

if (!function_exists('landing_page_template_find')) {
    function landing_page_template_find(string $id): ?array
    {
        $needle = trim($id);
        foreach (landing_page_templates_all(true) as $template) {
            if ((string)$template['id'] === $needle) {
                return $template;
            }
        }
        return null;
    }
}

if (!function_exists('landing_page_template_save')) {
    function landing_page_template_save(array $input): array
    {
        $templates = landing_page_templates_all(true);
        $id = trim((string)($input['id'] ?? ''));
        $existingIndex = null;
        $existing = null;
        foreach ($templates as $index => $template) {
            if ($id !== '' && (string)$template['id'] === $id) {
                $existingIndex = $index;
                $existing = $template;
                break;
            }
        }

        $input['id'] = $id !== '' ? $id : ('lpt_' . bin2hex(random_bytes(6)));
        $input['created_at'] = $existing['created_at'] ?? date('c');
        $input['updated_at'] = date('c');
        $template = landing_page_template_normalize($input);

        if ($existingIndex === null) {
            $templates[] = $template;
        } else {
            $templates[$existingIndex] = $template;
        }

        landing_page_template_write_raw($templates);
        return $template;
    }
}

if (!function_exists('landing_page_template_save_from_page')) {
    function landing_page_template_save_from_page(array $page, array $options = []): array
    {
        $includeSeo = !empty($options['include_seo']);
        $includeTracking = !empty($options['include_tracking']);
        return landing_page_template_save([
            'name' => (string)($options['name'] ?? ($page['title'] ?? 'Template Landing Page')),
            'category' => (string)($options['category'] ?? 'Custom UMKM'),
            'description' => (string)($options['description'] ?? ''),
            'layout_mode' => $page['layout_mode'] ?? 'focus',
            'hide_header' => !empty($page['hide_header']),
            'hide_footer' => !empty($page['hide_footer']),
            'hide_floating_wa' => !empty($page['hide_floating_wa']),
            'show_nav_only' => !empty($page['show_nav_only']),
            'mini_footer_brand' => (string)($page['mini_footer_brand'] ?? ''),
            'mini_footer_text' => (string)($page['mini_footer_text'] ?? ''),
            'mini_footer_bg' => (string)($page['mini_footer_bg'] ?? ''),
            'mini_footer_text_color' => (string)($page['mini_footer_text_color'] ?? ''),
            'mini_footer_brand_color' => (string)($page['mini_footer_brand_color'] ?? ''),
            'mini_footer_text_size' => (string)($page['mini_footer_text_size'] ?? ''),
            'mini_footer_align' => (string)($page['mini_footer_align'] ?? ''),
            'include_seo' => $includeSeo,
            'include_tracking' => $includeTracking,
            'meta_title' => $includeSeo ? (string)($page['meta_title'] ?? '') : '',
            'meta_description' => $includeSeo ? (string)($page['meta_description'] ?? '') : '',
            'meta_keywords' => $includeSeo ? (string)($page['meta_keywords'] ?? '') : '',
            'og_image' => $includeSeo ? (string)($page['og_image'] ?? '') : '',
            'tracking_label' => $includeTracking ? (string)($page['tracking_label'] ?? '') : '',
            'sumber_lp_id' => (string)($page['id'] ?? ''),
            'blocks' => $page['blocks'] ?? [],
        ]);
    }
}

if (!function_exists('landing_page_template_delete')) {
    function landing_page_template_delete(string $id): bool
    {
        $templates = array_values(array_filter(landing_page_templates_all(true), static fn(array $template): bool => (string)$template['id'] !== $id));
        return landing_page_template_write_raw($templates);
    }
}

if (!function_exists('landing_page_template_to_page_seed')) {
    function landing_page_template_to_page_seed(array $template): array
    {
        $name = (string)($template['name'] ?? 'Template Landing Page');
        $seed = [
            'id' => '',
            'title' => 'Landing Page dari ' . $name,
            'slug' => landing_page_unique_slug(slugify($name . '-' . date('Ymd-His'))),
            'status' => 'draft',
            'layout_mode' => $template['layout_mode'] ?? 'focus',
            'hide_header' => !empty($template['hide_header']),
            'hide_footer' => !empty($template['hide_footer']),
            'hide_floating_wa' => !empty($template['hide_floating_wa']),
            'show_nav_only' => !empty($template['show_nav_only']),
            'indexable' => false,
            'meta_title' => '',
            'meta_description' => '',
            'meta_keywords' => '',
            'og_image' => '',
            'tracking_label' => !empty($template['include_tracking']) ? (string)($template['tracking_label'] ?? $name) : $name,
            'blocks' => $template['blocks'] ?? [],
        ];

        if (!empty($template['include_seo'])) {
            $seed['meta_title'] = (string)($template['meta_title'] ?? '');
            $seed['meta_description'] = (string)($template['meta_description'] ?? '');
            $seed['meta_keywords'] = (string)($template['meta_keywords'] ?? '');
            $seed['og_image'] = (string)($template['og_image'] ?? '');
        }

        return $seed;
    }
}

if (!function_exists('landing_page_template_summary')) {
    function landing_page_template_summary(): array
    {
        $templates = landing_page_templates_all(true);
        $counts = ['total' => count($templates), 'with_form' => 0, 'with_seo' => 0, 'with_tracking' => 0, 'categories' => []];
        foreach ($templates as $template) {
            $category = (string)($template['category'] ?? 'Custom UMKM');
            $counts['categories'][$category] = ($counts['categories'][$category] ?? 0) + 1;
            if (!empty($template['include_seo'])) {
                $counts['with_seo']++;
            }
            if (!empty($template['include_tracking'])) {
                $counts['with_tracking']++;
            }
            foreach ((array)($template['blocks'] ?? []) as $block) {
                if (is_array($block) && (string)($block['type'] ?? '') === 'lead_form') {
                    $counts['with_form']++;
                    break;
                }
            }
        }
        return ['counts' => $counts, 'items' => $templates];
    }
}

if (!function_exists('landing_page_summary')) {
    function landing_page_summary(): array
    {
        $pages = landing_page_all(true);
        $counts = ['total' => count($pages), 'published' => 0, 'draft' => 0, 'archived' => 0, 'indexable' => 0, 'focus' => 0, 'nav_only' => 0, 'full_html' => 0];
        foreach ($pages as $page) {
            $status = (string)$page['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            if (!empty($page['indexable'])) {
                $counts['indexable']++;
            }
            if ((string)$page['layout_mode'] === 'focus') {
                $counts['focus']++;
            }
            if (!empty($page['show_nav_only'])) {
                $counts['nav_only']++;
            }
            if (!empty($page['full_html_mode'])) {
                $counts['full_html']++;
            }
        }
        return ['counts' => $counts, 'items' => $pages];
    }
}

if (!function_exists('landing_page_form_segmentation_summary')) {
    function landing_page_form_segmentation_summary(): array
    {
        $summary = ['forms' => 0, 'segmented_forms' => 0, 'custom_list_forms' => 0, 'segments' => [], 'field_types' => []];
        foreach (landing_page_all(true) as $page) {
            foreach ((array)($page['blocks'] ?? []) as $block) {
                if (!is_array($block) || (string)($block['type'] ?? '') !== 'lead_form') {
                    continue;
                }
                $summary['forms']++;
                $segment = landing_page_clean_segment((string)($block['lead_segment'] ?? ''));
                if ($segment !== '') {
                    $summary['segmented_forms']++;
                    $summary['segments'][$segment] = (int)($summary['segments'][$segment] ?? 0) + 1;
                }
                if ((string)($block['mailketing_list_id'] ?? '') !== '') {
                    $summary['custom_list_forms']++;
                }
                foreach (landing_page_sanitize_form_fields($block['fields'] ?? []) as $field) {
                    $type = (string)($field['type'] ?? 'text');
                    $summary['field_types'][$type] = (int)($summary['field_types'][$type] ?? 0) + 1;
                }
            }
        }
        arsort($summary['segments']);
        arsort($summary['field_types']);
        return $summary;
    }
}


if (!function_exists('landing_page_public_records')) {
    function landing_page_public_records(bool $indexableOnly = false): array
    {
        $records = [];
        foreach (landing_page_all(true) as $page) {
            if ((string)$page['status'] !== 'published') {
                continue;
            }
            if ($indexableOnly && empty($page['indexable'])) {
                continue;
            }
            $page['url'] = landing_page_url((string)$page['slug']);
            $records[] = $page;
        }
        return $records;
    }
}

if (!function_exists('landing_page_block_text')) {
    function landing_page_block_text(array $block, string $key, string $fallback = ''): string
    {
        return trim((string)($block[$key] ?? $fallback));
    }
}

if (!function_exists('landing_page_section_head_html')) {
    /**
     * Render public LP section heading only when the admin explicitly fills content.
     * Internal block names/funnel goals must stay as data attributes for tracking/audit,
     * not as visible frontend labels.
     */
    function landing_page_section_head_html(array $block, string $headline = '', string $text = '', string $headingTag = 'h2', string $class = 'lp-section-head'): string
    {
        $eyebrow = landing_page_block_text($block, 'eyebrow');
        $headline = trim($headline);
        $text = trim($text);
        $headingTag = in_array($headingTag, ['h1', 'h2', 'h3'], true) ? $headingTag : 'h2';
        if ($eyebrow === '' && $headline === '' && $text === '') {
            return '';
        }
        $html = '<div class="' . esc($class) . '">';
        if ($eyebrow !== '') {
            $html .= '<span class="lp-eyebrow">' . esc($eyebrow) . '</span>';
        }
        if ($headline !== '') {
            $html .= '<' . $headingTag . '>' . esc($headline) . '</' . $headingTag . '>';
        }
        if ($text !== '') {
            $html .= '<p>' . nl2br(esc($text)) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}


if (!function_exists('landing_page_grid_style_attr')) {
    function landing_page_grid_style_attr(array $items, int $maxColumns = 3): string
    {
        $count = 0;
        foreach ($items as $item) {
            if (is_array($item)) {
                $hasContent = trim((string)($item['title'] ?? $item['text'] ?? $item['image'] ?? $item['url'] ?? $item['question'] ?? $item['answer'] ?? $item['price'] ?? '')) !== '' || !empty($item['features']);
                if ($hasContent) { $count++; }
            } elseif (trim((string)$item) !== '') {
                $count++;
            }
        }
        $total = max(1, $count > 0 ? $count : $maxColumns);
        if ($total === 1) {
            $columns = 1;
        } elseif ($total === 2) {
            $columns = min(2, $maxColumns);
        } elseif ($total === 4 && $maxColumns >= 2) {
            // 4 item paling rapi menjadi 2x2, bukan 3+1 yang terlihat timpang.
            $columns = 2;
        } else {
            $columns = min($maxColumns, $total);
        }
        return ' style="--lp-card-columns:' . (int)$columns . '"';
    }
}


if (!function_exists('landing_page_tracking_signal_slug')) {
    function landing_page_tracking_signal_slug(string $value, string $fallback = 'cta'): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]+/', '-', $value) ?: '';
        $value = trim((string)preg_replace('/[-_]+/', '_', $value), '-_');
        if ($value === '') {
            $value = $fallback;
        }
        return substr($value, 0, 90);
    }
}

if (!function_exists('landing_page_button_tracking_context')) {
    function landing_page_button_tracking_context(array $page, array $block = [], int $index = 0, string $buttonKey = 'button', string $style = 'primary', string $label = ''): array
    {
        $blockType = (string)($block['type'] ?? 'button');
        $goal = (string)($block['block_goal'] ?? '');
        $role = (string)($block['cta_role'] ?? '');
        if ($role === '') {
            $role = match ($blockType) {
                'hero_offer' => $style === 'secondary' ? 'secondary' : 'primary',
                'pricing_cards' => 'pricing',
                'lead_form' => 'form',
                'countdown_timer' => 'primary',
                'cta' => 'closing',
                default => $style === 'secondary' ? 'secondary' : 'primary',
            };
        }
        $signalBase = $blockType . '_' . $role . '_' . $buttonKey;
        if ($blockType === 'hero_offer' && $style !== 'secondary') { $signalBase = 'hero_cta'; }
        if ($blockType === 'hero_offer' && $style === 'secondary') { $signalBase = 'hero_secondary_cta'; }
        if ($blockType === 'pricing_cards') { $signalBase = 'pricing_cta'; }
        if ($blockType === 'countdown_timer') { $signalBase = 'countdown_cta'; }
        if ($blockType === 'cta') { $signalBase = 'closing_cta'; }
        if ($blockType === 'media') { $signalBase = 'media_cta'; }
        if ($blockType === 'lead_form') { $signalBase = 'lead_form_submit'; }

        $title = trim((string)($page['tracking_label'] ?? $page['title'] ?? 'Landing Page'));
        return [
            'source' => 'landing-page-builder',
            'landing_page_slug' => (string)($page['slug'] ?? ''),
            'landing_page_id' => (string)($page['id'] ?? ''),
            'block_type' => $blockType,
            'block_index' => (string)max(1, $index + 1),
            'block_goal' => $goal,
            'cta_role' => $role,
            'cta_signal' => landing_page_tracking_signal_slug($signalBase),
            'cta_signal_label' => trim(($label !== '' ? $label : (string)($block['headline'] ?? $title)) . ' · #' . max(1, $index + 1)),
        ];
    }
}

if (!function_exists('landing_page_block_tracking_attrs')) {
    function landing_page_block_tracking_attrs(array $page, array $block, int $index, string $type = ''): string
    {
        $ctx = landing_page_button_tracking_context($page, $block, $index, 'section', 'primary', (string)($block['headline'] ?? ''));
        $attrs = [
            'data-landing-page-slug' => (string)($ctx['landing_page_slug'] ?? ''),
            'data-landing-page-id' => (string)($ctx['landing_page_id'] ?? ''),
            'data-lp-block-type' => $type !== '' ? $type : (string)($ctx['block_type'] ?? ''),
            'data-lp-block-index' => (string)($ctx['block_index'] ?? ''),
            'data-lp-block-goal' => (string)($ctx['block_goal'] ?? ''),
            'data-lp-cta-role' => (string)($ctx['cta_role'] ?? ''),
        ];
        $html = [];
        foreach ($attrs as $key => $value) {
            if ($value !== '') {
                $html[] = $key . '="' . esc($value) . '"';
            }
        }
        return $html ? ' ' . implode(' ', $html) : '';
    }
}

if (!function_exists('landing_page_render_button')) {
    function landing_page_render_button(string $text, string $url, array $page, string $style = 'primary', array $trackingContext = []): void
    {
        $text = trim($text);
        $url = trim($url);
        if (function_exists('landing_page_ab_apply_cta')) {
            [$text, $url] = landing_page_ab_apply_cta($text, $url);
        }
        if ($text === '' || $url === '') {
            return;
        }
        $external = preg_match('#^https?://#i', $url) === 1;
        $class = $style === 'secondary' ? 'lp-btn lp-btn--soft' : 'lp-btn';
        $isWhatsapp = str_contains($url, 'wa.me') || str_contains($url, 'api.whatsapp.com');
        $eventContext = array_merge([
            'source' => 'landing-page-builder',
            'type' => $isWhatsapp ? 'whatsapp' : 'landing-cta',
            'channel' => $isWhatsapp ? 'whatsapp' : 'click',
            'category' => 'landing-page',
            'label' => (string)($page['tracking_label'] ?? $page['title'] ?? 'Landing Page Tombol'),
            'intent' => 'direct-selling-cta',
            'landing_page_slug' => (string)($page['slug'] ?? ''),
            'landing_page_id' => (string)($page['id'] ?? ''),
        ], $trackingContext);
        $attrs = function_exists('conversion_link_attrs') ? conversion_link_attrs($eventContext) : '';
        $abAttrs = function_exists('landing_page_ab_attrs') ? landing_page_ab_attrs('cta') : '';
        echo '<a class="' . esc($class) . '" href="' . esc($url) . '" ' . ($external ? 'target="_blank" rel="nofollow noopener" ' : '') . $attrs . $abAttrs . '>' . esc($text) . '</a>';
    }
}

if (!function_exists('landing_page_form_input_name')) {
    function landing_page_form_input_name(string $name): string
    {
        $name = landing_page_sanitize_form_field_name($name);
        $system = ['name', 'phone', 'email', 'need', 'location', 'message'];
        return in_array($name, $system, true) ? $name : 'custom_fields[' . $name . ']';
    }
}

if (!function_exists('landing_page_render_form_field')) {
    function landing_page_render_form_field(array $field): void
    {
        $name = landing_page_sanitize_form_field_name((string)($field['name'] ?? 'field'));
        $inputName = landing_page_form_input_name($name);
        $type = (string)($field['type'] ?? 'text');
        $label = (string)($field['label'] ?? ucwords(str_replace('_', ' ', $name)));
        $placeholder = (string)($field['placeholder'] ?? '');
        $required = !empty($field['required']);
        $requiredAttr = $required ? ' required aria-required="true"' : '';
        $fieldId = 'lp-field-' . substr(md5($inputName . $label), 0, 10);

        echo '<label class="lp-form-field lp-form-field--' . esc($type) . '">';
        echo '<span>' . esc($label) . ($required ? ' <em>*</em>' : '') . '</span>';
        if (!empty($field['system_field']) || in_array($name, ['name', 'phone', 'email', 'need', 'location', 'message'], true)) {
            echo '<input type="hidden" name="custom_labels[' . esc($name) . ']" value="' . esc($label) . '">';
        } else {
            echo '<input type="hidden" name="custom_labels[' . esc($name) . ']" value="' . esc($label) . '">';
        }

        if ($type === 'textarea') {
            echo '<textarea id="' . esc($fieldId) . '" name="' . esc($inputName) . '" rows="4" placeholder="' . esc($placeholder) . '"' . $requiredAttr . '></textarea>';
        } elseif ($type === 'select') {
            echo '<select id="' . esc($fieldId) . '" name="' . esc($inputName) . '"' . $requiredAttr . '>';
            echo '<option value="">' . esc($placeholder !== '' ? $placeholder : 'Pilih salah satu') . '</option>';
            foreach ((array)($field['options'] ?? []) as $option) {
                $option = is_array($option) ? (string)($option['title'] ?? $option['text'] ?? '') : (string)$option;
                if ($option !== '') {
                    echo '<option value="' . esc($option) . '">' . esc($option) . '</option>';
                }
            }
            echo '</select>';
        } elseif (in_array($type, ['radio', 'checkbox'], true)) {
            $isSystem = in_array($name, ['name', 'phone', 'email', 'need', 'location', 'message'], true);
            $groupName = ($type === 'checkbox' && !$isSystem) ? $inputName . '[]' : $inputName;
            echo '<div class="lp-choice-group lp-choice-group--' . esc($type) . '" role="group" aria-label="' . esc($label) . '">';
            foreach ((array)($field['options'] ?? []) as $idx => $option) {
                $option = is_array($option) ? (string)($option['title'] ?? $option['text'] ?? '') : (string)$option;
                if ($option === '') { continue; }
                $choiceId = $fieldId . '-' . (int)$idx;
                echo '<label class="lp-choice"><input id="' . esc($choiceId) . '" type="' . esc($type) . '" name="' . esc($groupName) . '" value="' . esc($option) . '"' . ($required && $idx === 0 ? $requiredAttr : '') . '><span>' . esc($option) . '</span></label>';
            }
            echo '</div>';
        } else {
            $htmlType = in_array($type, ['text', 'tel', 'email', 'number', 'date'], true) ? $type : 'text';
            $autocomplete = $name === 'name' ? ' autocomplete="name"' : ($name === 'phone' ? ' autocomplete="tel"' : ($name === 'email' ? ' autocomplete="email"' : ''));
            echo '<input id="' . esc($fieldId) . '" type="' . esc($htmlType) . '" name="' . esc($inputName) . '" placeholder="' . esc($placeholder) . '"' . $autocomplete . $requiredAttr . '>';
        }
        echo '</label>';
    }
}

if (!function_exists('landing_page_render_lead_form')) {
    function landing_page_render_lead_form(array $block, array $page, int $index = 0): void
    {
        if (function_exists('landing_page_ab_apply_form_block')) {
            $block = landing_page_ab_apply_form_block($block);
        }
        $headline = landing_page_block_text($block, 'headline');
        $text = landing_page_block_text($block, 'text');
        $submitText = landing_page_block_text($block, 'submit_text', 'Kirim Form');
        $successText = landing_page_block_text($block, 'success_text', 'Terima kasih, form sudah masuk. Admin akan segera menghubungi Anda.');
        $needDefault = landing_page_block_text($block, 'need_default', (string)($page['title'] ?? 'Konsultasi Landing Page'));
        $consentText = landing_page_block_text($block, 'consent_text', 'Saya bersedia dihubungi admin melalui WhatsApp/telepon/email terkait penawaran ini.');
        $formName = landing_page_block_text($block, 'form_name', $headline);
        $leadSegment = landing_page_clean_segment((string)($block['lead_segment'] ?? ''));
        $leadTags = implode(',', landing_page_clean_tags($block['lead_tags'] ?? ''));
        $leadPriority = in_array((string)($block['lead_priority'] ?? ''), ['cold','warm','hot','vip'], true) ? (string)$block['lead_priority'] : '';
        $leadStage = landing_page_clean_segment((string)($block['lead_stage'] ?? ''));
        $leadScore = (string)max(0, min(100, (int)($block['lead_score'] ?? 0)));
        $fields = landing_page_sanitize_form_fields($block['fields'] ?? []);
        $names = array_map(static fn(array $field): string => landing_page_sanitize_form_field_name((string)($field['name'] ?? '')), $fields);
        $formId = 'lp-lead-form-' . substr(md5((string)($page['slug'] ?? '') . $headline), 0, 10);
        $formSource = (string)($block['form_source'] ?? 'lp_builtin');
        $customFormSlug = function_exists('custom_form_slug') ? custom_form_slug((string)($block['custom_form_slug'] ?? ''), '') : '';

        if ($formSource === 'custom_form' && $customFormSlug !== '' && function_exists('custom_form_render')) {
            echo '<section class="lp-section lp-lead-form-section lp-lead-form-section--custom" data-landing-block="lead_form"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'lead_form') . '>';
            echo '<div class="container lp-narrow">';
            custom_form_render($customFormSlug, [
                'title' => $headline,
                'description' => $text,
                'show_header' => ($headline !== '' || $text !== ''),
                'class' => 'custom-form-card--landing-page',
                'source_type' => 'landing_page',
                'source_label' => (string)($page['tracking_label'] ?? $page['title'] ?? $headline),
                'source_landing_page_id' => (string)($page['id'] ?? ''),
                'source_landing_page_slug' => (string)($page['slug'] ?? ''),
            ]);
            echo '</div></section>';
            return;
        }

        echo '<section class="lp-section lp-lead-form-section" data-landing-block="lead_form"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'lead_form') . '>';
        echo '<div class="container lp-narrow">';
        echo '<div class="lp-lead-form-card inquiry-card">';
        echo landing_page_section_head_html($block, $headline, $text);
        $formTrack = landing_page_button_tracking_context($page, $block, $index, 'form', 'primary', $submitText);
        echo '<form id="' . esc($formId) . '" class="inquiry-form inquiry-form--template lp-custom-lead-form" action="' . esc(url('inquiry-submit')) . '" method="post" data-inquiry-form="1" data-lead-form="1" data-lead-source="landing-page-builder" data-lead-sumber="landing-page-builder" data-lead-channel="form" data-lead-type="form_submit" data-lead-category="landing-page" data-lead-intent="lead-form" data-lead-label="' . esc((string)($page['tracking_label'] ?? $page['title'] ?? 'Landing Page Form')) . '" data-landing-page-slug="' . esc((string)($page['slug'] ?? '')) . '" data-landing-page-id="' . esc((string)($page['id'] ?? '')) . '" data-lp-block-type="lead_form" data-lp-block-index="' . esc((string)($formTrack['block_index'] ?? '')) . '" data-lp-block-goal="' . esc((string)($formTrack['block_goal'] ?? '')) . '" data-lp-cta-role="form" data-cta-signal="lead_form_submit" data-cta-signal-label="' . esc((string)($formTrack['cta_signal_label'] ?? 'Form Submit')) . '"' . (function_exists('landing_page_ab_attrs') ? landing_page_ab_attrs('form') : '') . ' aria-label="' . esc($headline) . '">';
        echo csrf_field();
        echo '<input type="hidden" name="sumber" value="landing-page-builder">';
        echo '<input type="hidden" name="category" value="landing-page">';
        echo '<input type="hidden" name="intent" value="lead-form">';
        echo '<input type="hidden" name="label" value="' . esc((string)($page['tracking_label'] ?? $page['title'] ?? 'Landing Page Form')) . '">';
        echo '<input type="hidden" name="item_title" value="' . esc((string)($page['title'] ?? 'Landing Page')) . '">';
        echo '<input type="hidden" name="item_url" value="' . esc(landing_page_url((string)($page['slug'] ?? ''))) . '">';
        echo '<input type="hidden" name="landing_page_slug" value="' . esc((string)($page['slug'] ?? '')) . '">';
        echo '<input type="hidden" name="landing_page_id" value="' . esc((string)($page['id'] ?? '')) . '">';
        echo '<input type="hidden" name="page_path" value="' . esc(current_uri() . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . (string)$_SERVER['QUERY_STRING'] : '')) . '">';
        if (function_exists('landing_page_ab_hidden_inputs')) { echo landing_page_ab_hidden_inputs('form'); }
        echo '<input type="hidden" name="lp_success_text" value="' . esc($successText) . '">';
        echo '<input type="hidden" name="lp_form_name" value="' . esc($formName) . '">';
        if ($leadSegment !== '') { echo '<input type="hidden" name="lead_segment" value="' . esc($leadSegment) . '">'; }
        if ($leadTags !== '') { echo '<input type="hidden" name="lead_tags" value="' . esc($leadTags) . '">'; }
        if ($leadPriority !== '') { echo '<input type="hidden" name="lead_priority" value="' . esc($leadPriority) . '">'; }
        if ($leadStage !== '') { echo '<input type="hidden" name="lead_stage" value="' . esc($leadStage) . '">'; }
        if ($leadScore !== '0') { echo '<input type="hidden" name="lead_score" value="' . esc($leadScore) . '">'; }
        if (($block['mailketing_list_id'] ?? '') !== '') {
            echo '<input type="hidden" name="mailketing_list_id" value="' . esc((string)$block['mailketing_list_id']) . '">';
        }
        if (!in_array('need', $names, true)) {
            echo '<input type="hidden" name="need" value="' . esc($needDefault) . '">';
        }
        echo '<div class="inquiry-hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
        echo '<div class="lp-form-grid">';
        foreach ($fields as $field) {
            landing_page_render_form_field($field);
        }
        echo '</div>';
        echo '<label class="form-consent-check lp-form-consent"><input type="checkbox" name="consent_contact" value="1" required><span>' . esc($consentText) . '</span></label>';
        echo '<div class="inquiry-form__actions lp-form-actions"><button class="lp-btn" type="submit">' . esc($submitText) . '</button><small>Data masuk ke inbox admin dan dapat diteruskan ke Mailketing/Fonnte sesuai pengaturan integrasi.</small></div>';
        echo '<div class="inquiry-form__status" role="status" aria-live="polite"></div>';
        echo '</form></div></div></section>';
    }
}


if (!function_exists('landing_page_block_schema_items')) {
    function landing_page_block_schema_items(array $page, string $canonical = ''): array
    {
        $faqItems = [];
        $offerItems = [];
        $blocks = (array)($page['blocks'] ?? []);

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string)($block['type'] ?? '');
            $items = (array)($block['items'] ?? []);

            if ($type === 'faq') {
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $question = trim(strip_tags((string)($item['question'] ?? $item['title'] ?? '')));
                    $answer = trim(strip_tags((string)($item['answer'] ?? $item['text'] ?? '')));
                    if ($question !== '' && $answer !== '') {
                        $faqItems[] = ['question' => $question, 'answer' => $answer];
                    }
                }
            }

            if ($type === 'pricing_cards') {
                $buttonUrl = landing_page_clean_url((string)($block['button_url'] ?? ''));
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $name = trim(strip_tags((string)($item['title'] ?? '')));
                    $priceText = trim(strip_tags((string)($item['price'] ?? 'Konsultasi')));
                    $features = [];
                    foreach ((array)($item['features'] ?? []) as $feature) {
                        $featureText = trim(strip_tags(is_array($feature) ? (string)($feature['title'] ?? $feature['text'] ?? '') : (string)$feature));
                        if ($featureText !== '') {
                            $features[] = $featureText;
                        }
                    }
                    if ($name === '' && $priceText === '') {
                        continue;
                    }
                    $offerItems[] = [
                        '@type' => 'Offer',
                        'name' => $name !== '' ? $name : 'Paket Landing Page',
                        'description' => implode(', ', array_slice($features, 0, 6)),
                        'availability' => 'https://schema.org/InStock',
                        'url' => $buttonUrl !== '' ? $buttonUrl : ($canonical !== '' ? $canonical : landing_page_url((string)($page['slug'] ?? ''))),
                        'priceSpecification' => [
                            '@type' => 'PriceSpecification',
                            'priceCurrency' => 'IDR',
                            'description' => $priceText !== '' ? $priceText : 'Konsultasi',
                        ],
                    ];
                }
            }
        }

        return ['faq' => array_slice($faqItems, 0, 20), 'offers' => array_slice($offerItems, 0, 20)];
    }
}

if (!function_exists('landing_page_register_block_schemas')) {
    function landing_page_register_block_schemas(array $page, string $canonical = '', string $description = ''): void
    {
        if (!function_exists('add_schema')) {
            return;
        }
        $items = landing_page_block_schema_items($page, $canonical);
        $canonical = $canonical !== '' ? $canonical : landing_page_url((string)($page['slug'] ?? ''));

        if (!empty($items['faq']) && count($items['faq']) >= 2 && function_exists('faq_schema')) {
            faq_schema($items['faq']);
        }

        if (!empty($items['offers'])) {
            $list = [];
            foreach ($items['offers'] as $index => $offer) {
                $list[] = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => $offer,
                ];
            }
            add_schema([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => (string)($page['title'] ?? 'Paket Penawaran'),
                'description' => $description,
                'url' => $canonical,
                'itemListElement' => $list,
            ]);
        }
    }
}

if (!function_exists('landing_page_render_blocks')) {
    function landing_page_render_blocks(array $page): void
    {
        foreach ((array)($page['blocks'] ?? []) as $index => $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string)($block['type'] ?? 'text');
            $headline = landing_page_block_text($block, 'headline');
            $text = landing_page_block_text($block, 'text');
            $subheadline = landing_page_block_text($block, 'subheadline');
            $items = (array)($block['items'] ?? []);

            if ($type === 'hero_offer') {
                $heroImage = trim((string)($block['image'] ?? ''));
                $heroLayout = (string)($block['hero_layout'] ?? 'auto');
                $heroPosition = (string)($block['hero_position'] ?? 'right');
                $heroButtonText = trim((string)($block['primary_text'] ?? ''));
                $heroButtonUrl = trim((string)($block['primary_url'] ?? ''));
                $heroSecondaryText = trim((string)($block['secondary_text'] ?? ''));
                $heroSecondaryUrl = trim((string)($block['secondary_url'] ?? ''));
                $hasHeroImage = $heroImage !== '';
                $hasHeroCopy = trim((string)($block['eyebrow'] ?? '')) !== '' || $headline !== '' || $subheadline !== '' || $heroButtonText !== '' || $heroSecondaryText !== '';
                $showHeroImage = $hasHeroImage && $heroLayout !== 'text_only';
                $showHeroCopy = $hasHeroCopy && $heroLayout !== 'media_only';
                if (!$showHeroImage && !$showHeroCopy) { $showHeroCopy = true; }
                $heroModeClass = ($showHeroImage && $showHeroCopy) ? 'split' : ($showHeroImage ? 'media-only' : 'text-only');
                $heroPositionClass = ($heroModeClass === 'split' && $heroPosition === 'right') ? ' lp-hero__grid--media-right' : '';
                echo '<section class="lp-section lp-hero" data-landing-block="hero_offer"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'hero_offer') . '>';
                echo '<div class="container lp-hero__grid lp-hero__grid--' . esc($heroModeClass) . $heroPositionClass . '">';
                if ($showHeroImage) {
                    echo '<div class="lp-hero__media"><img src="' . esc($heroImage) . '" alt="' . esc((string)($block['image_alt'] ?? $headline ?: $page['title'])) . '" loading="eager" fetchpriority="high"></div>';
                }
                if ($showHeroCopy) {
                    echo '<div class="lp-hero__copy">';
                    echo landing_page_section_head_html($block, $headline, $subheadline, 'h1');
                    echo '<div class="lp-actions">';
                    landing_page_render_button((string)($block['primary_text'] ?? 'Konsultasi WhatsApp'), (string)($block['primary_url'] ?? ''), $page, 'primary', landing_page_button_tracking_context($page, $block, $index, 'primary', 'primary', (string)($block['primary_text'] ?? 'Hero CTA')));
                    landing_page_render_button((string)($block['secondary_text'] ?? ''), (string)($block['secondary_url'] ?? ''), $page, 'secondary', landing_page_button_tracking_context($page, $block, $index, 'secondary', 'secondary', (string)($block['secondary_text'] ?? 'Hero Secondary CTA')));
                    echo '</div></div>';
                }
                echo '</div></section>';
                continue;
            }

            if ($type === 'pain_points') {
                echo '<section class="lp-section lp-pain" data-landing-block="pain_points"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'pain_points') . '><div class="container">' . landing_page_section_head_html($block, $headline, $text) . '<div class="lp-card-grid"' . landing_page_grid_style_attr($items) . '>';
                foreach ($items as $item) {
                    $itemText = is_array($item) ? (string)($item['text'] ?? $item['title'] ?? '') : (string)$item;
                    if ($itemText !== '') { echo '<div class="lp-card lp-card--pain"' . (is_array($item) ? landing_page_item_style_attrs($item) : '') . '><strong>✕</strong><p>' . esc($itemText) . '</p></div>'; }
                }
                echo '</div></div></section>';
                continue;
            }

            if ($type === 'benefits' || $type === 'product_highlight') {
                echo '<section class="lp-section lp-benefits" data-landing-block="' . esc($type) . '"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, $type) . '><div class="container">' . landing_page_section_head_html($block, $headline, $text) . '<div class="lp-card-grid"' . landing_page_grid_style_attr($items) . '>';
                foreach ($items as $item) {
                    $title = is_array($item) ? (string)($item['title'] ?? '') : (string)$item;
                    $body = is_array($item) ? (string)($item['text'] ?? '') : '';
                    $image = is_array($item) ? (string)($item['image'] ?? '') : '';
                    $imageAlt = is_array($item) ? (string)($item['image_alt'] ?? $title) : $title;
                    if ($title !== '' || $body !== '' || $image !== '') {
                        echo '<article class="lp-card lp-card--media"' . landing_page_item_style_attrs($item) . '>';
                        if ($image !== '') { echo '<img class="lp-card__image" src="' . esc($image) . '" alt="' . esc($imageAlt) . '" loading="lazy">'; }
                        if ($title !== '') { echo '<h3>' . esc($title) . '</h3>'; }
                        if ($body !== '') { echo '<p>' . esc($body) . '</p>'; }
                        echo '</article>';
                    }
                }
                echo '</div></div></section>';
                continue;
            }

            if ($type === 'pricing_cards') {
                echo '<section class="lp-section lp-pricing" data-landing-block="pricing_cards"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'pricing_cards') . '><div class="container">' . landing_page_section_head_html($block, $headline, $text) . '<div class="lp-pricing-grid"' . landing_page_grid_style_attr($items) . '>';
                foreach ($items as $item) {
                    if (!is_array($item)) { continue; }
                    $itemTitle = trim((string)($item['title'] ?? ''));
                    $itemPrice = trim((string)($item['price'] ?? ''));
                    echo '<article class="lp-price-card"' . landing_page_item_style_attrs($item) . '>';
                    if ($itemTitle !== '') { echo '<h3>' . esc($itemTitle) . '</h3>'; }
                    if ($itemPrice !== '') { echo '<div class="lp-price">' . esc($itemPrice) . '</div>'; }
                    echo '<ul>';
                    foreach ((array)($item['features'] ?? []) as $feature) {
                        $featureText = trim(is_array($feature) ? (string)($feature['title'] ?? $feature['text'] ?? '') : (string)$feature);
                        if ($featureText !== '') { echo '<li>' . esc($featureText) . '</li>'; }
                    }
                    echo '</ul>';
                    $pricingButtonText = trim((string)($item['button_text'] ?? $block['button_text'] ?? ''));
                    $pricingButtonUrl = trim((string)($item['button_url'] ?? $block['button_url'] ?? ''));
                    landing_page_render_button($pricingButtonText, $pricingButtonUrl, $page, 'primary', landing_page_button_tracking_context($page, $block, $index, 'pricing', 'primary', $itemTitle !== '' ? $itemTitle : $pricingButtonText));
                    echo '</article>';
                }
                echo '</div></div></section>';
                continue;
            }

            if ($type === 'countdown_timer') {
                $deadline = trim((string)($block['countdown_deadline'] ?? ''));
                $timezone = trim((string)($block['countdown_timezone'] ?? 'WIB'));
                $expiredText = landing_page_block_text($block, 'expired_text');
                $note = landing_page_block_text($block, 'countdown_note');
                $buttonText = trim((string)($block['button_text'] ?? 'Ambil Promo Sekarang'));
                $buttonUrl = trim((string)($block['button_url'] ?? ''));
                echo '<section class="lp-section lp-countdown" data-landing-block="countdown_timer"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'countdown_timer') . '><div class="container lp-narrow"><div class="lp-countdown-card" data-lp-countdown="1" data-deadline="' . esc($deadline) . '" data-expired-text="' . esc($expiredText ?: 'Promo sudah berakhir. Hubungi admin untuk info terbaru.') . '">';
                echo landing_page_section_head_html($block, $headline, $text);
                echo '<div class="lp-countdown-timer" aria-live="polite"><div><strong data-countdown-days>00</strong><span>Hari</span></div><div><strong data-countdown-hours>00</strong><span>Jam</span></div><div><strong data-countdown-minutes>00</strong><span>Menit</span></div><div><strong data-countdown-seconds>00</strong><span>Detik</span></div></div>';
                if ($timezone !== '') { echo '<p class="lp-countdown-note">Zona waktu: ' . esc($timezone) . ($note !== '' ? ' · ' . esc($note) : '') . '</p>'; } elseif ($note !== '') { echo '<p class="lp-countdown-note">' . esc($note) . '</p>'; }
                if ($buttonText !== '' && $buttonUrl !== '') { echo '<div class="lp-actions lp-countdown-actions">'; landing_page_render_button($buttonText, $buttonUrl, $page, 'primary', landing_page_button_tracking_context($page, $block, $index, 'countdown', 'primary', $buttonText)); echo '</div>'; }
                echo '<p class="lp-countdown-expired" data-countdown-expired hidden>' . esc($expiredText ?: 'Promo sudah berakhir. Hubungi admin untuk info terbaru.') . '</p>';
                echo '</div></div></section>';
                continue;
            }

            if ($type === 'testimonial') {
                echo '<section class="lp-section lp-testimonial" data-landing-block="testimonial"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'testimonial') . '><div class="container">' . landing_page_section_head_html($block, $headline, $text) . '<div class="lp-card-grid"' . landing_page_grid_style_attr($items) . '>';
                foreach ($items as $item) {
                    $quote = is_array($item) ? (string)($item['text'] ?? $item['answer'] ?? '') : (string)$item;
                    $name = is_array($item) ? (string)($item['title'] ?? '') : '';
                    if ($quote !== '') { echo '<blockquote class="lp-card"' . landing_page_item_style_attrs($item) . '><p>“' . esc($quote) . '”</p>' . ($name !== '' ? '<cite>' . esc($name) . '</cite>' : '') . '</blockquote>'; }
                }
                echo '</div></div></section>';
                continue;
            }

            if ($type === 'faq') {
                echo '<section class="lp-section lp-faq" data-landing-block="faq"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'faq') . '><div class="container lp-narrow">' . landing_page_section_head_html($block, $headline, $text);
                foreach ($items as $item) {
                    if (!is_array($item)) { continue; }
                    $q = trim((string)($item['question'] ?? $item['title'] ?? ''));
                    $a = trim((string)($item['answer'] ?? $item['text'] ?? ''));
                    if ($q === '' && $a === '') { continue; }
                    echo '<details class="lp-faq-item"' . landing_page_item_style_attrs($item) . '>' . ($q !== '' ? '<summary>' . esc($q) . '</summary>' : '') . ($a !== '' ? '<p>' . esc($a) . '</p>' : '') . '</details>';
                }
                echo '</div></section>';
                continue;
            }

            if ($type === 'custom_menu') {
                $menuStyle = (string)($block['menu_style'] ?? '');
                if ($menuStyle === '') { $menuStyle = !empty($block['engine_internal_links']) ? 'cards' : 'header'; }
                $menuStyle = $menuStyle === 'header' ? 'header' : 'cards';
                $menuPosition = (string)($block['menu_position'] ?? 'normal');
                if (!in_array($menuPosition, ['normal','sticky','fixed'], true)) { $menuPosition = 'normal'; }
                $menuAlign = landing_page_clean_choice((string)($block['menu_align'] ?? ''), ['left', 'center', 'right']);
                $logoAlign = landing_page_clean_choice((string)($block['logo_align'] ?? ''), ['left', 'center', 'right']);
                $sectionClass = 'lp-section lp-custom-menu ' . ($menuStyle === 'header' ? 'lp-custom-menu--header lp-custom-menu--' . $menuPosition : 'lp-custom-menu--cards');
                $sectionAttrs = ' data-landing-block="custom_menu" data-lp-menu-style="' . esc($menuStyle) . '" data-lp-menu-position="' . esc($menuPosition) . '"';
                if ($menuAlign !== '') { $sectionAttrs .= ' data-lp-menu-align="' . esc($menuAlign) . '"'; }
                if ($logoAlign !== '') { $sectionAttrs .= ' data-lp-logo-align="' . esc($logoAlign) . '"'; }

                $logoLinks = [];
                $menuLinks = [];
                foreach ($items as $item) {
                    if (!is_array($item)) { continue; }
                    $rawTitle = trim((string)($item['title'] ?? ''));
                    $url = landing_page_clean_url(trim((string)($item['url'] ?? $item['link_url'] ?? '#'))) ?: '#';
                    $body = trim((string)($item['text'] ?? ''));
                    $logo = landing_page_clean_url(trim((string)($item['image'] ?? '')));
                    $logoAlt = trim((string)($item['image_alt'] ?? $rawTitle ?: 'Logo menu'));
                    $itemType = (string)($item['item_type'] ?? '');
                    if ($itemType === '' && $logo !== '' && $rawTitle === '') { $itemType = 'logo'; }
                    $itemType = $itemType === 'logo' ? 'logo' : 'link';
                    if ($itemType !== 'logo' && $rawTitle === '') { $rawTitle = 'Menu'; }
                    $logoPosition = landing_page_clean_choice((string)($item['logo_position'] ?? $logoAlign), ['left', 'center', 'right']);
                    $itemClass = 'lp-custom-menu__item' . ($itemType === 'logo' ? ' lp-custom-menu__item--logo lp-custom-menu__logo--brand' : '');
                    if ($logoPosition !== '') { $itemClass .= ' lp-custom-menu__logo--' . $logoPosition; }

                    $linkHtml = '<a class="' . esc($itemClass) . '" href="' . esc($url) . '"' . landing_page_item_style_attrs($item) . '>';
                    if ($logo !== '') { $linkHtml .= '<img class="lp-custom-menu__logo" src="' . esc($logo) . '" alt="' . esc($logoAlt ?: $rawTitle ?: 'Logo menu') . '" loading="lazy">'; }
                    if ($itemType === 'logo') {
                        if ($logo === '' && $rawTitle !== '') { $linkHtml .= '<strong>' . esc($rawTitle) . '</strong>'; }
                    } else {
                        $linkHtml .= '<strong>' . esc($rawTitle) . '</strong>' . (($body !== '' && $menuStyle !== 'header') ? '<span>' . esc($body) . '</span>' : '');
                    }
                    $linkHtml .= '</a>';

                    if ($menuStyle === 'header' && $itemType === 'logo') {
                        $logoLinks[] = $linkHtml;
                    } else {
                        $menuLinks[] = $linkHtml;
                    }
                }

                echo '<section class="' . esc($sectionClass) . '"' . $sectionAttrs . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'custom_menu') . '><div class="container ' . ($menuStyle === 'header' ? 'lp-custom-menu__bar' : 'lp-narrow') . '">';
                if ($menuStyle !== 'header') { echo landing_page_section_head_html($block, $headline, $text); }
                if ($menuStyle === 'header') {
                    echo '<nav class="lp-custom-menu__nav lp-custom-menu__nav--structured" aria-label="Menu khusus landing page">';
                    if ($logoLinks) { echo '<div class="lp-custom-menu__logo-slot">' . implode('', $logoLinks) . '</div>'; }
                    if ($menuLinks) { echo '<div class="lp-custom-menu__links">' . implode('', $menuLinks) . '</div>'; }
                    echo '</nav>';
                } else {
                    echo '<nav class="lp-custom-menu__nav" aria-label="Menu khusus landing page">' . implode('', array_merge($logoLinks, $menuLinks)) . '</nav>';
                }
                echo '</div></section>';
                continue;
            }

            if ($type === 'media') {
                $image = trim((string)($block['image'] ?? ''));
                $layout = (string)($block['media_layout'] ?? 'auto');
                $position = (string)($block['media_position'] ?? 'left');
                $hasImage = $image !== '';
                $buttonText = trim((string)($block['button_text'] ?? ''));
                $buttonUrl = trim((string)($block['button_url'] ?? ''));
                $hasCopy = $headline !== '' || $text !== '' || $buttonText !== '' || $buttonUrl !== '';
                $showImage = $hasImage && $layout !== 'text_only';
                $showCopy = $hasCopy && $layout !== 'media_only';
                if (!$showImage && !$showCopy) { continue; }
                $modeClass = ($showImage && $showCopy) ? 'split' : ($showImage ? 'media-only' : 'text-only');
                $posClass = ($modeClass === 'split' && $position === 'right') ? ' lp-media-block__grid--media-right' : '';
                echo '<section class="lp-section lp-media-block" data-landing-block="media"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'media') . '><div class="container lp-media-block__grid lp-media-block__grid--' . esc($modeClass) . $posClass . '">';
                if ($showImage) { echo '<div class="lp-media-block__media"><img src="' . esc($image) . '" alt="' . esc((string)($block['image_alt'] ?? $headline)) . '" loading="lazy"></div>'; }
                if ($showCopy) {
                    echo '<div class="lp-media-block__copy">';
                    if ($headline !== '') { echo '<h2>' . esc($headline) . '</h2>'; }
                    if ($text !== '') { echo '<p>' . nl2br(esc($text)) . '</p>'; }
                    landing_page_render_button($buttonText, $buttonUrl, $page, 'primary', landing_page_button_tracking_context($page, $block, $index, 'media', 'primary', $buttonText));
                    echo '</div>';
                }
                echo '</div></section>';
                continue;
            }

            if ($type === 'free_cards') {
                echo '<section class="lp-section lp-free-cards" data-landing-block="free_cards"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'free_cards') . '><div class="container">' . landing_page_section_head_html($block, $headline, $text);
                echo '<div class="lp-card-grid"' . landing_page_grid_style_attr($items) . '>';
                foreach ($items as $item) {
                    if (!is_array($item)) { continue; }
                    $title = (string)($item['title'] ?? '');
                    $body = (string)($item['text'] ?? '');
                    $image = (string)($item['image'] ?? '');
                    $imageAlt = (string)($item['image_alt'] ?? $title);
                    $url = (string)($item['url'] ?? $item['link_url'] ?? '');
                    echo '<article class="lp-card lp-card--media lp-free-card"' . landing_page_item_style_attrs($item) . '>';
                    if ($image !== '') { echo '<img class="lp-card__image" src="' . esc($image) . '" alt="' . esc($imageAlt) . '" loading="lazy">'; }
                    if ($title !== '') { echo '<h3>' . esc($title) . '</h3>'; }
                    if ($body !== '') { echo '<p>' . esc($body) . '</p>'; }
                    if ($url !== '') { echo '<a class="lp-inline-link" href="' . esc($url) . '">Lihat detail</a>'; }
                    echo '</article>';
                }
                echo '</div></div></section>';
                continue;
            }

            if ($type === 'html_block') {
                $html = trim((string)($block['html'] ?? ''));
                $builderSource = trim((string)($block['builder_source'] ?? 'WordPress'));
                echo '<section class="lp-section lp-html-block" data-landing-block="html_block" data-builder-source="' . esc($builderSource) . '"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'html_block') . '><div class="container lp-html-block__inner">';
                echo landing_page_section_head_html($block, $headline, $text);
                if ($html !== '') { echo '<div class="lp-html-block__content">' . $html . '</div>'; }
                if ((string)($block['complex_widgets'] ?? '') !== '') { echo '<p class="lp-html-block__note">Catatan import: widget kompleks perlu dicek manual: ' . esc((string)$block['complex_widgets']) . '.</p>'; }
                echo '</div></section>';
                continue;
            }

            if ($type === 'lead_form') {
                landing_page_render_lead_form($block, $page, $index);
                continue;
            }

            if ($type === 'cta') {
                echo '<section class="lp-section lp-final-cta" data-landing-block="cta"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'cta') . '><div class="container lp-narrow">' . landing_page_section_head_html($block, $headline, $text);
                echo '<div class="lp-actions lp-actions--center">';
                landing_page_render_button((string)($block['button_text'] ?? 'Chat Admin'), (string)($block['button_url'] ?? ''), $page, 'primary', landing_page_button_tracking_context($page, $block, $index, 'closing', 'primary', (string)($block['button_text'] ?? 'Closing CTA')));
                echo '</div></div></section>';
                continue;
            }

            echo '<section class="lp-section lp-text" data-landing-block="text"' . landing_page_block_style_attrs($block) . landing_page_block_tracking_attrs($page, $block, $index, 'text') . '><div class="container lp-narrow">';
            if ($headline !== '') { echo '<h2>' . esc($headline) . '</h2>'; }
            if ($text !== '') { echo '<p>' . nl2br(esc($text)) . '</p>'; }
            echo '</div></section>';
        }
    }
}
