<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO Publish Checklist / Quality Gate
|--------------------------------------------------------------------------
| Converts SEO Execution Board tasks into practical publish-readiness checks.
| This is intentionally lightweight and file-free: it reads existing planner
| and board data, then helps admin decide which content is ready to publish.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_publish_length')) {
    function seo_publish_length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}

if (!function_exists('seo_publish_word_count')) {
    function seo_publish_word_count(string $value): int
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return 0;
        }
        $parts = preg_split('/\s+/u', $value) ?: [];
        return count(array_filter($parts, static fn($word): bool => trim((string)$word) !== ''));
    }
}

if (!function_exists('seo_publish_check_item')) {
    function seo_publish_check_item(string $key, string $label, bool $pass, string $hint, int $weight = 10): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'pass' => $pass,
            'hint' => $hint,
            'weight' => max(1, $weight),
        ];
    }
}

if (!function_exists('seo_publish_task_checks')) {
    function seo_publish_task_checks(array $task): array
    {
        $title = trim((string)($task['title'] ?? ''));
        $slug = trim((string)($task['suggested_slug'] ?? ''));
        $metaTitle = trim((string)($task['meta_title_template'] ?? ''));
        $metaDescription = trim((string)($task['meta_description_template'] ?? ''));
        $keywords = array_values(array_filter(array_map('strval', (array)($task['keyword_seed'] ?? []))));
        $outline = array_values(array_filter(array_map('strval', (array)($task['outline'] ?? []))));
        $faq = array_values(array_filter(array_map('strval', (array)($task['faq_questions'] ?? []))));
        $anchor = trim((string)($task['internal_link_anchor'] ?? ''));
        $targetTitle = trim((string)($task['target_title'] ?? ''));
        $targetUrl = trim((string)($task['target_url'] ?? ''));
        $cta = trim((string)($task['cta_note'] ?? ''));
        $briefNote = trim((string)($task['brief_note'] ?? ''));
        $draft = function_exists('seo_execution_article_draft_html') ? seo_execution_article_draft_html($task) : '';
        $wordEstimate = seo_publish_word_count($draft);

        return [
            seo_publish_check_item('title', 'Judul konten jelas', $title !== '' && seo_publish_length($title) >= 18, 'Judul minimal cukup deskriptif dan mudah dipahami calon customer.', 8),
            seo_publish_check_item('slug', 'Slug SEO tersedia', $slug !== '' && preg_match('/^[a-z0-9\-]+$/', $slug) === 1, 'Slug harus pendek, lowercase, dan memakai tanda hubung.', 8),
            seo_publish_check_item('meta_title', 'Meta title aman', seo_publish_length($metaTitle) >= 30 && seo_publish_length($metaTitle) <= 70, 'Idealnya 30–70 karakter agar snippet Google lebih rapi.', 10),
            seo_publish_check_item('meta_description', 'Meta description siap', seo_publish_length($metaDescription) >= 80 && seo_publish_length($metaDescription) <= 170, 'Idealnya 80–170 karakter, ada manfaat dan alasan klik.', 10),
            seo_publish_check_item('keyword', 'Focus keyword ada', count($keywords) >= 1, 'Minimal satu keyword seed supaya arah konten tidak ngambang.', 8),
            seo_publish_check_item('outline', 'Outline cukup lengkap', count($outline) >= 4, 'Minimal empat sub-topik agar artikel tidak terlalu tipis.', 10),
            seo_publish_check_item('faq', 'FAQ pendukung ada', count($faq) >= 2, 'FAQ membantu long-tail keyword dan trust calon customer.', 8),
            seo_publish_check_item('internal_link', 'Internal link jelas', $anchor !== '' && ($targetTitle !== '' || $targetUrl !== ''), 'Konten harus mengarah ke money page, katalog, layanan, landing page, atau checkout.', 12),
            seo_publish_check_item('cta', 'CTA natural tersedia', $cta !== '' || $briefNote !== '', 'Tambahkan arahan ke WhatsApp, form, katalog, checkout, atau konsultasi.', 8),
            seo_publish_check_item('draft_depth', 'Draft body tidak kosong', $wordEstimate >= 120 || count($outline) >= 4, 'Draft awal perlu cukup isi sebelum ditempel dan dipoles di editor.', 8),
            seo_publish_check_item('schedule', 'Target produksi ada', trim((string)($task['due_date'] ?? '')) !== '', 'Tanggal target membantu board SEO tetap bergerak.', 4),
            seo_publish_check_item('status', 'Status siap diproses', in_array((string)($task['status'] ?? 'todo'), ['review', 'internal_link', 'ready', 'published'], true), 'Naikkan status setelah draft mulai direview sebelum publish.', 6),
        ];
    }
}

