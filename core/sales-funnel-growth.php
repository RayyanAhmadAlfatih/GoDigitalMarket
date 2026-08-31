<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SALES FUNNEL GROWTH ENGINE
|--------------------------------------------------------------------------
| Lightweight local engine that connects SEO/content signals with lead,
| inquiry, order, payment, and closing stages so UMKM owners know which
| part of the funnel needs action first.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('sales_funnel_clean')) {
    function sales_funnel_clean(string $value, int $max = 180): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('sales_funnel_rate')) {
    function sales_funnel_rate(int|float $part, int|float $total, int $precision = 1): float
    {
        if ($total <= 0) {
            return 0.0;
        }
        return round(((float)$part / (float)$total) * 100, $precision);
    }
}

if (!function_exists('sales_funnel_stage_meta')) {
    function sales_funnel_stage_meta(string $key): array
    {
        return match ($key) {
            'traffic' => ['label' => 'Traffic & Interaksi', 'note' => 'Klik CTA, WhatsApp, card, form view, dan event tracking lain.', 'focus' => 'Perbesar traffic berkualitas dari SEO, artikel, dan landing page.'],
            'intent' => ['label' => 'High Intent', 'note' => 'Sinyal dekat transaksi: WhatsApp, form, checkout, atau intent tinggi.', 'focus' => 'Buat CTA dan offer lebih jelas agar interaksi naik menjadi prospek.'],
            'inquiry' => ['label' => 'Inquiry/Form', 'note' => 'Lead masuk dari form custom, kontak, atau checkout awal.', 'focus' => 'Percepat respon, rapikan template follow-up, dan segmentasi kebutuhan.'],
            'order' => ['label' => 'Order', 'note' => 'Pesanan atau request transaksi yang sudah tercatat.', 'focus' => 'Permudah order, invoice, pembayaran, dan konfirmasi WhatsApp.'],
            'payment' => ['label' => 'Pembayaran', 'note' => 'Bukti bayar, status menunggu bayar, DP, atau lunas.', 'focus' => 'Kurangi hambatan bayar dengan instruksi, reminder, dan trust signal.'],
            'closing' => ['label' => 'Closing/Selesai', 'note' => 'Order selesai/deal/lunas yang menjadi hasil bisnis.', 'focus' => 'Scale halaman dan channel yang menghasilkan closing paling sehat.'],
            default => ['label' => 'Funnel', 'note' => 'Tahap funnel bisnis.', 'focus' => 'Pantau dan optimasi tahap ini.'],
        };
    }
}

if (!function_exists('sales_funnel_stage_health')) {
    function sales_funnel_stage_health(string $key, int $value, int $previous): array
    {
        $rate = $previous > 0 ? sales_funnel_rate($value, $previous) : ($value > 0 ? 100.0 : 0.0);
        if ($key === 'traffic') {
            if ($value >= 30) {
                return ['tone' => 'success', 'label' => 'Aktif', 'rate' => $rate];
            }
            if ($value >= 5) {
                return ['tone' => 'warning', 'label' => 'Mulai Terbaca', 'rate' => $rate];
            }
            return ['tone' => 'neutral', 'label' => 'Butuh Traffic', 'rate' => $rate];
        }

        if ($previous <= 0 && $value <= 0) {
            return ['tone' => 'neutral', 'label' => 'Belum Ada Data', 'rate' => 0.0];
        }
        if ($rate >= 45) {
            return ['tone' => 'success', 'label' => 'Sehat', 'rate' => $rate];
        }
        if ($rate >= 18) {
            return ['tone' => 'warning', 'label' => 'Perlu Dipoles', 'rate' => $rate];
        }
        return ['tone' => 'error', 'label' => 'Bottleneck', 'rate' => $rate];
    }
}

