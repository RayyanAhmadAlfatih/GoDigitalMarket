<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| SIMPLE ORDER / CHECKOUT DRAFT HELPERS - Template
|--------------------------------------------------------------------------
| Lightweight file-based order foundation for the UMKM mini marketplace.
| This is intentionally not a payment gateway yet: it captures structured
| order intent, logs conversion events, and gives admin a follow-up inbox.
|--------------------------------------------------------------------------
*/

if (!function_exists('order_enabled')) {
    function order_enabled(): bool
    {
        $value = strtolower(trim((string)($_ENV['ENABLE_ORDER_FORM'] ?? 'true')));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('order_clean')) {
    function order_clean(string $value, int $max = 160): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('order_multiline_clean')) {
    function order_multiline_clean(string $value, int $max = 1200): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace("/\r\n|\r/", "\n", (string)$value);
        $value = preg_replace('/[ \t]+/', ' ', (string)$value);
        $value = preg_replace('/\n{3,}/', "\n\n", (string)$value);
        if ($value === '') {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}

if (!function_exists('order_phone_clean')) {
    function order_phone_clean(string $phone): string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[^0-9+]/', '', $phone) ?: '';
        return function_exists('mb_substr') ? mb_substr($phone, 0, 24) : substr($phone, 0, 24);
    }
}

if (!function_exists('order_phone_for_whatsapp')) {
    function order_phone_for_whatsapp(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }
        return $digits;
    }
}

if (!function_exists('order_allowed_statuses')) {
    function order_allowed_statuses(): array
    {
        return ['Baru', 'Diproses', 'Menunggu Pembayaran', 'Deal', 'Dikirim', 'Batal', 'Selesai', 'Spam'];
    }
}

if (!function_exists('order_payment_methods')) {
    function order_payment_methods(): array
    {
        return [
            'Konsultasi Dulu',
            'Transfer Setelah Deal',
            'QRIS Setelah Invoice',
            'Pembayaran Otomatis Setelah Invoice',
            'Tunai Saat Survey/Kirim',
            'Belum Memilih',
        ];
    }
}

if (!function_exists('order_allowed_payment_statuses')) {
    function order_allowed_payment_statuses(): array
    {
        return ['Belum Ditagih', 'Menunggu Pembayaran', 'DP Masuk', 'Lunas', 'Refund', 'Tidak Perlu Payment'];
    }
}

if (!function_exists('order_normalize_payment_method')) {
    function order_normalize_payment_method(string $method): string
    {
        $method = order_clean($method, 60);
        return in_array($method, order_payment_methods(), true) ? $method : 'Belum Memilih';
    }
}

if (!function_exists('order_normalize_payment_status')) {
    function order_normalize_payment_status(string $status): string
    {
        $status = order_clean($status, 60);
        return in_array($status, order_allowed_payment_statuses(), true) ? $status : 'Belum Ditagih';
    }
}

if (!function_exists('order_allowed_fulfillment_statuses')) {
    function order_allowed_fulfillment_statuses(): array
    {
        return [
            'Belum Diproses',
            'Perlu Dipacking',
            'Dipacking',
            'Siap Dikirim',
            'Dikirim',
            'Terkirim',
            'Pickup/COD',
            'Tidak Perlu Pengiriman',
            'Return/Komplain',
        ];
    }
}

if (!function_exists('order_normalize_fulfillment_status')) {
    function order_normalize_fulfillment_status(string $status): string
    {
        $status = order_clean($status, 60);
        return in_array($status, order_allowed_fulfillment_statuses(), true) ? $status : 'Belum Diproses';
    }
}

if (!function_exists('order_datetime_clean')) {
    function order_datetime_clean(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return str_replace('T', ' ', $value);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return $value;
        }
        return order_clean($value, 40);
    }
}

if (!function_exists('order_tracking_url_clean')) {
    function order_tracking_url_clean(string $value): string
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $value)) {
            return '';
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, 240) : substr($value, 0, 240);
    }
}

if (!function_exists('order_log_file')) {
    function order_log_file(?int $timestamp = null): string
    {
        $timestamp = $timestamp ?: time();
        return LOGS_PATH . '/orders-' . date('Y-m', $timestamp) . '.jsonl';
    }
}

if (!function_exists('order_status_file')) {
    function order_status_file(): string
    {
        return STORAGE_PATH . '/order-status.json';
    }
}

if (!function_exists('order_read_statuses')) {
    function order_read_statuses(): array
    {
        $file = order_status_file();
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('order_write_statuses')) {
    function order_write_statuses(array $statuses): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        return @file_put_contents(
            order_status_file(),
            json_encode($statuses, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            LOCK_EX
        ) !== false;
    }
}

if (!function_exists('order_update_status')) {
    function order_update_status(
        string $id,
        string $status,
        string $note = '',
        string $paymentStatus = '',
        string $paymentNote = '',
        array $extra = []
    ): bool {
        $id = order_clean($id, 80);
        $status = order_clean($status, 40);
        if ($id === '' || !in_array($status, order_allowed_statuses(), true)) {
            return false;
        }
        $statuses = order_read_statuses();
        $current = is_array($statuses[$id] ?? null) ? $statuses[$id] : [];
        $previousPaymentStatus = (string)($current['payment_status'] ?? '');
        $statuses[$id] = array_merge($current, [
            'status' => $status,
            'note' => order_multiline_clean($note, 400),
            'updated_at' => date('c'),
        ]);
        if ($paymentStatus !== '') {
            $statuses[$id]['payment_status'] = order_normalize_payment_status($paymentStatus);
        }
        if ($paymentNote !== '') {
            $statuses[$id]['payment_note'] = order_multiline_clean($paymentNote, 400);
        }

        $invoiceKeys = [
            'invoice_number' => 80,
            'invoice_total' => 20,
            'invoice_due_date' => 20,
            'invoice_payment_channel' => 80,
            'invoice_payment_profile' => 80,
            'invoice_payment_instruction' => 800,
            'invoice_public_note' => 500,
        ];
        foreach ($invoiceKeys as $key => $max) {
            if (!array_key_exists($key, $extra)) {
                continue;
            }
            $value = (string)$extra[$key];
            if ($key === 'invoice_payment_instruction' || $key === 'invoice_public_note') {
                $statuses[$id][$key] = order_multiline_clean($value, $max);
            } elseif ($key === 'invoice_total') {
                $statuses[$id][$key] = (string)max(0, (int)(preg_replace('/[^0-9]/', '', $value) ?: 0));
            } elseif ($key === 'invoice_due_date') {
                $statuses[$id][$key] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
            } else {
                $statuses[$id][$key] = order_clean($value, $max);
            }
        }
        if (!empty($extra)) {
            $statuses[$id]['invoice_updated_at'] = date('c');
        }

        $fulfillmentKeys = [
            'fulfillment_status' => 80,
            'shipping_carrier' => 80,
            'shipping_service_actual' => 80,
            'shipping_tracking_number' => 100,
            'shipping_tracking_url' => 240,
            'shipped_at' => 40,
            'delivered_at' => 40,
            'fulfillment_note' => 700,
            'internal_note' => 900,
        ];
        $hasFulfillmentUpdate = false;
        foreach ($fulfillmentKeys as $key => $max) {
            if (!array_key_exists($key, $extra)) {
                continue;
            }
            $value = (string)$extra[$key];
            if ($key === 'fulfillment_status') {
                $statuses[$id][$key] = order_normalize_fulfillment_status($value);
            } elseif (in_array($key, ['fulfillment_note', 'internal_note'], true)) {
                $statuses[$id][$key] = order_multiline_clean($value, $max);
            } elseif ($key === 'shipping_tracking_url') {
                $statuses[$id][$key] = order_tracking_url_clean($value);
            } elseif (in_array($key, ['shipped_at', 'delivered_at'], true)) {
                $statuses[$id][$key] = order_datetime_clean($value);
            } else {
                $statuses[$id][$key] = order_clean($value, $max);
            }
            $hasFulfillmentUpdate = true;
        }
        if ($hasFulfillmentUpdate) {
            $statuses[$id]['fulfillment_updated_at'] = date('c');
        }

        $gatewayKeys = [
            'gateway_provider' => 40,
            'gateway_provider_label' => 80,
            'gateway_reference' => 100,
            'gateway_payment_url' => 500,
            'gateway_token' => 260,
            'gateway_status' => 80,
            'gateway_mode' => 40,
            'gateway_transaction_id' => 120,
            'gateway_amount' => 20,
            'gateway_created_at' => 40,
            'gateway_paid_at' => 40,
            'gateway_expired_at' => 40,
            'gateway_error' => 260,
        ];
        $hasGatewayUpdate = false;
        foreach ($gatewayKeys as $key => $max) {
            if (!array_key_exists($key, $extra)) {
                continue;
            }
            $value = (string)$extra[$key];
            if ($key === 'gateway_payment_url') {
                $statuses[$id][$key] = order_tracking_url_clean($value);
            } elseif ($key === 'gateway_amount') {
                $statuses[$id][$key] = (string)max(0, (int)(preg_replace('/[^0-9]/', '', $value) ?: 0));
            } elseif (in_array($key, ['gateway_created_at', 'gateway_paid_at', 'gateway_expired_at'], true)) {
                $statuses[$id][$key] = order_datetime_clean($value);
            } else {
                $statuses[$id][$key] = order_clean($value, $max);
            }
            $hasGatewayUpdate = true;
        }
        if ($hasGatewayUpdate) {
            $statuses[$id]['gateway_updated_at'] = date('c');
        }

        $history = is_array($statuses[$id]['status_history'] ?? null) ? $statuses[$id]['status_history'] : [];
        $history[] = [
            'time' => date('c'),
            'status' => (string)($statuses[$id]['status'] ?? $status),
            'payment_status' => (string)($statuses[$id]['payment_status'] ?? ''),
            'fulfillment_status' => (string)($statuses[$id]['fulfillment_status'] ?? ''),
            'tracking_number' => (string)($statuses[$id]['shipping_tracking_number'] ?? ''),
            'note' => order_multiline_clean($note, 280),
            'payment_note' => order_multiline_clean($paymentNote, 280),
            'fulfillment_note' => order_multiline_clean((string)($extra['fulfillment_note'] ?? ''), 280),
        ];
        $statuses[$id]['status_history'] = array_slice($history, -30);
        $ok = order_write_statuses($statuses);
        if ($ok && function_exists('activity_log_record')) {
            activity_log_record('update_status', 'order', $id, 'Status order diperbarui.', [
                'status' => $status,
                'payment_status' => $statuses[$id]['payment_status'] ?? '',
                'has_invoice_update' => !empty($extra),
                'fulfillment_status' => (string)($statuses[$id]['fulfillment_status'] ?? ''),
            ]);
        }

        if ($ok && function_exists('payment_reminder_record_completion') && function_exists('order_find_by_id')) {
            $completedStatuses = function_exists('payment_reminder_completed_statuses') ? payment_reminder_completed_statuses() : ['DP Masuk', 'Lunas', 'Tidak Perlu Payment', 'Refund'];
            $currentPaymentStatus = (string)($statuses[$id]['payment_status'] ?? '');
            if (in_array($currentPaymentStatus, $completedStatuses, true) && !in_array($previousPaymentStatus, $completedStatuses, true)) {
                $completedOrder = order_find_by_id($id);
                if ($completedOrder) {
                    $completedOrder['payment_status'] = $currentPaymentStatus;
                    payment_reminder_record_completion($completedOrder, $previousPaymentStatus, 'order_update_status');
                }
            }
        }

        if ($ok && function_exists('digital_delivery_maybe_issue_for_order')) {
            $nextPaymentStatusForDigital = (string)($statuses[$id]['payment_status'] ?? '');
            $digitalPaidStatuses = function_exists('digital_delivery_read_settings') ? (array)(digital_delivery_read_settings()['paid_statuses'] ?? ['Lunas']) : ['Lunas'];
            if (in_array($nextPaymentStatusForDigital, $digitalPaidStatuses, true)) {
                digital_delivery_maybe_issue_for_order($id, 'order_update_status');
            }
        }

        if ($ok && function_exists('member_access_maybe_issue_for_order')) {
            $nextPaymentStatusForMember = (string)($statuses[$id]['payment_status'] ?? '');
            $memberPaidStatuses = function_exists('member_access_read_settings') ? (array)(member_access_read_settings()['paid_statuses'] ?? ['Lunas']) : ['Lunas'];
            if (in_array($nextPaymentStatusForMember, $memberPaidStatuses, true)) {
                member_access_maybe_issue_for_order($id, 'order_update_status');
            }
        }

        if ($ok && function_exists('marketing_integration_dispatch_buyer')) {
            $nextPaymentStatus = (string)($statuses[$id]['payment_status'] ?? '');
            $paidStatuses = function_exists('marketing_integration_paid_statuses') ? marketing_integration_paid_statuses() : ['DP Masuk', 'Lunas'];
            $becamePaid = in_array($nextPaymentStatus, $paidStatuses, true) && !in_array($previousPaymentStatus, $paidStatuses, true);
            $alreadySynced = (string)($current['buyer_synced_at'] ?? '') !== '';
            if ($becamePaid && !$alreadySynced && function_exists('order_find_by_id')) {
                $buyerOrder = order_find_by_id($id);
                if ($buyerOrder) {
                    $buyerOrder['payment_status'] = $nextPaymentStatus;
                    $sentBuyer = marketing_integration_dispatch_buyer($buyerOrder, ['trigger' => 'order_update_status']);
                    if ($sentBuyer) {
                        $latestStatuses = order_read_statuses();
                        if (is_array($latestStatuses[$id] ?? null)) {
                            $latestStatuses[$id]['buyer_synced_at'] = date('c');
                            $latestStatuses[$id]['buyer_synced_source'] = 'order_update_status';
                            order_write_statuses($latestStatuses);
                        }
                    }
                }
            }
        }
        return $ok;
    }
}

if (!function_exists('order_rate_limit_key')) {
    function order_rate_limit_key(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        return hash('sha256', $ip . '|' . $ua . '|' . (string)($_ENV['APP_KEY'] ?? 'produk-order'));
    }
}

if (!function_exists('order_rate_limit_file')) {
    function order_rate_limit_file(): string
    {
        return CACHE_PATH . '/order-rate-limit.json';
    }
}

if (!function_exists('order_is_rate_limited')) {
    function order_is_rate_limited(): bool
    {
        $now = time();
        $lastSubmit = (int)($_SESSION['last_order_submit_at'] ?? 0);
        if ($lastSubmit > 0 && ($now - $lastSubmit) < 20) {
            return true;
        }

        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }

        $file = order_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = order_rate_limit_key();
        $bucket = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => ((int)$ts) > (time() - 3600)));

        return count($bucket) >= 5;
    }
}

