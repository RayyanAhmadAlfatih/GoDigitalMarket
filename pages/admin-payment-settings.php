<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$message = '';
$error = '';

function admin_payment_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_payment_text(string $key, int $max = 160): string
{
    return payment_clean((string)($_POST[$key] ?? ''), $max);
}

function admin_payment_multiline(string $key, int $max = 1200): string
{
    return payment_multiline_clean((string)($_POST[$key] ?? ''), $max);
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/payment-settings');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_payment_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'payment_settings']);
            }
            redirect_302('admin/payment-settings');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'save_payment_settings') {
        $bankAccounts = [];
        $banks = (array)($_POST['bank'] ?? []);
        for ($i = 0; $i < 5; $i++) {
            $row = is_array($banks[$i] ?? null) ? $banks[$i] : [];
            $bankAccounts[] = [
                'id' => 'bank-' . ($i + 1),
                'enabled' => !empty($row['enabled']),
                'label' => payment_clean((string)($row['label'] ?? ''), 80),
                'bank_name' => payment_clean((string)($row['bank_name'] ?? ''), 80),
                'account_number' => payment_clean((string)($row['account_number'] ?? ''), 80),
                'account_holder' => payment_clean((string)($row['account_holder'] ?? ''), 120),
                'note' => payment_multiline_clean((string)($row['note'] ?? ''), 400),
            ];
        }

        $settings = [
            'public_label' => admin_payment_text('public_label', 120),
            'default_due_days' => max(1, min(30, (int)($_POST['default_due_days'] ?? 3))),
            'default_instruction' => admin_payment_multiline('default_instruction', 900),
            'default_public_note' => admin_payment_multiline('default_public_note', 700),
            'bank_accounts' => $bankAccounts,
            'qris' => [
                'enabled' => !empty($_POST['qris_enabled']),
                'label' => admin_payment_text('qris_label', 80) ?: 'QRIS Manual',
                'image_url' => admin_payment_text('qris_image_url', 240),
                'note' => admin_payment_multiline('qris_note', 600),
            ],
            'cash' => [
                'enabled' => !empty($_POST['cash_enabled']),
                'label' => admin_payment_text('cash_label', 80) ?: 'Tunai Saat Survey/Kirim',
                'note' => admin_payment_multiline('cash_note', 500),
            ],
            'custom' => [
                'enabled' => !empty($_POST['custom_enabled']),
                'label' => admin_payment_text('custom_label', 80) ?: 'Instruksi Custom Admin',
                'note' => admin_payment_multiline('custom_note', 500),
            ],
        ];

        if (payment_write_settings($settings)) {
            redirect_302('admin/payment-settings?saved=1');
        }
        $error = 'Pengaturan pembayaran belum bisa disimpan. Pastikan folder storage bisa ditulis server.';
    }
}

if (!empty($_GET['saved'])) {
    $message = 'Pengaturan pembayaran manual berhasil disimpan.';
}

