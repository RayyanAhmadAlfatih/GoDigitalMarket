<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$allowedViews = ['board', 'list', 'draft'];
$view = (string)($_GET['view'] ?? 'board');
if (!in_array($view, $allowedViews, true)) {
    $view = 'board';
}

$status = (string)($_GET['status'] ?? 'all');
if ($status !== 'all' && !array_key_exists($status, seo_execution_statuses())) {
    $status = 'all';
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
$selectedTaskId = trim((string)($_GET['task'] ?? ''));
$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_task') {
        $taskId = trim((string)($_POST['task_id'] ?? ''));
        $ok = seo_execution_save_task_state($taskId, [
            'status' => (string)($_POST['status'] ?? 'todo'),
            'owner' => (string)($_POST['owner'] ?? ''),
            'due_date' => (string)($_POST['due_date'] ?? ''),
            'note' => (string)($_POST['note'] ?? ''),
        ]);
        $message = $ok ? 'Task SEO berhasil diperbarui.' : 'Task SEO gagal diperbarui.';
        if (!$ok) {
            $error = $message;
            $message = '';
        }
    }

    if ($action === 'quick_status') {
        $taskId = trim((string)($_POST['task_id'] ?? ''));
        $currentTasks = seo_execution_tasks();
        $current = null;
        foreach ($currentTasks as $task) {
            if ((string)($task['id'] ?? '') === $taskId) {
                $current = $task;
                break;
            }
        }
        $ok = seo_execution_save_task_state($taskId, [
            'status' => (string)($_POST['status'] ?? 'todo'),
            'owner' => (string)($current['owner'] ?? ''),
            'due_date' => (string)($current['due_date'] ?? ''),
            'note' => (string)($current['note'] ?? ''),
        ]);
        $message = $ok ? 'Status task berhasil diubah.' : 'Status task gagal diubah.';
        if (!$ok) {
            $error = $message;
            $message = '';
        }
    }

    if ($action === 'reset_board') {
        $ok = seo_execution_reset_state();
        $message = $ok ? 'Board dikembalikan ke status awal dari SEO Content Planner.' : 'Gagal reset board.';
        if (!$ok) {
            $error = $message;
            $message = '';
        }
    }
}

