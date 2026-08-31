<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$message = '';
$error = '';

function admin_analytics_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_analytics_range(): string
{
    $range = strtolower(trim((string)($_GET['range'] ?? '30')));
    $allowed = ['7', '14', '30', '60', '90', '180', '365', 'all'];
    return in_array($range, $allowed, true) ? $range : '30';
}

function admin_analytics_days(): int
{
    $range = admin_analytics_range();
    return $range === 'all' ? 0 : max(1, min(3650, (int)$range));
}

function admin_analytics_current_url(array $extra = []): string
{
    $query = array_merge(['range' => admin_analytics_range()], $extra);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== false);
    return url('admin/analytics' . ($query ? '?' . http_build_query($query) : ''));
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/analytics');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_analytics_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'analytics']);
            }
            redirect_302('admin/analytics');
        }
        $error = 'Password admin salah.';
    } elseif (($_POST['form_action'] ?? '') === 'save_analytics_settings') {
        $settings = analytics_settings_from_post($_POST);
        if (analytics_write_settings($settings)) {
            if (function_exists('activity_log_record')) {
                activity_log_record('update', 'analytics', null, 'Analytics settings diperbarui.', [
                    'gtm' => (string)($settings['gtm']['container_id'] ?? ''),
                    'ga4' => (string)($settings['ga4']['measurement_id'] ?? ''),
                    'has_gsc' => !empty($settings['gsc']['verification']),
                ]);
            }
            redirect_302('admin/analytics?saved=1');
        }
        $error = 'Pengaturan analytics belum bisa disimpan. Pastikan folder storage bisa ditulis server.';
    } elseif (($_POST['form_action'] ?? '') === 'save_server_conversion_settings') {
        $current = function_exists('server_conversion_read_settings') ? server_conversion_read_settings() : [];
        $settings = function_exists('server_conversion_settings_from_post') ? server_conversion_settings_from_post($_POST, $current) : [];
        if (function_exists('server_conversion_write_settings') && server_conversion_write_settings($settings)) {
            if (function_exists('activity_log_record')) {
                activity_log_record('update', 'server_conversion', null, 'Server-side conversion settings diperbarui.', [
                    'enabled' => !empty($settings['enabled']),
                    'meta' => !empty($settings['meta']['enabled']),
                    'tiktok' => !empty($settings['tiktok']['enabled']),
                    'google_ads' => !empty($settings['google_ads']['enabled']),
                ]);
            }
            redirect_302('admin/analytics?server_saved=1');
        }
        $error = 'Pengaturan pengiriman data belum bisa disimpan. Pastikan folder storage bisa ditulis server.';
    } elseif (($_POST['form_action'] ?? '') === 'server_conversion_test_enqueue') {
        $ok = function_exists('server_conversion_enqueue_test_event') ? server_conversion_enqueue_test_event() : false;
        redirect_302('admin/analytics?' . ($ok ? 'server_test=1' : 'server_test=0'));
    } elseif (($_POST['form_action'] ?? '') === 'server_conversion_send_pending') {
        $limit = max(1, min(100, (int)($_POST['server_send_limit'] ?? ((function_exists('server_conversion_read_settings') ? (server_conversion_read_settings()['max_events_per_run'] ?? 20) : 20)))));
        $result = function_exists('server_conversion_process_pending') ? server_conversion_process_pending($limit, false) : ['processed' => 0, 'sent' => 0, 'failed' => 0];
        redirect_302('admin/analytics?server_sent=' . (int)($result['sent'] ?? 0) . '&server_failed=' . (int)($result['failed'] ?? 0) . '&server_processed=' . (int)($result['processed'] ?? 0));
    } elseif (($_POST['form_action'] ?? '') === 'server_conversion_retry_send_failed') {
        $limit = max(1, min(100, (int)($_POST['server_send_limit'] ?? 20)));
        $result = function_exists('server_conversion_process_pending') ? server_conversion_process_pending($limit, true) : ['processed' => 0, 'sent' => 0, 'failed' => 0];
        redirect_302('admin/analytics?server_retry_sent=' . (int)($result['sent'] ?? 0) . '&server_retry_failed=' . (int)($result['failed'] ?? 0) . '&server_retry_processed=' . (int)($result['processed'] ?? 0));
    } elseif (($_POST['form_action'] ?? '') === 'server_conversion_mark_failed_ignored') {
        $count = function_exists('server_conversion_mark_failed_ignored') ? server_conversion_mark_failed_ignored() : 0;
        redirect_302('admin/analytics?server_ignored=' . (int)$count);
    } elseif (($_POST['form_action'] ?? '') === 'server_conversion_mark_ignored') {
        $ids = array_values(array_filter(array_map('strval', (array)($_POST['queue_ids'] ?? []))));
        $count = function_exists('server_conversion_mark_ignored') ? server_conversion_mark_ignored($ids) : 0;
        redirect_302('admin/analytics?server_ignored=' . (int)$count);
    } elseif (($_POST['form_action'] ?? '') === 'server_conversion_clear_old_sent') {
        $days = max(1, min(3650, (int)($_POST['server_clear_sent_days'] ?? 30)));
        $removed = function_exists('server_conversion_clear_old_sent_events') ? server_conversion_clear_old_sent_events($days) : 0;
        redirect_302('admin/analytics?server_cleared_sent=' . (int)$removed . '&server_clear_days=' . (int)$days);
    } elseif (($_POST['form_action'] ?? '') === 'google_ads_foundation_clear_old') {
        $days = max(1, min(3650, (int)($_POST['google_ads_clear_days'] ?? 90)));
        $removed = function_exists('server_conversion_google_ads_clear_old') ? server_conversion_google_ads_clear_old($days) : 0;
        redirect_302('admin/analytics?google_ads_cleared=' . (int)$removed . '&google_ads_clear_days=' . (int)$days);
    } elseif (($_POST['form_action'] ?? '') === 'google_ads_sender_send_ready') {
        $settingsForLimit = function_exists('server_conversion_read_settings') ? server_conversion_read_settings() : [];
        $limit = max(1, min(100, (int)($_POST['google_ads_send_limit'] ?? ($settingsForLimit['google_ads']['sender']['max_events_per_run'] ?? 10))));
        $result = function_exists('server_conversion_process_google_ads_queue') ? server_conversion_process_google_ads_queue($limit, false) : ['processed' => 0, 'sent' => 0, 'validated' => 0, 'failed' => 0];
        redirect_302('admin/analytics?google_ads_processed=' . (int)($result['processed'] ?? 0) . '&google_ads_sent=' . (int)($result['sent'] ?? 0) . '&google_ads_validated=' . (int)($result['validated'] ?? 0) . '&google_ads_failed=' . (int)($result['failed'] ?? 0));
    } elseif (($_POST['form_action'] ?? '') === 'google_ads_sender_retry_failed') {
        $settingsForLimit = function_exists('server_conversion_read_settings') ? server_conversion_read_settings() : [];
        $limit = max(1, min(100, (int)($_POST['google_ads_send_limit'] ?? ($settingsForLimit['google_ads']['sender']['max_events_per_run'] ?? 10))));
        $result = function_exists('server_conversion_process_google_ads_queue') ? server_conversion_process_google_ads_queue($limit, true) : ['processed' => 0, 'sent' => 0, 'validated' => 0, 'failed' => 0];
        redirect_302('admin/analytics?google_ads_retry_processed=' . (int)($result['processed'] ?? 0) . '&google_ads_retry_sent=' . (int)($result['sent'] ?? 0) . '&google_ads_retry_validated=' . (int)($result['validated'] ?? 0) . '&google_ads_retry_failed=' . (int)($result['failed'] ?? 0));
    } elseif (($_POST['form_action'] ?? '') === 'google_ads_sender_mark_ignored') {
        $ids = array_values(array_filter(array_map('strval', (array)($_POST['google_ads_queue_ids'] ?? []))));
        $count = function_exists('server_conversion_google_ads_mark_ignored') ? server_conversion_google_ads_mark_ignored($ids) : 0;
        redirect_302('admin/analytics?google_ads_ignored=' . (int)$count);
    } elseif (($_POST['form_action'] ?? '') === 'save_google_ads_api_credentials') {
        $ok = function_exists('google_ads_vault_upsert_from_post') ? google_ads_vault_upsert_from_post($_POST) : false;
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('update', 'google_ads_vault', null, 'Data Koneksi Google Ads diperbarui.', [
                'enabled' => !empty($_POST['google_ads_api_vault_enabled']),
                'login_customer_id_set' => !empty($_POST['google_ads_login_customer_id']),
                'api_version' => (string)($_POST['google_ads_api_version'] ?? ''),
            ]);
        }
        redirect_302('admin/analytics?' . ($ok ? 'google_ads_vault_saved=1' : 'google_ads_vault_saved=0'));
    } elseif (($_POST['form_action'] ?? '') === 'clear_google_ads_api_credentials') {
        $ok = function_exists('google_ads_vault_clear') ? google_ads_vault_clear() : false;
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('delete', 'google_ads_vault', null, 'Data Koneksi Google Ads dibersihkan.', []);
        }
        redirect_302('admin/analytics?' . ($ok ? 'google_ads_vault_cleared=1' : 'google_ads_vault_cleared=0'));
    } elseif (($_POST['form_action'] ?? '') === 'server_conversion_retry_failed') {
        $count = function_exists('server_conversion_retry_failed') ? server_conversion_retry_failed() : 0;
        redirect_302('admin/analytics?server_retry=' . (int)$count);
    } elseif (($_POST['form_action'] ?? '') === 'server_conversion_prune_queue') {
        $removed = function_exists('server_conversion_prune_queue') ? server_conversion_prune_queue(500) : 0;
        redirect_302('admin/analytics?server_pruned=' . (int)$removed);
    }
}

if (!empty($_GET['saved'])) {
    $message = 'Pengaturan Analytics & Iklan berhasil disimpan.';
}
if (!empty($_GET['server_saved'])) {
    $message = 'Pengaturan pengiriman data berhasil disimpan.';
}
if (isset($_GET['server_test'])) {
    $message = ((string)$_GET['server_test'] === '1')
        ? 'Data uji berhasil masuk ke antrian pengiriman.'
        : 'Data uji belum masuk. Pastikan fitur aktif dan minimal satu platform sudah lengkap.';
}
if (isset($_GET['server_sent'])) {
    $message = 'Pengiriman selesai. Diproses: ' . (int)($_GET['server_processed'] ?? 0) . ', terkirim: ' . (int)$_GET['server_sent'] . ', gagal: ' . (int)($_GET['server_failed'] ?? 0) . '.';
}
if (isset($_GET['server_retry_sent'])) {
    $message = 'Percobaan ulang selesai. Diproses: ' . (int)($_GET['server_retry_processed'] ?? 0) . ', terkirim: ' . (int)$_GET['server_retry_sent'] . ', gagal: ' . (int)($_GET['server_retry_failed'] ?? 0) . '.';
}
if (isset($_GET['server_ignored'])) {
    $message = 'Data yang diabaikan: ' . (int)$_GET['server_ignored'] . ' event.';
}
if (isset($_GET['server_cleared_sent'])) {
    $message = 'Data terkirim lama dibersihkan: ' . (int)$_GET['server_cleared_sent'] . ' event lebih lama dari ' . (int)($_GET['server_clear_days'] ?? 30) . ' hari.';
}
if (isset($_GET['server_retry'])) {
    $message = 'Data gagal disiapkan ulang: ' . (int)$_GET['server_retry'] . ' event.';
}
if (isset($_GET['server_pruned'])) {
    $message = 'Data lama dibersihkan: ' . (int)$_GET['server_pruned'] . ' event.';
}
if (isset($_GET['google_ads_cleared'])) {
    $message = 'Data lama Google Ads dibersihkan: ' . (int)$_GET['google_ads_cleared'] . ' event lebih lama dari ' . (int)($_GET['google_ads_clear_days'] ?? 90) . ' hari.';
}
if (isset($_GET['google_ads_vault_saved'])) {
    $message = ((string)$_GET['google_ads_vault_saved'] === '1')
        ? 'Data Koneksi Google Ads berhasil disimpan. Data rahasia tetap aman dan tidak ditampilkan penuh.'
        : 'Data Koneksi Google Ads belum bisa disimpan. Pastikan folder storage bisa ditulis server.';
}
if (isset($_GET['google_ads_vault_cleared'])) {
    $message = ((string)$_GET['google_ads_vault_cleared'] === '1')
        ? 'Data Koneksi Google Ads berhasil dibersihkan.'
        : 'Data Koneksi Google Ads belum bisa dibersihkan.';
}
if (isset($_GET['google_ads_processed'])) {
    $message = 'Pengiriman Google Ads selesai. Diproses: ' . (int)$_GET['google_ads_processed'] . ', terkirim: ' . (int)($_GET['google_ads_sent'] ?? 0) . ', mode tes sukses: ' . (int)($_GET['google_ads_validated'] ?? 0) . ', gagal: ' . (int)($_GET['google_ads_failed'] ?? 0) . '.';
}
if (isset($_GET['google_ads_retry_processed'])) {
    $message = 'Percobaan ulang Google Ads selesai. Diproses: ' . (int)$_GET['google_ads_retry_processed'] . ', terkirim: ' . (int)($_GET['google_ads_retry_sent'] ?? 0) . ', mode tes sukses: ' . (int)($_GET['google_ads_retry_validated'] ?? 0) . ', gagal: ' . (int)($_GET['google_ads_retry_failed'] ?? 0) . '.';
}
if (isset($_GET['google_ads_ignored'])) {
    $message = 'Data Google Ads yang diabaikan: ' . (int)$_GET['google_ads_ignored'] . ' event.';
}

