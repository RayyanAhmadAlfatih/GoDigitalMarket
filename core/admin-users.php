<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ADMIN USERS, ROLE & WORKFLOW MODE - U-Growth
|--------------------------------------------------------------------------
| Lightweight shared-hosting friendly admin user management.
| Shared-hosting friendly admin team accounts with role-based menu access.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('admin_users_file')) {
 function admin_users_file(): string
 {
 return STORAGE_PATH . '/admin-users.json';
 }
}

if (!function_exists('admin_users_clean')) {
 function admin_users_clean(string $value, int $max = 180): string
 {
 $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?: '');
 return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
 }
}

if (!function_exists('admin_users_email')) {
 function admin_users_email(string $email): string
 {
 $email = strtolower(trim($email));
 return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
 }
}

if (!function_exists('admin_users_username')) {
 function admin_users_username(string $username): string
 {
 $username = strtolower(trim($username));
 $username = preg_replace('/[^a-z0-9_.\-@]/', '', $username) ?: '';
 return substr($username, 0, 80);
 }
}

if (!function_exists('admin_users_roles')) {
 function admin_users_roles(): array
 {
 return [
 'owner' => [
 'label' => 'Owner / Super Admin',
 'short' => 'Owner',
 'workflow' => 'Semua Menu',
 'description' => 'Akses penuh termasuk user, keamanan, payment, backup, dan setting sensitif.',
 ],
 'admin_operasional' => [
 'label' => 'Admin Operasional',
 'short' => 'Operasional',
 'workflow' => 'Kerja Harian',
 'description' => 'Kelola website harian: produk, artikel, landing page, order, pembayaran, follow-up, dan stok.',
 ],
 'seo_specialist' => [
 'label' => 'SEO Specialist',
 'short' => 'SEO',
 'workflow' => 'SEO & Konten',
 'description' => 'Fokus artikel, SEO engine, SEO landing, internal link, content performance, dan media SEO.',
 ],
 'performance_marketer' => [
 'label' => 'Performance Marketer',
 'short' => 'Ads & Funnel',
 'workflow' => 'Marketing & Analytics',
 'description' => 'Fokus landing page, form, lead, tracking, pixel/CAPI, campaign, A/B testing, dan funnel.',
 ],
 'digital_growth_manager' => [
 'label' => 'Digital Growth Manager',
 'short' => 'Growth Manager',
 'workflow' => 'Strategi Growth',
 'description' => 'Akses luas untuk melihat strategi, laporan, action plan, funnel, SEO, marketing, dan commerce.',
 ],
 'sales_specialist' => [
 'label' => 'Sales Specialist',
 'short' => 'Sales',
 'workflow' => 'Order, Pembayaran & Closing',
 'description' => 'Fokus lead siap beli, order, invoice, bukti bayar, status pembayaran, follow-up closing, dan audit transaksi.',
 ],
 ];
 }
}

if (!function_exists('admin_users_role_aliases')) {
 function admin_users_role_aliases(): array
 {
 return [
 'finance_order_staff' => 'sales_specialist',
 'finance' => 'sales_specialist',
 'order_staff' => 'sales_specialist',
 ];
 }
}

if (!function_exists('admin_users_normalize_role')) {
 function admin_users_normalize_role(string $role): string
 {
 $role = trim($role);
 $aliases = admin_users_role_aliases();
 return (string)($aliases[$role] ?? $role);
 }
}

if (!function_exists('admin_users_role_label')) {
 function admin_users_role_label(string $role): string
 {
 $role = admin_users_normalize_role($role);
 $roles = admin_users_roles();
 return (string)($roles[$role]['label'] ?? 'Admin');
 }
}

if (!function_exists('admin_users_role_exists')) {
 function admin_users_role_exists(string $role): bool
 {
 $role = admin_users_normalize_role($role);
 return isset(admin_users_roles()[$role]);
 }
}

if (!function_exists('admin_users_default_record')) {
 function admin_users_default_record(): array
 {
 return [
 'id' => '',
 'name' => '',
 'email' => '',
 'username' => '',
 'role' => 'admin_operasional',
 'status' => 'active',
 'password_hash' => '',
 'notes' => '',
 'created_at' => date('c'),
 'updated_at' => date('c'),
 'last_login_at' => '',
 'password_changed_at' => '',
 'reset_token_hash' => '',
 'reset_token_expires_at' => '',
 ];
 }
}

