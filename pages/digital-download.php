<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$access = digital_delivery_clean((string)($_GET['access'] ?? ''), 120);
$record = function_exists('digital_delivery_touch_download') ? digital_delivery_touch_download($access) : null;
if (!$record || empty($record['file_url'])) {
    http_response_code(403);
    set_seo(['title' => 'Download Tidak Tersedia - ' . SITE_NAME, 'robots' => 'noindex, nofollow']);
    require_once ROOT_PATH . '/components/layout/head.php';
    require_once ROOT_PATH . '/components/layout/header.php';
    ?>
    <section class="mini-hero"><div class="container"><span class="dynamic-mini-label">Digital Delivery</span><h1>Download Tidak Tersedia</h1><p>Link download tidak valid, sudah kedaluwarsa, atau limit download sudah habis.</p><a class="cta" href="<?= esc(wa_link('Halo Admin, saya butuh bantuan download produk digital.')); ?>" target="_blank" rel="nofollow noopener">Hubungi Admin</a></div></section>
    <?php
    require_once ROOT_PATH . '/components/layout/footer.php';
    return;
}

$fileUrl = (string)$record['file_url'];
if (str_starts_with($fileUrl, 'assets/') || str_starts_with($fileUrl, '/assets/')) {
    $fileUrl = url(ltrim($fileUrl, '/'));
}
header('Location: ' . $fileUrl, true, 302);
exit;
