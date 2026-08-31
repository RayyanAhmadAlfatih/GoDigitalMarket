<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';

$params = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['days' => 30, 'range' => '30', 'filters' => []];
$rangeLabel = function_exists('report_range_label') ? report_range_label((string)$params['range'], $params) : '30 hari terakhir';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        $id = (string)($_POST['id'] ?? '');

        if ($action === 'complete') {
            profit_action_mark_completed($id, true);
            redirect_302('admin/profit-action-dashboard?message=' . rawurlencode('Action profit ditandai selesai untuk hari ini.'));
        }

        if ($action === 'undo') {
            profit_action_mark_completed($id, false);
            redirect_302('admin/profit-action-dashboard?message=' . rawurlencode('Status action profit dikembalikan.'));
        }

        if ($action === 'reset_today') {
            profit_action_reset_today();
            redirect_302('admin/profit-action-dashboard?message=' . rawurlencode('Checklist profit hari ini sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = function_exists('profit_action_dashboard_summary') ? profit_action_dashboard_summary((int)$params['days'], (array)$params['filters']) : [];
$focus = (string)($_GET['focus'] ?? 'all');
$allowedFocus = ['all', 'money_leak', 'follow_up', 'seo_to_sales', 'trust_cta', 'setup', 'scale'];
if (!in_array($focus, $allowedFocus, true)) {
    $focus = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$showDone = (string)($_GET['show_done'] ?? '1') !== '0';
$actions = function_exists('profit_action_filter_actions') ? profit_action_filter_actions((array)($summary['actions'] ?? []), $focus, $q, $showDone) : (array)($summary['actions'] ?? []);
$report = (array)($summary['report'] ?? []);
$readiness = (array)($summary['readiness'] ?? []);
$launch = (array)($summary['launch'] ?? []);
$actionCenter = (array)($summary['action_center'] ?? []);
$content = (array)($summary['content'] ?? []);
$opportunity = (array)($summary['opportunity'] ?? []);
$focusOptions = [
    'all' => 'Semua Fokus',
    'money_leak' => 'Money Leak',
    'follow_up' => 'Follow-up',
    'seo_to_sales' => 'SEO → Sales',
    'trust_cta' => 'Trust & CTA',
    'setup' => 'Setup Profit',
    'scale' => 'Scale Winner',
];

function admin_profit_action_url(array $overrides = []): string
{
    $query = array_merge([
        'range' => $_GET['range'] ?? '30',
        'year' => $_GET['year'] ?? date('Y'),
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'focus' => $_GET['focus'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'show_done' => $_GET['show_done'] ?? '1',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/profit-action-dashboard' . ($query ? '?' . http_build_query($query) : ''));
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="profit-action-dashboard-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'summary' => $summary,
        'filtered_actions' => $actions,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    profit_action_export_csv($actions);
}

$moneyEstimate = (int)($report['sales']['estimate'] ?? 0);
$paidValue = (int)($report['sales']['paid_order_value'] ?? 0);
$unpaidEstimate = max(0, $moneyEstimate - $paidValue);
$leadEvents = (int)($report['lead']['events'] ?? 0);
$highIntent = (int)($report['lead']['high_intent'] ?? 0) + (int)($report['lead']['whatsapp'] ?? 0) + (int)($report['lead']['inquiries'] ?? 0);
$orders = (int)($report['order']['total'] ?? 0);
$paymentWaiting = (int)($report['order']['payment_waiting'] ?? 0);
$pendingProofs = (int)($report['payment']['pending_proofs'] ?? 0);
$leadToOrderRate = (float)($report['conversion']['lead_to_order_rate'] ?? 0);
$totalActions = count((array)($summary['actions'] ?? []));
$completedToday = (int)($summary['completed_today'] ?? 0);

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Profit Action Dashboard - Admin',
    'description' => 'Dashboard aksi profit harian untuk mengubah SEO, lead, CTA, order, dan payment menjadi tindakan yang bisa dieksekusi.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-profit-action-shell">
    <section class="admin-hero admin-profit-action-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Profit Action Dashboard</h1>
                <p>Dashboard ini mengubah data SEO, lead, order, payment, trust, dan funnel menjadi action harian yang paling dekat dengan profit.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/funnel-action-center')); ?>">Funnel Action</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/conversion-opportunities')); ?>">Opportunity</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/reports')); ?>">Laporan & Insight</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-profit-action-score-grid">
                <article class="admin-card admin-profit-action-score-card">
                    <span class="admin-badge">Profit Readiness</span>
                    <div class="admin-profit-action-score-ring" style="--score:<?= (int)($readiness['total'] ?? 0); ?>">
                        <strong><?= (int)($readiness['total'] ?? 0); ?></strong>
                        <span>/100</span>
                    </div>
                    <h2><?= esc((string)($readiness['label'] ?? 'Fondasi Profit Awal')); ?></h2>
                    <p>Skor gabungan dari launch readiness, funnel, content signal, traffic, intent, closing, dan payment.</p>
                    <div class="admin-profit-action-progress-list">
                        <?php foreach ((array)($readiness['parts'] ?? []) as $label => $value): ?>
                            <div><span><?= esc((string)$label); ?></span><strong><?= (int)$value; ?></strong><em style="--value:<?= (int)$value; ?>%"></em></div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="admin-card admin-profit-action-kpi-card">
                    <span class="admin-badge">Money</span>
                    <div><span>Estimasi omzet</span><strong><?= esc(function_exists('rupiah') ? rupiah($moneyEstimate) : (string)$moneyEstimate); ?></strong></div>
                    <div><span>Terbayar</span><strong><?= esc(function_exists('rupiah') ? rupiah($paidValue) : (string)$paidValue); ?></strong></div>
                    <div><span>Potensi tertahan</span><strong><?= esc(function_exists('rupiah') ? rupiah($unpaidEstimate) : (string)$unpaidEstimate); ?></strong></div>
                </article>

                <article class="admin-card admin-profit-action-kpi-card">
                    <span class="admin-badge">Signal</span>
                    <div><span>Lead/event</span><strong><?= (int)$leadEvents; ?></strong></div>
                    <div><span>High intent</span><strong><?= (int)$highIntent; ?></strong></div>
                    <div><span>Lead → order</span><strong><?= esc((string)$leadToOrderRate); ?>%</strong></div>
                </article>

                <article class="admin-card admin-profit-action-kpi-card">
                    <span class="admin-badge">Action</span>
                    <div><span>Total action</span><strong><?= (int)$totalActions; ?></strong></div>
                    <div><span>Selesai hari ini</span><strong><?= (int)$completedToday; ?></strong></div>
                    <div><span>Order/payment leak</span><strong><?= (int)($paymentWaiting + $pendingProofs); ?></strong></div>
                </article>
            </div>

            <div class="admin-profit-action-main-grid">
                <div class="admin-profit-action-main">
                    <div class="admin-card admin-profit-action-today-card">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="admin-badge">Prioritas Hari Ini</span>
                                <h2>Action yang paling dekat dengan profit</h2>
                                <p>Kerjakan dari atas dulu. Checklist ini otomatis membaca sinyal dari order, payment, lead, content, conversion, dan launch readiness.</p>
                            </div>
                            <form method="post" onsubmit="return confirm('Reset checklist profit hari ini?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="reset_today">
                                <button class="admin-btn admin-btn--soft" type="submit">Reset Hari Ini</button>
                            </form>
                        </div>

                        <div class="admin-profit-action-today-list">
                            <?php foreach ((array)($summary['today_plan'] ?? []) as $action): ?>
                                <?php $tone = (string)($action['priority']['tone'] ?? 'monitor'); ?>
                                <article class="admin-profit-action-today-item admin-profit-action-today-item--<?= esc($tone); ?>">
                                    <div class="admin-profit-action-today-head">
                                        <span><?= esc((string)($action['focus_label'] ?? 'Profit Action')); ?> · <?= esc((string)($action['priority']['label'] ?? 'Pantau')); ?> · <?= (int)($action['score'] ?? 0); ?>/100</span>
                                        <h3><?= esc((string)($action['title'] ?? 'Aksi profit')); ?></h3>
                                        <p><?= esc((string)($action['why'] ?? 'Ada sinyal yang perlu ditindaklanjuti.')); ?></p>
                                    </div>
                                    <div class="admin-profit-action-today-body">
                                        <div>
                                            <strong>Impact</strong>
                                            <p><?= esc((string)($action['impact'] ?? 'Membantu meningkatkan peluang closing.')); ?></p>
                                        </div>
                                        <div>
                                            <strong>Step cepat</strong>
                                            <ol>
                                                <?php foreach (array_slice((array)($action['steps'] ?? []), 0, 3) as $step): ?>
                                                    <li><?= esc((string)$step); ?></li>
                                                <?php endforeach; ?>
                                            </ol>
                                        </div>
                                    </div>
                                    <?php if (trim((string)($action['script'] ?? '')) !== ''): ?>
                                        <details class="admin-profit-action-script-mini">
                                            <summary>Script follow-up</summary>
                                            <textarea readonly><?= esc((string)$action['script']); ?></textarea>
                                        </details>
                                    <?php endif; ?>
                                    <div class="admin-profit-action-today-foot">
                                        <small>Estimasi: <?= esc((string)($action['effort'] ?? '15-30 menit')); ?> · Sumber: <?= esc((string)($action['source'] ?? 'Profit Engine')); ?></small>
                                        <div>
                                            <?php if (!empty($action['action_url'])): ?>
                                                <a class="admin-btn admin-btn--primary" href="<?= esc((string)$action['action_url']); ?>"><?= esc((string)($action['action_label'] ?? 'Buka')); ?></a>
                                            <?php endif; ?>
                                            <form method="post">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="action" value="complete">
                                                <input type="hidden" name="id" value="<?= esc((string)($action['id'] ?? '')); ?>">
                                                <button class="admin-btn admin-btn--soft" type="submit">Tandai Selesai</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>

                            <?php if (empty($summary['today_plan'])): ?>
                                <div class="admin-empty-state">
                                    <h2>Action utama hari ini sudah selesai</h2>
                                    <p>Mantap. Lanjut pantau laporan, tambah konten SEO, atau scale halaman yang performanya mulai bagus.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <form method="get" action="<?= esc(url('admin/profit-action-dashboard')); ?>" class="admin-card admin-report-filter admin-profit-action-filter">
                        <div class="admin-report-filter-head">
                            <div>
                                <span class="admin-badge">Action Board</span>
                                <h3>Filter semua action profit</h3>
                            </div>
                            <p>Rentang aktif: <strong><?= esc($rangeLabel); ?></strong>. Pakai filter untuk fokus ke money leak, follow-up, SEO, CTA, setup, atau scale.</p>
                        </div>
                        <div class="admin-report-filter-grid admin-report-filter-grid--wide">
                            <label><span>Range</span>
                                <select name="range">
                                    <?php foreach (function_exists('report_allowed_ranges') ? report_allowed_ranges() : ['7','30','90','365','all'] as $rangeOption): ?>
                                        <option value="<?= esc((string)$rangeOption); ?>" <?= (string)$params['range'] === (string)$rangeOption ? 'selected' : ''; ?>><?= esc($rangeOption === 'year' ? 'Tahun tertentu' : ($rangeOption === 'all' ? 'Semua data' : ($rangeOption === 'custom' ? 'Custom tanggal' : $rangeOption . ' hari'))); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label><span>Tahun</span><input type="number" name="year" value="<?= esc((string)($params['year'] ?? date('Y'))); ?>" min="2020" max="2100"></label>
                            <label><span>Dari</span><input type="date" name="date_from" value="<?= esc((string)($params['date_from'] ?? '')); ?>"></label>
                            <label><span>Sampai</span><input type="date" name="date_to" value="<?= esc((string)($params['date_to'] ?? '')); ?>"></label>
                            <label><span>Fokus</span>
                                <select name="focus">
                                    <?php foreach ($focusOptions as $value => $label): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= $focus === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label><span>Status</span>
                                <select name="show_done">
                                    <option value="1" <?= $showDone ? 'selected' : ''; ?>>Tampilkan semua</option>
                                    <option value="0" <?= !$showDone ? 'selected' : ''; ?>>Sembunyikan selesai</option>
                                </select>
                            </label>
                            <label class="admin-report-filter-search"><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="payment, follow-up, CTA, SEO, order..."></label>
                        </div>
                        <div class="admin-report-filter-actions">
                            <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/profit-action-dashboard')); ?>">Reset</a>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(admin_profit_action_url(['export' => 'csv'])); ?>">Export CSV</a>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(admin_profit_action_url(['export' => 'json'])); ?>">Export JSON</a>
                        </div>
                    </form>

                    <div class="admin-profit-action-board">
                        <?php foreach ($actions as $action): ?>
                            <?php $tone = (string)($action['priority']['tone'] ?? 'monitor'); ?>
                            <article class="admin-card admin-profit-action-card <?= !empty($action['completed']) ? 'is-completed' : ''; ?> admin-profit-action-card--<?= esc($tone); ?>">
                                <div class="admin-profit-action-card-head">
                                    <div>
                                        <span class="admin-badge"><?= esc((string)($action['focus_label'] ?? 'Profit Action')); ?> · <?= esc((string)($action['priority']['label'] ?? 'Pantau')); ?></span>
                                        <h2><?= esc((string)($action['title'] ?? 'Aksi profit')); ?></h2>
                                        <p><?= esc((string)($action['impact'] ?? 'Membantu memperkuat profit.')); ?></p>
                                    </div>
                                    <div class="admin-profit-action-card-score"><strong><?= (int)($action['score'] ?? 0); ?></strong><span>/100</span></div>
                                </div>
                                <div class="admin-profit-action-card-body">
                                    <div>
                                        <strong>Kenapa penting</strong>
                                        <p><?= esc((string)($action['why'] ?? 'Ada sinyal yang perlu ditindaklanjuti.')); ?></p>
                                    </div>
                                    <div>
                                        <strong>Checklist</strong>
                                        <ul>
                                            <?php foreach (array_slice((array)($action['steps'] ?? []), 0, 4) as $step): ?>
                                                <li><?= esc((string)$step); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                <div class="admin-profit-action-card-foot">
                                    <small>Target: <?= esc((string)($action['target'] ?? '-')); ?> · Effort: <?= esc((string)($action['effort'] ?? '15-30 menit')); ?></small>
                                    <div>
                                        <?php if (!empty($action['action_url'])): ?><a class="admin-btn admin-btn--primary" href="<?= esc((string)$action['action_url']); ?>"><?= esc((string)($action['action_label'] ?? 'Buka')); ?></a><?php endif; ?>
                                        <?php if (!empty($action['secondary_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$action['secondary_url']); ?>"><?= esc((string)($action['secondary_label'] ?? 'Detail')); ?></a><?php endif; ?>
                                        <form method="post">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="<?= !empty($action['completed']) ? 'undo' : 'complete'; ?>">
                                            <input type="hidden" name="id" value="<?= esc((string)($action['id'] ?? '')); ?>">
                                            <button class="admin-btn admin-btn--soft" type="submit"><?= !empty($action['completed']) ? 'Batal Selesai' : 'Selesai'; ?></button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$actions): ?>
                            <div class="admin-card admin-empty-state">
                                <h2>Belum ada action yang cocok</h2>
                                <p>Coba reset filter atau tunggu data lead, order, payment, dan content performance bertambah.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="admin-profit-action-side">
                    <div class="admin-card admin-profit-action-side-card">
                        <span class="admin-badge">Profit Funnel Leak Map</span>
                        <h2>Peta bocor funnel</h2>
                        <p>Lihat tahap mana yang butuh perhatian cepat.</p>
                        <div class="admin-profit-funnel-map">
                            <?php foreach ((array)($summary['funnel_map'] ?? []) as $stage): ?>
                                <div class="admin-profit-funnel-stage admin-profit-funnel-stage--<?= esc((string)($stage['tone'] ?? 'neutral')); ?>">
                                    <strong><?= esc((string)($stage['stage'] ?? 'Stage')); ?></strong>
                                    <span><?= (int)($stage['value'] ?? 0); ?> <?= esc((string)($stage['label'] ?? '')); ?></span>
                                    <small><?= esc((string)($stage['note'] ?? 'Pantau tahap ini.')); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-card admin-profit-action-side-card">
                        <span class="admin-badge">Money Leak Watch</span>
                        <h2>Yang jangan dibiarkan</h2>
                        <div class="admin-profit-action-watch-list">
                            <a href="<?= esc(url('admin/orders?payment_status=' . rawurlencode('Menunggu Pembayaran'))); ?>"><strong><?= (int)$paymentWaiting; ?></strong><span>Order menunggu pembayaran</span></a>
                            <a href="<?= esc(url('admin/payment-proofs')); ?>"><strong><?= (int)$pendingProofs; ?></strong><span>Bukti pembayaran menunggu review</span></a>
                            <a href="<?= esc(url('admin/followups')); ?>"><strong><?= (int)($actionCenter['metrics']['overdue_followups'] ?? 0); ?></strong><span>Follow-up terlambat</span></a>
                            <a href="<?= esc(url('admin/conversion-opportunities')); ?>"><strong><?= (int)($opportunity['metrics']['opportunities_total'] ?? 0); ?></strong><span>Peluang konversi</span></a>
                        </div>
                    </div>

                    <div class="admin-card admin-profit-action-side-card">
                        <span class="admin-badge">SEO to Profit Queue</span>
                        <h2>Konten yang layak dipoles</h2>
                        <div class="admin-profit-action-content-queue">
                            <?php foreach ((array)($content['top_rows'] ?? []) as $row): ?>
                                <a href="<?= esc(trim((string)($row['edit_url'] ?? '')) !== '' ? (string)$row['edit_url'] : url('admin/content-performance')); ?>">
                                    <strong><?= esc((string)($row['title'] ?? 'Halaman')); ?></strong>
                                    <span><?= esc((string)($row['bucket']['label'] ?? 'Pantau')); ?> · Score <?= (int)($row['performance_score'] ?? 0); ?></span>
                                    <small>Interaksi <?= (int)($row['metrics']['interactions'] ?? 0); ?> · Intent <?= (int)($row['metrics']['high_intent'] ?? 0); ?> · Order <?= (int)($row['metrics']['orders'] ?? 0); ?></small>
                                </a>
                            <?php endforeach; ?>
                            <?php if (empty($content['top_rows'])): ?>
                                <p>Belum ada konten dengan sinyal cukup. Mulai dari SEO Growth Planner dan artikel pendukung.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="admin-card admin-profit-action-side-card">
                        <span class="admin-badge">Quick Links</span>
                        <h2>Menu profit terkait</h2>
                        <div class="admin-help-mini-path">
                            <a href="<?= esc(url('admin/content-performance')); ?>"><strong>Content Performance</strong><span>Cek halaman yang mulai punya sinyal.</span></a>
                            <a href="<?= esc(url('admin/trust-conversion')); ?>"><strong>Trust & CTA</strong><span>Tambah testimoni, FAQ, CTA, garansi.</span></a>
                            <a href="<?= esc(url('admin/followups')); ?>"><strong>Follow-up CRM</strong><span>Jaga lead agar tidak dingin.</span></a>
                            <a href="<?= esc(url('admin/payment-reminders')); ?>"><strong>Payment Reminder</strong><span>Kejar order pending.</span></a>
                            <a href="<?= esc(url('admin/seo-growth-planner')); ?>"><strong>SEO Planner</strong><span>Bangun traffic yang relevan.</span></a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
