<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PROFIT ACTION DASHBOARD
|--------------------------------------------------------------------------
| A lightweight, rule-based dashboard that turns existing SEO, funnel,
| conversion, lead, order, and payment signals into daily profit actions.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('profit_action_storage_file')) {
    function profit_action_storage_file(): string
    {
        return STORAGE_PATH . '/profit-action-dashboard-state.json';
    }
}

if (!function_exists('profit_action_clean')) {
    function profit_action_clean(mixed $value, int $max = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('profit_action_safe_id')) {
    function profit_action_safe_id(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]+/', '-', $value) ?: '';
        $value = trim($value, '-');
        return $value !== '' ? substr($value, 0, 96) : md5((string)microtime(true));
    }
}

if (!function_exists('profit_action_default_state')) {
    function profit_action_default_state(): array
    {
        return [
            'completed' => [],
            'updated_at' => '',
        ];
    }
}

if (!function_exists('profit_action_normalize_state')) {
    function profit_action_normalize_state(array $state): array
    {
        $completed = [];
        foreach ((array)($state['completed'] ?? []) as $date => $ids) {
            $date = profit_action_clean($date, 20);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            foreach ((array)$ids as $id => $timestamp) {
                $cleanId = profit_action_safe_id((string)$id);
                if ($cleanId !== '') {
                    $completed[$date][$cleanId] = profit_action_clean($timestamp, 60) ?: date(DATE_ATOM);
                }
            }
        }

        return [
            'completed' => $completed,
            'updated_at' => profit_action_clean($state['updated_at'] ?? '', 60),
        ];
    }
}

if (!function_exists('profit_action_write_state')) {
    function profit_action_write_state(array $state, bool $throw = false): bool
    {
        $state = profit_action_normalize_state($state);
        $state['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(profit_action_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Status action profit belum bisa disimpan. Cek permission folder storage.');
            }
            return false;
        }

        @chmod(profit_action_storage_file(), 0644);
        return true;
    }
}

if (!function_exists('profit_action_state')) {
    function profit_action_state(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $file = profit_action_storage_file();
        if (!is_file($file)) {
            $cached = profit_action_default_state();
            profit_action_write_state($cached, false);
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = profit_action_default_state();
            profit_action_write_state($cached, false);
            return $cached;
        }

        $cached = profit_action_normalize_state($decoded);
        return $cached;
    }
}

if (!function_exists('profit_action_mark_completed')) {
    function profit_action_mark_completed(string $id, bool $completed = true): bool
    {
        $id = profit_action_safe_id($id);
        if ($id === '') {
            return false;
        }

        $state = profit_action_state();
        $today = date('Y-m-d');
        $state['completed'][$today] = (array)($state['completed'][$today] ?? []);

        if ($completed) {
            $state['completed'][$today][$id] = date(DATE_ATOM);
        } else {
            unset($state['completed'][$today][$id]);
        }

        return profit_action_write_state($state, true);
    }
}

if (!function_exists('profit_action_reset_today')) {
    function profit_action_reset_today(): bool
    {
        $state = profit_action_state();
        unset($state['completed'][date('Y-m-d')]);
        return profit_action_write_state($state, true);
    }
}

if (!function_exists('profit_action_is_completed')) {
    function profit_action_is_completed(string $id, ?array $state = null): bool
    {
        $state = $state ?? profit_action_state();
        $today = date('Y-m-d');
        $id = profit_action_safe_id($id);
        return isset($state['completed'][$today][$id]);
    }
}

if (!function_exists('profit_action_priority_meta')) {
    function profit_action_priority_meta(int $score): array
    {
        if ($score >= 85) {
            return ['label' => 'Dikerjakan sekarang', 'tone' => 'critical', 'rank' => 4];
        }
        if ($score >= 70) {
            return ['label' => 'Prioritas tinggi', 'tone' => 'high', 'rank' => 3];
        }
        if ($score >= 55) {
            return ['label' => 'Penting', 'tone' => 'medium', 'rank' => 2];
        }
        return ['label' => 'Pantau', 'tone' => 'monitor', 'rank' => 1];
    }
}

