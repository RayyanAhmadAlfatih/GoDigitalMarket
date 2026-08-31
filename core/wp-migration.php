<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| WORDPRESS MIGRATION FOUNDATION
|--------------------------------------------------------------------------
| Safe WXR/XML + CSV preview/import helper for U-Growth.
| V32.92/V32.93 focus: dry-run preview, article/page/LP mapping, basic SEO meta,
| legacy URL capture, shortcode/Gutenberg cleaner, batch logs, backup, rollback.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('wp_migration_base_path')) {
    function wp_migration_base_path(): string
    {
        return STORAGE_PATH . '/wp-migration';
    }
}

if (!function_exists('wp_migration_upload_path')) {
    function wp_migration_upload_path(): string
    {
        return wp_migration_base_path() . '/uploads';
    }
}

if (!function_exists('wp_migration_backup_path')) {
    function wp_migration_backup_path(): string
    {
        return wp_migration_base_path() . '/backups';
    }
}

if (!function_exists('wp_migration_jobs_path')) {
    function wp_migration_jobs_path(): string
    {
        return wp_migration_base_path() . '/jobs.json';
    }
}

if (!function_exists('wp_migration_ensure_storage')) {
    function wp_migration_ensure_storage(): void
    {
        foreach ([wp_migration_base_path(), wp_migration_upload_path(), wp_migration_backup_path()] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        foreach ([wp_migration_base_path(), wp_migration_upload_path(), wp_migration_backup_path()] as $dir) {
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

if (!function_exists('wp_migration_read_json')) {
    function wp_migration_read_json(string $path, array $fallback = []): array
    {
        if (!is_file($path)) {
            return $fallback;
        }
        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : $fallback;
    }
}

if (!function_exists('wp_migration_write_json')) {
    function wp_migration_write_json(string $path, array $payload): bool
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return false;
        }
        return @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('wp_migration_jobs')) {
    function wp_migration_jobs(int $limit = 20): array
    {
        wp_migration_ensure_storage();
        $state = wp_migration_read_json(wp_migration_jobs_path(), ['jobs' => []]);
        $jobs = array_values(array_filter((array)($state['jobs'] ?? []), 'is_array'));
        usort($jobs, static fn(array $a, array $b): int => strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? '')));
        return array_slice($jobs, 0, max(1, $limit));
    }
}

if (!function_exists('wp_migration_find_job')) {
    function wp_migration_find_job(string $jobId): ?array
    {
        foreach (wp_migration_jobs(1000) as $job) {
            if ((string)($job['id'] ?? '') === $jobId) {
                return $job;
            }
        }
        return null;
    }
}

if (!function_exists('wp_migration_save_job')) {
    function wp_migration_save_job(array $job): bool
    {
        wp_migration_ensure_storage();
        $state = wp_migration_read_json(wp_migration_jobs_path(), ['version' => 'V32.93', 'jobs' => []]);
        $jobs = array_values(array_filter((array)($state['jobs'] ?? []), 'is_array'));
        $job['id'] = trim((string)($job['id'] ?? 'wp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3))));
        $job['updated_at'] = date('c');
        $found = false;
        foreach ($jobs as $index => $existing) {
            if ((string)($existing['id'] ?? '') === (string)$job['id']) {
                $jobs[$index] = array_merge($existing, $job);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $job['created_at'] = (string)($job['created_at'] ?? date('c'));
            array_unshift($jobs, $job);
        }
        $state['version'] = 'V32.93';
        $state['updated_at'] = date('c');
        $state['jobs'] = array_slice($jobs, 0, 100);
        return wp_migration_write_json(wp_migration_jobs_path(), $state);
    }
}

if (!function_exists('wp_migration_clean_filename')) {
    function wp_migration_clean_filename(string $name): string
    {
        $name = basename($name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = slugify($base ?: 'wordpress-export');
        return $base . ($ext !== '' ? '.' . $ext : '');
    }
}

if (!function_exists('wp_migration_store_upload')) {
    function wp_migration_store_upload(array $file): array
    {
        wp_migration_ensure_storage();

        if (empty($file['tmp_name']) || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new RuntimeException('File export WordPress belum dipilih.');
        }

        $original = (string)($file['name'] ?? 'wordpress-export.xml');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xml', 'wxr', 'csv', 'txt'], true)) {
            throw new RuntimeException('Format belum didukung. Gunakan file WordPress XML/WXR atau CSV.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 30 * 1024 * 1024) {
            throw new RuntimeException('Ukuran file tidak valid atau melebihi 30 MB.');
        }

        $jobId = 'wp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $filename = $jobId . '_' . wp_migration_clean_filename($original);
        $target = wp_migration_upload_path() . '/' . $filename;
        if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
            throw new RuntimeException('File gagal disimpan ke storage migrasi.');
        }

        return [
            'job_id' => $jobId,
            'original_name' => $original,
            'stored_name' => $filename,
            'path' => $target,
            'size' => $size,
            'extension' => $ext,
        ];
    }
}

if (!function_exists('wp_migration_decode_xml_value')) {
    function wp_migration_decode_xml_value(string $value): string
    {
        $value = preg_replace('/<!\[CDATA\[(.*?)\]\]>/s', '$1', $value) ?? $value;
        $value = html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return trim($value);
    }
}

if (!function_exists('wp_migration_xml_first')) {
    function wp_migration_xml_first(string $xml, string $tag): string
    {
        $tagPattern = preg_quote($tag, '#');
        if (!preg_match('#<' . $tagPattern . '(?:\s[^>]*)?>(.*?)</' . $tagPattern . '>#is', $xml, $m)) {
            return '';
        }
        return wp_migration_decode_xml_value((string)$m[1]);
    }
}

if (!function_exists('wp_migration_xml_attr')) {
    function wp_migration_xml_attr(string $attrs, string $name): string
    {
        $pattern = '#\b' . preg_quote($name, '#') . '\s*=\s*(["\'])(.*?)\1#i';
        if (!preg_match($pattern, $attrs, $m)) {
            return '';
        }
        return wp_migration_decode_xml_value((string)$m[2]);
    }
}

if (!function_exists('wp_migration_url_path')) {
    function wp_migration_url_path(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (is_array($parts)) {
            $path = trim((string)($parts['path'] ?? ''), '/');
            $query = trim((string)($parts['query'] ?? ''));
            $path = $path !== '' ? '/' . $path : '/';
            return $query !== '' ? $path . '?' . $query : $path;
        }

        if (str_starts_with($url, '/')) {
            return '/' . trim($url, '/');
        }

        return '/' . trim($url, '/');
    }
}

if (!function_exists('wp_migration_absolute_url')) {
    function wp_migration_absolute_url(string $urlOrPath): string
    {
        $urlOrPath = trim($urlOrPath);
        if ($urlOrPath === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $urlOrPath)) {
            return $urlOrPath;
        }
        return url(ltrim($urlOrPath, '/'));
    }
}

if (!function_exists('wp_migration_shortcodes')) {
    function wp_migration_shortcodes(string $content): array
    {
        preg_match_all('/\[([a-zA-Z0-9_\-:]+)(?:\s[^\]]*)?\]/', $content, $matches);
        $codes = array_values(array_unique(array_map('strtolower', (array)($matches[1] ?? []))));
        sort($codes);
        return array_slice($codes, 0, 20);
    }
}

if (!function_exists('wp_migration_clean_content')) {
    function wp_migration_clean_content(string $content): string
    {
        $content = trim($content);
        $content = preg_replace('/<!--\s*\/??wp:[^>]*-->/', '', $content) ?? $content;
        $content = preg_replace('/\[(contact-form-7|gravityform|rank_math[^\]]*|wpforms|woocommerce_[^\]]*|elementor-template|rev_slider|vc_[^\]]*|et_pb_[^\]]*)[^\]]*\]/i', '', $content) ?? $content;
        $content = preg_replace('#<\s*(script|iframe|object|embed|form|input|button|style|link|meta)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $content) ?? $content;
        $content = preg_replace('#on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $content) ?? $content;
        $content = preg_replace('#javascript\s*:#i', '', $content) ?? $content;
        $content = strip_tags($content, '<p><br><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><del><mark><ul><ol><li><a><blockquote><hr><img><figure><figcaption><table><thead><tbody><tfoot><tr><th><td><span><div><pre><code>');
        $content = preg_replace('#href\s*=\s*(["\'])\s*javascript:[^"\']*\1#i', 'href="#"', (string)$content) ?? $content;
        $content = preg_replace('#src\s*=\s*(["\'])\s*javascript:[^"\']*\1#i', 'src=""', (string)$content) ?? $content;
        return trim((string)$content);
    }
}

if (!function_exists('wp_migration_first_image')) {
    function wp_migration_first_image(string $content): string
    {
        if (preg_match('#<img[^>]+src\s*=\s*(["\'])(.*?)\1#i', $content, $m)) {
            return trim((string)$m[2]);
        }
        return '';
    }
}

if (!function_exists('wp_migration_item_categories')) {
    function wp_migration_item_categories(string $itemXml): array
    {
        $categories = [];
        $tags = [];
        preg_match_all('#<category\b([^>]*)>(.*?)</category>#is', $itemXml, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $domain = strtolower(wp_migration_xml_attr((string)$match[1], 'domain'));
            $label = wp_migration_decode_xml_value((string)$match[2]);
            if ($label === '') {
                continue;
            }
            if ($domain === 'post_tag' || $domain === 'tag') {
                $tags[] = $label;
            } else {
                $categories[] = $label;
            }
        }
        return [
            'categories' => array_values(array_unique($categories)),
            'tags' => array_values(array_unique($tags)),
        ];
    }
}

if (!function_exists('wp_migration_item_meta')) {
    function wp_migration_item_meta(string $itemXml): array
    {
        $meta = [];
        preg_match_all('#<wp:postmeta>(.*?)</wp:postmeta>#is', $itemXml, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $block = (string)$match[1];
            $key = wp_migration_xml_first($block, 'wp:meta_key');
            if ($key === '') {
                continue;
            }
            $meta[$key] = wp_migration_xml_first($block, 'wp:meta_value');
        }
        return $meta;
    }
}

if (!function_exists('wp_migration_parse_wxr')) {
    function wp_migration_parse_wxr(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('File migrasi tidak ditemukan.');
        }

        $xml = (string)@file_get_contents($path);
        if (trim($xml) === '') {
            throw new RuntimeException('File XML kosong atau tidak bisa dibaca.');
        }

        preg_match_all('#<item>(.*?)</item>#is', $xml, $matches);
        $itemsXml = (array)($matches[1] ?? []);
        if (!$itemsXml) {
            throw new RuntimeException('Tidak ada item WordPress yang terbaca dari file XML/WXR.');
        }

        $attachments = [];
        foreach ($itemsXml as $itemXml) {
            $postType = wp_migration_xml_first((string)$itemXml, 'wp:post_type');
            if ($postType !== 'attachment') {
                continue;
            }
            $id = wp_migration_xml_first((string)$itemXml, 'wp:post_id');
            $url = wp_migration_xml_first((string)$itemXml, 'wp:attachment_url') ?: wp_migration_xml_first((string)$itemXml, 'guid');
            if ($id !== '' && $url !== '') {
                $attachments[$id] = $url;
            }
        }

        $items = [];
        foreach ($itemsXml as $index => $itemXml) {
            $itemXml = (string)$itemXml;
            $postType = wp_migration_xml_first($itemXml, 'wp:post_type') ?: 'post';
            if (!in_array($postType, ['post', 'page'], true)) {
                continue;
            }

            $status = wp_migration_xml_first($itemXml, 'wp:status') ?: 'publish';
            if (in_array($status, ['trash', 'auto-draft', 'inherit'], true)) {
                continue;
            }

            $title = wp_migration_xml_first($itemXml, 'title');
            $contentRaw = wp_migration_xml_first($itemXml, 'content:encoded');
            $excerptRaw = wp_migration_xml_first($itemXml, 'excerpt:encoded');
            $link = wp_migration_xml_first($itemXml, 'link');
            $slug = wp_migration_xml_first($itemXml, 'wp:post_name') ?: slugify($title);
            $postId = wp_migration_xml_first($itemXml, 'wp:post_id');
            $pubDate = wp_migration_xml_first($itemXml, 'pubDate');
            $date = wp_migration_xml_first($itemXml, 'wp:post_date');
            $dateGmt = wp_migration_xml_first($itemXml, 'wp:post_date_gmt');
            $author = wp_migration_xml_first($itemXml, 'dc:creator') ?: SITE_NAME;
            $tax = wp_migration_item_categories($itemXml);
            $meta = wp_migration_item_meta($itemXml);
            $shortcodes = wp_migration_shortcodes($contentRaw);
            $content = wp_migration_clean_content($contentRaw);
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');
            $excerpt = trim(strip_tags($excerptRaw)) ?: limit_chars($plain, 160);
            $thumbnailId = trim((string)($meta['_thumbnail_id'] ?? ''));
            $featuredImage = $thumbnailId !== '' ? (string)($attachments[$thumbnailId] ?? '') : '';
            $featuredImage = $featuredImage ?: (string)($meta['_yoast_wpseo_opengraph-image'] ?? $meta['rank_math_facebook_image'] ?? '');
            $featuredImage = $featuredImage ?: wp_migration_first_image($contentRaw);
            $seoTitle = (string)($meta['_yoast_wpseo_title'] ?? $meta['rank_math_title'] ?? $meta['_aioseo_title'] ?? $title);
            $seoDescription = (string)($meta['_yoast_wpseo_metadesc'] ?? $meta['rank_math_description'] ?? $meta['_aioseo_description'] ?? $excerpt);
            $canonical = (string)($meta['_yoast_wpseo_canonical'] ?? $meta['rank_math_canonical_url'] ?? $meta['_aioseo_canonical_url'] ?? '');
            $legacyUrl = wp_migration_url_path($link);
            if ($legacyUrl === '/' && $slug !== '') {
                $legacyUrl = '/' . $slug;
            }
            $warnings = [];
            if ($title === '') {
                $warnings[] = 'Judul kosong';
            }
            if ($plain === '') {
                $warnings[] = 'Konten kosong setelah dibersihkan';
            }
            if ($shortcodes) {
                $warnings[] = 'Ada shortcode/plugin: ' . implode(', ', array_slice($shortcodes, 0, 5));
            }
            if (!empty($meta['_elementor_data'])) {
                $warnings[] = 'Terdeteksi Elementor data; V32.97 dapat mengimpor halaman sebagai HTML block aman atau mode campuran native sederhana.';
            }

            $items[] = [
                'source_index' => $index + 1,
                'wp_post_id' => $postId,
                'wp_post_type' => $postType,
                'target_type' => $postType === 'post' ? 'article' : 'landing_page',
                'status' => $status,
                'title' => $title ?: ('Konten WordPress #' . ($index + 1)),
                'slug' => slugify($slug ?: $title),
                'legacy_url' => $legacyUrl,
                'original_url' => $link,
                'canonical_url' => $canonical,
                'content' => $content,
                'raw_content' => $contentRaw,
                'meta' => $meta,
                'plain_text' => $plain,
                'excerpt' => $excerpt,
                'author' => $author,
                'published_at' => wp_migration_normalize_date($date ?: $dateGmt ?: $pubDate),
                'updated_at' => wp_migration_normalize_date(wp_migration_xml_first($itemXml, 'wp:post_modified') ?: $date ?: $pubDate),
                'categories' => $tax['categories'],
                'tags' => $tax['tags'],
                'meta_title' => trim($seoTitle),
                'meta_description' => trim($seoDescription),
                'featured_image' => trim($featuredImage),
                'image_alt' => trim($title),
                'shortcodes' => $shortcodes,
                'page_builder' => !empty($meta['_elementor_data']) ? 'Elementor' : (str_contains($contentRaw, '<!-- wp:') ? 'Gutenberg' : 'Classic/HTML'),
                'warnings' => $warnings,
            ];
        }

        return $items;
    }
}

if (!function_exists('wp_migration_normalize_date')) {
    function wp_migration_normalize_date(string $date): string
    {
        $date = trim($date);
        if ($date === '' || $date === '0000-00-00 00:00:00') {
            return date('Y-m-d H:i:s');
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_migration_csv_detect_delimiter')) {
    function wp_migration_csv_detect_delimiter(string $path): string
    {
        $sample = '';
        $handle = @fopen($path, 'rb');
        if ($handle) {
            $sample = (string)fgets($handle);
            fclose($handle);
        }
        $best = ',';
        $count = -1;
        foreach ([',', ';', "\t", '|'] as $delimiter) {
            $current = substr_count($sample, $delimiter);
            if ($current > $count) {
                $best = $delimiter;
                $count = $current;
            }
        }
        return $best;
    }
}

if (!function_exists('wp_migration_csv_key')) {
    function wp_migration_csv_key(string $key): string
    {
        $key = strtolower(trim($key));
        $key = str_replace(["\xef\xbb\xbf", ' ', '-', '.', '/', '(', ')'], ['', '_', '_', '_', '_', '', ''], $key);
        $key = preg_replace('/[^a-z0-9_]/', '', $key) ?: '';
        return trim($key, '_');
    }
}

if (!function_exists('wp_migration_first')) {
    function wp_migration_first(array $row, array $keys, string $fallback = ''): string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }
        return $fallback;
    }
}

if (!function_exists('wp_migration_parse_csv')) {
    function wp_migration_parse_csv(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException('File CSV tidak bisa dibaca.');
        }
        $delimiter = wp_migration_csv_detect_delimiter($path);
        $headers = fgetcsv($handle, 0, $delimiter, '"', '');
        if (!is_array($headers)) {
            fclose($handle);
            throw new RuntimeException('Header CSV tidak terbaca.');
        }
        $headers = array_map(static fn($h): string => wp_migration_csv_key((string)$h), $headers);
        $items = [];
        $rowNumber = 1;
        while (($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rowNumber++;
            if (!is_array($values) || count(array_filter($values, static fn($v): bool => trim((string)$v) !== '')) === 0) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                if ($header !== '') {
                    $row[$header] = (string)($values[$i] ?? '');
                }
            }
            $title = wp_migration_first($row, ['title', 'judul', 'post_title', 'page_title']);
            $rawContent = wp_migration_first($row, ['content', 'isi', 'post_content', 'body', 'konten']);
            $content = wp_migration_clean_content($rawContent);
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');
            $type = strtolower(wp_migration_first($row, ['type', 'post_type', 'wp_post_type'], 'post'));
            $targetType = in_array($type, ['page', 'landing_page', 'lp'], true) ? 'landing_page' : 'article';
            $legacy = wp_migration_url_path(wp_migration_first($row, ['old_url', 'legacy_url', 'permalink', 'url', 'link']));
            $slug = slugify(wp_migration_first($row, ['slug', 'post_name'], $title));
            $shortcodes = wp_migration_shortcodes($rawContent);
            $warnings = [];
            if ($title === '') {
                $warnings[] = 'Judul kosong';
            }
            if ($plain === '') {
                $warnings[] = 'Konten kosong setelah dibersihkan';
            }
            if ($shortcodes) {
                $warnings[] = 'Ada shortcode/plugin: ' . implode(', ', array_slice($shortcodes, 0, 5));
            }
            $items[] = [
                'source_index' => $rowNumber,
                'wp_post_id' => wp_migration_first($row, ['id', 'post_id', 'wp_post_id']),
                'wp_post_type' => $targetType === 'article' ? 'post' : 'page',
                'target_type' => $targetType,
                'status' => wp_migration_first($row, ['status', 'post_status'], 'publish'),
                'title' => $title ?: ('Konten CSV #' . $rowNumber),
                'slug' => $slug ?: ('konten-csv-' . $rowNumber),
                'legacy_url' => $legacy,
                'original_url' => wp_migration_first($row, ['url', 'link', 'permalink', 'old_url']),
                'canonical_url' => wp_migration_first($row, ['canonical', 'canonical_url']),
                'content' => $content,
                'raw_content' => $contentRaw,
                'meta' => $meta,
                'plain_text' => $plain,
                'excerpt' => wp_migration_first($row, ['excerpt', 'ringkasan', 'description', 'meta_description'], limit_chars($plain, 160)),
                'author' => wp_migration_first($row, ['author', 'penulis'], SITE_NAME),
                'published_at' => wp_migration_normalize_date(wp_migration_first($row, ['date', 'published_at', 'post_date'], date('Y-m-d H:i:s'))),
                'updated_at' => wp_migration_normalize_date(wp_migration_first($row, ['updated_at', 'modified_at'], date('Y-m-d H:i:s'))),
                'categories' => array_values(array_filter(array_map('trim', explode(',', wp_migration_first($row, ['category', 'categories', 'kategori']))))),
                'tags' => array_values(array_filter(array_map('trim', explode(',', wp_migration_first($row, ['tags', 'tag', 'keywords']))))),
                'meta_title' => wp_migration_first($row, ['meta_title', 'seo_title'], $title),
                'meta_description' => wp_migration_first($row, ['meta_description', 'seo_description'], limit_chars($plain, 160)),
                'featured_image' => wp_migration_first($row, ['featured_image', 'image', 'gambar', 'thumbnail']),
                'image_alt' => wp_migration_first($row, ['image_alt', 'alt'], $title),
                'shortcodes' => $shortcodes,
                'page_builder' => 'CSV/HTML',
                'warnings' => $warnings,
            ];
        }
        fclose($handle);
        return $items;
    }
}

if (!function_exists('wp_migration_parse_file')) {
    function wp_migration_parse_file(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            return wp_migration_parse_csv($path);
        }
        return wp_migration_parse_wxr($path);
    }
}

if (!function_exists('wp_migration_conflict_for_item')) {
    function wp_migration_conflict_for_item(array $item): string
    {
        $slug = (string)($item['slug'] ?? '');
        if ($slug === '') {
            return 'slug_empty';
        }
        if ((string)($item['target_type'] ?? '') === 'article') {
            return article_exists($slug) ? 'article_slug_exists' : 'none';
        }
        if (function_exists('landing_page_find') && landing_page_find($slug)) {
            return 'landing_slug_exists';
        }
        return 'none';
    }
}

if (!function_exists('wp_migration_preview_file')) {
    function wp_migration_preview_file(string $path): array
    {
        $items = wp_migration_parse_file($path);
        $summary = [
            'total' => count($items),
            'articles' => 0,
            'landing_pages' => 0,
            'published' => 0,
            'drafts' => 0,
            'warnings' => 0,
            'conflicts' => 0,
            'shortcodes' => 0,
        ];
        $samples = [];
        foreach ($items as $item) {
            $target = (string)($item['target_type'] ?? 'article');
            $summary[$target === 'article' ? 'articles' : 'landing_pages']++;
            ((string)($item['status'] ?? '') === 'publish') ? $summary['published']++ : $summary['drafts']++;
            if (!empty($item['warnings'])) {
                $summary['warnings']++;
            }
            if (!empty($item['shortcodes'])) {
                $summary['shortcodes']++;
            }
            $conflict = wp_migration_conflict_for_item($item);
            if ($conflict !== 'none') {
                $summary['conflicts']++;
            }
            if (count($samples) < 30) {
                $item['conflict'] = $conflict;
                $samples[] = $item;
            }
        }
        return [
            'summary' => $summary,
            'samples' => $samples,
            'items' => $items,
        ];
    }
}

if (!function_exists('wp_migration_backup_storage')) {
    function wp_migration_backup_storage(string $batchId): array
    {
        wp_migration_ensure_storage();
        $backupDir = wp_migration_backup_path() . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $batchId);
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0775, true);
        }
        $files = [
            'articles.json' => STORAGE_PATH . '/articles.json',
            'landing-pages.json' => STORAGE_PATH . '/landing-pages.json',
            'landing-page-revisions.json' => STORAGE_PATH . '/landing-page-revisions.json',
            'template-content.json' => STORAGE_PATH . '/template-content.json',
        ];
        $copied = [];
        foreach ($files as $name => $source) {
            if (is_file($source)) {
                @copy($source, $backupDir . '/' . $name);
                $copied[$name] = true;
            }
        }
        wp_migration_write_json($backupDir . '/manifest.json', [
            'batch_id' => $batchId,
            'created_at' => date('c'),
            'files' => array_keys($copied),
        ]);
        return ['dir' => $backupDir, 'files' => array_keys($copied)];
    }
}

