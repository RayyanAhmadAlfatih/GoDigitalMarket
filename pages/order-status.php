<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PUBLIC ORDER STATUS PAGE - Template
|--------------------------------------------------------------------------
| Account-less customer order tracking. Customers can use a private token
| link from admin, or verify with order reference + WhatsApp number.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$ref = order_clean((string)($_GET['ref'] ?? ''), 80);
$token = order_clean((string)($_GET['token'] ?? ''), 80);
$lookupPhone = order_phone_clean((string)($_GET['phone'] ?? $_GET['wa'] ?? ''));
$lookupAttempted = $ref !== '' && $lookupPhone !== '' && $token === '';
$lookupError = '';
$order = function_exists('order_find_by_reference') ? order_find_by_reference($ref, $token) : null;
$lookupUsed = false;

if (!is_array($order) && $lookupAttempted) {
    if (!function_exists('order_customer_lookup_enabled') || !order_customer_lookup_enabled()) {
        $lookupError = 'Fitur cek order publik sedang tidak aktif. Gunakan link status dari admin atau hubungi admin WhatsApp.';
    } elseif (function_exists('order_lookup_is_rate_limited') && order_lookup_is_rate_limited()) {
        $lookupError = 'Terlalu banyak percobaan cek order. Tunggu beberapa saat lalu coba lagi.';
    } else {
        if (function_exists('order_lookup_touch_rate_limit')) {
            order_lookup_touch_rate_limit();
        }
        $order = function_exists('order_find_by_customer_lookup') ? order_find_by_customer_lookup($ref, $lookupPhone) : null;
        $lookupUsed = is_array($order);
        if (!$lookupUsed) {
            $lookupError = 'Order belum ditemukan. Pastikan nomor order dan nomor WhatsApp sama seperti saat mengisi form.';
        }
    }
}

$hasOrder = is_array($order);
$publicRef = $hasOrder ? order_public_reference($order) : ($ref !== '' ? $ref : 'ORD');
$title = $hasOrder ? 'Status Order ' . $publicRef : 'Cek Status Order';
$stageKey = $hasOrder && function_exists('order_public_stage_key') ? order_public_stage_key($order) : 'received';
$stageIndex = function_exists('order_public_stage_index') ? order_public_stage_index($stageKey) : 0;
$stages = function_exists('order_public_stage_definitions') ? order_public_stage_definitions() : [];
$nextAction = $hasOrder && function_exists('order_public_next_action') ? order_public_next_action($order) : [
    'title' => 'Masukkan data order',
    'body' => 'Gunakan nomor order dan nomor WhatsApp yang dipakai saat checkout untuk melihat status tanpa akun.',
    'kind' => 'info',
];
$proofs = $hasOrder && function_exists('order_public_payment_proofs') ? order_public_payment_proofs($order, 5) : [];
$invoiceTotal = $hasOrder && function_exists('order_invoice_total') ? order_invoice_total($order) : 0;
$invoiceDue = $hasOrder ? order_clean((string)($order['invoice_due_date'] ?? ''), 20) : '';
$invoiceNumber = $hasOrder && function_exists('order_invoice_number') ? order_invoice_number($order) : '';
$tracking = $hasOrder && function_exists('order_tracking_summary') ? order_tracking_summary($order) : [];
$digitalDelivery = ($hasOrder && function_exists('digital_delivery_public_status')) ? digital_delivery_public_status($order) : ['state' => '', 'url' => '', 'message' => ''];
$digitalDeliverySettings = function_exists('digital_delivery_read_settings') ? digital_delivery_read_settings() : [];
$memberAccess = ($hasOrder && function_exists('member_access_public_status')) ? member_access_public_status($order) : ['state' => '', 'url' => '', 'message' => ''];
$memberAccessSettings = function_exists('member_access_read_settings') ? member_access_read_settings() : [];
$statusHistory = $hasOrder && function_exists('order_status_history') ? order_status_history($order, 6) : [];
$maskedPhone = $hasOrder && function_exists('order_mask_phone') ? order_mask_phone((string)($order['phone'] ?? '')) : '-';
$createdAt = $hasOrder ? (order_event_timestamp($order) ?: strtotime((string)($order['time'] ?? '')) ?: time()) : time();
$updatedAt = $hasOrder ? strtotime((string)($order['updated_at'] ?? $order['invoice_updated_at'] ?? $order['time'] ?? '')) : false;
$statusPagePath = 'order-status' . ($hasOrder ? '?' . http_build_query(array_filter([
    'ref' => $publicRef,
    'token' => function_exists('order_public_token') ? order_public_token($order) : '',
])) : '');