if (!function_exists('profit_action_focus_meta')) {
    function profit_action_focus_meta(string $focus): array
    {
        return match ($focus) {
            'money_leak' => ['label' => 'Money Leak', 'note' => 'Aksi yang paling dekat dengan order, pembayaran, atau omzet tertahan.'],
            'follow_up' => ['label' => 'Follow-up', 'note' => 'Aksi untuk menjaga lead agar tidak dingin.'],
            'seo_to_sales' => ['label' => 'SEO → Sales', 'note' => 'Aksi agar traffic organik diarahkan ke penawaran.'],
            'trust_cta' => ['label' => 'Trust & CTA', 'note' => 'Aksi untuk mengurangi ragu dan memperjelas langkah beli.'],
            'setup' => ['label' => 'Setup Profit', 'note' => 'Pondasi yang perlu dilengkapi agar website siap dipakai jualan.'],
            'scale' => ['label' => 'Scale Winner', 'note' => 'Aksi untuk memperbesar halaman/channel yang sudah punya sinyal bagus.'],
            default => ['label' => 'Profit Action', 'note' => 'Aksi praktis yang membantu website lebih dekat ke profit.'],
        };
    }
}

if (!function_exists('profit_action_make')) {
    function profit_action_make(array $data): array
    {
        $focus = (string)($data['focus'] ?? 'profit');
        $score = min(100, max(1, (int)($data['score'] ?? 50)));
        $title = profit_action_clean($data['title'] ?? 'Aksi profit', 140);
        $target = profit_action_clean($data['target'] ?? $title, 140);
        $source = profit_action_clean($data['source'] ?? 'Profit Engine', 80);
        $id = profit_action_safe_id((string)($data['id'] ?? md5($focus . '|' . $title . '|' . $target . '|' . $source)));
        $focusMeta = profit_action_focus_meta($focus);
        $priority = profit_action_priority_meta($score);

        return [
            'id' => $id,
            'focus' => $focus,
            'focus_label' => $focusMeta['label'],
            'focus_note' => $focusMeta['note'],
            'priority' => $priority,
            'score' => $score,
            'title' => $title,
            'target' => $target,
            'source' => $source,
            'impact' => profit_action_clean($data['impact'] ?? 'Membantu website lebih dekat ke lead, order, atau closing.', 260),
            'why' => profit_action_clean($data['why'] ?? 'Ada sinyal yang layak diprioritaskan hari ini.', 360),
            'steps' => array_values(array_filter(array_map(static fn($value): string => profit_action_clean($value, 160), (array)($data['steps'] ?? [])), static fn($value): bool => $value !== '')),
            'action_label' => profit_action_clean($data['action_label'] ?? 'Buka Area Terkait', 80),
            'action_url' => (string)($data['action_url'] ?? ''),
            'secondary_label' => profit_action_clean($data['secondary_label'] ?? '', 80),
            'secondary_url' => (string)($data['secondary_url'] ?? ''),
            'effort' => profit_action_clean($data['effort'] ?? '15-30 menit', 40),
            'script' => (string)($data['script'] ?? ''),
        ];
    }
}

if (!function_exists('profit_action_merge_rows')) {
    function profit_action_merge_rows(array $actions): array
    {
        $unique = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $id = (string)($action['id'] ?? '');
            if ($id === '') {
                continue;
            }
            if (!isset($unique[$id]) || (int)($action['score'] ?? 0) > (int)($unique[$id]['score'] ?? 0)) {
                $unique[$id] = $action;
            }
        }
        $actions = array_values($unique);
        usort($actions, static function (array $a, array $b): int {
            return ((int)($b['priority']['rank'] ?? 0) <=> (int)($a['priority']['rank'] ?? 0))
                ?: ((int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0));
        });
        return $actions;
    }
}