$loggedIn = admin_payment_logged_in();
$settings = $loggedIn ? payment_read_settings() : payment_default_settings();
$profiles = $loggedIn ? payment_profiles() : [];

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Payment Settings - Admin',
    'description' => 'Pengaturan metode pembayaran manual untuk invoice, transfer bank, QRIS manual, dan pembayaran tunai.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-payment-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Payment Settings</div>
                <h1>Manual QRIS & Transfer Profile</h1>
                <p>Atur rekening bank, QRIS manual, pembayaran tunai, instruksi default, dan catatan invoice tanpa perlu mengubah kode PHP.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <style>
                .admin-payment-shell .payment-admin-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px;align-items:start}.admin-payment-shell .payment-admin-form{display:grid;gap:16px}.admin-payment-shell .payment-block{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-payment-shell .payment-block h2,.admin-payment-shell .payment-block h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-payment-shell .payment-block p{color:#64748b;margin:.2rem 0 .9rem}.admin-payment-shell .payment-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-payment-shell label{display:grid;gap:7px;color:#334155;font-weight:800;font-size:.86rem}.admin-payment-shell input,.admin-payment-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;color:#0f172a;background:#fff}.admin-payment-shell textarea{min-height:96px;resize:vertical}.admin-payment-shell .payment-check{display:flex!important;align-items:center;gap:8px}.admin-payment-shell .payment-check input{width:auto}.admin-payment-shell .bank-row{border:1px solid #dbeafe;background:#f8fbff;border-radius:20px;padding:14px;display:grid;gap:10px}.admin-payment-shell .payment-preview-list{display:grid;gap:12px}.admin-payment-shell .payment-preview-card{border:1px solid #dbeafe;background:#f8fbff;border-radius:20px;padding:14px}.admin-payment-shell .payment-preview-card strong{display:block;color:#0f172a;margin-bottom:4px}.admin-payment-shell .payment-preview-card ul{margin:.5rem 0 0;padding-left:1.1rem;color:#475569}.admin-payment-shell .payment-muted{color:#64748b;font-size:.86rem}.admin-payment-shell .payment-alert{margin-bottom:16px;padding:13px 15px;border-radius:16px;font-weight:800}.admin-payment-shell .payment-alert--success{background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--admin-primary)}.admin-payment-shell .payment-alert--danger{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}@media(max-width:900px){.admin-payment-shell .payment-admin-grid,.admin-payment-shell .payment-form-grid{grid-template-columns:1fr}}
            </style>
            <?php if ($message): ?><div class="payment-alert payment-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="payment-alert payment-alert--danger"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka pengaturan pembayaran.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="payment-admin-grid">
                    <form method="post" class="payment-admin-form" data-admin-page-tab-scope>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_payment_settings">

                        <div class="admin-page-subtabs admin-page-subtabs--4" role="tablist" aria-label="Bagian Pembayaran Manual">
                            <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="pay-invoice"><span>1. Invoice</span><small>Label & instruksi</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="pay-bank"><span>2. Rekening Bank</span><small>Transfer manual</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="pay-qris"><span>3. QRIS Manual</span><small>QR statis</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="pay-custom"><span>4. Tunai & Custom</span><small>Metode lain</small></button>
                        </div>
                        <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian pembayaran</span><select data-admin-page-tab-select aria-label="Pilih bagian Pembayaran Manual"><option value="pay-invoice">1. Invoice</option><option value="pay-bank">2. Rekening Bank</option><option value="pay-qris">3. QRIS Manual</option><option value="pay-custom">4. Tunai & Custom</option></select></label></div>

                        <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="pay-invoice">
                        <section class="payment-block">
                            <h2>Instruksi Default Invoice</h2>
                            <p>Dipakai sebagai fallback kalau order belum memilih profil pembayaran khusus.</p>
                            <div class="payment-form-grid">
                                <label>Label publik pembayaran
                                    <input type="text" name="public_label" value="<?= esc((string)($settings['public_label'] ?? '')); ?>" placeholder="Transfer/QRIS setelah konfirmasi admin">
                                </label>
                                <label>Jatuh tempo default (hari)
                                    <input type="number" name="default_due_days" min="1" max="30" value="<?= esc((string)($settings['default_due_days'] ?? 3)); ?>">
                                </label>
                            </div>
                            <label>Instruksi default
                                <textarea name="default_instruction"><?= esc((string)($settings['default_instruction'] ?? '')); ?></textarea>
                            </label>
                            <label>Catatan publik invoice
                                <textarea name="default_public_note"><?= esc((string)($settings['default_public_note'] ?? '')); ?></textarea>
                            </label>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="pay-bank" hidden>
                        <section class="payment-block">
                            <h2>Rekening Bank</h2>
                            <p>Tambahkan maksimal 5 rekening untuk ditampilkan di invoice manual.</p>
                            <?php
                                $banks = (array)($settings['bank_accounts'] ?? []);
                                for ($i = 0; $i < 5; $i++):
                                    $bank = is_array($banks[$i] ?? null) ? $banks[$i] : ['enabled' => $i === 0, 'label' => '', 'bank_name' => '', 'account_number' => '', 'account_holder' => '', 'note' => ''];
                            ?>
                                <div class="bank-row">
                                    <label class="payment-check"><input type="checkbox" name="bank[<?= $i; ?>][enabled]" value="1" <?= !empty($bank['enabled']) ? 'checked' : ''; ?>> Aktifkan rekening #<?= $i + 1; ?></label>
                                    <div class="payment-form-grid">
                                        <label>Label
                                            <input type="text" name="bank[<?= $i; ?>][label]" value="<?= esc((string)($bank['label'] ?? '')); ?>" placeholder="BCA Utama / Mandiri / BSI">
                                        </label>
                                        <label>Nama Bank
                                            <input type="text" name="bank[<?= $i; ?>][bank_name]" value="<?= esc((string)($bank['bank_name'] ?? '')); ?>" placeholder="BCA">
                                        </label>
                                        <label>Nomor Rekening
                                            <input type="text" name="bank[<?= $i; ?>][account_number]" value="<?= esc((string)($bank['account_number'] ?? '')); ?>" placeholder="1234567890">
                                        </label>
                                        <label>Atas Nama
                                            <input type="text" name="bank[<?= $i; ?>][account_holder]" value="<?= esc((string)($bank['account_holder'] ?? '')); ?>" placeholder="Nama pemilik rekening">
                                        </label>
                                    </div>
                                    <label>Catatan rekening
                                        <textarea name="bank[<?= $i; ?>][note]" placeholder="Contoh: Mohon kirim bukti transfer via WhatsApp admin."><?= esc((string)($bank['note'] ?? '')); ?></textarea>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="pay-qris" hidden>
                        <section class="payment-block">
                            <h2>QRIS Manual</h2>
                            <p>Untuk QRIS statis/manual. Upload gambar QRIS bisa dilakukan manual ke folder aset, lalu masukkan URL gambar di sini.</p>
                            <label class="payment-check"><input type="checkbox" name="qris_enabled" value="1" <?= !empty($settings['qris']['enabled']) ? 'checked' : ''; ?>> Aktifkan QRIS manual</label>
                            <div class="payment-form-grid">
                                <label>Label QRIS
                                    <input type="text" name="qris_label" value="<?= esc((string)($settings['qris']['label'] ?? 'QRIS Manual')); ?>">
                                </label>
                                <label>URL gambar QRIS
                                    <input type="text" name="qris_image_url" value="<?= esc((string)($settings['qris']['image_url'] ?? '')); ?>" placeholder="https://domain.com/assets/images/qris.png">
                                </label>
                            </div>
                            <label>Catatan QRIS
                                <textarea name="qris_note"><?= esc((string)($settings['qris']['note'] ?? '')); ?></textarea>
                            </label>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="pay-custom" hidden>
                        <section class="payment-block">
                            <h2>Tunai & Custom</h2>
                            <div class="payment-form-grid">
                                <div>
                                    <label class="payment-check"><input type="checkbox" name="cash_enabled" value="1" <?= !empty($settings['cash']['enabled']) ? 'checked' : ''; ?>> Aktifkan pembayaran tunai</label>
                                    <label>Label Tunai<input type="text" name="cash_label" value="<?= esc((string)($settings['cash']['label'] ?? 'Tunai Saat Survey/Kirim')); ?>"></label>
                                    <label>Catatan Tunai<textarea name="cash_note"><?= esc((string)($settings['cash']['note'] ?? '')); ?></textarea></label>
                                </div>
                                <div>
                                    <label class="payment-check"><input type="checkbox" name="custom_enabled" value="1" <?= !empty($settings['custom']['enabled']) ? 'checked' : ''; ?>> Aktifkan instruksi custom</label>
                                    <label>Label Custom<input type="text" name="custom_label" value="<?= esc((string)($settings['custom']['label'] ?? 'Instruksi Custom Admin')); ?>"></label>
                                    <label>Catatan Custom<textarea name="custom_note"><?= esc((string)($settings['custom']['note'] ?? '')); ?></textarea></label>
                                </div>
                            </div>
                        </section>
                        </section>

                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Payment Settings</button>
                    </form>

                    <aside class="payment-preview-list">
                        <section class="payment-block">
                            <h2>Preview Metode Aktif</h2>
                            <p class="payment-muted">Metode ini akan tersedia sebagai pilihan profil pembayaran di Order Dashboard dan invoice publik.</p>
                            <?php foreach ($profiles as $profile): ?>
                                <div class="payment-preview-card">
                                    <strong><?= esc((string)($profile['label'] ?? '-')); ?></strong>
                                    <span class="payment-muted"><?= esc((string)($profile['type'] ?? '-')); ?></span>
                                    <ul>
                                        <?php foreach ((array)($profile['lines'] ?? []) as $line): ?>
                                            <li><?= nl2br(esc((string)$line)); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$profiles): ?><p>Belum ada metode pembayaran aktif.</p><?php endif; ?>
                        </section>
                        <section class="payment-block">
                            <h2>Catatan Implementasi</h2>
                            <p>Payment manual tetap menjadi fallback utama. Untuk persiapan Midtrans/Xendit/Flip, buka menu <a href="<?= url('admin/payment-gateway'); ?>">Pembayaran Otomatis</a>.</p>
                            <p>Untuk live, gunakan rekening/QRIS resmi bisnis dan selalu minta bukti pembayaran melalui WhatsApp/admin sebelum mengubah status menjadi Lunas.</p>
                        </section>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