set_seo([
    'title' => $title,
    'description' => 'Halaman cek status order pelanggan tanpa akun. Gunakan nomor order dan nomor WhatsApp atau link privat dari admin.',
    'keywords' => 'status order, cek order, order pelanggan, invoice manual',
    'canonical' => strtok(current_url(), '?') ?: url('order-status'),
    'robots' => 'noindex, nofollow',
    'type' => 'website',
    'image' => asset('images/placeholder-product.svg'),
    'url' => current_url(),
]);

if (function_exists('conversion_store_event')) {
    conversion_store_event(conversion_normalize_event([
        'source' => 'order-status',
        'type' => $lookupUsed ? 'order_status_lookup_success' : 'order_status_viewed',
        'channel' => 'checkout',
        'category' => $hasOrder ? (string)($order['category'] ?? '') : '',
        'location' => $hasOrder ? (string)($order['location'] ?? '') : '',
        'intent' => $lookupUsed ? 'public-order-lookup' : 'public-order-status-view',
        'label' => $publicRef,
        'page_path' => $statusPagePath,
        'target_url' => url($statusPagePath),
    ]));
}

$waMessage = $hasOrder && function_exists('order_status_whatsapp_message')
    ? order_status_whatsapp_message($order)
    : "Halo Admin, saya ingin menanyakan status order saya. No. Order: " . $publicRef;

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero order-success-hero order-public-hero--template">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span>Cek Status Order</span></div>
        <span class="dynamic-mini-label">Tracking Tanpa Akun</span>
        <h1><?= esc($hasOrder ? 'Status Order Anda' : 'Cek Status Order'); ?></h1>
        <p>Customer tidak perlu login. Cukup gunakan link privat dari admin, atau masukkan nomor order dan nomor WhatsApp yang dipakai saat checkout.</p>
    </div>
</section>

