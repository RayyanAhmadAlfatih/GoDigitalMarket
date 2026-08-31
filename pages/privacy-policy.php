<?php

declare(strict_types=1);
if (!defined('APP_START')) { exit('Direct access not allowed.'); }
$page = function_exists('template_content_public_page') ? template_content_public_page('privacy') : [];
set_seo([
    'title' => (string)($page['meta_title'] ?? ('Privacy Policy - ' . SITE_NAME)),
    'description' => (string)($page['meta_description'] ?? 'Kebijakan privasi website.'),
    'canonical' => url('privacy-policy'),
    'robots' => ((string)($page['status'] ?? 'published') === 'published') ? 'index, follow' : 'noindex, follow',
]);
require_once ROOT_PATH . '/components/layout/head.php'; require_once ROOT_PATH . '/components/layout/header.php';
?>
<section class="mini-hero"><div class="container"><div class="breadcrumb"><a href="<?= url(); ?>">Home</a><span>/</span><span><?= esc((string)($page['label'] ?? 'Privacy Policy')); ?></span></div><h1><?= esc((string)($page['hero_title'] ?? 'Privacy Policy')); ?></h1><p><?= esc((string)($page['hero_description'] ?? 'Kebijakan privasi website.')); ?></p></div></section>
<section class="section"><div class="container"><div class="dynamic-panel"><h2><?= esc((string)($page['primary_title'] ?? 'Kebijakan Privasi')); ?></h2><?= (string)($page['primary_html'] ?? ''); ?></div></div></section>
<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
