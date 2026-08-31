<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| APP START
|--------------------------------------------------------------------------
*/

define('APP_START', microtime(true));

/*
|--------------------------------------------------------------------------
| LOAD CONFIG
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/app.php';

/*
|--------------------------------------------------------------------------
| PAGE CACHE START
|--------------------------------------------------------------------------
*/

page_cache_start(3600);

/*
|--------------------------------------------------------------------------
| URI
|--------------------------------------------------------------------------
*/

$uri = parse_url(

    $_SERVER['REQUEST_URI'] ?? '/',

    PHP_URL_PATH
);

$uri = trim($uri, '/');

/*
|--------------------------------------------------------------------------
| REMOVE SUBFOLDER
|--------------------------------------------------------------------------
|
| Support localhost subfolder
|--------------------------------------------------------------------------
*/

$basePathRaw = parse_url(
    BASE_URL,
    PHP_URL_PATH
);

$basePath = trim(
    (string) ($basePathRaw ?? ''),
    '/'
);

if (
    $basePath !== '' &&
    str_starts_with($uri, $basePath)
) {

    $uri = substr(
        $uri,
        strlen($basePath)
    );

    $uri = trim($uri, '/');
}

/*
|--------------------------------------------------------------------------
| ROUTES
|--------------------------------------------------------------------------
*/

$legacyRedirects = [
    // Legacy admin form placeholder now redirects to the active Custom Form Builder.
    'admin-form-lainnya' => 'admin/forms',
    'admin/form-lainnya' => 'admin/forms',
    'admin/other-forms' => 'admin/forms',
];

if (isset($legacyRedirects[$uri])) {
    header('Location: ' . url($legacyRedirects[$uri]), true, 301);
    exit;
}