if (!function_exists('profit_action_readiness_score')) {
    function profit_action_readiness_score(array $report, array $launch, array $actionCenter, array $content): array
    {
        $leadEvents = (int)($report['lead']['events'] ?? 0);
        $highIntent = (int)($report['lead']['high_intent'] ?? 0) + (int)($report['lead']['whatsapp'] ?? 0) + (int)($report['lead']['inquiries'] ?? 0);
        $orders = (int)($report['order']['total'] ?? 0);
        $paidValue = (int)($report['sales']['paid_order_value'] ?? 0);
        $pendingProofs = (int)($report['payment']['pending_proofs'] ?? 0);
        $paymentWaiting = (int)($report['order']['payment_waiting'] ?? 0);
        $launchScore = (int)($launch['score'] ?? 0);
        $funnelScore = (int)($actionCenter['funnel_score']['total'] ?? 0);
        $contentRows = (array)($content['rows'] ?? []);
        $contentScore = 0;
        if ($contentRows) {
            $contentScore = (int)round(array_sum(array_map(static fn(array $row): int => (int)($row['performance_score'] ?? 0), $contentRows)) / max(1, count($contentRows)));
        }

        $traffic = $leadEvents >= 50 ? 100 : ($leadEvents >= 20 ? 75 : ($leadEvents >= 5 ? 55 : ($leadEvents > 0 ? 35 : 15)));
        $intent = $highIntent >= 20 ? 100 : ($highIntent >= 8 ? 75 : ($highIntent >= 2 ? 50 : ($highIntent > 0 ? 35 : 10)));
        $closing = $orders > 0 ? min(100, 45 + ($orders * 10)) : 15;
        $payment = $paidValue > 0 ? 90 : ($orders > 0 ? 50 : 20);
        if ($pendingProofs > 0 || $paymentWaiting > 0) {
            $payment = max(15, $payment - 15);
        }
        $readiness = min(100, max(0, (int)round(($launchScore * 0.30) + ($funnelScore * 0.25) + ($contentScore * 0.15) + ($traffic * 0.10) + ($intent * 0.10) + ($closing * 0.05) + ($payment * 0.05))));

        return [
            'total' => $readiness,
            'label' => $readiness >= 80 ? 'Siap Scale Profit' : ($readiness >= 60 ? 'Sudah Mulai Kuat' : ($readiness >= 40 ? 'Perlu Aksi Harian' : 'Fondasi Profit Awal')),
            'parts' => [
                'Launch readiness' => $launchScore,
                'Funnel readiness' => $funnelScore,
                'Content signal' => $contentScore,
                'Traffic signal' => $traffic,
                'Intent signal' => $intent,
                'Closing signal' => $closing,
                'Payment signal' => $payment,
            ],
        ];
    }
}

