<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| SECURITY ENGINE
|--------------------------------------------------------------------------
| Production-grade security helper
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| START SECURE SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/*
|--------------------------------------------------------------------------
| SECURITY HEADERS
|--------------------------------------------------------------------------
*/

if (!headers_sent()) {

    header('X-Frame-Options: SAMEORIGIN');

    header('X-Content-Type-Options: nosniff');

    header('Referrer-Policy: strict-origin-when-cross-origin');

    header('X-Permitted-Cross-Domain-Policies: none');

    header(
        'Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()'
    );

    header(
        "Content-Security-Policy: default-src 'self' https: data: blob:; img-src 'self' https: data: blob:; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' https: data:; connect-src 'self' https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self' https://wa.me https://api.whatsapp.com;"
    );
}

/*
|--------------------------------------------------------------------------
| GENERATE CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (!function_exists('csrf_token')) {

    function csrf_token(): string
    {
        if (empty($_SESSION[CSRF_TOKEN_NAME])) {

            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(
                random_bytes(32)
            );
        }

        return $_SESSION[CSRF_TOKEN_NAME];
    }
}

/*
|--------------------------------------------------------------------------
| CSRF FIELD
|--------------------------------------------------------------------------
*/

if (!function_exists('csrf_field')) {

    function csrf_field(): string
    {
        return '<input type="hidden" name="' .
            CSRF_TOKEN_NAME .
            '" value="' .
            csrf_token() .
            '">';
    }
}

/*
|--------------------------------------------------------------------------
| VERIFY CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (!function_exists('verify_csrf')) {

    function verify_csrf(): bool
    {
        $token = $_POST[CSRF_TOKEN_NAME] ?? '';

        if (
            empty($_SESSION[CSRF_TOKEN_NAME]) ||
            empty($token)
        ) {
            return false;
        }

        return hash_equals(
            $_SESSION[CSRF_TOKEN_NAME],
            $token
        );
    }
}

/*
|--------------------------------------------------------------------------
| REQUIRE VALID CSRF
|--------------------------------------------------------------------------
*/

if (!function_exists('require_csrf')) {

    function require_csrf(): void
    {
        if (!verify_csrf()) {

            http_response_code(419);

            exit('Invalid CSRF token.');
        }
    }
}

/*
|--------------------------------------------------------------------------
| SANITIZE STRING
|--------------------------------------------------------------------------
*/

if (!function_exists('sanitize_string')) {

    function sanitize_string(
        ?string $value
    ): string {

        $value = trim((string) $value);

        $value = strip_tags($value);

        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| SANITIZE EMAIL
|--------------------------------------------------------------------------
*/

if (!function_exists('sanitize_email')) {

    function sanitize_email(
        ?string $email
    ): string {

        return filter_var(
            trim((string) $email),
            FILTER_SANITIZE_EMAIL
        );
    }
}

/*
|--------------------------------------------------------------------------
| VALIDATE EMAIL
|--------------------------------------------------------------------------
*/

if (!function_exists('is_valid_email')) {

    function is_valid_email(
        string $email
    ): bool {

        return filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        ) !== false;
    }
}

/*
|--------------------------------------------------------------------------
| SANITIZE URL
|--------------------------------------------------------------------------
*/

if (!function_exists('sanitize_url')) {

    function sanitize_url(
        ?string $url
    ): string {

        return filter_var(
            trim((string) $url),
            FILTER_SANITIZE_URL
        );
    }
}

/*
|--------------------------------------------------------------------------
| VALIDATE INTEGER
|--------------------------------------------------------------------------
*/

if (!function_exists('sanitize_int')) {

    function sanitize_int(
        mixed $value
    ): int {

        return (int) filter_var(
            $value,
            FILTER_SANITIZE_NUMBER_INT
        );
    }
}

/*
|--------------------------------------------------------------------------
| SAFE REDIRECT
|--------------------------------------------------------------------------
*/

if (!function_exists('safe_redirect')) {

    function safe_redirect(
        string $path
    ): never {

        $url = base_url($path);

        header('Location: ' . $url);

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| PUBLIC ENDPOINT SAFETY HELPERS
|--------------------------------------------------------------------------
| Shared guards for public JSON/form endpoints. These helpers keep public
| endpoints lightweight while preventing accidental log flooding and oversized
| request bodies on shared hosting.
|--------------------------------------------------------------------------
*/

if (!function_exists('public_endpoint_client_key')) {
    function public_endpoint_client_key(string $scope): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $scope = preg_replace('/[^a-z0-9_\-]/i', '-', $scope) ?: 'public';
        return hash('sha256', $ip . '|' . $ua . '|' . $scope . '|' . (string)($_ENV['APP_KEY'] ?? SITE_URL));
    }
}

if (!function_exists('public_endpoint_rate_limit_file')) {
    function public_endpoint_rate_limit_file(): string
    {
        return CACHE_PATH . '/public-endpoint-rate-limit.json';
    }
}

if (!function_exists('public_endpoint_rate_limit_bucket')) {
    function public_endpoint_rate_limit_bucket(string $scope, int $windowSeconds = 3600): array
    {
        $windowSeconds = max(60, min(86400, $windowSeconds));
        $file = public_endpoint_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = public_endpoint_client_key($scope);
        $cutoff = time() - $windowSeconds;
        return array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => (int)$ts > $cutoff));
    }
}

