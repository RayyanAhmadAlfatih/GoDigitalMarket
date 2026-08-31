<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$slug = custom_form_slug((string)($_GET['slug'] ?? ''), '');
$form = custom_form_find($slug, true);

if (!$form) {
    http_response_code(404);
    set_seo([
        'title' => 'Form Tidak Tersedia - ' . SITE_NAME,
        'description' => 'Form yang dicari belum aktif atau tidak ditemukan.',
    ]);
    require_once ROOT_PATH . '/components/layout/head.php';
    require_once ROOT_PATH . '/components/layout/header.php';
    ?>
    <main id="main-content" class="section section-soft">
        <div class="container narrow">
            <div class="custom-form-card custom-form-card--empty">
                <span class="custom-form-badge">Form</span>
                <h1>Form belum tersedia</h1>
                <p>Form yang Anda buka belum aktif. Silakan kembali ke website atau hubungi admin.</p>
                <a class="btn btn-primary" href="<?= esc(url('')); ?>">Kembali ke Beranda</a>
            </div>
        </div>
    </main>
    <?php
    require_once ROOT_PATH . '/components/layout/footer.php';
    return;
}

set_seo([
    'title' => (string)($form['title'] ?? 'Form') . ' - ' . SITE_NAME,
    'description' => (string)($form['description'] ?? 'Isi form singkat agar admin bisa membantu kebutuhan Anda.'),
]);

$success = (string)($_GET['success'] ?? '');
$error = (string)($_GET['error'] ?? '');

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="custom-form-page">
    <section class="mini-hero custom-form-page-hero">
        <div class="container">
            <nav class="breadcrumb"><a href="<?= esc(url('')); ?>">Home</a><span>/</span><span><?= esc((string)$form['title']); ?></span></nav>
            <span class="eyebrow">Form Online</span>
            <h1><?= esc((string)$form['title']); ?></h1>
            <?php if (!empty($form['description'])): ?>
                <p><?= esc((string)$form['description']); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="section section-soft">
        <div class="container narrow">
            <?php if ($success): ?><div class="form-message form-message--success"><?= esc($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="form-message form-message--error"><?= esc($error); ?></div><?php endif; ?>
            <?php custom_form_render((string)$form['slug'], ['show_header' => false, 'class' => 'custom-form-card--standalone']); ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
