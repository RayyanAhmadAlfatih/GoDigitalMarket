<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marketing & Analytics Menu Map
|--------------------------------------------------------------------------
| A lightweight registry to help admin users understand which existing menu
| handles setup, tracking, analytics, optimization, testing, placement, and
| action planning without creating duplicate dashboards.
|--------------------------------------------------------------------------
*/

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

if (!function_exists('marketing_analytics_menu_map')) {
    function marketing_analytics_menu_map(): array
    {
        return [
            [
                'group' => 'Center',
                'title' => 'Marketing & Analytics Center',
                'route' => 'admin/marketing-analytics',
                'badge' => 'Kompas Menu',
                'purpose' => 'Halaman ringkasan untuk memilih menu yang tepat: setup iklan, tracking, analisis LP, CTA, profit, action plan, atau report.',
                'source' => 'core/marketing-analytics-map.php',
                'when' => 'Dipakai pertama kali saat admin bingung harus membuka menu marketing/analytics yang mana.',
                'priority' => 1,
            ],
            [
                'group' => 'Setup',
                'title' => 'Analytics & Iklan',
                'route' => 'admin/analytics',
                'badge' => 'Pixel & Ads',
                'purpose' => 'Tempat setup GTM, GA4, Meta Pixel, TikTok Pixel, Google Ads, GSC, dan pengiriman conversion server.',
                'source' => 'core/analytics.php + core/server-conversion.php',
                'when' => 'Dipakai saat memasang alat ukur, pixel iklan, label conversion, dan halaman tes Google Ads.',
            ],
            [
                'group' => 'Setup',
                'title' => 'WhatsApp & Email Marketing',
                'route' => 'admin/marketing-integrations',
                'badge' => 'Follow-up',
                'purpose' => 'Tempat mengatur provider WhatsApp/email dan template pesan global untuk inquiry atau order.',
                'source' => 'core/marketing-integration.php',
                'when' => 'Dipakai saat admin ingin pesan form/order terkirim otomatis ke customer atau tim.',
            ],
            [
                'group' => 'Tracking',
                'title' => 'Tracking Lead',
                'route' => 'admin/leads',
                'badge' => 'Event Log',
                'purpose' => 'Membaca event dasar seperti klik tombol, WhatsApp, form, checkout, dan conversion channel.',
                'source' => 'pages/lead-event.php + core/conversion.php',
                'when' => 'Dipakai sebagai log utama untuk melihat sinyal mentah dari pengunjung.',
            ],
            [
                'group' => 'LP',
                'title' => 'Analisis Landing Page',
                'route' => 'admin/landing-page-analytics',
                'badge' => 'LP Insight',
                'purpose' => 'Membaca performa per landing page: kunjungan, CTA, form, lead, order, source, campaign, dan CTA signal.',
                'source' => 'core/landing-page-analytics.php',
                'when' => 'Dipakai setelah LP mulai mendapat traffic untuk membaca hasil kampanye.',
            ],
            [
                'group' => 'LP',
                'title' => 'Optimasi Landing Page',
                'route' => 'admin/landing-page-optimization',
                'badge' => 'Action Plan',
                'purpose' => 'Memberikan prioritas perbaikan LP berdasarkan struktur, readiness, traffic, CTA, lead, dan order.',
                'source' => 'core/landing-page-optimization.php',
                'when' => 'Dipakai untuk menentukan LP mana yang perlu diperbaiki dulu.',
            ],
            [
                'group' => 'Testing',
                'title' => 'Offer & CTA Testing Lab',
                'route' => 'admin/offer-cta-testing',
                'badge' => 'Tes A/B',
                'purpose' => 'Lab untuk membandingkan offer, headline, proof, dan CTA sebelum dipasang ke halaman.',
                'source' => 'core/offer-cta-testing.php',
                'when' => 'Dipakai untuk membuat hipotesis dan variasi CTA sebelum dipasang ke aset website.',
            ],
            [
                'group' => 'Deployment',
                'title' => 'CTA Placement Assistant',
                'route' => 'admin/cta-placement',
                'badge' => 'Pasang CTA',
                'purpose' => 'Mengarahkan CTA winner ke homepage, landing page, artikel, form, trust block, atau campaign.',
                'source' => 'core/cta-placement-assistant.php',
                'when' => 'Dipakai setelah ada CTA/offer yang ingin dipasang ke aset website.',
            ],
            [
                'group' => 'Tracking',
                'title' => 'CTA Result Tracker',
                'route' => 'admin/cta-result-tracker',
                'badge' => 'Hasil CTA',
                'purpose' => 'Membaca hasil CTA dari data Tracking Lead agar admin tahu lanjut, revisi, atau scale.',
                'source' => 'core/cta-result-tracker.php',
                'when' => 'Dipakai setelah CTA dipasang dan mulai mendapat klik/lead/order.',
            ],
            [
                'group' => 'Profit',
                'title' => 'SEO Profit Attribution',
                'route' => 'admin/seo-profit-attribution',
                'badge' => 'SEO → Profit',
                'purpose' => 'Menghubungkan konten/SEO dengan sinyal profit, CTA result, lead, dan order.',
                'source' => 'core/seo-profit-attribution.php',
                'when' => 'Dipakai untuk melihat halaman SEO mana yang layak dijadikan money page atau campaign.',
            ],
            [
                'group' => 'Action',
                'title' => 'Profit Action Dashboard',
                'route' => 'admin/profit-action-dashboard',
                'badge' => 'Prioritas',
                'purpose' => 'Menyusun prioritas action bisnis dari order, lead, konten, CTA, dan peluang conversion.',
                'source' => 'core/profit-action-dashboard.php',
                'when' => 'Dipakai saat admin ingin tahu pekerjaan paling penting berikutnya.',
            ],
        ];
    }
}

