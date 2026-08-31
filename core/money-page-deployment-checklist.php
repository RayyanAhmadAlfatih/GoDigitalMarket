<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MONEY PAGE DEPLOYMENT CHECKLIST
|--------------------------------------------------------------------------
| Turns SEO Money Page Optimizer recommendations into practical deployment
| tasks. This is an execution layer only; tracking remains in Lead Tracking,
| CTA Result Tracker, SEO Profit Attribution, and SEO Journey Map.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('money_deploy_clean')) {
    function money_deploy_clean(mixed $value, int $max = 220): string
    {
        if (function_exists('seo_money_clean')) {
            return seo_money_clean($value, $max);
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('money_deploy_id')) {
    function money_deploy_id(string $value = ''): string
    {
        if (function_exists('seo_money_id')) {
            return seo_money_id($value);
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');

        return substr($value, 0, 140);
    }
}

if (!function_exists('money_deploy_storage_file')) {
    function money_deploy_storage_file(): string
    {
        return STORAGE_PATH . '/money-page-deployment-checklist.json';
    }
}

if (!function_exists('money_deploy_status_options')) {
    function money_deploy_status_options(): array
    {
        return [
            'pending' => 'Belum dikerjakan',
            'working' => 'Sedang dikerjakan',
            'done' => 'Selesai',
            'blocked' => 'Tertahan',
            'skipped' => 'Dilewati',
        ];
    }
}

if (!function_exists('money_deploy_filter_options')) {
    function money_deploy_filter_options(): array
    {
        return [
            'all' => 'Semua Status',
            'open' => 'Masih Terbuka',
            'working' => 'Sedang Dikerjakan',
            'blocked' => 'Tertahan',
            'done' => 'Selesai',
        ];
    }
}

if (!function_exists('money_deploy_default_settings')) {
    function money_deploy_default_settings(): array
    {
        return [
            'pages' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('money_deploy_normalize_task_state')) {
    function money_deploy_normalize_task_state(array $task): array
    {
        $options = money_deploy_status_options();
        $status = (string)($task['status'] ?? 'pending');
        if (!isset($options[$status])) {
            $status = 'pending';
        }

        return [
            'task_id' => money_deploy_id((string)($task['task_id'] ?? '')),
            'status' => $status,
            'note' => money_deploy_clean($task['note'] ?? '', 520),
            'updated_at' => money_deploy_clean($task['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('money_deploy_normalize_page_state')) {
    function money_deploy_normalize_page_state(array $page): array
    {
        $tasks = [];
        foreach ((array)($page['tasks'] ?? []) as $task) {
            if (!is_array($task)) {
                continue;
            }
            $normalized = money_deploy_normalize_task_state($task);
            if ((string)$normalized['task_id'] === '') {
                continue;
            }
            $tasks[(string)$normalized['task_id']] = $normalized;
        }

        return [
            'page_id' => money_deploy_id((string)($page['page_id'] ?? '')),
            'owner_note' => money_deploy_clean($page['owner_note'] ?? '', 640),
            'tasks' => $tasks,
            'updated_at' => money_deploy_clean($page['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('money_deploy_normalize_settings')) {
    function money_deploy_normalize_settings(array $settings): array
    {
        $settings = array_merge(money_deploy_default_settings(), $settings);
        $pages = [];

        foreach ((array)($settings['pages'] ?? []) as $page) {
            if (!is_array($page)) {
                continue;
            }
            $normalized = money_deploy_normalize_page_state($page);
            if ((string)$normalized['page_id'] === '') {
                continue;
            }
            $pages[(string)$normalized['page_id']] = $normalized;
        }

        return [
            'pages' => $pages,
            'updated_at' => money_deploy_clean($settings['updated_at'] ?? date(DATE_ATOM), 80),
        ];
    }
}

if (!function_exists('money_deploy_settings')) {
    function money_deploy_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = money_deploy_storage_file();
        if (!is_file($file)) {
            $cached = money_deploy_normalize_settings(money_deploy_default_settings());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = money_deploy_normalize_settings(money_deploy_default_settings());
            return $cached;
        }

        $cached = money_deploy_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('money_deploy_write_settings')) {
    function money_deploy_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = money_deploy_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(money_deploy_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Checklist deployment belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(money_deploy_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'money-page-deployment-checklist', null, 'Menyimpan checklist deployment money page.');
        }

        return true;
    }
}

if (!function_exists('money_deploy_update_task')) {
    function money_deploy_update_task(string $pageId, string $taskId, string $status, string $note = ''): bool
    {
        $pageId = money_deploy_id($pageId);
        $taskId = money_deploy_id($taskId);
        if ($pageId === '' || $taskId === '') {
            throw new RuntimeException('ID halaman atau task tidak valid.');
        }

        $options = money_deploy_status_options();
        if (!isset($options[$status])) {
            $status = 'pending';
        }

        $settings = money_deploy_settings(true);
        $page = (array)($settings['pages'][$pageId] ?? ['page_id' => $pageId, 'tasks' => []]);
        $page['page_id'] = $pageId;
        $page['tasks'][$taskId] = [
            'task_id' => $taskId,
            'status' => $status,
            'note' => money_deploy_clean($note, 520),
            'updated_at' => date(DATE_ATOM),
        ];
        $page['updated_at'] = date(DATE_ATOM);
        $settings['pages'][$pageId] = $page;

        return money_deploy_write_settings($settings, true);
    }
}

if (!function_exists('money_deploy_update_page_note')) {
    function money_deploy_update_page_note(string $pageId, string $note = ''): bool
    {
        $pageId = money_deploy_id($pageId);
        if ($pageId === '') {
            throw new RuntimeException('ID halaman tidak valid.');
        }

        $settings = money_deploy_settings(true);
        $page = (array)($settings['pages'][$pageId] ?? ['page_id' => $pageId, 'tasks' => []]);
        $page['page_id'] = $pageId;
        $page['owner_note'] = money_deploy_clean($note, 640);
        $page['updated_at'] = date(DATE_ATOM);
        $settings['pages'][$pageId] = $page;

        return money_deploy_write_settings($settings, true);
    }
}

if (!function_exists('money_deploy_mark_page_done')) {
    function money_deploy_mark_page_done(string $pageId, array $taskIds): bool
    {
        $pageId = money_deploy_id($pageId);
        if ($pageId === '') {
            throw new RuntimeException('ID halaman tidak valid.');
        }

        $settings = money_deploy_settings(true);
        $page = (array)($settings['pages'][$pageId] ?? ['page_id' => $pageId, 'tasks' => []]);
        $page['page_id'] = $pageId;
        foreach ($taskIds as $taskId) {
            $taskId = money_deploy_id((string)$taskId);
            if ($taskId === '') {
                continue;
            }
            $page['tasks'][$taskId] = [
                'task_id' => $taskId,
                'status' => 'done',
                'note' => 'Ditandai selesai dari checklist halaman.',
                'updated_at' => date(DATE_ATOM),
            ];
        }
        $page['updated_at'] = date(DATE_ATOM);
        $settings['pages'][$pageId] = $page;

        return money_deploy_write_settings($settings, true);
    }
}

if (!function_exists('money_deploy_reset_page')) {
    function money_deploy_reset_page(string $pageId): bool
    {
        $pageId = money_deploy_id($pageId);
        if ($pageId === '') {
            throw new RuntimeException('ID halaman tidak valid.');
        }

        $settings = money_deploy_settings(true);
        unset($settings['pages'][$pageId]);

        return money_deploy_write_settings($settings, true);
    }
}

if (!function_exists('money_deploy_reset_all')) {
    function money_deploy_reset_all(): void
    {
        if (is_file(money_deploy_storage_file())) {
            @unlink(money_deploy_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'money-page-deployment-checklist', null, 'Reset semua checklist deployment money page.');
        }
    }
}

if (!function_exists('money_deploy_action_url')) {
    function money_deploy_action_url(string $path): string
    {
        return function_exists('url') ? url($path) : '/' . ltrim($path, '/');
    }
}

if (!function_exists('money_deploy_priority_weight')) {
    function money_deploy_priority_weight(string $priority): int
    {
        return match ($priority) {
            'critical' => 5,
            'high' => 4,
            'medium' => 3,
            'low' => 2,
            default => 1,
        };
    }
}

if (!function_exists('money_deploy_task')) {
    function money_deploy_task(string $id, string $category, string $title, string $description, string $priority, string $actionLabel, string $actionUrl, array $checkpoints = [], array $meta = []): array
    {
        return [
            'task_id' => money_deploy_id($id),
            'category' => money_deploy_clean($category, 80),
            'title' => money_deploy_clean($title, 140),
            'description' => money_deploy_clean($description, 360),
            'priority' => in_array($priority, ['critical', 'high', 'medium', 'low'], true) ? $priority : 'medium',
            'action_label' => money_deploy_clean($actionLabel, 80),
            'action_url' => $actionUrl,
            'checkpoints' => array_values(array_filter(array_map(static fn($item): string => money_deploy_clean($item, 180), $checkpoints))),
            'meta' => $meta,
        ];
    }
}

if (!function_exists('money_deploy_task_templates')) {
    function money_deploy_task_templates(array $optimizer): array
    {
        $page = (array)($optimizer['item'] ?? []);
        $metrics = (array)($optimizer['metrics'] ?? []);
        $cta = (array)($optimizer['cta_plan'] ?? []);
        $links = (array)($optimizer['internal_links'] ?? []);
        $trustPlans = (array)($optimizer['trust_plan'] ?? []);
        $fixes = (array)($optimizer['content_fixes'] ?? []);
        $stage = (array)($optimizer['stage'] ?? []);

        $pageTitle = money_deploy_clean($page['title'] ?? 'halaman ini', 100);
        $pageEditUrl = money_deploy_clean($page['edit_url'] ?? '', 220);
        $pageUrl = money_deploy_clean($page['url'] ?? '', 220);
        $ctaLabel = money_deploy_clean($cta['cta_label'] ?? 'Tanya Rekomendasi', 80);
        $ctaPlacement = money_deploy_clean($cta['placement_label'] ?? 'CTA Placement', 120);
        $targetTitle = money_deploy_clean($links[0]['title'] ?? 'produk/jasa paling relevan', 120);
        $trustFocus = money_deploy_clean($trustPlans[0]['label'] ?? 'FAQ, testimoni, atau proof', 120);
        $leadCount = (int)($metrics['leads'] ?? 0);
        $orderCount = (int)($metrics['orders'] ?? 0) + (int)($metrics['payments'] ?? 0);
        $stageKey = (string)($stage['key'] ?? 'seed_money_page');

        $editAction = $pageEditUrl !== '' ? $pageEditUrl : money_deploy_action_url('admin/universal-seo');
        $tasks = [];

        $fixTitle = money_deploy_clean($fixes[0]['title'] ?? 'Perkuat transisi dari konten ke penawaran', 140);
        $fixText = money_deploy_clean($fixes[0]['text'] ?? 'Tambahkan kalimat yang menghubungkan isi halaman dengan produk, layanan, form, atau WhatsApp.', 240);
        $tasks[] = money_deploy_task(
            'content_offer_bridge',
            'Content Fix',
            'Edit konten dan transisi offer',
            $fixTitle . '. ' . $fixText,
            in_array($stageKey, ['seo_foundation', 'seed_money_page'], true) ? 'high' : 'medium',
            'Edit Konten',
            $editAction,
            [
                'Pastikan intro menjawab kebutuhan calon customer.',
                'Tambahkan paragraf transisi menuju solusi/produk/jasa.',
                'Jangan hanya edukasi; arahkan pembaca ke langkah berikutnya.',
            ],
            ['page_url' => $pageUrl]
        );

        $tasks[] = money_deploy_task(
            'cta_primary_deployment',
            'CTA Deployment',
            'Pasang CTA utama di money page',
            'Gunakan CTA “' . $ctaLabel . '” di area ' . $ctaPlacement . ' agar traffic SEO punya jalur menuju lead/order.',
            'critical',
            'Buka CTA Placement',
            money_deploy_action_url('admin/cta-placement'),
            array_merge([
                'Pilih placement yang sesuai dengan halaman ini.',
                'Pastikan label tombol jelas dan tidak terlalu umum.',
                'Arahkan URL CTA ke form, WhatsApp, katalog, atau landing page yang benar.',
            ], array_slice((array)($cta['slots'] ?? []), 0, 2)),
            ['cta_label' => $ctaLabel, 'placement' => $ctaPlacement, 'source_variant_id' => (string)($cta['source_variant_id'] ?? '')]
        );

        $tasks[] = money_deploy_task(
            'internal_link_deployment',
            'Internal Link',
            'Tambahkan jalur internal link',
            'Arahkan pembaca dari “' . $pageTitle . '” ke “' . $targetTitle . '” atau halaman relevan lain supaya journey tidak berhenti di artikel.',
            'high',
            'Kelola Internal Link',
            $editAction,
            [
                'Tambahkan minimal 1 link ke produk/jasa/landing page utama.',
                'Tambahkan 1 link ke artikel pendukung bila relevan.',
                'Gunakan anchor text natural, bukan hanya “klik di sini”.',
            ],
            ['targets' => array_slice($links, 0, 4)]
        );

        $tasks[] = money_deploy_task(
            'trust_block_deployment',
            'Trust Block',
            'Tambahkan bukti yang mengurangi ragu',
            'Lengkapi trust block seperti ' . $trustFocus . ' agar pengunjung lebih yakin setelah membaca halaman ini.',
            ($leadCount > 0 && $orderCount <= 0) ? 'critical' : 'medium',
            'Kelola Trust Block',
            money_deploy_action_url('admin/trust-conversion'),
            [
                'Tambahkan FAQ keberatan yang paling sering muncul.',
                'Tambahkan testimoni, portofolio, before-after, atau garansi bila ada.',
                'Pastikan proof relevan dengan topik halaman ini.',
            ],
            ['trust_plans' => array_slice($trustPlans, 0, 3)]
        );

        $tasks[] = money_deploy_task(
            'offer_variant_alignment',
            'Offer & Copy',
            'Sinkronkan offer dengan Offer Lab',
            'Pastikan headline, subheadline, tombol, dan proof yang dipakai di halaman ini konsisten dengan varian offer terbaik.',
            ((int)($metrics['clicks'] ?? 0) > 0 && $leadCount <= 0) ? 'high' : 'medium',
            'Buka Offer Lab',
            money_deploy_action_url('admin/offer-cta-testing'),
            [
                'Pakai winner/kandidat offer yang paling dekat dengan halaman ini.',
                'Catat hipotesis: kenapa offer ini dipasang di halaman tersebut.',
                'Jangan pasang terlalu banyak CTA yang saling berebut perhatian.',
            ],
            ['source_variant_title' => (string)($cta['source_variant_title'] ?? '')]
        );

        if ($leadCount > 0 && $orderCount <= 0) {
            $tasks[] = money_deploy_task(
                'follow_up_bridge',
                'Follow-up',
                'Siapkan follow-up untuk lead dari halaman ini',
                'Halaman sudah membawa lead. Jangan berhenti di form/chat masuk; siapkan follow-up agar peluang order tidak bocor.',
                'critical',
                'Buka Follow-up',
                money_deploy_action_url('admin/followups'),
                [
                    'Buat pesan follow-up yang menyebut konteks halaman/artikel.',
                    'Tambahkan proof singkat dan next step yang jelas.',
                    'Prioritaskan lead yang sudah bertanya harga, stok, jadwal, atau layanan.',
                ],
                ['leads' => $leadCount]
            );
        }

        $tasks[] = money_deploy_task(
            'monitor_journey_result',
            'Monitoring',
            'Pantau hasil setelah deployment',
            'Setelah CTA, internal link, trust, dan offer dipasang, pantau ulang apakah ada klik, lead, order, atau payment dari halaman ini.',
            'medium',
            'Cek Journey Map',
            money_deploy_action_url('admin/seo-assisted-journey'),
            [
                'Cek CTA Result Tracker setelah ada traffic baru.',
                'Cek SEO Journey Map untuk melihat bottleneck terbaru.',
                'Update keputusan: scale, perbaiki, atau ganti offer bila hasil belum naik.',
            ],
            ['journey_url' => money_deploy_action_url('admin/seo-assisted-journey'), 'cta_result_url' => money_deploy_action_url('admin/cta-result-tracker')]
        );

        usort($tasks, static function (array $a, array $b): int {
            return (money_deploy_priority_weight((string)($b['priority'] ?? 'medium')) <=> money_deploy_priority_weight((string)($a['priority'] ?? 'medium'))) ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        return $tasks;
    }
}

if (!function_exists('money_deploy_merge_task_state')) {
    function money_deploy_merge_task_state(array $task, array $state = []): array
    {
        $taskId = (string)($task['task_id'] ?? '');
        $state = money_deploy_normalize_task_state(array_merge(['task_id' => $taskId], $state));

        return array_merge($task, [
            'status' => (string)($state['status'] ?? 'pending'),
            'note' => (string)($state['note'] ?? ''),
            'updated_at' => (string)($state['updated_at'] ?? ''),
            'is_done' => in_array((string)($state['status'] ?? 'pending'), ['done', 'skipped'], true),
            'is_blocked' => (string)($state['status'] ?? '') === 'blocked',
        ]);
    }
}

if (!function_exists('money_deploy_checklist_item')) {
    function money_deploy_checklist_item(array $optimizer, array $pageState = []): array
    {
        $pageId = money_deploy_id((string)($optimizer['page_id'] ?? ''));
        $pageState = money_deploy_normalize_page_state(array_merge(['page_id' => $pageId], $pageState));
        $taskTemplates = money_deploy_task_templates($optimizer);
        $tasks = [];

        foreach ($taskTemplates as $task) {
            $taskId = (string)($task['task_id'] ?? '');
            $tasks[] = money_deploy_merge_task_state($task, (array)($pageState['tasks'][$taskId] ?? ['task_id' => $taskId]));
        }

        $total = count($tasks);
        $done = 0;
        $blocked = 0;
        $working = 0;
        $pending = 0;
        foreach ($tasks as $task) {
            $status = (string)($task['status'] ?? 'pending');
            if (in_array($status, ['done', 'skipped'], true)) {
                $done++;
            } elseif ($status === 'blocked') {
                $blocked++;
            } elseif ($status === 'working') {
                $working++;
            } else {
                $pending++;
            }
        }

        $progress = $total > 0 ? (int)round(($done / $total) * 100) : 0;
        $stage = 'open';
        $stageLabel = 'Masih terbuka';
        if ($total > 0 && $done >= $total) {
            $stage = 'done';
            $stageLabel = 'Selesai';
        } elseif ($blocked > 0) {
            $stage = 'blocked';
            $stageLabel = 'Ada hambatan';
        } elseif ($working > 0 || $done > 0) {
            $stage = 'working';
            $stageLabel = 'Sedang dikerjakan';
        }

        $nextTask = null;
        foreach ($tasks as $task) {
            if (empty($task['is_done']) && (string)($task['status'] ?? '') !== 'blocked') {
                $nextTask = $task;
                break;
            }
        }
        if ($nextTask === null) {
            foreach ($tasks as $task) {
                if (empty($task['is_done'])) {
                    $nextTask = $task;
                    break;
                }
            }
        }

        $deploymentScore = min(100, (int)round(((int)($optimizer['money_score'] ?? 0) * 0.62) + ($progress * 0.38)));

        return [
            'page_id' => $pageId,
            'optimizer' => $optimizer,
            'tasks' => $tasks,
            'counts' => [
                'total' => $total,
                'done' => $done,
                'pending' => $pending,
                'working' => $working,
                'blocked' => $blocked,
            ],
            'progress' => $progress,
            'deployment_score' => $deploymentScore,
            'deployment_stage' => $stage,
            'deployment_stage_label' => $stageLabel,
            'next_task' => $nextTask,
            'owner_note' => (string)($pageState['owner_note'] ?? ''),
            'updated_at' => (string)($pageState['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('money_deploy_summary')) {
    function money_deploy_summary(int $days = 30, string $type = 'all', string $priority = 'all', string $status = 'all'): array
    {
        $days = max(1, min(365, $days));
        $filters = money_deploy_filter_options();
        if (!isset($filters[$status])) {
            $status = 'all';
        }

        $moneySummary = function_exists('seo_money_summary') ? seo_money_summary($days, $type, $priority) : ['items' => []];
        $settings = money_deploy_settings(true);
        $items = [];

        foreach ((array)($moneySummary['items'] ?? []) as $optimizer) {
            if (!is_array($optimizer)) {
                continue;
            }
            $pageId = money_deploy_id((string)($optimizer['page_id'] ?? ''));
            if ($pageId === '') {
                continue;
            }
            $item = money_deploy_checklist_item($optimizer, (array)($settings['pages'][$pageId] ?? []));
            $stage = (string)($item['deployment_stage'] ?? 'open');
            if ($status === 'open' && $stage === 'done') {
                continue;
            }
            if (in_array($status, ['working', 'blocked', 'done'], true) && $stage !== $status) {
                continue;
            }
            $items[] = $item;
        }

        usort($items, static function (array $a, array $b): int {
            $stageWeight = ['blocked' => 4, 'working' => 3, 'open' => 2, 'done' => 1];
            $aw = $stageWeight[(string)($a['deployment_stage'] ?? 'open')] ?? 0;
            $bw = $stageWeight[(string)($b['deployment_stage'] ?? 'open')] ?? 0;
            return ($bw <=> $aw)
                ?: ((int)($b['optimizer']['money_score'] ?? 0) <=> (int)($a['optimizer']['money_score'] ?? 0))
                ?: ((int)($b['deployment_score'] ?? 0) <=> (int)($a['deployment_score'] ?? 0))
                ?: strcmp((string)($a['optimizer']['item']['title'] ?? ''), (string)($b['optimizer']['item']['title'] ?? ''));
        });

        $counts = ['total' => count($items), 'open' => 0, 'working' => 0, 'blocked' => 0, 'done' => 0, 'tasks_total' => 0, 'tasks_done' => 0, 'tasks_blocked' => 0];
        $scoreSum = 0;
        $nextActions = [];
        foreach ($items as $item) {
            $stage = (string)($item['deployment_stage'] ?? 'open');
            if (isset($counts[$stage])) {
                $counts[$stage]++;
            }
            $itemCounts = (array)($item['counts'] ?? []);
            $counts['tasks_total'] += (int)($itemCounts['total'] ?? 0);
            $counts['tasks_done'] += (int)($itemCounts['done'] ?? 0);
            $counts['tasks_blocked'] += (int)($itemCounts['blocked'] ?? 0);
            $scoreSum += (int)($item['deployment_score'] ?? 0);
            if (!empty($item['next_task'])) {
                $nextActions[] = [
                    'page_id' => (string)($item['page_id'] ?? ''),
                    'page_title' => money_deploy_clean($item['optimizer']['item']['title'] ?? 'Money Page', 120),
                    'task' => (array)$item['next_task'],
                    'money_score' => (int)($item['optimizer']['money_score'] ?? 0),
                ];
            }
        }

        $averageProgress = $counts['tasks_total'] > 0 ? (int)round(($counts['tasks_done'] / $counts['tasks_total']) * 100) : 0;
        $averageScore = $items ? (int)round($scoreSum / count($items)) : 0;
        $focus = 'Pilih money page prioritas, kerjakan checklist deployment, lalu pantau hasilnya di Journey Map.';
        if ($counts['blocked'] > 0) {
            $focus = 'Ada checklist yang tertahan. Bereskan hambatan dulu agar money page tidak mandek di tahap eksekusi.';
        } elseif ($averageProgress >= 75 && $counts['done'] > 0) {
            $focus = 'Checklist deployment mulai matang. Lanjut pantau CTA Result dan Journey Map untuk lihat dampak ke lead/order.';
        } elseif ($counts['working'] > 0) {
            $focus = 'Ada money page yang sedang dikerjakan. Fokus selesaikan CTA, internal link, trust, dan offer sebelum pindah halaman lain.';
        }

        return [
            'days' => $days,
            'type' => (string)($moneySummary['type'] ?? $type),
            'priority' => (string)($moneySummary['priority'] ?? $priority),
            'status' => $status,
            'type_options' => (array)($moneySummary['type_options'] ?? ['all' => 'Semua SEO Page']),
            'priority_options' => (array)($moneySummary['priority_options'] ?? ['all' => 'Semua Prioritas']),
            'status_options' => $filters,
            'tracking_enabled' => !empty($moneySummary['tracking_enabled']),
            'counts' => $counts,
            'average_progress' => $averageProgress,
            'deployment_score' => $averageScore,
            'top_focus' => $focus,
            'top_item' => $items[0] ?? null,
            'next_actions' => array_slice($nextActions, 0, 10),
            'items' => $items,
            'source_summary' => $moneySummary,
            'generated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('money_deploy_export_csv')) {
    function money_deploy_export_csv(array $items): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="money-page-deployment-checklist-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['page_id', 'title', 'priority', 'money_score', 'deployment_stage', 'progress', 'task_id', 'task_category', 'task_title', 'task_priority', 'task_status', 'task_note', 'action_url']);
        foreach ($items as $item) {
            $optimizer = (array)($item['optimizer'] ?? []);
            $page = (array)($optimizer['item'] ?? []);
            foreach ((array)($item['tasks'] ?? []) as $task) {
                fputcsv($out, [
                    (string)($item['page_id'] ?? ''),
                    (string)($page['title'] ?? ''),
                    (string)($optimizer['priority'] ?? ''),
                    (int)($optimizer['money_score'] ?? 0),
                    (string)($item['deployment_stage_label'] ?? ''),
                    (int)($item['progress'] ?? 0),
                    (string)($task['task_id'] ?? ''),
                    (string)($task['category'] ?? ''),
                    (string)($task['title'] ?? ''),
                    (string)($task['priority'] ?? ''),
                    (string)($task['status'] ?? ''),
                    (string)($task['note'] ?? ''),
                    (string)($task['action_url'] ?? ''),
                ]);
            }
        }
        fclose($out);
        exit;
    }
}
