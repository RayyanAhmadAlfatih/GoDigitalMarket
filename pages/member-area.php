<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$settings = function_exists('member_access_read_settings') ? member_access_read_settings() : [];
$buyerSettings = function_exists('buyer_account_read_settings') ? buyer_account_read_settings() : [];
$notice = '';
$error = '';

if (function_exists('buyer_account_logout') && ($_GET['logout'] ?? '') === '1') {
    buyer_account_logout();
    redirect_302('member-area?message=' . rawurlencode('Anda sudah keluar dari Member Area.'));
}

if (!empty($_GET['message'])) {
    $notice = (string)$_GET['message'];
}

if (!empty($_GET['magic']) && function_exists('buyer_account_login_with_magic')) {
    $magicResult = buyer_account_login_with_magic((string)($_GET['email'] ?? ''), (string)$_GET['magic']);
    if (!empty($magicResult['ok'])) {
        redirect_302('member-area?message=' . rawurlencode('Magic login berhasil.'));
    }
    $error = (string)($magicResult['message'] ?? 'Magic link tidak valid.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (function_exists('verify_csrf') && !verify_csrf()) {
        $error = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $action = (string)($_POST['form_action'] ?? 'lookup_access');
        if ($action === 'password_login' && function_exists('buyer_account_login_with_password')) {
            $result = buyer_account_login_with_password((string)($_POST['login_email'] ?? ''), (string)($_POST['login_password'] ?? ''));
            if (!empty($result['ok'])) {
                redirect_302('member-area?message=' . rawurlencode('Login berhasil.'));
            }
            $error = (string)($result['message'] ?? 'Login belum berhasil.');
        } elseif ($action === 'request_magic' && function_exists('buyer_account_request_magic_link')) {
            $result = buyer_account_request_magic_link((string)($_POST['magic_email'] ?? ''));
            if (!empty($result['ok'])) {
                $notice = 'Magic link berhasil dibuat. Untuk testing lokal, link ditampilkan di bawah.';
                $GLOBALS['member_magic_url_preview'] = (string)($result['magic_url'] ?? '');
            } else {
                $error = (string)($result['message'] ?? 'Magic link belum bisa dibuat.');
            }
        } elseif ($action === 'update_profile' && function_exists('buyer_account_update_profile')) {
            $current = function_exists('buyer_account_current') ? buyer_account_current() : null;
            if (!$current) {
                $error = 'Silakan login atau buka akses member dulu sebelum memperbarui profil.';
            } else {
                $result = buyer_account_update_profile((string)($current['email'] ?? ''), [
                    'name' => (string)($_POST['buyer_name'] ?? ''),
                    'phone' => (string)($_POST['buyer_phone'] ?? ''),
                ]);
                if (!empty($result['ok'])) {
                    $notice = (string)$result['message'];
                    $currentBuyer = is_array($result['account'] ?? null) ? $result['account'] : $current;
                } else {
                    $error = (string)($result['message'] ?? 'Profil belum bisa disimpan.');
                }
            }
        } elseif ($action === 'set_password' && function_exists('buyer_account_set_password')) {
            $current = function_exists('buyer_account_current') ? buyer_account_current() : null;
            if (!$current) {
                $error = 'Silakan login atau buka akses member dulu sebelum membuat password.';
            } elseif ((string)($_POST['new_password'] ?? '') !== (string)($_POST['new_password_confirm'] ?? '')) {
                $error = 'Konfirmasi password belum sama.';
            } else {
                $result = buyer_account_set_password((string)($current['email'] ?? ''), (string)($_POST['new_password'] ?? ''));
                if (!empty($result['ok'])) {
                    $notice = (string)$result['message'];
                    $currentBuyer = function_exists('buyer_account_current') ? buyer_account_current() : $current;
                } else {
                    $error = (string)($result['message'] ?? 'Password belum bisa disimpan.');
                }
            }
        }
    }
}

