<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ORDER SUCCESS PAGE - Template
|--------------------------------------------------------------------------
| Public confirmation page after checkout/order form submission.
| The page is noindex and uses a reference + token when available.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$ref = order_clean((string)($_GET['ref'] ?? ''), 80);
$token = order_clean((string)($_GET['token'] ?? ''), 80);
$order = function_exists('order_find_by_reference') ? order_find_by_reference($ref, $token) : null;
$hasOrder = is_array($order);
$title = $hasOrder ? 'Order Berhasil Diterima' : 'Konfirmasi Order';
$publicRef = $hasOrder ? order_public_reference($order) : ($ref !== '' ? $ref : 'ORD');
$productTitle = $hasOrder ? (string)($order['product_title'] ?? 'Pesanan') : 'Pesanan';
$waMessage = $hasOrder && function_exists('order_success_whatsapp_message')
    ? order_success_whatsapp_message($order)
    : "Halo Admin, saya sudah mengisi form pemesanan. Mohon dibantu proses selanjutnya.";
$checkoutSettings = function_exists('checkout_settings') ? checkout_settings() : [];
$successMessage = (string)($checkoutSettings['success_message'] ?? 'Terima kasih. Data pemesanan Anda sudah masuk ke admin. Simpan nomor referensi berikut untuk follow-up dan cek status tanpa membuat akun.');
$digitalDelivery = ($hasOrder && function_exists('digital_delivery_public_status')) ? digital_delivery_public_status($order) : ['state' => '', 'url' => '', 'message' => ''];
$digitalDeliverySettings = function_exists('digital_delivery_read_settings') ? digital_delivery_read_settings() : [];

set_seo([
    'title' => $title . ' - ' . $publicRef,
    'description' => 'Halaman konfirmasi pemesanan. Admin akan menindaklanjuti pesanan melalui WhatsApp atau kontak yang diisi.',
    'keywords' => 'konfirmasi order, order berhasil, form pemesanan',
    'canonical' => strtok(current_url(), '?') ?: url('order-success'),
    'robots' => 'noindex, nofollow',
    'type' => 'website',
    'image' => asset('images/placeholder-product.svg'),
    'url' => current_url(),
]);

