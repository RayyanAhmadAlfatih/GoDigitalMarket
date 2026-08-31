<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GLOBAL HEAD LAYOUT
|--------------------------------------------------------------------------
| Production-grade SEO head renderer
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

/*
|--------------------------------------------------------------------------
| DEFAULT SEO FALLBACK
|--------------------------------------------------------------------------
*/

$title = seo('title') ?: DEFAULT_META_TITLE;

$description = seo('description') ?: DEFAULT_META_DESCRIPTION;

$image = seo('image') ?: DEFAULT_OG_IMAGE;

$url = canonical_url();

$type = seo('type') ?: 'website';

/*
|--------------------------------------------------------------------------
| PAGE SPEED OPTIMIZATION
|--------------------------------------------------------------------------
*/

$cssVersion = function_exists('asset_version')
    ? asset_version('assets/css/app.css')
    : (string) time();

$cssFile = asset('css/app.css') . '?v=' . rawurlencode((string) $cssVersion);

$jsFile = asset('js/app.js');

$adminCssVersion = function_exists('asset_version')
    ? asset_version('assets/css/admin.css')
    : (string) time();

$adminCssFile = asset('css/admin.css') . '?v=' . rawurlencode((string) $adminCssVersion);

/*
|--------------------------------------------------------------------------
| OPTIONAL PRELOAD IMAGE
|--------------------------------------------------------------------------
*/

$preloadImage = $GLOBALS['preload_image'] ?? null;

if (function_exists('analytics_capture_attribution')) {
    analytics_capture_attribution();
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <?php render_seo(); ?>

    <?php pagination_rel_links((int)($GLOBALS['pagination_total_pages'] ?? 1)); ?>

    <?php
    if (function_exists('organization_schema')) {
        organization_schema();
    }

    if (function_exists('website_schema')) {
        website_schema();
    }

    if (function_exists('universal_seo_business_schema')) {
        universal_seo_business_schema();
    }
    ?>

    <?php render_schema(); ?>

    <?php if (function_exists('analytics_render_head')) { analytics_render_head(); } ?>

    <!-- DNS PREFETCH -->

<!-- PRECONNECT -->

    <!-- APPLE -->
    <meta name="apple-mobile-web-app-capable" content="yes">

    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <!-- MOBILE -->
    <meta name="format-detection" content="telephone=no">

    <!-- PERFORMANCE -->
    <meta http-equiv="x-dns-prefetch-control" content="on">

    <!-- GOOGLE FONTS -->
<!-- PRELOAD CSS -->
    <link
        rel="preload"
        href="<?= esc($cssFile); ?>"
        as="style">

    <!-- MAIN CSS -->
    <link
        rel="stylesheet"
        href="<?= esc($cssFile); ?>">

    <?php if (empty($GLOBALS['admin_page']) && function_exists('theme_render_style')) { theme_render_style(); } ?>

    <?php if (!empty($GLOBALS['admin_page'])): ?>

        <link
            rel="stylesheet"
            href="<?= esc($adminCssFile); ?>">

        <?php if (function_exists('theme_render_style')) { theme_render_style(); } ?>

    <?php endif; ?>

    <!-- PRELOAD HERO IMAGE -->
    <?php if ($preloadImage): ?>

        <link
            rel="preload"
            as="image"
            href="<?= esc($preloadImage); ?>">

    <?php endif; ?>

    <!-- FAVICON -->
    <link
        rel="icon"
        type="image/png"
        href="<?= esc(function_exists('theme_favicon_url') ? theme_favicon_url() : asset('images/favicon.png')); ?>">

    <!-- MANIFEST -->
    <link
        rel="manifest"
        href="<?= esc(url('manifest.json')); ?>">

    <!-- THEME -->
    <meta
        name="theme-color"
        content="<?= esc(function_exists('theme_color') ? theme_color('primary_dark_color', '#0f172a') : '#0f172a'); ?>">

    <!-- MICRODATA -->
    <meta
        itemprop="name"
        content="<?= esc($title); ?>">

    <meta
        itemprop="description"
        content="<?= esc($description); ?>">

    <meta
        itemprop="image"
        content="<?= esc($image); ?>">

    <!-- TWITTER -->
    <meta
        name="twitter:url"
        content="<?= esc($url); ?>">

    <!-- ROBOTS -->
    <meta
        name="googlebot"
        content="<?= esc(function_exists('seo_robots_content') ? seo_robots_content((string)(seo('robots') ?: 'index, follow')) : (string)(seo('robots') ?: 'index, follow')); ?>">

    <!-- AUTHOR -->
    <meta
        name="publisher"
        content="<?= esc(SITE_NAME); ?>">

    <!-- GEO -->
    <meta
        name="geo.region"
        content="ID">

    <meta
        name="geo.country"
        content="Indonesia">

    <!-- LANGUAGE -->
    <meta
        http-equiv="content-language"
        content="id-ID">

</head>