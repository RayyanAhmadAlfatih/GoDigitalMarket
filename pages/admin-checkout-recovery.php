<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();
$GLOBALS['admin_page'] = true;
$message = (string)($_GET['message'] ?? '');
$error = '';
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';

function admin_checkout_recovery_logged_in(): bool
{
    return function_exists('admin_panel_logged_in') ? admin_panel_logged_in() : !empty($_SESSION['admin_articles_logged_in']);
}

function admin_checkout_recovery_filter(string $key, int $max = 80): string
{
    return function_exists('checkout_recovery_clean') ? checkout_recovery_clean((string)($_GET[$key] ?? ''), $max) : trim((string)($_GET[$key] ?? ''));
}

function admin_checkout_recovery_range(): string
{
    $range = (string)($_GET['range'] ?? '30');
    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'all'];
    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_checkout_recovery_days(): int
{
    $range = admin_checkout_recovery_range();
    if ($range === 'all') {
        return 0;
    }
    return max(1, min(3650, (int)$range));
}

function admin_checkout_recovery_filters(): array
{
    $filters = array_filter([
        'stage' => admin_checkout_recovery_filter('stage', 80),
        'priority' => admin_checkout_recovery_filter('priority', 80),
        'followup' => admin_checkout_recovery_filter('followup', 80),
        'search' => admin_checkout_recovery_filter('search', 140),
    ], static fn($value): bool => $value !== '' && $value !== null);
    if (admin_checkout_recovery_range() === 'all') {
        $filters['_all_time'] = true;
    }
    if (!empty($_GET['include_grace'])) {
        $filters['include_grace'] = true;
    }
    return $filters;
}

function admin_checkout_recovery_url(array $extra = []): string
{
    $query = array_merge([
        'range' => admin_checkout_recovery_range(),
        'stage' => admin_checkout_recovery_filter('stage', 80),
        'priority' => admin_checkout_recovery_filter('priority', 80),
        'followup' => admin_checkout_recovery_filter('followup', 80),
        'search' => admin_checkout_recovery_filter('search', 140),
    ], $extra);
    if (!empty($_GET['include_grace']) && !array_key_exists('include_grace', $query)) {
        $query['include_grace'] = '1';
    }
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/checkout-recovery' . ($query ? '?' . http_build_query($query) : ''));
}

function admin_checkout_recovery_age_label(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' menit lalu';
    }
    if ($minutes < 1440) {
        return floor($minutes / 60) . ' jam lalu';
    }
    return floor($minutes / 1440) . ' hari lalu';
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/checkout-recovery');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_checkout_recovery_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            redirect_302('admin/checkout-recovery');
        }
        $error = 'Password admin salah.';
    } else {
        $action = (string)($_POST['form_action'] ?? '');
        if ($action === 'save_settings') {
            $templates = [];
            foreach (array_keys(checkout_recovery_default_templates()) as $key) {
                $templates[$key] = (string)($_POST['template_' . $key] ?? '');
            }
            $settings = [
                'enabled' => !empty($_POST['enabled']),
                'recovery_after_minutes' => (int)($_POST['recovery_after_minutes'] ?? 30),
                'hot_window_hours' => (int)($_POST['hot_window_hours'] ?? 24),
                'stale_after_days' => (int)($_POST['stale_after_days'] ?? 7),
                'rate_limit_per_day' => (int)($_POST['rate_limit_per_day'] ?? 3),
                'auto_schedule_next' => !empty($_POST['auto_schedule_next']),
                'default_next_followup_hours' => (int)($_POST['default_next_followup_hours'] ?? 24),
                'whatsapp_intro' => (string)($_POST['whatsapp_intro'] ?? ''),
                'show_anonymous_intent' => !empty($_POST['show_anonymous_intent']),
                'anonymous_days' => (int)($_POST['anonymous_days'] ?? 7),
                'templates' => $templates,
            ];
            if (checkout_recovery_write_settings($settings)) {
                redirect_302('admin/checkout-recovery?message=' . rawurlencode('Pengaturan follow-up checkout berhasil disimpan.'));
            }
            $error = 'Pengaturan belum bisa disimpan. Pastikan folder storage writable.';
        } elseif ($action === 'log_followup') {
            $stored = checkout_recovery_store_followup([
                'order_id' => (string)($_POST['order_id'] ?? ''),
                'template_key' => (string)($_POST['template_key'] ?? ''),
                'priority' => (string)($_POST['priority'] ?? ''),
                'outcome' => (string)($_POST['outcome'] ?? 'Chat Terkirim'),
                'note' => (string)($_POST['note'] ?? ''),
                'next_followup_date' => (string)($_POST['next_followup_date'] ?? ''),
                'next_followup_time' => (string)($_POST['next_followup_time'] ?? ''),
            ]);
            if ($stored) {
                redirect_302(admin_checkout_recovery_url(['message' => 'Catatan follow-up checkout berhasil disimpan.']));
            }
            $error = 'Catatan follow-up belum bisa disimpan. Pastikan order valid dan catatan/hasil follow-up terisi.';
        }
    }
}

