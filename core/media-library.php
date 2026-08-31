<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| Template MEDIA & ASSET SEO MANAGER
|--------------------------------------------------------------------------
| Scans local image assets, maps usage to products/articles/landing pages,
| audits SEO readiness, generates safe alt/filename recommendations, and
| supports local-only bulk alt suggestions. Shared-hosting safe.
|--------------------------------------------------------------------------
*/


if (!function_exists('media_library_lower')) {
    function media_library_lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }
}

if (!function_exists('media_library_allowed_extensions')) {
    function media_library_allowed_extensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    }
}

if (!function_exists('media_library_format_bytes')) {
    function media_library_format_bytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }
        return $bytes . ' B';
    }
}

if (!function_exists('media_library_asset_relative_from_url')) {
    function media_library_asset_relative_from_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $path = (string)(parse_url($url, PHP_URL_PATH) ?: $url);
        $path = str_replace('\\', '/', $path);
        $pos = strpos($path, 'assets/');
        if ($pos !== false) {
            return ltrim(substr($path, $pos), '/');
        }

        return ltrim($path, '/');
    }
}

if (!function_exists('media_library_url')) {
    function media_library_url(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if (str_starts_with($relative, 'assets/')) {
            return asset(substr($relative, 7));
        }
        return asset($relative);
    }
}

if (!function_exists('media_library_references')) {
    function media_library_references(): array
    {
        $references = [];

        $push = static function (string $url, array $ref) use (&$references): void {
            $relative = media_library_asset_relative_from_url($url);
            if ($relative === '') {
                return;
            }
            $references[$relative][] = $ref;
        };

        foreach (function_exists('all_products') ? all_products() : [] as $product) {
            $title = trim((string)($product['title'] ?? 'Produk'));
            $id = (int)($product['id'] ?? 0);
            $source = (string)($product['source'] ?? $product['_source'] ?? 'admin');
            $editUrl = $source === 'seed' ? '' : url('admin/produk?action=edit&id=' . $id);
            $viewUrl = !empty($product['slug']) && function_exists('product_url') ? product_url((string)$product['slug']) : url('katalog');

            if (!empty($product['image'])) {
                $push((string)$product['image'], [
                    'type' => 'product',
                    'field' => 'image',
                    'title' => $title,
                    'id' => $id,
                    'source' => $source,
                    'alt' => trim((string)($product['image_alt'] ?? '')),
                    'edit_url' => $editUrl,
                    'view_url' => $viewUrl,
                ]);
            }

            foreach ((array)($product['gallery'] ?? []) as $galleryImage) {
                $push((string)$galleryImage, [
                    'type' => 'product',
                    'field' => 'gallery',
                    'title' => $title,
                    'id' => $id,
                    'source' => $source,
                    'alt' => '',
                    'edit_url' => $editUrl,
                    'view_url' => $viewUrl,
                ]);
            }
        }

        $articleRows = function_exists('managed_articles')
            ? managed_articles()
            : (function_exists('all_articles') ? all_articles() : []);

        foreach ($articleRows as $article) {
            $title = trim((string)($article['title'] ?? 'Artikel'));
            $id = (int)($article['id'] ?? 0);
            $source = (string)($article['source'] ?? $article['_source'] ?? 'admin');
            $editUrl = $source === 'seed' ? '' : url('admin/artikel?action=edit&id=' . $id);
            $viewUrl = !empty($article['slug']) && function_exists('article_url') ? article_url((string)$article['slug']) : url('artikel');

            if (!empty($article['image'])) {
                $push((string)$article['image'], [
                    'type' => 'article',
                    'field' => 'image',
                    'title' => $title,
                    'id' => $id,
                    'source' => $source,
                    'alt' => trim((string)($article['image_alt'] ?? '')),
                    'edit_url' => $editUrl,
                    'view_url' => $viewUrl,
                ]);
            }
        }

        foreach (function_exists('landing_page_all') ? landing_page_all(true) : [] as $landingPage) {
            $pageTitle = trim((string)($landingPage['title'] ?? $landingPage['slug'] ?? 'Landing Page'));
            $pageId = (string)($landingPage['id'] ?? '');
            $pageSlug = (string)($landingPage['slug'] ?? '');
            $editUrl = $pageId !== '' ? url('admin/landing-pages?builder=' . rawurlencode($pageId)) : url('admin/landing-pages');
            $viewUrl = $pageSlug !== '' ? url('lp/' . rawurlencode($pageSlug)) : url('admin/landing-pages');

            if (!empty($landingPage['og_image'])) {
                $push((string)$landingPage['og_image'], [
                    'type' => 'landing_page',
                    'field' => 'og_image',
                    'title' => $pageTitle,
                    'id' => $pageId,
                    'source' => 'landing-page',
                    'alt' => '',
                    'edit_url' => $editUrl,
                    'view_url' => $viewUrl,
                ]);
            }

            foreach ((array)($landingPage['blocks'] ?? []) as $blockIndex => $block) {
                if (!is_array($block)) {
                    continue;
                }
                $blockTitle = trim((string)($block['headline'] ?? $block['title'] ?? $pageTitle));
                if (!empty($block['image'])) {
                    $push((string)$block['image'], [
                        'type' => 'landing_page',
                        'field' => 'block_image',
                        'title' => $blockTitle !== '' ? $blockTitle : $pageTitle,
                        'id' => $pageId,
                        'block_index' => (int)$blockIndex,
                        'block_type' => (string)($block['type'] ?? ''),
                        'source' => 'landing-page',
                        'alt' => trim((string)($block['image_alt'] ?? '')),
                        'edit_url' => $editUrl,
                        'view_url' => $viewUrl,
                    ]);
                }

                foreach ((array)($block['items'] ?? []) as $itemIndex => $item) {
                    if (!is_array($item) || empty($item['image'])) {
                        continue;
                    }
                    $itemTitle = trim((string)($item['title'] ?? $item['headline'] ?? $blockTitle));
                    $push((string)$item['image'], [
                        'type' => 'landing_page',
                        'field' => 'item_image',
                        'title' => $itemTitle !== '' ? $itemTitle : $pageTitle,
                        'id' => $pageId,
                        'block_index' => (int)$blockIndex,
                        'item_index' => (int)$itemIndex,
                        'block_type' => (string)($block['type'] ?? ''),
                        'source' => 'landing-page',
                        'alt' => trim((string)($item['image_alt'] ?? '')),
                        'edit_url' => $editUrl,
                        'view_url' => $viewUrl,
                    ]);
                }
            }
        }

        return $references;
    }
}


