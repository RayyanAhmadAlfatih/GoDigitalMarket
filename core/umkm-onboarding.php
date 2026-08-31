<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| UMKM ONBOARDING ASSISTANT
|--------------------------------------------------------------------------
| A lightweight guided setup layer for admins who need a clear daily path
| after entering the dashboard for the first time.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('umkm_onboarding_storage_file')) {
    function umkm_onboarding_storage_file(): string
    {
        return STORAGE_PATH . '/umkm-onboarding-progress.json';
    }
}

if (!function_exists('umkm_onboarding_clean_text')) {
    function umkm_onboarding_clean_text(mixed $value, int $limit = 180): string
    {
        $text = trim(strip_tags((string)$value));
        $text = preg_replace('/\s+/', ' ', $text) ?: '';
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $limit);
        }
        return substr($text, 0, $limit);
    }
}

if (!function_exists('umkm_onboarding_non_default_text')) {
    function umkm_onboarding_non_default_text(string $value, array $defaults): bool
    {
        $value = trim(strtolower($value));
        if ($value === '') {
            return false;
        }
        foreach ($defaults as $default) {
            if ($value === trim(strtolower((string)$default))) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('umkm_onboarding_default_state')) {
    function umkm_onboarding_default_state(): array
    {
        return [
            'started_at' => date('Y-m-d'),
            'manual_completed' => [],
            'updated_at' => '',
        ];
    }
}

if (!function_exists('umkm_onboarding_normalize_state')) {
    function umkm_onboarding_normalize_state(array $state): array
    {
        $defaults = umkm_onboarding_default_state();
        $startedAt = umkm_onboarding_clean_text($state['started_at'] ?? $defaults['started_at'], 20);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startedAt)) {
            $startedAt = $defaults['started_at'];
        }

        $manual = [];
        foreach ((array)($state['manual_completed'] ?? []) as $key => $value) {
            $cleanKey = preg_replace('/[^a-z0-9_\-]/i', '', (string)$key) ?: '';
            if ($cleanKey !== '' && !empty($value)) {
                $manual[$cleanKey] = true;
            }
        }

        return [
            'started_at' => $startedAt,
            'manual_completed' => $manual,
            'updated_at' => umkm_onboarding_clean_text($state['updated_at'] ?? '', 60),
        ];
    }
}

if (!function_exists('umkm_onboarding_write_state')) {
    function umkm_onboarding_write_state(array $state, bool $throw = false): bool
    {
        $state = umkm_onboarding_normalize_state($state);
        $state['updated_at'] = date(DATE_ATOM);

        if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
            if ($throw) {
                throw new RuntimeException('Folder storage belum bisa dibuat.');
            }
            return false;
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents(umkm_onboarding_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
            if ($throw) {
                throw new RuntimeException('Progress onboarding belum bisa disimpan. Cek permission folder storage.');
            }
            return false;
        }

        @chmod(umkm_onboarding_storage_file(), 0644);
        return true;
    }
}

if (!function_exists('umkm_onboarding_state')) {
    function umkm_onboarding_state(): array
    {
        static $cached = null;
        if (is_array($cached)) {
            return $cached;
        }

        $file = umkm_onboarding_storage_file();
        if (!is_file($file)) {
            $cached = umkm_onboarding_default_state();
            umkm_onboarding_write_state($cached, false);
            return $cached;
        }

        $decoded = json_decode((string)file_get_contents($file), true);
        if (!is_array($decoded)) {
            $cached = umkm_onboarding_default_state();
            umkm_onboarding_write_state($cached, false);
            return $cached;
        }

        $cached = umkm_onboarding_normalize_state($decoded);
        return $cached;
    }
}

if (!function_exists('umkm_onboarding_reset')) {
    function umkm_onboarding_reset(): void
    {
        umkm_onboarding_write_state(umkm_onboarding_default_state(), true);
        if (function_exists('activity_log_record')) {
            activity_log_record('reset', 'umkm-onboarding', null, 'Mengulang panduan onboarding UMKM.');
        }
    }
}

if (!function_exists('umkm_onboarding_set_manual_completed')) {
    function umkm_onboarding_set_manual_completed(string $key, bool $completed): void
    {
        $key = preg_replace('/[^a-z0-9_\-]/i', '', $key) ?: '';
        if ($key === '') {
            return;
        }

        $state = umkm_onboarding_state();
        $manual = (array)($state['manual_completed'] ?? []);
        if ($completed) {
            $manual[$key] = true;
        } else {
            unset($manual[$key]);
        }

        $state['manual_completed'] = $manual;
        umkm_onboarding_write_state($state, true);

        if (function_exists('activity_log_record')) {
            activity_log_record($completed ? 'complete' : 'reopen', 'umkm-onboarding', null, ($completed ? 'Menandai selesai: ' : 'Membuka ulang: ') . $key);
        }
    }
}

