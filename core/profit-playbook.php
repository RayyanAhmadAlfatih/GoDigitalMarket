<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PROFIT PLAYBOOK & CAMPAIGN PLANNER
|--------------------------------------------------------------------------
| Rule-based campaign planner that converts daily profit actions, SEO signal,
| funnel gaps, and trust/CTA readiness into 7/14/30 day execution playbooks.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('profit_playbook_storage_file')) {
    function profit_playbook_storage_file(): string
    {
        return STORAGE_PATH . '/profit-playbook-state.json';
    }
}

if (!function_exists('profit_playbook_clean')) {
    function profit_playbook_clean(mixed $value, int $max = 180): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$value)) ?: '');
        if ($text === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

if (!function_exists('profit_playbook_safe_id')) {
    function profit_playbook_safe_id(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_\-]+/', '-', $value) ?: '';
        $value = trim($value, '-');
        return $value !== '' ? substr($value, 0, 120) : md5((string)microtime(true));
    }
}

if (!function_exists('profit_playbook_default_state')) {
    function profit_playbook_default_state(): array
    {
        return [
            'completed' => [],
            'updated_at' => '',
        ];
    }
}

if (!function_exists('profit_playbook_normalize_state')) {
    function profit_playbook_normalize_state(array $state): array
    {
        $completed = [];
        foreach ((array)($state['completed'] ?? []) as $campaign => $ids) {
            $campaign = profit_playbook_safe_id((string)$campaign);
            if ($campaign === '') {
                continue;
            }
            foreach ((array)$ids as $id => $timestamp) {
                $cleanId = profit_playbook_safe_id((string)$id);
                if ($cleanId !== '') {
                    $completed[$campaign][$cleanId] = profit_playbook_clean($timestamp, 80) ?: date(DATE_ATOM);
                }
            }
        }

        return [
            'completed' => $completed,
            'updated_at' => profit_playbook_clean($state['updated_at'] ?? '', 80),
        ];
    }
}

if (!function_exists('profit_playbook_write_state')) {
    function profit_playbook_write_state(array $state, bool $throw = false): bool
    {
        $state = profit_playbook_normalize_state($state);
        $state['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(profit_playbook_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Progress campaign belum bisa disimpan. Cek permission folder storage.');
            }
            return false;
        }

        @chmod(profit_playbook_storage_file(), 0644);
        return true;
    }
}

if (!function_exists('profit_playbook_state')) {
    function profit_playbook_state(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $file = profit_playbook_storage_file();
        if (!is_file($file)) {
            $cached = profit_playbook_default_state();
            profit_playbook_write_state($cached, false);
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = profit_playbook_default_state();
            profit_playbook_write_state($cached, false);
            return $cached;
        }

        $cached = profit_playbook_normalize_state($decoded);
        return $cached;
    }
}

if (!function_exists('profit_playbook_campaign_id')) {
    function profit_playbook_campaign_id(int $duration, string $goal, int $days, array $filters = []): string
    {
        $fingerprint = md5(json_encode([$duration, $goal, $days, $filters, date('Y-m')]) ?: 'campaign');
        return profit_playbook_safe_id('campaign-' . $duration . '-' . $goal . '-' . substr($fingerprint, 0, 10));
    }
}

if (!function_exists('profit_playbook_mark_completed')) {
    function profit_playbook_mark_completed(string $campaignId, string $taskId, bool $completed = true): bool
    {
        $campaignId = profit_playbook_safe_id($campaignId);
        $taskId = profit_playbook_safe_id($taskId);
        if ($campaignId === '' || $taskId === '') {
            return false;
        }

        $state = profit_playbook_state();
        $state['completed'][$campaignId] = (array)($state['completed'][$campaignId] ?? []);

        if ($completed) {
            $state['completed'][$campaignId][$taskId] = date(DATE_ATOM);
        } else {
            unset($state['completed'][$campaignId][$taskId]);
        }

        return profit_playbook_write_state($state, true);
    }
}

if (!function_exists('profit_playbook_reset_campaign')) {
    function profit_playbook_reset_campaign(string $campaignId): bool
    {
        $campaignId = profit_playbook_safe_id($campaignId);
        $state = profit_playbook_state();
        unset($state['completed'][$campaignId]);
        return profit_playbook_write_state($state, true);
    }
}

if (!function_exists('profit_playbook_goal_options')) {
    function profit_playbook_goal_options(): array
    {
        return [
            'closing' => 'Closing Cepat',
            'seo_to_sales' => 'SEO ke Penjualan',
            'trust' => 'Trust & CTA',
            'follow_up' => 'Follow-up Lead',
            'scale' => 'Scale Konten Winner',
        ];
    }
}

