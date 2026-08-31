<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WORDPRESS MEDIA MIGRATION
|--------------------------------------------------------------------------
| V32.95 helper layer for moving WordPress media safely into U-Growth.
| - Scan remote/wp-content images across articles, products, and landing pages.
| - Download selected remote images to local assets/uploads/wp-migration.
| - Keep an origin map so content can be rewritten safely after review.
| - Backup storage before rewriting any content/image field.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('wp_media_migration_storage_dir')) {
    function wp_media_migration_storage_dir(): string
    {
        return STORAGE_PATH . '/wp-media-migration';
    }
}

if (!function_exists('wp_media_migration_download_dir')) {
    function wp_media_migration_download_dir(): string
    {
        return ROOT_PATH . '/assets/uploads/wp-migration';
    }
}

if (!function_exists('wp_media_migration_download_url_base')) {
    function wp_media_migration_download_url_base(): string
    {
        return 'assets/uploads/wp-migration';
    }
}

if (!function_exists('wp_media_migration_map_file')) {
    function wp_media_migration_map_file(): string
    {
        return wp_media_migration_storage_dir() . '/media-map.json';
    }
}

if (!function_exists('wp_media_migration_report_file')) {
    function wp_media_migration_report_file(): string
    {
        return wp_media_migration_storage_dir() . '/last-scan.json';
    }
}

if (!function_exists('wp_media_migration_backup_dir')) {
    function wp_media_migration_backup_dir(): string
    {
        return wp_media_migration_storage_dir() . '/backups';
    }
}

if (!function_exists('wp_media_migration_ensure_storage')) {
    function wp_media_migration_ensure_storage(): void
    {
        foreach ([wp_media_migration_storage_dir(), wp_media_migration_backup_dir()] as $dir) {
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $htaccess = $dir . '/.htaccess';
            if (!is_file($htaccess)) {
                @file_put_contents($htaccess, "Options -Indexes\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order Allow,Deny\n    Deny from all\n</IfModule>\n", LOCK_EX);
            }
            $gitkeep = $dir . '/.gitkeep';
            if (!is_file($gitkeep)) { @file_put_contents($gitkeep, '', LOCK_EX); }
        }
        $downloadDir = wp_media_migration_download_dir();
        if (!is_dir($downloadDir)) { @mkdir($downloadDir, 0775, true); }
    }
}

if (!function_exists('wp_media_migration_read_json')) {
    function wp_media_migration_read_json(string $path, array $fallback = []): array
    {
        if (!is_file($path)) { return $fallback; }
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : $fallback;
    }
}

if (!function_exists('wp_media_migration_write_json')) {
    function wp_media_migration_write_json(string $path, array $payload): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) && @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('wp_media_migration_allowed_extensions')) {
    function wp_media_migration_allowed_extensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];
    }
}

if (!function_exists('wp_media_migration_normalize_url')) {
    function wp_media_migration_normalize_url(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') { return ''; }
        $url = preg_replace('/\s+/', '', $url) ?? $url;
        return $url;
    }
}

if (!function_exists('wp_media_migration_is_remote_url')) {
    function wp_media_migration_is_remote_url(string $url): bool
    {
        return (bool)preg_match('#^https?://#i', trim($url));
    }
}

if (!function_exists('wp_media_migration_is_wp_upload_url')) {
    function wp_media_migration_is_wp_upload_url(string $url): bool
    {
        $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?? $url));
        return str_contains($path, '/wp-content/uploads/') || str_contains($path, '/wp-content/uploads-webpc/') || str_contains($path, '/wp-content/ewww/') || str_contains($path, '/wp-content/cache/');
    }
}

if (!function_exists('wp_media_migration_url_extension')) {
    function wp_media_migration_url_extension(string $url): string
    {
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? $url);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return preg_replace('/[^a-z0-9]/', '', $ext) ?: '';
    }
}

if (!function_exists('wp_media_migration_is_supported_image_url')) {
    function wp_media_migration_is_supported_image_url(string $url): bool
    {
        $ext = wp_media_migration_url_extension($url);
        return in_array($ext, wp_media_migration_allowed_extensions(), true);
    }
}

