<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| INTERNAL LINK & CTA INJECTION ASSISTANT
|--------------------------------------------------------------------------
| Execution bridge for SEO money pages. It does not create a new tracking
| system. It reads Money Page Optimizer, CTA plans, internal link targets,
| and existing SEO/Lead Tracking signals, then turns them into placement
| recommendations that admins can mark as applied/deferred.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('link_cta_clean')) {
    function link_cta_clean(mixed $value, int $max = 220): string
    {
        if (function_exists('money_deploy_clean')) {
            return money_deploy_clean($value, $max);
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('link_cta_id')) {
    function link_cta_id(string $value = ''): string
    {
        if (function_exists('money_deploy_id')) {
            return money_deploy_id($value);
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');

        return substr($value, 0, 140);
    }
}

if (!function_exists('link_cta_storage_file')) {
    function link_cta_storage_file(): string
    {
        return STORAGE_PATH . '/internal-link-cta-injection-assistant.json';
    }
}

if (!function_exists('link_cta_status_options')) {
    function link_cta_status_options(): array
    {
        return [
            'pending' => 'Belum dipasang',
            'reviewing' => 'Sedang disiapkan',
            'applied' => 'Sudah dipasang',
            'monitoring' => 'Pantau hasil',
            'deferred' => 'Tunda dulu',
        ];
    }
}

if (!function_exists('link_cta_filter_options')) {
    function link_cta_filter_options(): array
    {
        return [
            'all' => 'Semua Status',
            'open' => 'Belum Selesai',
            'reviewing' => 'Sedang Disiapkan',
            'applied' => 'Sudah Dipasang',
            'monitoring' => 'Pantau Hasil',
            'deferred' => 'Ditunda',
        ];
    }
}

if (!function_exists('link_cta_default_settings')) {
    function link_cta_default_settings(): array
    {
        return [
            'recommendations' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('link_cta_normalize_state')) {
    function link_cta_normalize_state(array $state): array
    {
        $options = link_cta_status_options();
        $status = (string)($state['status'] ?? 'pending');
        if (!isset($options[$status])) {
            $status = 'pending';
        }

        return [
            'recommendation_id' => link_cta_id((string)($state['recommendation_id'] ?? '')),
            'page_id' => link_cta_id((string)($state['page_id'] ?? '')),
            'status' => $status,
            'note' => link_cta_clean($state['note'] ?? '', 560),
            'updated_at' => link_cta_clean($state['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('link_cta_normalize_settings')) {
    function link_cta_normalize_settings(array $settings): array
    {
        $settings = array_merge(link_cta_default_settings(), $settings);
        $recommendations = [];

        foreach ((array)($settings['recommendations'] ?? []) as $state) {
            if (!is_array($state)) {
                continue;
            }
            $normalized = link_cta_normalize_state($state);
            if ((string)$normalized['recommendation_id'] === '') {
                continue;
            }
            $recommendations[(string)$normalized['recommendation_id']] = $normalized;
        }

        return [
            'recommendations' => $recommendations,
            'updated_at' => link_cta_clean($settings['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('link_cta_settings')) {
    function link_cta_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = link_cta_storage_file();
        if (!is_file($file)) {
            $cached = link_cta_normalize_settings(link_cta_default_settings());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = link_cta_normalize_settings(link_cta_default_settings());
            return $cached;
        }

        $cached = link_cta_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('link_cta_write_settings')) {
    function link_cta_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = link_cta_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(link_cta_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan Internal Link & CTA belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(link_cta_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'internal-link-cta-injection-assistant', null, 'Menyimpan status rekomendasi Internal Link & CTA Injection Assistant.');
        }

        return true;
    }
}

if (!function_exists('link_cta_update_recommendation')) {
    function link_cta_update_recommendation(string $recommendationId, string $pageId, string $status, string $note = ''): bool
    {
        $recommendationId = link_cta_id($recommendationId);
        $pageId = link_cta_id($pageId);
        if ($recommendationId === '') {
            throw new RuntimeException('ID rekomendasi tidak valid.');
        }

        $options = link_cta_status_options();
        if (!isset($options[$status])) {
            $status = 'pending';
        }

        $settings = link_cta_settings(true);
        $settings['recommendations'][$recommendationId] = [
            'recommendation_id' => $recommendationId,
            'page_id' => $pageId,
            'status' => $status,
            'note' => link_cta_clean($note, 560),
            'updated_at' => date(DATE_ATOM),
        ];

        return link_cta_write_settings($settings, true);
    }
}

if (!function_exists('link_cta_reset_page')) {
    function link_cta_reset_page(string $pageId): bool
    {
        $pageId = link_cta_id($pageId);
        if ($pageId === '') {
            throw new RuntimeException('ID halaman tidak valid.');
        }

        $settings = link_cta_settings(true);
        foreach ((array)$settings['recommendations'] as $id => $state) {
            if ((string)($state['page_id'] ?? '') === $pageId) {
                unset($settings['recommendations'][$id]);
            }
        }

        return link_cta_write_settings($settings, true);
    }
}

if (!function_exists('link_cta_reset_all')) {
    function link_cta_reset_all(): void
    {
        if (is_file(link_cta_storage_file())) {
            @unlink(link_cta_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'internal-link-cta-injection-assistant', null, 'Reset semua catatan Internal Link & CTA Injection Assistant.');
        }
    }
}

if (!function_exists('link_cta_action_url')) {
    function link_cta_action_url(string $path): string
    {
        return function_exists('url') ? url($path) : '/' . ltrim($path, '/');
    }
}

if (!function_exists('link_cta_priority_weight')) {
    function link_cta_priority_weight(string $priority): int
    {
        return match ($priority) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }
}

if (!function_exists('link_cta_type_label')) {
    function link_cta_type_label(string $type): string
    {
        if (function_exists('universal_seo_type_label')) {
            return universal_seo_type_label($type);
        }
        if (function_exists('seo_profit_type_label')) {
            return seo_profit_type_label($type);
        }

        return ucwords(str_replace('_', ' ', $type ?: 'Halaman'));
    }
}

if (!function_exists('link_cta_recommendation_score')) {
    function link_cta_recommendation_score(array $optimizer, string $kind, int $targetScore = 0): int
    {
        $metrics = (array)($optimizer['metrics'] ?? []);
        $moneyScore = (int)($optimizer['money_score'] ?? 0);
        $bonus = ((int)($metrics['clicks'] ?? 0) * 2) + ((int)($metrics['leads'] ?? 0) * 4) + (((int)($metrics['orders'] ?? 0) + (int)($metrics['payments'] ?? 0)) * 6);

        if ($kind === 'cta') {
            $bonus += 12;
        }
        if ($kind === 'internal_link') {
            $bonus += max(4, (int)round($targetScore / 12));
        }

        return max(1, min(100, (int)round(($moneyScore * 0.72) + ($targetScore * 0.18) + $bonus)));
    }
}

if (!function_exists('link_cta_snippet_for_internal_link')) {
    function link_cta_snippet_for_internal_link(array $optimizer, array $target): string
    {
        $pageTitle = link_cta_clean($optimizer['item']['title'] ?? 'halaman ini', 90);
        $targetTitle = link_cta_clean($target['title'] ?? 'halaman tujuan', 90);
        $type = (string)($optimizer['item']['type'] ?? 'page');

        if ($type === 'article') {
            return 'Setelah pembahasan utama, tambahkan kalimat natural: “Kalau ingin lanjut ke solusi praktis, cek juga ' . $targetTitle . '.”';
        }
        if (in_array($type, ['product', 'service'], true)) {
            return 'Di bagian manfaat atau FAQ, arahkan pembaca ke konten pendukung: “Baca juga panduan ' . $targetTitle . ' agar pilihan Anda makin tepat.”';
        }

        return 'Sisipkan link dari “' . $pageTitle . '” menuju “' . $targetTitle . '” pada paragraf yang membahas kebutuhan, solusi, atau langkah berikutnya.';
    }
}

if (!function_exists('link_cta_snippet_for_cta')) {
    function link_cta_snippet_for_cta(array $optimizer): string
    {
        $cta = (array)($optimizer['cta_plan'] ?? []);
        $headline = link_cta_clean($cta['headline'] ?? 'Butuh rekomendasi yang paling cocok?', 110);
        $subheadline = link_cta_clean($cta['subheadline'] ?? 'Arahkan pembaca ke chat, form, atau katalog agar traffic SEO berubah menjadi prospek.', 190);
        $label = link_cta_clean($cta['cta_label'] ?? 'Tanya Rekomendasi', 60);

        return $headline . ' — ' . $subheadline . ' Tombol: ' . $label . '.';
    }
}

if (!function_exists('link_cta_recommendations_for_optimizer')) {
    function link_cta_recommendations_for_optimizer(array $optimizer, array $states): array
    {
        $page = (array)($optimizer['item'] ?? []);
        $pageId = link_cta_id((string)($optimizer['page_id'] ?? $page['page_id'] ?? ''));
        if ($pageId === '') {
            return [];
        }

        $pageTitle = link_cta_clean($page['title'] ?? 'Halaman SEO', 120);
        $pageType = (string)($page['type'] ?? 'page');
        $pageTypeLabel = link_cta_type_label($pageType);
        $pageEditUrl = link_cta_clean($page['edit_url'] ?? '', 220);
        $pageUrl = link_cta_clean($page['url'] ?? '', 220);
        $priority = (string)($optimizer['priority'] ?? 'low');
        if (!in_array($priority, ['high', 'medium', 'low'], true)) {
            $priority = 'low';
        }

        $recommendations = [];
        $links = array_slice((array)($optimizer['internal_links'] ?? []), 0, 3);
        foreach ($links as $index => $target) {
            if (!is_array($target)) {
                continue;
            }
            $targetTitle = link_cta_clean($target['title'] ?? '', 120);
            $targetUrl = link_cta_clean($target['url'] ?? '', 220);
            if ($targetTitle === '' && $targetUrl === '') {
                continue;
            }
            $targetId = link_cta_id($targetUrl !== '' ? $targetUrl : $targetTitle);
            $recommendationId = link_cta_id($pageId . '-internal-' . ($index + 1) . '-' . $targetId);
            $state = (array)($states[$recommendationId] ?? []);
            $targetType = (string)($target['type'] ?? 'page');
            $targetTypeLabel = link_cta_type_label($targetType);
            $score = link_cta_recommendation_score($optimizer, 'internal_link', (int)($target['score'] ?? 0));
            $reason = link_cta_clean($target['reason'] ?? 'Halaman tujuan ini relevan sebagai jalur lanjut dari konten SEO.', 220);

            $recommendations[] = [
                'recommendation_id' => $recommendationId,
                'page_id' => $pageId,
                'kind' => 'internal_link',
                'kind_label' => 'Internal Link',
                'priority' => $priority,
                'score' => $score,
                'page_title' => $pageTitle,
                'page_type' => $pageType,
                'page_type_label' => $pageTypeLabel,
                'page_url' => $pageUrl,
                'page_edit_url' => $pageEditUrl,
                'target_title' => $targetTitle,
                'target_type' => $targetType,
                'target_type_label' => $targetTypeLabel,
                'target_url' => $targetUrl,
                'target_edit_url' => link_cta_clean($target['edit_url'] ?? '', 220),
                'placement' => $index === 0 ? 'Paragraf tengah / setelah penjelasan utama' : 'Bagian rekomendasi lanjutan',
                'anchor_text' => $targetTitle,
                'snippet' => link_cta_snippet_for_internal_link($optimizer, $target),
                'reason' => $reason,
                'checkpoints' => [
                    'Pilih paragraf yang konteksnya nyambung dengan halaman tujuan.',
                    'Gunakan anchor text natural, bukan keyword stuffing.',
                    'Pastikan link mengarah ke URL internal yang aktif.',
                    'Cek ulang alur: pembaca paham kenapa harus klik halaman tujuan.',
                ],
                'status' => (string)($state['status'] ?? 'pending'),
                'note' => link_cta_clean($state['note'] ?? '', 560),
                'updated_at' => link_cta_clean($state['updated_at'] ?? '', 80),
            ];
        }

        $cta = (array)($optimizer['cta_plan'] ?? []);
        $ctaLabel = link_cta_clean($cta['cta_label'] ?? 'Tanya Rekomendasi', 80);
        $ctaUrl = link_cta_clean($cta['cta_url'] ?? '/kontak', 220);
        $ctaPlacement = link_cta_clean($cta['placement_label'] ?? 'Area CTA utama', 140);
        $recommendationId = link_cta_id($pageId . '-cta-' . (string)($cta['placement'] ?? 'main') . '-' . $ctaLabel);
        $state = (array)($states[$recommendationId] ?? []);
        $recommendations[] = [
            'recommendation_id' => $recommendationId,
            'page_id' => $pageId,
            'kind' => 'cta',
            'kind_label' => 'CTA Injection',
            'priority' => $priority,
            'score' => link_cta_recommendation_score($optimizer, 'cta', 80),
            'page_title' => $pageTitle,
            'page_type' => $pageType,
            'page_type_label' => $pageTypeLabel,
            'page_url' => $pageUrl,
            'page_edit_url' => $pageEditUrl,
            'target_title' => $ctaLabel,
            'target_type' => 'cta',
            'target_type_label' => 'CTA',
            'target_url' => $ctaUrl,
            'target_edit_url' => link_cta_action_url((string)($cta['admin_url'] ?? 'admin/cta-placement')),
            'placement' => $ctaPlacement,
            'anchor_text' => $ctaLabel,
            'snippet' => link_cta_snippet_for_cta($optimizer),
            'reason' => 'CTA ini meneruskan rekomendasi Money Page Optimizer agar traffic SEO tidak berhenti sebagai pembaca saja.',
            'checkpoints' => [
                'Pasang headline CTA yang relevan dengan isi halaman.',
                'Gunakan tombol “' . $ctaLabel . '” atau variasi winner dari Offer Lab.',
                'Arahkan tombol ke WhatsApp, form, katalog, atau halaman offer yang tepat.',
                'Setelah dipasang, pantau klik/lead/order di CTA Result dan SEO Journey Map.',
            ],
            'status' => (string)($state['status'] ?? 'pending'),
            'note' => link_cta_clean($state['note'] ?? '', 560),
            'updated_at' => link_cta_clean($state['updated_at'] ?? '', 80),
        ];

        usort($recommendations, static function (array $a, array $b): int {
            return ((int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0)) ?: strcmp((string)($a['kind_label'] ?? ''), (string)($b['kind_label'] ?? ''));
        });

        return $recommendations;
    }
}

if (!function_exists('link_cta_status_matches')) {
    function link_cta_status_matches(string $status, string $filter): bool
    {
        return match ($filter) {
            'all' => true,
            'open' => !in_array($status, ['applied', 'monitoring'], true),
            default => $status === $filter,
        };
    }
}

if (!function_exists('link_cta_summary')) {
    function link_cta_summary(int $days = 30, string $type = 'all', string $priority = 'all', string $status = 'open'): array
    {
        $days = max(1, min(365, $days));
        $moneySummary = function_exists('seo_money_summary') ? seo_money_summary($days, $type, $priority) : ['items' => [], 'type_options' => ['all' => 'Semua SEO Page'], 'priority_options' => ['all' => 'Semua Prioritas']];
        $typeOptions = (array)($moneySummary['type_options'] ?? ['all' => 'Semua SEO Page']);
        if (!isset($typeOptions[$type])) {
            $type = 'all';
        }
        $priorityOptions = (array)($moneySummary['priority_options'] ?? ['all' => 'Semua Prioritas', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low']);
        if (!isset($priorityOptions[$priority])) {
            $priority = 'all';
        }
        $statusOptions = link_cta_filter_options();
        if (!isset($statusOptions[$status])) {
            $status = 'open';
        }

        $states = (array)(link_cta_settings(true)['recommendations'] ?? []);
        $pages = [];
        $flat = [];
        $counts = [
            'pages' => 0,
            'recommendations' => 0,
            'internal_links' => 0,
            'ctas' => 0,
            'pending' => 0,
            'reviewing' => 0,
            'applied' => 0,
            'monitoring' => 0,
            'deferred' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ((array)($moneySummary['items'] ?? []) as $optimizer) {
            if (!is_array($optimizer)) {
                continue;
            }
            $pageRecommendations = link_cta_recommendations_for_optimizer($optimizer, $states);
            $visibleRecommendations = [];
            foreach ($pageRecommendations as $recommendation) {
                $recStatus = (string)($recommendation['status'] ?? 'pending');
                if (!link_cta_status_matches($recStatus, $status)) {
                    continue;
                }
                $visibleRecommendations[] = $recommendation;
                $flat[] = $recommendation;
            }
            if (!$visibleRecommendations) {
                continue;
            }

            $page = (array)($optimizer['item'] ?? []);
            $pageId = link_cta_id((string)($optimizer['page_id'] ?? $page['page_id'] ?? ''));
            $pageProgressTotal = count($pageRecommendations);
            $pageProgressDone = 0;
            foreach ($pageRecommendations as $rec) {
                if (in_array((string)($rec['status'] ?? 'pending'), ['applied', 'monitoring'], true)) {
                    $pageProgressDone++;
                }
            }

            $pages[] = [
                'page_id' => $pageId,
                'page_title' => link_cta_clean($page['title'] ?? 'Halaman SEO', 140),
                'page_type' => (string)($page['type'] ?? 'page'),
                'page_type_label' => link_cta_type_label((string)($page['type'] ?? 'page')),
                'page_url' => link_cta_clean($page['url'] ?? '', 220),
                'page_edit_url' => link_cta_clean($page['edit_url'] ?? '', 220),
                'priority' => (string)($optimizer['priority'] ?? 'low'),
                'money_score' => (int)($optimizer['money_score'] ?? 0),
                'metrics' => (array)($optimizer['metrics'] ?? []),
                'stage' => (array)($optimizer['stage'] ?? []),
                'progress_total' => $pageProgressTotal,
                'progress_done' => $pageProgressDone,
                'progress_percent' => $pageProgressTotal > 0 ? (int)round(($pageProgressDone / $pageProgressTotal) * 100) : 0,
                'recommendations' => $visibleRecommendations,
            ];
        }

        foreach ($flat as $recommendation) {
            $counts['recommendations']++;
            if ((string)($recommendation['kind'] ?? '') === 'internal_link') {
                $counts['internal_links']++;
            }
            if ((string)($recommendation['kind'] ?? '') === 'cta') {
                $counts['ctas']++;
            }
            $recStatus = (string)($recommendation['status'] ?? 'pending');
            if (isset($counts[$recStatus])) {
                $counts[$recStatus]++;
            }
            $recPriority = (string)($recommendation['priority'] ?? 'low');
            if (isset($counts[$recPriority])) {
                $counts[$recPriority]++;
            }
        }

        $counts['pages'] = count($pages);
        usort($pages, static function (array $a, array $b): int {
            return (link_cta_priority_weight((string)($b['priority'] ?? 'low')) <=> link_cta_priority_weight((string)($a['priority'] ?? 'low')))
                ?: ((int)($b['money_score'] ?? 0) <=> (int)($a['money_score'] ?? 0))
                ?: strcmp((string)($a['page_title'] ?? ''), (string)($b['page_title'] ?? ''));
        });

        usort($flat, static function (array $a, array $b): int {
            return (link_cta_priority_weight((string)($b['priority'] ?? 'low')) <=> link_cta_priority_weight((string)($a['priority'] ?? 'low')))
                ?: ((int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0));
        });

        $done = (int)$counts['applied'] + (int)$counts['monitoring'];
        $total = (int)$counts['recommendations'];
        $progress = $total > 0 ? (int)round(($done / $total) * 100) : 0;
        $topFocus = 'Mulai dari halaman prioritas tinggi: pasang internal link ke halaman offer, lalu sisipkan CTA yang paling relevan.';
        if ($total <= 0) {
            $topFocus = 'Belum ada rekomendasi visible. Cek Money Page Optimizer atau ubah filter status/periode.';
        } elseif ($counts['ctas'] > $counts['internal_links']) {
            $topFocus = 'Banyak CTA belum dipasang. Pastikan tiap money page punya ajakan aksi yang jelas.';
        } elseif ($counts['internal_links'] > 0) {
            $topFocus = 'Internal link siap dikerjakan. Arahkan artikel/halaman SEO ke produk, landing page, form, atau offer yang tepat.';
        }

        return [
            'days' => $days,
            'type' => $type,
            'priority' => $priority,
            'status' => $status,
            'type_options' => $typeOptions,
            'priority_options' => $priorityOptions,
            'status_options' => $statusOptions,
            'tracking_enabled' => !empty($moneySummary['tracking_enabled']),
            'progress_percent' => $progress,
            'top_focus' => $topFocus,
            'counts' => $counts,
            'pages' => $pages,
            'recommendations' => $flat,
            'top_recommendation' => $flat[0] ?? null,
            'source_summary' => $moneySummary,
            'generated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('link_cta_export_csv')) {
    function link_cta_export_csv(array $recommendations): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="internal-link-cta-injection-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['recommendation_id', 'page_id', 'priority', 'kind', 'page_type', 'page_title', 'page_url', 'target_title', 'target_url', 'placement', 'anchor_text', 'score', 'status', 'note']);
        foreach ($recommendations as $recommendation) {
            fputcsv($out, [
                (string)($recommendation['recommendation_id'] ?? ''),
                (string)($recommendation['page_id'] ?? ''),
                (string)($recommendation['priority'] ?? ''),
                (string)($recommendation['kind_label'] ?? ''),
                (string)($recommendation['page_type_label'] ?? ''),
                (string)($recommendation['page_title'] ?? ''),
                (string)($recommendation['page_url'] ?? ''),
                (string)($recommendation['target_title'] ?? ''),
                (string)($recommendation['target_url'] ?? ''),
                (string)($recommendation['placement'] ?? ''),
                (string)($recommendation['anchor_text'] ?? ''),
                (int)($recommendation['score'] ?? 0),
                (string)($recommendation['status'] ?? ''),
                (string)($recommendation['note'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