if (!function_exists('profit_playbook_duration_options')) {
    function profit_playbook_duration_options(): array
    {
        return [
            7 => '7 Hari Sprint',
            14 => '14 Hari Booster',
            30 => '30 Hari Growth Campaign',
        ];
    }
}

if (!function_exists('profit_playbook_goal_meta')) {
    function profit_playbook_goal_meta(string $goal): array
    {
        return match ($goal) {
            'closing' => [
                'label' => 'Closing Cepat',
                'headline' => 'Sprint mengejar order, pembayaran, dan lead panas',
                'metric' => 'Order pending turun, bukti bayar cepat direview, follow-up lebih rapi.',
                'focus' => ['money_leak', 'follow_up'],
            ],
            'seo_to_sales' => [
                'label' => 'SEO ke Penjualan',
                'headline' => 'Campaign mengubah konten SEO menjadi jalur lead dan order',
                'metric' => 'Artikel prioritas punya CTA, internal link, dan penawaran yang jelas.',
                'focus' => ['seo_to_sales', 'trust_cta'],
            ],
            'trust' => [
                'label' => 'Trust & CTA',
                'headline' => 'Campaign memperkuat rasa percaya sebelum pengunjung klik CTA',
                'metric' => 'FAQ, testimoni, garansi, benefit, dan CTA utama lebih siap.',
                'focus' => ['trust_cta', 'setup'],
            ],
            'follow_up' => [
                'label' => 'Follow-up Lead',
                'headline' => 'Campaign menjaga lead agar tidak dingin',
                'metric' => 'Lead baru, order baru, dan inquiry punya alur follow-up yang konsisten.',
                'focus' => ['follow_up', 'money_leak'],
            ],
            'scale' => [
                'label' => 'Scale Konten Winner',
                'headline' => 'Campaign memperbesar halaman yang sudah punya sinyal bagus',
                'metric' => 'Konten pemenang dipoles, ditambah CTA, dan dijadikan bahan campaign.',
                'focus' => ['scale', 'seo_to_sales'],
            ],
            default => [
                'label' => 'Profit Campaign',
                'headline' => 'Campaign praktis untuk menaikkan peluang profit website',
                'metric' => 'Aksi harian lebih fokus, lebih terukur, dan tidak random.',
                'focus' => ['money_leak', 'follow_up', 'seo_to_sales', 'trust_cta', 'setup', 'scale'],
            ],
        };
    }
}

if (!function_exists('profit_playbook_task')) {
    function profit_playbook_task(array $data): array
    {
        $day = max(1, (int)($data['day'] ?? 1));
        $title = profit_playbook_clean($data['title'] ?? 'Aksi campaign', 140);
        $phase = profit_playbook_clean($data['phase'] ?? 'Execution', 80);
        $id = profit_playbook_safe_id((string)($data['id'] ?? ('day-' . $day . '-' . $title)));

        return [
            'id' => $id,
            'day' => $day,
            'phase' => $phase,
            'title' => $title,
            'priority' => profit_playbook_clean($data['priority'] ?? 'Penting', 40),
            'objective' => profit_playbook_clean($data['objective'] ?? 'Membuat campaign lebih dekat ke lead, order, atau closing.', 260),
            'why' => profit_playbook_clean($data['why'] ?? 'Aksi ini dipilih dari sinyal profit website.', 360),
            'checklist' => array_values(array_filter(array_map(static fn($value): string => profit_playbook_clean($value, 170), (array)($data['checklist'] ?? [])), static fn($value): bool => $value !== '')),
            'kpi' => profit_playbook_clean($data['kpi'] ?? 'Minimal 1 improvement selesai.', 160),
            'cta_label' => profit_playbook_clean($data['cta_label'] ?? 'Buka Menu', 70),
            'cta_url' => (string)($data['cta_url'] ?? ''),
            'secondary_label' => profit_playbook_clean($data['secondary_label'] ?? '', 70),
            'secondary_url' => (string)($data['secondary_url'] ?? ''),
            'asset' => profit_playbook_clean($data['asset'] ?? 'Catatan eksekusi', 120),
            'script' => trim((string)($data['script'] ?? '')),
            'source' => profit_playbook_clean($data['source'] ?? 'Campaign Planner', 80),
        ];
    }
}

