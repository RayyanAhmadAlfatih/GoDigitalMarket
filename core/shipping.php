<?php

declare(strict_types=1);

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| SHIPPING / ONGKIR ENGINE - Template
|--------------------------------------------------------------------------
| adds multi-origin / warehouse routing on top of the
| Shipping API Bridge and smart cache foundation.
|--------------------------------------------------------------------------
*/

if (!function_exists('shipping_settings_file')) {
 function shipping_settings_file(): string
 {
 return STORAGE_PATH . '/shipping-settings.json';
 }
}

if (!function_exists('shipping_cache_dir')) {
 function shipping_cache_dir(): string
 {
 return CACHE_PATH . '/shipping-ongkir';
 }
}

if (!function_exists('shipping_clean')) {
 function shipping_clean(string $value, int $max = 180): string
 {
 if (function_exists('checkout_clean')) {
 return checkout_clean($value, $max);
 }
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 if ($value === '') {
 return '';
 }
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('shipping_multiline_clean')) {
 function shipping_multiline_clean(string $value, int $max = 3000): string
 {
 if (function_exists('checkout_multiline_clean')) {
 return checkout_multiline_clean($value, $max);
 }
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

if (!function_exists('shipping_bool')) {
 function shipping_bool(mixed $value, bool $default = false): bool
 {
 if (function_exists('checkout_bool')) {
 return checkout_bool($value, $default);
 }
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

if (!function_exists('shipping_money_int')) {
 function shipping_money_int(mixed $value, int $max = 999999999): int
 {
 $digits = preg_replace('/[^0-9]/', '', (string)$value) ?: '0';
 return max(0, min($max, (int)$digits));
 }
}

if (!function_exists('shipping_float_value')) {
 function shipping_float_value(mixed $value, float $default = 0.0, float $max = 99999.0): float
 {
 $raw = str_replace(',', '.', trim((string)$value));
 if ($raw === '' || !is_numeric($raw)) {
 return $default;
 }
 return max(0.0, min($max, (float)$raw));
 }
}

if (!function_exists('shipping_keywords_to_array')) {
 function shipping_keywords_to_array(string|array $value, int $limit = 24): array
 {
 $items = is_array($value) ? $value : preg_split('/[,\n\r|]+/', (string)$value);
 $items = array_map(static fn($item): string => strtolower(shipping_clean((string)$item, 80)), (array)$items);
 $items = array_values(array_unique(array_filter($items, static fn(string $item): bool => $item !== '')));
 return array_slice($items, 0, max(1, $limit));
 }
}

if (!function_exists('shipping_csv_to_array')) {
 function shipping_csv_to_array(string|array $value, int $limit = 20): array
 {
 $items = is_array($value) ? $value : preg_split('/[,\n\r|]+/', (string)$value);
 $items = array_map(static fn($item): string => strtolower(shipping_clean((string)$item, 40)), (array)$items);
 $items = array_values(array_unique(array_filter($items, static fn(string $item): bool => $item !== '')));
 return array_slice($items, 0, max(1, $limit));
 }
}

if (!function_exists('shipping_default_settings')) {
 function shipping_default_settings(): array
 {
 return [
 'enabled' => true,
 'shipping_mode' => 'manual', // manual, api, hybrid
 'origin_city' => 'Depok',
 'origin_province' => 'Jawa Barat',
 'default_weight_kg' => 1.0,
 'round_weight_up' => true,
 'handling_fee' => 0,
 'free_shipping_threshold' => 0,
 'fallback_enabled' => true,
 'fallback_base_cost' => 25000,
 'fallback_per_kg' => 5000,
 'fallback_eta' => 'Konfirmasi admin',
 'public_note' => 'Estimasi ongkir dihitung dari aturan admin. Biaya final tetap bisa dikonfirmasi ulang sebelum invoice.',
 'checkout_preview_enabled' => true,
 'pickup_keywords' => ['ambil', 'pickup', 'cod', 'bayar di tempat', 'digital', 'tidak perlu pengiriman'],
 'api_enabled' => false,
 'api_provider' => 'rajaongkir', // rajaongkir, komerce, api_co_id, binderbyte, custom
 'api_base_url' => '',
 'api_key' => '',
 'api_origin_code' => '',
 'api_origin_label' => 'Depok',
 'multi_origin_enabled' => true,
 'origin_selection_mode' => 'product_first', // product_first, keyword_auto, global_only
 'origin_locations' => [
 [
 'active' => true,
 'default' => true,
 'id' => 'gudang-utama-depok',
 'name' => 'Gudang Utama Depok',
 'city' => 'Depok',
 'province' => 'Jawa Barat',
 'api_origin_code' => '',
 'api_origin_label' => 'Depok',
 'keywords' => ['depok', 'utama', 'default'],
 'note' => 'Asal pengiriman default untuk produk yang belum punya gudang khusus.',
 ],
 [
 'active' => false,
 'default' => false,
 'id' => 'gudang-bandung',
 'name' => 'Gudang Bandung',
 'city' => 'Bandung',
 'province' => 'Jawa Barat',
 'api_origin_code' => '',
 'api_origin_label' => 'Bandung',
 'keywords' => ['bandung', 'jawa barat'],
 'note' => 'Contoh asal pengiriman kedua, aktifkan jika dipakai.',
 ],
 ],
 'api_couriers' => ['jne', 'jnt', 'sicepat', 'pos'],
 'api_cache_ttl_minutes' => 720,
 'api_error_cache_minutes' => 10,
 'api_session_cache_enabled' => true,
 'api_rate_limit_per_hour' => 20,
 'api_timeout_seconds' => 8,
 'api_fallback_to_manual' => true,
 'api_debug_log' => false,
 'api_destination_rules' => [],
 'rules' => [
 [
 'active' => true,
 'name' => 'Jabodetabek Ring 1',
 'keywords' => ['jakarta', 'depok', 'bogor', 'tangerang', 'bekasi'],
 'base_cost' => 15000,
 'per_kg' => 4000,
 'eta' => '1-2 hari',
 'note' => 'Cocok untuk same day/kurir lokal/ekspedisi area dekat.',
 ],
 [
 'active' => true,
 'name' => 'Pulau Jawa Reguler',
 'keywords' => ['bandung', 'cirebon', 'semarang', 'yogyakarta', 'solo', 'surabaya', 'malang', 'jawa'],
 'base_cost' => 22000,
 'per_kg' => 6000,
 'eta' => '2-4 hari',
 'note' => 'Estimasi awal untuk ekspedisi reguler Pulau Jawa.',
 ],
 [
 'active' => true,
 'name' => 'Luar Jawa Estimasi',
 'keywords' => ['sumatera', 'kalimantan', 'sulawesi', 'bali', 'lombok', 'ntb', 'ntt', 'papua'],
 'base_cost' => 35000,
 'per_kg' => 9000,
 'eta' => '3-7 hari',
 'note' => 'Estimasi awal, admin bisa validasi ulang sebelum invoice.',
 ],
 ],
 'updated_at' => '',
 ];
 }
}

if (!function_exists('shipping_normalize_rule')) {
 function shipping_normalize_rule(array $rule): array
 {
 return [
 'active' => shipping_bool($rule['active'] ?? true, true),
 'name' => shipping_clean((string)($rule['name'] ?? ''), 100) ?: 'Zona Ongkir',
 'keywords' => shipping_keywords_to_array($rule['keywords'] ?? [], 24),
 'base_cost' => shipping_money_int($rule['base_cost'] ?? 0),
 'per_kg' => shipping_money_int($rule['per_kg'] ?? 0),
 'eta' => shipping_clean((string)($rule['eta'] ?? ''), 80),
 'note' => shipping_clean((string)($rule['note'] ?? ''), 220),
 ];
 }
}

if (!function_exists('shipping_normalize_destination_rule')) {
 function shipping_normalize_destination_rule(array $rule): array
 {
 return [
 'active' => shipping_bool($rule['active'] ?? true, true),
 'label' => shipping_clean((string)($rule['label'] ?? ''), 120),
 'keywords' => shipping_keywords_to_array($rule['keywords'] ?? [], 30),
 'code' => shipping_clean((string)($rule['code'] ?? ''), 80),
 'provider' => shipping_clean((string)($rule['provider'] ?? ''), 40),
 ];
 }
}


if (!function_exists('shipping_origin_id_from_label')) {
 function shipping_origin_id_from_label(string $value, int $index = 0): string
 {
 $value = trim($value);
 if ($value === '') {
 $value = 'gudang-' . ($index + 1);
 }
 if (function_exists('slugify')) {
 $id = slugify($value);
 } else {
 $id = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $value) ?: '');
 $id = trim($id, '-');
 }
 return shipping_clean($id ?: ('gudang-' . ($index + 1)), 80);
 }
}

if (!function_exists('shipping_normalize_origin_location')) {
 function shipping_normalize_origin_location(array $origin, int $index = 0): array
 {
 $name = shipping_clean((string)($origin['name'] ?? ''), 120);
 $city = shipping_clean((string)($origin['city'] ?? $origin['origin_city'] ?? ''), 100);
 $province = shipping_clean((string)($origin['province'] ?? $origin['origin_province'] ?? ''), 100);
 $id = shipping_clean((string)($origin['id'] ?? ''), 80);
 if ($id === '') {
 $id = shipping_origin_id_from_label($name !== '' ? $name : ($city !== '' ? $city : 'gudang-' . ($index + 1)), $index);
 }
 return [
 'active' => shipping_bool($origin['active'] ?? true, true),
 'default' => shipping_bool($origin['default'] ?? false, false),
 'id' => $id,
 'name' => $name ?: ('Gudang ' . ($index + 1)),
 'city' => $city ?: 'Depok',
 'province' => $province ?: 'Jawa Barat',
 'api_origin_code' => shipping_clean((string)($origin['api_origin_code'] ?? $origin['api_code'] ?? ''), 80),
 'api_origin_label' => shipping_clean((string)($origin['api_origin_label'] ?? $origin['api_label'] ?? $city), 120),
 'keywords' => shipping_keywords_to_array($origin['keywords'] ?? [], 30),
 'note' => shipping_clean((string)($origin['note'] ?? ''), 220),
 ];
 }
}

if (!function_exists('shipping_origin_label')) {
 function shipping_origin_label(array $origin): string
 {
 $name = trim((string)($origin['name'] ?? ''));
 $city = trim((string)($origin['city'] ?? ''));
 $province = trim((string)($origin['province'] ?? ''));
 $place = trim($city . ($province !== '' ? ', ' . $province : ''));
 if ($name !== '' && $place !== '') {
 return $name . ' · ' . $place;
 }
 return $name ?: $place ?: 'Asal pengiriman toko';
 }
}

if (!function_exists('shipping_origin_options')) {
 function shipping_origin_options(?array $settings = null, bool $activeOnly = true): array
 {
 $settings = $settings ?? shipping_settings();
 $origins = [];
 foreach ((array)($settings['origin_locations'] ?? []) as $origin) {
 if ($activeOnly && empty($origin['active'])) {
 continue;
 }
 $origins[] = $origin;
 }
 return $origins;
 }
}

if (!function_exists('shipping_default_origin_location')) {
 function shipping_default_origin_location(?array $settings = null): array
 {
 $settings = $settings ?? shipping_settings();
 $origins = shipping_origin_options($settings, true);
 foreach ($origins as $origin) {
 if (!empty($origin['default'])) {
 return $origin;
 }
 }
 if ($origins) {
 return $origins[0];
 }
 return shipping_normalize_origin_location([
 'name' => 'Asal Utama',
 'city' => (string)($settings['origin_city'] ?? 'Depok'),
 'province' => (string)($settings['origin_province'] ?? 'Jawa Barat'),
 'api_origin_code' => (string)($settings['api_origin_code'] ?? ''),
 'api_origin_label' => (string)($settings['api_origin_label'] ?? ''),
 'default' => true,
 ], 0);
 }
}

if (!function_exists('shipping_origin_by_id')) {
 function shipping_origin_by_id(string $id, ?array $settings = null): ?array
 {
 $id = shipping_clean($id, 80);
 if ($id === '') {
 return null;
 }
 $settings = $settings ?? shipping_settings();
 foreach ((array)($settings['origin_locations'] ?? []) as $origin) {
 if (!empty($origin['active']) && (string)($origin['id'] ?? '') === $id) {
 return $origin;
 }
 }
 return null;
 }
}

if (!function_exists('shipping_origin_for_product')) {
 function shipping_origin_for_product(?array $product = null, array $payload = [], ?array $settings = null): array
 {
 $settings = $settings ?? shipping_settings();
 $mode = (string)($settings['origin_selection_mode'] ?? 'product_first');
 $global = shipping_default_origin_location($settings);
 if (empty($settings['multi_origin_enabled']) || $mode === 'global_only') {
 return $global;
 }

 $candidateId = shipping_clean((string)($payload['shipping_origin_id'] ?? $product['shipping_origin_id'] ?? $product['origin_id'] ?? ''), 80);
 if ($candidateId !== '' && $mode !== 'keyword_auto') {
 $found = shipping_origin_by_id($candidateId, $settings);
 if ($found) {
 return $found;
 }
 }

 $haystack = strtolower(implode(' ', array_map('strval', [
 $payload['product_slug'] ?? '',
 $payload['city'] ?? '',
 $payload['location'] ?? '',
 $product['title'] ?? '',
 $product['sku'] ?? '',
 $product['category'] ?? '',
 $product['location'] ?? '',
 $product['shipping_origin_note'] ?? '',
 ])));
 $haystack = trim(preg_replace('/\s+/', ' ', $haystack) ?: '');
 if ($haystack !== '') {
 foreach (shipping_origin_options($settings, true) as $origin) {
 foreach ((array)($origin['keywords'] ?? []) as $keyword) {
 $keyword = strtolower(trim((string)$keyword));
 if ($keyword !== '' && str_contains($haystack, $keyword)) {
 return $origin;
 }
 }
 }
 }

 return $global;
 }
}

if (!function_exists('shipping_settings_normalize')) {
 function shipping_settings_normalize(array $settings): array
 {
 $defaults = shipping_default_settings();
 $settings = array_merge($defaults, $settings);

 foreach (['enabled', 'round_weight_up', 'fallback_enabled', 'checkout_preview_enabled', 'api_enabled', 'api_session_cache_enabled', 'api_fallback_to_manual', 'api_debug_log', 'multi_origin_enabled'] as $key) {
 $settings[$key] = shipping_bool($settings[$key] ?? $defaults[$key], (bool)$defaults[$key]);
 }

 $mode = strtolower(shipping_clean((string)($settings['shipping_mode'] ?? $defaults['shipping_mode']), 20));
 $settings['shipping_mode'] = in_array($mode, ['manual', 'api', 'hybrid'], true) ? $mode : 'manual';

 $originSelectionMode = strtolower(shipping_clean((string)($settings['origin_selection_mode'] ?? $defaults['origin_selection_mode']), 30));
 $settings['origin_selection_mode'] = in_array($originSelectionMode, ['product_first', 'keyword_auto', 'global_only'], true) ? $originSelectionMode : 'product_first';

 $provider = strtolower(shipping_clean((string)($settings['api_provider'] ?? $defaults['api_provider']), 40));
 $settings['api_provider'] = in_array($provider, ['rajaongkir', 'komerce', 'api_co_id', 'binderbyte', 'custom'], true) ? $provider : 'rajaongkir';

 foreach (['origin_city' => 100, 'origin_province' => 100, 'fallback_eta' => 80, 'api_origin_code' => 80, 'api_origin_label' => 120, 'api_base_url' => 240] as $key => $max) {
 $settings[$key] = shipping_clean((string)($settings[$key] ?? $defaults[$key]), $max);
 }
 if ($settings['origin_city'] === '') {
 $settings['origin_city'] = $defaults['origin_city'];
 }
 if ($settings['origin_province'] === '') {
 $settings['origin_province'] = $defaults['origin_province'];
 }
 if ($settings['fallback_eta'] === '') {
 $settings['fallback_eta'] = $defaults['fallback_eta'];
 }
 if ($settings['api_origin_label'] === '') {
 $settings['api_origin_label'] = $settings['origin_city'];
 }

 $settings['api_key'] = trim((string)($settings['api_key'] ?? ''));
 $settings['default_weight_kg'] = shipping_float_value($settings['default_weight_kg'] ?? $defaults['default_weight_kg'], (float)$defaults['default_weight_kg'], 1000.0);
 if ($settings['default_weight_kg'] <= 0) {
 $settings['default_weight_kg'] = 1.0;
 }
 $settings['handling_fee'] = shipping_money_int($settings['handling_fee'] ?? 0);
 $settings['free_shipping_threshold'] = shipping_money_int($settings['free_shipping_threshold'] ?? 0);
 $settings['fallback_base_cost'] = shipping_money_int($settings['fallback_base_cost'] ?? $defaults['fallback_base_cost']);
 $settings['fallback_per_kg'] = shipping_money_int($settings['fallback_per_kg'] ?? $defaults['fallback_per_kg']);
 $settings['public_note'] = shipping_multiline_clean((string)($settings['public_note'] ?? $defaults['public_note']), 500) ?: $defaults['public_note'];
 $settings['pickup_keywords'] = shipping_keywords_to_array($settings['pickup_keywords'] ?? $defaults['pickup_keywords'], 30) ?: $defaults['pickup_keywords'];
 $settings['api_couriers'] = shipping_csv_to_array($settings['api_couriers'] ?? $defaults['api_couriers'], 20) ?: $defaults['api_couriers'];
 $settings['api_cache_ttl_minutes'] = max(5, min(10080, (int)($settings['api_cache_ttl_minutes'] ?? $defaults['api_cache_ttl_minutes'])));
 $settings['api_error_cache_minutes'] = max(1, min(120, (int)($settings['api_error_cache_minutes'] ?? $defaults['api_error_cache_minutes'])));
 $settings['api_rate_limit_per_hour'] = max(1, min(200, (int)($settings['api_rate_limit_per_hour'] ?? $defaults['api_rate_limit_per_hour'])));
 $settings['api_timeout_seconds'] = max(3, min(20, (int)($settings['api_timeout_seconds'] ?? $defaults['api_timeout_seconds'])));

 $originLocations = [];
 $rawOriginLocations = (array)($settings['origin_locations'] ?? []);
 if (!$rawOriginLocations) {
 $rawOriginLocations = [[
 'active' => true,
 'default' => true,
 'id' => shipping_origin_id_from_label((string)($settings['origin_city'] ?? 'Depok'), 0),
 'name' => 'Asal Utama ' . (string)($settings['origin_city'] ?? 'Depok'),
 'city' => (string)($settings['origin_city'] ?? 'Depok'),
 'province' => (string)($settings['origin_province'] ?? 'Jawa Barat'),
 'api_origin_code' => (string)($settings['api_origin_code'] ?? ''),
 'api_origin_label' => (string)($settings['api_origin_label'] ?? $settings['origin_city'] ?? 'Depok'),
 'keywords' => [(string)($settings['origin_city'] ?? 'Depok')],
 'note' => 'Asal pengiriman bawaan dari pengaturan lama.',
 ]];
 }
 foreach ($rawOriginLocations as $index => $origin) {
 if (!is_array($origin)) {
 continue;
 }
 $normalized = shipping_normalize_origin_location($origin, (int)$index);
 if ($normalized['name'] === '' || $normalized['city'] === '') {
 continue;
 }
 $originLocations[$normalized['id']] = $normalized;
 if (count($originLocations) >= 20) {
 break;
 }
 }
 if (!$originLocations) {
 $originLocations['asal-utama'] = shipping_normalize_origin_location($defaults['origin_locations'][0], 0);
 }
 $hasDefaultOrigin = false;
 foreach ($originLocations as $id => $origin) {
 if (!empty($origin['default'])) {
 if ($hasDefaultOrigin) {
 $originLocations[$id]['default'] = false;
 }
 $hasDefaultOrigin = true;
 }
 }
 if (!$hasDefaultOrigin) {
 $firstId = array_key_first($originLocations);
 $originLocations[$firstId]['default'] = true;
 }
 $settings['origin_locations'] = array_values($originLocations);

 $rules = [];
 foreach ((array)($settings['rules'] ?? []) as $rule) {
 if (!is_array($rule)) {
 continue;
 }
 $normalized = shipping_normalize_rule($rule);
 if ($normalized['name'] === '' || !$normalized['keywords']) {
 continue;
 }
 $rules[] = $normalized;
 if (count($rules) >= 30) {
 break;
 }
 }
 $settings['rules'] = $rules ?: $defaults['rules'];

 $destinationRules = [];
 foreach ((array)($settings['api_destination_rules'] ?? []) as $rule) {
 if (!is_array($rule)) {
 continue;
 }
 $normalized = shipping_normalize_destination_rule($rule);
 if ($normalized['code'] === '' || (!$normalized['keywords'] && $normalized['label'] === '')) {
 continue;
 }
 $destinationRules[] = $normalized;
 if (count($destinationRules) >= 80) {
 break;
 }
 }
 $settings['api_destination_rules'] = $destinationRules;
 $settings['updated_at'] = shipping_clean((string)($settings['updated_at'] ?? ''), 40);

 return $settings;
 }
}

if (!function_exists('shipping_settings')) {
 function shipping_settings(): array
 {
 $file = shipping_settings_file();
 if (!is_file($file)) {
 return shipping_settings_normalize([]);
 }
 $data = json_decode((string)@file_get_contents($file), true);
 return shipping_settings_normalize(is_array($data) ? $data : []);
 }
}

if (!function_exists('shipping_settings_from_post')) {
 function shipping_settings_from_post(array $post): array
 {
 $current = shipping_settings();
 $rules = [];
 $names = (array)($post['rule_name'] ?? []);
 $keywords = (array)($post['rule_keywords'] ?? []);
 $baseCosts = (array)($post['rule_base_cost'] ?? []);
 $perKgs = (array)($post['rule_per_kg'] ?? []);
 $etas = (array)($post['rule_eta'] ?? []);
 $notes = (array)($post['rule_note'] ?? []);
 $actives = (array)($post['rule_active'] ?? []);
 $max = max(count($names), count($keywords), count($baseCosts), 1);

 for ($i = 0; $i < $max; $i++) {
 $name = shipping_clean((string)($names[$i] ?? ''), 100);
 $kw = shipping_keywords_to_array((string)($keywords[$i] ?? ''), 24);
 if ($name === '' && !$kw) {
 continue;
 }
 $rules[] = shipping_normalize_rule([
 'active' => in_array((string)$i, array_map('strval', $actives), true) || isset($actives[$i]),
 'name' => $name,
 'keywords' => $kw,
 'base_cost' => $baseCosts[$i] ?? 0,
 'per_kg' => $perKgs[$i] ?? 0,
 'eta' => $etas[$i] ?? '',
 'note' => $notes[$i] ?? '',
 ]);
 }

 $originLocations = [];
 $originIds = (array)($post['origin_location_id'] ?? []);
 $originNames = (array)($post['origin_location_name'] ?? []);
 $originCities = (array)($post['origin_location_city'] ?? []);
 $originProvinces = (array)($post['origin_location_province'] ?? []);
 $originApiCodes = (array)($post['origin_location_api_code'] ?? []);
 $originApiLabels = (array)($post['origin_location_api_label'] ?? []);
 $originKeywords = (array)($post['origin_location_keywords'] ?? []);
 $originNotes = (array)($post['origin_location_note'] ?? []);
 $originActives = (array)($post['origin_location_active'] ?? []);
 $originDefaultRaw = (string)($post['origin_location_default'] ?? '0');
 $originMax = max(count($originNames), count($originCities), count($originApiCodes), 1);
 for ($i = 0; $i < $originMax; $i++) {
 $name = shipping_clean((string)($originNames[$i] ?? ''), 120);
 $city = shipping_clean((string)($originCities[$i] ?? ''), 100);
 $apiCode = shipping_clean((string)($originApiCodes[$i] ?? ''), 80);
 $kw = shipping_keywords_to_array((string)($originKeywords[$i] ?? ''), 30);
 if ($name === '' && $city === '' && $apiCode === '' && !$kw) {
 continue;
 }
 $originLocations[] = shipping_normalize_origin_location([
 'active' => in_array((string)$i, array_map('strval', $originActives), true) || isset($originActives[$i]),
 'default' => $originDefaultRaw === (string)$i,
 'id' => $originIds[$i] ?? '',
 'name' => $name,
 'city' => $city,
 'province' => $originProvinces[$i] ?? '',
 'api_origin_code' => $apiCode,
 'api_origin_label' => $originApiLabels[$i] ?? '',
 'keywords' => $kw,
 'note' => $originNotes[$i] ?? '',
 ], $i);
 }

 $destinationRules = [];
 $destinationLabels = (array)($post['api_destination_label'] ?? []);
 $destinationKeywords = (array)($post['api_destination_keywords'] ?? []);
 $destinationCodes = (array)($post['api_destination_code'] ?? []);
 $destinationActives = (array)($post['api_destination_active'] ?? []);
 $destinationMax = max(count($destinationLabels), count($destinationKeywords), count($destinationCodes), 1);
 for ($i = 0; $i < $destinationMax; $i++) {
 $label = shipping_clean((string)($destinationLabels[$i] ?? ''), 120);
 $code = shipping_clean((string)($destinationCodes[$i] ?? ''), 80);
 $kw = shipping_keywords_to_array((string)($destinationKeywords[$i] ?? ''), 30);
 if ($label === '' && $code === '' && !$kw) {
 continue;
 }
 $destinationRules[] = shipping_normalize_destination_rule([
 'active' => in_array((string)$i, array_map('strval', $destinationActives), true) || isset($destinationActives[$i]),
 'label' => $label,
 'keywords' => $kw,
 'code' => $code,
 'provider' => (string)($post['api_provider'] ?? 'rajaongkir'),
 ]);
 }

 $newApiKey = trim((string)($post['api_key'] ?? ''));
 $settings = [
 'enabled' => isset($post['enabled']),
 'shipping_mode' => (string)($post['shipping_mode'] ?? 'manual'),
 'origin_city' => (string)($post['origin_city'] ?? ''),
 'origin_province' => (string)($post['origin_province'] ?? ''),
 'default_weight_kg' => $post['default_weight_kg'] ?? 1,
 'round_weight_up' => isset($post['round_weight_up']),
 'handling_fee' => $post['handling_fee'] ?? 0,
 'free_shipping_threshold' => $post['free_shipping_threshold'] ?? 0,
 'fallback_enabled' => isset($post['fallback_enabled']),
 'fallback_base_cost' => $post['fallback_base_cost'] ?? 0,
 'fallback_per_kg' => $post['fallback_per_kg'] ?? 0,
 'fallback_eta' => (string)($post['fallback_eta'] ?? ''),
 'public_note' => (string)($post['public_note'] ?? ''),
 'checkout_preview_enabled' => isset($post['checkout_preview_enabled']),
 'pickup_keywords' => shipping_keywords_to_array((string)($post['pickup_keywords'] ?? ''), 30),
 'api_enabled' => isset($post['api_enabled']),
 'api_provider' => (string)($post['api_provider'] ?? 'rajaongkir'),
 'api_base_url' => (string)($post['api_base_url'] ?? ''),
 'api_key' => $newApiKey !== '' ? $newApiKey : (string)($current['api_key'] ?? ''),
 'api_origin_code' => (string)($post['api_origin_code'] ?? ''),
 'api_origin_label' => (string)($post['api_origin_label'] ?? ''),
 'multi_origin_enabled' => isset($post['multi_origin_enabled']),
 'origin_selection_mode' => (string)($post['origin_selection_mode'] ?? 'product_first'),
 'origin_locations' => $originLocations,
 'api_couriers' => shipping_csv_to_array((string)($post['api_couriers'] ?? ''), 20),
 'api_cache_ttl_minutes' => $post['api_cache_ttl_minutes'] ?? 720,
 'api_error_cache_minutes' => $post['api_error_cache_minutes'] ?? 10,
 'api_session_cache_enabled' => isset($post['api_session_cache_enabled']),
 'api_rate_limit_per_hour' => $post['api_rate_limit_per_hour'] ?? 20,
 'api_timeout_seconds' => $post['api_timeout_seconds'] ?? 8,
 'api_fallback_to_manual' => isset($post['api_fallback_to_manual']),
 'api_debug_log' => isset($post['api_debug_log']),
 'api_destination_rules' => $destinationRules,
 'rules' => $rules,
 'updated_at' => date('c'),
 ];

 return shipping_settings_normalize($settings);
 }
}

if (!function_exists('shipping_save_settings')) {
 function shipping_save_settings(array $settings): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 $settings = shipping_settings_normalize($settings);
 $ok = @file_put_contents(
 shipping_settings_file(),
 json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
 LOCK_EX
 ) !== false;
 if ($ok && function_exists('activity_log_record')) {
 activity_log_record('update_shipping_settings', 'shipping', 'settings', 'Admin menyimpan pengaturan ongkir.', [
 'enabled' => $settings['enabled'],
 'mode' => $settings['shipping_mode'],
 'provider' => $settings['api_provider'],
 'rules' => count((array)$settings['rules']),
 'api_destination_rules' => count((array)$settings['api_destination_rules']),
 'origins' => count((array)$settings['origin_locations']),
 'free_shipping_threshold' => $settings['free_shipping_threshold'],
 ]);
 }
 return $ok;
 }
}

if (!function_exists('shipping_mask_secret')) {
 function shipping_mask_secret(string $value): string
 {
 $value = trim($value);
 if ($value === '') {
 return 'Belum diisi';
 }
 $len = strlen($value);
 if ($len <= 8) {
 return str_repeat('•', max(4, $len));
 }
 return substr($value, 0, 4) . str_repeat('•', max(4, $len - 8)) . substr($value, -4);
 }
}

if (!function_exists('shipping_product_weight_kg')) {
 function shipping_product_weight_kg(?array $product, ?array $settings = null): float
 {
 $settings = $settings ?? shipping_settings();
 $fallback = (float)($settings['default_weight_kg'] ?? 1.0);
 if (!$product) {
 return max(0.1, $fallback);
 }
 $raw = strtolower(trim((string)($product['weight'] ?? $product['shipping_weight'] ?? '')));
 if ($raw === '') {
 return max(0.1, $fallback);
 }
 if (!preg_match('/([0-9]+(?:[\.,][0-9]+)?)/', $raw, $m)) {
 return max(0.1, $fallback);
 }
 $number = (float)str_replace(',', '.', $m[1]);
 if ($number <= 0) {
 return max(0.1, $fallback);
 }
 if (str_contains($raw, 'gram') || preg_match('/\bgr?\b/', $raw)) {
 return max(0.1, $number / 1000);
 }
 if (str_contains($raw, 'kg') || str_contains($raw, 'kilogram')) {
 return max(0.1, $number);
 }
 return max(0.1, $fallback);
 }
}

if (!function_exists('shipping_destination_text')) {
 function shipping_destination_text(array $payload): string
 {
 $parts = [
 $payload['district'] ?? '',
 $payload['city'] ?? '',
 $payload['location'] ?? '',
 $payload['province'] ?? '',
 $payload['postal_code'] ?? '',
 $payload['address_line'] ?? '',
 ];
 $text = strtolower(implode(' ', array_map('strval', $parts)));
 $text = preg_replace('/\s+/', ' ', $text) ?: '';
 return trim($text);
 }
}

if (!function_exists('shipping_method_is_free_flow')) {
 function shipping_method_is_free_flow(string $method, ?array $settings = null): bool
 {
 $settings = $settings ?? shipping_settings();
 $method = strtolower($method);
 foreach ((array)($settings['pickup_keywords'] ?? []) as $keyword) {
 $keyword = strtolower(trim((string)$keyword));
 if ($keyword !== '' && str_contains($method, $keyword)) {
 return true;
 }
 }
 return false;
 }
}

if (!function_exists('shipping_match_rule')) {
 function shipping_match_rule(string $destination, ?array $settings = null): ?array
 {
 $settings = $settings ?? shipping_settings();
 $destination = strtolower(trim($destination));
 if ($destination === '') {
 return null;
 }
 foreach ((array)($settings['rules'] ?? []) as $rule) {
 if (empty($rule['active'])) {
 continue;
 }
 foreach ((array)($rule['keywords'] ?? []) as $keyword) {
 $keyword = strtolower(trim((string)$keyword));
 if ($keyword !== '' && str_contains($destination, $keyword)) {
 return $rule;
 }
 }
 }
 return null;
 }
}

if (!function_exists('shipping_match_destination_code')) {
 function shipping_match_destination_code(array $payload, array $settings): string
 {
 $explicit = shipping_clean((string)($payload['destination_code'] ?? $payload['shipping_destination_code'] ?? ''), 80);
 if ($explicit !== '') {
 return $explicit;
 }
 $destination = shipping_destination_text($payload);
 if ($destination === '') {
 return '';
 }
 $provider = strtolower((string)($settings['api_provider'] ?? ''));
 foreach ((array)($settings['api_destination_rules'] ?? []) as $rule) {
 if (empty($rule['active']) || empty($rule['code'])) {
 continue;
 }
 $ruleProvider = strtolower((string)($rule['provider'] ?? ''));
 if ($ruleProvider !== '' && $ruleProvider !== $provider) {
 continue;
 }
 $keywords = (array)($rule['keywords'] ?? []);
 if (!empty($rule['label'])) {
 $keywords[] = (string)$rule['label'];
 }
 foreach ($keywords as $keyword) {
 $keyword = strtolower(trim((string)$keyword));
 if ($keyword !== '' && str_contains($destination, $keyword)) {
 return (string)$rule['code'];
 }
 }
 }
 return '';
 }
}

if (!function_exists('shipping_manual_estimate')) {
 function shipping_manual_estimate(array $payload, ?array $product = null, ?array $settings = null): array
 {
 $settings = $settings ?? shipping_settings();
 if (!$product && !empty($payload['product_slug']) && function_exists('get_product_by_slug')) {
 $product = get_product_by_slug((string)$payload['product_slug']);
 }

 $quantity = max(1, min(999, (int)($payload['quantity'] ?? 1)));
 $price = shipping_money_int($payload['price'] ?? ($product['price'] ?? 0));
 $subtotal = $price * $quantity;
 $shippingRequired = (string)($payload['shipping_required'] ?? '1') !== '0' && (string)($payload['shipping_required'] ?? 'yes') !== 'no';
 if (function_exists('checkout_shipping_needed_for_product') && $product) {
 $shippingRequired = $shippingRequired && checkout_shipping_needed_for_product($product);
 }
 $method = shipping_clean((string)($payload['shipping_method'] ?? ''), 120);
 $destination = shipping_destination_text($payload);
 $origin = shipping_origin_for_product($product, $payload, $settings);
 $unitWeightKg = shipping_product_weight_kg($product, $settings);
 $rawWeight = max(0.1, $unitWeightKg * $quantity);
 $billableWeight = !empty($settings['round_weight_up']) ? (float)max(1, (int)ceil($rawWeight)) : round($rawWeight, 2);

 $result = [
 'enabled' => !empty($settings['enabled']),
 'required' => $shippingRequired,
 'method' => $method,
 'origin' => shipping_origin_label($origin),
 'origin_id' => (string)($origin['id'] ?? ''),
 'origin_city' => (string)($origin['city'] ?? ''),
 'origin_province' => (string)($origin['province'] ?? ''),
 'origin_code' => trim((string)($origin['api_origin_code'] ?? '')) !== '' ? (string)$origin['api_origin_code'] : (string)($settings['api_origin_code'] ?? ''),
 'origin_api_label' => trim((string)($origin['api_origin_label'] ?? '')) !== '' ? (string)$origin['api_origin_label'] : (string)($settings['api_origin_label'] ?? ''),
 'origin_note' => (string)($origin['note'] ?? ''),
 'destination' => $destination,
 'destination_code' => shipping_match_destination_code($payload, $settings),
 'quantity' => $quantity,
 'subtotal' => $subtotal,
 'unit_weight_kg' => $unitWeightKg,
 'billable_weight_kg' => $billableWeight,
 'rule_name' => '',
 'eta' => '',
 'note' => (string)($settings['public_note'] ?? ''),
 'shipping_cost' => 0,
 'handling_fee' => 0,
 'discount' => 0,
 'total' => 0,
 'free_shipping_applied' => false,
 'mode' => 'manual_confirmation',
 'quote_source' => 'manual',
 'provider' => '',
 'courier' => '',
 'service' => '',
 'service_label' => '',
 'cache_status' => 'none',
 'options' => [],
 ];

 if (function_exists('commerce_shipping_policy')) {
 $policy = commerce_shipping_policy($product);
 if (!empty($policy['free_shipping'])) {
 $result['mode'] = 'product_free_shipping';
 $result['quote_source'] = 'product_policy';
 $result['rule_name'] = (string)($policy['label'] ?? 'Free ongkir produk');
 $result['eta'] = 'Sesuai jadwal pengiriman admin';
 $result['note'] = (string)($policy['note'] ?? 'Produk ini memakai free ongkir.');
 $result['free_shipping_applied'] = true;
 $result['service_label'] = $result['rule_name'];
 return $result;
 }
 if (!empty($policy['force_manual_confirmation'])) {
 $result['mode'] = 'product_shipping_confirm_admin';
 $result['quote_source'] = 'product_policy_manual';
 $result['rule_name'] = (string)($policy['label'] ?? 'Konfirmasi ongkir admin');
 $result['eta'] = 'Konfirmasi admin';
 $result['note'] = (string)($policy['note'] ?? 'Ongkir produk ini perlu dikonfirmasi admin.');
 $result['service_label'] = $result['rule_name'];
 return $result;
 }
 }

 if (!$shippingRequired) {
 $result['mode'] = 'not_required';
 $result['quote_source'] = 'none';
 $result['note'] = 'Item ini tidak membutuhkan pengiriman fisik.';
 if (function_exists('commerce_shipping_policy')) {
 $policy = commerce_shipping_policy($product);
 $result['rule_name'] = (string)($policy['label'] ?? 'Tidak perlu pengiriman');
 $result['note'] = (string)($policy['note'] ?? $result['note']);
 $result['service_label'] = $result['rule_name'];
 }
 return $result;
 }
 if (empty($settings['enabled'])) {
 $result['note'] = 'Ongkir belum diaktifkan admin. Biaya kirim dikonfirmasi manual.';
 return $result;
 }
 if ($method !== '' && shipping_method_is_free_flow($method, $settings)) {
 $result['mode'] = 'pickup_or_digital';
 $result['quote_source'] = 'manual';
 $result['rule_name'] = $method;
 $result['eta'] = 'Sesuai jadwal admin/customer';
 $result['note'] = 'Metode ini tidak memakai ongkir ekspedisi.';
 return $result;
 }

 $rule = shipping_match_rule($destination, $settings);
 if (!$rule && !empty($settings['fallback_enabled'])) {
 $rule = [
 'name' => 'Fallback Manual',
 'base_cost' => (int)($settings['fallback_base_cost'] ?? 0),
 'per_kg' => (int)($settings['fallback_per_kg'] ?? 0),
 'eta' => (string)($settings['fallback_eta'] ?? 'Konfirmasi admin'),
 'note' => 'Kota belum masuk zona khusus, memakai tarif fallback manual.',
 ];
 }

 if (!$rule) {
 $result['note'] = 'Kota tujuan belum cocok dengan aturan ongkir. Admin akan konfirmasi manual.';
 return $result;
 }

 $cost = ((int)($rule['base_cost'] ?? 0)) + ((int)($rule['per_kg'] ?? 0) * max(1, (int)ceil($billableWeight)));
 $handling = (int)($settings['handling_fee'] ?? 0);
 $threshold = (int)($settings['free_shipping_threshold'] ?? 0);
 $discount = 0;
 $freeApplied = false;
 if ($threshold > 0 && $subtotal >= $threshold && $cost > 0) {
 $discount = $cost;
 $freeApplied = true;
 }

 $result['rule_name'] = (string)($rule['name'] ?? 'Zona Ongkir');
 $result['eta'] = (string)($rule['eta'] ?? 'Konfirmasi admin');
 $result['note'] = (string)($rule['note'] ?? ($settings['public_note'] ?? 'Estimasi ongkir manual.'));
 $result['shipping_cost'] = max(0, $cost);
 $result['handling_fee'] = max(0, $handling);
 $result['discount'] = max(0, $discount);
 $result['total'] = max(0, $cost + $handling - $discount);
 $result['free_shipping_applied'] = $freeApplied;
 $result['mode'] = $freeApplied ? 'free_shipping' : 'estimated';
 $result['service_label'] = $result['rule_name'];

 return $result;
 }
}

if (!function_exists('shipping_api_ready')) {
 function shipping_api_ready(array $settings, ?string $originCode = null): bool
 {
 $originCode = trim((string)($originCode ?? ($settings['api_origin_code'] ?? '')));
 return !empty($settings['api_enabled'])
 && trim((string)($settings['api_key'] ?? '')) !== ''
 && $originCode !== ''
 && in_array((string)($settings['shipping_mode'] ?? 'manual'), ['api', 'hybrid'], true);
 }
}


if (!function_exists('shipping_api_any_origin_ready')) {
 function shipping_api_any_origin_ready(array $settings): bool
 {
 if (shipping_api_ready($settings)) {
 return true;
 }
 foreach ((array)($settings['origin_locations'] ?? []) as $origin) {
 if (!empty($origin['active']) && shipping_api_ready($settings, (string)($origin['api_origin_code'] ?? ''))) {
 return true;
 }
 }
 return false;
 }
}

if (!function_exists('shipping_api_cache_key')) {
 function shipping_api_cache_key(array $payload, array $settings, int $weightGram, string $originCode, string $destinationCode, array $couriers): string
 {
 $parts = [
 'shipping-api-cache',
 (string)($settings['api_provider'] ?? 'rajaongkir'),
 $originCode,
 $destinationCode,
 implode(',', $couriers),
 (string)max(1, $weightGram),
 ];
 return hash('sha256', implode('|', $parts));
 }
}

if (!function_exists('shipping_cache_get')) {
 function shipping_cache_get(string $key): ?array
 {
 if ($key === '') {
 return null;
 }
 if (!empty($_SESSION['shipping_api_cache'][$key]) && is_array($_SESSION['shipping_api_cache'][$key])) {
 $item = $_SESSION['shipping_api_cache'][$key];
 if ((int)($item['expires_at'] ?? 0) >= time()) {
 $value = $item['value'] ?? null;
 return is_array($value) ? array_merge($value, ['cache_status' => 'session_hit']) : null;
 }
 }
 $file = shipping_cache_dir() . '/' . $key . '.json';
 if (!is_file($file)) {
 return null;
 }
 $data = json_decode((string)@file_get_contents($file), true);
 if (!is_array($data) || (int)($data['expires_at'] ?? 0) < time()) {
 @unlink($file);
 return null;
 }
 $value = $data['value'] ?? null;
 return is_array($value) ? array_merge($value, ['cache_status' => 'file_hit']) : null;
 }
}

if (!function_exists('shipping_cache_set')) {
 function shipping_cache_set(string $key, array $value, int $ttlSeconds, bool $session = true): void
 {
 if ($key === '' || $ttlSeconds <= 0) {
 return;
 }
 $record = ['expires_at' => time() + $ttlSeconds, 'value' => $value];
 if ($session && session_status() === PHP_SESSION_ACTIVE) {
 $_SESSION['shipping_api_cache'][$key] = $record;
 if (count((array)$_SESSION['shipping_api_cache']) > 40) {
 $_SESSION['shipping_api_cache'] = array_slice((array)$_SESSION['shipping_api_cache'], -30, null, true);
 }
 }
 $dir = shipping_cache_dir();
 if (!is_dir($dir)) {
 @mkdir($dir, 0775, true);
 }
 if (is_dir($dir)) {
 @file_put_contents($dir . '/' . $key . '.json', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
 }
 }
}

if (!function_exists('shipping_rate_limit_hit')) {
 function shipping_rate_limit_hit(array $settings): bool
 {
 if (session_status() !== PHP_SESSION_ACTIVE) {
 return false;
 }
 $limit = max(1, (int)($settings['api_rate_limit_per_hour'] ?? 20));
 $now = time();
 $window = $now - 3600;
 $hits = array_values(array_filter((array)($_SESSION['shipping_api_hits'] ?? []), static fn($ts): bool => (int)$ts >= $window));
 if (count($hits) >= $limit) {
 $_SESSION['shipping_api_hits'] = $hits;
 return true;
 }
 $hits[] = $now;
 $_SESSION['shipping_api_hits'] = $hits;
 return false;
 }
}

if (!function_exists('shipping_api_default_endpoint')) {
 function shipping_api_default_endpoint(string $provider): string
 {
 return match ($provider) {
 'komerce' => 'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost',
 'api_co_id' => 'https://api.api.co.id/api/cek-ongkir',
 'binderbyte' => 'https://api.binderbyte.com/v1/cost',
 default => 'https://api.rajaongkir.com/starter/cost',
 };
 }
}

if (!function_exists('shipping_http_request')) {
 function shipping_http_request(string $url, string $method, array $headers, array $params, int $timeout): array
 {
 $method = strtoupper($method);
 $body = http_build_query($params);
 if ($method === 'GET' && $params) {
 $url .= (str_contains($url, '?') ? '&' : '?') . $body;
 $body = '';
 }

 if (function_exists('curl_init')) {
 $ch = curl_init($url);
 $curlHeaders = [];
 foreach ($headers as $key => $value) {
 if ((string)$value !== '') {
 $curlHeaders[] = $key . ': ' . $value;
 }
 }
 curl_setopt_array($ch, [
 CURLOPT_RETURNTRANSFER => true,
 CURLOPT_FOLLOWLOCATION => true,
 CURLOPT_TIMEOUT => $timeout,
 CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
 CURLOPT_HTTPHEADER => $curlHeaders,
 CURLOPT_USERAGENT => 'U-Growth-Shipping-Bridge/32.34',
 ]);
 if ($method !== 'GET') {
 curl_setopt($ch, CURLOPT_POST, true);
 curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
 }
 $raw = (string)curl_exec($ch);
 $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
 $error = curl_error($ch);
 curl_close($ch);
 return ['status' => $status, 'body' => $raw, 'error' => $error];
 }

 $headerLines = '';
 foreach ($headers as $key => $value) {
 if ((string)$value !== '') {
 $headerLines .= $key . ': ' . $value . "\r\n";
 }
 }
 $context = stream_context_create([
 'http' => [
 'method' => $method,
 'header' => $headerLines,
 'content' => $method === 'GET' ? '' : $body,
 'timeout' => $timeout,
 'ignore_errors' => true,
 ],
 ]);
 $raw = (string)@file_get_contents($url, false, $context);
 $status = 0;
 foreach (($http_response_header ?? []) as $line) {
 if (preg_match('/HTTP\/\S+\s+(\d+)/', (string)$line, $m)) {
 $status = (int)$m[1];
 break;
 }
 }
 return ['status' => $status, 'body' => $raw, 'error' => $raw === '' ? 'Request kosong/gagal.' : ''];
 }
}

if (!function_exists('shipping_api_request_payload')) {
 function shipping_api_request_payload(array $payload, array $settings, int $weightGram, string $originCode, string $destinationCode, string $courier): array
 {
 $provider = (string)($settings['api_provider'] ?? 'rajaongkir');
 $origin = $originCode !== '' ? $originCode : (string)($settings['api_origin_code'] ?? '');
 $key = (string)($settings['api_key'] ?? '');
 $endpoint = trim((string)($settings['api_base_url'] ?? '')) ?: shipping_api_default_endpoint($provider);
 $headers = ['Accept' => 'application/json'];
 $method = 'POST';
 $params = [
 'origin' => $origin,
 'destination' => $destinationCode,
 'weight' => $weightGram,
 'courier' => $courier,
 ];

 if ($provider === 'rajaongkir') {
 $headers['key'] = $key;
 $headers['Content-Type'] = 'application/x-www-form-urlencoded';
 } elseif ($provider === 'komerce') {
 $headers['key'] = $key;
 $headers['x-api-key'] = $key;
 $headers['Content-Type'] = 'application/x-www-form-urlencoded';
 } elseif ($provider === 'api_co_id') {
 $headers['Authorization'] = 'Bearer ' . $key;
 $headers['X-API-Key'] = $key;
 $headers['Content-Type'] = 'application/x-www-form-urlencoded';
 $params = [
 'origin_village_code' => $origin,
 'destination_village_code' => $destinationCode,
 'weight' => $weightGram,
 'courier' => $courier,
 ];
 } elseif ($provider === 'binderbyte') {
 $method = 'GET';
 $params['api_key'] = $key;
 } else {
 $headers['Authorization'] = 'Bearer ' . $key;
 $headers['X-API-Key'] = $key;
 $headers['Content-Type'] = 'application/x-www-form-urlencoded';
 }

 return compact('endpoint', 'method', 'headers', 'params');
 }
}

if (!function_exists('shipping_api_normalize_option')) {
 function shipping_api_normalize_option(array $data, string $defaultCourier = ''): ?array
 {
 $courier = strtolower(shipping_clean((string)($data['courier'] ?? $data['code'] ?? $defaultCourier), 30));
 $service = shipping_clean((string)($data['service'] ?? $data['name'] ?? $data['type'] ?? ''), 80);
 $description = shipping_clean((string)($data['description'] ?? $data['desc'] ?? $data['service_name'] ?? ''), 120);
 $rawCost = $data['cost'] ?? $data['value'] ?? $data['price'] ?? $data['tariff'] ?? 0;
 if (is_array($rawCost)) {
 $rawCost = $rawCost[0]['value'] ?? $rawCost['value'] ?? $rawCost['cost'] ?? 0;
 }
 $cost = shipping_money_int($rawCost);
 $eta = shipping_clean((string)($data['etd'] ?? $data['eta'] ?? $data['duration'] ?? ''), 80);
 if ($cost <= 0 || ($courier === '' && $service === '')) {
 return null;
 }
 $label = strtoupper($courier ?: $defaultCourier) . ($service !== '' ? ' ' . $service : '') . ($description !== '' ? ' · ' . $description : '');
 $id = substr(hash('sha256', $courier . '|' . $service . '|' . $cost . '|' . $eta), 0, 16);
 return [
 'id' => $id,
 'courier' => $courier ?: $defaultCourier,
 'service' => $service ?: 'REG',
 'description' => $description,
 'label' => trim($label),
 'cost' => $cost,
 'eta' => $eta,
 ];
 }
}

if (!function_exists('shipping_api_parse_options')) {
 function shipping_api_parse_options(array $json, string $courier = ''): array
 {
 $options = [];
 $add = static function (?array $option) use (&$options): void {
 if (!$option) {
 return;
 }
 $options[$option['id']] = $option;
 };

 if (isset($json['rajaongkir']['results']) && is_array($json['rajaongkir']['results'])) {
 foreach ($json['rajaongkir']['results'] as $result) {
 $code = (string)($result['code'] ?? $courier);
 foreach ((array)($result['costs'] ?? []) as $cost) {
 $first = (array)($cost['cost'][0] ?? []);
 $add(shipping_api_normalize_option([
 'courier' => $code,
 'service' => $cost['service'] ?? '',
 'description' => $cost['description'] ?? '',
 'cost' => $first['value'] ?? 0,
 'etd' => $first['etd'] ?? '',
 ], $code));
 }
 }
 }

 $candidateLists = [];
 foreach (['data', 'result', 'results', 'costs', 'services'] as $key) {
 if (isset($json[$key]) && is_array($json[$key])) {
 $candidateLists[] = $json[$key];
 }
 }
 foreach ($candidateLists as $list) {
 foreach ($list as $item) {
 if (!is_array($item)) {
 continue;
 }
 if (isset($item['costs']) && is_array($item['costs'])) {
 $code = (string)($item['code'] ?? $item['courier'] ?? $courier);
 foreach ($item['costs'] as $cost) {
 if (!is_array($cost)) {
 continue;
 }
 $first = (array)($cost['cost'][0] ?? []);
 $add(shipping_api_normalize_option([
 'courier' => $code,
 'service' => $cost['service'] ?? '',
 'description' => $cost['description'] ?? '',
 'cost' => $first['value'] ?? ($cost['cost'] ?? 0),
 'etd' => $first['etd'] ?? ($cost['etd'] ?? ''),
 ], $code));
 }
 continue;
 }
 $add(shipping_api_normalize_option($item, $courier));
 }
 }

 $options = array_values($options);
 usort($options, static fn(array $a, array $b): int => ((int)$a['cost']) <=> ((int)$b['cost']));
 return array_slice($options, 0, 20);
 }
}

if (!function_exists('shipping_api_debug_log')) {
 function shipping_api_debug_log(string $message, array $context = []): void
 {
 $file = LOGS_PATH . '/shipping-api-' . date('Y-m') . '.log';
 $safe = $context;
 if (isset($safe['api_key'])) {
 $safe['api_key'] = '***';
 }
 @file_put_contents($file, '[' . date('c') . '] ' . $message . ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
 }
}

if (!function_exists('shipping_api_estimate')) {
 function shipping_api_estimate(array $payload, ?array $product, array $settings, array $base): array
 {
 $provider = (string)($settings['api_provider'] ?? 'rajaongkir');
 $originCode = shipping_clean((string)($base['origin_code'] ?? $settings['api_origin_code'] ?? ''), 80);
 $destinationCode = shipping_match_destination_code($payload, $settings);
 $payloadCourierRaw = trim((string)($payload['shipping_courier'] ?? ''));
 $couriers = shipping_csv_to_array($payloadCourierRaw !== '' ? $payloadCourierRaw : ($settings['api_couriers'] ?? []), 20);
 $weightGram = max(1, (int)ceil(((float)($base['billable_weight_kg'] ?? 1)) * 1000));
 $cacheKey = shipping_api_cache_key($payload, $settings, $weightGram, $originCode, $destinationCode, $couriers);
 $selectedId = shipping_clean((string)($payload['shipping_quote_option_id'] ?? ''), 40);

 $apiBase = $base;
 $apiBase['quote_source'] = 'api';
 $apiBase['provider'] = $provider;
 $apiBase['origin_code'] = $originCode;
 $apiBase['destination_code'] = $destinationCode;
 $apiBase['cache_key'] = $cacheKey;
 $apiBase['cache_status'] = 'miss';

 if (!shipping_api_ready($settings, $originCode)) {
 $apiBase['mode'] = 'api_not_ready';
 $apiBase['note'] = 'API ongkir belum lengkap. Isi API key dan kode asal provider/gudang di admin.';
 return $apiBase;
 }
 if ($destinationCode === '') {
 $apiBase['mode'] = 'api_destination_missing';
 $apiBase['note'] = 'Kode tujuan provider belum ditemukan. Tambahkan mapping kota/kecamatan di admin atau pakai fallback manual.';
 return $apiBase;
 }
 if (!$couriers) {
 $apiBase['mode'] = 'api_courier_missing';
 $apiBase['note'] = 'Daftar kurir API belum diisi.';
 return $apiBase;
 }

 $cached = shipping_cache_get($cacheKey);
 if ($cached) {
 $cached['cache_key'] = $cacheKey;
 $cached['destination_code'] = $destinationCode;
 if ($selectedId !== '') {
 foreach ((array)($cached['options'] ?? []) as $option) {
 if ((string)($option['id'] ?? '') === $selectedId) {
 $cached['selected_option_id'] = $selectedId;
 $cached = shipping_apply_api_option($cached, $option, $settings, $base);
 break;
 }
 }
 }
 return $cached;
 }

 if (shipping_rate_limit_hit($settings)) {
 $apiBase['mode'] = 'api_rate_limited';
 $apiBase['note'] = 'Terlalu banyak cek ongkir dalam sesi ini. Coba lagi nanti atau pakai konfirmasi admin.';
 return $apiBase;
 }

 $allOptions = [];
 $errors = [];
 foreach ($couriers as $courier) {
 $request = shipping_api_request_payload($payload, $settings, $weightGram, $originCode, $destinationCode, $courier);
 $response = shipping_http_request((string)$request['endpoint'], (string)$request['method'], (array)$request['headers'], (array)$request['params'], (int)$settings['api_timeout_seconds']);
 $json = json_decode((string)$response['body'], true);
 if ((int)$response['status'] >= 200 && (int)$response['status'] < 300 && is_array($json)) {
 $options = shipping_api_parse_options($json, $courier);
 foreach ($options as $option) {
 $allOptions[$option['id']] = $option;
 }
 } else {
 $errors[] = strtoupper($courier) . ': ' . ((string)($response['error'] ?? '') ?: 'HTTP ' . (string)$response['status']);
 }
 }

 $allOptions = array_values($allOptions);
 usort($allOptions, static fn(array $a, array $b): int => ((int)$a['cost']) <=> ((int)$b['cost']));
 $allOptions = array_slice($allOptions, 0, 20);

 if (!$allOptions) {
 $apiBase['mode'] = 'api_no_rate';
 $apiBase['note'] = 'API ongkir belum mengembalikan tarif. ' . ($errors ? implode(' | ', array_slice($errors, 0, 3)) : 'Cek kode asal/tujuan, kurir, dan API key.');
 shipping_cache_set($cacheKey, $apiBase, max(60, (int)$settings['api_error_cache_minutes'] * 60), !empty($settings['api_session_cache_enabled']));
 if (!empty($settings['api_debug_log'])) {
 shipping_api_debug_log('API ongkir gagal', ['provider' => $provider, 'destination_code' => $destinationCode, 'errors' => $errors]);
 }
 return $apiBase;
 }

 $result = $apiBase;
 $result['options'] = $allOptions;
 $result['mode'] = 'api_estimated';
 $result['note'] = 'Estimasi ongkir live dari provider API. Tarif final mengikuti respons provider saat pengecekan.';
 $result['cache_status'] = 'fresh';
 $selected = $allOptions[0];
 if ($selectedId !== '') {
 foreach ($allOptions as $option) {
 if ((string)$option['id'] === $selectedId) {
 $selected = $option;
 break;
 }
 }
 }
 $result = shipping_apply_api_option($result, $selected, $settings, $base);
 shipping_cache_set($cacheKey, $result, max(300, (int)$settings['api_cache_ttl_minutes'] * 60), !empty($settings['api_session_cache_enabled']));
 if (!empty($settings['api_debug_log'])) {
 shipping_api_debug_log('API ongkir sukses', ['provider' => $provider, 'destination_code' => $destinationCode, 'options' => count($allOptions)]);
 }
 return $result;
 }
}

if (!function_exists('shipping_apply_api_option')) {
 function shipping_apply_api_option(array $result, array $option, array $settings, array $base): array
 {
 $cost = (int)($option['cost'] ?? 0);
 $handling = (int)($settings['handling_fee'] ?? 0);
 $threshold = (int)($settings['free_shipping_threshold'] ?? 0);
 $subtotal = (int)($base['subtotal'] ?? 0);
 $discount = 0;
 $freeApplied = false;
 if ($threshold > 0 && $subtotal >= $threshold && $cost > 0) {
 $discount = $cost;
 $freeApplied = true;
 }
 $courier = strtolower((string)($option['courier'] ?? ''));
 $service = (string)($option['service'] ?? '');
 $label = (string)($option['label'] ?? trim(strtoupper($courier) . ' ' . $service));
 $result['rule_name'] = $label;
 $result['eta'] = (string)($option['eta'] ?? '');
 $result['shipping_cost'] = max(0, $cost);
 $result['handling_fee'] = max(0, $handling);
 $result['discount'] = max(0, $discount);
 $result['total'] = max(0, $cost + $handling - $discount);
 $result['free_shipping_applied'] = $freeApplied;
 $result['mode'] = $freeApplied ? 'api_free_shipping' : 'api_estimated';
 $result['courier'] = $courier;
 $result['service'] = $service;
 $result['service_label'] = $label;
 $result['selected_option_id'] = (string)($option['id'] ?? '');
 return $result;
 }
}

if (!function_exists('shipping_estimate')) {
 function shipping_estimate(array $payload, ?array $product = null, ?array $settings = null): array
 {
 $settings = $settings ?? shipping_settings();
 if (!$product && !empty($payload['product_slug']) && function_exists('get_product_by_slug')) {
 $product = get_product_by_slug((string)$payload['product_slug']);
 }
 $manual = shipping_manual_estimate($payload, $product, $settings);
 $mode = (string)($settings['shipping_mode'] ?? 'manual');

 if (!$manual['required'] || in_array((string)$manual['mode'], ['not_required', 'pickup_or_digital', 'product_free_shipping', 'product_shipping_confirm_admin'], true)) {
 return $manual;
 }
 if ($mode === 'manual' || empty($settings['api_enabled'])) {
 $manual['mode'] = $manual['mode'] === 'estimated' ? 'manual_estimated' : $manual['mode'];
 return $manual;
 }

 $api = shipping_api_estimate($payload, $product, $settings, $manual);
 if (in_array((string)($api['mode'] ?? ''), ['api_estimated', 'api_free_shipping'], true)) {
 return $api;
 }

 $fallbackAllowed = $mode === 'hybrid' && !empty($settings['api_fallback_to_manual']);
 if ($fallbackAllowed) {
 $manual['api_error_note'] = (string)($api['note'] ?? 'API ongkir belum tersedia.');
 $manual['quote_source'] = 'hybrid_fallback';
 $manual['provider'] = (string)($settings['api_provider'] ?? '');
 $manual['cache_status'] = (string)($api['cache_status'] ?? 'none');
 $manual['mode'] = 'hybrid_manual_fallback';
 return $manual;
 }
 return $api;
 }
}

if (!function_exists('shipping_apply_to_order')) {
 function shipping_apply_to_order(array $order): array
 {
 $product = null;
 if (!empty($order['product_slug']) && function_exists('get_product_by_slug')) {
 $product = get_product_by_slug((string)$order['product_slug']);
 }
 $estimate = shipping_estimate($order, $product);
 $order['subtotal'] = (int)$estimate['subtotal'];
 $order['shipping_origin'] = (string)$estimate['origin'];
 $order['shipping_origin_id'] = (string)($estimate['origin_id'] ?? '');
 $order['shipping_origin_city'] = (string)($estimate['origin_city'] ?? '');
 $order['shipping_origin_province'] = (string)($estimate['origin_province'] ?? '');
 $order['shipping_origin_code'] = (string)($estimate['origin_code'] ?? '');
 $order['shipping_origin_note'] = (string)($estimate['origin_note'] ?? '');
 $order['shipping_destination'] = (string)$estimate['destination'];
 $order['shipping_destination_code'] = (string)($estimate['destination_code'] ?? '');
 $order['shipping_weight_kg'] = (string)$estimate['billable_weight_kg'];
 $order['shipping_rule_name'] = (string)$estimate['rule_name'];
 $order['shipping_eta'] = (string)$estimate['eta'];
 $order['shipping_note'] = (string)$estimate['note'];
 $order['shipping_cost'] = (int)$estimate['shipping_cost'];
 $order['shipping_handling_fee'] = (int)$estimate['handling_fee'];
 $order['shipping_discount'] = (int)$estimate['discount'];
 $order['shipping_total'] = (int)$estimate['total'];
 $order['shipping_free_applied'] = !empty($estimate['free_shipping_applied']) ? 'yes' : 'no';
 $order['shipping_quote_source'] = (string)($estimate['quote_source'] ?? 'manual');
 $order['shipping_provider'] = (string)($estimate['provider'] ?? '');
 $order['shipping_courier'] = (string)($estimate['courier'] ?? '');
 $order['shipping_service'] = (string)($estimate['service'] ?? '');
 $order['shipping_service_label'] = (string)($estimate['service_label'] ?? '');
 $order['shipping_cache_status'] = (string)($estimate['cache_status'] ?? 'none');
 $order['shipping_cache_key'] = (string)($estimate['cache_key'] ?? '');
 $order['order_total_estimate'] = ((int)$order['subtotal']) + ((int)$order['shipping_total']);
 return $order;
 }
}

if (!function_exists('shipping_public_estimator_payload')) {
 function shipping_public_estimator_payload(?array $product, int $price, ?array $settings = null): array
 {
 $settings = $settings ?? shipping_settings();
 $rules = [];
 foreach ((array)($settings['rules'] ?? []) as $rule) {
 if (empty($rule['active'])) {
 continue;
 }
 $rules[] = [
 'name' => (string)($rule['name'] ?? ''),
 'keywords' => array_values((array)($rule['keywords'] ?? [])),
 'base_cost' => (int)($rule['base_cost'] ?? 0),
 'per_kg' => (int)($rule['per_kg'] ?? 0),
 'eta' => (string)($rule['eta'] ?? ''),
 'note' => (string)($rule['note'] ?? ''),
 ];
 }
 $origin = shipping_origin_for_product($product, [], $settings);
 return [
 'enabled' => !empty($settings['enabled']) && !empty($settings['checkout_preview_enabled']),
 'origin' => shipping_origin_label($origin),
 'origin_id' => (string)($origin['id'] ?? ''),
 'origin_city' => (string)($origin['city'] ?? ''),
 'origin_code_ready' => (trim((string)($origin['api_origin_code'] ?? '')) !== '' || trim((string)($settings['api_origin_code'] ?? '')) !== ''),
 'bridge_mode' => (string)($settings['shipping_mode'] ?? 'manual'),
 'api_enabled' => !empty($settings['api_enabled']) && in_array((string)($settings['shipping_mode'] ?? 'manual'), ['api', 'hybrid'], true),
 'api_ready' => shipping_api_ready($settings, trim((string)($origin['api_origin_code'] ?? '')) !== '' ? (string)$origin['api_origin_code'] : (string)($settings['api_origin_code'] ?? '')),
 'api_provider' => (string)($settings['api_provider'] ?? ''),
 'estimate_endpoint' => function_exists('url') ? url('shipping-estimate') : '/shipping-estimate',
 'price' => max(0, $price),
 'unit_weight_kg' => shipping_product_weight_kg($product, $settings),
 'round_weight_up' => !empty($settings['round_weight_up']),
 'handling_fee' => (int)($settings['handling_fee'] ?? 0),
 'free_shipping_threshold' => (int)($settings['free_shipping_threshold'] ?? 0),
 'fallback_enabled' => !empty($settings['fallback_enabled']),
 'fallback_base_cost' => (int)($settings['fallback_base_cost'] ?? 0),
 'fallback_per_kg' => (int)($settings['fallback_per_kg'] ?? 0),
 'fallback_eta' => (string)($settings['fallback_eta'] ?? ''),
 'pickup_keywords' => array_values((array)($settings['pickup_keywords'] ?? [])),
 'rules' => $rules,
 'public_note' => (string)($settings['public_note'] ?? ''),
 ];
 }
}

if (!function_exists('shipping_estimate_public_payload')) {
 function shipping_estimate_public_payload(array $estimate): array
 {
 $options = [];
 $subtotalForOptions = (int)($estimate['subtotal'] ?? 0);
 $handlingForOptions = (int)($estimate['handling_fee'] ?? 0);
 $thresholdForOptions = 0;
 $settingsForOptions = function_exists('shipping_settings') ? shipping_settings() : [];
 if (is_array($settingsForOptions)) {
 $thresholdForOptions = (int)($settingsForOptions['free_shipping_threshold'] ?? 0);
 }
 foreach ((array)($estimate['options'] ?? []) as $option) {
 $optionCost = (int)($option['cost'] ?? 0);
 $optionDiscount = ($thresholdForOptions > 0 && $subtotalForOptions >= $thresholdForOptions) ? $optionCost : 0;
 $options[] = [
 'id' => (string)($option['id'] ?? ''),
 'courier' => (string)($option['courier'] ?? ''),
 'service' => (string)($option['service'] ?? ''),
 'label' => (string)($option['label'] ?? ''),
 'cost' => $optionCost,
 'total' => max(0, $optionCost + $handlingForOptions - $optionDiscount),
 'eta' => (string)($option['eta'] ?? ''),
 ];
 }
 return [
 'required' => !empty($estimate['required']),
 'mode' => (string)($estimate['mode'] ?? ''),
 'origin' => (string)($estimate['origin'] ?? ''),
 'origin_id' => (string)($estimate['origin_id'] ?? ''),
 'origin_code' => (string)($estimate['origin_code'] ?? ''),
 'quote_source' => (string)($estimate['quote_source'] ?? 'manual'),
 'provider' => (string)($estimate['provider'] ?? ''),
 'courier' => (string)($estimate['courier'] ?? ''),
 'service' => (string)($estimate['service'] ?? ''),
 'service_label' => (string)($estimate['service_label'] ?? $estimate['rule_name'] ?? ''),
 'selected_option_id' => (string)($estimate['selected_option_id'] ?? ''),
 'rule_name' => (string)($estimate['rule_name'] ?? ''),
 'eta' => (string)($estimate['eta'] ?? ''),
 'note' => (string)($estimate['note'] ?? ''),
 'api_error_note' => (string)($estimate['api_error_note'] ?? ''),
 'subtotal' => (int)($estimate['subtotal'] ?? 0),
 'shipping_cost' => (int)($estimate['shipping_cost'] ?? 0),
 'handling_fee' => (int)($estimate['handling_fee'] ?? 0),
 'discount' => (int)($estimate['discount'] ?? 0),
 'total' => (int)($estimate['total'] ?? 0),
 'free_shipping_applied' => !empty($estimate['free_shipping_applied']),
 'billable_weight_kg' => (string)($estimate['billable_weight_kg'] ?? ''),
 'destination_code' => (string)($estimate['destination_code'] ?? ''),
 'cache_status' => (string)($estimate['cache_status'] ?? 'none'),
 'cache_key' => (string)($estimate['cache_key'] ?? ''),
 'options' => $options,
 ];
 }
}