$loggedIn = admin_analytics_logged_in();
$settings = $loggedIn ? analytics_read_settings() : analytics_default_settings();
$summary = $loggedIn ? analytics_channel_dashboard_summary(admin_analytics_days(), []) : [];
$pixelStatus = $loggedIn && function_exists('analytics_pixel_status_summary') ? analytics_pixel_status_summary($settings) : [];
$googleAdsDirectStatus = $loggedIn && function_exists('analytics_google_ads_direct_status') ? analytics_google_ads_direct_status($settings) : [];
$googleAdsVerification = $loggedIn && function_exists('analytics_google_ads_verification_summary') ? analytics_google_ads_verification_summary($settings) : [];
$googleAdsTestUrl = (string)($googleAdsVerification['test_url'] ?? (function_exists('url') ? url('admin/google-ads-tracking-test') : '/admin/google-ads-tracking-test'));
$serverSettings = $loggedIn && function_exists('server_conversion_read_settings') ? server_conversion_read_settings() : (function_exists('server_conversion_default_settings') ? server_conversion_default_settings() : []);
$serverSummary = $loggedIn && function_exists('server_conversion_queue_summary') ? server_conversion_queue_summary(10) : [];
$serverStatus = $loggedIn && function_exists('server_conversion_status_summary') ? server_conversion_status_summary($serverSettings) : [];
$googleAdsFoundationStatus = (array)($serverStatus['platforms']['google_ads'] ?? []);
$googleAdsSenderStatus = (array)($googleAdsFoundationStatus['sender'] ?? []);
$googleAdsVaultStatus = $loggedIn && function_exists('google_ads_vault_status') ? google_ads_vault_status($serverSettings) : (array)($googleAdsFoundationStatus['credential_vault'] ?? []);
$googleAdsQueueSummary = $loggedIn && function_exists('server_conversion_google_ads_queue_summary') ? server_conversion_google_ads_queue_summary(8) : (array)($googleAdsFoundationStatus['queue'] ?? []);
$googleAdsMappingSummary = $loggedIn && function_exists('server_conversion_google_ads_mapping_summary') ? server_conversion_google_ads_mapping_summary($serverSettings) : (array)($googleAdsFoundationStatus['mapping'] ?? []);
$googleAdsDebugRows = $loggedIn && function_exists('server_conversion_google_ads_debug_rows') ? server_conversion_google_ads_debug_rows(30) : [];
$serverUxStatus = $loggedIn && function_exists('server_conversion_ux_sync_summary') ? server_conversion_ux_sync_summary($settings, $serverSettings) : [];
$serverPreview = $loggedIn && isset($_GET['server_preview']) && function_exists('server_conversion_payload_preview') ? server_conversion_payload_preview((string)($_GET['queue_id'] ?? '')) : [];
$serverLogs = $loggedIn && function_exists('server_conversion_recent_logs') ? server_conversion_recent_logs(10) : [];
$serverDebugStatus = $loggedIn ? strtolower(trim((string)($_GET['server_status'] ?? ''))) : '';
$serverDebugStatus = in_array($serverDebugStatus, ['pending', 'sent', 'gagal', 'ignored'], true) ? $serverDebugStatus : '';
$serverDebugRows = $loggedIn && function_exists('server_conversion_debug_rows') ? server_conversion_debug_rows(60, $serverDebugStatus) : [];
$serverCronUrl = function_exists('url') ? url('cron/server-conversions?token=CRON_TOKEN') : '/cron/server-conversions?token=CRON_TOKEN';

if ($loggedIn && (string)($_GET['action'] ?? '') === 'export_server_conversion_csv' && function_exists('server_conversion_export_debug_csv')) {
    server_conversion_export_debug_csv();
}

if ($loggedIn && (string)($_GET['action'] ?? '') === 'export_google_ads_foundation_csv' && function_exists('server_conversion_google_ads_export_csv')) {
    server_conversion_google_ads_export_csv();
}

