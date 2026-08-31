<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| TRUST & CONVERSION BLOCK BUILDER
|--------------------------------------------------------------------------
| Admin-managed trust sections for homepage and campaign pages.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('trust_conversion_storage_file')) {
    function trust_conversion_storage_file(): string
    {
        return STORAGE_PATH . '/trust-conversion-blocks.json';
    }
}

if (!function_exists('trust_conversion_block_types')) {
    function trust_conversion_block_types(): array
    {
        return [
            'benefits' => [
                'label' => 'Benefit',
                'description' => 'Poin alasan kenapa calon customer perlu memilih bisnis ini.',
                'item_title' => 'Judul benefit',
                'item_text' => 'Keterangan benefit',
                'item_meta' => 'Catatan kecil',
            ],
            'testimonials' => [
                'label' => 'Testimoni',
                'description' => 'Bukti sosial dari pelanggan, client, peserta, atau pengguna layanan.',
                'item_title' => 'Nama / profil',
                'item_text' => 'Isi testimoni',
                'item_meta' => 'Keterangan singkat',
            ],
            'faq' => [
                'label' => 'FAQ',
                'description' => 'Pertanyaan yang sering ditanyakan sebelum calon customer membeli atau menghubungi.',
                'item_title' => 'Pertanyaan',
                'item_text' => 'Jawaban',
                'item_meta' => 'Kategori',
            ],
            'guarantee' => [
                'label' => 'Garansi / Komitmen',
                'description' => 'Jelaskan komitmen layanan, garansi, support, atau rasa aman untuk pembeli.',
                'item_title' => 'Judul komitmen',
                'item_text' => 'Penjelasan',
                'item_meta' => 'Syarat singkat',
            ],
            'trust_badges' => [
                'label' => 'Badge Trust',
                'description' => 'Tampilkan badge seperti aman, cepat, resmi, berpengalaman, atau support.',
                'item_title' => 'Badge',
                'item_text' => 'Keterangan',
                'item_meta' => 'Angka / label kecil',
            ],
            'before_after' => [
                'label' => 'Before - After',
                'description' => 'Bandingkan kondisi sebelum dan sesudah menggunakan produk, jasa, atau solusi.',
                'item_title' => 'Area perubahan',
                'item_text' => 'Sebelum',
                'item_meta' => 'Sesudah',
            ],
            'cta' => [
                'label' => 'CTA Block',
                'description' => 'Ajakan aksi khusus untuk WhatsApp, form, checkout, konsultasi, atau katalog.',
                'item_title' => 'Highlight',
                'item_text' => 'Keterangan',
                'item_meta' => 'Catatan',
            ],
        ];
    }
}

