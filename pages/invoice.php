<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PUBLIC INVOICE PAGE - Template
|--------------------------------------------------------------------------
| Token-protected public invoice/payment instruction page. This is still a
| manual invoice plus optional payment gateway payment link.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$ref = order_clean((string)($_GET['ref'] ?? ''), 80);
$token = order_clean((string)($_GET['token'] ?? ''), 80);
$order = function_exists('order_find_by_reference') ? order_find_by_reference($ref, $token) : null;
$hasOrder = is_array($order);
$publicRef = $hasOrder ? order_public_reference($order) : ($ref !== '' ? $ref : 'ORD');
$invoiceNumber = $hasOrder ? order_invoice_number($order) : 'INV';
$invoiceTotal = $hasOrder ? order_invoice_total($order) : 0;
$invoiceDueDate = $hasOrder ? (string)($order['invoice_due_date'] ?? order_invoice_default_due_date()) : '';
$invoiceChannel = $hasOrder ? order_invoice_payment_channel($order) : 'Konfirmasi Admin';
$invoicePaymentProfileHtml = $hasOrder && function_exists('payment_render_public_profile') ? payment_render_public_profile($order) : '';
$invoiceInstruction = $hasOrder ? order_invoice_payment_instruction($order) : 'Invoice tidak ditemukan. Hubungi admin untuk konfirmasi.';
$invoiceNote = $hasOrder ? order_invoice_public_note($order) : '';
$waMessage = $hasOrder ? order_invoice_confirmation_whatsapp_message($order) : "Halo Admin, saya ingin konfirmasi invoice. No. Order: " . $publicRef;
$proofSuccess = isset($_GET['proof']) && (string)$_GET['proof'] === 'success';
$tracking = $hasOrder && function_exists('order_tracking_summary') ? order_tracking_summary($order) : [];
$digitalDelivery = ($hasOrder && function_exists('digital_delivery_public_status')) ? digital_delivery_public_status($order) : ['state' => '', 'url' => '', 'message' => ''];
$digitalDeliverySettings = function_exists('digital_delivery_read_settings') ? digital_delivery_read_settings() : [];
$memberAccess = ($hasOrder && function_exists('member_access_public_status')) ? member_access_public_status($order) : ['state' => '', 'url' => '', 'message' => ''];
$memberAccessSettings = function_exists('member_access_read_settings') ? member_access_read_settings() : [];

set_seo([
    'title' => 'Invoice ' . $invoiceNumber,
    'description' => 'Halaman invoice manual pelanggan. Halaman ini bersifat privat melalui link token dan tidak diindex mesin pencari.',
    'keywords' => 'invoice order, instruksi pembayaran, invoice manual',
    'canonical' => strtok(current_url(), '?') ?: url('invoice'),
    'robots' => 'noindex, nofollow',
    'type' => 'website',
    'image' => asset('images/placeholder-product.svg'),
    'url' => current_url(),
]);

if (function_exists('conversion_store_event')) {
    conversion_store_event(conversion_normalize_event([
        'source' => 'public-invoice',
        'type' => 'invoice_viewed',
        'channel' => 'payment',
        'category' => $hasOrder ? (string)($order['category'] ?? '') : '',
        'location' => $hasOrder ? (string)($order['location'] ?? '') : '',
        'intent' => 'public-invoice-view',
        'label' => $invoiceNumber,
        'page_path' => current_uri() . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . (string)$_SERVER['QUERY_STRING'] : ''),
        'target_url' => current_url(),
    ]));
}

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero order-success-hero order-public-hero--template">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span>Invoice</span></div>
        <span class="dynamic-mini-label">Invoice & Pembayaran</span>
        <h1><?= esc($hasOrder ? 'Invoice Pesanan' : 'Invoice Tidak Ditemukan'); ?></h1>
        <p>Invoice ini berisi instruksi pembayaran manual dan tombol payment gateway otomatis jika order mendukung pembayaran online.</p>
    </div>
</section>