if (!function_exists('umkm_onboarding_active_count')) {
    function umkm_onboarding_active_count(array $rows, string $statusField = 'status', array $inactive = ['draft', 'inactive', 'archived']): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $status = strtolower(trim((string)($row[$statusField] ?? 'published')));
            if (!in_array($status, $inactive, true)) {
                $count++;
            }
        }
        return $count;
    }
}

if (!function_exists('umkm_onboarding_context')) {
    function umkm_onboarding_context(): array
    {
        $theme = function_exists('theme_settings') ? theme_settings() : [];
        $business = function_exists('business_settings') ? business_settings() : [];
        $starter = function_exists('starter_wizard_state') ? starter_wizard_state() : [];
        $homepage = function_exists('homepage_settings') ? homepage_settings() : [];
        $products = function_exists('all_products') ? all_products() : [];
        $articles = function_exists('all_articles') ? all_articles() : [];
        $forms = function_exists('custom_form_read_forms') ? custom_form_read_forms() : [];
        $trust = function_exists('trust_conversion_summary') ? trust_conversion_summary() : ['enabled_blocks' => 0, 'total_items' => 0];
        $seo = function_exists('seo_quality_summary') ? seo_quality_summary('all') : [];
        $readiness = function_exists('launch_readiness_report') ? launch_readiness_report() : ['score' => 0, 'status' => 'Perlu Setup Awal'];

        $businessName = (string)($theme['business_name'] ?? SITE_NAME);
        $tagline = (string)($theme['tagline'] ?? '');
        $description = (string)($theme['description'] ?? '');
        $wa = trim((string)($theme['whatsapp'] ?? $theme['phone'] ?? ''));
        $email = trim((string)($theme['email'] ?? ''));
        $logo = trim((string)($theme['logo_url'] ?? $theme['logo'] ?? ''));
        $hero = (array)($homepage['hero'] ?? []);
        $heroTitle = (string)($hero['title'] ?? '');
        $heroDescription = (string)($hero['description'] ?? '');

        $activeProducts = umkm_onboarding_active_count($products);
        $activeArticles = umkm_onboarding_active_count($articles);
        $activeForms = umkm_onboarding_active_count($forms, 'status', ['draft', 'inactive', 'archived']);

        return [
            'brand_ready' => umkm_onboarding_non_default_text($businessName, ['UMKM Commerce Template']) && ($tagline !== '' || $description !== ''),
            'contact_ready' => $wa !== '' || $email !== '',
            'logo_ready' => $logo !== '',
            'business_mode_ready' => trim((string)($business['business_mode'] ?? '')) !== '',
            'starter_ready' => trim((string)($starter['setup_mode'] ?? '')) !== '',
            'homepage_ready' => umkm_onboarding_non_default_text($heroTitle, ['Website Growth untuk Bisnis Anda', 'Bangun Website UMKM yang Siap SEO, Lead, dan Scale']) || $heroDescription !== '',
            'homepage_order_ready' => count((array)($homepage['section_order'] ?? [])) >= 5,
            'products_count' => $activeProducts,
            'articles_count' => $activeArticles,
            'forms_count' => $activeForms,
            'trust_blocks_count' => (int)($trust['enabled_blocks'] ?? 0),
            'trust_items_count' => (int)($trust['total_items'] ?? 0),
            'seo_score' => (int)($seo['score'] ?? $seo['avg_score'] ?? 0),
            'launch_score' => (int)($readiness['score'] ?? 0),
            'launch_status' => (string)($readiness['status'] ?? 'Perlu Setup Awal'),
        ];
    }
}

if (!function_exists('umkm_onboarding_check')) {
    function umkm_onboarding_check(string $label, bool $done, string $href = '', string $action = ''): array
    {
        return [
            'label' => $label,
            'done' => $done,
            'href' => $href,
            'action' => $action,
        ];
    }
}

