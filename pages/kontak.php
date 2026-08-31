<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

$page = function_exists('template_content_public_page') ? template_content_public_page('contact') : [];
set_seo([
    'title' => (string)($page['meta_title'] ?? ('Kontak - ' . SITE_NAME)),
    'description' => (string)($page['meta_description'] ?? 'Hubungi admin untuk konsultasi produk, layanan, atau pemesanan.'),
    'keywords' => 'kontak bisnis, whatsapp umkm, form kontak',
    'canonical' => url('kontak'),
    'robots' => ((string)($page['status'] ?? 'published') === 'published') ? 'index, follow' : 'noindex, follow',
    'type' => 'website',
    'image' => asset('images/og-default.jpg'),
]);
require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<section class="mini-hero"><div class="container"><div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span><?= esc((string)($page['label'] ?? 'Kontak')); ?></span></div><h1><?= esc((string)($page['hero_title'] ?? 'Kontak')); ?></h1><p><?= esc((string)($page['hero_description'] ?? 'Hubungi admin untuk konsultasi produk, layanan, atau kebutuhan khusus.')); ?></p></div></section>
<section class="section"><div class="container"><div class="dynamic-two-columns"><div class="dynamic-panel"><h2><?= esc((string)($page['primary_title'] ?? 'Informasi Kontak')); ?></h2><?= (string)($page['primary_html'] ?? ''); ?></div><div><?php if (!empty($page['secondary_html'])): ?><div class="dynamic-panel" style="margin-bottom:16px;"><h2><?= esc((string)($page['secondary_title'] ?? 'Kirim Pesan')); ?></h2><?= (string)($page['secondary_html'] ?? ''); ?></div><?php endif; ?><?php if (!array_key_exists('show_contact_form', $page) || !empty($page['show_contact_form'])): ?><?php $inquiryContext=['title'=>(string)($page['secondary_title'] ?? 'Kirim Pesan'),'text'=>strip_tags((string)($page['secondary_html'] ?? 'Isi form berikut agar admin bisa menghubungi Anda kembali.')),'source'=>'contact-form','category'=>'kontak','intent'=>'contact-inquiry','label'=>'Kontak','button'=>'Kirim Pesan']; require ROOT_PATH . '/components/inquiry-form.php'; ?><?php endif; ?></div></div></div></section>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
