<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('looker_studio_dashboard_template_pack')) {
    function looker_studio_dashboard_template_pack(): array
    {
        return [
            'owner_overview' => [
                'title' => 'Owner Overview',
                'audience' => 'Owner / CEO',
                'goal' => 'Membaca kondisi bisnis harian dan menentukan prioritas scale up.',
                'primary_source' => 'orders',
                'sources' => ['orders', 'form_submissions', 'seo_profit_attribution', 'profit_actions'],
                'scorecards' => [
                    ['label' => 'Omzet', 'source' => 'orders', 'field' => 'total', 'aggregation' => 'SUM'],
                    ['label' => 'Order Masuk', 'source' => 'orders', 'field' => 'order_ref', 'aggregation' => 'COUNT'],
                    ['label' => 'Lead Baru', 'source' => 'form_submissions', 'field' => 'lead_name', 'aggregation' => 'COUNT'],
                    ['label' => 'Order Belum Bayar', 'source' => 'orders', 'field' => 'pending_amount', 'aggregation' => 'SUM'],
                ],
                'charts' => [
                    ['title' => 'Omzet harian', 'type' => 'time_series', 'source' => 'orders', 'dimension' => 'date', 'metric' => 'total', 'decision' => 'Melihat hari paling kuat dan pola penjualan.'],
                    ['title' => 'Lead vs order', 'type' => 'combo', 'source' => 'orders + form_submissions', 'dimension' => 'date', 'metric' => 'lead/order', 'decision' => 'Mengukur apakah traffic/lead berubah menjadi transaksi.'],
                    ['title' => 'Status pembayaran', 'type' => 'bar', 'source' => 'orders', 'dimension' => 'payment_status', 'metric' => 'order_ref', 'decision' => 'Menentukan follow-up invoice dan prioritas CS.'],
                ],
                'filters' => ['date', 'payment_status', 'source', 'campaign'],
                'decision_questions' => ['Omzet naik dari channel mana?', 'Order belum bayar berapa nilainya?', 'Action plan mana yang paling urgent?'],
            ],
            'sales_payment' => [
                'title' => 'Sales & Payment Dashboard',
                'audience' => 'Sales Specialist / admin penjualan',
                'goal' => 'Memantau order, pembayaran, bukti bayar, dan invoice yang perlu ditindaklanjuti.',
                'primary_source' => 'orders',
                'sources' => ['orders', 'payment_proofs', 'buyer_accounts'],
                'scorecards' => [
                    ['label' => 'Order Baru', 'source' => 'orders', 'field' => 'order_ref', 'aggregation' => 'COUNT'],
                    ['label' => 'Pembayaran Lunas', 'source' => 'orders', 'field' => 'total', 'aggregation' => 'SUM'],
                    ['label' => 'Tagihan Tertunda', 'source' => 'orders', 'field' => 'pending_amount', 'aggregation' => 'SUM'],
                    ['label' => 'Bukti Bayar Masuk', 'source' => 'payment_proofs', 'field' => 'order_ref', 'aggregation' => 'COUNT'],
                ],
                'charts' => [
                    ['title' => 'Omzet berdasarkan tanggal', 'type' => 'time_series', 'source' => 'orders', 'dimension' => 'date', 'metric' => 'total', 'decision' => 'Melihat tren penjualan.'],
                    ['title' => 'Status pembayaran order', 'type' => 'bar', 'source' => 'orders', 'dimension' => 'payment_status', 'metric' => 'order_ref', 'decision' => 'Menentukan prioritas follow-up pembayaran.'],
                    ['title' => 'Kota pembeli', 'type' => 'bar', 'source' => 'orders', 'dimension' => 'city', 'metric' => 'order_ref', 'decision' => 'Menentukan area promosi dan pengiriman.'],
                ],
                'filters' => ['date', 'payment_status', 'order_status', 'city'],
                'decision_questions' => ['Invoice mana yang perlu ditagih?', 'Produk mana paling sering dibeli?', 'Area mana paling potensial?'],
            ],
            'lead_crm' => [
                'title' => 'Lead & CRM Dashboard',
                'audience' => 'CS / tim follow-up',
                'goal' => 'Membedakan lead panas, lead dingin, dan sumber lead paling efektif.',
                'primary_source' => 'form_submissions',
                'sources' => ['form_submissions', 'lead_quality_scores', 'analytics_events'],
                'scorecards' => [
                    ['label' => 'Lead Baru', 'source' => 'form_submissions', 'field' => 'lead_name', 'aggregation' => 'COUNT'],
                    ['label' => 'Rata-rata Skor Lead', 'source' => 'lead_quality_scores', 'field' => 'lead_score', 'aggregation' => 'AVG'],
                    ['label' => 'Lead Belum Follow-up', 'source' => 'form_submissions', 'field' => 'followup_status', 'aggregation' => 'COUNT'],
                ],
                'charts' => [
                    ['title' => 'Lead harian', 'type' => 'time_series', 'source' => 'form_submissions', 'dimension' => 'date', 'metric' => 'lead_name', 'decision' => 'Melihat performa campaign harian.'],
                    ['title' => 'Sumber lead', 'type' => 'bar', 'source' => 'form_submissions', 'dimension' => 'source', 'metric' => 'lead_name', 'decision' => 'Menentukan channel yang perlu ditambah budget/waktu.'],
                    ['title' => 'Lead score', 'type' => 'bar', 'source' => 'lead_quality_scores', 'dimension' => 'temperature', 'metric' => 'lead_score', 'decision' => 'Memilih lead yang harus difollow-up duluan.'],
                ],
                'filters' => ['date', 'source', 'campaign', 'followup_status', 'temperature'],
                'decision_questions' => ['Lead dari mana yang paling siap beli?', 'Siapa lead yang belum difollow-up?', 'Campaign mana yang perlu dinaikkan?'],
            ],
            'seo_profit' => [
                'title' => 'SEO Profit Dashboard',
                'audience' => 'SEO / content / owner',
                'goal' => 'Melihat halaman SEO yang menghasilkan lead, order, dan omzet.',
                'primary_source' => 'seo_profit_attribution',
                'sources' => ['seo_profit_attribution', 'seo_content_refresh', 'seo_money_pages', 'internal_link_cta'],
                'scorecards' => [
                    ['label' => 'Traffic SEO', 'source' => 'seo_profit_attribution', 'field' => 'traffic', 'aggregation' => 'SUM'],
                    ['label' => 'Lead SEO', 'source' => 'seo_profit_attribution', 'field' => 'leads', 'aggregation' => 'SUM'],
                    ['label' => 'Order SEO', 'source' => 'seo_profit_attribution', 'field' => 'orders', 'aggregation' => 'SUM'],
                    ['label' => 'Omzet SEO', 'source' => 'seo_profit_attribution', 'field' => 'revenue', 'aggregation' => 'SUM'],
                ],
                'charts' => [
                    ['title' => 'Revenue per halaman', 'type' => 'bar', 'source' => 'seo_profit_attribution', 'dimension' => 'page_title', 'metric' => 'revenue', 'decision' => 'Menentukan money page yang harus dijaga dan diperkuat.'],
                    ['title' => 'Traffic vs lead', 'type' => 'scatter', 'source' => 'seo_profit_attribution', 'dimension' => 'traffic', 'metric' => 'leads', 'decision' => 'Melihat halaman ramai tapi belum menghasilkan.'],
                    ['title' => 'Prioritas refresh konten', 'type' => 'table', 'source' => 'seo_content_refresh', 'dimension' => 'page_title', 'metric' => 'priority', 'decision' => 'Memilih konten yang perlu diperbaiki duluan.'],
                ],
                'filters' => ['keyword', 'priority', 'status', 'date'],
                'decision_questions' => ['Halaman mana yang paling menghasilkan?', 'Halaman mana ramai tapi belum closing?', 'Konten mana harus direfresh minggu ini?'],
            ],
            'campaign_cta' => [
                'title' => 'CTA & Campaign Dashboard',
                'audience' => 'Marketing / growth',
                'goal' => 'Menilai offer, CTA, campaign, dan eksperimen yang paling menghasilkan konversi.',
                'primary_source' => 'cta_results',
                'sources' => ['offer_cta_tests', 'cta_placements', 'cta_results', 'seo_campaign_calendar'],
                'scorecards' => [
                    ['label' => 'CTA Aktif', 'source' => 'offer_cta_tests', 'field' => 'variant_name', 'aggregation' => 'COUNT'],
                    ['label' => 'Klik CTA', 'source' => 'cta_results', 'field' => 'clicks', 'aggregation' => 'SUM'],
                    ['label' => 'Lead dari CTA', 'source' => 'cta_results', 'field' => 'leads', 'aggregation' => 'SUM'],
                    ['label' => 'Conversion Rate', 'source' => 'cta_results', 'field' => 'conversion_rate', 'aggregation' => 'AVG'],
                ],
                'charts' => [
                    ['title' => 'Klik vs lead', 'type' => 'combo', 'source' => 'cta_results', 'dimension' => 'placement', 'metric' => 'clicks/leads', 'decision' => 'Menilai CTA mana yang benar-benar menghasilkan lead.'],
                    ['title' => 'Conversion rate per CTA', 'type' => 'bar', 'source' => 'cta_results', 'dimension' => 'placement', 'metric' => 'conversion_rate', 'decision' => 'Memilih CTA pemenang.'],
                    ['title' => 'Campaign status', 'type' => 'bar', 'source' => 'seo_campaign_calendar', 'dimension' => 'status', 'metric' => 'task_title', 'decision' => 'Menjaga sprint campaign tetap jalan.'],
                ],
                'filters' => ['status', 'campaign', 'priority', 'period'],
                'decision_questions' => ['CTA mana yang harus dipakai permanen?', 'Campaign mana yang terlambat?', 'Offer mana yang perlu diulang?'],
            ],
            'member_digital_product' => [
                'title' => 'Member & Digital Product Dashboard',
                'audience' => 'Admin produk digital / support',
                'goal' => 'Memantau akses produk digital, member aktif, dan akses yang hampir berakhir.',
                'primary_source' => 'member_access',
                'sources' => ['member_access', 'buyer_accounts', 'orders'],
                'scorecards' => [
                    ['label' => 'Member Aktif', 'source' => 'buyer_accounts', 'field' => 'buyer_name', 'aggregation' => 'COUNT'],
                    ['label' => 'Akses Aktif', 'source' => 'member_access', 'field' => 'access_status', 'aggregation' => 'COUNT'],
                    ['label' => 'Produk Digital Terjual', 'source' => 'orders', 'field' => 'product_name', 'aggregation' => 'COUNT'],
                ],
                'charts' => [
                    ['title' => 'Akses per produk', 'type' => 'bar', 'source' => 'member_access', 'dimension' => 'product_name', 'metric' => 'buyer_name', 'decision' => 'Melihat produk digital paling aktif.'],
                    ['title' => 'Member baru', 'type' => 'time_series', 'source' => 'buyer_accounts', 'dimension' => 'date', 'metric' => 'buyer_name', 'decision' => 'Melihat pertumbuhan member.'],
                    ['title' => 'Status akses', 'type' => 'bar', 'source' => 'member_access', 'dimension' => 'access_status', 'metric' => 'buyer_name', 'decision' => 'Menentukan follow-up akses expired.'],
                ],
                'filters' => ['date', 'access_status', 'product_name'],
                'decision_questions' => ['Produk digital mana paling aktif?', 'Akses siapa yang hampir expired?', 'Member mana perlu dibantu login?'],
            ],
        ];
    }
}

