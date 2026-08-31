<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_execution_storage_path')) {
    function seo_execution_storage_path(): string
    {
        return STORAGE_PATH . '/seo-execution-board.json';
    }
}

if (!function_exists('seo_execution_statuses')) {
    function seo_execution_statuses(): array
    {
        return [
            'todo' => ['label' => 'Ide Masuk', 'hint' => 'Brief sudah siap, belum dikerjakan.', 'class' => 'admin-status-pill admin-status-pill--info'],
            'writing' => ['label' => 'Sedang Ditulis', 'hint' => 'Konten sedang dibuat/dilengkapi.', 'class' => 'admin-status-pill admin-status-pill--warning'],
            'review' => ['label' => 'Review SEO', 'hint' => 'Cek judul, meta, intent, FAQ, dan readability.', 'class' => 'admin-status-pill admin-status-pill--warning'],
            'internal_link' => ['label' => 'Internal Link', 'hint' => 'Butuh link dari/ke halaman target.', 'class' => 'admin-status-pill admin-status-pill--info'],
            'ready' => ['label' => 'Siap Publish', 'hint' => 'Konten sudah siap masuk artikel/landing page.', 'class' => 'admin-status-pill admin-status-pill--success'],
            'published' => ['label' => 'Published', 'hint' => 'Sudah tayang dan tinggal dipantau performanya.', 'class' => 'admin-status-pill admin-status-pill--success'],
        ];
    }
}

if (!function_exists('seo_execution_status_label')) {
    function seo_execution_status_label(string $status): string
    {
        $statuses = seo_execution_statuses();
        return (string)($statuses[$status]['label'] ?? $statuses['todo']['label']);
    }
}

if (!function_exists('seo_execution_status_class')) {
    function seo_execution_status_class(string $status): string
    {
        $statuses = seo_execution_statuses();
        return (string)($statuses[$status]['class'] ?? $statuses['todo']['class']);
    }
}

if (!function_exists('seo_execution_read_state')) {
    function seo_execution_read_state(): array
    {
        $path = seo_execution_storage_path();
        if (!is_file($path)) {
            return ['updated_at' => '', 'tasks' => []];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded)) {
            return ['updated_at' => '', 'tasks' => []];
        }

        if (!is_array($decoded['tasks'] ?? null)) {
            $decoded['tasks'] = [];
        }

        return $decoded;
    }
}

