<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BUYER ACCOUNT, MAGIC LOGIN & MEMBER ACCESS POLISH - U-Growth
|--------------------------------------------------------------------------
| Lightweight buyer account layer for member area, course, license, and
| subscription products. Built for shared hosting: JSON storage, optional
| password, magic-link first, no forced account creation at checkout.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('buyer_account_clean')) {
 function buyer_account_clean(string $value, int $max = 180): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 return $value === '' ? '' : (function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max));
 }
}

if (!function_exists('buyer_account_email')) {
 function buyer_account_email(string $email): string
 {
 $email = strtolower(trim($email));
 return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
 }
}

if (!function_exists('buyer_account_phone')) {
 function buyer_account_phone(string $phone): string
 {
 return preg_replace('/[^0-9+]/', '', trim($phone)) ?: '';
 }
}

if (!function_exists('buyer_account_settings_file')) {
 function buyer_account_settings_file(): string
 {
 return STORAGE_PATH . '/buyer-account-settings.json';
 }
}

if (!function_exists('buyer_account_records_file')) {
 function buyer_account_records_file(): string
 {
 return STORAGE_PATH . '/buyer-accounts.json';
 }
}

if (!function_exists('buyer_account_log_file')) {
 function buyer_account_log_file(?int $timestamp = null): string
 {
 return LOGS_PATH . '/buyer-account-' . date('Y-m', $timestamp ?: time()) . '.jsonl';
 }
}

if (!function_exists('buyer_account_default_settings')) {
 function buyer_account_default_settings(): array
 {
 return [
 'enabled' => true,
 'auto_create_after_paid' => true,
 'allow_magic_login' => true,
 'allow_password_login' => true,
 'password_optional' => true,
 'magic_token_ttl_minutes' => 60,
 'session_days' => 14,
 'show_products_page' => true,
 'login_title' => 'Masuk Member Area',
 'login_note' => 'Masuk memakai email pembelian. Password tidak wajib; link akses/magic login bisa dipakai untuk pembeli yang belum membuat password.',
 'magic_message_template' => "Halo {name}, berikut link masuk Member Area Anda:\n{magic_url}\n\nLink ini bersifat privat dan berlaku sementara.",
 ];
 }
}

if (!function_exists('buyer_account_read_settings')) {
 function buyer_account_read_settings(): array
 {
 $defaults = buyer_account_default_settings();
 $file = buyer_account_settings_file();
 if (!is_file($file)) {
 return $defaults;
 }
 $data = json_decode((string)@file_get_contents($file), true);
 if (!is_array($data)) {
 return $defaults;
 }
 $settings = array_merge($defaults, $data);
 $settings['magic_token_ttl_minutes'] = max(5, min(1440, (int)($settings['magic_token_ttl_minutes'] ?? 60)));
 $settings['session_days'] = max(1, min(365, (int)($settings['session_days'] ?? 14)));
 return $settings;
 }
}

