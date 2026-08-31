<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$context = $inquiryContext ?? [];
$title = (string)($context['title'] ?? 'Butuh Dibantu?');
$text = (string)($context['text'] ?? 'Isi form singkat berikut agar admin bisa menghubungi Anda.');
$source = (string)($context['source'] ?? 'inquiry-form');
$category = (string)($context['category'] ?? 'produk-layanan');
$location = (string)($context['location'] ?? '');
$button = (string)($context['button'] ?? 'Kirim Permintaan');
$intent = (string)($context['intent'] ?? 'inquiry');
$label = (string)($context['label'] ?? $title);
$ctaDeploymentId = (string)($context['cta_deployment_id'] ?? '');
$offerVariantId = (string)($context['offer_variant_id'] ?? '');
$ctaPlacement = (string)($context['cta_placement'] ?? '');
$ctaTrackingAttrs = '';
if ($ctaDeploymentId !== '' || $offerVariantId !== '' || $ctaPlacement !== '') {
    $ctaTrackingAttrs = ' data-cta-deployment-id="' . esc($ctaDeploymentId) . '" data-offer-variant-id="' . esc($offerVariantId) . '" data-cta-placement="' . esc($ctaPlacement) . '"';
}
$services = $context['services'] ?? ['Produk Fisik','Jasa & Layanan','Produk Digital','Booking / Reservasi','Konsultasi Umum'];
$areas = $context['areas'] ?? ['Online','Jakarta','Bandung','Surabaya','Yogyakarta','Semarang','Area lain'];
?>
<div class="inquiry-form-card" id="product-inquiry-form">
    <div class="inquiry-form-copy"><span class="inquiry-form-kicker">Form Bantuan</span><h2><?= esc($title); ?></h2><p><?= esc($text); ?></p></div>
    <form action="<?= esc(url('inquiry-submit')); ?>" method="post" class="inquiry-form"<?= $ctaTrackingAttrs; ?>>
        <?= csrf_field(); ?>
        <input type="hidden" name="source" value="<?= esc($source); ?>">
        <input type="hidden" name="category" value="<?= esc($category); ?>">
        <input type="hidden" name="intent" value="<?= esc($intent); ?>">
        <input type="hidden" name="label" value="<?= esc($label); ?>">
        <input type="hidden" name="cta_deployment_id" value="<?= esc($ctaDeploymentId); ?>">
        <input type="hidden" name="offer_variant_id" value="<?= esc($offerVariantId); ?>">
        <input type="hidden" name="cta_placement" value="<?= esc($ctaPlacement); ?>">
        <div class="form-grid">
            <label class="form-field"><span>Nama</span><input name="name" required placeholder="Nama Anda"></label>
            <label class="form-field"><span>WhatsApp</span><input name="phone" required placeholder="08xxxxxxxxxx" inputmode="tel"></label>
        </div>
        <div class="form-grid">
            <label class="form-field"><span>Kebutuhan</span><select name="need"><option value="">Pilih kebutuhan</option><?php foreach ($services as $service): ?><option value="<?= esc((string)$service); ?>"><?= esc((string)$service); ?></option><?php endforeach; ?></select></label>
            <label class="form-field"><span>Area / Lokasi</span><select name="location"><option value="<?= esc($location); ?>"><?= esc($location ?: 'Pilih area'); ?></option><?php foreach ($areas as $area): ?><option value="<?= esc((string)$area); ?>"><?= esc((string)$area); ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="form-field form-field--full"><span>Pesan</span><textarea name="message" rows="4" placeholder="Contoh: saya ingin tanya harga, stok, jadwal, atau paket yang cocok..."></textarea></label>
        <div class="inquiry-form-actions"><button class="button inquiry-submit-button" type="submit" <?= conversion_link_attrs(['source'=>$source,'type'=>'form_submit','category'=>$category,'label'=>$label,'intent'=>$intent]); ?>><?= esc($button); ?></button><small>Tim admin akan menghubungi Anda melalui WhatsApp.</small></div>
    </form>
</div>
