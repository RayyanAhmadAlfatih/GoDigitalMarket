<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$action = (string)($_GET['action'] ?? 'list');
$message = (string)($_GET['message'] ?? '');
$error = '';
$formTypes = custom_form_type_options();
$fieldTypes = custom_form_field_types();
$statusOptions = custom_form_status_options();

if (!function_exists('admin_forms_url')) {
    function admin_forms_url(array $params = []): string
    {
        $query = http_build_query(array_filter($params, static fn($value): bool => $value !== '' && $value !== null));
        return url('admin/forms' . ($query ? '?' . $query : ''));
    }
}

if (!function_exists('admin_forms_csv_value')) {
    function admin_forms_csv_value(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(static fn($item): string => is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string)$item, $value));
        }
        return (string)$value;
    }
}

if ($action === 'export') {
    $filters = [
        'form_slug' => (string)($_GET['form_slug'] ?? ''),
        'search' => (string)($_GET['search'] ?? ''),
        'date_from' => (string)($_GET['date_from'] ?? ''),
        'date_to' => (string)($_GET['date_to'] ?? ''),
    ];
    $submissions = custom_form_read_submissions($filters, 5000);

    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="custom-form-submissions-' . date('Ymd-His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Waktu', 'Form', 'Jenis Form', 'Ringkasan', 'Data Lengkap', 'File', 'Halaman Sumber'], ',', chr(34), '\\', PHP_EOL);
    foreach ($submissions as $row) {
        fputcsv($out, [
            (string)($row['created_at'] ?? ''),
            (string)($row['form_title'] ?? ''),
            (string)($row['form_type'] ?? ''),
            (string)($row['summary'] ?? ''),
            admin_forms_csv_value($row['values'] ?? []),
            admin_forms_csv_value($row['files'] ?? []),
            (string)($row['source_url'] ?? ''),
        ], ',', chr(34), '\\', PHP_EOL);
    }
    fclose($out);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $formAction = (string)($_POST['form_action'] ?? '');

        if ($formAction === 'save') {
            $saved = custom_form_save_from_post($_POST);
            redirect_302('admin/forms?action=edit&id=' . rawurlencode((string)$saved['id']) . '&message=' . rawurlencode('Form berhasil disimpan.'));
        }

        if ($formAction === 'delete') {
            $id = (string)($_POST['id'] ?? '');
            custom_form_delete($id);
            redirect_302('admin/forms?message=' . rawurlencode('Form berhasil dihapus.'));
        }

        if ($formAction === 'quick_seed') {
            custom_form_write_forms(custom_form_default_forms());
            redirect_302('admin/forms?message=' . rawurlencode('Contoh form bawaan sudah dibuat ulang.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$forms = custom_form_read_forms();
$stats = custom_form_stats();
$previewForm = null;
foreach ($forms as $candidateForm) {
    if ((string)($candidateForm['status'] ?? '') === 'active') {
        $previewForm = $candidateForm;
        break;
    }
}
if (!$previewForm && !empty($forms[0]) && is_array($forms[0])) {
    $previewForm = $forms[0];
}
$previewFormUrl = $previewForm ? url('form/' . (string)$previewForm['slug']) : url('admin/forms?action=edit');
$previewFormTitle = $previewForm ? (string)($previewForm['title'] ?? 'Form Custom') : 'Belum ada form';
$previewFormStatus = $previewForm ? (string)($previewForm['status'] ?? 'draft') : 'draft';
$currentForm = null;
if ($action === 'edit') {
    $id = (string)($_GET['id'] ?? '');
    if ($id !== '') {
        $currentForm = custom_form_find($id, false);
    }
    if (!$currentForm) {
        $presetType = (string)($_GET['type'] ?? 'consultation');
        $currentForm = custom_form_normalize([
            'title' => 'Form Baru',
            'slug' => 'form-baru',
            'type' => isset($formTypes[$presetType]) ? $presetType : 'consultation',
            'status' => 'draft',
            'description' => 'Jelaskan tujuan form ini agar pengunjung paham sebelum mengisi.',
            'submit_label' => 'Kirim Form',
            'success_message' => 'Terima kasih, data Anda sudah masuk. Admin akan menghubungi Anda.',
            'consent_text' => 'Saya bersedia dihubungi admin terkait data yang saya kirim.',
            'integrations' => custom_form_default_integrations(),
            'fields' => custom_form_default_fields(isset($formTypes[$presetType]) ? $presetType : 'consultation'),
        ]);
        $currentForm['id'] = '';
    }
}

$filters = [
    'form_slug' => (string)($_GET['form_slug'] ?? ''),
    'search' => (string)($_GET['search'] ?? ''),
    'date_from' => (string)($_GET['date_from'] ?? ''),
    'date_to' => (string)($_GET['date_to'] ?? ''),
];
$submissions = $action === 'submissions' ? custom_form_read_submissions($filters, 300) : [];

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Form Custom - ' . SITE_NAME,
    'description' => 'Buat form kontak, konsultasi, lead magnet, booking, dan checkout sederhana.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-forms-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Form Builder</div>
                <h1>Form Custom</h1>
                <p>Buat form untuk kontak, konsultasi, lead magnet, booking, permintaan penawaran, atau checkout sederhana.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-tabs admin-tabs--forms">
                <a class="<?= $action === 'list' ? 'is-active' : ''; ?>" href="<?= esc(admin_forms_url()); ?>">Daftar Form</a>
                <a class="<?= $action === 'edit' ? 'is-active' : ''; ?>" href="<?= esc(admin_forms_url(['action' => 'edit'])); ?>">Buat/Edit Form</a>
                <a class="<?= $action === 'submissions' ? 'is-active' : ''; ?>" href="<?= esc(admin_forms_url(['action' => 'submissions'])); ?>">Data Masuk</a>
            </div>

            <?php if ($action === 'list'): ?>
                <div class="admin-stat-grid admin-stat-grid--compact">
                    <div class="admin-stat-card"><span>Total Form</span><strong><?= (int)$stats['forms']; ?></strong><small>Form tersimpan.</small></div>
                    <div class="admin-stat-card"><span>Form Aktif</span><strong><?= (int)$stats['active_forms']; ?></strong><small>Bisa diisi pengunjung.</small></div>
                    <div class="admin-stat-card"><span>Data Masuk</span><strong><?= (int)$stats['submissions']; ?></strong><small>Total submission.</small></div>
                    <div class="admin-stat-card"><span>Hari Ini</span><strong><?= (int)$stats['today']; ?></strong><small>Submission hari ini.</small></div>
                </div>

                <div class="admin-card admin-editor">
                    <div class="admin-form-head admin-form-head--row">
                        <div>
                            <span class="admin-badge">Daftar Form</span>
                            <h2>Form yang Bisa Dipakai</h2>
                            <p>Gunakan link form untuk halaman mandiri atau pilih form ini di Landing Page Builder.</p>
                        </div>
                        <form method="post" onsubmit="return confirm('Buat ulang contoh form bawaan? Form custom yang sudah ada akan diganti.');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="quick_seed">
                            <button class="admin-btn admin-btn--ghost" type="submit">Reset Contoh</button>
                        </form>
                    </div>

                    <div class="admin-table-wrap">
                        <table class="admin-table admin-forms-table">
                            <thead>
                                <tr>
                                    <th>Nama Form</th>
                                    <th>Jenis</th>
                                    <th>Status</th>
                                    <th>Field</th>
                                    <th>Link</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($forms as $form): ?>
                                    <?php $publicUrl = url('form/' . (string)$form['slug']); ?>
                                    <tr>
                                        <td>
                                            <strong><?= esc((string)$form['title']); ?></strong>
                                            <small><?= esc((string)$form['description']); ?></small>
                                        </td>
                                        <td><span class="admin-chip"><?= esc($formTypes[(string)$form['type']] ?? 'Custom'); ?></span></td>
                                        <td><span class="admin-status admin-status--<?= (string)$form['status'] === 'active' ? 'ok' : 'muted'; ?>"><?= esc($statusOptions[(string)$form['status']] ?? 'Draft'); ?></span></td>
                                        <td><?= count((array)$form['fields']); ?> field</td>
                                        <td><a href="<?= esc($publicUrl); ?>" target="_blank" rel="noopener">/form/<?= esc((string)$form['slug']); ?></a></td>
                                        <td class="admin-table-actions">
                                            <a class="admin-btn admin-btn--small" href="<?= esc(admin_forms_url(['action' => 'edit', 'id' => (string)$form['id']])); ?>">Edit</a>
                                            <a class="admin-btn admin-btn--small admin-btn--light" href="<?= esc(admin_forms_url(['action' => 'submissions', 'form_slug' => (string)$form['slug']])); ?>">Data</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif ($action === 'edit' && $currentForm): ?>
                <?php $fieldRows = array_values((array)($currentForm['fields'] ?? [])); ?>
                <?php if (!$fieldRows): $fieldRows[] = ['key' => '', 'label' => '', 'type' => 'text', 'required' => false, 'placeholder' => '', 'help' => '', 'options' => []]; endif; ?>
                <?php $currentIntegrations = is_array($currentForm['integrations'] ?? null) ? array_replace(custom_form_default_integrations(), (array)$currentForm['integrations']) : custom_form_default_integrations(); ?>
                <?php $adminWhatsappValue = (string)($currentIntegrations['admin_whatsapp'] ?? '') ?: (defined('SITE_WHATSAPP') ? SITE_WHATSAPP : ''); ?>
                <?php $adminEmailValue = (string)($currentIntegrations['admin_email'] ?? '') ?: (function_exists('notification_admin_email') ? notification_admin_email() : (defined('SITE_EMAIL') ? SITE_EMAIL : '')); ?>

                <form method="post" class="admin-form-builder" id="customFormBuilder">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="form_action" value="save">
                    <input type="hidden" name="id" value="<?= esc((string)($currentForm['id'] ?? '')); ?>">
                    <input type="hidden" name="created_at" value="<?= esc((string)($currentForm['created_at'] ?? date('c'))); ?>">

                    <div class="custom-form-edit-shell">
                        <div class="admin-card admin-editor custom-form-edit-intro">
                            <div class="admin-form-head admin-form-head--row">
                                <div>
                                    <span class="admin-badge">Buat/Edit Form</span>
                                    <h2>Editor Form per Bagian</h2>
                                    <p>Kelola pengaturan form, field, pesan otomatis, alur submit, dan integrasi dari bagian yang terpisah.</p>
                                </div>
                                <?php if (!empty($currentForm['id'])): ?>
                                    <a class="admin-btn admin-btn--light" href="<?= esc(url('form/' . (string)$currentForm['slug'])); ?>" target="_blank" rel="noopener">Lihat Form Publik</a>
                                <?php endif; ?>
                            </div>
                            <div class="custom-form-step-hint">
                                <span><strong>1</strong> Atur nama form</span>
                                <span><strong>2</strong> Susun field</span>
                                <span><strong>3</strong> Atur pesan otomatis</span>
                                <span><strong>4</strong> Atur setelah submit</span>
                                <span><strong>5</strong> Cek integrasi</span>
                            </div>
                        </div>

                        <div class="custom-form-subtabs" role="tablist" aria-label="Bagian edit form custom">
                            <button type="button" class="custom-form-subtab is-active" role="tab" aria-selected="true" aria-controls="form-tab-settings" data-form-subtab="settings"><span>1. Pengaturan Form</span><small>Nama, slug, status</small></button>
                            <button type="button" class="custom-form-subtab" role="tab" aria-selected="false" aria-controls="form-tab-fields" data-form-subtab="fields"><span>2. Field Form</span><small>Pertanyaan & input</small></button>
                            <button type="button" class="custom-form-subtab" role="tab" aria-selected="false" aria-controls="form-tab-messages" data-form-subtab="messages"><span>3. Pesan Otomatis</span><small>WA & email otomatis</small></button>
                            <button type="button" class="custom-form-subtab" role="tab" aria-selected="false" aria-controls="form-tab-after-submit" data-form-subtab="after-submit"><span>4. Setelah Submit</span><small>Redirect & pesan sukses</small></button>
                            <button type="button" class="custom-form-subtab" role="tab" aria-selected="false" aria-controls="form-tab-integrations" data-form-subtab="integrations"><span>5. Integrasi Layanan</span><small>Fonnte, Mailketing, webhook</small></button>
                        </div>

                        <div class="custom-form-mobile-jump">
                            <label class="admin-field">
                                <span>Pilih bagian edit</span>
                                <select data-form-subtab-select>
                                    <option value="settings">1. Pengaturan Form</option>
                                    <option value="fields">2. Field Form</option>
                                    <option value="messages">3. Pesan Otomatis Form</option>
                                    <option value="after-submit">4. Setelah Submit Form</option>
                                    <option value="integrations">5. Integrasi Layanan Lain</option>
                                </select>
                            </label>
                        </div>

                        <div class="admin-builder-layout admin-builder-layout--form-tabs">
                            <div class="admin-builder-main">
                                <section class="custom-form-tab-panel is-active" id="form-tab-settings" role="tabpanel" data-form-tab-panel="settings">
                                    <div class="admin-card admin-editor">
                                        <div class="admin-form-head">
                                            <span class="admin-badge">Pengaturan Form</span>
                                            <h2>Informasi Utama</h2>
                                            <p>Buat nama form yang mudah dipahami. Slug akan menjadi link publik form.</p>
                                        </div>
                                        <div class="admin-form-grid">
                                            <label class="admin-field">
                                                <span>Nama Form</span>
                                                <input type="text" name="title" value="<?= esc((string)$currentForm['title']); ?>" maxlength="120" required>
                                            </label>
                                            <label class="admin-field">
                                                <span>Slug / Link Form</span>
                                                <input type="text" name="slug" value="<?= esc((string)$currentForm['slug']); ?>" maxlength="120" placeholder="konsultasi">
                                                <small>Link publik: /form/slug-form</small>
                                            </label>
                                            <label class="admin-field">
                                                <span>Jenis Form</span>
                                                <select name="type">
                                                    <?php foreach ($formTypes as $key => $label): ?>
                                                        <option value="<?= esc($key); ?>" <?= (string)$currentForm['type'] === $key ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label class="admin-field">
                                                <span>Status</span>
                                                <select name="status">
                                                    <?php foreach ($statusOptions as $key => $label): ?>
                                                        <option value="<?= esc($key); ?>" <?= (string)$currentForm['status'] === $key ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label class="admin-field admin-field--wide">
                                                <span>Deskripsi Form</span>
                                                <textarea name="description" rows="3" maxlength="360" placeholder="Jelaskan singkat tujuan form ini."><?= esc((string)$currentForm['description']); ?></textarea>
                                            </label>
                                        </div>
                                    </div>
                                </section>

                                <section class="custom-form-tab-panel" id="form-tab-fields" role="tabpanel" data-form-tab-panel="fields">
                                    <div class="admin-card admin-editor">
                                        <div class="admin-form-head admin-form-head--row">
                                            <div>
                                                <span class="admin-badge">Field Form</span>
                                                <h2>Susun Pertanyaan</h2>
                                                <p>Isi label field, pilih jenis input, lalu centang wajib jika harus diisi.</p>
                                            </div>
                                            <button class="admin-btn admin-btn--ghost" type="button" data-add-field>+ Tambah Field</button>
                                        </div>

                                        <div class="admin-help-box admin-help-box--inline">
                                            <strong>Fleksibel</strong>
                                            <span>Field bawaan hanya contoh awal. Admin bisa tambah sampai 30 field, hapus field yang tidak perlu, atau urutkan sesuai alur pertanyaan.</span>
                                        </div>
                                        <div class="custom-field-toolbar">
                                            <button class="admin-btn" type="button" data-add-field>+ Tambah Field Baru</button>
                                            <div class="custom-field-template-picker">
                                                <select data-field-template-select aria-label="Pilih field cepat">
                                                    <option value="text">Teks Pendek</option>
                                                    <option value="phone">Nomor WhatsApp</option>
                                                    <option value="email">Email</option>
                                                    <option value="textarea">Pesan / Catatan</option>
                                                    <option value="select">Dropdown Pilihan</option>
                                                    <option value="radio">Pilihan Satu Jawaban</option>
                                                    <option value="checkbox">Checklist</option>
                                                    <option value="date">Tanggal</option>
                                                    <option value="number">Angka</option>
                                                    <option value="file">Upload File</option>
                                                </select>
                                                <button class="admin-btn admin-btn--light" type="button" data-add-template-field>+ Tambah dari Template</button>
                                            </div>
                                            <span class="custom-field-counter" data-field-counter><?= count($fieldRows); ?> field aktif</span>
                                        </div>
                                        <div class="custom-field-list" data-field-list>
                                            <?php foreach ($fieldRows as $index => $field): ?>
                                                <div class="custom-field-row" data-field-row>
                                                    <div class="custom-field-row__top">
                                                        <strong>Field <span data-field-number><?= $index + 1; ?></span></strong>
                                                        <div class="custom-field-row__actions">
                                                            <button type="button" class="admin-mini-link" data-move-field="up">Naik</button>
                                                            <button type="button" class="admin-mini-link" data-move-field="down">Turun</button>
                                                            <button type="button" class="admin-mini-link" data-duplicate-field>Duplikat</button>
                                                            <button type="button" class="admin-mini-link admin-mini-link--danger" data-remove-field>Hapus</button>
                                                        </div>
                                                    </div>
                                                    <div class="admin-form-grid">
                                                        <label class="admin-field">
                                                            <span>Label</span>
                                                            <input type="text" name="field_label[]" value="<?= esc((string)($field['label'] ?? '')); ?>" maxlength="90" placeholder="Contoh: Nama">
                                                        </label>
                                                        <label class="admin-field">
                                                            <span>Nama Data</span>
                                                            <input type="text" name="field_key[]" value="<?= esc((string)($field['key'] ?? '')); ?>" maxlength="90" placeholder="nama">
                                                        </label>
                                                        <label class="admin-field">
                                                            <span>Jenis Field</span>
                                                            <select name="field_type[]">
                                                                <?php foreach ($fieldTypes as $key => $label): ?>
                                                                    <option value="<?= esc($key); ?>" <?= (string)($field['type'] ?? 'text') === $key ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label class="admin-field admin-field--checkline">
                                                            <span>Wajib?</span>
                                                            <input type="hidden" name="field_required[<?= $index; ?>]" value="0">
                                                            <label class="admin-switch-inline"><input type="checkbox" name="field_required[<?= $index; ?>]" value="1" <?= !empty($field['required']) ? 'checked' : ''; ?>><span>Wajib diisi</span></label>
                                                        </label>
                                                        <label class="admin-field">
                                                            <span>Placeholder</span>
                                                            <input type="text" name="field_placeholder[]" value="<?= esc((string)($field['placeholder'] ?? '')); ?>" maxlength="120" placeholder="Contoh: Nama Anda">
                                                        </label>
                                                        <label class="admin-field">
                                                            <span>Panduan Kecil</span>
                                                            <input type="text" name="field_help[]" value="<?= esc((string)($field['help'] ?? '')); ?>" maxlength="180" placeholder="Opsional">
                                                        </label>
                                                        <label class="admin-field admin-field--wide">
                                                            <span>Opsi Pilihan</span>
                                                            <textarea name="field_options[]" rows="3" placeholder="Untuk dropdown/radio/checkbox. Satu pilihan per baris."><?= esc(implode("\n", (array)($field['options'] ?? []))); ?></textarea>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="custom-field-bottom-action">
                                            <button class="admin-btn" type="button" data-add-field>+ Tambah Field Baru</button>
                                            <small>Field baru akan muncul di bawah daftar. Admin tetap bisa mengurutkan dengan tombol Naik/Turun.</small>
                                        </div>
                                    </div>
                                </section>

                                <section class="custom-form-tab-panel" id="form-tab-messages" role="tabpanel" data-form-tab-panel="messages">
                                    <div class="admin-card admin-editor">
                                        <div class="admin-form-head">
                                            <span class="admin-badge">Pesan Otomatis Form</span>
                                            <h2>Pesan yang Dikirim Saat User Submit</h2>
                                            <p>Atur isi WhatsApp dan email otomatis khusus untuk form ini. Admin bisa membedakan pesan untuk pemilik website dan lead/customer.</p>
                                        </div>
                                        <div class="admin-help-box admin-help-box--inline">
                                            <strong>Default tetap aman</strong>
                                            <span>Kalau template khusus dikosongkan, sistem memakai pesan default global dari menu WhatsApp & Email Marketing. Jadi admin bisa custom hanya jika memang perlu.</span>
                                        </div>
                                        <div class="custom-form-variable-box">
                                            <strong>Variable pesan yang bisa dipakai:</strong>
                                            <code>{nama}</code><code>{whatsapp}</code><code>{email}</code><code>{kebutuhan}</code><code>{pesan}</code><code>{summary}</code><code>{form_name}</code><code>{submission_id}</code><code>{source_url}</code><code>{site_name}</code>
                                        </div>
                                        <div class="custom-automation-grid">
                                            <div class="custom-automation-card custom-automation-card--wa">
                                                <input type="hidden" name="integration_whatsapp_admin_enabled" value="0">
                                                <label class="admin-toggle-option"><input type="checkbox" name="integration_whatsapp_admin_enabled" value="1" <?= !empty($currentIntegrations['whatsapp_admin_enabled']) ? 'checked' : ''; ?>><span>WhatsApp ke admin / pemilik website</span></label>
                                                <small>Notifikasi internal untuk pemilik website saat ada data baru masuk.</small>
                                                <textarea name="integration_whatsapp_admin_template" rows="7" maxlength="1400" placeholder="Kosongkan untuk memakai pesan default global dari menu WhatsApp & Email Marketing"><?= esc((string)($currentIntegrations['whatsapp_admin_template'] ?? '')); ?></textarea>
                                            </div>
                                            <div class="custom-automation-card custom-automation-card--wa">
                                                <input type="hidden" name="integration_whatsapp_customer_enabled" value="0">
                                                <label class="admin-toggle-option"><input type="checkbox" name="integration_whatsapp_customer_enabled" value="1" <?= !empty($currentIntegrations['whatsapp_customer_enabled']) ? 'checked' : ''; ?>><span>WhatsApp ke lead / customer</span></label>
                                                <small>Auto-reply ramah untuk orang yang baru saja mengisi form.</small>
                                                <textarea name="integration_whatsapp_customer_template" rows="7" maxlength="1400" placeholder="Kosongkan untuk memakai pesan default global dari menu WhatsApp & Email Marketing"><?= esc((string)($currentIntegrations['whatsapp_customer_template'] ?? '')); ?></textarea>
                                            </div>
                                            <div class="custom-automation-card custom-automation-card--email">
                                                <input type="hidden" name="integration_email_admin_enabled" value="0">
                                                <label class="admin-toggle-option"><input type="checkbox" name="integration_email_admin_enabled" value="1" <?= !empty($currentIntegrations['email_admin_enabled']) ? 'checked' : ''; ?>><span>Email ke admin / pemilik website</span></label>
                                                <small>Subjek dan isi email notifikasi untuk admin. Kosongkan untuk memakai default global.</small>
                                                <input type="text" name="integration_email_admin_subject" value="<?= esc((string)($currentIntegrations['email_admin_subject'] ?? '')); ?>" maxlength="160" placeholder="Subjek email admin">
                                                <textarea name="integration_email_admin_template" rows="7" maxlength="2600" placeholder="Isi email admin"><?= esc((string)($currentIntegrations['email_admin_template'] ?? '')); ?></textarea>
                                            </div>
                                            <div class="custom-automation-card custom-automation-card--email">
                                                <input type="hidden" name="integration_email_customer_enabled" value="0">
                                                <label class="admin-toggle-option"><input type="checkbox" name="integration_email_customer_enabled" value="1" <?= !empty($currentIntegrations['email_customer_enabled']) ? 'checked' : ''; ?>><span>Email ke lead / customer</span></label>
                                                <small>Auto-reply email untuk customer, lead magnet, instruksi booking, atau arahan lanjutan.</small>
                                                <input type="text" name="integration_email_customer_subject" value="<?= esc((string)($currentIntegrations['email_customer_subject'] ?? '')); ?>" maxlength="160" placeholder="Subjek email lead/customer">
                                                <textarea name="integration_email_customer_template" rows="7" maxlength="2600" placeholder="Isi email lead/customer"><?= esc((string)($currentIntegrations['email_customer_template'] ?? '')); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </section>

                                <section class="custom-form-tab-panel" id="form-tab-after-submit" role="tabpanel" data-form-tab-panel="after-submit">
                                    <div class="admin-card admin-editor">
                                        <div class="admin-form-head">
                                            <span class="admin-badge">Setelah Submit Form</span>
                                            <h2>Pesan Sukses dan Arah Lanjutan</h2>
                                            <p>Atur tombol, pesan sukses, redirect, dan persetujuan setelah pengunjung mengirim form.</p>
                                        </div>
                                        <div class="admin-form-grid">
                                            <label class="admin-field">
                                                <span>Teks Tombol</span>
                                                <input type="text" name="submit_label" value="<?= esc((string)$currentForm['submit_label']); ?>" maxlength="50">
                                            </label>
                                            <label class="admin-field">
                                                <span>Redirect Setelah Submit</span>
                                                <input type="text" name="redirect_url" value="<?= esc((string)$currentForm['redirect_url']); ?>" maxlength="240" placeholder="Kosongkan untuk halaman sukses bawaan">
                                                <small>Bisa diisi URL thank-you page, link katalog, atau halaman download.</small>
                                            </label>
                                            <label class="admin-field admin-field--wide">
                                                <span>Pesan Sukses</span>
                                                <textarea name="success_message" rows="3" maxlength="360"><?= esc((string)$currentForm['success_message']); ?></textarea>
                                            </label>
                                            <label class="admin-field admin-field--wide">
                                                <span>Kalimat Persetujuan</span>
                                                <textarea name="consent_text" rows="2" maxlength="240"><?= esc((string)$currentForm['consent_text']); ?></textarea>
                                                <small>Kosongkan jika form tidak membutuhkan checkbox persetujuan.</small>
                                            </label>
                                        </div>
                                    </div>
                                </section>

                                <section class="custom-form-tab-panel" id="form-tab-integrations" role="tabpanel" data-form-tab-panel="integrations">
                                    <div class="admin-card admin-editor">
                                        <div class="admin-form-head admin-form-head--row">
                                            <div>
                                                <span class="admin-badge">Setting Integrasi dengan Layanan Lain</span>
                                                <h2>Fonnte, Mailketing, Email, dan Webhook</h2>
                                                <p>Pilih layanan tambahan untuk form ini. Token/API utama tetap diatur secara global agar admin tidak mengisi berulang-ulang.</p>
                                            </div>
                                            <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/marketing-integrations')); ?>">Buka WhatsApp & Email Marketing</a>
                                        </div>
                                        <div class="custom-integration-service-grid">
                                            <div class="custom-integration-service-card"><strong>Fonnte</strong><span>Dipakai untuk kirim WhatsApp otomatis. Token global ada di WhatsApp & Email Marketing.</span></div>
                                            <div class="custom-integration-service-card"><strong>Mailketing</strong><span>Dipakai untuk memasukkan lead ke list email/CRM.</span></div>
                                            <div class="custom-integration-service-card"><strong>Webhook</strong><span>Opsional untuk n8n, Zapier, Make, atau sistem lain.</span></div>
                                        </div>
                                        <div class="admin-form-grid">
                                            <label class="admin-field admin-field--wide admin-field--checkline">
                                                <span>Mailketing untuk form ini</span>
                                                <input type="hidden" name="integration_send_to_marketing" value="0">
                                                <label class="admin-toggle-option"><input type="checkbox" name="integration_send_to_marketing" value="1" <?= !empty($currentIntegrations['send_to_marketing']) ? 'checked' : ''; ?>><span>Masukkan kontak ke Mailketing setelah form dikirim</span></label>
                                                <small>Memakai token Mailketing dari menu WhatsApp & Email Marketing.</small>
                                            </label>
                                            <label class="admin-field">
                                                <span>List Mailketing Khusus Form Ini</span>
                                                <input type="text" name="integration_mailketing_list_id" value="<?= esc((string)($currentIntegrations['mailketing_list_id'] ?? '')); ?>" maxlength="80" placeholder="Opsional, kosongkan untuk List ID Form Masuk/default">
                                            </label>
                                            <label class="admin-field">
                                                <span>Nomor WA Admin Khusus Form Ini</span>
                                                <input type="text" name="integration_admin_whatsapp" value="<?= esc($adminWhatsappValue); ?>" maxlength="40" placeholder="628xxxxxxxxxx">
                                                <small>Kosongkan untuk memakai nomor WhatsApp dari Brand & Warna.</small>
                                            </label>
                                            <label class="admin-field">
                                                <span>Email Admin Khusus Form Ini</span>
                                                <input type="email" name="integration_admin_email" value="<?= esc($adminEmailValue); ?>" maxlength="160" placeholder="admin@email.com">
                                            </label>
                                            <label class="admin-field admin-field--checkline">
                                                <span>Kirim ke Layanan Lain</span>
                                                <input type="hidden" name="integration_webhook_enabled" value="0">
                                                <label class="admin-toggle-option"><input type="checkbox" name="integration_webhook_enabled" value="1" <?= !empty($currentIntegrations['webhook_enabled']) ? 'checked' : ''; ?>><span>Aktifkan URL tujuan tambahan</span></label>
                                            </label>
                                            <label class="admin-field admin-field--wide">
                                                <span>URL Tujuan Tambahan</span>
                                                <input type="url" name="integration_webhook_url" value="<?= esc((string)($currentIntegrations['webhook_url'] ?? '')); ?>" maxlength="360" placeholder="https://contoh.com/terima-data-form">
                                                <small>Opsional untuk n8n, Zapier, Make, atau sistem lain. Kosongkan jika belum dipakai.</small>
                                            </label>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <aside class="admin-builder-side">
                                <div class="admin-card admin-editor admin-sticky-card">
                                    <span class="admin-badge">Simpan</span>
                                    <h2>Publikasikan Form</h2>
                                    <p>Form disimpan dari tombol ini, walaupun field berada di sub tab berbeda.</p>
                                    <button class="admin-btn admin-btn--wide" type="submit" name="form_action" value="save">Simpan Form</button>
                                    <?php if (!empty($currentForm['id'])): ?>
                                        <a class="admin-btn admin-btn--light admin-btn--wide" href="<?= esc(url('form/' . (string)$currentForm['slug'])); ?>" target="_blank" rel="noopener">Lihat Form</a>
                                        <button class="admin-btn admin-btn--danger admin-btn--wide" type="submit" name="form_action" value="delete" onclick="return confirm('Hapus form ini? Data submission lama tetap tersimpan.');">Hapus Form</button>
                                    <?php endif; ?>
                                    <div class="admin-help-box custom-form-save-help">
                                        <strong>Tips cepat</strong>
                                        <span>Mulai dari Pengaturan Form, lanjut Field Form, lalu cek Pesan Otomatis. Bagian integrasi bisa dibuka belakangan kalau token layanan sudah siap.</span>
                                    </div>
                                    <div class="custom-form-side-nav" aria-label="Shortcut bagian edit form">
                                        <button type="button" data-form-subtab-jump="settings">Pengaturan</button>
                                        <button type="button" data-form-subtab-jump="fields">Field</button>
                                        <button type="button" data-form-subtab-jump="messages">Pesan Otomatis</button>
                                        <button type="button" data-form-subtab-jump="after-submit">Setelah Submit</button>
                                        <button type="button" data-form-subtab-jump="integrations">Integrasi</button>
                                    </div>
                                </div>
                            </aside>
                        </div>
                    </div>
                </form>
            <?php elseif ($action === 'submissions'): ?>
                <div class="admin-card admin-editor">
                    <div class="admin-form-head">
                        <span class="admin-badge">Inbox Form</span>
                        <h2>Data Masuk dari Pengunjung</h2>
                        <p>Lihat data yang dikirim melalui Form Custom. Admin bisa follow-up via WhatsApp/email, membuka halaman sumber, dan mengecek riwayat pengiriman otomatis.</p>
                    </div>
                    <form method="get" class="admin-filter-bar admin-filter-bar--forms">
                        <input type="hidden" name="action" value="submissions">
                        <select name="form_slug">
                            <option value="">Semua form</option>
                            <?php foreach ($forms as $form): ?>
                                <option value="<?= esc((string)$form['slug']); ?>" <?= (string)$filters['form_slug'] === (string)$form['slug'] ? 'selected' : ''; ?>><?= esc((string)$form['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date_from" value="<?= esc((string)$filters['date_from']); ?>">
                        <input type="date" name="date_to" value="<?= esc((string)$filters['date_to']); ?>">
                        <input type="search" name="search" value="<?= esc((string)$filters['search']); ?>" placeholder="Cari nama, WA, email, pesan...">
                        <button class="admin-btn" type="submit">Filter</button>
                        <a class="admin-btn admin-btn--light" href="<?= esc(admin_forms_url(array_merge(['action' => 'export'], $filters))); ?>">Export CSV</a>
                    </form>
                </div>

                <?php
                    $submissionsByForm = [];
                    foreach ($submissions as $row) {
                        $formSlug = (string)($row['form_slug'] ?? 'form-custom');
                        $formTitle = (string)($row['form_title'] ?? 'Form Custom');
                        if (!isset($submissionsByForm[$formSlug])) {
                            $submissionsByForm[$formSlug] = [
                                'title' => $formTitle,
                                'url' => url('form/' . $formSlug),
                                'items' => [],
                            ];
                        }
                        $submissionsByForm[$formSlug]['items'][] = $row;
                    }
                ?>

                <div class="custom-submission-list custom-submission-list--table">
                    <?php if (!$submissions): ?>
                        <div class="admin-card admin-empty-state"><h2>Belum ada data masuk</h2><p>Data akan muncul setelah pengunjung mengirim form custom.</p></div>
                    <?php endif; ?>
                    <?php foreach ($submissionsByForm as $formSlug => $group): ?>
                        <section class="admin-card custom-submission-table-card">
                            <div class="custom-submission-table-head">
                                <div>
                                    <span class="admin-badge">Nama Form</span>
                                    <h3><a href="<?= esc((string)$group['url']); ?>" target="_blank" rel="noopener"><?= esc((string)$group['title']); ?></a></h3>
                                    <p><?= count((array)$group['items']); ?> data masuk. Klik nama form untuk membuka halaman publik form ini.</p>
                                </div>
                                <a class="admin-btn admin-btn--light" href="<?= esc((string)$group['url']); ?>" target="_blank" rel="noopener">Buka Form</a>
                            </div>

                            <div class="admin-table-wrap custom-submission-table-wrap">
                                <table class="admin-table custom-submission-table">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>WhatsApp</th>
                                            <th>Email</th>
                                            <th>Kebutuhan</th>
                                            <th>Waktu</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ((array)$group['items'] as $row): ?>
                                            <?php
                                                $contact = function_exists('custom_form_submission_contact') ? custom_form_submission_contact($row) : ['name' => 'Customer', 'phone' => '', 'email' => '', 'need' => ''];
                                                $sourceLabel = function_exists('custom_form_submission_source_label') ? custom_form_submission_source_label($row) : 'Halaman form';
                                                $actions = function_exists('custom_form_submission_action_urls') ? custom_form_submission_action_urls($row) : ['whatsapp' => '', 'email' => '', 'source' => (string)($row['source_url'] ?? '')];
                                                $automationLogs = function_exists('custom_form_submission_integration_logs') ? custom_form_submission_integration_logs($row, 6) : [];
                                                $values = is_array($row['values'] ?? null) ? (array)$row['values'] : [];
                                                $createdAt = strtotime((string)($row['created_at'] ?? 'now')) ?: time();
                                                $rowId = 'submission-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)($row['id'] ?? uniqid('row_', true)));
                                                $sourceUrl = (string)($actions['source'] ?? '');
                                                $defaultFollowupWaMessage = 'Halo {nama}, terima kasih sudah menghubungi {site_name}. Kami sudah menerima data dari {form_name}. Admin akan bantu follow-up ya.';
                                                $defaultFollowupEmailMessage = "Halo {nama},\n\nTerima kasih sudah menghubungi {site_name} melalui {form_name}. Data Anda sudah kami terima dan admin akan membantu follow-up.\n\nSalam,\n{site_name}";
                                            ?>
                                            <tr class="custom-submission-row" data-submission-row data-name="<?= esc((string)$contact['name']); ?>" data-phone="<?= esc((string)$contact['phone']); ?>" data-email="<?= esc((string)$contact['email']); ?>" data-need="<?= esc((string)$contact['need']); ?>" data-form="<?= esc((string)($row['form_title'] ?? $group['title'])); ?>" data-source="<?= esc($sourceLabel); ?>">
                                                <td>
                                                    <strong><?= esc((string)$contact['name']); ?></strong>
                                                    <small>Dari: <?php if ($sourceUrl !== ''): ?><a class="custom-submission-source-link" href="<?= esc($sourceUrl); ?>" target="_blank" rel="noopener"><?= esc($sourceLabel); ?></a><?php else: ?><?= esc($sourceLabel); ?><?php endif; ?></small>
                                                </td>
                                                <td><?= esc((string)($contact['phone'] ?: '-')); ?></td>
                                                <td><?= esc((string)($contact['email'] ?: '-')); ?></td>
                                                <td><?= esc((string)($contact['need'] ?: '-')); ?></td>
                                                <td><?= esc(date('d M Y H:i', $createdAt)); ?></td>
                                                <td class="custom-submission-row-actions">
                                                    <button class="admin-mini-link" type="button" data-toggle-followup="<?= esc($rowId); ?>">Follow-up</button>
                                                </td>
                                            </tr>
                                            <tr class="custom-followup-row" id="<?= esc($rowId); ?>" hidden>
                                                <td colspan="6">
                                                    <div class="custom-followup-panel">
                                                        <div class="custom-followup-panel__main">
                                                            <div class="custom-followup-template-grid">
                                                                <label>
                                                                    <span>Template pesan WhatsApp</span>
                                                                    <textarea rows="4" data-followup-template="wa"><?= esc($defaultFollowupWaMessage); ?></textarea>
                                                                </label>
                                                                <label>
                                                                    <span>Template pesan Email</span>
                                                                    <textarea rows="5" data-followup-template="email"><?= esc($defaultFollowupEmailMessage); ?></textarea>
                                                                </label>
                                                            </div>
                                                            <small>Variable yang bisa dipakai: <code>{nama}</code> <code>{whatsapp}</code> <code>{email}</code> <code>{kebutuhan}</code> <code>{form_name}</code> <code>{site_name}</code> <code>{source}</code></small>
                                                        </div>
                                                        <div class="custom-followup-panel__actions">
                                                            <a class="admin-btn admin-btn--small" href="<?= esc((string)$actions['whatsapp']); ?>" target="_blank" rel="noopener" data-followup-wa>Kirim WA</a>
                                                            <a class="admin-btn admin-btn--small admin-btn--light" href="<?= esc((string)$actions['email']); ?>" data-followup-email>Kirim Email</a>
                                                        </div>
                                                    </div>

                                                    <details class="custom-submission-detail-accordion">
                                                        <summary>Lihat data lengkap & riwayat otomatis</summary>
                                                        <div class="custom-submission-detail-grid">
                                                            <div class="custom-submission-detail-box">
                                                                <h4>Data yang dikirim</h4>
                                                                <dl class="custom-submission-fields">
                                                                    <?php foreach ($values as $key => $value): ?>
                                                                        <?php
                                                                            $prettyValue = is_array($value) ? implode(', ', array_map('strval', $value)) : (string)$value;
                                                                            if (trim($prettyValue) === '') { continue; }
                                                                            $prettyLabel = function_exists('custom_form_pretty_field_label') ? custom_form_pretty_field_label((string)$key) : ucwords(str_replace('_', ' ', (string)$key));
                                                                        ?>
                                                                        <div><dt><?= esc($prettyLabel); ?></dt><dd><?php if (str_contains($prettyValue, 'admin/form-file?file=')): ?><a class="admin-btn admin-btn--small admin-btn--light" href="<?= esc($prettyValue); ?>" target="_blank" rel="noopener">Buka file</a><?php else: ?><?= nl2br(esc($prettyValue)); ?><?php endif; ?></dd></div>
                                                                    <?php endforeach; ?>
                                                                </dl>
                                                            </div>
                                                            <div class="custom-submission-automation">
                                                                <div class="custom-submission-automation__head">
                                                                    <strong>Riwayat Otomatis</strong>
                                                                    <span>WA, email, Mailketing, webhook</span>
                                                                </div>
                                                                <?php if (!$automationLogs): ?>
                                                                    <p class="admin-muted">Belum ada riwayat otomatis untuk data ini.</p>
                                                                <?php else: ?>
                                                                    <div class="custom-submission-log-list">
                                                                        <?php foreach ($automationLogs as $log): ?>
                                                                            <div class="custom-submission-log custom-submission-log--<?= esc((string)$log['status']); ?>">
                                                                                <span><?= esc(strtoupper((string)$log['channel'])); ?></span>
                                                                                <strong><?= esc((string)$log['status']); ?></strong>
                                                                                <small><?= esc((string)$log['message']); ?></small>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </details>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>

<script>

(function () {
    const tabs = Array.from(document.querySelectorAll('[data-form-subtab]'));
    const panels = Array.from(document.querySelectorAll('[data-form-tab-panel]'));
    const select = document.querySelector('[data-form-subtab-select]');
    const jumps = Array.from(document.querySelectorAll('[data-form-subtab-jump]'));
    if (!tabs.length || !panels.length) return;

    const aliases = {
        form: 'settings',
        setting: 'settings',
        settings: 'settings',
        pengaturan: 'settings',
        field: 'fields',
        fields: 'fields',
        pesan: 'messages',
        message: 'messages',
        messages: 'messages',
        otomatis: 'messages',
        submit: 'after-submit',
        'after-submit': 'after-submit',
        integrasi: 'integrations',
        integration: 'integrations',
        integrations: 'integrations'
    };

    function resolveTab(name) {
        const clean = String(name || '').replace(/^#/, '').replace(/^form-tab-/, '').trim();
        return aliases[clean] || clean || 'settings';
    }

    function activateTab(name, updateHash) {
        const active = resolveTab(name);
        const hasPanel = panels.some((panel) => panel.dataset.formTabPanel === active);
        const target = hasPanel ? active : 'settings';

        tabs.forEach((tab) => {
            const isActive = tab.dataset.formSubtab === target;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.formTabPanel === target;
            panel.classList.toggle('is-active', isActive);
            if (isActive) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        });

        jumps.forEach((jump) => {
            jump.classList.toggle('is-active', jump.dataset.formSubtabJump === target);
        });

        if (select) select.value = target;
        if (updateHash && window.history && window.location.hash !== '#form-tab-' + target) {
            window.history.replaceState(null, '', '#form-tab-' + target);
        }
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', function () {
            activateTab(tab.dataset.formSubtab, true);
        });
    });

    jumps.forEach((jump) => {
        jump.addEventListener('click', function () {
            activateTab(jump.dataset.formSubtabJump, true);
            const nav = document.querySelector('.custom-form-subtabs');
            if (nav) nav.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
    });

    if (select) {
        select.addEventListener('change', function () {
            activateTab(select.value, true);
        });
    }

    activateTab(resolveTab(window.location.hash || 'settings'), false);
}());

(function () {
    const list = document.querySelector('[data-field-list]');
    const addButtons = Array.from(document.querySelectorAll('[data-add-field]'));
    const templateButton = document.querySelector('[data-add-template-field]');
    const templateSelect = document.querySelector('[data-field-template-select]');
    const counter = document.querySelector('[data-field-counter]');
    const maxFields = 30;
    if (!list || addButtons.length === 0) return;

    const templates = {
        text: {label:'Field Baru', key:'field_baru', type:'text', placeholder:'Isi jawaban singkat', help:'', options:''},
        phone: {label:'Nomor WhatsApp', key:'whatsapp', type:'phone', placeholder:'08xxxxxxxxxx', help:'Pastikan nomor aktif.', options:''},
        email: {label:'Email', key:'email', type:'email', placeholder:'nama@email.com', help:'', options:''},
        textarea: {label:'Pesan', key:'pesan', type:'textarea', placeholder:'Tulis pesan atau kebutuhan Anda', help:'', options:''},
        select: {label:'Kebutuhan', key:'kebutuhan', type:'select', placeholder:'', help:'', options:'Konsultasi\nTanya Harga\nOrder\nBooking'},
        radio: {label:'Pilihan Paket', key:'pilihan_paket', type:'radio', placeholder:'', help:'', options:'Basic\nStandard\nPremium'},
        checkbox: {label:'Channel Follow-up', key:'channel_followup', type:'checkbox', placeholder:'', help:'Bisa pilih lebih dari satu.', options:'WhatsApp\nEmail\nTelepon'},
        date: {label:'Tanggal', key:'tanggal', type:'date', placeholder:'', help:'', options:''},
        number: {label:'Jumlah / Budget', key:'jumlah', type:'number', placeholder:'Contoh: 100000', help:'Isi angka saja.', options:''},
        file: {label:'Upload File', key:'file_upload', type:'file', placeholder:'', help:'Format aman: JPG, PNG, WebP, PDF, ZIP. Maksimal 5MB.', options:''}
    };

    function uniqueKey(base) {
        base = (base || 'field').toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '') || 'field';
        const existing = Array.from(list.querySelectorAll('input[name="field_key[]"]')).map(input => input.value.trim());
        let key = base;
        let counter = 2;
        while (existing.includes(key)) {
            key = base + '_' + counter;
            counter++;
        }
        return key;
    }

    function refresh() {
        const rows = Array.from(list.querySelectorAll('[data-field-row]'));
        rows.forEach((row, index) => {
            const number = row.querySelector('[data-field-number]');
            if (number) number.textContent = String(index + 1);
            const hidden = row.querySelector('input[type="hidden"][name^="field_required"]');
            const checkbox = row.querySelector('input[type="checkbox"][name^="field_required"]');
            if (hidden) hidden.name = 'field_required[' + index + ']';
            if (checkbox) checkbox.name = 'field_required[' + index + ']';
            const up = row.querySelector('[data-move-field="up"]');
            const down = row.querySelector('[data-move-field="down"]');
            if (up) up.disabled = index === 0;
            if (down) down.disabled = index === rows.length - 1;
        });
        if (counter) counter.textContent = rows.length + ' field aktif';
        addButtons.forEach((button) => { button.disabled = rows.length >= maxFields; });
        if (templateButton) templateButton.disabled = rows.length >= maxFields;
    }

    function clearRow(row) {
        row.querySelectorAll('input, textarea').forEach((input) => {
            if (input.name && input.name.indexOf('field_required') === 0) {
                if (input.type === 'checkbox') input.checked = false;
                else input.value = '0';
            } else if (input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        row.querySelectorAll('select').forEach((select) => { select.value = 'text'; });
    }

    function applyTemplate(row, templateKey) {
        const data = templates[templateKey] || templates.text;
        const label = row.querySelector('input[name="field_label[]"]');
        const key = row.querySelector('input[name="field_key[]"]');
        const type = row.querySelector('select[name="field_type[]"]');
        const placeholder = row.querySelector('input[name="field_placeholder[]"]');
        const help = row.querySelector('input[name="field_help[]"]');
        const options = row.querySelector('textarea[name="field_options[]"]');
        const required = row.querySelector('input[type="checkbox"][name^="field_required"]');
        if (label) label.value = data.label;
        if (key) key.value = uniqueKey(data.key);
        if (type) type.value = data.type;
        if (placeholder) placeholder.value = data.placeholder;
        if (help) help.value = data.help;
        if (options) options.value = data.options;
        if (required) required.checked = ['text','phone','email','select'].includes(templateKey);
    }

    function cloneRow(copyValues, templateKey) {
        const first = list.querySelector('[data-field-row]');
        if (!first) return null;
        if (list.querySelectorAll('[data-field-row]').length >= maxFields) return null;
        const clone = first.cloneNode(true);
        if (!copyValues) clearRow(clone);
        if (templateKey) applyTemplate(clone, templateKey);
        list.appendChild(clone);
        refresh();
        const firstInput = clone.querySelector('input[name="field_label[]"]');
        if (firstInput) firstInput.focus();
        clone.scrollIntoView({behavior:'smooth', block:'center'});
        return clone;
    }

    addButtons.forEach((button) => {
        button.addEventListener('click', function () {
            cloneRow(false);
        });
    });

    if (templateButton && templateSelect) {
        templateButton.addEventListener('click', function () {
            cloneRow(false, templateSelect.value || 'text');
        });
    }

    list.addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (!button) return;
        const row = button.closest('[data-field-row]');
        if (!row) return;
        const rows = Array.from(list.querySelectorAll('[data-field-row]'));
        const index = rows.indexOf(row);

        if (button.matches('[data-remove-field]')) {
            if (rows.length <= 1) {
                clearRow(row);
                refresh();
                return;
            }
            row.remove();
            refresh();
            return;
        }

        if (button.matches('[data-duplicate-field]')) {
            if (rows.length >= maxFields) return;
            const clone = row.cloneNode(true);
            row.insertAdjacentElement('afterend', clone);
            const label = clone.querySelector('input[name="field_label[]"]');
            if (label && label.value.trim() !== '') label.value = label.value.trim() + ' Copy';
            const key = clone.querySelector('input[name="field_key[]"]');
            if (key && key.value.trim() !== '') key.value = uniqueKey(key.value.trim() + '_copy');
            refresh();
            return;
        }

        if (button.dataset.moveField === 'up' && index > 0) {
            list.insertBefore(row, rows[index - 1]);
            refresh();
            return;
        }

        if (button.dataset.moveField === 'down' && index < rows.length - 1) {
            list.insertBefore(rows[index + 1], row);
            refresh();
        }
    });

    refresh();
}());


(function () {
    const siteName = <?= json_encode(SITE_NAME, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function normalizePhone(phone) {
        let cleaned = String(phone || '').replace(/\D+/g, '');
        if (cleaned.startsWith('0')) {
            cleaned = '62' + cleaned.slice(1);
        }
        return cleaned;
    }

    function renderTemplate(template, data) {
        return String(template || '').replace(/\{(nama|whatsapp|email|kebutuhan|form_name|site_name|source)\}/g, function (_, key) {
            return data[key] || '';
        });
    }

    function updateFollowup(panel) {
        const row = panel.closest('tr')?.previousElementSibling;
        if (!row || !row.matches('[data-submission-row]')) return;
        const waTextarea = panel.querySelector('[data-followup-template="wa"]');
        const emailTextarea = panel.querySelector('[data-followup-template="email"]');
        const waLink = panel.querySelector('[data-followup-wa]');
        const emailLink = panel.querySelector('[data-followup-email]');
        const data = {
            nama: row.dataset.name || 'Customer',
            whatsapp: row.dataset.phone || '',
            email: row.dataset.email || '',
            kebutuhan: row.dataset.need || '',
            form_name: row.dataset.form || 'Form Custom',
            site_name: siteName || 'Website',
            source: row.dataset.source || '',
        };
        const waMessage = renderTemplate(waTextarea ? waTextarea.value : '', data);
        const emailMessage = renderTemplate(emailTextarea ? emailTextarea.value : '', data);
        const phone = normalizePhone(data.whatsapp);
        if (waLink) {
            if (phone) {
                waLink.href = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(waMessage);
                waLink.removeAttribute('aria-disabled');
                waLink.classList.remove('is-disabled');
            } else {
                waLink.href = '#';
                waLink.setAttribute('aria-disabled', 'true');
                waLink.classList.add('is-disabled');
            }
        }
        if (emailLink) {
            if (data.email) {
                emailLink.href = 'mailto:' + encodeURIComponent(data.email) + '?subject=' + encodeURIComponent('Follow-up dari ' + (siteName || 'Website')) + '&body=' + encodeURIComponent(emailMessage);
                emailLink.removeAttribute('aria-disabled');
                emailLink.classList.remove('is-disabled');
            } else {
                emailLink.href = '#';
                emailLink.setAttribute('aria-disabled', 'true');
                emailLink.classList.add('is-disabled');
            }
        }
    }

    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('[data-toggle-followup]');
        if (toggle) {
            const target = document.getElementById(toggle.getAttribute('data-toggle-followup'));
            if (target) {
                target.hidden = !target.hidden;
                if (!target.hidden) {
                    const panel = target.querySelector('.custom-followup-panel');
                    if (panel) updateFollowup(panel);
                }
            }
        }
    });

    document.addEventListener('input', function (event) {
        const textarea = event.target.closest('[data-followup-template]');
        if (!textarea) return;
        const panel = textarea.closest('.custom-followup-panel');
        if (panel) updateFollowup(panel);
    });

    document.querySelectorAll('.custom-followup-panel').forEach(updateFollowup);
}());
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
