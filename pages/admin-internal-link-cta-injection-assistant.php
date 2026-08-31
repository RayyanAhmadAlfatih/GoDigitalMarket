<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$allowedDays = [7, 14, 30, 60, 90, 180];
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, $allowedDays, true)) {
    $days = 30;
}

$typeOptions = function_exists('seo_profit_type_options') ? seo_profit_type_options() : ['all' => 'Semua SEO Page'];
$type = (string)($_GET['type'] ?? 'all');
if (!isset($typeOptions[$type])) {
    $type = 'all';
}

$priorityOptions = function_exists('seo_money_summary') ? (array)(seo_money_summary($days, $type, 'all')['priority_options'] ?? []) : ['all' => 'Semua Prioritas'];
$priority = (string)($_GET['priority'] ?? 'all');
if (!isset($priorityOptions[$priority])) {
    $priority = 'all';
}

$statusOptions = function_exists('link_cta_filter_options') ? link_cta_filter_options() : ['open' => 'Belum Selesai'];
$status = (string)($_GET['status'] ?? 'open');
if (!isset($statusOptions[$status])) {
    $status = 'open';
}

$redirectBase = static function (string $message = '') use ($days, $type, $priority, $status): string {
    $query = [
        'days' => $days,
        'type' => $type,
        'priority' => $priority,
        'status' => $status,
    ];
    if ($message !== '') {
        $query['message'] = $message;
    }
    return 'admin/internal-link-cta-injection?' . http_build_query($query);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'update_recommendation') {
            link_cta_update_recommendation(
                (string)($_POST['recommendation_id'] ?? ''),
                (string)($_POST['page_id'] ?? ''),
                (string)($_POST['recommendation_status'] ?? 'pending'),
                (string)($_POST['recommendation_note'] ?? '')
            );
            redirect_302($redirectBase('Status rekomendasi berhasil disimpan.'));
        }

        if ($action === 'reset_page') {
            link_cta_reset_page((string)($_POST['page_id'] ?? ''));
            redirect_302($redirectBase('Catatan halaman ini sudah direset.'));
        }

        if ($action === 'reset_all') {
            link_cta_reset_all();
            redirect_302($redirectBase('Semua catatan Internal Link & CTA sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = link_cta_summary($days, $type, $priority, $status);
$pages = (array)($summary['pages'] ?? []);
$recommendations = (array)($summary['recommendations'] ?? []);
$counts = (array)($summary['counts'] ?? []);
$topRecommendation = is_array($summary['top_recommendation'] ?? null) ? (array)$summary['top_recommendation'] : null;
$statusOptions = (array)($summary['status_options'] ?? $statusOptions);
$typeOptions = (array)($summary['type_options'] ?? $typeOptions);
$priorityOptions = (array)($summary['priority_options'] ?? $priorityOptions);
$recommendationStatusOptions = link_cta_status_options();

$baseUrl = static function (array $override = []) use ($days, $type, $priority, $status): string {
    $query = array_merge([
        'days' => $days,
        'type' => $type,
        'priority' => $priority,
        'status' => $status,
    ], $override);
    return url('admin/internal-link-cta-injection?' . http_build_query($query));
};

if (($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="internal-link-cta-injection-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['export'] ?? '') === 'csv') {
    link_cta_export_csv($recommendations);
}

$priorityLabel = static function (string $priority): string {
    return match ($priority) {
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        default => ucfirst($priority ?: 'Prioritas'),
    };
};

$statusLabel = static function (string $value) use ($recommendationStatusOptions): string {
    return (string)($recommendationStatusOptions[$value] ?? ucfirst(str_replace('_', ' ', $value)));
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Internal Link & CTA Injection - Admin',
    'description' => 'Assistant internal link dan CTA untuk mengarahkan traffic SEO menuju offer, form, katalog, lead, dan order.',
    'robots' => 'noindex, nofollow',
]);

$pageTitle = 'Internal Link & CTA Injection Assistant';
$pageDescription = 'Asisten eksekusi untuk memasang internal link dan CTA dari rekomendasi SEO Money Page Optimizer ke halaman yang paling dekat dengan lead/order.';

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-link-cta-shell">
    <section class="admin-hero admin-link-cta-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Internal Link & CTA Injection Assistant</h1>
                <p>Bantu admin menentukan link internal dan CTA mana yang harus disisipkan ke artikel, landing page, produk, atau halaman SEO agar traffic punya jalur menuju lead dan order.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Money Page</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/money-page-deployment-checklist')); ?>">Deployment Checklist</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement')); ?>">CTA Placement</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey')); ?>">Journey Map</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview admin-link-cta-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Injection Progress</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['progress_percent'] ?? 0); ?>;">
                        <strong><?= (int)($summary['progress_percent'] ?? 0); ?></strong><span>%</span>
                    </div>
                    <h2>SEO Page → Offer Path</h2>
                    <p><?= esc((string)($summary['top_focus'] ?? 'Pasang internal link dan CTA dari halaman SEO ke halaman penjualan.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Rekomendasi Aktif</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($counts['pages'] ?? 0); ?></strong> page</span>
                        <span><strong><?= (int)($counts['internal_links'] ?? 0); ?></strong> internal link</span>
                        <span><strong><?= (int)($counts['ctas'] ?? 0); ?></strong> CTA</span>
                        <span><strong><?= (int)($counts['high'] ?? 0); ?></strong> high</span>
                    </div>
                    <p>Fitur ini meneruskan Money Page Optimizer. Tracking hasil tetap dibaca dari Lead Tracking, CTA Result, dan SEO Journey Map.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Status Eksekusi</span>
                    <h2><?= (int)(($counts['applied'] ?? 0) + ($counts['monitoring'] ?? 0)); ?> / <?= (int)($counts['recommendations'] ?? 0); ?> selesai</h2>
                    <p><?= (int)($counts['reviewing'] ?? 0); ?> sedang disiapkan, <?= (int)($counts['deferred'] ?? 0); ?> ditunda. Utamakan link dan CTA di money page prioritas tinggi.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Assistant</span>
                        <h2>Pilih periode, tipe halaman, prioritas, dan status</h2>
                        <p>Mulai dari halaman yang sudah punya sinyal klik/lead/order agar effort optimasi lebih dekat ke profit.</p>
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
                        <?php foreach ($statusOptions as $statusKey => $statusText): ?>
                            <a class="<?= $status === (string)$statusKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['status' => (string)$statusKey])); ?>"><?= esc((string)$statusText); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <?php if ($topRecommendation): ?>
                <section class="admin-card admin-cta-result-bridge-card">
                    <div class="admin-card-header">
                        <div>
                            <span class="admin-badge">Prioritas Terdekat</span>
                            <h2><?= esc((string)($topRecommendation['kind_label'] ?? 'Rekomendasi')); ?> untuk <?= esc((string)($topRecommendation['page_title'] ?? 'halaman SEO')); ?></h2>
                            <p><?= esc((string)($topRecommendation['reason'] ?? 'Rekomendasi ini paling layak dikerjakan dulu berdasarkan sinyal money page.')); ?></p>
                        </div>
                        <div class="admin-hero__actions">
                            <?php if (!empty($topRecommendation['page_edit_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topRecommendation['page_edit_url']); ?>">Edit Halaman</a><?php endif; ?>
                            <?php if (!empty($topRecommendation['target_edit_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topRecommendation['target_edit_url']); ?>">Buka Target</a><?php endif; ?>
                        </div>
                    </div>
                    <div class="admin-cta-result-flow">
                        <span><?= esc((string)($topRecommendation['page_type_label'] ?? 'Halaman')); ?></span>
                        <strong><?= esc((string)($topRecommendation['page_title'] ?? 'Halaman sumber')); ?></strong>
                        <span>→</span>
                        <strong><?= esc((string)($topRecommendation['target_title'] ?? 'Target')); ?></strong>
                        <span><?= esc((string)($topRecommendation['placement'] ?? 'Placement')); ?></span>
                    </div>
                </section>
            <?php endif; ?>

            <section class="admin-card admin-cta-result-table-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Injection Queue</span>
                        <h2>Daftar halaman dan rekomendasi pemasangan</h2>
                        <p>Setelah link/CTA dipasang, tandai statusnya lalu pantau efeknya di CTA Result dan SEO Journey Map.</p>
                    </div>
                </div>

                <?php if (!$pages): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada rekomendasi pada filter ini.</h3>
                        <p>Coba ubah periode/status, atau buka Money Page Optimizer untuk melihat halaman yang sudah punya potensi SEO ke profit.</p>
                        <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Buka Money Page Optimizer</a>
                    </div>
                <?php else: ?>
                    <div class="admin-stack">
                        <?php foreach ($pages as $page): ?>
                            <?php $metrics = (array)($page['metrics'] ?? []); ?>
                            <article class="admin-card admin-cta-result-card">
                                <div class="admin-card-header">
                                    <div>
                                        <span class="admin-badge"><?= esc($priorityLabel((string)($page['priority'] ?? 'low'))); ?> Priority</span>
                                        <h2><?= esc((string)($page['page_title'] ?? 'Halaman SEO')); ?></h2>
                                        <p><?= esc((string)($page['page_type_label'] ?? 'Halaman')); ?> · Money Score <?= (int)($page['money_score'] ?? 0); ?>/100 · Progress <?= (int)($page['progress_done'] ?? 0); ?>/<?= (int)($page['progress_total'] ?? 0); ?></p>
                                    </div>
                                    <div class="admin-hero__actions">
                                        <?php if (!empty($page['page_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$page['page_url']); ?>" target="_blank" rel="noopener">Lihat Page</a><?php endif; ?>
                                        <?php if (!empty($page['page_edit_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$page['page_edit_url']); ?>">Edit Page</a><?php endif; ?>
                                    </div>
                                </div>

                                <div class="admin-cta-result-stat-row">
                                    <span><strong><?= (int)($metrics['clicks'] ?? 0); ?></strong> klik</span>
                                    <span><strong><?= (int)($metrics['leads'] ?? 0); ?></strong> lead</span>
                                    <span><strong><?= (int)(($metrics['orders'] ?? 0) + ($metrics['payments'] ?? 0)); ?></strong> order/payment</span>
                                    <span><strong><?= (int)($page['progress_percent'] ?? 0); ?>%</strong> progress</span>
                                </div>

                                <div class="admin-cta-result-grid">
                                    <?php foreach ((array)($page['recommendations'] ?? []) as $recommendation): ?>
                                        <article class="admin-card admin-cta-result-card">
                                            <div class="admin-cta-result-card-head">
                                                <div>
                                                    <span class="admin-badge"><?= esc((string)($recommendation['kind_label'] ?? 'Rekomendasi')); ?></span>
                                                    <h3><?= esc((string)($recommendation['target_title'] ?? 'Target')); ?></h3>
                                                    <p><?= esc((string)($recommendation['placement'] ?? 'Placement')); ?></p>
                                                </div>
                                                <div class="admin-cta-result-card-score"><strong><?= (int)($recommendation['score'] ?? 0); ?></strong><span>/100</span></div>
                                            </div>

                                            <div class="admin-cta-result-recommendation">
                                                <strong>Snippet yang disarankan:</strong>
                                                <p><?= esc((string)($recommendation['snippet'] ?? '')); ?></p>
                                            </div>

                                            <div class="admin-cta-result-meta">
                                                <span>Status: <strong><?= esc($statusLabel((string)($recommendation['status'] ?? 'pending'))); ?></strong></span>
                                                <span>Anchor/Tombol: <strong><?= esc((string)($recommendation['anchor_text'] ?? '-')); ?></strong></span>
                                            </div>

                                            <?php if (!empty($recommendation['checkpoints'])): ?>
                                                <details class="admin-cta-result-events">
                                                    <summary>Checklist pemasangan</summary>
                                                    <ul>
                                                        <?php foreach ((array)$recommendation['checkpoints'] as $checkpoint): ?>
                                                            <li><?= esc((string)$checkpoint); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </details>
                                            <?php endif; ?>

                                            <form method="post" action="<?= esc($baseUrl()); ?>" class="admin-cta-result-decision-form">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_recommendation">
                                                <input type="hidden" name="recommendation_id" value="<?= esc((string)($recommendation['recommendation_id'] ?? '')); ?>">
                                                <input type="hidden" name="page_id" value="<?= esc((string)($recommendation['page_id'] ?? '')); ?>">
                                                <label>
                                                    Status
                                                    <select name="recommendation_status">
                                                        <?php foreach ($recommendationStatusOptions as $statusKey => $statusText): ?>
                                                            <option value="<?= esc((string)$statusKey); ?>" <?= (string)($recommendation['status'] ?? 'pending') === (string)$statusKey ? 'selected' : ''; ?>><?= esc((string)$statusText); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label>
                                                    Catatan
                                                    <textarea name="recommendation_note" rows="2" placeholder="Contoh: sudah dipasang di paragraf kedua / perlu revisi copy CTA."><?= esc((string)($recommendation['note'] ?? '')); ?></textarea>
                                                </label>
                                                <div class="admin-hero__actions">
                                                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Status</button>
                                                    <?php if (!empty($recommendation['target_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$recommendation['target_url']); ?>" target="_blank" rel="noopener">Lihat Target</a><?php endif; ?>
                                                    <?php if (!empty($recommendation['target_edit_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$recommendation['target_edit_url']); ?>">Buka Area</a><?php endif; ?>
                                                </div>
                                            </form>
                                        </article>
                                    <?php endforeach; ?>
                                </div>

                                <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset catatan rekomendasi untuk halaman ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="reset_page">
                                    <input type="hidden" name="page_id" value="<?= esc((string)($page['page_id'] ?? '')); ?>">
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
                        <h2>Reset status Internal Link & CTA</h2>
                        <p>Ini hanya menghapus catatan status assistant. Data Tracking Lead, Money Page, CTA Result, artikel, dan halaman publik tidak ikut dihapus.</p>
                    </div>
                    <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset semua catatan Internal Link & CTA Injection Assistant?');">
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
