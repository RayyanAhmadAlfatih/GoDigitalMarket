<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONVERSION OPPORTUNITY ENGINE
|--------------------------------------------------------------------------
| Lightweight rule-based engine that turns content performance, SEO score,
| internal lead events, WhatsApp/form/order signals, and money page context
| into practical conversion opportunities for UMKM owners.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('conversion_opportunity_clean')) {
    function conversion_opportunity_clean(string $value, int $max = 180): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('conversion_opportunity_type_label')) {
    function conversion_opportunity_type_label(string $type): string
    {
        if (function_exists('content_performance_item_label')) {
            return content_performance_item_label($type);
        }

        return match ($type) {
            'product' => 'Produk',
            'service' => 'Layanan',
            'article' => 'Artikel',
            'landing_page' => 'Landing Page',
            'seo_landing' => 'SEO Landing',
            'portfolio' => 'Portfolio',
            'homepage' => 'Homepage',
            'static_page' => 'Halaman',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}

if (!function_exists('conversion_opportunity_impact_meta')) {
    function conversion_opportunity_impact_meta(int $score): array
    {
        if ($score >= 82) {
            return ['key' => 'critical', 'label' => 'Prioritas Kritis', 'tone' => 'error'];
        }
        if ($score >= 65) {
            return ['key' => 'high', 'label' => 'Prioritas Tinggi', 'tone' => 'warning'];
        }
        if ($score >= 42) {
            return ['key' => 'medium', 'label' => 'Prioritas Sedang', 'tone' => 'info'];
        }
        return ['key' => 'low', 'label' => 'Pantau', 'tone' => 'neutral'];
    }
}

if (!function_exists('conversion_opportunity_category_meta')) {
    function conversion_opportunity_category_meta(string $category): array
    {
        return match ($category) {
            'cta_gap' => ['label' => 'CTA Gap', 'note' => 'Ada interaksi, tapi aksi lanjutan belum kuat.'],
            'offer_gap' => ['label' => 'Offer/Trust Gap', 'note' => 'Ada intent, tapi belum cukup mendorong order.'],
            'support_gap' => ['label' => 'Support Content Gap', 'note' => 'Money page butuh artikel/FAQ pendukung.'],
            'seo_to_conversion' => ['label' => 'SEO → Conversion', 'note' => 'SEO cukup, tetapi belum menghasilkan sinyal bisnis.'],
            'checkout_gap' => ['label' => 'Checkout Gap', 'note' => 'Sinyal transaksi ada, closing/order masih tertahan.'],
            default => ['label' => 'Growth Opportunity', 'note' => 'Peluang optimasi lanjutan.'],
        };
    }
}

if (!function_exists('conversion_opportunity_row_intent')) {
    function conversion_opportunity_row_intent(array $row): int
    {
        return (int)($row['metrics']['high_intent'] ?? 0)
            + (int)($row['metrics']['whatsapp'] ?? 0)
            + (int)($row['metrics']['inquiries'] ?? 0)
            + (int)($row['metrics']['orders'] ?? 0);
    }
}

if (!function_exists('conversion_opportunity_action')) {
    function conversion_opportunity_action(string $category, array $row): array
    {
        $type = (string)($row['type'] ?? 'page');
        $title = conversion_opportunity_clean((string)($row['title'] ?? 'halaman ini'), 90);

        if ($category === 'cta_gap') {
            return [
                'title' => 'Perjelas CTA dan jalur kontak cepat',
                'body' => 'Tambahkan CTA utama di area atas dan tengah halaman, tampilkan benefit paling spesifik, lalu arahkan ke WhatsApp/form/checkout yang paling mudah dipahami.',
                'checklist' => ['CTA utama terlihat tanpa scroll panjang', 'Ada alasan kuat untuk klik', 'Ada trust signal/testimoni/garansi', 'Form/WhatsApp tidak terlalu jauh'],
                'url' => (string)($row['edit_url'] ?? ''),
                'label' => 'Edit Halaman',
            ];
        }

        if ($category === 'offer_gap') {
            return [
                'title' => 'Perkuat offer, bukti sosial, dan alasan beli',
                'body' => 'Halaman sudah punya sinyal intent. Naikkan konversi dengan bonus, paket yang lebih jelas, testimoni, FAQ keberatan, urgency wajar, dan CTA yang lebih spesifik.',
                'checklist' => ['Paket/harga jelas', 'Ada testimoni atau proof', 'FAQ menjawab keberatan', 'CTA memakai kata kerja spesifik'],
                'url' => (string)($row['edit_url'] ?? ''),
                'label' => 'Poles Offer',
            ];
        }

        if ($category === 'support_gap') {
            return [
                'title' => 'Buat artikel pendukung menuju money page',
                'body' => 'Buat 2-3 artikel atau FAQ yang membahas masalah calon pembeli, lalu pasang internal link natural menuju ' . $title . '.',
                'checklist' => ['Artikel edukasi pendukung', 'Anchor text natural', 'FAQ sesuai keberatan calon pembeli', 'Link balik ke money page'],
                'url' => function_exists('url') ? url('admin/seo-content-planner?q=' . rawurlencode($title)) : '',
                'label' => 'Buka Content Planner',
            ];
        }

        if ($category === 'seo_to_conversion') {
            return [
                'title' => 'Ubah traffic SEO menjadi lead',
                'body' => 'SEO halaman sudah lumayan, tetapi sinyal bisnis masih rendah. Tambahkan lead magnet, CTA konsultasi, tombol WhatsApp kontekstual, atau rekomendasi produk/jasa terkait.',
                'checklist' => ['Ada CTA kontekstual', 'Ada penawaran kecil/lead magnet', 'Ada internal link ke produk/jasa', 'Tracking klik aktif'],
                'url' => function_exists('url') ? url('admin/content-performance?q=' . rawurlencode($title)) : '',
                'label' => 'Lihat Performa',
            ];
        }

        if ($category === 'checkout_gap') {
            return [
                'title' => 'Kurangi hambatan checkout/closing',
                'body' => 'Ada sinyal menuju transaksi, tapi order belum kuat. Cek form checkout, pilihan pembayaran, kejelasan ongkir/biaya, dan follow-up setelah klik WhatsApp.',
                'checklist' => ['Form checkout singkat', 'Biaya/harga jelas', 'Pilihan pembayaran jelas', 'Follow-up prospek aktif'],
                'url' => function_exists('url') ? url('admin/form-checkout') : '',
                'label' => 'Cek Checkout',
            ];
        }

        return [
            'title' => 'Pantau dan rapikan jalur konversi',
            'body' => 'Hubungkan halaman ini ke halaman penawaran yang relevan dan cek ulang CTA setelah mulai ada sinyal interaksi.',
            'checklist' => ['Internal link relevan', 'CTA terlihat', 'Tracking aktif'],
            'url' => (string)($row['edit_url'] ?? ''),
            'label' => 'Edit',
        ];
    }
}

if (!function_exists('conversion_opportunity_make')) {
    function conversion_opportunity_make(string $category, array $row, int $baseScore): array
    {
        $interactions = (int)($row['metrics']['interactions'] ?? 0);
        $intent = conversion_opportunity_row_intent($row);
        $orders = (int)($row['metrics']['orders'] ?? 0);
        $seoScore = (int)($row['seo_score'] ?? 0);
        $score = min(100, max(1, $baseScore + min(24, $interactions * 4) + min(22, $intent * 7) + min(12, max(0, 80 - $seoScore) / 6) - min(12, $orders * 6)));
        $score = (int)round($score);
        $impact = conversion_opportunity_impact_meta($score);
        $categoryMeta = conversion_opportunity_category_meta($category);
        $action = conversion_opportunity_action($category, $row);

        $topSource = '';
        $sources = (array)($row['sources'] ?? []);
        if ($sources) {
            arsort($sources);
            $first = array_key_first($sources);
            $topSource = $first === null ? '' : (string)$first;
        }

        return [
            'id' => md5($category . '|' . (string)($row['id'] ?? '') . '|' . (string)($row['path'] ?? '')),
            'category' => $category,
            'category_label' => $categoryMeta['label'],
            'category_note' => $categoryMeta['note'],
            'impact' => $impact,
            'priority_score' => $score,
            'page' => [
                'id' => (string)($row['id'] ?? ''),
                'type' => (string)($row['type'] ?? 'page'),
                'type_label' => conversion_opportunity_type_label((string)($row['type'] ?? 'page')),
                'title' => conversion_opportunity_clean((string)($row['title'] ?? 'Halaman'), 140),
                'path' => (string)($row['path'] ?? ''),
                'url' => (string)($row['url'] ?? ''),
                'edit_url' => (string)($row['edit_url'] ?? ''),
                'seo_score' => $seoScore,
                'grade' => (string)($row['grade'] ?? ''),
            ],
            'metrics' => [
                'interactions' => $interactions,
                'intent' => $intent,
                'whatsapp' => (int)($row['metrics']['whatsapp'] ?? 0),
                'inquiries' => (int)($row['metrics']['inquiries'] ?? 0),
                'orders' => $orders,
                'intent_rate' => (float)($row['intent_rate'] ?? 0),
                'top_source' => $topSource,
            ],
            'action' => $action,
        ];
    }
}

if (!function_exists('conversion_opportunity_summary')) {
    function conversion_opportunity_summary(int $days = 30, array $filters = []): array
    {
        $content = function_exists('content_performance_summary') ? content_performance_summary($days, $filters) : ['rows' => [], 'metrics' => []];
        $rows = (array)($content['rows'] ?? []);
        $opportunities = [];

        foreach ($rows as $row) {
            $type = (string)($row['type'] ?? 'page');
            $interactions = (int)($row['metrics']['interactions'] ?? 0);
            $intent = conversion_opportunity_row_intent($row);
            $orders = (int)($row['metrics']['orders'] ?? 0);
            $seoScore = (int)($row['seo_score'] ?? 0);
            $bucket = (string)($row['bucket']['key'] ?? '');
            $isMoneyPage = in_array($type, ['product', 'service', 'landing_page', 'seo_landing', 'homepage'], true);

            if ($interactions >= 3 && $intent === 0) {
                $opportunities[] = conversion_opportunity_make('cta_gap', $row, 50);
            }

            if ($intent > 0 && $orders === 0 && $isMoneyPage) {
                $opportunities[] = conversion_opportunity_make('offer_gap', $row, 58);
            }

            if ($intent >= 2 && $orders === 0) {
                $opportunities[] = conversion_opportunity_make('checkout_gap', $row, 62);
            }

            if ($isMoneyPage && ($bucket === 'build_support' || ($seoScore >= 75 && $interactions <= 1))) {
                $opportunities[] = conversion_opportunity_make('support_gap', $row, 42);
            }

            if ($seoScore >= 80 && $interactions === 0 && !$isMoneyPage) {
                $opportunities[] = conversion_opportunity_make('seo_to_conversion', $row, 38);
            }
        }

        $deduped = [];
        foreach ($opportunities as $item) {
            $key = (string)($item['id'] ?? '');
            if ($key === '') {
                continue;
            }
            if (!isset($deduped[$key]) || (int)$item['priority_score'] > (int)($deduped[$key]['priority_score'] ?? 0)) {
                $deduped[$key] = $item;
            }
        }
        $opportunities = array_values($deduped);
        usort($opportunities, static fn(array $a, array $b): int => ((int)($b['priority_score'] ?? 0)) <=> ((int)($a['priority_score'] ?? 0)));

        $categories = [];
        $impacts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($opportunities as $item) {
            $category = (string)($item['category'] ?? 'other');
            $categories[$category] = ($categories[$category] ?? 0) + 1;
            $impact = (string)($item['impact']['key'] ?? 'low');
            if (!isset($impacts[$impact])) {
                $impacts[$impact] = 0;
            }
            $impacts[$impact]++;
        }

        $actionPlan = [];
        if (($categories['cta_gap'] ?? 0) > 0) {
            $actionPlan[] = 'Rapikan CTA pada halaman yang sudah punya interaksi agar klik tidak berhenti sebagai kunjungan pasif.';
        }
        if (($categories['offer_gap'] ?? 0) > 0) {
            $actionPlan[] = 'Perkuat offer, trust, testimoni, FAQ keberatan, dan alasan beli pada money page yang sudah punya intent.';
        }
        if (($categories['support_gap'] ?? 0) > 0) {
            $actionPlan[] = 'Bangun artikel pendukung untuk money page agar SEO organik tidak berhenti di konten informasi saja.';
        }
        if (($categories['checkout_gap'] ?? 0) > 0) {
            $actionPlan[] = 'Cek jalur checkout, form, pembayaran, dan follow-up WhatsApp agar prospek lebih mudah closing.';
        }
        if (!$actionPlan) {
            $actionPlan[] = 'Belum ada gap besar. Lanjut pantau Content Performance dan Growth Insight sambil menambah konten pendukung berkualitas.';
        }

        $avgScore = 0;
        if ($opportunities) {
            $avgScore = (int)round(array_sum(array_map(static fn(array $item): int => (int)($item['priority_score'] ?? 0), $opportunities)) / count($opportunities));
        }

        return [
            'metrics' => [
                'opportunities_total' => count($opportunities),
                'priority_avg' => $avgScore,
                'critical' => (int)($impacts['critical'] ?? 0),
                'high' => (int)($impacts['high'] ?? 0),
                'cta_gap' => (int)($categories['cta_gap'] ?? 0),
                'offer_gap' => (int)($categories['offer_gap'] ?? 0),
                'support_gap' => (int)($categories['support_gap'] ?? 0),
                'checkout_gap' => (int)($categories['checkout_gap'] ?? 0),
                'seo_to_conversion' => (int)($categories['seo_to_conversion'] ?? 0),
            ],
            'categories' => $categories,
            'impacts' => $impacts,
            'action_plan' => $actionPlan,
            'opportunities' => $opportunities,
            'content_metrics' => (array)($content['metrics'] ?? []),
        ];
    }
}
