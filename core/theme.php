<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| BRAND IDENTITY & THEME ENGINE
|--------------------------------------------------------------------------
| Lightweight file-based brand settings for UMKM owners.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
 exit('Direct access not allowed.');
}

if (!function_exists('theme_storage_file')) {
 function theme_storage_file(): string
 {
 return STORAGE_PATH . '/theme-settings.json';
 }
}

if (!function_exists('theme_default_settings')) {
 function theme_default_settings(): array
 {
 return function_exists('app_theme_default_settings')
 ? app_theme_default_settings()
 : [];
 }
}

if (!function_exists('theme_settings')) {
 function theme_settings(): array
 {
 $defaults = theme_default_settings();
 $settings = defined('SITE_THEME_SETTINGS') && is_array(SITE_THEME_SETTINGS)
 ? SITE_THEME_SETTINGS
 : [];

 return array_merge($defaults, $settings);
 }
}

if (!function_exists('theme_setting')) {
 function theme_setting(string $key, mixed $default = null): mixed
 {
 $settings = theme_settings();

 return $settings[$key] ?? $default;
 }
}

if (!function_exists('theme_presets')) {
 function theme_presets(): array
 {
 return [
 'hijau-fresh' => [
 'label' => 'Hijau Fresh',
 'description' => 'Cocok untuk bisnis natural, kuliner, kesehatan, dan toko harian.',
 'primary_color' => '#166534',
 'primary_dark_color' => '#052e16',
 'secondary_color' => '#facc15',
 'secondary_light_color' => '#fde68a',
 'button_color' => '#15803d',
 'button_text_color' => '#ffffff',
 'header_color' => '#166534',
 'footer_color' => '#052e16',
 'background_color' => '#f6f8f1',
 'text_color' => '#172417',
 'muted_text_color' => '#647067',
 'border_color' => '#e4e9df',
 'admin_primary_color' => '#0f766e',
 'admin_primary_dark_color' => '#115e59',
 'admin_soft_color' => '#ecfeff',
 ],
 'biru-profesional' => [
 'label' => 'Biru Profesional',
 'description' => 'Cocok untuk jasa, konsultan, edukasi, teknologi, dan B2B.',
 'primary_color' => '#1d4ed8',
 'primary_dark_color' => '#1e3a8a',
 'secondary_color' => '#38bdf8',
 'secondary_light_color' => '#bae6fd',
 'button_color' => '#2563eb',
 'button_text_color' => '#ffffff',
 'header_color' => '#1d4ed8',
 'footer_color' => '#172554',
 'background_color' => '#f0f7ff',
 'text_color' => '#0f172a',
 'muted_text_color' => '#64748b',
 'border_color' => '#dbeafe',
 'admin_primary_color' => '#2563eb',
 'admin_primary_dark_color' => '#1d4ed8',
 'admin_soft_color' => '#eff6ff',
 ],
 'merah-enerjik' => [
 'label' => 'Merah Enerjik',
 'description' => 'Cocok untuk promo, kuliner, event, dan brand yang ingin terlihat berani.',
 'primary_color' => '#b91c1c',
 'primary_dark_color' => '#7f1d1d',
 'secondary_color' => '#f97316',
 'secondary_light_color' => '#fed7aa',
 'button_color' => '#dc2626',
 'button_text_color' => '#ffffff',
 'header_color' => '#b91c1c',
 'footer_color' => '#450a0a',
 'background_color' => '#fff7ed',
 'text_color' => '#1f1717',
 'muted_text_color' => '#765b5b',
 'border_color' => '#fed7aa',
 'admin_primary_color' => '#dc2626',
 'admin_primary_dark_color' => '#991b1b',
 'admin_soft_color' => '#fff1f2',
 ],
 'ungu-modern' => [
 'label' => 'Ungu Modern',
 'description' => 'Cocok untuk beauty, digital product, kreatif, dan brand modern.',
 'primary_color' => '#7c3aed',
 'primary_dark_color' => '#4c1d95',
 'secondary_color' => '#f0abfc',
 'secondary_light_color' => '#fae8ff',
 'button_color' => '#8b5cf6',
 'button_text_color' => '#ffffff',
 'header_color' => '#6d28d9',
 'footer_color' => '#2e1065',
 'background_color' => '#faf5ff',
 'text_color' => '#1e1630',
 'muted_text_color' => '#6b5b7d',
 'border_color' => '#e9d5ff',
 'admin_primary_color' => '#7c3aed',
 'admin_primary_dark_color' => '#6d28d9',
 'admin_soft_color' => '#f5f3ff',
 ],
 'coklat-natural' => [
 'label' => 'Coklat Natural',
 'description' => 'Cocok untuk kopi, makanan, kerajinan, fashion natural, dan produk lokal.',
 'primary_color' => '#92400e',
 'primary_dark_color' => '#431407',
 'secondary_color' => '#d97706',
 'secondary_light_color' => '#fde68a',
 'button_color' => '#b45309',
 'button_text_color' => '#ffffff',
 'header_color' => '#78350f',
 'footer_color' => '#2c1204',
 'background_color' => '#fffbeb',
 'text_color' => '#23180f',
 'muted_text_color' => '#756556',
 'border_color' => '#fcd34d',
 'admin_primary_color' => '#b45309',
 'admin_primary_dark_color' => '#92400e',
 'admin_soft_color' => '#fffbeb',
 ],
 'hitam-premium' => [
 'label' => 'Hitam Premium',
 'description' => 'Cocok untuk brand premium, fashion, studio, dan produk eksklusif.',
 'primary_color' => '#111827',
 'primary_dark_color' => '#030712',
 'secondary_color' => '#f59e0b',
 'secondary_light_color' => '#fde68a',
 'button_color' => '#111827',
 'button_text_color' => '#ffffff',
 'header_color' => '#111827',
 'footer_color' => '#030712',
 'background_color' => '#f8fafc',
 'text_color' => '#111827',
 'muted_text_color' => '#64748b',
 'border_color' => '#e5e7eb',
 'admin_primary_color' => '#111827',
 'admin_primary_dark_color' => '#030712',
 'admin_soft_color' => '#f3f4f6',
 ],
 ];
 }
}

