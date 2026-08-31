<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';
$allowedDays = [7, 14, 30, 60, 90, 180];
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, $allowedDays, true)) {
    $days = 30;
}

$typeOptions = function_exists('seo_profit_type_options') ? seo_profit_type_options() : ['all' => 'Semua SEO Page'];
$type = (string)($_GET['type'] ?? 'all');
if (!isset($typeOptions[$type])) {
    $type = 'all';
}

$priorityOptions = function_exists('seo_money_summary') ? (array)(seo_money_summary($days, $type, 'all')['priority_options'] ?? []) : ['all' => 'Semua Prioritas'];
$priority = (string)($_GET['priority'] ?? 'all');
if (!isset($priorityOptions[$priority])) {
    $priority = 'all';
}

$statusOptions = function_exists('money_deploy_filter_options') ? money_deploy_filter_options() : ['all' => 'Semua Status'];
$status = (string)($_GET['status'] ?? 'all');
if (!isset($statusOptions[$status])) {
    $status = 'all';
}

$redirectBase = static function (string $message = '') use ($days, $type, $priority, $status): string {
    $query = [
        'days' => $days,
        'type' => $type,
        'priority' => $priority,
        'status' => $status,
    ];
    if ($message !== '') {
        $query['message'] = $message;
    }
    return 'admin/money-page-deployment-checklist?' . http_build_query($query);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'update_task') {
            money_deploy_update_task(
                (string)($_POST['page_id'] ?? ''),
                (string)($_POST['task_id'] ?? ''),
                (string)($_POST['task_status'] ?? 'pending'),
                (string)($_POST['task_note'] ?? '')
            );
            redirect_302($redirectBase('Checklist task berhasil disimpan.'));
        }

        if ($action === 'page_note') {
            money_deploy_update_page_note(
                (string)($_POST['page_id'] ?? ''),
                (string)($_POST['owner_note'] ?? '')
            );
            redirect_302($redirectBase('Catatan halaman berhasil disimpan.'));
        }

        if ($action === 'mark_page_done') {
            money_deploy_mark_page_done(
                (string)($_POST['page_id'] ?? ''),
                (array)($_POST['task_ids'] ?? [])
            );
            redirect_302($redirectBase('Semua task halaman ini sudah ditandai selesai.'));
        }

        if ($action === 'reset_page') {
            money_deploy_reset_page((string)($_POST['page_id'] ?? ''));
            redirect_302($redirectBase('Checklist halaman sudah direset.'));
        }

        if ($action === 'reset_all') {
            money_deploy_reset_all();
            redirect_302($redirectBase('Semua catatan checklist deployment sudah direset.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$summary = money_deploy_summary($days, $type, $priority, $status);
$items = (array)($summary['items'] ?? []);
$counts = (array)($summary['counts'] ?? []);
$topItem = is_array($summary['top_item'] ?? null) ? (array)$summary['top_item'] : null;
$statusOptions = (array)($summary['status_options'] ?? $statusOptions);
$typeOptions = (array)($summary['type_options'] ?? $typeOptions);
$priorityOptions = (array)($summary['priority_options'] ?? $priorityOptions);
$taskStatusOptions = money_deploy_status_options();

$baseUrl = static function (array $override = []) use ($days, $type, $priority, $status): string {
    $query = array_merge([
        'days' => $days,
        'type' => $type,
        'priority' => $priority,
        'status' => $status,
    ], $override);
    return url('admin/money-page-deployment-checklist?' . http_build_query($query));
};

if (($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="money-page-deployment-checklist-' . date('Ymd-His') . '.json"');
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_GET['export'] ?? '') === 'csv') {
    money_deploy_export_csv($items);
}

$priorityLabel = static function (string $priority): string {
    return match ($priority) {
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        default => ucfirst($priority ?: 'Prioritas'),
    };
};

$taskPriorityLabel = static function (string $priority): string {
    return match ($priority) {
        'critical' => 'Kritis',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        default => ucfirst($priority ?: 'Medium'),
    };
};

$formatPercent = static function (mixed $value): string {
    return rtrim(rtrim(number_format((float)$value, 1, ',', '.'), '0'), ',') . '%';
};

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Money Page Deployment Checklist - Admin',
    'description' => 'Checklist eksekusi rekomendasi Money Page Optimizer untuk konten, CTA, internal link, trust block, offer, dan monitoring hasil.',
    'robots' => 'noindex, nofollow',
]);

$pageTitle = 'Money Page Deployment Checklist';
$pageDescription = 'Checklist eksekusi untuk memasang rekomendasi SEO Money Page Optimizer: edit konten, CTA, internal link, trust block, offer, dan monitoring hasil.';

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-money-deploy-shell">
    <section class="admin-hero admin-money-deploy-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Money Page Deployment Checklist</h1>
                <p>Ubah brief Money Page Optimizer menjadi pekerjaan nyata: edit konten, pasang CTA, tambah internal link, lengkapi trust block, sinkron offer, lalu pantau hasilnya.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Money Page Optimizer</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement')); ?>">CTA Placement</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-assisted-journey')); ?>">Journey Map</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-result-tracker')); ?>">CTA Result</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/internal-link-cta-injection')); ?>">Link & CTA Injection</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="admin-cta-result-overview admin-money-deploy-overview">
                <article class="admin-card admin-cta-result-score-card">
                    <span class="admin-badge">Deployment Progress</span>
                    <div class="admin-cta-result-score-ring" style="--score:<?= (int)($summary['average_progress'] ?? 0); ?>;">
                        <strong><?= (int)($summary['average_progress'] ?? 0); ?></strong><span>%</span>
                    </div>
                    <h2>Rekomendasi → Eksekusi</h2>
                    <p><?= esc((string)($summary['top_focus'] ?? 'Kerjakan checklist prioritas, lalu pantau hasilnya.')); ?></p>
                </article>

                <article class="admin-card admin-cta-result-metric-card">
                    <span class="admin-badge">Checklist Aktif</span>
                    <div class="admin-cta-result-mini-metrics">
                        <span><strong><?= (int)($counts['total'] ?? 0); ?></strong> page</span>
                        <span><strong><?= (int)($counts['working'] ?? 0); ?></strong> dikerjakan</span>
                        <span><strong><?= (int)($counts['blocked'] ?? 0); ?></strong> tertahan</span>
                        <span><strong><?= (int)($counts['done'] ?? 0); ?></strong> selesai</span>
                    </div>
                    <p>Checklist ini meneruskan <strong>SEO Money Page Optimizer</strong>. Tracking tetap membaca data existing dari Lead Tracking dan CTA Result.</p>
                </article>

                <article class="admin-card admin-cta-result-health-card">
                    <span class="admin-badge">Task Progress</span>
                    <h2><?= (int)($counts['tasks_done'] ?? 0); ?> / <?= (int)($counts['tasks_total'] ?? 0); ?> task selesai</h2>
                    <p><?= (int)($counts['tasks_blocked'] ?? 0); ?> task tertahan. Fokus bereskan hambatan agar halaman potensial tidak mandek sebelum menghasilkan lead/order.</p>
                    <div class="admin-cta-result-export-row">
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--light" href="<?= esc($baseUrl(['export' => 'json'])); ?>">Export JSON</a>
                    </div>
                </article>
            </div>

            <section class="admin-card admin-cta-result-filter-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Filter Deployment</span>
                        <h2>Periode, tipe halaman, prioritas, dan status</h2>
                        <p>Mulai dari halaman prioritas tinggi yang paling dekat ke klik, lead, order, atau payment.</p>
                    </div>
                </div>
                <div class="admin-seo-profit-filter-row">
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($allowedDays as $rangeDays): ?>
                            <a class="<?= $days === $rangeDays ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['days' => $rangeDays])); ?>"><?= (int)$rangeDays; ?> hari</a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($typeOptions as $typeKey => $typeLabel): ?>
                            <a class="<?= $type === (string)$typeKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['type' => (string)$typeKey])); ?>"><?= esc((string)$typeLabel); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($priorityOptions as $priorityKey => $priorityText): ?>
                            <a class="<?= $priority === (string)$priorityKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['priority' => (string)$priorityKey])); ?>"><?= esc((string)$priorityText); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="admin-cta-result-range-tabs">
                        <?php foreach ($statusOptions as $statusKey => $statusText): ?>
                            <a class="<?= $status === (string)$statusKey ? 'is-active' : ''; ?>" href="<?= esc($baseUrl(['status' => (string)$statusKey])); ?>"><?= esc((string)$statusText); ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="admin-card admin-cta-result-bridge-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Execution Flow</span>
                        <h2>Dari rekomendasi sampai hasil</h2>
                        <p>Alur ini menjaga agar dashboard tidak berhenti di insight. Admin tahu halaman mana dikerjakan, apa yang dipasang, dan kapan hasilnya dicek.</p>
                    </div>
                </div>
                <div class="admin-cta-result-flow admin-money-deploy-flow">
                    <div><strong>Money Page Optimizer</strong><span>Menentukan brief CTA, link, trust, offer, dan content fix.</span></div>
                    <div><strong>Deployment Checklist</strong><span>Mengubah brief menjadi task eksekusi yang bisa dicentang.</span></div>
                    <div><strong>CTA Result</strong><span>Membaca hasil CTA dari Lead Tracking existing.</span></div>
                    <div><strong>Journey Map</strong><span>Memastikan halaman bergerak dari SEO → klik → lead → order.</span></div>
                </div>
            </section>

            <section class="admin-card admin-money-deploy-next-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Next Actions</span>
                        <h2>Aksi terdekat yang perlu dikerjakan</h2>
                        <p>Ini ringkasan task paling dekat untuk mempercepat eksekusi money page.</p>
                    </div>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-action-dashboard')); ?>">Profit Action</a>
                </div>
                <?php $nextActions = (array)($summary['next_actions'] ?? []); ?>
                <?php if (!$nextActions): ?>
                    <div class="admin-empty-state"><p>Belum ada next action. Coba ganti filter atau cek Money Page Optimizer.</p></div>
                <?php else: ?>
                    <div class="admin-money-deploy-next-list">
                        <?php foreach (array_slice($nextActions, 0, 6) as $next): ?>
                            <?php $task = (array)($next['task'] ?? []); ?>
                            <article>
                                <span><?= esc((string)($task['category'] ?? 'Task')); ?> · Score <?= (int)($next['money_score'] ?? 0); ?></span>
                                <strong><?= esc((string)($task['title'] ?? 'Task deployment')); ?></strong>
                                <p><?= esc((string)($next['page_title'] ?? 'Money Page')); ?> — <?= esc((string)($task['description'] ?? 'Kerjakan task ini.')); ?></p>
                                <?php if (!empty($task['action_url'])): ?><a href="<?= esc((string)$task['action_url']); ?>">Kerjakan</a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-money-deploy-list-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Deployment Checklist</span>
                        <h2>Checklist per money page</h2>
                        <p>Centang progres kerja agar rekomendasi benar-benar jadi perubahan di halaman, bukan cuma catatan dashboard.</p>
                    </div>
                    <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer?days=' . $days . '&type=' . rawurlencode($type) . '&priority=' . rawurlencode($priority))); ?>">Buka Optimizer</a>
                </div>

                <?php if (!$items): ?>
                    <div class="admin-empty-state">
                        <h3>Belum ada checklist untuk filter ini</h3>
                        <p>Coba ganti filter, jalankan Money Page Optimizer, atau pastikan Lead Tracking tetap aktif agar sinyal halaman bisa terbaca.</p>
                    </div>
                <?php else: ?>
                    <div class="admin-money-deploy-list">
                        <?php foreach (array_slice($items, 0, 20) as $checklist): ?>
                            <?php
                            $optimizer = (array)($checklist['optimizer'] ?? []);
                            $page = (array)($optimizer['item'] ?? []);
                            $metrics = (array)($optimizer['metrics'] ?? []);
                            $countsItem = (array)($checklist['counts'] ?? []);
                            $tasks = (array)($checklist['tasks'] ?? []);
                            $nextTask = is_array($checklist['next_task'] ?? null) ? (array)$checklist['next_task'] : null;
                            $stage = (string)($checklist['deployment_stage'] ?? 'open');
                            ?>
                            <article class="admin-money-deploy-card is-<?= esc($stage); ?>">
                                <div class="admin-seo-journey-card__head">
                                    <div>
                                        <span class="admin-badge"><?= esc((string)($page['type_label'] ?? 'Halaman')); ?> · <?= esc($priorityLabel((string)($optimizer['priority'] ?? 'low'))); ?> · <?= esc((string)($checklist['deployment_stage_label'] ?? 'Terbuka')); ?></span>
                                        <h3><?= esc((string)($page['title'] ?? 'Money Page')); ?></h3>
                                        <p><?= esc((string)($page['page_path'] ?? parse_url((string)($page['url'] ?? '/'), PHP_URL_PATH))); ?></p>
                                    </div>
                                    <div class="admin-money-deploy-progress">
                                        <strong><?= (int)($checklist['progress'] ?? 0); ?>%</strong>
                                        <span><?= (int)($countsItem['done'] ?? 0); ?>/<?= (int)($countsItem['total'] ?? 0); ?> task</span>
                                    </div>
                                </div>

                                <div class="admin-cta-result-stat-row">
                                    <span><strong><?= (int)($optimizer['money_score'] ?? 0); ?></strong> money score</span>
                                    <span><strong><?= (int)($metrics['clicks'] ?? 0); ?></strong> klik</span>
                                    <span><strong><?= (int)($metrics['leads'] ?? 0); ?></strong> lead</span>
                                    <span><strong><?= (int)($metrics['orders'] ?? 0); ?></strong> order</span>
                                    <span><strong><?= $formatPercent($metrics['lead_rate'] ?? 0); ?></strong> lead rate</span>
                                </div>

                                <?php if ($nextTask): ?>
                                    <div class="admin-money-deploy-next-task">
                                        <span>Next action</span>
                                        <strong><?= esc((string)($nextTask['title'] ?? 'Task berikutnya')); ?></strong>
                                        <p><?= esc((string)($nextTask['description'] ?? 'Kerjakan task berikutnya.')); ?></p>
                                        <?php if (!empty($nextTask['action_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$nextTask['action_url']); ?>">Kerjakan Sekarang</a><?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="admin-money-deploy-task-list">
                                    <?php foreach ($tasks as $task): ?>
                                        <details class="admin-money-deploy-task is-<?= esc((string)($task['status'] ?? 'pending')); ?>" <?= in_array((string)($task['status'] ?? 'pending'), ['pending', 'working', 'blocked'], true) ? 'open' : ''; ?>>
                                            <summary>
                                                <div>
                                                    <span><?= esc((string)($task['category'] ?? 'Task')); ?> · <?= esc($taskPriorityLabel((string)($task['priority'] ?? 'medium'))); ?></span>
                                                    <strong><?= esc((string)($task['title'] ?? 'Task deployment')); ?></strong>
                                                </div>
                                                <em><?= esc((string)($taskStatusOptions[(string)($task['status'] ?? 'pending')] ?? 'Belum dikerjakan')); ?></em>
                                            </summary>
                                            <div class="admin-money-deploy-task-body">
                                                <p><?= esc((string)($task['description'] ?? 'Kerjakan task ini.')); ?></p>
                                                <?php if (!empty($task['checkpoints'])): ?>
                                                    <ul>
                                                        <?php foreach ((array)$task['checkpoints'] as $checkpoint): ?><li><?= esc((string)$checkpoint); ?></li><?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                                <div class="admin-money-deploy-task-actions">
                                                    <?php if (!empty($task['action_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$task['action_url']); ?>"> <?= esc((string)($task['action_label'] ?? 'Kerjakan')); ?></a><?php endif; ?>
                                                    <?php if (!empty($page['url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$page['url']); ?>" target="_blank" rel="noopener">Lihat Page</a><?php endif; ?>
                                                </div>
                                                <form method="post" action="<?= esc($baseUrl()); ?>" class="admin-money-deploy-task-form">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="action" value="update_task">
                                                    <input type="hidden" name="page_id" value="<?= esc((string)($checklist['page_id'] ?? '')); ?>">
                                                    <input type="hidden" name="task_id" value="<?= esc((string)($task['task_id'] ?? '')); ?>">
                                                    <label><span>Status</span><select name="task_status">
                                                        <?php foreach ($taskStatusOptions as $value => $label): ?>
                                                            <option value="<?= esc((string)$value); ?>" <?= (string)($task['status'] ?? 'pending') === (string)$value ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                                                        <?php endforeach; ?>
                                                    </select></label>
                                                    <label><span>Catatan task</span><input type="text" name="task_note" value="<?= esc((string)($task['note'] ?? '')); ?>" placeholder="Contoh: CTA sudah dipasang di tengah artikel"></label>
                                                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Task</button>
                                                </form>
                                            </div>
                                        </details>
                                    <?php endforeach; ?>
                                </div>

                                <div class="admin-money-deploy-page-actions">
                                    <?php if (!empty($page['edit_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$page['edit_url']); ?>">Edit Konten</a><?php endif; ?>
                                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/seo-assisted-journey?days=' . $days . '&type=' . rawurlencode($type))); ?>">Cek Journey</a>
                                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/cta-result-tracker')); ?>">Cek CTA Result</a>
                                    <a class="admin-btn admin-btn--soft" href="<?= esc(url('admin/trust-conversion')); ?>">Trust Block</a>
                                </div>

                                <form method="post" action="<?= esc($baseUrl()); ?>" class="admin-money-deploy-note-form">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="page_note">
                                    <input type="hidden" name="page_id" value="<?= esc((string)($checklist['page_id'] ?? '')); ?>">
                                    <label><span>Catatan halaman</span><input type="text" name="owner_note" value="<?= esc((string)($checklist['owner_note'] ?? '')); ?>" placeholder="Contoh: target selesai minggu ini, fokus CTA + internal link dulu"></label>
                                    <button class="admin-btn admin-btn--primary" type="submit">Simpan Catatan</button>
                                </form>

                                <div class="admin-money-deploy-quick-actions">
                                    <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Tandai semua task halaman ini selesai?');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="mark_page_done">
                                        <input type="hidden" name="page_id" value="<?= esc((string)($checklist['page_id'] ?? '')); ?>">
                                        <?php foreach ($tasks as $task): ?><input type="hidden" name="task_ids[]" value="<?= esc((string)($task['task_id'] ?? '')); ?>"><?php endforeach; ?>
                                        <button class="admin-btn admin-btn--light" type="submit">Tandai Halaman Selesai</button>
                                    </form>
                                    <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset checklist halaman ini?');">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="reset_page">
                                        <input type="hidden" name="page_id" value="<?= esc((string)($checklist['page_id'] ?? '')); ?>">
                                        <button class="admin-btn admin-btn--danger" type="submit">Reset Halaman</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="admin-card admin-cta-result-danger-card">
                <div class="admin-card-header">
                    <div>
                        <span class="admin-badge">Reset Checklist</span>
                        <h2>Reset semua catatan deployment</h2>
                        <p>Ini hanya menghapus catatan checklist manual. Data Tracking Lead, CTA Result, SEO Journey, order, payment, artikel, produk, dan landing page tidak ikut dihapus.</p>
                    </div>
                    <form method="post" action="<?= esc($baseUrl()); ?>" onsubmit="return confirm('Reset semua catatan Money Page Deployment Checklist?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_all">
                        <button class="admin-btn admin-btn--danger" type="submit">Reset Semua Catatan</button>
                    </form>
                </div>
            </section>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
