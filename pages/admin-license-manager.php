<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

seo_noindex();
$GLOBALS['admin_page'] = true;
$message = (string)($_GET['message'] ?? '');
$error = '';
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';

function admin_license_manager_logged_in(): bool { return function_exists('admin_panel_logged_in') ? admin_panel_logged_in() : !empty($_SESSION['admin_articles_logged_in']); }
if (($_GET['action'] ?? '') === 'logout') { unset($_SESSION['admin_articles_logged_in']); redirect_302('admin/license-manager'); }

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_license_manager_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) { $_SESSION['admin_articles_logged_in'] = true; redirect_302('admin/license-manager'); }
        $error = 'Password admin salah.';
    } else {
        $action = (string)($_POST['form_action'] ?? '');
        if ($action === 'save_license_manager') {
            $settings = [
                'enabled' => !empty($_POST['enabled']),
                'mode' => (string)($_POST['mode'] ?? 'hybrid'),
                'domain_lock_enabled' => !empty($_POST['domain_lock_enabled']),
                'local_fallback_enabled' => !empty($_POST['local_fallback_enabled']),
                'central_base_url' => (string)($_POST['central_base_url'] ?? ''),
                'central_api_key' => (string)($_POST['central_api_key'] ?? ''),
                'central_product_id' => (string)($_POST['central_product_id'] ?? ''),
                'request_timeout_seconds' => (int)($_POST['request_timeout_seconds'] ?? 8),
                'local_activation_limit_default' => (int)($_POST['local_activation_limit_default'] ?? 1),
                'cache_verify_minutes' => (int)($_POST['cache_verify_minutes'] ?? 720),
                'allow_reset_by_admin' => !empty($_POST['allow_reset_by_admin']),
                'public_api_note' => (string)($_POST['public_api_note'] ?? ''),
            ];
            if (license_manager_write_settings($settings)) { redirect_302('admin/license-manager?message=' . rawurlencode('Pengaturan License Manager berhasil disimpan.')); }
            $error = 'Pengaturan belum bisa disimpan. Pastikan folder storage writable.';
        } elseif ($action === 'test_activate') {
            $result = license_manager_activate((string)($_POST['test_license_key'] ?? ''), (string)($_POST['test_domain'] ?? ''), ['site_url' => 'https://' . (string)($_POST['test_domain'] ?? ''), 'allow_create_local' => true]);
            $message = !empty($result['ok']) ? 'Test aktivasi berhasil: ' . (string)($result['message'] ?? '') : '';
            $error = empty($result['ok']) ? 'Test aktivasi gagal: ' . (string)($result['message'] ?? '') : '';
        } elseif ($action === 'reset_domain') {
            if (license_manager_reset_domain((string)($_POST['license_key'] ?? ''), (string)($_POST['domain'] ?? ''))) { redirect_302('admin/license-manager?message=' . rawurlencode('Domain aktivasi lisensi berhasil direset.')); }
            $error = 'Domain lisensi belum bisa direset.';
        }
    }
}

$loggedIn = admin_license_manager_logged_in();
$settings = $loggedIn ? license_manager_read_settings() : license_manager_default_settings();
$summary = $loggedIn ? license_manager_summary() : [];
$activateUrl = url('license/activate');
$verifyUrl = url('license/verify');

