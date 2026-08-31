<?php

declare(strict_types=1);

if (!defined('APP_START')) {
    exit('Direct access not allowed.');
}

seo_noindex();

$message = (string)($_GET['message'] ?? '');
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        require_csrf();
        $action = (string)($_POST['form_action'] ?? '');
        $taskKey = (string)($_POST['task_key'] ?? '');

        if ($action === 'reset') {
            umkm_onboarding_reset();
            redirect_302('admin/onboarding-assistant?message=' . rawurlencode('Panduan onboarding sudah dimulai ulang.'));
        }

        if ($action === 'mark_complete') {
            umkm_onboarding_set_manual_completed($taskKey, true);
            redirect_302('admin/onboarding-assistant?message=' . rawurlencode('Langkah onboarding sudah ditandai selesai.'));
        }

        if ($action === 'mark_incomplete') {
            umkm_onboarding_set_manual_completed($taskKey, false);
            redirect_302('admin/onboarding-assistant?message=' . rawurlencode('Langkah onboarding sudah dibuka ulang.'));
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$report = function_exists('umkm_onboarding_report') ? umkm_onboarding_report() : ['tasks' => [], 'score' => 0, 'status' => 'Mulai Setup', 'current_day' => 1, 'context' => []];
$tasks = (array)($report['tasks'] ?? []);
$todayTask = (array)($report['today_task'] ?? ($tasks[0] ?? []));
$tomorrowTask = is_array($report['tomorrow_task'] ?? null) ? (array)$report['tomorrow_task'] : null;
$nextTodo = is_array($report['next_todo'] ?? null) ? (array)$report['next_todo'] : null;
$context = (array)($report['context'] ?? []);
$currentDay = (int)($report['current_day'] ?? 1);
$focusTask = $nextTodo ?: $todayTask;

$GLOBALS['admin_page'] = true;

set_seo([
    'title' => 'Onboarding Setup Assistant - ' . SITE_NAME,
    'description' => 'Panduan harian ringan untuk membantu admin menyiapkan website dari brand, homepage, katalog, trust block, form, SEO, hingga launch.',
    'robots' => 'noindex, nofollow',
]);

require_once ROOT_PATH . '/components/layout/head.php';
require_once ROOT_PATH . '/components/layout/header.php';
?>

<main id="main-content" class="admin-shell admin-onboarding-shell">
    <section class="admin-hero onboarding-hero">
        <div class="container admin-hero__inner">
            <div>
                <div class="admin-eyebrow">Panduan Setup Harian</div>
                <h1>Onboarding Setup Assistant</h1>
                <p>Ikuti arahan ringan per hari agar admin tidak bingung harus mulai dari mana: brand, homepage, katalog, trust, form, SEO, lalu launch.</p>
            </div>
            <div class="admin-hero__actions">
                <a class="admin-btn admin-btn--light" href="<?= esc(url('')); ?>" target="_blank" rel="noopener">Lihat Website</a>
                <a class="admin-btn admin-btn--light" href="<?= esc(url('admin/launch-readiness')); ?>">Launch Readiness</a>
            </div>
        </div>
    </section>

    <section class="admin-section">
        <div class="container">
            <?php if ($message): ?><div class="admin-alert admin-alert--success"><?= esc($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="admin-alert admin-alert--error"><?= esc($error); ?></div><?php endif; ?>

            <div class="onboarding-score-card admin-card">
                <div class="admin-form-head admin-form-head--split">
                    <div>
                        <span class="admin-badge">Progress Onboarding</span>
                        <h2><?= esc((string)($report['status'] ?? 'Mulai Setup')); ?></h2>
                        <p>Progress ini membaca data website yang sudah diisi. Admin tetap bisa menandai langkah selesai secara manual kalau sudah dicek sendiri.</p>
                    </div>
                    <div class="onboarding-score">
                        <strong><?= (int)($report['score'] ?? 0); ?>%</strong>
                        <span>Hari ke-<?= $currentDay; ?> dari 7</span>
                    </div>
                </div>
                <div class="onboarding-progress-bar" aria-label="Progress onboarding"><span style="width:<?= max(0, min(100, (int)($report['score'] ?? 0))); ?>%"></span></div>
            </div>

            <div class="onboarding-layout">
                <div class="onboarding-main">
                    <div class="admin-card onboarding-focus-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Fokus Hari Ini</span>
                            <h2><?= esc((string)($focusTask['title'] ?? 'Mulai setup website')); ?></h2>
                            <p><?= esc((string)($focusTask['summary'] ?? 'Kerjakan langkah paling penting dulu agar website cepat siap dipakai.')); ?></p>
                        </div>

                        <div class="onboarding-focus-body">
                            <div>
                                <strong>Kenapa ini penting?</strong>
                                <p><?= esc((string)($focusTask['why'] ?? 'Langkah ini membantu website terlihat lebih siap dan lebih mudah dipahami customer.')); ?></p>
                            </div>
                            <div class="onboarding-focus-actions">
                                <a class="admin-btn admin-btn--primary" href="<?= esc((string)($focusTask['href'] ?? url('admin'))); ?>"><?= esc((string)($focusTask['action_label'] ?? 'Buka Pengaturan')); ?></a>
                                <?php if (!empty($focusTask['done'])): ?>
                                    <span class="onboarding-done-pill">✓ Sudah selesai</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="onboarding-checklist">
                            <?php foreach ((array)($focusTask['checks'] ?? []) as $check): ?>
                                <a class="onboarding-check <?= !empty($check['done']) ? 'is-done' : ''; ?>" href="<?= esc((string)($check['href'] ?? ($focusTask['href'] ?? url('admin')))); ?>">
                                    <span><?= !empty($check['done']) ? '✓' : '→'; ?></span>
                                    <strong><?= esc((string)($check['label'] ?? 'Checklist')); ?></strong>
                                    <?php if (!empty($check['action'])): ?><small><?= esc((string)$check['action']); ?></small><?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="admin-card">
                        <div class="admin-form-head admin-form-head--split">
                            <div>
                                <span class="admin-badge">Rencana 7 Hari</span>
                                <h2>Urutan Setup yang Disarankan</h2>
                                <p>Setiap langkah punya tombol menuju menu terkait. Status akan otomatis ikut berubah saat datanya sudah lengkap.</p>
                            </div>
                            <form method="post" onsubmit="return confirm('Mulai ulang progress onboarding? Data website tidak dihapus, hanya progress panduan yang direset.');">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="form_action" value="reset">
                                <button type="submit" class="admin-btn admin-btn--soft">Mulai Ulang</button>
                            </form>
                        </div>

                        <div class="onboarding-task-list">
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                $status = (string)($task['status'] ?? 'todo');
                                $isCurrent = (int)($task['day'] ?? 0) === $currentDay;
                                ?>
                                <article class="onboarding-task-card onboarding-task-card--<?= esc($status); ?> <?= $isCurrent ? 'is-current' : ''; ?>">
                                    <div class="onboarding-task-day">
                                        <span><?= esc((string)($task['badge'] ?? 'Hari')); ?></span>
                                        <strong><?= !empty($task['done']) ? '✓' : ((int)($task['progress'] ?? 0) . '%'); ?></strong>
                                    </div>
                                    <div class="onboarding-task-content">
                                        <div class="onboarding-task-head">
                                            <div>
                                                <h3><?= esc((string)($task['title'] ?? '-')); ?></h3>
                                                <p><?= esc((string)($task['summary'] ?? '')); ?></p>
                                            </div>
                                            <?php if ($isCurrent): ?><span class="onboarding-current-pill">Hari ini</span><?php endif; ?>
                                        </div>
                                        <div class="onboarding-mini-checks">
                                            <?php foreach ((array)($task['checks'] ?? []) as $check): ?>
                                                <span class="<?= !empty($check['done']) ? 'is-done' : ''; ?>"><?= !empty($check['done']) ? '✓' : '•'; ?> <?= esc((string)($check['label'] ?? 'Checklist')); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="onboarding-task-actions">
                                            <a class="admin-btn <?= !empty($task['done']) ? 'admin-btn--soft' : 'admin-btn--primary'; ?>" href="<?= esc((string)($task['href'] ?? url('admin'))); ?>"><?= esc((string)($task['action_label'] ?? 'Buka')); ?></a>
                                            <form method="post">
                                                <?= csrf_field(); ?>
                                                <input type="hidden" name="task_key" value="<?= esc((string)($task['key'] ?? '')); ?>">
                                                <?php if (!empty($task['manual_done']) && empty($task['auto_done'])): ?>
                                                    <input type="hidden" name="form_action" value="mark_incomplete">
                                                    <button type="submit" class="admin-btn admin-btn--soft">Buka Lagi</button>
                                                <?php elseif (empty($task['done'])): ?>
                                                    <input type="hidden" name="form_action" value="mark_complete">
                                                    <button type="submit" class="admin-btn admin-btn--soft">Tandai Selesai</button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <aside class="onboarding-side">
                    <div class="admin-card onboarding-side-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Besok Lanjut</span>
                            <h2><?= esc((string)($tomorrowTask['title'] ?? 'Review hasil setup')); ?></h2>
                            <p><?= esc((string)($tomorrowTask['summary'] ?? 'Kalau semua langkah sudah selesai, lanjut pantau growth dan optimasi konten.')); ?></p>
                        </div>
                        <a class="admin-btn admin-btn--soft" href="<?= esc((string)($tomorrowTask['href'] ?? url('admin/growth-snapshot'))); ?>"><?= esc((string)($tomorrowTask['action_label'] ?? 'Buka Growth Snapshot')); ?></a>
                    </div>

                    <div class="admin-card onboarding-side-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Ringkasan Website</span>
                            <h2>Yang Sudah Terbaca</h2>
                            <p>Angka ini diambil dari data website saat ini.</p>
                        </div>
                        <div class="onboarding-stats">
                            <div><strong><?= (int)($context['products_count'] ?? 0); ?></strong><span>Produk/Jasa</span></div>
                            <div><strong><?= (int)($context['forms_count'] ?? 0); ?></strong><span>Form Aktif</span></div>
                            <div><strong><?= (int)($context['trust_blocks_count'] ?? 0); ?></strong><span>Trust Block</span></div>
                            <div><strong><?= (int)($context['articles_count'] ?? 0); ?></strong><span>Artikel</span></div>
                            <div><strong><?= (int)($context['seo_score'] ?? 0); ?></strong><span>Skor SEO</span></div>
                            <div><strong><?= (int)($context['launch_score'] ?? 0); ?></strong><span>Launch</span></div>
                        </div>
                    </div>

                    <div class="admin-card onboarding-side-card">
                        <div class="admin-form-head">
                            <span class="admin-badge">Tips Pakai</span>
                            <h2>Alur Anti Bingung</h2>
                            <p>Kerjakan satu langkah per hari. Tidak harus sempurna dulu; yang penting website punya identitas, penawaran, jalur kontak, dan CTA yang jelas.</p>
                        </div>
                        <div class="onboarding-help-list">
                            <a href="<?= esc(url('admin/brand')); ?>"><strong>1. Identitas</strong><span>Brand, kontak, logo, warna.</span></a>
                            <a href="<?= esc(url('admin/homepage')); ?>"><strong>2. Homepage</strong><span>Hero, section, urutan.</span></a>
                            <a href="<?= esc(url('admin/trust-conversion')); ?>"><strong>3. Konversi</strong><span>Trust block, FAQ, CTA.</span></a>
                            <a href="<?= esc(url('admin/launch-readiness')); ?>"><strong>4. Launch</strong><span>Cek sebelum promosi.</span></a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>
</main>

<?php require_once ROOT_PATH . '/components/layout/footer.php'; ?>
