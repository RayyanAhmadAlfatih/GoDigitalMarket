<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WORDPRESS SHORTCODE & GUTENBERG CLEANER
|--------------------------------------------------------------------------
| V32.96 helper layer for cleaning migrated WordPress content safely.
| - Scan articles, products/services, and landing page blocks.
| - Detect Gutenberg block comments, WordPress/plugin shortcodes, embeds,
|   builder residue, empty tags, and risky script/iframe leftovers.
| - Preview before changing content, create storage backup, and then apply.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('wp_content_cleaner_storage_dir')) {
    function wp_content_cleaner_storage_dir(): string
    {
        return STORAGE_PATH . '/wp-content-cleaner';
    }
}

if (!function_exists('wp_content_cleaner_backup_dir')) {
    function wp_content_cleaner_backup_dir(): string
    {
        return wp_content_cleaner_storage_dir() . '/backups';
    }
}

if (!function_exists('wp_content_cleaner_report_file')) {
    function wp_content_cleaner_report_file(): string
    {
        return wp_content_cleaner_storage_dir() . '/last-scan.json';
    }
}

if (!function_exists('wp_content_cleaner_ensure_storage')) {
    function wp_content_cleaner_ensure_storage(): void
    {
        foreach ([wp_content_cleaner_storage_dir(), wp_content_cleaner_backup_dir()] as $dir) {
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $htaccess = $dir . '/.htaccess';
            if (!is_file($htaccess)) {
                @file_put_contents($htaccess, "Options -Indexes\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Allow,Deny\n    Deny from all\n</IfModule>\n", LOCK_EX);
            }
            $gitkeep = $dir . '/.gitkeep';
            if (!is_file($gitkeep)) { @file_put_contents($gitkeep, '', LOCK_EX); }
        }
    }
}

if (!function_exists('wp_content_cleaner_write_json')) {
    function wp_content_cleaner_write_json(string $path, array $payload): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) && @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('wp_content_cleaner_read_json')) {
    function wp_content_cleaner_read_json(string $path, array $fallback = []): array
    {
        if (!is_file($path)) { return $fallback; }
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : $fallback;
    }
}

if (!function_exists('wp_content_cleaner_create_backup')) {
    function wp_content_cleaner_create_backup(string $label = 'shortcode-cleaner'): string
    {
        wp_content_cleaner_ensure_storage();
        $safeLabel = preg_replace('/[^a-z0-9\-]+/i', '-', $label) ?: 'shortcode-cleaner';
        $dir = wp_content_cleaner_backup_dir() . '/' . date('Ymd_His') . '_' . $safeLabel;
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        foreach (['articles.json', 'products.json', 'landing-pages.json', 'template-content.json'] as $file) {
            $source = STORAGE_PATH . '/' . $file;
            if (is_file($source)) { @copy($source, $dir . '/' . $file); }
        }
        return $dir;
    }
}

if (!function_exists('wp_content_cleaner_shortcodes')) {
    function wp_content_cleaner_shortcodes(): array
    {
        return [
            'drop' => [
                'contact-form-7','gravityform','wpforms','ninja_form','fluentform','formidable','caldera_form','everest_form',
                'rank_math_rich_snippet','rank_math_toc','ez-toc','toc','tocplus','table-of-content','wpseo_breadcrumb',
                'elementor-template','elementor-pro-template','rev_slider','layerslider','smartslider3','soliloquy','metaslider',
                'mailchimp','mc4wp_form','woocommerce_checkout','woocommerce_cart','woocommerce_my_account','products','product_page',
                'wpml_language_selector_widget','recent-posts','recent-posts-widget','related-posts','sharethis','addtoany','social_warfare',
            ],
            'unwrap' => [
                'caption','gallery','embed','video','audio','playlist','su_note','su_box','su_spoiler','su_tabs','su_tab','su_column','su_row',
                'vc_row','vc_column','vc_column_text','vc_section','vc_text_separator','vc_empty_space','vc_single_image','vc_btn','vc_cta',
                'et_pb_section','et_pb_row','et_pb_column','et_pb_text','et_pb_image','et_pb_button','fusion_builder_container','fusion_builder_row','fusion_text','fusion_imageframe',
            ],
        ];
    }
}

