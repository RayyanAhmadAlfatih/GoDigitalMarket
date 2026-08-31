<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GROWTH INSIGHT ENGINE
|--------------------------------------------------------------------------
| Rule-based business insight layer for UMKM owners. It turns existing
| reports, lead events, orders, payment proofs, content assets, and business
| mode settings into practical recommendations without external AI/API calls.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('growth_insight_clean')) {
    function growth_insight_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('growth_insight_first_key')) {
    function growth_insight_first_key(array $items): string
    {
        $key = array_key_first($items);
        return $key === null ? '' : (string)$key;
    }
}

if (!function_exists('growth_insight_score_label')) {
    function growth_insight_score_label(int $score): string
    {
        if ($score >= 80) {
            return 'Siap scale up';
        }
        if ($score >= 60) {
            return 'Mulai kuat';
        }
        if ($score >= 40) {
            return 'Perlu optimasi';
        }
        return 'Fondasi awal';
    }
}

if (!function_exists('growth_insight_score_tone')) {
    function growth_insight_score_tone(int $score): string
    {
        if ($score >= 80) {
            return 'ok';
        }
        if ($score >= 60) {
            return 'info';
        }
        if ($score >= 40) {
            return 'warning';
        }
        return 'error';
    }
}

if (!function_exists('growth_insight_ratio')) {
    function growth_insight_ratio(int|float $part, int|float $total, int $precision = 1): float
    {
        if ($total <= 0) {
            return 0.0;
        }
        return round(((float)$part / (float)$total) * 100, $precision);
    }
}

if (!function_exists('growth_insight_content_health')) {
    function growth_insight_content_health(): array
    {
        $products = function_exists('all_products') ? all_products() : [];
        $articles = function_exists('all_articles') ? all_articles() : [];
        $landingPages = function_exists('landing_page_all') ? landing_page_all(true) : [];
        $forms = function_exists('custom_form_read_forms') ? custom_form_read_forms() : [];
        $catalogCategories = function_exists('business_category_rows') ? business_category_rows('catalog', true) : [];
        $articleCategories = function_exists('business_category_rows') ? business_category_rows('article', true) : [];
        $portfolioCategories = function_exists('business_category_rows') ? business_category_rows('portfolio', true) : [];

        $activeProducts = array_values(array_filter($products, static fn(array $item): bool => (string)($item['status'] ?? 'published') !== 'draft'));
        $publishedArticles = array_values(array_filter($articles, static fn(array $item): bool => (string)($item['status'] ?? 'published') !== 'draft'));
        $publishedLandingPages = array_values(array_filter($landingPages, static fn(array $item): bool => (string)($item['status'] ?? '') === 'published'));
        $activeForms = array_values(array_filter($forms, static fn(array $item): bool => (string)($item['status'] ?? 'active') === 'active'));

        $score = 0;
        $score += min(18, count($activeProducts) * 3);
        $score += min(18, count($publishedArticles) * 3);
        $score += min(16, count($publishedLandingPages) * 8);
        $score += min(12, count($activeForms) * 6);
        $score += min(18, (count($catalogCategories) + count($articleCategories) + count($portfolioCategories)) * 2);

        return [
            'products_total' => count($products),
            'products_active' => count($activeProducts),
            'articles_total' => count($articles),
            'articles_published' => count($publishedArticles),
            'landing_pages_total' => count($landingPages),
            'landing_pages_published' => count($publishedLandingPages),
            'forms_total' => count($forms),
            'forms_active' => count($activeForms),
            'catalog_categories' => count($catalogCategories),
            'article_categories' => count($articleCategories),
            'portfolio_categories' => count($portfolioCategories),
            'score' => min(100, $score),
        ];
    }
}