$allTasks = seo_execution_tasks();
$filteredTasks = seo_execution_filtered_tasks($allTasks, [
    'status' => $status,
    'week' => $week,
    'priority' => $priority,
    'q' => $q,
]);
$metrics = seo_execution_metrics($allTasks);
$selectedTask = null;
if ($selectedTaskId !== '') {
    foreach ($allTasks as $task) {
        if ((string)($task['id'] ?? '') === $selectedTaskId) {
            $selectedTask = $task;
            break;
        }
    }
}
if ($view === 'draft' && $selectedTask === null && $filteredTasks) {
    $selectedTask = $filteredTasks[0];
    $selectedTaskId = (string)($selectedTask['id'] ?? '');
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-execution-board-' . date('Ymd-His') . '.json"');
    echo json_encode([
        'generated_at' => date('Y-m-d H:i:s'),
        'metrics' => $metrics,
        'filters' => ['view' => $view, 'status' => $status, 'week' => $week, 'priority' => $priority, 'q' => $q],
        'tasks' => $filteredTasks,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="seo-execution-board-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['status','priority','week','title','target','slug','keywords','owner','due_date','internal_link_anchor','note'], ',', '"', '\\', "\n");
    foreach ($filteredTasks as $task) {
        fputcsv($out, [
            seo_execution_status_label((string)($task['status'] ?? 'todo')),
            (string)($task['priority_label'] ?? ''),
            (string)($task['week'] ?? ''),
            (string)($task['title'] ?? ''),
            (string)($task['target_title'] ?? ''),
            (string)($task['suggested_slug'] ?? ''),
            implode(', ', (array)($task['keyword_seed'] ?? [])),
            (string)($task['owner'] ?? ''),
            (string)($task['due_date'] ?? ''),
            (string)($task['internal_link_anchor'] ?? ''),
            (string)($task['note'] ?? ''),
        ], ',', '"', '\\', "\n");
    }
    fclose($out);
    exit;
}

function admin_seo_execution_url(array $overrides = []): string
{
    $query = array_merge([
        'view' => $_GET['view'] ?? 'board',
        'status' => $_GET['status'] ?? 'all',
        'week' => $_GET['week'] ?? 'all',
        'priority' => $_GET['priority'] ?? 'all',
        'q' => $_GET['q'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null && $value !== 'all');
    return url('admin/seo-execution-board' . ($query ? '?' . http_build_query($query) : ''));
}

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'SEO Execution Board - Admin',
    'description' => 'Kanban eksekusi konten SEO, status produksi, draft pack, dan checklist publish untuk scale organic traffic.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-seo-execution-shell">
    <section class="admin-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Konten & SEO</div>
                <h1>SEO Execution Board</h1>
                <p>Ubah content planner menjadi papan kerja: ide, penulisan, review SEO, internal link, siap publish, sampai published. Jadi growth SEO tidak berhenti di rekomendasi.</p>
            </div>
            <div class="admin-toolbar__actions">
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-content-planner'); ?>">Content Planner</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/universal-seo'); ?>">Audit SEO</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-publish-checklist'); ?>">Publish Checklist</a>
                <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-draft-publisher'); ?>">Draft Publisher</a>
                <a class="admin-btn admin-btn--primary" href="<?= url('admin/artikel?action=create'); ?>">Tulis Artikel</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-report-grid admin-report-grid--four admin-seo-execution-metrics">
                <div class="admin-card admin-report-metric"><span class="admin-badge">Total Task</span><h2><?= (int)($metrics['total'] ?? 0); ?></h2><p>Task otomatis dari content planner dan backlog topik.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Progress</span><h2><?= (int)($metrics['progress_percent'] ?? 0); ?>%</h2><p>Konten yang sudah siap publish atau published.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Prioritas Tinggi</span><h2><?= (int)($metrics['high_priority'] ?? 0); ?></h2><p>Task yang paling dekat ke dampak trafik dan conversion.</p></div>
                <div class="admin-card admin-report-metric"><span class="admin-badge">Perlu Kejar</span><h2><?= (int)($metrics['overdue'] ?? 0); ?></h2><p>Task aktif yang tanggal targetnya sudah lewat.</p></div>
            </div>

            <div class="admin-card admin-seo-execution-focus">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Execution Layer</span>
                        <h2>Dari Rencana SEO Jadi Produksi Konten</h2>
                        <p>Board ini menjaga agar rekomendasi SEO benar-benar dieksekusi: tulis konten, review snippet, pasang internal link, publish, lalu pantau Growth Insight.</p>
                    </div>
                    <div class="admin-seo-execution-progress">
                        <strong><?= (int)($metrics['counts']['published'] ?? 0); ?></strong>
                        <small>Published</small>
                    </div>
                </div>
                <div class="admin-seo-execution-status-strip">
                    <?php foreach (seo_execution_statuses() as $key => $row): ?>
                        <a href="<?= esc(admin_seo_execution_url(['status' => $key, 'view' => 'board'])); ?>">
                            <span><?= esc((string)$row['label']); ?></span>
                            <strong><?= (int)($metrics['counts'][$key] ?? 0); ?></strong>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="get" action="<?= url('admin/seo-execution-board'); ?>" class="admin-card admin-report-filter admin-seo-execution-filter">
                <div class="admin-report-filter-head">
                    <div><span class="admin-badge">Board Filter</span><h3>Atur fokus eksekusi</h3></div>
                    <p>Pilih tampilan board/list/draft pack, filter status, minggu, prioritas, atau cari topik tertentu.</p>
                </div>
                <div class="admin-report-filter-grid admin-report-filter-grid--wide">
                    <label><span>Tampilan</span>
                        <select name="view">
                            <?php foreach (['board' => 'Board Kanban', 'list' => 'List Detail', 'draft' => 'Draft Pack'] as $value => $label): ?>
                                <option value="<?= esc($value); ?>" <?= $view === $value ? 'selected' : ''; ?>><?= esc($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Status</span>
                        <select name="status">
                            <option value="all">Semua</option>
                            <?php foreach (seo_execution_statuses() as $key => $row): ?>
                                <option value="<?= esc($key); ?>" <?= $status === $key ? 'selected' : ''; ?>><?= esc((string)$row['label']); ?></option>
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
                    <label><span>Cari</span><input type="search" name="q" value="<?= esc($q); ?>" placeholder="judul, target, keyword, owner..."></label>
                </div>
                <div class="admin-report-filter-actions">
                    <button class="admin-btn admin-btn--primary" type="submit">Terapkan</button>
                    <a class="admin-btn admin-btn--soft" href="<?= url('admin/seo-execution-board'); ?>">Reset</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_execution_url(['export' => 'csv'])); ?>">Export CSV</a>
                    <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_execution_url(['export' => 'json'])); ?>">Export JSON</a>
                    <button class="admin-btn admin-btn--soft" type="submit" form="seoExecutionResetForm" onclick="return confirm('Reset semua status dan catatan board?');">Reset Board</button>
                </div>
            </form>
            <form id="seoExecutionResetForm" method="post" class="admin-hidden-form">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="reset_board">
            </form>

            <?php if ($view === 'draft'): ?>
                <div class="admin-grid admin-grid--two admin-seo-execution-draft-layout">
                    <div class="admin-card admin-seo-execution-task-picker">
                        <div class="admin-form-head"><h2>Pilih Draft Pack</h2><p>Pilih task untuk melihat paket brief siap tempel ke editor artikel.</p></div>
                        <?php foreach (array_slice($filteredTasks, 0, 18) as $task): ?>
                            <a class="admin-seo-execution-picker-row <?= ((string)($task['id'] ?? '') === $selectedTaskId) ? 'is-active' : ''; ?>" href="<?= esc(admin_seo_execution_url(['view' => 'draft', 'task' => (string)($task['id'] ?? '')])); ?>">
                                <span class="<?= esc(seo_execution_priority_class((int)($task['priority_score'] ?? 0))); ?>"><?= esc((string)($task['priority_label'] ?? 'Sedang')); ?></span>
                                <strong><?= esc((string)($task['title'] ?? 'Task SEO')); ?></strong>
                                <small><?= esc((string)($task['target_title'] ?? '-')); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-card admin-seo-execution-draft-pack">
                        <?php if ($selectedTask): ?>
                            <div class="admin-form-head admin-form-head--split">
                                <div><span class="<?= esc(seo_execution_status_class((string)($selectedTask['status'] ?? 'todo'))); ?>"><?= esc(seo_execution_status_label((string)($selectedTask['status'] ?? 'todo'))); ?></span><h2><?= esc((string)($selectedTask['title'] ?? 'Draft konten')); ?></h2><p><?= esc((string)($selectedTask['brief_note'] ?? 'Lengkapi draft ini sebelum publish.')); ?></p></div>
                                <a class="admin-btn admin-btn--primary" href="<?= esc(url('admin/seo-draft-publisher?task=' . rawurlencode((string)($selectedTask['id'] ?? '')))); ?>">Buat Draft Artikel</a>
                            </div>
                            <div class="admin-seo-content-copybox admin-seo-execution-copybox">
                                <strong>SEO Pack</strong>
                                <p>Slug: <code><?= esc((string)($selectedTask['suggested_slug'] ?? '')); ?></code></p>
                                <p>Meta title: <?= esc((string)($selectedTask['meta_title_template'] ?? '')); ?></p>
                                <p>Meta description: <?= esc((string)($selectedTask['meta_description_template'] ?? '')); ?></p>
                                <p>Focus keyword: <code><?= esc(implode(', ', (array)($selectedTask['keyword_seed'] ?? []))); ?></code></p>
                                <p>Internal link anchor: <code><?= esc((string)($selectedTask['internal_link_anchor'] ?? '')); ?></code></p>
                            </div>
                            <div class="admin-seo-execution-draft-html">
                                <h3>Isi Draft Artikel Siap Tempel</h3>
                                <textarea rows="18" readonly><?= esc(seo_execution_article_draft_html($selectedTask)); ?></textarea>
                            </div>
                            <form method="post" class="admin-seo-execution-update-form">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="update_task">
                                <input type="hidden" name="task_id" value="<?= esc((string)($selectedTask['id'] ?? '')); ?>">
                                <div class="admin-report-filter-grid">
                                    <label><span>Status</span><select name="status"><?php foreach (seo_execution_statuses() as $key => $row): ?><option value="<?= esc($key); ?>" <?= ((string)($selectedTask['status'] ?? '') === $key) ? 'selected' : ''; ?>><?= esc((string)$row['label']); ?></option><?php endforeach; ?></select></label>
                                    <label><span>Owner</span><input name="owner" value="<?= esc((string)($selectedTask['owner'] ?? '')); ?>" placeholder="nama PIC"></label>
                                    <label><span>Target Selesai</span><input type="date" name="due_date" value="<?= esc((string)($selectedTask['due_date'] ?? '')); ?>"></label>
                                </div>
                                <label><span>Catatan</span><textarea name="note" rows="3" placeholder="catatan produksi, revisi, atau internal link..."><?= esc((string)($selectedTask['note'] ?? '')); ?></textarea></label>
                                <button class="admin-btn admin-btn--primary" type="submit">Simpan Status</button>
                            </form>
                        <?php else: ?>
                            <div class="admin-empty-state"><h2>Belum ada draft pack.</h2><p>Pastikan SEO Content Planner punya brief konten.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($view === 'list'): ?>
                <div class="admin-card admin-table-card">
                    <div class="admin-form-head admin-form-head--split"><div><h2>List Detail Eksekusi</h2><p><?= count($filteredTasks); ?> task sesuai filter.</p></div><a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_execution_url(['view' => 'board'])); ?>">Board View</a></div>
                    <div class="admin-table-responsive">
                        <table class="admin-table">
                            <thead><tr><th>Status</th><th>Task</th><th>Prioritas</th><th>Minggu</th><th>PIC</th><th>Target</th><th>Aksi</th></tr></thead>
                            <tbody>
                            <?php foreach ($filteredTasks as $task): ?>
                                <tr>
                                    <td><span class="<?= esc(seo_execution_status_class((string)($task['status'] ?? 'todo'))); ?>"><?= esc(seo_execution_status_label((string)($task['status'] ?? 'todo'))); ?></span></td>
                                    <td><strong><?= esc((string)($task['title'] ?? 'Task SEO')); ?></strong><small><?= esc((string)($task['target_title'] ?? '-')); ?></small></td>
                                    <td><span class="<?= esc(seo_execution_priority_class((int)($task['priority_score'] ?? 0))); ?>"><?= esc((string)($task['priority_label'] ?? 'Sedang')); ?></span></td>
                                    <td><?= esc((string)($task['week'] ?? '')); ?></td>
                                    <td><?= esc((string)(($task['owner'] ?? '') ?: '-')); ?></td>
                                    <td><?= esc((string)(($task['due_date'] ?? '') ?: '-')); ?></td>
                                    <td><a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_execution_url(['view' => 'draft', 'task' => (string)($task['id'] ?? '')])); ?>">Draft</a> <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-draft-publisher?task=' . rawurlencode((string)($task['id'] ?? '')))); ?>">Publisher</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-seo-execution-board">
                    <?php foreach (seo_execution_statuses() as $statusKey => $statusRow): ?>
                        <?php $columnTasks = array_values(array_filter($filteredTasks, static fn(array $task): bool => (string)($task['status'] ?? 'todo') === (string)$statusKey)); ?>
                        <section class="admin-card admin-seo-execution-column">
                            <div class="admin-seo-execution-column__head">
                                <div><span class="<?= esc((string)$statusRow['class']); ?>"><?= esc((string)$statusRow['label']); ?></span><p><?= esc((string)$statusRow['hint']); ?></p></div>
                                <strong><?= count($columnTasks); ?></strong>
                            </div>
                            <div class="admin-seo-execution-cards">
                                <?php foreach ($columnTasks as $task): ?>
                                    <article class="admin-seo-execution-card">
                                        <div class="admin-seo-execution-card__top">
                                            <span class="<?= esc(seo_execution_priority_class((int)($task['priority_score'] ?? 0))); ?>"><?= esc((string)($task['priority_label'] ?? 'Sedang')); ?></span>
                                            <small><?= esc((string)($task['week'] ?? '')); ?></small>
                                        </div>
                                        <h3><?= esc((string)($task['title'] ?? 'Task SEO')); ?></h3>
                                        <p><?= esc((string)($task['brief_note'] ?? 'Lengkapi task SEO ini.')); ?></p>
                                        <div class="admin-seo-content-keywords">
                                            <?php foreach (array_slice((array)($task['keyword_seed'] ?? []), 0, 4) as $keyword): ?><span><?= esc((string)$keyword); ?></span><?php endforeach; ?>
                                        </div>
                                        <div class="admin-seo-execution-card__meta">
                                            <span>Target: <?= esc((string)($task['target_title'] ?? '-')); ?></span>
                                            <span>Due: <?= esc((string)(($task['due_date'] ?? '') ?: '-')); ?></span>
                                            <?php if (trim((string)($task['owner'] ?? '')) !== ''): ?><span>PIC: <?= esc((string)$task['owner']); ?></span><?php endif; ?>
                                        </div>
                                        <div class="admin-row-actions">
                                            <a class="admin-btn admin-btn--soft" href="<?= esc(admin_seo_execution_url(['view' => 'draft', 'task' => (string)($task['id'] ?? '')])); ?>">Draft</a>
                                            <?php if ($statusKey !== 'published'): ?>
                                                <?php $next = ['todo' => 'writing', 'writing' => 'review', 'review' => 'internal_link', 'internal_link' => 'ready', 'ready' => 'published'][$statusKey] ?? 'todo'; ?>
                                                <form method="post" class="admin-inline-form">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="quick_status">
                                                    <input type="hidden" name="task_id" value="<?= esc((string)($task['id'] ?? '')); ?>">
                                                    <input type="hidden" name="status" value="<?= esc($next); ?>">
                                                    <button class="admin-btn admin-btn--primary" type="submit">Lanjut</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                                <?php if (!$columnTasks): ?><div class="admin-seo-execution-empty">Belum ada task di kolom ini.</div><?php endif; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
