<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CTA PLACEMENT & WINNER DEPLOYMENT ASSISTANT
|--------------------------------------------------------------------------
| Turns Offer & CTA Testing Lab winners into concrete deployment plans.
| Safe default: guided placement tracking. Selected homepage areas can be
| pushed with one click through existing homepage settings helpers.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('cta_placement_storage_file')) {
    function cta_placement_storage_file(): string
    {
        return STORAGE_PATH . '/cta-placement-deployment-assistant.json';
    }
}

if (!function_exists('cta_placement_clean')) {
    function cta_placement_clean(mixed $value, int $max = 220): string
    {
        if (function_exists('offer_cta_lab_clean')) {
            return offer_cta_lab_clean($value, $max);
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('cta_placement_multiline')) {
    function cta_placement_multiline(mixed $value, int $max = 1000): string
    {
        if (function_exists('offer_cta_lab_multiline')) {
            return offer_cta_lab_multiline($value, $max);
        }

        $text = trim(strip_tags((string)$value));
        $text = preg_replace("/\r\n|\r/", "\n", $text) ?: '';
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?: '';
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('cta_placement_id')) {
    function cta_placement_id(string $value = ''): string
    {
        if (function_exists('offer_cta_lab_id')) {
            return offer_cta_lab_id($value);
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]+/', '-', $value) ?: '';
        $value = trim($value, '-');
        if ($value === '') {
            $value = 'deployment-' . substr(md5((string)microtime(true) . random_int(1000, 9999)), 0, 12);
        }

        return substr($value, 0, 120);
    }
}

if (!function_exists('cta_placement_clean_url')) {
    function cta_placement_clean_url(mixed $value, string $fallback = '/kontak'): string
    {
        if (function_exists('offer_cta_lab_clean_url')) {
            return offer_cta_lab_clean_url($value, $fallback);
        }

        $value = trim((string)$value);
        if ($value === '') {
            return $fallback;
        }
        if (preg_match('~^(https?://|mailto:|tel:|/|#)~i', $value)) {
            return substr($value, 0, 500);
        }

        return '/' . ltrim(substr($value, 0, 500), '/');
    }
}

if (!function_exists('cta_placement_status_options')) {
    function cta_placement_status_options(): array
    {
        return [
            'planned' => 'Rencana',
            'ready' => 'Siap Dipasang',
            'deployed' => 'Sudah Dipasang',
            'monitoring' => 'Pantau Hasil',
            'paused' => 'Ditahan',
        ];
    }
}

if (!function_exists('cta_placement_priority_options')) {
    function cta_placement_priority_options(): array
    {
        return [
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            'low' => 'Ringan',
        ];
    }
}

if (!function_exists('cta_placement_area_options')) {
    function cta_placement_area_options(): array
    {
        return [
            'homepage_hero' => [
                'label' => 'Homepage Hero',
                'group' => 'Homepage',
                'description' => 'Area pertama yang dilihat pengunjung. Cocok untuk winner paling kuat.',
                'target_url' => '/',
                'admin_url' => 'admin/homepage',
                'one_click' => true,
            ],
            'homepage_mid' => [
                'label' => 'Homepage Tengah',
                'group' => 'Homepage',
                'description' => 'CTA setelah pengantar, katalog, atau layanan untuk dorong klik berikutnya.',
                'target_url' => '/',
                'admin_url' => 'admin/homepage',
                'one_click' => false,
            ],
            'homepage_bottom' => [
                'label' => 'Homepage Bawah / Form',
                'group' => 'Homepage',
                'description' => 'CTA dekat form agar pengunjung yang sudah membaca bisa langsung kontak.',
                'target_url' => '/#form-konsultasi',
                'admin_url' => 'admin/homepage',
                'one_click' => true,
            ],
            'article_inline' => [
                'label' => 'Artikel / Blog',
                'group' => 'SEO Content',
                'description' => 'CTA di dalam atau akhir artikel untuk mengubah traffic SEO menjadi lead.',
                'target_url' => '/artikel',
                'admin_url' => 'admin/artikel',
                'one_click' => false,
            ],
            'landing_page' => [
                'label' => 'Landing Page',
                'group' => 'Campaign',
                'description' => 'CTA utama untuk halaman promosi khusus, campaign, atau sales page.',
                'target_url' => '/landing-page',
                'admin_url' => 'admin/landing-pages',
                'one_click' => false,
            ],
            'product_detail' => [
                'label' => 'Detail Produk/Jasa',
                'group' => 'Katalog',
                'description' => 'CTA dekat deskripsi produk agar pengunjung bisa lanjut chat atau order.',
                'target_url' => '/katalog',
                'admin_url' => 'admin/produk',
                'one_click' => false,
            ],
            'trust_block' => [
                'label' => 'Trust & Conversion Block',
                'group' => 'Trust',
                'description' => 'CTA setelah testimoni, FAQ, benefit, garansi, atau bukti kepercayaan.',
                'target_url' => '/',
                'admin_url' => 'admin/trust-conversion',
                'one_click' => false,
            ],
            'form_page' => [
                'label' => 'Form / Lead Magnet',
                'group' => 'Lead',
                'description' => 'CTA dan copy pendamping form agar pengunjung paham kenapa harus mengisi data.',
                'target_url' => '/kontak',
                'admin_url' => 'admin/forms',
                'one_click' => false,
            ],
            'follow_up' => [
                'label' => 'Follow-up WA/Email',
                'group' => 'CRM',
                'description' => 'Offer dipakai sebagai script follow-up untuk lead/order yang belum closing.',
                'target_url' => '/kontak',
                'admin_url' => 'admin/followups',
                'one_click' => false,
            ],
            'campaign_playbook' => [
                'label' => 'Profit Playbook / Campaign',
                'group' => 'Campaign',
                'description' => 'Offer winner dijadikan inti campaign 7/14/30 hari.',
                'target_url' => '/kontak',
                'admin_url' => 'admin/profit-playbook',
                'one_click' => false,
            ],
        ];
    }
}

if (!function_exists('cta_placement_default_settings')) {
    function cta_placement_default_settings(): array
    {
        return [
            'enabled' => true,
            'deployment_note' => 'Pakai halaman ini untuk mengubah winner Offer Lab menjadi rencana pemasangan CTA yang jelas dan bisa dipantau.',
            'deployments' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('cta_placement_variant_score_value')) {
    function cta_placement_variant_score_value(array $variant): int
    {
        if (isset($variant['score'])) {
            return max(0, min(100, (int)$variant['score']));
        }

        if (function_exists('offer_cta_lab_variant_score')) {
            $score = offer_cta_lab_variant_score($variant);
            return max(0, min(100, (int)($score['score'] ?? 0)));
        }

        return 0;
    }
}

if (!function_exists('cta_placement_candidate_variants')) {
    function cta_placement_candidate_variants(int $limit = 12): array
    {
        if (!function_exists('offer_cta_lab_settings')) {
            return [];
        }

        $settings = offer_cta_lab_settings(true);
        $variants = [];
        foreach ((array)($settings['variants'] ?? []) as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $status = (string)($variant['status'] ?? 'draft');
            if (!in_array($status, ['winner', 'active', 'testing', 'draft'], true)) {
                continue;
            }
            $variant['score'] = cta_placement_variant_score_value($variant);
            $variants[] = $variant;
        }

        $weight = ['winner' => 5, 'active' => 4, 'testing' => 3, 'draft' => 1];
        usort($variants, static function (array $a, array $b) use ($weight): int {
            $aw = $weight[(string)($a['status'] ?? 'draft')] ?? 0;
            $bw = $weight[(string)($b['status'] ?? 'draft')] ?? 0;
            return ($bw <=> $aw) ?: ((int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0));
        });

        return array_slice($variants, 0, max(1, $limit));
    }
}

if (!function_exists('cta_placement_find_variant')) {
    function cta_placement_find_variant(string $id): ?array
    {
        $id = cta_placement_id($id);
        foreach (cta_placement_candidate_variants(100) as $variant) {
            if ((string)($variant['id'] ?? '') === $id) {
                return $variant;
            }
        }

        return null;
    }
}

if (!function_exists('cta_placement_default_priority')) {
    function cta_placement_default_priority(array $variant, string $placement): string
    {
        $status = (string)($variant['status'] ?? 'draft');
        $score = cta_placement_variant_score_value($variant);
        if ($status === 'winner' || $score >= 80 || in_array($placement, ['homepage_hero', 'article_inline'], true)) {
            return 'high';
        }
        if ($status === 'active' || $score >= 65) {
            return 'medium';
        }
        return 'low';
    }
}

if (!function_exists('cta_placement_deployment_from_variant')) {
    function cta_placement_deployment_from_variant(array $variant, string $placement = '', array $overrides = []): array
    {
        $areas = cta_placement_area_options();
        $placement = cta_placement_id($placement ?: (string)($variant['placement'] ?? 'homepage_mid'));
        if (!isset($areas[$placement])) {
            $placement = 'homepage_mid';
        }
        $area = $areas[$placement];
        $variantId = cta_placement_id((string)($variant['id'] ?? ''));
        $now = date(DATE_ATOM);
        $base = [
            'id' => cta_placement_id(($variantId ?: 'variant') . '-' . $placement),
            'variant_id' => $variantId,
            'variant_title' => cta_placement_clean($variant['title'] ?? 'Offer Winner', 140),
            'placement' => $placement,
            'placement_label' => (string)$area['label'],
            'priority' => cta_placement_default_priority($variant, $placement),
            'status' => 'planned',
            'target_label' => (string)$area['label'],
            'target_url' => (string)$area['target_url'],
            'admin_url' => (string)$area['admin_url'],
            'headline' => cta_placement_clean($variant['headline'] ?? '', 160),
            'subheadline' => cta_placement_clean($variant['subheadline'] ?? '', 320),
            'cta_label' => cta_placement_clean($variant['cta_label'] ?? 'Hubungi Kami', 60),
            'cta_url' => cta_placement_clean_url($variant['cta_url'] ?? '/kontak', '/kontak'),
            'proof_note' => cta_placement_clean($variant['proof_note'] ?? '', 220),
            'hypothesis' => cta_placement_clean($variant['hypothesis'] ?? '', 260),
            'deployment_note' => cta_placement_clean($area['description'] ?? '', 320),
            'last_result_note' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        return cta_placement_normalize_deployment(array_merge($base, $overrides));
    }
}

if (!function_exists('cta_placement_normalize_deployment')) {
    function cta_placement_normalize_deployment(array $deployment, int $index = 0): array
    {
        $areas = cta_placement_area_options();
        $statuses = cta_placement_status_options();
        $priorities = cta_placement_priority_options();
        $now = date(DATE_ATOM);

        $placement = cta_placement_id((string)($deployment['placement'] ?? 'homepage_mid'));
        if (!isset($areas[$placement])) {
            $placement = 'homepage_mid';
        }
        $area = $areas[$placement];

        $status = (string)($deployment['status'] ?? 'planned');
        if (!isset($statuses[$status])) {
            $status = 'planned';
        }

        $priority = (string)($deployment['priority'] ?? 'medium');
        if (!isset($priorities[$priority])) {
            $priority = 'medium';
        }

        $variantId = cta_placement_id((string)($deployment['variant_id'] ?? ''));
        $id = cta_placement_id((string)($deployment['id'] ?? ($variantId . '-' . $placement . '-' . $index)));

        return [
            'id' => $id,
            'variant_id' => $variantId,
            'variant_title' => cta_placement_clean($deployment['variant_title'] ?? 'Offer Winner', 140) ?: 'Offer Winner',
            'placement' => $placement,
            'placement_label' => (string)$area['label'],
            'priority' => $priority,
            'status' => $status,
            'target_label' => cta_placement_clean($deployment['target_label'] ?? $area['label'], 140) ?: (string)$area['label'],
            'target_url' => cta_placement_clean_url($deployment['target_url'] ?? $area['target_url'], (string)$area['target_url']),
            'admin_url' => trim((string)($deployment['admin_url'] ?? $area['admin_url']), '/'),
            'headline' => cta_placement_clean($deployment['headline'] ?? '', 180),
            'subheadline' => cta_placement_clean($deployment['subheadline'] ?? '', 360),
            'cta_label' => cta_placement_clean($deployment['cta_label'] ?? 'Hubungi Kami', 70) ?: 'Hubungi Kami',
            'cta_url' => cta_placement_clean_url($deployment['cta_url'] ?? '/kontak', '/kontak'),
            'proof_note' => cta_placement_clean($deployment['proof_note'] ?? '', 260),
            'hypothesis' => cta_placement_clean($deployment['hypothesis'] ?? '', 320),
            'deployment_note' => cta_placement_multiline($deployment['deployment_note'] ?? ($area['description'] ?? ''), 900),
            'last_result_note' => cta_placement_multiline($deployment['last_result_note'] ?? '', 900),
            'created_at' => cta_placement_clean($deployment['created_at'] ?? $now, 80) ?: $now,
            'updated_at' => cta_placement_clean($deployment['updated_at'] ?? $now, 80) ?: $now,
        ];
    }
}

if (!function_exists('cta_placement_normalize_settings')) {
    function cta_placement_normalize_settings(array $settings): array
    {
        $defaults = cta_placement_default_settings();
        $settings = array_merge($defaults, $settings);
        $settings['enabled'] = !empty($settings['enabled']);
        $settings['deployment_note'] = cta_placement_multiline($settings['deployment_note'] ?? $defaults['deployment_note'], 900) ?: $defaults['deployment_note'];

        $deployments = [];
        $seen = [];
        foreach ((array)($settings['deployments'] ?? []) as $index => $deployment) {
            if (!is_array($deployment)) {
                continue;
            }
            $normalized = cta_placement_normalize_deployment($deployment, (int)$index);
            $id = (string)$normalized['id'];
            $counter = 2;
            while (isset($seen[$id])) {
                $id = cta_placement_id($normalized['id'] . '-' . $counter);
                $counter++;
            }
            $normalized['id'] = $id;
            $seen[$id] = true;
            $deployments[] = $normalized;
        }

        $priorityRank = ['high' => 1, 'medium' => 2, 'low' => 3];
        $statusRank = ['ready' => 1, 'planned' => 2, 'deployed' => 3, 'monitoring' => 4, 'paused' => 5];
        usort($deployments, static function (array $a, array $b) use ($priorityRank, $statusRank): int {
            return (($priorityRank[(string)($a['priority'] ?? 'medium')] ?? 9) <=> ($priorityRank[(string)($b['priority'] ?? 'medium')] ?? 9))
                ?: (($statusRank[(string)($a['status'] ?? 'planned')] ?? 9) <=> ($statusRank[(string)($b['status'] ?? 'planned')] ?? 9))
                ?: strcmp((string)($a['target_label'] ?? ''), (string)($b['target_label'] ?? ''));
        });

        $settings['deployments'] = $deployments;
        $settings['updated_at'] = cta_placement_clean($settings['updated_at'] ?? date(DATE_ATOM), 80);

        return $settings;
    }
}

if (!function_exists('cta_placement_settings')) {
    function cta_placement_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $defaults = cta_placement_default_settings();
        $file = cta_placement_storage_file();
        if (!is_file($file)) {
            $cached = cta_placement_normalize_settings($defaults);
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = cta_placement_normalize_settings($defaults);
            return $cached;
        }

        $cached = cta_placement_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('cta_placement_write_settings')) {
    function cta_placement_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = cta_placement_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(cta_placement_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Deployment assistant gagal disimpan. Cek permission folder storage.');
            }
            return false;
        }

        @chmod(cta_placement_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'cta-placement-deployment', null, 'Menyimpan CTA Placement & Winner Deployment Assistant.', [
                'deployments' => count((array)($settings['deployments'] ?? [])),
            ]);
        }

        return true;
    }
}

if (!function_exists('cta_placement_settings_from_post')) {
    function cta_placement_settings_from_post(array $post, array $current): array
    {
        $settings = $current;
        $settings['enabled'] = isset($post['enabled']);
        $settings['deployment_note'] = cta_placement_multiline($post['deployment_note'] ?? '', 900);

        $ids = (array)($post['deployment_id'] ?? []);
        $deployments = [];
        foreach ($ids as $index => $id) {
            $deployments[] = cta_placement_normalize_deployment([
                'id' => (string)$id,
                'variant_id' => (string)(($post['variant_id'] ?? [])[$index] ?? ''),
                'variant_title' => (string)(($post['variant_title'] ?? [])[$index] ?? ''),
                'placement' => (string)(($post['placement'] ?? [])[$index] ?? 'homepage_mid'),
                'priority' => (string)(($post['priority'] ?? [])[$index] ?? 'medium'),
                'status' => (string)(($post['status'] ?? [])[$index] ?? 'planned'),
                'target_label' => (string)(($post['target_label'] ?? [])[$index] ?? ''),
                'target_url' => (string)(($post['target_url'] ?? [])[$index] ?? '/'),
                'admin_url' => (string)(($post['admin_url'] ?? [])[$index] ?? ''),
                'headline' => (string)(($post['headline'] ?? [])[$index] ?? ''),
                'subheadline' => (string)(($post['subheadline'] ?? [])[$index] ?? ''),
                'cta_label' => (string)(($post['cta_label'] ?? [])[$index] ?? ''),
                'cta_url' => (string)(($post['cta_url'] ?? [])[$index] ?? '/kontak'),
                'proof_note' => (string)(($post['proof_note'] ?? [])[$index] ?? ''),
                'hypothesis' => (string)(($post['hypothesis'] ?? [])[$index] ?? ''),
                'deployment_note' => (string)(($post['item_deployment_note'] ?? [])[$index] ?? ''),
                'last_result_note' => (string)(($post['last_result_note'] ?? [])[$index] ?? ''),
                'created_at' => (string)(($post['created_at'] ?? [])[$index] ?? date(DATE_ATOM)),
                'updated_at' => date(DATE_ATOM),
            ], (int)$index);
        }

        $settings['deployments'] = $deployments;
        return cta_placement_normalize_settings($settings);
    }
}

if (!function_exists('cta_placement_add_deployment')) {
    function cta_placement_add_deployment(string $variantId, string $placement): array
    {
        $variant = cta_placement_find_variant($variantId);
        if (!$variant) {
            throw new RuntimeException('Varian offer/CTA tidak ditemukan.');
        }

        $settings = cta_placement_settings(true);
        $new = cta_placement_deployment_from_variant($variant, $placement);
        $exists = false;
        foreach ((array)($settings['deployments'] ?? []) as $deployment) {
            if ((string)($deployment['variant_id'] ?? '') === (string)$new['variant_id'] && (string)($deployment['placement'] ?? '') === (string)$new['placement']) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $settings['deployments'][] = $new;
        }
        cta_placement_write_settings($settings, true);

        return $new;
    }
}

if (!function_exists('cta_placement_update_status')) {
    function cta_placement_update_status(string $id, string $status): bool
    {
        $statuses = cta_placement_status_options();
        if (!isset($statuses[$status])) {
            throw new RuntimeException('Status deployment tidak valid.');
        }
        $settings = cta_placement_settings(true);
        $updated = false;
        foreach ($settings['deployments'] as &$deployment) {
            if ((string)($deployment['id'] ?? '') === cta_placement_id($id)) {
                $deployment['status'] = $status;
                $deployment['updated_at'] = date(DATE_ATOM);
                $updated = true;
                break;
            }
        }
        unset($deployment);

        if ($updated) {
            cta_placement_write_settings($settings, true);
        }

        return $updated;
    }
}

if (!function_exists('cta_placement_delete_deployment')) {
    function cta_placement_delete_deployment(string $id): bool
    {
        $id = cta_placement_id($id);
        $settings = cta_placement_settings(true);
        $before = count((array)$settings['deployments']);
        $settings['deployments'] = array_values(array_filter((array)$settings['deployments'], static fn(array $deployment): bool => (string)($deployment['id'] ?? '') !== $id));
        $changed = count((array)$settings['deployments']) !== $before;
        if ($changed) {
            cta_placement_write_settings($settings, true);
        }

        return $changed;
    }
}

if (!function_exists('cta_placement_reset')) {
    function cta_placement_reset(): void
    {
        cta_placement_write_settings(cta_placement_default_settings(), true);
    }
}

if (!function_exists('cta_placement_find_deployment')) {
    function cta_placement_find_deployment(string $id): ?array
    {
        $id = cta_placement_id($id);
        foreach ((array)(cta_placement_settings(true)['deployments'] ?? []) as $deployment) {
            if ((string)($deployment['id'] ?? '') === $id) {
                return $deployment;
            }
        }

        return null;
    }
}

if (!function_exists('cta_placement_deploy_homepage_hero')) {
    function cta_placement_deploy_homepage_hero(string $id): array
    {
        $deployment = cta_placement_find_deployment($id);
        if (!$deployment) {
            throw new RuntimeException('Rencana deployment tidak ditemukan.');
        }
        if (!function_exists('homepage_settings') || !function_exists('homepage_save_settings')) {
            throw new RuntimeException('Helper homepage belum tersedia.');
        }

        $settings = homepage_settings();
        $settings['source'] = 'template';
        $settings['sections']['hero'] = true;
        $settings['hero']['eyebrow'] = cta_placement_clean($deployment['variant_title'] ?? 'Penawaran Pilihan', 100) ?: 'Penawaran Pilihan';
        $settings['hero']['title'] = cta_placement_clean($deployment['headline'] ?? '', 140) ?: (string)($settings['hero']['title'] ?? 'Penawaran Pilihan');
        $settings['hero']['description'] = cta_placement_clean($deployment['subheadline'] ?? '', 320) ?: (string)($settings['hero']['description'] ?? '');
        $settings['hero']['primary_label'] = cta_placement_clean($deployment['cta_label'] ?? 'Hubungi Kami', 40) ?: 'Hubungi Kami';
        $settings['hero']['primary_url'] = cta_placement_clean_url($deployment['cta_url'] ?? '/kontak', '/kontak');
        $settings['cta_tracking']['homepage_hero'] = [
            'deployment_id' => (string)($deployment['id'] ?? ''),
            'variant_id' => (string)($deployment['variant_id'] ?? ''),
            'placement' => 'homepage_hero',
        ];
        $saved = homepage_save_settings($settings);

        cta_placement_update_status($id, 'deployed');
        return $saved;
    }
}

if (!function_exists('cta_placement_deploy_homepage_form')) {
    function cta_placement_deploy_homepage_form(string $id): array
    {
        $deployment = cta_placement_find_deployment($id);
        if (!$deployment) {
            throw new RuntimeException('Rencana deployment tidak ditemukan.');
        }
        if (!function_exists('homepage_settings') || !function_exists('homepage_save_settings')) {
            throw new RuntimeException('Helper homepage belum tersedia.');
        }

        $settings = homepage_settings();
        $settings['source'] = 'template';
        $settings['sections']['lead_form'] = true;
        $settings['lead_form']['title'] = cta_placement_clean($deployment['headline'] ?? '', 100) ?: (string)($settings['lead_form']['title'] ?? 'Butuh Bantuan?');
        $settings['lead_form']['text'] = cta_placement_clean($deployment['subheadline'] ?? '', 260) ?: (string)($settings['lead_form']['text'] ?? 'Isi form untuk konsultasi.');
        $settings['lead_form']['button'] = cta_placement_clean($deployment['cta_label'] ?? 'Kirim Permintaan', 60) ?: 'Kirim Permintaan';
        $settings['cta_tracking']['homepage_bottom'] = [
            'deployment_id' => (string)($deployment['id'] ?? ''),
            'variant_id' => (string)($deployment['variant_id'] ?? ''),
            'placement' => 'homepage_bottom',
        ];
        $saved = homepage_save_settings($settings);

        cta_placement_update_status($id, 'deployed');
        return $saved;
    }
}

if (!function_exists('cta_placement_suggestions')) {
    function cta_placement_suggestions(int $limit = 8): array
    {
        $areas = cta_placement_area_options();
        $suggestions = [];
        $seen = [];

        foreach (cta_placement_candidate_variants(10) as $variant) {
            $status = (string)($variant['status'] ?? 'draft');
            $score = cta_placement_variant_score_value($variant);
            $basePlacement = cta_placement_id((string)($variant['placement'] ?? 'homepage_mid'));
            $placements = [];

            if ($status === 'winner') {
                $placements[] = 'homepage_hero';
                $placements[] = 'landing_page';
            }
            if ($basePlacement !== '' && isset($areas[$basePlacement])) {
                $placements[] = $basePlacement;
            }
            if ((string)($variant['goal'] ?? '') === 'article_to_offer') {
                $placements[] = 'article_inline';
            }
            if ((string)($variant['goal'] ?? '') === 'trust_building') {
                $placements[] = 'trust_block';
            }
            if ((string)($variant['goal'] ?? '') === 'campaign_boost') {
                $placements[] = 'campaign_playbook';
            }
            if ($score >= 70) {
                $placements[] = 'homepage_bottom';
            }
            $placements[] = 'homepage_mid';

            foreach (array_values(array_unique($placements)) as $placement) {
                if (!isset($areas[$placement])) {
                    continue;
                }
                $key = (string)($variant['id'] ?? '') . ':' . $placement;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $suggestions[] = cta_placement_deployment_from_variant($variant, $placement, [
                    'status' => $status === 'winner' ? 'ready' : 'planned',
                    'deployment_note' => cta_placement_suggestion_reason($variant, $placement),
                ]);
                if (count($suggestions) >= $limit) {
                    return $suggestions;
                }
            }
        }

        return $suggestions;
    }
}

if (!function_exists('cta_placement_suggestion_reason')) {
    function cta_placement_suggestion_reason(array $variant, string $placement): string
    {
        $areas = cta_placement_area_options();
        $area = $areas[$placement] ?? ['label' => 'Area CTA', 'description' => ''];
        $status = (string)($variant['status'] ?? 'draft');
        $score = cta_placement_variant_score_value($variant);
        $reason = 'Cocok dipasang di ' . (string)$area['label'] . ' karena ' . strtolower((string)($area['description'] ?? 'area ini dekat dengan keputusan pengunjung.'));
        if ($status === 'winner') {
            $reason .= ' Varian ini sudah berstatus winner, jadi layak diprioritaskan.';
        } elseif ($score >= 70) {
            $reason .= ' Skor kesiapan offer cukup kuat untuk diuji di area ini.';
        } else {
            $reason .= ' Mulai sebagai rencana dulu, lalu pantau hasilnya setelah dipakai.';
        }

        return $reason;
    }
}

if (!function_exists('cta_placement_checklist')) {
    function cta_placement_checklist(array $deployment): array
    {
        $placement = (string)($deployment['placement'] ?? 'homepage_mid');
        $base = [
            'Pastikan headline menyebut manfaat utama dengan jelas.',
            'Pastikan tombol CTA mengarah ke langkah berikutnya yang benar.',
            'Tambahkan proof/trust cue dekat CTA bila pengunjung masih perlu diyakinkan.',
            'Setelah dipasang, pantau klik, lead, order, atau pesan masuk selama beberapa hari.',
        ];

        $specific = [
            'homepage_hero' => ['Cek tampilan hero di desktop dan mobile.', 'Jangan pakai dua CTA utama yang saling membingungkan.'],
            'homepage_bottom' => ['Pastikan form konsultasi aktif dan notifikasi lead masuk normal.'],
            'article_inline' => ['Pilih artikel yang intent-nya paling dekat dengan produk/jasa.', 'Letakkan CTA setelah jawaban utama, bukan terlalu awal.'],
            'landing_page' => ['Pastikan satu landing page fokus pada satu offer utama.', 'Samakan CTA atas, tengah, dan bawah agar tidak pecah fokus.'],
            'product_detail' => ['Letakkan CTA dekat harga/manfaat/fitur utama.', 'Tambahkan jawaban keberatan singkat sebelum tombol.'],
            'trust_block' => ['Letakkan CTA setelah testimoni/FAQ/garansi, bukan sebelum bukti.'],
            'form_page' => ['Buat copy form terasa ringan dan aman untuk diisi.'],
            'follow_up' => ['Gunakan bahasa personal dan jangan terlalu memaksa.'],
            'campaign_playbook' => ['Masukkan offer ini ke task campaign dan script follow-up.'],
        ];

        return array_values(array_merge($specific[$placement] ?? [], $base));
    }
}

if (!function_exists('cta_placement_summary')) {
    function cta_placement_summary(?array $settings = null): array
    {
        $settings = $settings ?? cta_placement_settings(true);
        $deployments = (array)($settings['deployments'] ?? []);
        $statusCounts = array_fill_keys(array_keys(cta_placement_status_options()), 0);
        $priorityCounts = array_fill_keys(array_keys(cta_placement_priority_options()), 0);
        $areas = cta_placement_area_options();
        $oneClickReady = 0;
        $deployed = 0;

        foreach ($deployments as $deployment) {
            $status = (string)($deployment['status'] ?? 'planned');
            $priority = (string)($deployment['priority'] ?? 'medium');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
            if (isset($priorityCounts[$priority])) {
                $priorityCounts[$priority]++;
            }
            if ($status === 'deployed') {
                $deployed++;
            }
            $placement = (string)($deployment['placement'] ?? '');
            if (!empty($areas[$placement]['one_click']) && in_array($status, ['ready', 'planned'], true)) {
                $oneClickReady++;
            }
        }

        $candidates = cta_placement_candidate_variants(20);
        $winnerCount = count(array_filter($candidates, static fn(array $variant): bool => (string)($variant['status'] ?? '') === 'winner'));
        $readiness = min(100, ($winnerCount > 0 ? 30 : 0) + (count($deployments) > 0 ? 25 : 0) + min(25, $deployed * 12) + min(20, $oneClickReady * 8));

        return [
            'total_deployments' => count($deployments),
            'status_counts' => $statusCounts,
            'priority_counts' => $priorityCounts,
            'one_click_ready' => $oneClickReady,
            'deployed' => $deployed,
            'candidate_count' => count($candidates),
            'winner_count' => $winnerCount,
            'readiness_score' => $readiness,
            'next_focus' => $winnerCount > 0
                ? 'Pasang winner ke area dengan traffic/intent paling tinggi, lalu pantau hasilnya.'
                : 'Pilih satu winner di Offer Lab dulu agar deployment lebih terarah.',
        ];
    }
}

if (!function_exists('cta_placement_export_csv')) {
    function cta_placement_export_csv(array $deployments): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cta-placement-deployment-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['status', 'priority', 'placement', 'target_label', 'target_url', 'variant_title', 'headline', 'cta_label', 'cta_url', 'proof_note', 'deployment_note']);
        foreach ($deployments as $deployment) {
            fputcsv($out, [
                (string)($deployment['status'] ?? ''),
                (string)($deployment['priority'] ?? ''),
                (string)($deployment['placement_label'] ?? $deployment['placement'] ?? ''),
                (string)($deployment['target_label'] ?? ''),
                (string)($deployment['target_url'] ?? ''),
                (string)($deployment['variant_title'] ?? ''),
                (string)($deployment['headline'] ?? ''),
                (string)($deployment['cta_label'] ?? ''),
                (string)($deployment['cta_url'] ?? ''),
                (string)($deployment['proof_note'] ?? ''),
                (string)($deployment['deployment_note'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