if (!function_exists('wp_media_migration_context_slug')) {
    function wp_media_migration_context_slug(string $context, string $fallback = 'wordpress-media'): string
    {
        $context = trim(strip_tags($context));
        $slug = function_exists('slugify') ? slugify($context) : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $context) ?? '');
        $slug = trim((string)$slug, '-');
        if ($slug === '') { $slug = $fallback; }
        $parts = array_slice(array_values(array_filter(explode('-', $slug))), 0, 10);
        return implode('-', $parts) ?: $fallback;
    }
}

if (!function_exists('wp_media_migration_suggest_relative')) {
    function wp_media_migration_suggest_relative(string $url, array $source = []): string
    {
        $ext = wp_media_migration_url_extension($url) ?: 'jpg';
        if (!in_array($ext, wp_media_migration_allowed_extensions(), true)) { $ext = 'jpg'; }
        $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
        $year = date('Y');
        $month = date('m');
        if (preg_match('#/uploads/(\d{4})/(\d{2})/#', $path, $m)) {
            $year = (string)$m[1];
            $month = (string)$m[2];
        }
        $title = trim((string)($source['source_title'] ?? $source['title'] ?? ''));
        $original = pathinfo($path, PATHINFO_FILENAME);
        $base = wp_media_migration_context_slug($title !== '' ? $title : $original, 'wordpress-media');
        $suffix = substr(sha1(strtolower($url)), 0, 8);
        return wp_media_migration_download_url_base() . '/' . $year . '/' . $month . '/' . $base . '-' . $suffix . '.' . $ext;
    }
}

if (!function_exists('wp_media_migration_local_path')) {
    function wp_media_migration_local_path(string $relative): string
    {
        return ROOT_PATH . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }
}

if (!function_exists('wp_media_migration_public_url')) {
    function wp_media_migration_public_url(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if (str_starts_with($relative, 'assets/')) {
            return asset(substr($relative, 7));
        }
        return asset($relative);
    }
}

if (!function_exists('wp_media_migration_extract_urls_from_html')) {
    function wp_media_migration_extract_urls_from_html(string $html): array
    {
        if (trim($html) === '') { return []; }
        $urls = [];
        if (preg_match_all('/<img\s+[^>]*(?:src|data-src|data-lazy-src)\s*=\s*(["\'])(.*?)\1[^>]*>/i', $html, $matches)) {
            foreach ((array)($matches[2] ?? []) as $url) { $urls[] = wp_media_migration_normalize_url((string)$url); }
        }
        if (preg_match_all('/(?:srcset|data-srcset)\s*=\s*(["\'])(.*?)\1/i', $html, $srcsets)) {
            foreach ((array)($srcsets[2] ?? []) as $srcset) {
                foreach (explode(',', (string)$srcset) as $part) {
                    $candidate = trim(preg_split('/\s+/', trim($part))[0] ?? '');
                    if ($candidate !== '') { $urls[] = wp_media_migration_normalize_url($candidate); }
                }
            }
        }
        if (preg_match_all('/url\(([^\)]+)\)/i', $html, $cssUrls)) {
            foreach ((array)($cssUrls[1] ?? []) as $url) {
                $urls[] = wp_media_migration_normalize_url(trim((string)$url, " \t\n\r\0\x0B'\""));
            }
        }
        $urls = array_values(array_unique(array_filter($urls, static fn(string $u): bool => $u !== '')));
        return $urls;
    }
}

if (!function_exists('wp_media_migration_source_record')) {
    function wp_media_migration_source_record(string $type, string $id, string $title, string $field, string $url, array $extra = []): array
    {
        $url = wp_media_migration_normalize_url($url);
        $key = sha1(strtolower($type . '|' . $id . '|' . $field . '|' . $url));
        return array_merge([
            'key' => $key,
            'source_type' => $type,
            'source_id' => $id,
            'source_title' => $title,
            'field' => $field,
            'url' => $url,
            'is_remote' => wp_media_migration_is_remote_url($url),
            'is_wordpress_upload' => wp_media_migration_is_wp_upload_url($url),
            'is_supported_image' => wp_media_migration_is_supported_image_url($url),
            'extension' => wp_media_migration_url_extension($url),
            'host' => strtolower((string)(parse_url($url, PHP_URL_HOST) ?? '')),
        ], $extra);
    }
}

