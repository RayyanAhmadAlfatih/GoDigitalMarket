<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$message = '';
$error = '';

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/payment-gateway');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_panel_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'payment_gateway']);
            }
            redirect_302('admin/payment-gateway');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'save_payment_gateway') {
        $current = payment_gateway_read_settings();
        $settings = [
            'enabled' => !empty($_POST['enabled']),
            'default_provider' => payment_gateway_slug((string)($_POST['default_provider'] ?? 'midtrans')),
            'default_expiry_hours' => max(1, min(168, (int)($_POST['default_expiry_hours'] ?? 24))),
            'auto_update_order_default' => !empty($_POST['auto_update_order_default']),
            'safe_mode' => !empty($_POST['safe_mode']),
            'public_note' => payment_gateway_multiline_clean((string)($_POST['public_note'] ?? ''), 800),
            'providers' => [],
        ];

        $postedProviders = is_array($_POST['providers'] ?? null) ? $_POST['providers'] : [];
        foreach (array_keys(payment_gateway_provider_definitions()) as $key) {
            $row = is_array($postedProviders[$key] ?? null) ? $postedProviders[$key] : [];
            $old = is_array($current['providers'][$key] ?? null) ? $current['providers'][$key] : payment_gateway_default_provider($key);
            $provider = [
                'enabled' => !empty($row['enabled']),
                'mode' => in_array((string)($row['mode'] ?? 'sandbox'), ['sandbox', 'production'], true) ? (string)$row['mode'] : 'sandbox',
                'auto_update_order' => !empty($row['auto_update_order']),
                'webhook_enabled' => !empty($row['webhook_enabled']),
                'merchant_id' => payment_gateway_clean((string)($row['merchant_id'] ?? ''), 120),
                'business_id' => payment_gateway_clean((string)($row['business_id'] ?? ''), 120),
                'note' => payment_gateway_multiline_clean((string)($row['note'] ?? ''), 600),
            ];

            foreach (['server_key', 'client_key', 'secret_key', 'public_key', 'webhook_secret', 'callback_token', 'validation_token'] as $secretField) {
                if (!empty($row['clear_' . $secretField])) {
                    $provider[$secretField] = '';
                    continue;
                }
                $incoming = payment_gateway_secret_clean((string)($row[$secretField] ?? ''), 260);
                $provider[$secretField] = $incoming !== '' ? $incoming : (string)($old[$secretField] ?? '');
            }

            $settings['providers'][$key] = $provider;
        }

        if (payment_gateway_write_settings($settings)) {
            redirect_302('admin/payment-gateway?saved=1');
        }
        $error = 'Payment gateway settings belum bisa disimpan. Pastikan folder storage writable.';
    }
}

if (!empty($_GET['saved'])) {
    $message = 'Payment gateway bridge berhasil disimpan.';
}

