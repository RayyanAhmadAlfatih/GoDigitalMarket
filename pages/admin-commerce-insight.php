<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();
$GLOBALS['admin_page'] = true;

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$error = '';
$message = (string)($_GET['message'] ?? '');
$currentPath = trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? ''), '/');
$allowedTabs = ['ringkasan', 'lead-funnel', 'order-payment', 'shipping', 'digital', 'action-export'];
$requestedTab = strtolower(trim((string)($_GET['tab'] ?? '')));
$activeTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'ringkasan';
if ($requestedTab === '' && preg_match('#(^|/)admin(-|/)reports?$#', $currentPath)) {
    $activeTab = 'lead-funnel';
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/commerce-insight');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_panel_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'unified-sales-report-commerce-insight']);
            }
            redirect_302('admin/commerce-insight');
        }
        $error = 'Password admin salah.';
    }
}

$range = commerce_insight_range_from_request($_GET);
$legacyParams = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['range' => (string)($range['range'] ?? '30'), 'days' => (int)($range['days'] ?? 30), 'filters' => [], 'year' => date('Y'), 'date_from' => '', 'date_to' => ''];
$summary = admin_panel_logged_in() ? commerce_insight_summary((int)$range['days']) : [];
$legacySummary = admin_panel_logged_in() && function_exists('report_dashboard_summary') ? report_dashboard_summary((int)($legacyParams['days'] ?? 30), (array)($legacyParams['filters'] ?? [])) : [];
$legacyRangeLabel = function_exists('report_range_label') ? report_range_label((string)($legacyParams['range'] ?? '30'), $legacyParams) : (string)($range['label'] ?? '30 hari terakhir');

if (admin_panel_logged_in() && (string)($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'generated_at' => date('c'),
        'active_tab' => $activeTab,
        'commerce_insight' => $summary,
        'classic_sales_report' => $legacySummary,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

if (admin_panel_logged_in()) {
    $export = (string)($_GET['export'] ?? '');
    if ($export === 'summary' || $export === 'commerce_summary') {
        commerce_insight_export_summary_csv($summary);
    }
    if ($export === 'actions') {
        commerce_insight_export_actions_csv($summary);
    }
    if ($export === 'report_summary' && function_exists('report_export_summary_csv')) {
        report_export_summary_csv($legacySummary);
    }
    if ($export === 'report_daily' && function_exists('report_export_daily_csv')) {
        report_export_daily_csv($legacySummary);
    }
}

function unified_report_url(array $extra = []): string
{
    $query = array_merge($_GET, $extra);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) {
            unset($query[$key]);
        }
    }
    return url('admin/commerce-insight' . ($query ? '?' . http_build_query($query) : ''));
}

function unified_report_money(int $value): string
{
    return function_exists('rupiah') ? rupiah($value) : 'Rp ' . number_format($value, 0, ',', '.');
}

function unified_report_stat(string $label, string $value, string $note = '', string $href = ''): void
{
    ?>
    <div class="ci-stat-card">
        <span><?= esc($label); ?></span>
        <strong><?= esc($value); ?></strong>
        <?php if ($note !== ''): ?><p><?= esc($note); ?></p><?php endif; ?>
        <?php if ($href !== ''): ?><a href="<?= esc($href); ?>">Buka detail</a><?php endif; ?>
    </div>
    <?php
}