if (!function_exists('wp_media_migration_collect_sources')) {
    function wp_media_migration_collect_sources(): array
    {
        $records = [];
        $push = static function (array $record) use (&$records): void {
            $url = (string)($record['url'] ?? '');
            if ($url === '') { return; }
            if (!wp_media_migration_is_supported_image_url($url)) { return; }
            $records[$record['key']] = $record;
        };

        foreach (function_exists('all_articles') ? all_articles() : [] as $article) {
            $id = (string)($article['id'] ?? $article['slug'] ?? '');
            $title = (string)($article['title'] ?? 'Artikel');
            foreach (['image', 'featured_image', 'og_image'] as $field) {
                if (!empty($article[$field])) {
                    $push(wp_media_migration_source_record('article', $id, $title, $field, (string)$article[$field], ['edit_url'=>url('admin/artikel'), 'view_url'=>function_exists('article_permalink') ? article_permalink($article) : url('artikel')]));
                }
            }
            foreach (wp_media_migration_extract_urls_from_html((string)($article['content'] ?? '')) as $url) {
                $push(wp_media_migration_source_record('article', $id, $title, 'content', $url, ['edit_url'=>url('admin/artikel'), 'view_url'=>function_exists('article_permalink') ? article_permalink($article) : url('artikel')]));
            }
        }

        foreach (function_exists('all_products') ? all_products() : [] as $product) {
            $id = (string)($product['id'] ?? $product['slug'] ?? '');
            $title = (string)($product['title'] ?? 'Produk/Layanan');
            foreach (['image', 'featured_image', 'og_image'] as $field) {
                if (!empty($product[$field])) {
                    $push(wp_media_migration_source_record('product', $id, $title, $field, (string)$product[$field], ['edit_url'=>url('admin/produk'), 'view_url'=>function_exists('product_permalink') ? product_permalink($product) : url('katalog')]));
                }
            }
            foreach ((array)($product['gallery'] ?? []) as $galleryIndex => $url) {
                $push(wp_media_migration_source_record('product', $id, $title, 'gallery.' . (int)$galleryIndex, (string)$url, ['edit_url'=>url('admin/produk'), 'view_url'=>function_exists('product_permalink') ? product_permalink($product) : url('katalog')]));
            }
            foreach (['description', 'long_description', 'content'] as $field) {
                foreach (wp_media_migration_extract_urls_from_html((string)($product[$field] ?? '')) as $url) {
                    $push(wp_media_migration_source_record('product', $id, $title, $field, $url, ['edit_url'=>url('admin/produk'), 'view_url'=>function_exists('product_permalink') ? product_permalink($product) : url('katalog')]));
                }
            }
        }

        foreach (function_exists('landing_page_all') ? landing_page_all(true) : [] as $page) {
            $id = (string)($page['id'] ?? $page['slug'] ?? '');
            $title = (string)($page['title'] ?? 'Landing Page');
            if (!empty($page['og_image'])) {
                $push(wp_media_migration_source_record('landing_page', $id, $title, 'og_image', (string)$page['og_image'], ['edit_url'=>url('admin/landing-pages'), 'view_url'=>!empty($page['slug']) ? url('lp/' . (string)$page['slug']) : url('admin/landing-pages')]));
            }
            foreach ((array)($page['blocks'] ?? []) as $blockIndex => $block) {
                if (!is_array($block)) { continue; }
                if (!empty($block['image'])) {
                    $push(wp_media_migration_source_record('landing_page', $id, $title, 'blocks.' . (int)$blockIndex . '.image', (string)$block['image'], ['edit_url'=>url('admin/landing-pages'), 'view_url'=>!empty($page['slug']) ? url('lp/' . (string)$page['slug']) : url('admin/landing-pages')]));
                }
                foreach (['text', 'html', 'description', 'content'] as $field) {
                    foreach (wp_media_migration_extract_urls_from_html((string)($block[$field] ?? '')) as $url) {
                        $push(wp_media_migration_source_record('landing_page', $id, $title, 'blocks.' . (int)$blockIndex . '.' . $field, $url, ['edit_url'=>url('admin/landing-pages'), 'view_url'=>!empty($page['slug']) ? url('lp/' . (string)$page['slug']) : url('admin/landing-pages')]));
                    }
                }
                foreach ((array)($block['items'] ?? []) as $itemIndex => $item) {
                    if (!is_array($item)) { continue; }
                    if (!empty($item['image'])) {
                        $push(wp_media_migration_source_record('landing_page', $id, $title, 'blocks.' . (int)$blockIndex . '.items.' . (int)$itemIndex . '.image', (string)$item['image'], ['edit_url'=>url('admin/landing-pages'), 'view_url'=>!empty($page['slug']) ? url('lp/' . (string)$page['slug']) : url('admin/landing-pages')]));
                    }
                    foreach (['text', 'html', 'description', 'content'] as $field) {
                        foreach (wp_media_migration_extract_urls_from_html((string)($item[$field] ?? '')) as $url) {
                            $push(wp_media_migration_source_record('landing_page', $id, $title, 'blocks.' . (int)$blockIndex . '.items.' . (int)$itemIndex . '.' . $field, $url, ['edit_url'=>url('admin/landing-pages'), 'view_url'=>!empty($page['slug']) ? url('lp/' . (string)$page['slug']) : url('admin/landing-pages')]));
                        }
                    }
                }
            }
        }

        return array_values($records);
    }
}

