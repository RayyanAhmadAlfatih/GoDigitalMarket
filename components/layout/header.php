<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GLOBAL HEADER LAYOUT
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$__headerRequestPath = trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? ''), '/');
$__headerBasePath = trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?? ''), '/');
if ($__headerBasePath !== '' && str_starts_with($__headerRequestPath, $__headerBasePath . '/')) {
    $__headerRequestPath = trim(substr($__headerRequestPath, strlen($__headerBasePath) + 1), '/');
}
$__headerLooksLikeAdmin = $__headerRequestPath === 'admin' || str_starts_with($__headerRequestPath, 'admin/') || str_starts_with($__headerRequestPath, 'admin-');
if (!empty($GLOBALS['admin_page']) || $__headerLooksLikeAdmin) {
    $GLOBALS['admin_page'] = true;
    $adminLayoutUri = $__headerRequestPath;
    $adminBasePath = trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?? ''), '/');

    if ($adminBasePath !== '' && str_starts_with($adminLayoutUri, $adminBasePath)) {
        $adminLayoutUri = trim(substr($adminLayoutUri, strlen($adminBasePath)), '/');
    }

    $adminMenuGroups = function_exists('admin_menu_groups') ? admin_menu_groups() : [];

    if (function_exists('admin_users_filter_menu_groups')) {
        $adminMenuGroups = admin_users_filter_menu_groups($adminMenuGroups);
    }

    if (function_exists('admin_menu_filter_visibility')) {
        $adminMenuGroups = admin_menu_filter_visibility($adminMenuGroups);
    }

    $adminIsActive = static function (array $patterns) use ($adminLayoutUri): bool {
        foreach ($patterns as $pattern) {
            $pattern = trim((string)$pattern, '/');
            if ($pattern !== '' && $adminLayoutUri === $pattern) {
                return true;
            }
        }
        return false;
    };

    $activeAdminLabel = 'Admin Panel';
    foreach ($adminMenuGroups as $group) {
        foreach ($group['items'] as $item) {
            if ($adminIsActive((array)($item['match'] ?? []))) {
                $activeAdminLabel = (string)$item['label'];
                break 2;
            }
        }
    }

    $adminLoggedIn = function_exists('admin_auth_is_logged_in') ? admin_auth_is_logged_in() : !empty($_SESSION['admin_articles_logged_in']);
    $adminCurrentUser = function_exists('admin_auth_current_user') ? admin_auth_current_user() : [];
    $adminCurrentRole = (string)($adminCurrentUser['role'] ?? 'owner');
    $adminRoleLabel = function_exists('admin_users_role_label') ? admin_users_role_label($adminCurrentRole) : 'Owner / Admin';
    $adminUserName = trim((string)($adminCurrentUser['name'] ?? $adminCurrentUser['email'] ?? $adminCurrentUser['username'] ?? 'Admin'));
    ?>
    <body class="admin-dashboard-body <?= esc(function_exists('theme_body_motion_classes') ? theme_body_motion_classes('admin') : ''); ?>">
        <a href="#admin-content" class="skip-link">Lewati ke Konten Admin</a>
        <input type="checkbox" id="adminSidebarToggle" class="admin-sidebar-toggle" aria-hidden="true">
        <div class="admin-dashboard-layout">
            <aside class="admin-sidebar" aria-label="Navigasi Admin Panel">
                <div class="admin-sidebar__brand">
                    <a href="<?= esc(url('admin/brand')); ?>" aria-label="Dashboard Admin Panel">
                        <span class="admin-sidebar__logo"><?= esc(function_exists('theme_brand_initials') ? theme_brand_initials() : 'UC'); ?></span>
                        <span><strong>Dashboard Admin</strong><small><?= esc(SITE_NAME); ?></small></span>
                    </a>
                    <label class="admin-sidebar__close" for="adminSidebarToggle" aria-label="Tutup menu admin">×</label>
                </div>
                <nav class="admin-sidebar__nav">
                    <?php foreach ($adminMenuGroups as $group): ?>
                        <?php
                        $groupOpen = false;
                        foreach ($group['items'] as $item) {
                            if ($adminIsActive((array)($item['match'] ?? []))) { $groupOpen = true; break; }
                        }
                        ?>
                        <details class="admin-sidebar__group" <?= $groupOpen ? 'open' : ''; ?>>
                            <summary><?= esc((string)$group['label']); ?></summary>
                            <ul>
                                <?php foreach ($group['items'] as $item): ?>
                                    <?php $itemActive = $adminIsActive((array)($item['match'] ?? [])); ?>
                                    <li><a href="<?= esc((string)$item['href']); ?>" class="<?= $itemActive ? 'is-active' : ''; ?>" <?= $itemActive ? 'aria-current="page"' : ''; ?>><span class="admin-sidebar__icon" aria-hidden="true"><?= esc((string)$item['icon']); ?></span><span><?= esc((string)$item['label']); ?></span></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endforeach; ?>
                </nav>
                <div class="admin-sidebar__footer"><a href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Website</a></div>
            </aside>
            <label class="admin-sidebar-backdrop" for="adminSidebarToggle" aria-hidden="true"></label>
            <div class="admin-dashboard-main">
                <header class="admin-dashboard-topbar">
                    <div class="admin-dashboard-topbar__title">
                        <label class="admin-sidebar-trigger" for="adminSidebarToggle" aria-label="Buka menu admin">☰ Menu</label>
                        <div><span>Admin Panel</span><strong><?= esc($activeAdminLabel); ?></strong></div>
                    </div>
                    <div class="admin-dashboard-topbar__actions">
                        <?php if ($adminLoggedIn): ?>
                            <span class="admin-role-chip" title="<?= esc($adminRoleLabel); ?><?= $adminUserName !== '' ? ' · ' . esc($adminUserName) : ''; ?>" aria-label="Role aktif: <?= esc($adminRoleLabel); ?><?= $adminUserName !== '' ? ' · ' . esc($adminUserName) : ''; ?>">
                                <span class="admin-role-chip__role"><?= esc($adminRoleLabel); ?></span>
                                <?php if ($adminUserName !== ''): ?><span class="admin-role-chip__name"><?= esc($adminUserName); ?></span><?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($adminLoggedIn && function_exists('admin_help_render_popover')) { admin_help_render_popover($adminLayoutUri); } ?>
                        <a href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Website</a>
                        <?= $adminLoggedIn ? '<a href="' . esc(url('admin/logout')) . '">Keluar</a>' : '<a href="' . esc(url('admin/login')) . '">Login</a>'; ?>
                    </div>
                </header>
                <div id="admin-content" class="admin-dashboard-content-anchor"></div>
    <?php
    return;
}
?>

