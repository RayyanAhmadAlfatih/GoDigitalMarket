<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$ref = function_exists('order_clean') ? order_clean((string)($_GET['ref'] ?? ''), 80) : trim((string)($_GET['ref'] ?? ''));
$token = function_exists('order_clean') ? order_clean((string)($_GET['token'] ?? ''), 80) : trim((string)($_GET['token'] ?? ''));
$provider = function_exists('payment_gateway_slug') ? payment_gateway_slug((string)($_GET['provider'] ?? '')) : trim((string)($_GET['provider'] ?? ''));
$order = function_exists('order_find_by_reference') ? order_find_by_reference($ref, $token) : null;
$hasOrder = is_array($order);
$error = '';
$paymentUrl = '';
$result = null;

if ($hasOrder) {
    $paymentUrl = function_exists('payment_gateway_existing_payment_url') ? payment_gateway_existing_payment_url($order) : '';
    if ($paymentUrl === '' && function_exists('payment_gateway_create_charge_for_order')) {
        $result = payment_gateway_create_charge_for_order($order, $provider);
        if (!empty($result['ok']) && !empty($result['payment_url'])) {
            $paymentUrl = (string)$result['payment_url'];
        } else {
            $error = (string)($result['message'] ?? 'Payment gateway belum bisa membuat link pembayaran.');
        }
    }
}

if ($paymentUrl !== '') {
    header('Location: ' . $paymentUrl, true, 302);
    exit;
}

seo_noindex();
set_seo([
    'title' => 'Pembayaran Otomatis',
    'description' => 'Halaman aman untuk membuat dan membuka payment link order.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero order-public-hero--template">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span>Pembayaran</span></div>
        <span class="dynamic-mini-label">Payment Gateway</span>
        <h1><?= esc($hasOrder ? 'Link Pembayaran Belum Tersedia' : 'Order Tidak Ditemukan'); ?></h1>
        <p>Order tetap aman tercatat. Jika payment link belum tersedia, pembayaran manual/konfirmasi admin tetap bisa digunakan.</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:880px">
        <article class="admin-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:28px;padding:1.5rem;box-shadow:0 20px 70px rgba(15,23,42,.07)">
            <?php if (!$hasOrder): ?>
                <h2>Order tidak ditemukan</h2>
                <p>Pastikan link pembayaran lengkap dengan nomor referensi dan token dari invoice/status order.</p>
                <a class="cta" href="<?= esc(url('katalog')); ?>">Kembali ke Katalog</a>
            <?php else: ?>
                <h2>Payment link belum bisa dibuat</h2>
                <p><?= esc($error !== '' ? $error : 'Provider belum aktif, credential belum lengkap, atau produk ini tidak memakai pembayaran otomatis.'); ?></p>
                <?php if (!empty($order['gateway_error'])): ?><p><strong>Catatan:</strong> <?= esc((string)$order['gateway_error']); ?></p><?php endif; ?>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
                    <a class="cta" href="<?= esc(order_public_invoice_url($order)); ?>" rel="nofollow">Kembali ke Invoice</a>
                    <a class="cta secondary" href="<?= esc(order_status_url($order)); ?>" rel="nofollow">Cek Status Order</a>
                    <a class="cta secondary" href="<?= esc(wa_link(order_invoice_confirmation_whatsapp_message($order))); ?>" target="_blank" rel="nofollow noopener">Hubungi Admin</a>
                </div>
            <?php endif; ?>
        </article>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
