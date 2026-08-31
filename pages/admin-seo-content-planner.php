<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$view = (string)($_GET['view'] ?? 'briefs');
if (!in_array($view, ['briefs', 'calendar', 'backlog'], true)) {
    $view = 'briefs';
}

$week = (string)($_GET['week'] ?? 'all');
if (!in_array($week, ['all', 'Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'], true)) {
    $week = 'all';
}

$priority = (string)($_GET['priority'] ?? 'all');
if (!in_array($priority, ['all', 'tinggi', 'sedang', 'rendah'], true)) {
    $priority = 'all';
}

$q = trim((string)($_GET['q'] ?? ''));
$planner = function_exists('seo_content_planner_summary') ? seo_content_planner_summary() : [];
$briefs = (array)($planner['briefs'] ?? []);
$calendar = (array)($planner['calendar'] ?? []);
$backlog = (array)($planner['backlog'] ?? []);
$metrics = (array)($planner['metrics'] ?? []);

$matchesQuery = static function (array $row) use ($q): bool {
    if ($q === '') {
        return true;
    }
    $needle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
    $flatten = static function (mixed $value) use (&$flatten): string {
        if (is_array($value)) {
            return implode(' ', array_map($flatten, $value));
        }
        return (string)$value;
    };
    $haystack = $flatten($row);
    $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack, 'UTF-8') : strtolower($haystack);
    return str_contains($haystack, $needle);
};

$matchesPriority = static function (array $row) use ($priority): bool {
    if ($priority === 'all') {
        return true;
    }
    $label = function_exists('mb_strtolower') ? mb_strtolower((string)($row['priority_label'] ?? ''), 'UTF-8') : strtolower((string)($row['priority_label'] ?? ''));
    return $label === $priority;
};

$briefs = array_values(array_filter($briefs, static fn(array $row): bool => ($week === 'all' || (string)($row['week'] ?? '') === $week) && $matchesPriority($row) && $matchesQuery($row)));
$backlog = array_values(array_filter($backlog, static fn(array $row): bool => $matchesQuery($row)));

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-content-planner-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'planner' => $planner,
        'filtered' => [
            'view' => $view,
            'week' => $week,
            'priority' => $priority,
            'q' => $q,
            'briefs' => $briefs,
            'calendar' => $calendar,
            'backlog' => $backlog,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-content-planner-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    if ($view === 'backlog') {
        fputcsv($out, ['cluster','title','content_type','intent','keyword_seed','reason','recommended_cta'], ',', '"', '\\', "\n");
        foreach ($backlog as $row) {
            fputcsv($out, [$row['cluster'] ?? '', $row['title'] ?? '', $row['content_type'] ?? '', $row['intent'] ?? '', implode(', ', (array)($row['keyword_seed'] ?? [])), $row['reason'] ?? '', $row['recommended_cta'] ?? ''], ',', '"', '\\', "\n");
        }
    } elseif ($view === 'calendar') {
        fputcsv($out, ['week','theme','output_target','title','priority','intent','target_title','keyword_seed'], ',', '"', '\\', "\n");
        foreach ($calendar as $row) {
            foreach ((array)($row['items'] ?? []) as $item) {
                fputcsv($out, [$row['week'] ?? '', $row['theme'] ?? '', $row['output_target'] ?? '', $item['title'] ?? '', $item['priority_score'] ?? 0, $item['intent_label'] ?? '', $item['target_title'] ?? '', implode(', ', (array)($item['keyword_seed'] ?? []))], ',', '"', '\\', "\n");
            }
        }
    } else {
        fputcsv($out, ['week','priority','title','content_type','intent','target_title','target_url','keyword_seed','suggested_slug','meta_title','meta_description','internal_link_anchor','cta_note'], ',', '"', '\\', "\n");
        foreach ($briefs as $brief) {
            fputcsv($out, [$brief['week'] ?? '', $brief['priority_score'] ?? 0, $brief['title'] ?? '', $brief['content_type_label'] ?? '', $brief['intent_label'] ?? '', $brief['target_title'] ?? '', $brief['target_url'] ?? '', implode(', ', (array)($brief['keyword_seed'] ?? [])), $brief['suggested_slug'] ?? '', $brief['meta_title_template'] ?? '', $brief['meta_description_template'] ?? '', $brief['internal_link_anchor'] ?? '', $brief['cta_note'] ?? ''], ',', '"', '\\', "\n");
        }
    }
    fclose($out);
    exit;
}

