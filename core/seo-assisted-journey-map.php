<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO ASSISTED CONVERSION JOURNEY MAP
|--------------------------------------------------------------------------
| Connects SEO pages/articles to CTA clicks, leads, order/payment signals,
| and next actions by reading the existing Lead Tracking and SEO Profit
| Attribution bridge. This is a decision layer, not a duplicate tracker.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('seo_journey_clean')) {
    function seo_journey_clean(mixed $value, int $max = 180): string
    {
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

if (!function_exists('seo_journey_id')) {
    function seo_journey_id(string $value = ''): string
    {
        if (function_exists('seo_profit_id')) {
            return seo_profit_id($value);
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-\/]+/', '-', $value) ?: '';
        $value = trim($value, '-/');

        return substr($value, 0, 140);
    }
}

if (!function_exists('seo_journey_storage_file')) {
    function seo_journey_storage_file(): string
    {
        return STORAGE_PATH . '/seo-assisted-journey-decisions.json';
    }
}

if (!function_exists('seo_journey_decision_options')) {
    function seo_journey_decision_options(): array
    {
        return [
            'monitor' => 'Pantau dulu',
            'add_cta' => 'Tambah CTA',
            'improve_offer' => 'Perbaiki offer',
            'add_trust' => 'Tambah trust/FAQ',
            'followup_leads' => 'Follow-up lead',
            'scale_page' => 'Scale halaman',
            'refresh_seo' => 'Update SEO/konten',
        ];
    }
}

if (!function_exists('seo_journey_default_settings')) {
    function seo_journey_default_settings(): array
    {
        return [
            'decisions' => [],
            'updated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_journey_normalize_decision')) {
    function seo_journey_normalize_decision(array $decision): array
    {
        $options = seo_journey_decision_options();
        $status = (string)($decision['status'] ?? 'monitor');
        if (!isset($options[$status])) {
            $status = 'monitor';
        }

        return [
            'page_id' => seo_journey_id((string)($decision['page_id'] ?? '')),
            'status' => $status,
            'note' => seo_journey_clean($decision['note'] ?? '', 360),
            'updated_at' => seo_journey_clean($decision['updated_at'] ?? date(DATE_ATOM), 80) ?: date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_journey_normalize_settings')) {
    function seo_journey_normalize_settings(array $settings): array
    {
        $settings = array_merge(seo_journey_default_settings(), $settings);
        $decisions = [];

        foreach ((array)($settings['decisions'] ?? []) as $decision) {
            if (!is_array($decision)) {
                continue;
            }
            $normalized = seo_journey_normalize_decision($decision);
            if ((string)$normalized['page_id'] === '') {
                continue;
            }
            $decisions[(string)$normalized['page_id']] = $normalized;
        }

        return [
            'decisions' => $decisions,
            'updated_at' => seo_journey_clean($settings['updated_at'] ?? date(DATE_ATOM), 80),
        ];
    }
}

if (!function_exists('seo_journey_settings')) {
    function seo_journey_settings(bool $fresh = false): array
    {
        static $cached = null;
        if ($cached !== null && !$fresh) {
            return $cached;
        }

        $file = seo_journey_storage_file();
        if (!is_file($file)) {
            $cached = seo_journey_normalize_settings(seo_journey_default_settings());
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = seo_journey_normalize_settings(seo_journey_default_settings());
            return $cached;
        }

        $cached = seo_journey_normalize_settings($decoded);
        return $cached;
    }
}

if (!function_exists('seo_journey_write_settings')) {
    function seo_journey_write_settings(array $settings, bool $throw = false): bool
    {
        $settings = seo_journey_normalize_settings($settings);
        $settings['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(seo_journey_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Catatan Journey Map belum bisa disimpan. Cek permission storage.');
            }
            return false;
        }

        @chmod(seo_journey_storage_file(), 0644);

        if (function_exists('activity_log_record')) {
            activity_log_record('update', 'seo-assisted-journey', null, 'Menyimpan keputusan SEO Assisted Conversion Journey Map.');
        }

        return true;
    }
}

if (!function_exists('seo_journey_update_decision')) {
    function seo_journey_update_decision(string $pageId, string $status, string $note = ''): bool
    {
        $pageId = seo_journey_id($pageId);
        if ($pageId === '') {
            throw new RuntimeException('ID halaman journey tidak valid.');
        }

        $settings = seo_journey_settings(true);
        $settings['decisions'][$pageId] = seo_journey_normalize_decision([
            'page_id' => $pageId,
            'status' => $status,
            'note' => $note,
            'updated_at' => date(DATE_ATOM),
        ]);

        return seo_journey_write_settings($settings, true);
    }
}

if (!function_exists('seo_journey_reset_decisions')) {
    function seo_journey_reset_decisions(): void
    {
        if (is_file(seo_journey_storage_file())) {
            @unlink(seo_journey_storage_file());
        }

        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'seo-assisted-journey', null, 'Reset catatan SEO Assisted Conversion Journey Map.');
        }
    }
}

if (!function_exists('seo_journey_stage_status')) {
    function seo_journey_stage_status(int $count, int $strong = 3): string
    {
        if ($count >= $strong) {
            return 'strong';
        }
        if ($count > 0) {
            return 'active';
        }
        return 'empty';
    }
}

if (!function_exists('seo_journey_bottleneck')) {
    function seo_journey_bottleneck(array $item, array $metrics): array
    {
        $views = (int)($metrics['views'] ?? 0);
        $clicks = (int)($metrics['clicks'] ?? 0);
        $leads = (int)($metrics['leads'] ?? 0);
        $orders = (int)($metrics['orders'] ?? 0);
        $payments = (int)($metrics['payments'] ?? 0);
        $seoScore = (int)($item['score'] ?? 0);

        if (($views + $clicks + $leads + $orders + $payments) <= 0) {
            return [
                'key' => 'no_signal',
                'label' => 'Belum ada sinyal',
                'tone' => 'monitor',
                'title' => 'Beri jalan masuk ke halaman ini',
                'text' => 'Halaman sudah ada, tapi belum terbaca klik/lead/order dari Tracking Lead. Cek indexing, internal link, dan CTA awal.',
                'action_url' => 'admin/universal-seo',
            ];
        }

        if ($clicks <= 0 && $leads <= 0 && $orders <= 0) {
            return [
                'key' => 'view_no_click',
                'label' => 'Dibaca, belum diklik',
                'tone' => 'place',
                'title' => 'Pasang CTA yang lebih jelas',
                'text' => 'Ada sinyal halaman, tapi belum ada klik CTA/aksi kuat. Tambahkan CTA di atas, tengah, dan bawah konten.',
                'action_url' => 'admin/cta-placement',
            ];
        }

        if ($clicks > 0 && $leads <= 0 && $orders <= 0) {
            return [
                'key' => 'click_no_lead',
                'label' => 'Klik ada, lead belum',
                'tone' => 'improve',
                'title' => 'Perbaiki offer dan form',
                'text' => 'Pengunjung sudah klik, tapi belum menjadi lead. Cek headline CTA, form, trust block, dan alasan menghubungi.',
                'action_url' => 'admin/offer-cta-testing',
            ];
        }

        if ($leads > 0 && $orders <= 0 && $payments <= 0) {
            return [
                'key' => 'lead_no_order',
                'label' => 'Lead ada, order belum',
                'tone' => 'followup',
                'title' => 'Kuatkan follow-up dan closing',
                'text' => 'Halaman sudah menghasilkan lead. Prioritas berikutnya adalah follow-up, proof, garansi, dan penawaran yang lebih jelas.',
                'action_url' => 'admin/followups',
            ];
        }

        if ($orders > 0 || $payments > 0) {
            return [
                'key' => 'order_ready',
                'label' => 'Sudah sampai order',
                'tone' => 'scale',
                'title' => 'Scale halaman pemenang',
                'text' => 'Journey sudah sampai order/payment. Perkuat internal link, buat artikel pendukung, dan masukkan ke campaign profit.',
                'action_url' => 'admin/profit-playbook',
            ];
        }

        if ($seoScore > 0 && $seoScore < 70) {
            return [
                'key' => 'seo_needs_refresh',
                'label' => 'SEO perlu dipoles',
                'tone' => 'seo',
                'title' => 'Update konten SEO',
                'text' => 'Sinyal journey belum kuat dan skor SEO masih bisa dipoles. Perbaiki title, meta, struktur, dan internal link.',
                'action_url' => 'admin/universal-seo',
            ];
        }

        return [
            'key' => 'monitor',
            'label' => 'Pantau dulu',
            'tone' => 'monitor',
            'title' => 'Pantau sinyal berikutnya',
            'text' => 'Journey mulai terbaca. Pantau beberapa hari lagi sebelum mengambil keputusan besar.',
            'action_url' => 'admin/seo-profit-attribution',
        ];
    }
}

if (!function_exists('seo_journey_build')) {
    function seo_journey_build(array $result, array $decision = []): array
    {
        $item = (array)($result['item'] ?? []);
        $metrics = (array)($result['metrics'] ?? []);
        $clicks = (int)($metrics['clicks'] ?? 0);
        $leads = (int)($metrics['leads'] ?? 0);
        $orders = (int)($metrics['orders'] ?? 0);
        $payments = (int)($metrics['payments'] ?? 0);
        $views = (int)($metrics['views'] ?? 0);
        $events = (int)($metrics['events'] ?? 0);
        $seoScore = (int)($item['score'] ?? 0);
        $pageId = (string)($item['page_id'] ?? '');
        $bottleneck = seo_journey_bottleneck($item, $metrics);

        $stageScore = min(100,
            min(30, max(8, (int)round($seoScore * 0.3))) +
            min(20, ($views > 0 ? 8 : 0) + ($clicks * 4)) +
            min(25, ($leads * 8) + ((int)($metrics['lead_rate'] ?? 0) > 0 ? 5 : 0)) +
            min(25, (($orders + $payments) * 10) + ((int)($metrics['order_rate'] ?? 0) > 0 ? 5 : 0))
        );

        $stages = [
            [
                'key' => 'seo_page',
                'label' => 'SEO Page',
                'count' => $events > 0 ? max(1, $views) : 0,
                'status' => $events > 0 ? 'active' : 'empty',
                'hint' => $events > 0 ? 'Halaman sudah punya sinyal Tracking Lead.' : 'Butuh traffic/internal link agar mulai terbaca.',
            ],
            [
                'key' => 'cta_click',
                'label' => 'CTA Click',
                'count' => $clicks,
                'status' => seo_journey_stage_status($clicks),
                'hint' => $clicks > 0 ? 'Pengunjung mulai klik aksi.' : 'CTA belum cukup kuat/terlihat.',
            ],
            [
                'key' => 'lead',
                'label' => 'Lead',
                'count' => $leads,
                'status' => seo_journey_stage_status($leads),
                'hint' => $leads > 0 ? 'Halaman sudah menghasilkan prospek.' : 'Perlu offer/form/trust lebih kuat.',
            ],
            [
                'key' => 'order_payment',
                'label' => 'Order / Payment',
                'count' => $orders + $payments,
                'status' => seo_journey_stage_status($orders + $payments, 2),
                'hint' => ($orders + $payments) > 0 ? 'Journey sudah menyentuh revenue.' : 'Perkuat follow-up dan closing.',
            ],
        ];

        return [
            'page_id' => $pageId,
            'item' => $item,
            'metrics' => $metrics,
            'stages' => $stages,
            'journey_score' => $stageScore,
            'profit_score' => (int)($result['profit_score'] ?? 0),
            'bottleneck' => $bottleneck,
            'decision' => $decision,
            'recommendation' => [
                'title' => (string)($bottleneck['title'] ?? 'Pantau journey'),
                'text' => (string)($bottleneck['text'] ?? ''),
                'action_url' => (string)($bottleneck['action_url'] ?? 'admin/seo-profit-attribution'),
            ],
            'recent_events' => array_slice((array)($metrics['recent_events'] ?? []), 0, 4),
        ];
    }
}

if (!function_exists('seo_journey_summary')) {
    function seo_journey_summary(int $days = 30, string $type = 'all'): array
    {
        $days = max(1, min(365, $days));
        $typeOptions = function_exists('seo_profit_type_options') ? seo_profit_type_options() : ['all' => 'Semua SEO Page'];
        if (!isset($typeOptions[$type])) {
            $type = 'all';
        }

        $seoProfit = function_exists('seo_profit_summary') ? seo_profit_summary($days, $type) : [
            'results' => [],
            'tracking_enabled' => false,
            'lead_tracking_available' => false,
        ];
        $decisions = (array)(seo_journey_settings(true)['decisions'] ?? []);
        $journeys = [];

        foreach ((array)($seoProfit['results'] ?? []) as $result) {
            if (!is_array($result)) {
                continue;
            }
            $item = (array)($result['item'] ?? []);
            $pageId = (string)($item['page_id'] ?? '');
            $journeys[] = seo_journey_build($result, (array)($decisions[$pageId] ?? []));
        }

        usort($journeys, static function (array $a, array $b): int {
            $am = (array)($a['metrics'] ?? []);
            $bm = (array)($b['metrics'] ?? []);
            $aImpact = ((int)($am['orders'] ?? 0) * 20) + ((int)($am['payments'] ?? 0) * 20) + ((int)($am['leads'] ?? 0) * 10) + ((int)($am['clicks'] ?? 0) * 3) + (int)($a['journey_score'] ?? 0);
            $bImpact = ((int)($bm['orders'] ?? 0) * 20) + ((int)($bm['payments'] ?? 0) * 20) + ((int)($bm['leads'] ?? 0) * 10) + ((int)($bm['clicks'] ?? 0) * 3) + (int)($b['journey_score'] ?? 0);
            return ($bImpact <=> $aImpact) ?: strcmp((string)($a['item']['title'] ?? ''), (string)($b['item']['title'] ?? ''));
        });

        $totals = [
            'events' => 0,
            'views' => 0,
            'clicks' => 0,
            'leads' => 0,
            'orders' => 0,
            'payments' => 0,
        ];
        $stageCounts = [
            'seo_page' => 0,
            'cta_click' => 0,
            'lead' => 0,
            'order_payment' => 0,
        ];
        $bottlenecks = [];
        $scoreSum = 0;

        foreach ($journeys as $journey) {
            $metrics = (array)($journey['metrics'] ?? []);
            foreach ($totals as $key => $_) {
                $totals[$key] += (int)($metrics[$key] ?? 0);
            }
            foreach ((array)($journey['stages'] ?? []) as $stage) {
                if ((int)($stage['count'] ?? 0) > 0 && isset($stageCounts[(string)($stage['key'] ?? '')])) {
                    $stageCounts[(string)$stage['key']]++;
                }
            }
            $bKey = (string)($journey['bottleneck']['key'] ?? 'monitor');
            $bottlenecks[$bKey] = ((int)($bottlenecks[$bKey] ?? 0)) + 1;
            $scoreSum += (int)($journey['journey_score'] ?? 0);
        }

        arsort($bottlenecks);
        $averageScore = $journeys ? (int)round($scoreSum / count($journeys)) : 0;
        $topJourney = $journeys[0] ?? null;
        $focus = 'Hubungkan halaman SEO, CTA, lead, order, dan payment dalam satu journey agar keputusan optimasi lebih jelas.';

        if (empty($seoProfit['tracking_enabled'])) {
            $focus = 'Aktifkan Lead Tracking agar journey dari SEO ke lead/order bisa terbaca.';
        } elseif ($totals['orders'] > 0 || $totals['payments'] > 0) {
            $focus = 'Ada journey yang sudah sampai order/payment. Prioritaskan scale halaman pemenang.';
        } elseif ($totals['leads'] > 0) {
            $focus = 'Ada halaman SEO yang sudah membawa lead. Perkuat follow-up dan offer agar naik ke order.';
        } elseif ($totals['clicks'] > 0) {
            $focus = 'Klik CTA sudah ada, tapi lead/order belum kuat. Perbaiki offer, form, dan trust block.';
        } elseif ($totals['events'] > 0) {
            $focus = 'Halaman mulai terbaca, tapi CTA belum cukup menghasilkan aksi. Rapikan placement CTA.';
        }

        return [
            'days' => $days,
            'type' => $type,
            'type_options' => $typeOptions,
            'tracking_enabled' => !empty($seoProfit['tracking_enabled']),
            'lead_tracking_available' => !empty($seoProfit['lead_tracking_available']),
            'total_journeys' => count($journeys),
            'totals' => $totals,
            'stage_counts' => $stageCounts,
            'bottlenecks' => $bottlenecks,
            'average_journey_score' => $averageScore,
            'top_focus' => $focus,
            'top_journey' => $topJourney,
            'journeys' => $journeys,
            'source_summary' => $seoProfit,
            'generated_at' => date(DATE_ATOM),
        ];
    }
}

if (!function_exists('seo_journey_export_csv')) {
    function seo_journey_export_csv(array $journeys): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="seo-assisted-journey-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['page_id', 'type', 'title', 'url', 'events', 'views', 'clicks', 'leads', 'orders', 'payments', 'journey_score', 'bottleneck', 'next_action', 'decision']);
        foreach ($journeys as $journey) {
            $item = (array)($journey['item'] ?? []);
            $metrics = (array)($journey['metrics'] ?? []);
            $bottleneck = (array)($journey['bottleneck'] ?? []);
            $decision = (array)($journey['decision'] ?? []);
            fputcsv($out, [
                (string)($journey['page_id'] ?? ''),
                (string)($item['type_label'] ?? $item['type'] ?? ''),
                (string)($item['title'] ?? ''),
                (string)($item['url'] ?? ''),
                (int)($metrics['events'] ?? 0),
                (int)($metrics['views'] ?? 0),
                (int)($metrics['clicks'] ?? 0),
                (int)($metrics['leads'] ?? 0),
                (int)($metrics['orders'] ?? 0),
                (int)($metrics['payments'] ?? 0),
                (int)($journey['journey_score'] ?? 0),
                (string)($bottleneck['label'] ?? ''),
                (string)($bottleneck['title'] ?? ''),
                (string)($decision['status'] ?? ''),
            ]);
        }
        fclose($out);
        exit;
    }
}
