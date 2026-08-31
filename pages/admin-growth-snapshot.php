<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$params = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['range' => '30', 'year' => date('Y'), 'days' => 30, 'filters' => [], 'date_from' => '', 'date_to' => ''];
$days = (int)($params['days'] ?? 30);
$filters = (array)($params['filters'] ?? []);
$snapshot = function_exists('growth_snapshot_summary') ? growth_snapshot_summary($days, $filters) : [];
$rangeLabel = function_exists('report_range_label') ? report_range_label((string)($params['range'] ?? '30'), $params) : '30 hari terakhir';

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="growth-snapshot-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'schema' => 'growth-snapshot-report',
        'range_label' => $rangeLabel,
        'snapshot' => $snapshot,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="growth-snapshot-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['section', 'label', 'value', 'note'], ',', '"', '\\', "\n");
    foreach ((array)($snapshot['kpis'] ?? []) as $kpi) {
        fputcsv($out, ['KPI', (string)($kpi['label'] ?? ''), (string)($kpi['value'] ?? ''), (string)($kpi['note'] ?? '')], ',', '"', '\\', "\n");
    }
    foreach ((array)($snapshot['highlights'] ?? []) as $item) {
        fputcsv($out, ['Highlight', '', (string)$item, ''], ',', '"', '\\', "\n");
    }
    foreach ((array)($snapshot['next_moves'] ?? []) as $item) {
        fputcsv($out, ['Next Move', '', (string)$item, ''], ',', '"', '\\', "\n");
    }
    foreach ((array)($snapshot['top_actions'] ?? []) as $row) {
        fputcsv($out, ['Action', (string)($row['stage_label'] ?? ''), (string)($row['title'] ?? ''), (string)($row['reason'] ?? '')], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Growth Snapshot Report - Admin',
    'description' => 'Ringkasan presentasi growth UMKM: SEO, konten, funnel, conversion opportunity, dan action plan dalam satu laporan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';

$score = (array)($snapshot['score'] ?? []);
$scores = (array)($snapshot['scores'] ?? []);
$metrics = (array)($snapshot['metrics'] ?? []);
$business = (array)($snapshot['business'] ?? []);
$stageRows = (array)($snapshot['sources']['funnel']['stages'] ?? []);
?>

<main id="main-content" class="admin-shell admin-growth-snapshot-shell">
    <section class="admin-hero admin-growth-snapshot-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Growth Snapshot Report</h1>
                <p>Laporan ringkas untuk menjelaskan ke pelaku UMKM bahwa SEO, konten, CTA, funnel, follow-up, dan order saling terhubung untuk growth bisnis.</p>
            </div>
            <div class="admin-toolbar__actions">
                <button class="admin-btn admin-btn--soft" type="button" onclick="window.print()">Print / PDF</button>
                <a class="admin-btn admin-btn--soft" href="<?= esc(growth_snapshot_current_url($params, ['export' => 'csv'])); ?>">Export CSV</a>
                <a class="admin-btn admin-btn--primary" href="<?= esc(growth_snapshot_current_url($params, ['export' => 'json'])); ?>">Export JSON</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <form method="get" action="<?= esc(url('admin/growth-snapshot')); ?>" class="admin-card admin-report-filter admin-growth-snapshot-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Snapshot Filter</span>
                        <h3>Atur periode laporan</h3>
                    </div>
                    <p>Periode aktif: <strong><?= esc($rangeLabel); ?></strong>. Cocok untuk demo, evaluasi mingguan, atau bahan meeting dengan owner bisnis.</p>
                </div>
                <div class="admin-report-filter-grid">
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
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/growth-snapshot')); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/sales-funnel-growth')); ?>">Sales Funnel</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/funnel-action-center')); ?>">Action Center</a>
                </div>
            </form>

            <div class="admin-growth-snapshot-top-grid">
                <div class="admin-card admin-growth-snapshot-score admin-growth-snapshot-score--<?= esc((string)($score['tone'] ?? 'neutral')); ?>">
                    <span class="admin-badge">UMKM Growth Readiness</span>
                    <strong><?= (int)($score['total'] ?? 0); ?><small>/100</small></strong>
                    <h2><?= esc((string)($score['label'] ?? 'Growth Snapshot')); ?></h2>
                    <p><?= esc((string)($score['note'] ?? 'Ringkasan kesiapan website untuk SEO dan growth bisnis.')); ?></p>
                </div>
                <div class="admin-card admin-growth-snapshot-business">
                    <span class="admin-badge">Business Context</span>
                    <h2><?= esc((string)($business['name'] ?? SITE_NAME)); ?></h2>
                    <p><?= esc((string)($business['tagline'] ?? SITE_TAGLINE)); ?></p>
                    <div><span>Mode</span><strong><?= esc((string)($business['mode'] ?? 'Hybrid Growth Website')); ?></strong></div>
                    <div><span>Periode</span><strong><?= esc($rangeLabel); ?></strong></div>
                    <div><span>Website</span><strong><?= esc((string)($business['url'] ?? SITE_URL)); ?></strong></div>
                </div>
                <div class="admin-card admin-growth-snapshot-score-stack">
                    <span class="admin-badge">Engine Score</span>
                    <?php foreach ([
                        'seo' => 'SEO',
                        'funnel' => 'Funnel',
                        'content' => 'Content',
                        'internal_link' => 'Internal Link',
                        'action_readiness' => 'Action Ready',
                    ] as $key => $label): ?>
                        <div><span><?= esc($label); ?></span><strong><?= (int)($scores[$key] ?? 0); ?>/100</strong><i style="--w:<?= (int)($scores[$key] ?? 0); ?>%"></i></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-growth-snapshot-kpis">
                <?php foreach ((array)($snapshot['kpis'] ?? []) as $kpi): ?>
                    <div class="admin-card admin-growth-snapshot-kpi admin-growth-snapshot-kpi--<?= esc((string)($kpi['tone'] ?? 'neutral')); ?>">
                        <span><?= esc((string)($kpi['label'] ?? 'KPI')); ?></span>
                        <strong><?= esc((string)($kpi['value'] ?? 0)); ?></strong>
                        <small><?= esc((string)($kpi['note'] ?? '')); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="admin-grid-2 admin-growth-snapshot-story-grid">
                <div class="admin-card admin-growth-snapshot-report-card">
                    <span class="admin-badge">Narasi Untuk Owner UMKM</span>
                    <h2>Cerita Growth Website</h2>
                    <div class="admin-growth-snapshot-story">
                        <?php foreach ((array)($snapshot['story'] ?? []) as $index => $item): ?>
                            <p><strong><?= $index + 1; ?></strong><?= esc((string)$item); ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="admin-card admin-growth-snapshot-report-card">
                    <span class="admin-badge">Key Highlight</span>
                    <h2>Yang Bisa Ditunjukkan</h2>
                    <ul class="admin-growth-snapshot-list">
                        <?php foreach ((array)($snapshot['highlights'] ?? []) as $item): ?>
                            <li><?= esc((string)$item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="admin-card admin-growth-snapshot-funnel-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Funnel View</span>
                        <h2>SEO → Lead → Order → Payment → Scale</h2>
                        <p>Bagian ini membantu menjelaskan bahwa SEO bukan sekadar ranking, tapi pintu masuk menuju interaksi, inquiry, order, dan follow-up.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/sales-funnel-growth')); ?>">Buka Sales Funnel</a>
                </div>
                <div class="admin-growth-snapshot-funnel-grid">
                    <?php foreach ($stageRows as $stage): ?>
                        <div class="admin-growth-snapshot-funnel-stage admin-growth-snapshot-funnel-stage--<?= esc((string)($stage['tone'] ?? 'neutral')); ?>">
                            <span><?= esc((string)($stage['label'] ?? 'Stage')); ?></span>
                            <strong><?= (int)($stage['value'] ?? 0); ?></strong>
                            <small><?= esc((string)($stage['note'] ?? '')); ?></small>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$stageRows): ?>
                        <div class="admin-empty-state">Belum ada data funnel. Aktifkan tracking lead dan mulai gunakan CTA/checkout agar funnel terbaca.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-grid-2 admin-growth-snapshot-lists">
                <div class="admin-card admin-growth-snapshot-report-card">
                    <span class="admin-badge">Next Move</span>
                    <h2>Prioritas Eksekusi Berikutnya</h2>
                    <ol class="admin-growth-snapshot-steps">
                        <?php foreach ((array)($snapshot['next_moves'] ?? []) as $item): ?>
                            <li><?= esc((string)$item); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
                <div class="admin-card admin-growth-snapshot-report-card">
                    <span class="admin-badge">30 Hari</span>
                    <h2>Sprint Growth Singkat</h2>
                    <div class="admin-growth-snapshot-sprint">
                        <?php foreach ((array)($snapshot['sprint'] ?? []) as $week): ?>
                            <div>
                                <strong><?= esc((string)($week['week'] ?? 'Sprint') . ' · ' . (string)($week['title'] ?? 'Eksekusi Growth')); ?></strong>
                                <?php $tasks = array_values(array_filter((array)($week['tasks'] ?? []), static fn($task): bool => trim((string)$task) !== '')); ?>
                                <p><?= esc($tasks ? implode(' · ', array_slice($tasks, 0, 3)) : (string)($week['body'] ?? 'Eksekusi action plan.')); ?></p>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($snapshot['sprint'])): ?>
                            <div><strong>Minggu 1</strong><p>Rapikan SEO dasar, CTA utama, dan halaman penawaran prioritas.</p></div>
                            <div><strong>Minggu 2</strong><p>Buat artikel support dan internal link ke money page.</p></div>
                            <div><strong>Minggu 3</strong><p>Follow-up lead dan cek bottleneck order/payment.</p></div>
                            <div><strong>Minggu 4</strong><p>Evaluasi Growth Insight dan scale halaman pemenang.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="admin-card admin-growth-snapshot-report-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Action Preview</span>
                        <h2>Aksi yang Siap Dipakai Tim</h2>
                        <p>Diambil dari Funnel Action Center, Conversion Opportunities, Content Performance, dan Mini CRM.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/funnel-action-center')); ?>">Buka Semua Aksi</a>
                </div>
                <div class="admin-growth-snapshot-action-grid">
                    <?php foreach ((array)($snapshot['top_actions'] ?? []) as $row): ?>
                        <article class="admin-growth-snapshot-action">
                            <span><?= esc((string)($row['priority']['label'] ?? 'Aksi')); ?> · <?= esc((string)($row['stage_label'] ?? 'Funnel')); ?></span>
                            <h3><?= esc((string)($row['title'] ?? 'Action')); ?></h3>
                            <p><?= esc((string)($row['reason'] ?? '')); ?></p>
                            <?php if (!empty($row['action_url'])): ?><a href="<?= esc((string)$row['action_url']); ?>"><?= esc((string)($row['action_label'] ?? 'Buka')); ?> →</a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($snapshot['top_actions'])): ?>
                        <div class="admin-empty-state">Belum ada action otomatis. Mulai dari tracking lead, conversion opportunity, dan content performance.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-growth-snapshot-bottom-grid">
                <div class="admin-card admin-growth-snapshot-report-card">
                    <span class="admin-badge">Content ROI</span>
                    <h2>Konten Prioritas</h2>
                    <div class="admin-growth-snapshot-mini-table">
                        <?php foreach ((array)($snapshot['top_content'] ?? []) as $row): ?>
                            <div>
                                <strong><?= esc((string)($row['title'] ?? 'Halaman')); ?></strong>
                                <span><?= esc((string)($row['bucket']['label'] ?? 'Monitor')); ?> · Score <?= (int)($row['performance_score'] ?? 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="admin-card admin-growth-snapshot-report-card">
                    <span class="admin-badge">Conversion</span>
                    <h2>Peluang Konversi</h2>
                    <div class="admin-growth-snapshot-mini-table">
                        <?php foreach ((array)($snapshot['top_opportunities'] ?? []) as $row): ?>
                            <div>
                                <strong><?= esc((string)($row['page']['title'] ?? $row['title'] ?? 'Halaman')); ?></strong>
                                <span><?= esc((string)($row['category_label'] ?? 'Opportunity')); ?> · Priority <?= (int)($row['priority_score'] ?? 0); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
