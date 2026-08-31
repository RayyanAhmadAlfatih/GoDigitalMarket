<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('dynamic_term_lower')) {
    function dynamic_term_lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('dynamic_term_values')) {
    function dynamic_term_values(mixed $value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                $out = array_merge($out, dynamic_term_values($item));
            }
            return array_values(array_filter(array_map('trim', array_map('strval', $out))));
        }

        $value = trim((string)$value);
        if ($value === '') {
            return [];
        }

        if (str_contains($value, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [$value];
    }
}

if (!function_exists('dynamic_term_tokens_from_slug')) {
    function dynamic_term_tokens_from_slug(string $slug): array
    {
        $slug = trim(slugify($slug));
        if ($slug === '') {
            return [];
        }
        return array_values(array_filter(explode('-', $slug), static fn(string $token): bool => strlen($token) >= 3));
    }
}

if (!function_exists('dynamic_term_item_terms')) {
    function dynamic_term_item_terms(array $item, string $type): array
    {
        $seo = is_array($item['seo'] ?? null) ? $item['seo'] : [];
        $terms = [];

        $terms['kategori'] = dynamic_term_values($item['category'] ?? '');
        $terms['tag'] = dynamic_term_values($item['tags'] ?? []);
        $terms['keyword'] = array_merge(
            dynamic_term_values($item['keywords'] ?? []),
            dynamic_term_values($seo['keywords'] ?? []),
            dynamic_term_values($item['focus_keyword'] ?? ''),
            dynamic_term_values($item['meta_keywords'] ?? '')
        );

        if ($type === 'product') {
            $terms['kategori'] = array_merge($terms['kategori'], dynamic_term_values($item['type'] ?? ''), dynamic_term_values($item['subcategory'] ?? ''), dynamic_term_values($item['breed'] ?? ''), dynamic_term_values($item['tier'] ?? ''));
            $terms['tag'] = array_merge($terms['tag'], dynamic_term_values($item['location'] ?? ''), dynamic_term_values($item['animal_type'] ?? ''));
        }

        if ($type === 'article') {
            $terms['keyword'] = array_merge($terms['keyword'], dynamic_term_values($item['title'] ?? ''), dynamic_term_values($item['excerpt'] ?? ''));
        }

        $slugTokens = dynamic_term_tokens_from_slug((string)($item['slug'] ?? ''));
        if ($slugTokens) {
            $terms['slug'] = $slugTokens;
        }

        foreach ($terms as $key => $values) {
            $clean = [];
            foreach ($values as $value) {
                $value = trim(strip_tags((string)$value));
                if ($value !== '' && strlen($value) >= 2) {
                    $clean[] = $value;
                }
            }
            $terms[$key] = array_values(array_unique($clean));
        }

        return $terms;
    }
}

if (!function_exists('dynamic_term_item_text')) {
    function dynamic_term_item_text(array $item): string
    {
        $seo = is_array($item['seo'] ?? null) ? $item['seo'] : [];
        return dynamic_term_lower(strip_tags(implode(' ', array_filter([
            $item['title'] ?? '', $item['slug'] ?? '', $item['category'] ?? '', $item['subcategory'] ?? '', $item['type'] ?? '',
            $item['description'] ?? '', $item['excerpt'] ?? '', $item['content'] ?? '', $item['location'] ?? '',
            implode(' ', dynamic_term_values($item['tags'] ?? [])), implode(' ', dynamic_term_values($item['keywords'] ?? [])),
            implode(' ', dynamic_term_values($seo['keywords'] ?? [])), $item['focus_keyword'] ?? '', $item['meta_keywords'] ?? '',
        ]))));
    }
}

if (!function_exists('dynamic_term_match_item')) {
    function dynamic_term_match_item(array $item, string $itemType, string $termType, string $slug): ?array
    {
        $termType = in_array($termType, ['kategori', 'tag', 'keyword', 'slug'], true) ? $termType : 'keyword';
        $slug = slugify($slug);
        if ($slug === '') {
            return null;
        }

        $terms = dynamic_term_item_terms($item, $itemType);
        $score = 0;
        $matched = [];

        foreach (($terms[$termType] ?? []) as $term) {
            if (slugify($term) === $slug) {
                $score += 80;
                $matched[] = $term;
            }
        }

        foreach ($terms as $group => $values) {
            foreach ($values as $term) {
                $termSlug = slugify($term);
                if ($termSlug === $slug) {
                    $score += $group === $termType ? 40 : 24;
                    $matched[] = $term;
                } elseif ($termSlug !== '' && (str_contains($termSlug, $slug) || str_contains($slug, $termSlug))) {
                    $score += $group === $termType ? 14 : 8;
                    $matched[] = $term;
                }
            }
        }

        $needleText = str_replace('-', ' ', $slug);
        if (str_contains(dynamic_term_item_text($item), dynamic_term_lower($needleText))) {
            $score += 12;
        }

        if ($score <= 0) {
            return null;
        }

        return [
            'type' => $itemType,
            'score' => $score,
            'matched_terms' => array_values(array_unique($matched)),
            'item' => $item,
        ];
    }
}

