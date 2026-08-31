<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$result = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? 'save');
        if ($action === 'save') {
            cloud_backup_save_from_post($_POST);
            redirect_302('admin/cloud-backup-sync?message=' . rawurlencode('Pengaturan Backup & Sinkronisasi Data berhasil disimpan.'));
        }
        if ($action === 'export') {
            $result = cloud_backup_export_source((string)($_POST['source'] ?? ''), (string)($_POST['format'] ?? 'csv'), 2000);
            $message = 'Export lokal berhasil dibuat: ' . (string)($result['basename'] ?? 'file export');
        }
        if ($action === 'export_all') {
            $result = function_exists('cloud_backup_export_all_enabled') ? cloud_backup_export_all_enabled((string)($_POST['format'] ?? 'json')) : ['sources' => 0, 'rows' => 0];
            $message = 'Backup batch selesai: ' . (int)($result['sources'] ?? 0) . ' sumber, ' . (int)($result['rows'] ?? 0) . ' baris.';
        }
        if ($action === 'sync') {
            $result = cloud_backup_sync_source((string)($_POST['source'] ?? ''), 1000);
            $message = !empty($result['ok']) ? 'Data berhasil dikirim ke endpoint cloud.' : (string)($result['message'] ?? 'Sync belum berhasil.');
        }
        if ($action === 'looker_preview') {
            $previewSource = preg_replace('/[^a-zA-Z0-9_]+/', '', (string)($_POST['source'] ?? 'orders')) ?: 'orders';
            redirect_302('admin/cloud-backup-sync?preview_source=' . rawurlencode($previewSource) . '#looker-preview');
        }
        if ($action === 'looker_wizard_test') {
            $wizardSource = preg_replace('/[^a-zA-Z0-9_]+/', '', (string)($_POST['source'] ?? 'orders')) ?: 'orders';
            redirect_302('admin/cloud-backup-sync?wizard_source=' . rawurlencode($wizardSource) . '#looker-setup-wizard');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$settings = cloud_backup_settings(true);
$report = cloud_backup_report();
$syncHealth = function_exists('cloud_backup_health_report') ? cloud_backup_health_report() : ['score' => 0, 'overall' => 'info', 'checks' => [], 'history' => []];
$syncHistory = function_exists('cloud_backup_sync_history') ? cloud_backup_sync_history(6) : [];
$sources = cloud_backup_source_rows();
$appsScriptCode = cloud_backup_apps_script_code();
$lookerDirectCode = function_exists('looker_studio_connector_code') ? looker_studio_connector_code() : '';
$lookerReport = function_exists('looker_studio_report') ? looker_studio_report() : [];
$lookerPreviewSource = preg_replace('/[^a-zA-Z0-9_]+/', '', (string)($_GET['preview_source'] ?? 'orders')) ?: 'orders';
$lookerPreview = function_exists('looker_studio_source_preview') ? looker_studio_source_preview($lookerPreviewSource, 5) : [];
$lookerReadiness = function_exists('looker_studio_visual_readiness') ? looker_studio_visual_readiness() : [];
$lookerBlueprints = function_exists('looker_studio_dashboard_blueprints') ? looker_studio_dashboard_blueprints() : [];
$lookerWizardSource = preg_replace('/[^a-zA-Z0-9_]+/', '', (string)($_GET['wizard_source'] ?? 'orders')) ?: 'orders';
$lookerWizard = function_exists('looker_studio_setup_wizard_readiness') ? looker_studio_setup_wizard_readiness() : ['steps' => [], 'score' => 0, 'ready' => 0, 'total' => 0, 'next_action' => 'Lengkapi pengaturan Looker Studio.'];
$lookerWizardTest = function_exists('looker_studio_setup_wizard_source_test') ? looker_studio_setup_wizard_source_test($lookerWizardSource) : [];
$lookerWizardUrls = function_exists('looker_studio_setup_wizard_test_urls') ? looker_studio_setup_wizard_test_urls($lookerWizardSource) : [];
$lookerDashboardChecklist = function_exists('looker_studio_setup_wizard_dashboard_checklist') ? looker_studio_setup_wizard_dashboard_checklist() : [];
$lookerTemplatePack = function_exists('looker_studio_dashboard_template_pack') ? looker_studio_dashboard_template_pack() : [];
$lookerTemplateSummary = function_exists('looker_studio_dashboard_template_summary') ? looker_studio_dashboard_template_summary() : ['dashboards' => 0, 'sources' => 0, 'charts' => 0, 'scorecards' => 0];
$lookerTemplateMatrix = function_exists('looker_studio_dashboard_template_sheet_matrix') ? looker_studio_dashboard_template_sheet_matrix() : [];
$lookerTemplateReadiness = function_exists('looker_studio_dashboard_template_readiness') ? looker_studio_dashboard_template_readiness() : ['score' => 0, 'ready' => 0, 'total' => 0, 'checks' => []];
$setupSteps = cloud_backup_setup_steps();

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Backup & Sinkronisasi Data - Admin',
    'description' => 'Backup data lead, order, analytics, dan member ke Google Sheets, Drive, dan dashboard visual.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="admin-content" class="admin-shell admin-cloud-backup-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Sistem</div>
                <h1>Backup & Sinkronisasi Data</h1>
                <p>Siapkan export data U-Growth ke Google Sheets, Google Drive, dan Looker Studio tanpa mengubah alur website yang sudah stabil.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/storage-database')); ?>">Storage & Database</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/data-migration')); ?>">Migrasi Data MySQL</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/release-audit')); ?>">Audit Kesiapan</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Status</span><h2><?= !empty($report['enabled']) ? 'Aktif' : 'Manual'; ?></h2><p><?= !empty($report['enabled']) ? 'Sync bisa dijalankan sesuai jadwal/server cron.' : 'Belum otomatis. Export manual tetap bisa dipakai.'; ?></p></div>
                <div class="admin-card"><span class="admin-badge">Endpoint</span><h2><?= !empty($report['endpoint_ready']) ? 'Siap' : 'Belum'; ?></h2><p>Apps Script URL: <?= esc(cloud_backup_mask((string)($settings['apps_script_url'] ?? ''))); ?></p></div>
                <div class="admin-card"><span class="admin-badge">Spreadsheet</span><h2><?= !empty($report['spreadsheet_ready']) ? 'Terhubung' : 'Belum'; ?></h2><p>ID: <?= esc(cloud_backup_mask((string)($settings['spreadsheet_id'] ?? ''))); ?></p></div>
                <div class="admin-card"><span class="admin-badge">Reliability</span><h2><?= (int)($report['sync_health_score'] ?? 0); ?>%</h2><p>Status: <?= esc(strtoupper((string)($syncHealth['overall'] ?? 'info'))); ?>.</p></div>
                <div class="admin-card"><span class="admin-badge">Data Terbaca</span><h2><?= (int)($report['total_records'] ?? 0); ?></h2><p>Total record dari sumber data yang dipetakan.</p></div>
            </div>

            <form method="post" class="admin-card admin-editor admin-cloud-backup-card">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="save">
                <div class="admin-form-head">
                    <span class="admin-badge">Pengaturan Aman</span>
                    <h2>Koneksi Google Sheets / Drive</h2>
                    <p>Gunakan Web App Google Apps Script sebagai jembatan ringan. Secret tidak ikut source ZIP dan bisa disimpan di <code>.env</code> atau form ini.</p>
                </div>

                <div class="admin-form-grid admin-form-row--2">
                    <label class="admin-toggle-option"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan sinkronisasi otomatis jika cron/server sudah disiapkan</label>
                    <label>Jadwal sync
                        <select name="frequency">
                            <?php foreach (['manual' => 'Manual dulu', 'hourly' => 'Per jam', 'daily' => 'Harian', 'weekly' => 'Mingguan'] as $key => $label): ?>
                                <option value="<?= esc($key); ?>" <?= ((string)($settings['frequency'] ?? 'manual') === $key) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="admin-form-grid admin-form-row--2">
                    <label class="admin-toggle-option"><input type="checkbox" name="destination_google_sheets" value="1" <?= !empty($settings['destination_google_sheets']) ? 'checked' : ''; ?>> Kirim data tabular ke Google Sheets</label>
                    <label class="admin-toggle-option"><input type="checkbox" name="destination_google_drive" value="1" <?= !empty($settings['destination_google_drive']) ? 'checked' : ''; ?>> Simpan arsip export ke Google Drive</label>
                </div>

                <div class="admin-form-grid admin-form-row--2">
                    <label>Google Apps Script Web App URL
                        <input type="url" name="apps_script_url" value="<?= esc((string)($settings['apps_script_url'] ?? '')); ?>" placeholder="https://script.google.com/macros/s/.../exec">
                        <small>Endpoint HTTPS yang menerima payload JSON dari website.</small>
                    </label>
                    <label>Token sinkronisasi
                        <input type="password" name="apps_script_token" value="<?= esc((string)($settings['apps_script_token'] ?? '')); ?>" autocomplete="new-password" placeholder="Token rahasia">
                        <small>Token ini dikirim sebagai header <code>X-Ugrowth-Token</code>.</small>
                    </label>
                </div>

                <div class="admin-form-grid admin-form-row--2">
                    <label>Google Spreadsheet ID
                        <input type="text" name="spreadsheet_id" value="<?= esc((string)($settings['spreadsheet_id'] ?? '')); ?>" placeholder="ID Spreadsheet tujuan">
                    </label>
                    <label>Google Drive Folder ID
                        <input type="text" name="drive_folder_id" value="<?= esc((string)($settings['drive_folder_id'] ?? '')); ?>" placeholder="Opsional: folder backup Drive">
                    </label>
                </div>

                <div class="admin-form-grid admin-form-row--2">
                    <label class="admin-toggle-option"><input type="checkbox" name="looker_direct_enabled" value="1" <?= !empty($settings['looker_direct_enabled']) ? 'checked' : ''; ?>> Aktifkan koneksi langsung Looker Studio</label>
                    <label>Token Looker Studio Direct
                        <input type="password" name="looker_connector_token" value="<?= esc((string)($settings['looker_connector_token'] ?? '')); ?>" autocomplete="new-password" placeholder="Token khusus Looker Studio">
                        <small>Token ini dipakai oleh Community Connector saat membaca endpoint U-Growth langsung.</small>
                    </label>
                </div>

                <div class="admin-form-grid admin-form-row--2">
                    <label>Mode tulis sheet
                        <select name="sync_mode">
                            <?php foreach (['replace' => 'Replace / bersihkan lalu tulis ulang', 'append' => 'Append / tambah baris baru'] as $key => $label): ?>
                                <option value="<?= esc($key); ?>" <?= ((string)($settings['sync_mode'] ?? 'replace') === $key) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Replace lebih aman untuk dashboard karena mengurangi duplikat.</small>
                    </label>
                    <label>Maksimal baris per sync
                        <input type="number" name="max_sync_rows" min="1" max="10000" value="<?= (int)($settings['max_sync_rows'] ?? 1000); ?>">
                        <small>Batasi payload agar shared hosting dan Apps Script tetap stabil.</small>
                    </label>
                </div>

                <div class="admin-form-grid admin-form-row--2">
                    <label>Retry sync
                        <input type="number" name="retry_attempts" min="0" max="3" value="<?= (int)($settings['retry_attempts'] ?? 1); ?>">
                        <small>Jika endpoint sempat gagal, sistem mencoba ulang singkat.</small>
                    </label>
                    <label>Timeout sync per percobaan
                        <input type="number" name="sync_timeout_seconds" min="5" max="60" value="<?= (int)($settings['sync_timeout_seconds'] ?? 15); ?>">
                        <small>Disarankan 15–30 detik.</small>
                    </label>
                </div>

                <label class="admin-toggle-option" style="margin-top:10px"><input type="checkbox" name="sync_log_enabled" value="1" <?= !empty($settings['sync_log_enabled']) ? 'checked' : ''; ?>> Catat riwayat sinkronisasi ke log privat</label>

                <div class="admin-table-wrap admin-table-wrap--comfortable" style="margin-top:18px">
                    <table class="admin-table">
                        <thead><tr><th>Aktif</th><th>Sumber Data</th><th>Nama Sheet</th><th>Record</th><th>Status</th><th>Health</th><th>Catatan</th></tr></thead>
                        <tbody>
                        <?php foreach ($sources as $row): ?>
                            <?php $key = (string)($row['key'] ?? ''); ?>
                            <tr>
                                <td><label class="admin-toggle-option" style="justify-content:center"><input type="checkbox" name="sources[]" value="<?= esc($key); ?>" <?= !empty($row['enabled']) ? 'checked' : ''; ?>></label></td>
                                <td><strong><?= esc((string)($row['label'] ?? $key)); ?></strong><?= !empty($row['recommended']) ? '<br><small>Direkomendasikan untuk dashboard owner.</small>' : ''; ?></td>
                                <td><input class="admin-sheet-name-input" type="text" name="sheet_names[<?= esc($key); ?>]" value="<?= esc((string)($row['sheet_name'] ?? $key)); ?>"></td>
                                <td><?= (int)($row['records'] ?? 0); ?></td>
                                <td><span class="admin-status-pill admin-status-pill--<?= (string)($row['last_status'] ?? '') === 'sync_failed' ? 'error' : (!empty($row['last_status']) ? 'ok' : 'info'); ?>"><?= esc((string)($row['last_status'] ?: 'Belum sync')); ?></span><br><small><?= !empty($row['last_sync_rows']) ? (int)$row['last_sync_rows'] . ' baris sync terakhir' : ''; ?></small></td>
                                <?php $rowHealth = function_exists('cloud_backup_source_health') ? cloud_backup_source_health($row) : ['status' => 'info', 'message' => '']; ?>
                                <td><span class="admin-status-pill admin-status-pill--<?= esc((string)($rowHealth['status'] ?? 'info')); ?>"><?= esc(strtoupper((string)($rowHealth['status'] ?? 'info'))); ?></span><br><small><?= esc((string)($rowHealth['message'] ?? '')); ?></small></td>
                                <td><?= esc((string)($row['note'] ?? '')); ?><?php if (!empty($row['last_error'])): ?><br><small>Error: <?= esc((string)$row['last_error']); ?></small><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-form-actions" style="margin-top:18px">
                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Pengaturan Backup</button>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cloud-backup-sync')); ?>">Refresh Data</a>
                </div>
            </form>

            <section class="admin-grid admin-grid--2">
                <article class="admin-card">
                    <span class="admin-badge">Export Lokal</span>
                    <h2>Buat file CSV/JSON untuk backup</h2>
                    <p>Export lokal aman dipakai meskipun Google endpoint belum siap. File hasil export berada di storage privat.</p>
                    <form method="post" class="admin-form-grid admin-form-row--2">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="export">
                        <label>Sumber data
                            <select name="source">
                                <?php foreach ($sources as $row): ?><option value="<?= esc((string)$row['key']); ?>"><?= esc((string)$row['label']); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>Format
                            <select name="format"><option value="csv">CSV</option><option value="json">JSON</option></select>
                        </label>
                        <div class="admin-form-actions admin-form-actions--inline"><button class="admin-btn admin-btn--primary" type="submit">Buat Export</button></div>
                    </form>
                </article>

                <article class="admin-card">
                    <span class="admin-badge">Backup Batch</span>
                    <h2>Export semua sumber aktif</h2>
                    <p>Cocok sebelum update besar atau sebelum mengaktifkan MySQL/Looker. Sistem membuat file backup lokal untuk semua sumber yang dicentang.</p>
                    <form method="post" class="admin-form-grid admin-form-row--2">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="export_all">
                        <label>Format
                            <select name="format"><option value="json">JSON</option><option value="csv">CSV</option></select>
                        </label>
                        <div class="admin-form-actions admin-form-actions--inline"><button class="admin-btn admin-btn--primary" type="submit">Backup Semua Aktif</button></div>
                    </form>
                </article>

                <article class="admin-card">
                    <span class="admin-badge">Sync Cloud</span>
                    <h2>Kirim data ke Google Apps Script</h2>
                    <p>Gunakan setelah Web App Apps Script disiapkan. Jika belum siap, sistem akan memberi pesan aman tanpa merusak data.</p>
                    <form method="post" class="admin-form-grid admin-form-row--2">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="sync">
                        <label>Sumber data
                            <select name="source">
                                <?php foreach ($sources as $row): ?><option value="<?= esc((string)$row['key']); ?>"><?= esc((string)$row['label']); ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <div class="admin-form-actions admin-form-actions--inline"><button class="admin-btn admin-btn--primary" type="submit" <?= empty($report['endpoint_ready']) ? 'disabled' : ''; ?>>Kirim ke Cloud</button></div>
                    </form>
                    <?php if (empty($report['endpoint_ready'])): ?><div class="admin-alert admin-alert--info" style="margin-top:14px">Isi Apps Script URL dulu untuk mengaktifkan tombol sync cloud.</div><?php endif; ?>
                </article>
            </section>

            <section class="admin-card">
                <div class="admin-form-head admin-form-head--split">
                    <div><span class="admin-badge">Data Health & Sync Reliability</span><h2>Kesehatan backup dan sinkronisasi</h2><p>Ringkasan ini membantu admin melihat endpoint, token, Apps Script, sumber data kosong, dan kegagalan sync terakhir.</p></div>
                    <div><strong><?= (int)($syncHealth['score'] ?? 0); ?>%</strong><br><small>Skor reliability</small></div>
                </div>
                <div class="admin-table-wrap admin-table-wrap--comfortable">
                    <table class="admin-table">
                        <thead><tr><th>Status</th><th>Check</th><th>Pesan</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice((array)($syncHealth['checks'] ?? []), 0, 12) as $check): ?>
                            <tr><td><span class="admin-status-pill admin-status-pill--<?= esc((string)($check['status'] ?? 'info')); ?>"><?= esc(strtoupper((string)($check['status'] ?? 'info'))); ?></span></td><td><strong><?= esc((string)($check['label'] ?? '-')); ?></strong></td><td><?= esc((string)($check['message'] ?? '-')); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($syncHistory): ?>
                    <h3 style="margin-top:18px">Riwayat Sync Terakhir</h3>
                    <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Waktu</th><th>Event</th><th>Sumber</th><th>Rows</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($syncHistory as $history): ?>
                        <tr><td><?= esc((string)($history['logged_at'] ?? '-')); ?></td><td><?= esc((string)($history['event'] ?? '-')); ?></td><td><?= esc((string)($history['source'] ?? '-')); ?></td><td><?= (int)($history['rows'] ?? 0); ?></td><td><?= !empty($history['ok']) ? 'OK' : 'Gagal'; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody></table></div>
                <?php endif; ?>
            </section>

            <section id="looker-setup-wizard" class="admin-card admin-looker-wizard-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Looker Studio Setup Wizard</span>
                    <h2>Panduan koneksi visual dashboard</h2>
                    <p>Ikuti checklist ini agar data U-Growth bisa dibaca Looker Studio dengan rapi, baik lewat Google Sheets maupun koneksi langsung.</p>
                </div>

                <div class="admin-grid admin-grid--stats admin-looker-wizard-stats">
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Skor Setup</span><h2><?= (int)($lookerWizard['score'] ?? 0); ?>%</h2><p><?= (int)($lookerWizard['ready'] ?? 0); ?>/<?= (int)($lookerWizard['total'] ?? 0); ?> langkah otomatis siap.</p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Source Test</span><h2><?= esc((string)($lookerWizardTest['label'] ?? $lookerWizardSource)); ?></h2><p><?= (int)($lookerWizardTest['records'] ?? 0); ?> record, <?= (int)($lookerWizardTest['fields'] ?? 0); ?> field.</p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Koneksi</span><h2><?= !empty($lookerReport['enabled']) ? 'Aktif' : 'Belum'; ?></h2><p><?= !empty($lookerReport['token_ready']) ? 'Token siap.' : 'Token belum diisi.'; ?></p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Aksi Berikutnya</span><h2>Next</h2><p><?= esc((string)($lookerWizard['next_action'] ?? 'Cek setup connector.')); ?></p></div>
                </div>

                <div class="admin-looker-step-list">
                    <?php foreach ((array)($lookerWizard['steps'] ?? []) as $step): ?>
                        <?php
                            $status = (string)($step['status'] ?? 'todo');
                            $statusLabel = match ($status) {
                                'ready' => 'Siap',
                                'warning' => 'Perlu cek',
                                'manual' => 'Manual',
                                default => 'Belum',
                            };
                        ?>
                        <article class="admin-card admin-card--subtle admin-looker-step admin-looker-step--<?= esc($status); ?>">
                            <div class="admin-looker-step__head">
                                <span class="admin-status-pill admin-status-pill--<?= $status === 'ready' ? 'ok' : ($status === 'manual' ? 'info' : 'warning'); ?>"><?= esc($statusLabel); ?></span>
                                <strong><?= esc((string)($step['title'] ?? 'Langkah Setup')); ?></strong>
                            </div>
                            <p><?= esc((string)($step['summary'] ?? '')); ?></p>
                            <?php if (!empty($step['detail'])): ?><small><?= esc((string)$step['detail']); ?></small><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="admin-grid admin-grid--2 admin-looker-test-grid">
                    <article class="admin-card admin-card--subtle">
                        <h3>Tes source data</h3>
                        <p>Pilih source yang akan dipakai sebagai data source pertama di Looker Studio. Sistem akan mengecek schema, jumlah data, dan rekomendasi chart.</p>
                        <form method="post" class="admin-form-grid admin-form-row--2">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="looker_wizard_test">
                            <label>Source untuk dites
                                <select name="source">
                                    <?php foreach ((array)($lookerReport['sources'] ?? []) as $sourceRow): ?>
                                        <?php $key = (string)($sourceRow['key'] ?? ''); ?>
                                        <option value="<?= esc($key); ?>" <?= $key === $lookerWizardSource ? 'selected' : ''; ?>><?= esc((string)($sourceRow['label'] ?? $key)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <div class="admin-form-actions admin-form-actions--inline"><button class="admin-btn admin-btn--primary" type="submit">Tes Source</button></div>
                        </form>
                        <div class="admin-alert <?= !empty($lookerWizardTest['ok']) ? 'admin-alert--success' : 'admin-alert--info'; ?>" style="margin-top:14px"><?= esc((string)($lookerWizardTest['message'] ?? 'Pilih source untuk dites.')); ?></div>
                        <div class="admin-chip-list" style="margin-top:12px">
                            <?php foreach ((array)($lookerWizardTest['recommended_charts'] ?? []) as $chart): ?>
                                <span class="admin-chip"><?= esc((string)$chart); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="admin-card admin-card--subtle">
                        <h3>URL test API</h3>
                        <p>Gunakan URL ini hanya untuk pengujian koneksi. Ganti <code>TOKEN_ANDA</code> dengan token connector di browser/tab privat atau Apps Script.</p>
                        <div class="admin-code-stack admin-api-url-stack">
                            <?php foreach ($lookerWizardUrls as $label => $testUrl): ?>
                                <label class="admin-code-label admin-code-label--compact admin-api-url-field"><span><?= esc(ucfirst((string)$label)); ?></span>
                                    <input type="text" readonly value="<?= esc((string)$testUrl); ?>" aria-label="<?= esc(ucfirst((string)$label)); ?> URL">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </article>
                </div>

                <div class="admin-card admin-card--subtle admin-looker-dashboard-checklist">
                    <h3>Checklist dashboard yang disarankan</h3>
                    <div class="admin-grid admin-grid--3">
                        <?php foreach ($lookerDashboardChecklist as $dashboardTitle => $items): ?>
                            <article class="admin-card admin-card--subtle">
                                <span class="admin-badge"><?= esc((string)$dashboardTitle); ?></span>
                                <ul class="admin-mini-list">
                                    <?php foreach ((array)$items as $item): ?><li><?= esc((string)$item); ?></li><?php endforeach; ?>
                                </ul>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section id="looker-preview" class="admin-card admin-cloud-preview-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Looker Studio Data Preview</span>
                    <h2>Preview data sebelum divisualisasikan</h2>
                    <p>Lihat schema, contoh baris, dan rekomendasi grafik sebelum data dipakai di Google Sheets atau Looker Studio.</p>
                </div>

                <div class="admin-grid admin-grid--stats">
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Sumber Siap</span><h2><?= (int)($lookerReadiness['ready_sources'] ?? 0); ?>/<?= (int)($lookerReadiness['total_sources'] ?? 0); ?></h2><p>Source punya schema visual yang stabil.</p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Dashboard Pack</span><h2><?= count($lookerBlueprints); ?></h2><p>Blueprint dashboard untuk owner, sales, SEO, campaign, dan member.</p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Source Terpilih</span><h2><?= esc((string)($lookerPreview['label'] ?? $lookerPreviewSource)); ?></h2><p><?= (int)($lookerPreview['records'] ?? 0); ?> record terbaca.</p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Field</span><h2><?= count((array)($lookerPreview['schema'] ?? [])); ?></h2><p>Field siap dipakai sebagai dimensi/metric.</p></div>
                </div>

                <form method="post" class="admin-form-grid admin-form-row--2" style="margin-top:18px">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="looker_preview">
                    <label>Pilih sumber data preview
                        <select name="source">
                            <?php foreach ((array)($lookerReport['sources'] ?? []) as $sourceRow): ?>
                                <?php $key = (string)($sourceRow['key'] ?? ''); ?>
                                <option value="<?= esc($key); ?>" <?= $key === $lookerPreviewSource ? 'selected' : ''; ?>><?= esc((string)($sourceRow['label'] ?? $key)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="admin-form-actions admin-form-actions--inline"><button class="admin-btn admin-btn--primary" type="submit">Lihat Preview Data</button></div>
                </form>

                <div class="admin-grid admin-grid--2" style="margin-top:18px">
                    <article class="admin-card admin-card--subtle">
                        <h3>Field utama</h3>
                        <div class="admin-chip-list">
                            <?php foreach (array_slice((array)($lookerPreview['schema'] ?? []), 0, 18) as $field): ?>
                                <span class="admin-chip"><?= esc((string)($field['label'] ?? $field['name'] ?? 'Field')); ?> · <?= esc((string)($field['type'] ?? 'TEXT')); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                    <article class="admin-card admin-card--subtle">
                        <h3>Rekomendasi visual</h3>
                        <ul class="admin-check-list">
                            <?php foreach ((array)($lookerPreview['recommended_charts'] ?? []) as $chart): ?>
                                <li><?= esc((string)$chart); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                </div>

                <div class="admin-table-wrap admin-table-wrap--comfortable" style="margin-top:18px">
                    <table class="admin-table admin-table--compact">
                        <thead>
                            <tr>
                                <?php foreach (array_slice((array)($lookerPreview['schema'] ?? []), 0, 8) as $field): ?>
                                    <th><?= esc((string)($field['label'] ?? $field['name'] ?? 'Field')); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ((array)($lookerPreview['rows'] ?? []) as $row): ?>
                            <tr>
                                <?php foreach (array_slice((array)($lookerPreview['schema'] ?? []), 0, 8) as $field): ?>
                                    <?php $fieldName = (string)($field['name'] ?? ''); ?>
                                    <td><?= esc((string)($row[$fieldName] ?? '')); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($lookerPreview['rows'])): ?>
                            <tr><td colspan="8">Data belum ada. Schema tetap siap, nanti chart akan terisi setelah lead/order/event masuk.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="admin-card admin-dashboard-pack-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Dashboard Pack</span>
                    <h2>Blueprint dashboard visual untuk scale up bisnis</h2>
                    <p>Paket ini membantu owner membaca data tersebar dari U-Growth menjadi dashboard keputusan: lead, order, SEO profit, CTA, campaign, dan member access.</p>
                </div>
                <div class="admin-grid admin-grid--3">
                    <?php foreach ($lookerBlueprints as $blueprint): ?>
                        <article class="admin-card admin-card--subtle">
                            <span class="admin-badge"><?= esc((string)($blueprint['title'] ?? 'Dashboard')); ?></span>
                            <p><?= esc((string)($blueprint['goal'] ?? '')); ?></p>
                            <h4>Card utama</h4>
                            <div class="admin-chip-list admin-chip-list--compact">
                                <?php foreach ((array)($blueprint['cards'] ?? []) as $card): ?><span class="admin-chip"><?= esc((string)$card); ?></span><?php endforeach; ?>
                            </div>
                            <h4>Chart disarankan</h4>
                            <ul class="admin-mini-list">
                                <?php foreach ((array)($blueprint['charts'] ?? []) as $chart): ?><li><?= esc((string)$chart); ?></li><?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section id="dashboard-template-pack" class="admin-card admin-dashboard-template-pack-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Dashboard Template Pack</span>
                    <h2>Template dashboard siap disusun di Looker Studio</h2>
                    <p>Gunakan pack ini sebagai panduan visual. Admin bisa melihat dashboard apa saja yang perlu dibuat, sumber data yang dipakai, scorecard, chart, dan pertanyaan bisnis yang dijawab.</p>
                </div>

                <div class="admin-grid admin-grid--stats">
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Dashboard</span><h2><?= (int)($lookerTemplateSummary['dashboards'] ?? 0); ?></h2><p>Blueprint dashboard owner dan tim.</p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Source</span><h2><?= (int)($lookerTemplateSummary['sources'] ?? 0); ?></h2><p>Sumber data yang dipakai template.</p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Visual</span><h2><?= (int)($lookerTemplateSummary['charts'] ?? 0); ?></h2><p>Chart rekomendasi siap dibuat.</p></div>
                    <div class="admin-card admin-card--subtle"><span class="admin-badge">Kesiapan</span><h2><?= (int)($lookerTemplateReadiness['score'] ?? 0); ?>%</h2><p><?= (int)($lookerTemplateReadiness['ready'] ?? 0); ?>/<?= (int)($lookerTemplateReadiness['total'] ?? 0); ?> check template siap.</p></div>
                </div>

                <div class="admin-grid admin-grid--3 admin-dashboard-template-grid">
                    <?php foreach ($lookerTemplatePack as $dashboardKey => $dashboard): ?>
                        <article class="admin-card admin-card--subtle admin-dashboard-template-card">
                            <span class="admin-badge"><?= esc((string)($dashboard['audience'] ?? 'Dashboard')); ?></span>
                            <h3><?= esc((string)($dashboard['title'] ?? $dashboardKey)); ?></h3>
                            <p><?= esc((string)($dashboard['goal'] ?? '')); ?></p>
                            <div class="admin-mini-meta"><strong>Source utama:</strong> <?= esc((string)($dashboard['primary_source'] ?? '-')); ?></div>
                            <h4>Scorecard</h4>
                            <div class="admin-chip-list admin-chip-list--compact">
                                <?php foreach ((array)($dashboard['scorecards'] ?? []) as $scorecard): ?><span class="admin-chip"><?= esc((string)($scorecard['label'] ?? 'Metric')); ?></span><?php endforeach; ?>
                            </div>
                            <h4>Chart</h4>
                            <ul class="admin-mini-list">
                                <?php foreach (array_slice((array)($dashboard['charts'] ?? []), 0, 3) as $chart): ?><li><?= esc((string)($chart['title'] ?? 'Chart')); ?> <small>(<?= esc((string)($chart['type'] ?? 'visual')); ?>)</small></li><?php endforeach; ?>
                            </ul>
                            <h4>Pertanyaan bisnis</h4>
                            <ul class="admin-mini-list">
                                <?php foreach (array_slice((array)($dashboard['decision_questions'] ?? []), 0, 2) as $question): ?><li><?= esc((string)$question); ?></li><?php endforeach; ?>
                            </ul>
                        </article>
                    <?php endforeach; ?>
                </div>

                <details class="admin-card admin-card--subtle admin-dashboard-template-matrix">
                    <summary><strong>Lihat matrix source, sheet, dan field</strong></summary>
                    <div class="admin-table-wrap admin-table-wrap--comfortable" style="margin-top:14px">
                        <table class="admin-table">
                            <thead><tr><th>Source</th><th>Sheet</th><th>Field</th><th>Record</th><th>Kolom utama</th></tr></thead>
                            <tbody>
                            <?php foreach ($lookerTemplateMatrix as $row): ?>
                                <tr>
                                    <td><strong><?= esc((string)($row['label'] ?? $row['source'] ?? 'Data')); ?></strong><br><small><?= esc((string)($row['source'] ?? '')); ?></small></td>
                                    <td><code><?= esc((string)($row['sheet_name'] ?? '')); ?></code></td>
                                    <td><?= (int)($row['field_count'] ?? 0); ?></td>
                                    <td><?= (int)($row['records'] ?? 0); ?></td>
                                    <td><?= esc(implode(', ', array_slice((array)($row['fields'] ?? []), 0, 8))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </section>

            <section class="admin-card admin-cloud-script-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Apps Script Plug & Play</span>
                    <h2>Kode connector siap salin-tempel</h2>
                    <p>Tempel kode ini di Google Apps Script. Connector akan membuat sheet standar, menerima payload U-Growth, mengatur header otomatis, dan menyiapkan tab panduan dashboard agar admin tidak mencocokkan tabel manual.</p>
                </div>
                <div class="admin-grid admin-grid--2 admin-cloud-script-guide">
                    <div>
                        <h3>Langkah cepat</h3>
                        <ol class="admin-steps-list">
                            <?php foreach ($setupSteps as $step): ?><li><?= esc((string)$step); ?></li><?php endforeach; ?>
                        </ol>
                        <div class="admin-alert admin-alert--info">Di Apps Script, buka <strong>Project Settings → Script Properties</strong>, lalu isi <code>SYNC_TOKEN</code>. Nilainya harus sama dengan token di pengaturan U-Growth.</div>
                    </div>
                    <div>
                        <h3>Sheet yang otomatis disiapkan</h3>
                        <div class="admin-chip-list">
                            <?php foreach (array_slice($lookerTemplateMatrix, 0, 18) as $sheetRow): ?>
                                <span class="admin-chip"><?= esc((string)($sheetRow['sheet_name'] ?? $sheetRow['source'] ?? 'data')); ?></span>
                            <?php endforeach; ?>
                            <span class="admin-chip">_dashboard_guide</span>
                            <span class="admin-chip">_field_dictionary</span>
                            <span class="admin-chip">_chart_blueprint</span>
                        </div>
                        <p class="admin-muted">Mode sync default adalah <strong>replace snapshot</strong>, jadi sheet tetap bersih dan tidak dobel saat data dikirim ulang.</p>
                    </div>
                </div>
                <label class="admin-code-label">Kode Google Apps Script
                    <textarea class="admin-code-textarea" rows="18" readonly><?= esc($appsScriptCode); ?></textarea>
                    <small>Salin semua kode di atas, lalu tempel ke file Apps Script. Kode tidak berisi token rahasia.</small>
                </label>
            </section>

            <section class="admin-card admin-cloud-script-card">
                <div class="admin-form-head">
                    <span class="admin-badge">Looker Studio Direct</span>
                    <h2>Koneksi langsung dari U-Growth ke Looker Studio</h2>
                    <p>Selain lewat Google Sheets, U-Growth juga menyiapkan Community Connector agar Looker Studio bisa membaca data langsung dari endpoint website dengan token aman.</p>
                </div>
                <div class="admin-grid admin-grid--2 admin-cloud-script-guide">
                    <div>
                        <h3>Endpoint dan status</h3>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <tbody>
                                    <tr><th>API URL</th><td><code><?= esc((string)($lookerReport['api_url'] ?? url('api/looker-studio-data'))); ?></code></td></tr>
                                    <tr><th>Status koneksi langsung</th><td><?= !empty($lookerReport['enabled']) ? 'Aktif' : 'Belum aktif'; ?></td></tr>
                                    <tr><th>Token</th><td><?= !empty($lookerReport['token_ready']) ? 'Siap' : 'Belum diisi'; ?></td></tr>
                                    <tr><th>Connector</th><td><?= !empty($lookerReport['connector_ready']) ? 'Siap salin' : 'Belum siap'; ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="admin-alert admin-alert--info" style="margin-top:14px">Untuk cara paling mudah, gunakan Google Sheets sebagai jembatan. Untuk dashboard yang ingin membaca data langsung dari website, gunakan connector di bawah.</div>
                    </div>
                    <div>
                        <h3>Sumber data visual</h3>
                        <div class="admin-chip-list">
                            <?php foreach ((array)($lookerReport['sources'] ?? []) as $sourceRow): ?>
                                <span class="admin-chip"><?= esc((string)($sourceRow['label'] ?? $sourceRow['key'] ?? 'Data')); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <label class="admin-code-label">Kode Looker Studio Community Connector
                    <textarea class="admin-code-textarea" rows="18" readonly><?= esc($lookerDirectCode); ?></textarea>
                    <small>Salin kode ini ke Apps Script Community Connector. Kode tidak berisi token rahasia.</small>
                </label>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
