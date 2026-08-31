<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$durationOptions = function_exists('growth_sprint_duration_options') ? growth_sprint_duration_options() : [14 => '14 hari'];
$rangeOptions = function_exists('growth_sprint_range_options') ? growth_sprint_range_options() : [30 => '30 hari'];
$focusOptions = function_exists('growth_sprint_focus_options') ? growth_sprint_focus_options() : ['balanced' => 'Balanced Growth'];
$filterOptions = function_exists('growth_sprint_filter_options') ? growth_sprint_filter_options() : ['open' => 'Belum selesai'];
$statusOptions = function_exists('growth_sprint_status_options') ? growth_sprint_status_options() : ['planned' => 'Masuk kalender'];

$duration = (int)($_GET['duration'] ?? 14);
if (!isset($durationOptions[$duration])) {
    $duration = 14;
}
$days = (int)($_GET['days'] ?? 30);
if (!isset($rangeOptions[$days])) {
    $days = 30;
}
$focus = (string)($_GET['focus'] ?? 'balanced');
if (!isset($focusOptions[$focus])) {
    $focus = 'balanced';
}
$status = (string)($_GET['status'] ?? 'open');
if (!isset($filterOptions[$status])) {
    $status = 'open';
}

$baseUrl = static function (array $override = []) use ($duration, $days, $focus, $status): string {
    $query = array_merge([
        'duration' => $duration,
        'days' => $days,
        'focus' => $focus,
        'status' => $status,
    ], $override);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/seo-campaign-calendar?' . http_build_query($query));
};

