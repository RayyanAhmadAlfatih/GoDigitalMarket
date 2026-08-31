<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UMKM GROWTH SNAPSHOT REPORT
|--------------------------------------------------------------------------
| One-page executive report that connects SEO health, content performance,
| sales funnel, conversion opportunities, and action scripts into a simple
| story that can be shown to UMKM owners before execution.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('growth_snapshot_clean')) {
    function growth_snapshot_clean(string $value, int $max = 180): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('growth_snapshot_current_url')) {
    function growth_snapshot_current_url(array $params, array $extra = []): string
    {
        $query = array_merge([
            'range' => (string)($params['range'] ?? '30'),
            'year' => (string)($params['year'] ?? ''),
            'date_from' => (string)($params['date_from'] ?? ''),
            'date_to' => (string)($params['date_to'] ?? ''),
        ], $extra);

        $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
        return function_exists('url') ? url('admin/growth-snapshot' . ($query ? '?' . http_build_query($query) : '')) : 'admin/growth-snapshot';
    }
}

if (!function_exists('growth_snapshot_tone')) {
    function growth_snapshot_tone(int $score): array
    {
        if ($score >= 82) {
            return ['key' => 'success', 'label' => 'Siap Ditunjukkan & Discales', 'note' => 'Fondasi sudah kuat. Fokus berikutnya memperbesar traffic dan follow-up.'];
        }
        if ($score >= 65) {
            return ['key' => 'info', 'label' => 'Sudah Layak Demo', 'note' => 'Sudah enak ditunjukkan. Tinggal polish CTA, offer, dan support content.'];
        }
        if ($score >= 45) {
            return ['key' => 'warning', 'label' => 'Perlu Polish Sebelum Scale', 'note' => 'Fondasi ada, tapi beberapa area growth masih perlu dirapikan.'];
        }
        return ['key' => 'error', 'label' => 'Butuh Fondasi Growth', 'note' => 'Mulai dari SEO dasar, CTA, tracking lead, dan funnel sederhana dulu.'];
    }
}

if (!function_exists('growth_snapshot_kpi')) {
    function growth_snapshot_kpi(string $label, int|string $value, string $note = '', string $tone = 'neutral'): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'note' => $note,
            'tone' => $tone,
        ];
    }
}

if (!function_exists('growth_snapshot_story')) {
    function growth_snapshot_story(array $snapshot): array
    {
        $readiness = (int)($snapshot['score']['total'] ?? 0);
        $seoScore = (int)($snapshot['scores']['seo'] ?? 0);
        $funnelScore = (int)($snapshot['scores']['funnel'] ?? 0);
        $actions = (int)($snapshot['metrics']['total_actions'] ?? 0);
        $opportunities = (int)($snapshot['metrics']['opportunities'] ?? 0);
        $activePages = (int)($snapshot['metrics']['active_pages'] ?? 0);

        $story = [];
        $story[] = 'Website ini tidak hanya menampilkan katalog, tetapi mulai membaca hubungan antara SEO, konten, CTA, lead, order, dan follow-up.';

        if ($seoScore >= 75) {
            $story[] = 'Fondasi SEO sudah cukup kuat untuk mulai diarahkan ke strategi internal link, konten pendukung, dan halaman penawaran.';
        } else {
            $story[] = 'SEO dasar masih perlu dipoles agar halaman penting lebih siap bersaing di pencarian organik.';
        }

        if ($funnelScore >= 60) {
            $story[] = 'Funnel bisnis sudah mulai terbaca, jadi optimasi bisa difokuskan ke titik yang paling dekat dengan konversi.';
        } else {
            $story[] = 'Funnel masih perlu diperjelas dari traffic menjadi lead, lalu dari inquiry menjadi order dan pembayaran.';
        }

        if ($actions > 0) {
            $story[] = 'Sistem sudah menyiapkan ' . $actions . ' aksi praktis yang bisa langsung dieksekusi oleh admin atau tim marketing.';
        }

        if ($opportunities > 0) {
            $story[] = 'Ada ' . $opportunities . ' peluang konversi yang bisa dipoles agar traffic organik tidak berhenti sebagai kunjungan pasif.';
        }

        if ($activePages <= 0) {
            $story[] = 'Tracking interaksi perlu mulai diaktifkan dan dipakai agar data performa halaman makin akurat dari waktu ke waktu.';
        }

        $story[] = $readiness >= 65
            ? 'Kesimpulannya: website sudah punya bahan demo yang kuat untuk menunjukkan bahwa SEO bisa nyambung ke growth bisnis.'
            : 'Kesimpulannya: website punya fondasi growth, tetapi perlu sprint polish singkat sebelum dipakai untuk scale serius.';

        return $story;
    }
}

