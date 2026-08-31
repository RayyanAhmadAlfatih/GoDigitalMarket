<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';

$params = function_exists('report_filters_from_request') ? report_filters_from_request($_GET) : ['days' => 30, 'range' => '30', 'filters' => []];
$duration = (int)($_GET['duration'] ?? 14);
if (!in_array($duration, [7, 14, 30], true)) {
    $duration = 14;
}
$goal = (string)($_GET['goal'] ?? 'seo_to_sales');
$goalOptions = function_exists('profit_playbook_goal_options') ? profit_playbook_goal_options() : ['seo_to_sales' => 'SEO ke Penjualan'];
if (!array_key_exists($goal, $goalOptions)) {
    $goal = 'seo_to_sales';
}
$durationOptions = function_exists('profit_playbook_duration_options') ? profit_playbook_duration_options() : [14 => '14 Hari'];
$campaignId = function_exists('profit_playbook_campaign_id') ? profit_playbook_campaign_id($duration, $goal, (int)$params['days'], (array)$params['filters']) : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['action'] ?? '');
        $taskId = (string)($_POST['task_id'] ?? '');
        $postCampaignId = (string)($_POST['campaign_id'] ?? $campaignId);

        if ($action === 'complete_task') {
            profit_playbook_mark_completed($postCampaignId, $taskId, true);
            redirect_302('admin/profit-playbook?message=' . rawurlencode('Task campaign ditandai selesai.') . '&duration=' . $duration . '&goal=' . rawurlencode($goal) . '&range=' . rawurlencode((string)($params['range'] ?? '30')));
        }

        if ($action === 'undo_task') {
            profit_playbook_mark_completed($postCampaignId, $taskId, false);
            redirect_302('admin/profit-playbook?message=' . rawurlencode('Task campaign dibuka ulang.') . '&duration=' . $duration . '&goal=' . rawurlencode($goal) . '&range=' . rawurlencode((string)($params['range'] ?? '30')));
        }

        if ($action === 'reset_campaign') {
            profit_playbook_reset_campaign($postCampaignId);
            redirect_302('admin/profit-playbook?message=' . rawurlencode('Progress campaign sudah direset.') . '&duration=' . $duration . '&goal=' . rawurlencode($goal) . '&range=' . rawurlencode((string)($params['range'] ?? '30')));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$campaign = function_exists('profit_playbook_campaign_summary') ? profit_playbook_campaign_summary($duration, $goal, (int)$params['days'], (array)$params['filters']) : [];
$summary = (array)($campaign['summary'] ?? []);
$report = (array)($summary['report'] ?? []);
$todayActions = array_slice((array)($summary['today_plan'] ?? []), 0, 4);
$rangeLabel = function_exists('report_range_label') ? report_range_label((string)$params['range'], $params) : '30 hari terakhir';
$goalMeta = (array)($campaign['goal_meta'] ?? []);

function admin_profit_playbook_url(array $overrides = []): string
{
    $query = array_merge([
        'duration' => $_GET['duration'] ?? '14',
        'goal' => $_GET['goal'] ?? 'seo_to_sales',
        'range' => $_GET['range'] ?? '30',
        'year' => $_GET['year'] ?? date('Y'),
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
    ], $overrides);
    $query = array_filter($query, static fn($value): bool => $value !== '' && $value !== null);
    return url('admin/profit-playbook' . ($query ? '?' . http_build_query($query) : ''));
}

if ((string)($_GET['export'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="profit-playbook-' . date('Ymd-His') . '.json"');
    echo json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ((string)($_GET['export'] ?? '') === 'csv' && function_exists('profit_playbook_export_csv')) {
    profit_playbook_export_csv($campaign);
}

$GLOBALS['admin_page'] = true;
set_seo([
    'title' => 'Profit Playbook & Campaign Planner - Admin',
    'description' => 'Planner campaign 7, 14, dan 30 hari untuk mengubah action profit, SEO, CTA, trust, dan follow-up menjadi eksekusi terarah.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-profit-playbook-shell">
    <section class="admin-hero admin-profit-playbook-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Marketing & Analytics</div>
                <h1>Profit Playbook & Campaign Planner</h1>
                <p>Susun campaign 7/14/30 hari dari sinyal profit, SEO, CTA, follow-up, trust, order, dan payment agar aksi marketing tidak random.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/profit-action-dashboard')); ?>">Profit Action</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/content-performance')); ?>">Content Performance</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/trust-conversion')); ?>">Trust & CTA</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/offer-cta-testing')); ?>">Offer Lab</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/seo-money-page-optimizer')); ?>">Money Page</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/cta-placement')); ?>">CTA Placement</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container admin-stack">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <form class="admin-card admin-profit-playbook-filter" method="get" action="<?= esc(url('admin/profit-playbook')); ?>">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Campaign Setup</span>
                        <h2>Pilih arah campaign</h2>
                        <p>Planner otomatis menyesuaikan task berdasarkan data <?= esc($rangeLabel); ?> dan progress campaign yang sedang dipilih.</p>
                    </div>
                    <div class="admin-report-filter-actions">
                        <button class="admin-btn admin-btn--primary" type="submit">Generate Planner</button>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_profit_playbook_url(['export' => 'csv'])); ?>">Export CSV</a>
                        <a class="admin-btn admin-btn--soft" href="<?= esc(admin_profit_playbook_url(['export' => 'json'])); ?>">Export JSON</a>
                    </div>
                </div>
                <div class="admin-report-filter-grid admin-profit-playbook-filter-grid">
                    <label><span>Durasi</span><select name="duration">
                        <?php foreach ($durationOptions as $value => $label): ?>
                            <option value="<?= (int)$value; ?>" <?= (int)$value === $duration ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label><span>Goal Campaign</span><select name="goal">
                        <?php foreach ($goalOptions as $value => $label): ?>
                            <option value="<?= esc((string)$value); ?>" <?= (string)$value === $goal ? 'selected' : ''; ?>><?= esc((string)$label); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label><span>Data Dibaca</span><select name="range">
                        <option value="7" <?= (string)($params['range'] ?? '') === '7' ? 'selected' : ''; ?>>7 hari terakhir</option>
                        <option value="30" <?= (string)($params['range'] ?? '') === '30' ? 'selected' : ''; ?>>30 hari terakhir</option>
                        <option value="90" <?= (string)($params['range'] ?? '') === '90' ? 'selected' : ''; ?>>90 hari terakhir</option>
                        <option value="year" <?= (string)($params['range'] ?? '') === 'year' ? 'selected' : ''; ?>>Tahun ini</option>
                    </select></label>
                </div>
            </form>

            <div class="admin-profit-playbook-overview">
                <article class="admin-card admin-profit-playbook-score">
                    <span class="admin-badge"><?= esc((string)($campaign['duration_label'] ?? 'Campaign')); ?></span>
                    <div class="admin-profit-playbook-progress-ring" style="--score:<?= (int)($campaign['progress'] ?? 0); ?>;">
                        <strong><?= (int)($campaign['progress'] ?? 0); ?></strong><span>%</span>
                    </div>
                    <h2><?= esc((string)($goalMeta['label'] ?? 'Profit Campaign')); ?></h2>
                    <p><?= esc((string)($goalMeta['headline'] ?? 'Campaign praktis untuk menaikkan peluang profit website.')); ?></p>
                    <small><?= (int)($campaign['completed'] ?? 0); ?> dari <?= (int)($campaign['total_tasks'] ?? 0); ?> task selesai</small>
                </article>

                <?php foreach ((array)($campaign['kpis'] ?? []) as $kpi): ?>
                    <article class="admin-card admin-profit-playbook-kpi">
                        <span><?= esc((string)($kpi['label'] ?? 'KPI')); ?></span>
                        <strong><?= esc((string)($kpi['value'] ?? '-')); ?></strong>
                        <p><?= esc((string)($kpi['note'] ?? 'Pantau metrik ini selama campaign.')); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="admin-profit-playbook-layout">
                <div class="admin-profit-playbook-main">
                    <div class="admin-card admin-profit-playbook-phase-card">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="admin-badge">Phase Map</span>
                                <h2>Peta campaign</h2>
                                <p>Progress per fase agar admin tahu campaign sedang di tahap apa.</p>
                            </div>
                            <form method="post" onsubmit="return confirm('Reset progress campaign ini?');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="reset_campaign">
                                <input type="hidden" name="campaign_id" value="<?= esc((string)($campaign['campaign_id'] ?? '')); ?>">
                                <button class="admin-btn admin-btn--soft" type="submit">Reset Progress</button>
                            </form>
                        </div>
                        <div class="admin-profit-playbook-phases">
                            <?php foreach ((array)($campaign['phases'] ?? []) as $phase): ?>
                                <div>
                                    <strong><?= esc((string)($phase['label'] ?? 'Phase')); ?></strong>
                                    <span><?= (int)($phase['completed'] ?? 0); ?>/<?= (int)($phase['total'] ?? 0); ?> task · <?= (int)($phase['progress'] ?? 0); ?>%</span>
                                    <em style="--value:<?= (int)($phase['progress'] ?? 0); ?>%"></em>
                                    <small>Hari <?= esc(implode(', ', array_map('strval', (array)($phase['days'] ?? [])))); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-profit-playbook-timeline">
                        <?php foreach ((array)($campaign['daily'] ?? []) as $day): ?>
                            <section class="admin-card admin-profit-playbook-day">
                                <div class="admin-profit-playbook-day-head">
                                    <div class="admin-profit-playbook-day-number">
                                        <span>Hari</span><strong><?= (int)($day['day'] ?? 0); ?></strong><small><?= esc((string)($day['date_hint'] ?? '')); ?></small>
                                    </div>
                                    <div>
                                        <span class="admin-badge"><?= (int)($day['completed'] ?? 0); ?>/<?= (int)($day['total'] ?? 0); ?> selesai</span>
                                        <h2><?= esc((string)($day['tasks'][0]['phase'] ?? 'Execution')); ?></h2>
                                        <p>Kerjakan task utama hari ini, lalu tandai selesai agar progress campaign terbaca.</p>
                                    </div>
                                </div>
                                <div class="admin-profit-playbook-task-list">
                                    <?php foreach ((array)($day['tasks'] ?? []) as $task): ?>
                                        <article class="admin-profit-playbook-task <?= !empty($task['completed']) ? 'is-completed' : ''; ?>">
                                            <div class="admin-profit-playbook-task-head">
                                                <div>
                                                    <span><?= esc((string)($task['priority'] ?? 'Penting')); ?> · <?= esc((string)($task['source'] ?? 'Campaign Planner')); ?></span>
                                                    <h3><?= esc((string)($task['title'] ?? 'Aksi campaign')); ?></h3>
                                                    <p><?= esc((string)($task['objective'] ?? 'Membantu campaign lebih dekat ke profit.')); ?></p>
                                                </div>
                                                <form method="post">
                                                    <?= csrf_field(); ?>
                                                    <input type="hidden" name="campaign_id" value="<?= esc((string)($campaign['campaign_id'] ?? '')); ?>">
                                                    <input type="hidden" name="task_id" value="<?= esc((string)($task['id'] ?? '')); ?>">
                                                    <input type="hidden" name="action" value="<?= !empty($task['completed']) ? 'undo_task' : 'complete_task'; ?>">
                                                    <button class="admin-btn admin-btn--soft" type="submit"><?= !empty($task['completed']) ? 'Batal Selesai' : 'Selesai'; ?></button>
                                                </form>
                                            </div>
                                            <div class="admin-profit-playbook-task-body">
                                                <div>
                                                    <strong>Kenapa</strong>
                                                    <p><?= esc((string)($task['why'] ?? 'Aksi ini dipilih dari sinyal website.')); ?></p>
                                                </div>
                                                <div>
                                                    <strong>Checklist</strong>
                                                    <ul>
                                                        <?php foreach ((array)($task['checklist'] ?? []) as $check): ?>
                                                            <li><?= esc((string)$check); ?></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                            <?php if (trim((string)($task['script'] ?? '')) !== ''): ?>
                                                <details class="admin-profit-playbook-script">
                                                    <summary>Copy/script siap pakai</summary>
                                                    <textarea readonly><?= esc((string)$task['script']); ?></textarea>
                                                </details>
                                            <?php endif; ?>
                                            <div class="admin-profit-playbook-task-foot">
                                                <small>KPI: <?= esc((string)($task['kpi'] ?? '-')); ?> · Asset: <?= esc((string)($task['asset'] ?? '-')); ?></small>
                                                <div>
                                                    <?php if (!empty($task['cta_url'])): ?><a class="admin-btn admin-btn--primary" href="<?= esc((string)$task['cta_url']); ?>"><?= esc((string)($task['cta_label'] ?? 'Buka')); ?></a><?php endif; ?>
                                                    <?php if (!empty($task['secondary_url'])): ?><a class="admin-btn admin-btn--soft" href="<?= esc((string)$task['secondary_url']); ?>"><?= esc((string)($task['secondary_label'] ?? 'Detail')); ?></a><?php endif; ?>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>

                <aside class="admin-profit-playbook-side">
                    <div class="admin-card admin-profit-playbook-side-card">
                        <span class="admin-badge">Rekomendasi</span>
                        <h2>Arah eksekusi</h2>
                        <div class="admin-profit-playbook-recommendations">
                            <?php foreach ((array)($campaign['recommendations'] ?? []) as $recommendation): ?>
                                <p><?= esc((string)$recommendation); ?></p>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-card admin-profit-playbook-side-card">
                        <span class="admin-badge">Action Harian</span>
                        <h2>Prioritas dari Profit Action</h2>
                        <div class="admin-profit-playbook-action-list">
                            <?php foreach ($todayActions as $action): ?>
                                <a href="<?= esc((string)($action['action_url'] ?? url('admin/profit-action-dashboard'))); ?>">
                                    <strong><?= esc((string)($action['title'] ?? 'Aksi profit')); ?></strong>
                                    <span><?= esc((string)($action['focus_label'] ?? 'Profit')); ?> · <?= (int)($action['score'] ?? 0); ?>/100</span>
                                </a>
                            <?php endforeach; ?>
                            <?php if (!$todayActions): ?>
                                <p>Belum ada action prioritas. Cek Profit Action Dashboard untuk membaca sinyal terbaru.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="admin-card admin-profit-playbook-side-card">
                        <span class="admin-badge">Campaign Assets</span>
                        <h2>Yang sebaiknya disiapkan</h2>
                        <div class="admin-help-mini-path">
                            <a href="<?= esc(url('admin/homepage')); ?>"><strong>Money Page</strong><span>Halaman utama yang diarahkan dari campaign.</span></a>
                            <a href="<?= esc(url('admin/trust-conversion')); ?>"><strong>Trust Block</strong><span>FAQ, testimoni, garansi, benefit, CTA.</span></a>
                            <a href="<?= esc(url('admin/forms')); ?>"><strong>Lead Form</strong><span>Jalur tangkap prospek yang mudah diisi.</span></a>
                            <a href="<?= esc(url('admin/seo-content-planner')); ?>"><strong>Content Plan</strong><span>Topik pendukung untuk traffic organik.</span></a>
                            <a href="<?= esc(url('admin/followups')); ?>"><strong>Follow-up</strong><span>Script untuk menjaga lead tetap hangat.</span></a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
