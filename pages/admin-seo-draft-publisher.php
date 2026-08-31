<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$status = (string)($_GET['status'] ?? 'all');
if ($status !== 'all' && !array_key_exists($status, seo_execution_statuses())) {
    $status = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$selectedTaskId = trim((string)($_GET['task'] ?? ''));
$message = trim((string)($_GET['message'] ?? ''));
$error = '';

$allTasks = function_exists('seo_execution_tasks') ? seo_execution_tasks() : [];
$allDrafts = seo_draft_publisher_all_drafts();
$filteredTasks = seo_draft_publisher_filtered_tasks($allTasks, $q, $status);

if ($selectedTaskId === '' && $filteredTasks) {
    $selectedTaskId = (string)($filteredTasks[0]['id'] ?? '');
}
$selectedTask = $selectedTaskId !== '' ? seo_draft_publisher_find_task($selectedTaskId) : null;
$selectedDraft = $selectedTask ? seo_draft_publisher_find_by_task((string)($selectedTask['id'] ?? '')) : null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $taskId = trim((string)($_POST['task_id'] ?? ''));
    $task = seo_draft_publisher_find_task($taskId);

    if (!$task) {
        $error = 'Task SEO tidak ditemukan.';
    } elseif (in_array($action, ['create_draft', 'refresh_draft'], true)) {
        $result = seo_draft_publisher_create_or_refresh($task);
        $message = (string)($result['message'] ?? '');
        if (empty($result['ok'])) {
            $error = $message ?: 'Gagal menyiapkan draft.';
            $message = '';
        }
        redirect_302('admin/seo-draft-publisher?task=' . rawurlencode($taskId) . '&message=' . rawurlencode($message ?: $error));
    }
}

$allDrafts = seo_draft_publisher_all_drafts();
$selectedDraft = $selectedTask ? seo_draft_publisher_find_by_task((string)($selectedTask['id'] ?? '')) : null;
$metrics = seo_draft_publisher_metrics($allDrafts, $allTasks);

