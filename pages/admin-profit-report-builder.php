<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$rangeOptions = function_exists('profit_report_range_options') ? profit_report_range_options() : [30 => '30 hari'];
$days = (int)($_GET['days'] ?? 30);
if (!isset($rangeOptions[$days])) {
    $days = 30;
}

$baseUrl = static function (array $override = []) use ($days): string {
    $query = array_merge(['days' => $days], $override);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/profit-report-builder' . ($query ? '?' . http_build_query($query) : ''));
};

$redirectBase = static function (string $message = '') use ($days): string {
    $query = ['days' => $days];
    if ($message !== '') {
        $query['message'] = $message;
    }
    return 'admin/profit-report-builder?' . http_build_query($query);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'save_note') {
            profit_report_update_note(
                (string)($_POST['note_id'] ?? 'ceo-summary'),
                (string)($_POST['note_title'] ?? 'Catatan Owner/CEO'),
                (string)($_POST['note'] ?? ''),
                (string)($_POST['owner'] ?? '')
            );
            redirect_302($redirectBase('Catatan laporan berhasil disimpan.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = profit_report_builder_summary($days);
$kpis = (array)($summary['kpis'] ?? []);
$moneyLeaks = (array)($summary['money_leaks'] ?? []);
$topSeoPages = (array)($summary['top_seo_pages'] ?? []);
$topCtas = (array)($summary['top_ctas'] ?? []);
$topLeads = (array)($summary['top_leads'] ?? []);
$actionPlan = (array)($summary['action_plan'] ?? []);
$sourceFocus = (array)($summary['source_focus'] ?? []);
$notes = (array)($summary['notes'] ?? []);
$currentNote = (array)($notes['ceo-summary'] ?? []);

if (($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="profit-report-builder-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['export'] ?? '') === 'csv') {
    profit_report_export_kpi_csv($summary);
}

if (($_GET['export'] ?? '') === 'text') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="profit-report-builder-' . date('Ymd-His') . '.txt"');
    echo profit_report_plain_text($summary);
    exit;
}

$formatMoney = static fn(int|float $value): string => function_exists('profit_report_money') ? profit_report_money($value) : 'Rp ' . number_format((float)$value, 0, ',', '.');
$formatNumber = static fn(int|float $value): string => function_exists('profit_report_number') ? profit_report_number($value) : number_format((float)$value, 0, ',', '.');
$score = (int)($summary['executive_score'] ?? 0);
$scoreLabel = $score >= 80 ? 'Siap Scale' : ($score >= 60 ? 'Momentum Bagus' : ($score >= 40 ? 'Perlu Eksekusi' : 'Masih Fondasi'));

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Profit Report Builder - Admin',
    'description' => 'Bangun laporan CEO/owner berbasis data SEO, CTA, lead, order, payment, money leak, dan action plan tanpa membuat tracking baru.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-profit-report-shell">
    <section class="admin-hero admin-profit-report-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Executive Growth Report</div>
                <h1>Profit Report Builder</h1>
                <p>Buat laporan ringkas untuk owner/CEO dari data existing: SEO, CTA, Tracking Lead, order, payment, lead quality, money page, dan action plan mingguan.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/reports')); ?>">Laporan & Insight</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-profit-attribution')); ?>">SEO Profit</a>
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
                        <span class="admin-badge">Periode Laporan</span>
                        <h2>Pilih rentang laporan CEO/owner</h2>
                        <p>Semua angka diambil dari sistem existing. Modul ini tidak membuat tracking baru, hanya merangkum data agar keputusan bisnis lebih jelas.</p>
                    </div>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'text'])); ?>">Export Teks</a>
                    </div>
                </div>
                <div class="admin-cta-result-range-tabs">
                    <?php foreach ($rangeOptions as $rangeDays => $rangeLabel): ?>
                        <a class="<?= $days === (int)$rangeDays ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['days' => (int)$rangeDays])); ?>"><?= esc((string)$rangeLabel); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="admin-cta-result-overview admin-profit-report-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Executive Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= $score; ?>;">
                        <strong><?= $score; ?></strong><span>/100</span>
                    </div>
                    <h2><?= esc($scoreLabel); ?></h2>
                    <p><?= esc((string)($summary['executive_summary'] ?? 'Belum ada ringkasan.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Revenue Signal</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= esc($formatMoney((int)($kpis['sales_estimate'] ?? 0))); ?></strong> estimasi omzet</span>
                        <span><strong><?= esc((string)(int)($kpis['orders'] ?? 0)); ?></strong> order</span>
                        <span><strong><?= esc((string)(int)($kpis['waiting_payment'] ?? 0)); ?></strong> tunggu bayar</span>
                        <span><strong><?= esc($formatMoney((int)($kpis['average_order_value'] ?? 0))); ?></strong> AOV</span>
                    </div>
                    <p>Ringkasan penjualan ini mengambil data dari order, invoice, bukti pembayaran, dan report engine existing.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">SEO → Lead → Order</span>
                    <h2><?= (int)($kpis['seo_pages_with_lead'] ?? 0); ?> halaman SEO mendatangkan lead</h2>
                    <p><?= (int)($kpis['lead_events'] ?? 0); ?> tracking lead, <?= (int)($kpis['inquiries'] ?? 0); ?> inbox lead, <?= (int)($kpis['cta_leads'] ?? 0); ?> lead dari CTA placement, dan <?= (int)($kpis['hot_leads'] ?? 0); ?> lead hot.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-profit-attribution')); ?>">SEO Attribution</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/lead-priority-scoring')); ?>">Lead Priority</a>
                    </div>
                </article>
            </div>

            <section class="admin-grid admin-grid--stats admin-report-main-stats">
                <article class="admin-card admin-report-metric"><span class="admin-badge">Lead to Order</span><h2><?= esc((string)($kpis['lead_to_order_rate'] ?? 0)); ?>%</h2><p>Rasio dari lead tracking compact ke order pada periode ini.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">Inbox to Order</span><h2><?= esc((string)($kpis['inquiry_to_order_rate'] ?? 0)); ?>%</h2><p>Rasio lead form/inbox ke order pada periode ini.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">CTA Signal</span><h2><?= (int)($kpis['cta_clicks'] ?? 0); ?></h2><p>Klik/sinyal CTA yang terbaca dari Lead Tracking existing.</p></article>
                <article class="admin-card admin-report-metric"><span class="admin-badge">Money Page High</span><h2><?= (int)($kpis['money_pages_high'] ?? 0); ?></h2><p>Halaman prioritas tinggi yang perlu dieksekusi agar traffic SEO lebih dekat ke profit.</p></article>
            </section>

            <section class="admin-card admin-profit-report-copy-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Executive Summary Siap Pakai</span>
                        <h2>Copy laporan singkat untuk CEO/owner</h2>
                        <p>Gunakan teks ini untuk update mingguan. Admin bisa copy, edit, lalu kirim ke owner/CEO.</p>
                    </div>
                </div>
                <textarea rows="13" readonly onclick="this.select();"><?= esc(profit_report_plain_text($summary)); ?></textarea>
            </section>

            <div class="admin-grid admin-grid--two">
                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Money Leak Watch</span>
                            <h2>Bocoran profit yang perlu dicek</h2>
                            <p>Bagian ini membantu owner/CEO tahu hal yang paling cepat berdampak ke revenue.</p>
                        </div>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php foreach ($moneyLeaks as $leak): ?>
                            <article class="admin-mini-card">
                                <strong><?= esc((string)($leak['label'] ?? 'Money leak')); ?>: <?= esc($formatNumber((int)($leak['value'] ?? 0))); ?></strong>
                                <p><?= esc((string)($leak['action'] ?? 'Cek dan tindaklanjuti.')); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Focus Insight</span>
                            <h2>Arah keputusan minggu ini</h2>
                            <p>Rangkuman dari modul growth yang sudah ada.</p>
                        </div>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php foreach ($sourceFocus as $label => $focus): ?>
                            <article class="admin-mini-card">
                                <strong><?= esc(ucwords(str_replace('_', ' ', (string)$label))); ?></strong>
                                <p><?= esc((string)$focus); ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Action Plan</span>
                        <h2>Rencana kerja yang bisa dilaporkan</h2>
                        <p>Ini mengubah data menjadi pekerjaan nyata: follow-up, optimasi money page, refresh konten, CTA, dan campaign.</p>
                    </div>
                </div>
                <div class="admin-stack admin-stack--sm">
                    <?php if (!$actionPlan): ?>
                        <p class="admin-muted">Belum ada action plan pada periode ini.</p>
                    <?php endif; ?>
                    <?php foreach ($actionPlan as $idx => $action): ?>
                        <article class="admin-card admin-card--soft">
                            <div class="admin-form-head admin-form-head--split">
                                <div>
                                    <span class="admin-badge"><?= esc((string)($action['source'] ?? 'Action')); ?> · <?= esc((string)($action['priority'] ?? 'Prioritas')); ?></span>
                                    <h3><?= ($idx + 1); ?>. <?= esc((string)($action['title'] ?? 'Action plan')); ?></h3>
                                    <p><?= esc((string)($action['why'] ?? '')); ?></p>
                                </div>
                                <?php if (!empty($action['url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$action['url']); ?>">Buka</a><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <div class="admin-grid admin-grid--two">
                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Top SEO Page</span>
                            <h2>Halaman yang paling dekat ke profit</h2>
                            <p>Diambil dari SEO Profit Attribution dan Tracking Lead existing.</p>
                        </div>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-profit-attribution')); ?>">Detail</a>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php foreach ($topSeoPages as $page): ?>
                            <article class="admin-mini-card">
                                <strong><?= esc((string)($page['title'] ?? 'Halaman SEO')); ?></strong>
                                <p><?= esc((string)($page['type'] ?? 'SEO Page')); ?> · klik <?= (int)($page['clicks'] ?? 0); ?> · lead <?= (int)($page['leads'] ?? 0); ?> · order <?= (int)($page['orders'] ?? 0); ?></p>
                                <?php if (!empty($page['url'])): ?><a href="<?= esc((string)$page['url']); ?>" target="_blank" rel="noopener">Buka halaman</a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$topSeoPages): ?><p class="admin-muted">Belum ada halaman SEO yang terbaca pada periode ini.</p><?php endif; ?>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Top CTA</span>
                            <h2>CTA yang perlu dipantau</h2>
                            <p>Diambil dari CTA Result Tracker, bukan tracking baru.</p>
                        </div>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/cta-result-tracker')); ?>">Detail</a>
                    </div>
                    <div class="admin-stack admin-stack--sm">
                        <?php foreach ($topCtas as $cta): ?>
                            <article class="admin-mini-card">
                                <strong><?= esc((string)($cta['title'] ?? 'CTA')); ?></strong>
                                <p><?= esc((string)($cta['placement'] ?? 'Placement')); ?> · klik <?= (int)($cta['clicks'] ?? 0); ?> · lead <?= (int)($cta['leads'] ?? 0); ?> · order <?= (int)($cta['orders'] ?? 0); ?></p>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$topCtas): ?><p class="admin-muted">Belum ada CTA placement dengan sinyal pada periode ini.</p><?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Lead Priority</span>
                        <h2>Lead yang perlu masuk laporan follow-up</h2>
                        <p>Owner/CEO bisa langsung lihat prospek mana yang jangan sampai lepas.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/lead-priority-scoring')); ?>">Lead Priority</a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Lead</th><th>Jenis</th><th>Prioritas</th><th>Skor</th><th>Status</th><th>Alasan</th></tr></thead>
                        <tbody>
                        <?php if (!$topLeads): ?>
                            <tr><td colspan="6">Belum ada lead prioritas pada periode ini.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($topLeads as $lead): ?>
                            <tr>
                                <td><strong><?= esc((string)($lead['name'] ?? 'Lead')); ?></strong></td>
                                <td><?= esc((string)($lead['type'] ?? 'Lead')); ?></td>
                                <td><?= esc((string)($lead['priority'] ?? '')); ?></td>
                                <td><?= (int)($lead['score'] ?? 0); ?></td>
                                <td><?= esc((string)($lead['status'] ?? '')); ?></td>
                                <td><?= esc((string)($lead['reason'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Catatan Internal</span>
                        <h2>Catatan untuk laporan owner/CEO</h2>
                        <p>Simpan catatan tambahan agar laporan tidak cuma angka, tapi ada konteks bisnisnya.</p>
                    </div>
                </div>
                <form method="post" class="admin-grid admin-grid--two">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="note_id" value="ceo-summary">
                    <label>Judul Catatan
                        <input type="text" name="note_title" value="<?= esc((string)($currentNote['title'] ?? 'Catatan Owner/CEO')); ?>" maxlength="120">
                    </label>
                    <label>PIC / Admin
                        <input type="text" name="owner" value="<?= esc((string)($currentNote['owner'] ?? '')); ?>" maxlength="80" placeholder="Nama admin atau divisi">
                    </label>
                    <label style="grid-column:1/-1">Isi Catatan
                        <textarea name="note" rows="5" maxlength="1800" placeholder="Contoh: minggu ini fokus follow-up order pending dan refresh 3 artikel money page..."><?= esc((string)($currentNote['note'] ?? '')); ?></textarea>
                    </label>
                    <div style="grid-column:1/-1">
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Catatan</button>
                    </div>
                </form>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
