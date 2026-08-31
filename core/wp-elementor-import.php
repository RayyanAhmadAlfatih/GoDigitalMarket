<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WORDPRESS ELEMENTOR / PAGE BUILDER SAFE HTML IMPORT
|--------------------------------------------------------------------------
| V32.97: Detect Elementor/page-builder traces from WordPress exports and
| convert pages/LPs into safe U-Growth HTML block drafts. This layer is
| intentionally conservative: native block conversion is limited to obvious
| simple elements, while complex widgets stay as sanitized HTML with warnings.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('wp_elementor_import_base_path')) {
    function wp_elementor_import_base_path(): string
    {
        return STORAGE_PATH . '/wp-elementor-import';
    }
}

if (!function_exists('wp_elementor_import_report_path')) {
    function wp_elementor_import_report_path(): string
    {
        return wp_elementor_import_base_path() . '/report.json';
    }
}

if (!function_exists('wp_elementor_import_ensure_storage')) {
    function wp_elementor_import_ensure_storage(): void
    {
        foreach ([wp_elementor_import_base_path()] as $dir) {
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
}

if (!function_exists('wp_elementor_import_write_json')) {
    function wp_elementor_import_write_json(string $path, array $payload): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) && @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('wp_elementor_import_sanitize_html')) {
    function wp_elementor_import_sanitize_html(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Drop risky executable/plugin surfaces first.
        $html = preg_replace('#<\s*(script|iframe|object|embed|form|input|button|textarea|select|option|link|meta)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? $html;
        $html = preg_replace('#<\s*(script|iframe|object|embed|form|input|button|textarea|select|option|link|meta)[^>]*\/?>#is', '', $html) ?? $html;
        $html = preg_replace('#<\s*style[^>]*>.*?<\s*/\s*style\s*>#is', '', $html) ?? $html;

        // Remove event handlers and javascript/data scripting URLs.
        $html = preg_replace('#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
        $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2#i', '$1="#"', $html) ?? $html;
        $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*data:(?!image\/(png|jpeg|jpg|gif|webp|svg\+xml);)[^"\']*\2#i', '$1=""', $html) ?? $html;

        // Keep layout-friendly HTML, classes, inline styles, data attributes, and images.
        $allowed = '<section><div><article><aside><header><footer><main><nav><p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><del><mark><span><small><ul><ol><li><a><blockquote><hr><img><figure><figcaption><picture><source><table><thead><tbody><tfoot><tr><th><td><dl><dt><dd><pre><code>';
        $html = strip_tags($html, $allowed);
        $html = preg_replace('/\s{2,}/', ' ', (string)$html) ?? $html;
        return trim((string)$html);
    }
}

if (!function_exists('wp_elementor_import_plain_text')) {
    function wp_elementor_import_plain_text(string $html, int $limit = 260): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        return function_exists('limit_chars') ? limit_chars($text, $limit) : substr($text, 0, $limit);
    }
}

if (!function_exists('wp_elementor_import_detect_builder')) {
    function wp_elementor_import_detect_builder(array $item): array
    {
        $content = (string)($item['content'] ?? '');
        $raw = (string)($item['raw_content'] ?? $item['content_raw'] ?? $content);
        $metaKeys = array_map('strtolower', array_keys((array)($item['meta'] ?? [])));
        $haystack = strtolower($content . ' ' . $raw . ' ' . implode(' ', $metaKeys) . ' ' . (string)($item['page_builder'] ?? ''));

        $builder = 'Classic/HTML';
        $confidence = 35;
        if (str_contains($haystack, '_elementor_data') || str_contains($haystack, 'elementor') || str_contains($haystack, 'elementor-section') || str_contains($haystack, 'elementor-widget')) {
            $builder = 'Elementor';
            $confidence = 95;
        } elseif (str_contains($haystack, 'wpbakery') || str_contains($haystack, 'vc_row') || str_contains($haystack, 'js_composer')) {
            $builder = 'WPBakery';
            $confidence = 85;
        } elseif (str_contains($haystack, 'et_pb_') || str_contains($haystack, 'divi')) {
            $builder = 'Divi';
            $confidence = 85;
        } elseif (str_contains($haystack, '<!-- wp:')) {
            $builder = 'Gutenberg';
            $confidence = 75;
        } elseif (preg_match('/\[[a-z0-9_\-:]+(?:\s[^\]]*)?\]/i', $raw . $content)) {
            $builder = 'Shortcode Builder';
            $confidence = 65;
        }

        $complexMap = [
            'slider' => ['slider', 'carousel', 'swiper', 'slick', 'rev_slider'],
            'popup' => ['popup', 'modal', 'lightbox'],
            'form' => ['form', 'contact-form-7', 'wpforms', 'gravityform', 'elementor-field'],
            'tabs' => ['tabs', 'accordion', 'toggle'],
            'countdown' => ['countdown', 'timer'],
            'woocommerce' => ['woocommerce', 'add_to_cart', 'product_cat'],
            'custom_js' => ['<script', 'javascript:'],
        ];
        $complex = [];
        foreach ($complexMap as $label => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, strtolower($needle))) {
                    $complex[] = $label;
                    break;
                }
            }
        }
        $complex = array_values(array_unique($complex));

        $warnings = [];
        if ($complex) {
            $warnings[] = 'Widget kompleks terdeteksi: ' . implode(', ', $complex) . '. Import aman sebagai HTML block; elemen interaktif perlu review manual.';
        }
        if (str_contains($haystack, '<script') || str_contains($haystack, 'javascript:')) {
            $warnings[] = 'Script lama akan dibuang oleh sanitizer.';
        }
        if (str_contains($haystack, 'form') || str_contains($haystack, 'contact-form-7') || str_contains($haystack, 'wpforms')) {
            $warnings[] = 'Form WordPress/plugin sebaiknya diganti ke Form Builder U-Growth.';
        }

        return [
            'builder' => $builder,
            'confidence' => $confidence,
            'complex_widgets' => $complex,
            'warnings' => $warnings,
        ];
    }
}

