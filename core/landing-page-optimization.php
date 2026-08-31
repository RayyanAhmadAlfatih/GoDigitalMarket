<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| Template Landing Page Optimization Center
|--------------------------------------------------------------------------
| Combines Landing Page Builder structure, Analytics, Tes A/B, AI Copy Assistant,
| and Media Library readiness into one optimization report. Local-only and
| shared-hosting friendly.
|--------------------------------------------------------------------------
*/

if (!function_exists('landing_page_optimization_clean_text')) {
    function landing_page_optimization_clean_text(string $value, int $max = 180): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }
        return substr($value, 0, $max);
    }
}

if (!function_exists('landing_page_optimization_block_types')) {
    function landing_page_optimization_block_types(array $page): array
    {
        $types = [];
        foreach ((array)($page['blocks'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = trim((string)($block['type'] ?? ''));
            if ($type !== '') {
                $types[$type] = (int)($types[$type] ?? 0) + 1;
            }
        }
        return $types;
    }
}

if (!function_exists('landing_page_optimization_has_clickable_cta')) {
    function landing_page_optimization_has_clickable_cta(array $page): bool
    {
        foreach ((array)($page['blocks'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            $type = (string)($block['type'] ?? '');
            $urls = [];
            if ($type === 'hero_offer') {
                $urls[] = (string)($block['primary_url'] ?? '');
                $urls[] = (string)($block['secondary_url'] ?? '');
            }
            if ($type === 'cta') {
                $urls[] = (string)($block['button_url'] ?? '');
            }
            if ($type === 'pricing_cards') {
                $urls[] = (string)($block['button_url'] ?? '');
            }
            foreach ($urls as $url) {
                $url = trim($url);
                if ($url !== '' && $url !== '#') {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('landing_page_optimization_image_audit')) {
    function landing_page_optimization_image_audit(array $page): array
    {
        $total = 0;
        $missingAlt = 0;
        $localImages = 0;
        $remoteImages = 0;
        $largeKnown = 0;

        $check = static function (string $url, string $alt = '') use (&$total, &$missingAlt, &$localImages, &$remoteImages, &$largeKnown): void {
            $url = trim($url);
            if ($url === '') {
                return;
            }
            $total++;
            if (trim($alt) === '') {
                $missingAlt++;
            }
            $isLocal = false;
            if (function_exists('media_library_asset_relative_from_url')) {
                $relative = media_library_asset_relative_from_url($url);
                $isLocal = $relative !== '' && str_starts_with($relative, 'assets/');
                if ($isLocal) {
                    $localImages++;
                    $path = ROOT_PATH . '/' . ltrim($relative, '/');
                    if (is_file($path) && (int)@filesize($path) > 500 * 1024) {
                        $largeKnown++;
                    }
                    return;
                }
            }
            if (preg_match('#^https?://#i', $url)) {
                $remoteImages++;
            }
        };

        foreach ((array)($page['blocks'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            $check((string)($block['image'] ?? ''), (string)($block['image_alt'] ?? $block['alt'] ?? ''));
            foreach ((array)($block['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $check((string)($item['image'] ?? ''), (string)($item['image_alt'] ?? $item['alt'] ?? ''));
            }
        }

        return [
            'total' => $total,
            'missing_alt' => $missingAlt,
            'local' => $localImages,
            'remote' => $remoteImages,
            'large_known' => $largeKnown,
        ];
    }
}

if (!function_exists('landing_page_optimization_ab_readiness')) {
    function landing_page_optimization_ab_readiness(array $page): array
    {
        $cfg = function_exists('landing_page_ab_sanitize_config')
            ? landing_page_ab_sanitize_config($page['ab_tests'] ?? [])
            : (is_array($page['ab_tests'] ?? null) ? (array)$page['ab_tests'] : []);

        $enabled = 0;
        $complete = 0;
        $issues = [];

        foreach (['cta', 'form'] as $type) {
            $test = is_array($cfg[$type] ?? null) ? (array)$cfg[$type] : [];
            if (empty($test['enabled'])) {
                continue;
            }
            $enabled++;
            $a = is_array($test['variasi_a'] ?? null) ? (array)$test['variasi_a'] : [];
            $b = is_array($test['variasi_b'] ?? null) ? (array)$test['variasi_b'] : [];
            $ok = false;
            if ($type === 'cta') {
                $ok = trim((string)($a['button_text'] ?? '')) !== ''
                    && trim((string)($b['button_text'] ?? '')) !== ''
                    && trim((string)($a['button_url'] ?? '')) !== ''
                    && trim((string)($b['button_url'] ?? '')) !== '';
            } else {
                $ok = trim((string)($a['headline'] ?? '')) !== ''
                    && trim((string)($b['headline'] ?? '')) !== ''
                    && trim((string)($a['submit_text'] ?? '')) !== ''
                    && trim((string)($b['submit_text'] ?? '')) !== '';
            }
            if ($ok) {
                $complete++;
            } else {
                $issues[] = strtoupper($type) . ' test aktif, tapi variasi A/B belum lengkap.';
            }
        }

        return [
            'enabled' => $enabled,
            'complete' => $complete,
            'issues' => $issues,
            'ready' => $enabled === 0 || $enabled === $complete,
        ];
    }
}

if (!function_exists('landing_page_optimization_issue')) {
    function landing_page_optimization_issue(string $severity, string $title, string $text, string $action = '', int $penalty = 5): array
    {
        return [
            'severity' => $severity,
            'title' => $title,
            'text' => $text,
            'action' => $action,
            'penalty' => $penalty,
        ];
    }
}

if (!function_exists('landing_page_optimization_metric_row')) {
    function landing_page_optimization_metric_row(array $analyticsRows, string $slug): array
    {
        foreach ($analyticsRows as $row) {
            if ((string)($row['slug'] ?? '') === $slug) {
                return $row;
            }
        }
        return [];
    }
}

if (!function_exists('landing_page_optimization_ab_winner')) {
    function landing_page_optimization_ab_winner(array $abRows, string $slug): array
    {
        $groups = [];
        foreach ($abRows as $row) {
            if ((string)($row['slug'] ?? '') !== $slug) {
                continue;
            }
            $type = (string)($row['test_type'] ?? '');
            if ($type === '') {
                continue;
            }
            $groups[$type][] = $row;
        }

        $insights = [];
        foreach ($groups as $type => $rows) {
            if (count($rows) < 2) {
                $insights[] = strtoupper($type) . ': data variasi belum lengkap.';
                continue;
            }
            usort($rows, static function (array $a, array $b): int {
                $leadCompare = ((int)($b['lead_total'] ?? 0)) <=> ((int)($a['lead_total'] ?? 0));
                if ($leadCompare !== 0) {
                    return $leadCompare;
                }
                return ((float)($b['conversion_rate'] ?? 0)) <=> ((float)($a['conversion_rate'] ?? 0));
            });
            $top = $rows[0];
            $kunjungansTotal = 0;
            foreach ($rows as $row) {
                $kunjungansTotal += (int)($row['page_kunjungan'] ?? 0);
            }
            if ($kunjungansTotal < 20) {
                $insights[] = strtoupper($type) . ': data belum cukup untuk menentukan winner.';
            } else {
                $insights[] = strtoupper($type) . ': variasi ' . strtoupper((string)($top['variasi'] ?? '-')) . ' sementara unggul.';
            }
        }

        return $insights;
    }
}

if (!function_exists('landing_page_optimization_analyze_page')) {
    function landing_page_optimization_analyze_page(array $page, array $analyticsRow = [], array $abRows = []): array
    {
        $page = function_exists('landing_page_normalize') ? landing_page_normalize($page) : $page;
        $types = landing_page_optimization_block_types($page);
        $issues = [];
        $recommendations = [];
        $score = 100;

        $status = (string)($page['status'] ?? 'draft');
        $title = landing_page_optimization_clean_text((string)($page['title'] ?? 'Landing Page'), 120);
        $slug = (string)($page['slug'] ?? '');
        $kunjungans = (int)($analyticsRow['page_kunjungan'] ?? 0);
        $cta = (int)($analyticsRow['cta_click'] ?? 0);
        $leadTotal = (int)($analyticsRow['lead_total'] ?? 0);
        $orders = (int)($analyticsRow['order'] ?? 0);
        $ctaRate = (float)($analyticsRow['cta_rate'] ?? 0);
        $leadRate = (float)($analyticsRow['lead_rate'] ?? 0);
        $cvr = (float)($analyticsRow['conversion_rate'] ?? 0);

        $add = static function (array $issue) use (&$issues, &$score): void {
            $issues[] = $issue;
            $score -= (int)($issue['penalty'] ?? 5);
        };

        if ($status === 'published' && empty($types)) {
            $add(landing_page_optimization_issue('danger', 'Published tapi belum ada blok', 'Landing page sudah publish, tapi kontennya masih kosong.', 'Tambahkan hero, Tombol, form, dan FAQ sebelum scale traffic.', 22));
        }
        if (empty($types['hero_offer'])) {
            $add(landing_page_optimization_issue('warning', 'Hero offer belum ada', 'Halaman belum punya hero yang menjelaskan offer utama.', 'Buat/Update Hero dari AI Copy Assistant.', 10));
        }
        if (!landing_page_optimization_has_clickable_cta($page)) {
            $add(landing_page_optimization_issue('danger', 'Tombol belum siap', 'Belum ditemukan tombol Tombol yang jelas dan bisa diklik.', 'Tambahkan blok Tombol atau isi URL tombol hero.', 15));
        }
        if (empty($types['lead_form'])) {
            $add(landing_page_optimization_issue('warning', 'Form lead belum ada', 'Landing Page belum punya form custom untuk segmentasi lead.', 'Tambahkan Form Custom agar follow-up admin lebih rapi.', 12));
        }
        if (empty($types['faq'])) {
            $add(landing_page_optimization_issue('info', 'FAQ SEO belum ada', 'FAQ membantu menjawab objection dan menambah relevansi SEO.', 'Tambahkan FAQ SEO dari AI Copy Assistant.', 5));
        }
        if (empty($types['benefits']) && empty($types['pain_points']) && empty($types['pricing_cards'])) {
            $add(landing_page_optimization_issue('info', 'Section trust/benefit masih kurang', 'Landing Page butuh alasan memilih layanan sebelum Tombol.', 'Tambahkan benefit, pain point, atau paket harga.', 6));
        }

        $metaTitle = trim((string)($page['meta_title'] ?? ''));
        $metaDescription = trim((string)($page['meta_description'] ?? ''));
        if ($metaTitle === '' || strlen($metaTitle) < 25) {
            $add(landing_page_optimization_issue('warning', 'Meta title belum optimal', 'Meta title kosong atau terlalu pendek.', 'Isi SEO Pack dari AI Copy Assistant.', 9));
        }
        if ($metaDescription === '' || strlen($metaDescription) < 90) {
            $add(landing_page_optimization_issue('warning', 'Meta description belum optimal', 'Meta description sebaiknya menjelaskan layanan, lokasi, dan Tombol.', 'Isi SEO Pack dari AI Copy Assistant.', 10));
        }
        if (trim((string)($page['tracking_label'] ?? '')) === '') {
            $add(landing_page_optimization_issue('warning', 'Tracking label kosong', 'Tracking label membantu membaca event di analytics.', 'Isi label tracking sesuai promosi.', 7));
        }
        if ($status === 'published' && empty($page['indexable'])) {
            $add(landing_page_optimization_issue('info', 'Published tapi noindex', 'Ini aman untuk traffic iklan, tapi tidak akan fokus SEO organik.', 'Aktifkan indexable hanya jika Landing Page memang siap untuk SEO publik.', 3));
        }

        $imageAudit = landing_page_optimization_image_audit($page);
        if ((int)$imageAudit['missing_alt'] > 0) {
            $add(landing_page_optimization_issue('warning', 'Alt gambar Landing Page belum lengkap', 'Ada ' . (int)$imageAudit['missing_alt'] . ' gambar Landing Page tanpa alt text.', 'Isi alt text agar SEO gambar lebih rapi.', 6));
        }
        if ((int)$imageAudit['large_known'] > 0) {
            $add(landing_page_optimization_issue('warning', 'Gambar Landing Page terdeteksi besar', 'Ada gambar lokal yang melewati 500KB.', 'Optimasi gambar lewat Media Library sebelum scale traffic.', 6));
        }

        $abReadiness = landing_page_optimization_ab_readiness($page);
        foreach ((array)$abReadiness['issues'] as $abIssue) {
            $add(landing_page_optimization_issue('warning', 'A/B test belum lengkap', (string)$abIssue, 'Lengkapi variasi A/B di tab Tracking.', 8));
        }

        if ($kunjungans >= 20 && $cta <= 0) {
            $add(landing_page_optimization_issue('danger', 'Kunjungan tinggi, Tombol belum bergerak', 'Traffic sudah masuk tapi belum ada klik Tombol.', 'Perbaiki headline, tombol utama, dan posisi Tombol above the fold.', 16));
        } elseif ($kunjungans >= 50 && $ctaRate < 2.0) {
            $add(landing_page_optimization_issue('warning', 'Tombol rate rendah', 'Tombol rate di bawah 2% pada rentang laporan.', 'Jalankan A/B test teks tombol dan hero offer.', 12));
        }

        if ($cta >= 10 && $leadTotal <= 0) {
            $add(landing_page_optimization_issue('danger', 'Klik ada, lead belum masuk', 'Visitor klik Tombol/form tapi belum menjadi lead.', 'Ringankan form, perjelas benefit, dan cek flow WhatsApp/form.', 16));
        } elseif ($kunjungans >= 50 && $leadRate < 1.0) {
            $add(landing_page_optimization_issue('warning', 'Lead rate rendah', 'Lead rate di bawah 1% pada rentang laporan.', 'Uji form copy dan offer low barrier.', 10));
        }

        if ($leadTotal >= 5 && $orders <= 0) {
            $add(landing_page_optimization_issue('info', 'Lead masuk, order belum tercatat', 'Lead sudah ada tapi order belum masuk ke alur.', 'Cek follow-up CRM dan pastikan order dicatat dari lead yang valid.', 5));
        }

        if (!$issues) {
            $recommendations[] = 'Landing Page terlihat siap. Fokus berikutnya: tambah traffic, pantau winner dari Tes A/B existing, dan scale sumber promosi terbaik.';
        } else {
            foreach (array_slice($issues, 0, 4) as $issue) {
                if (!empty($issue['action'])) {
                    $recommendations[] = (string)$issue['action'];
                }
            }
        }

        $winnerInsights = landing_page_optimization_ab_winner($abRows, $slug);
        foreach ($winnerInsights as $insight) {
            $recommendations[] = $insight;
        }

        $score = max(0, min(100, $score));
        $priority = 100 - $score + min(40, $kunjungans / 5) + count($issues) * 2;
        $tone = $score >= 82 ? 'success' : ($score >= 62 ? 'warning' : 'danger');

        return [
            'id' => (string)($page['id'] ?? ''),
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'url' => function_exists('landing_page_url') ? landing_page_url($slug) : url('lp/' . $slug),
            'edit_url' => url('admin/landing-pages?builder=' . rawurlencode((string)($page['id'] ?? $slug))),
            'analytics_url' => url('admin/landing-page-analytics?lp=' . rawurlencode($slug)),
            'score' => $score,
            'tone' => $tone,
            'priority' => $priority,
            'issue_count' => count($issues),
            'critical_count' => count(array_filter($issues, static fn(array $issue): bool => (string)($issue['severity'] ?? '') === 'danger')),
            'issues' => $issues,
            'recommendations' => array_values(array_unique(array_filter($recommendations))),
            'types' => $types,
            'metrics' => [
                'page_kunjungan' => $kunjungans,
                'cta_click' => $cta,
                'lead_total' => $leadTotal,
                'order' => $orders,
                'cta_rate' => $ctaRate,
                'lead_rate' => $leadRate,
                'conversion_rate' => $cvr,
            ],
            'ab_readiness' => $abReadiness,
            'image_audit' => $imageAudit,
            'updated_at' => (string)($page['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('landing_page_optimization_media_readiness')) {
    function landing_page_optimization_media_readiness(): array
    {
        $summary = function_exists('media_library_summary') ? media_library_summary() : [];
        $features = [
            'scan_assets' => function_exists('media_library_scan'),
            'alt_audit' => function_exists('media_library_summary'),
            'large_file_audit' => function_exists('media_library_summary'),
            'unused_detection' => function_exists('media_library_references'),
            'admin_page' => is_file(ROOT_PATH . '/pages/admin-media-library.php'),
            'product_picker' => is_file(ROOT_PATH . '/pages/admin-produk.php') && str_contains((string)@file_get_contents(ROOT_PATH . '/pages/admin-produk.php'), 'media-library'),
            'article_picker' => is_file(ROOT_PATH . '/pages/admin-artikel.php') && str_contains((string)@file_get_contents(ROOT_PATH . '/pages/admin-artikel.php'), 'media-library'),
            'asset_score' => function_exists('media_library_asset_score'),
            'lp_asset_mapping' => function_exists('landing_page_all') && function_exists('media_library_references'),
            'bulk_alt_suggestion' => function_exists('media_library_apply_suggested_alt'),
        ];
        $readyCount = count(array_filter($features));
        $totalFeatures = count($features);
        return [
            'summary' => $summary,
            'features' => $features,
            'ready_count' => $readyCount,
            'total_features' => $totalFeatures,
            'readiness_percent' => $totalFeatures > 0 ? (int)round(($readyCount / $totalFeatures) * 100) : 0,
            'note' => 'Pemeriksaan media sudah aktif: kualitas gambar, pemakaian di halaman, saran alt text, nama file SEO, dan kesiapan WebP.',
        ];
    }
}


if (!function_exists('landing_page_optimization_action_queue')) {
    function landing_page_optimization_action_queue(array $items): array
    {
        $queue = [];
        foreach ($items as $item) {
            $issues = (array)($item['issues'] ?? []);
            $metrics = (array)($item['metrics'] ?? []);
            $primaryIssue = $issues[0] ?? [];
            $score = (int)($item['score'] ?? 0);
            $critical = (int)($item['critical_count'] ?? 0);
            $kunjungans = (int)($metrics['page_kunjungan'] ?? 0);
            $cta = (int)($metrics['cta_click'] ?? 0);
            $leads = (int)($metrics['lead_total'] ?? 0);
            $orders = (int)($metrics['order'] ?? 0);
            $priorityScore = (100 - $score) + ($critical * 20) + min(30, (int)floor($kunjungans / 10));
            $tone = $critical > 0 ? 'danger' : ($score < 82 ? 'warning' : 'success');
            $badge = $critical > 0 ? 'Fix dulu' : ($score < 82 ? 'Optimasi' : 'Scale');
            $title = (string)($primaryIssue['title'] ?? 'Siap scale bertahap');
            $action = (string)($primaryIssue['action'] ?? 'Pantau analytics, source traffic, dan hasil follow-up sebelum menaikkan budget.');
            if (!$issues && $kunjungans <= 0) {
                $title = 'Validasi traffic awal';
                $action = 'Buka LP publik, kirim test event, lalu mulai promosi kecil untuk membaca sinyal pertama.';
                $tone = 'info';
                $badge = 'Mulai';
            } elseif (!$issues && ($leads > 0 || $orders > 0)) {
                $title = 'Pertahankan pola LP winner';
                $action = 'Duplikasi pola copy, CTA, dan trust section yang sudah menghasilkan lead/order ke campaign berikutnya.';
                $tone = 'success';
                $badge = 'Winner';
            } elseif ($kunjungans >= 30 && $cta <= 0) {
                $title = 'Traffic ada, CTA belum bergerak';
                $action = 'Prioritaskan hero, headline, dan tombol above the fold sebelum menambah traffic.';
                $tone = 'danger';
                $badge = 'Urgent';
            } elseif ($cta >= 5 && $leads <= 0) {
                $title = 'Klik ada, lead belum masuk';
                $action = 'Ringankan form atau arahkan ke WhatsApp dengan pesan yang lebih jelas.';
                $tone = 'warning';
                $badge = 'Lead Path';
            }
            $queue[] = [
                'priority_score' => $priorityScore,
                'tone' => $tone,
                'badge' => $badge,
                'title' => $title,
                'action' => $action,
                'lp_title' => (string)($item['title'] ?? 'Landing Page'),
                'slug' => (string)($item['slug'] ?? ''),
                'score' => $score,
                'metrics' => $metrics,
                'edit_url' => (string)($item['edit_url'] ?? '#'),
                'analytics_url' => (string)($item['analytics_url'] ?? '#'),
            ];
        }
        usort($queue, static function (array $a, array $b): int {
            return ((int)($b['priority_score'] ?? 0)) <=> ((int)($a['priority_score'] ?? 0));
        });
        return array_slice($queue, 0, 6);
    }
}

if (!function_exists('landing_page_optimization_report')) {
    function landing_page_optimization_report(int $days = 30, array $filters = []): array
    {
        $pages = function_exists('landing_page_all') ? landing_page_all(true) : [];
        $analytics = function_exists('landing_page_analytics_report') ? landing_page_analytics_report($days, $filters) : ['rows' => [], 'ab_breakdown' => []];
        $analyticsRows = (array)($analytics['rows'] ?? []);
        $abRows = (array)($analytics['ab_breakdown'] ?? []);
        $items = [];

        foreach ($pages as $page) {
            $page = function_exists('landing_page_normalize') ? landing_page_normalize($page) : $page;
            $slug = (string)($page['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            if (!empty($filters['lp_slug']) && slugify((string)$filters['lp_slug']) !== $slug) {
                continue;
            }
            $items[] = landing_page_optimization_analyze_page($page, landing_page_optimization_metric_row($analyticsRows, $slug), $abRows);
        }

        usort($items, static function (array $a, array $b): int {
            $priority = ((float)($b['priority'] ?? 0)) <=> ((float)($a['priority'] ?? 0));
            if ($priority !== 0) {
                return $priority;
            }
            return ((int)($a['score'] ?? 0)) <=> ((int)($b['score'] ?? 0));
        });

        $statusFilter = trim((string)($filters['status'] ?? ''));
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $items = array_values(array_filter($items, static fn(array $item): bool => (string)($item['status'] ?? '') === $statusFilter));
        }

        $issueFilter = trim((string)($filters['issue'] ?? ''));
        if ($issueFilter === 'critical') {
            $items = array_values(array_filter($items, static fn(array $item): bool => (int)($item['critical_count'] ?? 0) > 0));
        } elseif ($issueFilter === 'needs_fix') {
            $items = array_values(array_filter($items, static fn(array $item): bool => (int)($item['score'] ?? 0) < 82));
        } elseif ($issueFilter === 'ready') {
            $items = array_values(array_filter($items, static fn(array $item): bool => (int)($item['score'] ?? 0) >= 82));
        }

        $summary = [
            'total' => count($items),
            'ready' => 0,
            'needs_fix' => 0,
            'critical' => 0,
            'avg_score' => 0,
            'published' => 0,
            'ab_active' => 0,
        ];
        $scoreTotal = 0;
        foreach ($items as $item) {
            $score = (int)($item['score'] ?? 0);
            $scoreTotal += $score;
            if ($score >= 82) {
                $summary['ready']++;
            } else {
                $summary['needs_fix']++;
            }
            if ((int)($item['critical_count'] ?? 0) > 0) {
                $summary['critical']++;
            }
            if ((string)($item['status'] ?? '') === 'published') {
                $summary['published']++;
            }
            if ((int)($item['ab_readiness']['enabled'] ?? 0) > 0) {
                $summary['ab_active']++;
            }
        }
        $summary['avg_score'] = $summary['total'] > 0 ? (int)round($scoreTotal / $summary['total']) : 0;

        $globalRecommendations = [];
        if ($summary['critical'] > 0) {
            $globalRecommendations[] = 'Prioritaskan Landing Page dengan issue merah sebelum menambah budget iklan.';
        }
        if ($summary['needs_fix'] > 0) {
            $globalRecommendations[] = 'Gunakan AI Copy Assistant untuk memperbaiki hero, SEO pack, FAQ, Tombol, dan form copy.';
        }
        if ($summary['ab_active'] === 0) {
            $globalRecommendations[] = 'Gunakan modul Tes A/B existing pada Landing Page yang sudah punya traffic agar optimasi lebih berbasis data.';
        }
        if (!$globalRecommendations) {
            $globalRecommendations[] = 'Mayoritas Landing Page sudah sehat. Fokus scale promosi dan pantau winner dari modul Tes A/B existing per minggu.';
        }

        $actionQueue = landing_page_optimization_action_queue($items);

        return [
            'generated_at' => date('c'),
            'days' => $days,
            'filters' => $filters,
            'summary' => $summary,
            'items' => $items,
            'action_queue' => $actionQueue,
            'recommendations' => $globalRecommendations,
            'analytics' => $analytics,
            'media_readiness' => landing_page_optimization_media_readiness(),
        ];
    }
}

if (!function_exists('landing_page_optimization_csv_rows')) {
    function landing_page_optimization_csv_rows(array $report): array
    {
        $rows = [];
        foreach ((array)($report['items'] ?? []) as $item) {
            $issues = array_map(static fn(array $issue): string => (string)($issue['title'] ?? ''), (array)($item['issues'] ?? []));
            $recs = (array)($item['recommendations'] ?? []);
            $metrics = (array)($item['metrics'] ?? []);
            $rows[] = [
                'title' => (string)($item['title'] ?? ''),
                'slug' => (string)($item['slug'] ?? ''),
                'status' => (string)($item['status'] ?? ''),
                'score' => (string)($item['score'] ?? 0),
                'critical_count' => (string)($item['critical_count'] ?? 0),
                'issue_count' => (string)($item['issue_count'] ?? 0),
                'page_kunjungan' => (string)($metrics['page_kunjungan'] ?? 0),
                'cta_click' => (string)($metrics['cta_click'] ?? 0),
                'lead_total' => (string)($metrics['lead_total'] ?? 0),
                'order' => (string)($metrics['order'] ?? 0),
                'cta_rate' => (string)($metrics['cta_rate'] ?? 0),
                'lead_rate' => (string)($metrics['lead_rate'] ?? 0),
                'conversion_rate' => (string)($metrics['conversion_rate'] ?? 0),
                'issues' => implode(' | ', array_filter($issues)),
                'recommendations' => implode(' | ', array_filter($recs)),
                'edit_url' => (string)($item['edit_url'] ?? ''),
                'analytics_url' => (string)($item['analytics_url'] ?? ''),
            ];
        }
        return $rows;
    }
}
