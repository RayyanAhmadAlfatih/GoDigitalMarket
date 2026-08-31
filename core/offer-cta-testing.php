<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OFFER & CTA TESTING LAB
|--------------------------------------------------------------------------
| Lightweight UMKM-friendly lab to plan, compare, and score offer/CTA
| variants before they are pushed into homepage, articles, landing pages,
| forms, trust blocks, or campaign playbooks.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('offer_cta_lab_storage_file')) {
    function offer_cta_lab_storage_file(): string
    {
        return STORAGE_PATH . '/offer-cta-testing-lab.json';
    }
}

if (!function_exists('offer_cta_lab_clean')) {
    function offer_cta_lab_clean(mixed $value, int $max = 220): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($text, 0, $max, 'UTF-8')
            : substr($text, 0, $max);
    }
}

if (!function_exists('offer_cta_lab_multiline')) {
    function offer_cta_lab_multiline(mixed $value, int $max = 900): string
    {
        $text = trim(strip_tags((string)$value));
        $text = preg_replace("/\r\n|\r/", "\n", $text) ?: '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?: '';
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr')
            ? mb_substr($text, 0, $max, 'UTF-8')
            : substr($text, 0, $max);
    }
}

if (!function_exists('offer_cta_lab_id')) {
    function offer_cta_lab_id(string $value = ''): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]+/', '-', $value) ?: '';
        $value = trim($value, '-');

        if ($value === '') {
            $value = 'offer-' . substr(md5((string)microtime(true) . random_int(1000, 9999)), 0, 12);
        }

        return substr($value, 0, 120);
    }
}

if (!function_exists('offer_cta_lab_status_options')) {
    function offer_cta_lab_status_options(): array
    {
        return [
            'draft' => 'Draft',
            'testing' => 'Sedang Diuji',
            'active' => 'Aktif Dipakai',
            'winner' => 'Winner',
            'paused' => 'Ditahan',
            'archived' => 'Arsip',
        ];
    }
}

if (!function_exists('offer_cta_lab_goal_options')) {
    function offer_cta_lab_goal_options(): array
    {
        return [
            'whatsapp_lead' => 'Lead WhatsApp',
            'checkout' => 'Checkout / Order',
            'form_lead' => 'Isi Form',
            'trust_building' => 'Bangun Trust',
            'article_to_offer' => 'Artikel ke Penawaran',
            'campaign_boost' => 'Campaign Boost',
        ];
    }
}

if (!function_exists('offer_cta_lab_placement_options')) {
    function offer_cta_lab_placement_options(): array
    {
        return [
            'homepage_hero' => 'Homepage Hero',
            'homepage_mid' => 'Homepage Tengah',
            'homepage_bottom' => 'Homepage Bawah',
            'article_inline' => 'Artikel / Blog',
            'landing_page' => 'Landing Page',
            'product_detail' => 'Detail Produk/Jasa',
            'form_page' => 'Form / Lead Magnet',
            'trust_block' => 'Trust & Conversion Block',
            'follow_up' => 'Follow-up WA/Email',
        ];
    }
}

if (!function_exists('offer_cta_lab_channel_options')) {
    function offer_cta_lab_channel_options(): array
    {
        return [
            'website' => 'Website',
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'seo_content' => 'SEO Content',
            'landing_page' => 'Landing Page',
            'campaign' => 'Campaign',
        ];
    }
}