if (!function_exists('growth_insight_score')) {
    function growth_insight_score(array $summary, array $contentHealth): array
    {
        $leadEvents = (int)($summary['lead']['events'] ?? 0);
        $highIntent = (int)($summary['lead']['high_intent'] ?? 0);
        $whatsapp = (int)($summary['lead']['whatsapp'] ?? 0);
        $orders = (int)($summary['order']['total'] ?? 0);
        $completed = (int)($summary['order']['completed'] ?? 0);
        $paidValue = (int)($summary['sales']['paid_order_value'] ?? 0);
        $salesValue = (int)($summary['sales']['estimate'] ?? 0);
        $pendingProofs = (int)($summary['payment']['pending_proofs'] ?? 0);
        $leadToOrderRate = (float)($summary['conversion']['lead_to_order_rate'] ?? 0);

        $traffic = 0;
        if ($leadEvents >= 100) {
            $traffic = 25;
        } elseif ($leadEvents >= 50) {
            $traffic = 22;
        } elseif ($leadEvents >= 20) {
            $traffic = 18;
        } elseif ($leadEvents >= 5) {
            $traffic = 12;
        } elseif ($leadEvents > 0) {
            $traffic = 7;
        }

        $intent = 0;
        if ($highIntent >= 20) {
            $intent = 20;
        } elseif ($highIntent >= 10) {
            $intent = 16;
        } elseif ($highIntent >= 3) {
            $intent = 11;
        } elseif ($highIntent > 0 || $whatsapp > 0) {
            $intent = 7;
        }

        $conversion = 0;
        if ($leadToOrderRate >= 10) {
            $conversion = 25;
        } elseif ($leadToOrderRate >= 5) {
            $conversion = 20;
        } elseif ($leadToOrderRate >= 2) {
            $conversion = 14;
        } elseif ($orders > 0) {
            $conversion = 9;
        }

        $sales = 0;
        if ($paidValue > 0 && $completed > 0) {
            $sales = 20;
        } elseif ($salesValue > 0 && $orders >= 3) {
            $sales = 15;
        } elseif ($orders > 0) {
            $sales = 10;
        }
        if ($pendingProofs > 0) {
            $sales = max(0, $sales - 3);
        }

        $content = min(10, (int)round(((int)($contentHealth['score'] ?? 0)) / 10));
        $total = min(100, $traffic + $intent + $conversion + $sales + $content);

        return [
            'total' => $total,
            'label' => growth_insight_score_label($total),
            'tone' => growth_insight_score_tone($total),
            'parts' => [
                'traffic' => $traffic,
                'intent' => $intent,
                'conversion' => $conversion,
                'sales' => $sales,
                'content' => $content,
            ],
        ];
    }
}

if (!function_exists('growth_insight_add_recommendation')) {
    function growth_insight_add_recommendation(array &$items, string $priority, string $title, string $body, string $actionLabel = '', string $actionUrl = ''): void
    {
        $items[] = [
            'priority' => growth_insight_clean($priority, 30),
            'title' => growth_insight_clean($title, 120),
            'body' => growth_insight_clean($body, 360),
            'action_label' => growth_insight_clean($actionLabel, 80),
            'action_url' => $actionUrl,
        ];
    }
}

