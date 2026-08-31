<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

if (empty($_SESSION['admin_articles_logged_in'])) {
    redirect_302('admin/orders');
}

$id = order_clean((string)($_GET['id'] ?? ''), 80);
$order = $id !== '' ? order_find_by_id($id) : null;

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Invoice Order - Admin',
    'description' => 'Draft invoice manual order untuk admin.',
    'robots' => 'noindex, nofollow',
]);

if (!$order) {
    require_once ROOT_PATH . '/components/layout/head.php';
    require_once ROOT_PATH . '/components/layout/header.php';
    ?>
    <main id="main-content" class="admin-shell">
        <section class="admin-section">
            <div class="container">
                <div class="admin-card admin-empty-card">
                    <h1>Invoice tidak ditemukan</h1>
                    <p>Order mungkin sudah tidak tersedia atau ID invoice tidak valid.</p>
                    <a class="admin-btn admin-btn--primary" href="<?= url('admin/orders'); ?>">Kembali ke Order</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    require_once ROOT_PATH . '/components/layout/footer.php';
    return;
}

$invoiceNumber = order_invoice_number($order);
$invoiceTotal = order_invoice_total($order);
$invoiceDueDate = (string)($order['invoice_due_date'] ?? order_invoice_default_due_date());
$invoiceChannel = order_invoice_payment_channel($order);
$invoicePaymentProfileHtml = function_exists('payment_render_public_profile') ? payment_render_public_profile($order) : '';
$invoiceInstruction = order_invoice_payment_instruction($order);
$invoiceNote = order_invoice_public_note($order);
$unitPrice = (int)($order['price'] ?? 0);
$quantity = max(1, (int)($order['quantity'] ?? 1));
$lineSubtotal = (int)($order['subtotal'] ?? ($unitPrice * $quantity));
$shippingTotal = max(0, (int)($order['shipping_total'] ?? 0));