if (!function_exists('offer_cta_lab_default_settings')) {
    function offer_cta_lab_default_settings(): array
    {
        $now = date(DATE_ATOM);

        return [
            'enabled' => true,
            'active_variant_id' => '',
            'testing_note' => 'Pakai lab ini untuk membandingkan beberapa offer dan CTA sebelum dipakai di halaman penting.',
            'variants' => [
                [
                    'id' => 'konsultasi-cepat-wa',
                    'title' => 'Konsultasi Cepat via WhatsApp',
                    'status' => 'testing',
                    'goal' => 'whatsapp_lead',
                    'placement' => 'homepage_hero',
                    'channel' => 'website',
                    'audience' => 'Calon customer yang baru mengenal bisnis dan butuh jawaban cepat.',
                    'hook' => 'Bantu pengunjung merasa mudah mulai bertanya tanpa harus langsung beli.',
                    'headline' => 'Butuh rekomendasi yang paling cocok?',
                    'subheadline' => 'Ceritakan kebutuhan Anda, tim kami bantu arahkan pilihan terbaik dengan bahasa yang simpel.',
                    'cta_label' => 'Konsultasi via WhatsApp',
                    'cta_url' => '/kontak',
                    'proof_note' => 'Respons cepat, arahan jelas, dan tidak memaksa langsung checkout.',
                    'hypothesis' => 'CTA konsultasi ringan akan menaikkan klik WhatsApp dari pengunjung yang belum siap membeli.',
                    'impressions' => 0,
                    'clicks' => 0,
                    'leads' => 0,
                    'orders' => 0,
                    'notes' => 'Cocok untuk homepage, artikel edukasi, dan halaman jasa.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 'cek-paket-terbaik',
                    'title' => 'Cek Paket Terbaik',
                    'status' => 'draft',
                    'goal' => 'checkout',
                    'placement' => 'product_detail',
                    'channel' => 'website',
                    'audience' => 'Pengunjung yang sudah membandingkan produk atau layanan.',
                    'hook' => 'Dorong pengunjung yang sudah tertarik agar lanjut ke pilihan paket atau checkout.',
                    'headline' => 'Pilih paket yang paling pas untuk kebutuhan Anda',
                    'subheadline' => 'Lihat opsi produk/jasa, bandingkan manfaatnya, lalu lanjutkan ke chat atau checkout.',
                    'cta_label' => 'Cek Paket Sekarang',
                    'cta_url' => '/katalog',
                    'proof_note' => 'Katalog rapi membantu customer merasa lebih yakin sebelum kontak.',
                    'hypothesis' => 'CTA yang mengarah ke katalog akan memperbaiki jalur dari konten SEO menuju produk/jasa.',
                    'impressions' => 0,
                    'clicks' => 0,
                    'leads' => 0,
                    'orders' => 0,
                    'notes' => 'Cocok untuk artikel dan homepage bagian tengah.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            'updated_at' => $now,
        ];
    }
}

if (!function_exists('offer_cta_lab_clean_url')) {
    function offer_cta_lab_clean_url(mixed $value, string $fallback = '/kontak'): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('~^(https?://|mailto:|tel:|/|#)~i', $value)) {
            return substr($value, 0, 500);
        }

        return '/' . ltrim(substr($value, 0, 500), '/');
    }
}

if (!function_exists('offer_cta_lab_variant_score')) {
    function offer_cta_lab_variant_score(array $variant): array
    {
        $impressions = max(0, (int)($variant['impressions'] ?? 0));
        $clicks = max(0, (int)($variant['clicks'] ?? 0));
        $leads = max(0, (int)($variant['leads'] ?? 0));
        $orders = max(0, (int)($variant['orders'] ?? 0));

        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0;
        $leadRate = $clicks > 0 ? round(($leads / $clicks) * 100, 2) : 0.0;
        $orderRate = $leads > 0 ? round(($orders / max(1, $leads)) * 100, 2) : 0.0;

        $quality = 0;
        $quality += trim((string)($variant['headline'] ?? '')) !== '' ? 16 : 0;
        $quality += trim((string)($variant['subheadline'] ?? '')) !== '' ? 12 : 0;
        $quality += trim((string)($variant['cta_label'] ?? '')) !== '' ? 14 : 0;
        $quality += trim((string)($variant['cta_url'] ?? '')) !== '' ? 10 : 0;
        $quality += trim((string)($variant['proof_note'] ?? '')) !== '' ? 12 : 0;
        $quality += trim((string)($variant['hypothesis'] ?? '')) !== '' ? 10 : 0;
        $quality += trim((string)($variant['audience'] ?? '')) !== '' ? 8 : 0;
        $quality += in_array((string)($variant['status'] ?? ''), ['testing', 'active', 'winner'], true) ? 8 : 0;

        $metricScore = min(10, (int)floor($ctr * 1.4))
            + min(6, (int)floor($leadRate / 2))
            + min(4, (int)floor($orderRate / 5));

        $score = max(0, min(100, $quality + $metricScore));

        return [
            'score' => $score,
            'ctr' => $ctr,
            'lead_rate' => $leadRate,
            'order_rate' => $orderRate,
            'has_data' => $impressions > 0 || $clicks > 0 || $leads > 0 || $orders > 0,
        ];
    }
}

