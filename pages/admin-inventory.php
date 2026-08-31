<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();
$GLOBALS['admin_page'] = true;

$range = max(0, min(3650, (int)($_GET['range'] ?? 0)));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$q = strtolower(trim((string)($_GET['q'] ?? '')));
$dashboard = function_exists('inventory_dashboard') ? inventory_dashboard() : ['stats' => [], 'rows' => [], 'actions' => []];
$stats = (array)($dashboard['stats'] ?? []);
$rows = (array)($dashboard['rows'] ?? []);

if ($statusFilter !== '') {
    $rows = array_values(array_filter($rows, static fn(array $row): bool => (string)($row['summary']['status_key'] ?? '') === $statusFilter));
}
if ($q !== '') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($q): bool {
        $product = (array)($row['product'] ?? []);
        $haystack = strtolower(implode(' ', array_map('strval', [
            $product['title'] ?? '',
            $product['sku'] ?? '',
            $product['slug'] ?? '',
            $product['category'] ?? '',
            $product['location'] ?? '',
            $product['stock_note'] ?? '',
        ])));
        return str_contains($haystack, $q);
    }));
}

set_seo([
    'title' => 'Stock, Inventory & Product Availability - ' . SITE_NAME,
    'description' => 'Kontrol stok, reserved stock, pre-order, low stock alert, dan ketersediaan produk untuk UMKM.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-inventory-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Commerce Operations</div>
                <h1>Stock, Inventory & Product Availability Control</h1>
                <p>Kontrol stok/kuota, reserved order, produk low stock, pre-order, dan ketersediaan supaya UMKM tidak over-selling.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/produk')); ?>">Edit Produk</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/orders')); ?>">Lihat Order</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <div class="admin-grid admin-grid--stats">
                <div class="admin-card"><span class="admin-badge">Produk</span><h2><?= (int)($stats['total'] ?? 0); ?></h2><p>Total item aktif/dinamis yang terbaca dashboard.</p></div>
                <div class="admin-card"><span class="admin-badge">Dilacak</span><h2><?= (int)($stats['tracked'] ?? 0); ?></h2><p>Produk dengan kontrol stok/kuota aktif.</p></div>
                <div class="admin-card"><span class="admin-badge">Stok Aman</span><h2><?= (int)($stats['ok'] ?? 0); ?></h2><p>Masih aman untuk dijual.</p></div>
                <div class="admin-card"><span class="admin-badge">Menipis</span><h2><?= (int)($stats['low'] ?? 0); ?></h2><p>Butuh restock atau pre-order.</p></div>
                <div class="admin-card"><span class="admin-badge">Habis</span><h2><?= (int)($stats['out'] ?? 0); ?></h2><p>Rawan over-selling jika checkout tetap aktif.</p></div>
                <div class="admin-card"><span class="admin-badge">Reserved</span><h2><?= (int)($stats['reserved'] ?? 0); ?></h2><p>Qty tertahan oleh order belum closing/belum bayar.</p></div>
            </div>

            <div class="admin-grid admin-grid--two">
                <div class="admin-card admin-editor">
                    <div class="admin-form-head"><span class="admin-badge">Action Plan</span><h2>Yang Perlu Dikerjakan Admin</h2><p>Prioritas harian agar stok, order, dan checkout tetap aman.</p></div>
                    <?php if (empty($dashboard['actions'])): ?>
                        <div class="admin-empty admin-empty--compact"><h2>Stok terlihat aman</h2><p>Belum ada produk yang perlu perhatian khusus.</p></div>
                    <?php else: ?>
                        <ol class="admin-action-list">
                            <?php foreach ((array)$dashboard['actions'] as $action): ?>
                                <li><?= esc((string)$action); ?></li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
                <div class="admin-card admin-editor">
                    <div class="admin-form-head"><span class="admin-badge">Cara Kerja</span><h2>Stock Flow Ringan</h2><p>Shared-hosting friendly, tanpa cron berat dan tanpa wajib database khusus.</p></div>
                    <div class="admin-foundation-list">
                        <div><strong>Stok Total</strong><span>Diambil dari field Stok/Kuota produk.</span></div>
                        <div><strong>Reserved</strong><span>Order baru/menunggu pembayaran menahan stok sementara.</span></div>
                        <div><strong>Committed</strong><span>Order DP Masuk/Lunas/Deal dianggap sudah memakai stok.</span></div>
                        <div><strong>Available</strong><span>Stok total - reserved - committed.</span></div>
                        <div><strong>Pre-order</strong><span>Produk bisa tetap dijual walau stok habis jika backorder/pre-order diizinkan.</span></div>
                    </div>
                </div>
            </div>

            <div class="admin-card admin-editor">
                <div class="admin-form-head admin-form-head--split">
                    <div><span class="admin-badge">Inventory Table</span><h2>Daftar Stok & Ketersediaan</h2><p>Filter produk berdasarkan status stok, lalu edit langsung di katalog.</p></div>
                    <form method="get" class="admin-list-search">
                        <input type="search" name="q" value="<?= esc((string)($_GET['q'] ?? '')); ?>" placeholder="Cari produk, SKU, slug...">
                        <select name="status" aria-label="Filter status stok">
                            <option value="">Semua status</option>
                            <?php foreach (['out' => 'Habis', 'low' => 'Menipis', 'preorder' => 'Pre-order', 'ok' => 'Aman', 'untracked' => 'Tidak Dilacak'] as $key => $label): ?>
                                <option value="<?= esc($key); ?>" <?= $statusFilter === $key ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="admin-btn admin-btn--primary" type="submit">Filter</button>
                        <?php if ($statusFilter !== '' || $q !== ''): ?><a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/inventory')); ?>">Reset</a><?php endif; ?>
                    </form>
                </div>

                <?php if (!$rows): ?>
                    <div class="admin-empty admin-empty--compact"><h2>Tidak ada item</h2><p>Belum ada produk yang cocok dengan filter ini.</p></div>
                <?php else: ?>
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Produk</th><th>Status</th><th>Stok</th><th>Reserved</th><th>Committed</th><th>Tersedia</th><th>Order Aktif</th><th>Aksi</th></tr></thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <?php $product = (array)($row['product'] ?? []); $summary = (array)($row['summary'] ?? []); ?>
                                    <tr>
                                        <td>
                                            <strong><?= esc((string)($product['title'] ?? 'Produk')); ?></strong><br>
                                            <small><?= esc((string)($product['sku'] ?? 'Tanpa SKU')); ?> · <?= esc((string)($product['slug'] ?? '')); ?></small>
                                            <?php if (!empty($summary['note'])): ?><br><small><?= esc((string)$summary['note']); ?></small><?php endif; ?>
                                        </td>
                                        <td><span class="<?= esc(function_exists('inventory_status_badge_class') ? inventory_status_badge_class((string)($summary['status_key'] ?? 'ok')) : 'admin-badge'); ?>"><?= esc((string)($summary['status_label'] ?? 'Aman')); ?></span></td>
                                        <td><?= (int)($summary['stock_total'] ?? 0); ?></td>
                                        <td><?= (int)($summary['reserved'] ?? 0); ?></td>
                                        <td><?= (int)($summary['committed'] ?? 0); ?></td>
                                        <td><strong><?= (int)($summary['available'] ?? 0); ?></strong></td>
                                        <td><?= (int)($summary['open_orders'] ?? 0); ?> pending · <?= (int)($summary['paid_orders'] ?? 0); ?> paid</td>
                                        <td>
                                            <div class="admin-row-actions">
                                                <?php if (!empty($product['id'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/produk?action=edit&id=' . (int)$product['id'])); ?>">Edit Stok</a><?php endif; ?>
                                                <?php if (!empty($product['slug'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc(url('checkout?produk=' . rawurlencode((string)$product['slug']))); ?>" target="_blank" rel="noopener">Tes Checkout</a><?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