if (!function_exists('looker_studio_dashboard_template_summary')) {
    function looker_studio_dashboard_template_summary(): array
    {
        $pack = looker_studio_dashboard_template_pack();
        $sources = [];
        $charts = 0;
        $scorecards = 0;
        foreach ($pack as $dashboard) {
            foreach ((array)($dashboard['sources'] ?? []) as $source) {
                $sources[$source] = true;
            }
            $charts += count((array)($dashboard['charts'] ?? []));
            $scorecards += count((array)($dashboard['scorecards'] ?? []));
        }
        return [
            'dashboards' => count($pack),
            'sources' => count($sources),
            'charts' => $charts,
            'scorecards' => $scorecards,
            'source_keys' => array_keys($sources),
        ];
    }
}

if (!function_exists('looker_studio_dashboard_template_sheet_matrix')) {
    function looker_studio_dashboard_template_sheet_matrix(): array
    {
        $sources = function_exists('looker_studio_dashboard_sources') ? looker_studio_dashboard_sources() : [];
        $rows = [];
        foreach ($sources as $source) {
            $meta = function_exists('looker_studio_source_meta') ? (looker_studio_source_meta((string)$source) ?: []) : [];
            $schema = function_exists('looker_studio_schema') ? looker_studio_schema((string)$source) : [];
            $rows[] = [
                'source' => (string)$source,
                'label' => (string)($meta['label'] ?? $source),
                'sheet_name' => (string)($meta['sheet_name'] ?? $source),
                'fields' => array_map(static fn(array $field): string => (string)($field['name'] ?? ''), $schema),
                'field_count' => count($schema),
                'records' => function_exists('looker_studio_record_count') ? looker_studio_record_count((string)$source) : 0,
            ];
        }
        return $rows;
    }
}

