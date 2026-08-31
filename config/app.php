<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| APPLICATION START
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {

    define(
        'APP_START',
        microtime(true)
    );
}

/*
|--------------------------------------------------------------------------
| ROOT PATH
|--------------------------------------------------------------------------
*/

define(
    'ROOT_PATH',
    dirname(__DIR__)
);

/*
|--------------------------------------------------------------------------
| PUBLIC PATH
|--------------------------------------------------------------------------
*/

define(
    'PUBLIC_PATH',
    ROOT_PATH
);

/*
|--------------------------------------------------------------------------
| ENV FILE SUPPORT
|--------------------------------------------------------------------------
|
| Future:
| - .env support
| - production secrets
|--------------------------------------------------------------------------
*/

if (
    file_exists(
        ROOT_PATH . '/.env'
    )
) {

    $envLines = file(
        ROOT_PATH . '/.env'
    );

    foreach ($envLines as $line) {

        $line = trim($line);

        if (
            $line === '' ||
            str_starts_with($line, '#')
        ) {

            continue;
        }

        [$key, $value] =
            array_pad(
                explode('=', $line, 2),
                2,
                null
            );

        if (
            $key !== null &&
            $value !== null
        ) {

            $_ENV[trim($key)] =
                trim($value);
        }
    }
}

/*
|--------------------------------------------------------------------------
| HTTPS DETECTION
|--------------------------------------------------------------------------
*/