if (!function_exists('dynamic_term_page')) {
    function dynamic_term_page(string $termType, string $slug, int $limit = 24): ?array
    {
        $termType = in_array($termType, ['kategori', 'tag', 'keyword', 'slug'], true) ? $termType : 'keyword';
        $slug = slugify($slug);
        if ($slug === '') {
            return null;
        }

        $matches = [];
        foreach (all_products() as $product) {
            if ((string)($product['status'] ?? 'published') === 'draft') {
                continue;
            }
            $match = dynamic_term_match_item($product, 'product', $termType, $slug);
            if ($match) {
                $matches[] = $match;
            }
        }
        foreach (all_articles() as $article) {
            $match = dynamic_term_match_item($article, 'article', $termType, $slug);
            if ($match) {
                $matches[] = $match;
            }
        }

        usort($matches, static fn(array $a, array $b): int => (int)$b['score'] <=> (int)$a['score']);
        $matches = array_slice($matches, 0, $limit);

        if (!$matches) {
            return null;
        }

        $label = seo_landing_title_case(str_replace('-', ' ', $slug));
        $typeLabel = match ($termType) {
            'kategori' => 'Kategori',
            'tag' => 'Tag',
            'slug' => 'Topik Slug',
            default => 'Keyword',
        };

        $productCount = count(array_filter($matches, static fn(array $m): bool => $m['type'] === 'product'));
        $articleCount = count(array_filter($matches, static fn(array $m): bool => $m['type'] === 'article'));
        $title = $typeLabel . ' ' . $label;

        return [
            'term_type' => $termType,
            'slug' => $slug,
            'label' => $label,
            'title' => $title,
            'description' => 'Kumpulan produk, layanan, artikel, dan halaman terkait ' . $label . ' dari ' . SITE_NAME . '.',
            'matches' => $matches,
            'products' => array_values(array_map(static fn(array $m): array => $m['item'], array_filter($matches, static fn(array $m): bool => $m['type'] === 'product'))),
            'articles' => array_values(array_map(static fn(array $m): array => $m['item'], array_filter($matches, static fn(array $m): bool => $m['type'] === 'article'))),
            'product_count' => $productCount,
            'article_count' => $articleCount,
            'canonical' => url(($termType === 'kategori' ? 'kategori' : $termType) . '/' . $slug),
        ];
    }
}

if (!function_exists('dynamic_term_sitemap_urls')) {
    function dynamic_term_sitemap_urls(array $urls, int $limit = 120): array
    {
        $seen = [];
        $add = static function (string $type, string $value) use (&$seen, &$urls, $limit): void {
            if (count($seen) >= $limit) {
                return;
            }
            $slug = slugify($value);
            if ($slug === '' || isset($seen[$type . ':' . $slug])) {
                return;
            }
            $seen[$type . ':' . $slug] = true;
            $pathType = $type === 'kategori' ? 'kategori' : $type;
            $urls[] = ['loc' => url($pathType . '/' . $slug), 'changefreq' => 'weekly', 'priority' => $type === 'kategori' ? '0.65' : '0.55'];
        };

        foreach (all_products() as $product) {
            foreach (dynamic_term_item_terms($product, 'product') as $type => $terms) {
                if (!in_array($type, ['kategori', 'tag', 'keyword'], true)) {
                    continue;
                }
                foreach (array_slice($terms, 0, 10) as $term) {
                    $add($type, $term);
                }
            }
        }
        foreach (all_articles() as $article) {
            foreach (dynamic_term_item_terms($article, 'article') as $type => $terms) {
                if (!in_array($type, ['kategori', 'tag', 'keyword'], true)) {
                    continue;
                }
                foreach (array_slice($terms, 0, 10) as $term) {
                    $add($type, $term);
                }
            }
        }

        return $urls;
    }
}
