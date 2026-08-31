<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$action = (string)($_GET['action'] ?? 'list');
$message = (string)($_GET['message'] ?? '');
$error = '';
$editId = (int)($_GET['id'] ?? 0);

if ($action === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/produk');
}

function admin_product_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}

function admin_upload_product_image(): ?string
{
    if (empty($_FILES['image_file']['tmp_name']) || !is_uploaded_file((string)$_FILES['image_file']['tmp_name'])) {
        return null;
    }

    try {
        $originalBaseName = pathinfo(
            (string)($_FILES['image_file']['name'] ?? ''),
            PATHINFO_FILENAME
        );

        $baseName = trim((string)$originalBaseName)
            ?: trim((string)($_POST['slug'] ?? ''))
            ?: trim((string)($_POST['title'] ?? ''))
            ?: trim((string)($_POST['sku'] ?? ''))
            ?: 'item-katalog';

        return image_upload_to_webp(
            $_FILES['image_file'],
            'products',
            $baseName,
            [
                'prefix' => 'produk',
                'max_size' => 12 * 1024 * 1024,
                'max_width' => 1600,
                'max_height' => 1600,
                'quality' => 78,
            ]
        );
    } catch (Throwable $e) {
        $GLOBALS['admin_product_upload_error'] = $e->getMessage();
        error_log('[ADMIN_PRODUCT_UPLOAD] ' . $e->getMessage());
        return null;
    }
}

function admin_product_payload(?array $current = null): array
{
    $payload = product_payload_prepare($_POST, $current);
    $uploaded = admin_upload_product_image();
    if ($uploaded) {
        $payload['image'] = $uploaded;
    }
    return $payload;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (admin_password_needs_setup($adminPassword)) {
        $error = 'ADMIN_PASSWORD belum aman. Ganti nilai ADMIN_PASSWORD di file .env dengan password kuat sebelum login admin.';
    } elseif (!admin_product_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'produk']);
            }
            redirect_302('admin/produk');
        }
        $error = 'Password admin salah.';
    } else {
        if (!verify_csrf()) {
            $error = 'Token keamanan tidak valid. Refresh halaman lalu coba lagi.';
        } elseif (($_POST['form_action'] ?? '') === 'convert_seed') {
            $result = product_convert_seed_to_storage();
            redirect_302('admin/produk?message=' . rawurlencode('Impor contoh data selesai: ' . $result['created'] . ' dibuat, ' . $result['skipped'] . ' dilewati.'));
        } elseif ($action === 'create') {
            $payload = admin_product_payload();
            if ((string)($GLOBALS['admin_product_upload_error'] ?? '') !== '') {
                $error = (string)$GLOBALS['admin_product_upload_error'];
            } elseif (trim((string)$payload['title']) === '') {
                $error = 'Nama item wajib diisi.';
            } else {
                $createdId = product_create($payload);
                if ($createdId > 0) {
                    if (function_exists('content_restriction_save_for')) {
                        content_restriction_save_for('product', ['id' => $createdId, 'slug' => $payload['slug'] ?? ''], content_restriction_rule_from_post($_POST));
                    }
                    redirect_302('admin/produk?message=' . rawurlencode('Item berhasil ditambahkan.'));
                }
                $error = 'Item gagal ditambahkan.';
            }
        } elseif ($action === 'edit' && $editId > 0) {
            $current = product_admin_find($editId);
            if (!$current) {
                $error = 'Item tidak ditemukan.';
            } else {
                $payload = admin_product_payload($current);
                if ((string)($GLOBALS['admin_product_upload_error'] ?? '') !== '') {
                    $error = (string)$GLOBALS['admin_product_upload_error'];
                } else {
                    $updated = product_update($editId, $payload);
                    if ($updated && function_exists('content_restriction_save_for')) {
                        content_restriction_save_for('product', ['id' => $editId, 'slug' => $payload['slug'] ?? ''], content_restriction_rule_from_post($_POST));
                    }
                    if ($updated) {
                        redirect_302('admin/produk?message=' . rawurlencode('Item berhasil diperbarui.'));
                    }
                    $error = 'Item gagal diperbarui.';
                }
            }
        } elseif ($action === 'delete' && $editId > 0) {
            if (product_delete($editId)) {
                redirect_302('admin/produk?message=' . rawurlencode('Item berhasil dihapus.'));
            }
            $error = 'Item gagal dihapus.';
        }
    }
}

$loggedIn = admin_product_logged_in();
$editingProduct = ($loggedIn && $editId > 0) ? product_admin_find($editId) : null;
$products = $loggedIn ? product_managed_products() : [];
$productSearchQuery = trim((string)($_GET['q'] ?? ''));
$productPerPage = (int)($_GET['per_page'] ?? 10);
$productPerPageOptions = [10, 20, 50, 100];
if (!in_array($productPerPage, $productPerPageOptions, true)) {
    $productPerPage = 10;
}
$productCurrentPage = max(1, (int)($_GET['page'] ?? 1));
$productFiltered = $products;

if ($productSearchQuery !== '') {
    $needle = mb_strtolower($productSearchQuery);
    $productFiltered = array_values(array_filter($products, static function (array $product) use ($needle): bool {
        $haystack = implode(' ', array_map('strval', [
            $product['title'] ?? '',
            $product['slug'] ?? '',
            $product['sku'] ?? '',
            $product['category'] ?? '',
            $product['animal_type'] ?? '',
            $product['breed'] ?? '',
            $product['tier'] ?? ($product['subcategory'] ?? ''),
            $product['location'] ?? '',
        ]));

        return str_contains(mb_strtolower($haystack), $needle);
    }));
}