function unified_report_table(array $rows, array $headers, callable $renderer, string $empty = 'Belum ada data.'): void
{
    echo '<table class="ci-table"><thead><tr>';
    foreach ($headers as $header) {
        echo '<th>' . esc((string)$header) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $hasRows = false;
    foreach ($rows as $key => $row) {
        $hasRows = true;
        echo $renderer($key, $row);
    }
    if (!$hasRows) {
        echo '<tr><td colspan="' . count($headers) . '" class="admin-muted">' . esc($empty) . '</td></tr>';
    }
    echo '</tbody></table>';
}

function unified_report_count_table(array $items, string $labelTitle = 'Item', string $countTitle = 'Jumlah', string $empty = 'Belum ada data.'): void
{
    unified_report_table($items, [$labelTitle, $countTitle], static function ($label, $count): string {
        if (is_array($count)) {
            $count = (int)($count['orders'] ?? $count['count'] ?? $count['value'] ?? 0);
        }
        return '<tr><td>' . esc((string)$label) . '</td><td>' . number_format((int)$count, 0, ',', '.') . '</td></tr>';
    }, $empty);
}

function unified_report_tab_link(string $tab, string $label, string $activeTab): string
{
    $class = $tab === $activeTab ? 'admin-btn admin-btn--primary' : 'admin-btn admin-btn--soft';
    return '<a class="' . esc($class) . '" href="' . esc(unified_report_url(['tab' => $tab, 'export' => '', 'format' => ''])) . '">' . esc($label) . '</a>';
}

set_seo([
    'title' => 'Admin Laporan & Insight Penjualan - ' . SITE_NAME,
    'description' => 'Laporan penjualan terpadu untuk order, lead, payment, shipping, digital, license, subscription, dan action plan.',
    'robots' => 'noindex, nofollow',
]);

require ROOT_PATH . '/components/layout/head.php';
require ROOT_PATH . '/components/layout/header.php';
?>
<main id="main-content" class="admin-shell admin-commerce-insight-shell">
    <section class="admin-hero ci-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Laporan & Insight Penjualan</div>
                <h1>Unified Sales Report & Commerce Insight</h1>
                <p>Satu dashboard terpadu untuk omzet, lead, order, payment, shipping, digital product, license, subscription, recovery, dan action plan penjualan.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/orders')); ?>">Buka Order</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/checkout-recovery')); ?>">Recovery</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <style>
                .admin-commerce-insight-shell .ci-hero{background:linear-gradient(135deg,#1d4ed8,#0f172a)}
                .admin-commerce-insight-shell .ci-toolbar,.admin-commerce-insight-shell .ci-tabs{display:flex;justify-content:space-between;gap:14px;align-items:center;flex-wrap:wrap;border:1px solid #dbeafe;background:#fff;border-radius:24px;padding:16px 18px;box-shadow:0 18px 50px rgba(15,23,42,.06)}
                .admin-commerce-insight-shell .ci-tabs{justify-content:flex-start}.admin-commerce-insight-shell .ci-range{display:flex;gap:8px;flex-wrap:wrap}.admin-commerce-insight-shell .ci-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.admin-commerce-insight-shell .ci-grid--three{grid-template-columns:repeat(3,minmax(0,1fr))}.admin-commerce-insight-shell .ci-grid--two{grid-template-columns:repeat(2,minmax(0,1fr))}
                .admin-commerce-insight-shell .ci-stat-card,.admin-commerce-insight-shell .ci-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 15px 44px rgba(15,23,42,.05)}
                .admin-commerce-insight-shell .ci-stat-card span{display:inline-flex;border-radius:999px;background:#eff6ff;color:#1d4ed8;padding:6px 10px;font-weight:900;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em}.admin-commerce-insight-shell .ci-stat-card strong{display:block;color:#0f172a;font-size:1.8rem;line-height:1.08;margin:12px 0 6px}.admin-commerce-insight-shell .ci-stat-card p,.admin-commerce-insight-shell .ci-card p{color:#475569;margin:.3rem 0 .6rem}.admin-commerce-insight-shell .ci-stat-card a{font-weight:900;color:#2563eb;text-decoration:none}
                .admin-commerce-insight-shell .ci-card h2,.admin-commerce-insight-shell .ci-card h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-commerce-insight-shell .ci-table{width:100%;border-collapse:collapse}.admin-commerce-insight-shell .ci-table th,.admin-commerce-insight-shell .ci-table td{border-bottom:1px solid #e2e8f0;padding:10px;text-align:left;vertical-align:top}.admin-commerce-insight-shell .ci-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b}.admin-commerce-insight-shell .ci-action{border:1px solid #dbeafe;border-radius:18px;background:#f8fbff;padding:14px;margin-bottom:10px}.admin-commerce-insight-shell .ci-action strong{display:block;color:#0f172a;font-size:1rem}.admin-commerce-insight-shell .ci-action span{display:inline-flex;margin-bottom:7px;border-radius:999px;padding:5px 9px;background:#dbeafe;color:#1e40af;font-weight:900;font-size:.75rem}.admin-commerce-insight-shell .ci-action a{font-weight:900;color:#2563eb;text-decoration:none}.admin-commerce-insight-shell .ci-bars{height:180px;display:flex;align-items:end;gap:5px;border:1px solid #e2e8f0;border-radius:18px;padding:12px;background:#f8fafc;overflow:hidden}.admin-commerce-insight-shell .ci-bar{display:flex;gap:2px;align-items:end;flex:1;height:100%;min-width:6px}.admin-commerce-insight-shell .ci-bar span{display:block;flex:1;min-height:3px;border-radius:6px 6px 0 0;background:#2563eb}.admin-commerce-insight-shell .ci-bar span:nth-child(2){background:#16a34a}.admin-commerce-insight-shell .ci-bar span:nth-child(3){background:#f59e0b}.admin-commerce-insight-shell .ci-empty{padding:18px;border:1px dashed #cbd5e1;border-radius:18px;background:#f8fafc;color:#64748b}
                .admin-commerce-insight-shell .ci-note{border:1px solid #bfdbfe;background:#eff6ff;border-radius:20px;padding:14px;color:#1e3a8a}.admin-commerce-insight-shell .ci-note strong{display:block;color:#172554;margin-bottom:4px}
                @media(max-width:1100px){.admin-commerce-insight-shell .ci-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-commerce-insight-shell .ci-grid--three{grid-template-columns:1fr}.admin-commerce-insight-shell .ci-grid--two{grid-template-columns:1fr}}@media(max-width:680px){.admin-commerce-insight-shell .ci-grid{grid-template-columns:1fr}.admin-commerce-insight-shell .ci-toolbar{align-items:flex-start}.admin-commerce-insight-shell .ci-table{font-size:.9rem}}
            </style>

            <?php if ($message !== ''): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!admin_panel_logged_in()): ?>
                <div class="admin-login-layout">
                    <div class="admin-login-copy">
                        <span class="admin-badge">Akses terbatas</span>
                        <h2>Masuk Laporan & Insight</h2>
                        <p>Gunakan password admin untuk membuka ringkasan penjualan, order, lead, payment, subscription, dan action plan.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/commerce-insight'); ?>

                <div class="ci-note">
                    <strong>Menu Laporan Penjualan dan Commerce Insight sudah disatukan.</strong>
                    Menu laporan penjualan dan insight bisnis sudah dipusatkan di halaman ini agar admin membaca data dari satu tempat yang jelas.
                </div>

                <div class="ci-toolbar">
                    <div>
                        <strong>Rentang aktif: <?= esc((string)$range['label']); ?></strong>
                        <p class="admin-muted">Data commerce digabung dari order, checkout recovery, payment proof, member access, license, dan subscription. Data lead/funnel memakai filter laporan terpadu: <?= esc($legacyRangeLabel); ?>.</p>
                    </div>
                    <div class="ci-range">
                        <?php foreach (commerce_insight_allowed_ranges() as $rangeOption): ?>
                            <a class="admin-btn <?= $range['range'] === $rangeOption ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= esc(unified_report_url(['range' => $rangeOption, 'export' => '', 'format' => ''])); ?>"><?= esc($rangeOption === 'all' ? 'Semua' : $rangeOption . ' Hari'); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <nav class="ci-tabs" aria-label="Tab Laporan Penjualan">
                    <?= unified_report_tab_link('ringkasan', 'Ringkasan', $activeTab); ?>
                    <?= unified_report_tab_link('lead-funnel', 'Lead & Funnel', $activeTab); ?>
                    <?= unified_report_tab_link('order-payment', 'Order & Payment', $activeTab); ?>
                    <?= unified_report_tab_link('shipping', 'Shipping & Fulfillment', $activeTab); ?>
                    <?= unified_report_tab_link('digital', 'Digital, Member & License', $activeTab); ?>
                    <?= unified_report_tab_link('action-export', 'Action Plan & Export', $activeTab); ?>
                </nav>

                <?php if ($activeTab === 'ringkasan'): ?>
                    <div class="ci-grid">
                        <?php
                        unified_report_stat('Omzet valid', unified_report_money((int)($summary['totals']['paid_revenue'] ?? 0)), 'Estimasi revenue dari order yang payment-nya sudah valid.', url('admin/orders'));
                        unified_report_stat('Pipeline belum bayar', unified_report_money((int)($summary['totals']['unpaid_pipeline'] ?? 0)), ((int)($summary['totals']['unpaid_orders'] ?? 0)) . ' order belum lunas.', url('admin/checkout-recovery'));
                        unified_report_stat('Paid rate', ((string)($summary['totals']['paid_rate'] ?? 0)) . '%', ((int)($summary['totals']['paid_orders'] ?? 0)) . ' order paid dari ' . ((int)($summary['totals']['orders'] ?? 0)) . ' order.', url('admin/orders'));
                        unified_report_stat('AOV', unified_report_money((int)($summary['totals']['average_order_value'] ?? 0)), 'Rata-rata nilai order.', '');
                        ?>
                    </div>
                    <div class="ci-grid ci-grid--three">
                        <section class="ci-card">
                            <h2>Action Plan Teratas</h2>
                            <p>Prioritas kerja yang bisa langsung dikerjakan hari ini.</p>
                            <?php foreach (array_slice((array)($summary['actions'] ?? []), 0, 5) as $action): if (!is_array($action)) continue; ?>
                                <div class="ci-action">
                                    <span><?= esc((string)($action['priority'] ?? 'Normal')); ?></span>
                                    <strong><?= esc((string)($action['title'] ?? 'Action')); ?></strong>
                                    <p><?= esc((string)($action['note'] ?? '')); ?></p>
                                    <p><b><?= esc((string)($action['metric'] ?? '')); ?></b></p>
                                    <?php if (!empty($action['url'])): ?><a href="<?= esc((string)$action['url']); ?>">Kerjakan sekarang</a><?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['actions'])): ?><div class="ci-empty">Belum ada action plan khusus.</div><?php endif; ?>
                        </section>
                        <section class="ci-card">
                            <h2>Trend Order & Revenue</h2>
                            <p>Biru: order. Hijau: revenue paid. Kuning: pipeline belum bayar.</p>
                            <?php $daily = (array)($summary['daily'] ?? []); $maxDaily = 1; foreach ($daily as $row) { if (is_array($row)) { $maxDaily = max($maxDaily, (int)($row['orders'] ?? 0), (int)round(((int)($row['revenue'] ?? 0)) / 100000), (int)round(((int)($row['pipeline'] ?? 0)) / 100000)); } } ?>
                            <?php if ($daily): ?>
                                <div class="ci-bars">
                                    <?php foreach ($daily as $row): if (!is_array($row)) continue; ?>
                                        <?php
                                        $orderH = max(3, (int)round(((int)($row['orders'] ?? 0) / $maxDaily) * 100));
                                        $revenueH = max(3, (int)round(((int)round(((int)($row['revenue'] ?? 0)) / 100000) / $maxDaily) * 100));
                                        $pipelineH = max(3, (int)round(((int)round(((int)($row['pipeline'] ?? 0)) / 100000) / $maxDaily) * 100));
                                        ?>
                                        <div class="ci-bar" title="<?= esc((string)($row['date'] ?? '')); ?> | Order <?= (int)($row['orders'] ?? 0); ?> | Revenue <?= esc(unified_report_money((int)($row['revenue'] ?? 0))); ?> | Pipeline <?= esc(unified_report_money((int)($row['pipeline'] ?? 0))); ?>">
                                            <span style="height:<?= $orderH; ?>%"></span><span style="height:<?= $revenueH; ?>%"></span><span style="height:<?= $pipelineH; ?>%"></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?><div class="ci-empty">Trend harian tersedia untuk range 7–365 hari.</div><?php endif; ?>
                        </section>
                        <section class="ci-card">
                            <h2>Produk Paling Menghasilkan</h2>
                            <?php unified_report_table((array)($summary['leaderboards']['products'] ?? []), ['Produk', 'Order', 'Qty', 'Nilai'], static function ($label, $row): string {
                                if (!is_array($row)) return '';
                                return '<tr><td>' . esc((string)$label) . '</td><td>' . (int)($row['orders'] ?? 0) . '</td><td>' . (int)($row['quantity'] ?? 0) . '</td><td>' . esc(unified_report_money((int)($row['value'] ?? 0))) . '</td></tr>';
                            }, 'Belum ada data produk.'); ?>
                        </section>
                    </div>
                    <section class="ci-card">
                        <h2>Order Terbaru</h2>
                        <?php unified_report_table((array)($summary['recent_orders'] ?? []), ['Ref', 'Customer', 'Produk', 'Status', 'Total', 'Aksi'], static function ($key, $order): string {
                            if (!is_array($order)) return '';
                            $ref = (string)($order['ref'] ?? $order['id'] ?? '-');
                            return '<tr><td>' . esc($ref) . '</td><td>' . esc((string)($order['name'] ?? '-')) . '<br><small>' . esc((string)($order['phone'] ?? $order['email'] ?? '')) . '</small></td><td>' . esc((string)($order['product_title'] ?? '-')) . '</td><td>' . esc((string)($order['status'] ?? 'Baru')) . '<br><small>' . esc((string)($order['payment_status'] ?? 'Belum bayar')) . '</small></td><td>' . esc(unified_report_money(function_exists('commerce_insight_order_total') ? commerce_insight_order_total($order) : (int)($order['total'] ?? 0))) . '</td><td><a class="admin-btn admin-btn--soft" href="' . esc(url('admin/orders?search=' . rawurlencode($ref))) . '">Detail</a></td></tr>';
                        }, 'Belum ada order pada rentang ini.'); ?>
                    </section>

                <?php elseif ($activeTab === 'lead-funnel'): ?>
                    <div class="ci-grid">
                        <?php
                        unified_report_stat('Lead event', number_format((int)($legacySummary['lead']['events'] ?? 0), 0, ',', '.'), 'Aktivitas calon customer dari klik, CTA, dan tracking.', url('admin/leads'));
                        unified_report_stat('Form masuk', number_format((int)($legacySummary['lead']['inquiries'] ?? 0), 0, ',', '.'), 'Submission form custom/lead magnet/kontak.', url('admin/inquiries'));
                        unified_report_stat('Order', number_format((int)($legacySummary['order']['total'] ?? 0), 0, ',', '.'), 'Order pada rentang laporan terpadu.', url('admin/orders'));
                        unified_report_stat('Lead → Order', ((string)($legacySummary['conversion']['lead_to_order_rate'] ?? 0)) . '%', 'Rasio lead event terhadap order.', '');
                        ?>
                    </div>
                    <div class="ci-grid ci-grid--three">
                        <section class="ci-card"><h2>Channel Lead</h2><?php unified_report_count_table((array)($legacySummary['breakdowns']['lead_channel'] ?? []), 'Channel', 'Lead'); ?></section>
                        <section class="ci-card"><h2>Sumber Lead</h2><?php unified_report_count_table((array)($legacySummary['breakdowns']['lead_source'] ?? []), 'Sumber', 'Lead'); ?></section>
                        <section class="ci-card"><h2>Kebutuhan Form</h2><?php unified_report_count_table((array)($legacySummary['breakdowns']['inquiry_need'] ?? []), 'Kebutuhan', 'Jumlah'); ?></section>
                    </div>
                    <section class="ci-card">
                        <h2>Lead & Inquiry Terbaru</h2>
                        <div class="ci-grid ci-grid--two">
                            <div>
                                <h3>Lead Prioritas</h3>
                                <?php unified_report_table((array)($legacySummary['recent']['leads'] ?? []), ['Lead', 'Halaman'], static function ($key, $row): string {
                                    if (!is_array($row)) return '';
                                    return '<tr><td>' . esc((string)($row['label'] ?? $row['type'] ?? 'Lead')) . '</td><td>' . esc((string)($row['page_path'] ?? '/')) . '</td></tr>';
                                }); ?>
                            </div>
                            <div>
                                <h3>Form Masuk</h3>
                                <?php unified_report_table((array)($legacySummary['recent']['inquiries'] ?? []), ['Nama', 'Kebutuhan', 'Status'], static function ($key, $row): string {
                                    if (!is_array($row)) return '';
                                    return '<tr><td>' . esc((string)($row['name'] ?? '-')) . '</td><td>' . esc((string)($row['need'] ?? '-')) . '</td><td>' . esc((string)($row['status'] ?? 'Baru')) . '</td></tr>';
                                }); ?>
                            </div>
                        </div>
                    </section>

                <?php elseif ($activeTab === 'order-payment'): ?>
                    <div class="ci-grid">
                        <?php
                        unified_report_stat('Sales estimate', unified_report_money((int)($legacySummary['sales']['estimate'] ?? 0)), 'Estimasi nilai order dari laporan terpadu.', url('admin/orders'));
                        unified_report_stat('Paid order value', unified_report_money((int)($legacySummary['sales']['paid_order_value'] ?? 0)), 'Nilai order dengan status pembayaran valid.', '');
                        unified_report_stat('Bukti bayar valid', number_format((int)($legacySummary['payment']['valid_proofs'] ?? 0), 0, ',', '.'), 'Bukti transfer yang sudah valid.', url('admin/payment-proofs'));
                        unified_report_stat('Bukti pending', number_format((int)($legacySummary['payment']['pending_proofs'] ?? 0), 0, ',', '.'), 'Bukti bayar menunggu review.', url('admin/payment-proofs'));
                        ?>
                    </div>
                    <div class="ci-grid ci-grid--three">
                        <section class="ci-card"><h2>Channel Pembayaran</h2><?php unified_report_table((array)($summary['payment']['by_channel'] ?? []), ['Channel', 'Order', 'Nilai'], static function ($label, $row): string { if (!is_array($row)) return ''; return '<tr><td>' . esc((string)$label) . '</td><td>' . (int)($row['orders'] ?? 0) . '</td><td>' . esc(unified_report_money((int)($row['value'] ?? 0))) . '</td></tr>'; }); ?></section>
                        <section class="ci-card"><h2>Status Order</h2><?php unified_report_count_table((array)($legacySummary['breakdowns']['order_status'] ?? []), 'Status', 'Order'); ?></section>
                        <section class="ci-card"><h2>Status Payment</h2><?php unified_report_count_table((array)($legacySummary['breakdowns']['payment_status'] ?? []), 'Status', 'Order'); ?></section>
                    </div>
                    <section class="ci-card"><h2>Bukti Pembayaran Terbaru</h2><?php unified_report_table((array)($legacySummary['recent']['proofs'] ?? []), ['Order', 'Nama', 'Status', 'Nominal'], static function ($key, $row): string { if (!is_array($row)) return ''; return '<tr><td>' . esc((string)($row['order_ref'] ?? '-')) . '</td><td>' . esc((string)($row['payer_name'] ?? '-')) . '</td><td>' . esc((string)($row['status'] ?? '-')) . '</td><td>' . esc(unified_report_money((int)($row['amount'] ?? 0))) . '</td></tr>'; }); ?></section>

                <?php elseif ($activeTab === 'shipping'): ?>
                    <div class="ci-grid">
                        <?php
                        unified_report_stat('Ongkir terkumpul', unified_report_money((int)($summary['shipping']['revenue'] ?? 0)), 'Total shipping charge yang masuk order.', url('admin/shipping'));
                        unified_report_stat('Recovery value', unified_report_money((int)($summary['recovery']['value'] ?? 0)), ((int)($summary['recovery']['count'] ?? 0)) . ' kandidat follow-up.', url('admin/checkout-recovery'));
                        unified_report_stat('Order belum bayar', number_format((int)($summary['totals']['unpaid_orders'] ?? 0), 0, ',', '.'), 'Perlu follow-up/payment reminder.', url('admin/checkout-recovery'));
                        unified_report_stat('Order paid', number_format((int)($summary['totals']['paid_orders'] ?? 0), 0, ',', '.'), 'Siap diproses fulfillment.', url('admin/orders'));
                        ?>
                    </div>
                    <div class="ci-grid ci-grid--two">
                        <section class="ci-card"><h2>Shipping Origin & Gudang</h2><?php unified_report_table((array)($summary['shipping']['by_origin'] ?? []), ['Asal Kirim', 'Order', 'Nilai'], static function ($label, $row): string { if (!is_array($row)) return ''; return '<tr><td>' . esc((string)$label) . '</td><td>' . (int)($row['orders'] ?? 0) . '</td><td>' . esc(unified_report_money((int)($row['value'] ?? 0))) . '</td></tr>'; }, 'Belum ada data asal kirim.'); ?></section>
                        <section class="ci-card"><h2>Status Fulfillment</h2><?php unified_report_count_table((array)($summary['leaderboards']['fulfillment_statuses'] ?? $summary['leaderboards']['statuses'] ?? []), 'Status', 'Order'); ?></section>
                    </div>

                <?php elseif ($activeTab === 'digital'): ?>
                    <div class="ci-grid">
                        <?php
                        unified_report_stat('Akses member aktif', number_format((int)($summary['digital']['member_access_active'] ?? 0), 0, ',', '.'), 'Course/file/license aktif.', url('admin/member-area'));
                        unified_report_stat('Buyer account', number_format((int)($summary['digital']['buyer_accounts'] ?? 0), 0, ',', '.'), 'Akun buyer/member yang tercatat.', url('admin/member-area'));
                        unified_report_stat('License aktif', number_format((int)($summary['license']['active'] ?? 0), 0, ',', '.'), 'License key aktif.', url('admin/license-manager'));
                        unified_report_stat('Subscription due', number_format((int)($summary['subscription']['due'] ?? 0), 0, ',', '.'), ((int)($summary['subscription']['expired'] ?? 0)) . ' expired perlu follow-up.', url('admin/subscriptions'));
                        ?>
                    </div>
                    <div class="ci-grid ci-grid--three">
                        <section class="ci-card"><h2>Digital Business</h2><table class="ci-table"><tbody><tr><td>Order digital</td><td><?= number_format((int)($summary['digital']['digital_orders'] ?? 0), 0, ',', '.'); ?></td></tr><tr><td>Order lisensi</td><td><?= number_format((int)($summary['digital']['license_orders'] ?? 0), 0, ',', '.'); ?></td></tr><tr><td>License perlu dicek</td><td><?= number_format((int)($summary['license']['needs_attention'] ?? 0), 0, ',', '.'); ?></td></tr></tbody></table></section>
                        <section class="ci-card"><h2>Shortcut Operasional</h2><p>Gunakan menu berikut untuk validasi akses digital, course, lisensi, dan subscription.</p><p><a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/member-area')); ?>">Member Area</a> <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/license-manager')); ?>">License</a> <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/subscriptions')); ?>">Subscription</a></p></section>
                        <section class="ci-card"><h2>Catatan</h2><p>Produk course, lisensi, dan membership tetap mengikuti status payment/order. Akses otomatis idealnya dirilis setelah payment valid/lunas.</p></section>
                    </div>

                <?php else: ?>
                    <div class="ci-grid ci-grid--two">
                        <section class="ci-card">
                            <h2>Action Plan Penjualan</h2>
                            <p>Prioritas kerja yang bisa langsung dikerjakan admin.</p>
                            <?php foreach ((array)($summary['actions'] ?? []) as $action): if (!is_array($action)) continue; ?>
                                <div class="ci-action"><span><?= esc((string)($action['priority'] ?? 'Normal')); ?></span><strong><?= esc((string)($action['title'] ?? 'Action')); ?></strong><p><?= esc((string)($action['note'] ?? '')); ?></p><p><b><?= esc((string)($action['metric'] ?? '')); ?></b></p><?php if (!empty($action['url'])): ?><a href="<?= esc((string)$action['url']); ?>">Kerjakan sekarang</a><?php endif; ?></div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['actions'])): ?><div class="ci-empty">Belum ada action plan khusus.</div><?php endif; ?>
                        </section>
                        <section class="ci-card">
                            <h2>Export Data</h2>
                            <p>Export commerce insight dan laporan terpadu dari satu menu.</p>
                            <div class="ci-range">
                                <a class="admin-btn admin-btn--soft" href="<?= esc(unified_report_url(['export' => 'summary', 'format' => ''])); ?>">Export Commerce Summary</a>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(unified_report_url(['export' => 'actions', 'format' => ''])); ?>">Export Action Plan</a>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(unified_report_url(['export' => 'report_summary', 'format' => ''])); ?>">Export Laporan Klasik</a>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(unified_report_url(['export' => 'report_daily', 'format' => ''])); ?>">Export Daily Klasik</a>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(unified_report_url(['format' => 'json', 'export' => ''])); ?>" target="_blank" rel="noopener">JSON Terpadu</a>
                            </div>
                        </section>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require ROOT_PATH . '/components/layout/footer.php'; ?>