if (!function_exists('wp_migration_rollback')) {
    function wp_migration_rollback(string $batchId): array
    {
        $job = wp_migration_find_job($batchId);
        if (!$job) {
            throw new RuntimeException('Batch migrasi tidak ditemukan.');
        }
        $backupDir = (string)($job['backup_dir'] ?? (wp_migration_backup_path() . '/' . $batchId));
        if (!is_dir($backupDir)) {
            throw new RuntimeException('Folder backup untuk batch ini tidak ditemukan.');
        }
        $restored = [];
        foreach (['articles.json', 'landing-pages.json', 'landing-page-revisions.json', 'template-content.json'] as $name) {
            $source = $backupDir . '/' . $name;
            if (is_file($source)) {
                @copy($source, STORAGE_PATH . '/' . $name);
                $restored[] = $name;
            }
        }
        $job['status'] = 'rolled_back';
        $job['rolled_back_at'] = date('c');
        $job['rollback_files'] = $restored;
        wp_migration_save_job($job);
        if (function_exists('activity_log_record')) {
            activity_log_record('rollback', 'wp_migration', 0, 'Rollback batch migrasi WordPress.', ['batch_id' => $batchId, 'files' => $restored]);
        }
        return ['restored' => $restored, 'batch_id' => $batchId];
    }
}