if (!function_exists('seo_publish_task_score')) {
    function seo_publish_task_score(array $task): array
    {
        $checks = seo_publish_task_checks($task);
        $total = 0;
        $passed = 0;
        $blocking = [];

        foreach ($checks as $check) {
            $weight = (int)($check['weight'] ?? 1);
            $total += $weight;
            if (!empty($check['pass'])) {
                $passed += $weight;
            } elseif (in_array((string)($check['key'] ?? ''), ['meta_title', 'meta_description', 'internal_link', 'outline'], true)) {
                $blocking[] = $check;
            }
        }

        $score = $total > 0 ? (int)round(($passed / $total) * 100) : 0;
        $status = 'Perlu Dilengkapi';
        $class = 'admin-status-pill admin-status-pill--warning';
        if ($score >= 90 && !$blocking) {
            $status = 'Siap Publish';
            $class = 'admin-status-pill admin-status-pill--success';
        } elseif ($score >= 75) {
            $status = 'Hampir Siap';
            $class = 'admin-status-pill admin-status-pill--info';
        }

        return [
            'score' => $score,
            'status' => $status,
            'class' => $class,
            'checks' => $checks,
            'blocking_count' => count($blocking),
            'passed_count' => count(array_filter($checks, static fn(array $row): bool => !empty($row['pass']))),
            'total_count' => count($checks),
        ];
    }
}

if (!function_exists('seo_publish_tasks')) {
    function seo_publish_tasks(): array
    {
        $tasks = function_exists('seo_execution_tasks') ? seo_execution_tasks() : [];
        foreach ($tasks as $index => $task) {
            $score = seo_publish_task_score((array)$task);
            $task['publish_score'] = (int)$score['score'];
            $task['publish_status'] = (string)$score['status'];
            $task['publish_status_class'] = (string)$score['class'];
            $task['publish_blocking_count'] = (int)$score['blocking_count'];
            $task['publish_passed_count'] = (int)$score['passed_count'];
            $task['publish_total_count'] = (int)$score['total_count'];
            $tasks[$index] = $task;
        }

        usort($tasks, static function (array $a, array $b): int {
            return ((int)($b['publish_score'] ?? 0) <=> (int)($a['publish_score'] ?? 0))
                ?: ((int)($b['priority_score'] ?? 0) <=> (int)($a['priority_score'] ?? 0));
        });

        return $tasks;
    }
}

if (!function_exists('seo_publish_filtered_tasks')) {
    function seo_publish_filtered_tasks(array $tasks, array $filters): array
    {
        $readiness = (string)($filters['readiness'] ?? 'all');
        $status = (string)($filters['status'] ?? 'all');
        $q = trim((string)($filters['q'] ?? ''));

        return array_values(array_filter($tasks, static function (array $task) use ($readiness, $status, $q): bool {
            $score = (int)($task['publish_score'] ?? 0);
            if ($readiness === 'ready' && $score < 90) {
                return false;
            }
            if ($readiness === 'almost' && ($score < 75 || $score >= 90)) {
                return false;
            }
            if ($readiness === 'needs_fix' && $score >= 75) {
                return false;
            }
            if ($status !== 'all' && (string)($task['status'] ?? '') !== $status) {
                return false;
            }
            if ($q !== '') {
                $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
                $haystack = implode(' ', array_map('strval', [
                    $task['title'] ?? '', $task['target_title'] ?? '', $task['suggested_slug'] ?? '',
                    $task['meta_title_template'] ?? '', $task['meta_description_template'] ?? '',
                    implode(' ', (array)($task['keyword_seed'] ?? [])), $task['internal_link_anchor'] ?? '',
                    $task['owner'] ?? '', $task['note'] ?? '',
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

if (!function_exists('seo_publish_metrics')) {
    function seo_publish_metrics(array $tasks): array
    {
        $total = count($tasks);
        $ready = 0;
        $almost = 0;
        $needsFix = 0;
        $scoreTotal = 0;

        foreach ($tasks as $task) {
            $score = (int)($task['publish_score'] ?? 0);
            $scoreTotal += $score;
            if ($score >= 90) {
                $ready++;
            } elseif ($score >= 75) {
                $almost++;
            } else {
                $needsFix++;
            }
        }

        return [
            'total' => $total,
            'ready' => $ready,
            'almost' => $almost,
            'needs_fix' => $needsFix,
            'average_score' => $total > 0 ? (int)round($scoreTotal / $total) : 0,
        ];
    }
}

if (!function_exists('seo_publish_find_task')) {
    function seo_publish_find_task(array $tasks, string $taskId): ?array
    {
        foreach ($tasks as $task) {
            if ((string)($task['id'] ?? '') === $taskId) {
                return $task;
            }
        }
        return null;
    }
}