if (!function_exists('trust_conversion_default_blocks')) {
    function trust_conversion_default_blocks(): array
    {
        return [
            [
                'id' => 'benefit-utama',
                'type' => 'benefits',
                'enabled' => true,
                'location' => 'homepage',
                'order' => 10,
                'badge' => 'Benefit Utama',
                'title' => 'Alasan Customer Lebih Nyaman Memilih Kami',
                'description' => 'Tampilkan poin keunggulan yang mudah dipahami pengunjung sebelum mereka lanjut melihat katalog, mengisi form, atau menghubungi WhatsApp.',
                'cta_label' => 'Lihat Katalog',
                'cta_url' => '/katalog',
                'items' => [
                    ['title' => 'Mudah Dipahami', 'text' => 'Informasi produk, layanan, harga, dan alur pemesanan dibuat jelas.', 'meta' => 'Cocok untuk pembeli baru'],
                    ['title' => 'Respons Cepat', 'text' => 'Pengunjung bisa langsung diarahkan ke WhatsApp atau form sesuai kebutuhan.', 'meta' => 'Lead lebih mudah ditangani'],
                    ['title' => 'Siap SEO', 'text' => 'Konten bisa diarahkan untuk pencarian lokal, produk, jasa, dan edukasi.', 'meta' => 'Growth jangka panjang'],
                ],
            ],
            [
                'id' => 'testimoni-pelanggan',
                'type' => 'testimonials',
                'enabled' => true,
                'location' => 'homepage',
                'order' => 20,
                'badge' => 'Testimoni',
                'title' => 'Apa Kata Customer',
                'description' => 'Gunakan bagian ini untuk menampilkan pengalaman pelanggan agar pengunjung baru lebih percaya.',
                'cta_label' => 'Konsultasi Sekarang',
                'cta_url' => '/kontak',
                'items' => [
                    ['title' => 'Pelanggan A', 'text' => 'Informasinya jelas, prosesnya mudah, dan responsnya cepat.', 'meta' => 'Customer'],
                    ['title' => 'Pelanggan B', 'text' => 'Saya jadi lebih yakin karena detail layanan dan tahap pemesanan mudah diikuti.', 'meta' => 'Client'],
                    ['title' => 'Pelanggan C', 'text' => 'Website-nya membantu saya memilih paket yang paling sesuai kebutuhan.', 'meta' => 'Buyer'],
                ],
            ],
            [
                'id' => 'faq-pembeli',
                'type' => 'faq',
                'enabled' => true,
                'location' => 'homepage',
                'order' => 30,
                'badge' => 'FAQ',
                'title' => 'Pertanyaan yang Sering Ditanyakan',
                'description' => 'Jawab keberatan umum agar calon customer tidak bingung sebelum mengambil aksi.',
                'cta_label' => 'Tanya via WhatsApp',
                'cta_url' => '/kontak',
                'items' => [
                    ['title' => 'Bagaimana cara mulai pesan atau konsultasi?', 'text' => 'Klik tombol WhatsApp, isi form, atau pilih katalog yang paling sesuai dengan kebutuhan Anda.', 'meta' => 'Pemesanan'],
                    ['title' => 'Apakah bisa tanya dulu sebelum membeli?', 'text' => 'Bisa. Kami sarankan konsultasi singkat agar rekomendasi produk atau layanan lebih tepat.', 'meta' => 'Konsultasi'],
                    ['title' => 'Apakah informasi bisa disesuaikan?', 'text' => 'Bisa. Setiap detail produk, layanan, form, CTA, dan halaman dapat diatur dari dashboard admin.', 'meta' => 'Fleksibel'],
                ],
            ],
            [
                'id' => 'garansi-komitmen',
                'type' => 'guarantee',
                'enabled' => false,
                'location' => 'homepage',
                'order' => 40,
                'badge' => 'Komitmen',
                'title' => 'Komitmen Layanan yang Membuat Pembeli Lebih Tenang',
                'description' => 'Tuliskan garansi, support, atau standar layanan sesuai bisnis masing-masing.',
                'cta_label' => 'Pelajari Layanan',
                'cta_url' => '/layanan',
                'items' => [
                    ['title' => 'Informasi Transparan', 'text' => 'Detail layanan dan alur order dijelaskan sejak awal.', 'meta' => 'Jelas'],
                    ['title' => 'Support Setelah Order', 'text' => 'Customer tetap bisa bertanya setelah melakukan pemesanan.', 'meta' => 'Dibantu'],
                    ['title' => 'Arahan Sesuai Kebutuhan', 'text' => 'Rekomendasi disesuaikan dengan kebutuhan customer, bukan asal jual.', 'meta' => 'Relevan'],
                ],
            ],
            [
                'id' => 'badge-kepercayaan',
                'type' => 'trust_badges',
                'enabled' => false,
                'location' => 'homepage',
                'order' => 50,
                'badge' => 'Trust Badge',
                'title' => 'Sinyal Kepercayaan untuk Pengunjung Baru',
                'description' => 'Tambahkan badge singkat agar brand terasa lebih kredibel dan siap melayani.',
                'cta_label' => 'Hubungi Kami',
                'cta_url' => '/kontak',
                'items' => [
                    ['title' => 'Resmi', 'text' => 'Website dan kontak bisnis aktif.', 'meta' => 'Verified'],
                    ['title' => 'Fast Response', 'text' => 'Tim siap membantu via WhatsApp atau form.', 'meta' => 'Cepat'],
                    ['title' => 'SEO Ready', 'text' => 'Struktur website siap dikembangkan untuk pencarian.', 'meta' => 'Growth'],
                    ['title' => 'Checkout Ready', 'text' => 'Alur order dan pembayaran dapat diatur sesuai bisnis.', 'meta' => 'Order'],
                ],
            ],
            [
                'id' => 'before-after-solusi',
                'type' => 'before_after',
                'enabled' => false,
                'location' => 'homepage',
                'order' => 60,
                'badge' => 'Before - After',
                'title' => 'Perubahan yang Bisa Dirasakan Customer',
                'description' => 'Gunakan format sebelum-sesudah untuk memperjelas value produk atau layanan.',
                'cta_label' => 'Mulai Konsultasi',
                'cta_url' => '/kontak',
                'items' => [
                    ['title' => 'Sebelum order', 'text' => 'Bingung memilih produk atau paket yang sesuai.', 'meta' => 'Mendapat rekomendasi yang lebih jelas.'],
                    ['title' => 'Sebelum konsultasi', 'text' => 'Tidak tahu alur, estimasi, dan pilihan yang tersedia.', 'meta' => 'Paham langkah berikutnya setelah membaca halaman.'],
                    ['title' => 'Sebelum melihat katalog', 'text' => 'Informasi tercecer dan sulit dibandingkan.', 'meta' => 'Katalog lebih rapi dan mudah dipilih.'],
                ],
            ],
            [
                'id' => 'cta-konsultasi',
                'type' => 'cta',
                'enabled' => true,
                'location' => 'homepage',
                'order' => 70,
                'badge' => 'Siap Dibantu',
                'title' => 'Masih Bingung Memilih? Ceritakan Kebutuhan Anda Dulu',
                'description' => 'Arahkan pengunjung yang belum siap membeli agar tetap menjadi lead melalui WhatsApp atau form konsultasi.',
                'cta_label' => 'Konsultasi Sekarang',
                'cta_url' => '/kontak',
                'items' => [
                    ['title' => 'Konsultasi dulu', 'text' => 'Ceritakan kebutuhan, budget, atau target yang ingin dicapai.', 'meta' => 'Gratis tanya awal'],
                    ['title' => 'Dapat arahan', 'text' => 'Tim/admin bisa mengarahkan pengunjung ke produk, layanan, atau form yang tepat.', 'meta' => 'Lebih cepat closing'],
                ],
            ],
        ];
    }
}