if (!function_exists('looker_studio_dashboard_template_chart_rows')) {
    function looker_studio_dashboard_template_chart_rows(): array
    {
        $rows = [];
        foreach (looker_studio_dashboard_template_pack() as $key => $dashboard) {
            foreach ((array)($dashboard['scorecards'] ?? []) as $scorecard) {
                $rows[] = [
                    'dashboard' => (string)($dashboard['title'] ?? $key),
                    'item_type' => 'scorecard',
                    'title' => (string)($scorecard['label'] ?? ''),
                    'chart_type' => 'scorecard',
                    'source' => (string)($scorecard['source'] ?? ''),
                    'dimension' => '',
                    'metric' => (string)($scorecard['field'] ?? ''),
                    'aggregation' => (string)($scorecard['aggregation'] ?? ''),
                    'decision' => (string)($scorecard['note'] ?? ''),
                ];
            }
            foreach ((array)($dashboard['charts'] ?? []) as $chart) {
                $rows[] = [
                    'dashboard' => (string)($dashboard['title'] ?? $key),
                    'item_type' => 'chart',
                    'title' => (string)($chart['title'] ?? ''),
                    'chart_type' => (string)($chart['type'] ?? ''),
                    'source' => (string)($chart['source'] ?? ''),
                    'dimension' => (string)($chart['dimension'] ?? ''),
                    'metric' => (string)($chart['metric'] ?? ''),
                    'aggregation' => '',
                    'decision' => (string)($chart['decision'] ?? ''),
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('looker_studio_dashboard_template_readiness')) {
    function looker_studio_dashboard_template_readiness(): array
    {
        $summary = looker_studio_dashboard_template_summary();
        $appsCode = function_exists('cloud_backup_apps_script_code') ? cloud_backup_apps_script_code() : '';
        $connectorCode = function_exists('looker_studio_connector_code') ? looker_studio_connector_code() : '';
        $checks = [
            ['label' => 'Dashboard pack', 'ok' => $summary['dashboards'] >= 6],
            ['label' => 'Chart blueprint', 'ok' => $summary['charts'] >= 12 && $summary['scorecards'] >= 12],
            ['label' => 'Sheet matrix', 'ok' => count(looker_studio_dashboard_template_sheet_matrix()) >= 12],
            ['label' => 'Apps Script guide tabs', 'ok' => str_contains($appsCode, 'setupUGrowthDashboardTemplate') && str_contains($appsCode, '_dashboard_guide')],
            ['label' => 'Direct connector schema', 'ok' => str_contains($connectorCode, 'getSchema') && str_contains($connectorCode, 'getData')],
            ['label' => 'Guide document', 'ok' => is_file(ROOT_PATH . '/docs/looker-studio-template-pack-guide.md')],
        ];
        $ready = count(array_filter($checks, static fn(array $check): bool => !empty($check['ok'])));
        return [
            'checks' => $checks,
            'ready' => $ready,
            'total' => count($checks),
            'score' => count($checks) > 0 ? (int)round(($ready / count($checks)) * 100) : 0,
            'summary' => $summary,
        ];
    }
}