if (!function_exists('sales_funnel_score')) {
    function sales_funnel_score(array $report, array $content, array $opportunity): array
    {
        $lead = (array)($report['lead'] ?? []);
        $order = (array)($report['order'] ?? []);
        $payment = (array)($report['payment'] ?? []);
        $conversion = (array)($report['conversion'] ?? []);
        $contentMetrics = (array)($content['metrics'] ?? []);
        $oppMetrics = (array)($opportunity['metrics'] ?? []);

        $leadToOrder = (float)($conversion['lead_to_order_rate'] ?? 0);
        $inquiryToOrder = (float)($conversion['inquiry_to_order_rate'] ?? 0);
        $proofToOrder = (float)($payment['proof_to_order_rate'] ?? 0);
        $completedRatio = sales_funnel_rate((int)($order['completed'] ?? 0), max(1, (int)($order['total'] ?? 0)));
        $activeCoverage = sales_funnel_rate((int)($contentMetrics['active_pages'] ?? 0), max(1, (int)($contentMetrics['pages_total'] ?? 1)));

        $score = 18;
        $score += min(20, (int)round($leadToOrder * 1.5));
        $score += min(18, (int)round($inquiryToOrder * 1.2));
        $score += min(12, (int)round($proofToOrder * 0.7));
        $score += min(14, (int)round($completedRatio * 0.35));
        $score += min(12, (int)round($activeCoverage * 0.25));
        $score += min(12, (int)($lead['high_intent'] ?? 0) * 2);
        $score -= min(14, ((int)($oppMetrics['critical'] ?? 0) * 3) + ((int)($oppMetrics['high'] ?? 0) * 2));
        $score = max(1, min(100, $score));

        $label = 'Butuh Fondasi';
        $tone = 'error';
        if ($score >= 82) {
            $label = 'Siap Scale';
            $tone = 'success';
        } elseif ($score >= 65) {
            $label = 'Growth Sehat';
            $tone = 'info';
        } elseif ($score >= 45) {
            $label = 'Perlu Optimasi';
            $tone = 'warning';
        }

        return [
            'total' => $score,
            'label' => $label,
            'tone' => $tone,
            'inputs' => [
                'lead_to_order_rate' => $leadToOrder,
                'inquiry_to_order_rate' => $inquiryToOrder,
                'proof_to_order_rate' => $proofToOrder,
                'completed_ratio' => $completedRatio,
                'active_page_coverage' => $activeCoverage,
            ],
        ];
    }
}

if (!function_exists('sales_funnel_stages')) {
    function sales_funnel_stages(array $report): array
    {
        $lead = (array)($report['lead'] ?? []);
        $order = (array)($report['order'] ?? []);
        $payment = (array)($report['payment'] ?? []);

        $traffic = (int)($lead['events'] ?? 0);
        $intent = max((int)($lead['high_intent'] ?? 0), (int)($lead['whatsapp'] ?? 0));
        $inquiry = (int)($lead['inquiries'] ?? 0);
        $orders = (int)($order['total'] ?? 0);
        $payments = max((int)($payment['proofs'] ?? 0), (int)($payment['valid_proofs'] ?? 0) + (int)($order['payment_waiting'] ?? 0));
        $closing = (int)($order['completed'] ?? 0);

        $raw = [
            'traffic' => $traffic,
            'intent' => $intent,
            'inquiry' => $inquiry,
            'order' => $orders,
            'payment' => $payments,
            'closing' => $closing,
        ];

        $stages = [];
        $previous = 0;
        foreach ($raw as $key => $value) {
            $meta = sales_funnel_stage_meta($key);
            $health = sales_funnel_stage_health($key, (int)$value, (int)$previous);
            $drop = $key === 'traffic' ? 0 : max(0, (int)$previous - (int)$value);
            $stages[] = [
                'key' => $key,
                'label' => $meta['label'],
                'note' => $meta['note'],
                'focus' => $meta['focus'],
                'value' => (int)$value,
                'previous' => (int)$previous,
                'conversion_rate' => (float)$health['rate'],
                'drop' => $drop,
                'health' => $health,
            ];
            $previous = max(0, (int)$value);
        }

        return $stages;
    }
}

if (!function_exists('sales_funnel_bottlenecks')) {
    function sales_funnel_bottlenecks(array $stages, array $opportunity): array
    {
        $items = [];
        foreach ($stages as $stage) {
            if ((string)($stage['key'] ?? '') === 'traffic') {
                continue;
            }
            $rate = (float)($stage['conversion_rate'] ?? 0);
            $drop = (int)($stage['drop'] ?? 0);
            if ($drop > 0 || $rate < 25) {
                $items[] = [
                    'stage' => (string)($stage['key'] ?? ''),
                    'title' => 'Bottleneck di ' . (string)($stage['label'] ?? 'Funnel'),
                    'body' => (string)($stage['focus'] ?? 'Rapikan tahap funnel ini.'),
                    'score' => max(1, min(100, (int)round((100 - min(100, $rate)) + min(30, $drop * 4)))),
                    'tone' => $rate < 18 ? 'error' : 'warning',
                ];
            }
        }

        $oppMetrics = (array)($opportunity['metrics'] ?? []);
        if ((int)($oppMetrics['cta_gap'] ?? 0) > 0) {
            $items[] = ['stage' => 'intent', 'title' => 'CTA belum cukup mengangkat intent', 'body' => 'Rapikan CTA pada halaman yang sudah punya interaksi agar klik organik berubah menjadi WhatsApp/form.', 'score' => 76, 'tone' => 'warning'];
        }
        if ((int)($oppMetrics['checkout_gap'] ?? 0) > 0) {
            $items[] = ['stage' => 'order', 'title' => 'Checkout/closing perlu dipendekkan', 'body' => 'Cek form, invoice, pembayaran manual, dan follow-up agar prospek tidak tertahan sebelum order.', 'score' => 82, 'tone' => 'error'];
        }
        if ((int)($oppMetrics['support_gap'] ?? 0) > 0) {
            $items[] = ['stage' => 'traffic', 'title' => 'Money page butuh konten pendukung', 'body' => 'Tambahkan artikel/FAQ pendukung untuk mengalirkan traffic SEO ke halaman jualan prioritas.', 'score' => 68, 'tone' => 'info'];
        }

        usort($items, static fn(array $a, array $b): int => (int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0));
        return array_slice($items, 0, 8);
    }
}

