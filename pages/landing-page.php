<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

$slug = slugify((string)($_GET['slug'] ?? ''));
$page = function_exists('landing_page_public_find') ? landing_page_public_find($slug) : null;
if ($page && function_exists('landing_page_ab_prepare_public_page')) {
    $page = landing_page_ab_prepare_public_page($page);
}

if (!$page) {
    http_response_code(404);
    require PAGES_PATH . '/404.php';
    return;
}

$focusMode = (string)($page['layout_mode'] ?? 'focus') === 'focus';
$navOnly = !empty($page['show_nav_only']);
$GLOBALS['landing_page_nav_only'] = $navOnly;
$GLOBALS['landing_page_focus_no_header'] = $focusMode && !empty($page['hide_header']) && !$navOnly;
$GLOBALS['landing_page_focus_footer'] = $focusMode && !empty($page['hide_footer']);
$GLOBALS['landing_page_disable_floating_wa'] = $focusMode && !empty($page['hide_floating_wa']);
$GLOBALS['landing_page_public'] = $page;

$canonical = function_exists('seo_preservation_landing_canonical') ? seo_preservation_landing_canonical($page) : landing_page_url((string)$page['slug']);
$metaTitle = trim((string)($page['meta_title'] ?? '')) ?: (string)$page['title'];
$metaDescription = trim((string)($page['meta_description'] ?? '')) ?: limit_words(strip_tags(json_encode($page['blocks'] ?? [], JSON_UNESCAPED_UNICODE) ?: ''), 24);
$ogImage = trim((string)($page['og_image'] ?? '')) ?: DEFAULT_OG_IMAGE;
$landingBreadcrumbTrail = function_exists('breadcrumb_migration_trail') ? breadcrumb_migration_trail($page, 'landing_page', (string)$page['title'], $canonical) : [
    ['name' => 'Home', 'url' => url('/')],
    ['name' => 'Landing Page', 'url' => url('lp/' . (string)$page['slug'])],
    ['name' => (string)$page['title'], 'url' => $canonical],
];

set_seo([
    'title' => meta_title($metaTitle),
    'description' => $metaDescription,
    'keywords' => (string)($page['meta_keywords'] ?? DEFAULT_META_KEYWORDS),
    'image' => $ogImage,
    'url' => $canonical,
    'canonical' => $canonical,
    'robots' => !empty($page['indexable']) ? 'index, follow' : 'noindex, follow',
    'type' => 'website',
]);

if (function_exists('breadcrumb_schema')) {
    breadcrumb_schema($landingBreadcrumbTrail);
}

if (function_exists('add_schema')) {
    add_schema([
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => (string)$page['title'],
        'description' => $metaDescription,
        'url' => $canonical,
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => SITE_NAME,
            'url' => SITE_URL,
        ],
    ]);
}

if (function_exists('landing_page_register_block_schemas')) {
    landing_page_register_block_schemas($page, $canonical, $metaDescription);
}

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
if (function_exists('content_restriction_allowed')) {
    $restrictionStatus = content_restriction_allowed('landing_page', $page);
    if (empty($restrictionStatus['allowed'])) {
        content_restriction_render_gate($restrictionStatus, (string)$page['title']);
        require_once ROOT_PATH . '/components/layout/footer.php';
        return;
    }
}
?>

<?php
$landingMotionEnabled = !empty($page['motion_enabled']) && (!function_exists('theme_motion_enabled') || theme_motion_enabled('landing'));
$landingMotionStyle = in_array((string)($page['motion_style'] ?? 'fade-up'), ['fade-up', 'zoom-soft', 'fade'], true) ? (string)$page['motion_style'] : 'fade-up';
$landingFullHtmlMode = !empty($page['full_html_mode']) && trim((string)($page['raw_html_document'] ?? '')) !== '';
$landingHasCountdown = false;
foreach ((array)($page['blocks'] ?? []) as $landingBlock) {
    if (is_array($landingBlock) && (string)($landingBlock['type'] ?? '') === 'countdown_timer') {
        $landingHasCountdown = true;
        break;
    }
}
?>
<main id="main-content" class="landing-page-builder <?= $focusMode ? 'landing-page-builder--focus' : 'landing-page-builder--website'; ?> <?= $landingFullHtmlMode ? 'landing-page-builder--full-html' : ''; ?> <?= $landingMotionEnabled ? 'landing-motion-enabled landing-motion-' . esc($landingMotionStyle) : ''; ?>" data-landing-page="<?= esc((string)$page['slug']); ?>" data-lp-renderer="v33.1.15">
    <?php if (!$focusMode && !$navOnly): ?>
        <section class="mini-hero landing-page-breadcrumb-hero">
            <div class="container">
                <?php if (function_exists('breadcrumb_migration_render')) { breadcrumb_migration_render($landingBreadcrumbTrail); } ?>
                <h1><?= esc((string)$page['title']); ?></h1>
                <p><?= esc($metaDescription); ?></p>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($landingFullHtmlMode): ?>
        <section class="lp-section lp-full-html-document" data-landing-block="full_html_document" data-builder-mode="expert-html">
            <?= function_exists('landing_page_sanitize_full_html_document') ? landing_page_sanitize_full_html_document((string)($page['raw_html_document'] ?? '')) : '' ?>
        </section>
    <?php else: ?>
        <?php landing_page_render_blocks($page); ?>
    <?php endif; ?>

<script>
window.__MARKETING_PAGE_EVENT__ = Object.assign({}, window.__MARKETING_PAGE_EVENT__ || {}, {
    source: 'landing-page-builder',
    type: 'landing_page_view',
    channel: 'landing_page',
    category: 'landing-page',
    intent: 'ads-landing-page-view',
    label: <?= json_encode((string)($page['tracking_label'] ?? $page['title']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    landing_page_slug: <?= json_encode((string)$page['slug'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    landing_page_id: <?= json_encode((string)($page['id'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    page_path: window.location.pathname + window.location.search,
    event_id: <?= json_encode('lp_view_' . substr(md5((string)$page['slug'] . date('YmdH')), 0, 16), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    ...<?= json_encode(function_exists('landing_page_ab_page_event_payload') ? landing_page_ab_page_event_payload() : [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
});
</script>
<?php if ($landingHasCountdown): ?>
<script>
(function(){
    const timers = document.querySelectorAll('[data-lp-countdown="1"]');
    if (!timers.length) return;
    const pad = value => String(Math.max(0, value)).padStart(2, '0');
    function parseDeadline(value){
        if (!value) return null;
        const normalized = String(value).trim();
        const date = new Date(normalized);
        return Number.isNaN(date.getTime()) ? null : date;
    }
    function updateTimer(timer){
        const deadline = parseDeadline(timer.dataset.deadline || '');
        const expired = timer.querySelector('[data-countdown-expired]');
        if (!deadline) return;
        const diff = deadline.getTime() - Date.now();
        const done = diff <= 0;
        const totalSeconds = Math.max(0, Math.floor(diff / 1000));
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        const map = {days: days, hours: hours, minutes: minutes, seconds: seconds};
        Object.keys(map).forEach(function(key){
            const el = timer.querySelector('[data-countdown-' + key + ']');
            if (el) el.textContent = pad(map[key]);
        });
        timer.classList.toggle('is-expired', done);
        if (expired) expired.hidden = !done;
    }
    timers.forEach(function(timer){
        updateTimer(timer);
        window.setInterval(function(){ updateTimer(timer); }, 1000);
    });
})();
</script>
<?php endif; ?>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