if (!function_exists('offer_cta_lab_normalize_variant')) {
    function offer_cta_lab_normalize_variant(array $variant, int $index = 0): array
    {
        $statusOptions = offer_cta_lab_status_options();
        $goalOptions = offer_cta_lab_goal_options();
        $placementOptions = offer_cta_lab_placement_options();
        $channelOptions = offer_cta_lab_channel_options();
        $now = date(DATE_ATOM);

        $title = offer_cta_lab_clean($variant['title'] ?? '', 120);
        $headline = offer_cta_lab_clean($variant['headline'] ?? '', 160);
        $idSeed = (string)($variant['id'] ?? ($title ?: $headline ?: 'offer-' . ($index + 1)));
        $id = offer_cta_lab_id($idSeed);
        $status = (string)($variant['status'] ?? 'draft');
        $goal = (string)($variant['goal'] ?? 'whatsapp_lead');
        $placement = (string)($variant['placement'] ?? 'homepage_hero');
        $channel = (string)($variant['channel'] ?? 'website');

        $normalized = [
            'id' => $id,
            'title' => $title !== '' ? $title : 'Variant ' . ($index + 1),
            'status' => isset($statusOptions[$status]) ? $status : 'draft',
            'goal' => isset($goalOptions[$goal]) ? $goal : 'whatsapp_lead',
            'placement' => isset($placementOptions[$placement]) ? $placement : 'homepage_hero',
            'channel' => isset($channelOptions[$channel]) ? $channel : 'website',
            'audience' => offer_cta_lab_clean($variant['audience'] ?? '', 180),
            'hook' => offer_cta_lab_clean($variant['hook'] ?? '', 220),
            'headline' => $headline,
            'subheadline' => offer_cta_lab_clean($variant['subheadline'] ?? '', 260),
            'cta_label' => offer_cta_lab_clean($variant['cta_label'] ?? '', 80),
            'cta_url' => offer_cta_lab_clean_url($variant['cta_url'] ?? '/kontak'),
            'proof_note' => offer_cta_lab_clean($variant['proof_note'] ?? '', 220),
            'hypothesis' => offer_cta_lab_clean($variant['hypothesis'] ?? '', 260),
            'impressions' => max(0, (int)($variant['impressions'] ?? 0)),
            'clicks' => max(0, (int)($variant['clicks'] ?? 0)),
            'leads' => max(0, (int)($variant['leads'] ?? 0)),
            'orders' => max(0, (int)($variant['orders'] ?? 0)),
            'notes' => offer_cta_lab_multiline($variant['notes'] ?? '', 800),
            'created_at' => offer_cta_lab_clean($variant['created_at'] ?? $now, 80) ?: $now,
            'updated_at' => $now,
        ];

        return array_merge($normalized, offer_cta_lab_variant_score($normalized));
    }
}

