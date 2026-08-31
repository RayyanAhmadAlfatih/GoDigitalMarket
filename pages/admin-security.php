<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = (string)($_POST['security_action'] ?? 'settings');

    if ($action === 'settings') {
        $timeout = max(900, min(86400, (int)($_POST['admin_session_timeout'] ?? 7200)));
        $result = app_env_update(['ADMIN_SESSION_TIMEOUT' => (string)$timeout]);
        if (!empty($result['success'])) {
            activity_log_record('update_security_settings', 'system', null, 'Admin menyimpan pengaturan keamanan login.', ['timeout' => $timeout]);
            redirect_302('admin/security?message=' . rawurlencode('Pengaturan keamanan berhasil disimpan.'));
        }
        $error = (string)($result['message'] ?? 'Pengaturan gagal disimpan.');
    }

    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = trim((string)($_POST['new_password'] ?? ''));
        $confirm = trim((string)($_POST['confirm_password'] ?? ''));
        $adminPassword = admin_auth_password();
        if (!hash_equals($adminPassword, $current)) {
            $error = 'Password lama belum sesuai.';
        } elseif ($new !== $confirm) {
            $error = 'Konfirmasi password baru belum sama.';
        } elseif (strlen($new) < 10 || admin_password_needs_setup($new)) {
            $error = 'Password baru minimal 10 karakter dan jangan memakai password default/lemah.';
        } else {
            $result = app_env_update(['ADMIN_PASSWORD' => $new]);
            if (!empty($result['success'])) {
                activity_log_record('update_admin_password', 'system', null, 'Admin mengganti password dashboard.', []);
                redirect_302('admin/security?message=' . rawurlencode('Password admin berhasil diperbarui. Login berikutnya gunakan password baru.'));
            }
            $error = (string)($result['message'] ?? 'Password gagal disimpan.');
        }
    }
}

$adminPassword = admin_auth_password();
$passwordSafe = $adminPassword !== '' && !admin_password_needs_setup($adminPassword);
$envExists = is_file(app_env_path());
$envWritable = is_writable(ROOT_PATH) || is_writable(app_env_path());
$timeout = admin_auth_timeout_seconds();

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Keamanan Admin - ' . SITE_NAME,
    'description' => 'Pengaturan keamanan admin, password, dan session dashboard.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="admin-content" class="admin-shell admin-security-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Sistem</div>
                <h1>Keamanan Admin</h1>
                <p>Kelola password admin, durasi login, dan checklist keamanan dasar sebelum website live.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Password Admin</span><h2><?= $passwordSafe ? 'Aman' : 'Perlu diganti'; ?></h2><p><?= $passwordSafe ? 'Password tidak memakai nilai default.' : 'Gunakan password kuat sebelum live.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Session Login</span><h2><?= (int)round($timeout / 60); ?> menit</h2><p>Admin logout otomatis setelah tidak aktif.</p></div>
                <div class="admin-card"><span class="admin-badge">File .env</span><h2><?= $envExists ? 'Ada' : 'Belum'; ?></h2><p><?= $envWritable ? 'Bisa ditulis dashboard.' : 'Belum writable oleh PHP.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Index Admin</span><h2>Noindex</h2><p>Halaman admin tidak disiapkan untuk mesin pencari.</p></div>
            </div>

            <div data-admin-page-tab-scope>
                <div class="admin-page-subtabs admin-page-subtabs--3" style="margin-top:18px" role="tablist" aria-label="Bagian Keamanan">
                    <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="security-check"><span>1. Checklist</span><small>Kesiapan dasar</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="security-password"><span>2. Password Admin</span><small>Ganti password</small></button>
                    <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="security-session"><span>3. Session Login</span><small>Durasi akses</small></button>
                </div>
                <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian keamanan</span><select data-admin-page-tab-select aria-label="Pilih bagian Keamanan"><option value="security-check">1. Checklist</option><option value="security-password">2. Password Admin</option><option value="security-session">3. Session Login</option></select></label></div>

                <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="security-check">
                <div class="admin-card">
                    <div class="admin-form-head"><span class="admin-badge">Checklist Keamanan</span><h2>Pemeriksaan Dasar</h2><p>Ini bukan security suite lengkap, tapi guardrail awal agar admin tidak lupa hal penting.</p></div>
                    <ul class="admin-checklist">
                        <li>Password admin kuat dan tidak memakai default.</li>
                        <li>File .env tersedia di server dan tidak ikut dibagikan ke publik.</li>
                        <li>Folder storage/log/cache tidak menampilkan listing publik.</li>
                        <li>Backup dibuat sebelum update source code.</li>
                        <li>SMTP dan payment gateway hanya diaktifkan setelah credential benar.</li>
                    </ul>
                </div>
            </section>

            <section class="admin-page-tab-panel" data-admin-page-tab-panel="security-password" hidden>
                <form method="post" class="admin-card admin-form-grid">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="security_action" value="password">
                    <div class="admin-form-head"><span class="admin-badge">Password Admin</span><h2>Ganti Password Dashboard</h2><p>Password disimpan di file .env agar tetap mudah dipindah hosting.</p></div>
                    <label>Password Lama<input type="password" name="current_password" autocomplete="current-password" required></label>
                    <label>Password Baru<input type="password" name="new_password" autocomplete="new-password" required minlength="10"></label>
                    <label>Ulangi Password Baru<input type="password" name="confirm_password" autocomplete="new-password" required minlength="10"></label>
                    <div class="admin-form-actions"><button class="admin-btn admin-btn--primary" type="submit">Simpan Password Baru</button></div>
                </form>
            </section>

            <section class="admin-page-tab-panel" data-admin-page-tab-panel="security-session" hidden>
                <form method="post" class="admin-card admin-form-grid">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="security_action" value="settings">
                    <div class="admin-form-head"><span class="admin-badge">Session Login</span><h2>Durasi Login Admin</h2><p>Nilai aman antara 15 menit sampai 24 jam.</p></div>
                    <label>Auto logout setelah tidak aktif<select name="admin_session_timeout"><option value="900" <?= $timeout === 900 ? 'selected' : ''; ?>>15 menit</option><option value="1800" <?= $timeout === 1800 ? 'selected' : ''; ?>>30 menit</option><option value="3600" <?= $timeout === 3600 ? 'selected' : ''; ?>>1 jam</option><option value="7200" <?= $timeout === 7200 ? 'selected' : ''; ?>>2 jam</option><option value="14400" <?= $timeout === 14400 ? 'selected' : ''; ?>>4 jam</option><option value="86400" <?= $timeout === 86400 ? 'selected' : ''; ?>>24 jam</option></select></label>
                    <div class="admin-form-actions"><button class="admin-btn admin-btn--primary" type="submit">Simpan Durasi Login</button></div>
                </form>
            </section>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
