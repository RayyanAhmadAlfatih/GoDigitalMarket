<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$params = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['days' => 30, 'range' => '30', 'filters' => []];
$rangeLabel = function_exists('report_range_label') ? report_range_label((string)$params['range'], $params) : '30 hari';
$summary = function_exists('sales_action_center_summary') ? sales_action_center_summary((int)$params['days'], (array)$params['filters']) : ['metrics' => [], 'rows' => []];

$stage = (string)($_GET['stage'] ?? 'all');
$allowedStages = ['all', 'traffic', 'intent', 'inquiry', 'order', 'payment', 'closing', 'scale'];
if (!in_array($stage, $allowedStages, true)) {
    $stage = 'all';
}

$priority = (string)($_GET['priority'] ?? 'all');
$allowedPriorities = ['all', 'critical', 'high', 'scale', 'medium', 'monitor'];
if (!in_array($priority, $allowedPriorities, true)) {
    $priority = 'all';
}

$q = trim((string)($_GET['q'] ?? ''));
$rows = function_exists('sales_action_center_filter_rows') ? sales_action_center_filter_rows((array)($summary['rows'] ?? []), $stage, $priority, $q) : (array)($summary['rows'] ?? []);

function admin_funnel_action_url(array $overrides = []): string
{
    $query = array_merge([
        'range' => $_GET['range'] ?? '30',
        'year' => $_GET['year'] ?? date('Y'),
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'stage' => $_GET['stage'] ?? 'all',
        'priority' => $_GET['priority'] ?? 'all',
        'q' => $_GET['q'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/funnel-action-center' . ($query ? '?' . http_build_query($query) : ''));
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="funnel-action-center-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'summary' => $summary,
        'filtered' => [
            'range' => $params['range'] ?? '30',
            'stage' => $stage,
            'priority' => $priority,
            'q' => $q,
            'rows' => $rows,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="funnel-action-center-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['score','priority','stage','source','title','target','channel','reason','whatsapp_script','email_subject','email_body','action_url'], ',', '"', '\\', "\n");
    foreach ($rows as $row) {
        fputcsv($out, [
            (int)($row['score'] ?? 0),
            (string)($row['priority']['label'] ?? ''),
            (string)($row['stage_label'] ?? ''),
            (string)($row['source'] ?? ''),
            (string)($row['title'] ?? ''),
            (string)($row['target'] ?? ''),
            (string)($row['channel'] ?? ''),
            (string)($row['reason'] ?? ''),
            (string)($row['whatsapp'] ?? ''),
            (string)($row['email_subject'] ?? ''),
            (string)($row['email_body'] ?? ''),
            (string)($row['action_url'] ?? ''),
        ], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

$metrics = (array)($summary['metrics'] ?? []);
$funnelScore = (array)($summary['funnel_score'] ?? []);
$crm = (array)($summary['crm'] ?? []);
$stageOptions = [
    'all' => 'Semua Tahap',
    'traffic' => 'Traffic → Lead',
    'intent' => 'Intent → Inquiry',
    'inquiry' => 'Inquiry → Order',
    'order' => 'Order → Payment',
    'payment' => 'Payment → Closing',
    'closing' => 'Closing → Repeat',
    'scale' => 'Scale Winner',
];
$priorityOptions = [
    'all' => 'Semua Prioritas',
    'critical' => 'Kritis',
    'high' => 'Tinggi',
    'scale' => 'Scale',
    'medium' => 'Sedang',
    'monitor' => 'Pantau',
];

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Funnel Action Center - Admin',
    'description' => 'Pusat eksekusi sales funnel: script WhatsApp, email, follow-up, offer polish, payment recovery, dan scale action.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-funnel-action-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Funnel Action Center</h1>
                <p>Ubah insight funnel menjadi aksi harian: follow-up WhatsApp, email, payment recovery, offer polish, internal link, dan scale campaign.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/sales-funnel-growth')); ?>">Sales Funnel</a>
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/conversion-opportunities')); ?>">Conversion Opportunities</a>
                <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/followups')); ?>">Follow-up CRM</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <div class="admin-funnel-action-hero-grid">
                <div class="admin-card admin-funnel-action-main-score">
                    <span class="admin-badge">Action Readiness</span>
                    <strong><?= (int)($metrics['total_actions'] ?? 0); ?></strong>
                    <h2>Aksi siap dieksekusi</h2>
                    <p>Diambil dari bottleneck funnel, peluang konversi, performa konten, dan jadwal follow-up CRM.</p>
                </div>
                <div class="admin-card admin-funnel-action-kpi">
                    <span class="admin-badge">Prioritas</span>
                    <div><span>Kritis</span><strong><?= (int)($metrics['critical'] ?? 0); ?></strong></div>
                    <div><span>Tinggi</span><strong><?= (int)($metrics['high'] ?? 0); ?></strong></div>
                    <div><span>Scale</span><strong><?= (int)($metrics['scale'] ?? 0); ?></strong></div>
                    <div><span>Funnel Score</span><strong><?= (int)($funnelScore['total'] ?? 0); ?>/100</strong></div>
                </div>
                <div class="admin-card admin-funnel-action-kpi">
                    <span class="admin-badge">Follow-up</span>
                    <div><span>Hari ini</span><strong><?= (int)($metrics['today_followups'] ?? 0); ?></strong></div>
                    <div><span>Terlambat</span><strong><?= (int)($metrics['overdue_followups'] ?? 0); ?></strong></div>
                    <div><span>Hot Lead</span><strong><?= (int)($crm['hot'] ?? 0); ?></strong></div>
                    <div><span>Upcoming</span><strong><?= (int)($crm['upcoming'] ?? 0); ?></strong></div>
                </div>
            </div>

            <form method="get" action="<?= esc(url('admin/funnel-action-center')); ?>" class="admin-card admin-report-filter admin-funnel-action-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Action Filter</span>
                        <h3>Atur fokus aksi funnel</h3>
                    </div>
                    <p>Rentang aktif: <strong><?= esc($rangeLabel); ?></strong>. Pakai filter ini untuk memilih tahap, prioritas, atau mencari script tertentu.</p>
                </div>
                <div class="admin-report-filter-grid admin-report-filter-grid--wide">
                    <label><span>Range</span>
                        <select name="range">
                            <?php foreach (function_exists('report_allowed_ranges') ? report_allowed_ranges() : ['7','30','90','365','all'] as $rangeOption): ?>
                                <option value="<?= esc($rangeOption); ?>" <?= (string)$params['range'] === $rangeOption ? 'selected' : ''; ?>><?= esc($rangeOption === 'year' ? 'Tahun tertentu' : ($rangeOption === 'all' ? 'Semua data' : ($rangeOption === 'custom' ? 'Custom tanggal' : $rangeOption . ' hari'))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Tahun</span><input type="number" name="year" value="<?= esc((string)($params['year'] ?? date('Y'))); ?>" min="2020" max="2100"></label>
                    <label><span>Dari</span><input type="date" name="date_from" value="<?= esc((string)($params['date_from'] ?? '')); ?>"></label>
                    <label><span>Sampai</span><input type="date" name="date_to" value="<?= esc((string)($params['date_to'] ?? '')); ?>"></label>
                    <label><span>Tahap</span>
                        <select name="stage">
                            <?php foreach ($stageOptions as $value => $label): ?>
                                <option value="<?= esc((string)$value); ?>" <?= $stage === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Prioritas</span>
                        <select name="priority">
                            <?php foreach ($priorityOptions as $value => $label): ?>
                                <option value="<?= esc((string)$value); ?>" <?= $priority === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="admin-report-filter-search"><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="follow-up, payment, CTA, offer, WhatsApp..."></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/funnel-action-center')); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_funnel_action_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_funnel_action_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <div class="admin-card admin-funnel-action-guide">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Flow</span>
                        <h2>Alur Eksekusi Funnel</h2>
                        <p>SEO membuat traffic, CTA mengubah jadi intent, follow-up mengubah jadi order, payment recovery mengubah jadi closing.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/sales-funnel-growth')); ?>">Lihat Peta Funnel</a>
                </div>
                <div class="admin-funnel-action-flow">
                    <div><strong>1</strong><span>Traffic</span><small>Artikel, SEO, internal link</small></div>
                    <div><strong>2</strong><span>Intent</span><small>CTA, WhatsApp, form</small></div>
                    <div><strong>3</strong><span>Inquiry</span><small>Fast response, diagnosa kebutuhan</small></div>
                    <div><strong>4</strong><span>Order</span><small>Offer, invoice, checkout</small></div>
                    <div><strong>5</strong><span>Payment</span><small>Reminder & bukti bayar</small></div>
                    <div><strong>6</strong><span>Scale</span><small>Repeat, campaign, konten support</small></div>
                </div>
            </div>

            <div class="admin-funnel-action-list">
                <?php foreach ($rows as $row): ?>
                    <?php $tone = (string)($row['priority']['tone'] ?? 'neutral'); ?>
                    <article class="admin-card admin-funnel-action-card admin-funnel-action-card--<?= esc($tone); ?>">
                        <div class="admin-funnel-action-card-head">
                            <div>
                                <span class="admin-badge admin-funnel-action-priority admin-funnel-action-priority--<?= esc($tone); ?>"><?= esc((string)($row['priority']['label'] ?? 'Pantau')); ?> · <?= (int)($row['score'] ?? 0); ?>/100</span>
                                <h2><?= esc((string)($row['title'] ?? 'Action Funnel')); ?></h2>
                                <p><?= esc((string)($row['reason'] ?? 'Kerjakan aksi ini untuk memperkuat funnel.')); ?></p>
                            </div>
                            <div class="admin-funnel-action-meta">
                                <span><?= esc((string)($row['stage_label'] ?? 'Funnel')); ?></span>
                                <strong><?= esc((string)($row['channel'] ?? 'Follow-up')); ?></strong>
                                <small><?= esc((string)($row['source'] ?? 'Engine')); ?></small>
                            </div>
                        </div>

                        <div class="admin-funnel-action-card-grid">
                            <div class="admin-funnel-action-script">
                                <h3>Script WhatsApp</h3>
                                <textarea readonly><?= esc((string)($row['whatsapp'] ?? '')); ?></textarea>
                                <p>Placeholder: <code>{nama}</code>, <code>{brand}</code>. Sesuaikan dulu sebelum dikirim.</p>
                            </div>
                            <div class="admin-funnel-action-script">
                                <h3>Email / Follow-up</h3>
                                <input readonly value="<?= esc((string)($row['email_subject'] ?? '')); ?>">
                                <textarea readonly><?= esc((string)($row['email_body'] ?? '')); ?></textarea>
                            </div>
                        </div>

                        <div class="admin-funnel-action-bottom">
                            <div>
                                <h3>Checklist Eksekusi</h3>
                                <ul>
                                    <?php foreach ((array)($row['checklist'] ?? []) as $check): ?>
                                        <li><?= esc((string)$check); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="admin-funnel-action-buttons">
                                <?php if (!empty($row['action_url'])): ?>
                                    <a class="admin-btn admin-btn--primary" href="<?= esc((string)$row['action_url']); ?>"><?= esc((string)($row['action_label'] ?? 'Buka Area Terkait')); ?></a>
                                <?php endif; ?>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/followups')); ?>">Catat Follow-up</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <div class="admin-card admin-empty-state">
                        <h2>Belum ada action yang cocok</h2>
                        <p>Coba reset filter atau tunggu data lead/order/interaksi lebih banyak.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
