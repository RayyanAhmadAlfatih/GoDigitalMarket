<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| CHECKOUT COMPLETION & FIELD MANAGER - Template
|--------------------------------------------------------------------------
| File-based checkout settings for shared-hosting friendly UMKM commerce.
| This module keeps checkout editable from admin without requiring database.
|--------------------------------------------------------------------------
*/

if (!function_exists('checkout_settings_file')) {
    function checkout_settings_file(): string
    {
        return STORAGE_PATH . '/checkout-settings.json';
    }
}

if (!function_exists('checkout_clean')) {
    function checkout_clean(string $value, int $max = 180): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('checkout_multiline_clean')) {
    function checkout_multiline_clean(string $value, int $max = 3000): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace("/\r\n|\r/", "\n", (string)$value);
        $value = preg_replace('/[ \t]+/', ' ', (string)$value);
        $value = preg_replace('/\n{4,}/', "\n\n\n", (string)$value);
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('checkout_bool')) {
    function checkout_bool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        $raw = strtolower(trim((string)$value));
        if ($raw === '') {
            return $default;
        }
        return in_array($raw, ['1', 'true', 'on', 'yes', 'aktif', 'enabled'], true);
    }
}

if (!function_exists('checkout_lines_to_array')) {
    function checkout_lines_to_array(string|array $value, int $limit = 30, int $max = 100): array
    {
        $items = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string)$value);
        $items = array_map(static fn($item): string => checkout_clean((string)$item, $max), (array)$items);
        $items = array_values(array_unique(array_filter($items, static fn(string $item): bool => $item !== '')));
        return array_slice($items, 0, max(1, $limit));
    }
}

if (!function_exists('checkout_default_settings')) {
    function checkout_default_settings(): array
    {
        return [
            'enabled' => true,
            'headline' => 'Lengkapi Data Pemesanan',
            'intro' => 'Isi data awal agar admin bisa membantu cek stok, jadwal, alamat pengiriman, invoice, dan langkah berikutnya.',
            'summary_note' => 'Data ini belum pembayaran otomatis. Admin akan menghubungi Anda untuk konfirmasi akhir.',
            'button_label' => 'Kirim Data Pemesanan',
            'success_message' => 'Terima kasih, data pemesanan sudah masuk. Simpan nomor order Anda dan lanjutkan konfirmasi dengan admin bila diperlukan.',
            'consent_text' => 'Saya bersedia dihubungi admin melalui WhatsApp/telepon/email terkait pemesanan ini.',
            'email_required' => false,
            'quantity_enabled' => true,
            'planned_date_enabled' => true,
            'need_enabled' => true,
            'location_enabled' => true,
            'payment_method_enabled' => true,
            'notes_enabled' => true,
            'address_enabled' => true,
            'address_required' => false,
            'province_enabled' => true,
            'city_enabled' => true,
            'district_enabled' => true,
            'postal_code_enabled' => true,
            'shipping_method_enabled' => true,
            'shipping_method_required' => false,
            'need_options' => [
                'Booking Produk Ini',
                'Tanya Stok & Harga Terbaru',
                'Minta Video / Foto Terbaru',
                'Survey Area Layanan',
                'Kirim ke Lokasi Saya',
                'Paket Produk Keluarga',
                'Paket Layanan',
                'Paket komunitas / Perusahaan',
            ],
            'location_options' => [
                'Jakarta Selatan',
                'Tangerang Selatan',
                'Depok',
                'Bekasi',
                'Bandung',
                'Surabaya',
                'Bali',
            ],
            'shipping_method_options' => [
                'Konfirmasi Ongkir Dulu',
                'Kirim Kurir / Ekspedisi',
                'Ambil di Tempat',
                'COD / Bayar di Tempat',
                'Digital / Tidak Perlu Pengiriman',
            ],
            'admin_message_template' => "Order baru masuk dari checkout.\n\nNo. Order: {order_ref}\nNama: {name}\nWhatsApp: {phone}\nProduk/Layanan: {product}\nJumlah: {quantity}\nAlamat: {shipping_address}\nPengiriman: {shipping_method}\nOngkir: {shipping_cost}\nEstimasi sampai: {shipping_eta}\nPembayaran: {payment_method}\nTotal estimasi: {invoice_total}\nCatatan: {message}",
            'customer_message_template' => "Halo {name}, terima kasih. Data pemesanan Anda sudah kami terima.\n\nNo. Order: {order_ref}\nProduk/Layanan: {product}\nJumlah: {quantity}\nPengiriman: {shipping_method}\nOngkir: {shipping_cost}\nTotal estimasi: {invoice_total}\n\nAdmin akan membantu konfirmasi stok, ongkir, invoice, dan langkah berikutnya.",
            'updated_at' => '',
        ];
    }
}

