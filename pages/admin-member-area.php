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

function admin_member_area_logged_in(): bool
{
    return function_exists('admin_panel_logged_in') ? admin_panel_logged_in() : !empty($_SESSION['admin_articles_logged_in']);
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/member-area');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_member_area_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            redirect_302('admin/member-area');
        }
        $error = 'Password admin salah.';
    } else {
        $action = (string)($_POST['form_action'] ?? '');
        if ($action === 'save_member_area') {
            $settings = [
                'enabled' => !empty($_POST['enabled']),
                'auto_enroll_when_paid' => !empty($_POST['auto_enroll_when_paid']),
                'paid_statuses' => (array)($_POST['paid_statuses'] ?? ['Lunas']),
                'show_on_invoice' => !empty($_POST['show_on_invoice']),
                'show_on_order_status' => !empty($_POST['show_on_order_status']),
                'default_member_access_days' => (int)($_POST['default_member_access_days'] ?? 365),
                'default_license_duration_days' => (int)($_POST['default_license_duration_days'] ?? 365),
                'license_prefix' => (string)($_POST['license_prefix'] ?? 'UGR'),
                'login_hint' => (string)($_POST['login_hint'] ?? ''),
                'public_note' => (string)($_POST['public_note'] ?? ''),
                'pending_note' => (string)($_POST['pending_note'] ?? ''),
                'customer_message_template' => (string)($_POST['customer_message_template'] ?? ''),
            ];
            if (member_access_write_settings($settings)) {
                redirect_302('admin/member-area?message=' . rawurlencode('Pengaturan Member Area berhasil disimpan.'));
            }
            $error = 'Pengaturan belum bisa disimpan. Pastikan folder storage writable.';
        } elseif ($action === 'issue_member_access') {
            $orderId = member_access_clean((string)($_POST['order_id'] ?? ''), 120);
            $order = function_exists('order_find_by_id') ? order_find_by_id($orderId) : null;
            if (!$order) {
                $error = 'Order tidak ditemukan.';
            } else {
                $result = member_access_issue_for_order($order, 'admin_manual');
                if (!empty($result['ok'])) {
                    redirect_302('admin/member-area?message=' . rawurlencode('Akses member/course/lisensi berhasil dibuat atau diperbarui.'));
                }
                $error = (string)($result['message'] ?? 'Akses member belum bisa dibuat.');
            }
        }
    }
}

$loggedIn = admin_member_area_logged_in();
$settings = $loggedIn ? member_access_read_settings() : member_access_default_settings();
$summary = $loggedIn ? member_access_summary() : [];
$paidOptions = function_exists('order_allowed_payment_statuses') ? order_allowed_payment_statuses() : ['Belum Ditagih', 'Menunggu Pembayaran', 'DP Masuk', 'Lunas', 'Tidak Perlu Payment'];
$memberOrders = [];
if ($loggedIn && function_exists('order_read_all')) {
    foreach (order_read_all(0, ['_all_time' => true], 2000) as $order) {
        $product = member_access_product_for_order($order);
        if (member_access_product_enabled($product, $order)) {
            $memberOrders[] = $order;
        }
        if (count($memberOrders) >= 60) {
            break;
        }
    }
}