if (!function_exists('profit_action_generate_actions')) {
    function profit_action_generate_actions(array $report, array $launch, array $actionCenter, array $content, array $opportunity): array
    {
        $actions = [];
        $leadEvents = (int)($report['lead']['events'] ?? 0);
        $highIntent = (int)($report['lead']['high_intent'] ?? 0) + (int)($report['lead']['whatsapp'] ?? 0) + (int)($report['lead']['inquiries'] ?? 0);
        $orders = (int)($report['order']['total'] ?? 0);
        $newOrders = (int)($report['order']['new'] ?? 0);
        $paymentWaiting = (int)($report['order']['payment_waiting'] ?? 0);
        $pendingProofs = (int)($report['payment']['pending_proofs'] ?? 0);
        $leadToOrderRate = (float)($report['conversion']['lead_to_order_rate'] ?? 0);
        $avgOrder = (int)($report['sales']['average_order_value'] ?? 0);
        $moneyPotential = max(0, $paymentWaiting * max(1, $avgOrder));

        if ($pendingProofs > 0) {
            $actions[] = profit_action_make([
                'id' => 'review-bukti-pembayaran',
                'focus' => 'money_leak',
                'score' => 95,
                'title' => 'Review bukti pembayaran yang menunggu',
                'target' => $pendingProofs . ' bukti pembayaran',
                'source' => 'Payment Proof',
                'impact' => 'Mempercepat order yang sudah dekat closing agar tidak tertahan di admin.',
                'why' => $pendingProofs . ' bukti pembayaran masih menunggu review. Ini termasuk aksi paling dekat dengan omzet nyata.',
                'steps' => ['Buka daftar bukti pembayaran.', 'Validasi nominal, nama pengirim, dan invoice/order.', 'Update status lalu kirim kabar ke customer.'],
                'action_label' => 'Review Bukti Bayar',
                'action_url' => function_exists('url') ? url('admin/payment-proofs') : '',
                'secondary_label' => 'Cek Order',
                'secondary_url' => function_exists('url') ? url('admin/orders') : '',
                'effort' => '10-20 menit',
            ]);
        }

        if ($paymentWaiting > 0) {
            $actions[] = profit_action_make([
                'id' => 'kejar-order-menunggu-pembayaran',
                'focus' => 'money_leak',
                'score' => 92,
                'title' => 'Kejar order yang menunggu pembayaran',
                'target' => $paymentWaiting . ' order pending',
                'source' => 'Order & Payment',
                'impact' => $moneyPotential > 0 ? 'Potensi tertahan sekitar ' . (function_exists('rupiah') ? rupiah($moneyPotential) : (string)$moneyPotential) . '.' : 'Mengurangi order pending yang belum berubah jadi omzet.',
                'why' => 'Ada order yang sudah masuk tetapi belum selesai pembayaran. Jangan dibiarkan dingin terlalu lama.',
                'steps' => ['Cek order dengan status menunggu pembayaran.', 'Kirim reminder ramah via WhatsApp atau email.', 'Pastikan instruksi transfer/upload bukti jelas.'],
                'action_label' => 'Buka Order',
                'action_url' => function_exists('url') ? url('admin/orders?payment_status=' . rawurlencode('Menunggu Pembayaran')) : '',
                'secondary_label' => 'Reminder Pembayaran',
                'secondary_url' => function_exists('url') ? url('admin/payment-reminders') : '',
                'effort' => '15 menit',
                'script' => 'Halo Kak {nama}, izin mengingatkan order Kakak. Jika sudah transfer, Kakak bisa kirim bukti pembayaran ya. Kalau ada kendala, saya bantu cek sekarang.',
            ]);
        }

        if ($newOrders > 0) {
            $actions[] = profit_action_make([
                'id' => 'follow-up-order-baru',
                'focus' => 'follow_up',
                'score' => 86,
                'title' => 'Follow-up order baru sebelum dingin',
                'target' => $newOrders . ' order baru',
                'source' => 'Order',
                'impact' => 'Menjaga order baru tetap bergerak ke invoice, pembayaran, dan proses layanan.',
                'why' => 'Order baru butuh respons cepat. Delay terlalu lama bisa membuat calon customer berubah pikiran.',
                'steps' => ['Buka daftar order baru.', 'Pastikan data customer lengkap.', 'Kirim instruksi langkah berikutnya secara singkat.'],
                'action_label' => 'Buka Order Baru',
                'action_url' => function_exists('url') ? url('admin/orders?status=' . rawurlencode('Baru')) : '',
                'secondary_label' => 'Catat Follow-up',
                'secondary_url' => function_exists('url') ? url('admin/followups') : '',
                'effort' => '15-25 menit',
            ]);
        }

        if ($leadEvents >= 5 && ($orders <= 0 || $leadToOrderRate < 2.0)) {
            $actions[] = profit_action_make([
                'id' => 'perbaiki-offer-cta-lead-order-rendah',
                'focus' => 'trust_cta',
                'score' => 82,
                'title' => 'Perbaiki offer dan CTA karena lead belum jadi order',
                'target' => $leadEvents . ' lead/event, ' . $orders . ' order',
                'source' => 'Conversion Rate',
                'impact' => 'Membantu traffic dan lead yang sudah ada agar lebih mudah lanjut ke chat, form, checkout, atau order.',
                'why' => 'Sinyal lead sudah ada, tapi rasio order masih rendah. Biasanya perlu CTA lebih jelas, trust lebih kuat, atau jalur order lebih pendek.',
                'steps' => ['Cek homepage dan money page utama.', 'Tambahkan CTA yang spesifik: chat, konsultasi, checkout, atau katalog.', 'Pasang FAQ/testimoni/garansi dekat area keputusan.'],
                'action_label' => 'Atur Trust & CTA',
                'action_url' => function_exists('url') ? url('admin/trust-conversion') : '',
                'secondary_label' => 'Lihat Opportunity',
                'secondary_url' => function_exists('url') ? url('admin/conversion-opportunities') : '',
                'effort' => '30-45 menit',
            ]);
        }

        if ($leadEvents <= 0) {
            $actions[] = profit_action_make([
                'id' => 'aktifkan-tracking-lead-dan-cta',
                'focus' => 'setup',
                'score' => 78,
                'title' => 'Pastikan tombol lead dan tracking sudah aktif',
                'target' => 'Fondasi tracking profit',
                'source' => 'Analytics',
                'impact' => 'Agar keputusan bisnis tidak hanya berdasarkan feeling, tapi dari klik, lead, dan order.',
                'why' => 'Belum ada event lead pada rentang ini. Website perlu sinyal data untuk tahu halaman mana yang mendekati profit.',
                'steps' => ['Cek tombol WhatsApp/form/checkout di halaman utama.', 'Pastikan analytics dan event tracking aktif.', 'Lakukan test klik dari halaman publik.'],
                'action_label' => 'Buka Analytics',
                'action_url' => function_exists('url') ? url('admin/analytics') : '',
                'secondary_label' => 'Cek Form',
                'secondary_url' => function_exists('url') ? url('admin/forms') : '',
                'effort' => '20-30 menit',
            ]);
        }

        foreach (array_slice((array)($actionCenter['rows'] ?? []), 0, 6) as $row) {
            $stage = (string)($row['stage'] ?? 'intent');
            $focus = match ($stage) {
                'traffic' => 'seo_to_sales',
                'order', 'payment', 'closing' => 'money_leak',
                'inquiry' => 'follow_up',
                'scale' => 'scale',
                default => 'trust_cta',
            };
            $actions[] = profit_action_make([
                'id' => 'sales-action-' . (string)($row['id'] ?? md5(json_encode($row) ?: '')),
                'focus' => $focus,
                'score' => min(94, max(50, (int)($row['score'] ?? 60))),
                'title' => (string)($row['title'] ?? 'Eksekusi funnel action'),
                'target' => (string)($row['target'] ?? 'Funnel'),
                'source' => (string)($row['source'] ?? 'Funnel Action Center'),
                'impact' => 'Mengubah insight funnel menjadi tindakan yang bisa langsung dikerjakan admin.',
                'why' => (string)($row['reason'] ?? 'Ada peluang funnel yang perlu dieksekusi.'),
                'steps' => array_slice((array)($row['checklist'] ?? []), 0, 4),
                'action_label' => (string)($row['action_label'] ?? 'Buka Action'),
                'action_url' => (string)($row['action_url'] ?? (function_exists('url') ? url('admin/funnel-action-center') : '')),
                'secondary_label' => 'Buka Action Center',
                'secondary_url' => function_exists('url') ? url('admin/funnel-action-center') : '',
                'effort' => '15-45 menit',
                'script' => (string)($row['whatsapp'] ?? ''),
            ]);
        }

        foreach (array_slice((array)($opportunity['opportunities'] ?? []), 0, 4) as $item) {
            $action = (array)($item['action'] ?? []);
            $category = (string)($item['category'] ?? 'cta_gap');
            $actions[] = profit_action_make([
                'id' => 'opportunity-' . (string)($item['id'] ?? md5(json_encode($item) ?: '')),
                'focus' => in_array($category, ['support_gap', 'seo_to_conversion'], true) ? 'seo_to_sales' : 'trust_cta',
                'score' => min(88, max(52, (int)($item['priority_score'] ?? 62))),
                'title' => (string)($action['title'] ?? $item['title'] ?? 'Poles peluang konversi'),
                'target' => (string)($item['page_title'] ?? 'Halaman prioritas'),
                'source' => 'Conversion Opportunity',
                'impact' => 'Meningkatkan peluang halaman yang sudah punya sinyal agar tidak berhenti sebagai kunjungan biasa.',
                'why' => (string)($action['body'] ?? $item['reason'] ?? 'Ada gap CTA, offer, atau support content.'),
                'steps' => (array)($action['checklist'] ?? ['Cek halaman prioritas.', 'Perjelas CTA.', 'Tambahkan trust signal.']),
                'action_label' => (string)($action['label'] ?? 'Buka Opportunity'),
                'action_url' => (string)($action['url'] ?? (function_exists('url') ? url('admin/conversion-opportunities') : '')),
                'secondary_label' => 'Content Performance',
                'secondary_url' => function_exists('url') ? url('admin/content-performance') : '',
                'effort' => '30-60 menit',
            ]);
        }

        foreach (array_slice((array)($launch['next_items'] ?? []), 0, 3) as $item) {
            $actions[] = profit_action_make([
                'id' => 'launch-' . (string)($item['key'] ?? md5(json_encode($item) ?: '')),
                'focus' => 'setup',
                'score' => min(80, 45 + (int)($item['weight'] ?? 8) * 3),
                'title' => (string)($item['label'] ?? 'Lengkapi readiness website'),
                'target' => (string)($item['group'] ?? 'Setup'),
                'source' => 'Launch Readiness',
                'impact' => 'Merapikan pondasi agar traffic dari SEO, iklan, atau promosi tidak bocor.',
                'why' => (string)($item['description'] ?? 'Checklist ini belum lengkap.'),
                'steps' => ['Buka menu yang disarankan.', 'Lengkapi field atau konten penting.', 'Cek ulang Launch Readiness setelah selesai.'],
                'action_label' => (string)($item['action'] ?? 'Lengkapi'),
                'action_url' => (string)($item['href'] ?? (function_exists('url') ? url('admin/launch-readiness') : '')),
                'secondary_label' => 'Cek Readiness',
                'secondary_url' => function_exists('url') ? url('admin/launch-readiness') : '',
                'effort' => '15-40 menit',
            ]);
        }

        foreach (array_slice((array)($content['rows'] ?? []), 0, 6) as $row) {
            $bucket = (string)($row['bucket']['key'] ?? 'monitor');
            if (!in_array($bucket, ['scale_winner', 'cta_polish', 'build_support', 'seo_boost'], true)) {
                continue;
            }
            $focus = $bucket === 'scale_winner' ? 'scale' : ($bucket === 'build_support' || $bucket === 'seo_boost' ? 'seo_to_sales' : 'trust_cta');
            $actions[] = profit_action_make([
                'id' => 'content-' . (string)($row['id'] ?? md5(json_encode($row) ?: '')),
                'focus' => $focus,
                'score' => min(84, max(50, (int)($row['performance_score'] ?? 55))),
                'title' => (string)($row['recommendation']['title'] ?? $row['bucket']['note'] ?? 'Poles halaman prioritas'),
                'target' => (string)($row['title'] ?? 'Halaman'),
                'source' => 'Content Performance',
                'impact' => 'Mengarahkan performa konten agar lebih dekat ke CTA, lead, atau order.',
                'why' => 'Halaman ini punya sinyal performa: interaksi ' . (int)($row['metrics']['interactions'] ?? 0) . ', intent ' . (int)($row['metrics']['high_intent'] ?? 0) . ', order ' . (int)($row['metrics']['orders'] ?? 0) . '.',
                'steps' => ['Cek judul, opening, dan CTA halaman.', 'Tambahkan internal link ke produk/jasa relevan.', 'Update FAQ atau trust signal bila perlu.'],
                'action_label' => 'Buka Halaman',
                'action_url' => (string)($row['edit_url'] ?? (function_exists('url') ? url('admin/content-performance') : '')),
                'secondary_label' => 'Lihat Performa',
                'secondary_url' => function_exists('url') ? url('admin/content-performance?q=' . rawurlencode((string)($row['title'] ?? ''))) : '',
                'effort' => '30 menit',
            ]);
        }

        return profit_action_merge_rows($actions);
    }
}

