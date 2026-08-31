<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONTENT RESTRICTION ENGINE - ADVANCED BUYER ACCESS
|--------------------------------------------------------------------------
| Shared-hosting friendly access guard for articles, product pages, and
| landing pages. Rules live in JSON and can unlock content by buyer login,
| purchased product, product category, order/payment status, active
| subscription, and unexpired member access.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('content_restriction_storage_path')) {
    function content_restriction_storage_path(): string
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        return STORAGE_PATH . '/content-restrictions.json';
    }
}

if (!function_exists('content_restriction_default_rule')) {
    function content_restriction_default_rule(): array
    {
        return [
            'mode' => 'public',
            'required_product_slugs' => [],
            'required_product_categories' => [],
            'required_order_statuses' => [],
            'required_payment_statuses' => ['Lunas'],
            'required_subscription_slugs' => [],
            'require_unexpired_access' => true,
            'login_message' => 'Konten ini hanya bisa dibuka oleh pembeli yang memiliki akses aktif.',
            'product_required_message' => 'Akses konten ini membutuhkan pembelian produk yang sesuai.',
            'category_required_message' => 'Akses konten ini membutuhkan pembelian produk dari kategori yang sesuai.',
            'order_status_message' => 'Akses konten ini akan terbuka setelah status order/pembayaran memenuhi syarat.',
            'subscription_message' => 'Konten ini membutuhkan subscription atau membership aktif.',
            'expired_message' => 'Masa akses Anda sudah berakhir. Hubungi admin untuk perpanjangan atau cek opsi renewal.',
            'updated_at' => '',
        ];
    }
}

if (!function_exists('content_restriction_clean')) {
    function content_restriction_clean(string $value, int $max = 220): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }
        return substr($value, 0, $max);
    }
}

if (!function_exists('content_restriction_textarea_items')) {
    function content_restriction_textarea_items(mixed $value, bool $slugifyItems = true): array
    {
        if (is_string($value)) {
            $parts = preg_split('/[\r\n,;]+/', $value) ?: [];
        } elseif (is_array($value)) {
            $parts = $value;
        } else {
            $parts = [];
        }

        $items = [];
        foreach ($parts as $part) {
            $raw = content_restriction_clean((string)$part, 120);
            $item = $slugifyItems ? slugify($raw) : $raw;
            if ($item !== '') {
                $items[$item] = true;
            }
        }
        return array_keys($items);
    }
}

if (!function_exists('content_restriction_slugs_from_mixed')) {
    function content_restriction_slugs_from_mixed(mixed $value): array
    {
        return content_restriction_textarea_items($value, true);
    }
}

if (!function_exists('content_restriction_modes')) {
    function content_restriction_modes(): array
    {
        return [
            'public' => 'Publik',
            'buyer' => 'Hanya buyer/member login',
            'active_access' => 'Hanya buyer dengan masa akses aktif',
            'purchased_product' => 'Hanya pembeli produk tertentu',
            'product_category' => 'Hanya pembeli kategori produk tertentu',
            'order_status' => 'Hanya order/status pembayaran tertentu',
            'subscription_active' => 'Hanya subscription aktif',
        ];
    }
}

if (!function_exists('content_restriction_normalize_rule')) {
    function content_restriction_normalize_rule(array $rule): array
    {
        $mode = content_restriction_clean((string)($rule['mode'] ?? 'public'), 40);
        if (!array_key_exists($mode, content_restriction_modes())) {
            $mode = 'public';
        }

        $normalized = content_restriction_default_rule();
        $normalized['mode'] = $mode;
        $normalized['required_product_slugs'] = content_restriction_slugs_from_mixed($rule['required_product_slugs'] ?? $rule['required_products'] ?? []);
        $normalized['required_product_categories'] = content_restriction_textarea_items($rule['required_product_categories'] ?? $rule['required_categories'] ?? [], true);
        $normalized['required_order_statuses'] = content_restriction_textarea_items($rule['required_order_statuses'] ?? [], false);
        $paymentStatuses = content_restriction_textarea_items($rule['required_payment_statuses'] ?? $normalized['required_payment_statuses'], false);
        $normalized['required_payment_statuses'] = $paymentStatuses ?: $normalized['required_payment_statuses'];
        $normalized['required_subscription_slugs'] = content_restriction_slugs_from_mixed($rule['required_subscription_slugs'] ?? $rule['required_product_slugs'] ?? []);
        $normalized['require_unexpired_access'] = array_key_exists('require_unexpired_access', $rule) ? !empty($rule['require_unexpired_access']) : true;
        foreach (['login_message', 'product_required_message', 'category_required_message', 'order_status_message', 'subscription_message', 'expired_message'] as $field) {
            $normalized[$field] = content_restriction_clean((string)($rule[$field] ?? $normalized[$field]), 320);
        }
        $normalized['updated_at'] = content_restriction_clean((string)($rule['updated_at'] ?? ''), 80);
        return $normalized;
    }
}