$access = function_exists('member_access_clean') ? member_access_clean((string)($_GET['access'] ?? $_POST['access'] ?? ''), 140) : trim((string)($_GET['access'] ?? $_POST['access'] ?? ''));
$ref = function_exists('member_access_clean') ? member_access_clean((string)($_GET['ref'] ?? $_POST['ref'] ?? ''), 120) : trim((string)($_GET['ref'] ?? $_POST['ref'] ?? ''));
$orderToken = function_exists('member_access_clean') ? member_access_clean((string)($_GET['token'] ?? $_POST['token'] ?? ''), 120) : trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$email = strtolower(function_exists('member_access_clean') ? member_access_clean((string)($_GET['email'] ?? $_POST['email'] ?? ''), 180) : trim((string)($_GET['email'] ?? $_POST['email'] ?? '')));
$records = [];
$lookupMessage = '';
$currentBuyer = function_exists('buyer_account_current') ? buyer_account_current() : null;

if ($currentBuyer && function_exists('buyer_account_records')) {
    $records = buyer_account_records($currentBuyer);
} elseif ($access !== '' && function_exists('member_access_record_by_token')) {
    $record = member_access_record_by_token($access);
    if ($record) {
        $records[] = $record;
        if (function_exists('member_access_touch_open')) {
            member_access_touch_open($access);
        }
        if (function_exists('buyer_account_find_by_email') && function_exists('buyer_account_login')) {
            $acc = buyer_account_find_by_email((string)($record['customer_email'] ?? ''));
            if ($acc) {
                buyer_account_login($acc);
                $currentBuyer = $acc;
            }
        }
    } else {
        $lookupMessage = 'Token akses member tidak ditemukan atau sudah berubah.';
    }
} elseif ($ref !== '' && $orderToken !== '' && function_exists('order_find_by_reference') && function_exists('member_access_record_for_order')) {
    $order = order_find_by_reference($ref, $orderToken);
    if ($order) {
        $record = member_access_record_for_order($order);
        if ($record) {
            $records[] = $record;
        } else {
            $status = function_exists('member_access_public_status') ? member_access_public_status($order) : ['message' => 'Akses member belum aktif.'];
            $lookupMessage = (string)($status['message'] ?? 'Akses member belum aktif.');
        }
    } else {
        $lookupMessage = 'Order tidak ditemukan. Pastikan nomor order dan token sesuai link dari invoice/status order.';
    }
} elseif ($email !== '' && function_exists('member_access_records_by_email')) {
    $records = member_access_records_by_email($email, $ref);
    if (!$records) {
        $lookupMessage = 'Belum ada akses aktif untuk email/order tersebut. Gunakan link akses dari invoice atau hubungi admin.';
    }
}

