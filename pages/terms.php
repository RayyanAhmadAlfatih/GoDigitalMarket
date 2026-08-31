<?php

declare(strict_types=1);
if (!defined('APP_START')) { exit('Direct access not allowed.'); }
$page = function_exists('template_content_public_page') ? template_content_public_page('terms') : [];
set_seo([
    'title' => (string)($page['meta_title'] ?? ('Terms - ' . SITE_NAME)),
    'description' => (string)($page['meta_description'] ?? 'Syarat dan ketentuan penggunaan website.'),
    'canonical' => url('terms'),
    'robots' => ((string)($page['status'] ?? 'published') === 'published') ? 'index, follow' : 'noindex, follow',
]);
require_once ROOT_PATH . '/components/layout/head.php'; require_once ROOT_PATH . '/components/layout/header.php';
?>
<section class="mini-hero"><div class="container"><div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span><?= esc((string)($page['label'] ?? 'Terms')); ?></span></div><h1><?= esc((string)($page['hero_title'] ?? 'Terms')); ?></h1><p><?= esc((string)($page['hero_description'] ?? 'Syarat dan ketentuan website.')); ?></p></div></section>
<section class="section"><div class="container"><div class="dynamic-panel"><h2><?= esc((string)($page['primary_title'] ?? 'Syarat & Ketentuan')); ?></h2><?= (string)($page['primary_html'] ?? ''); ?></div></div></section>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
