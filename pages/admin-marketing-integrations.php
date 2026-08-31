<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$message = '';
$error = '';

function admin_marketing_integration_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/marketing-integrations');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_marketing_integration_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'marketing-integrations']);
            }
            redirect_302('admin/marketing-integrations');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'save_marketing_integrations') {
        $current = function_exists('marketing_integration_read_settings') ? marketing_integration_read_settings() : [];
        $settings = function_exists('marketing_integration_settings_from_post') ? marketing_integration_settings_from_post($_POST, $current) : [];
        if (function_exists('marketing_integration_write_settings') && marketing_integration_write_settings($settings)) {
            redirect_302('admin/marketing-integrations?saved=1');
        }
        $error = 'Pengaturan belum bisa disimpan. Pastikan folder storage bisa ditulis server.';
    } elseif (($_POST['form_action'] ?? '') === 'test_integration_send') {
        $results = function_exists('marketing_integration_test_send') ? marketing_integration_test_send($_POST) : ['Fitur test belum tersedia.'];
        $message = implode(' ', array_map(static fn($item): string => (string)$item, $results));
    }
}

if (!empty($_GET['saved'])) {
    $message = 'Pengaturan WhatsApp & Email Marketing berhasil disimpan.';
}

$loggedIn = admin_marketing_integration_logged_in();
$settings = $loggedIn && function_exists('marketing_integration_read_settings') ? marketing_integration_read_settings() : (function_exists('marketing_integration_default_settings') ? marketing_integration_default_settings() : []);
$summary = $loggedIn && function_exists('marketing_integration_summary') ? marketing_integration_summary() : [];
$recentLogs = $loggedIn && function_exists('marketing_integration_recent_logs') ? marketing_integration_recent_logs(12) : [];

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Email Marketing & WhatsApp Otomatis - Admin',
    'description' => 'Pengaturan integrasi Mailketing dan Fonnte untuk inquiry/order website.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-marketing-integration-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>WhatsApp & Email Marketing</h1>
                <p>Hubungkan website dengan layanan email marketing Mailketing dan WhatsApp gateway Fonnte. Data form, order, dan buyer bisa dikirim otomatis hanya untuk customer yang setuju dihubungi. Pesan default global juga diatur dari halaman ini.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <style>
                .admin-marketing-integration-shell .mi-alert{margin-bottom:16px;padding:13px 15px;border-radius:16px;font-weight:800}.admin-marketing-integration-shell .mi-alert--success{background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--admin-primary)}.admin-marketing-integration-shell .mi-alert--danger{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}.admin-marketing-integration-shell .mi-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:18px;align-items:start}.admin-marketing-integration-shell .mi-form{display:grid;gap:16px}.admin-marketing-integration-shell .mi-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-marketing-integration-shell .mi-card h2,.admin-marketing-integration-shell .mi-card h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-marketing-integration-shell .mi-card p{color:#64748b;margin:.2rem 0 .9rem}.admin-marketing-integration-shell .mi-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-marketing-integration-shell label{display:grid;gap:7px;color:#334155;font-weight:800;font-size:.86rem}.admin-marketing-integration-shell input,.admin-marketing-integration-shell select,.admin-marketing-integration-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;color:#0f172a;background:#fff}.admin-marketing-integration-shell textarea{min-height:104px;resize:vertical;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.82rem}.admin-marketing-integration-shell .mi-check{display:flex!important;align-items:center;gap:8px}.admin-marketing-integration-shell .mi-check input{width:auto}.admin-marketing-integration-shell .mi-secret-note{font-size:.8rem;color:#64748b;margin-top:-2px}.admin-marketing-integration-shell .mi-status{display:grid;gap:10px}.admin-marketing-integration-shell .mi-status-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:16px;padding:11px 12px}.admin-marketing-integration-shell .mi-status-row span{color:#64748b;font-size:.86rem}.admin-marketing-integration-shell .mi-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:.75rem;font-weight:900;background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}.admin-marketing-integration-shell .mi-badge--ok{background:color-mix(in srgb,var(--bg) 82%,#ffffff);color:var(--admin-primary);border-color:var(--border)}.admin-marketing-integration-shell .mi-badge--warn{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff);color:var(--admin-primary-dark);border-color:color-mix(in srgb,var(--admin-primary) 22%,#ffffff)}.admin-marketing-integration-shell .mi-log{display:grid;gap:10px}.admin-marketing-integration-shell .mi-log-row{border:1px solid #e2e8f0;border-radius:18px;padding:12px;background:#fff}.admin-marketing-integration-shell .mi-log-row strong{display:flex;justify-content:space-between;gap:10px;color:#0f172a}.admin-marketing-integration-shell .mi-log-row small{display:block;color:#64748b;word-break:break-word;margin-top:5px}.admin-marketing-integration-shell .mi-guide ul{margin:.6rem 0 0;padding-left:1.15rem;color:#475569}.admin-marketing-integration-shell .mi-guide li{margin:.4rem 0}.admin-marketing-integration-shell code{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:2px 6px}.admin-marketing-integration-shell .mi-variable-list{display:flex;flex-wrap:wrap;gap:6px;margin:.6rem 0 .9rem}.admin-marketing-integration-shell .mi-template-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-marketing-integration-shell .mi-test-actions{display:grid;gap:10px}.admin-marketing-integration-shell .mi-test-actions .mi-check{border:1px solid #e2e8f0;background:#f8fafc;border-radius:14px;padding:10px}.admin-marketing-integration-shell .mi-note-box{border:1px dashed var(--border);background:color-mix(in srgb,var(--bg) 78%,#ffffff);border-radius:18px;padding:12px;color:#475569;font-weight:700}@media(max-width:980px){.admin-marketing-integration-shell .mi-grid,.admin-marketing-integration-shell .mi-field-grid,.admin-marketing-integration-shell .mi-template-grid{grid-template-columns:1fr}}
            </style>

            <?php if ($message): ?><div class="mi-alert mi-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="mi-alert mi-alert--danger"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka pengaturan WhatsApp & Email Marketing.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php if (function_exists('marketing_analytics_render_menu_map')) { marketing_analytics_render_menu_map('marketing'); } ?>
                <div class="mi-grid">
                    <form method="post" class="mi-form" data-admin-page-tab-scope>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_marketing_integrations">

                        <div class="admin-page-subtabs admin-page-subtabs--3" role="tablist" aria-label="Bagian WhatsApp & Email Marketing">
                            <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="marketing-whatsapp"><span>1. WhatsApp</span><small>Fonnte & provider WA</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="marketing-email"><span>2. Email</span><small>Mailketing & provider email</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="marketing-default-message"><span>3. Pesan Default Global</span><small>Template form custom</small></button>
                        </div>
                        <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian marketing</span><select data-admin-page-tab-select aria-label="Pilih bagian WhatsApp & Email Marketing"><option value="marketing-whatsapp">1. WhatsApp</option><option value="marketing-email">2. Email</option><option value="marketing-default-message">3. Pesan Default Global</option></select></label></div>

                        <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="marketing-whatsapp">
                        <section class="mi-card">
                            <h2>Aktifkan Otomatis Umum</h2>
                            <p>Dipakai untuk alur inquiry/order umum. Form Custom tetap mengikuti pengaturan provider di bawah dan opsi khusus pada masing-masing form.</p>
                            <div class="mi-field-grid">
                                <label class="mi-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan otomatis untuk inquiry/order umum</label>
                                <label class="mi-check"><input type="checkbox" name="require_contact_consent" value="1" <?= !empty($settings['require_contact_consent']) ? 'checked' : ''; ?>> Wajib persetujuan customer sebelum kirim data/pesan</label>
                            </div>
                        </section>
                        <section class="mi-card">
                            <h2>Pengaturan Fonnte WhatsApp</h2>
                            <p>Fonnte adalah layanan WhatsApp gateway. Gunakan untuk mengirim pesan WhatsApp otomatis setelah form atau order masuk.</p>
                            <div class="mi-field-grid">
                                <label class="mi-check"><input type="checkbox" name="fonnte_enabled" value="1" <?= !empty($settings['fonnte']['enabled']) ? 'checked' : ''; ?>> Aktifkan Fonnte</label>
                                <label>Fonnte Token
                                    <input type="password" name="fonnte_token" value="" placeholder="<?= esc(function_exists('marketing_integration_mask_secret') ? marketing_integration_mask_secret((string)($settings['fonnte']['token'] ?? '')) : 'Isi token baru'); ?>">
                                    <span class="mi-secret-note">Kosongkan untuk mempertahankan token lama.</span>
                                    <label class="mi-check"><input type="checkbox" name="clear_fonnte_token" value="1"> Hapus token Fonnte</label>
                                </label>
                                <label>Kode Negara
                                    <input type="text" name="fonnte_country_code" value="<?= esc((string)($settings['fonnte']['country_code'] ?? '62')); ?>" placeholder="62">
                                </label>
                                <label class="mi-check"><input type="checkbox" name="fonnte_send_inquiry_message" value="1" <?= !empty($settings['fonnte']['send_inquiry_message']) ? 'checked' : ''; ?>> Kirim WA untuk inquiry</label>
                                <label class="mi-check"><input type="checkbox" name="fonnte_send_order_message" value="1" <?= !empty($settings['fonnte']['send_order_message']) ? 'checked' : ''; ?>> Kirim WA untuk order</label>
                            </div>
                            <label>Pesan WhatsApp Default untuk Inquiry Umum
                                <textarea name="fonnte_inquiry_template"><?= esc((string)($settings['fonnte']['inquiry_template'] ?? '')); ?></textarea>
                            </label>
                            <label>Pesan WhatsApp Default untuk Order
                                <textarea name="fonnte_order_template"><?= esc((string)($settings['fonnte']['order_template'] ?? '')); ?></textarea>
                            </label>
                            <p class="mi-secret-note">Placeholder inquiry/order: <code>{site_name}</code>, <code>{name}</code>, <code>{need}</code>, <code>{location}</code>, <code>{product_title}</code>, <code>{order_ref}</code>. Pesan form custom punya daftar variable sendiri di bawah.</p>
                        </section>
                        <section class="mi-card">
                            <h2>Layanan WhatsApp Lainnya</h2>
                            <p>Disiapkan untuk integrasi provider WhatsApp lain jika admin tidak memakai Fonnte.</p>
                            <div class="admin-page-helper-grid">
                                <div class="admin-page-helper-card"><strong>Wablas / Qontak</strong><span>Tempat future setting token, sender, dan template pesan.</span></div>
                                <div class="admin-page-helper-card"><strong>WhatsApp Cloud API</strong><span>Disiapkan untuk integrasi resmi Meta jika bisnis sudah siap.</span></div>
                            </div>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="marketing-email" hidden>
                        <section class="mi-card">
                            <h2>Pengaturan Mailketing</h2>
                            <p>Mailketing adalah layanan email marketing. Data form atau order yang memiliki email valid bisa masuk otomatis ke daftar yang dipilih.</p>
                            <div class="mi-field-grid">
                                <label class="mi-check"><input type="checkbox" name="mailketing_enabled" value="1" <?= !empty($settings['mailketing']['enabled']) ? 'checked' : ''; ?>> Aktifkan Mailketing</label>
                                <label>Mailketing API Token
                                    <input type="password" name="mailketing_api_token" value="" placeholder="<?= esc(function_exists('marketing_integration_mask_secret') ? marketing_integration_mask_secret((string)($settings['mailketing']['api_token'] ?? '')) : 'Isi token baru'); ?>">
                                    <span class="mi-secret-note">Kosongkan untuk mempertahankan token lama.</span>
                                    <label class="mi-check"><input type="checkbox" name="clear_mailketing_api_token" value="1"> Hapus token Mailketing</label>
                                </label>
                                <label>List ID Utama
                                    <input type="text" name="mailketing_default_list_id" value="<?= esc((string)($settings['mailketing']['default_list_id'] ?? '')); ?>" placeholder="Contoh: 12345">
                                </label>
                                <label>List ID Form Masuk
                                    <input type="text" name="mailketing_inquiry_list_id" value="<?= esc((string)($settings['mailketing']['inquiry_list_id'] ?? '')); ?>" placeholder="Opsional, fallback ke default">
                                </label>
                                <label>List ID Order
                                    <input type="text" name="mailketing_order_list_id" value="<?= esc((string)($settings['mailketing']['order_list_id'] ?? '')); ?>" placeholder="Opsional, fallback ke default">
                                </label>
                                <label>List ID Buyer
                                    <input type="text" name="mailketing_buyer_list_id" value="<?= esc((string)($settings['mailketing']['buyer_list_id'] ?? '')); ?>" placeholder="List khusus customer sudah bayar">
                                    <span class="mi-secret-note">Customer akan masuk ke daftar ini ketika pembayaran sudah ditandai DP, lunas, atau valid.</span>
                                </label>
                                <label class="mi-check"><input type="checkbox" name="mailketing_sync_inquiry" value="1" <?= !empty($settings['mailketing']['sync_inquiry']) ? 'checked' : ''; ?>> Kirim data form ke Mailketing</label>
                                <label class="mi-check"><input type="checkbox" name="mailketing_sync_order" value="1" <?= !empty($settings['mailketing']['sync_order']) ? 'checked' : ''; ?>> Kirim data order ke Mailketing</label>
                                <label class="mi-check"><input type="checkbox" name="mailketing_sync_buyer" value="1" <?= !empty($settings['mailketing']['sync_buyer']) ? 'checked' : ''; ?>> Kirim buyer yang sudah bayar ke List Buyer</label>
                            </div>
                        </section>
                        <section class="mi-card">
                            <h2>Layanan Email Marketing Lainnya</h2>
                            <p>Disiapkan untuk provider email marketing lain agar template tetap fleksibel untuk berbagai UMKM.</p>
                            <div class="admin-page-helper-grid">
                                <div class="admin-page-helper-card"><strong>Brevo / Mailchimp</strong><span>Ruang future setting API key, list/audience ID, dan tags.</span></div>
                                <div class="admin-page-helper-card"><strong>SMTP Campaign Custom</strong><span>Tempat rencana integrasi broadcast email ringan berbasis SMTP.</span></div>
                            </div>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="marketing-default-message" hidden>
                        <section class="mi-card">
                            <h2>Pesan Default Global untuk Form Custom</h2>
                            <p>Pesan ini dipakai jika form custom tidak mengisi pesan khusus. Jadi admin bisa punya standar pesan yang sama untuk semua form.</p>
                            <div class="mi-note-box">Di menu Form Custom, kolom pesan disebut <strong>Pesan Khusus Form Ini</strong>. Jika dikosongkan, sistem otomatis memakai pesan default global di bawah.</div>
                            <div class="mi-variable-list" aria-label="Variable pesan">
                                <code>{nama}</code><code>{whatsapp}</code><code>{email}</code><code>{kebutuhan}</code><code>{pesan}</code><code>{summary}</code><code>{form_name}</code><code>{submission_id}</code><code>{source_url}</code><code>{site_name}</code>
                            </div>
                            <div class="mi-template-grid">
                                <label>WhatsApp ke admin / pemilik website Default
                                    <textarea name="form_whatsapp_admin_template" rows="7"><?= esc((string)($settings['form_messages']['whatsapp_admin_template'] ?? '')); ?></textarea>
                                </label>
                                <label>WhatsApp ke lead / customer Default
                                    <textarea name="form_whatsapp_customer_template" rows="7"><?= esc((string)($settings['form_messages']['whatsapp_customer_template'] ?? '')); ?></textarea>
                                </label>
                                <label>Email ke admin / pemilik website Default
                                    <input type="text" name="form_email_admin_subject" value="<?= esc((string)($settings['form_messages']['email_admin_subject'] ?? '')); ?>" placeholder="Subjek email admin">
                                    <textarea name="form_email_admin_template" rows="7"><?= esc((string)($settings['form_messages']['email_admin_template'] ?? '')); ?></textarea>
                                </label>
                                <label>Email ke lead / customer Default
                                    <input type="text" name="form_email_customer_subject" value="<?= esc((string)($settings['form_messages']['email_customer_subject'] ?? '')); ?>" placeholder="Subjek email lead/customer">
                                    <textarea name="form_email_customer_template" rows="7"><?= esc((string)($settings['form_messages']['email_customer_template'] ?? '')); ?></textarea>
                                </label>
                            </div>
                        </section>                        </section>

                        <button class="admin-btn admin-btn--primary" type="submit">Simpan Pengaturan</button>
                    </form>

                    <aside class="mi-sidebar">
                        <section class="mi-card">
                            <h2>Status Integrasi</h2>
                            <div class="mi-status">
                                <div class="mi-status-row"><strong>Global</strong><span class="mi-badge <?= !empty($summary['enabled']) ? 'mi-badge--ok' : ''; ?>"><?= !empty($summary['enabled']) ? 'Aktif' : 'Nonaktif'; ?></span></div>
                                <div class="mi-status-row"><strong>Mailketing</strong><span class="mi-badge <?= !empty($summary['mailketing']['configured']) ? 'mi-badge--ok' : 'mi-badge--warn'; ?>"><?= !empty($summary['mailketing']['configured']) ? 'Siap' : 'Belum lengkap'; ?></span></div>
                                <div class="mi-status-row"><strong>Fonnte</strong><span class="mi-badge <?= !empty($summary['fonnte']['configured']) ? 'mi-badge--ok' : 'mi-badge--warn'; ?>"><?= !empty($summary['fonnte']['configured']) ? 'Siap' : 'Belum lengkap'; ?></span></div>
                                <div class="mi-status-row"><strong>Buyer List</strong><span class="mi-badge <?= !empty($summary['mailketing']['buyer_configured']) ? 'mi-badge--ok' : 'mi-badge--warn'; ?>"><?= !empty($summary['mailketing']['buyer_configured']) ? 'Siap' : 'Belum lengkap'; ?></span></div>
                                <div class="mi-status-row"><strong>Kirim Buyer</strong><span><?= !empty($summary['mailketing']['sync_buyer']) ? 'Aktif' : 'Nonaktif'; ?></span></div>
                                <div class="mi-status-row"><strong>Persetujuan Customer</strong><span><?= !empty($summary['require_contact_consent']) ? 'Aktif' : 'Nonaktif'; ?></span></div>
                            </div>
                        </section>

                        <section class="mi-card">
                            <h2>Tes Kirim</h2>
                            <p>Gunakan untuk mengecek apakah Fonnte dan email website sudah siap. Tes hanya jalan saat tombol ditekan.</p>
                            <form method="post" class="mi-test-actions">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="test_integration_send">
                                <label>Nomor WhatsApp Tujuan
                                    <input type="text" name="test_phone" value="" placeholder="628xxxxxxxxxx">
                                </label>
                                <label>Email Tujuan
                                    <input type="email" name="test_email" value="" placeholder="admin@email.com">
                                </label>
                                <label class="mi-check"><input type="checkbox" name="test_send_whatsapp" value="1"> Tes WhatsApp Fonnte</label>
                                <label class="mi-check"><input type="checkbox" name="test_send_email" value="1"> Tes Email Website</label>
                                <button class="admin-btn admin-btn--primary" type="submit">Kirim Tes</button>
                            </form>
                        </section>

                        <section class="mi-card mi-guide">
                            <h2>Catatan Penggunaan</h2>
                            <ul>
                                <li>Gunakan hanya untuk customer yang memberi persetujuan untuk dihubungi.</li>
                                <li>Mailketing membutuhkan provider Mailketing aktif, email customer valid, token, dan List ID yang benar.</li>
                                <li>List ID Buyer hanya dipakai untuk lead/order yang sudah DP Masuk, Lunas, atau bukti pembayaran Valid.</li>
                                <li>Fonnte membutuhkan token aktif, provider Fonnte dicentang, dan nomor WhatsApp customer valid.</li>
                                <li>Token disimpan dengan aman di area penyimpanan website dan tidak ditampilkan penuh di halaman ini.</li>
                            </ul>
                        </section>

                        <section class="mi-card">
                            <h2>Log Terbaru</h2>
                            <p>Log menampilkan status pengiriman tanpa menyimpan nomor/email lengkap.</p>
                            <div class="mi-log">
                                <?php foreach ($recentLogs as $row): ?>
                                    <div class="mi-log-row">
                                        <strong>
                                            <span><?= esc(strtoupper((string)($row['provider'] ?? '-'))); ?> · <?= esc((string)($row['type'] ?? '-')); ?></span>
                                            <span class="mi-badge <?= (string)($row['status'] ?? '') === 'success' ? 'mi-badge--ok' : ((string)($row['status'] ?? '') === 'failed' ? 'mi-badge--warn' : ''); ?>"><?= esc((string)($row['status'] ?? 'info')); ?></span>
                                        </strong>
                                        <small><?= esc((string)($row['time'] ?? '')); ?></small>
                                        <small><?= esc((string)($row['message'] ?? '')); ?></small>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$recentLogs): ?><p>Belum ada log integrasi.</p><?php endif; ?>
                            </div>
                        </section>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
