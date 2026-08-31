<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| AI Copy / SEO Content Assistant Lokal
|--------------------------------------------------------------------------
| Asisten copywriting lokal untuk membantu membuat konten landing page.
| Tidak mengirim data ke layanan luar dan tetap berjalan dari server website.
|--------------------------------------------------------------------------
*/

if (!function_exists('landing_page_ai_assistant_tones')) {
    function landing_page_ai_assistant_tones(): array
    {
        return [
            'friendly' => 'Ramah & edukatif',
            'direct' => 'Jualan langsung',
            'premium' => 'Premium & tepercaya',
            'urgent' => 'Promo terbatas',
            'local' => 'Local SEO',
        ];
    }
}

if (!function_exists('landing_page_ai_assistant_segments')) {
    function landing_page_ai_assistant_segments(): array
    {
        return [
            'layanan' => [
                'label' => 'Layanan',
                'keywords' => ['paket layanan', 'jasa layanan', 'layanan paket', 'layanan', 'layanan area lokal'],
                'pain_points' => ['Bingung memilih paket layanan yang sesuai kebutuhan keluarga.', 'Butuh estimasi biaya, jadwal, dan area layanan yang jelas.', 'Ingin proses konsultasi lebih cepat tanpa bolak-balik tanya.'],
                'benefits' => ['Admin membantu rekomendasi paket sesuai kebutuhan.', 'Form lead membuat follow-up lebih rapi dan terarah.', 'Calon customer bisa mulai dari konsultasi tanpa wajib langsung order.'],
            ],
            'produk' => [
                'label' => 'Produk',
                'keywords' => ['produk fisik', 'paket produk', 'produk', 'paket produk', 'produk area lokal'],
                'pain_points' => ['Bingung memilih produk sesuai budget.', 'Butuh cek stok, area layanan, dan opsi pengiriman/penyaluran.', 'Ingin penawaran jelas sebelum mengambil keputusan.'],
                'benefits' => ['Pilihan ekonomis, medium, dan premium lebih mudah dibandingkan.', 'Admin membantu cek stok dan rekomendasi produk/layanan.', 'CTA WhatsApp dan form siap dipakai untuk campaign iklan.'],
            ],
            'lokasi' => [
                'label' => 'Area Layanan',
                'keywords' => ['area layanan produk', 'area layanan produk fisik', 'area layanan paket', 'area layanan produk'],
                'pain_points' => ['Calon customer ingin memastikan area layanan sebelum konsultasi.', 'Informasi lokasi membantu mempercepat rekomendasi stok.', 'Butuh navigasi lokal yang jelas untuk SEO dan customer.'],
                'benefits' => ['Konten lebih relevan untuk pencarian lokal.', 'Admin bisa follow-up berdasarkan area customer.', 'Internal link lokasi membantu SEO landing page.'],
            ],
            'promo' => [
                'label' => 'Promo / Campaign',
                'keywords' => ['promo paket', 'penawaran khusus', 'konsultasi cepat', 'campaign WhatsApp'],
                'pain_points' => ['Traffic iklan sering masuk tapi belum yakin untuk klik.', 'Copy penawaran perlu lebih jelas dan low barrier.', 'Lead perlu segmentasi agar admin lebih cepat follow-up.'],
                'benefits' => ['Headline, CTA, dan form dibuat lebih fokus konversi.', 'Tes variasi halaman membantu membaca pesan mana yang lebih efektif.', 'Laporan membantu melihat bagian mana yang perlu diperbaiki.'],
            ],
        ];
    }
}

if (!function_exists('landing_page_ai_assistant_seed')) {
    function landing_page_ai_assistant_seed(): array
    {
        return [
            'version' => 'Template',
            'label' => 'AI Copy Assistant / SEO Content Assistant',
            'local_only' => true,
            'tones' => landing_page_ai_assistant_tones(),
            'segments' => landing_page_ai_assistant_segments(),
            'locations' => [
                'Jakarta', 'Jakarta Selatan', 'Tangerang Selatan', 'Depok', 'Jakarta', 'Bekasi', 'Bandung', 'Surabaya', 'Bali'
            ],
            'cta_templates' => [
                'Konsultasi Sekarang',
                'Chat Admin via WhatsApp',
                'Cek Paket & Ketersediaan',
                'Minta Rekomendasi Paket',
                'Isi Form Konsultasi',
            ],
            'faq_templates' => [
                ['question' => 'Apakah bisa konsultasi dulu?', 'answer' => 'Bisa. Admin akan membantu menjelaskan pilihan paket, stok, lokasi, dan langkah berikutnya sesuai kebutuhan Anda.'],
                ['question' => 'Apakah wajib langsung order?', 'answer' => 'Tidak. Anda bisa mulai dari konsultasi agar lebih yakin sebelum menentukan pilihan.'],
                ['question' => 'Bagaimana cara admin follow-up?', 'answer' => 'Data dari form atau klik WhatsApp dipakai admin untuk follow-up dengan informasi yang lebih terarah.'],
                ['question' => 'Apakah bisa cek area layanan?', 'answer' => 'Bisa. Tulis lokasi atau area kebutuhan agar admin bisa mengecek opsi layanan yang relevan.'],
            ],
        ];
    }
}

if (!function_exists('landing_page_ai_assistant_health')) {
    function landing_page_ai_assistant_health(array $page): array
    {
        $blocks = isset($page['blocks']) && is_array($page['blocks']) ? $page['blocks'] : [];
        $types = array_map(static fn($block): string => is_array($block) ? (string)($block['type'] ?? '') : '', $blocks);
        $score = 0;
        $issues = [];

        if (trim((string)($page['title'] ?? '')) !== '') { $score += 10; } else { $issues[] = 'Judul landing page belum diisi.'; }
        if (trim((string)($page['meta_title'] ?? '')) !== '') { $score += 10; } else { $issues[] = 'Meta title belum diisi.'; }
        if (strlen(trim((string)($page['meta_description'] ?? ''))) >= 90) { $score += 15; } else { $issues[] = 'Meta description sebaiknya minimal 90 karakter.'; }
        if (in_array('hero_offer', $types, true)) { $score += 15; } else { $issues[] = 'Tambahkan hero offer yang jelas.'; }
        if (in_array('benefits', $types, true) || in_array('free_cards', $types, true)) { $score += 10; } else { $issues[] = 'Tambahkan benefit/card alasan memilih layanan.'; }
        if (in_array('lead_form', $types, true)) { $score += 15; } else { $issues[] = 'Tambahkan form lead agar follow-up lebih rapi.'; }
        if (in_array('cta', $types, true)) { $score += 10; } else { $issues[] = 'Tambahkan CTA penutup.'; }
        if (in_array('faq', $types, true)) { $score += 10; } else { $issues[] = 'Tambahkan FAQ untuk bantu SEO dan pertanyaan customer.'; }
        if (trim((string)($page['tracking_label'] ?? '')) !== '') { $score += 5; } else { $issues[] = 'Tracking label belum diisi.'; }

        return [
            'score' => min(100, $score),
            'issues' => $issues,
        ];
    }
}
