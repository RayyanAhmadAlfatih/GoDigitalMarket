<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ADMIN MENU REGISTRY & FEATURE VISIBILITY
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('admin_menu_groups')) {
    function admin_menu_groups(): array
    {
        return [
        ['label' => 'Pengaturan Web', 'items' => [
            ['label' => 'Brand & Warna', 'icon' => '◐', 'href' => url('admin/brand'), 'match' => ['admin', 'admin-brand', 'admin/brand', 'admin/theme']],
            ['label' => 'Mode & Kategori Bisnis', 'icon' => '▦', 'href' => url('admin/business'), 'match' => ['admin-business', 'admin/business', 'admin/business-mode', 'admin/categories']],
            ['label' => 'Website Starter Wizard', 'icon' => '✦', 'href' => url('admin/starter-wizard'), 'match' => ['admin-starter-wizard', 'admin/starter-wizard', 'admin/website-starter', 'admin/onboarding-wizard']],
            ['label' => 'Launch Readiness', 'icon' => '⬢', 'href' => url('admin/launch-readiness'), 'match' => ['admin-launch-readiness', 'admin/launch-readiness', 'admin/guided-setup']],
            ['label' => 'Onboarding Setup Assistant', 'icon' => '◌', 'href' => url('admin/onboarding-assistant'), 'match' => ['admin-onboarding-assistant', 'admin/onboarding-assistant', 'admin/panduan-harian']],
            ['label' => 'Pusat Bantuan Dashboard', 'icon' => '?', 'href' => url('admin/help-center'), 'match' => ['admin-help-center', 'admin/help-center', 'admin/bantuan-dashboard', 'admin/panduan-dashboard']],
            ['label' => 'Konten Template', 'icon' => '✎', 'href' => url('admin/template-content'), 'match' => ['admin-template-content', 'admin/template-content', 'admin/editable-template']],
            ['label' => 'Menu & Footer', 'icon' => '☰', 'href' => url('admin/navigation'), 'match' => ['admin-navigation', 'admin/navigation', 'admin/menu', 'admin/header-footer']],
            ['label' => 'Atur Beranda', 'icon' => '⌂', 'href' => url('admin/homepage'), 'match' => ['admin-homepage', 'admin/homepage', 'admin/beranda']],
            ['label' => 'Trust & Conversion Block', 'icon' => '◆', 'href' => url('admin/trust-conversion'), 'match' => ['admin-trust-conversion', 'admin/trust-conversion', 'admin/conversion-blocks']],
        ]],
        ['label' => 'Konten & SEO', 'items' => [
            ['label' => 'Katalog Produk/Jasa', 'icon' => '▣', 'href' => url('admin/produk'), 'match' => ['admin-produk', 'admin/produk']],
            ['label' => 'Artikel', 'icon' => '✎', 'href' => url('admin/artikel'), 'match' => ['admin-artikel', 'admin/artikel']],
            ['label' => 'Migration Command Center', 'icon' => 'CMD', 'href' => url('admin/migration-command-center'), 'match' => ['admin-migration-command-center', 'admin/migration-command-center', 'admin/migration-center', 'admin/wp-command-center', 'admin/migrasi-command-center']],
            ['label' => 'Migrasi WordPress', 'icon' => 'WP', 'href' => url('admin/wp-migration'), 'match' => ['admin-wp-migration', 'admin/wp-migration', 'admin/wordpress-migration', 'admin/migrasi-wordpress']],
            ['label' => 'WordPress Media Migration', 'icon' => 'IMG', 'href' => url('admin/wp-media-migration'), 'match' => ['admin-wp-media-migration', 'admin/wp-media-migration', 'admin/wordpress-media-migration', 'admin/migrasi-media-wordpress']],
            ['label' => 'Shortcode & Gutenberg Cleaner', 'icon' => 'SC', 'href' => url('admin/wp-content-cleaner'), 'match' => ['admin-wp-content-cleaner', 'admin/wp-content-cleaner', 'admin/shortcode-cleaner', 'admin/gutenberg-cleaner', 'admin/wordpress-content-cleaner']],
            ['label' => 'Elementor Safe Import', 'icon' => 'EL', 'href' => url('admin/wp-elementor-import'), 'match' => ['admin-wp-elementor-import', 'admin/wp-elementor-import', 'admin/elementor-import', 'admin/page-builder-import', 'admin/elementor-safe-import']],
            ['label' => 'SEO Preservation & Redirect', 'icon' => '301', 'href' => url('admin/seo-preservation'), 'match' => ['admin-seo-preservation', 'admin/seo-preservation', 'admin/redirects', 'admin/seo-redirects', 'admin/legacy-url']],
            ['label' => 'Breadcrumb & Internal Link', 'icon' => '↔', 'href' => url('admin/internal-link-migration'), 'match' => ['admin-internal-link-migration', 'admin/internal-link-migration', 'admin/breadcrumb-migration', 'admin/internal-links']],
            ['label' => 'Universal SEO Engine', 'icon' => '✧', 'href' => url('admin/universal-seo'), 'match' => ['admin-universal-seo', 'admin/universal-seo', 'admin/seo-engine', 'admin/seo-audit']],
            ['label' => 'SEO Growth Planner', 'icon' => '↗', 'href' => url('admin/seo-growth-planner'), 'match' => ['admin-seo-growth-planner', 'admin/seo-growth-planner', 'admin/seo-planner', 'admin/internal-link-planner']],
            ['label' => 'SEO Content Planner', 'icon' => '☷', 'href' => url('admin/seo-content-planner'), 'match' => ['admin-seo-content-planner', 'admin/seo-content-planner', 'admin/content-planner', 'admin/seo-calendar']],
            ['label' => 'SEO Execution Board', 'icon' => '▤', 'href' => url('admin/seo-execution-board'), 'match' => ['admin-seo-execution-board', 'admin/seo-execution-board', 'admin/seo-task-board', 'admin/content-execution']],
            ['label' => 'SEO Publish Checklist', 'icon' => '✓', 'href' => url('admin/seo-publish-checklist'), 'match' => ['admin-seo-publish-checklist', 'admin/seo-publish-checklist', 'admin/seo-publish-gate', 'admin/publish-checklist']],
            ['label' => 'SEO Draft Publisher', 'icon' => '✎', 'href' => url('admin/seo-draft-publisher'), 'match' => ['admin-seo-draft-publisher', 'admin/seo-draft-publisher', 'admin/seo-article-drafts', 'admin/draft-publisher']],
            ['label' => 'Internal Link Manager', 'icon' => '↔', 'href' => url('admin/seo-link-health'), 'match' => ['admin-seo-link-health', 'admin/seo-link-health', 'admin/internal-link-manager', 'admin/link-health']],
            ['label' => 'Content Performance', 'icon' => '◉', 'href' => url('admin/content-performance'), 'match' => ['admin-content-performance', 'admin/content-performance', 'admin/content-performance-insight', 'admin/content-roi']],
            ['label' => 'Dynamic Content Guard', 'icon' => '◎', 'href' => url('admin/dynamic-content-guard'), 'match' => ['admin-dynamic-content-guard', 'admin/dynamic-content-guard', 'admin/dynamic-content']],
            ['label' => 'Cek SEO', 'icon' => '✓', 'href' => url('admin/seo-quality'), 'match' => ['admin-seo-quality', 'admin/seo-quality', 'admin/seo-assistant']],
            ['label' => 'SEO Landing Pages', 'icon' => '⌁', 'href' => url('admin/seo-landings'), 'match' => ['admin-seo-landings', 'admin/seo-landings', 'admin/seo-landing']],
            ['label' => 'Media & Asset SEO', 'icon' => '▧', 'href' => url('admin/media-library'), 'match' => ['admin-media-library', 'admin/media-library', 'admin/media']],
        ]],
        ['label' => 'Form Builder', 'items' => [
            ['label' => 'Form Custom', 'icon' => '▣', 'href' => url('admin/forms'), 'match' => ['admin-forms', 'admin/forms', 'admin/custom-forms']],
            ['label' => 'Form Checkout', 'icon' => '◫', 'href' => url('admin/form-checkout'), 'match' => ['admin-form-checkout', 'admin/form-checkout', 'admin/checkout-form']],
        ]],
        ['label' => 'Order & Penjualan', 'items' => [
            ['label' => 'Order', 'icon' => '▤', 'href' => url('admin/orders'), 'match' => ['admin-orders', 'admin/orders']],
            ['label' => 'Stock & Inventory', 'icon' => 'STK', 'href' => url('admin/inventory'), 'match' => ['admin-inventory', 'admin/inventory', 'admin/stok', 'admin/product-availability']],
            ['label' => 'Shipping & Ongkir', 'icon' => '⇄', 'href' => url('admin/shipping'), 'match' => ['admin-shipping', 'admin/shipping', 'admin/ongkir', 'admin/shipping-rates']],
            ['label' => 'Digital Delivery', 'icon' => '⬇', 'href' => url('admin/digital-delivery'), 'match' => ['admin-digital-delivery', 'admin/digital-delivery', 'admin/digital-access']],
            ['label' => 'Member Area', 'icon' => 'MEM', 'href' => url('admin/member-area'), 'match' => ['admin-member-area', 'admin/member-area', 'admin/course-license', 'admin/license-access']],
            ['label' => 'License Manager', 'icon' => 'KEY', 'href' => url('admin/license-manager'), 'match' => ['admin-license-manager', 'admin/license-manager', 'admin/domain-license']],
            ['label' => 'Subscription', 'icon' => 'SUB', 'href' => url('admin/subscriptions'), 'match' => ['admin-subscriptions', 'admin/subscriptions', 'admin/membership-subscriptions']],
            ['label' => 'Renewal & CLV', 'icon' => 'CLV', 'href' => url('admin/renewal-clv'), 'match' => ['admin-renewal-clv', 'admin/renewal-clv', 'admin/customer-lifetime-value', 'admin/renewal-upgrade', 'admin/clv']],
            ['label' => 'Checkout Recovery', 'icon' => 'HOT', 'href' => url('admin/checkout-recovery'), 'match' => ['admin-checkout-recovery', 'admin/checkout-recovery', 'admin/abandoned-checkout', 'admin/recovery-checkout']],
            ['label' => 'Laporan & Insight Penjualan', 'icon' => 'Rp', 'href' => url('admin/commerce-insight'), 'match' => ['admin-reports', 'admin/reports', 'admin/report', 'admin-commerce-insight', 'admin/commerce-insight', 'admin/sales-insight', 'admin/commerce-report']],
        ]],
        ['label' => 'Pembayaran', 'items' => [
            ['label' => 'Pembayaran Manual', 'icon' => '⚙', 'href' => url('admin/payment-settings'), 'match' => ['admin-payment-settings', 'admin/payment-settings']],
            ['label' => 'Payment Gateway', 'icon' => '◆', 'href' => url('admin/payment-gateway'), 'match' => ['admin-payment-gateway', 'admin/payment-gateway', 'admin/payment-gateway-settings']],
            ['label' => 'Bukti Pembayaran', 'icon' => '▥', 'href' => url('admin/payment-proofs'), 'match' => ['admin-payment-proofs', 'admin/payment-proofs']],
            ['label' => 'Reminder Pembayaran', 'icon' => '⏱', 'href' => url('admin/payment-reminders'), 'match' => ['admin-payment-reminders', 'admin/payment-reminders']],
            ['label' => 'Audit Transaksi', 'icon' => '≡', 'href' => url('admin/transaction-audit'), 'match' => ['admin-transaction-audit', 'admin/transaction-audit']],
        ]],
        ['label' => 'Lead & Customer', 'items' => [
            ['label' => 'Tracking Lead', 'icon' => '◎', 'href' => url('admin/leads'), 'match' => ['admin-leads', 'admin/leads', 'admin/lead-dashboard', 'admin/lead-tracking']],
            ['label' => 'Inbox Lead / Form', 'icon' => '☏', 'href' => url('admin/inquiries'), 'match' => ['admin-inquiries', 'admin/inquiries']],
            ['label' => 'Follow-up & CRM', 'icon' => '↻', 'href' => url('admin/followups'), 'match' => ['admin-followups', 'admin/followups']],
        ]],
        ['label' => 'Landing Page Builder', 'items' => [
            ['label' => 'Landing Pages', 'icon' => '▦', 'href' => url('admin/landing-pages'), 'match' => ['admin-landing-pages', 'admin/landing-pages', 'admin/landing-pages/builder', 'admin/page-builder']],
            ['label' => 'Analisis Landing Page', 'icon' => '▥', 'href' => url('admin/landing-page-analytics'), 'match' => ['admin/landing-page-analytics', 'admin/lp-analytics']],
            ['label' => 'Optimasi Landing Page', 'icon' => '◬', 'href' => url('admin/landing-page-optimization'), 'match' => ['admin-landing-page-optimization', 'admin/landing-page-optimization', 'admin/lp-optimization']],
        ]],
        ['label' => 'Marketing & Analytics', 'items' => [
            ['label' => 'Marketing & Analytics Center', 'icon' => 'MAP', 'href' => url('admin/marketing-analytics'), 'match' => ['admin-marketing-analytics', 'admin/marketing-analytics', 'admin/marketing-analytics-center', 'admin/growth-map']],
            ['label' => 'Analytics & Iklan', 'icon' => '◈', 'href' => url('admin/analytics'), 'match' => ['admin-analytics', 'admin/analytics', 'admin/analytics-settings']],
            ['label' => 'WhatsApp & Email Marketing', 'icon' => '✦', 'href' => url('admin/marketing-integrations'), 'match' => ['admin-marketing-integrations', 'admin/marketing-integrations', 'admin/wa-email-marketing', 'admin/email-marketing', 'admin/marketing-fonnte', 'admin/mailketing-fonnte']],
            ['label' => 'Offer & CTA Testing Lab', 'icon' => 'A/B', 'href' => url('admin/offer-cta-testing'), 'match' => ['admin-offer-cta-testing', 'admin/offer-cta-testing', 'admin/offer-cta-lab', 'admin/cta-testing', 'admin/offer-lab']],
            ['label' => 'CTA Placement Assistant', 'icon' => 'CTA', 'href' => url('admin/cta-placement'), 'match' => ['admin-cta-placement', 'admin/cta-placement', 'admin/cta-deployment', 'admin/winner-deployment', 'admin/cta-placement-assistant']],
            ['label' => 'CTA Result Tracker', 'icon' => '↗', 'href' => url('admin/cta-result-tracker'), 'match' => ['admin-cta-result-tracker', 'admin/cta-result-tracker', 'admin/cta-results', 'admin/result-tracker', 'admin/lead-tracking-bridge']],
            ['label' => 'SEO Profit Attribution', 'icon' => 'SEO', 'href' => url('admin/seo-profit-attribution'), 'match' => ['admin-seo-profit-attribution', 'admin/seo-profit-attribution', 'admin/seo-profit', 'admin/seo-attribution', 'admin/seo-profit-bridge']],
            ['label' => 'Profit Action Dashboard', 'icon' => 'Rp', 'href' => url('admin/profit-action-dashboard'), 'match' => ['admin-profit-action-dashboard', 'admin/profit-action-dashboard', 'admin/profit-actions', 'admin/profit', 'admin/daily-profit-actions']],
            ['label' => 'Profit Playbook', 'icon' => '▣', 'href' => url('admin/profit-playbook'), 'match' => ['admin-profit-playbook', 'admin/profit-playbook', 'admin/campaign-planner', 'admin/profit-campaign', 'admin/campaign-playbook']],
            ['label' => 'U-Growth Command Center', 'icon' => 'CMD', 'href' => url('admin/u-growth-command-center'), 'match' => ['admin-u-growth-command-center', 'admin/u-growth-command-center', 'admin/growth-command-center', 'admin/command-center', 'admin/growth-command']],
            ['label' => 'Growth Insight', 'icon' => '✦', 'href' => url('admin/growth-insights'), 'match' => ['admin-growth-insights', 'admin/growth-insights', 'admin/growth', 'admin/business-insights']],
            ['label' => 'Conversion Opportunities', 'icon' => '◎', 'href' => url('admin/conversion-opportunities'), 'match' => ['admin-conversion-opportunities', 'admin/conversion-opportunities', 'admin/conversion-opportunity', 'admin/conversion-roi']],
            ['label' => 'Sales Funnel Growth', 'icon' => '⟲', 'href' => url('admin/sales-funnel-growth'), 'match' => ['admin-sales-funnel-growth', 'admin/sales-funnel-growth', 'admin/sales-funnel', 'admin/funnel-growth']],
            ['label' => 'Funnel Action Center', 'icon' => '➤', 'href' => url('admin/funnel-action-center'), 'match' => ['admin-funnel-action-center', 'admin/funnel-action-center', 'admin/funnel-action', 'admin/sales-action-center']],
            ['label' => 'Growth Snapshot', 'icon' => '▰', 'href' => url('admin/growth-snapshot'), 'match' => ['admin-growth-snapshot', 'admin/growth-snapshot', 'admin/growth-snapshot-report', 'admin/umkm-growth-report']],
            ['label' => 'Profit Report Builder', 'icon' => 'CEO', 'href' => url('admin/profit-report-builder'), 'match' => ['admin-profit-report-builder', 'admin/profit-report-builder', 'admin/profit-report', 'admin/ceo-report', 'admin/executive-report']],
            ['label' => 'SEO Journey Map', 'icon' => 'MAP', 'href' => url('admin/seo-assisted-journey'), 'match' => ['admin-seo-assisted-journey', 'admin/seo-assisted-journey', 'admin/seo-journey-map', 'admin/conversion-journey', 'admin/assisted-conversion']],
            ['label' => 'SEO Money Page Optimizer', 'icon' => 'Rp', 'href' => url('admin/seo-money-page-optimizer'), 'match' => ['admin-seo-money-page-optimizer', 'admin/seo-money-page-optimizer', 'admin/seo-money-page', 'admin/money-page-optimizer', 'admin/money-page']],
            ['label' => 'Money Page Deployment Checklist', 'icon' => '✓', 'href' => url('admin/money-page-deployment-checklist'), 'match' => ['admin-money-page-deployment-checklist', 'admin/money-page-deployment-checklist', 'admin/deployment-checklist', 'admin/seo-deployment-checklist', 'admin/money-page-checklist']],
            ['label' => 'Internal Link & CTA Injection', 'icon' => '↔', 'href' => url('admin/internal-link-cta-injection'), 'match' => ['admin-internal-link-cta-injection', 'admin/internal-link-cta-injection', 'admin/internal-link-cta-assistant', 'admin/cta-injection', 'admin/link-cta-injection']],
            ['label' => 'SEO Content Refresh Planner', 'icon' => '↻', 'href' => url('admin/seo-content-refresh-planner'), 'match' => ['admin-seo-content-refresh-planner', 'admin/seo-content-refresh-planner', 'admin/content-refresh-planner', 'admin/seo-refresh', 'admin/content-refresh']],
            ['label' => 'SEO Campaign Calendar', 'icon' => 'CAL', 'href' => url('admin/seo-campaign-calendar'), 'match' => ['admin-seo-campaign-calendar', 'admin/seo-campaign-calendar', 'admin/growth-sprint-planner', 'admin/growth-sprint', 'admin/campaign-calendar']],
            ['label' => 'Lead Priority Scoring', 'icon' => 'HOT', 'href' => url('admin/lead-priority-scoring'), 'match' => ['admin-lead-priority-scoring', 'admin/lead-priority-scoring', 'admin-lead-quality-scoring', 'admin/lead-quality-scoring', 'admin/lead-quality', 'admin/followup-scoring', 'admin/lead-opportunity-scoring']],
        ]],
        ['label' => 'Sistem', 'items' => [
            ['label' => 'Setup Awal / Installer', 'icon' => 'SET', 'href' => url('install'), 'match' => ['install', 'setup', 'first-run']],
            ['label' => 'Manajemen User & Role', 'icon' => 'USR', 'href' => url('admin/users'), 'match' => ['admin-users', 'admin/users', 'admin/team', 'admin/roles', 'admin/user-management']],
            ['label' => 'Keamanan', 'icon' => '▣', 'href' => url('admin/security'), 'match' => ['admin-security', 'admin/security']],
            ['label' => 'Menu & Fitur Admin', 'icon' => 'TOG', 'href' => url('admin/menu-features'), 'match' => ['admin-menu-features', 'admin/menu-features', 'admin/feature-toggle', 'admin/menu-visibility']],
            ['label' => 'SMTP / Email Server', 'icon' => '✉', 'href' => url('admin/smtp'), 'match' => ['admin-smtp', 'admin/smtp', 'admin/email-server']],
            ['label' => 'Riwayat Email', 'icon' => 'MAIL', 'href' => url('admin/notifications'), 'match' => ['admin-notifications', 'admin/notifications', 'admin/email-history', 'admin/riwayat-email']],
            ['label' => 'Storage & Database', 'icon' => 'DB', 'href' => url('admin/storage-database'), 'match' => ['admin-storage-database', 'admin/storage-database', 'admin/storage', 'admin/database', 'admin/mysql-readiness']],
            ['label' => 'Migrasi Data MySQL', 'icon' => 'SQL', 'href' => url('admin/data-migration'), 'match' => ['admin-data-migration', 'admin/data-migration', 'admin/mysql-migration', 'admin/storage-migration']],
            ['label' => 'Backup & Sync Data', 'icon' => 'SYNC', 'href' => url('admin/cloud-backup-sync'), 'match' => ['admin-cloud-backup-sync', 'admin/cloud-backup-sync', 'admin/data-backup-sync', 'admin/google-sheets-backup', 'admin/google-drive-backup', 'admin/looker-studio', 'admin/looker-studio-setup', 'admin/looker-setup-wizard', 'admin/dashboard-visual-setup', 'admin/dashboard-template-pack', 'admin/looker-dashboard-template', 'admin/business-dashboard-template', 'admin/data-export-center']],
            ['label' => 'Backup & Restore', 'icon' => '◫', 'href' => url('admin/maintenance'), 'match' => ['admin-maintenance', 'admin/maintenance', 'admin/backup-restore']],
            ['label' => 'Log Sistem', 'icon' => '◷', 'href' => url('admin/activity-log'), 'match' => ['admin-activity-log', 'admin/activity-log']],
            ['label' => 'Cek Sistem', 'icon' => '♡', 'href' => url('admin/data-health'), 'match' => ['admin-data-health', 'admin/data-health']],
            ['label' => 'Kesiapan Website', 'icon' => '⬢', 'href' => url('admin/production-readiness'), 'match' => ['admin-production-readiness', 'admin/production-readiness']],
            ['label' => 'Audit Kesiapan Website', 'icon' => '✓', 'href' => url('admin/release-audit'), 'match' => ['admin-release-audit', 'admin/release-audit', 'admin/final-release-audit', 'admin/final-hardening', 'admin/release-checklist']],
        ]],
    ];
    }
}

