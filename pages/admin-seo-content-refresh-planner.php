<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$allowedDays = [30, 60, 90, 120, 180, 365];
$days = (int)($_GET['days'] ?? 90);
if (!in_array($days, $allowedDays, true)) {
    $days = 90;
}

$typeOptions = function_exists('seo_refresh_type_options') ? seo_refresh_type_options() : ['all' => 'Semua SEO Page'];
$type = (string)($_GET['type'] ?? 'all');
if (!isset($typeOptions[$type])) {
    $type = 'all';
}

$priorityOptions = function_exists('seo_refresh_priority_options') ? seo_refresh_priority_options() : ['all' => 'Semua Prioritas'];
$priority = (string)($_GET['priority'] ?? 'all');
if (!isset($priorityOptions[$priority])) {
    $priority = 'all';
}

$reasonOptions = function_exists('seo_refresh_reason_options') ? seo_refresh_reason_options() : ['all' => 'Semua Alasan'];
$reason = (string)($_GET['reason'] ?? 'all');
if (!isset($reasonOptions[$reason])) {
    $reason = 'all';
}

$statusOptions = function_exists('seo_refresh_filter_options') ? seo_refresh_filter_options() : ['open' => 'Belum Selesai'];
$status = (string)($_GET['status'] ?? 'open');
if (!isset($statusOptions[$status])) {
    $status = 'open';
}