function admin_seo_content_planner_url(array $overrides = []): string
{
    $query = array_merge([
        'view' => $_GET['view'] ?? 'briefs',
        'week' => $_GET['week'] ?? 'all',
        'priority' => $_GET['priority'] ?? 'all',
        'q' => $_GET['q'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/seo-content-planner' . ($query ? '?' . http_build_query($query) : ''));
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'SEO Content Planner - Admin',
    'description' => 'Brief konten, kalender publikasi, dan backlog topik SEO untuk mengubah audit menjadi mesin growth.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-content-planner-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>SEO Content Planner</h1>
                <p>Ubah audit dan SEO Growth Planner menjadi brief artikel, kalender 30 hari, FAQ, studi kasus, dan konten pendukung yang nyambung ke conversion.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-growth-planner'); ?>">SEO Growth Planner</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-execution-board'); ?>">Execution Board</a>
                <a class="admin-btn admin-btn--primary" href="<?= url('admin/artikel'); ?>">Buka Artikel</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <div class="admin-report-grid admin-report-grid--four">
                <div class="admin-card admin-report-metric"><span class="admin-badge">Brief</span><h2><?= (int)($metrics['brief_count'] ?? count($briefs)); ?></h2><p>Draft arahan konten berbasis halaman target dan gap SEO.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Prioritas</span><h2><?= (int)($metrics['high_priority_count'] ?? 0); ?></h2><p>Konten high-impact yang paling dekat ke growth dan conversion.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Cluster</span><h2><?= (int)($metrics['cluster_count'] ?? 0); ?></h2><p>Topik utama yang bisa diperkuat dengan artikel dan internal link.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">SEO Score</span><h2><?= (int)($metrics['seo_score'] ?? 100); ?>/100</h2><p>Skor fondasi SEO dari Universal SEO Engine.</p></div>
            </div>

            <div class="admin-card admin-seo-content-focus">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Content Engine</span>
                        <h2>Rencana Konten yang Nyambung ke Penjualan</h2>
                        <p>Planner ini membantu admin membuat konten bukan asal posting, tapi diarahkan ke katalog, layanan, landing page, WhatsApp, form, dan checkout.</p>
                    </div>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/universal-seo'); ?>">Audit SEO</a>
                </div>
                <div class="admin-seo-content-mini-grid">
                    <?php foreach (array_slice($calendar, 0, 4) as $row): ?>
                        <article class="admin-seo-content-mini-card">
                            <span><?= esc((string)($row['week'] ?? 'Minggu')); ?></span>
                            <strong><?= esc((string)($row['theme'] ?? 'Fokus SEO')); ?></strong>
                            <p><?= esc((string)($row['output_target'] ?? '2 konten pendukung')); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="get" action="<?= url('admin/seo-content-planner'); ?>" class="admin-card admin-report-filter admin-seo-content-filter">
                <div class="admin-report-filter-head">
                    <div>
                        <span class="admin-badge">Content Filter</span>
                        <h3>Atur planner konten</h3>
                    </div>
                    <p>Pilih mode tampilan, minggu kerja, prioritas, atau cari keyword/topik tertentu.</p>
                </div>
                <div class="admin-report-filter-grid admin-report-filter-grid--wide">
                    <label><span>Tampilan</span>
                        <select name="view">
                            <?php foreach (['briefs' => 'Brief Konten', 'calendar' => 'Kalender 30 Hari', 'backlog' => 'Backlog Topik'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $view === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Minggu</span>
                        <select name="week">
                            <?php foreach (['all' => 'Semua', 'Minggu 1' => 'Minggu 1', 'Minggu 2' => 'Minggu 2', 'Minggu 3' => 'Minggu 3', 'Minggu 4' => 'Minggu 4'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $week === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Prioritas</span>
                        <select name="priority">
                            <?php foreach (['all' => 'Semua', 'tinggi' => 'Tinggi', 'sedang' => 'Sedang', 'rendah' => 'Rendah'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $priority === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari</span>
                        <input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, keyword, target, cluster...">
                    </label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-content-planner'); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_content_planner_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_content_planner_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <?php if ($view === 'calendar'): ?>
                <div class="admin-seo-content-calendar">
                    <?php foreach ($calendar as $row): ?>
                        <article class="admin-card admin-seo-content-week">
                            <div class="admin-seo-content-week__head">
                                <div><span class="admin-badge"><?= esc((string)($row['week'] ?? 'Minggu')); ?></span><h2><?= esc((string)($row['theme'] ?? 'Fokus Konten')); ?></h2><p>Target: <?= esc((string)($row['output_target'] ?? '2 konten pendukung')); ?></p></div>
                                <strong><?= count((array)($row['items'] ?? [])); ?> item</strong>
                            </div>
                            <div class="admin-seo-content-week__list">
                                <?php foreach (array_slice((array)($row['items'] ?? []), 0, 6) as $item): ?>
                                    <div class="admin-seo-content-week__item">
                                        <span class="<?= esc(seo_content_priority_class((int)($item['priority_score'] ?? 0))); ?>"><?= esc((string)($item['priority_label'] ?? 'Sedang')); ?></span>
                                        <strong><?= esc((string)($item['title'] ?? 'Brief konten')); ?></strong>
                                        <small><?= esc((string)($item['intent_label'] ?? '-')); ?> · Target: <?= esc((string)($item['target_title'] ?? '-')); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($view === 'backlog'): ?>
                <div class="admin-card">
                    <div class="admin-form-head admin-form-head--split">
                        <div><h2>Backlog Topik SEO</h2><p>Cadangan ide FAQ, studi kasus, dan konten trust builder berdasarkan cluster yang sudah ada.</p></div>
                        <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-landings'); ?>">Buka SEO Landing</a>
                    </div>
                    <div class="admin-seo-content-backlog-grid">
                        <?php foreach ($backlog as $row): ?>
                            <article class="admin-seo-content-backlog-card">
                                <span class="admin-badge"><?= esc((string)($row['cluster'] ?? 'cluster')); ?></span>
                                <h3><?= esc((string)($row['title'] ?? 'Ide konten')); ?></h3>
                                <p><?= esc((string)($row['reason'] ?? 'Konten ini membantu memperkuat SEO dan conversion.')); ?></p>
                                <small>Keyword: <?= esc(implode(', ', (array)($row['keyword_seed'] ?? []))); ?></small>
                                <div class="admin-seo-content-cta-note"><?= esc((string)($row['recommended_cta'] ?? 'Tambahkan CTA ke halaman target.')); ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-seo-content-brief-list">
                    <?php foreach ($briefs as $brief): ?>
                        <article class="admin-card admin-seo-content-brief">
                            <div class="admin-seo-content-brief__head">
                                <div>
                                    <span class="<?= esc(seo_content_priority_class((int)($brief['priority_score'] ?? 0))); ?>"><?= esc((string)($brief['priority_label'] ?? 'Sedang')); ?> · <?= (int)($brief['priority_score'] ?? 0); ?>/100</span>
                                    <h2><?= esc((string)($brief['title'] ?? 'Brief konten SEO')); ?></h2>
                                    <p><?= esc((string)($brief['brief_note'] ?? 'Buat konten pendukung dan arahkan ke halaman target.')); ?></p>
                                </div>
                                <div class="admin-row-actions">
                                    <?php if (!empty($brief['target_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$brief['target_url']); ?>" target="_blank" rel="noopener">Target</a><?php endif; ?>
                                    <a class="admin-btn admin-btn--primary" href="<?= url('admin/artikel'); ?>">Buat Artikel</a>
                                </div>
                            </div>
                            <div class="admin-seo-content-brief__meta">
                                <span><?= esc((string)($brief['week'] ?? 'Minggu 1')); ?></span>
                                <span><?= esc((string)($brief['content_type_label'] ?? 'Artikel SEO')); ?></span>
                                <span><?= esc((string)($brief['intent_label'] ?? 'Growth Content')); ?></span>
                                <span>Target: <?= esc((string)($brief['target_title'] ?? '-')); ?></span>
                            </div>
                            <div class="admin-seo-content-keywords">
                                <?php foreach ((array)($brief['keyword_seed'] ?? []) as $keyword): ?>
                                    <span><?= esc((string)$keyword); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <div class="admin-seo-content-brief__body">
                                <div>
                                    <h3>Outline</h3>
                                    <ol>
                                        <?php foreach ((array)($brief['outline'] ?? []) as $point): ?>
                                            <li><?= esc((string)$point); ?></li>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>
                                <div>
                                    <h3>FAQ Pendukung</h3>
                                    <ul>
                                        <?php foreach ((array)($brief['faq_questions'] ?? []) as $question): ?>
                                            <li><?= esc((string)$question); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="admin-seo-content-copybox">
                                <strong>Snippet kerja</strong>
                                <p>Slug: <code><?= esc((string)($brief['suggested_slug'] ?? '')); ?></code></p>
                                <p>Meta title: <?= esc((string)($brief['meta_title_template'] ?? '')); ?></p>
                                <p>Meta description: <?= esc((string)($brief['meta_description_template'] ?? '')); ?></p>
                                <p>Internal link anchor: <code><?= esc((string)($brief['internal_link_anchor'] ?? '')); ?></code></p>
                                <p><?= esc((string)($brief['cta_note'] ?? 'Tambahkan CTA natural ke halaman target.')); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