if (!function_exists('offer_cta_lab_normalize_settings')) {
    function offer_cta_lab_normalize_settings(array $settings): array
    {
        $defaults = offer_cta_lab_default_settings();
        $variants = [];
        $seen = [];

        foreach ((array)($settings['variants'] ?? []) as $index => $variant) {
            if (!is_array($variant)) {
                continue;
            }

            $normalized = offer_cta_lab_normalize_variant($variant, (int)$index);
            if (($normalized['title'] ?? '') === '' && ($normalized['headline'] ?? '') === '') {
                continue;
            }

            $baseId = (string)$normalized['id'];
            $id = $baseId;
            $counter = 2;
            while (isset($seen[$id])) {
                $id = offer_cta_lab_id($baseId . '-' . $counter);
                $counter++;
            }
            $normalized['id'] = $id;
            $seen[$id] = true;
            $variants[] = $normalized;
        }

        if (!$variants) {
            foreach ((array)$defaults['variants'] as $index => $variant) {
                $variants[] = offer_cta_lab_normalize_variant($variant, (int)$index);
            }
        }

        usort($variants, static function (array $a, array $b): int {
            $statusWeight = ['winner' => 1, 'active' => 2, 'testing' => 3, 'draft' => 4, 'paused' => 5, 'archived' => 6];
            $weightA = $statusWeight[(string)($a['status'] ?? 'draft')] ?? 9;
            $weightB = $statusWeight[(string)($b['status'] ?? 'draft')] ?? 9;
            if ($weightA === $weightB) {
                return (int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0);
            }
            return $weightA <=> $weightB;
        });

        $activeId = offer_cta_lab_id((string)($settings['active_variant_id'] ?? ''));
        if ($activeId !== '' && !array_filter($variants, static fn(array $variant): bool => (string)($variant['id'] ?? '') === $activeId)) {
            $activeId = '';
        }

        return [
            'enabled' => array_key_exists('enabled', $settings) ? !empty($settings['enabled']) : true,
            'active_variant_id' => $activeId,
            'testing_note' => offer_cta_lab_multiline($settings['testing_note'] ?? $defaults['testing_note'], 600),
            'variants' => $variants,
            'updated_at' => offer_cta_lab_clean($settings['updated_at'] ?? date(DATE_ATOM), 80),
        ];
    }
}

if (!function_exists('offer_cta_lab_write_settings')) {
    function offer_cta_lab_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = offer_cta_lab_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(offer_cta_lab_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Offer & CTA Testing Lab belum bisa disimpan. Cek permission folder storage.');
            }
            return false;
        }

        @chmod(offer_cta_lab_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'offer-cta-testing-lab', null, 'Menyimpan varian offer dan CTA testing lab.');
        }

        return true;
    }
}

if (!function_exists('offer_cta_lab_settings')) {
    function offer_cta_lab_settings(bool $fresh = false): array
    {
        static $cached = null;

        if (!$fresh && is_array($cached)) {
            return $cached;
        }

        $file = offer_cta_lab_storage_file();
        if (!is_file($file)) {
            $cached = offer_cta_lab_normalize_settings(offer_cta_lab_default_settings());
            offer_cta_lab_write_settings($cached, false);
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = offer_cta_lab_normalize_settings(offer_cta_lab_default_settings());
            offer_cta_lab_write_settings($cached, false);
            return $cached;
        }

        $cached = offer_cta_lab_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('offer_cta_lab_settings_from_post')) {
    function offer_cta_lab_settings_from_post(array $post, array $current): array
    {
        $rawVariants = (array)($post['variants'] ?? []);
        $variants = [];

        foreach ($rawVariants as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = offer_cta_lab_clean($row['title'] ?? '', 120);
            $headline = offer_cta_lab_clean($row['headline'] ?? '', 160);
            $cta = offer_cta_lab_clean($row['cta_label'] ?? '', 80);
            if ($title === '' && $headline === '' && $cta === '') {
                continue;
            }

            $variants[] = offer_cta_lab_normalize_variant($row, (int)$index);
        }

        return offer_cta_lab_normalize_settings([
            'enabled' => !empty($post['enabled']),
            'active_variant_id' => (string)($post['active_variant_id'] ?? ($current['active_variant_id'] ?? '')),
            'testing_note' => (string)($post['testing_note'] ?? ($current['testing_note'] ?? '')),
            'variants' => $variants,
        ]);
    }
}

if (!function_exists('offer_cta_lab_add_variant')) {
    function offer_cta_lab_add_variant(array $variant): array
    {
        $settings = offer_cta_lab_settings(true);
        $settings['variants'][] = offer_cta_lab_normalize_variant($variant, count((array)$settings['variants']));
        offer_cta_lab_write_settings($settings, true);
        return offer_cta_lab_settings(true);
    }
}

if (!function_exists('offer_cta_lab_delete_variant')) {
    function offer_cta_lab_delete_variant(string $id): bool
    {
        $id = offer_cta_lab_id($id);
        $settings = offer_cta_lab_settings(true);
        $settings['variants'] = array_values(array_filter((array)$settings['variants'], static fn(array $variant): bool => (string)($variant['id'] ?? '') !== $id));
        if ((string)($settings['active_variant_id'] ?? '') === $id) {
            $settings['active_variant_id'] = '';
        }
        return offer_cta_lab_write_settings($settings, true);
    }
}

if (!function_exists('offer_cta_lab_set_winner')) {
    function offer_cta_lab_set_winner(string $id): bool
    {
        $id = offer_cta_lab_id($id);
        $settings = offer_cta_lab_settings(true);
        foreach ((array)$settings['variants'] as $index => $variant) {
            if ((string)($variant['id'] ?? '') === $id) {
                $settings['variants'][$index]['status'] = 'winner';
                $settings['active_variant_id'] = $id;
            } elseif ((string)($variant['status'] ?? '') === 'winner') {
                $settings['variants'][$index]['status'] = 'active';
            }
        }

        return offer_cta_lab_write_settings($settings, true);
    }
}

if (!function_exists('offer_cta_lab_reset')) {
    function offer_cta_lab_reset(): void
    {
        if (is_file(offer_cta_lab_storage_file())) {
            @unlink(offer_cta_lab_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'offer-cta-testing-lab', null, 'Reset Offer & CTA Testing Lab ke bawaan.');
        }
    }
}

if (!function_exists('offer_cta_lab_url_to_href')) {
    function offer_cta_lab_url_to_href(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return url('kontak');
        }
        if (preg_match('~^(https?://|mailto:|tel:|#)~i', $url)) {
            return $url;
        }
        return url(ltrim($url, '/'));
    }
}

