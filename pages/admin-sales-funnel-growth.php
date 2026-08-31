<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$params = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['days' => 30, 'range' => '30', 'filters' => []];
$rangeLabel = function_exists('report_range_label') ? report_range_label((string)$params['range'], $params) : '30 hari';
$summary = function_exists('sales_funnel_growth_summary') ? sales_funnel_growth_summary((int)$params['days'], (array)$params['filters']) : [];

$stage = (string)($_GET['stage'] ?? 'all');
$allowedStages = ['all', 'traffic', 'intent', 'inquiry', 'order', 'payment', 'closing'];
if (!in_array($stage, $allowedStages, true)) {
    $stage = 'all';
}

$priority = (string)($_GET['priority'] ?? 'all');
$allowedPriorities = ['all', 'Kritis', 'Tinggi', 'Scale', 'Pantau'];
if (!in_array($priority, $allowedPriorities, true)) {
    $priority = 'all';
}

$q = trim((string)($_GET['q'] ?? ''));
$matchesQuery = static function (array $item) use ($q): bool {
    if ($q === '') {
        return true;
    }
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $haystack = implode(' ', [
        $item['title'] ?? '',
        $item['body'] ?? '',
        $item['priority'] ?? '',
        $item['stage'] ?? '',
    ]);
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, $needle);
};

$actionPlan = array_values(array_filter((array)($summary['action_plan'] ?? []), static function (array $item) use ($priority, $matchesQuery): bool {
    if ($priority !== 'all' && (string)($item['priority'] ?? '') !== $priority) {
        return false;
    }
    return $matchesQuery($item);
}));

$stageRows = array_values(array_filter((array)($summary['stages'] ?? []), static function (array $item) use ($stage): bool {
    return $stage === 'all' || (string)($item['key'] ?? '') === $stage;
}));