$redirectBase = static function (string $message = '') use ($duration, $days, $focus, $status): string {
    $query = [
        'duration' => $duration,
        'days' => $days,
        'focus' => $focus,
        'status' => $status,
    ];
    if ($message !== '') {
        $query['message'] = $message;
    }
    return 'admin/seo-campaign-calendar?' . http_build_query($query);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'update_task') {
            growth_sprint_update_task(
                (string)($_POST['sprint_id'] ?? ''),
                (string)($_POST['task_id'] ?? ''),
                (string)($_POST['task_status'] ?? 'planned'),
                (string)($_POST['task_note'] ?? ''),
                (string)($_POST['owner'] ?? ''),
                (string)($_POST['due_date'] ?? '')
            );
            redirect_302($redirectBase('Status task sprint berhasil disimpan.'));
        }
        if ($action === 'reset_task') {
            growth_sprint_reset_task((string)($_POST['task_id'] ?? ''));
            redirect_302($redirectBase('Catatan task sprint sudah direset.'));
        }
        if ($action === 'save_note') {
            growth_sprint_save_note((string)($_POST['sprint_id'] ?? ''), (string)($_POST['sprint_note'] ?? ''));
            redirect_302($redirectBase('Catatan sprint berhasil disimpan.'));
        }
        if ($action === 'reset_all') {
            growth_sprint_reset_all();
            redirect_302($redirectBase('Semua catatan Growth Sprint sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = growth_sprint_summary($duration, $days, $focus, $status);
$sprintId = (string)($summary['sprint_id'] ?? '');
$tasks = (array)($summary['tasks'] ?? []);
$allTasks = (array)($summary['all_tasks'] ?? []);
$todayTasks = (array)($summary['today_tasks'] ?? []);
$daily = (array)($summary['daily'] ?? []);
$weekly = (array)($summary['weekly'] ?? []);
$kpis = (array)($summary['source_kpis'] ?? []);
$recommendations = (array)($summary['recommendations'] ?? []);
$sourceModules = (array)($summary['source_modules'] ?? []);

if (($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-campaign-calendar-growth-sprint-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_GET['export'] ?? '') === 'csv') {
    growth_sprint_export_csv($summary);
}
if (($_GET['export'] ?? '') === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-campaign-calendar-growth-sprint-' . date('Ymd-His') . '.txt"');
    echo growth_sprint_plain_text($summary);
    exit;
}

$formatMoney = static fn(int|float $value): string => function_exists('rupiah') ? rupiah($value) : 'Rp ' . number_format((float)$value, 0, ',', '.');
$formatNumber = static fn(int|float $value): string => number_format((float)$value, 0, ',', '.');
$statusLabel = static fn(string $value): string => (string)($statusOptions[$value] ?? ucfirst(str_replace('_', ' ', $value)));
$progress = (int)($summary['progress'] ?? 0);

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'SEO Campaign Calendar & Growth Sprint Planner - Admin',
    'description' => 'Kalender eksekusi SEO, CTA, offer, content refresh, money page, dan follow-up yang meneruskan Profit Report Builder tanpa membuat tracking baru.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-growth-sprint-shell">
    <section class="admin-hero admin-profit-report-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">SEO Campaign Calendar</div>
                <h1>Growth Sprint Planner</h1>
                <p>Turunkan laporan CEO menjadi kalender kerja SEO, CTA, offer, money page, content refresh, dan follow-up. Modul ini membaca data existing, bukan membuat tracking baru.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-report-builder')); ?>">Profit Report</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-playbook')); ?>">Profit Playbook</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-action-dashboard')); ?>">Profit Action</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Sprint Setting</span>
                        <h2>Atur kalender campaign</h2>
                        <p>Pilih durasi sprint, periode data, fokus kerja, dan status task. Semua rekomendasi diturunkan dari modul growth existing.</p>
                    </div>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'text'])); ?>">Export Teks</a>
                    </div>
                </div>
                <form method="get" class="admin-grid admin-grid--four">
                    <label>Durasi Sprint
                        <select name="duration" onchange="this.form.submit()">
                            <?php foreach ($durationOptions as $key => $label): ?>
                                <option value="<?= (int)$key; ?>" <?= $duration === (int)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Periode Data
                        <select name="days" onchange="this.form.submit()">
                            <?php foreach ($rangeOptions as $key => $label): ?>
                                <option value="<?= (int)$key; ?>" <?= $days === (int)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Fokus
                        <select name="focus" onchange="this.form.submit()">
                            <?php foreach ($focusOptions as $key => $label): ?>
                                <option value="<?= esc((string)$key); ?>" <?= $focus === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Status
                        <select name="status" onchange="this.form.submit()">
                            <?php foreach ($filterOptions as $key => $label): ?>
                                <option value="<?= esc((string)$key); ?>" <?= $status === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            </section>

            <div class="admin-cta-result-overview admin-profit-report-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Sprint Progress</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= $progress; ?>;">
                        <strong><?= $progress; ?></strong><span>%</span>
                    </div>
                    <h2><?= esc((string)($summary['focus_label'] ?? 'Balanced Growth')); ?></h2>
                    <p><?= esc((string)($summary['summary_text'] ?? 'Kalender sprint siap dipakai.')); ?></p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">CEO Context</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($kpis['executive_score'] ?? 0); ?>/100</strong> executive score</span>
                        <span><strong><?= esc($formatMoney((int)($kpis['sales_estimate'] ?? 0))); ?></strong> estimasi omzet</span>
                        <span><strong><?= (int)($kpis['orders'] ?? 0); ?></strong> order</span>
                        <span><strong><?= (int)($kpis['waiting_payment'] ?? 0); ?></strong> tunggu bayar</span>
                    </div>
                    <p>Data konteks ini diambil dari Profit Report Builder dan modul report existing.</p>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Task Pipeline</span>
                    <h2><?= (int)($summary['open_tasks'] ?? 0); ?> task belum selesai</h2>
                    <p><?= (int)($summary['completed_tasks'] ?? 0); ?> selesai dari <?= (int)($summary['total_tasks'] ?? 0); ?> task total. Ada <?= (int)($kpis['money_pages_high'] ?? 0); ?> money page high dan <?= (int)($kpis['content_refresh_high'] ?? 0); ?> content refresh high.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Money Page</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/lead-priority-scoring')); ?>">Lead Priority</a>
                    </div>
                </article>
            </div>

            <section class="admin-grid admin-grid--stats admin-report-main-stats">
                <article class="admin-card admin-report-metric"><span class="admin-badge">SEO Lead</span><h2><?= (int)($kpis['seo_pages_with_lead'] ?? 0); ?></h2><p>Halaman SEO yang sudah punya kontribusi lead.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">CTA Click</span><h2><?= (int)($kpis['cta_clicks'] ?? 0); ?></h2><p>Sinyal klik CTA dari Lead Tracking existing.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">Hot Lead</span><h2><?= (int)($kpis['hot_leads'] ?? 0); ?></h2><p>Lead prioritas yang perlu difollow-up cepat.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">Playbook</span><h2><?= (int)($kpis['playbook_progress'] ?? 0); ?>%</h2><p>Progress Profit Playbook sebagai referensi campaign.</p></article>
            </section>

            <div class="admin-grid admin-grid--two">
                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Prioritas Hari Ini</span>
                            <h2>Task yang paling layak dikerjakan dulu</h2>
                            <p>Daftar ini otomatis mengambil task open dari kalender sprint.</p>
                        </div>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php if (!$todayTasks): ?><p class="admin-muted">Tidak ada task hari ini. Coba ubah filter status atau durasi sprint.</p><?php endif; ?>
                        <?php foreach (array_slice($todayTasks, 0, 5) as $task): ?>
                            <article class="admin-mini-card">
                                <strong><?= esc((string)($task['title'] ?? 'Task')); ?></strong>
                                <p>Hari <?= (int)($task['day'] ?? 0); ?> · <?= esc((string)($task['source'] ?? 'Growth')); ?> · <?= esc((string)($task['priority'] ?? 'Medium')); ?></p>
                                <p><?= esc((string)($task['why'] ?? '')); ?></p>
                                <?php if (!empty($task['cta_url'])): ?><a href="<?= esc((string)$task['cta_url']); ?>"><?= esc((string)($task['cta_label'] ?? 'Buka')); ?></a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-card admin-profit-report-copy-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Copy Action Plan</span>
                            <h2>Teks siap kirim ke owner/CEO</h2>
                            <p>Admin bisa copy ringkasan sprint ini untuk update mingguan.</p>
                        </div>
                    </div>
                    <textarea rows="12" readonly onclick="this.select();"><?= esc(growth_sprint_plain_text($summary)); ?></textarea>
                </section>
            </div>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Weekly Sprint</span>
                        <h2>Ringkasan per minggu</h2>
                        <p>Gunakan ini untuk melihat beban kerja mingguan agar campaign tidak terlalu melebar.</p>
                    </div>
                </div>
                <div class="admin-grid admin-grid--stats">
                    <?php foreach ($weekly as $week): ?>
                        <article class="admin-card admin-card--soft">
                            <span class="admin-badge"><?= esc((string)($week['label'] ?? 'Minggu')); ?></span>
                            <h3><?= (int)($week['progress'] ?? 0); ?>% selesai</h3>
                            <p><?= (int)($week['completed'] ?? 0); ?> dari <?= (int)($week['total'] ?? 0); ?> task selesai.</p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Calendar View</span>
                        <h2>Kalender task <?= esc((string)($summary['duration_label'] ?? '')); ?></h2>
                        <p>Setiap task bisa diberi status, PIC, deadline, dan catatan. Tracking hasil tetap dibaca dari modul existing.</p>
                    </div>
                </div>
                <div class="admin-stack admin-stack--sm">
                    <?php if (!$tasks): ?>
                        <div class="admin-empty-state"><p>Tidak ada task sesuai filter. Coba pilih status semua atau fokus Balanced Growth.</p></div>
                    <?php endif; ?>
                    <?php foreach ($daily as $day): ?>
                        <?php if (empty($day['tasks'])) { continue; } ?>
                        <article class="admin-card admin-card--soft">
                            <div class="admin-form-head admin-form-head--split">
                                <div>
                                    <span class="admin-badge">Hari <?= (int)($day['day'] ?? 0); ?> · <?= esc((string)($day['date_hint'] ?? '')); ?></span>
                                    <h3><?= (int)($day['total'] ?? 0); ?> task campaign</h3>
                                    <p><?= (int)($day['completed'] ?? 0); ?> task sudah selesai/monitoring.</p>
                                </div>
                            </div>
                            <div class="admin-stack admin-stack--sm">
                                <?php foreach ((array)($day['tasks'] ?? []) as $task): ?>
                                    <div class="admin-mini-card">
                                        <div class="admin-form-head admin-form-head--split">
                                            <div>
                                                <span class="admin-badge"><?= esc((string)($task['source'] ?? 'Growth')); ?> · <?= esc((string)($task['priority'] ?? 'Medium')); ?> · <?= esc((string)($task['status_label'] ?? 'Masuk kalender')); ?></span>
                                                <h4><?= esc((string)($task['title'] ?? 'Task')); ?></h4>
                                                <p><?= esc((string)($task['objective'] ?? '')); ?></p>
                                                <p><strong>Kenapa:</strong> <?= esc((string)($task['why'] ?? '')); ?></p>
                                            </div>
                                            <div class="admin-cta-result-export-row">
                                                <?php if (!empty($task['cta_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$task['cta_url']); ?>"><?= esc((string)($task['cta_label'] ?? 'Buka')); ?></a><?php endif; ?>
                                                <?php if (!empty($task['target_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$task['target_url']); ?>" target="_blank" rel="noopener">Lihat Target</a><?php endif; ?>
                                            </div>
                                        </div>
                                        <ul class="admin-check-list">
                                            <?php foreach ((array)($task['checklist'] ?? []) as $check): ?>
                                                <li><?= esc((string)$check); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p><strong>KPI:</strong> <?= esc((string)($task['kpi'] ?? 'Ada progres.')); ?></p>
                                        <form method="post" class="admin-grid admin-grid--four">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="update_task">
                                            <input type="hidden" name="sprint_id" value="<?= esc($sprintId); ?>">
                                            <input type="hidden" name="task_id" value="<?= esc((string)($task['id'] ?? '')); ?>">
                                            <label>Status
                                                <select name="task_status">
                                                    <?php foreach ($statusOptions as $key => $label): ?>
                                                        <option value="<?= esc((string)$key); ?>" <?= (string)($task['status'] ?? 'planned') === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>PIC
                                                <input type="text" name="owner" value="<?= esc((string)($task['owner'] ?? '')); ?>" maxlength="80" placeholder="Admin/Tim">
                                            </label>
                                            <label>Deadline
                                                <input type="date" name="due_date" value="<?= esc((string)($task['due_date'] ?? '')); ?>">
                                            </label>
                                            <label>Catatan
                                                <input type="text" name="task_note" value="<?= esc((string)($task['note'] ?? '')); ?>" maxlength="900" placeholder="Progress/kendala singkat">
                                            </label>
                                            <div style="grid-column:1/-1" class="admin-cta-result-export-row">
                                                <button class="admin-btn admin-btn--primary" type="submit">Simpan Status</button>
                                                <button class="admin-btn admin-btn--soft" type="submit" name="action" value="reset_task" onclick="return confirm('Reset catatan task ini?');">Reset Task</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="admin-grid admin-grid--two">
                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Sumber Data</span>
                            <h2>Modul yang dibaca planner</h2>
                            <p>Planner ini hanya menyusun action calendar dari data dan modul yang sudah ada.</p>
                        </div>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php foreach ($sourceModules as $module): ?>
                            <article class="admin-mini-card"><strong><?= esc((string)$module); ?></strong><p>Berkontribusi sebagai sinyal/rekomendasi sprint.</p></article>
                        <?php endforeach; ?>
                    </div>
                </section>
                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Rekomendasi Sistem</span>
                            <h2>Catatan untuk admin</h2>
                            <p>Gunakan catatan ini saat menjelaskan arah campaign ke owner/CEO.</p>
                        </div>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php foreach ($recommendations as $rec): ?>
                            <article class="admin-mini-card"><p><?= esc((string)$rec); ?></p></article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Catatan Sprint</span>
                        <h2>Catatan rencana kerja untuk owner/CEO</h2>
                        <p>Simpan catatan tambahan agar sprint punya konteks: target minggu ini, kendala, dan next action.</p>
                    </div>
                </div>
                <form method="post" class="admin-stack admin-stack--sm">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="sprint_id" value="<?= esc($sprintId); ?>">
                    <label>Catatan Sprint
                        <textarea name="sprint_note" rows="5" maxlength="1500" placeholder="Contoh: minggu ini fokus 2 money page, follow-up lead hot, dan refresh 1 artikel lama..."><?= esc((string)($summary['note'] ?? '')); ?></textarea>
                    </label>
                    <div class="admin-cta-result-export-row">
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Catatan</button>
                    </div>
                </form>
            </section>

            <section class="admin-card admin-card--danger-zone">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Reset</span>
                        <h2>Reset catatan Growth Sprint</h2>
                        <p>Ini hanya menghapus status/catatan sprint. Data Tracking Lead, SEO, CTA, order, report, dan konten tidak ikut dihapus.</p>
                    </div>
                    <form method="post" onsubmit="return confirm('Reset semua catatan Growth Sprint?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_all">
                        <button class="admin-btn admin-btn--danger" type="submit">Reset Semua</button>
                    </form>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