if (!function_exists('checkout_settings_normalize')) {
    function checkout_settings_normalize(array $settings): array
    {
        $defaults = checkout_default_settings();
        $settings = array_merge($defaults, $settings);

        foreach ([
            'enabled', 'email_required', 'quantity_enabled', 'planned_date_enabled', 'need_enabled',
            'location_enabled', 'payment_method_enabled', 'notes_enabled', 'address_enabled',
            'address_required', 'province_enabled', 'city_enabled', 'district_enabled',
            'postal_code_enabled', 'shipping_method_enabled', 'shipping_method_required',
        ] as $key) {
            $settings[$key] = checkout_bool($settings[$key] ?? $defaults[$key], (bool)$defaults[$key]);
        }

        foreach (['headline' => 140, 'button_label' => 80, 'consent_text' => 260] as $key => $max) {
            $settings[$key] = checkout_clean((string)($settings[$key] ?? $defaults[$key]), $max) ?: $defaults[$key];
        }

        foreach (['intro' => 600, 'summary_note' => 500, 'success_message' => 700, 'admin_message_template' => 2500, 'customer_message_template' => 2500] as $key => $max) {
            $settings[$key] = checkout_multiline_clean((string)($settings[$key] ?? $defaults[$key]), $max) ?: $defaults[$key];
        }

        $settings['need_options'] = checkout_lines_to_array($settings['need_options'] ?? $defaults['need_options'], 40, 120) ?: $defaults['need_options'];
        $settings['location_options'] = checkout_lines_to_array($settings['location_options'] ?? $defaults['location_options'], 80, 120) ?: $defaults['location_options'];
        $settings['shipping_method_options'] = checkout_lines_to_array($settings['shipping_method_options'] ?? $defaults['shipping_method_options'], 30, 120) ?: $defaults['shipping_method_options'];
        $settings['updated_at'] = checkout_clean((string)($settings['updated_at'] ?? ''), 40);

        return $settings;
    }
}

if (!function_exists('checkout_settings')) {
    function checkout_settings(): array
    {
        $file = checkout_settings_file();
        if (!is_file($file)) {
            return checkout_settings_normalize([]);
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return checkout_settings_normalize(is_array($data) ? $data : []);
    }
}

if (!function_exists('checkout_settings_from_post')) {
    function checkout_settings_from_post(array $post): array
    {
        $current = checkout_settings();
        $checkboxes = [
            'enabled', 'email_required', 'quantity_enabled', 'planned_date_enabled', 'need_enabled',
            'location_enabled', 'payment_method_enabled', 'notes_enabled', 'address_enabled',
            'address_required', 'province_enabled', 'city_enabled', 'district_enabled',
            'postal_code_enabled', 'shipping_method_enabled', 'shipping_method_required',
        ];

        $next = $current;
        foreach ($checkboxes as $key) {
            $next[$key] = isset($post[$key]);
        }
        foreach (['headline', 'button_label', 'consent_text'] as $key) {
            $next[$key] = (string)($post[$key] ?? $current[$key] ?? '');
        }
        foreach (['intro', 'summary_note', 'success_message', 'admin_message_template', 'customer_message_template'] as $key) {
            $next[$key] = (string)($post[$key] ?? $current[$key] ?? '');
        }
        $next['need_options'] = checkout_lines_to_array((string)($post['need_options'] ?? ''), 40, 120);
        $next['location_options'] = checkout_lines_to_array((string)($post['location_options'] ?? ''), 80, 120);
        $next['shipping_method_options'] = checkout_lines_to_array((string)($post['shipping_method_options'] ?? ''), 30, 120);
        $next['updated_at'] = date('c');

        return checkout_settings_normalize($next);
    }
}

if (!function_exists('checkout_save_settings')) {
    function checkout_save_settings(array $settings): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        $settings = checkout_settings_normalize($settings);
        $ok = @file_put_contents(
            checkout_settings_file(),
            json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        ) !== false;
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('update_checkout_settings', 'checkout', 'settings', 'Admin menyimpan pengaturan form checkout.', [
                'address_enabled' => $settings['address_enabled'],
                'shipping_method_enabled' => $settings['shipping_method_enabled'],
                'email_required' => $settings['email_required'],
            ]);
        }
        return $ok;
    }
}

if (!function_exists('checkout_field_enabled')) {
    function checkout_field_enabled(string $key, ?array $settings = null): bool
    {
        $settings = $settings ?? checkout_settings();
        return checkout_bool($settings[$key . '_enabled'] ?? false);
    }
}