function admin_sales_funnel_url(array $overrides = []): string
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
    return url('admin/sales-funnel-growth' . ($query ? '?' . http_build_query($query) : ''));
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-funnel-growth-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'summary' => $summary,
        'filtered' => [
            'range' => $params['range'] ?? '30',
            'stage' => $stage,
            'priority' => $priority,
            'q' => $q,
            'action_plan' => $actionPlan,
            'stages' => $stageRows,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales-funnel-growth-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['section','stage','label','value','rate','drop','health','priority','title','body'], ',', '"', '\\', "\n");
    foreach ((array)($summary['stages'] ?? []) as $row) {
        fputcsv($out, ['stage', $row['key'] ?? '', $row['label'] ?? '', $row['value'] ?? 0, $row['conversion_rate'] ?? 0, $row['drop'] ?? 0, $row['health']['label'] ?? '', '', '', $row['focus'] ?? ''], ',', '"', '\\', "\n");
    }
    foreach ((array)($summary['action_plan'] ?? []) as $row) {
        fputcsv($out, ['action', '', '', '', '', '', '', $row['priority'] ?? '', $row['title'] ?? '', $row['body'] ?? ''], ',', '"', '\\', "\n");
    }
    foreach ((array)($summary['bottlenecks'] ?? []) as $row) {
        fputcsv($out, ['bottleneck', $row['stage'] ?? '', '', $row['score'] ?? 0, '', '', $row['tone'] ?? '', '', $row['title'] ?? '', $row['body'] ?? ''], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

$score = (array)($summary['score'] ?? ['total' => 0, 'label' => 'Belum Ada Data', 'tone' => 'neutral', 'inputs' => []]);
$report = (array)($summary['report'] ?? []);
$lead = (array)($report['lead'] ?? []);
$order = (array)($report['order'] ?? []);
$sales = (array)($report['sales'] ?? []);
$payment = (array)($report['payment'] ?? []);
$contentMetrics = (array)($summary['content_performance']['metrics'] ?? []);
$oppMetrics = (array)($summary['conversion_opportunity']['metrics'] ?? []);
$stageOptions = [
    'all' => 'Semua Tahap',
    'traffic' => 'Traffic',
    'intent' => 'High Intent',
    'inquiry' => 'Inquiry/Form',
    'order' => 'Order',
    'payment' => 'Pembayaran',
    'closing' => 'Closing',
];

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Sales Funnel Growth Engine - Admin',
    'description' => 'Dashboard funnel bisnis yang menghubungkan SEO, konten, CTA, lead, inquiry, order, pembayaran, dan closing.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-sales-funnel-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Sales Funnel Growth Engine</h1>
                <p>Satukan SEO, Content Performance, Conversion Opportunities, lead, order, pembayaran, dan closing menjadi peta kerja growth yang mudah dieksekusi.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/conversion-opportunities')); ?>">Conversion Opportunities</a>
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/content-performance')); ?>">Content Performance</a>
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/funnel-action-center')); ?>">Funnel Action Center</a>
                <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/growth-insights')); ?>">Growth Insight</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <div class="admin-sales-funnel-hero-grid">
                <div class="admin-card admin-sales-funnel-score admin-sales-funnel-score--<?= esc((string)($score['tone'] ?? 'neutral')); ?>">
                    <span class="admin-badge">Funnel Score</span>
                    <strong><?= (int)($score['total'] ?? 0); ?><small>/100</small></strong>
                    <h2><?= esc((string)($score['label'] ?? 'Belum Ada Data')); ?></h2>
                    <p>Skor gabungan dari lead → order, inquiry → order, pembayaran, closing, active page coverage, dan gap konversi.</p>
                </div>
                <div class="admin-card admin-sales-funnel-kpis">
                    <span class="admin-badge">Business Snapshot</span>
                    <div><span>Lead Event</span><strong><?= (int)($lead['events'] ?? 0); ?></strong></div>
                    <div><span>High Intent</span><strong><?= (int)($lead['high_intent'] ?? 0); ?></strong></div>
                    <div><span>Order</span><strong><?= (int)($order['total'] ?? 0); ?></strong></div>
                    <div><span>Closing</span><strong><?= (int)($order['completed'] ?? 0); ?></strong></div>
                    <div><span>Estimasi Sales</span><strong><?= function_exists('format_rupiah') ? format_rupiah((int)($sales['estimate'] ?? 0)) : ('Rp ' . number_format((int)($sales['estimate'] ?? 0), 0, ',', '.')); ?></strong></div>
                    <div><span>Payment Proof</span><strong><?= (int)($payment['proofs'] ?? 0); ?></strong></div>
                </div>
                <div class="admin-card admin-sales-funnel-kpis">
                    <span class="admin-badge">Growth Inputs</span>
                    <div><span>Active Pages</span><strong><?= (int)($contentMetrics['active_pages'] ?? 0); ?>/<?= (int)($contentMetrics['pages_total'] ?? 0); ?></strong></div>
                    <div><span>Scale Winner</span><strong><?= (int)($contentMetrics['scale_winners'] ?? 0); ?></strong></div>
                    <div><span>CTA Gap</span><strong><?= (int)($oppMetrics['cta_gap'] ?? 0); ?></strong></div>
                    <div><span>Offer Gap</span><strong><?= (int)($oppMetrics['offer_gap'] ?? 0); ?></strong></div>
                    <div><span>Checkout Gap</span><strong><?= (int)($oppMetrics['checkout_gap'] ?? 0); ?></strong></div>
                    <div><span>Priority Gap</span><strong><?= (int)(($oppMetrics['critical'] ?? 0) + ($oppMetrics['high'] ?? 0)); ?></strong></div>
                </div>
            </div>

            <form method="get" action="<?= esc(url('admin/sales-funnel-growth')); ?>" class="admin-card admin-report-filter admin-sales-funnel-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Funnel Filter</span>
                        <h3>Atur fokus funnel bisnis</h3>
                    </div>
                    <p>Rentang aktif: <strong><?= esc($rangeLabel); ?></strong>. Filter ini membantu memilih tahap funnel, prioritas action, atau kata kunci tertentu.</p>
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
                    <label><span>Tahap Funnel</span>
                        <select name="stage">
                            <?php foreach ($stageOptions as $value => $label): ?>
                                <option value="<?= esc((string)$value); ?>" <?= $stage === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Prioritas</span>
                        <select name="priority">
                            <?php foreach ($allowedPriorities as $value): ?>
                                <option value="<?= esc($value); ?>" <?= $priority === $value ? 'selected' : ''; ?>><?= esc($value === 'all' ? 'Semua Prioritas' : $value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="cta, offer, checkout, payment, support..."></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/sales-funnel-growth')); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_sales_funnel_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_sales_funnel_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <div class="admin-card admin-sales-funnel-map">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Funnel Map</span>
                        <h2>Peta Alur Growth Bisnis</h2>
                        <p>Lihat tahap mana yang paling bocor: traffic, intent, form, order, payment, atau closing.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/reports')); ?>">Laporan & Insight</a>
                </div>
                <div class="admin-sales-funnel-stage-grid">
                    <?php foreach ($stageRows as $row): ?>
                        <div class="admin-sales-funnel-stage admin-sales-funnel-stage--<?= esc((string)($row['health']['tone'] ?? 'neutral')); ?>">
                            <span><?= esc((string)($row['label'] ?? 'Funnel')); ?></span>
                            <strong><?= (int)($row['value'] ?? 0); ?></strong>
                            <small><?= esc((string)($row['health']['label'] ?? 'Pantau')); ?> · Rate <?= esc((string)($row['conversion_rate'] ?? 0)); ?>%</small>
                            <p><?= esc((string)($row['focus'] ?? 'Pantau tahap ini.')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-grid-two admin-sales-funnel-layout">
                <div class="admin-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">Action Plan</span>
                        <h2>Prioritas Eksekusi Funnel</h2>
                        <p>Kerjakan dari prioritas tertinggi agar SEO lebih cepat berubah menjadi lead, order, dan sales.</p>
                    </div>
                    <div class="admin-sales-funnel-action-list">
                        <?php foreach ($actionPlan as $item): ?>
                            <div>
                                <span><?= esc((string)($item['priority'] ?? 'Pantau')); ?></span>
                                <strong><?= esc((string)($item['title'] ?? 'Action')); ?></strong>
                                <p><?= esc((string)($item['body'] ?? 'Lanjutkan optimasi.')); ?></p>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$actionPlan): ?><p class="admin-empty-state">Tidak ada action plan yang cocok dengan filter ini.</p><?php endif; ?>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">Bottleneck</span>
                        <h2>Titik Bocor Terbesar</h2>
                        <p>Sistem membaca drop antar tahap funnel dan gap conversion dari halaman prioritas.</p>
                    </div>
                    <div class="admin-sales-funnel-bottlenecks">
                        <?php foreach ((array)($summary['bottlenecks'] ?? []) as $item): ?>
                            <a href="<?= esc(url('admin/conversion-opportunities?q=' . rawurlencode((string)($item['title'] ?? '')))); ?>">
                                <strong><?= esc((string)($item['title'] ?? 'Bottleneck')); ?></strong>
                                <span><?= (int)($item['score'] ?? 0); ?>/100</span>
                                <p><?= esc((string)($item['body'] ?? 'Rapikan tahap ini.')); ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="admin-card admin-sales-funnel-playbook">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Playbook</span>
                        <h2>Growth Playbook Siap Pakai</h2>
                        <p>Pilih playbook sesuai tahap funnel yang ingin diperkuat.</p>
                    </div>
                    <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/seo-execution-board')); ?>">Buka Execution Board</a>
                </div>
                <div class="admin-sales-funnel-playbook-grid">
                    <?php foreach ((array)($summary['playbooks'] ?? []) as $item): ?>
                        <div>
                            <span><?= esc((string)($item['stage'] ?? 'Growth')); ?></span>
                            <strong><?= esc((string)($item['title'] ?? 'Playbook')); ?></strong>
                            <p><?= esc((string)($item['body'] ?? 'Jalankan playbook ini.')); ?></p>
                            <?php if (!empty($item['url'])): ?><a class="admin-link" href="<?= esc((string)$item['url']); ?>"><?= esc((string)($item['label'] ?? 'Buka')); ?> →</a><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card admin-sales-funnel-sprint">
                <div class="admin-form-head">
                    <span class="admin-badge">30 Hari</span>
                    <h2>Sprint Scale Funnel UMKM</h2>
                    <p>Roadmap praktis agar pemilik bisnis tidak bingung harus mulai dari mana.</p>
                </div>
                <div class="admin-sales-funnel-sprint-grid">
                    <?php foreach ((array)($summary['sprint'] ?? []) as $week): ?>
                        <div>
                            <span><?= esc((string)($week['week'] ?? 'Minggu')); ?></span>
                            <strong><?= esc((string)($week['title'] ?? 'Sprint')); ?></strong>
                            <ul>
                                <?php foreach ((array)($week['tasks'] ?? []) as $task): ?>
                                    <li><?= esc((string)$task); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