if (!function_exists('seo_execution_write_state')) {
    function seo_execution_write_state(array $state): bool
    {
        $path = seo_execution_storage_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $state['updated_at'] = date('Y-m-d H:i:s');
        $state['tasks'] = is_array($state['tasks'] ?? null) ? $state['tasks'] : [];

        return (bool)file_put_contents(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}

if (!function_exists('seo_execution_state_by_id')) {
    function seo_execution_state_by_id(): array
    {
        $state = seo_execution_read_state();
        $rows = [];
        foreach ((array)($state['tasks'] ?? []) as $taskId => $row) {
            if (is_array($row)) {
                $rows[(string)$taskId] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('seo_execution_priority_label')) {
    function seo_execution_priority_label(int $score): string
    {
        if (function_exists('seo_content_priority_label')) {
            return seo_content_priority_label($score);
        }
        return $score >= 85 ? 'Tinggi' : ($score >= 65 ? 'Sedang' : 'Rendah');
    }
}

if (!function_exists('seo_execution_priority_class')) {
    function seo_execution_priority_class(int $score): string
    {
        if (function_exists('seo_content_priority_class')) {
            return seo_content_priority_class($score);
        }
        return $score >= 85 ? 'admin-status-pill admin-status-pill--error' : ($score >= 65 ? 'admin-status-pill admin-status-pill--warning' : 'admin-status-pill admin-status-pill--info');
    }
}

if (!function_exists('seo_execution_task_id')) {
    function seo_execution_task_id(array $brief, string $source = 'brief', int $index = 0): string
    {
        $base = (string)($brief['id'] ?? $brief['suggested_slug'] ?? $brief['title'] ?? 'task-' . $index);
        $base = function_exists('slugify') ? slugify($base) : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $base) ?: '', '-'));
        return ($source === 'backlog' ? 'backlog-' : 'brief-') . ($base !== '' ? $base : ('task-' . $index));
    }
}

if (!function_exists('seo_execution_default_due_date')) {
    function seo_execution_default_due_date(string $week): string
    {
        $number = (int)preg_replace('/\D+/', '', $week);
        $number = max(1, min(4, $number ?: 1));
        return date('Y-m-d', strtotime('+' . (($number * 7) - 2) . ' days'));
    }
}

if (!function_exists('seo_execution_normalize_task')) {
    function seo_execution_normalize_task(array $brief, string $source = 'brief', int $index = 0, array $stored = []): array
    {
        $id = seo_execution_task_id($brief, $source, $index);
        $priorityScore = (int)($brief['priority_score'] ?? ($source === 'backlog' ? 58 : 70));
        $week = (string)($brief['week'] ?? ('Minggu ' . (($index % 4) + 1)));
        $keywords = array_values(array_filter(array_map('strval', (array)($brief['keyword_seed'] ?? []))));
        $title = trim((string)($brief['title'] ?? 'Task SEO'));
        $targetTitle = trim((string)($brief['target_title'] ?? ($brief['cluster'] ?? 'Halaman target')));
        $targetUrl = (string)($brief['target_url'] ?? $brief['internal_link_target'] ?? '');
        $status = (string)($stored['status'] ?? 'todo');
        if (!array_key_exists($status, seo_execution_statuses())) {
            $status = 'todo';
        }

        return [
            'id' => $id,
            'source' => $source,
            'title' => $title,
            'week' => $week,
            'status' => $status,
            'status_label' => seo_execution_status_label($status),
            'priority_score' => min(100, max(0, $priorityScore)),
            'priority_label' => seo_execution_priority_label($priorityScore),
            'content_type' => (string)($brief['content_type'] ?? ($source === 'backlog' ? 'backlog' : 'article')),
            'content_type_label' => (string)($brief['content_type_label'] ?? ($source === 'backlog' ? 'Backlog Topik' : 'Artikel SEO')),
            'intent_label' => (string)($brief['intent_label'] ?? ($brief['intent'] ?? 'Growth Content')),
            'target_title' => $targetTitle,
            'target_type' => (string)($brief['target_type'] ?? ''),
            'target_url' => $targetUrl,
            'keyword_seed' => array_slice($keywords, 0, 8),
            'suggested_slug' => (string)($brief['suggested_slug'] ?? (function_exists('slugify') ? slugify($title) : strtolower(str_replace(' ', '-', $title)))),
            'meta_title_template' => (string)($brief['meta_title_template'] ?? $title),
            'meta_description_template' => (string)($brief['meta_description_template'] ?? ('Pelajari ' . strtolower($title) . ' sebelum mengambil keputusan terbaik.')),
            'outline' => array_values(array_filter(array_map('strval', (array)($brief['outline'] ?? [])))),
            'faq_questions' => array_values(array_filter(array_map('strval', (array)($brief['faq_questions'] ?? [])))),
            'internal_link_anchor' => (string)($brief['internal_link_anchor'] ?? ($keywords ? implode(' ', array_slice($keywords, 0, 3)) : $targetTitle)),
            'cta_note' => (string)($brief['cta_note'] ?? $brief['recommended_cta'] ?? 'Tambahkan CTA natural ke halaman conversion yang relevan.'),
            'brief_note' => (string)($brief['brief_note'] ?? $brief['reason'] ?? 'Konten ini membantu memperkuat SEO, trust, dan conversion.'),
            'owner' => trim((string)($stored['owner'] ?? '')),
            'due_date' => trim((string)($stored['due_date'] ?? seo_execution_default_due_date($week))),
            'note' => trim((string)($stored['note'] ?? '')),
            'updated_at' => trim((string)($stored['updated_at'] ?? '')),
            'completed_at' => trim((string)($stored['completed_at'] ?? '')),
        ];
    }
}

if (!function_exists('seo_execution_tasks')) {
    function seo_execution_tasks(): array
    {
        $planner = function_exists('seo_content_planner_summary') ? seo_content_planner_summary() : [];
        $stateById = seo_execution_state_by_id();
        $tasks = [];

        foreach ((array)($planner['briefs'] ?? []) as $index => $brief) {
            $base = seo_execution_normalize_task((array)$brief, 'brief', (int)$index);
            $tasks[] = seo_execution_normalize_task((array)$brief, 'brief', (int)$index, (array)($stateById[$base['id']] ?? []));
        }

        foreach (array_slice((array)($planner['backlog'] ?? []), 0, 10) as $index => $brief) {
            $base = seo_execution_normalize_task((array)$brief, 'backlog', (int)$index);
            $tasks[] = seo_execution_normalize_task((array)$brief, 'backlog', (int)$index, (array)($stateById[$base['id']] ?? []));
        }

        usort($tasks, static function (array $a, array $b): int {
            $statusWeight = ['todo' => 0, 'writing' => 1, 'review' => 2, 'internal_link' => 3, 'ready' => 4, 'published' => 5];
            $aStatus = (int)($statusWeight[(string)($a['status'] ?? 'todo')] ?? 0);
            $bStatus = (int)($statusWeight[(string)($b['status'] ?? 'todo')] ?? 0);
            return ($aStatus <=> $bStatus)
                ?: ((int)($b['priority_score'] ?? 0) <=> (int)($a['priority_score'] ?? 0))
                ?: strcmp((string)($a['due_date'] ?? ''), (string)($b['due_date'] ?? ''));
        });

        return $tasks;
    }
}

if (!function_exists('seo_execution_filtered_tasks')) {
    function seo_execution_filtered_tasks(array $tasks, array $filters): array
    {
        $status = (string)($filters['status'] ?? 'all');
        $week = (string)($filters['week'] ?? 'all');
        $priority = (string)($filters['priority'] ?? 'all');
        $q = trim((string)($filters['q'] ?? ''));

        return array_values(array_filter($tasks, static function (array $task) use ($status, $week, $priority, $q): bool {
            if ($status !== 'all' && (string)($task['status'] ?? '') !== $status) {
                return false;
            }
            if ($week !== 'all' && (string)($task['week'] ?? '') !== $week) {
                return false;
            }
            if ($priority !== 'all') {
                $label = function_exists('mb_strtolower') ? mb_strtolower((string)($task['priority_label'] ?? ''), 'UTF-8') : strtolower((string)($task['priority_label'] ?? ''));
                if ($label !== $priority) {
                    return false;
                }
            }
            if ($q !== '') {
                $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
                $haystack = implode(' ', array_map('strval', [
                    $task['title'] ?? '', $task['target_title'] ?? '', $task['content_type_label'] ?? '', $task['intent_label'] ?? '',
                    $task['suggested_slug'] ?? '', $task['internal_link_anchor'] ?? '', implode(' ', (array)($task['keyword_seed'] ?? [])),
                    $task['note'] ?? '', $task['owner'] ?? '',
                ]));
                $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }
            return true;
        }));
    }
}

if (!function_exists('seo_execution_metrics')) {
    function seo_execution_metrics(array $tasks): array
    {
        $counts = array_fill_keys(array_keys(seo_execution_statuses()), 0);
        $highPriority = 0;
        $overdue = 0;
        $today = date('Y-m-d');
        foreach ($tasks as $task) {
            $status = (string)($task['status'] ?? 'todo');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if ((int)($task['priority_score'] ?? 0) >= 85) {
                $highPriority++;
            }
            $dueDate = (string)($task['due_date'] ?? '');
            if ($dueDate !== '' && $dueDate < $today && !in_array($status, ['ready', 'published'], true)) {
                $overdue++;
            }
        }

        $done = (int)$counts['published'] + (int)$counts['ready'];
        $total = count($tasks);
        return [
            'total' => $total,
            'counts' => $counts,
            'high_priority' => $highPriority,
            'overdue' => $overdue,
            'progress_percent' => $total > 0 ? (int)round(($done / $total) * 100) : 0,
            'active_count' => $total - (int)$counts['published'],
        ];
    }
}

if (!function_exists('seo_execution_save_task_state')) {
    function seo_execution_save_task_state(string $taskId, array $payload): bool
    {
        $taskId = trim($taskId);
        if ($taskId === '') {
            return false;
        }

        $state = seo_execution_read_state();
        $tasks = (array)($state['tasks'] ?? []);
        $existing = is_array($tasks[$taskId] ?? null) ? (array)$tasks[$taskId] : [];
        $status = (string)($payload['status'] ?? ($existing['status'] ?? 'todo'));
        if (!array_key_exists($status, seo_execution_statuses())) {
            $status = 'todo';
        }

        $dueDate = trim((string)($payload['due_date'] ?? ($existing['due_date'] ?? '')));
        if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $dueDate = '';
        }

        $completedAt = (string)($existing['completed_at'] ?? '');
        if (in_array($status, ['ready', 'published'], true) && $completedAt === '') {
            $completedAt = date('Y-m-d H:i:s');
        }
        if (!in_array($status, ['ready', 'published'], true)) {
            $completedAt = '';
        }

        $tasks[$taskId] = [
            'status' => $status,
            'owner' => trim((string)($payload['owner'] ?? ($existing['owner'] ?? ''))),
            'due_date' => $dueDate,
            'note' => trim((string)($payload['note'] ?? ($existing['note'] ?? ''))),
            'updated_at' => date('Y-m-d H:i:s'),
            'completed_at' => $completedAt,
        ];

        $state['tasks'] = $tasks;
        $ok = seo_execution_write_state($state);
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('update', 'seo_execution_task', null, 'Task SEO execution diperbarui.', ['task_id' => $taskId, 'status' => $status]);
        }
        return $ok;
    }
}

