<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$GLOBALS['admin_page'] = true;

$message = trim((string)($_GET['message'] ?? ''));
$error = '';
$next = function_exists('admin_auth_clean_next_path')
    ? admin_auth_clean_next_path((string)($_GET['next'] ?? 'admin/produk'))
    : 'admin/produk';

if (function_exists('admin_auth_is_logged_in') && admin_auth_is_logged_in() && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    redirect_302($next);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $next = function_exists('admin_auth_clean_next_path')
        ? admin_auth_clean_next_path((string)($_POST['next'] ?? 'admin/produk'))
        : 'admin/produk';

    if (!verify_csrf()) {
        $error = 'Sesi keamanan tidak valid. Refresh halaman lalu coba lagi.';
    } else {
        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $result = function_exists('admin_auth_attempt_login')
            ? admin_auth_attempt_login($login, $password)
            : ['ok' => hash_equals((string)($_ENV['ADMIN_PASSWORD'] ?? ''), $password), 'user' => ['role' => 'owner']];

        if (!empty($result['ok'])) {
            $user = is_array($result['user'] ?? null) ? $result['user'] : [];
            $role = (string)($user['role'] ?? 'owner');
            if (function_exists('admin_users_can_access_path') && !admin_users_can_access_path($next, $user)) {
                $next = function_exists('admin_users_default_path_for_role') ? admin_users_default_path_for_role($role) : 'admin/brand';
            }

            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'dashboard', 'role' => $role, 'source' => (string)($result['source'] ?? '')]);
            }

            redirect_302($next);
        }

        $error = (string)($result['message'] ?? 'Email/username atau password admin salah.');
    }
}

$loginBrandingEnabled = function_exists('theme_bool_setting') ? theme_bool_setting('login_branding_enabled', true) : true;
$loginLayout = function_exists('theme_setting') ? (string)theme_setting('login_layout', 'split') : 'split';
$loginLayout = in_array($loginLayout, ['split', 'center', 'card'], true) ? $loginLayout : 'split';
$loginBackgroundStyle = function_exists('theme_setting') ? (string)theme_setting('login_background_style', 'soft-gradient') : 'soft-gradient';
$loginBackgroundStyle = in_array($loginBackgroundStyle, ['soft-gradient', 'clean', 'brand-gradient', 'image'], true) ? $loginBackgroundStyle : 'soft-gradient';
$loginLogo = function_exists('theme_login_logo_url') ? theme_login_logo_url() : asset('images/logo.png');
$loginBg = function_exists('theme_login_background_url') ? theme_login_background_url() : '';
$loginBadge = function_exists('theme_setting') ? (string)theme_setting('login_badge', 'Dashboard Admin') : 'Dashboard Admin';
$loginTitle = function_exists('theme_setting') ? (string)theme_setting('login_title', 'Masuk ke Dashboard') : 'Masuk ke Dashboard';
$loginTagline = function_exists('theme_setting') ? (string)theme_setting('login_tagline', 'Kelola website bisnis dari satu tempat.') : 'Kelola website bisnis dari satu tempat.';
$loginDescription = function_exists('theme_setting') ? (string)theme_setting('login_description', 'Atur katalog, landing page, checkout, form prospek, artikel SEO, dan insight bisnis tanpa ribet.') : 'Atur katalog, landing page, checkout, form prospek, artikel SEO, dan insight bisnis tanpa ribet.';
$loginButton = function_exists('theme_setting') ? (string)theme_setting('login_button_text', 'Masuk Dashboard') : 'Masuk Dashboard';
$loginFooterNote = function_exists('theme_setting') ? (string)theme_setting('login_footer_note', 'Dashboard resmi {business_name}') : 'Dashboard resmi {business_name}';
$loginFooterNote = str_replace('{business_name}', SITE_NAME, $loginFooterNote);

set_seo([
    'title' => 'Login Admin - ' . SITE_NAME,
    'description' => 'Halaman login dashboard admin.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
?>
<body class="admin-login-body admin-login-body--<?= esc($loginBackgroundStyle); ?> admin-login-layout--<?= esc($loginLayout); ?> <?= $loginBrandingEnabled ? 'admin-login-body--branded' : ''; ?> <?= esc(function_exists('theme_body_motion_classes') ? theme_body_motion_classes('admin') : ''); ?>"<?= ($loginBackgroundStyle === 'image' && $loginBg !== '') ? ' style="--login-bg-image:url(\'' . esc($loginBg) . '\');"' : ''; ?>>
    <main class="admin-login-page" id="main-content">
        <section class="admin-login-panel admin-login-panel--<?= esc($loginLayout); ?>" aria-labelledby="admin-login-title">
            <div class="admin-login-panel__copy">
                <?php if ($loginBrandingEnabled): ?>
                    <span class="admin-login-brand-logo"><img src="<?= esc($loginLogo); ?>" alt="<?= esc(SITE_NAME); ?>" onerror="this.style.display='none'"></span>
                <?php endif; ?>
                <span class="admin-badge"><?= esc($loginBadge !== '' ? $loginBadge : 'Dashboard Admin'); ?></span>
                <h1 id="admin-login-title"><?= esc($loginTitle !== '' ? $loginTitle : 'Masuk ke Dashboard'); ?></h1>
                <?php if ($loginTagline !== ''): ?><p class="admin-login-tagline"><?= esc($loginTagline); ?></p><?php endif; ?>
                <?php if ($loginDescription !== ''): ?><p class="admin-login-description"><?= esc($loginDescription); ?></p><?php endif; ?>
                <div class="admin-login-feature-pills" aria-label="Fitur dashboard">
                    <span>Katalog</span><span>Landing Page</span><span>Lead</span><span>SEO</span>
                </div>
            </div>

            <form method="post" class="admin-card admin-login-card admin-login-card--standalone">
                <?= csrf_field(); ?>
                <input type="hidden" name="next" value="<?= esc($next); ?>">

                <div class="admin-login-card__head">
                    <?php if ($loginBrandingEnabled): ?>
                        <img src="<?= esc($loginLogo); ?>" alt="<?= esc(SITE_NAME); ?>" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div>
                        <strong><?= esc(SITE_NAME); ?></strong>
                        <span><?= esc($loginFooterNote !== '' ? $loginFooterNote : 'Dashboard admin'); ?></span>
                    </div>
                </div>

                <?php if ($message !== ''): ?>
                    <div class="admin-alert admin-alert--success"><?= esc($message); ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="admin-alert admin-alert--error"><?= esc($error); ?></div>
                <?php endif; ?>

                <label for="login">Email / Username Admin</label>
                <input id="login" name="login" placeholder="Masukkan email atau username admin" autocomplete="username" autofocus>
                <small class="admin-muted">Gunakan akun admin yang sudah dibuat owner. Untuk akses owner utama, kolom ini boleh dikosongkan.</small>

                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="Masukkan password" required autocomplete="current-password">

                <button type="submit" class="admin-btn admin-btn--primary admin-btn--full"><?= esc($loginButton !== '' ? $loginButton : 'Masuk Dashboard'); ?></button>
                <a class="admin-login-card__link" href="<?= esc(url('admin/password-reset')); ?>">Lupa password akun admin?</a>
                <a class="admin-login-card__link" href="<?= esc(url('')); ?>">Kembali ke website</a>
            </form>
        </section>
    </main>
</body>
</html>