if (!function_exists('wp_media_migration_map_state')) {
    function wp_media_migration_map_state(): array
    {
        wp_media_migration_ensure_storage();
        return wp_media_migration_read_json(wp_media_migration_map_file(), ['version' => 'V32.95', 'items' => []]);
    }
}

if (!function_exists('wp_media_migration_save_map_state')) {
    function wp_media_migration_save_map_state(array $state): bool
    {
        $state['version'] = 'V32.95';
        $state['updated_at'] = date('c');
        return wp_media_migration_write_json(wp_media_migration_map_file(), $state);
    }
}

if (!function_exists('wp_media_migration_map_by_url')) {
    function wp_media_migration_map_by_url(): array
    {
        $state = wp_media_migration_map_state();
        $map = [];
        foreach ((array)($state['items'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $url = wp_media_migration_normalize_url((string)($item['remote_url'] ?? ''));
            if ($url !== '') { $map[$url] = $item; }
        }
        return $map;
    }
}

if (!function_exists('wp_media_migration_scan')) {
    function wp_media_migration_scan(array $options = []): array
    {
        wp_media_migration_ensure_storage();
        $records = wp_media_migration_collect_sources();
        $map = wp_media_migration_map_by_url();
        $query = strtolower(trim((string)($options['q'] ?? '')));
        $status = trim((string)($options['status'] ?? 'all'));
        $rows = [];
        $counts = ['total'=>0,'remote'=>0,'wp_uploads'=>0,'downloaded'=>0,'not_downloaded'=>0,'local'=>0,'unsupported'=>0,'rewrite_ready'=>0,'external_remote'=>0];

        foreach ($records as $record) {
            $url = (string)($record['url'] ?? '');
            $mapped = $map[$url] ?? null;
            $isRemote = !empty($record['is_remote']);
            $relative = is_array($mapped) ? (string)($mapped['local_relative'] ?? '') : '';
            if (!$isRemote) {
                // Local media is already usable as-is. Do not invent a wp-migration path
                // for local placeholders/assets because that can confuse the launch audit
                // and make the report look like a missing downloaded file.
                $relative = ltrim((string)parse_url($url, PHP_URL_PATH), '/');
                if ($relative === '') {
                    $relative = ltrim($url, '/');
                }
            } elseif ($relative === '') {
                $relative = wp_media_migration_suggest_relative($url, $record);
            }
            $localPath = wp_media_migration_local_path($relative);
            $isDownloaded = is_file($localPath);
            $isWp = !empty($record['is_wordpress_upload']);
            $rowStatus = !$isRemote ? 'local' : ($isDownloaded ? 'downloaded' : ($isWp ? 'wp_remote' : 'external_remote'));
            if ($status !== 'all' && $status !== $rowStatus) { continue; }
            $haystack = strtolower(implode(' ', [$url, $record['source_title'] ?? '', $record['source_type'] ?? '', $record['field'] ?? '', $relative]));
            if ($query !== '' && !str_contains($haystack, $query)) { continue; }
            $row = array_merge($record, [
                'status' => $rowStatus,
                'local_relative' => $relative,
                'local_url' => wp_media_migration_public_url($relative),
                'local_exists' => $isDownloaded,
                'downloaded_at' => is_array($mapped) ? (string)($mapped['downloaded_at'] ?? '') : '',
                'file_size' => $isDownloaded ? filesize($localPath) : 0,
            ]);
            $rows[] = $row;
        }

        foreach ($rows as $row) {
            $counts['total']++;
            if (!empty($row['is_remote'])) { $counts['remote']++; }
            else { $counts['local']++; }
            if (!empty($row['is_wordpress_upload'])) { $counts['wp_uploads']++; }
            if ((string)($row['status'] ?? '') === 'downloaded') { $counts['downloaded']++; }
            if ((string)($row['status'] ?? '') === 'wp_remote') { $counts['not_downloaded']++; }
            if ((string)($row['status'] ?? '') === 'external_remote') { $counts['external_remote']++; }
            if (empty($row['is_supported_image'])) { $counts['unsupported']++; }
            if (!empty($row['local_exists']) && !empty($row['is_remote'])) { $counts['rewrite_ready']++; }
        }

        usort($rows, static function (array $a, array $b): int {
            $rank = ['wp_remote'=>5,'external_remote'=>4,'downloaded'=>3,'local'=>1];
            $r = ($rank[(string)($b['status'] ?? '')] ?? 0) <=> ($rank[(string)($a['status'] ?? '')] ?? 0);
            if ($r !== 0) { return $r; }
            return strcmp((string)($a['source_title'] ?? ''), (string)($b['source_title'] ?? ''));
        });

        $score = 100;
        if ($counts['wp_uploads'] > 0) { $score -= min(35, $counts['not_downloaded'] * 8); }
        if ($counts['external_remote'] > 0) { $score -= min(15, $counts['external_remote'] * 3); }
        $score = max(0, min(100, $score));
        $report = ['version'=>'V32.95','generated_at'=>date('c'),'health_score'=>$score,'counts'=>$counts,'rows'=>$rows];
        wp_media_migration_write_json(wp_media_migration_report_file(), $report);
        return $report;
    }
}

if (!function_exists('wp_media_migration_create_backup')) {
    function wp_media_migration_create_backup(string $label = 'media-rewrite'): string
    {
        wp_media_migration_ensure_storage();
        $dir = wp_media_migration_backup_dir() . '/' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9\-]+/i', '-', $label);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        foreach (['articles.json', 'products.json', 'landing-pages.json', 'template-content.json'] as $file) {
            $source = STORAGE_PATH . '/' . $file;
            if (is_file($source)) { @copy($source, $dir . '/' . $file); }
        }
        return $dir;
    }
}

