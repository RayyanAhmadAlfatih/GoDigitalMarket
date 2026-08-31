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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'decision') {
            seo_profit_update_decision(
                (string)($_POST['page_id'] ?? ''),
                (string)($_POST['decision_status'] ?? 'monitor'),
                (string)($_POST['decision_note'] ?? '')
            );
            redirect_302('admin/seo-profit-attribution?days=' . $days . '&type=' . rawurlencode($type) . '&message=' . rawurlencode('Keputusan SEO attribution berhasil disimpan.'));
        }

        if ($action === 'reset_decisions') {
            seo_profit_reset_decisions();
            redirect_302('admin/seo-profit-attribution?days=' . $days . '&type=' . rawurlencode($type) . '&message=' . rawurlencode('Catatan keputusan SEO attribution sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = function_exists('seo_profit_summary') ? seo_profit_summary($days, $type) : [];
$results = (array)($summary['results'] ?? []);
$queue = (array)($summary['action_queue'] ?? []);
$topPage = is_array($summary['top_page'] ?? null) ? (array)$summary['top_page'] : [];
$decisionOptions = function_exists('seo_profit_decision_options') ? seo_profit_decision_options() : ['monitor' => 'Pantau dulu'];

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-profit-attribution-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv' && function_exists('seo_profit_export_csv')) {
    seo_profit_export_csv($results);
}

$formatPercent = static function (mixed $value): string {
    return rtrim(rtrim(number_format((float)$value, 1, ',', '.'), '0'), ',') . '%';
};

$rangeUrl = static function (int $rangeDays) use ($type): string {
    return url('admin/seo-profit-attribution?days=' . $rangeDays . '&type=' . rawurlencode($type));
};

$typeUrl = static function (string $targetType) use ($days): string {
    return url('admin/seo-profit-attribution?days=' . $days . '&type=' . rawurlencode($targetType));
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'SEO Profit Attribution Bridge - Admin',
    'description' => 'Bridge untuk membaca kontribusi artikel dan halaman SEO ke klik CTA, lead, order, payment, dan campaign profit dari data Lead Tracking existing.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-profit-shell">
    <section class="admin-hero admin-seo-profit-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>SEO Profit Attribution Bridge</h1>
                <p>Jawab pertanyaan penting: artikel mana yang mendatangkan lead, order datang dari halaman mana, dan halaman SEO mana yang perlu dipoles agar lebih dekat ke profit.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/leads')); ?>">Tracking Lead</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/universal-seo')); ?>">Universal SEO</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-result-tracker')); ?>">CTA Result</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey')); ?>">Journey Map</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Money Page</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-action-dashboard')); ?>">Profit Action</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview admin-seo-profit-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Attribution Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['attribution_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['attribution_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>SEO ke Profit</h2>
                    <p><?= esc((string)($summary['top_focus'] ?? 'Pantau kontribusi halaman SEO dari data Lead Tracking.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Sinyal <?= (int)$days; ?> hari</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($summary['pages_with_signal'] ?? 0); ?></strong> page bersinyal</span>
                        <span><strong><?= (int)($summary['total_clicks'] ?? 0); ?></strong> klik</span>
                        <span><strong><?= (int)($summary['total_leads'] ?? 0); ?></strong> lead</span>
                        <span><strong><?= (int)($summary['total_orders'] ?? 0); ?></strong> order/payment</span>
                        <span><strong><?= $formatPercent($summary['lead_rate'] ?? 0); ?></strong> lead rate</span>
                    </div>
                    <p>Data ini memakai <strong>Tracking Lead existing</strong>. Jadi tidak ada tracking dobel, hanya bridge attribution untuk keputusan SEO dan profit.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Top SEO Page</span>
                    <?php if ($topPage): ?>
                        <?php $topItem = (array)($topPage['item'] ?? []); $topMetrics = (array)($topPage['metrics'] ?? []); ?>
                        <h2><?= esc((string)($topItem['title'] ?? 'Halaman SEO')); ?></h2>
                        <p><?= esc((string)($topItem['type_label'] ?? 'Halaman')); ?> · <?= (int)($topMetrics['leads'] ?? 0); ?> lead · <?= (int)($topMetrics['orders'] ?? 0); ?> order/payment</p>
                        <div class="admin-cta-result-export-row">
                            <?php if (!empty($topItem['url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topItem['url']); ?>" target="_blank" rel="noopener">Lihat Page</a><?php endif; ?>
                            <?php if (!empty($topItem['edit_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topItem['edit_url']); ?>">Edit</a><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <h2>Belum ada halaman terbaca</h2>
                        <p>Setelah ada artikel/landing page dan event Lead Tracking, halaman terbaik akan tampil di sini.</p>
                    <?php endif; ?>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-profit-attribution?days=' . $days . '&type=' . rawurlencode($type) . '&export=csv')); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-profit-attribution?days=' . $days . '&type=' . rawurlencode($type) . '&export=json')); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Attribution</span>
                        <h2>Rentang dan tipe halaman</h2>
                        <p>Pilih periode dan tipe halaman SEO yang ingin dibaca kontribusinya.</p>
                    </div>
                </div>
                <div class="admin-seo-profit-filter-row">
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($allowedDays as $rangeDays): ?>
                            <a class="<?= $days === $rangeDays ? 'is-active' : ''; ?>" href="<?= esc($rangeUrl($rangeDays)); ?>"><?= (int)$rangeDays; ?> hari</a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
                            <a class="<?= $type === (string)$typeKey ? 'is-active' : ''; ?>" href="<?= esc($typeUrl((string)$typeKey)); ?>"><?= esc((string)$typeLabel); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="admin-card admin-cta-result-bridge-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Alur Attribution</span>
                        <h2>Bukan tracker baru, tapi pembaca hasil SEO</h2>
                        <p>Bridge ini menjaga menu Tracking Lead tetap sebagai sumber data utama, lalu menerjemahkannya menjadi insight halaman SEO.</p>
                    </div>
                </div>
                <div class="admin-cta-result-flow">
                    <div><strong>Artikel / SEO Page</strong><span>Halaman yang menangkap trafik organik dan mengedukasi calon pembeli.</span></div>
                    <div><strong>Tracking Lead</strong><span>Mencatat klik, WA, form, checkout, order, dan payment dari halaman website.</span></div>
                    <div><strong>CTA Result</strong><span>Membaca CTA/offer mana yang mulai bergerak dari placement.</span></div>
                    <div><strong>SEO Profit</strong><span>Menjawab halaman mana yang membawa lead/order dan action apa berikutnya.</span></div>
                </div>
            </section>

            <section class="admin-card admin-seo-profit-queue-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Action Queue</span>
                        <h2>Prioritas SEO yang paling dekat ke profit</h2>
                        <p>Kerjakan dari prioritas tertinggi agar update SEO tidak berhenti di traffic saja.</p>
                    </div>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-playbook')); ?>">Buka Profit Playbook</a>
                </div>
                <?php if (!$queue): ?>
                    <div class="admin-empty-state"><p>Belum ada queue. Setelah ada halaman SEO dan Lead Tracking, prioritas akan muncul otomatis.</p></div>
                <?php else: ?>
                    <div class="admin-seo-profit-queue">
                        <?php foreach ($queue as $action): ?>
                            <?php $rec = (array)($action['recommendation'] ?? []); $metrics = (array)($action['metrics'] ?? []); ?>
                            <article>
                                <strong><?= esc((string)($action['title'] ?? 'Halaman SEO')); ?></strong>
                                <span><?= esc((string)($action['type_label'] ?? 'Halaman')); ?> · Prioritas <?= (int)($action['priority'] ?? 0); ?>/100 · <?= (int)($metrics['leads'] ?? 0); ?> lead · <?= (int)($metrics['orders'] ?? 0); ?> order</span>
                                <p><b><?= esc((string)($rec['title'] ?? 'Pantau dulu')); ?></b> — <?= esc((string)($rec['text'] ?? 'Pantau sinyal halaman.')); ?></p>
                                <div>
                                    <?php if (!empty($action['url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$action['url']); ?>" target="_blank" rel="noopener">Lihat</a><?php endif; ?>
                                    <?php if (!empty($action['edit_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$action['edit_url']); ?>">Edit</a><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-table-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Attribution Table</span>
                        <h2>Artikel dan halaman SEO yang terbaca</h2>
                        <p>Urutan diprioritaskan dari halaman yang paling punya sinyal klik, lead, order, dan potensi profit.</p>
                    </div>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/universal-seo')); ?>">Audit SEO</a>
                </div>

                <?php if (!$results): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada halaman SEO</h3>
                        <p>Tambahkan artikel, landing page, atau SEO landing dulu. Setelah ada event dari Tracking Lead, kontribusinya akan muncul di sini.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-seo-profit-grid">
                        <?php foreach (array_slice($results, 0, 24) as $result): ?>
                            <?php
                            $item = (array)($result['item'] ?? []);
                            $metrics = (array)($result['metrics'] ?? []);
                            $recommendation = (array)($result['recommendation'] ?? []);
                            $decision = (array)($result['decision'] ?? []);
                            $tone = (string)($recommendation['tone'] ?? 'monitor');
                            ?>
                            <article class="admin-cta-result-card is-<?= esc($tone); ?>">
                                <div class="admin-cta-result-card-head">
                                    <div>
                                        <span class="admin-badge"><?= esc((string)($item['type_label'] ?? 'Halaman')); ?> · SEO <?= (int)($item['score'] ?? 0); ?>/100</span>
                                        <h3><?= esc((string)($item['title'] ?? 'Halaman SEO')); ?></h3>
                                        <p><?= esc((string)($item['page_path'] ?? '/')); ?></p>
                                    </div>
                                    <div class="admin-cta-result-card-score"><strong><?= (int)($result['profit_score'] ?? 0); ?></strong><span>/100</span></div>
                                </div>

                                <div class="admin-cta-result-stat-row">
                                    <span><strong><?= (int)($metrics['clicks'] ?? 0); ?></strong> klik</span>
                                    <span><strong><?= (int)($metrics['leads'] ?? 0); ?></strong> lead</span>
                                    <span><strong><?= (int)($metrics['orders'] ?? 0); ?></strong> order</span>
                                    <span><strong><?= $formatPercent($metrics['lead_rate'] ?? 0); ?></strong> lead rate</span>
                                </div>

                                <div class="admin-cta-result-recommendation">
                                    <strong><?= esc((string)($recommendation['title'] ?? 'Pantau dulu')); ?></strong>
                                    <p><?= esc((string)($recommendation['text'] ?? 'Pantau sinyal halaman beberapa hari lagi.')); ?></p>
                                </div>

                                <div class="admin-cta-result-meta">
                                    <span>Event: <strong><?= (int)($metrics['events'] ?? 0); ?></strong></span>
                                    <span>Payment: <strong><?= (int)($metrics['payments'] ?? 0); ?></strong></span>
                                    <span>Terakhir: <strong><?= !empty($metrics['last_event_at']) ? esc(date('d M H:i', strtotime((string)$metrics['last_event_at']))) : 'Belum ada'; ?></strong></span>
                                </div>

                                <?php if (!empty($metrics['recent_events'])): ?>
                                    <details class="admin-cta-result-events">
                                        <summary>Lihat event attribution</summary>
                                        <div>
                                            <?php foreach ((array)$metrics['recent_events'] as $event): ?>
                                                <p><strong><?= esc(date('d M H:i', (int)($event['_ts'] ?? time()))); ?></strong> · <?= esc((string)($event['_event_group_label'] ?? $event['channel'] ?? 'event')); ?> · <?= esc((string)($event['label'] ?? '-')); ?> <small><?= esc((string)($event['page_path'] ?? '/')); ?> · match <?= (int)($event['_match_score'] ?? 0); ?></small></p>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>

                                <div class="admin-cta-result-export-row">
                                    <?php if (!empty($item['url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$item['url']); ?>" target="_blank" rel="noopener">Lihat Page</a><?php endif; ?>
                                    <?php if (!empty($item['edit_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$item['edit_url']); ?>">Edit SEO</a><?php endif; ?>
                                </div>

                                <form method="post" action="<?= esc(url('admin/seo-profit-attribution?days=' . $days . '&type=' . rawurlencode($type))); ?>" class="admin-cta-result-decision-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="decision">
                                    <input type="hidden" name="page_id" value="<?= esc((string)($item['page_id'] ?? '')); ?>">
                                    <label><span>Keputusan admin</span><select name="decision_status">
                                        <?php foreach ($decisionOptions as $value => $label): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= (string)($decision['status'] ?? 'monitor') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                    <label><span>Catatan</span><input type="text" name="decision_note" value="<?= esc((string)($decision['note'] ?? '')); ?>" placeholder="Contoh: tambah CTA bawah artikel / scale ke campaign"></label>
                                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Keputusan</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-danger-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Reset Keputusan</span>
                        <h2>Reset catatan keputusan SEO attribution</h2>
                        <p>Ini hanya menghapus catatan manual di halaman ini. Data Tracking Lead, artikel, SEO, CTA, order, dan payment tidak dihapus.</p>
                    </div>
                    <form method="post" action="<?= esc(url('admin/seo-profit-attribution?days=' . $days . '&type=' . rawurlencode($type))); ?>" onsubmit="return confirm('Reset catatan SEO Profit Attribution? Data Tracking Lead tidak akan dihapus.');">
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
