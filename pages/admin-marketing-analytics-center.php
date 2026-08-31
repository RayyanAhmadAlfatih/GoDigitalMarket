<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$summary = function_exists('marketing_analytics_menu_audit_summary') ? marketing_analytics_menu_audit_summary() : ['total' => 0, 'groups' => [], 'guardrails' => []];
$groups = function_exists('marketing_analytics_menu_groups') ? marketing_analytics_menu_groups() : [];
$steps = function_exists('marketing_analytics_workflow_steps') ? marketing_analytics_workflow_steps() : [];
$decisions = function_exists('marketing_analytics_quick_decision') ? marketing_analytics_quick_decision() : [];

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Marketing & Analytics Center - Admin',
    'description' => 'Pusat peta menu marketing, analytics, iklan, tracking, landing page, CTA, SEO profit, dan action plan agar admin UMKM tidak bingung memilih menu.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-marketing-analytics-center-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Marketing & Analytics Center</h1>
                <p>Kompas menu untuk membedakan setup iklan, tracking lead, analisis landing page, testing CTA existing, attribution profit, action plan, dan report. Tujuannya bukan menambah fitur dobel, tapi membuat alur kerja marketing lebih jelas.</p>
            </div>
            <div class="mac-hero-card" aria-label="Ringkasan audit menu">
                <span>Audit menu</span>
                <strong><?= (int)($summary['total'] ?? 0); ?></strong>
                <small>titik fitur terhubung</small>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php admin_panel_render_nav('admin/marketing-analytics'); ?>
            <style>
                .admin-marketing-analytics-center-shell .mac-hero-card{min-width:210px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.12);color:#fff;border-radius:24px;padding:18px;box-shadow:0 18px 44px rgba(15,23,42,.12)}
                .admin-marketing-analytics-center-shell .mac-hero-card span,.admin-marketing-analytics-center-shell .mac-hero-card small{display:block;color:rgba(255,255,255,.82);font-weight:900}.admin-marketing-analytics-center-shell .mac-hero-card strong{display:block;font-size:3rem;line-height:1;margin:.3rem 0;color:#fff}
                .mac-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.mac-grid--2{grid-template-columns:repeat(2,minmax(0,1fr))}.mac-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 38px rgba(15,23,42,.055)}.mac-card h2,.mac-card h3{margin:.1rem 0 .45rem;color:#0f172a}.mac-card p{margin:.25rem 0;color:#64748b;line-height:1.55}.mac-badge{display:inline-flex;align-items:center;border-radius:999px;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 82%,#fff);color:var(--admin-primary);padding:5px 10px;font-size:.76rem;font-weight:900}.mac-flow{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.mac-flow__step{position:relative;border:1px solid #dbeafe;background:linear-gradient(135deg,#eff6ff,#fff);border-radius:22px;padding:14px;display:grid;gap:8px}.mac-flow__step strong{color:#0f172a}.mac-flow__num{width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:var(--admin-primary);color:#fff;font-weight:1000}.mac-decision{display:grid;gap:9px}.mac-decision__row{display:grid;grid-template-columns:minmax(0,1fr) 230px;gap:10px;align-items:center;border:1px solid #e2e8f0;background:#f8fafc;border-radius:16px;padding:11px}.mac-decision__row p{margin:0}.mac-decision__row strong{color:#0f172a}.mac-group{display:grid;gap:10px}.mac-group__head{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap}.mac-menu-list{display:grid;gap:9px}.mac-menu-item{border:1px solid #e2e8f0;background:#fff;border-radius:16px;padding:12px;display:grid;gap:7px}.mac-menu-item__top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}.mac-menu-item strong{color:#0f172a}.mac-menu-item small{display:block;color:#64748b;line-height:1.45}.mac-source{display:inline-flex;align-items:center;border-radius:999px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:4px 8px;font-size:.7rem;font-weight:900}.mac-guard{border:1px dashed var(--border);background:color-mix(in srgb,var(--bg) 78%,#fff);border-radius:22px;padding:16px}.mac-guard ul{margin:.5rem 0 0;padding-left:1.1rem;color:#475569}.mac-guard li{margin:.28rem 0}.mac-shortcuts{display:flex;gap:8px;flex-wrap:wrap}@media(max-width:1180px){.mac-flow{grid-template-columns:repeat(2,minmax(0,1fr))}.mac-grid{grid-template-columns:1fr}.mac-grid--2{grid-template-columns:1fr}}@media(max-width:760px){.mac-flow,.mac-decision__row{grid-template-columns:1fr}.admin-marketing-analytics-center-shell .mac-hero-card{min-width:0;width:100%}}
            </style>

            <section class="mac-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="mac-badge">Alur kerja yang disarankan</span>
                        <h2>Dari setup iklan sampai action plan</h2>
                        <p>Ikuti alur ini agar admin tidak loncat-loncat menu dan bisa membedakan fungsi tiap menu dengan jelas.</p>
                    </div>
                    <div class="mac-shortcuts">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/analytics')); ?>">Setup Iklan</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-analytics')); ?>">Analisis LP</a>
                        <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/profit-action-dashboard')); ?>">Action Dashboard</a>
                    </div>
                </div>
                <div class="mac-flow">
                    <?php foreach ($steps as $step): ?>
                        <article class="mac-flow__step">
                            <span class="mac-flow__num"><?= esc((string)($step['step'] ?? '')); ?></span>
                            <strong><?= esc((string)($step['title'] ?? '')); ?></strong>
                            <p><?= esc((string)($step['body'] ?? '')); ?></p>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($step['route'] ?? 'admin'))); ?>"><?= esc((string)($step['cta'] ?? 'Buka menu')); ?></a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="mac-grid mac-grid--2">
                <div class="mac-card">
                    <span class="mac-badge">Cepat pilih menu</span>
                    <h2>Kalau kebutuhannya ini, buka menu ini</h2>
                    <div class="mac-decision">
                        <?php foreach ($decisions as $row): ?>
                            <div class="mac-decision__row">
                                <p><?= esc((string)($row['need'] ?? '')); ?></p>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($row['route'] ?? 'admin'))); ?>"><?= esc((string)($row['go'] ?? 'Buka menu')); ?></a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <aside class="mac-card mac-guard">
                    <span class="mac-badge">Guardrail anti dobel</span>
                    <h2>Yang dipertahankan dari fitur existing</h2>
                    <p>Semua modul yang sudah tersedia tetap dipakai. Halaman ini hanya merapikan navigasi dan peta alur supaya setiap fitur punya posisi yang jelas.</p>
                    <ul>
                        <?php foreach ((array)($summary['guardrails'] ?? []) as $guardrail): ?>
                            <li><?= esc((string)$guardrail); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
            </section>

            <section class="mac-grid">
                <?php foreach ($groups as $groupName => $items): ?>
                    <article class="mac-card mac-group">
                        <div class="mac-group__head">
                            <div>
                                <span class="mac-badge"><?= esc(function_exists('marketing_analytics_group_labels') ? (marketing_analytics_group_labels()[$groupName] ?? (string)$groupName) : (string)$groupName); ?></span>
                                <h3><?= esc((string)$groupName); ?></h3>
                                <p><?= esc(function_exists('marketing_analytics_group_description') ? marketing_analytics_group_description((string)$groupName) : 'Menu pendukung marketing dan analytics.'); ?></p>
                            </div>
                        </div>
                        <div class="mac-menu-list">
                            <?php foreach ($items as $item): ?>
                                <div class="mac-menu-item">
                                    <div class="mac-menu-item__top">
                                        <strong><?= esc((string)($item['title'] ?? '-')); ?></strong>
                                        <span class="mac-source"><?= esc((string)($item['badge'] ?? 'Menu')); ?></span>
                                    </div>
                                    <small><?= esc((string)($item['purpose'] ?? '')); ?></small>
                                    <small><b>Kapan:</b> <?= esc((string)($item['when'] ?? '')); ?></small>
                                    <div class="mac-menu-item__top">
                                        <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($item['route'] ?? 'admin'))); ?>">Buka</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