if (!function_exists('content_restriction_read_all')) {
    function content_restriction_read_all(): array
    {
        $path = content_restriction_storage_path();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('content_restriction_write_all')) {
    function content_restriction_write_all(array $rules): bool
    {
        return (bool)file_put_contents(
            content_restriction_storage_path(),
            json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }
}

if (!function_exists('content_restriction_keys_for')) {
    function content_restriction_keys_for(string $type, array|string $itemOrIdSlug): array
    {
        $type = slugify($type);
        $id = '';
        $slug = '';

        if (is_array($itemOrIdSlug)) {
            $id = content_restriction_clean((string)($itemOrIdSlug['id'] ?? ''), 100);
            $slug = slugify((string)($itemOrIdSlug['slug'] ?? ''));
        } else {
            $raw = trim((string)$itemOrIdSlug);
            if (preg_match('/^[0-9]+$/', $raw)) {
                $id = $raw;
            } else {
                $slug = slugify($raw);
            }
        }

        $keys = [];
        if ($id !== '') {
            $keys[] = $type . ':id:' . $id;
        }
        if ($slug !== '') {
            $keys[] = $type . ':slug:' . $slug;
        }
        return $keys;
    }
}

if (!function_exists('content_restriction_rule_for')) {
    function content_restriction_rule_for(string $type, array|string $itemOrIdSlug): array
    {
        $rules = content_restriction_read_all();
        foreach (content_restriction_keys_for($type, $itemOrIdSlug) as $key) {
            if (is_array($rules[$key] ?? null)) {
                return content_restriction_normalize_rule($rules[$key]);
            }
        }
        if (is_array($itemOrIdSlug) && is_array($itemOrIdSlug['access_rule'] ?? null)) {
            return content_restriction_normalize_rule($itemOrIdSlug['access_rule']);
        }
        return content_restriction_default_rule();
    }
}

if (!function_exists('content_restriction_rule_from_post')) {
    function content_restriction_rule_from_post(array $post): array
    {
        return content_restriction_normalize_rule([
            'mode' => (string)($post['access_mode'] ?? 'public'),
            'required_product_slugs' => (string)($post['required_product_slugs'] ?? ''),
            'required_product_categories' => (string)($post['required_product_categories'] ?? ''),
            'required_order_statuses' => (string)($post['required_order_statuses'] ?? ''),
            'required_payment_statuses' => (string)($post['required_payment_statuses'] ?? ''),
            'required_subscription_slugs' => (string)($post['required_subscription_slugs'] ?? ''),
            'require_unexpired_access' => !empty($post['require_unexpired_access']),
            'login_message' => (string)($post['access_login_message'] ?? ''),
            'product_required_message' => (string)($post['access_product_required_message'] ?? ''),
            'category_required_message' => (string)($post['access_category_required_message'] ?? ''),
            'order_status_message' => (string)($post['access_order_status_message'] ?? ''),
            'subscription_message' => (string)($post['access_subscription_message'] ?? ''),
            'expired_message' => (string)($post['access_expired_message'] ?? ''),
            'updated_at' => date('c'),
        ]);
    }
}

if (!function_exists('content_restriction_save_for')) {
    function content_restriction_save_for(string $type, array|string $itemOrIdSlug, array $rule): bool
    {
        $rules = content_restriction_read_all();
        $normalized = content_restriction_normalize_rule($rule);
        $keys = content_restriction_keys_for($type, $itemOrIdSlug);
        if (!$keys) {
            return false;
        }

        foreach ($keys as $key) {
            if ($normalized['mode'] === 'public') {
                unset($rules[$key]);
            } else {
                $rules[$key] = $normalized;
            }
        }
        return content_restriction_write_all($rules);
    }
}

if (!function_exists('content_restriction_buyer_records')) {
    function content_restriction_buyer_records(): array
    {
        $current = function_exists('buyer_account_current') ? buyer_account_current() : null;
        if (!$current || !function_exists('buyer_account_records')) {
            return [];
        }
        return buyer_account_records($current);
    }
}

if (!function_exists('content_restriction_record_active')) {
    function content_restriction_record_active(array $record): bool
    {
        if ((string)($record['status'] ?? 'active') !== 'active') {
            return false;
        }
        if (function_exists('member_access_record_is_expired') && member_access_record_is_expired($record)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('content_restriction_product_for_record')) {
    function content_restriction_product_for_record(array $record): ?array
    {
        $slug = slugify((string)($record['product_slug'] ?? ''));
        if ($slug === '' || !function_exists('get_product_by_slug')) {
            return null;
        }
        $product = get_product_by_slug($slug);
        return is_array($product) ? $product : null;
    }
}

if (!function_exists('content_restriction_order_for_record')) {
    function content_restriction_order_for_record(array $record): ?array
    {
        $orderId = content_restriction_clean((string)($record['order_id'] ?? ''), 120);
        if ($orderId === '' || !function_exists('order_find_by_id')) {
            return null;
        }
        $order = order_find_by_id($orderId);
        return is_array($order) ? $order : null;
    }
}

if (!function_exists('content_restriction_record_matches_product')) {
    function content_restriction_record_matches_product(array $record, array $required): bool
    {
        $slug = slugify((string)($record['product_slug'] ?? ''));
        if (!$required) {
            return $slug !== '';
        }
        $requiredMap = array_flip(array_map('slugify', $required));
        return $slug !== '' && isset($requiredMap[$slug]);
    }
}

if (!function_exists('content_restriction_record_matches_category')) {
    function content_restriction_record_matches_category(array $record, array $requiredCategories): bool
    {
        $product = content_restriction_product_for_record($record);
        $category = slugify((string)($product['category'] ?? $record['product_category'] ?? ''));
        if (!$requiredCategories) {
            return $category !== '';
        }
        $requiredMap = array_flip(array_map('slugify', $requiredCategories));
        return $category !== '' && isset($requiredMap[$category]);
    }
}

if (!function_exists('content_restriction_record_matches_order_status')) {
    function content_restriction_record_matches_order_status(array $record, array $rule): bool
    {
        $order = content_restriction_order_for_record($record);
        if (!$order) {
            return false;
        }
        $requiredOrderStatuses = array_map('strtolower', (array)($rule['required_order_statuses'] ?? []));
        $requiredPaymentStatuses = array_map('strtolower', (array)($rule['required_payment_statuses'] ?? ['Lunas']));
        $orderStatus = strtolower(content_restriction_clean((string)($order['status'] ?? ''), 80));
        $paymentStatus = strtolower(content_restriction_clean((string)($order['payment_status'] ?? ''), 80));
        $orderOk = !$requiredOrderStatuses || in_array($orderStatus, $requiredOrderStatuses, true);
        $paymentOk = !$requiredPaymentStatuses || in_array($paymentStatus, $requiredPaymentStatuses, true);
        return $orderOk && $paymentOk;
    }
}

if (!function_exists('content_restriction_subscription_records')) {
    function content_restriction_subscription_records(): array
    {
        $current = function_exists('buyer_account_current') ? buyer_account_current() : null;
        $email = strtolower((string)($current['email'] ?? ''));
        if ($email === '' || !function_exists('subscription_records_by_email')) {
            return [];
        }
        return subscription_records_by_email($email);
    }
}

if (!function_exists('content_restriction_subscription_active')) {
    function content_restriction_subscription_active(array $rule): bool
    {
        $requiredSlugs = array_map('slugify', (array)($rule['required_subscription_slugs'] ?? []));
        $requiredMap = array_flip($requiredSlugs);
        foreach (content_restriction_subscription_records() as $record) {
            if (!is_array($record)) {
                continue;
            }
            $status = function_exists('subscription_status') ? subscription_status($record) : (string)($record['status'] ?? 'active');
            if (!in_array($status, ['active', 'lifetime', 'grace'], true)) {
                continue;
            }
            $slug = slugify((string)($record['product_slug'] ?? ''));
            if (!$requiredSlugs || ($slug !== '' && isset($requiredMap[$slug]))) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('content_restriction_allowed')) {
    function content_restriction_allowed(string $type, array $item): array
    {
        $rule = content_restriction_rule_for($type, $item);
        $mode = (string)($rule['mode'] ?? 'public');
        if ($mode === 'public') {
            return ['allowed' => true, 'restricted' => false, 'rule' => $rule, 'records' => []];
        }

        $allRecords = array_values(array_filter(content_restriction_buyer_records(), 'is_array'));
        if (!$allRecords) {
            return ['allowed' => false, 'restricted' => true, 'rule' => $rule, 'records' => [], 'reason' => 'login_required'];
        }

        $records = !empty($rule['require_unexpired_access'])
            ? array_values(array_filter($allRecords, 'content_restriction_record_active'))
            : $allRecords;
        if (!$records) {
            return ['allowed' => false, 'restricted' => true, 'rule' => $rule, 'records' => $allRecords, 'reason' => 'access_expired'];
        }

        if ($mode === 'buyer' || $mode === 'active_access') {
            return ['allowed' => true, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'reason' => 'allowed'];
        }

        if ($mode === 'purchased_product') {
            $required = (array)($rule['required_product_slugs'] ?? []);
            if (!$required && !empty($item['slug'])) {
                $required = [slugify((string)$item['slug'])];
            }
            foreach ($records as $record) {
                if (content_restriction_record_matches_product($record, $required)) {
                    return ['allowed' => true, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'matched_product' => slugify((string)($record['product_slug'] ?? ''))];
                }
            }
            return ['allowed' => false, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'reason' => 'product_required'];
        }

        if ($mode === 'product_category') {
            $required = (array)($rule['required_product_categories'] ?? []);
            foreach ($records as $record) {
                if (content_restriction_record_matches_category($record, $required)) {
                    return ['allowed' => true, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'matched_category' => true];
                }
            }
            return ['allowed' => false, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'reason' => 'category_required'];
        }

        if ($mode === 'order_status') {
            foreach ($records as $record) {
                if (content_restriction_record_matches_order_status($record, $rule)) {
                    return ['allowed' => true, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'matched_order_status' => true];
                }
            }
            return ['allowed' => false, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'reason' => 'order_status_required'];
        }

        if ($mode === 'subscription_active') {
            if (content_restriction_subscription_active($rule)) {
                return ['allowed' => true, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'matched_subscription' => true];
            }
            return ['allowed' => false, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'reason' => 'subscription_required'];
        }

        return ['allowed' => false, 'restricted' => true, 'rule' => $rule, 'records' => $records, 'reason' => 'unknown_rule'];
    }
}

if (!function_exists('content_restriction_mode_label')) {
    function content_restriction_mode_label(string $mode): string
    {
        return content_restriction_modes()[$mode] ?? 'Publik';
    }
}

if (!function_exists('content_restriction_admin_fields')) {
    function content_restriction_admin_fields(string $type, array $item = []): string
    {
        $rule = content_restriction_rule_for($type, $item);
        $mode = (string)($rule['mode'] ?? 'public');
        $slugs = implode("\n", (array)($rule['required_product_slugs'] ?? []));
        $categories = implode("\n", (array)($rule['required_product_categories'] ?? []));
        $orderStatuses = implode("\n", (array)($rule['required_order_statuses'] ?? []));
        $paymentStatuses = implode("\n", (array)($rule['required_payment_statuses'] ?? []));
        $subscriptionSlugs = implode("\n", (array)($rule['required_subscription_slugs'] ?? []));
        ob_start();
        ?>
        <div class="admin-card admin-nested-card admin-content-restriction-card">
            <h3>Restriction / Akses Konten</h3>
            <p class="admin-help-text">Atur apakah konten ini publik atau hanya bisa dibuka oleh buyer/member tertentu. Cocok untuk artikel premium, bonus pembeli, LP khusus member, atau materi produk digital.</p>
            <label>Mode akses
                <select name="access_mode">
                    <?php foreach (content_restriction_modes() as $value => $label): ?>
                        <option value="<?= esc($value); ?>" <?= $mode === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="admin-check"><input type="checkbox" name="require_unexpired_access" value="1" <?= !empty($rule['require_unexpired_access']) ? 'checked' : ''; ?>> Wajib masa akses member masih aktif / belum kedaluwarsa</label>
            <label>Slug produk pembuka akses
                <textarea name="required_product_slugs" rows="3" placeholder="produk-a&#10;produk-b"><?= esc($slugs); ?></textarea>
                <small>Dipakai untuk mode “Hanya pembeli produk tertentu”. Kosongkan untuk memakai slug konten/produk saat ini jika relevan.</small>
            </label>
            <label>Kategori produk pembuka akses
                <textarea name="required_product_categories" rows="3" placeholder="produk-digital&#10;e-course"><?= esc($categories); ?></textarea>
                <small>Dipakai untuk mode “Hanya pembeli kategori produk tertentu”. Isi satu kategori per baris.</small>
            </label>
            <label>Status order yang diizinkan
                <textarea name="required_order_statuses" rows="2" placeholder="Diproses&#10;Selesai"><?= esc($orderStatuses); ?></textarea>
                <small>Opsional. Dipakai untuk mode status order. Kosongkan jika cukup pakai status pembayaran.</small>
            </label>
            <label>Status pembayaran yang diizinkan
                <textarea name="required_payment_statuses" rows="2" placeholder="Lunas&#10;DP Masuk"><?= esc($paymentStatuses); ?></textarea>
                <small>Default: Lunas. Dipakai untuk mode status order/pembayaran.</small>
            </label>
            <label>Slug produk subscription yang diizinkan
                <textarea name="required_subscription_slugs" rows="3" placeholder="membership-pro&#10;kelas-premium"><?= esc($subscriptionSlugs); ?></textarea>
                <small>Dipakai untuk mode “Hanya subscription aktif”. Kosongkan agar semua subscription aktif bisa membuka akses.</small>
            </label>
            <details class="admin-help-details" open>
                <summary>Pesan akses terkunci</summary>
                <label>Pesan login/member
                    <textarea name="access_login_message" rows="2"><?= esc((string)($rule['login_message'] ?? '')); ?></textarea>
                </label>
                <label>Pesan wajib produk tertentu
                    <textarea name="access_product_required_message" rows="2"><?= esc((string)($rule['product_required_message'] ?? '')); ?></textarea>
                </label>
                <label>Pesan wajib kategori produk
                    <textarea name="access_category_required_message" rows="2"><?= esc((string)($rule['category_required_message'] ?? '')); ?></textarea>
                </label>
                <label>Pesan status order/pembayaran belum sesuai
                    <textarea name="access_order_status_message" rows="2"><?= esc((string)($rule['order_status_message'] ?? '')); ?></textarea>
                </label>
                <label>Pesan subscription belum aktif
                    <textarea name="access_subscription_message" rows="2"><?= esc((string)($rule['subscription_message'] ?? '')); ?></textarea>
                </label>
                <label>Pesan masa akses kedaluwarsa
                    <textarea name="access_expired_message" rows="2"><?= esc((string)($rule['expired_message'] ?? '')); ?></textarea>
                </label>
            </details>
        </div>
        <?php
        return (string)ob_get_clean();
    }
}

if (!function_exists('content_restriction_reason_message')) {
    function content_restriction_reason_message(array $status): string
    {
        $rule = is_array($status['rule'] ?? null) ? $status['rule'] : content_restriction_default_rule();
        return match ((string)($status['reason'] ?? 'login_required')) {
            'access_expired' => (string)($rule['expired_message'] ?? ''),
            'product_required' => (string)($rule['product_required_message'] ?? ''),
            'category_required' => (string)($rule['category_required_message'] ?? ''),
            'order_status_required' => (string)($rule['order_status_message'] ?? ''),
            'subscription_required' => (string)($rule['subscription_message'] ?? ''),
            default => (string)($rule['login_message'] ?? ''),
        } ?: 'Konten ini membutuhkan akses pembeli yang sesuai.';
    }
}

if (!function_exists('content_restriction_requirement_lines')) {
    function content_restriction_requirement_lines(array $rule): array
    {
        $lines = [];
        if (!empty($rule['required_product_slugs'])) {
            $lines[] = 'Produk: ' . implode(', ', (array)$rule['required_product_slugs']);
        }
        if (!empty($rule['required_product_categories'])) {
            $lines[] = 'Kategori produk: ' . implode(', ', (array)$rule['required_product_categories']);
        }
        if (!empty($rule['required_order_statuses'])) {
            $lines[] = 'Status order: ' . implode(', ', (array)$rule['required_order_statuses']);
        }
        if (!empty($rule['required_payment_statuses']) && in_array((string)($rule['mode'] ?? ''), ['order_status'], true)) {
            $lines[] = 'Status pembayaran: ' . implode(', ', (array)$rule['required_payment_statuses']);
        }
        if (!empty($rule['required_subscription_slugs'])) {
            $lines[] = 'Subscription: ' . implode(', ', (array)$rule['required_subscription_slugs']);
        }
        if (!empty($rule['require_unexpired_access']) && (string)($rule['mode'] ?? '') !== 'public') {
            $lines[] = 'Masa akses: masih aktif';
        }
        return $lines;
    }
}

if (!function_exists('content_restriction_render_gate')) {
    function content_restriction_render_gate(array $status, string $title = 'Konten Khusus Pembeli'): void
    {
        if (!empty($status['allowed'])) {
            return;
        }
        $rule = is_array($status['rule'] ?? null) ? $status['rule'] : content_restriction_default_rule();
        $message = content_restriction_reason_message($status);
        $modeLabel = content_restriction_mode_label((string)($rule['mode'] ?? 'public'));
        $currentUrl = current_url();
        $loginUrl = url('member-area?' . http_build_query(['message' => 'Silakan login untuk membuka konten khusus pembeli.', 'next' => $currentUrl]));
        $requirements = content_restriction_requirement_lines($rule);
        ?>
        <section class="section content-restriction-gate">
            <div class="container">
                <article class="member-area-card--template content-restriction-card" style="max-width:860px;margin:0 auto;background:#fff;border:1px solid #dbe7e2;border-radius:30px;box-shadow:0 22px 70px rgba(15,23,42,.07);padding:1.6rem">
                    <span class="dynamic-mini-label"><?= esc($modeLabel); ?></span>
                    <h1><?= esc($title); ?></h1>
                    <p><?= esc($message); ?></p>
                    <?php if ($requirements): ?>
                        <div class="member-area-card--template" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:20px;padding:1rem;margin-top:1rem">
                            <strong>Syarat akses:</strong>
                            <ul style="margin:.55rem 0 0 1.1rem">
                                <?php foreach ($requirements as $line): ?>
                                    <li><?= esc($line); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <div class="member-actions--template" style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem">
                        <a class="cta" href="<?= esc($loginUrl); ?>" rel="nofollow">Login Member Area</a>
                        <a class="cta secondary" href="<?= esc(url('member-area')); ?>" rel="nofollow">Cek Akses Pembelian</a>
                        <a class="cta secondary" href="<?= esc(wa_link('Halo Admin, saya ingin cek akses konten khusus pembeli.')); ?>" target="_blank" rel="nofollow noopener">Hubungi Admin</a>
                    </div>
                    <p class="member-muted--template" style="margin-top:.9rem">Sudah membeli? Gunakan email pembelian atau link akses dari invoice/status order.</p>
                </article>
            </div>
        </section>
        <?php
    }
}
