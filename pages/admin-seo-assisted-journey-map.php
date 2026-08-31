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
            seo_journey_update_decision(
                (string)($_POST['page_id'] ?? ''),
                (string)($_POST['decision_status'] ?? 'monitor'),
                (string)($_POST['decision_note'] ?? '')
            );
            redirect_302('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($type) . '&message=' . rawurlencode('Keputusan journey berhasil disimpan.'));
        }

        if ($action === 'reset_decisions') {
            seo_journey_reset_decisions();
            redirect_302('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($type) . '&message=' . rawurlencode('Catatan journey sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = function_exists('seo_journey_summary') ? seo_journey_summary($days, $type) : [];
$journeys = (array)($summary['journeys'] ?? []);
$totals = (array)($summary['totals'] ?? []);
$stageCounts = (array)($summary['stage_counts'] ?? []);
$bottlenecks = (array)($summary['bottlenecks'] ?? []);
$topJourney = is_array($summary['top_journey'] ?? null) ? (array)$summary['top_journey'] : [];
$decisionOptions = function_exists('seo_journey_decision_options') ? seo_journey_decision_options() : ['monitor' => 'Pantau dulu'];

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-assisted-journey-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv' && function_exists('seo_journey_export_csv')) {
    seo_journey_export_csv($journeys);
}

$formatPercent = static function (mixed $value): string {
    return rtrim(rtrim(number_format((float)$value, 1, ',', '.'), '0'), ',') . '%';
};

$rangeUrl = static function (int $rangeDays) use ($type): string {
    return url('admin/seo-assisted-journey?days=' . $rangeDays . '&type=' . rawurlencode($type));
};

$typeUrl = static function (string $targetType) use ($days): string {
    return url('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($targetType));
};

$bottleneckLabel = static function (string $key): string {
    return match ($key) {
        'no_signal' => 'Belum ada sinyal',
        'view_no_click' => 'Dibaca, belum diklik',
        'click_no_lead' => 'Klik ada, lead belum',
        'lead_no_order' => 'Lead ada, order belum',
        'order_ready' => 'Sudah sampai order',
        'seo_needs_refresh' => 'SEO perlu dipoles',
        default => 'Pantau dulu',
    };
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'SEO Assisted Conversion Journey Map - Admin',
    'description' => 'Peta journey dari halaman SEO/artikel ke klik CTA, lead, order, dan payment dengan membaca Tracking Lead existing.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-journey-shell">
    <section class="admin-hero admin-seo-journey-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>SEO Assisted Conversion Journey Map</h1>
                <p>Lihat alur dari artikel/halaman SEO → klik CTA → lead → order/payment. Tracking tetap memakai Lead Tracking existing, jadi tidak ada sistem dobel.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-profit-attribution')); ?>">SEO Profit</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-result-tracker')); ?>">CTA Result</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/leads')); ?>">Tracking Lead</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Money Page Optimizer</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-playbook')); ?>">Profit Playbook</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview admin-seo-journey-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Journey Score</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['average_journey_score'] ?? 0); ?>;">
                        <strong><?= (int)($summary['average_journey_score'] ?? 0); ?></strong><span>/100</span>
                    </div>
                    <h2>SEO → Profit Journey</h2>
                    <p><?= esc((string)($summary['top_focus'] ?? 'Baca alur kontribusi SEO ke CTA, lead, order, dan payment.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Sinyal <?= (int)$days; ?> hari</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($summary['total_journeys'] ?? 0); ?></strong> journey</span>
                        <span><strong><?= (int)($totals['events'] ?? 0); ?></strong> event</span>
                        <span><strong><?= (int)($totals['clicks'] ?? 0); ?></strong> klik CTA</span>
                        <span><strong><?= (int)($totals['leads'] ?? 0); ?></strong> lead</span>
                        <span><strong><?= (int)($totals['orders'] ?? 0) + (int)($totals['payments'] ?? 0); ?></strong> order/payment</span>
                    </div>
                    <p>Angka di sini dibaca dari <strong>Tracking Lead existing</strong> dan hasil bridge SEO Profit. Menu ini hanya menerjemahkan journey agar action lebih jelas.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Top Journey</span>
                    <?php if ($topJourney): ?>
                        <?php $topItem = (array)($topJourney['item'] ?? []); $topMetrics = (array)($topJourney['metrics'] ?? []); ?>
                        <h2><?= esc((string)($topItem['title'] ?? 'Halaman SEO')); ?></h2>
                        <p><?= esc((string)($topItem['type_label'] ?? 'Halaman')); ?> · <?= (int)($topMetrics['clicks'] ?? 0); ?> klik · <?= (int)($topMetrics['leads'] ?? 0); ?> lead · <?= (int)($topMetrics['orders'] ?? 0); ?> order</p>
                        <div class="admin-cta-result-export-row">
                            <?php if (!empty($topItem['url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topItem['url']); ?>" target="_blank" rel="noopener">Lihat Page</a><?php endif; ?>
                            <?php if (!empty($topItem['edit_url'])): ?><a class="admin-btn admin-btn--light" href="<?= esc((string)$topItem['edit_url']); ?>">Edit</a><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <h2>Belum ada journey terbaca</h2>
                        <p>Tambahkan artikel/halaman SEO, pasang CTA, lalu pastikan Lead Tracking aktif.</p>
                    <?php endif; ?>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($type) . '&export=csv')); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($type) . '&export=json')); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Journey</span>
                        <h2>Rentang dan tipe halaman</h2>
                        <p>Pilih periode dan tipe halaman untuk melihat perjalanan SEO ke profit.</p>
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

            <section class="admin-card admin-seo-journey-map-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Journey Stage Map</span>
                        <h2>Peta alur SEO ke conversion</h2>
                        <p>Stage ini membantu admin melihat macetnya di mana: halaman, CTA, lead, atau closing.</p>
                    </div>
                </div>
                <div class="admin-seo-journey-stage-grid">
                    <article>
                        <span>1</span>
                        <strong>SEO Page</strong>
                        <p><?= (int)($stageCounts['seo_page'] ?? 0); ?> halaman punya sinyal.</p>
                    </article>
                    <article>
                        <span>2</span>
                        <strong>CTA Click</strong>
                        <p><?= (int)($stageCounts['cta_click'] ?? 0); ?> halaman memicu klik.</p>
                    </article>
                    <article>
                        <span>3</span>
                        <strong>Lead</strong>
                        <p><?= (int)($stageCounts['lead'] ?? 0); ?> halaman menghasilkan lead.</p>
                    </article>
                    <article>
                        <span>4</span>
                        <strong>Order / Payment</strong>
                        <p><?= (int)($stageCounts['order_payment'] ?? 0); ?> halaman sampai order/payment.</p>
                    </article>
                </div>
            </section>

            <section class="admin-card admin-seo-journey-bottleneck-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Bottleneck</span>
                        <h2>Macetnya paling banyak di mana?</h2>
                        <p>Gunakan ringkasan ini untuk menentukan jenis pekerjaan: SEO, CTA, offer, trust, follow-up, atau scale.</p>
                    </div>
                </div>
                <?php if (!$bottlenecks): ?>
                    <div class="admin-empty-state"><p>Belum ada bottleneck terbaca.</p></div>
                <?php else: ?>
                    <div class="admin-seo-journey-bottleneck-grid">
                        <?php foreach ($bottlenecks as $key => $count): ?>
                            <article>
                                <strong><?= esc($bottleneckLabel((string)$key)); ?></strong>
                                <span><?= (int)$count; ?> halaman</span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-bridge-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Bridge Aman</span>
                        <h2>Lead Tracking tetap jadi sumber data utama</h2>
                        <p>Journey Map ini tidak membuat tracker baru. Ia membaca hasil dari Tracking Lead, SEO Profit Attribution, dan CTA Result agar admin bisa mengambil keputusan lebih cepat.</p>
                    </div>
                </div>
                <div class="admin-cta-result-flow admin-seo-journey-flow">
                    <div><strong>Artikel / SEO Page</strong><span>Halaman yang menangkap traffic organik.</span></div>
                    <div><strong>CTA Placement</strong><span>Offer dan tombol yang diarahkan ke aksi.</span></div>
                    <div><strong>Tracking Lead</strong><span>Data klik, form, WA, checkout, order, dan payment existing.</span></div>
                    <div><strong>Journey Decision</strong><span>Keputusan lanjut: tambah CTA, perbaiki offer, follow-up, atau scale.</span></div>
                </div>
            </section>

            <section class="admin-card admin-seo-journey-list-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Journey Detail</span>
                        <h2>Perjalanan per halaman SEO</h2>
                        <p>Urutan diprioritaskan dari halaman yang paling dekat ke lead/order.</p>
                    </div>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer?days=' . $days . '&type=' . rawurlencode($type))); ?>">Buka Money Page Optimizer</a>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-profit-attribution')); ?>">Buka SEO Profit</a>
                </div>

                <?php if (!$journeys): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada journey</h3>
                        <p>Tambahkan artikel/landing page/produk, pasang CTA, lalu cek ulang setelah ada aktivitas pengunjung.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-seo-journey-list">
                        <?php foreach (array_slice($journeys, 0, 24) as $journey): ?>
                            <?php
                            $item = (array)($journey['item'] ?? []);
                            $metrics = (array)($journey['metrics'] ?? []);
                            $bottleneck = (array)($journey['bottleneck'] ?? []);
                            $decision = (array)($journey['decision'] ?? []);
                            $tone = (string)($bottleneck['tone'] ?? 'monitor');
                            ?>
                            <article class="admin-seo-journey-card is-<?= esc($tone); ?>">
                                <div class="admin-seo-journey-card__head">
                                    <div>
                                        <span class="admin-badge"><?= esc((string)($item['type_label'] ?? 'Halaman')); ?> · SEO <?= (int)($item['score'] ?? 0); ?>/100</span>
                                        <h3><?= esc((string)($item['title'] ?? 'Halaman SEO')); ?></h3>
                                        <p><?= esc((string)($item['page_path'] ?? '/')); ?></p>
                                    </div>
                                    <div class="admin-cta-result-card-score"><strong><?= (int)($journey['journey_score'] ?? 0); ?></strong><span>/100</span></div>
                                </div>

                                <div class="admin-seo-journey-stages" aria-label="Journey stages">
                                    <?php foreach ((array)($journey['stages'] ?? []) as $stage): ?>
                                        <div class="is-<?= esc((string)($stage['status'] ?? 'empty')); ?>">
                                            <strong><?= esc((string)($stage['label'] ?? 'Stage')); ?></strong>
                                            <span><?= (int)($stage['count'] ?? 0); ?></span>
                                            <small><?= esc((string)($stage['hint'] ?? '')); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="admin-cta-result-stat-row">
                                    <span><strong><?= (int)($metrics['events'] ?? 0); ?></strong> event</span>
                                    <span><strong><?= (int)($metrics['clicks'] ?? 0); ?></strong> klik</span>
                                    <span><strong><?= (int)($metrics['leads'] ?? 0); ?></strong> lead</span>
                                    <span><strong><?= (int)($metrics['orders'] ?? 0); ?></strong> order</span>
                                    <span><strong><?= $formatPercent($metrics['lead_rate'] ?? 0); ?></strong> lead rate</span>
                                </div>

                                <div class="admin-seo-journey-next-action">
                                    <span><?= esc((string)($bottleneck['label'] ?? 'Pantau dulu')); ?></span>
                                    <h4><?= esc((string)($bottleneck['title'] ?? 'Pantau journey')); ?></h4>
                                    <p><?= esc((string)($bottleneck['text'] ?? 'Pantau sinyal halaman ini.')); ?></p>
                                    <div>
                                        <?php if (!empty($item['url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$item['url']); ?>" target="_blank" rel="noopener">Lihat Page</a><?php endif; ?>
                                        <?php if (!empty($item['edit_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$item['edit_url']); ?>">Edit Konten</a><?php endif; ?>
                                        <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($bottleneck['action_url'] ?? 'admin/seo-profit-attribution'))); ?>">Kerjakan Action</a>
                                    </div>
                                </div>

                                <form method="post" action="<?= esc(url('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($type))); ?>" class="admin-cta-result-decision-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="decision">
                                    <input type="hidden" name="page_id" value="<?= esc((string)($journey['page_id'] ?? '')); ?>">
                                    <label><span>Keputusan</span><select name="decision_status">
                                        <?php foreach ($decisionOptions as $value => $label): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= (string)($decision['status'] ?? 'monitor') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                        <?php endforeach; ?>
                                    </select></label>
                                    <label><span>Catatan</span><input type="text" name="decision_note" value="<?= esc((string)($decision['note'] ?? '')); ?>" placeholder="Contoh: tambah CTA tengah artikel / follow-up lead dari halaman ini"></label>
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
                        <h2>Reset catatan Journey Map</h2>
                        <p>Ini hanya menghapus catatan manual di halaman ini. Data Tracking Lead, SEO, CTA, order, dan payment tidak ikut dihapus.</p>
                    </div>
                    <form method="post" action="<?= esc(url('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($type))); ?>" onsubmit="return confirm('Reset catatan Journey Map? Data Tracking Lead tidak akan dihapus.');">
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
