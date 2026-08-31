<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| Template CLEAN SEO LANDING REGISTRY
|--------------------------------------------------------------------------
| Local-only landing registry for clean SEO URLs:
| /produk/{slug}, /layanan/{slug}, /lokasi/{slug}, /katalog/{slug}.
|
| The registry is generated from real product data plus small admin overrides.
| It avoids thin/duplicate index bloat by only exposing landings that have
| matching products and can be toggled off from /admin/seo-landings.
|--------------------------------------------------------------------------
*/

if (!function_exists('seo_landing_storage_path')) {
    function seo_landing_storage_path(): string
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }

        return STORAGE_PATH . '/seo-landings.json';
    }
}

if (!function_exists('seo_landing_settings_read')) {
    function seo_landing_settings_read(): array
    {
        $path = seo_landing_storage_path();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string)@file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('seo_landing_settings_write')) {
    function seo_landing_settings_write(array $settings): bool
    {
        return (bool)@file_put_contents(
            seo_landing_storage_path(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
}

if (!function_exists('seo_landing_allowed_prefixes')) {
    function seo_landing_allowed_prefixes(): array
    {
        return ['produk', 'layanan', 'lokasi', 'katalog'];
    }
}

if (!function_exists('seo_landing_clean_label')) {
    function seo_landing_clean_label(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';
        $value = trim($value, " \t\n\r\0\x0B,.;:-");
        $value = str_ireplace(['jakarta selatan', 'jakarta selatan'], 'Jakarta Selatan', $value);
        $value = str_ireplace(['area lama satu', 'area lama dua'], 'Tangerang Selatan', $value);
        $value = str_ireplace(['depok', 'depok'], 'Depok', $value);
        $value = str_ireplace(['area lokal', 'area-lokal'], 'Area Lokal', $value);
        $value = str_ireplace(['surabaya', 'surabaya'], 'Surabaya', $value);

        return trim($value);
    }
}

if (!function_exists('seo_landing_title_case')) {
    function seo_landing_title_case(string $value): string
    {
        $value = seo_landing_clean_label($value);
        if ($value === '') {
            return '';
        }

        $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $words = preg_split('/\s+/u', $lower) ?: [];
        $words = array_map(static function (string $word): string {
            if ($word === '') {
                return '';
            }
            $first = function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
            $rest = function_exists('mb_substr') ? mb_substr($word, 1, null, 'UTF-8') : substr($word, 1);
            $first = function_exists('mb_strtoupper') ? mb_strtoupper($first, 'UTF-8') : strtoupper($first);
            return $first . $rest;
        }, $words);

        return seo_landing_clean_label(implode(' ', $words));
    }
}

if (!function_exists('seo_landing_slug')) {
    function seo_landing_slug(array $parts): string
    {
        $parts = array_values(array_filter(array_map(static fn($part): string => trim((string)$part), $parts)));
        return slugify(implode(' ', $parts));
    }
}

if (!function_exists('seo_landing_value_contains')) {
    function seo_landing_value_contains(string $haystack, string $needle): bool
    {
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
        $needle = function_exists('mb_strtolower') ? mb_strtolower($needle, 'UTF-8') : strtolower($needle);
        return $needle !== '' && str_contains($haystack, $needle);
    }
}

if (!function_exists('seo_landing_product_text')) {
    function seo_landing_product_text(array $product): string
    {
        return implode(' ', array_map('strval', [
            $product['title'] ?? '',
            $product['category'] ?? '',
            $product['subcategory'] ?? '',
            $product['type'] ?? '',
            $product['animal_type'] ?? '',
            $product['breed'] ?? '',
            $product['tier'] ?? '',
            $product['location'] ?? '',
            $product['excerpt'] ?? '',
            $product['description'] ?? '',
        ]));
    }
}

if (!function_exists('seo_landing_derive_animal')) {
    function seo_landing_derive_animal(array $product): string
    {
        $titleCategoryBreed = implode(' ', array_map('strval', [
            $product['title'] ?? '',
            $product['category'] ?? '',
            $product['breed'] ?? '',
        ]));

        foreach (['Produk Fisik', 'Paket', 'Layanan'] as $animal) {
            if (seo_landing_value_contains($titleCategoryBreed, $animal)) {
                return $animal;
            }
        }

        $candidate = seo_landing_clean_label((string)($product['animal_type'] ?? ''));
        if (in_array($candidate, ['Produk Fisik', 'Paket', 'Layanan'], true)) {
            return $candidate;
        }

        $text = seo_landing_product_text($product);
        foreach (['Produk Fisik', 'Paket', 'Layanan'] as $animal) {
            if (seo_landing_value_contains($text, $animal)) {
                return $animal;
            }
        }

        return $candidate;
    }
}

if (!function_exists('seo_landing_derive_service')) {
    function seo_landing_derive_service(array $product): string
    {
        $text = seo_landing_product_text($product);
        if (seo_landing_value_contains($text, 'layanan')) {
            return 'layanan';
        }
        if (seo_landing_value_contains($text, 'produk')) {
            return 'produk';
        }

        return 'produk';
    }
}

if (!function_exists('seo_landing_product_matches')) {
    function seo_landing_product_matches(array $product, array $filters): bool
    {
        $service = trim((string)($filters['service'] ?? ''));
        if ($service !== '' && seo_landing_derive_service($product) !== $service) {
            return false;
        }

        $animal = trim((string)($filters['animal_type'] ?? ''));
        if ($animal !== '' && !seo_landing_value_contains(seo_landing_derive_animal($product), $animal) && !seo_landing_value_contains(seo_landing_product_text($product), $animal)) {
            return false;
        }

        $breed = trim((string)($filters['breed'] ?? ''));
        if ($breed !== '' && !seo_landing_value_contains(seo_landing_clean_label((string)($product['breed'] ?? '')), $breed) && !seo_landing_value_contains(seo_landing_clean_label((string)($product['title'] ?? '')), $breed)) {
            return false;
        }

        $tier = trim((string)($filters['tier'] ?? ''));
        if ($tier !== '' && !seo_landing_value_contains(seo_landing_clean_label((string)($product['tier'] ?? $product['subcategory'] ?? '')), $tier)) {
            return false;
        }

        $location = trim((string)($filters['location'] ?? ''));
        if ($location !== '') {
            $productLocation = seo_landing_clean_label((string)($product['location'] ?? ''));
            if (!seo_landing_value_contains($productLocation, $location) && !seo_landing_value_contains($location, $productLocation)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('seo_landing_filter_products')) {
    function seo_landing_filter_products(array $filters): array
    {
        $products = [];
        foreach (all_products() as $product) {
            if (seo_landing_product_matches($product, $filters)) {
                $products[] = $product;
            }
        }

        return array_values($products);
    }
}

if (!function_exists('seo_landing_record_key')) {
    function seo_landing_record_key(string $prefix, string $slug): string
    {
        return slugify($prefix) . ':' . slugify($slug);
    }
}

if (!function_exists('seo_landing_make_record')) {
    function seo_landing_make_record(string $prefix, string $slug, string $title, string $description, array $filters, string $template = 'catalog', array $extra = []): array
    {
        $prefix = slugify($prefix);
        $slug = slugify($slug);
        $products = seo_landing_filter_products($filters);
        $productSlugs = array_values(array_filter(array_map(static fn(array $product): string => (string)($product['slug'] ?? ''), $products)));
        sort($productSlugs);

        $record = array_merge([
            'key' => seo_landing_record_key($prefix, $slug),
            'prefix' => $prefix,
            'slug' => $slug,
            'path' => $prefix . '/' . $slug,
            'url' => url($prefix . '/' . $slug),
            'title' => $title,
            'h1' => $title,
            'description' => limit_chars($description, 155),
            'summary' => $description,
            'template' => $template,
            'filters' => $filters,
            'product_count' => count($products),
            'product_slugs' => $productSlugs,
            'products' => $products,
            'source' => 'generated',
            'priority' => $prefix === 'lokasi' ? '0.72' : '0.74',
            'changefreq' => 'weekly',
            'lastmod' => date('c'),
            'indexable' => count($products) > 0,
            'enabled' => true,
        ], $extra);

        $record['description'] = limit_chars((string)$record['description'], 155);
        $record['summary'] = trim((string)$record['summary']);

        return $record;
    }
}

if (!function_exists('seo_landing_add_record')) {
    function seo_landing_add_record(array &$records, array $record): void
    {
        $key = (string)($record['key'] ?? '');
        if ($key === '') {
            return;
        }

        if (!isset($records[$key])) {
            $records[$key] = $record;
            return;
        }

        $existingCount = (int)($records[$key]['product_count'] ?? 0);
        $newCount = (int)($record['product_count'] ?? 0);
        if ($newCount > $existingCount) {
            $records[$key] = $record;
        }
    }
}

if (!function_exists('seo_landing_generated_records')) {
    function seo_landing_generated_records(): array
    {
        $records = [];
        $locations = [];

        foreach (all_products() as $product) {
            $title = trim((string)($product['title'] ?? ''));
            $slug = trim((string)($product['slug'] ?? ''));
            if ($title === '' || $slug === '') {
                continue;
            }

            $service = seo_landing_derive_service($product);
            $animal = seo_landing_derive_animal($product);
            $breed = seo_landing_title_case((string)($product['breed'] ?? ''));
            $tier = seo_landing_title_case((string)($product['tier'] ?? $product['subcategory'] ?? ''));
            $location = seo_landing_title_case((string)($product['location'] ?? ''));

            if ($location !== '') {
                $locations[$location] = $location;
            }

            if ($service === 'produk') {
                if ($animal !== '' && $location !== '') {
                    seo_landing_add_record($records, seo_landing_make_record(
                        'produk',
                        seo_landing_slug([$animal, $location]),
                        $animal . ' Produk di ' . $location,
                        'Pilihan ' . strtolower($animal) . ' produk di ' . $location . ' dari katalog yang tersedia, lengkap dengan status stok dan tombol konsultasi WhatsApp.',
                        ['service' => 'produk', 'animal_type' => $animal, 'location' => $location],
                        'service-location',
                        ['label' => 'Produk ' . $animal]
                    ));
                }

                if ($breed !== '' && $location !== '') {
                    seo_landing_add_record($records, seo_landing_make_record(
                        'produk',
                        seo_landing_slug([$breed, $location]),
                        $breed . ' Produk di ' . $location,
                        'Katalog ' . $breed . ' untuk produk area ' . $location . ' dengan pilihan produk atau layanan terkait dan jalur konsultasi admin.',
                        ['service' => 'produk', 'breed' => $breed, 'location' => $location],
                        'breed-location',
                        ['label' => 'Produk ' . $breed]
                    ));
                }

                if ($animal !== '' && $tier !== '' && $location !== '') {
                    seo_landing_add_record($records, seo_landing_make_record(
                        'produk',
                        seo_landing_slug([$animal, $tier, $location]),
                        $animal . ' Produk ' . $tier . ' di ' . $location,
                        'Pilihan ' . strtolower($animal) . ' produk kelas ' . strtolower($tier) . ' untuk area ' . $location . ' dengan produk yang relevan dan siap dikonsultasikan.',
                        ['service' => 'produk', 'animal_type' => $animal, 'tier' => $tier, 'location' => $location],
                        'service-tier-location',
                        ['label' => 'Produk ' . $tier]
                    ));
                }
            }

            if ($service === 'layanan') {
                if ($animal !== '' && $location !== '') {
                    seo_landing_add_record($records, seo_landing_make_record(
                        'layanan',
                        seo_landing_slug([$animal, $location]),
                        'Layanan ' . $animal . ' di ' . $location,
                        'Landing layanan ' . strtolower($animal) . ' untuk area ' . $location . ' dengan produk atau layanan terkait yang tersedia di katalog.',
                        ['service' => 'layanan', 'animal_type' => $animal, 'location' => $location],
                        'layanan-location',
                        ['label' => 'Layanan ' . $animal]
                    ));
                }
            }

            if (in_array($animal, ['Paket', 'Layanan'], true) && $location !== '') {
                seo_landing_add_record($records, seo_landing_make_record(
                    'layanan',
                    seo_landing_slug([$animal, $location]),
                    'Layanan ' . $animal . ' di ' . $location,
                    'Landing layanan ' . strtolower($animal) . ' area ' . $location . ' yang memakai data produk/layanan terkait dari katalog agar halaman tetap punya produk relevan.',
                    ['animal_type' => $animal, 'location' => $location],
                    'layanan-related-animal-location',
                    ['label' => 'Layanan ' . $animal, 'priority' => '0.70']
                ));
            }

            if ($animal !== '') {
                seo_landing_add_record($records, seo_landing_make_record(
                    'katalog',
                    seo_landing_slug([$animal]),
                    'Katalog ' . $animal . ' Produk & Layanan',
                    'Kumpulan pilihan ' . strtolower($animal) . ' dari katalog produk dan layanan yang bisa difilter berdasarkan kelas, lokasi, dan stok.',
                    ['animal_type' => $animal],
                    'catalog-animal',
                    ['priority' => '0.78']
                ));
            }

            if ($animal !== '' && $tier !== '') {
                seo_landing_add_record($records, seo_landing_make_record(
                    'katalog',
                    seo_landing_slug([$animal, $tier]),
                    'Katalog ' . $animal . ' ' . $tier,
                    'Pilihan ' . strtolower($animal) . ' kelas ' . strtolower($tier) . ' dari katalog produk dan layanan, siap dibandingkan sebelum konsultasi.',
                    ['animal_type' => $animal, 'tier' => $tier],
                    'catalog-animal-tier',
                    ['priority' => '0.76']
                ));
            }

            if ($animal !== '' && $location !== '') {
                seo_landing_add_record($records, seo_landing_make_record(
                    'katalog',
                    seo_landing_slug([$animal, $location]),
                    'Katalog ' . $animal . ' di ' . $location,
                    'Pilihan ' . strtolower($animal) . ' untuk area ' . $location . ' dari katalog produk dan layanan, lengkap dengan produk terkait.',
                    ['animal_type' => $animal, 'location' => $location],
                    'catalog-animal-location',
                    ['priority' => '0.75']
                ));
            }

            if ($animal !== '' && $tier !== '' && $location !== '') {
                seo_landing_add_record($records, seo_landing_make_record(
                    'katalog',
                    seo_landing_slug([$animal, $tier, $location]),
                    'Katalog ' . $animal . ' ' . $tier . ' di ' . $location,
                    'Katalog bersih untuk ' . strtolower($animal) . ' kelas ' . strtolower($tier) . ' area ' . $location . ' dengan produk valid yang sudah tersedia.',
                    ['animal_type' => $animal, 'tier' => $tier, 'location' => $location],
                    'catalog-animal-tier-location',
                    ['priority' => '0.77']
                ));
            }
        }

        foreach ($locations as $location) {
            seo_landing_add_record($records, seo_landing_make_record(
                'lokasi',
                seo_landing_slug(['area layanan', $location]),
                'Area Layanan Produk Pilihan di ' . $location,
                'Informasi katalog produk dan layanan yang terhubung dengan area ' . $location . ', termasuk pilihan produk dan jalur konsultasi admin.',
                ['location' => $location],
                'location',
                ['label' => 'Area Layanan', 'priority' => '0.73']
            ));
        }

        $records = array_values($records);
        usort($records, static function (array $a, array $b): int {
            return [(string)($a['prefix'] ?? ''), (string)($a['slug'] ?? '')] <=> [(string)($b['prefix'] ?? ''), (string)($b['slug'] ?? '')];
        });

        return $records;
    }
}

if (!function_exists('seo_landing_registry')) {
    function seo_landing_registry(bool $includeProducts = true): array
    {
        $settings = seo_landing_settings_read();
        $records = [];

        foreach (seo_landing_generated_records() as $record) {
            $key = (string)($record['key'] ?? '');
            $override = is_array($settings[$key] ?? null) ? $settings[$key] : [];
            $enabled = array_key_exists('enabled', $override) ? (bool)$override['enabled'] : (bool)($record['enabled'] ?? true);

            $record['enabled'] = $enabled;
            $record['override'] = $override;
            $record['indexable'] = $enabled && (int)($record['product_count'] ?? 0) > 0;
            $record['robots'] = $record['indexable'] ? 'index, follow' : 'noindex, follow';
            $record['canonical'] = url((string)($record['path'] ?? ''));

            if (!$includeProducts) {
                unset($record['products']);
            }

            $records[] = $record;
        }

        return $records;
    }
}

if (!function_exists('seo_landing_find')) {
    function seo_landing_find(string $prefix, string $slug, bool $includeProducts = true): ?array
    {
        $prefix = slugify($prefix);
        $slug = slugify($slug);
        $key = seo_landing_record_key($prefix, $slug);

        foreach (seo_landing_registry($includeProducts) as $record) {
            if ((string)($record['key'] ?? '') === $key) {
                return $record;
            }
        }

        return null;
    }
}

if (!function_exists('seo_landing_public_records')) {
    function seo_landing_public_records(bool $includeProducts = false): array
    {
        return array_values(array_filter(
            seo_landing_registry($includeProducts),
            static fn(array $record): bool => (bool)($record['indexable'] ?? false)
        ));
    }
}

if (!function_exists('seo_landing_summary')) {
    function seo_landing_summary(): array
    {
        $records = seo_landing_registry(false);
        $counts = [
            'total' => count($records),
            'enabled' => 0,
            'disabled' => 0,
            'indexable' => 0,
            'thin' => 0,
            'produk' => 0,
            'layanan' => 0,
            'lokasi' => 0,
            'katalog' => 0,
        ];

        foreach ($records as $record) {
            $prefix = (string)($record['prefix'] ?? '');
            if (isset($counts[$prefix])) {
                $counts[$prefix]++;
            }
            if ((bool)($record['enabled'] ?? false)) {
                $counts['enabled']++;
            } else {
                $counts['disabled']++;
            }
            if ((bool)($record['indexable'] ?? false)) {
                $counts['indexable']++;
            }
            if ((int)($record['product_count'] ?? 0) <= 0) {
                $counts['thin']++;
            }
        }

        return [
            'counts' => $counts,
            'items' => $records,
            'storage_path' => seo_landing_storage_path(),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('seo_landing_set_enabled')) {
    function seo_landing_set_enabled(string $key, bool $enabled): bool
    {
        $settings = seo_landing_settings_read();
        $settings[$key] = array_merge(
            is_array($settings[$key] ?? null) ? $settings[$key] : [],
            [
                'enabled' => $enabled,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        $ok = seo_landing_settings_write($settings);
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record($enabled ? 'enable' : 'disable', 'seo_landing', null, $enabled ? 'SEO landing diaktifkan.' : 'SEO landing dinonaktifkan.', ['key' => $key]);
        }

        return $ok;
    }
}

if (!function_exists('seo_landing_match_filter_slug')) {
    function seo_landing_match_filter_slug(array $filters): ?array
    {
        $q = trim((string)($filters['q'] ?? ''));
        $stockStatus = trim((string)($filters['stock_status'] ?? ''));
        $page = (int)($_GET['page'] ?? 1);
        if ($q !== '' || $stockStatus !== '' || $page > 1) {
            return null;
        }

        $category = seo_landing_clean_label((string)($filters['category'] ?? ''));
        $animal = seo_landing_title_case((string)($filters['animal_type'] ?? ''));
        $tier = seo_landing_title_case((string)($filters['tier'] ?? ''));
        $location = seo_landing_title_case((string)($filters['location'] ?? ''));

        $candidates = [];

        if ($category !== '' && seo_landing_value_contains($category, 'produk')) {
            if ($animal !== '' && $tier !== '' && $location !== '') {
                $candidates[] = ['produk', seo_landing_slug([$animal, $tier, $location])];
            }
            if ($animal !== '' && $location !== '') {
                $candidates[] = ['produk', seo_landing_slug([$animal, $location])];
            }
        }

        if ($category !== '' && seo_landing_value_contains($category, 'layanan')) {
            if ($animal !== '' && $location !== '') {
                $candidates[] = ['layanan', seo_landing_slug([$animal, $location])];
            }
        }

        if ($animal !== '' && $tier !== '' && $location !== '') {
            $candidates[] = ['katalog', seo_landing_slug([$animal, $tier, $location])];
        }
        if ($animal !== '' && $location !== '') {
            $candidates[] = ['katalog', seo_landing_slug([$animal, $location])];
        }
        if ($animal !== '' && $tier !== '') {
            $candidates[] = ['katalog', seo_landing_slug([$animal, $tier])];
        }
        if ($animal !== '') {
            $candidates[] = ['katalog', seo_landing_slug([$animal])];
        }
        if ($location !== '' && $animal === '' && $tier === '' && $category === '') {
            $candidates[] = ['lokasi', seo_landing_slug(['area layanan', $location])];
        }

        foreach ($candidates as $candidate) {
            $record = seo_landing_find((string)$candidate[0], (string)$candidate[1], false);
            if ($record && (bool)($record['indexable'] ?? false)) {
                return $record;
            }
        }

        return null;
    }
}

if (!function_exists('seo_landing_canonical_for_filters')) {
    function seo_landing_canonical_for_filters(array $filters): ?string
    {
        $record = seo_landing_match_filter_slug($filters);
        return $record ? (string)($record['canonical'] ?? $record['url'] ?? '') : null;
    }
}

if (!function_exists('seo_landing_faq')) {
    function seo_landing_faq(array $landing): array
    {
        $title = (string)($landing['title'] ?? 'halaman ini');
        $count = (int)($landing['product_count'] ?? 0);

        return [
            [
                'question' => 'Apakah produk di halaman ' . $title . ' benar-benar tersedia?',
                'answer' => 'Halaman ini hanya dibuat ketika ada produk terkait di katalog. Untuk status stok paling baru, silakan konfirmasi ke admin melalui WhatsApp.',
            ],
            [
                'question' => 'Berapa jumlah produk terkait di halaman ini?',
                'answer' => 'Saat ini ada sekitar ' . $count . ' produk terkait yang bisa dibandingkan dari katalog.',
            ],
            [
                'question' => 'Apakah bisa konsultasi sebelum memilih produk/layanan?',
                'answer' => 'Bisa. Gunakan tombol WhatsApp pada produk atau tombol konsultasi agar admin membantu memilih sesuai kebutuhan, lokasi, dan budget.',
            ],
        ];
    }
}

if (!function_exists('seo_landing_internal_links')) {
    function seo_landing_internal_links(array $landing, int $limit = 8): array
    {
        $links = [
            ['label' => 'Katalog Utama', 'url' => url('katalog'), 'text' => 'Lihat semua pilihan produk dan layanan.'],
            ['label' => 'Paket Produk', 'url' => url('paket-produk'), 'text' => 'Panduan pilihan paket produk.'],
            ['label' => 'Paket Layanan', 'url' => url('paket-layanan'), 'text' => 'Layanan layanan siap bantu keluarga.'],
            ['label' => 'Area Layanan', 'url' => url('kontak'), 'text' => 'Cek area area layanan dan layanan.'],
        ];

        foreach (seo_landing_public_records(false) as $record) {
            if ((string)($record['key'] ?? '') === (string)($landing['key'] ?? '')) {
                continue;
            }
            $links[] = [
                'label' => (string)($record['title'] ?? ''),
                'url' => (string)($record['url'] ?? ''),
                'text' => (int)($record['product_count'] ?? 0) . ' produk terkait.',
            ];
            if (count($links) >= $limit) {
                break;
            }
        }

        return array_slice($links, 0, $limit);
    }
}