if (!function_exists('checkout_shipping_needed_for_product')) {
    function checkout_shipping_needed_for_product(?array $product): bool
    {
        if (!$product) {
            return true;
        }
        if (function_exists('commerce_product_shipping_needed')) {
            return commerce_product_shipping_needed($product);
        }
        if (function_exists('product_supports_shipping')) {
            return product_supports_shipping($product);
        }
        $type = strtolower((string)($product['item_type'] ?? $product['type'] ?? $product['category'] ?? ''));
        return !in_array($type, ['digital', 'produk digital', 'ebook', 'e-book', 'course', 'jasa', 'service'], true);
    }
}

if (!function_exists('checkout_shipping_address')) {
    function checkout_shipping_address(array $order): string
    {
        $parts = array_filter([
            checkout_clean((string)($order['address_line'] ?? ''), 240),
            checkout_clean((string)($order['district'] ?? ''), 100),
            checkout_clean((string)($order['city'] ?? $order['location'] ?? ''), 120),
            checkout_clean((string)($order['province'] ?? ''), 120),
            checkout_clean((string)($order['postal_code'] ?? ''), 20),
        ], static fn(string $value): bool => $value !== '');
        return implode(', ', $parts) ?: '-';
    }
}

if (!function_exists('checkout_placeholder_values')) {
    function checkout_placeholder_values(array $order): array
    {
        $ref = function_exists('order_public_reference') ? order_public_reference($order) : (string)($order['id'] ?? 'ORDER');
        $invoiceTotal = function_exists('order_invoice_total') ? order_invoice_total($order) : (((int)($order['price'] ?? 0)) * max(1, (int)($order['quantity'] ?? 1)));
        return [
            '{site_name}' => SITE_NAME,
            '{order_ref}' => $ref,
            '{name}' => checkout_clean((string)($order['name'] ?? 'Kak'), 80) ?: 'Kak',
            '{phone}' => checkout_clean((string)($order['phone'] ?? '-'), 30) ?: '-',
            '{email}' => checkout_clean((string)($order['email'] ?? '-'), 120) ?: '-',
            '{product}' => checkout_clean((string)($order['product_title'] ?? 'Pesanan'), 140) ?: 'Pesanan',
            '{quantity}' => (string)max(1, (int)($order['quantity'] ?? 1)),
            '{need}' => checkout_clean((string)($order['need'] ?? '-'), 140) ?: '-',
            '{location}' => checkout_clean((string)($order['location'] ?? '-'), 140) ?: '-',
            '{planned_date}' => checkout_clean((string)($order['planned_date'] ?? '-'), 30) ?: '-',
            '{payment_method}' => checkout_clean((string)($order['payment_method'] ?? 'Belum Memilih'), 80) ?: 'Belum Memilih',
            '{shipping_method}' => checkout_clean((string)($order['shipping_method'] ?? '-'), 120) ?: '-',
            '{shipping_address}' => checkout_shipping_address($order),
            '{shipping_cost}' => !empty($order['shipping_total']) && function_exists('rupiah') ? rupiah((int)$order['shipping_total']) : 'Konfirmasi admin',
            '{shipping_rule}' => checkout_clean((string)($order['shipping_rule_name'] ?? '-'), 120) ?: '-',
            '{shipping_eta}' => checkout_clean((string)($order['shipping_eta'] ?? '-'), 80) ?: '-',
            '{address_line}' => checkout_clean((string)($order['address_line'] ?? '-'), 240) ?: '-',
            '{province}' => checkout_clean((string)($order['province'] ?? '-'), 120) ?: '-',
            '{city}' => checkout_clean((string)($order['city'] ?? '-'), 120) ?: '-',
            '{district}' => checkout_clean((string)($order['district'] ?? '-'), 120) ?: '-',
            '{postal_code}' => checkout_clean((string)($order['postal_code'] ?? '-'), 20) ?: '-',
            '{message}' => checkout_multiline_clean((string)($order['message'] ?? '-'), 800) ?: '-',
            '{invoice_total}' => $invoiceTotal > 0 && function_exists('rupiah') ? rupiah($invoiceTotal) : 'Konfirmasi admin',
            '{order_status_url}' => function_exists('order_status_url') ? order_status_url($order) : (function_exists('order_success_url') ? order_success_url($order) : ''),
            '{invoice_url}' => function_exists('order_public_invoice_url') ? order_public_invoice_url($order) : '',
        ];
    }
}

if (!function_exists('checkout_render_template')) {
    function checkout_render_template(string $template, array $order): string
    {
        $message = strtr($template, checkout_placeholder_values($order));
        return checkout_multiline_clean($message, 2500);
    }
}

if (!function_exists('checkout_admin_message')) {
    function checkout_admin_message(array $order): string
    {
        $settings = checkout_settings();
        return checkout_render_template((string)$settings['admin_message_template'], $order);
    }
}

if (!function_exists('checkout_customer_message')) {
    function checkout_customer_message(array $order): string
    {
        $settings = checkout_settings();
        return checkout_render_template((string)$settings['customer_message_template'], $order);
    }
}
