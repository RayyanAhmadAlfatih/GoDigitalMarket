<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!empty($GLOBALS['admin_page'])) {
    ?>
                <script>
                (function(){
                    function directInScope(scope, selector) {
                        return Array.from(scope.querySelectorAll(selector)).filter(function(el){
                            return el.closest('[data-admin-page-tab-scope]') === scope;
                        });
                    }
                    function initAdminTabScope(scope) {
                        const tabs = directInScope(scope, '[data-admin-page-tab]');
                        const panels = directInScope(scope, '[data-admin-page-tab-panel]');
                        const selects = directInScope(scope, '[data-admin-page-tab-select]');
                        if (!tabs.length || !panels.length) return;

                        function activate(target) {
                            if (!target) return;
                            tabs.forEach(function(tab){
                                const active = tab.dataset.adminPageTab === target;
                                tab.classList.toggle('is-active', active);
                                tab.setAttribute('aria-selected', active ? 'true' : 'false');
                            });
                            panels.forEach(function(panel){
                                const active = panel.dataset.adminPageTabPanel === target;
                                panel.classList.toggle('is-active', active);
                                panel.hidden = !active;
                            });
                            selects.forEach(function(select){
                                if (select.value !== target) select.value = target;
                            });
                        }

                        tabs.forEach(function(tab){
                            tab.addEventListener('click', function(){ activate(tab.dataset.adminPageTab || ''); });
                        });
                        selects.forEach(function(select){
                            select.addEventListener('change', function(){ activate(select.value); });
                        });
                    }

                    document.querySelectorAll('[data-admin-page-tab-scope]').forEach(initAdminTabScope);
                })();
                </script>
                </div><!-- /.admin-dashboard-main -->
            </div><!-- /.admin-dashboard-layout -->
        </body>
        </html>
    <?php
    return;
}

?>
</main>

<?php if (!empty($GLOBALS['landing_page_focus_footer'])): ?>
<?php
$landingMiniFooterPage = (array)($GLOBALS['landing_page_public'] ?? []);
$landingMiniFooterBrand = trim((string)($landingMiniFooterPage['mini_footer_brand'] ?? '')) ?: SITE_NAME;
$landingMiniFooterText = trim((string)($landingMiniFooterPage['mini_footer_text'] ?? '')) ?: ('Landing page fokus penawaran · ' . date('Y'));
$landingMiniFooterVars = [];
$landingMiniFooterBg = trim((string)($landingMiniFooterPage['mini_footer_bg'] ?? ''));
$landingMiniFooterTextColor = trim((string)($landingMiniFooterPage['mini_footer_text_color'] ?? ''));
$landingMiniFooterBrandColor = trim((string)($landingMiniFooterPage['mini_footer_brand_color'] ?? ''));
$landingMiniFooterTextSize = trim((string)($landingMiniFooterPage['mini_footer_text_size'] ?? ''));
$landingMiniFooterAlign = trim((string)($landingMiniFooterPage['mini_footer_align'] ?? ''));
$landingMiniFooterBg = function_exists('landing_page_clean_color') ? landing_page_clean_color($landingMiniFooterBg) : $landingMiniFooterBg;
$landingMiniFooterTextColor = function_exists('landing_page_clean_color') ? landing_page_clean_color($landingMiniFooterTextColor) : $landingMiniFooterTextColor;
$landingMiniFooterBrandColor = function_exists('landing_page_clean_color') ? landing_page_clean_color($landingMiniFooterBrandColor) : $landingMiniFooterBrandColor;
$landingMiniFooterTextSize = function_exists('landing_page_clean_px') ? landing_page_clean_px($landingMiniFooterTextSize, 11, 22) : $landingMiniFooterTextSize;
$landingMiniFooterCustom = false;
if ($landingMiniFooterBg !== '') { $landingMiniFooterVars[] = '--lp-mini-footer-bg:' . $landingMiniFooterBg; $landingMiniFooterVars[] = 'background:' . $landingMiniFooterBg . ' !important'; $landingMiniFooterCustom = true; }
if ($landingMiniFooterTextColor !== '') { $landingMiniFooterVars[] = '--lp-mini-footer-text:' . $landingMiniFooterTextColor; $landingMiniFooterVars[] = 'color:' . $landingMiniFooterTextColor . ' !important'; $landingMiniFooterCustom = true; }
if ($landingMiniFooterBrandColor !== '') { $landingMiniFooterVars[] = '--lp-mini-footer-brand:' . $landingMiniFooterBrandColor; $landingMiniFooterCustom = true; }
if ($landingMiniFooterTextSize !== '') { $landingMiniFooterVars[] = '--lp-mini-footer-size:' . $landingMiniFooterTextSize; $landingMiniFooterVars[] = 'font-size:' . $landingMiniFooterTextSize . ' !important'; $landingMiniFooterCustom = true; }
if (in_array($landingMiniFooterAlign, ['left', 'center', 'right'], true)) { $landingMiniFooterVars[] = '--lp-mini-footer-align:' . $landingMiniFooterAlign; }
$landingMiniFooterAttr = $landingMiniFooterVars ? ' style="' . esc(implode(';', $landingMiniFooterVars)) . '"' : '';
$landingMiniFooterClass = 'landing-mini-footer' . ($landingMiniFooterCustom ? ' landing-mini-footer--custom' : '') . (in_array($landingMiniFooterAlign, ['left', 'center', 'right'], true) ? ' landing-mini-footer--' . $landingMiniFooterAlign : '');
$landingMiniFooterBrandAttr = $landingMiniFooterBrandColor !== '' ? ' style="color:' . esc($landingMiniFooterBrandColor) . ' !important"' : '';
$landingMiniFooterTextAttr = $landingMiniFooterTextColor !== '' ? ' style="color:' . esc($landingMiniFooterTextColor) . ' !important"' : '';
?>
<footer class="<?= esc($landingMiniFooterClass); ?>"<?= $landingMiniFooterAttr; ?>>
    <div class="container"><strong<?= $landingMiniFooterBrandAttr; ?>><?= esc($landingMiniFooterBrand); ?></strong><span<?= $landingMiniFooterTextAttr; ?>><?= esc($landingMiniFooterText); ?></span></div>
