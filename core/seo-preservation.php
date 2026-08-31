<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO PRESERVATION LAYER
|--------------------------------------------------------------------------
| V32.93 foundation for WordPress/legacy URL preservation.
| - Internal 301/302 redirect map stored in protected storage.
| - Legacy URL resolver before 404 for imported WordPress root-level slugs.
| - Canonical/sitemap helpers that prefer stored canonical/legacy URLs safely.
| - Audit report for migration readiness.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_preservation_storage_dir')) {
    function seo_preservation_storage_dir(): string
    {
        return STORAGE_PATH . '/seo-preservation';
    }
}

if (!function_exists('seo_preservation_redirects_file')) {
    function seo_preservation_redirects_file(): string
    {
        return seo_preservation_storage_dir() . '/redirects.json';
    }
}

if (!function_exists('seo_preservation_ensure_storage')) {
    function seo_preservation_ensure_storage(): void
    {
        $dir = seo_preservation_storage_dir();
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

if (!function_exists('seo_preservation_read_json')) {
    function seo_preservation_read_json(string $path, array $fallback = []): array
    {
        if (!is_file($path)) {
            return $fallback;
        }

        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : $fallback;
    }
}

if (!function_exists('seo_preservation_write_json')) {
    function seo_preservation_write_json(string $path, array $payload): bool
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

if (!function_exists('seo_preservation_state')) {
    function seo_preservation_state(bool $fresh = false): array
    {
        static $cache = null;
        if ($fresh || $cache === null) {
            seo_preservation_ensure_storage();
            $cache = seo_preservation_read_json(seo_preservation_redirects_file(), [
                'version' => 'V32.93',
                'updated_at' => date(DATE_ATOM),
                'records' => [],
            ]);
            if (!is_array($cache['records'] ?? null)) {
                $cache['records'] = [];
            }
        }

        return $cache;
    }
}

if (!function_exists('seo_preservation_save_state')) {
    function seo_preservation_save_state(array $state): bool
    {
        $state['version'] = 'V32.93';
        $state['updated_at'] = date(DATE_ATOM);
        $state['records'] = array_values(array_filter((array)($state['records'] ?? []), 'is_array'));
        $ok = seo_preservation_write_json(seo_preservation_redirects_file(), $state);
        seo_preservation_state(true);
        return $ok;
    }
}

if (!function_exists('seo_preservation_normalize_path')) {
    function seo_preservation_normalize_path(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '/';
        }

        $path = $input;
        $parts = parse_url($input);
        if (is_array($parts) && (isset($parts['scheme']) || isset($parts['host']))) {
            $path = (string)($parts['path'] ?? '/');
        } else {
            $path = (string)strtok($input, '?');
        }

        $path = rawurldecode($path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '/';
        }

        $basePath = trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?? ''), '/');
        if ($basePath !== '' && str_starts_with(trim($path, '/'), $basePath . '/')) {
            $path = '/' . substr(trim($path, '/'), strlen($basePath) + 1);
        }

        return '/' . trim($path, '/');
    }
}

if (!function_exists('seo_preservation_path_key')) {
    function seo_preservation_path_key(string $input): string
    {
        return strtolower(seo_preservation_normalize_path($input));
    }
}

if (!function_exists('seo_preservation_absolute_url')) {
    function seo_preservation_absolute_url(string $urlOrPath): string
    {
        $urlOrPath = trim($urlOrPath);
        if ($urlOrPath === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $urlOrPath)) {
            return $urlOrPath;
        }
        return url(ltrim(seo_preservation_normalize_path($urlOrPath), '/'));
    }
}

if (!function_exists('seo_preservation_same_site_url')) {
    function seo_preservation_same_site_url(string $url): bool
    {
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        $siteHost = strtolower((string)(parse_url((string)SITE_URL, PHP_URL_HOST) ?? ''));
        return $host === '' || $host === $siteHost;
    }
}

if (!function_exists('seo_preservation_records')) {
    function seo_preservation_records(bool $includeInactive = true): array
    {
        $state = seo_preservation_state();
        $records = array_values(array_filter((array)($state['records'] ?? []), 'is_array'));
        if (!$includeInactive) {
            $records = array_values(array_filter($records, static fn(array $r): bool => (string)($r['status'] ?? 'active') === 'active'));
        }
        usort($records, static function (array $a, array $b): int {
            return strcmp((string)($b['updated_at'] ?? $b['created_at'] ?? ''), (string)($a['updated_at'] ?? $a['created_at'] ?? ''));
        });
        return $records;
    }
}