<?php if (!empty($GLOBALS['landing_page_focus_no_header'])): ?>
<body class="landing-focus-body <?= esc(function_exists('theme_body_motion_classes') ? theme_body_motion_classes('public landing') : ''); ?>">
<?php if (function_exists('analytics_render_body_noscript')) { analytics_render_body_noscript(); } ?>
<a href="#main-content" class="skip-link">Lewati ke Konten</a>
<?php return; ?>
<?php endif; ?>

<?php
$landingNavOnly = !empty($GLOBALS['landing_page_nav_only']);
$navHeader = function_exists('navigation_settings') ? (array)(navigation_settings()['header'] ?? []) : [];
$headerShowTopbar = !empty($navHeader['show_topbar']);
$headerShowTopbarPhone = !empty($navHeader['show_topbar_phone']);
$headerShowTopbarWhatsApp = !empty($navHeader['show_topbar_whatsapp']);
$headerShowLogo = !array_key_exists('show_logo', $navHeader) || !empty($navHeader['show_logo']);
$headerShowMenu = !array_key_exists('show_menu', $navHeader) || !empty($navHeader['show_menu']);
$headerShowSearch = !array_key_exists('show_search', $navHeader) || !empty($navHeader['show_search']);
$headerShowCta = !empty($navHeader['show_header_cta']);
$headerTopbarText = trim((string)($navHeader['topbar_text'] ?? SITE_TAGLINE));
$headerSearchPlaceholder = trim((string)($navHeader['search_placeholder'] ?? 'Cari produk, layanan, atau artikel...'));
$headerCtaLabel = trim((string)($navHeader['header_cta_label'] ?? 'Konsultasi'));
$headerCtaUrl = function_exists('navigation_url_to_href') ? navigation_url_to_href((string)($navHeader['header_cta_url'] ?? '/kontak')) : url('kontak');
$headerCtaTarget = (!empty($navHeader['header_cta_new_tab']) && function_exists('navigation_target_attrs')) ? navigation_target_attrs(true) : '';
?>
<body class="<?= $landingNavOnly ? 'landing-nav-only-body' : ''; ?> <?= esc(function_exists('theme_body_motion_classes') ? theme_body_motion_classes('public') : ''); ?>">
<?php if (function_exists('analytics_render_body_noscript')) { analytics_render_body_noscript(); } ?>
<a href="#main-content" class="skip-link">Lewati ke Konten</a>

