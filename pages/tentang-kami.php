<?php

declare(strict_types=1);

if (!defined('APP_START')) { exit('Direct access not allowed.'); }

$page = function_exists('template_content_public_page') ? template_content_public_page('about') : [];
set_seo([
    'title' => (string)($page['meta_title'] ?? ('Tentang Kami - ' . SITE_NAME)),
    'description' => (string)($page['meta_description'] ?? 'Profil singkat bisnis dan keunggulan.'),
    'keywords' => 'tentang kami, profil bisnis umkm, company profile',
    'canonical' => url('tentang-kami'),
    'robots' => ((string)($page['status'] ?? 'published') === 'published') ? 'index, follow' : 'noindex, follow',
    'type' => 'website',
    'image' => asset('images/og-default.jpg'),
]);
require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>
<section class="mini-hero"><div class="container"><div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span><?= esc((string)($page['label'] ?? 'Tentang Kami')); ?></span></div><h1><?= esc((string)($page['hero_title'] ?? 'Tentang Kami')); ?></h1><p><?= esc((string)($page['hero_description'] ?? 'Profil bisnis yang bisa diedit dari dashboard admin.')); ?></p></div></section>
<section class="section"><div class="container"><div class="dynamic-two-columns"><div class="dynamic-panel"><h2><?= esc((string)($page['primary_title'] ?? 'Ceritakan Bisnis Anda')); ?></h2><?= (string)($page['primary_html'] ?? ''); ?></div><div class="dynamic-panel"><h2><?= esc((string)($page['secondary_title'] ?? 'Keunggulan')); ?></h2><?= (string)($page['secondary_html'] ?? ''); ?></div></div></div></section>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