if (!function_exists('media_library_reference_requires_alt')) {
    function media_library_reference_requires_alt(array $ref): bool
    {
        return in_array((string)($ref['field'] ?? ''), ['image', 'block_image', 'item_image'], true);
    }
}

if (!function_exists('media_library_slug_words')) {
    function media_library_slug_words(string $value, int $maxWords = 9): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (function_exists('slugify')) {
            $slug = slugify($value);
        } else {
            $slug = strtolower($value);
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?: '';
            $slug = trim($slug, '-');
        }
        $words = array_values(array_filter(explode('-', $slug), static fn(string $word): bool => $word !== ''));
        $words = array_slice($words, 0, max(1, $maxWords));
        return implode('-', $words);
    }
}

if (!function_exists('media_library_asset_context_title')) {
    function media_library_asset_context_title(array $refs, string $fallback): string
    {
        foreach ($refs as $ref) {
            $title = trim((string)($ref['title'] ?? ''));
            if ($title !== '') {
                return $title;
            }
        }

        $name = pathinfo($fallback, PATHINFO_FILENAME);
        $name = str_replace(['-', '_'], ' ', $name);
        return trim($name) !== '' ? trim($name) : 'Gambar produk layanan';
    }
}

if (!function_exists('media_library_str_limit')) {
    function media_library_str_limit(string $value, int $limit): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }
        return substr($value, 0, $limit);
    }
}