if (!function_exists('marketing_analytics_menu_audit_summary')) {
    function marketing_analytics_menu_audit_summary(): array
    {
        $items = marketing_analytics_menu_map();
        $groups = [];
        foreach ($items as $item) {
            $group = (string)($item['group'] ?? 'Lainnya');
            $groups[$group] = ($groups[$group] ?? 0) + 1;
        }

        return [
            'total' => count($items),
            'groups' => $groups,
            'guardrails' => [
                'Tracking utama tetap menggunakan sistem Lead Event dan Conversion yang sudah tersedia.',
                'Landing Page Analytics membaca data dari sistem tracking utama.',
                'Offer & CTA Testing Lab tetap menjadi modul Tes A/B utama.',
                'CTA Placement dan CTA Result Tracker dipakai untuk memasang CTA dan membaca hasil tanpa mengganti analytics utama.',
            ],
        ];
    }
}


if (!function_exists('marketing_analytics_group_labels')) {
    function marketing_analytics_group_labels(): array
    {
        return [
            'Center' => 'Kompas utama',
            'Setup' => 'Setup alat',
            'Tracking' => 'Tracking & log',
            'LP' => 'Landing page',
            'Testing' => 'Tes A/B',
            'Deployment' => 'Pemasangan CTA',
            'Profit' => 'Attribution profit',
            'Action' => 'Action plan',
        ];
    }
}

if (!function_exists('marketing_analytics_group_description')) {
    function marketing_analytics_group_description(string $group): string
    {
        $descriptions = [
            'Center' => 'Mulai dari sini untuk memilih menu yang paling sesuai.',
            'Setup' => 'Tempat memasang pixel, analytics, provider WA/email, dan server conversion.',
            'Tracking' => 'Tempat membaca sinyal mentah seperti klik, form, WhatsApp, dan CTA result.',
            'LP' => 'Tempat membaca performa dan action plan khusus landing page.',
            'Testing' => 'Modul utama untuk menguji offer/CTA sebelum dipasang ke halaman.',
            'Deployment' => 'Tempat memasang CTA/offer yang sudah siap ke aset website.',
            'Profit' => 'Tempat menghubungkan SEO, konten, dan halaman penting ke potensi profit.',
            'Action' => 'Tempat membaca prioritas kerja marketing, growth, dan laporan bisnis.',
        ];
        return $descriptions[$group] ?? 'Menu pendukung marketing dan analytics.';
    }
}