if (!function_exists('wp_elementor_import_html_block')) {
    function wp_elementor_import_html_block(array $item, string $batchId = ''): array
    {
        $content = (string)($item['content'] ?? '');
        $html = wp_elementor_import_sanitize_html($content);
        $detect = wp_elementor_import_detect_builder($item);
        if ($html === '') {
            $html = '<p>' . esc(wp_elementor_import_plain_text($content, 900)) . '</p>';
        }
        return [
            'type' => 'html_block',
            'headline' => (string)($item['title'] ?? 'Konten WordPress'),
            'text' => wp_elementor_import_plain_text($html, 320),
            'html' => $html,
            'bg_color' => '#ffffff',
            'block_goal' => 'trust',
            'animation_style' => 'fade',
            'builder_source' => (string)$detect['builder'],
            'builder_confidence' => (string)$detect['confidence'],
            'complex_widgets' => implode(',', (array)$detect['complex_widgets']),
            'migration_batch_id' => $batchId,
            'legacy_url' => (string)($item['legacy_url'] ?? ''),
            'original_url' => (string)($item['original_url'] ?? ''),
        ];
    }
}

if (!function_exists('wp_elementor_import_native_blocks')) {
    function wp_elementor_import_native_blocks(array $item, string $batchId = ''): array
    {
        $html = wp_elementor_import_sanitize_html((string)($item['content'] ?? ''));
        $blocks = [];

        if (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $m) || preg_match('#<h2[^>]*>(.*?)</h2>#is', $html, $m)) {
            $headline = trim(strip_tags((string)$m[1]));
        } else {
            $headline = (string)($item['title'] ?? 'Halaman WordPress');
        }

        $firstImage = '';
        if (preg_match('#<img[^>]+src\s*=\s*(["\'])(.*?)\1#i', $html, $img)) {
            $firstImage = trim((string)$img[2]);
        }

        $intro = wp_elementor_import_plain_text($html, 420);
        $blocks[] = [
            'type' => 'hero_offer',
            'eyebrow' => 'Import WordPress',
            'headline' => $headline,
            'subheadline' => $intro,
            'image' => $firstImage,
            'image_alt' => $headline,
            'primary_text' => 'Konsultasi',
            'primary_url' => '#form-konsultasi',
            'block_goal' => 'awareness',
            'migration_batch_id' => $batchId,
        ];
        $blocks[] = wp_elementor_import_html_block($item, $batchId);
        return $blocks;
    }
}