if (!function_exists('admin_menu_settings_file')) {
    function admin_menu_settings_file(): string
    {
        return STORAGE_PATH . '/admin-menu-settings.json';
    }
}

if (!function_exists('admin_menu_settings_defaults')) {
    function admin_menu_settings_defaults(): array
    {
        return [
            'disabled_items' => [],
            'updated_at' => '',
        ];
    }
}

if (!function_exists('admin_menu_settings_read')) {
    function admin_menu_settings_read(): array
    {
        $file = admin_menu_settings_file();
        if (!is_file($file)) {
            return admin_menu_settings_defaults();
        }

        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) {
            return admin_menu_settings_defaults();
        }

        $settings = array_merge(admin_menu_settings_defaults(), $data);
        $settings['disabled_items'] = array_values(array_unique(array_filter(array_map('admin_menu_key_normalize', (array)($settings['disabled_items'] ?? [])))));
        return $settings;
    }
}

if (!function_exists('admin_menu_settings_write')) {
    function admin_menu_settings_write(array $settings): bool
    {
        if (!is_dir(STORAGE_PATH)) {
            @mkdir(STORAGE_PATH, 0775, true);
        }
        $payload = array_merge(admin_menu_settings_defaults(), $settings);
        $payload['disabled_items'] = array_values(array_unique(array_filter(array_map('admin_menu_key_normalize', (array)($payload['disabled_items'] ?? [])))));
        $payload['updated_at'] = date('c');
        return @file_put_contents(admin_menu_settings_file(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}

if (!function_exists('admin_menu_key_normalize')) {
    function admin_menu_key_normalize(string $key): string
    {
        $key = trim(str_replace('\\', '/', $key));
        $key = preg_replace('#^https?://[^/]+#i', '', $key) ?? '';
        $key = trim((string)strtok($key, '?'), '/');
        $basePath = trim((string)(parse_url((string)BASE_URL, PHP_URL_PATH) ?? ''), '/');
        if ($basePath !== '' && str_starts_with($key, $basePath . '/')) {
            $key = substr($key, strlen($basePath) + 1);
        }
        return trim($key, '/');
    }
}

if (!function_exists('admin_menu_item_key')) {
    function admin_menu_item_key(array $item): string
    {
        if (!empty($item['feature_key'])) {
            return admin_menu_key_normalize((string)$item['feature_key']);
        }

        $href = (string)($item['href'] ?? '');
        $path = trim((string)(parse_url($href, PHP_URL_PATH) ?? ''), '/');
        $key = admin_menu_key_normalize($path);
        if ($key !== '') {
            return $key;
        }

        $matches = (array)($item['match'] ?? []);
        foreach ($matches as $match) {
            $matchKey = admin_menu_key_normalize((string)$match);
            if ($matchKey !== '') {
                return $matchKey;
            }
        }
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)($item['label'] ?? 'menu')), '-'));
    }
}