if (!function_exists('offer_cta_lab_summary')) {
    function offer_cta_lab_summary(?array $settings = null): array
    {
        $settings = $settings ?? offer_cta_lab_settings();
        $variants = (array)($settings['variants'] ?? []);
        $statusCounts = array_fill_keys(array_keys(offer_cta_lab_status_options()), 0);
        $totalScore = 0;
        $withData = 0;
        $winner = null;
        $best = null;
        $needsAttention = [];

        foreach ($variants as $variant) {
            $status = (string)($variant['status'] ?? 'draft');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
            $totalScore += (int)($variant['score'] ?? 0);
            if (!empty($variant['has_data'])) {
                $withData++;
            }
            if ($status === 'winner') {
                $winner = $variant;
            }
            if ($best === null || (int)($variant['score'] ?? 0) > (int)($best['score'] ?? 0)) {
                $best = $variant;
            }
            if ((int)($variant['score'] ?? 0) < 55 && $status !== 'archived') {
                $needsAttention[] = $variant;
            }
        }

        return [
            'enabled' => !empty($settings['enabled']),
            'total_variants' => count($variants),
            'status_counts' => $statusCounts,
            'average_score' => count($variants) > 0 ? (int)round($totalScore / count($variants)) : 0,
            'with_data' => $withData,
            'winner' => $winner,
            'best_candidate' => $winner ?: $best,
            'needs_attention' => array_slice($needsAttention, 0, 5),
            'updated_at' => (string)($settings['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('offer_cta_lab_context_report')) {
    function offer_cta_lab_context_report(): array
    {
        $products = function_exists('all_products') ? all_products() : [];
        $articles = function_exists('all_articles') ? all_articles() : [];
        $trust = function_exists('trust_conversion_summary') ? trust_conversion_summary() : [];
        $profit = function_exists('profit_action_dashboard_summary') ? profit_action_dashboard_summary(30, []) : [];

        return [
            'products_count' => count($products),
            'articles_count' => count($articles),
            'trust_enabled_blocks' => (int)($trust['enabled_blocks'] ?? 0),
            'trust_items' => (int)($trust['total_items'] ?? 0),
            'profit_readiness' => (int)($profit['readiness']['score'] ?? 0),
            'profit_actions' => count((array)($profit['actions'] ?? [])),
            'top_content' => array_slice((array)($profit['content']['top_rows'] ?? []), 0, 3),
        ];
    }
}

if (!function_exists('offer_cta_lab_suggestion')) {
    function offer_cta_lab_suggestion(array $data): array
    {
        return offer_cta_lab_normalize_variant([
            'id' => (string)($data['id'] ?? ''),
            'title' => (string)($data['title'] ?? 'Ide Offer Baru'),
            'status' => (string)($data['status'] ?? 'draft'),
            'goal' => (string)($data['goal'] ?? 'whatsapp_lead'),
            'placement' => (string)($data['placement'] ?? 'homepage_mid'),
            'channel' => (string)($data['channel'] ?? 'website'),
            'audience' => (string)($data['audience'] ?? 'Calon customer yang butuh arahan sebelum membeli.'),
            'hook' => (string)($data['hook'] ?? 'Buat penawaran terasa lebih jelas dan ringan untuk diklik.'),
            'headline' => (string)($data['headline'] ?? 'Butuh pilihan yang paling cocok?'),
            'subheadline' => (string)($data['subheadline'] ?? 'Lihat rekomendasi dan konsultasikan kebutuhan Anda sebelum order.'),
            'cta_label' => (string)($data['cta_label'] ?? 'Konsultasi Sekarang'),
            'cta_url' => (string)($data['cta_url'] ?? '/kontak'),
            'proof_note' => (string)($data['proof_note'] ?? 'Dibuat dari sinyal konten, katalog, trust, dan campaign.'),
            'hypothesis' => (string)($data['hypothesis'] ?? 'Varian ini layak diuji karena lebih dekat dengan kebutuhan pengunjung.'),
            'notes' => (string)($data['notes'] ?? 'Tambahkan sebagai draft, lalu edit sesuai bahasa brand.'),
        ], random_int(1, 9999));
    }
}

if (!function_exists('offer_cta_lab_suggestions')) {
    function offer_cta_lab_suggestions(int $limit = 6): array
    {
        $suggestions = [];
        $products = function_exists('all_products') ? all_products() : [];
        $articles = function_exists('all_articles') ? all_articles() : [];
        $trust = function_exists('trust_conversion_summary') ? trust_conversion_summary() : [];

        foreach (array_slice($products, 0, 3) as $product) {
            $title = offer_cta_lab_clean($product['title'] ?? 'Produk/Jasa Utama', 90);
            if ($title === '') {
                continue;
            }
            $slug = slugify((string)($product['slug'] ?? $title));
            $suggestions[] = offer_cta_lab_suggestion([
                'id' => 'produk-' . $slug,
                'title' => 'Offer katalog: ' . $title,
                'goal' => 'checkout',
                'placement' => 'product_detail',
                'channel' => 'website',
                'audience' => 'Pengunjung yang sudah tertarik dengan ' . $title . '.',
                'hook' => 'Dorong pengunjung dari katalog menuju chat atau checkout dengan manfaat yang jelas.',
                'headline' => 'Cek pilihan ' . $title . ' yang paling pas',
                'subheadline' => 'Lihat detail, manfaat, dan cara order. Tim kami siap bantu pilihkan sesuai kebutuhan Anda.',
                'cta_label' => 'Cek Detail ' . $title,
                'cta_url' => '/produk/' . $slug,
                'proof_note' => 'Mengarah langsung ke detail produk/jasa agar minat tidak hilang.',
                'hypothesis' => 'CTA spesifik produk akan lebih kuat daripada tombol umum untuk pengunjung yang sudah punya minat jelas.',
            ]);
        }

        foreach (array_slice($articles, 0, 2) as $article) {
            $title = offer_cta_lab_clean($article['title'] ?? 'Artikel Edukasi', 90);
            if ($title === '') {
                continue;
            }
            $suggestions[] = offer_cta_lab_suggestion([
                'id' => 'artikel-' . slugify($title),
                'title' => 'CTA artikel: arahkan pembaca ke konsultasi',
                'goal' => 'article_to_offer',
                'placement' => 'article_inline',
                'channel' => 'seo_content',
                'audience' => 'Pembaca artikel yang sedang mencari solusi dan butuh langkah berikutnya.',
                'hook' => 'Jangan biarkan pembaca selesai membaca tanpa arah ke produk, jasa, atau konsultasi.',
                'headline' => 'Ingin menerapkan ini untuk kebutuhan Anda?',
                'subheadline' => 'Setelah membaca “' . $title . '”, Anda bisa konsultasi singkat agar lebih mudah menentukan langkah berikutnya.',
                'cta_label' => 'Tanya Rekomendasi',
                'cta_url' => '/kontak',
                'proof_note' => 'Membantu mengubah traffic SEO menjadi percakapan yang lebih dekat ke penjualan.',
                'hypothesis' => 'CTA setelah artikel edukasi akan menaikkan lead dari pembaca yang sudah punya intent.',
            ]);
        }

        if ((int)($trust['enabled_blocks'] ?? 0) < 2) {
            $suggestions[] = offer_cta_lab_suggestion([
                'id' => 'trust-before-cta',
                'title' => 'Trust dulu sebelum CTA',
                'goal' => 'trust_building',
                'placement' => 'trust_block',
                'channel' => 'website',
                'audience' => 'Pengunjung yang masih ragu sebelum chat atau checkout.',
                'hook' => 'Tambahkan alasan percaya tepat sebelum tombol aksi.',
                'headline' => 'Masih ragu? Ini alasan customer memilih kami',
                'subheadline' => 'Lihat benefit, garansi, FAQ, dan bukti layanan sebelum mengambil keputusan.',
                'cta_label' => 'Lihat Alasan Percaya',
                'cta_url' => '/kontak',
                'proof_note' => 'Trust block membantu menjawab keraguan yang sering menahan klik CTA.',
                'hypothesis' => 'Pengunjung yang melihat trust cue sebelum CTA lebih siap menghubungi bisnis.',
            ]);
        }

        $suggestions[] = offer_cta_lab_suggestion([
            'id' => 'campaign-7-hari-offer',
            'title' => 'Offer campaign 7 hari',
            'goal' => 'campaign_boost',
            'placement' => 'landing_page',
            'channel' => 'campaign',
            'audience' => 'Lead hangat dari SEO, WhatsApp, dan pengunjung ulang.',
            'hook' => 'Buat penawaran berbatas waktu yang tetap aman dan tidak berlebihan.',
            'headline' => 'Mulai dari langkah kecil, hasilnya bisa terasa lebih cepat',
            'subheadline' => 'Ambil rekomendasi terbaik minggu ini dan konsultasikan kebutuhan Anda sebelum jadwal penuh.',
            'cta_label' => 'Ambil Rekomendasi Minggu Ini',
            'cta_url' => '/kontak',
            'proof_note' => 'Cocok digabung dengan Profit Playbook 7 hari.',
            'hypothesis' => 'Offer dengan konteks waktu mingguan membantu lead mengambil keputusan lebih cepat.',
        ]);

        return array_slice($suggestions, 0, max(1, $limit));
    }
}

if (!function_exists('offer_cta_lab_filter_variants')) {
    function offer_cta_lab_filter_variants(array $variants, string $status = 'all', string $q = ''): array
    {
        $q = strtolower(trim($q));
        return array_values(array_filter($variants, static function (array $variant) use ($status, $q): bool {
            if ($status !== 'all' && (string)($variant['status'] ?? '') !== $status) {
                return false;
            }

            if ($q === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', [
                (string)($variant['title'] ?? ''),
                (string)($variant['headline'] ?? ''),
                (string)($variant['subheadline'] ?? ''),
                (string)($variant['cta_label'] ?? ''),
                (string)($variant['audience'] ?? ''),
                (string)($variant['placement'] ?? ''),
                (string)($variant['goal'] ?? ''),
            ]));

            return str_contains($haystack, $q);
        }));
    }
}

if (!function_exists('offer_cta_lab_export_csv')) {
    function offer_cta_lab_export_csv(array $variants): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="offer-cta-testing-lab-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['status', 'goal', 'placement', 'title', 'headline', 'cta_label', 'cta_url', 'score', 'ctr', 'lead_rate', 'order_rate', 'hypothesis']);
        foreach ($variants as $variant) {
            fputcsv($out, [
                (string)($variant['status'] ?? ''),
                (string)($variant['goal'] ?? ''),
                (string)($variant['placement'] ?? ''),
                (string)($variant['title'] ?? ''),
                (string)($variant['headline'] ?? ''),
                (string)($variant['cta_label'] ?? ''),
                (string)($variant['cta_url'] ?? ''),
                (int)($variant['score'] ?? 0),
                (float)($variant['ctr'] ?? 0),
                (float)($variant['lead_rate'] ?? 0),
                (float)($variant['order_rate'] ?? 0),
                (string)($variant['hypothesis'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