if (!function_exists('umkm_onboarding_task_definitions')) {
    function umkm_onboarding_task_definitions(?array $context = null): array
    {
        $context = $context ?? umkm_onboarding_context();

        return [
            [
                'key' => 'brand_identity',
                'day' => 1,
                'badge' => 'Hari 1',
                'title' => 'Isi identitas brand dulu',
                'summary' => 'Mulai dari nama bisnis, tagline, deskripsi singkat, kontak, logo, dan warna utama.',
                'why' => 'Ini jadi pondasi header, footer, CTA, SEO fallback, dan tampilan dashboard.',
                'action_label' => 'Buka Brand & Warna',
                'href' => url('admin/brand'),
                'checks' => [
                    umkm_onboarding_check('Nama bisnis dan deskripsi sudah tidak memakai bawaan template.', (bool)$context['brand_ready'], url('admin/brand'), 'Lengkapi profil brand'),
                    umkm_onboarding_check('Kontak WhatsApp atau email sudah tersedia.', (bool)$context['contact_ready'], url('admin/brand'), 'Isi kontak bisnis'),
                    umkm_onboarding_check('Logo atau aset brand sudah disiapkan.', (bool)$context['logo_ready'], url('admin/brand'), 'Cek logo dan media'),
                ],
            ],
            [
                'key' => 'business_direction',
                'day' => 2,
                'badge' => 'Hari 2',
                'title' => 'Tentukan arah bisnis dan starter path',
                'summary' => 'Pilih mode bisnis, kategori, dan cara mulai: preset, struktur kosong, atau custom dari nol.',
                'why' => 'Arah bisnis yang jelas bikin label menu, kategori, konten, dan homepage lebih nyambung.',
                'action_label' => 'Buka Mode Bisnis',
                'href' => url('admin/business'),
                'checks' => [
                    umkm_onboarding_check('Mode bisnis sudah dipilih.', (bool)$context['business_mode_ready'], url('admin/business'), 'Atur mode bisnis'),
                    umkm_onboarding_check('Starter path sudah ditentukan.', (bool)$context['starter_ready'], url('admin/starter-wizard'), 'Buka starter wizard'),
                ],
            ],
            [
                'key' => 'homepage_message',
                'day' => 3,
                'badge' => 'Hari 3',
                'title' => 'Rapikan homepage dan pesan utama',
                'summary' => 'Atur sumber homepage, headline hero, tombol CTA, section aktif, dan urutan section.',
                'why' => 'Homepage adalah tempat pertama calon customer memahami value bisnis dan langkah berikutnya.',
                'action_label' => 'Buka Atur Beranda',
                'href' => url('admin/homepage'),
                'checks' => [
                    umkm_onboarding_check('Headline atau deskripsi hero sudah disesuaikan.', (bool)$context['homepage_ready'], url('admin/homepage'), 'Edit hero homepage'),
                    umkm_onboarding_check('Urutan section homepage sudah tersedia.', (bool)$context['homepage_order_ready'], url('admin/homepage'), 'Cek tab Urutan'),
                ],
            ],
            [
                'key' => 'catalog_offer',
                'day' => 4,
                'badge' => 'Hari 4',
                'title' => 'Tambahkan produk, jasa, atau penawaran utama',
                'summary' => 'Isi minimal satu produk/jasa agar website tidak cuma terlihat seperti company profile kosong.',
                'why' => 'Calon customer perlu melihat apa yang ditawarkan sebelum menghubungi atau checkout.',
                'action_label' => 'Kelola Katalog',
                'href' => url('admin/produk'),
                'checks' => [
                    umkm_onboarding_check('Minimal 1 produk/jasa aktif sudah tersedia.', ((int)$context['products_count']) >= 1, url('admin/produk'), 'Tambah produk/jasa'),
                    umkm_onboarding_check('Idealnya mulai dengan 3 item agar katalog terasa hidup.', ((int)$context['products_count']) >= 3, url('admin/produk'), 'Lengkapi katalog awal'),
                ],
            ],
            [
                'key' => 'trust_conversion',
                'day' => 5,
                'badge' => 'Hari 5',
                'title' => 'Bangun kepercayaan dan CTA',
                'summary' => 'Aktifkan benefit, testimoni, FAQ, garansi, badge trust, before-after, dan CTA block.',
                'why' => 'Trust block membantu menjawab keraguan pengunjung sebelum mereka klik WhatsApp, form, atau checkout.',
                'action_label' => 'Buka Trust Block',
                'href' => url('admin/trust-conversion'),
                'checks' => [
                    umkm_onboarding_check('Minimal 3 trust/conversion block aktif.', ((int)$context['trust_blocks_count']) >= 3, url('admin/trust-conversion'), 'Aktifkan block penting'),
                    umkm_onboarding_check('Isi poin trust sudah cukup untuk dibaca pengunjung.', ((int)$context['trust_items_count']) >= 6, url('admin/trust-conversion'), 'Lengkapi isi block'),
                ],
            ],
            [
                'key' => 'lead_capture',
                'day' => 6,
                'badge' => 'Hari 6',
                'title' => 'Siapkan form lead atau checkout',
                'summary' => 'Pastikan pengunjung punya jalur jelas untuk tanya, isi kebutuhan, daftar, atau checkout.',
                'why' => 'Traffic tanpa form/CTA yang jelas sering lewat begitu saja tanpa jadi prospek.',
                'action_label' => 'Kelola Form',
                'href' => url('admin/forms'),
                'checks' => [
                    umkm_onboarding_check('Minimal 1 form aktif tersedia.', ((int)$context['forms_count']) >= 1, url('admin/forms'), 'Aktifkan form'),
                    umkm_onboarding_check('Katalog dan form sudah sama-sama siap.', ((int)$context['products_count']) >= 1 && ((int)$context['forms_count']) >= 1, url('admin/form-checkout'), 'Cek alur checkout/form'),
                ],
            ],
            [
                'key' => 'seo_launch',
                'day' => 7,
                'badge' => 'Hari 7',
                'title' => 'Cek SEO dan kesiapan launch',
                'summary' => 'Review SEO dasar, artikel awal, dan Launch Readiness sebelum mulai promosi.',
                'why' => 'Sebelum website dibagikan, pastikan halaman penting, konten, CTA, dan sistem dasar sudah aman.',
                'action_label' => 'Buka Launch Readiness',
                'href' => url('admin/launch-readiness'),
                'checks' => [
                    umkm_onboarding_check('Minimal 2 artikel/konten SEO awal tersedia.', ((int)$context['articles_count']) >= 2, url('admin/artikel'), 'Tambah artikel awal'),
                    umkm_onboarding_check('Skor SEO dasar minimal 60.', ((int)$context['seo_score']) >= 60, url('admin/universal-seo'), 'Cek SEO dasar'),
                    umkm_onboarding_check('Launch Readiness minimal 65%.', ((int)$context['launch_score']) >= 65, url('admin/launch-readiness'), 'Cek kesiapan launch'),
                ],
            ],
        ];
    }
}