if (!function_exists('seo_preservation_record_normalize')) {
    function seo_preservation_record_normalize(array $record): array
    {
        $source = seo_preservation_normalize_path((string)($record['source_path'] ?? $record['from'] ?? ''));
        $target = trim((string)($record['target_url'] ?? $record['to'] ?? ''));
        $code = (int)($record['code'] ?? 301);
        if (!in_array($code, [301, 302, 307, 308], true)) {
            $code = 301;
        }

        return [
            'id' => trim((string)($record['id'] ?? 'redir_' . date('YmdHis') . '_' . substr(sha1($source . '|' . $target), 0, 8))),
            'source_path' => $source,
            'source_key' => seo_preservation_path_key($source),
            'target_url' => seo_preservation_absolute_url($target),
            'code' => $code,
            'status' => in_array((string)($record['status'] ?? 'active'), ['active', 'inactive'], true) ? (string)($record['status'] ?? 'active') : 'active',
            'type' => trim((string)($record['type'] ?? 'manual')) ?: 'manual',
            'note' => trim((string)($record['note'] ?? '')),
            'hits' => max(0, (int)($record['hits'] ?? 0)),
            'last_hit_at' => trim((string)($record['last_hit_at'] ?? '')),
            'created_at' => trim((string)($record['created_at'] ?? date(DATE_ATOM))),
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_preservation_save_record')) {
    function seo_preservation_save_record(array $record): array
    {
        $record = seo_preservation_record_normalize($record);
        if ($record['source_path'] === '/' || $record['target_url'] === '') {
            throw new RuntimeException('Source path dan target URL wajib diisi. Source root / tidak diizinkan untuk redirect manual.');
        }

        if (seo_preservation_path_key($record['target_url']) === $record['source_key']) {
            throw new RuntimeException('Target redirect tidak boleh sama dengan source path.');
        }

        $state = seo_preservation_state(true);
        $records = array_values(array_filter((array)($state['records'] ?? []), 'is_array'));
        $found = false;
        foreach ($records as $i => $existing) {
            if ((string)($existing['id'] ?? '') === $record['id'] || (string)($existing['source_key'] ?? '') === $record['source_key']) {
                $record['created_at'] = (string)($existing['created_at'] ?? $record['created_at']);
                $records[$i] = $record;
                $found = true;
                break;
            }
        }
        if (!$found) {
            array_unshift($records, $record);
        }
        $state['records'] = $records;
        seo_preservation_save_state($state);
        return $record;
    }
}

if (!function_exists('seo_preservation_delete_record')) {
    function seo_preservation_delete_record(string $id): bool
    {
        $state = seo_preservation_state(true);
        $state['records'] = array_values(array_filter((array)($state['records'] ?? []), static fn(array $r): bool => (string)($r['id'] ?? '') !== $id));
        return seo_preservation_save_state($state);
    }
}

if (!function_exists('seo_preservation_match_redirect')) {
    function seo_preservation_match_redirect(string $path): ?array
    {
        $key = seo_preservation_path_key($path);
        foreach (seo_preservation_records(false) as $record) {
            if ((string)($record['source_key'] ?? '') === $key) {
                return $record;
            }
        }
        return null;
    }
}

if (!function_exists('seo_preservation_note_hit')) {
    function seo_preservation_note_hit(string $id): void
    {
        $state = seo_preservation_state(true);
        foreach ((array)($state['records'] ?? []) as $i => $record) {
            if ((string)($record['id'] ?? '') === $id) {
                $record['hits'] = (int)($record['hits'] ?? 0) + 1;
                $record['last_hit_at'] = date(DATE_ATOM);
                $record['updated_at'] = date(DATE_ATOM);
                $state['records'][$i] = $record;
                seo_preservation_save_state($state);
                return;
            }
        }
    }
}

if (!function_exists('seo_preservation_redirect_now')) {
    function seo_preservation_redirect_now(array $record): never
    {
        seo_preservation_note_hit((string)($record['id'] ?? ''));
        header('Location: ' . (string)($record['target_url'] ?? url()), true, (int)($record['code'] ?? 301));
        exit;
    }
}

if (!function_exists('seo_preservation_content_paths')) {
    function seo_preservation_content_paths(array $item): array
    {
        $paths = [];
        foreach (['legacy_url', 'original_url', 'old_url', 'redirect_from', 'wp_original_url', 'wp_guid'] as $key) {
            $value = trim((string)($item[$key] ?? ''));
            if ($value !== '') {
                $paths[] = seo_preservation_normalize_path($value);
            }
        }
        if (!empty($item['redirect_from']) && is_array($item['redirect_from'])) {
            foreach ((array)$item['redirect_from'] as $path) {
                $paths[] = seo_preservation_normalize_path((string)$path);
            }
        }
        return array_values(array_unique(array_filter($paths, static fn(string $p): bool => $p !== '/' && $p !== '')));
    }
}

if (!function_exists('seo_preservation_find_legacy_content')) {
    function seo_preservation_find_legacy_content(string $path): ?array
    {
        $key = seo_preservation_path_key($path);
        $segments = array_values(array_filter(explode('/', trim(seo_preservation_normalize_path($path), '/'))));

        if (function_exists('all_articles')) {
            foreach (all_articles() as $article) {
                foreach (seo_preservation_content_paths($article) as $legacyPath) {
                    if (seo_preservation_path_key($legacyPath) === $key) {
                        return ['type' => 'article', 'slug' => (string)($article['slug'] ?? ''), 'item' => $article, 'match' => $legacyPath, 'mode' => 'legacy'];
                    }
                }
            }

            if (count($segments) === 1) {
                $article = function_exists('get_article_by_slug') ? get_article_by_slug((string)$segments[0]) : null;
                if ($article) {
                    return ['type' => 'article', 'slug' => (string)($article['slug'] ?? $segments[0]), 'item' => $article, 'match' => '/' . $segments[0], 'mode' => 'root-slug-alias'];
                }
            }
        }

        if (function_exists('landing_page_all')) {
            foreach (landing_page_all(true) as $page) {
                if ((string)($page['status'] ?? '') !== 'published') {
                    continue;
                }
                foreach (seo_preservation_content_paths($page) as $legacyPath) {
                    if (seo_preservation_path_key($legacyPath) === $key) {
                        return ['type' => 'landing_page', 'slug' => (string)($page['slug'] ?? ''), 'item' => $page, 'match' => $legacyPath, 'mode' => 'legacy'];
                    }
                }
            }
            if (count($segments) === 1 && function_exists('landing_page_public_find')) {
                $page = landing_page_public_find((string)$segments[0]);
                if ($page) {
                    return ['type' => 'landing_page', 'slug' => (string)($page['slug'] ?? $segments[0]), 'item' => $page, 'match' => '/' . $segments[0], 'mode' => 'root-slug-alias'];
                }
            }
        }

        if (function_exists('all_products')) {
            foreach (all_products() as $product) {
                foreach (seo_preservation_content_paths($product) as $legacyPath) {
                    if (seo_preservation_path_key($legacyPath) === $key) {
                        return ['type' => 'product', 'slug' => (string)($product['slug'] ?? ''), 'item' => $product, 'match' => $legacyPath, 'mode' => 'legacy'];
                    }
                }
            }
            if (count($segments) === 1 && function_exists('get_product_by_slug')) {
                $product = get_product_by_slug((string)$segments[0]);
                if ($product) {
                    return ['type' => 'product', 'slug' => (string)($product['slug'] ?? $segments[0]), 'item' => $product, 'match' => '/' . $segments[0], 'mode' => 'root-slug-alias'];
                }
            }
        }

        return null;
    }
}

if (!function_exists('seo_preservation_handle_request')) {
    function seo_preservation_handle_request(string $uri): bool
    {
        $path = seo_preservation_normalize_path($uri);
        $redirect = seo_preservation_match_redirect($path);
        if ($redirect) {
            seo_preservation_redirect_now($redirect);
        }

        $legacy = seo_preservation_find_legacy_content($path);
        if (!$legacy) {
            return false;
        }

        $GLOBALS['seo_preservation_legacy_route'] = $legacy;
        if ((string)($legacy['type'] ?? '') === 'article') {
            $_GET['slug'] = (string)($legacy['slug'] ?? '');
            require PAGES_PATH . '/artikel-detail.php';
            return true;
        }
        if ((string)($legacy['type'] ?? '') === 'landing_page') {
            $_GET['slug'] = (string)($legacy['slug'] ?? '');
            require PAGES_PATH . '/landing-page.php';
            return true;
        }
        if ((string)($legacy['type'] ?? '') === 'product') {
            $_GET['slug'] = (string)($legacy['slug'] ?? '');
            require PAGES_PATH . '/product-detail.php';
            return true;
        }

        return false;
    }
}

if (!function_exists('seo_preservation_preferred_content_url')) {
    function seo_preservation_preferred_content_url(array $item, string $fallbackUrl): string
    {
        $canonical = trim((string)($item['canonical_url'] ?? ''));
        if ($canonical !== '') {
            $absolute = seo_preservation_absolute_url($canonical);
            if (seo_preservation_same_site_url($absolute)) {
                return $absolute;
            }
        }
        return $fallbackUrl;
    }
}

if (!function_exists('seo_preservation_article_canonical')) {
    function seo_preservation_article_canonical(array $article): string
    {
        return seo_preservation_preferred_content_url($article, function_exists('article_permalink') ? article_permalink($article) : url('artikel/' . (string)($article['slug'] ?? '')));
    }
}

if (!function_exists('seo_preservation_landing_canonical')) {
    function seo_preservation_landing_canonical(array $page): string
    {
        return seo_preservation_preferred_content_url($page, function_exists('landing_page_url') ? landing_page_url((string)($page['slug'] ?? '')) : url('lp/' . (string)($page['slug'] ?? '')));
    }
}

if (!function_exists('seo_preservation_product_canonical')) {
    function seo_preservation_product_canonical(array $product): string
    {
        return seo_preservation_preferred_content_url($product, function_exists('product_url') ? product_url((string)($product['slug'] ?? '')) : url('produk/' . (string)($product['slug'] ?? '')));
    }
}

if (!function_exists('seo_preservation_scan_content_aliases')) {
    function seo_preservation_scan_content_aliases(): array
    {
        $rows = [];
        foreach (function_exists('all_articles') ? all_articles() : [] as $article) {
            foreach (seo_preservation_content_paths($article) as $path) {
                $rows[] = [
                    'type' => 'Artikel',
                    'title' => (string)($article['title'] ?? ''),
                    'source_path' => $path,
                    'canonical' => seo_preservation_article_canonical($article),
                    'target_url' => article_url((string)($article['slug'] ?? '')),
                    'mode' => seo_preservation_path_key($path) === seo_preservation_path_key(seo_preservation_article_canonical($article)) ? 'Preserve URL lama' : 'Alias/redirect kandidat',
                ];
            }
        }
        foreach (function_exists('landing_page_all') ? landing_page_all(true) : [] as $page) {
            foreach (seo_preservation_content_paths($page) as $path) {
                $rows[] = [
                    'type' => 'Landing Page',
                    'title' => (string)($page['title'] ?? ''),
                    'source_path' => $path,
                    'canonical' => seo_preservation_landing_canonical($page),
                    'target_url' => landing_page_url((string)($page['slug'] ?? '')),
                    'mode' => seo_preservation_path_key($path) === seo_preservation_path_key(seo_preservation_landing_canonical($page)) ? 'Preserve URL lama' : 'Alias/redirect kandidat',
                ];
            }
        }
        foreach (function_exists('all_products') ? all_products() : [] as $product) {
            foreach (seo_preservation_content_paths($product) as $path) {
                $rows[] = [
                    'type' => 'Produk/Layanan',
                    'title' => (string)($product['title'] ?? ''),
                    'source_path' => $path,
                    'canonical' => seo_preservation_product_canonical($product),
                    'target_url' => product_url((string)($product['slug'] ?? '')),
                    'mode' => seo_preservation_path_key($path) === seo_preservation_path_key(seo_preservation_product_canonical($product)) ? 'Preserve URL lama' : 'Alias/redirect kandidat',
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('seo_preservation_sync_redirects_from_content')) {
    function seo_preservation_sync_redirects_from_content(): array
    {
        $created = 0;
        $skipped = 0;
        foreach (seo_preservation_scan_content_aliases() as $row) {
            $source = (string)($row['source_path'] ?? '');
            $canonical = (string)($row['canonical'] ?? '');
            $target = (string)($row['target_url'] ?? '');
            if ($source === '' || $source === '/' || seo_preservation_path_key($source) === seo_preservation_path_key($canonical)) {
                $skipped++;
                continue;
            }
            seo_preservation_save_record([
                'id' => 'auto_' . substr(sha1($source), 0, 12),
                'source_path' => $source,
                'target_url' => $target,
                'code' => 301,
                'status' => 'inactive',
                'type' => 'auto-content-scan',
                'note' => 'Auto-scan dari legacy_url/original_url. Sengaja dibuat inactive agar owner bisa review sebelum mengaktifkan 301.',
            ]);
            $created++;
        }
        return ['created' => $created, 'skipped' => $skipped];
    }
}

if (!function_exists('seo_preservation_report')) {
    function seo_preservation_report(): array
    {
        $records = seo_preservation_records(true);
        $active = count(array_filter($records, static fn(array $r): bool => (string)($r['status'] ?? 'active') === 'active'));
        $aliases = seo_preservation_scan_content_aliases();
        $preserved = count(array_filter($aliases, static fn(array $r): bool => (string)($r['mode'] ?? '') === 'Preserve URL lama'));
        $candidates = count($aliases) - $preserved;
        $score = 70;
        if ($aliases) { $score += 10; }
        if ($active || $preserved) { $score += 10; }
        if (function_exists('wp_migration_jobs') && wp_migration_jobs(1)) { $score += 5; }
        if (is_file(seo_preservation_redirects_file())) { $score += 5; }
        return [
            'version' => 'V32.93 SEO Preservation Layer',
            'health_score' => min(100, $score),
            'records' => $records,
            'aliases' => $aliases,
            'counts' => [
                'redirects' => count($records),
                'active_redirects' => $active,
                'inactive_redirects' => count($records) - $active,
                'legacy_aliases' => count($aliases),
                'preserved_urls' => $preserved,
                'redirect_candidates' => $candidates,
            ],
            'storage_file' => seo_preservation_redirects_file(),
        ];
    }
}
