<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$GLOBALS['admin_page'] = true;
$GLOBALS['admin_force_sidebar_layout'] = true;
$message = trim((string)($_GET['message'] ?? ''));
$error = '';
$resetUrl = '';
$currentUser = function_exists('admin_auth_current_user') ? admin_auth_current_user() : [];
$currentRole = (string)($currentUser['role'] ?? 'owner');
$roles = function_exists('admin_users_roles') ? admin_users_roles() : [];

if ($currentRole !== 'owner') {
    http_response_code(403);
    set_seo(['title' => 'Akses Role Admin - ' . SITE_NAME, 'robots' => 'noindex, nofollow']);
    require_once ROOT_PATH . '/components/layout/head.php';
    require_once ROOT_PATH . '/components/layout/header.php';
    ?>
    <main id="main-content" class="admin-shell">
        <section class="admin-hero"><div class="container admin-hero__inner"><div><div class="admin-eyebrow">Role Dashboard</div><h1>Akses Khusus Owner</h1><p>Manajemen user dan role dashboard hanya tersedia untuk Owner/Super Admin.</p></div></div></section>
        <section class="admin-section"><div class="container"><a class="admin-btn admin-btn--primary" href="<?= esc(url(function_exists('admin_users_default_path_for_role') ? admin_users_default_path_for_role($currentRole) : 'admin/brand')); ?>">Kembali ke Dashboard</a></div></section>
    </main>
    <?php
    require_once ROOT_PATH . '/components/layout/footer.php';
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf()) {
        $error = 'Sesi keamanan tidak valid. Refresh halaman lalu coba lagi.';
    } else {
        $action = (string)($_POST['form_action'] ?? '');
        if ($action === 'save_user' && function_exists('admin_users_save_record')) {
            $id = trim((string)($_POST['id'] ?? ''));
            $result = admin_users_save_record($_POST, $id !== '' ? $id : null);
            if (!empty($result['ok'])) {
                $message = (string)$result['message'];
            } else {
                $error = (string)($result['message'] ?? 'Gagal menyimpan user.');
            }
        } elseif ($action === 'delete_user' && function_exists('admin_users_delete_record')) {
            $id = trim((string)($_POST['id'] ?? ''));
            if ($id === (string)($currentUser['id'] ?? '')) {
                $error = 'User yang sedang login tidak bisa menghapus akunnya sendiri.';
            } else {
                $result = admin_users_delete_record($id);
                !empty($result['ok']) ? $message = (string)$result['message'] : $error = (string)($result['message'] ?? 'Gagal menghapus user.');
            }
        } elseif ($action === 'reset_user' && function_exists('admin_users_generate_reset')) {
            $result = admin_users_generate_reset(trim((string)($_POST['id'] ?? '')));
            if (!empty($result['ok'])) {
                $message = (string)$result['message'];
                $resetUrl = (string)($result['reset_url'] ?? '');
            } else {
                $error = (string)($result['message'] ?? 'Gagal membuat reset password.');
            }
        }
    }
}

$records = function_exists('admin_users_read_all') ? admin_users_read_all() : [];
$summary = function_exists('admin_users_summary') ? admin_users_summary() : ['total' => count($records), 'active' => count($records), 'inactive' => 0, 'roles' => []];
$editId = trim((string)($_GET['edit'] ?? ''));
$editRecord = $editId !== '' && isset($records[$editId]) ? $records[$editId] : null;

