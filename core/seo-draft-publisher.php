<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| SEO Draft to Article Publisher
|--------------------------------------------------------------------------
| Lightweight bridge from SEO Execution Board / Publish Checklist into the
| article editor. Drafts are stored separately first, so they do not appear on
| the public website until admin intentionally saves them as articles.
|--------------------------------------------------------------------------
*/

if (!function_exists('seo_draft_publisher_storage_path')) {
    function seo_draft_publisher_storage_path(): string
    {
        return STORAGE_PATH . '/seo-article-drafts.json';
    }
}

if (!function_exists('seo_draft_publisher_read_state')) {
    function seo_draft_publisher_read_state(): array
    {
        $path = seo_draft_publisher_storage_path();
        if (!is_file($path)) {
            return ['updated_at' => '', 'drafts' => []];
        }

        $decoded = json_decode((string)file_get_contents($path), true);
        if (!is_array($decoded)) {
            return ['updated_at' => '', 'drafts' => []];
        }

        if (!is_array($decoded['drafts'] ?? null)) {
            $decoded['drafts'] = [];
        }

        return $decoded;
    }
}

if (!function_exists('seo_draft_publisher_write_state')) {
    function seo_draft_publisher_write_state(array $state): bool
    {
        $path = seo_draft_publisher_storage_path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $state['updated_at'] = date('Y-m-d H:i:s');
        $state['drafts'] = is_array($state['drafts'] ?? null) ? $state['drafts'] : [];

        return (bool)file_put_contents(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}

if (!function_exists('seo_draft_publisher_all_drafts')) {
    function seo_draft_publisher_all_drafts(): array
    {
        $state = seo_draft_publisher_read_state();
        $drafts = [];
        foreach ((array)($state['drafts'] ?? []) as $id => $draft) {
            if (!is_array($draft)) {
                continue;
            }
            $draft['id'] = (string)($draft['id'] ?? $id);
            $drafts[] = $draft;
        }

        usort($drafts, static function (array $a, array $b): int {
            return strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? ''));
        });

        return $drafts;
    }
}

if (!function_exists('seo_draft_publisher_find_draft')) {
    function seo_draft_publisher_find_draft(string $draftId): ?array
    {
        $draftId = trim($draftId);
        if ($draftId === '') {
            return null;
        }

        $state = seo_draft_publisher_read_state();
        $draft = $state['drafts'][$draftId] ?? null;
        if (!is_array($draft)) {
            return null;
        }

        $draft['id'] = $draftId;
        return $draft;
    }
}

if (!function_exists('seo_draft_publisher_find_by_task')) {
    function seo_draft_publisher_find_by_task(string $taskId): ?array
    {
        foreach (seo_draft_publisher_all_drafts() as $draft) {
            if ((string)($draft['task_id'] ?? '') === $taskId) {
                return $draft;
            }
        }
        return null;
    }
}

if (!function_exists('seo_draft_publisher_find_task')) {
    function seo_draft_publisher_find_task(string $taskId): ?array
    {
        $taskId = trim($taskId);
        if ($taskId === '' || !function_exists('seo_execution_tasks')) {
            return null;
        }

        foreach (seo_execution_tasks() as $task) {
            if ((string)($task['id'] ?? '') === $taskId) {
                return $task;
            }
        }
        return null;
    }
}

if (!function_exists('seo_draft_publisher_faq_json')) {
    function seo_draft_publisher_faq_json(array $questions): string
    {
        $items = [];
        foreach (array_slice($questions, 0, 6) as $question) {
            $question = trim((string)$question);
            if ($question === '') {
                continue;
            }
            $items[] = [
                'question' => $question,
                'answer' => 'Lengkapi jawaban singkat, jelas, dan relevan sebelum artikel dipublish.',
            ];
        }

        return $items ? json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
    }
}

if (!function_exists('seo_draft_publisher_body_html')) {
    function seo_draft_publisher_body_html(array $task): string
    {
        $html = function_exists('seo_execution_article_draft_html') ? seo_execution_article_draft_html($task) : '';
        $targetUrl = trim((string)($task['target_url'] ?? ''));
        $targetTitle = trim((string)($task['target_title'] ?? 'halaman target'));
        $anchor = trim((string)($task['internal_link_anchor'] ?? $targetTitle));

        if ($targetUrl !== '') {
            $html .= '<p><strong>Internal link wajib:</strong> Tambahkan link natural ke <a href="' . esc($targetUrl) . '">' . esc($anchor !== '' ? $anchor : $targetTitle) . '</a> agar artikel ini menguatkan halaman conversion.</p>';
        }

        $html .= '<p><em>Checklist sebelum publish:</em> cek ulang data bisnis, harga, area layanan, gambar, alt text, testimoni, dan CTA agar artikel siap mendorong lead/WhatsApp/checkout.</p>';
        return $html;
    }
}

