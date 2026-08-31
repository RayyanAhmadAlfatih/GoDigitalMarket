<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}


/*
|--------------------------------------------------------------------------
| SCHEMA ENGINE
|--------------------------------------------------------------------------
| Production-grade structured data generator
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| SCHEMA STORAGE
|--------------------------------------------------------------------------
*/

$GLOBALS['schemas'] = [];

/*
|--------------------------------------------------------------------------
| ADD SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('add_schema')) {

    function add_schema(array $schema): void
    {
        if (empty($schema['@type']) && empty($schema['@graph'])) {
            return;
        }

        $signature = md5(json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: serialize($schema));

        foreach (($GLOBALS['schemas'] ?? []) as $existing) {
            $existingSignature = md5(json_encode($existing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: serialize($existing));
            if ($existingSignature === $signature) {
                return;
            }
        }

        $GLOBALS['schemas'][] = $schema;
    }
}

/*
|--------------------------------------------------------------------------
| RENDER SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('render_schema')) {

    function render_schema(): void
    {
        if (empty($GLOBALS['schemas'])) {
            return;
        }

        foreach ($GLOBALS['schemas'] as $schema) {

            echo PHP_EOL;

            echo '<script type="application/ld+json">';

            echo json_encode(
                $schema,
                JSON_UNESCAPED_SLASHES |
                JSON_UNESCAPED_UNICODE |
                JSON_PRETTY_PRINT
            );

            echo '</script>';

            echo PHP_EOL;
        }
    }
}

/*
|--------------------------------------------------------------------------
| ORGANIZATION SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('organization_schema')) {

    function organization_schema(): void
    {
        add_schema([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',

            'name' => SITE_NAME,

            'url' => SITE_URL,

            'logo' => function_exists('theme_logo_url') ? theme_logo_url() : asset('images/logo.png'),

            'description' => DEFAULT_META_DESCRIPTION,

            'email' => SITE_EMAIL,

            'telephone' => SITE_PHONE,

            'sameAs' => array_values(function_exists('theme_social_links') ? theme_social_links() : []),
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| LOCAL BUSINESS SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('local_business_schema')) {

    function local_business_schema(
        array $data = []
    ): void {

        add_schema([

            '@context' => 'https://schema.org',

            '@type' => 'LocalBusiness',

            'name' => $data['name'] ?? SITE_NAME,

            'image' => $data['image']
                ?? (function_exists('theme_og_image_url') ? theme_og_image_url() : asset('images/og-default.jpg')),

            'url' => $data['url']
                ?? SITE_URL,

            'telephone' => $data['phone']
                ?? SITE_PHONE,

            'email' => $data['email']
                ?? SITE_EMAIL,

            'description' => $data['description']
                ?? DEFAULT_META_DESCRIPTION,

            'address' => [
                '@type' => 'PostalAddress',

                'streetAddress' =>
                    $data['streetAddress']
                    ?? '',

                'addressLocality' =>
                    $data['city']
                    ?? '',

                'addressRegion' =>
                    $data['province']
                    ?? '',

                'postalCode' =>
                    $data['postalCode']
                    ?? '',

                'addressCountry' =>
                    $data['country']
                    ?? 'ID',
            ],

            'openingHours' =>
                $data['openingHours']
                ?? 'Mo-Su 00:00-23:59',

            'priceRange' =>
                $data['priceRange']
                ?? '$$',
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| PRODUCT SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('product_schema')) {

    function product_schema(array $product): void
    {
        add_schema([

            '@context' => 'https://schema.org',

            '@type' => 'Product',

            'name' => $product['name'] ?? '',

            'image' => [
                $product['image'] ?? '',
            ],

            'description' =>
                $product['description'] ?? '',

            'sku' =>
                $product['sku'] ?? '',

            'brand' => [
                '@type' => 'Brand',
                'name' => SITE_NAME,
            ],

            'offers' => [

                '@type' => 'Offer',

                'url' =>
                    $product['url']
                    ?? current_url(),

                'priceCurrency' => 'IDR',

                'price' =>
                    $product['price']
                    ?? 0,

                'availability' =>
                    'https://schema.org/InStock',

                'itemCondition' =>
                    'https://schema.org/NewCondition',
            ],
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| ARTICLE SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('article_schema')) {

    function article_schema(array $article): void
    {
        add_schema([

            '@context' => 'https://schema.org',

            '@type' => $article['schema_type'] ?? 'Article',

            'headline' =>
                $article['title'] ?? '',

            'description' =>
                $article['description'] ?? $article['excerpt'] ?? '',

            'image' => [
                $article['image']
                ?? DEFAULT_OG_IMAGE
            ],

            'author' => [
                '@type' => 'Organization',
                'name' => SITE_NAME,
            ],

            'publisher' => [
                '@type' => 'Organization',

                'name' => SITE_NAME,

                'logo' => [
                    '@type' => 'ImageObject',

                    'url' => function_exists('theme_logo_url') ? theme_logo_url() : asset('images/logo.png'),
                ],
            ],

            'datePublished' =>
                $article['published_at']
                ?? date('c'),

            'dateModified' =>
                $article['updated_at']
                ?? date('c'),

            'mainEntityOfPage' => [
                '@type' => 'WebPage',

                '@id' => canonical_url(),
            ],
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| FAQ SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('faq_schema')) {

    function faq_schema(array $faqs): void
    {
        $items = [];

        foreach ($faqs as $faq) {

            $items[] = [

                '@type' => 'Question',

                'name' => strip_tags(
                    $faq['question']
                ),

                'acceptedAnswer' => [

                    '@type' => 'Answer',

                    'text' => strip_tags(
                        $faq['answer']
                    ),
                ],
            ];
        }

        add_schema([

            '@context' => 'https://schema.org',

            '@type' => 'FAQPage',

            'mainEntity' => $items,
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| BREADCRUMB SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('breadcrumb_schema')) {

    function breadcrumb_schema(array $items): void
    {
        $list = [];

        foreach ($items as $index => $item) {

            $list[] = [

                '@type' => 'ListItem',

                'position' => $index + 1,

                'name' => $item['name'],

                'item' => $item['url'],
            ];
        }

        add_schema([

            '@context' => 'https://schema.org',

            '@type' => 'BreadcrumbList',

            'itemListElement' => $list,
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| WEBSITE SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('website_schema')) {

    function website_schema(): void
    {
        add_schema([

            '@context' => 'https://schema.org',

            '@type' => 'WebSite',

            'name' => SITE_NAME,

            'url' => SITE_URL,

            'potentialAction' => [

                '@type' => 'SearchAction',

                'target' =>
                    SITE_URL .
                    '/search.php?q={search_term_string}',

                'query-input' =>
                    'required name=search_term_string',
            ],
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| SERVICE SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('service_schema')) {

    function service_schema(array $service): void
    {
        add_schema([

            '@context' => 'https://schema.org',

            '@type' => 'Service',

            'name' =>
                $service['name'] ?? '',

            'description' =>
                $service['description'] ?? '',

            'provider' => [
                '@type' => 'Organization',

                'name' => SITE_NAME,
            ],

            'areaServed' =>
                $service['area']
                ?? 'Indonesia',
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| PAGE DEFAULT SCHEMA
|--------------------------------------------------------------------------
*/

if (!function_exists('default_schema')) {

    function default_schema(): void
    {
        organization_schema();

        website_schema();

        local_business_schema();
    }
}