if (!function_exists('media_library_alt_suggestion')) {
    function media_library_alt_suggestion(array $item): string
    {
        $context = media_library_asset_context_title((array)($item['references'] ?? []), (string)($item['filename'] ?? ''));
        $context = preg_replace('/\s+/', ' ', trim((string)$context)) ?: 'Gambar produk atau layanan';
        $suffix = 'Produk & Layanan';
        $alt = $context;
        if (!str_contains(media_library_lower($alt), 'produk') && !str_contains(media_library_lower($alt), 'layanan')) {
            $alt .= ' - ' . $suffix;
        }
        return media_library_str_limit($alt, 140);
    }
}

if (!function_exists('media_library_filename_is_seo_friendly')) {
    function media_library_filename_is_seo_friendly(string $filename): bool
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $lower = media_library_lower($name);
        if ($name === '' || $lower !== $name) {
            return false;
        }
        if (preg_match('/\s|_/', $name)) {
            return false;
        }
        if (!str_contains($name, '-')) {
            return false;
        }
        foreach (['img', 'image', 'photo', 'foto', 'untitled', 'screenshot', 'whatsapp-image', 'dsc'] as $generic) {
            if (str_contains($lower, $generic)) {
                return false;
            }
        }
        return (bool)preg_match('/^[a-z0-9-]+$/', $name);
    }
}

if (!function_exists('media_library_filename_suggestion')) {
    function media_library_filename_suggestion(array $item): string
    {
        $ext = strtolower((string)($item['extension'] ?? pathinfo((string)($item['filename'] ?? ''), PATHINFO_EXTENSION)));
        $context = media_library_asset_context_title((array)($item['references'] ?? []), (string)($item['filename'] ?? ''));
        $slug = media_library_slug_words($context, 10);
        if ($slug === '') {
            $slug = media_library_slug_words((string)($item['filename'] ?? 'gambar-produk-layanan'), 10);
        }
        if ($slug === '') {
            $slug = 'gambar-produk-layanan';
        }
        return $slug . ($ext !== '' ? '.' . $ext : '');
    }
}

if (!function_exists('media_library_asset_score')) {
    function media_library_asset_score(array $item): array
    {
        $score = 100;
        if ((int)($item['missing_alt_count'] ?? 0) > 0) {
            $score -= 30;
        }
        if (!empty($item['is_large'])) {
            $score -= 22;
        }
        if (empty($item['used'])) {
            $score -= 14;
        }
        if (!media_library_filename_is_seo_friendly((string)($item['filename'] ?? ''))) {
            $score -= 12;
        }
        $ext = strtolower((string)($item['extension'] ?? ''));
        if ($ext !== '' && !in_array($ext, ['webp', 'svg'], true)) {
            $score -= 8;
        }
        if ((int)($item['width'] ?? 0) > 1800 || (int)($item['height'] ?? 0) > 1800) {
            $score -= 6;
        }

        $score = max(0, min(100, $score));
        $grade = $score >= 86 ? 'ok' : ($score >= 70 ? 'warning' : 'error');
        return ['score' => $score, 'grade' => $grade];
    }
}

if (!function_exists('media_library_asset_recommendations')) {
    function media_library_asset_recommendations(array $item): array
    {
        $recommendations = [];
        if ((int)($item['missing_alt_count'] ?? 0) > 0) {
            $recommendations[] = 'Isi alt text untuk gambar yang dipakai di produk/artikel/landing page.';
        }
        if (!empty($item['is_large'])) {
            $recommendations[] = 'Kompres gambar atau upload versi WebP yang lebih ringan sebelum dipakai untuk traffic iklan.';
        }
        if (!media_library_filename_is_seo_friendly((string)($item['filename'] ?? ''))) {
            $recommendations[] = 'Nama file kurang SEO-friendly. Simpan saran filename untuk upload berikutnya; jangan rename file lama kalau sudah terindeks.';
        }
        $ext = strtolower((string)($item['extension'] ?? ''));
        if ($ext !== '' && !in_array($ext, ['webp', 'svg'], true)) {
            $recommendations[] = 'Pertimbangkan versi WebP dengan nama file SEO yang sama agar ukuran lebih ringan.';
        }
        if (empty($item['used'])) {
            $recommendations[] = 'File belum terhubung ke konten. Pakai ulang jika relevan atau arsipkan manual jika benar-benar tidak dibutuhkan.';
        }
        if (!$recommendations) {
            $recommendations[] = 'Asset sudah cukup sehat. Pertahankan alt text, ukuran ringan, dan nama file SEO.';
        }
        return $recommendations;
    }
}