if (!function_exists('order_touch_rate_limit')) {
    function order_touch_rate_limit(): void
    {
        $_SESSION['last_order_submit_at'] = time();
        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        $file = order_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = order_rate_limit_key();
        $data[$key] = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => ((int)$ts) > (time() - 3600)));
        $data[$key][] = time();
        if (count($data) > 500) {
            $data = array_slice($data, -500, null, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}

if (!function_exists('order_validate_payload')) {
    function order_validate_payload(array $payload): array
    {
        $errors = [];
        $name = order_clean((string)($payload['name'] ?? ''), 80);
        $phone = order_phone_clean((string)($payload['phone'] ?? ''));
        $productTitle = order_clean((string)($payload['product_title'] ?? $payload['item_title'] ?? ''), 140);
        $need = order_clean((string)($payload['need'] ?? ''), 120);
        $quantity = (int)($payload['quantity'] ?? 1);
        $message = order_multiline_clean((string)($payload['message'] ?? ''), 1200);
        $honeypot = trim((string)($payload['website'] ?? $payload['url'] ?? ''));
        $plannedDate = trim((string)($payload['planned_date'] ?? ''));
        $paymentMethod = order_clean((string)($payload['payment_method'] ?? ''), 60);
        $email = order_clean((string)($payload['email'] ?? ''), 120);
        $consent = (string)($payload['consent_contact'] ?? '') !== '';
        $policyProduct = null;
        if (!empty($payload['product_slug']) && function_exists('get_product_by_slug')) {
            $policyProduct = get_product_by_slug((string)$payload['product_slug']);
        }
        $checkoutSettings = function_exists('checkout_settings_for_product')
            ? checkout_settings_for_product(is_array($policyProduct) ? $policyProduct : null)
            : (function_exists('checkout_settings') ? checkout_settings() : []);
        $shippingRequired = is_array($policyProduct) && function_exists('checkout_shipping_needed_for_product')
            ? checkout_shipping_needed_for_product($policyProduct)
            : ((string)($payload['shipping_required'] ?? '1') === '1');
        $emailRequired = !empty($checkoutSettings['email_enabled']) && !empty($checkoutSettings['email_required']);
        $plannedDateRequired = !empty($checkoutSettings['planned_date_enabled']) && !empty($checkoutSettings['planned_date_required']);
        $needRequired = !empty($checkoutSettings['need_enabled']) && !empty($checkoutSettings['need_required']);
        $locationRequired = !empty($checkoutSettings['location_enabled']) && !empty($checkoutSettings['location_required']);
        $paymentMethodRequired = !empty($checkoutSettings['payment_method_enabled']) && !empty($checkoutSettings['payment_method_required']);
        $notesRequired = !empty($checkoutSettings['notes_enabled']) && !empty($checkoutSettings['notes_required']);
        $addressRequired = $shippingRequired && !empty($checkoutSettings['address_enabled']) && !empty($checkoutSettings['address_required']);
        $shippingMethodRequired = $shippingRequired && !empty($checkoutSettings['shipping_method_enabled']) && !empty($checkoutSettings['shipping_method_required']);
        $addressLine = order_clean((string)($payload['address_line'] ?? ''), 240);
        $province = order_clean((string)($payload['province'] ?? ''), 120);
        $city = order_clean((string)($payload['city'] ?? ''), 120);
        $district = order_clean((string)($payload['district'] ?? ''), 120);
        $postalCode = order_clean((string)($payload['postal_code'] ?? ''), 20);
        $shippingMethod = order_clean((string)($payload['shipping_method'] ?? ''), 120);

        if ($honeypot !== '') {
            $errors[] = 'Permintaan tidak dapat diproses.';
        }
        if (strlen($name) < 2) {
            $errors[] = 'Nama wajib diisi minimal 2 karakter.';
        }
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (strlen($digits) < 8 || strlen($digits) > 16) {
            $errors[] = 'Nomor WhatsApp/telepon belum valid.';
        }
        if ($productTitle === '' && $need === '') {
            $errors[] = 'Produk atau kebutuhan wajib diisi.';
        }
        if ($quantity < 1 || $quantity > 999) {
            $errors[] = 'Jumlah pesanan belum valid.';
        }
        if ($plannedDateRequired && $plannedDate === '') {
            $errors[] = 'Rencana tanggal wajib diisi.';
        }
        if ($plannedDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $plannedDate)) {
            $errors[] = 'Tanggal rencana belum valid.';
        }
        if ($needRequired && $need === '') {
            $errors[] = 'Jenis kebutuhan wajib dipilih.';
        }
        if ($locationRequired && order_clean((string)($payload['location'] ?? ''), 100) === '') {
            $errors[] = 'Lokasi / kota wajib dipilih.';
        }
        if ($paymentMethodRequired && ($paymentMethod === '' || $paymentMethod === 'Belum Memilih')) {
            $errors[] = 'Metode pembayaran wajib dipilih.';
        }
        if ($paymentMethod !== '' && !in_array($paymentMethod, order_payment_methods(), true)) {
            $errors[] = 'Pilihan metode pembayaran belum valid.';
        }
        if ($paymentMethod !== '' && is_array($policyProduct) && function_exists('commerce_payment_is_allowed')) {
            if (!commerce_payment_is_allowed($policyProduct, $paymentMethod)) {
                $errors[] = 'Metode pembayaran ini tidak aktif untuk produk tersebut.';
            }
        }
        if ($emailRequired && $email === '') {
            $errors[] = 'Email wajib diisi.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email belum valid.';
        }
        if ($addressRequired) {
            if ($addressLine === '') {
                $errors[] = 'Alamat lengkap wajib diisi.';
            }
            if (!empty($checkoutSettings['province_enabled']) && $province === '') {
                $errors[] = 'Provinsi wajib diisi.';
            }
            if (!empty($checkoutSettings['city_enabled']) && $city === '') {
                $errors[] = 'Kota/kabupaten wajib diisi.';
            }
            if (!empty($checkoutSettings['district_enabled']) && $district === '') {
                $errors[] = 'Kecamatan wajib diisi.';
            }
        }
        if ($postalCode !== '' && !preg_match('/^[0-9A-Za-z .-]{3,20}$/', $postalCode)) {
            $errors[] = 'Kode pos belum valid.';
        }
        if ($shippingMethodRequired && $shippingMethod === '') {
            $errors[] = 'Metode pengiriman wajib dipilih.';
        }
        if ($shippingMethod !== '' && function_exists('checkout_settings')) {
            $shippingOptions = (array)($checkoutSettings['shipping_method_options'] ?? []);
            if ($shippingOptions && !in_array($shippingMethod, $shippingOptions, true)) {
                $errors[] = 'Pilihan metode pengiriman belum valid.';
            }
        }
        if (function_exists('inventory_validate_order_payload')) {
            $errors = array_merge($errors, inventory_validate_order_payload($payload));
        }
        if (!$consent) {
            $errors[] = 'Centang persetujuan untuk dihubungi admin.';
        }
        if ($notesRequired && $message === '') {
            $errors[] = 'Catatan pesanan wajib diisi.';
        }
        if ($message !== '' && strlen($message) < 4) {
            $errors[] = 'Catatan terlalu pendek.';
        }

        return $errors;
    }
}

