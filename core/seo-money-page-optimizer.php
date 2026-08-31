<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO MONEY PAGE OPTIMIZER
|--------------------------------------------------------------------------
| Turns SEO Journey Map and Lead Tracking Bridge signals into practical
| optimization briefs for pages that can become money pages. This is not a
| new tracker; it reads the existing journey/lead/CTA ecosystem.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_money_clean')) {
    function seo_money_clean(mixed $value, int $max = 180): string
    {
        if (function_exists('seo_journey_clean')) {
            return seo_journey_clean($value, $max);
        }
        if (function_exists('seo_profit_clean')) {
            return seo_profit_clean($value, $max);
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('seo_money_id')) {
    function seo_money_id(string $value = ''): string
    {
        if (function_exists('seo_journey_id')) {
            return seo_journey_id($value);
        }
        if (function_exists('seo_profit_id')) {
            return seo_profit_id($value);
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');

        return substr($value, 0, 140);
    }
}

if (!function_exists('seo_money_storage_file')) {
    function seo_money_storage_file(): string
    {
        return STORAGE_PATH . '/seo-money-page-optimizer-decisions.json';
    }
}

if (!function_exists('seo_money_decision_options')) {
    function seo_money_decision_options(): array
    {
        return [
            'queued' => 'Masuk antrean optimasi',
            'optimizing' => 'Sedang dioptimasi',
            'done' => 'Sudah dikerjakan',
            'monitor' => 'Pantau hasil',
            'hold' => 'Tahan dulu',
        ];
    }
}

if (!function_exists('seo_money_default_settings')) {
    function seo_money_default_settings(): array
    {
        return [
            'decisions' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_money_normalize_decision')) {
    function seo_money_normalize_decision(array $decision): array
    {
        $options = seo_money_decision_options();
        $status = (string)($decision['status'] ?? 'queued');
        if (!isset($options[$status])) {
            $status = 'queued';
        }

        return [
            'page_id' => seo_money_id((string)($decision['page_id'] ?? '')),
            'status' => $status,
            'owner_note' => seo_money_clean($decision['owner_note'] ?? '', 420),
            'updated_at' => seo_money_clean($decision['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_money_normalize_settings')) {
    function seo_money_normalize_settings(array $settings): array
    {
        $settings = array_merge(seo_money_default_settings(), $settings);
        $decisions = [];

        foreach ((array)($settings['decisions'] ?? []) as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $normalized = seo_money_normalize_decision($decision);
            if ((string)$normalized['page_id'] === '') {
                continue;
            }
            $decisions[(string)$normalized['page_id']] = $normalized;
        }

        return [
            'decisions' => $decisions,
            'updated_at' => seo_money_clean($settings['updated_at'] ?? date(DATE_ATOM), 80),
        ];
    }
}

if (!function_exists('seo_money_settings')) {
    function seo_money_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = seo_money_storage_file();
        if (!is_file($file)) {
            $cached = seo_money_normalize_settings(seo_money_default_settings());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = seo_money_normalize_settings(seo_money_default_settings());
            return $cached;
        }

        $cached = seo_money_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('seo_money_write_settings')) {
    function seo_money_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = seo_money_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(seo_money_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan SEO Money Page Optimizer belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(seo_money_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'seo-money-page-optimizer', null, 'Menyimpan keputusan SEO Money Page Optimizer.');
        }

        return true;
    }
}

if (!function_exists('seo_money_update_decision')) {
    function seo_money_update_decision(string $pageId, string $status, string $note = ''): bool
    {
        $pageId = seo_money_id($pageId);
        if ($pageId === '') {
            throw new RuntimeException('ID halaman money page tidak valid.');
        }

        $settings = seo_money_settings(true);
        $settings['decisions'][$pageId] = seo_money_normalize_decision([
            'page_id' => $pageId,
            'status' => $status,
            'owner_note' => $note,
            'updated_at' => date(DATE_ATOM),
        ]);

        return seo_money_write_settings($settings, true);
    }
}

if (!function_exists('seo_money_reset_decisions')) {
    function seo_money_reset_decisions(): void
    {
        if (is_file(seo_money_storage_file())) {
            @unlink(seo_money_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'seo-money-page-optimizer', null, 'Reset catatan SEO Money Page Optimizer.');
        }
    }
}

if (!function_exists('seo_money_tokens')) {
    function seo_money_tokens(string $text, int $limit = 20): array
    {
        $text = strtolower(seo_money_clean($text, 600));
        $tokens = preg_split('/[^a-z0-9]+/i', $text) ?: [];
        $skip = ['yang', 'dan', 'atau', 'untuk', 'dengan', 'dari', 'pada', 'dalam', 'kami', 'anda', 'this', 'that', 'with', 'from'];
        $tokens = array_values(array_filter(array_unique($tokens), static function (string $token) use ($skip): bool {
            return strlen($token) >= 4 && !in_array($token, $skip, true);
        }));

        return array_slice($tokens, 0, max(1, $limit));
    }
}

if (!function_exists('seo_money_item_text')) {
    function seo_money_item_text(array $item): string
    {
        return implode(' ', array_filter([
            (string)($item['title'] ?? ''),
            (string)($item['meta_title'] ?? ''),
            (string)($item['meta_description'] ?? ''),
            implode(' ', (array)($item['keywords'] ?? [])),
            (string)($item['body'] ?? ''),
        ]));
    }
}

if (!function_exists('seo_money_internal_link_targets')) {
    function seo_money_internal_link_targets(array $sourceItem, int $limit = 4): array
    {
        $items = function_exists('universal_seo_summary')
            ? (array)(universal_seo_summary('all')['items'] ?? [])
            : (function_exists('universal_seo_items') ? universal_seo_items(true) : []);

        $sourceId = seo_money_id((string)($sourceItem['page_id'] ?? ($sourceItem['type'] ?? 'page') . '-' . ($sourceItem['slug'] ?? $sourceItem['id'] ?? '')));
        $sourceUrl = (string)($sourceItem['url'] ?? '');
        $sourceType = (string)($sourceItem['type'] ?? 'page');
        $sourceTokens = seo_money_tokens(seo_money_item_text($sourceItem), 18);
        $targets = [];

        foreach ($items as $target) {
            if (!is_array($target) || empty($target['indexable'])) {
                continue;
            }
            $targetId = seo_money_id((string)($target['page_id'] ?? ($target['type'] ?? 'page') . '-' . ($target['slug'] ?? $target['id'] ?? '')));
            $targetUrl = (string)($target['url'] ?? '');
            if (($targetId !== '' && $targetId === $sourceId) || ($targetUrl !== '' && $targetUrl === $sourceUrl)) {
                continue;
            }

            $targetType = (string)($target['type'] ?? 'page');
            $targetTokens = seo_money_tokens(seo_money_item_text($target), 18);
            $overlap = count(array_intersect($sourceTokens, $targetTokens));
            $score = $overlap * 12;

            if (in_array($sourceType, ['article', 'landing_page', 'seo_landing'], true) && in_array($targetType, ['product', 'service', 'landing_page'], true)) {
                $score += 35;
            } elseif (in_array($sourceType, ['product', 'service'], true) && in_array($targetType, ['article', 'landing_page', 'seo_landing'], true)) {
                $score += 25;
            } elseif ($targetType === 'static_page') {
                $score += 8;
            }

            $targetScore = (int)($target['score'] ?? 0);
            if ($targetScore >= 80) {
                $score += 8;
            }
            if ($score <= 0) {
                $score = in_array($targetType, ['product', 'service'], true) ? 10 : 4;
            }

            $targets[] = [
                'score' => min(100, $score),
                'title' => seo_money_clean($target['title'] ?? 'Halaman internal', 120),
                'type' => $targetType,
                'type_label' => function_exists('seo_profit_type_label') ? seo_profit_type_label($targetType) : ucfirst(str_replace('_', ' ', $targetType)),
                'url' => (string)($target['url'] ?? ''),
                'edit_url' => (string)($target['edit_url'] ?? ''),
                'reason' => $overlap > 0 ? 'Topiknya masih beririsan dengan halaman ini.' : 'Cocok sebagai jalur lanjut ke produk, layanan, atau halaman penting.',
            ];
        }

        usort($targets, static fn(array $a, array $b): int => ((int)$b['score'] <=> (int)$a['score']) ?: strcmp((string)$a['title'], (string)$b['title']));

        return array_slice($targets, 0, max(1, $limit));
    }
}

if (!function_exists('seo_money_pick_placement')) {
    function seo_money_pick_placement(array $item, array $journey): string
    {
        $type = (string)($item['type'] ?? 'page');
        $bottleneck = (string)($journey['bottleneck']['key'] ?? 'monitor');

        if ($type === 'article') {
            return 'article_inline';
        }
        if (in_array($type, ['product', 'service'], true)) {
            return 'product_detail';
        }
        if (in_array($type, ['landing_page', 'seo_landing'], true)) {
            return 'landing_page';
        }
        if ($bottleneck === 'lead_no_order') {
            return 'follow_up';
        }
        if ($bottleneck === 'order_ready') {
            return 'campaign_playbook';
        }

        return 'homepage_mid';
    }
}

if (!function_exists('seo_money_cta_plan')) {
    function seo_money_cta_plan(array $item, array $journey, ?array $bestVariant = null): array
    {
        $placement = seo_money_pick_placement($item, $journey);
        $areas = function_exists('cta_placement_area_options') ? cta_placement_area_options() : [];
        $area = (array)($areas[$placement] ?? []);
        $title = seo_money_clean($item['title'] ?? 'halaman ini', 90);
        $type = (string)($item['type'] ?? 'page');
        $bottleneck = (string)($journey['bottleneck']['key'] ?? 'monitor');

        $headline = seo_money_clean($bestVariant['headline'] ?? '', 120);
        $subheadline = seo_money_clean($bestVariant['subheadline'] ?? '', 220);
        $ctaLabel = seo_money_clean($bestVariant['cta_label'] ?? '', 60);
        $ctaUrl = seo_money_clean($bestVariant['cta_url'] ?? '', 180);
        $proof = seo_money_clean($bestVariant['proof_note'] ?? '', 220);

        if ($headline === '') {
            $headline = in_array($type, ['article', 'landing_page', 'seo_landing'], true)
                ? 'Ingin menerapkan ini untuk kebutuhan Anda?'
                : 'Butuh rekomendasi yang paling cocok?';
        }
        if ($subheadline === '') {
            $subheadline = 'Setelah membaca “' . $title . '”, arahkan pengunjung ke chat, form, atau katalog agar traffic SEO tidak berhenti sebagai pembaca saja.';
        }
        if ($ctaLabel === '') {
            $ctaLabel = $bottleneck === 'lead_no_order' ? 'Minta Penawaran Terbaik' : 'Tanya Rekomendasi';
        }
        if ($ctaUrl === '') {
            $ctaUrl = '/kontak';
        }
        if ($proof === '') {
            $proof = 'Tambahkan bukti seperti testimoni, FAQ, garansi, portofolio, atau alasan kenapa calon customer aman mengambil langkah berikutnya.';
        }

        $slots = match ($placement) {
            'article_inline' => ['Setelah pembukaan artikel', 'Tengah artikel setelah poin edukasi penting', 'Akhir artikel sebelum rekomendasi produk/jasa'],
            'product_detail' => ['Dekat judul dan manfaat produk/jasa', 'Setelah detail harga/fitur', 'Dekat form order atau tombol WhatsApp'],
            'landing_page' => ['Hero landing page', 'Setelah benefit dan proof', 'Bagian bawah sebelum form'],
            'follow_up' => ['Template follow-up WA/email', 'Reminder order atau lead hangat', 'Script closing setelah calon customer bertanya'],
            'campaign_playbook' => ['Campaign headline', 'CTA utama campaign', 'Konten pendukung campaign'],
            default => ['Homepage tengah', 'Setelah trust block', 'Dekat form kontak atau katalog'],
        };

        return [
            'placement' => $placement,
            'placement_label' => (string)($area['label'] ?? ucwords(str_replace('_', ' ', $placement))),
            'admin_url' => (string)($area['admin_url'] ?? 'admin/cta-placement'),
            'headline' => $headline,
            'subheadline' => $subheadline,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaUrl,
            'proof_note' => $proof,
            'slots' => $slots,
            'source_variant_id' => seo_money_clean($bestVariant['id'] ?? '', 90),
            'source_variant_title' => seo_money_clean($bestVariant['title'] ?? '', 120),
        ];
    }
}

if (!function_exists('seo_money_trust_plan')) {
    function seo_money_trust_plan(array $item, array $metrics, array $journey): array
    {
        $trust = function_exists('trust_conversion_summary') ? trust_conversion_summary() : [];
        $byType = (array)($trust['by_type'] ?? []);
        $clicks = (int)($metrics['clicks'] ?? 0);
        $leads = (int)($metrics['leads'] ?? 0);
        $orders = (int)($metrics['orders'] ?? 0);
        $bottleneck = (string)($journey['bottleneck']['key'] ?? 'monitor');
        $title = seo_money_clean($item['title'] ?? 'halaman ini', 90);
        $plans = [];

        if ($clicks > 0 && $leads <= 0) {
            $plans[] = ['type' => 'faq', 'label' => 'FAQ keberatan', 'text' => 'Jawab 3-5 pertanyaan yang membuat orang ragu setelah klik CTA dari halaman ini.'];
            $plans[] = ['type' => 'guarantee', 'label' => 'Garansi / komitmen', 'text' => 'Tambahkan rasa aman seperti proses jelas, support, konsultasi dulu, atau komitmen layanan.'];
        } elseif ($leads > 0 && $orders <= 0) {
            $plans[] = ['type' => 'testimonials', 'label' => 'Testimoni relevan', 'text' => 'Tampilkan bukti pelanggan yang mirip dengan kebutuhan pembaca halaman ini.'];
            $plans[] = ['type' => 'trust_badges', 'label' => 'Badge trust', 'text' => 'Tampilkan angka, pengalaman, proses aman, atau bukti kredibilitas yang mudah dipahami.'];
        } elseif ($orders > 0 || $bottleneck === 'order_ready') {
            $plans[] = ['type' => 'testimonials', 'label' => 'Proof pemenang', 'text' => 'Jadikan halaman ini makin kuat dengan testimoni/portofolio dari customer terbaik.'];
            $plans[] = ['type' => 'cta', 'label' => 'CTA scale', 'text' => 'Buat CTA block khusus untuk campaign dari halaman yang sudah membawa order.'];
        } else {
            $plans[] = ['type' => 'benefits', 'label' => 'Benefit utama', 'text' => 'Ringkas 3 manfaat yang membuat pembaca perlu lanjut ke produk, jasa, atau konsultasi.'];
            $plans[] = ['type' => 'cta', 'label' => 'CTA transisi', 'text' => 'Tambahkan ajakan aksi yang natural setelah pembaca memahami topik “' . $title . '”.'];
        }

        $plans[] = ['type' => 'before_after', 'label' => 'Before-after', 'text' => 'Tunjukkan perubahan sebelum dan sesudah memakai solusi agar manfaat lebih konkret.'];

        foreach ($plans as &$plan) {
            $type = (string)($plan['type'] ?? 'cta');
            $plan['current_count'] = (int)($byType[$type] ?? 0);
            $plan['need_attention'] = ((int)$plan['current_count']) <= 0;
        }
        unset($plan);

        return array_slice($plans, 0, 3);
    }
}

if (!function_exists('seo_money_content_fix_plan')) {
    function seo_money_content_fix_plan(array $item): array
    {
        $fixes = [];
        foreach ((array)($item['issues'] ?? []) as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $fixes[] = [
                'title' => seo_money_clean($issue['title'] ?? 'Catatan SEO', 120),
                'text' => seo_money_clean($issue['suggestion'] ?? $issue['message'] ?? 'Perbaiki bagian ini agar halaman lebih siap menghasilkan conversion.', 240),
                'field' => seo_money_clean($issue['field'] ?? 'seo', 60),
                'severity' => seo_money_clean($issue['severity'] ?? 'info', 30),
            ];
        }

        if (!$fixes) {
            $fixes[] = ['title' => 'Tambahkan paragraf transisi ke CTA', 'text' => 'Sisipkan kalimat yang menghubungkan isi halaman dengan produk, layanan, form, atau WhatsApp.', 'field' => 'cta_copy', 'severity' => 'info'];
            $fixes[] = ['title' => 'Perkuat internal link', 'text' => 'Tambahkan link ke produk/jasa paling relevan dan artikel pendukung agar pembaca punya jalur lanjut.', 'field' => 'internal_links', 'severity' => 'info'];
        }

        return array_slice($fixes, 0, 4);
    }
}

if (!function_exists('seo_money_stage')) {
    function seo_money_stage(array $item, array $metrics, array $journey): array
    {
        $events = (int)($metrics['events'] ?? 0);
        $clicks = (int)($metrics['clicks'] ?? 0);
        $leads = (int)($metrics['leads'] ?? 0);
        $orders = (int)($metrics['orders'] ?? 0) + (int)($metrics['payments'] ?? 0);
        $seoScore = (int)($item['score'] ?? 0);
        $bottleneck = (string)($journey['bottleneck']['key'] ?? 'monitor');

        if ($orders > 0 || $bottleneck === 'order_ready') {
            return ['key' => 'scale_ready', 'label' => 'Siap di-scale', 'tone' => 'scale', 'text' => 'Halaman sudah punya sinyal order/payment. Jadikan money page utama, tambah internal link, dan masukkan campaign.'];
        }
        if ($leads > 0) {
            return ['key' => 'lead_optimizer', 'label' => 'Lead optimizer', 'tone' => 'followup', 'text' => 'Halaman sudah membawa lead. Fokus ke trust, follow-up, proof, dan offer agar naik ke order.'];
        }
        if ($clicks > 0) {
            return ['key' => 'offer_optimizer', 'label' => 'Offer optimizer', 'tone' => 'improve', 'text' => 'Klik sudah ada, tapi lead/order belum kuat. Perbaiki offer, form, dan bukti.'];
        }
        if ($events > 0) {
            return ['key' => 'cta_builder', 'label' => 'CTA builder', 'tone' => 'place', 'text' => 'Halaman mulai terbaca. Pasang CTA dan internal link agar traffic berubah menjadi aksi.'];
        }
        if ($seoScore < 75) {
            return ['key' => 'seo_foundation', 'label' => 'SEO foundation', 'tone' => 'seo', 'text' => 'Halaman perlu dipoles dulu agar siap menangkap traffic dan conversion.'];
        }

        return ['key' => 'seed_money_page', 'label' => 'Seed money page', 'tone' => 'monitor', 'text' => 'Halaman punya potensi, tapi butuh traffic, internal link, dan CTA untuk mulai terbaca.'];
    }
}

if (!function_exists('seo_money_priority')) {
    function seo_money_priority(int $moneyScore, array $metrics, array $stage): string
    {
        $orders = (int)($metrics['orders'] ?? 0) + (int)($metrics['payments'] ?? 0);
        $leads = (int)($metrics['leads'] ?? 0);
        $clicks = (int)($metrics['clicks'] ?? 0);
        $stageKey = (string)($stage['key'] ?? 'monitor');

        if ($orders > 0 || $leads > 0 || $moneyScore >= 78 || in_array($stageKey, ['scale_ready', 'lead_optimizer'], true)) {
            return 'high';
        }
        if ($clicks > 0 || $moneyScore >= 58 || $stageKey === 'offer_optimizer') {
            return 'medium';
        }
        return 'low';
    }
}

if (!function_exists('seo_money_optimizer_item')) {
    function seo_money_optimizer_item(array $journey, array $decision = [], ?array $bestVariant = null): array
    {
        $item = (array)($journey['item'] ?? []);
        $metrics = (array)($journey['metrics'] ?? []);
        $stage = seo_money_stage($item, $metrics, $journey);
        $seoScore = (int)($item['score'] ?? 0);
        $journeyScore = (int)($journey['journey_score'] ?? 0);
        $profitScore = (int)($journey['profit_score'] ?? 0);
        $signalBonus = min(16, ((int)($metrics['orders'] ?? 0) * 6) + ((int)($metrics['payments'] ?? 0) * 6) + ((int)($metrics['leads'] ?? 0) * 4) + ((int)($metrics['clicks'] ?? 0) * 2));
        $moneyScore = min(100, (int)round(($journeyScore * 0.42) + ($profitScore * 0.34) + ($seoScore * 0.18) + $signalBonus));
        $priority = seo_money_priority($moneyScore, $metrics, $stage);
        $pageId = seo_money_id((string)($journey['page_id'] ?? $item['page_id'] ?? ''));
        $ctaPlan = seo_money_cta_plan($item, $journey, $bestVariant);
        $internalLinks = seo_money_internal_link_targets($item, 4);
        $trustPlan = seo_money_trust_plan($item, $metrics, $journey);
        $contentFixes = seo_money_content_fix_plan($item);
        $title = seo_money_clean($item['title'] ?? 'Halaman SEO', 120);

        $steps = [
            'Rapikan intro/offer halaman agar pembaca paham manfaat dan langkah berikutnya.',
            'Pasang CTA “' . (string)$ctaPlan['cta_label'] . '” di area ' . (string)$ctaPlan['placement_label'] . '.',
            'Tambahkan internal link ke ' . (string)($internalLinks[0]['title'] ?? 'produk/jasa utama') . '.',
            'Lengkapi trust block: ' . (string)($trustPlan[0]['label'] ?? 'FAQ dan proof') . '.',
            'Pantau ulang hasilnya di CTA Result Tracker dan SEO Journey Map setelah ada traffic.',
        ];

        return [
            'page_id' => $pageId,
            'item' => $item,
            'metrics' => $metrics,
            'stage' => $stage,
            'priority' => $priority,
            'money_score' => $moneyScore,
            'journey_score' => $journeyScore,
            'profit_score' => $profitScore,
            'bottleneck' => (array)($journey['bottleneck'] ?? []),
            'cta_plan' => $ctaPlan,
            'internal_links' => $internalLinks,
            'trust_plan' => $trustPlan,
            'content_fixes' => $contentFixes,
            'action_steps' => $steps,
            'decision' => $decision,
            'brief' => [
                'title' => 'Optimasi money page: ' . $title,
                'summary' => (string)($stage['text'] ?? 'Halaman ini perlu dipoles agar lebih dekat ke lead/order.'),
                'copy' => (string)$ctaPlan['headline'] . ' — ' . (string)$ctaPlan['subheadline'] . ' CTA: ' . (string)$ctaPlan['cta_label'],
            ],
        ];
    }
}

if (!function_exists('seo_money_summary')) {
    function seo_money_summary(int $days = 30, string $type = 'all', string $priority = 'all'): array
    {
        $days = max(1, min(365, $days));
        $typeOptions = function_exists('seo_profit_type_options') ? seo_profit_type_options() : ['all' => 'Semua SEO Page'];
        if (!isset($typeOptions[$type])) {
            $type = 'all';
        }
        $priorityOptions = ['all' => 'Semua Prioritas', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
        if (!isset($priorityOptions[$priority])) {
            $priority = 'all';
        }

        $journeySummary = function_exists('seo_journey_summary') ? seo_journey_summary($days, $type) : ['journeys' => []];
        $decisions = (array)(seo_money_settings(true)['decisions'] ?? []);
        $offerSummary = function_exists('offer_cta_lab_summary') ? offer_cta_lab_summary() : [];
        $bestVariant = is_array($offerSummary['best_candidate'] ?? null) ? (array)$offerSummary['best_candidate'] : null;
        $items = [];

        foreach ((array)($journeySummary['journeys'] ?? []) as $journey) {
            if (!is_array($journey)) {
                continue;
            }
            $pageId = seo_money_id((string)($journey['page_id'] ?? ''));
            $optimizer = seo_money_optimizer_item($journey, (array)($decisions[$pageId] ?? []), $bestVariant);
            if ($priority !== 'all' && (string)($optimizer['priority'] ?? '') !== $priority) {
                continue;
            }
            $items[] = $optimizer;
        }

        usort($items, static function (array $a, array $b): int {
            $weights = ['high' => 3, 'medium' => 2, 'low' => 1];
            $aw = $weights[(string)($a['priority'] ?? 'low')] ?? 0;
            $bw = $weights[(string)($b['priority'] ?? 'low')] ?? 0;
            return ($bw <=> $aw) ?: ((int)($b['money_score'] ?? 0) <=> (int)($a['money_score'] ?? 0)) ?: strcmp((string)($a['item']['title'] ?? ''), (string)($b['item']['title'] ?? ''));
        });

        $counts = ['total' => count($items), 'high' => 0, 'medium' => 0, 'low' => 0, 'done' => 0, 'optimizing' => 0];
        $stageCounts = [];
        $scoreSum = 0;
        foreach ($items as $optimizer) {
            $p = (string)($optimizer['priority'] ?? 'low');
            if (isset($counts[$p])) {
                $counts[$p]++;
            }
            $decisionStatus = (string)($optimizer['decision']['status'] ?? 'queued');
            if (isset($counts[$decisionStatus])) {
                $counts[$decisionStatus]++;
            }
            $stageKey = (string)($optimizer['stage']['key'] ?? 'monitor');
            $stageCounts[$stageKey] = ((int)($stageCounts[$stageKey] ?? 0)) + 1;
            $scoreSum += (int)($optimizer['money_score'] ?? 0);
        }

        arsort($stageCounts);
        $averageScore = $items ? (int)round($scoreSum / count($items)) : 0;
        $topItem = $items[0] ?? null;
        $focus = 'Pilih halaman SEO yang paling dekat ke lead/order, lalu kerjakan CTA, internal link, trust, dan offer-nya.';
        if (!empty($journeySummary['tracking_enabled']) && $counts['high'] > 0) {
            $focus = 'Ada money page prioritas tinggi. Kerjakan dulu karena paling dekat dengan lead, order, atau sinyal conversion.';
        } elseif (empty($journeySummary['tracking_enabled'])) {
            $focus = 'Aktifkan Lead Tracking agar rekomendasi money page bisa memakai data klik, lead, order, dan payment real.';
        } elseif ($averageScore < 45) {
            $focus = 'Belum banyak sinyal conversion. Mulai dari internal link, CTA, dan trust block untuk halaman SEO utama.';
        }

        return [
            'days' => $days,
            'type' => $type,
            'priority' => $priority,
            'type_options' => $typeOptions,
            'priority_options' => $priorityOptions,
            'tracking_enabled' => !empty($journeySummary['tracking_enabled']),
            'lead_tracking_available' => !empty($journeySummary['lead_tracking_available']),
            'money_page_score' => $averageScore,
            'top_focus' => $focus,
            'counts' => $counts,
            'stage_counts' => $stageCounts,
            'top_item' => $topItem,
            'items' => $items,
            'source_summary' => $journeySummary,
            'offer_summary' => $offerSummary,
            'generated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_money_export_csv')) {
    function seo_money_export_csv(array $items): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="seo-money-page-optimizer-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['page_id', 'priority', 'stage', 'type', 'title', 'url', 'money_score', 'journey_score', 'profit_score', 'clicks', 'leads', 'orders', 'cta_placement', 'cta_label', 'internal_link_1', 'trust_focus', 'decision']);
        foreach ($items as $optimizer) {
            $item = (array)($optimizer['item'] ?? []);
            $metrics = (array)($optimizer['metrics'] ?? []);
            $cta = (array)($optimizer['cta_plan'] ?? []);
            $links = (array)($optimizer['internal_links'] ?? []);
            $trust = (array)($optimizer['trust_plan'] ?? []);
            $decision = (array)($optimizer['decision'] ?? []);
            fputcsv($out, [
                (string)($optimizer['page_id'] ?? ''),
                (string)($optimizer['priority'] ?? ''),
                (string)($optimizer['stage']['label'] ?? ''),
                (string)($item['type_label'] ?? $item['type'] ?? ''),
                (string)($item['title'] ?? ''),
                (string)($item['url'] ?? ''),
                (int)($optimizer['money_score'] ?? 0),
                (int)($optimizer['journey_score'] ?? 0),
                (int)($optimizer['profit_score'] ?? 0),
                (int)($metrics['clicks'] ?? 0),
                (int)($metrics['leads'] ?? 0),
                (int)($metrics['orders'] ?? 0),
                (string)($cta['placement_label'] ?? ''),
                (string)($cta['cta_label'] ?? ''),
                (string)($links[0]['title'] ?? ''),
                (string)($trust[0]['label'] ?? ''),
                (string)($decision['status'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