if (!function_exists('marketing_analytics_menu_groups')) {
    function marketing_analytics_menu_groups(): array
    {
        $groups = [];
        foreach (marketing_analytics_menu_map() as $item) {
            $group = (string)($item['group'] ?? 'Lainnya');
            $groups[$group][] = $item;
        }
        $order = ['Center', 'Setup', 'Tracking', 'LP', 'Testing', 'Deployment', 'Profit', 'Action'];
        $sorted = [];
        foreach ($order as $group) {
            if (isset($groups[$group])) {
                $sorted[$group] = $groups[$group];
                unset($groups[$group]);
            }
        }
        foreach ($groups as $group => $items) {
            $sorted[$group] = $items;
        }
        return $sorted;
    }
}

if (!function_exists('marketing_analytics_workflow_steps')) {
    function marketing_analytics_workflow_steps(): array
    {
        return [
            [
                'step' => '1',
                'title' => 'Pasang alat ukur',
                'body' => 'Mulai dari Analytics & Iklan untuk GTM, GA4, pixel iklan, GSC, dan server conversion. Ini hanya setup, bukan tempat membaca hasil LP.',
                'route' => 'admin/analytics',
                'cta' => 'Buka setup iklan',
            ],
            [
                'step' => '2',
                'title' => 'Buat aset promosi',
                'body' => 'Gunakan Landing Page Builder, Smart Preset, template, dan Publish Guard untuk membuat halaman campaign yang siap dipakai iklan.',
                'route' => 'admin/landing-pages',
                'cta' => 'Buka LP Builder',
            ],
            [
                'step' => '3',
                'title' => 'Aktifkan follow-up',
                'body' => 'Atur WhatsApp & Email Marketing agar lead dari form/order bisa langsung mendapat respon yang sesuai.',
                'route' => 'admin/marketing-integrations',
                'cta' => 'Buka follow-up',
            ],
            [
                'step' => '4',
                'title' => 'Baca performa',
                'body' => 'Setelah traffic masuk, buka Analisis Landing Page dan Tracking Lead untuk melihat kunjungan, CTA, form, WhatsApp, dan order.',
                'route' => 'admin/landing-page-analytics',
                'cta' => 'Buka analisis LP',
            ],
            [
                'step' => '5',
                'title' => 'Ambil action berikutnya',
                'body' => 'Gunakan Optimasi Landing Page, Profit Action Dashboard, dan Growth Insight untuk menentukan prioritas perbaikan.',
                'route' => 'admin/landing-page-optimization',
                'cta' => 'Buka action plan',
            ],
        ];
    }
}

if (!function_exists('marketing_analytics_quick_decision')) {
    function marketing_analytics_quick_decision(): array
    {
        return [
            ['need' => 'Mau pasang Meta Pixel, TikTok Pixel, GA4, GTM, GSC, atau Google Ads?', 'go' => 'Analytics & Iklan', 'route' => 'admin/analytics'],
            ['need' => 'Mau lihat klik tombol, form, WhatsApp, order, atau sumber lead mentah?', 'go' => 'Tracking Lead', 'route' => 'admin/leads'],
            ['need' => 'Mau lihat performa landing page tertentu?', 'go' => 'Analisis Landing Page', 'route' => 'admin/landing-page-analytics'],
            ['need' => 'Mau tahu landing page mana yang harus diperbaiki dulu?', 'go' => 'Optimasi Landing Page', 'route' => 'admin/landing-page-optimization'],
            ['need' => 'Mau menguji offer/headline/CTA?', 'go' => 'Offer & CTA Testing Lab', 'route' => 'admin/offer-cta-testing'],
            ['need' => 'Mau melihat CTA mana yang berhasil?', 'go' => 'CTA Result Tracker', 'route' => 'admin/cta-result-tracker'],
            ['need' => 'Mau menghubungkan konten SEO ke lead/profit?', 'go' => 'SEO Profit Attribution', 'route' => 'admin/seo-profit-attribution'],
            ['need' => 'Mau prioritas action bisnis harian?', 'go' => 'Profit Action Dashboard', 'route' => 'admin/profit-action-dashboard'],
        ];
    }
}