if (!function_exists('profit_action_funnel_map')) {
    function profit_action_funnel_map(array $report): array
    {
        $leads = (int)($report['lead']['events'] ?? 0);
        $intent = (int)($report['lead']['high_intent'] ?? 0) + (int)($report['lead']['whatsapp'] ?? 0) + (int)($report['lead']['inquiries'] ?? 0);
        $orders = (int)($report['order']['total'] ?? 0);
        $paidValue = (int)($report['sales']['paid_order_value'] ?? 0);
        $paymentWaiting = (int)($report['order']['payment_waiting'] ?? 0);
        $completed = (int)($report['order']['completed'] ?? 0);

        return [
            ['stage' => 'Traffic', 'value' => $leads, 'label' => 'Lead/event', 'note' => $leads > 0 ? 'Sudah ada sinyal traffic.' : 'Butuh test CTA dan tracking.', 'tone' => $leads > 0 ? 'ok' : 'warning'],
            ['stage' => 'Intent', 'value' => $intent, 'label' => 'High intent', 'note' => $intent > 0 ? 'Ada calon customer yang mulai tertarik.' : 'CTA dan offer perlu dibuat lebih jelas.', 'tone' => $intent > 0 ? 'ok' : 'warning'],
            ['stage' => 'Order', 'value' => $orders, 'label' => 'Order', 'note' => $orders > 0 ? 'Sudah ada transaksi masuk.' : 'Lead belum menjadi order.', 'tone' => $orders > 0 ? 'ok' : 'warning'],
            ['stage' => 'Payment', 'value' => $paymentWaiting, 'label' => 'Menunggu bayar', 'note' => $paymentWaiting > 0 ? 'Ada potensi tertahan.' : 'Tidak ada payment leak besar dari status order.', 'tone' => $paymentWaiting > 0 ? 'danger' : 'ok'],
            ['stage' => 'Closing', 'value' => $completed, 'label' => 'Selesai/deal', 'note' => $paidValue > 0 ? 'Sudah ada nilai terbayar.' : 'Perlu dorong closing dan pembayaran.', 'tone' => $paidValue > 0 ? 'ok' : 'neutral'],
        ];
    }
}

