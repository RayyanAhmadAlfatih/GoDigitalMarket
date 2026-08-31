<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$access = digital_delivery_clean((string)($_GET['access'] ?? ''), 120);
$ref = digital_delivery_clean((string)($_GET['ref'] ?? ''), 100);
$orderToken = digital_delivery_clean((string)($_GET['token'] ?? ''), 100);
$record = function_exists('digital_delivery_record_by_token') ? digital_delivery_record_by_token($access) : null;
$order = null;
$settings = function_exists('digital_delivery_read_settings') ? digital_delivery_read_settings() : [];

if ($record && function_exists('order_find_by_reference')) {
    $order = order_find_by_reference((string)($record['order_ref'] ?? $ref), $orderToken !== '' ? $orderToken : (string)($record['order_token'] ?? ''));
}

$tokenOk = true;
if (!empty($settings['require_order_token']) && $record) {
    $storedToken = (string)($record['order_token'] ?? '');
    $tokenOk = $storedToken === '' || ($orderToken !== '' && hash_equals($storedToken, $orderToken));
}

if ($record && $tokenOk && function_exists('digital_delivery_touch_open')) {
    digital_delivery_touch_open($access);
}

$valid = is_array($record) && $tokenOk;
$expired = $valid && function_exists('digital_delivery_record_is_expired') && digital_delivery_record_is_expired($record);
$downloadAllowed = $valid && function_exists('digital_delivery_record_download_allowed') && digital_delivery_record_download_allowed($record);
$title = $valid ? 'Akses Digital ' . (string)($record['product_title'] ?? 'Produk Digital') : 'Akses Digital Tidak Ditemukan';

