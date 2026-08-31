<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

seo_noindex();

$type = (string)($_GET['type'] ?? 'all');
$allowedTypes = ['all','product','service','article','landing_page','seo_landing','portfolio','static_page'];
if (!in_array($type, $allowedTypes, true)) { $type = 'all'; }

$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all','error','warning','info','ok'], true)) { $status = 'all'; }

$q = trim((string)($_GET['q'] ?? ''));
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [10,20,50,100], true)) { $perPage = 20; }
$page = max(1, (int)($_GET['page'] ?? 1));

$summary = universal_seo_summary($type);
$items = (array)($summary['items'] ?? []);

if ($status !== 'all') {
    $items = array_values(array_filter($items, static fn(array $item): bool => (string)($item['status'] ?? '') === $status));
}

if ($q !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
        $issueText = implode(' ', array_map(static fn(array $issue): string => (string)($issue['title'] ?? '') . ' ' . (string)($issue['field'] ?? ''), (array)($item['issues'] ?? [])));
        $haystack = implode(' ', array_map('strval', [
            $item['type'] ?? '', $item['title'] ?? '', $item['slug'] ?? '', $item['source'] ?? '', $item['schema_type'] ?? '', $issueText,
        ]));
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
        return str_contains($haystack, $needle);
    }));
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="universal-seo-' . date('Ymd-His') . '.json"');
    echo json_encode(['summary' => $summary, 'filtered_items' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="universal-seo-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['type','title','slug','url','source','indexable','schema_type','score','grade','status','issues','top_issue','suggestion'], ',', '"', '\\', "\n");
    foreach ($items as $item) {
        $top = (array)($item['issues'][0] ?? []);
        fputcsv($out, [
            $item['type'] ?? '', $item['title'] ?? '', $item['slug'] ?? '', $item['url'] ?? '', $item['source'] ?? '', !empty($item['indexable']) ? 'yes' : 'no',
            $item['schema_type'] ?? '', $item['score'] ?? '', $item['grade'] ?? '', $item['status'] ?? '', $item['issue_count'] ?? 0, $top['title'] ?? '', $top['suggestion'] ?? '',
        ], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

$total = count($items);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pageItems = array_slice($items, $offset, $perPage);
$counts = (array)($summary['counts'] ?? []);
$schemaCoverage = (int)(($counts['total'] ?? 0) > 0 ? round(((int)($counts['schema_ready'] ?? 0) / (int)$counts['total']) * 100) : 100);
$indexCoverage = (int)(($counts['total'] ?? 0) > 0 ? round(((int)($counts['indexable'] ?? 0) / (int)$counts['total']) * 100) : 100);
$business = function_exists('business_current_mode') ? business_current_mode() : [];
$businessSettings = function_exists('business_settings') ? business_settings() : [];
$opportunity = function_exists('universal_seo_opportunity_summary') ? universal_seo_opportunity_summary($items) : [];

function admin_universal_seo_url(array $overrides = []): string
{
    $query = array_merge([
        'type' => $_GET['type'] ?? 'all',
        'status' => $_GET['status'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'per_page' => $_GET['per_page'] ?? 20,
        'page' => $_GET['page'] ?? 1,
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/universal-seo' . ($query ? '?' . http_build_query($query) : ''));
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Universal SEO Engine - Admin',
    'description' => 'Audit SEO universal lintas produk, jasa, artikel, landing page, portfolio, dan halaman bisnis.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-universal-seo-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>Universal SEO Engine</h1>
                <p>Audit SEO lintas niche: produk, layanan, artikel, landing page, portfolio, SEO landing, schema, snippet, dan sitemap.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= url('sitemap.xml'); ?>" target="_blank" rel="noopener">Lihat Sitemap</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-growth-planner'); ?>">SEO Growth Planner</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-content-planner'); ?>">Content Planner</a>
                <a class="admin-btn admin-btn--primary" href="<?= url('admin/seo-quality'); ?>">Cek SEO Detail</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <div class="admin-grid admin-grid--stats admin-seo-universal-metrics">
                <div class="admin-card admin-report-metric"><span class="admin-badge">SEO Growth Score</span><h2><?= (int)($summary['score_average'] ?? 100); ?>/100</h2><p>Grade <?= esc((string)($summary['grade_average'] ?? 'A')); ?></p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Indexable</span><h2><?= $indexCoverage; ?>%</h2><p><?= (int)($counts['indexable'] ?? 0); ?> dari <?= (int)($counts['total'] ?? 0); ?> halaman siap index.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Schema Ready</span><h2><?= $schemaCoverage; ?>%</h2><p><?= (int)($counts['schema_ready'] ?? 0); ?> halaman punya tipe schema.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Prioritas</span><h2><?= (int)($counts['error'] ?? 0); ?></h2><p><?= (int)($counts['warning'] ?? 0); ?> halaman masih perlu dipoles.</p></div>
            </div>

            <div class="admin-grid admin-grid--two admin-seo-universal-top">
                <div class="admin-card">
                    <div class="admin-form-head"><span class="admin-badge">Action Plan</span><h2>Rekomendasi Scale SEO</h2><p>Fokuskan perbaikan yang paling berdampak ke trust, trafik, dan conversion.</p></div>
                    <div class="admin-insight-list">
                        <?php foreach ((array)($summary['action_plan'] ?? []) as $plan): ?>
                            <div class="admin-insight-item"><strong>•</strong><span><?= esc((string)$plan); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="admin-card">
                    <div class="admin-form-head"><span class="admin-badge">Business Schema</span><h2>Profil Schema Website</h2><p>Mode: <?= esc((string)($business['label'] ?? 'Hybrid Growth Website')); ?> · Schema: <?= esc((string)($businessSettings['schema_profile'] ?? 'LocalBusiness')); ?></p></div>
                    <div class="admin-seo-schema-grid">
                        <?php foreach (['Product'=>'Produk fisik/digital', 'Service'=>'Jasa/layanan', 'Article'=>'Artikel SEO', 'Person'=>'Personal branding', 'LocalBusiness'=>'Bisnis lokal', 'CollectionPage'=>'Kategori/portfolio'] as $schema => $label): ?>
                            <span><strong><?= esc($schema); ?></strong><small><?= esc($label); ?></small></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="admin-card admin-seo-opportunity-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">SEO Opportunity</span>
                        <h2>Peta Peluang SEO</h2>
                        <p><?= esc((string)($opportunity['recommended_focus'] ?? 'Lanjutkan audit dan penguatan konten SEO.')); ?></p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-landings'); ?>">Buka SEO Landing</a>
                </div>
                <div class="admin-seo-opportunity-grid">
                    <div class="admin-seo-opportunity-box">
                        <strong>Quick Win</strong>
                        <span><?= count((array)($opportunity['quick_wins'] ?? [])); ?></span>
                        <small>Halaman yang sudah dekat grade A dan paling cepat dipoles.</small>
                    </div>
                    <div class="admin-seo-opportunity-box">
                        <strong>Konten Tipis</strong>
                        <span><?= count((array)($opportunity['content_gaps'] ?? [])); ?></span>
                        <small>Butuh tambahan manfaat, FAQ, bukti, atau detail penawaran.</small>
                    </div>
                    <div class="admin-seo-opportunity-box">
                        <strong>Internal Link</strong>
                        <span><?= count((array)($opportunity['internal_link_targets'] ?? [])); ?></span>
                        <small>Perlu diarahkan ke produk, form, landing page, atau artikel pendukung.</small>
                    </div>
                    <div class="admin-seo-opportunity-box admin-seo-opportunity-box--wide">
                        <strong>Catatan Teratas</strong>
                        <?php foreach (array_slice((array)($opportunity['top_issues'] ?? []), 0, 3) as $issue): ?>
                            <small><b><?= (int)($issue['count'] ?? 0); ?>×</b> <?= esc((string)($issue['title'] ?? 'Catatan SEO')); ?></small>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <form method="get" action="<?= url('admin/universal-seo'); ?>" class="admin-card admin-report-filter admin-universal-seo-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Filter Audit</span>
                        <h3>Rapikan fokus pengecekan</h3>
                    </div>
                    <p>Pilih tipe konten, status SEO, atau cari slug/schema/catatan tertentu.</p>
                </div>
                <div class="admin-report-filter-grid">
                    <label><span>Tipe Konten</span>
                        <select name="type">
                            <?php foreach (['all'=>'Semua','product'=>'Produk','service'=>'Layanan','article'=>'Artikel','landing_page'=>'Landing Page','seo_landing'=>'SEO Landing','portfolio'=>'Portfolio','static_page'=>'Halaman Statis'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $type === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Status</span>
                        <select name="status">
                            <?php foreach (['all'=>'Semua','error'=>'Prioritas','warning'=>'Perlu Dipoles','info'=>'Info','ok'=>'Siap Index'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $status === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari</span>
                        <input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, slug, schema, catatan...">
                    </label>
                    <label><span>Per Halaman</span>
                        <select name="per_page">
                            <?php foreach ([10,20,50,100] as $option): ?>
                                <option value="<?= (int)$option; ?>" <?= $perPage === $option ? 'selected' : ''; ?>><?= (int)$option; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan Filter</button>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/universal-seo'); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_universal_seo_url(['export'=>'csv','page'=>null])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_universal_seo_url(['export'=>'json','page'=>null])); ?>">Export JSON</a>
                </div>
            </form>

            <div class="admin-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <h2>Audit Halaman Universal</h2>
                        <p>Menampilkan <?= $total > 0 ? (int)($offset + 1) : 0; ?>-<?= (int)min($offset + $perPage, $total); ?> dari <?= (int)$total; ?> item.</p>
                    </div>
                    <div class="admin-toolbar__actions">
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/media-library?status=missing_alt'); ?>">Cek Alt Gambar</a>
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-landings'); ?>">SEO Landing</a>
                    </div>
                </div>

                <?php if (!$pageItems): ?>
                    <div class="admin-empty admin-empty--compact"><h2>Tidak ada data sesuai filter</h2><p>Coba reset filter atau pilih status lain.</p></div>
                <?php else: ?>
                    <div class="admin-table-wrap admin-table-wrap--comfortable">
                        <table class="admin-table admin-universal-seo-table">
                            <thead><tr><th>Score</th><th>Halaman</th><th>Snippet Preview</th><th>Schema</th><th>Top Catatan</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php foreach ($pageItems as $item): ?>
                                    <?php $topIssue = (array)($item['issues'][0] ?? []); $snippetTitle = trim((string)($item['meta_title'] ?? '')) ?: (string)($item['title'] ?? ''); $snippetDescription = trim((string)($item['meta_description'] ?? '')) ?: universal_seo_text((string)($item['body'] ?? '')); ?>
                                    <tr>
                                        <td><strong class="admin-seo-score admin-seo-score--<?= esc((string)$item['status']); ?>"><?= (int)$item['score']; ?><span>/100</span></strong><br><small>Grade <?= esc((string)$item['grade']); ?></small></td>
                                        <td><strong><?= esc((string)$item['title']); ?></strong><br><small><?= esc(universal_seo_type_label((string)$item['type'])); ?> · <?= esc((string)$item['source']); ?> · <?= !empty($item['indexable']) ? 'indexable' : 'draft/noindex'; ?></small><br><small><?= esc((string)$item['slug']); ?></small></td>
                                        <td><div class="admin-serp-preview"><strong><?= esc($snippetTitle); ?></strong><span><?= esc((string)($item['url'] ?? '')); ?></span><p><?= esc(function_exists('mb_substr') ? mb_substr($snippetDescription, 0, 165, 'UTF-8') : substr($snippetDescription, 0, 165)); ?></p></div></td>
                                        <td><span class="admin-badge"><?= esc((string)($item['schema_type'] ?? '-')); ?></span><br><small>Title <?= (int)($item['meta']['meta_title_length'] ?? 0); ?> · Desc <?= (int)($item['meta']['meta_description_length'] ?? 0); ?> · Words <?= (int)($item['meta']['body_words'] ?? 0); ?></small></td>
                                        <td><span class="<?= esc(universal_seo_status_class((string)$item['status'])); ?>"><?= esc(universal_seo_status_label((string)$item['status'])); ?></span><br><?php if ($topIssue): ?><strong><?= esc((string)$topIssue['title']); ?></strong><br><small><?= esc((string)$topIssue['suggestion']); ?></small><?php else: ?><small>Siap untuk dilanjutkan.</small><?php endif; ?></td>
                                        <td><div class="admin-row-actions"><a class="admin-btn admin-btn--soft" href="<?= esc((string)$item['url']); ?>" target="_blank" rel="noopener">Lihat</a><a class="admin-btn admin-btn--primary" href="<?= esc((string)$item['edit_url']); ?>">Edit</a></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($totalPages > 1): ?>
                    <nav class="admin-pagination" aria-label="Pagination Universal SEO">
                        <a class="admin-page-link <?= $page <= 1 ? 'is-disabled' : ''; ?>" href="<?= $page <= 1 ? '#' : admin_universal_seo_url(['page'=>$page-1]); ?>">‹ Prev</a>
                        <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?><a class="admin-page-link <?= $i===$page?'is-active':''; ?>" href="<?= admin_universal_seo_url(['page'=>$i]); ?>"><?= (int)$i; ?></a><?php endfor; ?>
                        <a class="admin-page-link <?= $page >= $totalPages ? 'is-disabled' : ''; ?>" href="<?= $page >= $totalPages ? '#' : admin_universal_seo_url(['page'=>$page+1]); ?>">Next ›</a>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
