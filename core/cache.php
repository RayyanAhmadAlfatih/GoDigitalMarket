<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CACHE ENGINE
|--------------------------------------------------------------------------
| SEO performance optimization
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| CACHE CONFIG
|--------------------------------------------------------------------------
*/

if (!defined('CACHE_PATH')) {

    define(
        'CACHE_PATH',
        ROOT_PATH . '/cache'
    );
}

/*
|--------------------------------------------------------------------------
| CACHE ENABLED
|--------------------------------------------------------------------------
*/

function cache_enabled(): bool
{
    return
        defined('ENABLE_CACHE')
        ? (bool) ENABLE_CACHE
        : true;
}

/*
|--------------------------------------------------------------------------
| CACHE DIRECTORY READY
|--------------------------------------------------------------------------
*/

function cache_directory_ready(): bool
{
    if (!cache_enabled()) {
        return false;
    }

    if (!is_dir(CACHE_PATH)) {
        @mkdir(CACHE_PATH, 0775, true);
    }

    return is_dir(CACHE_PATH)
        && is_writable(CACHE_PATH);
}

/*
|--------------------------------------------------------------------------
| CACHE KEY
|--------------------------------------------------------------------------
*/

function cache_key(
    string $key
): string {

    return md5($key);
}

/*
|--------------------------------------------------------------------------
| CACHE FILE PATH
|--------------------------------------------------------------------------
*/

function cache_file(
    string $key
): string {

    return CACHE_PATH . '/' .
        cache_key($key) .
        '.cache';
}

/*
|--------------------------------------------------------------------------
| CACHE EXISTS
|--------------------------------------------------------------------------
*/

function cache_exists(
    string $key
): bool {

    return is_file(
        cache_file($key)
    );
}

/*
|--------------------------------------------------------------------------
| CACHE EXPIRED
|--------------------------------------------------------------------------
*/

function cache_expired(
    string $key,
    int $ttl
): bool {

    if (!cache_exists($key)) {
        return true;
    }

    $mtime = filemtime(
        cache_file($key)
    );

    if ($mtime === false) {
        return true;
    }

    return (
        time() -
        $mtime
    ) > $ttl;
}

/*
|--------------------------------------------------------------------------
| CACHE GET
|--------------------------------------------------------------------------
*/

function cache_get(
    string $key,
    mixed $default = null
): mixed {

    if (!cache_enabled()) {
        return $default;
    }

    $file = cache_file($key);

    if (!is_file($file) || !is_readable($file)) {
        return $default;
    }

    $content =
        file_get_contents($file);

    if ($content === false || $content === '') {
        return $default;
    }

    $data =
        @unserialize(
            $content,
            ['allowed_classes' => false]
        );

    if ($data === false && $content !== serialize(false)) {
        return $default;
    }

    return $data;
}

/*
|--------------------------------------------------------------------------
| CACHE PUT
|--------------------------------------------------------------------------
*/

function cache_put(
    string $key,
    mixed $data
): bool {

    if (!cache_directory_ready()) {
        return false;
    }

    $file = cache_file($key);
    $tmpFile = $file . '.' . getmypid() . '.tmp';

    $written = @file_put_contents(
        $tmpFile,
        serialize($data),
        LOCK_EX
    );

    if ($written === false) {
        return false;
    }

    @chmod($tmpFile, 0664);

    return @rename(
        $tmpFile,
        $file
    );
}

/*
|--------------------------------------------------------------------------
| CACHE REMEMBER
|--------------------------------------------------------------------------
*/

function cache_remember(
    string $key,
    int $ttl,
    callable $callback
): mixed {

    if (
        !cache_expired($key, $ttl)
    ) {

        return cache_get($key);
    }

    $data = $callback();

    cache_put($key, $data);

    return $data;
}

/*
|--------------------------------------------------------------------------
| CACHE DELETE
|--------------------------------------------------------------------------
*/

function cache_delete(
    string $key
): bool {

    $file = cache_file($key);

    if (!is_file($file)) {
        return false;
    }

    return @unlink($file);
}

/*
|--------------------------------------------------------------------------
| CACHE CLEAR
|--------------------------------------------------------------------------
*/

function cache_clear(): void
{
    if (!is_dir(CACHE_PATH)) {
        return;
    }

    $files = glob(
        CACHE_PATH . '/*.cache'
    );

    if (!$files) {
        return;
    }

    foreach ($files as $file) {

        if (is_file($file)) {

            @unlink($file);
        }
    }
}

/*
|--------------------------------------------------------------------------
| PAGE CACHE KEY
|--------------------------------------------------------------------------
*/

function page_cache_key(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    return
        'page_' .
        md5(
            strtolower((string) $host) . '|' . (string) $uri
        );
}

/*
|--------------------------------------------------------------------------
| PAGE CACHE START
|--------------------------------------------------------------------------
*/

