<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        if (!function_exists('admin_menu_settings_save_from_post')) {
            throw new RuntimeException('Pengaturan menu belum siap.');
        }
        admin_menu_settings_save_from_post($_POST);
        redirect_302('admin/menu-features?message=' . rawurlencode('Pengaturan menu admin berhasil disimpan. Menu yang dinonaktifkan hanya disembunyikan dari sidebar, data dan fitur tidak dihapus.'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$groups = function_exists('admin_menu_groups') ? admin_menu_groups() : [];
$rows = function_exists('admin_menu_flatten_groups') ? admin_menu_flatten_groups($groups) : [];
$settings = function_exists('admin_menu_settings_read') ? admin_menu_settings_read() : ['disabled_items' => []];
$disabled = array_flip((array)($settings['disabled_items'] ?? []));
$groupedRows = [];
foreach ($rows as $row) {
    $groupedRows[(string)($row['group'] ?? 'Menu')][] = $row;
}
$total = count($rows);
$disabledCount = count($disabled);
$lockedCount = 0;
foreach ($rows as $row) {
    if (!empty($row['locked'])) {
        $lockedCount++;
    }
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Menu & Fitur Admin - Admin',
    'description' => 'Atur menu dashboard yang ingin ditampilkan atau disembunyikan tanpa menghapus data website.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="admin-content" class="admin-shell admin-menu-features-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Sistem</div>
                <h1>Menu & Fitur Admin</h1>
                <p>Nonaktifkan tampilan menu yang belum dipakai agar dashboard lebih ringkas. Pengaturan ini hanya menyembunyikan menu dari sidebar, bukan menghapus file, data, route, atau fitur.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/users')); ?>">User & Role</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/data-health')); ?>">Cek Sistem</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Total Menu</span><h2><?= (int)$total; ?></h2><p>Item menu admin yang terdaftar di dashboard.</p></div>
                <div class="admin-card"><span class="admin-badge">Disembunyikan</span><h2><?= (int)$disabledCount; ?></h2><p>Menu yang tidak tampil di sidebar.</p></div>
                <div class="admin-card"><span class="admin-badge">Terkunci Aman</span><h2><?= (int)$lockedCount; ?></h2><p>Menu inti yang tidak bisa disembunyikan agar owner tidak terkunci.</p></div>
                <div class="admin-card"><span class="admin-badge">Dampak Data</span><h2>Aman</h2><p>Tidak mengubah storage, MySQL, produk, artikel, order, atau analytics.</p></div>
            </div>

            <form method="post" class="admin-card admin-editor admin-menu-features-card">
                <?= csrf_field(); ?>
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Feature Toggle</span>
                        <h2>Pilih menu yang ingin disembunyikan</h2>
                        <p>Centang kolom “Sembunyikan” untuk menu yang tidak ingin ditampilkan. Route tetap ada untuk menjaga kompatibilitas link lama dan audit.</p>
                    </div>
                    <div class="admin-form-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Pengaturan</button>
                    </div>
                </div>

                <div class="admin-alert admin-alert--info" style="margin-top:14px">
                    Saran aman: sembunyikan dulu menu yang tumpang tindih. Hapus file/source hanya setelah audit dependency, route, role, storage, dan database benar-benar aman.
                </div>

                <div class="admin-feature-groups">
                    <?php foreach ($groupedRows as $groupLabel => $items): ?>
                        <section class="admin-feature-group">
                            <div class="admin-feature-group__head">
                                <h3><?= esc((string)$groupLabel); ?></h3>
                                <span><?= count($items); ?> menu</span>
                            </div>
                            <div class="admin-feature-list">
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $key = (string)($item['key'] ?? '');
                                    $isDisabled = isset($disabled[$key]);
                                    $locked = !empty($item['locked']);
                                    ?>
                                    <label class="admin-feature-toggle <?= $isDisabled ? 'is-disabled' : ''; ?> <?= $locked ? 'is-locked' : ''; ?>">
                                        <span class="admin-feature-toggle__main">
                                            <span class="admin-sidebar__icon" aria-hidden="true"><?= esc((string)($item['icon'] ?? '')); ?></span>
                                            <span>
                                                <strong><?= esc((string)($item['label'] ?? $key)); ?></strong>
                                                <small><?= esc($key); ?></small>
                                            </span>
                                        </span>
                                        <span class="admin-feature-toggle__control">
                                            <?php if ($locked): ?>
                                                <span class="admin-status-pill admin-status-pill--ok">Terkunci</span>
                                            <?php else: ?>
                                                <input type="checkbox" name="disabled_items[]" value="<?= esc($key); ?>" <?= $isDisabled ? 'checked' : ''; ?>>
                                                <span>Sembunyikan</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div class="admin-form-actions" style="margin-top:18px">
                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Pengaturan</button>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/menu-features')); ?>">Reset Tampilan Form</a>
                </div>
            </form>

            <section class="admin-grid admin-grid--2">
                <article class="admin-card">
                    <span class="admin-badge">Cara Kerja</span>
                    <h2>Hanya menyembunyikan sidebar</h2>
                    <p>Feature toggle ini tidak menghapus page, helper, storage, schema database, atau data user. Jadi aman untuk merapikan dashboard tanpa risiko kehilangan data.</p>
                </article>
                <article class="admin-card">
                    <span class="admin-badge">Best Practice</span>
                    <h2>Sembunyikan sebelum hapus</h2>
                    <p>Kalau menu terasa tumpang tindih, sembunyikan dulu. Setelah dipakai beberapa waktu dan tidak ada dependency, baru menu tersebut bisa diaudit untuk penghapusan permanen.</p>
                </article>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
