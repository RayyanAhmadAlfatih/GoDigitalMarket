<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$readiness = (string)($_GET['readiness'] ?? 'all');
if (!in_array($readiness, ['all', 'ready', 'almost', 'needs_fix'], true)) {
    $readiness = 'all';
}

$status = (string)($_GET['status'] ?? 'all');
if ($status !== 'all' && !array_key_exists($status, seo_execution_statuses())) {
    $status = 'all';
}

$q = trim((string)($_GET['q'] ?? ''));
$selectedTaskId = trim((string)($_GET['task'] ?? ''));
$message = '';
$error = '';

$allTasks = seo_publish_tasks();
$filteredTasks = seo_publish_filtered_tasks($allTasks, [
    'readiness' => $readiness,
    'status' => $status,
    'q' => $q,
]);
$metrics = seo_publish_metrics($allTasks);

if ($selectedTaskId === '' && $filteredTasks) {
    $selectedTaskId = (string)($filteredTasks[0]['id'] ?? '');
}
$selectedTask = $selectedTaskId !== '' ? seo_publish_find_task($allTasks, $selectedTaskId) : null;
if ($selectedTask === null && $filteredTasks) {
    $selectedTask = $filteredTasks[0];
    $selectedTaskId = (string)($selectedTask['id'] ?? '');
}
$selectedScore = $selectedTask ? seo_publish_task_score($selectedTask) : ['score' => 0, 'status' => 'Belum Ada Task', 'class' => 'admin-status-pill admin-status-pill--warning', 'checks' => []];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $taskId = trim((string)($_POST['task_id'] ?? ''));
    $task = seo_publish_find_task($allTasks, $taskId);

    if ($action === 'mark_ready' && $task) {
        $score = seo_publish_task_score($task);
        $ok = seo_execution_save_task_state($taskId, [
            'status' => 'ready',
            'owner' => (string)($task['owner'] ?? ''),
            'due_date' => (string)($task['due_date'] ?? ''),
            'note' => trim((string)($task['note'] ?? '') . "\nChecklist publish score: " . (int)$score['score'] . '/100.'),
        ]);
        $message = $ok ? 'Task ditandai Siap Publish dari quality gate.' : 'Gagal menandai task.';
        if (!$ok) {
            $error = $message;
            $message = '';
        }
        redirect_302('admin/seo-publish-checklist?task=' . rawurlencode($taskId) . '&message=' . rawurlencode($message ?: $error));
    }
}

if (isset($_GET['message'])) {
    $message = trim((string)$_GET['message']);
}