if (!function_exists('profit_playbook_base_tasks')) {
    function profit_playbook_base_tasks(int $duration, string $goal, array $summary): array
    {
        $meta = profit_playbook_goal_meta($goal);
        $readiness = (int)($summary['readiness']['total'] ?? 0);
        $report = (array)($summary['report'] ?? []);
        $paymentWaiting = (int)($report['order']['payment_waiting'] ?? 0);
        $pendingProofs = (int)($report['payment']['pending_proofs'] ?? 0);
        $leadEvents = (int)($report['lead']['events'] ?? 0);
        $orders = (int)($report['order']['total'] ?? 0);
        $contentRows = (array)($summary['content']['top_rows'] ?? []);
        $topContentTitle = profit_playbook_clean((string)($contentRows[0]['title'] ?? 'halaman prioritas'), 80);

        $tasks = [
            profit_playbook_task([
                'day' => 1,
                'phase' => 'Audit Cepat',
                'title' => 'Tentukan offer utama campaign',
                'priority' => 'Wajib',
                'objective' => 'Bikin satu penawaran utama yang jelas supaya semua konten, CTA, dan follow-up mengarah ke tujuan yang sama.',
                'why' => 'Campaign yang terlalu umum biasanya susah closing. Pilih 1 produk/jasa/lead magnet utama dulu.',
                'checklist' => ['Pilih 1 produk/jasa yang ingin didorong.', 'Tulis alasan kenapa customer perlu memilih sekarang.', 'Siapkan CTA utama: WhatsApp, form, katalog, atau checkout.'],
                'kpi' => '1 offer utama campaign siap dipakai.',
                'cta_label' => 'Atur Brand/Offer',
                'cta_url' => function_exists('url') ? url('admin/brand') : '',
                'secondary_label' => 'Atur Homepage',
                'secondary_url' => function_exists('url') ? url('admin/homepage') : '',
                'asset' => 'Offer statement campaign',
            ]),
            profit_playbook_task([
                'day' => 2,
                'phase' => 'Money Leak',
                'title' => $pendingProofs > 0 ? 'Review bukti pembayaran sebelum campaign diperbesar' : 'Pastikan alur pembayaran tidak bikin calon pembeli bingung',
                'priority' => $pendingProofs > 0 ? 'Urgent' : 'Penting',
                'objective' => 'Mengurangi hambatan paling dekat dengan omzet sebelum traffic baru diarahkan.',
                'why' => $pendingProofs > 0 ? $pendingProofs . ' bukti pembayaran masih menunggu review.' : 'Payment instruction yang jelas membantu order tidak berhenti di tengah.',
                'checklist' => ['Cek bukti bayar/order pending.', 'Pastikan instruksi transfer/upload bukti jelas.', 'Siapkan template reminder pembayaran.'],
                'kpi' => 'Payment leak utama sudah dicek.',
                'cta_label' => 'Bukti Pembayaran',
                'cta_url' => function_exists('url') ? url('admin/payment-proofs') : '',
                'secondary_label' => 'Reminder',
                'secondary_url' => function_exists('url') ? url('admin/payment-reminders') : '',
                'asset' => 'Payment reminder script',
                'script' => 'Halo Kak {nama}, izin mengingatkan order Kakak. Jika sudah transfer, Kakak bisa upload/kirim bukti pembayaran ya. Kalau ada kendala, saya bantu cek sekarang.',
            ]),
            profit_playbook_task([
                'day' => 3,
                'phase' => 'CTA & Trust',
                'title' => 'Perkuat CTA dan trust block utama',
                'priority' => 'Tinggi',
                'objective' => 'Membuat pengunjung lebih yakin dan tahu harus klik apa setelah membaca halaman.',
                'why' => 'CTA dan trust sering menjadi pembeda antara pengunjung yang cuma baca dan pengunjung yang bertanya/order.',
                'checklist' => ['Aktifkan minimal benefit, FAQ, testimoni/garansi, dan CTA.', 'Buat CTA yang spesifik sesuai offer campaign.', 'Pastikan section tampil di homepage atau halaman prioritas.'],
                'kpi' => 'Minimal 3 trust/conversion block aktif.',
                'cta_label' => 'Trust & CTA',
                'cta_url' => function_exists('url') ? url('admin/trust-conversion') : '',
                'secondary_label' => 'Urutan Homepage',
                'secondary_url' => function_exists('url') ? url('admin/homepage') : '',
                'asset' => 'FAQ + CTA block',
            ]),
            profit_playbook_task([
                'day' => 4,
                'phase' => 'SEO to Sales',
                'title' => 'Poles konten prioritas: ' . $topContentTitle,
                'priority' => 'Tinggi',
                'objective' => 'Mengubah konten yang punya sinyal menjadi jalan masuk ke produk, form, WhatsApp, atau checkout.',
                'why' => $leadEvents > 0 ? 'Sudah ada ' . $leadEvents . ' lead/event. Konten perlu diarahkan lebih jelas ke tindakan berikutnya.' : 'Mulai bangun jalur dari konten menuju offer sebelum traffic naik.',
                'checklist' => ['Tambahkan CTA di awal, tengah, dan akhir konten.', 'Tambahkan internal link ke produk/jasa terkait.', 'Tambahkan FAQ yang menjawab keberatan calon buyer.'],
                'kpi' => '1 konten prioritas dipoles untuk konversi.',
                'cta_label' => 'Content Performance',
                'cta_url' => function_exists('url') ? url('admin/content-performance') : '',
                'secondary_label' => 'SEO Planner',
                'secondary_url' => function_exists('url') ? url('admin/seo-growth-planner') : '',
                'asset' => 'CTA paragraph + internal link',
            ]),
            profit_playbook_task([
                'day' => 5,
                'phase' => 'Follow-up',
                'title' => 'Rapikan follow-up untuk lead dan order baru',
                'priority' => $paymentWaiting > 0 || $orders > 0 ? 'Tinggi' : 'Penting',
                'objective' => 'Mencegah lead/order yang sudah masuk menjadi dingin karena tidak ada tindak lanjut.',
                'why' => $orders > 0 ? 'Sudah ada ' . $orders . ' order. Follow-up perlu konsisten agar closing rate naik.' : 'Follow-up tetap perlu disiapkan sebelum traffic campaign didorong.',
                'checklist' => ['Siapkan script follow-up hari H, H+1, dan H+2.', 'Cek inbox lead dan order baru.', 'Catat follow-up yang perlu dikejar ulang.'],
                'kpi' => 'Minimal 1 script follow-up siap dipakai.',
                'cta_label' => 'Follow-up CRM',
                'cta_url' => function_exists('url') ? url('admin/followups') : '',
                'secondary_label' => 'Inbox Lead',
                'secondary_url' => function_exists('url') ? url('admin/inquiries') : '',
                'asset' => 'Follow-up script',
                'script' => 'Halo Kak {nama}, kemarin sempat tertarik dengan {produk/jasa}. Saya bantu ringkas ya: manfaat utamanya {benefit}. Jika Kakak mau, saya bisa bantu cek pilihan yang paling cocok.',
            ]),
            profit_playbook_task([
                'day' => 6,
                'phase' => 'Publish & Promote',
                'title' => 'Buat bahan promosi dari offer campaign',
                'priority' => 'Penting',
                'objective' => 'Menyiapkan bahan singkat untuk WhatsApp, sosial media, atau broadcast manual yang mengarah ke halaman/CTA.',
                'why' => 'SEO butuh waktu. Bahan promosi ringan membantu campaign mulai mendapat sinyal lebih cepat.',
                'checklist' => ['Tulis 1 pesan promosi singkat.', 'Tautkan ke halaman produk/jasa/artikel prioritas.', 'Gunakan CTA yang sama dengan campaign.'],
                'kpi' => '1 copy promosi siap dipakai.',
                'cta_label' => 'Marketing',
                'cta_url' => function_exists('url') ? url('admin/marketing-integrations') : '',
                'secondary_label' => 'Landing Pages',
                'secondary_url' => function_exists('url') ? url('admin/landing-pages') : '',
                'asset' => 'Copy promosi campaign',
                'script' => 'Lagi cari solusi untuk {masalah customer}? Kami bantu dengan {offer}. Cek detailnya di sini: {link}. Bisa chat admin juga kalau ingin dibantu pilih yang cocok.',
            ]),
            profit_playbook_task([
                'day' => 7,
                'phase' => 'Review',
                'title' => 'Review hasil sprint dan pilih aksi lanjutan',
                'priority' => 'Wajib',
                'objective' => 'Melihat tindakan mana yang sudah jalan dan menentukan campaign berikutnya berdasarkan sinyal, bukan tebak-tebakan.',
                'why' => 'Campaign yang bagus selalu punya review. Setelah 7 hari, lihat leak, konten, lead, dan order yang berubah.',
                'checklist' => ['Cek Profit Action Dashboard.', 'Catat action yang selesai dan yang belum.', 'Pilih 1 konten/offer untuk dipush lagi.'],
                'kpi' => '1 keputusan campaign lanjutan dibuat.',
                'cta_label' => 'Profit Action',
                'cta_url' => function_exists('url') ? url('admin/profit-action-dashboard') : '',
                'secondary_label' => 'Growth Snapshot',
                'secondary_url' => function_exists('url') ? url('admin/growth-snapshot') : '',
                'asset' => 'Review campaign 7 hari',
            ]),
        ];

        if ($duration >= 14) {
            $extra = [
                ['day' => 8, 'phase' => 'Content Expansion', 'title' => 'Buat 1 konten pendukung untuk offer utama', 'objective' => 'Menambah pintu masuk SEO yang mendukung offer campaign.', 'checklist' => ['Cari 1 pertanyaan customer.', 'Buat artikel/jawaban singkat.', 'Arahkan ke halaman offer.'], 'kpi' => '1 ide konten siap/draft dibuat.', 'cta_url' => function_exists('url') ? url('admin/seo-content-planner') : '', 'cta_label' => 'Content Planner', 'asset' => 'Brief artikel pendukung'],
                ['day' => 9, 'phase' => 'Internal Link', 'title' => 'Bangun jalur internal link ke money page', 'objective' => 'Membantu pembaca dan mesin pencari memahami halaman prioritas.', 'checklist' => ['Pilih 3 artikel/halaman terkait.', 'Tambahkan link ke produk/jasa utama.', 'Gunakan anchor text natural.'], 'kpi' => 'Minimal 3 internal link ditambahkan.', 'cta_url' => function_exists('url') ? url('admin/seo-link-health') : '', 'cta_label' => 'Internal Link'],
                ['day' => 10, 'phase' => 'Offer Polish', 'title' => 'Perjelas perbandingan manfaat, paket, atau pilihan', 'objective' => 'Mengurangi bingung saat customer memilih.', 'checklist' => ['Tulis benefit utama.', 'Buat perbandingan paket/opsi bila ada.', 'Tambahkan alasan pilih paket yang direkomendasikan.'], 'kpi' => 'Offer lebih mudah dipahami.', 'cta_url' => function_exists('url') ? url('admin/produk') : '', 'cta_label' => 'Katalog'],
                ['day' => 11, 'phase' => 'Lead Capture', 'title' => 'Cek form lead/checkout agar tidak terlalu panjang', 'objective' => 'Memastikan calon customer tidak batal karena form membingungkan.', 'checklist' => ['Cek field wajib.', 'Hapus pertanyaan yang tidak perlu.', 'Pastikan pesan setelah submit jelas.'], 'kpi' => 'Form campaign sudah ringan.', 'cta_url' => function_exists('url') ? url('admin/forms') : '', 'cta_label' => 'Form Builder'],
                ['day' => 12, 'phase' => 'Trust Asset', 'title' => 'Tambahkan bukti sosial atau jawaban keberatan', 'objective' => 'Membantu calon buyer yang masih ragu.', 'checklist' => ['Tambah 1 testimoni/studi kasus.', 'Tambah 2 FAQ keberatan.', 'Tambahkan badge/garansi bila relevan.'], 'kpi' => 'Trust asset bertambah.', 'cta_url' => function_exists('url') ? url('admin/trust-conversion') : '', 'cta_label' => 'Trust Block'],
                ['day' => 13, 'phase' => 'Campaign Push', 'title' => 'Dorong ulang campaign ke channel yang sudah ada', 'objective' => 'Memberi sinyal baru ke campaign tanpa harus menunggu SEO saja.', 'checklist' => ['Share ke WhatsApp/status/sosial.', 'Gunakan copy promosi yang sudah disiapkan.', 'Arahkan ke halaman prioritas.'], 'kpi' => '1 channel promosi dipakai.', 'cta_url' => function_exists('url') ? url('admin/marketing-integrations') : '', 'cta_label' => 'Marketing'],
                ['day' => 14, 'phase' => 'Review Booster', 'title' => 'Bandingkan sebelum-sesudah campaign 14 hari', 'objective' => 'Menentukan apakah offer perlu diulang, dipoles, atau diganti.', 'checklist' => ['Cek lead, order, payment, dan content signal.', 'Catat top page/top CTA.', 'Tentukan campaign 14/30 hari berikutnya.'], 'kpi' => 'Keputusan scale/polish dibuat.', 'cta_url' => function_exists('url') ? url('admin/profit-action-dashboard') : '', 'cta_label' => 'Profit Action'],
            ];
            foreach ($extra as $row) {
                $tasks[] = profit_playbook_task($row + ['priority' => 'Penting', 'why' => 'Aksi lanjutan ini memperkuat campaign setelah sprint awal berjalan.', 'source' => '14 Hari Booster']);
            }
        }

        if ($duration >= 30) {
            for ($day = 15; $day <= 30; $day++) {
                $cycle = ($day - 15) % 4;
                if ($cycle === 0) {
                    $row = ['phase' => 'SEO Asset', 'title' => 'Tambah/poles aset SEO campaign hari ' . $day, 'objective' => 'Menambah aset organik yang tetap bekerja setelah campaign selesai.', 'checklist' => ['Pilih 1 topik long-tail.', 'Tulis outline/jawaban cepat.', 'Arahkan ke offer utama.'], 'kpi' => '1 aset SEO disentuh.', 'cta_url' => function_exists('url') ? url('admin/seo-execution-board') : '', 'cta_label' => 'Execution Board', 'asset' => 'SEO asset'];
                } elseif ($cycle === 1) {
                    $row = ['phase' => 'Conversion Polish', 'title' => 'Optimasi CTA/trust campaign hari ' . $day, 'objective' => 'Menaikkan peluang visitor menjadi lead/order.', 'checklist' => ['Cek CTA di halaman prioritas.', 'Tambahkan trust/FAQ singkat.', 'Pastikan tombol menuju channel yang benar.'], 'kpi' => '1 conversion polish selesai.', 'cta_url' => function_exists('url') ? url('admin/trust-conversion') : '', 'cta_label' => 'Trust & CTA', 'asset' => 'CTA/trust update'];
                } elseif ($cycle === 2) {
                    $row = ['phase' => 'Follow-up & Retarget', 'title' => 'Follow-up lead/calon buyer hari ' . $day, 'objective' => 'Mengaktifkan kembali calon customer yang belum closing.', 'checklist' => ['Cek lead/order yang belum lanjut.', 'Kirim follow-up ramah.', 'Catat alasan belum closing bila ada.'], 'kpi' => '1 batch follow-up selesai.', 'cta_url' => function_exists('url') ? url('admin/followups') : '', 'cta_label' => 'Follow-up', 'asset' => 'Follow-up batch', 'script' => 'Halo Kak {nama}, saya follow-up singkat ya. Untuk {offer}, benefit utamanya {benefit}. Jika masih ada pertanyaan, saya bantu jawab sekarang.'];
                } else {
                    $row = ['phase' => 'Review & Scale', 'title' => 'Review sinyal dan scale mini hari ' . $day, 'objective' => 'Menentukan aksi scale kecil berdasarkan data terbaru.', 'checklist' => ['Cek halaman/CTA yang mulai bagus.', 'Pilih satu yang layak dipush.', 'Duplikasi pola yang berhasil.'], 'kpi' => '1 keputusan scale mini dibuat.', 'cta_url' => function_exists('url') ? url('admin/content-performance') : '', 'cta_label' => 'Performance', 'asset' => 'Scale note'];
                }
                $tasks[] = profit_playbook_task($row + ['day' => $day, 'priority' => $day === 30 ? 'Wajib' : 'Penting', 'why' => 'Campaign 30 hari butuh ritme: bangun traffic, poles conversion, follow-up, lalu review.', 'source' => '30 Hari Growth Campaign']);
            }
        }

        if ($goal === 'closing') {
            $tasks[0]['title'] = 'Pilih offer yang paling cepat closing';
            $tasks[0]['objective'] = 'Fokus ke produk/jasa yang paling mudah dibeli sekarang, bukan yang paling rumit dijelaskan.';
        } elseif ($goal === 'seo_to_sales') {
            $tasks[0]['title'] = 'Pilih money page untuk diarahkan dari SEO';
            $tasks[3]['priority'] = 'Wajib';
        } elseif ($goal === 'trust') {
            $tasks[2]['priority'] = 'Wajib';
            $tasks[2]['title'] = 'Jadikan trust block sebagai pusat campaign';
        } elseif ($goal === 'follow_up') {
            $tasks[4]['priority'] = 'Wajib';
            $tasks[4]['title'] = 'Bangun ritme follow-up H, H+1, dan H+2';
        } elseif ($goal === 'scale') {
            $tasks[3]['title'] = 'Pilih konten pemenang untuk discale: ' . $topContentTitle;
            $tasks[6]['title'] = 'Review konten winner dan siapkan scale berikutnya';
        }

        foreach ($tasks as $index => $task) {
            $tasks[$index]['id'] = profit_playbook_safe_id('d' . (int)$task['day'] . '-' . $goal . '-' . $task['title']);
        }

        return array_values(array_filter($tasks, static fn(array $task): bool => (int)($task['day'] ?? 0) <= $duration));
    }
}