</footer>
<script>window.__LEAD_TRACKING_ENDPOINT__ = '<?= esc(url('lead-event')); ?>';</script>
<script src="<?= asset('js/app.js'); ?>" defer></script>
</body>
</html>
<?php return; ?>
<?php endif; ?>

<?php
$footerSettings = function_exists('navigation_settings') ? navigation_settings() : [];
$footerOptions = (array)($footerSettings['footer'] ?? []);
$footerColumns = function_exists('navigation_footer_columns') ? navigation_footer_columns() : [];
$footerBottomLinks = function_exists('navigation_bottom_links') ? navigation_bottom_links() : [];
$footerSocialLinks = function_exists('theme_social_links') ? theme_social_links() : [];
$showFooterBrand = !array_key_exists('show_brand_column', $footerOptions) || !empty($footerOptions['show_brand_column']);
$showFooterSocial = !array_key_exists('show_social_links', $footerOptions) || !empty($footerOptions['show_social_links']);
$showFooterContact = !array_key_exists('show_contact_line', $footerOptions) || !empty($footerOptions['show_contact_line']);
$footerDescription = trim((string)($footerOptions['brand_description'] ?? DEFAULT_META_DESCRIPTION));
?>
<footer class="site-footer">
    <div class="footer-top">
        <div class="container footer-grid <?= !$showFooterBrand ? 'footer-grid--links-only' : ''; ?>">
            <?php if ($showFooterBrand): ?>
            <div class="footer-column footer-column--brand">
                <h2 class="footer-title"><?= esc(SITE_NAME); ?></h2>
                <p class="footer-description"><?= esc($footerDescription !== '' ? $footerDescription : DEFAULT_META_DESCRIPTION); ?></p>
                <?php if ($showFooterSocial && $footerSocialLinks): ?>
                    <div class="footer-social">
                        <?php foreach ($footerSocialLinks as $label => $link): ?>
                            <a href="<?= esc($link); ?>" target="_blank" rel="nofollow noopener"><?= esc($label); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($showFooterContact): ?>
                <p class="footer-description footer-description--contact">
                    <?= esc(SITE_ADDRESS); ?> · <?= esc(SITE_PHONE); ?> · <?= esc(SITE_EMAIL); ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php foreach ($footerColumns as $column): ?>
                <?php $links = (array)($column['links'] ?? []); ?>
                <?php if (trim((string)($column['title'] ?? '')) !== '' && $links): ?>
                <div class="footer-column">
                    <h2 class="footer-title"><?= esc((string)$column['title']); ?></h2>
                    <?php if (function_exists('navigation_render_footer_links')) { navigation_render_footer_links($links); } ?>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container footer-bottom-wrapper">
            <p><?= esc(function_exists('navigation_copyright_text') ? navigation_copyright_text() : ('© ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.')); ?></p>
            <?php if ($footerBottomLinks): ?>
            <nav class="footer-bottom-nav" aria-label="Footer Navigation">
                <?php foreach ($footerBottomLinks as $link): ?>
                    <?php if (!empty($link['enabled']) && trim((string)($link['label'] ?? '')) !== ''): ?>
                        <a href="<?= esc(function_exists('navigation_url_to_href') ? navigation_url_to_href((string)$link['url']) : url((string)$link['url'])); ?>"<?= function_exists('navigation_target_attrs') ? navigation_target_attrs(!empty($link['new_tab'])) : ''; ?>><?= esc((string)$link['label']); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</footer>

<?php if (empty($GLOBALS['landing_page_disable_floating_wa'])): ?>
<a href="<?= esc(wa_link_contextual('Halo, saya ingin konsultasi produk atau layanan.', ['source'=>'Floating WhatsApp','title'=>'Chat Tim Support','category'=>'Produk & Layanan'])); ?>" class="floating-wa" target="_blank" rel="nofollow noopener" aria-label="Chat WhatsApp" <?= conversion_link_attrs(['source'=>'floating-wa','type'=>'whatsapp','category'=>'produk-layanan','label'=>'Chat Tim Support','intent'=>'consultation']); ?>>
    <div class="floating-wa-icon">💬</div><div class="floating-wa-text"><strong>Chat Tim Support</strong></div>
</a>
<div class="sticky-mobile-cta">
    <a href="<?= esc(wa_link_contextual('Halo, saya ingin bertanya tentang produk atau layanan.', ['source'=>'Sticky Mobile CTA','title'=>'Chat WhatsApp','category'=>'Produk & Layanan'])); ?>" target="_blank" rel="nofollow noopener" <?= conversion_link_attrs(['source'=>'sticky-mobile-cta','type'=>'whatsapp','category'=>'produk-layanan','label'=>'Chat WhatsApp Mobile','intent'=>'consultation']); ?>>Chat WhatsApp</a>
</div>
<?php endif; ?>

<script>window.__LEAD_TRACKING_ENDPOINT__ = '<?= esc(url('lead-event')); ?>';</script>
<script src="<?= asset('js/app.js'); ?>" defer></script>
</body>
</html>
