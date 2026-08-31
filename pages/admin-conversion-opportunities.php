<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$params = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['days' => 30, 'range' => '30', 'filters' => []];
$rangeLabel = function_exists('report_range_label') ? report_range_label((string)$params['range'], $params) : '30 hari';
$summary = function_exists('conversion_opportunity_summary') ? conversion_opportunity_summary((int)$params['days'], (array)$params['filters']) : ['metrics' => [], 'opportunities' => [], 'action_plan' => []];

$type = (string)($_GET['type'] ?? 'all');
$allowedTypes = ['all', 'product', 'service', 'article', 'landing_page', 'seo_landing', 'portfolio', 'static_page', 'homepage'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

$category = (string)($_GET['category'] ?? 'all');
$allowedCategories = ['all', 'cta_gap', 'offer_gap', 'support_gap', 'seo_to_conversion', 'checkout_gap'];
if (!in_array($category, $allowedCategories, true)) {
    $category = 'all';
}

$impact = (string)($_GET['impact'] ?? 'all');
$allowedImpacts = ['all', 'critical', 'high', 'medium', 'low'];
if (!in_array($impact, $allowedImpacts, true)) {
    $impact = 'all';
}

$q = trim((string)($_GET['q'] ?? ''));
$items = (array)($summary['opportunities'] ?? []);
$matchesQuery = static function (array $item) use ($q): bool {
    if ($q === '') {
        return true;
    }
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $haystack = implode(' ', [
        $item['page']['title'] ?? '',
        $item['page']['path'] ?? '',
        $item['page']['type_label'] ?? '',
        $item['category_label'] ?? '',
        $item['impact']['label'] ?? '',
        $item['action']['title'] ?? '',
        $item['action']['body'] ?? '',
        $item['metrics']['top_source'] ?? '',
    ]);
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, $needle);
};

