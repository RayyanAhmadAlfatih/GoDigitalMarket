<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$view = (string)($_GET['view'] ?? 'recommendations');
if (!in_array($view, ['recommendations', 'links', 'targets'], true)) {
    $view = 'recommendations';
}

$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'ok', 'broken'], true)) {
    $status = 'all';
}

$type = (string)($_GET['type'] ?? 'all');
$allowedTypes = ['all', 'product', 'service', 'article', 'landing_page', 'seo_landing', 'portfolio', 'static_page'];
if (!in_array($type, $allowedTypes, true)) {
    $type = 'all';
}

$q = trim((string)($_GET['q'] ?? ''));
$summary = function_exists('seo_link_health_summary') ? seo_link_health_summary() : ['metrics' => [], 'links' => [], 'low_targets' => [], 'recommendations' => [], 'action_plan' => []];
$metrics = (array)($summary['metrics'] ?? []);
$links = (array)($summary['links'] ?? []);
$targets = (array)($summary['low_targets'] ?? []);
$recommendations = (array)($summary['recommendations'] ?? []);

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
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($flatten($row), 'UTF-8') : strtolower($flatten($row));
    return str_contains($haystack, $needle);
};

$links = array_values(array_filter($links, static function (array $row) use ($status, $type, $matchesQuery): bool {
    if ($status !== 'all' && (string)($row['status'] ?? '') !== $status) {
        return false;
    }
    if ($type !== 'all' && (string)($row['source_type'] ?? '') !== $type && (string)($row['target_type'] ?? '') !== $type) {
        return false;
    }
    return $matchesQuery($row);
}));

$targets = array_values(array_filter($targets, static function (array $row) use ($type, $matchesQuery): bool {
    if ($type !== 'all' && (string)($row['type'] ?? '') !== $type) {
        return false;
    }
    return $matchesQuery($row);
}));

$recommendations = array_values(array_filter($recommendations, static function (array $row) use ($type, $matchesQuery): bool {
    if ($type !== 'all' && (string)($row['source_type'] ?? '') !== $type && (string)($row['target_type'] ?? '') !== $type) {
        return false;
    }
    return $matchesQuery($row);
}));

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-link-health-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'summary' => $summary,
        'filtered' => [
            'view' => $view,
            'status' => $status,
            'type' => $type,
            'q' => $q,
            'links' => $links,
            'targets' => $targets,
            'recommendations' => $recommendations,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-link-health-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    if ($view === 'links') {
        fputcsv($out, ['status','source_type','source_title','source_url','anchor','target_path','target_title','target_type'], ',', '"', '\\', "\n");
        foreach ($links as $row) {
            fputcsv($out, [$row['status'] ?? '', $row['source_type_label'] ?? '', $row['source_title'] ?? '', $row['source_url'] ?? '', $row['anchor'] ?? '', $row['path'] ?? '', $row['target_title'] ?? '', $row['target_type_label'] ?? ''], ',', '"', '\\', "\n");
        }
    } elseif ($view === 'targets') {
        fputcsv($out, ['type','title','url','incoming_count','seo_score','recommended_action'], ',', '"', '\\', "\n");
        foreach ($targets as $row) {
            fputcsv($out, [function_exists('universal_seo_type_label') ? universal_seo_type_label((string)($row['type'] ?? '')) : ($row['type'] ?? ''), $row['title'] ?? '', $row['url'] ?? '', $row['incoming_count'] ?? 0, $row['score'] ?? 0, $row['recommended_action'] ?? ''], ',', '"', '\\', "\n");
        }
    } else {
        fputcsv($out, ['priority','source_title','source_type','target_title','target_type','anchor','reason'], ',', '"', '\\', "\n");
        foreach ($recommendations as $row) {
            fputcsv($out, [$row['priority_score'] ?? 0, $row['source_title'] ?? '', $row['source_type'] ?? '', $row['target_title'] ?? '', $row['target_type'] ?? '', $row['anchor'] ?? '', $row['reason'] ?? ''], ',', '"', '\\', "\n");
        }
    }
    fclose($out);
    exit;
}