if ($loggedIn && (string)($_GET['action'] ?? '') === 'export_channel_csv') {
    analytics_export_channel_csv($summary);
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Analytics & Attribution - Admin',
    'description' => 'Pengaturan analytics, pixel iklan, verifikasi website, dan ringkasan performa channel marketing.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-analytics-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Pengaturan Analytics & Iklan</div>
                <h1>Analytics & Iklan</h1>
                <p>Atur alat analitik, pixel iklan, verifikasi domain, dan pelacakan hasil iklan dari satu tempat. Data customer tetap dijaga agar tidak tampil mentah di dashboard.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <style>
                .admin-analytics-shell .analytics-alert{margin-bottom:16px;padding:13px 15px;border-radius:16px;font-weight:800}.admin-analytics-shell .analytics-alert--success{background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--admin-primary)}.admin-analytics-shell .analytics-alert--danger{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}.admin-analytics-shell .analytics-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr);gap:18px;align-items:start}.admin-analytics-shell .analytics-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 40px rgba(15,23,42,.05)}.admin-analytics-shell .analytics-card h2,.admin-analytics-shell .analytics-card h3{margin:.1rem 0 .45rem;color:#0f172a}.admin-analytics-shell .analytics-card p{color:#64748b;margin:.2rem 0 .9rem}.admin-analytics-shell .analytics-form{display:grid;gap:16px}.admin-analytics-shell .analytics-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-analytics-shell label{display:grid;gap:7px;color:#334155;font-weight:800;font-size:.86rem}.admin-analytics-shell input,.admin-analytics-shell select,.admin-analytics-shell textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:10px 12px;color:#0f172a;background:#fff}.admin-analytics-shell textarea{min-height:112px;resize:vertical;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.82rem}.admin-analytics-shell .analytics-platform-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.admin-analytics-shell .analytics-platform-card{border:1px solid #e2e8f0;background:#f8fafc;border-radius:18px;padding:13px}.admin-analytics-shell .analytics-platform-card label{margin-top:8px}.admin-analytics-shell .analytics-monitor-list{display:grid;gap:8px}.admin-analytics-shell .analytics-monitor-row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:14px;padding:9px 11px}.admin-analytics-shell .analytics-monitor-row small{color:#64748b;word-break:break-all}.admin-analytics-shell .analytics-check{display:flex!important;align-items:center;gap:8px}.admin-analytics-shell .analytics-check input{width:auto}.admin-analytics-shell .analytics-status{display:grid;gap:10px}.admin-analytics-shell .analytics-status div{border:1px solid #dbeafe;background:#f8fbff;border-radius:18px;padding:12px}.admin-analytics-shell .analytics-status strong{display:block;color:#0f172a}.admin-analytics-shell .analytics-status span{color:#64748b;font-size:.86rem}.admin-analytics-shell .analytics-badge{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:.75rem;font-weight:900;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.admin-analytics-shell .analytics-badge--ok{background:color-mix(in srgb,var(--bg) 82%,#ffffff);color:var(--admin-primary);border-color:var(--border)}.admin-analytics-shell .analytics-badge--off{background:#f1f5f9;color:#475569;border-color:#cbd5e1}.admin-analytics-shell .analytics-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0}.admin-analytics-shell .analytics-kpi{border:1px solid #e2e8f0;background:#fff;border-radius:22px;padding:16px;box-shadow:0 12px 36px rgba(15,23,42,.04)}.admin-analytics-shell .analytics-kpi span{display:inline-flex;border-radius:999px;background:color-mix(in srgb,var(--admin-primary) 13%,#ffffff);color:var(--admin-primary);padding:4px 9px;font-size:.75rem;font-weight:900;text-transform:uppercase}.admin-analytics-shell .analytics-kpi strong{display:block;font-size:1.8rem;color:#0f172a;margin-top:8px}.admin-analytics-shell .analytics-table-note{color:#64748b;margin:.2rem 0 1rem}.admin-analytics-shell .analytics-guide ul{margin:.6rem 0 0;padding-left:1.15rem;color:#475569}.admin-analytics-shell .analytics-guide li{margin:.4rem 0}.admin-analytics-shell code{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:2px 6px}.admin-analytics-shell .analytics-pre{background:#0f172a;color:#e2e8f0;border-radius:18px;padding:14px;overflow:auto;max-height:420px;font-size:.78rem;line-height:1.55}.admin-analytics-shell .analytics-mini-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.admin-analytics-shell .analytics-mini-actions form{margin:0}.admin-analytics-shell .analytics-badge--warn{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff);color:var(--admin-primary-dark);border-color:color-mix(in srgb,var(--admin-primary) 22%,#ffffff)}.admin-analytics-shell .analytics-badge--info{background:#eef2ff;color:#4338ca;border-color:#c7d2fe}.admin-analytics-shell .analytics-google-mapping{display:grid;gap:10px;margin-top:12px}.admin-analytics-shell .analytics-google-map-row{display:grid;grid-template-columns:150px 1fr 1fr 120px;gap:10px;align-items:end;border:1px solid #e2e8f0;background:#fff;border-radius:16px;padding:10px}.admin-analytics-shell .analytics-google-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0}.admin-analytics-shell .analytics-google-kpi{border:1px solid #dbeafe;background:#f8fbff;border-radius:16px;padding:12px}.admin-analytics-shell .analytics-google-kpi span{font-size:.72rem;text-transform:uppercase;font-weight:900;color:#64748b}.admin-analytics-shell .analytics-google-kpi strong{display:block;font-size:1.35rem;color:#0f172a}.admin-analytics-shell .analytics-platform-card--google-foundation{grid-column:1/-1;background:linear-gradient(135deg,#f8fafc,#ecfeff)}.admin-analytics-shell .analytics-google-head{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.admin-analytics-shell .analytics-google-note{border:1px solid #bae6fd;background:#f0f9ff;border-radius:14px;padding:10px;color:#0c4a6e;margin:10px 0}.admin-analytics-shell .analytics-field-help{color:#64748b;font-size:.78rem;font-weight:700}.admin-analytics-shell .analytics-cron-box,.admin-analytics-shell .analytics-sync-panel{border:1px solid #e2e8f0;background:#f8fafc;border-radius:18px;padding:14px;margin-top:14px}.admin-analytics-shell .analytics-sync-panel{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.admin-analytics-shell .analytics-sync-box{border:1px solid #e2e8f0;background:#fff;border-radius:16px;padding:12px}.admin-analytics-shell .analytics-sync-box--info{background:#eff6ff;border-color:#bfdbfe}.admin-analytics-shell .analytics-sync-box--ok{background:color-mix(in srgb,var(--bg) 82%,#ffffff);border-color:var(--border)}.admin-analytics-shell .analytics-sync-box--warn{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff);border-color:color-mix(in srgb,var(--admin-primary) 22%,#ffffff)}.admin-analytics-shell .analytics-monitor-legend{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0}.admin-analytics-shell .analytics-monitor-legend>div{border:1px solid #e2e8f0;background:#fff;border-radius:14px;padding:10px}@media(max-width:980px){.admin-analytics-shell .analytics-grid,.admin-analytics-shell .analytics-form-grid,.admin-analytics-shell .analytics-platform-grid,.admin-analytics-shell .analytics-kpis,.admin-analytics-shell .analytics-google-kpis,.admin-analytics-shell .analytics-sync-panel,.admin-analytics-shell .analytics-monitor-legend{grid-template-columns:1fr}.admin-analytics-shell .analytics-google-map-row{grid-template-columns:1fr}}
            </style>
            <style>
                .admin-analytics-shell .analytics-platform-card--google-tag{grid-column:1/-1;background:linear-gradient(135deg,#f8fafc,#eefcf6);border-color:var(--border)}.admin-analytics-shell .analytics-google-tag-events{display:grid;gap:10px;margin-top:12px;border:1px solid var(--border);background:#f7fefb;border-radius:16px;padding:12px}.admin-analytics-shell .analytics-google-tag-event-row{display:grid;grid-template-columns:190px minmax(180px,1fr) minmax(220px,1.2fr);gap:10px;align-items:center;border:1px solid #e2e8f0;background:#fff;border-radius:14px;padding:10px}.admin-analytics-shell .analytics-setup-checklist{display:grid;gap:8px;margin-top:12px}.admin-analytics-shell .analytics-setup-checklist div{display:flex;align-items:flex-start;gap:8px;border:1px solid #e2e8f0;background:#fff;border-radius:14px;padding:10px}.admin-analytics-shell .analytics-setup-checklist strong{min-width:110px}.admin-analytics-shell .analytics-direct-ready{border:1px solid var(--border);background:color-mix(in srgb,var(--secondary-light) 50%,#ffffff);border-radius:18px;padding:12px;margin-top:12px}.admin-analytics-shell .analytics-verification-panel{border:1px solid #bfdbfe;background:linear-gradient(135deg,#eff6ff,#f8fafc);border-radius:20px;padding:14px;margin-top:12px}.admin-analytics-shell .analytics-verification-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}.admin-analytics-shell .analytics-verification-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px}.admin-analytics-shell .analytics-verification-item{border:1px solid #e2e8f0;background:#fff;border-radius:14px;padding:10px}.admin-analytics-shell .analytics-verification-item strong{display:block;color:#0f172a}.admin-analytics-shell .analytics-verification-item small{color:#64748b}.admin-analytics-shell .analytics-test-url{word-break:break-all;background:#0f172a;color:#e2e8f0;border-radius:14px;padding:10px;margin-top:10px;font-size:.8rem}.admin-analytics-shell .analytics-tag-assistant-note{border:1px solid color-mix(in srgb,var(--admin-primary) 22%,#ffffff);background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff);color:var(--admin-primary-dark);border-radius:14px;padding:10px;margin-top:10px}.admin-analytics-shell .analytics-vault-panel{border:1px solid #c7d2fe;background:linear-gradient(135deg,#eef2ff,#f8fafc);border-radius:20px;padding:14px;margin-top:14px}.admin-analytics-shell .analytics-vault-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0}.admin-analytics-shell .analytics-vault-item{border:1px solid #e2e8f0;background:#fff;border-radius:14px;padding:10px}.admin-analytics-shell .analytics-vault-item strong{display:block;color:#0f172a}.admin-analytics-shell .analytics-vault-item small{display:block;color:#64748b;word-break:break-all}.admin-analytics-shell .analytics-vault-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.admin-analytics-shell .analytics-vault-danger{border:1px solid #fecaca;background:#fef2f2;border-radius:16px;padding:12px;margin-top:12px}@media(max-width:980px){.admin-analytics-shell .analytics-google-tag-event-row,.admin-analytics-shell .analytics-vault-grid,.admin-analytics-shell .analytics-vault-form{grid-template-columns:1fr}}
            </style>

            <?php if ($message): ?><div class="analytics-alert analytics-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="analytics-alert analytics-alert--danger"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-card admin-login-card">
                    <h2>Login Admin</h2>
                    <p>Masukkan password admin untuk membuka pengaturan analytics.</p>
                    <form method="post" class="admin-login-form">
                        <?= csrf_field(); ?>
                        <label>Password Admin</label>
                        <input type="password" name="password" required autofocus>
                        <button class="admin-btn admin-btn--primary" type="submit">Login</button>
                    </form>
                </div>
            <?php else: ?>
                <?php if (function_exists('marketing_analytics_render_menu_map')) { marketing_analytics_render_menu_map('analytics'); } ?>
                <div class="analytics-grid">
                    <form method="post" class="analytics-form" data-admin-page-tab-scope>
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_analytics_settings">

                        <div class="admin-page-subtabs" role="tablist" aria-label="Bagian Analytics & Iklan">
                            <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="analytics-enable"><span>1. Aktifkan Pelacakan</span><small>Mode & consent</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="analytics-gtm"><span>2. GTM</span><small>Google Tag Manager</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="analytics-ga4"><span>3. GA4</span><small>Analytics direct</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="analytics-pixels"><span>4. Pixel Platform Ads</span><small>Meta, TikTok, Google Ads</small></button>
                            <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="analytics-gsc"><span>5. GSC</span><small>Search Console</small></button>
                        </div>
                        <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian analytics</span><select data-admin-page-tab-select aria-label="Pilih bagian Analytics & Iklan"><option value="analytics-enable">1. Aktifkan Pelacakan</option><option value="analytics-gtm">2. GTM</option><option value="analytics-ga4">3. GA4</option><option value="analytics-pixels">4. Pixel Platform Ads</option><option value="analytics-gsc">5. GSC</option></select></label></div>

                        <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="analytics-enable">
                        <section class="analytics-card">
                            <h2>Aktifkan Pelacakan</h2>
                            <p>Pelacakan eksternal bersifat opsional. Website tetap bisa mencatat performa dasar tanpa membagikan data pribadi customer.</p>
                            <div class="analytics-form-grid">
                                <label class="analytics-check"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : ''; ?>> Aktifkan Analytics Foundation</label>
                                <label class="analytics-check"><input type="checkbox" name="internal_attribution_enabled" value="1" <?= !empty($settings['internal_attribution_enabled']) ? 'checked' : ''; ?>> Simpan Parameter Campaign/referrer attribution lokal</label>
                                <label class="analytics-check"><input type="checkbox" name="datalayer_enabled" value="1" <?= !empty($settings['datalayer_enabled']) ? 'checked' : ''; ?>> Kirim data event ke Google Tag Manager</label>
                                <label class="analytics-check"><input type="checkbox" name="debug" value="1" <?= !empty($settings['debug']) ? 'checked' : ''; ?>> Mode tes</label>
                            </div>
                            <div class="analytics-form-grid">
                                <label>Durasi cookie attribution (hari)
                                    <input type="number" name="cookie_days" min="1" max="730" value="<?= esc((string)($settings['cookie_days'] ?? 90)); ?>">
                                </label>
                                <label>Mode Pixel Iklan
                                    <select name="pixel_mode">
                                        <?php foreach (['gtm_only' => 'Google Tag Manager saja', 'direct' => 'Pixel langsung saja', 'hybrid' => 'Gabungan Google Tag Manager + pixel langsung'] as $value => $label): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= (string)($settings['pixel_mode'] ?? 'gtm_only') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="analytics-gtm" hidden>
                        <section class="analytics-card">
                            <h2>Google Tag Manager</h2>
                            <p>Gunakan Google Tag Manager agar pengaturan tracking iklan dan analytics lebih rapi dalam satu tempat.</p>
                            <div class="analytics-form-grid">
                                <label class="analytics-check"><input type="checkbox" name="gtm_enabled" value="1" <?= !empty($settings['gtm']['enabled']) ? 'checked' : ''; ?>> Aktifkan Google Tag Manager</label>
                                <label>ID Google Tag Manager
                                    <input type="text" name="gtm_container_id" value="<?= esc((string)($settings['gtm']['container_id'] ?? '')); ?>" placeholder="GTM-XXXXXXX">
                                </label>
                            </div>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="analytics-ga4" hidden>
                        <section class="analytics-card">
                            <h2>Google Analytics 4</h2>
                            <p>Opsional. Kosongkan jika belum memakai Google Tag Manager atau semua pixel sudah diatur manual.</p>
                            <div class="analytics-form-grid">
                                <label class="analytics-check"><input type="checkbox" name="ga4_enabled" value="1" <?= !empty($settings['ga4']['enabled']) ? 'checked' : ''; ?>> Aktifkan GA4 direct</label>
                                <label>GA4 Measurement ID
                                    <input type="text" name="ga4_measurement_id" value="<?= esc((string)($settings['ga4']['measurement_id'] ?? '')); ?>" placeholder="G-XXXXXXXXXX">
                                </label>
                            </div>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="analytics-pixels" hidden>
                        <section class="analytics-card">
                            <div class="analytics-section-title">
                                <span class="analytics-mini-badge">Pixel Iklan</span>
                                <h2>Pixel Iklan & Platform Ads</h2>
                            </div>
                            <p>Bagian ini untuk memasang pixel iklan dari browser pengunjung. Cocok untuk Meta, TikTok, Google Ads, dan platform iklan lain.</p>
                            <div class="analytics-platform-grid">
                                <div class="analytics-platform-card">
                                    <label class="analytics-check"><input type="checkbox" name="meta_pixel_enabled" value="1" <?= !empty($settings['pixels']['meta']['enabled']) ? 'checked' : ''; ?>> Aktifkan Meta Pixel</label>
                                    <label>Meta Pixel ID
                                        <input type="text" name="meta_pixel_id" id="analytics_meta_pixel_id" value="<?= esc((string)($settings['pixels']['meta']['pixel_id'] ?? '')); ?>" placeholder="123456789012345">
                                    </label>
                                </div>
                                <div class="analytics-platform-card">
                                    <label class="analytics-check"><input type="checkbox" name="tiktok_pixel_enabled" value="1" <?= !empty($settings['pixels']['tiktok']['enabled']) ? 'checked' : ''; ?>> Aktifkan TikTok Pixel</label>
                                    <label>TikTok Pixel ID
                                        <input type="text" name="tiktok_pixel_id" id="analytics_tiktok_pixel_id" value="<?= esc((string)($settings['pixels']['tiktok']['pixel_id'] ?? '')); ?>" placeholder="CXXXXXXXXXXXXXXX">
                                    </label>
                                </div>
                                <div class="analytics-platform-card analytics-platform-card--google-tag">
                                    <div class="analytics-google-head">
                                        <label class="analytics-check"><input type="checkbox" name="google_ads_enabled" value="1" <?= !empty($settings['pixels']['google_ads']['enabled']) ? 'checked' : ''; ?>> Aktifkan Tracking Google Ads</label>
                                        <span class="analytics-badge <?= !empty($googleAdsDirectStatus['direct_ready']) ? 'analytics-badge--ok' : (!empty($googleAdsDirectStatus['gtm_ready']) ? 'analytics-badge--info' : 'analytics-badge--off'); ?>"><?= esc((string)($googleAdsDirectStatus['status_label'] ?? 'Nonaktif')); ?></span>
                                    </div>
                                    <p class="analytics-google-note">Sistem ini membantu mengecek apakah klik WhatsApp, form, checkout, dan order sudah terbaca oleh Google Ads. Gunakan halaman tes sebelum iklan dijalankan besar.</p>
                                    <div class="analytics-form-grid">
                                        <label>Google Tag / Conversion ID
                                            <input type="text" name="google_ads_conversion_id" value="<?= esc((string)($settings['pixels']['google_ads']['conversion_id'] ?? '')); ?>" placeholder="AW-123456789">
                                            <small class="analytics-field-help">Ambil dari Google Ads conversion tag. Boleh isi angka saja, sistem otomatis ubah ke AW-.</small>
                                        </label>
                                        <label>Fallback Conversion Label
                                            <input type="text" name="google_ads_conversion_label" value="<?= esc((string)($settings['pixels']['google_ads']['conversion_label'] ?? '')); ?>" placeholder="AbCdEfGhIjkLmNoP">
                                            <small class="analytics-field-help">Dipakai kalau label event spesifik di bawah masih kosong.</small>
                                        </label>
                                    </div>
                                    <label class="analytics-check"><input type="checkbox" name="google_ads_fire_conversion_events" value="1" <?= !array_key_exists('fire_conversion_events', (array)($settings['pixels']['google_ads'] ?? [])) || !empty($settings['pixels']['google_ads']['fire_conversion_events']) ? 'checked' : ''; ?>> Kirim event konversi Google Ads dari website</label>
                                    <div class="analytics-google-tag-events">
                                        <strong>Event Conversion Label Mapping</strong>
                                        <small class="analytics-field-help">Isi label konversi dari Google Ads. Kalau baru mulai, cukup isi label untuk klik WhatsApp dan form masuk.</small>
                                        <?php foreach (analytics_google_ads_event_defaults() as $gaEventName => $gaEventDefault): ?>
                                            <?php $gaEventRow = (array)($settings['pixels']['google_ads']['event_labels'][$gaEventName] ?? $gaEventDefault); ?>
                                            <div class="analytics-google-tag-event-row">
                                                <label class="analytics-check"><input type="checkbox" name="google_ads_event_<?= esc((string)$gaEventName); ?>_enabled" value="1" <?= !empty($gaEventRow['enabled']) ? 'checked' : ''; ?>> <?= esc((string)($gaEventDefault['label'] ?? $gaEventName)); ?></label>
                                                <label>Conversion Label
                                                    <input type="text" name="google_ads_event_<?= esc((string)$gaEventName); ?>_conversion_label" value="<?= esc((string)($gaEventRow['conversion_label'] ?? '')); ?>" placeholder="label-dari-google-ads">
                                                </label>
                                                <small><?= esc((string)($gaEventDefault['description'] ?? '')); ?><br>Goal: <?= esc((string)($gaEventDefault['recommended_goal'] ?? '-')); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="analytics-platform-card">
                                    <label class="analytics-check"><input type="checkbox" name="microsoft_uet_enabled" value="1" <?= !empty($settings['pixels']['microsoft_uet']['enabled']) ? 'checked' : ''; ?>> Aktifkan Microsoft Ads UET</label>
                                    <label>Microsoft UET Tag ID
                                        <input type="text" name="microsoft_uet_id" value="<?= esc((string)($settings['pixels']['microsoft_uet']['tag_id'] ?? '')); ?>" placeholder="123456789">
                                    </label>
                                </div>
                                <div class="analytics-platform-card">
                                    <label class="analytics-check"><input type="checkbox" name="linkedin_enabled" value="1" <?= !empty($settings['pixels']['linkedin']['enabled']) ? 'checked' : ''; ?>> Aktifkan LinkedIn Insight</label>
                                    <label>LinkedIn Partner ID
                                        <input type="text" name="linkedin_partner_id" value="<?= esc((string)($settings['pixels']['linkedin']['partner_id'] ?? '')); ?>" placeholder="123456">
                                    </label>
                                </div>
                            </div>
                        </section>
                        </section>

                        <section class="admin-page-tab-panel" data-admin-page-tab-panel="analytics-gsc" hidden>
                        <section class="analytics-card">
                            <h2>Google Search Console</h2>
                            <p>Masukkan isi meta verification agar mudah verifikasi domain/property.</p>
                            <label>Google Site Verification Content
                                <input type="text" name="gsc_verification" value="<?= esc((string)($settings['gsc']['verification'] ?? '')); ?>" placeholder="kode-verifikasi-dari-search-console">
                            </label>
                        </section>


                        <section class="analytics-card">
                            <h2>Custom Verification Meta</h2>
                            <p>Opsional untuk verifikasi domain platform lain. Hanya tag <code>&lt;meta&gt;</code> yang aman yang akan disimpan; script custom tidak diperbolehkan dari field ini.</p>
                            <label>Meta Verification Tags
                                <textarea name="custom_meta" placeholder="&lt;meta name=&quot;facebook-domain-verification&quot; content=&quot;kode-verifikasi&quot;&gt;
&lt;meta name=&quot;p:domain_verify&quot; content=&quot;kode-verifikasi&quot;&gt;"><?= esc((string)($settings['custom_meta'] ?? '')); ?></textarea>
                            </label>
                        </section>
                        </section>

                        <button type="submit" class="admin-btn admin-btn--primary">Simpan Pengaturan Analytics</button>
                    </form>

                    <aside class="analytics-card analytics-guide">
                        <h2>Status Integrasi</h2>
                        <div class="analytics-status">
                            <div><strong>Google Tag Manager</strong><span><span class="analytics-badge <?= !empty($settings['gtm']['enabled']) && !empty($settings['gtm']['container_id']) ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= !empty($settings['gtm']['enabled']) && !empty($settings['gtm']['container_id']) ? 'Aktif' : 'Nonaktif'; ?></span> <?= esc((string)($settings['gtm']['container_id'] ?? '')); ?></span></div>
                            <div><strong>GA4 Direct</strong><span><span class="analytics-badge <?= !empty($settings['ga4']['enabled']) && !empty($settings['ga4']['measurement_id']) ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= !empty($settings['ga4']['enabled']) && !empty($settings['ga4']['measurement_id']) ? 'Aktif' : 'Nonaktif'; ?></span> <?= esc((string)($settings['ga4']['measurement_id'] ?? '')); ?></span></div>
                            <div><strong>Verifikasi Google Search Console</strong><span><span class="analytics-badge <?= !empty($settings['gsc']['verification']) ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= !empty($settings['gsc']['verification']) ? 'Siap' : 'Kosong'; ?></span></span></div>
                            <div><strong>Mode Pixel Iklan</strong><span><span class="analytics-badge <?= !empty($pixelStatus['direct_enabled']) ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= esc((string)($pixelStatus['mode'] ?? 'gtm_only')); ?></span> <?= (int)($pixelStatus['active_count'] ?? 0); ?> pixel aktif</span></div>
                            <div><strong>Pengiriman Server Meta/TikTok</strong><span><span class="analytics-badge <?= !empty($serverStatus['enabled']) ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= !empty($serverStatus['enabled']) ? 'Aktif' : 'Nonaktif'; ?></span> <?= (int)($serverStatus['queue_counts']['pending'] ?? 0); ?> menunggu · <?= (int)($serverStatus['queue_counts']['failed'] ?? 0); ?> gagal</span></div>
                            <div><strong>Pengiriman Terjadwal</strong><span><span class="analytics-badge <?= !empty($serverStatus['cron']['enabled']) && !empty($serverStatus['cron']['token_set']) ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= !empty($serverStatus['cron']['enabled']) ? 'Aktif' : 'Nonaktif'; ?></span> mode <?= esc((string)($serverStatus['sending_mode'] ?? 'manual')); ?> · token <?= !empty($serverStatus['cron']['token_set']) ? 'set' : 'kosong'; ?></span></div>
                            <div><strong>Tracking Website Google Ads</strong><span><span class="analytics-badge <?= !empty($googleAdsDirectStatus['direct_ready']) ? 'analytics-badge--ok' : (!empty($googleAdsDirectStatus['gtm_ready']) ? 'analytics-badge--info' : 'analytics-badge--off'); ?>"><?= esc((string)($googleAdsDirectStatus['status_label'] ?? 'Nonaktif')); ?></span> <?= (int)($googleAdsDirectStatus['ready_event_count'] ?? 0); ?> label event siap</span></div>
                            <div><strong>Tracking Google Ads</strong><span><span class="analytics-badge <?= !empty($googleAdsFoundationStatus['enabled']) ? 'analytics-badge--info' : 'analytics-badge--off'; ?>"><?= esc((string)($googleAdsFoundationStatus['status_label'] ?? 'Nonaktif')); ?></span> Data klik siap dicatat; pengiriman otomatis bisa diaktifkan nanti.</span></div>
                            <div><strong>Hybrid Dedup</strong><span><span class="analytics-badge <?= (int)($serverUxStatus['hybrid_ready_count'] ?? 0) > 0 ? 'analytics-badge--ok' : (((int)($serverUxStatus['warning_count'] ?? 0) > 0) ? 'analytics-badge--warn' : 'analytics-badge--off'); ?>"><?= (int)($serverUxStatus['hybrid_ready_count'] ?? 0); ?> platform siap</span> <?= (int)($serverUxStatus['warning_count'] ?? 0); ?> warning</span></div>
                            <div><strong>Custom Meta</strong><span><span class="analytics-badge <?= (int)($pixelStatus['custom_meta_count'] ?? 0) > 0 ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= (int)($pixelStatus['custom_meta_count'] ?? 0); ?> tag</span></span></div>
                            <div><strong>Proteksi Data</strong><span>Nama, email, nomor WhatsApp, token, pesan customer, dan detail pembayaran tidak dikirim ke alat iklan.</span></div>
                        </div>
                        <h3>Event Website yang Tersedia</h3>
                        <ul>
                            <li><code>PageView</code> / <code>page_context_ready</code> untuk page view aman.</li>
                            <li><code>ViewContent</code> / <code>select_item</code> untuk interaksi konten/produk.</li>
                            <li><code>contact_whatsapp</code> untuk klik WhatsApp.</li>
                            <li><code>begin_checkout</code> untuk checkout/order intent.</li>
                            <li><code>submit_inquiry</code> untuk inquiry form.</li>
                            <li><code>order_success</code> / <code>Purchase</code> untuk order success.</li>
                            <li><code>upload_payment_proof</code>, <code>view_invoice</code>, dan <code>check_order_status</code>.</li>
                        </ul>
                        <h3>Mode Tes Pixel Aktif</h3>
                        <div class="analytics-monitor-list">
                            <?php foreach ((array)($pixelStatus['platforms'] ?? []) as $platform => $row): ?>
                                <div class="analytics-monitor-row">
                                    <strong><?= esc((string)($row['label'] ?? $platform)); ?></strong>
                                    <span class="analytics-badge <?= !empty($row['enabled']) ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= !empty($row['enabled']) ? 'Aktif' : 'Off'; ?></span>
                                    <small><?= esc((string)($row['id'] ?? '')); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="analytics-direct-ready">
                            <h3>Checklist Google Ads</h3>
                            <p class="analytics-table-note"><?= esc((string)($googleAdsDirectStatus['message'] ?? 'Google Ads tracking belum aktif.')); ?></p>
                            <div class="analytics-setup-checklist">
                                <div><strong>1. ID Google Tag</strong><span class="analytics-badge <?= !empty($googleAdsDirectStatus['conversion_id']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsDirectStatus['conversion_id']) ? 'Terisi' : 'Kosong'; ?></span><small>Isi Google Tag / Conversion ID dari Google Ads.</small></div>
                                <div><strong>2. Label Konversi</strong><span class="analytics-badge <?= (int)($googleAdsDirectStatus['ready_event_count'] ?? 0) > 0 ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= (int)($googleAdsDirectStatus['ready_event_count'] ?? 0); ?> siap</span><small>Isi label konversi untuk klik WhatsApp, form, checkout, order berhasil, dan bukti pembayaran.</small></div>
                                <div><strong>3. Mode</strong><span class="analytics-badge <?= !empty($googleAdsDirectStatus['direct_mode_ready']) || !empty($googleAdsDirectStatus['gtm_ready']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsDirectStatus['direct_mode_ready']) ? 'Langsung/Gabungan' : (!empty($googleAdsDirectStatus['gtm_ready']) ? 'GTM' : 'Belum siap'); ?></span><small>Pilih mode sesuai cara pemasangan. Jika memakai Google Tag Manager, pastikan tag konversi sudah dibuat.</small></div>
                                <div><strong>4. Penanda Otomatis</strong><span class="analytics-badge analytics-badge--info">Cek di Google Ads</span><small>Pastikan penanda otomatis aktif di Google Ads agar hasil iklan bisa terbaca.</small></div>
                            </div>
                        </div>
                        <div class="analytics-verification-panel">
                            <div class="analytics-verification-head">
                                <div>
                                    <h3>Pengecekan Google Ads</h3>
                                    <p class="analytics-table-note">Cek kesiapan ID Google Tag, label konversi, mode pemasangan, dan halaman tes sebelum iklan diperbesar.</p>
                                </div>
                                <span class="analytics-badge <?= !empty($googleAdsVerification['tracking_ready']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= esc((string)($googleAdsVerification['status_label'] ?? 'Perlu Setup')); ?></span>
                            </div>
                            <div class="analytics-verification-grid">
                                <?php foreach ((array)($googleAdsVerification['checks'] ?? []) as $check): ?>
                                    <div class="analytics-verification-item">
                                        <strong><?= esc((string)($check['label'] ?? '-')); ?></strong>
                                        <span class="analytics-badge <?= !empty($check['ok']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($check['ok']) ? 'OK' : 'Cek'; ?></span>
                                        <small><?= esc((string)($check['message'] ?? '')); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (!empty($googleAdsVerification['missing_label_events'])): ?>
                                <div class="analytics-tag-assistant-note"><strong>Label belum lengkap:</strong> <?= esc(implode(', ', (array)$googleAdsVerification['missing_label_events'])); ?></div>
                            <?php endif; ?>
                            <div class="analytics-test-url"><?= esc($googleAdsTestUrl); ?></div>
                            <p class="analytics-table-note" style="margin-top:10px">Buka URL tes ini saat mengecek setup Google Ads. Klik tombol tes di halaman tersebut untuk memastikan event terbaca.</p>
                            <a class="admin-btn admin-btn--primary" href="<?= esc($googleAdsTestUrl); ?>" target="_blank" rel="noopener">Buka Halaman Tes Google Ads</a>
                        </div>
                    </aside>
                </div>

                <section class="analytics-card" style="margin-top:18px">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <h2>Pengiriman Data Iklan dari Server</h2>
                            <p class="analytics-table-note">Atur pengiriman data konversi ke platform iklan secara lebih aman. Cocok untuk membaca hasil form, WhatsApp, checkout, order, dan pembayaran tanpa menampilkan data pribadi customer secara mentah.</p>
                        </div>
                        <div class="admin-table-actions analytics-mini-actions">
                            <form method="post">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="server_conversion_test_enqueue">
                                <button class="admin-btn admin-btn--soft" type="submit">Buat Data Tes</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Kirim data yang menunggu ke Meta/TikTok sekarang? Pastikan pengaturan sudah benar.')">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="server_conversion_send_pending">
                                <input type="hidden" name="server_send_limit" value="<?= (int)($serverSettings['max_events_per_run'] ?? 20); ?>">
                                <button class="admin-btn admin-btn--primary" type="submit">Kirim yang Menunggu</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Coba ulang data yang gagal dikirim ke platform iklan?')">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="server_conversion_retry_send_failed">
                                <input type="hidden" name="server_send_limit" value="<?= (int)($serverSettings['max_events_per_run'] ?? 20); ?>">
                                <button class="admin-btn admin-btn--soft" type="submit">Coba Ulang yang Gagal</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Bersihkan data terkirim yang sudah lama? Data menunggu, gagal, dan diabaikan tidak akan dihapus.')">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="server_conversion_clear_old_sent">
                                <input type="hidden" name="server_clear_sent_days" value="30">
                                <button class="admin-btn admin-btn--ghost" type="submit">Bersihkan Data Terkirim Lama</button>
                            </form>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(admin_analytics_current_url(['action' => 'export_server_conversion_csv'])); ?>">Export CSV</a>
                        </div>
                    </div>

                    <div class="analytics-sync-panel">
                        <div class="analytics-sync-box analytics-sync-box--info">
                            <strong>Mode mudah untuk admin</strong>
                            <p>Isi Pixel ID di bagian <b>Pixel Iklan</b>. Centang sinkron agar pengaturan server mengikuti otomatis. Token tetap diisi jika ingin mengirim data dari server.</p>
                        </div>
                        <?php foreach ((array)($serverUxStatus['platforms'] ?? []) as $syncPlatform => $syncRow): ?>
                            <div class="analytics-sync-box <?= !empty($syncRow['mismatch']) ? 'analytics-sync-box--warn' : (!empty($syncRow['hybrid_ready']) ? 'analytics-sync-box--ok' : ''); ?>">
                                <strong><?= esc((string)($syncRow['label'] ?? $syncPlatform)); ?> Status Sinkron</strong>
                                <p><?= esc((string)($syncRow['message'] ?? 'Belum ada status.')); ?></p>
                                <small>ID Browser: <?= esc((string)($syncRow['browser_id'] ?? '-')); ?> · ID Server: <?= esc((string)($syncRow['server_id'] ?? '-')); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form method="post" class="analytics-form" style="margin-top:12px" id="server_conversion_settings_form">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="form_action" value="save_server_conversion_settings">

                        <div class="analytics-form-grid">
                            <label class="analytics-check"><input type="checkbox" name="server_conversion_enabled" value="1" <?= !empty($serverSettings['enabled']) ? 'checked' : ''; ?>> Aktifkan Pengiriman Data dari Server</label>
                            <label class="analytics-check"><input type="checkbox" name="server_conversion_test_mode" value="1" <?= !empty($serverSettings['test_mode']) ? 'checked' : ''; ?>> Mode tes aman</label>
                            <label class="analytics-check"><input type="checkbox" name="server_conversion_queue_high_intent_only" value="1" <?= !empty($serverSettings['queue_high_intent_only']) ? 'checked' : ''; ?>> Simpan aktivitas penting saja</label>
                            <label class="analytics-check"><input type="checkbox" name="server_conversion_advanced_matching_enabled" value="1" <?= !empty($serverSettings['advanced_matching_enabled']) ? 'checked' : ''; ?>> Cocokkan data customer hanya jika ada persetujuan</label>
                            <label>Mode Pengiriman
                                <select name="server_conversion_sending_mode">
                                    <?php foreach (['manual' => 'Manual saja', 'auto' => 'Otomatis terjadwal', 'hybrid' => 'Otomatis + coba ulang manual', 'disabled' => 'Nonaktif'] as $value => $label): ?>
                                        <option value="<?= esc((string)$value); ?>" <?= (string)($serverSettings['sending_mode'] ?? 'manual') === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Batas Data per Proses
                                <input type="number" name="server_conversion_max_events_per_run" min="1" max="100" value="<?= (int)($serverSettings['max_events_per_run'] ?? 20); ?>">
                            </label>
                        </div>

                        <div class="analytics-cron-box">
                            <div>
                                <span class="analytics-mini-badge">Jadwal Pengiriman Otomatis</span>
                                <h3>Pengiriman Terjadwal</h3>
                                <p class="analytics-table-note">Nonaktif secara bawaan. Aktifkan hanya jika hosting/VPS sudah siap menjalankan jadwal otomatis. Link ini dilindungi token.</p>
                            </div>
                            <div class="analytics-form-grid">
                                <label class="analytics-check"><input type="checkbox" name="server_conversion_cron_enabled" value="1" <?= !empty($serverSettings['cron']['enabled']) ? 'checked' : ''; ?>> Aktifkan pengiriman ulang terjadwal</label>
                                <label class="analytics-check"><input type="checkbox" name="server_conversion_cron_retry_failed" value="1" <?= !array_key_exists('retry_failed', (array)($serverSettings['cron'] ?? [])) || !empty($serverSettings['cron']['retry_failed']) ? 'checked' : ''; ?>> Coba ulang data gagal setelah data menunggu selesai</label>
                                <label>Token Jadwal
                                    <input type="password" name="server_conversion_cron_token" value="" placeholder="<?= esc(function_exists('server_conversion_mask_secret') ? server_conversion_mask_secret((string)($serverSettings['cron']['token'] ?? '')) : 'Isi token cron'); ?>">
                                    <small class="analytics-field-help">Token disimpan lokal dan tidak ditampilkan penuh. Isi field ini hanya jika ingin membuat/mengganti token.</small>
                                </label>
                                <label>Batas Data per Jadwal
                                    <input type="number" name="server_conversion_cron_max_events_per_run" min="1" max="100" value="<?= (int)($serverSettings['cron']['max_events_per_run'] ?? $serverSettings['max_events_per_run'] ?? 20); ?>">
                                </label>
                            </div>
                            <div class="analytics-cron-endpoint">
                                <strong>Link Jadwal:</strong> <code><?= esc($serverCronUrl); ?></code>
                                <span class="analytics-badge <?= !empty($serverSettings['cron']['enabled']) && !empty($serverSettings['cron']['token']) ? 'analytics-badge--ok' : 'analytics-badge--off'; ?>"><?= !empty($serverSettings['cron']['enabled']) ? 'Aktif' : 'Nonaktif'; ?></span>
                                <small>Terakhir jalan: <?= esc((string)($serverSettings['cron']['last_run_at'] ?? '-')); ?></small>
                            </div>
                        </div>

                        <div class="analytics-platform-grid" style="margin-top:14px">
                            <div class="analytics-platform-card">
                                <span class="analytics-mini-badge">Pengiriman Server Meta</span>
                                <label class="analytics-check"><input type="checkbox" name="server_meta_enabled" value="1" <?= !empty($serverSettings['meta']['enabled']) ? 'checked' : ''; ?>> Aktifkan pengiriman data ke Meta</label>
                                <label class="analytics-check analytics-sync-check"><input type="checkbox" name="server_meta_use_browser_pixel_id" id="server_meta_use_browser_pixel_id" value="1" <?= !empty($serverSettings['sync']['meta_use_browser_pixel_id']) ? 'checked' : ''; ?>> Gunakan Meta Pixel ID dari Pixel Iklan di atas</label>
                                <label>Meta Pixel/Dataset ID
                                    <input type="text" name="server_meta_dataset_id" id="server_meta_dataset_id" data-sync-source="analytics_meta_pixel_id" data-sync-toggle="server_meta_use_browser_pixel_id" value="<?= esc((string)($serverSettings['meta']['dataset_id'] ?? '')); ?>" placeholder="123456789012345">
                                    <small class="analytics-field-help" id="server_meta_sync_help">Samakan dengan Meta Pixel ID jika memakai pixel di browser. Token tetap wajib diisi untuk pengiriman server.</small>
                                </label>
                                <label>Token Akses Meta
                                    <input type="password" name="server_meta_access_token" value="" placeholder="<?= esc(function_exists('server_conversion_mask_secret') ? server_conversion_mask_secret((string)($serverSettings['meta']['access_token'] ?? '')) : 'Isi token baru'); ?>">
                                </label>
                                <label>Versi API Meta
                                    <input type="text" name="server_meta_api_version" value="<?= esc((string)($serverSettings['meta']['api_version'] ?? 'v20.0')); ?>" placeholder="v20.0">
                                </label>
                                <label>Kode Tes Event
                                    <input type="text" name="server_meta_test_event_code" value="<?= esc((string)($serverSettings['meta']['test_event_code'] ?? '')); ?>" placeholder="TEST12345">
                                </label>
                            </div>

                            <div class="analytics-platform-card">
                                <span class="analytics-mini-badge">Pengiriman Server TikTok</span>
                                <label class="analytics-check"><input type="checkbox" name="server_tiktok_enabled" value="1" <?= !empty($serverSettings['tiktok']['enabled']) ? 'checked' : ''; ?>> Aktifkan pengiriman data ke TikTok</label>
                                <label class="analytics-check analytics-sync-check"><input type="checkbox" name="server_tiktok_use_browser_pixel_id" id="server_tiktok_use_browser_pixel_id" value="1" <?= !empty($serverSettings['sync']['tiktok_use_browser_pixel_id']) ? 'checked' : ''; ?>> Gunakan TikTok Pixel ID dari Pixel Iklan di atas</label>
                                <label>TikTok Pixel ID
                                    <input type="text" name="server_tiktok_pixel_id" id="server_tiktok_pixel_id" data-sync-source="analytics_tiktok_pixel_id" data-sync-toggle="server_tiktok_use_browser_pixel_id" value="<?= esc((string)($serverSettings['tiktok']['pixel_id'] ?? '')); ?>" placeholder="CXXXXXXXXXXXXXXX">
                                    <small class="analytics-field-help" id="server_tiktok_sync_help">Samakan dengan TikTok Pixel ID jika memakai pixel di browser. Token tetap wajib diisi untuk pengiriman server.</small>
                                </label>
                                <label>TikTok Access Token
                                    <input type="password" name="server_tiktok_access_token" value="" placeholder="<?= esc(function_exists('server_conversion_mask_secret') ? server_conversion_mask_secret((string)($serverSettings['tiktok']['access_token'] ?? '')) : 'Isi token baru'); ?>">
                                </label>
                                <label>Versi API TikTok
                                    <input type="text" name="server_tiktok_api_version" value="<?= esc((string)($serverSettings['tiktok']['api_version'] ?? 'v1.3')); ?>" placeholder="v1.3">
                                </label>
                                <label>Kode Tes Event
                                    <input type="text" name="server_tiktok_test_event_code" value="<?= esc((string)($serverSettings['tiktok']['test_event_code'] ?? '')); ?>" placeholder="Opsional">
                                </label>
                            </div>

                            <div class="analytics-platform-card analytics-platform-card--google-foundation">
                                <div class="analytics-google-head">
                                    <span class="analytics-mini-badge">Tracking Konversi Google Ads</span>
                                    <span class="analytics-badge analytics-badge--info">Catat Klik Iklan</span>
                                    <span class="analytics-badge analytics-badge--info">Pengelompokan Siap</span>
                                    <span class="analytics-badge <?= !empty($googleAdsSenderStatus['enabled']) ? (!empty($googleAdsSenderStatus['ready']) ? 'analytics-badge--ok' : 'analytics-badge--warn') : 'analytics-badge--off'; ?>"><?= !empty($googleAdsSenderStatus['enabled']) ? esc((string)($googleAdsSenderStatus['status_label'] ?? 'Pengiriman')) : 'Pengiriman Nonaktif'; ?></span>
                                    <span class="analytics-badge <?= !empty($googleAdsFoundationStatus['oauth_required']) ? 'analytics-badge--warn' : 'analytics-badge--ok'; ?>"><?= !empty($googleAdsFoundationStatus['oauth_required']) ? 'Koneksi Dibutuhkan' : 'Koneksi Siap'; ?></span>
                                </div>
                                <label class="analytics-check"><input type="checkbox" name="server_google_ads_enabled" value="1" <?= !empty($serverSettings['google_ads']['enabled']) ? 'checked' : ''; ?>> Aktifkan tracking konversi Google Ads</label>
                                <div class="analytics-form-grid">
                                    <label class="analytics-check"><input type="checkbox" name="server_google_ads_capture_click_ids_enabled" value="1" <?= !array_key_exists('capture_click_ids_enabled', (array)($serverSettings['google_ads'] ?? [])) || !empty($serverSettings['google_ads']['capture_click_ids_enabled']) ? 'checked' : ''; ?>> Catat data klik dari iklan Google</label>
                                    <label class="analytics-check"><input type="checkbox" name="server_google_ads_queue_enabled" value="1" <?= !array_key_exists('queue_enabled', (array)($serverSettings['google_ads'] ?? [])) || !empty($serverSettings['google_ads']['queue_enabled']) ? 'checked' : ''; ?>> Simpan data Google Ads yang perlu diproses</label>
                                    <label class="analytics-check"><input type="checkbox" name="server_google_ads_sender_enabled" value="1" <?= !empty($serverSettings['google_ads']['sender']['enabled']) ? 'checked' : ''; ?>> Aktifkan Google Ads API Pengiriman</label>
                                    <label class="analytics-check"><input type="checkbox" name="server_google_ads_sender_validate_only" value="1" <?= !array_key_exists('validate_only', (array)($serverSettings['google_ads']['sender'] ?? [])) || !empty($serverSettings['google_ads']['sender']['validate_only']) ? 'checked' : ''; ?>> Mode tes <small class="analytics-field-help">Aman untuk uji coba, belum mencatat konversi live.</small></label>
                                    <label class="analytics-check"><input type="checkbox" name="server_google_ads_sender_partial_failure" value="1" <?= !array_key_exists('partial_failure', (array)($serverSettings['google_ads']['sender'] ?? [])) || !empty($serverSettings['google_ads']['sender']['partial_failure']) ? 'checked' : ''; ?>> Tetap proses data lain jika sebagian gagal</label>
                                    <label>Limit Pengiriman / Run
                                        <input type="number" name="server_google_ads_sender_max_events_per_run" value="<?= (int)($serverSettings['google_ads']['sender']['max_events_per_run'] ?? 10); ?>" min="1" max="100">
                                    </label>
                                </div>
                                <div class="analytics-google-note">
                                    <strong>Mode aman.</strong> Website mencatat data klik iklan, menyimpan data koneksi dengan aman, dan bisa mengirim konversi ke Google Ads setelah pengaturan lengkap. Mode tes aktif secara bawaan.
                                </div>

                                <div class="analytics-form-grid">
                                    <label>Google Ads ID Akun Google Ads
                                        <input type="text" name="server_google_ads_customer_id" value="<?= esc((string)($serverSettings['google_ads']['customer_id'] ?? '')); ?>" placeholder="1234567890">
                                        <small class="analytics-field-help">Isi angka ID Akun Google Ads tanpa strip. Ini bukan token.</small>
                                    </label>
                                    <label>ID Konversi Google Ads Utama
                                        <input type="text" name="server_google_ads_conversion_action_id" value="<?= esc((string)($serverSettings['google_ads']['conversion_action_id'] ?? '')); ?>" placeholder="customers/1234567890/conversionActions/456">
                                        <small class="analytics-field-help">Dipakai jika aktivitas di bawah belum punya ID konversi khusus.</small>
                                    </label>
                                    <label>Mata Uang
                                        <input type="text" name="server_google_ads_currency" value="<?= esc((string)($serverSettings['google_ads']['currency'] ?? 'IDR')); ?>" placeholder="IDR" maxlength="3">
                                    </label>
                                </div>

                                <div class="analytics-google-kpis">
                                    <div class="analytics-google-kpi"><span>Total Antrian</span><strong><?= (int)($googleAdsQueueSummary['counts']['total'] ?? 0); ?></strong></div>
                                    <div class="analytics-google-kpi"><span>Siap</span><strong><?= (int)($googleAdsQueueSummary['counts']['ready_for_sender'] ?? 0); ?></strong></div>
                                    <div class="analytics-google-kpi"><span>ID Klik Kosong</span><strong><?= (int)($googleAdsQueueSummary['counts']['missing_click_id'] ?? 0); ?></strong></div>
                                    <div class="analytics-google-kpi"><span>Pengaturan Kosong</span><strong><?= (int)($googleAdsQueueSummary['counts']['missing_mapping'] ?? 0); ?></strong></div>
                                </div>

                                <h3>Pengelompokan Event Google Ads</h3>
                                <p class="analytics-table-note">Setiap aktivitas penting bisa diarahkan ke konversi Google Ads yang berbeda. Jika kosong, sistem memakai pengaturan utama di atas.</p>
                                <div class="analytics-google-mapping">
                                    <?php foreach ((array)($googleAdsMappingSummary['rows'] ?? []) as $eventName => $mapRow): ?>
                                        <div class="analytics-google-map-row">
                                            <label class="analytics-check"><input type="checkbox" name="server_google_ads_map_<?= esc((string)$eventName); ?>_enabled" value="1" <?= !empty($mapRow['enabled']) ? 'checked' : ''; ?>> <?= esc((string)$eventName); ?></label>
                                            <label>Label
                                                <input type="text" name="server_google_ads_map_<?= esc((string)$eventName); ?>_label" value="<?= esc((string)($mapRow['label'] ?? $eventName)); ?>">
                                            </label>
                                            <label>ID Konversi Google Ads
                                                <input type="text" name="server_google_ads_map_<?= esc((string)$eventName); ?>_conversion_action_id" value="<?= esc((string)($serverSettings['google_ads']['mapping'][$eventName]['conversion_action_id'] ?? '')); ?>" placeholder="customers/123/conversionActions/456">
                                            </label>
                                            <label>Nilai Default
                                                <input type="number" name="server_google_ads_map_<?= esc((string)$eventName); ?>_default_value" value="<?= (int)($mapRow['default_value'] ?? 0); ?>" min="0" max="999999999">
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="analytics-table-note"><?= esc((string)($googleAdsFoundationStatus['message'] ?? 'Tracking Google Ads sudah menyiapkan pencatatan klik dan pengelompokan event. Pengiriman otomatis bisa diaktifkan setelah akun Google Ads tersambung.')); ?></p>
                            </div>
                        </div>

                        <button type="submit" class="admin-btn admin-btn--primary" style="margin-top:14px">Simpan Pengaturan Server</button>
                    </form>

                    <section class="analytics-card" style="margin-top:14px">
                        <div class="analytics-vault-panel">
                                    <div class="analytics-google-head">
                                        <span class="analytics-mini-badge">Pengaturan Analytics & Iklan</span>
                                        <span class="analytics-badge <?= !empty($googleAdsVaultStatus['enabled']) ? 'analytics-badge--info' : 'analytics-badge--off'; ?>"><?= !empty($googleAdsVaultStatus['enabled']) ? 'Aktif' : 'Nonaktif'; ?></span>
                                        <span class="analytics-badge <?= !empty($googleAdsVaultStatus['encrypted_all']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsVaultStatus['encrypted_all']) ? 'Terlindungi' : 'Perlu dicek'; ?></span>
                                        <span class="analytics-badge <?= !empty($googleAdsSenderStatus['enabled']) ? (!empty($googleAdsSenderStatus['ready']) ? 'analytics-badge--ok' : 'analytics-badge--warn') : 'analytics-badge--off'; ?>"><?= esc((string)($googleAdsSenderStatus['status_label'] ?? 'Pengiriman Nonaktif')); ?></span>
                                    </div>
                                    <p class="analytics-table-note"><?= esc((string)($googleAdsVaultStatus['message'] ?? 'Simpan data koneksi Google Ads dengan aman.')); ?></p>
                                    <div class="analytics-vault-grid">
                                        <div class="analytics-vault-item"><strong>Token Developer</strong><span class="analytics-badge <?= !empty($googleAdsVaultStatus['developer_token_set']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsVaultStatus['developer_token_set']) ? 'Ada' : 'Kosong'; ?></span><small><?= esc((string)($googleAdsVaultStatus['developer_token_mask'] ?? '')); ?></small></div>
                                        <div class="analytics-vault-item"><strong>Client ID Google</strong><span class="analytics-badge <?= !empty($googleAdsVaultStatus['oauth_client_id_set']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsVaultStatus['oauth_client_id_set']) ? 'Ada' : 'Kosong'; ?></span><small><?= esc((string)($googleAdsVaultStatus['client_id_mask'] ?? '')); ?></small></div>
                                        <div class="analytics-vault-item"><strong>Client Secret</strong><span class="analytics-badge <?= !empty($googleAdsVaultStatus['oauth_client_secret_set']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsVaultStatus['oauth_client_secret_set']) ? 'Ada' : 'Kosong'; ?></span><small><?= esc((string)($googleAdsVaultStatus['client_secret_mask'] ?? '')); ?></small></div>
                                        <div class="analytics-vault-item"><strong>Token Koneksi</strong><span class="analytics-badge <?= !empty($googleAdsVaultStatus['refresh_token_set']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsVaultStatus['refresh_token_set']) ? 'Ada' : 'Kosong'; ?></span><small><?= esc((string)($googleAdsVaultStatus['refresh_token_mask'] ?? '')); ?></small></div>
                                        <div class="analytics-vault-item"><strong>ID Akun Google Ads Login</strong><span class="analytics-badge <?= !empty($googleAdsVaultStatus['login_customer_id_set']) ? 'analytics-badge--ok' : 'analytics-badge--info'; ?>"><?= !empty($googleAdsVaultStatus['login_customer_id_set']) ? 'Ada' : 'Opsional'; ?></span><small><?= esc((string)($googleAdsVaultStatus['login_customer_id'] ?? '')); ?></small></div>
                                        <div class="analytics-vault-item"><strong>Versi API</strong><span class="analytics-badge analytics-badge--info"><?= esc((string)($googleAdsVaultStatus['api_version'] ?: 'Belum diset')); ?></span><small>Kosongkan jika ingin memakai versi bawaan.</small></div>
                                        <div class="analytics-vault-item"><strong>Koneksi Siap</strong><span class="analytics-badge <?= !empty($googleAdsVaultStatus['oauth_ready']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsVaultStatus['oauth_ready']) ? 'Siap' : 'Belum'; ?></span><small>Client ID + Secret + Token Koneksi.</small></div>
                                        <div class="analytics-vault-item"><strong>Syarat Pengiriman</strong><span class="analytics-badge <?= !empty($googleAdsVaultStatus['sender_prereq_ready']) ? 'analytics-badge--ok' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsVaultStatus['sender_prereq_ready']) ? 'Siap' : 'Belum'; ?></span><small>Data koneksi + ID Akun Google Ads + ID konversi Google Ads.</small></div>
                                    </div>

                                    <form method="post" class="analytics-vault-form">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="form_action" value="save_google_ads_api_credentials">
                                        <label class="analytics-check"><input type="checkbox" name="google_ads_api_vault_enabled" value="1" <?= !empty($googleAdsVaultStatus['enabled']) ? 'checked' : ''; ?>> Aktifkan penyimpanan data koneksi Google Ads</label>
                                        <label>Google Ads Versi API
                                            <input type="text" name="google_ads_api_version" value="<?= esc((string)($googleAdsVaultStatus['api_version'] ?? '')); ?>" placeholder="v24">
                                            <small class="analytics-field-help">Kosongkan jika ingin memakai versi bawaan.</small>
                                        </label>
                                        <label>Token Developer
                                            <input type="password" name="google_ads_developer_token" value="" placeholder="<?= !empty($googleAdsVaultStatus['developer_token_set']) ? 'Sudah tersimpan — isi hanya jika ingin replace' : 'Masukkan token developer'; ?>" autocomplete="off">
                                        </label>
                                        <label>Client ID Google
                                            <input type="password" name="google_ads_oauth_client_id" value="" placeholder="<?= !empty($googleAdsVaultStatus['oauth_client_id_set']) ? 'Sudah tersimpan — isi hanya jika ingin replace' : 'Masukkan Client ID Google'; ?>" autocomplete="off">
                                        </label>
                                        <label>Client Secret Google
                                            <input type="password" name="google_ads_oauth_client_secret" value="" placeholder="<?= !empty($googleAdsVaultStatus['oauth_client_secret_set']) ? 'Sudah tersimpan — isi hanya jika ingin replace' : 'Masukkan Client Secret Google'; ?>" autocomplete="off">
                                        </label>
                                        <label>Token Koneksi Google Ads
                                            <input type="password" name="google_ads_refresh_token" value="" placeholder="<?= !empty($googleAdsVaultStatus['refresh_token_set']) ? 'Sudah tersimpan — isi hanya jika ingin replace' : 'Masukkan token koneksi'; ?>" autocomplete="off">
                                        </label>
                                        <label>ID Akun Google Ads Login / MCC ID Opsional
                                            <input type="text" name="google_ads_login_customer_id" value="<?= esc((string)($googleAdsVaultStatus['login_customer_id'] ?? '')); ?>" placeholder="1234567890">
                                            <small class="analytics-field-help">Isi angka tanpa strip jika memakai manager account Google Ads.</small>
                                        </label>
                                        <label>Catatan Internal
                                            <input type="text" name="google_ads_api_notes" value="<?= esc((string)($googleAdsVaultStatus['notes'] ?? '')); ?>" placeholder="Contoh: akun Google Ads utama">
                                        </label>
                                        <div class="analytics-google-note" style="grid-column:1/-1">
                                            <strong>Catatan keamanan:</strong> field rahasia tidak ditampilkan ulang. Kosongkan field rahasia untuk mempertahankan token lama. Isi hanya saat setup pertama atau mengganti token.
                                        </div>
                                        <div class="analytics-mini-actions" style="grid-column:1/-1">
                                            <button type="submit" class="admin-btn admin-btn--primary">Simpan Data Koneksi Google Ads</button>
                                        </div>
                                    </form>
                                    <form method="post" class="analytics-vault-danger" onsubmit="return confirm('Hapus semua Google Ads API credential tersimpan?')">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="form_action" value="clear_google_ads_api_credentials">
                                        <strong>Zona Hati-hati</strong>
                                        <p class="analytics-table-note">Hapus data koneksi jika salah akun, pindah akun Google Ads, atau ingin reset total. Ini tidak menghapus riwayat event.</p>
                                        <button type="submit" class="admin-btn admin-btn--ghost">Hapus Data Koneksi Google Ads</button>
                                    </form>
                                </div>
                    </section>

                    <section class="analytics-card" style="margin-top:14px;background:#f8fafc">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="analytics-mini-badge">Pengiriman Konversi Google Ads</span>
                                <h3>Kontrol Pengiriman</h3>
                                <p class="analytics-table-note"><?= esc((string)($googleAdsSenderStatus['message'] ?? 'Pengiriman Google Ads belum aktif.')); ?></p>
                            </div>
                            <div class="admin-table-actions analytics-mini-actions">
                                <form method="post" onsubmit="return confirm('Jalankan pengiriman Google Ads untuk data yang sudah siap?')">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="google_ads_sender_send_ready">
                                    <input type="hidden" name="google_ads_send_limit" value="<?= (int)($googleAdsSenderStatus['max_events_per_run'] ?? 10); ?>">
                                    <button class="admin-btn admin-btn--primary" type="submit">Kirim / Cek yang Siap</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Coba ulang data Google Ads yang gagal?')">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="google_ads_sender_retry_failed">
                                    <input type="hidden" name="google_ads_send_limit" value="<?= (int)($googleAdsSenderStatus['max_events_per_run'] ?? 10); ?>">
                                    <button class="admin-btn admin-btn--soft" type="submit">Coba Ulang yang Gagal</button>
                                </form>
                            </div>
                        </div>
                        <div class="analytics-vault-grid">
                            <div class="analytics-vault-item"><strong>Status</strong><span class="analytics-badge <?= !empty($googleAdsSenderStatus['ready']) ? 'analytics-badge--ok' : (!empty($googleAdsSenderStatus['enabled']) ? 'analytics-badge--warn' : 'analytics-badge--off'); ?>"><?= esc((string)($googleAdsSenderStatus['status_label'] ?? 'Pengiriman Nonaktif')); ?></span><small>Aktif melalui pengaturan di atas.</small></div>
                            <div class="analytics-vault-item"><strong>Mode</strong><span class="analytics-badge <?= !empty($googleAdsSenderStatus['validate_only']) ? 'analytics-badge--info' : 'analytics-badge--warn'; ?>"><?= !empty($googleAdsSenderStatus['validate_only']) ? 'Mode Tes' : 'Kirim Langsung'; ?></span><small><?= !empty($googleAdsSenderStatus['validate_only']) ? 'Aman untuk test API.' : 'Conversion bisa tercatat live di Google Ads.'; ?></small></div>
                            <div class="analytics-vault-item"><strong>Batas per Proses</strong><span class="analytics-badge analytics-badge--info"><?= (int)($googleAdsSenderStatus['max_events_per_run'] ?? 10); ?></span><small>Disarankan kecil dulu saat uji coba.</small></div>
                            <div class="analytics-vault-item"><strong>Terakhir Diproses</strong><span class="analytics-badge analytics-badge--off"><?= esc((string)($googleAdsSenderStatus['last_run_at'] ?? 'Belum ada')); ?></span><small><?= esc(json_encode($googleAdsSenderStatus['last_result'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?></small></div>
                        </div>
                        <div class="analytics-google-note" style="margin-top:12px">
                            <strong>Catatan keamanan:</strong> gunakan mode tes dulu sampai pengaturan Google Ads benar. Kirim langsung hanya saat ID customer, label konversi, dan data klik sudah valid.
                        </div>
                    </section>

                    <div class="analytics-kpis" style="margin-top:18px">
                        <div class="analytics-kpi"><span>Total Antrian</span><strong><?= (int)($serverSummary['counts']['total'] ?? 0); ?></strong><small>Menunggu diproses</small></div>
                        <div class="analytics-kpi"><span>Menunggu</span><strong><?= (int)($serverSummary['counts']['pending'] ?? 0); ?></strong><small>Siap dikirim</small></div>
                        <div class="analytics-kpi"><span>Terkirim</span><strong><?= (int)($serverSummary['counts']['sent'] ?? 0); ?></strong><small>Berhasil dikirim</small></div>
                        <div class="analytics-kpi"><span>Gagal</span><strong><?= (int)($serverSummary['counts']['failed'] ?? 0); ?></strong><small>Bisa dicoba ulang</small></div>
                        <div class="analytics-kpi"><span>Diabaikan</span><strong><?= (int)($serverSummary['counts']['ignored'] ?? 0); ?></strong><small>Diabaikan manual</small></div>
                    </div>

                    <section class="analytics-card" style="margin-top:14px;background:#f8fafc">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="analytics-mini-badge">Data Google Ads</span>
                                <h3>Persiapan & Riwayat Pengiriman Data Iklan</h3>
                                <p class="analytics-table-note">Status siap berarti data bisa diproses. Status tes berarti pengecekan berhasil, sedangkan terkirim berarti data sudah dikirim ke Google Ads.</p>
                            </div>
                            <div class="admin-table-actions analytics-mini-actions">
                                <a class="admin-btn admin-btn--soft" href="<?= esc(admin_analytics_current_url(['action' => 'export_google_ads_foundation_csv'])); ?>">Export Data Google Ads</a>
                                <form method="post" onsubmit="return confirm('Abaikan data Google Ads yang dipilih/gagal?')">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="google_ads_sender_mark_ignored">
                                    <button class="admin-btn admin-btn--ghost" type="submit">Abaikan Data Terpilih/Gagal</button>
                                </form>
                                <form method="post" onsubmit="return confirm('Bersihkan data Google Ads lama?')">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="form_action" value="google_ads_foundation_clear_old">
                                    <input type="hidden" name="google_ads_clear_days" value="90">
                                    <button class="admin-btn admin-btn--ghost" type="submit">Bersihkan Data Lama Google Ads</button>
                                </form>
                            </div>
                        </div>
                        <form method="post">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="google_ads_sender_mark_ignored">
                            <div class="admin-table-wrap admin-table-wrap--comfortable">
                                <table class="admin-table">
                                    <thead><tr><th>Pilih</th><th>Dibuat</th><th>Event</th><th>Status</th><th>ID Klik</th><th>Konversi Google Ads</th><th>Nilai</th><th>Percobaan</th><th>HTTP</th><th>Catatan</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($googleAdsDebugRows)): ?>
                                            <tr><td colspan="10">Belum ada data Google Ads. Data akan muncul setelah ada pengunjung dari iklan lalu melakukan form masuk, checkout, order, atau pembayaran.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ((array)$googleAdsDebugRows as $gaRow): ?>
                                            <?php $gaStatus = (string)($gaRow['status'] ?? ''); ?>
                                            <tr>
                                                <td><?php if (!in_array($gaStatus, ['sent', 'validated'], true)): ?><input type="checkbox" name="google_ads_queue_ids[]" value="<?= esc((string)($gaRow['id'] ?? '')); ?>"><?php endif; ?></td>
                                                <td><small><?= esc((string)($gaRow['created_at'] ?? '-')); ?></small></td>
                                                <td><strong><?= esc((string)($gaRow['event_name'] ?? '-')); ?></strong><br><small><?= esc((string)($gaRow['event_id'] ?? '')); ?></small></td>
                                                <td><span class="analytics-badge <?= in_array($gaStatus, ['ready_for_sender', 'sent', 'validated'], true) ? 'analytics-badge--ok' : ($gaStatus === 'failed' || $gaStatus === 'missing_click_id' || $gaStatus === 'missing_mapping' ? 'analytics-badge--warn' : 'analytics-badge--off'); ?>"><?= esc($gaStatus ?: '-'); ?></span></td>
                                                <td><small><?= esc((string)($gaRow['click_id_type'] ?? '-')); ?> · <?= esc((string)($gaRow['click_id_mask'] ?? 'Belum ada')); ?></small></td>
                                                <td><small><?= esc((string)($gaRow['conversion_action_id'] ?? '-')); ?></small></td>
                                                <td><?= (int)($gaRow['conversion_value'] ?? 0); ?> <?= esc((string)($gaRow['currency'] ?? 'IDR')); ?></td>
                                                <td><?= (int)($gaRow['attempts'] ?? 0); ?></td>
                                                <td><?= (int)($gaRow['http_status'] ?? 0); ?></td>
                                                <td><small><?= esc((string)($gaRow['last_error'] ?? '') ?: (string)($gaRow['last_response'] ?? '') ?: (string)($gaRow['reason'] ?? '-')); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="analytics-mini-actions" style="margin-top:10px">
                                <button class="admin-btn admin-btn--ghost" type="submit">Abaikan Data Terpilih/Gagal</button>
                            </div>
                        </form>
                    </section>

                    <?php if (!empty($serverPreview)): ?>
                        <section class="analytics-card" style="margin-top:14px;background:#f8fafc">
                            <h3>Preview Data</h3>
                            <p class="analytics-table-note">Preview ini aman untuk dicek. Token tidak ditampilkan dan data pribadi customer tidak ditampilkan mentah.</p>
                            <pre class="analytics-pre"><?= esc(json_encode($serverPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}'); ?></pre>
                        </section>
                    <?php endif; ?>

                    <section class="analytics-monitor-center">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="analytics-mini-badge">Monitoring Pengiriman</span>
                                <h3>Riwayat Pengiriman Meta/TikTok</h3>
                                <p class="analytics-table-note">Cek data yang menunggu, terkirim, gagal, atau diabaikan dari Meta/TikTok. Google Ads punya panel terpisah agar laporan lebih rapi.</p>
                            </div>
                            <form method="get" action="<?= url('admin/analytics'); ?>" class="admin-filter-inline">
                                <input type="hidden" name="range" value="<?= esc(admin_analytics_range()); ?>">
                                <label>Status
                                    <select name="server_status">
                                        <option value="" <?= $serverDebugStatus === '' ? 'selected' : ''; ?>>Semua</option>
                                        <?php foreach (['pending' => 'Menunggu', 'sent' => 'Terkirim', 'failed' => 'Gagal', 'ignored' => 'Diabaikan'] as $value => $label): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= $serverDebugStatus === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button class="admin-btn admin-btn--soft" type="submit">Filter</button>
                            </form>
                        </div>

                        <div class="analytics-monitor-legend">
                            <div><span class="analytics-badge analytics-badge--ok">pending</span><strong>Menunggu dikirim</strong><small>Aman untuk Kirim yang Menunggu.</small></div>
                            <div><span class="analytics-badge analytics-badge--ok">sent</span><strong>Sudah sukses</strong><small>Bisa dibersihkan lewat Bersihkan Data Terkirim Lama.</small></div>
                            <div><span class="analytics-badge analytics-badge--warn">gagal</span><strong>Gagal dari API</strong><small>Cek response ringkas lalu Coba Ulang yang Gagal.</small></div>
                            <div><span class="analytics-badge analytics-badge--off">ignored</span><strong>Diabaikan admin</strong><small>Tidak ikut retry otomatis.</small></div>
                        </div>

                        <form method="post" onsubmit="return confirm('Abaikan data terpilih? Jika tidak memilih data, semua data gagal akan diabaikan.')">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="form_action" value="server_conversion_mark_ignored">
                            <div class="analytics-mini-actions" style="margin-bottom:10px">
                                <button class="admin-btn admin-btn--ghost" type="submit">Abaikan Data Terpilih/Gagal</button>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(admin_analytics_current_url(['server_preview' => '1'])); ?>">Lihat Preview Data</a>
                            </div>
                            <div class="admin-table-wrap admin-table-wrap--comfortable">
                                <table class="admin-table analytics-monitor-table">
                                    <thead><tr><th>Pilih</th><th>Dibuat</th><th>Event</th><th>Event ID</th><th>Status</th><th>Platform</th><th>Percobaan</th><th>Jawaban Ringkas</th><th>Aksi</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($serverDebugRows)): ?>
                                            <tr><td colspan="9">Belum ada data untuk filter ini. Aktifkan pengaturan lalu coba form, WhatsApp, checkout, atau order.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ((array)$serverDebugRows as $row): ?>
                                            <?php $rowStatus = (string)($row['status'] ?? 'pending'); ?>
                                            <tr>
                                                <td><?php if ($rowStatus !== 'sent'): ?><input type="checkbox" name="queue_ids[]" value="<?= esc((string)($row['id'] ?? '')); ?>"><?php endif; ?></td>
                                                <td><small><?= esc((string)($row['created_at'] ?? '-')); ?></small></td>
                                                <td><strong><?= esc((string)($row['event_name'] ?? '-')); ?></strong><br><small><?= esc((string)($row['event_group'] ?? '')); ?> · <?= esc((string)($row['source'] ?? '')); ?></small></td>
                                                <td><code><?= esc((string)($row['event_id'] ?? '-')); ?></code></td>
                                                <td><span class="analytics-badge analytics-badge--<?= $rowStatus === 'sent' ? 'ok' : ($rowStatus === 'failed' ? 'warn' : ($rowStatus === 'ignored' ? 'off' : 'ok')); ?>"><?= esc($rowStatus); ?></span></td>
                                                <td><small><?php foreach ((array)($row['platforms'] ?? []) as $platformKey => $platformRow): ?> <span class="analytics-badge <?= (string)($platformRow['status'] ?? 'pending') === 'sent' ? 'analytics-badge--ok' : (((string)($platformRow['status'] ?? 'pending') === 'failed') ? 'analytics-badge--warn' : 'analytics-badge--off'); ?>"><?= esc(strtoupper((string)$platformKey) . ':' . (string)($platformRow['status'] ?? 'pending')); ?></span><?php endforeach; ?></small></td>
                                                <td><?= (int)($row['attempts'] ?? 0); ?></td>
                                                <td><small><?= esc((string)($row['response_summary'] ?? '-')); ?></small></td>
                                                <td><a class="admin-btn admin-btn--soft admin-btn--xs" href="<?= esc(admin_analytics_current_url(['server_preview' => '1', 'queue_id' => (string)($row['id'] ?? '')])); ?>">Preview</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </section>

                    <div class="admin-table-wrap admin-table-wrap--comfortable" style="margin-top:14px">
                        <table class="admin-table">
                            <thead><tr><th>Waktu</th><th>Action</th><th>Platform</th><th>Event</th><th>Status</th><th>HTTP</th><th>Response/Error</th></tr></thead>
                            <tbody>
                                <?php if (empty($serverLogs)): ?>
                                    <tr><td colspan="7">Belum ada riwayat pengiriman.</td></tr>
                                <?php endif; ?>
                                <?php foreach ((array)$serverLogs as $logRow): ?>
                                    <tr>
                                        <td><small><?= esc((string)($logRow['time'] ?? '-')); ?></small></td>
                                        <td><?= esc((string)($logRow['action'] ?? '-')); ?></td>
                                        <td><?= esc((string)($logRow['platform'] ?? '-')); ?></td>
                                        <td><strong><?= esc((string)($logRow['event_name'] ?? '-')); ?></strong><br><small><?= esc((string)($logRow['event_id'] ?? '')); ?></small></td>
                                        <td><span class="analytics-badge <?= (string)($logRow['status'] ?? '') === 'sent' || (string)($logRow['status'] ?? '') === 'ok' ? 'analytics-badge--ok' : (((string)($logRow['status'] ?? '') === 'failed') ? 'analytics-badge--warn' : 'analytics-badge--off'); ?>"><?= esc((string)($logRow['status'] ?? '-')); ?></span></td>
                                        <td><?= (int)($logRow['http_status'] ?? 0); ?></td>
                                        <td><small><?= esc((string)($logRow['error'] ?? '') ?: (string)($logRow['response'] ?? '')); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="analytics-table-note" style="margin-top:12px">Proteksi data: nama, email, nomor WhatsApp, alamat, token invoice/order, pesan customer, dan detail pembayaran tidak ditampilkan mentah di laporan. Data koneksi iklan tetap disimpan aman dan tidak ditampilkan ke publik.</p>
                </section>

                <div class="analytics-kpis">
                    <div class="analytics-kpi"><span>Lead Event</span><strong><?= (int)($summary['totals']['lead_events'] ?? 0); ?></strong><small>Ringkas</small></div>
                    <div class="analytics-kpi"><span>Form Masuk</span><strong><?= (int)($summary['totals']['inquiries'] ?? 0); ?></strong><small>Form masuk</small></div>
                    <div class="analytics-kpi"><span>Order</span><strong><?= (int)($summary['totals']['orders'] ?? 0); ?></strong><small>Order draft</small></div>
                    <div class="analytics-kpi"><span>Bukti Bayar</span><strong><?= (int)($summary['totals']['payment_proofs'] ?? 0); ?></strong><small>Bukti pembayaran</small></div>
                </div>

                <section class="analytics-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <h2>Dashboard Ringkas per Channel</h2>
                            <p class="analytics-table-note">Membantu membaca kontribusi Paid, Organic, Social, Referral, Chat/WhatsApp, dan Direct terhadap lead/order.</p>
                        </div>
                        <div class="admin-table-actions">
                            <form method="get" action="<?= url('admin/analytics'); ?>" class="admin-filter-inline">
                                <label>Range
                                    <select name="range">
                                        <?php foreach (['7' => '7 hari', '14' => '14 hari', '30' => '30 hari', '60' => '60 hari', '90' => '90 hari', '180' => '180 hari', '365' => '365 hari', 'all' => 'Semua'] as $value => $label): ?>
                                            <option value="<?= esc((string)$value); ?>" <?= admin_analytics_range() === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(admin_analytics_current_url(['action' => 'export_channel_csv'])); ?>">Export CSV</a>
                            </form>
                        </div>
                    </div>
                    <div class="admin-table-wrap admin-table-wrap--comfortable">
                        <table class="admin-table">
                            <thead><tr><th>Channel</th><th>Lead Event</th><th>Minat Tinggi</th><th>Form Masuk</th><th>Order</th><th>Bukti Bayar</th><th>Estimasi Penjualan</th></tr></thead>
                            <tbody>
                                <?php if (empty($summary['channels'])): ?>
                                    <tr><td colspan="7">Belum ada data attribution untuk range ini.</td></tr>
                                <?php endif; ?>
                                <?php foreach ((array)($summary['channels'] ?? []) as $row): ?>
                                    <tr>
                                        <td><strong><?= esc((string)($row['label'] ?? 'Direct')); ?></strong><br><small><?= esc((string)($row['channel'] ?? 'direct')); ?></small></td>
                                        <td><?= (int)($row['lead_events'] ?? 0); ?></td>
                                        <td><?= (int)($row['high_intent'] ?? 0); ?></td>
                                        <td><?= (int)($row['inquiries'] ?? 0); ?></td>
                                        <td><?= (int)($row['orders'] ?? 0); ?></td>
                                        <td><?= (int)($row['payment_proofs'] ?? 0); ?></td>
                                        <td><?= rupiah((int)($row['sales_estimate'] ?? 0)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="analytics-card analytics-guide" style="margin-top:18px">
                    <h2>Catatan untuk Laporan Owner</h2>
                    <ul>
                        <li><strong>Data detail tetap aman</strong> di halaman lead; halaman ini hanya membuat ringkasan channel yang lebih mudah dibaca.</li>
                        <li><strong>Parameter Campaign capture</strong> menyimpan first touch dan last touch secara lokal agar lead/order bisa dilihat asal campaign-nya.</li>
                        <li><strong>Pixel iklan dan pengiriman data server</strong> hanya membawa metadata aman: channel, source, campaign, page path, event type, value, dan hash consent-based jika diaktifkan.</li>
                        <li><strong>Search Console</strong> tetap dipakai untuk query, impression, CTR, dan posisi SEO; website ini melengkapi sisi lead/order/conversion.</li>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>


<script>
(function(){
    function byId(id){ return document.getElementById(id); }
    function syncField(targetId){
        var target = byId(targetId);
        if (!target) return;
        var source = byId(target.getAttribute('data-sync-source') || '');
        var toggle = byId(target.getAttribute('data-sync-toggle') || '');
        if (!source || !toggle) return;
        var apply = function(){
            if (toggle.checked) {
                target.value = source.value || '';
                target.setAttribute('readonly', 'readonly');
                target.classList.add('is-synced');
            } else {
                target.removeAttribute('readonly');
                target.classList.remove('is-synced');
            }
        };
        source.addEventListener('input', apply);
        toggle.addEventListener('change', apply);
        apply();
    }
    syncField('server_meta_dataset_id');
    syncField('server_tiktok_pixel_id');
})();
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