$serverEventId = function_exists('server_conversion_event_id')
    ? server_conversion_event_id(['analytics_event' => 'order_success', 'source' => 'order-success', 'label' => $publicRef])
    : ('evt_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)));

if (function_exists('conversion_store_event')) {
    conversion_store_event(conversion_normalize_event([
        'event_id' => $serverEventId,
        'source' => 'order-success',
        'type' => 'order_success_viewed',
        'channel' => 'checkout',
        'category' => $hasOrder ? (string)($order['category'] ?? '') : '',
        'location' => $hasOrder ? (string)($order['location'] ?? '') : '',
        'intent' => 'order-confirmation-view',
        'label' => $publicRef,
        'page_path' => current_uri() . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . (string)$_SERVER['QUERY_STRING'] : ''),
        'target_url' => current_url(),
    ]));
}

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<script>
window.__MARKETING_PAGE_EVENT__ = <?= json_encode([
    'event_id' => $serverEventId,
    'source' => 'order-success',
    'type' => 'order_success',
    'channel' => 'checkout',
    'category' => $hasOrder ? (string)($order['category'] ?? '') : '',
    'location' => $hasOrder ? (string)($order['location'] ?? '') : '',
    'intent' => 'order-success',
    'label' => 'Order Success',
    'page_path' => parse_url((string)current_url(), PHP_URL_PATH) ?: '/order-success',
    'target_url' => strtok((string)current_url(), '?') ?: url('order-success'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

<section class="mini-hero order-success-hero order-success-hero--template">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= url(); ?>">Home</a>
            <span>/</span>
            <span>Order Berhasil</span>
        </div>
        <span class="dynamic-mini-label">Order Diterima</span>
        <h1><?= esc($title); ?></h1>
        <p><?= esc($successMessage); ?></p>
    </div>
</section>

<section class="section order-success-section">
    <div class="container">
        <div class="order-success-grid-template">
            <div class="order-success-card">
                <span class="dynamic-mini-label">Nomor Referensi</span>
                <h2><?= esc($publicRef); ?></h2>
                <p><?= esc($successMessage); ?></p>

                <?php if ($hasOrder): ?>
                    <div class="order-success-summary">
                        <span><strong>Nama</strong><?= esc((string)($order['name'] ?? '-')); ?></span>
                        <span><strong>Produk/Layanan</strong><?= esc($productTitle ?: '-'); ?></span>
                        <span><strong>Kebutuhan</strong><?= esc((string)($order['need'] ?? '-')); ?></span>
                        <span><strong>Jumlah</strong><?= esc((string)max(1, (int)($order['quantity'] ?? 1))); ?></span>
                        <?php if (!empty($order['location'])): ?><span><strong>Lokasi</strong><?= esc((string)$order['location']); ?></span><?php endif; ?>
                        <?php if (!empty($order['shipping_method'])): ?><span><strong>Pengiriman</strong><?= esc((string)$order['shipping_method']); ?></span><?php endif; ?>
                        <?php if (!empty($order['shipping_origin'])): ?><span><strong>Asal Kirim</strong><?= esc((string)$order['shipping_origin']); ?></span><?php endif; ?>
                        <?php if (function_exists('checkout_shipping_address') && checkout_shipping_address($order) !== '-'): ?><span><strong>Alamat</strong><?= esc(checkout_shipping_address($order)); ?></span><?php endif; ?>
                        <?php if (!empty($order['planned_date'])): ?><span><strong>Rencana Tanggal</strong><?= esc(date('d M Y', strtotime((string)$order['planned_date']))); ?></span><?php endif; ?>
                        <?php if (!empty($order['payment_method'])): ?><span><strong>Preferensi Pembayaran</strong><?= esc((string)$order['payment_method']); ?></span><?php endif; ?>
                        <?php if (!empty($order['commerce_shipping_policy_label'])): ?><span><strong>Aturan Ongkir</strong><?= esc((string)$order['commerce_shipping_policy_label']); ?></span><?php endif; ?>
                        <?php if (!empty($order['commerce_payment_policy_label'])): ?><span><strong>Aturan Pembayaran</strong><?= esc((string)$order['commerce_payment_policy_label']); ?></span><?php endif; ?>
                        <?php if (($order['commerce_preorder_enabled'] ?? '') === 'yes'): ?><span><strong>Pre-order</strong><?= esc(trim(((string)($order['commerce_preorder_eta'] ?? '') !== '' ? (string)$order['commerce_preorder_eta'] . ' · ' : '') . (string)($order['commerce_preorder_note'] ?? 'Admin akan konfirmasi jadwal.'))); ?></span><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="order-success-note is-warning">
                        Detail order tidak bisa ditampilkan. Jika Anda baru saja mengisi form, silakan lanjut konfirmasi via WhatsApp dan sebutkan nomor referensi di atas.
                    </div>
                <?php endif; ?>

                <?php if ($hasOrder && in_array((string)($digitalDelivery['state'] ?? ''), ['active', 'pending', 'expired'], true)): ?>
                    <div class="order-success-note <?= (string)($digitalDelivery['state'] ?? '') === 'active' ? '' : 'is-warning'; ?>">
                        <strong>Produk Digital:</strong><br>
                        <?= esc((string)($digitalDelivery['message'] ?? 'Akses digital akan mengikuti status pembayaran.')); ?>
                        <?php if (!empty($digitalDelivery['url']) && (string)($digitalDelivery['state'] ?? '') === 'active'): ?>
                            <br><a href="<?= esc((string)$digitalDelivery['url']); ?>" rel="nofollow">Buka halaman akses digital</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="order-success-actions">
                    <a
                        class="cta"
                        href="<?= esc(wa_link($waMessage)); ?>"
                        target="_blank"
                        rel="nofollow noopener"
                        <?= conversion_link_attrs([
                            'source' => 'order-success',
                            'type' => 'whatsapp_after_order',
                            'channel' => 'whatsapp',
                            'category' => $hasOrder ? (string)($order['category'] ?? '') : '',
                            'location' => $hasOrder ? (string)($order['location'] ?? '') : '',
                            'label' => $publicRef,
                            'intent' => 'confirm-order-via-whatsapp',
                        ]); ?>>
                        Lanjut Konfirmasi via WhatsApp
                    </a>
                    <?php if ($hasOrder): ?>
                        <?php if (function_exists('payment_gateway_existing_payment_url') && payment_gateway_existing_payment_url($order) !== ''): ?>
                            <a class="cta secondary" href="<?= esc(payment_gateway_existing_payment_url($order)); ?>" target="_blank" rel="nofollow noopener">Bayar Sekarang</a>
                        <?php elseif (function_exists('payment_gateway_order_can_create_charge') && !empty(payment_gateway_order_can_create_charge($order)['allowed']) && function_exists('payment_gateway_pay_url')): ?>
                            <a class="cta secondary" href="<?= esc(payment_gateway_pay_url($order)); ?>" rel="nofollow">Buat Link Pembayaran</a>
                        <?php endif; ?>
                        <?php if (!empty($digitalDelivery['url']) && (string)($digitalDelivery['state'] ?? '') === 'active'): ?>
                            <a class="cta secondary" href="<?= esc((string)$digitalDelivery['url']); ?>" rel="nofollow">Buka Akses Digital</a>
                        <?php endif; ?>
                        <a class="cta secondary" href="<?= esc(order_status_url($order)); ?>" rel="nofollow">Cek Status Order</a>
                        <a class="cta secondary" href="<?= esc(order_public_invoice_url($order)); ?>" rel="nofollow">Lihat Invoice</a>
                    <?php endif; ?>
                    <a class="cta secondary" href="<?= url('katalog'); ?>">Kembali ke Katalog</a>
                </div>
            </div>

            <aside class="order-success-next-card">
                <h2>Langkah Berikutnya</h2>
                <ol>
                    <li>Admin mengecek data pesanan dan ketersediaan produk.</li>
                    <li>Admin menghubungi Anda melalui WhatsApp/telepon/email sesuai data yang diisi.</li>
                    <li>Jika produk mendukung pembayaran otomatis, sistem bisa membuka payment link provider. Jika tidak, admin menyiapkan invoice atau instruksi pembayaran manual.</li>
                    <li>Pesanan diproses setelah detail akhir disepakati.</li>
                </ol>
                <div class="order-success-note">
                    Pembayaran otomatis hanya tampil untuk produk/metode yang memang diizinkan admin. Untuk order manual, jangan transfer sebelum nominal dan instruksi pembayaran dikonfirmasi admin.
                </div>
                <?php if ($hasOrder): ?>
                    <div class="order-success-note" style="margin-top:.75rem">
                        Cek status tanpa akun tersedia di halaman status order. Jika link ini hilang, customer bisa memakai nomor order + nomor WhatsApp yang sama saat checkout.
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
