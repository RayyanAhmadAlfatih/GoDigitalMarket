<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| NAVIGATION & FOOTER BUILDER ENGINE
|--------------------------------------------------------------------------
| Lightweight file-based settings for public header, menu, and footer.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('navigation_storage_file')) {
    function navigation_storage_file(): string
    {
        return STORAGE_PATH . '/navigation-settings.json';
    }
}

if (!function_exists('navigation_default_settings')) {
    function navigation_default_settings(): array
    {
        return [
            'header' => [
                'show_topbar' => true,
                'topbar_text' => SITE_TAGLINE,
                'show_topbar_phone' => true,
                'show_topbar_whatsapp' => true,
                'show_logo' => true,
                'show_menu' => true,
                'show_search' => true,
                'search_placeholder' => 'Cari produk, layanan, atau artikel...',
                'show_header_cta' => false,
                'header_cta_label' => 'Konsultasi',
                'header_cta_url' => '/kontak',
                'header_cta_new_tab' => false,
            ],
            'menu_items' => [
                ['label' => 'Home', 'url' => '/', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Katalog', 'url' => '/katalog', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Layanan', 'url' => '/layanan', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Portfolio', 'url' => '/portfolio', 'enabled' => false, 'new_tab' => false, 'children' => []],
                ['label' => 'Artikel', 'url' => '/artikel', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Tentang Kami', 'url' => '/tentang-kami', 'enabled' => true, 'new_tab' => false, 'children' => []],
                ['label' => 'Kontak', 'url' => '/kontak', 'enabled' => true, 'new_tab' => false, 'children' => []],
            ],
            'footer' => [
                'show_brand_column' => true,
                'brand_description' => DEFAULT_META_DESCRIPTION,
                'show_social_links' => true,
                'show_contact_line' => true,
                'copyright_text' => '© {year} {site}. All rights reserved.',
            ],
            'footer_columns' => [
                [
                    'title' => 'Katalog',
                    'links' => [
                        ['label' => 'Produk Fisik', 'url' => '/katalog', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Jasa & Layanan', 'url' => '/layanan', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Produk Digital', 'url' => '/katalog?category=Produk%20Digital', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Booking / Reservasi', 'url' => '/katalog?category=Booking', 'enabled' => true, 'new_tab' => false],
                    ],
                ],
                [
                    'title' => 'Halaman',
                    'links' => [
                        ['label' => 'Tentang Kami', 'url' => '/tentang-kami', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Kontak', 'url' => '/kontak', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Portfolio', 'url' => '/portfolio', 'enabled' => false, 'new_tab' => false],
                        ['label' => 'Artikel', 'url' => '/artikel', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Checkout', 'url' => '/checkout', 'enabled' => true, 'new_tab' => false],
                    ],
                ],
                [
                    'title' => 'Panduan',
                    'links' => [
                        ['label' => 'Panduan Bisnis', 'url' => '/artikel', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Marketing & SEO', 'url' => '/artikel?kategori=marketing-seo', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Checkout & Pembayaran', 'url' => '/artikel?kategori=checkout-pembayaran', 'enabled' => true, 'new_tab' => false],
                        ['label' => 'Privacy Policy', 'url' => '/privacy-policy', 'enabled' => true, 'new_tab' => false],
                    ],
                ],
            ],
            'bottom_links' => [
                ['label' => 'Privacy Policy', 'url' => '/privacy-policy', 'enabled' => true, 'new_tab' => false],
                ['label' => 'Terms', 'url' => '/terms', 'enabled' => true, 'new_tab' => false],
                ['label' => 'Sitemap', 'url' => '/sitemap.xml', 'enabled' => true, 'new_tab' => false],
            ],
            'updated_at' => '',
        ];
    }
}


if (!function_exists('navigation_array_is_list')) {
    function navigation_array_is_list(array $array): bool
    {
        $expected = 0;
        foreach (array_keys($array) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }
}

if (!function_exists('navigation_deep_merge')) {
    function navigation_deep_merge(array $defaults, array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && !navigation_array_is_list($value)) {
                $defaults[$key] = navigation_deep_merge($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }
}

if (!function_exists('navigation_settings')) {
    function navigation_settings(): array
    {
        static $cached = null;

        if (is_array($cached)) {
            return $cached;
        }

        $defaults = navigation_default_settings();
        $file = navigation_storage_file();

        if (!is_file($file)) {
            $cached = $defaults;
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);

        if (!is_array($decoded)) {
            $cached = $defaults;
            return $cached;
        }

        $cached = navigation_normalize_settings(navigation_deep_merge($defaults, $decoded));

        return $cached;
    }
}

if (!function_exists('navigation_forget_cache')) {
    function navigation_forget_cache(): void
    {
        // PHP request cache is intentionally static in navigation_settings().
        // New requests will load the new JSON after save/reset.
    }
}

if (!function_exists('navigation_bool')) {
    function navigation_bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        return $default;
    }
}

if (!function_exists('navigation_clean_text')) {
    function navigation_clean_text(mixed $value, int $limit = 120): string
    {
        $value = trim(strip_tags((string)$value));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        return substr($value, 0, $limit);
    }
}

if (!function_exists('navigation_clean_url')) {
    function navigation_clean_url(mixed $value, string $fallback = '/'): string
    {
        $value = trim((string)$value);

        if ($value === '') {
            return $fallback;
        }

        if ($value === '#') {
            return '#';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)) {
            return $value;
        }

        if (preg_match('#^(mailto:|tel:)#i', $value)) {
            return $value;
        }

        if (str_starts_with($value, '?')) {
            return '/' . $value;
        }

        $value = '/' . ltrim($value, '/');

        return preg_match('#^/[a-zA-Z0-9._~/%?=&+\-]*$#', $value)
            ? $value
            : $fallback;
    }
}

if (!function_exists('navigation_url_to_href')) {
    function navigation_url_to_href(string $urlValue): string
    {
        $urlValue = trim($urlValue);

        if ($urlValue === '') {
            return url('');
        }

        if ($urlValue === '#') {
            return '#';
        }

        if (filter_var($urlValue, FILTER_VALIDATE_URL) || preg_match('#^(mailto:|tel:)#i', $urlValue)) {
            return $urlValue;
        }

        return url(ltrim($urlValue, '/'));
    }
}

if (!function_exists('navigation_target_attrs')) {
    function navigation_target_attrs(bool $newTab): string
    {
        return $newTab ? ' target="_blank" rel="noopener"' : '';
    }
}

if (!function_exists('navigation_parse_children')) {
    function navigation_parse_children(mixed $value): array
    {
        $children = [];

        if (is_array($value)) {
            foreach ($value as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $label = navigation_clean_text($child['label'] ?? '', 80);
                $url = navigation_clean_url($child['url'] ?? '', '');

                if ($label === '' || $url === '') {
                    continue;
                }

                $children[] = [
                    'label' => $label,
                    'url' => $url,
                    'enabled' => navigation_bool($child['enabled'] ?? true, true),
                    'new_tab' => navigation_bool($child['new_tab'] ?? false, false),
                ];
            }

            return array_slice($children, 0, 8);
        }

        $lines = preg_split('/\R/', (string)$value) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) < 2) {
                continue;
            }

            $label = navigation_clean_text($parts[0], 80);
            $url = navigation_clean_url($parts[1], '');

            if ($label === '' || $url === '') {
                continue;
            }

            $children[] = [
                'label' => $label,
                'url' => $url,
                'enabled' => true,
                'new_tab' => false,
            ];
        }

        return array_slice($children, 0, 8);
    }
}

if (!function_exists('navigation_children_to_text')) {
    function navigation_children_to_text(array $children): string
    {
        $lines = [];

        foreach ($children as $child) {
            if (empty($child['enabled'])) {
                continue;
            }

            $label = navigation_clean_text($child['label'] ?? '', 80);
            $url = navigation_clean_url($child['url'] ?? '', '');
            if ($label !== '' && $url !== '') {
                $lines[] = $label . ' | ' . $url;
            }
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('navigation_normalize_menu_item')) {
    function navigation_normalize_menu_item(array $item): array
    {
        return [
            'label' => navigation_clean_text($item['label'] ?? '', 80),
            'url' => navigation_clean_url($item['url'] ?? '/', '/'),
            'enabled' => navigation_bool($item['enabled'] ?? true, true),
            'new_tab' => navigation_bool($item['new_tab'] ?? false, false),
            'children' => navigation_parse_children($item['children'] ?? []),
        ];
    }
}

if (!function_exists('navigation_normalize_link')) {
    function navigation_normalize_link(array $item): array
    {
        return [
            'label' => navigation_clean_text($item['label'] ?? '', 80),
            'url' => navigation_clean_url($item['url'] ?? '/', '/'),
            'enabled' => navigation_bool($item['enabled'] ?? true, true),
            'new_tab' => navigation_bool($item['new_tab'] ?? false, false),
        ];
    }
}

if (!function_exists('navigation_normalize_settings')) {
    function navigation_normalize_settings(array $settings): array
    {
        $defaults = navigation_default_settings();
        $settings = navigation_deep_merge($defaults, $settings);

        $header = is_array($settings['header'] ?? null) ? $settings['header'] : [];
        $settings['header'] = [
            'show_topbar' => navigation_bool($header['show_topbar'] ?? true, true),
            'topbar_text' => navigation_clean_text($header['topbar_text'] ?? SITE_TAGLINE, 180),
            'show_topbar_phone' => navigation_bool($header['show_topbar_phone'] ?? true, true),
            'show_topbar_whatsapp' => navigation_bool($header['show_topbar_whatsapp'] ?? true, true),
            'show_logo' => navigation_bool($header['show_logo'] ?? true, true),
            'show_menu' => navigation_bool($header['show_menu'] ?? true, true),
            'show_search' => navigation_bool($header['show_search'] ?? true, true),
            'search_placeholder' => navigation_clean_text($header['search_placeholder'] ?? 'Cari produk, layanan, atau artikel...', 120),
            'show_header_cta' => navigation_bool($header['show_header_cta'] ?? false, false),
            'header_cta_label' => navigation_clean_text($header['header_cta_label'] ?? 'Konsultasi', 40),
            'header_cta_url' => navigation_clean_url($header['header_cta_url'] ?? '/kontak', '/kontak'),
            'header_cta_new_tab' => navigation_bool($header['header_cta_new_tab'] ?? false, false),
        ];

        $menu = [];
        foreach ((array)($settings['menu_items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = navigation_normalize_menu_item($item);
            if ($normalized['label'] !== '') {
                $menu[] = $normalized;
            }
        }
        $settings['menu_items'] = array_slice($menu ?: $defaults['menu_items'], 0, 10);

        $footer = is_array($settings['footer'] ?? null) ? $settings['footer'] : [];
        $settings['footer'] = [
            'show_brand_column' => navigation_bool($footer['show_brand_column'] ?? true, true),
            'brand_description' => navigation_clean_text($footer['brand_description'] ?? DEFAULT_META_DESCRIPTION, 260),
            'show_social_links' => navigation_bool($footer['show_social_links'] ?? true, true),
            'show_contact_line' => navigation_bool($footer['show_contact_line'] ?? true, true),
            'copyright_text' => navigation_clean_text($footer['copyright_text'] ?? '© {year} {site}. All rights reserved.', 160),
        ];

        $columns = [];
        foreach ((array)($settings['footer_columns'] ?? []) as $column) {
            if (!is_array($column)) {
                continue;
            }

            $title = navigation_clean_text($column['title'] ?? '', 80);
            $links = [];

            foreach ((array)($column['links'] ?? []) as $link) {
                if (!is_array($link)) {
                    continue;
                }
                $normalized = navigation_normalize_link($link);
                if ($normalized['label'] !== '') {
                    $links[] = $normalized;
                }
            }

            if ($title !== '' || $links) {
                $columns[] = [
                    'title' => $title !== '' ? $title : 'Menu Footer',
                    'links' => array_slice($links, 0, 10),
                ];
            }
        }
        $settings['footer_columns'] = array_slice($columns ?: $defaults['footer_columns'], 0, 4);

        $bottom = [];
        foreach ((array)($settings['bottom_links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $normalized = navigation_normalize_link($link);
            if ($normalized['label'] !== '') {
                $bottom[] = $normalized;
            }
        }
        $settings['bottom_links'] = array_slice($bottom ?: $defaults['bottom_links'], 0, 6);

        return $settings;
    }
}

if (!function_exists('navigation_settings_from_post')) {
    function navigation_settings_from_post(array $post): array
    {
        $defaults = navigation_default_settings();
        $settings = $defaults;

        $settings['header'] = [
            'show_topbar' => isset($post['show_topbar']),
            'topbar_text' => navigation_clean_text($post['topbar_text'] ?? '', 180),
            'show_topbar_phone' => isset($post['show_topbar_phone']),
            'show_topbar_whatsapp' => isset($post['show_topbar_whatsapp']),
            'show_logo' => isset($post['show_logo']),
            'show_menu' => isset($post['show_menu']),
            'show_search' => isset($post['show_search']),
            'search_placeholder' => navigation_clean_text($post['search_placeholder'] ?? '', 120),
            'show_header_cta' => isset($post['show_header_cta']),
            'header_cta_label' => navigation_clean_text($post['header_cta_label'] ?? '', 40),
            'header_cta_url' => navigation_clean_url($post['header_cta_url'] ?? '/kontak', '/kontak'),
            'header_cta_new_tab' => isset($post['header_cta_new_tab']),
        ];

        $labels = (array)($post['menu_label'] ?? []);
        $urls = (array)($post['menu_url'] ?? []);
        $children = (array)($post['menu_children'] ?? []);
        $enabled = (array)($post['menu_enabled'] ?? []);
        $newTabs = (array)($post['menu_new_tab'] ?? []);
        $menu = [];

        for ($i = 0; $i < min(10, max(count($labels), count($urls))); $i++) {
            $label = navigation_clean_text($labels[$i] ?? '', 80);
            $urlValue = navigation_clean_url($urls[$i] ?? '', '');

            if ($label === '' && $urlValue === '') {
                continue;
            }

            if ($label === '' || $urlValue === '') {
                continue;
            }

            $menu[] = [
                'label' => $label,
                'url' => $urlValue,
                'enabled' => isset($enabled[$i]),
                'new_tab' => isset($newTabs[$i]),
                'children' => navigation_parse_children($children[$i] ?? ''),
            ];
        }
        $settings['menu_items'] = $menu ?: $defaults['menu_items'];

        $settings['footer'] = [
            'show_brand_column' => isset($post['footer_show_brand_column']),
            'brand_description' => navigation_clean_text($post['footer_brand_description'] ?? '', 260),
            'show_social_links' => isset($post['footer_show_social_links']),
            'show_contact_line' => isset($post['footer_show_contact_line']),
            'copyright_text' => navigation_clean_text($post['footer_copyright_text'] ?? '', 160),
        ];

        $columnTitles = (array)($post['footer_column_title'] ?? []);
        $columnLinkLabels = (array)($post['footer_link_label'] ?? []);
        $columnLinkUrls = (array)($post['footer_link_url'] ?? []);
        $columnLinkEnabled = (array)($post['footer_link_enabled'] ?? []);
        $columnLinkNewTab = (array)($post['footer_link_new_tab'] ?? []);
        $columns = [];

        for ($c = 0; $c < 4; $c++) {
            $title = navigation_clean_text($columnTitles[$c] ?? '', 80);
            $links = [];
            $labelsForColumn = (array)($columnLinkLabels[$c] ?? []);
            $urlsForColumn = (array)($columnLinkUrls[$c] ?? []);

            for ($i = 0; $i < min(8, max(count($labelsForColumn), count($urlsForColumn))); $i++) {
                $label = navigation_clean_text($labelsForColumn[$i] ?? '', 80);
                $urlValue = navigation_clean_url($urlsForColumn[$i] ?? '', '');

                if ($label === '' && $urlValue === '') {
                    continue;
                }

                if ($label === '' || $urlValue === '') {
                    continue;
                }

                $links[] = [
                    'label' => $label,
                    'url' => $urlValue,
                    'enabled' => isset($columnLinkEnabled[$c][$i]),
                    'new_tab' => isset($columnLinkNewTab[$c][$i]),
                ];
            }

            if ($title !== '' || $links) {
                $columns[] = ['title' => $title !== '' ? $title : 'Menu Footer', 'links' => $links];
            }
        }
        $settings['footer_columns'] = $columns ?: $defaults['footer_columns'];

        $bottomLabels = (array)($post['bottom_link_label'] ?? []);
        $bottomUrls = (array)($post['bottom_link_url'] ?? []);
        $bottomEnabled = (array)($post['bottom_link_enabled'] ?? []);
        $bottomNewTab = (array)($post['bottom_link_new_tab'] ?? []);
        $bottom = [];

        for ($i = 0; $i < min(6, max(count($bottomLabels), count($bottomUrls))); $i++) {
            $label = navigation_clean_text($bottomLabels[$i] ?? '', 80);
            $urlValue = navigation_clean_url($bottomUrls[$i] ?? '', '');

            if ($label === '' && $urlValue === '') {
                continue;
            }

            if ($label === '' || $urlValue === '') {
                continue;
            }

            $bottom[] = [
                'label' => $label,
                'url' => $urlValue,
                'enabled' => isset($bottomEnabled[$i]),
                'new_tab' => isset($bottomNewTab[$i]),
            ];
        }
        $settings['bottom_links'] = $bottom ?: $defaults['bottom_links'];
        $settings['updated_at'] = date(DATE_ATOM);

        return navigation_normalize_settings($settings);
    }
}

if (!function_exists('navigation_save_settings')) {
    function navigation_save_settings(array $settings): array
    {
        $settings = navigation_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            throw new RuntimeException('Folder penyimpanan belum bisa dibuat.');
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false || file_put_contents(navigation_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Pengaturan menu dan footer gagal disimpan. Cek permission folder storage.');
        }

        @chmod(navigation_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'navigation', null, 'Pengaturan menu, header, dan footer diperbarui.');
        }

        return $settings;
    }
}

if (!function_exists('navigation_reset_settings')) {
    function navigation_reset_settings(): void
    {
        navigation_save_settings(navigation_default_settings());
    }
}

if (!function_exists('navigation_header')) {
    function navigation_header(string $key, mixed $default = null): mixed
    {
        $settings = navigation_settings();

        return $settings['header'][$key] ?? $default;
    }
}

if (!function_exists('navigation_footer')) {
    function navigation_footer(string $key, mixed $default = null): mixed
    {
        $settings = navigation_settings();

        return $settings['footer'][$key] ?? $default;
    }
}

if (!function_exists('navigation_public_menu_items')) {
    function navigation_public_menu_items(): array
    {
        $settings = navigation_settings();

        return array_values(array_filter((array)($settings['menu_items'] ?? []), static function (array $item): bool {
            return !empty($item['enabled']) && trim((string)($item['label'] ?? '')) !== '';
        }));
    }
}

if (!function_exists('navigation_footer_columns')) {
    function navigation_footer_columns(): array
    {
        $settings = navigation_settings();

        return (array)($settings['footer_columns'] ?? []);
    }
}

if (!function_exists('navigation_bottom_links')) {
    function navigation_bottom_links(): array
    {
        $settings = navigation_settings();

        return (array)($settings['bottom_links'] ?? []);
    }
}

if (!function_exists('navigation_path_for_active')) {
    function navigation_path_for_active(string $urlValue): string
    {
        $urlValue = trim($urlValue);

        if ($urlValue === '' || $urlValue === '/') {
            return '';
        }

        if (filter_var($urlValue, FILTER_VALIDATE_URL)) {
            $siteHost = parse_url(SITE_URL, PHP_URL_HOST);
            $host = parse_url($urlValue, PHP_URL_HOST);
            if ($host !== $siteHost) {
                return '';
            }
            $urlValue = (string)(parse_url($urlValue, PHP_URL_PATH) ?? '/');
        }

        $urlValue = strtok($urlValue, '?') ?: $urlValue;

        return trim($urlValue, '/');
    }
}

if (!function_exists('navigation_active_class')) {
    function navigation_active_class(string $urlValue): string
    {
        $target = navigation_path_for_active($urlValue);

        if ($target === '') {
            return is_home() ? 'active' : '';
        }

        if (route_is($target) || route_starts_with($target . '/')) {
            return 'active';
        }

        return '';
    }
}

if (!function_exists('navigation_render_menu')) {
    function navigation_render_menu(bool $mobile = false): void
    {
        $items = navigation_public_menu_items();
        $ulClass = $mobile ? 'mobile-menu-list' : 'nav-menu';
        $submenuClass = $mobile ? 'mobile-submenu' : 'nav-dropdown';

        echo '<ul class="' . esc($ulClass) . '">';

        foreach ($items as $item) {
            $children = array_values(array_filter((array)($item['children'] ?? []), static fn(array $child): bool => !empty($child['enabled']) && trim((string)($child['label'] ?? '')) !== ''));
            $hasChildren = !empty($children);
            $liClass = $hasChildren ? ($mobile ? ' class="mobile-has-children"' : ' class="nav-item nav-item--has-children"') : ($mobile ? '' : ' class="nav-item"');
            $active = navigation_active_class((string)$item['url']);
            $activeAttr = $active !== '' ? ' class="' . esc($active) . '" aria-current="page"' : '';

            echo '<li' . $liClass . '>';
            echo '<a href="' . esc(navigation_url_to_href((string)$item['url'])) . '"' . $activeAttr . navigation_target_attrs(!empty($item['new_tab'])) . '>' . esc((string)$item['label']) . ($hasChildren && !$mobile ? ' <span aria-hidden="true">▾</span>' : '') . '</a>';

            if ($hasChildren) {
                echo '<ul class="' . esc($submenuClass) . '">';
                foreach ($children as $child) {
                    echo '<li><a href="' . esc(navigation_url_to_href((string)$child['url'])) . '"' . navigation_target_attrs(!empty($child['new_tab'])) . '>' . esc((string)$child['label']) . '</a></li>';
                }
                echo '</ul>';
            }

            echo '</li>';
        }

        echo '</ul>';
    }
}

if (!function_exists('navigation_render_footer_links')) {
    function navigation_render_footer_links(array $links): void
    {
        echo '<ul class="footer-links">';

        foreach ($links as $link) {
            if (empty($link['enabled']) || trim((string)($link['label'] ?? '')) === '') {
                continue;
            }

            echo '<li><a href="' . esc(navigation_url_to_href((string)$link['url'])) . '"' . navigation_target_attrs(!empty($link['new_tab'])) . '>' . esc((string)$link['label']) . '</a></li>';
        }

        echo '</ul>';
    }
}

if (!function_exists('navigation_copyright_text')) {
    function navigation_copyright_text(): string
    {
        $text = (string)navigation_footer('copyright_text', '© {year} {site}. All rights reserved.');

        return strtr($text, [
            '{year}' => (string)date('Y'),
            '{site}' => SITE_NAME,
        ]);
    }
}
