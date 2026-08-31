<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$GLOBALS['admin_page'] = true;

$readiness = function_exists('first_run_readiness_checks') ? first_run_readiness_checks() : ['checks' => [], 'required_ok' => false, 'completed' => false, 'installer_open' => false];
$locked = empty($readiness['installer_open']);
$error = '';
$success = '';
$resultLogin = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($locked) {
        $error = 'Installer sudah terkunci. Login sebagai owner untuk membuka pengaturan awal.';
    } elseif (!verify_csrf()) {
        $error = 'Sesi keamanan tidak valid. Refresh halaman lalu coba lagi.';
    } else {
        $result = function_exists('first_run_install') ? first_run_install($_POST) : ['ok' => false, 'message' => 'Installer belum tersedia.'];
        if (!empty($result['ok'])) {
            $success = (string)($result['message'] ?? 'Setup berhasil disimpan.');
            $resultLogin = (string)($result['owner_login'] ?? '');
            $readiness = function_exists('first_run_readiness_checks') ? first_run_readiness_checks() : $readiness;
            $locked = false;
        } else {
            $error = (string)($result['message'] ?? 'Setup belum berhasil disimpan.');
        }
    }
}

$themePresets = function_exists('theme_presets') ? theme_presets() : [];
$currentPreset = function_exists('theme_setting') ? (string)theme_setting('theme_preset', 'biru-profesional') : 'biru-profesional';
$currentPreset = isset($themePresets[$currentPreset]) ? $currentPreset : 'biru-profesional';
$ownerReady = function_exists('first_run_owner_auth_ready') ? first_run_owner_auth_ready() : false;

