<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO CONTENT REFRESH PLANNER
|--------------------------------------------------------------------------
| Turns existing SEO, Content Performance, Lead Tracking, Money Page,
| Internal Link, and CTA signals into a refresh queue for older or weakening
| content. This is not a new tracking system; it reads existing data and
| helps admins decide which article/page should be refreshed first.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_refresh_clean')) {
    function seo_refresh_clean(mixed $value, int $max = 220): string
    {
        if (function_exists('link_cta_clean')) {
            return link_cta_clean($value, $max);
        }
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

if (!function_exists('seo_refresh_id')) {
    function seo_refresh_id(string $value = ''): string
    {
        if (function_exists('link_cta_id')) {
            return link_cta_id($value);
        }
        if (function_exists('seo_money_id')) {
            return seo_money_id($value);
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');

        return substr($value, 0, 140);
    }
}

if (!function_exists('seo_refresh_storage_file')) {
    function seo_refresh_storage_file(): string
    {
        return STORAGE_PATH . '/seo-content-refresh-planner.json';
    }
}

if (!function_exists('seo_refresh_status_options')) {
    function seo_refresh_status_options(): array
    {
        return [
            'queued' => 'Masuk antrean refresh',
            'researching' => 'Riset update konten',
            'refreshing' => 'Sedang direfresh',
            'published' => 'Refresh sudah publish',
            'monitoring' => 'Pantau hasil',
            'hold' => 'Tahan dulu',
        ];
    }
}

if (!function_exists('seo_refresh_filter_options')) {
    function seo_refresh_filter_options(): array
    {
        return [
            'all' => 'Semua Status',
            'open' => 'Belum Selesai',
            'queued' => 'Antrean',
            'researching' => 'Riset',
            'refreshing' => 'Sedang Refresh',
            'published' => 'Sudah Publish',
            'monitoring' => 'Monitoring',
            'hold' => 'Ditahan',
        ];
    }
}

if (!function_exists('seo_refresh_priority_options')) {
    function seo_refresh_priority_options(): array
    {
        return [
            'all' => 'Semua Prioritas',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ];
    }
}

if (!function_exists('seo_refresh_reason_options')) {
    function seo_refresh_reason_options(): array
    {
        return [
            'all' => 'Semua Alasan',
            'stale_winner' => 'Konten lama masih punya sinyal',
            'content_decay' => 'Konten mulai melemah',
            'seo_gap' => 'SEO dasar perlu dipoles',
            'conversion_gap' => 'CTA/lead/order belum optimal',
            'evergreen_update' => 'Butuh update evergreen',
        ];
    }
}

if (!function_exists('seo_refresh_default_settings')) {
    function seo_refresh_default_settings(): array
    {
        return [
            'items' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_refresh_normalize_state')) {
    function seo_refresh_normalize_state(array $state): array
    {
        $options = seo_refresh_status_options();
        $status = (string)($state['status'] ?? 'queued');
        if (!isset($options[$status])) {
            $status = 'queued';
        }

        return [
            'page_id' => seo_refresh_id((string)($state['page_id'] ?? '')),
            'status' => $status,
            'note' => seo_refresh_clean($state['note'] ?? '', 700),
            'last_refreshed_at' => seo_refresh_clean($state['last_refreshed_at'] ?? '', 80),
            'updated_at' => seo_refresh_clean($state['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_refresh_normalize_settings')) {
    function seo_refresh_normalize_settings(array $settings): array
    {
        $settings = array_merge(seo_refresh_default_settings(), $settings);
        $items = [];

        foreach ((array)($settings['items'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $normalized = seo_refresh_normalize_state($state);
            if ((string)$normalized['page_id'] === '') {
                continue;
            }
            $items[(string)$normalized['page_id']] = $normalized;
        }

        return [
            'items' => $items,
            'updated_at' => seo_refresh_clean($settings['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_refresh_settings')) {
    function seo_refresh_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = seo_refresh_storage_file();
        if (!is_file($file)) {
            $cached = seo_refresh_normalize_settings(seo_refresh_default_settings());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = seo_refresh_normalize_settings(seo_refresh_default_settings());
            return $cached;
        }

        $cached = seo_refresh_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('seo_refresh_write_settings')) {
    function seo_refresh_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = seo_refresh_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(seo_refresh_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan SEO Content Refresh Planner belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(seo_refresh_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'seo-content-refresh-planner', null, 'Menyimpan status SEO Content Refresh Planner.');
        }

        return true;
    }
}

if (!function_exists('seo_refresh_update_item')) {
    function seo_refresh_update_item(string $pageId, string $status, string $note = '', string $lastRefreshedAt = ''): bool
    {
        $pageId = seo_refresh_id($pageId);
        if ($pageId === '') {
            throw new RuntimeException('ID halaman refresh tidak valid.');
        }

        $options = seo_refresh_status_options();
        if (!isset($options[$status])) {
            $status = 'queued';
        }

        $settings = seo_refresh_settings(true);
        $settings['items'][$pageId] = seo_refresh_normalize_state([
            'page_id' => $pageId,
            'status' => $status,
            'note' => $note,
            'last_refreshed_at' => $lastRefreshedAt,
            'updated_at' => date(DATE_ATOM),
        ]);

        return seo_refresh_write_settings($settings, true);
    }
}

if (!function_exists('seo_refresh_reset_page')) {
    function seo_refresh_reset_page(string $pageId): void
    {
        $pageId = seo_refresh_id($pageId);
        if ($pageId === '') {
            return;
        }
        $settings = seo_refresh_settings(true);
        unset($settings['items'][$pageId]);
        seo_refresh_write_settings($settings, true);
    }
}

if (!function_exists('seo_refresh_reset_all')) {
    function seo_refresh_reset_all(): void
    {
        if (is_file(seo_refresh_storage_file())) {
            @unlink(seo_refresh_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'seo-content-refresh-planner', null, 'Reset catatan SEO Content Refresh Planner.');
        }
    }
}

if (!function_exists('seo_refresh_status_matches')) {
    function seo_refresh_status_matches(string $itemStatus, string $filter): bool
    {
        if ($filter === 'all') {
            return true;
        }
        if ($filter === 'open') {
            return !in_array($itemStatus, ['published', 'monitoring'], true);
        }
        return $itemStatus === $filter;
    }
}

if (!function_exists('seo_refresh_normalize_path')) {
    function seo_refresh_normalize_path(string $url): string
    {
        if (function_exists('content_performance_normalize_path')) {
            return content_performance_normalize_path($url);
        }
        if (function_exists('seo_profit_normalize_path')) {
            return seo_profit_normalize_path($url);
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?? $url);
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim(preg_replace('#/+#', '/', $path) ?: $path, '/');
    }
}

if (!function_exists('seo_refresh_type_label')) {
    function seo_refresh_type_label(string $type): string
    {
        if (function_exists('universal_seo_type_label')) {
            return universal_seo_type_label($type);
        }

        return match ($type) {
            'article' => 'Artikel',
            'landing_page' => 'Landing Page',
            'seo_landing' => 'SEO Landing',
            'product' => 'Produk',
            'service' => 'Layanan',
            'portfolio' => 'Portfolio',
            'static_page' => 'Halaman',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }
}

if (!function_exists('seo_refresh_type_options')) {
    function seo_refresh_type_options(): array
    {
        $options = function_exists('seo_profit_type_options') ? seo_profit_type_options() : [
            'all' => 'Semua SEO Page',
            'article' => 'Artikel',
            'landing_page' => 'Landing Page',
            'seo_landing' => 'SEO Landing',
            'product' => 'Produk',
            'service' => 'Layanan',
            'static_page' => 'Halaman',
        ];

        if (!isset($options['all'])) {
            $options = ['all' => 'Semua SEO Page'] + $options;
        }

        return $options;
    }
}

if (!function_exists('seo_refresh_content_dates')) {
    function seo_refresh_content_dates(): array
    {
        $dates = [];

        foreach (function_exists('all_articles') ? all_articles() : [] as $article) {
            if (!is_array($article)) {
                continue;
            }
            $slug = seo_refresh_clean($article['slug'] ?? '', 120);
            if ($slug === '') {
                continue;
            }
            $path = seo_refresh_normalize_path(function_exists('article_url') ? article_url($slug) : url('artikel/' . $slug));
            $updated = seo_refresh_clean($article['updated_at'] ?? $article['published_at'] ?? '', 80);
            $published = seo_refresh_clean($article['published_at'] ?? $updated, 80);
            $dates[$path] = [
                'updated_at' => $updated,
                'published_at' => $published,
                'source' => 'article',
            ];
        }

        foreach (function_exists('landing_page_all') ? landing_page_all(true) : [] as $page) {
            if (!is_array($page)) {
                continue;
            }
            $slug = seo_refresh_clean($page['slug'] ?? '', 120);
            if ($slug === '') {
                continue;
            }
            $path = seo_refresh_normalize_path(function_exists('landing_page_url') ? landing_page_url($slug) : url('landing/' . $slug));
            $updated = seo_refresh_clean($page['updated_at'] ?? $page['published_at'] ?? $page['created_at'] ?? '', 80);
            $dates[$path] = [
                'updated_at' => $updated,
                'published_at' => seo_refresh_clean($page['published_at'] ?? $page['created_at'] ?? $updated, 80),
                'source' => 'landing_page',
            ];
        }

        foreach (function_exists('all_products') ? all_products() : [] as $product) {
            if (!is_array($product)) {
                continue;
            }
            $slug = seo_refresh_clean($product['slug'] ?? '', 120);
            if ($slug === '') {
                continue;
            }
            $path = seo_refresh_normalize_path(function_exists('product_url') ? product_url($slug) : url('produk/' . $slug));
            $updated = seo_refresh_clean($product['updated_at'] ?? $product['created_at'] ?? '', 80);
            if ($updated !== '') {
                $dates[$path] = [
                    'updated_at' => $updated,
                    'published_at' => seo_refresh_clean($product['created_at'] ?? $updated, 80),
                    'source' => 'product',
                ];
            }
        }

        return $dates;
    }
}

if (!function_exists('seo_refresh_age_days')) {
    function seo_refresh_age_days(string $dateValue): ?int
    {
        $time = strtotime($dateValue);
        if ($time === false || $time <= 0) {
            return null;
        }

        return max(0, (int)floor((time() - $time) / 86400));
    }
}

if (!function_exists('seo_refresh_issue_fields')) {
    function seo_refresh_issue_fields(array $issues): array
    {
        $fields = [];
        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $field = strtolower((string)($issue['field'] ?? 'other'));
            if ($field !== '') {
                $fields[$field] = true;
            }
        }
        return array_keys($fields);
    }
}

if (!function_exists('seo_refresh_reason_for_item')) {
    function seo_refresh_reason_for_item(array $row, ?int $ageDays): array
    {
        $metrics = (array)($row['metrics'] ?? []);
        $score = (int)($row['seo_score'] ?? $row['score'] ?? 0);
        $interactions = (int)($metrics['interactions'] ?? 0);
        $intent = (int)($metrics['high_intent'] ?? 0) + (int)($metrics['whatsapp'] ?? 0) + (int)($metrics['inquiries'] ?? 0);
        $orders = (int)($metrics['orders'] ?? 0);
        $fields = seo_refresh_issue_fields((array)($row['issues'] ?? []));

        if (($ageDays !== null && $ageDays >= 90) && ($intent > 0 || $orders > 0 || $interactions >= 2)) {
            return ['key' => 'stale_winner', 'label' => 'Konten lama masih punya sinyal', 'note' => 'Konten sudah cukup lama tetapi masih punya sinyal klik/lead/order. Refresh bisa membantu menjaga dan menaikkan performa.'];
        }
        if (($ageDays !== null && $ageDays >= 120) && $interactions === 0) {
            return ['key' => 'content_decay', 'label' => 'Konten mulai melemah', 'note' => 'Konten sudah lama dan belum punya sinyal baru. Perlu dihidupkan ulang dengan update angle, CTA, dan internal link.'];
        }
        if ($score < 80 || array_intersect($fields, ['meta_title', 'meta_description', 'content', 'image', 'image_alt', 'schema', 'internal_links'])) {
            return ['key' => 'seo_gap', 'label' => 'SEO dasar perlu dipoles', 'note' => 'Ada issue SEO yang bisa diperbaiki: meta, konten, gambar, schema, atau internal link.'];
        }
        if ($interactions > 0 && $intent === 0) {
            return ['key' => 'conversion_gap', 'label' => 'CTA/lead belum optimal', 'note' => 'Halaman punya interaksi tetapi belum cukup mendorong WhatsApp, form, atau order. Refresh fokus ke offer dan CTA.'];
        }
        if ($ageDays === null || $ageDays >= 60) {
            return ['key' => 'evergreen_update', 'label' => 'Butuh update evergreen', 'note' => 'Konten layak dicek berkala agar tetap relevan, fresh, dan lebih siap menghasilkan lead.'];
        }

        return ['key' => 'evergreen_update', 'label' => 'Monitoring refresh', 'note' => 'Konten relatif aman, tetapi tetap bisa diberi update kecil jika masuk campaign.'];
    }
}

if (!function_exists('seo_refresh_priority_for_item')) {
    function seo_refresh_priority_for_item(array $row, ?int $ageDays, array $reason): string
    {
        $metrics = (array)($row['metrics'] ?? []);
        $score = (int)($row['seo_score'] ?? $row['score'] ?? 0);
        $interactions = (int)($metrics['interactions'] ?? 0);
        $intent = (int)($metrics['high_intent'] ?? 0) + (int)($metrics['whatsapp'] ?? 0) + (int)($metrics['inquiries'] ?? 0) + (int)($metrics['orders'] ?? 0);
        $reasonKey = (string)($reason['key'] ?? 'evergreen_update');

        if (in_array($reasonKey, ['stale_winner', 'conversion_gap'], true) && ($intent > 0 || $interactions >= 2)) {
            return 'high';
        }
        if (($ageDays !== null && $ageDays >= 180) || $score < 70) {
            return 'high';
        }
        if (($ageDays !== null && $ageDays >= 90) || $score < 85 || $interactions > 0) {
            return 'medium';
        }
        return 'low';
    }
}

if (!function_exists('seo_refresh_score_for_item')) {
    function seo_refresh_score_for_item(array $row, ?int $ageDays, array $reason, string $priority): int
    {
        $metrics = (array)($row['metrics'] ?? []);
        $score = 0;
        $score += min(28, max(0, (int)round((($ageDays ?? 45) / 180) * 28)));
        $score += min(18, max(0, 95 - (int)($row['seo_score'] ?? $row['score'] ?? 80)));
        $score += min(20, (int)($metrics['interactions'] ?? 0) * 4);
        $score += min(20, ((int)($metrics['high_intent'] ?? 0) + (int)($metrics['whatsapp'] ?? 0) + (int)($metrics['inquiries'] ?? 0) + (int)($metrics['orders'] ?? 0)) * 7);
        $score += match ((string)($reason['key'] ?? '')) {
            'stale_winner' => 12,
            'conversion_gap' => 10,
            'seo_gap' => 8,
            'content_decay' => 8,
            default => 4,
        };
        $score += match ($priority) {
            'high' => 8,
            'medium' => 4,
            default => 0,
        };

        return max(0, min(100, $score));
    }
}

if (!function_exists('seo_refresh_tasks_for_item')) {
    function seo_refresh_tasks_for_item(array $row, ?int $ageDays, array $reason): array
    {
        $fields = seo_refresh_issue_fields((array)($row['issues'] ?? []));
        $metrics = (array)($row['metrics'] ?? []);
        $tasks = [];

        if (array_intersect($fields, ['meta_title', 'meta_description']) || (int)($row['seo_score'] ?? 0) < 85) {
            $tasks[] = ['key' => 'meta', 'label' => 'Update meta title & description', 'detail' => 'Buat snippet lebih klik-able: manfaat utama, konteks bisnis/lokasi, dan CTA ringan.'];
        }
        if (array_intersect($fields, ['content']) || ($ageDays !== null && $ageDays >= 90)) {
            $tasks[] = ['key' => 'content', 'label' => 'Refresh isi konten', 'detail' => 'Tambahkan update terbaru, contoh kasus, benefit, perbandingan, dan jawaban keberatan calon pembeli.'];
        }
        if (array_intersect($fields, ['internal_links']) || function_exists('link_cta_summary')) {
            $tasks[] = ['key' => 'internal_link', 'label' => 'Tambah internal link', 'detail' => 'Arahkan pembaca ke produk, layanan, landing page, form, atau offer yang paling relevan.'];
        }
        if (((int)($metrics['interactions'] ?? 0) > 0 && (int)($metrics['high_intent'] ?? 0) === 0) || in_array((string)($reason['key'] ?? ''), ['conversion_gap', 'stale_winner'], true)) {
            $tasks[] = ['key' => 'cta_offer', 'label' => 'Perkuat CTA & offer', 'detail' => 'Pasang CTA di atas/tengah/bawah konten, gunakan winner Offer Lab bila tersedia, dan buat next step jelas.'];
        }
        if (array_intersect($fields, ['image', 'image_alt'])) {
            $tasks[] = ['key' => 'image', 'label' => 'Update gambar & alt text', 'detail' => 'Gunakan gambar yang lebih spesifik untuk bisnis dan isi alt text natural sesuai konteks halaman.'];
        }
        if (array_intersect($fields, ['schema']) || (string)($row['type'] ?? '') === 'article') {
            $tasks[] = ['key' => 'schema_faq', 'label' => 'Tambah FAQ/schema pendukung', 'detail' => 'Tambahkan FAQ praktis dan pastikan schema sesuai tipe halaman agar Google lebih mudah memahami konten.'];
        }
        $tasks[] = ['key' => 'monitor', 'label' => 'Pantau hasil setelah refresh', 'detail' => 'Setelah publish, pantau perubahan klik, lead, dan order melalui Lead Tracking, SEO Journey, dan CTA Result.'];

        $seen = [];
        return array_values(array_filter($tasks, static function (array $task) use (&$seen): bool {
            $key = (string)($task['key'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));
    }
}

if (!function_exists('seo_refresh_merge_money_signal')) {
    function seo_refresh_merge_money_signal(array $row, array $moneyByPath): array
    {
        $path = seo_refresh_normalize_path((string)($row['url'] ?? ''));
        if ($path === '' || !isset($moneyByPath[$path])) {
            return $row;
        }

        $money = (array)$moneyByPath[$path];
        $row['money_score'] = (int)($money['money_score'] ?? 0);
        $row['money_priority'] = (string)($money['priority'] ?? '');
        $row['money_stage'] = (array)($money['stage'] ?? []);
        $row['cta_plan'] = (array)($money['cta_plan'] ?? []);
        $row['trust_plan'] = (array)($money['trust_plan'] ?? []);

        return $row;
    }
}

if (!function_exists('seo_refresh_candidates')) {
    function seo_refresh_candidates(int $days = 90): array
    {
        $days = max(7, min(365, $days));
        $contentSummary = function_exists('content_performance_summary') ? content_performance_summary($days) : ['rows' => []];
        $rows = (array)($contentSummary['rows'] ?? []);
        $dates = seo_refresh_content_dates();

        $moneyByPath = [];
        if (function_exists('seo_money_summary')) {
            foreach ((array)(seo_money_summary($days, 'all', 'all')['items'] ?? []) as $moneyItem) {
                if (!is_array($moneyItem)) {
                    continue;
                }
                $item = (array)($moneyItem['item'] ?? []);
                $path = seo_refresh_normalize_path((string)($item['url'] ?? ''));
                if ($path !== '') {
                    $moneyByPath[$path] = $moneyItem;
                }
            }
        }

        $items = [];
        $states = (array)(seo_refresh_settings(true)['items'] ?? []);

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = (string)($row['type'] ?? 'static_page');
            if (!in_array($type, ['article', 'landing_page', 'seo_landing', 'product', 'service', 'static_page', 'portfolio', 'homepage'], true)) {
                continue;
            }

            $path = seo_refresh_normalize_path((string)($row['url'] ?? ''));
            $dateInfo = (array)($dates[$path] ?? []);
            $updatedAt = seo_refresh_clean($dateInfo['updated_at'] ?? $row['updated_at'] ?? $row['latest_event_at'] ?? '', 80);
            $publishedAt = seo_refresh_clean($dateInfo['published_at'] ?? $row['published_at'] ?? '', 80);
            $ageDays = seo_refresh_age_days($updatedAt);
            $row = seo_refresh_merge_money_signal($row, $moneyByPath);
            $reason = seo_refresh_reason_for_item($row, $ageDays);
            $priority = seo_refresh_priority_for_item($row, $ageDays, $reason);
            $pageId = seo_refresh_id((string)($row['id'] ?? (($type ?: 'page') . '-' . $path)));
            if ($pageId === '') {
                $pageId = seo_refresh_id($type . '-' . $path);
            }
            $state = (array)($states[$pageId] ?? []);
            $status = (string)($state['status'] ?? 'queued');
            $score = seo_refresh_score_for_item($row, $ageDays, $reason, $priority);
            $metrics = (array)($row['metrics'] ?? []);
            $tasks = seo_refresh_tasks_for_item($row, $ageDays, $reason);

            $items[] = [
                'page_id' => $pageId,
                'type' => $type,
                'type_label' => seo_refresh_type_label($type),
                'title' => seo_refresh_clean($row['title'] ?? 'Halaman SEO', 150),
                'url' => seo_refresh_clean($row['url'] ?? '', 240),
                'path' => $path,
                'edit_url' => seo_refresh_clean($row['edit_url'] ?? '', 240),
                'updated_at' => $updatedAt,
                'published_at' => $publishedAt,
                'age_days' => $ageDays,
                'freshness_label' => $ageDays === null ? 'Tanggal update belum terbaca' : ($ageDays . ' hari sejak update'),
                'seo_score' => (int)($row['seo_score'] ?? $row['score'] ?? 0),
                'performance_score' => (int)($row['performance_score'] ?? 0),
                'refresh_score' => $score,
                'priority' => $priority,
                'reason' => $reason,
                'metrics' => $metrics,
                'issues' => array_slice((array)($row['issues'] ?? []), 0, 6),
                'tasks' => $tasks,
                'status' => $status,
                'state' => $state,
                'note' => seo_refresh_clean($state['note'] ?? '', 700),
                'last_refreshed_at' => seo_refresh_clean($state['last_refreshed_at'] ?? '', 80),
                'recommendation' => seo_refresh_clean($row['recommendation'] ?? '', 360),
                'money_score' => (int)($row['money_score'] ?? 0),
                'money_priority' => (string)($row['money_priority'] ?? ''),
                'cta_plan' => (array)($row['cta_plan'] ?? []),
                'trust_plan' => (array)($row['trust_plan'] ?? []),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            $weights = ['high' => 3, 'medium' => 2, 'low' => 1];
            return (($weights[(string)($b['priority'] ?? 'low')] ?? 0) <=> ($weights[(string)($a['priority'] ?? 'low')] ?? 0))
                ?: ((int)($b['refresh_score'] ?? 0) <=> (int)($a['refresh_score'] ?? 0))
                ?: ((int)($b['money_score'] ?? 0) <=> (int)($a['money_score'] ?? 0))
                ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        return $items;
    }
}

if (!function_exists('seo_refresh_summary')) {
    function seo_refresh_summary(int $days = 90, string $type = 'all', string $priority = 'all', string $reason = 'all', string $status = 'open'): array
    {
        $days = max(7, min(365, $days));
        $typeOptions = seo_refresh_type_options();
        if (!isset($typeOptions[$type])) {
            $type = 'all';
        }
        $priorityOptions = seo_refresh_priority_options();
        if (!isset($priorityOptions[$priority])) {
            $priority = 'all';
        }
        $reasonOptions = seo_refresh_reason_options();
        if (!isset($reasonOptions[$reason])) {
            $reason = 'all';
        }
        $statusOptions = seo_refresh_filter_options();
        if (!isset($statusOptions[$status])) {
            $status = 'open';
        }

        $items = [];
        $allItems = seo_refresh_candidates($days);
        $counts = [
            'total' => 0,
            'visible' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'queued' => 0,
            'researching' => 0,
            'refreshing' => 0,
            'published' => 0,
            'monitoring' => 0,
            'hold' => 0,
            'articles' => 0,
            'old_content' => 0,
            'content_decay' => 0,
            'stale_winner' => 0,
        ];
        $scoreSum = 0;

        foreach ($allItems as $item) {
            $counts['total']++;
            $p = (string)($item['priority'] ?? 'low');
            if (isset($counts[$p])) {
                $counts[$p]++;
            }
            $s = (string)($item['status'] ?? 'queued');
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
            if ((string)($item['type'] ?? '') === 'article') {
                $counts['articles']++;
            }
            if (($item['age_days'] ?? null) !== null && (int)$item['age_days'] >= 90) {
                $counts['old_content']++;
            }
            $reasonKey = (string)($item['reason']['key'] ?? '');
            if (isset($counts[$reasonKey])) {
                $counts[$reasonKey]++;
            }
            $scoreSum += (int)($item['refresh_score'] ?? 0);

            if ($type !== 'all' && (string)($item['type'] ?? '') !== $type) {
                continue;
            }
            if ($priority !== 'all' && (string)($item['priority'] ?? '') !== $priority) {
                continue;
            }
            if ($reason !== 'all' && $reasonKey !== $reason) {
                continue;
            }
            if (!seo_refresh_status_matches((string)($item['status'] ?? 'queued'), $status)) {
                continue;
            }
            $items[] = $item;
        }

        $counts['visible'] = count($items);
        $averageScore = $counts['total'] > 0 ? (int)round($scoreSum / $counts['total']) : 0;
        $topItem = $items[0] ?? ($allItems[0] ?? null);
        $focus = 'Refresh konten lama yang paling dekat ke klik, lead, order, atau punya issue SEO paling jelas.';
        if ($counts['stale_winner'] > 0) {
            $focus = 'Ada konten lama yang masih punya sinyal. Refresh dulu agar peluang lead/order tidak turun.';
        } elseif ($counts['content_decay'] > 0) {
            $focus = 'Ada konten lama yang belum punya sinyal baru. Hidupkan ulang dengan angle baru, CTA, dan internal link.';
        } elseif ($counts['high'] > 0) {
            $focus = 'Prioritaskan halaman high karena paling dekat dengan perbaikan cepat.';
        }

        return [
            'generated_at' => date(DATE_ATOM),
            'days' => $days,
            'type' => $type,
            'priority' => $priority,
            'reason' => $reason,
            'status' => $status,
            'type_options' => $typeOptions,
            'priority_options' => $priorityOptions,
            'reason_options' => $reasonOptions,
            'status_options' => $statusOptions,
            'status_action_options' => seo_refresh_status_options(),
            'counts' => $counts,
            'average_refresh_score' => $averageScore,
            'top_focus' => $focus,
            'top_item' => $topItem,
            'items' => $items,
        ];
    }
}

if (!function_exists('seo_refresh_export_csv')) {
    function seo_refresh_export_csv(array $items): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="seo-content-refresh-planner-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['page_id', 'priority', 'status', 'reason', 'type', 'title', 'url', 'age_days', 'seo_score', 'performance_score', 'refresh_score', 'interactions', 'leads', 'orders', 'tasks', 'note']);
        foreach ($items as $item) {
            $metrics = (array)($item['metrics'] ?? []);
            fputcsv($out, [
                (string)($item['page_id'] ?? ''),
                (string)($item['priority'] ?? ''),
                (string)($item['status'] ?? ''),
                (string)($item['reason']['label'] ?? ''),
                (string)($item['type_label'] ?? ''),
                (string)($item['title'] ?? ''),
                (string)($item['url'] ?? ''),
                $item['age_days'] === null ? '' : (int)$item['age_days'],
                (int)($item['seo_score'] ?? 0),
                (int)($item['performance_score'] ?? 0),
                (int)($item['refresh_score'] ?? 0),
                (int)($metrics['interactions'] ?? 0),
                (int)(($metrics['inquiries'] ?? 0) + ($metrics['whatsapp'] ?? 0)),
                (int)($metrics['orders'] ?? 0),
                implode(' | ', array_map(static fn(array $task): string => (string)($task['label'] ?? ''), (array)($item['tasks'] ?? []))),
                (string)($item['note'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
