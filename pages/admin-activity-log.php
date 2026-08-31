<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$message = (string)($_GET['message'] ?? '');
$error = '';

if ((string)($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/activity-log');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !admin_panel_logged_in()) {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
        $_SESSION['admin_articles_logged_in'] = true;
        activity_log_record('login', 'admin', null, 'Admin login ke Activity Log.', ['area' => 'activity_log']);
        redirect_302('admin/activity-log');
    } else {
        $error = 'Password admin salah.';
        activity_log_record('login_failed', 'admin', null, 'Percobaan login Activity Log gagal.', ['area' => 'activity_log']);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && admin_panel_logged_in() && isset($_POST['log_action'])) {
    require_csrf();
    $logAction = (string)($_POST['log_action'] ?? '');
    if ($logAction === 'prune') {
        $deleted = activity_log_prune(1000);
        activity_log_record('prune', 'activity_log', null, 'Activity log dipangkas.', ['deleted_rows' => $deleted, 'keep_last' => 1000]);
        redirect_302('admin/activity-log?message=' . rawurlencode('Log dipangkas. Dihapus: ' . $deleted . ' baris lama.'));
    }
    if ($logAction === 'clear') {
        activity_log_clear();
        activity_log_record('clear', 'activity_log', null, 'Activity log dikosongkan oleh admin.');
        redirect_302('admin/activity-log?message=' . rawurlencode('Activity log dikosongkan.'));
    }
}

$loggedIn = admin_panel_logged_in();
$limit = max(25, min(500, (int)($_GET['limit'] ?? 200)));
$filters = [
    'action' => trim((string)($_GET['action_filter'] ?? '')),
    'entity' => trim((string)($_GET['entity_filter'] ?? '')),
    'q' => trim((string)($_GET['q'] ?? '')),
];
$logs = $loggedIn ? activity_log_read($limit, $filters) : [];
$stats = $loggedIn ? activity_log_stats() : [];

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Admin Activity Log - ' . SITE_NAME,
    'description' => 'Audit trail aktivitas admin website produk dan layanan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-activity-log-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Riwayat Aktivitas Admin</div>
                <h1>Riwayat Aktivitas Admin</h1>
                <p>Lihat riwayat login, perubahan produk/artikel, pesanan, pembayaran, pengingat, dan aktivitas penting lainnya.</p>
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
                        <h2>Masuk Activity Log</h2>
                        <p>Gunakan password admin yang sama dengan dashboard produk dan order.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/activity-log'); ?>

                <div class="admin-grid admin-grid--stats">
                    <div class="admin-card"><span class="admin-badge">Total Log</span><h2><?= (int)($stats['total'] ?? 0); ?></h2><p>Semua aktivitas tersimpan.</p></div>
                    <div class="admin-card"><span class="admin-badge">Ditampilkan</span><h2><?= count($logs); ?></h2><p>Baris yang ditampilkan.</p></div>
                    <div class="admin-card"><span class="admin-badge">Ukuran</span><h2><?= number_format(((int)($stats['size_bytes'] ?? 0)) / 1024, 1); ?> KB</h2><p>Ukuran file log.</p></div>
                    <div class="admin-card"><span class="admin-badge">Penyimpanan</span><h2><?= !empty($stats['writable']) ? 'OK' : 'WARN'; ?></h2><p><?= esc((string)($stats['path'] ?? activity_log_path())); ?></p></div>
                </div>

                <div class="admin-card" style="margin-top:18px">
                    <div class="admin-form-head admin-form-head--split">
                        <div><h2>Filter & Perawatan</h2><p>Filter aktivitas terbaru atau bersihkan riwayat lama agar dashboard tetap nyaman digunakan.</p></div>
                        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap">
                            <?= csrf_field(); ?>
                            <button class="admin-btn admin-btn--soft" type="submit" name="log_action" value="prune">Prune Log</button>
                            <button class="admin-btn admin-btn--danger" type="submit" name="log_action" value="clear" onclick="return confirm('Yakin kosongkan activity log?')">Clear Log</button>
                        </form>
                    </div>
                    <form method="get" class="admin-form-grid">
                        <label>Search<input name="q" value="<?= esc((string)($_GET['q'] ?? '')); ?>" placeholder="Cari pesan, IP, path, entity..."></label>
                        <label>Action<input name="action_filter" value="<?= esc((string)($_GET['action_filter'] ?? '')); ?>" placeholder="login, create, update..."></label>
                        <label>Entity<input name="entity_filter" value="<?= esc((string)($_GET['entity_filter'] ?? '')); ?>" placeholder="product, article, order..."></label>
                        <label>Limit<input name="limit" type="number" min="25" max="500" value="<?= (int)$limit; ?>"></label>
                        <div style="display:flex;gap:8px;align-items:end"><button class="admin-btn admin-btn--primary" type="submit">Terapkan</button><a class="admin-btn admin-btn--soft" href="<?= url('admin/activity-log'); ?>">Reset</a></div>
                    </form>
                </div>

                <div class="admin-grid" style="margin-top:18px">
                    <div class="admin-card">
                        <div class="admin-form-head"><h2>Aksi Terbanyak</h2><p>Distribusi aksi dari 1000 log terbaru.</p></div>
                        <div class="admin-activity-rank-list">
                            <?php foreach (array_slice((array)($stats['by_action'] ?? []), 0, 8, true) as $name => $count): ?>
                                <div class="admin-activity-rank-row"><span><?= esc((string)$name); ?></span><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="admin-card">
                        <div class="admin-form-head"><h2>Area Terbanyak</h2><p>Area yang paling sering berubah.</p></div>
                        <div class="admin-activity-rank-list">
                            <?php foreach (array_slice((array)($stats['by_entity'] ?? []), 0, 8, true) as $name => $count): ?>
                                <div class="admin-activity-rank-row"><span><?= esc((string)$name); ?></span><strong><?= (int)$count; ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="admin-card" style="margin-top:18px">
                    <div class="admin-form-head">
                        <h2>Aktivitas Terbaru</h2>
                        <p>Data sensitif seperti password, token, dan cookie otomatis disensor.</p>
                    </div>
                    <?php if (!$logs): ?>
                        <p>Belum ada aktivitas admin yang tercatat sesuai filter.</p>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead><tr><th>Waktu</th><th>Aksi</th><th>Entity</th><th>Pesan</th><th>Request</th><th>Context</th></tr></thead>
                                <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php $request = (array)($log['request'] ?? []); ?>
                                    <tr>
                                        <td><?= esc((string)($log['time'] ?? '-')); ?></td>
                                        <td><span class="admin-source-badge"><?= esc((string)($log['action'] ?? '-')); ?></span></td>
                                        <td><?= esc((string)($log['entity'] ?? '-')); ?><?= isset($log['entity_id']) && $log['entity_id'] !== null ? ' #' . esc((string)$log['entity_id']) : ''; ?></td>
                                        <td><?= esc((string)($log['message'] ?? '-')); ?></td>
                                        <td><small><?= esc((string)($request['method'] ?? '-')); ?> <?= esc((string)($request['path'] ?? '-')); ?><br><?= esc((string)($request['ip'] ?? '-')); ?></small></td>
                                        <td><small><?= esc(json_encode($log['context'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
