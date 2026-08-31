<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$params = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['range' => '30', 'days' => 30, 'filters' => [], 'date_from' => '', 'date_to' => '', 'year' => date('Y')];
$rangeLabel = function_exists('report_range_label') ? report_range_label((string)$params['range'], $params) : '30 hari';
$insight = function_exists('growth_insight_summary') ? growth_insight_summary((int)$params['days'], (array)$params['filters']) : [];
$report = (array)($insight['report'] ?? []);
$score = (array)($insight['score'] ?? []);
$contentHealth = (array)($insight['content_health'] ?? []);
$business = (array)($insight['business'] ?? []);

if ((string)($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($insight, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv' && function_exists('growth_insight_export_csv')) {
    growth_insight_export_csv($insight);
}

function admin_growth_url(array $params, array $extra = []): string
{
    $query = array_merge([
        'range' => (string)($params['range'] ?? '30'),
        'year' => (string)($params['year'] ?? date('Y')),
        'date_from' => (string)($params['date_from'] ?? ''),
        'date_to' => (string)($params['date_to'] ?? ''),
        'source' => (string)($params['filters']['source'] ?? ''),
        'category' => (string)($params['filters']['category'] ?? ''),
        'location' => (string)($params['filters']['location'] ?? ''),
        'status' => (string)($params['filters']['status'] ?? ''),
        'payment_status' => (string)($params['filters']['payment_status'] ?? ''),
        'search' => (string)($params['filters']['search'] ?? ''),
    ], $extra);

    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/growth-insights' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_growth_metric(string $label, string $value, string $note = '', string $tone = ''): void
{
    $toneClass = $tone !== '' ? ' admin-growth-metric--' . preg_replace('/[^a-z0-9\-]/', '', strtolower($tone)) : '';
    ?>
    <div class="admin-card admin-growth-metric<?= esc($toneClass); ?>">
        <span class="admin-badge"><?= esc($label); ?></span>
        <h2><?= esc($value); ?></h2>
        <?php if ($note !== ''): ?><p><?= esc($note); ?></p><?php endif; ?>
    </div>
    <?php
}

function admin_growth_bar_list(array $items, string $empty = 'Belum ada data.'): void
{
    if (!$items) {
        echo '<p class="admin-muted">' . esc($empty) . '</p>';
        return;
    }
    $max = max(array_map('intval', array_values($items)) ?: [1]);
    echo '<div class="admin-growth-bars">';
    foreach ($items as $label => $count) {
        $count = (int)$count;
        $width = $max > 0 ? max(4, (int)round(($count / $max) * 100)) : 4;
        echo '<div class="admin-growth-bar-row">';
        echo '<div class="admin-growth-bar-head"><span>' . esc((string)$label) . '</span><strong>' . esc((string)$count) . '</strong></div>';
        echo '<div class="admin-growth-bar-track"><i style="width:' . esc((string)$width) . '%"></i></div>';
        echo '</div>';
    }
    echo '</div>';
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Growth Insight - Admin',
    'description' => 'Dashboard insight bisnis untuk membaca lead, order, funnel, konten, dan rekomendasi action plan UMKM.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-growth-shell">
    <section class="admin-hero admin-growth-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Growth Insight</h1>
                <p>Dashboard praktis untuk membaca apakah website sudah membantu bisnis tumbuh: dari konten, klik, lead, order, pembayaran, sampai rekomendasi langkah berikutnya.</p>
            </div>
            <div class="admin-growth-score-card">
                <span>Growth Score</span>
                <strong><?= esc((string)($score['total'] ?? 0)); ?></strong>
                <small><?= esc((string)($score['label'] ?? 'Fondasi awal')); ?></small>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php admin_panel_render_nav('admin/growth-insights'); ?>

            <form method="get" class="admin-card admin-growth-filter">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Filter Insight</span>
                        <h2>Rentang & Segment Bisnis</h2>
                        <p>Rentang aktif: <strong><?= esc($rangeLabel); ?></strong>. Mode bisnis aktif: <strong><?= esc((string)($business['label'] ?? 'Hybrid Growth Website')); ?></strong>.</p>
                    </div>
                    <div class="admin-toolbar__actions">
                        <?php foreach (['7', '30', '90', '365', 'year', 'all'] as $rangeOption): ?>
                            <a class="admin-btn <?= $params['range'] === $rangeOption ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= esc(admin_growth_url($params, ['range' => $rangeOption, 'date_from' => '', 'date_to' => '', 'export' => '', 'format' => ''])); ?>"><?= esc($rangeOption === 'year' ? 'Tahun' : ($rangeOption === 'all' ? 'Semua' : $rangeOption . ' Hari')); ?></a>
                        <?php endforeach; ?>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_growth_url($params, ['export' => 'csv', 'format' => ''])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_growth_url($params, ['format' => 'json', 'export' => ''])); ?>" target="_blank" rel="noopener">JSON</a>
                    </div>
                </div>
                <div class="admin-growth-filter-grid">
                    <label>Range
                        <select name="range">
                            <?php foreach (report_allowed_ranges() as $rangeOption): ?>
                                <option value="<?= esc($rangeOption); ?>" <?= $params['range'] === $rangeOption ? 'selected' : ''; ?>><?= esc($rangeOption === 'year' ? 'Tahun tertentu' : ($rangeOption === 'all' ? 'Semua data' : ($rangeOption === 'custom' ? 'Custom tanggal' : $rangeOption . ' hari'))); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Tahun
                        <input type="number" name="year" value="<?= esc((string)$params['year']); ?>" min="2020" max="2100">
                    </label>
                    <label>Dari
                        <input type="date" name="date_from" value="<?= esc((string)$params['date_from']); ?>">
                    </label>
                    <label>Sampai
                        <input type="date" name="date_to" value="<?= esc((string)$params['date_to']); ?>">
                    </label>
                    <label>Sumber Lead
                        <input type="text" name="source" value="<?= esc((string)($params['filters']['source'] ?? '')); ?>" placeholder="website, katalog, ads...">
                    </label>
                    <label>Lokasi
                        <input type="text" name="location" value="<?= esc((string)($params['filters']['location'] ?? '')); ?>" placeholder="kota / area layanan">
                    </label>
                    <label>Status Order
                        <input type="text" name="status" value="<?= esc((string)($params['filters']['status'] ?? '')); ?>" placeholder="Baru, Deal, Selesai...">
                    </label>
                    <label>Pencarian
                        <input type="search" name="search" value="<?= esc((string)($params['filters']['search'] ?? '')); ?>" placeholder="produk, ref, kebutuhan...">
                    </label>
                    <div class="admin-growth-filter-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/growth-insights')); ?>">Reset</a>
                    </div>
                </div>
            </form>

            <div class="admin-grid admin-grid--stats admin-growth-main-stats">
                <?php admin_growth_metric('Lead Event', number_format((int)($report['lead']['events'] ?? 0), 0, ',', '.'), 'Klik CTA, WhatsApp, form, dan event tracking.', 'info'); ?>
                <?php admin_growth_metric('High Intent', number_format((int)($report['lead']['high_intent'] ?? 0), 0, ',', '.'), 'Prospek yang lebih dekat ke aksi beli/daftar.', 'ok'); ?>
                <?php admin_growth_metric('Order', number_format((int)($report['order']['total'] ?? 0), 0, ',', '.'), 'Pesanan yang masuk pada rentang aktif.', 'info'); ?>
                <?php admin_growth_metric('Estimasi Omzet', function_exists('rupiah') ? rupiah((int)($report['sales']['estimate'] ?? 0)) : (string)($report['sales']['estimate'] ?? 0), 'Total nilai order terdeteksi.', 'ok'); ?>
                <?php admin_growth_metric('Lead → Order', (string)($report['conversion']['lead_to_order_rate'] ?? 0) . '%', 'Rasio event lead menjadi order.', 'warning'); ?>
                <?php admin_growth_metric('AOV', function_exists('rupiah') ? rupiah((int)($report['sales']['average_order_value'] ?? 0)) : (string)($report['sales']['average_order_value'] ?? 0), 'Rata-rata nilai order.', 'info'); ?>
            </div>

            <div class="admin-growth-layout">
                <section class="admin-card admin-growth-score-detail">
                    <div class="admin-form-head">
                        <span class="admin-badge admin-status-pill--<?= esc((string)($score['tone'] ?? 'info')); ?>">Score</span>
                        <h2><?= esc((string)($score['label'] ?? 'Fondasi awal')); ?></h2>
                        <p>Score ini bukan angka final mutlak, tapi indikator cepat dari traffic, intent, conversion, sales, dan fondasi konten.</p>
                    </div>
                    <div class="admin-growth-score-ring" style="--score: <?= esc((string)max(0, min(100, (int)($score['total'] ?? 0)))); ?>;">
                        <strong><?= esc((string)($score['total'] ?? 0)); ?></strong>
                        <span>/100</span>
                    </div>
                    <div class="admin-growth-score-parts">
                        <?php foreach ((array)($score['parts'] ?? []) as $label => $value): ?>
                            <div><span><?= esc(ucwords(str_replace('_', ' ', (string)$label))); ?></span><strong><?= esc((string)$value); ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-card admin-growth-recommendations">
                    <div class="admin-form-head">
                        <span class="admin-badge">Action Plan</span>
                        <h2>Rekomendasi Keputusan Bisnis</h2>
                        <p>Insight rule-based dari data website, bukan sekadar statistik mentah.</p>
                    </div>
                    <div class="admin-growth-rec-list">
                        <?php foreach ((array)($insight['recommendations'] ?? []) as $item): ?>
                            <article class="admin-growth-rec-item">
                                <span><?= esc((string)($item['priority'] ?? 'Insight')); ?></span>
                                <h3><?= esc((string)($item['title'] ?? 'Insight')); ?></h3>
                                <p><?= esc((string)($item['body'] ?? '')); ?></p>
                                <?php if (!empty($item['action_url']) && !empty($item['action_label'])): ?>
                                    <a href="<?= esc((string)$item['action_url']); ?>"><?= esc((string)$item['action_label']); ?> →</a>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <div class="admin-grid admin-growth-panels">
                <section class="admin-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">Funnel</span>
                        <h2>Alur Growth</h2>
                        <p>Lihat titik mana yang paling perlu dikuatkan: traffic, intent, inquiry, order, bayar, atau closing.</p>
                    </div>
                    <div class="admin-growth-funnel">
                        <?php foreach ((array)($insight['funnel'] ?? []) as $row): ?>
                            <?php $rate = max(0, min(100, (float)($row['rate'] ?? 0))); ?>
                            <div class="admin-growth-funnel-row">
                                <div><strong><?= esc((string)($row['label'] ?? '')); ?></strong><small><?= esc((string)($row['note'] ?? '')); ?></small></div>
                                <span><?= esc(number_format((int)($row['value'] ?? 0), 0, ',', '.')); ?></span>
                                <i><b style="width: <?= esc((string)max(3, (int)$rate)); ?>%"></b></i>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">Sumber</span>
                        <h2>Sumber Lead</h2>
                        <p>Channel atau sumber yang paling sering mendorong interaksi.</p>
                    </div>
                    <?php admin_growth_bar_list((array)($report['breakdowns']['lead_source'] ?? [])); ?>
                </section>

                <section class="admin-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">Produk</span>
                        <h2>Produk/Paket Teratas</h2>
                        <p>Penawaran yang paling sering muncul di order.</p>
                    </div>
                    <?php admin_growth_bar_list((array)($report['breakdowns']['product'] ?? [])); ?>
                </section>

                <section class="admin-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">Konten</span>
                        <h2>Fondasi Konten & SEO</h2>
                        <p>Semakin rapi katalog, artikel, landing page, form, dan kategori, semakin mudah website dipakai untuk scale.</p>
                    </div>
                    <div class="admin-growth-content-health">
                        <div><strong><?= esc((string)($contentHealth['products_active'] ?? 0)); ?></strong><span><?= esc(function_exists('business_label') ? business_label('product', 'Produk') : 'Produk'); ?> aktif</span></div>
                        <div><strong><?= esc((string)($contentHealth['articles_published'] ?? 0)); ?></strong><span>Artikel publish</span></div>
                        <div><strong><?= esc((string)($contentHealth['landing_pages_published'] ?? 0)); ?></strong><span>Landing page publish</span></div>
                        <div><strong><?= esc((string)($contentHealth['forms_active'] ?? 0)); ?></strong><span>Form aktif</span></div>
                        <div><strong><?= esc((string)($contentHealth['catalog_categories'] ?? 0)); ?></strong><span>Kategori katalog</span></div>
                        <div><strong><?= esc((string)($contentHealth['article_categories'] ?? 0)); ?></strong><span>Kategori artikel</span></div>
                    </div>
                </section>
            </div>

            <section class="admin-card admin-growth-next-map">
                <div class="admin-form-head">
                    <span class="admin-badge">Roadmap Scale</span>
                    <h2>Peta Keputusan Cepat</h2>
                    <p>Gunakan peta ini untuk menentukan area yang perlu dikerjakan dulu, sesuai data yang sedang masuk.</p>
                </div>
                <div class="admin-growth-map-grid">
                    <div><strong>Traffic</strong><span>Perkuat SEO artikel, landing page, dan campaign UTM.</span><a href="<?= esc(url('admin/seo-quality')); ?>">Cek SEO</a></div>
                    <div><strong>Conversion</strong><span>Optimasi copy, CTA, form, dan bukti sosial.</span><a href="<?= esc(url('admin/landing-page-optimization')); ?>">Optimasi LP</a></div>
                    <div><strong>Sales</strong><span>Rapikan katalog, harga, paket, dan follow-up order.</span><a href="<?= esc(url('admin/produk')); ?>">Katalog</a></div>
                    <div><strong>Payment</strong><span>Percepat invoice, reminder, dan validasi bukti bayar.</span><a href="<?= esc(url('admin/payment-reminders')); ?>">Reminder</a></div>
                    <div><strong>Retention</strong><span>Gunakan WA/email untuk follow-up customer lama dan upsell.</span><a href="<?= esc(url('admin/marketing-integrations')); ?>">Marketing</a></div>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