<section class="section invoice-public-section--template">
    <div class="container">
        <style>
            .invoice-public-card--template{max-width:980px;margin:0 auto;background:#fff;border:1px solid #dbe7e2;border-radius:30px;box-shadow:0 24px 80px rgba(15,23,42,.07);padding:1.6rem}.invoice-public-top--template{display:flex;justify-content:space-between;gap:1rem;border-bottom:1px solid #e2e8f0;padding-bottom:1rem;margin-bottom:1rem}.invoice-public-top--template h2{margin:.25rem 0;color:var(--primary-dark)}.invoice-public-badge--template{display:inline-flex;border-radius:999px;background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--admin-primary);font-weight:850;padding:.48rem .8rem}.invoice-public-grid--template{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.9rem;margin:1rem 0}.invoice-public-grid--template span{display:block;border:1px solid #e2e8f0;border-radius:18px;background:#f8fafc;padding:.9rem;color:#475569}.invoice-public-grid--template strong{display:block;color:#0f172a;font-size:.84rem;margin-bottom:.25rem}.invoice-public-total--template{margin:1rem 0;border-radius:22px;background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);padding:1rem}.invoice-public-total--template span{display:block;color:#475569;font-weight:800}.invoice-public-total--template strong{display:block;color:var(--primary-dark);font-size:1.7rem;margin-top:.25rem}.invoice-public-instruction--template{white-space:pre-line;border:1px dashed var(--border);background:var(--admin-soft);border-radius:20px;padding:1rem;color:#134e4a}.payment-public-profile--template{margin:1rem 0;border:1px solid #dbeafe;background:#f8fbff;border-radius:20px;padding:1rem}.payment-public-profile--template h3{margin:.1rem 0 .7rem;color:#0f172a}.payment-public-profile--template ul{margin:0;padding-left:1.15rem;color:#334155}.payment-public-profile--template li{margin:.35rem 0}.payment-public-qris--template{margin:.5rem 0 1rem}.payment-public-qris--template img{max-width:260px;width:100%;height:auto;border-radius:18px;border:1px solid #e2e8f0;background:#fff}.invoice-public-warning--template{margin-top:1rem;border-radius:18px;background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--primary-dark);padding:.95rem}.invoice-public-tracking--template{margin:1rem 0;border:1px solid #bae6fd;background:#f0f9ff;border-radius:20px;padding:1rem;color:#0f172a}.invoice-public-tracking--template h2{margin:.1rem 0 .7rem}.invoice-public-tracking--template p{margin:.45rem 0;color:#475569}.invoice-public-actions--template{display:flex;flex-wrap:wrap;gap:.7rem;margin-top:1.2rem}.invoice-public-actions--template .cta{text-decoration:none}.payment-proof-form--template{margin:1.1rem 0;padding:1rem;border:1px solid #dbeafe;background:#f8fbff;border-radius:22px;display:grid;gap:.85rem}.payment-proof-form--template h2{margin:0;color:#0f172a}.payment-proof-form-grid--template{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.payment-proof-form--template label{display:grid;gap:.35rem;color:#334155;font-weight:800}.payment-proof-form--template input,.payment-proof-form--template select,.payment-proof-form--template textarea{width:100%;border:1px solid #cbd5e1;border-radius:14px;padding:.78rem .85rem;background:#fff;color:#0f172a}.payment-proof-form--template textarea{min-height:88px}.payment-proof-form--template .wide{grid-column:1/-1}.payment-proof-success--template{margin:1rem 0;border-radius:18px;background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--primary);padding:.95rem;font-weight:800}.payment-proof-note--template{color:#64748b;font-size:.88rem;margin:0}.payment-gateway-public-box--template{margin:1rem 0;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:22px;padding:1rem;color:#0f172a}.payment-gateway-public-box--template h2{margin:.1rem 0 .45rem;color:#14532d}.payment-gateway-public-box--template p{color:#166534;margin:.35rem 0 .8rem}.payment-gateway-public-box--template .cta{display:inline-flex;text-decoration:none}.payment-gateway-public-error--template{background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:.75rem;color:#9a3412!important}.digital-delivery-public-box--template,.member-access-public-box--template{margin:1rem 0;border:1px solid #bbf7d0;background:#f0fdf4;border-radius:22px;padding:1rem;color:#0f172a}.member-access-public-box--template{border-color:#bfdbfe;background:#eff6ff}.digital-delivery-public-box--template h2,.member-access-public-box--template h2{margin:.1rem 0 .45rem;color:#14532d}.member-access-public-box--template h2{color:#1e3a8a}.digital-delivery-public-box--template p,.member-access-public-box--template p{color:#166534;margin:.35rem 0 .8rem}.member-access-public-box--template p{color:#1e40af}@media(max-width:760px){.invoice-public-top--template{display:grid}.invoice-public-grid--template,.payment-proof-form-grid--template{grid-template-columns:1fr}.payment-proof-form--template .wide{grid-column:span 1}}
        </style>
        <article class="invoice-public-card--template">
            <div class="invoice-public-top--template">
                <div>
                    <span class="dynamic-mini-label">No. Invoice</span>
                    <h2><?= esc($invoiceNumber); ?></h2>
                    <p>No. Order: <strong><?= esc($publicRef); ?></strong></p>
                </div>
                <div><span class="invoice-public-badge--template"><?= esc($hasOrder ? order_public_payment_status_label($order) : 'Tidak ditemukan'); ?></span></div>
            </div>
            <?php if ($hasOrder): ?>
                <div class="invoice-public-grid--template">
                    <span><strong>Nama</strong><?= esc((string)($order['name'] ?? '-')); ?></span>
                    <span><strong>Produk/Layanan</strong><?= esc((string)($order['product_title'] ?? 'Pesanan')); ?></span>
                    <span><strong>Jumlah</strong><?= esc((string)max(1, (int)($order['quantity'] ?? 1))); ?></span>
                    <span><strong>Channel Pembayaran</strong><?= esc($invoiceChannel); ?></span>
                        <?php if (!empty($order['commerce_shipping_policy_label'])): ?><span><strong>Aturan Ongkir</strong><?= esc((string)$order['commerce_shipping_policy_label']); ?></span><?php endif; ?>
                        <?php if (!empty($order['commerce_payment_policy_label'])): ?><span><strong>Aturan Pembayaran</strong><?= esc((string)$order['commerce_payment_policy_label']); ?></span><?php endif; ?>
                        <?php if (($order['commerce_preorder_enabled'] ?? '') === 'yes'): ?><span><strong>Pre-order</strong><?= esc(trim(((string)($order['commerce_preorder_eta'] ?? '') !== '' ? (string)$order['commerce_preorder_eta'] . ' · ' : '') . (string)($order['commerce_preorder_note'] ?? 'Admin akan konfirmasi jadwal.'))); ?></span><?php endif; ?>
                    <span><strong>Jatuh Tempo</strong><?= esc($invoiceDueDate !== '' ? date('d M Y', strtotime($invoiceDueDate)) : '-'); ?></span>
                    <span><strong>Lokasi</strong><?= esc((string)($order['location'] ?? '-')); ?></span>
                    <?php if (!empty($order['shipping_method'])): ?><span><strong>Pengiriman</strong><?= esc((string)$order['shipping_method']); ?></span><?php endif; ?>
                    <?php if (!empty($order['shipping_origin'])): ?><span><strong>Asal Kirim</strong><?= esc((string)$order['shipping_origin']); ?></span><?php endif; ?>
                    <?php if (!empty($order['shipping_total']) || !empty($order['shipping_rule_name'])): ?><span><strong>Ongkir</strong><?= esc(!empty($order['shipping_total']) ? rupiah((int)$order['shipping_total']) : 'Konfirmasi admin'); ?><?= !empty($order['shipping_rule_name']) ? ' · ' . esc((string)$order['shipping_rule_name']) : ''; ?><?= !empty($order['shipping_eta']) ? ' · ETA ' . esc((string)$order['shipping_eta']) : ''; ?></span><?php endif; ?>
                    <?php if (function_exists('checkout_shipping_address') && checkout_shipping_address($order) !== '-'): ?><span><strong>Alamat</strong><?= esc(checkout_shipping_address($order)); ?></span><?php endif; ?>
                </div>
                <div class="invoice-public-total--template"><span>Total Invoice</span><strong><?= esc($invoiceTotal > 0 ? rupiah($invoiceTotal) : 'Konfirmasi admin'); ?></strong></div>
                <?php if (!empty($tracking['tracking_number']) || !empty($tracking['carrier']) || !empty($tracking['fulfillment_status'])): ?>
                    <div class="invoice-public-tracking--template">
                        <h2>Status Fulfillment</h2>
                        <p><strong>Status:</strong> <?= esc((string)($tracking['fulfillment_status'] ?? 'Belum Diproses')); ?></p>
                        <?php if (!empty($tracking['carrier'])): ?><p><strong>Kurir/Ekspedisi:</strong> <?= esc((string)$tracking['carrier']); ?><?= !empty($tracking['service']) ? ' · ' . esc((string)$tracking['service']) : ''; ?></p><?php endif; ?>
                        <?php if (!empty($tracking['tracking_number'])): ?><p><strong>No. Resi:</strong> <?= esc((string)$tracking['tracking_number']); ?></p><?php endif; ?>
                        <?php if (!empty($tracking['tracking_url'])): ?><p><a href="<?= esc((string)$tracking['tracking_url']); ?>" target="_blank" rel="nofollow noopener">Buka link tracking ekspedisi</a></p><?php endif; ?>
                    </div>
                <?php endif; ?>
                <h2>Metode & Instruksi Pembayaran</h2>
                <?php if (function_exists('payment_gateway_public_payment_box')): ?>
                    <?= payment_gateway_public_payment_box($order); ?>
                <?php endif; ?>
                <?= $invoicePaymentProfileHtml; ?>
                <div class="invoice-public-instruction--template"><?= esc($invoiceInstruction); ?></div>
                <div class="invoice-public-warning--template"><?= nl2br(esc($invoiceNote)); ?></div>
                <?php if ($proofSuccess): ?>
                    <div class="payment-proof-success--template">Bukti pembayaran berhasil dikirim. Admin akan melakukan pengecekan manual dan menghubungi Anda untuk konfirmasi.</div>
                <?php endif; ?>
                <?php if (function_exists('payment_proof_enabled') && payment_proof_enabled()): ?>
                    <form class="payment-proof-form--template" method="post" action="<?= url('payment-proof-submit'); ?>" enctype="multipart/form-data">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="ref" value="<?= esc($publicRef); ?>">
                        <input type="hidden" name="token" value="<?= esc(order_public_token($order)); ?>">
                        <input type="hidden" name="source" value="public-invoice">
                        <input type="hidden" name="page_path" value="<?= esc(current_uri() . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . (string)$_SERVER['QUERY_STRING'] : '')); ?>">
                        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;height:0;width:0;" aria-hidden="true">
                        <div>
                            <h2>Upload Bukti Pembayaran</h2>
                            <p class="payment-proof-note--template">Jika sudah melakukan transfer/QRIS manual, kirim bukti pembayaran di sini agar admin bisa melakukan pengecekan.</p>
                        </div>
                        <div class="payment-proof-form-grid--template">
                            <label>Nama Pengirim
                                <input type="text" name="payer_name" value="<?= esc((string)($order['name'] ?? '')); ?>" required maxlength="80">
                            </label>
                            <label>Nomor WhatsApp
                                <input type="tel" name="payer_phone" value="<?= esc((string)($order['phone'] ?? '')); ?>" required maxlength="24">
                            </label>
                            <label>Email Opsional
                                <input type="email" name="payer_email" value="<?= esc((string)($order['email'] ?? '')); ?>" maxlength="120">
                            </label>
                            <label>Nominal Pembayaran
                                <input type="number" name="amount" min="1" step="1000" value="<?= esc((string)max(0, $invoiceTotal)); ?>" required>
                            </label>
                            <label>Metode Pembayaran
                                <select name="payment_method">
                                    <?php foreach (payment_proof_methods() as $method): ?>
                                        <option value="<?= esc($method); ?>" <?= str_contains(strtolower($invoiceChannel), strtolower(str_replace(' Manual', '', $method))) ? 'selected' : ''; ?>><?= esc($method); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Bank / QRIS / Channel
                                <input type="text" name="payment_channel" value="<?= esc($invoiceChannel); ?>" maxlength="120" placeholder="Contoh: BCA / QRIS / Tunai">
                            </label>
                            <label class="wide">Upload Bukti Pembayaran
                                <input type="file" name="proof_file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                            </label>
                            <label class="wide">Catatan Tambahan
                                <textarea name="note" maxlength="700" placeholder="Contoh: sudah transfer DP, transfer dari rekening atas nama..., atau catatan lainnya."></textarea>
                            </label>
                        </div>
                        <button class="cta" type="submit">Kirim Bukti Pembayaran</button>
                        <p class="payment-proof-note--template">Format yang didukung: JPG, PNG, WebP, atau PDF. Maksimal <?= esc((string)((int)(payment_proof_max_upload_bytes() / 1024 / 1024))); ?> MB.</p>
                    </form>
                <?php endif; ?>
                <?php if ($hasOrder && !empty($memberAccessSettings['show_on_invoice']) && in_array((string)($memberAccess['state'] ?? ''), ['active', 'pending', 'ready', 'expired'], true)): ?>
                    <div class="member-access-public-box--template">
                        <h2>Member Area / Course / Lisensi</h2>
                        <p><?= esc((string)($memberAccess['message'] ?? 'Akses member mengikuti status pembayaran.')); ?></p>
                        <?php if (!empty($memberAccess['url']) && (string)($memberAccess['state'] ?? '') === 'active'): ?>
                            <a class="cta secondary" href="<?= esc((string)$memberAccess['url']); ?>" rel="nofollow">Buka Member Area</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="order-public-actions--template invoice-public-actions--template">
                    <a class="cta" href="<?= esc(wa_link($waMessage)); ?>" target="_blank" rel="nofollow noopener" <?= conversion_link_attrs(['source'=>'public-invoice','type'=>'whatsapp_confirm_payment','channel'=>'whatsapp','label'=>$invoiceNumber,'intent'=>'confirm-payment']); ?>>Konfirmasi via WhatsApp</a>
                    <a class="cta secondary" href="<?= esc(order_status_url($order)); ?>" rel="nofollow">Lihat Status Order</a>
                </div>
            <?php else: ?>
                <div class="invoice-public-warning--template">Invoice tidak bisa ditampilkan. Pastikan link invoice lengkap dengan nomor referensi dan token dari admin.</div>
                <?php if ($hasOrder && !empty($digitalDeliverySettings['show_access_on_invoice']) && in_array((string)($digitalDelivery['state'] ?? ''), ['active', 'pending', 'expired'], true)): ?>
                    <div class="digital-delivery-public-box--template">
                        <h2>Akses Produk Digital</h2>
                        <p><?= esc((string)($digitalDelivery['message'] ?? 'Akses digital mengikuti status pembayaran.')); ?></p>
                        <?php if (!empty($digitalDelivery['url']) && (string)($digitalDelivery['state'] ?? '') === 'active'): ?>
                            <a class="cta" href="<?= esc((string)$digitalDelivery['url']); ?>" rel="nofollow">Buka Akses Digital</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="invoice-public-actions--template"><a class="cta" href="<?= esc(wa_link($waMessage)); ?>" target="_blank" rel="nofollow noopener">Hubungi Admin</a></div>
            <?php endif; ?>
        </article>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
