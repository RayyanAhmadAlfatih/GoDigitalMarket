<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PROFIT REPORT BUILDER
|--------------------------------------------------------------------------
| Executive report layer for owner/CEO review. This module does not create
| a new tracking source. It summarizes existing sales, lead, SEO, CTA,
| money page, content refresh, and follow-up signals into a decision report.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('profit_report_clean')) {
    function profit_report_clean(mixed $value, int $max = 220): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('profit_report_storage_file')) {
    function profit_report_storage_file(): string
    {
        return STORAGE_PATH . '/profit-report-builder.json';
    }
}

if (!function_exists('profit_report_default_settings')) {
    function profit_report_default_settings(): array
    {
        return [
            'notes' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('profit_report_normalize_settings')) {
    function profit_report_normalize_settings(array $settings): array
    {
        $settings = array_merge(profit_report_default_settings(), $settings);
        $notes = [];
        foreach ((array)($settings['notes'] ?? []) as $key => $note) {
            if (!is_array($note)) {
                continue;
            }
            $id = profit_report_clean((string)($key ?: ($note['id'] ?? '')), 80);
            if ($id === '') {
                continue;
            }
            $notes[$id] = [
                'id' => $id,
                'title' => profit_report_clean($note['title'] ?? '', 120),
                'note' => profit_report_clean($note['note'] ?? '', 1800),
                'owner' => profit_report_clean($note['owner'] ?? '', 80),
                'updated_at' => profit_report_clean($note['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
            ];
        }
        return [
            'notes' => $notes,
            'updated_at' => profit_report_clean($settings['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('profit_report_settings')) {
    function profit_report_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }
        $file = profit_report_storage_file();
        if (!is_file($file)) {
            $cached = profit_report_normalize_settings(profit_report_default_settings());
            return $cached;
        }
        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = profit_report_normalize_settings(profit_report_default_settings());
            return $cached;
        }
        $cached = profit_report_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('profit_report_write_settings')) {
    function profit_report_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = profit_report_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);
        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(profit_report_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan Profit Report belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }
        @chmod(profit_report_storage_file(), 0644);
        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'profit-report-builder', null, 'Menyimpan catatan Profit Report Builder.');
        }
        return true;
    }
}

if (!function_exists('profit_report_update_note')) {
    function profit_report_update_note(string $id, string $title, string $note, string $owner = ''): bool
    {
        $id = profit_report_clean($id, 80);
        if ($id === '') {
            throw new RuntimeException('ID catatan laporan tidak valid.');
        }
        $settings = profit_report_settings(true);
        $settings['notes'][$id] = [
            'id' => $id,
            'title' => profit_report_clean($title, 120),
            'note' => profit_report_clean($note, 1800),
            'owner' => profit_report_clean($owner, 80),
            'updated_at' => date(DATE_ATOM),
        ];
        return profit_report_write_settings($settings, true);
    }
}

if (!function_exists('profit_report_range_options')) {
    function profit_report_range_options(): array
    {
        return [
            7 => '7 hari',
            14 => '14 hari',
            30 => '30 hari',
            60 => '60 hari',
            90 => '90 hari',
            180 => '180 hari',
            365 => '365 hari',
        ];
    }
}

if (!function_exists('profit_report_number')) {
    function profit_report_number(int|float $value): string
    {
        return number_format((float)$value, 0, ',', '.');
    }
}

if (!function_exists('profit_report_money')) {
    function profit_report_money(int|float $value): string
    {
        return function_exists('rupiah') ? rupiah($value) : 'Rp ' . profit_report_number($value);
    }
}

if (!function_exists('profit_report_top_seo_pages')) {
    function profit_report_top_seo_pages(array $seoProfit, int $limit = 5): array
    {
        $items = [];
        foreach ((array)($seoProfit['results'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = (array)($row['item'] ?? []);
            $metrics = (array)($row['metrics'] ?? []);
            $items[] = [
                'title' => profit_report_clean($item['title'] ?? 'Halaman SEO', 140),
                'url' => (string)($item['url'] ?? ''),
                'type' => profit_report_clean($item['type_label'] ?? $item['type'] ?? 'SEO Page', 80),
                'score' => (int)($row['profit_score'] ?? 0),
                'clicks' => (int)($metrics['clicks'] ?? 0),
                'leads' => (int)($metrics['leads'] ?? 0),
                'orders' => (int)($metrics['orders'] ?? 0),
                'recommendation' => profit_report_clean($row['recommendation']['label'] ?? $row['recommendation']['title'] ?? '', 180),
            ];
        }
        usort($items, static function (array $a, array $b): int {
            $aw = ((int)$a['orders'] * 12) + ((int)$a['leads'] * 7) + ((int)$a['clicks'] * 2) + (int)$a['score'];
            $bw = ((int)$b['orders'] * 12) + ((int)$b['leads'] * 7) + ((int)$b['clicks'] * 2) + (int)$b['score'];
            return $bw <=> $aw;
        });
        return array_slice($items, 0, max(1, $limit));
    }
}

if (!function_exists('profit_report_top_ctas')) {
    function profit_report_top_ctas(array $ctaSummary, int $limit = 5): array
    {
        $items = [];
        foreach ((array)($ctaSummary['deployment_results'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $deployment = (array)($row['deployment'] ?? []);
            $metrics = (array)($row['metrics'] ?? []);
            $variant = (array)($row['variant'] ?? []);
            $items[] = [
                'title' => profit_report_clean($variant['title'] ?? $deployment['variant_title'] ?? $deployment['title'] ?? 'CTA Placement', 140),
                'placement' => profit_report_clean($deployment['placement_label'] ?? $deployment['placement'] ?? 'Placement', 100),
                'score' => (int)($row['result_score'] ?? 0),
                'clicks' => (int)($metrics['clicks'] ?? 0),
                'leads' => (int)($metrics['leads'] ?? 0),
                'orders' => (int)($metrics['orders'] ?? 0),
                'recommendation' => profit_report_clean($row['recommendation']['label'] ?? $row['recommendation']['title'] ?? '', 180),
            ];
        }
        usort($items, static function (array $a, array $b): int {
            $aw = ((int)$a['orders'] * 12) + ((int)$a['leads'] * 7) + ((int)$a['clicks'] * 2) + (int)$a['score'];
            $bw = ((int)$b['orders'] * 12) + ((int)$b['leads'] * 7) + ((int)$b['clicks'] * 2) + (int)$b['score'];
            return $bw <=> $aw;
        });
        return array_slice($items, 0, max(1, $limit));
    }
}

if (!function_exists('profit_report_top_leads')) {
    function profit_report_top_leads(array $leadQuality, int $limit = 5): array
    {
        $items = [];
        foreach ((array)($leadQuality['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $items[] = [
                'name' => profit_report_clean($row['name'] ?? $row['title'] ?? 'Lead', 140),
                'type' => profit_report_clean($row['type_label'] ?? $row['type'] ?? 'Lead', 80),
                'priority' => profit_report_clean($row['priority_label'] ?? $row['priority'] ?? 'Lead', 40),
                'score' => (int)($row['score'] ?? 0),
                'status' => profit_report_clean($row['status_label'] ?? $row['status'] ?? '', 80),
                'reason' => profit_report_clean($row['reason'] ?? $row['summary'] ?? '', 180),
                'next_followup' => profit_report_clean(trim((string)($row['next_followup_date'] ?? '') . ' ' . (string)($row['next_followup_time'] ?? '')), 60),
            ];
        }
        usort($items, static fn(array $a, array $b): int => (int)$b['score'] <=> (int)$a['score']);
        return array_slice($items, 0, max(1, $limit));
    }
}

if (!function_exists('profit_report_action_plan')) {
    function profit_report_action_plan(array $profitAction, array $money, array $refresh, array $leadQuality, array $cta): array
    {
        $plan = [];
        foreach ((array)($profitAction['today_plan'] ?? []) as $action) {
            if (!is_array($action)) {
                continue;
            }
            $plan[] = [
                'source' => 'Profit Action',
                'priority' => profit_report_clean($action['priority']['label'] ?? $action['priority'] ?? 'Prioritas', 40),
                'title' => profit_report_clean($action['title'] ?? 'Action profit', 160),
                'why' => profit_report_clean($action['why'] ?? $action['impact'] ?? '', 220),
                'url' => (string)($action['url'] ?? $action['href'] ?? ''),
            ];
        }
        foreach (array_slice((array)($money['items'] ?? []), 0, 3) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $page = (array)($item['item'] ?? []);
            $plan[] = [
                'source' => 'Money Page',
                'priority' => strtoupper((string)($item['priority'] ?? 'medium')),
                'title' => 'Optimasi money page: ' . profit_report_clean($page['title'] ?? 'Halaman SEO', 120),
                'why' => profit_report_clean($item['top_reason'] ?? $item['recommendation'] ?? $money['top_focus'] ?? '', 220),
                'url' => (string)($page['url'] ?? ''),
            ];
        }
        foreach (array_slice((array)($leadQuality['items'] ?? []), 0, 3) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $plan[] = [
                'source' => 'Follow-up',
                'priority' => strtoupper((string)($item['priority'] ?? 'warm')),
                'title' => 'Follow-up: ' . profit_report_clean($item['name'] ?? 'Lead prioritas', 120),
                'why' => profit_report_clean($item['reason'] ?? 'Lead ini masuk prioritas follow-up.', 220),
                'url' => function_exists('url') ? url('admin/lead-priority-scoring') : '',
            ];
        }
        foreach (array_slice((array)($refresh['items'] ?? []), 0, 2) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $plan[] = [
                'source' => 'Content Refresh',
                'priority' => strtoupper((string)($item['priority'] ?? 'medium')),
                'title' => 'Refresh konten: ' . profit_report_clean($item['title'] ?? 'Konten lama', 120),
                'why' => profit_report_clean($item['reason']['label'] ?? $refresh['top_focus'] ?? '', 220),
                'url' => (string)($item['url'] ?? ''),
            ];
        }
        foreach (array_slice((array)($cta['deployment_results'] ?? []), 0, 2) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rec = (array)($row['recommendation'] ?? []);
            $deployment = (array)($row['deployment'] ?? []);
            $plan[] = [
                'source' => 'CTA',
                'priority' => ((int)($row['result_score'] ?? 0) >= 70) ? 'SCALE' : 'OPTIMASI',
                'title' => 'Pantau CTA: ' . profit_report_clean($deployment['variant_title'] ?? $deployment['title'] ?? 'CTA Placement', 120),
                'why' => profit_report_clean($rec['label'] ?? $rec['title'] ?? 'Cek hasil CTA dari Lead Tracking.', 220),
                'url' => function_exists('url') ? url('admin/cta-result-tracker') : '',
            ];
        }
        return array_slice($plan, 0, 10);
    }
}

if (!function_exists('profit_report_compute_score')) {
    function profit_report_compute_score(array $report, array $seo, array $cta, array $lead, array $money): int
    {
        $score = 35;
        $score += min(15, (int)($report['order']['total'] ?? 0) * 3);
        $score += min(15, (int)($report['lead']['inquiries'] ?? 0) + (int)($report['lead']['events'] ?? 0));
        $score += min(12, (int)($seo['pages_with_signal'] ?? 0) * 2);
        $score += min(10, (int)($cta['deployments_with_signal'] ?? 0) * 3);
        $score += min(8, (int)($lead['counts']['hot'] ?? 0) * 2);
        $score += min(5, (int)($money['counts']['high'] ?? 0));
        if ((int)($report['order']['payment_waiting'] ?? 0) > 0) {
            $score -= min(10, (int)($report['order']['payment_waiting'] ?? 0) * 2);
        }
        if ((int)($cta['needs_action'] ?? 0) > 0) {
            $score -= min(8, (int)($cta['needs_action'] ?? 0));
        }
        return max(0, min(100, $score));
    }
}

if (!function_exists('profit_report_builder_summary')) {
    function profit_report_builder_summary(int $days = 30): array
    {
        $days = array_key_exists($days, profit_report_range_options()) ? $days : 30;
        $report = function_exists('report_dashboard_summary') ? report_dashboard_summary($days, []) : [];
        $seo = function_exists('seo_profit_summary') ? seo_profit_summary($days, 'all') : [];
        $cta = function_exists('cta_result_bridge_summary') ? cta_result_bridge_summary($days) : [];
        $lead = function_exists('lead_quality_summary') ? lead_quality_summary(max(7, $days), 'all', 'all', 'open') : [];
        $money = function_exists('seo_money_summary') ? seo_money_summary($days, 'all', 'all') : [];
        $refresh = function_exists('seo_refresh_summary') ? seo_refresh_summary(max(30, $days), 'all', 'all', 'all', 'open') : [];
        $profitAction = function_exists('profit_action_dashboard_summary') ? profit_action_dashboard_summary($days, []) : [];
        $settings = profit_report_settings(true);

        $score = profit_report_compute_score($report, $seo, $cta, $lead, $money);
        $sales = (array)($report['sales'] ?? []);
        $order = (array)($report['order'] ?? []);
        $leadMetric = (array)($report['lead'] ?? []);
        $payment = (array)($report['payment'] ?? []);
        $conversion = (array)($report['conversion'] ?? []);
        $leadCounts = (array)($lead['counts'] ?? []);

        $summaryText = 'Website sudah mulai membentuk loop growth dari SEO, CTA, lead, order, dan follow-up.';
        if ((int)($order['total'] ?? 0) <= 0 && ((int)($leadMetric['events'] ?? 0) + (int)($leadMetric['inquiries'] ?? 0)) <= 0) {
            $summaryText = 'Belum banyak sinyal lead/order pada periode ini. Fokuskan minggu ini ke pemasangan CTA, internal link, trust block, dan promosi halaman SEO utama.';
        } elseif ((int)($order['payment_waiting'] ?? 0) > 0) {
            $summaryText = 'Ada order yang masih menunggu pembayaran. Prioritas utama: follow-up payment agar peluang revenue tidak bocor.';
        } elseif ((int)($leadCounts['hot'] ?? 0) > 0) {
            $summaryText = 'Ada lead hot yang perlu segera ditindaklanjuti. Prioritas utama: follow-up cepat dan offer yang jelas.';
        } elseif ((int)($seo['pages_with_lead'] ?? 0) > 0) {
            $summaryText = 'SEO mulai berkontribusi ke lead. Perkuat money page, CTA, dan follow-up agar naik ke order.';
        }

        return [
            'generated_at' => date(DATE_ATOM),
            'days' => $days,
            'range_label' => $days . ' hari terakhir',
            'business_name' => SITE_NAME,
            'executive_score' => $score,
            'executive_summary' => $summaryText,
            'kpis' => [
                'sales_estimate' => (int)($sales['estimate'] ?? 0),
                'paid_order_value' => (int)($sales['paid_order_value'] ?? 0),
                'average_order_value' => (int)($sales['average_order_value'] ?? 0),
                'lead_events' => (int)($leadMetric['events'] ?? 0),
                'inquiries' => (int)($leadMetric['inquiries'] ?? 0),
                'orders' => (int)($order['total'] ?? 0),
                'completed_orders' => (int)($order['completed'] ?? 0),
                'waiting_payment' => (int)($order['payment_waiting'] ?? 0),
                'pending_payment_proofs' => (int)($payment['pending_proofs'] ?? 0),
                'lead_to_order_rate' => (float)($conversion['lead_to_order_rate'] ?? 0),
                'inquiry_to_order_rate' => (float)($conversion['inquiry_to_order_rate'] ?? 0),
                'seo_pages_with_signal' => (int)($seo['pages_with_signal'] ?? 0),
                'seo_pages_with_lead' => (int)($seo['pages_with_lead'] ?? 0),
                'seo_pages_with_order' => (int)($seo['pages_with_order'] ?? 0),
                'cta_clicks' => (int)($cta['total_clicks'] ?? 0),
                'cta_leads' => (int)($cta['total_leads'] ?? 0),
                'cta_orders' => (int)($cta['total_orders'] ?? 0),
                'hot_leads' => (int)($leadCounts['hot'] ?? 0),
                'warm_leads' => (int)($leadCounts['warm'] ?? 0),
                'money_pages_high' => (int)($money['counts']['high'] ?? 0),
                'content_refresh_high' => (int)($refresh['counts']['high'] ?? 0),
            ],
            'money_leaks' => [
                ['label' => 'Order menunggu pembayaran', 'value' => (int)($order['payment_waiting'] ?? 0), 'action' => 'Follow-up pembayaran dan cek reminder.'],
                ['label' => 'Bukti pembayaran menunggu review', 'value' => (int)($payment['pending_proofs'] ?? 0), 'action' => 'Review bukti pembayaran agar order bisa naik status.'],
                ['label' => 'CTA perlu perbaikan', 'value' => (int)($cta['needs_action'] ?? 0), 'action' => 'Perbaiki offer/CTA yang punya klik tapi belum jadi lead/order.'],
                ['label' => 'Money page high belum selesai', 'value' => (int)($money['counts']['high'] ?? 0), 'action' => 'Kerjakan Money Page Deployment Checklist prioritas high.'],
                ['label' => 'Lead hot/warm open', 'value' => (int)($leadCounts['hot'] ?? 0) + (int)($leadCounts['warm'] ?? 0), 'action' => 'Follow-up lead hot dan warm sebelum dingin.'],
            ],
            'top_seo_pages' => profit_report_top_seo_pages($seo, 5),
            'top_ctas' => profit_report_top_ctas($cta, 5),
            'top_leads' => profit_report_top_leads($lead, 5),
            'action_plan' => profit_report_action_plan($profitAction, $money, $refresh, $lead, $cta),
            'source_focus' => [
                'profit_action' => (string)($profitAction['today_plan'][0]['title'] ?? $profitAction['readiness']['label'] ?? 'Kerjakan prioritas profit harian.'),
                'seo' => (string)($seo['top_focus'] ?? 'Pantau halaman SEO yang punya sinyal.'),
                'cta' => (string)($cta['top_focus'] ?? 'Pantau hasil CTA dari Lead Tracking.'),
                'lead' => (string)($lead['top_focus'] ?? 'Prioritaskan lead yang paling dekat ke closing.'),
                'refresh' => (string)($refresh['top_focus'] ?? 'Refresh konten yang paling potensial.'),
            ],
            'notes' => (array)($settings['notes'] ?? []),
            'raw' => [
                'report' => $report,
                'seo_profit' => $seo,
                'cta_result' => $cta,
                'lead_quality' => $lead,
                'money_page' => $money,
                'content_refresh' => $refresh,
                'profit_action' => $profitAction,
            ],
        ];
    }
}


if (!function_exists('profit_report_csv_row')) {
    function profit_report_csv_row($handle, array $fields): void
    {
        fputcsv($handle, $fields, ',', '"', '\\', "\n");
    }
}

if (!function_exists('profit_report_export_kpi_csv')) {
    function profit_report_export_kpi_csv(array $summary): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="profit-report-builder-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        profit_report_csv_row($out, ['section', 'metric', 'value']);
        profit_report_csv_row($out, ['executive', 'score', (int)($summary['executive_score'] ?? 0)]);
        profit_report_csv_row($out, ['executive', 'summary', (string)($summary['executive_summary'] ?? '')]);
        foreach ((array)($summary['kpis'] ?? []) as $metric => $value) {
            profit_report_csv_row($out, ['kpi', (string)$metric, is_scalar($value) ? (string)$value : json_encode($value)]);
        }
        foreach ((array)($summary['money_leaks'] ?? []) as $leak) {
            profit_report_csv_row($out, ['money_leak', (string)($leak['label'] ?? ''), (string)($leak['value'] ?? 0) . ' | ' . (string)($leak['action'] ?? '')]);
        }
        foreach ((array)($summary['action_plan'] ?? []) as $action) {
            profit_report_csv_row($out, ['action_plan', (string)($action['source'] ?? ''), (string)($action['priority'] ?? '') . ' | ' . (string)($action['title'] ?? '') . ' | ' . (string)($action['why'] ?? '')]);
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('profit_report_plain_text')) {
    function profit_report_plain_text(array $summary): string
    {
        $kpi = (array)($summary['kpis'] ?? []);
        $lines = [];
        $lines[] = 'Laporan Profit Growth - ' . (string)($summary['business_name'] ?? SITE_NAME);
        $lines[] = 'Periode: ' . (string)($summary['range_label'] ?? '30 hari terakhir');
        $lines[] = 'Skor eksekutif: ' . (int)($summary['executive_score'] ?? 0) . '/100';
        $lines[] = '';
        $lines[] = 'Ringkasan:';
        $lines[] = (string)($summary['executive_summary'] ?? 'Belum ada ringkasan.');
        $lines[] = '';
        $lines[] = 'KPI utama:';
        $lines[] = '- Estimasi omzet: ' . profit_report_money((int)($kpi['sales_estimate'] ?? 0));
        $lines[] = '- Order: ' . (int)($kpi['orders'] ?? 0) . ' | Lead/event: ' . (int)($kpi['lead_events'] ?? 0) . ' | Inbox lead: ' . (int)($kpi['inquiries'] ?? 0);
        $lines[] = '- Halaman SEO dengan lead: ' . (int)($kpi['seo_pages_with_lead'] ?? 0) . ' | CTA lead: ' . (int)($kpi['cta_leads'] ?? 0) . ' | Lead hot: ' . (int)($kpi['hot_leads'] ?? 0);
        $lines[] = '';
        $lines[] = 'Money leak watch:';
        foreach ((array)($summary['money_leaks'] ?? []) as $leak) {
            $lines[] = '- ' . (string)($leak['label'] ?? '') . ': ' . (string)($leak['value'] ?? '0') . ' — ' . (string)($leak['action'] ?? '');
        }
        $lines[] = '';
        $lines[] = 'Action plan minggu ini:';
        foreach (array_slice((array)($summary['action_plan'] ?? []), 0, 7) as $action) {
            $lines[] = '- [' . (string)($action['priority'] ?? 'Prioritas') . '] ' . (string)($action['title'] ?? '') . ' — ' . (string)($action['why'] ?? '');
        }
        return implode("\n", $lines) . "\n";
    }
}
