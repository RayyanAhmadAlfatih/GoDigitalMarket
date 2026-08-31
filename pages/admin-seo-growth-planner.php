<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$view = (string)($_GET['view'] ?? 'links');
if (!in_array($view, ['links', 'ideas', 'clusters', 'sprint'], true)) {
    $view = 'links';
}

$priority = (string)($_GET['priority'] ?? 'all');
if (!in_array($priority, ['all', 'tinggi', 'sedang', 'rendah'], true)) {
    $priority = 'all';
}

$q = trim((string)($_GET['q'] ?? ''));
$planner = function_exists('seo_growth_planner_summary') ? seo_growth_planner_summary() : [];
$linkRecommendations = (array)($planner['link_recommendations'] ?? []);
$contentIdeas = (array)($planner['content_gap_ideas'] ?? []);
$clusters = (array)($planner['clusters'] ?? []);
$sprintPlan = (array)($planner['sprint_plan'] ?? []);
$summary = (array)($planner['summary'] ?? []);
$counts = (array)($summary['counts'] ?? []);

$matchesQuery = static function (array $row) use ($q): bool {
    if ($q === '') {
        return true;
    }
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $flatten = static function (mixed $value) use (&$flatten): string {
        if (is_array($value)) {
            return implode(' ', array_map($flatten, $value));
        }
        return (string)$value;
    };
    $haystack = $flatten($row);
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, $needle);
};

$matchesPriority = static function (array $row) use ($priority): bool {
    if ($priority === 'all') {
        return true;
    }
    $label = function_exists('mb_strtolower') ? mb_strtolower((string)($row['priority_label'] ?? ''), 'UTF-8') : strtolower((string)($row['priority_label'] ?? ''));
    return $label === $priority;
};

$linkRecommendations = array_values(array_filter($linkRecommendations, static fn(array $row): bool => $matchesQuery($row) && $matchesPriority($row)));
$contentIdeas = array_values(array_filter($contentIdeas, static fn(array $row): bool => $matchesQuery($row) && $matchesPriority($row)));
$clusters = array_values(array_filter($clusters, static fn(array $row): bool => $matchesQuery($row)));

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-growth-planner-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'planner' => $planner,
        'filtered' => [
            'view' => $view,
            'priority' => $priority,
            'q' => $q,
            'link_recommendations' => $linkRecommendations,
            'content_gap_ideas' => $contentIdeas,
            'clusters' => $clusters,
            'sprint_plan' => $sprintPlan,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-growth-planner-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    if ($view === 'ideas') {
        fputcsv($out, ['priority','idea_title','intent','format','target_title','target_type','target_url','keyword_seed','brief'], ',', '"', '\\', "\n");
        foreach ($contentIdeas as $idea) {
            fputcsv($out, [(int)($idea['priority_score'] ?? 0), $idea['idea_title'] ?? '', $idea['intent'] ?? '', $idea['format'] ?? '', $idea['target_title'] ?? '', $idea['target_type'] ?? '', $idea['target_url'] ?? '', implode(', ', (array)($idea['keyword_seed'] ?? [])), $idea['brief'] ?? ''], ',', '"', '\\', "\n");
        }
    } elseif ($view === 'clusters') {
        fputcsv($out, ['cluster','total_pages','money_pages','support_pages','score_average','keyword_seeds'], ',', '"', '\\', "\n");
        foreach ($clusters as $cluster) {
            fputcsv($out, [$cluster['cluster'] ?? '', $cluster['total_pages'] ?? 0, $cluster['money_pages'] ?? 0, $cluster['support_pages'] ?? 0, $cluster['score_average'] ?? 0, implode(', ', (array)($cluster['keyword_seeds'] ?? []))], ',', '"', '\\', "\n");
        }
    } elseif ($view === 'sprint') {
        fputcsv($out, ['week','focus','task'], ',', '"', '\\', "\n");
        foreach ($sprintPlan as $task) {
            fputcsv($out, [$task['week'] ?? '', $task['focus'] ?? '', $task['task'] ?? ''], ',', '"', '\\', "\n");
        }
    } else {
        fputcsv($out, ['priority','source_title','source_type','source_url','target_title','target_type','target_url','anchor','shared_tokens','reason'], ',', '"', '\\', "\n");
        foreach ($linkRecommendations as $row) {
            fputcsv($out, [(int)($row['priority_score'] ?? 0), $row['source_title'] ?? '', $row['source_type'] ?? '', $row['source_url'] ?? '', $row['target_title'] ?? '', $row['target_type'] ?? '', $row['target_url'] ?? '', $row['anchor'] ?? '', implode(', ', (array)($row['shared_tokens'] ?? [])), $row['reason'] ?? ''], ',', '"', '\\', "\n");
        }
    }
    fclose($out);
    exit;
}

