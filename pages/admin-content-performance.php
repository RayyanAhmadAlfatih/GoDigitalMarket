<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$params = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['days' => 30, 'range' => '30', 'filters' => []];
$rangeLabel = function_exists('report_range_label') ? report_range_label((string)$params['range'], $params) : '30 hari';
$summary = function_exists('content_performance_summary') ? content_performance_summary((int)$params['days'], (array)$params['filters']) : ['metrics' => [], 'rows' => [], 'buckets' => [], 'action_plan' => []];

$type = (string)($_GET['type'] ?? 'all');
$allowedTypes = ['all', 'product', 'service', 'article', 'landing_page', 'seo_landing', 'portfolio', 'static_page', 'homepage'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

$bucket = (string)($_GET['bucket'] ?? 'all');
$allowedBuckets = ['all', 'scale_winner', 'cta_polish', 'seo_boost', 'build_support', 'monitor'];
if (!in_array($bucket, $allowedBuckets, true)) {
    $bucket = 'all';
}

$q = trim((string)($_GET['q'] ?? ''));
$rows = (array)($summary['rows'] ?? []);
$matchesQuery = static function (array $row) use ($q): bool {
    if ($q === '') {
        return true;
    }
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $haystack = implode(' ', [
        $row['title'] ?? '',
        $row['path'] ?? '',
        $row['type'] ?? '',
        $row['type_label'] ?? '',
        $row['bucket']['label'] ?? '',
        $row['recommendation'] ?? '',
        implode(' ', array_keys((array)($row['sources'] ?? []))),
    ]);
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, $needle);
};