$dashboardSummary = function_exists('member_dashboard_summary') ? member_dashboard_summary($records) : ['total' => count($records), 'active' => count($records), 'expired' => 0, 'course' => 0, 'license' => 0, 'download' => 0, 'next_expiry' => 'Tidak dibatasi'];
$dashboardAction = function_exists('member_dashboard_next_action') ? member_dashboard_next_action($records, $currentBuyer) : ['tone' => 'info', 'title' => 'Member Area', 'message' => '', 'href' => '#akses-produk', 'label' => 'Buka Akses'];
$supportUrl = function_exists('member_dashboard_support_url') ? member_dashboard_support_url($currentBuyer, $records) : (function_exists('wa_link') ? wa_link('Halo Admin, saya butuh bantuan akses member.') : '#');
$title = $records ? 'Member Area Anda' : 'Login Member Area';
set_seo([
    'title' => $title . ' - ' . SITE_NAME,
    'description' => 'Area member untuk mengakses course, produk digital, template, file, lisensi, dan membership pembelian.',
    'robots' => 'noindex, nofollow',
    'canonical' => strtok(current_url(), '?') ?: url('member-area'),
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<section class="mini-hero member-area-hero--template">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span>Member Area</span></div>
        <span class="dynamic-mini-label">Member Area</span>
        <h1><?= esc($records ? 'Akses Member Anda' : ((string)($buyerSettings['login_title'] ?? 'Masuk Member Area'))); ?></h1>
        <p><?= esc((string)($settings['public_note'] ?? 'Akses produk digital, course, membership, dan lisensi yang sudah aktif.')); ?></p>
    </div>
</section>

<section class="section member-area-section--template">
    <div class="container">
        <style>
            .member-area-layout--template{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:1rem;align-items:start}.member-area-card--template{background:#fff;border:1px solid #dbe7e2;border-radius:30px;box-shadow:0 22px 70px rgba(15,23,42,.07);padding:1.4rem}.member-area-card--template h2,.member-area-card--template h3{margin:.15rem 0 .55rem;color:#0f172a}.member-area-card--template p{color:#475569}.member-area-login--template{display:grid;gap:.85rem}.member-area-login--template label{display:grid;gap:.35rem;color:#334155;font-weight:850}.member-area-login--template input{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:.82rem .9rem;background:#fff;color:#0f172a}.member-area-alert--template{border-radius:18px;padding:1rem;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 82%,#fff);color:var(--primary-dark);margin:1rem 0}.member-area-alert--danger{border-color:#fecaca;background:#fef2f2;color:#991b1b}.member-access-record--template{display:grid;gap:1rem;margin-bottom:1rem}.member-access-top--template{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.member-access-badge--template{display:inline-flex;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-weight:900;padding:.38rem .7rem;font-size:.8rem}.member-access-badge--license{background:#fff7ed;border-color:#fed7aa;color:#9a3412}.member-access-badge--subscription{background:#ecfdf5;border-color:#bbf7d0;color:#047857}.member-access-grid--template{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.member-access-grid--template span{display:block;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:.85rem;color:#475569}.member-access-grid--template strong{display:block;color:#0f172a;font-size:.84rem;margin-bottom:.22rem}.member-course-list--template{display:grid;gap:.7rem}.member-course-module--template{border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc;padding:.9rem;display:grid;gap:.25rem}.member-license-box--template{border:1px dashed #fed7aa;background:#fff7ed;border-radius:20px;padding:1rem;color:#7c2d12}.member-subscription-box--template{border:1px dashed #86efac;background:#f0fdf4;border-radius:20px;padding:1rem;color:#14532d}.member-license-key--template{display:block;word-break:break-all;background:#fff;border:1px solid #fdba74;border-radius:14px;padding:.75rem;margin:.55rem 0;font-weight:900;color:#9a3412}.member-instruction--template{white-space:pre-line;border:1px dashed var(--border);background:var(--admin-soft);border-radius:20px;padding:1rem;color:#134e4a}.member-actions--template{display:flex;flex-wrap:wrap;gap:.65rem}.member-actions--template .cta{text-decoration:none}.member-muted--template{color:#64748b;font-size:.9rem}.member-tabs--template{display:grid;gap:.8rem}.member-mini-grid--template{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}.member-dashboard-menu--template{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem;margin:1rem 0}.member-dashboard-menu--template a{display:grid;gap:.25rem;text-decoration:none;border:1px solid #dbe7e2;border-radius:18px;background:#f8fafc;padding:.8rem;color:#0f172a;font-weight:900}.member-dashboard-menu--template small{font-weight:700;color:#64748b}.member-dashboard-stat--template{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem}.member-dashboard-stat--template div{background:#eff6ff;border:1px solid #bfdbfe;border-radius:18px;padding:.85rem}.member-dashboard-stat--template strong{display:block;font-size:1.45rem;color:#1d4ed8}.member-next-action--template{border:1px solid #bfdbfe;background:#eff6ff;border-radius:22px;padding:1rem;display:flex;justify-content:space-between;gap:1rem;align-items:center}.member-next-action--template.is-warning{border-color:#fed7aa;background:#fff7ed}.member-next-action--template.is-success{border-color:#bbf7d0;background:#f0fdf4}.member-next-action--template b{display:block;color:#0f172a}.member-next-action--template span{display:block;color:#475569;margin-top:.25rem}.member-product-status--template{display:inline-flex;border-radius:999px;padding:.28rem .55rem;background:#ecfdf5;border:1px solid #bbf7d0;color:#047857;font-size:.75rem;font-weight:900}.member-product-status--expired{background:#fef2f2;border-color:#fecaca;color:#991b1b}.member-history-list--template{display:grid;gap:.65rem}.member-history-list--template div{border:1px solid #e2e8f0;border-radius:16px;padding:.75rem;background:#fff}.member-history-list--template small{display:block;color:#64748b}@media(max-width:860px){.member-area-layout--template,.member-access-grid--template,.member-mini-grid--template,.member-dashboard-menu--template,.member-dashboard-stat--template{grid-template-columns:1fr}.member-access-top--template{display:grid}}
        </style>
        <div class="member-area-layout--template">
            <div>
                <?php if ($notice): ?><div class="member-area-alert--template"><?= esc($notice); ?></div><?php endif; ?>
                <?php if ($error): ?><div class="member-area-alert--template member-area-alert--danger"><?= esc($error); ?></div><?php endif; ?>
                <?php if (!empty($GLOBALS['member_magic_url_preview'])): ?><div class="member-area-alert--template"><strong>Link testing:</strong><br><code><?= esc((string)$GLOBALS['member_magic_url_preview']); ?></code></div><?php endif; ?>
                <?php if ($lookupMessage): ?><div class="member-area-alert--template <?= $records ? '' : 'member-area-alert--danger'; ?>"><?= esc($lookupMessage); ?></div><?php endif; ?>

                <?php if (!$records): ?>
                    <article class="member-area-card--template member-tabs--template">
                        <h2>Cari Akses / Login Buyer</h2>
                        <p><?= esc((string)($buyerSettings['login_note'] ?? $settings['login_hint'] ?? 'Gunakan email dan nomor order atau link privat dari invoice.')); ?></p>
                        <div class="member-mini-grid--template">
                            <form method="post" class="member-area-login--template">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="lookup_access">
                                <label>Email pembeli<input type="email" name="email" value="<?= esc($email); ?>" placeholder="email@domain.com"></label>
                                <label>No. order / referensi<input name="ref" value="<?= esc($ref); ?>" placeholder="Contoh: ORD-2026..."></label>
                                <label>Token member langsung, opsional<input name="access" value="<?= esc($access); ?>" placeholder="Biasanya sudah otomatis dari link akses"></label>
                                <button class="cta" type="submit">Buka Akses</button>
                            </form>
                            <div class="member-area-login--template">
                                <form method="post" class="member-area-login--template">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="password_login">
                                    <label>Email akun<input type="email" name="login_email" placeholder="email@domain.com"></label>
                                    <label>Password<input type="password" name="login_password" placeholder="Password opsional buyer"></label>
                                    <button class="cta secondary" type="submit">Login Password</button>
                                </form>
                                <form method="post" class="member-area-login--template">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="request_magic">
                                    <label>Minta magic link / lupa password<input type="email" name="magic_email" placeholder="email pembelian"></label>
                                    <button class="cta secondary" type="submit">Buat Magic Link</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php else: ?>
                    <article class="member-area-card--template" style="margin-bottom:1rem" id="dashboard-member">
                        <div class="member-access-top--template">
                            <div>
                                <h2>Dashboard Member</h2>
                                <p><?= $currentBuyer ? 'Login sebagai ' . esc((string)($currentBuyer['email'] ?? 'buyer')) : 'Akses via token privat.'; ?></p>
                            </div>
                            <div class="member-actions--template">
                                <?php if ($currentBuyer): ?><a class="cta secondary" href="<?= esc(url('member-area?logout=1')); ?>" rel="nofollow">Keluar</a><?php endif; ?>
                            </div>
                        </div>
                        <nav class="member-dashboard-menu--template" aria-label="Menu Member Area">
                            <a href="#akses-produk">Akses Produk<small>Course, file, link, lisensi</small></a>
                            <a href="#riwayat-pembelian">Riwayat Pembelian<small>Order & masa akses</small></a>
                            <a href="#profil-member">Profil & Password<small>Akun buyer</small></a>
                            <a href="#bantuan-member">Bantuan<small>Kontak admin</small></a>
                        </nav>
                        <div class="member-dashboard-stat--template">
                            <div><strong><?= (int)$dashboardSummary['active']; ?></strong><span>Akses aktif</span></div>
                            <div><strong><?= (int)$dashboardSummary['course']; ?></strong><span>Course/module</span></div>
                            <div><strong><?= (int)$dashboardSummary['license']; ?></strong><span>Lisensi</span></div>
                        </div>
                        <div class="member-next-action--template is-<?= esc((string)($dashboardAction['tone'] ?? 'info')); ?>" style="margin-top:1rem">
                            <div><b><?= esc((string)($dashboardAction['title'] ?? 'Member Area')); ?></b><span><?= esc((string)($dashboardAction['message'] ?? '')); ?></span></div>
                            <?php if (!empty($dashboardAction['href'])): ?><a class="cta secondary" href="<?= esc((string)$dashboardAction['href']); ?>"><?= esc((string)($dashboardAction['label'] ?? 'Lanjutkan')); ?></a><?php endif; ?>
                        </div>
                    </article>
                    <article class="member-area-card--template" id="profil-member" style="margin-bottom:1rem">
                        <h2>Profil Pembeli</h2>
                        <p class="member-muted--template">Data akun mengikuti email pembelian. Pembeli bisa merapikan nama/WhatsApp agar admin lebih mudah membantu jika ada kendala akses.</p>
                        <div class="member-access-grid--template">
                            <span><strong>Email</strong><?= esc((string)($currentBuyer['email'] ?? $email ?? '-')); ?></span>
                            <span><strong>Status Login</strong><?= $currentBuyer ? 'Akun aktif' : 'Token privat'; ?></span>
                            <span><strong>Total akses</strong><?= (int)$dashboardSummary['total']; ?> produk</span>
                            <span><strong>Akses berikutnya</strong><?= esc((string)$dashboardSummary['next_expiry']); ?></span>
                        </div>
                        <?php if ($currentBuyer): ?>
                            <form method="post" class="member-area-login--template" style="margin-top:1rem;max-width:520px">
                                <?= csrf_field(); ?><input type="hidden" name="form_action" value="update_profile">
                                <strong>Profil Pembeli</strong>
                                <label>Nama Pembeli<input name="buyer_name" value="<?= esc((string)($currentBuyer['name'] ?? '')); ?>" required placeholder="Nama sesuai pembelian"></label>
                                <label>No. WhatsApp<input name="buyer_phone" value="<?= esc((string)($currentBuyer['phone'] ?? '')); ?>" placeholder="Nomor WA aktif"></label>
                                <button class="cta secondary" type="submit">Simpan Profil</button>
                            </form>
                            <form method="post" class="member-area-login--template" style="margin-top:1rem;max-width:520px">
                                <?= csrf_field(); ?><input type="hidden" name="form_action" value="set_password">
                                <strong><?= (string)($currentBuyer['password_hash'] ?? '') === '' ? 'Buat password opsional' : 'Ganti / reset password member'; ?></strong>
                                <p class="member-muted--template">Kalau lupa password, minta magic link dulu, login, lalu simpan password baru di sini.</p>
                                <label>Password baru<input type="password" name="new_password" minlength="8" placeholder="Minimal 8 karakter"></label>
                                <label>Ulangi password<input type="password" name="new_password_confirm" minlength="8"></label>
                                <button class="cta secondary" type="submit"><?= trim((string)($currentBuyer['password_hash'] ?? '')) === '' ? 'Buat Password' : 'Ganti Password'; ?></button>
                            </form>
                        <?php endif; ?>
                    </article>
                    <article class="member-area-card--template" id="riwayat-pembelian" style="margin-bottom:1rem">
                        <h2>Riwayat Pembelian Produk</h2>
                        <div class="member-history-list--template">
                            <?php foreach ($records as $historyRecord): ?>
                                <div><strong><?= esc((string)($historyRecord['product_title'] ?? 'Produk Digital')); ?></strong> <?php $historyActive = function_exists('member_dashboard_record_active') ? member_dashboard_record_active($historyRecord) : true; ?><span class="member-product-status--template <?= $historyActive ? '' : 'member-product-status--expired'; ?>"><?= $historyActive ? 'Aktif' : 'Tidak aktif'; ?></span><small>Order <?= esc((string)($historyRecord['order_ref'] ?? '-')); ?> · Akses sampai <?= esc(!empty($historyRecord['expires_at']) ? date('d M Y', strtotime((string)$historyRecord['expires_at'])) : 'Tidak ditentukan'); ?></small></div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                    <div id="akses-produk"></div>
                <?php endif; ?>

                <?php foreach ($records as $record): ?>
                    <?php
                    $expired = function_exists('member_access_record_is_expired') && member_access_record_is_expired($record);
                    $license = is_array($record['license'] ?? null) ? $record['license'] : [];
                    $subs = function_exists('subscription_records_by_email') ? array_values(array_filter(subscription_records_by_email((string)($record['customer_email'] ?? '')), static fn($s) => is_array($s) && (string)($s['order_id'] ?? '') === (string)($record['order_id'] ?? ''))) : [];
                    $subscription = $subs[0] ?? null;
                    ?>
                    <article class="member-area-card--template member-access-record--template">
                        <div class="member-access-top--template">
                            <div>
                                <span class="member-access-badge--template"><?= esc((string)($record['product_type'] ?? 'digital')); ?></span>
                                <?php if (!empty($license['enabled'])): ?><span class="member-access-badge--template member-access-badge--license">Lisensi</span><?php endif; ?>
                                <?php if ($subscription): ?><span class="member-access-badge--template member-access-badge--subscription">Membership</span><?php endif; ?>
                                <h2><?= esc((string)($record['product_title'] ?? 'Produk Digital')); ?></h2>
                                <p><?= esc((string)($record['order_ref'] ?? '-')); ?> · <?= esc((string)($record['customer_name'] ?? '-')); ?></p>
                            </div>
                            <div class="member-actions--template">
                                <?php if (!empty($record['digital_delivery_url'])): ?><a class="cta secondary" href="<?= esc((string)$record['digital_delivery_url']); ?>" rel="nofollow">Akses Download</a><?php endif; ?>
                                <?php if (!empty($record['digital_access_url'])): ?><a class="cta" href="<?= esc((string)$record['digital_access_url']); ?>" target="_blank" rel="nofollow noopener">Buka Link Produk</a><?php endif; ?>
                            </div>
                        </div>

                        <?php if ($expired): ?><div class="member-area-alert--template member-area-alert--danger">Masa akses sudah kedaluwarsa. Hubungi admin jika perlu perpanjangan.</div>
                        <?php elseif ((string)($record['status'] ?? 'active') !== 'active'): ?><div class="member-area-alert--template member-area-alert--danger">Akses member sedang tidak aktif.</div>
                        <?php else: ?><div class="member-area-alert--template">Akses aktif. Simpan link ini dan jangan dibagikan ke pihak lain.</div><?php endif; ?>

                        <div class="member-access-grid--template">
                            <span><strong>No. Order</strong><?= esc((string)($record['order_ref'] ?? '-')); ?></span>
                            <span><strong>Aktif sampai</strong><?= esc(!empty($record['expires_at']) ? date('d M Y H:i', strtotime((string)$record['expires_at'])) : 'Tidak ditentukan'); ?></span>
                            <span><strong>Rilis akses</strong><?= esc(!empty($record['issued_at']) ? date('d M Y H:i', strtotime((string)$record['issued_at'])) : '-'); ?></span>
                            <span><strong>Terakhir dibuka</strong><?= esc(!empty($record['last_opened_at']) ? date('d M Y H:i', strtotime((string)$record['last_opened_at'])) : '-'); ?></span>
                        </div>

                        <?php if (!empty($record['course_modules']) && is_array($record['course_modules'])): ?>
                            <div><h3>Materi Course / Modul</h3><div class="member-course-list--template">
                                <?php foreach ($record['course_modules'] as $idx => $module): if (!is_array($module)) { continue; } ?>
                                    <div class="member-course-module--template"><b><?= esc(((int)$idx + 1) . '. ' . (string)($module['title'] ?? 'Modul')); ?></b><?php if (!empty($module['duration'])): ?><small><?= esc((string)$module['duration']); ?></small><?php endif; ?><?php if (!empty($module['note'])): ?><span><?= esc((string)$module['note']); ?></span><?php endif; ?><?php if (!$expired && !empty($module['url'])): ?><a href="<?= esc((string)$module['url']); ?>" target="_blank" rel="nofollow noopener">Buka materi</a><?php endif; ?></div>
                                <?php endforeach; ?>
                            </div></div>
                        <?php endif; ?>

                        <?php if (!empty($license['enabled'])): ?>
                            <div class="member-license-box--template">
                                <h3>License Key</h3><span class="member-license-key--template"><?= esc((string)($license['key'] ?? '-')); ?></span>
                                <div class="member-access-grid--template">
                                    <span><strong>Tipe</strong><?= esc((string)($license['type'] ?? '-')); ?></span>
                                    <span><strong>Seat/User</strong><?= esc((string)($license['seats'] ?? '1')); ?></span>
                                    <span><strong>Batas Aktivasi</strong><?= esc((string)($license['activation_limit'] ?? '1')); ?></span>
                                    <span><strong>Berlaku sampai</strong><?= esc(!empty($license['expires_at']) ? date('d M Y H:i', strtotime((string)$license['expires_at'])) : 'Lifetime/fleksibel'); ?></span>
                                </div>
                                <?php if (!empty($license['note'])): ?><p><?= nl2br(esc((string)$license['note'])); ?></p><?php endif; ?>
                                <p class="member-muted--template">Untuk software/template, aktivasi domain mengikuti aturan License Manager toko.</p>
                            </div>
                        <?php endif; ?>

                        <?php if ($subscription): ?>
                            <?php $subStatus = function_exists('subscription_status') ? subscription_status($subscription) : (string)($subscription['status'] ?? 'active'); ?>
                            <div class="member-subscription-box--template">
                                <h3>Status Membership / Subscription</h3>
                                <div class="member-access-grid--template">
                                    <span><strong>Status</strong><?= esc($subStatus); ?></span>
                                    <span><strong>Siklus</strong><?= esc((string)($subscription['cycle'] ?? '-')); ?></span>
                                    <span><strong>Berakhir</strong><?= esc(!empty($subscription['expires_at']) ? date('d M Y', strtotime((string)$subscription['expires_at'])) : 'Lifetime'); ?></span>
                                    <span><strong>Grace Period</strong><?= esc(!empty($subscription['grace_until']) ? date('d M Y', strtotime((string)$subscription['grace_until'])) : '-'); ?></span>
                                </div>
                                <div class="member-actions--template"><a class="cta secondary" href="<?= esc(function_exists('subscription_renewal_url') ? subscription_renewal_url($subscription) : url('checkout')); ?>" rel="nofollow">Perpanjang Akses</a></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($record['instructions'])): ?><div><h3>Instruksi Akses</h3><div class="member-instruction--template"><?= nl2br(esc((string)$record['instructions'])); ?></div></div><?php endif; ?>
                        <div class="member-actions--template">
                            <?php if (!empty($record['digital_file_url'])): ?><a class="cta secondary" href="<?= esc((string)$record['digital_file_url']); ?>" target="_blank" rel="nofollow noopener">Buka File Produk</a><?php endif; ?>
                            <a class="cta secondary" href="<?= esc(wa_link('Halo Admin, saya butuh bantuan akses member untuk order ' . (string)($record['order_ref'] ?? ''))); ?>" target="_blank" rel="nofollow noopener">Butuh Bantuan</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <aside class="member-area-card--template" id="bantuan-member">
                <h2>Panduan Singkat & Bantuan</h2>
                <p>Area ini bisa dipakai untuk pembeli e-course, membership, template, lisensi software, dan produk digital personal brand.</p>
                <ul class="member-muted--template"><li>Checkout tetap ringan tanpa password wajib.</li><li>Akun buyer dibuat otomatis setelah akses aktif.</li><li>Login bisa memakai magic link atau password opsional.</li><li>Lisensi domain dan subscription mengikuti aturan admin.</li></ul><div class="member-actions--template" style="margin-top:1rem"><a class="cta secondary" href="<?= esc(url('order-status')); ?>">Cek Status Order</a><a class="cta secondary" href="<?= esc($supportUrl); ?>" target="_blank" rel="nofollow noopener">Hubungi Admin</a></div>
            </aside>
        </div>
    </div>
</section>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
