<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$GLOBALS['admin_page'] = true;

$message = trim((string)($_GET['message'] ?? ''));
$error = '';
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$id = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sesi keamanan tidak valid. Refresh halaman lalu coba lagi.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        if ($password !== $confirm) {
            $error = 'Konfirmasi password belum sama.';
        } elseif ($token !== '' && $id !== '' && function_exists('admin_users_reset_password_with_token')) {
            $result = admin_users_reset_password_with_token($id, $token, $password);
            if (!empty($result['ok'])) {
                redirect_302('admin/login?message=' . rawurlencode((string)$result['message']));
            }
            $error = (string)($result['message'] ?? 'Gagal reset password.');
        } else {
            $error = 'Untuk keamanan, reset password akun role perlu link dari Owner/Super Admin di menu Manajemen User.';
        }
    }
}

set_seo([
    'title' => 'Reset Password Admin - ' . SITE_NAME,
    'description' => 'Reset password akun dashboard admin.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
?>
<body class="admin-login-body admin-login-body--soft-gradient admin-login-layout--center">
    <main class="admin-login-page" id="main-content">
        <section class="admin-login-panel admin-login-panel--center" aria-labelledby="reset-title">
            <form method="post" class="admin-card admin-login-card admin-login-card--standalone">
                <?= csrf_field(); ?>
                <input type="hidden" name="token" value="<?= esc($token); ?>">
                <input type="hidden" name="id" value="<?= esc($id); ?>">
                <div class="admin-login-card__head">
                    <span class="admin-sidebar__logo">🔐</span>
                    <div><strong id="reset-title">Reset Password Admin</strong><span>Akun role dashboard</span></div>
                </div>

                <?php if ($message !== ''): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
                <?php if ($error !== ''): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

                <?php if ($token !== '' && $id !== ''): ?>
                    <label>Password baru</label>
                    <input type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Minimal 8 karakter">
                    <label>Ulangi password baru</label>
                    <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
                    <button class="admin-btn admin-btn--primary admin-btn--full" type="submit">Simpan Password Baru</button>
                <?php else: ?>
                    <p class="admin-muted">Minta Owner/Super Admin membuat link reset dari menu <strong>Manajemen User & Role</strong>. Akun darurat berbasis <code>.env</code> tetap diubah langsung dari file <code>.env</code>.</p>
                    <a class="admin-btn admin-btn--primary admin-btn--full" href="<?= esc(url('admin/login')); ?>">Kembali Login</a>
                <?php endif; ?>

                <a class="admin-login-card__link" href="<?= esc(url('admin/login')); ?>">Masuk dashboard</a>
            </form>
        </section>
    </main>
</body>
</html>