$rows = array_values(array_filter($rows, static function (array $row) use ($type, $bucket, $matchesQuery): bool {
    if ($type !== 'all' && (string)($row['type'] ?? '') !== $type) {
        return false;
    }
    if ($bucket !== 'all' && (string)($row['bucket']['key'] ?? '') !== $bucket) {
        return false;
    }
    return $matchesQuery($row);
}));

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="content-performance-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'summary' => $summary,
        'filtered' => [
            'range' => $params['range'] ?? '30',
            'type' => $type,
            'bucket' => $bucket,
            'q' => $q,
            'rows' => $rows,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="content-performance-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['bucket','type','title','path','performance_score','seo_score','interactions','high_intent','whatsapp','inquiries','orders','intent_rate','top_source','recommendation'], ',', '"', '\\', "\n");
    foreach ($rows as $row) {
        $topSource = array_key_first((array)($row['sources'] ?? []));
        fputcsv($out, [
            $row['bucket']['label'] ?? '',
            $row['type_label'] ?? '',
            $row['title'] ?? '',
            $row['path'] ?? '',
            $row['performance_score'] ?? 0,
            $row['seo_score'] ?? 0,
            $row['metrics']['interactions'] ?? 0,
            $row['metrics']['high_intent'] ?? 0,
            $row['metrics']['whatsapp'] ?? 0,
            $row['metrics']['inquiries'] ?? 0,
            $row['metrics']['orders'] ?? 0,
            $row['intent_rate'] ?? 0,
            $topSource ?? '',
            $row['recommendation'] ?? '',
        ], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

function admin_content_performance_url(array $overrides = []): string
{
    $query = array_merge([
        'range' => $_GET['range'] ?? '30',
        'year' => $_GET['year'] ?? date('Y'),
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'type' => $_GET['type'] ?? 'all',
        'bucket' => $_GET['bucket'] ?? 'all',
        'q' => $_GET['q'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/content-performance' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_content_performance_bar(int $value, int $max): string
{
    $width = $max > 0 ? max(4, min(100, (int)round(($value / $max) * 100))) : 4;
    return '<span class="admin-content-performance-bar"><i style="width:' . esc((string)$width) . '%"></i></span>';
}

$metrics = (array)($summary['metrics'] ?? []);
$maxInteractions = max(1, ...array_map(static fn(array $row): int => (int)($row['metrics']['interactions'] ?? 0), $rows ?: [['metrics' => ['interactions' => 1]]]));
$bucketLabels = [
    'scale_winner' => 'Scale Winner',
    'cta_polish' => 'CTA Polish',
    'seo_boost' => 'SEO Boost',
    'build_support' => 'Build Support',
    'monitor' => 'Monitor',
];

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Content Performance Insight - Admin',
    'description' => 'Dashboard performa konten yang menghubungkan SEO inventory, CTA, lead event, WhatsApp, form, dan peluang conversion.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-content-performance-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>Content Performance Insight</h1>
                <p>Hubungkan kerja SEO dengan sinyal bisnis: halaman mana yang mulai menarik interaksi, mendorong WhatsApp/form/order, atau masih butuh CTA dan konten pendukung.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/growth-insights')); ?>">Growth Insight</a>
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-link-health')); ?>">Internal Link</a>
                <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/seo-execution-board')); ?>">Execution Board</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <div class="admin-report-grid admin-report-grid--four">
                <div class="admin-card admin-report-metric"><span class="admin-badge">Performance</span><h2><?= (int)($metrics['performance_score_avg'] ?? 0); ?>/100</h2><p>Rata-rata skor performa gabungan dari SEO score dan sinyal lead/conversion internal.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Halaman Aktif</span><h2><?= (int)($metrics['active_pages'] ?? 0); ?>/<?= (int)($metrics['pages_total'] ?? 0); ?></h2><p>Halaman yang sudah punya minimal satu interaksi/lead event dalam rentang aktif.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">High Intent</span><h2><?= (int)($metrics['high_intent'] ?? 0); ?></h2><p>Sinyal dekat transaksi: WhatsApp, form, checkout, order, atau event intent tinggi.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">CTA Polish</span><h2><?= (int)($metrics['cta_polish'] ?? 0); ?></h2><p>Halaman yang sudah ada interaksi tetapi belum cukup mendorong aksi lanjutan.</p></div>
            </div>

            <div class="admin-card admin-content-performance-action">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Action Plan</span>
                        <h2>Prioritas Growth Konten</h2>
                        <p>Rentang aktif: <strong><?= esc($rangeLabel); ?></strong>. Gunakan ini untuk menentukan halaman mana yang harus dipoles, disupport, atau langsung discale.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-content-planner')); ?>">Buka Content Planner</a>
                </div>
                <div class="admin-seo-link-plan-list">
                    <?php foreach ((array)($summary['action_plan'] ?? []) as $plan): ?>
                        <div><span>✓</span><p><?= esc((string)$plan); ?></p></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-content-performance-matrix">
                <?php foreach ($bucketLabels as $key => $label): $count = count((array)($summary['buckets'][$key] ?? [])); ?>
                    <a class="admin-card admin-content-performance-bucket <?= $bucket === $key ? 'is-active' : ''; ?>" href="<?= esc(admin_content_performance_url(['bucket' => $key, 'export' => ''])); ?>">
                        <span class="admin-badge"><?= esc($label); ?></span>
                        <strong><?= (int)$count; ?></strong>
                        <small><?= esc(match ($key) {
                            'scale_winner' => 'Konten siap diperbesar',
                            'cta_polish' => 'Traffic ada, CTA perlu tajam',
                            'seo_boost' => 'SEO dasar perlu dipoles',
                            'build_support' => 'Money page butuh artikel',
                            default => 'Pantau sinyal berikutnya',
                        }); ?></small>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" action="<?= esc(url('admin/content-performance')); ?>" class="admin-card admin-report-filter admin-content-performance-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Performance Filter</span>
                        <h3>Atur insight performa konten</h3>
                    </div>
                    <p>Pilih rentang waktu, tipe halaman, bucket peluang, atau cari halaman tertentu.</p>
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
                    <label><span>Tipe Halaman</span>
                        <select name="type">
                            <?php foreach ($allowedTypes as $value): ?>
                                <option value="<?= esc($value); ?>" <?= $type === $value ? 'selected' : ''; ?>><?= esc($value === 'all' ? 'Semua' : content_performance_item_label($value)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Bucket</span>
                        <select name="bucket">
                            <?php foreach (['all' => 'Semua Bucket'] + $bucketLabels as $value => $label): ?>
                                <option value="<?= esc((string)$value); ?>" <?= $bucket === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, path, sumber, rekomendasi..."></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/content-performance')); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_content_performance_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_content_performance_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <div class="admin-card admin-table-card admin-content-performance-table-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Content ROI Map</span>
                        <h2>Peta Performa Halaman</h2>
                        <p>Menampilkan <?= count($rows); ?> halaman setelah filter. Fokuskan eksekusi dari score dan rekomendasi paling actionable.</p>
                    </div>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table admin-content-performance-table">
                        <thead>
                            <tr>
                                <th>Score</th>
                                <th>Halaman</th>
                                <th>Sinyal</th>
                                <th>Bucket</th>
                                <th>Rekomendasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td>
                                        <span class="admin-content-performance-score"><?= (int)($row['performance_score'] ?? 0); ?><small>/100</small></span>
                                        <small>SEO <?= (int)($row['seo_score'] ?? 0); ?> · <?= esc((string)($row['grade'] ?? '')); ?></small>
                                    </td>
                                    <td>
                                        <strong><?= esc((string)($row['title'] ?? 'Halaman')); ?></strong>
                                        <small><?= esc((string)($row['type_label'] ?? 'Halaman')); ?> · <?= esc((string)($row['path'] ?? '')); ?></small>
                                        <?php if (!empty($row['latest_event_at'])): ?><small>Event terakhir: <?= esc((string)$row['latest_event_at']); ?></small><?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="admin-content-performance-signal">
                                            <div><span>Interaksi</span><strong><?= (int)($row['metrics']['interactions'] ?? 0); ?></strong></div>
                                            <?= admin_content_performance_bar((int)($row['metrics']['interactions'] ?? 0), $maxInteractions); ?>
                                            <small>Intent <?= esc((string)($row['intent_rate'] ?? 0)); ?>% · WA <?= (int)($row['metrics']['whatsapp'] ?? 0); ?> · Form <?= (int)($row['metrics']['inquiries'] ?? 0); ?> · Order <?= (int)($row['metrics']['orders'] ?? 0); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="admin-badge admin-content-performance-pill admin-content-performance-pill--<?= esc((string)($row['bucket']['tone'] ?? 'info')); ?>"><?= esc((string)($row['bucket']['label'] ?? 'Monitor')); ?></span>
                                        <small><?= esc((string)($row['bucket']['note'] ?? '')); ?></small>
                                    </td>
                                    <td><p><?= esc((string)($row['recommendation'] ?? 'Pantau performa.')); ?></p></td>
                                    <td>
                                        <div class="admin-table-actions">
                                            <?php if (!empty($row['url'])): ?><a class="admin-link" href="<?= esc((string)$row['url']); ?>" target="_blank" rel="noopener">Lihat</a><?php endif; ?>
                                            <?php if (!empty($row['edit_url'])): ?><a class="admin-link" href="<?= esc((string)$row['edit_url']); ?>">Edit</a><?php endif; ?>
                                            <a class="admin-link" href="<?= esc(url('admin/seo-link-health?q=' . rawurlencode((string)($row['title'] ?? '')))); ?>">Link</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$rows): ?>
                                <tr><td colspan="6"><p class="admin-empty-state">Belum ada halaman yang cocok dengan filter ini.</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