if (!function_exists('wp_content_cleaner_detect')) {
    function wp_content_cleaner_detect(string $html): array
    {
        $counts = [
            'gutenberg_comments' => 0,
            'shortcodes' => 0,
            'drop_shortcodes' => 0,
            'unwrap_shortcodes' => 0,
            'unknown_shortcodes' => 0,
            'risky_tags' => 0,
            'empty_tags' => 0,
            'builder_classes' => 0,
        ];
        $samples = [];
        if ($html === '') { return ['counts'=>$counts, 'samples'=>$samples, 'severity'=>'clean']; }

        if (preg_match_all('/<!--\s*\/?wp:[\s\S]*?-->/i', $html, $m)) {
            $counts['gutenberg_comments'] = count($m[0]);
            $samples = array_merge($samples, array_slice($m[0], 0, 4));
        }
        if (preg_match_all('/\[(\/?)([a-zA-Z][a-zA-Z0-9_\-:.]*)(?:\s+[^\]]*)?\]/', $html, $m)) {
            $names = array_map(static fn($n): string => strtolower((string)$n), (array)$m[2]);
            $counts['shortcodes'] = count($names);
            $sets = wp_content_cleaner_shortcodes();
            foreach ($names as $name) {
                if (in_array($name, $sets['drop'], true)) { $counts['drop_shortcodes']++; }
                elseif (in_array($name, $sets['unwrap'], true)) { $counts['unwrap_shortcodes']++; }
                else { $counts['unknown_shortcodes']++; }
            }
            $samples = array_merge($samples, array_slice($m[0], 0, 5));
        }
        if (preg_match_all('/<\s*(script|style)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>|<\s*iframe\b[^>]*>[\s\S]*?<\s*\/\s*iframe\s*>/i', $html, $m)) {
            $counts['risky_tags'] = count($m[0]);
            $samples = array_merge($samples, array_slice($m[0], 0, 3));
        }
        if (preg_match_all('/<p>\s*(?:&nbsp;|\xc2\xa0|\s|<br\s*\/?\s*>)*<\/p>|<div>\s*(?:&nbsp;|\xc2\xa0|\s|<br\s*\/?\s*>)*<\/div>/i', $html, $m)) {
            $counts['empty_tags'] = count($m[0]);
        }
        if (preg_match_all('/\b(?:wp-block-[a-z0-9\-]+|elementor-[a-z0-9\-]+|vc_[a-z0-9_\-]+|et_pb_[a-z0-9_\-]+)\b/i', $html, $m)) {
            $counts['builder_classes'] = count($m[0]);
        }

        $total = array_sum($counts);
        $severity = 'clean';
        if ($counts['risky_tags'] > 0 || $counts['drop_shortcodes'] > 0) { $severity = 'review'; }
        elseif ($total > 0) { $severity = 'needs_cleaning'; }

        $samples = array_values(array_unique(array_map(static function ($sample): string {
            $sample = trim(strip_tags((string)$sample));
            if ($sample === '') { $sample = trim((string)$sample); }
            return function_exists('mb_substr') ? mb_substr($sample, 0, 160) : substr($sample, 0, 160);
        }, $samples)));

        return ['counts'=>$counts, 'samples'=>array_slice($samples, 0, 8), 'severity'=>$severity];
    }
}

if (!function_exists('wp_content_cleaner_remove_gutenberg_comments')) {
    function wp_content_cleaner_remove_gutenberg_comments(string $html): string
    {
        $html = preg_replace('/<!--\s*\/?wp:[\s\S]*?-->/i', '', $html) ?? $html;
        return $html;
    }
}

if (!function_exists('wp_content_cleaner_clean_shortcode_tag')) {
    function wp_content_cleaner_clean_shortcode_tag(string $html, string $tag, string $mode): string
    {
        $tagQuoted = preg_quote($tag, '/');
        if ($mode === 'drop') {
            $html = preg_replace('/\[' . $tagQuoted . '\b[^\]]*\][\s\S]*?\[\/' . $tagQuoted . '\]/i', '', $html) ?? $html;
            $html = preg_replace('/\[' . $tagQuoted . '\b[^\]]*\/?\]/i', '', $html) ?? $html;
            return $html;
        }

        $loop = 0;
        do {
            $before = $html;
            $html = preg_replace('/\[' . $tagQuoted . '\b[^\]]*\]([\s\S]*?)\[\/' . $tagQuoted . '\]/i', '$1', $html) ?? $html;
            $loop++;
        } while ($before !== $html && $loop < 8);
        $html = preg_replace('/\[' . $tagQuoted . '\b[^\]]*\/?\]/i', '', $html) ?? $html;
        $html = preg_replace('/\[\/' . $tagQuoted . '\]/i', '', $html) ?? $html;
        return $html;
    }
}