require_once ROOT_PATH . '/components/layout/head.php';
?>
<main id="main-content" class="invoice-print-shell">
    <style>
        body{background:#f8fafc!important;color:#0f172a!important;}
        .invoice-print-shell{padding:32px 16px;}
        .invoice-sheet{max-width:900px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:24px;box-shadow:0 24px 80px rgba(15,23,42,.08);padding:34px;}
        .invoice-top{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;border-bottom:1px solid #e2e8f0;padding-bottom:22px;margin-bottom:24px;}
        .invoice-brand h1{margin:0;font-size:1.75rem;color:var(--primary-dark);}.invoice-brand p{margin:5px 0;color:#475569;}
        .invoice-badge{text-align:right}.invoice-badge strong{display:block;font-size:1.25rem;color:#0f172a}.invoice-badge span{display:inline-flex;margin-top:8px;padding:7px 12px;border-radius:999px;background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);color:var(--admin-primary);font-weight:800;font-size:.8rem;}
        .invoice-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px;}.invoice-card{border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#f8fafc}.invoice-card h2{margin:0 0 10px;font-size:1rem;color:#0f172a}.invoice-card p{margin:5px 0;color:#334155;}
        .invoice-table{width:100%;border-collapse:collapse;margin:18px 0;overflow:hidden;border-radius:18px}.invoice-table th,.invoice-table td{border-bottom:1px solid #e2e8f0;padding:14px;text-align:left}.invoice-table th{background:var(--primary-dark);color:#fff}.invoice-table td:last-child,.invoice-table th:last-child{text-align:right}.invoice-total{display:flex;justify-content:flex-end;margin:18px 0}.invoice-total div{min-width:280px;border-radius:18px;background:color-mix(in srgb,var(--bg) 82%,#ffffff);border:1px solid var(--border);padding:18px}.invoice-total span{display:block;color:#475569;font-weight:700}.invoice-total strong{display:block;font-size:1.6rem;color:var(--primary-dark);margin-top:5px}.invoice-instruction{white-space:pre-line;border:1px dashed var(--border);background:var(--admin-soft);border-radius:18px;padding:18px;color:#134e4a}.payment-public-profile--template{margin:14px 0;border:1px solid #dbeafe;background:#f8fbff;border-radius:18px;padding:16px}.payment-public-profile--template h3{margin:0 0 10px;color:#0f172a}.payment-public-profile--template ul{margin:0;padding-left:1.15rem;color:#334155}.payment-public-profile--template li{margin:5px 0}.payment-public-qris--template img{max-width:220px;border-radius:16px;border:1px solid #e2e8f0;background:#fff}.invoice-note{font-size:.92rem;color:#64748b;}.invoice-actions{max-width:900px;margin:16px auto 0;display:flex;gap:10px;justify-content:flex-end}.invoice-actions a,.invoice-actions button{border:0;border-radius:999px;padding:12px 18px;background:var(--admin-primary);color:#fff;font-weight:800;text-decoration:none;cursor:pointer}.invoice-actions a{background:#334155}.invoice-warning{margin-top:18px;padding:12px 14px;border-radius:16px;background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff);border:1px solid color-mix(in srgb,var(--admin-primary) 22%,#ffffff);color:var(--admin-primary-dark);font-size:.9rem;}
        @media(max-width:720px){.invoice-top,.invoice-grid{grid-template-columns:1fr;display:grid}.invoice-badge{text-align:left}.invoice-sheet{padding:22px}.invoice-actions{justify-content:flex-start;flex-wrap:wrap}}
        @media print{header,.site-header,.topbar,.search-bar,.floating-wa,.invoice-actions{display:none!important}body{background:#fff!important}.invoice-print-shell{padding:0}.invoice-sheet{box-shadow:none;border:0;border-radius:0;max-width:none}}
    </style>
    <section class="invoice-sheet">
        <div class="invoice-top">
            <div class="invoice-brand">
                <h1><?= esc(SITE_NAME); ?></h1>
                <p><?= esc(SITE_TAGLINE); ?></p>
                <p><?= esc(SITE_PHONE); ?> · <?= esc(SITE_EMAIL); ?></p>
            </div>
            <div class="invoice-badge">
                <strong><?= esc($invoiceNumber); ?></strong>
                <span><?= esc((string)($order['payment_status'] ?? 'Belum Ditagih')); ?></span>
            </div>
        </div>

        <div class="invoice-grid">
            <div class="invoice-card">
                <h2>Data Pemesan</h2>
                <p><b>Nama:</b> <?= esc((string)($order['name'] ?? '-')); ?></p>
                <p><b>Kontak:</b> <?= esc((string)($order['phone'] ?? '-')); ?></p>
                <p><b>Lokasi:</b> <?= esc((string)($order['location'] ?? '-')); ?></p>
                <?php if (!empty($order['shipping_method'])): ?><p><b>Pengiriman:</b> <?= esc((string)$order['shipping_method']); ?></p><?php endif; ?>
                <?php if (!empty($order['shipping_origin'])): ?><p><b>Asal Kirim:</b> <?= esc((string)$order['shipping_origin']); ?><?= !empty($order['shipping_origin_code']) ? ' · Kode: ' . esc((string)$order['shipping_origin_code']) : ''; ?></p><?php endif; ?>
                <?php if (!empty($order['shipping_total']) || !empty($order['shipping_rule_name'])): ?><p><b>Ongkir:</b> <?= esc(!empty($order['shipping_total']) ? rupiah((int)$order['shipping_total']) : 'Konfirmasi admin'); ?><?= !empty($order['shipping_rule_name']) ? ' · Layanan: ' . esc((string)$order['shipping_rule_name']) : ''; ?><?= !empty($order['shipping_eta']) ? ' · ETA: ' . esc((string)$order['shipping_eta']) : ''; ?></p><?php endif; ?>
                <?php if (!empty($order['commerce_shipping_policy_label']) || !empty($order['commerce_payment_policy_label']) || (($order['commerce_preorder_enabled'] ?? '') === 'yes')): ?>
                    <p><b>Policy Produk:</b> <?= esc(trim(((string)($order['commerce_shipping_policy_label'] ?? '') ?: 'Ongkir global') . ' · ' . ((string)($order['commerce_payment_policy_label'] ?? '') ?: 'Payment global'))); ?><?= (($order['commerce_preorder_enabled'] ?? '') === 'yes') ? ' · Pre-order ' . esc((string)($order['commerce_preorder_eta'] ?? '')) : ''; ?></p>
                <?php endif; ?>
                <?php if (function_exists('checkout_shipping_address') && checkout_shipping_address($order) !== '-'): ?><p><b>Alamat:</b> <?= esc(checkout_shipping_address($order)); ?></p><?php endif; ?>
            </div>
            <div class="invoice-card">
                <h2>Detail Invoice</h2>
                <p><b>Tanggal order:</b> <?= esc(date('d M Y H:i', (int)($order['_ts'] ?? time()))); ?></p>
                <p><b>Batas pembayaran:</b> <?= esc($invoiceDueDate !== '' ? date('d M Y', strtotime($invoiceDueDate)) : '-'); ?></p>
                <p><b>Channel:</b> <?= esc($invoiceChannel); ?></p>
            </div>
        </div>

        <table class="invoice-table">
            <thead><tr><th>Produk / Layanan</th><th>Jumlah</th><th>Harga</th><th>Total</th></tr></thead>
            <tbody>
                <tr>
                    <td>
                        <b><?= esc((string)($order['product_title'] ?? 'Order Produk')); ?></b><br>
                        <small><?= esc((string)($order['need'] ?? '')); ?></small>
                    </td>
                    <td><?= esc((string)$quantity); ?></td>
                    <td><?= esc($unitPrice > 0 ? rupiah($unitPrice) : 'Konfirmasi admin'); ?></td>
                    <td><?= esc($lineSubtotal > 0 ? rupiah($lineSubtotal) : 'Konfirmasi admin'); ?></td>
                </tr>
                <?php if ($shippingTotal > 0): ?>
                <tr>
                    <td><b>Ongkir</b><br><small><?= esc((string)($order['shipping_rule_name'] ?? 'Ongkir / layanan kirim')); ?><?= !empty($order['shipping_eta']) ? ' · ETA ' . esc((string)$order['shipping_eta']) : ''; ?></small></td>
                    <td>1</td>
                    <td><?= esc(rupiah($shippingTotal)); ?></td>
                    <td><?= esc(rupiah($shippingTotal)); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="invoice-total"><div><span>Total Invoice</span><strong><?= esc($invoiceTotal > 0 ? rupiah($invoiceTotal) : 'Konfirmasi admin'); ?></strong></div></div>

        <h2>Metode & Instruksi Pembayaran</h2>
        <?= $invoicePaymentProfileHtml; ?>
        <div class="invoice-instruction"><?= esc($invoiceInstruction); ?></div>

        <div class="invoice-warning">Invoice ini belum terhubung ke payment gateway otomatis. Validasi pembayaran tetap dilakukan manual oleh admin.</div>
        <p class="invoice-note"><?= nl2br(esc($invoiceNote)); ?></p>
    </section>
    <div class="invoice-actions">
        <a href="<?= esc(url('admin/orders')); ?>">Kembali</a>
        <button type="button" onclick="window.print()">Print Invoice</button>
    </div>
</main>
