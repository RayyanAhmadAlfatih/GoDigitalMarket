<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BREADCRUMB & INTERNAL LINK MIGRATION
|--------------------------------------------------------------------------
| V32.94 helper layer for WordPress migration.
| - Universal breadcrumb path mapper for imported WP categories/subcategories.
| - Internal link scanner for articles/products/landing pages.
| - Safe link rewrite helper with storage backup before mutation.
| - Designed as additive layer above SEO Preservation V32.93.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('internal_link_migration_storage_dir')) {
    function internal_link_migration_storage_dir(): string
    {
        return STORAGE_PATH . '/internal-link-migration';
    }
}

if (!function_exists('internal_link_migration_ensure_storage')) {
    function internal_link_migration_ensure_storage(): void
    {
        $dir = internal_link_migration_storage_dir();
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

if (!function_exists('internal_link_migration_normalize_path')) {
    function internal_link_migration_normalize_path(string $urlOrPath): string
    {
        if (function_exists('seo_preservation_normalize_path')) {
            return seo_preservation_normalize_path($urlOrPath);
        }
        $urlOrPath = trim($urlOrPath);
        if ($urlOrPath === '') { return '/'; }
        $parts = parse_url($urlOrPath);
        $path = is_array($parts) ? (string)($parts['path'] ?? '/') : $urlOrPath;
        $path = rawurldecode($path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return '/' . trim($path, '/');
    }
}

if (!function_exists('internal_link_migration_path_key')) {
    function internal_link_migration_path_key(string $urlOrPath): string
    {
        if (function_exists('seo_preservation_path_key')) {
            return seo_preservation_path_key($urlOrPath);
        }
        return strtolower(internal_link_migration_normalize_path($urlOrPath));
    }
}

if (!function_exists('internal_link_migration_same_site')) {
    function internal_link_migration_same_site(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || preg_match('#^(mailto|tel|whatsapp|javascript):#i', $url)) {
            return false;
        }
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        $siteHost = strtolower((string)(parse_url((string)SITE_URL, PHP_URL_HOST) ?? ''));
        return $host === '' || $host === $siteHost;
    }
}

if (!function_exists('internal_link_migration_content_index')) {
    function internal_link_migration_content_index(): array
    {
        $rows = [];
        $add = static function (array $row) use (&$rows): void {
            $url = trim((string)($row['url'] ?? ''));
            if ($url === '') { return; }
            $paths = array_values(array_unique(array_filter(array_merge([
                internal_link_migration_normalize_path($url),
            ], (array)($row['paths'] ?? [])), static fn($p): bool => trim((string)$p) !== '' && trim((string)$p) !== '/')));
            foreach ($paths as $path) {
                $rows[internal_link_migration_path_key((string)$path)] = array_merge($row, [
                    'path' => internal_link_migration_normalize_path((string)$path),
                    'path_key' => internal_link_migration_path_key((string)$path),
                ]);
            }
        };

        foreach (function_exists('all_articles') ? all_articles() : [] as $article) {
            $target = function_exists('seo_preservation_article_canonical') ? seo_preservation_article_canonical($article) : (function_exists('article_permalink') ? article_permalink($article) : url('artikel/' . (string)($article['slug'] ?? '')));
            $paths = function_exists('seo_preservation_content_paths') ? seo_preservation_content_paths($article) : [];
            $add([
                'type' => 'article',
                'type_label' => 'Artikel',
                'id' => (string)($article['id'] ?? ''),
                'slug' => (string)($article['slug'] ?? ''),
                'title' => (string)($article['title'] ?? ''),
                'url' => $target,
                'edit_url' => url('admin/artikel'),
                'paths' => array_merge(['/artikel/' . (string)($article['slug'] ?? '')], $paths),
            ]);
        }

        foreach (function_exists('all_products') ? all_products() : [] as $product) {
            $target = function_exists('seo_preservation_product_canonical') ? seo_preservation_product_canonical($product) : (function_exists('product_permalink') ? product_permalink($product) : url('produk/' . (string)($product['slug'] ?? '')));
            $paths = function_exists('seo_preservation_content_paths') ? seo_preservation_content_paths($product) : [];
            $add([
                'type' => 'product',
                'type_label' => 'Produk/Layanan',
                'id' => (string)($product['id'] ?? ''),
                'slug' => (string)($product['slug'] ?? ''),
                'title' => (string)($product['title'] ?? ''),
                'url' => $target,
                'edit_url' => url('admin/produk'),
                'paths' => array_merge(['/produk/' . (string)($product['slug'] ?? '')], $paths),
            ]);
        }

        foreach (function_exists('landing_page_all') ? landing_page_all(true) : [] as $page) {
            $target = function_exists('seo_preservation_landing_canonical') ? seo_preservation_landing_canonical($page) : (function_exists('landing_page_url') ? landing_page_url((string)($page['slug'] ?? '')) : url('lp/' . (string)($page['slug'] ?? '')));
            $paths = function_exists('seo_preservation_content_paths') ? seo_preservation_content_paths($page) : [];
            $add([
                'type' => 'landing_page',
                'type_label' => 'Landing Page',
                'id' => (string)($page['id'] ?? ''),
                'slug' => (string)($page['slug'] ?? ''),
                'title' => (string)($page['title'] ?? ''),
                'url' => $target,
                'edit_url' => url('admin/landing-pages'),
                'paths' => array_merge(['/lp/' . (string)($page['slug'] ?? '')], $paths),
            ]);
        }

        return $rows;
    }
}

if (!function_exists('internal_link_migration_extract_links')) {
    function internal_link_migration_extract_links(string $html): array
    {
        if (trim($html) === '') { return []; }
        preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        return array_values(array_unique(array_map('trim', $matches[1] ?? [])));
    }
}

if (!function_exists('internal_link_migration_resolve_link')) {
    function internal_link_migration_resolve_link(string $href, array $index = []): array
    {
        $href = trim($href);
        $index = $index ?: internal_link_migration_content_index();
        if ($href === '' || str_starts_with($href, '#')) {
            return ['href' => $href, 'status' => 'anchor', 'suggested_url' => '', 'note' => 'Anchor/empty link'];
        }
        if (preg_match('#^(mailto|tel|whatsapp|javascript):#i', $href)) {
            return ['href' => $href, 'status' => 'special', 'suggested_url' => '', 'note' => 'Link khusus, tidak diubah'];
        }
        if (!internal_link_migration_same_site($href)) {
            return ['href' => $href, 'status' => 'external', 'suggested_url' => '', 'note' => 'External link'];
        }

        $key = internal_link_migration_path_key($href);
        if (isset($index[$key])) {
            $target = (string)($index[$key]['url'] ?? '');
            $status = internal_link_migration_path_key($target) === $key ? 'ok' : 'legacy_replacement';
            return [
                'href' => $href,
                'status' => $status,
                'suggested_url' => $target,
                'target_title' => (string)($index[$key]['title'] ?? ''),
                'target_type' => (string)($index[$key]['type_label'] ?? ''),
                'note' => $status === 'ok' ? 'Internal link sudah cocok' : 'Legacy/internal link bisa diarahkan ke URL utama konten',
            ];
        }

        if (function_exists('seo_preservation_match_redirect') && ($redirect = seo_preservation_match_redirect($href))) {
            return [
                'href' => $href,
                'status' => 'redirect_map',
                'suggested_url' => (string)($redirect['target_url'] ?? ''),
                'target_title' => 'Redirect map',
                'target_type' => 'Redirect',
                'note' => 'Terdeteksi di redirect map aktif',
            ];
        }

        if (function_exists('seo_preservation_find_legacy_content') && ($legacy = seo_preservation_find_legacy_content($href))) {
            $item = is_array($legacy['item'] ?? null) ? $legacy['item'] : [];
            $type = (string)($legacy['type'] ?? '');
            $target = match ($type) {
                'article' => function_exists('seo_preservation_article_canonical') ? seo_preservation_article_canonical($item) : url('artikel/' . (string)($legacy['slug'] ?? '')),
                'product' => function_exists('seo_preservation_product_canonical') ? seo_preservation_product_canonical($item) : url('produk/' . (string)($legacy['slug'] ?? '')),
                'landing_page' => function_exists('seo_preservation_landing_canonical') ? seo_preservation_landing_canonical($item) : url('lp/' . (string)($legacy['slug'] ?? '')),
                default => '',
            };
            return [
                'href' => $href,
                'status' => 'legacy_replacement',
                'suggested_url' => $target,
                'target_title' => (string)($item['title'] ?? ''),
                'target_type' => $type,
                'note' => 'Legacy URL ditemukan oleh SEO Preservation Layer',
            ];
        }

        return ['href' => $href, 'status' => 'unknown_internal', 'suggested_url' => '', 'note' => 'Internal link belum cocok dengan konten/redirect yang dikenal'];
    }
}

if (!function_exists('internal_link_migration_content_sources')) {
    function internal_link_migration_content_sources(): array
    {
        $sources = [];
        foreach (function_exists('all_articles') ? all_articles() : [] as $article) {
            $sources[] = [
                'type' => 'article', 'type_label' => 'Artikel', 'id' => (string)($article['id'] ?? ''), 'title' => (string)($article['title'] ?? ''), 'slug' => (string)($article['slug'] ?? ''),
                'url' => function_exists('article_permalink') ? article_permalink($article) : url('artikel/' . (string)($article['slug'] ?? '')),
                'field' => 'content', 'html' => (string)($article['content'] ?? ''), 'editable' => (string)($article['source'] ?? '') !== 'seed',
            ];
        }
        foreach (function_exists('all_products') ? all_products() : [] as $product) {
            $sources[] = [
                'type' => 'product', 'type_label' => 'Produk/Layanan', 'id' => (string)($product['id'] ?? ''), 'title' => (string)($product['title'] ?? ''), 'slug' => (string)($product['slug'] ?? ''),
                'url' => function_exists('product_permalink') ? product_permalink($product) : url('produk/' . (string)($product['slug'] ?? '')),
                'field' => 'content', 'html' => (string)($product['content'] ?? ''), 'editable' => (string)($product['source'] ?? '') !== 'seed',
            ];
        }
        foreach (function_exists('landing_page_all') ? landing_page_all(true) : [] as $page) {
            $html = json_encode($page['blocks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            $sources[] = [
                'type' => 'landing_page', 'type_label' => 'Landing Page', 'id' => (string)($page['id'] ?? ''), 'title' => (string)($page['title'] ?? ''), 'slug' => (string)($page['slug'] ?? ''),
                'url' => function_exists('landing_page_url') ? landing_page_url((string)($page['slug'] ?? '')) : url('lp/' . (string)($page['slug'] ?? '')),
                'field' => 'blocks', 'html' => $html, 'editable' => true,
            ];
        }
        return $sources;
    }
}

if (!function_exists('internal_link_migration_scan')) {
    function internal_link_migration_scan(): array
    {
        $index = internal_link_migration_content_index();
        $rows = [];
        $counts = ['total_links'=>0,'ok'=>0,'legacy_replacement'=>0,'redirect_map'=>0,'unknown_internal'=>0,'external'=>0,'special'=>0,'anchor'=>0,'editable_sources'=>0,'sources'=>0];
        foreach (internal_link_migration_content_sources() as $source) {
            $counts['sources']++;
            if (!empty($source['editable'])) { $counts['editable_sources']++; }
            foreach (internal_link_migration_extract_links((string)($source['html'] ?? '')) as $href) {
                $resolved = internal_link_migration_resolve_link($href, $index);
                $status = (string)($resolved['status'] ?? 'unknown_internal');
                $counts['total_links']++;
                if (!isset($counts[$status])) { $counts[$status] = 0; }
                $counts[$status]++;
                $rows[] = array_merge($resolved, [
                    'source_type' => (string)($source['type'] ?? ''),
                    'source_type_label' => (string)($source['type_label'] ?? ''),
                    'source_id' => (string)($source['id'] ?? ''),
                    'source_title' => (string)($source['title'] ?? ''),
                    'source_url' => (string)($source['url'] ?? ''),
                    'editable' => !empty($source['editable']),
                ]);
            }
        }
        $fixable = $counts['legacy_replacement'] + $counts['redirect_map'];
        $score = 70;
        if ($counts['total_links'] > 0) { $score += 10; }
        if ($counts['unknown_internal'] === 0) { $score += 10; }
        if ($fixable > 0) { $score += 5; }
        if (function_exists('breadcrumb_migration_report') && (int)(breadcrumb_migration_report()['counts']['custom_paths'] ?? 0) >= 0) { $score += 5; }
        return [
            'version' => 'V32.94 Breadcrumb & Internal Link Migration',
            'health_score' => min(100, $score),
            'counts' => $counts + ['fixable_links' => $fixable],
            'rows' => $rows,
            'generated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('internal_link_migration_backup_files')) {
    function internal_link_migration_backup_files(): string
    {
        internal_link_migration_ensure_storage();
        $backupDir = internal_link_migration_storage_dir() . '/backup-' . date('Ymd-His');
        @mkdir($backupDir, 0775, true);
        foreach ([
            function_exists('article_storage_path') ? article_storage_path() : '',
            function_exists('product_storage_path') ? product_storage_path() : '',
            function_exists('landing_page_storage_path') ? landing_page_storage_path() : '',
        ] as $path) {
            if ($path !== '' && is_file($path)) {
                @copy($path, $backupDir . '/' . basename($path));
            }
        }
        return $backupDir;
    }
}

if (!function_exists('internal_link_migration_replace_html')) {
    function internal_link_migration_replace_html(string $html, array $index = []): array
    {
        $index = $index ?: internal_link_migration_content_index();
        $replacements = [];
        foreach (internal_link_migration_extract_links($html) as $href) {
            $resolved = internal_link_migration_resolve_link($href, $index);
            $status = (string)($resolved['status'] ?? '');
            $target = trim((string)($resolved['suggested_url'] ?? ''));
            if (in_array($status, ['legacy_replacement', 'redirect_map'], true) && $target !== '') {
                $replacements[$href] = $target;
            }
        }
        foreach ($replacements as $from => $to) {
            $html = str_replace(['href="' . $from . '"', "href='" . $from . "'"], ['href="' . $to . '"', "href='" . $to . "'"], $html);
        }
        return ['html' => $html, 'replacements' => $replacements];
    }
}

if (!function_exists('internal_link_migration_rewrite_known_links')) {
    function internal_link_migration_rewrite_known_links(bool $dryRun = true): array
    {
        $index = internal_link_migration_content_index();
        $changed = 0;
        $replacements = 0;
        $backupDir = $dryRun ? '' : internal_link_migration_backup_files();

        if (function_exists('managed_articles') && function_exists('article_update')) {
            foreach (managed_articles() as $article) {
                $result = internal_link_migration_replace_html((string)($article['content'] ?? ''), $index);
                if ($result['replacements']) {
                    $replacements += count($result['replacements']);
                    if (!$dryRun) {
                        $article['content'] = (string)$result['html'];
                        if (article_update((int)($article['id'] ?? 0), $article)) { $changed++; }
                    } else { $changed++; }
                }
            }
        }

        if (function_exists('product_json_read') && function_exists('product_update')) {
            foreach (product_json_read() as $product) {
                $result = internal_link_migration_replace_html((string)($product['content'] ?? ''), $index);
                if ($result['replacements']) {
                    $replacements += count($result['replacements']);
                    if (!$dryRun) {
                        $product['content'] = (string)$result['html'];
                        if (product_update((int)($product['id'] ?? 0), $product)) { $changed++; }
                    } else { $changed++; }
                }
            }
        }

        if (function_exists('landing_page_all') && function_exists('landing_page_save')) {
            foreach (landing_page_all(true) as $page) {
                $json = json_encode($page['blocks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
                $result = internal_link_migration_replace_html($json, $index);
                if ($result['replacements']) {
                    $replacements += count($result['replacements']);
                    if (!$dryRun) {
                        $decoded = json_decode((string)$result['html'], true);
                        if (is_array($decoded)) {
                            $page['blocks'] = $decoded;
                            landing_page_save($page, ['action' => 'internal-link-migration', 'note' => 'Rewrite link internal dari V32.94']);
                            $changed++;
                        }
                    } else { $changed++; }
                }
            }
        }

        return ['dry_run' => $dryRun, 'changed_sources' => $changed, 'replacements' => $replacements, 'backup_dir' => $backupDir];
    }
}

if (!function_exists('breadcrumb_migration_label_for_type')) {
    function breadcrumb_migration_label_for_type(string $type): string
    {
        return match ($type) {
            'article' => function_exists('business_label') ? business_label('article', 'Artikel') : 'Artikel',
            'product' => function_exists('business_label') ? business_label('catalog', 'Katalog') : 'Katalog',
            'service' => function_exists('business_label') ? business_label('service', 'Layanan') : 'Layanan',
            'landing_page' => 'Landing Page',
            default => 'Halaman',
        };
    }
}

if (!function_exists('breadcrumb_migration_parse_custom_path')) {
    function breadcrumb_migration_parse_custom_path(mixed $raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $text = trim((string)$raw);
            if ($text === '') { return []; }
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/\s*(?:>|\/|›|»|,)\s*/u', $text) ?: [];
            }
        }
        $trail = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $label = trim((string)($item['label'] ?? $item['name'] ?? $item['title'] ?? ''));
                $url = trim((string)($item['url'] ?? ''));
            } else {
                $label = trim((string)$item);
                $url = '';
            }
            if ($label !== '' && strtolower($label) !== 'home') {
                $trail[] = ['name' => $label, 'url' => $url !== '' ? $url : url(slugify($label))];
            }
        }
        return $trail;
    }
}

if (!function_exists('breadcrumb_migration_trail')) {
    function breadcrumb_migration_trail(array $item, string $type, string $fallbackTitle, string $currentUrl): array
    {
        $title = trim((string)($item['breadcrumb_title'] ?? $fallbackTitle)) ?: $fallbackTitle;
        $custom = breadcrumb_migration_parse_custom_path($item['breadcrumb_path'] ?? $item['breadcrumb'] ?? '');
        $trail = [['name' => 'Home', 'url' => url('/')]];

        if ($custom) {
            foreach ($custom as $row) { $trail[] = $row; }
        } else {
            $sectionLabel = breadcrumb_migration_label_for_type($type);
            $sectionUrl = match ($type) {
                'article' => url('artikel'),
                'product' => url('katalog'),
                'service' => url('layanan'),
                'landing_page' => url('lp/' . (string)($item['slug'] ?? '')),
                default => url('/'),
            };
            $trail[] = ['name' => $sectionLabel, 'url' => $sectionUrl];

            foreach (['category', 'subcategory'] as $field) {
                $label = trim((string)($item[$field] ?? ''));
                if ($label !== '' && !in_array(strtolower($label), ['artikel','katalog','produk','layanan','landing page'], true)) {
                    $prefix = $type === 'article' ? 'kategori' : 'katalog';
                    if ($type === 'service') { $prefix = 'layanan'; }
                    $trail[] = ['name' => $label, 'url' => url($prefix . '/' . slugify($label))];
                }
            }
        }

        $trail[] = ['name' => $title, 'url' => $currentUrl];
        $deduped = [];
        $seen = [];
        foreach ($trail as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') { continue; }
            $key = strtolower($name . '|' . (string)($row['url'] ?? ''));
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $deduped[] = ['name' => $name, 'url' => trim((string)($row['url'] ?? '')) ?: $currentUrl];
        }
        return $deduped;
    }
}

if (!function_exists('breadcrumb_migration_render')) {
    function breadcrumb_migration_render(array $trail): void
    {
        $last = count($trail) - 1;
        echo '<div class="breadcrumb" data-breadcrumb-migration="v32.94">';
        foreach ($trail as $i => $item) {
            if ($i > 0) { echo '<span>/</span>'; }
            $label = (string)($item['name'] ?? '');
            $url = (string)($item['url'] ?? '');
            if ($i < $last && $url !== '') {
                echo '<a href="' . esc($url) . '">' . esc($label) . '</a>';
            } else {
                echo '<span>' . esc($label) . '</span>';
            }
        }
        echo '</div>';
    }
}

if (!function_exists('breadcrumb_migration_report')) {
    function breadcrumb_migration_report(): array
    {
        $rows = [];
        $custom = 0;
        $generated = 0;
        foreach (function_exists('all_articles') ? all_articles() : [] as $article) {
            $hasCustom = trim((string)($article['breadcrumb_path'] ?? $article['breadcrumb'] ?? '')) !== '';
            $rows[] = ['type'=>'Artikel','title'=>(string)($article['title'] ?? ''),'trail'=>breadcrumb_migration_trail($article, 'article', (string)($article['title'] ?? ''), function_exists('seo_preservation_article_canonical') ? seo_preservation_article_canonical($article) : article_permalink($article)), 'custom'=>$hasCustom];
            $hasCustom ? $custom++ : $generated++;
        }
        foreach (function_exists('all_products') ? all_products() : [] as $product) {
            $isService = function_exists('product_is_service_like') && product_is_service_like($product);
            $hasCustom = trim((string)($product['breadcrumb_path'] ?? $product['breadcrumb'] ?? '')) !== '';
            $rows[] = ['type'=>$isService ? 'Layanan' : 'Produk','title'=>(string)($product['title'] ?? ''),'trail'=>breadcrumb_migration_trail($product, $isService ? 'service' : 'product', (string)($product['title'] ?? ''), function_exists('seo_preservation_product_canonical') ? seo_preservation_product_canonical($product) : product_permalink($product)), 'custom'=>$hasCustom];
            $hasCustom ? $custom++ : $generated++;
        }
        foreach (function_exists('landing_page_all') ? landing_page_all(true) : [] as $page) {
            $hasCustom = trim((string)($page['breadcrumb_path'] ?? $page['breadcrumb'] ?? '')) !== '';
            $rows[] = ['type'=>'Landing Page','title'=>(string)($page['title'] ?? ''),'trail'=>breadcrumb_migration_trail($page, 'landing_page', (string)($page['title'] ?? ''), function_exists('seo_preservation_landing_canonical') ? seo_preservation_landing_canonical($page) : landing_page_url((string)($page['slug'] ?? ''))), 'custom'=>$hasCustom];
            $hasCustom ? $custom++ : $generated++;
        }
        return ['version'=>'V32.94 Breadcrumb Mapper','rows'=>$rows,'counts'=>['total'=>count($rows),'custom_paths'=>$custom,'generated_paths'=>$generated]];
    }
}