set_seo(['title' => 'License Manager - Admin', 'description' => 'Kelola lisensi domain local, central, dan hybrid untuk software/template.', 'robots' => 'noindex, nofollow']);
require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="main-content" class="admin-shell admin-license-manager-shell">
    <section class="admin-hero"><div class="container admin-hero__inner"><div><div class="admin-eyebrow">License Manager</div><h1>Domain-Locked License Bridge</h1><p>Fasilitasi UMKM yang sudah punya server lisensi pusat dan yang masih butuh mode lokal/fallback.</p></div><div class="admin-hero__actions"><a class="admin-btn admin-btn--light" href="<?= esc(url('admin/member-area')); ?>">Member Area</a><a class="admin-btn admin-btn--light" href="<?= esc(url('member-area')); ?>" target="_blank" rel="noopener">Lihat Buyer Area</a></div></div></section>
    <section class="admin-section"><div class="container admin-stack">
        <style>.admin-license-manager-shell .lm-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:18px;align-items:start}.admin-license-manager-shell .lm-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-license-manager-shell .lm-card h2,.admin-license-manager-shell .lm-card h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-license-manager-shell .lm-field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-license-manager-shell label{display:grid;gap:7px;color:#334155;font-weight:850;font-size:.86rem}.admin-license-manager-shell input,.admin-license-manager-shell select,.admin-license-manager-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;background:#fff;color:#0f172a}.admin-license-manager-shell select option{background:#fff;color:#0f172a}.admin-license-manager-shell textarea{min-height:100px}.admin-license-manager-shell .lm-check{display:flex!important;align-items:center;gap:8px}.admin-license-manager-shell .lm-check input{width:auto}.admin-license-manager-shell .lm-record{border:1px solid #e2e8f0;border-radius:18px;padding:12px;background:#f8fafc;margin-bottom:10px}.admin-license-manager-shell .lm-record code{word-break:break-all}.admin-license-manager-shell .lm-endpoints{display:grid;gap:8px}.admin-license-manager-shell .lm-endpoints code{display:block;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:10px;word-break:break-all}@media(max-width:920px){.admin-license-manager-shell .lm-grid,.admin-license-manager-shell .lm-field-grid{grid-template-columns:1fr}}</style>
        <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?><?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>
        <?php if (!$loggedIn): ?>
            <div class="admin-card admin-login-card"><h2>Login Admin</h2><p>Masukkan password admin untuk membuka License Manager.</p><form method="post" class="admin-login-form"><?= csrf_field(); ?><label>Password Admin</label><input type="password" name="password" required autofocus><button class="admin-btn admin-btn--primary" type="submit">Login</button></form></div>
        <?php else: ?>
            <?php admin_panel_render_nav('admin/license-manager'); ?>
            <div class="admin-grid admin-grid--stats"><div class="admin-card"><span class="admin-badge">Mode</span><h2><?= esc(strtoupper((string)($settings['mode'] ?? 'hybrid'))); ?></h2><p>Local / Central / Hybrid.</p></div><div class="admin-card"><span class="admin-badge">Lisensi</span><h2><?= (int)($summary['total'] ?? 0); ?></h2><p>Total license key.</p></div><div class="admin-card"><span class="admin-badge">Aktif</span><h2><?= (int)($summary['active'] ?? 0); ?></h2><p>Lisensi aktif.</p></div><div class="admin-card"><span class="admin-badge">Domain</span><h2><?= (int)($summary['domains'] ?? 0); ?></h2><p>Domain teraktivasi.</p></div><div class="admin-card"><span class="admin-badge">Expired</span><h2><?= (int)($summary['expired'] ?? 0); ?></h2><p>Perlu ditindak.</p></div></div>
            <div class="lm-grid"><form method="post" class="lm-card"><?= csrf_field(); ?><input type="hidden" name="form_action" value="save_license_manager"><h2>Pengaturan License Bridge</h2><label class="lm-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan License Manager</label><label>Mode lisensi<select name="mode"><option value="local" <?= ($settings['mode'] ?? '') === 'local' ? 'selected' : ''; ?>>Local Only — untuk UMKM belum punya server pusat</option><option value="central" <?= ($settings['mode'] ?? '') === 'central' ? 'selected' : ''; ?>>Central Only — semua validasi ke server pusat</option><option value="hybrid" <?= ($settings['mode'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>Hybrid — server pusat + fallback lokal</option></select></label><div class="lm-field-grid"><label>Central license base URL<input name="central_base_url" value="<?= esc((string)($settings['central_base_url'] ?? '')); ?>" placeholder="https://license.domain.com"></label><label>Central API key<input name="central_api_key" value="<?= esc((string)($settings['central_api_key'] ?? '')); ?>" placeholder="Simpan secret server lisensi"></label><label>Central product ID<input name="central_product_id" value="<?= esc((string)($settings['central_product_id'] ?? '')); ?>" placeholder="ugrowth-template"></label><label>Timeout request, detik<input type="number" name="request_timeout_seconds" min="2" max="30" value="<?= esc((string)($settings['request_timeout_seconds'] ?? 8)); ?>"></label><label>Batas aktivasi lokal default<input type="number" name="local_activation_limit_default" min="1" max="100" value="<?= esc((string)($settings['local_activation_limit_default'] ?? 1)); ?>"></label><label>Cache verify client, menit<input type="number" name="cache_verify_minutes" min="5" max="10080" value="<?= esc((string)($settings['cache_verify_minutes'] ?? 720)); ?>"></label></div><label class="lm-check"><input type="checkbox" name="domain_lock_enabled" value="1" <?= !empty($settings['domain_lock_enabled']) ? 'checked' : ''; ?>> Kunci lisensi berdasarkan domain</label><label class="lm-check"><input type="checkbox" name="local_fallback_enabled" value="1" <?= !empty($settings['local_fallback_enabled']) ? 'checked' : ''; ?>> Izinkan fallback lokal saat server pusat gagal</label><label class="lm-check"><input type="checkbox" name="allow_reset_by_admin" value="1" <?= !empty($settings['allow_reset_by_admin']) ? 'checked' : ''; ?>> Admin boleh reset domain aktivasi</label><label>Catatan API lisensi<textarea name="public_api_note"><?= esc((string)($settings['public_api_note'] ?? '')); ?></textarea></label><button class="admin-btn admin-btn--primary" type="submit">Simpan License Manager</button></form>
                <aside class="lm-card"><h2>Endpoint Aktivasi</h2><p class="admin-muted">Dipakai software/template yang dijual customer. API key pusat tetap disimpan server-side.</p><div class="lm-endpoints"><code>POST <?= esc($activateUrl); ?></code><code>POST/GET <?= esc($verifyUrl); ?></code></div><hr><h3>Test Aktivasi Lokal/Hybrid</h3><form method="post" class="admin-stack"><?= csrf_field(); ?><input type="hidden" name="form_action" value="test_activate"><label>License key<input name="test_license_key" placeholder="UGR-XXXX-XXXX-XXXX-XXXX"></label><label>Domain<input name="test_domain" placeholder="contoh.com"></label><button class="admin-btn admin-btn--soft" type="submit">Test Aktivasi</button></form></aside>
            </div>
            <div class="lm-card"><h2>Lisensi & Domain Terbaru</h2><?php foreach ((array)($summary['recent'] ?? []) as $record): ?><div class="lm-record"><strong><?= esc((string)($record['product_title'] ?? 'License')); ?></strong><br><code><?= esc((string)($record['license_key'] ?? '-')); ?></code><p><?= esc((string)($record['customer_email'] ?? '-')); ?> · Status: <?= esc((string)($record['status'] ?? 'active')); ?> · Limit: <?= esc((string)($record['activation_limit'] ?? '1')); ?></p><?php $domains = (array)($record['domains'] ?? []); if ($domains): ?><p><b>Domain:</b> <?= esc(implode(', ', array_keys($domains))); ?></p><?php foreach ($domains as $domain => $row): ?><form method="post" style="display:inline-flex;margin:.2rem .2rem .2rem 0"><?= csrf_field(); ?><input type="hidden" name="form_action" value="reset_domain"><input type="hidden" name="license_key" value="<?= esc((string)($record['license_key'] ?? '')); ?>"><input type="hidden" name="domain" value="<?= esc((string)$domain); ?>"><button class="admin-btn admin-btn--ghost" type="submit">Reset <?= esc((string)$domain); ?></button></form><?php endforeach; ?><?php else: ?><p class="admin-muted">Belum ada domain aktivasi.</p><?php endif; ?></div><?php endforeach; ?><?php if (empty($summary['recent'])): ?><p class="admin-muted">Belum ada license key/aktivasi. Lisensi akan dibuat otomatis saat akses member produk lisensi dirilis.</p><?php endif; ?></div>
        <?php endif; ?>
    </div></section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
