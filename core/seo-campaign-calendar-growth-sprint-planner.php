<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO CAMPAIGN CALENDAR & GROWTH SPRINT PLANNER
|--------------------------------------------------------------------------
| Turns Profit Report Builder, SEO, CTA, Money Page, Content Refresh,
| Internal Link, and Lead Priority signals into a practical sprint calendar.
| This module does not create a new tracking source. It reads existing data
| and helps admins turn CEO reports into weekly/monthly action plans.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('growth_sprint_clean')) {
    function growth_sprint_clean(mixed $value, int $max = 220): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('growth_sprint_id')) {
    function growth_sprint_id(string $value = ''): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');
        if ($value === '') {
            $value = md5((string)microtime(true));
        }
        return substr($value, 0, 150);
    }
}

if (!function_exists('growth_sprint_storage_file')) {
    function growth_sprint_storage_file(): string
    {
        return STORAGE_PATH . '/seo-campaign-calendar-growth-sprint.json';
    }
}

if (!function_exists('growth_sprint_duration_options')) {
    function growth_sprint_duration_options(): array
    {
        return [
            7 => '7 hari sprint cepat',
            14 => '14 hari growth sprint',
            30 => '30 hari campaign calendar',
        ];
    }
}

if (!function_exists('growth_sprint_range_options')) {
    function growth_sprint_range_options(): array
    {
        return [
            7 => 'Data 7 hari',
            14 => 'Data 14 hari',
            30 => 'Data 30 hari',
            60 => 'Data 60 hari',
            90 => 'Data 90 hari',
            180 => 'Data 180 hari',
        ];
    }
}

if (!function_exists('growth_sprint_focus_options')) {
    function growth_sprint_focus_options(): array
    {
        return [
            'balanced' => 'Balanced Growth',
            'ceo_action' => 'Turunan Laporan CEO',
            'seo_to_profit' => 'SEO ke Profit',
            'money_page' => 'Money Page',
            'content_refresh' => 'Content Refresh',
            'cta_offer' => 'CTA & Offer',
            'follow_up' => 'Follow-up Lead',
        ];
    }
}

if (!function_exists('growth_sprint_status_options')) {
    function growth_sprint_status_options(): array
    {
        return [
            'planned' => 'Masuk kalender',
            'today' => 'Dikerjakan hari ini',
            'doing' => 'Sedang dikerjakan',
            'done' => 'Selesai',
            'monitoring' => 'Pantau hasil',
            'blocked' => 'Tertahan',
            'skipped' => 'Dilewati',
        ];
    }
}

if (!function_exists('growth_sprint_filter_options')) {
    function growth_sprint_filter_options(): array
    {
        return [
            'open' => 'Belum selesai',
            'all' => 'Semua status',
            'planned' => 'Masuk kalender',
            'today' => 'Hari ini',
            'doing' => 'Sedang dikerjakan',
            'done' => 'Selesai',
            'monitoring' => 'Monitoring',
            'blocked' => 'Tertahan',
            'skipped' => 'Dilewati',
        ];
    }
}