$routes = [
    '' => 'homepage.php',

    // Public pages
    'katalog' => 'katalog.php',
    'layanan' => 'layanan.php',
    'portfolio' => 'portfolio.php',
    'portofolio' => 'portfolio.php',
    'showcase' => 'portfolio.php',
    'artikel' => 'artikel.php',
    'tentang-kami' => 'tentang-kami.php',
    'kontak' => 'kontak.php',
    'search' => 'search.php',
    'health' => 'health.php',
    'install' => 'install.php',
    'setup' => 'install.php',
    'first-run' => 'install.php',
    'lead-event' => 'lead-event.php',
    'inquiry-submit' => 'inquiry-submit.php',
    'form-submit' => 'form-submit.php',
    'order-submit' => 'order-submit.php',
    'checkout' => 'checkout.php',
    'shipping-estimate' => 'shipping-estimate.php',
    'api/shipping-estimate' => 'shipping-estimate.php',
    'form-order' => 'checkout.php',
    'order-success' => 'order-success.php',
    'order-status' => 'order-status.php',
    'cek-order' => 'order-status.php',
    'invoice' => 'invoice.php',
    'payment-proof-submit' => 'payment-proof-submit.php',
    'payment-gateway-webhook' => 'payment-gateway-webhook.php',
    'payment-gateway/webhook' => 'payment-gateway-webhook.php',
    'webhook/payment-gateway' => 'payment-gateway-webhook.php',
    'payment-gateway/pay' => 'payment-gateway-pay.php',
    'payment-return' => 'payment-return.php',
    'payment/return' => 'payment-return.php',
    'digital-access' => 'digital-access.php',
    'digital-download' => 'digital-download.php',
    'digital/akses' => 'digital-access.php',
    'digital/download' => 'digital-download.php',
    'member-area' => 'member-area.php',
    'member' => 'member-area.php',
    'member-login' => 'member-area.php',
    'akses-member' => 'member-area.php',
    'license/activate' => 'license-activate.php',
    'license/verify' => 'license-verify.php',
    'cron/server-conversions' => 'server-conversion-cron.php',
    'cron/conversion-api-retry' => 'server-conversion-cron.php',
    'server-conversion-cron' => 'server-conversion-cron.php',
    'privacy-policy' => 'privacy-policy.php',
    'terms' => 'terms.php',

    // Admin pages
    'admin' => 'admin-brand.php',
    'admin-login' => 'admin-login.php',
    'admin/login' => 'admin-login.php',
    'admin-logout' => 'admin-login.php',
    'admin/logout' => 'admin-login.php',
    'admin-password-reset' => 'admin-password-reset.php',
    'admin/password-reset' => 'admin-password-reset.php',
    'admin-forgot-password' => 'admin-password-reset.php',
    'admin/forgot-password' => 'admin-password-reset.php',
    'admin-artikel' => 'admin-artikel.php',
    'admin/artikel' => 'admin-artikel.php',
    'admin-produk' => 'admin-produk.php',
    'admin/produk' => 'admin-produk.php',
    'admin-brand' => 'admin-brand.php',
    'admin/brand' => 'admin-brand.php',
    'admin-business' => 'admin-business.php',
    'admin/business' => 'admin-business.php',
    'admin/business-mode' => 'admin-business.php',
    'admin/categories' => 'admin-business.php',
    'admin-starter-wizard' => 'admin-website-starter-wizard.php',
    'admin/starter-wizard' => 'admin-website-starter-wizard.php',
    'admin/website-starter' => 'admin-website-starter-wizard.php',
    'admin/onboarding-wizard' => 'admin-website-starter-wizard.php',
    'admin-launch-readiness' => 'admin-launch-readiness.php',
    'admin/launch-readiness' => 'admin-launch-readiness.php',
    'admin/guided-setup' => 'admin-launch-readiness.php',
    'admin-onboarding-assistant' => 'admin-onboarding-assistant.php',
    'admin/onboarding-assistant' => 'admin-onboarding-assistant.php',
    'admin/panduan-harian' => 'admin-onboarding-assistant.php',
    'admin-help-center' => 'admin-help-center.php',
    'admin/help-center' => 'admin-help-center.php',
    'admin/bantuan-dashboard' => 'admin-help-center.php',
    'admin/panduan-dashboard' => 'admin-help-center.php',
    'admin-template-content' => 'admin-template-content.php',
    'admin/template-content' => 'admin-template-content.php',
    'admin/editable-template' => 'admin-template-content.php',
    'admin/theme' => 'admin-brand.php',
    'admin-navigation' => 'admin-navigation.php',
    'admin/navigation' => 'admin-navigation.php',
    'admin/menu' => 'admin-navigation.php',
    'admin-homepage' => 'admin-homepage.php',
    'admin/homepage' => 'admin-homepage.php',
    'admin-trust-conversion' => 'admin-trust-conversion.php',
    'admin/trust-conversion' => 'admin-trust-conversion.php',
    'admin/conversion-blocks' => 'admin-trust-conversion.php',
    'admin-forms' => 'admin-forms.php',
    'admin/forms' => 'admin-forms.php',
    'admin/custom-forms' => 'admin-forms.php',
    'admin-form-file' => 'admin-form-file.php',
    'admin/form-file' => 'admin-form-file.php',
    'admin-form-checkout' => 'admin-form-checkout.php',
    'admin/form-checkout' => 'admin-form-checkout.php',
    'admin/checkout-form' => 'admin-form-checkout.php',
    'admin/beranda' => 'admin-homepage.php',
    'admin/header-footer' => 'admin-navigation.php',
    'admin-leads' => 'admin-leads.php',
    'admin/leads' => 'admin-leads.php',
    'admin-marketing-analytics' => 'admin-marketing-analytics-center.php',
    'admin/marketing-analytics' => 'admin-marketing-analytics-center.php',
    'admin/marketing-analytics-center' => 'admin-marketing-analytics-center.php',
    'admin/growth-map' => 'admin-marketing-analytics-center.php',
    'admin-analytics' => 'admin-analytics.php',
    'admin/analytics' => 'admin-analytics.php',
    'admin/analytics-settings' => 'admin-analytics.php',
    'admin-profit-action-dashboard' => 'admin-profit-action-dashboard.php',
    'admin/profit-action-dashboard' => 'admin-profit-action-dashboard.php',
    'admin/profit-actions' => 'admin-profit-action-dashboard.php',
    'admin/profit' => 'admin-profit-action-dashboard.php',
    'admin/daily-profit-actions' => 'admin-profit-action-dashboard.php',
    'admin-profit-playbook' => 'admin-profit-playbook.php',
    'admin/profit-playbook' => 'admin-profit-playbook.php',
    'admin/campaign-planner' => 'admin-profit-playbook.php',
    'admin/profit-campaign' => 'admin-profit-playbook.php',
    'admin/campaign-playbook' => 'admin-profit-playbook.php',
    'admin-offer-cta-testing' => 'admin-offer-cta-testing.php',
    'admin/offer-cta-testing' => 'admin-offer-cta-testing.php',
    'admin/offer-cta-lab' => 'admin-offer-cta-testing.php',
    'admin/cta-testing' => 'admin-offer-cta-testing.php',
    'admin/offer-lab' => 'admin-offer-cta-testing.php',
    'admin-cta-placement' => 'admin-cta-placement-assistant.php',
    'admin/cta-placement' => 'admin-cta-placement-assistant.php',
    'admin/cta-deployment' => 'admin-cta-placement-assistant.php',
    'admin/winner-deployment' => 'admin-cta-placement-assistant.php',
    'admin/cta-placement-assistant' => 'admin-cta-placement-assistant.php',
    'admin-cta-result-tracker' => 'admin-cta-result-tracker.php',
    'admin/cta-result-tracker' => 'admin-cta-result-tracker.php',
    'admin/cta-results' => 'admin-cta-result-tracker.php',
    'admin/result-tracker' => 'admin-cta-result-tracker.php',
    'admin/lead-tracking-bridge' => 'admin-cta-result-tracker.php',
    'admin-seo-profit-attribution' => 'admin-seo-profit-attribution.php',
    'admin/seo-profit-attribution' => 'admin-seo-profit-attribution.php',
    'admin/seo-profit' => 'admin-seo-profit-attribution.php',
    'admin/seo-attribution' => 'admin-seo-profit-attribution.php',
    'admin/seo-profit-bridge' => 'admin-seo-profit-attribution.php',
    'admin-seo-assisted-journey' => 'admin-seo-assisted-journey-map.php',
    'admin/seo-assisted-journey' => 'admin-seo-assisted-journey-map.php',
    'admin/seo-journey-map' => 'admin-seo-assisted-journey-map.php',
    'admin/conversion-journey' => 'admin-seo-assisted-journey-map.php',
    'admin/assisted-conversion' => 'admin-seo-assisted-journey-map.php',
    'admin-seo-money-page-optimizer' => 'admin-seo-money-page-optimizer.php',
    'admin/seo-money-page-optimizer' => 'admin-seo-money-page-optimizer.php',
    'admin/seo-money-page' => 'admin-seo-money-page-optimizer.php',
    'admin/money-page-optimizer' => 'admin-seo-money-page-optimizer.php',
    'admin/money-page' => 'admin-seo-money-page-optimizer.php',
    'admin-money-page-deployment-checklist' => 'admin-money-page-deployment-checklist.php',
    'admin/money-page-deployment-checklist' => 'admin-money-page-deployment-checklist.php',
    'admin/deployment-checklist' => 'admin-money-page-deployment-checklist.php',
    'admin/seo-deployment-checklist' => 'admin-money-page-deployment-checklist.php',
    'admin/money-page-checklist' => 'admin-money-page-deployment-checklist.php',
    'admin-internal-link-cta-injection' => 'admin-internal-link-cta-injection-assistant.php',
    'admin/internal-link-cta-injection' => 'admin-internal-link-cta-injection-assistant.php',
    'admin/internal-link-cta-assistant' => 'admin-internal-link-cta-injection-assistant.php',
    'admin/cta-injection' => 'admin-internal-link-cta-injection-assistant.php',
    'admin/link-cta-injection' => 'admin-internal-link-cta-injection-assistant.php',
    'admin-seo-content-refresh-planner' => 'admin-seo-content-refresh-planner.php',
    'admin/seo-content-refresh-planner' => 'admin-seo-content-refresh-planner.php',
    'admin/content-refresh-planner' => 'admin-seo-content-refresh-planner.php',
    'admin/seo-refresh' => 'admin-seo-content-refresh-planner.php',
    'admin/content-refresh' => 'admin-seo-content-refresh-planner.php',
    'admin-lead-priority-scoring' => 'admin-lead-quality-followup-scoring.php',
    'admin/lead-priority-scoring' => 'admin-lead-quality-followup-scoring.php',
    'admin-lead-quality-scoring' => 'admin-lead-quality-followup-scoring.php',
    'admin/lead-quality-scoring' => 'admin-lead-quality-followup-scoring.php',
    'admin/lead-quality' => 'admin-lead-quality-followup-scoring.php',
    'admin/followup-scoring' => 'admin-lead-quality-followup-scoring.php',
    'admin/lead-opportunity-scoring' => 'admin-lead-quality-followup-scoring.php',
    'admin-profit-report-builder' => 'admin-profit-report-builder.php',
    'admin/profit-report-builder' => 'admin-profit-report-builder.php',
    'admin/profit-report' => 'admin-profit-report-builder.php',
    'admin/ceo-report' => 'admin-profit-report-builder.php',
    'admin/executive-report' => 'admin-profit-report-builder.php',
    'admin-seo-campaign-calendar' => 'admin-seo-campaign-calendar-growth-sprint.php',
    'admin/seo-campaign-calendar' => 'admin-seo-campaign-calendar-growth-sprint.php',
    'admin/growth-sprint-planner' => 'admin-seo-campaign-calendar-growth-sprint.php',
    'admin/growth-sprint' => 'admin-seo-campaign-calendar-growth-sprint.php',
    'admin/campaign-calendar' => 'admin-seo-campaign-calendar-growth-sprint.php',
    'admin-u-growth-command-center' => 'admin-u-growth-command-center.php',
    'admin/u-growth-command-center' => 'admin-u-growth-command-center.php',
    'admin/growth-command-center' => 'admin-u-growth-command-center.php',
    'admin/command-center' => 'admin-u-growth-command-center.php',
    'admin/growth-command' => 'admin-u-growth-command-center.php',
    'admin-release-audit' => 'admin-release-audit.php',
    'admin/release-audit' => 'admin-release-audit.php',
    'admin/final-release-audit' => 'admin-release-audit.php',
    'admin/final-hardening' => 'admin-release-audit.php',
    'admin/release-checklist' => 'admin-release-audit.php',
    'admin-growth-insights' => 'admin-growth-insights.php',
    'admin/growth-insights' => 'admin-growth-insights.php',
    'admin/growth' => 'admin-growth-insights.php',
    'admin/business-insights' => 'admin-growth-insights.php',
    'admin/google-ads-tracking-test' => 'google-ads-tracking-test.php',
    'admin/google-ads-test' => 'google-ads-tracking-test.php',
    'admin-marketing-integrations' => 'admin-marketing-integrations.php',
    'admin/marketing-integrations' => 'admin-marketing-integrations.php',
    'admin/wa-email-marketing' => 'admin-marketing-integrations.php',
    'admin/email-marketing' => 'admin-marketing-integrations.php',
    'admin/marketing-fonnte' => 'admin-marketing-integrations.php',
    'admin/mailketing-fonnte' => 'admin-marketing-integrations.php',
    'admin-reports' => 'admin-commerce-insight.php',
    'admin/reports' => 'admin-commerce-insight.php',
    'admin-commerce-insight' => 'admin-commerce-insight.php',
    'admin/commerce-insight' => 'admin-commerce-insight.php',
    'admin/sales-insight' => 'admin-commerce-insight.php',
    'admin/commerce-report' => 'admin-commerce-insight.php',
    'admin/report' => 'admin-commerce-insight.php',
    'admin-seo-growth-planner' => 'admin-seo-growth-planner.php',
    'admin/seo-growth-planner' => 'admin-seo-growth-planner.php',
    'admin/seo-planner' => 'admin-seo-growth-planner.php',
    'admin/internal-link-planner' => 'admin-seo-growth-planner.php',
    'admin-seo-content-planner' => 'admin-seo-content-planner.php',
    'admin/seo-content-planner' => 'admin-seo-content-planner.php',
    'admin/content-planner' => 'admin-seo-content-planner.php',
    'admin/seo-calendar' => 'admin-seo-content-planner.php',
    'admin-seo-execution-board' => 'admin-seo-execution-board.php',
    'admin/seo-execution-board' => 'admin-seo-execution-board.php',
    'admin/seo-task-board' => 'admin-seo-execution-board.php',
    'admin/content-execution' => 'admin-seo-execution-board.php',
    'admin-seo-publish-checklist' => 'admin-seo-publish-checklist.php',
    'admin/seo-publish-checklist' => 'admin-seo-publish-checklist.php',
    'admin/seo-publish-gate' => 'admin-seo-publish-checklist.php',
    'admin/publish-checklist' => 'admin-seo-publish-checklist.php',
    'admin-seo-draft-publisher' => 'admin-seo-draft-publisher.php',
    'admin/seo-draft-publisher' => 'admin-seo-draft-publisher.php',
    'admin/seo-article-drafts' => 'admin-seo-draft-publisher.php',
    'admin/draft-publisher' => 'admin-seo-draft-publisher.php',
    'admin-seo-link-health' => 'admin-seo-link-health.php',
    'admin/seo-link-health' => 'admin-seo-link-health.php',
    'admin/internal-link-manager' => 'admin-seo-link-health.php',
    'admin/link-health' => 'admin-seo-link-health.php',
    'admin-conversion-opportunities' => 'admin-conversion-opportunities.php',
    'admin/conversion-opportunities' => 'admin-conversion-opportunities.php',
    'admin/conversion-opportunity' => 'admin-conversion-opportunities.php',
    'admin/conversion-roi' => 'admin-conversion-opportunities.php',
    'admin-sales-funnel-growth' => 'admin-sales-funnel-growth.php',
    'admin/sales-funnel-growth' => 'admin-sales-funnel-growth.php',
    'admin/sales-funnel' => 'admin-sales-funnel-growth.php',
    'admin/funnel-growth' => 'admin-sales-funnel-growth.php',
    'admin-funnel-action-center' => 'admin-funnel-action-center.php',
    'admin/funnel-action-center' => 'admin-funnel-action-center.php',
    'admin/funnel-action' => 'admin-funnel-action-center.php',
    'admin/sales-action-center' => 'admin-funnel-action-center.php',
    'admin-growth-snapshot' => 'admin-growth-snapshot.php',
    'admin/growth-snapshot' => 'admin-growth-snapshot.php',
    'admin/growth-snapshot-report' => 'admin-growth-snapshot.php',
    'admin/umkm-growth-report' => 'admin-growth-snapshot.php',
    'admin-content-performance' => 'admin-content-performance.php',
    'admin/content-performance' => 'admin-content-performance.php',
    'admin/content-performance-insight' => 'admin-content-performance.php',
    'admin/content-roi' => 'admin-content-performance.php',
    'admin-universal-seo' => 'admin-universal-seo.php',
    'admin/universal-seo' => 'admin-universal-seo.php',
    'admin/seo-engine' => 'admin-universal-seo.php',
    'admin/seo-audit' => 'admin-universal-seo.php',
    'admin-seo-quality' => 'admin-seo-quality.php',
    'admin/seo-quality' => 'admin-seo-quality.php',
    'admin/seo-assistant' => 'admin-seo-quality.php',
    'admin-media-library' => 'admin-media-library.php',
    'admin/media-library' => 'admin-media-library.php',
    'admin/media' => 'admin-media-library.php',
    'admin-jodit-upload' => 'admin-jodit-upload.php',
    'admin/jodit-upload' => 'admin-jodit-upload.php',
    'admin/editor-image-upload' => 'admin-jodit-upload.php',
    'admin-seo-landings' => 'admin-seo-landings.php',
    'admin/seo-landings' => 'admin-seo-landings.php',
    'admin/seo-landing' => 'admin-seo-landings.php',
    'admin-landing-pages' => 'admin-landing-pages.php',
    'admin/landing-pages' => 'admin-landing-pages.php',
    'admin/landing-pages/builder' => 'admin-landing-pages.php',
    'admin/landing-page-analytics' => 'admin-landing-page-analytics.php',
    'admin/lp-analytics' => 'admin-landing-page-analytics.php',
    'admin-landing-page-optimization' => 'admin-landing-page-optimization.php',
    'admin/landing-page-optimization' => 'admin-landing-page-optimization.php',
    'admin/lp-optimization' => 'admin-landing-page-optimization.php',
    'admin/page-builder' => 'admin-landing-pages.php',
    'admin-inquiries' => 'admin-inquiries.php',
    'admin/inquiries' => 'admin-inquiries.php',
    'admin-orders' => 'admin-orders.php',
    'admin/orders' => 'admin-orders.php',
    'admin-shipping' => 'admin-shipping.php',
    'admin/shipping' => 'admin-shipping.php',
    'admin-inventory' => 'admin-inventory.php',
    'admin/inventory' => 'admin-inventory.php',
    'admin/stok' => 'admin-inventory.php',
    'admin/product-availability' => 'admin-inventory.php',
    'admin/ongkir' => 'admin-shipping.php',
    'admin/shipping-rates' => 'admin-shipping.php',
    'admin/order-invoice' => 'admin-order-invoice.php',
    'admin-followups' => 'admin-followups.php',
    'admin/followups' => 'admin-followups.php',
    'admin-checkout-recovery' => 'admin-checkout-recovery.php',
    'admin/checkout-recovery' => 'admin-checkout-recovery.php',
    'admin/abandoned-checkout' => 'admin-checkout-recovery.php',
    'admin/recovery-checkout' => 'admin-checkout-recovery.php',
    'admin-notifications' => 'admin-notifications.php',
    'admin/notifications' => 'admin-notifications.php',
    'admin/email-history' => 'admin-notifications.php',
    'admin/riwayat-email' => 'admin-notifications.php',
    'admin-payment-settings' => 'admin-payment-settings.php',
    'admin/payment-settings' => 'admin-payment-settings.php',
    'admin-payment-gateway' => 'admin-payment-gateway.php',
    'admin/payment-gateway' => 'admin-payment-gateway.php',
    'admin/payment-gateway-settings' => 'admin-payment-gateway.php',
    'admin-digital-delivery' => 'admin-digital-delivery.php',
    'admin/digital-delivery' => 'admin-digital-delivery.php',
    'admin/digital-access' => 'admin-digital-delivery.php',
    'admin-member-area' => 'admin-member-area.php',
    'admin/member-area' => 'admin-member-area.php',
    'admin/course-license' => 'admin-member-area.php',
    'admin/license-access' => 'admin-member-area.php',
    'admin-license-manager' => 'admin-license-manager.php',
    'admin/license-manager' => 'admin-license-manager.php',
    'admin/domain-license' => 'admin-license-manager.php',
    'admin-subscriptions' => 'admin-subscriptions.php',
    'admin/subscriptions' => 'admin-subscriptions.php',
    'admin/membership-subscriptions' => 'admin-subscriptions.php',
    'admin-renewal-clv' => 'admin-renewal-clv.php',
    'admin/renewal-clv' => 'admin-renewal-clv.php',
    'admin/customer-lifetime-value' => 'admin-renewal-clv.php',
    'admin/renewal-upgrade' => 'admin-renewal-clv.php',
    'admin/clv' => 'admin-renewal-clv.php',
    'admin-payment-proofs' => 'admin-payment-proofs.php',
    'admin/payment-proofs' => 'admin-payment-proofs.php',
    'admin/payment-proof-file' => 'admin-payment-proof-file.php',
    'admin-payment-reminders' => 'admin-payment-reminders.php',
    'admin/payment-reminders' => 'admin-payment-reminders.php',
    'admin-transaction-audit' => 'admin-transaction-audit.php',
    'admin/transaction-audit' => 'admin-transaction-audit.php',
    'admin-activity-log' => 'admin-activity-log.php',
    'admin/activity-log' => 'admin-activity-log.php',
    'admin-data-health' => 'admin-data-health.php',
    'admin/data-health' => 'admin-data-health.php',
    'admin-maintenance' => 'admin-maintenance.php',
    'admin/maintenance' => 'admin-maintenance.php',
    'admin-production-readiness' => 'admin-production-readiness.php',
    'admin/production-readiness' => 'admin-production-readiness.php',
    'admin-users' => 'admin-users.php',
    'admin/users' => 'admin-users.php',
    'admin/team' => 'admin-users.php',
    'admin/roles' => 'admin-users.php',
    'admin/user-management' => 'admin-users.php',
    'admin-security' => 'admin-security.php',
    'admin/security' => 'admin-security.php',
    'admin-menu-features' => 'admin-menu-features.php',
    'admin/menu-features' => 'admin-menu-features.php',
    'admin/feature-toggle' => 'admin-menu-features.php',
    'admin/menu-visibility' => 'admin-menu-features.php',
    'admin-smtp' => 'admin-smtp.php',
    'admin/smtp' => 'admin-smtp.php',
    'admin/email-server' => 'admin-smtp.php',
    'admin-storage-database' => 'admin-storage-database.php',
    'admin/storage-database' => 'admin-storage-database.php',
    'admin/storage' => 'admin-storage-database.php',
    'admin/database' => 'admin-storage-database.php',
    'admin/mysql-readiness' => 'admin-storage-database.php',
    'admin-migration-command-center' => 'admin-migration-command-center.php',
    'admin/migration-command-center' => 'admin-migration-command-center.php',
    'admin/migration-center' => 'admin-migration-command-center.php',
    'admin/wp-command-center' => 'admin-migration-command-center.php',
    'admin/migrasi-command-center' => 'admin-migration-command-center.php',
    'admin-wp-migration' => 'admin-wp-migration.php',
    'admin/wp-migration' => 'admin-wp-migration.php',
    'admin/wordpress-migration' => 'admin-wp-migration.php',
    'admin/migrasi-wordpress' => 'admin-wp-migration.php',
    'admin-wp-media-migration' => 'admin-wp-media-migration.php',
    'admin/wp-media-migration' => 'admin-wp-media-migration.php',
    'admin/wordpress-media-migration' => 'admin-wp-media-migration.php',
    'admin/migrasi-media-wordpress' => 'admin-wp-media-migration.php',
    'admin-wp-content-cleaner' => 'admin-wp-content-cleaner.php',
    'admin/wp-content-cleaner' => 'admin-wp-content-cleaner.php',
    'admin/shortcode-cleaner' => 'admin-wp-content-cleaner.php',
    'admin/gutenberg-cleaner' => 'admin-wp-content-cleaner.php',
    'admin/wordpress-content-cleaner' => 'admin-wp-content-cleaner.php',
    'admin-wp-elementor-import' => 'admin-wp-elementor-import.php',
    'admin/wp-elementor-import' => 'admin-wp-elementor-import.php',
    'admin/elementor-import' => 'admin-wp-elementor-import.php',
    'admin/page-builder-import' => 'admin-wp-elementor-import.php',
    'admin/elementor-safe-import' => 'admin-wp-elementor-import.php',
    'admin-seo-preservation' => 'admin-seo-preservation.php',
    'admin/seo-preservation' => 'admin-seo-preservation.php',
    'admin/redirects' => 'admin-seo-preservation.php',
    'admin/seo-redirects' => 'admin-seo-preservation.php',
    'admin/legacy-url' => 'admin-seo-preservation.php',
    'admin-internal-link-migration' => 'admin-internal-link-migration.php',
    'admin/internal-link-migration' => 'admin-internal-link-migration.php',
    'admin/breadcrumb-migration' => 'admin-internal-link-migration.php',
    'admin/internal-links' => 'admin-internal-link-migration.php',
    'admin-dynamic-content-guard' => 'admin-dynamic-content-guard.php',
    'admin/dynamic-content-guard' => 'admin-dynamic-content-guard.php',
    'admin/dynamic-content' => 'admin-dynamic-content-guard.php',
    'admin-data-migration' => 'admin-data-migration.php',
    'admin/data-migration' => 'admin-data-migration.php',
    'admin/mysql-migration' => 'admin-data-migration.php',
    'admin/storage-migration' => 'admin-data-migration.php',
    'admin-cloud-backup-sync' => 'admin-cloud-backup-sync.php',
    'admin/cloud-backup-sync' => 'admin-cloud-backup-sync.php',
    'admin/data-backup-sync' => 'admin-cloud-backup-sync.php',
    'admin/google-sheets-backup' => 'admin-cloud-backup-sync.php',
    'admin/google-drive-backup' => 'admin-cloud-backup-sync.php',
    'admin/looker-studio' => 'admin-cloud-backup-sync.php',
    'admin/looker-studio-setup' => 'admin-cloud-backup-sync.php',
    'admin/looker-setup-wizard' => 'admin-cloud-backup-sync.php',
    'admin/dashboard-visual-setup' => 'admin-cloud-backup-sync.php',
    'admin/dashboard-template-pack' => 'admin-cloud-backup-sync.php',
    'admin/looker-dashboard-template' => 'admin-cloud-backup-sync.php',
    'admin/business-dashboard-template' => 'admin-cloud-backup-sync.php',
    'admin/data-export-center' => 'admin-cloud-backup-sync.php',
    'api/looker-studio-data' => 'looker-studio-data.php',
    'api/looker-studio' => 'looker-studio-data.php',
    'admin/backup-restore' => 'admin-maintenance.php',
    'admin/tools-maintenance' => 'admin-maintenance.php',
    'admin/lead-dashboard' => 'admin-leads.php',
    'admin/lead-tracking' => 'admin-leads.php',
];