if (!function_exists('sales_funnel_action_plan')) {
    function sales_funnel_action_plan(array $score, array $stages, array $content, array $opportunity): array
    {
        $plan = [];
        $stageMap = [];
        foreach ($stages as $stage) {
            $stageMap[(string)$stage['key']] = $stage;
        }
        $metrics = (array)($content['metrics'] ?? []);
        $opp = (array)($opportunity['metrics'] ?? []);

        if ((int)($stageMap['traffic']['value'] ?? 0) <= 3) {
            $plan[] = ['priority' => 'Tinggi', 'title' => 'Naikkan traffic berkualitas dulu', 'body' => 'Perkuat artikel pendukung, internal link, dan landing page prioritas agar tracking punya data yang cukup.'];
        }
        if ((int)($opp['cta_gap'] ?? 0) > 0 || (float)($stageMap['intent']['conversion_rate'] ?? 0) < 20) {
            $plan[] = ['priority' => 'Kritis', 'title' => 'Poles CTA di halaman yang sudah ada sinyal', 'body' => 'Letakkan CTA utama di atas, tengah, dan akhir halaman. Pakai alasan klik yang spesifik, bukan tombol generik.'];
        }
        if ((int)($opp['offer_gap'] ?? 0) > 0) {
            $plan[] = ['priority' => 'Tinggi', 'title' => 'Perkuat offer dan trust signal', 'body' => 'Tambahkan testimoni, FAQ keberatan, benefit paket, garansi/komitmen layanan, dan bukti hasil.'];
        }
        if ((int)($opp['checkout_gap'] ?? 0) > 0 || (float)($stageMap['order']['conversion_rate'] ?? 0) < 18) {
            $plan[] = ['priority' => 'Tinggi', 'title' => 'Kurangi hambatan order dan pembayaran', 'body' => 'Sederhanakan form, pastikan instruksi pembayaran jelas, lalu aktifkan follow-up untuk prospek yang belum bayar.'];
        }
        if ((int)($metrics['scale_winners'] ?? 0) > 0) {
            $plan[] = ['priority' => 'Scale', 'title' => 'Scale halaman pemenang', 'body' => 'Halaman dengan performa terbaik bisa didorong lewat internal link tambahan, artikel pendukung, dan campaign WhatsApp/iklan.'];
        }
        if (!$plan) {
            $plan[] = ['priority' => 'Pantau', 'title' => 'Funnel belum punya bottleneck besar', 'body' => 'Lanjutkan publish konten SEO, pantau Growth Insight, dan kumpulkan data lead/order lebih banyak.'];
        }

        return array_slice($plan, 0, 6);
    }
}

