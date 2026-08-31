<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$testResult = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = (string)($_POST['smtp_action'] ?? 'save');

    if ($action === 'save') {
        $updates = [
            'ENABLE_EMAIL_NOTIFICATIONS' => !empty($_POST['enable_email_notifications']) ? 'true' : 'false',
            'EMAIL_TRANSPORT' => in_array((string)($_POST['email_transport'] ?? 'log'), ['log', 'mail', 'smtp'], true) ? (string)$_POST['email_transport'] : 'log',
            'EMAIL_ADMIN_TO' => sanitize_email((string)($_POST['email_admin_to'] ?? '')),
            'EMAIL_FROM' => sanitize_email((string)($_POST['email_from'] ?? '')),
            'EMAIL_FROM_NAME' => notification_clean((string)($_POST['email_from_name'] ?? SITE_NAME), 100),
            'EMAIL_REPLY_TO' => sanitize_email((string)($_POST['email_reply_to'] ?? '')),
            'EMAIL_SEND_CUSTOMER_CONFIRMATION' => !empty($_POST['email_send_customer_confirmation']) ? 'true' : 'false',
            'EMAIL_SMTP_HOST' => notification_clean((string)($_POST['email_smtp_host'] ?? ''), 160),
            'EMAIL_SMTP_PORT' => (string)max(1, min(65535, (int)($_POST['email_smtp_port'] ?? 587))),
            'EMAIL_SMTP_USERNAME' => notification_clean((string)($_POST['email_smtp_username'] ?? ''), 220),
            'EMAIL_SMTP_ENCRYPTION' => in_array((string)($_POST['email_smtp_encryption'] ?? 'tls'), ['none', 'tls', 'ssl'], true) ? (string)$_POST['email_smtp_encryption'] : 'tls',
            'EMAIL_RULE_ADMIN_INQUIRY_CREATED' => !empty($_POST['rule_admin_inquiry_created']) ? 'true' : 'false',
            'EMAIL_RULE_CUSTOMER_INQUIRY_CONFIRMATION' => !empty($_POST['rule_customer_inquiry_confirmation']) ? 'true' : 'false',
            'EMAIL_RULE_ADMIN_ORDER_CREATED' => !empty($_POST['rule_admin_order_created']) ? 'true' : 'false',
            'EMAIL_RULE_CUSTOMER_ORDER_CONFIRMATION' => !empty($_POST['rule_customer_order_confirmation']) ? 'true' : 'false',
            'EMAIL_RULE_CUSTOMER_ORDER_STATUS_LINK' => !empty($_POST['rule_customer_order_status_link']) ? 'true' : 'false',
            'EMAIL_RULE_CUSTOMER_INVOICE_LINK' => !empty($_POST['rule_customer_invoice_link']) ? 'true' : 'false',
            'EMAIL_RULE_TEST_EMAIL' => !empty($_POST['rule_test_email']) ? 'true' : 'false',
        ];

        $newPassword = trim((string)($_POST['email_smtp_password'] ?? ''));
        if ($newPassword !== '') {
            $updates['EMAIL_SMTP_PASSWORD'] = $newPassword;
        }

        $result = app_env_update($updates);
        if (!empty($result['success'])) {
            activity_log_record('update_smtp_settings', 'system', null, 'Admin menyimpan pengaturan SMTP/Email Server.', ['transport' => $updates['EMAIL_TRANSPORT'], 'enabled' => $updates['ENABLE_EMAIL_NOTIFICATIONS']]);
            redirect_302('admin/smtp?message=' . rawurlencode((string)$result['message']));
        }
        $error = (string)($result['message'] ?? 'Pengaturan gagal disimpan.');
    }

    if ($action === 'test') {
        $to = sanitize_email((string)($_POST['test_email_to'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $error = 'Isi alamat email tujuan test yang valid.';
        } else {
            $ok = notification_send_email($to, 'Tes SMTP / Email Server - ' . SITE_NAME, "Halo,\n\nIni adalah email test dari dashboard admin " . SITE_NAME . ".\n\nJika email ini diterima, pengaturan email sudah bisa dipakai.\n", ['type' => 'admin_smtp_test', 'target_type' => 'system', 'target_ref' => 'smtp']);
            $testResult = $ok ? 'Email test berhasil diproses. Cek inbox atau spam tujuan.' : 'Email test belum terkirim. Cek status enable, transport, host, username/password, dan log email.';
            activity_log_record($ok ? 'test_smtp_success' : 'test_smtp_failed', 'system', null, 'Admin menjalankan test SMTP/Email Server.', ['to' => $to, 'ok' => $ok]);
        }
    }
}

$rules = notification_rules_summary();
$transport = notification_transport();
$enabled = notification_enabled();
$envWritable = is_writable(ROOT_PATH) || is_writable(app_env_path());

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'SMTP / Email Server - Admin',
    'description' => 'Pengaturan SMTP dan email otomatis untuk order, form, invoice, dan reminder.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="admin-content" class="admin-shell admin-smtp-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Sistem</div>
                <h1>SMTP / Email Server</h1>
                <p>Atur pengiriman email otomatis dari dashboard tanpa harus edit file .env manual.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/notifications')); ?>">Lihat Riwayat Email</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>
            <?php if ($testResult): ?><div class="admin-alert admin-alert--success"><?= esc($testResult); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Status Email</span><h2><?= $enabled ? 'Aktif' : 'Nonaktif'; ?></h2><p><?= $enabled ? 'Email otomatis boleh dikirim.' : 'Email otomatis masih dimatikan.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Transport</span><h2><?= esc(strtoupper($transport)); ?></h2><p>Log = simulasi, mail = PHP mail(), SMTP = provider email.</p></div>
                <div class="admin-card"><span class="admin-badge">Host SMTP</span><h2><?= esc($_ENV['EMAIL_SMTP_HOST'] ?? '-'); ?></h2><p><?= !empty($_ENV['EMAIL_SMTP_HOST']) ? 'Host sudah diisi.' : 'Host belum diisi.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">File .env</span><h2><?= is_file(app_env_path()) ? 'Ada' : 'Belum'; ?></h2><p><?= $envWritable ? 'Bisa ditulis dashboard.' : 'Belum writable oleh PHP.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Riwayat</span><h2>Email Log</h2><p><a href="<?= esc(url('admin/notifications')); ?>">Buka riwayat email terkirim/gagal</a>.</p></div>
            </div>

            <form method="post" class="admin-card admin-editor" style="margin-top:18px" data-admin-page-tab-scope>
                <?= csrf_field(); ?>
                <input type="hidden" name="smtp_action" value="save">
                <div class="admin-form-head"><span class="admin-badge">Pengaturan Email</span><h2>Konfigurasi SMTP dan Email Otomatis</h2><p>Kosongkan password SMTP jika tidak ingin mengubah password lama.</p></div>

                <div class="admin-page-subtabs admin-page-subtabs--3" role="tablist" aria-label="Bagian SMTP">
                    <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="smtp-basic"><span>1. Dasar Email</span><small>Aktifkan & alamat</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="smtp-server"><span>2. Server SMTP</span><small>Host & login</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="smtp-rules"><span>3. Aturan Kirim</span><small>Email otomatis</small></button>
                </div>
                <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian SMTP</span><select data-admin-page-tab-select aria-label="Pilih bagian SMTP"><option value="smtp-basic">1. Dasar Email</option><option value="smtp-server">2. Server SMTP</option><option value="smtp-rules">3. Aturan Kirim</option></select></label></div>

                <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="smtp-basic">
                    <div class="admin-form-grid admin-form-row--2">
                        <label class="admin-toggle-option"><input type="checkbox" name="enable_email_notifications" value="1" <?= $enabled ? 'checked' : ''; ?>> Aktifkan email otomatis</label>
                        <label class="admin-toggle-option"><input type="checkbox" name="email_send_customer_confirmation" value="1" <?= notification_bool_env('EMAIL_SEND_CUSTOMER_CONFIRMATION', true) ? 'checked' : ''; ?>> Kirim konfirmasi ke customer</label>
                        <label>Mode Pengiriman<select name="email_transport"><option value="log" <?= $transport === 'log' ? 'selected' : ''; ?>>Log / Simulasi</option><option value="mail" <?= $transport === 'mail' ? 'selected' : ''; ?>>PHP mail()</option><option value="smtp" <?= $transport === 'smtp' ? 'selected' : ''; ?>>SMTP Provider</option></select></label>
                        <label>Email Admin Tujuan<input type="email" name="email_admin_to" value="<?= esc((string)($_ENV['EMAIL_ADMIN_TO'] ?? '')); ?>" placeholder="admin@domain.com"></label>
                        <label>Email Pengirim<input type="email" name="email_from" value="<?= esc((string)($_ENV['EMAIL_FROM'] ?? '')); ?>" placeholder="no-reply@domain.com"></label>
                        <label>Nama Pengirim<input name="email_from_name" value="<?= esc((string)($_ENV['EMAIL_FROM_NAME'] ?? SITE_NAME)); ?>" placeholder="<?= esc(SITE_NAME); ?>"></label>
                        <label>Email Balasan<input type="email" name="email_reply_to" value="<?= esc((string)($_ENV['EMAIL_REPLY_TO'] ?? '')); ?>" placeholder="cs@domain.com"></label>
                    </div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="smtp-server" hidden>
                    <div class="admin-form-grid admin-form-row--2">
                        <label>SMTP Host<input name="email_smtp_host" value="<?= esc((string)($_ENV['EMAIL_SMTP_HOST'] ?? '')); ?>" placeholder="smtp.domain.com"></label>
                        <label>SMTP Port<input type="number" name="email_smtp_port" value="<?= esc((string)($_ENV['EMAIL_SMTP_PORT'] ?? '587')); ?>" placeholder="587"></label>
                        <label>SMTP Username<input name="email_smtp_username" value="<?= esc((string)($_ENV['EMAIL_SMTP_USERNAME'] ?? '')); ?>" placeholder="username@email.com"></label>
                        <label>SMTP Password<input type="password" name="email_smtp_password" value="" placeholder="<?= esc(app_env_mask_secret((string)($_ENV['EMAIL_SMTP_PASSWORD'] ?? ''))); ?>"></label>
                        <label>Enkripsi<select name="email_smtp_encryption"><option value="tls" <?= (($_ENV['EMAIL_SMTP_ENCRYPTION'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS / STARTTLS</option><option value="ssl" <?= (($_ENV['EMAIL_SMTP_ENCRYPTION'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL</option><option value="none" <?= (($_ENV['EMAIL_SMTP_ENCRYPTION'] ?? '') === 'none') ? 'selected' : ''; ?>>Tanpa enkripsi</option></select></label>
                    </div>
                </section>

                <section class="admin-page-tab-panel" data-admin-page-tab-panel="smtp-rules" hidden>
                    <div class="admin-check-grid">
                        <?php foreach ($rules as $rule): ?>
                            <label><input type="checkbox" name="rule_<?= esc((string)$rule['key']); ?>" value="1" <?= !empty($rule['enabled']) ? 'checked' : ''; ?>> <?= esc((string)$rule['label']); ?></label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="admin-form-actions"><button class="admin-btn admin-btn--primary" type="submit">Simpan Pengaturan Email</button></div>
            </form>

            <form method="post" class="admin-card" style="margin-top:18px">
                <?= csrf_field(); ?>
                <input type="hidden" name="smtp_action" value="test">
                <div class="admin-form-head"><span class="admin-badge">Test Email</span><h2>Kirim Email Percobaan</h2><p>Gunakan ini setelah SMTP disimpan. Untuk mode log, email hanya masuk riwayat/log.</p></div>
                <div class="admin-form-grid admin-form-row--2">
                    <label>Email Tujuan Test<input type="email" name="test_email_to" value="<?= esc((string)($_ENV['EMAIL_ADMIN_TO'] ?? SITE_EMAIL)); ?>" required></label>
                    <div class="admin-form-actions"><button class="admin-btn admin-btn--soft" type="submit">Kirim Test Email</button></div>
                </div>
            </form>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