if (!function_exists('public_endpoint_is_rate_limited')) {
    function public_endpoint_is_rate_limited(string $scope, int $maxHits = 120, int $windowSeconds = 3600, int $minIntervalSeconds = 0): bool
    {
        $bucket = public_endpoint_rate_limit_bucket($scope, $windowSeconds);
        $last = $bucket ? max(array_map('intval', $bucket)) : 0;
        if ($minIntervalSeconds > 0 && $last > 0 && (time() - $last) < $minIntervalSeconds) {
            return true;
        }
        return count($bucket) >= max(1, $maxHits);
    }
}

if (!function_exists('public_endpoint_touch_rate_limit')) {
    function public_endpoint_touch_rate_limit(string $scope, int $windowSeconds = 3600): void
    {
        if (!is_dir(CACHE_PATH)) {
            @mkdir(CACHE_PATH, 0775, true);
        }
        $file = public_endpoint_rate_limit_file();
        $data = is_file($file) ? json_decode((string)@file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $key = public_endpoint_client_key($scope);
        $cutoff = time() - max(60, min(86400, $windowSeconds));
        $data[$key] = array_values(array_filter((array)($data[$key] ?? []), static fn($ts): bool => (int)$ts > $cutoff));
        $data[$key][] = time();
        if (count($data) > 1200) {
            $data = array_slice($data, -1200, null, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }
}

if (!function_exists('public_endpoint_body_too_large')) {
    function public_endpoint_body_too_large(int $maxBytes): bool
    {
        $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        return $length > 0 && $length > max(1024, $maxBytes);
    }
}

/*
|--------------------------------------------------------------------------
| ESCAPE ARRAY
|--------------------------------------------------------------------------
*/

if (!function_exists('escape_array')) {

    function escape_array(array $data): array
    {
        return array_map(
            static function ($item) {

                if (is_array($item)) {
                    return escape_array($item);
                }

                return esc((string) $item);

            },
            $data
        );
    }
}

/*
|--------------------------------------------------------------------------
| GENERATE RANDOM STRING
|--------------------------------------------------------------------------
*/

if (!function_exists('random_string')) {

    function random_string(
        int $length = 32
    ): string {

        return substr(
            bin2hex(random_bytes($length)),
            0,
            $length
        );
    }
}

/*
|--------------------------------------------------------------------------
| HASH PASSWORD
|--------------------------------------------------------------------------
*/

if (!function_exists('hash_password')) {

    function hash_password(
        string $password
    ): string {

        return password_hash(
            $password,
            HASH_ALGO_TYPE
        );
    }
}

/*
|--------------------------------------------------------------------------
| VERIFY PASSWORD
|--------------------------------------------------------------------------
*/

if (!function_exists('verify_password')) {

    function verify_password(
        string $password,
        string $hash
    ): bool {

        return password_verify(
            $password,
            $hash
        );
    }
}

/*
|--------------------------------------------------------------------------
| RATE LIMIT BASIC
|--------------------------------------------------------------------------
*/

if (!function_exists('rate_limit')) {

    function rate_limit(
        string $key,
        int $seconds = 60,
        int $maxAttempts = 10
    ): bool {

        if (!isset($_SESSION['rate_limit'])) {
            $_SESSION['rate_limit'] = [];
        }

        $now = time();

        if (
            !isset($_SESSION['rate_limit'][$key])
        ) {

            $_SESSION['rate_limit'][$key] = [
                'count' => 1,
                'time' => $now,
            ];

            return true;
        }

        $attempt = &$_SESSION['rate_limit'][$key];

        if (
            ($now - $attempt['time']) > $seconds
        ) {

            $attempt = [
                'count' => 1,
                'time' => $now,
            ];

            return true;
        }

        if (
            $attempt['count'] >= $maxAttempts
        ) {

            return false;
        }

        $attempt['count']++;

        return true;
    }
}

/*
|--------------------------------------------------------------------------
| CLEAN INPUT ARRAY
|--------------------------------------------------------------------------
*/

if (!function_exists('clean_input')) {

    function clean_input(array $input): array
    {
        $clean = [];

        foreach ($input as $key => $value) {

            if (is_array($value)) {

                $clean[$key] = clean_input($value);

            } else {

                $clean[$key] = sanitize_string(
                    (string) $value
                );
            }
        }

        return $clean;
    }
}

/*
|--------------------------------------------------------------------------
| SECURE FILE NAME
|--------------------------------------------------------------------------
*/

if (!function_exists('secure_filename')) {

    function secure_filename(
        string $filename
    ): string {

        $filename = preg_replace(
            '/[^A-Za-z0-9\-\_\.]/',
            '',
            $filename
        );

        return strtolower(
            trim((string) $filename)
        );
    }
}

/*
|--------------------------------------------------------------------------
| BLOCK PHP FILE UPLOAD
|--------------------------------------------------------------------------
*/

if (!function_exists('is_safe_upload')) {

    function is_safe_upload(
        string $filename
    ): bool {

        $blocked = [
            'php',
            'phtml',
            'php3',
            'php4',
            'php5',
            'phar',
        ];

        $extension = strtolower(
            pathinfo(
                $filename,
                PATHINFO_EXTENSION
            )
        );

        return !in_array(
            $extension,
            $blocked,
            true
        );
    }
}