if (!function_exists('marketing_analytics_render_menu_map')) {
    function marketing_analytics_render_menu_map(string $context = 'analytics'): void
    {
        if (!function_exists('url') || !function_exists('esc')) {
            return;
        }

        $items = marketing_analytics_menu_map();
        $summary = marketing_analytics_menu_audit_summary();
        $title = $context === 'marketing'
            ? 'Peta Menu Marketing & Analytics'
            : 'Peta Tracking, Analytics, dan Iklan';
        $intro = $context === 'marketing'
            ? 'Gunakan peta ini agar WhatsApp/email marketing tidak tertukar dengan tracking, pixel iklan, atau analisa landing page.'
            : 'Peta ini membantu membedakan menu setup iklan, tracking lead, analisa landing page, tes CTA, dan action plan agar alur kerja lebih jelas.';
        ?>
        <section class="admin-card marketing-analytics-map" aria-label="Peta menu marketing dan analytics">
            <style>
                .marketing-analytics-map{margin-bottom:18px;background:linear-gradient(135deg,#ffffff,#f8fafc)}
                .marketing-analytics-map__head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:12px}
                .marketing-analytics-map__head h2{margin:.1rem 0 .35rem;color:#0f172a}
                .marketing-analytics-map__head p{margin:0;color:#64748b;max-width:760px}
                .marketing-analytics-map__badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-weight:900;font-size:.75rem;border:1px solid var(--border);background:color-mix(in srgb,var(--bg) 82%,#ffffff);color:var(--admin-primary)}
                .marketing-analytics-map__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px}
                .marketing-analytics-map__item{border:1px solid #e2e8f0;background:#fff;border-radius:18px;padding:13px;display:grid;gap:8px;box-shadow:0 10px 28px rgba(15,23,42,.04)}
                .marketing-analytics-map__item-top{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
                .marketing-analytics-map__item strong{color:#0f172a;font-size:.98rem}
                .marketing-analytics-map__item p{margin:0;color:#64748b;font-size:.86rem;line-height:1.45}
                .marketing-analytics-map__mini{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
                .marketing-analytics-map__mini span{display:inline-flex;border-radius:999px;background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:3px 8px;font-size:.7rem;font-weight:900}
                .marketing-analytics-map__item a{justify-self:start}
                .marketing-analytics-map__guard{margin-top:12px;border:1px dashed var(--border);background:color-mix(in srgb,var(--bg) 78%,#ffffff);border-radius:16px;padding:12px;color:#475569;font-weight:700}
                .marketing-analytics-map__guard ul{margin:.45rem 0 0;padding-left:1.1rem}
                .marketing-analytics-map__guard li{margin:.25rem 0}
                @media(max-width:980px){.marketing-analytics-map__grid{grid-template-columns:1fr}}
            </style>
            <div class="marketing-analytics-map__head">
                <div>
                    <span class="marketing-analytics-map__badge">Audit menu: <?= (int)($summary['total'] ?? 0); ?> titik fitur</span>
                    <h2><?= esc($title); ?></h2>
                    <p><?= esc($intro); ?></p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/marketing-analytics')); ?>">Buka Center</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-analytics')); ?>">Buka Analisis LP</a>
                </div>
            </div>
            <div class="marketing-analytics-map__grid">
                <?php foreach ($items as $item): ?>
                    <article class="marketing-analytics-map__item">
                        <div class="marketing-analytics-map__item-top">
                            <strong><?= esc((string)($item['title'] ?? '-')); ?></strong>
                            <span class="marketing-analytics-map__badge"><?= esc((string)($item['badge'] ?? 'Menu')); ?></span>
                        </div>
                        <p><?= esc((string)($item['purpose'] ?? '')); ?></p>
                        <div class="marketing-analytics-map__mini">
                            <span><?= esc((string)($item['group'] ?? '')); ?></span>
                        </div>
                        <p><strong>Kapan dipakai:</strong> <?= esc((string)($item['when'] ?? '')); ?></p>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(url((string)($item['route'] ?? 'admin'))); ?>">Buka menu</a>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="marketing-analytics-map__guard">
                <strong>Guardrail alur kerja</strong>
                <ul>
                    <?php foreach ((array)($summary['guardrails'] ?? []) as $guardrail): ?>
                        <li><?= esc((string)$guardrail); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php
    }
}