if (!function_exists('admin_menu_locked_keys')) {
    function admin_menu_locked_keys(): array
    {
        return [
            'admin/brand',
            'admin/menu-features',
            'admin/users',
            'admin/security',
        ];
    }
}

if (!function_exists('admin_menu_is_locked')) {
    function admin_menu_is_locked(string $key): bool
    {
        $key = admin_menu_key_normalize($key);
        return in_array($key, admin_menu_locked_keys(), true);
    }
}

if (!function_exists('admin_menu_flatten_groups')) {
    function admin_menu_flatten_groups(array $groups): array
    {
        $rows = [];
        foreach ($groups as $group) {
            $groupLabel = (string)($group['label'] ?? 'Menu');
            foreach ((array)($group['items'] ?? []) as $item) {
                $key = admin_menu_item_key((array)$item);
                if ($key === '') {
                    continue;
                }
                $rows[$key] = [
                    'key' => $key,
                    'group' => $groupLabel,
                    'label' => (string)($item['label'] ?? $key),
                    'icon' => (string)($item['icon'] ?? ''),
                    'href' => (string)($item['href'] ?? ''),
                    'match' => array_values(array_map('strval', (array)($item['match'] ?? []))),
                    'locked' => admin_menu_is_locked($key),
                ];
            }
        }
        return array_values($rows);
    }
}

