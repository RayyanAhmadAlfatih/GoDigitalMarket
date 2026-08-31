<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

seo_noindex();

if (isset($_GET['export'])) {
    migration_command_center_export((string)$_GET['export']);
}

$summary = migration_command_center_summary();
$modules = (array)($summary['modules'] ?? []);
$checklist = (array)($summary['checklist'] ?? []);

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Migration Command Center - Admin',
    'description' => 'Pusat komando migrasi WordPress ke U-Growth: import, SEO redirect, media, cleaner, Elementor, internal link, dynamic content, dan checklist final.',
    'robots' => 'noindex, nofollow',
]);
require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="admin-content" class="admin-shell admin-migration-command-center-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>Migration Command Center</h1>
                <p>Pusat komando untuk menjalankan migrasi WordPress ke U-Growth secara bertahap: preview, import, redirect, media, cleaner, Elementor, internal link, dynamic content, sampai checklist GSC.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/migration-command-center?export=json')); ?>">Export JSON</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/migration-command-center?export=csv')); ?>">Export CSV</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <section class="admin-grid admin-grid--stats">
                <article class="admin-preview-metric"><strong><?= (int)($summary['score'] ?? 0); ?></strong><small>Migration Health Score</small></article>
                <article class="admin-preview-metric"><strong><?= (int)($summary['available_modules'] ?? 0); ?>/<?= (int)($summary['module_count'] ?? 0); ?></strong><small>Modul Aktif</small></article>
                <article class="admin-preview-metric"><strong><?= (int)($summary['review_modules'] ?? 0); ?></strong><small>Modul Perlu Review</small></article>
                <article class="admin-preview-metric"><strong><?= (int)($summary['open_checklist'] ?? 0); ?></strong><small>Checklist Terbuka</small></article>
            </section>

            <section class="admin-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Status</span>
                    <h2><?= esc((string)($summary['status'] ?? '')); ?></h2>
                    <p>Command Center ini tidak menjalankan aksi destruktif otomatis. Semua modul tetap memakai preview, dry-run, backup, dan review manual.</p>
                </div>
                <div class="admin-grid admin-grid--2">
                    <div>
                        <h3>Alur paling aman</h3>
                        <ol class="admin-checklist">
                            <li>Upload XML/WXR/CSV dan buat preview.</li>
                            <li>Import artikel/page sebagai data U-Growth.</li>
                            <li>Review legacy URL, canonical, dan redirect 301.</li>
                            <li>Scan media, shortcode, Gutenberg, Elementor, breadcrumb, dan internal link.</li>
                            <li>Validasi dynamic content dan launch readiness sebelum go-live.</li>
                        </ol>
                    </div>
                    <div>
                        <h3>Checklist sebelum go-live</h3>
                        <p>Setelah semua modul migrasi selesai, lanjutkan audit source, route, SEO, security, installer, storage, wording publik, dan tes migrasi sample dari WordPress export sebelum website go-live.</p>
                    </div>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Workflow</span>
                    <h2>Checklist migrasi WordPress</h2>
                    <p>Gunakan daftar ini sebagai SOP migrasi supaya tidak ada langkah SEO yang kelewat.</p>
                </div>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Langkah</th><th>Status</th><th>Catatan</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($checklist as $item): ?>
                            <?php $status = (string)($item['status'] ?? 'todo'); ?>
                            <tr>
                                <td><strong><?= esc((string)($item['step'] ?? '')); ?></strong></td>
                                <td><span class="admin-badge admin-badge--<?= esc($status); ?>"><?= esc(strtoupper($status)); ?></span></td>
                                <td><?= esc((string)($item['note'] ?? '')); ?></td>
                                <td><a class="admin-btn admin-btn--small" href="<?= esc(url((string)($item['route'] ?? 'admin'))); ?>">Buka</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Modules</span>
                    <h2>Panel modul migrasi</h2>
                    <p>Semua modul migrasi tetap berdiri sendiri, tetapi sekarang dipantau dari satu tempat.</p>
                </div>
                <div class="admin-grid admin-grid--2">
                    <?php foreach ($modules as $module): ?>
                        <article class="admin-card admin-card--soft">
                            <div class="admin-form-head">
                                <span class="admin-badge"><?= esc((string)($module['phase'] ?? '')); ?></span>
                                <h3><?= esc((string)($module['title'] ?? '')); ?></h3>
                                <p><?= esc((string)($module['summary'] ?? '')); ?></p>
                            </div>
                            <div class="admin-grid admin-grid--stats">
                                <div class="admin-preview-metric"><strong><?= (int)($module['health'] ?? 0); ?></strong><small>Health</small></div>
                                <div class="admin-preview-metric"><strong><?= esc(strtoupper((string)($module['status'] ?? ''))); ?></strong><small>Status</small></div>
                            </div>
                            <?php if (!empty($module['counts'])): ?>
                                <div class="admin-mini-list" style="margin-top:12px">
                                    <?php foreach (array_slice((array)$module['counts'], 0, 6, true) as $key => $value): ?>
                                        <span><strong><?= esc(str_replace('_', ' ', (string)$key)); ?>:</strong> <?= esc(is_scalar($value) ? (string)$value : json_encode($value)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php foreach ((array)($module['notes'] ?? []) as $note): ?>
                                <div class="admin-alert admin-alert--info" style="margin-top:10px"><?= esc((string)$note); ?></div>
                            <?php endforeach; ?>
                            <div class="admin-form-actions"><a class="admin-btn admin-btn--primary" href="<?= esc(url((string)($module['route'] ?? 'admin'))); ?>">Buka Modul</a></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="admin-card">
                <div class="admin-form-head">
                    <span class="admin-badge">GSC</span>
                    <h2>Checklist setelah migrasi live</h2>
                </div>
                <ul>
                    <li>Submit ulang <code>/sitemap.xml</code> di Google Search Console.</li>
                    <li>Gunakan URL Inspection untuk beberapa URL lama ranking dan URL baru U-Growth.</li>
                    <li>Pantau Pages/Coverage, 404, redirect error, dan canonical mismatch selama beberapa hari pertama.</li>
                    <li>Jangan hapus redirect 301 URL lama sampai data Google benar-benar stabil.</li>
                </ul>
            </section>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