if (!function_exists('wp_migration_import_article')) {
    function wp_migration_import_article(array $item, string $batchId, array $options): array
    {
        $slug = (string)($item['slug'] ?? '');
        $duplicateStrategy = (string)($options['duplicate_strategy'] ?? 'rename');
        if ($duplicateStrategy === 'skip' && article_exists($slug)) {
            return ['created' => false, 'skipped' => true, 'title' => $item['title'] ?? '', 'reason' => 'Slug artikel sudah ada'];
        }

        $legacyUrl = (string)($item['legacy_url'] ?? '');
        $canonical = trim((string)($item['canonical_url'] ?? ''));
        if ($canonical === '' && (string)($options['canonical_strategy'] ?? 'legacy') === 'legacy' && $legacyUrl !== '') {
            $canonical = wp_migration_absolute_url($legacyUrl);
        }

        $payload = [
            'title' => (string)($item['title'] ?? ''),
            'slug' => $slug,
            'category' => (string)($item['categories'][0] ?? 'Artikel'),
            'excerpt' => (string)($item['excerpt'] ?? ''),
            'image' => (string)($item['featured_image'] ?? ''),
            'image_alt' => (string)($item['image_alt'] ?? $item['title'] ?? ''),
            'image_title' => (string)($item['title'] ?? ''),
            'author' => (string)($item['author'] ?? SITE_NAME),
            'published_at' => (string)($item['published_at'] ?? date('Y-m-d H:i:s')),
            'featured' => false,
            'keywords' => implode(', ', (array)($item['tags'] ?? [])),
            'content' => (string)($item['content'] ?? ''),
            'meta_title' => (string)($item['meta_title'] ?? $item['title'] ?? ''),
            'meta_description' => (string)($item['meta_description'] ?? $item['excerpt'] ?? ''),
            'meta_keywords' => implode(', ', (array)($item['tags'] ?? [])),
            'canonical_url' => $canonical,
            'og_title' => (string)($item['meta_title'] ?? $item['title'] ?? ''),
            'og_description' => (string)($item['meta_description'] ?? $item['excerpt'] ?? ''),
            'focus_keyword' => (string)($item['tags'][0] ?? $item['categories'][0] ?? ''),
            'robots' => 'index, follow',
            'breadcrumb_title' => (string)($item['title'] ?? ''),
            'schema_type' => 'Article',
            'source' => 'wp-import',
            'legacy_url' => $legacyUrl,
            'original_url' => (string)($item['original_url'] ?? ''),
            'wp_post_id' => (string)($item['wp_post_id'] ?? ''),
            'wp_post_type' => 'post',
            'migration_batch_id' => $batchId,
        ];

        $id = article_create($payload);
        return ['created' => $id > 0, 'skipped' => false, 'id' => $id, 'title' => $payload['title'], 'slug' => $payload['slug']];
    }
}