if (!function_exists('theme_color_keys')) {
 function theme_color_keys(): array
 {
 return [
 'primary_color' => 'Warna utama',
 'primary_dark_color' => 'Warna utama gelap',
 'secondary_color' => 'Warna aksen',
 'secondary_light_color' => 'Warna aksen lembut',
 'button_color' => 'Warna tombol',
 'button_text_color' => 'Warna teks tombol',
 'header_color' => 'Warna header',
 'footer_color' => 'Warna footer',
 'background_color' => 'Warna latar website',
 'text_color' => 'Warna teks utama',
 'muted_text_color' => 'Warna teks pendukung',
 'border_color' => 'Warna garis/border',
 'admin_primary_color' => 'Warna utama dashboard',
 'admin_primary_dark_color' => 'Warna gelap dashboard',
 'admin_soft_color' => 'Warna lembut dashboard',
 ];
 }
}

if (!function_exists('theme_sanitize_hex')) {
 function theme_sanitize_hex(mixed $value, string $fallback): string
 {
 return function_exists('app_theme_sanitize_hex')
 ? app_theme_sanitize_hex($value, $fallback)
 : $fallback;
 }
}

if (!function_exists('theme_clean_text')) {
 function theme_clean_text(mixed $value, int $limit = 180): string
 {
 $value = trim(strip_tags((string)$value));
 $value = preg_replace('/\s+/', ' ', $value) ?: '';

 if (function_exists('mb_substr')) {
 return mb_substr($value, 0, $limit);
 }

 return substr($value, 0, $limit);
 }
}

if (!function_exists('theme_clean_url')) {
 function theme_clean_url(mixed $value, string $fallback = ''): string
 {
 $value = trim((string)$value);

 if ($value === '') {
 return $fallback;
 }

 if (filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)) {
 return $value;
 }

 $value = ltrim($value, '/');

 if (preg_match('#^((assets/)?(images|uploads))/[a-zA-Z0-9._/\-]+$#', $value)) {
 return $value;
 }

 return $fallback;
 }
}

if (!function_exists('theme_asset_url')) {
 function theme_asset_url(?string $value, string $fallback): string
 {
 $value = trim((string)$value);

 if ($value === '') {
 $value = $fallback;
 }

 if (filter_var($value, FILTER_VALIDATE_URL)) {
 return $value;
 }

 $value = ltrim($value, '/');

 if (str_starts_with($value, 'assets/')) {
 return rtrim(SITE_URL, '/') . '/' . $value;
 }

 return asset($value);
 }
}

if (!function_exists('theme_logo_url')) {
 function theme_logo_url(): string
 {
 return theme_asset_url((string)theme_setting('logo_url', 'images/logo.png'), 'images/logo.png');
 }
}