if (!function_exists('media_library_enrich_item')) {
    function media_library_enrich_item(array $item): array
    {
        $score = media_library_asset_score($item);
        $item['seo_score'] = (int)$score['score'];
        $item['seo_grade'] = (string)$score['grade'];
        $item['alt_suggestion'] = media_library_alt_suggestion($item);
        $item['filename_is_seo'] = media_library_filename_is_seo_friendly((string)($item['filename'] ?? ''));
        $item['filename_suggestion'] = media_library_filename_suggestion($item);
        $item['webp_suggestion'] = preg_replace('/\.[^.]+$/', '.webp', (string)$item['filename_suggestion']);
        $item['recommendations'] = media_library_asset_recommendations($item);
        $item['reference_types'] = array_values(array_unique(array_map(static fn(array $ref): string => (string)($ref['type'] ?? ''), (array)($item['references'] ?? []))));
        return $item;
    }
}


if (!function_exists('media_library_scan')) {
    function media_library_scan(array $options = []): array
    {
        $roots = $options['roots'] ?? ['assets/uploads', 'assets/images'];
        $query = media_library_lower(trim((string)($options['q'] ?? '')));
        $status = trim((string)($options['status'] ?? 'all'));
        $issue = trim((string)($options['issue'] ?? 'all'));
        $largeThreshold = (int)($options['large_threshold'] ?? (500 * 1024));
        $references = media_library_references();
        $items = [];

        foreach ($roots as $root) {
            $root = trim((string)$root, '/');
            $dir = ROOT_PATH . '/' . $root;
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $ext = strtolower($file->getExtension());
                if (!in_array($ext, media_library_allowed_extensions(), true)) {
                    continue;
                }

                $path = str_replace('\\', '/', $file->getPathname());
                $relative = ltrim(str_replace(str_replace('\\', '/', ROOT_PATH) . '/', '', $path), '/');
                $url = media_library_url($relative);
                $size = (int)$file->getSize();
                $dims = ['width' => null, 'height' => null];
                if ($ext !== 'svg') {
                    $imageSize = @getimagesize($file->getPathname());
                    if (is_array($imageSize)) {
                        $dims = ['width' => (int)$imageSize[0], 'height' => (int)$imageSize[1]];
                    }
                }

                $refs = $references[$relative] ?? [];
                $used = count($refs) > 0;
                $missingAltRefs = array_values(array_filter($refs, static function (array $ref): bool {
                    return media_library_reference_requires_alt($ref) && trim((string)($ref['alt'] ?? '')) === '';
                }));
                $isLarge = $size > $largeThreshold || ((int)($dims['width'] ?? 0) > 2200) || ((int)($dims['height'] ?? 0) > 2200);

                $issues = [];
                if ($isLarge) {
                    $issues[] = 'large';
                }
                if ($missingAltRefs) {
                    $issues[] = 'missing_alt';
                }
                if (!$used) {
                    $issues[] = 'unused';
                }

                $itemStatus = $missingAltRefs ? 'missing_alt' : ($isLarge ? 'large' : (!$used ? 'unused' : 'ok'));
                $haystack = media_library_lower($relative . ' ' . implode(' ', array_map(static fn(array $ref): string => (string)($ref['title'] ?? ''), $refs)) . ' ' . media_library_alt_suggestion(['filename' => $file->getFilename(), 'references' => $refs]));

                if ($query !== '' && !str_contains($haystack, $query)) {
                    continue;
                }
                if ($status !== '' && $status !== 'all') {
                    if ($status === 'used' && !$used) {
                        continue;
                    }
                    if ($status === 'unused' && $used) {
                        continue;
                    }
                    if ($status === 'large' && !$isLarge) {
                        continue;
                    }
                    if ($status === 'missing_alt' && !$missingAltRefs) {
                        continue;
                    }
                    if ($status === 'ok' && $itemStatus !== 'ok') {
                        continue;
                    }
                }

                $item = media_library_enrich_item([
                    'relative' => $relative,
                    'url' => $url,
                    'filename' => $file->getFilename(),
                    'folder' => dirname($relative),
                    'extension' => $ext,
                    'size' => $size,
                    'size_label' => media_library_format_bytes($size),
                    'width' => $dims['width'],
                    'height' => $dims['height'],
                    'modified_at' => date('Y-m-d H:i:s', (int)$file->getMTime()),
                    'used' => $used,
                    'references' => $refs,
                    'missing_alt_count' => count($missingAltRefs),
                    'is_large' => $isLarge,
                    'issues' => $issues,
                    'status' => $itemStatus,
                ]);

                if ($issue !== '' && $issue !== 'all') {
                    $matchesIssue = match ($issue) {
                        'missing_alt' => (int)($item['missing_alt_count'] ?? 0) > 0,
                        'large' => !empty($item['is_large']),
                        'unused' => empty($item['used']),
                        'filename' => empty($item['filename_is_seo']),
                        'not_webp' => !in_array((string)($item['extension'] ?? ''), ['webp', 'svg'], true),
                        'low_score' => (int)($item['seo_score'] ?? 100) < 80,
                        default => true,
                    };
                    if (!$matchesIssue) {
                        continue;
                    }
                }

                $items[] = $item;
            }
        }

        usort($items, static function (array $a, array $b): int {
            $rank = ['missing_alt' => 5, 'large' => 4, 'unused' => 3, 'ok' => 1];
            $statusRank = ($rank[(string)$b['status']] ?? 0) <=> ($rank[(string)$a['status']] ?? 0);
            if ($statusRank !== 0) {
                return $statusRank;
            }
            return ((int)$b['size']) <=> ((int)$a['size']);
        });

        return $items;
    }
}