if (!function_exists('buyer_account_write_settings')) {
 function buyer_account_write_settings(array $settings): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 $defaults = buyer_account_default_settings();
 $payload = [
 'enabled' => !empty($settings['enabled']),
 'auto_create_after_paid' => !empty($settings['auto_create_after_paid']),
 'allow_magic_login' => !empty($settings['allow_magic_login']),
 'allow_password_login' => !empty($settings['allow_password_login']),
 'password_optional' => !empty($settings['password_optional']),
 'magic_token_ttl_minutes' => max(5, min(1440, (int)($settings['magic_token_ttl_minutes'] ?? $defaults['magic_token_ttl_minutes']))),
 'session_days' => max(1, min(365, (int)($settings['session_days'] ?? $defaults['session_days']))),
 'show_products_page' => !empty($settings['show_products_page']),
 'login_title' => buyer_account_clean((string)($settings['login_title'] ?? $defaults['login_title']), 160),
 'login_note' => trim(strip_tags((string)($settings['login_note'] ?? $defaults['login_note']))),
 'magic_message_template' => trim(strip_tags((string)($settings['magic_message_template'] ?? $defaults['magic_message_template']))),
 ];
 return @file_put_contents(buyer_account_settings_file(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('buyer_account_read_all')) {
 function buyer_account_read_all(): array
 {
 $file = buyer_account_records_file();
 if (!is_file($file)) {
 return [];
 }
 $data = json_decode((string)@file_get_contents($file), true);
 return is_array($data) ? $data : [];
 }
}

if (!function_exists('buyer_account_write_all')) {
 function buyer_account_write_all(array $accounts): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 return @file_put_contents(buyer_account_records_file(), json_encode($accounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('buyer_account_log_event')) {
 function buyer_account_log_event(array $event): void
 {
 if (!is_dir(LOGS_PATH)) {
 @mkdir(LOGS_PATH, 0775, true);
 }
 $event['created_at'] = (string)($event['created_at'] ?? date('c'));
 @file_put_contents(buyer_account_log_file(), json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
 }
}

if (!function_exists('buyer_account_id_for_email')) {
 function buyer_account_id_for_email(string $email): string
 {
 return 'ba_' . substr(hash('sha256', buyer_account_email($email)), 0, 24);
 }
}

if (!function_exists('buyer_account_find_by_email')) {
 function buyer_account_find_by_email(string $email): ?array
 {
 $email = buyer_account_email($email);
 if ($email === '') {
 return null;
 }
 $accounts = buyer_account_read_all();
 $id = buyer_account_id_for_email($email);
 return is_array($accounts[$id] ?? null) ? $accounts[$id] : null;
 }
}

if (!function_exists('buyer_account_upsert_from_order')) {
 function buyer_account_upsert_from_order(array $order, array $record = []): ?array
 {
 $settings = buyer_account_read_settings();
 if (empty($settings['enabled']) || empty($settings['auto_create_after_paid'])) {
 return null;
 }
 $email = buyer_account_email((string)($order['email'] ?? $record['customer_email'] ?? ''));
 if ($email === '') {
 return null;
 }
 $id = buyer_account_id_for_email($email);
 $accounts = buyer_account_read_all();
 $existing = is_array($accounts[$id] ?? null) ? $accounts[$id] : [];
 $productSlug = buyer_account_clean((string)($order['product_slug'] ?? $record['product_slug'] ?? ''), 160);
 $orderId = buyer_account_clean((string)($order['id'] ?? $record['order_id'] ?? ''), 120);
 $accessToken = buyer_account_clean((string)($record['access_token'] ?? ''), 120);
 $products = is_array($existing['products'] ?? null) ? $existing['products'] : [];
 if ($orderId !== '') {
 $products[$orderId] = [
 'order_id' => $orderId,
 'order_ref' => (string)($record['order_ref'] ?? (function_exists('order_public_reference') ? order_public_reference($order) : ($order['ref'] ?? ''))),
 'product_slug' => $productSlug,
 'product_title' => (string)($record['product_title'] ?? $order['product_title'] ?? 'Produk Digital'),
 'access_token' => $accessToken,
 'status' => (string)($record['status'] ?? 'active'),
 'expires_at' => (string)($record['expires_at'] ?? ''),
 'updated_at' => date('c'),
 ];
 }
 $account = array_merge($existing, [
 'id' => $id,
 'email' => $email,
 'name' => buyer_account_clean((string)($order['name'] ?? $record['customer_name'] ?? $existing['name'] ?? ''), 160),
 'phone' => buyer_account_phone((string)($order['phone'] ?? $record['customer_phone'] ?? $existing['phone'] ?? '')),
 'status' => (string)($existing['status'] ?? 'active'),
 'password_hash' => (string)($existing['password_hash'] ?? ''),
 'magic_tokens' => is_array($existing['magic_tokens'] ?? null) ? $existing['magic_tokens'] : [],
 'products' => $products,
 'created_at' => (string)($existing['created_at'] ?? date('c')),
 'last_login_at' => (string)($existing['last_login_at'] ?? ''),
 'updated_at' => date('c'),
 ]);
 $accounts[$id] = $account;
 buyer_account_write_all($accounts);
 buyer_account_log_event(['type' => 'account_upsert', 'account_id' => $id, 'email' => $email, 'order_id' => $orderId]);
 return $account;
 }
}

if (!function_exists('buyer_account_set_password')) {
 function buyer_account_set_password(string $email, string $password): array
 {
 $email = buyer_account_email($email);
 if ($email === '' || strlen($password) < 8) {
 return ['ok' => false, 'message' => 'Email tidak valid atau password kurang dari 8 karakter.'];
 }
 $accounts = buyer_account_read_all();
 $id = buyer_account_id_for_email($email);
 if (!is_array($accounts[$id] ?? null)) {
 return ['ok' => false, 'message' => 'Akun buyer belum ditemukan.'];
 }
 $accounts[$id]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
 $accounts[$id]['updated_at'] = date('c');
 buyer_account_write_all($accounts);
 buyer_account_log_event(['type' => 'password_set', 'account_id' => $id, 'email' => $email]);
 return ['ok' => true, 'message' => 'Password member berhasil disimpan.'];
 }
}


if (!function_exists('buyer_account_update_profile')) {
 function buyer_account_update_profile(string $email, array $profile): array
 {
 $email = buyer_account_email($email);
 if ($email === '') {
 return ['ok' => false, 'message' => 'Email buyer tidak valid.'];
 }
 $accounts = buyer_account_read_all();
 $id = buyer_account_id_for_email($email);
 if (!is_array($accounts[$id] ?? null)) {
 return ['ok' => false, 'message' => 'Akun buyer belum ditemukan.'];
 }
 $name = buyer_account_clean((string)($profile['name'] ?? ''), 160);
 $phone = buyer_account_phone((string)($profile['phone'] ?? ''));
 if ($name === '') {
 return ['ok' => false, 'message' => 'Nama pembeli wajib diisi.'];
 }
 $accounts[$id]['name'] = $name;
 $accounts[$id]['phone'] = $phone;
 $accounts[$id]['updated_at'] = date('c');
 buyer_account_write_all($accounts);
 buyer_account_log_event(['type' => 'profile_updated', 'account_id' => $id, 'email' => $email]);
 if (function_exists('buyer_account_login')) {
 buyer_account_login($accounts[$id]);
 }
 return ['ok' => true, 'message' => 'Profil member berhasil diperbarui.', 'account' => $accounts[$id]];
 }
}

if (!function_exists('buyer_account_request_magic_link')) {
 function buyer_account_request_magic_link(string $email): array
 {
 $settings = buyer_account_read_settings();
 if (empty($settings['enabled']) || empty($settings['allow_magic_login'])) {
 return ['ok' => false, 'message' => 'Magic login sedang nonaktif.'];
 }
 $email = buyer_account_email($email);
 if ($email === '') {
 return ['ok' => false, 'message' => 'Email tidak valid.'];
 }
 $accounts = buyer_account_read_all();
 $id = buyer_account_id_for_email($email);
 if (!is_array($accounts[$id] ?? null)) {
 return ['ok' => false, 'message' => 'Akun buyer belum ditemukan. Gunakan link akses dari invoice/order terlebih dahulu.'];
 }
 $token = bin2hex(random_bytes(24));
 $expires = time() + ((int)($settings['magic_token_ttl_minutes'] ?? 60) * 60);
 $tokens = is_array($accounts[$id]['magic_tokens'] ?? null) ? $accounts[$id]['magic_tokens'] : [];
 $tokens = array_filter($tokens, static fn($row): bool => is_array($row) && (int)($row['expires_at_ts'] ?? 0) > time());
 $tokens[$token] = ['token' => $token, 'created_at' => date('c'), 'expires_at' => date('c', $expires), 'expires_at_ts' => $expires, 'used_at' => ''];
 $accounts[$id]['magic_tokens'] = $tokens;
 $accounts[$id]['updated_at'] = date('c');
 buyer_account_write_all($accounts);
 $url = url('member-area?magic=' . rawurlencode($token) . '&email=' . rawurlencode($email));
 buyer_account_log_event(['type' => 'magic_requested', 'account_id' => $id, 'email' => $email]);
 return ['ok' => true, 'message' => 'Magic link berhasil dibuat.', 'magic_url' => $url, 'token' => $token, 'account' => $accounts[$id]];
 }
}

if (!function_exists('buyer_account_login')) {
 function buyer_account_login(array $account): void
 {
 if (session_status() !== PHP_SESSION_ACTIVE) {
 @session_start();
 }
 $_SESSION['buyer_account_id'] = (string)($account['id'] ?? '');
 $_SESSION['buyer_account_email'] = (string)($account['email'] ?? '');
 }
}

if (!function_exists('buyer_account_logout')) {
 function buyer_account_logout(): void
 {
 if (session_status() !== PHP_SESSION_ACTIVE) {
 @session_start();
 }
 unset($_SESSION['buyer_account_id'], $_SESSION['buyer_account_email']);
 }
}

if (!function_exists('buyer_account_current')) {
 function buyer_account_current(): ?array
 {
 if (session_status() !== PHP_SESSION_ACTIVE) {
 @session_start();
 }
 $id = (string)($_SESSION['buyer_account_id'] ?? '');
 if ($id === '') {
 return null;
 }
 $accounts = buyer_account_read_all();
 return is_array($accounts[$id] ?? null) ? $accounts[$id] : null;
 }
}

if (!function_exists('buyer_account_login_with_magic')) {
 function buyer_account_login_with_magic(string $email, string $token): array
 {
 $email = buyer_account_email($email);
 $token = buyer_account_clean($token, 120);
 $accounts = buyer_account_read_all();
 $id = buyer_account_id_for_email($email);
 $account = is_array($accounts[$id] ?? null) ? $accounts[$id] : null;
 if (!$account || $token === '') {
 return ['ok' => false, 'message' => 'Magic link tidak valid.'];
 }
 $tokens = is_array($account['magic_tokens'] ?? null) ? $account['magic_tokens'] : [];
 $row = is_array($tokens[$token] ?? null) ? $tokens[$token] : null;
 if (!$row || (int)($row['expires_at_ts'] ?? 0) < time() || (string)($row['used_at'] ?? '') !== '') {
 return ['ok' => false, 'message' => 'Magic link sudah kedaluwarsa atau sudah dipakai.'];
 }
 $tokens[$token]['used_at'] = date('c');
 $account['magic_tokens'] = $tokens;
 $account['last_login_at'] = date('c');
 $account['updated_at'] = date('c');
 $accounts[$id] = $account;
 buyer_account_write_all($accounts);
 buyer_account_login($account);
 buyer_account_log_event(['type' => 'magic_login', 'account_id' => $id, 'email' => $email]);
 return ['ok' => true, 'message' => 'Login berhasil.', 'account' => $account];
 }
}

if (!function_exists('buyer_account_login_with_password')) {
 function buyer_account_login_with_password(string $email, string $password): array
 {
 $settings = buyer_account_read_settings();
 if (empty($settings['enabled']) || empty($settings['allow_password_login'])) {
 return ['ok' => false, 'message' => 'Login password sedang nonaktif.'];
 }
 $account = buyer_account_find_by_email($email);
 if (!$account || (string)($account['password_hash'] ?? '') === '' || !password_verify($password, (string)$account['password_hash'])) {
 return ['ok' => false, 'message' => 'Email atau password belum sesuai.'];
 }
 $accounts = buyer_account_read_all();
 $account['last_login_at'] = date('c');
 $account['updated_at'] = date('c');
 $accounts[(string)$account['id']] = $account;
 buyer_account_write_all($accounts);
 buyer_account_login($account);
 buyer_account_log_event(['type' => 'password_login', 'account_id' => (string)$account['id'], 'email' => (string)$account['email']]);
 return ['ok' => true, 'message' => 'Login berhasil.', 'account' => $account];
 }
}

if (!function_exists('buyer_account_records')) {
 function buyer_account_records(array $account): array
 {
 $email = buyer_account_email((string)($account['email'] ?? ''));
 if ($email === '' || !function_exists('member_access_records_by_email')) {
 return [];
 }
 return member_access_records_by_email($email, '');
 }
}

if (!function_exists('buyer_account_summary')) {
 function buyer_account_summary(): array
 {
 $accounts = buyer_account_read_all();
 $withPassword = 0;
 $products = 0;
 $recent = [];
 foreach ($accounts as $account) {
 if (!is_array($account)) {
 continue;
 }
 if ((string)($account['password_hash'] ?? '') !== '') {
 $withPassword++;
 }
 $products += count((array)($account['products'] ?? []));
 $recent[] = $account;
 }
 usort($recent, static fn($a, $b): int => strcmp((string)($b['updated_at'] ?? ''), (string)($a['updated_at'] ?? '')));
 return ['accounts' => count($accounts), 'with_password' => $withPassword, 'products' => $products, 'recent' => array_slice($recent, 0, 8)];
 }
}
