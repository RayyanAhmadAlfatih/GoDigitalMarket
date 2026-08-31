<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$query = trim((string)($_GET['q'] ?? ''));
$items = function_exists('admin_help_search') ? admin_help_search($query) : [];
$grouped = function_exists('admin_help_grouped_items') ? admin_help_grouped_items($items) : [];
$totalItems = count(function_exists('admin_help_definitions') ? admin_help_definitions() : []);
$currentHelp = function_exists('admin_help_current') ? admin_help_current() : [];

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Pusat Bantuan Dashboard - ' . SITE_NAME,
    'description' => 'Panduan singkat untuk memahami menu dashboard website UMKM.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-help-center-shell">
    <section class="admin-hero admin-help-center-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Bantuan Dashboard</div>
                <h1>Pusat Bantuan Admin</h1>
                <p>Panduan ringan untuk memahami fungsi menu dashboard, urutan kerja yang disarankan, dan langkah yang paling dekat dengan SEO, lead, dan penjualan.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/onboarding-assistant')); ?>">Buka Panduan Harian</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/launch-readiness')); ?>">Cek Kesiapan</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <div class="admin-help-center-grid">
                <div class="admin-help-center-main">
                    <div class="admin-card admin-help-search-card">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="admin-badge">Cari Panduan</span>
                                <h2>Mau Ngatur Apa Hari Ini?</h2>
                                <p>Cari menu seperti brand, katalog, SEO, form, checkout, follow-up, SMTP, atau backup.</p>
                            </div>
                            <span class="admin-help-count"><?= (int)count($items); ?> / <?= (int)$totalItems; ?> panduan</span>
                        </div>
                        <form class="admin-help-search" method="get" action="<?= esc(url('admin/help-center')); ?>">
                            <input type="search" name="q" value="<?= esc($query); ?>" placeholder="Cari panduan dashboard...">
                            <button class="admin-btn admin-btn--primary" type="submit">Cari</button>
                            <?php if ($query !== ''): ?><a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/help-center')); ?>">Reset</a><?php endif; ?>
                        </form>
                    </div>

                    <?php if (!$items): ?>
                        <div class="admin-card admin-help-empty">
                            <span class="admin-badge">Belum Ketemu</span>
                            <h2>Panduan tidak ditemukan</h2>
                            <p>Coba kata lain yang lebih umum, misalnya “SEO”, “form”, “produk”, “order”, “brand”, atau “email”.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($grouped as $group => $groupItems): ?>
                        <div class="admin-help-group-block">
                            <div class="admin-help-group-title">
                                <span><?= esc((string)$group); ?></span>
                                <strong><?= count($groupItems); ?> panduan</strong>
                            </div>
                            <div class="admin-help-card-grid">
                                <?php foreach ($groupItems as $item): ?>
                                    <article class="admin-help-guide-card">
                                        <div class="admin-help-guide-card__head">
                                            <span><?= esc((string)($item['group'] ?? 'Dashboard')); ?></span>
                                            <h3><?= esc((string)($item['title'] ?? 'Panduan')); ?></h3>
                                            <p><?= esc((string)($item['summary'] ?? 'Panduan singkat untuk menu ini.')); ?></p>
                                        </div>
                                        <div class="admin-help-guide-card__body">
                                            <strong>Kerjakan dulu:</strong>
                                            <ol>
                                                <?php foreach (array_slice((array)($item['first_steps'] ?? []), 0, 3) as $step): ?>
                                                    <li><?= esc((string)$step); ?></li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                        <div class="admin-help-guide-card__foot">
                                            <a class="admin-btn admin-btn--primary" href="<?= esc(url((string)($item['primary_path'] ?? 'admin/brand'))); ?>"><?= esc((string)($item['primary_label'] ?? 'Buka Menu')); ?></a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <aside class="admin-help-center-side">
                    <div class="admin-card admin-help-side-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Menu Saat Ini</span>
                            <h2><?= esc((string)($currentHelp['title'] ?? 'Dashboard')); ?></h2>
                            <p><?= esc((string)($currentHelp['summary'] ?? 'Buka panduan menu untuk melihat langkah singkat.')); ?></p>
                        </div>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($currentHelp['primary_path'] ?? 'admin/brand'))); ?>"><?= esc((string)($currentHelp['primary_label'] ?? 'Buka Menu')); ?></a>
                    </div>

                    <div class="admin-card admin-help-side-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Alur Paling Aman</span>
                            <h2>Urutan Setup Anti Bingung</h2>
                            <p>Untuk admin baru, mulai dari pondasi dulu lalu masuk ke SEO dan growth.</p>
                        </div>
                        <div class="admin-help-mini-path">
                            <a href="<?= esc(url('admin/brand')); ?>"><strong>1. Brand</strong><span>Identitas, kontak, warna.</span></a>
                            <a href="<?= esc(url('admin/homepage')); ?>"><strong>2. Homepage</strong><span>Headline, section, CTA.</span></a>
                            <a href="<?= esc(url('admin/produk')); ?>"><strong>3. Katalog</strong><span>Produk/jasa utama.</span></a>
                            <a href="<?= esc(url('admin/trust-conversion')); ?>"><strong>4. Trust</strong><span>Testimoni, FAQ, benefit.</span></a>
                            <a href="<?= esc(url('admin/universal-seo')); ?>"><strong>5. SEO</strong><span>Meta, schema, struktur.</span></a>
                            <a href="<?= esc(url('admin/launch-readiness')); ?>"><strong>6. Launch</strong><span>Cek kesiapan promosi.</span></a>
                        </div>
                    </div>

                    <div class="admin-card admin-help-side-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Tips Praktis</span>
                            <h2>Fokus yang Dekat Profit</h2>
                            <p>Utamakan halaman yang punya CTA jelas: homepage, katalog, landing page, form, checkout, dan halaman artikel yang mengarah ke penawaran.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