if (!function_exists('growth_snapshot_summary')) {
    function growth_snapshot_summary(int $days = 30, array $filters = []): array
    {
        $seo = function_exists('universal_seo_summary') ? universal_seo_summary('all') : ['score_average' => 0, 'counts' => [], 'items' => [], 'action_plan' => []];
        $content = function_exists('content_performance_summary') ? content_performance_summary($days, $filters) : ['metrics' => [], 'rows' => [], 'action_plan' => []];
        $conversion = function_exists('conversion_opportunity_summary') ? conversion_opportunity_summary($days, $filters) : ['metrics' => [], 'opportunities' => [], 'action_plan' => []];
        $funnel = function_exists('sales_funnel_growth_summary') ? sales_funnel_growth_summary($days, $filters) : ['score' => [], 'stages' => [], 'bottlenecks' => [], 'action_plan' => [], 'sprint' => []];
        $actions = function_exists('sales_action_center_summary') ? sales_action_center_summary($days, $filters) : ['metrics' => [], 'rows' => []];
        $linkHealth = function_exists('seo_link_health_summary') ? seo_link_health_summary() : ['metrics' => [], 'action_plan' => []];
        $report = function_exists('report_dashboard_summary') ? report_dashboard_summary($days, $filters) : [];
        $business = function_exists('business_settings') ? business_settings() : [];
        $mode = function_exists('business_current_mode') ? business_current_mode() : ['label' => 'Hybrid Growth Website'];

        $seoScore = (int)($seo['score_average'] ?? 0);
        $funnelScore = (int)($funnel['score']['total'] ?? 0);
        $contentScore = (int)($content['metrics']['performance_score_avg'] ?? 0);
        $linkScore = (int)($linkHealth['metrics']['health_score'] ?? 100);
        $actionCount = (int)($actions['metrics']['total_actions'] ?? 0);
        $actionReadiness = min(100, 36 + ($actionCount * 5) + ((int)($actions['metrics']['critical'] ?? 0) * 4) + ((int)($actions['metrics']['high'] ?? 0) * 3));

        $readiness = (int)round(
            ($seoScore * 0.28) +
            ($funnelScore * 0.28) +
            ($contentScore * 0.20) +
            ($linkScore * 0.10) +
            ($actionReadiness * 0.14)
        );
        $readiness = max(1, min(100, $readiness));
        $tone = growth_snapshot_tone($readiness);

        $contentMetrics = (array)($content['metrics'] ?? []);
        $conversionMetrics = (array)($conversion['metrics'] ?? []);
        $reportLead = (array)($report['lead'] ?? []);
        $reportOrder = (array)($report['order'] ?? []);
        $payment = (array)($report['payment'] ?? []);

        $topContent = array_slice((array)($content['rows'] ?? []), 0, 6);
        $topOpportunities = array_slice((array)($conversion['opportunities'] ?? []), 0, 6);
        $topActions = array_slice((array)($actions['rows'] ?? []), 0, 6);
        $sprint = array_slice((array)($funnel['sprint'] ?? []), 0, 4);

        $kpis = [
            growth_snapshot_kpi('Readiness', $readiness . '/100', (string)$tone['label'], (string)$tone['key']),
            growth_snapshot_kpi('SEO Score', $seoScore . '/100', 'Rata-rata audit SEO universal', $seoScore >= 75 ? 'success' : ($seoScore >= 55 ? 'warning' : 'error')),
            growth_snapshot_kpi('Funnel Score', $funnelScore . '/100', (string)($funnel['score']['label'] ?? 'Sales funnel'), (string)($funnel['score']['tone'] ?? 'neutral')),
            growth_snapshot_kpi('Action Ready', $actionCount, 'Aksi praktis siap dieksekusi', $actionCount > 0 ? 'info' : 'neutral'),
            growth_snapshot_kpi('Lead Event', (int)($reportLead['events'] ?? 0), 'Klik CTA, WhatsApp, form, dan event tracking', 'info'),
            growth_snapshot_kpi('Order', (int)($reportOrder['total'] ?? 0), 'Pesanan/order yang terbaca', ((int)($reportOrder['total'] ?? 0)) > 0 ? 'success' : 'neutral'),
        ];

        $highlights = [];
        $highlights[] = 'Mode website: ' . growth_snapshot_clean((string)($mode['label'] ?? 'Hybrid Growth Website'), 80);
        $highlights[] = 'Halaman indexable: ' . (int)($seo['counts']['indexable'] ?? 0) . ' dari ' . (int)($seo['counts']['total'] ?? 0) . ' item audit.';
        $highlights[] = 'Internal link health: ' . $linkScore . '/100 dengan ' . (int)($linkHealth['metrics']['broken_links'] ?? 0) . ' link rusak terdeteksi.';
        $highlights[] = 'Peluang konversi: ' . (int)($conversionMetrics['opportunities_total'] ?? 0) . ' item, termasuk ' . (int)($conversionMetrics['high'] ?? 0) . ' prioritas tinggi.';
        $highlights[] = 'Performa konten: ' . (int)($contentMetrics['active_pages'] ?? 0) . ' halaman punya sinyal interaksi dari ' . (int)($contentMetrics['pages_total'] ?? 0) . ' halaman.';
        $highlights[] = 'Pembayaran/bukti bayar terbaca: ' . (int)($payment['proofs'] ?? 0) . ' bukti/status pembayaran.';

        $nextMoves = [];
        foreach ([(array)($funnel['action_plan'] ?? []), (array)($conversion['action_plan'] ?? []), (array)($content['action_plan'] ?? []), (array)($linkHealth['action_plan'] ?? [])] as $plans) {
            foreach ($plans as $plan) {
                if (is_array($plan)) {
                    $plan = trim((string)($plan['title'] ?? '') . ' — ' . (string)($plan['body'] ?? $plan['note'] ?? ''));
                }
                $plan = growth_snapshot_clean((string)$plan, 220);
                if ($plan !== '' && !in_array($plan, $nextMoves, true)) {
                    $nextMoves[] = $plan;
                }
                if (count($nextMoves) >= 8) {
                    break 2;
                }
            }
        }
        if (!$nextMoves) {
            $nextMoves[] = 'Mulai dari audit SEO, polish CTA utama, aktifkan tracking lead, lalu cek Growth Insight setiap pekan.';
        }

        $snapshot = [
            'generated_at' => date('c'),
            'days' => $days,
            'filters' => $filters,
            'business' => [
                'name' => (string)($business['business_name'] ?? (defined('SITE_NAME') ? SITE_NAME : 'UMKM Growth Web Template')),
                'mode' => (string)($mode['label'] ?? 'Hybrid Growth Website'),
                'tagline' => (string)($business['tagline'] ?? ''),
                'url' => defined('SITE_URL') ? SITE_URL : '',
            ],
            'score' => [
                'total' => $readiness,
                'label' => (string)$tone['label'],
                'tone' => (string)$tone['key'],
                'note' => (string)$tone['note'],
            ],
            'scores' => [
                'seo' => $seoScore,
                'funnel' => $funnelScore,
                'content' => $contentScore,
                'internal_link' => $linkScore,
                'action_readiness' => $actionReadiness,
            ],
            'metrics' => [
                'pages_total' => (int)($contentMetrics['pages_total'] ?? 0),
                'active_pages' => (int)($contentMetrics['active_pages'] ?? 0),
                'scale_winners' => (int)($contentMetrics['scale_winners'] ?? 0),
                'cta_polish' => (int)($contentMetrics['cta_polish'] ?? 0),
                'seo_boost' => (int)($contentMetrics['seo_boost'] ?? 0),
                'build_support' => (int)($contentMetrics['build_support'] ?? 0),
                'opportunities' => (int)($conversionMetrics['opportunities_total'] ?? 0),
                'critical_opportunities' => (int)($conversionMetrics['critical'] ?? 0),
                'high_opportunities' => (int)($conversionMetrics['high'] ?? 0),
                'total_actions' => $actionCount,
                'lead_events' => (int)($reportLead['events'] ?? 0),
                'high_intent' => (int)($reportLead['high_intent'] ?? 0),
                'inquiries' => (int)($reportLead['inquiries'] ?? 0),
                'orders' => (int)($reportOrder['total'] ?? 0),
                'completed_orders' => (int)($reportOrder['completed'] ?? 0),
                'payment_proofs' => (int)($payment['proofs'] ?? 0),
            ],
            'kpis' => $kpis,
            'highlights' => $highlights,
            'story' => [],
            'next_moves' => $nextMoves,
            'top_content' => $topContent,
            'top_opportunities' => $topOpportunities,
            'top_actions' => $topActions,
            'sprint' => $sprint,
            'sources' => [
                'seo' => $seo,
                'content' => ['metrics' => $contentMetrics],
                'conversion' => ['metrics' => $conversionMetrics],
                'funnel' => ['score' => (array)($funnel['score'] ?? []), 'stages' => (array)($funnel['stages'] ?? []), 'bottlenecks' => (array)($funnel['bottlenecks'] ?? [])],
                'link_health' => ['metrics' => (array)($linkHealth['metrics'] ?? [])],
            ],
        ];

        $snapshot['story'] = growth_snapshot_story($snapshot);

        return $snapshot;
    }
}