$redirectBase = static function (string $message = '') use ($days, $type, $priority, $reason, $status): string {
    $query = [
        'days' => $days,
        'type' => $type,
        'priority' => $priority,
        'reason' => $reason,
        'status' => $status,
    ];
    if ($message !== '') {
        $query['message'] = $message;
    }
    return 'admin/seo-content-refresh-planner?' . http_build_query($query);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'update_item') {
            seo_refresh_update_item(
                (string)($_POST['page_id'] ?? ''),
                (string)($_POST['refresh_status'] ?? 'queued'),
                (string)($_POST['refresh_note'] ?? ''),
                (string)($_POST['last_refreshed_at'] ?? '')
            );
            redirect_302($redirectBase('Status refresh konten berhasil disimpan.'));
        }

        if ($action === 'reset_page') {
            seo_refresh_reset_page((string)($_POST['page_id'] ?? ''));
            redirect_302($redirectBase('Catatan refresh halaman ini sudah direset.'));
        }

        if ($action === 'reset_all') {
            seo_refresh_reset_all();
            redirect_302($redirectBase('Semua catatan SEO Content Refresh Planner sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = seo_refresh_summary($days, $type, $priority, $reason, $status);
$items = (array)($summary['items'] ?? []);
$counts = (array)($summary['counts'] ?? []);
$topItem = is_array($summary['top_item'] ?? null) ? (array)$summary['top_item'] : null;
$typeOptions = (array)($summary['type_options'] ?? $typeOptions);
$priorityOptions = (array)($summary['priority_options'] ?? $priorityOptions);
$reasonOptions = (array)($summary['reason_options'] ?? $reasonOptions);
$statusOptions = (array)($summary['status_options'] ?? $statusOptions);
$statusActionOptions = (array)($summary['status_action_options'] ?? seo_refresh_status_options());

$baseUrl = static function (array $override = []) use ($days, $type, $priority, $reason, $status): string {
    $query = array_merge([
        'days' => $days,
        'type' => $type,
        'priority' => $priority,
        'reason' => $reason,
        'status' => $status,
    ], $override);
    return url('admin/seo-content-refresh-planner?' . http_build_query($query));
};

if (($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-content-refresh-planner-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['export'] ?? '') === 'csv') {
    seo_refresh_export_csv($items);
}

$priorityLabel = static function (string $value): string {
    return match ($value) {
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        default => ucfirst($value ?: 'Prioritas'),
    };
};

$statusLabel = static function (string $value) use ($statusActionOptions): string {
    return (string)($statusActionOptions[$value] ?? ucfirst(str_replace('_', ' ', $value)));
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'SEO Content Refresh Planner - Admin',
    'description' => 'Planner refresh artikel dan halaman SEO lama agar performa konten, CTA, internal link, schema, dan offer lebih siap menghasilkan lead.',
    'robots' => 'noindex, nofollow',
]);

$pageTitle = 'SEO Content Refresh Planner';
$pageDescription = 'Planner untuk menghidupkan ulang artikel dan halaman lama dengan update konten, meta, FAQ, CTA, internal link, schema, dan offer berdasarkan data yang sudah ada.';

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-refresh-shell">
    <section class="admin-hero admin-seo-refresh-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO Growth</div>
                <h1>SEO Content Refresh Planner</h1>
                <p>Hidupkan lagi artikel dan halaman lama yang mulai melemah atau punya peluang profit. Planner ini membaca SEO, Content Performance, Lead Tracking, Money Page, CTA, dan internal link existing tanpa membuat tracking baru.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/content-performance')); ?>">Content Performance</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Money Page</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/internal-link-cta-injection')); ?>">Internal Link & CTA</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey')); ?>">Journey Map</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview admin-seo-refresh-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Refresh Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['average_refresh_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['average_refresh_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>Konten Lama → Lead Baru</h2>
                    <p><?= esc((string)($summary['top_focus'] ?? 'Refresh konten lama yang paling dekat dengan peluang klik, lead, dan order.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Antrean Refresh</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($counts['visible'] ?? 0); ?></strong> tampil</span>
                        <span><strong><?= (int)($counts['high'] ?? 0); ?></strong> high</span>
                        <span><strong><?= (int)($counts['old_content'] ?? 0); ?></strong> lama</span>
                        <span><strong><?= (int)($counts['articles'] ?? 0); ?></strong> artikel</span>
                    </div>
                    <p>Utamakan konten lama yang masih punya sinyal klik/lead, atau halaman dengan issue SEO yang bisa diperbaiki cepat.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Progress Eksekusi</span>
                    <h2><?= (int)(($counts['published'] ?? 0) + ($counts['monitoring'] ?? 0)); ?> / <?= (int)($counts['total'] ?? 0); ?> selesai</h2>
                    <p><?= (int)($counts['refreshing'] ?? 0); ?> sedang direfresh, <?= (int)($counts['researching'] ?? 0); ?> riset update, <?= (int)($counts['hold'] ?? 0); ?> ditahan.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Planner</span>
                        <h2>Pilih periode, tipe halaman, prioritas, alasan, dan status</h2>
                        <p>Gunakan filter ini untuk fokus ke artikel/halaman yang paling layak direfresh minggu ini.</p>
                    </div>
                </div>
                <div class="admin-seo-profit-filter-row">
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($allowedDays as $rangeDays): ?>
                            <a class="<?= $days === $rangeDays ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['days' => $rangeDays])); ?>"><?= (int)$rangeDays; ?> hari</a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
                            <a class="<?= $type === (string)$typeKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['type' => (string)$typeKey])); ?>"><?= esc((string)$typeLabel); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($priorityOptions as $priorityKey => $priorityText): ?>
                            <a class="<?= $priority === (string)$priorityKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['priority' => (string)$priorityKey])); ?>"><?= esc((string)$priorityText); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($reasonOptions as $reasonKey => $reasonText): ?>
                            <a class="<?= $reason === (string)$reasonKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['reason' => (string)$reasonKey])); ?>"><?= esc((string)$reasonText); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($statusOptions as $statusKey => $statusText): ?>
                            <a class="<?= $status === (string)$statusKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['status' => (string)$statusKey])); ?>"><?= esc((string)$statusText); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <?php if ($topItem): ?>
                <section class="admin-card admin-cta-result-bridge-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Prioritas Refresh Terdekat</span>
                            <h2><?= esc((string)($topItem['title'] ?? 'Konten SEO')); ?></h2>
                            <p><?= esc((string)($topItem['reason']['note'] ?? 'Refresh konten ini agar lebih relevan dan lebih dekat ke lead/order.')); ?></p>
                        </div>
                        <div class="admin-hero__actions">
                            <?php if (!empty($topItem['edit_url'])): ?><a class="admin-btn admin-btn--primary" href="<?= esc((string)$topItem['edit_url']); ?>">Edit Konten</a><?php endif; ?>
                            <?php if (!empty($topItem['url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topItem['url']); ?>" target="_blank" rel="noopener">Lihat Halaman</a><?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-cta-result-mini-metrics">
                        <span>Prioritas <strong><?= esc($priorityLabel((string)($topItem['priority'] ?? 'low'))); ?></strong></span>
                        <span>Refresh <strong><?= (int)($topItem['refresh_score'] ?? 0); ?></strong></span>
                        <span>SEO <strong><?= (int)($topItem['seo_score'] ?? 0); ?></strong></span>
                        <span>Usia <strong><?= esc((string)($topItem['freshness_label'] ?? '-')); ?></strong></span>
                    </div>
                </section>
            <?php endif; ?>

            <section class="admin-card admin-cta-result-list-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Refresh Queue</span>
                        <h2>Daftar artikel/halaman yang perlu dihidupkan lagi</h2>
                        <p>Checklist di bawah membantu admin tahu apa yang harus diupdate, bukan cuma melihat skor.</p>
                    </div>
                </div>

                <?php if (!$items): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada antrean refresh untuk filter ini.</h3>
                        <p>Coba ubah periode, tipe halaman, prioritas, atau status. Jika data masih kosong, pastikan artikel/halaman dan Lead Tracking sudah tersedia.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-cta-result-card-list">
                        <?php foreach ($items as $item): ?>
                            <?php $metrics = (array)($item['metrics'] ?? []); ?>
                            <article class="admin-card admin-cta-result-item-card">
                                <div class="admin-card-header">
                                    <div>
                                        <span class="admin-badge"><?= esc((string)($item['type_label'] ?? 'Halaman')); ?> · <?= esc($priorityLabel((string)($item['priority'] ?? 'low'))); ?></span>
                                        <h3><?= esc((string)($item['title'] ?? 'Konten SEO')); ?></h3>
                                        <p><?= esc((string)($item['reason']['label'] ?? 'Alasan refresh')); ?> — <?= esc((string)($item['reason']['note'] ?? 'Konten ini layak direfresh.')); ?></p>
                                    </div>
                                    <div class="admin-cta-result-score-pill">
                                        <strong><?= (int)($item['refresh_score'] ?? 0); ?></strong>
                                        <span>Refresh</span>
                                    </div>
                                </div>

                                <div class="admin-cta-result-mini-metrics">
                                    <span>Usia <strong><?= esc((string)($item['freshness_label'] ?? '-')); ?></strong></span>
                                    <span>SEO <strong><?= (int)($item['seo_score'] ?? 0); ?></strong></span>
                                    <span>Interaksi <strong><?= (int)($metrics['interactions'] ?? 0); ?></strong></span>
                                    <span>Lead <strong><?= (int)(($metrics['inquiries'] ?? 0) + ($metrics['whatsapp'] ?? 0)); ?></strong></span>
                                    <span>Order <strong><?= (int)($metrics['orders'] ?? 0); ?></strong></span>
                                    <?php if ((int)($item['money_score'] ?? 0) > 0): ?><span>Money <strong><?= (int)($item['money_score'] ?? 0); ?></strong></span><?php endif; ?>
                                </div>

                                <?php if (!empty($item['tasks'])): ?>
                                    <div class="admin-cta-result-recommendation">
                                        <strong>Checklist refresh yang disarankan:</strong>
                                        <ul>
                                            <?php foreach ((array)$item['tasks'] as $task): ?>
                                                <li><strong><?= esc((string)($task['label'] ?? 'Task')); ?>:</strong> <?= esc((string)($task['detail'] ?? '')); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item['recommendation'])): ?>
                                    <div class="admin-cta-result-recommendation">
                                        <strong>Insight dari Content Performance:</strong>
                                        <p><?= esc((string)$item['recommendation']); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item['issues'])): ?>
                                    <details class="admin-cta-result-events">
                                        <summary>Issue SEO yang ikut terbaca</summary>
                                        <ul>
                                            <?php foreach ((array)$item['issues'] as $issue): ?>
                                                <li><strong><?= esc((string)($issue['title'] ?? 'Catatan SEO')); ?></strong> — <?= esc((string)($issue['fix'] ?? $issue['message'] ?? 'Perlu dicek.')); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </details>
                                <?php endif; ?>

                                <form method="post" action="<?= esc($baseUrl()); ?>" class="admin-cta-result-decision-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="update_item">
                                    <input type="hidden" name="page_id" value="<?= esc((string)($item['page_id'] ?? '')); ?>">
                                    <label>
                                        Status Refresh
                                        <select name="refresh_status">
                                            <?php foreach ($statusActionOptions as $statusKey => $statusText): ?>
                                                <option value="<?= esc((string)$statusKey); ?>" <?= (string)($item['status'] ?? 'queued') === (string)$statusKey ? 'selected' : ''; ?>><?= esc((string)$statusText); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>
                                        Tanggal terakhir refresh
                                        <input type="date" name="last_refreshed_at" value="<?= esc(substr((string)($item['last_refreshed_at'] ?: $item['updated_at'] ?? ''), 0, 10)); ?>">
                                    </label>
                                    <label>
                                        Catatan
                                        <textarea name="refresh_note" rows="2" placeholder="Contoh: sudah update FAQ, CTA tengah artikel, dan internal link ke katalog."><?= esc((string)($item['note'] ?? '')); ?></textarea>
                                    </label>
                                    <div class="admin-hero__actions">
                                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Status</button>
                                        <?php if (!empty($item['edit_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$item['edit_url']); ?>">Edit Konten</a><?php endif; ?>
                                        <?php if (!empty($item['url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$item['url']); ?>" target="_blank" rel="noopener">Lihat Halaman</a><?php endif; ?>
                                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey')); ?>">Pantau Journey</a>
                                    </div>
                                </form>

                                <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset catatan refresh untuk halaman ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="reset_page">
                                    <input type="hidden" name="page_id" value="<?= esc((string)($item['page_id'] ?? '')); ?>">
                                    <button class="admin-btn admin-btn--light" type="submit">Reset Catatan Halaman Ini</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-danger-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Reset Catatan</span>
                        <h2>Reset status SEO Content Refresh Planner</h2>
                        <p>Ini hanya menghapus catatan status planner. Artikel, halaman publik, Tracking Lead, Content Performance, Money Page, dan CTA Result tidak ikut dihapus.</p>
                    </div>
                    <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset semua catatan SEO Content Refresh Planner?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_all">
                        <button class="admin-btn admin-btn--danger" type="submit">Reset Semua Catatan</button>
                    </form>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
