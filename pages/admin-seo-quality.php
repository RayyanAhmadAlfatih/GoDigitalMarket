<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$message = trim((string)($_GET['message'] ?? ''));
$error = '';

function admin_seo_quality_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_seo_quality_logged_in()) {
        if (hash_equals((string)$adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'seo-quality']);
            }
            redirect_302('admin/seo-quality');
        }
        $error = 'Password admin salah.';
    }
}

$loggedIn = admin_seo_quality_logged_in();
$type = (string)($_GET['type'] ?? 'all');
if (!in_array($type, ['all', 'products', 'articles'], true)) {
    $type = 'all';
}
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'error', 'warning', 'info', 'ok'], true)) {
    $status = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [10, 20, 50, 100], true)) {
    $perPage = 20;
}
$page = max(1, (int)($_GET['page'] ?? 1));

$summary = $loggedIn ? seo_quality_summary($type) : ['counts' => [], 'items' => [], 'score_average' => 100, 'grade_average' => 'A'];
$items = $loggedIn ? (array)($summary['items'] ?? []) : [];

if ($status !== 'all') {
    $items = array_values(array_filter($items, static fn(array $item): bool => (string)($item['status'] ?? 'info') === $status));
}

if ($q !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);
    $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
        $catatanText = implode(' ', array_map(static fn(array $catatan): string => (string)($catatan['title'] ?? '') . ' ' . (string)($catatan['field'] ?? ''), (array)($item['issues'] ?? [])));
        $haystack = (function_exists('mb_strtolower') ? mb_strtolower(implode(' ', array_map('strval', [
            $item['type'] ?? '',
            $item['title'] ?? '',
            $item['slug'] ?? '',
            $item['source'] ?? '',
            $catatanText,
        ])) ) : strtolower(implode(' ', array_map('strval', [
            $item['type'] ?? '',
            $item['title'] ?? '',
            $item['slug'] ?? '',
            $item['source'] ?? '',
            $catatanText,
        ]))));
        return str_contains($haystack, $needle);
    }));
}

