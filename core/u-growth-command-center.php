<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| U-GROWTH COMMAND CENTER
|--------------------------------------------------------------------------
| Executive command layer that summarizes existing growth modules into one
| actionable dashboard. This module does not create a new tracking source;
| it reads Lead Tracking, SEO, CTA, report, sprint, and follow-up modules.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('ugrowth_command_clean')) {
    function ugrowth_command_clean(mixed $value, int $max = 220): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('ugrowth_command_id')) {
    function ugrowth_command_id(string $value = ''): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');
        return $value !== '' ? substr($value, 0, 150) : md5((string)microtime(true));
    }
}

if (!function_exists('ugrowth_command_storage_file')) {
    function ugrowth_command_storage_file(): string
    {
        return STORAGE_PATH . '/u-growth-command-center.json';
    }
}

if (!function_exists('ugrowth_command_range_options')) {
    function ugrowth_command_range_options(): array
    {
        return [
            7 => '7 hari terakhir',
            14 => '14 hari terakhir',
            30 => '30 hari terakhir',
            60 => '60 hari terakhir',
            90 => '90 hari terakhir',
            180 => '180 hari terakhir',
        ];
    }
}

if (!function_exists('ugrowth_command_focus_options')) {
    function ugrowth_command_focus_options(): array
    {
        return [
            'overview' => 'Overview Growth',
            'profit' => 'Profit & Money Leak',
            'seo' => 'SEO ke Profit',
            'cta' => 'CTA & Offer',
            'follow_up' => 'Lead & Follow-up',
            'execution' => 'Execution Sprint',
        ];
    }
}

