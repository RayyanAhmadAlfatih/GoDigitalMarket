<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| GLOBAL HELPERS
|--------------------------------------------------------------------------
| Production-ready helper functions
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| ESCAPE OUTPUT
|--------------------------------------------------------------------------
*/

if (!function_exists('esc')) {

    function esc(?string $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| DETECT HTTPS
|--------------------------------------------------------------------------
*/

if (!function_exists('is_https')) {

    function is_https(): bool
    {
        if (function_exists('app_is_https')) {
            return app_is_https();
        }

        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443
        );
    }
}

/*
|--------------------------------------------------------------------------
| DETECT HOST
|--------------------------------------------------------------------------
*/

if (!function_exists('site_host')) {

    function site_host(): string
    {
        if (function_exists('app_host')) {
            return app_host();
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return preg_replace('/:\d+$/', '', strtolower($host)) ?: 'localhost';
    }
}

/*
|--------------------------------------------------------------------------
| BASE URL
|--------------------------------------------------------------------------
*/

if (!function_exists('base_url')) {

    function base_url(string $path = ''): string
    {
        $protocol = is_https() ? 'https://' : 'http://';

        $host = site_host();

        $base = rtrim($protocol . $host, '/');

        return $base . '/' . ltrim($path, '/');
    }
}

/*
|--------------------------------------------------------------------------
| ASSET URL
|--------------------------------------------------------------------------
*/

if (!function_exists('asset')) {

    function asset(string $path): string
    {
        return rtrim(
            ASSET_URL,
            '/'
        ) . '/' . ltrim($path, '/');
    }
}

/*
|--------------------------------------------------------------------------
| UPLOAD URL
|--------------------------------------------------------------------------
*/

if (!function_exists('upload_url')) {

    function upload_url(string $path): string
    {
        return base_url('uploads/' . ltrim($path, '/'));
    }
}

/*
|--------------------------------------------------------------------------
| CURRENT URL
|--------------------------------------------------------------------------
*/

if (!function_exists('current_url')) {

    function current_url(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $query = parse_url($requestUri, PHP_URL_QUERY);

        $basePath = parse_url(SITE_URL, PHP_URL_PATH) ?: '';
        $basePath = '/' . trim((string)$basePath, '/');
        if ($basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
            $path = '/' . ltrim((string)$path, '/');
        }

        $url = rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');

        if (is_string($query) && $query !== '') {
            $url .= '?' . $query;
        }

        return $url;
    }
}

/*
|--------------------------------------------------------------------------
| SLUGIFY
|--------------------------------------------------------------------------
*/

if (!function_exists('slugify')) {

    function slugify(string $text): string
    {
        $text = strtolower(trim($text));

        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);

        return trim((string)$text, '-');
    }
}

/*
|--------------------------------------------------------------------------
| ACTIVE CLASS
|--------------------------------------------------------------------------
*/

if (!function_exists('active_class')) {

    function active_class(string $needle): string
    {
        $url = current_url();

        return str_contains($url, $needle)
            ? 'active'
            : '';
    }
}

/*
|--------------------------------------------------------------------------
| LIMIT TEXT
|--------------------------------------------------------------------------
*/

if (!function_exists('limit_words')) {

    function limit_words(string $text, int $limit = 20): string
    {
        $words = explode(' ', strip_tags($text));

        if (count($words) <= $limit) {
            return $text;
        }

        return implode(
            ' ',
            array_slice($words, 0, $limit)
        ) . '...';
    }
}

/*
|--------------------------------------------------------------------------
| FORMAT RUPIAH
|--------------------------------------------------------------------------
*/

if (!function_exists('rupiah')) {

    function rupiah(int|float $number): string
    {
        return 'Rp ' . number_format(
            $number,
            0,
            ',',
            '.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| WHATSAPP URL
|--------------------------------------------------------------------------
*/

if (!function_exists('wa_link')) {

    function wa_link(string $message = ''): string
    {
        $phone = SITE_WHATSAPP;

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
    }
}

/*
|--------------------------------------------------------------------------
| SAFE INCLUDE
|--------------------------------------------------------------------------
*/

if (!function_exists('safe_include')) {

    function safe_include(string $path): void
    {
        if (file_exists($path)) {
            require $path;
        }
    }
}


/*
|--------------------------------------------------------------------------
| CANONICAL URL
|--------------------------------------------------------------------------
*/

if (!function_exists('normalize_canonical_url')) {

    function normalize_canonical_url(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return strtok(current_url(), '?') ?: SITE_URL;
        }

        $parts = parse_url($url);

        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string)$parts['scheme']);
        $host = strtolower((string)$parts['host']);
        $path = (string)($parts['path'] ?? '/');
        $query = [];

        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);

            foreach ([
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'gclid',
                'fbclid',
                'msclkid',
                '_gl',
                '_ga',
            ] as $trackingKey) {
                unset($query[$trackingKey]);
            }

            ksort($query);
        }

        $cleanUrl = $scheme . '://' . $host . $path;

        if ($query) {
            $cleanUrl .= '?' . http_build_query($query);
        }

        return $cleanUrl;
    }
}