if (!function_exists('wp_content_cleaner_clean_html')) {
    function wp_content_cleaner_clean_html(string $html, array $options = []): array
    {
        $original = $html;
        $beforeDetect = wp_content_cleaner_detect($html);
        $removeRisky = (bool)($options['remove_risky_tags'] ?? false);
        $preserveUnknown = (bool)($options['preserve_unknown_shortcodes'] ?? true);

        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = wp_content_cleaner_remove_gutenberg_comments($html);

        // Convert common WordPress caption shortcode into a lightweight figure-like block while preserving image/caption text.
        $html = preg_replace_callback('/\[caption\b[^\]]*\]([\s\S]*?)\[\/caption\]/i', static function (array $m): string {
            $inner = trim((string)($m[1] ?? ''));
            if ($inner === '') { return ''; }
            return '<figure class="wp-migrated-caption">' . $inner . '</figure>';
        }, $html) ?? $html;

        $sets = wp_content_cleaner_shortcodes();
        foreach ($sets['drop'] as $tag) { $html = wp_content_cleaner_clean_shortcode_tag($html, $tag, 'drop'); }
        foreach ($sets['unwrap'] as $tag) { $html = wp_content_cleaner_clean_shortcode_tag($html, $tag, 'unwrap'); }

        if (!$preserveUnknown) {
            // Preserve inner text of unknown paired shortcode, remove standalone tags.
            $loop = 0;
            do {
                $before = $html;
                $html = preg_replace('/\[([a-zA-Z][a-zA-Z0-9_\-:.]*)\b[^\]]*\]([\s\S]*?)\[\/\1\]/', '$2', $html) ?? $html;
                $loop++;
            } while ($before !== $html && $loop < 8);
            $html = preg_replace('/\[(\/?)[a-zA-Z][a-zA-Z0-9_\-:.]*(?:\s+[^\]]*)?\/?\]/', '', $html) ?? $html;
        }

        if ($removeRisky) {
            $html = preg_replace('/<\s*(script|style)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/i', '', $html) ?? $html;
            $html = preg_replace('/<\s*iframe\b[^>]*>[\s\S]*?<\s*\/\s*iframe\s*>/i', '', $html) ?? $html;
        }

        $html = preg_replace('/<p>\s*(?:&nbsp;|\xc2\xa0|\s|<br\s*\/?\s*>)*<\/p>/i', '', $html) ?? $html;
        $html = preg_replace('/<div>\s*(?:&nbsp;|\xc2\xa0|\s|<br\s*\/?\s*>)*<\/div>/i', '', $html) ?? $html;
        $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;
        $html = trim($html);

        $afterDetect = wp_content_cleaner_detect($html);
        return [
            'changed' => $html !== $original,
            'before' => $beforeDetect,
            'after' => $afterDetect,
            'cleaned' => $html,
        ];
    }
}

if (!function_exists('wp_content_cleaner_source_record')) {
    function wp_content_cleaner_source_record(string $type, string $id, string $title, string $field, string $content): array
    {
        $detect = wp_content_cleaner_detect($content);
        $key = sha1($type . '|' . $id . '|' . $field);
        return [
            'key' => $key,
            'source_type' => $type,
            'source_id' => $id,
            'source_title' => $title,
            'field' => $field,
            'content_length' => function_exists('mb_strlen') ? mb_strlen($content) : strlen($content),
            'severity' => (string)($detect['severity'] ?? 'clean'),
            'counts' => (array)($detect['counts'] ?? []),
            'samples' => (array)($detect['samples'] ?? []),
            'needs_cleaning' => array_sum((array)($detect['counts'] ?? [])) > 0,
        ];
    }
}