set_seo([
    'title' => $title . ' - ' . SITE_NAME,
    'description' => 'Halaman akses produk digital yang dilindungi token order.',
    'robots' => 'noindex, nofollow',
    'canonical' => strtok(current_url(), '?') ?: url('digital-access'),
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<section class="mini-hero digital-access-hero--template">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span>Akses Digital</span></div>
        <span class="dynamic-mini-label">Digital Delivery</span>
        <h1><?= esc($valid ? 'Akses Digital Anda' : 'Akses Tidak Ditemukan'); ?></h1>
        <p><?= esc($valid ? (string)($settings['public_note'] ?? 'Simpan link akses ini dan jangan dibagikan.') : 'Link akses tidak valid, kedaluwarsa, atau token order tidak cocok.'); ?></p>
    </div>
</section>

<section class="section digital-access-section--template">
    <div class="container">
        <style>
            .digital-access-card--template{max-width:980px;margin:0 auto;background:#fff;border:1px solid #dbe7e2;border-radius:30px;box-shadow:0 22px 70px rgba(15,23,42,.07);padding:1.6rem}.digital-access-grid--template{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem;margin:1rem 0}.digital-access-grid--template span{display:block;border:1px solid #e2e8f0;background:#f8fafc;border-radius:18px;padding:.9rem;color:#475569}.digital-access-grid--template strong{display:block;color:#0f172a;font-size:.84rem;margin-bottom:.22rem}.digital-access-alert--template{border-radius:18px;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 82%,#fff);padding:1rem;color:var(--primary-dark);margin:1rem 0}.digital-access-alert--danger{border-color:#fecaca;background:#fef2f2;color:#991b1b}.digital-access-instruction--template{white-space:pre-line;border:1px dashed var(--border);background:var(--admin-soft);border-radius:20px;padding:1rem;color:#134e4a;margin:1rem 0}.digital-access-actions--template{display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1rem}.digital-access-actions--template .cta{text-decoration:none}.digital-access-muted--template{color:#64748b}@media(max-width:760px){.digital-access-grid--template{grid-template-columns:1fr}}
        </style>
        <div class="digital-access-card--template">
            <?php if (!$valid): ?>
                <div class="digital-access-alert--template digital-access-alert--danger">
                    Link akses tidak bisa dibuka. Pastikan Anda memakai link resmi dari halaman order/invoice atau hubungi admin dengan nomor order.
                </div>
                <div class="digital-access-actions--template"><a class="cta" href="<?= esc(wa_link('Halo Admin, saya butuh bantuan membuka akses digital. Ref: ' . $ref)); ?>" target="_blank" rel="nofollow noopener">Hubungi Admin</a></div>
            <?php else: ?>
                <span class="dynamic-mini-label"><?= esc((string)($record['delivery_type'] ?? 'digital')); ?></span>
                <h2><?= esc((string)($record['product_title'] ?? 'Produk Digital')); ?></h2>
                <?php if ($expired): ?>
                    <div class="digital-access-alert--template digital-access-alert--danger">Akses digital sudah kedaluwarsa. Hubungi admin untuk perpanjangan atau pengecekan ulang.</div>
                <?php elseif ((string)($record['status'] ?? 'active') !== 'active'): ?>
                    <div class="digital-access-alert--template digital-access-alert--danger">Akses digital sedang tidak aktif. Hubungi admin untuk bantuan.</div>
                <?php else: ?>
                    <div class="digital-access-alert--template">Akses digital aktif. Gunakan tombol akses/download di bawah dan simpan nomor order Anda.</div>
                <?php endif; ?>

                <div class="digital-access-grid--template">
                    <span><strong>No. Order</strong><?= esc((string)($record['order_ref'] ?? '-')); ?></span>
                    <span><strong>Pelanggan</strong><?= esc((string)($record['customer_name'] ?? '-')); ?></span>
                    <span><strong>Mode Akses</strong><?= esc((string)($record['access_mode'] ?? '-')); ?></span>
                    <span><strong>Berlaku Sampai</strong><?= esc(!empty($record['expires_at']) ? date('d M Y H:i', strtotime((string)$record['expires_at'])) : 'Tidak ditentukan'); ?></span>
                    <span><strong>Download</strong><?= esc((string)((int)($record['download_count'] ?? 0))) . ' / ' . esc(((int)($record['download_limit'] ?? 0) > 0) ? (string)$record['download_limit'] : '∞'); ?></span>
                    <span><strong>Rilis Akses</strong><?= esc(!empty($record['issued_at']) ? date('d M Y H:i', strtotime((string)$record['issued_at'])) : '-'); ?></span>
                </div>

                <?php if (!empty($record['instructions'])): ?>
                    <h3>Instruksi Akses</h3>
                    <div class="digital-access-instruction--template"><?= nl2br(esc((string)$record['instructions'])); ?></div>
                <?php endif; ?>

                <div class="digital-access-actions--template">
                    <?php if (!$expired && (string)($record['status'] ?? 'active') === 'active'): ?>
                        <?php if (!empty($record['access_url'])): ?><a class="cta" href="<?= esc((string)$record['access_url']); ?>" target="_blank" rel="nofollow noopener">Buka Link Akses</a><?php endif; ?>
                        <?php if (!empty($record['file_url']) && $downloadAllowed): ?><a class="cta secondary" href="<?= esc(digital_delivery_download_url($record)); ?>" rel="nofollow">Download File</a><?php endif; ?>
                    <?php endif; ?>
                    <?php if ($order && function_exists('order_status_url')): ?><a class="cta secondary" href="<?= esc(order_status_url($order)); ?>" rel="nofollow">Cek Status Order</a><?php endif; ?>
                    <a class="cta secondary" href="<?= esc(wa_link('Halo Admin, saya butuh bantuan akses digital untuk order ' . (string)($record['order_ref'] ?? ''))); ?>" target="_blank" rel="nofollow noopener">Butuh Bantuan</a>
                </div>
                <p class="digital-access-muted--template">Catatan: link akses bersifat privat. Jangan membagikan link ini ke orang lain.</p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