if (!function_exists('seo_execution_reset_state')) {
    function seo_execution_reset_state(): bool
    {
        $path = seo_execution_storage_path();
        if (is_file($path)) {
            return @unlink($path);
        }
        return true;
    }
}

if (!function_exists('seo_execution_article_draft_html')) {
    function seo_execution_article_draft_html(array $task): string
    {
        $title = (string)($task['title'] ?? 'Judul konten SEO');
        $target = (string)($task['target_title'] ?? 'halaman target');
        $keywords = (array)($task['keyword_seed'] ?? []);
        $mainKeyword = trim((string)($keywords[0] ?? $target));
        if ($mainKeyword === '') {
            $mainKeyword = $target !== '' ? $target : 'topik ini';
        }

        $html = '<p><strong>Catatan penulis:</strong> Artikel ini disiapkan dari SEO Execution Board. Sesuaikan contoh, bukti, harga, area layanan, dan CTA dengan bisnis Anda sebelum publish.</p>';
        $html .= '<h2>Kenapa ' . esc($mainKeyword) . ' penting untuk calon customer?</h2>';
        $html .= '<p>Bagian pembuka harus langsung menjawab kebutuhan pembaca, menjelaskan masalah utama, dan menunjukkan kenapa solusi dari bisnis Anda relevan.</p>';

        foreach ((array)($task['outline'] ?? []) as $point) {
            $point = trim((string)$point);
            if ($point === '') {
                continue;
            }
            $html .= '<h2>' . esc($point) . '</h2>';
            $html .= '<p>Jelaskan poin ini dengan bahasa sederhana, contoh nyata, detail manfaat, dan kaitkan dengan kebutuhan calon customer.</p>';
        }

        $html .= '<h2>FAQ seputar ' . esc($mainKeyword) . '</h2>';
        foreach ((array)($task['faq_questions'] ?? []) as $question) {
            $question = trim((string)$question);
            if ($question === '') {
                continue;
            }
            $html .= '<h3>' . esc($question) . '</h3><p>Jawab secara jelas, ringkas, dan tambahkan arahan ke konsultasi, WhatsApp, form, katalog, atau checkout jika relevan.</p>';
        }

        $anchor = trim((string)($task['internal_link_anchor'] ?? $target));
        $html .= '<h2>Langkah berikutnya</h2>';
        $html .= '<p>Setelah pembaca memahami topik ini, arahkan mereka ke <strong>' . esc($target) . '</strong> dengan anchor internal link seperti <em>' . esc($anchor) . '</em>. ' . esc((string)($task['cta_note'] ?? 'Tambahkan CTA natural ke halaman target.')) . '</p>';

        return $html;
    }
}