<header class="site-header">
    <?php if (!$landingNavOnly && $headerShowTopbar): ?>
    <div class="topbar">
        <div class="container topbar-wrapper">
            <div class="topbar-left"><span><?= esc($headerTopbarText !== '' ? $headerTopbarText : SITE_TAGLINE); ?></span></div>
            <div class="topbar-right">
                <?php if ($headerShowTopbarPhone): ?><a href="tel:<?= esc(SITE_PHONE); ?>" rel="nofollow"><?= esc(SITE_PHONE); ?></a><?php endif; ?>
                <?php if ($headerShowTopbarWhatsApp): ?><a href="<?= esc(wa_link_contextual('Halo, saya ingin konsultasi tentang produk atau layanan.', ['source'=>'Header','title'=>'Tombol WhatsApp atas','category'=>'Produk & Layanan'])); ?>" target="_blank" rel="nofollow noopener" <?= conversion_link_attrs(['source'=>'header-topbar','type'=>'whatsapp','category'=>'produk-layanan','label'=>'WhatsApp Header','intent'=>'consultation']); ?>>WhatsApp</a><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="navbar">
        <div class="container navbar-wrapper">
            <?php if ($headerShowLogo): ?>
            <div class="navbar-brand">
                <a href="<?= esc(SITE_URL); ?>" title="<?= esc(SITE_NAME); ?>" aria-label="<?= esc(SITE_NAME); ?>">
                    <img src="<?= esc(function_exists('theme_logo_url') ? theme_logo_url() : asset('images/logo.png')); ?>" alt="<?= esc(SITE_NAME); ?>" width="180" height="60" loading="eager" fetchpriority="high" onerror="this.src='<?= esc(asset('images/placeholder-product.svg')); ?>';">
                </a>
            </div>
            <?php else: ?>
            <div class="navbar-brand navbar-brand--text"><a href="<?= esc(SITE_URL); ?>"><?= esc(SITE_NAME); ?></a></div>
            <?php endif; ?>
            <button class="mobile-toggle" aria-label="Buka Menu" aria-expanded="false" aria-controls="mobileMenu">☰</button>
            <?php if ($headerShowMenu): ?>
            <nav class="main-navigation" aria-label="Navigasi Utama">
                <?php if (function_exists('navigation_render_menu')) { navigation_render_menu(false); } ?>
                <?php if ($headerShowCta && $headerCtaLabel !== ''): ?>
                    <a class="header-cta-btn" href="<?= esc($headerCtaUrl); ?>"<?= $headerCtaTarget; ?>><?= esc($headerCtaLabel); ?></a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$landingNavOnly && $headerShowSearch): ?>
    <div class="header-search">
        <form action="<?= url('search'); ?>" method="get" class="header-search-form">
            <input type="text" name="q" placeholder="<?= esc($headerSearchPlaceholder !== '' ? $headerSearchPlaceholder : 'Cari produk, layanan, atau artikel...'); ?>" autocomplete="off">
            <button type="submit">🔍</button>
        </form>
    </div>
    <?php endif; ?>
</header>

<div class="mobile-overlay"></div>
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header"><strong>Menu</strong><button class="mobile-close" aria-label="Tutup Menu">✕</button></div>
    <nav aria-label="Mobile Navigation">
        <?php if ($headerShowMenu && function_exists('navigation_render_menu')) { navigation_render_menu(true); } ?>
        <?php if ($headerShowCta && $headerCtaLabel !== ''): ?>
            <a class="mobile-header-cta" href="<?= esc($headerCtaUrl); ?>"<?= $headerCtaTarget; ?>><?= esc($headerCtaLabel); ?></a>
        <?php endif; ?>
    </nav>
</div>

<?php if (empty($GLOBALS['landing_page_public'])): ?>
<main id="main-content">
<?php endif; ?>