if (!function_exists('theme_favicon_url')) {
 function theme_favicon_url(): string
 {
 return theme_asset_url((string)theme_setting('favicon_url', 'images/favicon.png'), 'images/favicon.png');
 }
}

if (!function_exists('theme_og_image_url')) {
 function theme_og_image_url(): string
 {
 return theme_asset_url((string)theme_setting('og_image_url', 'images/og-default.jpg'), 'images/og-default.jpg');
 }
}

if (!function_exists('theme_social_links')) {
 function theme_social_links(): array
 {
 $links = [
 'Facebook' => (string)theme_setting('facebook_url', ''),
 'Instagram' => (string)theme_setting('instagram_url', ''),
 'YouTube' => (string)theme_setting('youtube_url', ''),
 'TikTok' => (string)theme_setting('tiktok_url', ''),
 'LinkedIn' => (string)theme_setting('linkedin_url', ''),
 ];

 return array_filter($links, static fn(string $url): bool => $url !== '');
 }
}

if (!function_exists('theme_apply_preset')) {
 function theme_apply_preset(array $settings, string $presetKey): array
 {
 $presets = theme_presets();

 if (!isset($presets[$presetKey])) {
 return $settings;
 }

 foreach (theme_color_keys() as $key => $label) {
 if (isset($presets[$presetKey][$key])) {
 $settings[$key] = $presets[$presetKey][$key];
 }
 }

 $settings['theme_preset'] = $presetKey;

 return $settings;
 }
}

if (!function_exists('theme_handle_upload')) {
 function theme_handle_upload(string $field, string $baseName, string $current): string
 {
 if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file((string)$_FILES[$field]['tmp_name'])) {
 return $current;
 }

 try {
 $uploaded = image_upload_to_webp(
 $_FILES[$field],
 'brand',
 $baseName,
 [
 'prefix' => 'brand',
 'max_size' => 4 * 1024 * 1024,
 'max_width' => $field === 'favicon_file' ? 512 : 1200,
 'max_height' => $field === 'favicon_file' ? 512 : 800,
 'quality' => 82,
 ]
 );

 return $uploaded ?: $current;
 } catch (Throwable $e) {
 throw new RuntimeException($field === 'favicon_file'
 ? 'Favicon gagal diupload: ' . $e->getMessage()
 : 'Logo gagal diupload: ' . $e->getMessage());
 }
 }
}

