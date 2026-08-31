<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GOOGLE ADS TRACKING TEST PAGE - Template
|--------------------------------------------------------------------------
| Admin-gated public-rendered page for Tag Assistant / GTM preview checks.
| It intentionally renders outside admin layout so Google tag, dataLayer,
| and direct pixel helpers can load like a normal public page.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (empty($_SESSION['admin_articles_logged_in'])) {
    redirect_302('admin/analytics');
}

$settings = function_exists('analytics_read_settings') ? analytics_read_settings() : [];
$verification = function_exists('analytics_google_ads_verification_summary') ? analytics_google_ads_verification_summary($settings) : [];
$directStatus = function_exists('analytics_google_ads_direct_status') ? analytics_google_ads_direct_status($settings) : [];
$events = function_exists('analytics_google_ads_test_events') ? analytics_google_ads_test_events() : [];

set_seo([
    'title' => 'Google Ads Tracking Test - Admin',
    'description' => 'Halaman tes untuk memastikan tracking Google Ads sudah terbaca dengan benar.',
    'robots' => 'noindex, nofollow',
    'canonical' => strtok(current_url(), '?') ?: url('admin/google-ads-tracking-test'),
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= esc(url('admin/analytics')); ?>">Admin Analytics</a>
            <span>/</span>
            <span>Google Ads Tracking Test</span>
        </div>
        <span class="dynamic-mini-label">Pusat Tes Iklan</span>
        <h1>Tes Tracking Google Ads</h1>
        <p>Gunakan halaman ini untuk mengecek apakah klik dan form dari website terbaca oleh Google Ads. Jalankan tes hanya saat setup iklan.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <style>
            .ga-test-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;align-items:start}.ga-test-card{border:1px solid #e2e8f0;background:#fff;border-radius:24px;padding:18px;box-shadow:0 14px 38px rgba(15,23,42,.06)}.ga-test-card h2,.ga-test-card h3{margin:.1rem 0 .55rem;color:#0f172a}.ga-test-card p,.ga-test-card small{color:#64748b}.ga-test-actions{display:grid;gap:10px}.ga-test-btn{border:0;border-radius:16px;padding:12px 14px;background:var(--admin-primary);color:#fff;font-weight:900;cursor:pointer;text-align:left}.ga-test-btn small{display:block;color:color-mix(in srgb,var(--admin-primary) 13%,#ffffff);margin-top:3px}.ga-test-badge{display:inline-flex;border-radius:999px;padding:4px 9px;font-size:.75rem;font-weight:900;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8}.ga-test-badge--ok{background:color-mix(in srgb,var(--bg) 82%,#ffffff);color:var(--admin-primary);border-color:var(--border)}.ga-test-badge--warn{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff);color:var(--admin-primary-dark);border-color:color-mix(in srgb,var(--admin-primary) 22%,#ffffff)}.ga-test-status{display:grid;gap:8px}.ga-test-status div{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;border:1px solid #e2e8f0;background:#f8fafc;border-radius:14px;padding:10px}.ga-test-log{background:#0f172a;color:#e2e8f0;border-radius:18px;padding:14px;min-height:180px;max-height:420px;overflow:auto;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.8rem;white-space:pre-wrap}.ga-test-note{border:1px solid color-mix(in srgb,var(--admin-primary) 22%,#ffffff);background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff);color:var(--admin-primary-dark);border-radius:16px;padding:12px;margin-bottom:14px}.ga-test-muted{font-size:.85rem;color:#64748b}.ga-test-url{word-break:break-all;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:10px;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:.82rem}@media(max-width:980px){.ga-test-grid{grid-template-columns:1fr}}
        </style>

        <div class="ga-test-note">
            <strong>Catatan aman:</strong> tombol di halaman ini membuat data tes tanpa nama, email, nomor WhatsApp, alamat, atau detail pembayaran. Jika Google Ads direct tag sudah aktif, data tes bisa terbaca sebagai conversion sesuai label yang dipasang. Pakai saat testing/Tag Assistant dulu.
        </div>

        <div class="ga-test-grid">
            <div class="ga-test-card">
                <h2>Tombol Tes</h2>
                <p>Klik satu per satu, lalu cek status di panel kanan dan alat pengecekan Google.</p>
                <div class="ga-test-actions">
                    <?php foreach ($events as $eventName => $event): ?>
                        <button type="button" class="ga-test-btn js-ga-test-event" data-event-name="<?= esc((string)$eventName); ?>" data-event-type="<?= esc((string)($event['type'] ?? $eventName)); ?>" data-event-channel="<?= esc((string)($event['channel'] ?? 'test')); ?>" data-event-intent="<?= esc((string)($event['intent'] ?? 'tracking-test')); ?>" data-event-category="<?= esc((string)($event['category'] ?? 'google-ads-test')); ?>" data-event-label="<?= esc((string)($event['label'] ?? $eventName)); ?>">
                            <?= esc((string)($event['label'] ?? $eventName)); ?>
                            <small><?= esc((string)($event['description'] ?? 'Simulasi tracking.')); ?></small>
                        </button>
                    <?php endforeach; ?>
                </div>
                <h3 style="margin-top:18px">Cara cek cepat</h3>
                <ol class="ga-test-muted">
                    <li>Buka halaman ini dari link tes Google Ads.</li>
                    <li>Aktifkan alat pengecekan Google Tag di browser.</li>
                    <li>Klik tombol test, lalu cek event <code>contact_whatsapp</code>, <code>submit_inquiry</code>, <code>begin_checkout</code>, <code>order_success</code>, atau <code>upload_payment_proof</code>.</li>
                    <li>Jika mode Direct/Hybrid aktif dan label benar, Google Ads conversion event akan punya nilai <code>send_to</code> format <code>AW-ID/label</code>.</li>
                </ol>
            </div>

            <aside class="ga-test-card">
                <h2>Status Tracking</h2>
                <p><?= esc((string)($directStatus['message'] ?? 'Google Ads tracking belum aktif.')); ?></p>
                <div class="ga-test-status">
                    <?php foreach ((array)($verification['checks'] ?? []) as $check): ?>
                        <div>
                            <span><?= esc((string)($check['label'] ?? '-')); ?><br><small><?= esc((string)($check['message'] ?? '')); ?></small></span>
                            <span class="ga-test-badge <?= !empty($check['ok']) ? 'ga-test-badge--ok' : 'ga-test-badge--warn'; ?>"><?= !empty($check['ok']) ? 'OK' : 'Cek'; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin-top:16px">URL Tes Saat Ini</h3>
                <div class="ga-test-url"><?= esc(current_url()); ?></div>

                <h3 style="margin-top:16px">Riwayat Tes</h3>
                <div id="gaTestLog" class="ga-test-log">Menunggu data tes...</div>
                <p class="ga-test-muted">Riwayat ini hanya tampil di browser admin untuk pengecekan.</p>
            </aside>
        </div>
    </div>
</section>

<script>
(function(){
    var logEl = document.getElementById('gaTestLog');
    function writeLog(row) {
        if (!logEl) return;
        var line = '[' + new Date().toLocaleTimeString() + '] ' + JSON.stringify(row, null, 2);
        logEl.textContent = (logEl.textContent === 'Menunggu data tes...' ? '' : logEl.textContent + '\n\n') + line;
        logEl.scrollTop = logEl.scrollHeight;
    }

    window.addEventListener('adsPixelTrackingDebug', function(event){
        writeLog(event.detail || {status: 'debug'});
    });

    document.querySelectorAll('.js-ga-test-event').forEach(function(button){
        button.addEventListener('click', function(){
            var eventName = button.getAttribute('data-event-name') || 'contact_whatsapp';
            var eventId = ('test_' + eventName + '_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8)).replace(/[^A-Za-z0-9_-]/g, '').slice(0, 90);
            var payload = {
                event_id: eventId,
                source: 'google-ads-verification-center',
                type: button.getAttribute('data-event-type') || eventName,
                channel: button.getAttribute('data-event-channel') || 'test',
                category: button.getAttribute('data-event-category') || 'google-ads-test',
                location: 'admin-test',
                intent: button.getAttribute('data-event-intent') || 'tracking-test',
                label: button.getAttribute('data-event-label') || eventName,
                page_path: window.location.pathname + window.location.search,
                target_url: window.location.href + '#test-' + eventName
            };

            writeLog({status: 'clicked', event_name: eventName, event_id: eventId, note: 'Dispatching test event'});

            if (typeof window.sendConversionEvent === 'function') {
                window.sendConversionEvent(window.__LEAD_TRACKING_ENDPOINT__ || '', payload);
                return;
            }

            if (typeof window.pushMarketingDataLayerEvent === 'function') {
                window.pushMarketingDataLayerEvent(payload);
                return;
            }

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({event: eventName, event_id: eventId, event_group: 'conversion', pii_safe: true});
            writeLog({status: 'fallback_datalayer', event_name: eventName, event_id: eventId});
        });
    });
})();
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