if (!function_exists('growth_insight_recommendations')) {
    function growth_insight_recommendations(array $summary, array $contentHealth, array $business): array
    {
        $items = [];
        $leadEvents = (int)($summary['lead']['events'] ?? 0);
        $highIntent = (int)($summary['lead']['high_intent'] ?? 0);
        $whatsapp = (int)($summary['lead']['whatsapp'] ?? 0);
        $inquiries = (int)($summary['lead']['inquiries'] ?? 0);
        $orders = (int)($summary['order']['total'] ?? 0);
        $newOrders = (int)($summary['order']['new'] ?? 0);
        $paymentWaiting = (int)($summary['order']['payment_waiting'] ?? 0);
        $pendingProofs = (int)($summary['payment']['pending_proofs'] ?? 0);
        $leadToOrderRate = (float)($summary['conversion']['lead_to_order_rate'] ?? 0);
        $topProduct = growth_insight_first_key((array)($summary['breakdowns']['product'] ?? []));
        $topSource = growth_insight_first_key((array)($summary['breakdowns']['lead_source'] ?? []));
        $topChannel = growth_insight_first_key((array)($summary['breakdowns']['marketing_channel'] ?? []));
        $topLocation = growth_insight_first_key((array)($summary['breakdowns']['location'] ?? []));
        $modeLabel = (string)($business['label'] ?? 'Hybrid Growth Website');

        if ($leadEvents <= 0) {
            growth_insight_add_recommendation(
                $items,
                'Prioritas tinggi',
                'Mulai kumpulkan data klik dan lead',
                'Belum ada event lead pada rentang ini. Pastikan tombol WhatsApp, form, checkout, dan landing page punya tracking aktif agar keputusan bisnis tidak hanya berdasarkan feeling.',
                'Buka Analytics',
                function_exists('url') ? url('admin/analytics') : ''
            );
        }

        if ($leadEvents >= 20 && $leadToOrderRate < 2.0) {
            growth_insight_add_recommendation(
                $items,
                'Prioritas tinggi',
                'Perbaiki offer, CTA, dan follow-up',
                'Traffic/klik sudah mulai ada, tapi rasio order masih rendah. Cek apakah harga, benefit, bukti sosial, tombol CTA, dan pesan follow-up sudah cukup meyakinkan.',
                'Buka Laporan',
                function_exists('url') ? url('admin/reports') : ''
            );
        }

        if ($highIntent > 0 && $orders <= 0) {
            growth_insight_add_recommendation(
                $items,
                'Prioritas tinggi',
                'Jangan biarkan prospek panas hilang',
                'Ada interaksi high-intent, tetapi belum menjadi order. Follow-up manual lewat WhatsApp/CRM dan sederhanakan alur checkout agar prospek lebih cepat ambil keputusan.',
                'Buka Follow-up',
                function_exists('url') ? url('admin/followups') : ''
            );
        }

        if ($newOrders > 0) {
            growth_insight_add_recommendation(
                $items,
                'Aksi cepat',
                'Tindak lanjuti order baru',
                'Ada order baru yang perlu diproses. Respons cepat biasanya membantu menaikkan peluang closing dan mengurangi calon buyer yang berubah pikiran.',
                'Buka Order',
                function_exists('url') ? url('admin/orders') : ''
            );
        }

        if ($paymentWaiting > 0) {
            growth_insight_add_recommendation(
                $items,
                'Aksi cepat',
                'Aktifkan dorongan pembayaran',
                'Ada order yang menunggu pembayaran. Gunakan reminder pembayaran dan pesan follow-up yang jelas supaya revenue tidak nyangkut di tahap invoice.',
                'Buka Reminder',
                function_exists('url') ? url('admin/payment-reminders') : ''
            );
        }

        if ($pendingProofs > 0) {
            growth_insight_add_recommendation(
                $items,
                'Aksi cepat',
                'Review bukti pembayaran',
                'Ada bukti pembayaran yang menunggu review. Validasi lebih cepat membuat customer merasa aman dan order bisa lanjut diproses.',
                'Buka Bukti Pembayaran',
                function_exists('url') ? url('admin/payment-proofs') : ''
            );
        }

        if ($topProduct !== '' && $topProduct !== 'Tidak diketahui') {
            growth_insight_add_recommendation(
                $items,
                'Peluang scale',
                'Scale penawaran yang paling sering menghasilkan',
                'Produk/paket teratas saat ini: ' . $topProduct . '. Pertimbangkan buat landing page khusus, artikel SEO pendukung, bonus, bundle, atau campaign iklan kecil untuk validasi demand.',
                'Buka Katalog',
                function_exists('url') ? url('admin/produk') : ''
            );
        }

        if ($topSource !== '' && $topSource !== 'Tidak diketahui') {
            growth_insight_add_recommendation(
                $items,
                'Peluang marketing',
                'Perkuat sumber lead yang sudah bekerja',
                'Sumber lead yang paling sering muncul: ' . $topSource . '. Buat konten/CTA yang lebih konsisten di channel tersebut, lalu bandingkan hasilnya dengan channel lain.',
                'Buka Tracking Lead',
                function_exists('url') ? url('admin/leads') : ''
            );
        } elseif ($topChannel !== '' && $topChannel !== 'Tidak diketahui') {
            growth_insight_add_recommendation(
                $items,
                'Peluang marketing',
                'Baca channel marketing utama',
                'Channel marketing yang paling terlihat: ' . $topChannel . '. Gunakan insight ini untuk menentukan konten, budget promosi, dan landing page yang perlu diperkuat.',
                'Buka Analytics',
                function_exists('url') ? url('admin/analytics') : ''
            );
        }

        if ($topLocation !== '' && $topLocation !== 'Tidak diketahui') {
            growth_insight_add_recommendation(
                $items,
                'Peluang lokal',
                'Buat konten/landing berbasis area',
                'Area/lokasi yang sering muncul: ' . $topLocation . '. Untuk bisnis lokal, ini bisa jadi bahan landing page SEO, promo area, atau copy WhatsApp yang lebih spesifik.',
                'Buka SEO Landing',
                function_exists('url') ? url('admin/seo-landings') : ''
            );
        }

        if ((int)($contentHealth['articles_published'] ?? 0) < 5) {
            growth_insight_add_recommendation(
                $items,
                'Fondasi SEO',
                'Tambah artikel SEO sesuai niche',
                'Artikel publish masih sedikit. Untuk mode ' . $modeLabel . ', minimal siapkan artikel panduan, masalah customer, perbandingan layanan/produk, testimoni, dan FAQ agar traffic organik tumbuh.',
                'Buka Artikel',
                function_exists('url') ? url('admin/artikel') : ''
            );
        }

        if ((int)($contentHealth['landing_pages_published'] ?? 0) <= 0 && $leadEvents > 0) {
            growth_insight_add_recommendation(
                $items,
                'Conversion',
                'Siapkan landing page khusus campaign',
                'Sudah ada interaksi calon customer, tapi belum ada landing page publish. Buat satu landing page fokus untuk offer paling penting agar promosi lebih mudah diukur.',
                'Buka Landing Pages',
                function_exists('url') ? url('admin/landing-pages') : ''
            );
        }

        if (!$items) {
            growth_insight_add_recommendation(
                $items,
                'Mantap',
                'Fondasi growth mulai rapi',
                'Data lead, order, payment, dan konten terlihat cukup sehat. Lanjutkan optimasi bertahap: A/B CTA, SEO artikel, landing page khusus, dan follow-up yang lebih cepat.',
                'Buka Optimasi LP',
                function_exists('url') ? url('admin/landing-page-optimization') : ''
            );
        }

        return array_slice($items, 0, 8);
    }
}