if (!function_exists('theme_save_settings')) {
 function theme_save_settings(array $input): array
 {
 $defaults = theme_default_settings();
 $current = theme_settings();
 $settings = array_merge($defaults, $current);
 $preset = theme_clean_text($input['theme_preset'] ?? ($settings['theme_preset'] ?? 'biru-profesional'), 60);

 if (isset(theme_presets()[$preset])) {
 $settings = theme_apply_preset($settings, $preset);
 }

 $textFields = [
 'business_name' => 90,
 'tagline' => 180,
 'description' => 240,
 'keywords' => 240,
 'email' => 120,
 'phone' => 40,
 'whatsapp' => 40,
 'address' => 220,
 'login_badge' => 80,
 'login_title' => 120,
 'login_tagline' => 180,
 'login_description' => 260,
 'login_button_text' => 80,
 'login_footer_note' => 160,
 ];

 foreach ($textFields as $field => $limit) {
 $settings[$field] = theme_clean_text($input[$field] ?? ($settings[$field] ?? ''), $limit);
 }

 $settings['email'] = filter_var($settings['email'], FILTER_VALIDATE_EMAIL)
 ? $settings['email']
 : (string)$defaults['email'];
 $settings['phone'] = preg_replace('/[^0-9+]/', '', (string)$settings['phone']) ?: (string)$defaults['phone'];
 $settings['whatsapp'] = preg_replace('/\D+/', '', (string)$settings['whatsapp']) ?: (string)$defaults['whatsapp'];

 foreach (['facebook_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'linkedin_url'] as $field) {
 $settings[$field] = theme_clean_url($input[$field] ?? ($settings[$field] ?? ''), '');
 }

 foreach (['logo_url', 'favicon_url', 'og_image_url'] as $field) {
 $settings[$field] = theme_clean_url($input[$field] ?? ($settings[$field] ?? ''), (string)$defaults[$field]);
 }

 foreach (['login_logo_url', 'login_background_image'] as $field) {
 $settings[$field] = theme_clean_url($input[$field] ?? ($settings[$field] ?? ''), (string)($defaults[$field] ?? ''));
 }

 foreach ([
 'login_branding_enabled',
 'motion_effects_enabled',
 'motion_public_enabled',
 'motion_admin_enabled',
 'motion_landing_enabled',
 ] as $field) {
 $settings[$field] = !empty($input[$field]) ? '1' : '0';
 }

 $loginLayout = theme_clean_text($input['login_layout'] ?? ($settings['login_layout'] ?? 'split'), 40);
 $settings['login_layout'] = in_array($loginLayout, ['split', 'center', 'card'], true) ? $loginLayout : 'split';

 $loginBackgroundStyle = theme_clean_text($input['login_background_style'] ?? ($settings['login_background_style'] ?? 'soft-gradient'), 50);
 $settings['login_background_style'] = in_array($loginBackgroundStyle, ['soft-gradient', 'clean', 'brand-gradient', 'image'], true) ? $loginBackgroundStyle : 'soft-gradient';

 $motionIntensity = theme_clean_text($input['motion_intensity'] ?? ($settings['motion_intensity'] ?? 'soft'), 40);
 $settings['motion_intensity'] = in_array($motionIntensity, ['soft', 'medium'], true) ? $motionIntensity : 'soft';

 foreach (theme_color_keys() as $field => $label) {
 $settings[$field] = theme_sanitize_hex($input[$field] ?? ($settings[$field] ?? ''), (string)$defaults[$field]);
 }

 $settings['logo_url'] = theme_handle_upload('logo_file', $settings['business_name'] ?: 'logo-brand', (string)$settings['logo_url']);
 $settings['favicon_url'] = theme_handle_upload('favicon_file', $settings['business_name'] ?: 'favicon-brand', (string)$settings['favicon_url']);
 $settings['og_image_url'] = theme_handle_upload('og_image_file', $settings['business_name'] ?: 'og-image-brand', (string)$settings['og_image_url']);
 $settings['login_logo_url'] = theme_handle_upload('login_logo_file', ($settings['business_name'] ?: 'brand') . '-login-logo', (string)($settings['login_logo_url'] ?? ''));
 $settings['login_background_image'] = theme_handle_upload('login_background_file', ($settings['business_name'] ?: 'brand') . '-login-bg', (string)($settings['login_background_image'] ?? ''));
 $settings['updated_at'] = date(DATE_ATOM);

 if (!is_dir(STORAGE_PATH) && !mkdir(STORAGE_PATH, 0775, true) && !is_dir(STORAGE_PATH)) {
 throw new RuntimeException('Folder penyimpanan belum bisa dibuat.');
 }

 $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

 if ($json === false || file_put_contents(theme_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
 throw new RuntimeException('Pengaturan brand gagal disimpan. Cek permission folder storage.');
 }

 @chmod(theme_storage_file(), 0644);

 if (function_exists('activity_log_record')) {
 activity_log_record('update', 'theme', null, 'Pengaturan brand dan warna diperbarui.', ['preset' => $preset]);
 }

 return $settings;
 }
}

if (!function_exists('theme_reset_settings')) {
 function theme_reset_settings(): void
 {
 $json = json_encode(theme_default_settings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

 if ($json === false || file_put_contents(theme_storage_file(), $json . PHP_EOL, LOCK_EX) === false) {
 throw new RuntimeException('Reset pengaturan gagal. Cek permission folder storage.');
 }

 @chmod(theme_storage_file(), 0644);
 }
}

if (!function_exists('theme_color')) {
 function theme_color(string $key, string $fallback = '#0f172a'): string
 {
 return theme_sanitize_hex(theme_setting($key, $fallback), $fallback);
 }
}

if (!function_exists('theme_brand_initials')) {
 function theme_brand_initials(): string
 {
 $name = trim((string)theme_setting('business_name', SITE_NAME));
 $words = preg_split('/\s+/', $name) ?: [];
 $initials = '';

 foreach ($words as $word) {
 $clean = preg_replace('/[^a-zA-Z0-9]/', '', $word) ?: '';
 if ($clean !== '') {
 $initials .= strtoupper(substr($clean, 0, 1));
 }
 if (strlen($initials) >= 2) {
 break;
 }
 }

 return $initials !== '' ? $initials : 'UC';
 }
}

if (!function_exists('theme_bool_setting')) {
 function theme_bool_setting(string $key, bool $default = false): bool
 {
 $value = theme_setting($key, $default ? '1' : '0');

 if (is_bool($value)) {
 return $value;
 }

 return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
 }
}

if (!function_exists('theme_motion_enabled')) {
 function theme_motion_enabled(string $area = 'public'): bool
 {
 if (!theme_bool_setting('motion_effects_enabled', true)) {
 return false;
 }

 $area = strtolower(trim($area));
 if ($area === 'admin') {
 return theme_bool_setting('motion_admin_enabled', true);
 }
 if ($area === 'landing') {
 return theme_bool_setting('motion_landing_enabled', true);
 }

 return theme_bool_setting('motion_public_enabled', true);
 }
}

if (!function_exists('theme_motion_intensity')) {
 function theme_motion_intensity(): string
 {
 $value = strtolower(trim((string)theme_setting('motion_intensity', 'soft')));
 return in_array($value, ['soft', 'medium'], true) ? $value : 'soft';
 }
}

if (!function_exists('theme_body_motion_classes')) {
 function theme_body_motion_classes(string $area = 'public'): string
 {
 $classes = [];
 $areas = preg_split('/\s+/', strtolower(trim($area))) ?: [];
 $publicEnabled = in_array('public', $areas, true) && theme_motion_enabled('public');
 $adminEnabled = in_array('admin', $areas, true) && theme_motion_enabled('admin');
 $landingEnabled = in_array('landing', $areas, true) && theme_motion_enabled('landing');

 if ($publicEnabled || $adminEnabled || $landingEnabled) {
 $classes[] = 'motion-enabled';
 $classes[] = 'motion-intensity-' . theme_motion_intensity();
 }
 if ($publicEnabled) {
 $classes[] = 'motion-public-enabled';
 }
 if ($adminEnabled) {
 $classes[] = 'motion-admin-enabled';
 }
 if ($landingEnabled) {
 $classes[] = 'motion-landing-enabled';
 }

 return implode(' ', $classes);
 }
}

if (!function_exists('theme_login_logo_url')) {
 function theme_login_logo_url(): string
 {
 $custom = trim((string)theme_setting('login_logo_url', ''));
 return $custom !== '' ? theme_asset_url($custom, '') : theme_logo_url();
 }
}

if (!function_exists('theme_login_background_url')) {
 function theme_login_background_url(): string
 {
 $custom = trim((string)theme_setting('login_background_image', ''));
 return $custom !== '' ? theme_asset_url($custom, '') : '';
 }
}

if (!function_exists('theme_render_style')) {
 function theme_render_style(): void
 {
 $vars = [
 '--primary' => theme_color('primary_color', '#1d4ed8'),
 '--primary-dark' => theme_color('primary_dark_color', '#1e3a8a'),
 '--secondary' => theme_color('secondary_color', '#38bdf8'),
 '--secondary-light' => theme_color('secondary_light_color', '#bae6fd'),
 '--button' => theme_color('button_color', '#2563eb'),
 '--button-text' => theme_color('button_text_color', '#ffffff'),
 '--header' => theme_color('header_color', '#1d4ed8'),
 '--footer' => theme_color('footer_color', '#172554'),
 '--bg' => theme_color('background_color', '#f0f7ff'),
 '--text' => theme_color('text_color', '#0f172a'),
 '--text-light' => theme_color('muted_text_color', '#64748b'),
 '--border' => theme_color('border_color', '#dbeafe'),
 '--admin-primary' => theme_color('admin_primary_color', '#2563eb'),
 '--admin-primary-dark' => theme_color('admin_primary_dark_color', '#1d4ed8'),
 '--admin-soft' => theme_color('admin_soft_color', '#eff6ff'),
 ];

 echo "\n<style id=\"brand-theme-vars\">\n:root{\n";
 foreach ($vars as $name => $value) {
 echo ' ' . $name . ':' . esc($value) . ";\n";
 }
 echo "}\n";
 ?>
/* Brand theme runtime overrides.
 Keep this layer after the main CSS so homepage, forms, catalog, admin,
 and older blocks follow the colors selected in Dashboard > Brand & Warna. */
body{background:var(--bg);color:var(--text);}
.topbar{background:var(--primary-dark)!important;}
.navbar{background:var(--header)!important;}
.site-footer{background:var(--footer)!important;}
.site-header{background:#ffffff;}
.nav-menu a::after{background:var(--secondary)!important;}
.header-search-form button,.floating-wa,.sticky-mobile-cta a{background:var(--button)!important;color:var(--button-text)!important;}
.btn,.button,.cta-button,.hero-button,.lp-btn,.admin-btn--primary{background:var(--button)!important;color:var(--button-text)!important;border-color:var(--button)!important;}
.cta,.btn-soft,.lp-btn--soft{background:var(--secondary)!important;color:var(--text)!important;border-color:var(--secondary)!important;}
.btn:hover,.button:hover,.cta-button:hover,.hero-button:hover,.lp-btn:hover,.header-search-form button:hover,.floating-wa:hover{filter:brightness(.96);}
.hero,.mini-hero{
 background:
 radial-gradient(circle at 22% 28%,color-mix(in srgb,var(--secondary) 20%,transparent),transparent 24%),
 radial-gradient(circle at 78% 22%,color-mix(in srgb,var(--primary) 25%,transparent),transparent 28%),
 linear-gradient(135deg,color-mix(in srgb,var(--primary-dark) 92%,#000000) 0%,var(--primary) 48%,color-mix(in srgb,var(--secondary) 18%,var(--primary-dark)) 100%)!important;
}
.hero::before,.hero::after,.mini-hero::before{content:'';position:absolute;pointer-events:none;border-radius:999px;opacity:.16;background:#ffffff;}
.hero::before{width:280px;height:280px;left:18%;top:18%;}
.hero::after{width:540px;height:30px;right:16%;top:38%;}
.overlay{position:relative;z-index:1;}
.hero-badge,.dynamic-eyebrow,.dynamic-mini-label,.lp-eyebrow{background:color-mix(in srgb,var(--secondary) 22%,#ffffff)!important;color:var(--primary-dark)!important;border-color:color-mix(in srgb,var(--secondary) 38%,#ffffff)!important;}
.breadcrumb a{color:var(--primary)!important;}
.breadcrumb span{color:var(--text-light)!important;}
.mini-hero .breadcrumb a,.mini-hero .breadcrumb span,.hero .breadcrumb a,.hero .breadcrumb span{color:rgba(255,255,255,.84)!important;}
.mini-hero .breadcrumb span:last-child,.hero .breadcrumb span:last-child{color:#ffffff!important;font-weight:700;}
.mini-hero .breadcrumb a:hover,.hero .breadcrumb a:hover{color:#ffffff!important;}
.trustbar{background:var(--primary-dark)!important;}
.trust-item h3{color:var(--secondary)!important;}
.section.alt,.alt{background:color-mix(in srgb,var(--bg) 82%,#ffffff)!important;}
.title,.section-head h2,.card-content h3,.product-content h3,.article-category-tile strong{color:var(--primary-dark)!important;}
.card,.product,.product-card,.article,.article-card,.inquiry-card{border-color:var(--border)!important;}
.price,.product-price,.article-latest-row span{color:var(--primary)!important;}
.badge{border-left-color:var(--secondary)!important;}
.dynamic-overview-section,.dynamic-area-layanan-section,.landing-page-breadcrumb-hero{background:linear-gradient(180deg,color-mix(in srgb,var(--bg) 92%,#ffffff),#ffffff)!important;}
.dynamic-section-head .title,.dynamic-block-head h2,.dynamic-block-head h3,.dynamic-card-grid .product-content h3,.dynamic-card-grid .product-card-content h3,.lp-card h3{color:var(--primary-dark)!important;}
.dynamic-more-link,.dynamic-area-layanan-card,.lp-final-cta{background:linear-gradient(135deg,var(--primary-dark),var(--primary))!important;}
.dynamic-area-layanan-meta a{background:var(--secondary)!important;color:var(--text)!important;}
.dynamic-chip,.article-toc a{color:var(--primary-dark)!important;background:color-mix(in srgb,var(--bg) 70%,#ffffff)!important;border-color:var(--border)!important;}
.article-category-tile:hover,.article-category-tile.active{border-color:color-mix(in srgb,var(--primary) 35%,#ffffff)!important;background:color-mix(in srgb,var(--bg) 76%,#ffffff)!important;}
.checkout-hero--template,.order-success-hero--template{background:radial-gradient(circle at 18% 20%,color-mix(in srgb,var(--secondary) 22%,transparent),transparent 28%),radial-gradient(circle at 82% 28%,rgba(255,255,255,.14),transparent 30%),linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 62%,#0f172a 100%)!important;}
.checkout-hero--template h1,.checkout-hero--template p,.order-success-hero--template h1,.order-success-hero--template p{color:#ffffff!important;text-shadow:0 12px 34px rgba(15,23,42,.34)!important;}
.lp-hero{background:linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 52%,#ffffff 52%,#ffffff 100%)!important;}
.lp-price{color:var(--primary)!important;}
.landing-mini-footer:not(.landing-mini-footer--custom){background:var(--footer)!important;}
.admin-shell{background:linear-gradient(180deg,var(--admin-soft) 0%,var(--admin-bg) 260px);}
.admin-hero{background:radial-gradient(circle at 18% 10%,color-mix(in srgb,var(--secondary) 32%,transparent),transparent 28%),linear-gradient(135deg,var(--admin-primary-dark) 0%,var(--admin-primary) 45%,#0f172a 100%)!important;}
.admin-badge,.admin-sidebar__logo{background:color-mix(in srgb,var(--admin-primary) 14%,#ffffff)!important;color:var(--admin-primary-dark)!important;}
.admin-sidebar{background:linear-gradient(180deg,var(--admin-primary-dark),#0f172a)!important;}
.admin-login-body{background:radial-gradient(circle at 10% 10%,color-mix(in srgb,var(--admin-primary) 18%,transparent),transparent 30%),radial-gradient(circle at 90% 20%,color-mix(in srgb,var(--secondary) 14%,transparent),transparent 30%),linear-gradient(135deg,#f8fafc 0%,var(--admin-soft) 100%)!important;}
.admin-login-panel__copy{background:linear-gradient(135deg,var(--admin-primary-dark) 0%,var(--admin-primary) 52%,#0f172a 100%)!important;}
.admin-login-panel__copy,.admin-login-card--standalone{border-color:color-mix(in srgb,var(--admin-primary) 18%,#ffffff)!important;}
.admin-sidebar__nav a.is-active{background:#ffffff!important;color:var(--admin-primary-dark)!important;}
.admin-sidebar__nav a:hover{background:rgba(255,255,255,.12)!important;}
.admin-btn--soft,.admin-btn--light{color:var(--admin-primary-dark)!important;border-color:color-mix(in srgb,var(--admin-primary) 18%,#ffffff)!important;}
.theme-brand-preview{background:linear-gradient(135deg,var(--header),var(--primary-dark))!important;color:#fff;}

/* full theme coverage for older public blocks. */
.product-detail-meta a,.product-detail-content a,.article-content a,.article-toc a,.footer-social a,.site-footer a:hover{color:var(--secondary)!important;}
.product-detail-button,.marketplace-results .product-card .product-detail-button,.cards3 .product-card .product-detail-button,.cards4 .product-card .product-detail-button{background:linear-gradient(135deg,var(--button),var(--primary))!important;border-color:var(--button)!important;color:var(--button-text)!important;}
.product-detail-button::before,.marketplace-results .product-card .product-detail-button::before,.cards3 .product-card .product-detail-button::before,.cards4 .product-card .product-detail-button::before{background:color-mix(in srgb,var(--secondary) 26%,#ffffff)!important;}
.dynamic-landing-hero .btn,.dynamic-landing-hero .cta,.dynamic-hero-actions .btn,.dynamic-hero-actions .cta{background:var(--button)!important;color:var(--button-text)!important;border-color:var(--button)!important;}
.dynamic-landing-hero .btn:hover,.dynamic-landing-hero .cta:hover{background:color-mix(in srgb,var(--button) 86%,#000000)!important;color:var(--button-text)!important;}
.dynamic-check-list li:before,.landing-package-card h3,.landing-step-card h3,.landing-location-card strong,.catalog-quick-card strong,.catalog-buyer-step strong,.catalog-local-item strong{color:var(--primary)!important;}
.dynamic-chip,.landing-related-links a,.landing-link-grid a{background:color-mix(in srgb,var(--bg) 72%,#ffffff)!important;border-color:var(--border)!important;color:var(--primary-dark)!important;}
.dynamic-chip:hover,.landing-related-links a:hover,.landing-link-grid a:hover,.dynamic-list-item:hover,.catalog-quick-card:hover,.catalog-local-item:hover,.landing-location-card:hover{background:color-mix(in srgb,var(--secondary-light) 55%,#ffffff)!important;color:var(--primary-dark)!important;}
.landing-step-card span,.catalog-buyer-step span{background:var(--button)!important;color:var(--button-text)!important;}
.dynamic-panel,.dynamic-list-item,.dynamic-faq-card,.landing-package-card,.landing-step-card,.landing-location-card,.catalog-buyer-step,.catalog-local-item{border-color:var(--border)!important;}
.lp-hero,.lp-final-cta{background:linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 52%,#f8fafc 52%,#f8fafc 100%)!important;}
.lp-eyebrow{background:color-mix(in srgb,var(--secondary) 20%,#ffffff)!important;color:var(--primary-dark)!important;}
.lp-hero .lp-eyebrow{background:rgba(255,255,255,.16)!important;color:#ffffff!important;border-color:rgba(255,255,255,.25)!important;}
.lp-btn,.lp-section[data-lp-styled="1"] .lp-btn{background:var(--button)!important;color:var(--button-text)!important;box-shadow:0 16px 32px color-mix(in srgb,var(--button) 24%,transparent)!important;}
.lp-btn--soft{background:#ffffff!important;color:var(--primary-dark)!important;border-color:color-mix(in srgb,var(--primary) 18%,#ffffff)!important;}
.lp-card h3,.lp-price{color:var(--primary)!important;}
.lp-final-cta,.landing-mini-footer:not(.landing-mini-footer--custom){background:linear-gradient(135deg,var(--footer),var(--primary-dark))!important;}
.lp-lead-form-section,.landing-page-breadcrumb-hero{background:linear-gradient(180deg,#ffffff 0%,color-mix(in srgb,var(--bg) 82%,#ffffff) 100%)!important;}
.lp-lead-form-card{border-color:var(--border)!important;}
.lp-section[data-lp-styled="1"] .lp-eyebrow,.lp-inline-link{color:var(--primary)!important;}
/* full theme coverage for dashboard modules. */
.admin-shell{background:linear-gradient(180deg,var(--admin-soft) 0%,var(--admin-bg) 260px)!important;}
.admin-card a:not(.admin-btn),.admin-report-card-link,.admin-table a,.admin-help a,.admin-metric a{color:var(--admin-primary)!important;}
.admin-badge,.admin-category,.admin-eyebrow,.admin-pill,.admin-stat-badge,.admin-status-pill,.admin-filter-pill,.admin-mini-badge,.admin-report-badge,.admin-chart-badge,.admin-chip,.admin-lead-chip,.admin-order-card__head em,.admin-inquiry-card__head em{background:color-mix(in srgb,var(--admin-primary) 13%,#ffffff)!important;color:var(--admin-primary-dark)!important;border-color:color-mix(in srgb,var(--admin-primary) 22%,#ffffff)!important;}
.admin-alert--success{background:color-mix(in srgb,var(--admin-primary) 9%,#ffffff)!important;border-color:color-mix(in srgb,var(--admin-primary) 24%,#ffffff)!important;color:var(--admin-primary-dark)!important;}
.admin-field input:focus,.admin-field select:focus,.admin-field textarea:focus,.admin-login-card input:focus{border-color:var(--admin-primary)!important;box-shadow:0 0 0 4px color-mix(in srgb,var(--admin-primary) 15%,transparent)!important;}
.admin-btn--primary{background:linear-gradient(135deg,var(--admin-primary),var(--admin-primary-dark))!important;border-color:var(--admin-primary)!important;color:#ffffff!important;box-shadow:0 14px 28px color-mix(in srgb,var(--admin-primary) 20%,transparent)!important;}
.admin-btn--soft,.admin-btn--light{background:#ffffff!important;color:var(--admin-primary-dark)!important;border-color:color-mix(in srgb,var(--admin-primary) 18%,#ffffff)!important;}
.admin-btn--soft:hover,.admin-btn--light:hover{background:color-mix(in srgb,var(--admin-primary) 9%,#ffffff)!important;color:var(--admin-primary-dark)!important;}
.admin-reports-shell .admin-report-card-link{color:var(--admin-primary)!important;}
.admin-reports-shell .admin-report-bar-track i,.admin-lead-dashboard .lead-bar i,.admin-dashboard-chart-bar i,.admin-chart-bar i,.admin-progress-bar i,.admin-progress-fill,.admin-mini-bar i{background:linear-gradient(90deg,var(--admin-primary),var(--secondary))!important;}
.admin-lead-dashboard .admin-lead-status,.admin-leads-shell .admin-lead-status,.admin-dashboard-status,.admin-realtime-status{background:color-mix(in srgb,var(--admin-primary) 8%,#ffffff)!important;border-color:color-mix(in srgb,var(--admin-primary) 18%,#ffffff)!important;color:var(--admin-primary-dark)!important;}
.admin-product-count,.admin-result-count,.admin-summary-pill{background:color-mix(in srgb,var(--admin-primary) 12%,#ffffff)!important;color:var(--admin-primary-dark)!important;}
.admin-marketing-card,.admin-report-metric,.admin-lead-card,.admin-analytics-card{border-color:color-mix(in srgb,var(--admin-primary) 12%,#ffffff)!important;}
<?php
 echo "</style>\n";
 }
}