if (!function_exists('order_normalize_payload')) {
    function order_normalize_payload(array $payload): array
    {
        $id = 'ord_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $ref = 'ORD-' . date('Ym') . '-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $id), -6));
        $token = bin2hex(random_bytes(16));
        $page = order_clean((string)($payload['page_path'] ?? $payload['source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? current_url())), 240);
        $price = preg_replace('/[^0-9]/', '', (string)($payload['price'] ?? '')) ?: '0';

        $order = [
            'id' => $id,
            'ref' => $ref,
            'public_token' => $token,
            'time' => date('c'),
            'status' => 'Baru',
            'name' => order_clean((string)($payload['name'] ?? ''), 80),
            'phone' => order_phone_clean((string)($payload['phone'] ?? '')),
            'email' => order_clean((string)($payload['email'] ?? ''), 120),
            'consent_contact' => ((string)($payload['consent_contact'] ?? '') !== '') ? 'yes' : 'no',
            'product_title' => order_clean((string)($payload['product_title'] ?? $payload['item_title'] ?? ''), 140),
            'product_slug' => order_clean((string)($payload['product_slug'] ?? ''), 120),
            'product_url' => order_clean((string)($payload['product_url'] ?? $payload['item_url'] ?? ''), 240),
            'category' => order_clean((string)($payload['category'] ?? ''), 80),
            'price' => (int)$price,
            'quantity' => max(1, min(999, (int)($payload['quantity'] ?? 1))),
            'payment_method' => order_normalize_payment_method((string)($payload['payment_method'] ?? '')),
            'payment_status' => order_normalize_payment_status((string)($payload['payment_status'] ?? 'Belum Ditagih')),
            'payment_note' => order_multiline_clean((string)($payload['payment_note'] ?? ''), 400),
            'shipping_required' => ((string)($payload['shipping_required'] ?? '1') === '1') ? 'yes' : 'no',
            'shipping_method' => order_clean((string)($payload['shipping_method'] ?? ''), 120),
            'shipping_quote_source' => order_clean((string)($payload['shipping_quote_source'] ?? ''), 40),
            'shipping_provider' => order_clean((string)($payload['shipping_provider'] ?? ''), 40),
            'shipping_courier' => order_clean((string)($payload['shipping_courier'] ?? ''), 40),
            'shipping_service' => order_clean((string)($payload['shipping_service'] ?? ''), 80),
            'shipping_service_label' => order_clean((string)($payload['shipping_service_label'] ?? ''), 160),
            'shipping_quote_option_id' => order_clean((string)($payload['shipping_quote_option_id'] ?? ''), 40),
            'shipping_cache_key' => order_clean((string)($payload['shipping_cache_key'] ?? ''), 80),
            'shipping_destination_code' => order_clean((string)($payload['shipping_destination_code'] ?? ''), 80),
            'destination_code' => order_clean((string)($payload['shipping_destination_code'] ?? $payload['destination_code'] ?? ''), 80),
            'address_line' => order_clean((string)($payload['address_line'] ?? ''), 240),
            'province' => order_clean((string)($payload['province'] ?? ''), 120),
            'city' => order_clean((string)($payload['city'] ?? ''), 120),
            'district' => order_clean((string)($payload['district'] ?? ''), 120),
            'postal_code' => order_clean((string)($payload['postal_code'] ?? ''), 20),
            'need' => order_clean((string)($payload['need'] ?? ''), 120),
            'location' => order_clean((string)($payload['location'] ?? ''), 100),
            'planned_date' => order_clean((string)($payload['planned_date'] ?? ''), 20),
            'message' => order_multiline_clean((string)($payload['message'] ?? ''), 1200),
            'source' => order_clean((string)($payload['source'] ?? 'product-order-form'), 80),
            'intent' => order_clean((string)($payload['intent'] ?? 'order-draft'), 80),
            'label' => order_clean((string)($payload['label'] ?? 'Order Draft'), 120),
            'checkout_profile_source' => order_clean((string)($payload['checkout_profile_source'] ?? 'global'), 30),
            'checkout_profile_preset' => order_clean((string)($payload['checkout_profile_preset'] ?? 'global'), 30),
            'landing_page_slug' => function_exists('slugify') ? slugify((string)($payload['landing_page_slug'] ?? '')) : order_clean((string)($payload['landing_page_slug'] ?? ''), 120),
            'landing_page_id' => order_clean((string)($payload['landing_page_id'] ?? ''), 90),
            'ab_test_type' => function_exists('landing_page_ab_clean_slug') ? landing_page_ab_clean_slug((string)($payload['ab_test_type'] ?? '')) : order_clean((string)($payload['ab_test_type'] ?? ''), 40),
            'ab_test_id' => function_exists('landing_page_ab_clean_slug') ? landing_page_ab_clean_slug((string)($payload['ab_test_id'] ?? '')) : order_clean((string)($payload['ab_test_id'] ?? ''), 90),
            'ab_test_name' => order_clean((string)($payload['ab_test_name'] ?? ''), 100),
            'ab_variant' => in_array(strtolower((string)($payload['ab_variant'] ?? '')), ['a', 'b'], true) ? strtolower((string)$payload['ab_variant']) : '',
            'ab_variant_label' => order_clean((string)($payload['ab_variant_label'] ?? ''), 80),
            'page_path' => $page,
            'referrer' => order_clean((string)($_SERVER['HTTP_REFERER'] ?? ''), 240),
            'iklan' => function_exists('analytics_current_iklan') ? analytics_current_iklan() : [],
            'marketing_channel' => function_exists('analytics_iklan_channel') ? analytics_iklan_channel(function_exists('analytics_current_iklan') ? analytics_current_iklan() : []) : '',
            'ip_hash' => order_rate_limit_key(),
        ];

        $policyProduct = null;
        if (!empty($order['product_slug']) && function_exists('get_product_by_slug')) {
            $policyProduct = get_product_by_slug((string)$order['product_slug']);
        }

        if (function_exists('shipping_apply_to_order')) {
            $order = shipping_apply_to_order($order);
        }

        if (function_exists('commerce_snapshot_for_order')) {
            $order = array_merge($order, commerce_snapshot_for_order($policyProduct, $order));
        }

        if (function_exists('inventory_order_snapshot')) {
            $order = inventory_order_snapshot($order);
        }

        if (function_exists('checkout_admin_message')) {
            $order['checkout_admin_message'] = checkout_admin_message($order);
        }
        if (function_exists('checkout_customer_message')) {
            $order['checkout_customer_message'] = checkout_customer_message($order);
        }

        return $order;
    }
}

if (!function_exists('order_store')) {
    function order_store(array $order): bool
    {
        if (!order_enabled()) {
            return false;
        }

        $mysqlOk = false;
        $mysqlActive = function_exists('storage_mysql_enabled') && storage_mysql_enabled('orders');
        if ($mysqlActive && function_exists('storage_adapter_mysql_append_order')) {
            $mysqlOk = storage_adapter_mysql_append_order($order);
        }

        if (!is_dir(LOGS_PATH)) {
            @mkdir(LOGS_PATH, 0775, true);
        }
        $json = json_encode($order, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fileOk = $json !== false && @file_put_contents(order_log_file(), $json . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;

        if ($mysqlActive) {
            return $mysqlOk || (function_exists('storage_adapter_safe_fallback_enabled') && storage_adapter_safe_fallback_enabled() && $fileOk);
        }
        return $fileOk;
    }
}

if (!function_exists('order_log_files')) {
    function order_log_files(int $days = 30, array $filters = []): array
    {
        if (!defined('LOGS_PATH') || !is_dir(LOGS_PATH)) {
            return [];
        }
        $files = glob(LOGS_PATH . '/orders-*.jsonl') ?: [];
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }
        $startMonth = $startTs > 0 ? strtotime(date('Y-m-01 00:00:00', $startTs)) : null;
        $endMonth = $endTs > 0 ? strtotime(date('Y-m-01 00:00:00', $endTs)) : null;
        $files = array_values(array_filter($files, static function (string $file) use ($startMonth, $endMonth): bool {
            if (!preg_match('/orders-(\d{4})-(\d{2})\.jsonl$/', $file, $matches)) {
                return false;
            }
            $month = strtotime($matches[1] . '-' . $matches[2] . '-01 00:00:00') ?: 0;
            if ($startMonth !== null && $month < $startMonth) {
                return false;
            }
            if ($endMonth !== null && $month > $endMonth) {
                return false;
            }
            return true;
        }));
        rsort($files, SORT_STRING);
        return $files;
    }
}

if (!function_exists('order_event_timestamp')) {
    function order_event_timestamp(array $order): int
    {
        $time = (string)($order['time'] ?? '');
        $timestamp = $time !== '' ? strtotime($time) : false;
        return $timestamp !== false ? (int)$timestamp : 0;
    }
}

if (!function_exists('order_matches_filters')) {
    function order_matches_filters(array $order, array $filters = []): bool
    {
        foreach (['status', 'source', 'category', 'location', 'need', 'product_title', 'payment_method', 'payment_status', 'fulfillment_status'] as $key) {
            $filter = strtolower(trim((string)($filters[$key] ?? '')));
            if ($filter === '') {
                continue;
            }
            $value = strtolower(trim((string)($order[$key] ?? '')));
            if ($value === '' || !str_contains($value, $filter)) {
                return false;
            }
        }
        $search = strtolower(trim((string)($filters['search'] ?? '')));
        if ($search !== '') {
            $haystack = strtolower(implode(' ', array_map('strval', [
                $order['name'] ?? '',
                $order['phone'] ?? '',
                $order['need'] ?? '',
                $order['location'] ?? '',
                $order['message'] ?? '',
                $order['product_title'] ?? '',
                $order['shipping_method'] ?? '',
                $order['address_line'] ?? '',
                $order['city'] ?? '',
                $order['province'] ?? '',
                $order['district'] ?? '',
                $order['postal_code'] ?? '',
                $order['status'] ?? '',
                $order['payment_status'] ?? '',
                $order['fulfillment_status'] ?? '',
                $order['shipping_tracking_number'] ?? '',
                $order['shipping_carrier'] ?? '',
            ])));
            if (!str_contains($haystack, $search)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('order_read_all')) {
    function order_read_all(int $days = 30, array $filters = [], int $max = 5000): array
    {
        $max = max(50, min(50000, $max));
        $startTs = isset($filters['_start_ts']) ? (int)$filters['_start_ts'] : 0;
        $endTs = isset($filters['_end_ts']) ? (int)$filters['_end_ts'] : 0;
        if (!empty($filters['_all_time'])) {
            $days = 0;
        }
        if ($days > 0 && $startTs <= 0) {
            $startTs = time() - (max(1, min(3650, $days)) * 86400);
        }

        if (function_exists('storage_adapter_mysql_read_orders') && function_exists('storage_mysql_enabled') && storage_mysql_enabled('orders')) {
            $mysqlOrders = storage_adapter_mysql_read_orders($days, $filters, $max);
            if (is_array($mysqlOrders)) {
                $statuses = order_read_statuses();
                foreach ($mysqlOrders as &$mysqlOrder) {
                    $id = (string)($mysqlOrder['id'] ?? '');
                    if ($id !== '' && isset($statuses[$id])) {
                        $mysqlOrder['status'] = (string)($statuses[$id]['status'] ?? ($mysqlOrder['status'] ?? 'Baru'));
                        $mysqlOrder['status_note'] = (string)($statuses[$id]['note'] ?? '');
                        $mysqlOrder['status_updated_at'] = (string)($statuses[$id]['updated_at'] ?? '');
                        if (isset($statuses[$id]['payment_status'])) {
                            $mysqlOrder['payment_status'] = (string)$statuses[$id]['payment_status'];
                        }
                        if (isset($statuses[$id]['payment_note'])) {
                            $mysqlOrder['payment_note'] = (string)$statuses[$id]['payment_note'];
                        }
                        foreach (['invoice_number', 'invoice_total', 'invoice_due_date', 'invoice_payment_channel', 'invoice_payment_profile', 'invoice_payment_instruction', 'invoice_public_note', 'invoice_updated_at', 'buyer_synced_at', 'buyer_synced_source', 'fulfillment_status', 'shipping_carrier', 'shipping_service_actual', 'shipping_tracking_number', 'shipping_tracking_url', 'shipped_at', 'delivered_at', 'fulfillment_note', 'internal_note', 'fulfillment_updated_at', 'gateway_provider', 'gateway_provider_label', 'gateway_reference', 'gateway_payment_url', 'gateway_token', 'gateway_status', 'gateway_mode', 'gateway_transaction_id', 'gateway_amount', 'gateway_created_at', 'gateway_paid_at', 'gateway_expired_at', 'gateway_error', 'gateway_updated_at'] as $statusMetaKey) {
                            if (isset($statuses[$id][$statusMetaKey])) {
                                $mysqlOrder[$statusMetaKey] = (string)$statuses[$id][$statusMetaKey];
                            }
                        }
                        if (isset($statuses[$id]['status_history']) && is_array($statuses[$id]['status_history'])) {
                            $mysqlOrder['status_history'] = $statuses[$id]['status_history'];
                        }
                    }
                }
                unset($mysqlOrder);
                return array_slice($mysqlOrders, 0, $max);
            }
        }
        $statuses = order_read_statuses();
        $orders = [];
        foreach (order_log_files($days, $filters) as $file) {
            $handle = @fopen($file, 'rb');
            if (!$handle) {
                continue;
            }
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $order = json_decode($line, true);
                if (!is_array($order)) {
                    continue;
                }
                $ts = order_event_timestamp($order);
                if ($ts <= 0) {
                    continue;
                }
                if ($startTs > 0 && $ts < $startTs) {
                    continue;
                }
                if ($endTs > 0 && $ts > $endTs) {
                    continue;
                }
                $id = (string)($order['id'] ?? '');
                if ($id !== '' && isset($statuses[$id])) {
                    $order['status'] = (string)($statuses[$id]['status'] ?? ($order['status'] ?? 'Baru'));
                    $order['status_note'] = (string)($statuses[$id]['note'] ?? '');
                    $order['status_updated_at'] = (string)($statuses[$id]['updated_at'] ?? '');
                    if (isset($statuses[$id]['payment_status'])) {
                        $order['payment_status'] = (string)$statuses[$id]['payment_status'];
                    }
                    if (isset($statuses[$id]['payment_note'])) {
                        $order['payment_note'] = (string)$statuses[$id]['payment_note'];
                    }
                    foreach (['invoice_number', 'invoice_total', 'invoice_due_date', 'invoice_payment_channel', 'invoice_payment_profile', 'invoice_payment_instruction', 'invoice_public_note', 'invoice_updated_at', 'buyer_synced_at', 'buyer_synced_source', 'fulfillment_status', 'shipping_carrier', 'shipping_service_actual', 'shipping_tracking_number', 'shipping_tracking_url', 'shipped_at', 'delivered_at', 'fulfillment_note', 'internal_note', 'fulfillment_updated_at', 'gateway_provider', 'gateway_provider_label', 'gateway_reference', 'gateway_payment_url', 'gateway_token', 'gateway_status', 'gateway_mode', 'gateway_transaction_id', 'gateway_amount', 'gateway_created_at', 'gateway_paid_at', 'gateway_expired_at', 'gateway_error', 'gateway_updated_at'] as $statusMetaKey) {
                        if (isset($statuses[$id][$statusMetaKey])) {
                            $order[$statusMetaKey] = (string)$statuses[$id][$statusMetaKey];
                        }
                    }
                    if (isset($statuses[$id]['status_history']) && is_array($statuses[$id]['status_history'])) {
                        $order['status_history'] = $statuses[$id]['status_history'];
                    }
                }
                if (!order_matches_filters($order, $filters)) {
                    continue;
                }
                $order['_ts'] = $ts;
                $orders[] = $order;
                if (count($orders) >= $max) {
                    break 2;
                }
            }
            fclose($handle);
        }
        usort($orders, static fn(array $a, array $b): int => ((int)($b['_ts'] ?? 0)) <=> ((int)($a['_ts'] ?? 0)));
        return $orders;
    }
}

if (!function_exists('order_count_by')) {
    function order_count_by(array $orders, string $key, int $limit = 8): array
    {
        $counts = [];
        foreach ($orders as $order) {
            $value = trim((string)($order[$key] ?? '')) ?: 'Tidak diketahui';
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);
        return array_slice($counts, 0, max(1, $limit), true);
    }
}

if (!function_exists('order_summary')) {
    function order_summary(int $days = 30, array $filters = []): array
    {
        $orders = order_read_all($days, $filters, 20000);
        $today = date('Y-m-d');
        $new = 0;
        $todayCount = 0;
        $grossEstimate = 0;
        $paymentReady = 0;
        $paidLike = 0;
        foreach ($orders as $item) {
            if (($item['status'] ?? 'Baru') === 'Baru') {
                $new++;
            }
            if (date('Y-m-d', (int)($item['_ts'] ?? time())) === $today) {
                $todayCount++;
            }
            $grossEstimate += function_exists('order_invoice_total') ? order_invoice_total($item) : (((int)($item['price'] ?? 0)) * max(1, (int)($item['quantity'] ?? 1)));
            if (($item['status'] ?? '') === 'Menunggu Pembayaran' || ($item['payment_status'] ?? '') === 'Menunggu Pembayaran') {
                $paymentReady++;
            }
            if (in_array((string)($item['payment_status'] ?? ''), ['DP Masuk', 'Lunas'], true)) {
                $paidLike++;
            }
        }
        return [
            'total' => count($orders),
            'new' => $new,
            'today' => $todayCount,
            'gross_estimate' => $grossEstimate,
            'payment_ready' => $paymentReady,
            'paid_like' => $paidLike,
            'by_status' => order_count_by($orders, 'status', 8),
            'by_payment_status' => order_count_by($orders, 'payment_status', 8),
            'by_payment_method' => order_count_by($orders, 'payment_method', 8),
            'by_product' => order_count_by($orders, 'product_title', 8),
            'by_location' => order_count_by($orders, 'location', 8),
            'recent' => array_slice($orders, 0, 40),
        ];
    }
}

/*
|--------------------------------------------------------------------------
| INVOICE / PAYMENT INSTRUCTION FOUNDATION - Template
|--------------------------------------------------------------------------
| Manual invoice helpers for admins. This is not an automatic payment
| gateway yet. It prepares clear payment instructions and printable invoice
| drafts while keeping the system lightweight and file-based.
|--------------------------------------------------------------------------
*/

if (!function_exists('order_invoice_default_due_date')) {
    function order_invoice_default_due_date(): string
    {
        $days = max(1, min(30, (int)($_ENV['PAYMENT_DEFAULT_DUE_DAYS'] ?? 3)));
        return date('Y-m-d', strtotime('+' . $days . ' days'));
    }
}

if (!function_exists('order_invoice_total')) {
    function order_invoice_total(array $order): int
    {
        $stored = (int)($order['invoice_total'] ?? 0);
        if ($stored > 0) {
            return $stored;
        }
        $subtotal = (int)($order['subtotal'] ?? 0);
        if ($subtotal <= 0) {
            $subtotal = ((int)($order['price'] ?? 0)) * max(1, (int)($order['quantity'] ?? 1));
        }
        return $subtotal + max(0, (int)($order['shipping_total'] ?? 0));
    }
}

if (!function_exists('order_invoice_number')) {
    function order_invoice_number(array $order): string
    {
        $existing = order_clean((string)($order['invoice_number'] ?? ''), 80);
        if ($existing !== '') {
            return $existing;
        }
        $id = (string)($order['id'] ?? 'order');
        $date = date('Ymd', order_event_timestamp($order) ?: time());
        return 'INV-' . $date . '-' . strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $id) ?: 'ORDER', -6));
    }
}

if (!function_exists('order_invoice_payment_channel')) {
    function order_invoice_payment_channel(array $order): string
    {
        $stored = order_clean((string)($order['invoice_payment_channel'] ?? ''), 80);
        if ($stored !== '') {
            return $stored;
        }
        $method = order_clean((string)($order['payment_method'] ?? ''), 80);
        if ($method !== '') {
            return $method;
        }
        if (function_exists('payment_public_label')) {
            return payment_public_label();
        }
        return (string)($_ENV['PAYMENT_PUBLIC_LABEL'] ?? 'Transfer/QRIS setelah konfirmasi admin');
    }
}

if (!function_exists('order_default_payment_instruction')) {
    function order_default_payment_instruction(array $order = []): string
    {
        $lines = [];
        $bank = trim((string)($_ENV['PAYMENT_BANK_NAME'] ?? ''));
        $account = trim((string)($_ENV['PAYMENT_ACCOUNT_NUMBER'] ?? ''));
        $holder = trim((string)($_ENV['PAYMENT_ACCOUNT_HOLDER'] ?? ''));
        if ($bank !== '' || $account !== '' || $holder !== '') {
            $lines[] = 'Transfer bank: ' . trim($bank . ' ' . $account . ($holder !== '' ? ' a.n. ' . $holder : ''));
        }
        $qris = trim((string)($_ENV['PAYMENT_QRIS_NOTE'] ?? ''));
        if ($qris !== '') {
            $lines[] = 'QRIS: ' . $qris;
        }
        if (!$lines && function_exists('payment_instruction_for_order')) {
            $profileInstruction = payment_instruction_for_order($order);
            if ($profileInstruction !== '') {
                $lines[] = $profileInstruction;
            }
        }
        if (!$lines) {
            $lines[] = 'Admin akan mengirim detail transfer/QRIS setelah stok, jadwal, dan nominal pesanan dikonfirmasi.';
        }
        $lines[] = 'Mohon kirim bukti pembayaran melalui WhatsApp admin setelah melakukan pembayaran.';
        return implode("\n", $lines);
    }
}

if (!function_exists('order_invoice_payment_instruction')) {
    function order_invoice_payment_instruction(array $order): string
    {
        $stored = order_multiline_clean((string)($order['invoice_payment_instruction'] ?? ''), 1000);
        return $stored !== '' ? $stored : order_default_payment_instruction($order);
    }
}

if (!function_exists('order_invoice_public_note')) {
    function order_invoice_public_note(array $order): string
    {
        $stored = order_multiline_clean((string)($order['invoice_public_note'] ?? ''), 600);
        if ($stored !== '') {
            return $stored;
        }
        if (function_exists('payment_public_note')) {
            return payment_public_note();
        }
        return 'Invoice ini adalah draft instruksi pembayaran manual. Pembayaran dianggap valid setelah admin mengonfirmasi dana/bukti pembayaran.';
    }
}

if (!function_exists('order_find_by_id')) {
    function order_find_by_id(string $id): ?array
    {
        $id = order_clean($id, 80);
        if ($id === '') {
            return null;
        }
        $orders = order_read_all(0, ['_all_time' => true], 50000);
        foreach ($orders as $order) {
            if ((string)($order['id'] ?? '') === $id) {
                return $order;
            }
        }
        return null;
    }
}

if (!function_exists('order_public_reference')) {
    function order_public_reference(array $order): string
    {
        $ref = order_clean((string)($order['ref'] ?? ''), 80);
        if ($ref !== '') {
            return $ref;
        }
        $id = preg_replace('/[^a-zA-Z0-9]/', '', (string)($order['id'] ?? 'ORDER')) ?: 'ORDER';
        $date = date('Ym', order_event_timestamp($order) ?: time());
        return 'ORD-' . $date . '-' . strtoupper(substr($id, -6));
    }
}

if (!function_exists('order_public_token')) {
    function order_public_token(array $order): string
    {
        return order_clean((string)($order['public_token'] ?? $order['token'] ?? ''), 80);
    }
}

if (!function_exists('order_find_by_reference')) {
    function order_find_by_reference(string $ref, string $token = ''): ?array
    {
        $ref = order_clean($ref, 80);
        $token = order_clean($token, 80);
        if ($ref === '') {
            return null;
        }
        $orders = order_read_all(0, ['_all_time' => true], 50000);
        foreach ($orders as $order) {
            $orderRef = order_public_reference($order);
            if ($orderRef !== $ref) {
                continue;
            }
            $storedToken = order_public_token($order);
            if ($storedToken !== '' && $token !== '' && hash_equals($storedToken, $token)) {
                return $order;
            }
            if ($storedToken === '' && $token === '') {
                return $order;
            }
        }
        return null;
    }
}

if (!function_exists('order_success_url')) {
    function order_success_url(array $order): string
    {
        $query = ['ref' => order_public_reference($order)];
        $token = order_public_token($order);
        if ($token !== '') {
            $query['token'] = $token;
        }
        return url('order-success?' . http_build_query($query));
    }
}

if (!function_exists('order_checkout_url')) {
    function order_checkout_url(array|string $product = '', array $extra = []): string
    {
        $params = $extra;
        if (is_array($product)) {
            $slug = order_clean((string)($product['slug'] ?? ''), 120);
            if ($slug !== '') {
                $params['produk'] = $slug;
            }
        } else {
            $slug = order_clean((string)$product, 120);
            if ($slug !== '') {
                $params['produk'] = $slug;
            }
        }
        return url('checkout' . ($params ? '?' . http_build_query($params) : ''));
    }
}

if (!function_exists('order_success_whatsapp_message')) {
    function order_success_whatsapp_message(array $order): string
    {
        $ref = order_public_reference($order);
        $product = (string)($order['product_title'] ?? 'pesanan');
        $name = (string)($order['name'] ?? '');
        $message = "Halo Admin, saya " . $name . " sudah mengisi form pemesanan di " . SITE_NAME . ".\n\n";
        $message .= "No. Order: " . $ref . "\n";
        $message .= "Produk/Layanan: " . $product . "\n";
        if (!empty($order['need'])) {
            $message .= "Kebutuhan: " . (string)$order['need'] . "\n";
        }
        if (!empty($order['location'])) {
            $message .= "Lokasi: " . (string)$order['location'] . "\n";
        }
        $message .= "\nMohon dibantu proses konfirmasi stok, jadwal, dan langkah berikutnya.";
        return $message;
    }
}

if (!function_exists('order_invoice_whatsapp_message')) {
    function order_invoice_whatsapp_message(array $order): string
    {
        $invoice = order_invoice_number($order);
        $total = order_invoice_total($order);
        $dueDate = order_clean((string)($order['invoice_due_date'] ?? ''), 20);
        if ($dueDate === '') {
            $dueDate = order_invoice_default_due_date();
        }
        $product = (string)($order['product_title'] ?? 'Pesanan');
        $quantity = max(1, (int)($order['quantity'] ?? 1));
        $instruction = order_invoice_payment_instruction($order);
        $message = "Halo " . (string)($order['name'] ?? '') . ", berikut draft invoice pesanan Anda di " . SITE_NAME . ".\n\n";
        $message .= "No. Invoice: " . $invoice . "\n";
        $message .= "Produk/Layanan: " . $product . "\n";
        $message .= "Jumlah: " . $quantity . "\n";
        if ($total > 0) {
            $message .= "Total sementara: " . rupiah($total) . "\n";
        }
        $message .= "Batas follow-up/pembayaran: " . date('d M Y', strtotime($dueDate)) . "\n\n";
        $message .= "Instruksi pembayaran:\n" . $instruction . "\n\n";
        $message .= order_invoice_public_note($order);
        return $message;
    }
}



/*
|--------------------------------------------------------------------------
| PUBLIC ORDER STATUS / INVOICE LINKS - Template
|--------------------------------------------------------------------------
| Token-protected public links for customer order status and manual invoice.
| These pages are noindex and are meant for follow-up via WhatsApp/email.
|--------------------------------------------------------------------------
*/

if (!function_exists('order_public_query')) {
    function order_public_query(array $order): array
    {
        $query = ['ref' => order_public_reference($order)];
        $token = order_public_token($order);
        if ($token !== '') {
            $query['token'] = $token;
        }
        return $query;
    }
}

if (!function_exists('order_status_url')) {
    function order_status_url(array $order): string
    {
        return url('order-status?' . http_build_query(order_public_query($order)));
    }
}

if (!function_exists('order_public_invoice_url')) {
    function order_public_invoice_url(array $order): string
    {
        return url('invoice?' . http_build_query(order_public_query($order)));
    }
}

if (!function_exists('order_public_status_label')) {
    function order_public_status_label(array $order): string
    {
        return order_clean((string)($order['status'] ?? 'Baru'), 60) ?: 'Baru';
    }
}

if (!function_exists('order_public_payment_status_label')) {
    function order_public_payment_status_label(array $order): string
    {
        return order_clean((string)($order['payment_status'] ?? 'Belum Ditagih'), 80) ?: 'Belum Ditagih';
    }
}

if (!function_exists('order_status_whatsapp_message')) {
    function order_status_whatsapp_message(array $order): string
    {
        $message = "Halo Admin, saya ingin follow-up status pesanan saya.\n\n";
        $message .= "No. Order: " . order_public_reference($order) . "\n";
        $message .= "Produk/Layanan: " . (string)($order['product_title'] ?? 'Pesanan') . "\n";
        $message .= "Status Order: " . order_public_status_label($order) . "\n";
        $message .= "Status Pembayaran: " . order_public_payment_status_label($order) . "\n\n";
        $message .= "Link status order: " . order_status_url($order) . "\n\n";
        $message .= "Mohon dibantu konfirmasi langkah berikutnya.";
        return $message;
    }
}

if (!function_exists('order_invoice_confirmation_whatsapp_message')) {
    function order_invoice_confirmation_whatsapp_message(array $order): string
    {
        $total = order_invoice_total($order);
        $message = "Halo Admin, saya ingin konfirmasi invoice/pembayaran untuk pesanan berikut.\n\n";
        $message .= "No. Invoice: " . order_invoice_number($order) . "\n";
        $message .= "No. Order: " . order_public_reference($order) . "\n";
        $message .= "Produk/Layanan: " . (string)($order['product_title'] ?? 'Pesanan') . "\n";
        if ($total > 0) {
            $message .= "Total Invoice: " . rupiah($total) . "\n";
        }
        $message .= "Status Pembayaran: " . order_public_payment_status_label($order) . "\n\n";
        $message .= "Link invoice: " . order_public_invoice_url($order) . "\n\n";
        $message .= "Mohon dibantu cek dan konfirmasi pembayaran/instruksi berikutnya.";
        return $message;
    }
}

/*
|--------------------------------------------------------------------------
| CUSTOM WHATSAPP FOLLOW-UP COMPOSER - Template
|--------------------------------------------------------------------------
| Admin helpers to generate editable WhatsApp follow-up messages for each
| order. Messages are still opened manually by admin in WhatsApp, so this is
| lightweight and does not require a WhatsApp gateway/API.
|--------------------------------------------------------------------------
*/

if (!function_exists('order_whatsapp_followup_templates')) {
    function order_whatsapp_followup_templates(): array
    {
        return [
            'followup_order' => [
                'label' => 'Follow-up Order Baru',
                'description' => 'Untuk menindaklanjuti order yang baru masuk.',
                'template' => "Halo {name}, terima kasih sudah mengajukan pemesanan di {site_name}.\n\nNo. Order: {order_ref}\nProduk/Layanan: {product}\nKebutuhan: {need}\nLokasi: {location}\n\nApakah data di atas sudah sesuai? Admin siap bantu cek stok, jadwal, dan langkah berikutnya.",
            ],
            'ask_details' => [
                'label' => 'Minta Detail Kebutuhan',
                'description' => 'Untuk menggali kebutuhan customer sebelum dibuatkan rekomendasi.',
                'template' => "Halo {name}, kami sudah menerima data awal order Anda di {site_name}.\n\nAgar kami bisa bantu rekomendasikan pilihan yang paling sesuai, boleh dibantu konfirmasi:\n1. Kebutuhan utama: produk / jasa / booking / lainnya\n2. Area pengiriman atau lokasi acara\n3. Kisaran budget\n4. Tanggal rencana pelaksanaan\n\nNo. Order: {order_ref}",
            ],
            'stock_schedule' => [
                'label' => 'Konfirmasi Stok & Jadwal',
                'description' => 'Untuk follow-up stok produk dan jadwal layanan.',
                'template' => "Halo {name}, kami follow-up untuk order Anda.\n\nNo. Order: {order_ref}\nProduk/Layanan: {product}\nKebutuhan: {need}\n\nAdmin sedang/akan bantu cek stok dan jadwal yang paling sesuai. Jika ada preferensi khusus, boleh langsung disampaikan di chat ini ya.",
            ],
            'send_invoice' => [
                'label' => 'Kirim Invoice',
                'description' => 'Untuk mengirim draft invoice/instruksi pembayaran manual.',
                'template' => "Halo {name}, berikut draft invoice pesanan Anda di {site_name}.\n\nNo. Invoice: {invoice_no}\nNo. Order: {order_ref}\nProduk/Layanan: {product}\nJumlah: {quantity}\nTotal: {invoice_total}\nJatuh Tempo: {invoice_due_date}\n\nInstruksi pembayaran:\n{invoice_instruction}\n\n{invoice_note}

Link invoice: {invoice_url}",
            ],
            'payment_reminder' => [
                'label' => 'Reminder Pembayaran',
                'description' => 'Untuk mengingatkan pembayaran/DP yang belum masuk.',
                'template' => "Halo {name}, izin mengingatkan untuk invoice pesanan Anda.\n\nNo. Invoice: {invoice_no}\nNo. Order: {order_ref}\nTotal: {invoice_total}\nJatuh Tempo: {invoice_due_date}\nStatus Pembayaran: {payment_status}\n\nJika sudah melakukan pembayaran, mohon kirim bukti transfer/QRIS melalui chat ini agar admin bisa bantu cek dan konfirmasi.",
            ],
            'dp_received' => [
                'label' => 'DP Diterima',
                'description' => 'Untuk konfirmasi bahwa DP sudah diterima/admin catat.',
                'template' => "Halo {name}, terima kasih. DP untuk pesanan Anda sudah kami catat.\n\nNo. Order: {order_ref}\nNo. Invoice: {invoice_no}\nProduk/Layanan: {product}\nStatus Pembayaran: {payment_status}\n\nAdmin akan lanjut bantu proses sesuai jadwal dan kesepakatan.",
            ],
            'paid_full' => [
                'label' => 'Pembayaran Lunas',
                'description' => 'Untuk konfirmasi pelunasan.',
                'template' => "Halo {name}, terima kasih. Pembayaran pesanan Anda sudah kami catat lunas.\n\nNo. Order: {order_ref}\nProduk/Layanan: {product}\n\nAdmin akan lanjut koordinasi detail pelaksanaan/pengiriman sesuai jadwal.",
            ],
            'shipping_update' => [
                'label' => 'Update Pengiriman / Resi',
                'description' => 'Untuk mengirim status fulfillment, ekspedisi, dan nomor resi ke customer.',
                'template' => "Halo {name}, kami update status pengiriman pesanan Anda.

No. Order: {order_ref}
Produk/Layanan: {product}
Status Fulfillment: {fulfillment_status}
Ekspedisi/Kurir: {shipping_carrier}
Layanan: {shipping_service_actual}
No. Resi: {tracking_number}
ETA: {shipping_eta}

{tracking_url}

Catatan admin:
{fulfillment_note}

Link status order: {order_status_url}",
            ],
            'digital_access' => [
                'label' => 'Kirim Akses Digital',
                'description' => 'Untuk mengirim link akses/download produk digital setelah pembayaran valid.',
                'template' => "Halo {name}, akses digital untuk pesanan Anda sudah aktif.\n\nNo. Order: {order_ref}\nProduk/Layanan: {product}\nStatus Pembayaran: {payment_status}\n\nLink akses digital: {digital_access_url}\n\nCatatan akses:\n{digital_instructions}",
            ],
            'order_done' => [
                'label' => 'Order Selesai',
                'description' => 'Untuk closing dan ucapan terima kasih.',
                'template' => "Halo {name}, terima kasih sudah mempercayakan kebutuhan Anda kepada {site_name}.\n\nNo. Order: {order_ref}\nProduk/Layanan: {product}\n\nSemoga layanan kami bermanfaat. Jika ada kebutuhan produk, jasa, booking, atau kebutuhan lainnya di kemudian hari, admin siap bantu kembali.",
            ],
        ];
    }
}

if (!function_exists('order_whatsapp_placeholder_values')) {
    function order_whatsapp_placeholder_values(array $order): array
    {
        $invoiceDue = order_clean((string)($order['invoice_due_date'] ?? ''), 20);
        if ($invoiceDue === '') {
            $invoiceDue = order_invoice_default_due_date();
        }
        $invoiceDueLabel = $invoiceDue !== '' ? date('d M Y', strtotime($invoiceDue)) : '-';
        $invoiceTotal = order_invoice_total($order);

        $digitalRecord = function_exists('digital_delivery_record_for_order') ? digital_delivery_record_for_order($order) : null;
        $digitalAccessUrl = ($digitalRecord && function_exists('digital_delivery_public_access_url')) ? digital_delivery_public_access_url($digitalRecord, $order) : '';
        $digitalInstructions = $digitalRecord ? (string)($digitalRecord['instructions'] ?? '') : '';

        return [
            '{site_name}' => SITE_NAME,
            '{name}' => order_clean((string)($order['name'] ?? 'Kak'), 80) ?: 'Kak',
            '{phone}' => order_clean((string)($order['phone'] ?? '-'), 30) ?: '-',
            '{email}' => order_clean((string)($order['email'] ?? '-'), 120) ?: '-',
            '{order_ref}' => order_public_reference($order),
            '{invoice_no}' => order_invoice_number($order),
            '{product}' => order_clean((string)($order['product_title'] ?? 'Pesanan'), 140) ?: 'Pesanan',
            '{quantity}' => (string)max(1, (int)($order['quantity'] ?? 1)),
            '{need}' => order_clean((string)($order['need'] ?? '-'), 140) ?: '-',
            '{location}' => order_clean((string)($order['location'] ?? '-'), 120) ?: '-',
            '{planned_date}' => !empty($order['planned_date']) ? date('d M Y', strtotime((string)$order['planned_date'])) : '-',
            '{payment_method}' => order_clean((string)($order['payment_method'] ?? 'Belum Memilih'), 80) ?: 'Belum Memilih',
            '{payment_status}' => order_clean((string)($order['payment_status'] ?? 'Belum Ditagih'), 80) ?: 'Belum Ditagih',
            '{shipping_method}' => order_clean((string)($order['shipping_method'] ?? '-'), 120) ?: '-',
            '{shipping_address}' => function_exists('checkout_shipping_address') ? checkout_shipping_address($order) : '-',
            '{shipping_cost}' => !empty($order['shipping_total']) && function_exists('rupiah') ? rupiah((int)$order['shipping_total']) : 'Konfirmasi admin',
            '{shipping_rule}' => order_clean((string)($order['shipping_rule_name'] ?? '-'), 120) ?: '-',
            '{shipping_eta}' => order_clean((string)($order['shipping_eta'] ?? '-'), 80) ?: '-',
            '{fulfillment_status}' => function_exists('order_fulfillment_status_label') ? order_fulfillment_status_label($order) : order_clean((string)($order['fulfillment_status'] ?? 'Belum Diproses'), 80),
            '{shipping_carrier}' => order_clean((string)($order['shipping_carrier'] ?? $order['shipping_courier'] ?? '-'), 80) ?: '-',
            '{shipping_service_actual}' => order_clean((string)($order['shipping_service_actual'] ?? $order['shipping_service_label'] ?? $order['shipping_service'] ?? '-'), 120) ?: '-',
            '{tracking_number}' => order_clean((string)($order['shipping_tracking_number'] ?? '-'), 100) ?: '-',
            '{tracking_url}' => order_tracking_url_clean((string)($order['shipping_tracking_url'] ?? '')) ?: 'Link tracking belum tersedia. Admin akan update jika ekspedisi menyediakan link lacak.',
            '{shipped_at}' => order_clean((string)($order['shipped_at'] ?? '-'), 40) ?: '-',
            '{delivered_at}' => order_clean((string)($order['delivered_at'] ?? '-'), 40) ?: '-',
            '{fulfillment_note}' => order_multiline_clean((string)($order['fulfillment_note'] ?? ''), 700) ?: '-',
            '{address_line}' => order_clean((string)($order['address_line'] ?? '-'), 240) ?: '-',
            '{province}' => order_clean((string)($order['province'] ?? '-'), 120) ?: '-',
            '{city}' => order_clean((string)($order['city'] ?? '-'), 120) ?: '-',
            '{district}' => order_clean((string)($order['district'] ?? '-'), 120) ?: '-',
            '{postal_code}' => order_clean((string)($order['postal_code'] ?? '-'), 20) ?: '-',
            '{invoice_total}' => $invoiceTotal > 0 ? rupiah($invoiceTotal) : 'Belum ditentukan',
            '{invoice_due_date}' => $invoiceDueLabel,
            '{invoice_channel}' => order_invoice_payment_channel($order),
            '{invoice_instruction}' => order_invoice_payment_instruction($order),
            '{invoice_note}' => order_invoice_public_note($order),
            '{admin_note}' => order_multiline_clean((string)($order['status_note'] ?? ''), 400) ?: '-',
            '{payment_note}' => order_multiline_clean((string)($order['payment_note'] ?? ''), 400) ?: '-',
            '{checkout_url}' => !empty($order['product_slug']) ? order_checkout_url((string)$order['product_slug'], ['source' => 'admin-follow-up']) : order_checkout_url('', ['source' => 'admin-follow-up']),
            '{order_status_url}' => function_exists('order_status_url') ? order_status_url($order) : '',
            '{invoice_url}' => function_exists('order_public_invoice_url') ? order_public_invoice_url($order) : '',
            '{digital_access_url}' => $digitalAccessUrl !== '' ? $digitalAccessUrl : 'Akses digital belum aktif.',
            '{digital_instructions}' => $digitalInstructions !== '' ? $digitalInstructions : 'Instruksi akses belum tersedia. Hubungi admin jika membutuhkan bantuan.',
        ];
    }
}

if (!function_exists('order_render_whatsapp_template')) {
    function order_render_whatsapp_template(string $templateKey, array $order): string
    {
        $templates = order_whatsapp_followup_templates();
        $template = (string)($templates[$templateKey]['template'] ?? $templates['followup_order']['template']);
        $message = strtr($template, order_whatsapp_placeholder_values($order));
        $message = order_multiline_clean($message, 1800);
        if (!str_contains($message, 'Sumber:')) {
            $message .= "\n\nSumber: Admin Order Follow-up";
        }
        return $message;
    }
}

if (!function_exists('order_whatsapp_template_messages')) {
    function order_whatsapp_template_messages(array $order): array
    {
        $messages = [];
        foreach (array_keys(order_whatsapp_followup_templates()) as $key) {
            $messages[$key] = order_render_whatsapp_template($key, $order);
        }
        return $messages;
    }
}

/*
|--------------------------------------------------------------------------
| CUSTOMER ACCOUNT-LESS ORDER TRACKING POLISH - Template
|--------------------------------------------------------------------------
| Public lookup helpers for customers who do not have an account. Customers
| can open their private status link with token, or verify an order reference
| using the WhatsApp/phone number used at checkout.
|--------------------------------------------------------------------------
*/

if (!function_exists('order_customer_lookup_enabled')) {
    function order_customer_lookup_enabled(): bool
    {
        $value = strtolower(trim((string)($_ENV['ENABLE_CUSTOMER_ORDER_LOOKUP'] ?? 'true')));
        return !in_array($value, ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('order_phone_digits')) {
    function order_phone_digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: '';
    }
}

if (!function_exists('order_mask_phone')) {
    function order_mask_phone(string $phone): string
    {
        $digits = order_phone_digits($phone);
        if ($digits === '') {
            return '-';
        }
        $visible = substr($digits, -4);
        return '••••' . $visible;
    }
}

if (!function_exists('order_phone_matches_customer_lookup')) {
    function order_phone_matches_customer_lookup(string $storedPhone, string $inputPhone): bool
    {
        $stored = order_phone_digits($storedPhone);
        $input = order_phone_digits($inputPhone);
        if (strlen($stored) < 8 || strlen($input) < 8) {
            return false;
        }
        if ($stored === $input) {
            return true;
        }
        $storedLocal = str_starts_with($stored, '62') ? '0' . substr($stored, 2) : $stored;
        $inputLocal = str_starts_with($input, '62') ? '0' . substr($input, 2) : $input;
        if ($storedLocal === $inputLocal) {
            return true;
        }
        // Keep lookup user-friendly for 08/628 formats, but still require a
        // strong suffix match because the customer must also know the order ref.
        return substr($stored, -8) === substr($input, -8);
    }
}

if (!function_exists('order_lookup_rate_limit_file')) {
    function order_lookup_rate_limit_file(): string
    {
        return CACHE_PATH . '/order-lookup-rate-limit.json';
    }
}

if (!function_exists('order_lookup_rate_limit_key')) {
    function order_lookup_rate_limit_key(): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        return hash('sha256', $ip . '|' . $ua . '|' . (string)($_ENV['APP_KEY'] ?? 'order-public-lookup'));
    }
}

if (!function_exists('order_lookup_is_rate_limited')) {
    function order_lookup_is_rate_limited(): bool
    {
        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        $file = order_lookup_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $bucket = array_values(array_filter((array)($data[order_lookup_rate_limit_key()] ?? []), static fn($ts): bool => ((int)$ts) > (time() - 3600)));
        return count($bucket) >= 20;
    }
}

if (!function_exists('order_lookup_touch_rate_limit')) {
    function order_lookup_touch_rate_limit(): void
    {
        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        $file = order_lookup_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = order_lookup_rate_limit_key();
        $data[$key] = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => ((int)$ts) > (time() - 3600)));
        $data[$key][] = time();
        if (count($data) > 500) {
            $data = array_slice($data, -500, null, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}

if (!function_exists('order_find_by_customer_lookup')) {
    function order_find_by_customer_lookup(string $ref, string $phone): ?array
    {
        if (!order_customer_lookup_enabled()) {
            return null;
        }
        $ref = order_clean($ref, 80);
        $phone = order_phone_clean($phone);
        if ($ref === '' || $phone === '') {
            return null;
        }
        foreach (order_read_all(0, ['_all_time' => true], 50000) as $order) {
            if (order_public_reference($order) !== $ref) {
                continue;
            }
            if (order_phone_matches_customer_lookup((string)($order['phone'] ?? ''), $phone)) {
                return $order;
            }
        }
        return null;
    }
}


if (!function_exists('order_fulfillment_status_label')) {
    function order_fulfillment_status_label(array $order): string
    {
        return order_normalize_fulfillment_status((string)($order['fulfillment_status'] ?? 'Belum Diproses'));
    }
}

if (!function_exists('order_tracking_summary')) {
    function order_tracking_summary(array $order): array
    {
        return [
            'fulfillment_status' => order_fulfillment_status_label($order),
            'carrier' => order_clean((string)($order['shipping_carrier'] ?? $order['shipping_courier'] ?? ''), 80),
            'service' => order_clean((string)($order['shipping_service_actual'] ?? $order['shipping_service_label'] ?? $order['shipping_service'] ?? ''), 120),
            'tracking_number' => order_clean((string)($order['shipping_tracking_number'] ?? ''), 100),
            'tracking_url' => order_tracking_url_clean((string)($order['shipping_tracking_url'] ?? '')),
            'shipped_at' => order_datetime_clean((string)($order['shipped_at'] ?? '')),
            'delivered_at' => order_datetime_clean((string)($order['delivered_at'] ?? '')),
            'note' => order_multiline_clean((string)($order['fulfillment_note'] ?? ''), 700),
            'updated_at' => order_datetime_clean((string)($order['fulfillment_updated_at'] ?? '')),
        ];
    }
}

if (!function_exists('order_status_history')) {
    function order_status_history(array $order, int $limit = 8): array
    {
        $history = is_array($order['status_history'] ?? null) ? $order['status_history'] : [];
        $history = array_values(array_filter($history, static fn($item): bool => is_array($item)));
        $history = array_reverse($history);
        return array_slice($history, 0, max(1, min(30, $limit)));
    }
}

if (!function_exists('order_fulfillment_summary')) {
    function order_fulfillment_summary(array $orders): array
    {
        $summary = [
            'total' => count($orders),
            'needs_action' => 0,
            'to_pack' => 0,
            'ready_to_ship' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'no_shipping' => 0,
            'by_fulfillment_status' => [],
        ];
        foreach ($orders as $order) {
            $status = order_fulfillment_status_label($order);
            $summary['by_fulfillment_status'][$status] = ($summary['by_fulfillment_status'][$status] ?? 0) + 1;
            if (in_array($status, ['Belum Diproses', 'Perlu Dipacking'], true)) {
                $summary['needs_action']++;
            }
            if ($status === 'Dipacking') {
                $summary['to_pack']++;
            }
            if ($status === 'Siap Dikirim') {
                $summary['ready_to_ship']++;
            }
            if ($status === 'Dikirim') {
                $summary['shipped']++;
            }
            if (in_array($status, ['Terkirim'], true)) {
                $summary['delivered']++;
            }
            if (in_array($status, ['Pickup/COD', 'Tidak Perlu Pengiriman'], true)) {
                $summary['no_shipping']++;
            }
        }
        arsort($summary['by_fulfillment_status']);
        return $summary;
    }
}

if (!function_exists('order_public_stage_definitions')) {
    function order_public_stage_definitions(): array
    {
        return [
            'received' => [
                'label' => 'Order Diterima',
                'description' => 'Data order sudah tercatat dan menunggu pengecekan admin.',
            ],
            'review' => [
                'label' => 'Dicek Admin',
                'description' => 'Admin mengecek stok, jadwal, lokasi, dan detail kebutuhan.',
            ],
            'payment' => [
                'label' => 'Pembayaran / DP',
                'description' => 'Invoice atau instruksi pembayaran manual sudah/akan dikirim admin.',
            ],
            'process' => [
                'label' => 'Diproses',
                'description' => 'Pesanan diproses/dipacking sesuai kesepakatan.',
            ],
            'shipped' => [
                'label' => 'Dikirim / Siap Ambil',
                'description' => 'Pesanan sudah masuk tahap pengiriman, pickup, atau penyerahan.',
            ],
            'done' => [
                'label' => 'Selesai',
                'description' => 'Order selesai atau sudah ditutup admin.',
            ],
        ];
    }
}

if (!function_exists('order_public_stage_key')) {
    function order_public_stage_key(array $order): string
    {
        $status = order_public_status_label($order);
        $paymentStatus = order_public_payment_status_label($order);
        $fulfillmentStatus = function_exists('order_fulfillment_status_label') ? order_fulfillment_status_label($order) : (string)($order['fulfillment_status'] ?? '');
        if (in_array($status, ['Selesai', 'Batal', 'Spam'], true) || in_array($fulfillmentStatus, ['Terkirim'], true)) {
            return 'done';
        }
        if (in_array($status, ['Dikirim'], true) || in_array($fulfillmentStatus, ['Siap Dikirim', 'Dikirim', 'Pickup/COD'], true) || (string)($order['shipping_tracking_number'] ?? '') !== '') {
            return 'shipped';
        }
        if (in_array($status, ['Deal'], true) || in_array($paymentStatus, ['DP Masuk', 'Lunas'], true) || in_array($fulfillmentStatus, ['Dipacking'], true)) {
            return 'process';
        }
        if ($status === 'Menunggu Pembayaran' || in_array($paymentStatus, ['Menunggu Pembayaran', 'DP Masuk', 'Lunas'], true)) {
            return 'payment';
        }
        if ($status === 'Diproses' || in_array($fulfillmentStatus, ['Perlu Dipacking'], true)) {
            return 'review';
        }
        return 'received';
    }
}

if (!function_exists('order_public_stage_index')) {
    function order_public_stage_index(string $stageKey): int
    {
        $keys = array_keys(order_public_stage_definitions());
        $index = array_search($stageKey, $keys, true);
        return $index === false ? 0 : (int)$index;
    }
}

if (!function_exists('order_public_next_action')) {
    function order_public_next_action(array $order): array
    {
        $status = order_public_status_label($order);
        $paymentStatus = order_public_payment_status_label($order);
        $tracking = function_exists('order_tracking_summary') ? order_tracking_summary($order) : [];
        $fulfillmentStatus = (string)($tracking['fulfillment_status'] ?? '');
        if ($fulfillmentStatus === 'Terkirim') {
            return [
                'title' => 'Pesanan sudah terkirim',
                'body' => 'Terima kasih. Simpan halaman ini sebagai arsip order dan hubungi admin jika ada catatan setelah pesanan diterima.',
                'kind' => 'success',
            ];
        }
        if ($fulfillmentStatus === 'Dikirim' || (string)($tracking['tracking_number'] ?? '') !== '') {
            return [
                'title' => 'Pesanan sedang dikirim',
                'body' => 'Cek nomor resi atau link tracking yang tampil di halaman ini. Hubungi admin jika alamat atau jadwal terima perlu dikoreksi.',
                'kind' => 'info',
            ];
        }
        if (in_array($status, ['Batal', 'Spam'], true)) {
            return [
                'title' => 'Order ditutup',
                'body' => 'Order ini ditandai tidak dilanjutkan. Hubungi admin jika status ini perlu dikoreksi.',
                'kind' => 'warning',
            ];
        }
        if ($status === 'Selesai' || $paymentStatus === 'Lunas') {
            return [
                'title' => 'Order sudah aman',
                'body' => 'Status order/pembayaran sudah berada di tahap akhir. Simpan halaman ini sebagai arsip pesanan.',
                'kind' => 'success',
            ];
        }
        if ($paymentStatus === 'Menunggu Pembayaran' || $status === 'Menunggu Pembayaran') {
            return [
                'title' => 'Cek invoice dan pembayaran',
                'body' => 'Buka invoice, ikuti instruksi resmi admin, lalu upload bukti pembayaran jika sudah transfer/QRIS.',
                'kind' => 'payment',
            ];
        }
        if ($status === 'Diproses' || $status === 'Deal') {
            return [
                'title' => 'Tunggu koordinasi admin',
                'body' => 'Admin sedang melanjutkan proses sesuai data order. Gunakan tombol WhatsApp jika ada perubahan jadwal/lokasi.',
                'kind' => 'info',
            ];
        }
        return [
            'title' => 'Konfirmasi data ke admin',
            'body' => 'Pastikan nomor WhatsApp aktif. Admin akan follow-up untuk cek stok, jadwal, dan detail pesanan.',
            'kind' => 'info',
        ];
    }
}

if (!function_exists('order_public_payment_proofs')) {
    function order_public_payment_proofs(array $order, int $limit = 5): array
    {
        if (!function_exists('payment_proofs_for_order')) {
            return [];
        }
        return payment_proofs_for_order($order, $limit);
    }
}