if (!function_exists('sales_funnel_playbooks')) {
    function sales_funnel_playbooks(array $summary): array
    {
        $score = (int)($summary['score']['total'] ?? 0);
        $report = (array)($summary['report'] ?? []);
        $content = (array)($summary['content_performance'] ?? []);
        $opp = (array)($summary['conversion_opportunity'] ?? []);
        $leadToOrder = (float)($report['conversion']['lead_to_order_rate'] ?? 0);
        $orders = (int)($report['order']['total'] ?? 0);
        $scaleWinners = (int)($content['metrics']['scale_winners'] ?? 0);
        $checkoutGap = (int)($opp['metrics']['checkout_gap'] ?? 0);

        $items = [
            ['title' => 'SEO → Lead Magnet', 'stage' => 'Traffic ke Lead', 'body' => 'Ambil artikel/landing page yang mulai aktif, tambahkan lead magnet kecil dan CTA konsultasi WhatsApp.', 'url' => function_exists('url') ? url('admin/seo-content-planner') : '', 'label' => 'Buka Content Planner'],
            ['title' => 'Lead → WhatsApp Follow-up', 'stage' => 'Lead ke Intent', 'body' => 'Siapkan template follow-up cepat untuk prospek dari form, WhatsApp, dan checkout awal.', 'url' => function_exists('url') ? url('admin/followups') : '', 'label' => 'Buka Follow-up'],
            ['title' => 'Offer Polish Sprint', 'stage' => 'Intent ke Order', 'body' => 'Poles 3 halaman penawaran terpenting dengan testimoni, FAQ keberatan, bonus, dan CTA spesifik.', 'url' => function_exists('url') ? url('admin/conversion-opportunities?category=offer_gap') : '', 'label' => 'Lihat Offer Gap'],
            ['title' => 'Payment Recovery', 'stage' => 'Order ke Bayar', 'body' => 'Gunakan reminder pembayaran dan instruksi invoice yang jelas untuk prospek yang sudah order.', 'url' => function_exists('url') ? url('admin/payment-reminders') : '', 'label' => 'Buka Reminder'],
        ];

        if ($scaleWinners > 0 || ($score >= 65 && $leadToOrder > 0)) {
            array_unshift($items, ['title' => 'Scale Winner Campaign', 'stage' => 'Scale', 'body' => 'Dorong halaman pemenang dengan internal link, artikel support, broadcast WhatsApp ringan, dan campaign tracking.', 'url' => function_exists('url') ? url('admin/content-performance?bucket=scale_winner') : '', 'label' => 'Lihat Winner']);
        }
        if ($checkoutGap > 0 || $orders === 0) {
            $items[] = ['title' => 'Checkout Friction Fix', 'stage' => 'Order', 'body' => 'Review form checkout, field wajib, harga/biaya, instruksi pembayaran, dan tombol konfirmasi.', 'url' => function_exists('url') ? url('admin/form-checkout') : '', 'label' => 'Cek Checkout'];
        }

        return array_slice($items, 0, 6);
    }
}

if (!function_exists('sales_funnel_sprint')) {
    function sales_funnel_sprint(array $summary): array
    {
        $bottlenecks = (array)($summary['bottlenecks'] ?? []);
        $first = $bottlenecks[0]['title'] ?? 'Poles CTA dan halaman prioritas';
        return [
            ['week' => 'Minggu 1', 'title' => 'Audit funnel dan pilih 3 halaman prioritas', 'tasks' => ['Buka Content Performance', 'Pilih money page dengan sinyal terbaik', 'Catat bottleneck utama: CTA, offer, checkout, atau payment']],
            ['week' => 'Minggu 2', 'title' => sales_funnel_clean((string)$first, 90), 'tasks' => ['Perbaiki CTA/offer di halaman prioritas', 'Tambahkan trust signal dan FAQ', 'Pastikan tracking lead tetap aktif']],
            ['week' => 'Minggu 3', 'title' => 'Bangun support content dan internal link', 'tasks' => ['Buat artikel pendukung dari SEO Content Planner', 'Pasang internal link ke money page', 'Cek Link Health setelah publish']],
            ['week' => 'Minggu 4', 'title' => 'Follow-up, payment recovery, dan scale', 'tasks' => ['Cek lead/order yang belum closing', 'Kirim follow-up/reminder manual', 'Scale halaman yang mulai menghasilkan sinyal']],
        ];
    }
}

if (!function_exists('sales_funnel_growth_summary')) {
    function sales_funnel_growth_summary(int $days = 30, array $filters = []): array
    {
        $report = function_exists('report_dashboard_summary') ? report_dashboard_summary($days, $filters) : [];
        $content = function_exists('content_performance_summary') ? content_performance_summary($days, $filters) : ['metrics' => [], 'rows' => []];
        $opportunity = function_exists('conversion_opportunity_summary') ? conversion_opportunity_summary($days, $filters) : ['metrics' => [], 'opportunities' => []];
        $growth = function_exists('growth_insight_summary') ? growth_insight_summary($days, $filters) : [];
        $stages = sales_funnel_stages($report);
        $score = sales_funnel_score($report, $content, $opportunity);
        $bottlenecks = sales_funnel_bottlenecks($stages, $opportunity);
        $actionPlan = sales_funnel_action_plan($score, $stages, $content, $opportunity);

        $summary = [
            'generated_at' => date('c'),
            'days' => $days,
            'filters' => $filters,
            'report' => $report,
            'growth' => $growth,
            'content_performance' => [
                'metrics' => (array)($content['metrics'] ?? []),
                'top_rows' => array_slice((array)($content['rows'] ?? []), 0, 10),
            ],
            'conversion_opportunity' => [
                'metrics' => (array)($opportunity['metrics'] ?? []),
                'top_items' => array_slice((array)($opportunity['opportunities'] ?? []), 0, 10),
            ],
            'score' => $score,
            'stages' => $stages,
            'bottlenecks' => $bottlenecks,
            'action_plan' => $actionPlan,
        ];
        $summary['playbooks'] = sales_funnel_playbooks($summary);
        $summary['sprint'] = sales_funnel_sprint($summary);

        return $summary;
    }
}