function admin_seo_growth_planner_url(array $overrides = []): string
{
    $query = array_merge([
        'view' => $_GET['view'] ?? 'links',
        'priority' => $_GET['priority'] ?? 'all',
        'q' => $_GET['q'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/seo-growth-planner' . ($query ? '?' . http_build_query($query) : ''));
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'SEO Growth Planner - Admin',
    'description' => 'Peta cluster, internal link, ide konten, dan sprint SEO agar trafik lebih dekat ke penjualan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-growth-planner-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>SEO Growth Planner</h1>
                <p>Ubah hasil audit SEO jadi action plan: internal link, cluster konten, ide artikel, dan sprint mingguan yang nyambung ke conversion.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/universal-seo'); ?>">Universal SEO</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-content-planner'); ?>">Content Planner</a>
                <a class="admin-btn admin-btn--primary" href="<?= url('admin/landing-pages'); ?>">Buat Landing Page</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <div class="admin-grid admin-grid--stats admin-seo-planner-metrics">
                <div class="admin-card admin-report-metric"><span class="admin-badge">Money Page</span><h2><?= (int)($planner['money_page_count'] ?? 0); ?></h2><p>Produk, jasa, dan landing page yang perlu didorong trafiknya.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Support Page</span><h2><?= (int)($planner['support_page_count'] ?? 0); ?></h2><p>Artikel, portfolio, dan halaman pendukung untuk authority.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Internal Link</span><h2><?= count((array)($planner['link_recommendations'] ?? [])); ?></h2><p>Rekomendasi link dari konten pendukung ke halaman conversion.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Ide Konten</span><h2><?= count((array)($planner['content_gap_ideas'] ?? [])); ?></h2><p>Topik yang bisa dibuat untuk memperkuat SEO dan trust.</p></div>
            </div>

            <div class="admin-card admin-seo-planner-focus">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Growth Plan</span>
                        <h2>Rute Scale SEO 30 Hari</h2>
                        <p>Fokusnya bukan cuma naik traffic, tapi mengarahkan traffic ke produk, layanan, form, WhatsApp, dan checkout.</p>
                    </div>
                    <div class="admin-seo-planner-score">
                        <strong><?= (int)($summary['score_average'] ?? 100); ?>/100</strong>
                        <small>SEO Growth Score</small>
                    </div>
                </div>
                <div class="admin-seo-sprint-grid">
                    <?php foreach (array_slice($sprintPlan, 0, 4) as $task): ?>
                        <div class="admin-seo-sprint-card">
                            <span><?= esc((string)($task['week'] ?? 'Minggu')); ?></span>
                            <strong><?= esc((string)($task['focus'] ?? 'Fokus SEO')); ?></strong>
                            <p><?= esc((string)($task['task'] ?? 'Lanjutkan optimasi konten dan conversion.')); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="get" action="<?= url('admin/seo-growth-planner'); ?>" class="admin-card admin-report-filter admin-seo-planner-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Planner Filter</span>
                        <h3>Atur fokus growth</h3>
                    </div>
                    <p>Pilih tampilan planner, prioritas, atau cari halaman/topik tertentu.</p>
                </div>
                <div class="admin-report-filter-grid">
                    <label><span>Tampilan</span>
                        <select name="view">
                            <?php foreach (['links' => 'Internal Link', 'ideas' => 'Ide Konten', 'clusters' => 'Cluster Map', 'sprint' => 'Sprint 30 Hari'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $view === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Prioritas</span>
                        <select name="priority">
                            <?php foreach (['all' => 'Semua', 'tinggi' => 'Tinggi', 'sedang' => 'Sedang', 'rendah' => 'Rendah'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $priority === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari</span>
                        <input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, keyword, cluster, anchor...">
                    </label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-growth-planner'); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_growth_planner_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_growth_planner_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <?php if ($view === 'ideas'): ?>
                <div class="admin-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><h2>Ide Konten Pendukung</h2><p>Topik yang bisa dibuat agar halaman jualan punya support SEO dan trust lebih kuat.</p></div>
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/artikel'); ?>">Buka Artikel</a>
                    </div>
                    <div class="admin-seo-planner-list">
                        <?php foreach ($contentIdeas as $idea): ?>
                            <article class="admin-seo-planner-item">
                                <div>
                                    <span class="<?= esc(seo_growth_priority_class((int)($idea['priority_score'] ?? 0))); ?>"><?= esc(seo_growth_priority_label((int)($idea['priority_score'] ?? 0))); ?> · <?= (int)($idea['priority_score'] ?? 0); ?></span>
                                    <h3><?= esc((string)($idea['idea_title'] ?? 'Ide konten SEO')); ?></h3>
                                    <p><?= esc((string)($idea['brief'] ?? 'Buat konten pendukung dan arahkan ke halaman target.')); ?></p>
                                    <small>Intent: <?= esc((string)($idea['intent'] ?? '-')); ?> · Format: <?= esc((string)($idea['format'] ?? '-')); ?> · Seed: <?= esc(implode(', ', (array)($idea['keyword_seed'] ?? []))); ?></small>
                                </div>
                                <div class="admin-row-actions"><a class="admin-btn admin-btn--soft" href="<?= esc((string)($idea['target_url'] ?? '#')); ?>" target="_blank" rel="noopener">Target</a></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($view === 'clusters'): ?>
                <div class="admin-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><h2>Cluster Map</h2><p>Lihat kelompok topik yang sudah punya money page dan halaman pendukung.</p></div>
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-landings'); ?>">SEO Landing</a>
                    </div>
                    <div class="admin-seo-cluster-grid">
                        <?php foreach ($clusters as $cluster): ?>
                            <article class="admin-seo-cluster-card">
                                <div class="admin-seo-cluster-card__head">
                                    <span class="admin-badge"><?= esc((string)($cluster['cluster'] ?? 'cluster')); ?></span>
                                    <strong><?= (int)($cluster['score_average'] ?? 0); ?>/100</strong>
                                </div>
                                <h3><?= esc(ucwords((string)($cluster['cluster'] ?? 'Cluster'))); ?></h3>
                                <p><?= (int)($cluster['money_pages'] ?? 0); ?> money page · <?= (int)($cluster['support_pages'] ?? 0); ?> support page · <?= (int)($cluster['total_pages'] ?? 0); ?> total halaman.</p>
                                <small>Seed: <?= esc(implode(', ', (array)($cluster['keyword_seeds'] ?? []))); ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ($view === 'sprint'): ?>
                <div class="admin-card">
                    <div class="admin-form-head"><h2>Sprint 30 Hari</h2><p>Checklist ringkas agar SEO berjalan sebagai mesin growth, bukan sekadar audit.</p></div>
                    <div class="admin-seo-planner-list">
                        <?php foreach ($sprintPlan as $task): ?>
                            <article class="admin-seo-planner-item">
                                <div>
                                    <span class="admin-badge"><?= esc((string)($task['week'] ?? 'Minggu')); ?></span>
                                    <h3><?= esc((string)($task['focus'] ?? 'Fokus SEO')); ?></h3>
                                    <p><?= esc((string)($task['task'] ?? 'Lanjutkan optimasi konten dan conversion.')); ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><h2>Rekomendasi Internal Link</h2><p>Hubungkan artikel/portfolio/halaman pendukung ke produk, layanan, landing page, dan SEO landing.</p></div>
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/universal-seo?status=warning'); ?>">Lihat Warning SEO</a>
                    </div>
                    <div class="admin-table-wrap admin-table-wrap--comfortable">
                        <table class="admin-table admin-seo-link-table">
                            <thead><tr><th>Prioritas</th><th>Dari Halaman</th><th>Ke Target</th><th>Anchor</th><th>Alasan</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php foreach ($linkRecommendations as $row): ?>
                                    <tr>
                                        <td><span class="<?= esc(seo_growth_priority_class((int)($row['priority_score'] ?? 0))); ?>"><?= esc((string)($row['priority_label'] ?? 'Sedang')); ?></span><br><small><?= (int)($row['priority_score'] ?? 0); ?>/100</small></td>
                                        <td><strong><?= esc((string)($row['source_title'] ?? '-')); ?></strong><br><small><?= esc(seo_growth_intent_label((string)($row['source_type'] ?? ''))); ?></small></td>
                                        <td><strong><?= esc((string)($row['target_title'] ?? '-')); ?></strong><br><small><?= esc(seo_growth_intent_label((string)($row['target_type'] ?? ''))); ?></small></td>
                                        <td><code><?= esc((string)($row['anchor'] ?? '')); ?></code><br><small><?= esc(implode(', ', (array)($row['shared_tokens'] ?? []))); ?></small></td>
                                        <td><p><?= esc((string)($row['reason'] ?? 'Perkuat alur internal link.')); ?></p></td>
                                        <td><div class="admin-row-actions"><a class="admin-btn admin-btn--soft" href="<?= esc((string)($row['source_edit_url'] ?? '#')); ?>">Edit Sumber</a><a class="admin-btn admin-btn--primary" href="<?= esc((string)($row['target_url'] ?? '#')); ?>" target="_blank" rel="noopener">Target</a></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
