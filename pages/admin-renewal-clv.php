<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

seo_noindex();
$GLOBALS['admin_page'] = true;

$message = (string)($_GET['message'] ?? '');
$error = '';
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';

function admin_renewal_clv_logged_in(): bool
{
    return function_exists('admin_panel_logged_in') ? admin_panel_logged_in() : !empty($_SESSION['admin_articles_logged_in']);
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/renewal-clv');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_renewal_clv_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            redirect_302('admin/renewal-clv');
        }
        $error = 'Password admin salah.';
    } else {
        $action = (string)($_POST['form_action'] ?? '');
        if ($action === 'save_renewal_clv') {
            $settings = [
                'enabled' => !empty($_POST['enabled']),
                'renewal_window_days' => (int)($_POST['renewal_window_days'] ?? 14),
                'winback_after_days' => (int)($_POST['winback_after_days'] ?? 7),
                'clv_high_threshold' => (string)($_POST['clv_high_threshold'] ?? '1000000'),
                'clv_medium_threshold' => (string)($_POST['clv_medium_threshold'] ?? '250000'),
                'upgrade_min_paid_orders' => (int)($_POST['upgrade_min_paid_orders'] ?? 2),
                'upgrade_min_revenue' => (string)($_POST['upgrade_min_revenue'] ?? '300000'),
                'renewal_template' => (string)($_POST['renewal_template'] ?? ''),
                'upgrade_template' => (string)($_POST['upgrade_template'] ?? ''),
                'winback_template' => (string)($_POST['winback_template'] ?? ''),
            ];
            if (renewal_clv_write_settings($settings)) {
                redirect_302('admin/renewal-clv?message=' . rawurlencode('Pengaturan Renewal & CLV berhasil disimpan.'));
            }
            $error = 'Pengaturan belum bisa disimpan. Pastikan folder storage writable.';
        }
    }
}

$loggedIn = admin_renewal_clv_logged_in();
$settings = $loggedIn ? renewal_clv_read_settings() : renewal_clv_default_settings();
$days = max(30, min(3650, (int)($_GET['days'] ?? 365)));
$summary = $loggedIn ? renewal_clv_summary($days) : [];
$opportunities = (array)($summary['opportunities'] ?? []);
$profiles = (array)($summary['profiles'] ?? []);

if ($loggedIn && (string)($_GET['export'] ?? '') !== '') {
    $export = (string)$_GET['export'];
    $filename = 'renewal-clv-' . $export . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    if ($export === 'opportunities') {
        fputcsv($out, ['Tipe', 'Prioritas', 'Customer', 'Email', 'WhatsApp', 'Produk', 'Segment', 'Estimasi Nilai', 'Pesan']);
        foreach ($opportunities as $row) {
            $customer = (array)($row['customer'] ?? []);
            $sub = (array)($row['subscription'] ?? []);
            fputcsv($out, [
                (string)($row['type'] ?? ''),
                (string)($row['priority'] ?? ''),
                (string)($customer['name'] ?? ''),
                (string)($customer['email'] ?? ''),
                (string)($customer['phone'] ?? ''),
                (string)($sub['product_title'] ?? $customer['last_product'] ?? ''),
                (string)($customer['segment'] ?? ''),
                (string)($row['amount_hint'] ?? 0),
                (string)($row['message'] ?? ''),
            ]);
        }
    } else {
        fputcsv($out, ['Customer', 'Email', 'WhatsApp', 'Segment', 'Total Revenue', 'Paid Orders', 'AOV', 'Pipeline', 'Subscription Aktif', 'Expired', 'Lisensi']);
        foreach ($profiles as $row) {
            fputcsv($out, [
                (string)($row['name'] ?? ''),
                (string)($row['email'] ?? ''),
                (string)($row['phone'] ?? ''),
                (string)($row['segment'] ?? ''),
                (string)($row['revenue'] ?? 0),
                (string)($row['paid_orders'] ?? 0),
                (string)($row['aov'] ?? 0),
                (string)($row['pipeline'] ?? 0),
                (string)($row['active_subscriptions'] ?? 0),
                (string)($row['expired_subscriptions'] ?? 0),
                (string)($row['license_count'] ?? 0),
            ]);
        }
    }
    fclose($out);
    exit;
}

