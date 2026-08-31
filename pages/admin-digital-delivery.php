<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();
$GLOBALS['admin_page'] = true;
$message = (string)($_GET['message'] ?? '');
$error = '';
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/digital-delivery');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_panel_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            redirect_302('admin/digital-delivery');
        }
        $error = 'Password admin salah.';
    } else {
        $action = (string)($_POST['form_action'] ?? '');
        if ($action === 'save_digital_delivery') {
            $paid = [];
            foreach ((array)($_POST['paid_statuses'] ?? []) as $status) {
                $status = digital_delivery_clean((string)$status, 80);
                if ($status !== '') {
                    $paid[] = $status;
                }
            }
            $settings = [
                'enabled' => !empty($_POST['enabled']),
                'auto_issue_when_paid' => !empty($_POST['auto_issue_when_paid']),
                'paid_statuses' => $paid,
                'issue_on_dp' => !empty($_POST['issue_on_dp']),
                'default_access_days' => (int)($_POST['default_access_days'] ?? 30),
                'default_download_limit' => (int)($_POST['default_download_limit'] ?? 5),
                'show_access_on_order_status' => !empty($_POST['show_access_on_order_status']),
                'show_access_on_invoice' => !empty($_POST['show_access_on_invoice']),
                'require_order_token' => !empty($_POST['require_order_token']),
                'public_note' => (string)($_POST['public_note'] ?? ''),
                'pending_note' => (string)($_POST['pending_note'] ?? ''),
                'customer_message_template' => (string)($_POST['customer_message_template'] ?? ''),
            ];
            if (digital_delivery_write_settings($settings)) {
                redirect_302('admin/digital-delivery?message=' . rawurlencode('Pengaturan Digital Delivery berhasil disimpan.'));
            }
            $error = 'Pengaturan belum bisa disimpan. Pastikan folder storage writable.';
        } elseif ($action === 'issue_access') {
            $orderId = digital_delivery_clean((string)($_POST['order_id'] ?? ''), 100);
            $order = $orderId !== '' && function_exists('order_find_by_id') ? order_find_by_id($orderId) : null;
            if (!$order) {
                $error = 'Order tidak ditemukan.';
            } else {
                $result = digital_delivery_issue_for_order($order, 'admin_manual');
                if (!empty($result['ok'])) {
                    redirect_302('admin/digital-delivery?message=' . rawurlencode('Akses digital berhasil dibuat/diperbarui.'));
                }
                $error = (string)($result['message'] ?? 'Akses digital belum bisa dibuat.');
            }
        }
    }
}

$loggedIn = admin_panel_logged_in();
$settings = $loggedIn ? digital_delivery_read_settings() : digital_delivery_default_settings();
$summary = $loggedIn ? digital_delivery_summary() : ['total' => 0, 'active' => 0, 'expired' => 0, 'downloads' => 0, 'recent' => []];
$orders = $loggedIn && function_exists('order_read_all') ? order_read_all(180, ['_all_time' => true], 5000) : [];
$digitalOrders = [];
foreach ($orders as $order) {
    $product = function_exists('digital_delivery_product_for_order') ? digital_delivery_product_for_order($order) : null;
    if (function_exists('digital_delivery_product_is_digital') && digital_delivery_product_is_digital($product, $order)) {
        $digitalOrders[] = $order;
    }
}
$paidOptions = function_exists('order_allowed_payment_statuses') ? order_allowed_payment_statuses() : ['Lunas', 'DP Masuk'];

