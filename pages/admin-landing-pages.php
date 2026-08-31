<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? '';
$error = '';
$message = trim((string)($_GET['message'] ?? ''));

function admin_landing_pages_logged_in(): bool
{
    return (bool)($_SESSION['admin_articles_logged_in'] ?? false);
}


function admin_landing_page_input_from_post(array $blocks): array
{
    return [
        'id' => trim((string)($_POST['id'] ?? '')),
        'title' => trim((string)($_POST['title'] ?? '')),
        'slug' => trim((string)($_POST['slug'] ?? '')),
        'status' => trim((string)($_POST['status'] ?? 'draft')),
        'layout_mode' => trim((string)($_POST['layout_mode'] ?? 'focus')),
        'hide_header' => !empty($_POST['hide_header']),
        'hide_footer' => !empty($_POST['hide_footer']),
        'hide_floating_wa' => !empty($_POST['hide_floating_wa']),
        'show_nav_only' => !empty($_POST['show_nav_only']),
        'mini_footer_brand' => trim((string)($_POST['mini_footer_brand'] ?? '')),
        'mini_footer_text' => trim((string)($_POST['mini_footer_text'] ?? '')),
        'mini_footer_bg' => trim((string)($_POST['mini_footer_bg'] ?? '')),
        'mini_footer_text_color' => trim((string)($_POST['mini_footer_text_color'] ?? '')),
        'mini_footer_brand_color' => trim((string)($_POST['mini_footer_brand_color'] ?? '')),
        'mini_footer_text_size' => trim((string)($_POST['mini_footer_text_size'] ?? '')),
        'mini_footer_align' => trim((string)($_POST['mini_footer_align'] ?? '')),
        'motion_enabled' => !empty($_POST['motion_enabled']),
        'motion_style' => trim((string)($_POST['motion_style'] ?? 'fade-up')),
        'indexable' => !empty($_POST['indexable']),
        'meta_title' => trim((string)($_POST['meta_title'] ?? '')),
        'meta_description' => trim((string)($_POST['meta_description'] ?? '')),
        'meta_keywords' => trim((string)($_POST['meta_keywords'] ?? '')),
        'og_image' => trim((string)($_POST['og_image'] ?? '')),
        'tracking_label' => trim((string)($_POST['tracking_label'] ?? '')),
        'ab_tests' => function_exists('landing_page_ab_config_from_post') ? landing_page_ab_config_from_post($_POST) : [],
        'full_html_mode' => !empty($_POST['full_html_mode']),
        'raw_html_document' => (string)($_POST['raw_html_document'] ?? ''),
        'blocks' => $blocks,
    ];
}

if ((string)($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_articles_logged_in']);
    redirect_302('admin/landing-pages');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();

    if (!admin_landing_pages_logged_in()) {
        if (hash_equals($adminPassword, (string)($_POST['password'] ?? ''))) {
            $_SESSION['admin_articles_logged_in'] = true;
            if (function_exists('activity_log_record')) {
                activity_log_record('login', 'admin', null, 'Admin login.', ['area' => 'landing-pages-workspace']);
            }
            redirect_302('admin/landing-pages');
        }
        $error = 'Password admin salah.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'delete') {
            $id = (string)($_POST['id'] ?? '');
            if ($id !== '' && landing_page_delete($id)) {
                if (function_exists('activity_log_record')) {
                    activity_log_record('delete', 'landing_page', $id, 'Landing page dihapus.', []);
                }
                redirect_302('admin/landing-pages?message=' . rawurlencode('Landing page berhasil dihapus.'));
            }
            $error = 'Gagal menghapus landing page.';
        }

        if ($action === 'delete_template') {
            $id = (string)($_POST['template_id'] ?? '');
            if ($id !== '' && landing_page_template_delete($id)) {
                if (function_exists('activity_log_record')) {
                    activity_log_record('delete', 'landing_page_template', $id, 'Template landing page dihapus.', []);
                }
                redirect_302('admin/landing-pages?message=' . rawurlencode('Template berhasil dihapus.'));
            }
            $error = 'Gagal menghapus template landing page.';
        }

        if ($action === 'duplicate') {
            $id = (string)($_POST['id'] ?? '');
            $copy = $id !== '' ? landing_page_duplicate($id) : null;
            if ($copy) {
                if (function_exists('activity_log_record')) {
                    activity_log_record('duplicate', 'landing_page', (string)$copy['id'], 'Landing page diduplikat.', ['sumber_id' => $id, 'slug' => $copy['slug'] ?? '']);
                }
                redirect_302('admin/landing-pages?edit=' . rawurlencode((string)$copy['id']) . '&message=' . rawurlencode('Landing page berhasil diduplikat sebagai draft.'));
            }
            $error = 'Gagal menduplikat landing page.';
        }

        if ($action === 'restore_revision') {
            $id = (string)($_POST['id'] ?? '');
            $revisionId = (string)($_POST['revision_id'] ?? '');
            $note = trim((string)($_POST['revision_note'] ?? ''));
            $restored = ($id !== '' && $revisionId !== '') ? landing_page_restore_revision($id, $revisionId, $note) : null;
            if ($restored) {
                if (function_exists('activity_log_record')) {
                    activity_log_record('restore_revision', 'landing_page', (string)$restored['id'], 'Landing page direstore dari revision history.', ['revision_id' => $revisionId, 'slug' => $restored['slug'] ?? '']);
                }
                redirect_302('admin/landing-pages?edit=' . rawurlencode((string)$restored['id']) . '&message=' . rawurlencode('Landing page berhasil direstore dari revision history.'));
            }
            $error = 'Gagal restore revision landing page.';
        }

        if ($action === 'status_update') {
            $id = (string)($_POST['id'] ?? '');
            $status = trim((string)($_POST['status'] ?? 'draft'));
            $note = trim((string)($_POST['revision_note'] ?? ''));
            $page = $id !== '' ? landing_page_change_status($id, $status, $note) : null;
            if ($page) {
                if (function_exists('activity_log_record')) {
                    activity_log_record('status_update', 'landing_page', (string)$page['id'], 'Status landing page diperbarui.', ['status' => $page['status'] ?? '', 'slug' => $page['slug'] ?? '']);
                }
                redirect_302('admin/landing-pages?edit=' . rawurlencode((string)$page['id']) . '&message=' . rawurlencode('Status landing page berhasil diperbarui.'));
            }
            $error = 'Gagal mengubah status landing page.';
        }

        if ($action === 'save' || $action === 'save_template') {
            $blocksJson = trim((string)($_POST['blocks_json'] ?? ''));
            $blocks = $blocksJson !== '' ? json_decode($blocksJson, true) : [];
            if (!is_array($blocks)) {
                $error = 'Struktur blok belum valid. Coba refresh halaman atau klik pilih template ulang.';
            } else {
                $revisionNote = trim((string)($_POST['revision_note'] ?? ''));
                $page = landing_page_save(admin_landing_page_input_from_post($blocks), [
                    'note' => $revisionNote,
                    'action' => $action === 'save_template' ? 'save_template' : 'save',
                ]);

                if (function_exists('content_restriction_save_for')) {
                    content_restriction_save_for('landing_page', $page, content_restriction_rule_from_post($_POST));
                }

                if ($action === 'save_template') {
                    $templateName = trim((string)($_POST['template_name'] ?? ''));
                    if ($templateName === '') {
                        $templateName = (string)($page['title'] ?? 'Template Landing Page');
                    }
                    $template = landing_page_template_save_from_page($page, [
                        'name' => $templateName,
                        'category' => trim((string)($_POST['template_category'] ?? 'Custom UMKM')),
                        'description' => trim((string)($_POST['template_description'] ?? '')),
                        'include_seo' => !empty($_POST['template_include_seo']),
                        'include_tracking' => !empty($_POST['template_include_tracking']),
                    ]);

                    if (function_exists('activity_log_record')) {
                        activity_log_record('save_template', 'landing_page_template', (string)$template['id'], 'Landing page disimpan sebagai template.', ['sumber_lp_id' => $page['id'] ?? '', 'category' => $template['category'] ?? '']);
                    }

                    redirect_302('admin/landing-pages?edit=' . rawurlencode((string)$page['id']) . '&message=' . rawurlencode('Landing page berhasil disimpan sebagai Template Saya.'));
                }

                if (function_exists('activity_log_record')) {
                    activity_log_record('save', 'landing_page', (string)$page['id'], 'Landing page disimpan lewat workspace builder.', ['slug' => $page['slug'] ?? '']);
                }

                redirect_302('admin/landing-pages?edit=' . rawurlencode((string)$page['id']) . '&message=' . rawurlencode('Landing page berhasil disimpan.'));
            }
        }
    }
}

$loggedIn = admin_landing_pages_logged_in();
$summary = $loggedIn ? landing_page_summary() : ['counts' => [], 'items' => []];
$pages = $loggedIn ? array_values((array)($summary['items'] ?? [])) : [];
$templateSummary = $loggedIn ? landing_page_template_summary() : ['counts' => [], 'items' => []];
$templates = $loggedIn ? array_values((array)($templateSummary['items'] ?? [])) : [];
$templateCounts = (array)($templateSummary['counts'] ?? []);
$editId = trim((string)($_GET['edit'] ?? ''));
$editing = $editId !== '' ? landing_page_find($editId) : null;
if (!$editing) {
    $editing = landing_page_normalize([
        'id' => '',
        'title' => '',
        'slug' => '',
        'status' => 'draft',
        'layout_mode' => 'focus',
        'hide_header' => true,
        'hide_footer' => true,
        'hide_floating_wa' => true,
        'show_nav_only' => false,
        'mini_footer_brand' => '',
        'mini_footer_text' => '',
        'mini_footer_bg' => '',
        'mini_footer_text_color' => '',
        'mini_footer_brand_color' => '',
        'mini_footer_text_size' => '',
        'mini_footer_align' => '',
        'motion_enabled' => true,
        'motion_style' => 'fade-up',
        'indexable' => false,
        'ab_tests' => function_exists('landing_page_ab_default_config') ? landing_page_ab_default_config() : [],
        'full_html_mode' => false,
        'raw_html_document' => '',
        'blocks' => [],
    ]);
    $editing['id'] = '';
}

$workspaceMode = $loggedIn && ($editId !== '' || trim((string)($_GET['builder'] ?? '')) !== '');
$requestedTemplate = trim((string)($_GET['template'] ?? ''));
if (!in_array($requestedTemplate, ['direct', 'lead', 'whatsapp'], true)) {
    $requestedTemplate = '';
}
$customTemplateId = trim((string)($_GET['custom_template'] ?? ''));
$customTemplate = ($workspaceMode && $editId === '' && $customTemplateId !== '') ? landing_page_template_find($customTemplateId) : null;
if ($customTemplate) {
    $editing = landing_page_normalize(landing_page_template_to_page_seed($customTemplate));
    $editing['id'] = '';
}

$builtinTemplateId = trim((string)($_GET['builtin_template'] ?? ''));
$builtinTemplate = ($workspaceMode && $editId === '' && $builtinTemplateId !== '') ? landing_page_builtin_template_find($builtinTemplateId) : null;
if ($builtinTemplate) {
    $editing = landing_page_normalize(landing_page_builtin_template_to_page_seed($builtinTemplate));
    $editing['id'] = '';
}

$editingId = (string)($editing['id'] ?? '');
$editingRevisions = $editingId !== '' ? landing_page_revisions_for_page($editingId, 12) : [];
$editingRevisionCount = $editingId !== '' ? landing_page_revision_count($editingId) : 0;
$editingLastRevision = $editingId !== '' ? landing_page_revision_last($editingId) : null;
$editingAbTests = function_exists('landing_page_ab_sanitize_config') ? landing_page_ab_sanitize_config($editing['ab_tests'] ?? []) : [];
$editingCtaAb = (array)($editingAbTests['cta'] ?? []);
$editingFormAb = (array)($editingAbTests['form'] ?? []);
$editingCtaA = (array)($editingCtaAb['variasi_a'] ?? []);
$editingCtaB = (array)($editingCtaAb['variasi_b'] ?? []);
$editingFormA = (array)($editingFormAb['variasi_a'] ?? []);
$editingFormB = (array)($editingFormAb['variasi_b'] ?? []);

$builtinTemplates = landing_page_builtin_templates();
$presetSections = landing_page_preset_sections();
$presetPacks = function_exists('landing_page_smart_preset_packs') ? landing_page_smart_preset_packs() : [];
$assistantSeed = function_exists('landing_page_ai_assistant_seed') ? landing_page_ai_assistant_seed() : [];
$builtinTemplatePayload = [];
foreach ($builtinTemplates as $template) {
    $seed = landing_page_builtin_template_to_page_seed($template);
    $builtinTemplatePayload[(string)($template['id'] ?? '')] = [
        'id' => (string)($template['id'] ?? ''),
        'name' => (string)($template['name'] ?? ''),
        'category' => (string)($template['category'] ?? ''),
        'description' => (string)($template['description'] ?? ''),
        'title' => (string)($seed['title'] ?? ''),
        'slug' => (string)($seed['slug'] ?? ''),
        'tracking_label' => (string)($seed['tracking_label'] ?? ''),
        'meta_title' => (string)($seed['meta_title'] ?? ''),
        'meta_description' => (string)($seed['meta_description'] ?? ''),
        'meta_keywords' => (string)($seed['meta_keywords'] ?? ''),
        'layout_mode' => (string)($seed['layout_mode'] ?? 'focus'),
        'hide_header' => !empty($seed['hide_header']) ? '1' : '',
        'hide_footer' => !empty($seed['hide_footer']) ? '1' : '',
        'hide_floating_wa' => !empty($seed['hide_floating_wa']) ? '1' : '',
        'show_nav_only' => !empty($seed['show_nav_only']) ? '1' : '',
        'mini_footer_brand' => (string)($seed['mini_footer_brand'] ?? ''),
        'mini_footer_text' => (string)($seed['mini_footer_text'] ?? ''),
        'mini_footer_bg' => (string)($seed['mini_footer_bg'] ?? ''),
        'mini_footer_text_color' => (string)($seed['mini_footer_text_color'] ?? ''),
        'mini_footer_brand_color' => (string)($seed['mini_footer_brand_color'] ?? ''),
        'mini_footer_text_size' => (string)($seed['mini_footer_text_size'] ?? ''),
        'mini_footer_align' => (string)($seed['mini_footer_align'] ?? ''),
        'blocks' => $seed['blocks'] ?? [],
    ];
}
$customTemplatePayload = [];
foreach ($templates as $template) {
    $customTemplatePayload[(string)($template['id'] ?? '')] = [
        'id' => (string)($template['id'] ?? ''),
        'name' => (string)($template['name'] ?? 'Template Landing Page'),
        'category' => (string)($template['category'] ?? 'Custom UMKM'),
        'description' => (string)($template['description'] ?? ''),
        'title' => 'Landing Page dari ' . (string)($template['name'] ?? 'Template Landing Page'),
        'slug' => '',
        'tracking_label' => (string)($template['tracking_label'] ?? $template['name'] ?? 'Template Landing Page'),
        'meta_title' => (string)($template['meta_title'] ?? ''),
        'meta_description' => (string)($template['meta_description'] ?? ''),
        'meta_keywords' => (string)($template['meta_keywords'] ?? ''),
        'layout_mode' => (string)($template['layout_mode'] ?? 'focus'),
        'hide_header' => !empty($template['hide_header']) ? '1' : '',
        'hide_footer' => !empty($template['hide_footer']) ? '1' : '',
        'hide_floating_wa' => !empty($template['hide_floating_wa']) ? '1' : '',
        'show_nav_only' => !empty($template['show_nav_only']) ? '1' : '',
        'mini_footer_brand' => (string)($template['mini_footer_brand'] ?? ''),
        'mini_footer_text' => (string)($template['mini_footer_text'] ?? ''),
        'mini_footer_bg' => (string)($template['mini_footer_bg'] ?? ''),
        'mini_footer_text_color' => (string)($template['mini_footer_text_color'] ?? ''),
        'mini_footer_brand_color' => (string)($template['mini_footer_brand_color'] ?? ''),
        'mini_footer_text_size' => (string)($template['mini_footer_text_size'] ?? ''),
        'mini_footer_align' => (string)($template['mini_footer_align'] ?? ''),
        'include_seo' => !empty($template['include_seo']) ? '1' : '',
        'include_tracking' => !empty($template['include_tracking']) ? '1' : '',
        'blocks' => array_values((array)($template['blocks'] ?? [])),
    ];
}

$presetPayload = [];
foreach ($presetSections as $key => $preset) {
    $presetPayload[(string)$key] = [
        'label' => (string)($preset['label'] ?? $key),
        'group' => (string)($preset['group'] ?? 'Preset'),
        'block' => $preset['block'] ?? [],
    ];
}
$presetPackPayload = [];
foreach ($presetPacks as $key => $pack) {
    $presetPackPayload[(string)$key] = [
        'label' => (string)($pack['label'] ?? $key),
        'category' => (string)($pack['category'] ?? 'Smart Preset'),
        'description' => (string)($pack['description'] ?? ''),
        'suggested_title' => (string)($pack['suggested_title'] ?? ''),
        'suggested_slug' => (string)($pack['suggested_slug'] ?? ''),
        'tracking_label' => (string)($pack['tracking_label'] ?? ''),
        'meta_title' => (string)($pack['meta_title'] ?? ''),
        'meta_description' => (string)($pack['meta_description'] ?? ''),
        'meta_keywords' => (string)($pack['meta_keywords'] ?? ''),
        'blocks' => array_values((array)($pack['blocks'] ?? [])),
    ];
}

$activeCustomForms = [];
if (function_exists('custom_form_read_forms')) {
    foreach (custom_form_read_forms() as $customForm) {
        if ((string)($customForm['status'] ?? '') !== 'active') {
            continue;
        }
        $activeCustomForms[] = [
            'slug' => (string)($customForm['slug'] ?? ''),
            'title' => (string)($customForm['title'] ?? 'Form Custom'),
            'type' => (string)($customForm['type'] ?? 'custom'),
            'fields' => count((array)($customForm['fields'] ?? [])),
        ];
    }
}

$lpJsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$blocksJson = json_encode($editing['blocks'] ?? [], $lpJsonFlags) ?: '[]';
$templateDirect = json_encode(landing_page_default_blocks(), $lpJsonFlags) ?: '[]';
$templateLeadMagnet = json_encode([
    ['type' => 'hero_offer', 'eyebrow' => 'Free Konsultasi', 'headline' => 'Dapatkan Rekomendasi Paket Terbaik untuk Kebutuhan Anda', 'subheadline' => 'Isi form singkat, admin akan bantu cek kebutuhan, stok, lokasi, dan estimasi biaya.', 'image' => asset('images/placeholder-product.svg'), 'image_alt' => 'Konsultasi paket', 'primary_text' => 'Isi Form Konsultasi', 'primary_url' => '#form-konsultasi', 'bg_color' => '#eff6ff', 'headline_size' => '44px', 'text_size' => '18px'],
    ['type' => 'benefits', 'headline' => 'Apa yang Anda dapatkan?', 'items' => [['title' => 'Arahan cepat', 'text' => 'Admin membantu memilih paket sesuai kebutuhan.'], ['title' => 'Follow-up rapi', 'text' => 'Data bisa masuk ke Mailketing/Fonnte jika integrasi aktif.'], ['title' => 'Tracking iklan', 'text' => 'Form terhubung ke event conversion website.']]],
    ['type' => 'lead_form', 'headline' => 'Isi Form Konsultasi', 'text' => 'Lengkapi data berikut agar admin bisa follow-up dengan cepat.', 'submit_text' => 'Kirim Konsultasi', 'success_text' => 'Terima kasih, data konsultasi sudah masuk.', 'need_default' => 'Konsultasi landing page', 'mailketing_list_id' => '', 'form_name' => 'Form Lead Magnet', 'lead_segment' => 'lead-magnet', 'lead_tags' => 'lead-magnet,lp-form', 'lead_priority' => 'warm', 'lead_stage' => 'new-lead', 'lead_score' => '55', 'consent_text' => 'Saya bersedia dihubungi admin untuk follow-up kebutuhan saya.', 'fields' => landing_page_default_form_fields(), 'bg_color' => '#f8fafc'],
    ['type' => 'faq', 'headline' => 'Pertanyaan Umum', 'items' => [['question' => 'Apakah wajib order setelah isi form?', 'answer' => 'Tidak. Form ini untuk konsultasi dan follow-up awal.'], ['question' => 'Apakah data saya aman?', 'answer' => 'Data hanya dipakai admin untuk follow-up sesuai persetujuan.']]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
$templateWhatsapp = json_encode([
    ['type' => 'hero_offer', 'eyebrow' => 'Promo Iklan', 'headline' => 'Penawaran Khusus Hari Ini', 'subheadline' => 'Klik WhatsApp atau isi form, admin akan bantu rekomendasikan paket terbaik.', 'image' => asset('images/placeholder-product.svg'), 'image_alt' => 'Penawaran khusus', 'primary_text' => 'Chat WhatsApp', 'primary_url' => wa_link_contextual('saya mau tanya promo dari landing page.', ['sumber' => 'Landing Page Direct WA']), 'secondary_text' => 'Isi Form', 'secondary_url' => '#form-konsultasi', 'bg_color' => '#1e3a8a', 'text_color' => '#ffffff', 'accent_color' => '#bfdbfe', 'headline_size' => '46px'],
    ['type' => 'pain_points', 'headline' => 'Masalah yang sering terjadi', 'items' => ['Bingung pilih paket yang sesuai budget.', 'Takut stok tidak sesuai kebutuhan.', 'Butuh admin cepat dan jelas.']],
    ['type' => 'pricing_cards', 'headline' => 'Pilih penawaran', 'items' => [['title' => 'Paket Hemat', 'price' => 'Konsultasi', 'features' => ['Cocok untuk mulai tanya stok', 'Follow-up via WhatsApp'], 'button_text' => 'Tanya Paket Hemat'], ['title' => 'Paket Rekomendasi', 'price' => 'Populer', 'features' => ['Pilihan aman untuk keluarga', 'Admin bantu arahan'], 'button_text' => 'Tanya Paket Rekomendasi']], 'button_url' => wa_link_contextual('saya ingin tanya paket dari landing page.', ['sumber' => 'Landing Page Pricing'])],
    ['type' => 'lead_form', 'headline' => 'Minta Admin Follow-up', 'text' => 'Admin akan menghubungi Anda sesuai data yang diisi.', 'submit_text' => 'Minta Follow-up', 'success_text' => 'Terima kasih, admin akan follow-up.', 'need_default' => 'Follow-up landing page', 'form_name' => 'Form WhatsApp Ads Follow-up', 'lead_segment' => 'whatsapp-ads', 'lead_tags' => 'whatsapp-ads,lp-form', 'lead_priority' => 'hot', 'lead_stage' => 'new-lead', 'lead_score' => '70', 'fields' => landing_page_default_form_fields()],
    ['type' => 'cta', 'headline' => 'Mau langsung konsultasi?', 'text' => 'Klik tombol WhatsApp untuk respon tercepat.', 'button_text' => 'Chat Admin Sekarang', 'button_url' => wa_link_contextual('saya ingin konsultasi sekarang.', ['sumber' => 'Landing Page Final Tombol']), 'bg_color' => '#eff6ff', 'align' => 'center'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Landing Page Builder & Analytics - ' . SITE_NAME,
    'description' => 'Pembuat landing page dengan simpan otomatis, undo/redo, SEO, form custom, kategori lead, contoh layout, draft/publish, riwayat revisi, laporan performa, tes variasi, dan bantuan copywriting.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';

if ($loggedIn && !$workspaceMode) {
    require_once ROOT_PATH . '/components/layout/header.php';
    $counts = (array)($summary['counts'] ?? []);
    ?>
    <main id="main-content" class="admin-shell admin-landing-pages-home admin-landing-pages-home-template">
        <section class="admin-hero admin-landing-home-hero">
            <div class="container admin-hero__inner">
                <div>
                    <div class="admin-eyebrow">Landing Page Builder</div>
                    <h1>Landing Page Builder</h1>
                    <p>Kelola landing page promosi. Klik Buat Baru atau Edit untuk menyusun halaman dengan preview langsung.</p>
                </div>
            </div>
        </section>

        <section class="admin-section">
            <div class="container">
                <?php if ($message !== ''): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
                <?php if ($error !== ''): ?><div class="admin-alert admin-alert--danger"><?= esc($error); ?></div><?php endif; ?>

                <div class="admin-card admin-lp-readiness-note" style="margin-bottom:16px">
                    <div class="admin-form-head admin-form-head--split">
                        <div>
                            <span class="admin-badge">Status Fitur LP Builder</span>
                            <h2>LP Builder sudah siap untuk produksi campaign</h2>
                            <p>Fitur utama sudah lengkap: workspace compact, smart preset, template gallery, publish guard, performance guard, countdown timer, CTA signal, analisis LP, optimasi, dan action plan. Gunakan pusat Marketing & Analytics untuk membaca data iklan, tracking, dan hasil CTA dari alur yang rapi.</p>
                        </div>
                        <div class="admin-row-actions">
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-page-analytics')); ?>">Analisis LP</a>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/analytics')); ?>">Analytics & Iklan</a>
                        </div>
                    </div>
                </div>

                <div class="admin-page-tab-actions">
                    <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/landing-pages?builder=new')); ?>">+ Buat LP Baru</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-pages?builder=new&template=direct')); ?>">Buat LP dengan Bantuan</a>
                </div>

                <div class="admin-grid admin-grid--stats admin-lp-home-metrics">
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Total Halaman</span><h2><?= (int)($counts['total'] ?? 0); ?></h2><p>Semua landing page.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Publik</span><h2><?= (int)($counts['published'] ?? 0); ?></h2><p>Sudah bisa diakses pengunjung.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Mode Fokus</span><h2><?= (int)($counts['focus'] ?? 0); ?></h2><p>Landing page promosi yang lebih fokus.</p></div>
                    <div class="admin-card admin-report-metric"><span class="admin-badge">Masuk Google</span><h2><?= (int)($counts['indexable'] ?? 0); ?></h2><p>Bisa masuk sitemap jika sudah publik.</p></div>
                </div>

                <div data-admin-page-tab-scope>
                    <div class="admin-page-subtabs admin-page-subtabs--4" role="tablist" aria-label="Bagian Landing Page Builder">
                        <button type="button" class="admin-page-subtab is-active" role="tab" aria-selected="true" data-admin-page-tab="lp-list"><span>1. Daftar LP</span><small>Halaman yang dibuat</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="lp-template"><span>2. Template LP</span><small>Contoh & bantuan</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="lp-my-template"><span>3. Template Saya</span><small>Template tersimpan</small></button>
                        <button type="button" class="admin-page-subtab" role="tab" aria-selected="false" data-admin-page-tab="lp-help"><span>4. Bantuan</span><small>Cara pakai LP</small></button>
                    </div>
                    <div class="admin-page-mobile-jump"><label class="admin-field"><span>Pilih bagian landing page</span><select data-admin-page-tab-select aria-label="Pilih bagian Landing Page"><option value="lp-list">1. Daftar LP</option><option value="lp-template">2. Template LP</option><option value="lp-my-template">3. Template Saya</option><option value="lp-help">4. Bantuan</option></select></label></div>

                    <section class="admin-page-tab-panel is-active" data-admin-page-tab-panel="lp-list">
                        <div class="admin-card admin-lp-home-card">
                            <div class="admin-form-head admin-form-head--split">
                                <div>
                                    <span class="admin-badge">Daftar landing page</span>
                                    <h2>Landing Page yang Sudah Dibuat</h2>
                                    <p>Halaman published bisa diakses dari format <code>/lp/slug</code>. Klik Edit untuk masuk ke workspace builder.</p>
                                </div>
                                <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/landing-pages?builder=new')); ?>">+ Buat Baru</a>
                            </div>
                            <div class="admin-table-wrap">
                                <table class="admin-table admin-lp-home-table">
                                    <thead>
                                        <tr><th>Landing Page</th><th>Status</th><th>Mode</th><th>SEO</th><th>Revisi</th><th>Aksi</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pages as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= esc((string)($item['title'] ?? '-')); ?></strong><br>
                                                    <small>/lp/<?= esc((string)($item['slug'] ?? '')); ?></small>
                                                </td>
                                                <td><span class="admin-status-pill <?= (string)($item['status'] ?? '') === 'published' ? 'admin-status-pill--ok' : 'admin-status-pill--info'; ?>"><?= esc((string)($item['status'] ?? 'draft')); ?></span></td>
                                                <td><?= esc((string)($item['layout_mode'] ?? 'focus')); ?></td>
                                                <td><?= !empty($item['indexable']) ? '<span class="admin-status-pill admin-status-pill--ok">index</span>' : '<span class="admin-status-pill admin-status-pill--warning">noindex</span>'; ?></td>
                                                <td><span class="admin-status-pill admin-status-pill--info"><?= landing_page_revision_count((string)($item['id'] ?? '')); ?>x</span></td>
                                                <td>
                                                    <div class="admin-row-actions">
                                                        <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/landing-pages?edit=' . rawurlencode((string)($item['id'] ?? '')))); ?>">Edit</a>
                                                        <?php if ((string)($item['status'] ?? '') === 'published'): ?>
                                                            <a class="admin-btn admin-btn--soft" href="<?= esc(landing_page_url((string)($item['slug'] ?? ''))); ?>" target="_blank" rel="noopener">Preview</a>
                                                        <?php endif; ?>
                                                        <?php if ((string)($item['status'] ?? '') !== 'published'): ?>
                                                            <form method="post">
                                                                <?= csrf_field(); ?>
                                                                <input type="hidden" name="action" value="status_update">
                                                                <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                                                <input type="hidden" name="status" value="published">
                                                                <input type="hidden" name="revision_note" value="Publish cepat dari daftar landing page">
                                                                <button class="admin-btn admin-btn--soft" type="submit">Publish</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="post">
                                                                <?= csrf_field(); ?>
                                                                <input type="hidden" name="action" value="status_update">
                                                                <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                                                <input type="hidden" name="status" value="draft">
                                                                <input type="hidden" name="revision_note" value="Kembali ke draft dari daftar landing page">
                                                                <button class="admin-btn admin-btn--soft" type="submit">Draft</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="post">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="action" value="duplicate">
                                                            <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                                            <button class="admin-btn admin-btn--soft" type="submit">Duplikat</button>
                                                        </form>
                                                        <form method="post" onsubmit="return confirm('Hapus landing page ini?');">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id" value="<?= esc((string)($item['id'] ?? '')); ?>">
                                                            <button class="admin-btn admin-btn--danger" type="submit">Hapus</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (!$pages): ?>
                                            <tr><td colspan="6">Belum ada landing page. Klik tombol Buat Baru untuk mulai.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="lp-template" hidden>
                        <div class="admin-grid admin-grid--2 admin-lp-home-grid">
                            <div class="admin-card admin-lp-home-card admin-lp-ai-home-card">
                                <div class="admin-form-head admin-form-head--split">
                                    <div>
                                        <span class="admin-badge">Bantuan</span>
                                        <h2>Bantuan Copywriting & SEO</h2>
                                        <p>Bantu membuat headline, deskripsi SEO, tombol, isi form, FAQ, dan checklist konten.</p>
                                    </div>
                                    <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/landing-pages?builder=new&template=direct')); ?>">Buat LP dengan Bantuan</a>
                                </div>
                            </div>
                            <div class="admin-card admin-lp-home-card">
                                <div class="admin-form-head admin-form-head--split">
                                    <div>
                                        <span class="admin-badge">Mulai Cepat</span>
                                        <h2>Buat Landing Page Baru</h2>
                                        <p>Pilih desain awal, lalu edit konten, tampilan, SEO, tracking, dan form sesuai kebutuhan bisnis.</p>
                                    </div>
                                </div>
                                <div class="admin-lp-template-cards">
                                    <a href="<?= esc(url('admin/landing-pages?builder=new&template=direct')); ?>"><strong>Direct Selling</strong><small>Hero, pain point, benefit, harga, FAQ, form, dan tombol.</small></a>
                                    <a href="<?= esc(url('admin/landing-pages?builder=new&template=lead')); ?>"><strong>Lead Magnet / List Building</strong><small>Fokus isi form, masuk inquiry, Mailketing/Fonnte, dan follow-up.</small></a>
                                    <a href="<?= esc(url('admin/landing-pages?builder=new&template=whatsapp')); ?>"><strong>Iklan WhatsApp</strong><small>Fokus klik WA, form follow-up, dan tracking iklan.</small></a>
                                </div>
                            </div>
                        </div>
                        <div class="admin-card admin-lp-home-card admin-lp-template-library-card admin-lp-template-template-bank">
                            <div class="admin-form-head admin-form-head--split">
                                <div><span class="admin-badge">Template LP</span><h2>Contoh Siap Pakai</h2><p>Pilih template promosi yang sudah membawa struktur blok, SEO dasar, form, tombol, internal link, dan tracking label.</p></div>
                                <strong class="admin-status-pill admin-status-pill--ok"><?= count($builtinTemplates); ?> template</strong>
                            </div>
                            <div class="admin-lp-custom-template-grid admin-lp-template-template-grid">
                                <?php foreach ($builtinTemplates as $template): ?>
                                    <article class="admin-lp-custom-template admin-lp-template-template-card">
                                        <div><span class="admin-badge"><?= esc((string)($template['category'] ?? 'Template')); ?></span><h3><?= esc((string)($template['name'] ?? 'Template Landing Page')); ?></h3><p><?= esc((string)($template['description'] ?? 'Template siap pakai.')); ?></p><small><?= count((array)($template['blocks'] ?? [])); ?> blok · SEO + form + tracking</small></div>
                                        <div class="admin-row-actions"><a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/landing-pages?builder=new&builtin_template=' . rawurlencode((string)($template['id'] ?? '')))); ?>">Pakai Template</a></div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="lp-my-template" hidden>
                        <div class="admin-card admin-lp-home-card admin-lp-template-library-card">
                            <div class="admin-form-head admin-form-head--split">
                                <div><span class="admin-badge">Template Saya</span><h2>Template Custom</h2><p>Gunakan ulang struktur Landing Page yang pernah disimpan sebagai template. Cocok untuk promosi kota, produk, atau niche baru.</p></div>
                                <strong class="admin-status-pill admin-status-pill--info"><?= (int)($templateCounts['total'] ?? 0); ?> template</strong>
                            </div>
                            <?php if ($templates): ?>
                                <div class="admin-lp-custom-template-grid">
                                    <?php foreach ($templates as $template): ?>
                                        <article class="admin-lp-custom-template">
                                            <div><span class="admin-badge"><?= esc((string)($template['category'] ?? 'Custom UMKM')); ?></span><h3><?= esc((string)($template['name'] ?? 'Template Landing Page')); ?></h3><p><?= esc((string)($template['description'] ?? 'Template tersimpan dari landing page existing.')); ?></p><small><?= count((array)($template['blocks'] ?? [])); ?> blok<?= !empty($template['include_seo']) ? ' · SEO ikut' : ''; ?><?= !empty($template['include_tracking']) ? ' · tracking ikut' : ''; ?></small></div>
                                            <div class="admin-row-actions">
                                                <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/landing-pages?builder=new&custom_template=' . rawurlencode((string)($template['id'] ?? '')))); ?>">Pakai Template</a>
                                                <form method="post" onsubmit="return confirm('Hapus template ini? Landing page yang sudah dibuat tidak ikut terhapus.');"><?= csrf_field(); ?><input type="hidden" name="action" value="delete_template"><input type="hidden" name="template_id" value="<?= esc((string)($template['id'] ?? '')); ?>"><button class="admin-btn admin-btn--danger" type="submit">Hapus</button></form>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="admin-helper-box"><h3>Belum ada Template Saya</h3><p>Masuk ke builder, susun landing page, lalu klik <strong>Simpan sebagai Template</strong>. Nanti template akan muncul di sini.</p></div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="admin-page-tab-panel" data-admin-page-tab-panel="lp-help" hidden>
                        <div class="admin-card admin-lp-home-card">
                            <span class="admin-badge">Bantuan</span>
                            <h2>Cara Pakai Landing Page</h2>
                            <ol class="admin-checklist">
                                <li>Buat landing page dari template cepat.</li>
                                <li>Edit blok penawaran dan form sesuai kebutuhan promosi.</li>
                                <li>Lengkapi SEO dan tracking label.</li>
                                <li>Preview desktop/tablet/mobile, lalu publish.</li>
                            </ol>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </main>
    <?php
    require_once ROOT_PATH . '/components/layout/footer.php';
    return;
}
?>
<body class="admin-lp-workspace-body admin-lp-workspace-template admin-lp-builder-v331">
<?php if (function_exists('analytics_render_body_noscript')) { analytics_render_body_noscript(); } ?>
<a href="#main-content" class="skip-link">Lewati ke Builder</a>

<?php if (!$loggedIn): ?>
    <main id="main-content" class="lpw-login-screen">
        <section class="admin-card admin-login-card lpw-login-card">
            <div class="admin-eyebrow">Landing Page Builder</div>
            <h1>Login Admin</h1>
            <p class="admin-muted">Masuk untuk membuat landing page direct selling, form custom, SEO, tracking, dan desain blok.</p>
            <?php if ($message !== ''): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="admin-alert admin-alert--danger"><?= esc($error); ?></div><?php endif; ?>
            <form method="post" class="admin-form-grid">
                <?= csrf_field(); ?>
                <label>Password Admin<input type="password" name="password" autocomplete="current-password" required></label>
                <button class="admin-btn admin-btn--primary" type="submit">Login</button>
            </form>
            <p><a href="<?= esc(url('admin/produk')); ?>">Kembali ke dashboard admin</a></p>
        </section>
    </main>
<?php else: ?>
    <main id="main-content" class="lpw-shell lpw-shell-v331" data-lpw-shell data-lp-builder-version="v33.1.15">
        <header class="lpw-topbar">
            <div class="lpw-brand">
                <span class="lpw-logo">LP</span>
                <div>
                    <small>Landing Page Builder</small>
                    <strong>Landing Page Builder</strong>
                </div>
            </div>
            <div class="lpw-topbar-actions lpw-topbar-actions--compact" aria-label="Aksi builder">
                <div class="lpw-action-group lpw-action-group--nav">
                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/landing-pages')); ?>">← Kembali</a>
                </div>
                <div class="lpw-action-group lpw-action-group--device" aria-label="Mode preview landing page">
                    <div class="lpw-device-switcher lpw-device-switcher--topbar" role="group" aria-label="Mode preview">
                        <button type="button" class="is-active" data-preview-device="desktop">Desktop</button>
                        <button type="button" data-preview-device="tablet">Tablet</button>
                        <button type="button" data-preview-device="mobile">Mobile</button>
                    </div>
                </div>
                <div class="lpw-action-group lpw-action-group--state" aria-label="Status autosave dan riwayat edit">
                    <button class="admin-btn admin-btn--ghost lpw-history-btn" type="button" data-lpw-undo disabled title="Undo perubahan terakhir">↶ Undo</button>
                    <button class="admin-btn admin-btn--ghost lpw-history-btn" type="button" data-lpw-redo disabled title="Redo perubahan yang dibatalkan">Redo ↷</button>
                    <span id="lpAutosaveStatus" class="lpw-autosave-status lpw-autosave-status--topbar">Tersimpan</span>
                </div>
                <div class="lpw-action-group lpw-action-group--publish">
                    <?php if (!empty($editing['slug']) && ($editing['status'] ?? '') === 'published'): ?>
                        <a class="admin-btn admin-btn--ghost lpw-live-preview-btn" href="<?= esc(landing_page_url((string)$editing['slug'])); ?>" target="_blank" rel="noopener">Preview Live</a>
                    <?php endif; ?>
                    <div class="lpw-publish-dropdown" data-lpw-save-dropdown>
                        <div class="lpw-publish-split" role="group" aria-label="Publish dan opsi penyimpanan landing page">
                            <button class="admin-btn admin-btn--primary lpw-publish-main" type="submit" form="landingBuilderForm" data-submit-action="save" data-force-status="published" data-revision-note="Publish dari topbar">Publish</button>
                            <button class="admin-btn admin-btn--primary lpw-publish-arrow" type="button" data-lpw-save-menu-toggle aria-haspopup="true" aria-expanded="false" aria-label="Buka opsi publish dan simpan">▾</button>
                        </div>
                        <div class="lpw-save-menu lpw-publish-menu" data-lpw-save-menu hidden>
                            <button type="submit" form="landingBuilderForm" data-submit-action="save" data-force-status="published" data-revision-note="Publish dari menu topbar">Publish sekarang</button>
                            <button type="submit" form="landingBuilderForm" data-submit-action="save" data-revision-note="Simpan manual dari topbar">Simpan</button>
                            <button type="submit" form="landingBuilderForm" data-submit-action="save" data-force-status="draft" data-revision-note="Simpan sebagai draft dari topbar">Simpan Draft</button>
                            <button type="button" data-open-template-gallery>Galeri Template</button>
                            <button type="button" data-open-template-dialog>Simpan sebagai Template</button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <button type="button" class="lpw-sidebar-toggle" data-lpw-sidebar-toggle aria-expanded="true" aria-label="Sembunyikan panel builder">
            <span class="lpw-sidebar-toggle-icon" aria-hidden="true">‹</span>
        </button>

        <?php if ($error !== ''): ?><div class="admin-alert admin-alert--danger lpw-alert"><?= esc($error); ?></div><?php endif; ?>

        <div class="lpw-grid">
            <aside class="lpw-sidebar" id="lpwBuilderSidebar" aria-label="Kontrol Landing Page Builder">
                <form method="post" class="admin-lp-form" id="landingBuilderForm">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" id="lpFormAction" value="save">
                    <input type="hidden" name="id" value="<?= esc((string)($editing['id'] ?? '')); ?>">
                    <input type="hidden" name="blocks_json" id="lpBlocksJson" value="<?= esc($blocksJson); ?>">
                    <input type="hidden" name="revision_note" id="lpRevisionNote" value="">

                    <div class="lpw-sticky-top">
                        <div class="lpw-workspace-hint" role="note">
                            <span>Tambah block, pilih block aktif, lalu edit isi/desainnya. Gunakan Preset System untuk menambah beberapa blok sekaligus atau pilih section satuan untuk melengkapi bagian tertentu.</span>
                        </div>
                    </div>

                    <div class="lpw-panel-scroll" aria-live="polite">
                        <div class="lpw-panel is-active" data-lp-panel="content">
                            <div class="lpw-sidebar-section lpw-add-blocks-section">
                                <div class="lpw-add-grid">
                                    <button type="button" data-add-block="hero_offer">+ Hero</button>
                                    <button type="button" data-add-block="pain_points">+ Pain Point</button>
                                    <button type="button" data-add-block="benefits">+ Benefit</button>
                                    <button type="button" data-add-block="pricing_cards">+ Paket Harga</button>
                                    <button type="button" data-add-block="countdown_timer">+ Countdown</button>
                                    <button type="button" data-add-block="lead_form">+ Form Custom</button>
                                    <button type="button" data-add-block="custom_menu">+ Menu Custom</button>
                                    <button type="button" data-add-block="media">+ Media</button>
                                    <button type="button" data-add-block="free_cards">+ Judul Bebas</button>
                                    <button type="button" data-add-block="text">+ Teks Bebas</button>
                                    <button type="button" data-add-block="faq">+ FAQ</button>
                                    <button type="button" data-add-block="testimonial">+ Testimoni</button>
                                    <button type="button" data-add-block="cta">+ Tombol</button>
                                    <button type="button" data-add-block="html_block">+ HTML Block</button>
                                </div>
                            </div>

                            <div class="lpw-sidebar-section lpw-template-template-panel lpw-preset-system-panel">
                                <div class="lpw-section-title"><span>PR</span><div><strong>Pilih Preset System</strong><small>Paket alur LP + section siap pakai. Tidak mengganti isi landing page.</small></div></div>
                                <details class="lpw-template-details lpw-smart-preset-details">
                                    <summary>Paket Smart LP</summary>
                                    <div class="lpw-smart-preset-grid">
                                        <?php foreach ($presetPacks as $key => $pack): ?>
                                            <button type="button" data-add-preset-pack="<?= esc((string)$key); ?>">
                                                <span><?= esc((string)($pack['category'] ?? 'Smart Preset')); ?> · <?= count((array)($pack['blocks'] ?? [])); ?> blok</span>
                                                <strong><?= esc((string)($pack['label'] ?? $key)); ?></strong>
                                                <small><?= esc((string)($pack['description'] ?? 'Tambah paket blok siap pakai.')); ?></small>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                                <details class="lpw-template-details">
                                    <summary>Section satuan / long copy</summary>
                                    <div class="lpw-template-preset-grid">
                                        <?php foreach ($presetSections as $key => $preset): ?>
                                            <button type="button" data-add-preset="<?= esc((string)$key); ?>">
                                                <span><?= esc((string)($preset['group'] ?? 'Preset')); ?></span>
                                                <strong><?= esc((string)($preset['label'] ?? $key)); ?></strong>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </details>
                            </div>


                            <div class="lpw-sidebar-section">
                                <div class="lpw-section-title"><span>01</span><div><strong>Blok Aktif</strong><small>Edit isi dan desain per blok. Tidak perlu JSON.</small></div></div>
                                <div class="lpw-section-toolbar" role="toolbar" aria-label="Aksi cepat blok">
                                    <button type="button" data-lpw-expand-all>Perluas editor aktif</button>
                                    <button type="button" data-lpw-collapse-all>Ringkas editor aktif</button>
                                    <button type="button" data-lpw-jump-issue>Cek yang belum lengkap</button>
                                </div>
                                <div id="lpBlocksEditor" class="lpw-block-editor" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="lpw-panel" data-lp-panel="seo">
                            <div class="lpw-sidebar-section lpw-seo-engine-shell">
                                <div class="lpw-section-title"><span>GX</span><div><strong>SEO & Conversion Sistem</strong><small>Rekomendasi otomatis, internal link, schema, dan kesiapan tracking.</small></div></div>
                                <div id="lpBuilderSeoSistem" class="lpw-engine-card" aria-live="polite"></div>
                            </div>

                            <div class="lpw-sidebar-section lpw-meta-card">
                                <div class="lpw-section-title">
                                    <span>SEO</span>
                                    <div><strong>Identitas & SEO Landing Page</strong><small>Judul, slug, tracking label, dan meta SEO.</small></div>
                                </div>
                                <label>Judul Landing Page<input id="lpTitleInput" name="title" value="<?= esc((string)($editing['title'] ?? '')); ?>" placeholder="Promo Paket Layanan Jakarta"></label>
                                <label>Slug<input id="lpSlugInput" name="slug" value="<?= esc((string)($editing['slug'] ?? '')); ?>" placeholder="promo-paket-layanan-jakarta"></label>
                                <label>Tracking Label<input name="tracking_label" value="<?= esc((string)($editing['tracking_label'] ?? '')); ?>" placeholder="Landing Page Promo Layanan"></label>
                                <label>Meta Title<input name="meta_title" value="<?= esc((string)($editing['meta_title'] ?? '')); ?>" placeholder="Judul SEO landing page"></label>
                                <label>OG Image URL<input name="og_image" value="<?= esc((string)($editing['og_image'] ?? '')); ?>" placeholder="https://domain.com/image.jpg"></label>
                                <label>Meta Description<textarea name="meta_description" rows="3" placeholder="Ringkasan halaman untuk Google dan share sosial media"><?= esc((string)($editing['meta_description'] ?? '')); ?></textarea></label>
                                <label>Meta Keywords<input name="meta_keywords" value="<?= esc((string)($editing['meta_keywords'] ?? '')); ?>" placeholder="kata kunci 1, kata kunci 2"></label>
                                <label class="lpw-check"><input type="checkbox" name="indexable" value="1" <?= !empty($editing['indexable']) ? 'checked' : ''; ?>> Index di Google & masuk sitemap</label>
                            </div>
                        </div>

                        <div class="lpw-panel" data-lp-panel="assistant">
                            <div class="lpw-sidebar-section lpw-ai-assistant-card">
                                <div class="lpw-section-title"><span>AI</span><div><strong>AI Copy Assistant</strong><small>Generate copy lokal untuk headline, Tombol, form, FAQ, dan long copy.</small></div></div>
                                <div class="lpw-helper-guide lpw-helper-guide--compact"><strong>Aman untuk Data</strong><p>Bantuan copywriting ini berjalan dari pola konten lokal. Data tidak dikirim ke layanan luar.</p></div>
                                <label>Fokus Produk / Layanan<input data-ai-field="product" placeholder="contoh: Paket Layanan, Produk Fisik, Paket Layanan"></label>
                                <label>Lokasi Target<input data-ai-field="location" placeholder="contoh: Jakarta, Bandung, Surabaya"></label>
                                <label>Keyword Utama<input data-ai-field="keyword" placeholder="contoh: paket layanan jakarta"></label>
                                <label>Offer / Keunggulan<textarea data-ai-field="offer" rows="2" placeholder="contoh: konsultasi gratis, admin bantu pilih paket, stok bisa dicek cepat"></textarea></label>
                                <label>Gaya Copy<select data-ai-field="tone"><option value="friendly">Ramah & edukatif</option><option value="direct">Direct selling</option><option value="premium">Premium & tepercaya</option><option value="urgent">Urgency iklan</option><option value="local">SEO Lokal</option></select></label>
                                <div class="lpw-ai-actions">
                                    <button type="button" data-ai-action="suggest">Generate Ide Copy</button>
                                    <button type="button" data-ai-action="seo">Isi SEO Pack</button>
                                    <button type="button" data-ai-action="hero">Buat/Update Hero</button>
                                    <button type="button" data-ai-action="full">Buat Full Landing Page Pack</button>
                                    <button type="button" data-ai-action="faq">Tambah FAQ SEO</button>
                                    <button type="button" data-ai-action="audit">Audit Copy & SEO</button>
                                </div>
                                <div id="lpAiAssistantOutput" class="lpw-ai-output" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="lpw-panel" data-lp-panel="tracking">
                            <div class="lpw-sidebar-section">
                                <div class="lpw-section-title"><span>TR</span><div><strong>Tracking Inspector</strong><small>Preview event, validasi event name, dan lead sumber sebelum publish.</small></div></div>
                                <div id="lpTrackingInspector" class="lpw-tracking-inspector" aria-live="polite"></div>
                            </div>

                            <div class="lpw-sidebar-section lpw-abtest-card">
                                <div class="lpw-section-title"><span>AB</span><div><strong>Tes Variasi Tombol/Form</strong><small>Uji copy tombol dan form tanpa membuat Landing Page baru.</small></div></div>
                                <div class="lpw-helper-guide lpw-helper-guide--compact"><strong>Cara kerja</strong><p>Pengunjung dibagi otomatis ke variasi A/B selama 30 hari. Kunjungan, klik tombol, form, lead, dan order akan terbaca di laporan landing page.</p></div>

                                <details class="lpw-template-details" open>
                                    <summary>Tombol Button Test</summary>
                                    <label class="lpw-check"><input type="checkbox" name="ab_cta_enabled" value="1" <?= !empty($editingCtaAb['enabled']) ? 'checked' : ''; ?>> Aktifkan A/B test Tombol</label>
                                    <label>Nama Test<input name="ab_cta_name" value="<?= esc((string)($editingCtaAb['name'] ?? 'Tombol Button Test')); ?>" placeholder="Tombol Button Test"></label>
                                    <div class="lpw-ab-grid">
                                        <div>
                                            <strong>Variasi A</strong>
                                            <label>Label<input name="ab_cta_a_label" value="<?= esc((string)($editingCtaA['label'] ?? 'A - Control')); ?>"></label>
                                            <label>Teks tombol<input name="ab_cta_a_text" value="<?= esc((string)($editingCtaA['button_text'] ?? '')); ?>" placeholder="Kosongkan = pakai teks asli blok"></label>
                                            <label>URL tombol<input name="ab_cta_a_url" value="<?= esc((string)($editingCtaA['button_url'] ?? '')); ?>" placeholder="Kosongkan = pakai URL asli blok"></label>
                                        </div>
                                        <div>
                                            <strong>Variasi B</strong>
                                            <label>Label<input name="ab_cta_b_label" value="<?= esc((string)($editingCtaB['label'] ?? 'B - Variasi')); ?>"></label>
                                            <label>Teks tombol<input name="ab_cta_b_text" value="<?= esc((string)($editingCtaB['button_text'] ?? '')); ?>" placeholder="Contoh: Chat Admin Sekarang"></label>
                                            <label>URL tombol<input name="ab_cta_b_url" value="<?= esc((string)($editingCtaB['button_url'] ?? '')); ?>" placeholder="/checkout atau https://wa.me/..."></label>
                                        </div>
                                    </div>
                                </details>

                                <details class="lpw-template-details">
                                    <summary>Form Copy Test</summary>
                                    <label class="lpw-check"><input type="checkbox" name="ab_form_enabled" value="1" <?= !empty($editingFormAb['enabled']) ? 'checked' : ''; ?>> Aktifkan A/B test Form</label>
                                    <label>Nama Test<input name="ab_form_name" value="<?= esc((string)($editingFormAb['name'] ?? 'Form Copy Test')); ?>" placeholder="Form Copy Test"></label>
                                    <div class="lpw-ab-grid">
                                        <div>
                                            <strong>Variasi A</strong>
                                            <label>Label<input name="ab_form_a_label" value="<?= esc((string)($editingFormA['label'] ?? 'A - Control')); ?>"></label>
                                            <label>Headline<input name="ab_form_a_headline" value="<?= esc((string)($editingFormA['headline'] ?? '')); ?>" placeholder="Kosongkan = headline asli"></label>
                                            <label>Deskripsi<textarea name="ab_form_a_text" rows="2" placeholder="Kosongkan = deskripsi asli"><?= esc((string)($editingFormA['text'] ?? '')); ?></textarea></label>
                                            <label>Tombol submit<input name="ab_form_a_submit" value="<?= esc((string)($editingFormA['submit_text'] ?? '')); ?>" placeholder="Kosongkan = tombol asli"></label>
                                            <label>Lead segment<input name="ab_form_a_segment" value="<?= esc((string)($editingFormA['lead_segment'] ?? '')); ?>" placeholder="contoh: lp-form-a"></label>
                                        </div>
                                        <div>
                                            <strong>Variasi B</strong>
                                            <label>Label<input name="ab_form_b_label" value="<?= esc((string)($editingFormB['label'] ?? 'B - Variasi')); ?>"></label>
                                            <label>Headline<input name="ab_form_b_headline" value="<?= esc((string)($editingFormB['headline'] ?? '')); ?>" placeholder="Contoh: Mau dibantu pilih paket?"></label>
                                            <label>Deskripsi<textarea name="ab_form_b_text" rows="2" placeholder="Contoh: Isi data singkat, admin follow-up cepat."><?= esc((string)($editingFormB['text'] ?? '')); ?></textarea></label>
                                            <label>Tombol submit<input name="ab_form_b_submit" value="<?= esc((string)($editingFormB['submit_text'] ?? '')); ?>" placeholder="Contoh: Minta Admin Follow-up"></label>
                                            <label>Lead segment<input name="ab_form_b_segment" value="<?= esc((string)($editingFormB['lead_segment'] ?? '')); ?>" placeholder="contoh: lp-form-b"></label>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        </div>

                        <div class="lpw-panel" data-lp-panel="optimization">
                            <div class="lpw-sidebar-section lpw-health-shell">
                                <div class="lpw-section-title"><span>HC</span><div><strong>Health Check Landing Page</strong><small>Checklist otomatis agar Landing Page siap iklan, SEO, dan follow-up.</small></div></div>
                                <div id="lpBuilderHealth" class="lpw-health-card" aria-live="polite"></div>
                            </div>

                            <div class="lpw-sidebar-section lpw-publish-checklist-shell">
                                <div class="lpw-section-title"><span>PG</span><div><strong>Publish Guard & Conversion Checklist</strong><small>Cek kesiapan sebelum LP dipublish ke publik.</small></div></div>
                                <div id="lpPublishChecklist" class="lpw-publish-checklist" aria-live="polite"></div>
                            </div>

                            <div class="lpw-sidebar-section lpw-performance-shell">
                                <div class="lpw-section-title"><span>PF</span><div><strong>Analisa Performa & Improvement</strong><small>Ringkasan funnel, CTA, efek visual, dan rekomendasi optimasi blok.</small></div></div>
                                <div id="lpBuilderPerformance" class="lpw-performance-card" aria-live="polite"></div>
                            </div>

                            <div class="lpw-sidebar-section lpw-speed-guard-shell">
                                <div class="lpw-section-title"><span>SG</span><div><strong>Speed Guard & Performance Optimizer</strong><small>Cek ringan/beratnya LP publik untuk kebutuhan iklan UMKM.</small></div></div>
                                <div id="lpPerformanceOptimizer" class="lpw-speed-guard-card" aria-live="polite"></div>
                            </div>

                            <div class="lpw-sidebar-section lpw-buyer-view-shell">
                                <div class="lpw-section-title"><span>BV</span><div><strong>Buyer View Advisor</strong><small>Melihat LP dari sudut pandang calon pembeli: jelas, meyakinkan, nyaman, dan mudah klik.</small></div></div>
                                <div id="lpBuyerViewAdvisor" class="lpw-buyer-view-card" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="lpw-panel" data-lp-panel="settings">
                            <div class="lpw-sidebar-section">
                                <div class="lpw-section-title"><span>UI</span><div><strong>Mode Tampilan</strong><small>Focus mode cocok untuk traffic iklan.</small></div></div>
                                <label>Layout Mode<select name="layout_mode"><option value="focus" <?= ($editing['layout_mode'] ?? '') === 'focus' ? 'selected' : ''; ?>>Focus Landing Page</option><option value="website" <?= ($editing['layout_mode'] ?? '') === 'website' ? 'selected' : ''; ?>>Full Website Layout</option></select></label>
                                <label class="lpw-check"><input type="checkbox" name="hide_header" value="1" <?= !empty($editing['hide_header']) ? 'checked' : ''; ?>> Sembunyikan header/menu</label>
                                <label class="lpw-check"><input type="checkbox" name="hide_footer" value="1" <?= !empty($editing['hide_footer']) ? 'checked' : ''; ?>> Footer mini/no distraction</label>
                                <label class="lpw-check"><input type="checkbox" name="hide_floating_wa" value="1" <?= !empty($editing['hide_floating_wa']) ? 'checked' : ''; ?>> Matikan floating WA global</label>
                                <label class="lpw-check"><input type="checkbox" name="show_nav_only" value="1" <?= !empty($editing['show_nav_only']) ? 'checked' : ''; ?>> Tampilkan hanya navigasi menu</label>
                                <label>Judul mini footer <small>Tampil di kiri mini footer focus mode. Kosong = nama website.</small><input name="mini_footer_brand" value="<?= esc((string)($editing['mini_footer_brand'] ?? '')); ?>" placeholder="Contoh: Nama Brand / Promo Khusus"></label>
                                <label>Teks mini footer <small>Tampil di kanan mini footer. Kosong = teks default landing page.</small><input name="mini_footer_text" value="<?= esc((string)($editing['mini_footer_text'] ?? '')); ?>" placeholder="Contoh: Konsultasi gratis · 2026"></label>
                                <details class="lpw-design-panel lpw-design-panel--mini-footer"><summary>Desain mini footer</summary>
                                    <div class="lpw-design-grid">
                                        <label>Background footer<input type="color" name="mini_footer_bg" value="<?= esc((string)($editing['mini_footer_bg'] ?? '#052e2b') ?: '#052e2b'); ?>"></label>
                                        <label>Warna teks footer<input type="color" name="mini_footer_text_color" value="<?= esc((string)($editing['mini_footer_text_color'] ?? '#cbd5e1') ?: '#cbd5e1'); ?>"></label>
                                        <label>Warna brand footer<input type="color" name="mini_footer_brand_color" value="<?= esc((string)($editing['mini_footer_brand_color'] ?? '#ffffff') ?: '#ffffff'); ?>"></label>
                                        <label>Ukuran teks footer<select name="mini_footer_text_size"><option value="" <?= empty($editing['mini_footer_text_size']) ? 'selected' : ''; ?>>Default</option><option value="12px" <?= (string)($editing['mini_footer_text_size'] ?? '') === '12px' ? 'selected' : ''; ?>>12px</option><option value="14px" <?= (string)($editing['mini_footer_text_size'] ?? '') === '14px' ? 'selected' : ''; ?>>14px</option><option value="16px" <?= (string)($editing['mini_footer_text_size'] ?? '') === '16px' ? 'selected' : ''; ?>>16px</option><option value="18px" <?= (string)($editing['mini_footer_text_size'] ?? '') === '18px' ? 'selected' : ''; ?>>18px</option></select></label>
                                        <label>Rata footer<select name="mini_footer_align"><option value="" <?= empty($editing['mini_footer_align']) ? 'selected' : ''; ?>>Default responsive</option><option value="left" <?= (string)($editing['mini_footer_align'] ?? '') === 'left' ? 'selected' : ''; ?>>Kiri</option><option value="center" <?= (string)($editing['mini_footer_align'] ?? '') === 'center' ? 'selected' : ''; ?>>Tengah</option><option value="right" <?= (string)($editing['mini_footer_align'] ?? '') === 'right' ? 'selected' : ''; ?>>Kanan</option></select></label>
                                    </div>
                                </details>
                                <label class="lpw-check"><input type="checkbox" name="motion_enabled" value="1" <?= !array_key_exists('motion_enabled', $editing) || !empty($editing['motion_enabled']) ? 'checked' : ''; ?>> Aktifkan efek ringan di LP ini</label>
                                <label>Style efek LP<select name="motion_style"><option value="fade-up" <?= (string)($editing['motion_style'] ?? 'fade-up') === 'fade-up' ? 'selected' : ''; ?>>Fade Up lembut</option><option value="zoom-soft" <?= (string)($editing['motion_style'] ?? '') === 'zoom-soft' ? 'selected' : ''; ?>>Zoom Soft</option><option value="fade" <?= (string)($editing['motion_style'] ?? '') === 'fade' ? 'selected' : ''; ?>>Fade saja</option></select></label>
                                <div class="lpw-helper-guide lpw-helper-guide--compact"><strong>Efek aman</strong><p>Efek ini ringan dan otomatis mati jika browser pengunjung mengaktifkan reduced motion.</p></div>
                            </div>

                            <div class="lpw-sidebar-section lpw-visual-polish-card">
                                <div class="lpw-section-title"><span>VP</span><div><strong>Visual Polish Cepat</strong><small>Pilih mood visual agar LP lebih enak dilihat calon pembeli tanpa edit warna satu-satu.</small></div></div>
                                <div class="lpw-visual-preset-grid">
                                    <button type="button" data-lpw-visual-preset="clean-premium"><strong>Clean Premium</strong><small>Putih rapi, soft card, cocok jasa/brand profesional.</small></button>
                                    <button type="button" data-lpw-visual-preset="soft-trust"><strong>Soft Trust</strong><small>Biru lembut, rasa aman, cocok form konsultasi.</small></button>
                                    <button type="button" data-lpw-visual-preset="promo-bold"><strong>Promo Bold</strong><small>Hero & CTA lebih tegas untuk campaign promo.</small></button>
                                </div>
                                <div class="lpw-helper-guide lpw-helper-guide--compact"><strong>Aman digunakan</strong><p>Preset ini hanya memoles warna, efek section, goal, CTA role, dan animasi ringan per blok. Isi teks dan form tidak dihapus.</p></div>
                            </div>

                            <div class="lpw-sidebar-section lpw-html-expert-card">
                                <div class="lpw-section-title"><span>HTML</span><div><strong>Full HTML Expert Mode</strong><small>Untuk import landing page dari HTML penuh. Script dan kode berisiko tetap dibersihkan.</small></div></div>
                                <label class="lpw-check"><input type="checkbox" name="full_html_mode" value="1" <?= !empty($editing['full_html_mode']) ? 'checked' : ''; ?>> Aktifkan full HTML mode untuk halaman publik</label>
                                <label>HTML penuh / body landing page<textarea name="raw_html_document" rows="9" placeholder="Tempel HTML landing page di sini. Script, iframe berbahaya, event handler, dan javascript: URL akan dibersihkan saat disimpan."><?= esc((string)($editing['raw_html_document'] ?? '')); ?></textarea></label>
                                <div class="lpw-helper-guide lpw-helper-guide--warning"><strong>Mode expert</strong><p>Gunakan hanya jika perlu import LP dari HTML lama. Analytics, SEO, canonical, sitemap, dan tracking U-Growth tetap aktif, tapi script eksternal tidak otomatis diizinkan.</p></div>
                            </div>

                            <?= function_exists('content_restriction_admin_fields') ? content_restriction_admin_fields('landing_page', $editing) : ''; ?>

                            <div class="lpw-sidebar-section lpw-status-card lpw-template-status-card">
                                <div class="lpw-section-title"><span>ST</span><div><strong>Draft/Publish Workflow</strong><small>Atur draft, published, archived, dan catatan revisi.</small></div></div>
                                <div class="lpw-template-state-grid">
                                    <div><span>Status sekarang</span><strong><?= esc((string)($editing['status'] ?? 'draft')); ?></strong></div>
                                    <div><span>Total revisi</span><strong><?= (int)$editingRevisionCount; ?>x</strong></div>
                                    <div><span>Update terakhir</span><strong><?= esc(date('d M Y H:i', strtotime((string)($editing['updated_at'] ?? 'now')))); ?></strong></div>
                                </div>
                                <label>Status<select name="status"><option value="draft" <?= ($editing['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option><option value="published" <?= ($editing['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Publik</option><option value="archived" <?= ($editing['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option></select></label>
                                <label>Catatan revisi<textarea data-revision-note-field rows="2" placeholder="Contoh: ubah headline, tambah form, publish promosi Meta Ads."></textarea></label>
                                <div class="lpw-status-help">
                                    <strong>Tips status</strong>
                                    <p><b>Draft</b> aman untuk editing. <b>Publik</b> membuka halaman publik. <b>Archived</b> menyembunyikan halaman lama tanpa menghapus datanya. Setiap simpan/publish otomatis membuat snapshot revision history.</p>
                                </div>
                                <div class="lpw-template-status-actions">
                                    <button class="admin-btn admin-btn--soft" type="submit" data-submit-action="save" data-force-status="draft" data-revision-note="Simpan sebagai draft dari panel status">Simpan Draft</button>
                                    <button class="admin-btn admin-btn--primary" type="submit" data-submit-action="save" data-force-status="published" data-revision-note="Publish dari panel status">Publish</button>
                                    <button class="admin-btn admin-btn--ghost" type="submit" data-submit-action="save" data-force-status="archived" data-revision-note="Archive dari panel status">Archive</button>
                                </div>
                                <?php if (!empty($editing['slug'])): ?>
                                    <a class="admin-btn admin-btn--ghost" href="<?= esc(landing_page_url((string)$editing['slug'])); ?>" target="_blank" rel="noopener">Buka preview live</a>
                                <?php endif; ?>
                            </div>
                            <div class="lpw-sidebar-section lpw-template-revision-card">
                                <div class="lpw-section-title"><span>RV</span><div><strong>Revision History</strong><small>Restore cepat kalau perubahan terbaru kurang cocok.</small></div></div>
                                <?php if ($editingRevisions): ?>
                                    <div class="lpw-template-revision-list">
                                        <?php foreach ($editingRevisions as $revision): ?>
                                            <article class="lpw-template-revision-item">
                                                <div>
                                                    <strong>#<?= (int)($revision['revision_number'] ?? 0); ?> · <?= esc((string)($revision['action_label'] ?? 'Save')); ?></strong>
                                                    <small><?= esc(date('d M Y H:i', strtotime((string)($revision['created_at'] ?? 'now')))); ?> · <?= esc((string)($revision['status'] ?? 'draft')); ?></small>
                                                </div>
                                                <?php if (!empty($revision['note'])): ?><p><?= esc((string)$revision['note']); ?></p><?php endif; ?>
                                                <?php if (!empty($revision['summary']) && is_array($revision['summary'])): ?>
                                                    <ul>
                                                        <?php foreach (array_slice((array)$revision['summary'], 0, 3) as $line): ?><li><?= esc((string)$line); ?></li><?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                                <button class="admin-btn admin-btn--ghost" type="submit" name="revision_id" value="<?= esc((string)($revision['id'] ?? '')); ?>" data-submit-action="restore_revision" data-restore-revision="<?= (int)($revision['revision_number'] ?? 0); ?>">Restore revisi ini</button>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="lpw-helper-guide lpw-helper-guide--compact"><strong>Belum ada revision snapshot</strong><p>Setelah landing page disimpan, daftar revisi akan muncul di sini otomatis.</p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="lpw-builder-tabs" role="tablist" aria-label="Landing page workspace tabs">
                        <button type="button" class="is-active" data-lp-tab="content">Konten</button>
                        <button type="button" data-lp-tab="seo">SEO</button>
                        <button type="button" data-lp-tab="assistant">AI Copy</button>
                        <button type="button" data-lp-tab="tracking">Tracking</button>
                        <button type="button" data-lp-tab="optimization">Optimasi</button>
                        <button type="button" data-lp-tab="settings">Pengaturan</button>
                    </div>

                    <dialog class="lpw-template-dialog" data-template-dialog>
                        <div class="lpw-template-dialog-head">
                            <div>
                                <span class="admin-badge">Template Saya</span>
                                <h2>Simpan sebagai Template</h2>
                                <p>Template menyimpan struktur blok, desain, dan form fields. Slug, status publish, dan statistik tidak ikut disalin.</p>
                            </div>
                            <button type="button" class="lpw-dialog-close" data-close-template-dialog aria-label="Tutup">×</button>
                        </div>
                        <label>Nama Template<input name="template_name" value="<?= esc((string)($editing['title'] ?? 'Template Landing Page')); ?>" placeholder="Template Promo Produk"></label>
                        <label>Kategori<select name="template_category">
                            <?php foreach (landing_page_template_categories() as $category): ?>
                                <option value="<?= esc($category); ?>"><?= esc($category); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <label>Deskripsi singkat<textarea name="template_description" rows="3" placeholder="Contoh: struktur direct selling untuk iklan Google/Meta."></textarea></label>
                        <div class="lpw-template-options">
                            <label class="lpw-check"><input type="checkbox" name="template_include_seo" value="1" checked> Ikutkan SEO default</label>
                            <label class="lpw-check"><input type="checkbox" name="template_include_tracking" value="1" checked> Ikutkan tracking label</label>
                        </div>
                        <div class="lpw-template-dialog-actions">
                            <button type="button" class="admin-btn admin-btn--ghost" data-close-template-dialog>Batal</button>
                            <button type="submit" class="admin-btn admin-btn--primary" data-submit-action="save_template">Simpan sebagai Template</button>
                        </div>
                    </dialog>

                    <dialog class="lpw-template-dialog lpw-template-gallery-dialog" data-template-gallery-dialog>
                        <div class="lpw-template-dialog-head">
                            <div>
                                <span class="admin-badge">Template Gallery</span>
                                <h2>Galeri Template LP</h2>
                                <p>Pilih template full landing page untuk halaman baru/campaign baru. Template bawaan dan Template Saya tetap bisa diedit setelah diterapkan.</p>
                            </div>
                            <button type="button" class="lpw-dialog-close" data-close-template-gallery aria-label="Tutup">×</button>
                        </div>
                        <div class="lpw-template-gallery-help">
                            <strong>Bedanya dengan Smart Preset</strong>
                            <p><b>Template Gallery</b> mengganti susunan LP menjadi template full. <b>Smart Preset</b> menambah beberapa section ke LP yang sedang diedit tanpa menghapus isi.</p>
                        </div>
                        <div class="lpw-template-gallery-columns">
                            <section>
                                <div class="lpw-template-gallery-section-head">
                                    <strong>Template Bawaan</strong>
                                    <small><?= count($builtinTemplates); ?> template siap pakai</small>
                                </div>
                                <div class="lpw-template-gallery-grid">
                                    <?php foreach ($builtinTemplates as $template): ?>
                                        <article class="lpw-template-gallery-item">
                                            <span class="admin-badge"><?= esc((string)($template['category'] ?? 'Template')); ?></span>
                                            <h3><?= esc((string)($template['name'] ?? 'Template Landing Page')); ?></h3>
                                            <p><?= esc((string)($template['description'] ?? 'Template siap pakai.')); ?></p>
                                            <small><?= count((array)($template['blocks'] ?? [])); ?> blok · SEO + form + tracking</small>
                                            <div class="lpw-template-gallery-actions">
                                                <button type="button" class="admin-btn admin-btn--primary" data-apply-template-gallery="builtin" data-template-id="<?= esc((string)($template['id'] ?? '')); ?>">Pakai di halaman ini</button>
                                                <a class="admin-btn admin-btn--ghost" href="<?= esc(url('admin/landing-pages?builder=new&builtin_template=' . rawurlencode((string)($template['id'] ?? '')))); ?>">Buat LP baru</a>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                            <section>
                                <div class="lpw-template-gallery-section-head">
                                    <strong>Template Saya</strong>
                                    <small><?= count($templates); ?> template tersimpan</small>
                                </div>
                                <?php if ($templates): ?>
                                    <div class="lpw-template-gallery-grid">
                                        <?php foreach ($templates as $template): ?>
                                            <article class="lpw-template-gallery-item lpw-template-gallery-item--custom">
                                                <span class="admin-badge"><?= esc((string)($template['category'] ?? 'Custom UMKM')); ?></span>
                                                <h3><?= esc((string)($template['name'] ?? 'Template Landing Page')); ?></h3>
                                                <p><?= esc((string)($template['description'] ?? 'Template tersimpan dari landing page existing.')); ?></p>
                                                <small><?= count((array)($template['blocks'] ?? [])); ?> blok<?= !empty($template['include_seo']) ? ' · SEO ikut' : ''; ?><?= !empty($template['include_tracking']) ? ' · tracking ikut' : ''; ?></small>
                                                <div class="lpw-template-gallery-actions">
                                                    <button type="button" class="admin-btn admin-btn--primary" data-apply-template-gallery="custom" data-template-id="<?= esc((string)($template['id'] ?? '')); ?>">Pakai di halaman ini</button>
                                                    <a class="admin-btn admin-btn--ghost" href="<?= esc(url('admin/landing-pages?builder=new&custom_template=' . rawurlencode((string)($template['id'] ?? '')))); ?>">Buat LP baru</a>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="lpw-template-gallery-empty">
                                        <strong>Belum ada Template Saya</strong>
                                        <p>Susun LP, lalu pilih <b>Publish ▼ → Simpan sebagai Template</b>. Template akan muncul di sini dan di halaman daftar LP.</p>
                                    </div>
                                <?php endif; ?>
                            </section>
                        </div>
                        <div class="lpw-template-dialog-actions">
                            <button type="button" class="admin-btn admin-btn--ghost" data-close-template-gallery>Tutup</button>
                        </div>
                    </dialog>

                    <dialog class="lpw-publish-guard-dialog" data-lpw-publish-guard-dialog>
                        <div class="lpw-publish-guard-head">
                            <div>
                                <span class="admin-badge">Publish Guard</span>
                                <h2>Cek kesiapan sebelum publish</h2>
                                <p>LP tetap bisa dipublish, tapi sistem akan memberi warning agar halaman tidak live dalam kondisi terlalu mentah.</p>
                            </div>
                            <button type="button" class="lpw-dialog-close" data-lpw-guard-close aria-label="Tutup">×</button>
                        </div>
                        <div id="lpPublishGuardContent" class="lpw-publish-guard-content" aria-live="polite"></div>
                        <div class="lpw-publish-guard-actions">
                            <button type="button" class="admin-btn admin-btn--ghost" data-lpw-guard-close>Perbaiki dulu</button>
                            <button type="button" class="admin-btn admin-btn--soft" data-lpw-guard-save-draft>Simpan Draft</button>
                            <button type="button" class="admin-btn admin-btn--primary" data-lpw-guard-continue>Publish tetap</button>
                        </div>
                    </dialog>
                </form>
            </aside>

            <section class="lpw-stage" aria-label="Live preview landing page">
                <span id="lpPreviewStatus" class="lpw-preview-status lpw-preview-status--hidden" hidden>Preview siap</span>
                <div class="lpw-preview-viewport">
                    <div id="lpLivePreview" class="lpw-preview-canvas lpw-preview-canvas--desktop" aria-live="polite"></div>
                </div>
            </section>
        </div>
    </main>
<?php endif; ?>

<?php if ($loggedIn): ?>
<script>
(function(){
    const initialBlocks = <?= $blocksJson; ?>;
    const templates = {
        direct: <?= $templateDirect; ?>,
        lead: <?= $templateLeadMagnet; ?>,
        whatsapp: <?= $templateWhatsapp; ?>
    };
    const builtinTemplates = <?= json_encode($builtinTemplatePayload, $lpJsonFlags) ?: '{}'; ?>;
    const customTemplates = <?= json_encode($customTemplatePayload, $lpJsonFlags) ?: '{}'; ?>;
    const presetSections = <?= json_encode($presetPayload, $lpJsonFlags) ?: '{}'; ?>;
    const presetPacks = <?= json_encode($presetPackPayload, $lpJsonFlags) ?: '{}'; ?>;
    const aiAssistantSeed = <?= json_encode($assistantSeed, $lpJsonFlags) ?: '{}'; ?>;
    const activeCustomForms = <?= json_encode($activeCustomForms, $lpJsonFlags) ?: '[]'; ?>;
    const requestedTemplate = <?= json_encode($requestedTemplate, $lpJsonFlags); ?>;
    const isNewLandingPage = <?= empty($editing['id']) ? 'true' : 'false'; ?>;
    const editor = document.getElementById('lpBlocksEditor');
    const hidden = document.getElementById('lpBlocksJson');
    const form = document.getElementById('landingBuilderForm');
    const preview = document.getElementById('lpLivePreview');
    const healthPanel = document.getElementById('lpBuilderHealth');
    const performancePanel = document.getElementById('lpBuilderPerformance');
    const performanceOptimizerPanel = document.getElementById('lpPerformanceOptimizer');
    const buyerViewPanel = document.getElementById('lpBuyerViewAdvisor');
    const publishChecklistPanel = document.getElementById('lpPublishChecklist');
    const publishGuardDialog = document.querySelector('[data-lpw-publish-guard-dialog]');
    const publishGuardContent = document.getElementById('lpPublishGuardContent');
    const seoSistemPanel = document.getElementById('lpBuilderSeoSistem');
    const trackingInspector = document.getElementById('lpTrackingInspector');
    const aiAssistantOutput = document.getElementById('lpAiAssistantOutput');
    const previewStatus = document.getElementById('lpPreviewStatus');
    const autosaveStatus = document.getElementById('lpAutosaveStatus');
    const undoButton = document.querySelector('[data-lpw-undo]');
    const redoButton = document.querySelector('[data-lpw-redo]');
    const pageIdentity = <?= json_encode((string)($editing['id'] ?? '') !== '' ? (string)$editing['id'] : ('new-' . (($editing['slug'] ?? '') ?: ($builtinTemplateId ?: ($requestedTemplate ?: 'blank')))), $lpJsonFlags); ?>;
    const pageSavedAt = <?= json_encode((string)($editing['updated_at'] ?? ''), $lpJsonFlags); ?>;
    const autosaveKey = 'lp-builder-draft:template:' + pageIdentity;
    let blocks = Array.isArray(initialBlocks) ? JSON.parse(JSON.stringify(initialBlocks)) : [];
    let hasUnsavedChanges = false;
    let isApplyingState = false;
    let historyStack = [];
    let historyIndex = -1;
    let historyTimer = null;
    let autosaveTimer = null;
    let previewCountdownTimer = null;
    let previewDeviceChecks = {desktop:true, tablet:false, mobile:false};
    let pendingPublishButton = null;
    let publishGuardBypass = false;
    let selectedBlockIndex = 0;

    const typeLabels = {
        hero_offer: 'Hero Penawaran', pain_points: 'Pain Point', benefits: 'Benefit', product_highlight: 'Highlight Produk',
        pricing_cards: 'Paket Harga', countdown_timer: 'Countdown Timer', testimonial: 'Testimoni', faq: 'FAQ', lead_form: 'Form Custom', custom_menu: 'Menu Custom', media: 'Media', free_cards: 'Judul Bebas / Card', cta: 'Tombol', text: 'Teks Bebas', html_block: 'HTML Block'
    };

    const engineInternalLinks = [
        {title:'Katalog Produk Fisik', url:'<?= esc(url('katalog')); ?>', text:'Pilihan produk fisik dan produk terkait'},
        {title:'Paket Layanan', url:'<?= esc(url('layanan')); ?>', text:'Info layanan layanan dan paket masak'},
        {title:'Area Layanan', url:'<?= esc(url('kontak')); ?>', text:'Cek area layanan Jakarta, Bandung, Surabaya, dan Yogyakarta'},
        {title:'Artikel Edukasi', url:'<?= esc(url('artikel')); ?>', text:'Baca panduan produk, layanan, dan tips memilih produk/layanan'}
    ];

    function formValue(name){
        const el = Array.from(form ? form.elements : []).find(function(field){ return field && field.name === name; });
        if (!el) return '';
        if (el.type === 'checkbox') return el.checked ? '1' : '';
        return String(el.value || '');
    }
    function setFormValue(name, value){
        const el = Array.from(form ? form.elements : []).find(function(field){ return field && field.name === name; });
        if (!el || el.type === 'hidden' || el.type === 'password') return;
        if (el.type === 'checkbox') el.checked = value === '1' || value === true;
        else el.value = value || '';
    }

    function applyTemplateExample(seed){
        if (!seed || !Array.isArray(seed.blocks)) return;
        readAll();
        blocks = JSON.parse(JSON.stringify(seed.blocks || []));
        ['title','slug','tracking_label','meta_title','meta_description','meta_keywords','layout_mode','hide_header','hide_footer','hide_floating_wa','show_nav_only','mini_footer_brand','mini_footer_text','mini_footer_bg','mini_footer_text_color','mini_footer_brand_color','mini_footer_text_size','mini_footer_align'].forEach(function(name){
            if (Object.prototype.hasOwnProperty.call(seed, name)) setFormValue(name, seed[name]);
        });
        render();
        captureHistory(true);
        markDirty();
        setAutosaveStatus('Template diterapkan · belum disimpan', true);
    }
    function setFormValueIfEmpty(name, value){
        if (!value && value !== '0') return;
        if (!formValue(name)) setFormValue(name, value);
    }
    function closeTemplateGallery(){
        const dialog = document.querySelector('[data-template-gallery-dialog]');
        if (!dialog) return;
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
    }
    function applyTemplateFromGallery(source, id){
        const bank = source === 'custom' ? customTemplates : builtinTemplates;
        const seed = bank && bank[id] ? bank[id] : null;
        if (!seed || !Array.isArray(seed.blocks) || seed.blocks.length === 0) return;
        readAll();
        const label = seed.name || 'Template Landing Page';
        const confirmText = blocks.length > 0
            ? 'Pakai template "' + label + '"? Susunan blok LP saat ini akan diganti, tapi belum tersimpan sampai Anda klik Simpan/Publish.'
            : 'Pakai template "' + label + '" untuk mulai membuat landing page?';
        if (!confirm(confirmText)) return;
        blocks = JSON.parse(JSON.stringify(seed.blocks || [])).map(optimizeBlockDefaults);
        selectedBlockIndex = 0;
        ['layout_mode','hide_header','hide_footer','hide_floating_wa','show_nav_only','mini_footer_brand','mini_footer_text','mini_footer_bg','mini_footer_text_color','mini_footer_brand_color','mini_footer_text_size','mini_footer_align'].forEach(function(name){
            if (Object.prototype.hasOwnProperty.call(seed, name)) setFormValue(name, seed[name]);
        });
        ['title','slug','tracking_label','meta_title','meta_description','meta_keywords'].forEach(function(name){
            if (Object.prototype.hasOwnProperty.call(seed, name)) setFormValueIfEmpty(name, seed[name]);
        });
        render();
        captureHistory(true);
        markDirty();
        closeTemplateGallery();
        setAutosaveStatus('Template "' + label + '" diterapkan · belum disimpan', true);
    }
    function addPresetSection(key){
        const preset = presetSections[key];
        if (!preset || !preset.block) return;
        readAll();
        blocks.push(optimizeBlockDefaults(JSON.parse(JSON.stringify(preset.block))));
        selectedBlockIndex = blocks.length - 1;
        render();
        markDirty();
        setAutosaveStatus('Section preset ditambahkan · belum disimpan', true);
    }
    function fillEmptyPresetIdentity(pack){
        const mapping = {
            suggested_title: 'title',
            suggested_slug: 'slug',
            tracking_label: 'tracking_label',
            meta_title: 'meta_title',
            meta_description: 'meta_description',
            meta_keywords: 'meta_keywords'
        };
        Object.keys(mapping).forEach(function(source){
            const target = mapping[source];
            if (pack && pack[source] && !formValue(target)) setFormValue(target, pack[source]);
        });
    }
    function addPresetPack(key){
        const pack = presetPacks[key];
        if (!pack || !Array.isArray(pack.blocks) || pack.blocks.length === 0) return;
        readAll();
        const label = pack.label || 'Paket Smart LP';
        const shouldAdd = blocks.length === 0 || confirm('Tambahkan paket "' + label + '" berisi ' + pack.blocks.length + ' blok? Isi landing page yang sudah ada tidak akan dihapus.');
        if (!shouldAdd) return;
        const incoming = JSON.parse(JSON.stringify(pack.blocks)).map(optimizeBlockDefaults);
        const firstNewIndex = blocks.length;
        blocks = blocks.concat(incoming);
        selectedBlockIndex = firstNewIndex;
        fillEmptyPresetIdentity(pack);
        render();
        markDirty();
        setAutosaveStatus(label + ' ditambahkan · belum disimpan', true);
    }
    function pageFormState(){
        const state = {};
        if (!form) return state;
        Array.from(form.elements).forEach(function(el){
            if (!el.name || ['blocks_json','action','password'].includes(el.name) || el.name.indexOf('_token') >= 0) return;
            if (el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return;
            state[el.name] = el.type === 'checkbox' ? (el.checked ? '1' : '') : String(el.value || '');
        });
        return state;
    }
    function applyFormState(state){
        Object.keys(state || {}).forEach(function(name){ setFormValue(name, state[name]); });
    }
    function stateSnapshot(){
        return {blocks: JSON.parse(JSON.stringify(blocks || [])), form: pageFormState()};
    }
    function snapshotString(state){
        return JSON.stringify(state || stateSnapshot());
    }
    function updateHistoryButtons(){
        if (undoButton) undoButton.disabled = historyIndex <= 0;
        if (redoButton) redoButton.disabled = historyIndex < 0 || historyIndex >= historyStack.length - 1;
    }
    function captureHistory(force){
        const snap = stateSnapshot();
        const encoded = snapshotString(snap);
        if (!force && historyStack[historyIndex] && historyStack[historyIndex].encoded === encoded) { updateHistoryButtons(); return; }
        historyStack = historyStack.slice(0, Math.max(0, historyIndex + 1));
        historyStack.push({encoded: encoded, snapshot: snap, ts: Date.now()});
        if (historyStack.length > 30) historyStack.shift();
        historyIndex = historyStack.length - 1;
        updateHistoryButtons();
    }
    function scheduleHistoryCapture(){
        if (isApplyingState) return;
        window.clearTimeout(historyTimer);
        historyTimer = window.setTimeout(function(){ readAll(); captureHistory(false); }, 550);
    }
    function applyStateSnapshot(snap, asDirty){
        if (!snap || !Array.isArray(snap.blocks)) return;
        isApplyingState = true;
        blocks = JSON.parse(JSON.stringify(snap.blocks));
        applyFormState(snap.form || {});
        render();
        isApplyingState = false;
        hasUnsavedChanges = !!asDirty;
        syncHidden();
        renderPreview();
        renderSistemPanels();
        updatePreviewStatus();
        updateHistoryButtons();
    }
    function undoState(){
        if (historyIndex <= 0) return;
        historyIndex -= 1;
        applyStateSnapshot(historyStack[historyIndex].snapshot, true);
        setAutosaveStatus('Undo diterapkan · belum disimpan', true);
        scheduleAutosave();
    }
    function redoState(){
        if (historyIndex >= historyStack.length - 1) return;
        historyIndex += 1;
        applyStateSnapshot(historyStack[historyIndex].snapshot, true);
        setAutosaveStatus('Redo diterapkan · belum disimpan', true);
        scheduleAutosave();
    }
    function setAutosaveStatus(text, dirty){
        if (!autosaveStatus) return;
        autosaveStatus.textContent = text;
        autosaveStatus.classList.toggle('is-dirty', !!dirty);
    }
    function saveLocalDraft(){
        if (isApplyingState) return;
        readAll(); syncHidden();
        const payload = {version:'umkm-template', saved_at:Date.now(), page_saved_at:pageSavedAt, snapshot:stateSnapshot()};
        try {
            window.localStorage.setItem(autosaveKey, JSON.stringify(payload));
            const stamp = new Date(payload.saved_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            setAutosaveStatus('Autosave lokal · ' + stamp, true);
        } catch (err) {
            setAutosaveStatus('Autosave gagal: storage penuh/ditolak', true);
        }
    }
    function scheduleAutosave(){
        if (isApplyingState) return;
        window.clearTimeout(autosaveTimer);
        autosaveTimer = window.setTimeout(saveLocalDraft, 900);
    }
    function clearLocalDraft(){
        try { window.localStorage.removeItem(autosaveKey); } catch(e) {}
        setAutosaveStatus('Tersimpan · autosave bersih', false);
    }
    function restoreLocalDraftIfAvailable(){
        let raw = '';
        try { raw = window.localStorage.getItem(autosaveKey) || ''; } catch(e) { raw = ''; }
        if (!raw) return;
        let payload = null;
        try { payload = JSON.parse(raw); } catch(e) { payload = null; }
        if (!payload || !payload.snapshot || !Array.isArray(payload.snapshot.blocks)) return;
        const stamp = payload.saved_at ? new Date(payload.saved_at).toLocaleString() : 'sebelumnya';
        const ok = window.confirm('Ada autosave lokal dari ' + stamp + '. Restore draft ini agar kerjaan tidak hilang? Pilih Cancel untuk pakai data dari server.');
        if (ok) {
            applyStateSnapshot(payload.snapshot, true);
            captureHistory(true);
            setAutosaveStatus('Draft autosave direstore · belum disimpan', true);
        } else {
            clearLocalDraft();
        }
    }
    if (undoButton) undoButton.addEventListener('click', undoState);
    if (redoButton) redoButton.addEventListener('click', redoState);
    window.addEventListener('beforeunload', function(e){
        if (!hasUnsavedChanges) return;
        saveLocalDraft();
        e.preventDefault();
        e.returnValue = '';
    });

    function escapeHtml(value){
        return String(value || '').replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c];});
    }
    function sanitizePreviewHtml(value){
        let html = String(value || '');
        html = html.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
        html = html.replace(/<iframe[\s\S]*?>[\s\S]*?<\/iframe>/gi, '');
        html = html.replace(/<object[\s\S]*?>[\s\S]*?<\/object>/gi, '');
        html = html.replace(/<embed[\s\S]*?>/gi, '');
        html = html.replace(/\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');
        html = html.replace(/\s+(href|src)\s*=\s*("|')?\s*javascript:[^"'\s>]*/gi, ' $1="#"');
        html = html.replace(/javascript\s*:/gi, '');
        return html;
    }
    function fullHtmlModeActive(){ return formValue('full_html_mode') === '1'; }
    function rawHtmlDocumentValue(){ return formValue('raw_html_document'); }
    function slugify(value){return String(value||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,90);}
    function itemsToLines(items, mode){
        if (!Array.isArray(items)) return '';
        return items.map(function(item){
            if (typeof item === 'string') return item;
            if (!item || typeof item !== 'object') return '';
            if (mode === 'pricing') return [item.title||'', item.price||'', Array.isArray(item.features)?item.features.map(function(f){return typeof f === 'string' ? f : (f.title || f.text || '');}).filter(Boolean).join('; '):'', item.button_text||''].join(' | ');
            if (mode === 'faq') return [item.question||item.title||'', item.answer||item.text||''].join(' | ');
            if (mode === 'menu') return [item.item_type||'link', item.title||'', item.url||item.link_url||'', item.image||'', item.text||item.answer||''].join(' | ');
            return [item.title||'', item.text||item.answer||'', item.image||'', item.url||item.link_url||''].filter(function(v, idx){return idx < 2 || String(v||'').trim() !== '';}).join(' | ');
        }).filter(Boolean).join('\n');
    }
    function linesToItems(value, mode){
        return String(value||'').split(/\r?\n/).map(v=>v.trim()).filter(Boolean).map(function(line){
            const parts = line.split('|').map(v=>v.trim());
            if (mode === 'simple') return line;
            if (mode === 'pricing') return {title:parts[0]||'Paket', price:parts[1]||'Konsultasi', features:(parts[2]||'').split(';').map(v=>v.trim()).filter(Boolean), button_text:parts[3]||'Konsultasi'};
            if (mode === 'faq') return {question:parts[0]||'Pertanyaan', answer:parts.slice(1).join(' | ')||''};
            if (mode === 'menu') { const itemType = (parts[0] === 'logo') ? 'logo' : 'link'; return {item_type:itemType, title:itemType === 'logo' ? (parts[1]||'') : (parts[1]||'Menu'), url:parts[2]||'#', link_url:parts[2]||'#', image:parts[3]||'', image_alt:parts[1]||'Logo menu', text:parts.slice(4).join(' | ')||'', logo_position:''}; }
            return {title:parts[0]||'', text:parts[1]||'', image:parts[2]||'', image_alt:parts[0]||'', url:parts[3]||'', link_url:parts[3]||''};
        });
    }
    function fieldsToLines(fields){
        if (!Array.isArray(fields)) return '';
        return fields.map(function(f){ return [f.name||'', f.label||'', f.type||'text', f.required?'wajib':'opsional', Array.isArray(f.options)?f.options.join(', '):''].join(' | '); }).join('\n');
    }
    function linesToFields(value){
        const allowed = ['text','tel','email','number','date','select','radio','checkbox','textarea'];
        return String(value||'').split(/\r?\n/).map(v=>v.trim()).filter(Boolean).map(function(line, index){
            const parts = line.split('|').map(v=>v.trim());
            const name = slugify(parts[0] || 'field_' + (index+1)).replace(/-/g,'_') || 'field_' + (index+1);
            const type = allowed.includes((parts[2]||'text').toLowerCase()) ? (parts[2]||'text').toLowerCase() : 'text';
            return {name:name, label:parts[1] || name.replace(/_/g,' '), type:type, required:/^(wajib|required|ya|yes|1)$/i.test(parts[3]||''), options:(parts[4]||'').split(',').map(v=>v.trim()).filter(Boolean), placeholder:''};
        });
    }
    function optimizeBlockDefaults(block){
        block = block && typeof block === 'object' ? block : {type:'text'};
        const map = {
            hero_offer:{block_goal:'awareness', cta_role:'primary', section_effect:'gradient-glow', animation_style:'fade-up'},
            pain_points:{block_goal:'awareness', animation_style:'fade-up'},
            benefits:{block_goal:'trust', section_effect:'soft-card', animation_style:'fade-up'},
            product_highlight:{block_goal:'trust', section_effect:'soft-card', animation_style:'fade-up'},
            testimonial:{block_goal:'trust', section_effect:'soft-card', animation_style:'fade-up'},
            pricing_cards:{block_goal:'offer', cta_role:'pricing', section_effect:'soft-card', animation_style:'zoom-soft'},
            countdown_timer:{block_goal:'offer', cta_role:'primary', section_effect:'gradient-glow', animation_style:'zoom-soft'},
            faq:{block_goal:'trust', animation_style:'fade'},
            lead_form:{block_goal:'lead', cta_role:'form', section_effect:'soft-card', animation_style:'fade-up'},
            custom_menu:{block_goal:'trust', animation_style:'fade'},
            media:{block_goal:'trust', animation_style:'slide-left'},
            free_cards:{block_goal:'trust', animation_style:'fade-up'},
            cta:{block_goal:'closing', cta_role:'closing', section_effect:'gradient-glow', animation_style:'zoom-soft'},
            text:{block_goal:'trust', animation_style:'fade'},
            html_block:{block_goal:'trust', section_effect:'soft-card', animation_style:'fade'}
        };
        return Object.assign({}, map[block.type || 'text'] || map.text, block);
    }
    function defaultBlock(type){
        if (type === 'hero_offer') return optimizeBlockDefaults({type:type, eyebrow:'', headline:'', subheadline:'', image:'<?= esc(asset('images/placeholder-product.svg')); ?>', image_alt:'Gambar penawaran', primary_text:'Konsultasi WhatsApp', primary_url:'#form-konsultasi', secondary_text:'Lihat Detail', secondary_url:'#detail', hero_layout:'auto', hero_position:'right', bg_color:'#eff6ff', headline_size:'44px'});
        if (type === 'pain_points') return optimizeBlockDefaults({type:type, headline:'', items:['Bingung memilih produk yang tepat','Butuh respon admin yang cepat','Ingin proses lebih praktis']});
        if (type === 'benefits') return optimizeBlockDefaults({type:type, headline:'', items:[{title:'Praktis', text:'Admin membantu dari awal sampai selesai.'},{title:'Jelas', text:'Informasi produk dan follow-up lebih rapi.'},{title:'Siap iklan', text:'Tombol dan form terhubung tracking.'}]});
        if (type === 'pricing_cards') return optimizeBlockDefaults({type:type, headline:'', button_url:'#form-konsultasi', items:[{title:'Paket Hemat', price:'Konsultasi', features:['Cocok untuk mulai tanya','Admin bantu arahan'], button_text:'Tanya Paket'}]});
        if (type === 'countdown_timer') { const d = new Date(Date.now() + 3*24*60*60*1000); const pad = n => String(n).padStart(2,'0'); const deadline = d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); return optimizeBlockDefaults({type:type, headline:'', text:'', countdown_deadline:deadline, countdown_timezone:'WIB', expired_text:'Promo sudah berakhir. Silakan hubungi admin untuk promo terbaru.', countdown_note:'Cocok untuk promo, event, bonus terbatas, atau campaign iklan.', button_text:'Ambil Promo Sekarang', button_url:'#form-konsultasi', bg_color:'#fff7ed', accent_color:'#f59e0b', align:'center'}); }
        if (type === 'custom_menu') return optimizeBlockDefaults({type:type, menu_style:'header', menu_position:'sticky', menu_align:'right', logo_align:'left', headline:'', text:'', bg_color:'#ffffff', card_bg:'#ffffff', card_title_color:'#1d4ed8', items:[{item_type:'logo', title:'', url:'#', link_url:'#', text:'', image:'/assets/images/logo.png', image_alt:'Logo brand', logo_position:'left'},{item_type:'link', title:'Lihat Paket', url:'#paket', link_url:'#paket', text:'Ke bagian pilihan paket', image:'', image_alt:'Logo Lihat Paket'},{item_type:'link', title:'Isi Form', url:'#form-konsultasi', link_url:'#form-konsultasi', text:'Ke form konsultasi', image:'', image_alt:'Logo Isi Form'}]});
        if (type === 'media') return optimizeBlockDefaults({type:type, headline:'', text:'', image:'<?= esc(asset('images/placeholder-product.svg')); ?>', image_alt:'Media landing page', button_text:'Konsultasi', button_url:'#form-konsultasi', media_layout:'auto', media_position:'left'});
        if (type === 'free_cards') return optimizeBlockDefaults({type:type, headline:'', text:'', items:[{title:'Card dengan gambar', text:'Isi deskripsi card pertama.', image:'<?= esc(asset('images/placeholder-product.svg')); ?>', image_alt:'Contoh card', url:'#form-konsultasi'}]});
        if (type === 'lead_form') return optimizeBlockDefaults({type:type, form_source:'lp_builtin', custom_form_slug:'', headline:'', text:'', submit_text:'Kirim Form', success_text:'Terima kasih, data sudah masuk.', need_default:'Konsultasi Landing Page', mailketing_list_id:'', form_name:'Form Konsultasi Landing Page', lead_segment:'lp-consultation', lead_tags:'landing-page,consultation', lead_priority:'warm', lead_stage:'new-lead', lead_score:'50', consent_text:'Saya bersedia dihubungi admin.', fields:[{name:'name', label:'Nama Lengkap', type:'text', required:true},{name:'phone', label:'Nomor WhatsApp', type:'tel', required:true},{name:'email', label:'Email', type:'email', required:false},{name:'need', label:'Kebutuhan', type:'select', required:true, options:['Konsultasi','Tanya Harga','Order']},{name:'budget_range', label:'Range Budget', type:'radio', required:false, options:['Ekonomis','Medium','Premium']},{name:'followup_channel', label:'Channel Follow-up', type:'checkbox', required:false, options:['WhatsApp','Email']},{name:'message', label:'Catatan', type:'textarea', required:false}]});
        if (type === 'faq') return optimizeBlockDefaults({type:type, headline:'', items:[{question:'Apakah bisa konsultasi dulu?', answer:'Bisa, admin akan membantu menjelaskan kebutuhan Anda.'}]});
        if (type === 'testimonial') return optimizeBlockDefaults({type:type, headline:'', items:[{title:'Customer', text:'Pelayanan cepat dan jelas.'}]});
        if (type === 'cta') return optimizeBlockDefaults({type:type, headline:'', text:'', button_text:'Chat Admin', button_url:'#form-konsultasi', bg_color:'#1e3a8a', text_color:'#ffffff', align:'center'});
        if (type === 'html_block') return optimizeBlockDefaults({type:type, headline:'', text:'', html:'<section><h2>Judul HTML Section</h2><p>Isi HTML aman tanpa script.</p></section>', builder_source:'manual-html', complex_widgets:'', bg_color:'#ffffff'});
        return optimizeBlockDefaults({type:'text', headline:'', text:''});
    }
    function field(label, key, value, type='input', placeholder=''){
        if (type === 'textarea') return `<label>${label}<textarea data-key="${key}" rows="3" placeholder="${escapeHtml(placeholder)}">${escapeHtml(value)}</textarea></label>`;
        if (type === 'color') return `<label>${label}<input type="color" data-key="${key}" value="${escapeHtml(value || '#ffffff')}"></label>`;
        return `<label>${label}<input data-key="${key}" value="${escapeHtml(value)}" placeholder="${escapeHtml(placeholder)}"></label>`;
    }
    function selectField(label, key, value, options){
        return `<label>${label}<select data-key="${key}">${options.map(function(opt){const val=Array.isArray(opt)?opt[0]:opt; const text=Array.isArray(opt)?opt[1]:opt; return `<option value="${escapeHtml(val)}" ${String(value||'')===String(val)?'selected':''}>${escapeHtml(text)}</option>`;}).join('')}</select></label>`;
    }
    function guideBox(title, body){
        return `<div class="lpw-helper-guide"><strong>${escapeHtml(title)}</strong><p>${body}</p></div>`;
    }
    function normalizeCardItem(item){
        if (typeof item === 'string') return {title:item, text:'', image:'', image_alt:item, url:'', link_url:'', price:'', features:[], button_text:''};
        item = item && typeof item === 'object' ? item : {};
        return {
            title: item.title || item.question || '',
            question: item.question || item.title || '',
            text: item.text || item.answer || '',
            answer: item.answer || item.text || '',
            image: item.image || '',
            image_alt: item.image_alt || item.title || item.question || '',
            url: item.url || item.link_url || item.button_url || '',
            link_url: item.link_url || item.url || item.button_url || '',
            price: item.price || '',
            features: Array.isArray(item.features) ? item.features.map(function(f){ return typeof f === 'string' ? f : (f.title || f.text || ''); }).filter(Boolean).join('\n') : (item.features || ''),
            button_text: item.button_text || '',
            item_type: item.item_type || 'link',
            logo_position: item.logo_position || '',
            item_bg: item.item_bg || '',
            item_title_color: item.item_title_color || '',
            item_text_color: item.item_text_color || '',
            item_button_bg: item.item_button_bg || '',
            item_button_text_color: item.item_button_text_color || '',
            item_title_size: item.item_title_size || '',
            item_text_size: item.item_text_size || '',
            item_radius: item.item_radius || '',
            item_align: item.item_align || '',
            item_shadow: item.item_shadow || ''
        };
    }
    function repeatEmptyState(mode){
        const label = mode === 'fields' ? 'Belum ada field. Klik tombol Tambah Field untuk membuat field baru.' : (mode === 'menu' ? 'Belum ada menu. Klik Tambah Menu untuk membuat menu baru.' : 'Belum ada item. Klik tombol tambah untuk membuat item baru.');
        return `<div class="lpw-repeat-empty">${escapeHtml(label)}</div>`;
    }
    function repeatAddLabel(mode){
        if (mode === 'menu') return '+ Tambah Menu';
        if (mode === 'simple') return '+ Tambah Poin';
        if (mode === 'pricing') return '+ Tambah Paket';
        if (mode === 'faq') return '+ Tambah FAQ';
        return '+ Tambah Card';
    }
    function repeatTitle(mode){
        if (mode === 'menu') return 'Item Menu';
        if (mode === 'simple') return 'Daftar Poin';
        if (mode === 'pricing') return 'Daftar Paket';
        if (mode === 'faq') return 'Daftar FAQ';
        return 'Daftar Card';
    }
    function repeatHint(mode){
        if (mode === 'menu') return 'Isi menu satu-satu. Anchor boleh #paket atau #form-konsultasi. Logo menu opsional, bisa pakai URL gambar/icon dari Media & Asset SEO.';
        if (mode === 'simple') return 'Klik Tambah Poin, lalu isi satu poin per kartu. Tidak perlu format manual lagi.';
        if (mode === 'pricing') return 'Klik Tambah Paket, lalu isi nama paket, harga, fitur, dan teks tombol satu-satu.';
        if (mode === 'faq') return 'Klik Tambah FAQ, lalu isi pertanyaan dan jawaban satu-satu.';
        return 'Isi card satu-satu. Card boleh 1, 2, 3, atau lebih. Gambar per card opsional dan layout otomatis menyesuaikan jumlah card.';
    }
    function repeatItemPreviewTitle(item, mode, index){
        item = normalizeCardItem(item);
        const fallback = (mode === 'menu' ? 'Menu' : (mode === 'simple' ? 'Poin' : (mode === 'pricing' ? 'Paket' : (mode === 'faq' ? 'FAQ' : 'Card')))) + ' #' + (index + 1);
        const source = mode === 'faq'
            ? (item.question || item.title || item.text || item.answer)
            : (mode === 'pricing' ? (item.title || item.price || item.button_text) : (item.title || item.text || item.url || item.image));
        const text = String(source || fallback).replace(/\s+/g, ' ').trim();
        return text.length > 54 ? text.slice(0, 51) + '...' : text;
    }
    function cardEditor(items, mode){
        mode = mode || 'cards';
        const rows = Array.isArray(items) ? items.map(normalizeCardItem) : [];
        const countLabel = rows.length ? rows.length + ' item' : 'Belum ada item';
        return `<div class="lpw-helper-guide"><strong>${mode==='menu'?'Editor menu custom':(mode==='simple'?'Editor poin visual':(mode==='pricing'?'Editor paket harga visual':(mode==='faq'?'Editor FAQ visual':'Editor card visual')))}</strong><p>${repeatHint(mode)}</p></div><div class="lpw-repeat-box lpw-repeat-box--v3313" data-repeat="cards" data-repeat-mode="${escapeHtml(mode)}"><div class="lpw-repeat-head lpw-repeat-head--v3313"><div><strong>${repeatTitle(mode)}</strong><small>${escapeHtml(countLabel)} · klik item untuk buka/edit detail. Sidebar tetap ringkas walau card banyak.</small></div><div class="lpw-repeat-tools"><button type="button" class="lpw-repeat-mini-btn" data-repeat-expand> Buka semua</button><button type="button" class="lpw-repeat-mini-btn" data-repeat-collapse> Tutup semua</button><button type="button" class="lpw-repeat-add-btn" data-repeat-add="${escapeHtml(mode)}">${repeatAddLabel(mode)}</button></div></div><div class="lpw-repeat-list lpw-repeat-list--compact">${rows.length ? rows.map(function(item, index){ return cardRow(item, index, mode, rows.length); }).join('') : repeatEmptyState(mode)}</div></div>`;
    }
    function cardRow(item, index, mode, totalRows){
        item = normalizeCardItem(item);
        mode = mode || 'cards';
        const isMenu = mode === 'menu';
        const isSimple = mode === 'simple';
        const isPricing = mode === 'pricing';
        const isFaq = mode === 'faq';
        let fieldsHtml = '';
        if (isSimple) {
            fieldsHtml = `<label class="lpw-repeat-wide">Isi Poin<input data-item-field="text" value="${escapeHtml(item.text || item.title)}" placeholder="Contoh: Bingung pilih paket yang tepat"></label>`;
        } else if (isPricing) {
            fieldsHtml = `<label>Nama Paket<input data-item-field="title" value="${escapeHtml(item.title)}" placeholder="Paket Hemat"></label><label>Harga<input data-item-field="price" value="${escapeHtml(item.price)}" placeholder="Rp xxx / Konsultasi"></label><label class="lpw-repeat-wide">Fitur Paket<textarea data-item-field="features" rows="3" placeholder="Satu fitur per baris">${escapeHtml(item.features)}</textarea></label><label>Teks Tombol<input data-item-field="button_text" value="${escapeHtml(item.button_text)}" placeholder="Tanya Paket"></label>`;
        } else if (isFaq) {
            fieldsHtml = `<label class="lpw-repeat-wide">Pertanyaan<input data-item-field="question" value="${escapeHtml(item.question || item.title)}" placeholder="Apakah bisa konsultasi dulu?"></label><label class="lpw-repeat-wide">Jawaban<textarea data-item-field="answer" rows="3" placeholder="Tulis jawaban FAQ">${escapeHtml(item.answer || item.text)}</textarea></label>`;
        } else {
            const titleLabel = isMenu ? 'Label Menu' : 'Judul Card';
            const textLabel = isMenu ? 'Deskripsi Menu' : 'Deskripsi Card';
            const urlLabel = isMenu ? 'URL / Anchor' : 'Link Opsional';
            const mediaFields = isMenu
                ? `<label>Jenis Item Menu<select data-item-field="item_type"><option value="link" ${item.item_type!=='logo'?'selected':''}>Link menu biasa</option><option value="logo" ${item.item_type==='logo'?'selected':''}>Logo menu saja</option></select></label><label>Posisi Logo<select data-item-field="logo_position"><option value="" ${!item.logo_position?'selected':''}>Ikuti pengaturan blok</option><option value="left" ${item.logo_position==='left'?'selected':''}>Kiri</option><option value="center" ${item.logo_position==='center'?'selected':''}>Tengah</option><option value="right" ${item.logo_position==='right'?'selected':''}>Kanan</option></select></label><label>URL Logo<input data-item-field="image" value="${escapeHtml(item.image)}" placeholder="/assets/images/logo.png"></label><label>Alt Logo Menu<input data-item-field="image_alt" value="${escapeHtml(item.image_alt)}" placeholder="Logo ${escapeHtml(item.title || 'brand')}"></label>`
                : `<label>URL Gambar Opsional<input data-item-field="image" value="${escapeHtml(item.image)}" placeholder="/assets/images/contoh.webp"></label><label>Alt Text Gambar<input data-item-field="image_alt" value="${escapeHtml(item.image_alt)}" placeholder="Deskripsi gambar"></label>`;
            fieldsHtml = `<label>${titleLabel}<input data-item-field="title" value="${escapeHtml(item.title)}" placeholder="${isMenu?'Label menu / kosongkan jika logo saja':'Praktis'}"></label><label>${urlLabel}<input data-item-field="url" value="${escapeHtml(item.url || item.link_url)}" placeholder="${isMenu?'#paket / /katalog':'#form-konsultasi'}"></label>${mediaFields}<label class="lpw-repeat-wide">${textLabel}<textarea data-item-field="text" rows="2" placeholder="${isMenu?'Deskripsi menu, boleh kosong jika logo saja':'Tulis manfaat/card ini'}">${escapeHtml(item.text)}</textarea></label>`;
        }
        const rowTitle = isMenu ? 'Menu' : (isSimple ? 'Poin' : (isPricing ? 'Paket' : (isFaq ? 'FAQ' : 'Card')));
        const itemDesignHtml = `<details class="lpw-item-design-panel"><summary>Desain & typography ${rowTitle.toLowerCase()}</summary><div class="lpw-repeat-grid">
            <label>Background ${rowTitle}<input type="color" data-item-field="item_bg" value="${escapeHtml(item.item_bg || '#ffffff')}"></label>
            <label>Warna Judul<input type="color" data-item-field="item_title_color" value="${escapeHtml(item.item_title_color || '#0f172a')}"></label>
            <label>Warna Teks<input type="color" data-item-field="item_text_color" value="${escapeHtml(item.item_text_color || '#475569')}"></label>
            <label>Warna Tombol<input type="color" data-item-field="item_button_bg" value="${escapeHtml(item.item_button_bg || '#2563eb')}"></label>
            <label>Warna Teks Tombol<input type="color" data-item-field="item_button_text_color" value="${escapeHtml(item.item_button_text_color || '#ffffff')}"></label>
            <label>Ukuran Judul<select data-item-field="item_title_size"><option value="" ${!item.item_title_size?'selected':''}>Default</option><option value="16px" ${item.item_title_size==='16px'?'selected':''}>16px</option><option value="18px" ${item.item_title_size==='18px'?'selected':''}>18px</option><option value="20px" ${item.item_title_size==='20px'?'selected':''}>20px</option><option value="24px" ${item.item_title_size==='24px'?'selected':''}>24px</option></select></label>
            <label>Ukuran Teks<select data-item-field="item_text_size"><option value="" ${!item.item_text_size?'selected':''}>Default</option><option value="13px" ${item.item_text_size==='13px'?'selected':''}>13px</option><option value="14px" ${item.item_text_size==='14px'?'selected':''}>14px</option><option value="16px" ${item.item_text_size==='16px'?'selected':''}>16px</option><option value="18px" ${item.item_text_size==='18px'?'selected':''}>18px</option></select></label>
            <label>Radius Card<select data-item-field="item_radius"><option value="" ${!item.item_radius?'selected':''}>Default</option><option value="12px" ${item.item_radius==='12px'?'selected':''}>12px</option><option value="18px" ${item.item_radius==='18px'?'selected':''}>18px</option><option value="24px" ${item.item_radius==='24px'?'selected':''}>24px</option><option value="32px" ${item.item_radius==='32px'?'selected':''}>32px</option></select></label>
            <label>Rata Isi<select data-item-field="item_align"><option value="" ${!item.item_align?'selected':''}>Default</option><option value="left" ${item.item_align==='left'?'selected':''}>Kiri</option><option value="center" ${item.item_align==='center'?'selected':''}>Tengah</option><option value="right" ${item.item_align==='right'?'selected':''}>Kanan</option></select></label>
            <label>Shadow<select data-item-field="item_shadow"><option value="" ${!item.item_shadow?'selected':''}>Default</option><option value="none" ${item.item_shadow==='none'?'selected':''}>Tidak ada</option><option value="soft" ${item.item_shadow==='soft'?'selected':''}>Soft</option><option value="medium" ${item.item_shadow==='medium'?'selected':''}>Medium</option><option value="strong" ${item.item_shadow==='strong'?'selected':''}>Strong</option></select></label>
        </div></details>`;
        const previewTitle = repeatItemPreviewTitle(item, mode, index);
        const openAttr = index === 0 ? ' open' : '';
        return `<details class="lpw-repeat-card lpw-repeat-card--collapsible" data-repeat-card${openAttr}><summary><span class="lpw-repeat-summary-title"><b>${rowTitle} #${index+1}</b><em>${escapeHtml(previewTitle)}</em></span><span class="lpw-repeat-summary-actions"><button type="button" data-repeat-move="up" ${index===0?'disabled':''} title="Geser ke atas">↑</button><button type="button" data-repeat-move="down" ${(totalRows && totalRows-1===index)?'disabled':''} title="Geser ke bawah">↓</button><button type="button" data-repeat-duplicate title="Duplikat item">Duplikat</button><button type="button" data-repeat-remove>Hapus</button></span></summary><div class="lpw-repeat-grid">${fieldsHtml}</div>${itemDesignHtml}</details>`;
    }
    function repeatDefaultItem(mode, count){
        if (mode === 'menu') return {item_type:'link', title:'Menu Baru', url:'#', link_url:'#', text:'', image:'', image_alt:'Logo Menu Baru', logo_position:''};
        if (mode === 'simple') return 'Poin baru';
        if (mode === 'pricing') return {title:'Paket Baru', price:'Konsultasi', features:['Fitur pertama'], button_text:'Tanya Paket'};
        if (mode === 'faq') return {question:'Pertanyaan baru?', answer:'Tulis jawaban di sini.'};
        return {title:'Card Baru', text:'', image:'', image_alt:'', url:''};
    }
    function readRepeatItems(repeatBox){
        const mode = repeatBox.dataset.repeatMode || 'cards';
        return Array.from(repeatBox.querySelectorAll('[data-repeat-card]')).map(function(row){
            const raw = {};
            row.querySelectorAll('[data-item-field]').forEach(function(input){ raw[input.dataset.itemField] = input.value; });
            const designKeys = ['item_bg','item_title_color','item_text_color','item_button_bg','item_button_text_color','item_title_size','item_text_size','item_radius','item_align','item_shadow'];
            function designPayload(target){ designKeys.forEach(function(key){ if (raw[key]) target[key] = raw[key]; }); return target; }
            if (mode === 'simple') {
                const value = String(raw.text || raw.title || '').trim();
                const styled = designPayload({title:value, text:value});
                return designKeys.some(function(key){ return !!raw[key]; }) ? styled : value;
            }
            if (mode === 'pricing') {
                return designPayload({
                    title: raw.title || '',
                    price: raw.price || '',
                    features: String(raw.features || '').split(/[\n;,]+/).map(function(v){return v.trim();}).filter(Boolean),
                    button_text: raw.button_text || ''
                });
            }
            if (mode === 'faq') {
                return designPayload({question: raw.question || raw.title || '', title: raw.question || raw.title || '', answer: raw.answer || raw.text || '', text: raw.answer || raw.text || ''});
            }
            const item = Object.assign({}, raw);
            item.link_url = item.url || '';
            return item;
        }).filter(function(item){
            if (typeof item === 'string') return item.trim() !== '';
            if (!item || typeof item !== 'object') return false;
            return String(item.title||item.text||item.image||item.url||item.question||item.answer||item.price||item.button_text||'').trim() !== '' || (Array.isArray(item.features) && item.features.length > 0);
        });
    }
    function sanitizeFieldName(value, index){
        const fallback = 'field_' + (Number(index || 0) + 1);
        let raw = String(value || '').trim().toLowerCase().replace(/_/g, '-');
        let cleaned = slugify(raw).replace(/-/g, '_').replace(/[^a-z0-9_]/g, '').replace(/^_+|_+$/g, '');
        if (!cleaned || /^[0-9]/.test(cleaned)) cleaned = fallback;
        return cleaned;
    }
    function normalizeStoredFormFields(fields){
        const allowed = ['text','tel','email','number','date','select','radio','checkbox','textarea'];
        const seen = {};
        return (Array.isArray(fields) ? fields : []).map(function(field, index){
            field = field && typeof field === 'object' ? field : {};
            let name = sanitizeFieldName(field.name || field.label || '', index);
            if (seen[name] !== undefined) {
                seen[name] += 1;
                name = name + '_' + seen[name];
            } else {
                seen[name] = 1;
            }
            let type = String(field.type || 'text').toLowerCase();
            if (!allowed.includes(type)) type = 'text';
            const options = Array.isArray(field.options) ? field.options : String(field.options || '').split(',').map(function(v){return v.trim();}).filter(Boolean);
            return {
                name: name,
                label: field.label || name.replace(/_/g, ' ').replace(/\w/g, function(c){ return c.toUpperCase(); }),
                type: type,
                required: !!field.required,
                placeholder: field.placeholder || '',
                options: options
            };
        }).filter(function(field){ return String(field.name || field.label || '').trim() !== ''; });
    }
    function normalizeFormField(field, index){
        const stored = normalizeStoredFormFields([field])[0] || {name:'field_' + (index + 1), label:'Field ' + (index + 1), type:'text', required:false, placeholder:'', options:[]};
        return Object.assign({}, stored, {options: Array.isArray(stored.options) ? stored.options.join(', ') : String(stored.options || '')});
    }
    function formFieldEditor(fields){
        const sumber = Array.isArray(fields) ? fields : defaultBlock('lead_form').fields;
        const rows = normalizeStoredFormFields(sumber).map(normalizeFormField);
        return `<div class="lpw-helper-guide"><strong>Editor field form visual</strong><p><b>Nama Field</b> adalah nama teknis untuk database/tracking. Sistem otomatis merapikan jadi huruf kecil, angka, dan underscore. Admin boleh mengetik bebas, contoh <b>Nomor WA</b> akan jadi <b>nomor_wa</b>. <b>Label Tampil</b> adalah teks yang terlihat oleh pengunjung.</p></div><div class="lpw-repeat-box" data-repeat="fields"><div class="lpw-repeat-head"><strong>Field Form</strong><button type="button" class="lpw-repeat-add-btn" data-field-add>+ Tambah Field</button></div><div class="lpw-repeat-list">${rows.length ? rows.map(function(field, index){ return formFieldRow(field, index, rows.length); }).join('') : repeatEmptyState('fields')}</div></div>`;
    }
    function formFieldRow(field, index, totalRows){
        field = normalizeFormField(field, index);
        const typeOptions = ['text','tel','email','number','date','select','radio','checkbox','textarea'].map(function(type){ return `<option value="${type}" ${field.type===type?'selected':''}>${type}</option>`; }).join('');
        return `<article class="lpw-repeat-card lpw-repeat-card--field" data-form-field><header><strong>Field #${index+1}</strong><div><button type="button" data-field-move="up" ${index===0?'disabled':''}>↑</button><button type="button" data-field-move="down" ${(totalRows && index===totalRows-1)?'disabled':''}>↓</button><button type="button" data-field-remove>Hapus</button></div></header><div class="lpw-repeat-grid"><label class="lpw-repeat-wide lpw-field-name-wrap">Nama Field <small>Nama teknis. Otomatis dibuat aman: huruf kecil, angka, underscore. Contoh: nomor_wa.</small><input data-field-prop="name" value="${escapeHtml(field.name)}" placeholder="name / phone / budget_range"></label><label class="lpw-repeat-wide">Label Tampil <small>Teks yang dilihat pengunjung di form.</small><input data-field-prop="label" value="${escapeHtml(field.label)}" placeholder="Nama Lengkap"></label><label>Tipe Field<select data-field-prop="type">${typeOptions}</select></label><label>Status<select data-field-prop="required"><option value="1" ${field.required?'selected':''}>Wajib</option><option value="0" ${!field.required?'selected':''}>Opsional</option></select></label><label class="lpw-repeat-wide">Placeholder <small>Teks bantuan di dalam input. Boleh dikosongkan.</small><input data-field-prop="placeholder" value="${escapeHtml(field.placeholder)}" placeholder="Teks bantuan di input"></label><label class="lpw-repeat-wide">Opsi select / radio / checkbox <small>Khusus field pilihan. Pisahkan dengan koma, contoh: Ekonomis, Medium, Premium.</small><input data-field-prop="options" value="${escapeHtml(field.options)}" placeholder="Ekonomis, Medium, Premium"></label></div></article>`;
    }
    function designFields(block){
        return `<details class="lpw-design-panel lpw-design-panel--v3311" open><summary>Desain & typography lengkap blok</summary>
            <div class="lpw-design-tabs-note"><strong>Kontrol per bagian</strong><p>Atur section, judul, deskripsi/teks, tombol, dan card tanpa mengganggu SEO/tracking U-Growth.</p></div>
            <div class="lpw-design-grid">
                <label>Background section<input type="color" data-key="bg_color" value="${escapeHtml(block.bg_color || '#ffffff')}"></label>
                <label>Warna teks umum<input type="color" data-key="text_color" value="${escapeHtml(block.text_color || '#0f172a')}"></label>
                <label>Warna aksen<input type="color" data-key="accent_color" value="${escapeHtml(block.accent_color || '#2563eb')}"></label>
                ${selectField('Rata teks umum', 'align', block.align || '', [['','Default'],['left','Kiri'],['center','Tengah'],['right','Kanan']])}
                ${selectField('Efek section', 'section_effect', block.section_effect || 'none', [['none','Tanpa efek'],['soft-card','Soft card'],['gradient-glow','Gradient glow'],['spotlight','Spotlight'],['top-wave','Wave atas'],['bottom-wave','Wave bawah'],['divider-line','Divider line']])}
                ${selectField('Animasi masuk', 'animation_style', block.animation_style || 'inherit', [['inherit','Ikuti animasi halaman'],['none','Matikan animasi blok'],['fade-up','Fade up'],['zoom-soft','Zoom soft'],['fade','Fade'],['slide-left','Slide kiri'],['slide-right','Slide kanan']])}
            </div>
            <div class="lpw-design-subgroup"><h4>Judul section</h4><div class="lpw-design-grid">
                <label>Warna judul<input type="color" data-key="title_color" value="${escapeHtml(block.title_color || block.text_color || '#0f172a')}"></label>
                ${selectField('Ukuran judul', 'title_size', block.title_size || block.headline_size || '', [['','Default'],['28px','28px'],['34px','34px'],['40px','40px'],['46px','46px'],['56px','56px'],['64px','64px']])}
                ${selectField('Rata judul', 'title_align', block.title_align || block.align || '', [['','Default'],['left','Kiri'],['center','Tengah'],['right','Kanan']])}
                ${selectField('Tebal judul', 'title_weight', block.title_weight || block.font_weight || '', [['','Default'],['normal','Normal'],['600','Semi bold'],['700','Bold'],['800','Extra bold'],['900','Black']])}
                ${selectField('Italic judul', 'title_style', block.title_style || block.font_style || '', [['','Default'],['normal','Normal'],['italic','Italic']])}
                ${selectField('Underline judul', 'title_decoration', block.title_decoration || block.text_decoration || '', [['','Default'],['none','Tidak'],['underline','Underline']])}
            </div></div>
            <div class="lpw-design-subgroup"><h4>Deskripsi / teks section</h4><div class="lpw-design-grid">
                <label>Warna deskripsi<input type="color" data-key="description_color" value="${escapeHtml(block.description_color || block.text_color || '#475569')}"></label>
                ${selectField('Ukuran deskripsi', 'description_size', block.description_size || block.text_size || '', [['','Default'],['14px','14px'],['16px','16px'],['18px','18px'],['20px','20px'],['22px','22px']])}
                ${selectField('Rata deskripsi', 'description_align', block.description_align || block.align || '', [['','Default'],['left','Kiri'],['center','Tengah'],['right','Kanan']])}
                ${selectField('Tebal deskripsi', 'description_weight', block.description_weight || '', [['','Default'],['normal','Normal'],['500','Medium'],['600','Semi bold'],['700','Bold']])}
                ${selectField('Italic deskripsi', 'description_style', block.description_style || block.font_style || '', [['','Default'],['normal','Normal'],['italic','Italic']])}
                ${selectField('Underline deskripsi', 'description_decoration', block.description_decoration || block.text_decoration || '', [['','Default'],['none','Tidak'],['underline','Underline']])}
            </div></div>
            <div class="lpw-design-subgroup"><h4>Tombol</h4><div class="lpw-design-grid">
                <label>Warna tombol<input type="color" data-key="button_bg" value="${escapeHtml(block.button_bg || block.accent_color || '#2563eb')}"></label>
                <label>Warna teks tombol<input type="color" data-key="button_text_color" value="${escapeHtml(block.button_text_color || '#ffffff')}"></label>
                ${selectField('Ukuran teks tombol', 'button_size', block.button_size || '', [['','Default'],['13px','13px'],['14px','14px'],['16px','16px'],['18px','18px'],['20px','20px']])}
                ${selectField('Radius tombol', 'button_radius', block.button_radius || '', [['','Default'],['8px','8px'],['14px','14px'],['999px','Pill penuh']])}
                ${selectField('Rata tombol', 'button_align', block.button_align || block.align || '', [['','Default'],['left','Kiri'],['center','Tengah'],['right','Kanan']])}
            </div></div>
            <div class="lpw-design-subgroup"><h4>Card / item</h4><div class="lpw-design-grid">
                <label>Background card<input type="color" data-key="card_bg" value="${escapeHtml(block.card_bg || '#ffffff')}"></label>
                <label>Warna teks card<input type="color" data-key="card_text_color" value="${escapeHtml(block.card_text_color || '#475569')}"></label>
                <label>Warna judul card<input type="color" data-key="card_title_color" value="${escapeHtml(block.card_title_color || '#0f172a')}"></label>
                ${selectField('Ukuran judul card', 'card_title_size', block.card_title_size || '', [['','Default'],['16px','16px'],['18px','18px'],['20px','20px'],['24px','24px']])}
                ${selectField('Ukuran teks card', 'card_text_size', block.card_text_size || '', [['','Default'],['13px','13px'],['14px','14px'],['16px','16px'],['18px','18px']])}
                ${selectField('Radius card', 'card_radius', block.card_radius || '', [['','Default'],['12px','12px'],['18px','18px'],['24px','24px'],['32px','32px']])}
                ${selectField('Rata isi card', 'card_align', block.card_align || '', [['','Default'],['left','Kiri'],['center','Tengah'],['right','Kanan']])}
                ${selectField('Shadow card', 'card_shadow', block.card_shadow || '', [['','Default'],['none','Tidak ada'],['soft','Soft'],['medium','Medium'],['strong','Strong']])}
            </div></div>
            <div class="lpw-design-subgroup"><h4>Growth metadata</h4><div class="lpw-design-grid">
                ${selectField('Tujuan blok', 'block_goal', block.block_goal || '', [['','Belum ditandai'],['awareness','Awareness / pembuka'],['trust','Trust builder'],['offer','Offer / paket'],['lead','Lead capture'],['closing','Closing']])}
                ${selectField('Peran CTA', 'cta_role', block.cta_role || '', [['','Bukan CTA utama'],['primary','CTA utama'],['secondary','CTA pendukung'],['form','CTA form'],['pricing','CTA paket harga'],['closing','CTA closing']])}
                <label class="lpw-repeat-wide">Catatan optimasi <small>Opsional. Isi hipotesis/perbaikan yang ingin dites.</small><textarea data-key="optimization_note" rows="2" placeholder="Contoh: tes CTA lebih direct ke WhatsApp">${escapeHtml(block.optimization_note || '')}</textarea></label>
            </div></div>
        </details>`;
    }
    function customFormSelect(block) {
        const options = [['','Pilih form aktif dari Form Custom']].concat((Array.isArray(activeCustomForms) ? activeCustomForms : []).map(function(form){
            return [form.slug, form.title + ' (' + form.fields + ' field)'];
        }));
        let html = selectField('Form yang dipakai', 'custom_form_slug', block.custom_form_slug || '', options);
        if (!activeCustomForms.length) {
            html += guideBox('Belum ada Form Custom aktif', 'Buat dan aktifkan form di menu <b>Form Custom</b>, lalu kembali ke Landing Page Builder untuk memilihnya.');
        } else {
            const picked = activeCustomForms.find(function(form){ return form.slug === block.custom_form_slug; });
            html += guideBox('Form dari admin', picked ? ('Landing page ini akan menampilkan <b>' + escapeHtml(picked.title) + '</b>. Data masuk tetap tercatat di Data Masuk Form Custom.') : 'Pilih form yang sudah dibuat di menu Form Custom. Field form dikelola dari halaman Form Custom, bukan dari blok landing page ini.');
        }
        return html;
    }

    function blockFields(block){
        let html = '';
        if (block.type === 'hero_offer') {
            html += guideBox('Panduan hero fleksibel', 'Hero sekarang bisa 2 kolom atau 1 kolom. Pakai <b>Otomatis</b> agar gambar+teks jadi 2 kolom, gambar saja jadi full, dan teks saja jadi full. Untuk long copy, Hero sebaiknya tetap fokus ke headline, subheadline, dan Tombol utama.');
            html += selectField('Mode Tampilan Hero', 'hero_layout', block.hero_layout || 'auto', [['auto','Otomatis fleksibel'],['split','2 kolom: gambar + teks'],['media_only','Gambar saja full'],['text_only','Teks saja full']]);
            html += selectField('Posisi gambar saat 2 kolom', 'hero_position', block.hero_position || 'right', [['left','Gambar kiri, teks kanan'],['right','Teks kiri, gambar kanan']]);
            html += field('Eyebrow kecil', 'eyebrow', block.eyebrow || '', 'input', 'Promo Terbatas');
            html += field('Headline / H1', 'headline', block.headline || '', 'input', 'Judul penawaran utama');
            html += field('Subheadline', 'subheadline', block.subheadline || '', 'textarea', 'Penjelasan singkat penawaran');
            html += field('URL Gambar', 'image', block.image || '', 'input', 'https://...');
            html += field('Alt Text Gambar', 'image_alt', block.image_alt || '', 'input', 'Deskripsi gambar');
            html += field('Teks Tombol Utama', 'primary_text', block.primary_text || '', 'input', 'Konsultasi WhatsApp');
            html += field('URL Tombol Utama', 'primary_url', block.primary_url || '', 'input', '#form-konsultasi / https://wa.me/...');
            html += field('Teks Tombol Kedua', 'secondary_text', block.secondary_text || '', 'input', 'Lihat Paket');
            html += field('URL Tombol Kedua', 'secondary_url', block.secondary_url || '', 'input', '#paket');
        } else if (block.type === 'pain_points') {
            html += field('Judul Section', 'headline', block.headline || '', 'input');
            html += guideBox('Panduan pain point', 'Klik <b>Tambah Poin</b>, lalu isi masalah customer satu-satu. Admin tidak perlu menulis format manual atau 1 baris = 1 poin lagi.');
            html += cardEditor(block.items, 'simple');
        } else if (block.type === 'benefits' || block.type === 'product_highlight' || block.type === 'testimonial') {
            html += field('Judul Section', 'headline', block.headline || '', 'input');
            html += guideBox('Panduan card benefit', 'Klik <b>Tambah Card</b> untuk menambah benefit. Setiap card bisa punya judul, deskripsi, gambar opsional, dan link opsional. Mau 2 card saja? Sisakan 2 card saja.');
            html += cardEditor(block.items, 'cards');
        } else if (block.type === 'pricing_cards') {
            html += field('Judul Section', 'headline', block.headline || '', 'input');
            html += field('URL tombol semua paket', 'button_url', block.button_url || '', 'input', '#form-konsultasi / https://wa.me/...');
            html += guideBox('Panduan paket harga', 'Klik <b>Tambah Paket</b>, lalu isi nama paket, harga, fitur, dan teks tombol satu-satu. Fitur bisa ditulis per baris.');
            html += cardEditor(block.items, 'pricing');
        } else if (block.type === 'countdown_timer') {
            html += guideBox('Panduan countdown timer', 'Cocok untuk promo terbatas, event, bonus, launching, atau deadline pendaftaran. Gunakan secara jujur sesuai periode campaign agar trust tetap aman.');
            html += field('Headline Countdown', 'headline', block.headline || '', 'input', 'Promo Berakhir Dalam');
            html += field('Teks Pendukung', 'text', block.text || '', 'textarea', 'Jelaskan alasan pengunjung perlu action sebelum waktu habis.');
            html += `<label>Deadline Promo <small>Format mengikuti browser/local time admin. Contoh: 2026-06-05T23:59.</small><input type="datetime-local" data-key="countdown_deadline" value="${escapeHtml(block.countdown_deadline || '')}"></label>`;
            html += field('Zona Waktu / Catatan waktu', 'countdown_timezone', block.countdown_timezone || 'WIB', 'input', 'WIB / WITA / WIT');
            html += field('Pesan saat waktu habis', 'expired_text', block.expired_text || '', 'textarea', 'Promo sudah berakhir. Hubungi admin untuk promo terbaru.');
            html += field('Catatan kecil', 'countdown_note', block.countdown_note || '', 'input', 'Kuota terbatas / periode promo bisa berubah sewaktu-waktu.');
            html += field('Teks Tombol', 'button_text', block.button_text || '', 'input', 'Ambil Promo Sekarang');
            html += field('URL Tombol', 'button_url', block.button_url || '', 'input', '#form-konsultasi / https://wa.me/...');
        } else if (block.type === 'custom_menu') {
            html += guideBox('Panduan header/menu custom', 'Gunakan blok ini untuk membuat menu landing page di bagian atas. Pilih <b>Header menu</b>, lalu atur apakah normal, sticky, atau fixed. Mode <b>Card link</b> tetap tersedia untuk internal link SEO di tengah halaman.');
            html += selectField('Mode tampilan menu', 'menu_style', block.menu_style || (block.engine_internal_links ? 'cards' : 'header'), [['header','Header menu atas'],['cards','Card link / internal link']]);
            html += selectField('Posisi header menu', 'menu_position', block.menu_position || 'normal', [['normal','Normal mengikuti posisi blok'],['sticky','Sticky saat discroll'],['fixed','Fixed selalu di atas']]);
            html += selectField('Rata menu header', 'menu_align', block.menu_align || 'center', [['left','Kiri'],['center','Tengah'],['right','Kanan']]);
            html += selectField('Posisi logo menu', 'logo_align', block.logo_align || 'left', [['left','Kiri'],['center','Tengah'],['right','Kanan']]);
            html += field('Judul Menu', 'headline', block.headline || '', 'input', 'Menu Cepat Landing Page');
            html += field('Deskripsi singkat', 'text', block.text || '', 'textarea', 'Opsional, tampil jika mode card link atau jika ingin ada intro.');
            html += guideBox('Isi menu', 'Klik <b>Tambah Menu</b>, isi label, anchor/URL, logo menu opsional, dan deskripsi singkat. Contoh anchor: <b>#paket</b>, <b>#form-konsultasi</b>.');
            html += cardEditor(block.items, 'menu');
        } else if (block.type === 'media') {
            html += guideBox('Panduan layout media fleksibel', 'Pakai mode <b>Otomatis</b> untuk menyesuaikan isi. Jika hanya ingin gambar besar, pilih <b>Media saja full</b>. Jika hanya ingin teks penjelasan, pilih <b>Teks saja full</b>.');
            html += selectField('Mode Tampilan Media', 'media_layout', block.media_layout || 'auto', [['auto','Otomatis fleksibel'],['split','2 kolom: media + teks'],['media_only','Media saja full'],['text_only','Teks saja full']]);
            html += selectField('Posisi media saat 2 kolom', 'media_position', block.media_position || 'left', [['left','Media kiri, teks kanan'],['right','Teks kiri, media kanan']]);
            html += field('Judul Media', 'headline', block.headline || '', 'input');
            html += field('Teks Media', 'text', block.text || '', 'textarea');
            html += field('URL Gambar/Media', 'image', block.image || '', 'input', '/assets/images/contoh.webp');
            html += field('Alt Text Media', 'image_alt', block.image_alt || '', 'input', 'Deskripsi gambar');
            html += field('Teks Tombol', 'button_text', block.button_text || '', 'input', 'Konsultasi');
            html += field('URL Tombol', 'button_url', block.button_url || '', 'input', '#form-konsultasi');
        } else if (block.type === 'free_cards') {
            html += field('Judul Section Bebas', 'headline', block.headline || '', 'input', 'Judul Bebas');
            html += field('Deskripsi Section', 'text', block.text || '', 'textarea', 'Opsional.');
            html += guideBox('Panduan Judul Bebas / card fleksibel', 'Klik <b>Tambah Card</b> untuk membuat card bebas berisi teks, gambar, dan link. Cocok untuk bonus, galeri mini, layanan, profil, atau konten campuran.');
            html += cardEditor(block.items, 'cards');
        } else if (block.type === 'html_block') {
            html += guideBox('HTML Block aman', 'Gunakan untuk import section HTML dari website lama. Script, iframe berisiko, event handler, dan javascript: URL dibersihkan saat disimpan. Untuk HTML satu halaman penuh, gunakan Full HTML Expert Mode di tab Pengaturan.');
            html += field('Judul section', 'headline', block.headline || 'HTML Block Aman', 'input');
            html += field('Catatan pendek', 'text', block.text || '', 'textarea');
            html += field('HTML section', 'html', block.html || '<section><h2>Judul HTML Section</h2><p>Isi HTML aman tanpa script.</p></section>', 'textarea');
            html += field('Sumber builder', 'builder_source', block.builder_source || 'manual-html', 'input');
            html += field('Widget kompleks/catatan review', 'complex_widgets', block.complex_widgets || '', 'input');
        } else if (block.type === 'faq') {
            html += field('Judul Section', 'headline', block.headline || '', 'input');
            html += guideBox('Panduan FAQ', 'Klik <b>Tambah FAQ</b>, lalu isi pertanyaan dan jawaban satu-satu.');
            html += cardEditor(block.items, 'faq');
        } else if (block.type === 'lead_form') {
            html += field('Judul Form', 'headline', block.headline || '', 'input', 'Isi Form Konsultasi');
            html += field('Deskripsi Form', 'text', block.text || '', 'textarea', 'Lengkapi data berikut agar admin bisa follow-up.');
            html += selectField('Sumber Form', 'form_source', block.form_source || 'lp_builtin', [['lp_builtin','Form cepat di landing page'],['custom_form','Pakai Form Custom dari admin']]);
            if ((block.form_source || 'lp_builtin') === 'custom_form') {
                html += customFormSelect(block);
            } else {
                html += field('Teks Tombol Submit', 'submit_text', block.submit_text || '', 'input', 'Kirim Form');
                html += field('Pesan sukses', 'success_text', block.success_text || '', 'input', 'Terima kasih, form sudah masuk.');
                html += field('Default kebutuhan', 'need_default', block.need_default || '', 'input', 'Konsultasi Landing Page');
                html += field('Mailketing List ID khusus form ini', 'mailketing_list_id', block.mailketing_list_id || '', 'input', 'Kosongkan untuk pakai list default/inquiry');
                html += `<div class="lpw-advanced-form-box"><strong>Segmentasi Lead</strong><small>Opsional untuk membaca asal lead dan kebutuhan follow-up.</small>`;
                html += field('Nama Form', 'form_name', block.form_name || '', 'input', 'Form Konsultasi Landing Page');
                html += field('Lead Segment', 'lead_segment', block.lead_segment || '', 'input', 'contoh: layanan-bogor, promo-premium');
                html += field('Lead Tags', 'lead_tags', block.lead_tags || '', 'input', 'pisahkan koma: layanan,bogor,hot-lead');
                html += selectField('Prioritas Lead', 'lead_priority', block.lead_priority || '', [['','Default'],['cold','Rendah'],['warm','Sedang'],['hot','Tinggi'],['vip','VIP']]);
                html += field('Tahap Lead', 'lead_stage', block.lead_stage || '', 'input', 'contoh: baru, konsultasi, siap-order');
                html += field('Skor Lead', 'lead_score', block.lead_score || '', 'input', '0-100');
                html += `</div>`;
                html += field('Teks persetujuan', 'consent_text', block.consent_text || '', 'textarea', 'Saya bersedia dihubungi admin.');
                html += guideBox('Panduan field form', 'Klik <b>Tambah Field</b>, isi nama field dan label, pilih tipe dari dropdown, lalu pilih <b>Wajib</b> atau <b>Opsional</b>. Untuk select/radio/checkbox, isi opsi seperti: <b>Ekonomis, Medium, Premium</b>.');
                html += formFieldEditor(block.fields);
            }
        } else if (block.type === 'cta') {
            html += field('Headline Tombol', 'headline', block.headline || '', 'input');
            html += field('Teks Pendukung', 'text', block.text || '', 'textarea');
            html += field('Teks Tombol', 'button_text', block.button_text || '', 'input');
            html += field('URL Tombol', 'button_url', block.button_url || '', 'input', '#form-konsultasi / https://wa.me/...');
        } else {
            html += field('Headline', 'headline', block.headline || '', 'input');
            html += field('Teks', 'text', block.text || '', 'textarea');
        }
        html += designFields(block);
        return html;
    }
    function cleanPreviewItems(items){
        return (Array.isArray(items) ? items : []).filter(function(item){
            if (typeof item === 'string') return item.trim() !== '';
            if (!item || typeof item !== 'object') return false;
            return String(item.title||item.text||item.image||item.url||item.question||item.answer||item.price||item.button_text||'').trim() !== '' || (Array.isArray(item.features) && item.features.length > 0);
        });
    }
    function smartGridColumns(count, maxCols){
        const limit = maxCols || 3;
        const total = Math.max(1, Number(count || 0));
        if (total === 1) return 1;
        if (total === 2) return Math.min(2, limit);
        // 4 card lebih rapi memakai 2x2, bukan 3+1 yang terlihat kosong/orphan.
        if (total === 4 && limit >= 2) return 2;
        return Math.min(limit, total);
    }
    function repeatGridStyle(items, maxCols){
        const count = cleanPreviewItems(items).length;
        const cols = smartGridColumns(count, maxCols || 3);
        return ` style="--lpw-card-columns:${cols}"`;
    }
    function cssVar(name, value){ return value ? `${name}:${value}` : ''; }
    function flexJustify(align){
        if (align === 'center') return 'center';
        if (align === 'right') return 'flex-end';
        if (align === 'left') return 'flex-start';
        return '';
    }
    function actionStyleAttr(block){
        const justify = flexJustify(block.button_align || '');
        return justify ? ` style="justify-content:${justify}"` : '';
    }
    function styleAttr(block){
        const styles = [];
        const attrs = [];
        if (block.bg_color) styles.push(`--lp-block-bg:${block.bg_color}`, `background:${block.bg_color}`);
        if (block.text_color) styles.push(`--lp-block-text:${block.text_color}`, `color:${block.text_color}`);
        if (block.accent_color) styles.push(`--lp-block-accent:${block.accent_color}`);
        if (block.align) styles.push(`text-align:${block.align}`, `--lp-block-align:${block.align}`);
        const buttonJustify = flexJustify(block.button_align || '');
        if (buttonJustify) styles.push(`--lp-button-justify:${buttonJustify}`);
        const vars = [
            cssVar('--lp-title-color', block.title_color), cssVar('--lp-title-size', block.title_size || block.headline_size), cssVar('--lp-title-align', block.title_align), cssVar('--lp-title-weight', block.title_weight || block.font_weight), cssVar('--lp-title-style', block.title_style || block.font_style), cssVar('--lp-title-decoration', block.title_decoration || block.text_decoration),
            cssVar('--lp-description-color', block.description_color), cssVar('--lp-description-size', block.description_size || block.text_size), cssVar('--lp-description-align', block.description_align), cssVar('--lp-description-weight', block.description_weight), cssVar('--lp-description-style', block.description_style || block.font_style), cssVar('--lp-description-decoration', block.description_decoration || block.text_decoration),
            cssVar('--lp-button-bg', block.button_bg), cssVar('--lp-button-color', block.button_text_color), cssVar('--lp-button-size', block.button_size), cssVar('--lp-button-radius', block.button_radius), cssVar('--lp-button-align', block.button_align), cssVar('--lp-menu-align', block.menu_align), cssVar('--lp-logo-align', block.logo_align),
            cssVar('--lp-card-bg', block.card_bg), cssVar('--lp-card-color', block.card_text_color), cssVar('--lp-card-title-color', block.card_title_color), cssVar('--lp-card-title-size', block.card_title_size), cssVar('--lp-card-text-size', block.card_text_size), cssVar('--lp-card-radius', block.card_radius), cssVar('--lp-card-align', block.card_align), cssVar('--lp-card-shadow', block.card_shadow)
        ].filter(Boolean);
        styles.push(...vars);
        if (block.button_align) attrs.push(`data-lp-button-align="${escapeHtml(block.button_align)}"`);
        if (styles.length) attrs.push(`style="${escapeHtml(styles.join(';'))}"`);
        if (block.section_effect && block.section_effect !== 'none') attrs.push(`data-lp-effect="${escapeHtml(block.section_effect)}"`);
        if (block.animation_style && block.animation_style !== 'inherit') attrs.push(`data-lp-animation="${escapeHtml(block.animation_style)}"`);
        if (block.block_goal) attrs.push(`data-lp-goal="${escapeHtml(block.block_goal)}"`);
        if (block.cta_role) attrs.push(`data-lp-cta-role="${escapeHtml(block.cta_role)}"`);
        return attrs.length ? ' ' + attrs.join(' ') : '';
    }
    function titleStyle(block){
        const styles = [];
        if (block.title_color) styles.push(`color:${block.title_color}`);
        if (block.title_size || block.headline_size) styles.push(`font-size:${block.title_size || block.headline_size}`);
        if (block.title_align) styles.push(`text-align:${block.title_align}`);
        if (block.title_weight || block.font_weight) styles.push(`font-weight:${block.title_weight || block.font_weight}`);
        if (block.title_style || block.font_style) styles.push(`font-style:${block.title_style || block.font_style}`);
        if (block.title_decoration || block.text_decoration) styles.push(`text-decoration:${block.title_decoration || block.text_decoration}`);
        return styles.length ? ` style="${escapeHtml(styles.join(';'))}"` : '';
    }
    function paragraphStyle(block){
        const styles = [];
        if (block.description_color) styles.push(`color:${block.description_color}`);
        if (block.description_size || block.text_size) styles.push(`font-size:${block.description_size || block.text_size}`);
        if (block.description_align) styles.push(`text-align:${block.description_align}`);
        if (block.description_weight) styles.push(`font-weight:${block.description_weight}`);
        if (block.description_style || block.font_style) styles.push(`font-style:${block.description_style || block.font_style}`);
        if (block.description_decoration || block.text_decoration) styles.push(`text-decoration:${block.description_decoration || block.text_decoration}`);
        return styles.length ? ` style="${escapeHtml(styles.join(';'))}"` : '';
    }
    function itemStyle(item){
        item = item && typeof item === 'object' ? item : {};
        const styles = [];
        if (item.item_bg) styles.push(`--lp-item-bg:${item.item_bg}`, `background:${item.item_bg}`);
        if (item.item_title_color) styles.push(`--lp-item-title-color:${item.item_title_color}`);
        if (item.item_text_color) styles.push(`--lp-item-text-color:${item.item_text_color}`, `color:${item.item_text_color}`);
        if (item.item_button_bg) styles.push(`--lp-item-button-bg:${item.item_button_bg}`);
        if (item.item_button_text_color) styles.push(`--lp-item-button-color:${item.item_button_text_color}`);
        if (item.item_title_size) styles.push(`--lp-item-title-size:${item.item_title_size}`);
        if (item.item_text_size) styles.push(`--lp-item-text-size:${item.item_text_size}`);
        if (item.item_radius) styles.push(`--lp-item-radius:${item.item_radius}`, `border-radius:${item.item_radius}`);
        if (item.item_align) styles.push(`--lp-item-align:${item.item_align}`, `text-align:${item.item_align}`);
        if (item.item_shadow) styles.push(`--lp-item-shadow:${item.item_shadow}`);
        return styles.length ? ` data-lp-item-styled="1" style="${escapeHtml(styles.join(';'))}"` : '';
    }
    function button(text){ return `<span class="lpw-pv-btn">${escapeHtml(text || 'Tombol')}</span>`; }
    function actionWrap(block, html){ return html ? `<div class="lpw-pv-actions"${actionStyleAttr(block)}>${html}</div>` : ''; }
    function hasText(value){ return String(value || '').trim() !== ''; }
    function hasUrl(value){ return String(value || '').trim() !== ''; }
    function blockItemsCount(block){ return cleanPreviewItems(block.items || []).length; }
    function blockStatus(block){
        const type = block.type || 'text';
        const hasHeadline = hasText(block.headline) || type === 'hero_offer';
        let ok = hasHeadline;
        let hint = 'Lengkapi judul section.';
        if (type === 'hero_offer') { ok = hasText(block.headline) && (hasText(block.primary_text) || hasText(block.secondary_text)); hint = 'Hero butuh headline dan minimal 1 Tombol.'; }
        else if (['pain_points','benefits','product_highlight','testimonial','pricing_cards','faq','custom_menu','free_cards'].includes(type)) { ok = hasHeadline && blockItemsCount(block) > 0; hint = 'Tambahkan minimal 1 item agar section tidak kosong.'; }
        else if (type === 'lead_form') { if (block.form_source === 'custom_form') { ok = hasHeadline && hasText(block.custom_form_slug); hint = 'Pilih Form Custom yang sudah aktif dari menu Form Custom.'; } else { ok = hasHeadline && Array.isArray(block.fields) && block.fields.length > 0 && hasText(block.submit_text); hint = 'Form bawaan butuh judul, minimal 1 field, dan tombol submit.'; } }
        else if (type === 'media') { ok = hasHeadline || hasText(block.text) || hasText(block.image); hint = 'Isi judul, teks, atau gambar media.'; }
        else if (type === 'countdown_timer') { ok = hasHeadline && hasText(block.countdown_deadline); hint = 'Countdown butuh headline dan deadline promo/event.'; }
        else if (type === 'cta') { ok = hasHeadline && hasText(block.button_text) && hasUrl(block.button_url); hint = 'Tombol butuh headline, teks tombol, dan URL tombol.'; }
        else { ok = hasHeadline || hasText(block.text); hint = 'Isi judul atau teks blok.'; }
        if (ok && !hasText(block.block_goal)) {
            return {label:'Perlu goal', cls:'is-mid', hint:'Isi Tujuan blok agar analisa funnel lebih rapi.'};
        }
        return ok ? {label:'Siap', cls:'is-ok', hint:'Blok sudah cukup aman.'} : {label:'Perlu isi', cls:'is-warning', hint:hint};
    }
    function ctaBlocks(){
        return blocks.filter(function(b){
            return (b.type === 'cta' && hasUrl(b.button_url)) || (b.type === 'hero_offer' && (hasUrl(b.primary_url) || hasUrl(b.secondary_url))) || (b.type === 'pricing_cards' && hasUrl(b.button_url)) || (b.type === 'countdown_timer' && hasUrl(b.button_url));
        });
    }
    function firstLeadForm(){ return blocks.find(function(b){ return b.type === 'lead_form'; }) || {}; }
    function validEventName(name){ return /^[A-Za-z][A-Za-z0-9_:-]{1,63}$/.test(String(name || '')); }
    function ctaCopyIsStrong(){
        const phrases = [];
        blocks.forEach(function(b){
            ['primary_text','secondary_text','button_text','submit_text'].forEach(function(k){ if (b[k]) phrases.push(String(b[k]).toLowerCase()); });
            if (Array.isArray(b.items)) b.items.forEach(function(i){ if (i && typeof i === 'object' && i.button_text) phrases.push(String(i.button_text).toLowerCase()); });
        });
        if (!phrases.length) return false;
        return phrases.some(function(text){ return /(konsultasi|chat|pesan|order|tanya|daftar|minta|kirim|hubungi)/i.test(text) && text.length >= 8; });
    }
    function internalLinkCoverage(){
        const haystack = JSON.stringify(blocks || []).toLowerCase();
        return {
            produk_fisik: /produk fisik|katalog/.test(haystack),
            layanan: /layanan|paket-layanan/.test(haystack),
            lokasi: /online|jakarta|bandung|surabaya|semarang|kontak/.test(haystack),
            artikel: /artikel|panduan|edukasi/.test(haystack)
        };
    }
    function landingHealth(){
        const total = blocks.length;
        const title = formValue('title').trim();
        const slug = formValue('slug').trim();
        const metaTitle = (formValue('meta_title') || title).trim();
        const metaDescription = formValue('meta_description').trim();
        const trackingLabel = formValue('tracking_label').trim();
        const indexable = formValue('indexable') === '1';
        const hasHero = blocks.some(b => b.type === 'hero_offer' && hasText(b.headline));
        const hasH1 = hasHero || hasText(title);
        const hasForm = blocks.some(b => b.type === 'lead_form' && Array.isArray(b.fields) && b.fields.length > 0 && hasText(b.submit_text));
        const hasCta = ctaBlocks().length > 0;
        const hasStrongCta = hasCta && ctaCopyIsStrong();
        const faqCount = blocks.reduce(function(count,b){ return count + (b.type === 'faq' ? blockItemsCount(b) : 0); }, 0);
        const pricingCount = blocks.reduce(function(count,b){ return count + (b.type === 'pricing_cards' ? blockItemsCount(b) : 0); }, 0);
        const hasFaq = faqCount >= 2;
        const hasMetaTitle = metaTitle.length >= 35 && metaTitle.length <= 70;
        const hasMetaDescription = metaDescription.length >= 110 && metaDescription.length <= 170;
        const leadForm = firstLeadForm();
        const trackingActive = hasText(trackingLabel) || hasText(leadForm.lead_segment) || hasText(leadForm.lead_tags);
        const links = internalLinkCoverage();
        const linkScore = Object.values(links).filter(Boolean).length;
        const incomplete = blocks.map((b, i) => ({index:i, status:blockStatus(b), block:b})).filter(row => row.status.cls === 'is-warning');
        const goalMarked = blocks.filter(b => hasText(b.block_goal)).length;
        const goalCoverage = total ? Math.round(goalMarked / total * 100) : 0;
        const effectCount = blocks.filter(b => b.section_effect && b.section_effect !== 'none').length;
        const animationCount = blocks.filter(b => b.animation_style && b.animation_style !== 'inherit' && b.animation_style !== 'none').length;
        const ctaRoleCount = blocks.filter(b => hasText(b.cta_role)).length;
        const optimizationNotes = blocks.filter(b => hasText(b.optimization_note)).length;
        const ctaTestEnabled = formValue('ab_cta_enabled') === '1';
        const formTestEnabled = formValue('ab_form_enabled') === '1';
        const ctaTestingReady = hasCta && (ctaRoleCount > 0 || ctaTestEnabled);
        const formTestingReady = hasForm && formTestEnabled;
        const mobilePreviewChecked = !!previewDeviceChecks.mobile;
        const hasTrustBlock = hasFaq || blocks.some(function(b){ return ['testimonial','benefits','free_cards'].includes(b.type) && blockItemsCount(b) > 0; });
        const countdownCount = blocks.filter(function(b){ return b.type === 'countdown_timer'; }).length;
        const hasOfferBlock = pricingCount > 0 || countdownCount > 0 || blocks.some(function(b){ return ['product_highlight','pricing_cards'].includes(b.type); });
        const hasClosingBlock = blocks.some(function(b){ return b.type === 'cta' || b.block_goal === 'closing'; });
        const critical = [];
        let score = 0;
        if (total >= 4) score += 8;
        if (hasH1) score += 10;
        if (hasHero) score += 10;
        if (hasCta) score += 10;
        if (hasStrongCta) score += 7;
        if (hasForm) score += 10;
        if (hasFaq) score += 8;
        if (hasMetaTitle) score += 10;
        if (hasMetaDescription) score += 10;
        if (trackingActive) score += 8;
        if (pricingCount > 0) score += 4;
        if (linkScore >= 2) score += 3;
        if (incomplete.length === 0 && total > 0) score += 2;
        if (effectCount > 0) score += 3;
        if (animationCount > 0) score += 2;
        if (goalCoverage >= 70) score += 4;
        if (ctaTestingReady) score += 3;
        score = Math.min(100, score);
        const suggestions = [];
        if (!hasH1) { suggestions.push('H1/headline utama masih kosong. Isi judul Landing Page atau Hero headline.'); critical.push('Headline utama belum jelas.'); }
        if (!hasHero) { suggestions.push('Tambahkan/isi Hero agar penawaran langsung jelas saat halaman dibuka.'); critical.push('Hero/penawaran awal belum siap.'); }
        if (!hasCta) { suggestions.push('Tombol belum kuat. Tambahkan tombol WA/form/Tombol dengan URL tujuan.'); critical.push('Belum ada CTA untuk mengarahkan visitor.'); }
        else if (!hasStrongCta) suggestions.push('Copy Tombol masih lemah. Pakai kata aksi seperti Konsultasi, Chat, Tanya, Pesan, atau Kirim.');
        if (!hasForm) { suggestions.push('Form Custom belum siap. Tambahkan field dan tombol submit untuk menangkap lead.'); critical.push('Belum ada form/lead capture yang siap.'); }
        if (!hasTrustBlock) suggestions.push('Trust element masih kurang. Tambahkan benefit, testimoni, FAQ, atau bukti kepercayaan.');
        if (!hasOfferBlock) suggestions.push('Offer belum terlihat kuat. Tambahkan paket harga, highlight produk, bonus, atau penawaran utama.');
        if (!hasClosingBlock) suggestions.push('Tambahkan CTA penutup agar visitor punya arah jelas setelah membaca LP.');
        if (!hasFaq) suggestions.push('FAQ kurang. Minimal 2 FAQ agar objection calon pembeli berkurang dan bisa jadi FAQ schema.');
        if (!hasMetaTitle) suggestions.push('Meta title ideal 35–70 karakter. Saat ini ' + metaTitle.length + ' karakter.');
        if (!hasMetaDescription) suggestions.push('Meta description ideal 110–170 karakter. Saat ini ' + metaDescription.length + ' karakter.');
        if (!trackingActive) suggestions.push('Label tracking atau kategori lead belum jelas. Isi agar laporan promosi dan sumber lead lebih rapi.');
        if (linkScore < 2) suggestions.push('Internal link masih minim. Tambahkan link ke katalog produk fisik, layanan, lokasi, atau artikel terkait.');
        if (goalCoverage < 70 && total > 0) suggestions.push('Tandai tujuan blok minimal 70% agar analisa funnel awareness → trust → offer → lead → closing lebih kebaca.');
        if (effectCount === 0) suggestions.push('Belum ada section effect. Tambahkan efek ringan di 1-2 blok penting agar LP terlihat lebih premium tanpa berat.');
        if (!ctaTestingReady) suggestions.push('CTA testing belum siap. Tandai Peran CTA atau aktifkan A/B test Tombol untuk membandingkan copy tombol.');
        if (hasForm && !formTestingReady) suggestions.push('Form Copy Test belum aktif. Cocok dipakai kalau traffic iklan sudah mulai masuk.');
        if (!mobilePreviewChecked) suggestions.push('Mobile preview belum dicek. Klik mode Mobile sekali sebelum publish agar tampilan HP aman.');
        if (incomplete.length) suggestions.push(incomplete.length + ' blok masih perlu dilengkapi.');
        if (!suggestions.length) suggestions.push('Struktur, SEO, conversion, schema, tracking, efek visual, dan CTA testing foundation sudah solid. Tinggal final polish copywriting.');
        const readinessLabel = score >= 90 ? 'Siap publish' : (score >= 70 ? 'Cukup siap' : 'Perlu dilengkapi');
        const guardTone = score >= 90 && critical.length === 0 ? 'is-good' : (score >= 70 ? 'is-mid' : 'is-low');
        return {score,total,title,slug,hasHero,hasH1,hasForm,hasCta,hasStrongCta,hasFaq,faqCount,pricingCount,hasMetaTitle,hasMetaDescription,trackingActive,indexable,links,linkScore,incomplete,suggestions,goalMarked,goalCoverage,effectCount,animationCount,ctaRoleCount,optimizationNotes,ctaTestEnabled,formTestEnabled,ctaTestingReady,formTestingReady,mobilePreviewChecked,hasTrustBlock,hasOfferBlock,hasClosingBlock,countdownCount,critical,readinessLabel,guardTone};
    }
    function renderHealth(){
        if (!healthPanel) return;
        const h = landingHealth();
        const tone = h.score >= 82 ? 'is-good' : (h.score >= 58 ? 'is-mid' : 'is-low');
        healthPanel.innerHTML = `<div class="lpw-health-score ${tone}"><strong>${h.score}%</strong><span>Skor kesiapan publish</span></div><div class="lpw-health-meter" aria-hidden="true"><i style="width:${h.score}%"></i></div><div class="lpw-health-grid"><span>${h.total} blok</span><span>${h.hasH1?'H1/headline ok':'H1 kosong'}</span><span>${h.hasCta?'Tombol ada':'Tombol belum'}</span><span>${h.hasForm?'Form siap':'Form belum'}</span><span>${h.hasFaq?'FAQ schema siap':'FAQ kurang'}</span><span>${h.hasMetaDescription?'Meta desc ok':'Meta desc kurang'}</span><span>${h.trackingActive?'Tracking aktif':'Tracking belum'}</span><span>${h.linkScore}/4 internal link</span><span>${h.goalCoverage}% goal blok</span><span>${h.effectCount} efek visual</span><span>${h.ctaTestingReady?'CTA test ready':'CTA test belum'}</span></div><ul class="lpw-health-list">${h.suggestions.slice(0,6).map(s=>`<li>${escapeHtml(s)}</li>`).join('')}</ul>`;
    }
    function publishChecklistItems(h){
        return [
            {key:'headline', ok:h.hasH1 && h.hasHero, label:'Headline/Hero sudah jelas'},
            {key:'cta', ok:h.hasCta && h.hasStrongCta, label:'CTA/tombol mengarah ke aksi'},
            {key:'capture', ok:h.hasForm, label:'Form atau lead capture tersedia'},
            {key:'trust', ok:h.hasTrustBlock, label:'Trust element ada'},
            {key:'offer', ok:h.hasOfferBlock, label:'Offer/paket/penawaran terlihat'},
            {key:'closing', ok:h.hasClosingBlock, label:'Closing CTA tersedia'},
            {key:'seo', ok:h.hasMetaTitle && h.hasMetaDescription, label:'SEO title & meta description ideal'},
            {key:'tracking', ok:h.trackingActive, label:'Tracking label/lead segment siap'},
            {key:'mobile', ok:h.mobilePreviewChecked, label:'Mobile preview sudah dicek'}
        ];
    }
    function renderPublishChecklist(){
        if (!publishChecklistPanel) return;
        const h = landingHealth();
        const items = publishChecklistItems(h);
        const passed = items.filter(function(item){ return item.ok; }).length;
        const rows = items.map(function(item){ return `<li class="${item.ok ? 'is-ok' : 'is-warning'}"><span>${item.ok ? '✓' : '!'}</span><strong>${escapeHtml(item.label)}</strong></li>`; }).join('');
        publishChecklistPanel.innerHTML = `<div class="lpw-publish-score ${h.guardTone}"><strong>${h.score}%</strong><div><b>${escapeHtml(h.readinessLabel)}</b><small>${passed}/${items.length} checklist siap</small></div></div><ul class="lpw-publish-checklist-list">${rows}</ul><div class="lpw-publish-guard-note"><strong>Catatan publish</strong><p>Publish akan menyimpan versi terbaru lalu membuka LP ke publik. Autosave lokal hanya cadangan browser dan belum menggantikan keputusan publish.</p></div>`;
    }
    function renderPublishGuardDialog(){
        if (!publishGuardContent) return;
        const h = landingHealth();
        const items = publishChecklistItems(h);
        const passed = items.filter(function(item){ return item.ok; }).length;
        const criticalRows = h.critical.length ? h.critical.map(function(item){ return `<li>${escapeHtml(item)}</li>`; }).join('') : '<li>Tidak ada masalah kritis. Tinggal cek saran kecil bila perlu.</li>';
        const checklistRows = items.map(function(item){ return `<li class="${item.ok ? 'is-ok' : 'is-warning'}"><span>${item.ok ? '✓' : '!'}</span><strong>${escapeHtml(item.label)}</strong></li>`; }).join('');
        const suggestions = h.suggestions.slice(0, 5).map(function(item){ return `<li>${escapeHtml(item)}</li>`; }).join('');
        publishGuardContent.innerHTML = `<div class="lpw-publish-score lpw-publish-score--dialog ${h.guardTone}"><strong>${h.score}%</strong><div><b>${escapeHtml(h.readinessLabel)}</b><small>${passed}/${items.length} checklist siap · ${h.critical.length} warning kritis</small></div></div><div class="lpw-publish-guard-grid"><section><h3>Checklist Publish</h3><ul class="lpw-publish-checklist-list">${checklistRows}</ul></section><section><h3>Warning penting</h3><ul class="lpw-publish-warning-list">${criticalRows}</ul></section></div><div class="lpw-engine-reco lpw-engine-reco--guard"><strong>Rekomendasi cepat</strong><ul>${suggestions}</ul></div>`;
    }
    function openPublishGuard(button){
        pendingPublishButton = button || null;
        renderPublishGuardDialog();
        if (publishGuardDialog && typeof publishGuardDialog.showModal === 'function') publishGuardDialog.showModal();
        else if (publishGuardDialog) publishGuardDialog.setAttribute('open', 'open');
    }
    function closePublishGuard(){
        if (publishGuardDialog && typeof publishGuardDialog.close === 'function') publishGuardDialog.close();
        else if (publishGuardDialog) publishGuardDialog.removeAttribute('open');
    }

    function internalLinkBlockIndex(){
        return blocks.findIndex(function(b){
            if (!b || b.type !== 'custom_menu') return false;
            if (b.engine_internal_links === true || b.auto_internal_links === true) return true;
            const text = JSON.stringify(b || {}).toLowerCase();
            return /link cepat|katalog produk fisik|paket layanan|area layanan|artikel edukasi/.test(text);
        });
    }
    function renderSeoSistem(){
        if (!seoSistemPanel) return;
        const h = landingHealth();
        const links = engineInternalLinks.map(function(link){
            const active = JSON.stringify(blocks || []).toLowerCase().includes(link.url.toLowerCase()) || JSON.stringify(blocks || []).toLowerCase().includes(link.title.toLowerCase());
            return `<li class="${active?'is-ok':'is-missing'}"><strong>${escapeHtml(link.title)}</strong><small>${active?'Sudah ada di konten/menu':'Disarankan ditambahkan'}</small></li>`;
        }).join('');
        const schemaFaq = h.faqCount >= 2;
        const schemaOffer = h.pricingCount > 0;
        const internalIndex = internalLinkBlockIndex();
        const hasInternalBlock = internalIndex >= 0;
        const internalActions = hasInternalBlock
            ? `<div class="lpw-engine-actions"><button type="button" class="lpw-repeat-add-btn" data-add-internal-links>↻ Update rekomendasi link</button><button type="button" class="lpw-repeat-add-btn lpw-repeat-add-btn--danger" data-remove-internal-links>Batalkan / Hapus Blok Link</button><button type="button" class="lpw-repeat-add-btn lpw-repeat-add-btn--ghost" data-focus-internal-links>Lihat di editor</button></div>`
            : `<button type="button" class="lpw-repeat-add-btn" data-add-internal-links>+ Buat Blok Menu Internal Link</button>`;
        seoSistemPanel.innerHTML = `<div class="lpw-engine-grid"><span class="${h.hasMetaTitle?'is-ok':'is-warning'}">Meta title ${h.hasMetaTitle?'ok':'perlu polish'}</span><span class="${h.hasMetaDescription?'is-ok':'is-warning'}">Meta description ${h.hasMetaDescription?'ok':'kurang ideal'}</span><span class="${schemaFaq?'is-ok':'is-warning'}">FAQ schema ${schemaFaq?'siap':'butuh 2 FAQ'}</span><span class="${schemaOffer?'is-ok':'is-warning'}">Offer schema ${schemaOffer?'siap':'butuh pricing'}</span></div><div class="lpw-engine-reco"><strong>Internal link suggestion</strong><ul>${links}</ul>${internalActions}</div>`;
    }

    function lpCtaSignalForBlock(block, index){
        const type = block && block.type ? String(block.type) : 'cta';
        const role = block && block.cta_role ? String(block.cta_role) : '';
        if (type === 'hero_offer') return role === 'secondary' ? 'hero_secondary_cta' : 'hero_cta';
        if (type === 'pricing_cards') return 'pricing_cta';
        if (type === 'countdown_timer') return 'countdown_cta';
        if (type === 'lead_form') return 'lead_form_submit';
        if (type === 'cta') return 'closing_cta';
        if (type === 'media') return 'media_cta';
        return (type + '_' + (role || 'cta')).replace(/[^a-zA-Z0-9_-]+/g, '_').toLowerCase() || ('block_' + (index + 1));
    }
    function lpCtaSignalRows(){
        return blocks.map(function(block, index){
            const hasButton = (block.type === 'hero_offer' && (hasUrl(block.primary_url) || hasUrl(block.secondary_url))) ||
                (block.type === 'pricing_cards' && hasUrl(block.button_url)) ||
                (block.type === 'countdown_timer' && hasUrl(block.button_url)) ||
                (block.type === 'media' && hasUrl(block.button_url)) ||
                (block.type === 'cta' && hasUrl(block.button_url)) ||
                (block.type === 'lead_form');
            if (!hasButton) return null;
            return {index:index + 1, type:block.type || 'blok', signal:lpCtaSignalForBlock(block, index), role:block.cta_role || '', goal:block.block_goal || ''};
        }).filter(Boolean);
    }
    function trackingPayloadPreview(){
        const leadForm = firstLeadForm();
        const slug = formValue('slug') || slugify(formValue('title')) || 'landing-page';
        return {
            sumber:'landing-page-builder',
            channel:'landing_page',
            event_names:['landing_page_view','landing_cta_click','submit_inquiry'],
            cta_signals: lpCtaSignalRows(),
            label: formValue('tracking_label') || formValue('title') || 'Landing Page',
            landing_page_slug: slug,
            lead_segment: leadForm.lead_segment || 'default',
            lead_tags: leadForm.lead_tags || '',
            lead_priority: leadForm.lead_priority || '',
            lead_stage: leadForm.lead_stage || '',
            lead_score: leadForm.lead_score || ''
        };
    }
    function renderTrackingInspector(message){
        if (!trackingInspector) return;
        const payload = trackingPayloadPreview();
        const eventRows = payload.event_names.map(function(name){ return `<li class="${validEventName(name)?'is-ok':'is-warning'}"><code>${escapeHtml(name)}</code><small>${validEventName(name)?'valid':'tidak valid'}</small></li>`; }).join('');
        const signalRows = payload.cta_signals.length ? payload.cta_signals.map(function(row){ return `<li><code>${escapeHtml(row.signal)}</code><small>#${row.index} · ${escapeHtml(typeLabels[row.type] || row.type)}${row.role ? ' · ' + escapeHtml(row.role) : ''}</small></li>`; }).join('') : '<li><code>Belum ada sinyal CTA</code><small>Tambahkan tombol, form, pricing, countdown, atau CTA closing.</small></li>';
        trackingInspector.innerHTML = `<div class="lpw-helper-guide lpw-helper-guide--compact"><strong>Tracking utama tetap dipakai</strong><p>Event tetap masuk ke sistem tracking utama, Landing Page Analytics, Optimization, dan CTA Result Tracker yang sudah tersedia.</p></div><div class="lpw-tracking-card"><strong>Event Name Preview</strong><ul>${eventRows}</ul></div><div class="lpw-tracking-card"><strong>CTA Signal Map</strong><ul>${signalRows}</ul></div><div class="lpw-tracking-card"><strong>Lead Source Preview</strong><pre>${escapeHtml(JSON.stringify(payload, null, 2))}</pre></div><button type="button" class="lpw-repeat-add-btn" data-lpw-test-event>Kirim Test Event Lokal</button>${message?`<p class="lpw-test-event-note">${escapeHtml(message)}</p>`:''}`;
    }
    function blockGoalLabel(goal){
        const map = {awareness:'Awareness', trust:'Trust', offer:'Offer', lead:'Lead Capture', closing:'Closing'};
        return map[goal] || 'Belum ditandai';
    }
    function renderPerformance(){
        if (!performancePanel) return;
        const h = landingHealth();
        const goalRows = ['awareness','trust','offer','lead','closing'].map(function(goal){
            const count = blocks.filter(function(b){ return b.block_goal === goal; }).length;
            return `<span class="${count ? 'is-ok' : 'is-warning'}">${blockGoalLabel(goal)}: ${count}</span>`;
        }).join('');
        const actionRows = [];
        if (!h.ctaTestingReady) actionRows.push('Tandai 1 tombol sebagai CTA utama atau aktifkan A/B test Tombol.');
        if (h.effectCount === 0) actionRows.push('Pilih efek section untuk Hero, Pricing, atau CTA closing. Cukup 1-2 blok agar tetap ringan.');
        if (h.goalCoverage < 70 && h.total > 0) actionRows.push('Beri tujuan blok di panel Desain & Optimasi agar funnel lebih mudah diaudit.');
        if (h.optimizationNotes === 0) actionRows.push('Isi catatan optimasi pada blok yang ingin dites, misalnya headline/CTA/trust.');
        if (!h.hasFaq) actionRows.push('Tambah minimal 2 FAQ untuk mengurangi keraguan visitor.');
        if (!actionRows.length) actionRows.push('Mantap, LP sudah punya struktur analisa yang enak dipantau. Lanjut cek data real di Landing Page Analytics setelah traffic masuk.');
        const topBlocks = blocks.map(function(block, index){
            return {index:index + 1, type:typeLabels[block.type] || 'Blok', goal:block.block_goal || '', cta:block.cta_role || '', effect:block.section_effect || '', note:block.optimization_note || ''};
        }).filter(function(row){ return row.goal || row.cta || row.effect || row.note; }).slice(0, 5);
        const blockRows = topBlocks.length ? topBlocks.map(function(row){
            return `<li><strong>#${row.index} ${escapeHtml(row.type)}</strong><small>${escapeHtml([row.goal ? blockGoalLabel(row.goal) : '', row.cta ? 'CTA: ' + row.cta : '', row.effect && row.effect !== 'none' ? 'Efek: ' + row.effect : ''].filter(Boolean).join(' · '))}</small>${row.note ? `<em>${escapeHtml(row.note)}</em>` : ''}</li>`;
        }).join('') : '<li><strong>Belum ada blok ditandai</strong><small>Gunakan panel Desain & Optimasi di tiap blok.</small></li>';
        performancePanel.innerHTML = `<div class="lpw-performance-metrics"><span><strong>${h.goalCoverage}%</strong><small>goal coverage</small></span><span><strong>${h.ctaRoleCount}</strong><small>CTA role</small></span><span><strong>${h.effectCount}</strong><small>section effect</small></span><span><strong>${h.optimizationNotes}</strong><small>catatan tes</small></span></div><div class="lpw-engine-grid lpw-performance-goals">${goalRows}</div><div class="lpw-engine-reco"><strong>Rekomendasi improvement</strong><ul>${actionRows.slice(0,5).map(a=>`<li>${escapeHtml(a)}</li>`).join('')}</ul></div><div class="lpw-performance-blocks"><strong>Blok yang sudah ditandai</strong><ul>${blockRows}</ul></div>`;
    }
    function buyerViewInsights(h){
        const first = blocks[0] || {};
        const heroText = String(first.headline || first.subheadline || '');
        const hasHeroFirst = first.type === 'hero_offer';
        const hasVisual = blocks.some(function(b){ return !!(b.image || (Array.isArray(b.items) && b.items.some(function(i){ return i && typeof i === 'object' && i.image; }))); });
        const hasShortForm = blocks.some(function(b){ return b.type === 'lead_form' && Array.isArray(b.fields) && b.fields.length > 0 && b.fields.length <= 6; });
        const strongOpening = hasHeroFirst && heroText.length >= 28 && heroText.length <= 170;
        const visualBalance = hasVisual || h.effectCount >= 2;
        const comfort = h.hasFaq && h.hasTrustBlock && h.hasClosingBlock;
        const actionClarity = h.hasStrongCta && h.hasForm;
        const checks = [
            {ok:strongOpening, label:'First screen langsung menjelaskan penawaran'},
            {ok:visualBalance, label:'Ada visual/efek yang membuat halaman tidak flat'},
            {ok:comfort, label:'Ada trust, FAQ, dan closing untuk mengurangi ragu'},
            {ok:actionClarity, label:'Aksi berikutnya jelas: tombol/form/WA'},
            {ok:hasShortForm || !h.hasForm, label:'Form tidak terasa terlalu berat untuk calon pembeli'}
        ];
        const score = Math.round(checks.filter(function(item){ return item.ok; }).length / checks.length * 100);
        const recs = [];
        if (!strongOpening) recs.push('Pastikan blok pertama adalah Hero dengan headline yang langsung menjawab kebutuhan calon pembeli.');
        if (!visualBalance) recs.push('Tambahkan gambar, soft card, gradient glow, atau spotlight di blok penting agar LP lebih eye-catching.');
        if (!comfort) recs.push('Tambahkan trust element: benefit, testimoni, FAQ, jaminan layanan, atau alur pemesanan.');
        if (!actionClarity) recs.push('Buat CTA lebih jelas, misalnya “Konsultasi WhatsApp”, “Cek Paket”, atau “Isi Form Konsultasi”.');
        if (!hasShortForm && h.hasForm) recs.push('Form terlalu panjang bisa terasa berat. Untuk LP iklan, prioritaskan nama, WhatsApp, kebutuhan, lokasi, dan catatan.');
        if (!recs.length) recs.push('Dari sisi calon pembeli, struktur LP sudah enak: jelas, meyakinkan, dan ada aksi yang mudah diklik.');
        return {score:score, checks:checks, recs:recs};
    }
    function renderBuyerViewAdvisor(){
        if (!buyerViewPanel) return;
        const h = landingHealth();
        const view = buyerViewInsights(h);
        const tone = view.score >= 80 ? 'is-good' : (view.score >= 60 ? 'is-mid' : 'is-low');
        buyerViewPanel.innerHTML = `<div class="lpw-buyer-view-score ${tone}"><strong>${view.score}%</strong><div><b>Buyer comfort score</b><small>Jelas · meyakinkan · mudah klik</small></div></div><ul class="lpw-buyer-view-checks">${view.checks.map(function(item){ return `<li class="${item.ok ? 'is-ok' : 'is-warning'}"><span>${item.ok ? '✓' : '!'}</span><strong>${escapeHtml(item.label)}</strong></li>`; }).join('')}</ul><div class="lpw-engine-reco"><strong>Saran dari sudut pandang calon pembeli</strong><ul>${view.recs.slice(0,5).map(function(item){ return `<li>${escapeHtml(item)}</li>`; }).join('')}</ul></div>`;
    }
    function applyVisualPolishPreset(name){
        readAll();
        if (!blocks.length) { alert('Belum ada blok. Tambahkan Smart Preset atau Template dulu, lalu pakai Visual Polish.'); return; }
        const cfg = {
            'clean-premium': {heroBg:'#f8fafc', ctaBg:'#0f172a', accent:'#2563eb', effect:'soft-card', motion:'fade-up'},
            'soft-trust': {heroBg:'#eff6ff', ctaBg:'#1d4ed8', accent:'#2563eb', effect:'soft-card', motion:'fade-up'},
            'promo-bold': {heroBg:'#eef2ff', ctaBg:'#1e3a8a', accent:'#f59e0b', effect:'gradient-glow', motion:'zoom-soft'}
        }[name] || null;
        if (!cfg) return;
        blocks = blocks.map(function(block, index){
            const b = Object.assign({}, block);
            b.accent_color = b.accent_color || cfg.accent;
            if (b.type === 'hero_offer') { b.bg_color = cfg.heroBg; b.section_effect = 'gradient-glow'; b.animation_style = cfg.motion; b.block_goal = 'awareness'; b.cta_role = 'primary'; }
            else if (['benefits','testimonial','pricing_cards','lead_form','free_cards'].includes(b.type)) { b.section_effect = b.section_effect && b.section_effect !== 'none' ? b.section_effect : cfg.effect; b.animation_style = b.animation_style || 'fade-up'; }
            else if (b.type === 'cta') { b.bg_color = cfg.ctaBg; b.text_color = '#ffffff'; b.section_effect = 'gradient-glow'; b.animation_style = cfg.motion; b.block_goal = 'closing'; b.cta_role = 'closing'; }
            else if (index > 0 && index % 3 === 0) { b.section_effect = b.section_effect || 'divider-line'; }
            return optimizeBlockDefaults(b);
        });
        if (form && form.querySelector('[name="motion_enabled"]')) form.querySelector('[name="motion_enabled"]').checked = true;
        setFormValue('motion_style', cfg.motion);
        render();
        markDirty();
        setAutosaveStatus('Visual polish diterapkan · belum disimpan', true);
    }

    function lpBlockImageCount(){
        let count = 0;
        blocks.forEach(function(b){
            if (hasText(b.image)) count += 1;
            if (Array.isArray(b.items)) b.items.forEach(function(i){ if (i && typeof i === 'object' && hasText(i.image)) count += 1; });
        });
        return count;
    }
    function lpExternalUrlCount(){
        const raw = JSON.stringify(blocks || []);
        const matches = raw.match(/https?:\/\//g);
        return matches ? matches.length : 0;
    }
    function lpHeavyEffectCount(){
        return blocks.filter(function(b){ return ['gradient-glow','spotlight','top-wave','bottom-wave'].includes(b.section_effect || ''); }).length;
    }
    function lpPerformanceInsights(){
        const total = blocks.length;
        const imageCount = lpBlockImageCount();
        const heavyEffects = lpHeavyEffectCount();
        const animated = blocks.filter(function(b){ return b.animation_style && !['none','inherit'].includes(b.animation_style); }).length;
        const externalLinks = lpExternalUrlCount();
        const formFields = blocks.reduce(function(sum,b){ return sum + (b.type === 'lead_form' && Array.isArray(b.fields) ? b.fields.length : 0); }, 0);
        const countdownCount = blocks.filter(function(b){ return b.type === 'countdown_timer'; }).length;
        let score = 100;
        if (total > 10) score -= Math.min(20, (total - 10) * 4);
        if (imageCount > 5) score -= Math.min(20, (imageCount - 5) * 4);
        if (heavyEffects > 3) score -= Math.min(15, (heavyEffects - 3) * 5);
        if (animated > 8) score -= Math.min(12, (animated - 8) * 2);
        if (externalLinks > 8) score -= Math.min(12, (externalLinks - 8) * 2);
        if (formFields > 7) score -= Math.min(10, (formFields - 7) * 2);
        if (countdownCount > 1) score -= 6;
        score = Math.max(45, Math.min(100, score));
        const recs = [];
        if (total > 10) recs.push('Jumlah blok cukup banyak. Untuk LP iklan, prioritaskan section yang benar-benar membantu keputusan calon pembeli.');
        if (imageCount > 5) recs.push('Gambar cukup banyak. Gunakan WebP, ukuran ringan, dan hanya tampilkan visual yang benar-benar mendukung konversi.');
        if (heavyEffects > 3) recs.push('Efek visual berat sebaiknya maksimal 1–3 section penting agar LP tetap terasa cepat.');
        if (animated > 8) recs.push('Animasi terlalu banyak bisa terasa ramai. Matikan animasi di section yang tidak terlalu penting.');
        if (externalLinks > 8) recs.push('Banyak link eksternal bisa memperlambat tracking/redirect. Pastikan hanya link penting yang dipakai.');
        if (formFields > 7) recs.push('Form cukup panjang. Untuk traffic iklan, field utama sebaiknya nama, WhatsApp, kebutuhan, dan catatan.');
        if (countdownCount > 1) recs.push('Cukup gunakan 1 countdown utama agar urgensi tidak terasa berlebihan.');
        if (!countdownCount) recs.push('Untuk promo/event terbatas, tambahkan blok Countdown agar pengunjung punya alasan action sekarang.');
        if (!recs.length) recs.push('Struktur LP masih ringan: blok, gambar, efek, animasi, dan form masih aman untuk campaign iklan.');
        return {score,total,imageCount,heavyEffects,animated,externalLinks,formFields,countdownCount,recs};
    }
    function renderPerformanceOptimizer(){
        if (!performanceOptimizerPanel) return;
        const p = lpPerformanceInsights();
        const tone = p.score >= 85 ? 'is-good' : (p.score >= 70 ? 'is-mid' : 'is-low');
        const rows = [
            {label:'Blok', value:p.total, ok:p.total <= 10, hint:'Ideal 5–10 blok untuk LP iklan.'},
            {label:'Gambar', value:p.imageCount, ok:p.imageCount <= 5, hint:'Gunakan WebP dan lazy load.'},
            {label:'Efek berat', value:p.heavyEffects, ok:p.heavyEffects <= 3, hint:'Gradient/spotlight/wave secukupnya.'},
            {label:'Animasi', value:p.animated, ok:p.animated <= 8, hint:'Animasi ringan dan tidak berlebihan.'},
            {label:'Link eksternal', value:p.externalLinks, ok:p.externalLinks <= 8, hint:'Kurangi redirect yang tidak perlu.'},
            {label:'Field form', value:p.formFields, ok:p.formFields <= 7, hint:'Form pendek lebih enak untuk iklan.'},
            {label:'Countdown', value:p.countdownCount, ok:p.countdownCount <= 1, hint:'Satu timer utama sudah cukup.'}
        ];
        performanceOptimizerPanel.innerHTML = `<div class="lpw-speed-score ${tone}"><strong>${p.score}%</strong><div><b>Performance readiness</b><small>Ringan · cepat · cocok iklan</small></div></div><div class="lpw-speed-metrics">${rows.map(function(row){ return `<span class="${row.ok ? 'is-ok' : 'is-warning'}"><b>${escapeHtml(row.value)}</b><small>${escapeHtml(row.label)}</small><em>${escapeHtml(row.hint)}</em></span>`; }).join('')}</div><div class="lpw-engine-reco"><strong>Rekomendasi speed</strong><ul>${p.recs.slice(0,5).map(function(item){ return `<li>${escapeHtml(item)}</li>`; }).join('')}</ul></div><div class="lpw-helper-guide lpw-helper-guide--compact"><strong>Catatan performa</strong><p>Script builder, template gallery, undo/redo, dan panel admin tidak ikut dimuat di halaman publik. Countdown memakai JavaScript kecil hanya saat blok countdown ada.</p></div>`;
    }

    function renderSistemPanels(){ renderHealth(); renderPublishChecklist(); renderPerformance(); renderPerformanceOptimizer(); renderBuyerViewAdvisor(); renderSeoSistem(); renderTrackingInspector(); }

    function aiFieldValue(name){
        const el = document.querySelector('[data-ai-field="' + name + '"]');
        return el ? String(el.value || '').trim() : '';
    }
    function aiTitleCase(value){
        return String(value || '').replace(/\s+/g, ' ').trim().replace(/\b\w/g, function(c){ return c.toUpperCase(); });
    }
    function aiInferSegment(text){
        const haystack = String(text || '').toLowerCase();
        const segments = aiAssistantSeed.segments || {};
        let bestKey = 'promo';
        let bestScore = -1;
        Object.keys(segments).forEach(function(key){
            const seg = segments[key] || {};
            const words = [key, seg.label || ''].concat(seg.keywords || []);
            const score = words.reduce(function(total, word){ return total + (word && haystack.indexOf(String(word).toLowerCase()) >= 0 ? 1 : 0); }, 0);
            if (score > bestScore) { bestScore = score; bestKey = key; }
        });
        return segments[bestKey] || segments.promo || {label:'Campaign', keywords:[], pain_points:[], benefits:[]};
    }
    function aiContext(){
        readAll();
        const currentTitle = formValue('title') || (blocks.find(function(block){ return block && block.type === 'hero_offer' && block.headline; }) || {}).headline || '';
        const product = aiFieldValue('product') || currentTitle || 'Paket Layanan dan Produk';
        const location = aiFieldValue('location') || (String(currentTitle).match(/Jakarta|Bandung|Surabaya|Yogyakarta|Semarang|Depok|Tangerang Selatan|Bali/i) || ['Online'])[0];
        const keyword = aiFieldValue('keyword') || formValue('meta_keywords').split(',')[0].trim() || (product + ' ' + location).toLowerCase();
        const offer = aiFieldValue('offer') || 'konsultasi cepat, admin bantu pilih paket, dan follow-up lebih rapi';
        const tone = aiFieldValue('tone') || 'friendly';
        const segment = aiInferSegment([product, location, keyword, offer, currentTitle].join(' '));
        return {product:product, location:location, keyword:keyword, offer:offer, tone:tone, segment:segment};
    }
    function aiPhrase(ctx){
        const local = ctx.location ? ' di ' + ctx.location : '';
        if (ctx.tone === 'premium') return {headline:'Pilih ' + ctx.product + local + ' dengan proses yang lebih tenang dan tepercaya', cta:'Konsultasi Paket Terbaik', angle:'pilihan lebih jelas, proses rapi, dan admin siap membantu dari awal'};
        if (ctx.tone === 'urgent') return {headline:'Cek ' + ctx.product + local + ' sebelum jadwal dan stok berubah', cta:'Cek Ketersediaan Sekarang', angle:'stok, jadwal, dan slot layanan bisa berubah, jadi lebih aman konsultasi lebih awal'};
        if (ctx.tone === 'direct') return {headline:ctx.product + local + ' siap dibantu admin dari konsultasi sampai follow-up', cta:'Chat Admin Sekarang', angle:'langsung tanya kebutuhan, cek rekomendasi, lalu lanjutkan lewat WhatsApp atau form'};
        if (ctx.tone === 'local') return {headline:ctx.product + local + ' untuk customer yang butuh arahan cepat', cta:'Cek Area Layanan', angle:'konten dan follow-up dibuat relevan dengan lokasi agar calon customer lebih mudah mengambil langkah'};
        return {headline:'Bingung pilih ' + ctx.product + local + '? Mulai dari konsultasi dulu', cta:'Minta Rekomendasi Paket', angle:'calon customer bisa bertanya dulu, admin membantu cek kebutuhan, budget, dan langkah berikutnya'};
    }
    function aiSeoPack(ctx){
        const phrase = aiPhrase(ctx);
        const product = aiTitleCase(ctx.product);
        const location = aiTitleCase(ctx.location);
        const title = (product + (location ? ' ' + location : '') + ' - Konsultasi Cepat & Rekomendasi Paket').slice(0, 68);
        const desc = ('Butuh ' + ctx.product + (ctx.location ? ' area ' + ctx.location : '') + '? Konsultasi cepat untuk cek pilihan paket, stok, lokasi, budget, dan follow-up admin yang lebih rapi.').slice(0, 158);
        const baseKeywords = [ctx.keyword, ctx.product, ctx.product + ' ' + ctx.location, 'konsultasi ' + ctx.product, 'paket ' + ctx.product].map(function(v){ return String(v || '').toLowerCase().replace(/\s+/g, ' ').trim(); }).filter(Boolean);
        return {title:title, description:desc, keywords:Array.from(new Set(baseKeywords)).slice(0, 8).join(', '), slug:slugify(ctx.keyword || (ctx.product + ' ' + ctx.location)), tracking:'Landing Page ' + product + (location ? ' ' + location : ''), headline:phrase.headline};
    }
    function aiBlockIndex(type){
        return blocks.findIndex(function(block){ return block && block.type === type; });
    }
    function aiUpsertBlock(type, block){
        const index = aiBlockIndex(type);
        if (index >= 0) blocks[index] = Object.assign({}, blocks[index] || {}, block);
        else blocks.push(block);
    }
    function aiBuildHero(ctx){
        const phrase = aiPhrase(ctx);
        return {type:'hero_offer', eyebrow:ctx.segment.label || 'Landing Page', headline:phrase.headline, subheadline:'Isi form atau klik WhatsApp agar admin bisa bantu cek kebutuhan, lokasi, budget, dan rekomendasi yang paling relevan. ' + phrase.angle + '.', image:'<?= esc(asset('images/placeholder-product.svg')); ?>', image_alt:ctx.product + ' ' + ctx.location, primary_text:phrase.cta, primary_url:'#form-konsultasi', secondary_text:'Lihat Detail', secondary_url:'#detail', hero_layout:'auto', hero_position:'right', bg_color:'#eff6ff', headline_size:'44px'};
    }
    function aiBuildCta(ctx){
        const phrase = aiPhrase(ctx);
        return {type:'cta', headline:'Siap dibantu pilih ' + ctx.product + '?', text:'Klik tombol atau isi form agar admin bisa follow-up sesuai kebutuhan dan lokasi Anda.', button_text:phrase.cta, button_url:'#form-konsultasi', bg_color:'#1e3a8a', text_color:'#ffffff', align:'center'};
    }
    function aiBuildForm(ctx){
        return {type:'lead_form', headline:'Isi Form Konsultasi ' + aiTitleCase(ctx.product), text:'Lengkapi data singkat ini agar admin bisa membantu cek kebutuhan, budget, lokasi, dan rekomendasi paket.', submit_text:'Kirim Konsultasi', success_text:'Terima kasih, data konsultasi sudah masuk. Admin akan follow-up.', need_default:'Konsultasi ' + ctx.product, mailketing_list_id:'', form_name:'Form ' + aiTitleCase(ctx.product), lead_segment:slugify(ctx.keyword || ctx.product).replace(/-/g, '_') || 'lp_consultation', lead_tags:[slugify(ctx.product), slugify(ctx.location), 'konten-ai'].filter(Boolean).join(','), lead_priority:'warm', lead_stage:'new-lead', lead_score:'65', consent_text:'Saya bersedia dihubungi admin terkait konsultasi ini.', fields:[{name:'name', label:'Nama Lengkap', type:'text', required:true, placeholder:'Nama Anda', options:[]},{name:'phone', label:'Nomor WhatsApp', type:'tel', required:true, placeholder:'08xxxxxxxxxx', options:[]},{name:'location', label:'Lokasi / Area', type:'text', required:false, placeholder:ctx.location || 'Area layanan', options:[]},{name:'need', label:'Kebutuhan', type:'select', required:true, placeholder:'', options:['Konsultasi','Tanya Harga','Cek Stok','Order']},{name:'budget_range', label:'Range Budget', type:'radio', required:false, placeholder:'', options:['Ekonomis','Medium','Premium']},{name:'message', label:'Catatan Tambahan', type:'textarea', required:false, placeholder:'Tulis kebutuhan/jadwal di sini', options:[]}]};
    }
    function aiBuildFaq(ctx){
        const product = ctx.product || 'layanan';
        return {type:'faq', headline:'FAQ ' + aiTitleCase(product), items:[{question:'Apakah bisa konsultasi ' + product + ' dulu?', answer:'Bisa. Admin akan membantu menjelaskan pilihan paket, stok, lokasi, dan langkah berikutnya sesuai kebutuhan Anda.'},{question:'Apakah wajib langsung order setelah isi form?', answer:'Tidak. Form ini dipakai untuk konsultasi awal agar admin bisa follow-up lebih terarah.'},{question:'Apakah bisa cek area layanan ' + (ctx.location || 'terdekat') + '?', answer:'Bisa. Tulis lokasi atau area kebutuhan agar admin dapat mengecek opsi yang relevan.'},{question:'Bagaimana cara follow-up dari admin?', answer:'Admin akan menghubungi lewat data yang Anda isi, terutama WhatsApp, dengan informasi yang sesuai kebutuhan.'}]};
    }
    function aiBuildFullPack(ctx){
        const seg = ctx.segment || {};
        const pain = Array.isArray(seg.pain_points) && seg.pain_points.length ? seg.pain_points : ['Bingung memilih paket yang tepat.', 'Butuh respon admin yang cepat.', 'Ingin proses lebih praktis.'];
        const benefits = Array.isArray(seg.benefits) && seg.benefits.length ? seg.benefits : ['Admin membantu memilih paket.', 'Follow-up lebih rapi.', 'Tombol dan form siap tracking.'];
        return [
            aiBuildHero(ctx),
            {type:'pain_points', headline:'Biasanya calon customer bingung di bagian ini', items:pain},
            {type:'benefits', headline:'Kenapa konsultasi lewat halaman ini?', items:benefits.map(function(text, index){ return {title:['Arahan lebih jelas','Follow-up lebih rapi','Siap untuk promosi'][index] || 'Benefit', text:text}; })},
            {type:'pricing_cards', headline:'Pilih kebutuhan awal Anda', button_url:'#form-konsultasi', items:[{title:'Ekonomis', price:'Mulai konsultasi', features:['Cocok untuk cek kebutuhan awal','Admin bantu arahkan opsi hemat'], button_text:'Tanya Ekonomis'},{title:'Medium', price:'Paling populer', features:['Pilihan seimbang untuk keluarga/kelompok','Rekomendasi lebih terarah'], button_text:'Tanya Medium'},{title:'Premium', price:'Pilihan terbaik', features:['Untuk kebutuhan lebih spesial','Cek opsi terbaik yang tersedia'], button_text:'Tanya Premium'}]},
            aiBuildForm(ctx),
            aiBuildFaq(ctx),
            aiBuildCta(ctx)
        ];
    }
    function aiSuggestionsHtml(ctx){
        const phrase = aiPhrase(ctx);
        const seo = aiSeoPack(ctx);
        const headlines = [phrase.headline, 'Cara mudah memilih ' + ctx.product + (ctx.location ? ' di ' + ctx.location : '') + ' tanpa bingung', ctx.product + (ctx.location ? ' ' + ctx.location : '') + ' dengan konsultasi cepat dari admin'];
        const ctas = [phrase.cta, 'Chat Admin via WhatsApp', 'Isi Form Konsultasi', 'Cek Paket & Ketersediaan'];
        return '<div class="lpw-ai-result"><strong>Ide Headline</strong><ul>' + headlines.map(function(v){ return '<li>' + escapeHtml(v) + '</li>'; }).join('') + '</ul><strong>Tombol yang bisa dites</strong><ul>' + ctas.map(function(v){ return '<li>' + escapeHtml(v) + '</li>'; }).join('') + '</ul><strong>SEO Pack</strong><p><b>Meta title:</b> ' + escapeHtml(seo.title) + '</p><p><b>Meta description:</b> ' + escapeHtml(seo.description) + '</p><p><b>Keywords:</b> ' + escapeHtml(seo.keywords) + '</p></div>';
    }
    function aiAuditHtml(){
        readAll();
        const pseudoPage = {title:formValue('title'), meta_title:formValue('meta_title'), meta_description:formValue('meta_description'), tracking_label:formValue('tracking_label'), blocks:blocks};
        const hasHero = aiBlockIndex('hero_offer') >= 0;
        const hasForm = aiBlockIndex('lead_form') >= 0;
        const hasCta = aiBlockIndex('cta') >= 0;
        const hasFaq = aiBlockIndex('faq') >= 0;
        const metaLength = String(pseudoPage.meta_description || '').trim().length;
        const checks = [
            {ok:!!pseudoPage.title, text:'Judul landing page terisi'},
            {ok:!!pseudoPage.meta_title, text:'Meta title terisi'},
            {ok:metaLength >= 90 && metaLength <= 160, text:'Meta description ideal 90-160 karakter'},
            {ok:hasHero, text:'Hero offer tersedia'},
            {ok:hasForm, text:'Form lead tersedia'},
            {ok:hasCta, text:'Tombol penutup tersedia'},
            {ok:hasFaq, text:'FAQ SEO tersedia'},
            {ok:!!pseudoPage.tracking_label, text:'Tracking label terisi'}
        ];
        const score = Math.round(checks.filter(function(item){ return item.ok; }).length / checks.length * 100);
        return '<div class="lpw-ai-result"><strong>Audit Copy & SEO: ' + score + '%</strong><ul>' + checks.map(function(item){ return '<li class="' + (item.ok ? 'is-ok' : 'is-warn') + '">' + (item.ok ? '✓ ' : '• ') + escapeHtml(item.text) + '</li>'; }).join('') + '</ul></div>';
    }
    function renderAiAssistantOutput(html){
        if (aiAssistantOutput) aiAssistantOutput.innerHTML = html;
    }
    function aiRunAction(action){
        const ctx = aiContext();
        const seo = aiSeoPack(ctx);
        if (action === 'suggest') {
            renderAiAssistantOutput(aiSuggestionsHtml(ctx));
            return;
        }
        if (action === 'audit') {
            renderAiAssistantOutput(aiAuditHtml());
            return;
        }
        readAll();
        if (action === 'seo') {
            if (!formValue('title')) setFormValue('title', aiTitleCase(ctx.product) + (ctx.location ? ' ' + aiTitleCase(ctx.location) : ''));
            if (!formValue('slug')) setFormValue('slug', seo.slug);
            setFormValue('tracking_label', seo.tracking);
            setFormValue('meta_title', seo.title);
            setFormValue('meta_description', seo.description);
            setFormValue('meta_keywords', seo.keywords);
            renderAiAssistantOutput('<div class="lpw-ai-result"><strong>SEO Pack sudah diisi.</strong><p>Cek kembali meta title, description, keyword, dan slug sebelum publish.</p></div>');
        }
        if (action === 'hero') {
            aiUpsertBlock('hero_offer', aiBuildHero(ctx));
            renderAiAssistantOutput('<div class="lpw-ai-result"><strong>Hero copy sudah dibuat/update.</strong><p>Preview kanan sudah disinkronkan.</p></div>');
        }
        if (action === 'faq') {
            aiUpsertBlock('faq', aiBuildFaq(ctx));
            renderAiAssistantOutput('<div class="lpw-ai-result"><strong>FAQ SEO sudah dibuat/update.</strong><p>FAQ membantu menjawab objection dan memperkuat struktur konten.</p></div>');
        }
        if (action === 'full') {
            if (!window.confirm('Buat Full Landing Page Pack dari assistant? Struktur blok yang ada akan diganti dengan paket baru berbasis input assistant.')) return;
            blocks = aiBuildFullPack(ctx);
            if (!formValue('title')) setFormValue('title', aiTitleCase(ctx.product) + (ctx.location ? ' ' + aiTitleCase(ctx.location) : ''));
            if (!formValue('slug')) setFormValue('slug', seo.slug);
            setFormValue('tracking_label', seo.tracking);
            setFormValue('meta_title', seo.title);
            setFormValue('meta_description', seo.description);
            setFormValue('meta_keywords', seo.keywords);
            renderAiAssistantOutput('<div class="lpw-ai-result"><strong>Full Landing Page Pack sudah dibuat.</strong><p>Berisi hero, pain point, benefit, paket, form, FAQ, dan Tombol penutup.</p></div>');
        }
        render();
        captureHistory(true);
        markDirty();
        setAutosaveStatus('AI Assistant menerapkan perubahan · belum disimpan', true);
    }
    function updatePreviewStatus(){
        if (!previewStatus) return;
        const stamp = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        previewStatus.textContent = `${hasUnsavedChanges ? 'Preview sinkron · belum disimpan' : 'Preview sinkron'} · ${stamp}`;
        previewStatus.classList.toggle('is-dirty', hasUnsavedChanges);
    }
    function padCountdownUnit(value){
        return String(Math.max(0, Number(value) || 0)).padStart(2, '0');
    }
    function parseCountdownDeadline(value){
        const raw = String(value || '').trim();
        if (!raw) return null;
        const parsed = new Date(raw);
        if (!Number.isNaN(parsed.getTime())) return parsed;
        const normalized = raw.replace(' ', 'T');
        const fallback = new Date(normalized);
        return Number.isNaN(fallback.getTime()) ? null : fallback;
    }
    function updatePreviewCountdowns(){
        if (!preview) return;
        preview.querySelectorAll('[data-lpw-preview-countdown]').forEach(function(timer){
            const deadline = parseCountdownDeadline(timer.dataset.deadline || '');
            const expiredText = timer.dataset.expiredText || 'Promo sudah berakhir.';
            const expiredBox = timer.querySelector('[data-lpw-countdown-expired]');
            if (!deadline) {
                timer.querySelectorAll('[data-lpw-countdown-unit]').forEach(function(unit){ unit.textContent = '00'; });
                if (expiredBox) { expiredBox.hidden = true; expiredBox.textContent = ''; }
                timer.classList.remove('is-expired');
                return;
            }
            const diff = deadline.getTime() - Date.now();
            const done = diff <= 0;
            const totalSeconds = Math.max(0, Math.floor(diff / 1000));
            const map = {
                days: Math.floor(totalSeconds / 86400),
                hours: Math.floor((totalSeconds % 86400) / 3600),
                minutes: Math.floor((totalSeconds % 3600) / 60),
                seconds: totalSeconds % 60
            };
            Object.keys(map).forEach(function(key){
                const el = timer.querySelector('[data-lpw-countdown-' + key + ']');
                if (el) el.textContent = padCountdownUnit(map[key]);
            });
            timer.classList.toggle('is-expired', done);
            if (expiredBox) {
                expiredBox.hidden = !done;
                expiredBox.textContent = done ? expiredText : '';
            }
        });
    }
    function startPreviewCountdownRuntime(){
        if (previewCountdownTimer) return;
        previewCountdownTimer = window.setInterval(updatePreviewCountdowns, 1000);
    }
    function markDirty(){
        hasUnsavedChanges = true;
        updatePreviewStatus();
        renderSistemPanels();
        scheduleHistoryCapture();
        scheduleAutosave();
    }
    function previewFieldOptions(field){
        const options = Array.isArray(field.options) ? field.options : [];
        if (!['select','radio','checkbox'].includes(field.type || '') || !options.length) return '';
        return `<div class="lpw-pv-field-options">${options.slice(0,4).map(opt=>`<i>${escapeHtml(opt)}</i>`).join('')}</div>`;
    }
    function pvHeading(text, hStyle, tag){
        const safe = escapeHtml(text || '');
        const safeTag = ['h1','h2','h3'].includes(tag) ? tag : 'h2';
        return safe ? `<${safeTag}${hStyle || ''}>${safe}</${safeTag}>` : '';
    }
    function pvParagraph(text, pStyle){
        const safe = escapeHtml(text || '');
        return safe ? `<p${pStyle || ''}>${safe}</p>` : '';
    }
    function pvEyebrow(block){
        const safe = escapeHtml(block && block.eyebrow ? block.eyebrow : '');
        return safe ? `<small>${safe}</small>` : '';
    }
    function pvHead(block, hStyle, pStyle, tag, textValue){
        const eyebrow = pvEyebrow(block);
        const heading = pvHeading(block && block.headline ? block.headline : '', hStyle, tag || 'h2');
        const desc = pvParagraph(textValue !== undefined ? textValue : (block && block.text ? block.text : ''), pStyle);
        return (eyebrow || heading || desc) ? `${eyebrow}${heading}${desc}` : '';
    }
    function heroPreviewHtml(block, sectionOpen, h, hStyle, pStyle){
        const layout = block.hero_layout || 'auto';
        const position = block.hero_position || 'right';
        const hasImage = hasText(block.image);
        const hasCopy = hasText(block.eyebrow) || hasText(block.headline) || hasText(block.subheadline) || hasText(block.primary_text) || hasText(block.secondary_text);
        let showImage = hasImage && layout !== 'text_only';
        let showCopy = hasCopy && layout !== 'media_only';
        if (!showImage && !showCopy) showCopy = true;
        const modeClass = (showImage && showCopy) ? 'split' : (showImage ? 'media-only' : 'text-only');
        const posClass = (modeClass === 'split' && position === 'right') ? ' lpw-pv-hero-flex--media-right' : '';
        const actions = (block.primary_text || block.secondary_text) ? `<div class="lpw-pv-actions"${actionStyleAttr(block)}>${block.primary_text ? button(block.primary_text) : ''} ${block.secondary_text ? button(block.secondary_text) : ''}</div>` : '';
        const head = pvHead(block, hStyle, pStyle, 'h1', block.subheadline || '');
        const copyHtml = showCopy ? `<div class="lpw-pv-hero-copy">${head}${actions}</div>` : '';
        const imageHtml = showImage ? `<div class="lpw-pv-media"><img src="${escapeHtml(block.image)}" alt="${escapeHtml(block.image_alt || block.headline || 'Hero')}"></div>` : '';
        return `${sectionOpen}<div class="lpw-pv-hero-flex lpw-pv-hero-flex--${modeClass}${posClass}">${imageHtml}${copyHtml}</div></section>`;
    }
    function mediaPreviewHtml(block, sectionOpen, h, hStyle, pStyle){
        const layout = block.media_layout || 'auto';
        const position = block.media_position || 'left';
        const hasImage = hasText(block.image);
        const hasCopy = hasText(block.headline) || hasText(block.text) || hasText(block.button_text);
        const showImage = hasImage && layout !== 'text_only';
        const showCopy = hasCopy && layout !== 'media_only';
        if (!showImage && !showCopy) return `${sectionOpen}<div class="lpw-preview-empty">Media kosong. Isi gambar atau teks penjelasan.</div></section>`;
        const modeClass = (showImage && showCopy) ? 'split' : (showImage ? 'media-only' : 'text-only');
        const posClass = (modeClass === 'split' && position === 'right') ? ' lpw-pv-media-block--media-right' : '';
        const imageHtml = showImage ? `<img src="${escapeHtml(block.image)}" alt="${escapeHtml(block.image_alt || block.headline || 'Media')}">` : '';
        const copyHtml = showCopy ? `<div class="lpw-pv-media-copy">${pvHead(block, hStyle, pStyle, 'h2', block.text || '')}${actionWrap(block, block.button_text?button(block.button_text):'')}</div>` : '';
        return `${sectionOpen}<div class="lpw-pv-media-block lpw-pv-media-block--${modeClass}${posClass}">${imageHtml}${copyHtml}</div></section>`;
    }
    function previewBlock(block, index){
        const h = escapeHtml(block.headline || '');
        const pStyle = paragraphStyle(block);
        const hStyle = titleStyle(block);
        const selectedClass = index === selectedBlockIndex ? ' is-selected-preview' : '';
        const sectionOpen = `<section class="lpw-pv-section lpw-pv-${escapeHtml(block.type || 'text')}${selectedClass}" data-preview-block="${index}" tabindex="0" role="button" aria-label="Pilih blok ${index + 1}"${styleAttr(block)}>`;
        if (block.type === 'hero_offer') return heroPreviewHtml(block, sectionOpen, h, hStyle, pStyle);
        if (block.type === 'pain_points') { const items = cleanPreviewItems(block.items); return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}<div class="lpw-pv-cards"${repeatGridStyle(items,3)}>${items.map(i=>`<article${itemStyle(i)}><strong>✕</strong><p${pStyle}>${escapeHtml(typeof i==='string'?i:(i.text||i.title||''))}</p></article>`).join('')}</div></section>`; }
        if (block.type === 'benefits' || block.type === 'product_highlight' || block.type === 'testimonial') { const items = cleanPreviewItems(block.items); return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}<div class="lpw-pv-cards"${repeatGridStyle(items,3)}>${items.map(i=>`<article${itemStyle(i)}>${(i&&i.image)?`<img src="${escapeHtml(i.image)}" alt="">`:''}${(typeof i==='string'?i:(i.title||''))?`<h3>${escapeHtml(typeof i==='string'?i:(i.title||''))}</h3>`:''}${(typeof i==='string'?'':(i.text||i.answer||''))?`<p${pStyle}>${escapeHtml(typeof i==='string'?'':(i.text||i.answer||''))}</p>`:''}</article>`).join('')}</div></section>`; }
        if (block.type === 'pricing_cards') { const items = cleanPreviewItems(block.items); return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}<div class="lpw-pv-price-grid"${repeatGridStyle(items,3)}>${items.map(i=>`<article${itemStyle(i)}>${i.title?`<h3>${escapeHtml(i.title)}</h3>`:''}${i.price?`<strong>${escapeHtml(i.price)}</strong>`:''}<ul>${(i.features||[]).map(f=>{ const t=typeof f==='string'?f:(f.title||f.text||''); return t?`<li>${escapeHtml(t)}</li>`:''; }).join('')}</ul>${i.button_text?actionWrap(block, button(i.button_text)):''}</article>`).join('')}</div></section>`; }
        if (block.type === 'custom_menu') {
            const items = cleanPreviewItems(block.items);
            const menuStyle = block.menu_style || (block.engine_internal_links ? 'cards' : 'header');
            const menuPosition = ['normal','sticky','fixed'].includes(block.menu_position || '') ? block.menu_position : 'normal';
            const menuAlign = ['left','center','right'].includes(block.menu_align || '') ? block.menu_align : 'center';
            const logoAlign = ['left','center','right'].includes(block.logo_align || '') ? block.logo_align : 'left';
            if (menuStyle === 'header') {
                const headerSectionOpen = `<section class="lpw-pv-section lpw-pv-custom_menu lpw-pv-custom-menu-header lpw-pv-custom-menu--${escapeHtml(menuPosition)}${selectedClass}" data-preview-block="${index}" tabindex="0" role="button" aria-label="Pilih blok ${index + 1}" data-lp-menu-style="header" data-lp-menu-position="${escapeHtml(menuPosition)}" data-lp-menu-align="${escapeHtml(menuAlign)}" data-lp-logo-align="${escapeHtml(logoAlign)}"${styleAttr(block)}>`;
                const logoItems = [];
                const linkItems = [];
                items.forEach(function(i){
                    const itemType = (i.item_type === 'logo' || (!i.title && i.image)) ? 'logo' : 'link';
                    const cls = itemType === 'logo' ? 'lpw-pv-menu-link lpw-pv-menu-logo lpw-pv-menu-logo--brand' : 'lpw-pv-menu-link';
                    const html = `<a class="${cls}" href="${escapeHtml(i.url||i.link_url||'#')}"${itemStyle(i)}>${i.image?`<img src="${escapeHtml(i.image)}" alt="${escapeHtml(i.image_alt||i.title||'Logo menu')}">`:''}${itemType === 'logo' ? (i.image?'':`<span>${escapeHtml(i.title||'Logo')}</span>`) : `<span>${escapeHtml(i.title||'Menu')}</span>`}</a>`;
                    if (itemType === 'logo') logoItems.push(html); else linkItems.push(html);
                });
                return `${headerSectionOpen}<nav class="lpw-pv-menu-header lpw-pv-menu-header--structured" aria-label="Preview menu khusus landing page">${logoItems.length?`<div class="lpw-pv-menu-logo-slot">${logoItems.join('')}</div>`:''}${linkItems.length?`<div class="lpw-pv-menu-links">${linkItems.join('')}</div>`:''}</nav></section>`;
            }
            return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}<div class="lpw-pv-menu">${items.map(i=>`<span${itemStyle(i)}>${i.image?`<img src="${escapeHtml(i.image)}" alt="${escapeHtml(i.image_alt||i.title||'Logo menu')}">`:''}${i.title?`<b>${escapeHtml(i.title)}</b>`:''}${(i.text||i.url)?`<small>${escapeHtml(i.text||i.url||'')}</small>`:''}</span>`).join('')}</div></section>`;
        }

        if (block.type === 'media') return mediaPreviewHtml(block, sectionOpen, h, hStyle, pStyle);
        if (block.type === 'free_cards') { const items = cleanPreviewItems(block.items); return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}<div class="lpw-pv-cards"${repeatGridStyle(items,3)}>${items.map(i=>`<article${itemStyle(i)}>${(i&&i.image)?`<img src="${escapeHtml(i.image)}" alt="">`:''}${i.title?`<h3>${escapeHtml(i.title)}</h3>`:''}${i.text?`<p${pStyle}>${escapeHtml(i.text||'')}</p>`:''}</article>`).join('')}</div></section>`; }
        if (block.type === 'lead_form') { if (block.form_source === 'custom_form') { const picked = activeCustomForms.find(f => f.slug === block.custom_form_slug); return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}<div class="lpw-pv-form"><label>${escapeHtml(picked ? picked.title : 'Pilih Form Custom')}<span>${picked ? (picked.fields + ' field dari Form Custom') : 'Belum ada form dipilih'}</span></label>${button(picked ? 'Render form terpilih saat live' : 'Pilih Form')}</div></section>`; } const fields = Array.isArray(block.fields) ? block.fields : []; return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}${block.lead_segment?`<div class="lpw-pv-segment">Segment: ${escapeHtml(block.lead_segment)}</div>`:''}<div class="lpw-pv-form">${fields.map(f=>`<label>${escapeHtml(f.label||f.name||'Field')}<span>${escapeHtml(f.type||'text')}${f.required?' · wajib':' · opsional'}</span>${previewFieldOptions(f)}</label>`).join('')} ${button(block.submit_text||'Kirim Form')}</div></section>`; }
        if (block.type === 'faq') { const items = cleanPreviewItems(block.items); return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}${items.map(i=>{ const q=i.question||i.title||''; const a=i.answer||i.text||''; return (q||a)?`<details open${itemStyle(i)}>${q?`<summary>${escapeHtml(q)}</summary>`:''}${a?`<p${pStyle}>${escapeHtml(a)}</p>`:''}</details>`:''; }).join('')}</section>`; }
        if (block.type === 'countdown_timer') return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}<div class="lpw-pv-countdown" data-lpw-preview-countdown="1" data-deadline="${escapeHtml(block.countdown_deadline || '')}" data-expired-text="${escapeHtml(block.expired_text || 'Promo sudah berakhir.')}"><span><b data-lpw-countdown-days data-lpw-countdown-unit>00</b><small>Hari</small></span><span><b data-lpw-countdown-hours data-lpw-countdown-unit>00</b><small>Jam</small></span><span><b data-lpw-countdown-minutes data-lpw-countdown-unit>00</b><small>Menit</small></span><span><b data-lpw-countdown-seconds data-lpw-countdown-unit>00</b><small>Detik</small></span></div>${block.countdown_deadline?`<p class="lpw-pv-countdown-note">Deadline: ${escapeHtml(block.countdown_deadline)} ${escapeHtml(block.countdown_timezone||'')}</p>`:''}<p class="lpw-pv-countdown-expired" data-lpw-countdown-expired hidden></p>${actionWrap(block, block.button_text?button(block.button_text):'')}</section>`;
        if (block.type === 'cta') return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}${actionWrap(block, block.button_text?button(block.button_text):'')}</section>`;
        if (block.type === 'html_block') return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}<div class="lpw-pv-html-block">${sanitizePreviewHtml(block.html || block.text || '')}</div>${block.complex_widgets?`<p class="lpw-pv-note">Widget kompleks: ${escapeHtml(block.complex_widgets)}</p>`:''}</section>`;
        return `${sectionOpen}${pvHead(block,hStyle,pStyle,'h2',block.text||'')}</section>`;
    }
    function renderPreview(){
        if (!preview) return;
        if (fullHtmlModeActive() && rawHtmlDocumentValue().trim() !== '') {
            preview.innerHTML = `<div class="lpw-preview-full-html" data-preview-full-html="1"><div class="lpw-preview-full-html-note"><strong>Full HTML Expert Mode</strong><span>Preview disanitasi. Script/iframe/event handler tidak ditampilkan.</span></div>${sanitizePreviewHtml(rawHtmlDocumentValue())}</div>`;
            updatePreviewCountdowns();
            renderSistemPanels();
            updatePreviewStatus();
            return;
        }
        const lpMotionEnabled = form && form.querySelector('[name="motion_enabled"]') ? form.querySelector('[name="motion_enabled"]').checked : true;
        const lpMotionStyle = formValue('motion_style') || 'fade-up';
        preview.classList.toggle('landing-motion-preview-enabled', !!lpMotionEnabled);
        preview.classList.remove('landing-motion-preview-fade-up','landing-motion-preview-zoom-soft','landing-motion-preview-fade');
        preview.classList.add('landing-motion-preview-' + (['fade-up','zoom-soft','fade'].includes(lpMotionStyle) ? lpMotionStyle : 'fade-up'));
        preview.innerHTML = blocks.map(previewBlock).join('') || '<div class="lpw-preview-empty">Belum ada blok.</div>';
        updatePreviewCountdowns();
        renderSistemPanels();
        updatePreviewStatus();
    }
    if (preview) {
        preview.addEventListener('click', function(e){
            const blockEl = e.target.closest('[data-preview-block]');
            if (!blockEl) return;
            e.preventDefault();
            selectBlock(Number(blockEl.dataset.previewBlock || 0));
        });
        preview.addEventListener('keydown', function(e){
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const blockEl = e.target.closest('[data-preview-block]');
            if (!blockEl) return;
            e.preventDefault();
            selectBlock(Number(blockEl.dataset.previewBlock || 0));
        });
    }
    function renderBlockNavigator(){
        if (!blocks.length) return '';
        return `<div class="lpw-selected-block-nav" aria-label="Daftar blok landing page">${blocks.map(function(block,index){
            const label = typeLabels[block.type] || 'Blok';
            const status = blockStatus(block);
            return `<button type="button" data-select-block="${index}" class="${index===selectedBlockIndex?'is-active':''}"><span>${index+1}</span><strong>${escapeHtml(label)}</strong><small>${escapeHtml(block.headline || block.text || 'Klik untuk edit')}</small><em class="lpw-block-status ${status.cls}">${escapeHtml(status.label)}</em></button>`;
        }).join('')}</div>`;
    }
    function clampSelectedBlock(){
        if (!blocks.length) { selectedBlockIndex = 0; return; }
        selectedBlockIndex = Math.max(0, Math.min(selectedBlockIndex, blocks.length - 1));
    }
    function selectBlock(index){
        readAll();
        selectedBlockIndex = Math.max(0, Math.min(Number(index || 0), Math.max(0, blocks.length - 1)));
        render();
        setAutosaveStatus('Block #' + (selectedBlockIndex + 1) + ' dipilih', hasUnsavedChanges);
    }
    function render(){
        clampSelectedBlock();
        if (!blocks.length) {
            editor.innerHTML = '<div class="lpw-empty-blocks"><strong>Belum ada blok.</strong><span>Klik tombol tambah blok di panel Konten atau pilih template dari halaman daftar Landing Page untuk mulai menyusun landing page.</span></div>';
            syncHidden();
            renderPreview();
            return;
        }
        const index = selectedBlockIndex;
        const block = blocks[index] || blocks[0] || defaultBlock('text');
        const label = typeLabels[block.type] || 'Blok';
        const status = blockStatus(block);
        const activeCard = `<article class="lpw-block-card lpw-block-card--active" data-index="${index}"><header><button class="lpw-block-toggle" type="button" aria-label="Buka tutup blok"><span class="lpw-block-number">${index+1}</span><span><strong>${escapeHtml(label)}</strong><em class="lpw-block-status ${status.cls}" title="${escapeHtml(status.hint)}">${escapeHtml(status.label)}</em><small>${escapeHtml(block.headline || block.text || 'Klik untuk edit isi blok')}</small></span></button><div class="lpw-block-actions"><button type="button" data-move="up" ${index===0?'disabled':''}>↑</button><button type="button" data-move="down" ${index===blocks.length-1?'disabled':''}>↓</button><button type="button" data-duplicate="1">Duplikat</button><button type="button" data-delete="1">Hapus</button></div></header><div class="lpw-block-body">${blockFields(block)}</div></article>`;
        editor.innerHTML = renderBlockNavigator() + activeCard;
        syncHidden();
        renderPreview();
    }
    function readCard(card){
        const index = Number(card.dataset.index || 0);
        const block = Object.assign({}, blocks[index] || {});
        card.querySelectorAll('[data-key]').forEach(function(input){
            const key = input.dataset.key;
            if (key === 'items_simple') block.items = linesToItems(input.value, 'simple');
            else if (key === 'items_pair') block.items = linesToItems(input.value, 'pair');
            else if (key === 'items_pricing') block.items = linesToItems(input.value, 'pricing');
            else if (key === 'items_faq') block.items = linesToItems(input.value, 'faq');
            else if (key === 'items_menu') block.items = linesToItems(input.value, 'menu');
            else if (key === 'fields') block.fields = linesToFields(input.value);
            else block[key] = input.value;
        });
        const visualCards = card.querySelector('[data-repeat="cards"]');
        if (visualCards) {
            block.items = readRepeatItems(visualCards);
        }
        const visualFields = card.querySelector('[data-repeat="fields"]');
        if (visualFields) {
            const rows = Array.from(visualFields.querySelectorAll('[data-form-field]'));
            const rawFields = rows.map(function(row, fieldIndex){
                const field = {name:'field_' + (fieldIndex+1), label:'Field ' + (fieldIndex+1), type:'text', required:false, placeholder:'', options:[]};
                row.querySelectorAll('[data-field-prop]').forEach(function(input){
                    const prop = input.dataset.fieldProp;
                    if (prop === 'required') field.required = input.value === '1';
                    else if (prop === 'options') field.options = input.value.split(',').map(function(v){return v.trim();}).filter(Boolean);
                    else field[prop] = input.value;
                });
                return field;
            });
            block.fields = normalizeStoredFormFields(rawFields);
            block.fields.forEach(function(field, idx){
                const nameInput = rows[idx] ? rows[idx].querySelector('[data-field-prop="name"]') : null;
                if (nameInput && nameInput.value !== field.name) nameInput.value = field.name;
            });
        }
        blocks[index] = block;
    }
    function readAll(){ editor.querySelectorAll('.lpw-block-card').forEach(readCard); }
    function syncHidden(){ if (hidden) hidden.value = JSON.stringify(blocks); }
    const liveInputSelector = '[data-key], [data-item-field], [data-field-prop]';
    editor.addEventListener('input', function(e){ if (e.target && e.target.matches(liveInputSelector)) { readAll(); syncHidden(); renderPreview(); markDirty(); } });
    editor.addEventListener('change', function(e){ if (e.target && e.target.matches(liveInputSelector)) { readAll(); syncHidden(); renderPreview(); markDirty(); } });
    if (form) {
        form.addEventListener('input', function(e){
            if (!e.target || !e.target.name || e.target.closest('#lpBlocksEditor')) return;
            if (['blocks_json','action','password'].includes(e.target.name)) return;
            renderPreview(); renderSistemPanels(); updatePreviewStatus(); markDirty();
        });
        form.addEventListener('change', function(e){
            if (!e.target || !e.target.name || e.target.closest('#lpBlocksEditor')) return;
            if (['blocks_json','action','password'].includes(e.target.name)) return;
            renderPreview(); renderSistemPanels(); updatePreviewStatus(); markDirty();
        });
    }
    editor.addEventListener('click', function(e){
        const btn = e.target.closest('button'); if (!btn) return;
        if (btn.dataset.selectBlock !== undefined) { selectBlock(Number(btn.dataset.selectBlock || 0)); return; }
        const card = e.target.closest('.lpw-block-card'); if (!card) return;
        if (btn.classList.contains('lpw-block-toggle')) { card.classList.toggle('is-collapsed'); return; }
        const repeatCard = btn.closest('[data-repeat-card]');
        const repeatBox = btn.closest('[data-repeat]');
        if (repeatBox && (btn.dataset.repeatAdd || btn.dataset.repeatRemove !== undefined || btn.dataset.repeatMove || btn.dataset.repeatDuplicate !== undefined || btn.dataset.repeatExpand !== undefined || btn.dataset.repeatCollapse !== undefined)) {
            e.preventDefault();
            if (btn.dataset.repeatExpand !== undefined || btn.dataset.repeatCollapse !== undefined) {
                repeatBox.querySelectorAll('[data-repeat-card]').forEach(function(row){ row.open = btn.dataset.repeatExpand !== undefined; });
                return;
            }
            readAll();
            const i = Number(card.dataset.index || 0);
            const mode = repeatBox.dataset.repeatMode || 'cards';
            blocks[i].items = Array.isArray(blocks[i].items) ? blocks[i].items : [];
            if (btn.dataset.repeatAdd) { blocks[i].items.push(repeatDefaultItem(mode, blocks[i].items.length + 1)); }
            if (repeatCard && btn.dataset.repeatRemove !== undefined) {
                const rows = Array.from(repeatBox.querySelectorAll('[data-repeat-card]'));
                blocks[i].items.splice(rows.indexOf(repeatCard), 1);
            }
            if (repeatCard && btn.dataset.repeatDuplicate !== undefined) {
                const rows = Array.from(repeatBox.querySelectorAll('[data-repeat-card]'));
                const idx = rows.indexOf(repeatCard);
                if (idx >= 0) blocks[i].items.splice(idx + 1, 0, JSON.parse(JSON.stringify(blocks[i].items[idx] || repeatDefaultItem(mode, idx + 1))));
            }
            if (repeatCard && btn.dataset.repeatMove) {
                const rows = Array.from(repeatBox.querySelectorAll('[data-repeat-card]'));
                const idx = rows.indexOf(repeatCard);
                const target = btn.dataset.repeatMove === 'up' ? idx - 1 : idx + 1;
                if (target >= 0 && target < blocks[i].items.length) { [blocks[i].items[idx], blocks[i].items[target]] = [blocks[i].items[target], blocks[i].items[idx]]; }
            }
            render(); markDirty();
            return;
        }
        const fieldRow = btn.closest('[data-form-field]');
        const fieldBox = btn.closest('[data-repeat="fields"]');
        if (fieldBox && (btn.dataset.fieldAdd !== undefined || btn.dataset.fieldRemove !== undefined || btn.dataset.fieldMove)) {
            readAll();
            const i = Number(card.dataset.index || 0);
            blocks[i].fields = Array.isArray(blocks[i].fields) ? blocks[i].fields : [];
            if (btn.dataset.fieldAdd !== undefined) { blocks[i].fields.push({name:'field_' + (blocks[i].fields.length + 1), label:'Field Baru', type:'text', required:false, placeholder:'', options:[]}); }
            if (fieldRow && btn.dataset.fieldRemove !== undefined) {
                const rows = Array.from(fieldBox.querySelectorAll('[data-form-field]'));
                blocks[i].fields.splice(rows.indexOf(fieldRow), 1);
            }
            if (fieldRow && btn.dataset.fieldMove) {
                const rows = Array.from(fieldBox.querySelectorAll('[data-form-field]'));
                const idx = rows.indexOf(fieldRow);
                const target = btn.dataset.fieldMove === 'up' ? idx - 1 : idx + 1;
                if (target >= 0 && target < blocks[i].fields.length) { [blocks[i].fields[idx], blocks[i].fields[target]] = [blocks[i].fields[target], blocks[i].fields[idx]]; }
            }
            render(); markDirty();
            return;
        }
        readAll();
        const i = Number(card.dataset.index || 0);
        if (btn.dataset.move) { e.preventDefault(); }
        if (btn.dataset.move === 'up' && i > 0) { [blocks[i-1], blocks[i]] = [blocks[i], blocks[i-1]]; selectedBlockIndex = i - 1; render(); syncHidden(); markDirty(); }
        if (btn.dataset.move === 'down' && i < blocks.length - 1) { [blocks[i+1], blocks[i]] = [blocks[i], blocks[i+1]]; selectedBlockIndex = i + 1; render(); syncHidden(); markDirty(); }
        if (btn.dataset.duplicate) { blocks.splice(i+1, 0, JSON.parse(JSON.stringify(blocks[i]))); selectedBlockIndex = i + 1; render(); markDirty(); }
        if (btn.dataset.delete && confirm('Hapus blok ini?')) { blocks.splice(i, 1); selectedBlockIndex = Math.max(0, Math.min(i, blocks.length - 1)); render(); markDirty(); }
    });
    document.querySelectorAll('[data-add-block]').forEach(btn => btn.addEventListener('click', function(){
        readAll();
        const type = this.dataset.addBlock;
        const block = defaultBlock(type);
        if (type === 'custom_menu') {
            blocks.unshift(block);
            selectedBlockIndex = 0;
        } else {
            blocks.push(block);
            selectedBlockIndex = blocks.length - 1;
        }
        render();
        syncHidden();
        markDirty();
        setAutosaveStatus(type === 'custom_menu' ? 'Menu custom ditambahkan di bagian atas · belum disimpan' : 'Block baru ditambahkan', true);
    }));
    document.querySelectorAll('[data-add-preset]').forEach(btn => btn.addEventListener('click', function(){ addPresetSection(this.dataset.addPreset); }));
    document.querySelectorAll('[data-add-preset-pack]').forEach(btn => btn.addEventListener('click', function(){ addPresetPack(this.dataset.addPresetPack); }));
    document.querySelectorAll('[data-ai-action]').forEach(btn => btn.addEventListener('click', function(){ aiRunAction(this.dataset.aiAction || 'suggest'); }));
    document.querySelectorAll('[data-lp-template]').forEach(btn => btn.addEventListener('click', function(){ if (confirm('Ganti struktur blok dengan template ini?')) { blocks = JSON.parse(JSON.stringify(templates[this.dataset.lpTemplate] || templates.direct)).map(optimizeBlockDefaults); selectedBlockIndex = 0; render(); markDirty(); } }));
    document.querySelectorAll('[data-lp-tab]').forEach(btn => btn.addEventListener('click', function(){
        document.querySelectorAll('[data-lp-tab]').forEach(b=>b.classList.toggle('is-active', b===btn));
        document.querySelectorAll('[data-lp-panel]').forEach(p=>p.classList.toggle('is-active', p.dataset.lpPanel===btn.dataset.lpTab));
    }));
    const expandAll = document.querySelector('[data-lpw-expand-all]');
    const collapseAll = document.querySelector('[data-lpw-collapse-all]');
    const jumpIssue = document.querySelector('[data-lpw-jump-issue]');
    if (expandAll) expandAll.addEventListener('click', function(){ editor.querySelectorAll('.lpw-block-card').forEach(card=>card.classList.remove('is-collapsed')); });
    if (collapseAll) collapseAll.addEventListener('click', function(){ editor.querySelectorAll('.lpw-block-card').forEach(card=>card.classList.add('is-collapsed')); });
    if (jumpIssue) jumpIssue.addEventListener('click', function(){
        readAll();
        const row = blocks.map((block,index)=>({block,index,status:blockStatus(block)})).find(item=>item.status.cls !== 'is-ok');
        if (!row) { renderSistemPanels(); alert('Mantap, semua blok utama sudah cukup lengkap.'); return; }
        selectedBlockIndex = row.index;
        render();
        const target = editor.querySelector(`[data-index="${row.index}"]`);
        if (target) {
            target.classList.remove('is-collapsed');
            target.classList.add('is-attention');
            target.scrollIntoView({behavior:'smooth', block:'center'});
            setTimeout(()=>target.classList.remove('is-attention'), 1600);
        }
    });
    document.addEventListener('click', function(e){
        const visualPresetBtn = e.target.closest('[data-lpw-visual-preset]');
        if (visualPresetBtn) {
            applyVisualPolishPreset(visualPresetBtn.dataset.lpwVisualPreset || 'clean-premium');
            return;
        }
        const internalBtn = e.target.closest('[data-add-internal-links]');
        if (internalBtn) {
            readAll();
            const existing = internalLinkBlockIndex();
            const menuBlock = {type:'custom_menu', engine_internal_links:true, headline:'Link Cepat & Rekomendasi', text:'Arahkan pengunjung ke halaman pendukung agar SEO dan navigasi lebih kuat.', items:engineInternalLinks.map(function(link){ return {title:link.title, url:link.url, link_url:link.url, text:link.text}; })};
            if (existing >= 0) blocks[existing] = Object.assign({}, blocks[existing] || {}, menuBlock);
            else blocks.push(menuBlock);
            render(); markDirty();
            return;
        }
        const removeInternalBtn = e.target.closest('[data-remove-internal-links]');
        if (removeInternalBtn) {
            readAll();
            const existing = internalLinkBlockIndex();
            if (existing < 0) { renderSistemPanels(); return; }
            if (window.confirm('Hapus blok Menu Internal Link dari landing page ini?')) {
                blocks.splice(existing, 1);
                render(); markDirty();
            }
            return;
        }
        const focusInternalBtn = e.target.closest('[data-focus-internal-links]');
        if (focusInternalBtn) {
            const existing = internalLinkBlockIndex();
            if (existing >= 0) {
                selectedBlockIndex = existing;
                render();
            }
            const target = existing >= 0 ? editor.querySelector(`[data-index="${existing}"]`) : null;
            if (target) {
                target.classList.remove('is-collapsed');
                target.classList.add('is-attention');
                target.scrollIntoView({behavior:'smooth', block:'center'});
                setTimeout(()=>target.classList.remove('is-attention'), 1600);
            }
            return;
        }
        const testBtn = e.target.closest('[data-lpw-test-event]');
        if (testBtn) {
            const payload = trackingPayloadPreview();
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({event:'lp_builder_test_event', lp_builder_payload: payload});
            renderTrackingInspector('Test event lokal sudah dipush ke dataLayer browser. Ini aman untuk preview tanpa mengirim order/lead asli.');
        }
    });
    document.querySelectorAll('[data-preview-device]').forEach(btn => btn.addEventListener('click', function(){
        document.querySelectorAll('[data-preview-device]').forEach(b=>b.classList.toggle('is-active', b===btn));
        preview.classList.remove('lpw-preview-canvas--desktop','lpw-preview-canvas--tablet','lpw-preview-canvas--mobile');
        preview.classList.add('lpw-preview-canvas--' + btn.dataset.previewDevice);
        previewDeviceChecks[btn.dataset.previewDevice || 'desktop'] = true;
        renderSistemPanels();
    }));
    const shell = document.querySelector('[data-lpw-shell]');
    const sidebarToggle = document.querySelector('[data-lpw-sidebar-toggle]');
    function setSidebarCollapsed(collapsed){
        if (!shell || !sidebarToggle) return;
        shell.classList.toggle('is-sidebar-collapsed', collapsed);
        sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Tampilkan panel builder' : 'Sembunyikan panel builder');
        const icon = sidebarToggle.querySelector('.lpw-sidebar-toggle-icon');
        if (icon) icon.textContent = collapsed ? '›' : '‹';
        try { window.localStorage.setItem('lpwSidebarCollapsed', collapsed ? '1' : '0'); } catch(e) {}
    }
    if (sidebarToggle) {
        let savedCollapsed = false;
        try { savedCollapsed = window.localStorage.getItem('lpwSidebarCollapsed') === '1'; } catch(e) {}
        setSidebarCollapsed(savedCollapsed);
        sidebarToggle.addEventListener('click', function(){ setSidebarCollapsed(!(shell && shell.classList.contains('is-sidebar-collapsed'))); });
    }

    const actionInput = document.getElementById('lpFormAction');
    const revisionNoteInput = document.getElementById('lpRevisionNote');
    const revisionNoteField = document.querySelector('[data-revision-note-field]');
    function prepareSubmitFromButton(btn){
        if (actionInput) actionInput.value = btn.dataset.submitAction || 'save';
        if (btn.dataset.forceStatus) setFormValue('status', btn.dataset.forceStatus);
        const manualNote = revisionNoteField ? String(revisionNoteField.value || '').trim() : '';
        if (revisionNoteInput) revisionNoteInput.value = manualNote || btn.dataset.revisionNote || '';
    }
    function isPublishButton(btn){
        return !!(btn && btn.dataset && btn.dataset.forceStatus === 'published' && (btn.dataset.submitAction || 'save') === 'save');
    }
    document.querySelectorAll('[data-submit-action]').forEach(function(btn){
        btn.addEventListener('click', function(e){
            if (btn.dataset.restoreRevision) {
                const ok = window.confirm('Restore revisi #' + btn.dataset.restoreRevision + '? Perubahan current akan diganti oleh snapshot revisi ini dan tetap dibuatkan snapshot restore baru.');
                if (!ok) { e.preventDefault(); return; }
            }
            if (isPublishButton(btn) && !publishGuardBypass) {
                e.preventDefault();
                readAll(); syncHidden(); renderSistemPanels();
                openPublishGuard(btn);
                closeSaveMenu();
                return;
            }
            prepareSubmitFromButton(btn);
        });
    });
    const saveDropdown = document.querySelector('[data-lpw-save-dropdown]');
    const saveMenuToggle = document.querySelector('[data-lpw-save-menu-toggle]');
    const saveMenu = document.querySelector('[data-lpw-save-menu]');
    function closeSaveMenu(){
        if (!saveMenu || !saveMenuToggle) return;
        saveMenu.hidden = true;
        saveMenuToggle.setAttribute('aria-expanded', 'false');
    }
    if (saveMenuToggle && saveMenu) {
        saveMenuToggle.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            const willOpen = saveMenu.hidden;
            saveMenu.hidden = !willOpen;
            saveMenuToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
        document.addEventListener('click', function(e){
            if (saveDropdown && !saveDropdown.contains(e.target)) closeSaveMenu();
        });
        document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeSaveMenu(); });
    }

    const templateGalleryDialog = document.querySelector('[data-template-gallery-dialog]');
    document.querySelectorAll('[data-open-template-gallery]').forEach(function(openTemplateGallery){
        if (!openTemplateGallery || !templateGalleryDialog) return;
        openTemplateGallery.addEventListener('click', function(){
            closeSaveMenu();
            readAll(); syncHidden();
            if (typeof templateGalleryDialog.showModal === 'function') templateGalleryDialog.showModal();
            else templateGalleryDialog.setAttribute('open', 'open');
        });
    });
    document.querySelectorAll('[data-close-template-gallery]').forEach(function(btn){
        btn.addEventListener('click', closeTemplateGallery);
    });
    document.querySelectorAll('[data-apply-template-gallery]').forEach(function(btn){
        btn.addEventListener('click', function(){
            applyTemplateFromGallery(this.dataset.applyTemplateGallery || 'builtin', this.dataset.templateId || '');
        });
    });

    const templateDialog = document.querySelector('[data-template-dialog]');
    document.querySelectorAll('[data-open-template-dialog]').forEach(function(openTemplateDialog){
        if (!openTemplateDialog || !templateDialog) return;
        openTemplateDialog.addEventListener('click', function(){
            closeSaveMenu();
            readAll(); syncHidden();
            if (typeof templateDialog.showModal === 'function') templateDialog.showModal();
            else templateDialog.setAttribute('open', 'open');
        });
    });
    document.querySelectorAll('[data-close-template-dialog]').forEach(function(btn){
        btn.addEventListener('click', function(){ if (templateDialog && typeof templateDialog.close === 'function') templateDialog.close(); else if (templateDialog) templateDialog.removeAttribute('open'); });
    });

    document.querySelectorAll('[data-lpw-guard-close]').forEach(function(btn){
        btn.addEventListener('click', function(){
            closePublishGuard();
            const optTab = document.querySelector('[data-lp-tab="optimization"]');
            if (optTab) optTab.click();
        });
    });
    const guardDraftBtn = document.querySelector('[data-lpw-guard-save-draft]');
    if (guardDraftBtn) guardDraftBtn.addEventListener('click', function(){
        closePublishGuard();
        setFormValue('status', 'draft');
        if (actionInput) actionInput.value = 'save';
        if (revisionNoteInput) revisionNoteInput.value = 'Simpan draft dari Publish Guard';
        readAll(); syncHidden(); hasUnsavedChanges = false; clearLocalDraft();
        form.submit();
    });
    const guardContinueBtn = document.querySelector('[data-lpw-guard-continue]');
    if (guardContinueBtn) guardContinueBtn.addEventListener('click', function(){
        closePublishGuard();
        publishGuardBypass = true;
        const btn = pendingPublishButton || document.querySelector('.lpw-publish-main');
        if (btn) prepareSubmitFromButton(btn);
        else setFormValue('status', 'published');
        readAll(); syncHidden();
        if (form.requestSubmit && btn) form.requestSubmit(btn);
        else { hasUnsavedChanges = false; clearLocalDraft(); form.submit(); }
    });

    form.addEventListener('submit', function(e){
        const submitter = e.submitter || pendingPublishButton;
        if (isPublishButton(submitter) && !publishGuardBypass) {
            e.preventDefault();
            readAll(); syncHidden(); renderSistemPanels(); openPublishGuard(submitter); closeSaveMenu();
            return;
        }
        readAll(); syncHidden(); hasUnsavedChanges = false; clearLocalDraft(); if (previewStatus) previewStatus.textContent = 'Menyimpan perubahan...';
    });
    const titleInput = document.getElementById('lpTitleInput');
    const slugInput = document.getElementById('lpSlugInput');
    if (titleInput && slugInput) titleInput.addEventListener('blur', function(){ if (!slugInput.value.trim()) slugInput.value = slugify(titleInput.value); });
    if (isNewLandingPage && requestedTemplate && templates[requestedTemplate]) {
        blocks = JSON.parse(JSON.stringify(templates[requestedTemplate])).map(optimizeBlockDefaults);
    }
    render();
    startPreviewCountdownRuntime();
    captureHistory(true);
    restoreLocalDraftIfAvailable();
    renderSistemPanels();
    updateHistoryButtons();
})();
</script>
<?php endif; ?>
<script>window.__LEAD_TRACKING_ENDPOINT__ = '<?= esc(url('lead-event')); ?>';</script>
<script src="<?= esc(asset('js/app.js')); ?>" defer></script>
</body>
</html>