if (!function_exists('growth_sprint_default_state')) {
    function growth_sprint_default_state(): array
    {
        return [
            'tasks' => [],
            'notes' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('growth_sprint_normalize_task_state')) {
    function growth_sprint_normalize_task_state(array $state): array
    {
        $status = (string)($state['status'] ?? 'planned');
        if (!isset(growth_sprint_status_options()[$status])) {
            $status = 'planned';
        }

        return [
            'task_id' => growth_sprint_id((string)($state['task_id'] ?? '')),
            'sprint_id' => growth_sprint_id((string)($state['sprint_id'] ?? '')),
            'status' => $status,
            'owner' => growth_sprint_clean($state['owner'] ?? '', 80),
            'due_date' => growth_sprint_clean($state['due_date'] ?? '', 80),
            'note' => growth_sprint_clean($state['note'] ?? '', 900),
            'updated_at' => growth_sprint_clean($state['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('growth_sprint_normalize_state')) {
    function growth_sprint_normalize_state(array $state): array
    {
        $state = array_merge(growth_sprint_default_state(), $state);
        $tasks = [];
        foreach ((array)($state['tasks'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $row = growth_sprint_normalize_task_state($item);
            if ($row['task_id'] === '') {
                continue;
            }
            $tasks[$row['task_id']] = $row;
        }

        $notes = [];
        foreach ((array)($state['notes'] ?? []) as $key => $note) {
            $id = growth_sprint_id((string)($key ?: ($note['id'] ?? '')));
            if ($id === '') {
                continue;
            }
            $notes[$id] = [
                'id' => $id,
                'note' => growth_sprint_clean(is_array($note) ? ($note['note'] ?? '') : $note, 1500),
                'updated_at' => growth_sprint_clean(is_array($note) ? ($note['updated_at'] ?? date(DATE_ATOM)) : date(DATE_ATOM), 80) ?: date(DATE_ATOM),
            ];
        }

        return [
            'tasks' => $tasks,
            'notes' => $notes,
            'updated_at' => growth_sprint_clean($state['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('growth_sprint_state')) {
    function growth_sprint_state(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = growth_sprint_storage_file();
        if (!is_file($file)) {
            $cached = growth_sprint_normalize_state(growth_sprint_default_state());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = growth_sprint_normalize_state(growth_sprint_default_state());
            return $cached;
        }

        $cached = growth_sprint_normalize_state($decoded);
        return $cached;
    }
}

if (!function_exists('growth_sprint_write_state')) {
    function growth_sprint_write_state(array $state, bool $throw = false): bool
    {
        $state = growth_sprint_normalize_state($state);
        $state['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(growth_sprint_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan Growth Sprint belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(growth_sprint_storage_file(), 0644);
        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'seo-campaign-calendar', null, 'Menyimpan status SEO Campaign Calendar & Growth Sprint Planner.');
        }
        return true;
    }
}

if (!function_exists('growth_sprint_update_task')) {
    function growth_sprint_update_task(string $sprintId, string $taskId, string $status, string $note = '', string $owner = '', string $dueDate = ''): bool
    {
        $sprintId = growth_sprint_id($sprintId);
        $taskId = growth_sprint_id($taskId);
        if ($sprintId === '' || $taskId === '') {
            throw new RuntimeException('ID sprint atau task tidak valid.');
        }
        if (!isset(growth_sprint_status_options()[$status])) {
            $status = 'planned';
        }
        $state = growth_sprint_state(true);
        $state['tasks'][$taskId] = growth_sprint_normalize_task_state([
            'task_id' => $taskId,
            'sprint_id' => $sprintId,
            'status' => $status,
            'note' => $note,
            'owner' => $owner,
            'due_date' => $dueDate,
            'updated_at' => date(DATE_ATOM),
        ]);
        return growth_sprint_write_state($state, true);
    }
}

if (!function_exists('growth_sprint_reset_task')) {
    function growth_sprint_reset_task(string $taskId): void
    {
        $taskId = growth_sprint_id($taskId);
        if ($taskId === '') {
            return;
        }
        $state = growth_sprint_state(true);
        unset($state['tasks'][$taskId]);
        growth_sprint_write_state($state, true);
    }
}

if (!function_exists('growth_sprint_save_note')) {
    function growth_sprint_save_note(string $sprintId, string $note): bool
    {
        $sprintId = growth_sprint_id($sprintId);
        if ($sprintId === '') {
            throw new RuntimeException('ID sprint tidak valid.');
        }
        $state = growth_sprint_state(true);
        $state['notes'][$sprintId] = [
            'id' => $sprintId,
            'note' => growth_sprint_clean($note, 1500),
            'updated_at' => date(DATE_ATOM),
        ];
        return growth_sprint_write_state($state, true);
    }
}

if (!function_exists('growth_sprint_reset_all')) {
    function growth_sprint_reset_all(): void
    {
        if (is_file(growth_sprint_storage_file())) {
            @unlink(growth_sprint_storage_file());
        }
        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'seo-campaign-calendar', null, 'Reset catatan SEO Campaign Calendar & Growth Sprint Planner.');
        }
    }
}

if (!function_exists('growth_sprint_priority_weight')) {
    function growth_sprint_priority_weight(string $priority): int
    {
        $value = strtolower($priority);
        return match ($value) {
            'high', 'hot', 'scale', 'urgent', 'prioritas tinggi' => 100,
            'medium', 'warm', 'optimasi', 'normal' => 70,
            'low', 'watch', 'buffer' => 35,
            default => 55,
        };
    }
}

if (!function_exists('growth_sprint_task')) {
    function growth_sprint_task(array $data): array
    {
        $day = max(1, (int)($data['day'] ?? 1));
        $source = growth_sprint_clean($data['source'] ?? 'Growth Sprint', 70);
        $title = growth_sprint_clean($data['title'] ?? 'Growth action', 160);
        $target = growth_sprint_clean($data['target_title'] ?? '', 160);
        $rawId = (string)($data['id'] ?? ($source . '-' . $title . '-' . $target . '-' . $day));

        return [
            'id' => growth_sprint_id($rawId),
            'day' => $day,
            'week' => (int)ceil($day / 7),
            'date_hint' => date('d M', strtotime('+' . ($day - 1) . ' days')),
            'source' => $source,
            'focus' => growth_sprint_clean($data['focus'] ?? 'balanced', 50),
            'priority' => growth_sprint_clean($data['priority'] ?? 'Medium', 40),
            'weight' => growth_sprint_priority_weight((string)($data['priority'] ?? 'medium')),
            'title' => $title,
            'objective' => growth_sprint_clean($data['objective'] ?? '', 260),
            'why' => growth_sprint_clean($data['why'] ?? '', 320),
            'checklist' => array_values(array_filter(array_map(static fn($v): string => growth_sprint_clean($v, 180), (array)($data['checklist'] ?? [])))) ?: ['Kerjakan action ini.', 'Catat hasil atau kendalanya.', 'Pantau hasil lewat dashboard terkait.'],
            'kpi' => growth_sprint_clean($data['kpi'] ?? 'Ada progres yang bisa dipantau.', 180),
            'target_title' => $target,
            'target_url' => (string)($data['target_url'] ?? ''),
            'cta_label' => growth_sprint_clean($data['cta_label'] ?? 'Buka Menu', 80),
            'cta_url' => (string)($data['cta_url'] ?? ''),
            'owner_hint' => growth_sprint_clean($data['owner_hint'] ?? 'Admin marketing/owner', 80),
        ];
    }
}

if (!function_exists('growth_sprint_take_items')) {
    function growth_sprint_take_items(array $items, int $limit): array
    {
        return array_slice(array_values(array_filter($items, static fn($row): bool => is_array($row))), 0, max(0, $limit));
    }
}

if (!function_exists('growth_sprint_base_tasks')) {
    function growth_sprint_base_tasks(int $duration, array $report): array
    {
        $kpis = (array)($report['kpis'] ?? []);
        $tasks = [
            growth_sprint_task([
                'day' => 1,
                'source' => 'CEO Report',
                'focus' => 'ceo_action',
                'priority' => 'High',
                'title' => 'Review laporan CEO dan pilih 3 prioritas utama',
                'objective' => 'Ubah laporan profit menjadi daftar kerja yang jelas sebelum tim mulai eksekusi.',
                'why' => 'Owner/CEO biasanya akan bertanya “terus action-nya apa?”. Task ini membuat admin sudah siap dengan jawaban dan prioritas.',
                'checklist' => ['Buka Profit Report Builder.', 'Pilih 3 action paling berdampak minggu ini.', 'Tentukan PIC dan deadline singkat.'],
                'kpi' => '3 prioritas sprint sudah jelas.',
                'cta_label' => 'Buka Profit Report',
                'cta_url' => function_exists('url') ? url('admin/profit-report-builder') : '',
            ]),
            growth_sprint_task([
                'day' => 2,
                'source' => 'SEO Profit',
                'focus' => 'seo_to_profit',
                'priority' => 'High',
                'title' => 'Pilih halaman SEO yang paling dekat ke lead/order',
                'objective' => 'Pastikan effort SEO diarahkan ke halaman yang punya potensi bisnis, bukan cuma ramai dibaca.',
                'why' => 'Halaman yang punya sinyal klik/lead harus diberi CTA, internal link, trust, dan offer yang lebih kuat.',
                'checklist' => ['Buka SEO Profit Attribution.', 'Pilih halaman dengan klik/lead/order terbaik.', 'Masukkan halaman itu ke money page/action sprint.'],
                'kpi' => 'Minimal 1 halaman SEO prioritas dipilih.',
                'cta_label' => 'Buka SEO Profit',
                'cta_url' => function_exists('url') ? url('admin/seo-profit-attribution') : '',
            ]),
            growth_sprint_task([
                'day' => 3,
                'source' => 'CTA & Link',
                'focus' => 'cta_offer',
                'priority' => 'Medium',
                'title' => 'Pasang CTA dan internal link pada halaman prioritas',
                'objective' => 'Membuat pembaca punya jalur jelas menuju chat, form, katalog, landing page, atau checkout.',
                'why' => 'Traffic SEO tanpa CTA yang jelas sering berhenti jadi pembaca saja, belum menjadi lead.',
                'checklist' => ['Buka Internal Link & CTA Injection.', 'Pilih snippet yang relevan.', 'Pasang link/CTA di posisi atas, tengah, atau bawah konten.'],
                'kpi' => 'Minimal 1 CTA/internal link terpasang.',
                'cta_label' => 'Buka Injection',
                'cta_url' => function_exists('url') ? url('admin/internal-link-cta-injection') : '',
            ]),
            growth_sprint_task([
                'day' => 4,
                'source' => 'Follow-up',
                'focus' => 'follow_up',
                'priority' => ((int)($kpis['hot_leads'] ?? 0) > 0 || (int)($kpis['waiting_payment'] ?? 0) > 0) ? 'High' : 'Medium',
                'title' => 'Follow-up lead hot, warm, dan order pending',
                'objective' => 'Mencegah peluang revenue bocor karena lead/order tidak ditindaklanjuti.',
                'why' => 'Lead panas cepat dingin. Order pending dan bukti pembayaran yang belum dicek bisa langsung berdampak ke omzet.',
                'checklist' => ['Buka Lead Priority Scoring.', 'Hubungi lead hot/warm.', 'Cek order menunggu pembayaran dan bukti transfer.'],
                'kpi' => 'Lead prioritas punya status/catatan follow-up.',
                'cta_label' => 'Buka Lead Priority',
                'cta_url' => function_exists('url') ? url('admin/lead-priority-scoring') : '',
            ]),
            growth_sprint_task([
                'day' => 5,
                'source' => 'Content Refresh',
                'focus' => 'content_refresh',
                'priority' => 'Medium',
                'title' => 'Refresh konten lama yang masih punya peluang',
                'objective' => 'Menghidupkan kembali artikel/halaman lama agar tetap relevan dan lebih dekat ke lead/order.',
                'why' => 'Konten lama bisa turun performa atau kehilangan relevansi. Refresh membantu menjaga momentum SEO.',
                'checklist' => ['Buka SEO Content Refresh Planner.', 'Update meta, konten, FAQ, CTA, dan internal link.', 'Ubah status ke monitoring setelah publish.'],
                'kpi' => 'Minimal 1 konten refresh masuk progress.',
                'cta_label' => 'Buka Refresh Planner',
                'cta_url' => function_exists('url') ? url('admin/seo-content-refresh-planner') : '',
            ]),
            growth_sprint_task([
                'day' => 6,
                'source' => 'Offer Lab',
                'focus' => 'cta_offer',
                'priority' => 'Medium',
                'title' => 'Review offer dan CTA yang sedang dipakai',
                'objective' => 'Pastikan tombol, headline, dan proof yang dipasang masih relevan dengan target bisnis.',
                'why' => 'Offer yang kurang kuat bisa membuat klik tidak lanjut menjadi lead/order.',
                'checklist' => ['Buka Offer & CTA Testing Lab.', 'Bandingkan varian offer.', 'Pilih atau tandai winner yang siap dipasang.'],
                'kpi' => 'Minimal 1 CTA/offer punya keputusan lanjut.',
                'cta_label' => 'Buka Offer Lab',
                'cta_url' => function_exists('url') ? url('admin/offer-cta-testing') : '',
            ]),
            growth_sprint_task([
                'day' => 7,
                'source' => 'Monitoring',
                'focus' => 'ceo_action',
                'priority' => 'Medium',
                'title' => 'Review hasil sprint dan siapkan update singkat',
                'objective' => 'Menutup minggu dengan data, catatan tindakan, dan rencana next step.',
                'why' => 'Growth sprint harus punya ritme review agar keputusan berikutnya tidak asal tebak.',
                'checklist' => ['Cek CTA Result Tracker.', 'Cek SEO Journey Map.', 'Copy ringkasan untuk owner/CEO.'],
                'kpi' => 'Ada update mingguan yang bisa dilaporkan.',
                'cta_label' => 'Buka CTA Result',
                'cta_url' => function_exists('url') ? url('admin/cta-result-tracker') : '',
            ]),
        ];

        if ($duration >= 14) {
            $tasks[] = growth_sprint_task([
                'day' => 8,
                'source' => 'Money Page',
                'focus' => 'money_page',
                'priority' => 'High',
                'title' => 'Eksekusi Money Page Deployment Checklist',
                'objective' => 'Mengubah rekomendasi money page menjadi perubahan nyata di konten.',
                'why' => 'Money page high priority harus benar-benar dipasang CTA, internal link, trust, dan offer-nya.',
                'checklist' => ['Buka Money Page Deployment Checklist.', 'Kerjakan task halaman prioritas.', 'Ubah status task yang selesai.'],
                'kpi' => 'Minimal 1 money page selesai checklist utama.',
                'cta_label' => 'Buka Deployment',
                'cta_url' => function_exists('url') ? url('admin/money-page-deployment-checklist') : '',
            ]);
            $tasks[] = growth_sprint_task([
                'day' => 10,
                'source' => 'Trust Block',
                'focus' => 'cta_offer',
                'priority' => 'Medium',
                'title' => 'Perkuat trust block pada halaman/beranda',
                'objective' => 'Menjawab keraguan calon pembeli dengan FAQ, testimoni, benefit, badge trust, atau garansi.',
                'why' => 'Pengunjung yang sudah tertarik sering butuh bukti percaya sebelum klik CTA atau order.',
                'checklist' => ['Buka Trust & Conversion Block.', 'Aktifkan FAQ/testimoni/garansi yang relevan.', 'Pastikan CTA diarahkan ke action utama.'],
                'kpi' => 'Trust block penting aktif dan relevan.',
                'cta_label' => 'Buka Trust Block',
                'cta_url' => function_exists('url') ? url('admin/trust-conversion') : '',
            ]);
            $tasks[] = growth_sprint_task([
                'day' => 14,
                'source' => 'CEO Report',
                'focus' => 'ceo_action',
                'priority' => 'High',
                'title' => 'Buat laporan sprint dan rencana 2 minggu berikutnya',
                'objective' => 'Menyampaikan hasil kerja, hambatan, dan action plan berikutnya ke owner/CEO.',
                'why' => 'Laporan yang bagus harus menjawab progress dan next step, bukan hanya angka.',
                'checklist' => ['Buka Profit Report Builder.', 'Copy executive summary.', 'Tambahkan 3 action plan berikutnya.'],
                'kpi' => 'Laporan sprint siap dikirim ke owner/CEO.',
                'cta_label' => 'Buka Profit Report',
                'cta_url' => function_exists('url') ? url('admin/profit-report-builder') : '',
            ]);
        }

        if ($duration >= 30) {
            foreach ([15, 22, 29] as $day) {
                $tasks[] = growth_sprint_task([
                    'day' => $day,
                    'source' => 'Weekly Review',
                    'focus' => 'ceo_action',
                    'priority' => 'Medium',
                    'title' => 'Review mingguan: hasil, bottleneck, dan next action',
                    'objective' => 'Menjaga campaign 30 hari tetap berbasis data dan tidak melebar ke terlalu banyak pekerjaan.',
                    'why' => 'Review mingguan membantu admin tahu apa yang perlu di-scale, ditahan, atau diperbaiki.',
                    'checklist' => ['Cek Profit Action Dashboard.', 'Cek SEO Journey dan CTA Result.', 'Update status task sprint.'],
                    'kpi' => 'Keputusan minggu berikutnya sudah jelas.',
                    'cta_label' => 'Buka Profit Action',
                    'cta_url' => function_exists('url') ? url('admin/profit-action-dashboard') : '',
                ]);
            }
        }

        return $tasks;
    }
}

if (!function_exists('growth_sprint_dynamic_tasks')) {
    function growth_sprint_dynamic_tasks(int $duration, int $days, array $report, array $money, array $refresh, array $lead, array $cta, array $link): array
    {
        $tasks = [];
        $slot = 2;

        foreach (growth_sprint_take_items((array)($report['action_plan'] ?? []), 4) as $action) {
            $source = growth_sprint_clean($action['source'] ?? 'Report Action', 80);
            $focus = match (strtolower($source)) {
                'follow-up', 'follow up' => 'follow_up',
                'money page' => 'money_page',
                'content refresh' => 'content_refresh',
                'cta' => 'cta_offer',
                default => 'ceo_action',
            };
            $tasks[] = growth_sprint_task([
                'day' => min($duration, $slot++),
                'source' => 'Profit Report',
                'focus' => $focus,
                'priority' => (string)($action['priority'] ?? 'High'),
                'title' => growth_sprint_clean($action['title'] ?? 'Action dari laporan', 160),
                'objective' => 'Jalankan action yang muncul dari laporan owner/CEO.',
                'why' => growth_sprint_clean($action['why'] ?? 'Action ini diprioritaskan dari sinyal data existing.', 260),
                'checklist' => ['Buka sumber action.', 'Kerjakan perubahan yang diperlukan.', 'Catat progress di sprint calendar.'],
                'kpi' => 'Action laporan tidak berhenti sebagai insight.',
                'target_url' => (string)($action['url'] ?? ''),
                'cta_label' => 'Buka Action',
                'cta_url' => (string)($action['url'] ?? (function_exists('url') ? url('admin/profit-report-builder') : '')),
            ]);
        }

        foreach (growth_sprint_take_items((array)($money['items'] ?? []), 5) as $item) {
            $page = (array)($item['item'] ?? []);
            $priority = (string)($item['priority'] ?? 'medium');
            $tasks[] = growth_sprint_task([
                'day' => min($duration, $slot++),
                'source' => 'Money Page',
                'focus' => 'money_page',
                'priority' => $priority,
                'title' => 'Optimasi money page: ' . growth_sprint_clean($page['title'] ?? 'Halaman SEO', 110),
                'objective' => 'Ubah halaman SEO potensial menjadi jalur lead/order yang lebih jelas.',
                'why' => growth_sprint_clean($item['top_reason'] ?? $item['recommendation'] ?? 'Halaman ini muncul dari Money Page Optimizer.', 260),
                'checklist' => ['Cek rekomendasi CTA dan trust.', 'Tambahkan internal link relevan.', 'Pantau hasil di SEO Journey Map.'],
                'kpi' => 'Halaman punya CTA/internal link/trust yang lebih kuat.',
                'target_title' => growth_sprint_clean($page['title'] ?? '', 140),
                'target_url' => (string)($page['url'] ?? ''),
                'cta_label' => 'Buka Money Page',
                'cta_url' => function_exists('url') ? url('admin/seo-money-page-optimizer') : '',
            ]);
        }

        foreach (growth_sprint_take_items((array)($refresh['items'] ?? []), 4) as $item) {
            $tasks[] = growth_sprint_task([
                'day' => min($duration, $slot++),
                'source' => 'Content Refresh',
                'focus' => 'content_refresh',
                'priority' => (string)($item['priority'] ?? 'medium'),
                'title' => 'Refresh konten: ' . growth_sprint_clean($item['title'] ?? 'Konten lama', 120),
                'objective' => 'Menghidupkan ulang konten agar tetap relevan dan lebih siap menghasilkan lead.',
                'why' => growth_sprint_clean($item['reason']['note'] ?? $item['reason']['label'] ?? 'Konten ini masuk antrean refresh dari data existing.', 260),
                'checklist' => ['Update meta/title.', 'Tambah CTA dan internal link.', 'Tambahkan FAQ atau trust bila relevan.'],
                'kpi' => 'Konten refresh publish atau masuk monitoring.',
                'target_title' => growth_sprint_clean($item['title'] ?? '', 140),
                'target_url' => (string)($item['url'] ?? ''),
                'cta_label' => 'Buka Refresh',
                'cta_url' => function_exists('url') ? url('admin/seo-content-refresh-planner') : '',
            ]);
        }

        foreach (growth_sprint_take_items((array)($lead['items'] ?? []), 5) as $item) {
            $tasks[] = growth_sprint_task([
                'day' => min($duration, $slot++),
                'source' => 'Lead Follow-up',
                'focus' => 'follow_up',
                'priority' => (string)($item['priority'] ?? 'warm'),
                'title' => 'Follow-up lead: ' . growth_sprint_clean($item['name'] ?? 'Lead prioritas', 110),
                'objective' => 'Menangani lead/order yang paling dekat ke closing.',
                'why' => growth_sprint_clean($item['reason'] ?? 'Lead ini masuk prioritas follow-up.', 260),
                'checklist' => ['Cek kontak dan konteks lead.', 'Kirim follow-up WA/email.', 'Catat status dan next follow-up.'],
                'kpi' => 'Lead punya catatan follow-up terbaru.',
                'cta_label' => 'Buka Lead Priority',
                'cta_url' => function_exists('url') ? url('admin/lead-priority-scoring') : '',
            ]);
        }

        foreach (growth_sprint_take_items((array)($cta['deployment_results'] ?? []), 4) as $row) {
            $deployment = (array)($row['deployment'] ?? []);
            $metrics = (array)($row['metrics'] ?? []);
            $needsFix = ((int)($metrics['clicks'] ?? 0) > 0 && (int)($metrics['leads'] ?? 0) <= 0) || (int)($row['result_score'] ?? 0) < 60;
            $tasks[] = growth_sprint_task([
                'day' => min($duration, $slot++),
                'source' => 'CTA Result',
                'focus' => 'cta_offer',
                'priority' => $needsFix ? 'High' : 'Medium',
                'title' => ($needsFix ? 'Perbaiki CTA: ' : 'Scale/Pantau CTA: ') . growth_sprint_clean($deployment['variant_title'] ?? $deployment['title'] ?? 'CTA Placement', 100),
                'objective' => 'Mengambil keputusan dari hasil CTA yang sudah terbaca.',
                'why' => growth_sprint_clean($row['recommendation']['label'] ?? $row['recommendation']['title'] ?? 'CTA ini punya sinyal yang perlu dipantau.', 260),
                'checklist' => ['Cek klik, lead, dan order.', 'Perbaiki offer/label CTA bila perlu.', 'Catat keputusan lanjut.'],
                'kpi' => 'CTA punya keputusan: scale, perbaiki, ganti, atau pantau.',
                'cta_label' => 'Buka CTA Result',
                'cta_url' => function_exists('url') ? url('admin/cta-result-tracker') : '',
            ]);
        }

        foreach (growth_sprint_take_items((array)($link['items'] ?? []), 4) as $item) {
            $tasks[] = growth_sprint_task([
                'day' => min($duration, $slot++),
                'source' => 'Internal Link',
                'focus' => 'seo_to_profit',
                'priority' => (string)($item['priority'] ?? 'medium'),
                'title' => 'Sisipkan link/CTA: ' . growth_sprint_clean($item['source_title'] ?? $item['title'] ?? 'Halaman prioritas', 110),
                'objective' => 'Menghubungkan halaman SEO ke jalur penjualan yang relevan.',
                'why' => growth_sprint_clean($item['why'] ?? $item['reason'] ?? 'Rekomendasi ini muncul dari Internal Link & CTA Injection.', 260),
                'checklist' => ['Buka halaman sumber.', 'Sisipkan internal link atau CTA.', 'Ubah status injection menjadi sudah dipasang.'],
                'kpi' => 'Internal link/CTA terpasang pada halaman sumber.',
                'cta_label' => 'Buka Injection',
                'cta_url' => function_exists('url') ? url('admin/internal-link-cta-injection') : '',
            ]);
        }

        return $tasks;
    }
}

if (!function_exists('growth_sprint_apply_state')) {
    function growth_sprint_apply_state(array $tasks, string $sprintId): array
    {
        $states = (array)(growth_sprint_state(true)['tasks'] ?? []);
        foreach ($tasks as &$task) {
            $taskId = (string)($task['id'] ?? '');
            $state = (array)($states[$taskId] ?? []);
            $status = (string)($state['status'] ?? 'planned');
            $task['sprint_id'] = $sprintId;
            $task['status'] = $status;
            $task['status_label'] = growth_sprint_status_options()[$status] ?? 'Masuk kalender';
            $task['owner'] = (string)($state['owner'] ?? '');
            $task['due_date'] = (string)($state['due_date'] ?? '');
            $task['note'] = (string)($state['note'] ?? '');
            $task['updated_at'] = (string)($state['updated_at'] ?? '');
            $task['is_done'] = in_array($status, ['done', 'monitoring', 'skipped'], true);
        }
        unset($task);
        return $tasks;
    }
}

if (!function_exists('growth_sprint_status_matches')) {
    function growth_sprint_status_matches(string $status, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }
        if ($filter === 'open') {
            return !in_array($status, ['done', 'monitoring', 'skipped'], true);
        }
        return $status === $filter;
    }
}

if (!function_exists('growth_sprint_focus_matches')) {
    function growth_sprint_focus_matches(string $taskFocus, string $filter): bool
    {
        return $filter === 'balanced' || $taskFocus === $filter;
    }
}

if (!function_exists('growth_sprint_dedupe_sort')) {
    function growth_sprint_dedupe_sort(array $tasks, int $duration): array
    {
        $seen = [];
        $items = [];
        foreach ($tasks as $task) {
            if (!is_array($task)) {
                continue;
            }
            $id = (string)($task['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $task['day'] = max(1, min($duration, (int)($task['day'] ?? 1)));
            $task['week'] = (int)ceil((int)$task['day'] / 7);
            $items[] = $task;
        }
        usort($items, static function (array $a, array $b): int {
            $aw = (int)($a['weight'] ?? 0);
            $bw = (int)($b['weight'] ?? 0);
            if ((int)($a['day'] ?? 0) === (int)($b['day'] ?? 0)) {
                return $bw <=> $aw;
            }
            return (int)($a['day'] ?? 0) <=> (int)($b['day'] ?? 0);
        });
        return $items;
    }
}

if (!function_exists('growth_sprint_calendar')) {
    function growth_sprint_calendar(array $tasks, int $duration): array
    {
        $daily = [];
        for ($day = 1; $day <= $duration; $day++) {
            $dayTasks = array_values(array_filter($tasks, static fn(array $task): bool => (int)($task['day'] ?? 0) === $day));
            $daily[] = [
                'day' => $day,
                'date_hint' => date('d M', strtotime('+' . ($day - 1) . ' days')),
                'week' => (int)ceil($day / 7),
                'tasks' => $dayTasks,
                'total' => count($dayTasks),
                'completed' => count(array_filter($dayTasks, static fn(array $task): bool => !empty($task['is_done']))),
            ];
        }
        return $daily;
    }
}

if (!function_exists('growth_sprint_weekly')) {
    function growth_sprint_weekly(array $daily): array
    {
        $weeks = [];
        foreach ($daily as $day) {
            $week = (int)($day['week'] ?? 1);
            $weeks[$week] ??= ['week' => $week, 'total' => 0, 'completed' => 0, 'days' => []];
            $weeks[$week]['total'] += (int)($day['total'] ?? 0);
            $weeks[$week]['completed'] += (int)($day['completed'] ?? 0);
            $weeks[$week]['days'][] = $day;
        }
        foreach ($weeks as &$week) {
            $week['progress'] = (int)round(((int)$week['completed'] / max(1, (int)$week['total'])) * 100);
            $week['label'] = 'Minggu ' . (int)$week['week'];
        }
        unset($week);
        return array_values($weeks);
    }
}

if (!function_exists('growth_sprint_summary')) {
    function growth_sprint_summary(int $duration = 14, int $days = 30, string $focus = 'balanced', string $status = 'open'): array
    {
        $duration = isset(growth_sprint_duration_options()[$duration]) ? $duration : 14;
        $days = isset(growth_sprint_range_options()[$days]) ? $days : 30;
        $focus = isset(growth_sprint_focus_options()[$focus]) ? $focus : 'balanced';
        $status = isset(growth_sprint_filter_options()[$status]) ? $status : 'open';

        $report = function_exists('profit_report_builder_summary') ? profit_report_builder_summary($days) : [];
        $money = function_exists('seo_money_summary') ? seo_money_summary($days, 'all', 'all') : [];
        $refresh = function_exists('seo_refresh_summary') ? seo_refresh_summary(max(30, $days), 'all', 'all', 'all', 'open') : [];
        $lead = function_exists('lead_quality_summary') ? lead_quality_summary(max(7, $days), 'all', 'all', 'open') : [];
        $cta = function_exists('cta_result_bridge_summary') ? cta_result_bridge_summary($days) : [];
        $link = function_exists('link_cta_summary') ? link_cta_summary($days, 'all', 'all', 'open') : [];
        $playbook = function_exists('profit_playbook_campaign_summary') ? profit_playbook_campaign_summary($duration, 'seo_to_sales', $days, []) : [];

        $sprintId = growth_sprint_id('growth-sprint-' . $duration . '-' . $days . '-' . $focus . '-' . date('Y-m'));
        $tasks = array_merge(
            growth_sprint_base_tasks($duration, $report),
            growth_sprint_dynamic_tasks($duration, $days, $report, $money, $refresh, $lead, $cta, $link)
        );
        $tasks = growth_sprint_dedupe_sort($tasks, $duration);
        $tasks = growth_sprint_apply_state($tasks, $sprintId);

        $allTasks = $tasks;
        $tasks = array_values(array_filter($tasks, static function (array $task) use ($focus, $status): bool {
            return growth_sprint_focus_matches((string)($task['focus'] ?? 'balanced'), $focus)
                && growth_sprint_status_matches((string)($task['status'] ?? 'planned'), $status);
        }));

        $daily = growth_sprint_calendar($tasks, $duration);
        $weekly = growth_sprint_weekly($daily);
        $total = count($allTasks);
        $completed = count(array_filter($allTasks, static fn(array $task): bool => !empty($task['is_done'])));
        $open = $total - $completed;
        $today = min($duration, max(1, (int)date('j') % $duration ?: 1));
        $todayTasks = array_values(array_filter($allTasks, static fn(array $task): bool => (int)($task['day'] ?? 0) === $today && empty($task['is_done'])));
        if (!$todayTasks) {
            $todayTasks = array_slice(array_values(array_filter($allTasks, static fn(array $task): bool => empty($task['is_done']))), 0, 3);
        }

        $state = growth_sprint_state(true);
        $note = (string)($state['notes'][$sprintId]['note'] ?? '');
        $kpis = (array)($report['kpis'] ?? []);

        $focusText = 'Balanced Growth: kombinasikan SEO, CTA, follow-up, money page, refresh konten, dan laporan owner.';
        if ($focus !== 'balanced') {
            $focusText = 'Mode fokus: ' . (growth_sprint_focus_options()[$focus] ?? $focus) . '. Task difilter agar admin bisa fokus pada jalur kerja tertentu.';
        }

        return [
            'generated_at' => date(DATE_ATOM),
            'sprint_id' => $sprintId,
            'duration' => $duration,
            'duration_label' => growth_sprint_duration_options()[$duration] ?? ($duration . ' hari'),
            'days' => $days,
            'range_label' => growth_sprint_range_options()[$days] ?? ($days . ' hari'),
            'focus' => $focus,
            'focus_label' => growth_sprint_focus_options()[$focus] ?? 'Balanced Growth',
            'status_filter' => $status,
            'status_label' => growth_sprint_filter_options()[$status] ?? 'Belum selesai',
            'summary_text' => $focusText,
            'progress' => (int)round(($completed / max(1, $total)) * 100),
            'total_tasks' => $total,
            'completed_tasks' => $completed,
            'open_tasks' => $open,
            'tasks' => $tasks,
            'all_tasks' => $allTasks,
            'today_tasks' => $todayTasks,
            'daily' => $daily,
            'weekly' => $weekly,
            'note' => $note,
            'source_kpis' => [
                'executive_score' => (int)($report['executive_score'] ?? 0),
                'sales_estimate' => (int)($kpis['sales_estimate'] ?? 0),
                'orders' => (int)($kpis['orders'] ?? 0),
                'waiting_payment' => (int)($kpis['waiting_payment'] ?? 0),
                'lead_events' => (int)($kpis['lead_events'] ?? 0),
                'inquiries' => (int)($kpis['inquiries'] ?? 0),
                'seo_pages_with_lead' => (int)($kpis['seo_pages_with_lead'] ?? 0),
                'cta_clicks' => (int)($kpis['cta_clicks'] ?? 0),
                'hot_leads' => (int)($kpis['hot_leads'] ?? 0),
                'money_pages_high' => (int)($kpis['money_pages_high'] ?? 0),
                'content_refresh_high' => (int)($kpis['content_refresh_high'] ?? 0),
                'playbook_progress' => (int)($playbook['progress'] ?? 0),
            ],
            'source_modules' => [
                'Profit Report Builder',
                'SEO Profit Attribution',
                'SEO Money Page Optimizer',
                'Money Page Deployment Checklist',
                'Internal Link & CTA Injection',
                'SEO Content Refresh Planner',
                'CTA Result Tracker',
                'Lead Priority Scoring',
                'Profit Playbook',
            ],
            'recommendations' => [
                $open > 0 ? 'Kerjakan task high priority terlebih dulu sebelum menambah campaign baru.' : 'Sprint terlihat rapi. Masuk ke fase monitoring dan laporan owner.',
                ((int)($kpis['waiting_payment'] ?? 0) > 0) ? 'Ada order menunggu pembayaran. Sisipkan follow-up payment ke agenda hari ini.' : 'Tidak ada sinyal payment waiting besar pada laporan ini.',
                ((int)($kpis['money_pages_high'] ?? 0) > 0) ? 'Money page high masih perlu dieksekusi agar traffic SEO lebih dekat ke lead/order.' : 'Money page high relatif aman, fokuskan pada monitoring dan scale.',
            ],
        ];
    }
}

if (!function_exists('growth_sprint_plain_text')) {
    function growth_sprint_plain_text(array $summary): string
    {
        $lines = [];
        $lines[] = 'SEO Campaign Calendar & Growth Sprint';
        $lines[] = 'Periode data: ' . (string)($summary['range_label'] ?? '-');
        $lines[] = 'Durasi sprint: ' . (string)($summary['duration_label'] ?? '-');
        $lines[] = 'Progress: ' . (int)($summary['progress'] ?? 0) . '% (' . (int)($summary['completed_tasks'] ?? 0) . '/' . (int)($summary['total_tasks'] ?? 0) . ' task)';
        $lines[] = '';
        $lines[] = 'Prioritas hari ini:';
        foreach (array_slice((array)($summary['today_tasks'] ?? []), 0, 5) as $idx => $task) {
            $lines[] = ($idx + 1) . '. [' . (string)($task['priority'] ?? '-') . '] ' . (string)($task['title'] ?? 'Task');
            $lines[] = '   Kenapa: ' . (string)($task['why'] ?? '-');
            $lines[] = '   KPI: ' . (string)($task['kpi'] ?? '-');
        }
        $lines[] = '';
        $lines[] = 'Action plan sprint:';
        foreach (array_slice((array)($summary['all_tasks'] ?? []), 0, 18) as $task) {
            $lines[] = 'Hari ' . (int)($task['day'] ?? 0) . ' - ' . (string)($task['source'] ?? 'Action') . ': ' . (string)($task['title'] ?? 'Task') . ' (' . (string)($task['status_label'] ?? 'Masuk kalender') . ')';
        }
        return implode("\n", $lines) . "\n";
    }
}

if (!function_exists('growth_sprint_export_csv')) {
    function growth_sprint_export_csv(array $summary): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="seo-campaign-calendar-growth-sprint-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['day', 'week', 'date_hint', 'source', 'focus', 'priority', 'status', 'title', 'objective', 'why', 'checklist', 'kpi', 'owner', 'due_date', 'note', 'cta_url', 'target_url'], ',', '"', '\\', "\n");
        foreach ((array)($summary['all_tasks'] ?? []) as $task) {
            if (!is_array($task)) {
                continue;
            }
            fputcsv($out, [
                (int)($task['day'] ?? 0),
                (int)($task['week'] ?? 0),
                (string)($task['date_hint'] ?? ''),
                (string)($task['source'] ?? ''),
                (string)($task['focus'] ?? ''),
                (string)($task['priority'] ?? ''),
                (string)($task['status_label'] ?? ''),
                (string)($task['title'] ?? ''),
                (string)($task['objective'] ?? ''),
                (string)($task['why'] ?? ''),
                implode(' | ', (array)($task['checklist'] ?? [])),
                (string)($task['kpi'] ?? ''),
                (string)($task['owner'] ?? ''),
                (string)($task['due_date'] ?? ''),
                (string)($task['note'] ?? ''),
                (string)($task['cta_url'] ?? ''),
                (string)($task['target_url'] ?? ''),
            ], ',', '"', '\\', "\n");
        }
        fclose($out);
        exit;
    }
}