if (!function_exists('profit_action_dashboard_summary')) {
    function profit_action_dashboard_summary(int $days = 30, array $filters = []): array
    {
        $report = function_exists('report_dashboard_summary') ? report_dashboard_summary($days, $filters) : [];
        $launch = function_exists('launch_readiness_report') ? launch_readiness_report() : ['score' => 0, 'next_items' => [], 'counts' => []];
        $actionCenter = function_exists('sales_action_center_summary') ? sales_action_center_summary($days, $filters) : ['metrics' => [], 'rows' => [], 'funnel_score' => []];
        $content = function_exists('content_performance_summary') ? content_performance_summary($days, $filters) : ['metrics' => [], 'rows' => []];
        $opportunity = function_exists('conversion_opportunity_summary') ? conversion_opportunity_summary($days, $filters) : ['metrics' => [], 'opportunities' => []];
        $readiness = profit_action_readiness_score($report, $launch, $actionCenter, $content);
        $actions = profit_action_generate_actions($report, $launch, $actionCenter, $content, $opportunity);
        $state = profit_action_state();

        $completed = 0;
        foreach ($actions as $idx => $action) {
            $isDone = profit_action_is_completed((string)($action['id'] ?? ''), $state);
            $actions[$idx]['completed'] = $isDone;
            if ($isDone) {
                $completed++;
            }
        }

        $pendingActions = array_values(array_filter($actions, static fn(array $action): bool => empty($action['completed'])));
        $topContent = [];
        foreach ((array)($content['rows'] ?? []) as $row) {
            if ((int)($row['metrics']['interactions'] ?? 0) > 0 || in_array((string)($row['bucket']['key'] ?? ''), ['scale_winner', 'cta_polish', 'build_support', 'seo_boost'], true)) {
                $topContent[] = $row;
            }
        }

        return [
            'generated_at' => date('c'),
            'days' => $days,
            'filters' => $filters,
            'readiness' => $readiness,
            'report' => $report,
            'launch' => $launch,
            'action_center' => [
                'metrics' => (array)($actionCenter['metrics'] ?? []),
                'funnel_score' => (array)($actionCenter['funnel_score'] ?? []),
            ],
            'content' => [
                'metrics' => (array)($content['metrics'] ?? []),
                'top_rows' => array_slice($topContent, 0, 8),
            ],
            'opportunity' => [
                'metrics' => (array)($opportunity['metrics'] ?? []),
                'top_items' => array_slice((array)($opportunity['opportunities'] ?? []), 0, 8),
            ],
            'actions' => $actions,
            'today_plan' => array_slice($pendingActions, 0, 7),
            'completed_today' => $completed,
            'funnel_map' => profit_action_funnel_map($report),
        ];
    }
}

