<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();
$report = function_exists('dynamic_v3_guard_report') ? dynamic_v3_guard_report(120) : ['rows' => [], 'counts' => [], 'health_score' => 0, 'guardrails' => []];
$rows = (array)($report['rows'] ?? []);
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'strong', 'ok', 'weak'], true)) {
    $status = 'all';
}
$type = (string)($_GET['type'] ?? 'all');
if (!in_array($type, ['all', 'Artikel', 'Produk/Layanan'], true)) {
    $type = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));

$rows = array_values(array_filter($rows, static function (array $row) use ($status, $type, $q): bool {
    if ($status !== 'all' && (string)($row['status'] ?? '') !== $status) {
        return false;
    }
    if ($type !== 'all' && (string)($row['type'] ?? '') !== $type) {
        return false;
    }
    if ($q !== '') {
        $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
        $haystack = implode(' ', [$row['title'] ?? '', $row['category'] ?? '', $row['note'] ?? '', $row['url'] ?? '']);
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
        return str_contains($haystack, $needle);
    }
    return true;
}));

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="dynamic-content-guard-' . date('Ymd-His') . '.json"');
    echo json_encode(['report' => $report, 'filtered_rows' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dynamic-content-guard-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['type', 'title', 'url', 'category', 'token_count', 'best_score', 'status', 'related_count', 'note']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['type'] ?? '',
            $row['title'] ?? '',
            $row['url'] ?? '',
            $row['category'] ?? '',
            $row['token_count'] ?? 0,
            $row['best_score'] ?? 0,
            $row['status'] ?? '',
            $row['related_count'] ?? 0,
            $row['note'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$baseUrl = static function (array $override = []) use ($status, $type, $q): string {
    $query = array_merge(['status' => $status, 'type' => $type, 'q' => $q], $override);
    return url('admin/dynamic-content-guard?' . http_build_query($query));
};

$statusLabel = static function (string $value): string {
    return match ($value) {
        'strong' => 'Kuat',
        'ok' => 'Aman',
        'weak' => 'Perlu Data',
        default => 'Semua',
    };
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Dynamic Content Guard - Admin',
    'description' => 'Audit relevansi dynamic content berdasarkan niche, kategori, tag, keyword, slug, judul, konten, produk, layanan, dan lokasi.',
    'robots' => 'noindex, nofollow',
]);

$pageTitle = 'Dynamic Content Guard';
$pageDescription = 'Memastikan dynamic content tidak random: rekomendasi artikel, produk, layanan, dan landing page harus sesuai konteks halaman dan niche bisnis.';

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-dynamic-guard-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>Dynamic Content Relevance Guard</h1>
                <p>Audit ini membaca produk, layanan, artikel, kategori, tag, keyword, slug, judul, isi konten, lokasi, tipe item, dan mode bisnis agar dynamic content yang tampil tetap relevan. Product Detail Merge v4 juga memastikan dynamic section dan schema disiapkan tanpa hardcode niche.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/content-performance')); ?>">Content Performance</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-link-health')); ?>">Internal Link</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/universal-seo')); ?>">Universal SEO</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <div class="admin-cta-result-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Guard Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($report['health_score'] ?? 0); ?>;">
                        <strong><?= (int)($report['health_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>Relevansi Dynamic Content</h2>
                    <p>Mode bisnis: <?= esc((string)($report['business']['label'] ?? 'Hybrid Growth Website')); ?>. Detail page memakai threshold ketat; product detail v4 juga memberi penalti jika tipe item berbeda dan kategorinya tidak cocok.</p>
                </article>
                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Konten Dicek</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($report['counts']['items'] ?? 0); ?></strong> total</span>
                        <span><strong><?= (int)($report['counts']['article_rows'] ?? 0); ?></strong> artikel</span>
                        <span><strong><?= (int)($report['counts']['product_rows'] ?? 0); ?></strong> produk</span>
                        <span><strong><?= (int)($report['counts']['empty_context'] ?? 0); ?></strong> minim konteks</span>
                    </div>
                    <p>Konten minim konteks biasanya butuh tag, focus keyword, kategori, excerpt, atau deskripsi yang lebih spesifik.</p>
                </article>
                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Status</span>
                    <h2><?= (int)($report['counts']['strong'] ?? 0); ?> kuat · <?= (int)($report['counts']['ok'] ?? 0); ?> aman · <?= (int)($report['counts']['weak'] ?? 0); ?> perlu data</h2>
                    <p>Homepage boleh lebih luas, tapi detail artikel/produk/LP tidak memakai fallback random kalau relevansinya rendah. Produk fisik, jasa, digital, course, menu, booking, dan custom order dibaca sebagai konteks berbeda.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Audit</span>
                        <h2>Cek konten yang rekomendasinya kuat atau perlu dilengkapi</h2>
                        <p>Gunakan audit ini sebelum migrasi WordPress besar agar artikel lama tidak tersambung ke produk/layanan yang tidak relevan.</p>
                    </div>
                </div>
                <form method="get" class="admin-filter-form">
                    <label><span>Status</span><select name="status">
                        <?php foreach (['all' => 'Semua', 'strong' => 'Kuat', 'ok' => 'Aman', 'weak' => 'Perlu Data'] as $key => $label): ?>
                            <option value="<?= esc((string)$key); ?>" <?= $status === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label><span>Tipe</span><select name="type">
                        <?php foreach (['all' => 'Semua', 'Artikel' => 'Artikel', 'Produk/Layanan' => 'Produk/Layanan'] as $key => $label): ?>
                            <option value="<?= esc((string)$key); ?>" <?= $type === (string)$key ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="kategori, keyword, judul..."></label>
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                </form>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Daftar Konten</span>
                        <h2><?= count($rows); ?> item tampil</h2>
                        <p>Skor terbaik dihitung dari relasi artikel ↔ artikel, artikel ↔ produk, produk ↔ produk, dan produk ↔ artikel.</p>
                    </div>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Konten</th><th>Kategori</th><th>Konteks</th><th>Skor</th><th>Status</th><th>Catatan</th></tr></thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><strong><?= esc((string)($row['title'] ?? 'Konten')); ?></strong><br><small><?= esc((string)($row['type'] ?? 'Konten')); ?> · <a href="<?= esc((string)($row['url'] ?? '#')); ?>" target="_blank" rel="noopener">Lihat</a></small></td>
                                <td><?= esc((string)($row['category'] ?? '-')); ?></td>
                                <td><?= (int)($row['token_count'] ?? 0); ?> token · <?= (int)($row['related_count'] ?? 0); ?> relasi</td>
                                <td><strong><?= (int)($row['best_score'] ?? 0); ?></strong></td>
                                <td><span class="dynamic-guard-status dynamic-guard-status--<?= esc((string)($row['status'] ?? 'weak')); ?>"><?= esc($statusLabel((string)($row['status'] ?? 'weak'))); ?></span></td>
                                <td><?= esc((string)($row['note'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$rows): ?>
                            <tr><td colspan="6">Belum ada data yang cocok dengan filter.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Guardrail</span>
                        <h2>Aturan dynamic content yang dipakai</h2>
                    </div>
                </div>
                <div class="admin-grid-2">
                    <?php foreach ((array)($report['guardrails'] ?? []) as $guardrail): ?>
                        <article class="admin-note-card"><p><?= esc((string)$guardrail); ?></p></article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
