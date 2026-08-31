<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$report = function_exists('launch_readiness_report') ? launch_readiness_report() : ['score' => 0, 'status' => 'Perlu Setup Awal', 'items' => [], 'next_items' => [], 'counts' => []];
$items = (array)($report['items'] ?? []);
$nextItems = (array)($report['next_items'] ?? []);
$counts = (array)($report['counts'] ?? []);
$groups = [];
foreach ($items as $item) {
    $groups[(string)($item['group'] ?? 'Setup')][] = $item;
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Launch Readiness - ' . SITE_NAME,
    'description' => 'Panduan kesiapan website untuk membantu admin menyiapkan brand, konten, menu, SEO, form, CTA, dan keamanan sebelum website dipromosikan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<style>
.launch-readiness-hero{background:linear-gradient(135deg,#2563eb,#17336c);color:#fff;border-radius:0 0 34px 34px;overflow:hidden}.launch-readiness-hero .admin-eyebrow,.launch-readiness-hero p{color:rgba(255,255,255,.86)}.launch-readiness-hero h1{color:#fff}.launch-readiness-score{display:flex;align-items:center;gap:1rem;flex-wrap:wrap}.launch-readiness-score strong{font-size:3.2rem;letter-spacing:-.08em;color:#fff}.launch-readiness-score span{font-weight:900;color:#dbeafe}.launch-readiness-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:1.2rem}.launch-readiness-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.8rem;margin-top:1rem}.launch-readiness-stats div{border:1px solid #dbe7f0;border-radius:22px;background:#fff;padding:1rem}.launch-readiness-stats strong{display:block;font-size:1.55rem;color:#1557e6}.launch-readiness-checklist{display:grid;gap:.8rem}.launch-readiness-item{display:grid;grid-template-columns:auto 1fr auto;gap:.85rem;align-items:flex-start;border:1px solid #dbe7f0;border-radius:22px;background:#fff;padding:1rem;box-shadow:0 14px 34px rgba(15,23,42,.04)}.launch-readiness-status{width:36px;height:36px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-weight:950}.launch-readiness-status--ok{background:#dcfce7;color:#15803d}.launch-readiness-status--todo{background:#eaf1ff;color:#1557e6}.launch-readiness-group{margin-top:1.2rem}.launch-readiness-group h3{margin:.2rem 0 .9rem}.launch-readiness-next{display:grid;gap:.75rem}.launch-readiness-next a{display:block;border:1px solid #dbe7f0;border-radius:20px;background:linear-gradient(180deg,#fff,#f8fbff);padding:.9rem;text-decoration:none;color:#0f172a}.launch-readiness-next strong{display:block}.launch-readiness-next span{display:block;color:#64748b;margin-top:.25rem}.launch-readiness-path{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}.launch-readiness-path div{border:1px solid #dbe7f0;border-radius:22px;background:#fff;padding:1rem}.launch-readiness-path strong{display:block;color:#1454d8;margin-bottom:.35rem}.launch-readiness-item p{margin:.25rem 0 0;color:#64748b}.launch-readiness-item small{display:block;color:#64748b;font-weight:800;margin-top:.15rem}@media(max-width:1100px){.launch-readiness-grid,.launch-readiness-path{grid-template-columns:1fr}.launch-readiness-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.launch-readiness-item{grid-template-columns:auto 1fr}.launch-readiness-item .admin-btn{grid-column:1/-1}}
</style>

<main id="main-content" class="admin-shell admin-launch-readiness-shell">
    <section class="admin-hero launch-readiness-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Guided Setup</div>
                <h1>Launch Readiness Center</h1>
                <p>Panduan ringkas agar admin tahu langkah apa yang perlu dikerjakan sebelum website dipromosikan: brand, menu, konten, katalog, form, CTA, SEO, dan keamanan.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Website</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/starter-wizard')); ?>">Starter Wizard</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <div class="admin-card admin-report-hero-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Website Setup Score</span>
                        <h2><?= esc((string)($report['status'] ?? 'Perlu Setup Awal')); ?></h2>
                        <p>Skor ini berbasis checklist internal website. Gunakan sebagai panduan kerja, bukan pengganti review manual.</p>
                    </div>
                    <div class="launch-readiness-score"><strong><?= (int)($report['score'] ?? 0); ?></strong><span>/100</span></div>
                </div>
                <div class="launch-readiness-stats">
                    <div><span class="admin-badge">Katalog</span><strong><?= (int)($counts['products'] ?? 0); ?></strong><small>produk/jasa aktif</small></div>
                    <div><span class="admin-badge">Artikel</span><strong><?= (int)($counts['articles'] ?? 0); ?></strong><small>konten SEO</small></div>
                    <div><span class="admin-badge">Form</span><strong><?= (int)($counts['forms'] ?? 0); ?></strong><small>form aktif</small></div>
                    <div><span class="admin-badge">Trust</span><strong><?= (int)($counts['trust_blocks'] ?? 0); ?></strong><small>block aktif</small></div>
                    <div><span class="admin-badge">Menu</span><strong><?= (int)($counts['navigation'] ?? 0); ?></strong><small>navigasi aktif</small></div>
                    <div><span class="admin-badge">SEO</span><strong><?= (int)($counts['seo_score'] ?? 0); ?></strong><small>score dasar</small></div>
                </div>
            </div>

            <div class="launch-readiness-grid" style="margin-top:1.2rem">
                <div class="admin-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">Guided Checklist</span>
                        <h2>Langkah Setup Website</h2>
                        <p>Checklist ini membantu user awam memahami menu mana yang harus dibuka dan apa tujuan setiap langkah.</p>
                    </div>
                    <?php foreach ($groups as $group => $groupItems): ?>
                        <div class="launch-readiness-group">
                            <h3><?= esc((string)$group); ?></h3>
                            <div class="launch-readiness-checklist">
                                <?php foreach ($groupItems as $item): ?>
                                    <?php $status = (string)($item['status'] ?? 'todo'); ?>
                                    <div class="launch-readiness-item">
                                        <span class="launch-readiness-status launch-readiness-status--<?= esc($status); ?>"><?= $status === 'ok' ? '✓' : '→'; ?></span>
                                        <div>
                                            <strong><?= esc((string)($item['label'] ?? '-')); ?></strong>
                                            <p><?= esc((string)($item['description'] ?? '')); ?></p>
                                            <small>Status: <?= $status === 'ok' ? 'Selesai' : 'Perlu dikerjakan'; ?></small>
                                        </div>
                                        <a class="admin-btn <?= $status === 'ok' ? 'admin-btn--soft' : 'admin-btn--primary'; ?>" href="<?= esc((string)($item['href'] ?? url('admin'))); ?>"><?= esc((string)($item['action'] ?? 'Buka')); ?></a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <aside class="admin-card">
                    <div class="admin-form-head">
                        <span class="admin-badge">Next Action</span>
                        <h2>Prioritas Terdekat</h2>
                        <p>Kerjakan item di bawah ini dulu agar website lebih cepat siap digunakan.</p>
                    </div>
                    <div class="launch-readiness-next">
                        <?php if (!$nextItems): ?>
                            <a href="<?= esc(url('admin/growth-snapshot')); ?>"><strong>Website sudah siap masuk tahap growth</strong><span>Lanjut pantau Growth Snapshot, funnel, dan conversion opportunity.</span></a>
                        <?php else: ?>
                            <?php foreach ($nextItems as $item): ?>
                                <a href="<?= esc((string)($item['href'] ?? url('admin'))); ?>">
                                    <strong><?= esc((string)($item['label'] ?? '-')); ?></strong>
                                    <span><?= esc((string)($item['action'] ?? 'Buka pengaturan')); ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>

            <div class="admin-card" style="margin-top:1.2rem">
                <div class="admin-form-head">
                    <span class="admin-badge">Alur Paling Aman</span>
                    <h2>Urutan Setup untuk User Awam</h2>
                    <p>Mulai dari identitas bisnis, lanjut konten, lalu konversi dan SEO. Setelah itu baru scale dengan insight.</p>
                </div>
                <div class="launch-readiness-path">
                    <div><strong>1. Identitas</strong><span>Brand, logo, favicon, warna, mode bisnis, dan menu.</span></div>
                    <div><strong>2. Konten</strong><span>Homepage, katalog/layanan, artikel awal, halaman kontak.</span></div>
                    <div><strong>3. Konversi</strong><span>CTA, WhatsApp, form, checkout, invoice, dan follow-up.</span></div>
                    <div><strong>4. Growth</strong><span>SEO Engine, Growth Snapshot, Funnel Action Center, dan laporan.</span></div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