if (!function_exists('trust_conversion_default_settings')) {
    function trust_conversion_default_settings(): array
    {
        return [
            'enabled' => true,
            'homepage_enabled' => true,
            'insert_position' => 'before_lead_form',
            'blocks' => trust_conversion_default_blocks(),
            'updated_at' => '',
        ];
    }
}

if (!function_exists('trust_conversion_array_is_list')) {
    function trust_conversion_array_is_list(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}

if (!function_exists('trust_conversion_deep_merge')) {
    function trust_conversion_deep_merge(array $defaults, array $settings): array
    {
        foreach ($settings as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key]) && !trust_conversion_array_is_list($value)) {
                $defaults[$key] = trust_conversion_deep_merge($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}

if (!function_exists('trust_conversion_bool')) {
    function trust_conversion_bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int)$value === 1;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));
            if (in_array($value, ['1', 'true', 'yes', 'on', 'aktif', 'enabled'], true)) {
                return true;
            }
            if (in_array($value, ['0', 'false', 'no', 'off', 'nonaktif', 'disabled'], true)) {
                return false;
            }
        }

        return $default;
    }
}

if (!function_exists('trust_conversion_clean_text')) {
    function trust_conversion_clean_text(mixed $value, int $limit = 180): string
    {
        $text = trim(strip_tags((string)$value));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        if ($limit > 0 && function_exists('mb_substr')) {
            $text = mb_substr($text, 0, $limit);
        } elseif ($limit > 0) {
            $text = substr($text, 0, $limit);
        }
        return trim($text);
    }
}