set_seo([
    'title' => 'Member Area, Course & License Access - Admin',
    'description' => 'Kelola akses member, course, template, produk digital, dan license key setelah pembayaran valid.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="main-content" class="admin-shell admin-member-area-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Member Area</div>
                <h1>Member Area, Buyer Account & Magic Login</h1>
                <p>Kelola akses course, template, file digital, buyer account, magic login, dan license key setelah pembayaran customer valid.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/produk')); ?>">Atur Produk</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('member-area')); ?>" target="_blank" rel="noopener">Lihat Member Area</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <style>
                .admin-member-area-shell .ma-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:18px;align-items:start}.admin-member-area-shell .ma-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-member-area-shell .ma-card h2,.admin-member-area-shell .ma-card h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-member-area-shell .ma-card p{color:#64748b;margin:.25rem 0 .85rem}.admin-member-area-shell .ma-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}.admin-member-area-shell label{display:grid;gap:7px;color:#334155;font-weight:850;font-size:.86rem}.admin-member-area-shell input,.admin-member-area-shell select,.admin-member-area-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;background:#fff;color:#0f172a}.admin-member-area-shell select:not([multiple]){min-height:46px;appearance:auto}.admin-member-area-shell select option{background:#fff;color:#0f172a}.admin-member-area-shell textarea{min-height:100px}.admin-member-area-shell small{color:#64748b;font-weight:700;line-height:1.55}.admin-member-area-shell .ma-check{display:flex!important;align-items:flex-start;gap:10px;line-height:1.45}.admin-member-area-shell .ma-check input{width:auto;min-width:16px;margin-top:3px}.admin-member-area-shell .ma-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:18px;padding-top:16px;border-top:1px solid #e2e8f0}.admin-member-area-shell .ma-actions .admin-btn{position:relative;z-index:1;margin:0}.admin-member-area-shell .ma-records{display:grid;gap:10px}.admin-member-area-shell .ma-record{border:1px solid #e2e8f0;border-radius:18px;padding:12px;background:#f8fafc}.admin-member-area-shell .ma-record strong{display:block;color:#0f172a}.admin-member-area-shell .ma-record small{display:block;color:#64748b;margin-top:.25rem}.admin-member-area-shell .ma-url{word-break:break-all;background:#fff;border:1px solid #dbeafe;border-radius:14px;padding:8px;margin-top:.55rem}.admin-member-area-shell .ma-order-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;border:1px solid #e2e8f0;border-radius:18px;padding:12px;background:#fff}.admin-member-area-shell .ma-badge{display:inline-flex;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-weight:900;padding:4px 9px;font-size:.78rem}.admin-member-area-shell .ma-badge--license{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.admin-member-area-shell .ma-badge--warn{background:#fef2f2;border-color:#fecaca;color:#991b1b}@media(max-width:920px){.admin-member-area-shell .ma-grid,.admin-member-area-shell .ma-field-grid,.admin-member-area-shell .ma-order-row{grid-template-columns:1fr}}
            </style>
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka Member Area Center.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/member-area'); ?>
                <div class="admin-grid admin-grid--stats">
                    <div class="admin-card"><span class="admin-badge">Status</span><h2><?= !empty($settings['enabled']) ? 'Aktif' : 'Nonaktif'; ?></h2><p>Member area public.</p></div>
                    <div class="admin-card"><span class="admin-badge">Akses Aktif</span><h2><?= (int)($summary['active'] ?? 0); ?></h2><p>Member aktif.</p></div>
                    <div class="admin-card"><span class="admin-badge">Course</span><h2><?= (int)($summary['course'] ?? 0); ?></h2><p>Akses berisi modul.</p></div>
                    <div class="admin-card"><span class="admin-badge">Lisensi</span><h2><?= (int)($summary['license'] ?? 0); ?></h2><p>License key dibuat.</p></div>
                    <div class="admin-card"><span class="admin-badge">Order Siap</span><h2><?= count($memberOrders); ?></h2><p>Produk member/course/license.</p></div>
                </div>

                <div class="ma-grid">
                    <form method="post" class="ma-card" data-admin-page-tab-scope>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_member_area">
                        <div class="admin-page-subtabs admin-page-subtabs--3" role="tablist" aria-label="Bagian Member Area">
                            <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="ma-basic"><span>1. Dasar</span><small>Aktif & rilis</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="ma-license"><span>2. Lisensi</span><small>Prefix & masa</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="ma-message"><span>3. Pesan</span><small>WA & catatan</small></button>
                        </div>
                        <div class="admin-page-tab-panel is-active" data-admin-page-tab-panel="ma-basic">
                            <h2>Aturan Akses Member</h2>
                            <label class="ma-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan Member Area</label>
                            <label class="ma-check"><input type="checkbox" name="auto_enroll_when_paid" value="1" <?= !empty($settings['auto_enroll_when_paid']) ? 'checked' : ''; ?>> Otomatis buat akses setelah pembayaran valid</label>
                            <div class="ma-field-grid">
                                <label>Status pembayaran yang membuka akses member
                                    <select name="paid_statuses[]" aria-label="Status pembayaran yang membuka akses member area">
                                        <?php foreach ($paidOptions as $status): ?>
                                            <option value="<?= esc($status); ?>" <?= in_array($status, (array)($settings['paid_statuses'] ?? ['Lunas']), true) ? 'selected' : ''; ?>><?= esc($status); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small>Pilihan aman: <strong>Lunas</strong>. Akses member otomatis aktif saat order memakai status ini.</small>
                                </label>
                                <label>Masa akses member default, hari
                                    <input type="number" name="default_member_access_days" min="1" max="3650" value="<?= esc((string)($settings['default_member_access_days'] ?? 365)); ?>">
                                </label>
                            </div>
                            <label class="ma-check"><input type="checkbox" name="show_on_invoice" value="1" <?= !empty($settings['show_on_invoice']) ? 'checked' : ''; ?>> Tampilkan link member area di invoice saat aktif</label>
                            <label class="ma-check"><input type="checkbox" name="show_on_order_status" value="1" <?= !empty($settings['show_on_order_status']) ? 'checked' : ''; ?>> Tampilkan link member area di status order saat aktif</label>
                        </div>
                        <div class="admin-page-tab-panel" data-admin-page-tab-panel="ma-license" hidden>
                            <h2>Aturan Lisensi</h2>
                            <div class="ma-field-grid">
                                <label>Prefix license key
                                    <input name="license_prefix" maxlength="12" value="<?= esc((string)($settings['license_prefix'] ?? 'UGR')); ?>" placeholder="UGR">
                                </label>
                                <label>Masa lisensi default, hari
                                    <input type="number" name="default_license_duration_days" min="0" max="3650" value="<?= esc((string)($settings['default_license_duration_days'] ?? 365)); ?>">
                                </label>
                            </div>
                            <p class="admin-muted">Produk tetap bisa override seat, aktivasi, tipe lisensi, dan masa lisensi dari halaman edit produk.</p>
                        </div>
                        <div class="admin-page-tab-panel" data-admin-page-tab-panel="ma-message" hidden>
                            <h2>Pesan & Instruksi</h2>
                            <label>Hint login member<textarea name="login_hint"><?= esc((string)($settings['login_hint'] ?? '')); ?></textarea></label>
                            <label>Catatan area member<textarea name="public_note"><?= esc((string)($settings['public_note'] ?? '')); ?></textarea></label>
                            <label>Catatan saat belum aktif<textarea name="pending_note"><?= esc((string)($settings['pending_note'] ?? '')); ?></textarea></label>
                            <label>Template pesan WhatsApp akses<textarea name="customer_message_template"><?= esc((string)($settings['customer_message_template'] ?? '')); ?></textarea><small>Placeholder: {name}, {order_ref}, {product}, {member_url}, {license_key}, {instructions}, {site_name}</small></label>
                        </div>
                        <div class="ma-actions">
                            <button class="admin-btn admin-btn--primary" type="submit">Simpan Member Area</button>
                            <small>Pengaturan disimpan untuk rilis akses member/course/lisensi berikutnya.</small>
                        </div>
                    </form>

                    <aside class="ma-card">
                        <h2>Akses Member Terbaru</h2>
                        <div class="ma-records">
                            <?php foreach ((array)($summary['recent'] ?? []) as $record): ?>
                                <div class="ma-record">
                                    <strong><?= esc((string)($record['product_title'] ?? 'Produk Digital')); ?></strong>
                                    <small><?= esc((string)($record['order_ref'] ?? '-')); ?> · <?= esc((string)($record['customer_name'] ?? '-')); ?></small>
                                    <p>
                                        <?php if (!empty($record['course_modules'])): ?><span class="ma-badge">Course</span><?php endif; ?>
                                        <?php if (!empty($record['license']['enabled'])): ?><span class="ma-badge ma-badge--license">Lisensi</span><?php endif; ?>
                                    </p>
                                    <div class="ma-url"><code><?= esc((string)($record['public_url'] ?? '')); ?></code></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($summary['recent'])): ?><p class="admin-muted">Belum ada akses member.</p><?php endif; ?>
                        </div>
                    </aside>
                </div>

                <div class="ma-card">
                    <h2>Order Course / Digital / Lisensi</h2>
                    <p>Jika pembayaran sudah sesuai tapi link member belum muncul, admin bisa buat/perbarui akses secara manual.</p>
                    <div class="ma-records">
                        <?php foreach ($memberOrders as $order): ?>
                            <?php $record = member_access_record_for_order($order); $can = member_access_can_issue_for_order($order); $product = member_access_product_for_order($order); ?>
                            <div class="ma-order-row">
                                <div>
                                    <strong><?= esc((string)($order['product_title'] ?? $product['title'] ?? 'Produk Digital')); ?></strong>
                                    <small><?= esc(function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['ref'] ?? '-')); ?> · <?= esc((string)($order['name'] ?? '-')); ?> · Payment: <?= esc((string)($order['payment_status'] ?? '-')); ?></small><br>
                                    <?php if ($record): ?><span class="ma-badge">Akses aktif</span><?php else: ?><span class="ma-badge ma-badge--warn">Belum ada akses</span><?php endif; ?>
                                    <?php if (!$record && empty($can['ok'])): ?><small><?= esc((string)($can['reason'] ?? 'Belum bisa rilis.')); ?></small><?php endif; ?>
                                </div>
                                <form method="post">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="issue_member_access">
                                    <input type="hidden" name="order_id" value="<?= esc((string)($order['id'] ?? '')); ?>">
                                    <button class="admin-btn admin-btn--ghost" type="submit" <?= empty($can['ok']) ? 'disabled' : ''; ?>>Buat/Refresh Akses</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$memberOrders): ?><p class="admin-muted">Belum ada order produk member/course/lisensi.</p><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