if (!function_exists('profit_action_filter_actions')) {
    function profit_action_filter_actions(array $actions, string $focus = 'all', string $q = '', bool $showDone = true): array
    {
        $q = trim($q);
        $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
        return array_values(array_filter($actions, static function (array $action) use ($focus, $needle, $showDone): bool {
            if (!$showDone && !empty($action['completed'])) {
                return false;
            }
            if ($focus !== 'all' && (string)($action['focus'] ?? '') !== $focus) {
                return false;
            }
            if ($needle !== '') {
                $haystack = implode(' ', [
                    $action['focus_label'] ?? '',
                    $action['priority']['label'] ?? '',
                    $action['title'] ?? '',
                    $action['target'] ?? '',
                    $action['source'] ?? '',
                    $action['impact'] ?? '',
                    $action['why'] ?? '',
                    implode(' ', (array)($action['steps'] ?? [])),
                ]);
                $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
                if (!str_contains($haystack, $needle)) {
                    return false;
                }
            }
            return true;
        }));
    }
}

if (!function_exists('profit_action_export_csv')) {
    function profit_action_export_csv(array $actions): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="profit-action-dashboard-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['score', 'priority', 'focus', 'status', 'title', 'target', 'source', 'impact', 'why', 'steps', 'action_url'], ',', '"', '\\', "\n");
        foreach ($actions as $action) {
            fputcsv($out, [
                (int)($action['score'] ?? 0),
                (string)($action['priority']['label'] ?? ''),
                (string)($action['focus_label'] ?? ''),
                !empty($action['completed']) ? 'Selesai' : 'Belum',
                (string)($action['title'] ?? ''),
                (string)($action['target'] ?? ''),
                (string)($action['source'] ?? ''),
                (string)($action['impact'] ?? ''),
                (string)($action['why'] ?? ''),
                implode(' | ', (array)($action['steps'] ?? [])),
                (string)($action['action_url'] ?? ''),
            ], ',', '"', '\\', "\n");
        }
        fclose($out);
        exit;
    }
}
