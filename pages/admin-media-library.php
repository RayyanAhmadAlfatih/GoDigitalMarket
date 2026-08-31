<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$error = '';
$message = trim((string)($_GET['message'] ?? ''));

function admin_media_library_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (!admin_media_library_logged_in()) {
        if (hash_equals((string)$adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'media-library']);
            }
            redirect_302('admin/media-library');
        }
        $error = 'Password admin salah.';
    } elseif ((string)($_POST['media_action'] ?? '') === 'apply_suggested_alt') {
        $applyItems = media_library_scan(['status' => 'missing_alt', 'issue' => 'missing_alt']);
        $result = media_library_apply_suggested_alt($applyItems, 200);
        $messageText = (int)($result['total'] ?? 0) > 0
            ? 'Alt suggestion diterapkan: ' . (int)$result['total'] . ' field diperbarui.'
            : 'Belum ada alt kosong yang bisa diperbarui otomatis.';
        redirect_302('admin/media-library?message=' . rawurlencode($messageText));
    }
}

$loggedIn = admin_media_library_logged_in();
$status = (string)($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'used', 'unused', 'large', 'missing_alt', 'ok'], true)) {
    $status = 'all';
}
$issue = (string)($_GET['issue'] ?? 'all');
if (!in_array($issue, ['all', 'missing_alt', 'large', 'unused', 'filename', 'not_webp', 'low_score'], true)) {
    $issue = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$root = (string)($_GET['root'] ?? 'all');
if (!in_array($root, ['all', 'uploads', 'images'], true)) {
    $root = 'all';
}
$roots = match ($root) {
    'uploads' => ['assets/uploads'],
    'images' => ['assets/images'],
    default => ['assets/uploads', 'assets/images'],
};
$perPage = (int)($_GET['per_page'] ?? 24);
if (!in_array($perPage, [12, 24, 48, 96], true)) {
    $perPage = 24;
}
$page = max(1, (int)($_GET['page'] ?? 1));

$summary = $loggedIn ? media_library_summary() : [];
$items = $loggedIn ? media_library_scan(['roots' => $roots, 'status' => $status, 'issue' => $issue, 'q' => $q]) : [];

if ($loggedIn && (string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="media-asset-seo-manager-v29-49-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['file', 'url', 'size', 'width', 'height', 'status', 'seo_score', 'used', 'missing_alt_count', 'alt_suggestion', 'filename_suggestion', 'webp_suggestion', 'references', 'recommendations']);
    foreach ($items as $item) {
        fputcsv($out, [
            $item['relative'] ?? '',
            $item['url'] ?? '',
            $item['size_label'] ?? '',
            $item['width'] ?? '',
            $item['height'] ?? '',
            $item['status'] ?? '',
            $item['seo_score'] ?? 0,
            !empty($item['used']) ? 'yes' : 'no',
            $item['missing_alt_count'] ?? 0,
            $item['alt_suggestion'] ?? '',
            $item['filename_suggestion'] ?? '',
            $item['webp_suggestion'] ?? '',
            implode(' | ', array_map(static fn(array $ref): string => (string)($ref['type'] ?? '') . ':' . (string)($ref['title'] ?? ''), (array)($item['references'] ?? []))),
            implode(' | ', (array)($item['recommendations'] ?? [])),
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

function admin_media_library_url(array $overrides = []): string
{
    $query = array_merge([
        'status' => $_GET['status'] ?? 'all',
        'issue' => $_GET['issue'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'root' => $_GET['root'] ?? 'all',
        'per_page' => $_GET['per_page'] ?? 24,
        'page' => $_GET['page'] ?? 1,
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/media-library' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_media_status_label(string $status): string
{
    return match ($status) {
        'missing_alt' => 'Alt Kosong',
        'large' => 'Besar',
        'unused' => 'Belum Dipakai',
        'used' => 'Dipakai',
        'ok' => 'OK',
        default => 'Semua',
    };
}

function admin_media_status_class(string $status): string
{
    return match ($status) {
        'missing_alt' => 'warning',
        'large' => 'warning',
        'unused' => 'info',
        'ok', 'used' => 'ok',
        default => 'info',
    };
}

function admin_media_score_class(int $score): string
{
    if ($score >= 86) {
        return 'ok';
    }

    // Media score badge follows dashboard theme color so low-score asset notes
    // do not look like brown/orange legacy warning pills in the UMKM admin UI.
    return 'warning';
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Media & Gambar SEO - ' . SITE_NAME,
    'description' => 'Kelola, audit, dan optimasi SEO asset gambar produk, artikel, dan landing page.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-media-shell admin-media-seo-manager-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Media & Gambar SEO</div>
                <h1>Media & Gambar SEO</h1>
                <p>Kelola gambar website, cek kualitas gambar, alt text, nama file SEO, dan kesiapan WebP tanpa mengganti file lama secara paksa.</p>
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
                        <h2>Masuk untuk melihat Media & Gambar SEO</h2>
                        <p>Dashboard ini membaca file gambar lokal dari folder assets dan memetakan penggunaannya ke produk, artikel, dan landing page.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-grid admin-grid--stats admin-media-metrics admin-media-metrics--template">
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Asset Score</span><h2><?= (int)($summary['avg_score'] ?? 0); ?>%</h2><p>Rata-rata health asset.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Total File</span><h2><?= (int)($summary['total'] ?? 0); ?></h2><p><?= esc((string)($summary['total_size_label'] ?? '0 B')); ?></p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Alt Kosong</span><h2><?= (int)($summary['missing_alt'] ?? 0); ?></h2><p>Produk/artikel/LP perlu alt.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Gambar Besar</span><h2><?= (int)($summary['large'] ?? 0); ?></h2><p>Perlu kompres/WebP.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Filename Issue</span><h2><?= (int)($summary['filename_issue'] ?? 0); ?></h2><p>Nama file kurang SEO.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">LP Asset</span><h2><?= (int)($summary['landing_page_refs'] ?? 0); ?></h2><p>Terpakai di landing page.</p></div>
                </div>

                <div class="admin-card admin-media-action-center">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Smart Asset Action</span>
                            <h2>Optimasi Alt Text Lokal</h2>
                            <p>Gunakan saran alt otomatis untuk field gambar yang masih kosong. Sistem hanya mengisi field alt, tidak rename file dan tidak menghapus asset.</p>
                        </div>
                        <form method="post" onsubmit="return confirm('Terapkan saran alt text ke field yang masih kosong? File gambar tidak akan diubah.');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="media_action" value="apply_suggested_alt">
                            <button class="admin-btn admin-btn--primary" type="submit">Terapkan Saran Alt Kosong</button>
                        </form>
                    </div>
                </div>

                <form method="get" action="<?= url('admin/media-library'); ?>" class="admin-card admin-report-filter admin-media-filter">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Filter Asset</span>
                            <h2>Audit Media & Asset SEO</h2>
                            <p>Filter berdasarkan status, isu SEO, folder, dan nama file/konten yang menggunakan asset.</p>
                        </div>
                        <div class="admin-toolbar__actions">
                            <a class="admin-btn admin-btn--soft" href="<?= esc(admin_media_library_url(['export' => 'csv', 'page' => null])); ?>">Export CSV</a>
                        </div>
                    </div>
                    <div class="admin-report-filter-grid admin-media-filter-grid--template">
                        <label>Status
                            <select name="status">
                                <?php foreach (['all' => 'Semua', 'missing_alt' => 'Alt Kosong', 'large' => 'Gambar Besar', 'unused' => 'Belum Dipakai', 'used' => 'Dipakai', 'ok' => 'OK'] as $value => $label): ?>
                                    <option value="<?= esc($value); ?>" <?= $status === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Isu SEO
                            <select name="issue">
                                <?php foreach (['all' => 'Semua isu', 'low_score' => 'Score di bawah 80', 'missing_alt' => 'Alt kosong', 'large' => 'Gambar besar', 'filename' => 'Filename kurang SEO', 'not_webp' => 'Belum WebP', 'unused' => 'Belum dipakai'] as $value => $label): ?>
                                    <option value="<?= esc($value); ?>" <?= $issue === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Folder
                            <select name="root">
                                <?php foreach (['all' => 'Uploads + Images', 'uploads' => 'assets/uploads', 'images' => 'assets/images'] as $value => $label): ?>
                                    <option value="<?= esc($value); ?>" <?= $root === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Cari File / Konten
                            <input type="search" name="q" value="<?= esc($q); ?>" placeholder="nama file, produk, artikel, LP...">
                        </label>
                        <label>Per Halaman
                            <select name="per_page">
                                <?php foreach ([12,24,48,96] as $option): ?>
                                    <option value="<?= (int)$option; ?>" <?= $perPage === $option ? 'selected' : ''; ?>><?= (int)$option; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="admin-report-filter-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan Filter</button>
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/media-library'); ?>">Reset</a>
                    </div>
                </form>

                <div class="admin-card admin-media-guide-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">SEO Guardrail</span>
                        <h2>Catatan Aman untuk Nama File & WebP</h2>
                        <p>Manager ini memberi rekomendasi nama file SEO dan WebP, tapi tidak rename otomatis supaya URL lama yang sudah terindeks Google tidak rusak. Gunakan saran filename saat upload ulang asset baru.</p>
                    </div>
                </div>

                <div class="admin-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <h2>Daftar Asset</h2>
                            <p>Menampilkan <?= $total > 0 ? (int)($offset + 1) : 0; ?>-<?= (int)min($offset + $perPage, $total); ?> dari <?= (int)$total; ?> file.</p>
                        </div>
                        <div class="admin-toolbar__actions">
                            <a class="admin-btn admin-btn--soft" href="<?= url('admin/produk?action=create'); ?>">Produk Baru</a>
                            <a class="admin-btn admin-btn--soft" href="<?= url('admin/artikel?action=create'); ?>">Artikel Baru</a>
                            <a class="admin-btn admin-btn--soft" href="<?= url('admin/landing-pages'); ?>">Landing Page</a>
                        </div>
                    </div>

                    <?php if (!$pageItems): ?>
                        <div class="admin-empty admin-empty--compact">
                            <h2>Asset tidak ditemukan</h2>
                            <p>Coba reset filter atau cek folder assets/uploads.</p>
                        </div>
                    <?php else: ?>
                        <div class="admin-media-grid admin-media-grid--template">
                            <?php foreach ($pageItems as $item): ?>
                                <?php $statusClass = admin_media_status_class((string)$item['status']); ?>
                                <?php $score = (int)($item['seo_score'] ?? 0); ?>
                                <article class="admin-media-card admin-media-card--template">
                                    <div class="admin-media-preview">
                                        <img src="<?= esc((string)$item['url']); ?>" alt="<?= esc((string)($item['alt_suggestion'] ?? $item['filename'])); ?>" loading="lazy">
                                    </div>
                                    <div class="admin-media-body">
                                        <div class="admin-media-title-row">
                                            <strong><?= esc((string)$item['filename']); ?></strong>
                                            <span class="admin-seo-score admin-seo-score--<?= esc(admin_media_score_class($score)); ?>"><?= $score; ?><span>%</span></span>
                                        </div>
                                        <div class="admin-media-title-row">
                                            <span class="admin-status-pill admin-status-pill--<?= esc($statusClass); ?>"><?= esc(admin_media_status_label((string)$item['status'])); ?></span>
                                            <?php if (!empty($item['reference_types'])): ?><span class="admin-status-pill admin-status-pill--info"><?= esc(implode(' + ', (array)$item['reference_types'])); ?></span><?php endif; ?>
                                        </div>
                                        <p class="admin-media-path"><?= esc((string)$item['relative']); ?></p>
                                        <div class="admin-media-meta">
                                            <span><?= esc((string)$item['size_label']); ?></span>
                                            <?php if (!empty($item['width']) && !empty($item['height'])): ?><span><?= (int)$item['width']; ?>×<?= (int)$item['height']; ?></span><?php endif; ?>
                                            <span><?= esc(strtoupper((string)$item['extension'])); ?></span>
                                            <span><?= esc((string)$item['modified_at']); ?></span>
                                        </div>

                                        <div class="admin-media-suggestions">
                                            <div><strong>Alt suggestion</strong><button type="button" data-copy-media="<?= esc((string)($item['alt_suggestion'] ?? '')); ?>">Salin</button><p><?= esc((string)($item['alt_suggestion'] ?? '-')); ?></p></div>
                                            <div><strong>Filename SEO</strong><button type="button" data-copy-media="<?= esc((string)($item['filename_suggestion'] ?? '')); ?>">Salin</button><p><?= esc((string)($item['filename_suggestion'] ?? '-')); ?></p></div>
                                            <div><strong>WebP target</strong><button type="button" data-copy-media="<?= esc((string)($item['webp_suggestion'] ?? '')); ?>">Salin</button><p><?= esc((string)($item['webp_suggestion'] ?? '-')); ?></p></div>
                                        </div>

                                        <div class="admin-media-actions">
                                            <button class="admin-btn admin-btn--primary" type="button" data-copy-media="<?= esc((string)$item['url']); ?>">Salin URL</button>
                                            <a class="admin-btn admin-btn--soft" href="<?= url('admin/produk?action=create&media_image=' . rawurlencode((string)$item['url'])); ?>">Pakai Produk</a>
                                            <a class="admin-btn admin-btn--soft" href="<?= url('admin/artikel?action=create&media_image=' . rawurlencode((string)$item['url'])); ?>">Pakai Artikel</a>
                                        </div>

                                        <div class="admin-media-recommendations">
                                            <strong>Rekomendasi</strong>
                                            <ul>
                                                <?php foreach (array_slice((array)($item['recommendations'] ?? []), 0, 4) as $recommendation): ?>
                                                    <li><?= esc((string)$recommendation); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>

                                        <?php if (!empty($item['references'])): ?>
                                            <div class="admin-media-refs">
                                                <strong>Dipakai di:</strong>
                                                <?php foreach (array_slice((array)$item['references'], 0, 5) as $ref): ?>
                                                    <div>
                                                        <span><?= esc((string)$ref['type']); ?>: <?= esc((string)$ref['title']); ?></span>
                                                        <?php if (media_library_reference_requires_alt($ref) && trim((string)($ref['alt'] ?? '')) === ''): ?><em>Alt kosong</em><?php endif; ?>
                                                        <?php if (!empty($ref['edit_url'])): ?><a href="<?= esc((string)$ref['edit_url']); ?>">Edit</a><?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="admin-muted">Belum terhubung ke produk/artikel/landing page. Pakai ulang jika masih relevan.</p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav class="admin-pagination" aria-label="Pagination media library">
                            <a class="admin-page-link <?= $page <= 1 ? 'is-disabled' : ''; ?>" href="<?= $page <= 1 ? '#' : admin_media_library_url(['page' => $page - 1]); ?>">‹ Prev</a>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a class="admin-page-link <?= $i === $page ? 'is-active' : ''; ?>" href="<?= admin_media_library_url(['page' => $i]); ?>"><?= (int)$i; ?></a>
                            <?php endfor; ?>
                            <a class="admin-page-link <?= $page >= $totalPages ? 'is-disabled' : ''; ?>" href="<?= $page >= $totalPages ? '#' : admin_media_library_url(['page' => $page + 1]); ?>">Next ›</a>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
(function(){
  document.querySelectorAll('[data-copy-media]').forEach(function(button){
    button.addEventListener('click', async function(){
      const value = button.getAttribute('data-copy-media') || '';
      const original = button.textContent;
      try {
        await navigator.clipboard.writeText(value);
        button.textContent = 'Tersalin';
      } catch (error) {
        window.prompt('Salin teks:', value);
      }
      setTimeout(function(){ button.textContent = original || 'Salin'; }, 1800);
    });
  });
})();
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