if ($loggedIn && (string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-quality-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['type', 'id', 'title', 'slug', 'source', 'score', 'grade', 'status', 'catatan_count', 'top_catatan', 'suggestion']);
    foreach ($items as $item) {
        $top = (array)($item['issues'][0] ?? []);
        fputcsv($out, [
            $item['type'] ?? '',
            $item['id'] ?? '',
            $item['title'] ?? '',
            $item['slug'] ?? '',
            $item['source'] ?? '',
            $item['score'] ?? '',
            $item['grade'] ?? '',
            $item['status'] ?? '',
            $item['issue_count'] ?? 0,
            $top['title'] ?? '',
            $top['suggestion'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$total = count($items);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pageItems = array_slice($items, $offset, $perPage);

function admin_seo_quality_url(array $overrides = []): string
{
    $query = array_merge([
        'type' => $_GET['type'] ?? 'all',
        'status' => $_GET['status'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'per_page' => $_GET['per_page'] ?? 20,
        'page' => $_GET['page'] ?? 1,
    ], $overrides);

    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/seo-quality' . ($query ? '?' . http_build_query($query) : ''));
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Pemeriksa SEO - ' . SITE_NAME,
    'description' => 'Audit SEO otomatis untuk produk dan artikel marketplace.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-quality-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Pemeriksa SEO</div>
                <h1>Pemeriksa SEO</h1>
                <p>Cek judul SEO, deskripsi, alt gambar, slug, isi konten, keyword, dan link internal agar halaman lebih siap tampil di Google.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-login-layout">
                    <div class="admin-login-copy">
                        <span class="admin-badge">Akses terbatas</span>
                        <h2>Masuk untuk audit SEO</h2>
                        <p>Dashboard ini membaca produk dan artikel lokal, lalu memberi warning otomatis tanpa API eksternal.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <?php $counts = (array)($summary['counts'] ?? []); ?>
                <div class="admin-grid admin-grid--stats admin-seo-metrics">
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Rata-rata</span><h2><?= (int)($summary['score_average'] ?? 100); ?>/100</h2><p>Grade <?= esc((string)($summary['grade_average'] ?? 'A')); ?></p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Perlu Dipoles</span><h2><?= (int)($counts['warning'] ?? 0); ?></h2><p>Konten perlu dipoles.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Prioritas</span><h2><?= (int)($counts['error'] ?? 0); ?></h2><p>Perlu dicek sebelum live.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Catatan</span><h2><?= (int)($counts['issues'] ?? 0); ?></h2><p>Total catatan SEO.</p></div>
                </div>

                <form method="get" action="<?= url('admin/seo-quality'); ?>" class="admin-card admin-report-filter admin-seo-filter">
                    <div class="admin-report-filter-grid">
                        <label>Tipe Konten
                            <select name="type">
                                <?php foreach (['all' => 'Semua', 'products' => 'Produk', 'articles' => 'Artikel'] as $value => $label): ?>
                                    <option value="<?= esc($value); ?>" <?= $type === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Status
                            <select name="status">
                                <?php foreach (['all' => 'Semua', 'error' => 'Prioritas', 'warning' => 'Perlu Dipoles', 'info' => 'Info', 'ok' => 'OK'] as $value => $label): ?>
                                    <option value="<?= esc($value); ?>" <?= $status === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Cari
                            <input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, slug, field, catatan...">
                        </label>
                        <label>Per Halaman
                            <select name="per_page">
                                <?php foreach ([10,20,50,100] as $option): ?>
                                    <option value="<?= (int)$option; ?>" <?= $perPage === $option ? 'selected' : ''; ?>><?= (int)$option; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="admin-report-filter-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan Filter</button>
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-quality'); ?>">Reset</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_quality_url(['export' => 'csv', 'page' => null])); ?>">Export CSV</a>
                    </div>
                </form>

                <div class="admin-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <h2>Daftar Catatan SEO</h2>
                            <p>Menampilkan <?= $total > 0 ? (int)($offset + 1) : 0; ?>-<?= (int)min($offset + $perPage, $total); ?> dari <?= (int)$total; ?> item.</p>
                        </div>
                        <div class="admin-toolbar__actions">
                            <a class="admin-btn admin-btn--soft" href="<?= url('admin/media-library?status=missing_alt'); ?>">Cek Alt Gambar</a>
                            <a class="admin-btn admin-btn--soft" href="<?= url('admin/media-library?status=large'); ?>">Cek Gambar Besar</a>
                        </div>
                    </div>

                    <?php if (!$pageItems): ?>
                        <div class="admin-empty admin-empty--compact">
                            <h2>Tidak ada data sesuai filter</h2>
                            <p>Coba reset filter atau pilih status lain.</p>
                        </div>
                    <?php else: ?>
                        <div class="admin-table-wrap admin-table-wrap--comfortable">
                            <table class="admin-table admin-seo-quality-table">
                                <thead><tr><th>Score</th><th>Konten</th><th>Status</th><th>Top Perlu Dipoles</th><th>Meta</th><th>Aksi</th></tr></thead>
                                <tbody>
                                    <?php foreach ($pageItems as $item): ?>
                                        <?php $topIssue = (array)($item['issues'][0] ?? []); ?>
                                        <tr>
                                            <td><strong class="admin-seo-score admin-seo-score--<?= esc((string)$item['status']); ?>"><?= (int)$item['score']; ?><span>/100</span></strong><br><small>Grade <?= esc((string)$item['grade']); ?></small></td>
                                            <td><strong><?= esc((string)$item['title']); ?></strong><br><small><?= esc((string)$item['type']); ?> · <?= esc((string)$item['source']); ?> · /<?= esc((string)$item['slug']); ?></small></td>
                                            <td><span class="<?= esc(seo_quality_issue_class((string)$item['status'])); ?>"><?= esc(seo_quality_issue_label((string)$item['status'])); ?></span><br><small><?= (int)$item['issue_count']; ?> catatan</small></td>
                                            <td>
                                                <?php if ($topIssue): ?>
                                                    <strong><?= esc((string)$topIssue['title']); ?></strong><br>
                                                    <small><?= esc((string)$topIssue['message']); ?></small>
                                                    <?php if (!empty($topIssue['suggestion'])): ?><em class="admin-seo-suggestion"><?= esc((string)$topIssue['suggestion']); ?></em><?php endif; ?>
                                                <?php else: ?>
                                                    <span class="admin-muted">Tidak ada catatan besar.</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small>Title: <?= (int)($item['meta']['meta_title_length'] ?? 0); ?> karakter<br>Description: <?= (int)($item['meta']['meta_description_length'] ?? 0); ?> karakter<br>Words: <?= (int)($item['meta']['body_words'] ?? 0); ?></small></td>
                                            <td>
                                                <div class="admin-row-actions">
                                                    <?php if ((string)($item['source'] ?? '') !== 'seed'): ?><a class="admin-btn admin-btn--primary" href="<?= esc((string)$item['edit_url']); ?>">Edit</a><?php endif; ?>
                                                    <a class="admin-btn admin-btn--soft" target="_blank" rel="noopener" href="<?= esc((string)$item['view_url']); ?>">Lihat</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav class="admin-pagination" aria-label="Pagination SEO quality">
                            <a class="admin-page-link <?= $page <= 1 ? 'is-disabled' : ''; ?>" href="<?= $page <= 1 ? '#' : admin_seo_quality_url(['page' => $page - 1]); ?>">‹ Prev</a>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a class="admin-page-link <?= $i === $page ? 'is-active' : ''; ?>" href="<?= admin_seo_quality_url(['page' => $i]); ?>"><?= (int)$i; ?></a>
                            <?php endfor; ?>
                            <a class="admin-page-link <?= $page >= $totalPages ? 'is-disabled' : ''; ?>" href="<?= $page >= $totalPages ? '#' : admin_seo_quality_url(['page' => $page + 1]); ?>">Next ›</a>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
