<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WORDPRESS MIGRATION COMMAND CENTER
|--------------------------------------------------------------------------
| Unified dashboard for the migration workflow. This file does not
| execute destructive actions. It only reads module reports, creates a health
| score, and gives the admin a safe step-by-step migration command map.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('migration_command_center_storage_path')) {
    function migration_command_center_storage_path(): string
    {
        return STORAGE_PATH . '/migration-command-center';
    }
}

if (!function_exists('migration_command_center_ensure_storage')) {
    function migration_command_center_ensure_storage(): void
    {
        $dir = migration_command_center_storage_path();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Allow,Deny\n    Deny from all\n</IfModule>\n", LOCK_EX);
        }
        $gitkeep = $dir . '/.gitkeep';
        if (!is_file($gitkeep)) {
            @file_put_contents($gitkeep, '', LOCK_EX);
        }
    }
}

if (!function_exists('migration_command_center_safe_count')) {
    function migration_command_center_safe_count(mixed $value): int
    {
        if (is_countable($value)) {
            return count($value);
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        return 0;
    }
}

if (!function_exists('migration_command_center_module_definitions')) {
    function migration_command_center_module_definitions(): array
    {
        return [
            [
                'id' => 'wp_migration',
                'title' => 'Import WordPress',
                'phase' => '01. Import',
                'route' => 'admin/wp-migration',
                'page' => 'pages/admin-wp-migration.php',
                'core' => 'core/wp-migration.php',
                'function' => 'wp_migration_jobs',
                'summary' => 'Upload XML/WXR/CSV, preview, import artikel/page, backup, rollback.',
            ],
            [
                'id' => 'seo_preservation',
                'title' => 'SEO Preservation & Redirect',
                'phase' => '02. SEO URL',
                'route' => 'admin/seo-preservation',
                'page' => 'pages/admin-seo-preservation.php',
                'core' => 'core/seo-preservation.php',
                'function' => 'seo_preservation_report',
                'summary' => 'Legacy URL resolver, canonical, redirect map, sitemap-aware URL utama.',
            ],
            [
                'id' => 'internal_link',
                'title' => 'Breadcrumb & Internal Link',
                'phase' => '03. Struktur Link',
                'route' => 'admin/internal-link-migration',
                'page' => 'pages/admin-internal-link-migration.php',
                'core' => 'core/internal-link-migration.php',
                'function' => 'internal_link_migration_scan',
                'summary' => 'Breadcrumb mapper, internal link checker, dry-run rewrite link lama.',
            ],
            [
                'id' => 'media',
                'title' => 'WordPress Media Migration',
                'phase' => '04. Media',
                'route' => 'admin/wp-media-migration',
                'page' => 'pages/admin-wp-media-migration.php',
                'core' => 'core/wp-media-migration.php',
                'function' => 'wp_media_migration_report',
                'summary' => 'Scan gambar wp-content, download map, rewrite URL gambar ke lokal dengan backup.',
            ],
            [
                'id' => 'content_cleaner',
                'title' => 'Shortcode & Gutenberg Cleaner',
                'phase' => '05. Cleanup Konten',
                'route' => 'admin/wp-content-cleaner',
                'page' => 'pages/admin-wp-content-cleaner.php',
                'core' => 'core/wp-content-cleaner.php',
                'function' => 'wp_content_cleaner_report',
                'summary' => 'Bersihkan shortcode, Gutenberg comment, dan sisa plugin WordPress secara aman.',
            ],
            [
                'id' => 'elementor',
                'title' => 'Elementor Safe Import',
                'phase' => '06. Page Builder',
                'route' => 'admin/wp-elementor-import',
                'page' => 'pages/admin-wp-elementor-import.php',
                'core' => 'core/wp-elementor-import.php',
                'function' => 'wp_elementor_import_report',
                'summary' => 'Deteksi page builder dan import halaman sebagai Landing Page draft HTML block aman.',
            ],
            [
                'id' => 'dynamic_guard',
                'title' => 'Dynamic Content Guard',
                'phase' => '07. Relevansi Konten',
                'route' => 'admin/dynamic-content-guard',
                'page' => 'pages/admin-dynamic-content-guard.php',
                'core' => 'core/dynamic-content.php',
                'function' => 'dynamic_v3_guard_report',
                'summary' => 'Audit rekomendasi artikel/produk/layanan agar dynamic content tidak random.',
            ],
        ];
    }
}

if (!function_exists('migration_command_center_module_report')) {
    function migration_command_center_module_report(array $module): array
    {
        $corePath = ROOT_PATH . '/' . ltrim((string)($module['core'] ?? ''), '/');
        $pagePath = ROOT_PATH . '/' . ltrim((string)($module['page'] ?? ''), '/');
        $fn = (string)($module['function'] ?? '');
        $available = is_file($corePath) && is_file($pagePath) && ($fn === '' || function_exists($fn));
        $health = $available ? 80 : 20;
        $counts = [];
        $notes = [];

        try {
            if ($available && $fn !== '') {
                if ($fn === 'wp_migration_jobs') {
                    $jobs = wp_migration_jobs(25);
                    $counts = [
                        'jobs' => count($jobs),
                        'imported_jobs' => count(array_filter($jobs, static fn(array $job): bool => (string)($job['status'] ?? '') === 'imported')),
                        'preview_jobs' => count(array_filter($jobs, static fn(array $job): bool => (string)($job['status'] ?? '') !== 'imported')),
                    ];
                    $health += $jobs ? 15 : 5;
                    $notes[] = $jobs ? 'Ada batch migrasi yang bisa dipantau.' : 'Belum ada batch migrasi; mulai dari upload XML/WXR/CSV.';
                } elseif ($fn === 'seo_preservation_report') {
                    $report = seo_preservation_report();
                    $counts = (array)($report['counts'] ?? []);
                    $health = max($health, (int)($report['health_score'] ?? 80));
                    $notes[] = 'Redirect/canonical/legacy URL bisa dicek dari menu SEO Preservation.';
                } elseif ($fn === 'internal_link_migration_scan') {
                    $scan = internal_link_migration_scan();
                    $counts = (array)($scan['counts'] ?? []);
                    $unknown = (int)($counts['unknown_internal'] ?? 0);
                    $legacy = (int)($counts['legacy_replacement'] ?? 0) + (int)($counts['redirect_map'] ?? 0);
                    $health += $unknown > 0 ? 0 : 15;
                    if ($legacy > 0) { $health += 5; }
                    $notes[] = $unknown > 0 ? 'Ada link internal yang perlu review manual.' : 'Tidak ada unknown internal link terdeteksi pada scan terakhir.';
                } elseif ($fn === 'wp_media_migration_report') {
                    $report = wp_media_migration_report();
                    $counts = (array)($report['counts'] ?? []);
                    $remote = (int)($counts['remote_images'] ?? $counts['remote'] ?? 0);
                    $mapped = (int)($counts['mapped'] ?? $counts['downloaded'] ?? 0);
                    $health += ($remote === 0 || $mapped > 0) ? 15 : 5;
                    $notes[] = $remote > 0 ? 'Ada gambar remote/WordPress yang bisa diproses.' : 'Belum ada gambar WordPress remote terdeteksi.';
                } elseif ($fn === 'wp_content_cleaner_report') {
                    $report = wp_content_cleaner_report();
                    $counts = (array)($report['counts'] ?? []);
                    $issues = (int)($counts['items_with_residue'] ?? $counts['matches'] ?? $counts['affected'] ?? 0);
                    $health += $issues > 0 ? 8 : 15;
                    $notes[] = $issues > 0 ? 'Ada shortcode/Gutenberg residue yang bisa dibersihkan dengan dry-run.' : 'Tidak ada residue besar pada scan sekarang.';
                } elseif ($fn === 'wp_elementor_import_report') {
                    $report = wp_elementor_import_report();
                    $counts = (array)($report['counts'] ?? []);
                    $complex = (int)($counts['complex'] ?? 0);
                    $ready = (int)($counts['safe_html_ready'] ?? 0);
                    $health += $complex > 0 ? 8 : 15;
                    if ($ready > 0) { $health += 3; }
                    $notes[] = $complex > 0 ? 'Ada widget kompleks yang perlu review sebelum publish.' : 'Belum ada warning widget kompleks dari report saat ini.';
                } elseif ($fn === 'dynamic_v3_guard_report') {
                    $report = dynamic_v3_guard_report(80);
                    $counts = (array)($report['counts'] ?? []);
                    $weak = (int)($counts['weak'] ?? 0);
                    $health = (int)($report['score'] ?? ($weak > 0 ? 86 : 96));
                    $notes[] = $weak > 0 ? 'Ada item weak; perkuat kategori/tag/keyword agar rekomendasi lebih relevan.' : 'Dynamic content relevansinya aman.';
                }
            }
        } catch (Throwable $e) {
            $available = false;
            $health = 35;
            $notes[] = 'Report gagal dibaca: ' . $e->getMessage();
        }

        $health = max(0, min(100, $health));
        $status = $available ? ($health >= 90 ? 'strong' : ($health >= 75 ? 'ok' : 'review')) : 'missing';

        return [
            'id' => (string)($module['id'] ?? ''),
            'title' => (string)($module['title'] ?? ''),
            'phase' => (string)($module['phase'] ?? ''),
            'route' => (string)($module['route'] ?? ''),
            'summary' => (string)($module['summary'] ?? ''),
            'available' => $available,
            'health' => $health,
            'status' => $status,
            'counts' => $counts,
            'notes' => $notes,
        ];
    }
}

if (!function_exists('migration_command_center_checklist')) {
    function migration_command_center_checklist(array $modules): array
    {
        $byId = [];
        foreach ($modules as $module) {
            $byId[(string)$module['id']] = $module;
        }

        $jobs = function_exists('wp_migration_jobs') ? wp_migration_jobs(25) : [];
        $hasImported = count(array_filter($jobs, static fn(array $job): bool => (string)($job['status'] ?? '') === 'imported')) > 0;
        $seoCounts = (array)($byId['seo_preservation']['counts'] ?? []);
        $linkCounts = (array)($byId['internal_link']['counts'] ?? []);
        $mediaCounts = (array)($byId['media']['counts'] ?? []);
        $cleanerCounts = (array)($byId['content_cleaner']['counts'] ?? []);
        $elementorCounts = (array)($byId['elementor']['counts'] ?? []);
        $dynamicCounts = (array)($byId['dynamic_guard']['counts'] ?? []);

        return [
            [
                'step' => 'Upload & preview export WordPress',
                'status' => $jobs ? 'done' : 'todo',
                'note' => $jobs ? 'Batch migrasi sudah pernah dibuat.' : 'Mulai dari XML/WXR/CSV WordPress.',
                'route' => 'admin/wp-migration',
            ],
            [
                'step' => 'Import artikel/page secara aman',
                'status' => $hasImported ? 'done' : 'todo',
                'note' => $hasImported ? 'Ada batch dengan status imported.' : 'Jalankan import setelah preview aman.',
                'route' => 'admin/wp-migration',
            ],
            [
                'step' => 'Cek legacy URL, canonical, dan redirect 301',
                'status' => ((int)($seoCounts['redirects'] ?? 0) + (int)($seoCounts['legacy_aliases'] ?? 0)) > 0 ? 'review' : 'todo',
                'note' => 'Aktifkan redirect hanya setelah URL lama dan URL baru sudah yakin benar.',
                'route' => 'admin/seo-preservation',
            ],
            [
                'step' => 'Scan breadcrumb & internal link',
                'status' => ((int)($linkCounts['total'] ?? 0) + (int)($linkCounts['links'] ?? 0)) > 0 ? 'review' : 'todo',
                'note' => 'Gunakan dry-run sebelum rewrite link lama.',
                'route' => 'admin/internal-link-migration',
            ],
            [
                'step' => 'Migrasi gambar WordPress',
                'status' => ((int)($mediaCounts['remote_images'] ?? 0) + (int)($mediaCounts['mapped'] ?? 0) + (int)($mediaCounts['downloaded'] ?? 0)) > 0 ? 'review' : 'todo',
                'note' => 'Download gambar dulu, baru rewrite URL jika mapping sudah aman.',
                'route' => 'admin/wp-media-migration',
            ],
            [
                'step' => 'Bersihkan shortcode & Gutenberg residue',
                'status' => ((int)($cleanerCounts['items_with_residue'] ?? 0) + (int)($cleanerCounts['matches'] ?? 0)) > 0 ? 'review' : 'ok',
                'note' => 'Unknown shortcode sebaiknya direview manual, jangan langsung dihapus semua.',
                'route' => 'admin/wp-content-cleaner',
            ],
            [
                'step' => 'Import halaman Elementor/page builder sebagai draft',
                'status' => ((int)($elementorCounts['pages'] ?? 0)) > 0 ? 'review' : 'todo',
                'note' => 'Widget kompleks tetap perlu review manual sebelum publish.',
                'route' => 'admin/wp-elementor-import',
            ],
            [
                'step' => 'Validasi dynamic content tetap relevan',
                'status' => ((int)($dynamicCounts['weak'] ?? 0)) > 0 ? 'review' : 'ok',
                'note' => 'Perkuat tag/kategori/keyword bila ada weak item.',
                'route' => 'admin/dynamic-content-guard',
            ],
            [
                'step' => 'Final check sitemap, analytics, dan GSC',
                'status' => 'manual',
                'note' => 'Submit sitemap dan pantau Coverage/Pages di Google Search Console setelah go-live.',
                'route' => 'admin/launch-readiness',
            ],
        ];
    }
}

if (!function_exists('migration_command_center_summary')) {
    function migration_command_center_summary(): array
    {
        migration_command_center_ensure_storage();
        $modules = array_map('migration_command_center_module_report', migration_command_center_module_definitions());
        $available = count(array_filter($modules, static fn(array $module): bool => !empty($module['available'])));
        $average = $modules ? (int)round(array_sum(array_map(static fn(array $module): int => (int)$module['health'], $modules)) / count($modules)) : 0;
        $missing = count($modules) - $available;
        $review = count(array_filter($modules, static fn(array $module): bool => in_array((string)$module['status'], ['review', 'missing'], true)));
        $score = max(0, min(100, $average - ($missing * 8)));
        $checklist = migration_command_center_checklist($modules);
        $todo = count(array_filter($checklist, static fn(array $item): bool => in_array((string)$item['status'], ['todo', 'review', 'manual'], true)));

        return [
            'version' => 'Migration Command Center',
            'generated_at' => date('c'),
            'score' => $score,
            'status' => $score >= 92 ? 'Siap untuk final audit' : ($score >= 80 ? 'Aman, masih perlu review migrasi' : 'Perlu review sebelum launch'),
            'module_count' => count($modules),
            'available_modules' => $available,
            'missing_modules' => $missing,
            'review_modules' => $review,
            'open_checklist' => $todo,
            'modules' => $modules,
            'checklist' => $checklist,
            'next_steps' => [
                'Jalankan migrasi pada data WordPress asli lewat preview dulu, jangan langsung publish.',
                'Review redirect 301 dan canonical sebelum URL lama dialihkan permanen.',
                'Pastikan gambar, shortcode, Elementor block, breadcrumb, dan internal link sudah dicek.',
                'Setelah itu lanjut final audit source, SEO, route, storage, dan hasil migrasi sebelum go-live.',
            ],
        ];
    }
}

if (!function_exists('migration_command_center_export')) {
    function migration_command_center_export(string $format = 'json'): void
    {
        $summary = migration_command_center_summary();
        $filename = 'ugrowth-migration-command-center-' . date('Ymd-His') . '.' . ($format === 'csv' ? 'csv' : 'json');
        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            if (is_resource($out)) {
                fputcsv($out, ['Type', 'Title/Step', 'Status', 'Health/Note', 'Route']);
                foreach ((array)$summary['modules'] as $module) {
                    fputcsv($out, ['Module', (string)$module['title'], (string)$module['status'], (string)$module['health'], (string)$module['route']]);
                }
                foreach ((array)$summary['checklist'] as $item) {
                    fputcsv($out, ['Checklist', (string)$item['step'], (string)$item['status'], (string)$item['note'], (string)$item['route']]);
                }
            }
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