if (!function_exists('media_library_summary')) {
    function media_library_summary(): array
    {
        $items = media_library_scan(['status' => 'all']);
        $summary = [
            'total' => count($items),
            'used' => 0,
            'unused' => 0,
            'large' => 0,
            'missing_alt' => 0,
            'low_score' => 0,
            'filename_issue' => 0,
            'not_webp' => 0,
            'landing_page_refs' => 0,
            'total_size' => 0,
        ];

        foreach ($items as $item) {
            $summary['total_size'] += (int)$item['size'];
            $summary[!empty($item['used']) ? 'used' : 'unused']++;
            if (!empty($item['is_large'])) {
                $summary['large']++;
            }
            if ((int)($item['missing_alt_count'] ?? 0) > 0) {
                $summary['missing_alt']++;
            }
            if ((int)($item['seo_score'] ?? 100) < 80) {
                $summary['low_score']++;
            }
            if (empty($item['filename_is_seo'])) {
                $summary['filename_issue']++;
            }
            if (!in_array((string)($item['extension'] ?? ''), ['webp', 'svg'], true)) {
                $summary['not_webp']++;
            }
            foreach ((array)($item['references'] ?? []) as $ref) {
                if ((string)($ref['type'] ?? '') === 'landing_page') {
                    $summary['landing_page_refs']++;
                    break;
                }
            }
        }

        $summary['total_size_label'] = media_library_format_bytes((int)$summary['total_size']);
        $summary['avg_score'] = $summary['total'] > 0 ? (int)round(array_sum(array_map(static fn(array $item): int => (int)($item['seo_score'] ?? 0), $items)) / max(1, $summary['total'])) : 0;
        return $summary;
    }
}

if (!function_exists('media_library_is_allowed_image_url')) {
    function media_library_is_allowed_image_url(string $url): bool
    {
        $relative = media_library_asset_relative_from_url($url);
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        return $relative !== '' && str_starts_with($relative, 'assets/') && in_array($ext, media_library_allowed_extensions(), true);
    }
}