set_seo([
    'title' => 'Renewal, Upgrade & CLV - Admin',
    'description' => 'Pusat follow-up renewal, upgrade, subscription, license, dan customer lifetime value.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="main-content" class="admin-shell admin-renewal-clv-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Customer Growth</div>
                <h1>Renewal, Upgrade & Customer Lifetime Value</h1>
                <p>Bantu UMKM mengubah customer lama menjadi repeat order, renewal membership, upgrade paket, dan pendapatan berulang.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/subscriptions')); ?>">Subscription</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/license-manager')); ?>">License Manager</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/commerce-insight')); ?>">Laporan & Insight</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <style>
                .admin-renewal-clv-shell .clv-toolbar{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.admin-renewal-clv-shell .clv-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(320px,.8fr);gap:18px;align-items:start}.admin-renewal-clv-shell .clv-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-renewal-clv-shell .clv-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-renewal-clv-shell label{display:grid;gap:7px;color:#334155;font-weight:850;font-size:.86rem}.admin-renewal-clv-shell input,.admin-renewal-clv-shell select,.admin-renewal-clv-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;background:#fff;color:#0f172a}.admin-renewal-clv-shell select option{background:#fff;color:#0f172a}.admin-renewal-clv-shell textarea{min-height:108px}.admin-renewal-clv-shell .clv-check{display:flex!important;align-items:center;gap:8px}.admin-renewal-clv-shell .clv-check input{width:auto}.admin-renewal-clv-shell .clv-list{display:grid;gap:10px}.admin-renewal-clv-shell .clv-row{border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc;padding:13px}.admin-renewal-clv-shell .clv-row-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.admin-renewal-clv-shell .clv-pill{display:inline-flex;border-radius:999px;padding:4px 9px;background:#e0f2fe;color:#075985;font-size:.76rem;font-weight:900}.admin-renewal-clv-shell .clv-message{white-space:pre-wrap;background:#fff;border:1px dashed #cbd5e1;border-radius:14px;padding:10px;color:#475569;margin:10px 0}.admin-renewal-clv-shell .clv-table{width:100%;border-collapse:separate;border-spacing:0 8px}.admin-renewal-clv-shell .clv-table th{text-align:left;font-size:.76rem;color:#64748b;text-transform:uppercase;letter-spacing:.04em}.admin-renewal-clv-shell .clv-table td{background:#f8fafc;border-top:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;padding:10px}.admin-renewal-clv-shell .clv-table td:first-child{border-left:1px solid #e2e8f0;border-radius:14px 0 0 14px}.admin-renewal-clv-shell .clv-table td:last-child{border-right:1px solid #e2e8f0;border-radius:0 14px 14px 0}@media(max-width:920px){.admin-renewal-clv-shell .clv-grid,.admin-renewal-clv-shell .clv-field-grid{grid-template-columns:1fr}.admin-renewal-clv-shell .clv-row-top{display:grid}.admin-renewal-clv-shell .clv-table{display:block;overflow:auto}}
            </style>

            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka Renewal & CLV Center.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/renewal-clv'); ?>

                <div class="clv-toolbar">
                    <form method="get" class="admin-filter-form" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
                        <label>Rentang analisa
                            <select name="days" onchange="this.form.submit()">
                                <?php foreach ([30=>'30 hari',90=>'90 hari',180=>'180 hari',365=>'1 tahun',730=>'2 tahun',3650=>'Semua data'] as $value=>$label): ?>
                                    <option value="<?= (int)$value; ?>" <?= $days===$value?'selected':''; ?>><?= esc($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <noscript><button class="admin-btn admin-btn--soft" type="submit">Terapkan</button></noscript>
                    </form>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/renewal-clv?days=' . $days . '&export=opportunities')); ?>">Export Opportunity</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/renewal-clv?days=' . $days . '&export=profiles')); ?>">Export Customer</a>
                    </div>
                </div>

                <div class="admin-grid admin-grid--stats">
                    <div class="admin-card"><span class="admin-badge">Customer</span><h2><?= (int)($summary['customer_count'] ?? 0); ?></h2><p>Profil customer/buyer.</p></div>
                    <div class="admin-card"><span class="admin-badge">Revenue</span><h2><?= esc(rupiah((int)($summary['revenue'] ?? 0))); ?></h2><p>Estimasi paid customer.</p></div>
                    <div class="admin-card"><span class="admin-badge">Avg CLV</span><h2><?= esc(rupiah((int)($summary['avg_clv'] ?? 0))); ?></h2><p>Rata-rata nilai customer.</p></div>
                    <div class="admin-card"><span class="admin-badge">Renewal</span><h2><?= (int)($summary['renewal_count'] ?? 0); ?></h2><p>Peluang perpanjangan.</p></div>
                    <div class="admin-card"><span class="admin-badge">Upgrade</span><h2><?= (int)($summary['upgrade_count'] ?? 0); ?></h2><p>Peluang cross-sell/upgrade.</p></div>
                </div>

                <div class="clv-grid">
                    <div class="clv-card">
                        <h2>Action Plan Renewal & Upgrade</h2>
                        <p class="admin-muted">Urut berdasarkan prioritas. Admin bisa langsung follow-up manual via WhatsApp tanpa butuh sistem auto-blast.</p>
                        <div class="clv-list">
                            <?php foreach (array_slice($opportunities, 0, 18) as $row): $customer = (array)($row['customer'] ?? []); $sub = (array)($row['subscription'] ?? []); ?>
                                <article class="clv-row">
                                    <div class="clv-row-top">
                                        <div>
                                            <span class="clv-pill"><?= esc(strtoupper((string)($row['type'] ?? 'action'))); ?> · Prioritas <?= (int)($row['priority'] ?? 0); ?></span>
                                            <h3><?= esc((string)($row['title'] ?? 'Follow-up customer')); ?></h3>
                                            <p><?= esc((string)($customer['name'] ?? 'Customer')); ?> · <?= esc((string)($customer['email'] ?? $customer['phone'] ?? '-')); ?> · <?= esc((string)($customer['segment'] ?? '')); ?></p>
                                            <p>Produk: <?= esc((string)($sub['product_title'] ?? $customer['last_product'] ?? '-')); ?><?php if (isset($row['days_left']) && $row['days_left'] !== null): ?> · H<?= esc((string)$row['days_left']); ?><?php endif; ?></p>
                                        </div>
                                        <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                                            <?php if (!empty($row['wa_url'])): ?><a class="admin-btn admin-btn--primary" href="<?= esc((string)$row['wa_url']); ?>" target="_blank" rel="noopener">Follow-up WA</a><?php endif; ?>
                                            <?php if (!empty($sub['renewal_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$sub['renewal_url']); ?>" target="_blank" rel="noopener">Link Renewal</a><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="clv-message"><?= esc((string)($row['message'] ?? '')); ?></div>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$opportunities): ?><p class="admin-muted">Belum ada opportunity renewal/upgrade dari data order dan subscription saat ini.</p><?php endif; ?>
                        </div>
                    </div>

                    <form method="post" class="clv-card">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_renewal_clv">
                        <h2>Setting Renewal & CLV</h2>
                        <label class="clv-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan Renewal & CLV Center</label>
                        <div class="clv-field-grid">
                            <label>Jendela renewal, hari<input type="number" name="renewal_window_days" min="1" max="120" value="<?= esc((string)($settings['renewal_window_days'] ?? 14)); ?>"></label>
                            <label>Winback setelah expired, hari<input type="number" name="winback_after_days" min="1" max="365" value="<?= esc((string)($settings['winback_after_days'] ?? 7)); ?>"></label>
                            <label>Ambang CLV tinggi<input name="clv_high_threshold" value="<?= esc((string)($settings['clv_high_threshold'] ?? 1000000)); ?>"></label>
                            <label>Ambang CLV menengah<input name="clv_medium_threshold" value="<?= esc((string)($settings['clv_medium_threshold'] ?? 250000)); ?>"></label>
                            <label>Minimal paid order untuk upgrade<input type="number" name="upgrade_min_paid_orders" min="1" max="50" value="<?= esc((string)($settings['upgrade_min_paid_orders'] ?? 2)); ?>"></label>
                            <label>Minimal revenue upgrade<input name="upgrade_min_revenue" value="<?= esc((string)($settings['upgrade_min_revenue'] ?? 300000)); ?>"></label>
                        </div>
                        <label>Template renewal<textarea name="renewal_template"><?= esc((string)($settings['renewal_template'] ?? '')); ?></textarea></label>
                        <label>Template upgrade/cross-sell<textarea name="upgrade_template"><?= esc((string)($settings['upgrade_template'] ?? '')); ?></textarea></label>
                        <label>Template winback expired<textarea name="winback_template"><?= esc((string)($settings['winback_template'] ?? '')); ?></textarea><small>Placeholder: {name}, {product}, {expires_at}, {renewal_url}, {order_ref}, {site_name}</small></label>
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Setting</button>
                    </form>
                </div>

                <div class="clv-card">
                    <h2>Customer Lifetime Value</h2>
                    <table class="clv-table">
                        <thead><tr><th>Customer</th><th>Segment</th><th>Revenue</th><th>Paid</th><th>AOV</th><th>Subscription</th><th>Lisensi</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($profiles, 0, 35) as $profile): ?>
                                <tr>
                                    <td><strong><?= esc((string)($profile['name'] ?? 'Customer')); ?></strong><br><small><?= esc((string)($profile['email'] ?? $profile['phone'] ?? '-')); ?></small></td>
                                    <td><?= esc((string)($profile['segment'] ?? '-')); ?></td>
                                    <td><?= esc(rupiah((int)($profile['revenue'] ?? 0))); ?></td>
                                    <td><?= (int)($profile['paid_orders'] ?? 0); ?> / <?= (int)($profile['orders'] ?? 0); ?></td>
                                    <td><?= esc(rupiah((int)($profile['aov'] ?? 0))); ?></td>
                                    <td>Aktif <?= (int)($profile['active_subscriptions'] ?? 0); ?> · Expired <?= (int)($profile['expired_subscriptions'] ?? 0); ?></td>
                                    <td><?= (int)($profile['license_count'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$profiles): ?><tr><td colspan="7">Belum ada data customer/order.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