$items = array_values(array_filter($items, static function (array $item) use ($type, $category, $impact, $matchesQuery): bool {
    if ($type !== 'all' && (string)($item['page']['type'] ?? '') !== $type) {
        return false;
    }
    if ($category !== 'all' && (string)($item['category'] ?? '') !== $category) {
        return false;
    }
    if ($impact !== 'all' && (string)($item['impact']['key'] ?? '') !== $impact) {
        return false;
    }
    return $matchesQuery($item);
}));

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="conversion-opportunities-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'summary' => $summary,
        'filtered' => [
            'range' => $params['range'] ?? '30',
            'type' => $type,
            'category' => $category,
            'impact' => $impact,
            'q' => $q,
            'items' => $items,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="conversion-opportunities-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['impact','priority_score','category','type','title','path','seo_score','interactions','intent','whatsapp','form','order','top_source','action_title','action_body'], ',', '"', '\\', "\n");
    foreach ($items as $item) {
        fputcsv($out, [
            $item['impact']['label'] ?? '',
            $item['priority_score'] ?? 0,
            $item['category_label'] ?? '',
            $item['page']['type_label'] ?? '',
            $item['page']['title'] ?? '',
            $item['page']['path'] ?? '',
            $item['page']['seo_score'] ?? 0,
            $item['metrics']['interactions'] ?? 0,
            $item['metrics']['intent'] ?? 0,
            $item['metrics']['whatsapp'] ?? 0,
            $item['metrics']['inquiries'] ?? 0,
            $item['metrics']['orders'] ?? 0,
            $item['metrics']['top_source'] ?? '',
            $item['action']['title'] ?? '',
            $item['action']['body'] ?? '',
        ], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

function admin_conversion_opportunity_url(array $overrides = []): string
{
    $query = array_merge([
        'range' => $_GET['range'] ?? '30',
        'year' => $_GET['year'] ?? date('Y'),
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'type' => $_GET['type'] ?? 'all',
        'category' => $_GET['category'] ?? 'all',
        'impact' => $_GET['impact'] ?? 'all',
        'q' => $_GET['q'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/conversion-opportunities' . ($query ? '?' . http_build_query($query) : ''));
}

$metrics = (array)($summary['metrics'] ?? []);
$categoryLabels = [
    'cta_gap' => 'CTA Gap',
    'offer_gap' => 'Offer/Trust Gap',
    'support_gap' => 'Support Gap',
    'seo_to_conversion' => 'SEO → Conversion',
    'checkout_gap' => 'Checkout Gap',
];
$impactLabels = [
    'critical' => 'Kritis',
    'high' => 'Tinggi',
    'medium' => 'Sedang',
    'low' => 'Pantau',
];

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Conversion Opportunity Engine - Admin',
    'description' => 'Dashboard peluang konversi yang menghubungkan performa konten, CTA, trust, internal link, form, WhatsApp, dan checkout.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-conversion-opportunity-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Conversion Opportunity Engine</h1>
                <p>Baca halaman mana yang sudah punya sinyal SEO/interaksi, tapi belum maksimal jadi WhatsApp, form, checkout, atau order.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/content-performance')); ?>">Content Performance</a>
                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/growth-insights')); ?>">Growth Insight</a>
                <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/seo-execution-board')); ?>">Execution Board</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <div class="admin-report-grid admin-report-grid--four">
                <div class="admin-card admin-report-metric"><span class="admin-badge">Opportunity</span><h2><?= (int)($metrics['opportunities_total'] ?? 0); ?></h2><p>Total peluang optimasi conversion dari data konten dan sinyal lead internal.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Prioritas</span><h2><?= (int)(($metrics['critical'] ?? 0) + ($metrics['high'] ?? 0)); ?></h2><p>Peluang kritis/tinggi yang sebaiknya dikerjakan dulu agar efek bisnis lebih cepat terasa.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">CTA Gap</span><h2><?= (int)($metrics['cta_gap'] ?? 0); ?></h2><p>Halaman yang sudah ada interaksi, tetapi belum cukup mendorong aksi lanjutan.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Offer Gap</span><h2><?= (int)($metrics['offer_gap'] ?? 0); ?></h2><p>Money page dengan intent, tapi belum cukup kuat untuk closing/order.</p></div>
            </div>

            <div class="admin-card admin-conversion-opportunity-action">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Action Plan</span>
                        <h2>Prioritas Konversi dari Data Website</h2>
                        <p>Rentang aktif: <strong><?= esc($rangeLabel); ?></strong>. Fokusnya bukan cuma traffic, tapi mengubah sinyal menjadi lead dan order.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-optimization')); ?>">Optimasi Landing Page</a>
                </div>
                <div class="admin-seo-link-plan-list">
                    <?php foreach ((array)($summary['action_plan'] ?? []) as $plan): ?>
                        <div><span>✓</span><p><?= esc((string)$plan); ?></p></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-conversion-opportunity-matrix">
                <?php foreach ($categoryLabels as $key => $label): $count = (int)($metrics[$key] ?? 0); ?>
                    <a class="admin-card admin-conversion-opportunity-bucket <?= $category === $key ? 'is-active' : ''; ?>" href="<?= esc(admin_conversion_opportunity_url(['category' => $key, 'export' => ''])); ?>">
                        <span class="admin-badge"><?= esc($label); ?></span>
                        <strong><?= $count; ?></strong>
                        <small><?= esc((string)(function_exists('conversion_opportunity_category_meta') ? conversion_opportunity_category_meta($key)['note'] : 'Peluang optimasi.')); ?></small>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" action="<?= esc(url('admin/conversion-opportunities')); ?>" class="admin-card admin-report-filter admin-conversion-opportunity-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Opportunity Filter</span>
                        <h3>Atur fokus peluang konversi</h3>
                    </div>
                    <p>Pilih rentang waktu, jenis halaman, kategori gap, prioritas, atau cari halaman tertentu.</p>
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
                                <option value="<?= esc($value); ?>" <?= $type === $value ? 'selected' : ''; ?>><?= esc($value === 'all' ? 'Semua' : conversion_opportunity_type_label($value)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Kategori Gap</span>
                        <select name="category">
                            <?php foreach (['all' => 'Semua Gap'] + $categoryLabels as $value => $label): ?>
                                <option value="<?= esc((string)$value); ?>" <?= $category === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Prioritas</span>
                        <select name="impact">
                            <?php foreach (['all' => 'Semua Prioritas'] + $impactLabels as $value => $label): ?>
                                <option value="<?= esc((string)$value); ?>" <?= $impact === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, path, action, sumber..."></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/conversion-opportunities')); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_conversion_opportunity_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_conversion_opportunity_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <div class="admin-card admin-table-card admin-conversion-opportunity-table-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Conversion ROI Map</span>
                        <h2>Peta Peluang Konversi</h2>
                        <p>Menampilkan <?= count($items); ?> peluang setelah filter. Kerjakan dari prioritas tertinggi agar dampaknya lebih terasa ke lead/order.</p>
                    </div>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table admin-conversion-opportunity-table">
                        <thead>
                            <tr>
                                <th>Prioritas</th>
                                <th>Halaman</th>
                                <th>Sinyal</th>
                                <th>Gap</th>
                                <th>Action</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <span class="admin-conversion-opportunity-score admin-conversion-opportunity-score--<?= esc((string)($item['impact']['tone'] ?? 'info')); ?>"><?= (int)($item['priority_score'] ?? 0); ?><small>/100</small></span>
                                        <small><?= esc((string)($item['impact']['label'] ?? 'Prioritas')); ?></small>
                                    </td>
                                    <td>
                                        <strong><?= esc((string)($item['page']['title'] ?? 'Halaman')); ?></strong>
                                        <small><?= esc((string)($item['page']['type_label'] ?? 'Halaman')); ?> · <?= esc((string)($item['page']['path'] ?? '')); ?></small>
                                        <small>SEO <?= (int)($item['page']['seo_score'] ?? 0); ?> · <?= esc((string)($item['page']['grade'] ?? '')); ?></small>
                                    </td>
                                    <td>
                                        <div class="admin-conversion-opportunity-signal">
                                            <div><span>Interaksi</span><strong><?= (int)($item['metrics']['interactions'] ?? 0); ?></strong></div>
                                            <small>Intent <?= (int)($item['metrics']['intent'] ?? 0); ?> · WA <?= (int)($item['metrics']['whatsapp'] ?? 0); ?> · Form <?= (int)($item['metrics']['inquiries'] ?? 0); ?> · Order <?= (int)($item['metrics']['orders'] ?? 0); ?></small>
                                            <?php if (!empty($item['metrics']['top_source'])): ?><small>Sumber: <?= esc((string)$item['metrics']['top_source']); ?></small><?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="admin-badge admin-conversion-opportunity-pill"><?= esc((string)($item['category_label'] ?? 'Opportunity')); ?></span>
                                        <small><?= esc((string)($item['category_note'] ?? '')); ?></small>
                                    </td>
                                    <td>
                                        <strong><?= esc((string)($item['action']['title'] ?? 'Action')); ?></strong>
                                        <p><?= esc((string)($item['action']['body'] ?? '')); ?></p>
                                        <div class="admin-conversion-opportunity-checks">
                                            <?php foreach (array_slice((array)($item['action']['checklist'] ?? []), 0, 4) as $check): ?>
                                                <span><?= esc((string)$check); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="admin-table-actions">
                                            <?php if (!empty($item['page']['url'])): ?><a class="admin-link" href="<?= esc((string)$item['page']['url']); ?>" target="_blank" rel="noopener">Lihat</a><?php endif; ?>
                                            <?php if (!empty($item['page']['edit_url'])): ?><a class="admin-link" href="<?= esc((string)$item['page']['edit_url']); ?>">Edit</a><?php endif; ?>
                                            <?php if (!empty($item['action']['url'])): ?><a class="admin-link" href="<?= esc((string)$item['action']['url']); ?>"><?= esc((string)($item['action']['label'] ?? 'Action')); ?></a><?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$items): ?>
                                <tr><td colspan="6"><p class="admin-empty-state">Belum ada peluang yang cocok dengan filter ini.</p></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