if (!function_exists('growth_insight_funnel')) {
    function growth_insight_funnel(array $summary): array
    {
        $leadEvents = (int)($summary['lead']['events'] ?? 0);
        $highIntent = (int)($summary['lead']['high_intent'] ?? 0);
        $inquiries = (int)($summary['lead']['inquiries'] ?? 0);
        $orders = (int)($summary['order']['total'] ?? 0);
        $proofs = (int)($summary['payment']['proofs'] ?? 0);
        $completed = (int)($summary['order']['completed'] ?? 0);

        return [
            ['label' => 'Interaksi Lead', 'value' => $leadEvents, 'rate' => 100.0, 'note' => 'Klik CTA, WhatsApp, form, atau event tracking lain.'],
            ['label' => 'High Intent', 'value' => $highIntent, 'rate' => growth_insight_ratio($highIntent, max(1, $leadEvents)), 'note' => 'Event yang lebih dekat ke transaksi.'],
            ['label' => 'Inquiry/Form', 'value' => $inquiries, 'rate' => growth_insight_ratio($inquiries, max(1, $leadEvents)), 'note' => 'Data masuk dari form/inquiry.'],
            ['label' => 'Order', 'value' => $orders, 'rate' => growth_insight_ratio($orders, max(1, $leadEvents)), 'note' => 'Pesanan yang masuk.'],
            ['label' => 'Bukti Bayar', 'value' => $proofs, 'rate' => growth_insight_ratio($proofs, max(1, $orders)), 'note' => 'Bukti pembayaran dari buyer.'],
            ['label' => 'Deal/Selesai', 'value' => $completed, 'rate' => growth_insight_ratio($completed, max(1, $orders)), 'note' => 'Order yang sudah selesai/deal.'],
        ];
    }
}

if (!function_exists('growth_insight_summary')) {
    function growth_insight_summary(int $days = 30, array $filters = []): array
    {
        $summary = function_exists('report_dashboard_summary') ? report_dashboard_summary($days, $filters) : [];
        $contentHealth = growth_insight_content_health();
        $business = function_exists('business_current_mode') ? business_current_mode() : ['label' => 'Hybrid Growth Website'];
        $score = growth_insight_score($summary, $contentHealth);
        $recommendations = growth_insight_recommendations($summary, $contentHealth, $business);
        $funnel = growth_insight_funnel($summary);

        return [
            'generated_at' => date('c'),
            'business' => $business,
            'report' => $summary,
            'content_health' => $contentHealth,
            'score' => $score,
            'recommendations' => $recommendations,
            'funnel' => $funnel,
        ];
    }
}

if (!function_exists('growth_insight_export_csv')) {
    function growth_insight_export_csv(array $insight): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="growth-insights-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['section', 'label', 'value', 'note'], ',', '"', '');
        fputcsv($out, ['score', 'Growth Score', (string)($insight['score']['total'] ?? 0), (string)($insight['score']['label'] ?? '')], ',', '"', '');
        foreach ((array)($insight['funnel'] ?? []) as $row) {
            fputcsv($out, ['funnel', (string)($row['label'] ?? ''), (string)($row['value'] ?? 0), (string)($row['note'] ?? '')], ',', '"', '');
        }
        foreach ((array)($insight['recommendations'] ?? []) as $row) {
            fputcsv($out, ['recommendation', (string)($row['title'] ?? ''), (string)($row['priority'] ?? ''), (string)($row['body'] ?? '')], ',', '"', '');
        }
        fclose($out);
        exit;
    }
}
