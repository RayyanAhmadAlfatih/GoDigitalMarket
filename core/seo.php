<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| SEO ENGINE
|--------------------------------------------------------------------------
| Production-grade SEO architecture
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| DEFAULT SEO DATA
|--------------------------------------------------------------------------
*/

$GLOBALS['seo'] = [
    'title' => DEFAULT_META_TITLE,
    'description' => DEFAULT_META_DESCRIPTION,
    'keywords' => DEFAULT_META_KEYWORDS,
    'image' => DEFAULT_OG_IMAGE,
    'url' => current_url(),
    'robots' => 'index, follow',
    'type' => 'website',
    'author' => SITE_NAME,
];

/*
|--------------------------------------------------------------------------
| SET SEO
|--------------------------------------------------------------------------
*/

if (!function_exists('set_seo')) {

    function set_seo(array $data = []): void
    {
        $GLOBALS['seo'] = array_merge(
            $GLOBALS['seo'],
            $data
        );
    }
}

/*
|--------------------------------------------------------------------------
| GET SEO
|--------------------------------------------------------------------------
*/

if (!function_exists('seo')) {

    function seo(?string $key = null): mixed
    {
        if ($key === null) {
            return $GLOBALS['seo'];
        }

        return $GLOBALS['seo'][$key] ?? null;
    }
}


/*
|--------------------------------------------------------------------------
| NORMALIZE ROBOTS CONTENT
|--------------------------------------------------------------------------
| Keep robot directives consistent across pages. Indexed public pages get
| max-image-preview:large so product/article images can be shown properly in
| search previews. Noindex pages stay conservative.
*/

if (!function_exists('seo_robots_content')) {

    function seo_robots_content(?string $value = null): string
    {
        $value = trim((string)($value ?: 'index, follow'));

        if ($value === '') {
            $value = 'index, follow';
        }

        $parts = [];
        foreach (explode(',', strtolower($value)) as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($part, $parts, true)) {
                $parts[] = $part;
            }
        }

        if (!$parts) {
            $parts = ['index', 'follow'];
        }

        if (!in_array('noindex', $parts, true) && !in_array('max-image-preview:large', $parts, true)) {
            $parts[] = 'max-image-preview:large';
        }

        return implode(', ', $parts);
    }
}

/*
|--------------------------------------------------------------------------
| RENDER META TAGS
|--------------------------------------------------------------------------
*/

if (!function_exists('render_seo')) {

    function render_seo(): void
    {
        $title = esc(seo('title'));
        $description = esc(seo('description'));
        $keywords = esc(seo('keywords'));
        $image = esc(seo('image'));
        $url = esc(canonical_url());
        $robots = esc(seo_robots_content((string)(seo('robots') ?: 'index, follow')));
        $type = esc(seo('type'));
        $author = esc(seo('author'));

        echo PHP_EOL;

        echo '<title>' . $title . '</title>' . PHP_EOL;

        echo '<meta charset="UTF-8">' . PHP_EOL;

        echo '<meta http-equiv="X-UA-Compatible" content="IE=edge">' . PHP_EOL;

        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">' . PHP_EOL;

        echo '<meta name="description" content="' . $description . '">' . PHP_EOL;

        echo '<meta name="keywords" content="' . $keywords . '">' . PHP_EOL;

        echo '<meta name="robots" content="' . $robots . '">' . PHP_EOL;

        echo '<meta name="author" content="' . $author . '">' . PHP_EOL;

        echo '<link rel="canonical" href="' . esc(canonical_url()) . '">' . PHP_EOL;

        /*
        |--------------------------------------------------------------------------
        | OPEN GRAPH
        |--------------------------------------------------------------------------
        */

        echo '<meta property="og:type" content="' . $type . '">' . PHP_EOL;

        echo '<meta property="og:title" content="' . $title . '">' . PHP_EOL;

        echo '<meta property="og:description" content="' . $description . '">' . PHP_EOL;

        echo '<meta property="og:url" content="' . $url . '">' . PHP_EOL;

        echo '<meta property="og:image" content="' . $image . '">' . PHP_EOL;

        echo '<meta property="og:site_name" content="' . esc(SITE_NAME) . '">' . PHP_EOL;

        echo '<meta property="og:locale" content="id_ID">' . PHP_EOL;

        /*
        |--------------------------------------------------------------------------
        | TWITTER CARD
        |--------------------------------------------------------------------------
        */

        echo '<meta name="twitter:card" content="summary_large_image">' . PHP_EOL;

        echo '<meta name="twitter:title" content="' . $title . '">' . PHP_EOL;

        echo '<meta name="twitter:description" content="' . $description . '">' . PHP_EOL;

        echo '<meta name="twitter:image" content="' . $image . '">' . PHP_EOL;

        /*
        |--------------------------------------------------------------------------
        | THEME
        |--------------------------------------------------------------------------
        */

        echo '<meta name="theme-color" content="#0f172a">' . PHP_EOL;

        /*
        |--------------------------------------------------------------------------
        | FAVICON
        |--------------------------------------------------------------------------
        */

        echo '<link rel="icon" href="' . asset('images/favicon.png') . '">' . PHP_EOL;

        echo PHP_EOL;
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT SEO
|--------------------------------------------------------------------------
*/

if (!function_exists('product_seo')) {

    function product_seo(
        string $title,
        string $description,
        string $image
    ): void {

        set_seo([
            'title' => meta_title($title),
            'description' => $description,
            'image' => $image,
            'type' => 'product',
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| ARTICLE SEO
|--------------------------------------------------------------------------
*/

if (!function_exists('article_seo')) {

    function article_seo(
        string $title,
        string $description,
        string $image
    ): void {

        set_seo([
            'title' => meta_title($title),
            'description' => $description,
            'image' => $image,
            'type' => 'article',
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| NOINDEX
|--------------------------------------------------------------------------
*/

if (!function_exists('seo_noindex')) {

    function seo_noindex(): void
    {
        set_seo([
            'robots' => 'noindex, nofollow'
        ]);
    }
}