<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SALES FUNNEL ACTION CENTER
|--------------------------------------------------------------------------
| Turns funnel bottlenecks and conversion opportunities into practical UMKM
| action scripts: WhatsApp follow-up, offer polish, payment recovery, and
| content-to-conversion moves.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('sales_action_clean')) {
    function sales_action_clean(string $value, int $max = 180): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max, 'UTF-8') : substr($value, 0, $max);
    }
}

if (!function_exists('sales_action_priority_meta')) {
    function sales_action_priority_meta(string $priority): array
    {
        return match (strtolower($priority)) {
            'kritis', 'critical' => ['key' => 'critical', 'label' => 'Kritis', 'tone' => 'error', 'score' => 92],
            'tinggi', 'high' => ['key' => 'high', 'label' => 'Tinggi', 'tone' => 'warning', 'score' => 78],
            'scale' => ['key' => 'scale', 'label' => 'Scale', 'tone' => 'success', 'score' => 72],
            'sedang', 'medium' => ['key' => 'medium', 'label' => 'Sedang', 'tone' => 'info', 'score' => 58],
            default => ['key' => 'monitor', 'label' => 'Pantau', 'tone' => 'neutral', 'score' => 38],
        };
    }
}

if (!function_exists('sales_action_stage_label')) {
    function sales_action_stage_label(string $stage): string
    {
        return match ($stage) {
            'traffic' => 'Traffic → Lead',
            'intent' => 'Intent → Inquiry',
            'inquiry' => 'Inquiry → Order',
            'order' => 'Order → Payment',
            'payment' => 'Payment → Closing',
            'closing' => 'Closing → Repeat/Scale',
            'scale' => 'Scale Winner',
            default => 'Funnel Action',
        };
    }
}