$productTotal = count($productFiltered);
$productTotalPages = max(1, (int)ceil($productTotal / $productPerPage));
$productCurrentPage = min($productCurrentPage, $productTotalPages);
$productOffset = ($productCurrentPage - 1) * $productPerPage;
$productPageItems = array_slice($productFiltered, $productOffset, $productPerPage);
$productCategoryOptions = function_exists('business_category_labels') ? business_category_labels('catalog', true) : ['Produk Fisik','Jasa & Layanan','Produk Digital','E-book','E-course','Kuliner','Booking','Custom Order'];
$currentProductCategory = trim((string)(($editingProduct['category'] ?? '') ?: ($_POST['category'] ?? '')));
if ($currentProductCategory !== '' && !in_array($currentProductCategory, $productCategoryOptions, true)) {
    $productCategoryOptions[] = $currentProductCategory;
}
$shippingOriginOptions = function_exists('shipping_origin_options') ? shipping_origin_options(null, true) : [];

function admin_product_page_url(int $page, ?int $perPage = null): string
{
    $query = array_filter([
        'q' => trim((string)($_GET['q'] ?? '')),
        'per_page' => $perPage ?? (int)($_GET['per_page'] ?? 10),
        'page' => $page,
    ], static fn($value): bool => $value !== '' && $value !== null);

    return url('admin/produk' . ($query ? '?' . http_build_query($query) : ''));
}
$storageLabel = (function_exists('storage_mysql_enabled') && storage_mysql_enabled('products')) ? 'MYSQL AKTIF' : 'JSON FILE AKTIF';
$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Dashboard Katalog Bisnis - ' . SITE_NAME,
    'description' => 'Kelola katalog produk, jasa, paket, menu, booking, dan SEO item bisnis.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Panel Katalog</div>
                <h1>Kelola Katalog Bisnis</h1>
                <p>Kelola produk fisik, jasa, menu, booking, produk digital, e-book, e-course, dan paket custom dari satu tempat.</p>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <?php if (!$loggedIn): ?>
                <div class="admin-login-layout">
                    <div class="admin-login-copy">
                        <span class="admin-badge">Akses terbatas</span>
                        <h2>Masuk Dashboard Produk</h2>
                        <p>Gunakan password admin yang sama dengan dashboard artikel.</p>
                    </div>
                    <form method="post" class="admin-card admin-login-card">
                        <?= csrf_field(); ?>
                        <label for="password">Password Admin</label>
                        <input id="password" type="password" name="password" placeholder="Masukkan password admin" required autocomplete="current-password">
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--full">Masuk Dashboard</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-toolbar">
                    <div><span class="admin-badge"><?= esc($storageLabel); ?></span><h2>Katalog Produk, Jasa & Digital</h2></div>
                    <div class="admin-toolbar__actions">
                        <a class="admin-btn <?= $action === 'list' ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= url('admin/produk'); ?>">Daftar</a>
                        <a class="admin-btn <?= $action === 'create' ? 'admin-btn--primary' : 'admin-btn--soft'; ?>" href="<?= url('admin/produk?action=create'); ?>">+ Tambah Item</a>
                    </div>
                </div>

                <?php if (in_array($action, ['create','edit'], true)): ?>
                    <?php
                    $p = $editingProduct ?? [];
                    $selectedMediaImage = trim((string)($_GET['media_image'] ?? ''));
                    if ($selectedMediaImage !== '' && function_exists('media_library_is_allowed_image_url') && media_library_is_allowed_image_url($selectedMediaImage)) {
                        $p['image'] = $selectedMediaImage;
                    }
                    ?>
                    <form method="post" enctype="multipart/form-data" class="admin-card admin-product-form">
                        <?= csrf_field(); ?>
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <h2><?= $action === 'edit' ? 'Edit Item' : 'Tambah Item Baru'; ?></h2>
                                <p>Isi data katalog agar user mudah mencari dan langsung chat WhatsApp.</p>
                            </div>
                            <button class="admin-btn admin-btn--primary" type="submit">Simpan Item</button>
                        </div>

                        <div class="admin-form-grid">
                            <div class="admin-form-main">
                                <label>Nama Item *</label>
                                <input name="title" value="<?= esc((string)($p['title'] ?? '')); ?>" required placeholder="Contoh: E-book Panduan Bisnis Digital">

                                <label>Slug URL</label>
                                <input name="slug" value="<?= esc((string)($p['slug'] ?? '')); ?>" placeholder="auto dari nama produk">

                                <label>Ringkasan Item</label>
                                <textarea name="excerpt" rows="3" placeholder="Deskripsi singkat untuk kartu katalog"><?= esc((string)($p['excerpt'] ?? '')); ?></textarea>

                                <label>Deskripsi Utama</label>
                                <textarea name="description" rows="4" placeholder="Deskripsi singkat untuk mesin pencari dan pelanggan"><?= esc((string)($p['description'] ?? '')); ?></textarea>

                                <label>Konten Detail</label>
                                <textarea id="content" name="content" rows="8" class="jodit-editor"><?= esc((string)($p['content'] ?? '')); ?></textarea>

                                <div class="admin-card admin-nested-card">
                                    <h3>SEO Item</h3>
                                    <label>Meta Title</label>
                                    <input name="meta_title" value="<?= esc((string)($p['seo']['title'] ?? $p['title'] ?? '')); ?>">
                                    <label>Meta Description</label>
                                    <textarea name="meta_description" rows="3"><?= esc((string)($p['seo']['description'] ?? $p['description'] ?? '')); ?></textarea>
                                    <label>Meta Keywords</label>
                                    <input name="meta_keywords" value="<?= esc(implode(', ', $p['seo']['keywords'] ?? [])); ?>" placeholder="produk unggulan, jasa profesional, paket promo, ...">
                                    <h3 class="admin-subtitle">Schema Markup</h3>
                                    <?php $schemaMode = (string)($p['seo']['schema_mode'] ?? 'auto'); ?>
                                    <label>Mode Schema SEO</label>
                                    <select name="schema_mode" id="schema_mode">
                                        <?php foreach ([
                                            'auto' => 'Auto aman sesuai jenis item',
                                            'product_offer' => 'Product + Offer / harga tetap',
                                            'product_no_offer' => 'Product tanpa Offer / harga by request',
                                            'service' => 'Service schema tanpa wajib harga',
                                            'course' => 'Course schema',
                                            'itempage' => 'ItemPage/WebPage saja',
                                            'none' => 'No Product/Service Schema',
                                        ] as $value => $label): ?>
                                            <option value="<?= esc($value); ?>" <?= $schemaMode === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="admin-help-text">Untuk produk harga berubah-ubah seperti dinar, bawang goreng, komoditas, grosir, atau custom order, pilih <strong>Product tanpa Offer</strong> atau <strong>No Product/Service Schema</strong>. Jangan pakai Offer jika harga belum angka valid.</p>
                                    <label>Tipe Schema Teknis</label>
                                    <select name="schema_type">
                                        <?php foreach (['Product' => 'Product', 'Service' => 'Service', 'Course' => 'Course', 'IndividualProduct' => 'IndividualProduct', 'ProductModel' => 'ProductModel'] as $value => $label): ?>
                                            <option value="<?= esc($value); ?>" <?= (($p['seo']['schema_type'] ?? 'Product') === $value) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Tipe Offer</label>
                                    <select name="schema_offer_type">
                                        <?php foreach (['Offer' => 'Offer', 'AggregateOffer' => 'AggregateOffer', 'none' => 'Tanpa Offer'] as $value => $label): ?>
                                            <option value="<?= esc($value); ?>" <?= (($p['seo']['schema_offer_type'] ?? 'Offer') === $value) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Brand/Penyedia Schema</label>
                                    <input name="schema_brand" value="<?= esc((string)($p['seo']['schema_brand'] ?? SITE_NAME)); ?>" placeholder="<?= esc(SITE_NAME); ?>">
                                    <label>Kondisi Item</label>
                                    <select name="schema_condition">
                                        <?php foreach ([
                                            'https://schema.org/NewCondition' => 'NewCondition',
                                            'https://schema.org/UsedCondition' => 'UsedCondition'
                                        ] as $value => $label): ?>
                                            <option value="<?= esc($value); ?>" <?= (($p['seo']['schema_condition'] ?? 'https://schema.org/NewCondition') === $value) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Rating Opsional</label>
                                    <input name="rating_value" value="<?= esc((string)($p['seo']['rating_value'] ?? '')); ?>" placeholder="Contoh: 4.8">
                                    <label>Jumlah Review Opsional</label>
                                    <input name="review_count" value="<?= esc((string)($p['seo']['review_count'] ?? '')); ?>" placeholder="Contoh: 27">
                                </div>
                            </div>

                            <aside class="admin-form-side">
                                <div class="admin-card admin-nested-card admin-item-type-card">
                                    <h3>Data Katalog</h3>
                                    <label>SKU</label>
                                    <input name="sku" value="<?= esc((string)($p['sku'] ?? '')); ?>" placeholder="ITEM-001">

                                    <label>Jenis Item</label>
                                    <select name="item_type_key" id="item_type_key">
                                        <?php foreach (product_item_type_definitions() as $key => $definition): ?>
                                            <option value="<?= esc($key); ?>" <?= product_item_type_key($p ?: 'physical') === $key ? 'selected' : ''; ?>><?= esc((string)$definition['icon'] . ' ' . (string)$definition['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="admin-help-text">Pilih jenis item agar tombol, label, katalog, dan SEO lebih sesuai dengan bisnis.</p>

                                    <label>Kategori</label>
                                    <select name="category">
                                        <?php foreach ($productCategoryOptions as $option): ?>
                                            <option value="<?= esc($option); ?>" <?= (($p['category'] ?? '') === $option) ? 'selected' : ''; ?>><?= esc($option); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <input type="hidden" name="animal_type" id="animal_type_value" value="<?= esc(product_item_type_label($p ?: 'physical')); ?>">
                                    <input type="hidden" name="type" id="type_value" value="<?= esc(product_item_type_label($p ?: 'physical')); ?>">

                                    <label>Karakter Item</label>
                                    <select name="breed">
                                        <?php foreach (['Produk Retail','Menu Kuliner','Layanan Profesional','Produk Digital','E-book / PDF','Video Course','Template / File Kerja','Booking / Reservasi','Paket Custom'] as $option): ?>
                                            <option value="<?= esc($option); ?>" <?= (($p['breed'] ?? '') === $option) ? 'selected' : ''; ?>><?= esc($option); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label>Kelas/Paket</label>
                                    <select name="tier">
                                        <?php foreach (['Starter','Ekonomis','Medium','Premium','Best Seller','Bundle','Custom'] as $option): ?>
                                            <option value="<?= esc($option); ?>" <?= (($p['tier'] ?? $p['subcategory'] ?? '') === $option) ? 'selected' : ''; ?>><?= esc($option); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="subcategory" value="<?= esc((string)($p['tier'] ?? $p['subcategory'] ?? 'Medium')); ?>">

                                    <label>Area / Kanal Akses</label>
                                    <select name="location">
                                        <?php foreach (['Online','Indonesia','Jakarta','Bandung','Surabaya','Yogyakarta','Semarang','Sesuai Jadwal','Area Custom'] as $option): ?>
                                            <option value="<?= esc($option); ?>" <?= (($p['location'] ?? '') === $option) ? 'selected' : ''; ?>><?= esc($option); ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <?php if ($shippingOriginOptions): ?>
                                        <label>Asal Pengiriman / Gudang</label>
                                        <select name="shipping_origin_id">
                                            <option value="">Auto/default dari pengaturan ongkir</option>
                                            <?php foreach ($shippingOriginOptions as $origin): ?>
                                                <option value="<?= esc((string)($origin['id'] ?? '')); ?>" <?= (($p['shipping_origin_id'] ?? '') === ($origin['id'] ?? '')) ? 'selected' : ''; ?>><?= esc(function_exists('shipping_origin_label') ? shipping_origin_label($origin) : (string)($origin['name'] ?? 'Gudang')); ?><?= !empty($origin['default']) ? ' · Default' : ''; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label>Catatan Origin Produk</label>
                                        <input name="shipping_origin_note" value="<?= esc((string)($p['shipping_origin_note'] ?? '')); ?>" placeholder="Contoh: ready stock dari gudang Bandung">
                                        <p class="admin-help-text">Dipakai untuk hitung ongkir multi gudang. Jika kosong, sistem pakai default/auto keyword.</p>
                                    <?php endif; ?>

                                    <div class="admin-commerce-policy-box">
                                        <h3>Aturan Checkout Produk</h3>
                                        <p class="admin-help-text">Atur perilaku jualan khusus per produk. Kosongkan/ikuti global jika produk ini tidak punya aturan spesial.</p>

                                        <label>Aturan Ongkir Produk</label>
                                        <select name="shipping_rule_mode">
                                            <?php foreach ((function_exists('commerce_shipping_policy_options') ? commerce_shipping_policy_options() : ['global' => 'Ikuti setting toko']) as $value => $label): ?>
                                                <option value="<?= esc((string)$value); ?>" <?= (($p['shipping_rule_mode'] ?? 'global') === $value) ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                        <label>Aturan Pembayaran Produk</label>
                                        <select name="payment_rule_mode">
                                            <?php foreach ((function_exists('commerce_payment_policy_options') ? commerce_payment_policy_options() : ['global' => 'Ikuti setting pembayaran toko']) as $value => $label): ?>
                                                <option value="<?= esc((string)$value); ?>" <?= (($p['payment_rule_mode'] ?? 'global') === $value) ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                        <label>Gateway/Channel yang Disiapkan</label>
                                        <div class="admin-check-grid">
                                            <?php $allowedGateways = function_exists('commerce_gateway_list_normalize') ? commerce_gateway_list_normalize($p['allowed_payment_gateways'] ?? []) : (array)($p['allowed_payment_gateways'] ?? []); ?>
                                            <?php foreach ((function_exists('commerce_gateway_options') ? commerce_gateway_options() : []) as $value => $label): ?>
                                                <label class="admin-check"><input type="checkbox" name="allowed_payment_gateways[]" value="<?= esc((string)$value); ?>" <?= in_array((string)$value, $allowedGateways, true) ? 'checked' : ''; ?>> <?= esc((string)$label); ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="admin-help-text">Ini belum memaksa transaksi otomatis sampai payment gateway real-charge aktif, tapi sudah jadi policy/snapshot order.</p>

                                        <label>Catatan Aturan Khusus</label>
                                        <textarea name="checkout_rule_note" rows="3" placeholder="Contoh: Free ongkir area Jabodetabek, luar area konfirmasi admin."><?= esc((string)($p['checkout_rule_note'] ?? '')); ?></textarea>

                                        <div class="admin-two-cols">
                                            <div>
                                                <label>Estimasi Pre-order</label>
                                                <input name="preorder_eta" value="<?= esc((string)($p['preorder_eta'] ?? '')); ?>" placeholder="Contoh: 7-14 hari kerja">
                                            </div>
                                            <div>
                                                <label>Catatan Pre-order</label>
                                                <input name="preorder_note" value="<?= esc((string)($p['preorder_note'] ?? '')); ?>" placeholder="Contoh: produksi setelah DP masuk">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="admin-card admin-nested-card">
                                    <h3>Harga & Stok</h3>
                                    <label>Harga</label><input name="price" value="<?= esc((string)($p['price'] ?? '')); ?>" placeholder="99000">
                                    <label>Harga Promo</label><input name="sale_price" value="<?= esc((string)($p['sale_price'] ?? '')); ?>" placeholder="Opsional">
                                    <label>Berat / Spesifikasi</label><input name="weight" value="<?= esc((string)($p['weight'] ?? '')); ?>" placeholder="Contoh: 1 kg, 10 modul, 5 video">
                                    <label>Durasi / Catatan Opsional</label><input name="age" value="<?= esc((string)($p['age'] ?? '')); ?>" placeholder="Contoh: akses 1 tahun, sesi 60 menit">
                                    <label>Stok / Kuota</label><input name="stock" value="<?= esc((string)($p['stock'] ?? '1')); ?>" placeholder="Kosongkan jika tidak perlu">
                                    <label>Status</label>
                                    <select name="stock_status">
                                        <?php foreach (['in_stock' => 'Tersedia', 'out_of_stock' => 'Habis', 'preorder' => 'Pre-order', 'contact_admin' => 'Hubungi Admin'] as $value => $label): ?>
                                            <option value="<?= esc($value); ?>" <?= (($p['stock_status'] ?? '') === $value) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="admin-card admin-nested-card" style="margin-top:12px;background:#f8fafc">
                                        <h3>Inventory Control</h3>
                                        <p class="admin-help-text">Untuk produk fisik, kuota workshop, kursi kelas, atau stok terbatas. Sistem menghitung stok tersedia dari stok total dikurangi reserved order dan order paid/deal.</p>
                                        <input type="hidden" name="stock_tracking_enabled" value="0"><label class="admin-check"><input type="checkbox" name="stock_tracking_enabled" value="1" <?= !empty($p['stock_tracking_enabled']) ? 'checked' : ''; ?>> Aktifkan tracking stok/kuota produk ini</label>
                                        <div class="admin-two-cols">
                                            <div><label>Reserved Manual</label><input name="stock_reserved_manual" value="<?= esc((string)($p['stock_reserved_manual'] ?? '0')); ?>" placeholder="0" inputmode="numeric"></div>
                                            <div><label>Low Stock Alert</label><input name="stock_low_threshold" value="<?= esc((string)($p['stock_low_threshold'] ?? '3')); ?>" placeholder="3" inputmode="numeric"></div>
                                        </div>
                                        <input type="hidden" name="stock_allow_backorder" value="0"><label class="admin-check"><input type="checkbox" name="stock_allow_backorder" value="1" <?= !empty($p['stock_allow_backorder']) ? 'checked' : ''; ?>> Izinkan backorder/pre-order saat stok habis</label>
                                        <input type="hidden" name="stock_auto_status" value="0"><label class="admin-check"><input type="checkbox" name="stock_auto_status" value="1" <?= !array_key_exists('stock_auto_status', $p) || !empty($p['stock_auto_status']) ? 'checked' : ''; ?>> Bantu admin lewat status otomatis di dashboard stok</label>
                                        <label>Catatan Stok Internal</label>
                                        <textarea name="stock_note" rows="3" placeholder="Contoh: restock tiap Senin, batch terbatas, atau kuota kelas maksimal 20 seat."><?= esc((string)($p['stock_note'] ?? '')); ?></textarea>
                                        <?php if ($p && function_exists('inventory_product_summary')): ?>
                                            <?php $inventoryPreview = inventory_product_summary($p); ?>
                                            <p class="admin-help-text">Ringkasan saat ini: tersedia <?= (int)$inventoryPreview['available']; ?>, reserved <?= (int)$inventoryPreview['reserved']; ?>, committed <?= (int)$inventoryPreview['committed']; ?> · <?= esc((string)$inventoryPreview['status_label']); ?>.</p>
                                        <?php endif; ?>
                                    </div>
                                    <label class="admin-check"><input type="checkbox" name="featured" value="1" <?= !empty($p['featured']) ? 'checked' : ''; ?>> Jadikan item unggulan</label>
                                </div>

                                <div class="admin-card admin-nested-card admin-digital-product-settings">
                                    <h3>Akses Produk Digital</h3>
                                    <p class="admin-help-text">Isi bagian ini untuk e-book, e-course, template, file ZIP, preset, video, audio, atau produk digital lain. Member Area memakai data ini untuk course, akses digital, dan lisensi buyer.</p>
                                    <label>Tipe Konten Digital</label>
                                    <select name="digital_delivery_type">
                                        <?php foreach (['digital' => 'Produk digital umum', 'ebook' => 'E-book / PDF', 'course' => 'E-course / Video', 'template' => 'Template / File kerja', 'zip' => 'File ZIP', 'link' => 'Link akses', 'bundle' => 'Bundle digital'] as $value => $label): ?>
                                            <option value="<?= esc($value); ?>" <?= (($p['digital_delivery_type'] ?? '') === $value) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Mode Akses Buyer</label>
                                    <select name="digital_access_mode">
                                        <?php foreach (['after_payment' => 'Akses setelah pembayaran', 'direct_download' => 'Download setelah pembayaran', 'access_link' => 'Link akses khusus', 'member_area' => 'Member area'] as $value => $label): ?>
                                            <option value="<?= esc($value); ?>" <?= (($p['digital_access_mode'] ?? 'after_payment') === $value) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>URL File / Download</label>
                                    <input name="digital_file_url" value="<?= esc((string)($p['digital_file_url'] ?? '')); ?>" placeholder="Opsional, bisa diisi setelah checkout siap">
                                    <label>URL Akses / Kelas / Komunitas</label>
                                    <input name="digital_access_url" value="<?= esc((string)($p['digital_access_url'] ?? '')); ?>" placeholder="Opsional, contoh link kelas atau halaman akses">
                                    <label>Instruksi Akses untuk Buyer</label>
                                    <textarea name="digital_instructions" rows="4" placeholder="Contoh: Setelah pembayaran disetujui, akses akan muncul di member area."><?= esc((string)($p['digital_instructions'] ?? '')); ?></textarea>
                                    <div class="admin-two-cols">
                                        <div><label>Batas Download</label><input name="download_limit" value="<?= esc((string)($p['download_limit'] ?? '')); ?>" placeholder="0 = tidak dibatasi"></div>
                                        <div><label>Masa Akses (hari)</label><input name="access_duration_days" value="<?= esc((string)($p['access_duration_days'] ?? '')); ?>" placeholder="0 = fleksibel"></div>
                                    </div>
                                    <label class="admin-check"><input type="checkbox" name="member_area_enabled" value="1" <?= !empty($p['member_area_enabled']) ? 'checked' : ''; ?>> Siapkan item ini untuk member area</label>

                                    <div class="admin-card admin-nested-card" style="margin-top:12px;background:#f8fbff">
                                        <h3>Course / Member Area</h3>
                                        <p class="admin-help-text">Untuk e-course, kelas video, template academy, atau konten personal brand. Format modul: Judul | URL materi | Durasi | Catatan.</p>
                                        <label>Daftar Modul Course</label>
                                        <textarea name="course_modules_raw" rows="5" placeholder="Modul 1: Pengenalan | https://... | 12 menit | Mulai dari sini
Modul 2: Praktik | https://... | 20 menit | Tonton berurutan"><?= esc((string)($p['course_modules_raw'] ?? '')); ?></textarea>
                                    </div>

                                    <div class="admin-card admin-nested-card" style="margin-top:12px;background:#fff7ed">
                                        <h3>Lisensi Produk Digital / Software</h3>
                                        <label class="admin-check"><input type="checkbox" name="license_enabled" value="1" <?= !empty($p['license_enabled']) ? 'checked' : ''; ?>> Produk ini memakai lisensi / license key</label>
                                        <label>Tipe Lisensi</label>
                                        <select name="license_type">
                                            <?php foreach (['single_site' => 'Single site / 1 website', 'multi_site' => 'Multi site', 'user_based' => 'Per user/akun', 'lifetime' => 'Lifetime', 'subscription' => 'Subscription / masa aktif'] as $value => $label): ?>
                                                <option value="<?= esc($value); ?>" <?= (($p['license_type'] ?? 'single_site') === $value) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="admin-two-cols">
                                            <div><label>Jumlah Seat/User</label><input name="license_seats" value="<?= esc((string)($p['license_seats'] ?? '1')); ?>" placeholder="1"></div>
                                            <div><label>Batas Aktivasi</label><input name="license_activation_limit" value="<?= esc((string)($p['license_activation_limit'] ?? '1')); ?>" placeholder="1"></div>
                                        </div>
                                        <label>Masa Lisensi (hari, 0 = lifetime/fleksibel)</label>
                                        <input name="license_duration_days" value="<?= esc((string)($p['license_duration_days'] ?? '365')); ?>" placeholder="365">
                                        <div class="admin-two-cols">
                                            <div>
                                                <label>Mode Validasi Lisensi</label>
                                                <select name="license_validation_mode">
                                                    <?php foreach (['global' => 'Ikuti License Manager', 'local' => 'Local only', 'central' => 'Central server only', 'hybrid' => 'Hybrid central + local fallback'] as $value => $label): ?>
                                                        <option value="<?= esc($value); ?>" <?= (($p['license_validation_mode'] ?? 'global') === $value) ? 'selected' : ''; ?>><?= esc($label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Central Product ID</label>
                                                <input name="central_license_product_id" value="<?= esc((string)($p['central_license_product_id'] ?? '')); ?>" placeholder="ugrowth-template-pro">
                                            </div>
                                        </div>
                                        <label class="admin-check"><input type="checkbox" name="license_domain_lock" value="1" <?= !empty($p['license_domain_lock']) ? 'checked' : ''; ?>> Kunci lisensi ke domain/website pertama yang aktivasi</label>
                                        <label>Catatan Lisensi untuk Buyer</label>
                                        <textarea name="license_note" rows="3" placeholder="Contoh: Lisensi berlaku untuk 1 domain. Hubungi admin untuk reset aktivasi."><?= esc((string)($p['license_note'] ?? '')); ?></textarea>
                                    </div>

                                    <div class="admin-card admin-nested-card" style="margin-top:12px;background:#f0fdf4">
                                        <h3>Membership / Subscription</h3>
                                        <p class="admin-help-text">Untuk komunitas premium, course berlangganan, software subscription, support/update plan, atau membership bulanan/tahunan.</p>
                                        <label class="admin-check"><input type="checkbox" name="subscription_enabled" value="1" <?= !empty($p['subscription_enabled']) ? 'checked' : ''; ?>> Produk ini memakai masa berlangganan</label>
                                        <div class="admin-two-cols">
                                            <div>
                                                <label>Siklus Berlangganan</label>
                                                <select name="subscription_billing_cycle">
                                                    <?php foreach ((function_exists('subscription_cycle_options') ? subscription_cycle_options() : ['none' => 'Sekali beli', 'monthly' => 'Bulanan', 'six_months' => '6 Bulanan', 'yearly' => 'Tahunan']) as $value => $label): ?>
                                                        <option value="<?= esc((string)$value); ?>" <?= (($p['subscription_billing_cycle'] ?? 'none') === $value) ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div>
                                                <label>Durasi Custom (hari)</label>
                                                <input name="subscription_duration_days" value="<?= esc((string)($p['subscription_duration_days'] ?? '0')); ?>" placeholder="0 = ikut siklus">
                                            </div>
                                        </div>
                                        <div class="admin-two-cols">
                                            <div><label>Grace Period (hari)</label><input name="subscription_grace_days" value="<?= esc((string)($p['subscription_grace_days'] ?? '3')); ?>" placeholder="3"></div>
                                            <div><label>Mode Renewal</label><select name="subscription_renewal_mode"><option value="manual_reminder" <?= (($p['subscription_renewal_mode'] ?? 'manual_reminder') === 'manual_reminder') ? 'selected' : ''; ?>>Manual reminder</option><option value="payment_link" <?= (($p['subscription_renewal_mode'] ?? '') === 'payment_link') ? 'selected' : ''; ?>>Payment link/checkout ulang</option><option value="auto_gateway_future" <?= (($p['subscription_renewal_mode'] ?? '') === 'auto_gateway_future') ? 'selected' : ''; ?>>Auto gateway future-ready</option></select></div>
                                        </div>
                                        <label>Catatan Subscription untuk Buyer</label>
                                        <textarea name="subscription_note" rows="3" placeholder="Contoh: Akses membership berlaku 30 hari dan bisa diperpanjang sebelum expired."><?= esc((string)($p['subscription_note'] ?? '')); ?></textarea>
                                    </div>
                                </div>

                                <div class="admin-card admin-nested-card">
                                    <h3>Media Item</h3>
                                    <?php if (!empty($p['image'])): ?><img class="admin-thumb" src="<?= esc((string)$p['image']); ?>" alt=""><?php endif; ?>
                                    <label>Upload Gambar Utama</label><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp">
                                    <label>Atau URL Gambar</label><input name="image_url" value="<?= esc((string)($p['image'] ?? '')); ?>">
                                    <a class="admin-btn admin-btn--soft admin-btn--full" href="<?= url('admin/media-library?target=product'); ?>" target="_blank" rel="noopener">Pilih dari Media Library</a>
                                    <label>Alt Gambar</label><input name="image_alt" value="<?= esc((string)($p['image_alt'] ?? $p['title'] ?? '')); ?>">
                                    <label>Galeri Gambar URL, 1 per baris</label><textarea name="gallery_raw" rows="4"><?= esc(implode("\n", $p['gallery'] ?? [])); ?></textarea>
                                </div>

                                <?= function_exists('content_restriction_admin_fields') ? content_restriction_admin_fields('product', $p) : ''; ?>

                                <?php if (function_exists('seo_quality_render_inline_assistant')) { seo_quality_render_inline_assistant('product', $p); } ?>

                                <div class="admin-card admin-nested-card">
                                    <h3>CTA & Keunggulan</h3>
                                    <label>Teks Chat WhatsApp</label><textarea name="whatsapp_text" rows="3"><?= esc((string)($p['whatsapp_text'] ?? '')); ?></textarea>
                                    <label>Keunggulan, 1 per baris</label><textarea name="features_raw" rows="4"><?= esc(implode("\n", $p['features'] ?? [])); ?></textarea>
                                </div>
                            </aside>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="admin-card">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <h2>Daftar Item Dinamis</h2>
                                <p>Menampilkan item aktif yang tersimpan dan bisa diedit, disembunyikan, atau dihapus dari dashboard.</p>
                            </div>
                            <div class="admin-list-tools">
                                <form method="get" action="<?= url('admin/produk'); ?>" class="admin-list-search">
                                    <input type="search" name="q" value="<?= esc($productSearchQuery); ?>" placeholder="Cari nama, slug, SKU, jenis, lokasi...">
                                    <select name="per_page" aria-label="Jumlah produk per halaman">
                                        <?php foreach ($productPerPageOptions as $option): ?>
                                            <option value="<?= (int)$option; ?>" <?= $productPerPage === $option ? 'selected' : ''; ?>><?= (int)$option; ?> / halaman</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="admin-btn admin-btn--primary" type="submit">Cari</button>
                                    <?php if ($productSearchQuery !== ''): ?>
                                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/produk'); ?>">Reset</a>
                                    <?php endif; ?>
                                </form>
                                <div class="admin-list-summary">
                                    Menampilkan <?= $productTotal > 0 ? (int)($productOffset + 1) : 0; ?>-<?= (int)min($productOffset + $productPerPage, $productTotal); ?> dari <?= (int)$productTotal; ?> item<?= $productSearchQuery !== '' ? ' untuk pencarian “' . esc($productSearchQuery) . '”' : ''; ?>.
                                </div>
                            </div>
                            <a class="admin-btn admin-btn--primary" href="<?= url('admin/produk?action=create'); ?>">+ Item Baru</a>
                            <form method="post" onsubmit="return confirm('Siapkan konten awal katalog agar bisa diedit dari dashboard? Item dengan slug yang sama akan dilewati.');" style="display:inline-flex">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="convert_seed">
                                <button class="admin-btn admin-btn--soft" type="submit">Siapkan Konten Awal</button>
                            </form>
                        </div>
                        <?php if (!$products): ?>
                            <p>Belum ada item aktif. Tambahkan item baru untuk mulai mengisi katalog bisnis.</p>
                        <?php elseif (!$productPageItems): ?>
                            <div class="admin-empty admin-empty--compact">
                                <h2>Item tidak ditemukan</h2>
                                <p>Coba kata kunci lain atau reset pencarian.</p>
                                <a class="admin-btn admin-btn--soft" href="<?= url('admin/produk'); ?>">Reset Filter</a>
                            </div>
                        <?php else: ?>
                            <div class="admin-product-list">
                                <?php foreach ($productPageItems as $product): ?>
                                    <article class="admin-product-row">
                                        <img src="<?= esc((string)($product['image'] ?? asset('images/placeholder-product.svg'))); ?>" alt="">
                                        <div>
                                            <h3><?= esc((string)($product['title'] ?? 'Item')); ?> <span class="admin-source-badge"><?= esc(product_source_label($product)); ?></span></h3>
                                            <p><?= esc(product_item_type_icon($product) . ' ' . product_item_type_label($product)); ?> · <?= esc((string)($product['category'] ?? '')); ?> · <?= esc((string)($product['breed'] ?? '')); ?> · <?= esc((string)($product['location'] ?? '')); ?></p>
                                            <strong><?= esc(product_price_label($product)); ?></strong>
                                            <?php if (product_is_digital($product)): ?><small class="admin-row-note">Akses: <?= esc(product_digital_access_mode_label($product)); ?></small><?php endif; ?>
                                            <?php if (!empty($product['shipping_origin_id']) && function_exists('shipping_origin_by_id')): ?>
                                                <?php $rowOrigin = shipping_origin_by_id((string)$product['shipping_origin_id']); ?>
                                                <?php if ($rowOrigin): ?><small class="admin-row-note">Asal kirim: <?= esc(shipping_origin_label($rowOrigin)); ?></small><?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (function_exists('commerce_policy_badges')): ?>
                                                <?php foreach (array_slice(commerce_policy_badges($product), 0, 3) as $badge): ?>
                                                    <small class="admin-row-note">Policy: <?= esc((string)$badge); ?></small>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <?php if (function_exists('inventory_product_summary')): ?>
                                                <?php $rowInventory = inventory_product_summary($product); ?>
                                                <small class="admin-row-note">Inventory: <?= esc((string)$rowInventory['status_label']); ?> · tersedia <?= (int)$rowInventory['available']; ?> · reserved <?= (int)$rowInventory['reserved']; ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="admin-row-actions">
                                            <a class="admin-btn admin-btn--soft" href="<?= product_url((string)$product['slug']); ?>" target="_blank">Lihat</a>
                                            <a class="admin-btn admin-btn--soft" href="<?= url('admin/produk?action=edit&id=' . (int)$product['id']); ?>">Edit</a>
                                            <form method="post" action="<?= url('admin/produk?action=delete&id=' . (int)$product['id']); ?>" onsubmit="return confirm('Hapus item ini?');">
                                                <?= csrf_field(); ?>
                                                <button class="admin-btn admin-btn--danger" type="submit">Hapus</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($productTotalPages > 1): ?>
                                <nav class="admin-pagination" aria-label="Pagination produk admin">
                                    <a class="admin-page-link <?= $productCurrentPage <= 1 ? 'is-disabled' : ''; ?>" href="<?= $productCurrentPage <= 1 ? '#' : admin_product_page_url($productCurrentPage - 1); ?>">‹ Prev</a>
                                    <?php for ($i = max(1, $productCurrentPage - 2); $i <= min($productTotalPages, $productCurrentPage + 2); $i++): ?>
                                        <a class="admin-page-link <?= $i === $productCurrentPage ? 'is-active' : ''; ?>" href="<?= admin_product_page_url($i); ?>"><?= (int)$i; ?></a>
                                    <?php endfor; ?>
                                    <a class="admin-page-link <?= $productCurrentPage >= $productTotalPages ? 'is-disabled' : ''; ?>" href="<?= $productCurrentPage >= $productTotalPages ? '#' : admin_product_page_url($productCurrentPage + 1); ?>">Next ›</a>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>


<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jodit@4.2.47/es2021/jodit.min.css">
<script src="https://cdn.jsdelivr.net/npm/jodit@4.2.47/es2021/jodit.min.js"></script>
<script>
(function(){
  const joditUploadUrl = <?= json_encode(url('admin/jodit-upload')); ?>;
  const joditCsrfName = <?= json_encode(CSRF_TOKEN_NAME); ?>;
  const joditCsrfToken = <?= json_encode(csrf_token()); ?>;

  const contentTextarea = document.getElementById('content');
  if (contentTextarea && typeof Jodit !== 'undefined') {
    const editor = Jodit.make(contentTextarea, {
      height: 420, language: 'id', toolbarAdaptive: false, toolbarSticky: true,
      askBeforePasteHTML: false, askBeforePasteFromWord: false, defaultActionOnPaste: 'insert_clear_html',
      uploader: {
                      url: joditUploadUrl,
                      method: 'POST',
                      format: 'json',
                      insertImageAsBase64URI: false,
                      filesVariableName: () => 'files',
                      prepareData: function (formData) {
                          formData.append(joditCsrfName, joditCsrfToken);
                          formData.append('editor_context', 'product');
                          return formData;
                      },
                      isSuccess: function (response) {
                          return !!(response && response.success === true);
                      },
                      getMessage: function (response) {
                          return response && response.message ? response.message : 'Upload gambar gagal.';
                      },
                      process: function (response) {
                          return response || {};
                      },
                      defaultHandlerSuccess: function (response) {
                          const files = Array.isArray(response.files) ? response.files : [];
                          files.forEach((file) => {
                              if (file && file.url) {
                                  this.selection.insertImage(file.url, file.title || null, 900);
                              }
                          });
                      }
                  },
      image: { editSrc: true, useImageEditor: false, openOnDblClick: true },
      buttons: ['source','|','paragraph','font','fontsize','|','bold','italic','underline','strikethrough','eraser','|','ul','ol','outdent','indent','|','left','center','right','justify','|','link','image','table','hr','quote','|','undo','redo','|','preview','fullsize'],
      placeholder: 'Tulis detail produk/jasa, keunggulan, info pengiriman/booking, dan catatan penting untuk customer...'
    });
    contentTextarea.closest('form')?.addEventListener('submit', () => { contentTextarea.value = editor.value; });
  }

  const itemTypeSelect = document.getElementById('item_type_key');
  const animalTypeInput = document.getElementById('animal_type_value');
  const typeInput = document.getElementById('type_value');
  const labels = {
    physical: 'Produk Fisik', service: 'Jasa / Layanan', digital: 'Produk Digital', course: 'E-course / Kelas Online', ebook: 'E-book / File Download', package: 'Paket / Bundle', menu: 'Menu Kuliner', booking: 'Booking / Reservasi', custom: 'Custom Order'
  };
  itemTypeSelect?.addEventListener('change', () => {
    const label = labels[itemTypeSelect.value] || 'Produk Fisik';
    if (animalTypeInput) animalTypeInput.value = label;
    if (typeInput) typeInput.value = label;
  });

})();
</script>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
