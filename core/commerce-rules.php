<?php

declare(strict_types=1);

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| COMMERCE RULES & PRODUCT-LEVEL CHECKOUT POLICY - 
|--------------------------------------------------------------------------
| UMKM often sell mixed products with different selling rules. This module
| lets global checkout/shipping/payment settings stay simple, while each
| product can override shipping, payment, gateway, preorder, and admin notes.
|--------------------------------------------------------------------------
*/

if (!function_exists('commerce_rule_clean')) {
 function commerce_rule_clean(string $value, int $max = 180): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 if ($value === '') {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('commerce_rule_multiline_clean')) {
 function commerce_rule_multiline_clean(string $value, int $max = 1200): string
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

if (!function_exists('commerce_shipping_policy_options')) {
 function commerce_shipping_policy_options(): array
 {
 return [
 'global' => 'Ikuti setting toko',
 'charge' => 'Produk ini kena ongkir',
 'free' => 'Free ongkir produk ini',
 'pickup_only' => 'Pickup / COD lokal saja',
 'no_shipping' => 'Tidak perlu pengiriman fisik',
 'confirm_admin' => 'Konfirmasi ongkir ke admin dulu',
 ];
 }
}

if (!function_exists('commerce_payment_policy_options')) {
 function commerce_payment_policy_options(): array
 {
 return [
 'global' => 'Ikuti setting pembayaran toko',
 'manual_only' => 'Manual transfer / QRIS manual saja',
 'gateway_only' => 'Payment gateway otomatis saja',
 'manual_gateway' => 'Manual + payment gateway',
 'cod_only' => 'COD / bayar di tempat saja',
 'consult_first' => 'Konsultasi admin dulu',
 ];
 }
}

if (!function_exists('commerce_gateway_options')) {
 function commerce_gateway_options(): array
 {
 return [
 'midtrans' => 'Midtrans',
 'xendit' => 'Xendit',
 'flip' => 'Flip',
 'manual_qris' => 'QRIS Manual',
 'bank_transfer' => 'Transfer Bank Manual',
 ];
 }
}

if (!function_exists('commerce_policy_value')) {
 function commerce_policy_value(mixed $value, array $allowed, string $default = 'global'): string
 {
 $value = commerce_rule_clean((string)$value, 80);
 return array_key_exists($value, $allowed) ? $value : $default;
 }
}

if (!function_exists('commerce_gateway_list_normalize')) {
 function commerce_gateway_list_normalize(mixed $value): array
 {
 if (is_string($value)) {
 $decoded = json_decode($value, true);
 if (is_array($decoded)) {
 $value = $decoded;
 } else {
 $value = preg_split('/[,\r\n|]+/', $value) ?: [];
 }
 }
 if (!is_array($value)) {
 $value = [];
 }
 $allowed = commerce_gateway_options();
 $items = [];
 foreach ($value as $item) {
 $key = strtolower(commerce_rule_clean((string)$item, 60));
 if (array_key_exists($key, $allowed)) {
 $items[] = $key;
 }
 }
 return array_values(array_unique($items));
 }
}

if (!function_exists('commerce_policy_normalize_product')) {
 function commerce_policy_normalize_product(?array $product): array
 {
 $product = is_array($product) ? $product : [];
 $seo = is_array($product['seo'] ?? null) ? $product['seo'] : [];

 $shippingMode = commerce_policy_value(
 $product['shipping_rule_mode'] ?? $seo['shipping_rule_mode'] ?? 'global',
 commerce_shipping_policy_options(),
 'global'
 );
 $paymentMode = commerce_policy_value(
 $product['payment_rule_mode'] ?? $seo['payment_rule_mode'] ?? 'global',
 commerce_payment_policy_options(),
 'global'
 );

 return [
 'shipping_rule_mode' => $shippingMode,
 'payment_rule_mode' => $paymentMode,
 'allowed_payment_gateways' => commerce_gateway_list_normalize($product['allowed_payment_gateways'] ?? $seo['allowed_payment_gateways'] ?? []),
 'checkout_rule_note' => commerce_rule_multiline_clean((string)($product['checkout_rule_note'] ?? $seo['checkout_rule_note'] ?? ''), 900),
 'preorder_note' => commerce_rule_multiline_clean((string)($product['preorder_note'] ?? $seo['preorder_note'] ?? ''), 900),
 'preorder_eta' => commerce_rule_clean((string)($product['preorder_eta'] ?? $seo['preorder_eta'] ?? ''), 120),
 ];
 }
}

if (!function_exists('commerce_shipping_policy')) {
 function commerce_shipping_policy(?array $product): array
 {
 $policy = commerce_policy_normalize_product($product);
 $mode = (string)$policy['shipping_rule_mode'];
 $label = commerce_shipping_policy_options()[$mode] ?? 'Ikuti setting toko';
 $isDigital = $product && function_exists('product_is_digital') && product_is_digital($product);

 $decision = [
 'mode' => $mode,
 'label' => $label,
 'requires_shipping' => true,
 'free_shipping' => false,
 'force_manual_confirmation' => false,
 'pickup_only' => false,
 'note' => (string)$policy['checkout_rule_note'],
 ];

 if ($isDigital) {
 $decision['requires_shipping'] = false;
 $decision['label'] = 'Produk digital / tidak perlu pengiriman fisik';
 $decision['mode'] = $mode === 'global' ? 'digital_no_shipping' : $mode;
 if ($decision['note'] === '') {
 $decision['note'] = 'Produk digital tidak membutuhkan alamat pengiriman fisik.';
 }
 return $decision;
 }

 if ($mode === 'free') {
 $decision['free_shipping'] = true;
 $decision['note'] = $decision['note'] ?: 'Produk ini memakai aturan free ongkir dari admin.';
 return $decision;
 }
 if ($mode === 'pickup_only') {
 $decision['requires_shipping'] = false;
 $decision['pickup_only'] = true;
 $decision['note'] = $decision['note'] ?: 'Produk ini hanya untuk pickup, COD lokal, atau pengiriman yang dikonfirmasi admin.';
 return $decision;
 }
 if ($mode === 'no_shipping') {
 $decision['requires_shipping'] = false;
 $decision['note'] = $decision['note'] ?: 'Produk ini tidak membutuhkan pengiriman fisik.';
 return $decision;
 }
 if ($mode === 'confirm_admin') {
 $decision['force_manual_confirmation'] = true;
 $decision['note'] = $decision['note'] ?: 'Ongkir produk ini perlu dikonfirmasi admin sebelum pembayaran final.';
 return $decision;
 }

 return $decision;
 }
}

if (!function_exists('commerce_product_shipping_needed')) {
 function commerce_product_shipping_needed(?array $product): bool
 {
 $policy = commerce_shipping_policy($product);
 return !empty($policy['requires_shipping']);
 }
}

if (!function_exists('commerce_payment_options_for_product')) {
 function commerce_payment_options_for_product(?array $product, array $globalOptions = []): array
 {
 $globalOptions = $globalOptions ?: (function_exists('order_payment_methods') ? order_payment_methods() : []);
 $policy = commerce_policy_normalize_product($product);
 $mode = (string)$policy['payment_rule_mode'];

 $filter = static function (array $include, array $exclude = []) use ($globalOptions): array {
 $items = [];
 foreach ($globalOptions as $option) {
 $needle = strtolower((string)$option);
 $blocked = false;
 foreach ($exclude as $keyword) {
 if ($keyword !== '' && str_contains($needle, strtolower((string)$keyword))) {
 $blocked = true;
 break;
 }
 }
 if ($blocked) {
 continue;
 }
 foreach ($include as $keyword) {
 if ($keyword !== '' && str_contains($needle, strtolower((string)$keyword))) {
 $items[] = (string)$option;
 break;
 }
 }
 }
 return array_values(array_unique($items));
 };

 $options = match ($mode) {
 'manual_only' => $filter(['transfer', 'qris', 'manual', 'konsultasi', 'deal'], ['otomatis', 'gateway']),
 'gateway_only' => $filter(['otomatis', 'gateway', 'payment'], ['manual', 'transfer setelah deal', 'tunai']),
 'cod_only' => $filter(['tunai', 'cod', 'survey', 'kirim'], ['otomatis', 'gateway']),
 'consult_first' => $filter(['konsultasi', 'belum memilih'], ['otomatis', 'gateway']),
 'manual_gateway' => $globalOptions,
 default => $globalOptions,
 };

 if (!$options) {
 $fallback = match ($mode) {
 'gateway_only' => ['Pembayaran Otomatis Setelah Invoice'],
 'cod_only' => ['Tunai Saat Survey/Kirim'],
 'consult_first' => ['Konsultasi Dulu'],
 default => ['Konsultasi Dulu', 'Transfer Setelah Deal', 'QRIS Setelah Invoice'],
 };
 $options = $fallback;
 }

 return array_values(array_unique($options));
 }
}

if (!function_exists('commerce_payment_default_for_product')) {
 function commerce_payment_default_for_product(?array $product, array $options = []): string
 {
 $options = $options ?: commerce_payment_options_for_product($product);
 $policy = commerce_policy_normalize_product($product);
 $preferred = match ((string)$policy['payment_rule_mode']) {
 'gateway_only' => 'Pembayaran Otomatis Setelah Invoice',
 'manual_only' => 'Transfer Setelah Deal',
 'cod_only' => 'Tunai Saat Survey/Kirim',
 'consult_first' => 'Konsultasi Dulu',
 default => 'Konsultasi Dulu',
 };
 return in_array($preferred, $options, true) ? $preferred : (string)($options[0] ?? 'Belum Memilih');
 }
}

if (!function_exists('commerce_payment_is_allowed')) {
 function commerce_payment_is_allowed(?array $product, string $method): bool
 {
 $method = commerce_rule_clean($method, 80);
 if ($method === '') {
 return true;
 }
 return in_array($method, commerce_payment_options_for_product($product), true);
 }
}

if (!function_exists('commerce_allowed_gateway_labels')) {
 function commerce_allowed_gateway_labels(?array $product): array
 {
 $policy = commerce_policy_normalize_product($product);
 $labels = commerce_gateway_options();
 $keys = (array)$policy['allowed_payment_gateways'];
 if (!$keys) {
 return [];
 }
 return array_values(array_filter(array_map(static fn(string $key): string => (string)($labels[$key] ?? ''), $keys)));
 }
}

if (!function_exists('commerce_preorder_status')) {
 function commerce_preorder_status(?array $product): array
 {
 $policy = commerce_policy_normalize_product($product);
 $stockStatus = strtolower((string)($product['stock_status'] ?? ''));
 $enabled = $stockStatus === 'preorder' || $policy['preorder_note'] !== '' || $policy['preorder_eta'] !== '';
 return [
 'enabled' => $enabled,
 'eta' => (string)$policy['preorder_eta'],
 'note' => (string)$policy['preorder_note'],
 ];
 }
}

if (!function_exists('commerce_policy_badges')) {
 function commerce_policy_badges(?array $product): array
 {
 $badges = [];
 $shipping = commerce_shipping_policy($product);
 $payment = commerce_policy_normalize_product($product);
 if (($shipping['mode'] ?? 'global') !== 'global') {
 $badges[] = (string)$shipping['label'];
 }
 if (($payment['payment_rule_mode'] ?? 'global') !== 'global') {
 $badges[] = (string)(commerce_payment_policy_options()[$payment['payment_rule_mode']] ?? 'Aturan pembayaran khusus');
 }
 $preorder = commerce_preorder_status($product);
 if (!empty($preorder['enabled'])) {
 $badges[] = 'Pre-order' . (!empty($preorder['eta']) ? ': ' . $preorder['eta'] : '');
 }
 return array_values(array_unique(array_filter($badges)));
 }
}

if (!function_exists('commerce_snapshot_for_order')) {
 function commerce_snapshot_for_order(?array $product, array $order = []): array
 {
 $policy = commerce_policy_normalize_product($product);
 $shipping = commerce_shipping_policy($product);
 $preorder = commerce_preorder_status($product);
 return [
 'commerce_shipping_policy' => (string)($shipping['mode'] ?? 'global'),
 'commerce_shipping_policy_label' => (string)($shipping['label'] ?? ''),
 'commerce_payment_policy' => (string)($policy['payment_rule_mode'] ?? 'global'),
 'commerce_payment_policy_label' => (string)(commerce_payment_policy_options()[$policy['payment_rule_mode']] ?? ''),
 'commerce_allowed_gateways' => implode(', ', commerce_allowed_gateway_labels($product)),
 'commerce_checkout_rule_note' => (string)($policy['checkout_rule_note'] ?? ''),
 'commerce_preorder_enabled' => !empty($preorder['enabled']) ? 'yes' : 'no',
 'commerce_preorder_eta' => (string)($preorder['eta'] ?? ''),
 'commerce_preorder_note' => (string)($preorder['note'] ?? ''),
 ];
 }
}