function admin_seo_draft_publisher_url(array $overrides = []): string
{
    $query = array_merge([
        'status' => $_GET['status'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'task' => $_GET['task'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/seo-draft-publisher' . ($query ? '?' . http_build_query($query) : ''));
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-draft-publisher-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'generated_at' => date('Y-m-d H:i:s'),
        'metrics' => $metrics,
        'drafts' => $allDrafts,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-draft-publisher-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['draft_id', 'task_id', 'status', 'article_id', 'title', 'slug', 'focus_keyword', 'target_title', 'updated_at'], ',', '"', '');
    foreach ($allDrafts as $draft) {
        fputcsv($out, [
            $draft['id'] ?? '',
            $draft['task_id'] ?? '',
            $draft['status'] ?? '',
            $draft['article_id'] ?? 0,
            $draft['title'] ?? '',
            $draft['slug'] ?? '',
            $draft['focus_keyword'] ?? '',
            $draft['seo_target_title'] ?? '',
            $draft['updated_at'] ?? '',
        ], ',', '"', '');
    }
    fclose($out);
    exit;
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'SEO Draft Publisher - Admin',
    'description' => 'Bridge dari SEO task menjadi draft artikel siap edit, publish, dan masuk sitemap setelah disimpan.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-draft-publisher-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>SEO Draft Publisher</h1>
                <p>Jembatan dari rekomendasi SEO menjadi draft artikel siap edit. Draft disimpan terpisah dulu, lalu admin bisa membukanya di editor artikel sebelum tayang.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-execution-board'); ?>">Execution Board</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-publish-checklist'); ?>">Publish Checklist</a>
                <a class="admin-btn admin-btn--primary" href="<?= url('admin/artikel?action=create'); ?>">Artikel Baru Manual</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-report-grid admin-report-grid--four admin-seo-draft-metrics">
                <div class="admin-card admin-report-metric"><span class="admin-badge">Task SEO</span><h2><?= (int)($metrics['tasks'] ?? 0); ?></h2><p>Total peluang konten dari planner dan board.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Draft Siap Edit</span><h2><?= (int)($metrics['drafts'] ?? 0); ?></h2><p>Draft yang sudah dibuat dari task SEO.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Jadi Artikel</span><h2><?= (int)($metrics['linked_articles'] ?? 0); ?></h2><p>Draft yang sudah tersambung ke artikel admin.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Progress Draft</span><h2><?= (int)($metrics['conversion_percent'] ?? 0); ?>%</h2><p>Persentase task yang sudah punya draft.</p></div>
            </div>

            <div class="admin-card admin-seo-draft-flow">
                <span class="admin-badge">Publish Flow</span>
                <h2>Alur SEO: Brief → Draft → Editor → Publish → Pantau Growth</h2>
                <p>Sistem mengambil brief dari SEO Execution Board, mengubahnya menjadi body artikel, meta title, meta description, FAQ, focus keyword, dan internal link. Draft ini belum muncul di publik sampai admin membuka editor dan menyimpan artikel.</p>
                <div class="admin-seo-draft-flow__steps">
                    <span>1. Pilih task</span><span>2. Buat draft</span><span>3. Review di editor</span><span>4. Simpan artikel</span><span>5. Pantau SEO & lead</span>
                </div>
            </div>

            <form method="get" action="<?= url('admin/seo-draft-publisher'); ?>" class="admin-card admin-report-filter admin-seo-draft-filter">
                <div class="admin-report-filter-head">
                    <div><span class="admin-badge">Draft Filter</span><h3>Atur antrian draft artikel</h3></div>
                    <p>Pilih status task, cari topik, lalu buat draft artikel dari task yang paling siap dieksekusi.</p>
                </div>
                <div class="admin-report-filter-grid">
                    <label><span>Status Task</span>
                        <select name="status">
                            <option value="all">Semua</option>
                            <?php foreach (seo_execution_statuses() as $key => $row): ?>
                                <option value="<?= esc($key); ?>" <?= $status === $key ? 'selected' : ''; ?>><?= esc((string)$row['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari Topik</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, keyword, target, slug..."></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-draft-publisher'); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_draft_publisher_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_draft_publisher_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <div class="admin-grid admin-grid--two admin-seo-draft-layout">
                <div class="admin-card admin-seo-draft-task-list">
                    <div class="admin-form-head"><h2>Antrian Task SEO</h2><p><?= count($filteredTasks); ?> task sesuai filter. Pilih satu untuk membuat draft artikel.</p></div>
                    <div class="admin-seo-draft-queue">
                        <?php foreach (array_slice($filteredTasks, 0, 32) as $task): ?>
                            <?php $draft = seo_draft_publisher_find_by_task((string)($task['id'] ?? '')); ?>
                            <a class="admin-seo-draft-task <?= ((string)($task['id'] ?? '') === $selectedTaskId) ? 'is-active' : ''; ?>" href="<?= esc(admin_seo_draft_publisher_url(['task' => (string)($task['id'] ?? '')])); ?>">
                                <span class="<?= esc(seo_execution_priority_class((int)($task['priority_score'] ?? 0))); ?>"><?= esc((string)($task['priority_label'] ?? 'Sedang')); ?></span>
                                <strong><?= esc((string)($task['title'] ?? 'Task SEO')); ?></strong>
                                <small><?= esc((string)($task['target_title'] ?? 'Halaman target')); ?></small>
                                <i><?= $draft ? 'Draft siap' : 'Belum draft'; ?></i>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$filteredTasks): ?>
                            <div class="admin-empty-state"><h2>Belum ada task sesuai filter.</h2><p>Buka SEO Content Planner atau reset filter.</p></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="admin-card admin-seo-draft-preview">
                    <?php if ($selectedTask): ?>
                        <?php $preview = $selectedDraft ?: seo_draft_publisher_payload_from_task($selectedTask); ?>
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="<?= esc(seo_execution_status_class((string)($selectedTask['status'] ?? 'todo'))); ?>"><?= esc(seo_execution_status_label((string)($selectedTask['status'] ?? 'todo'))); ?></span>
                                <h2><?= esc((string)($preview['title'] ?? 'Draft Artikel SEO')); ?></h2>
                                <p>Target: <?= esc((string)($selectedTask['target_title'] ?? '-')); ?> · Slug: <code><?= esc((string)($preview['slug'] ?? '')); ?></code></p>
                            </div>
                            <div class="admin-seo-draft-state">
                                <strong><?= $selectedDraft ? 'Draft Siap' : 'Preview' ?></strong>
                                <small><?= $selectedDraft ? esc((string)($selectedDraft['updated_at'] ?? '')) : 'belum disimpan'; ?></small>
                            </div>
                        </div>

                        <div class="admin-seo-draft-snippet">
                            <span>Snippet & SEO Pack</span>
                            <strong><?= esc((string)($preview['meta_title'] ?? 'Meta title')); ?></strong>
                            <small>/artikel/<?= esc((string)($preview['slug'] ?? 'slug-artikel')); ?></small>
                            <p><?= esc((string)($preview['meta_description'] ?? 'Meta description')); ?></p>
                            <div><em>Focus keyword:</em> <?= esc((string)($preview['focus_keyword'] ?? '-')); ?></div>
                        </div>

                        <?php $draftContent = (string)($preview['content'] ?? ''); ?>
                        <?php $draftPreviewHtml = function_exists('template_content_safe_html') ? template_content_safe_html($draftContent, 20000) : strip_tags($draftContent, '<p><br><strong><b><em><i><ul><ol><li><a><h2><h3><h4><blockquote><span><small>'); ?>
                        <div class="admin-seo-draft-body">
                            <div class="admin-seo-draft-body__head">
                                <div>
                                    <h3>Isi Draft Artikel</h3>
                                    <p>Ini adalah isi artikel yang akan dibuka di editor artikel. Kode seperti &lt;p&gt; dan &lt;h2&gt; akan tampil normal saat artikel diterbitkan.</p>
                                </div>
                                <span>Preview siap edit</span>
                            </div>
                            <div class="admin-seo-draft-body__preview" aria-label="Preview isi draft artikel">
                                <?= $draftPreviewHtml !== '' ? $draftPreviewHtml : '<p>Belum ada isi draft artikel.</p>'; ?>
                            </div>
                            <details class="admin-seo-draft-body__html">
                                <summary>Lihat format HTML</summary>
                                <textarea rows="12" readonly><?= esc($draftContent); ?></textarea>
                            </details>
                        </div>

                        <div class="admin-seo-draft-actions">
                            <form method="post" class="admin-inline-form">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="<?= $selectedDraft ? 'refresh_draft' : 'create_draft'; ?>">
                                <input type="hidden" name="task_id" value="<?= esc((string)($selectedTask['id'] ?? '')); ?>">
                                <button class="admin-btn admin-btn--primary" type="submit"><?= $selectedDraft ? 'Refresh Draft' : 'Buat Draft Artikel'; ?></button>
                            </form>
                            <?php if ($selectedDraft): ?>
                                <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/artikel?action=create&seo_draft=' . rawurlencode((string)($selectedDraft['id'] ?? '')))); ?>">Buka di Editor Artikel</a>
                            <?php endif; ?>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-publish-checklist?task=' . rawurlencode((string)($selectedTask['id'] ?? '')))); ?>">Cek Quality Gate</a>
                        </div>
                    <?php else: ?>
                        <div class="admin-empty-state"><h2>Belum ada task dipilih.</h2><p>Pilih task dari antrian untuk membuat draft artikel SEO.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