if (!function_exists('seo_draft_publisher_payload_from_task')) {
    function seo_draft_publisher_payload_from_task(array $task): array
    {
        $title = trim((string)($task['title'] ?? 'Draft Artikel SEO'));
        $slug = trim((string)($task['suggested_slug'] ?? (function_exists('slugify') ? slugify($title) : strtolower(str_replace(' ', '-', $title)))));
        $metaTitle = trim((string)($task['meta_title_template'] ?? $title));
        $metaDescription = trim((string)($task['meta_description_template'] ?? ($task['brief_note'] ?? 'Artikel SEO pendukung untuk meningkatkan trust, traffic, dan conversion.')));
        $keywords = array_values(array_filter(array_map('trim', array_map('strval', (array)($task['keyword_seed'] ?? [])))));
        $mainKeyword = trim((string)($keywords[0] ?? $task['internal_link_anchor'] ?? $task['target_title'] ?? ''));
        $body = seo_draft_publisher_body_html($task);

        return [
            'title' => $title,
            'slug' => $slug,
            'category' => 'Marketing & SEO',
            'excerpt' => limit_chars(strip_tags($metaDescription), 155),
            'content' => $body,
            'image' => asset('images/default-article.jpg'),
            'image_alt' => $title,
            'image_title' => $title,
            'author' => SITE_NAME,
            'published_at' => date('Y-m-d H:i:s'),
            'featured' => false,
            'keywords' => implode(', ', $keywords),
            'meta_title' => limit_chars($metaTitle, 180),
            'meta_description' => limit_chars($metaDescription, 255),
            'meta_keywords' => implode(', ', $keywords),
            'canonical_url' => '',
            'og_title' => limit_chars($metaTitle, 180),
            'og_description' => limit_chars($metaDescription, 255),
            'focus_keyword' => $mainKeyword,
            'robots' => 'index, follow',
            'breadcrumb_title' => $title,
            'schema_type' => 'Article',
            'faq_json' => seo_draft_publisher_faq_json((array)($task['faq_questions'] ?? [])),
            'whatsapp_label' => 'Chat WhatsApp',
            'whatsapp_phone' => '',
            'whatsapp_text' => trim('Halo admin, saya membaca artikel ' . $title . ' dan ingin konsultasi.'),
            'source' => 'seo-draft',
            'seo_task_id' => (string)($task['id'] ?? ''),
            'seo_target_title' => (string)($task['target_title'] ?? ''),
            'seo_target_url' => (string)($task['target_url'] ?? ''),
            'seo_internal_anchor' => (string)($task['internal_link_anchor'] ?? ''),
        ];
    }
}

if (!function_exists('seo_draft_publisher_create_or_refresh')) {
    function seo_draft_publisher_create_or_refresh(array $task): array
    {
        $taskId = (string)($task['id'] ?? '');
        if ($taskId === '') {
            return ['ok' => false, 'message' => 'Task SEO tidak valid.', 'draft' => null];
        }

        $payload = seo_draft_publisher_payload_from_task($task);
        $existing = seo_draft_publisher_find_by_task($taskId);
        $draftId = (string)($existing['id'] ?? ('draft-' . (function_exists('slugify') ? slugify($taskId) : preg_replace('/[^a-z0-9]+/i', '-', $taskId))));
        $now = date('Y-m-d H:i:s');

        $draft = array_merge($existing ?: [], $payload, [
            'id' => $draftId,
            'task_id' => $taskId,
            'status' => (string)($existing['status'] ?? 'draft'),
            'article_id' => (int)($existing['article_id'] ?? 0),
            'article_url' => (string)($existing['article_url'] ?? ''),
            'created_at' => (string)($existing['created_at'] ?? $now),
            'updated_at' => $now,
        ]);

        $state = seo_draft_publisher_read_state();
        $state['drafts'][$draftId] = $draft;
        $ok = seo_draft_publisher_write_state($state);

        if ($ok && function_exists('seo_execution_save_task_state')) {
            seo_execution_save_task_state($taskId, [
                'status' => in_array((string)($task['status'] ?? ''), ['todo'], true) ? 'writing' : (string)($task['status'] ?? 'writing'),
                'owner' => (string)($task['owner'] ?? ''),
                'due_date' => (string)($task['due_date'] ?? ''),
                'note' => trim((string)($task['note'] ?? '') . "\nDraft artikel dibuat/di-refresh: " . $draftId),
            ]);
        }

        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('create', 'seo_article_draft', null, 'Draft artikel SEO dibuat/di-refresh.', ['draft_id' => $draftId, 'task_id' => $taskId]);
        }

        return ['ok' => $ok, 'message' => $ok ? 'Draft artikel berhasil disiapkan.' : 'Gagal menyimpan draft artikel.', 'draft' => $draft];
    }
}