set_seo([
    'title' => 'Manajemen User & Role - ' . SITE_NAME,
    'description' => 'Kelola user dashboard admin, role, dan mode kerja U-Growth.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<main id="main-content" class="admin-shell admin-users-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">User & Role Management</div>
                <h1>Manajemen User, Role & Workflow Mode</h1>
                <p>Atur akun tim sesuai pekerjaan harian. Halaman ini memakai sidebar dashboard standar dan hanya tersedia untuk Owner/Super Admin.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/security')); ?>">Keamanan</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/activity-log')); ?>">Log Sistem</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <style>
                .admin-users-shell .role-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.admin-users-shell .role-card{border:1px solid #e2e8f0;background:#fff;border-radius:22px;padding:16px;box-shadow:0 12px 35px rgba(15,23,42,.04)}.admin-users-shell .role-card h3{margin:.25rem 0}.admin-users-shell .user-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(340px,.55fr);gap:18px;align-items:start}.admin-users-shell .user-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-users-shell .field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-users-shell label{display:grid;gap:7px;color:#334155;font-weight:850;font-size:.86rem}.admin-users-shell input,.admin-users-shell select,.admin-users-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;background:#fff;color:#0f172a}.admin-users-shell select option{background:#fff;color:#0f172a}.admin-users-shell textarea{min-height:86px}.admin-users-shell .user-list{display:grid;gap:10px}.admin-users-shell .user-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center;border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc;padding:12px}.admin-users-shell .pill{display:inline-flex;align-items:center;border-radius:999px;padding:.32rem .64rem;font-weight:900;font-size:.78rem;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.admin-users-shell .pill--inactive{background:#f1f5f9;color:#64748b;border-color:#cbd5e1}.admin-users-shell .row-actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}.admin-users-shell .inline-form{display:inline}.admin-users-shell code{word-break:break-all}@media(max-width:1000px){.admin-users-shell .role-grid,.admin-users-shell .user-grid,.admin-users-shell .field-grid{grid-template-columns:1fr}.admin-users-shell .user-row{grid-template-columns:1fr}}
            </style>

            <?php admin_panel_render_nav('admin/users'); ?>
            <?php if ($message !== ''): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>
            <?php if ($resetUrl !== ''): ?><div class="admin-alert admin-alert--info"><strong>Link reset password:</strong><br><code><?= esc($resetUrl); ?></code><p class="admin-muted">Salin link ini untuk user terkait. Link berlaku 1 jam.</p></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Total User</span><h2><?= (int)($summary['total'] ?? 0); ?></h2><p>Akun role tersimpan.</p></div>
                <div class="admin-card"><span class="admin-badge">Aktif</span><h2><?= (int)($summary['active'] ?? 0); ?></h2><p>Bisa login dashboard.</p></div>
                <div class="admin-card"><span class="admin-badge">Nonaktif</span><h2><?= (int)($summary['inactive'] ?? 0); ?></h2><p>Diblok sementara.</p></div>
                <div class="admin-card"><span class="admin-badge">Role</span><h2><?= count($roles); ?></h2><p>Mode kerja tersedia.</p></div>
            </div>

            <div class="role-grid">
                <?php foreach ($roles as $key => $role): ?>
                    <article class="role-card">
                        <span class="pill"><?= esc((string)($role['workflow'] ?? 'Workflow')); ?></span>
                        <h3><?= esc((string)($role['label'] ?? $key)); ?></h3>
                        <p><?= esc((string)($role['description'] ?? '')); ?></p>
                        <small><?= (int)($summary['roles'][$key] ?? 0); ?> user</small>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="user-grid">
                <form method="post" class="user-card">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="form_action" value="save_user">
                    <input type="hidden" name="id" value="<?= esc((string)($editRecord['id'] ?? '')); ?>">
                    <h2><?= $editRecord ? 'Edit User Admin' : 'Tambah User Admin'; ?></h2>
                    <p class="admin-muted">Password minimal 8 karakter. Kosongkan password saat edit jika tidak ingin mengganti password.</p>
                    <div class="field-grid">
                        <label>Nama<input name="name" value="<?= esc((string)($editRecord['name'] ?? '')); ?>" placeholder="Nama admin / staf"></label>
                        <label>Email<input type="email" name="email" value="<?= esc((string)($editRecord['email'] ?? '')); ?>" placeholder="admin@domain.com"></label>
                        <label>Username<input name="username" value="<?= esc((string)($editRecord['username'] ?? '')); ?>" placeholder="admin-operasional"></label>
                        <label>Role<select name="role">
                            <?php foreach ($roles as $key => $role): ?>
                                <option value="<?= esc((string)$key); ?>" <?= ((string)($editRecord['role'] ?? 'admin_operasional') === (string)$key) ? 'selected' : ''; ?>><?= esc((string)($role['label'] ?? $key)); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Status<select name="status">
                            <option value="active" <?= ((string)($editRecord['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="inactive" <?= ((string)($editRecord['status'] ?? 'active') === 'inactive') ? 'selected' : ''; ?>>Nonaktif</option>
                        </select></label>
                        <label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="<?= $editRecord ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter'; ?>"></label>
                    </div>
                    <label>Catatan internal<textarea name="notes" placeholder="Contoh: CS harian, tim SEO, sales specialist."><?= esc((string)($editRecord['notes'] ?? '')); ?></textarea></label>
                    <div class="admin-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Simpan User</button>
                        <?php if ($editRecord): ?><a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/users')); ?>">Batal Edit</a><?php endif; ?>
                    </div>
                </form>

                <aside class="user-card">
                    <h2>Panduan Role</h2>
                    <p>Gunakan role sesuai tugas tim supaya setiap orang hanya melihat menu yang relevan dengan pekerjaannya.</p>
                    <ul class="admin-muted">
                        <li><strong>Owner</strong>: pegang akses penuh dan pengaturan sensitif.</li>
                        <li><strong>Operasional</strong>: kelola produk, artikel, order, follow-up, dan stok.</li>
                        <li><strong>SEO/Marketing/Sales Specialist</strong>: fokus ke area kerja masing-masing.</li>
                    </ul>
                </aside>
            </div>

            <div class="user-card">
                <h2>Daftar User Admin</h2>
                <div class="user-list">
                    <?php foreach ($records as $row): ?>
                        <article class="user-row">
                            <div>
                                <strong><?= esc((string)($row['name'] ?: $row['email'] ?: $row['username'] ?: 'User Admin')); ?></strong>
                                <p><?= esc((string)($row['email'] ?: '-')); ?> · <?= esc((string)($row['username'] ?: '-')); ?></p>
                                <span class="pill <?= (string)($row['status'] ?? 'active') === 'inactive' ? 'pill--inactive' : ''; ?>"><?= esc(admin_users_role_label((string)($row['role'] ?? 'admin_operasional'))); ?> · <?= esc((string)($row['status'] ?? 'active')); ?></span>
                                <p class="admin-muted">Last login: <?= esc(!empty($row['last_login_at']) ? date('d M Y H:i', strtotime((string)$row['last_login_at'])) : '-'); ?></p>
                            </div>
                            <div class="row-actions">
                                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/users?edit=' . rawurlencode((string)$row['id']))); ?>">Edit</a>
                                <form method="post" class="inline-form"><?= csrf_field(); ?><input type="hidden" name="form_action" value="reset_user"><input type="hidden" name="id" value="<?= esc((string)$row['id']); ?>"><button class="admin-btn admin-btn--soft" type="submit">Reset</button></form>
                                <form method="post" class="inline-form" onsubmit="return confirm('Hapus user admin ini?')"><?= csrf_field(); ?><input type="hidden" name="form_action" value="delete_user"><input type="hidden" name="id" value="<?= esc((string)$row['id']); ?>"><button class="admin-btn admin-btn--danger" type="submit">Hapus</button></form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                    <?php if (!$records): ?><p class="admin-muted">Belum ada user admin. Tambahkan akun pertama untuk tim operasional, SEO, marketing, sales specialist, atau growth manager.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