function admin_seo_publish_url(array $overrides = []): string
{
    $query = array_merge([
        'readiness' => $_GET['readiness'] ?? 'all',
        'status' => $_GET['status'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'task' => $_GET['task'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/seo-publish-checklist' . ($query ? '?' . http_build_query($query) : ''));
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-publish-checklist-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'generated_at' => date('Y-m-d H:i:s'),
        'metrics' => $metrics,
        'filters' => ['readiness' => $readiness, 'status' => $status, 'q' => $q],
        'tasks' => $filteredTasks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-publish-checklist-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['score','readiness','execution_status','title','slug','meta_title','meta_description','target','keyword_seed','blocking_count','owner','due_date'], ',', '"', '\\', "\n");
    foreach ($filteredTasks as $task) {
        fputcsv($out, [
            (int)($task['publish_score'] ?? 0),
            (string)($task['publish_status'] ?? ''),
            seo_execution_status_label((string)($task['status'] ?? 'todo')),
            (string)($task['title'] ?? ''),
            (string)($task['suggested_slug'] ?? ''),
            (string)($task['meta_title_template'] ?? ''),
            (string)($task['meta_description_template'] ?? ''),
            (string)($task['target_title'] ?? ''),
            implode(', ', (array)($task['keyword_seed'] ?? [])),
            (int)($task['publish_blocking_count'] ?? 0),
            (string)($task['owner'] ?? ''),
            (string)($task['due_date'] ?? ''),
        ], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'SEO Publish Checklist - Admin',
    'description' => 'Quality gate publikasi konten SEO agar judul, meta, outline, FAQ, internal link, CTA, dan draft siap tayang.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-publish-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>SEO Publish Checklist</h1>
                <p>Quality gate sebelum konten dipublikasikan: cek snippet, keyword, outline, FAQ, internal link, CTA, dan kesiapan draft agar SEO Growth Engine benar-benar menghasilkan traffic dan conversion.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-execution-board'); ?>">Execution Board</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-content-planner'); ?>">Content Planner</a>
                <a class="admin-btn admin-btn--primary" href="<?= url('admin/artikel?action=create'); ?>">Tulis Artikel</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-report-grid admin-report-grid--four admin-seo-publish-metrics">
                <div class="admin-card admin-report-metric"><span class="admin-badge">Publish Score</span><h2><?= (int)($metrics['average_score'] ?? 0); ?><small>/100</small></h2><p>Rata-rata kesiapan task SEO untuk dipublish.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Siap Publish</span><h2><?= (int)($metrics['ready'] ?? 0); ?></h2><p>Konten yang sudah lolos quality gate utama.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Hampir Siap</span><h2><?= (int)($metrics['almost'] ?? 0); ?></h2><p>Tinggal poles beberapa bagian sebelum tayang.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Perlu Fix</span><h2><?= (int)($metrics['needs_fix'] ?? 0); ?></h2><p>Konten yang masih perlu dilengkapi dulu.</p></div>
            </div>

            <form method="get" action="<?= url('admin/seo-publish-checklist'); ?>" class="admin-card admin-report-filter admin-seo-publish-filter">
                <div class="admin-report-filter-head">
                    <div><span class="admin-badge">Publish Gate</span><h3>Filter checklist publish</h3></div>
                    <p>Pilih kesiapan konten, status board, atau cari task tertentu sebelum diproses ke editor.</p>
                </div>
                <div class="admin-report-filter-grid admin-report-filter-grid--wide">
                    <label><span>Kesiapan</span>
                        <select name="readiness">
                            <?php foreach (['all' => 'Semua', 'ready' => 'Siap Publish', 'almost' => 'Hampir Siap', 'needs_fix' => 'Perlu Fix'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $readiness === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Status Board</span>
                        <select name="status">
                            <option value="all">Semua</option>
                            <?php foreach (seo_execution_statuses() as $key => $row): ?>
                                <option value="<?= esc($key); ?>" <?= $status === $key ? 'selected' : ''; ?>><?= esc((string)$row['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Cari Task</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, slug, keyword, target..."></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-publish-checklist'); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_publish_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_publish_url(['export' => 'json'])); ?>">Export JSON</a>
                </div>
            </form>

            <div class="admin-grid admin-grid--two admin-seo-publish-layout">
                <div class="admin-card admin-seo-publish-gate">
                    <?php if ($selectedTask): ?>
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="<?= esc((string)($selectedScore['class'] ?? 'admin-status-pill admin-status-pill--warning')); ?>"><?= esc((string)($selectedScore['status'] ?? 'Perlu Dilengkapi')); ?></span>
                                <h2><?= esc((string)($selectedTask['title'] ?? 'Task SEO')); ?></h2>
                                <p>Score checklist: <strong><?= (int)($selectedScore['score'] ?? 0); ?>/100</strong> · Status board: <?= esc(seo_execution_status_label((string)($selectedTask['status'] ?? 'todo'))); ?></p>
                            </div>
                            <div class="admin-seo-publish-score-ring"><strong><?= (int)($selectedScore['score'] ?? 0); ?></strong><small>/100</small></div>
                        </div>

                        <div class="admin-seo-publish-snippet">
                            <span>Snippet Preview</span>
                            <strong><?= esc((string)($selectedTask['meta_title_template'] ?? $selectedTask['title'] ?? 'Judul SEO')); ?></strong>
                            <small><?= esc((string)($selectedTask['suggested_slug'] ?? 'slug-seo')); ?></small>
                            <p><?= esc((string)($selectedTask['meta_description_template'] ?? 'Meta description akan muncul di sini.')); ?></p>
                        </div>

                        <div class="admin-seo-publish-checklist">
                            <?php foreach ((array)($selectedScore['checks'] ?? []) as $check): ?>
                                <div class="admin-seo-publish-check <?= !empty($check['pass']) ? 'is-pass' : 'is-fail'; ?>">
                                    <span><?= !empty($check['pass']) ? '✓' : '!'; ?></span>
                                    <div>
                                        <strong><?= esc((string)($check['label'] ?? 'Checklist')); ?></strong>
                                        <p><?= esc((string)($check['hint'] ?? 'Lengkapi bagian ini.')); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="admin-seo-publish-actions">
                            <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_publish_url(['task' => (string)($selectedTask['id'] ?? '')])); ?>">Refresh Checklist</a>
                            <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-execution-board?view=draft&task=' . rawurlencode((string)($selectedTask['id'] ?? '')))); ?>">Buka Draft Pack</a>
                            <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/seo-draft-publisher?task=' . rawurlencode((string)($selectedTask['id'] ?? '')))); ?>">Buat Draft Artikel</a>
                            <?php if ((int)($selectedScore['score'] ?? 0) >= 75): ?>
                                <form method="post" class="admin-inline-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="mark_ready">
                                    <input type="hidden" name="task_id" value="<?= esc((string)($selectedTask['id'] ?? '')); ?>">
                                    <button class="admin-btn admin-btn--soft" type="submit">Tandai Siap Publish</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="admin-empty-state"><h2>Belum ada task SEO.</h2><p>Buat brief dulu dari SEO Content Planner agar checklist publish bisa dibuat.</p></div>
                    <?php endif; ?>
                </div>

                <div class="admin-card admin-seo-publish-task-list">
                    <div class="admin-form-head"><h2>Task Publish Queue</h2><p><?= count($filteredTasks); ?> task sesuai filter. Klik salah satu untuk membuka quality gate.</p></div>
                    <div class="admin-seo-publish-queue">
                        <?php foreach (array_slice($filteredTasks, 0, 30) as $task): ?>
                            <a class="admin-seo-publish-task <?= ((string)($task['id'] ?? '') === $selectedTaskId) ? 'is-active' : ''; ?>" href="<?= esc(admin_seo_publish_url(['task' => (string)($task['id'] ?? '')])); ?>">
                                <span class="<?= esc((string)($task['publish_status_class'] ?? 'admin-status-pill admin-status-pill--warning')); ?>"><?= esc((string)($task['publish_status'] ?? 'Perlu Fix')); ?></span>
                                <strong><?= esc((string)($task['title'] ?? 'Task SEO')); ?></strong>
                                <small><?= esc((string)($task['target_title'] ?? 'Halaman target')); ?></small>
                                <i><?= (int)($task['publish_score'] ?? 0); ?>/100</i>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$filteredTasks): ?>
                            <div class="admin-empty-state"><h2>Tidak ada task sesuai filter.</h2><p>Coba reset filter atau buka SEO Execution Board.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