if (!function_exists('media_library_apply_suggested_alt')) {
    function media_library_apply_suggested_alt(array $items, int $limit = 200): array
    {
        $updates = ['product' => 0, 'article' => 0, 'landing_page' => 0, 'skipped' => 0, 'total' => 0];
        $suggestions = [];
        foreach ($items as $item) {
            if ((int)($item['missing_alt_count'] ?? 0) <= 0) {
                continue;
            }
            $relative = (string)($item['relative'] ?? '');
            if ($relative === '') {
                continue;
            }
            $suggestions[$relative] = media_library_alt_suggestion($item);
            if (count($suggestions) >= $limit) {
                break;
            }
        }

        if (!$suggestions) {
            return $updates;
        }

        if (function_exists('product_json_read') && function_exists('product_write_json')) {
            $products = product_json_read();
            $changed = false;
            foreach ($products as &$product) {
                $relative = media_library_asset_relative_from_url((string)($product['image'] ?? ''));
                if ($relative !== '' && isset($suggestions[$relative]) && trim((string)($product['image_alt'] ?? '')) === '') {
                    $product['image_alt'] = $suggestions[$relative];
                    $updates['product']++;
                    $updates['total']++;
                    $changed = true;
                }
            }
            unset($product);
            if ($changed && !product_write_json($products)) {
                $updates['skipped'] += $updates['product'];
                $updates['total'] -= $updates['product'];
                $updates['product'] = 0;
            }
        }

        if (function_exists('article_storage_path') && function_exists('article_write_json')) {
            $path = article_storage_path();
            $articles = [];
            if (is_file($path)) {
                $decoded = json_decode((string)@file_get_contents($path), true);
                $articles = is_array($decoded) ? (isset($decoded['articles']) && is_array($decoded['articles']) ? $decoded['articles'] : $decoded) : [];
            }
            $changed = false;
            foreach ($articles as &$article) {
                $relative = media_library_asset_relative_from_url((string)($article['image'] ?? ''));
                if ($relative !== '' && isset($suggestions[$relative]) && trim((string)($article['image_alt'] ?? '')) === '') {
                    $article['image_alt'] = $suggestions[$relative];
                    $updates['article']++;
                    $updates['total']++;
                    $changed = true;
                }
            }
            unset($article);
            if ($changed && !article_write_json($articles)) {
                $updates['skipped'] += $updates['article'];
                $updates['total'] -= $updates['article'];
                $updates['article'] = 0;
            }
        }

        if (function_exists('landing_page_read_raw') && function_exists('landing_page_write_raw')) {
            $pages = landing_page_read_raw();
            $changed = false;
            foreach ($pages as &$page) {
                foreach ((array)($page['blocks'] ?? []) as $blockIndex => $block) {
                    if (!is_array($block)) {
                        continue;
                    }
                    $relative = media_library_asset_relative_from_url((string)($block['image'] ?? ''));
                    if ($relative !== '' && isset($suggestions[$relative]) && trim((string)($block['image_alt'] ?? '')) === '') {
                        $page['blocks'][$blockIndex]['image_alt'] = $suggestions[$relative];
                        $updates['landing_page']++;
                        $updates['total']++;
                        $changed = true;
                    }
                    foreach ((array)($block['items'] ?? []) as $itemIndex => $entry) {
                        if (!is_array($entry)) {
                            continue;
                        }
                        $relative = media_library_asset_relative_from_url((string)($entry['image'] ?? ''));
                        if ($relative !== '' && isset($suggestions[$relative]) && trim((string)($entry['image_alt'] ?? '')) === '') {
                            $page['blocks'][$blockIndex]['items'][$itemIndex]['image_alt'] = $suggestions[$relative];
                            $updates['landing_page']++;
                            $updates['total']++;
                            $changed = true;
                        }
                    }
                }
            }
            unset($page);
            if ($changed && !landing_page_write_raw($pages)) {
                $updates['skipped'] += $updates['landing_page'];
                $updates['total'] -= $updates['landing_page'];
                $updates['landing_page'] = 0;
            }
        }

        if (function_exists('activity_log_record') && $updates['total'] > 0) {
            activity_log_record('update', 'media_library', null, 'Bulk alt suggestion applied.', $updates);
        }

        return $updates;
    }
}