set_seo([
    'title' => 'Setup Awal Website - ' . SITE_NAME,
    'description' => 'Installer dan setup awal website.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
?>
<body class="admin-dashboard-body admin-first-run-body <?= esc(function_exists('theme_body_motion_classes') ? theme_body_motion_classes('admin') : ''); ?>">
    <main class="admin-first-run-page" id="main-content">
        <section class="admin-first-run-hero admin-card">
            <div>
                <span class="admin-badge">Setup Awal</span>
                <h1>Installer & First Run Setup</h1>
                <p>Lengkapi pengaturan paling penting dulu: URL website, akun owner, identitas brand, dan checklist server. Setelah akun owner aman, installer otomatis terkunci.</p>
            </div>
            <div class="admin-first-run-actions">
                <a class="admin-btn admin-btn--secondary" href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Website</a>
                <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/login')); ?>">Login Admin</a>
            </div>
        </section>

        <?php if ($locked): ?>
            <section class="admin-card admin-alert admin-alert--warning">
                <strong>Installer terkunci.</strong><br>
                Akun owner sudah tersedia. Untuk mengubah pengaturan awal, login sebagai owner lalu buka halaman ini lagi, atau aktifkan sementara <code>INSTALLER_ENABLED=true</code> di file <code>.env</code>.
            </section>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <section class="admin-card admin-alert admin-alert--success">
                <strong><?= esc($success); ?></strong>
                <?php if ($resultLogin !== ''): ?><br>Login owner: <code><?= esc($resultLogin); ?></code><?php endif; ?>
                <div style="margin-top:.85rem"><a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/login')); ?>">Masuk Dashboard</a></div>
            </section>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <section class="admin-card admin-alert admin-alert--error"><?= esc($error); ?></section>
        <?php endif; ?>

        <div class="admin-first-run-grid">
            <section class="admin-card">
                <div class="admin-section-title">
                    <span>01</span>
                    <div>
                        <h2>Checklist Server</h2>
                        <p>Pastikan item wajib aman sebelum setup disimpan.</p>
                    </div>
                </div>
                <div class="admin-first-run-checklist">
                    <?php foreach ((array)($readiness['checks'] ?? []) as $check): ?>
                        <?php
                        $status = (string)($check['status'] ?? 'warning');
                        $icon = $status === 'ok' ? '✓' : ($status === 'error' ? '!' : 'i');
                        ?>
                        <div class="admin-first-run-check admin-first-run-check--<?= esc($status); ?>">
                            <strong><?= esc($icon); ?></strong>
                            <div>
                                <b><?= esc((string)($check['label'] ?? 'Check')); ?></b>
                                <small><?= esc((string)($check['note'] ?? '')); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <form method="post" class="admin-card admin-first-run-form">
                <?= csrf_field(); ?>
                <div class="admin-section-title">
                    <span>02</span>
                    <div>
                        <h2>Setup Website</h2>
                        <p>Isi data awal. Semuanya masih bisa diedit lagi dari dashboard.</p>
                    </div>
                </div>

                <label>URL Website</label>
                <input name="app_url" value="<?= esc((string)($_POST['app_url'] ?? BASE_URL)); ?>" placeholder="https://domain-anda.com" required>
                <small class="admin-muted">Gunakan domain final saat website sudah live. Untuk localhost boleh pakai URL localhost.</small>

                <div class="admin-grid-2">
                    <label>Nama Bisnis
                        <input name="business_name" value="<?= esc((string)($_POST['business_name'] ?? SITE_NAME)); ?>" placeholder="Nama brand / bisnis" required>
                    </label>
                    <label>Preset Warna
                        <select name="theme_preset">
                            <?php foreach ($themePresets as $key => $preset): ?>
                                <option value="<?= esc((string)$key); ?>" <?= ((string)($_POST['theme_preset'] ?? $currentPreset) === (string)$key) ? 'selected' : ''; ?>><?= esc((string)($preset['label'] ?? $key)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="admin-grid-2">
                    <label>Email Admin
                        <input name="email" type="email" value="<?= esc((string)($_POST['email'] ?? SITE_EMAIL)); ?>" placeholder="admin@domain.com">
                    </label>
                    <label>WhatsApp Bisnis
                        <input name="whatsapp" value="<?= esc((string)($_POST['whatsapp'] ?? SITE_WHATSAPP)); ?>" placeholder="6281234567890">
                    </label>
                </div>

                <hr class="admin-soft-separator">

                <div class="admin-section-title admin-section-title--compact">
                    <span>03</span>
                    <div>
                        <h2>Akun Owner</h2>
                        <p>Akun ini punya akses penuh ke seluruh dashboard.</p>
                    </div>
                </div>

                <div class="admin-grid-2">
                    <label>Nama Owner
                        <input name="owner_name" value="<?= esc((string)($_POST['owner_name'] ?? 'Owner')); ?>" placeholder="Nama owner">
                    </label>
                    <label>Email Owner
                        <input name="owner_email" type="email" value="<?= esc((string)($_POST['owner_email'] ?? SITE_EMAIL)); ?>" placeholder="owner@domain.com">
                    </label>
                </div>

                <label>Username Owner</label>
                <input name="owner_username" value="<?= esc((string)($_POST['owner_username'] ?? 'owner')); ?>" placeholder="owner">

                <div class="admin-grid-2">
                    <label>Password Owner <?= $ownerReady ? '<small class="admin-muted">kosongkan jika tidak ingin mengganti</small>' : ''; ?>
                        <input name="owner_password" type="password" autocomplete="new-password" <?= $ownerReady ? '' : 'required'; ?> placeholder="Minimal 10 karakter">
                    </label>
                    <label>Ulangi Password
                        <input name="owner_password_confirm" type="password" autocomplete="new-password" <?= $ownerReady ? '' : 'required'; ?> placeholder="Ulangi password">
                    </label>
                </div>
                <small class="admin-muted">Saran: gunakan huruf besar, huruf kecil, angka, dan simbol. Jangan pakai password default.</small>

                <label>Durasi Sesi Admin</label>
                <select name="admin_session_timeout">
                    <?php foreach ([1800 => '30 menit', 3600 => '1 jam', 7200 => '2 jam', 14400 => '4 jam', 28800 => '8 jam'] as $seconds => $label): ?>
                        <option value="<?= esc((string)$seconds); ?>" <?= (int)($_POST['admin_session_timeout'] ?? 7200) === $seconds ? 'selected' : ''; ?>><?= esc($label); ?></option>
                    <?php endforeach; ?>
                </select>

                <button class="admin-btn admin-btn--primary admin-btn--full" type="submit" <?= ($locked || empty($readiness['required_ok'])) ? 'disabled' : ''; ?>>Simpan Setup Awal</button>
                <?php if (empty($readiness['required_ok'])): ?>
                    <div class="admin-alert admin-alert--warning">Tombol disimpan nonaktif sampai checklist wajib aman.</div>
                <?php endif; ?>
            </form>
        </div>
    </main>
</body>
</html>