set_seo([
    'title' => 'Digital Product Delivery - Admin',
    'description' => 'Kelola rilis akses e-book, template, course, file ZIP, dan produk digital setelah pembayaran terkonfirmasi.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="main-content" class="admin-shell admin-digital-delivery-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Digital Product Delivery</div>
                <h1>Digital Product Delivery Center</h1>
                <p>Rilis akses produk digital setelah pembayaran valid: e-book, template, course, file ZIP, link akses, bundle, dan karya digital personal brand.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/produk')); ?>">Atur Produk Digital</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/orders')); ?>">Order</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <style>
                .admin-digital-delivery-shell .ddl-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:18px;align-items:start}.admin-digital-delivery-shell .ddl-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-digital-delivery-shell .ddl-card h2,.admin-digital-delivery-shell .ddl-card h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-digital-delivery-shell .ddl-card p{color:#64748b;margin:.25rem 0 .85rem}.admin-digital-delivery-shell .ddl-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:start}.admin-digital-delivery-shell label{display:grid;gap:7px;color:#334155;font-weight:800;font-size:.86rem}.admin-digital-delivery-shell input,.admin-digital-delivery-shell select,.admin-digital-delivery-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;background:#fff;color:#0f172a}.admin-digital-delivery-shell select:not([multiple]){min-height:46px;appearance:auto}.admin-digital-delivery-shell textarea{min-height:100px}.admin-digital-delivery-shell small{color:#64748b;font-weight:700;line-height:1.55}.admin-digital-delivery-shell .ddl-check{display:flex!important;align-items:flex-start;gap:10px;line-height:1.45}.admin-digital-delivery-shell .ddl-check input{width:auto;min-width:16px;margin-top:3px}.admin-digital-delivery-shell .ddl-check small{display:block;margin-top:3px}.admin-digital-delivery-shell .ddl-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:18px;padding-top:16px;border-top:1px solid #e2e8f0}.admin-digital-delivery-shell .ddl-actions .admin-btn{position:relative;z-index:1;margin:0}.admin-digital-delivery-shell .ddl-records{display:grid;gap:10px}.admin-digital-delivery-shell .ddl-record{border:1px solid #e2e8f0;border-radius:18px;padding:12px;background:#f8fafc}.admin-digital-delivery-shell .ddl-record strong{display:block;color:#0f172a}.admin-digital-delivery-shell .ddl-record small{display:block;color:#64748b;margin-top:.25rem}.admin-digital-delivery-shell .ddl-url{word-break:break-all;background:#fff;border:1px solid #dbeafe;border-radius:14px;padding:8px;margin-top:.55rem}.admin-digital-delivery-shell .ddl-order-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid #e2e8f0;border-radius:18px;padding:12px;background:#fff}.admin-digital-delivery-shell .ddl-badge{display:inline-flex;border-radius:999px;background:color-mix(in srgb,var(--bg) 82%,#fff);border:1px solid var(--border);color:var(--admin-primary);font-weight:900;padding:4px 9px;font-size:.78rem}.admin-digital-delivery-shell .ddl-badge--warn{background:#fff7ed;border-color:#fed7aa;color:#9a3412}@media(max-width:920px){.admin-digital-delivery-shell .ddl-grid,.admin-digital-delivery-shell .ddl-field-grid,.admin-digital-delivery-shell .ddl-order-row{grid-template-columns:1fr}}
            </style>
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka Digital Delivery Center.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/digital-delivery'); ?>
                <div class="admin-grid admin-grid--stats">
                    <div class="admin-card"><span class="admin-badge">Status</span><h2><?= !empty($settings['enabled']) ? 'Aktif' : 'Nonaktif'; ?></h2><p>Digital delivery center.</p></div>
                    <div class="admin-card"><span class="admin-badge">Akses Aktif</span><h2><?= (int)($summary['active'] ?? 0); ?></h2><p>Link akses yang masih aktif.</p></div>
                    <div class="admin-card"><span class="admin-badge">Total Akses</span><h2><?= (int)($summary['total'] ?? 0); ?></h2><p>Semua akses yang pernah dibuat.</p></div>
                    <div class="admin-card"><span class="admin-badge">Download</span><h2><?= (int)($summary['downloads'] ?? 0); ?></h2><p>Total klik download tercatat.</p></div>
                    <div class="admin-card"><span class="admin-badge">Order Digital</span><h2><?= count($digitalOrders); ?></h2><p>Order produk digital terdeteksi.</p></div>
                </div>

                <div class="ddl-grid">
                    <form method="post" class="ddl-card" data-admin-page-tab-scope>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_digital_delivery">
                        <div class="admin-page-subtabs admin-page-subtabs--3" role="tablist" aria-label="Bagian Digital Delivery">
                            <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="ddl-basic"><span>1. Dasar</span><small>Aktif & rilis</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="ddl-security"><span>2. Proteksi</span><small>Token & limit</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="ddl-message"><span>3. Pesan</span><small>WA customer</small></button>
                        </div>
                        <div class="admin-page-tab-panel is-active" data-admin-page-tab-panel="ddl-basic">
                            <h2>Aturan Rilis Akses</h2>
                            <p>Rekomendasi aman: akses digital otomatis aktif hanya ketika status pembayaran Lunas.</p>
                            <label class="ddl-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan Digital Delivery Center</label>
                            <label class="ddl-check"><input type="checkbox" name="auto_issue_when_paid" value="1" <?= !empty($settings['auto_issue_when_paid']) ? 'checked' : ''; ?>> Otomatis buat akses saat payment status memenuhi aturan</label>
                            <div class="ddl-field-grid">
                                <label>Status pembayaran yang membuka akses
                                    <select name="paid_statuses[]" aria-label="Status pembayaran yang membuka akses digital">
                                        <?php foreach ($paidOptions as $status): ?>
                                            <option value="<?= esc($status); ?>" <?= in_array($status, (array)($settings['paid_statuses'] ?? ['Lunas']), true) ? 'selected' : ''; ?>><?= esc($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>Pilihan aman: <strong>Lunas</strong>. Akses digital otomatis aktif saat order memakai status ini.</small>
                                </label>
                                <label class="ddl-check"><input type="checkbox" name="issue_on_dp" value="1" <?= !empty($settings['issue_on_dp']) ? 'checked' : ''; ?>> <span>Izinkan DP Masuk ikut membuka akses <small>Gunakan hanya untuk produk yang memang boleh akses setelah DP.</small></span></label>
                            </div>
                        </div>
                        <div class="admin-page-tab-panel" data-admin-page-tab-panel="ddl-security" hidden>
                            <h2>Proteksi Akses</h2>
                            <div class="ddl-field-grid">
                                <label>Masa aktif akses, hari
                                    <input type="number" name="default_access_days" min="1" max="3650" value="<?= esc((string)($settings['default_access_days'] ?? 30)); ?>">
                                </label>
                                <label>Limit download, 0 = tanpa limit
                                    <input type="number" name="default_download_limit" min="0" max="9999" value="<?= esc((string)($settings['default_download_limit'] ?? 5)); ?>">
                                </label>
                            </div>
                            <label class="ddl-check"><input type="checkbox" name="show_access_on_order_status" value="1" <?= !empty($settings['show_access_on_order_status']) ? 'checked' : ''; ?>> Tampilkan akses di halaman status order saat aktif</label>
                            <label class="ddl-check"><input type="checkbox" name="show_access_on_invoice" value="1" <?= !empty($settings['show_access_on_invoice']) ? 'checked' : ''; ?>> Tampilkan akses di invoice saat aktif</label>
                            <label class="ddl-check"><input type="checkbox" name="require_order_token" value="1" <?= !empty($settings['require_order_token']) ? 'checked' : ''; ?>> Wajib cocok dengan token order privat</label>
                        </div>
                        <div class="admin-page-tab-panel" data-admin-page-tab-panel="ddl-message" hidden>
                            <h2>Pesan & Instruksi Publik</h2>
                            <label>Catatan saat akses aktif<textarea name="public_note"><?= esc((string)($settings['public_note'] ?? '')); ?></textarea></label>
                            <label>Catatan saat akses belum aktif<textarea name="pending_note"><?= esc((string)($settings['pending_note'] ?? '')); ?></textarea></label>
                            <label>Template pesan WhatsApp customer<textarea name="customer_message_template"><?= esc((string)($settings['customer_message_template'] ?? '')); ?></textarea><small>Placeholder: {name}, {order_ref}, {product}, {access_url}, {instructions}, {site_name}</small></label>
                        </div>
                        <div class="ddl-actions">
                            <button class="admin-btn admin-btn--primary" type="submit">Simpan Digital Delivery</button>
                            <small>Pengaturan disimpan untuk rilis akses produk digital berikutnya.</small>
                        </div>
                    </form>

                    <aside class="ddl-card">
                        <h2>Akses Terbaru</h2>
                        <div class="ddl-records">
                            <?php foreach ((array)($summary['recent'] ?? []) as $record): ?>
                                <div class="ddl-record">
                                    <strong><?= esc((string)($record['product_title'] ?? 'Produk Digital')); ?></strong>
                                    <small><?= esc((string)($record['order_ref'] ?? '-')); ?> · <?= esc((string)($record['customer_name'] ?? '-')); ?> · Download <?= (int)($record['download_count'] ?? 0); ?></small>
                                    <div class="ddl-url"><code><?= esc((string)($record['public_url'] ?? '')); ?></code></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['recent'])): ?><p class="admin-muted">Belum ada akses digital yang dibuat.</p><?php endif; ?>
                        </div>
                    </aside>
                </div>

                <div class="ddl-card">
                    <h2>Order Digital yang Bisa Diproses</h2>
                    <p>Jika payment status sudah sesuai tapi akses belum muncul, admin bisa klik buat/perbarui akses secara manual.</p>
                    <div class="ddl-records">
                        <?php foreach (array_slice($digitalOrders, 0, 30) as $order): ?>
                            <?php $record = digital_delivery_record_for_order($order); $can = digital_delivery_order_can_issue($order); ?>
                            <div class="ddl-order-row">
                                <div>
                                    <strong><?= esc((string)($order['product_title'] ?? 'Produk Digital')); ?></strong>
                                    <small><?= esc(function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? '-')); ?> · <?= esc((string)($order['name'] ?? '-')); ?> · Payment: <?= esc((string)($order['payment_status'] ?? '-')); ?></small><br>
                                    <?php if ($record): ?><span class="ddl-badge">Akses aktif</span><?php else: ?><span class="ddl-badge ddl-badge--warn">Belum ada akses</span><?php endif; ?>
                                    <?php if (!$record && empty($can['ok'])): ?><small><?= esc((string)($can['reason'] ?? 'Belum bisa rilis.')); ?></small><?php endif; ?>
                                </div>
                                <form method="post">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="issue_access">
                                    <input type="hidden" name="order_id" value="<?= esc((string)($order['id'] ?? '')); ?>">
                                    <button class="admin-btn admin-btn--ghost" type="submit" <?= empty($can['ok']) ? 'disabled' : ''; ?>>Buat/Refresh Akses</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$digitalOrders): ?><p class="admin-muted">Belum ada order produk digital.</p><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