$loggedIn = admin_panel_logged_in();
$settings = $loggedIn ? payment_gateway_read_settings() : payment_gateway_default_settings();
$summary = $loggedIn ? payment_gateway_summary() : [];
$events = $loggedIn ? payment_gateway_read_webhook_events(90, 20) : [];
$defs = payment_gateway_provider_definitions();

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Pembayaran Otomatis - Admin',
    'description' => 'Konfigurasi Midtrans, Xendit, Flip, create payment link/token, webhook, API key, dan audit callback payment gateway.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-payment-gateway-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Pembayaran Otomatis</div>
                <h1>Midtrans, Xendit, Flip Payment Link</h1>
                <p>Siapkan provider, API credential, create payment link/token, webhook URL, audit callback, dan update status otomatis tanpa mengganggu pembayaran manual yang sudah jalan.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <style>
                .admin-payment-gateway-shell .pg-alert{margin-bottom:16px;padding:13px 15px;border-radius:16px;font-weight:800}.admin-payment-gateway-shell .pg-alert--success{background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--admin-primary)}.admin-payment-gateway-shell .pg-alert--danger{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}.admin-payment-gateway-shell .pg-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:18px;align-items:start}.admin-payment-gateway-shell .pg-form{display:grid;gap:16px}.admin-payment-gateway-shell .pg-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-payment-gateway-shell .pg-card h2,.admin-payment-gateway-shell .pg-card h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-payment-gateway-shell .pg-card p{color:#64748b;margin:.2rem 0 .9rem}.admin-payment-gateway-shell .pg-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-payment-gateway-shell label{display:grid;gap:7px;color:#334155;font-weight:800;font-size:.86rem}.admin-payment-gateway-shell input,.admin-payment-gateway-shell textarea,.admin-payment-gateway-shell select{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;color:#0f172a;background:#fff}.admin-payment-gateway-shell textarea{min-height:88px;resize:vertical}.admin-payment-gateway-shell .pg-check{display:flex!important;align-items:center;gap:8px}.admin-payment-gateway-shell .pg-check input{width:auto}.admin-payment-gateway-shell .pg-provider{border:1px solid #dbeafe;background:#f8fbff;border-radius:22px;padding:15px;display:grid;gap:12px}.admin-payment-gateway-shell .pg-provider__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.admin-payment-gateway-shell .pg-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:.75rem;font-weight:900;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.admin-payment-gateway-shell .pg-badge--ok{background:color-mix(in srgb,var(--bg) 82%,#ffffff);color:var(--admin-primary);border-color:var(--border)}.admin-payment-gateway-shell .pg-badge--warn{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff);color:var(--admin-primary-dark);border-color:color-mix(in srgb,var(--admin-primary) 22%,#ffffff)}.admin-payment-gateway-shell .pg-muted{color:#64748b;font-size:.86rem}.admin-payment-gateway-shell .pg-url{display:flex;gap:8px;align-items:center;border:1px solid #dbeafe;background:#fff;border-radius:14px;padding:8px 10px;word-break:break-all}.admin-payment-gateway-shell .pg-url code{font-size:.78rem;color:#0f172a}.admin-payment-gateway-shell .pg-events{display:grid;gap:10px}.admin-payment-gateway-shell .pg-event{border:1px solid #e2e8f0;border-radius:18px;padding:12px;background:#fff}.admin-payment-gateway-shell .pg-event strong{display:block;color:#0f172a}.admin-payment-gateway-shell .pg-secret-note{font-size:.8rem;color:#64748b;margin-top:-2px}.admin-payment-gateway-shell .pg-danger-zone{border-color:#fecaca;background:#fff7f7}@media(max-width:920px){.admin-payment-gateway-shell .pg-grid,.admin-payment-gateway-shell .pg-field-grid{grid-template-columns:1fr}.admin-payment-gateway-shell .pg-provider__head{display:block}}
            </style>
            <?php if ($message): ?><div class="pg-alert pg-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="pg-alert pg-alert--danger"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka fondasi payment gateway.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/payment-gateway'); ?>
                <div class="pg-grid">
                    <form method="post" class="pg-form" data-admin-page-tab-scope>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_payment_gateway">

                        <div class="admin-page-subtabs admin-page-subtabs--5" role="tablist" aria-label="Bagian Payment Gateway">
                            <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="pg-global"><span>1. Global Gateway Switch</span><small>Aktif & default</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="pg-provider-midtrans"><span>2. Midtrans</span><small>Snap/Core API</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="pg-provider-xendit"><span>3. Xendit</span><small>Invoice/payment</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="pg-provider-flip"><span>4. Flip</span><small>Payment/webhook</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="pg-other"><span>5. Gateway Lainnya</span><small>Rencana provider</small></button>
                        </div>
                        <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian payment gateway</span><select data-admin-page-tab-select aria-label="Pilih bagian Payment Gateway"><option value="pg-global">1. Global Gateway Switch</option><option value="pg-provider-midtrans">2. Midtrans</option><option value="pg-provider-xendit">3. Xendit</option><option value="pg-provider-flip">4. Flip</option><option value="pg-other">5. Gateway Lainnya</option></select></label></div>

                        <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="pg-global">
                        <section class="pg-card">
                            <h2>Global Gateway Switch</h2>
                            <p>Default aman: gateway hanya membuat payment link untuk order yang memang mengizinkan pembayaran otomatis sesuai policy produk.</p>
                            <div class="pg-field-grid">
                                <label class="pg-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan payment gateway bridge</label>
                                <label class="pg-check"><input type="checkbox" name="safe_mode" value="1" <?= !empty($settings['safe_mode']) ? 'checked' : ''; ?>> Safe mode aktif</label>
                                <label>Provider default
                                    <select name="default_provider">
                                        <?php foreach ($defs as $key => $def): ?>
                                            <option value="<?= esc((string)$key); ?>" <?= ((string)($settings['default_provider'] ?? '') === (string)$key) ? 'selected' : ''; ?>><?= esc((string)$def['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Expiry default invoice gateway (jam)
                                    <input type="number" name="default_expiry_hours" min="1" max="168" value="<?= esc((string)($settings['default_expiry_hours'] ?? 24)); ?>">
                                </label>
                            </div>
                            <label class="pg-check"><input type="checkbox" name="auto_update_order_default" value="1" <?= !empty($settings['auto_update_order_default']) ? 'checked' : ''; ?>> Jadikan auto update order sebagai default provider baru</label>
                            <label>Catatan pembayaran
                                <textarea name="public_note"><?= esc((string)($settings['public_note'] ?? '')); ?></textarea>
                            </label>
                        </section>
                        </section>

                        <?php foreach ($defs as $key => $def): ?>
                            <?php
                                $provider = is_array($settings['providers'][$key] ?? null) ? $settings['providers'][$key] : payment_gateway_default_provider((string)$key);
                                $configured = false;
                                foreach ((array)($def['secret_fields'] ?? []) as $secretField) {
                                    if (trim((string)($provider[$secretField] ?? '')) !== '') {
                                        $configured = true;
                                    }
                                }
                            ?>
                            <section class="admin-page-tab-panel" data-admin-page-tab-panel="pg-provider-<?= esc((string)$key); ?>" hidden>
                            <section class="pg-provider">
                                <div class="pg-provider__head">
                                    <div>
                                        <span class="pg-badge <?= !empty($provider['enabled']) ? 'pg-badge--ok' : 'pg-badge--warn'; ?>"><?= !empty($provider['enabled']) ? 'Aktif' : 'Nonaktif'; ?></span>
                                        <h2><?= esc((string)$def['label']); ?></h2>
                                        <p><?= esc((string)$def['description']); ?></p>
                                    </div>
                                    <span class="pg-badge <?= $configured ? 'pg-badge--ok' : 'pg-badge--warn'; ?>"><?= $configured ? 'Credential tersimpan' : 'Belum lengkap'; ?></span>
                                </div>
                                <div class="pg-field-grid">
                                    <label class="pg-check"><input type="checkbox" name="providers[<?= esc((string)$key); ?>][enabled]" value="1" <?= !empty($provider['enabled']) ? 'checked' : ''; ?>> Aktifkan provider</label>
                                    <label class="pg-check"><input type="checkbox" name="providers[<?= esc((string)$key); ?>][webhook_enabled]" value="1" <?= !empty($provider['webhook_enabled']) ? 'checked' : ''; ?>> Terima webhook</label>
                                    <label>Mode
                                        <select name="providers[<?= esc((string)$key); ?>][mode]">
                                            <option value="sandbox" <?= ((string)($provider['mode'] ?? '') === 'sandbox') ? 'selected' : ''; ?>>Sandbox</option>
                                            <option value="production" <?= ((string)($provider['mode'] ?? '') === 'production') ? 'selected' : ''; ?>>Production</option>
                                        </select>
                                    </label>
                                    <label class="pg-check"><input type="checkbox" name="providers[<?= esc((string)$key); ?>][auto_update_order]" value="1" <?= !empty($provider['auto_update_order']) ? 'checked' : ''; ?>> Auto update status order kalau webhook valid</label>
                                </div>

                                <div class="pg-url">
                                    <strong>Webhook:</strong>
                                    <code><?= esc(payment_gateway_webhook_url((string)$key)); ?></code>
                                </div>

                                <div class="pg-field-grid">
                                    <label>Merchant ID
                                        <input type="text" name="providers[<?= esc((string)$key); ?>][merchant_id]" value="<?= esc((string)($provider['merchant_id'] ?? '')); ?>" placeholder="Opsional sesuai provider">
                                    </label>
                                    <label>Business ID
                                        <input type="text" name="providers[<?= esc((string)$key); ?>][business_id]" value="<?= esc((string)($provider['business_id'] ?? '')); ?>" placeholder="Opsional sesuai provider">
                                    </label>
                                </div>

                                <div class="pg-field-grid">
                                    <?php foreach ((array)($def['secret_fields'] ?? []) as $secretField): ?>
                                        <label><?= esc(ucwords(str_replace('_', ' ', (string)$secretField))); ?>
                                            <input type="password" name="providers[<?= esc((string)$key); ?>][<?= esc((string)$secretField); ?>]" value="" placeholder="<?= esc(payment_gateway_mask_secret((string)($provider[$secretField] ?? '')) ?: 'Isi credential baru'); ?>">
                                            <span class="pg-secret-note">Kosongkan untuk mempertahankan nilai lama. Centang hapus untuk mengosongkan.</span>
                                            <label class="pg-check"><input type="checkbox" name="providers[<?= esc((string)$key); ?>][clear_<?= esc((string)$secretField); ?>]" value="1"> Hapus <?= esc(str_replace('_', ' ', (string)$secretField)); ?></label>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <label>Catatan provider
                                    <textarea name="providers[<?= esc((string)$key); ?>][note]" placeholder="Catatan akun, environment, atau checklist aktivasi."><?= esc((string)($provider['note'] ?? '')); ?></textarea>
                                </label>
                            </section>
                            </section>
                        <?php endforeach; ?>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="pg-other" hidden>
                        <section class="pg-card">
                            <h2>Payment Gateway Lainnya</h2>
                            <p>Disiapkan sebagai tempat integrasi provider lain tanpa mengubah struktur dashboard.</p>
                            <div class="admin-page-helper-grid">
                                <div class="admin-page-helper-card"><strong>Duitku / Tripay</strong><span>Siap disiapkan untuk payment link, VA, dan callback jika dibutuhkan UMKM.</span></div>
                                <div class="admin-page-helper-card"><strong>Bank Virtual Account</strong><span>Bisa diarahkan ke provider gateway yang menyediakan VA bank Indonesia.</span></div>
                                <div class="admin-page-helper-card"><strong>E-wallet / QRIS Dinamis</strong><span>Tempat future setting untuk OVO, Dana, ShopeePay, atau QRIS dinamis.</span></div>
                                <div class="admin-page-helper-card"><strong>Webhook Custom</strong><span>Ruang integrasi untuk endpoint pembayaran lain yang memakai callback khusus.</span></div>
                            </div>
                        </section>

                        <section class="pg-card pg-danger-zone">
                            <h2>Catatan Keamanan</h2>
                            <p>Data koneksi pembayaran disimpan di area penyimpanan website dan tidak ditampilkan penuh di dashboard. Pastikan folder penyimpanan tidak bisa diakses publik.</p>
                        </section>
                        </section>

                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Pembayaran Otomatis</button>
                    </form>

                    <aside class="pg-form">
                        <section class="pg-card">
                            <h2>Status Ringkas</h2>
                            <p class="pg-muted">Status bridge gateway saat ini.</p>
                            <p><strong>Global:</strong> <?= !empty($summary['enabled']) ? 'Aktif' : 'Nonaktif'; ?></p>
                            <p><strong>Provider aktif:</strong> <?= esc((string)($summary['enabled_count'] ?? 0)); ?></p>
                            <p><strong>Provider sudah diisi:</strong> <?= esc((string)($summary['configured_count'] ?? 0)); ?></p>
                            <p><strong>Notifikasi 30 hari:</strong> <?= esc((string)($summary['webhook_events_30d'] ?? 0)); ?></p>
                            <p><strong>Event terakhir:</strong> <?= esc((string)($summary['last_event_at'] ?: '-')); ?></p>
                        </section>

                        <section class="pg-card">
                            <h2>URL Notifikasi Pembayaran</h2>
                            <p class="pg-muted">Salin URL ini ke dashboard pembayaran seperti Midtrans, Xendit, atau Flip.</p>
                            <?php foreach ($defs as $key => $def): ?>
                                <div class="pg-url" style="margin-bottom:8px">
                                    <strong><?= esc((string)$def['label']); ?>:</strong>
                                    <code><?= esc(payment_gateway_webhook_url((string)$key)); ?></code>
                                </div>
                            <?php endforeach; ?>
                        </section>

                        <section class="pg-card">
                            <h2>Notifikasi Pembayaran Terbaru</h2>
                            <div class="pg-events">
                                <?php foreach ($events as $event): ?>
                                    <div class="pg-event">
                                        <strong><?= esc(strtoupper((string)($event['provider'] ?? '-'))); ?> · <?= !empty($event['verified']) ? 'Verified' : 'Unverified'; ?></strong>
                                        <span class="pg-muted"><?= esc((string)($event['created_at'] ?? '-')); ?></span><br>
                                        <span class="pg-muted">Ref: <?= esc((string)($event['reference'] ?? '-')); ?> · Status: <?= esc((string)($event['gateway_status'] ?? '-')); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$events): ?><p class="pg-muted">Belum ada notifikasi pembayaran masuk.</p><?php endif; ?>
                            </div>
                        </section>

                        <section class="pg-card">
                            <h2>Catatan Tahap Ini</h2>
                            <p>Payment link/token dibuat dari server website setelah order tercatat. API key tidak pernah dikirim ke frontend. Jika provider error, order tetap masuk dan bisa diproses manual.</p>
                        </section>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