if (!function_exists('wp_media_migration_download_remote_file')) {
    function wp_media_migration_download_remote_file(string $remoteUrl, string $relative): array
    {
        if (!wp_media_migration_is_remote_url($remoteUrl)) { return ['ok'=>false,'error'=>'URL bukan remote URL.']; }
        if (!wp_media_migration_is_supported_image_url($remoteUrl)) { return ['ok'=>false,'error'=>'Ekstensi gambar belum didukung.']; }
        $target = wp_media_migration_local_path($relative);
        $dir = dirname($target);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        if (is_file($target) && filesize($target) > 0) { return ['ok'=>true,'skipped'=>true,'path'=>$target,'size'=>filesize($target)]; }
        $context = stream_context_create([
            'http' => ['timeout' => 12, 'follow_location' => 1, 'user_agent' => 'U-Growth WordPress Media Migration/32.95'],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $data = @file_get_contents($remoteUrl, false, $context);
        if (!is_string($data) || $data === '') { return ['ok'=>false,'error'=>'Gagal download atau file kosong.']; }
        if (strlen($data) > 12 * 1024 * 1024) { return ['ok'=>false,'error'=>'File lebih dari 12 MB, skip untuk keamanan shared hosting.']; }
        $written = @file_put_contents($target, $data, LOCK_EX);
        if ($written === false) { return ['ok'=>false,'error'=>'Gagal menyimpan file lokal.']; }
        return ['ok'=>true,'skipped'=>false,'path'=>$target,'size'=>(int)$written];
    }
}

if (!function_exists('wp_media_migration_download_candidates')) {
    function wp_media_migration_download_candidates(array $options = []): array
    {
        wp_media_migration_ensure_storage();
        $limit = max(1, min(100, (int)($options['limit'] ?? 20)));
        $dryRun = !empty($options['dry_run']);
        $includeExternal = !empty($options['include_external']);
        $scan = wp_media_migration_scan(['status'=>'all']);
        $state = wp_media_migration_map_state();
        $items = array_values(array_filter((array)($state['items'] ?? []), 'is_array'));
        $byUrl = [];
        foreach ($items as $item) { $byUrl[(string)($item['remote_url'] ?? '')] = $item; }
        $done = ['attempted'=>0,'downloaded'=>0,'skipped'=>0,'failed'=>0,'dry_run'=>$dryRun,'items'=>[]];
        foreach ((array)($scan['rows'] ?? []) as $row) {
            if ($done['attempted'] >= $limit) { break; }
            if (empty($row['is_remote']) || empty($row['is_supported_image'])) { continue; }
            if (empty($row['is_wordpress_upload']) && !$includeExternal) { continue; }
            if (!empty($row['local_exists'])) { continue; }
            $remote = (string)($row['url'] ?? '');
            $relative = (string)($row['local_relative'] ?? wp_media_migration_suggest_relative($remote, $row));
            if ($remote === '') { continue; }
            $done['attempted']++;
            if ($dryRun) {
                $done['items'][] = ['remote_url'=>$remote,'local_relative'=>$relative,'status'=>'dry_run'];
                continue;
            }
            $result = wp_media_migration_download_remote_file($remote, $relative);
            if (!empty($result['ok'])) {
                $entry = [
                    'remote_url' => $remote,
                    'local_relative' => $relative,
                    'local_url' => wp_media_migration_public_url($relative),
                    'source_title' => (string)($row['source_title'] ?? ''),
                    'source_type' => (string)($row['source_type'] ?? ''),
                    'field' => (string)($row['field'] ?? ''),
                    'size' => (int)($result['size'] ?? 0),
                    'downloaded_at' => date('c'),
                    'status' => !empty($result['skipped']) ? 'already_exists' : 'downloaded',
                ];
                $byUrl[$remote] = $entry;
                $done[!empty($result['skipped']) ? 'skipped' : 'downloaded']++;
                $done['items'][] = $entry;
            } else {
                $done['failed']++;
                $done['items'][] = ['remote_url'=>$remote,'local_relative'=>$relative,'status'=>'failed','error'=>(string)($result['error'] ?? 'Gagal download')];
            }
        }
        if (!$dryRun) {
            $state['items'] = array_values($byUrl);
            wp_media_migration_save_map_state($state);
            if (function_exists('activity_log_record')) {
                activity_log_record('download', 'wp_media_migration', null, 'Download media WordPress.', $done);
            }
        }
        return $done;
    }
}

if (!function_exists('wp_media_migration_replace_value')) {
    function wp_media_migration_replace_value(string $value, array $map): string
    {
        if ($value === '' || !$map) { return $value; }
        $search = [];
        $replace = [];
        foreach ($map as $remote => $relative) {
            if ($remote === '' || $relative === '') { continue; }
            $search[] = $remote;
            $replace[] = wp_media_migration_public_url($relative);
        }
        return str_replace($search, $replace, $value);
    }
}

if (!function_exists('wp_media_migration_rewrite_downloaded_media')) {
    function wp_media_migration_rewrite_downloaded_media(bool $dryRun = true): array
    {
        $state = wp_media_migration_map_state();
        $map = [];
        foreach ((array)($state['items'] ?? []) as $item) {
            if (!is_array($item)) { continue; }
            $remote = wp_media_migration_normalize_url((string)($item['remote_url'] ?? ''));
            $relative = (string)($item['local_relative'] ?? '');
            if ($remote !== '' && $relative !== '' && is_file(wp_media_migration_local_path($relative))) { $map[$remote] = $relative; }
        }
        $result = ['dry_run'=>$dryRun,'replacements'=>0,'changed_sources'=>0,'articles'=>0,'products'=>0,'landing_pages'=>0,'backup_dir'=>'','map_count'=>count($map)];
        if (!$map) { return $result; }
        $backupDir = $dryRun ? '' : wp_media_migration_create_backup('media-rewrite');
        $result['backup_dir'] = $backupDir;

        if (function_exists('article_storage_path') && function_exists('article_write_json') && is_file(article_storage_path())) {
            $decoded = json_decode((string)@file_get_contents(article_storage_path()), true);
            $articles = is_array($decoded) ? (isset($decoded['articles']) && is_array($decoded['articles']) ? $decoded['articles'] : $decoded) : [];
            $changed = false;
            foreach ($articles as &$article) {
                if (!is_array($article)) { continue; }
                $before = json_encode($article, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                foreach (['image','featured_image','og_image','content'] as $field) {
                    if (isset($article[$field]) && is_string($article[$field])) {
                        $article[$field] = wp_media_migration_replace_value($article[$field], $map);
                    }
                }
                $after = json_encode($article, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($before !== $after) { $changed = true; $result['articles']++; $result['changed_sources']++; }
            }
            unset($article);
            if ($changed && !$dryRun) { article_write_json($articles); }
        }

        if (function_exists('product_json_read') && function_exists('product_write_json')) {
            $products = product_json_read();
            $changed = false;
            foreach ($products as &$product) {
                if (!is_array($product)) { continue; }
                $before = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                foreach (['image','featured_image','og_image','description','long_description','content'] as $field) {
                    if (isset($product[$field]) && is_string($product[$field])) {
                        $product[$field] = wp_media_migration_replace_value($product[$field], $map);
                    }
                }
                if (isset($product['gallery']) && is_array($product['gallery'])) {
                    foreach ($product['gallery'] as &$galleryUrl) {
                        if (is_string($galleryUrl)) { $galleryUrl = wp_media_migration_replace_value($galleryUrl, $map); }
                    }
                    unset($galleryUrl);
                }
                $after = json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($before !== $after) { $changed = true; $result['products']++; $result['changed_sources']++; }
            }
            unset($product);
            if ($changed && !$dryRun) { product_write_json($products); }
        }

        if (function_exists('landing_page_read_raw') && function_exists('landing_page_write_raw')) {
            $pages = landing_page_read_raw();
            $changed = false;
            foreach ($pages as &$page) {
                if (!is_array($page)) { continue; }
                $before = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (isset($page['og_image']) && is_string($page['og_image'])) { $page['og_image'] = wp_media_migration_replace_value($page['og_image'], $map); }
                foreach ((array)($page['blocks'] ?? []) as $blockIndex => $block) {
                    if (!is_array($block)) { continue; }
                    foreach (['image','text','html','description','content'] as $field) {
                        if (isset($page['blocks'][$blockIndex][$field]) && is_string($page['blocks'][$blockIndex][$field])) {
                            $page['blocks'][$blockIndex][$field] = wp_media_migration_replace_value($page['blocks'][$blockIndex][$field], $map);
                        }
                    }
                    foreach ((array)($block['items'] ?? []) as $itemIndex => $entry) {
                        if (!is_array($entry)) { continue; }
                        foreach (['image','text','html','description','content'] as $field) {
                            if (isset($page['blocks'][$blockIndex]['items'][$itemIndex][$field]) && is_string($page['blocks'][$blockIndex]['items'][$itemIndex][$field])) {
                                $page['blocks'][$blockIndex]['items'][$itemIndex][$field] = wp_media_migration_replace_value($page['blocks'][$blockIndex]['items'][$itemIndex][$field], $map);
                            }
                        }
                    }
                }
                $after = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($before !== $after) { $changed = true; $result['landing_pages']++; $result['changed_sources']++; }
            }
            unset($page);
            if ($changed && !$dryRun) { landing_page_write_raw($pages); }
        }

        if (!$dryRun && function_exists('activity_log_record')) {
            activity_log_record('rewrite', 'wp_media_migration', null, 'Rewrite URL media WordPress ke lokal.', $result);
        }
        $result['replacements'] = $result['changed_sources'];
        return $result;
    }
}

if (!function_exists('wp_media_migration_report')) {
    function wp_media_migration_report(): array
    {
        return wp_media_migration_scan(['status'=>'all']);
    }
}