if (!function_exists('umkm_onboarding_current_day')) {
    function umkm_onboarding_current_day(array $state): int
    {
        try {
            $today = new DateTimeImmutable('today');
            $start = new DateTimeImmutable((string)($state['started_at'] ?? date('Y-m-d')));
            if ($start > $today) {
                return 1;
            }
            return min(7, max(1, ((int)$start->diff($today)->days) + 1));
        } catch (Throwable $e) {
            return 1;
        }
    }
}

if (!function_exists('umkm_onboarding_enrich_task')) {
    function umkm_onboarding_enrich_task(array $task, array $manualCompleted): array
    {
        $checks = (array)($task['checks'] ?? []);
        $doneChecks = 0;
        foreach ($checks as $check) {
            if (!empty($check['done'])) {
                $doneChecks++;
            }
        }

        $totalChecks = count($checks);
        $autoDone = $totalChecks > 0 && $doneChecks >= $totalChecks;
        $manualDone = !empty($manualCompleted[(string)($task['key'] ?? '')]);
        $task['done_checks'] = $doneChecks;
        $task['total_checks'] = $totalChecks;
        $task['auto_done'] = $autoDone;
        $task['manual_done'] = $manualDone;
        $task['done'] = $autoDone || $manualDone;
        $task['status'] = $task['done'] ? 'ok' : ($doneChecks > 0 ? 'progress' : 'todo');
        $task['progress'] = $totalChecks > 0 ? (int)round(($doneChecks / $totalChecks) * 100) : 0;

        return $task;
    }
}

if (!function_exists('umkm_onboarding_report')) {
    function umkm_onboarding_report(): array
    {
        $state = umkm_onboarding_state();
        $context = umkm_onboarding_context();
        $manual = (array)($state['manual_completed'] ?? []);
        $tasks = array_map(static fn(array $task): array => umkm_onboarding_enrich_task($task, $manual), umkm_onboarding_task_definitions($context));

        $total = count($tasks);
        $done = count(array_filter($tasks, static fn(array $task): bool => !empty($task['done'])));
        $inProgress = count(array_filter($tasks, static fn(array $task): bool => ($task['status'] ?? '') === 'progress'));
        $score = $total > 0 ? (int)round(($done / $total) * 100) : 0;
        $currentDay = umkm_onboarding_current_day($state);
        $todayTask = $tasks[$currentDay - 1] ?? ($tasks[0] ?? []);
        $nextTodo = null;
        foreach ($tasks as $task) {
            if (empty($task['done'])) {
                $nextTodo = $task;
                break;
            }
        }
        $tomorrowTask = $tasks[min(6, $currentDay)] ?? null;

        return [
            'state' => $state,
            'context' => $context,
            'tasks' => $tasks,
            'current_day' => $currentDay,
            'today_task' => $todayTask,
            'tomorrow_task' => $tomorrowTask,
            'next_todo' => $nextTodo,
            'total_tasks' => $total,
            'done_tasks' => $done,
            'in_progress_tasks' => $inProgress,
            'score' => $score,
            'status' => $score >= 100 ? 'Panduan selesai' : ($score >= 60 ? 'Sudah on track' : 'Mulai dari langkah penting'),
        ];
    }
}