if (!function_exists('sales_action_template')) {
    function sales_action_template(string $stage, string $title = '', string $target = ''): array
    {
        $title = sales_action_clean($title !== '' ? $title : 'halaman/penawaran ini', 90);
        $target = sales_action_clean($target !== '' ? $target : $title, 90);

        return match ($stage) {
            'traffic' => [
                'channel' => 'Content + CTA',
                'trigger' => 'Money page butuh traffic pendukung dari SEO.',
                'whatsapp' => "Halo Kak {nama}, aku lihat Kakak sedang cari solusi tentang {$target}. Kami punya panduan singkat yang bisa bantu pilih opsi paling cocok. Mau saya kirim ringkasannya?",
                'email_subject' => "Panduan singkat sebelum memilih {$target}",
                'email_body' => "Halo {nama},\n\nTerima kasih sudah mampir ke website kami. Sebelum memilih {$target}, ada beberapa hal penting yang sebaiknya dicek agar tidak salah pilih.\n\nSaya bisa bantu kirimkan ringkasan pilihan dan rekomendasi yang paling sesuai dengan kebutuhan Kakak.\n\nSalam,\n{brand}",
                'checklist' => ['Buat 1 artikel support', 'Pasang internal link ke money page', 'Tambahkan CTA konsultasi ringan', 'Pantau Content Performance 7 hari'],
            ],
            'intent' => [
                'channel' => 'WhatsApp / Form CTA',
                'trigger' => 'Ada klik/interaksi, tapi belum jadi inquiry jelas.',
                'whatsapp' => "Halo Kak {nama}, terima kasih sudah cek {$target}. Biar saya bantu lebih tepat, kebutuhan Kakak lebih ke harga, fitur/paket, atau cara pesan?",
                'email_subject' => "Bantu pilih opsi {$target} yang paling pas",
                'email_body' => "Halo {nama},\n\nSaya lihat Kakak tertarik dengan {$target}. Biar tidak bingung, kami bisa bantu arahkan paket/opsi yang paling sesuai.\n\nKebutuhan utama Kakak saat ini lebih ke apa: harga, manfaat, proses, atau jadwal?\n\nSalam,\n{brand}",
                'checklist' => ['CTA utama terlihat tanpa scroll panjang', 'Tambahkan alasan klik yang spesifik', 'Sediakan form/WA cepat', 'Tambahkan FAQ keberatan'],
            ],
            'inquiry' => [
                'channel' => 'Fast Response CRM',
                'trigger' => 'Lead/form sudah masuk, perlu respon cepat agar tidak dingin.',
                'whatsapp' => "Halo Kak {nama}, terima kasih sudah menghubungi {brand}. Saya bantu cek kebutuhan Kakak ya. Boleh info target/tujuan utamanya supaya saya bisa rekomendasikan opsi paling pas?",
                'email_subject' => "Kami sudah menerima kebutuhan Kakak",
                'email_body' => "Halo {nama},\n\nTerima kasih sudah mengisi form di website {brand}. Kami sudah menerima data kebutuhan Kakak.\n\nAgar rekomendasinya lebih tepat, boleh balas email ini dengan detail tambahan atau langsung lanjut chat WhatsApp.\n\nSalam,\n{brand}",
                'checklist' => ['Respon maksimal 15 menit', 'Tanyakan kebutuhan utama', 'Kirim rekomendasi paket', 'Jadwalkan follow-up berikutnya'],
            ],
            'order' => [
                'channel' => 'Checkout / Invoice Recovery',
                'trigger' => 'Prospek sudah dekat order, tapi masih tertahan sebelum pembayaran.',
                'whatsapp' => "Halo Kak {nama}, order untuk {$target} sudah hampir selesai. Saya bantu pandu sampai beres ya. Kendalanya di pembayaran, data order, atau masih ingin tanya dulu?",
                'email_subject' => "Order {$target} hampir selesai",
                'email_body' => "Halo {nama},\n\nOrder Kakak untuk {$target} sudah hampir selesai. Jika masih ada kendala di pembayaran atau konfirmasi data, kami siap bantu.\n\nKakak bisa balas email ini atau lanjut via WhatsApp agar lebih cepat.\n\nSalam,\n{brand}",
                'checklist' => ['Cek field checkout terlalu panjang/tidak', 'Pastikan harga/biaya jelas', 'Kirim instruksi order singkat', 'Jadwalkan reminder manual'],
            ],
            'payment' => [
                'channel' => 'Payment Reminder',
                'trigger' => 'Order sudah ada, pembayaran/bukti bayar perlu dituntaskan.',
                'whatsapp' => "Halo Kak {nama}, izin mengingatkan order {$target}. Jika sudah transfer, Kakak bisa kirim bukti bayar di sini ya. Kalau ada kendala, saya bantu cek sekarang.",
                'email_subject' => "Reminder pembayaran order {$target}",
                'email_body' => "Halo {nama},\n\nIni reminder pembayaran untuk order {$target}. Jika pembayaran sudah dilakukan, silakan kirim bukti bayar agar pesanan bisa segera diproses.\n\nJika ada kendala, balas pesan ini dan kami bantu cek.\n\nSalam,\n{brand}",
                'checklist' => ['Instruksi transfer jelas', 'Link upload bukti bayar aktif', 'Reminder H+0/H+1 disiapkan', 'Status invoice mudah dicek'],
            ],
            'closing', 'scale' => [
                'channel' => 'Scale + Repeat Order',
                'trigger' => 'Halaman/channel punya sinyal bagus dan layak diperbesar.',
                'whatsapp' => "Halo Kak {nama}, terima kasih sudah percaya dengan {brand}. Kalau Kakak butuh rekomendasi lanjutan atau paket berikutnya, saya bisa bantu pilihkan yang paling cocok.",
                'email_subject' => "Rekomendasi lanjutan dari {brand}",
                'email_body' => "Halo {nama},\n\nTerima kasih sudah menggunakan {brand}. Berdasarkan kebutuhan Kakak sebelumnya, ada beberapa rekomendasi lanjutan yang mungkin cocok.\n\nKami siap bantu kalau Kakak ingin upgrade, repeat order, atau konsultasi lanjutan.\n\nSalam,\n{brand}",
                'checklist' => ['Tambahkan testimoni dari customer', 'Buat campaign ringan', 'Pasang internal link tambahan', 'Tawarkan repeat/upgrade yang relevan'],
            ],
            default => [
                'channel' => 'Manual Follow-up',
                'trigger' => 'Ada peluang growth yang perlu dieksekusi manual.',
                'whatsapp' => "Halo Kak {nama}, saya bantu follow-up terkait {$target}. Boleh saya tahu bagian mana yang masih ingin Kakak tanyakan?",
                'email_subject' => "Follow-up dari {brand}",
                'email_body' => "Halo {nama},\n\nKami ingin follow-up terkait {$target}. Jika masih ada pertanyaan, kami siap bantu.\n\nSalam,\n{brand}",
                'checklist' => ['Cek konteks lead', 'Kirim follow-up singkat', 'Catat hasil follow-up', 'Jadwalkan langkah berikutnya'],
            ],
        };
    }
}