if (!function_exists('wp_elementor_import_blocks_for_item')) {
    function wp_elementor_import_blocks_for_item(array $item, string $batchId = '', string $mode = 'safe_html'): array
    {
        $mode = in_array($mode, ['safe_html', 'mixed_native'], true) ? $mode : 'safe_html';
        if ($mode === 'mixed_native') {
            return wp_elementor_import_native_blocks($item, $batchId);
        }
        return [wp_elementor_import_html_block($item, $batchId)];
    }
}

if (!function_exists('wp_elementor_import_jobs')) {
    function wp_elementor_import_jobs(): array
    {
        if (!function_exists('wp_migration_jobs')) {
            return [];
        }
        return wp_migration_jobs(100);
    }
}

if (!function_exists('wp_elementor_import_report')) {
    function wp_elementor_import_report(?string $jobId = null): array
    {
        wp_elementor_import_ensure_storage();
        $rows = [];
        $counts = ['jobs'=>0, 'pages'=>0, 'elementor'=>0, 'page_builder'=>0, 'complex'=>0, 'safe_html_ready'=>0];
        $jobs = wp_elementor_import_jobs();
        foreach ($jobs as $job) {
            $id = (string)($job['id'] ?? '');
            if ($jobId !== null && $jobId !== '' && $id !== $jobId) {
                continue;
            }
            $path = (string)($job['file_path'] ?? '');
            if ($path === '' || !is_file($path) || !function_exists('wp_migration_parse_file')) {
                continue;
            }
            $counts['jobs']++;
            try {
                $items = wp_migration_parse_file($path);
            } catch (Throwable $e) {
                $rows[] = ['job_id'=>$id, 'title'=>'Job gagal dibaca', 'builder'=>'Error', 'warnings'=>[$e->getMessage()], 'safe_html_ready'=>false];
                continue;
            }
            foreach ($items as $item) {
                if ((string)($item['target_type'] ?? '') !== 'landing_page') {
                    continue;
                }
                $detect = wp_elementor_import_detect_builder($item);
                $isBuilder = (string)$detect['builder'] !== 'Classic/HTML';
                $isElementor = (string)$detect['builder'] === 'Elementor';
                $hasComplex = !empty($detect['complex_widgets']);
                $counts['pages']++;
                $counts['safe_html_ready']++;
                if ($isBuilder) { $counts['page_builder']++; }
                if ($isElementor) { $counts['elementor']++; }
                if ($hasComplex) { $counts['complex']++; }
                $rows[] = [
                    'job_id' => $id,
                    'source_index' => $item['source_index'] ?? '',
                    'title' => (string)($item['title'] ?? ''),
                    'slug' => (string)($item['slug'] ?? ''),
                    'legacy_url' => (string)($item['legacy_url'] ?? ''),
                    'builder' => (string)$detect['builder'],
                    'confidence' => (int)$detect['confidence'],
                    'complex_widgets' => (array)$detect['complex_widgets'],
                    'warnings' => array_merge((array)($item['warnings'] ?? []), (array)$detect['warnings']),
                    'safe_html_ready' => true,
                    'excerpt' => wp_elementor_import_plain_text((string)($item['content'] ?? ''), 140),
                ];
            }
        }
        $score = 100;
        if ($counts['pages'] > 0) {
            $score -= min(25, $counts['complex'] * 5);
        }
        $report = [
            'version' => 'V32.97 Elementor Safe HTML Block Import',
            'generated_at' => date('c'),
            'score' => max(60, $score),
            'counts' => $counts,
            'rows' => $rows,
        ];
        wp_elementor_import_write_json(wp_elementor_import_report_path(), $report);
        return $report;
    }
}