$loggedIn = admin_checkout_recovery_logged_in();
$settings = $loggedIn ? checkout_recovery_read_settings() : checkout_recovery_default_settings();
$filters = admin_checkout_recovery_filters();
$candidates = $loggedIn ? checkout_recovery_candidates(admin_checkout_recovery_days(), $filters, 5000) : [];
$summary = $loggedIn ? checkout_recovery_summary($candidates) : ['total' => 0, 'hot' => 0, 'today' => 0, 'overdue' => 0, 'untouched' => 0, 'estimated_value' => 0, 'by_stage' => [], 'by_priority' => []];
$anonymous = $loggedIn && !empty($settings['show_anonymous_intent']) ? checkout_recovery_anonymous_intents((int)($settings['anonymous_days'] ?? 7), 8) : [];

if ($loggedIn && ($_GET['action'] ?? '') === 'export') {
    checkout_recovery_export_csv($candidates);
}

$stageOptions = [
    '' => 'Semua stage',
    'gateway_pending' => 'Payment Gateway Pending',
    'payment_pending' => 'Menunggu Pembayaran',
    'shipping_question' => 'Perlu Bantu Ongkir',
    'preorder_pending' => 'Pre-order Perlu Konfirmasi',
    'consultation_needed' => 'Perlu Konsultasi',
    'order_unpaid' => 'Belum Closing',
    'grace_period' => 'Baru Masuk',
];
$priorityOptions = ['' => 'Semua prioritas', 'Sangat Panas' => 'Sangat Panas', 'Tinggi' => 'Tinggi', 'Normal' => 'Normal', 'Rendah' => 'Rendah'];
$followupOptions = ['' => 'Semua follow-up', 'new' => 'Belum pernah follow-up', 'today' => 'Jatuh tempo hari ini', 'overdue' => 'Terlambat', 'scheduled' => 'Terjadwal', 'touched' => 'Sudah disentuh'];
$templateOptions = checkout_recovery_template_options();
$outcomeOptions = function_exists('crm_outcomes') ? array_keys(crm_outcomes()) : ['Chat Terkirim', 'Menunggu Respon', 'Reminder Pembayaran', 'Deal', 'Tidak Jadi'];