if (!function_exists('canonical_url')) {

    function canonical_url(): string
    {
        $seoCanonical = $GLOBALS['seo']['canonical'] ?? null;
        if (is_string($seoCanonical) && trim($seoCanonical) !== '') {
            return normalize_canonical_url(trim($seoCanonical));
        }

        return normalize_canonical_url(strtok(current_url(), '?') ?: SITE_URL);
    }
}

/*
|--------------------------------------------------------------------------
| META TITLE BUILDER
|--------------------------------------------------------------------------
*/

if (!function_exists('meta_title')) {

    function meta_title(?string $title = null): string
    {
        if (!$title) {
            return DEFAULT_META_TITLE;
        }

        return trim($title . ' | ' . SITE_NAME);
    }
}

/*
|--------------------------------------------------------------------------
| META DESCRIPTION
|--------------------------------------------------------------------------
*/

if (!function_exists('meta_description')) {

    function meta_description(
        ?string $description = null
    ): string {

        return $description
            ?: DEFAULT_META_DESCRIPTION;
    }
}

/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (!function_exists('is_post')) {

    function is_post(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
}

/*
|--------------------------------------------------------------------------
| CLIENT IP
|--------------------------------------------------------------------------
*/

if (!function_exists('client_ip')) {

    function client_ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/*
|--------------------------------------------------------------------------
| SEO, ROUTE URL, AND CANONICAL HELPERS
|--------------------------------------------------------------------------
| Canonical SEO state lives in core/seo.php. Canonical public URL helpers
| live in core/router.php so there is one source of truth for slug URLs.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| LIMIT CHARACTERS
|--------------------------------------------------------------------------
*/

if (!function_exists('limit_chars')) {

    function limit_chars(
        string $text,
        int $limit = 155
    ): string {

        $text = trim(strip_tags($text));
        $length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

        if ($length <= $limit) {
            return $text;
        }

        $short = function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);

        return rtrim($short) . '...';
    }
}

/*
|--------------------------------------------------------------------------
| ADMIN PASSWORD SETUP CHECK
|--------------------------------------------------------------------------
*/

if (!function_exists('admin_password_needs_setup')) {

    function admin_password_needs_setup(?string $password): bool
    {
        $password = trim((string)$password);

        if ($password === '') {
            return true;
        }

        $placeholders = [
            'password',
            'admin',
            'admin123',
            'password-kuat',
            'password-kuat-anda',
            'change-this-password-before-live',
            'ganti-password-kuat-sebelum-live',
            'ganti-dengan-password-kuat',
            'change-me',
            'changeme',
        ];

        return in_array(strtolower($password), $placeholders, true);
    }
}