if (!function_exists('sales_action_make_row')) {
    function sales_action_make_row(array $payload): array
    {
        $stage = (string)($payload['stage'] ?? 'intent');
        $title = sales_action_clean((string)($payload['title'] ?? sales_action_stage_label($stage)), 120);
        $target = sales_action_clean((string)($payload['target'] ?? $title), 120);
        $priority = sales_action_priority_meta((string)($payload['priority'] ?? 'Sedang'));
        $template = sales_action_template($stage, $title, $target);
        $score = max((int)$priority['score'], (int)($payload['score'] ?? 0));

        return [
            'id' => md5($stage . '|' . $title . '|' . $target . '|' . (string)($payload['source'] ?? '')),
            'stage' => $stage,
            'stage_label' => sales_action_stage_label($stage),
            'priority' => $priority,
            'score' => min(100, max(1, $score)),
            'title' => $title,
            'target' => $target,
            'source' => sales_action_clean((string)($payload['source'] ?? 'Funnel'), 80),
            'reason' => sales_action_clean((string)($payload['reason'] ?? ($template['trigger'] ?? '')), 220),
            'channel' => (string)($payload['channel'] ?? ($template['channel'] ?? 'Follow-up')),
            'related_url' => (string)($payload['related_url'] ?? ''),
            'action_url' => (string)($payload['action_url'] ?? ''),
            'action_label' => sales_action_clean((string)($payload['action_label'] ?? 'Buka Area Terkait'), 60),
            'whatsapp' => (string)($payload['whatsapp'] ?? ($template['whatsapp'] ?? '')),
            'email_subject' => (string)($payload['email_subject'] ?? ($template['email_subject'] ?? 'Follow-up')),
            'email_body' => (string)($payload['email_body'] ?? ($template['email_body'] ?? '')),
            'checklist' => array_values(array_filter((array)($payload['checklist'] ?? ($template['checklist'] ?? [])), static fn($v): bool => trim((string)$v) !== '')),
        ];
    }
}