if (!function_exists('seo_draft_publisher_link_article')) {
    function seo_draft_publisher_link_article(string $draftId, int $articleId, string $status = 'article_created'): bool
    {
        $draftId = trim($draftId);
        if ($draftId === '' || $articleId <= 0) {
            return false;
        }

        $state = seo_draft_publisher_read_state();
        if (!is_array($state['drafts'][$draftId] ?? null)) {
            return false;
        }

        $draft = (array)$state['drafts'][$draftId];
        $article = function_exists('article_admin_find') ? article_admin_find($articleId) : null;
        $draft['article_id'] = $articleId;
        $draft['article_url'] = $article ? article_url((string)($article['slug'] ?? '')) : '';
        $draft['status'] = $status;
        $draft['updated_at'] = date('Y-m-d H:i:s');
        $state['drafts'][$draftId] = $draft;

        $ok = seo_draft_publisher_write_state($state);
        $taskId = (string)($draft['task_id'] ?? '');
        if ($ok && $taskId !== '' && function_exists('seo_execution_save_task_state')) {
            seo_execution_save_task_state($taskId, [
                'status' => 'published',
                'owner' => '',
                'due_date' => '',
                'note' => 'Artikel dibuat dari SEO Draft Publisher. Article ID: ' . $articleId,
            ]);
        }

        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('create', 'seo_draft_article', $articleId, 'Artikel dibuat dari SEO Draft Publisher.', ['draft_id' => $draftId, 'task_id' => $taskId]);
        }

        return $ok;
    }
}

if (!function_exists('seo_draft_publisher_article_prefill')) {
    function seo_draft_publisher_article_prefill(string $draftId): ?array
    {
        $draft = seo_draft_publisher_find_draft($draftId);
        if (!$draft) {
            return null;
        }

        $draft['id'] = 0;
        $draft['_seo_draft_id'] = $draftId;
        $draft['_seo_task_id'] = (string)($draft['task_id'] ?? '');
        return $draft;
    }
}

if (!function_exists('seo_draft_publisher_metrics')) {
    function seo_draft_publisher_metrics(array $drafts, array $tasks): array
    {
        $created = count($drafts);
        $linked = 0;
        foreach ($drafts as $draft) {
            if ((int)($draft['article_id'] ?? 0) > 0) {
                $linked++;
            }
        }

        return [
            'tasks' => count($tasks),
            'drafts' => $created,
            'linked_articles' => $linked,
            'remaining' => max(0, count($tasks) - $created),
            'conversion_percent' => count($tasks) > 0 ? (int)round(($created / count($tasks)) * 100) : 0,
        ];
    }
}

if (!function_exists('seo_draft_publisher_filtered_tasks')) {
    function seo_draft_publisher_filtered_tasks(array $tasks, string $q = '', string $status = 'all'): array
    {
        $q = trim($q);
        $needle = $q !== '' ? (function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q)) : '';

        return array_values(array_filter($tasks, static function (array $task) use ($needle, $status): bool {
            if ($status !== 'all' && (string)($task['status'] ?? 'todo') !== $status) {
                return false;
            }
            if ($needle !== '') {
                $haystack = implode(' ', array_map('strval', [
                    $task['title'] ?? '', $task['target_title'] ?? '', $task['suggested_slug'] ?? '',
                    $task['internal_link_anchor'] ?? '', implode(' ', (array)($task['keyword_seed'] ?? [])),
                ]));
                $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
                return str_contains($haystack, $needle);
            }
            return true;
        }));
    }
}
