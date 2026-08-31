<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$ref = function_exists('order_clean') ? order_clean((string)($_GET['ref'] ?? ''), 80) : trim((string)($_GET['ref'] ?? ''));
$token = function_exists('order_clean') ? order_clean((string)($_GET['token'] ?? ''), 80) : trim((string)($_GET['token'] ?? ''));
$paymentState = function_exists('payment_gateway_slug') ? payment_gateway_slug((string)($_GET['payment'] ?? 'finish')) : 'finish';
$order = function_exists('order_find_by_reference') ? order_find_by_reference($ref, $token) : null;
$hasOrder = is_array($order);
$publicRef = $hasOrder && function_exists('order_public_reference') ? order_public_reference($order) : ($ref ?: 'ORD');

seo_noindex();
set_seo([
    'title' => 'Status Pembayaran ' . $publicRef,
    'description' => 'Halaman kembali setelah pembayaran. Status final mengikuti webhook/callback payment gateway.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<section class="mini-hero order-public-hero--template">
    <div class="container">
        <div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span>Payment Return</span></div>
        <span class="dynamic-mini-label">Pembayaran</span>
        <h1>Pembayaran Sedang Diproses</h1>
        <p>Terima kasih. Status pembayaran otomatis akan diperbarui setelah provider mengirim webhook/callback valid ke website.</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:880px">
        <article class="admin-card" style="background:#fff;border:1px solid #e2e8f0;border-radius:28px;padding:1.5rem;box-shadow:0 20px 70px rgba(15,23,42,.07)">
            <span class="dynamic-mini-label">No. Order</span>
            <h2><?= esc($publicRef); ?></h2>
            <?php if ($hasOrder): ?>
                <p>Status terakhir: <strong><?= esc(order_public_payment_status_label($order)); ?></strong></p>
                <p>Jika status belum berubah, tunggu beberapa saat atau hubungi admin dengan nomor order di atas.</p>
                <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
                    <a class="cta" href="<?= esc(order_status_url($order)); ?>" rel="nofollow">Cek Status Order</a>
                    <a class="cta secondary" href="<?= esc(order_public_invoice_url($order)); ?>" rel="nofollow">Lihat Invoice</a>
                    <a class="cta secondary" href="<?= esc(wa_link(order_status_whatsapp_message($order))); ?>" target="_blank" rel="nofollow noopener">Hubungi Admin</a>
                </div>
            <?php else: ?>
                <p>Order tidak ditemukan. Pastikan link kembali dari provider masih lengkap.</p>
                <a class="cta" href="<?= esc(url('katalog')); ?>">Kembali ke Katalog</a>
            <?php endif; ?>
        </article>
    </div>
</section>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