if (!function_exists('profit_playbook_action_tasks')) {
    function profit_playbook_action_tasks(array $summary, int $duration, string $goal): array
    {
        $meta = profit_playbook_goal_meta($goal);
        $preferred = (array)($meta['focus'] ?? []);
        $actions = (array)($summary['today_plan'] ?? []);
        if (!$actions) {
            $actions = array_slice((array)($summary['actions'] ?? []), 0, 6);
        }

        $day = $duration >= 14 ? 8 : 6;
        $tasks = [];
        foreach ($actions as $action) {
            if ($preferred && !in_array((string)($action['focus'] ?? ''), $preferred, true) && count($tasks) >= 2) {
                continue;
            }
            $tasks[] = profit_playbook_task([
                'day' => min($duration, max(1, $day)),
                'phase' => 'Action Profit',
                'title' => 'Eksekusi action: ' . (string)($action['title'] ?? 'Aksi profit'),
                'priority' => (string)($action['priority']['label'] ?? 'Penting'),
                'objective' => (string)($action['impact'] ?? 'Menutup gap profit yang terdeteksi dashboard.'),
                'why' => (string)($action['why'] ?? 'Action ini muncul dari Profit Action Dashboard.'),
                'checklist' => (array)($action['steps'] ?? []),
                'kpi' => 'Action terkait ditandai selesai atau punya catatan tindak lanjut.',
                'cta_label' => (string)($action['action_label'] ?? 'Buka Action'),
                'cta_url' => (string)($action['action_url'] ?? (function_exists('url') ? url('admin/profit-action-dashboard') : '')),
                'secondary_label' => 'Profit Action',
                'secondary_url' => function_exists('url') ? url('admin/profit-action-dashboard') : '',
                'asset' => 'Action dari dashboard',
                'script' => (string)($action['script'] ?? ''),
                'source' => 'Profit Action Dashboard',
            ]);
            $day++;
            if (count($tasks) >= min(5, max(2, (int)floor($duration / 4)))) {
                break;
            }
        }
        return $tasks;
    }
}