function app_is_https(): bool
{
    /*
    |--------------------------------------------------------------------------
    | STANDARD HTTPS
    |--------------------------------------------------------------------------
    */

    if (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    ) {

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | SERVER PORT
    |--------------------------------------------------------------------------
    */

    if (
        (int)(
            $_SERVER['SERVER_PORT']
            ?? 80
        ) === 443
    ) {

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | REVERSE PROXY
    |--------------------------------------------------------------------------
    */

    if (
        (
            $_SERVER['HTTP_X_FORWARDED_PROTO']
            ?? ''
        ) === 'https'
    ) {

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | CLOUDFLARE
    |--------------------------------------------------------------------------
    */

    if (
        (
            $_SERVER['HTTP_CF_VISITOR']
            ?? ''
        ) !== ''
    ) {

        return str_contains(

            strtolower(
                $_SERVER['HTTP_CF_VISITOR']
            ),

            'https'
        );
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| HOST DETECTION
|--------------------------------------------------------------------------
*/

function app_host(): string
{
    $host =
        $_SERVER['HTTP_HOST']
        ?? 'localhost';

    /*
    |--------------------------------------------------------------------------
    | REMOVE PORT
    |--------------------------------------------------------------------------
    */

    $host =
        preg_replace(
            '/:\d+$/',
            '',
            strtolower($host)
        );

    return $host ?: 'localhost';
}

/*
|--------------------------------------------------------------------------
| LOCALHOST DETECTION
|--------------------------------------------------------------------------
*/

function app_is_localhost(): bool
{
    $localhostHosts = [

        'localhost',
        '127.0.0.1',
        '::1',

    ];

    return in_array(

        app_host(),

        $localhostHosts,

        true
    );
}

/*
|--------------------------------------------------------------------------
| SUBFOLDER DETECTION
|--------------------------------------------------------------------------
|
| Support:
| localhost/umkm-commerce
| domain.com/subfolder
|--------------------------------------------------------------------------
*/

function app_subfolder(): string
{
    $scriptName =
        $_SERVER['SCRIPT_NAME']
        ?? '';

    $scriptName =
        str_replace(
            '\\',
            '/',
            $scriptName
        );

    $dirname =
        dirname($scriptName);

    if (
        $dirname === '/' ||
        $dirname === '.'
    ) {

        return '';
    }

    return trim(
        $dirname,
        '/'
    );
}

/*
|--------------------------------------------------------------------------
| URL SCHEME
|--------------------------------------------------------------------------
*/

function app_scheme(): string
{
    return app_is_https()
        ? 'https'
        : 'http';
}

/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
|
| APP_URL override support
|--------------------------------------------------------------------------
*/

function app_url(): string
{
    /*
    |--------------------------------------------------------------------------
    | MANUAL OVERRIDE
    |--------------------------------------------------------------------------
    */

    if (
        !empty($_ENV['APP_URL'])
    ) {

        return rtrim(
            $_ENV['APP_URL'],
            '/'
        );
    }

    $httpHost =
        $_SERVER['HTTP_HOST']
        ?? app_host();

    $url =
        app_scheme() .
        '://' .
        strtolower((string)$httpHost);

    $subfolder =
        app_subfolder();

    if (
        $subfolder !== ''
    ) {

        $url .= '/' .
            $subfolder;
    }

    return rtrim($url, '/');
}

/*
|--------------------------------------------------------------------------
| WWW NORMALIZATION
|--------------------------------------------------------------------------
*/

define(
    'FORCE_WWW',
    false
);

define(
    'FORCE_NON_WWW',
    true
);

/*
|--------------------------------------------------------------------------
| NORMALIZE URL
|--------------------------------------------------------------------------
*/

function normalize_site_url(
    string $url
): string {

    $parts =
        parse_url($url);

    if (!$parts) {
        return $url;
    }

    $scheme =
        $parts['scheme']
        ?? 'https';

    $host =
        $parts['host']
        ?? '';

    $path =
        $parts['path']
        ?? '';

    $port = isset($parts['port'])
        ? ':' . (string)$parts['port']
        : '';

    /*
    |--------------------------------------------------------------------------
    | WWW
    |--------------------------------------------------------------------------
    */

    if (
        FORCE_WWW &&
        !str_starts_with(
            $host,
            'www.'
        )
    ) {

        $host = 'www.' . $host;
    }

    /*
    |--------------------------------------------------------------------------
    | NON WWW
    |--------------------------------------------------------------------------
    */

    if (
        FORCE_NON_WWW &&
        str_starts_with(
            $host,
            'www.'
        )
    ) {

        $host =
            substr($host, 4);
    }

    return
        $scheme .
        '://' .
        $host .
        $port .
        $path;
}

/*
|--------------------------------------------------------------------------
| ENVIRONMENT
|--------------------------------------------------------------------------
*/

$appEnv = strtolower(trim((string)($_ENV['APP_ENV'] ?? 'production')));

if (!in_array($appEnv, ['production', 'staging', 'development', 'testing'], true)) {
    $appEnv = 'production';
}

define(
    'APP_ENV',
    $appEnv
);

/*
|--------------------------------------------------------------------------
| APPLICATION VERSION
|--------------------------------------------------------------------------
*/

define(
    'APP_VERSION',
    'Template'
);

$appDebugRequested = filter_var(
    (string)($_ENV['APP_DEBUG'] ?? 'false'),
    FILTER_VALIDATE_BOOLEAN
);

define(
    'APP_DEBUG',
    APP_ENV !== 'production' && $appDebugRequested
);

/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
*/

define(
    'BASE_URL',
    normalize_site_url(
        app_url()
    )
);

define(
    'SITE_URL',
    BASE_URL
);

/*
|--------------------------------------------------------------------------
| CDN URL
|--------------------------------------------------------------------------
*/

define(
    'CDN_URL',
    $_ENV['CDN_URL']
    ?? ''
);

/*
|--------------------------------------------------------------------------
| ASSET URL
|--------------------------------------------------------------------------
*/

define(
    'ASSET_URL',

    CDN_URL !== ''

    ? CDN_URL . '/assets'

    : BASE_URL . '/assets'
);

/*
|--------------------------------------------------------------------------
| BRAND & THEME BOOTSTRAP
|--------------------------------------------------------------------------
| Settings are loaded before constants so public pages, SEO, schema, and
| dashboard labels can follow the brand selected by the website owner.
|--------------------------------------------------------------------------
*/

if (!function_exists('app_theme_default_settings')) {
    function app_theme_default_settings(): array
    {
        return [
            'business_name' => 'UMKM Commerce Template',
            'tagline' => 'Website katalog, landing page, checkout, dan WhatsApp selling untuk UMKM',
            'description' => 'Template website commerce untuk UMKM: katalog produk, layanan, landing page, checkout, WhatsApp selling, dan artikel SEO.',
            'keywords' => 'template website umkm, katalog produk, landing page, checkout, whatsapp selling, jasa umkm, toko online',
            'email' => 'admin@example.com',
            'phone' => '6281234567890',
            'whatsapp' => '6281234567890',
            'address' => 'Indonesia',
            'logo_url' => 'images/logo.png',
            'favicon_url' => 'images/favicon.png',
            'og_image_url' => 'images/og-default.jpg',
            'facebook_url' => 'https://facebook.com/',
            'instagram_url' => 'https://instagram.com/',
            'youtube_url' => 'https://youtube.com/',
            'tiktok_url' => '',
            'linkedin_url' => '',
            'theme_preset' => 'biru-profesional',
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
            'login_branding_enabled' => '1',
            'login_layout' => 'split',
            'login_background_style' => 'soft-gradient',
            'login_logo_url' => '',
            'login_background_image' => '',
            'login_badge' => 'Dashboard Admin',
            'login_title' => 'Masuk ke Dashboard',
            'login_tagline' => 'Kelola website bisnis dari satu tempat.',
            'login_description' => 'Atur katalog, landing page, checkout, form prospek, artikel SEO, dan insight bisnis tanpa ribet.',
            'login_button_text' => 'Masuk Dashboard',
            'login_footer_note' => 'Dashboard resmi {business_name}',
            'motion_effects_enabled' => '1',
            'motion_public_enabled' => '1',
            'motion_admin_enabled' => '1',
            'motion_landing_enabled' => '1',
            'motion_intensity' => 'soft',
        ];
    }
}

if (!function_exists('app_theme_sanitize_hex')) {
    function app_theme_sanitize_hex(mixed $value, string $fallback): string
    {
        $value = strtolower(trim((string)$value));

        if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
            return $value;
        }

        if (preg_match('/^[0-9a-f]{6}$/', $value)) {
            return '#' . $value;
        }

        return $fallback;
    }
}

if (!function_exists('app_theme_load_settings')) {
    function app_theme_load_settings(): array
    {
        $defaults = app_theme_default_settings();
        $file = ROOT_PATH . '/storage/theme-settings.json';

        if (!is_file($file)) {
            return $defaults;
        }

        $decoded = json_decode((string)file_get_contents($file), true);

        if (!is_array($decoded)) {
            return $defaults;
        }

        $settings = array_merge($defaults, $decoded);

        foreach ([
            'primary_color', 'primary_dark_color', 'secondary_color', 'secondary_light_color',
            'button_color', 'button_text_color', 'header_color', 'footer_color',
            'background_color', 'text_color', 'muted_text_color', 'border_color',
            'admin_primary_color', 'admin_primary_dark_color', 'admin_soft_color',
        ] as $key) {
            $settings[$key] = app_theme_sanitize_hex($settings[$key] ?? '', (string)$defaults[$key]);
        }

        foreach ($settings as $key => $value) {
            if (is_string($value)) {
                $settings[$key] = trim($value);
            }
        }

        return $settings;
    }
}

$APP_THEME_SETTINGS = app_theme_load_settings();

define('SITE_THEME_SETTINGS', $APP_THEME_SETTINGS);

if (!function_exists('app_theme_value')) {
    function app_theme_value(string $key, mixed $default = null): mixed
    {
        $settings = defined('SITE_THEME_SETTINGS') && is_array(SITE_THEME_SETTINGS)
            ? SITE_THEME_SETTINGS
            : app_theme_default_settings();

        return $settings[$key] ?? $default;
    }
}

/*
|--------------------------------------------------------------------------
| SITE CONFIG
|--------------------------------------------------------------------------
*/

define('SITE_NAME', (string)app_theme_value('business_name', 'UMKM Commerce Template'));

define('SITE_TAGLINE', (string)app_theme_value('tagline', 'Website katalog, landing page, checkout, dan WhatsApp selling untuk UMKM'));

define('SITE_EMAIL', (string)app_theme_value('email', 'admin@example.com'));

define('SITE_PHONE', (string)app_theme_value('phone', '6281234567890'));

define('SITE_WHATSAPP', preg_replace('/\D+/', '', (string)app_theme_value('whatsapp', '6281234567890')) ?: '6281234567890');

define('SITE_ADDRESS', (string)app_theme_value('address', 'Indonesia'));

define('SITE_LOGO', (string)app_theme_value('logo_url', 'images/logo.png'));

define('SITE_FAVICON', (string)app_theme_value('favicon_url', 'images/favicon.png'));

define('SITE_OG_IMAGE', (string)app_theme_value('og_image_url', 'images/og-default.jpg'));

define('SITE_LOCALE', 'id_ID');

define('SITE_TIMEZONE', 'Asia/Jakarta');

/*
|--------------------------------------------------------------------------
| SEO DEFAULT
|--------------------------------------------------------------------------
*/

define('DEFAULT_META_TITLE', SITE_NAME);

define('DEFAULT_META_DESCRIPTION', (string)app_theme_value('description', 'Template website commerce untuk UMKM: katalog produk, layanan, landing page, checkout, WhatsApp selling, dan artikel SEO.'));

define('DEFAULT_META_KEYWORDS', (string)app_theme_value('keywords', 'template website umkm, katalog produk, landing page, checkout, whatsapp selling, jasa umkm, toko online'));

define('DEFAULT_OG_IMAGE', filter_var(SITE_OG_IMAGE, FILTER_VALIDATE_URL) ? SITE_OG_IMAGE : ASSET_URL . '/' . ltrim(SITE_OG_IMAGE, '/'));

/*
|--------------------------------------------------------------------------
| DIRECTORY PATHS
|--------------------------------------------------------------------------
*/

define(
    'ASSETS_PATH',
    ROOT_PATH . '/assets'
);

define(
    'UPLOADS_PATH',
    ROOT_PATH . '/uploads'
);

define(
    'CACHE_PATH',
    ROOT_PATH . '/cache'
);

define(
    'DATA_PATH',
    ROOT_PATH . '/data'
);

define(
    'COMPONENTS_PATH',
    ROOT_PATH . '/components'
);

define(
    'PAGES_PATH',
    ROOT_PATH . '/pages'
);

define(
    'CORE_PATH',
    ROOT_PATH . '/core'
);

define(
    'FEEDS_PATH',
    ROOT_PATH . '/feeds'
);

define(
    'LOGS_PATH',
    ROOT_PATH . '/logs'
);

define(
    'STORAGE_PATH',
    ROOT_PATH . '/storage'
);

/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/

define(
    'CSRF_TOKEN_NAME',
    '_token'
);

define(
    'HASH_ALGO_TYPE',
    PASSWORD_BCRYPT
);

/*
|--------------------------------------------------------------------------
| PERFORMANCE
|--------------------------------------------------------------------------
*/

define(
    'ENABLE_CACHE',
    false
);

define(
    'CACHE_TTL',
    3600
);

/*
|--------------------------------------------------------------------------
| ERROR REPORTING
|--------------------------------------------------------------------------
*/

if (APP_DEBUG) {

    ini_set(
        'display_errors',
        '1'
    );

    ini_set(
        'display_startup_errors',
        '1'
    );

    error_reporting(E_ALL);

} else {

    ini_set(
        'display_errors',
        '0'
    );

    ini_set(
        'display_startup_errors',
        '0'
    );

    error_reporting(0);
}

/*
|--------------------------------------------------------------------------
| TIMEZONE
|--------------------------------------------------------------------------
*/

date_default_timezone_set(
    SITE_TIMEZONE
);

/*
|--------------------------------------------------------------------------
| CREATE IMPORTANT DIRECTORIES
|--------------------------------------------------------------------------
*/

$directories = [

    CACHE_PATH,
    STORAGE_PATH,
    LOGS_PATH,
    STORAGE_PATH . '/form-files',
    ASSETS_PATH . '/uploads',
    STORAGE_PATH . '/payment-proofs',
    ASSETS_PATH . '/uploads/form-files',
    function_exists('maintenance_backup_dir') ? maintenance_backup_dir() : CACHE_PATH . '/maintenance-backups',

];

foreach ($directories as $directory) {

    if (!is_dir($directory)) {

        @mkdir(
            $directory,
            0775,
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FIX LINUX VPS/XAMPP PERMISSION
    |--------------------------------------------------------------------------
    */

    if (is_dir($directory)) {

        @chmod($directory, 0775);
    }
}


/*
|--------------------------------------------------------------------------
| RUNTIME SAFETY FILES
|--------------------------------------------------------------------------
| Final stabilization: create protection files for writable runtime folders
| when the hosting package is uploaded fresh. This keeps logs/storage private
| on Apache/LiteSpeed while staying harmless on Nginx/VPS.
|--------------------------------------------------------------------------
*/

$runtimeSafetyFiles = [
    STORAGE_PATH . '/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    LOGS_PATH . '/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    CACHE_PATH . '/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    ASSETS_PATH . '/uploads/.htaccess' => "Options -Indexes
<FilesMatch \"\\.(php|phtml|php[0-9]|phar|cgi|pl|py|sh)$\">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order Allow,Deny
        Deny from all
    </IfModule>
</FilesMatch>
",
    ASSETS_PATH . '/uploads/form-files/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    PAGES_PATH . '/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    CORE_PATH . '/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    COMPONENTS_PATH . '/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    DATA_PATH . '/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    ROOT_PATH . '/docs/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
    FEEDS_PATH . '/.htaccess' => "Options -Indexes
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order Allow,Deny
    Deny from all
</IfModule>
",
];

foreach ($runtimeSafetyFiles as $runtimeFile => $runtimeContent) {
    $runtimeDir = dirname((string)$runtimeFile);

    if (!is_dir($runtimeDir)) {
        @mkdir($runtimeDir, 0775, true);
    }

    if (!is_file((string)$runtimeFile) && is_dir($runtimeDir) && is_writable($runtimeDir)) {
        @file_put_contents((string)$runtimeFile, (string)$runtimeContent, LOCK_EX);
        @chmod((string)$runtimeFile, 0644);
    }
}

/*
|--------------------------------------------------------------------------
| LOAD CORE FILES
|--------------------------------------------------------------------------
*/

$coreFiles = [

    '/helpers.php',
    '/db.php',
    '/storage-adapter.php',
    '/cloud-backup.php',
    '/looker-studio-connector.php',
    '/looker-studio-setup-wizard.php',
    '/looker-studio-dashboard-pack.php',
    '/seo.php',
    '/cache.php',
    '/image.php',
    '/theme.php',
    '/navigation.php',
    '/business.php',
    '/website-starter-wizard.php',
    '/template-content.php',
    '/homepage.php',
    '/form-builder.php',
    '/content.php',
    '/product.php',
    '/seo-landing.php',
    '/landing-page-builder.php',
    '/wp-migration.php',
    '/wp-media-migration.php',
    '/wp-content-cleaner.php',
    '/wp-elementor-import.php',
    '/migration-command-center.php',
    '/seo-preservation.php',
    '/internal-link-migration.php',
    '/landing-page-ai-assistant.php',
    '/landing-page-analytics.php',
    '/landing-page-optimization.php',
    '/seo-quality.php',
    '/media-library.php',
    '/dynamic-content.php',
    '/dynamic-term-content.php',
    '/schema.php',
    '/universal-seo.php',
    '/seo-growth-planner.php',
    '/seo-content-planner.php',
    '/seo-execution-board.php',
    '/seo-publish-checklist.php',
    '/seo-draft-publisher.php',
    '/seo-link-health.php',
    '/content-performance.php',
    '/conversion-opportunity.php',
    '/sales-funnel-growth.php',
    '/sales-funnel-action-center.php',
    '/profit-action-dashboard.php',
    '/profit-playbook.php',
    '/offer-cta-testing.php',
    '/cta-placement-assistant.php',
    '/growth-snapshot.php',
    '/trust-conversion.php',
    '/launch-readiness.php',
    '/umkm-onboarding.php',
    '/admin-help.php',
    '/admin-menu.php',
    '/security.php',
    '/env-settings.php',
    '/admin-users.php',
    '/admin-auth.php',
    '/first-run.php',
    '/router.php',
    '/conversion.php',
    '/cta-result-tracker.php',
    '/seo-profit-attribution.php',
    '/seo-assisted-journey-map.php',
    '/seo-money-page-optimizer.php',
    '/money-page-deployment-checklist.php',
    '/internal-link-cta-injection-assistant.php',
    '/seo-content-refresh-planner.php',
    '/lead-quality-followup-scoring.php',
    '/profit-report-builder.php',
    '/seo-campaign-calendar-growth-sprint-planner.php',
    '/u-growth-command-center.php',
    '/release-audit.php',
    '/landing-page-ab.php',
    '/analytics.php',
    '/server-conversion.php',
    '/google-ads-vault.php',
    '/marketing-integration.php',
    '/marketing-analytics-map.php',
    '/inquiry.php',
    '/checkout.php',
    '/shipping.php',
    '/commerce-rules.php',
    '/order.php',
    '/inventory.php',
    '/payment.php',
    '/payment-gateway.php',
    '/digital-delivery.php',
    '/buyer-account.php',
    '/license-manager.php',
    '/subscription.php',
    '/renewal-clv.php',
    '/member-access.php',
    '/member-dashboard.php',
    '/content-restriction.php',
    '/payment-proof.php',
    '/payment-reminder.php',
    '/commerce-insight.php',
    '/report.php',
    '/growth-insight.php',
    '/crm.php',
    '/checkout-recovery.php',
    '/notification.php',
    '/transaction.php',
    '/activity-log.php',
    '/data-health.php',
    '/maintenance.php',
    '/production-readiness.php',

];

foreach ($coreFiles as $file) {

    $path =
        CORE_PATH . $file;

    if (file_exists($path)) {

        require_once $path;
    }
}