if (!function_exists('wp_content_cleaner_collect_sources')) {
    function wp_content_cleaner_collect_sources(): array
    {
        $records = [];
        $push = static function (string $type, string $id, string $title, string $field, mixed $value) use (&$records): void {
            if (!is_string($value) || trim($value) === '') { return; }
            $record = wp_content_cleaner_source_record($type, $id, $title, $field, $value);
            if (!empty($record['needs_cleaning'])) { $records[$record['key']] = $record; }
        };

        if (function_exists('all_articles')) {
            foreach (all_articles() as $article) {
                $id = (string)($article['id'] ?? $article['slug'] ?? sha1((string)($article['title'] ?? 'article')));
                $title = (string)($article['title'] ?? 'Artikel');
                foreach (['content','excerpt','meta_description','faq_json'] as $field) {
                    $push('article', $id, $title, $field, $article[$field] ?? '');
                }
            }
        }

        if (function_exists('all_products')) {
            foreach (all_products() as $product) {
                $id = (string)($product['id'] ?? $product['slug'] ?? sha1((string)($product['title'] ?? 'product')));
                $title = (string)($product['title'] ?? 'Produk/Layanan');
                foreach (['description','short_description','long_description','content','meta_description'] as $field) {
                    $push('product', $id, $title, $field, $product[$field] ?? '');
                }
                foreach (['features','faq'] as $field) {
                    if (isset($product[$field]) && is_array($product[$field])) {
                        $push('product', $id, $title, $field, json_encode($product[$field], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
                    }
                }
            }
        }

        if (function_exists('landing_page_read_raw')) {
            foreach (landing_page_read_raw() as $page) {
                if (!is_array($page)) { continue; }
                $id = (string)($page['id'] ?? $page['slug'] ?? sha1((string)($page['title'] ?? 'landing')));
                $title = (string)($page['title'] ?? 'Landing Page');
                foreach (['description','meta_description','custom_css','custom_js'] as $field) {
                    $push('landing_page', $id, $title, $field, $page[$field] ?? '');
                }
                foreach ((array)($page['blocks'] ?? []) as $blockIndex => $block) {
                    if (!is_array($block)) { continue; }
                    foreach (['title','subtitle','description','text','html','content','caption'] as $field) {
                        $push('landing_page', $id, $title, 'blocks.' . $blockIndex . '.' . $field, $block[$field] ?? '');
                    }
                    foreach ((array)($block['items'] ?? []) as $itemIndex => $item) {
                        if (!is_array($item)) { continue; }
                        foreach (['title','description','text','html','content','caption'] as $field) {
                            $push('landing_page', $id, $title, 'blocks.' . $blockIndex . '.items.' . $itemIndex . '.' . $field, $item[$field] ?? '');
                        }
                    }
                }
            }
        }

        return array_values($records);
    }
}

if (!function_exists('wp_content_cleaner_scan')) {
    function wp_content_cleaner_scan(array $filters = []): array
    {
        wp_content_cleaner_ensure_storage();
        $q = strtolower(trim((string)($filters['q'] ?? '')));
        $type = trim((string)($filters['type'] ?? 'all'));
        $severity = trim((string)($filters['severity'] ?? 'all'));
        $rows = wp_content_cleaner_collect_sources();
        $rows = array_values(array_filter($rows, static function (array $row) use ($q, $type, $severity): bool {
            if ($type !== 'all' && (string)($row['source_type'] ?? '') !== $type) { return false; }
            if ($severity !== 'all' && (string)($row['severity'] ?? '') !== $severity) { return false; }
            if ($q !== '') {
                $haystack = strtolower((string)($row['source_title'] ?? '') . ' ' . (string)($row['field'] ?? '') . ' ' . implode(' ', (array)($row['samples'] ?? [])));
                if (!str_contains($haystack, $q)) { return false; }
            }
            return true;
        }));

        $counts = ['total'=>count($rows),'articles'=>0,'products'=>0,'landing_pages'=>0,'review'=>0,'needs_cleaning'=>0,'clean'=>0,'gutenberg_comments'=>0,'shortcodes'=>0,'drop_shortcodes'=>0,'unknown_shortcodes'=>0,'risky_tags'=>0];
        foreach ($rows as $row) {
            $sourceType = (string)($row['source_type'] ?? '');
            if ($sourceType === 'article') { $counts['articles']++; }
            elseif ($sourceType === 'product') { $counts['products']++; }
            elseif ($sourceType === 'landing_page') { $counts['landing_pages']++; }
            $sev = (string)($row['severity'] ?? 'clean');
            if (isset($counts[$sev])) { $counts[$sev]++; }
            $c = (array)($row['counts'] ?? []);
            foreach (['gutenberg_comments','shortcodes','drop_shortcodes','unknown_shortcodes','risky_tags'] as $key) { $counts[$key] += (int)($c[$key] ?? 0); }
        }
        $penalty = min(75, ($counts['review'] * 8) + ($counts['needs_cleaning'] * 4) + min(20, $counts['unknown_shortcodes']));
        $score = max(0, 100 - $penalty);
        $report = ['version'=>'V32.96','generated_at'=>date('c'),'health_score'=>$score,'counts'=>$counts,'rows'=>$rows];
        wp_content_cleaner_write_json(wp_content_cleaner_report_file(), $report);
        return $report;
    }
}

if (!function_exists('wp_content_cleaner_clean_value')) {
    function wp_content_cleaner_clean_value(mixed $value, array $options = []): mixed
    {
        if (is_string($value)) { return wp_content_cleaner_clean_html($value, $options)['cleaned']; }
        if (is_array($value)) {
            foreach ($value as $key => $entry) { $value[$key] = wp_content_cleaner_clean_value($entry, $options); }
            return $value;
        }
        return $value;
    }
}

if (!function_exists('wp_content_cleaner_apply_to_collection')) {
    function wp_content_cleaner_apply_to_collection(array $records, array $fields, array $options = []): array
    {
        $changed = 0;
        foreach ($records as &$record) {
            if (!is_array($record)) { continue; }
            $before = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($fields as $field) {
                if (array_key_exists($field, $record)) { $record[$field] = wp_content_cleaner_clean_value($record[$field], $options); }
            }
            $after = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($before !== $after) { $changed++; }
        }
        unset($record);
        return ['records'=>$records,'changed'=>$changed];
    }
}

if (!function_exists('wp_content_cleaner_run')) {
    function wp_content_cleaner_run(bool $dryRun = true, array $options = []): array
    {
        wp_content_cleaner_ensure_storage();
        $options = array_merge(['remove_risky_tags'=>true, 'preserve_unknown_shortcodes'=>true], $options);
        $result = ['dry_run'=>$dryRun,'changed_sources'=>0,'articles'=>0,'products'=>0,'landing_pages'=>0,'backup_dir'=>''];
        $backupDir = $dryRun ? '' : wp_content_cleaner_create_backup('shortcode-gutenberg-cleaner');
        $result['backup_dir'] = $backupDir;

        if (function_exists('article_storage_path') && function_exists('article_write_json') && is_file(article_storage_path())) {
            $decoded = json_decode((string)@file_get_contents(article_storage_path()), true);
            $articles = is_array($decoded) ? (isset($decoded['articles']) && is_array($decoded['articles']) ? $decoded['articles'] : $decoded) : [];
            $cleaned = wp_content_cleaner_apply_to_collection($articles, ['content','excerpt','meta_description','faq_json'], $options);
            $result['articles'] = (int)$cleaned['changed'];
            $result['changed_sources'] += (int)$cleaned['changed'];
            if (!$dryRun && $cleaned['changed'] > 0) { article_write_json((array)$cleaned['records']); }
        }

        if (function_exists('product_json_read') && function_exists('product_write_json')) {
            $products = product_json_read();
            $cleaned = wp_content_cleaner_apply_to_collection($products, ['description','short_description','long_description','content','meta_description','features','faq'], $options);
            $result['products'] = (int)$cleaned['changed'];
            $result['changed_sources'] += (int)$cleaned['changed'];
            if (!$dryRun && $cleaned['changed'] > 0) { product_write_json((array)$cleaned['records']); }
        }

        if (function_exists('landing_page_read_raw') && function_exists('landing_page_write_raw')) {
            $pages = landing_page_read_raw();
            $changed = 0;
            foreach ($pages as &$page) {
                if (!is_array($page)) { continue; }
                $before = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                foreach (['description','meta_description','custom_css','custom_js'] as $field) {
                    if (isset($page[$field])) { $page[$field] = wp_content_cleaner_clean_value($page[$field], $options); }
                }
                foreach ((array)($page['blocks'] ?? []) as $blockIndex => $block) {
                    if (!is_array($block)) { continue; }
                    foreach (['title','subtitle','description','text','html','content','caption'] as $field) {
                        if (isset($page['blocks'][$blockIndex][$field])) { $page['blocks'][$blockIndex][$field] = wp_content_cleaner_clean_value($page['blocks'][$blockIndex][$field], $options); }
                    }
                    foreach ((array)($block['items'] ?? []) as $itemIndex => $item) {
                        if (!is_array($item)) { continue; }
                        foreach (['title','description','text','html','content','caption'] as $field) {
                            if (isset($page['blocks'][$blockIndex]['items'][$itemIndex][$field])) { $page['blocks'][$blockIndex]['items'][$itemIndex][$field] = wp_content_cleaner_clean_value($page['blocks'][$blockIndex]['items'][$itemIndex][$field], $options); }
                        }
                    }
                }
                $after = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($before !== $after) { $changed++; }
            }
            unset($page);
            $result['landing_pages'] = $changed;
            $result['changed_sources'] += $changed;
            if (!$dryRun && $changed > 0) { landing_page_write_raw($pages); }
        }

        if (!$dryRun && function_exists('activity_log_record')) {
            activity_log_record('clean', 'wp_content_cleaner', null, 'Bersihkan shortcode dan Gutenberg residue WordPress.', $result);
        }
        wp_content_cleaner_scan(['type'=>'all']);
        return $result;
    }
}

if (!function_exists('wp_content_cleaner_report')) {
    function wp_content_cleaner_report(): array
    {
        return wp_content_cleaner_scan(['type'=>'all']);
    }
}