if (!function_exists('profit_playbook_merge_tasks')) {
    function profit_playbook_merge_tasks(array $base, array $extra, int $duration): array
    {
        $rows = [];
        foreach (array_merge($base, $extra) as $task) {
            if (!is_array($task)) {
                continue;
            }
            $id = (string)($task['id'] ?? '');
            $day = (int)($task['day'] ?? 0);
            if ($id === '' || $day < 1 || $day > $duration) {
                continue;
            }
            $rows[$id] = $task;
        }
        $rows = array_values($rows);
        usort($rows, static function (array $a, array $b): int {
            return ((int)($a['day'] ?? 0) <=> (int)($b['day'] ?? 0)) ?: strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });
        return $rows;
    }
}

if (!function_exists('profit_playbook_enrich_completion')) {
    function profit_playbook_enrich_completion(array $tasks, string $campaignId): array
    {
        $state = profit_playbook_state();
        $completedIds = (array)($state['completed'][$campaignId] ?? []);
        $done = 0;
        foreach ($tasks as $idx => $task) {
            $id = profit_playbook_safe_id((string)($task['id'] ?? ''));
            $isDone = isset($completedIds[$id]);
            $tasks[$idx]['completed'] = $isDone;
            $tasks[$idx]['completed_at'] = $isDone ? (string)$completedIds[$id] : '';
            if ($isDone) {
                $done++;
            }
        }
        return [$tasks, $done];
    }
}