<section class="section order-success-section order-public-section--template">
    <div class="container">
        <style>
            .order-public-grid--template{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.75fr);gap:1.25rem;align-items:start}.order-public-card--template{background:#fff;border:1px solid #dbe7e2;border-radius:28px;padding:1.5rem;box-shadow:0 22px 70px rgba(15,23,42,.06)}.order-public-lookup--template{display:grid;grid-template-columns:1fr 1fr auto;gap:.75rem;align-items:end;margin-bottom:1rem}.order-public-lookup--template label{font-weight:800;color:#0f172a}.order-public-lookup--template input{width:100%;border:1px solid #dbe7e2;border-radius:14px;padding:.85rem .95rem;margin-top:.35rem}.order-public-alert--template{border-radius:18px;padding:1rem;margin:1rem 0;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 82%,#ffffff);color:var(--primary-dark)}.order-public-alert--template.is-ok{border-color:var(--border);background:color-mix(in srgb,var(--secondary-light) 50%,#ffffff);color:var(--primary)}.order-public-alert--template.is-danger{border-color:#fecaca;background:#fef2f2;color:#991b1b}.order-public-status-line--template{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.9rem;margin:1.1rem 0}.order-public-status-line--template span,.order-public-summary--template span{display:block;border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc;padding:.9rem;color:#475569}.order-public-status-line--template strong,.order-public-summary--template strong{display:block;color:#0f172a;font-size:.84rem;margin-bottom:.25rem}.order-public-summary--template{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem;margin-top:1rem}.order-public-progress--template{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.55rem;margin:1.25rem 0}.order-public-step--template{position:relative;border:1px solid #e2e8f0;background:#f8fafc;border-radius:18px;padding:.85rem;min-height:96px}.order-public-step--template.is-done{border-color:var(--border);background:color-mix(in srgb,var(--bg) 82%,#ffffff)}.order-public-step--template.is-current{border-color:var(--border);background:color-mix(in srgb,var(--bg) 82%,#ffffff)}.order-public-step--template b{display:block;color:#0f172a;font-size:.86rem}.order-public-step--template small{display:block;color:#64748b;margin-top:.25rem;line-height:1.35}.order-public-note--template{margin-top:1rem;border-radius:18px;padding:1rem;background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--primary-dark)}.order-public-actions--template{display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1.2rem}.order-public-actions--template .cta{display:inline-flex;align-items:center;justify-content:center;text-decoration:none}.order-public-proof-list--template{display:grid;gap:.7rem;margin-top:.75rem}.order-public-proof--template{border:1px solid #e2e8f0;border-radius:18px;padding:.9rem;background:#f8fafc}.order-public-proof--template strong{display:block;color:#0f172a}.order-public-proof--template small{display:block;color:#64748b;margin-top:.25rem}.order-public-next--template{border-radius:22px;padding:1rem;border:1px solid #bfdbfe;background:#eff6ff;color:#1e3a8a}.order-public-next--template.payment{border-color:var(--border);background:color-mix(in srgb,var(--bg) 82%,#ffffff);color:var(--primary-dark)}.order-public-next--template.success{border-color:var(--border);background:color-mix(in srgb,var(--secondary-light) 50%,#ffffff);color:var(--primary)}.order-public-next--template.warning{border-color:#fecaca;background:#fef2f2;color:#991b1b}.order-public-steps--template ol{margin:0;padding-left:1.2rem}.order-public-steps--template li{margin:.55rem 0;color:#334155}.order-public-pill--template{display:inline-flex;border-radius:999px;background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--admin-primary);font-weight:800;padding:.42rem .75rem;font-size:.82rem;margin:.25rem .25rem .25rem 0}.order-public-tracking--template{margin-top:1rem;border:1px solid #bae6fd;background:#f0f9ff;border-radius:22px;padding:1rem;color:#0f172a}.order-public-tracking--template h3{margin:0 0 .75rem}.order-public-tracking-grid--template{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.order-public-tracking-grid--template span{display:block;background:#fff;border:1px solid #dbeafe;border-radius:16px;padding:.8rem;color:#475569}.order-public-tracking-grid--template strong{display:block;color:#0f172a;font-size:.84rem;margin-bottom:.22rem}.order-public-history--template{margin-top:1rem;display:grid;gap:.65rem}.order-public-history--template div{border-left:3px solid var(--primary);background:#f8fafc;border-radius:14px;padding:.75rem;color:#475569}.order-public-history--template strong{display:block;color:#0f172a;margin-bottom:.2rem}.digital-delivery-status-box--template,.member-access-status-box--template{margin-top:1rem;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:22px;padding:1rem;color:#0f172a}.member-access-status-box--template{border-color:#bfdbfe;background:#eff6ff}.digital-delivery-status-box--template h3,.member-access-status-box--template h3{margin:0 0 .5rem;color:#14532d}.member-access-status-box--template h3{color:#1e3a8a}.digital-delivery-status-box--template p,.member-access-status-box--template p{margin:.25rem 0 .8rem;color:#166534}.member-access-status-box--template p{color:#1e40af}@media(max-width:920px){.order-public-grid--template,.order-public-status-line--template,.order-public-summary--template,.order-public-lookup--template,.order-public-progress--template,.order-public-tracking-grid--template{grid-template-columns:1fr}.order-public-step--template{min-height:auto}}
        </style>

        <div class="order-public-card--template">
            <form class="order-public-lookup--template" method="get" action="<?= url('order-status'); ?>">
                <label>Nomor Order
                    <input type="text" name="ref" value="<?= esc($ref); ?>" placeholder="Contoh: ORD-202605-ABC123" maxlength="80" autocomplete="off">
                </label>
                <label>Nomor WhatsApp
                    <input type="tel" name="phone" value="<?= esc($lookupPhone); ?>" placeholder="Contoh: 08xxxxxxxxxx" maxlength="24" autocomplete="tel">
                </label>
                <button class="cta" type="submit">Cek Order</button>
            </form>
            <div class="order-public-alert--template is-ok">Tidak perlu akun pelanggan. Link token dari admin tetap paling aman, sementara form ini membantu customer yang hanya menyimpan nomor order.</div>
            <?php if ($lookupError): ?><div class="order-public-alert--template is-danger"><?= esc($lookupError); ?></div><?php endif; ?>
        </div>

        <div class="order-public-grid--template" style="margin-top:1.25rem">
            <div class="order-public-card--template">
                <span class="dynamic-mini-label">No. Order</span>
                <h2><?= esc($publicRef); ?></h2>
                <?php if ($hasOrder): ?>
                    <div class="order-public-status-line--template">
                        <span><strong>Status Order</strong><?= esc(order_public_status_label($order)); ?></span>
                        <span><strong>Status Pembayaran</strong><?= esc(order_public_payment_status_label($order)); ?></span>
                        <?php if (!empty($order['commerce_shipping_policy_label'])): ?><span><strong>Aturan Ongkir</strong><?= esc((string)$order['commerce_shipping_policy_label']); ?></span><?php endif; ?>
                        <?php if (!empty($order['commerce_payment_policy_label'])): ?><span><strong>Aturan Pembayaran</strong><?= esc((string)$order['commerce_payment_policy_label']); ?></span><?php endif; ?>
                        <?php if (($order['commerce_preorder_enabled'] ?? '') === 'yes'): ?><span><strong>Pre-order</strong><?= esc(trim(((string)($order['commerce_preorder_eta'] ?? '') !== '' ? (string)$order['commerce_preorder_eta'] . ' · ' : '') . (string)($order['commerce_preorder_note'] ?? 'Admin akan konfirmasi jadwal.'))); ?></span><?php endif; ?>
                        <span><strong>Status Fulfillment</strong><?= esc((string)($tracking['fulfillment_status'] ?? 'Belum Diproses')); ?></span>
                    </div>

                    <?php if ($stages): ?>
                        <div class="order-public-progress--template" aria-label="Timeline status order">
                            <?php $i = 0; foreach ($stages as $key => $stage): ?>
                                <?php $class = $i < $stageIndex ? 'is-done' : ($i === $stageIndex ? 'is-current' : ''); ?>
                                <div class="order-public-step--template <?= esc($class); ?>">
                                    <b><?= esc((string)($stage['label'] ?? $key)); ?></b>
                                    <small><?= esc((string)($stage['description'] ?? '')); ?></small>
                                </div>
                            <?php $i++; endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="order-public-next--template <?= esc((string)($nextAction['kind'] ?? 'info')); ?>">
                        <strong><?= esc((string)($nextAction['title'] ?? 'Langkah berikutnya')); ?></strong><br>
                        <?= esc((string)($nextAction['body'] ?? 'Admin akan menghubungi Anda untuk konfirmasi berikutnya.')); ?>
                    </div>

                    <div class="order-public-summary--template">
                        <span><strong>Nama</strong><?= esc((string)($order['name'] ?? '-')); ?></span>
                        <span><strong>No. WhatsApp</strong><?= esc($maskedPhone); ?></span>
                        <span><strong>Produk/Layanan</strong><?= esc((string)($order['product_title'] ?? 'Pesanan')); ?></span>
                        <span><strong>Kebutuhan</strong><?= esc((string)($order['need'] ?? '-')); ?></span>
                        <span><strong>Jumlah</strong><?= esc((string)max(1, (int)($order['quantity'] ?? 1))); ?></span>
                        <span><strong>Order Masuk</strong><?= esc(date('d M Y H:i', $createdAt)); ?></span>
                        <?php if (!empty($order['location'])): ?><span><strong>Lokasi</strong><?= esc((string)$order['location']); ?></span><?php endif; ?>
                        <?php if (!empty($order['shipping_method'])): ?><span><strong>Pengiriman</strong><?= esc((string)$order['shipping_method']); ?></span><?php endif; ?>
                        <?php if (!empty($order['shipping_origin'])): ?><span><strong>Asal Kirim</strong><?= esc((string)$order['shipping_origin']); ?></span><?php endif; ?>
                        <?php if (function_exists('checkout_shipping_address') && checkout_shipping_address($order) !== '-'): ?><span><strong>Alamat</strong><?= esc(checkout_shipping_address($order)); ?></span><?php endif; ?>
                        <?php if (!empty($order['planned_date'])): ?><span><strong>Rencana</strong><?= esc(date('d M Y', strtotime((string)$order['planned_date']))); ?></span><?php endif; ?>
                        <?php if ($invoiceNumber !== ''): ?><span><strong>No. Invoice</strong><?= esc($invoiceNumber); ?></span><?php endif; ?>
                        <?php if ($invoiceTotal > 0): ?><span><strong>Total Invoice</strong><?= esc(rupiah($invoiceTotal)); ?></span><?php endif; ?>
                        <?php if ($invoiceDue !== ''): ?><span><strong>Jatuh Tempo</strong><?= esc(date('d M Y', strtotime($invoiceDue))); ?></span><?php endif; ?>
                        <?php if ($updatedAt): ?><span><strong>Update Terakhir</strong><?= esc(date('d M Y H:i', (int)$updatedAt)); ?></span><?php endif; ?>
                    </div>

                    <?php if (!empty($tracking['tracking_number']) || !empty($tracking['carrier']) || !empty($tracking['note'])): ?>
                        <div class="order-public-tracking--template">
                            <h3>Pengiriman & Resi</h3>
                            <div class="order-public-tracking-grid--template">
                                <span><strong>Status Fulfillment</strong><?= esc((string)($tracking['fulfillment_status'] ?? 'Belum Diproses')); ?></span>
                                <span><strong>Kurir/Ekspedisi</strong><?= esc((string)($tracking['carrier'] ?? '-')); ?></span>
                                <span><strong>Layanan</strong><?= esc((string)($tracking['service'] ?? '-')); ?></span>
                                <span><strong>No. Resi</strong><?= esc((string)($tracking['tracking_number'] ?? '-')); ?></span>
                                <?php if (!empty($tracking['shipped_at'])): ?><span><strong>Dikirim</strong><?= esc((string)$tracking['shipped_at']); ?></span><?php endif; ?>
                                <?php if (!empty($tracking['delivered_at'])): ?><span><strong>Diterima</strong><?= esc((string)$tracking['delivered_at']); ?></span><?php endif; ?>
                            </div>
                            <?php if (!empty($tracking['tracking_url'])): ?><p style="margin:.85rem 0 0"><a href="<?= esc((string)$tracking['tracking_url']); ?>" target="_blank" rel="nofollow noopener">Buka link tracking ekspedisi</a></p><?php endif; ?>
                            <?php if (!empty($tracking['note'])): ?><p style="margin:.85rem 0 0"><strong>Catatan Admin:</strong><br><?= nl2br(esc((string)$tracking['note'])); ?></p><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($hasOrder && !empty($digitalDeliverySettings['show_access_on_order_status']) && in_array((string)($digitalDelivery['state'] ?? ''), ['active', 'pending', 'expired'], true)): ?>
                        <div class="digital-delivery-status-box--template">
                            <h3>Akses Produk Digital</h3>
                            <p><?= esc((string)($digitalDelivery['message'] ?? 'Akses digital mengikuti status pembayaran.')); ?></p>
                            <?php if (!empty($digitalDelivery['url']) && (string)($digitalDelivery['state'] ?? '') === 'active'): ?>
                                <a class="cta secondary" href="<?= esc((string)$digitalDelivery['url']); ?>" rel="nofollow">Buka Akses Digital</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>


                    <?php if ($hasOrder && !empty($memberAccessSettings['show_on_order_status']) && in_array((string)($memberAccess['state'] ?? ''), ['active', 'pending', 'ready', 'expired'], true)): ?>
                        <div class="member-access-status-box--template">
                            <h3>Member Area / Course / Lisensi</h3>
                            <p><?= esc((string)($memberAccess['message'] ?? 'Akses member mengikuti status pembayaran.')); ?></p>
                            <?php if (!empty($memberAccess['url']) && (string)($memberAccess['state'] ?? '') === 'active'): ?>
                                <a class="cta secondary" href="<?= esc((string)$memberAccess['url']); ?>" rel="nofollow">Buka Member Area</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($statusHistory): ?>
                        <div class="order-public-history--template">
                            <h3>Timeline Update</h3>
                            <?php foreach ($statusHistory as $history): ?>
                                <div><strong><?= esc(date('d M Y H:i', strtotime((string)($history['time'] ?? 'now')) ?: time())); ?> · <?= esc((string)($history['status'] ?? '-')); ?><?= !empty($history['fulfillment_status']) ? ' · ' . esc((string)$history['fulfillment_status']) : ''; ?></strong><?= !empty($history['note']) ? nl2br(esc((string)$history['note'])) : 'Status order diperbarui.'; ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($order['note']) || !empty($order['payment_note'])): ?>
                        <div class="order-public-note--template">
                            <?php if (!empty($order['note'])): ?><strong>Catatan Admin:</strong><br><?= nl2br(esc((string)$order['note'])); ?><?php endif; ?>
                            <?php if (!empty($order['payment_note'])): ?><br><strong>Catatan Pembayaran:</strong><br><?= nl2br(esc((string)$order['payment_note'])); ?><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($proofs): ?>
                        <h3 style="margin-top:1.25rem">Riwayat Bukti Pembayaran</h3>
                        <div class="order-public-proof-list--template">
                            <?php foreach ($proofs as $proof): ?>
                                <div class="order-public-proof--template">
                                    <strong><?= esc((string)($proof['status'] ?? 'Menunggu Review')); ?> · <?= esc(isset($proof['amount']) ? rupiah((int)$proof['amount']) : '-'); ?></strong>
                                    <small><?= esc(date('d M Y H:i', (int)($proof['_ts'] ?? payment_proof_event_timestamp($proof) ?: time()))); ?> · <?= esc((string)($proof['payment_method'] ?? '-')); ?><?= !empty($proof['admin_note']) ? ' · Catatan admin: ' . esc((string)$proof['admin_note']) : ''; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="order-public-actions--template">
                        <a class="cta" href="<?= esc(wa_link($waMessage)); ?>" target="_blank" rel="nofollow noopener" <?= conversion_link_attrs(['source'=>'order-status','type'=>'whatsapp_status_confirm','channel'=>'whatsapp','label'=>$publicRef,'intent'=>'ask-order-status']); ?>>Chat Admin</a>
                        <?php if (!empty($digitalDelivery['url']) && (string)($digitalDelivery['state'] ?? '') === 'active'): ?><a class="cta secondary" href="<?= esc((string)$digitalDelivery['url']); ?>" rel="nofollow">Buka Akses Digital</a><?php endif; ?>
                        <a class="cta secondary" href="<?= esc(order_public_invoice_url($order)); ?>" rel="nofollow">Lihat Invoice / Upload Bukti</a>
                        <?php if (!empty($order['product_url'])): ?><a class="cta secondary" href="<?= esc((string)$order['product_url']); ?>">Lihat Produk</a><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="order-public-note--template">Detail order belum bisa ditampilkan. Masukkan nomor order dan nomor WhatsApp, atau buka link status privat yang dikirim admin.</div>
                    <div class="order-public-actions--template"><a class="cta" href="<?= esc(wa_link($waMessage)); ?>" target="_blank" rel="nofollow noopener">Hubungi Admin</a></div>
                <?php endif; ?>
            </div>
            <aside class="order-public-card--template order-public-steps--template">
                <h2>Panduan Customer</h2>
                <ol>
                    <li>Simpan nomor order untuk follow-up tanpa akun.</li>
                    <li>Cek status dengan nomor order dan WhatsApp yang sama seperti saat checkout.</li>
                    <li>Buka invoice hanya dari link resmi admin atau tombol di halaman ini.</li>
                    <li>Upload bukti pembayaran setelah transfer/QRIS manual dilakukan.</li>
                </ol>
                <div class="order-public-note--template">Website ini belum memakai payment gateway otomatis. Abaikan instruksi pembayaran yang bukan dari admin resmi.</div>
                <div style="margin-top:1rem">
                    <span class="order-public-pill--template">No login</span>
                    <span class="order-public-pill--template">Token privat</span>
                    <span class="order-public-pill--template">Verifikasi WhatsApp</span>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