function page_cache_start(
    int $ttl = 3600
): void {

    $GLOBALS['PAGE_CACHE_ACTIVE'] = false;
    $GLOBALS['PAGE_CACHE_OB_LEVEL'] = null;

    if (!cache_enabled()) {
        return;
    }

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '')
        !== 'GET'
    ) {

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | DO NOT CACHE ADMIN / AUTHENTICATED PAGES
    |--------------------------------------------------------------------------
    | This keeps admin pages safe and avoids fatal error when is_logged_in()
    | is not loaded yet.
    */

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?? $requestUri);
    $requestPath = '/' . ltrim($requestPath, '/');
    $basePathRaw = defined('BASE_URL') ? (string) (parse_url((string) BASE_URL, PHP_URL_PATH) ?? '') : '';
    $basePath = trim($basePathRaw, '/');
    if ($basePath !== '' && str_starts_with(trim($requestPath, '/'), $basePath . '/')) {
        $requestPath = '/' . substr(trim($requestPath, '/'), strlen($basePath) + 1);
    }

    if (str_starts_with($requestPath, '/admin')) {
        return;
    }

    if (preg_match('#/(lp|landing)/#', $requestUri) === 1) {
        return;
    }

    if (function_exists('is_logged_in') && is_logged_in()) {
        return;
    }

    if (!cache_directory_ready()) {
        return;
    }

    $key = page_cache_key();

    if (
        !cache_expired($key, $ttl)
    ) {

        $cached = cache_get($key);

        if (is_string($cached) && $cached !== '') {
            echo $cached;

            exit;
        }
    }

    $GLOBALS['PAGE_CACHE_ACTIVE'] = true;
    $GLOBALS['PAGE_CACHE_OB_LEVEL'] = ob_get_level();

    ob_start();
}

/*
|--------------------------------------------------------------------------
| PAGE CACHE END
|--------------------------------------------------------------------------
*/

function page_cache_end(): void
{
    if (!cache_enabled()) {
        return;
    }

    if (
        ($_SERVER['REQUEST_METHOD'] ?? '')
        !== 'GET'
    ) {

        return;
    }

    if (empty($GLOBALS['PAGE_CACHE_ACTIVE'])) {
        return;
    }

    if (ob_get_level() < 1) {
        $GLOBALS['PAGE_CACHE_ACTIVE'] = false;
        return;
    }

    $content =
        ob_get_clean();

    $GLOBALS['PAGE_CACHE_ACTIVE'] = false;
    $GLOBALS['PAGE_CACHE_OB_LEVEL'] = null;

    if ($content === false || $content === '') {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | HTML MINIFY
    |--------------------------------------------------------------------------
    */

    $content =
        minify_html($content);

    cache_put(
        page_cache_key(),
        $content
    );

    echo $content;
}

/*
|--------------------------------------------------------------------------
| HTML MINIFIER
|--------------------------------------------------------------------------
*/

function minify_html(
    string $html
): string {

    /*
    |--------------------------------------------------------------------------
    | SAFE HTML MINIFY
    |--------------------------------------------------------------------------
    | Keep it light. Aggressive minify can break inline JS/JSON-LD.
    */

    $html = preg_replace(
        '/<!--(?!\s*\[if).*?-->/s',
        '',
        $html
    ) ?? $html;

    $html = preg_replace(
        '/>\s+</',
        '><',
        $html
    ) ?? $html;

    return trim($html);
}

/*
|--------------------------------------------------------------------------
| CACHE RESPONSE HEADERS
|--------------------------------------------------------------------------
*/

function cache_headers(
    int $seconds = 3600
): void {

    if (headers_sent()) {
        return;
    }

    header(
        'Cache-Control: public, max-age=' .
        $seconds
    );

    header(
        'Pragma: cache'
    );

    header(
        'Expires: ' .
        gmdate(

            'D, d M Y H:i:s',

            time() + $seconds
        ) . ' GMT'
    );
}

/*
|--------------------------------------------------------------------------
| STATIC ASSET VERSION
|--------------------------------------------------------------------------
*/

function asset_version(
    string $path
): string {

    $fullPath =
        PUBLIC_PATH . '/' . ltrim(
            $path,
            '/'
        );

    if (!is_file($fullPath)) {

        return '1.0.0';
    }

    $mtime = filemtime($fullPath);

    return $mtime !== false
        ? (string) $mtime
        : '1.0.0';
}

/*
|--------------------------------------------------------------------------
| CACHEABLE JSON RESPONSE
|--------------------------------------------------------------------------
*/

function cache_json(
    string $key,
    int $ttl,
    callable $callback
): void {

    if (!headers_sent()) {
        header(
            'Content-Type: application/json; charset=UTF-8'
        );

        cache_headers($ttl);
    }

    $data = cache_remember(
        $key,
        $ttl,
        $callback
    );

    echo json_encode(

        $data,

        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| SITEMAP CACHE
|--------------------------------------------------------------------------
*/

function sitemap_cache_key(): string
{
    return 'sitemap_xml';
}

/*
|--------------------------------------------------------------------------
| RSS CACHE
|--------------------------------------------------------------------------
*/

function rss_cache_key(): string
{
    return 'rss_feed';
}

/*
|--------------------------------------------------------------------------
| ARTICLE CACHE KEY
|--------------------------------------------------------------------------
*/

function article_cache_key(
    string $slug
): string {

    return
        'article_' .
        md5($slug);
}

/*
|--------------------------------------------------------------------------
| PRODUCT CACHE KEY
|--------------------------------------------------------------------------
*/

function product_cache_key(
    string $slug
): string {

    return
        'product_' .
        md5($slug);
}