function admin_seo_link_health_url(array $overrides = []): string
{
    $query = array_merge([
        'view' => $_GET['view'] ?? 'recommendations',
        'status' => $_GET['status'] ?? 'all',
        'type' => $_GET['type'] ?? 'all',
        'q' => $_GET['q'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/seo-link-health' . ($query ? '?' . http_build_query($query) : ''));
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Internal Link Manager - Admin',
    'description' => 'Peta internal link, link rusak, dan rekomendasi anchor untuk memperkuat money page.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-link-health-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>Internal Link Manager</h1>
                <p>Kelola struktur link internal agar artikel, portfolio, dan landing page benar-benar mendorong produk, layanan, checkout, form, dan halaman penawaran utama.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-growth-planner'); ?>">Growth Planner</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-content-planner'); ?>">Content Planner</a>
                <a class="admin-btn admin-btn--primary" href="<?= url('admin/universal-seo'); ?>">Audit SEO</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <div class="admin-report-grid admin-report-grid--four">
                <div class="admin-card admin-report-metric"><span class="admin-badge">Link Health</span><h2><?= (int)($metrics['health_score'] ?? 100); ?>/100</h2><p>Skor kesehatan link internal dari link aman, link rusak, dan halaman target yang minim dorongan.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Internal Link</span><h2><?= (int)($metrics['internal_links'] ?? 0); ?></h2><p>Total link internal yang ditemukan dari konten artikel, produk, jasa, dan landing page.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Perlu Fix</span><h2><?= (int)($metrics['broken_links'] ?? 0); ?></h2><p>Link internal yang belum cocok dengan halaman indexable di inventory SEO.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Money Page</span><h2><?= (int)($metrics['low_targets'] ?? 0); ?></h2><p>Halaman produk/jasa/landing yang masih butuh tambahan incoming link.</p></div>
            </div>

            <div class="admin-card admin-seo-link-action-plan">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Action Plan</span>
                        <h2>Prioritas Link Internal Minggu Ini</h2>
                        <p>Mulai dari fix link rusak, lalu sambungkan halaman pendukung ke money page agar ranking dan conversion lebih kebaca arahnya.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-execution-board'); ?>">Buka Execution Board</a>
                </div>
                <div class="admin-seo-link-plan-list">
                    <?php foreach ((array)($summary['action_plan'] ?? []) as $plan): ?>
                        <div><span>✓</span><p><?= esc((string)$plan); ?></p></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="get" action="<?= url('admin/seo-link-health'); ?>" class="admin-card admin-report-filter admin-seo-link-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Link Filter</span>
                        <h3>Atur peta link internal</h3>
                    </div>
                    <p>Pilih mode rekomendasi, link health, atau money page yang masih butuh dorongan internal link.</p>
                </div>
                <div class="admin-report-filter-grid admin-report-filter-grid--wide">
                    <label><span>Tampilan</span>
                        <select name="view">
                            <?php foreach (['recommendations' => 'Rekomendasi Link', 'links' => 'Health Check Link', 'targets' => 'Money Page Minim Link'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $view === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Status Link</span>
                        <select name="status">
                            <option value="all">Semua</option>
                            <option value="ok" <?= $status === 'ok' ? 'selected' : ''; ?>>Aman</option>
                            <option value="broken" <?= $status === 'broken' ? 'selected' : ''; ?>>Perlu Fix</option>
                        </select>
                    </label>
                    <label><span>Tipe Konten</span>
                        <select name="type">
                            <?php foreach ($allowedTypes as $value): ?>
                                <option value="<?= esc($value); ?>" <?= $type === $value ? 'selected' : ''; ?>><?= esc($value === 'all' ? 'Semua' : (function_exists('universal_seo_type_label') ? universal_seo_type_label($value) : $value)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, anchor, url, target..."></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-link-health'); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_link_health_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_link_health_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <?php if ($view === 'links'): ?>
                <div class="admin-card admin-table-card">
                    <div class="admin-form-head admin-form-head--split"><div><h2>Health Check Link Internal</h2><p><?= count($links); ?> link sesuai filter.</p></div><a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_link_health_url(['status' => 'broken'])); ?>">Lihat Perlu Fix</a></div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-seo-link-table">
                            <thead><tr><th>Status</th><th>Sumber</th><th>Anchor</th><th>Target</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($links, 0, 80) as $row): ?>
                                <tr>
                                    <td><span class="<?= esc(seo_link_health_status_class((string)($row['status'] ?? 'ok'))); ?>"><?= esc(seo_link_health_status_label((string)($row['status'] ?? 'ok'))); ?></span></td>
                                    <td><strong><?= esc((string)($row['source_title'] ?? 'Halaman')); ?></strong><small><?= esc((string)($row['source_type_label'] ?? 'Halaman')); ?> · <?= esc(seo_link_health_url_label((string)($row['source_url'] ?? ''))); ?></small></td>
                                    <td><strong><?= esc((string)($row['anchor'] ?? '-')); ?></strong><small><?= esc((string)($row['href'] ?? '')); ?></small></td>
                                    <td><strong><?= esc((string)($row['target_title'] ?? ($row['path'] ?? '-'))); ?></strong><small><?= esc((string)($row['target_type_label'] ?? 'Belum cocok dengan inventory SEO')); ?></small></td>
                                    <td><a class="admin-btn admin-btn--soft" href="<?= esc((string)($row['source_edit_url'] ?? '#')); ?>">Edit Sumber</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$links): ?><tr><td colspan="5"><div class="admin-empty-state"><h2>Belum ada link sesuai filter.</h2><p>Coba reset filter atau buka rekomendasi link.</p></div></td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($view === 'targets'): ?>
                <div class="admin-grid admin-grid--two admin-seo-link-targets">
                    <?php foreach ($targets as $target): ?>
                        <article class="admin-card admin-seo-link-target-card">
                            <div class="admin-form-head admin-form-head--split">
                                <div>
                                    <span class="admin-badge"><?= esc(function_exists('universal_seo_type_label') ? universal_seo_type_label((string)($target['type'] ?? '')) : (string)($target['type'] ?? 'Halaman')); ?></span>
                                    <h2><?= esc((string)($target['title'] ?? 'Money Page')); ?></h2>
                                    <p><?= esc((string)($target['recommended_action'] ?? 'Tambahkan internal link dari konten pendukung.')); ?></p>
                                </div>
                                <strong class="admin-seo-link-count"><?= (int)($target['incoming_count'] ?? 0); ?></strong>
                            </div>
                            <div class="admin-seo-link-target-meta"><span>SEO <?= (int)($target['score'] ?? 0); ?>/100</span><span><?= esc(seo_link_health_url_label((string)($target['url'] ?? ''))); ?></span></div>
                            <div class="admin-seo-draft-actions"><a class="admin-btn admin-btn--soft" href="<?= esc((string)($target['edit_url'] ?? '#')); ?>">Edit Halaman</a><a class="admin-btn admin-btn--primary" href="<?= url('admin/seo-content-planner'); ?>">Buat Konten Pendukung</a></div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$targets): ?><div class="admin-card admin-empty-state"><h2>Money page sudah cukup aman.</h2><p>Tidak ada halaman prioritas yang minim incoming link sesuai filter.</p></div><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="admin-grid admin-grid--two admin-seo-link-recommendations">
                    <?php foreach ($recommendations as $row): ?>
                        <article class="admin-card admin-seo-link-rec-card">
                            <div class="admin-form-head admin-form-head--split">
                                <div>
                                    <span class="<?= esc(function_exists('seo_growth_priority_class') ? seo_growth_priority_class((int)($row['priority_score'] ?? 0)) : 'admin-status-pill admin-status-pill--info'); ?>"><?= esc((string)($row['priority_label'] ?? 'Prioritas')); ?> · <?= (int)($row['priority_score'] ?? 0); ?>/100</span>
                                    <h2><?= esc((string)($row['source_title'] ?? 'Halaman sumber')); ?></h2>
                                    <p>Link-kan ke: <strong><?= esc((string)($row['target_title'] ?? 'Target')); ?></strong></p>
                                </div>
                                <a class="admin-btn admin-btn--soft" href="<?= esc((string)($row['source_edit_url'] ?? '#')); ?>">Edit Sumber</a>
                            </div>
                            <div class="admin-seo-link-anchor-box"><span>Anchor disarankan</span><strong><?= esc((string)($row['anchor'] ?? 'halaman terkait')); ?></strong></div>
                            <p><?= esc((string)($row['reason'] ?? 'Topik halaman saling relevan dan bisa memperkuat authority.')); ?></p>
                            <div class="admin-seo-link-token-row">
                                <?php foreach ((array)($row['shared_tokens'] ?? []) as $token): ?><span><?= esc((string)$token); ?></span><?php endforeach; ?>
                            </div>
                            <div class="admin-seo-draft-actions"><a class="admin-btn admin-btn--soft" href="<?= esc((string)($row['target_edit_url'] ?? '#')); ?>">Edit Target</a><a class="admin-btn admin-btn--primary" href="<?= url('admin/seo-execution-board'); ?>">Masukkan Eksekusi</a></div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$recommendations): ?><div class="admin-card admin-empty-state"><h2>Belum ada rekomendasi sesuai filter.</h2><p>Coba reset filter atau perbanyak konten pendukung di Content Planner.</p></div><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