if (!function_exists('profit_playbook_phase_summary')) {
    function profit_playbook_phase_summary(array $tasks): array
    {
        $phases = [];
        foreach ($tasks as $task) {
            $phase = (string)($task['phase'] ?? 'Execution');
            if (!isset($phases[$phase])) {
                $phases[$phase] = ['label' => $phase, 'total' => 0, 'completed' => 0, 'days' => []];
            }
            $phases[$phase]['total']++;
            if (!empty($task['completed'])) {
                $phases[$phase]['completed']++;
            }
            $phases[$phase]['days'][] = (int)($task['day'] ?? 0);
        }
        foreach ($phases as $key => $phase) {
            $days = array_values(array_filter(array_unique((array)$phase['days'])));
            sort($days);
            $phases[$key]['days'] = $days;
            $phases[$key]['progress'] = (int)round(((int)$phase['completed'] / max(1, (int)$phase['total'])) * 100);
        }
        return array_values($phases);
    }
}

if (!function_exists('profit_playbook_campaign_summary')) {
    function profit_playbook_campaign_summary(int $duration = 14, string $goal = 'seo_to_sales', int $days = 30, array $filters = []): array
    {
        $duration = in_array($duration, [7, 14, 30], true) ? $duration : 14;
        $goal = array_key_exists($goal, profit_playbook_goal_options()) ? $goal : 'seo_to_sales';
        $summary = function_exists('profit_action_dashboard_summary') ? profit_action_dashboard_summary($days, $filters) : [];
        $campaignId = profit_playbook_campaign_id($duration, $goal, $days, $filters);
        $base = profit_playbook_base_tasks($duration, $goal, $summary);
        $extra = profit_playbook_action_tasks($summary, $duration, $goal);
        $tasks = profit_playbook_merge_tasks($base, $extra, $duration);
        [$tasks, $completed] = profit_playbook_enrich_completion($tasks, $campaignId);
        $total = count($tasks);
        $progress = (int)round(($completed / max(1, $total)) * 100);
        $meta = profit_playbook_goal_meta($goal);
        $report = (array)($summary['report'] ?? []);
        $readiness = (array)($summary['readiness'] ?? []);

        $daily = [];
        for ($day = 1; $day <= $duration; $day++) {
            $rows = array_values(array_filter($tasks, static fn(array $task): bool => (int)($task['day'] ?? 0) === $day));
            if (!$rows) {
                $rows[] = profit_playbook_task([
                    'day' => $day,
                    'phase' => 'Buffer',
                    'title' => 'Buffer campaign dan review ringan',
                    'priority' => 'Buffer',
                    'objective' => 'Gunakan hari ini untuk menyelesaikan task yang tertunda atau cek data terbaru.',
                    'why' => 'Tidak semua campaign perlu padat setiap hari. Buffer membuat eksekusi tetap realistis untuk UMKM.',
                    'checklist' => ['Cek task yang belum selesai.', 'Pilih satu improvement kecil.', 'Catat hasilnya.'],
                    'kpi' => 'Tidak ada task penting tertinggal.',
                    'cta_label' => 'Profit Action',
                    'cta_url' => function_exists('url') ? url('admin/profit-action-dashboard') : '',
                ]);
            }
            $daily[] = [
                'day' => $day,
                'date_hint' => date('d M', strtotime('+' . ($day - 1) . ' days')),
                'tasks' => $rows,
                'completed' => count(array_filter($rows, static fn(array $task): bool => !empty($task['completed']))),
                'total' => count($rows),
            ];
        }

        $estimate = (int)($report['sales']['estimate'] ?? 0);
        $paid = (int)($report['sales']['paid_order_value'] ?? 0);
        $leak = max(0, $estimate - $paid);
        $leadEvents = (int)($report['lead']['events'] ?? 0);
        $orders = (int)($report['order']['total'] ?? 0);
        $paymentWaiting = (int)($report['order']['payment_waiting'] ?? 0);
        $pendingProofs = (int)($report['payment']['pending_proofs'] ?? 0);

        return [
            'generated_at' => date('c'),
            'campaign_id' => $campaignId,
            'duration' => $duration,
            'duration_label' => profit_playbook_duration_options()[$duration] ?? ($duration . ' Hari'),
            'goal' => $goal,
            'goal_meta' => $meta,
            'range_days' => $days,
            'filters' => $filters,
            'progress' => $progress,
            'completed' => $completed,
            'total_tasks' => $total,
            'summary' => $summary,
            'tasks' => $tasks,
            'daily' => $daily,
            'phases' => profit_playbook_phase_summary($tasks),
            'kpis' => [
                ['label' => 'Profit readiness', 'value' => (int)($readiness['total'] ?? 0) . '/100', 'note' => (string)($readiness['label'] ?? 'Perlu aksi')],
                ['label' => 'Lead/event', 'value' => (string)$leadEvents, 'note' => 'Sinyal traffic/lead periode terpilih'],
                ['label' => 'Order', 'value' => (string)$orders, 'note' => 'Order yang tercatat'],
                ['label' => 'Money leak', 'value' => function_exists('rupiah') ? rupiah($leak) : (string)$leak, 'note' => 'Estimasi potensi belum terbayar'],
                ['label' => 'Pending payment', 'value' => (string)($paymentWaiting + $pendingProofs), 'note' => 'Order/bukti bayar yang perlu dicek'],
            ],
            'recommendations' => [
                $meta['metric'],
                $progress >= 80 ? 'Campaign hampir selesai. Fokus ke review dan scale.' : 'Kerjakan task paling atas dulu, jangan semua channel dibuka bersamaan.',
                $leadEvents > 0 ? 'Sudah ada sinyal lead. CTA dan follow-up jangan dibiarkan kosong.' : 'Mulai dari offer, trust, dan konten awal sebelum campaign dipush besar.',
            ],
        ];
    }
}

if (!function_exists('profit_playbook_export_csv')) {
    function profit_playbook_export_csv(array $campaign): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="profit-playbook-' . date('Ymd-His') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['day', 'phase', 'status', 'priority', 'title', 'objective', 'why', 'checklist', 'kpi', 'asset', 'source', 'cta_url'], ',', '"', '\\', "\n");
        foreach ((array)($campaign['tasks'] ?? []) as $task) {
            fputcsv($out, [
                (int)($task['day'] ?? 0),
                (string)($task['phase'] ?? ''),
                !empty($task['completed']) ? 'Selesai' : 'Belum',
                (string)($task['priority'] ?? ''),
                (string)($task['title'] ?? ''),
                (string)($task['objective'] ?? ''),
                (string)($task['why'] ?? ''),
                implode(' | ', (array)($task['checklist'] ?? [])),
                (string)($task['kpi'] ?? ''),
                (string)($task['asset'] ?? ''),
                (string)($task['source'] ?? ''),
                (string)($task['cta_url'] ?? ''),
            ], ',', '"', '\\', "\n");
        }
        fclose($out);
        exit;
    }
}
