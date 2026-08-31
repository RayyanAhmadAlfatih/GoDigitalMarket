<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$error = '';
$message = trim((string)($_GET['message'] ?? ''));

function admin_seo_landings_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

if ((string)($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/seo-landings');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();

    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_seo_landings_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'seo-landings']);
            }
            redirect_302('admin/seo-landings');
        }
        $error = 'Password admin salah.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $key = (string)($_POST['key'] ?? '');
        $recordExists = false;
        foreach (seo_landing_registry(false) as $record) {
            if ((string)($record['key'] ?? '') === $key) {
                $recordExists = true;
                break;
            }
        }

        if (!$recordExists) {
            $error = 'Landing tidak ditemukan atau key tidak valid.';
        } elseif ($action === 'enable' || $action === 'disable') {
            $enabled = $action === 'enable';
            if (seo_landing_set_enabled($key, $enabled)) {
                redirect_302('admin/seo-landings?message=' . rawurlencode($enabled ? 'Landing berhasil diaktifkan.' : 'Landing berhasil dinonaktifkan.'));
            }
            $error = 'Gagal menyimpan pengaturan landing.';
        }
    }
}

$loggedIn = admin_seo_landings_logged_in();
$summary = $loggedIn ? seo_landing_summary() : ['counts' => [], 'items' => []];
$items = $loggedIn ? array_values((array)($summary['items'] ?? [])) : [];

$prefix = (string)($_GET['prefix'] ?? 'all');
if (!in_array($prefix, array_merge(['all'], seo_landing_allowed_prefixes()), true)) {
    $prefix = 'all';
}
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'indexable', 'enabled', 'disabled', 'thin'], true)) {
    $status = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [10, 20, 50, 100], true)) {
    $perPage = 20;
}
$page = max(1, (int)($_GET['page'] ?? 1));

if ($prefix !== 'all') {
    $items = array_values(array_filter($items, static fn(array $item): bool => (string)($item['prefix'] ?? '') === $prefix));
}

if ($status !== 'all') {
    $items = array_values(array_filter($items, static function (array $item) use ($status): bool {
        if ($status === 'indexable') {
            return (bool)($item['indexable'] ?? false);
        }
        if ($status === 'enabled') {
            return (bool)($item['enabled'] ?? false);
        }
        if ($status === 'disabled') {
            return !(bool)($item['enabled'] ?? false);
        }
        if ($status === 'thin') {
            return (int)($item['product_count'] ?? 0) <= 0;
        }
        return true;
    }));
}

if ($q !== '') {
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $items = array_values(array_filter($items, static function (array $item) use ($needle): bool {
        $haystack = implode(' ', array_map('strval', [
            $item['key'] ?? '',
            $item['prefix'] ?? '',
            $item['slug'] ?? '',
            $item['title'] ?? '',
            $item['description'] ?? '',
            $item['template'] ?? '',
            implode(' ', array_map('strval', (array)($item['filters'] ?? []))),
        ]));
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
        return str_contains($haystack, $needle);
    }));
}