set_seo([
    'title' => 'Abandoned Checkout & Follow-up Center - Admin',
    'description' => 'Pusat recovery checkout untuk follow-up order belum bayar, reminder WhatsApp, dan action list calon pembeli hot intent.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="main-content" class="admin-shell admin-checkout-recovery-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Checkout Recovery</div>
                <h1>Abandoned Checkout & Follow-up Center</h1>
                <p>Kejar calon pembeli hot intent: sudah checkout/order tapi belum bayar, belum konfirmasi ongkir, atau masih perlu dibantu admin sampai closing.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/orders')); ?>">Buka Order</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/followups')); ?>">Riwayat CRM</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <style>
                .admin-checkout-recovery-shell .acr-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:18px;align-items:start}.admin-checkout-recovery-shell .acr-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-checkout-recovery-shell .acr-card h2,.admin-checkout-recovery-shell .acr-card h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-checkout-recovery-shell .acr-card p{color:#64748b;margin:.25rem 0 .85rem}.admin-checkout-recovery-shell .acr-filter{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;align-items:end}.admin-checkout-recovery-shell label{display:grid;gap:7px;color:#334155;font-weight:850;font-size:.86rem}.admin-checkout-recovery-shell input,.admin-checkout-recovery-shell select,.admin-checkout-recovery-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;background:#fff;color:#0f172a}.admin-checkout-recovery-shell select{appearance:auto;min-height:44px;line-height:1.25}.admin-checkout-recovery-shell select option{background:#fff;color:#0f172a;padding:8px 10px}.admin-checkout-recovery-shell .acr-filter{overflow:visible;position:relative;z-index:5}.admin-checkout-recovery-shell textarea{min-height:92px}.admin-checkout-recovery-shell .acr-check{display:flex!important;gap:8px;align-items:center}.admin-checkout-recovery-shell .acr-check input{width:auto}.admin-checkout-recovery-shell .acr-stat-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}.admin-checkout-recovery-shell .acr-stat{border:1px solid #e2e8f0;border-radius:20px;background:#fff;padding:14px}.admin-checkout-recovery-shell .acr-stat span{display:block;color:#64748b;font-size:.82rem;font-weight:850}.admin-checkout-recovery-shell .acr-stat strong{display:block;color:#0f172a;font-size:1.45rem;margin-top:4px}.admin-checkout-recovery-shell .acr-candidate{border:1px solid #e2e8f0;border-radius:24px;background:linear-gradient(135deg,#fff,#f8fafc);padding:16px;display:grid;gap:12px}.admin-checkout-recovery-shell .acr-candidate__head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.admin-checkout-recovery-shell .acr-candidate__head strong{display:block;color:#0f172a;font-size:1.05rem}.admin-checkout-recovery-shell .acr-candidate__head span,.admin-checkout-recovery-shell .acr-muted{color:#64748b}.admin-checkout-recovery-shell .acr-badges{display:flex;flex-wrap:wrap;gap:7px}.admin-checkout-recovery-shell .acr-badge{display:inline-flex;border-radius:999px;padding:5px 10px;border:1px solid #dbeafe;background:#eff6ff;color:#1d4ed8;font-weight:900;font-size:.76rem}.admin-checkout-recovery-shell .acr-badge--hot{background:#fff7ed;border-color:#fed7aa;color:#c2410c}.admin-checkout-recovery-shell .acr-badge--danger{background:#fef2f2;border-color:#fecaca;color:#b91c1c}.admin-checkout-recovery-shell .acr-badge--ok{background:#ecfdf5;border-color:#bbf7d0;color:#047857}.admin-checkout-recovery-shell .acr-info{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.admin-checkout-recovery-shell .acr-info div{border:1px solid #e2e8f0;background:#fff;border-radius:16px;padding:10px}.admin-checkout-recovery-shell .acr-info span{display:block;color:#64748b;font-size:.76rem;font-weight:850}.admin-checkout-recovery-shell .acr-info strong{display:block;color:#0f172a;margin-top:3px}.admin-checkout-recovery-shell .acr-actions{display:flex;flex-wrap:wrap;gap:9px}.admin-checkout-recovery-shell .acr-followup-form{border-top:1px solid #e2e8f0;padding-top:12px;display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:10px;align-items:end}.admin-checkout-recovery-shell .acr-followup-form textarea{grid-column:1/-1}.admin-checkout-recovery-shell .acr-list{display:grid;gap:14px}.admin-checkout-recovery-shell .acr-mini-list{display:grid;gap:9px}.admin-checkout-recovery-shell .acr-mini-item{border:1px solid #e2e8f0;border-radius:16px;background:#f8fafc;padding:11px}.admin-checkout-recovery-shell .acr-mini-item strong{display:block;color:#0f172a}.admin-checkout-recovery-shell .acr-settings-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.admin-checkout-recovery-shell .acr-template-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}@media(max-width:1180px){.admin-checkout-recovery-shell .acr-stat-grid{grid-template-columns:repeat(3,1fr)}.admin-checkout-recovery-shell .acr-filter,.admin-checkout-recovery-shell .acr-info,.admin-checkout-recovery-shell .acr-followup-form,.admin-checkout-recovery-shell .acr-settings-grid,.admin-checkout-recovery-shell .acr-template-grid{grid-template-columns:1fr 1fr}.admin-checkout-recovery-shell .acr-grid{grid-template-columns:1fr}}@media(max-width:720px){.admin-checkout-recovery-shell .acr-stat-grid,.admin-checkout-recovery-shell .acr-filter,.admin-checkout-recovery-shell .acr-info,.admin-checkout-recovery-shell .acr-followup-form,.admin-checkout-recovery-shell .acr-settings-grid,.admin-checkout-recovery-shell .acr-template-grid{grid-template-columns:1fr}.admin-checkout-recovery-shell .acr-candidate__head{display:grid}}
            </style>

            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka Checkout Recovery Center.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php admin_panel_render_nav('admin/checkout-recovery'); ?>

                <div class="acr-stat-grid">
                    <div class="acr-stat"><span>Perlu Follow-up</span><strong><?= (int)$summary['total']; ?></strong><small>Order belum closing.</small></div>
                    <div class="acr-stat"><span>Hot Lead</span><strong><?= (int)$summary['hot']; ?></strong><small>Prioritas tinggi.</small></div>
                    <div class="acr-stat"><span>Belum Disentuh</span><strong><?= (int)$summary['untouched']; ?></strong><small>Belum ada CRM.</small></div>
                    <div class="acr-stat"><span>Jatuh Tempo</span><strong><?= (int)$summary['today']; ?></strong><small>Reminder hari ini.</small></div>
                    <div class="acr-stat"><span>Terlambat</span><strong><?= (int)$summary['overdue']; ?></strong><small>Perlu dikejar.</small></div>
                    <div class="acr-stat"><span>Potensi Omzet</span><strong><?= function_exists('rupiah') ? esc(rupiah((int)$summary['estimated_value'])) : (int)$summary['estimated_value']; ?></strong><small>Estimasi order belum bayar.</small></div>
                </div>

                <form method="get" class="acr-card acr-filter">
                    <label>Rentang
                        <select name="range">
                            <?php foreach (['7'=>'7 hari','14'=>'14 hari','30'=>'30 hari','60'=>'60 hari','90'=>'90 hari','180'=>'180 hari','365'=>'1 tahun','all'=>'Semua waktu'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= admin_checkout_recovery_range() === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Stage
                        <select name="stage">
                            <?php foreach ($stageOptions as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= admin_checkout_recovery_filter('stage') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Prioritas
                        <select name="priority">
                            <?php foreach ($priorityOptions as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= admin_checkout_recovery_filter('priority') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Follow-up
                        <select name="followup">
                            <?php foreach ($followupOptions as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= admin_checkout_recovery_filter('followup') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Cari
                        <input name="search" value="<?= esc(admin_checkout_recovery_filter('search', 140)); ?>" placeholder="Nama, nomor, produk, kota...">
                    </label>
                    <label class="acr-check"><input type="checkbox" name="include_grace" value="1" <?= !empty($_GET['include_grace']) ? 'checked' : ''; ?>> Tampilkan order baru dalam grace period</label>
                    <div class="acr-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                        <a class="admin-btn" href="<?= esc(url('admin/checkout-recovery')); ?>">Reset</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_checkout_recovery_url(['action' => 'export'])); ?>">Export CSV</a>
                    </div>
                </form>

                <div class="acr-grid">
                    <section class="acr-card">
                        <h2>Action List Calon Pembeli</h2>
                        <p>Urutan otomatis berdasarkan skor intent, status pembayaran, umur order, nominal, dan apakah sudah pernah di-follow-up.</p>
                        <div class="acr-list">
                            <?php foreach ($candidates as $candidate): ?>
                                <?php
                                    $order = (array)($candidate['order'] ?? []);
                                    $templateKey = checkout_recovery_recommended_template($candidate);
                                    $waUrl = checkout_recovery_whatsapp_url($candidate, $templateKey);
                                    $followState = (string)($candidate['followup']['state'] ?? 'new');
                                    $badgeClass = in_array((string)$candidate['priority'], ['Sangat Panas', 'Tinggi'], true) ? 'acr-badge--hot' : '';
                                    $followClass = $followState === 'overdue' ? 'acr-badge--danger' : ($followState === 'today' ? 'acr-badge--hot' : ($followState === 'scheduled' ? 'acr-badge--ok' : ''));
                                ?>
                                <article class="acr-candidate">
                                    <div class="acr-candidate__head">
                                        <div>
                                            <strong><?= esc((string)$candidate['name'] ?: 'Tanpa nama'); ?> · <?= esc((string)$candidate['ref']); ?></strong>
                                            <span><?= esc((string)$candidate['product']); ?> · <?= esc(admin_checkout_recovery_age_label((int)$candidate['age_minutes'])); ?></span>
                                        </div>
                                        <div class="acr-badges">
                                            <span class="acr-badge <?= esc($badgeClass); ?>">Skor <?= (int)$candidate['score']; ?> · <?= esc((string)$candidate['priority']); ?></span>
                                            <span class="acr-badge"><?= esc((string)$candidate['stage_label']); ?></span>
                                            <span class="acr-badge <?= esc($followClass); ?>"><?= esc((string)$candidate['followup']['label']); ?></span>
                                        </div>
                                    </div>
                                    <div class="acr-info">
                                        <div><span>Kontak</span><strong><?= esc((string)$candidate['phone'] ?: '-'); ?></strong></div>
                                        <div><span>Total</span><strong><?= function_exists('rupiah') ? esc(rupiah((int)$candidate['total'])) : (int)$candidate['total']; ?></strong></div>
                                        <div><span>Pembayaran</span><strong><?= esc((string)($order['payment_status'] ?? '-')); ?></strong></div>
                                        <div><span>Tujuan/Ongkir</span><strong><?= esc((string)$candidate['destination']); ?> · <?= esc((string)$candidate['shipping']); ?></strong></div>
                                    </div>
                                    <div class="acr-actions">
                                        <?php if ($waUrl !== ''): ?><a class="admin-btn admin-btn--primary" href="<?= esc($waUrl); ?>" target="_blank" rel="nofollow noopener">Chat WA Recommended</a><?php endif; ?>
                                        <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/orders?search=' . rawurlencode((string)$candidate['ref']))); ?>">Buka Order</a>
                                        <?php if (function_exists('order_public_invoice_url')): ?><a class="admin-btn" href="<?= esc(order_public_invoice_url($order)); ?>" target="_blank" rel="nofollow noopener">Invoice</a><?php endif; ?>
                                        <?php if (function_exists('order_status_url')): ?><a class="admin-btn" href="<?= esc(order_status_url($order)); ?>" target="_blank" rel="nofollow noopener">Status Publik</a><?php endif; ?>
                                    </div>
                                    <details>
                                        <summary>Preview pesan & catat follow-up</summary>
                                        <pre style="white-space:pre-wrap;background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:12px;color:#0f172a"><?= esc(checkout_recovery_render_message($candidate, $templateKey)); ?></pre>
                                        <form method="post" class="acr-followup-form">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="form_action" value="log_followup">
                                            <input type="hidden" name="order_id" value="<?= esc((string)$candidate['id']); ?>">
                                            <label>Template
                                                <select name="template_key">
                                                    <?php foreach ($templateOptions as $key => $label): ?>
                                                        <option value="<?= esc($key); ?>" <?= $templateKey === $key ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>Hasil
                                                <select name="outcome">
                                                    <?php foreach ($outcomeOptions as $outcome): ?>
                                                        <option value="<?= esc($outcome); ?>" <?= $outcome === 'Chat Terkirim' ? 'selected' : ''; ?>><?= esc($outcome); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>Prioritas
                                                <select name="priority">
                                                    <?php foreach (['Sangat Panas', 'Tinggi', 'Normal', 'Rendah'] as $priority): ?>
                                                        <option value="<?= esc($priority); ?>" <?= $priority === (string)$candidate['priority'] ? 'selected' : ''; ?>><?= esc($priority); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label>Jadwal berikutnya
                                                <input type="date" name="next_followup_date" value="<?= esc(date('Y-m-d', time() + ((int)($settings['default_next_followup_hours'] ?? 24) * 3600))); ?>">
                                            </label>
                                            <label>Jam
                                                <input type="time" name="next_followup_time" value="<?= esc(date('H:i', time() + ((int)($settings['default_next_followup_hours'] ?? 24) * 3600))); ?>">
                                            </label>
                                            <textarea name="note" placeholder="Catatan singkat setelah chat/call. Kosongkan untuk menyimpan template pesan sebagai catatan."></textarea>
                                            <button class="admin-btn admin-btn--primary" type="submit">Simpan Catatan Follow-up</button>
                                        </form>
                                    </details>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$candidates): ?>
                                <div class="admin-card admin-empty-card">
                                    <h3>Belum ada checkout yang perlu recovery.</h3>
                                    <p>Order yang sudah lunas/selesai tidak ditampilkan. Order baru juga bisa disembunyikan sampai lewat grace period.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <aside class="admin-stack">
                        <section class="acr-card">
                            <h2>Breakdown Recovery</h2>
                            <div class="acr-mini-list">
                                <?php foreach ((array)$summary['by_stage'] as $stage => $count): ?>
                                    <div class="acr-mini-item"><strong><?= esc((string)$stage); ?></strong><span class="acr-muted"><?= (int)$count; ?> order</span></div>
                                <?php endforeach; ?>
                                <?php if (empty($summary['by_stage'])): ?><p class="admin-muted">Belum ada data.</p><?php endif; ?>
                            </div>
                        </section>

                        <section class="acr-card">
                            <h2>Anonymous Checkout Intent</h2>
                            <p>Pengunjung yang melihat checkout tapi belum bisa di-follow-up langsung karena belum memberi kontak. Ini dipakai untuk insight halaman/produk.</p>
                            <div class="acr-mini-list">
                                <?php foreach ($anonymous as $item): ?>
                                    <div class="acr-mini-item"><strong><?= esc((string)$item['label']); ?></strong><span class="acr-muted"><?= (int)$item['views']; ?> view checkout · terakhir <?= !empty($item['last_ts']) ? esc(date('d M H:i', (int)$item['last_ts'])) : '-'; ?></span></div>
                                <?php endforeach; ?>
                                <?php if (!$anonymous): ?><p class="admin-muted">Belum ada sinyal checkout anonim dalam rentang setting.</p><?php endif; ?>
                            </div>
                        </section>
                    </aside>
                </div>

                <form method="post" class="acr-card" data-admin-page-tab-scope>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="form_action" value="save_settings">
                    <div class="admin-page-subtabs admin-page-subtabs--3" role="tablist" aria-label="Pengaturan checkout recovery">
                        <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="acr-basic"><span>1. Aturan</span><small>Grace & scoring</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="acr-reminder"><span>2. Reminder</span><small>Jadwal follow-up</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="acr-template"><span>3. Template</span><small>Script WA</small></button>
                    </div>
                    <div class="admin-page-tab-panel is-active" data-admin-page-tab-panel="acr-basic">
                        <h2>Aturan Recovery</h2>
                        <p>Grace period mencegah admin terlalu cepat follow-up saat calon pembeli baru saja submit order.</p>
                        <div class="acr-settings-grid">
                            <label class="acr-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan Checkout Recovery Center</label>
                            <label>Mulai recovery setelah, menit<input type="number" name="recovery_after_minutes" min="5" max="10080" value="<?= esc((string)($settings['recovery_after_minutes'] ?? 30)); ?>"></label>
                            <label>Hot window, jam<input type="number" name="hot_window_hours" min="1" max="720" value="<?= esc((string)($settings['hot_window_hours'] ?? 24)); ?>"></label>
                            <label>Stale setelah, hari<input type="number" name="stale_after_days" min="1" max="365" value="<?= esc((string)($settings['stale_after_days'] ?? 7)); ?>"></label>
                            <label>Limit follow-up/hari/customer<input type="number" name="rate_limit_per_day" min="1" max="20" value="<?= esc((string)($settings['rate_limit_per_day'] ?? 3)); ?>"></label>
                            <label class="acr-check"><input type="checkbox" name="show_anonymous_intent" value="1" <?= !empty($settings['show_anonymous_intent']) ? 'checked' : ''; ?>> Tampilkan anonymous checkout intent</label>
                            <label>Rentang anonymous intent, hari<input type="number" name="anonymous_days" min="1" max="90" value="<?= esc((string)($settings['anonymous_days'] ?? 7)); ?>"></label>
                            <label>Intro WhatsApp<input name="whatsapp_intro" value="<?= esc((string)($settings['whatsapp_intro'] ?? '')); ?>"></label>
                        </div>
                    </div>
                    <div class="admin-page-tab-panel" data-admin-page-tab-panel="acr-reminder" hidden>
                        <h2>Jadwal Follow-up</h2>
                        <div class="acr-settings-grid">
                            <label class="acr-check"><input type="checkbox" name="auto_schedule_next" value="1" <?= !empty($settings['auto_schedule_next']) ? 'checked' : ''; ?>> Otomatis isi jadwal follow-up berikutnya</label>
                            <label>Default follow-up lagi setelah, jam<input type="number" name="default_next_followup_hours" min="1" max="720" value="<?= esc((string)($settings['default_next_followup_hours'] ?? 24)); ?>"></label>
                        </div>
                        <p class="admin-muted">Saran: H+0 untuk hot order, H+1 untuk reminder pembayaran, H+2/H+3 untuk last call, lalu arsipkan jika tidak respon.</p>
                    </div>
                    <div class="admin-page-tab-panel" data-admin-page-tab-panel="acr-template" hidden>
                        <h2>Template Pesan WhatsApp</h2>
                        <p>Placeholder: {name}, {order_ref}, {product}, {total}, {payment_status}, {invoice_url}, {status_url}, {destination}, {shipping}, {preorder_note}, {site_name}</p>
                        <div class="acr-template-grid">
                            <?php foreach ($templateOptions as $key => $label): ?>
                                <label><?= esc($label); ?>
                                    <textarea name="template_<?= esc($key); ?>"><?= esc((string)($settings['templates'][$key] ?? checkout_recovery_default_templates()[$key] ?? '')); ?></textarea>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Pengaturan Recovery</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