if (!function_exists('sales_action_center_summary')) {
    function sales_action_center_summary(int $days = 30, array $filters = []): array
    {
        $funnel = function_exists('sales_funnel_growth_summary') ? sales_funnel_growth_summary($days, $filters) : [];
        $opportunity = function_exists('conversion_opportunity_summary') ? conversion_opportunity_summary($days, $filters) : ['metrics' => [], 'opportunities' => []];
        $content = function_exists('content_performance_summary') ? content_performance_summary($days, $filters) : ['metrics' => [], 'rows' => []];
        $crm = function_exists('crm_summary') ? crm_summary($days, $filters) : ['today' => 0, 'overdue' => 0, 'hot' => 0, 'upcoming' => 0];
        $rows = [];

        foreach ((array)($funnel['bottlenecks'] ?? []) as $item) {
            $stage = (string)($item['stage'] ?? 'intent');
            $rows[] = sales_action_make_row([
                'stage' => $stage,
                'priority' => ((string)($item['tone'] ?? '') === 'error') ? 'Kritis' : 'Tinggi',
                'score' => (int)($item['score'] ?? 70),
                'title' => (string)($item['title'] ?? 'Bottleneck funnel'),
                'target' => (string)($item['title'] ?? 'Funnel bisnis'),
                'source' => 'Sales Funnel Growth',
                'reason' => (string)($item['body'] ?? 'Tahap funnel ini perlu dieksekusi.'),
                'action_url' => function_exists('url') ? url('admin/sales-funnel-growth?stage=' . rawurlencode($stage)) : '',
                'action_label' => 'Buka Funnel',
            ]);
        }

        foreach (array_slice((array)($opportunity['opportunities'] ?? []), 0, 12) as $item) {
            $category = (string)($item['category'] ?? 'cta_gap');
            $stage = match ($category) {
                'support_gap', 'seo_to_conversion' => 'traffic',
                'cta_gap' => 'intent',
                'offer_gap' => 'inquiry',
                'checkout_gap' => 'order',
                default => 'intent',
            };
            $impact = (array)($item['impact'] ?? []);
            $action = (array)($item['action'] ?? []);
            $rows[] = sales_action_make_row([
                'stage' => $stage,
                'priority' => (string)($impact['label'] ?? 'Tinggi'),
                'score' => (int)($item['score'] ?? 65),
                'title' => (string)($action['title'] ?? $item['title'] ?? 'Peluang konversi'),
                'target' => (string)($item['page_title'] ?? $item['title'] ?? 'Halaman prioritas'),
                'source' => 'Conversion Opportunity',
                'reason' => (string)($action['body'] ?? $item['reason'] ?? 'Ada gap konversi yang perlu dipoles.'),
                'action_url' => (string)($action['url'] ?? (function_exists('url') ? url('admin/conversion-opportunities?q=' . rawurlencode((string)($item['page_title'] ?? ''))) : '')),
                'action_label' => (string)($action['label'] ?? 'Lihat Opportunity'),
                'checklist' => (array)($action['checklist'] ?? []),
            ]);
        }

        foreach (array_slice((array)($content['rows'] ?? []), 0, 10) as $row) {
            $bucketKey = (string)($row['bucket']['key'] ?? 'monitor');
            $stage = match ($bucketKey) {
                'scale_winner' => 'scale',
                'cta_polish' => 'intent',
                'seo_boost', 'build_support' => 'traffic',
                default => 'intent',
            };
            $rows[] = sales_action_make_row([
                'stage' => $stage,
                'priority' => $bucketKey === 'scale_winner' ? 'Scale' : ($bucketKey === 'cta_polish' ? 'Tinggi' : 'Sedang'),
                'score' => (int)($row['performance_score'] ?? 40),
                'title' => (string)($row['bucket']['note'] ?? 'Poles performa konten'),
                'target' => (string)($row['title'] ?? 'Halaman prioritas'),
                'source' => 'Content Performance',
                'reason' => 'Sinyal: interaksi ' . (int)($row['metrics']['interactions'] ?? 0) . ', intent ' . ((int)($row['metrics']['high_intent'] ?? 0) + (int)($row['metrics']['whatsapp'] ?? 0) + (int)($row['metrics']['inquiries'] ?? 0)) . ', order ' . (int)($row['metrics']['orders'] ?? 0) . '.',
                'action_url' => (string)($row['edit_url'] ?? (function_exists('url') ? url('admin/content-performance?q=' . rawurlencode((string)($row['title'] ?? ''))) : '')),
                'action_label' => 'Lihat Konten',
            ]);
        }

        if ((int)($crm['overdue'] ?? 0) > 0) {
            $rows[] = sales_action_make_row([
                'stage' => 'inquiry',
                'priority' => 'Kritis',
                'score' => 90,
                'title' => 'Follow-up terlambat perlu dikejar',
                'target' => 'Lead yang sudah dijadwalkan',
                'source' => 'Mini CRM',
                'reason' => (int)($crm['overdue'] ?? 0) . ' follow-up sudah melewati jadwal. Ini bisa bikin prospek dingin.',
                'action_url' => function_exists('url') ? url('admin/followups?due=overdue') : '',
                'action_label' => 'Buka Follow-up',
            ]);
        }

        if ((int)($crm['today'] ?? 0) > 0) {
            $rows[] = sales_action_make_row([
                'stage' => 'inquiry',
                'priority' => 'Tinggi',
                'score' => 74,
                'title' => 'Follow-up hari ini',
                'target' => 'Lead aktif hari ini',
                'source' => 'Mini CRM',
                'reason' => (int)($crm['today'] ?? 0) . ' follow-up jatuh tempo hari ini.',
                'action_url' => function_exists('url') ? url('admin/followups?due=today') : '',
                'action_label' => 'Buka Follow-up',
            ]);
        }

        $unique = [];
        foreach ($rows as $row) {
            $unique[(string)($row['id'] ?? md5(json_encode($row) ?: ''))] = $row;
        }
        $rows = array_values($unique);
        usort($rows, static fn(array $a, array $b): int => (int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0));

        $metrics = [
            'total_actions' => count($rows),
            'critical' => count(array_filter($rows, static fn(array $row): bool => ($row['priority']['key'] ?? '') === 'critical')),
            'high' => count(array_filter($rows, static fn(array $row): bool => ($row['priority']['key'] ?? '') === 'high')),
            'scale' => count(array_filter($rows, static fn(array $row): bool => ($row['priority']['key'] ?? '') === 'scale')),
            'today_followups' => (int)($crm['today'] ?? 0),
            'overdue_followups' => (int)($crm['overdue'] ?? 0),
        ];

        return [
            'generated_at' => date('c'),
            'days' => $days,
            'filters' => $filters,
            'metrics' => $metrics,
            'funnel_score' => (array)($funnel['score'] ?? []),
            'crm' => $crm,
            'rows' => $rows,
        ];
    }
}

if (!function_exists('sales_action_center_filter_rows')) {
    function sales_action_center_filter_rows(array $rows, string $stage = 'all', string $priority = 'all', string $q = ''): array
    {
        $q = trim($q);
        $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
        return array_values(array_filter($rows, static function (array $row) use ($stage, $priority, $needle): bool {
            if ($stage !== 'all' && (string)($row['stage'] ?? '') !== $stage) {
                return false;
            }
            if ($priority !== 'all' && (string)($row['priority']['key'] ?? '') !== $priority) {
                return false;
            }
            if ($needle !== '') {
                $haystack = implode(' ', [
                    $row['stage_label'] ?? '',
                    $row['priority']['label'] ?? '',
                    $row['title'] ?? '',
                    $row['target'] ?? '',
                    $row['source'] ?? '',
                    $row['reason'] ?? '',
                    $row['channel'] ?? '',
                    $row['whatsapp'] ?? '',
                    $row['email_subject'] ?? '',
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