if (!function_exists('admin_menu_filter_visibility')) {
    function admin_menu_filter_visibility(array $groups): array
    {
        $settings = admin_menu_settings_read();
        $disabled = array_flip((array)($settings['disabled_items'] ?? []));
        if (!$disabled) {
            return $groups;
        }

        $filtered = [];
        foreach ($groups as $group) {
            $items = [];
            foreach ((array)($group['items'] ?? []) as $item) {
                $key = admin_menu_item_key((array)$item);
                if ($key !== '' && isset($disabled[$key]) && !admin_menu_is_locked($key)) {
                    continue;
                }
                $items[] = $item;
            }
            if ($items) {
                $group['items'] = $items;
                $filtered[] = $group;
            }
        }
        return $filtered;
    }
}

if (!function_exists('admin_menu_settings_save_from_post')) {
    function admin_menu_settings_save_from_post(array $post): bool
    {
        $known = [];
        foreach (admin_menu_flatten_groups(admin_menu_groups()) as $row) {
            $known[(string)$row['key']] = !empty($row['locked']);
        }

        $disabled = [];
        foreach ((array)($post['disabled_items'] ?? []) as $key) {
            $key = admin_menu_key_normalize((string)$key);
            if ($key === '' || !array_key_exists($key, $known) || !empty($known[$key])) {
                continue;
            }
            $disabled[] = $key;
        }

        return admin_menu_settings_write(['disabled_items' => $disabled]);
    }
}