if (!function_exists('admin_users_read_all')) {
 function admin_users_read_all(): array
 {
 $file = admin_users_file();
 if (!is_file($file)) {
 return [];
 }
 $data = json_decode((string)@file_get_contents($file), true);
 if (!is_array($data)) {
 return [];
 }
 $records = [];
 foreach ($data as $id => $row) {
 if (!is_array($row)) {
 continue;
 }
 $record = array_merge(admin_users_default_record(), $row);
 $record['id'] = admin_users_username((string)($record['id'] ?: $id));
 $record['email'] = admin_users_email((string)$record['email']);
 $record['username'] = admin_users_username((string)$record['username']);
 $role = admin_users_normalize_role((string)$record['role']);
 $record['role'] = admin_users_role_exists($role) ? $role : 'admin_operasional';
 $record['status'] = ((string)$record['status'] === 'inactive') ? 'inactive' : 'active';
 if ($record['id'] !== '') {
 $records[$record['id']] = $record;
 }
 }
 return $records;
 }
}

if (!function_exists('admin_users_write_all')) {
 function admin_users_write_all(array $records): bool
 {
 if (!is_dir(STORAGE_PATH)) {
 @mkdir(STORAGE_PATH, 0775, true);
 }
 ksort($records);
 return @file_put_contents(admin_users_file(), json_encode($records, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
 }
}

if (!function_exists('admin_users_make_id')) {
 function admin_users_make_id(string $email, string $username = ''): string
 {
 $base = $username !== '' ? $username : $email;
 $base = admin_users_username($base);
 if ($base === '') {
 $base = 'admin-' . substr(bin2hex(random_bytes(8)), 0, 10);
 }
 return 'au_' . substr(hash('sha256', $base), 0, 20);
 }
}

if (!function_exists('admin_users_find_by_login')) {
 function admin_users_find_by_login(string $login): ?array
 {
 $login = strtolower(trim($login));
 if ($login === '') {
 return null;
 }
 foreach (admin_users_read_all() as $row) {
 if ((string)($row['email'] ?? '') === $login || (string)($row['username'] ?? '') === $login) {
 return $row;
 }
 }
 return null;
 }
}

if (!function_exists('admin_users_find_by_id')) {
 function admin_users_find_by_id(string $id): ?array
 {
 $records = admin_users_read_all();
 return is_array($records[$id] ?? null) ? $records[$id] : null;
 }
}


if (!function_exists('admin_users_has_active_owner')) {
 function admin_users_has_active_owner(): bool
 {
 foreach (admin_users_read_all() as $row) {
 if ((string)($row['role'] ?? '') === 'owner' && (string)($row['status'] ?? 'active') === 'active' && (string)($row['password_hash'] ?? '') !== '') {
 return true;
 }
 }
 return false;
 }
}

if (!function_exists('admin_users_verify_login')) {
 function admin_users_verify_login(string $login, string $password): ?array
 {
 $user = admin_users_find_by_login($login);
 if (!$user || (string)($user['status'] ?? 'active') !== 'active') {
 return null;
 }
 $hash = (string)($user['password_hash'] ?? '');
 if ($hash === '' || !password_verify($password, $hash)) {
 return null;
 }
 $user['auth_source'] = 'admin_user';
 return $user;
 }
}

if (!function_exists('admin_users_emergency_owner')) {
 function admin_users_emergency_owner(): array
 {
 return [
 'id' => 'env_owner',
 'name' => 'Owner Utama',
 'email' => '',
 'username' => 'env-owner',
 'role' => 'owner',
 'status' => 'active',
 'auth_source' => 'env',
 'workflow' => 'Semua Menu',
 ];
 }
}

if (!function_exists('admin_users_touch_login')) {
 function admin_users_touch_login(array $user): void
 {
 if ((string)($user['auth_source'] ?? '') !== 'admin_user') {
 return;
 }
 $id = (string)($user['id'] ?? '');
 $records = admin_users_read_all();
 if ($id !== '' && isset($records[$id])) {
 $records[$id]['last_login_at'] = date('c');
 $records[$id]['updated_at'] = date('c');
 admin_users_write_all($records);
 }
 }
}

if (!function_exists('admin_users_save_record')) {
 function admin_users_save_record(array $input, ?string $existingId = null): array
 {
 $records = admin_users_read_all();
 $existing = ($existingId && isset($records[$existingId]) && is_array($records[$existingId])) ? $records[$existingId] : [];
 $email = admin_users_email((string)($input['email'] ?? $existing['email'] ?? ''));
 $username = admin_users_username((string)($input['username'] ?? $existing['username'] ?? ''));
 $role = admin_users_normalize_role((string)($input['role'] ?? $existing['role'] ?? 'admin_operasional'));
 if (!admin_users_role_exists($role)) {
 $role = 'admin_operasional';
 }
 if ($email === '' && $username === '') {
 return ['ok' => false, 'message' => 'Isi email atau username admin.'];
 }
 foreach ($records as $id => $row) {
 if ($existingId && $id === $existingId) {
 continue;
 }
 if ($email !== '' && (string)($row['email'] ?? '') === $email) {
 return ['ok' => false, 'message' => 'Email admin sudah dipakai.'];
 }
 if ($username !== '' && (string)($row['username'] ?? '') === $username) {
 return ['ok' => false, 'message' => 'Username admin sudah dipakai.'];
 }
 }
 $id = $existingId ?: admin_users_make_id($email, $username);
 $record = array_merge(admin_users_default_record(), $existing, [
 'id' => $id,
 'name' => admin_users_clean((string)($input['name'] ?? $existing['name'] ?? ''), 160),
 'email' => $email,
 'username' => $username,
 'role' => $role,
 'status' => !empty($input['status']) && (string)$input['status'] === 'inactive' ? 'inactive' : 'active',
 'notes' => admin_users_clean((string)($input['notes'] ?? $existing['notes'] ?? ''), 300),
 'created_at' => (string)($existing['created_at'] ?? date('c')),
 'updated_at' => date('c'),
 ]);
 $password = (string)($input['password'] ?? '');
 if ($password !== '') {
 if (strlen($password) < 8) {
 return ['ok' => false, 'message' => 'Password minimal 8 karakter.'];
 }
 $record['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
 $record['password_changed_at'] = date('c');
 } elseif (empty($record['password_hash'])) {
 return ['ok' => false, 'message' => 'Password wajib diisi untuk user baru.'];
 }
 $records[$id] = $record;
 if (!admin_users_write_all($records)) {
 return ['ok' => false, 'message' => 'Gagal menyimpan user admin. Pastikan folder storage writable.'];
 }
 if (function_exists('activity_log_record')) {
 activity_log_record('admin_user_saved', 'admin_user', $id, 'User admin disimpan.', ['role' => $role, 'email' => $email]);
 }
 return ['ok' => true, 'message' => 'User admin berhasil disimpan.', 'record' => $record];
 }
}

if (!function_exists('admin_users_delete_record')) {
 function admin_users_delete_record(string $id): array
 {
 $records = admin_users_read_all();
 if (!isset($records[$id])) {
 return ['ok' => false, 'message' => 'User admin tidak ditemukan.'];
 }
 unset($records[$id]);
 admin_users_write_all($records);
 if (function_exists('activity_log_record')) {
 activity_log_record('admin_user_deleted', 'admin_user', $id, 'User admin dihapus.', []);
 }
 return ['ok' => true, 'message' => 'User admin berhasil dihapus.'];
 }
}

if (!function_exists('admin_users_generate_reset')) {
 function admin_users_generate_reset(string $id): array
 {
 $records = admin_users_read_all();
 if (!isset($records[$id])) {
 return ['ok' => false, 'message' => 'User admin tidak ditemukan.'];
 }
 $token = bin2hex(random_bytes(24));
 $records[$id]['reset_token_hash'] = hash('sha256', $token);
 $records[$id]['reset_token_expires_at'] = date('c', time() + 3600);
 $records[$id]['updated_at'] = date('c');
 admin_users_write_all($records);
 return [
 'ok' => true,
 'message' => 'Link reset password dibuat. Berlaku 1 jam.',
 'reset_url' => url('admin/password-reset?token=' . rawurlencode($token) . '&id=' . rawurlencode($id)),
 ];
 }
}

if (!function_exists('admin_users_reset_password_with_token')) {
 function admin_users_reset_password_with_token(string $id, string $token, string $password): array
 {
 if (strlen($password) < 8) {
 return ['ok' => false, 'message' => 'Password minimal 8 karakter.'];
 }
 $records = admin_users_read_all();
 if (!isset($records[$id])) {
 return ['ok' => false, 'message' => 'User admin tidak ditemukan.'];
 }
 $expected = (string)($records[$id]['reset_token_hash'] ?? '');
 $expires = strtotime((string)($records[$id]['reset_token_expires_at'] ?? '')) ?: 0;
 if ($expected === '' || $expires < time() || !hash_equals($expected, hash('sha256', $token))) {
 return ['ok' => false, 'message' => 'Token reset tidak valid atau sudah kedaluwarsa.'];
 }
 $records[$id]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
 $records[$id]['password_changed_at'] = date('c');
 $records[$id]['reset_token_hash'] = '';
 $records[$id]['reset_token_expires_at'] = '';
 $records[$id]['updated_at'] = date('c');
 admin_users_write_all($records);
 return ['ok' => true, 'message' => 'Password berhasil diganti. Silakan login.'];
 }
}


if (!function_exists('admin_users_route_alias_groups')) {
 function admin_users_route_alias_groups(): array
 {
 static $groups = null;
 if ($groups !== null) {
 return $groups;
 }
 $groups = [];
 $indexPath = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__)) . '/index.php';
 if (!is_file($indexPath) || !is_readable($indexPath)) {
 return $groups;
 }
 $source = (string)@file_get_contents($indexPath);
 if ($source === '' || !preg_match('/\$routes\s*=\s*\[(.*?)\n\];/s', $source, $match)) {
 return $groups;
 }
 if (!preg_match_all("/'([^']+)'\\s*=>\\s*'([^']+)'/", $match[1], $rows, PREG_SET_ORDER)) {
 return $groups;
 }
 $byTarget = [];
 foreach ($rows as $row) {
 $route = trim((string)$row[1], '/');
 $target = trim((string)$row[2]);
 if ($route === '' || !($route === 'admin' || str_starts_with($route, 'admin/') || str_starts_with($route, 'admin-'))) {
 continue;
 }
 if (function_exists('admin_auth_is_public_path') && admin_auth_is_public_path($route)) {
 continue;
 }
 $byTarget[$target][] = $route;
 }
 foreach ($byTarget as $routes) {
 $routes = array_values(array_unique(array_map('strval', $routes)));
 if (count($routes) > 1) {
 $groups[] = $routes;
 }
 }
 return $groups;
 }
}

if (!function_exists('admin_users_expand_route_alias_permissions')) {
 function admin_users_expand_route_alias_permissions(array $sets): array
 {
 $groups = admin_users_route_alias_groups();
 foreach ($sets as $role => $allowed) {
 if (!is_array($allowed) || in_array('*', $allowed, true)) {
 continue;
 }
 $allowed = array_values(array_unique(array_map('strval', $allowed)));
 foreach ($groups as $group) {
 if (array_intersect($allowed, $group)) {
 $allowed = array_values(array_unique(array_merge($allowed, $group)));
 }
 }
 $sets[$role] = $allowed;
 }
 return $sets;
 }
}

if (!function_exists('admin_users_permission_sets')) {
 function admin_users_permission_sets(): array
 {
 $daily = [
 'admin', 'admin/brand', 'admin-brand', 'admin/business', 'admin-business', 'admin/starter-wizard', 'admin-starter-wizard', 'admin/website-starter', 'admin/onboarding-wizard',
 'admin/launch-readiness', 'admin-launch-readiness', 'admin/onboarding-assistant', 'admin-onboarding-assistant', 'admin/help-center', 'admin-help-center',
 'admin/template-content', 'admin-template-content', 'admin/navigation', 'admin-navigation', 'admin/menu', 'admin/header-footer', 'admin/homepage', 'admin-homepage', 'admin/trust-conversion', 'admin-trust-conversion',
 'admin/produk', 'admin-produk', 'admin/artikel', 'admin-artikel', 'admin/forms', 'admin-forms', 'admin/custom-forms', 'admin/form-file', 'admin-form-file', 'admin/form-checkout', 'admin-form-checkout',
 'admin/orders', 'admin-orders', 'admin/inventory', 'admin-inventory', 'admin/stok', 'admin/product-availability', 'admin/shipping', 'admin-shipping', 'admin/ongkir', 'admin/shipping-rates',
 'admin/digital-delivery', 'admin-digital-delivery', 'admin/member-area', 'admin-member-area', 'admin/subscriptions', 'admin-subscriptions', 'admin/checkout-recovery', 'admin-checkout-recovery', 'admin/followups', 'admin-followups',
 'admin/payment-settings', 'admin-payment-settings', 'admin/payment-proofs', 'admin-payment-proofs', 'admin/payment-proof-file', 'admin/payment-reminders', 'admin-payment-reminders', 'admin/transaction-audit', 'admin-transaction-audit', 'admin/order-invoice',
 'admin/landing-pages', 'admin-landing-pages', 'admin/page-builder', 'admin/landing-page-analytics', 'admin/landing-page-optimization', 'admin/offer-cta-testing', 'admin/offer-cta-lab',
 'admin/leads', 'admin-leads', 'admin/inquiries', 'admin-inquiries', 'admin/notifications', 'admin-notifications', 'admin/email-history', 'admin/riwayat-email', 'admin/commerce-insight', 'admin-commerce-insight', 'admin/reports', 'admin-reports', 'admin/report', 'admin/sales-insight', 'admin/commerce-report',
 ];
 $seo = [
 'admin', 'admin/artikel', 'admin-artikel', 'admin/produk', 'admin-produk', 'admin/landing-pages', 'admin-landing-pages', 'admin/seo-landings', 'admin-seo-landings', 'admin/seo-landing',
 'admin/universal-seo', 'admin-universal-seo', 'admin/seo-engine', 'admin/seo-audit', 'admin/seo-growth-planner', 'admin-seo-growth-planner', 'admin/seo-content-planner', 'admin-seo-content-planner',
 'admin/seo-execution-board', 'admin-seo-execution-board', 'admin/seo-publish-checklist', 'admin-seo-publish-checklist', 'admin/seo-draft-publisher', 'admin-seo-draft-publisher', 'admin/seo-link-health', 'admin-seo-link-health',
 'admin/content-performance', 'admin-content-performance', 'admin/seo-quality', 'admin-seo-quality', 'admin/media-library', 'admin-media-library', 'admin/marketing-analytics', 'admin-marketing-analytics', 'admin/seo-profit-attribution', 'admin-seo-profit-attribution',
 'admin/seo-assisted-journey', 'admin-seo-assisted-journey', 'admin/seo-money-page-optimizer', 'admin-seo-money-page-optimizer', 'admin/money-page-deployment-checklist', 'admin-money-page-deployment-checklist',
 'admin/internal-link-cta-injection', 'admin-internal-link-cta-injection', 'admin/seo-content-refresh-planner', 'admin-seo-content-refresh-planner', 'admin/commerce-insight', 'admin-commerce-insight', 'admin/reports', 'admin-reports',
 ];
 $marketing = [
 'admin', 'admin/landing-pages', 'admin-landing-pages', 'admin/landing-page-analytics', 'admin/landing-page-optimization',
 'admin/forms', 'admin-forms', 'admin/form-file', 'admin-form-file', 'admin/leads', 'admin-leads', 'admin/inquiries', 'admin-inquiries',
 'admin/marketing-analytics', 'admin-marketing-analytics', 'admin/marketing-analytics-center', 'admin/growth-map', 'admin/analytics', 'admin-analytics', 'admin/analytics-settings', 'admin/marketing-integrations', 'admin-marketing-integrations', 'admin/google-ads-tracking-test', 'admin/google-ads-test',
 'admin/profit-action-dashboard', 'admin-profit-action-dashboard', 'admin/profit-playbook', 'admin-profit-playbook', 'admin/profit-report-builder', 'admin-profit-report-builder', 'admin/profit-report', 'admin/ceo-report', 'admin/executive-report', 'admin/offer-cta-testing', 'admin-offer-cta-testing',
 'admin/cta-placement', 'admin-cta-placement', 'admin/cta-result-tracker', 'admin-cta-result-tracker', 'admin/conversion-opportunities', 'admin-conversion-opportunities',
 'admin/sales-funnel-growth', 'admin-sales-funnel-growth', 'admin/funnel-action-center', 'admin-funnel-action-center', 'admin/growth-snapshot', 'admin-growth-snapshot',
 'admin/lead-priority-scoring', 'admin/lead-quality-scoring', 'admin-lead-quality-scoring', 'admin/lead-quality', 'admin/followup-scoring', 'admin/lead-opportunity-scoring',
 'admin/seo-campaign-calendar', 'admin-seo-campaign-calendar', 'admin/growth-sprint-planner', 'admin/growth-sprint', 'admin/campaign-calendar',
 'admin/u-growth-command-center', 'admin-u-growth-command-center', 'admin/growth-command-center', 'admin/command-center', 'admin/growth-command', 'admin/growth-insights', 'admin-growth-insights', 'admin/growth', 'admin/business-insights',
  'admin/commerce-insight', 'admin-commerce-insight', 'admin/reports', 'admin-reports', 'admin/checkout-recovery', 'admin-checkout-recovery',
 ];
 $sales = [
 'admin', 'admin/orders', 'admin-orders', 'admin/order-invoice', 'admin/payment-settings', 'admin-payment-settings', 'admin/payment-proofs', 'admin-payment-proofs', 'admin/payment-proof-file',
 'admin/payment-reminders', 'admin-payment-reminders', 'admin/transaction-audit', 'admin-transaction-audit', 'admin/shipping', 'admin-shipping', 'admin/inventory', 'admin-inventory',
 'admin/commerce-insight', 'admin-commerce-insight', 'admin/reports', 'admin-reports', 'admin/report', 'admin/checkout-recovery', 'admin-checkout-recovery', 'admin/followups', 'admin-followups',
 ];
 $growth = array_values(array_unique(array_merge($daily, $seo, $marketing, $sales, [
 'admin/license-manager', 'admin-license-manager', 'admin/domain-license', 'admin/renewal-clv', 'admin-renewal-clv', 'admin/customer-lifetime-value', 'admin/renewal-upgrade', 'admin/clv',
 'admin/digital-delivery', 'admin/member-area', 'admin/subscriptions', 'admin/payment-gateway', 'admin-payment-gateway', 'admin/payment-gateway-settings',
 'admin/activity-log', 'admin-activity-log', 'admin/data-health', 'admin-data-health', 'admin/production-readiness', 'admin-production-readiness', 'admin/release-audit', 'admin-release-audit',
 ])));
 $sets = [
 'owner' => ['*'],
 'admin_operasional' => array_values(array_unique($daily)),
 'seo_specialist' => array_values(array_unique($seo)),
 'performance_marketer' => array_values(array_unique($marketing)),
 'digital_growth_manager' => $growth,
 'sales_specialist' => array_values(array_unique($sales)),
 'finance_order_staff' => array_values(array_unique($sales)), // Legacy role alias for older admin-users.json records.
 ];
 return admin_users_expand_route_alias_permissions($sets);
 }
}

if (!function_exists('admin_users_current')) {
 function admin_users_current(): array
 {
 $session = $_SESSION['admin_user'] ?? null;
 if (is_array($session)) {
 $sessionRole = (string)($session['role'] ?? '');
 $sessionRole = admin_users_normalize_role($sessionRole);
 $role = admin_users_role_exists($sessionRole) ? $sessionRole : 'admin_operasional';
 if (($session['auth_source'] ?? '') === 'env' || ($session['id'] ?? '') === 'env_owner') {
 $role = 'owner';
 }
 $session['role'] = $role;
 return $session;
 }
 if (!empty($_SESSION['admin_logged_in'])) {
 return admin_users_emergency_owner();
 }
 return [];
 }
}

if (!function_exists('admin_users_current_role')) {
 function admin_users_current_role(): string
 {
 $user = admin_users_current();
 $role = admin_users_normalize_role((string)($user['role'] ?? ''));
 return admin_users_role_exists($role) ? $role : 'admin_operasional';
 }
}

if (!function_exists('admin_users_path_normalize')) {
 function admin_users_path_normalize(string $path): string
 {
 $path = str_replace('\\', '/', trim($path));
 $path = preg_replace('#^https?://[^/]+#i', '', $path) ?? '';
 $path = trim($path, '/');
 $path = strtok($path, '?') ?: $path;
 return trim($path, '/');
 }
}

if (!function_exists('admin_users_can_access_path')) {
 function admin_users_can_access_path(string $path, ?array $user = null): bool
 {
 $path = admin_users_path_normalize($path);
 if ($path === '' || $path === 'admin') {
 return true;
 }
 if (function_exists('admin_auth_is_public_path') && admin_auth_is_public_path($path)) {
 return true;
 }
 $user = $user ?: admin_users_current();
 if (!$user) {
 return false;
 }
 $role = admin_users_normalize_role((string)($user['role'] ?? ''));
 $role = admin_users_role_exists($role) ? $role : 'admin_operasional';
 $sets = admin_users_permission_sets();
 $allowed = $sets[$role] ?? [];
 if (in_array('*', $allowed, true)) {
 return true;
 }
 if (in_array($path, $allowed, true)) {
 return true;
 }
 foreach ($allowed as $pattern) {
 $pattern = trim((string)$pattern, '/');
 if ($pattern !== '' && str_ends_with($pattern, '/*') && str_starts_with($path, rtrim($pattern, '/*') . '/')) {
 return true;
 }
 }
 return false;
 }
}

if (!function_exists('admin_users_default_path_for_role')) {
 function admin_users_default_path_for_role(string $role): string
 {
 return match ($role) {
 'admin_operasional' => 'admin/orders',
 'seo_specialist' => 'admin/universal-seo',
 'performance_marketer' => 'admin/marketing-analytics',
 'digital_growth_manager' => 'admin/commerce-insight',
 'sales_specialist', 'finance_order_staff' => 'admin/orders',
 default => 'admin/brand',
 };
 }
}

if (!function_exists('admin_users_filter_menu_groups')) {
 function admin_users_filter_menu_groups(array $groups): array
 {
 $user = admin_users_current();
 if (!$user) {
 return $groups;
 }
 $filtered = [];
 foreach ($groups as $group) {
 $items = [];
 foreach ((array)($group['items'] ?? []) as $item) {
 $href = (string)($item['href'] ?? '');
 $path = trim((string)(parse_url($href, PHP_URL_PATH) ?? ''), '/');
 $basePath = trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?? ''), '/');
 if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
 $path = substr($path, strlen($basePath) + 1);
 }
 $matches = (array)($item['match'] ?? []);
 $can = admin_users_can_access_path($path, $user);
 if (!$can) {
 foreach ($matches as $match) {
 if (admin_users_can_access_path((string)$match, $user)) {
 $can = true;
 break;
 }
 }
 }
 if ($can) {
 $items[] = $item;
 }
 }
 if ($items) {
 $group['items'] = $items;
 $filtered[] = $group;
 }
 }
 return $filtered;
 }
}

if (!function_exists('admin_users_summary')) {
 function admin_users_summary(): array
 {
 $records = admin_users_read_all();
 $summary = ['total' => count($records), 'active' => 0, 'inactive' => 0, 'roles' => []];
 foreach (array_keys(admin_users_roles()) as $role) {
 $summary['roles'][$role] = 0;
 }
 foreach ($records as $row) {
 $status = (string)($row['status'] ?? 'active');
 $summary[$status === 'inactive' ? 'inactive' : 'active']++;
 $role = admin_users_normalize_role((string)($row['role'] ?? 'admin_operasional'));
 if (!isset($summary['roles'][$role])) {
 $summary['roles'][$role] = 0;
 }
 $summary['roles'][$role]++;
 }
 return $summary;
 }
}