if (!function_exists('wp_migration_text_excerpt')) {
    function wp_migration_text_excerpt(string $html, int $limit = 6000): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        return limit_chars($text, $limit);
    }
}

if (!function_exists('wp_migration_import_landing_page')) {
    function wp_migration_import_landing_page(array $item, string $batchId, array $options): array
    {
        if (!function_exists('landing_page_save')) {
            return ['created' => false, 'skipped' => true, 'title' => $item['title'] ?? '', 'reason' => 'Landing page builder belum aktif'];
        }
        $slug = (string)($item['slug'] ?? '');
        $duplicateStrategy = (string)($options['duplicate_strategy'] ?? 'rename');
        if ($duplicateStrategy === 'skip' && landing_page_find($slug)) {
            return ['created' => false, 'skipped' => true, 'title' => $item['title'] ?? '', 'reason' => 'Slug landing page sudah ada'];
        }
        $text = wp_migration_text_excerpt((string)($item['content'] ?? ''), 8000);
        $page = landing_page_save([
            'title' => (string)($item['title'] ?? 'Halaman WordPress'),
            'slug' => $slug,
            'status' => (string)($options['page_status'] ?? 'draft'),
            'layout_mode' => 'website',
            'hide_header' => false,
            'hide_footer' => false,
            'hide_floating_wa' => false,
            'show_nav_only' => false,
            'indexable' => false,
            'meta_title' => (string)($item['meta_title'] ?? $item['title'] ?? ''),
            'meta_description' => (string)($item['meta_description'] ?? $item['excerpt'] ?? ''),
            'meta_keywords' => implode(', ', (array)($item['tags'] ?? [])),
            'tracking_label' => 'Import WordPress - ' . (string)($item['title'] ?? 'Halaman'),
            'canonical_url' => (string)($item['canonical_url'] ?? ''),
            'legacy_url' => (string)($item['legacy_url'] ?? ''),
            'original_url' => (string)($item['original_url'] ?? ''),
            'wp_post_id' => (string)($item['wp_post_id'] ?? ''),
            'wp_post_type' => 'page',
            'migration_batch_id' => $batchId,
            'blocks' => (function_exists('wp_elementor_import_blocks_for_item')
                ? wp_elementor_import_blocks_for_item($item, $batchId, (string)($options['page_builder_mode'] ?? 'safe_html'))
                : [[
                    'type' => 'text',
                    'headline' => (string)($item['title'] ?? 'Halaman WordPress'),
                    'text' => $text,
                    'bg_color' => '#ffffff',
                    'block_goal' => 'trust',
                    'animation_style' => 'fade',
                    'migration_batch_id' => $batchId,
                    'legacy_url' => (string)($item['legacy_url'] ?? ''),
                    'page_builder' => (string)($item['page_builder'] ?? 'WordPress'),
                ]]),
        ], ['note' => 'Import dari WordPress batch ' . $batchId, 'action' => 'create']);
        return ['created' => !empty($page['id']), 'skipped' => false, 'id' => $page['id'] ?? '', 'title' => $page['title'] ?? '', 'slug' => $page['slug'] ?? ''];
    }
}