if (isset($routes[$uri])) {
    if (function_exists('admin_auth_is_admin_path') && admin_auth_is_admin_path($uri)) {
        $isAdminPublicPath = function_exists('admin_auth_is_public_path') && admin_auth_is_public_path($uri);

        if ($uri === 'admin/logout' || $uri === 'admin-logout' || (($_GET['action'] ?? '') === 'logout' && !$isAdminPublicPath)) {
            if (function_exists('admin_auth_logout')) {
                admin_auth_logout();
            }
            redirect_302('admin/login?message=' . rawurlencode('Anda sudah keluar dari dashboard.'));
        }

        if (!$isAdminPublicPath && function_exists('admin_auth_require')) {
            admin_auth_require(function_exists('admin_auth_current_next') ? admin_auth_current_next($uri) : $uri);
        }
    }

    require PAGES_PATH . '/' . $routes[$uri];
} elseif (preg_match('#^payment-gateway/webhook/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
    $_GET['provider'] = $matches[1];
    require PAGES_PATH . '/payment-gateway-webhook.php';
} elseif (preg_match('#^webhook/payment-gateway/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
    $_GET['provider'] = $matches[1];
    require PAGES_PATH . '/payment-gateway-webhook.php';
} elseif (preg_match('#^form/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require PAGES_PATH . '/form-page.php';
} elseif (preg_match('#^(lp|landing)/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[2];
    require PAGES_PATH . '/landing-page.php';
} elseif (preg_match('#^(tag|keyword|topik|kategori)/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
    $_GET['term_type'] = $matches[1] === 'topik' ? 'keyword' : $matches[1];
    $_GET['term_slug'] = $matches[2];
    require PAGES_PATH . '/dynamic-term.php';
} elseif (preg_match('#^(area|katalog)/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
    $_GET['landing_prefix'] = $matches[1];
    $_GET['landing_slug'] = $matches[2];
    require PAGES_PATH . '/seo-landing.php';
} elseif (preg_match('#^artikel/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require PAGES_PATH . '/artikel-detail.php';
} elseif (preg_match('#^produk/([a-zA-Z0-9\-]+)$#', $uri, $matches)) {
    $_GET['slug'] = $matches[1];
    require PAGES_PATH . '/product-detail.php';
} elseif (in_array($uri, ['produk-fisik', 'paket-layanan', 'katalog-paket', 'paket-produk', 'layanan-paket', 'layanan-layanan'], true)) {
    $dynamicLandingRouteMap = [
        'produk-fisik' => 'produk-fisik',
        'paket-layanan' => 'paket-layanan',
        'katalog-paket' => 'paket-layanan',
        'paket-produk' => 'paket-layanan',
        'layanan-paket' => 'layanan-paket',
        'layanan-layanan' => 'layanan-layanan',
    ];
    $landingProfileKey = $dynamicLandingRouteMap[$uri] ?? 'layanan';
    require ROOT_PATH . '/components/dynamic-landing-page.php';
} elseif ($uri === 'sitemap.xml') {
    require ROOT_PATH . '/sitemap.xml.php';
} elseif ($uri === 'feed') {
    require ROOT_PATH . '/feeds/rss.php';
} elseif (function_exists('seo_preservation_handle_request') && seo_preservation_handle_request($uri)) {
    // Legacy URL was served or redirected by SEO Preservation Layer.
} else {
    http_response_code(404);
    require PAGES_PATH . '/404.php';
}

/*
|--------------------------------------------------------------------------
| PAGE CACHE END
|--------------------------------------------------------------------------
*/

page_cache_end();