if ($loggedIn && (string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-landings-v29-26-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['key', 'prefix', 'slug', 'url', 'title', 'template', 'enabled', 'indexable', 'product_count', 'filters']);
    foreach ($items as $item) {
        fputcsv($out, [
            $item['key'] ?? '',
            $item['prefix'] ?? '',
            $item['slug'] ?? '',
            $item['url'] ?? '',
            $item['title'] ?? '',
            $item['template'] ?? '',
            !empty($item['enabled']) ? 'yes' : 'no',
            !empty($item['indexable']) ? 'yes' : 'no',
            (int)($item['product_count'] ?? 0),
            json_encode($item['filters'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
    fclose($out);
    exit;
}

$total = count($items);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$pageItems = array_slice($items, ($page - 1) * $perPage, $perPage);

function admin_seo_landings_url(array $overrides = []): string
{
    $query = array_merge([
        'prefix' => $_GET['prefix'] ?? 'all',
        'status' => $_GET['status'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'per_page' => $_GET['per_page'] ?? 20,
        'page' => $_GET['page'] ?? 1,
    ], $overrides);

    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/seo-landings' . ($query ? '?' . http_build_query($query) : ''));
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'SEO Landing Page - ' . SITE_NAME,
    'description' => 'Kelola clean SEO landing URL yang valid, terkontrol, dan tidak duplikatif.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-landings-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">SEO Landing Page</div>
                <h1>SEO Landing Page</h1>
                <p>Kelola halaman SEO otomatis untuk produk, layanan, lokasi, dan katalog. Sitemap hanya mengambil halaman aktif yang punya konten terkait.</p>
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
                        <h2>Masuk untuk kelola SEO Landing</h2>
                        <p>Dashboard ini hanya menyimpan override aktif/nonaktif. Registry tetap dihasilkan dari produk yang valid agar tidak membuat halaman tipis massal.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-toolbar admin-page-actions">
                    <div>
                        <span class="admin-badge">Registry</span>
                        <h2>Action SEO Landing</h2>
                    </div>
                    <div class="admin-toolbar__actions">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_landings_url(['export' => 'csv', 'page' => null])); ?>">Export CSV</a>
                    </div>
                </div>
                <?php $counts = (array)($summary['counts'] ?? []); ?>
                <div class="admin-grid admin-grid--stats admin-seo-metrics">
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Total</span><h2><?= (int)($counts['total'] ?? 0); ?></h2><p>Halaman SEO yang tersedia.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Indexable</span><h2><?= (int)($counts['indexable'] ?? 0); ?></h2><p>Masuk sitemap jika aktif.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Disabled</span><h2><?= (int)($counts['disabled'] ?? 0); ?></h2><p>Dinonaktifkan manual.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Thin</span><h2><?= (int)($counts['thin'] ?? 0); ?></h2><p>Belum punya konten terkait.</p></div>
                </div>

                <form method="get" action="<?= url('admin/seo-landings'); ?>" class="admin-card admin-report-filter admin-seo-filter">
                    <div class="admin-report-filter-grid">
                        <label>Prefix
                            <select name="prefix">
                                <?php foreach (['all' => 'Semua', 'produk' => 'Produk', 'layanan' => 'Layanan', 'lokasi' => 'Lokasi', 'katalog' => 'Katalog'] as $value => $label): ?>
                                    <option value="<?= esc($value); ?>" <?= $prefix === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Status
                            <select name="status">
                                <?php foreach (['all' => 'Semua', 'indexable' => 'Indexable', 'enabled' => 'Aktif', 'disabled' => 'Nonaktif', 'thin' => 'Thin'] as $value => $label): ?>
                                    <option value="<?= esc($value); ?>" <?= $status === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Cari
                            <input type="search" name="q" value="<?= esc($q); ?>" placeholder="slug, judul, lokasi, breed...">
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
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-landings'); ?>">Reset</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_landings_url(['export' => 'csv', 'page' => null])); ?>">Export CSV</a>
                    </div>
                </form>

                <div class="admin-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <h2>Daftar Landing</h2>
                            <p><?= number_format($total); ?> landing cocok dengan filter. URL aktif + punya produk otomatis bisa masuk sitemap.</p>
                        </div>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-seo-landings-table">
                            <thead>
                                <tr>
                                    <th>Landing</th>
                                    <th>Prefix</th>
                                    <th>Produk</th>
                                    <th>Status</th>
                                    <th>Filter</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pageItems as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= esc((string)($item['title'] ?? '-')); ?></strong><br>
                                            <small><a href="<?= esc((string)($item['url'] ?? '#')); ?>" target="_blank" rel="noopener"><?= esc('/' . (string)($item['path'] ?? '')); ?></a></small><br>
                                            <small><?= esc(limit_chars((string)($item['description'] ?? ''), 120)); ?></small>
                                        </td>
                                        <td><span class="admin-badge"><?= esc((string)($item['prefix'] ?? '-')); ?></span><br><small><?= esc((string)($item['template'] ?? 'otomatis')); ?></small></td>
                                        <td><strong><?= (int)($item['product_count'] ?? 0); ?></strong></td>
                                        <td>
                                            <?php if (!empty($item['indexable'])): ?>
                                                <span class="admin-status-pill admin-status-pill--ok">Indexable</span>
                                            <?php elseif (empty($item['enabled'])): ?>
                                                <span class="admin-status-pill admin-status-pill--warning">Nonaktif</span>
                                            <?php else: ?>
                                                <span class="admin-status-pill admin-status-pill--info">Noindex</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php foreach ((array)($item['filters'] ?? []) as $key => $value): ?>
                                                <?php if (trim((string)$value) !== ''): ?>
                                                    <small><?= esc((string)$key); ?>: <?= esc((string)$value); ?></small><br>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </td>
                                        <td>
                                            <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="key" value="<?= esc((string)($item['key'] ?? '')); ?>">
                                                <?php if (!empty($item['enabled'])): ?>
                                                    <input type="hidden" name="action" value="disable">
                                                    <button class="admin-btn admin-btn--soft" type="submit">Nonaktifkan</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="action" value="enable">
                                                    <button class="admin-btn admin-btn--primary" type="submit">Aktifkan</button>
                                                <?php endif; ?>
                                                <a class="admin-btn admin-btn--soft" href="<?= esc((string)($item['url'] ?? '#')); ?>" target="_blank" rel="noopener">Preview</a>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$pageItems): ?>
                                    <tr><td colspan="6">Belum ada landing yang cocok.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="admin-pagination">
                            <?php if ($page > 1): ?><a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_landings_url(['page' => $page - 1])); ?>">Sebelumnya</a><?php endif; ?>
                            <span class="admin-muted">Halaman <?= (int)$page; ?> dari <?= (int)$totalPages; ?></span>
                            <?php if ($page < $totalPages): ?><a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_landings_url(['page' => $page + 1])); ?>">Berikutnya</a><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