if (!function_exists('wp_migration_run_import')) {
    function wp_migration_run_import(string $jobId, array $options = []): array
    {
        $job = wp_migration_find_job($jobId);
        if (!$job) {
            throw new RuntimeException('Job preview migrasi tidak ditemukan.');
        }
        $path = (string)($job['file_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            throw new RuntimeException('File sumber job tidak ditemukan. Upload ulang file WordPress.');
        }

        $items = wp_migration_parse_file($path);
        $backup = !empty($options['make_backup']) ? wp_migration_backup_storage($jobId) : ['dir' => '', 'files' => []];
        $result = [
            'batch_id' => $jobId,
            'created_articles' => 0,
            'created_landing_pages' => 0,
            'skipped' => 0,
            'failed' => 0,
            'logs' => [],
            'backup' => $backup,
        ];

        foreach ($items as $item) {
            try {
                if (trim((string)($item['title'] ?? '')) === '' || trim((string)($item['plain_text'] ?? '')) === '') {
                    $result['skipped']++;
                    $result['logs'][] = 'Lewati #' . (int)($item['source_index'] ?? 0) . ': judul/konten kosong.';
                    continue;
                }
                if ((string)($item['target_type'] ?? 'article') === 'article') {
                    if (empty($options['import_posts'])) {
                        $result['skipped']++;
                        continue;
                    }
                    $row = wp_migration_import_article($item, $jobId, $options);
                    if (!empty($row['created'])) {
                        $result['created_articles']++;
                        $result['logs'][] = 'Artikel masuk: ' . (string)($row['title'] ?? '');
                    } else {
                        $result['skipped']++;
                        $result['logs'][] = 'Artikel dilewati: ' . (string)($row['title'] ?? '') . ' — ' . (string)($row['reason'] ?? 'tidak dibuat');
                    }
                } else {
                    if (empty($options['import_pages'])) {
                        $result['skipped']++;
                        continue;
                    }
                    $row = wp_migration_import_landing_page($item, $jobId, $options);
                    if (!empty($row['created'])) {
                        $result['created_landing_pages']++;
                        $result['logs'][] = 'Halaman/LP draft masuk: ' . (string)($row['title'] ?? '');
                    } else {
                        $result['skipped']++;
                        $result['logs'][] = 'Halaman/LP dilewati: ' . (string)($row['title'] ?? '') . ' — ' . (string)($row['reason'] ?? 'tidak dibuat');
                    }
                }
            } catch (Throwable $e) {
                $result['failed']++;
                $result['logs'][] = 'Gagal import #' . (int)($item['source_index'] ?? 0) . ': ' . $e->getMessage();
            }
        }

        $job['status'] = 'imported';
        $job['imported_at'] = date('c');
        $job['options'] = $options;
        $job['backup_dir'] = (string)($backup['dir'] ?? '');
        $job['result'] = $result;
        wp_migration_save_job($job);

        if (function_exists('activity_log_record')) {
            activity_log_record('import', 'wp_migration', 0, 'Import WordPress dijalankan.', [
                'batch_id' => $jobId,
                'created_articles' => $result['created_articles'],
                'created_landing_pages' => $result['created_landing_pages'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
            ]);
        }

        return $result;
    }
}