if (!function_exists('wp_elementor_import_job_pages')) {
    function wp_elementor_import_job_pages(string $jobId, array $options = []): array
    {
        if (!function_exists('wp_migration_find_job') || !function_exists('wp_migration_parse_file') || !function_exists('landing_page_save')) {
            throw new RuntimeException('Modul migrasi WordPress atau Landing Page Builder belum aktif.');
        }
        $job = wp_migration_find_job($jobId);
        if (!$job) {
            throw new RuntimeException('Job migrasi tidak ditemukan.');
        }
        $path = (string)($job['file_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('File sumber job tidak ditemukan.');
        }
        $items = wp_migration_parse_file($path);
        $mode = (string)($options['mode'] ?? 'safe_html');
        $status = (string)($options['status'] ?? 'draft');
        $result = ['created'=>0, 'skipped'=>0, 'failed'=>0, 'logs'=>[]];
        foreach ($items as $item) {
            if ((string)($item['target_type'] ?? '') !== 'landing_page') {
                continue;
            }
            try {
                $blocks = wp_elementor_import_blocks_for_item($item, $jobId, $mode);
                $page = landing_page_save([
                    'title' => (string)($item['title'] ?? 'Halaman WordPress'),
                    'slug' => (string)($item['slug'] ?? ''),
                    'status' => $status,
                    'layout_mode' => 'website',
                    'hide_header' => false,
                    'hide_footer' => false,
                    'hide_floating_wa' => false,
                    'show_nav_only' => false,
                    'indexable' => false,
                    'meta_title' => (string)($item['meta_title'] ?? $item['title'] ?? ''),
                    'meta_description' => (string)($item['meta_description'] ?? $item['excerpt'] ?? ''),
                    'meta_keywords' => implode(', ', (array)($item['tags'] ?? [])),
                    'tracking_label' => 'Elementor Safe Import - ' . (string)($item['title'] ?? 'Halaman'),
                    'canonical_url' => (string)($item['canonical_url'] ?? ''),
                    'legacy_url' => (string)($item['legacy_url'] ?? ''),
                    'original_url' => (string)($item['original_url'] ?? ''),
                    'wp_post_id' => (string)($item['wp_post_id'] ?? ''),
                    'wp_post_type' => 'page',
                    'page_builder_source' => (string)(wp_elementor_import_detect_builder($item)['builder'] ?? 'WordPress'),
                    'migration_batch_id' => $jobId,
                    'blocks' => $blocks,
                ], ['note'=>'Elementor/Page Builder Safe HTML import batch ' . $jobId, 'action'=>'create']);
                if (!empty($page['id'])) {
                    $result['created']++;
                    $result['logs'][] = 'LP draft dibuat: ' . (string)($page['title'] ?? '');
                } else {
                    $result['skipped']++;
                    $result['logs'][] = 'LP dilewati: ' . (string)($item['title'] ?? '');
                }
            } catch (Throwable $e) {
                $result['failed']++;
                $result['logs'][] = 'Gagal import ' . (string)($item['title'] ?? '') . ': ' . $e->getMessage();
            }
        }
        if (function_exists('activity_log_record')) {
            activity_log_record('import', 'wp_elementor_import', 0, 'Elementor/Page Builder safe import dijalankan.', ['job_id'=>$jobId] + $result);
        }
        return $result;
    }
}
