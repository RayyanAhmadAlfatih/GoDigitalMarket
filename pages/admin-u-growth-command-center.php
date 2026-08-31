<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$rangeOptions = function_exists('ugrowth_command_range_options') ? ugrowth_command_range_options() : [30 => '30 hari terakhir'];
$focusOptions = function_exists('ugrowth_command_focus_options') ? ugrowth_command_focus_options() : ['overview' => 'Overview Growth'];

$days = (int)($_GET['days'] ?? 30);
if (!isset($rangeOptions[$days])) {
    $days = 30;
}
$focus = (string)($_GET['focus'] ?? 'overview');
if (!isset($focusOptions[$focus])) {
    $focus = 'overview';
}

$baseUrl = static function (array $override = []) use ($days, $focus): string {
    $query = array_merge(['days' => $days, 'focus' => $focus], $override);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/u-growth-command-center?' . http_build_query($query));
};

$redirectBase = static function (string $message = '') use ($days, $focus): string {
    $query = ['days' => $days, 'focus' => $focus];
    if ($message !== '') {
        $query['message'] = $message;
    }
    return 'admin/u-growth-command-center?' . http_build_query($query);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_note') {
            ugrowth_command_save_note((string)($_POST['command_note'] ?? ''), (string)($_POST['owner'] ?? ''));
            redirect_302($redirectBase('Catatan Command Center berhasil disimpan.'));
        }
        if ($action === 'reset_notes') {
            ugrowth_command_reset_notes();
            redirect_302($redirectBase('Catatan Command Center sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = ugrowth_command_center_summary($days, $focus);

if (($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="u-growth-command-center-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_GET['export'] ?? '') === 'csv') {
    ugrowth_command_export_csv($summary);
}
if (($_GET['export'] ?? '') === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="u-growth-command-center-' . date('Ymd-His') . '.txt"');
    echo ugrowth_command_plain_text($summary);
    exit;
}

$kpis = (array)($summary['kpis'] ?? []);
$modules = (array)($summary['modules'] ?? []);
$commands = (array)($summary['today_commands'] ?? []);
$bottlenecks = (array)($summary['bottlenecks'] ?? []);
$note = (array)($summary['note'] ?? []);
$formatMoney = static fn(int|float $value): string => function_exists('rupiah') ? rupiah($value) : 'Rp ' . number_format((float)$value, 0, ',', '.');
$formatNumber = static fn(int|float $value): string => number_format((float)$value, 0, ',', '.');

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'U-Growth Command Center - Admin',
    'description' => 'Pusat komando growth untuk membaca profit, SEO, CTA, lead, sprint, dan action plan dari data existing tanpa membuat tracking baru.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-command-center-shell">
    <section class="admin-hero admin-profit-report-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">U-Growth Command Center</div>
                <h1>Pusat Komando Growth</h1>
                <p>Ringkas semua sinyal penting: profit action, SEO attribution, CTA result, money page, content refresh, lead scoring, dan sprint campaign. Modul ini membaca data existing, bukan membuat tracking baru.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-action-dashboard')); ?>">Profit Action</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-report-builder')); ?>">Profit Report</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-campaign-calendar')); ?>">Growth Sprint</a>
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
                        <span class="admin-badge">Control Panel</span>
                        <h2>Atur sudut pandang command center</h2>
                        <p>Pilih periode dan fokus kerja. Semua angka tetap ditarik dari modul growth dan tracking existing.</p>
                    </div>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'text'])); ?>">Export Teks</a>
                    </div>
                </div>
                <form method="get" class="admin-grid admin-grid--two">
                    <label>Periode Data
                        <select name="days" onchange="this.form.submit()">
                            <?php foreach ($rangeOptions as $key => $label): ?>
                                <option value="<?= (int)$key; ?>" <?= $days === (int)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Fokus Komando
                        <select name="focus" onchange="this.form.submit()">
                            <?php foreach ($focusOptions as $key => $label): ?>
                                <option value="<?= esc((string)$key); ?>" <?= $focus === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            </section>

            <div class="admin-cta-result-overview admin-profit-report-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Command Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['command_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['command_score'] ?? 0); ?></strong><span>%</span>
                    </div>
                    <h2><?= esc((string)($summary['score_label'] ?? 'Growth')); ?></h2>
                    <p><?= esc((string)($summary['headline'] ?? 'Pusat komando growth siap dipakai.')); ?></p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Profit Snapshot</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= esc($formatMoney((int)($kpis['sales_estimate'] ?? 0))); ?></strong> estimasi omzet</span>
                        <span><strong><?= (int)($kpis['orders'] ?? 0); ?></strong> order</span>
                        <span><strong><?= (int)($kpis['waiting_payment'] ?? 0); ?></strong> tunggu bayar</span>
                        <span><strong><?= (int)($kpis['hot_leads'] ?? 0); ?></strong> hot lead</span>
                    </div>
                    <p>Dipakai untuk menjawab: profit bocor di mana dan action paling dekat ke closing apa.</p>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Execution Snapshot</span>
                    <h2><?= (int)($kpis['open_sprint_tasks'] ?? 0); ?> task sprint open</h2>
                    <p><?= (int)($kpis['today_actions'] ?? 0); ?> action profit hari ini, <?= (int)($kpis['internal_link_recommendations'] ?? 0); ?> rekomendasi internal link/CTA, dan <?= (int)($kpis['content_refresh_high'] ?? 0); ?> content refresh high priority.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-campaign-calendar')); ?>">Buka Sprint</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/lead-priority-scoring')); ?>">Lead Priority</a>
                    </div>
                </article>
            </div>

            <section class="admin-grid admin-grid--stats admin-report-main-stats">
                <article class="admin-card admin-report-metric"><span class="admin-badge">CTA Click</span><h2><?= esc($formatNumber((int)($kpis['cta_clicks'] ?? 0))); ?></h2><p>Sinyal klik CTA dari Lead Tracking existing.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">CTA Lead</span><h2><?= esc($formatNumber((int)($kpis['cta_leads'] ?? 0))); ?></h2><p>Lead yang terbaca dari jembatan CTA Result.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">SEO Butuh CTA</span><h2><?= esc($formatNumber((int)($kpis['seo_pages_need_cta'] ?? 0))); ?></h2><p>Halaman SEO yang perlu diarahkan ke offer/form.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">Deploy Progress</span><h2><?= (int)($kpis['deployment_progress'] ?? 0); ?>%</h2><p>Progress rata-rata checklist money page.</p></article>
            </section>

            <div class="admin-grid admin-grid--two">
                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Prioritas Hari Ini</span>
                            <h2>Action yang paling dekat ke growth</h2>
                            <p>Gabungan dari Profit Action, Growth Sprint, Profit Report, Money Page, Content Refresh, Internal Link, dan Lead Priority.</p>
                        </div>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php if (!$commands): ?><p class="admin-muted">Belum ada action prioritas. Coba isi data lead, order, CTA, atau jalankan setup growth dasar.</p><?php endif; ?>
                        <?php foreach (array_slice($commands, 0, 8) as $command): ?>
                            <article class="admin-mini-card">
                                <strong><?= esc((string)($command['title'] ?? 'Action')); ?></strong>
                                <p><?= esc((string)($command['source'] ?? 'Growth')); ?> · <?= esc((string)($command['priority'] ?? 'Medium')); ?></p>
                                <?php if (!empty($command['why'])): ?><p><?= esc((string)$command['why']); ?></p><?php endif; ?>
                                <?php if (!empty($command['checklist'])): ?>
                                    <ul class="admin-check-list">
                                        <?php foreach (array_slice((array)$command['checklist'], 0, 3) as $check): ?><li><?= esc((string)$check); ?></li><?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if (!empty($command['cta_url'])): ?><a href="<?= esc((string)$command['cta_url']); ?>"><?= esc((string)($command['cta_label'] ?? 'Buka')); ?></a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-card admin-profit-report-copy-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Owner Brief</span>
                            <h2>Ringkasan siap copy</h2>
                            <p>Untuk admin yang perlu menjawab owner/CEO: kondisi sekarang dan action berikutnya.</p>
                        </div>
                    </div>
                    <textarea rows="18" readonly onclick="this.select();"><?= esc((string)($summary['owner_brief'] ?? '')); ?></textarea>
                    <p class="admin-muted">Klik area teks untuk select, lalu copy ke WhatsApp/email/laporan internal.</p>
                </section>
            </div>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Module Health</span>
                        <h2>Status modul growth</h2>
                        <p>Ini bukan menu tracking baru. Command Center hanya merangkum status dari modul yang sudah ada.</p>
                    </div>
                </div>
                <div class="admin-grid admin-grid--three">
                    <?php foreach ($modules as $module): ?>
                        <article class="admin-mini-card">
                            <span class="admin-badge"><?= esc((string)($module['category'] ?? 'Growth')); ?></span>
                            <h3><?= esc((string)($module['title'] ?? 'Module')); ?></h3>
                            <p><strong><?= (int)($module['score'] ?? 0); ?>/100</strong> · <?= esc((string)($module['status'] ?? 'Pantau')); ?></p>
                            <p><?= esc((string)($module['summary'] ?? '')); ?></p>
                            <p class="admin-muted"><?= esc((string)($module['primary_metric'] ?? '')); ?><?= !empty($module['secondary_metric']) ? ' · ' . esc((string)$module['secondary_metric']) : ''; ?></p>
                            <?php if (!empty($module['url'])): ?><a href="<?= esc((string)$module['url']); ?>"><?= esc((string)($module['cta_label'] ?? 'Buka modul')); ?></a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Bottleneck Map</span>
                        <h2>Peta bocor growth</h2>
                        <p>Baca alur dari SEO ke CTA, lead, closing, dan eksekusi agar admin tahu titik mana yang harus dibereskan dulu.</p>
                    </div>
                </div>
                <div class="admin-stack admin-stack--sm">
                    <?php foreach ($bottlenecks as $item): ?>
                        <article class="admin-mini-card">
                            <strong><?= esc((string)($item['stage'] ?? 'Stage')); ?> · <?= esc((string)($item['status'] ?? 'Pantau')); ?></strong>
                            <p><?= esc((string)($item['metric'] ?? '')); ?></p>
                            <p><?= esc((string)($item['action'] ?? '')); ?></p>
                            <?php if (!empty($item['url'])): ?><a href="<?= esc((string)$item['url']); ?>">Buka area terkait</a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Catatan Komando</span>
                        <h2>Catatan kerja minggu ini</h2>
                        <p>Simpan catatan singkat agar tim tahu fokus utama, PIC, dan kendala. Catatan ini tidak mengubah data tracking.</p>
                    </div>
                </div>
                <form method="post" class="admin-stack admin-stack--sm">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="save_note">
                    <div class="admin-grid admin-grid--two">
                        <label>PIC / Owner
                            <input type="text" name="owner" maxlength="80" value="<?= esc((string)($note['owner'] ?? '')); ?>" placeholder="Contoh: Admin Marketing">
                        </label>
                        <label>Terakhir Update
                            <input type="text" readonly value="<?= esc((string)($note['updated_at'] ?? '-')); ?>">
                        </label>
                    </div>
                    <label>Catatan Command Center
                        <textarea name="command_note" rows="5" maxlength="2000" placeholder="Contoh: Minggu ini fokus deploy 2 money page, refresh 1 artikel lama, dan follow-up order pending..."><?= esc((string)($note['note'] ?? '')); ?></textarea>
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
                        <h2>Reset catatan Command Center</h2>
                        <p>Ini hanya menghapus catatan Command Center. Data Lead Tracking, order, SEO, CTA, report, dan sprint tidak ikut dihapus.</p>
                    </div>
                    <form method="post" onsubmit="return confirm('Reset catatan U-Growth Command Center?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_notes">
                        <button class="admin-btn admin-btn--danger" type="submit">Reset Catatan</button>
                    </form>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