if (!function_exists('trust_conversion_clean_url')) {
    function trust_conversion_clean_url(mixed $value, string $fallback = '/kontak'): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return $fallback;
        }

        if (str_starts_with($value, '#')) {
            return '#' . slugify(substr($value, 1));
        }

        if (str_starts_with($value, '/')) {
            return '/' . ltrim($value, '/');
        }

        if (preg_match('#^https?://#i', $value)) {
            return filter_var($value, FILTER_VALIDATE_URL) ? $value : $fallback;
        }

        return '/' . ltrim($value, '/');
    }
}

if (!function_exists('trust_conversion_url_to_href')) {
    function trust_conversion_url_to_href(string $value): string
    {
        $value = trust_conversion_clean_url($value, '/kontak');
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://') || str_starts_with($value, '#')) {
            return $value;
        }

        return url(ltrim($value, '/'));
    }
}

if (!function_exists('trust_conversion_normalize_item')) {
    function trust_conversion_normalize_item(array $item): array
    {
        return [
            'title' => trust_conversion_clean_text($item['title'] ?? '', 90),
            'text' => trust_conversion_clean_text($item['text'] ?? '', 360),
            'meta' => trust_conversion_clean_text($item['meta'] ?? '', 120),
        ];
    }
}

if (!function_exists('trust_conversion_normalize_block')) {
    function trust_conversion_normalize_block(array $block, int $fallbackOrder = 10): array
    {
        $types = trust_conversion_block_types();
        $type = (string)($block['type'] ?? 'benefits');
        if (!isset($types[$type])) {
            $type = 'benefits';
        }

        $id = slugify((string)($block['id'] ?? $type . '-' . $fallbackOrder));
        if ($id === '') {
            $id = $type . '-' . $fallbackOrder;
        }

        $items = [];
        foreach ((array)($block['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $clean = trust_conversion_normalize_item($item);
            if ($clean['title'] === '' && $clean['text'] === '' && $clean['meta'] === '') {
                continue;
            }
            $items[] = $clean;
        }

        $defaults = trust_conversion_default_blocks();
        $fallback = [];
        foreach ($defaults as $defaultBlock) {
            if (($defaultBlock['type'] ?? '') === $type) {
                $fallback = $defaultBlock;
                break;
            }
        }

        return [
            'id' => $id,
            'type' => $type,
            'enabled' => trust_conversion_bool($block['enabled'] ?? false),
            'location' => trust_conversion_clean_text($block['location'] ?? 'homepage', 40) ?: 'homepage',
            'order' => max(1, min(999, (int)($block['order'] ?? $fallbackOrder))),
            'badge' => trust_conversion_clean_text($block['badge'] ?? ($fallback['badge'] ?? $types[$type]['label']), 80) ?: $types[$type]['label'],
            'title' => trust_conversion_clean_text($block['title'] ?? ($fallback['title'] ?? $types[$type]['label']), 160) ?: $types[$type]['label'],
            'description' => trust_conversion_clean_text($block['description'] ?? ($fallback['description'] ?? $types[$type]['description']), 360),
            'cta_label' => trust_conversion_clean_text($block['cta_label'] ?? ($fallback['cta_label'] ?? 'Hubungi Kami'), 50),
            'cta_url' => trust_conversion_clean_url($block['cta_url'] ?? ($fallback['cta_url'] ?? '/kontak'), '/kontak'),
            'items' => array_slice($items ?: (array)($fallback['items'] ?? []), 0, 6),
        ];
    }
}

if (!function_exists('trust_conversion_normalize_settings')) {
    function trust_conversion_normalize_settings(array $settings): array
    {
        $defaults = trust_conversion_default_settings();
        $settings = trust_conversion_deep_merge($defaults, $settings);
        $positions = ['after_hero', 'after_intro', 'before_lead_form', 'after_content'];
        $position = (string)($settings['insert_position'] ?? 'before_lead_form');
        if (!in_array($position, $positions, true)) {
            $position = 'before_lead_form';
        }

        $blocks = [];
        foreach ((array)($settings['blocks'] ?? []) as $index => $block) {
            if (!is_array($block)) {
                continue;
            }
            $blocks[] = trust_conversion_normalize_block($block, ((int)$index + 1) * 10);
        }

        if (!$blocks) {
            foreach (trust_conversion_default_blocks() as $index => $block) {
                $blocks[] = trust_conversion_normalize_block($block, ((int)$index + 1) * 10);
            }
        }

        usort($blocks, static fn(array $a, array $b): int => ((int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0)) ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')));

        return [
            'enabled' => trust_conversion_bool($settings['enabled'] ?? true, true),
            'homepage_enabled' => trust_conversion_bool($settings['homepage_enabled'] ?? true, true),
            'insert_position' => $position,
            'blocks' => $blocks,
            'updated_at' => trust_conversion_clean_text($settings['updated_at'] ?? '', 80),
        ];
    }
}

if (!function_exists('trust_conversion_settings')) {
    function trust_conversion_settings(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $file = trust_conversion_storage_file();
        if (!is_file($file)) {
            $cached = trust_conversion_normalize_settings(trust_conversion_default_settings());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = trust_conversion_normalize_settings(trust_conversion_default_settings());
            return $cached;
        }

        $cached = trust_conversion_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('trust_conversion_settings_from_post')) {
    function trust_conversion_settings_from_post(array $input): array
    {
        $current = trust_conversion_settings();
        $blocks = [];
        $ids = (array)($input['block_id'] ?? []);
        $types = (array)($input['block_type'] ?? []);
        $enabled = (array)($input['block_enabled'] ?? []);
        $orders = (array)($input['block_order'] ?? []);
        $badges = (array)($input['block_badge'] ?? []);
        $titles = (array)($input['block_title'] ?? []);
        $descriptions = (array)($input['block_description'] ?? []);
        $ctaLabels = (array)($input['block_cta_label'] ?? []);
        $ctaUrls = (array)($input['block_cta_url'] ?? []);
        $itemTitles = (array)($input['item_title'] ?? []);
        $itemTexts = (array)($input['item_text'] ?? []);
        $itemMetas = (array)($input['item_meta'] ?? []);

        $count = max(count($ids), count($types));
        for ($i = 0; $i < $count; $i++) {
            $items = [];
            for ($j = 0; $j < 6; $j++) {
                $items[] = [
                    'title' => $itemTitles[$i][$j] ?? '',
                    'text' => $itemTexts[$i][$j] ?? '',
                    'meta' => $itemMetas[$i][$j] ?? '',
                ];
            }

            $blocks[] = trust_conversion_normalize_block([
                'id' => $ids[$i] ?? '',
                'type' => $types[$i] ?? 'benefits',
                'enabled' => isset($enabled[$i]),
                'location' => 'homepage',
                'order' => $orders[$i] ?? (($i + 1) * 10),
                'badge' => $badges[$i] ?? '',
                'title' => $titles[$i] ?? '',
                'description' => $descriptions[$i] ?? '',
                'cta_label' => $ctaLabels[$i] ?? '',
                'cta_url' => $ctaUrls[$i] ?? '/kontak',
                'items' => $items,
            ], ($i + 1) * 10);
        }

        return trust_conversion_normalize_settings([
            'enabled' => isset($input['enabled']),
            'homepage_enabled' => isset($input['homepage_enabled']),
            'insert_position' => trust_conversion_clean_text($input['insert_position'] ?? ($current['insert_position'] ?? 'before_lead_form'), 40),
            'blocks' => $blocks ?: (array)($current['blocks'] ?? trust_conversion_default_blocks()),
            'updated_at' => date(DATE_ATOM),
        ]);
    }
}

if (!function_exists('trust_conversion_save_settings')) {
    function trust_conversion_save_settings(array $settings): array
    {
        $settings = trust_conversion_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            throw new RuntimeException('Folder penyimpanan belum bisa dibuat.');
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(trust_conversion_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Pengaturan trust & conversion gagal disimpan. Cek permission folder storage.');
        }

        @chmod(trust_conversion_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'trust-conversion', null, 'Mengubah trust & conversion block builder.');
        }

        return $settings;
    }
}

if (!function_exists('trust_conversion_reset_settings')) {
    function trust_conversion_reset_settings(): void
    {
        if (is_file(trust_conversion_storage_file())) {
            @unlink(trust_conversion_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'trust-conversion', null, 'Mengembalikan trust & conversion block builder ke bawaan.');
        }
    }
}

if (!function_exists('trust_conversion_enabled_blocks')) {
    function trust_conversion_enabled_blocks(?array $settings = null): array
    {
        $settings = $settings ?? trust_conversion_settings();
        if (empty($settings['enabled'])) {
            return [];
        }

        $blocks = [];
        foreach ((array)($settings['blocks'] ?? []) as $block) {
            if (is_array($block) && !empty($block['enabled'])) {
                $blocks[] = $block;
            }
        }

        usort($blocks, static fn(array $a, array $b): int => (int)($a['order'] ?? 0) <=> (int)($b['order'] ?? 0));
        return $blocks;
    }
}

if (!function_exists('trust_conversion_summary')) {
    function trust_conversion_summary(?array $settings = null): array
    {
        $settings = $settings ?? trust_conversion_settings();
        $types = trust_conversion_block_types();
        $enabledBlocks = trust_conversion_enabled_blocks($settings);
        $byType = array_fill_keys(array_keys($types), 0);
        $totalItems = 0;

        foreach ($enabledBlocks as $block) {
            $type = (string)($block['type'] ?? 'benefits');
            if (isset($byType[$type])) {
                $byType[$type]++;
            }
            $totalItems += count((array)($block['items'] ?? []));
        }

        return [
            'enabled' => !empty($settings['enabled']),
            'homepage_enabled' => !empty($settings['homepage_enabled']),
            'total_blocks' => count((array)($settings['blocks'] ?? [])),
            'enabled_blocks' => count($enabledBlocks),
            'total_items' => $totalItems,
            'by_type' => $byType,
            'insert_position' => (string)($settings['insert_position'] ?? 'before_lead_form'),
            'updated_at' => (string)($settings['updated_at'] ?? ''),
        ];
    }
}

if (!function_exists('trust_conversion_render_cta')) {
    function trust_conversion_render_cta(array $block, string $source): void
    {
        $label = trim((string)($block['cta_label'] ?? ''));
        $url = trim((string)($block['cta_url'] ?? ''));
        if ($label === '' || $url === '') {
            return;
        }

        $cleanUrl = trust_conversion_clean_url($url, '/kontak');
        $href = trust_conversion_url_to_href($cleanUrl);
        $external = false;
        if (preg_match('#^https?://#i', $cleanUrl)) {
            $targetHost = strtolower((string)(parse_url($cleanUrl, PHP_URL_HOST) ?? ''));
            $siteHost = strtolower((string)(parse_url((string)SITE_URL, PHP_URL_HOST) ?? ''));
            $external = $targetHost !== '' && $siteHost !== '' && $targetHost !== $siteHost;
        }
        $attrs = $external ? ' target="_blank" rel="noopener nofollow"' : '';
        $tracking = function_exists('conversion_link_attrs') ? conversion_link_attrs([
            'source' => $source,
            'type' => $external ? 'external' : 'internal',
            'category' => 'trust-conversion',
            'label' => $label,
            'intent' => 'trust-conversion-cta',
        ]) : '';

        echo '<a class="trust-conversion-btn" href="' . esc($href) . '"' . $attrs . ' ' . $tracking . '>' . esc($label) . '</a>';
    }
}

if (!function_exists('trust_conversion_render_block')) {
    function trust_conversion_render_block(array $block, int $index = 0): void
    {
        $type = (string)($block['type'] ?? 'benefits');
        $items = array_values(array_filter((array)($block['items'] ?? []), static fn($item): bool => is_array($item)));
        $sectionClass = 'trust-conversion-section trust-conversion-section--' . $type . (($index % 2) ? ' trust-conversion-section--alt' : '');
        $source = 'trust-conversion-' . $type;
        ?>
<section class="<?= esc($sectionClass); ?>" id="<?= esc((string)($block['id'] ?? ('trust-block-' . $index))); ?>">
    <div class="container">
        <div class="trust-conversion-head">
            <span class="section-eyebrow trust-conversion-eyebrow"><?= esc((string)($block['badge'] ?? 'Trust')); ?></span>
            <h2 class="title"><?= esc((string)($block['title'] ?? '')); ?></h2>
            <?php if (trim((string)($block['description'] ?? '')) !== ''): ?>
                <p class="center"><?= esc((string)($block['description'] ?? '')); ?></p>
            <?php endif; ?>
        </div>

        <?php if ($type === 'faq'): ?>
            <div class="trust-conversion-faq">
                <?php foreach ($items as $item): ?>
                    <details class="trust-conversion-faq-item">
                        <summary><?= esc((string)($item['title'] ?? 'Pertanyaan')); ?></summary>
                        <p><?= esc((string)($item['text'] ?? '')); ?></p>
                        <?php if (trim((string)($item['meta'] ?? '')) !== ''): ?><small><?= esc((string)$item['meta']); ?></small><?php endif; ?>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php elseif ($type === 'before_after'): ?>
            <div class="trust-conversion-before-after">
                <?php foreach ($items as $item): ?>
                    <article class="trust-conversion-card trust-conversion-compare-card">
                        <strong><?= esc((string)($item['title'] ?? 'Perubahan')); ?></strong>
                        <div><span>Sebelum</span><p><?= esc((string)($item['text'] ?? '')); ?></p></div>
                        <div><span>Sesudah</span><p><?= esc((string)($item['meta'] ?? '')); ?></p></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php elseif ($type === 'cta'): ?>
            <div class="trust-conversion-cta-card">
                <div class="trust-conversion-cta-points">
                    <?php foreach ($items as $item): ?>
                        <div><strong><?= esc((string)($item['title'] ?? 'Highlight')); ?></strong><p><?= esc((string)($item['text'] ?? '')); ?></p><?php if (trim((string)($item['meta'] ?? '')) !== ''): ?><small><?= esc((string)$item['meta']); ?></small><?php endif; ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="trust-conversion-cta-action">
                    <?php trust_conversion_render_cta($block, $source); ?>
                </div>
            </div>
        <?php else: ?>
            <div class="trust-conversion-grid trust-conversion-grid--<?= esc($type); ?>">
                <?php foreach ($items as $item): ?>
                    <article class="trust-conversion-card">
                        <?php if (trim((string)($item['meta'] ?? '')) !== ''): ?><span><?= esc((string)$item['meta']); ?></span><?php endif; ?>
                        <h3><?= esc((string)($item['title'] ?? 'Info')); ?></h3>
                        <p><?= esc((string)($item['text'] ?? '')); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($type !== 'cta' && trim((string)($block['cta_label'] ?? '')) !== ''): ?>
            <div class="trust-conversion-actions"><?php trust_conversion_render_cta($block, $source); ?></div>
        <?php endif; ?>
    </div>
</section>
        <?php
    }
}

if (!function_exists('trust_conversion_render_homepage_blocks')) {
    function trust_conversion_render_homepage_blocks(string $position = ''): void
    {
        $settings = trust_conversion_settings();
        if (empty($settings['enabled']) || empty($settings['homepage_enabled'])) {
            return;
        }

        $selectedPosition = (string)($settings['insert_position'] ?? 'before_lead_form');
        if ($position !== '' && $position !== $selectedPosition) {
            return;
        }

        $blocks = trust_conversion_enabled_blocks($settings);
        if (!$blocks) {
            return;
        }

        echo '<div class="trust-conversion-builder" data-trust-conversion-position="' . esc($selectedPosition) . '">';
        foreach ($blocks as $index => $block) {
            trust_conversion_render_block($block, (int)$index);
        }
        echo '</div>';
    }
}
