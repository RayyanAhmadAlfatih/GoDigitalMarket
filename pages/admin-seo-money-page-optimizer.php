<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$allowedDays = [7, 14, 30, 60, 90, 180];
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, $allowedDays, true)) {
    $days = 30;
}

$typeOptions = function_exists('seo_profit_type_options') ? seo_profit_type_options() : ['all' => 'Semua SEO Page'];
$type = (string)($_GET['type'] ?? 'all');
if (!isset($typeOptions[$type])) {
    $type = 'all';
}

$priorityOptions = function_exists('seo_money_summary') ? (array)(seo_money_summary($days, $type, 'all')['priority_options'] ?? []) : ['all' => 'Semua Prioritas'];
$priority = (string)($_GET['priority'] ?? 'all');
if (!isset($priorityOptions[$priority])) {
    $priority = 'all';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'decision') {
            seo_money_update_decision(
                (string)($_POST['page_id'] ?? ''),
                (string)($_POST['decision_status'] ?? 'queued'),
                (string)($_POST['owner_note'] ?? '')
            );
            redirect_302('admin/seo-money-page-optimizer?days=' . $days . '&type=' . rawurlencode($type) . '&priority=' . rawurlencode($priority) . '&message=' . rawurlencode('Keputusan money page berhasil disimpan.'));
        }

        if ($action === 'reset_decisions') {
            seo_money_reset_decisions();
            redirect_302('admin/seo-money-page-optimizer?days=' . $days . '&type=' . rawurlencode($type) . '&priority=' . rawurlencode($priority) . '&message=' . rawurlencode('Catatan SEO Money Page Optimizer sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = function_exists('seo_money_summary') ? seo_money_summary($days, $type, $priority) : [];
$items = (array)($summary['items'] ?? []);
$counts = (array)($summary['counts'] ?? []);
$stageCounts = (array)($summary['stage_counts'] ?? []);
$topItem = is_array($summary['top_item'] ?? null) ? (array)$summary['top_item'] : [];
$decisionOptions = function_exists('seo_money_decision_options') ? seo_money_decision_options() : ['queued' => 'Masuk antrean optimasi'];
$priorityOptions = (array)($summary['priority_options'] ?? $priorityOptions);
$typeOptions = (array)($summary['type_options'] ?? $typeOptions);

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-money-page-optimizer-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv' && function_exists('seo_money_export_csv')) {
    seo_money_export_csv($items);
}

$formatPercent = static function (mixed $value): string {
    return rtrim(rtrim(number_format((float)$value, 1, ',', '.'), '0'), ',') . '%';
};

$baseUrl = static function (array $overrides = []) use ($days, $type, $priority): string {
    $query = array_merge(['days' => $days, 'type' => $type, 'priority' => $priority], $overrides);
    return url('admin/seo-money-page-optimizer?' . http_build_query($query));
};

$priorityLabel = static function (string $value): string {
    return match ($value) {
        'high' => 'High Priority',
        'medium' => 'Medium Priority',
        'low' => 'Low Priority',
        default => 'Semua Prioritas',
    };
};

$stageLabel = static function (string $key): string {
    return match ($key) {
        'scale_ready' => 'Siap di-scale',
        'lead_optimizer' => 'Lead optimizer',
        'offer_optimizer' => 'Offer optimizer',
        'cta_builder' => 'CTA builder',
        'seo_foundation' => 'SEO foundation',
        'seed_money_page' => 'Seed money page',
        default => 'Pantau dulu',
    };
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'SEO Money Page Optimizer - Admin',
    'description' => 'Optimizer halaman SEO prioritas agar punya CTA, internal link, trust block, dan offer yang lebih dekat ke lead/order.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-money-shell">
    <section class="admin-hero admin-seo-money-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>SEO Money Page Optimizer</h1>
                <p>Ubah artikel/halaman SEO potensial menjadi money page: CTA mana dipasang, internal link ke mana, trust block apa yang perlu, dan offer apa yang paling cocok.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey')); ?>">Journey Map</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-profit-attribution')); ?>">SEO Profit</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement')); ?>">CTA Placement</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/money-page-deployment-checklist')); ?>">Deployment Checklist</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/internal-link-cta-injection')); ?>">Link & CTA Injection</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/trust-conversion')); ?>">Trust Block</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview admin-seo-money-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Money Page Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['money_page_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['money_page_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>SEO → Lead/Order</h2>
                    <p><?= esc((string)($summary['top_focus'] ?? 'Pilih halaman prioritas lalu kerjakan CTA, internal link, trust, dan offer.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Antrean Optimasi</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($counts['total'] ?? 0); ?></strong> halaman</span>
                        <span><strong><?= (int)($counts['high'] ?? 0); ?></strong> high</span>
                        <span><strong><?= (int)($counts['medium'] ?? 0); ?></strong> medium</span>
                        <span><strong><?= (int)($counts['done'] ?? 0); ?></strong> selesai</span>
                    </div>
                    <p>Optimizer ini membaca <strong>SEO Journey Map</strong>, <strong>SEO Profit Attribution</strong>, dan <strong>Lead Tracking existing</strong>. Tidak membuat tracking baru.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Top Money Page</span>
                    <?php if ($topItem): ?>
                        <?php $topPage = (array)($topItem['item'] ?? []); $topMetrics = (array)($topItem['metrics'] ?? []); ?>
                        <h2><?= esc((string)($topPage['title'] ?? 'Money Page')); ?></h2>
                        <p><?= esc((string)($topPage['type_label'] ?? 'Halaman')); ?> · <?= esc($priorityLabel((string)($topItem['priority'] ?? 'low'))); ?> · <?= (int)($topMetrics['clicks'] ?? 0); ?> klik · <?= (int)($topMetrics['leads'] ?? 0); ?> lead · <?= (int)($topMetrics['orders'] ?? 0); ?> order</p>
                        <div class="admin-cta-result-export-row">
                            <?php if (!empty($topPage['url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topPage['url']); ?>" target="_blank" rel="noopener">Lihat Page</a><?php endif; ?>
                            <?php if (!empty($topPage['edit_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topPage['edit_url']); ?>">Edit Konten</a><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <h2>Belum ada money page terbaca</h2>
                        <p>Tambahkan artikel/halaman SEO, pasang CTA, lalu cek ulang setelah ada sinyal traffic atau lead.</p>
                    <?php endif; ?>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Money Page</span>
                        <h2>Periode, tipe halaman, dan prioritas</h2>
                        <p>Pilih halaman yang paling dekat ke lead/order agar pekerjaan optimasi tidak melebar.</p>
                    </div>
                </div>
                <div class="admin-seo-profit-filter-row">
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($allowedDays as $rangeDays): ?>
                            <a class="<?= $days === $rangeDays ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['days' => $rangeDays])); ?>"><?= (int)$rangeDays; ?> hari</a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
                            <a class="<?= $type === (string)$typeKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['type' => (string)$typeKey])); ?>"><?= esc((string)$typeLabel); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($priorityOptions as $priorityKey => $priorityText): ?>
                            <a class="<?= $priority === (string)$priorityKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['priority' => (string)$priorityKey])); ?>"><?= esc((string)$priorityText); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="admin-card admin-seo-money-stage-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Stage Optimasi</span>
                        <h2>Halaman ini perlu dikerjakan sebagai apa?</h2>
                        <p>Stage membantu admin menentukan pekerjaan utama: SEO foundation, CTA builder, offer optimizer, lead optimizer, atau scale.</p>
                    </div>
                </div>
                <?php if (!$stageCounts): ?>
                    <div class="admin-empty-state"><p>Belum ada stage terbaca.</p></div>
                <?php else: ?>
                    <div class="admin-seo-journey-bottleneck-grid admin-seo-money-stage-grid">
                        <?php foreach ($stageCounts as $stageKey => $count): ?>
                            <article>
                                <strong><?= esc($stageLabel((string)$stageKey)); ?></strong>
                                <span><?= (int)$count; ?> halaman</span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-bridge-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Alur Optimasi Aman</span>
                        <h2>Optimizer ini meneruskan data yang sudah ada</h2>
                        <p>Sumber tracking tetap Lead Tracking. Halaman ini hanya menerjemahkan data menjadi brief optimasi money page yang lebih mudah dieksekusi.</p>
                    </div>
                </div>
                <div class="admin-cta-result-flow admin-seo-money-flow">
                    <div><strong>Lead Tracking</strong><span>Data klik, lead, order, payment existing.</span></div>
                    <div><strong>SEO Journey</strong><span>Melihat macetnya di stage mana.</span></div>
                    <div><strong>Money Page Brief</strong><span>CTA, internal link, trust, offer, dan content fix.</span></div>
                    <div><strong>Action Menu</strong><span>Kerjakan di artikel, homepage, CTA Placement, Trust Block, atau Offer Lab.</span></div>
                </div>
            </section>

            <section class="admin-card admin-seo-money-list-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Optimization Brief</span>
                        <h2>Rekomendasi detail per money page</h2>
                        <p>Kerjakan dari prioritas high dulu, terutama halaman yang sudah punya lead/order atau klik tapi belum menjadi lead.</p>
                    </div>
                    <div class="admin-hero__actions">
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/money-page-deployment-checklist?days=' . $days . '&type=' . rawurlencode($type) . '&priority=' . rawurlencode($priority))); ?>">Buka Deployment Checklist</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/internal-link-cta-injection?days=' . $days . '&type=' . rawurlencode($type) . '&priority=' . rawurlencode($priority))); ?>">Buka Link & CTA Injection</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/offer-cta-testing')); ?>">Buka Offer Lab</a>
                    </div>
                </div>

                <?php if (!$items): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada halaman untuk dioptimasi</h3>
                        <p>Coba ganti filter, tambah artikel/landing page, atau pastikan Lead Tracking aktif agar sinyal halaman bisa terbaca.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-seo-money-list">
                        <?php foreach (array_slice($items, 0, 24) as $optimizer): ?>
                            <?php
                            $page = (array)($optimizer['item'] ?? []);
                            $metrics = (array)($optimizer['metrics'] ?? []);
                            $stage = (array)($optimizer['stage'] ?? []);
                            $cta = (array)($optimizer['cta_plan'] ?? []);
                            $links = (array)($optimizer['internal_links'] ?? []);
                            $trustPlans = (array)($optimizer['trust_plan'] ?? []);
                            $fixes = (array)($optimizer['content_fixes'] ?? []);
                            $decision = (array)($optimizer['decision'] ?? []);
                            $tone = (string)($stage['tone'] ?? 'monitor');
                            ?>
                            <article class="admin-seo-money-card is-<?= esc($tone); ?>">
                                <div class="admin-seo-journey-card__head">
                                    <div>
                                        <span class="admin-badge"><?= esc((string)($page['type_label'] ?? 'Halaman')); ?> · <?= esc($priorityLabel((string)($optimizer['priority'] ?? 'low'))); ?></span>
                                        <h3><?= esc((string)($page['title'] ?? 'Halaman SEO')); ?></h3>
                                        <p><?= esc((string)($page['page_path'] ?? parse_url((string)($page['url'] ?? '/'), PHP_URL_PATH))); ?></p>
                                    </div>
                                    <div class="admin-cta-result-card-score"><strong><?= (int)($optimizer['money_score'] ?? 0); ?></strong><span>/100</span></div>
                                </div>

                                <div class="admin-cta-result-stat-row">
                                    <span><strong><?= esc((string)($stage['label'] ?? 'Stage')); ?></strong> stage</span>
                                    <span><strong><?= (int)($metrics['clicks'] ?? 0); ?></strong> klik</span>
                                    <span><strong><?= (int)($metrics['leads'] ?? 0); ?></strong> lead</span>
                                    <span><strong><?= (int)($metrics['orders'] ?? 0); ?></strong> order</span>
                                    <span><strong><?= $formatPercent($metrics['lead_rate'] ?? 0); ?></strong> lead rate</span>
                                </div>

                                <div class="admin-seo-money-brief">
                                    <div class="admin-seo-money-panel admin-seo-money-panel--cta">
                                        <span>CTA & Offer</span>
                                        <h4><?= esc((string)($cta['headline'] ?? 'CTA halaman')); ?></h4>
                                        <p><?= esc((string)($cta['subheadline'] ?? 'Tambahkan CTA yang lebih jelas.')); ?></p>
                                        <ul>
                                            <li><strong>Tombol:</strong> <?= esc((string)($cta['cta_label'] ?? 'Tanya Rekomendasi')); ?></li>
                                            <li><strong>Placement:</strong> <?= esc((string)($cta['placement_label'] ?? 'CTA Placement')); ?></li>
                                            <?php if (!empty($cta['source_variant_title'])): ?><li><strong>Varian:</strong> <?= esc((string)$cta['source_variant_title']); ?></li><?php endif; ?>
                                        </ul>
                                        <div class="admin-seo-money-slot-list">
                                            <?php foreach ((array)($cta['slots'] ?? []) as $slot): ?><em><?= esc((string)$slot); ?></em><?php endforeach; ?>
                                        </div>
                                        <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($cta['admin_url'] ?? 'admin/cta-placement'))); ?>">Kerjakan CTA</a>
                                    </div>

                                    <div class="admin-seo-money-panel">
                                        <span>Internal Link</span>
                                        <h4>Link ke halaman paling relevan</h4>
                                        <?php if (!$links): ?>
                                            <p>Belum ada target internal link yang cukup relevan. Tambah produk/jasa/artikel pendukung dulu.</p>
                                        <?php else: ?>
                                            <ol>
                                                <?php foreach ($links as $link): ?>
                                                    <li>
                                                        <strong><?= esc((string)($link['title'] ?? 'Halaman')); ?></strong>
                                                        <small><?= esc((string)($link['type_label'] ?? 'Halaman')); ?> · <?= esc((string)($link['reason'] ?? 'Relevan untuk jalur lanjut.')); ?></small>
                                                        <?php if (!empty($link['url'])): ?><a href="<?= esc((string)$link['url']); ?>" target="_blank" rel="noopener">lihat</a><?php endif; ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ol>
                                        <?php endif; ?>
                                    </div>

                                    <div class="admin-seo-money-panel">
                                        <span>Trust Block</span>
                                        <h4>Proof yang perlu ditambahkan</h4>
                                        <ul>
                                            <?php foreach ($trustPlans as $plan): ?>
                                                <li>
                                                    <strong><?= esc((string)($plan['label'] ?? 'Trust')); ?></strong>
                                                    <small><?= esc((string)($plan['text'] ?? 'Tambahkan bukti agar pengunjung lebih yakin.')); ?></small>
                                                    <?php if (!empty($plan['need_attention'])): ?><b>belum kuat</b><?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/trust-conversion')); ?>">Kelola Trust Block</a>
                                    </div>
                                </div>

                                <details class="admin-seo-money-detail">
                                    <summary>Checklist content fix dan langkah eksekusi</summary>
                                    <div class="admin-seo-money-detail-grid">
                                        <div>
                                            <h4>Content fix</h4>
                                            <ul>
                                                <?php foreach ($fixes as $fix): ?>
                                                    <li><strong><?= esc((string)($fix['title'] ?? 'Perbaikan')); ?></strong><span><?= esc((string)($fix['text'] ?? '')); ?></span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        <div>
                                            <h4>Langkah eksekusi</h4>
                                            <ol>
                                                <?php foreach ((array)($optimizer['action_steps'] ?? []) as $step): ?>
                                                    <li><?= esc((string)$step); ?></li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                    </div>
                                </details>

                                <div class="admin-seo-money-action-row">
                                    <?php if (!empty($page['url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$page['url']); ?>" target="_blank" rel="noopener">Lihat Page</a><?php endif; ?>
                                    <?php if (!empty($page['edit_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$page['edit_url']); ?>">Edit Konten</a><?php endif; ?>
                                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($type))); ?>">Cek Journey</a>
                                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/cta-result-tracker')); ?>">Cek CTA Result</a>
                                </div>

                                <form method="post" action="<?= esc($baseUrl()); ?>" class="admin-cta-result-decision-form admin-seo-money-decision-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="decision">
                                    <input type="hidden" name="page_id" value="<?= esc((string)($optimizer['page_id'] ?? '')); ?>">
                                    <label><span>Status</span><select name="decision_status">
                                        <?php foreach ($decisionOptions as $value => $label): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= (string)($decision['status'] ?? 'queued') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                    <label><span>Catatan eksekusi</span><input type="text" name="owner_note" value="<?= esc((string)($decision['owner_note'] ?? '')); ?>" placeholder="Contoh: tambah CTA tengah artikel + link ke produk best seller"></label>
                                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Status</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-danger-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Reset Catatan</span>
                        <h2>Reset keputusan Money Page Optimizer</h2>
                        <p>Ini hanya menghapus catatan manual di halaman ini. Data SEO, Journey Map, CTA Result, Lead Tracking, order, dan payment tidak ikut dihapus.</p>
                    </div>
                    <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset catatan SEO Money Page Optimizer? Data tracking tidak akan dihapus.');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_decisions">
                        <button class="admin-btn admin-btn--danger" type="submit">Reset Catatan</button>
                    </form>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