if (!function_exists('ugrowth_command_default_state')) {
    function ugrowth_command_default_state(): array
    {
        return [
            'notes' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('ugrowth_command_normalize_state')) {
    function ugrowth_command_normalize_state(array $state): array
    {
        $state = array_merge(ugrowth_command_default_state(), $state);
        $notes = [];
        foreach ((array)($state['notes'] ?? []) as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = ugrowth_command_id((string)($key ?: ($item['id'] ?? '')));
            if ($id === '') {
                continue;
            }
            $notes[$id] = [
                'id' => $id,
                'title' => ugrowth_command_clean($item['title'] ?? '', 140),
                'note' => ugrowth_command_clean($item['note'] ?? '', 2000),
                'owner' => ugrowth_command_clean($item['owner'] ?? '', 80),
                'updated_at' => ugrowth_command_clean($item['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
            ];
        }
        return [
            'notes' => $notes,
            'updated_at' => ugrowth_command_clean($state['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('ugrowth_command_state')) {
    function ugrowth_command_state(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }
        $file = ugrowth_command_storage_file();
        if (!is_file($file)) {
            $cached = ugrowth_command_normalize_state(ugrowth_command_default_state());
            return $cached;
        }
        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = ugrowth_command_normalize_state(ugrowth_command_default_state());
            return $cached;
        }
        $cached = ugrowth_command_normalize_state($decoded);
        return $cached;
    }
}

if (!function_exists('ugrowth_command_write_state')) {
    function ugrowth_command_write_state(array $state, bool $throw = false): bool
    {
        $state = ugrowth_command_normalize_state($state);
        $state['updated_at'] = date(DATE_ATOM);
        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(ugrowth_command_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan U-Growth Command Center belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }
        @chmod(ugrowth_command_storage_file(), 0644);
        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'u-growth-command-center', null, 'Menyimpan catatan U-Growth Command Center.');
        }
        return true;
    }
}

if (!function_exists('ugrowth_command_save_note')) {
    function ugrowth_command_save_note(string $note, string $owner = '', string $id = 'weekly-command-note'): bool
    {
        $id = ugrowth_command_id($id);
        if ($id === '') {
            throw new RuntimeException('ID catatan tidak valid.');
        }
        $state = ugrowth_command_state(true);
        $state['notes'][$id] = [
            'id' => $id,
            'title' => 'Catatan Command Center',
            'note' => ugrowth_command_clean($note, 2000),
            'owner' => ugrowth_command_clean($owner, 80),
            'updated_at' => date(DATE_ATOM),
        ];
        return ugrowth_command_write_state($state, true);
    }
}

if (!function_exists('ugrowth_command_reset_notes')) {
    function ugrowth_command_reset_notes(): bool
    {
        $state = ugrowth_command_state(true);
        $state['notes'] = [];
        return ugrowth_command_write_state($state, true);
    }
}

if (!function_exists('ugrowth_command_safe_call')) {
    function ugrowth_command_safe_call(string $function, array $args = [], array $fallback = []): array
    {
        if (!function_exists($function)) {
            return $fallback;
        }
        try {
            $result = $function(...$args);
            return is_array($result) ? $result : $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}

if (!function_exists('ugrowth_command_value')) {
    function ugrowth_command_value(array $array, string $path, mixed $default = 0): mixed
    {
        $value = $array;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value;
    }
}

if (!function_exists('ugrowth_command_metric_int')) {
    function ugrowth_command_metric_int(array $array, string $path, int $default = 0): int
    {
        $value = ugrowth_command_value($array, $path, $default);
        return max(0, (int)$value);
    }
}

if (!function_exists('ugrowth_command_percent')) {
    function ugrowth_command_percent(mixed $value): int
    {
        return min(100, max(0, (int)round((float)$value)));
    }
}

if (!function_exists('ugrowth_command_score_meta')) {
    function ugrowth_command_score_meta(int $score): array
    {
        if ($score >= 80) {
            return ['label' => 'Siap Scale', 'tone' => 'ok', 'summary' => 'Sistem growth sudah kuat. Fokus berikutnya adalah scale halaman/CTA yang menang dan menjaga eksekusi sprint.'];
        }
        if ($score >= 60) {
            return ['label' => 'On Track', 'tone' => 'good', 'summary' => 'Fondasi growth sudah berjalan. Tetap kerjakan bottleneck paling dekat ke lead dan order.'];
        }
        if ($score >= 40) {
            return ['label' => 'Perlu Dorongan', 'tone' => 'warning', 'summary' => 'Website sudah punya sistem, tapi masih perlu eksekusi CTA, money page, content refresh, dan follow-up.'];
        }
        return ['label' => 'Bangun Momentum', 'tone' => 'critical', 'summary' => 'Mulai dari aksi yang paling dekat: pasang CTA, lengkapi trust, perkuat money page, dan follow-up lead.'];
    }
}

if (!function_exists('ugrowth_command_module_card')) {
    function ugrowth_command_module_card(array $data): array
    {
        $score = ugrowth_command_percent($data['score'] ?? 0);
        $status = $score >= 70 ? 'Sehat' : ($score >= 45 ? 'Perlu dipoles' : 'Butuh aksi');
        return [
            'id' => ugrowth_command_id((string)($data['id'] ?? $data['title'] ?? 'module')),
            'title' => ugrowth_command_clean($data['title'] ?? 'Module', 100),
            'category' => ugrowth_command_clean($data['category'] ?? 'Growth', 80),
            'score' => $score,
            'status' => ugrowth_command_clean($data['status'] ?? $status, 80),
            'summary' => ugrowth_command_clean($data['summary'] ?? '', 260),
            'primary_metric' => ugrowth_command_clean($data['primary_metric'] ?? '', 120),
            'secondary_metric' => ugrowth_command_clean($data['secondary_metric'] ?? '', 120),
            'url' => ugrowth_command_clean($data['url'] ?? '', 220),
            'cta_label' => ugrowth_command_clean($data['cta_label'] ?? 'Buka modul', 80),
            'priority' => ugrowth_command_clean($data['priority'] ?? ($score < 45 ? 'High' : 'Medium'), 30),
        ];
    }
}

if (!function_exists('ugrowth_command_action')) {
    function ugrowth_command_action(array $data): array
    {
        $source = ugrowth_command_clean($data['source'] ?? 'U-Growth', 80);
        $title = ugrowth_command_clean($data['title'] ?? 'Action', 160);
        return [
            'id' => ugrowth_command_id((string)($data['id'] ?? ($source . '-' . $title))),
            'title' => $title,
            'source' => $source,
            'priority' => ugrowth_command_clean($data['priority'] ?? 'Medium', 40),
            'why' => ugrowth_command_clean($data['why'] ?? $data['reason'] ?? '', 260),
            'impact' => ugrowth_command_clean($data['impact'] ?? '', 180),
            'cta_label' => ugrowth_command_clean($data['cta_label'] ?? 'Buka', 80),
            'cta_url' => ugrowth_command_clean($data['cta_url'] ?? $data['url'] ?? '', 220),
            'checklist' => array_values(array_filter(array_map(static fn($item): string => ugrowth_command_clean($item, 180), (array)($data['checklist'] ?? [])))),
        ];
    }
}

if (!function_exists('ugrowth_command_add_unique_action')) {
    function ugrowth_command_add_unique_action(array &$actions, array $action): void
    {
        $action = ugrowth_command_action($action);
        if ($action['title'] === '') {
            return;
        }
        $key = strtolower($action['source'] . '|' . $action['title']);
        foreach ($actions as $existing) {
            if (strtolower((string)($existing['source'] ?? '') . '|' . (string)($existing['title'] ?? '')) === $key) {
                return;
            }
        }
        $actions[] = $action;
    }
}

if (!function_exists('ugrowth_command_build_actions')) {
    function ugrowth_command_build_actions(array $ctx, string $focus = 'overview'): array
    {
        $actions = [];
        $profitAction = (array)($ctx['profit_action'] ?? []);
        foreach (array_slice((array)($profitAction['today_plan'] ?? []), 0, 6) as $item) {
            if (!is_array($item)) {
                continue;
            }
            ugrowth_command_add_unique_action($actions, [
                'id' => $item['id'] ?? '',
                'title' => $item['title'] ?? 'Aksi profit hari ini',
                'source' => 'Profit Action',
                'priority' => $item['priority_label'] ?? $item['priority'] ?? 'High',
                'why' => $item['why'] ?? $item['description'] ?? $item['target'] ?? '',
                'impact' => $item['focus_label'] ?? 'Dekat ke profit',
                'cta_label' => $item['cta_label'] ?? 'Buka action',
                'cta_url' => $item['cta_url'] ?? url('admin/profit-action-dashboard'),
                'checklist' => $item['checklist'] ?? [],
            ]);
        }

        $sprint = (array)($ctx['growth_sprint'] ?? []);
        foreach (array_slice((array)($sprint['today_tasks'] ?? []), 0, 5) as $task) {
            if (!is_array($task)) {
                continue;
            }
            ugrowth_command_add_unique_action($actions, [
                'id' => $task['id'] ?? $task['task_id'] ?? '',
                'title' => $task['title'] ?? 'Task sprint hari ini',
                'source' => 'Growth Sprint',
                'priority' => $task['priority'] ?? 'Medium',
                'why' => $task['why'] ?? 'Masuk prioritas kalender campaign.',
                'impact' => $task['objective'] ?? '',
                'cta_label' => $task['cta_label'] ?? 'Buka sprint',
                'cta_url' => $task['cta_url'] ?? url('admin/seo-campaign-calendar'),
                'checklist' => $task['checklist'] ?? [],
            ]);
        }

        $report = (array)($ctx['profit_report'] ?? []);
        foreach (array_slice((array)($report['action_plan'] ?? []), 0, 4) as $item) {
            if (is_array($item)) {
                $title = $item['title'] ?? $item['action'] ?? 'Action plan laporan CEO';
                $reason = $item['body'] ?? $item['description'] ?? $item['why'] ?? '';
            } else {
                $title = (string)$item;
                $reason = 'Turunan dari Profit Report Builder.';
            }
            ugrowth_command_add_unique_action($actions, [
                'title' => $title,
                'source' => 'Profit Report',
                'priority' => 'High',
                'why' => $reason,
                'impact' => 'Jawaban untuk owner/CEO: setelah laporan, ini action plan-nya.',
                'cta_label' => 'Buka report',
                'cta_url' => url('admin/profit-report-builder'),
            ]);
        }

        $money = (array)($ctx['seo_money'] ?? []);
        $moneyItem = (array)($money['top_item'] ?? []);
        if ($moneyItem) {
            ugrowth_command_add_unique_action($actions, [
                'title' => 'Optimasi money page: ' . ugrowth_command_clean($moneyItem['title'] ?? $moneyItem['page_title'] ?? 'halaman prioritas', 90),
                'source' => 'Money Page',
                'priority' => $moneyItem['priority'] ?? 'High',
                'why' => $money['top_focus'] ?? 'Halaman ini paling layak dipoles agar traffic punya jalur ke lead/order.',
                'impact' => 'CTA, internal link, trust block, dan offer lebih jelas.',
                'cta_label' => 'Buka optimizer',
                'cta_url' => url('admin/seo-money-page-optimizer'),
                'checklist' => ['Review CTA utama.', 'Tambahkan internal link.', 'Perkuat trust block atau FAQ.'],
            ]);
        }

        $refresh = (array)($ctx['seo_refresh'] ?? []);
        $refreshItem = (array)($refresh['top_item'] ?? []);
        if ($refreshItem) {
            ugrowth_command_add_unique_action($actions, [
                'title' => 'Refresh konten: ' . ugrowth_command_clean($refreshItem['title'] ?? $refreshItem['page_title'] ?? 'artikel lama', 90),
                'source' => 'Content Refresh',
                'priority' => $refreshItem['priority'] ?? 'Medium',
                'why' => $refresh['top_focus'] ?? 'Konten lama perlu dihidupkan lagi agar relevan dan punya CTA baru.',
                'impact' => 'Konten lama berpeluang hidup lagi dan diarahkan ke offer.',
                'cta_label' => 'Buka refresh',
                'cta_url' => url('admin/seo-content-refresh-planner'),
                'checklist' => ['Update meta title/description.', 'Tambah FAQ atau schema.', 'Sisipkan CTA dan internal link.'],
            ]);
        }

        $link = (array)($ctx['link_cta'] ?? []);
        $linkRec = (array)($link['top_recommendation'] ?? []);
        if ($linkRec) {
            ugrowth_command_add_unique_action($actions, [
                'title' => 'Pasang internal link/CTA: ' . ugrowth_command_clean($linkRec['source_title'] ?? $linkRec['page_title'] ?? $linkRec['title'] ?? 'halaman prioritas', 90),
                'source' => 'Internal Link & CTA',
                'priority' => $linkRec['priority'] ?? 'High',
                'why' => $link['top_focus'] ?? 'Ada halaman yang perlu diarahkan ke offer, form, atau katalog.',
                'impact' => 'Pembaca punya jalur yang lebih jelas ke konversi.',
                'cta_label' => 'Buka injection',
                'cta_url' => url('admin/internal-link-cta-injection'),
                'checklist' => ['Buka halaman sumber.', 'Sisipkan link natural.', 'Tambahkan CTA sesuai konteks.'],
            ]);
        }

        $lead = (array)($ctx['lead_quality'] ?? []);
        $leadItem = (array)($lead['top_item'] ?? []);
        if ($leadItem) {
            ugrowth_command_add_unique_action($actions, [
                'title' => 'Follow-up lead prioritas: ' . ugrowth_command_clean($leadItem['title'] ?? $leadItem['name'] ?? 'lead hot', 90),
                'source' => 'Lead Priority',
                'priority' => $leadItem['priority'] ?? $leadItem['temperature'] ?? 'Hot',
                'why' => $lead['top_focus'] ?? 'Lead prioritas perlu dihubungi sebelum dingin.',
                'impact' => 'Mengurangi peluang prospek lepas.',
                'cta_label' => 'Buka lead scoring',
                'cta_url' => url('admin/lead-priority-scoring'),
                'checklist' => ['Cek kontak dan kebutuhan lead.', 'Kirim follow-up singkat.', 'Catat status berikutnya di CRM.'],
            ]);
        }

        $focusSources = match ($focus) {
            'profit' => ['Profit Action', 'Profit Report', 'Money Page'],
            'seo' => ['Money Page', 'Content Refresh', 'Internal Link & CTA'],
            'cta' => ['Profit Action', 'Internal Link & CTA', 'Money Page'],
            'follow_up' => ['Lead Priority', 'Profit Action'],
            'execution' => ['Growth Sprint', 'Profit Report'],
            default => [],
        };
        if ($focusSources) {
            usort($actions, static function (array $a, array $b) use ($focusSources): int {
                $ai = in_array((string)($a['source'] ?? ''), $focusSources, true) ? 0 : 1;
                $bi = in_array((string)($b['source'] ?? ''), $focusSources, true) ? 0 : 1;
                return $ai <=> $bi;
            });
        }

        return array_slice($actions, 0, 12);
    }
}

if (!function_exists('ugrowth_command_build_modules')) {
    function ugrowth_command_build_modules(array $ctx): array
    {
        $profit = (array)($ctx['profit_action'] ?? []);
        $report = (array)($ctx['profit_report'] ?? []);
        $sprint = (array)($ctx['growth_sprint'] ?? []);
        $seoProfit = (array)($ctx['seo_profit'] ?? []);
        $journey = (array)($ctx['seo_journey'] ?? []);
        $money = (array)($ctx['seo_money'] ?? []);
        $deploy = (array)($ctx['money_deploy'] ?? []);
        $link = (array)($ctx['link_cta'] ?? []);
        $refresh = (array)($ctx['seo_refresh'] ?? []);
        $lead = (array)($ctx['lead_quality'] ?? []);
        $cta = (array)($ctx['cta_result'] ?? []);

        return [
            ugrowth_command_module_card([
                'id' => 'profit-action',
                'title' => 'Profit Action Dashboard',
                'category' => 'Daily Action',
                'score' => ugrowth_command_percent(ugrowth_command_value($profit, 'readiness.score', 0)),
                'summary' => ugrowth_command_clean($profit['today_plan'][0]['title'] ?? 'Prioritas aksi profit harian dari order, lead, CTA, SEO, dan trust signal.', 240),
                'primary_metric' => count((array)($profit['today_plan'] ?? [])) . ' prioritas hari ini',
                'secondary_metric' => (int)($profit['completed_today'] ?? 0) . ' selesai hari ini',
                'url' => url('admin/profit-action-dashboard'),
                'cta_label' => 'Buka Profit Action',
                'priority' => count((array)($profit['today_plan'] ?? [])) > 0 ? 'High' : 'Medium',
            ]),
            ugrowth_command_module_card([
                'id' => 'profit-report',
                'title' => 'Profit Report Builder',
                'category' => 'CEO Report',
                'score' => ugrowth_command_percent($report['executive_score'] ?? 0),
                'summary' => $report['executive_summary'] ?? 'Laporan owner/CEO dari revenue signal, SEO, CTA, lead, dan money leak.',
                'primary_metric' => 'Executive score ' . (int)($report['executive_score'] ?? 0) . '/100',
                'secondary_metric' => count((array)($report['action_plan'] ?? [])) . ' action plan',
                'url' => url('admin/profit-report-builder'),
                'cta_label' => 'Buka Profit Report',
            ]),
            ugrowth_command_module_card([
                'id' => 'growth-sprint',
                'title' => 'SEO Campaign Calendar',
                'category' => 'Execution',
                'score' => ugrowth_command_percent($sprint['progress'] ?? 0),
                'summary' => $sprint['summary_text'] ?? 'Kalender sprint untuk mengubah laporan menjadi action mingguan.',
                'primary_metric' => (int)($sprint['open_tasks'] ?? 0) . ' task open',
                'secondary_metric' => (int)($sprint['completed_tasks'] ?? 0) . '/' . (int)($sprint['total_tasks'] ?? 0) . ' selesai',
                'url' => url('admin/seo-campaign-calendar'),
                'cta_label' => 'Buka Sprint',
                'priority' => (int)($sprint['open_tasks'] ?? 0) > 0 ? 'High' : 'Medium',
            ]),
            ugrowth_command_module_card([
                'id' => 'seo-profit',
                'title' => 'SEO Profit Attribution',
                'category' => 'SEO to Profit',
                'score' => ugrowth_command_percent($seoProfit['attribution_score'] ?? 0),
                'summary' => $seoProfit['top_focus'] ?? 'Membaca kontribusi artikel/halaman SEO ke klik, lead, order, dan payment.',
                'primary_metric' => (int)($seoProfit['pages_with_lead'] ?? 0) . ' halaman punya lead',
                'secondary_metric' => (int)($seoProfit['needs_cta'] ?? 0) . ' butuh CTA',
                'url' => url('admin/seo-profit-attribution'),
                'cta_label' => 'Buka SEO Profit',
            ]),
            ugrowth_command_module_card([
                'id' => 'journey-map',
                'title' => 'SEO Journey Map',
                'category' => 'Journey',
                'score' => ugrowth_command_percent($journey['average_journey_score'] ?? 0),
                'summary' => $journey['top_focus'] ?? 'Peta alur SEO page → CTA click → lead → order/payment.',
                'primary_metric' => (int)($journey['total_journeys'] ?? 0) . ' journey',
                'secondary_metric' => count((array)($journey['bottlenecks'] ?? [])) . ' bottleneck',
                'url' => url('admin/seo-assisted-journey'),
                'cta_label' => 'Buka Journey',
            ]),
            ugrowth_command_module_card([
                'id' => 'money-page',
                'title' => 'SEO Money Page Optimizer',
                'category' => 'Money Page',
                'score' => ugrowth_command_percent($money['money_page_score'] ?? 0),
                'summary' => $money['top_focus'] ?? 'Rekomendasi CTA, offer, internal link, dan trust block untuk halaman potensial.',
                'primary_metric' => (int)ugrowth_command_value($money, 'counts.high', 0) . ' high priority',
                'secondary_metric' => (int)count((array)($money['items'] ?? [])) . ' halaman dibaca',
                'url' => url('admin/seo-money-page-optimizer'),
                'cta_label' => 'Buka Money Page',
            ]),
            ugrowth_command_module_card([
                'id' => 'deployment',
                'title' => 'Money Page Deployment Checklist',
                'category' => 'Deployment',
                'score' => ugrowth_command_percent($deploy['deployment_score'] ?? 0),
                'summary' => $deploy['top_focus'] ?? 'Checklist agar rekomendasi money page benar-benar dieksekusi.',
                'primary_metric' => (int)($deploy['average_progress'] ?? 0) . '% progress rata-rata',
                'secondary_metric' => count((array)($deploy['next_actions'] ?? [])) . ' next action',
                'url' => url('admin/money-page-deployment-checklist'),
                'cta_label' => 'Buka Checklist',
            ]),
            ugrowth_command_module_card([
                'id' => 'link-cta',
                'title' => 'Internal Link & CTA Injection',
                'category' => 'Injection',
                'score' => ugrowth_command_percent($link['progress_percent'] ?? 0),
                'summary' => $link['top_focus'] ?? 'Arahkan halaman SEO ke produk, landing page, form, atau offer yang tepat.',
                'primary_metric' => count((array)($link['recommendations'] ?? [])) . ' rekomendasi',
                'secondary_metric' => (int)($link['progress_percent'] ?? 0) . '% progress',
                'url' => url('admin/internal-link-cta-injection'),
                'cta_label' => 'Buka Injection',
            ]),
            ugrowth_command_module_card([
                'id' => 'content-refresh',
                'title' => 'SEO Content Refresh Planner',
                'category' => 'Content Refresh',
                'score' => ugrowth_command_percent($refresh['average_refresh_score'] ?? 0),
                'summary' => $refresh['top_focus'] ?? 'Hidupkan lagi artikel lama dengan update meta, konten, FAQ, CTA, internal link, dan offer.',
                'primary_metric' => (int)ugrowth_command_value($refresh, 'counts.high', 0) . ' high priority',
                'secondary_metric' => count((array)($refresh['items'] ?? [])) . ' item refresh',
                'url' => url('admin/seo-content-refresh-planner'),
                'cta_label' => 'Buka Refresh',
            ]),
            ugrowth_command_module_card([
                'id' => 'lead-quality',
                'title' => 'Lead Priority Scoring',
                'category' => 'Follow-up',
                'score' => ugrowth_command_percent($lead['average_lead_score'] ?? 0),
                'summary' => $lead['top_focus'] ?? 'Prioritaskan lead/order yang paling dekat ke closing.',
                'primary_metric' => (int)ugrowth_command_value($lead, 'counts.hot', 0) . ' hot lead',
                'secondary_metric' => count((array)($lead['items'] ?? [])) . ' lead/order dibaca',
                'url' => url('admin/lead-priority-scoring'),
                'cta_label' => 'Buka Lead Priority',
            ]),
            ugrowth_command_module_card([
                'id' => 'cta-result',
                'title' => 'CTA Result Tracker',
                'category' => 'CTA Result',
                'score' => ugrowth_command_percent($cta['bridge_score'] ?? 0),
                'summary' => $cta['top_focus'] ?? 'Baca hasil CTA dari Lead Tracking existing.',
                'primary_metric' => (int)($cta['total_clicks'] ?? 0) . ' klik CTA',
                'secondary_metric' => (int)($cta['total_leads'] ?? 0) . ' lead',
                'url' => url('admin/cta-result-tracker'),
                'cta_label' => 'Buka CTA Result',
            ]),
        ];
    }
}

if (!function_exists('ugrowth_command_build_bottlenecks')) {
    function ugrowth_command_build_bottlenecks(array $ctx): array
    {
        $items = [];
        $seoProfit = (array)($ctx['seo_profit'] ?? []);
        $cta = (array)($ctx['cta_result'] ?? []);
        $lead = (array)($ctx['lead_quality'] ?? []);
        $sprint = (array)($ctx['growth_sprint'] ?? []);
        $deploy = (array)($ctx['money_deploy'] ?? []);
        $refresh = (array)($ctx['seo_refresh'] ?? []);

        $items[] = [
            'stage' => 'SEO → CTA',
            'status' => ((int)($seoProfit['needs_cta'] ?? 0) > 0) ? 'Butuh CTA' : 'Pantau',
            'metric' => (int)($seoProfit['needs_cta'] ?? 0) . ' halaman butuh CTA',
            'action' => 'Pasang CTA dan internal link pada halaman SEO yang punya potensi.',
            'url' => url('admin/internal-link-cta-injection'),
        ];
        $items[] = [
            'stage' => 'CTA → Lead',
            'status' => ((int)($cta['needs_action'] ?? 0) > 0 || (int)($cta['total_clicks'] ?? 0) === 0) ? 'Perlu dipoles' : 'Pantau hasil',
            'metric' => (int)($cta['total_clicks'] ?? 0) . ' klik / ' . (int)($cta['total_leads'] ?? 0) . ' lead',
            'action' => 'Review placement CTA, offer, dan proof supaya klik lebih dekat ke lead.',
            'url' => url('admin/cta-result-tracker'),
        ];
        $items[] = [
            'stage' => 'Lead → Closing',
            'status' => ((int)ugrowth_command_value($lead, 'counts.hot', 0) > 0) ? 'Kejar hot lead' : 'Bangun sinyal',
            'metric' => (int)ugrowth_command_value($lead, 'counts.hot', 0) . ' hot lead',
            'action' => 'Follow-up lead prioritas, order pending, dan prospek yang belum direspons.',
            'url' => url('admin/lead-priority-scoring'),
        ];
        $items[] = [
            'stage' => 'Recommendation → Execution',
            'status' => ((int)($sprint['open_tasks'] ?? 0) > 0) ? 'Ada task open' : 'Rapi',
            'metric' => (int)($sprint['open_tasks'] ?? 0) . ' task sprint open',
            'action' => 'Ubah rekomendasi menjadi sprint harian dengan PIC dan deadline.',
            'url' => url('admin/seo-campaign-calendar'),
        ];
        $items[] = [
            'stage' => 'Money Page Deployment',
            'status' => ((int)($deploy['average_progress'] ?? 0) < 50) ? 'Belum banyak dieksekusi' : 'Mulai jalan',
            'metric' => (int)($deploy['average_progress'] ?? 0) . '% progress rata-rata',
            'action' => 'Kerjakan checklist deployment di halaman prioritas.',
            'url' => url('admin/money-page-deployment-checklist'),
        ];
        $items[] = [
            'stage' => 'Content Refresh',
            'status' => ((int)ugrowth_command_value($refresh, 'counts.high', 0) > 0) ? 'Refresh prioritas' : 'Pantau konten',
            'metric' => (int)ugrowth_command_value($refresh, 'counts.high', 0) . ' high priority',
            'action' => 'Hidupkan ulang artikel lama yang masih punya peluang.',
            'url' => url('admin/seo-content-refresh-planner'),
        ];

        return $items;
    }
}

if (!function_exists('ugrowth_command_center_summary')) {
    function ugrowth_command_center_summary(int $days = 30, string $focus = 'overview'): array
    {
        $rangeOptions = ugrowth_command_range_options();
        if (!isset($rangeOptions[$days])) {
            $days = 30;
        }
        $focusOptions = ugrowth_command_focus_options();
        if (!isset($focusOptions[$focus])) {
            $focus = 'overview';
        }

        $ctx = [
            'profit_action' => ugrowth_command_safe_call('profit_action_dashboard_summary', [$days, []]),
            'profit_report' => ugrowth_command_safe_call('profit_report_builder_summary', [$days]),
            'growth_sprint' => ugrowth_command_safe_call('growth_sprint_summary', [14, $days, 'balanced', 'open']),
            'seo_profit' => ugrowth_command_safe_call('seo_profit_summary', [$days, 'all']),
            'seo_journey' => ugrowth_command_safe_call('seo_journey_summary', [$days, 'all']),
            'seo_money' => ugrowth_command_safe_call('seo_money_summary', [$days, 'all', 'all']),
            'money_deploy' => ugrowth_command_safe_call('money_deploy_summary', [$days, 'all', 'all', 'all']),
            'link_cta' => ugrowth_command_safe_call('link_cta_summary', [$days, 'all', 'all', 'open']),
            'seo_refresh' => ugrowth_command_safe_call('seo_refresh_summary', [max(90, $days), 'all', 'all', 'all', 'open']),
            'lead_quality' => ugrowth_command_safe_call('lead_quality_summary', [$days, 'all', 'all', 'open']),
            'cta_result' => ugrowth_command_safe_call('cta_result_bridge_summary', [$days]),
        ];

        $modules = ugrowth_command_build_modules($ctx);
        $moduleScores = array_map(static fn(array $m): int => (int)($m['score'] ?? 0), $modules);
        $avgModules = $moduleScores ? (int)round(array_sum($moduleScores) / count($moduleScores)) : 0;
        $executiveScore = ugrowth_command_percent(ugrowth_command_value($ctx, 'profit_report.executive_score', 0));
        $sprintProgress = ugrowth_command_percent(ugrowth_command_value($ctx, 'growth_sprint.progress', 0));
        $score = (int)round(($avgModules * 0.48) + ($executiveScore * 0.32) + ($sprintProgress * 0.20));
        $score = ugrowth_command_percent($score);
        $scoreMeta = ugrowth_command_score_meta($score);

        $kpis = [
            'sales_estimate' => ugrowth_command_metric_int($ctx, 'profit_report.kpis.sales_estimate'),
            'orders' => ugrowth_command_metric_int($ctx, 'profit_report.kpis.orders'),
            'waiting_payment' => ugrowth_command_metric_int($ctx, 'profit_report.kpis.waiting_payment'),
            'cta_clicks' => ugrowth_command_metric_int($ctx, 'cta_result.total_clicks'),
            'cta_leads' => ugrowth_command_metric_int($ctx, 'cta_result.total_leads'),
            'seo_pages_with_lead' => ugrowth_command_metric_int($ctx, 'seo_profit.pages_with_lead'),
            'seo_pages_need_cta' => ugrowth_command_metric_int($ctx, 'seo_profit.needs_cta'),
            'hot_leads' => ugrowth_command_metric_int($ctx, 'lead_quality.counts.hot'),
            'sprint_progress' => ugrowth_command_metric_int($ctx, 'growth_sprint.progress'),
            'open_sprint_tasks' => ugrowth_command_metric_int($ctx, 'growth_sprint.open_tasks'),
            'money_page_score' => ugrowth_command_metric_int($ctx, 'seo_money.money_page_score'),
            'money_pages_high' => ugrowth_command_metric_int($ctx, 'seo_money.counts.high'),
            'deployment_progress' => ugrowth_command_metric_int($ctx, 'money_deploy.average_progress'),
            'internal_link_recommendations' => count((array)ugrowth_command_value($ctx, 'link_cta.recommendations', [])),
            'content_refresh_high' => ugrowth_command_metric_int($ctx, 'seo_refresh.counts.high'),
            'lead_tracking_events' => ugrowth_command_metric_int($ctx, 'seo_profit.total_raw_events'),
            'money_leaks' => count((array)ugrowth_command_value($ctx, 'profit_report.money_leaks', [])),
            'today_actions' => count((array)ugrowth_command_value($ctx, 'profit_action.today_plan', [])),
        ];

        $actions = ugrowth_command_build_actions($ctx, $focus);
        $bottlenecks = ugrowth_command_build_bottlenecks($ctx);
        $state = ugrowth_command_state();
        $note = (array)($state['notes']['weekly-command-note'] ?? []);

        $weeklyBrief = ugrowth_command_plain_text([
            'days' => $days,
            'focus_label' => $focusOptions[$focus],
            'score' => $score,
            'score_label' => $scoreMeta['label'],
            'kpis' => $kpis,
            'actions' => array_slice($actions, 0, 5),
            'bottlenecks' => array_slice($bottlenecks, 0, 4),
            'note' => (string)($note['note'] ?? ''),
        ], false);

        return [
            'generated_at' => date(DATE_ATOM),
            'days' => $days,
            'range_label' => $rangeOptions[$days],
            'focus' => $focus,
            'focus_label' => $focusOptions[$focus],
            'command_score' => $score,
            'score_label' => $scoreMeta['label'],
            'score_tone' => $scoreMeta['tone'],
            'headline' => $scoreMeta['summary'],
            'kpis' => $kpis,
            'modules' => $modules,
            'today_commands' => $actions,
            'bottlenecks' => $bottlenecks,
            'owner_brief' => $weeklyBrief,
            'note' => $note,
            'source_integrity' => [
                'tracking_source' => 'Lead Tracking existing',
                'creates_new_tracking' => false,
                'source_modules' => ['Profit Action Dashboard', 'Profit Report Builder', 'SEO Campaign Calendar', 'SEO Profit Attribution', 'SEO Journey Map', 'CTA Result Tracker', 'Money Page Optimizer', 'Content Refresh Planner', 'Lead Priority Scoring'],
            ],
            'raw' => $ctx,
        ];
    }
}

if (!function_exists('ugrowth_command_plain_text')) {
    function ugrowth_command_plain_text(array $summary, bool $full = true): string
    {
        $kpis = (array)($summary['kpis'] ?? []);
        $lines = [];
        $lines[] = 'U-Growth Command Center';
        $lines[] = 'Periode: ' . (int)($summary['days'] ?? 30) . ' hari';
        $lines[] = 'Fokus: ' . ugrowth_command_clean($summary['focus_label'] ?? 'Overview Growth', 120);
        $lines[] = 'Command Score: ' . (int)($summary['score'] ?? $summary['command_score'] ?? 0) . '/100 - ' . ugrowth_command_clean($summary['score_label'] ?? '', 80);
        $lines[] = '';
        $lines[] = 'Ringkasan KPI:';
        $lines[] = '- Estimasi omzet: ' . (function_exists('rupiah') ? rupiah((int)($kpis['sales_estimate'] ?? 0)) : 'Rp ' . number_format((int)($kpis['sales_estimate'] ?? 0), 0, ',', '.'));
        $lines[] = '- Order: ' . (int)($kpis['orders'] ?? 0) . ', tunggu bayar: ' . (int)($kpis['waiting_payment'] ?? 0);
        $lines[] = '- CTA click/lead: ' . (int)($kpis['cta_clicks'] ?? 0) . '/' . (int)($kpis['cta_leads'] ?? 0);
        $lines[] = '- SEO pages with lead: ' . (int)($kpis['seo_pages_with_lead'] ?? 0) . ', butuh CTA: ' . (int)($kpis['seo_pages_need_cta'] ?? 0);
        $lines[] = '- Hot lead: ' . (int)($kpis['hot_leads'] ?? 0) . ', task sprint open: ' . (int)($kpis['open_sprint_tasks'] ?? 0);
        $lines[] = '';
        $lines[] = 'Prioritas action:';
        foreach (array_slice((array)($summary['actions'] ?? $summary['today_commands'] ?? []), 0, 5) as $idx => $action) {
            if (!is_array($action)) {
                continue;
            }
            $lines[] = ($idx + 1) . '. ' . ugrowth_command_clean($action['title'] ?? 'Action', 160) . ' (' . ugrowth_command_clean($action['source'] ?? 'Growth', 80) . ')';
            $why = ugrowth_command_clean($action['why'] ?? '', 200);
            if ($why !== '') {
                $lines[] = '   Kenapa: ' . $why;
            }
        }
        if ($full) {
            $lines[] = '';
            $lines[] = 'Bottleneck utama:';
            foreach (array_slice((array)($summary['bottlenecks'] ?? []), 0, 6) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $lines[] = '- ' . ugrowth_command_clean($item['stage'] ?? '', 80) . ': ' . ugrowth_command_clean($item['metric'] ?? '', 120) . ' → ' . ugrowth_command_clean($item['action'] ?? '', 180);
            }
            $note = ugrowth_command_clean(ugrowth_command_value($summary, 'note.note', ''), 2000);
            if ($note !== '') {
                $lines[] = '';
                $lines[] = 'Catatan admin: ' . $note;
            }
        }
        return implode("\n", $lines) . "\n";
    }
}

if (!function_exists('ugrowth_command_export_csv')) {
    function ugrowth_command_export_csv(array $summary): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="u-growth-command-center-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }
        fputcsv($out, ['section', 'title', 'source/category', 'score/priority', 'metric/why', 'url']);
        fputcsv($out, ['summary', 'Command Score', (string)($summary['focus_label'] ?? ''), (string)($summary['command_score'] ?? 0), (string)($summary['headline'] ?? ''), '']);
        foreach ((array)($summary['today_commands'] ?? []) as $action) {
            if (!is_array($action)) {
                continue;
            }
            fputcsv($out, ['action', (string)($action['title'] ?? ''), (string)($action['source'] ?? ''), (string)($action['priority'] ?? ''), (string)($action['why'] ?? ''), (string)($action['cta_url'] ?? '')]);
        }
        foreach ((array)($summary['modules'] ?? []) as $module) {
            if (!is_array($module)) {
                continue;
            }
            fputcsv($out, ['module', (string)($module['title'] ?? ''), (string)($module['category'] ?? ''), (string)($module['score'] ?? 0), (string)($module['primary_metric'] ?? ''), (string)($module['url'] ?? '')]);
        }
        fclose($out);
        exit;
    }
}
