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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'decision') {
            cta_result_update_decision(
                (string)($_POST['deployment_id'] ?? ''),
                (string)($_POST['decision_status'] ?? 'monitor'),
                (string)($_POST['decision_note'] ?? '')
            );
            redirect_302('admin/cta-result-tracker?days=' . $days . '&message=' . rawurlencode('Keputusan hasil CTA berhasil disimpan.'));
        }

        if ($action === 'reset_decisions') {
            cta_result_reset_decisions();
            redirect_302('admin/cta-result-tracker?days=' . $days . '&message=' . rawurlencode('Keputusan CTA Result Tracker sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = function_exists('cta_result_bridge_summary') ? cta_result_bridge_summary($days) : [];
$results = (array)($summary['deployment_results'] ?? []);
$opportunities = (array)($summary['unmatched_opportunities'] ?? []);
$decisionOptions = function_exists('cta_result_decision_options') ? cta_result_decision_options() : [];

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="cta-result-bridge-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv' && function_exists('cta_result_export_csv')) {
    cta_result_export_csv($results);
}

$formatPercent = static function (mixed $value): string {
    return rtrim(rtrim(number_format((float)$value, 1, ',', '.'), '0'), ',') . '%';
};

$rangeUrl = static function (int $rangeDays): string {
    return url('admin/cta-result-tracker?days=' . $rangeDays);
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'CTA Result Tracker & Lead Tracking Bridge - Admin',
    'description' => 'Membaca hasil CTA Placement dari data Lead Tracking yang sudah tersedia tanpa membuat sistem tracking tambahan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-cta-result-shell">
    <section class="admin-hero admin-cta-result-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>CTA Result Tracker & Lead Tracking Bridge</h1>
                <p>Baca hasil CTA yang sudah dipasang dari Lead Tracking, lalu tentukan mana yang perlu dipertahankan, diperbaiki, atau di-scale.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/leads')); ?>">Tracking Lead</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement')); ?>">CTA Placement</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/offer-cta-testing')); ?>">Offer Lab</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-profit-attribution')); ?>">SEO Profit</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey')); ?>">Journey Map</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Money Page</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Bridge Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['bridge_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['bridge_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>Hasil CTA dari Lead Tracking</h2>
                    <p><?= esc((string)($summary['top_focus'] ?? 'Pantau hasil CTA dari data Lead Tracking.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Sinyal <?= (int)$days; ?> hari</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($summary['total_clicks'] ?? 0); ?></strong> klik/sinyal</span>
                        <span><strong><?= (int)($summary['total_leads'] ?? 0); ?></strong> lead</span>
                        <span><strong><?= (int)($summary['total_orders'] ?? 0); ?></strong> order/payment</span>
                        <span><strong><?= $formatPercent($summary['lead_rate'] ?? 0); ?></strong> lead rate</span>
                    </div>
                    <p>Data ini membaca log <strong>Tracking Lead</strong>, bukan membuat tracker baru. Manual metric di Offer Lab tetap jadi backup.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Bridge Health</span>
                    <div class="admin-cta-result-health-list">
                        <div><strong><?= !empty($summary['tracking_enabled']) ? 'Aktif' : 'Nonaktif'; ?></strong><span>Lead Tracking</span></div>
                        <div><strong><?= (int)($summary['total_deployments'] ?? 0); ?></strong><span>CTA Placement</span></div>
                        <div><strong><?= (int)($summary['deployments_with_signal'] ?? 0); ?></strong><span>Placement bersinyal</span></div>
                        <div><strong><?= (int)($summary['needs_action'] ?? 0); ?></strong><span>Butuh aksi</span></div>
                    </div>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-result-tracker?days=' . $days . '&export=csv')); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-result-tracker?days=' . $days . '&export=json')); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Cepat</span>
                        <h2>Rentang hasil</h2>
                        <p>Pilih periode pembacaan dari Lead Tracking.</p>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($allowedDays as $rangeDays): ?>
                            <a class="<?= $days === $rangeDays ? 'is-active' : ''; ?>" href="<?= esc($rangeUrl($rangeDays)); ?>"><?= (int)$rangeDays; ?> hari</a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="admin-card admin-cta-result-bridge-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Audit Anti Tumpang Tindih</span>
                        <h2>Alur data yang dipakai</h2>
                        <p>Halaman ini sengaja menjadi bridge, bukan pengganti menu Tracking Lead.</p>
                    </div>
                </div>
                <div class="admin-cta-result-flow">
                    <div><strong>Tracking Lead</strong><span>Sumber data klik, WA, form, checkout, order, dan payment.</span></div>
                    <div><strong>Offer Lab</strong><span>Tempat membuat variasi offer, headline, proof, dan tombol CTA.</span></div>
                    <div><strong>CTA Placement</strong><span>Tempat menentukan winner dipasang ke halaman mana.</span></div>
                    <div><strong>CTA Result</strong><span>Bridge keputusan: lanjut, perbaiki, scale, atau ganti.</span></div>
                </div>
            </section>

            <section class="admin-card admin-cta-result-table-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Hasil per Placement</span>
                        <h2>CTA yang sudah direncanakan/dipasang</h2>
                        <p>Prioritaskan CTA dengan klik tinggi tapi lead rendah, atau CTA yang sudah menghasilkan lead/order.</p>
                    </div>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement')); ?>">Kelola Placement</a>
                </div>

                <?php if (!$results): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada CTA Placement</h3>
                        <p>Buat rencana di CTA Placement Assistant dari winner Offer Lab dulu, lalu hasilnya akan terbaca di sini setelah ada traffic.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-cta-result-grid">
                        <?php foreach ($results as $result): ?>
                            <?php
                            $deployment = (array)($result['deployment'] ?? []);
                            $metrics = (array)($result['metrics'] ?? []);
                            $recommendation = (array)($result['recommendation'] ?? []);
                            $decision = (array)($result['decision'] ?? []);
                            $tone = (string)($recommendation['tone'] ?? 'monitor');
                            ?>
                            <article class="admin-cta-result-card is-<?= esc($tone); ?>">
                                <div class="admin-cta-result-card-head">
                                    <div>
                                        <span class="admin-badge"><?= esc((string)($deployment['placement_label'] ?? 'Placement')); ?> · <?= esc((string)($deployment['status'] ?? 'planned')); ?></span>
                                        <h3><?= esc((string)($deployment['variant_title'] ?? 'Offer CTA')); ?></h3>
                                        <p><?= esc((string)($deployment['headline'] ?? 'Headline belum diisi.')); ?></p>
                                    </div>
                                    <div class="admin-cta-result-card-score"><strong><?= (int)($result['result_score'] ?? 0); ?></strong><span>/100</span></div>
                                </div>

                                <div class="admin-cta-result-stat-row">
                                    <span><strong><?= (int)($metrics['clicks'] ?? 0); ?></strong> klik</span>
                                    <span><strong><?= (int)($metrics['leads'] ?? 0); ?></strong> lead</span>
                                    <span><strong><?= (int)($metrics['orders'] ?? 0); ?></strong> order</span>
                                    <span><strong><?= $formatPercent($metrics['lead_rate'] ?? 0); ?></strong> lead rate</span>
                                </div>

                                <div class="admin-cta-result-recommendation">
                                    <strong><?= esc((string)($recommendation['title'] ?? 'Pantau dulu')); ?></strong>
                                    <p><?= esc((string)($recommendation['text'] ?? 'Pantau sinyal CTA beberapa hari lagi.')); ?></p>
                                </div>

                                <div class="admin-cta-result-meta">
                                    <span>CTA: <strong><?= esc((string)($deployment['cta_label'] ?? 'CTA')); ?></strong></span>
                                    <span>Target: <strong><?= esc((string)($deployment['cta_url'] ?? '-')); ?></strong></span>
                                    <span>Terakhir: <strong><?= !empty($metrics['last_event_at']) ? esc(date('d M H:i', strtotime((string)$metrics['last_event_at']))) : 'Belum ada'; ?></strong></span>
                                </div>

                                <?php if (!empty($metrics['recent_events'])): ?>
                                    <details class="admin-cta-result-events">
                                        <summary>Lihat event yang cocok</summary>
                                        <div>
                                            <?php foreach ((array)$metrics['recent_events'] as $event): ?>
                                                <p><strong><?= esc(date('d M H:i', (int)($event['_ts'] ?? time()))); ?></strong> · <?= esc((string)($event['_event_group_label'] ?? $event['channel'] ?? 'event')); ?> · <?= esc((string)($event['label'] ?? '-')); ?> <small><?= esc((string)($event['page_path'] ?? '/')); ?></small></p>
                                            <?php endforeach; ?>
                                        </div>
                                    </details>
                                <?php endif; ?>

                                <form method="post" action="<?= esc(url('admin/cta-result-tracker?days=' . $days)); ?>" class="admin-cta-result-decision-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="decision">
                                    <input type="hidden" name="deployment_id" value="<?= esc((string)($deployment['id'] ?? '')); ?>">
                                    <label><span>Keputusan admin</span><select name="decision_status">
                                        <?php foreach ($decisionOptions as $value => $label): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= (string)($decision['status'] ?? 'monitor') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                    <label><span>Catatan</span><input type="text" name="decision_note" value="<?= esc((string)($decision['note'] ?? '')); ?>" placeholder="Contoh: pertahankan 7 hari lagi / ganti headline"></label>
                                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Keputusan</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-opportunity-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Opportunity dari Lead Tracking</span>
                        <h2>Sinyal lead yang belum terhubung ke CTA Placement</h2>
                        <p>Ini membantu menemukan halaman/CTA yang sebenarnya sudah bergerak, tapi belum masuk pipeline placement.</p>
                    </div>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/leads')); ?>">Buka Tracking Lead</a>
                </div>

                <?php if (!$opportunities): ?>
                    <div class="admin-empty-state"><p>Belum ada opportunity terpisah. Nanti setelah ada event Lead Tracking, halaman ini akan menampilkan sinyal yang perlu dimasukkan ke pipeline CTA.</p></div>
                <?php else: ?>
                    <div class="admin-cta-result-opportunity-list">
                        <?php foreach ($opportunities as $item): ?>
                            <div>
                                <strong><?= esc((string)($item['label'] ?? 'CTA')); ?></strong>
                                <span><?= esc((string)($item['page_path'] ?? '/')); ?> · <?= esc((string)($item['channel'] ?? 'click')); ?></span>
                                <em><?= (int)($item['events'] ?? 0); ?> event · <?= (int)($item['leads'] ?? 0); ?> lead · <?= (int)($item['orders'] ?? 0); ?> order</em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-danger-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Reset Keputusan</span>
                        <h2>Reset catatan keputusan CTA</h2>
                        <p>Ini hanya menghapus keputusan manual di halaman ini. Data Tracking Lead, Offer Lab, dan CTA Placement tidak dihapus.</p>
                    </div>
                    <form method="post" action="<?= esc(url('admin/cta-result-tracker?days=' . $days)); ?>" onsubmit="return confirm('Reset keputusan CTA Result Tracker? Data lead tracking tidak akan dihapus.');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_decisions">
                        <button class="admin-btn admin-btn--danger" type="submit">Reset Keputusan</button>
                    </form>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
